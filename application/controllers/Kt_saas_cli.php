<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_saas_cli extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        $GLOBALS['EXT']->call_hook('pre_controller_constructor');

        if (!is_cli()) {
            show_404();
        }

        if (!defined('KT_SAAS_MODULE')) {
            throw new RuntimeException('KT SaaS module is not loaded or not active.');
        }

        $this->load->helper(KT_SAAS_MODULE . '/kt_saas');
        $this->load->model(KT_SAAS_MODULE . '/Kt_saas_model');
        require_once module_dir_path(KT_SAAS_MODULE, 'provisioning/ProvisioningJobRunner.php');
        require_once module_dir_path(KT_SAAS_MODULE, 'services/OverageBillingService.php');
        require_once module_dir_path(KT_SAAS_MODULE, 'services/BillingEngineService.php');
        require_once module_dir_path(KT_SAAS_MODULE, 'services/PaymentCollectionService.php');
        require_once module_dir_path(KT_SAAS_MODULE, 'billing/RecurringBillingRunner.php');
        require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantLimitService.php');
        require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantBackupService.php');
        if (defined('KT_SEPAY_MODULE')) {
            $this->load->helper(KT_SEPAY_MODULE . '/kt_sepay');
            $this->load->model(KT_SEPAY_MODULE . '/Kt_sepay_model');
            $this->load->library(KT_SEPAY_MODULE . '/Kt_sepay_processor');
        }
        if (defined('KT_MATBAO_INVOICE_MODULE')) {
            $this->load->helper(KT_MATBAO_INVOICE_MODULE . '/kt_matbao_invoice');
            $this->load->model(KT_MATBAO_INVOICE_MODULE . '/Kt_matbao_invoice_model');
            $this->load->library(KT_MATBAO_INVOICE_MODULE . '/Matbao_invoice_client');
            $this->load->library(KT_MATBAO_INVOICE_MODULE . '/Matbao_sign_client');
        }
    }

    public function qa_auth_create()
    {
        $this->load->model('Qa_auth_model');
        $role = (string) $this->cliOption('role', 'landlord_admin');
        $tenantId = (int) $this->cliOption('tenant', 0);
        $ttl = (int) $this->cliOption('ttl', 600);
        $purpose = (string) $this->cliOption('purpose', 'QA browser proof');

        if ($tenantId > 0) {
            $tenant = $this->db
                ->where('id', $tenantId)
                ->get(db_prefix() . 'kt_saas_tenants')
                ->row_array();
            if (!$tenant) {
                $this->outputJson(['success' => false, 'message' => 'Tenant not found.']);
                return;
            }

            $tenantDb = $this->connectTenantDatabaseForCli($tenant);
            if (!$tenantDb) {
                $this->outputJson(['success' => false, 'message' => 'Tenant database is unavailable.']);
                return;
            }
            $this->Qa_auth_model->use_database($tenantDb);
        }

        $result = $this->Qa_auth_model->create_token($role, $tenantId, $ttl, $purpose);
        $this->outputJson([
            'success' => true,
            'token_id' => $result['token_id'],
            'raw_token' => $result['raw_token'],
            'expires_at' => $result['expires_at'],
            'staff_id' => $result['staff_id'],
            'redeem_route' => '/admin/qa-auth/redeem',
        ]);
    }

    public function qa_auth_revoke()
    {
        $this->load->model('Qa_auth_model');
        $this->useTenantQaAuthDatabaseIfRequested();
        $tokenId = (string) $this->cliOption('token-id', '');
        if ($tokenId === '') {
            $this->outputJson(['success' => false, 'message' => '--token-id is required']);
            return;
        }

        $this->outputJson([
            'success' => $this->Qa_auth_model->revoke($tokenId),
            'token_id' => $tokenId,
        ]);
    }

    public function qa_auth_cleanup()
    {
        $this->load->model('Qa_auth_model');
        $this->useTenantQaAuthDatabaseIfRequested();
        $this->outputJson([
            'success' => true,
            'deleted' => $this->Qa_auth_model->cleanup(),
        ]);
    }

    public function dms_rotate_bridge_credentials($tenantId = 0)
    {
        $localHost = strtolower((string) parse_url((string) APP_BASE_URL, PHP_URL_HOST));
        if (PHP_SAPI !== 'cli' || (!str_ends_with($localHost, '.test') && !in_array($localHost, ['localhost', '127.0.0.1'], true))) {
            throw new RuntimeException('Bridge credential rotation is CLI-only and local-only.');
        }

        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            throw new RuntimeException('A tenant id is required.');
        }

        $this->load->helper(KT_INTEGRATION_HUB_MODULE . '/kt_integration_hub');
        $table = db_prefix() . 'kt_integration_connections';
        $connection = $this->db
            ->where('tenant_id', $tenantId)
            ->where('provider_code', 'salesflow_dms')
            ->get($table)
            ->row_array();
        if (!$connection) {
            throw new RuntimeException('Salesrep DMS connection was not found.');
        }

        $token = 'dms_' . bin2hex(random_bytes(24));
        $secret = bin2hex(random_bytes(32));
        $this->db->where('id', (int) $connection['id'])->update($table, [
            'public_key' => $token,
            'webhook_secret_encrypted' => kt_integration_hub_encrypt_value($secret),
            'status' => 'disconnected',
            'last_error' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        echo json_encode([
            'success' => true,
            'tenant_id' => $tenantId,
            'connection_id' => (int) $connection['id'],
            'status' => 'disconnected',
            'token_fingerprint' => substr(hash('sha256', $token), 0, 12),
            'secret_fingerprint' => substr(hash('sha256', $secret), 0, 12),
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }

    public function dms_integration_ops($action = 'health', $tenantId = 0)
    {
        $localHost = strtolower((string) parse_url((string) APP_BASE_URL, PHP_URL_HOST));
        if (PHP_SAPI !== 'cli' || ENVIRONMENT !== 'development' || (!str_ends_with($localHost, '.test') && !in_array($localHost, ['localhost', '127.0.0.1'], true))) {
            throw new RuntimeException('DMS integration operations are CLI-only and local-development-only.');
        }

        $this->load->helper(KT_INTEGRATION_HUB_MODULE . '/kt_integration_hub');
        $this->load->model(KT_INTEGRATION_HUB_MODULE . '/Kt_integration_model');
        $tenantId = (int) $tenantId;
        $table = db_prefix() . 'kt_integration_sync_jobs';

        if ($action === 'run-pending-once') {
            $this->outputJson($this->Kt_integration_model->process_due_jobs(50));
            return;
        }

        if ($action === 'retry-failed') {
            $db = $this->db->where('status', 'failed');
            if ($tenantId > 0) {
                $db->where('tenant_id', $tenantId);
            }
            $db->update($table, [
                'status' => 'retry',
                'available_at' => date('Y-m-d H:i:s'),
                'locked_at' => null,
                'error_message' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->outputJson(['success' => true, 'retried' => $this->db->affected_rows(), 'tenant_id' => $tenantId ?: null]);
            return;
        }

        if ($action !== 'health') {
            throw new RuntimeException('Unsupported DMS integration operations action.');
        }

        $counts = [];
        foreach (['queued', 'retry', 'processing', 'failed', 'done'] as $status) {
            $db = $this->db->where('status', $status);
            if ($tenantId > 0) {
                $db->where('tenant_id', $tenantId);
            }
            $counts[$status] = (int) $db->count_all_results($table);
        }
        $this->outputJson([
            'success' => true,
            'tenant_id' => $tenantId ?: null,
            'queue' => $counts,
            'heartbeat' => $this->Kt_integration_model->get_integration_heartbeat_health(120),
            'cron_command' => 'php index.php kt_integration_hub/kt_integration_cron/process_jobs',
        ]);
    }

    public function dms_identity_schema_upgrade()
    {
        $localHost = strtolower((string) parse_url((string) APP_BASE_URL, PHP_URL_HOST));
        if (PHP_SAPI !== 'cli' || ENVIRONMENT !== 'development' || (!str_ends_with($localHost, '.test') && !in_array($localHost, ['localhost', '127.0.0.1'], true))) {
            throw new RuntimeException('Identity schema upgrade is CLI-only and local-development-only.');
        }
        require module_dir_path('kt_integration_hub', 'install.php');
        $this->outputJson(['success' => true, 'schema_version' => defined('KT_INTEGRATION_HUB_VERSION') ? KT_INTEGRATION_HUB_VERSION : null]);
    }

    public function dms_identity_backfill($tenantId = 0, $apply = 'dry-run')
    {
        $localHost = strtolower((string) parse_url((string) APP_BASE_URL, PHP_URL_HOST));
        if (PHP_SAPI !== 'cli' || ENVIRONMENT !== 'development' || (!str_ends_with($localHost,'.test') && !in_array($localHost,['localhost','127.0.0.1'],true))) throw new RuntimeException('Identity backfill is local CLI only.');
        $tenantId=(int)$tenantId; $doApply=$apply==='apply'; $landlord=$this->db;
        $tenant=$landlord->where('id',$tenantId)->get(db_prefix().'kt_saas_tenants')->row_array(); if(!$tenant) throw new RuntimeException('Tenant not found.');
        $companyId=(int)(kt_integration_hub_crm_company_id($tenant)??0);
        require_once module_dir_path('kt_saas','tenant_bootstrap/DatabaseSwitcher.php');
        if(empty((new DatabaseSwitcher())->switchConnection($tenant)['switched'])) throw new RuntimeException('Tenant switch failed.');
        $tenantDb=$this->db; $this->db=$landlord;
        $links=$landlord->where('tenant_id',$tenantId)->where('provider_code','salesflow_dms')->get(db_prefix().'kt_integration_entity_links')->result_array();
        $counts=['SAFE'=>0,'TOMBSTONE'=>0,'CONFLICT'=>0,'ORPHAN'=>0,'DUPLICATE'=>0]; $rows=[];
        $map=['product'=>['items','id','sku'],'customer'=>['clients','userid','customer_code'],'staff'=>['staff','staffid','email'],'warehouse'=>['kt_warehouses','id','warehouse_code']];
        foreach($links as $link){ $type=(string)$link['entity_type']; if(!isset($map[$type])) continue; [$table,$pk,$businessField]=$map[$type];
            $meta=kt_integration_hub_json_decode((string)($link['metadata_json']??''),[]); $payload=(array)($meta['last_payload']['payload']??[]); $entity=(array)($payload[$type]??($type==='customer'?($payload['customer']??[]):[]));
            $oldBusiness=strtoupper(trim((string)($entity[$businessField]??($type==='product'?($entity['sku']??''):''))));
            $source=$tenantDb->where($pk,(int)$link['local_id'])->get(db_prefix().$table)->row_array();
            $classification='SAFE'; if(!$source){$classification='TOMBSTONE';}
            $currentBusiness=$oldBusiness;
            if($source){ if($type==='product')$currentBusiness=strtoupper(trim((string)($source['commodity_code']??'')))?:$oldBusiness; elseif($type==='customer')$currentBusiness=strtoupper(trim((string)($source['customer_code']??'')))?:('CRM-CLIENT-'.(int)$source['userid']); elseif($type==='staff')$currentBusiness=strtoupper(trim((string)($source['email']??''))); elseif($type==='warehouse')$currentBusiness=strtoupper(trim((string)($source['warehouse_code']??'')));
                if($oldBusiness!=='' && $currentBusiness!=='' && $oldBusiness!==$currentBusiness)$classification='CONFLICT'; }
            $identityBusiness=$classification==='SAFE'?$currentBusiness:($oldBusiness?:('LEGACY-'.$type.'-'.(int)$link['local_id']));
            $stable='crm_'.$type.'_'.substr(hash('sha256',implode('|',['khachtot_crm',$tenantId,$companyId,$type,$identityBusiness])),0,40);
            $counts[$classification]++; $rows[]=['link_id'=>(int)$link['id'],'entity_type'=>$type,'numeric_id'=>(int)$link['local_id'],'classification'=>$classification,'stable_key'=>$stable];
            if($doApply){$landlord->where('id',(int)$link['id'])->update(db_prefix().'kt_integration_entity_links',['company_id'=>$companyId,'source_system'=>'khachtot_crm','source_numeric_id'=>(int)$link['local_id'],'source_stable_key'=>$stable,'source_fingerprint'=>(string)($link['external_hash']??''),'source_version'=>1,'target_system'=>'salesrep_dms','target_entity_id'=>(string)$link['external_id'],'lifecycle_state'=>$classification==='SAFE'?'active':'tombstoned','first_seen_at'=>$link['created_at']??kt_integration_hub_now(),'last_seen_at'=>$link['last_synced_at']??null,'tombstoned_at'=>$classification==='SAFE'?null:kt_integration_hub_now(),'updated_at'=>kt_integration_hub_now()]);}
        }
        $this->db=$landlord; $this->outputJson(['success'=>true,'tenant_id'=>$tenantId,'apply'=>$doApply,'counts'=>$counts,'rows'=>$rows]);
    }

    public function qa_data_loss_snapshot()
    {
        $tenantId = max(1, (int) $this->cliOption('tenant', 30));
        $tables = [
            'sync_scope' => db_prefix() . 'kt_dms_sync_scope',
            'staff_access' => db_prefix() . 'kt_dms_staff_access',
            'audit' => db_prefix() . 'kt_dms_landlord_actions',
        ];
        $result = ['tenant_id' => $tenantId, 'tables' => []];

        foreach ($tables as $key => $table) {
            if (!$this->db->table_exists($table)) {
                $result['tables'][$key] = ['count' => 0, 'hash' => sha1('missing')];
                continue;
            }

            $rows = $this->db
                ->where('tenant_id', $tenantId)
                ->order_by('id', 'asc')
                ->get($table)
                ->result_array();
            foreach ($rows as &$row) {
                foreach (array_keys($row) as $column) {
                    if (preg_match('/secret|token|password|payload/i', (string) $column)) {
                        unset($row[$column]);
                    }
                }
            }
            unset($row);

            $result['tables'][$key] = [
                'count' => count($rows),
                'hash' => sha1(json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            ];
        }

        $this->outputJson($result);
    }

    public function dms_identity_audit($tenantId = 0)
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            $this->outputJson(['success' => false, 'code' => 'TENANT_ID_REQUIRED']);
            return;
        }

        $tenant = $this->Kt_saas_model->get_tenant($tenantId);
        if (!$tenant) {
            $this->outputJson(['success' => false, 'code' => 'TENANT_NOT_FOUND', 'tenant_id' => $tenantId]);
            return;
        }

        $companyId = function_exists('kt_integration_hub_crm_company_id')
            ? kt_integration_hub_crm_company_id($tenant)
            : null;
        $workspaceCompanies = [];
        $table = db_prefix() . 'kt_dms_workspaces';
        if ($this->db->table_exists($table)) {
            $workspaceCompanies = array_map('intval', array_column(
                $this->db->select('company_id')->distinct()->where('tenant_id', $tenantId)->get($table)->result_array(),
                'company_id'
            ));
        }

        $this->outputJson([
            'success' => true,
            'tenant_id' => $tenantId,
            'landlord_client_id' => isset($tenant['landlord_client_id']) ? (int) $tenant['landlord_client_id'] : null,
            'canonical_company_id' => $companyId,
            'workspace_company_ids' => $workspaceCompanies,
        ]);
    }

    public function m8_data_integrity_check()
    {
        $tenantId = max(1, (int) $this->cliOption('tenant', 30));
        $tenant = $this->db->where('id', $tenantId)->get(db_prefix().'kt_saas_tenants')->row_array();
        if (!$tenant) { $this->outputJson(['success' => false, 'code' => 'TENANT_NOT_FOUND']); return; }
        $tenantDb = $this->connectTenantDatabaseForCli($tenant);
        if (!$tenantDb) { $this->outputJson(['success' => false, 'code' => 'TENANT_DATABASE_UNAVAILABLE']); return; }
        require_once module_dir_path('kt_integration_hub', 'libraries/DmsIntegrationContractStore.php');
        $store = new DmsIntegrationContractStore();
        $store->ensureSchema();
        $snapshot = static function ($db): array {
            $result = [];
            foreach (['items', 'itemable', 'payments'] as $name) {
                $table = db_prefix().$name;
                $result[$name] = $db->table_exists($table) ? ['count' => (int) $db->count_all($table), 'max_id' => (int) ($db->select_max('id')->get($table)->row_array()['id'] ?? 0)] : ['status' => 'NOT_FOUND'];
            }
            return $result;
        };
        $before = $snapshot($tenantDb);
        $requestId = 'm8_integrity_'.bin2hex(random_bytes(8));
        $payload = ['tenant_id' => $tenantId, 'company_id' => $tenantId, 'probe' => 'durable_idempotency'];
        $first = $store->beginRequest($tenantId, $tenantId, 'm8_integrity_probe', $requestId, $requestId, $payload);
        $store->completeRequest((int) $first['id'], ['status' => 'completed']);
        if (function_exists('cache')) { cache()->clean(); }
        $second = $store->beginRequest($tenantId, $tenantId, 'm8_integrity_probe', $requestId.'_replay', $requestId, $payload);
        $after = $snapshot($tenantDb);
        $this->db->where(['tenant_id' => $tenantId, 'company_id' => $tenantId, 'operation' => 'm8_integrity_probe', 'idempotency_key' => $requestId])->delete(db_prefix().'dms_integration_requests');
        $this->outputJson(['success' => $before === $after && !empty($second['duplicate']), 'tenant_id' => $tenantId, 'before' => $before, 'after' => $after, 'idempotent_replay' => !empty($second['duplicate']), 'tblitemable_unchanged' => ($before['itemable'] ?? null) === ($after['itemable'] ?? null), 'inventory_bridge' => 'WAREHOUSE_STORE_MAPPING_REQUIRED', 'outbound_collection' => 'NOT_IMPLEMENTED']);
    }

    public function provision_test($slug = 'demo')
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', trim((string) $slug)));
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'demo';
        }

        $stamp = date('YmdHis');
        $tenantCode = strtoupper(substr('TEN-' . $slug . '-' . $stamp, 0, 64));
        $subdomain = strtolower(substr($slug . '-' . date('His'), 0, 50));
        $dbName = 'khachtot_tenant_' . preg_replace('/[^a-z0-9_]+/', '_', strtolower($slug)) . '_' . date('His');

        $plan = $this->db->where('plan_code', 'trial')->get(db_prefix() . 'kt_saas_plans')->row_array();
        if (!$plan) {
            $plan = $this->db->order_by('id', 'asc')->get(db_prefix() . 'kt_saas_plans')->row_array();
        }
        if (!$plan) {
            $this->outputJson(['success' => false, 'message' => 'No plans found in landlord database.']);
            return;
        }

        $result = $this->Kt_saas_model->save_tenant([
            'tenant_code'   => $tenantCode,
            'company_name'  => 'Tenant Test ' . strtoupper($slug),
            'owner_name'    => 'Tenant Owner ' . strtoupper($slug),
            'owner_email'   => 'tenant+' . $slug . '-' . date('His') . '@example.test',
            'phone'         => '0000000000',
            'status'        => 'draft',
            'plan_id'       => $plan['id'],
            'db_name'       => $dbName,
            'db_host'       => APP_DB_HOSTNAME,
            'db_port'       => '3306',
            'db_user'       => '',
            'db_password'   => '',
            'subdomain'     => $subdomain,
            'custom_domain' => '',
            'timezone'      => 'Asia/Bangkok',
            'locale'        => 'vietnamese',
            'currency'      => 'VND',
            'storage_driver'=> 'local',
        ]);

        if (empty($result['success'])) {
            $this->outputJson($result);
            return;
        }

        $tenantId = (int) $result['id'];
        $job = $this->db
            ->where('tenant_id', $tenantId)
            ->where('job_type', 'provision_tenant')
            ->order_by('id', 'desc')
            ->get(db_prefix() . 'kt_saas_provision_jobs')
            ->row_array();

        if (!$job) {
            $this->outputJson(['success' => false, 'message' => 'Provision job was not created.', 'tenant_id' => $tenantId]);
            return;
        }

        $running = $this->Kt_saas_model->mark_provision_job_running((int) $job['id']);
        $runner = new ProvisioningJobRunner();
        $runResult = $runner->execute($running);

        if (!empty($runResult['success'])) {
            $this->Kt_saas_model->mark_provision_job_done((int) $job['id'], $runResult);
        } else {
            $this->Kt_saas_model->mark_provision_job_failed((int) $job['id'], $runResult['message'] ?? 'Provisioning failed.', $runResult);
        }

        $manifestPath = module_dir_path(KT_SAAS_MODULE, 'tenant_bootstrap/manifests/' . strtolower($tenantCode) . '.json');
        $dbExists = false;
        $query = $this->db->query("SHOW DATABASES LIKE " . $this->db->escape($dbName));
        if ($query && $query->num_rows() > 0) {
            $dbExists = true;
        }

        $this->outputJson([
            'success'       => !empty($runResult['success']),
            'tenant_id'     => $tenantId,
            'tenant_code'   => $tenantCode,
            'db_name'       => $dbName,
            'db_exists'     => $dbExists,
            'manifest_path' => $manifestPath,
            'manifest_exists' => file_exists($manifestPath),
            'job_id'        => (int) $job['id'],
            'result'        => $runResult,
        ]);
    }

    public function sepay_cert($tenantId = 22)
    {
        if (!defined('KT_SEPAY_MODULE')) {
            $this->outputJson(['success' => false, 'message' => 'KT SePay module is not loaded or not active.']);
            return;
        }

        $tenantId = (int) $tenantId;
        $tenant = $this->Kt_saas_model->get_tenant($tenantId);
        if (!$tenant) {
            $this->outputJson(['success' => false, 'message' => 'Tenant not found.', 'tenant_id' => $tenantId]);
            return;
        }
        if (!$this->isDisposableTenantForCliCert($tenant)) {
            $this->outputJson(['success' => false, 'message' => 'Refusing SePay certification against a non-disposable tenant.', 'tenant_id' => $tenantId]);
            return;
        }

        $subscription = $this->db
            ->where('tenant_id', $tenantId)
            ->where('deleted_at IS NULL', null, false)
            ->order_by('id', 'desc')
            ->get(db_prefix() . 'kt_saas_subscriptions')
            ->row_array();
        if (!$subscription) {
            $this->outputJson(['success' => false, 'message' => 'Tenant has no subscription.', 'tenant_id' => $tenantId]);
            return;
        }

        $live = $this->createSePayCertRequest($tenant, $subscription, 12345.00, 'live_match');
        $livePayload = $this->buildSePayCertPayload('S4LIVE', $live['reference_code'], 12345.00);
        $liveResult = $this->kt_sepay_processor->processIncomingTransaction($livePayload, ['source' => 's4_live_match']);
        $liveReplay = $this->kt_sepay_processor->processIncomingTransaction($livePayload, ['source' => 's4_replay', 'reprocess_existing' => true]);

        $reconcile = $this->createSePayCertRequest($tenant, $subscription, 23456.00, 'local_reconcile');
        $reconcilePayload = $this->buildSePayCertPayload('S4RECON', $reconcile['reference_code'], 23456.00);
        $insert = $this->Kt_sepay_model->insert_transaction_if_new([
            'sepay_transaction_id' => $reconcilePayload['id'],
            'gateway'              => $reconcilePayload['gateway'],
            'transaction_date'     => date('Y-m-d H:i:s'),
            'account_number'       => $reconcilePayload['accountNumber'],
            'code'                 => $reconcilePayload['code'],
            'content'              => $reconcilePayload['content'],
            'transfer_type'        => 'in',
            'transfer_amount'      => 23456.00,
            'reference_code'       => $reconcilePayload['referenceCode'],
            'status'               => 'unmatched',
            'raw_payload'          => $reconcilePayload,
        ]);
        $reprocessSummary = $this->kt_sepay_processor->reprocessUnmatchedTransactions(null, 100);
        $reconcileLogId = $this->Kt_sepay_model->create_reconciliation_log([
            'tenant_id'       => null,
            'run_id'          => 'S4-SEPAY-CERT-' . date('YmdHis'),
            'environment'     => 'production',
            'from_time'       => null,
            'to_time'         => date('Y-m-d H:i:s'),
            'total_fetched'   => 0,
            'total_matched'   => (int) ($reprocessSummary['matched'] ?? 0),
            'total_processed' => (int) ($reprocessSummary['processed'] ?? 0),
            'total_errors'    => (int) ($reprocessSummary['errors'] ?? 0),
            'metadata_json'   => json_encode(['source' => 'cli_s4_cert', 'local_reprocess' => $reprocessSummary], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        ]);

        $unknownPayload = $this->buildSePayCertPayload('S4UNMATCH', 'S4UNKNOWN' . date('His'), 9999.00);
        $unknownResult = $this->kt_sepay_processor->processIncomingTransaction($unknownPayload, ['source' => 's4_unmatched_alert']);

        $liveInvoice = $this->Kt_saas_model->get_invoice((int) $live['invoice_id']);
        $reconcileInvoice = $this->Kt_saas_model->get_invoice((int) $reconcile['invoice_id']);
        $liveRequest = $this->Kt_sepay_model->get_payment_request((int) $live['payment_request_id']);
        $reconcileRequest = $this->Kt_sepay_model->get_payment_request((int) $reconcile['payment_request_id']);
        $liveTransaction = $this->db->where('sepay_transaction_id', $livePayload['id'])->get(db_prefix() . 'kt_sepay_transactions')->row_array();
        $reconcileTransaction = $this->db->where('sepay_transaction_id', $reconcilePayload['id'])->get(db_prefix() . 'kt_sepay_transactions')->row_array();
        $unknownTransaction = $this->db->where('sepay_transaction_id', $unknownPayload['id'])->get(db_prefix() . 'kt_sepay_transactions')->row_array();

        $livePayments = $this->db
            ->where('payment_reference', $live['reference_code'])
            ->where('deleted_at IS NULL', null, false)
            ->get(db_prefix() . 'kt_saas_payments')
            ->result_array();

        $emailLogs = $this->db
            ->where_in('related_type', ['invoice', 'payment'])
            ->where_in('related_id', [
                (string) $live['invoice_id'],
                (string) $reconcile['invoice_id'],
                (string) ($unknownTransaction['id'] ?? 0),
            ])
            ->order_by('id', 'desc')
            ->get(db_prefix() . 'kt_saas_email_logs')
            ->result_array();

        $this->outputJson([
            'success' => ((string) ($liveInvoice['status'] ?? '') === 'paid')
                && ((string) ($liveRequest['status'] ?? '') === 'paid')
                && ((string) ($liveTransaction['status'] ?? '') === 'processed')
                && ((string) ($reconcileInvoice['status'] ?? '') === 'paid')
                && ((string) ($reconcileRequest['status'] ?? '') === 'paid')
                && ((string) ($reconcileTransaction['status'] ?? '') === 'processed')
                && count($livePayments) === 1,
            'tenant_id' => $tenantId,
            'tenant_code' => $tenant['tenant_code'] ?? null,
            'live_match' => [
                'invoice_id' => (int) $live['invoice_id'],
                'payment_request_id' => (int) $live['payment_request_id'],
                'reference_code' => $live['reference_code'],
                'result' => $liveResult,
                'replay_result' => $liveReplay,
                'invoice_status' => $liveInvoice['status'] ?? null,
                'payment_request_status' => $liveRequest['status'] ?? null,
                'transaction_status' => $liveTransaction['status'] ?? null,
                'payment_count_for_reference' => count($livePayments),
            ],
            'reconciliation' => [
                'invoice_id' => (int) $reconcile['invoice_id'],
                'payment_request_id' => (int) $reconcile['payment_request_id'],
                'transaction_inserted_id' => (int) ($insert['id'] ?? 0),
                'reconciliation_log_id' => $reconcileLogId,
                'summary' => $reprocessSummary,
                'invoice_status' => $reconcileInvoice['status'] ?? null,
                'payment_request_status' => $reconcileRequest['status'] ?? null,
                'transaction_status' => $reconcileTransaction['status'] ?? null,
            ],
            'unmatched_alert' => [
                'result' => $unknownResult,
                'transaction_id' => (int) ($unknownTransaction['id'] ?? 0),
                'transaction_status' => $unknownTransaction['status'] ?? null,
            ],
            'email_logs' => array_map(function ($row) {
                return [
                    'id' => (int) $row['id'],
                    'tenant_id' => $row['tenant_id'] !== null ? (int) $row['tenant_id'] : null,
                    'provider' => $row['provider'],
                    'recipient' => $row['recipient'],
                    'subject' => $row['subject'],
                    'status' => $row['status'],
                    'message_id' => $row['message_id'],
                    'related_type' => $row['related_type'],
                    'related_id' => $row['related_id'],
                    'sent_at' => $row['sent_at'],
                ];
            }, $emailLogs),
        ]);
    }

    private function createSePayCertRequest(array $tenant, array $subscription, $amount, $reason)
    {
        $now = date('Y-m-d H:i:s');
        $invoiceNumber = 'S4-SEPAY-' . strtoupper($reason) . '-' . date('YmdHis') . '-' . random_int(100, 999);
        $payload = [
            'tenant_id' => (int) $tenant['id'],
            'subscription_id' => (int) $subscription['id'],
            'invoice_number' => $invoiceNumber,
            'status' => 'pending_payment',
            'currency' => 'VND',
            'subtotal' => (float) $amount,
            'tax_total' => 0,
            'discount_total' => 0,
            'grand_total' => (float) $amount,
            'issued_at' => $now,
            'due_date' => date('Y-m-d', strtotime('+7 days')),
            'gateway' => 'sepay',
            'payload_json' => json_encode(['reason' => 's4_sepay_cert', 'scenario' => $reason], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => null,
            'updated_by' => null,
        ];
        $this->db->insert(db_prefix() . 'kt_saas_invoices', $payload);
        $invoiceId = (int) $this->db->insert_id();
        $reference = 'KTSAAS' . (int) $tenant['id'] . 'S' . (int) $subscription['id'] . 'I' . $invoiceId;
        $requestId = $this->Kt_sepay_model->create_payment_request([
            'context_type' => 'kt_saas_subscription',
            'context_id' => (int) $subscription['id'],
            'tenant_id' => (int) $tenant['id'],
            'invoice_id' => $invoiceId,
            'subscription_id' => (int) $subscription['id'],
            'amount' => (float) $amount,
            'currency' => 'VND',
            'reference_code' => $reference,
            'access_token' => app_generate_hash(),
            'description' => 'S4 SePay certification ' . $reason,
            'status' => 'pending',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'metadata_json' => json_encode(['source' => 'cli_s4_cert', 'scenario' => $reason], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        ]);

        return [
            'invoice_id' => $invoiceId,
            'payment_request_id' => $requestId,
            'reference_code' => $reference,
        ];
    }

    private function buildSePayCertPayload($prefix, $reference, $amount)
    {
        return [
            'id' => $prefix . date('YmdHis') . random_int(100, 999),
            'gateway' => 'VietinBank',
            'transactionDate' => date('Y-m-d H:i:s'),
            'accountNumber' => '105001262191',
            'code' => $reference,
            'content' => 'Thanh toan ' . $reference,
            'transferType' => 'in',
            'transferAmount' => (float) $amount,
            'referenceCode' => $reference,
        ];
    }

    public function matbao_cert($tenantId = 22)
    {
        if (!defined('KT_MATBAO_INVOICE_MODULE')) {
            $this->outputJson(['success' => false, 'message' => 'KT MatBao Invoice module is not loaded or not active.']);
            return;
        }

        $tenantId = (int) $tenantId;
        $tenant = $this->Kt_saas_model->get_tenant($tenantId);
        if (!$tenant) {
            $this->outputJson(['success' => false, 'message' => 'Tenant not found.', 'tenant_id' => $tenantId]);
            return;
        }
        if (!$this->isDisposableTenantForCliCert($tenant)) {
            $this->outputJson(['success' => false, 'message' => 'Refusing MatBao certification against a non-disposable tenant.', 'tenant_id' => $tenantId]);
            return;
        }

        require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');
        $entitlements = new TenantEntitlementService();
        $landlordSettings = $this->Kt_matbao_invoice_model->get_hddt_account(null, 'landlord');
        $landlordCa = $this->Kt_matbao_invoice_model->get_ca_account(null, 'landlord');
        $canAccess = (bool) $entitlements->canUseModule($tenantId, KT_MATBAO_INVOICE_MODULE);
        $planEnabled = (bool) $entitlements->getFeatureValue($tenantId, 'matbao_invoice.enabled', false);
        $tenantConfigFeature = (bool) $entitlements->getFeatureValue($tenantId, 'matbao_invoice.tenant_config', false);
        $landlordAllowsOverride = !empty($landlordSettings['is_active']) && !empty($landlordSettings['allow_tenant_override']);

        $tenantSettingsResult = $this->runMatBaoTenantSettingsCert($tenantId, $landlordSettings ?: [], $landlordCa ?: []);
        $settings = $tenantSettingsResult['tenant_hddt'] ?: $this->Kt_matbao_invoice_model->resolve_tenant_effective_settings($tenant);
        $settings['invoice_base_url'] = $settings['invoice_base_url'] ?? ($settings['base_url'] ?? '');

        $caSettings = $tenantSettingsResult['tenant_ca'] ?: $this->Kt_matbao_invoice_model->resolve_tenant_effective_ca_account($tenant);

        $issue = $this->runMatBaoIssueCert($tenant, $settings ?: []);
        $sign = $this->runMatBaoSignCert($tenant, $settings ?: [], $caSettings ?: [], $issue);
        $quota = $this->runMatBaoQuotaCert($tenant);
        $hsm = $this->runMatBaoHsmCert($tenant, $caSettings ?: []);
        $alerts = $this->runMatBaoAlertCert($tenant, $issue);
        $idempotency = $this->runMatBaoIdempotencyCert($tenant, $issue);

        $recordId = (int) ($issue['record_id'] ?? 0);
        $record = $recordId > 0 ? $this->Kt_matbao_invoice_model->get_record($recordId) : null;
        $logs = $this->db
            ->where('tenant_id', $tenantId)
            ->where('created_at >=', date('Y-m-d H:i:s', strtotime('-20 minutes')))
            ->order_by('id', 'desc')
            ->limit(30)
            ->get(db_prefix() . 'kt_matbao_invoice_logs')
            ->result_array();
        $emailLogs = $this->db
            ->where('tenant_id', $tenantId)
            ->where('created_at >=', date('Y-m-d H:i:s', strtotime('-20 minutes')))
            ->order_by('id', 'desc')
            ->limit(30)
            ->get(db_prefix() . 'kt_saas_email_logs')
            ->result_array();

        $success = $canAccess
            && ($tenantConfigFeature || $landlordAllowsOverride)
            && !empty($tenantSettingsResult['saved'])
            && !empty($tenantSettingsResult['reloaded'])
            && !empty($issue['record_saved'])
            && !empty($issue['provider_success'])
            && !empty($sign['signed'])
            && !empty($idempotency['no_duplicate_record'])
            && !empty($quota['low_alert_checked'])
            && !empty($quota['exhausted_alert_checked'])
            && !empty($hsm['provider_health_success']);

        $this->outputJson([
            'success' => $success,
            'tenant_id' => $tenantId,
            'tenant_code' => $tenant['tenant_code'] ?? null,
            'entitlement' => [
                'can_use_kt_matbao_invoice' => $canAccess,
                'matbao_invoice_enabled' => $planEnabled,
                'tenant_config_feature' => $tenantConfigFeature,
                'landlord_allow_tenant_override' => $landlordAllowsOverride,
                'settings_307_root_cause' => $tenantConfigFeature ? null : 'tenant_config feature is absent; tenant settings must be allowed by landlord allow_tenant_override.',
            ],
            'tenant_settings' => $tenantSettingsResult,
            'issue_flow' => $issue,
            'sign_flow' => $sign,
            'quota' => $quota,
            'hsm' => $hsm,
            'alerts' => $alerts,
            'idempotency' => $idempotency,
            'accounting_trace' => [
                'record_id' => $recordId,
                'record_status' => $record['local_status'] ?? null,
                'source_type' => $record['source_type'] ?? null,
                'source_id' => $record['source_id'] ?? null,
                'ma_tra_cuu' => $record['ma_tra_cuu'] ?? null,
                'ma_so_hdon' => $record['ma_so_hdon'] ?? null,
                'pdf_url_present' => !empty($record['pdf_url']),
                'xml_url_present' => !empty($record['xml_url']),
            ],
            'recent_provider_logs' => array_map([$this, 'summarizeMatBaoProviderLog'], $logs),
            'recent_email_logs' => array_map([$this, 'summarizeEmailLog'], $emailLogs),
        ]);
    }

    public function email_cert($tenantId = 22, $email = 'xemthach@gmail.com')
    {
        $this->tenant_email_test_cert($tenantId, $email);
    }

    public function tenant_email_test_cert($tenantId = 22, $email = 'xemthach@gmail.com')
    {
        try {
            $tenantId = (int) $tenantId;
            $email = trim((string) $email);
            $tenant = $this->Kt_saas_model->get_tenant($tenantId);
            if (!$tenant) {
                $this->outputJson(['success' => false, 'message' => 'Tenant not found.', 'tenant_id' => $tenantId]);
                return;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->outputJson(['success' => false, 'message' => 'Invalid test email.', 'recipient' => $email]);
                return;
            }

            require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEmailProviderService.php');
            $service = new TenantEmailProviderService();
            $ctx = $service->resolveForTenant($tenantId, 'transactional');
            if (($ctx['provider'] ?? '') === 'blocked') {
                $this->outputJson([
                    'success' => false,
                    'tenant_id' => $tenantId,
                    'provider' => 'blocked',
                    'error' => (string) ($ctx['error'] ?? 'Provider is blocked.'),
                ]);
                return;
            }

            $subject = 'KT SaaS Tenant Email Test - ' . date('Y-m-d H:i:s');
            $ctx['tenant_id'] = $tenantId;
            $ctx['related_type'] = 'tenant_email_test';
            $ctx['related_id'] = (string) $tenantId;
            $ctx['event_key'] = 'tenant_email_settings_test';
            $ctx['dedupe_key'] = '';
            $service->applyRuntimeTransport($ctx);
            $this->load->model('Emails_model');
            $ok = $this->emails_model->send_simple_email($email, $subject, 'Tenant provider connectivity test.');
            $messageId = method_exists($this->emails_model, 'get_last_send_message_id') ? trim((string) $this->emails_model->get_last_send_message_id()) : '';
            $error = method_exists($this->emails_model, 'get_last_send_error') ? trim((string) $this->emails_model->get_last_send_error()) : '';
            $errorCode = method_exists($this->emails_model, 'get_last_send_error_code') ? (int) $this->emails_model->get_last_send_error_code() : 0;
            $service->applyRuntimeTransport(['transport' => null]);

            $log = $this->db
                ->where('tenant_id', $tenantId)
                ->where('recipient', $email)
                ->where('subject', $subject)
                ->order_by('id', 'desc')
                ->get(db_prefix() . 'kt_saas_email_logs')
                ->row_array();
            if (!$log) {
                $service->logEmailResult(
                    $email,
                    $subject,
                    $ok ? 'sent' : 'failed',
                    $error,
                    (string) ($ctx['provider'] ?? ''),
                    $tenantId,
                    'notification',
                    $messageId,
                    (string) ($ctx['from_email'] ?? ''),
                    'tenant_email_test',
                    (string) $tenantId
                );
                $log = $this->db
                    ->where('tenant_id', $tenantId)
                    ->where('recipient', $email)
                    ->where('subject', $subject)
                    ->order_by('id', 'desc')
                    ->get(db_prefix() . 'kt_saas_email_logs')
                    ->row_array();
            }

            $this->outputJson([
                'success' => (bool) $ok,
                'tenant_id' => $tenantId,
                'tenant_code' => $tenant['tenant_code'] ?? null,
                'recipient' => $email,
                'provider' => (string) ($ctx['provider'] ?? ''),
                'source' => (string) ($ctx['source'] ?? ''),
                'transport' => is_array($ctx['transport'] ?? null) ? (string) ($ctx['transport']['protocol'] ?? '') : '',
                'from_email' => (string) ($ctx['from_email'] ?? ''),
                'subject' => $subject,
                'message_id' => $messageId,
                'error' => $error,
                'error_code' => $errorCode,
                'email_log' => $log ? [
                    'id' => (int) $log['id'],
                    'status' => $log['status'],
                    'message_id' => $log['message_id'],
                    'related_type' => $log['related_type'],
                    'related_id' => $log['related_id'],
                ] : null,
            ]);
        } catch (Throwable $e) {
            $this->outputJson([
                'success' => false,
                'tenant_id' => isset($tenantId) ? (int) $tenantId : null,
                'recipient' => isset($email) ? (string) $email : null,
                'error' => $e->getMessage(),
                'trace' => array_slice(explode(PHP_EOL, $e->getTraceAsString()), 0, 8),
            ]);
        } finally {
            if (isset($service) && $service instanceof TenantEmailProviderService) {
                $service->applyRuntimeTransport(['transport' => null]);
            }
        }
    }

    public function dms_auth_mail_test($tenantId = 27, $email = 'sme.mini@ranhroi.net')
    {
        try {
            $tenantId = (int) $tenantId;
            $email = trim((string) $email);

            $tenant = $this->Kt_saas_model->get_tenant($tenantId);
            if (!$tenant) {
                $this->outputJson(['success' => false, 'message' => 'Tenant not found.', 'tenant_id' => $tenantId]);
                return;
            }

            require_once APPPATH . '../modules/kt_integration_hub/libraries/DmsMailBridgeService.php';
            $service = new DmsMailBridgeService();
            $result = $service->send($tenant, [
                'tenant_id' => $tenantId,
                'crm_tenant_id' => $tenantId,
                'crm_company_id' => $tenantId,
                'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
                'crm_staff_id' => 3,
                'to' => $email,
                'template' => 'dms_password_reset',
                'subject' => 'Reset your DMS password',
                'data' => [
                    'name' => (string) ($tenant['owner_name'] ?? 'Tenant Admin'),
                    'action_url' => 'http://salesrep.test/reset-password/runtime-debug-token?email=' . rawurlencode($email),
                    'login_url' => 'http://salesrep.test/login',
                    'expires_in_minutes' => 60,
                    'support_email' => 'no-reply@mail.khachtot.com',
                    'email_signature' => 'Khach Tot CRM',
                    'app_name' => 'Salesflow DMS',
                ],
            ], [
                'idempotency_key' => 'dms-auth-mail-test-' . date('YmdHis'),
            ]);

            $this->outputJson($result);
        } catch (Throwable $e) {
            $this->outputJson([
                'success' => false,
                'tenant_id' => isset($tenantId) ? (int) $tenantId : null,
                'recipient' => isset($email) ? (string) $email : null,
                'error' => $e->getMessage(),
                'trace' => array_slice(explode(PHP_EOL, $e->getTraceAsString()), 0, 8),
            ]);
        }
    }

    private function runMatBaoTenantSettingsCert($tenantId, array $landlordSettings, array $landlordCa)
    {
        $password = kt_matbao_invoice_decrypt($landlordSettings['password_encrypted'] ?? '');
        $caPassword = kt_matbao_invoice_decrypt($landlordCa['password_encrypted'] ?? '');

        $savedHddtId = $this->Kt_matbao_invoice_model->save_hddt_account([
            'environment' => $landlordSettings['environment'] ?? 'demo',
            'invoice_base_url' => $landlordSettings['base_url'] ?? '',
            'mst' => $landlordSettings['mst'] ?? '',
            'username' => $landlordSettings['username'] ?? '',
            'password' => $password,
            'default_khmshdon' => $landlordSettings['default_khmshdon'] ?? '',
            'default_khhdon' => $landlordSettings['default_khhdon'] ?? '',
            'default_year' => $landlordSettings['default_year'] ?? date('Y'),
            'fallback_policy' => 'use_landlord',
            'auto_issue' => 0,
            'auto_sign' => 0,
            'is_active' => 1,
        ], $tenantId, 'tenant');

        $savedCaId = $this->Kt_matbao_invoice_model->save_ca_account([
            'environment' => $landlordCa['environment'] ?? 'demo',
            'ca_base_url' => $landlordCa['base_url'] ?? '',
            'ca_taxcode' => $landlordCa['taxcode'] ?? '',
            'ca_username' => $landlordCa['username'] ?? '',
            'ca_password' => $caPassword,
            'signing_mode' => $landlordCa['signing_mode'] ?? 'hddt_sign_invoice',
            'ca_is_active' => 1,
        ], $tenantId, 'tenant');

        $tenantHddt = $this->Kt_matbao_invoice_model->get_hddt_account($tenantId, 'tenant');
        $tenantCa = $this->Kt_matbao_invoice_model->get_ca_account($tenantId, 'tenant');

        return [
            'saved' => $savedHddtId > 0 && $savedCaId > 0,
            'hddt_account_id' => $savedHddtId,
            'ca_account_id' => $savedCaId,
            'reloaded' => !empty($tenantHddt) && !empty($tenantCa),
            'tenant_hddt' => $tenantHddt,
            'tenant_ca' => $tenantCa,
        ];
    }

    private function runMatBaoIssueCert(array $tenant, array $settings)
    {
        $tenantId = (int) $tenant['id'];
        $sourceId = (int) date('His') . random_int(100, 999);
        $existing = $this->Kt_matbao_invoice_model->get_record_by_source('cli_matbao_cert', $sourceId, $tenantId);
        if ($existing) {
            return ['record_id' => (int) $existing['id'], 'record_saved' => true, 'provider_skipped' => true];
        }

        $settings['invoice_base_url'] = $settings['invoice_base_url'] ?? ($settings['base_url'] ?? '');
        $maTraCuu = 'S5MB' . $tenantId . date('His') . random_int(100, 999);
        $payload = $this->buildMatBaoInvoicePayload($settings, $maTraCuu);
        $providerResult = [];
        $providerPassword = kt_matbao_invoice_decrypt($settings['password_encrypted'] ?? '');
        if (!empty($settings['invoice_base_url']) && !empty($settings['mst']) && !empty($settings['username']) && $providerPassword !== '') {
            $providerResult = $this->matbao_invoice_client->createInvoice($settings, [$payload], $tenantId);
        } else {
            $providerResult = ['success' => false, 'error' => 'Missing effective MatBao invoice credentials.'];
        }

        $response = is_array($providerResult['response'] ?? null) ? $providerResult['response'] : [];
        $providerData = $this->extractMatBaoProviderData($response);
        $status = !empty($providerResult['success']) ? 'issued' : 'issue_failed';
        $recordId = $this->Kt_matbao_invoice_model->save_record([
            'tenant_id' => $tenantId,
            'source_type' => 'cli_matbao_cert',
            'source_id' => $sourceId,
            'seller_scope' => (string) ($settings['account_scope'] ?? 'tenant'),
            'credential_scope' => (string) ($settings['account_scope'] ?? 'tenant'),
            'khmshdon' => (string) ($payload['KHMSHDon'] ?? ''),
            'khhdon' => (string) ($payload['KHHDon'] ?? ''),
            'ma_tra_cuu' => $maTraCuu,
            'mt_chieu' => (string) ($payload['MTChieu'] ?? ''),
            'ma_so_hdon' => (string) ($providerData['ma_so_hdon'] ?? ''),
            'inv_id' => (string) ($providerData['inv_id'] ?? ''),
            'invoice_type' => 'standard',
            'local_status' => $status,
            'issue_mode' => 'issue',
            'nlap' => date('Y-m-d H:i:s'),
            'total_before_tax' => 1000,
            'total_tax' => 0,
            'total_amount' => 1000,
            'pdf_url' => (string) ($providerData['pdf_url'] ?? ''),
            'xml_url' => (string) ($providerData['xml_url'] ?? ''),
            'raw_request_snapshot' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'raw_response_snapshot' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'error_message' => (string) ($providerResult['error'] ?? ''),
            'issued_at' => !empty($providerResult['success']) ? date('Y-m-d H:i:s') : null,
        ], [[
            'item_source_id' => 0,
            'tchat' => 1,
            'stt' => 1,
            'mhhdvu' => 'S5CERT',
            'thhdvu' => 'Dịch vụ kiểm thử MatBao S5',
            'dvtinh' => 'Lần',
            'sluong' => 1,
            'dgia' => 1000,
            'thtien' => 1000,
            'tsuat' => 0,
            'tthue' => 0,
            'tgtien' => 1000,
        ]]);

        if (empty($providerResult['success'])) {
            $this->sendMatBaoCertEvent('invoice_issue_failed', $tenant, [
                'related_type' => 'matbao_invoice',
                'related_id' => (string) $recordId,
                'error_message' => (string) ($providerResult['error'] ?? 'MatBao issue failed'),
            ]);
        }

        return [
            'record_id' => $recordId,
            'record_saved' => $recordId > 0,
            'source_id' => $sourceId,
            'provider_success' => !empty($providerResult['success']),
            'http_code' => $providerResult['http_code'] ?? null,
            'provider_error' => $providerResult['error'] ?? null,
            'ma_tra_cuu' => $maTraCuu,
            'ma_so_hdon' => $providerData['ma_so_hdon'] ?? null,
            'inv_id' => $providerData['inv_id'] ?? null,
        ];
    }

    private function runMatBaoSignCert(array $tenant, array $settings, array $caSettings, array $issue)
    {
        $tenantId = (int) $tenant['id'];
        $recordId = (int) ($issue['record_id'] ?? 0);
        $maSoHDon = (string) ($issue['ma_so_hdon'] ?? '');
        $maTraCuu = (string) ($issue['ma_tra_cuu'] ?? '');
        if ($recordId < 1 || $maSoHDon === '') {
            $this->sendMatBaoCertEvent('invoice_sign_failed', $tenant, [
                'related_type' => 'matbao_invoice',
                'related_id' => (string) $recordId,
                'error_message' => 'No issued provider invoice available for signing.',
            ]);
            return ['attempted' => false, 'signed' => false, 'reason' => 'No issued provider invoice available.'];
        }

        $settings['invoice_base_url'] = $settings['invoice_base_url'] ?? ($settings['base_url'] ?? '');
        $providerResult = $this->matbao_invoice_client->signInvoice($settings, [
            'MaSoHDon' => $maSoHDon,
            'MaTraCuu' => $maTraCuu,
        ], $tenantId);
        if (!empty($providerResult['success'])) {
            $this->Kt_matbao_invoice_model->save_record([
                'id' => $recordId,
                'tenant_id' => $tenantId,
                'source_type' => 'cli_matbao_cert',
                'source_id' => (int) ($issue['source_id'] ?? 0),
                'local_status' => 'signed',
                'signed_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $this->sendMatBaoCertEvent('invoice_sign_failed', $tenant, [
                'related_type' => 'matbao_invoice',
                'related_id' => (string) $recordId,
                'error_message' => (string) ($providerResult['error'] ?? 'MatBao sign failed'),
            ]);
        }

        $caHealth = [];
        if (!empty($caSettings['base_url']) && !empty($caSettings['taxcode']) && !empty($caSettings['username']) && kt_matbao_invoice_decrypt($caSettings['password_encrypted'] ?? '') !== '') {
            $caHealth = $this->matbao_sign_client->login($caSettings, $tenantId);
        }

        return [
            'attempted' => true,
            'signed' => !empty($providerResult['success']),
            'http_code' => $providerResult['http_code'] ?? null,
            'provider_error' => $providerResult['error'] ?? null,
            'ca_health_success' => !empty($caHealth['success']),
            'ca_health_http_code' => $caHealth['http_code'] ?? null,
            'ca_health_error' => $caHealth['error'] ?? null,
        ];
    }

    private function runMatBaoQuotaCert(array $tenant)
    {
        $tenantId = (int) $tenant['id'];
        $now = date('Y-m-d H:i:s');
        $this->db
            ->where('tenant_id', $tenantId)
            ->where('package_code', 'S5-CERT-EINVOICE')
            ->update(db_prefix() . 'kt_saas_tenant_addons', [
                'status' => 'cert_archived',
                'quantity_remaining' => 0,
                'updated_at' => $now,
            ]);

        $this->db->insert(db_prefix() . 'kt_saas_tenant_addons', [
            'tenant_id' => $tenantId,
            'subscription_id' => null,
            'order_id' => null,
            'package_id' => 0,
            'provider' => 'matbao',
            'service_type' => 'einvoice',
            'package_code' => 'S5-CERT-EINVOICE',
            'quantity_purchased' => 10,
            'quantity_used' => 8,
            'quantity_remaining' => 2,
            'starts_at' => $now,
            'ends_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'status' => 'active',
            'notes' => 'S5 MatBao certification disposable quota.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $addonId = (int) $this->db->insert_id();

        $this->db->where('id', $addonId)->update(db_prefix() . 'kt_saas_tenant_addons', [
            'quantity_used' => 9,
            'quantity_remaining' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->insert(db_prefix() . 'kt_saas_addon_usage_logs', [
            'tenant_id' => $tenantId,
            'addon_id' => $addonId,
            'service_type' => 'einvoice',
            'action' => 'consume',
            'quantity_delta' => -1,
            'before_quantity' => 2,
            'after_quantity' => 1,
            'reference_type' => 's5_matbao_cert',
            'reference_id' => $addonId,
            'created_by' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $low = $this->sendMatBaoCertEvent('einvoice_quota_low', $tenant, [
            'related_type' => 'usage',
            'related_id' => (string) $addonId,
            'einvoice_quota' => '10',
            'einvoice_remaining' => '1',
            'threshold' => 90,
        ]);

        $exhausted = $this->sendMatBaoCertEvent('einvoice_quota_exhausted', $tenant, [
            'related_type' => 'usage',
            'related_id' => (string) $addonId,
            'einvoice_quota' => '10',
            'einvoice_remaining' => '0',
            'error_message' => 'Insufficient eInvoice quota',
        ]);
        $summary = $this->Kt_matbao_invoice_model->get_tenant_addon_usage_summary($tenantId);

        return [
            'addon_id' => $addonId,
            'low_result' => $low,
            'exhausted_result' => $exhausted,
            'summary_after' => $summary,
            'low_alert_checked' => !empty($low['success']),
            'exhausted_alert_checked' => !empty($exhausted['success']),
        ];
    }

    private function runMatBaoHsmCert(array $tenant, array $caSettings)
    {
        $tenantId = (int) $tenant['id'];
        $now = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'kt_saas_tenant_addons', [
            'tenant_id' => $tenantId,
            'subscription_id' => null,
            'order_id' => null,
            'package_id' => 0,
            'provider' => 'matbao',
            'service_type' => 'hsm_signature',
            'package_code' => 'S5-CERT-HSM',
            'quantity_purchased' => 1,
            'quantity_used' => 0,
            'quantity_remaining' => 1,
            'starts_at' => $now,
            'ends_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'status' => 'active',
            'notes' => 'S5 MatBao certification disposable HSM.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $addonId = (int) $this->db->insert_id();
        $summary = $this->Kt_matbao_invoice_model->get_tenant_addon_usage_summary($tenantId);
        $credentialsPresent = !empty($caSettings['base_url'])
            && !empty($caSettings['taxcode'])
            && !empty($caSettings['username'])
            && kt_matbao_invoice_decrypt($caSettings['password_encrypted'] ?? '') !== '';
        $this->sendMatBaoCertEvent('hsm_expiry_warning', $tenant, [
            'related_type' => 'addon',
            'related_id' => (string) $addonId,
            'hsm_status' => 'active',
            'expiry_date' => date('Y-m-d', strtotime('+5 days')),
        ]);

        return [
            'addon_id' => $addonId,
            'hsm_active_count' => (int) ($summary['hsm_active'] ?? 0),
            'provider_credentials_present' => $credentialsPresent,
            'provider_health_success' => false,
            'provider_health_http_code' => null,
            'provider_health_error' => 'Not called in CLI certification because current CA/HSM provider login fails and hard-stops before report output.',
            'expiry_warning_dispatched' => true,
        ];
    }

    private function runMatBaoAlertCert(array $tenant, array $issue)
    {
        $recordId = (string) ((int) ($issue['record_id'] ?? 0));
        return [
            'einvoice_activated' => $this->sendMatBaoCertEvent('einvoice_activated', $tenant, ['related_type' => 'addon', 'related_id' => 's5-activated-' . date('His')]),
            'invoice_issue_failed' => $this->sendMatBaoCertEvent('invoice_issue_failed', $tenant, ['related_type' => 'matbao_invoice', 'related_id' => $recordId, 'error_message' => 'S5 controlled issue-failed alert verification.']),
            'invoice_sign_failed' => $this->sendMatBaoCertEvent('invoice_sign_failed', $tenant, ['related_type' => 'matbao_invoice', 'related_id' => $recordId, 'error_message' => 'S5 controlled sign-failed alert verification.']),
            'provider_connection_failed' => [
                'success' => false,
                'status' => 'not_verified',
                'reason' => 'Skipped because current MatBao provider credentials are incomplete; live client login would hard-stop before certification output.',
            ],
        ];
    }

    private function runMatBaoIdempotencyCert(array $tenant, array $issue)
    {
        $tenantId = (int) $tenant['id'];
        $sourceId = (int) ($issue['source_id'] ?? 0);
        $before = $this->db
            ->where('tenant_id', $tenantId)
            ->where('source_type', 'cli_matbao_cert')
            ->where('source_id', $sourceId)
            ->count_all_results(db_prefix() . 'kt_matbao_invoice_records');
        $existing = $this->Kt_matbao_invoice_model->get_record_by_source('cli_matbao_cert', $sourceId, $tenantId);
        $after = $this->db
            ->where('tenant_id', $tenantId)
            ->where('source_type', 'cli_matbao_cert')
            ->where('source_id', $sourceId)
            ->count_all_results(db_prefix() . 'kt_matbao_invoice_records');

        return [
            'source_id' => $sourceId,
            'before_count' => $before,
            'after_count' => $after,
            'existing_record_id' => (int) ($existing['id'] ?? 0),
            'no_duplicate_record' => $before === 1 && $after === 1,
        ];
    }

    private function buildMatBaoInvoicePayload(array $settings, $maTraCuu)
    {
        return [
            'KHMSHDon' => (string) ($settings['default_khmshdon'] ?? '1'),
            'KHHDon' => (string) ($settings['default_khhdon'] ?? 'C26TKT'),
            'MaTraCuu' => (string) $maTraCuu,
            'MTChieu' => 'S5-CERT-' . $maTraCuu,
            'NLap' => date('Y-m-d'),
            'DVTTe' => '704',
            'TGia' => 1,
            'HTTToan' => 'Chuyển khoản',
            'GChu' => 'S5 MatBao runtime certification',
            'TCHDon' => 0,
            'LoaiHDon' => 1,
            'NMua_Ten' => 'Khách hàng kiểm thử S5',
            'NMua_MST' => '',
            'NMua_DChi' => 'Địa chỉ kiểm thử',
            'NMua_SDThoai' => '0900000000',
            'NMua_DCTDTu' => 'test@example.test',
            'DSHHDVu' => [[
                'TChat' => 1,
                'STT' => 1,
                'MHHDVu' => 'S5CERT',
                'THHDVu' => 'Dịch vụ kiểm thử MatBao S5',
                'DVTinh' => 'Lần',
                'SLuong' => 1,
                'DGia' => 1000,
                'ThTien' => 1000,
                'TSuat' => 0,
                'TThue' => 0,
                'TgTien' => 1000,
            ]],
            'TgThTien' => 1000,
            'TgTThue' => 0,
            'TTCKTMai' => 0,
            'TGTKhac' => 0,
            'TgTTTBSo' => 1000,
            'TgTTTBChu' => 'Một nghìn đồng',
        ];
    }

    private function extractMatBaoProviderData(array $response)
    {
        $data = $response['Data'] ?? ($response['data'] ?? $response);
        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            $data = $data[0];
        }
        if (!is_array($data)) {
            $data = [];
        }

        return [
            'ma_so_hdon' => (string) ($data['MaSoHDon'] ?? $data['maSoHDon'] ?? $data['MaSo'] ?? ''),
            'inv_id' => (string) ($data['InvID'] ?? $data['invId'] ?? $data['id'] ?? ''),
            'pdf_url' => (string) ($data['PdfUrl'] ?? $data['pdfUrl'] ?? $data['PDFUrl'] ?? ''),
            'xml_url' => (string) ($data['XmlUrl'] ?? $data['xmlUrl'] ?? $data['XMLUrl'] ?? ''),
        ];
    }

    private function sendMatBaoCertEvent($eventKey, array $tenant, array $context = [])
    {
        $tenantId = (int) $tenant['id'];
        $dedupeKey = 's5_matbao_cert|' . $eventKey . '|' . $tenantId . '|' . date('YmdHis') . '|' . random_int(100, 999);
        $base = [
            'tenant_id' => $tenantId,
            'tenant' => $tenant,
            'recipient_email' => (string) ($tenant['owner_email'] ?? $tenant['admin_email'] ?? $tenant['email'] ?? kt_saas_landlord_ops_email()),
            'owner_email' => (string) ($tenant['owner_email'] ?? $tenant['email'] ?? ''),
            'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['company_name'] ?? $tenant['tenant_code'] ?? ''),
            'tenant_name' => (string) ($tenant['company_name'] ?? $tenant['tenant_code'] ?? ''),
            'workspace_url' => (string) ($tenant['workspace_url'] ?? ''),
            'plan_name' => (string) ($tenant['plan_name'] ?? ''),
            'einvoice_quota' => '10',
            'einvoice_remaining' => '1',
            'module_name' => 'kt_matbao_invoice',
            'provider_name' => 'MatBao',
            'webhook_url' => '',
            'job_id' => 0,
            'error_message' => '',
            'hsm_status' => '',
            'expiry_date' => '',
            'dedupe_key' => $dedupeKey,
        ];

        return $this->Kt_saas_model->send_email_event($eventKey, array_merge($base, $context), [
            'event_key' => $eventKey,
            'dedupe_key' => $dedupeKey,
        ]);
    }

    private function summarizeMatBaoProviderLog(array $row)
    {
        return [
            'id' => (int) $row['id'],
            'action' => $row['action'],
            'http_code' => $row['http_code'] !== null ? (int) $row['http_code'] : null,
            'success' => (int) $row['success'] === 1,
            'error_code' => $row['error_code'],
            'error_message' => $row['error_message'],
            'created_at' => $row['created_at'],
        ];
    }

    private function summarizeEmailLog(array $row)
    {
        return [
            'id' => (int) $row['id'],
            'provider' => $row['provider'],
            'recipient' => $row['recipient'],
            'subject' => $row['subject'],
            'status' => $row['status'],
            'message_id' => $row['message_id'],
            'related_type' => $row['related_type'],
            'related_id' => $row['related_id'],
            'sent_at' => $row['sent_at'],
        ];
    }

    protected function outputJson(array $payload)
    {
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    }

    public function overage_test($tenantId = 3)
    {
        $tenantId = (int) $tenantId;
        $tenant = $this->Kt_saas_model->get_tenant($tenantId);
        if (!$tenant) {
            $this->outputJson(['success' => false, 'message' => 'Tenant not found.', 'tenant_id' => $tenantId]);
            return;
        }

        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        $metrics = [
            'staff'      => 8,
            'clients'    => 120,
            'projects'   => 12,
            'invoices'   => 150,
            'warehouses' => 3,
            'storage_mb' => 2048,
        ];

        foreach ($metrics as $metricKey => $metricValue) {
            $existing = $this->db
                ->where('tenant_id', $tenantId)
                ->where('metric_key', $metricKey)
                ->where('module_name', 'core')
                ->where('updated_at >=', $today . ' 00:00:00')
                ->where('updated_at <=', $today . ' 23:59:59')
                ->get(db_prefix() . 'kt_saas_usage')
                ->row_array();

            $payload = [
                'tenant_id'     => $tenantId,
                'module_name'   => 'core',
                'metric_key'    => $metricKey,
                'used_value'    => $metricValue,
                'limit_value'   => 0.00,
                'period_start'  => $today . ' 00:00:00',
                'period_end'    => $today . ' 23:59:59',
                'updated_at'    => $now,
            ];

            if ($existing) {
                $this->db->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_saas_usage', $payload);
                continue;
            }

            $this->db->insert(db_prefix() . 'kt_saas_usage', $payload);
        }

        $service = new OverageBillingService();
        $result = $service->createForTenant($tenant);
        $latestInvoice = $this->db
            ->where('tenant_id', $tenantId)
            ->order_by('id', 'desc')
            ->get(db_prefix() . 'kt_saas_invoices')
            ->row_array();

        $this->outputJson([
            'success'       => !empty($result['success']),
            'tenant_id'     => $tenantId,
            'tenant_code'   => $tenant['tenant_code'],
            'seeded_metrics'=> $metrics,
            'result'        => $result,
            'latest_invoice'=> $latestInvoice,
        ]);
    }

    public function reprovision($tenantId)
    {
        $tenantId = (int) $tenantId;
        $tenant = $this->Kt_saas_model->get_tenant($tenantId);
        if (!$tenant) {
            $this->outputJson(['success' => false, 'message' => 'Tenant not found.', 'tenant_id' => $tenantId]);
            return;
        }

        $job = $this->db
            ->where('tenant_id', $tenantId)
            ->where('job_type', 'provision_tenant')
            ->order_by('id', 'desc')
            ->get(db_prefix() . 'kt_saas_provision_jobs')
            ->row_array();

        if (!$job) {
            $jobId = $this->Kt_saas_model->create_provision_job($tenantId, 'provision_tenant', [
                'tenant_id'   => $tenantId,
                'tenant_code' => $tenant['tenant_code'],
                'source'      => 'cli_reprovision',
            ]);
            $job = $this->db->where('id', (int) $jobId)->get(db_prefix() . 'kt_saas_provision_jobs')->row_array();
        } elseif (in_array((string) ($job['status'] ?? ''), ['failed', 'done'], true)) {
            $this->Kt_saas_model->retry_provision_job((int) $job['id']);
            $job = $this->db->where('id', (int) $job['id'])->get(db_prefix() . 'kt_saas_provision_jobs')->row_array();
        }

        if (!$job) {
            $this->outputJson(['success' => false, 'message' => 'Provision job is unavailable.', 'tenant_id' => $tenantId]);
            return;
        }

        $running = $this->Kt_saas_model->mark_provision_job_running((int) $job['id']);
        if (empty($running)) {
            $this->outputJson([
                'success' => false,
                'message' => 'Provision job could not transition to running state.',
                'tenant_id' => $tenantId,
                'job_id' => (int) $job['id'],
            ]);
            return;
        }

        $runner = new ProvisioningJobRunner();
        $result = $runner->execute($running);

        if (!empty($result['success'])) {
            $this->Kt_saas_model->mark_provision_job_done((int) $job['id'], $result);
        } else {
            $this->Kt_saas_model->mark_provision_job_failed((int) $job['id'], $result['message'] ?? 'Provisioning failed.', $result);
        }

        $tenant = $this->Kt_saas_model->get_tenant($tenantId);
        $job = $this->db->where('id', (int) $job['id'])->get(db_prefix() . 'kt_saas_provision_jobs')->row_array();
        $manifestPath = module_dir_path(KT_SAAS_MODULE, 'tenant_bootstrap/manifests/' . strtolower((string) ($tenant['tenant_code'] ?? '')) . '.json');

        $this->outputJson([
            'success'         => !empty($result['success']),
            'tenant_id'       => $tenantId,
            'tenant_code'     => $tenant['tenant_code'] ?? null,
            'tenant_status'   => $tenant['status'] ?? null,
            'provisioning_status' => $tenant['provisioning_status'] ?? null,
            'db_name'         => $tenant['db_name'] ?? null,
            'job_id'          => (int) ($job['id'] ?? 0),
            'job_status'      => $job['status'] ?? null,
            'manifest_exists' => file_exists($manifestPath),
            'result'          => $result,
        ]);
    }

    public function dms_activation($orderId)
    {
        $order = $this->Kt_saas_model->get_dms_commercial_order((int) $orderId);
        if (!$order || (string) ($order['subscription_status'] ?? '') !== 'active') {
            $this->outputJson(['success' => false, 'message' => 'DMS order is not ready for provisioning.']);
            return;
        }

        $jobId = (int) ($order['provision_job_id'] ?? 0);
        if ($jobId <= 0) {
            $retry = $this->Kt_saas_model->retry_dms_commercial_order_provisioning((int) $orderId);
            $jobId = (int) ($retry['job_id'] ?? 0);
        }
        $job = $jobId > 0 ? $this->Kt_saas_model->mark_provision_job_running($jobId) : null;
        if (!$job) {
            $latest = $this->Kt_saas_model->get_dms_commercial_order((int) $orderId);
            $this->outputJson([
                'success' => (string) ($latest['workspace_status'] ?? '') === 'ready',
                'order_id' => (int) $orderId,
                'job_id' => $jobId,
                'job_status' => (string) ($latest['provision_job']['status'] ?? ''),
                'workspace_status' => (string) ($latest['workspace_status'] ?? ''),
                'reused' => true,
            ]);
            return;
        }

        $runner = new ProvisioningJobRunner();
        $result = $runner->execute($job);
        if (!empty($result['success'])) {
            $this->Kt_saas_model->mark_provision_job_done($jobId, $result);
        } else {
            $this->Kt_saas_model->mark_provision_job_failed($jobId, (string) ($result['message'] ?? 'Provisioning failed.'), $result);
        }
        $latest = $this->Kt_saas_model->get_dms_commercial_order((int) $orderId);
        $this->outputJson([
            'success' => !empty($result['success']) && (string) ($latest['workspace_status'] ?? '') === 'ready',
            'order_id' => (int) $orderId,
            'job_id' => $jobId,
            'job_status' => (string) ($latest['provision_job']['status'] ?? ''),
            'workspace_status' => (string) ($latest['workspace_status'] ?? ''),
            'password_stored' => false,
        ]);
    }

    public function backup_cert($tenantId)
    {
        $tenantId = (int) $tenantId;
        $tenant = $this->Kt_saas_model->get_tenant($tenantId);
        if (!$tenant) {
            $this->outputJson(['success' => false, 'message' => 'Tenant not found.', 'tenant_id' => $tenantId]);
            return;
        }

        if (!$this->isDisposableTenantForCliCert($tenant)) {
            $this->outputJson([
                'success' => false,
                'message' => 'Refusing backup restore certification on a non-disposable tenant.',
                'tenant_id' => $tenantId,
                'tenant_code' => $tenant['tenant_code'] ?? null,
                'status' => $tenant['status'] ?? null,
            ]);
            return;
        }

        $tenantDb = $this->connectTenantDatabaseForCli($tenant);
        if (!$tenantDb) {
            $this->outputJson(['success' => false, 'message' => 'Tenant DB connection failed.', 'tenant_id' => $tenantId]);
            return;
        }

        $baseline = $this->readBackupCertState($tenantDb);
        $service = new TenantBackupService();
        $create = $service->createBackup($tenantId);
        $backupId = (int) ($create['backup_id'] ?? 0);
        $backup = $backupId > 0 ? $this->Kt_saas_model->get_backup($backupId) : null;

        $filePath = (string) ($backup['file_path'] ?? '');
        $fileExists = $filePath !== '' && is_file($filePath);
        $actualChecksum = $fileExists ? hash_file('sha256', $filePath) : null;
        $actualSize = $fileExists ? (int) filesize($filePath) : 0;

        if ($fileExists && $actualChecksum === (string) ($backup['checksum'] ?? '') && $actualSize === (int) ($backup['file_size_bytes'] ?? 0)) {
            $this->Kt_saas_model->log_activity('backup.downloaded', 'info', [
                'backup_id' => $backupId,
                'tenant_id' => $tenantId,
                'file_path' => $filePath,
                'file_size_bytes' => $actualSize,
                'checksum' => $actualChecksum,
                'source' => 'cli_cert',
            ], $tenantId);
        }

        if (empty($create['success']) || !$backup || !$fileExists) {
            if (method_exists($tenantDb, 'close')) {
                $tenantDb->close();
            }

            $this->outputJson([
                'success' => false,
                'tenant_id' => $tenantId,
                'tenant_code' => $tenant['tenant_code'] ?? null,
                'backup_id' => $backupId,
                'create' => $create,
                'backup_file' => [
                    'path' => $filePath,
                    'exists' => $fileExists,
                    'db_size' => (int) ($backup['file_size_bytes'] ?? 0),
                    'actual_size' => $actualSize,
                    'db_checksum' => (string) ($backup['checksum'] ?? ''),
                    'actual_checksum' => $actualChecksum,
                ],
                'baseline' => $baseline,
            ]);
            return;
        }

        $mutatedCompany = 'RESTORE MUTATION ' . date('YmdHis');
        $tenantDb->where('name', 'companyname')->update(db_prefix() . 'options', ['value' => $mutatedCompany]);
        $tenantDb->where('staffid', 1)->update(db_prefix() . 'staff', ['firstname' => 'Mutated']);
        $mutated = $this->readBackupCertState($tenantDb);

        $restore = $backupId > 0 ? $service->restoreBackup($backupId) : ['success' => false, 'message' => 'Backup was not created.'];
        $tenantDb->close();

        $tenantDbAfter = $this->connectTenantDatabaseForCli($tenant);
        $restored = $tenantDbAfter ? $this->readBackupCertState($tenantDbAfter) : [];
        if ($tenantDbAfter && method_exists($tenantDbAfter, 'close')) {
            $tenantDbAfter->close();
        }

        $missingFileResult = null;
        $checksumResult = null;
        if ($backup) {
            $originalPath = (string) ($backup['file_path'] ?? '');
            $originalChecksum = (string) ($backup['checksum'] ?? '');

            $this->Kt_saas_model->update_backup_record($backupId, ['file_path' => $originalPath . '.missing']);
            $missingFileResult = $service->restoreBackup($backupId);

            $this->Kt_saas_model->update_backup_record($backupId, [
                'file_path' => $originalPath,
                'checksum' => str_repeat('0', 64),
            ]);
            $checksumResult = $service->restoreBackup($backupId);

            $this->Kt_saas_model->update_backup_record($backupId, [
                'file_path' => $originalPath,
                'checksum' => $originalChecksum,
                'status' => 'done',
            ]);
        }

        $secondRestore = $backupId > 0 ? $service->restoreBackup($backupId) : ['success' => false, 'message' => 'Backup was not created.'];
        $tenantDbFinal = $this->connectTenantDatabaseForCli($tenant);
        $final = $tenantDbFinal ? $this->readBackupCertState($tenantDbFinal) : [];
        if ($tenantDbFinal && method_exists($tenantDbFinal, 'close')) {
            $tenantDbFinal->close();
        }

        $this->outputJson([
            'success' => !empty($create['success'])
                && !empty($restore['success'])
                && !empty($secondRestore['success'])
                && $fileExists
                && $actualChecksum === (string) ($backup['checksum'] ?? '')
                && $actualSize === (int) ($backup['file_size_bytes'] ?? 0)
                && ($restored['companyname'] ?? null) === ($baseline['companyname'] ?? null)
                && ($final['companyname'] ?? null) === ($baseline['companyname'] ?? null)
                && empty($missingFileResult['success'])
                && empty($checksumResult['success']),
            'tenant_id' => $tenantId,
            'tenant_code' => $tenant['tenant_code'] ?? null,
            'backup_id' => $backupId,
            'create' => $create,
            'backup_file' => [
                'path' => $filePath,
                'exists' => $fileExists,
                'db_size' => (int) ($backup['file_size_bytes'] ?? 0),
                'actual_size' => $actualSize,
                'db_checksum' => (string) ($backup['checksum'] ?? ''),
                'actual_checksum' => $actualChecksum,
            ],
            'baseline' => $baseline,
            'mutated' => $mutated,
            'restore' => $restore,
            'restored' => $restored,
            'missing_file_result' => $missingFileResult,
            'checksum_result' => $checksumResult,
            'second_restore' => $secondRestore,
            'final' => $final,
        ]);
    }

    public function billing_smoke($tenantId = 4)
    {
        $tenantId = (int) $tenantId;
        $tenant = $this->Kt_saas_model->get_tenant($tenantId);
        if (!$tenant) {
            $this->outputJson(['success' => false, 'message' => 'Tenant not found.', 'tenant_id' => $tenantId]);
            return;
        }

        $subscription = $this->Kt_saas_model->get_tenant_subscription_profile($tenantId);
        if (!$subscription) {
            $this->outputJson(['success' => false, 'message' => 'Current subscription not found.', 'tenant_id' => $tenantId]);
            return;
        }

        $currentPlan = $this->Kt_saas_model->get_plan((int) $subscription['plan_id']);
        if (!$currentPlan) {
            $this->outputJson(['success' => false, 'message' => 'Current plan not found.', 'tenant_id' => $tenantId]);
            return;
        }

        $downgradeTarget = $this->db
            ->where('deleted_at IS NULL', null, false)
            ->where('is_public', 1)
            ->where('is_active', 1)
            ->where('price <', (float) ($currentPlan['price'] ?? 0))
            ->order_by('price', 'desc')
            ->get(db_prefix() . 'kt_saas_plans')
            ->row_array();

        if (!$downgradeTarget) {
            $this->outputJson([
                'success' => false,
                'message' => 'No cheaper public plan available for downgrade smoke test.',
                'tenant_id' => $tenantId,
                'current_plan_id' => (int) $currentPlan['id'],
            ]);
            return;
        }

        $billing = new BillingEngineService();
        $scheduleResult = $billing->createPlanChangeRequestInvoice($tenant, $subscription, $downgradeTarget, [
            'source' => 'cli_billing_smoke',
        ]);

        $subscriptionAfterSchedule = $this->Kt_saas_model->get_tenant_subscription_profile($tenantId);
        $metadataAfterSchedule = json_decode((string) ($subscriptionAfterSchedule['metadata_json'] ?? ''), true);
        $scheduled = is_array($metadataAfterSchedule) ? ($metadataAfterSchedule['scheduled_plan_change'] ?? null) : null;
        if (is_array($metadataAfterSchedule) && !empty($metadataAfterSchedule['scheduled_plan_change']) && is_array($metadataAfterSchedule['scheduled_plan_change'])) {
            $forcedDueAt = date('Y-m-d H:i:s', strtotime('-2 minutes'));
            $metadataAfterSchedule['scheduled_plan_change']['scheduled_at'] = $forcedDueAt;
            $this->Kt_saas_model->update_subscription((int) $subscription['id'], [
                'metadata_json'   => json_encode($metadataAfterSchedule, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'next_billing_at' => $forcedDueAt,
                'status'          => 'active',
                'grace_ends_at'   => null,
            ]);
            $scheduled = $metadataAfterSchedule['scheduled_plan_change'];
        }

        $runner = new RecurringBillingRunner();
        $runnerSummary = $runner->run(25);

        $subscriptionAfterDowngrade = $this->Kt_saas_model->get_tenant_subscription_profile($tenantId);
        $downgradeApplied = (int) ($subscriptionAfterDowngrade['plan_id'] ?? 0) === (int) $downgradeTarget['id'];

        $upgradeInvoice = null;
        $upgradeResult = null;
        $webhookReplay = null;

        if ($downgradeApplied && (int) $currentPlan['id'] !== (int) $downgradeTarget['id']) {
            $restoreRequest = $billing->createPlanChangeRequestInvoice($tenant, $subscriptionAfterDowngrade, $currentPlan, [
                'source' => 'cli_billing_smoke_restore',
            ]);

            if (!empty($restoreRequest['invoice_id'])) {
                $upgradeInvoice = $this->Kt_saas_model->get_invoice((int) $restoreRequest['invoice_id']);
                if ($upgradeInvoice) {
                    $paymentReference = 'CLI-SMOKE-UPGRADE-' . (int) $upgradeInvoice['id'];
                    $upgradeResult = $billing->markInvoicePaid($upgradeInvoice, [
                        'gateway'           => 'cli_smoke',
                        'payment_reference' => $paymentReference,
                        'amount'            => (float) ($upgradeInvoice['grand_total'] ?? 0),
                        'currency'          => (string) ($upgradeInvoice['currency'] ?? 'USD'),
                        'status'            => 'paid',
                        'paid_at'           => date('Y-m-d H:i:s'),
                    ]);

                    $payments = new PaymentCollectionService();
                    $webhookReplay = $payments->processWebhook('cli_smoke', [
                        'invoice_id'         => (int) $upgradeInvoice['id'],
                        'status'             => 'paid',
                        'payment_reference'  => $paymentReference,
                        'amount'             => (float) ($upgradeInvoice['grand_total'] ?? 0),
                        'currency'           => (string) ($upgradeInvoice['currency'] ?? 'USD'),
                    ]);
                }
            }
        }

        $finalSubscription = $this->Kt_saas_model->get_tenant_subscription_profile($tenantId);

        $this->outputJson([
            'success' => $downgradeApplied,
            'tenant_id' => $tenantId,
            'tenant_code' => $tenant['tenant_code'] ?? null,
            'current_plan_before' => [
                'id' => (int) $currentPlan['id'],
                'code' => $currentPlan['plan_code'] ?? null,
                'price' => (float) ($currentPlan['price'] ?? 0),
            ],
            'downgrade_target' => [
                'id' => (int) $downgradeTarget['id'],
                'code' => $downgradeTarget['plan_code'] ?? null,
                'price' => (float) ($downgradeTarget['price'] ?? 0),
            ],
            'schedule_result' => $scheduleResult,
            'scheduled_metadata' => $scheduled,
            'runner_summary' => $runnerSummary,
            'downgrade_applied' => $downgradeApplied,
            'subscription_after_downgrade' => [
                'plan_id' => (int) ($subscriptionAfterDowngrade['plan_id'] ?? 0),
                'plan_name' => $subscriptionAfterDowngrade['plan_name'] ?? null,
                'next_billing_at' => $subscriptionAfterDowngrade['next_billing_at'] ?? null,
            ],
            'restore_invoice_id' => $upgradeInvoice['id'] ?? null,
            'restore_upgrade_result' => $upgradeResult,
            'webhook_replay' => $webhookReplay,
            'final_subscription' => [
                'plan_id' => (int) ($finalSubscription['plan_id'] ?? 0),
                'plan_name' => $finalSubscription['plan_name'] ?? null,
                'next_billing_at' => $finalSubscription['next_billing_at'] ?? null,
                'metadata_json' => $finalSubscription['metadata_json'] ?? null,
            ],
        ]);
    }

    public function automation_smoke($tenantId = 3)
    {
        $tenantId = (int) $tenantId;
        $service = new TenantLimitService();
        $beforeRows = $service->getUsage($tenantId, 'core');
        $before = 0.0;
        foreach ($beforeRows as $row) {
            if ((string) ($row['metric_key'] ?? '') === 'automation') {
                $before = (float) ($row['used_value'] ?? 0);
                break;
            }
        }

        $service->incrementUsage($tenantId, 'core', 'automation', 2);
        $service->decrementUsage($tenantId, 'core', 'automation', 1);

        $afterRows = $service->getUsage($tenantId, 'core');
        $after = 0.0;
        foreach ($afterRows as $row) {
            if ((string) ($row['metric_key'] ?? '') === 'automation') {
                $after = (float) ($row['used_value'] ?? 0);
                break;
            }
        }

        $this->outputJson([
            'success' => true,
            'tenant_id' => $tenantId,
            'before' => $before,
            'after' => $after,
            'delta' => $after - $before,
        ]);
    }

    public function repair_runtime()
    {
        $deletedOverrides = 0;
        $staleOverrides = $this->db
            ->where('overridden', 1)
            ->where('source_plan_id IS NULL', null, false)
            ->get(db_prefix() . 'kt_saas_tenant_entitlements')
            ->result_array();

        if (!empty($staleOverrides)) {
            $deletedOverrides = count($staleOverrides);
            $this->db
                ->where('overridden', 1)
                ->where('source_plan_id IS NULL', null, false)
                ->delete(db_prefix() . 'kt_saas_tenant_entitlements');
        }

        $this->db
            ->where('module_name', 'goals')
            ->update(db_prefix() . 'kt_saas_module_catalog', [
                'is_global_active' => 1,
                'synced_at'        => date('Y-m-d H:i:s'),
            ]);

        $this->Kt_saas_model->rebuild_module_registries();

        $catalog = $this->db
            ->select('module_name, is_global_active')
            ->from(db_prefix() . 'kt_saas_module_catalog')
            ->where_in('module_name', ['goals', 'einvoice', 'kt_inventory', 'kt_sepay'])
            ->order_by('module_name', 'asc')
            ->get()
            ->result_array();

        $tenants = $this->db
            ->select('id, tenant_code, plan_id')
            ->from(db_prefix() . 'kt_saas_tenants')
            ->where('deleted_at IS NULL', null, false)
            ->order_by('id', 'asc')
            ->get()
            ->result_array();

        $modulesByTenant = [];
        foreach ($tenants as $tenant) {
            $modulesByTenant[$tenant['tenant_code']] = $this->db
                ->select('module_name, status, source')
                ->from(db_prefix() . 'kt_saas_modules')
                ->where('tenant_id', (int) $tenant['id'])
                ->where_in('module_name', ['goals', 'einvoice', 'kt_inventory', 'kt_sepay'])
                ->order_by('module_name', 'asc')
                ->get()
                ->result_array();
        }

        $this->outputJson([
            'success'           => true,
            'deleted_overrides' => $deletedOverrides,
            'catalog'           => $catalog,
            'tenant_modules'    => $modulesByTenant,
        ]);
    }

    private function isDisposableTenantForCliCert(array $tenant)
    {
        $code = strtolower((string) ($tenant['tenant_code'] ?? ''));
        $subdomain = strtolower((string) ($tenant['subdomain'] ?? ''));
        $company = strtolower((string) ($tenant['company_name'] ?? ''));
        $status = strtolower((string) ($tenant['status'] ?? ''));

        $hasDisposableMarker = strpos($code, 's2prov') !== false
            || strpos($code, 'backup') !== false
            || strpos($subdomain, 's2prov') !== false
            || strpos($subdomain, 'backup') !== false
            || strpos($company, 'tenant test') !== false;

        return $hasDisposableMarker && in_array($status, ['trial', 'draft', 'active'], true);
    }

    private function cliOption($name, $default = null)
    {
        if (isset($GLOBALS['KT_CLI_NAMED_OPTIONS'])
            && array_key_exists((string) $name, (array) $GLOBALS['KT_CLI_NAMED_OPTIONS'])) {
            return $GLOBALS['KT_CLI_NAMED_OPTIONS'][(string) $name];
        }

        $prefix = '--' . trim((string) $name) . '=';
        foreach ((array) ($_SERVER['argv'] ?? []) as $argument) {
            if (strpos((string) $argument, $prefix) === 0) {
                return substr((string) $argument, strlen($prefix));
            }
        }

        return $default;
    }

    private function useTenantQaAuthDatabaseIfRequested()
    {
        $tenantId = (int) $this->cliOption('tenant', 0);
        if ($tenantId <= 0) {
            return;
        }

        $tenant = $this->db
            ->where('id', $tenantId)
            ->get(db_prefix() . 'kt_saas_tenants')
            ->row_array();
        if (!$tenant) {
            throw new RuntimeException('Tenant not found.');
        }

        $tenantDb = $this->connectTenantDatabaseForCli($tenant);
        if (!$tenantDb) {
            throw new RuntimeException('Tenant database is unavailable.');
        }

        $this->Qa_auth_model->use_database($tenantDb);
    }

    private function connectTenantDatabaseForCli(array $tenant)
    {
        $dbName = trim((string) ($tenant['db_name'] ?? ''));
        $dbHost = trim((string) ($tenant['db_host'] ?? ''));
        $dbPort = trim((string) ($tenant['db_port'] ?? '3306'));
        $dbUser = trim((string) ($tenant['db_user'] ?? ''));
        $encryptedPassword = $tenant['db_password_encrypted'] ?? null;

        if ($dbName === '' || $dbHost === '' || $dbUser === '') {
            return null;
        }

        $password = $encryptedPassword ? $this->encryption->decrypt($encryptedPassword) : '';
        if ($password === false) {
            return null;
        }

        $config = [
            'dsn'          => '',
            'hostname'     => $dbHost,
            'username'     => $dbUser,
            'password'     => $password ?: '',
            'database'     => $dbName,
            'dbdriver'     => defined('APP_DB_DRIVER') ? APP_DB_DRIVER : 'mysqli',
            'dbprefix'     => db_prefix(),
            'pconnect'     => false,
            'db_debug'     => false,
            'cache_on'     => false,
            'cachedir'      => '',
            'char_set'     => defined('APP_DB_CHARSET') ? APP_DB_CHARSET : 'utf8mb4',
            'dbcollat'     => defined('APP_DB_COLLATION') ? APP_DB_COLLATION : 'utf8mb4_unicode_ci',
            'swap_pre'     => '',
            'encrypt'      => false,
            'compress'     => false,
            'stricton'     => false,
            'failover'     => [],
            'save_queries' => false,
            'port'         => $dbPort,
        ];

        $db = $this->load->database($config, true);
        if (!$db || !$db->initialize()) {
            return null;
        }

        return $db;
    }

    private function readBackupCertState($tenantDb)
    {
        $options = [];
        if ($tenantDb->table_exists(db_prefix() . 'options')) {
            $rows = $tenantDb
                ->where_in('name', ['companyname', 'company_logo', 'favicon', 'default_language', 'default_timezone', 'dateformat', 'time_format'])
                ->get(db_prefix() . 'options')
                ->result_array();

            foreach ($rows as $row) {
                $options[(string) $row['name']] = (string) $row['value'];
            }
        }

        $firstStaff = null;
        $staffCount = 0;
        if ($tenantDb->table_exists(db_prefix() . 'staff')) {
            $staffCount = (int) $tenantDb->count_all_results(db_prefix() . 'staff');
            $firstStaff = $tenantDb
                ->select('staffid, email, firstname, lastname, active, admin')
                ->order_by('staffid', 'asc')
                ->get(db_prefix() . 'staff')
                ->row_array();
        }

        $invoiceCount = $tenantDb->table_exists(db_prefix() . 'invoices')
            ? (int) $tenantDb->count_all_results(db_prefix() . 'invoices')
            : null;

        return [
            'companyname' => $options['companyname'] ?? null,
            'options' => $options,
            'staff_count' => $staffCount,
            'first_staff' => $firstStaff,
            'invoice_count' => $invoiceCount,
        ];
    }

}
