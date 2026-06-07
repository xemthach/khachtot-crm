<?php

defined('BASEPATH') or exit('No direct script access allowed');

class UsageSnapshotRunner
{
    protected $CI;
    protected $landlordDb;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->landlordDb = $this->CI->db;
    }

    public function recalculateAll($limit = 100)
    {
        $tenants = $this->getEligibleTenants($limit);
        $results = [];

        foreach ($tenants as $tenant) {
            $results[] = $this->recalculateTenant($tenant);
        }

        return [
            'success'         => true,
            'processed'       => count($results),
            'successful'      => count(array_filter($results, function ($row) {
                return !empty($row['success']);
            })),
            'failed'          => count(array_filter($results, function ($row) {
                return empty($row['success']);
            })),
            'tenant_results'  => $results,
        ];
    }

    public function recalculateTenant(array $tenant)
    {
        require_once module_dir_path(KT_SAAS_MODULE, 'tenant_bootstrap/DatabaseSwitcher.php');
        require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');

        $switcher = new DatabaseSwitcher();
        $switchResult = $switcher->switchConnection($tenant);
        if (empty($switchResult['switched'])) {
            $this->restoreLandlordConnection();
            $this->logUsageActivity('usage.snapshot_failed', 'danger', $tenant, [
                'message' => $switchResult['message'] ?? 'Unable to switch tenant database.',
            ]);

            return [
                'success'     => false,
                'tenant_id'   => (int) $tenant['id'],
                'tenant_code' => $tenant['tenant_code'],
                'message'     => $switchResult['message'] ?? 'Unable to switch tenant database.',
            ];
        }

        $profileBackup = $GLOBALS['kt_saas_current_profile'] ?? null;
        $tenantBackup = $GLOBALS['kt_saas_current_tenant'] ?? null;
        $this->CI->config->set_item('kt_saas_current_tenant', $tenant);
        $GLOBALS['kt_saas_current_tenant'] = $tenant;

        try {
            $entitlements = new TenantEntitlementService();
            $metrics = $entitlements->getTenantUsageSnapshot($tenant);
            $entitlements->persistUsageSnapshot($tenant, $metrics);

            $this->restoreLandlordConnection();
            $GLOBALS['kt_saas_current_tenant'] = $tenantBackup;
            $GLOBALS['kt_saas_current_profile'] = $profileBackup;
            $this->CI->config->set_item('kt_saas_current_tenant', $tenantBackup);
            $this->CI->config->set_item('kt_saas_current_profile', $profileBackup);

            $this->logUsageActivity('usage.snapshot_recalculated', 'info', $tenant, $metrics);
            $this->dispatchQuotaWarnings($tenant, $metrics);

            return [
                'success'     => true,
                'tenant_id'   => (int) $tenant['id'],
                'tenant_code' => $tenant['tenant_code'],
                'metrics'     => $metrics,
            ];
        } catch (Throwable $e) {
            $this->restoreLandlordConnection();
            $GLOBALS['kt_saas_current_tenant'] = $tenantBackup;
            $GLOBALS['kt_saas_current_profile'] = $profileBackup;
            $this->CI->config->set_item('kt_saas_current_tenant', $tenantBackup);
            $this->CI->config->set_item('kt_saas_current_profile', $profileBackup);

            $this->logUsageActivity('usage.snapshot_failed', 'danger', $tenant, [
                'message' => $e->getMessage(),
            ]);

            return [
                'success'     => false,
                'tenant_id'   => (int) $tenant['id'],
                'tenant_code' => $tenant['tenant_code'],
                'message'     => $e->getMessage(),
            ];
        }
    }

    public function listEligibleTenants($limit = 100)
    {
        return $this->getEligibleTenants($limit);
    }

    protected function getEligibleTenants($limit)
    {
        $statuses = kt_saas_tenant_runtime_statuses();

        return $this->landlordDb
            ->select('t.*, p.plan_code, p.plan_name')
            ->from(db_prefix() . 'kt_saas_tenants t')
            ->join(db_prefix() . 'kt_saas_plans p', 'p.id = t.plan_id', 'left')
            ->where('t.deleted_at IS NULL', null, false)
            ->where('t.provisioning_status', 'done')
            ->where_in('t.status', $statuses)
            ->order_by('t.id', 'asc')
            ->limit((int) $limit)
            ->get()
            ->result_array();
    }

    protected function restoreLandlordConnection()
    {
        $this->CI->db = $this->landlordDb;
        $this->CI->config->set_item('kt_saas_landlord_db', $this->landlordDb);
    }

    protected function logUsageActivity($eventKey, $severity, array $tenant, array $context)
    {
        $this->landlordDb->insert(db_prefix() . 'kt_saas_activity_logs', [
            'tenant_id'    => (int) ($tenant['id'] ?? 0) ?: null,
            'actor_type'   => 'system',
            'actor_id'     => null,
            'event_key'    => $eventKey,
            'severity'     => $severity,
            'ip_address'   => $this->CI->input->ip_address(),
            'user_agent'   => substr((string) $this->CI->input->user_agent(), 0, 255),
            'context_json' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    protected function dispatchQuotaWarnings(array $tenant, array $metrics)
    {
        require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');

        $entitlements = new TenantEntitlementService();
        $profile = $entitlements->getRuntimeProfile($tenant);
        $limits = $profile['limits'] ?? [];

        $bestMetric = null;
        foreach ($metrics as $metricKey => $usedValue) {
            $limitValue = (float) ($limits[$metricKey] ?? 0);
            if ($limitValue <= 0) {
                continue;
            }

            $ratio = ((float) $usedValue / $limitValue) * 100;
            if ($ratio < 80) {
                continue;
            }

            if (!$bestMetric || $ratio > $bestMetric['ratio']) {
                $bestMetric = [
                    'metric_key' => $metricKey,
                    'used_value' => (float) $usedValue,
                    'limit_value' => $limitValue,
                    'remaining_value' => max(0, $limitValue - (float) $usedValue),
                    'ratio' => $ratio,
                ];
            }
        }

        if (!$bestMetric) {
            return 0;
        }

        $plan = !empty($tenant['plan_id']) ? $this->CI->Kt_saas_model->get_plan((int) $tenant['plan_id']) : null;
        $subscription = method_exists($this->CI->Kt_saas_model, 'get_current_subscription')
            ? $this->CI->Kt_saas_model->get_current_subscription((int) $tenant['id'])
            : null;
        $thresholds = [80, 90, 95];
        $sent = 0;
        foreach ($thresholds as $threshold) {
            if ($bestMetric['ratio'] < $threshold) {
                continue;
            }

            $context = [
                'tenant_id' => (int) $tenant['id'],
                'tenant' => $tenant,
                'subscription' => $subscription ?: [],
                'plan' => $plan ?: [],
                'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['company_name'] ?? ''),
                'owner_email' => (string) ($tenant['owner_email'] ?? ''),
                'tenant_name' => (string) ($tenant['company_name'] ?? ''),
                'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
                'workspace_url' => function_exists('kt_saas_tenant_public_base_url') ? rtrim((string) kt_saas_tenant_public_base_url($tenant), '/') : '',
                'workspace_domain' => trim((string) ($tenant['custom_domain'] ?? $tenant['subdomain'] ?? '')),
                'plan_name' => (string) ($plan['plan_name'] ?? ''),
                'quota_remaining' => (string) $bestMetric['remaining_value'],
                'quota_limit' => (string) $bestMetric['limit_value'],
                'subscription_status' => (string) ($subscription['status'] ?? ''),
                'related_type' => 'usage',
                'related_id' => (string) $tenant['id'],
                'quota_metric' => (string) $bestMetric['metric_key'],
                'quota_threshold' => (string) $threshold,
                'quota_ratio' => number_format((float) $bestMetric['ratio'], 2, '.', ''),
                'dedupe_key' => 'tenant_quota_warning|' . (int) $tenant['id'] . '|' . (string) $bestMetric['metric_key'] . '|' . $threshold . '|' . date('Y-m'),
            ];

            $result = $this->CI->Kt_saas_model->send_email_event('tenant_quota_warning', $context);
            if (!empty($result['success'])) {
                $sent++;
            }
        }

        return $sent;
    }
}
