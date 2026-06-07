<?php

defined('BASEPATH') or exit('No direct script access allowed');

class PaymentCollectionService
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model(KT_SAAS_MODULE . '/Kt_saas_model');
    }

    public function getCheckoutUrl(array $invoice, ?array $tenant = null)
    {
        $tenant = $tenant ?: [
            'subdomain'     => $invoice['subdomain'] ?? null,
            'custom_domain' => $invoice['custom_domain'] ?? null,
        ];

        $baseUrl = rtrim(kt_saas_tenant_public_base_url($tenant), '/');
        return $baseUrl . '/kt_saas/checkout/invoice/' . (int) $invoice['id'] . '/' . $this->buildCheckoutToken($invoice);
    }

    public function getWebhookUrl($gateway = 'manual')
    {
        return rtrim(APP_BASE_URL, '/') . '/kt_saas/checkout/webhook/' . rawurlencode(trim((string) $gateway));
    }

    public function resolveInvoiceForCheckout($invoiceId, $token)
    {
        $invoice = $this->CI->Kt_saas_model->get_invoice((int) $invoiceId);
        if (!$invoice) {
            return null;
        }

        if (!$this->validateCheckoutToken($invoice, $token)) {
            return null;
        }

        return $invoice;
    }

    public function canPayInvoice(array $invoice)
    {
        return !in_array((string) ($invoice['status'] ?? 'draft'), ['paid', 'cancelled'], true);
    }

    public function verifyWebhookSignature($rawBody, $signature)
    {
        $signature = trim((string) $signature);
        if ($signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', (string) $rawBody, $this->webhookSecret());
        return hash_equals($expected, $signature);
    }

    public function processWebhook($gateway, array $payload)
    {
        require_once module_dir_path(KT_SAAS_MODULE, 'services/BillingEngineService.php');

        $invoiceId = (int) ($payload['invoice_id'] ?? 0);
        if ($invoiceId <= 0) {
            return ['success' => false, 'message' => 'Missing invoice_id.'];
        }

        $invoice = $this->CI->Kt_saas_model->get_invoice($invoiceId);
        if (!$invoice) {
            return ['success' => false, 'message' => 'Invoice not found.'];
        }

        $status = strtolower(trim((string) ($payload['status'] ?? '')));
        $failureStatuses = ['failed', 'declined', 'canceled', 'cancelled', 'expired', 'error', 'rejected', 'unpaid'];
        if (in_array($status, $failureStatuses, true)) {
            $tenant = !empty($invoice['tenant_id']) ? $this->CI->Kt_saas_model->get_tenant((int) $invoice['tenant_id']) : null;
            $plan = (!empty($tenant['plan_id'])) ? $this->CI->Kt_saas_model->get_plan((int) $tenant['plan_id']) : null;
            $paymentUrl = '';
            if ($tenant) {
                $paymentUrl = (string) $this->getCheckoutUrl($invoice, $tenant);
            }

            $context = [
                'tenant_id' => (int) ($invoice['tenant_id'] ?? 0),
                'tenant' => $tenant ?: [],
                'invoice' => $invoice,
                'plan' => $plan ?: [],
                'owner_name' => (string) ($tenant['owner_name'] ?? ''),
                'owner_email' => (string) ($tenant['owner_email'] ?? ''),
                'tenant_name' => (string) ($tenant['company_name'] ?? ($invoice['company_name'] ?? '')),
                'tenant_code' => (string) ($tenant['tenant_code'] ?? ($invoice['tenant_code'] ?? '')),
                'workspace_url' => $tenant ? rtrim((string) kt_saas_tenant_public_base_url($tenant), '/') : '',
                'workspace_domain' => trim((string) ($tenant['custom_domain'] ?? $tenant['subdomain'] ?? '')),
                'payment_url' => $paymentUrl,
                'invoice_url' => $paymentUrl,
                'invoice_total' => (string) ($payload['amount'] ?? ($invoice['grand_total'] ?? '')),
                'currency' => (string) ($payload['currency'] ?? ($invoice['currency'] ?? '')),
                'error_message' => (string) ($payload['failure_reason'] ?? $payload['message'] ?? ($payload['error'] ?? 'Payment failed.')),
                'related_type' => 'invoice',
                'related_id' => (string) $invoiceId,
            ];

            if (!empty($payload['ops_emails']) && is_array($payload['ops_emails'])) {
                $context['cc'] = array_values(array_filter(array_map('trim', $payload['ops_emails'])));
            }

            $result = $this->CI->Kt_saas_model->send_email_event('payment_failed', $context);
            $renewalResult = null;
            if ($this->isRenewalInvoice($invoice)) {
                $renewalContext = $context;
                $renewalContext['dedupe_key'] = 'renewal_failed|' . $invoiceId . '|' . $status;
                $renewalResult = $this->CI->Kt_saas_model->send_email_event('renewal_failed', $renewalContext);
            }

            $this->CI->Kt_saas_model->log_activity('payment.webhook_failed', 'warning', [
                'invoice_id' => $invoiceId,
                'tenant_id' => (int) ($invoice['tenant_id'] ?? 0),
                'gateway' => trim((string) $gateway),
                'status' => $status,
                'result' => $result,
                'renewal_result' => $renewalResult,
            ], (int) ($invoice['tenant_id'] ?? 0));

            return [
                'success' => true,
                'handled' => true,
                'status' => $status,
                'result' => $result,
                'renewal_result' => $renewalResult,
                'invoice_id' => $invoiceId,
            ];
        }

        if (!in_array($status, ['paid', 'succeeded', 'success'], true)) {
            return ['success' => false, 'message' => 'Unsupported payment status.'];
        }

        $amount = isset($payload['amount']) ? (float) $payload['amount'] : (float) ($invoice['grand_total'] ?? 0);
        if (abs($amount - (float) ($invoice['grand_total'] ?? 0)) > 0.01) {
            $this->CI->Kt_saas_model->log_activity('payment.webhook_amount_mismatch', 'warning', [
                'invoice_id'      => $invoiceId,
                'tenant_id'       => (int) ($invoice['tenant_id'] ?? 0),
                'gateway'         => trim((string) $gateway),
                'expected_amount' => (float) ($invoice['grand_total'] ?? 0),
                'received_amount' => $amount,
                'payload'         => $payload,
            ], (int) ($invoice['tenant_id'] ?? 0));

            return ['success' => false, 'message' => 'Payment amount mismatch.'];
        }

        $currency = trim((string) ($payload['currency'] ?? $invoice['currency'] ?? 'USD'));
        if ($currency !== '' && strcasecmp($currency, (string) ($invoice['currency'] ?? 'USD')) !== 0) {
            $this->CI->Kt_saas_model->log_activity('payment.webhook_currency_mismatch', 'warning', [
                'invoice_id'         => $invoiceId,
                'tenant_id'          => (int) ($invoice['tenant_id'] ?? 0),
                'gateway'            => trim((string) $gateway),
                'expected_currency'  => (string) ($invoice['currency'] ?? 'USD'),
                'received_currency'  => $currency,
                'payload'            => $payload,
            ], (int) ($invoice['tenant_id'] ?? 0));

            return ['success' => false, 'message' => 'Payment currency mismatch.'];
        }

        $billing = new BillingEngineService();
        $result = $billing->markInvoicePaid($invoice, [
            'gateway'           => trim((string) $gateway) ?: 'manual_webhook',
            'payment_reference' => $this->resolveWebhookPaymentReference($gateway, $invoiceId, $payload),
            'amount'            => $amount,
            'currency'          => $currency,
            'status'            => 'paid',
            'paid_at'           => $payload['paid_at'] ?? date('Y-m-d H:i:s'),
            'webhook_payload'   => $payload,
        ]);

        return [
            'success'    => !empty($result['success']),
            'result'     => $result,
            'invoice_id' => $invoiceId,
        ];
    }

    protected function buildCheckoutToken(array $invoice)
    {
        $signingString = implode('|', [
            (int) ($invoice['id'] ?? 0),
            (int) ($invoice['tenant_id'] ?? 0),
            (string) ($invoice['invoice_number'] ?? ''),
            (string) ($invoice['created_at'] ?? ''),
        ]);

        return hash_hmac('sha256', $signingString, $this->checkoutSecret());
    }

    protected function validateCheckoutToken(array $invoice, $token)
    {
        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }

        return hash_equals($this->buildCheckoutToken($invoice), $token);
    }

    protected function checkoutSecret()
    {
        return trim((string) kt_saas_get_option('kt_saas_payment_link_secret', APP_ENC_KEY));
    }

    protected function webhookSecret()
    {
        return trim((string) kt_saas_get_option('kt_saas_payment_webhook_secret', APP_ENC_KEY));
    }

    protected function resolveWebhookPaymentReference($gateway, $invoiceId, array $payload)
    {
        $candidates = [
            trim((string) ($payload['payment_reference'] ?? '')),
            trim((string) ($payload['transaction_id'] ?? '')),
            trim((string) ($payload['event_id'] ?? '')),
            trim((string) ($payload['id'] ?? '')),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return 'WEBHOOK-' . strtoupper(trim((string) $gateway)) . '-' . (int) $invoiceId;
    }

    protected function isRenewalInvoice(array $invoice)
    {
        $payload = json_decode((string) ($invoice['payload_json'] ?? ''), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $reason = (string) ($payload['reason'] ?? ($payload['context']['reason'] ?? ''));
        if (in_array($reason, ['subscription_renewal', 'renewal', 'renewal_invoice'], true)) {
            return true;
        }

        return (string) ($invoice['invoice_mode'] ?? '') === 'renewal';
    }
}
