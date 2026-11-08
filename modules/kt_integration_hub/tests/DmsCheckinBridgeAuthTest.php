<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

require_once __DIR__ . '/../libraries/KtIntegrationSignedRequestValidator.php';

$validator = new KtIntegrationSignedRequestValidator();
$method = 'POST';
$path = '/api/v1/dms/checkins';
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
    'checkin' => [
        'dms_checkin_id' => 55001,
        'crm_staff_id' => 5001,
        'crm_customer_id' => 9001,
        'store_id' => 61,
        'route_id' => null,
        'checkin_at' => '2026-06-09T10:00:00+07:00',
        'latitude' => 10.77,
        'longitude' => 106.70,
        'accuracy_meter' => null,
        'distance_from_store_meter' => 120,
        'photo_url' => 'https://cdn.example.test/checkins/checkin.jpg',
        'status' => 'warning',
        'notes' => 'Morning visit',
        'updated_at' => '2026-06-09T10:01:00+07:00',
    ],
];
$rawBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$requestId = 'req_checkin_auth_success';
$idempotencyKey = 'checkin-55001-2026-06-09T10:01:00+07:00';
$signature = $validator->sign($method, $path, $timestamp, $tenantCode, $rawBody, $secret);

$assert = function ($condition, $message) {
    if (!$condition) {
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
$assert(!empty($success['success']) && $success['code'] === 'AUTHORIZED', 'Expected check-in validation success to pass.');

$invalidSignature = $validator->validate(
    [
        'Authorization' => 'Bearer ' . $token,
        'X-Timestamp' => $timestamp,
        'X-Signature' => str_repeat('c', 64),
        'X-Tenant-Code' => $tenantCode,
        'X-Request-Id' => 'req_checkin_auth_bad_signature',
        'X-Idempotency-Key' => 'checkin-55001-bad',
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
$assert(empty($invalidSignature['success']) && $invalidSignature['code'] === 'INVALID_SIGNATURE', 'Expected invalid check-in signature to be rejected.');

$tenantMismatch = $validator->validate(
    [
        'Authorization' => 'Bearer ' . $token,
        'X-Timestamp' => $timestamp,
        'X-Signature' => $signature,
        'X-Tenant-Code' => 'XYZ',
        'X-Request-Id' => 'req_checkin_auth_tenant_mismatch',
        'X-Idempotency-Key' => 'checkin-55001-mismatch',
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

fwrite(STDOUT, "DmsCheckinBridgeAuthTest passed." . PHP_EOL);
