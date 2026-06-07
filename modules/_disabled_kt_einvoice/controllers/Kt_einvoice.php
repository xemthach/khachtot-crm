<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * KT eInvoice — Tenant Admin Controller
 */
class Kt_einvoice extends AdminController
{
    /** @var KtEinvoiceService */
    private $service;

    /** @var int */
    private $tenantId;

    /** @var string */
    private $environment;

    public function __construct()
    {
        parent::__construct();

        // Chỉ cho tenant context
        if (!function_exists('kt_saas_is_tenant_runtime') || !kt_saas_is_tenant_runtime()) {
            show_404();
        }

        $this->tenantId    = kt_saas_current_tenant_id();
        $this->environment = $this->_getEnvironment();

        // Load dependencies
        $this->load->model('kt_einvoice/Kt_einvoice_model');
        $this->load->language('kt_einvoice/vietnamese/kt_einvoice');

        $this->_requireServices();
        $this->service = new KtEinvoiceService();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // DASHBOARD
    // ═══════════════════════════════════════════════════════════════════════════

    public function dashboard()
    {
        $this->_checkPermission('view');

        $stats        = $this->Kt_einvoice_model->getDashboardStats($this->tenantId, $this->environment);
        $quotaSummary = (new KtEinvoiceQuotaService())->getUsageSummary($this->tenantId, $this->environment);
        $recentList   = $this->Kt_einvoice_model->getRecordsList($this->tenantId, ['environment' => $this->environment], 10, 0);

        $data = [
            'title'        => _l('kt_einvoice_dashboard_title'),
            'stats'        => $stats,
            'quota'        => $quotaSummary,
            'recent_list'  => $recentList,
            'environment'  => $this->environment,
        ];

        $this->load->view('kt_einvoice/tenant/dashboard', $data);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // INVOICE LIST
    // ═══════════════════════════════════════════════════════════════════════════

    public function invoices()
    {
        $this->_checkPermission('view');

        $filters = [
            'status'      => $this->input->get('status'),
            'environment' => $this->environment,
            'search'      => $this->input->get('search'),
        ];

        $page   = max(1, (int) $this->input->get('page'));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        $list  = $this->Kt_einvoice_model->getRecordsList($this->tenantId, $filters, $limit, $offset);
        $total = $this->Kt_einvoice_model->countRecords($this->tenantId, $filters);

        $data = [
            'title'       => _l('kt_einvoice_list_title'),
            'list'        => $list,
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'filters'     => $filters,
            'environment' => $this->environment,
        ];

        $this->load->view('kt_einvoice/tenant/invoice_list', $data);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // INVOICE DETAIL
    // ═══════════════════════════════════════════════════════════════════════════

    public function invoice_detail(int $recordId)
    {
        $this->_checkPermission('view');

        $record = $this->Kt_einvoice_model->getRecord($recordId, $this->tenantId);
        if (!$record) {
            show_404();
        }

        $data = [
            'title'  => _l('kt_einvoice_detail_title'),
            'record' => $record,
        ];

        $this->load->view('kt_einvoice/tenant/invoice_detail', $data);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SETTINGS
    // ═══════════════════════════════════════════════════════════════════════════

    public function settings()
    {
        $this->_checkPermission('configure');

        if ($this->input->post()) {
            $input  = $this->input->post(null, true);
            $result = $this->service->saveSettings($this->tenantId, $this->environment, $input);

            if ($result['success']) {
                set_alert('success', _l('kt_einvoice_settings_saved'));
            } else {
                set_alert('danger', _l('kt_einvoice_settings_save_error'));
            }
            redirect(admin_url('kt_einvoice/settings'));
        }

        $settings   = $this->service->getSettingsForDisplay($this->tenantId, $this->environment);
        $quotaCheck = (new KtEinvoiceQuotaService())->getUsageSummary($this->tenantId, $this->environment);

        $data = [
            'title'       => _l('kt_einvoice_settings_title'),
            'settings'    => $settings,
            'quota'       => $quotaCheck,
            'environment' => $this->environment,
        ];

        $this->load->view('kt_einvoice/tenant/settings', $data);
    }

    /**
     * AJAX: Test kết nối SePay
     */
    public function test_connection()
    {
        $this->_checkPermission('configure');
        $this->_requireAjax();

        $result = $this->service->testConnection($this->tenantId, $this->environment);
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    /**
     * AJAX: Lấy danh sách nhà cung cấp từ SePay
     */
    public function get_providers()
    {
        $this->_checkPermission('configure');
        $this->_requireAjax();

        try {
            $client    = $this->service->buildApiClient($this->tenantId, $this->environment);
            $providers = $client->getProviderAccounts();
            $this->_json(['success' => true, 'data' => $providers['data'] ?? []]);
        } catch (Exception $e) {
            $this->_json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CREATE DRAFT
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * POST AJAX: Tạo hóa đơn nháp từ Perfex invoice
     */
    public function create_draft(int $perfexInvoiceId)
    {
        $this->_checkPermission('create');
        $this->_requireAjax();

        $result = $this->service->createDraft(
            $this->tenantId,
            $perfexInvoiceId,
            $this->environment,
            get_staff_user_id()
        );

        $this->_json($result);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ISSUE
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * POST AJAX: Phát hành hóa đơn
     */
    public function issue(int $recordId)
    {
        $this->_checkPermission('issue');
        $this->_requireAjax();

        $result = $this->service->issueInvoice($this->tenantId, $recordId, $this->environment);
        $this->_json($result);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // DELETE DRAFT
    // ═══════════════════════════════════════════════════════════════════════════

    public function delete_draft(int $recordId)
    {
        $this->_checkPermission('delete');
        $this->_requireAjax();

        $result = $this->service->deleteInvoice($this->tenantId, $recordId, $this->environment);
        $this->_json($result);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CANCEL
    // ═══════════════════════════════════════════════════════════════════════════

    public function cancel_invoice(int $recordId)
    {
        $this->_checkPermission('cancel');
        $this->_requireAjax();

        $reason = $this->input->post('reason', true) ?? '';
        $result = $this->service->cancelInvoice($this->tenantId, $recordId, $this->environment, $reason);
        $this->_json($result);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // DOWNLOAD
    // ═══════════════════════════════════════════════════════════════════════════

    public function download(int $recordId, string $type = 'pdf')
    {
        $this->_checkPermission('download');

        $type   = in_array($type, ['pdf', 'xml']) ? $type : 'pdf';
        $result = $this->service->downloadFile($this->tenantId, $recordId, $type, $this->environment);

        if (!$result['success']) {
            set_alert('danger', $result['message']);
            redirect(admin_url('kt_einvoice/invoice_detail/' . $recordId));
        }

        // Redirect đến URL download từ SePay
        redirect($result['url']);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // BATCH ISSUE
    // ═══════════════════════════════════════════════════════════════════════════

    public function batch_issue()
    {
        $this->_checkPermission('batch_issue');

        if (!$this->quotaService()->hasFeature($this->tenantId, KT_EINVOICE_FEATURE_BATCH_ISSUE)) {
            show_error(_l('kt_einvoice_error_batch_not_enabled'), 403);
        }

        if ($this->input->post()) {
            $this->_requireAjax();
            $ids    = (array) $this->input->post('invoice_ids');
            $ids    = array_map('intval', $ids);
            $result = $this->service->createBatchIssue($this->tenantId, $ids, $this->environment, get_staff_user_id());
            $this->_json($result);
            return;
        }

        // Load Perfex invoices chưa có HĐĐT
        $eligibleInvoices = $this->_getEligibleInvoices();
        $maxBatchSize     = (new KtEinvoiceQuotaService())->getMaxBatchSize($this->tenantId);

        $data = [
            'title'           => _l('kt_einvoice_batch_title'),
            'invoices'        => $eligibleInvoices,
            'max_batch_size'  => $maxBatchSize,
            'environment'     => $this->environment,
        ];

        $this->load->view('kt_einvoice/tenant/batch_issue', $data);
    }

    /**
     * AJAX: Poll trạng thái batch session
     */
    public function batch_status(string $sessionCode)
    {
        $this->_requireAjax();
        $session = $this->Kt_einvoice_model->getBatchSession($sessionCode, $this->tenantId);
        $this->_json(['success' => true, 'data' => $session]);
    }

    /**
     * AJAX: Kiểm tra trạng thái record thủ công
     */
    public function check_status(int $recordId)
    {
        $this->_requireAjax();
        $record = $this->Kt_einvoice_model->getRecord($recordId, $this->tenantId);
        if (!$record) {
            $this->_json(['success' => false, 'message' => 'Không tìm thấy.']);
            return;
        }
        $this->_json(['success' => true, 'data' => [
            'status'         => $record['status'],
            'invoice_number' => $record['invoice_number'],
            'status_message' => $record['status_message'],
        ]]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════════════════════

    private function _getEnvironment(): string
    {
        // Đọc từ session hoặc settings — default sandbox khi chưa cài
        return $this->session->userdata('kt_einvoice_env') ?: 'production';
    }

    private function _checkPermission(string $capability): void
    {
        if (!staff_can($capability, 'kt_einvoice')) {
            show_error(_l('kt_einvoice_error_permission'), 403);
        }
    }

    private function _requireAjax(): void
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
    }

    private function _json(array $data): void
    {
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }

    private function _requireServices(): void
    {
        $base = APPPATH . '../modules/kt_einvoice/services/';
        require_once $base . 'KtEinvoiceEncryptionService.php';
        require_once $base . 'SepayEinvoiceApiClient.php';
        require_once $base . 'KtEinvoiceMapperService.php';
        require_once $base . 'KtEinvoiceQuotaService.php';
        require_once $base . 'KtEinvoiceService.php';
    }

    private function quotaService(): KtEinvoiceQuotaService
    {
        return new KtEinvoiceQuotaService();
    }

    private function _getEligibleInvoices(): array
    {
        $CI = &get_instance();
        // Lấy invoices của tenant (đang trong Tenant DB context)
        // Chỉ lấy invoices đã gửi (status 2) và chưa có HĐĐT issued
        $invoices = $CI->db
            ->select('i.id, i.number, i.date, i.total, c.company, c.firstname, c.lastname')
            ->from(db_prefix() . 'invoices i')
            ->join(db_prefix() . 'clients c', 'c.userid = i.clientid', 'left')
            ->where_in('i.status', [2, 4]) // 2=Sent, 4=Partial
            ->order_by('i.date', 'DESC')
            ->limit(200)
            ->get()
            ->result_array();

        // Filter: bỏ những invoice đã có record issued
        $issuedIds = $CI->db
            ->select('perfex_invoice_id')
            ->where('tenant_id', $this->tenantId)
            ->where('environment', $this->environment)
            ->where_in('status', [KT_EINVOICE_STATUS_ISSUED, KT_EINVOICE_STATUS_PENDING_ISSUE, KT_EINVOICE_STATUS_PENDING_CREATE, KT_EINVOICE_STATUS_DRAFT])
            ->get(db_prefix() . 'kt_einvoice_records')
            ->result_array();

        $issuedSet = array_column($issuedIds, 'perfex_invoice_id');

        return array_filter($invoices, fn($inv) => !in_array($inv['id'], $issuedSet));
    }
}
