<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_sepay_processor
{
    protected $CI;
    protected $settings;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->helper(KT_SEPAY_MODULE . '/kt_sepay');
        $this->CI->load->model(KT_SEPAY_MODULE . '/Kt_sepay_model');
        $this->CI->load->library(KT_SEPAY_MODULE . '/Kt_sepay_matcher');
        $this->CI->load->library(KT_SEPAY_MODULE . '/Kt_sepay_gateway');
        $this->CI->load->model('payments_model');
        $this->settings = $this->CI->Kt_sepay_model->get_settings();
    }

    public function processIncomingTransaction(array $payload, array $options = [])
    {
        $normalized = $this->normalizePayload($payload);
        if (empty($normalized['sepay_transaction_id'])) {
            return ['success' => false, 'status' => 'error', 'message' => 'Thiếu mã giao dịch SePay.'];
        }

        if ($normalized['transfer_type'] !== 'in') {
            return ['success' => true, 'status' => 'ignored', 'message' => 'Bỏ qua giao dịch chuyển ra.'];
        }

        if ($normalized['transfer_amount'] <= 0) {
            return ['success' => false, 'status' => 'error', 'message' => 'Số tiền giao dịch không hợp lệ.'];
        }

        $insert = $this->CI->Kt_sepay_model->insert_transaction_if_new([
            'sepay_transaction_id' => $normalized['sepay_transaction_id'],
            'gateway'              => $normalized['gateway'],
            'transaction_date'     => $normalized['transaction_date'],
            'account_number'       => $normalized['account_number'],
            'code'                 => $normalized['code'],
            'content'              => $normalized['content'],
            'transfer_type'        => $normalized['transfer_type'],
            'transfer_amount'      => $normalized['transfer_amount'],
            'reference_code'       => $normalized['reference_code'],
            'tenant_id'            => !empty($options['tenant_id']) ? (int) $options['tenant_id'] : null,
            'status'               => 'received',
            'raw_payload'          => $payload,
        ]);

        if (empty($insert['created'])) {
            $existing = isset($insert['existing']) && is_array($insert['existing']) ? $insert['existing'] : [];
            if (!empty($options['reprocess_existing']) && in_array((string) ($existing['status'] ?? ''), ['unmatched', 'error'], true)) {
                $payload = $this->mergeStoredPayload($existing, $payload);
                $normalized = $this->normalizePayload($payload);
                return $this->processTransactionRecord((int) ($insert['id'] ?? 0), $payload, $normalized, $options + [
                    'reprocessed' => true,
                    'tenant_id' => !empty($existing['tenant_id']) ? (int) $existing['tenant_id'] : null,
                ]);
            }

            return ['success' => false, 'status' => 'replay', 'message' => 'Webhook transaction replay was rejected.', 'transaction_id' => $insert['id'] ?? 0];
        }

        return $this->processTransactionRecord((int) $insert['id'], $payload, $normalized, $options);
    }

    public function reprocessTransaction($transactionId, array $payloadOverrides = [], array $options = [])
    {
        $transaction = $this->CI->Kt_sepay_model->get_transaction((int) $transactionId);
        if (!$transaction) {
            return ['success' => false, 'status' => 'error', 'message' => 'Không tìm thấy giao dịch SePay.', 'transaction_id' => (int) $transactionId];
        }

        if (!in_array((string) ($transaction['status'] ?? ''), ['unmatched', 'error'], true)) {
            return ['success' => false, 'status' => 'replay', 'message' => 'Webhook transaction replay was rejected.', 'transaction_id' => (int) $transactionId];
        }

        $payload = $this->mergeStoredPayload($transaction, $payloadOverrides);
        $normalized = $this->normalizePayload($payload);

        return $this->processTransactionRecord((int) $transactionId, $payload, $normalized, $options + [
            'reprocessed' => true,
            'tenant_id' => !empty($transaction['tenant_id']) ? (int) $transaction['tenant_id'] : null,
        ]);
    }

    public function reprocessUnmatchedTransactions($tenantId = null, $limit = 100)
    {
        $filters = ['status' => 'unmatched'];
        if ($tenantId !== null) {
            $filters['tenant_id'] = (int) $tenantId;
        }

        $rows = array_slice($this->CI->Kt_sepay_model->get_transactions($filters), 0, max((int) $limit, 1));
        $summary = [
            'checked' => count($rows),
            'processed' => 0,
            'matched' => 0,
            'unmatched' => 0,
            'duplicate' => 0,
            'errors' => 0,
            'results' => [],
        ];

        foreach ($rows as $row) {
            $result = $this->reprocessTransaction((int) $row['id'], [], ['source' => 'local_reconcile']);
            $status = (string) ($result['status'] ?? '');
            if ($status === 'processed') {
                $summary['processed']++;
            }
            if (in_array($status, ['matched', 'processed'], true)) {
                $summary['matched']++;
            } elseif ($status === 'unmatched') {
                $summary['unmatched']++;
            } elseif ($status === 'duplicate') {
                $summary['duplicate']++;
            } elseif (!empty($result['success'])) {
                $summary['matched']++;
            } else {
                $summary['errors']++;
            }

            $summary['results'][] = [
                'transaction_id' => (int) $row['id'],
                'status' => $status,
                'message' => (string) ($result['message'] ?? ''),
            ];
        }

        return $summary;
    }

    private function processTransactionRecord($transactionId, array $payload, array $normalized, array $options = [])
    {
        $transactionId = (int) $transactionId;
        $tenantId = !empty($options['tenant_id']) ? (int) $options['tenant_id'] : null;
        $match = $this->CI->kt_sepay_matcher->matchPaymentRequest($payload, $tenantId);
        if (empty($match['matched']) || empty($match['payment_request'])) {
            $this->CI->Kt_sepay_model->update_transaction($transactionId, [
                'status' => 'unmatched',
                'processed_at' => kt_sepay_now(),
            ]);

            if (!function_exists('kt_saas_send_email_event')) {
                require_once module_dir_path('kt_saas', 'helpers/kt_saas_helper.php');
            }
            if (function_exists('kt_saas_send_email_event')) {
                $requestReference = trim((string) ($normalized['reference_code'] ?? $normalized['code'] ?? $normalized['sepay_transaction_id'] ?? ''));
                $dedupeKey = 'unmatched_payment_alert|' . $transactionId . '|' . date('Y-m-d');
                kt_saas_send_email_event('unmatched_payment_alert', [
                    'tenant_id' => !empty($options['tenant_id']) ? (int) $options['tenant_id'] : null,
                    'recipient_email' => function_exists('kt_saas_landlord_ops_email') ? kt_saas_landlord_ops_email() : '',
                    'owner_name' => 'Operations',
                    'tenant_name' => (string) ($options['source'] ?? 'SePay'),
                    'payment_reference' => $requestReference,
                    'payment_amount' => (float) $normalized['transfer_amount'],
                    'currency' => 'VND',
                    'transaction_code' => (string) ($normalized['code'] ?? ''),
                    'provider_name' => 'SePay',
                    'webhook_url' => site_url('admin/kt_sepay/webhook'),
                    'job_id' => (string) $transactionId,
                    'related_type' => 'payment',
                    'related_id' => (string) $transactionId,
                    'dedupe_key' => $dedupeKey,
                ], [
                    'event_key' => 'unmatched_payment_alert',
                    'dedupe_key' => $dedupeKey,
                ]);
            }

            return ['success' => false, 'status' => 'unmatched', 'message' => 'No matching payment request was found for this webhook transaction.', 'transaction_id' => $transactionId];
        }

        $request = $match['payment_request'];
        if (in_array((string) ($request['status'] ?? ''), ['paid', 'cancelled', 'expired'], true)) {
            $this->CI->Kt_sepay_model->update_transaction($transactionId, [
                'matched_reference'  => $match['reference_code'],
                'matched_type'       => (string) ($request['context_type'] ?? ''),
                'matched_id'         => (int) ($request['context_id'] ?? 0),
                'payment_request_id' => (int) ($request['id'] ?? 0),
                'tenant_id'          => !empty($request['tenant_id']) ? (int) $request['tenant_id'] : null,
                'status'             => 'duplicate',
                'processed_at'       => kt_sepay_now(),
            ]);

            return ['success' => false, 'status' => 'replay', 'message' => 'Payment request replay was rejected.', 'transaction_id' => $transactionId];
        }

        $this->CI->Kt_sepay_model->update_transaction($transactionId, [
            'matched_reference'  => $match['reference_code'],
            'matched_type'       => (string) ($request['context_type'] ?? ''),
            'matched_id'         => (int) ($request['context_id'] ?? 0),
            'payment_request_id' => (int) ($request['id'] ?? 0),
            'tenant_id'          => !empty($request['tenant_id']) ? (int) $request['tenant_id'] : null,
            'status'             => 'matched',
        ]);

        $requestSettings = $this->CI->Kt_sepay_model->get_request_settings($request);
        if (!$this->matchDestinationAccount($normalized, $requestSettings)) {
            $this->CI->Kt_sepay_model->update_transaction($transactionId, [
                'status'       => 'error',
                'processed_at' => kt_sepay_now(),
            ]);

            return ['success' => false, 'status' => 'error', 'message' => 'Thông tin tài khoản nhận tiền không khớp cấu hình.', 'transaction_id' => $transactionId];
        }

        $amountResult = $this->CI->kt_sepay_matcher->evaluateAmount($request, $payload, !empty($requestSettings['allow_partial_payment']));
        if ((string) $request['context_type'] === 'kt_saas_subscription' && $amountResult['status'] === 'partial') {
            $this->CI->Kt_sepay_model->update_payment_request((int) $request['id'], [
                'status'      => 'partial',
                'paid_amount' => (float) $amountResult['received'],
            ]);
            $this->CI->Kt_sepay_model->update_transaction($transactionId, [
                'status'       => 'error',
                'processed_at' => kt_sepay_now(),
            ]);

            return ['success' => false, 'status' => 'partial', 'message' => 'Thanh toán gói SaaS phải đúng số tiền yêu cầu.', 'transaction_id' => $transactionId];
        }

        if ($amountResult['status'] === 'short' || $amountResult['status'] === 'invalid') {
            $this->CI->Kt_sepay_model->update_payment_request((int) $request['id'], [
                'status'      => 'partial',
                'paid_amount' => (float) $amountResult['received'],
            ]);
            $this->CI->Kt_sepay_model->update_transaction($transactionId, [
                'status'       => 'error',
                'processed_at' => kt_sepay_now(),
            ]);

            return ['success' => false, 'status' => 'partial', 'message' => 'Số tiền chuyển vào thấp hơn số tiền cần thanh toán.', 'transaction_id' => $transactionId];
        }

        if ($amountResult['status'] === 'overpaid') {
            $this->CI->Kt_sepay_model->update_transaction($transactionId, [
                'status'       => 'error',
                'processed_at' => kt_sepay_now(),
            ]);

            return ['success' => false, 'status' => 'overpaid', 'message' => 'Số tiền chuyển vào cao hơn số tiền cần thanh toán.', 'transaction_id' => $transactionId];
        }

        $processResult = $this->processMatchedRequest($request, $payload, $normalized, $amountResult);
        $finalStatus = !empty($processResult['success']) ? 'processed' : 'error';
        $this->CI->Kt_sepay_model->update_transaction($transactionId, [
            'status'       => $finalStatus,
            'processed_at' => kt_sepay_now(),
        ]);

        return $processResult + ['transaction_id' => $transactionId, 'status' => $finalStatus];
    }

    private function processMatchedRequest(array $request, array $payload, array $normalized, array $amountResult)
    {
        $amount = (float) $amountResult['received'];

        if ((string) $request['context_type'] === 'perfex_invoice') {
            $result = $this->CI->kt_sepay_gateway->recordPerfexInvoicePayment($request, $payload, $amount);
            if (!empty($result['success'])) {
                $requestStatus = $amountResult['status'] === 'partial' ? 'partial' : 'paid';
                $this->CI->Kt_sepay_model->update_payment_request((int) $request['id'], [
                    'status'       => $requestStatus,
                    'paid_amount'  => $amount,
                    'processed_at' => kt_sepay_now(),
                ]);
            }

            return $result;
        }

        if ((string) $request['context_type'] === 'kt_saas_subscription') {
            require_once module_dir_path('kt_saas', 'services/BillingEngineService.php');
            $this->CI->load->model('kt_saas/Kt_saas_model');
            $invoice = $this->CI->Kt_sepay_model->get_kt_saas_invoice((int) ($request['invoice_id'] ?? 0));
            if (!$invoice) {
                return ['success' => false, 'message' => 'Không tìm thấy hóa đơn SaaS cần thanh toán.'];
            }

            $service = new BillingEngineService();
            $result = $service->markInvoicePaid($invoice, [
                'gateway'           => 'sepay',
                'payment_reference' => (string) $request['reference_code'],
                'amount'            => $amount,
                'currency'          => (string) ($request['currency'] ?? 'VND'),
                'status'            => 'paid',
                'paid_at'           => $normalized['transaction_date'] ?: kt_sepay_now(),
                'webhook_payload'   => $payload,
            ]);

            if (!empty($result['success'])) {
                $this->CI->Kt_sepay_model->update_payment_request((int) $request['id'], [
                    'status'       => 'paid',
                    'paid_amount'  => $amount,
                    'processed_at' => kt_sepay_now(),
                ]);
                $this->queueProvisioningAfterPublicSignupPayment($request, $invoice);
            }

            return [
                'success' => !empty($result['success']),
                'message' => !empty($result['success']) ? 'Đã ghi nhận thanh toán hóa đơn SaaS.' : ($result['message'] ?? 'Không thể xử lý thanh toán hóa đơn SaaS.'),
                'result'  => $result,
            ];
        }

        if ((string) $request['context_type'] === 'kt_matbao_invoice_order') {
            $orderId = (int) ($request['context_id'] ?? 0);
            if (!empty($request['tenant_id']) || $orderId <= 0) {
                return ['success' => false, 'message' => 'Yêu cầu thanh toán dịch vụ bổ sung không hợp lệ.'];
            }

            $this->CI->load->model('kt_matbao_invoice/Kt_matbao_invoice_model');
            $order = $this->CI->Kt_matbao_invoice_model->get_order($orderId);
            if (!$order) {
                return ['success' => false, 'message' => 'Không tìm thấy đơn dịch vụ bổ sung của doanh nghiệp.'];
            }
            if (abs((float) ($order['grand_total'] ?? 0) - $amount) > 0.01) {
                return ['success' => false, 'message' => 'Số tiền thanh toán không khớp tổng giá trị đơn hàng.'];
            }

            $result = $this->CI->Kt_matbao_invoice_model->mark_order_paid_and_activate_addons($orderId, 'sepay', [
                'payment_reference' => (string) ($request['reference_code'] ?? ''),
                'amount' => $amount,
                'paid_at' => $normalized['transaction_date'] ?: kt_sepay_now(),
                'provider_transaction_id' => (string) ($normalized['sepay_transaction_id'] ?? ''),
                'source' => 'landlord_sepay_webhook',
            ]);
            if (!empty($result['success'])) {
                $this->CI->Kt_sepay_model->update_payment_request((int) $request['id'], [
                    'status'       => 'paid',
                    'paid_amount'  => $amount,
                    'processed_at' => kt_sepay_now(),
                ]);
            }

            return [
                'success' => !empty($result['success']),
                'message' => !empty($result['success'])
                    ? 'Đã ghi nhận thanh toán và kích hoạt dịch vụ bổ sung.'
                    : ($result['message'] ?? 'Không thể kích hoạt dịch vụ bổ sung.'),
                'result' => $result,
            ];
        }

        return ['success' => false, 'message' => 'Không hỗ trợ loại yêu cầu thanh toán này.'];
    }

    private function normalizePayload(array $payload)
    {
        $transactionDate = trim((string) ($payload['transactionDate'] ?? $payload['transaction_date'] ?? ''));
        if ($transactionDate !== '') {
            $timestamp = strtotime($transactionDate);
            $transactionDate = $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
        } else {
            $transactionDate = null;
        }

        return [
            'sepay_transaction_id' => trim((string) ($payload['id'] ?? $payload['transaction_id'] ?? '')),
            'gateway'              => trim((string) ($payload['gateway'] ?? '')),
            'transaction_date'     => $transactionDate,
            'account_number'       => trim((string) ($payload['accountNumber'] ?? $payload['account_number'] ?? '')),
            'code'                 => trim((string) ($payload['code'] ?? '')),
            'content'              => trim((string) ($payload['content'] ?? '')),
            'transfer_type'        => strtolower(trim((string) ($payload['transferType'] ?? $payload['transfer_type'] ?? ''))),
            'transfer_amount'      => (float) ($payload['transferAmount'] ?? $payload['transfer_amount'] ?? $payload['amount'] ?? 0),
            'reference_code'       => trim((string) ($payload['referenceCode'] ?? $payload['reference_code'] ?? '')),
        ];
    }

    private function mergeStoredPayload(array $transaction, array $overrides = [])
    {
        $payload = kt_sepay_json_decode((string) ($transaction['raw_payload'] ?? ''), []);
        if (!is_array($payload)) {
            $payload = [];
        }

        $payload = array_merge([
            'id' => (string) ($transaction['sepay_transaction_id'] ?? ''),
            'transaction_id' => (string) ($transaction['sepay_transaction_id'] ?? ''),
            'gateway' => (string) ($transaction['gateway'] ?? ''),
            'transactionDate' => (string) ($transaction['transaction_date'] ?? ''),
            'transaction_date' => (string) ($transaction['transaction_date'] ?? ''),
            'accountNumber' => (string) ($transaction['account_number'] ?? ''),
            'account_number' => (string) ($transaction['account_number'] ?? ''),
            'code' => (string) ($transaction['code'] ?? ''),
            'content' => (string) ($transaction['content'] ?? ''),
            'transferType' => (string) ($transaction['transfer_type'] ?? ''),
            'transfer_type' => (string) ($transaction['transfer_type'] ?? ''),
            'transferAmount' => (float) ($transaction['transfer_amount'] ?? 0),
            'transfer_amount' => (float) ($transaction['transfer_amount'] ?? 0),
            'referenceCode' => (string) ($transaction['reference_code'] ?? ''),
            'reference_code' => (string) ($transaction['reference_code'] ?? ''),
        ], $payload, $overrides);

        if (empty($payload['id']) && !empty($transaction['sepay_transaction_id'])) {
            $payload['id'] = (string) $transaction['sepay_transaction_id'];
        }
        if (empty($payload['transaction_id']) && !empty($transaction['sepay_transaction_id'])) {
            $payload['transaction_id'] = (string) $transaction['sepay_transaction_id'];
        }

        return $payload;
    }

    private function matchDestinationAccount(array $normalized, array $settings)
    {
        $configured = trim((string) ($settings['account_number'] ?? ''));
        if ($configured === '') {
            return false;
        }

        $received = trim((string) $normalized['account_number']);
        if ($received === '') {
            return false;
        }

        return hash_equals($configured, $received);
    }

    private function queueProvisioningAfterPublicSignupPayment(array $request, array $invoice)
    {
        $tenantId = (int) ($request['tenant_id'] ?? $invoice['tenant_id'] ?? 0);
        if ($tenantId <= 0) {
            return;
        }

        $payload = json_decode((string) ($invoice['payload_json'] ?? ''), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $reason = (string) ($payload['reason'] ?? ($payload['context']['reason'] ?? ''));
        if ($reason !== 'public_signup') {
            return;
        }

        $tenant = $this->CI->Kt_saas_model->get_tenant($tenantId);
        if (!$tenant) {
            return;
        }

        if ((string) ($tenant['provisioning_status'] ?? '') === 'done') {
            return;
        }

        $jobId = (int) $this->CI->Kt_saas_model->create_provision_job($tenantId, 'provision_tenant', [
            'tenant_id'           => $tenantId,
            'trigger'             => 'sepay_webhook_public_signup_paid',
            'invoice_id'          => (int) ($invoice['id'] ?? 0),
            'payment_request_id'  => (int) ($request['id'] ?? 0),
            'reference_code'      => (string) ($request['reference_code'] ?? ''),
            'requested_at'        => date('Y-m-d H:i:s'),
        ]);

        $this->CI->Kt_saas_model->log_activity('signup.provisioning_queued_after_payment', 'info', [
            'tenant_id'           => $tenantId,
            'invoice_id'          => (int) ($invoice['id'] ?? 0),
            'payment_request_id'  => (int) ($request['id'] ?? 0),
            'provision_job_id'    => $jobId,
            'reason'              => 'public_signup',
        ], $tenantId);
    }
}
