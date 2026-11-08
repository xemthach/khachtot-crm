<?php

$root = dirname(__DIR__);
$model = file_get_contents($root . '/models/Kt_integration_model.php');
$control = file_get_contents($root . '/views/admin/dms_landlord/control_center.php');
$map = file_get_contents($root . '/views/admin/dms/map_activity.php');
$wizard = file_get_contents($root . '/views/admin/dms/setup_wizard.php');

foreach (['keyless', 'google', 'disabled', 'map_api_key_configured', 'managed_by'] as $contract) {
    if (strpos($model . $control, $contract) === false) {
        throw new RuntimeException('Missing map config contract: ' . $contract);
    }
}
foreach (['data-r25-map-provider', 'Chưa có vị trí để hiển thị trên bản đồ', 'Cấu hình bản đồ do nền tảng quản lý'] as $contract) {
    if (strpos($map . $wizard, $contract) === false) {
        throw new RuntimeException('Missing tenant map state: ' . $contract);
    }
}
if (strpos($control, 'value="<?php echo') !== false && strpos($control, 'map_api_key') < strpos($control, 'value="<?php echo')) {
    throw new RuntimeException('Map key must never be rendered back into the form.');
}

echo "DmsMapProviderConfigSmokeTest passed.\n";
