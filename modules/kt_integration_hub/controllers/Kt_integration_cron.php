<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_integration_cron extends App_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->helper(KT_INTEGRATION_HUB_MODULE . '/kt_integration_hub');
        $this->load->model(KT_INTEGRATION_HUB_MODULE . '/Kt_integration_model');
    }

    public function process_jobs($key = '')
    {
        if (!is_cli()) {
            $expectedKey = defined('APP_CRON_KEY') ? (string) APP_CRON_KEY : '';
            if ($expectedKey === '' || !hash_equals($expectedKey, (string) $key)) {
                show_error('Unauthorized', 401);
                return;
            }
        }

        $result = $this->Kt_integration_model->process_due_jobs(50);

        if (is_cli()) {
            echo json_encode($result) . PHP_EOL;
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function reprovision_staff($key = '')
    {
        if (!is_cli()) {
            $expectedKey = defined('APP_CRON_KEY') ? (string) APP_CRON_KEY : '';
            if ($expectedKey === '' || !hash_equals($expectedKey, (string) $key)) {
                show_error('Unauthorized', 401);
                return;
            }
        }

        require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/ResyncAllStaffToDmsJob.php');

        $job = new ResyncAllStaffToDmsJob();
        $result = $job->handle();

        if (is_cli()) {
            echo json_encode($result) . PHP_EOL;
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function m115_pilot_product($tenantId = 0, $batch = '')
    {
        try {
        $localHost = strtolower((string) parse_url((string) APP_BASE_URL, PHP_URL_HOST));
        if (PHP_SAPI !== 'cli' || (!str_ends_with($localHost, '.test') && !in_array($localHost, ['localhost', '127.0.0.1'], true))) {
            show_error('This local QA action is CLI-only.', 403);
            return;
        }

        $tenantId = (int) $tenantId;
        $batch = trim((string) $batch);
        if ($tenantId <= 0 || !preg_match('/^[A-Za-z0-9_-]{4,64}$/', $batch)) {
            echo json_encode(['status' => 'failed', 'message' => 'A tenant id and safe batch label are required.']) . PHP_EOL;
            return;
        }

        $landlordDb = $this->db;
        $tenant = $landlordDb->where('id', $tenantId)->get(db_prefix() . 'kt_saas_tenants')->row_array();
        if (!$tenant) {
            throw new RuntimeException('CRM tenant not found.');
        }
        if (!class_exists('DatabaseSwitcher', false)) {
            require_once module_dir_path('kt_saas', 'tenant_bootstrap/DatabaseSwitcher.php');
        }
        $switch = (new DatabaseSwitcher())->switchConnection($tenant);
        if (empty($switch['switched'])) {
            throw new RuntimeException('Tenant database switch failed.');
        }

        $this->load->model('invoice_items_model');
        $this->invoice_items_model->db = $this->db;
        $code = 'M115-CRM-T' . $tenantId . '-' . strtoupper($batch);
        $existing = $this->db->where('commodity_code', $code)->get(db_prefix() . 'items')->row_array();
        $itemId = (int) ($existing['id'] ?? 0);

        if ($itemId <= 0) {
            $itemId = (int) $this->invoice_items_model->add([
                'description' => 'Sản phẩm CRM QA M11.5 ' . $batch,
                'long_description' => 'M115_LOCAL_QA batch=' . $batch . ' tenant=' . $tenantId,
                'commodity_code' => $code,
                'rate' => 115,
                'unit' => 'hộp',
                'group_id' => 0,
            ]);
        }

        if ($itemId <= 0) {
            echo json_encode(['status' => 'failed', 'message' => 'CRM product service did not return an item id.']) . PHP_EOL;
            return;
        }

        $this->db = $landlordDb;
        $this->Kt_integration_model->db = $landlordDb;
        $jobId = $this->Kt_integration_model->queue_product_sync_job($tenantId, $itemId);
        echo json_encode([
            'status' => 'created',
            'source' => 'M115_LOCAL_QA',
            'tenant_id' => $tenantId,
            'crm_product_id' => $itemId,
            'sync_job_id' => $jobId,
            'batch' => $batch,
        ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        } catch (Throwable $exception) {
            echo json_encode([
                'status' => 'failed',
                'error_code' => 'M115_PILOT_PRODUCT_FAILED',
                'message' => $exception->getMessage(),
            ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        }
    }

    public function m115_accuracy($tenantId = 0, $batch = '')
    {
        try {
            $localHost = strtolower((string) parse_url((string) APP_BASE_URL, PHP_URL_HOST));
            if (PHP_SAPI !== 'cli' || (!str_ends_with($localHost, '.test') && !in_array($localHost, ['localhost', '127.0.0.1'], true))) {
                throw new RuntimeException('This local QA action is CLI-only.');
            }
            $tenantId = (int) $tenantId;
            $batch = trim((string) $batch);
            $landlordDb = $this->db;
            $tenant = $landlordDb->where('id', $tenantId)->get(db_prefix() . 'kt_saas_tenants')->row_array();
            if (!$tenant || !preg_match('/^[A-Za-z0-9_-]{4,64}$/', $batch)) {
                throw new RuntimeException('Tenant or batch is invalid.');
            }

            if (!class_exists('DatabaseSwitcher', false)) {
                require_once module_dir_path('kt_saas', 'tenant_bootstrap/DatabaseSwitcher.php');
            }
            $switch = (new DatabaseSwitcher())->switchConnection($tenant);
            if (empty($switch['switched'])) {
                throw new RuntimeException('Tenant database switch failed.');
            }
            $product = $this->db
                ->select('id, commodity_code, description, long_description, rate, unit')
                ->like('long_description', 'M115_LOCAL_QA batch=' . $batch)
                ->get(db_prefix() . 'items')
                ->row_array();

            $this->db = $landlordDb;
            $safeRows = function (string $table, array $where, array $columns) use ($landlordDb): array {
                if (!$landlordDb->table_exists($table)) {
                    return [];
                }
                $query = $landlordDb->select(implode(',', $columns))->where($where)->order_by('id', 'desc')->limit(10);
                return $query->get($table)->result_array();
            };

            $result = [
                'status' => 'ok',
                'tenant_id' => $tenantId,
                'batch' => $batch,
                'crm_product' => $product ?: null,
                'entity_links' => $safeRows(db_prefix() . 'kt_integration_entity_links', ['tenant_id' => $tenantId], ['id','entity_type','local_id','external_id','last_synced_at']),
                'sync_jobs' => $safeRows(db_prefix() . 'kt_integration_sync_jobs', ['tenant_id' => $tenantId], ['id','job_type','entity_type','external_id','status','attempts','error_message','updated_at']),
                'checkins' => $safeRows(db_prefix() . 'dms_checkin_events', ['tenant_id' => $tenantId], ['id','dms_checkin_id','crm_staff_id','store_id','checkin_at','latitude','longitude','status']),
                'visits' => $safeRows(db_prefix() . 'dms_visit_snapshots', ['tenant_id' => $tenantId], ['id','visit_id','crm_staff_id','store_id','started_at','ended_at','visit_status']),
                'locations' => $safeRows(db_prefix() . 'dms_live_tracking_snapshots', ['tenant_id' => $tenantId], ['id','dms_user_id','crm_staff_id','latitude','longitude','last_seen_at','tracking_status']),
                'health_ledger' => $safeRows(db_prefix() . 'dms_sync_health_ledger', ['tenant_id' => $tenantId], ['id','company_id','entity_type','source_id','target_id','correlation_id','status','last_error_code','last_synced_at']),
                'location_projection' => $safeRows(db_prefix() . 'dms_location_projection', ['tenant_id' => $tenantId], ['id','company_id','source_record_id','source_record_type','marker_type','staff_id','store_id','latitude','longitude','recorded_at','last_synced_at']),
            ];
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        } catch (Throwable $exception) {
            echo json_encode(['status' => 'failed', 'error_code' => 'M115_ACCURACY_FAILED', 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        }
    }

    public function v3_identity_update_product($tenantId = 0, $batch = '', $rate = 0)
    {
        try {
            $localHost = strtolower((string) parse_url((string) APP_BASE_URL, PHP_URL_HOST));
            if (!is_cli() || (!str_ends_with($localHost, '.test') && !in_array($localHost, ['localhost','127.0.0.1'], true))) throw new RuntimeException('Local CLI only.');
            $tenantId = (int) $tenantId; $batch = trim((string) $batch); $rate = (float) $rate;
            if ($tenantId <= 0 || $rate <= 0 || !preg_match('/^V3_IDENTITY_CERT_[A-Za-z0-9_-]+$/', $batch)) throw new RuntimeException('Invalid certification arguments.');
            $landlord = $this->db; $tenant = $landlord->where('id',$tenantId)->get(db_prefix().'kt_saas_tenants')->row_array();
            require_once module_dir_path('kt_saas','tenant_bootstrap/DatabaseSwitcher.php');
            if (empty((new DatabaseSwitcher())->switchConnection($tenant)['switched'])) throw new RuntimeException('Tenant switch failed.');
            $item = $this->db->where('commodity_code','M115-CRM-T'.$tenantId.'-'.strtoupper($batch))->get(db_prefix().'items')->row_array();
            if (!$item) throw new RuntimeException('Certification product not found.');
            $this->load->model('invoice_items_model'); $this->invoice_items_model->db = $this->db;
            $ok = $this->invoice_items_model->edit(['itemid'=>(int)$item['id'],'description'=>'V3_IDENTITY_CERT_UPDATED_'.$batch,'long_description'=>'M115_LOCAL_QA batch='.$batch.' tenant='.$tenantId.' updated','rate'=>$rate,'unit'=>(string)$item['unit'],'group_id'=>(int)$item['group_id']]);
            $this->db=$landlord; $this->Kt_integration_model->db=$landlord;
            $job=$this->Kt_integration_model->queue_product_sync_job($tenantId,(int)$item['id']);
            echo json_encode(['success'=>(bool)$ok,'crm_product_id'=>(int)$item['id'],'job'=>$job], JSON_UNESCAPED_SLASHES).PHP_EOL;
        } catch (Throwable $e) { echo json_encode(['success'=>false,'safe_error'=>kt_integration_hub_sanitize_error_message($e->getMessage())]).PHP_EOL; }
    }

    public function v3_identity_create_customer($tenantId = 0, $batch = '')
    {
        try {
            $localHost = strtolower((string) parse_url((string) APP_BASE_URL, PHP_URL_HOST));
            if (!is_cli() || (!str_ends_with($localHost,'.test') && !in_array($localHost,['localhost','127.0.0.1'],true))) throw new RuntimeException('Local CLI only.');
            $tenantId=(int)$tenantId; $batch=trim((string)$batch);
            if($tenantId<=0 || !preg_match('/^V3_IDENTITY_CERT_[A-Za-z0-9_-]+$/',$batch)) throw new RuntimeException('Invalid certification arguments.');
            $landlord=$this->db; $tenant=$landlord->where('id',$tenantId)->get(db_prefix().'kt_saas_tenants')->row_array();
            require_once module_dir_path('kt_saas','tenant_bootstrap/DatabaseSwitcher.php');
            if(empty((new DatabaseSwitcher())->switchConnection($tenant)['switched'])) throw new RuntimeException('Tenant switch failed.');
            $company='V3_IDENTITY_CERT_CUSTOMER_'.$batch;
            $existing=$this->db->where('company',$company)->get(db_prefix().'clients')->row_array();
            $clientId=(int)($existing['userid']??0);
            if($clientId<=0){ $this->load->model('clients_model'); $this->clients_model->db=$this->db; $clientId=(int)$this->clients_model->add(['company'=>$company,'active'=>1,'address'=>'Identity Certification','city'=>'Bangkok','state'=>'QA','zip'=>'','country'=>0,'vat'=>'V3CERT'.$tenantId,'phonenumber'=>'','website'=>'','default_currency'=>0,'default_language'=>'english'],false); }
            $this->db=$landlord; $this->Kt_integration_model->db=$landlord;
            $job=$this->Kt_integration_model->queue_customer_sync_job($tenantId,$clientId);
            echo json_encode(['success'=>$clientId>0,'crm_customer_id'=>$clientId,'job'=>$job],JSON_UNESCAPED_SLASHES).PHP_EOL;
        } catch(Throwable $e){ echo json_encode(['success'=>false,'safe_error'=>kt_integration_hub_sanitize_error_message($e->getMessage())]).PHP_EOL; }
    }

}
