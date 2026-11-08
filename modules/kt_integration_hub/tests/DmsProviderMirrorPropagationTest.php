<?php

$root = dirname(__DIR__);
$api = file_get_contents($root . '/controllers/Kt_integration_api.php');
$model = file_get_contents($root . '/models/Kt_integration_model.php');
$routes = file_get_contents($root . '/config/routes.php');
$licensing = file_get_contents(dirname($root) . '/kt_saas/services/LicensingService.php');

if (strpos($routes, "api/v1/integration/platform/providers/mirror") === false) {
    throw new RuntimeException('Platform provider mirror route is missing.');
}
if (strpos($api, 'platform_provider_mirror') === false || strpos($api, 'validatePlatformProviderMirrorRequest') === false) {
    throw new RuntimeException('Signed platform provider mirror receiver is missing.');
}
if (strpos($api, "PLATFORM_PROVIDER_MIRROR_PATH") === false || strpos($api, "api.platform_provider_mirror.accepted") === false) {
    throw new RuntimeException('Platform provider mirror request contract is incomplete.');
}
foreach ([
    'kt_dms_maps_runtime_browser_key_encrypted',
    'kt_dms_google_maps_api_key_encrypted',
    'kt_dms_platform_config_json',
    'salesrep_platform_signed_mirror',
    'bump_dms_config_version',
] as $contract) {
    if (strpos($model, $contract) === false) {
        throw new RuntimeException('CRM provider mirror model contract missing: ' . $contract);
    }
}
if (strpos($licensing, 'kt_dms_maps_runtime_browser_key_encrypted') === false
    || strpos($licensing, 'kt_dms_google_maps_api_key_encrypted') === false) {
    throw new RuntimeException('Licensing service does not use canonical Google Maps key with compatibility fallback.');
}

echo "DmsProviderMirrorPropagationTest passed.\n";
