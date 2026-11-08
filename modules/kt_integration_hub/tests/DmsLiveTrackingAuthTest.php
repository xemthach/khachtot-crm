<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

require_once __DIR__ . '/../libraries/KtIntegrationSignedRequestValidator.php';

$validator = new KtIntegrationSignedRequestValidator();
$method = 'POST';
$path = '/api/v1/dms/live-tracking';
$tenantCode = 'ABC';
$token = 'public-token-123';
$secret = 'tenant-secret-456';
$timestamp = (string) round(microtime(true) * 1000);
$payload = [
    'tenant' => [
        'crm_tenant_id' => 1001,
        'crm_company_id' => 1001,
        'tenant_code' => 'ABC',
    ],
    'tracking' => [
        'dms_user_id' => 55001,
        'crm_staff_id' => 5001,
        'latitude' => 10.7769,
        'longitude' => 106.7009,
        'last_seen_at' => '2026-06-09T10:00:30+07:00',
        'tracking_status' => 'online',
        'battery_level' => 84,
        'device_info' => 'Mozilla/5.0',
        'user_local_time' => '2026-06-09 10:00:30',
        'updated_at' => '2026-06-09T10:00:30+07:00',
    ],
];
$rawBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$requestId = 'req_live_tracking_auth_success';
$idempotencyKey = 'tracking-55001-2026-06-09T10:00:30+07:00';
$signature = $validator->sign($method, $path, $timestamp, $tenantCode, $rawBody, $secret);

$assert = function ($condition, $message) {
    if (! $condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$success = $validator->validate(
    [
        'Authorization' => 'Bearer ' . $token,
        'X-Timestamp' => $timestamp,
        'X-Signature' => $signature,
        'X-Tenant-Code' => $tenantCode,
        'X-Request-Id' => $requestId,
        'X-Idempotency-Key' => $idempotencyKey,
    ],
    $method,
    $path,
    $rawBody,
    $token,
    $secret,
    $tenantCode,
    function () {
        return false;
    },
    true
);
$assert(! empty($success['success']) && $success['code'] === 'AUTHORIZED', 'Expected live tracking validation success to pass.');

$invalidSignature = $validator->validate(
    [
        'Authorization' => 'Bearer ' . $token,
        'X-Timestamp' => $timestamp,
        'X-Signature' => str_repeat('c', 64),
        'X-Tenant-Code' => $tenantCode,
        'X-Request-Id' => 'req_live_tracking_auth_bad_signature',
        'X-Idempotency-Key' => 'tracking-55001-bad',
    ],
    $method,
    $path,
    $rawBody,
    $token,
    $secret,
    $tenantCode,
    function () {
        return false;
    },
    true
);
$assert(empty($invalidSignature['success']) && $invalidSignature['code'] === 'INVALID_SIGNATURE', 'Expected invalid live tracking signature to be rejected.');

$tenantMismatch = $validator->validate(
    [
        'Authorization' => 'Bearer ' . $token,
        'X-Timestamp' => $timestamp,
        'X-Signature' => $signature,
        'X-Tenant-Code' => 'XYZ',
        'X-Request-Id' => 'req_live_tracking_auth_tenant_mismatch',
        'X-Idempotency-Key' => 'tracking-55001-mismatch',
    ],
    $method,
    $path,
    $rawBody,
    $token,
    $secret,
    $tenantCode,
    function () {
        return false;
    },
    true
);
$assert(empty($tenantMismatch['success']) && $tenantMismatch['code'] === 'TENANT_SCOPE_MISMATCH', 'Expected tenant mismatch to be rejected.');

fwrite(STDOUT, "DmsLiveTrackingAuthTest passed." . PHP_EOL);
