<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);
$platformConfig = [];
$keyConfigured = false;
if (!function_exists('db_prefix')) { function db_prefix(): string { return ''; } }
if (!function_exists('get_option')) {
    function get_option($key) {
        global $platformConfig, $keyConfigured;
        if ($key === 'kt_saas_dms_config_version') return '52';
        if ($key === 'kt_saas_dms_config_generated_at') return '2026-06-28T10:00:00Z';
        if ($key === 'kt_dms_platform_config_json') return json_encode($platformConfig);
        if ($key === 'kt_dms_google_maps_api_key_encrypted') return $keyConfigured ? 'encrypted-present' : '';
        return '';
    }
}
class TenantEntitlementService {}
require_once dirname(__DIR__).'/../kt_saas/services/LicensingService.php';

$build = static function (array $global, bool $packageEnabled, bool $hasKey = false): array {
    global $platformConfig, $keyConfigured;
    $platformConfig = $global;
    $keyConfigured = $hasKey;
    return (new LicensingService())->buildTenantLicensingPayload(
        ['id' => 30, 'tenant_code' => 'T30', 'company_name' => 'Tenant 30', 'status' => 'active'],
        ['status' => 'active', 'plan_code' => 'CRM'], ['module_codes' => ['dms']], new TenantEntitlementService(),
        ['status' => 'active', 'addon' => [], 'package' => ['package_code' => 'dms_test', 'features' => ['DMS_ENABLED' => true, 'MAP_ENABLED' => $packageEnabled, 'SSO_ENABLED' => true]]]
    );
};

$cases = [
    ['payload' => $build(['map_provider' => 'keyless', 'map_enabled' => true], true), 'status' => 'ready'],
    ['payload' => $build(['map_provider' => 'google', 'map_enabled' => true], true, true), 'status' => 'ready'],
    ['payload' => $build(['map_provider' => 'google', 'map_enabled' => true], true, false), 'status' => 'missing_platform_config'],
    ['payload' => $build(['map_provider' => 'disabled', 'map_enabled' => false], true), 'status' => 'disabled_by_platform'],
    ['payload' => $build(['map_provider' => 'keyless', 'map_enabled' => true], false), 'status' => 'disabled_by_package'],
];
foreach ($cases as $case) {
    if (($case['payload']['map']['status'] ?? '') !== $case['status']) throw new RuntimeException('Unexpected map state: '.$case['status']);
    if (isset($case['payload']['google_maps_raw_key']) || isset($case['payload']['bridge_secret'])) throw new RuntimeException('Secret leaked into payload.');
}
echo "DmsMapEffectiveConfigMatrixTest passed.\n";
