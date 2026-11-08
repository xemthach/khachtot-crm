<?php defined('BASEPATH') or define('BASEPATH', __DIR__);
$model = file_get_contents(dirname(__DIR__) . '/models/Kt_integration_model.php');
foreach (['get_dms_support_override_preview', 'run_dms_support_override', 'support_override_', 'reason', 'confirm'] as $needle) {
    if (strpos($model, $needle) === false) throw new RuntimeException('Missing support override marker: ' . $needle);
}
$view = file_get_contents(dirname(__DIR__) . '/views/admin/dms_landlord/tenant_detail.php');
foreach (['data-r31-support-override-preview', 'support_reason', 'confirm_preview', 'Không phá dữ liệu'] as $needle) {
    if (strpos($view, $needle) === false) throw new RuntimeException('Missing support override UI marker: ' . $needle);
}
echo "DmsSupportOverridePolicyTest passed.\n";
