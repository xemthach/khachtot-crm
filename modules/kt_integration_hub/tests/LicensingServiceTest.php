<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);
defined('KT_INTEGRATION_HUB_MODULE') or define('KT_INTEGRATION_HUB_MODULE', 'kt_integration_hub');

if (! function_exists('db_prefix')) {
    function db_prefix(): string
    {
        return '';
    }
}

if (! function_exists('module_dir_path')) {
    function module_dir_path($module, $concat = '')
    {
        return dirname(__DIR__) . '/' . ltrim((string) $concat, '/');
    }
}

class TenantEntitlementService
{
    public function getFeatureValue($tenantId, $featureKey, $default = null)
    {
        $map = [
            'dms.enabled' => true,
            'dms.checkin_enabled' => true,
            'dms.visit_enabled' => false,
            'dms.live_tracking_enabled' => true,
            'dms.route_analytics_enabled' => true,
            'dms.notification_bridge_enabled' => true,
            'dms.sso_enabled' => true,
        ];

        return $map[$featureKey] ?? $default;
    }
}

require_once dirname(__DIR__) . '/../kt_saas/services/LicensingService.php';

$service = new LicensingService();
$tenant = [
    'id' => 1001,
    'tenant_code' => 'ABC',
    'company_name' => 'ABC Company',
    'status' => 'active',
];
$subscription = [
    'status' => 'active',
    'plan_code' => 'SME_PLUS',
];
$profile = [
    'module_codes' => ['crm', 'dms'],
];

$payload = $service->buildTenantLicensingPayload($tenant, $subscription, $profile, new TenantEntitlementService());

if (($payload['crm_tenant_id'] ?? null) !== 1001) {
    throw new RuntimeException('Expected crm_tenant_id to equal 1001.');
}

if (($payload['features']['DMS_ENABLED'] ?? null) !== false) {
    throw new RuntimeException('Expected DMS_ENABLED to remain false without an active DMS add-on.');
}

if (($payload['features']['VISIT_ENABLED'] ?? null) !== false) {
    throw new RuntimeException('Expected VISIT_ENABLED to be false.');
}

if (($payload['DMS_SUBSCRIPTION_STATUS'] ?? '') !== 'none') {
    throw new RuntimeException('Expected DMS_SUBSCRIPTION_STATUS=none without an active add-on.');
}

if (($payload['plan'] ?? '') !== 'SME_PLUS') {
    throw new RuntimeException('Expected plan to be SME_PLUS.');
}

echo "LicensingServiceTest passed.\n";
