<?php

defined('BASEPATH') or exit('No direct script access allowed');

class TenantLimitService
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * Kiểm tra xem tài nguyên tạo mới có vượt ngưỡng giới hạn hay không
     */
    public function checkLimit($tenantId, $moduleName, $metricKey, $increment = 1): bool
    {
        require_once __DIR__ . '/TenantEntitlementService.php';
        $entitlement = new TenantEntitlementService();
        $tenant = $this->CI->Kt_saas_model->get_tenant($tenantId);
        if (!$tenant) {
            return false;
        }

        $profile = $entitlement->getRuntimeProfile($tenant);
        $usage = $entitlement->getTenantUsageSnapshot($tenant);
        
        $currentValue = (float) ($usage[$metricKey] ?? 0);
        $projectedValue = $currentValue + $increment;

        return $entitlement->isWithinLimit($metricKey, $projectedValue, $profile);
    }

    /**
     * Tăng giá trị sử dụng của một metric cụ thể cho tenant
     */
    public function incrementUsage($tenantId, $moduleName, $metricKey, $value = 1): bool
    {
        $db = $this->landlordDb();
        $existing = $db->where('tenant_id', $tenantId)
            ->where('module_name', $moduleName)
            ->where('metric_key', $metricKey)
            ->get(db_prefix() . 'kt_saas_usage')
            ->row_array();

        $now = date('Y-m-d H:i:s');
        if ($existing) {
            $newVal = (float) $existing['used_value'] + $value;
            return $db->where('id', $existing['id'])->update(db_prefix() . 'kt_saas_usage', [
                'used_value' => $newVal,
                'updated_at' => $now,
            ]);
        }

        return $db->insert(db_prefix() . 'kt_saas_usage', [
            'tenant_id'   => $tenantId,
            'module_name' => $moduleName,
            'metric_key'  => $metricKey,
            'used_value'  => $value,
            'limit_value' => 0.00,
            'updated_at'  => $now,
        ]);
    }

    public function decrementUsage($tenantId, $moduleName, $metricKey, $value = 1): bool
    {
        $db = $this->landlordDb();
        $existing = $db->where('tenant_id', $tenantId)
            ->where('module_name', $moduleName)
            ->where('metric_key', $metricKey)
            ->get(db_prefix() . 'kt_saas_usage')
            ->row_array();

        if (!$existing) {
            return true;
        }

        $newVal = max(0, (float) $existing['used_value'] - (float) $value);
        return $db->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_saas_usage', [
            'used_value' => $newVal,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Lấy báo cáo sử dụng tài nguyên của tenant theo module
     */
    public function getUsage($tenantId, $moduleName): array
    {
        return $this->landlordDb()
            ->where('tenant_id', $tenantId)
            ->where('module_name', $moduleName)
            ->get(db_prefix() . 'kt_saas_usage')
            ->result_array();
    }

    /**
     * Khởi tạo lại chu kỳ đếm tài nguyên (ví dụ: API call hàng ngày)
     */
    public function resetUsageCycle(): void
    {
        $this->landlordDb()
            ->where('metric_key', 'api_daily')
            ->update(db_prefix() . 'kt_saas_usage', [
                'used_value' => 0.00,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    protected function landlordDb()
    {
        $landlordDb = $this->CI->config->item('kt_saas_landlord_db');
        return $landlordDb ?: $this->CI->db;
    }
}
