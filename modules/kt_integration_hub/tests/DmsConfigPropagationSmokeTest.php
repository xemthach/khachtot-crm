<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);
if (!function_exists('db_prefix')) {
    function db_prefix(): string { return ''; }
}
if (!function_exists('get_option')) {
    function get_option($key) {
        if ($key === 'kt_saas_dms_config_version') return '42';
        if ($key === 'kt_dms_platform_config_json') return json_encode(['map_provider' => 'keyless', 'sso_enabled' => true]);
        return '';
    }
}
class TenantEntitlementService {}
require_once dirname(__DIR__) . '/../kt_saas/services/LicensingService.php';

$service = new LicensingService();
$payload = $service->buildTenantLicensingPayload(
    ['id' => 30, 'tenant_code' => 'T30', 'company_name' => 'Tenant 30', 'status' => 'active'],
    ['status' => 'active', 'plan_code' => 'CRM'],
    ['module_codes' => ['dms']],
    new TenantEntitlementService(),
    [
        'status' => 'active',
        'addon' => ['billing_cycle' => 'quarterly'],
        'package' => [
            'package_code' => 'dms_standard',
            'allowed_billing_periods' => ['monthly', 'quarterly', 'yearly'],
            'default_billing_period' => 'monthly',
            'features' => ['DMS_ENABLED' => true, 'MAP_ENABLED' => true, 'SSO_ENABLED' => true],
            'user_limit' => 20,
        ],
    ]
);

if (empty($payload['features']['DMS_ENABLED']) || ($payload['config_version'] ?? 0) !== 42) {
    throw new RuntimeException('Active effective config did not propagate.');
}
if (($payload['dms_billing_cycle'] ?? '') !== 'quarterly' || !in_array('quarterly', $payload['allowed_billing_periods'] ?? [], true)) {
    throw new RuntimeException('Billing period contract did not propagate.');
}
if (($payload['map']['managed_by'] ?? '') !== 'platform' || ($payload['sso']['managed_by'] ?? '') !== 'platform') {
    throw new RuntimeException('Platform ownership contract is missing.');
}

echo "DmsConfigPropagationSmokeTest passed.\n";
