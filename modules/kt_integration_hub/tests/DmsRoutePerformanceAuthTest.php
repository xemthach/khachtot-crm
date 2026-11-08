<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

require_once __DIR__ . '/../libraries/KtIntegrationSignedRequestValidator.php';

$validator = new KtIntegrationSignedRequestValidator();
$method = 'GET';
$path = '/api/v1/dms/route-performance';
$tenantCode = 'ABC';
$token = 'public-token-123';
$secret = 'tenant-secret-456';
$timestamp = (string) round(microtime(true) * 1000);
$rawBody = '';
$requestId = 'req_route_perf_auth_success';
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
    false
);
$assert(! empty($success['success']) && $success['code'] === 'AUTHORIZED', 'Expected route performance validation success to pass.');

$invalidSignature = $validator->validate(
    [
        'Authorization' => 'Bearer ' . $token,
        'X-Timestamp' => $timestamp,
        'X-Signature' => str_repeat('c', 64),
        'X-Tenant-Code' => $tenantCode,
        'X-Request-Id' => 'req_route_perf_auth_bad_signature',
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
    false
);
$assert(empty($invalidSignature['success']) && $invalidSignature['code'] === 'INVALID_SIGNATURE', 'Expected invalid route performance signature to be rejected.');

$tenantMismatch = $validator->validate(
    [
        'Authorization' => 'Bearer ' . $token,
        'X-Timestamp' => $timestamp,
        'X-Signature' => $signature,
        'X-Tenant-Code' => 'XYZ',
        'X-Request-Id' => 'req_route_perf_auth_tenant_mismatch',
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
    false
);
$assert(empty($tenantMismatch['success']) && $tenantMismatch['code'] === 'TENANT_SCOPE_MISMATCH', 'Expected tenant mismatch to be rejected.');

fwrite(STDOUT, "DmsRoutePerformanceAuthTest passed." . PHP_EOL);
