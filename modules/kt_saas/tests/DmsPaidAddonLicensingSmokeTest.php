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
        $legacy = [
            'dms.enabled' => true,
            'dms.checkin_enabled' => true,
            'dms.visit_enabled' => true,
            'dms.live_tracking_enabled' => true,
            'dms.route_analytics_enabled' => true,
            'dms.notification_bridge_enabled' => true,
            'dms.sso_enabled' => true,
            'dms.package_code' => 'DMS_STANDARD',
            'dms.user_limit' => 20,
            'dms.store_limit' => 500,
            'dms.product_limit' => 5000,
            'dms.checkin_monthly_limit' => 10000,
            'dms.photo_limit' => 20000,
            'dms.report_export_limit' => 100,
            'dms.log_retention_months' => 6,
        ];

        return $legacy[$featureKey] ?? $default;
    }
}

require_once dirname(__DIR__) . '/services/LicensingService.php';

$service = new LicensingService();
$tenant = [
    'id' => 30,
    'tenant_code' => 'SME-MINI',
    'company_name' => 'SME Mini',
    'status' => 'active',
];
$subscription = [
    'status' => 'active',
    'plan_code' => 'CRM_SME',
];
$profile = [
    'module_codes' => ['crm'],
];

$noAddonPayload = $service->buildTenantLicensingPayload($tenant, $subscription, $profile, new class extends TenantEntitlementService {
    public function getFeatureValue($tenantId, $featureKey, $default = null)
    {
        return $default;
    }
});

if (($noAddonPayload['features']['DMS_ENABLED'] ?? true) !== false) {
    throw new RuntimeException('Expected DMS_ENABLED to be false when no add-on exists.');
}

if (($noAddonPayload['DMS_SUBSCRIPTION_STATUS'] ?? '') !== 'none') {
    throw new RuntimeException('Expected DMS_SUBSCRIPTION_STATUS=none when no add-on exists.');
}

$activeAddonPayload = $service->buildTenantLicensingPayload(
    $tenant,
    $subscription,
    $profile,
    new TenantEntitlementService(),
    [
        'status' => 'active',
        'addon' => ['ends_at' => '2026-12-31 23:59:59'],
        'package' => [
            'package_code' => 'dms_standard',
            'user_limit' => 20,
            'store_limit' => 500,
            'product_limit' => 5000,
            'checkin_monthly_limit' => 10000,
            'photo_limit' => 20000,
            'report_export_limit' => 100,
            'log_retention_months' => 6,
            'features' => [
                'DMS_ENABLED' => true,
                'CHECKIN_ENABLED' => true,
                'VISIT_ENABLED' => true,
                'LIVE_TRACKING_ENABLED' => true,
                'ROUTE_ANALYTICS_ENABLED' => true,
                'NOTIFICATION_BRIDGE_ENABLED' => true,
                'SSO_ENABLED' => true,
            ],
        ],
    ]
);

if (($activeAddonPayload['features']['DMS_ENABLED'] ?? false) !== true) {
    throw new RuntimeException('Expected DMS_ENABLED to be true for active add-on.');
}

if (($activeAddonPayload['DMS_SUBSCRIPTION_STATUS'] ?? '') !== 'active') {
    throw new RuntimeException('Expected DMS_SUBSCRIPTION_STATUS=active.');
}

if (($activeAddonPayload['DMS_PACKAGE'] ?? '') !== 'DMS_STANDARD') {
    throw new RuntimeException('Expected DMS_PACKAGE from active add-on package.');
}

if ((int) ($activeAddonPayload['USER_LIMIT'] ?? 0) !== 20) {
    throw new RuntimeException('Expected USER_LIMIT=20 from active add-on package.');
}

// Legacy plan-style feature values must no longer activate DMS commercial access.
$legacyPayload = $service->buildTenantLicensingPayload($tenant, $subscription, $profile, new TenantEntitlementService());

if (($legacyPayload['features']['DMS_ENABLED'] ?? true) !== false) {
    throw new RuntimeException('Expected legacy dms.enabled values to be ignored when no active add-on exists.');
}

if (($legacyPayload['DMS_SUBSCRIPTION_STATUS'] ?? '') !== 'none') {
    throw new RuntimeException('Expected no active DMS add-on to resolve as DMS_SUBSCRIPTION_STATUS=none.');
}

echo "DmsPaidAddonLicensingSmokeTest passed.\n";
