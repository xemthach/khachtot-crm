<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/KtIntegrationSignedRequestValidator.php');

class SyncProductToDmsJob
{
    private const PRODUCT_UPSERT_PATH = '/api/v1/integration/products/upsert';

    public function handle(array $tenant, array $connection, array $job): array
    {
        $payload = kt_integration_hub_json_decode((string) ($job['payload_json'] ?? ''), []);
        if (empty($payload['tenant']) || empty($payload['product'])) {
            throw new RuntimeException('SyncProductToDmsJob requires a valid payload_json snapshot.');
        }

        $baseUrl = $this->resolveBaseUrl($tenant, $connection);
        $publicKey = trim((string) ($connection['public_key'] ?? ''));
        $secret = kt_integration_hub_decrypt_value($connection['webhook_secret_encrypted'] ?? '');
        $tenantCode = strtoupper(trim((string) ($tenant['tenant_code'] ?? '')));
        $requestId = $this->attemptRequestId($job);
        $idempotencyKey = trim((string) ($job['idempotency_key'] ?? ''));

        if ($baseUrl === '' || $publicKey === '' || $secret === '' || $tenantCode === '' || $requestId === '' || $idempotencyKey === '') {
            throw new RuntimeException('Salesflow DMS connection is not fully configured.');
        }

        $rawBody = kt_integration_hub_json_encode($payload);
        $timestamp = (string) round(microtime(true) * 1000);
        $validator = new KtIntegrationSignedRequestValidator();
        $signature = $validator->sign('POST', self::PRODUCT_UPSERT_PATH, $timestamp, $tenantCode, $rawBody, $secret);

        $response = $this->dispatchRequest(rtrim($baseUrl, '/') . self::PRODUCT_UPSERT_PATH, [
            'Authorization: Bearer ' . $publicKey,
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Timestamp: ' . $timestamp,
            'X-Signature: ' . $signature,
            'X-Tenant-Code: ' . $tenantCode,
            'X-Request-Id: ' . $requestId,
            'X-Idempotency-Key: ' . $idempotencyKey,
        ], $rawBody);

        $decoded = json_decode((string) ($response['body'] ?? ''), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('DMS response is not valid JSON.');
        }

        if ((int) ($response['status_code'] ?? 0) === 409 && (string) ($decoded['code'] ?? '') === 'PRODUCT_SKU_CONFLICT') {
            return [
                'status' => 'conflict',
                'response' => $decoded,
            ];
        }

        if ((int) ($response['status_code'] ?? 0) < 200 || (int) ($response['status_code'] ?? 0) >= 300 || empty($decoded['success'])) {
            throw new RuntimeException((string) ($decoded['message'] ?? ('DMS product sync failed with status ' . (int) ($response['status_code'] ?? 0) . '.')));
        }

        return [
            'status' => 'synced',
            'response' => $decoded,
        ];
    }

    protected function dispatchRequest($url, array $headers, $rawBody): array
    {
        $parsedUrl = parse_url((string) $url);
        $host = strtolower((string) ($parsedUrl['host'] ?? ''));
        $isLocal = in_array($host, ['localhost', '127.0.0.1', 'salesrep.test'], true) || str_ends_with($host, '.test');

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $rawBody,
            CURLOPT_SSL_VERIFYPEER => $isLocal ? false : true,
            CURLOPT_SSL_VERIFYHOST => $isLocal ? 0 : 2,
        ]);

        $body = curl_exec($curl);
        $error = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false) {
            throw new RuntimeException('DMS request failed: ' . ($error ?: 'unknown cURL error'));
        }

        return [
            'status_code' => $statusCode,
            'body' => (string) $body,
        ];
    }

    private function attemptRequestId(array $job): string
    {
        $base = trim((string) ($job['request_id'] ?? ''));
        return $base === '' ? '' : $base . '_a' . ((int) ($job['attempts'] ?? 0) + 1);
    }

    private function resolveBaseUrl(array $tenant, array $connection): string
    {
        if (function_exists('kt_integration_hub_dms_control_plane_base_url')) {
            $controlPlaneBaseUrl = kt_integration_hub_dms_control_plane_base_url($tenant, $connection);
            if ($controlPlaneBaseUrl !== '') {
                return $controlPlaneBaseUrl;
            }
        }

        $settings = kt_integration_hub_json_decode((string) ($connection['settings_json'] ?? ''), []);
        $candidates = [
            trim((string) ($settings['base_url'] ?? '')),
            trim((string) ($connection['external_account_id'] ?? '')),
            trim((string) ($connection['external_account_name'] ?? '')),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && preg_match('/^https?:\\/\\//i', $candidate)) {
                return $candidate;
            }
        }

        return '';
    }
}
