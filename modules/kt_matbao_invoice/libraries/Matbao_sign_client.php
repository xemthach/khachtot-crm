<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Matbao_sign_client
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model(KT_MATBAO_INVOICE_MODULE . '/Kt_matbao_invoice_model');
    }

    public function login(array $settings, $tenantId = null)
    {
        $endpoint = rtrim((string) ($settings['base_url'] ?? ''), '/') . '/auth/token-matbaoca';
        $payload = [
            'taxcode' => (string) ($settings['taxcode'] ?? ''),
            'username' => (string) ($settings['username'] ?? ''),
            'password' => (string) (kt_matbao_invoice_decrypt($settings['password_encrypted'] ?? '') ?: ''),
        ];
        $result = $this->request('ca_login', 'POST', $endpoint, $payload, null, $tenantId ?? ((int) ($settings['tenant_id'] ?? 0) ?: null));
        if (empty($result['success'])) {
            if (!function_exists('kt_saas_send_email_event')) {
                require_once module_dir_path('kt_saas', 'helpers/kt_saas_helper.php');
            }
            if (function_exists('kt_saas_send_email_event')) {
                $tenant = !empty($tenantId) ? $this->CI->Kt_matbao_invoice_model->get_tenant((int) $tenantId) : [];
                kt_saas_send_email_event('provider_connection_failed', [
                    'tenant_id' => $tenantId,
                    'tenant' => $tenant,
                    'recipient_email' => (string) kt_saas_landlord_ops_email(),
                    'ops_email' => (string) kt_saas_landlord_ops_email(),
                    'module_name' => 'kt_matbao_invoice',
                    'provider_name' => 'MatBao CA/HSM',
                    'webhook_url' => (string) $endpoint,
                    'job_id' => 0,
                    'error_message' => (string) ($result['error'] ?? $result['message'] ?? 'MatBao CA/HSM login failed.'),
                    'dedupe_key' => 'provider_connection_failed|kt_matbao_invoice|ca_login|' . date('Y-m-d'),
                ], [
                    'event_key' => 'provider_connection_failed',
                    'dedupe_key' => 'provider_connection_failed|kt_matbao_invoice|ca_login|' . date('Y-m-d'),
                ]);
            }
        }

        return $result;
    }

    public function getAccessToken(array &$settings, $tenantId = null)
    {
        $encrypted = (string) ($settings['access_token_encrypted'] ?? '');
        $expiredAt = (string) ($settings['token_expired_at'] ?? '');
        $token = $encrypted !== '' ? kt_matbao_invoice_decrypt($encrypted) : '';
        if ($token !== '' && $expiredAt !== '' && strtotime($expiredAt) > time() + 60) {
            return $token;
        }

        $result = $this->login($settings, $tenantId);
        if (empty($result['success'])) {
            return '';
        }

        $data = is_array($result['response']) ? $result['response'] : [];
        $resolvedToken = (string) ($data['Data'] ?? ($data['data'] ?? ''));
        $resolvedExpire = (string) ($data['CustomData']['TokenExpired'] ?? ($data['customData']['TokenExpired'] ?? ''));

        if ($resolvedToken !== '' && !empty($settings['id'])) {
            $this->CI->Kt_matbao_invoice_model->update_ca_access_token((int) $settings['id'], $resolvedToken, $resolvedExpire);
        }

        return $resolvedToken;
    }

    public function getCert(array $settings, $tenantId = null)
    {
        $token = $this->getAccessToken($settings, $tenantId);
        $endpoint = rtrim((string) ($settings['base_url'] ?? ''), '/') . '/signing-matbaoca/getcert';
        return $this->request('ca_getcert', 'GET', $endpoint, null, $token, $tenantId ?? ((int) ($settings['tenant_id'] ?? 0) ?: null));
    }

    public function signatureXml(array $settings, array $payload, $tenantId = null)
    {
        $token = $this->getAccessToken($settings, $tenantId);
        $endpoint = rtrim((string) ($settings['base_url'] ?? ''), '/') . '/signing-matbaoca/signature-xml';
        return $this->request('ca_signature_xml', 'POST', $endpoint, $payload, $token, $tenantId ?? ((int) ($settings['tenant_id'] ?? 0) ?: null));
    }

    public function signaturePdf(array $settings, array $payload, $tenantId = null)
    {
        $token = $this->getAccessToken($settings, $tenantId);
        $endpoint = rtrim((string) ($settings['base_url'] ?? ''), '/') . '/signing-matbaoca/signature-pdf';
        return $this->request('ca_signature_pdf', 'POST', $endpoint, $payload, $token, $tenantId ?? ((int) ($settings['tenant_id'] ?? 0) ?: null));
    }

    public function signatureHash(array $settings, array $payload, $tenantId = null)
    {
        $token = $this->getAccessToken($settings, $tenantId);
        $endpoint = rtrim((string) ($settings['base_url'] ?? ''), '/') . '/signing-matbaoca/signature-hash';
        return $this->request('ca_signature_hash', 'POST', $endpoint, $payload, $token, $tenantId ?? ((int) ($settings['tenant_id'] ?? 0) ?: null));
    }

    private function request($action, $method, $endpoint, $payload = null, $token = null, $tenantId = null)
    {
        $started = microtime(true);
        $headers = ['Accept: application/json'];
        if (!empty($token)) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/json';
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        }

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $latency = (int) round((microtime(true) - $started) * 1000);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $httpOk = ($errno === 0 && $httpCode >= 200 && $httpCode < 300);
        $providerOk = true;
        $providerMessage = '';
        if (is_array($decoded)) {
            $successFlag = null;
            if (array_key_exists('success', $decoded)) {
                $successFlag = (bool) $decoded['success'];
            } elseif (array_key_exists('Success', $decoded)) {
                $successFlag = (bool) $decoded['Success'];
            }
            $providerMessage = (string) ($decoded['message'] ?? ($decoded['Message'] ?? ''));
            if ($successFlag === false) {
                $providerOk = false;
            }
        }
        $ok = $httpOk && $providerOk;

        $safePayload = is_array($payload) ? $payload : [];
        if (isset($safePayload['password'])) {
            $safePayload['password'] = '***';
        }
        if (isset($safePayload['XmlDataBase64'])) {
            $safePayload['XmlDataBase64'] = '***';
        }
        if (isset($safePayload['RecImgBase64'])) {
            $safePayload['RecImgBase64'] = '***';
        }
        $safeResponse = $decoded ?: ['raw' => (string) $raw];
        if (is_array($safeResponse) && isset($safeResponse['Data']) && is_string($safeResponse['Data']) && stripos((string) $action, 'login') !== false) {
            $safeResponse['Data'] = '***';
        }

        $this->CI->Kt_matbao_invoice_model->log_api([
            'tenant_id' => $tenantId,
            'action' => $action,
            'endpoint' => $endpoint,
            'method' => $method,
            'request_payload' => $safePayload,
            'response_payload' => $safeResponse,
            'http_code' => $httpCode,
            'success' => $ok,
            'error_code' => $errno ? 'curl_' . $errno : (!$providerOk ? 'provider_error' : ''),
            'error_message' => $errno ? $error : $providerMessage,
            'latency_ms' => $latency,
        ]);

        return [
            'success' => $ok,
            'http_code' => $httpCode,
            'response' => $decoded,
            'error' => $errno ? $error : $providerMessage,
            'latency_ms' => $latency,
        ];
    }
}
