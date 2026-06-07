<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * KT eInvoice — Landlord Admin Controller
 *
 * Quản lý:
 * 1. Plan feature configuration cho eInvoice (quota, batch size, enabled flag...)
 * 2. Xem tất cả records/logs của tất cả tenant
 * 3. Force reset, override quota, troubleshoot
 */
class Kt_einvoice_admin extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        // Chỉ Landlord (super admin) mới được vào
        if (!kt_saas_is_landlord_context()) {
            show_error('Access denied.', 403);
        }

        $this->load->model('kt_einvoice/Kt_einvoice_model');
        $this->load->language('kt_einvoice/vietnamese/kt_einvoice');
        $this->_requireServices();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PLAN FEATURE CONFIGURATION
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Danh sách các plan và config eInvoice
     */
    public function plan_features()
    {
        // Lấy tất cả plans từ kt_saas
        $plans = $this->_getAllPlans();

        $data = [
            'title'         => 'Cấu Hình eInvoice Theo Gói',
            'plans'         => $plans,
            'feature_keys'  => $this->_getFeatureKeysMeta(),
        ];

        $this->load->view('kt_einvoice/admin/plan_features', $data);
    }

    /**
     * AJAX POST: Lưu features cho 1 plan
     */
    public function save_plan_features(int $planId)
    {
        if (!$this->input->is_ajax_request()) show_404();

        $features = $this->input->post('features', true) ?? [];
        $result   = $this->_savePlanFeatures($planId, $features);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // GLOBAL OVERVIEW
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Tổng quan tất cả tenant đang dùng eInvoice
     */
    public function overview()
    {
        $stats = $this->_getGlobalStats();
        $data  = [
            'title'       => 'Tổng Quan eInvoice Toàn Hệ Thống',
            'stats'       => $stats,
            'top_tenants' => $this->_getTopTenants(10),
        ];

        $this->load->view('kt_einvoice/admin/overview', $data);
    }

    /**
     * Danh sách tất cả HĐĐTcủa mọi tenant (với filter)
     */
    public function all_records()
    {
        $filters = [
            'tenant_id'   => (int) $this->input->get('tenant_id'),
            'status'      => $this->input->get('status'),
            'environment' => $this->input->get('environment') ?: 'production',
            'date_from'   => $this->input->get('date_from'),
            'date_to'     => $this->input->get('date_to'),
        ];

        $page   = max(1, (int) $this->input->get('page'));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        $list  = $this->_getAllRecords($filters, $limit, $offset);
        $total = $this->_countAllRecords($filters);

        $data = [
            'title'    => 'Tất Cả Hóa Đơn Điện Tử',
            'list'     => $list,
            'total'    => $total,
            'page'     => $page,
            'limit'    => $limit,
            'filters'  => $filters,
            'tenants'  => $this->_getTenantList(),
        ];

        $this->load->view('kt_einvoice/admin/all_records', $data);
    }

    /**
     * API Logs viewer (cho troubleshoot)
     */
    public function api_logs()
    {
        $tenantId = (int) $this->input->get('tenant_id');
        $page     = max(1, (int) $this->input->get('page'));
        $limit    = 30;

        $logs  = $this->_getApiLogs($tenantId, $limit, ($page - 1) * $limit);
        $total = $this->_countApiLogs($tenantId);

        $data = [
            'title'     => 'API Logs eInvoice',
            'logs'      => $logs,
            'total'     => $total,
            'page'      => $page,
            'limit'     => $limit,
            'tenant_id' => $tenantId,
            'tenants'   => $this->_getTenantList(),
        ];

        $this->load->view('kt_einvoice/admin/api_logs', $data);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TENANT MANAGEMENT
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Xem settings của 1 tenant cụ thể
     */
    public function tenant_settings(int $tenantId)
    {
        $service  = new KtEinvoiceService();
        $settings = $service->getSettingsForDisplay($tenantId, 'production');
        $sandbox  = $service->getSettingsForDisplay($tenantId, 'sandbox');
        $quota    = (new KtEinvoiceQuotaService())->getUsageSummary($tenantId, 'production');

        $data = [
            'title'      => 'eInvoice Settings - Tenant #' . $tenantId,
            'tenant_id'  => $tenantId,
            'settings'   => $settings,
            'sandbox'    => $sandbox,
            'quota'      => $quota,
        ];

        $this->load->view('kt_einvoice/admin/tenant_settings', $data);
    }

    /**
     * AJAX: Reset quota của tenant (override)
     */
    public function reset_tenant_quota(int $tenantId)
    {
        if (!$this->input->is_ajax_request()) show_404();

        $year  = (int) date('Y');
        $month = (int) date('n');
        $env   = $this->input->post('environment', true) ?: 'production';

        // Reset used_count về 0
        $this->db
            ->where('tenant_id', $tenantId)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->where('environment', $env)
            ->update(db_prefix() . 'kt_einvoice_quota_usage', [
                'used_count'   => 0,
                'failed_count' => 0,
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(['success' => true, 'message' => 'Đã reset quota tenant #' . $tenantId]));
    }

    /**
     * AJAX: Force deactivate tenant eInvoice
     */
    public function deactivate_tenant(int $tenantId)
    {
        if (!$this->input->is_ajax_request()) show_404();

        $this->db
            ->where('tenant_id', $tenantId)
            ->update(db_prefix() . 'kt_einvoice_provider_settings', [
                'is_active'  => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(['success' => true]));
    }

    /**
     * Cron logs viewer
     */
    public function cron_logs()
    {
        $page  = max(1, (int) $this->input->get('page'));
        $limit = 30;

        $logs  = $this->db
            ->order_by('id', 'DESC')
            ->limit($limit, ($page - 1) * $limit)
            ->get(db_prefix() . 'kt_einvoice_cron_logs')
            ->result_array();

        $total = $this->db->count_all(db_prefix() . 'kt_einvoice_cron_logs');

        $data = [
            'title' => 'Cron Logs eInvoice',
            'logs'  => $logs,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ];

        $this->load->view('kt_einvoice/admin/cron_logs', $data);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════════════════════

    private function _getFeatureKeysMeta(): array
    {
        return [
            KT_EINVOICE_FEATURE_ENABLED => [
                'label'       => 'Bật tính năng eInvoice',
                'type'        => 'boolean',
                'default'     => false,
                'description' => 'Cho phép tenant dùng module Hóa Đơn Điện Tử',
            ],
            KT_EINVOICE_FEATURE_MONTHLY_QUOTA => [
                'label'       => 'Hạn mức HĐ/tháng',
                'type'        => 'integer',
                'default'     => 50,
                'description' => '0 = không giới hạn (Enterprise)',
            ],
            KT_EINVOICE_FEATURE_BATCH_ISSUE => [
                'label'       => 'Phát hành theo lô',
                'type'        => 'boolean',
                'default'     => false,
                'description' => 'Cho phép phát hành nhiều HĐ cùng lúc',
            ],
            KT_EINVOICE_FEATURE_MAX_BATCH_SIZE => [
                'label'       => 'Số HĐ tối đa/lô',
                'type'        => 'integer',
                'default'     => 20,
                'description' => 'Tối đa bao nhiêu HĐ trong 1 batch',
            ],
            KT_EINVOICE_FEATURE_AUTO_ISSUE => [
                'label'       => 'Auto-issue khi thanh toán',
                'type'        => 'boolean',
                'default'     => false,
                'description' => 'Cho phép cấu hình auto-issue khi payment',
            ],
            KT_EINVOICE_FEATURE_DOWNLOAD_XML => [
                'label'       => 'Tải file XML',
                'type'        => 'boolean',
                'default'     => false,
                'description' => 'Cho phép tải XML hóa đơn',
            ],
            KT_EINVOICE_FEATURE_CANCEL => [
                'label'       => 'Hủy hóa đơn đã phát hành',
                'type'        => 'boolean',
                'default'     => false,
                'description' => 'Cho phép hủy HĐ đã lên CQT',
            ],
        ];
    }

    private function _getAllPlans(): array
    {
        $featuresTable = db_prefix() . 'kt_einvoice_plan_features';
        $this->db->from(db_prefix() . 'kt_saas_plans p');
        $this->db->where('p.deleted_at IS NULL', null, false);
        $this->db->where('p.is_active', 1);
        $this->db->order_by('p.price', 'ASC');

        if ($this->db->table_exists($featuresTable)) {
            $this->db->select('p.*, GROUP_CONCAT(f.feature_key, "=", f.feature_value) as features_raw');
            $this->db->join($featuresTable . ' f', 'f.plan_id = p.id', 'left');
            $this->db->group_by('p.id');
        } else {
            $this->db->select('p.*, "" as features_raw', false);
        }

        return $this->db->get()->result_array();
    }

    private function _savePlanFeatures(int $planId, array $features): array
    {
        if (!$this->db->table_exists(db_prefix() . 'kt_einvoice_plan_features')) {
            require_once APPPATH . '../modules/kt_einvoice/install.php';
            if (function_exists('kt_einvoice_run_install')) {
                kt_einvoice_run_install();
            }
        }

        foreach ($features as $key => $value) {
            // Validate key là feature key hợp lệ
            $validKeys = [
                KT_EINVOICE_FEATURE_ENABLED, KT_EINVOICE_FEATURE_MONTHLY_QUOTA,
                KT_EINVOICE_FEATURE_BATCH_ISSUE, KT_EINVOICE_FEATURE_MAX_BATCH_SIZE,
                KT_EINVOICE_FEATURE_AUTO_ISSUE, KT_EINVOICE_FEATURE_DOWNLOAD_XML,
                KT_EINVOICE_FEATURE_CANCEL,
            ];
            if (!in_array($key, $validKeys)) continue;

            // Upsert into kt_einvoice_plan_features
            $existing = $this->db
                ->where('plan_id', $planId)
                ->where('feature_key', $key)
                ->get(db_prefix() . 'kt_einvoice_plan_features')
                ->row_array();

            $now = date('Y-m-d H:i:s');
            if ($existing) {
                $this->db->where('id', $existing['id'])
                    ->update(db_prefix() . 'kt_einvoice_plan_features', [
                        'feature_value' => $value,
                        'updated_at'    => $now,
                    ]);
            } else {
                $this->db->insert(db_prefix() . 'kt_einvoice_plan_features', [
                    'plan_id'       => $planId,
                    'feature_key'   => $key,
                    'feature_value' => $value,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }

        // Invalidate cache nếu có
        if (function_exists('kt_saas_invalidate_plan_cache')) {
            kt_saas_invalidate_plan_cache($planId);
        }

        return ['success' => true, 'message' => 'Đã lưu cấu hình eInvoice cho gói #' . $planId];
    }

    private function _getGlobalStats(): array
    {
        $table = db_prefix() . 'kt_einvoice_records';
        $year  = date('Y');
        $month = date('m');

        return [
            'total_issued_this_month' => (int) $this->db
                ->where('status', 'issued')
                ->where("YEAR(issued_at) = $year")
                ->where("MONTH(issued_at) = $month")
                ->count_all_results($table),

            'total_pending' => (int) $this->db
                ->where_in('status', ['pending_create', 'pending_issue'])
                ->count_all_results($table),

            'total_failed' => (int) $this->db
                ->where_in('status', ['failed_create', 'failed_issue'])
                ->count_all_results($table),

            'active_tenants' => (int) $this->db
                ->where('is_active', 1)
                ->count_all_results(db_prefix() . 'kt_einvoice_provider_settings'),

            'total_records' => (int) $this->db->count_all($table),
        ];
    }

    private function _getTopTenants(int $limit): array
    {
        return $this->db
            ->select('tenant_id, COUNT(*) as total, SUM(status="issued") as issued')
            ->where("YEAR(created_at) = " . date('Y'))
            ->where("MONTH(created_at) = " . date('m'))
            ->group_by('tenant_id')
            ->order_by('issued', 'DESC')
            ->limit($limit)
            ->get(db_prefix() . 'kt_einvoice_records')
            ->result_array();
    }

    private function _getAllRecords(array $filters, int $limit, int $offset): array
    {
        if ($filters['tenant_id']) $this->db->where('tenant_id', $filters['tenant_id']);
        if ($filters['status'])    $this->db->where('status', $filters['status']);
        if ($filters['environment']) $this->db->where('environment', $filters['environment']);
        if ($filters['date_from'])  $this->db->where('created_at >=', $filters['date_from']);
        if ($filters['date_to'])    $this->db->where('created_at <=', $filters['date_to'] . ' 23:59:59');

        return $this->db
            ->order_by('id', 'DESC')
            ->limit($limit, $offset)
            ->get(db_prefix() . 'kt_einvoice_records')
            ->result_array();
    }

    private function _countAllRecords(array $filters): int
    {
        if ($filters['tenant_id']) $this->db->where('tenant_id', $filters['tenant_id']);
        if ($filters['status'])    $this->db->where('status', $filters['status']);
        if ($filters['environment']) $this->db->where('environment', $filters['environment']);
        return (int) $this->db->count_all_results(db_prefix() . 'kt_einvoice_records');
    }

    private function _getApiLogs(int $tenantId, int $limit, int $offset): array
    {
        if ($tenantId) $this->db->where('tenant_id', $tenantId);
        return $this->db
            ->order_by('id', 'DESC')
            ->limit($limit, $offset)
            ->get(db_prefix() . 'kt_einvoice_api_logs')
            ->result_array();
    }

    private function _countApiLogs(int $tenantId): int
    {
        if ($tenantId) $this->db->where('tenant_id', $tenantId);
        return (int) $this->db->count_all_results(db_prefix() . 'kt_einvoice_api_logs');
    }

    private function _getTenantList(): array
    {
        return $this->db
            ->select('DISTINCT tenant_id')
            ->get(db_prefix() . 'kt_einvoice_provider_settings')
            ->result_array();
    }

    private function _requireServices(): void
    {
        $base = APPPATH . '../modules/kt_einvoice/';
        require_once $base . 'config/kt_einvoice_config.php';
        require_once $base . 'services/KtEinvoiceEncryptionService.php';
        require_once $base . 'services/SepayEinvoiceApiClient.php';
        require_once $base . 'services/KtEinvoiceMapperService.php';
        require_once $base . 'services/KtEinvoiceQuotaService.php';
        require_once $base . 'services/KtEinvoiceService.php';
    }
}

// ── Helper ────────────────────────────────────────────────────────────────────
if (!function_exists('kt_saas_is_landlord')) {
    function kt_saas_is_landlord(): bool
    {
        if (function_exists('kt_saas_is_landlord_runtime')) {
            return kt_saas_is_landlord_runtime();
        }
        // Fallback: super admin
        return is_admin() && !function_exists('kt_saas_is_tenant_runtime');
    }
}

