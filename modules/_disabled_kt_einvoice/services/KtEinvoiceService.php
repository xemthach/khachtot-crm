<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * KT eInvoice — Main Business Logic Service
 *
 * Orchestrates: entitlement check → quota check → mapper → API client → DB
 */
class KtEinvoiceService
{
    /** @var Kt_einvoice_model */
    private $model;

    /** @var KtEinvoiceQuotaService */
    private $quotaService;

    /** @var KtEinvoiceMapperService */
    private $mapper;

    /** @var KtEinvoiceEncryptionService */
    private $encryption;

    public function __construct()
    {
        $CI = &get_instance();

        if (!isset($CI->Kt_einvoice_model)) {
            $CI->load->model('kt_einvoice/Kt_einvoice_model');
        }
        $this->model = $CI->Kt_einvoice_model;

        $this->quotaService = new KtEinvoiceQuotaService();
        $this->mapper       = new KtEinvoiceMapperService();
        $this->encryption   = new KtEinvoiceEncryptionService();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SETTINGS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Lưu settings (upsert) — mã hóa credentials trước khi lưu
     */
    public function saveSettings(int $tenantId, string $environment, array $input): array
    {
        $data = [
            'provider_account_id'   => $input['provider_account_id'] ?? null,
            'provider_account_name' => $input['provider_account_name'] ?? null,
            'invoice_series'        => $input['invoice_series'] ?? 'C',
            'invoice_template_code' => $input['invoice_template_code'] ?? '01GTKT',
            'seller_tax_code'       => $input['seller_tax_code'] ?? null,
            'seller_name'           => $input['seller_name'] ?? null,
            'seller_address'        => $input['seller_address'] ?? null,
            'seller_phone'          => $input['seller_phone'] ?? null,
            'seller_email'          => $input['seller_email'] ?? null,
            'seller_bank_name'      => $input['seller_bank_name'] ?? null,
            'seller_bank_account'   => $input['seller_bank_account'] ?? null,
            'auto_issue_on_payment' => !empty($input['auto_issue_on_payment']) ? 1 : 0,
            'is_active'             => !empty($input['is_active']) ? 1 : 0,
        ];

        // Username
        if (!empty($input['api_username'])) {
            ['ciphertext' => $ct, 'iv' => $iv] = $this->encryption->encrypt($input['api_username']);
            $data['api_username_encrypted'] = $ct;
            $data['api_username_iv']        = $iv;
        }

        // Password — chỉ update nếu user nhập mới
        if (!empty($input['api_password'])) {
            ['ciphertext' => $ct, 'iv' => $iv] = $this->encryption->encrypt($input['api_password']);
            $data['api_password_encrypted'] = $ct;
            $data['api_password_iv']        = $iv;
            // Reset cached token khi đổi credentials
            $data['access_token_encrypted'] = null;
            $data['access_token_iv']        = null;
            $data['token_expires_at']       = null;
        }

        $ok = $this->model->upsertSettings($tenantId, $environment, $data);
        return ['success' => $ok];
    }

    /**
     * Lấy settings + giải mã credentials (trả về plaintext username, giữ password ẩn)
     */
    public function getSettingsForDisplay(int $tenantId, string $environment): array
    {
        $settings = $this->model->getSettings($tenantId, $environment);
        if (!$settings) return [];

        // Giải mã username để hiển thị
        if (!empty($settings['api_username_encrypted']) && !empty($settings['api_username_iv'])) {
            $settings['api_username'] = $this->encryption->decrypt(
                $settings['api_username_encrypted'],
                $settings['api_username_iv']
            );
        }

        // KHÔNG giải mã password để hiển thị
        unset(
            $settings['api_username_encrypted'], $settings['api_username_iv'],
            $settings['api_password_encrypted'], $settings['api_password_iv'],
            $settings['access_token_encrypted'], $settings['access_token_iv']
        );

        return $settings;
    }

    /**
     * Xây dựng API client từ settings của tenant
     */
    public function buildApiClient(int $tenantId, string $environment): SepayEinvoiceApiClient
    {
        $settings = $this->model->getActiveSettings($tenantId, $environment);
        if (!$settings) {
            throw new RuntimeException(_l('kt_einvoice_error_not_configured'));
        }

        $username = $this->encryption->decrypt(
            $settings['api_username_encrypted'] ?? '',
            $settings['api_username_iv'] ?? ''
        );
        $password = $this->encryption->decrypt(
            $settings['api_password_encrypted'] ?? '',
            $settings['api_password_iv'] ?? ''
        );

        if (empty($username) || empty($password)) {
            throw new RuntimeException(_l('kt_einvoice_error_not_configured'));
        }

        // Decrypt cached token nếu còn hạn
        $cachedToken   = null;
        $cachedExpires = 0;
        if (!empty($settings['access_token_encrypted']) && !empty($settings['token_expires_at'])) {
            $cachedToken   = $this->encryption->decrypt(
                $settings['access_token_encrypted'],
                $settings['access_token_iv'] ?? ''
            );
            $cachedExpires = strtotime($settings['token_expires_at']) ?: 0;
        }

        return new SepayEinvoiceApiClient(
            $environment,
            $username,
            $password,
            $tenantId,
            $cachedToken,
            $cachedExpires
        );
    }

    /**
     * Test kết nối SePay
     */
    public function testConnection(int $tenantId, string $environment): array
    {
        try {
            $client  = $this->buildApiClient($tenantId, $environment);
            $usage   = $client->checkUsage();
            $remaining = $usage['data']['remaining'] ?? null;
            return [
                'success'   => true,
                'message'   => _l('kt_einvoice_connection_ok'),
                'remaining' => $remaining,
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CREATE DRAFT
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Tạo hóa đơn nháp từ Perfex Invoice
     *
     * @param int    $tenantId
     * @param int    $perfexInvoiceId
     * @param string $environment
     * @param int    $createdBy  Staff ID
     * @return array{success: bool, message: string, record_id?: int, tracking_code?: string}
     */
    public function createDraft(int $tenantId, int $perfexInvoiceId, string $environment, int $createdBy = 0): array
    {
        // 1. Kiểm tra entitlement
        $quotaCheck = $this->quotaService->canIssue($tenantId, $environment);
        if (!$quotaCheck['allowed']) {
            return ['success' => false, 'message' => $quotaCheck['reason']];
        }

        // 2. Kiểm tra idempotency — không tạo trùng
        $idempotencyKey = $this->buildIdempotencyKey($tenantId, $perfexInvoiceId, $environment);
        $existing       = $this->model->getRecordByIdempotencyKey($idempotencyKey);

        if ($existing) {
            // Đã tồn tại — trả về record hiện tại
            if (in_array($existing['status'], [KT_EINVOICE_STATUS_ISSUED, KT_EINVOICE_STATUS_DRAFT, KT_EINVOICE_STATUS_PENDING_CREATE, KT_EINVOICE_STATUS_PENDING_ISSUE])) {
                return [
                    'success'    => true,
                    'message'    => _l('kt_einvoice_error_already_exists'),
                    'record_id'  => (int) $existing['id'],
                    'status'     => $existing['status'],
                    'idempotent' => true,
                ];
            }
        }

        // 3. Load settings
        $settings = $this->model->getActiveSettings($tenantId, $environment);
        if (!$settings) {
            return ['success' => false, 'message' => _l('kt_einvoice_error_not_configured')];
        }

        // Validate seller info
        if (empty($settings['seller_tax_code']) || empty($settings['seller_name'])) {
            return ['success' => false, 'message' => _l('kt_einvoice_error_seller_incomplete')];
        }

        // 4. Đọc invoice từ Tenant DB
        $invoiceData = $this->loadTenantInvoice($perfexInvoiceId);
        if (!$invoiceData) {
            return ['success' => false, 'message' => 'Invoice không tồn tại.'];
        }

        [$invoice, $client, $items, $taxItems] = $invoiceData;

        // 5. Build payload
        $payload  = $this->mapper->buildCreatePayload($settings, $settings, $client, $items, $taxItems);
        // Note: first $settings used as perfexInvoice stand-in for non-invoice fields
        // Actually fix: pass $invoice as first arg
        $payload  = $this->mapper->buildCreatePayload($invoice, $settings, $client, $items, $taxItems);

        // 6. Validate payload
        $validation = $this->mapper->validatePayload($payload);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => implode(' | ', $validation['errors'])];
        }

        // 7. Gọi SePay API
        try {
            $client = $this->buildApiClient($tenantId, $environment);
            $response = $client->createInvoice($payload);
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $trackingCode = $response['data']['tracking_code'] ?? null;
        $status       = $response['data']['status'] ?? 'pending';

        // 8. Lưu record
        $recordData = [
            'tenant_id'              => $tenantId,
            'perfex_invoice_id'      => $perfexInvoiceId,
            'perfex_invoice_number'  => $invoice['number'] ?? '',
            'environment'            => $environment,
            'sepay_tracking_code'    => $trackingCode,
            'status'                 => KT_EINVOICE_STATUS_PENDING_CREATE,
            'invoice_date'           => $invoice['date'] ?? date('Y-m-d'),
            'buyer_name'             => $payload['buyer']['name'] ?? '',
            'buyer_tax_code'         => $payload['buyer']['tax_code'] ?? null,
            'total_amount'           => $payload['totals']['total'] ?? 0,
            'tax_amount'             => $payload['totals']['tax_total'] ?? 0,
            'currency'               => 'VND',
            'idempotency_key'        => $idempotencyKey,
            'create_attempts'        => 1,
            'last_attempt_at'        => date('Y-m-d H:i:s'),
            'request_payload_json'   => json_encode($payload),
            'response_payload_json'  => json_encode($response),
            'created_by'             => $createdBy,
        ];

        // Nếu SePay trả về draft ngay
        if (isset($response['data']['invoice_id'])) {
            $recordData['sepay_invoice_id'] = $response['data']['invoice_id'];
            $recordData['status']           = KT_EINVOICE_STATUS_DRAFT;
        }

        $recordId = $this->model->insertRecord($recordData);

        return [
            'success'       => true,
            'message'       => _l('kt_einvoice_success_draft_created'),
            'record_id'     => $recordId,
            'tracking_code' => $trackingCode,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ISSUE INVOICE
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Phát hành hóa đơn đã tạo nháp
     */
    public function issueInvoice(int $tenantId, int $recordId, string $environment): array
    {
        $record = $this->model->getRecord($recordId, $tenantId);
        if (!$record) {
            return ['success' => false, 'message' => 'Không tìm thấy hóa đơn.'];
        }

        if ($record['status'] !== KT_EINVOICE_STATUS_DRAFT) {
            return ['success' => false, 'message' => _l('kt_einvoice_error_invalid_status')];
        }

        // Quota check lần cuối trước khi issue
        $quotaCheck = $this->quotaService->canIssue($tenantId, $environment);
        if (!$quotaCheck['allowed']) {
            return ['success' => false, 'message' => $quotaCheck['reason']];
        }

        try {
            $client   = $this->buildApiClient($tenantId, $environment);
            $response = $client->issueInvoice($record['sepay_invoice_id']);
        } catch (Exception $e) {
            $this->model->updateRecord($recordId, [
                'issue_attempts'  => (int) $record['issue_attempts'] + 1,
                'status_message'  => $e->getMessage(),
                'last_attempt_at' => date('Y-m-d H:i:s'),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $issueTracking = $response['data']['tracking_code'] ?? null;

        $this->model->updateRecord($recordId, [
            'status'                => KT_EINVOICE_STATUS_PENDING_ISSUE,
            'sepay_issue_tracking'  => $issueTracking,
            'issue_attempts'        => (int) $record['issue_attempts'] + 1,
            'last_attempt_at'       => date('Y-m-d H:i:s'),
            'response_payload_json' => json_encode($response),
        ]);

        return [
            'success'        => true,
            'message'        => _l('kt_einvoice_success_issued'),
            'tracking_code'  => $issueTracking,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // STATUS POLLING
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Poll trạng thái create (cron)
     */
    public function pollCreateStatus(int $tenantId, int $recordId, string $environment, SepayEinvoiceApiClient $client): void
    {
        $record = $this->model->getRecord($recordId, $tenantId);
        if (!$record || empty($record['sepay_tracking_code'])) return;

        // Timeout check
        $createdAt = strtotime($record['created_at']);
        if ((time() - $createdAt) > KT_EINVOICE_POLL_TIMEOUT) {
            $this->model->updateRecordStatus($recordId, KT_EINVOICE_STATUS_FAILED_CREATE, 'Timeout sau ' . (KT_EINVOICE_POLL_TIMEOUT / 60) . ' phút.');
            return;
        }

        try {
            $response = $client->checkStatus($record['sepay_tracking_code']);
            $status   = $response['data']['status'] ?? '';
            $invoiceId = $response['data']['invoice_id'] ?? null;

            if ($status === 'success' || $status === 'draft') {
                $updateData = ['status' => KT_EINVOICE_STATUS_DRAFT];
                if ($invoiceId) {
                    $updateData['sepay_invoice_id']      = $invoiceId;
                    $updateData['invoice_series']        = $response['data']['series'] ?? null;
                    $updateData['invoice_template']      = $response['data']['template'] ?? null;
                }
                $this->model->updateRecord($recordId, $updateData);
            } elseif ($status === 'failed' || $status === 'error') {
                $msg = $response['data']['message'] ?? 'SePay trả về lỗi.';
                $attempts = (int) $record['create_attempts'];

                if ($attempts < KT_EINVOICE_MAX_CREATE_ATTEMPTS) {
                    // Schedule retry
                    $nextRetry = date('Y-m-d H:i:s', time() + (KT_EINVOICE_RETRY_BASE_DELAY * (2 ** $attempts)));
                    $this->model->updateRecord($recordId, [
                        'status_message'   => $msg,
                        'create_attempts'  => $attempts + 1,
                        'next_retry_at'    => $nextRetry,
                    ]);
                } else {
                    $this->model->updateRecordStatus($recordId, KT_EINVOICE_STATUS_FAILED_CREATE, $msg);
                }
            }
            // status = 'processing' → tiếp tục chờ
        } catch (Exception $e) {
            log_message('error', "[kt_einvoice] pollCreateStatus error record $recordId: " . $e->getMessage());
        }
    }

    /**
     * Poll trạng thái issue (cron)
     */
    public function pollIssueStatus(int $tenantId, int $recordId, string $environment, SepayEinvoiceApiClient $client): void
    {
        $record = $this->model->getRecord($recordId, $tenantId);
        if (!$record || empty($record['sepay_issue_tracking'])) return;

        try {
            $response     = $client->checkStatus($record['sepay_issue_tracking']);
            $status       = $response['data']['status'] ?? '';
            $invoiceNumber = $response['data']['invoice_number'] ?? null;

            if ($status === 'success' || $status === 'issued') {
                $this->model->updateRecord($recordId, [
                    'status'         => KT_EINVOICE_STATUS_ISSUED,
                    'invoice_number' => $invoiceNumber,
                    'invoice_series' => $response['data']['series'] ?? $record['invoice_series'],
                    'issued_at'      => date('Y-m-d H:i:s'),
                    'status_message' => null,
                ]);
                // Tăng quota sử dụng
                $this->quotaService->incrementUsage($tenantId, $environment);

            } elseif ($status === 'failed' || $status === 'error') {
                $msg      = $response['data']['message'] ?? 'Phát hành thất bại.';
                $attempts = (int) $record['issue_attempts'];

                if ($attempts < KT_EINVOICE_MAX_ISSUE_ATTEMPTS) {
                    $nextRetry = date('Y-m-d H:i:s', time() + (KT_EINVOICE_RETRY_BASE_DELAY * (2 ** $attempts)));
                    $this->model->updateRecord($recordId, [
                        'status'         => KT_EINVOICE_STATUS_DRAFT, // Về lại draft để có thể issue lại
                        'status_message' => $msg,
                        'issue_attempts' => $attempts + 1,
                        'next_retry_at'  => $nextRetry,
                    ]);
                } else {
                    $this->model->updateRecordStatus($recordId, KT_EINVOICE_STATUS_FAILED_ISSUE, $msg);
                    $this->model->incrementFailedCount($tenantId, $environment);
                }
            }
        } catch (Exception $e) {
            log_message('error', "[kt_einvoice] pollIssueStatus error record $recordId: " . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // DELETE DRAFT
    // ═══════════════════════════════════════════════════════════════════════════

    public function deleteInvoice(int $tenantId, int $recordId, string $environment): array
    {
        $record = $this->model->getRecord($recordId, $tenantId);
        if (!$record) {
            return ['success' => false, 'message' => 'Không tìm thấy hóa đơn.'];
        }

        if ($record['status'] !== KT_EINVOICE_STATUS_DRAFT) {
            return ['success' => false, 'message' => 'Chỉ có thể xóa hóa đơn ở trạng thái nháp.'];
        }

        try {
            $client = $this->buildApiClient($tenantId, $environment);
            $client->deleteInvoice($record['sepay_invoice_id']);
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $this->model->updateRecord($recordId, [
            'status'     => KT_EINVOICE_STATUS_DELETED,
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);

        return ['success' => true, 'message' => _l('kt_einvoice_success_deleted')];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CANCEL ISSUED INVOICE
    // ═══════════════════════════════════════════════════════════════════════════

    public function cancelInvoice(int $tenantId, int $recordId, string $environment, string $reason = ''): array
    {
        // Kiểm tra entitlement cancel
        if (!$this->quotaService->hasFeature($tenantId, KT_EINVOICE_FEATURE_CANCEL)) {
            return ['success' => false, 'message' => _l('kt_einvoice_error_not_entitled')];
        }

        $record = $this->model->getRecord($recordId, $tenantId);
        if (!$record) {
            return ['success' => false, 'message' => 'Không tìm thấy hóa đơn.'];
        }

        if ($record['status'] !== KT_EINVOICE_STATUS_ISSUED) {
            return ['success' => false, 'message' => _l('kt_einvoice_error_cancel_issued_only')];
        }

        try {
            $client   = $this->buildApiClient($tenantId, $environment);
            $response = $client->cancelInvoice($record['sepay_invoice_id'], $reason);
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $cancelTracking = $response['data']['tracking_code'] ?? null;

        $this->model->updateRecord($recordId, [
            'status'                => KT_EINVOICE_STATUS_PENDING_CANCEL,
            'cancel_reason'         => $reason,
            'sepay_cancel_tracking' => $cancelTracking,
            'cancel_attempts'       => 1,
            'last_attempt_at'       => date('Y-m-d H:i:s'),
        ]);

        return [
            'success'       => true,
            'message'       => _l('kt_einvoice_success_cancelled'),
            'tracking_code' => $cancelTracking,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // DOWNLOAD
    // ═══════════════════════════════════════════════════════════════════════════

    public function downloadFile(int $tenantId, int $recordId, string $type, string $environment): array
    {
        // Kiểm tra XML entitlement
        if ($type === KT_EINVOICE_DOWNLOAD_XML && !$this->quotaService->hasFeature($tenantId, KT_EINVOICE_FEATURE_DOWNLOAD_XML)) {
            return ['success' => false, 'message' => _l('kt_einvoice_error_not_entitled')];
        }

        $record = $this->model->getRecord($recordId, $tenantId);
        if (!$record || $record['status'] !== KT_EINVOICE_STATUS_ISSUED) {
            return ['success' => false, 'message' => 'Hóa đơn chưa được phát hành.'];
        }

        try {
            $client   = $this->buildApiClient($tenantId, $environment);
            $response = $client->downloadInvoice($record['sepay_invoice_id'], $type);
        } catch (Exception $e) {
            return ['success' => false, 'message' => _l('kt_einvoice_error_download_failed')];
        }

        $url = $response['data']['url'] ?? null;
        if (!$url) {
            return ['success' => false, 'message' => _l('kt_einvoice_error_download_failed')];
        }

        // Cache URL
        if ($type === KT_EINVOICE_DOWNLOAD_PDF) {
            $this->model->updateRecord($recordId, ['pdf_url' => $url, 'pdf_downloaded_at' => date('Y-m-d H:i:s')]);
        } else {
            $this->model->updateRecord($recordId, ['xml_url' => $url]);
        }

        return ['success' => true, 'url' => $url, 'filename' => ($record['invoice_number'] ?: "einvoice-$recordId") . '.' . $type];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // BATCH ISSUE
    // ═══════════════════════════════════════════════════════════════════════════

    public function createBatchIssue(int $tenantId, array $perfexInvoiceIds, string $environment, int $createdBy): array
    {
        if (!$this->quotaService->hasFeature($tenantId, KT_EINVOICE_FEATURE_BATCH_ISSUE)) {
            return ['success' => false, 'message' => _l('kt_einvoice_error_batch_not_enabled')];
        }

        $maxSize = $this->quotaService->getMaxBatchSize($tenantId);
        if (count($perfexInvoiceIds) > $maxSize) {
            return ['success' => false, 'message' => str_replace('{max}', $maxSize, _l('kt_einvoice_batch_max_exceeded'))];
        }

        $batch = $this->model->createBatchSession($tenantId, $environment, $perfexInvoiceIds, $createdBy);

        return [
            'success'      => true,
            'message'      => str_replace('{count}', count($perfexInvoiceIds), _l('kt_einvoice_success_batch_queued')),
            'session_code' => $batch['session_code'],
            'batch_id'     => $batch['batch_id'],
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════════════

    private function buildIdempotencyKey(int $tenantId, int $perfexInvoiceId, string $environment): string
    {
        return hash('sha256', "kt_einvoice:{$tenantId}:{$perfexInvoiceId}:{$environment}");
    }

    /**
     * Đọc invoice từ Tenant DB
     * Trả về [invoice, client, items, taxItems] hoặc null
     */
    private function loadTenantInvoice(int $perfexInvoiceId): ?array
    {
        $CI = &get_instance();

        // Load invoice từ Tenant DB (đang dùng Perfex's invoices model)
        if (!isset($CI->invoices_model)) {
            $CI->load->model('invoices_model');
        }

        $invoice = $CI->invoices_model->get($perfexInvoiceId);
        if (!$invoice) return null;

        $invoice = (array) $invoice;

        // Load client
        if (!isset($CI->clients_model)) {
            $CI->load->model('clients_model');
        }
        $client = (array) ($CI->clients_model->get($invoice['clientid']) ?? []);

        // Load invoice items
        $items = $CI->db
            ->where('rel_id', $perfexInvoiceId)
            ->where('rel_type', 'invoice')
            ->get(db_prefix() . 'itemable')
            ->result_array();

        // Load tax items
        $taxItems = $CI->db
            ->where('itemid IN (SELECT id FROM ' . db_prefix() . 'itemable WHERE rel_id = ' . (int) $perfexInvoiceId . ' AND rel_type = \'invoice\')')
            ->get(db_prefix() . 'item_tax')
            ->result_array();

        return [$invoice, $client, $items, $taxItems];
    }
}
