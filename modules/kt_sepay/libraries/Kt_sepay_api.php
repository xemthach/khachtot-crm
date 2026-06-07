<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_sepay_api
{
    protected $CI;
    protected $settings;

    public function __construct($params = [])
    {
        $this->CI = &get_instance();
        $this->CI->load->model(KT_SEPAY_MODULE . '/Kt_sepay_model');
        $params = is_array($params) ? $params : [];
        $tenantId = isset($params['tenant_id']) ? (int) $params['tenant_id'] : null;
        $fallbackGlobal = array_key_exists('fallback_global', $params) ? (bool) $params['fallback_global'] : true;
        $this->settings = $this->CI->Kt_sepay_model->get_settings($tenantId, $fallbackGlobal);
    }

    public function listTransactions(array $query = [])
    {
        return $this->request('GET', '/transactions', ['query' => $query]);
    }

    public function getTransactionDetail($transactionId)
    {
        return $this->request('GET', '/transactions/' . rawurlencode(trim((string) $transactionId)));
    }

    public function listBankAccounts(array $query = [])
    {
        return $this->request('GET', '/bank-accounts', ['query' => $query]);
    }

    public function getBankAccountDetail($xid)
    {
        return $this->request('GET', '/bank-accounts/' . rawurlencode(trim((string) $xid)));
    }

    public function listVirtualAccounts($bankAccountXid, array $query = [])
    {
        return $this->request('GET', '/bank-accounts/' . rawurlencode(trim((string) $bankAccountXid)) . '/va', ['query' => $query]);
    }

    public function getVirtualAccountDetail($bankAccountXid, $vaXid)
    {
        return $this->request('GET', '/bank-accounts/' . rawurlencode(trim((string) $bankAccountXid)) . '/va/' . rawurlencode(trim((string) $vaXid)));
    }

    public function request($method, $path, array $options = [])
    {
        $token = trim((string) ($this->settings['api_token'] ?? ''));
        if ($token === '') {
            return [
                'success'    => false,
                'status'     => 0,
                'error_code' => 'missing_token',
                'message'    => 'Chưa cấu hình mã kết nối API SePay.',
                'data'       => null,
                'headers'    => [],
            ];
        }

        $baseUrl = rtrim(kt_sepay_api_base_url($this->settings['environment'] ?? 'sandbox'), '/');
        $url = $baseUrl . '/' . ltrim($path, '/');
        $query = $options['query'] ?? [];
        if (!empty($query)) {
            $url .= '?' . http_build_query(array_filter($query, static function ($value) {
                return $value !== null && $value !== '';
            }));
        }

        $attempts = 0;
        $maxAttempts = 3;
        $headers = [];

        do {
            $attempts++;
            $ch = curl_init($url);
            $responseHeaders = [];
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST  => strtoupper(trim((string) $method)),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_HTTPHEADER     => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $token,
                ],
                CURLOPT_HEADERFUNCTION => static function ($curl, $header) use (&$responseHeaders) {
                    $length = strlen($header);
                    $parts = explode(':', $header, 2);
                    if (count($parts) === 2) {
                        $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                    }

                    return $length;
                },
            ]);

            if (array_key_exists('body', $options)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, kt_sepay_json_encode($options['body']));
            }

            $raw = curl_exec($ch);
            $curlError = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $headers = $responseHeaders;
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            $message = is_array($decoded) ? (string) ($decoded['message'] ?? '') : ($curlError !== '' ? $curlError : 'Không xác định được lỗi từ SePay API.');
            $errorCode = is_array($decoded) ? (string) ($decoded['error_code'] ?? '') : '';

            if ($status !== 429) {
                return [
                    'success'    => $status >= 200 && $status < 300 && is_array($decoded) && (($decoded['status'] ?? '') === 'success' || array_key_exists('data', $decoded)),
                    'status'     => $status,
                    'error_code' => $errorCode,
                    'message'    => $message,
                    'data'       => is_array($decoded) ? ($decoded['data'] ?? null) : null,
                    'meta'       => is_array($decoded) ? ($decoded['meta'] ?? null) : null,
                    'headers'    => $headers,
                    'raw'        => $raw,
                ];
            }

            $retryAfter = max((int) ($headers['retry-after'] ?? 1), 1);
            sleep(min($retryAfter, 5));
        } while ($attempts < $maxAttempts);

        return [
            'success'    => false,
            'status'     => 429,
            'error_code' => 'rate_limited',
            'message'    => 'SePay API đang giới hạn tần suất truy cập. Vui lòng thử lại sau.',
            'data'       => null,
            'headers'    => $headers,
        ];
    }
}
