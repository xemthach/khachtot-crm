<?php

$root = dirname(__DIR__);
$model = file_get_contents($root.'/models/Kt_integration_model.php');
$licensing = file_get_contents(dirname($root).'/kt_saas/services/LicensingService.php');
$versioning = file_get_contents(dirname($root).'/kt_saas/models/Kt_saas_model.php');
$controller = file_get_contents($root.'/controllers/Kt_integration_hub.php');
$tenantMap = file_get_contents($root.'/views/admin/dms/map_activity.php');

foreach (['map_validation_state', 'allowed_domains', 'sso_timeout_seconds', 'retention_months', 'platform_config_saved'] as $contract) {
    if (strpos($model, $contract) === false) throw new RuntimeException('Missing global save contract: '.$contract);
}
foreach (['disabled_by_package', 'missing_platform_config', 'platform_config', 'config_generated_at'] as $contract) {
    if (strpos($licensing, $contract) === false) throw new RuntimeException('Missing effective config contract: '.$contract);
}
if (strpos($versioning, 'kt_saas_dms_config_generated_at') === false) throw new RuntimeException('Version timestamp is not persisted.');
if (strpos($controller, 'effectiveTenantMapConfig') === false) throw new RuntimeException('CRM tenant map does not use the effective resolver.');
if (strpos($tenantMap, 'Bản đồ do Khách Tốt Platform quản lý') === false) throw new RuntimeException('Safe tenant map status is missing.');
foreach (['google_maps_raw_key', 'bridge_secret', 'payment_secret'] as $secret) {
    if (preg_match("/'".preg_quote($secret, '/')."'\s*=>/", $licensing)) throw new RuntimeException('Secret field exposed: '.$secret);
}

echo "DmsGlobalConfigPropagationHardeningTest passed.\n";
