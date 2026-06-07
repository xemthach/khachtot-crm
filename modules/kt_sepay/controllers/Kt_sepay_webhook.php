<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_sepay_webhook extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(KT_SEPAY_MODULE . '/kt_sepay');
        $this->load->model(KT_SEPAY_MODULE . '/Kt_sepay_model');
        $this->load->library(KT_SEPAY_MODULE . '/Kt_sepay_processor');
    }

    public function index()
    {
        $this->processWebhookRequest(null);
    }

    public function tenant($tenantCode = '')
    {
        $tenantCode = trim((string) rawurldecode((string) $tenantCode));
        if ($tenantCode === '') {
            show_404();
        }

        $tenant = $this->resolveTenant($tenantCode);
        if (!$tenant) {
            show_404();
        }

        $this->processWebhookRequest($tenant);
    }

    private function processWebhookRequest($tenant = null)
    {
        $rawBody = file_get_contents('php://input');
        $payload = $this->parsePayload($rawBody);
        $source = $tenant ? 'webhook_tenant:' . ($tenant['tenant_code'] ?? '') : 'webhook';
        $logId = $this->Kt_sepay_model->log_webhook([
            'source'         => $source,
            'raw_body'       => $rawBody,
            'parsed_payload' => $payload,
            'status'         => 'received',
        ]);

        if (strtolower((string) $this->input->method()) !== 'post') {
            $this->Kt_sepay_model->update_webhook_log($logId, ['status' => 'error', 'error_message' => 'Method not allowed.']);
            $this->dispatchWebhookFailedEmail($tenant, 'method_not_allowed', 'Method not allowed.', $payload, $source, $logId);
            $this->jsonResponse(['success' => false], 405);
            return;
        }

        $security = $this->validateWebhookSecurity($tenant, $rawBody);
        if (empty($security['success'])) {
            $message = (string) ($security['message'] ?? 'Invalid webhook security.');
            $this->Kt_sepay_model->update_webhook_log($logId, ['status' => 'error', 'error_message' => $message]);
            $this->dispatchWebhookFailedEmail($tenant, 'invalid_security', $message, $payload, $source, $logId);
            $this->jsonResponse(['success' => false], 401);
            return;
        }

        if (!is_array($payload) || empty($payload)) {
            $this->Kt_sepay_model->update_webhook_log($logId, ['status' => 'error', 'error_message' => 'Invalid payload.']);
            $this->dispatchWebhookFailedEmail($tenant, 'invalid_payload', 'Invalid payload.', [], $source, $logId);
            $this->jsonResponse(['success' => false], 400);
            return;
        }

        $result = $this->kt_sepay_processor->processIncomingTransaction($payload, [
            'source'    => $source,
            'tenant_id' => $tenant['id'] ?? null,
        ]);
        $this->Kt_sepay_model->update_webhook_log($logId, [
            'status'         => !empty($result['success']) ? 'processed' : ($result['status'] ?? 'error'),
            'error_message'  => trim((string) ($result['message'] ?? '')),
            'parsed_payload' => kt_sepay_json_encode($payload),
        ]);

        if (!empty($result['success']) || in_array(($result['status'] ?? ''), ['ignored'], true)) {
            $this->jsonResponse(['success' => true]);
            return;
        }

        $this->dispatchWebhookFailedEmail($tenant, (string) ($result['status'] ?? 'error'), (string) ($result['message'] ?? 'Unknown webhook error.'), $payload, $source, $logId);
        $this->jsonResponse(['success' => false], 400);
    }

    private function parsePayload($rawBody)
    {
        $rawBody = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rawBody);
        $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? 'application/json')));
        if (strpos($contentType, 'application/json') !== false) {
            $decoded = json_decode((string) $rawBody, true);
            return is_array($decoded) ? $decoded : [];
        }

        if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
            parse_str((string) $rawBody, $parsed);
            return is_array($parsed) ? $parsed : [];
        }

        return $this->input->post() ?: [];
    }

    private function validateWebhookSecurity($tenant = null, $rawBody = '')
    {
        $authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
        if ($authorization === '') {
            return ['success' => false, 'message' => 'Missing webhook authorization.'];
        }

        $secret = $this->resolveWebhookSecret($tenant);
        if ($secret === '') {
            return ['success' => false, 'message' => 'Webhook secret is not configured.'];
        }

        if (!hash_equals('Apikey ' . $secret, $authorization)) {
            return ['success' => false, 'message' => 'Invalid webhook authorization.'];
        }

        $signature = trim((string) ($_SERVER['HTTP_X_SIGNATURE'] ?? ''));
        if ($signature === '') {
            return ['success' => false, 'message' => 'Missing webhook signature.'];
        }

        $timestamp = trim((string) ($_SERVER['HTTP_X_TIMESTAMP'] ?? ''));
        if ($timestamp === '' || !ctype_digit($timestamp)) {
            return ['success' => false, 'message' => 'Missing or invalid webhook timestamp.'];
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return ['success' => false, 'message' => 'Webhook timestamp is outside the allowed window.'];
        }

        $nonce = trim((string) ($_SERVER['HTTP_X_NONCE'] ?? ''));
        if ($nonce === '' || strlen($nonce) < 12 || strlen($nonce) > 128 || !preg_match('/^[A-Za-z0-9._:-]+$/', $nonce)) {
            return ['success' => false, 'message' => 'Missing or invalid webhook nonce.'];
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $nonce . '.' . (string) $rawBody, $secret);
        $provided = preg_replace('/^sha256=/i', '', $signature);
        if (!is_string($provided) || !preg_match('/^[a-f0-9]{64}$/i', $provided) || !hash_equals($expected, strtolower($provided))) {
            return ['success' => false, 'message' => 'Invalid webhook signature.'];
        }

        return ['success' => true];
    }

    private function resolveWebhookSecret($tenant = null)
    {
        if (is_array($tenant) && !empty($tenant['id'])) {
            $settings = $this->Kt_sepay_model->get_settings((int) $tenant['id'], false);

            return trim((string) ($settings['webhook_secret'] ?? ''));
        }

        $settings = $this->Kt_sepay_model->get_settings(null, false);

        return trim((string) ($settings['webhook_secret'] ?? ''));
    }

    private function resolveTenant($tenantCode)
    {
        $table = db_prefix() . 'kt_saas_tenants';
        if (!$this->db->table_exists($table)) {
            return null;
        }

        return $this->db
            ->where('tenant_code', trim((string) $tenantCode))
            ->where('deleted_at IS NULL', null, false)
            ->get($table)
            ->row_array();
    }

    private function dispatchWebhookFailedEmail($tenant, $reason, $message, array $payload, $source, $logId)
    {
        if (!function_exists('kt_saas_send_email_event')) {
            require_once module_dir_path('kt_saas', 'helpers/kt_saas_helper.php');
        }
        if (!function_exists('kt_saas_send_email_event') || !function_exists('kt_saas_landlord_ops_email')) {
            return;
        }

        $dedupeKey = 'webhook_failed|' . (string) $source . '|' . date('Y-m-d') . '|' . $reason;
        kt_saas_send_email_event('webhook_failed', [
            'tenant_id' => is_array($tenant) && !empty($tenant['id']) ? (int) $tenant['id'] : null,
            'recipient_email' => kt_saas_landlord_ops_email(),
            'owner_name' => 'Operations',
            'tenant_name' => is_array($tenant) ? (string) ($tenant['tenant_name'] ?? $tenant['company_name'] ?? $tenant['tenant_code'] ?? 'SePay') : 'SePay',
            'provider_name' => 'SePay',
            'module_name' => KT_SEPAY_MODULE,
            'webhook_url' => site_url('admin/kt_sepay/webhook'),
            'job_id' => (string) $logId,
            'error_message' => (string) $message,
            'related_type' => 'webhook',
            'related_id' => (string) $logId,
            'dedupe_key' => $dedupeKey,
            'payload_json' => kt_sepay_json_encode($payload),
        ], [
            'event_key' => 'webhook_failed',
            'dedupe_key' => $dedupeKey,
        ]);
    }

    private function jsonResponse(array $payload, $statusCode = 200)
    {
        set_status_header((int) $statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
