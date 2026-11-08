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
$headers = [
    'Authorization' => 'Bearer bridge-token',
    'X-Timestamp' => (string) round(microtime(true) * 1000),
    'X-Tenant-Code' => 'ABC',
    'X-Request-Id' => 'req-order-auth-1',
    'X-Idempotency-Key' => 'order-33001-2026-06-09T10:01:00+07:00',
    'X-Signature' => str_repeat('x', 64),
];

$result = $validator->validate(
    $headers,
    'POST',
    '/api/v1/dms/orders',
    json_encode(['tenant' => ['tenant_code' => 'ABC'], 'order' => ['order_id' => 33001]]),
    'bridge-token',
    'bridge-secret',
    'ABC',
    static fn () => false,
    true
);

if (!empty($result['success'])) {
    throw new RuntimeException('Expected invalid signature validation to fail.');
}

if (($result['code'] ?? null) !== 'INVALID_SIGNATURE') {
    throw new RuntimeException('Expected INVALID_SIGNATURE for tampered DMS order bridge request.');
}

echo "DmsOrderApiAuthTest passed.\n";
