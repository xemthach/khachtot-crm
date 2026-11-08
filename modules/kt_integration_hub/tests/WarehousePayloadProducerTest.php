<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);
defined('KT_INTEGRATION_HUB_MODULE') or define('KT_INTEGRATION_HUB_MODULE', 'kt_integration_hub');

require_once dirname(__DIR__) . '/helpers/kt_integration_hub_helper.php';
require_once dirname(__DIR__) . '/libraries/WarehousePayloadProducer.php';

$producer = new WarehousePayloadProducer();

$payload = $producer->buildPayload([
    'id' => 31,
    'landlord_client_id' => 41,
    'tenant_code' => 'TENANT31',
], [
    'id' => 9001,
    'warehouse_code' => 'MAIN',
    'warehouse_name' => 'Kho trung tâm',
    'address' => '1 Nguyen Trai',
    'is_active' => 1,
    'updated_at' => '2026-07-11 10:00:00',
]);

if (($payload['tenant']['crm_tenant_id'] ?? null) !== 31) {
    throw new RuntimeException('Expected tenant id in warehouse payload.');
}

if (($payload['warehouse']['crm_warehouse_id'] ?? null) !== 9001) {
    throw new RuntimeException('Expected crm_warehouse_id to match kt_warehouses.id.');
}

if (($payload['warehouse']['warehouse_code'] ?? null) !== 'MAIN') {
    throw new RuntimeException('Expected warehouse_code in payload.');
}

foreach (['stock_balance', 'stock_quantity', 'quantity', 'cost', 'ledger_movement', 'receipt_quantity', 'issue_quantity', 'transfer_quantity'] as $forbiddenKey) {
    if (array_key_exists($forbiddenKey, $payload['warehouse'])) {
        throw new RuntimeException('Warehouse catalog payload must not contain inventory key: ' . $forbiddenKey);
    }
}

$key = $producer->buildIdempotencyKey($payload);
if (strpos($key, 'warehouse-catalog-v1-9001-') !== 0) {
    throw new RuntimeException('Expected warehouse catalog idempotency key.');
}

echo "WarehousePayloadProducerTest passed.\n";
