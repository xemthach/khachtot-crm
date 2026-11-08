<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

$setupCenter = file_get_contents(dirname(__DIR__) . '/views/admin/dms/setup_center.php');
$syncHealth = file_get_contents(dirname(__DIR__) . '/views/admin/dms/sync_health.php');
$contractStore = file_get_contents(dirname(__DIR__) . '/libraries/DmsIntegrationContractStore.php');

foreach ([
    'Danh mục kho đã đồng bộ',
    'Cần cấu hình kho phục vụ điểm bán',
    'Phiên bản hiện tại chỉ đồng bộ danh mục kho, không đồng bộ tồn kho',
    'warehouse_catalog_mappings',
    'WAREHOUSE_CATALOG_READY',
] as $needle) {
    if (strpos($setupCenter . $syncHealth . $contractStore, $needle) === false) {
        throw new RuntimeException('Expected warehouse catalog readiness wording/token: ' . $needle);
    }
}

foreach (['tồn kho đã đồng bộ', 'ledger đã đồng bộ'] as $forbidden) {
    if (strpos($setupCenter . $syncHealth, $forbidden) !== false) {
        throw new RuntimeException('Tenant UI must not claim inventory sync: ' . $forbidden);
    }
}

echo "DmsWarehouseCatalogSyncSmokeTest passed.\n";
