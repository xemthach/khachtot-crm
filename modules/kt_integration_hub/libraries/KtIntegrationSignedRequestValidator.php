<?php

defined('BASEPATH') or exit('No direct script access allowed');

class KtIntegrationSignedRequestValidator
{
    public function validate(array $headers, $method, $path, $rawBody, $expectedBearerToken, $secret, $expectedTenantCode, callable $isReplay, $requireIdempotencyKey = false): array
    {
        $authorization = trim((string) ($headers['authorization'] ?? $headers['Authorization'] ?? ''));
        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return $this->error('INVALID_TOKEN', 'Missing bearer token.');
        }

        $providedToken = trim((string) ($matches[1] ?? ''));
        if ($providedToken === '' || !hash_equals((string) $expectedBearerToken, $providedToken)) {
            return $this->error('INVALID_TOKEN', 'Invalid bearer token.');
        }

        $requestId = trim((string) ($headers['x-request-id'] ?? $headers['X-Request-Id'] ?? ''));
        if ($requestId === '') {
            return $this->error('REQUEST_ID_REQUIRED', 'Missing request id.');
        }

        $idempotencyKey = trim((string) ($headers['x-idempotency-key'] ?? $headers['X-Idempotency-Key'] ?? ''));
        if ($requireIdempotencyKey && $idempotencyKey === '') {
            return $this->error('IDEMPOTENCY_KEY_REQUIRED', 'Missing idempotency key.');
        }

        $tenantCode = trim((string) ($headers['x-tenant-code'] ?? $headers['X-Tenant-Code'] ?? ''));
        if ($tenantCode === '' || strcasecmp($tenantCode, (string) $expectedTenantCode) !== 0) {
            return $this->error('TENANT_SCOPE_MISMATCH', 'Tenant code does not match connection scope.');
        }

        $timestamp = trim((string) ($headers['x-timestamp'] ?? $headers['X-Timestamp'] ?? ''));
        $timestampUnix = $this->normalizeTimestamp($timestamp);
        if ($timestampUnix === null) {
            return $this->error('REPLAY_BLOCKED', 'Missing or invalid timestamp.');
        }

        if (abs(time() - $timestampUnix) > 300) {
            return $this->error('REPLAY_BLOCKED', 'Timestamp outside allowed window.');
        }

        if ($isReplay($requestId) === true) {
            return $this->error('REPLAY_BLOCKED', 'Replay request detected.');
        }

        $signature = trim((string) ($headers['x-signature'] ?? $headers['X-Signature'] ?? ''));
        if ($signature === '') {
            return $this->error('INVALID_SIGNATURE', 'Missing signature.');
        }

        $providedSignature = preg_replace('/^sha256=/i', '', $signature);
        $expectedSignature = $this->sign($method, $path, $timestamp, $tenantCode, (string) $rawBody, (string) $secret);
        if (!preg_match('/^[a-f0-9]{64}$/i', (string) $providedSignature) || !hash_equals($expectedSignature, strtolower((string) $providedSignature))) {
            return $this->error('INVALID_SIGNATURE', 'Invalid request signature.');
        }

        return [
            'success' => true,
            'code' => 'AUTHORIZED',
            'request_id' => $requestId,
            'idempotency_key' => $idempotencyKey,
            'tenant_code' => $tenantCode,
            'timestamp' => $timestamp,
        ];
    }

    public function sign($method, $path, $timestamp, $tenantCode, $rawBody, $secret): string
    {
        return hash_hmac('sha256', $this->canonicalString($method, $path, $timestamp, $tenantCode, $rawBody), (string) $secret);
    }

    public function canonicalString($method, $path, $timestamp, $tenantCode, $rawBody): string
    {
        return strtoupper(trim((string) $method)) . "\n"
            . '/' . ltrim(trim((string) $path), '/') . "\n"
            . trim((string) $timestamp) . "\n"
            . strtoupper(trim((string) $tenantCode)) . "\n"
            . hash('sha256', (string) $rawBody);
    }

    private function normalizeTimestamp($value): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            if (strlen($value) >= 13) {
                return (int) floor(((int) $value) / 1000);
            }

            return (int) $value;
        }

        $parsed = strtotime($value);

        return $parsed === false ? null : $parsed;
    }

    private function error($code, $message): array
    {
        return [
            'success' => false,
            'code' => (string) $code,
            'message' => (string) $message,
        ];
    }
}
