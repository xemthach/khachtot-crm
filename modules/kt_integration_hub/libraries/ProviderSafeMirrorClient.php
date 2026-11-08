<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/KtIntegrationSignedRequestValidator.php');

class ProviderSafeMirrorClient
{
    private const PATH = '/api/v1/integration/providers/safe-status';

    public function fetch(array $tenant, array $connection): array
    {
        $settings = kt_integration_hub_json_decode((string) ($connection['settings_json'] ?? ''), []);
        $baseUrl = kt_integration_hub_dms_control_plane_base_url($tenant, $connection)
            ?: trim((string) ($settings['base_url'] ?? ''));
        $token = trim((string) ($connection['public_key'] ?? ''));
        $secret = kt_integration_hub_decrypt_value($connection['webhook_secret_encrypted'] ?? '');
        $tenantCode = strtoupper(trim((string) ($tenant['tenant_code'] ?? '')));
        if ($baseUrl === '' || $token === '' || $secret === '' || $tenantCode === '') {
            throw new RuntimeException('DMS provider mirror connection is incomplete.');
        }
        $timestamp = (string) round(microtime(true) * 1000);
        $requestId = 'provider-mirror-' . bin2hex(random_bytes(10));
        $signature = (new KtIntegrationSignedRequestValidator())->sign('GET', self::PATH, $timestamp, $tenantCode, '', $secret);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => rtrim($baseUrl, '/') . self::PATH,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
                'X-Timestamp: ' . $timestamp,
                'X-Signature: ' . $signature,
                'X-Tenant-Code: ' . $tenantCode,
                'X-Request-Id: ' . $requestId,
            ],
            CURLOPT_SSL_VERIFYPEER => !str_ends_with((string) parse_url($baseUrl, PHP_URL_HOST), '.test'),
        ]);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($body === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('DMS provider mirror request failed: ' . ($error ?: 'HTTP ' . $status));
        }
        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded) || empty($decoded['success']) || !is_array($decoded['data']['providers'] ?? null)) {
            throw new RuntimeException('DMS provider mirror response schema is invalid.');
        }
        return [
            'providers' => $decoded['data']['providers'],
            'browser_runtime' => (array) ($decoded['data']['browser_runtime'] ?? []),
        ];
    }
}
