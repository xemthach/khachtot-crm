<?php

$model = file_get_contents(dirname(__DIR__) . '/models/Kt_integration_model.php');
$view = file_get_contents(dirname(__DIR__) . '/views/admin/dms/setup_center.php');
$workspace = dirname(dirname(dirname(dirname(__DIR__))));
$salesrepPreset = file_get_contents($workspace . '/salesrep/app/Support/DmsRolePreset.php');
$salesrepSeeder = file_get_contents($workspace . '/salesrep/database/seeders/DynamicPermissionSeeder.php');
$salesrepStaffSync = file_get_contents($workspace . '/salesrep/app/Services/Integration/StaffSyncService.php');

foreach (["'tenant_admin', 'regional_manager', 'field_sales', 'finance'", "'finance_accounting'", 'Quản trị workspace', 'Quản lý sales'] as $needle) {
    if (strpos($model . $view . $salesrepPreset . $salesrepStaffSync, $needle) === false) {
        throw new RuntimeException('Canonical role contract missing: ' . $needle);
    }
}
if (strpos($salesrepSeeder, 'Canonical DMS role presets are the final authority') === false) {
    throw new RuntimeException('DynamicPermissionSeeder does not enforce canonical presets last.');
}
if (strpos($salesrepPreset, "self::FINANCE => ['view_orders']") === false) {
    throw new RuntimeException('Finance preset must remain read-only.');
}
require_once $workspace . '/salesrep/app/Support/DmsRolePreset.php';
$managerPermissions = \App\Support\DmsRolePreset::permissions(\App\Support\DmsRolePreset::SALES_MANAGER);
$financePermissions = \App\Support\DmsRolePreset::permissions(\App\Support\DmsRolePreset::FINANCE);
if (in_array('create_users', $managerPermissions, true) || in_array('manage_settings', $managerPermissions, true)) {
    throw new RuntimeException('Sales Manager preset contains tenant-admin permissions.');
}
if ($financePermissions !== ['view_orders']) {
    throw new RuntimeException('Finance preset is not read-only.');
}

echo "DmsPermissionMatrixSmokeTest passed.\n";
