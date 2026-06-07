<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_sepay_gateway extends App_gateway
{
    public function __construct()
    {
        parent::__construct();

        $this->setId(KT_SEPAY_GATEWAY_ID);
        $this->setName('SePay');
        $this->setSettings([
            [
                'name'          => 'currencies',
                'label'         => 'settings_paymentmethod_currencies',
                'default_value' => 'VND',
                'field_attributes' => ['disabled' => true],
            ],
            [
                'name'          => 'description_dashboard',
                'label'         => 'settings_paymentmethod_description',
                'type'          => 'textarea',
                'default_value' => 'SePay payment for invoice {invoice_number}',
            ],
        ]);

        $this->ci->load->helper(KT_SEPAY_MODULE . '/kt_sepay');
        $this->ci->load->model(KT_SEPAY_MODULE . '/Kt_sepay_model');
    }

    public function process_payment(array $data): void
    {
        $invoice = $data['invoice'];
        $tenant = function_exists('kt_saas_current_tenant') ? kt_saas_current_tenant() : null;
        $tenantId = !empty($tenant['id']) ? (int) $tenant['id'] : null;
        if (!$this->ci->Kt_sepay_model->is_active($tenantId, $tenantId === null)) {
            $this->markAsInactive();
            set_alert('warning', 'KT SePay chưa được cấu hình hoặc hiện đang tắt.');
            redirect(site_url('invoice/' . $invoice->id . '/' . $invoice->hash));
        }

        if (!$this->supportsCurrency((string) ($invoice->currency_name ?? ''))) {
            set_alert('warning', 'SePay hiện chỉ hỗ trợ hóa đơn bằng VND.');
            redirect(site_url('invoice/' . $invoice->id . '/' . $invoice->hash));
        }

        $requestId = $this->createPerfexInvoiceRequest($invoice, (float) $data['amount'], [
            'payment_attempt_reference' => $data['payment_attempt']->reference ?? '',
            'payment_attempt_fee'       => (float) ($data['gateway_fee'] ?? 0),
            'invoice_hash'              => $invoice->hash,
        ]);

        $request = $this->ci->Kt_sepay_model->get_payment_request($requestId);
        redirect(site_url('kt_sepay/pay/' . (int) $request['id'] . '/' . rawurlencode((string) $request['access_token'])));
    }

    public function createPerfexInvoiceRequest($invoice, $amount, array $metadata = [])
    {
        $invoiceId = (int) $invoice->id;
        $existing = $this->ci->Kt_sepay_model->get_latest_open_payment_request_for_context('perfex_invoice', $invoiceId);
        if ($existing && (float) $existing['amount'] === (float) $amount) {
            return (int) $existing['id'];
        }

        $tenant = function_exists('kt_saas_current_tenant') ? kt_saas_current_tenant() : null;
        $tenantId = !empty($tenant['id']) ? (int) $tenant['id'] : null;
        $settings = $this->ci->Kt_sepay_model->get_settings($tenantId, $tenantId === null);
        $referenceCode = $this->buildReferenceCode('perfex_invoice', [
            'context_id' => $invoiceId,
            'tenant_id'  => $tenantId,
        ], $settings);

        $expiresAt = date('Y-m-d H:i:s', time() + (max((int) ($settings['payment_request_expiry_minutes'] ?? 60), 5) * 60));
        $description = $referenceCode;
        $qrUrl = kt_sepay_qr_url(
            $settings['account_number'] ?? '',
            $settings['bank_code'] ?? '',
            (int) round($amount),
            $description,
            $settings['qr_template'] ?? 'compact'
        );

        return $this->ci->Kt_sepay_model->create_payment_request([
            'context_type'    => 'perfex_invoice',
            'context_id'      => $invoiceId,
            'tenant_id'       => $tenantId,
            'invoice_id'      => $invoiceId,
            'subscription_id' => null,
            'amount'          => round((float) $amount, 2),
            'currency'        => (string) ($invoice->currency_name ?? 'VND'),
            'reference_code'  => $referenceCode,
            'access_token'    => bin2hex(random_bytes(24)),
            'description'     => $description,
            'qr_url'          => $qrUrl,
            'status'          => 'pending',
            'paid_amount'     => 0,
            'metadata_json'   => kt_sepay_json_encode($metadata),
            'expires_at'      => $expiresAt,
            'created_by'      => is_staff_logged_in() ? get_staff_user_id() : null,
        ]);
    }

    public function createKtSaasInvoiceRequest(array $invoice, array $tenant, array $metadata = [])
    {
        if (!$this->supportsCurrency((string) ($invoice['currency'] ?? ''))) {
            return 0;
        }

        $invoiceId = (int) $invoice['id'];
        $existing = $this->ci->Kt_sepay_model->get_latest_open_payment_request_for_context('kt_saas_subscription', $invoiceId);
        if ($existing && (float) $existing['amount'] === (float) ($invoice['grand_total'] ?? 0)) {
            return (int) $existing['id'];
        }

        $settings = $this->ci->Kt_sepay_model->get_settings(null, false);
        $referenceCode = $this->buildReferenceCode('kt_saas_subscription', [
            'context_id'      => $invoiceId,
            'tenant_id'       => (int) ($tenant['id'] ?? 0),
            'subscription_id' => (int) ($invoice['subscription_id'] ?? 0),
        ], $settings);

        $expiresAt = date('Y-m-d H:i:s', time() + (max((int) ($settings['payment_request_expiry_minutes'] ?? 60), 5) * 60));
        $description = $referenceCode;
        $amount = (float) ($invoice['grand_total'] ?? 0);
        $qrUrl = kt_sepay_qr_url(
            $settings['account_number'] ?? '',
            $settings['bank_code'] ?? '',
            (int) round($amount),
            $description,
            $settings['qr_template'] ?? 'compact'
        );

        return $this->ci->Kt_sepay_model->create_payment_request([
            'context_type'    => 'kt_saas_subscription',
            'context_id'      => $invoiceId,
            'tenant_id'       => (int) ($tenant['id'] ?? 0),
            'invoice_id'      => $invoiceId,
            'subscription_id' => !empty($invoice['subscription_id']) ? (int) $invoice['subscription_id'] : null,
            'amount'          => round($amount, 2),
            'currency'        => (string) ($invoice['currency'] ?? 'VND'),
            'reference_code'  => $referenceCode,
            'access_token'    => bin2hex(random_bytes(24)),
            'description'     => $description,
            'qr_url'          => $qrUrl,
            'status'          => 'pending',
            'paid_amount'     => 0,
            'metadata_json'   => kt_sepay_json_encode($metadata),
            'expires_at'      => $expiresAt,
            'created_by'      => is_staff_logged_in() ? get_staff_user_id() : null,
        ]);
    }

    public function createMatbaoOrderRequest(array $order, array $tenant, array $metadata = [])
    {
        if (!$this->supportsCurrency((string) ($order['currency'] ?? ''))) {
            return 0;
        }

        $orderId = (int) ($order['id'] ?? 0);
        $tenantId = (int) ($tenant['id'] ?? 0);
        $amount = round((float) ($order['grand_total'] ?? 0), 2);
        if ($orderId <= 0 || $tenantId <= 0 || $amount <= 0) {
            return 0;
        }

        $existing = $this->ci->Kt_sepay_model->get_latest_open_payment_request_for_context('kt_matbao_invoice_order', $orderId);
        if (
            $existing
            && empty($existing['tenant_id'])
            && (float) ($existing['amount'] ?? 0) === $amount
        ) {
            return (int) $existing['id'];
        }

        if (!$this->ci->Kt_sepay_model->is_active(null, false)) {
            return 0;
        }

        $settings = $this->ci->Kt_sepay_model->get_settings(null, false);
        $referenceCode = $this->buildReferenceCode('kt_matbao_invoice_order', [
            'context_id' => $orderId,
            'tenant_id'  => $tenantId,
        ], $settings);
        if ($existing || $this->ci->Kt_sepay_model->get_payment_request_by_reference_any_owner($referenceCode)) {
            $baseReferenceCode = $referenceCode;
            do {
                $referenceCode = $baseReferenceCode . 'R' . strtoupper(bin2hex(random_bytes(2)));
            } while ($this->ci->Kt_sepay_model->get_payment_request_by_reference_any_owner($referenceCode));
        }
        $expiresAt = date('Y-m-d H:i:s', time() + (max((int) ($settings['payment_request_expiry_minutes'] ?? 60), 5) * 60));
        $qrUrl = kt_sepay_qr_url(
            $settings['account_number'] ?? '',
            $settings['bank_code'] ?? '',
            (int) round($amount),
            $referenceCode,
            $settings['qr_template'] ?? 'compact'
        );

        return $this->ci->Kt_sepay_model->create_payment_request([
            'context_type'    => 'kt_matbao_invoice_order',
            'context_id'      => $orderId,
            'tenant_id'       => null,
            'invoice_id'      => null,
            'subscription_id' => null,
            'amount'          => $amount,
            'currency'        => (string) ($order['currency'] ?? 'VND'),
            'reference_code'  => $referenceCode,
            'access_token'    => bin2hex(random_bytes(24)),
            'description'     => $referenceCode,
            'qr_url'          => $qrUrl,
            'status'          => 'pending',
            'paid_amount'     => 0,
            'metadata_json'   => kt_sepay_json_encode($metadata + [
                'order_code' => (string) ($order['order_code'] ?? ''),
                'buyer_tenant_id' => $tenantId,
                'revenue_owner' => 'landlord',
            ]),
            'expires_at'      => $expiresAt,
            'created_by'      => is_staff_logged_in() ? get_staff_user_id() : null,
        ]);
    }

    public function recordPerfexInvoicePayment(array $request, array $payload, $amount)
    {
        $invoiceId = (int) ($request['invoice_id'] ?? 0);
        if ($invoiceId <= 0) {
            return ['success' => false, 'message' => 'Mã tham chiếu hóa đơn Perfex không hợp lệ.'];
        }

        $transactionId = (string) ($payload['id'] ?? '');
        $referenceCode = trim((string) ($request['reference_code'] ?? ''));
        if ($this->ci->payments_model->transaction_exists($transactionId, $invoiceId)) {
            return ['success' => true, 'duplicate' => true, 'message' => 'Giao dịch này đã được ghi nhận trước đó.'];
        }

        $paymentMethod = 'SePay';
        if (!empty($payload['gateway'])) {
            $paymentMethod .= ' - ' . trim((string) $payload['gateway']);
        }

        $success = $this->addPayment([
            'amount'         => round((float) $amount, 2),
            'invoiceid'      => $invoiceId,
            'transactionid'  => $transactionId,
            'paymentmethod'  => $paymentMethod,
            'note'           => 'SePay reference: ' . $referenceCode,
        ]);

        return [
            'success' => (bool) $success,
            'message' => $success ? 'Đã ghi nhận thanh toán hóa đơn Perfex.' : 'Không thể ghi nhận thanh toán hóa đơn Perfex.',
        ];
    }

    public function getDescription($invoiceId): string
    {
        $invoiceNumber = format_invoice_number($invoiceId);

        return str_replace('{invoice_number}', $invoiceNumber, $this->getSetting('description_dashboard'));
    }

    private function buildReferenceCode($contextType, array $parts, array $settings)
    {
        $prefixMap = [
            'perfex_invoice'       => trim((string) ($settings['reference_prefix_invoice'] ?? 'KTINV')),
            'kt_saas_subscription' => trim((string) ($settings['reference_prefix_subscription'] ?? 'KTSAAS')),
            'kt_matbao_invoice_order' => 'KTMBAO',
            'manual'               => trim((string) ($settings['reference_prefix_manual'] ?? 'KTPAY')),
        ];

        $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', $prefixMap[$contextType] ?? 'KTPAY'));
        if ($contextType === 'perfex_invoice') {
            if (!empty($parts['tenant_id'])) {
                return $prefix . 'T' . (int) ($parts['tenant_id'] ?? 0) . 'I' . (int) ($parts['context_id'] ?? 0);
            }

            return $prefix . (int) ($parts['context_id'] ?? 0);
        }

        if ($contextType === 'kt_saas_subscription') {
            return $prefix . (int) ($parts['tenant_id'] ?? 0) . 'S' . (int) ($parts['subscription_id'] ?? 0) . 'I' . (int) ($parts['context_id'] ?? 0);
        }

        if ($contextType === 'kt_matbao_invoice_order') {
            return $prefix . 'T' . (int) ($parts['tenant_id'] ?? 0) . 'O' . (int) ($parts['context_id'] ?? 0);
        }

        return $prefix . (int) ($parts['context_id'] ?? 0);
    }

    private function supportsCurrency($currency)
    {
        return strtoupper(trim((string) $currency)) === 'VND';
    }
}
