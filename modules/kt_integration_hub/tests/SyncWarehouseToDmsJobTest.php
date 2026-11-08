<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);
defined('KT_INTEGRATION_HUB_MODULE') or define('KT_INTEGRATION_HUB_MODULE', 'kt_integration_hub');

$jobSource = file_get_contents(dirname(__DIR__) . '/libraries/SyncWarehouseToDmsJob.php');
$modelSource = file_get_contents(dirname(__DIR__) . '/models/Kt_integration_model.php');

foreach ([
    '/api/v1/integration/warehouses/upsert',
    'X-Idempotency-Key',
    'X-Signature',
    'sync_warehouse_to_dms',
] as $needle) {
    if (strpos($jobSource . $modelSource, $needle) === false) {
        throw new RuntimeException('Expected warehouse sync job contract token: ' . $needle);
    }
}

foreach (['inventory_ledger_entries', 'stock_balance', 'stock_quantity', 'StoreProduct'] as $forbidden) {
    if (strpos($jobSource, $forbidden) !== false) {
        throw new RuntimeException('Warehouse catalog job must not reference inventory mutation token: ' . $forbidden);
    }
}

echo "SyncWarehouseToDmsJobTest passed.\n";
