<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

$controllerSource = file_get_contents(dirname(__DIR__) . '/controllers/Kt_saas.php');
$modelSource = file_get_contents(dirname(__DIR__) . '/models/Kt_saas_model.php');
$appViewSource = file_get_contents(dirname(__DIR__) . '/views/tenant/marketplace_app.php');
$listViewSource = file_get_contents(dirname(__DIR__) . '/views/tenant/marketplace.php');

if ($controllerSource === false || $modelSource === false || $appViewSource === false || $listViewSource === false) {
    throw new RuntimeException('Unable to read Marketplace DMS sources.');
}

foreach ([
    'get_dms_addon_package_catalog',
    'upsert_tenant_dms_addon',
    'resolve_tenant_dms_addon_context',
    'create_or_refresh_tenant_dms_addon_invoice',
    'activate_tenant_dms_addon_from_invoice',
    'DMS_SUBSCRIPTION_STATUS',
    'Chọn gói DMS',
] as $needle) {
    if (strpos($controllerSource . $modelSource . $appViewSource . $listViewSource, $needle) === false) {
        throw new RuntimeException('Expected paid add-on Marketplace marker not found: ' . $needle);
    }
}

if (strpos($modelSource, 'install_salesflow_dms_marketplace_app') === false) {
    throw new RuntimeException('Expected install_salesflow_dms_marketplace_app to exist.');
}

$installSlice = substr($modelSource, strpos($modelSource, 'install_salesflow_dms_marketplace_app'), 2600);
if ($installSlice !== false && strpos($installSlice, 'save_tenant_feature_override') !== false) {
    throw new RuntimeException('Legacy free-install feature override path is still present inside install_salesflow_dms_marketplace_app.');
}

foreach ([
    'Xem thanh toán',
    'Gói DMS hiện tại',
    'DMS là dịch vụ bổ sung cho CRM',
] as $needle) {
    if (strpos($appViewSource, $needle) === false) {
        throw new RuntimeException('Expected Marketplace purchase lifecycle marker not found on app page: ' . $needle);
    }
}

if (strpos($appViewSource, 'Activate in dev') !== false) {
    throw new RuntimeException('Dev activation helper should no longer be exposed on the DMS package page.');
}

echo "DmsMarketplacePaidAddonSmokeTest passed.\n";
