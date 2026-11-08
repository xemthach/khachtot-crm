<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);
defined('KT_INTEGRATION_HUB_MODULE') or define('KT_INTEGRATION_HUB_MODULE', 'kt_integration_hub');

require_once dirname(__DIR__) . '/helpers/kt_integration_hub_helper.php';
require_once dirname(__DIR__) . '/libraries/ProductPayloadProducer.php';

$producer = new ProductPayloadProducer();

$tenant = [
    'id' => 1001,
    'landlord_client_id' => 2001,
    'tenant_code' => 'ABC',
];

$item = [
    'id' => 7001,
    'description' => 'Coca Cola 330ml',
    'long_description' => 'Mô tả sản phẩm chuẩn từ CRM',
    'rate' => '12000.00',
    'unit' => 'lon',
    '_bridge_updated_at' => '2026-06-09 09:00:00',
];

$inventory = [
    'id' => 501,
    'item_id' => 7001,
    'sku' => 'SKU-COCA-330',
    'barcode' => '8938501432000',
    'is_active' => 1,
    'updated_at' => '2026-06-09 09:00:00',
];

$primaryBarcode = [
    'barcode' => '8938501432100',
    'unit_type' => 'CAN-330',
];

$payload = $producer->buildPayload($tenant, $item, $inventory, $primaryBarcode);

if (($payload['tenant']['crm_tenant_id'] ?? null) !== 1001) {
    throw new RuntimeException('Expected tenant id to be mapped into product payload.');
}

if (($payload['product']['crm_product_id'] ?? null) !== 7001) {
    throw new RuntimeException('Expected crm_product_id to match tblitems.id.');
}

if (($payload['product']['sku'] ?? null) !== 'SKU-COCA-330') {
    throw new RuntimeException('Expected SKU to come from kt_inventory_items.sku.');
}

if (($payload['product']['variant_code'] ?? null) !== 'CAN-330') {
    throw new RuntimeException('Expected variant_code to come from primary barcode unit_type.');
}

if (($payload['product']['barcode'] ?? null) !== '8938501432100') {
    throw new RuntimeException('Expected primary barcode to override inventory barcode.');
}

if (($payload['product']['description'] ?? null) !== 'Mô tả sản phẩm chuẩn từ CRM') {
    throw new RuntimeException('Expected shared description to come from tblitems.long_description.');
}

$payloadWithoutInventory = $producer->buildPayload($tenant, $item, null, null);
if (($payloadWithoutInventory['product']['sku'] ?? null) !== 'CRM-ITEM-7001') {
    throw new RuntimeException('Expected fallback SKU when inventory row is absent.');
}

try {
    $producer->buildPayload($tenant, [], null, null);
    throw new RuntimeException('Expected invalid product payload to throw.');
} catch (RuntimeException $exception) {
    if (strpos($exception->getMessage(), 'valid product id') === false) {
        throw $exception;
    }
}

echo "ProductPayloadProducerTest passed.\n";
