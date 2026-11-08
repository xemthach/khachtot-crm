<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/KtIntegrationSignedRequestValidator.php');

class DmsWorkspaceMutationClient
{
    public function provision(array $tenant, array $connection, array $payload, string $idempotencyKey): array
    {
        return $this->post($tenant, $connection, '/api/v1/integration/workspaces/provision', $payload, $idempotencyKey);
    }

    public function requestDomain(array $tenant, array $connection, array $payload, string $idempotencyKey): array
    {
        return $this->post($tenant, $connection, '/api/v1/integration/domains/request', $payload, $idempotencyKey);
    }

    public function checkDomainAvailability(array $tenant, array $connection, array $payload, string $idempotencyKey): array
    {
        return $this->post($tenant, $connection, '/api/v1/integration/domains/availability', $payload, $idempotencyKey);
    }

    public function checkDomain(array $tenant, array $connection, array $payload, string $idempotencyKey): array
    {
        return $this->post($tenant, $connection, '/api/v1/integration/domains/check', $payload, $idempotencyKey);
    }

    private function post(array $tenant, array $connection, string $path, array $payload, string $idempotencyKey): array
    {
        $settings = kt_integration_hub_json_decode((string) ($connection['settings_json'] ?? ''), []);
        $baseUrl = kt_integration_hub_dms_control_plane_base_url($tenant, $connection)
            ?: trim((string) ($settings['base_url'] ?? ''));
        $token = trim((string) ($connection['public_key'] ?? ''));
        $secret = kt_integration_hub_decrypt_value($connection['webhook_secret_encrypted'] ?? '');
        $tenantCode = strtoupper(trim((string) ($tenant['tenant_code'] ?? '')));
        if ($baseUrl === '' || $token === '' || $secret === '' || $tenantCode === '') {
            throw new RuntimeException('Kết nối DMS chưa đủ cấu hình để gửi yêu cầu có chữ ký.');
        }
        $raw = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) round(microtime(true) * 1000);
        $requestId = 'm132-' . bin2hex(random_bytes(10));
        $signature = (new KtIntegrationSignedRequestValidator())->sign('POST', $path, $timestamp, $tenantCode, $raw, $secret);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => rtrim($baseUrl, '/') . $path,
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $raw, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$token, 'Content-Type: application/json', 'Accept: application/json',
                'X-Timestamp: '.$timestamp, 'X-Signature: '.$signature, 'X-Tenant-Code: '.$tenantCode,
                'X-Request-Id: '.$requestId, 'X-Idempotency-Key: '.$idempotencyKey],
            CURLOPT_SSL_VERIFYPEER => !str_ends_with((string) parse_url($baseUrl, PHP_URL_HOST), '.test'),
        ]);
        $body = curl_exec($curl); $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE); $error = curl_error($curl); curl_close($curl);
        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) throw new RuntimeException('Phản hồi DMS không hợp lệ: '.($error ?: 'HTTP '.$status));
        $checksum = (string) ($decoded['result_checksum'] ?? ''); $resultSignature = (string) ($decoded['result_signature'] ?? '');
        $unsigned = $decoded; unset($unsigned['result_checksum'], $unsigned['result_signature']);
        $expectedChecksum = hash('sha256', json_encode($unsigned, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if ($checksum === '' || !hash_equals($checksum, $expectedChecksum) || !hash_equals(hash_hmac('sha256', $checksum, $secret), $resultSignature)) {
            throw new RuntimeException('Chữ ký phản hồi DMS không hợp lệ.');
        }
        $decoded['_http_status'] = $status;
        return $decoded;
    }
}
