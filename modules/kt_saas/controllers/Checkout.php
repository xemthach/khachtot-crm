<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Checkout extends App_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->helper(KT_SAAS_MODULE . '/kt_saas');
        $this->load->model(KT_SAAS_MODULE . '/Kt_saas_model');
        require_once module_dir_path(KT_SAAS_MODULE, 'services/PaymentCollectionService.php');
        require_once module_dir_path(KT_SAAS_MODULE, 'services/BillingEngineService.php');
    }

    public function invoice($invoiceId = 0, $token = '')
    {
        $payments = new PaymentCollectionService();
        $invoice = $payments->resolveInvoiceForCheckout((int) $invoiceId, (string) $token);
        if (!$invoice) {
            show_404();
        }

        $data['title'] = 'Thanh toán hóa đơn dịch vụ';
        $data['invoice'] = $invoice;
        $data['token'] = $token;
        $data['checkout_service'] = $payments;
        $data['payable'] = $payments->canPayInvoice($invoice);
        $data['status_message'] = $this->input->get('status', true);
        $data['sepay_url'] = $this->buildSepayCheckoutUrl($invoice);
        $data['manual_checkout_enabled'] = kt_saas_get_option('kt_saas_checkout_manual_mode', '0') === '1';

        if (empty($data['status_message']) && !empty($data['sepay_url']) && !empty($data['payable'])) {
            redirect($data['sepay_url']);
            return;
        }

        $this->load->view(KT_SAAS_MODULE . '/public/checkout', $data);
    }

    public function pay($invoiceId = 0, $token = '')
    {
        if (strtolower((string) $this->input->method()) !== 'post') {
            redirect(site_url('kt_saas/checkout/invoice/' . (int) $invoiceId . '/' . rawurlencode((string) $token)));
        }

        $payments = new PaymentCollectionService();
        $invoice = $payments->resolveInvoiceForCheckout((int) $invoiceId, (string) $token);
        if (!$invoice) {
            show_404();
        }

        $redirectUrl = site_url('kt_saas/checkout/invoice/' . (int) $invoiceId . '/' . rawurlencode((string) $token));
        if (!$payments->canPayInvoice($invoice)) {
            redirect($redirectUrl . '?status=already_paid');
        }

        if (kt_saas_get_option('kt_saas_checkout_manual_mode', '0') !== '1') {
            redirect($redirectUrl . '?status=manual_disabled');
        }

        $billing = new BillingEngineService();
        $result = $billing->markInvoicePaid($invoice, [
            'gateway'           => 'manual_checkout',
            'payment_reference' => 'CHK-MANUAL-' . (int) $invoice['id'],
            'amount'            => (float) ($invoice['grand_total'] ?? 0),
            'currency'          => $invoice['currency'] ?? 'USD',
            'status'            => 'paid',
            'paid_at'           => date('Y-m-d H:i:s'),
        ]);

        redirect($redirectUrl . (!empty($result['success']) ? '?status=paid' : '?status=failed'));
    }

    public function webhook($gateway = 'manual')
    {
        $rawBody = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_KT_SAAS_SIGNATURE'] ?? '';

        $payments = new PaymentCollectionService();
        if (!$payments->verifyWebhookSignature($rawBody, $signature)) {
            $this->dispatchWebhookFailedEmail('invalid_signature', 'Invalid signature.', [], 'kt_saas_checkout_webhook', 0);
            $this->jsonResponse(['success' => false, 'message' => 'Invalid signature.'], 401);
            return;
        }

        $payload = json_decode((string) $rawBody, true);
        if (!is_array($payload)) {
            $this->dispatchWebhookFailedEmail('invalid_json', 'Invalid JSON payload.', [], 'kt_saas_checkout_webhook', 0);
            $this->jsonResponse(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
            return;
        }

        $result = $payments->processWebhook($gateway, $payload);
        if (empty($result['success'])) {
            $this->dispatchWebhookFailedEmail((string) ($result['status'] ?? 'error'), (string) ($result['message'] ?? 'Webhook processing failed.'), $payload, 'kt_saas_checkout_webhook', (int) ($payload['invoice_id'] ?? 0));
        }
        $this->jsonResponse($result, !empty($result['success']) ? 200 : 400);
    }

    protected function jsonResponse(array $payload, $statusCode = 200)
    {
        set_status_header((int) $statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function buildSepayCheckoutUrl(array $invoice)
    {
        try {
            $this->load->helper('kt_sepay/kt_sepay');
            $this->load->model('kt_sepay/Kt_sepay_model');

            $this->load->library('kt_sepay/Kt_sepay_gateway');
            $tenant = $this->Kt_saas_model->get_tenant((int) ($invoice['tenant_id'] ?? 0));
            if (empty($tenant['id'])) {
                return '';
            }

            $requestId = (int) $this->kt_sepay_gateway->createKtSaasInvoiceRequest($invoice, $tenant, [
                'source' => 'kt_saas_checkout_invoice',
            ]);
            if ($requestId <= 0) {
                return '';
            }

            $request = $this->Kt_sepay_model->get_payment_request($requestId);
            if (empty($request['id']) || empty($request['access_token'])) {
                return '';
            }

            return site_url('kt_sepay/pay/' . (int) $request['id'] . '/' . rawurlencode((string) $request['access_token']));
        } catch (Throwable $e) {
            log_message('error', 'KT SaaS checkout SePay init failed: ' . $e->getMessage());
            return '';
        }
    }

    protected function dispatchWebhookFailedEmail($reason, $message, array $payload, $source, $invoiceId)
    {
        if (!function_exists('kt_saas_send_email_event')) {
            require_once module_dir_path(KT_SAAS_MODULE, 'helpers/kt_saas_helper.php');
        }
        if (!function_exists('kt_saas_send_email_event') || !function_exists('kt_saas_landlord_ops_email')) {
            return;
        }

        $dedupeKey = 'webhook_failed|' . (string) $source . '|' . date('Y-m-d') . '|' . (string) $reason;
        kt_saas_send_email_event('webhook_failed', [
            'tenant_id' => !empty($payload['tenant_id']) ? (int) $payload['tenant_id'] : null,
            'recipient_email' => kt_saas_landlord_ops_email(),
            'owner_name' => 'Operations',
            'tenant_name' => 'Landlord',
            'provider_name' => 'KT SaaS',
            'module_name' => KT_SAAS_MODULE,
            'webhook_url' => site_url('kt_saas/checkout/webhook/' . rawurlencode((string) $source)),
            'job_id' => app_generate_hash(),
            'error_message' => (string) $message,
            'related_type' => 'webhook',
            'related_id' => (string) $invoiceId,
            'dedupe_key' => $dedupeKey,
        ], [
            'event_key' => 'webhook_failed',
            'dedupe_key' => $dedupeKey,
        ]);
    }
}
