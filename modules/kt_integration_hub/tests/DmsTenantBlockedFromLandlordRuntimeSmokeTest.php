<?php

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/controllers/Kt_integration_hub.php');

$assertContains = static function (string $haystack, string $needle, string $message): void {
    if (strpos($haystack, $needle) === false) {
        throw new RuntimeException($message);
    }
};

foreach ([
    'dms_landlord_dashboard',
    'dms_landlord_subscriptions',
    'dms_landlord_tenant',
    'dms_landlord_update_status',
    'dms_landlord_change_package',
    'dms_landlord_save_overrides',
] as $method) {
    $pos = strpos($controller, 'function ' . $method . '(');
    if ($pos === false) {
        throw new RuntimeException('Missing landlord route method: ' . $method);
    }

    $body = substr($controller, $pos, 1200);
    $assertContains($body, '$this->requireLandlord();', 'Landlord route must block tenant sessions: ' . $method);
    $assertContains($body, "requireCapability('kt_integration_hub_manage')", 'Landlord route must require manage capability: ' . $method);

    if (in_array($method, [
        'dms_landlord_update_status',
        'dms_landlord_change_package',
        'dms_landlord_save_overrides',
    ], true)) {
        $assertContains($body, '$this->requirePostRequest();', 'Landlord mutation must require POST: ' . $method);
    }
}

echo "DmsTenantBlockedFromLandlordRuntimeSmokeTest passed.\n";

