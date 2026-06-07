<?php

defined('BASEPATH') or exit('No direct script access allowed');

class TenantEntitlementService
{
    protected $CI;
    protected $planFeatureValueColumnExists;

    public function __construct()
    {
        $this->CI = &get_instance();
        if (!isset($this->CI->Kt_saas_model)) {
            $this->CI->load->model('kt_saas/Kt_saas_model');
        }
    }

    /**
     * Lấy profile hoạt động của tenant tại runtime
     */
    public function getRuntimeProfile(array $tenant)
    {
        $tenantId = (int) ($tenant['id'] ?? 0);
        $subscription = $this->getActiveSubscription($tenantId);
        $plan = $this->getEffectivePlan($tenantId, $tenant, $subscription);

        // Lấy tất cả các module được phép sử dụng từ catalog và features
        $allowedModules = [];
        $catalog = $this->landlordDb()
            ->where('is_global_active', 1)
            ->get(db_prefix() . 'kt_saas_module_catalog')
            ->result_array();

        foreach ($catalog as $item) {
            $modName = $item['module_name'];
            if ($this->canUseModule($tenantId, $modName)) {
                $allowedModules[] = $modName;
            }
        }

        $moduleOverrides = $this->getModuleOverrides($tenantId);
        $features = $this->getTenantFeatures($tenantId);
        $workspaceFeatures = [];
        foreach ($features as $featureKey => $enabled) {
            if (strpos((string) $featureKey, 'workspace.') === 0) {
                $workspaceFeatures[$featureKey] = (bool) $enabled;
            }
        }

        return [
            'tenant_id'         => $tenantId,
            'tenant_code'       => (string) ($tenant['tenant_code'] ?? ''),
            'subscription'      => $subscription,
            'plan'              => $plan,
            'module_codes'      => $allowedModules,
            'managed_modules'   => array_column($catalog, 'module_name'),
            'module_overrides'  => $moduleOverrides,
            'features'          => $features,
            'workspace_features'=> $workspaceFeatures,
            'limits'            => $this->extractPlanLimits($plan),
        ];
    }

    /**
     * Kiểm tra quyền truy cập route URI của Tenant
     */
    public function canAccessRequestUri($uri, array $tenant)
    {
        $moduleCode = $this->detectRequestedModule($uri);
        $profile = $this->getRuntimeProfile($tenant);

        if ($moduleCode === null) {
            return [
                'allowed' => true,
                'reason'  => 'core_route',
                'profile' => $profile,
            ];
        }

        if ($moduleCode === $this->saasModuleCode()) {
            if ($this->isTenantPortalRoute($uri)) {
                return [
                    'allowed' => true,
                    'reason'  => 'tenant_portal_route',
                    'module'  => $moduleCode,
                    'profile' => $profile,
                ];
            }

            return [
                'allowed' => false,
                'reason'  => 'landlord_only_module',
                'module'  => $moduleCode,
                'profile' => $profile,
            ];
        }

        if (!$this->canUseModule($tenant['id'], $moduleCode)) {
            return [
                'allowed' => false,
                'reason'  => 'module_not_entitled',
                'module'  => $moduleCode,
                'profile' => $profile,
            ];
        }

        return [
            'allowed' => true,
            'reason'  => 'module_allowed',
            'module'  => $moduleCode,
            'profile' => $profile,
        ];
    }

    /**
     * Kiểm tra xem Tenant có quyền truy cập vào module/app tổng thể hay không
     */
    public function canUseModule($tenantId, $moduleName): bool
    {
        $moduleName = strtolower(trim((string) $moduleName));
        if ($moduleName === '') {
            return true;
        }

        $entitlementModule = $moduleName;

        // 1. Kiểm tra xem module có trong catalog và đang hoạt động global không
        $syntheticModule = in_array($moduleName, ['workspace', 'core'], true);
        if (!$syntheticModule) {
            $catalog = $this->landlordDb()
                ->group_start()
                    ->where('module_name', $moduleName)
                    ->or_where('module_name', $entitlementModule)
                ->group_end()
                ->where('is_global_active', 1)
                ->get(db_prefix() . 'kt_saas_module_catalog')
                ->row_array();

            if (!$catalog) {
                return false;
            }
        }

        // 2. Kiểm tra ghi đè (override) của riêng tenant
        $override = $this->landlordDb()
            ->where('tenant_id', $tenantId)
            ->where_in('module_name', array_unique([$moduleName, $entitlementModule]))
            ->where_in('feature_key', array_unique([$moduleName . '.access', $entitlementModule . '.access']))
            ->get(db_prefix() . 'kt_saas_tenant_entitlements')
            ->row_array();

        if ($override) {
            return (int) $override['is_enabled'] === 1;
        }

        // 3. Fallback về plan features
        $tenant = $this->getTenantFromLandlord($tenantId);
        if (!$tenant) {
            return false;
        }

        $subscription = $this->getActiveSubscription($tenantId);
        $plan = $this->getEffectivePlan($tenantId, $tenant, $subscription);
        if (!$plan) {
            return false;
        }

        $planId = (int) $plan['id'];
        $planFeature = $this->landlordDb()
            ->where('plan_id', $planId)
            ->where_in('module_name', array_unique([$moduleName, $entitlementModule]))
            ->where_in('feature_key', array_unique([$moduleName . '.access', $entitlementModule . '.access']))
            ->get(db_prefix() . 'kt_saas_plan_features')
            ->row_array();

        if ($planFeature) {
            return (int) $planFeature['is_enabled'] === 1;
        }

        return false;
    }

    /**
     * Kiểm tra xem Tenant có quyền sử dụng một feature cụ thể hay không
     */
    public function canUseFeature($tenantId, $moduleName, $featureKey): bool
    {
        $moduleName = strtolower(trim((string) $moduleName));
        $featureKey = strtolower(trim((string) $featureKey));
        $entitlementModule = $moduleName;

        // 1. Kiểm tra catalog và trạng thái global active
        $featureHasOwnMapping = $this->featureExistsInPlanCatalog($featureKey);
        $syntheticFeature = in_array($moduleName, ['workspace', 'core'], true)
            || strpos($featureKey, 'workspace.') === 0
            || $featureHasOwnMapping
            || !is_dir(APP_MODULES_PATH . $moduleName);
        $catalog = null;
        if (!$syntheticFeature) {
            $catalog = $this->landlordDb()
                ->group_start()
                    ->where('module_name', $moduleName)
                    ->or_where('module_name', $entitlementModule)
                ->group_end()
                ->where('is_global_active', 1)
                ->get(db_prefix() . 'kt_saas_module_catalog')
                ->row_array();
        }

        if (!$syntheticFeature && !$catalog) {
            return false;
        }

        // 2. Kiểm tra ghi đè của tenant
        $override = $this->landlordDb()
            ->where('tenant_id', $tenantId)
            ->where('feature_key', $featureKey)
            ->get(db_prefix() . 'kt_saas_tenant_entitlements')
            ->row_array();

        if ($override) {
            return (int) $override['is_enabled'] === 1;
        }

        // 3. Fallback về plan features
        $tenant = $this->getTenantFromLandlord($tenantId);
        if (!$tenant) {
            return false;
        }

        $subscription = $this->getActiveSubscription($tenantId);
        $plan = $this->getEffectivePlan($tenantId, $tenant, $subscription);
        if (!$plan) {
            return false;
        }

        $planId = (int) $plan['id'];
        $planFeature = $this->landlordDb()
            ->where('plan_id', $planId)
            ->where('feature_key', $featureKey)
            ->get(db_prefix() . 'kt_saas_plan_features')
            ->row_array();

        if ($planFeature) {
            return (int) $planFeature['is_enabled'] === 1;
        }

        if ($this->featureFallbackAllowed($featureKey)) {
            return true;
        }

        return false;
    }

    /**
     * Backward-compatible feature value getter used by legacy modules.
     * Returns bool/int/string depending on stored value.
     */
    public function getFeatureValue($tenantId, $featureKey, $default = null)
    {
        $featureKey = strtolower(trim((string) $featureKey));
        if ($featureKey === '') {
            return $default;
        }

        $moduleName = (string) strstr($featureKey, '.', true);
        if ($moduleName === '') {
            $moduleName = 'core';
        }

        $tenant = $this->getTenantFromLandlord($tenantId);
        if (!$tenant) {
            return $default;
        }

        $subscription = $this->getActiveSubscription($tenantId);
        $plan = $this->getEffectivePlan($tenantId, $tenant, $subscription);
        $planId = (int) ($plan['id'] ?? 0);

        $override = $this->landlordDb()
            ->where('tenant_id', (int) $tenantId)
            ->where('feature_key', $featureKey)
            ->get(db_prefix() . 'kt_saas_tenant_entitlements')
            ->row_array();
        if ($override) {
            return (int) $override['is_enabled'] === 1;
        }

        if ($planId > 0) {
            $planFeature = $this->landlordDb()
                ->where('plan_id', $planId)
                ->where('feature_key', $featureKey)
                ->get(db_prefix() . 'kt_saas_plan_features')
                ->row_array();
            if ($planFeature) {
                if ($this->hasPlanFeatureValueColumn() && array_key_exists('feature_value', $planFeature) && $planFeature['feature_value'] !== null && $planFeature['feature_value'] !== '') {
                    return $this->normalizeFeatureValue($planFeature['feature_value']);
                }

                return (int) ($planFeature['is_enabled'] ?? 0) === 1;
            }
        }

        if ($this->featureFallbackAllowed($featureKey)) {
            return true;
        }

        return $default;
    }

    /**
     * Lấy danh sách toàn bộ các cờ tính năng khả dụng của Tenant
     */
    public function getTenantFeatures($tenantId): array
    {
        $tenant = $this->getTenantFromLandlord($tenantId);
        if (!$tenant) {
            return [];
        }

        $subscription = $this->getActiveSubscription($tenantId);
        $plan = $this->getEffectivePlan($tenantId, $tenant, $subscription);
        $planId = $plan ? (int) $plan['id'] : 0;

        $features = [];
        if ($planId > 0) {
            $rows = $this->landlordDb()
                ->where('plan_id', $planId)
                ->get(db_prefix() . 'kt_saas_plan_features')
                ->result_array();
            foreach ($rows as $row) {
                $features[$row['feature_key']] = (int) $row['is_enabled'] === 1;
            }
        }

        $overrides = $this->landlordDb()
            ->where('tenant_id', $tenantId)
            ->get(db_prefix() . 'kt_saas_tenant_entitlements')
            ->result_array();
        foreach ($overrides as $row) {
            $features[$row['feature_key']] = (int) $row['is_enabled'] === 1;
        }

        return $features;
    }

    /**
     * Lấy danh sách các ứng dụng hiển thị kèm trạng thái allowed
     */
    public function getTenantApps($tenantId): array
    {
        $catalog = $this->landlordDb()
            ->where('is_global_active', 1)
            ->get(db_prefix() . 'kt_saas_module_catalog')
            ->result_array();

        $apps = [];
        foreach ($catalog as $item) {
            $modName = $item['module_name'];
            $apps[] = [
                'module_name'  => $modName,
                'display_name' => $item['display_name'],
                'slug'         => $item['slug'],
                'description'  => $item['description'],
                'version'      => $item['version'],
                'allowed'      => $this->canUseModule($tenantId, $modName),
            ];
        }

        return $apps;
    }

    /**
     * Hàm adapter tương thích ngược cho logic cũ
     */
    public function isModuleAllowed($moduleCode, array $profile = [])
    {
        $tenantId = (int) ($profile['tenant_id'] ?? 0);
        if ($tenantId === 0) {
            $tenant = kt_saas_current_tenant();
            $tenantId = $tenant ? (int) $tenant['id'] : 0;
        }

        if ($tenantId === 0) {
            return true;
        }

        return $this->canUseModule($tenantId, $moduleCode);
    }

    /**
     * Lấy snapshot thống kê sử dụng tài nguyên của tenant
     */
    public function getTenantUsageSnapshot(?array $tenant = null)
    {
        $metrics = [
            'staff'               => $this->safeCount(db_prefix() . 'staff', ['active' => 1]),
            'clients'             => $this->safeCount(db_prefix() . 'clients'),
            'projects'            => $this->safeCount(db_prefix() . 'projects'),
            'invoices'            => $this->safeCount(db_prefix() . 'invoices'),
            'warehouses'          => $this->safeCount(db_prefix() . 'kt_warehouses'),
            'roles'               => $this->safeCount(db_prefix() . 'roles'),
            'departments'         => $this->safeCount(db_prefix() . 'departments'),
            'governance_viewers'  => function_exists('kt_saas_workspace_governance_view_allowed_staff_ids') ? count(kt_saas_workspace_governance_view_allowed_staff_ids()) : 0,
            'governance_managers' => function_exists('kt_saas_workspace_governance_manage_allowed_staff_ids') ? count(kt_saas_workspace_governance_manage_allowed_staff_ids()) : 0,
        ];

        $storagePath = kt_saas_tenant_storage_path('', $tenant);
        $metrics['storage_mb'] = $this->directorySizeInMb($storagePath);

        $tenant = $tenant ?: kt_saas_current_tenant();
        $tenantId = (int) ($tenant['id'] ?? 0);
        if ($tenantId > 0) {
            // Lấy dòng API call của hôm nay
            $row = $this->landlordDb()
                ->where('tenant_id', $tenantId)
                ->where('metric_key', 'api_daily')
                ->where('module_name', 'core')
                ->get(db_prefix() . 'kt_saas_usage')
                ->row_array();
            $metrics['api_daily'] = $row ? (int) $row['used_value'] : 0;
        } else {
            $metrics['api_daily'] = 0;
        }

        return $metrics;
    }

    public function isWithinLimit($limitKey, $currentValue, array $profile = [])
    {
        $limits = $profile['limits'] ?? [];
        $limitValue = (int) ($limits[$limitKey] ?? 0);
        if ($limitValue === 0) {
            return true;
        }

        return (float) $currentValue <= $limitValue;
    }

    public function assertCanCreate($resourceKey, $increment = 1, ?array $profile = null)
    {
        if (!kt_saas_is_tenant_runtime()) {
            return true;
        }

        $profile = $profile ?: kt_saas_current_profile();
        if (!$profile) {
            $profile = $this->getRuntimeProfile(kt_saas_current_tenant());
        }

        $usage = $this->getTenantUsageSnapshot();
        $currentValue = (float) ($usage[$resourceKey] ?? 0);
        $projectedValue = $currentValue + $increment;

        if ($this->isWithinLimit($resourceKey, $projectedValue, $profile)) {
            return true;
        }

        $limitValue = (int) ($profile['limits'][$resourceKey] ?? 0);
        $this->abortLimitExceeded($resourceKey, $limitValue, $currentValue, $projectedValue);
        return false;
    }

    public function assertWithinLimit($resourceKey, $projectedValue, ?array $profile = null, $currentValue = null)
    {
        if (!kt_saas_is_tenant_runtime()) {
            return true;
        }

        $profile = $profile ?: kt_saas_current_profile();
        if (!$profile) {
            $profile = $this->getRuntimeProfile(kt_saas_current_tenant());
        }

        if ($currentValue === null) {
            $usage = $this->getTenantUsageSnapshot();
            $currentValue = (float) ($usage[$resourceKey] ?? 0);
        }

        $projectedValue = (float) $projectedValue;
        if ($this->isWithinLimit($resourceKey, $projectedValue, $profile)) {
            return true;
        }

        $limitValue = (int) ($profile['limits'][$resourceKey] ?? 0);
        $this->abortLimitExceeded($resourceKey, $limitValue, $currentValue, $projectedValue);
        return false;
    }

    /**
     * Đồng bộ lưu trữ snapshot sử dụng của tenant vào Landlord DB
     */
    public function persistUsageSnapshot(?array $tenant = null, ?array $metrics = null)
    {
        $tenant = $tenant ?: kt_saas_current_tenant();
        $tenantId = (int) ($tenant['id'] ?? 0);
        if ($tenantId <= 0) {
            return false;
        }

        $metrics = $metrics ?: $this->getTenantUsageSnapshot($tenant);
        $now = date('Y-m-d H:i:s');
        $db = $this->landlordDb();

        $profile = $this->getRuntimeProfile($tenant);
        $limits = $profile['limits'] ?? [];

        foreach ($metrics as $metricKey => $metricValue) {
            $limitValue = (float) ($limits[$metricKey] ?? 0.00);

            $existing = $db
                ->where('tenant_id', $tenantId)
                ->where('metric_key', $metricKey)
                ->where('module_name', 'core')
                ->get(db_prefix() . 'kt_saas_usage')
                ->row_array();

            $payload = [
                'tenant_id'     => $tenantId,
                'module_name'   => 'core',
                'metric_key'    => $metricKey,
                'used_value'    => $metricValue,
                'limit_value'   => $limitValue,
                'updated_at'    => $now,
            ];

            if ($existing) {
                $db->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_saas_usage', $payload);
                continue;
            }

            $db->insert(db_prefix() . 'kt_saas_usage', $payload);
        }

        return true;
    }

    public function buildOverageSummary(?array $tenant = null, ?array $profile = null, ?array $metrics = null)
    {
        $tenant = $tenant ?: kt_saas_current_tenant();
        $profile = $profile ?: kt_saas_current_profile();
        if (!$profile && $tenant) {
            $profile = $this->getRuntimeProfile($tenant);
        }

        $metrics = $metrics ?: $this->getTenantUsageSnapshot($tenant);
        $limits = $profile['limits'] ?? [];
        $overages = [];

        foreach ($metrics as $metricKey => $metricValue) {
            $limitValue = (int) ($limits[$metricKey] ?? 0);
            if ($limitValue === 0) {
                continue;
            }

            if ((float) $metricValue > $limitValue) {
                $overages[$metricKey] = [
                    'metric_key'    => $metricKey,
                    'current_value' => (float) $metricValue,
                    'limit_value'   => $limitValue,
                    'excess_value'  => (float) $metricValue - $limitValue,
                ];
            }
        }

        return $overages;
    }

    public function detectRequestedModule($uri)
    {
        $uri = trim((string) $uri, '/');
        if ($uri === '') {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $uri), 'strlen'));
        if (empty($segments)) {
            return null;
        }

        $candidate = $segments[0];
        if ($candidate === 'admin') {
            $candidate = $segments[1] ?? '';
        }

        $candidate = strtolower(trim((string) $candidate));
        if ($candidate === '') {
            return null;
        }

        // Kiểm tra xem candidate có khớp với module name nào trong hệ thống
        return is_dir(APP_MODULES_PATH . $candidate) ? $candidate : null;
    }

    protected function getActiveSubscription($tenantId)
    {
        if ($tenantId <= 0) {
            return null;
        }

        if (method_exists($this->CI->Kt_saas_model, 'get_current_subscription')) {
            return $this->CI->Kt_saas_model->get_current_subscription((int) $tenantId);
        }

        return $this->landlordDb()
            ->where('tenant_id', $tenantId)
            ->where('deleted_at IS NULL', null, false)
            ->order_by('id', 'desc')
            ->get(db_prefix() . 'kt_saas_subscriptions')
            ->row_array();
    }

    protected function getEffectivePlan($tenantId, array $tenant, ?array $subscription = null)
    {
        $planId = (int) ($subscription['plan_id'] ?? ($tenant['plan_id'] ?? 0));
        if ($planId <= 0) {
            return null;
        }

        return $this->landlordDb()
            ->where('id', $planId)
            ->where('deleted_at IS NULL', null, false)
            ->get(db_prefix() . 'kt_saas_plans')
            ->row_array();
    }

    protected function getModuleOverrides($tenantId)
    {
        if ($tenantId <= 0) {
            return [];
        }

        $rows = $this->landlordDb()
            ->where('tenant_id', $tenantId)
            ->get(db_prefix() . 'kt_saas_tenant_entitlements')
            ->result_array();

        $overrides = [];
        foreach ($rows as $row) {
            if ($row['feature_key'] === $row['module_name'] . '.access') {
                $overrides[$row['module_name']] = (int) $row['is_enabled'] === 1 ? 'enabled' : 'disabled';
            }
        }

        return $overrides;
    }

    protected function featureFallbackAllowed($featureKey)
    {
        $defaults = [
            'kt_sepay.settings.edit' => true,
            'kt_sepay.health.run' => true,
            'kt_sepay.reconcile.run' => true,
            'kt_sepay.payment_requests.create' => true,
            'einvoice.settings.edit' => true,
        ];

        return $defaults[$featureKey] ?? false;
    }

    protected function featureExistsInPlanCatalog($featureKey)
    {
        $featureKey = strtolower(trim((string) $featureKey));
        if ($featureKey === '') {
            return false;
        }

        $row = $this->landlordDb()
            ->select('id')
            ->where('feature_key', $featureKey)
            ->limit(1)
            ->get(db_prefix() . 'kt_saas_plan_features')
            ->row_array();

        return is_array($row) && !empty($row['id']);
    }

    protected function extractPlanLimits($plan)
    {
        if (!$plan) {
            return [];
        }

        return [
            'staff'               => (int) ($plan['limit_staff'] ?? 0),
            'clients'             => (int) ($plan['limit_clients'] ?? 0),
            'storage_mb'          => (int) ($plan['limit_storage_mb'] ?? 0),
            'invoices'            => (int) ($plan['limit_invoices'] ?? 0),
            'projects'            => (int) ($plan['limit_projects'] ?? 0),
            'api_daily'           => (int) ($plan['limit_api_requests_daily'] ?? 0),
            'warehouses'          => (int) ($plan['limit_warehouses'] ?? 0),
            'automation'          => (int) ($plan['limit_automations'] ?? 0),
            'roles'               => (int) ($plan['limit_roles'] ?? 0),
            'departments'         => (int) ($plan['limit_departments'] ?? 0),
            'governance_viewers'  => (int) ($plan['limit_governance_viewers'] ?? 0),
            'governance_managers' => (int) ($plan['limit_governance_managers'] ?? 0),
        ];
    }

    protected function abortLimitExceeded($resourceKey, $limitValue, $currentValue, $projectedValue)
    {
        $labels = [
            'staff'      => 'nhân sự',
            'clients'    => 'khách hàng',
            'projects'   => 'dự án',
            'invoices'   => 'hóa đơn',
            'warehouses' => 'kho hàng',
            'storage_mb' => 'dung lượng lưu trữ',
            'api_daily'  => 'lượt gọi API trong ngày',
            'automation' => 'kịch bản tự động hóa',
            'roles' => 'vai trò tùy chỉnh',
            'departments' => 'phòng ban',
            'governance_viewers' => 'người xem governance',
            'governance_managers' => 'người quản trị governance',
        ];

        $label = $labels[$resourceKey] ?? $resourceKey;
        $message = 'Gói dịch vụ đã vượt giới hạn cho ' . $label . '. Đang dùng: ' . $currentValue . ', sau thao tác sẽ là: ' . $projectedValue . ', giới hạn cho phép: ' . $limitValue . '.';

        log_message('error', 'KT SaaS limit exceeded [' . $resourceKey . '] ' . $message);

        if (function_exists('set_alert')) {
            set_alert('warning', $message);
        }

        show_error($message, 403, 'Vượt giới hạn gói dịch vụ');
    }

    public function checkAndIncrementApiLimit(array $tenant, array $profile)
    {
        $limitValue = (int) ($profile['limits']['api_daily'] ?? 0);
        if ($limitValue <= 0) {
            return true;
        }

        $tenantId = (int) ($tenant['id'] ?? 0);
        $db = $this->landlordDb();

        $row = $db->where('tenant_id', $tenantId)
            ->where('metric_key', 'api_daily')
            ->where('module_name', 'core')
            ->get(db_prefix() . 'kt_saas_usage')
            ->row_array();

        $currentCount = $row ? (int) $row['used_value'] : 0;
        $newCount = $currentCount + 1;

        if ($newCount > $limitValue) {
            log_message('error', 'KT SaaS API limit exceeded for tenant [' . $tenantId . ']. Limit: ' . $limitValue . ', Current: ' . $newCount);
            set_status_header(429);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Đã vượt giới hạn gọi API trong ngày.';
            exit;
        }

        $now = date('Y-m-d H:i:s');
        $payload = [
            'tenant_id'     => $tenantId,
            'module_name'   => 'core',
            'metric_key'    => 'api_daily',
            'used_value'    => $newCount,
            'limit_value'   => $limitValue,
            'updated_at'    => $now,
        ];

        if ($row) {
            $db->where('id', (int) $row['id'])->update(db_prefix() . 'kt_saas_usage', $payload);
        } else {
            $db->insert(db_prefix() . 'kt_saas_usage', $payload);
        }

        return true;
    }

    protected function isTenantPortalRoute($uri)
    {
        $uri = trim((string) $uri, '/');

        if (in_array($uri, [
            'admin/kt_saas/tenant_subscription',
            'admin/kt_saas/tenant_billing',
            'admin/kt_saas/tenant_usage',
            'admin/kt_saas/tenant_settings',
            'admin/kt_saas/tenant_activity_logs',
            'admin/kt_saas/tenant_governance',
            'admin/kt_saas/tenant_departments',
            'admin/kt_saas/tenant_remove_company_logo',
            'admin/kt_saas/tenant_remove_favicon',
            'admin/kt_saas/tenant_request_renewal',
            'admin/kt_saas/tenant_settings_profile_save',
            'admin/kt_saas/tenant_settings_localization_save',
            'admin/kt_saas/tenant_settings_email_identity_save',
            'admin/kt_saas/tenant_settings_invoice_save',
            'admin/kt_saas/tenant_settings_finance_save',
            'admin/kt_saas/tenant_settings_branding_save',
            'admin/kt_saas/tenant_settings_notifications_save',
            'admin/kt_saas/tenant_settings_governance_save',
            'admin/kt_saas/tenant_email_settings_save',
            'admin/kt_saas/tenant_email_settings_reset',
            'admin/kt_saas/tenant_email_settings_test',
        ], true)) {
            return true;
        }

        if (preg_match('#^kt_saas/checkout/(invoice|pay)/\d+/[a-f0-9]+$#', $uri)) {
            return true;
        }

        return (bool) preg_match('#^admin/kt_saas/(tenant_request_plan_change/\d+|tenant_remove_company_logo/dark|tenant_role(?:/\d+)?|tenant_delete_role/\d+|tenant_delete_department/\d+)$#', $uri);
    }

    protected function saasModuleCode()
    {
        return defined('KT_SAAS_MODULE') ? KT_SAAS_MODULE : 'kt_saas';
    }

    protected function getTenantFromLandlord($tenantId)
    {
        if ((int) $tenantId <= 0) {
            return null;
        }

        return $this->landlordDb()
            ->where('id', (int) $tenantId)
            ->where('deleted_at IS NULL', null, false)
            ->get(db_prefix() . 'kt_saas_tenants')
            ->row_array();
    }

    protected function safeCount($table, array $where = [])
    {
        if (!$this->CI->db->table_exists($table)) {
            return 0;
        }

        foreach ($where as $field => $value) {
            $this->CI->db->where($field, $value);
        }

        return (int) $this->CI->db->count_all_results($table);
    }

    protected function directorySizeInMb($path)
    {
        if (!is_dir($path)) {
            return 0;
        }

        $bytes = 0;
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $bytes += (int) $file->getSize();
                }
            }
        } catch (Throwable $e) {
            return 0;
        }

        return round($bytes / 1048576, 2);
    }

    protected function landlordDb()
    {
        $landlordDb = $this->CI->config->item('kt_saas_landlord_db');

        return $landlordDb ?: $this->CI->db;
    }

    protected function hasPlanFeatureValueColumn(): bool
    {
        if ($this->planFeatureValueColumnExists !== null) {
            return $this->planFeatureValueColumnExists;
        }

        $this->planFeatureValueColumnExists = $this->landlordDb()->field_exists('feature_value', db_prefix() . 'kt_saas_plan_features');
        return $this->planFeatureValueColumnExists;
    }

    protected function normalizeFeatureValue($value)
    {
        if ($value === null) {
            return null;
        }

        $string = strtolower(trim((string) $value));
        if ($string === 'true') {
            return true;
        }

        if ($string === 'false') {
            return false;
        }

        if (is_numeric($string)) {
            return strpos($string, '.') !== false ? (float) $string : (int) $string;
        }

        return $value;
    }
}
