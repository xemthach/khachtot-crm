<?php defined('BASEPATH') or define('BASEPATH', __DIR__);
$model = file_get_contents(dirname(__DIR__) . '/models/Kt_integration_model.php');
foreach (['get_dms_sync_scope_settings', 'save_dms_sync_scope_settings', 'selected_only', 'all_eligible', 'disabled'] as $needle) {
    if (strpos($model, $needle) === false) throw new RuntimeException('Missing sync governance marker: ' . $needle);
}
$controller = file_get_contents(dirname(__DIR__) . '/controllers/Kt_integration_hub.php');
foreach (['dms_save_sync_scope', 'requireTenantContext', 'kt_integration_hub_connect', 'requirePostRequest'] as $needle) {
    if (strpos($controller, $needle) === false) throw new RuntimeException('Missing tenant-owned sync route marker: ' . $needle);
}
$view = file_get_contents(dirname(__DIR__) . '/views/admin/dms/setup_center.php');
foreach (['data-r31-tenant-sync-scope', 'Lưu phạm vi đồng bộ', 'Tenant Admin'] as $needle) {
    if (strpos($view, $needle) === false) throw new RuntimeException('Missing sync scope UI marker: ' . $needle);
}
echo "DmsTenantDataSyncGovernanceTest passed.\n";
