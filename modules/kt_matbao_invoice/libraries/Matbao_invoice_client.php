<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Matbao_invoice_client
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model(KT_MATBAO_INVOICE_MODULE . '/Kt_matbao_invoice_model');
    }

    public function login(array $settings, $tenantId = null)
    {
        $endpoint = rtrim((string) ($settings['invoice_base_url'] ?? ''), '/') . '/api/auth/login';
        $payload = [
            'MST' => (string) ($settings['mst'] ?? ''),
            'TDNhap' => (string) ($settings['username'] ?? ''),
            'MKhau' => (string) (kt_matbao_invoice_decrypt($settings['password_encrypted'] ?? '') ?: ''),
        ];

        $result = $this->request('login', 'POST', $endpoint, $payload, null, $tenantId ?? ((int) ($settings['tenant_id'] ?? 0) ?: null));
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
                    'provider_name' => 'MatBao Invoice',
                    'webhook_url' => (string) $endpoint,
                    'job_id' => 0,
                    'error_message' => (string) ($result['error'] ?? $result['message'] ?? 'MatBao Invoice login failed.'),
                    'dedupe_key' => 'provider_connection_failed|kt_matbao_invoice|login|' . date('Y-m-d'),
                ], [
                    'event_key' => 'provider_connection_failed',
                    'dedupe_key' => 'provider_connection_failed|kt_matbao_invoice|login|' . date('Y-m-d'),
                ]);
            }
        }

        return $result;
    }

    public function getTemplates(array $settings, $year, $tenantId = null)
    {
        $token = $this->getAccessToken($settings, $tenantId);
        $endpoint = rtrim((string) ($settings['invoice_base_url'] ?? ''), '/') . '/api/invoice/templates?year=' . (int) $year;
        return $this->request('get_templates', 'GET', $endpoint, null, $token, $tenantId ?? ((int) ($settings['tenant_id'] ?? 0) ?: null));
    }

    public function createInvoice(array $settings, array $payload, $tenantId = null)
    {
        $token = $this->getAccessToken($settings, $tenantId);
        $endpoint = rtrim((string) ($settings['invoice_base_url'] ?? ''), '/') . '/api/invoice/create-invoice';
        return $this->request('create_invoice', 'POST', $endpoint, $payload, $token, $tenantId ?? ((int) ($settings['tenant_id'] ?? 0) ?: null));
    }

    public function getInvoiceDetail(array $settings, array $payload, $tenantId = null)
    {
        $token = $this->getAccessToken($settings, $tenantId);
        $endpoint = rtrim((string) ($settings['invoice_base_url'] ?? ''), '/') . '/api/invoice/invoice-detail';
        return $this->request('invoice_detail', 'POST', $endpoint, $payload, $token, $tenantId ?? ((int) ($settings['tenant_id'] ?? 0) ?: null));
    }

    public function signInvoice(array $settings, array $payload, $tenantId = null)
    {
        $token = $this->getAccessToken($settings, $tenantId);
        $endpoint = rtrim((string) ($settings['invoice_base_url'] ?? ''), '/') . '/api/invoice/sign-invoice';
        return $this->request('sign_invoice', 'POST', $endpoint, $payload, $token, $tenantId ?? ((int) ($settings['tenant_id'] ?? 0) ?: null));
    }

    public function getXmlNotSign(array $settings, $maSoHDon, $tenantId = null)
    {
        $token = $this->getAccessToken($settings, $tenantId);
        $endpoint = rtrim((string) ($settings['invoice_base_url'] ?? ''), '/') . '/api/invoice/get-xml-not-sign?MaSoHDon=' . rawurlencode((string) $maSoHDon);
        return $this->request('get_xml_not_sign', 'GET', $endpoint, null, $token, $tenantId ?? ((int) ($settings['tenant_id'] ?? 0) ?: null));
    }

    public function signXml(array $settings, array $payload, $tenantId = null)
    {
        $token = $this->getAccessToken($settings, $tenantId);
        $endpoint = rtrim((string) ($settings['invoice_base_url'] ?? ''), '/') . '/api/invoice/sign-xml';
        return $this->request('sign_xml', 'POST', $endpoint, $payload, $token, $tenantId ?? ((int) ($settings['tenant_id'] ?? 0) ?: null));
    }

    public function downloadInvoice(array $settings, array $payload, $tenantId = null)
    {
        $token = $this->getAccessToken($settings, $tenantId);
        $endpoint = rtrim((string) ($settings['invoice_base_url'] ?? ''), '/') . '/api/invoice/download-invoice';
        return $this->request('download_invoice', 'POST', $endpoint, $payload, $token, $tenantId ?? ((int) ($settings['tenant_id'] ?? 0) ?: null));
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
        $resolvedToken = (string) ($data['accessToken'] ?? ($data['Data']['accessToken'] ?? ($data['data']['accessToken'] ?? '')));
        $resolvedExpire = (string) ($data['expiredDate'] ?? ($data['Data']['expiredDate'] ?? ($data['data']['expiredDate'] ?? '')));

        if ($resolvedToken !== '' && !empty($settings['id'])) {
            $this->CI->Kt_matbao_invoice_model->update_access_token((int) $settings['id'], $resolvedToken, $resolvedExpire);
        }

        return $resolvedToken;
    }

    public function request($action, $method, $endpoint, $payload = null, $token = null, $tenantId = null)
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
            $errorCodeRaw = $decoded['errorCode'] ?? ($decoded['ErrorCode'] ?? null);
            $errorCode = is_numeric($errorCodeRaw) ? (int) $errorCodeRaw : null;
            $providerMessage = (string) ($decoded['message'] ?? ($decoded['Message'] ?? ''));
            // MatBao APIs are inconsistent across versions:
            // - Some return success=true/false
            // - Some return ErrorCode=200 on success, 401/4xx/5xx on failure
            // Treat success as true when:
            //   successFlag === true, OR ErrorCode in [0,200], OR HTTP 2xx with no explicit failure markers.
            $codeIndicatesSuccess = ($errorCode === null || $errorCode === 0 || $errorCode === 200);
            if ($successFlag === false || ($successFlag !== true && !$codeIndicatesSuccess)) {
                $providerOk = false;
            }
        }
        $ok = $httpOk && $providerOk;

        $safePayload = is_array($payload) ? $payload : [];
        if (isset($safePayload['MKhau'])) {
            $safePayload['MKhau'] = '***';
        }
        if (isset($safePayload['accessToken'])) {
            $safePayload['accessToken'] = '***';
        }

        $resolvedErrorMessage = '';
        if ($errno) {
            $resolvedErrorMessage = $error;
        } elseif (!$providerOk) {
            $resolvedErrorMessage = $providerMessage;
        }

        $safeResponse = $decoded ?: ['raw' => (string) $raw];
        if (is_array($safeResponse)) {
            if (isset($safeResponse['data']['accessToken'])) {
                $safeResponse['data']['accessToken'] = '***';
            }
            if (isset($safeResponse['Data']['accessToken'])) {
                $safeResponse['Data']['accessToken'] = '***';
            }
            if (isset($safeResponse['Data']) && is_string($safeResponse['Data']) && stripos((string) $action, 'login') !== false) {
                $safeResponse['Data'] = '***';
            }
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
            'error_message' => $resolvedErrorMessage,
            'latency_ms' => $latency,
        ]);

        return [
            'success' => $ok,
            'http_code' => $httpCode,
            'response' => $decoded,
            'error' => $resolvedErrorMessage,
            'latency_ms' => $latency,
        ];
    }
}
