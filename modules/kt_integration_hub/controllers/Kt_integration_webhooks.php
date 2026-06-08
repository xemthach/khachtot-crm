<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_integration_webhooks extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(KT_INTEGRATION_HUB_MODULE . '/kt_integration_hub');
        $this->load->model(KT_INTEGRATION_HUB_MODULE . '/Kt_integration_model');
    }

    public function receive($providerCode = '', $publicKey = '')
    {
        try {
            $this->handleReceive($providerCode, $publicKey);
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub webhook failed: ' . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => ENVIRONMENT === 'production' ? 'Internal error.' : $e->getMessage(),
            ], 500);
        }
    }

    private function handleReceive($providerCode = '', $publicKey = '')
    {
        $providerCode = trim((string) rawurldecode((string) $providerCode));
        $publicKey = trim((string) rawurldecode((string) $publicKey));
        if ($providerCode === '' || $publicKey === '') {
            show_404();
        }

        if (strtolower((string) $this->input->method()) !== 'post') {
            $this->jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
            return;
        }

        $connection = $this->Kt_integration_model->get_connection_by_public_key($providerCode, $publicKey);
        if (!$connection) {
            $this->jsonResponse(['success' => false, 'message' => 'Connection not found.'], 404);
            return;
        }

        $rawBody = (string) file_get_contents('php://input');
        $headers = $this->requestHeaders();
        $payload = $this->parsePayload($rawBody);
        if (empty($payload)) {
            $this->jsonResponse(['success' => false, 'message' => 'Invalid payload.'], 400);
            return;
        }
        $verify = $this->verifyRequest($providerCode, $connection, $payload, $rawBody, $headers);
        if (empty($verify['success'])) {
            $this->Kt_integration_model->log('warning', 'webhook.auth_failed', (string) ($verify['message'] ?? 'Webhook authentication failed.'), [
                'provider_code' => $providerCode,
                'headers' => $headers,
            ], (int) $connection['tenant_id'], (int) $connection['id'], $providerCode);
            $this->jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 401);
            return;
        }

        $result = $this->Kt_integration_model->store_webhook_event($connection, $payload, $headers, $rawBody, (string) ($verify['status'] ?? 'verified'));
        if (!empty($result['duplicate'])) {
            $this->jsonResponse(['success' => true, 'status' => 'duplicate', 'event_id' => (int) $result['event_id']]);
            return;
        }

        $this->jsonResponse(['success' => !empty($result['success']), 'event_id' => (int) ($result['event_id'] ?? 0)], !empty($result['success']) ? 200 : 500);
    }

    public function zalo_oauth_callback($publicKey = '')
    {
        $publicKey = trim((string) rawurldecode((string) $publicKey));
        $connection = $this->Kt_integration_model->get_connection_by_public_key('zalo_oa', $publicKey);
        if (!$connection) {
            show_404();
        }

        $code = trim((string) $this->input->get('code'));
        $state = trim((string) $this->input->get('state'));
        $error = trim((string) $this->input->get('error'));
        $this->Kt_integration_model->log($error !== '' ? 'warning' : 'info', 'zalo.oauth_callback_received', 'Zalo OAuth callback received.', [
            'code_present' => $code !== '',
            'state_present' => $state !== '',
            'error' => $error,
        ], (int) $connection['tenant_id'], (int) $connection['id'], 'zalo_oa');

        $this->jsonResponse([
            'success' => $error === '',
            'message' => $error !== '' ? 'Zalo OAuth returned an error.' : 'OAuth callback received. Token exchange is pending real credential validation.',
        ], $error !== '' ? 400 : 200);
    }

    private function verifyRequest($providerCode, array $connection, array $payload, $rawBody, array $headers)
    {
        if ($providerCode === 'zalo_oa') {
            return $this->Kt_integration_model->verify_zalo_webhook($connection, $payload, $rawBody, $headers);
        }

        $verify = $this->Kt_integration_model->verify_custom_webhook($connection, $rawBody, $headers);
        if (!empty($verify['success']) && empty($verify['status'])) {
            $verify['status'] = 'verified';
        }

        return $verify;
    }

    private function parsePayload($rawBody)
    {
        $rawBody = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rawBody);
        $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? 'application/json')));
        if (strpos($contentType, 'application/json') !== false) {
            $decoded = json_decode($rawBody, true);
            return is_array($decoded) ? $decoded : [];
        }

        if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
            parse_str($rawBody, $parsed);
            return is_array($parsed) ? $parsed : [];
        }

        return $this->input->post() ?: [];
    }

    private function requestHeaders()
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[strtolower((string) $key)] = (string) $value;
            $normalized[(string) $key] = (string) $value;
        }

        return $normalized;
    }

    private function jsonResponse(array $payload, $statusCode = 200)
    {
        set_status_header((int) $statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
