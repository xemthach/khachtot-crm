<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_sepay extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(KT_SEPAY_MODULE . '/kt_sepay');
        $this->load->model(KT_SEPAY_MODULE . '/Kt_sepay_model');
        $this->load->library(KT_SEPAY_MODULE . '/Kt_sepay_gateway');
        $this->load->library(KT_SEPAY_MODULE . '/Kt_sepay_api');
        $this->load->library(KT_SEPAY_MODULE . '/Kt_sepay_processor');

        if ($this->isTenantMethod()) {
            $this->requireTenantContext();
            $this->requireTenantAdmin();
            return;
        }

        $this->requireLandlordContext();
    }

    public function index()
    {
        $this->requireCapability('kt_sepay_view');
        $data['title'] = _l('kt_sepay_dashboard');
        $data['summary'] = $this->Kt_sepay_model->get_summary();
        $data['settings'] = $this->Kt_sepay_model->get_settings();
        $data['health_logs'] = $this->Kt_sepay_model->get_health_logs();
        $data['health_checks_enabled'] = false;
        $data['can_edit_settings'] = false;
        $data['can_run_health_checks'] = false;
        $data['can_run_reconcile'] = false;
        $data['form_action'] = admin_url('kt_sepay/settings_save');
        $data['readonly_notice'] = 'Trang Overview chỉ để theo dõi. Vui lòng vào mục Settings để cấu hình và lưu KT SePay.';
        $this->load->view(KT_SEPAY_MODULE . '/admin/settings', $data);
    }

    public function settings()
    {
        $this->requireCapability('kt_sepay_manage_settings');
        $data['title'] = _l('kt_sepay_settings');
        $data['settings'] = $this->Kt_sepay_model->get_settings();
        $data['summary'] = $this->Kt_sepay_model->get_summary();
        $data['health_logs'] = $this->Kt_sepay_model->get_health_logs();
        $data['health_checks_enabled'] = true;
        $data['can_edit_settings'] = true;
        $data['can_run_health_checks'] = true;
        $data['can_run_reconcile'] = true;
        $data['form_action'] = admin_url('kt_sepay/settings_save');
        $this->load->view(KT_SEPAY_MODULE . '/admin/settings', $data);
    }

    public function settings_save()
    {
        $this->requireCapability('kt_sepay_manage_settings');
        $this->requirePost();

        $this->Kt_sepay_model->save_settings($this->input->post());
        set_alert('success', _l('settings_updated'));
        redirect(admin_url('kt_sepay/settings'));
    }

    public function transactions()
    {
        $this->requireCapability('kt_sepay_manage_payments');
        $data['title'] = _l('kt_sepay_transactions');
        $data['transactions'] = $this->Kt_sepay_model->get_transactions();
        $data['open_requests'] = $this->Kt_sepay_model->get_payment_requests(['status' => 'pending']);
        $this->load->view(KT_SEPAY_MODULE . '/admin/transactions', $data);
    }

    public function bulk_transactions()
    {
        $this->requireCapability('kt_sepay_manage_payments');
        $this->requirePost();

        $action = trim((string) $this->input->post('bulk_action'));
        $ids = $this->normalizeBulkIds($this->input->post('ids'));
        if ($action === '' || empty($ids)) {
            set_alert('warning', 'Vui lòng chọn ít nhất một giao dịch và một thao tác.');
            redirect(admin_url('kt_sepay/transactions'));
        }

        if ($action === 'export_csv') {
            $rows = [];
            foreach ($ids as $id) {
                $transaction = $this->Kt_sepay_model->get_transaction($id);
                if ($transaction) {
                    $rows[] = $transaction;
                }
            }

            $this->streamCsv('kt_sepay_transactions_selected.csv', [
                'ID',
                'Mã GD SePay',
                'Gateway',
                'Số tiền',
                'Nội dung',
                'Trạng thái',
                'Mã tham chiếu',
                'Yêu cầu thanh toán',
                'Tenant ID',
            ], array_map(function ($row) {
                return [
                    $row['id'],
                    $row['sepay_transaction_id'],
                    $row['gateway'],
                    $row['transfer_amount'],
                    $row['content'],
                    $row['status'],
                    $row['matched_reference'],
                    $row['payment_request_id'],
                    $row['tenant_id'],
                ];
            }, $rows));
        }

        $processed = 0;
        $failed = 0;
        foreach ($ids as $id) {
            $transaction = $this->Kt_sepay_model->get_transaction($id);
            if (!$transaction) {
                $failed++;
                continue;
            }

            $ok = false;
            if ($action === 'mark_unmatched') {
                $ok = $this->Kt_sepay_model->update_transaction($id, [
                    'status'            => 'unmatched',
                    'matched_reference' => null,
                    'matched_type'      => null,
                    'matched_id'        => null,
                    'payment_request_id'=> null,
                    'processed_at'      => null,
                ]);
            } elseif ($action === 'retry_processing') {
                $payload = kt_sepay_json_decode((string) ($transaction['raw_payload'] ?? ''), []);
                if (!empty($payload)) {
                    $result = $this->kt_sepay_processor->processIncomingTransaction($payload, ['source' => 'bulk_retry', 'reprocess_existing' => true]);
                    $ok = !empty($result['success']) || in_array((string) ($result['status'] ?? ''), ['duplicate', 'processed', 'matched'], true);
                }
            }

            if ($ok) {
                $processed++;
            } else {
                $failed++;
            }
        }

        log_activity('KT SePay bulk transactions action [' . $action . '] processed=' . $processed . ' failed=' . $failed);
        $this->setBulkAlert($processed, $failed, 'Đã xử lý giao dịch hàng loạt.');
        redirect(admin_url('kt_sepay/transactions'));
    }

    public function payment_requests()
    {
        $this->requireCapability('kt_sepay_manage_payments');
        $data['title'] = _l('kt_sepay_payment_requests');
        $data['requests'] = $this->Kt_sepay_model->get_payment_requests();
        $this->load->view(KT_SEPAY_MODULE . '/admin/transactions', $data);
    }

    public function bulk_payment_requests()
    {
        $this->requireCapability('kt_sepay_manage_payments');
        $this->requirePost();

        $action = trim((string) $this->input->post('bulk_action'));
        $ids = $this->normalizeBulkIds($this->input->post('ids'));
        if ($action === '' || empty($ids)) {
            set_alert('warning', 'Vui lòng chọn ít nhất một yêu cầu thanh toán và một thao tác.');
            redirect(admin_url('kt_sepay/payment_requests'));
        }

        $processed = 0;
        $failed = 0;
        foreach ($ids as $id) {
            $request = $this->Kt_sepay_model->get_payment_request($id);
            if (!$request) {
                $failed++;
                continue;
            }

            $ok = false;
            if ($action === 'expire') {
                $ok = $this->Kt_sepay_model->update_payment_request($id, [
                    'status'     => 'expired',
                    'expires_at' => kt_sepay_now(),
                ]);
            } elseif ($action === 'cancel') {
                $ok = $this->Kt_sepay_model->update_payment_request($id, [
                    'status' => 'cancelled',
                ]);
            }

            if ($ok) {
                $processed++;
            } else {
                $failed++;
            }
        }

        log_activity('KT SePay bulk payment requests action [' . $action . '] processed=' . $processed . ' failed=' . $failed);
        $this->setBulkAlert($processed, $failed, 'Đã xử lý yêu cầu thanh toán hàng loạt.');
        redirect(admin_url('kt_sepay/payment_requests'));
    }

    public function logs()
    {
        $this->requireCapability('kt_sepay_manage_logs');
        $data['title'] = _l('kt_sepay_webhook_logs');
        $data['logs'] = $this->Kt_sepay_model->get_webhook_logs();
        $this->load->view(KT_SEPAY_MODULE . '/admin/logs', $data);
    }

    public function reconciliation()
    {
        $this->requireCapability('kt_sepay_run_reconcile');
        $data['title'] = _l('kt_sepay_reconciliation');
        $data['logs'] = $this->Kt_sepay_model->get_reconciliation_logs();
        $data['settings'] = $this->Kt_sepay_model->get_settings();
        $data['summary'] = $this->buildReconciliationSummary($data['logs']);
        $data['last_reconcile_at'] = (string) get_option('kt_sepay_last_reconcile_at');
        $data['last_reconcile_transaction_id'] = (string) get_option('kt_sepay_last_reconcile_transaction_id');
        $data['health_logs'] = $this->Kt_sepay_model->get_health_logs(20);
        $data['latest_reconciliation_health_check'] = $this->findLatestHealthLog($data['health_logs'], ['test_reconciliation']);
        $data['latest_connection_health_check'] = $this->findLatestHealthLog($data['health_logs'], ['test_connection']);
        $this->load->view(KT_SEPAY_MODULE . '/admin/reconciliation', $data);
    }

    public function run_reconcile()
    {
        $this->requireCapability('kt_sepay_run_reconcile');
        $this->requirePost();

        $result = $this->performReconciliation();
        set_alert(!empty($result['success']) ? 'success' : 'warning', trim((string) ($result['message'] ?? 'Đã hoàn tất đối soát.')));
        redirect(admin_url('kt_sepay/reconciliation'));
    }

    public function manual_match($transactionId)
    {
        $this->requireCapability('kt_sepay_manage_payments');
        if (strtolower((string) $this->input->method()) !== 'post') {
            show_404();
        }

        $transaction = null;
        foreach ($this->Kt_sepay_model->get_transactions(['status' => 'unmatched']) as $row) {
            if ((int) $row['id'] === (int) $transactionId) {
                $transaction = $row;
                break;
            }
        }
        if (!$transaction) {
            show_404();
        }

        $request = $this->Kt_sepay_model->get_payment_request((int) $this->input->post('payment_request_id'));
        if (!$request) {
            set_alert('warning', 'Không tìm thấy yêu cầu thanh toán.');
            redirect(admin_url('kt_sepay/transactions'));
        }

        $payload = kt_sepay_json_decode((string) ($transaction['raw_payload'] ?? ''), []);
        if (empty($payload)) {
            set_alert('warning', 'Không có dữ liệu giao dịch để xử lý.');
            redirect(admin_url('kt_sepay/transactions'));
        }

        $payload['code'] = $request['reference_code'];
        $payload['content'] = $request['reference_code'];
        $payload['referenceCode'] = $request['reference_code'];
        $payload['reference_code'] = $request['reference_code'];
        $result = $this->kt_sepay_processor->processIncomingTransaction($payload, ['source' => 'manual_match', 'reprocess_existing' => true]);
        set_alert(!empty($result['success']) ? 'success' : 'warning', trim((string) ($result['message'] ?? 'Đã thử khớp thủ công giao dịch.')));
        redirect(admin_url('kt_sepay/transactions'));
    }

    public function test_mode()
    {
        $this->requireCapability('kt_sepay_manage_settings');
        $data['title'] = _l('kt_sepay_test_mode');
        $data['settings'] = $this->Kt_sepay_model->get_settings();
        $data['summary'] = $this->Kt_sepay_model->get_summary();
        $data['health_logs'] = $this->Kt_sepay_model->get_health_logs();
        $data['health_checks_enabled'] = true;
        $data['can_edit_settings'] = true;
        $data['can_run_health_checks'] = true;
        $data['can_run_reconcile'] = true;
        $data['form_action'] = admin_url('kt_sepay/settings_save');
        $data['api_accounts'] = [];
        if (!empty($data['settings']['api_token'])) {
            $accounts = $this->kt_sepay_api->listBankAccounts(['per_page' => 10]);
            $data['api_accounts'] = !empty($accounts['success']) && is_array($accounts['data']) ? $accounts['data'] : [];
        }
        $this->load->view(KT_SEPAY_MODULE . '/admin/settings', $data);
    }

    public function test_connection()
    {
        $this->requireCapability('kt_sepay_manage_settings');
        $this->requirePost();

        $settings = $this->Kt_sepay_model->get_settings();
        $started = microtime(true);
        $result = $this->kt_sepay_api->listBankAccounts(['per_page' => 1]);
        $latency = $this->elapsedMs($started);

        $success = !empty($result['success']);
        $payload = [
            'success'      => $success,
            'environment'  => (string) ($settings['environment'] ?? 'sandbox'),
            'message'      => $success ? 'Kết nối SePay thành công.' : $this->mapApiErrorMessage($result),
            'latency_ms'   => $latency,
            'checked_at'   => kt_sepay_now(),
            'status'       => $success ? 'success' : 'error',
            'http_code'    => (int) ($result['status'] ?? 0),
            'error_code'   => $this->mapApiErrorCode($result),
            'detail'       => [
                'base_url' => kt_sepay_api_base_url($settings['environment'] ?? 'sandbox'),
                'records'  => is_array($result['data']) ? count($result['data']) : 0,
            ],
        ];

        $this->logHealthCheck('test_connection', $payload, $result);
        $this->jsonResponse($payload, $success ? 200 : 400);
    }

    public function test_bank_account()
    {
        $this->requireCapability('kt_sepay_manage_settings');
        $this->requirePost();

        $settings = $this->Kt_sepay_model->get_settings();
        $bankCode = trim((string) ($settings['bank_code'] ?? ''));
        $accountNumber = trim((string) ($settings['account_number'] ?? ''));
        if ($bankCode === '' || $accountNumber === '') {
            $payload = [
                'success'    => false,
                'status'     => 'error',
                'message'    => 'Thiếu bank code hoặc account number.',
                'latency_ms' => 0,
                'http_code'  => 0,
                'error_code' => 'CONFIG_MISSING',
                'detail'     => ['bank_code' => $bankCode !== '', 'account_number' => $accountNumber !== ''],
            ];
            $this->logHealthCheck('test_bank_account', $payload);
            $this->jsonResponse($payload, 400);
            return;
        }

        $started = microtime(true);
        $result = $this->kt_sepay_api->listBankAccounts(['per_page' => 20]);
        $latency = $this->elapsedMs($started);

        $success = false;
        $status = 'warning';
        $message = 'Không thể verify tài khoản, cần kiểm tra thủ công.';
        $detail = ['configured_bank_code' => $bankCode, 'configured_account_number' => $accountNumber];
        if (!empty($result['success']) && is_array($result['data'])) {
            foreach ($result['data'] as $account) {
                $apiAccountNumber = trim((string) ($account['account_number'] ?? ''));
                $apiBankCode = strtoupper(trim((string) ($account['bank_short_name'] ?? $account['bank_code'] ?? '')));
                if ($apiAccountNumber === $accountNumber) {
                    $success = true;
                    $status = 'success';
                    $message = 'Đã tìm thấy tài khoản trong danh sách SePay.';
                    $detail['matched_account'] = $account;
                    if ($bankCode !== '' && $apiBankCode !== '' && strtoupper($bankCode) !== $apiBankCode) {
                        $success = false;
                        $status = 'warning';
                        $message = 'Tài khoản tồn tại nhưng bank code không khớp.';
                    }
                    break;
                }
            }
            if (!$success && $status !== 'warning') {
                $status = 'warning';
            }
            $detail['records'] = count($result['data']);
        } else {
            $status = 'error';
            $message = $this->mapApiErrorMessage($result);
            $detail['api_status'] = (int) ($result['status'] ?? 0);
        }

        $payload = [
            'success'    => $success,
            'status'     => $status,
            'message'    => $message,
            'latency_ms' => $latency,
            'http_code'  => (int) ($result['status'] ?? 0),
            'error_code' => $success ? null : $this->mapApiErrorCode($result),
            'detail'     => $detail,
        ];
        $this->logHealthCheck('test_bank_account', $payload, $result);
        $this->jsonResponse($payload, $success ? 200 : ($status === 'warning' ? 200 : 400));
    }

    public function test_qr()
    {
        $this->requireCapability('kt_sepay_manage_settings');
        $this->requirePost();

        $settings = $this->Kt_sepay_model->get_settings();
        $reference = 'SEPTEST' . date('YmdHis');
        $qrUrl = kt_sepay_qr_url(
            $settings['account_number'] ?? '',
            $settings['bank_code'] ?? '',
            10000,
            $reference,
            $settings['qr_template'] ?? 'compact'
        );

        $success = !empty($settings['account_number']) && !empty($settings['bank_code']);
        $payload = [
            'success'    => $success,
            'status'     => $success ? 'success' : 'error',
            'message'    => $success ? 'QR test được tạo thành công.' : 'Thiếu bank code hoặc account number để tạo QR.',
            'latency_ms' => 0,
            'http_code'  => 200,
            'error_code' => $success ? null : 'CONFIG_MISSING',
            'detail'     => [
                'reference_code' => $reference,
                'amount'         => 10000,
                'template'       => $settings['qr_template'] ?? 'compact',
                'qr_url'         => $qrUrl,
            ],
            'qr_url'     => $qrUrl,
        ];

        $this->logHealthCheck('test_qr', $payload);
        $this->jsonResponse($payload);
    }

    public function test_webhook_url()
    {
        $this->requireCapability('kt_sepay_manage_settings');
        $this->requirePost();

        $webhookUrl = kt_sepay_webhook_url();
        $csrfConfigPath = module_dir_path(KT_SEPAY_MODULE, 'config/csrf_exclude_uris.php');
        $csrfRoutes = file_exists($csrfConfigPath) ? require $csrfConfigPath : [];
        $csrfExcluded = is_array($csrfRoutes) && in_array('kt_sepay/webhook', $csrfRoutes, true);
        $controllerExists = file_exists(module_dir_path(KT_SEPAY_MODULE, 'controllers/Kt_sepay_webhook.php'));
        $https = strtolower((string) parse_url($webhookUrl, PHP_URL_SCHEME)) === 'https';
        $localWarning = kt_sepay_is_local_url($webhookUrl);

        $status = ($https && $csrfExcluded && $controllerExists) ? 'success' : 'warning';
        $payload = [
            'success'    => $status === 'success',
            'status'     => $status,
            'message'    => $status === 'success' ? 'Webhook URL hợp lệ cho triển khai.' : 'Webhook URL cần kiểm tra thêm trước khi đưa vào production.',
            'latency_ms' => 0,
            'http_code'  => 200,
            'error_code' => null,
            'detail'     => [
                'webhook_url'          => $webhookUrl,
                'is_https'             => $https,
                'csrf_excluded'        => $csrfExcluded,
                'public_controller'    => $controllerExists,
                'login_free_endpoint'  => true,
                'local_environment'    => $localWarning,
                'warning'              => $localWarning ? 'Local/test domain cần tunnel public như ngrok hoặc cloudflared để nhận webhook thật.' : '',
            ],
        ];

        $this->logHealthCheck('test_webhook_url', $payload);
        $this->jsonResponse($payload);
    }

    public function test_webhook_payload()
    {
        $this->requireCapability('kt_sepay_manage_settings');
        $this->requirePost();

        $settings = $this->Kt_sepay_model->get_settings();
        $timestamp = date('YmdHis');
        $reference = 'SEPTEST' . $timestamp;
        $payload = [
            'id'              => 'TEST-' . $timestamp,
            'gateway'         => 'test',
            'transactionDate' => kt_sepay_now(),
            'accountNumber'   => trim((string) ($settings['account_number'] ?? '')),
            'content'         => $reference,
            'transferType'    => 'in',
            'transferAmount'  => 10000,
            'referenceCode'   => $reference,
            'is_test'         => 1,
        ];

        $started = microtime(true);
        $result = $this->kt_sepay_processor->processIncomingTransaction($payload, ['source' => 'test_webhook_payload']);
        $latency = $this->elapsedMs($started);

        $response = [
            'success'    => !empty($result['success']) || in_array((string) ($result['status'] ?? ''), ['unmatched', 'duplicate', 'ignored'], true),
            'status'     => !empty($result['success']) ? 'success' : (($result['status'] ?? '') === 'unmatched' ? 'warning' : 'error'),
            'message'    => (string) ($result['message'] ?? 'Webhook test processed.'),
            'latency_ms' => $latency,
            'http_code'  => 200,
            'error_code' => empty($result['success']) ? strtoupper((string) ($result['status'] ?? 'ERROR')) : null,
            'detail'     => [
                'payload'        => $payload,
                'processor'      => $result,
                'created_payment' => false,
                'is_test'        => true,
            ],
        ];

        $this->logHealthCheck('test_webhook_payload', $response, ['processor' => $result, 'payload' => $payload]);
        $this->jsonResponse($response);
    }

    public function test_reconciliation()
    {
        $this->requireCapability('kt_sepay_run_reconcile');
        $this->requirePost();

        $settings = $this->Kt_sepay_model->get_settings();
        $started = microtime(true);
        $result = $this->kt_sepay_api->listTransactions(['transfer_type' => 'in', 'per_page' => 5]);
        $latency = $this->elapsedMs($started);

        $rows = !empty($result['success']) && is_array($result['data']) ? $result['data'] : [];
        $latestDate = '';
        foreach ($rows as $row) {
            $candidate = (string) ($row['transaction_date'] ?? '');
            if ($candidate !== '' && ($latestDate === '' || strtotime($candidate) > strtotime($latestDate))) {
                $latestDate = $candidate;
            }
        }

        $success = !empty($result['success']);
        $payload = [
            'success'      => $success,
            'status'       => $success ? 'success' : 'error',
            'message'      => $success ? 'Reconciliation API truy cập thành công.' : $this->mapApiErrorMessage($result),
            'latency_ms'   => $latency,
            'http_code'    => (int) ($result['status'] ?? 0),
            'error_code'   => $success ? null : $this->mapApiErrorCode($result),
            'environment'  => (string) ($settings['environment'] ?? 'sandbox'),
            'checked_at'   => kt_sepay_now(),
            'detail'       => [
                'records_fetched'         => count($rows),
                'latest_transaction_date' => $latestDate,
                'rate_limited'            => (int) ($result['status'] ?? 0) === 429,
            ],
        ];

        $this->logHealthCheck('test_reconciliation', $payload, $result);
        $this->jsonResponse($payload, $success ? 200 : 400);
    }

    public function tenant_payment($requestId)
    {
        $tenant = kt_saas_current_tenant();
        $request = $this->Kt_sepay_model->get_payment_request((int) $requestId);
        if (!$request || (int) ($request['tenant_id'] ?? 0) !== (int) ($tenant['id'] ?? 0)) {
            show_404();
        }

        $data['title'] = _l('kt_sepay_pay_with_sepay');
        $data['tenant'] = $tenant;
        $data['request'] = $request;
        $data['settings'] = $this->Kt_sepay_model->get_tenant_settings((int) $tenant['id']);
        $data['transactions'] = $this->Kt_sepay_model->get_transactions([
            'payment_request_id' => (int) $request['id'],
            'tenant_id'          => (int) $tenant['id'],
        ]);
        $this->load->view(KT_SEPAY_MODULE . '/tenant/billing_payment', $data);
    }

    public function tenant_portal()
    {
        $tenant = kt_saas_current_tenant();
        if (!$tenant) {
            show_404();
        }

        $requests = $this->Kt_sepay_model->get_payment_requests([
            'tenant_id' => (int) $tenant['id'],
        ]);

        $latestOpenRequest = null;
        foreach ($requests as $request) {
            if (in_array((string) ($request['status'] ?? ''), ['pending', 'partial'], true)) {
                $latestOpenRequest = $request;
                break;
            }
        }

        $data['title'] = _l('kt_sepay');
        $data['tenant'] = $tenant;
        $data['requests'] = $requests;
        $data['latest_open_request'] = $latestOpenRequest;
        $data['settings'] = $this->Kt_sepay_model->get_tenant_settings((int) $tenant['id']);
        $data['tenant_webhook_url'] = kt_sepay_webhook_url($tenant);
        $data['can_create_manual_requests'] = $this->tenantFeatureAllowed('kt_sepay.payment_requests.create', true);
        $this->load->view(KT_SEPAY_MODULE . '/tenant/dashboard', $data);
    }

    public function tenant_settings()
    {
        $tenant = kt_saas_current_tenant();
        if (!$tenant) {
            show_404();
        }

        $canEditSettings = $this->tenantFeatureAllowed('kt_sepay.settings.edit', true);
        $canRunHealthChecks = $this->tenantFeatureAllowed('kt_sepay.health.run', true);
        $canRunReconcile = $this->tenantFeatureAllowed('kt_sepay.reconcile.run', true);
        $canCreateManualRequests = $this->tenantFeatureAllowed('kt_sepay.payment_requests.create', true);

        if ($this->input->post()) {
            if (!$canEditSettings) {
                set_alert('warning', 'Gói hiện tại không cho phép chỉnh cấu hình KT SePay của tenant.');
                redirect(admin_url('kt_sepay/tenant_settings'));
            }

            $this->Kt_sepay_model->save_settings($this->input->post(), (int) $tenant['id']);
            set_alert('success', _l('settings_updated'));
            redirect(admin_url('kt_sepay/tenant_settings'));
        }

        $data['title'] = _l('kt_sepay_settings');
        $data['tenant'] = $tenant;
        $data['settings'] = $this->Kt_sepay_model->get_tenant_settings((int) $tenant['id']);
        $data['summary'] = $this->Kt_sepay_model->get_summary((int) $tenant['id']);
        $data['health_logs'] = $this->Kt_sepay_model->get_health_logs(20, (int) $tenant['id']);
        $data['health_checks_enabled'] = $canRunHealthChecks;
        $data['form_action'] = admin_url('kt_sepay/tenant_settings');
        $data['health_endpoints'] = $this->tenantHealthEndpoints();
        $data['tenant_webhook_url'] = kt_sepay_webhook_url($tenant);
        $data['can_edit_settings'] = $canEditSettings;
        $data['can_run_health_checks'] = $canRunHealthChecks;
        $data['can_run_reconcile'] = $canRunReconcile;
        $data['can_create_manual_requests'] = $canCreateManualRequests;
        $data['api_accounts'] = [];
        if ($canRunHealthChecks && !empty($data['settings']['api_token'])) {
            $api = $this->tenantApi((int) $tenant['id']);
            $accounts = $api->listBankAccounts(['per_page' => 10]);
            $data['api_accounts'] = !empty($accounts['success']) && is_array($accounts['data']) ? $accounts['data'] : [];
        }
        $data['setup_checklist'] = $this->buildTenantSetupChecklist($tenant, $data['settings'], $data['health_logs']);
        $this->load->view(KT_SEPAY_MODULE . '/admin/settings', $data);
    }

    public function tenant_transactions()
    {
        $tenant = kt_saas_current_tenant();
        if (!$tenant) {
            show_404();
        }

        $data['title'] = _l('kt_sepay_transactions');
        $data['transactions'] = $this->Kt_sepay_model->get_transactions([
            'tenant_id' => (int) $tenant['id'],
        ]);
        $this->load->view(KT_SEPAY_MODULE . '/tenant/transactions', $data);
    }

    public function tenant_payment_requests()
    {
        $tenant = kt_saas_current_tenant();
        if (!$tenant) {
            show_404();
        }

        $data['title'] = _l('kt_sepay_payment_requests');
        $data['tenant'] = $tenant;
        $data['settings'] = $this->Kt_sepay_model->get_tenant_settings((int) $tenant['id']);
        $data['requests'] = $this->Kt_sepay_model->get_payment_requests([
            'tenant_id' => (int) $tenant['id'],
        ]);
        $data['can_create_manual_requests'] = $this->tenantFeatureAllowed('kt_sepay.payment_requests.create', true);
        $this->load->view(KT_SEPAY_MODULE . '/tenant/payment_requests', $data);
    }

    public function tenant_create_payment_request()
    {
        $tenant = kt_saas_current_tenant();
        if (!$tenant) {
            show_404();
        }

        $this->requirePost();
        $this->requireTenantFeature('kt_sepay.payment_requests.create');
        $settings = $this->Kt_sepay_model->get_tenant_settings((int) $tenant['id']);
        $result = $this->createTenantManualPaymentRequest($tenant, $settings, $this->input->post());
        set_alert(!empty($result['success']) ? 'success' : 'warning', trim((string) ($result['message'] ?? '')));

        if (!empty($result['success']) && !empty($result['request_id'])) {
            redirect(admin_url('kt_sepay/tenant_payment/' . (int) $result['request_id']));
        }

        redirect(admin_url('kt_sepay/tenant_payment_requests'));
    }

    public function tenant_reconciliation()
    {
        $tenant = kt_saas_current_tenant();
        if (!$tenant) {
            show_404();
        }

        $tenantId = (int) $tenant['id'];
        $settings = $this->Kt_sepay_model->get_tenant_settings($tenantId);
        $logs = $this->Kt_sepay_model->get_reconciliation_logs(100, $tenantId);
        $data['title'] = _l('kt_sepay_reconciliation');
        $data['logs'] = $logs;
        $data['summary'] = $this->buildReconciliationSummary($logs);
        $data['last_reconcile_at'] = (string) ($settings['last_reconcile_at'] ?? '');
        $data['last_reconcile_transaction_id'] = (string) ($settings['last_reconcile_transaction_id'] ?? '');
        $data['can_run_reconcile'] = $this->tenantFeatureAllowed('kt_sepay.reconcile.run', true);
        $this->load->view(KT_SEPAY_MODULE . '/tenant/reconciliation', $data);
    }

    public function tenant_run_reconcile()
    {
        $tenant = kt_saas_current_tenant();
        if (!$tenant) {
            show_404();
        }

        $this->requirePost();
        $this->requireTenantFeature('kt_sepay.reconcile.run');
        $result = $this->performReconciliation((int) $tenant['id']);
        set_alert(!empty($result['success']) ? 'success' : 'warning', trim((string) ($result['message'] ?? 'Da hoan tat doi soat.')));
        redirect(admin_url('kt_sepay/tenant_reconciliation'));
    }

    public function tenant_test_connection()
    {
        $this->requirePost();
        $this->requireTenantFeature('kt_sepay.health.run');
        $tenantId = (int) (kt_saas_current_tenant()['id'] ?? 0);
        $settings = $this->Kt_sepay_model->get_tenant_settings($tenantId);
        $api = $this->tenantApi($tenantId);
        $started = microtime(true);
        $result = $api->listBankAccounts(['per_page' => 1]);
        $latency = $this->elapsedMs($started);
        $success = !empty($result['success']);
        $payload = [
            'success'      => $success,
            'environment'  => (string) ($settings['environment'] ?? 'sandbox'),
            'message'      => $success ? 'Ket noi SePay thanh cong.' : $this->mapApiErrorMessage($result),
            'latency_ms'   => $latency,
            'checked_at'   => kt_sepay_now(),
            'status'       => $success ? 'success' : 'error',
            'http_code'    => (int) ($result['status'] ?? 0),
            'error_code'   => $this->mapApiErrorCode($result),
            'detail'       => [
                'base_url' => kt_sepay_api_base_url($settings['environment'] ?? 'sandbox'),
                'records'  => is_array($result['data']) ? count($result['data']) : 0,
            ],
        ];
        $this->logHealthCheck('test_connection', $payload, $result, $tenantId);
        $this->jsonResponse($payload, $success ? 200 : 400);
    }

    public function tenant_test_bank_account()
    {
        $this->requirePost();
        $this->requireTenantFeature('kt_sepay.health.run');
        $tenantId = (int) (kt_saas_current_tenant()['id'] ?? 0);
        $settings = $this->Kt_sepay_model->get_tenant_settings($tenantId);
        $bankCode = trim((string) ($settings['bank_code'] ?? ''));
        $accountNumber = trim((string) ($settings['account_number'] ?? ''));
        if ($bankCode === '' || $accountNumber === '') {
            $payload = [
                'success'    => false,
                'status'     => 'error',
                'message'    => 'Thieu bank code hoac account number.',
                'latency_ms' => 0,
                'http_code'  => 0,
                'error_code' => 'CONFIG_MISSING',
                'detail'     => ['bank_code' => $bankCode !== '', 'account_number' => $accountNumber !== ''],
            ];
            $this->logHealthCheck('test_bank_account', $payload, [], $tenantId);
            $this->jsonResponse($payload, 400);
            return;
        }

        $api = $this->tenantApi($tenantId);
        $started = microtime(true);
        $result = $api->listBankAccounts(['per_page' => 20]);
        $latency = $this->elapsedMs($started);
        $success = false;
        $status = 'warning';
        $message = 'Không thể xác minh tài khoản, cần kiểm tra thủ công.';
        $detail = ['configured_bank_code' => $bankCode, 'configured_account_number' => $accountNumber];
        if (!empty($result['success']) && is_array($result['data'])) {
            foreach ($result['data'] as $account) {
                $apiAccountNumber = trim((string) ($account['account_number'] ?? ''));
                $apiBankCode = strtoupper(trim((string) ($account['bank_short_name'] ?? $account['bank_code'] ?? '')));
                if ($apiAccountNumber === $accountNumber) {
                    $success = true;
                    $status = 'success';
                    $message = 'Đã tìm thấy tài khoản trong danh sách SePay.';
                    $detail['matched_account'] = $account;
                    if ($bankCode !== '' && $apiBankCode !== '' && strtoupper($bankCode) !== $apiBankCode) {
                        $success = false;
                        $status = 'warning';
                        $message = 'Tài khoản tồn tại nhưng mã ngân hàng không khớp.';
                    }
                    break;
                }
            }
            $detail['records'] = count($result['data']);
        } else {
            $status = 'error';
            $message = $this->mapApiErrorMessage($result);
            $detail['api_status'] = (int) ($result['status'] ?? 0);
        }

        $payload = [
            'success'    => $success,
            'status'     => $status,
            'message'    => $message,
            'latency_ms' => $latency,
            'http_code'  => (int) ($result['status'] ?? 0),
            'error_code' => $success ? null : $this->mapApiErrorCode($result),
            'detail'     => $detail,
        ];
        $this->logHealthCheck('test_bank_account', $payload, $result, $tenantId);
        $this->jsonResponse($payload, $success ? 200 : ($status === 'warning' ? 200 : 400));
    }

    public function tenant_test_qr()
    {
        $this->requirePost();
        $this->requireTenantFeature('kt_sepay.health.run');
        $tenantId = (int) (kt_saas_current_tenant()['id'] ?? 0);
        $settings = $this->Kt_sepay_model->get_tenant_settings($tenantId);
        $reference = 'SEPTEST' . date('YmdHis');
        $qrUrl = kt_sepay_qr_url(
            $settings['account_number'] ?? '',
            $settings['bank_code'] ?? '',
            10000,
            $reference,
            $settings['qr_template'] ?? 'compact'
        );
        $success = !empty($settings['account_number']) && !empty($settings['bank_code']);
        $payload = [
            'success'    => $success,
            'status'     => $success ? 'success' : 'error',
            'message'    => $success ? 'QR test duoc tao thanh cong.' : 'Thieu bank code hoac account number de tao QR.',
            'latency_ms' => 0,
            'http_code'  => 200,
            'error_code' => $success ? null : 'CONFIG_MISSING',
            'detail'     => [
                'reference_code' => $reference,
                'amount'         => 10000,
                'template'       => $settings['qr_template'] ?? 'compact',
                'qr_url'         => $qrUrl,
            ],
            'qr_url'     => $qrUrl,
        ];
        $this->logHealthCheck('test_qr', $payload, [], $tenantId);
        $this->jsonResponse($payload);
    }

    public function tenant_test_webhook_url()
    {
        $this->requirePost();
        $this->requireTenantFeature('kt_sepay.health.run');
        $tenantId = (int) (kt_saas_current_tenant()['id'] ?? 0);
        $webhookUrl = kt_sepay_webhook_url();
        $csrfConfigPath = module_dir_path(KT_SEPAY_MODULE, 'config/csrf_exclude_uris.php');
        $csrfRoutes = file_exists($csrfConfigPath) ? require $csrfConfigPath : [];
        $csrfExcluded = is_array($csrfRoutes) && in_array('kt_sepay/webhook', $csrfRoutes, true);
        $controllerExists = file_exists(module_dir_path(KT_SEPAY_MODULE, 'controllers/Kt_sepay_webhook.php'));
        $https = strtolower((string) parse_url($webhookUrl, PHP_URL_SCHEME)) === 'https';
        $localWarning = kt_sepay_is_local_url($webhookUrl);
        $status = ($https && $csrfExcluded && $controllerExists) ? 'success' : 'warning';
        $payload = [
            'success'    => $status === 'success',
            'status'     => $status,
            'message'    => $status === 'success' ? 'Địa chỉ nhận thông báo hợp lệ cho triển khai.' : 'Địa chỉ nhận thông báo cần kiểm tra thêm trước khi đưa vào môi trường thật.',
            'latency_ms' => 0,
            'http_code'  => 200,
            'error_code' => null,
            'detail'     => [
                'webhook_url'       => $webhookUrl,
                'is_https'          => $https,
                'csrf_excluded'     => $csrfExcluded,
                'public_controller' => $controllerExists,
                'local_environment' => $localWarning,
            ],
        ];
        $this->logHealthCheck('test_webhook_url', $payload, [], $tenantId);
        $this->jsonResponse($payload);
    }

    public function tenant_test_webhook_payload()
    {
        $this->requirePost();
        $this->requireTenantFeature('kt_sepay.health.run');
        $tenantId = (int) (kt_saas_current_tenant()['id'] ?? 0);
        $settings = $this->Kt_sepay_model->get_tenant_settings($tenantId);
        $timestamp = date('YmdHis');
        $reference = 'SEPTESTT' . $tenantId . $timestamp;
        $payload = [
            'id'              => 'TEST-' . $timestamp,
            'gateway'         => 'test',
            'transactionDate' => kt_sepay_now(),
            'accountNumber'   => trim((string) ($settings['account_number'] ?? '')),
            'content'         => $reference,
            'transferType'    => 'in',
            'transferAmount'  => 10000,
            'referenceCode'   => $reference,
            'is_test'         => 1,
        ];
        $started = microtime(true);
        $result = $this->kt_sepay_processor->processIncomingTransaction($payload, ['source' => 'tenant_test_webhook_payload']);
        $latency = $this->elapsedMs($started);
        $response = [
            'success'    => !empty($result['success']) || in_array((string) ($result['status'] ?? ''), ['unmatched', 'duplicate', 'ignored'], true),
            'status'     => !empty($result['success']) ? 'success' : (($result['status'] ?? '') === 'unmatched' ? 'warning' : 'error'),
            'message'    => (string) ($result['message'] ?? 'Đã xử lý bài kiểm tra thông báo thanh toán.'),
            'latency_ms' => $latency,
            'http_code'  => 200,
            'error_code' => empty($result['success']) ? strtoupper((string) ($result['status'] ?? 'ERROR')) : null,
            'detail'     => [
                'payload'   => $payload,
                'processor' => $result,
                'is_test'   => true,
            ],
        ];
        $this->logHealthCheck('test_webhook_payload', $response, ['processor' => $result, 'payload' => $payload], $tenantId);
        $this->jsonResponse($response);
    }

    public function tenant_test_reconciliation()
    {
        $this->requirePost();
        $this->requireTenantFeature('kt_sepay.health.run');
        $tenantId = (int) (kt_saas_current_tenant()['id'] ?? 0);
        $settings = $this->Kt_sepay_model->get_tenant_settings($tenantId);
        $api = $this->tenantApi($tenantId);
        $started = microtime(true);
        $result = $api->listTransactions(['transfer_type' => 'in', 'per_page' => 5]);
        $latency = $this->elapsedMs($started);
        $rows = !empty($result['success']) && is_array($result['data']) ? $result['data'] : [];
        $latestDate = '';
        foreach ($rows as $row) {
            $candidate = (string) ($row['transaction_date'] ?? '');
            if ($candidate !== '' && ($latestDate === '' || strtotime($candidate) > strtotime($latestDate))) {
                $latestDate = $candidate;
            }
        }
        $success = !empty($result['success']);
        $payload = [
            'success'      => $success,
            'status'       => $success ? 'success' : 'error',
            'message'      => $success ? 'API đối soát truy cập thành công.' : $this->mapApiErrorMessage($result),
            'latency_ms'   => $latency,
            'http_code'    => (int) ($result['status'] ?? 0),
            'error_code'   => $success ? null : $this->mapApiErrorCode($result),
            'environment'  => (string) ($settings['environment'] ?? 'sandbox'),
            'checked_at'   => kt_sepay_now(),
            'detail'       => [
                'records_fetched'         => count($rows),
                'latest_transaction_date' => $latestDate,
                'rate_limited'            => (int) ($result['status'] ?? 0) === 429,
            ],
        ];
        $this->logHealthCheck('test_reconciliation', $payload, $result, $tenantId);
        $this->jsonResponse($payload, $success ? 200 : 400);
    }

    private function performReconciliation($tenantId = null)
    {
        $tenantId = $tenantId ? (int) $tenantId : null;
        $settings = $tenantId === null
            ? $this->Kt_sepay_model->get_settings(null, false)
            : $this->Kt_sepay_model->get_tenant_settings($tenantId);
        $sinceId = (string) ($settings['last_reconcile_transaction_id'] ?? '');
        $query = ['transfer_type' => 'in', 'per_page' => 100];
        if ($sinceId !== '') {
            $query['since_id'] = $sinceId;
        }

        $api = $tenantId === null ? $this->kt_sepay_api : $this->tenantApi($tenantId);
        $apiResult = $api->listTransactions($query);
        if (empty($apiResult['success'])) {
            $this->Kt_sepay_model->save_settings([
                'environment'                => $settings['environment'] ?? 'sandbox',
                'bank_code'                  => $settings['bank_code'] ?? '',
                'account_number'             => $settings['account_number'] ?? '',
                'account_name'               => $settings['account_name'] ?? '',
                'qr_template'                => $settings['qr_template'] ?? 'compact',
                'reference_prefix_invoice'   => $settings['reference_prefix_invoice'] ?? 'KTINV',
                'reference_prefix_subscription' => $settings['reference_prefix_subscription'] ?? 'KTSAAS',
                'reference_prefix_manual'    => $settings['reference_prefix_manual'] ?? 'KTPAY',
                'auto_reconcile_enabled'     => !empty($settings['auto_reconcile_enabled']) ? 1 : 0,
                'reconcile_interval_minutes' => $settings['reconcile_interval_minutes'] ?? 15,
                'payment_request_expiry_minutes' => $settings['payment_request_expiry_minutes'] ?? 60,
                'last_reconcile_transaction_id' => $sinceId !== '' ? $sinceId : null,
                'last_reconcile_at'          => kt_sepay_now(),
                'allow_partial_payment'      => !empty($settings['allow_partial_payment']) ? 1 : 0,
                'is_active'                  => !empty($settings['is_active']) ? 1 : 0,
            ], $tenantId);
            $logId = $this->Kt_sepay_model->create_reconciliation_log([
                'tenant_id'     => $tenantId,
                'run_id'        => app_generate_hash(),
                'environment'   => (string) ($settings['environment'] ?? 'sandbox'),
                'from_time'     => null,
                'to_time'       => kt_sepay_now(),
                'total_fetched' => 0,
                'total_errors'  => 1,
                'metadata_json' => kt_sepay_json_encode($apiResult),
            ]);

            return ['success' => false, 'message' => 'Đối soát SePay thất bại. Mã log: #' . $logId . '.'];
        }

        $rows = is_array($apiResult['data']) ? $apiResult['data'] : [];
        $matched = 0;
        $processed = 0;
        $errors = 0;
        $lastId = $sinceId;
        foreach ($rows as $row) {
            $payload = [
                'id'              => $row['id'] ?? '',
                'gateway'         => $row['bank_brand_name'] ?? '',
                'transactionDate' => $row['transaction_date'] ?? '',
                'accountNumber'   => $row['account_number'] ?? '',
                'code'            => $row['code'] ?? '',
                'content'         => $row['transaction_content'] ?? '',
                'transferType'    => ((int) ($row['amount_in'] ?? 0)) > 0 ? 'in' : 'out',
                'transferAmount'  => (int) (($row['amount_in'] ?? 0) > 0 ? $row['amount_in'] : ($row['amount_out'] ?? 0)),
                'referenceCode'   => $row['reference_number'] ?? '',
            ];

            $result = $this->kt_sepay_processor->processIncomingTransaction($payload, ['source' => 'reconcile', 'reprocess_existing' => true]);
            $resultStatus = (string) ($result['status'] ?? '');
            if (in_array($resultStatus, ['matched', 'processed', 'duplicate'], true) || (!empty($result['success']) && !in_array($resultStatus, ['unmatched', 'ignored'], true))) {
                $matched++;
            }
            if (!empty($result['success']) && $resultStatus !== 'unmatched') {
                $processed++;
            } elseif ($resultStatus !== 'duplicate' && $resultStatus !== 'unmatched' && $resultStatus !== 'ignored') {
                $errors++;
            }

            if (!empty($row['id'])) {
                $lastId = (string) $row['id'];
            }
        }

        $localReprocess = $this->kt_sepay_processor->reprocessUnmatchedTransactions($tenantId, 100);
        $matched += (int) ($localReprocess['matched'] ?? 0);
        $processed += (int) ($localReprocess['processed'] ?? 0);
        $errors += (int) ($localReprocess['errors'] ?? 0);

        $this->Kt_sepay_model->save_settings([
            'environment'                => $settings['environment'] ?? 'sandbox',
            'bank_code'                  => $settings['bank_code'] ?? '',
            'account_number'             => $settings['account_number'] ?? '',
            'account_name'               => $settings['account_name'] ?? '',
            'qr_template'                => $settings['qr_template'] ?? 'compact',
            'reference_prefix_invoice'   => $settings['reference_prefix_invoice'] ?? 'KTINV',
            'reference_prefix_subscription' => $settings['reference_prefix_subscription'] ?? 'KTSAAS',
            'reference_prefix_manual'    => $settings['reference_prefix_manual'] ?? 'KTPAY',
            'auto_reconcile_enabled'     => !empty($settings['auto_reconcile_enabled']) ? 1 : 0,
            'reconcile_interval_minutes' => $settings['reconcile_interval_minutes'] ?? 15,
            'payment_request_expiry_minutes' => $settings['payment_request_expiry_minutes'] ?? 60,
            'last_reconcile_transaction_id' => $lastId !== '' ? $lastId : $sinceId,
            'last_reconcile_at'          => kt_sepay_now(),
            'allow_partial_payment'      => !empty($settings['allow_partial_payment']) ? 1 : 0,
            'is_active'                  => !empty($settings['is_active']) ? 1 : 0,
        ], $tenantId);

        $this->Kt_sepay_model->create_reconciliation_log([
            'tenant_id'       => $tenantId,
            'run_id'          => app_generate_hash(),
            'environment'     => (string) ($settings['environment'] ?? 'sandbox'),
            'from_time'       => null,
            'to_time'         => kt_sepay_now(),
            'total_fetched'   => count($rows),
            'total_matched'   => $matched,
            'total_processed' => $processed,
            'total_errors'    => $errors,
            'metadata_json'   => kt_sepay_json_encode(['last_id' => $lastId, 'local_reprocess' => $localReprocess]),
        ]);

        return [
            'success' => true,
            'message' => 'Đối soát đã lấy ' . count($rows) . ' giao dịch, xử lý thành công ' . $processed . ', lỗi ' . $errors . '.',
        ];
    }

    private function requireCapability($capability)
    {
        if (!kt_sepay_staff_can($capability)) {
            access_denied(KT_SEPAY_MODULE);
        }
    }

    private function requirePost()
    {
        if (strtolower((string) $this->input->method()) !== 'post') {
            show_404();
        }
    }

    private function requireLandlordContext()
    {
        kt_sepay_landlord_only('Khu vực này chỉ khả dụng trên hệ thống landlord.');
    }

    private function requireTenantContext()
    {
        if (!function_exists('kt_saas_is_tenant_runtime') || !kt_saas_is_tenant_runtime()) {
            show_404();
        }
    }

    private function requireTenantAdmin()
    {
        if (!is_admin()) {
            access_denied(KT_SEPAY_MODULE);
        }
    }

    private function requireTenantFeature($featureKey, $default = true)
    {
        if (!$this->tenantFeatureAllowed($featureKey, $default)) {
            access_denied(KT_SEPAY_MODULE);
        }
    }

    private function tenantFeatureAllowed($featureKey, $default = true)
    {
        if (!function_exists('kt_saas_feature_allowed')) {
            return $default;
        }

        return kt_saas_feature_allowed(KT_SEPAY_MODULE, $featureKey, $default);
    }

    private function isTenantMethod()
    {
        return in_array($this->router->fetch_method(), [
            'tenant_payment',
            'tenant_portal',
            'tenant_settings',
            'tenant_create_payment_request',
            'tenant_transactions',
            'tenant_payment_requests',
            'tenant_reconciliation',
            'tenant_run_reconcile',
            'tenant_test_connection',
            'tenant_test_bank_account',
            'tenant_test_qr',
            'tenant_test_webhook_url',
            'tenant_test_webhook_payload',
            'tenant_test_reconciliation',
        ], true);
    }

    private function elapsedMs($started)
    {
        return (int) round((microtime(true) - (float) $started) * 1000);
    }

    private function buildReconciliationSummary(array $logs)
    {
        $summary = [
            'total_runs'      => count($logs),
            'total_fetched'   => 0,
            'total_matched'   => 0,
            'total_processed' => 0,
            'total_errors'    => 0,
            'latest_log'      => null,
        ];

        foreach ($logs as $index => $log) {
            $summary['total_fetched'] += (int) ($log['total_fetched'] ?? 0);
            $summary['total_matched'] += (int) ($log['total_matched'] ?? 0);
            $summary['total_processed'] += (int) ($log['total_processed'] ?? 0);
            $summary['total_errors'] += (int) ($log['total_errors'] ?? 0);
            if ($index === 0) {
                $summary['latest_log'] = $log;
            }
        }

        return $summary;
    }

    private function findLatestHealthLog(array $logs, array $types)
    {
        foreach ($logs as $log) {
            if (in_array((string) ($log['test_type'] ?? ''), $types, true)) {
                return $log;
            }
        }

        return null;
    }

    private function mapApiErrorCode(array $result)
    {
        $status = (int) ($result['status'] ?? 0);
        if ($status === 401) {
            return 'API_401';
        }
        if ($status === 429) {
            return 'API_429';
        }
        if ($status >= 500) {
            return 'API_5XX';
        }

        return $result['error_code'] ?? ($status > 0 ? 'API_' . $status : 'API_ERROR');
    }

    private function mapApiErrorMessage(array $result)
    {
        $status = (int) ($result['status'] ?? 0);
        if ($status === 401) {
            return 'API token không hợp lệ hoặc đã hết hạn.';
        }
        if ($status === 429) {
            return 'SePay API đang rate limit, cần thử lại sau.';
        }

        return trim((string) ($result['message'] ?? 'Không thể kết nối SePay API.'));
    }

    private function logHealthCheck($testType, array $payload, array $rawResponse = [], $tenantId = null)
    {
        $settings = $tenantId === null
            ? $this->Kt_sepay_model->get_settings(null, false)
            : $this->Kt_sepay_model->get_tenant_settings((int) $tenantId);
        $this->Kt_sepay_model->create_health_log([
            'tenant_id'    => $tenantId ? (int) $tenantId : null,
            'test_type'    => (string) $testType,
            'environment'  => (string) ($settings['environment'] ?? 'sandbox'),
            'status'       => (string) ($payload['status'] ?? ($payload['success'] ? 'success' : 'error')),
            'http_code'    => (int) ($payload['http_code'] ?? 0),
            'latency_ms'   => (int) ($payload['latency_ms'] ?? 0),
            'message'      => trim((string) ($payload['message'] ?? '')),
            'error_code'   => $payload['error_code'] ?? null,
            'raw_response' => !empty($rawResponse) ? kt_sepay_json_encode($rawResponse) : kt_sepay_json_encode($payload['detail'] ?? []),
        ]);
    }

    private function jsonResponse(array $payload, $statusCode = 200)
    {
        $payload['csrf_hash'] = $this->security->get_csrf_hash();
        set_status_header((int) $statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function tenantHealthEndpoints(): array
    {
        return [
            'test_connection'      => admin_url('kt_sepay/tenant_test_connection'),
            'test_bank_account'    => admin_url('kt_sepay/tenant_test_bank_account'),
            'test_qr'              => admin_url('kt_sepay/tenant_test_qr'),
            'test_webhook_url'     => admin_url('kt_sepay/tenant_test_webhook_url'),
            'test_webhook_payload' => admin_url('kt_sepay/tenant_test_webhook_payload'),
            'test_reconciliation'  => admin_url('kt_sepay/tenant_test_reconciliation'),
        ];
    }

    private function tenantApi($tenantId)
    {
        return new Kt_sepay_api([
            'tenant_id'       => (int) $tenantId,
            'fallback_global' => false,
        ]);
    }

    private function buildTenantSetupChecklist(array $tenant, array $settings, array $healthLogs): array
    {
        $hasSuccessHealthLog = false;
        foreach ($healthLogs as $log) {
            if ((string) ($log['status'] ?? '') === 'success') {
                $hasSuccessHealthLog = true;
                break;
            }
        }

        return [
            [
                'label' => _l('kt_sepay_setup_step_activation'),
                'done'  => !empty($settings['is_active']),
                'help'  => _l('kt_sepay_setup_step_activation_help'),
            ],
            [
                'label' => _l('kt_sepay_setup_step_api_token'),
                'done'  => !empty($settings['api_token']),
                'help'  => _l('kt_sepay_setup_step_api_token_help'),
            ],
            [
                'label' => _l('kt_sepay_setup_step_bank_account'),
                'done'  => !empty($settings['bank_code']) && !empty($settings['account_number']) && !empty($settings['account_name']),
                'help'  => _l('kt_sepay_setup_step_bank_account_help'),
            ],
            [
                'label' => _l('kt_sepay_setup_step_webhook_secret'),
                'done'  => !empty($settings['webhook_secret']),
                'help'  => _l('kt_sepay_setup_step_webhook_secret_help'),
            ],
            [
                'label' => _l('kt_sepay_setup_step_health_check'),
                'done'  => $hasSuccessHealthLog,
                'help'  => _l('kt_sepay_setup_step_health_check_help'),
            ],
        ];
    }

    private function createTenantManualPaymentRequest(array $tenant, array $settings, array $data): array
    {
        if (empty($settings['is_active']) || empty($settings['bank_code']) || empty($settings['account_number'])) {
            return [
                'success' => false,
                'message' => _l('kt_sepay_manual_request_settings_missing'),
            ];
        }

        $amount = $this->normalizeAmountInput($data['amount'] ?? 0);
        if ($amount <= 0) {
            return [
                'success' => false,
                'message' => _l('kt_sepay_manual_request_amount_invalid'),
            ];
        }

        $tenantId = (int) ($tenant['id'] ?? 0);
        $prefix = trim((string) ($settings['reference_prefix_manual'] ?? 'KTPAY'));
        $reference = strtoupper($prefix) . 'T' . $tenantId . date('YmdHis');
        $accessToken = bin2hex(random_bytes(24));
        $expiryMinutes = max((int) ($data['expiry_minutes'] ?? ($settings['payment_request_expiry_minutes'] ?? 60)), 5);
        $description = trim((string) ($data['description'] ?? ''));
        if ($description === '') {
            $description = 'Manual payment request #' . $reference;
        }

        $requestId = $this->Kt_sepay_model->create_payment_request([
            'context_type'   => 'manual',
            'context_id'     => 0,
            'tenant_id'      => $tenantId,
            'amount'         => $amount,
            'currency'       => trim((string) ($data['currency'] ?? 'VND')) ?: 'VND',
            'reference_code' => $reference,
            'access_token'   => $accessToken,
            'description'    => $description,
            'qr_url'         => kt_sepay_qr_url(
                $settings['account_number'],
                $settings['bank_code'],
                $amount,
                $reference,
                $settings['qr_template'] ?? 'compact'
            ),
            'status'         => 'pending',
            'metadata_json'  => kt_sepay_json_encode([
                'source'      => 'tenant_manual',
                'tenant_code' => $tenant['tenant_code'] ?? null,
            ]),
            'expires_at'     => date('Y-m-d H:i:s', time() + ($expiryMinutes * 60)),
            'created_by'     => get_staff_user_id() ?: null,
        ]);

        if ($requestId <= 0) {
            return [
                'success' => false,
                'message' => _l('kt_sepay_manual_request_create_failed'),
            ];
        }

        return [
            'success'    => true,
            'message'    => _l('kt_sepay_manual_request_created'),
            'request_id' => $requestId,
        ];
    }

    private function normalizeAmountInput($value): float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0.0;
        }

        $normalized = preg_replace('/[^0-9.]/', '', str_replace(',', '', $value));
        if ($normalized === '' || !is_numeric($normalized)) {
            return 0.0;
        }

        return round((float) $normalized, 2);
    }

    private function normalizeBulkIds($ids)
    {
        if (!is_array($ids)) {
            return [];
        }

        $normalized = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $normalized[$id] = $id;
            }
        }

        return array_values($normalized);
    }

    private function setBulkAlert($processed, $failed, $prefix)
    {
        if ($processed > 0 && $failed === 0) {
            set_alert('success', trim($prefix . ' Thành công: ' . (int) $processed . '.'));
            return;
        }

        set_alert('warning', trim($prefix . ' Thành công: ' . (int) $processed . ', thất bại: ' . (int) $failed . '.'));
    }

    private function streamCsv($filename, array $headers, array $rows)
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fputcsv($output, $headers);
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }
}
