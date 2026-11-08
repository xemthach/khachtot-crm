<?php

$root = dirname(__DIR__);
$api = file_get_contents($root.'/controllers/Kt_integration_api.php');
$routes = file_get_contents($root.'/config/routes.php');
$store = file_get_contents($root.'/libraries/DmsIntegrationContractStore.php');
$product = file_get_contents($root.'/libraries/DmsProductWritebackService.php');
$model = file_get_contents($root.'/models/Kt_integration_model.php');
$setup = file_get_contents($root.'/views/admin/dms/setup_center.php');

foreach (['dms_management_health', 'dms_mapping_summary', 'dms_retry_sync', 'dms_product_writeback'] as $method) {
    if (strpos($api, 'function '.$method) === false) throw new RuntimeException('Missing signed API method '.$method);
}
foreach (['integration/dms/health', 'integration/dms/mappings', 'integration/dms/retry', 'integration/dms/products/writeback'] as $route) {
    if (strpos($routes, $route) === false) throw new RuntimeException('Missing route '.$route);
}
foreach (['dms_integration_requests', 'dms_sync_health_ledger', 'dms_mapping_issues', 'tenant_idempotency', 'payload_checksum'] as $contract) {
    if (strpos($store, $contract) === false) throw new RuntimeException('Missing durable contract '.$contract);
}
foreach (['CRM_PRODUCT_WRITEBACK_DISABLED', 'product_writeback_enabled', "db_prefix().'items'"] as $contract) {
    if (strpos($product.$model.$setup, $contract) === false) throw new RuntimeException('Missing product contract '.$contract);
}
if (stripos($product, 'itemable') !== false) throw new RuntimeException('Product writeback must not access invoice item snapshots.');
foreach (['TENANT_SCOPE_MISMATCH', 'IDEMPOTENCY_KEY_REUSED', 'DATA_CORRECTION_REQUIRED'] as $code) {
    if (strpos($api.$store, $code) === false) throw new RuntimeException('Missing stable error '.$code);
}

echo "M8CrmDmsContractImplementationTest passed.\n";
