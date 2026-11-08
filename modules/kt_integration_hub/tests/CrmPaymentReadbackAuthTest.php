<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);
defined('KT_INTEGRATION_HUB_MODULE') or define('KT_INTEGRATION_HUB_MODULE', 'kt_integration_hub');
if (!function_exists('module_dir_path')) {
    function module_dir_path($module, $concat = '')
    {
        return dirname(__DIR__) . '/' . ltrim((string) $concat, '/');
    }
}

require_once dirname(__DIR__) . '/libraries/KtIntegrationSignedRequestValidator.php';

$validator = new KtIntegrationSignedRequestValidator();
$path = '/api/v1/crm/payments/1001';

$invalidSignatureHeaders = [
    'Authorization' => 'Bearer bridge-token',
    'X-Timestamp' => (string) round(microtime(true) * 1000),
    'X-Tenant-Code' => 'ABC',
    'X-Request-Id' => 'req-payment-readback-invalid-signature',
    'X-Signature' => str_repeat('z', 64),
];

$invalidSignature = $validator->validate(
    $invalidSignatureHeaders,
    'GET',
    $path,
    '',
    'bridge-token',
    'bridge-secret',
    'ABC',
    static fn () => false
);

if (!empty($invalidSignature['success']) || ($invalidSignature['code'] ?? null) !== 'INVALID_SIGNATURE') {
    throw new RuntimeException('Expected INVALID_SIGNATURE for tampered payment readback request.');
}

$tenantMismatchHeaders = [
    'Authorization' => 'Bearer bridge-token',
    'X-Timestamp' => (string) round(microtime(true) * 1000),
    'X-Tenant-Code' => 'XYZ',
    'X-Request-Id' => 'req-payment-readback-tenant-mismatch',
];
$tenantMismatchHeaders['X-Signature'] = $validator->sign('GET', $path, $tenantMismatchHeaders['X-Timestamp'], 'XYZ', '', 'bridge-secret');

$tenantMismatch = $validator->validate(
    $tenantMismatchHeaders,
    'GET',
    $path,
    '',
    'bridge-token',
    'bridge-secret',
    'ABC',
    static fn () => false
);

if (!empty($tenantMismatch['success']) || ($tenantMismatch['code'] ?? null) !== 'TENANT_SCOPE_MISMATCH') {
    throw new RuntimeException('Expected TENANT_SCOPE_MISMATCH for wrong tenant code on payment readback.');
}

echo "CrmPaymentReadbackAuthTest passed.\n";
