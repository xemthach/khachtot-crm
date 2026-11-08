<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/MisaCertification.php';
require_once __DIR__ . '/MisaSecretVault.php';
require_once __DIR__ . '/MisaTransportInterface.php';

final class MisaTokenService
{
    private $transport;
    private $vault;

    public function __construct(MisaTransportInterface $transport, MisaSecretVault $vault)
    {
        $this->transport = $transport;
        $this->vault = $vault;
    }

    public function connect(array $input)
    {
        foreach (['app_id', 'access_code', 'org_company_code'] as $field) {
            if (trim((string) ($input[$field] ?? '')) === '') throw new InvalidArgumentException($field . ' is required.');
        }
        $response = $this->transport->connect($input);
        if (empty($response['Success'])) return ['success' => false, 'error_code' => (string) ($response['ErrorCode'] ?? 'CONNECT_FAILED'), 'error_message' => (string) ($response['ErrorMessage'] ?? 'MISA connect failed.')];
        $data = $response['Data'] ?? null;
        if (is_string($data)) $data = json_decode($data, true);
        if (!is_array($data)) return ['success' => false, 'error_code' => 'MALFORMED_DATA', 'error_message' => 'MISA connect Data is not valid JSON.'];
        $token = trim((string) ($data['access_token'] ?? ''));
        if ($token === '') return ['success' => false, 'error_code' => 'TOKEN_MISSING', 'error_message' => 'MISA connect did not return an access token.'];
        $expiresAt = $this->parseExpiry($data);
        if ($expiresAt === null) return ['success' => false, 'error_code' => 'EXPIRY_MISSING', 'error_message' => 'MISA connect did not return a valid expiry.'];
        if (strtotime($expiresAt) <= time()) return ['success' => false, 'error_code' => 'TOKEN_EXPIRED', 'error_message' => 'MISA connect returned an expired token.'];
        return ['success' => true, 'token_encrypted' => $this->vault->encrypt($token), 'token_expires_at' => $expiresAt, 'token_expires_ticks' => isset($data['expired_time_ticks']) ? (int) $data['expired_time_ticks'] : null, 'app_name' => (string) ($data['app_name'] ?? ''), 'tenant_code' => (string) ($data['tenant_code'] ?? '')];
    }

    public function isExpired(array $connection, $now = null)
    {
        $now = $now ?: time();
        $expires = strtotime((string) ($connection['token_expires_at'] ?? ''));
        return $expires <= 0 || $expires <= ($now + 60);
    }

    private function parseExpiry(array $data)
    {
        $value = trim((string) ($data['expired_time'] ?? ''));
        if ($value !== '' && strtotime($value) !== false) return date('Y-m-d H:i:s', strtotime($value));
        return null;
    }
}
