<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

require_once __DIR__ . '/../libraries/KtIntegrationSignedRequestValidator.php';

$validator = new KtIntegrationSignedRequestValidator();
$method = 'GET';
$path = '/api/v1/integration/tenant/context';
$tenantCode = 'ABC';
$token = 'public-token-123';
$secret = 'tenant-secret-456';
$timestamp = (string) round(microtime(true) * 1000);
$rawBody = '';
$requestId = 'req_validator_success';
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
    ],
    $method,
    $path,
    $rawBody,
    $token,
    $secret,
    $tenantCode,
    function () {
        return false;
    }
);
$assert(!empty($success['success']) && $success['code'] === 'AUTHORIZED', 'Expected success validation to pass.');

$invalidToken = $validator->validate(
    [
        'Authorization' => 'Bearer wrong-token',
        'X-Timestamp' => $timestamp,
        'X-Signature' => $signature,
        'X-Tenant-Code' => $tenantCode,
        'X-Request-Id' => 'req_validator_bad_token',
    ],
    $method,
    $path,
    $rawBody,
    $token,
    $secret,
    $tenantCode,
    function () {
        return false;
    }
);
$assert(empty($invalidToken['success']) && $invalidToken['code'] === 'INVALID_TOKEN', 'Expected invalid token to be rejected.');

$invalidSignature = $validator->validate(
    [
        'Authorization' => 'Bearer ' . $token,
        'X-Timestamp' => $timestamp,
        'X-Signature' => str_repeat('a', 64),
        'X-Tenant-Code' => $tenantCode,
        'X-Request-Id' => 'req_validator_bad_signature',
    ],
    $method,
    $path,
    $rawBody,
    $token,
    $secret,
    $tenantCode,
    function () {
        return false;
    }
);
$assert(empty($invalidSignature['success']) && $invalidSignature['code'] === 'INVALID_SIGNATURE', 'Expected invalid signature to be rejected.');

$replay = $validator->validate(
    [
        'Authorization' => 'Bearer ' . $token,
        'X-Timestamp' => $timestamp,
        'X-Signature' => $signature,
        'X-Tenant-Code' => $tenantCode,
        'X-Request-Id' => 'req_validator_replay',
    ],
    $method,
    $path,
    $rawBody,
    $token,
    $secret,
    $tenantCode,
    function () {
        return true;
    }
);
$assert(empty($replay['success']) && $replay['code'] === 'REPLAY_BLOCKED', 'Expected replay request to be blocked.');

fwrite(STDOUT, "TenantContextRequestValidatorTest passed." . PHP_EOL);
