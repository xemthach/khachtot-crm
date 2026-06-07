<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * KT eInvoice — SePay eInvoice API Client
 *
 * Xử lý toàn bộ HTTP communication với SePay eInvoice API v1.
 * Token được tự động lấy và cache. KHÔNG log raw token.
 */
class SepayEinvoiceApiClient
{
    /** @var string */
    private $baseUrl;

    /** @var string */
    private $environment;

    /** @var string|null Access token hiện tại (in-memory) */
    private $accessToken;

    /** @var int Token expires timestamp */
    private $tokenExpiresAt = 0;

    /** @var string SePay username (plaintext, in memory only) */
    private $username;

    /** @var string SePay password (plaintext, in memory only) */
    private $password;

    /** @var int Tenant ID — dùng để log */
    private $tenantId;

    /** @var Kt_einvoice_model */
    private $model;

    /**
     * @param string $environment 'sandbox' | 'production'
     * @param string $username    SePay username (đã decrypt)
     * @param string $password    SePay password (đã decrypt)
     * @param int    $tenantId
     * @param string|null $cachedToken   Token đã cache từ DB (nếu còn hạn)
     * @param int         $cachedExpires Token expiry timestamp từ DB
     */
    public function __construct(
        string $environment,
        string $username,
        string $password,
        int    $tenantId,
        ?string $cachedToken   = null,
        int     $cachedExpires = 0
    ) {
        $this->environment = $environment;
        $this->username    = $username;
        $this->password    = $password;
        $this->tenantId    = $tenantId;
        $this->baseUrl     = ($environment === 'production')
            ? KT_EINVOICE_API_PRODUCTION
            : KT_EINVOICE_API_SANDBOX;

        // Dùng cached token nếu còn hạn
        if ($cachedToken && $cachedExpires > (time() + KT_EINVOICE_TOKEN_EXPIRY_BUFFER)) {
            $this->accessToken    = $cachedToken;
            $this->tokenExpiresAt = $cachedExpires;
        }

        $CI = &get_instance();
        if (!isset($CI->Kt_einvoice_model)) {
            $CI->load->model('kt_einvoice/Kt_einvoice_model');
        }
        $this->model = $CI->Kt_einvoice_model;
    }

    // ── Public API Methods ────────────────────────────────────────────────────

    /**
     * Lấy danh sách nhà cung cấp hóa đơn
     */
    public function getProviderAccounts(): array
    {
        return $this->request('GET', KT_EINVOICE_ENDPOINT_PROVIDERS);
    }

    /**
     * Lấy chi tiết nhà cung cấp
     */
    public function getProviderAccountDetail(string $id): array
    {
        $endpoint = str_replace('{id}', $id, KT_EINVOICE_ENDPOINT_PROVIDER_DETAIL);
        return $this->request('GET', $endpoint);
    }

    /**
     * Tạo hóa đơn (nháp hoặc phát hành luôn)
     */
    public function createInvoice(array $payload): array
    {
        return $this->request('POST', KT_EINVOICE_ENDPOINT_CREATE, $payload);
    }

    /**
     * Kiểm tra trạng thái tạo/phát hành qua tracking code
     */
    public function checkStatus(string $trackingCode): array
    {
        $endpoint = str_replace('{tracking_code}', urlencode($trackingCode), KT_EINVOICE_ENDPOINT_CHECK);
        return $this->request('GET', $endpoint);
    }

    /**
     * Phát hành hóa đơn đã tạo nháp
     */
    public function issueInvoice(string $sepayInvoiceId): array
    {
        return $this->request('POST', KT_EINVOICE_ENDPOINT_ISSUE, [
            'invoice_id' => $sepayInvoiceId,
        ]);
    }

    /**
     * Lấy danh sách hóa đơn
     */
    public function listInvoices(array $params = []): array
    {
        $query    = !empty($params) ? '?' . http_build_query($params) : '';
        return $this->request('GET', KT_EINVOICE_ENDPOINT_LIST . $query);
    }

    /**
     * Lấy chi tiết hóa đơn
     */
    public function getInvoiceDetail(string $invoiceId): array
    {
        $endpoint = str_replace('{id}', urlencode($invoiceId), KT_EINVOICE_ENDPOINT_DETAIL);
        return $this->request('GET', $endpoint);
    }

    /**
     * Tải hóa đơn PDF hoặc XML
     * @param string $type 'pdf' | 'xml'
     */
    public function downloadInvoice(string $invoiceId, string $type = 'pdf'): array
    {
        $endpoint = str_replace('{id}', urlencode($invoiceId), KT_EINVOICE_ENDPOINT_DOWNLOAD);
        return $this->request('GET', $endpoint . '?type=' . $type);
    }

    /**
     * Xóa hóa đơn nháp
     */
    public function deleteInvoice(string $invoiceId): array
    {
        $endpoint = str_replace('{id}', urlencode($invoiceId), KT_EINVOICE_ENDPOINT_DELETE);
        return $this->request('DELETE', $endpoint);
    }

    /**
     * Kiểm tra hạn mức còn lại
     */
    public function checkUsage(): array
    {
        return $this->request('GET', KT_EINVOICE_ENDPOINT_USAGE);
    }

    /**
     * Hủy hóa đơn đã phát hành
     */
    public function cancelInvoice(string $invoiceId, string $reason = ''): array
    {
        $endpoint = str_replace('{id}', urlencode($invoiceId), KT_EINVOICE_ENDPOINT_CANCEL);
        return $this->request('POST', $endpoint, ['reason' => $reason]);
    }

    /**
     * Lấy access token hiện tại (để cache vào DB)
     */
    public function getCurrentToken(): ?string
    {
        return $this->accessToken;
    }

    public function getTokenExpiresAt(): int
    {
        return $this->tokenExpiresAt;
    }

    // ── Token Management ──────────────────────────────────────────────────────

    /**
     * Lấy token từ SePay (POST /v1/token)
     * Tự động gọi khi token chưa có hoặc sắp hết hạn
     */
    private function refreshToken(): void
    {
        $startTime = microtime(true);
        $endpoint  = KT_EINVOICE_ENDPOINT_TOKEN;
        $url       = $this->baseUrl . $endpoint;

        $payload = json_encode([
            'username' => $this->username,
            'password' => $this->password,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => KT_EINVOICE_HTTP_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => KT_EINVOICE_HTTP_CONNECT_TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $latency  = (int) ((microtime(true) - $startTime) * 1000);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Log — KHÔNG log password
        $this->model->insertApiLog([
            'tenant_id'     => $this->tenantId,
            'record_id'     => null,
            'environment'   => $this->environment,
            'action'        => 'get_token',
            'endpoint'      => $endpoint,
            'method'        => 'POST',
            'request_json'  => json_encode(['username' => $this->username, 'password' => '***']),
            'response_code' => $httpCode,
            'response_json' => $raw,
            'latency_ms'    => $latency,
            'success'       => ($httpCode === 200) ? 1 : 0,
        ]);

        if ($curlError) {
            throw new RuntimeException('[kt_einvoice] cURL error on token: ' . $curlError);
        }

        $body = json_decode($raw, true);

        if ($httpCode !== 200 || empty($body['data']['access_token'])) {
            $msg = $body['message'] ?? 'Unknown error';
            throw new RuntimeException('[kt_einvoice] Token failed (HTTP ' . $httpCode . '): ' . $msg);
        }

        $this->accessToken    = $body['data']['access_token'];
        $expiresIn            = (int) ($body['data']['expires_in'] ?? 3600);
        $this->tokenExpiresAt = time() + $expiresIn;
    }

    /**
     * Đảm bảo token hợp lệ trước khi gọi API
     */
    private function ensureToken(): void
    {
        if (empty($this->accessToken) || time() >= ($this->tokenExpiresAt - KT_EINVOICE_TOKEN_EXPIRY_BUFFER)) {
            $this->refreshToken();
            // Lưu token mới vào DB để cache
            $this->model->cacheToken(
                $this->tenantId,
                $this->environment,
                $this->accessToken,
                date('Y-m-d H:i:s', $this->tokenExpiresAt)
            );
        }
    }

    // ── HTTP Core ─────────────────────────────────────────────────────────────

    /**
     * Gửi HTTP request đến SePay API
     *
     * @throws RuntimeException on transport or API errors
     */
    private function request(string $method, string $endpoint, array $data = []): array
    {
        $this->ensureToken();

        $startTime = microtime(true);
        $url       = $this->baseUrl . $endpoint;
        $method    = strtoupper($method);

        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => KT_EINVOICE_HTTP_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => KT_EINVOICE_HTTP_CONNECT_TIMEOUT,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $requestBody = '';
        if ($method === 'POST') {
            $requestBody = json_encode($data);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        } elseif ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $raw       = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $latency   = (int) ((microtime(true) - $startTime) * 1000);
        $curlError = curl_error($ch);
        curl_close($ch);

        $success = ($httpCode >= 200 && $httpCode < 300);

        // Log (sanitize — KHÔNG log token)
        $this->model->insertApiLog([
            'tenant_id'     => $this->tenantId,
            'record_id'     => null,
            'environment'   => $this->environment,
            'action'        => $this->actionFromEndpoint($endpoint, $method),
            'endpoint'      => $endpoint,
            'method'        => $method,
            'request_json'  => $requestBody ?: null,
            'response_code' => $httpCode,
            'response_json' => $raw,
            'latency_ms'    => $latency,
            'success'       => $success ? 1 : 0,
            'error_message' => $curlError ?: null,
        ]);

        if ($curlError) {
            throw new RuntimeException('[kt_einvoice] cURL error: ' . $curlError);
        }

        $body = json_decode($raw ?: '{}', true) ?? [];

        // 401 → token hết hạn — thử refresh 1 lần
        if ($httpCode === 401) {
            $this->accessToken = null; // Buộc refresh
            throw new RuntimeException('[kt_einvoice] Unauthorized (401). Token expired.');
        }

        // 429 → Rate limit
        if ($httpCode === 429) {
            throw new RuntimeException('[kt_einvoice] Rate limited (429). Retry later.');
        }

        // 5xx → Server error
        if ($httpCode >= 500) {
            $msg = $body['message'] ?? 'Server error';
            throw new RuntimeException('[kt_einvoice] Server error (HTTP ' . $httpCode . '): ' . $msg);
        }

        // 4xx (trừ 401, 429) → Client error — không retry
        if ($httpCode >= 400) {
            $msg = $body['message'] ?? 'Client error';
            throw new RuntimeException('[kt_einvoice] Client error (HTTP ' . $httpCode . '): ' . $msg, $httpCode);
        }

        return $body;
    }

    private function actionFromEndpoint(string $endpoint, string $method): string
    {
        if (strpos($endpoint, '/token') !== false)             return 'get_token';
        if (strpos($endpoint, '/provider-accounts') !== false) return 'get_providers';
        if (strpos($endpoint, '/create') !== false)            return 'create_invoice';
        if (strpos($endpoint, '/issue') !== false)             return 'issue_invoice';
        if (strpos($endpoint, '/check/') !== false)            return 'check_status';
        if (strpos($endpoint, '/download') !== false)          return 'download_invoice';
        if (strpos($endpoint, '/cancel') !== false)            return 'cancel_invoice';
        if (strpos($endpoint, '/usage') !== false)             return 'check_usage';
        if ($method === 'DELETE')                              return 'delete_invoice';
        if (strpos($endpoint, '/invoices') !== false)         return 'invoice_detail';
        return 'unknown';
    }
}
