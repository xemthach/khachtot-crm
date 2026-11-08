<?php

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/controllers/Kt_integration_hub.php');
$model = file_get_contents($root . '/models/Kt_integration_model.php');
$detailView = file_get_contents($root . '/views/admin/dms_landlord/tenant_detail.php');
$licensing = file_get_contents(dirname($root) . '/kt_saas/services/LicensingService.php');

$assertContains = static function (string $haystack, string $needle, string $message): void {
    if (strpos($haystack, $needle) === false) {
        throw new RuntimeException($message);
    }
};

foreach ([
    'dms_landlord_update_status',
    'dms_landlord_change_package',
    'dms_landlord_save_overrides',
] as $method) {
    $pos = strpos($controller, 'function ' . $method . '(');
    if ($pos === false) {
        throw new RuntimeException('Missing landlord POST method: ' . $method);
    }
    $body = substr($controller, $pos, 1000);
    $assertContains($body, '$this->requirePostRequest();', 'Landlord mutation must require POST: ' . $method);
    $assertContains($body, '$this->requireLandlord();', 'Landlord mutation must require landlord runtime: ' . $method);
    $assertContains($body, "requireCapability('kt_integration_hub_manage')", 'Landlord mutation must require manage capability: ' . $method);
}

foreach (['confirm_change', 'status', 'package_code', 'features[', 'quota[', 'form_open'] as $needle) {
    $assertContains($detailView, $needle, 'Landlord detail view missing safe action/form field: ' . $needle);
}

foreach ([
    'record_landlord_dms_action',
    'actor_user_id',
    'actor_email',
    'before_json',
    'after_json',
    'user_agent',
] as $needle) {
    $assertContains($model, $needle, 'Landlord audit log field/capability missing: ' . $needle);
}

foreach (['getTenantDmsOverrides', 'ORDER_MONTHLY_LIMIT', 'API_WEBHOOK_LIMIT'] as $needle) {
    $assertContains($licensing, $needle, 'Effective licensing proof support missing: ' . $needle);
}

echo "DmsLandlordPostActionRuntimeSmokeTest passed.\n";

