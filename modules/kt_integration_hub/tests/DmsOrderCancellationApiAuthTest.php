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
$path = '/api/v1/dms/orders/33001/cancel';
$payload = json_encode([
    'tenant' => ['tenant_code' => 'ABC', 'crm_tenant_id' => 1001, 'crm_company_id' => 1001],
    'order' => ['order_id' => 33001, 'crm_invoice_id' => 88001, 'status' => 'cancelled'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$invalidSignatureHeaders = [
    'Authorization' => 'Bearer bridge-token',
    'X-Timestamp' => (string) round(microtime(true) * 1000),
    'X-Tenant-Code' => 'ABC',
    'X-Request-Id' => 'req-order-cancel-auth-1',
    'X-Idempotency-Key' => 'cancel-order-33001-2026-06-09T19:30:00+07:00',
    'X-Signature' => str_repeat('x', 64),
];

$invalidSignature = $validator->validate(
    $invalidSignatureHeaders,
    'POST',
    $path,
    $payload,
    'bridge-token',
    'bridge-secret',
    'ABC',
    static fn () => false,
    true
);

if (!empty($invalidSignature['success']) || ($invalidSignature['code'] ?? null) !== 'INVALID_SIGNATURE') {
    throw new RuntimeException('Expected INVALID_SIGNATURE for tampered DMS order cancellation request.');
}

$tenantMismatchHeaders = [
    'Authorization' => 'Bearer bridge-token',
    'X-Timestamp' => (string) round(microtime(true) * 1000),
    'X-Tenant-Code' => 'XYZ',
    'X-Request-Id' => 'req-order-cancel-auth-2',
    'X-Idempotency-Key' => 'cancel-order-33001-2026-06-09T19:31:00+07:00',
];
$tenantMismatchHeaders['X-Signature'] = $validator->sign('POST', $path, $tenantMismatchHeaders['X-Timestamp'], 'XYZ', $payload, 'bridge-secret');

$tenantMismatch = $validator->validate(
    $tenantMismatchHeaders,
    'POST',
    $path,
    $payload,
    'bridge-token',
    'bridge-secret',
    'ABC',
    static fn () => false,
    true
);

if (!empty($tenantMismatch['success']) || ($tenantMismatch['code'] ?? null) !== 'TENANT_SCOPE_MISMATCH') {
    throw new RuntimeException('Expected TENANT_SCOPE_MISMATCH for wrong tenant code on DMS order cancellation.');
}

echo "DmsOrderCancellationApiAuthTest passed.\n";
