<?php

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/controllers/Kt_integration_hub.php');
$model = file_get_contents($root . '/models/Kt_integration_model.php');
$menu = file_get_contents($root . '/kt_integration_hub.php');
$licensing = file_get_contents(dirname($root) . '/kt_saas/services/LicensingService.php');

$requiredControllerMethods = [
    'dms_landlord_dashboard',
    'dms_landlord_subscriptions',
    'dms_landlord_tenant',
    'dms_landlord_update_status',
    'dms_landlord_change_package',
    'dms_landlord_save_overrides',
    'requirePostRequest',
];

foreach ($requiredControllerMethods as $method) {
    if (strpos($controller, 'function ' . $method . '(') === false) {
        throw new RuntimeException('Missing landlord controller method: ' . $method);
    }
}

foreach ([
    'dms_landlord_update_status',
    'dms_landlord_change_package',
    'dms_landlord_save_overrides',
] as $mutationMethod) {
    $pos = strpos($controller, 'function ' . $mutationMethod . '(');
    $body = substr($controller, $pos, 900);
    if (strpos($body, '$this->requirePostRequest();') === false) {
        throw new RuntimeException('Mutation method does not require POST: ' . $mutationMethod);
    }
}

foreach ([
    'get_landlord_dms_tenant_subscriptions',
    'get_landlord_dms_fleet_dashboard',
    'get_landlord_dms_tenant_detail',
    'landlord_update_dms_addon_status',
    'landlord_change_dms_package',
    'save_landlord_dms_tenant_overrides',
    'record_landlord_dms_action',
    'kt_dms_tenant_overrides',
    'kt_dms_landlord_actions',
] as $needle) {
    if (strpos($model, $needle) === false) {
        throw new RuntimeException('Missing landlord model capability: ' . $needle);
    }
}

foreach ([
    'views/admin/dms_landlord/tenant_subscriptions.php',
    'views/admin/dms_landlord/tenant_detail.php',
    'views/admin/dms_landlord/dashboard.php',
] as $view) {
    if (!is_file($root . '/' . $view)) {
        throw new RuntimeException('Missing landlord DMS view: ' . $view);
    }
}

$detailView = file_get_contents($root . '/views/admin/dms_landlord/tenant_detail.php');
foreach (['dms_landlord_change_package', 'dms_landlord_update_status', 'dms_landlord_save_overrides', 'form_open'] as $needle) {
    if (strpos($detailView, $needle) === false) {
        throw new RuntimeException('Landlord detail view missing POST form capability: ' . $needle);
    }
}

foreach (['kt_dms_landlord_dashboard', 'Sức khỏe CRM ↔ DMS', 'kt_dms_landlord_tenants', 'Tenant DMS', 'Vận hành DMS'] as $needle) {
    if (strpos($menu, $needle) === false) {
        throw new RuntimeException('Landlord DMS menu missing: ' . $needle);
    }
}

foreach (['getTenantDmsOverrides', 'ORDER_MONTHLY_LIMIT', 'API_WEBHOOK_LIMIT'] as $needle) {
    if (strpos($licensing, $needle) === false) {
        throw new RuntimeException('Licensing payload does not include landlord override/effective quota support: ' . $needle);
    }
}

echo "DmsLandlordManagementSmokeTest passed.\n";
