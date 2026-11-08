<?php

defined('BASEPATH') or exit('No direct script access allowed');

class LicensingService
{
    private const FEATURE_KEYS = [
        'DMS_ENABLED',
        'CHECKIN_ENABLED',
        'VISIT_ENABLED',
        'ORDERS_ENABLED',
        'INVENTORY_ENABLED',
        'FINANCE_VIEW_ENABLED',
        'REPORTS_ENABLED',
        'FIELD_FEEDBACK_ENABLED',
        'MAP_ENABLED',
        'LIVE_TRACKING_ENABLED',
        'ROUTE_ANALYTICS_ENABLED',
        'NOTIFICATION_BRIDGE_ENABLED',
        'SSO_ENABLED',
    ];

    private const VALUE_KEYS = [
        'DMS_PACKAGE',
        'USER_LIMIT',
        'STORE_LIMIT',
        'PRODUCT_LIMIT',
        'CHECKIN_MONTHLY_LIMIT',
        'ORDER_MONTHLY_LIMIT',
        'PHOTO_LIMIT',
        'API_WEBHOOK_LIMIT',
        'REPORT_EXPORT_LIMIT',
        'LOG_RETENTION_MONTHS',
    ];

    public function buildTenantLicensingPayload(array $tenant, ?array $subscription, array $profile, TenantEntitlementService $entitlementService, ?array $dmsAddon = null): array
    {
        $tenantId = (int) ($tenant['id'] ?? 0);
        $subscriptionStatus = strtolower(trim((string) ($subscription['status'] ?? '')));
        $tenantStatus = strtolower(trim((string) ($tenant['status'] ?? 'draft')));
        $planCode = trim((string) ($subscription['plan_code'] ?? ($profile['plan']['plan_code'] ?? '')));
        $companyName = trim((string) ($tenant['company_name'] ?? ''));
        $addonStatus = strtolower(trim((string) ($dmsAddon['status'] ?? '')));
        $addonPackage = is_array($dmsAddon['package'] ?? null) ? $dmsAddon['package'] : [];
        $addonIsActive = $addonStatus === 'active';

        $features = [];
        foreach (self::FEATURE_KEYS as $publicKey) {
            $value = $publicKey === 'DMS_ENABLED'
                ? $addonIsActive
                : (bool) ($addonPackage['features'][$publicKey] ?? false);

            if ($publicKey !== 'DMS_ENABLED' && ! $features['DMS_ENABLED']) {
                $value = false;
            }

            $features[$publicKey] = $value;
        }

        if (empty($features['DMS_ENABLED'])) {
            foreach (self::FEATURE_KEYS as $publicKey) {
                if ($publicKey !== 'DMS_ENABLED') {
                    $features[$publicKey] = false;
                }
            }
        }

        $limits = [];
        foreach (self::VALUE_KEYS as $publicKey) {
            $value = $addonIsActive
                ? $this->resolveAddonLimitValue($publicKey, $addonPackage)
                : ($publicKey === 'DMS_PACKAGE' ? '' : 0);

            if ($publicKey === 'DMS_PACKAGE') {
                $limits[$publicKey] = strtoupper(trim((string) $value));
                continue;
            }

            $limits[$publicKey] = max(0, (int) $value);
        }

        $overrides = $this->getTenantDmsOverrides($tenantId);
        if ($addonIsActive && !empty($overrides)) {
            $featureOverrideMap = [
                'dms_enabled' => 'DMS_ENABLED',
                'orders_enabled' => 'ORDERS_ENABLED',
                'visits_enabled' => 'VISIT_ENABLED',
                'checkin_review_enabled' => 'CHECKIN_REVIEW_ENABLED',
                'live_tracking_enabled' => 'LIVE_TRACKING_ENABLED',
                'inventory_enabled' => 'INVENTORY_ENABLED',
                'finance_view_enabled' => 'FINANCE_VIEW_ENABLED',
                'reports_enabled' => 'REPORTS_ENABLED',
                'map_enabled' => 'MAP_ENABLED',
                'mobile_app_enabled' => 'MOBILE_APP_ENABLED',
            ];
            foreach ((array) ($overrides['features'] ?? []) as $key => $value) {
                $publicKey = $featureOverrideMap[$key] ?? null;
                if ($publicKey === null) {
                    continue;
                }
                $features[$publicKey] = (bool) $value;
            }
            if (empty($features['DMS_ENABLED'])) {
                foreach (array_keys($features) as $publicKey) {
                    if ($publicKey !== 'DMS_ENABLED') {
                        $features[$publicKey] = false;
                    }
                }
            }

            $quotaOverrideMap = [
                'user_limit' => 'USER_LIMIT',
                'store_limit' => 'STORE_LIMIT',
                'product_limit' => 'PRODUCT_LIMIT',
                'checkin_monthly_limit' => 'CHECKIN_MONTHLY_LIMIT',
                'photo_limit' => 'PHOTO_LIMIT',
                'report_export_limit' => 'REPORT_EXPORT_LIMIT',
                'retention_months' => 'LOG_RETENTION_MONTHS',
                'order_monthly_limit' => 'ORDER_MONTHLY_LIMIT',
                'api_webhook_limit' => 'API_WEBHOOK_LIMIT',
            ];
            foreach ((array) ($overrides['quota'] ?? []) as $key => $value) {
                $publicKey = $quotaOverrideMap[$key] ?? null;
                if ($publicKey === null) {
                    continue;
                }
                $limits[$publicKey] = max(0, (int) $value);
            }
        }

        $configVersion = function_exists('get_option')
            ? max(1, (int) get_option('kt_saas_dms_config_version'))
            : 1;
        $platformConfig = $this->getPlatformDmsConfig();
        $packageMapEnabled = !empty($features['MAP_ENABLED']);
        if (($platformConfig['map_provider'] ?? 'keyless') === 'disabled') {
            $features['MAP_ENABLED'] = false;
        }
        if (empty($platformConfig['sso_enabled'])) {
            $features['SSO_ENABLED'] = false;
        }
        $allowedBillingPeriods = array_values((array) ($addonPackage['allowed_billing_periods'] ?? ['monthly']));
        $billingCycle = strtolower(trim((string) ($dmsAddon['addon']['billing_cycle'] ?? ($addonPackage['default_billing_period'] ?? 'monthly'))));
        if (!in_array($billingCycle, $allowedBillingPeriods, true)) {
            $billingCycle = (string) ($allowedBillingPeriods[0] ?? 'monthly');
        }

        $platformMapStatus = (string) ($platformConfig['map_status'] ?? 'ready');
        $effectiveMapStatus = !$packageMapEnabled
            ? 'disabled_by_package'
            : ($platformMapStatus === 'disabled' ? 'disabled_by_platform'
                : ($platformMapStatus === 'missing_config' ? 'missing_platform_config' : 'ready'));

        return [
            'tenant_id' => $tenantId,
            'crm_tenant_id' => $tenantId,
            'crm_company_id' => $tenantId,
            'tenant_code' => strtoupper(trim((string) ($tenant['tenant_code'] ?? ''))),
            'company_name' => $companyName,
            'plan' => $planCode,
            'dms_package' => (string) ($limits['DMS_PACKAGE'] ?? ''),
            'config_version' => $configVersion,
            'config_generated_at' => (string) ($platformConfig['generated_at'] ?? gmdate('c')),
            'dms_billing_cycle' => $billingCycle,
            'allowed_billing_periods' => $allowedBillingPeriods,
            'platform_config' => [
                'map_provider' => (string) ($platformConfig['map_provider'] ?? 'keyless'),
                'map_enabled' => !empty($platformConfig['map_enabled']),
                'map_key_configured' => !empty($platformConfig['map_api_key_configured']),
                'map_allowed_domains' => array_values((array) ($platformConfig['allowed_domains'] ?? [])),
                'map_validation_state' => (string) ($platformConfig['map_validation_state'] ?? 'unknown'),
                'sso_timeout_seconds' => (int) ($platformConfig['sso_timeout_seconds'] ?? 3),
                'bridge_enabled' => !empty($platformConfig['bridge_enabled']),
                'retention_months' => (int) ($platformConfig['retention_months'] ?? 6),
                'config_version' => $configVersion,
                'generated_at' => (string) ($platformConfig['generated_at'] ?? gmdate('c')),
            ],
            'map' => [
                'provider' => (string) ($platformConfig['map_provider'] ?? 'keyless'),
                'status' => $effectiveMapStatus,
                'platform_status' => $platformMapStatus,
                'enabled' => $effectiveMapStatus === 'ready',
                'package_enabled' => $packageMapEnabled,
                'managed_by' => 'platform',
                'api_key_configured' => !empty($platformConfig['map_api_key_configured']),
                'allowed_domains' => array_values((array) ($platformConfig['allowed_domains'] ?? [])),
                'validation_state' => (string) ($platformConfig['map_validation_state'] ?? 'unknown'),
            ],
            'sso' => [
                'enabled' => !empty($features['SSO_ENABLED']),
                'bridge_enabled' => !empty($platformConfig['bridge_enabled']),
                'managed_by' => 'platform',
                'timeout_seconds' => (int) ($platformConfig['sso_timeout_seconds'] ?? 3),
            ],
            'limits' => $limits,
            'entitlements' => array_values((array) ($profile['module_codes'] ?? [])),
            'features' => $features,
            'feature_flags' => $features,
            'DMS_SUBSCRIPTION_STATUS' => $addonIsActive ? 'active' : ($addonStatus !== '' ? $addonStatus : 'none'),
            'DMS_EXPIRES_AT' => $addonIsActive ? (string) ($dmsAddon['addon']['ends_at'] ?? '') : '',
            'USER_LIMIT' => (int) ($limits['USER_LIMIT'] ?? 0),
            'STORE_LIMIT' => (int) ($limits['STORE_LIMIT'] ?? 0),
            'PRODUCT_LIMIT' => (int) ($limits['PRODUCT_LIMIT'] ?? 0),
            'CHECKIN_MONTHLY_LIMIT' => (int) ($limits['CHECKIN_MONTHLY_LIMIT'] ?? 0),
            'PHOTO_LIMIT' => (int) ($limits['PHOTO_LIMIT'] ?? 0),
            'REPORT_EXPORT_LIMIT' => (int) ($limits['REPORT_EXPORT_LIMIT'] ?? 0),
            'LOG_RETENTION_MONTHS' => (int) ($limits['LOG_RETENTION_MONTHS'] ?? 0),
            'ORDER_MONTHLY_LIMIT' => (int) ($limits['ORDER_MONTHLY_LIMIT'] ?? 0),
            'API_WEBHOOK_LIMIT' => (int) ($limits['API_WEBHOOK_LIMIT'] ?? 0),
            'DMS_PACKAGE' => (string) ($limits['DMS_PACKAGE'] ?? ''),
            'status' => $this->normalizeTenantStatus($tenantStatus, $subscriptionStatus),
            'fetched_at' => gmdate('c'),
        ];
    }

    private function getPlatformDmsConfig(): array
    {
        $defaults = [
            'map_provider' => 'keyless',
            'map_status' => 'ready',
            'map_api_key_configured' => false,
            'allowed_domains' => [],
            'map_validation_state' => 'ready',
            'sso_enabled' => true,
            'bridge_enabled' => true,
            'sso_timeout_seconds' => 3,
            'retention_months' => 6,
        ];
        if (!function_exists('get_option')) {
            return $defaults;
        }

        $decoded = json_decode((string) get_option('kt_dms_platform_config_json'), true);
        $config = is_array($decoded) ? array_merge($defaults, $decoded) : $defaults;
        $config['map_api_key_configured'] = trim((string) get_option('kt_dms_maps_runtime_browser_key_encrypted')) !== ''
            || trim((string) get_option('kt_dms_google_maps_api_key_encrypted')) !== '';
        if (is_string($config['allowed_domains'])) {
            $config['allowed_domains'] = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $config['allowed_domains']))));
        }
        if (($config['map_provider'] ?? 'keyless') === 'google' && empty($config['map_api_key_configured'])) {
            $config['map_status'] = 'missing_config';
            $config['map_validation_state'] = 'missing_key';
        } elseif (($config['map_provider'] ?? '') === 'disabled') {
            $config['map_status'] = 'disabled';
            $config['map_validation_state'] = 'disabled';
        } else {
            $config['map_status'] = 'ready';
            $config['map_validation_state'] = 'ready';
        }
        $config['generated_at'] = (string) (get_option('kt_saas_dms_config_generated_at') ?: gmdate('c'));

        return $config;
    }

    private function resolveAddonLimitValue(string $publicKey, array $addonPackage)
    {
        $map = [
            'DMS_PACKAGE' => 'package_code',
            'USER_LIMIT' => 'user_limit',
            'STORE_LIMIT' => 'store_limit',
            'PRODUCT_LIMIT' => 'product_limit',
            'CHECKIN_MONTHLY_LIMIT' => 'checkin_monthly_limit',
            'PHOTO_LIMIT' => 'photo_limit',
            'REPORT_EXPORT_LIMIT' => 'report_export_limit',
            'LOG_RETENTION_MONTHS' => 'log_retention_months',
            'ORDER_MONTHLY_LIMIT' => 'order_monthly_limit',
        ];

        $field = $map[$publicKey] ?? null;
        if ($field === null) {
            return $publicKey === 'DMS_PACKAGE' ? '' : 0;
        }

        return $addonPackage[$field] ?? ($publicKey === 'DMS_PACKAGE' ? '' : 0);
    }

    private function normalizeTenantStatus(string $tenantStatus, string $subscriptionStatus): string
    {
        if (in_array($tenantStatus, ['suspended', 'terminated', 'inactive'], true)) {
            return 'suspended';
        }

        if ($subscriptionStatus === 'grace') {
            return 'grace';
        }

        if (in_array($subscriptionStatus, ['trial', 'active'], true) || in_array($tenantStatus, ['active', 'trial'], true)) {
            return 'active';
        }

        return 'suspended';
    }

    private function getTenantDmsOverrides(int $tenantId): array
    {
        if ($tenantId <= 0 || !function_exists('get_instance')) {
            return [];
        }

        $CI = &get_instance();
        if (!isset($CI->db) || !$CI->db->table_exists(db_prefix() . 'kt_dms_tenant_overrides')) {
            return [];
        }

        $row = $CI->db
            ->where('tenant_id', $tenantId)
            ->get(db_prefix() . 'kt_dms_tenant_overrides')
            ->row_array();
        if (!$row) {
            return [];
        }

        $decode = static function ($json): array {
            $data = json_decode((string) $json, true);
            return is_array($data) ? $data : [];
        };

        return [
            'features' => $decode($row['feature_overrides_json'] ?? ''),
            'quota' => $decode($row['quota_overrides_json'] ?? ''),
        ];
    }
}
