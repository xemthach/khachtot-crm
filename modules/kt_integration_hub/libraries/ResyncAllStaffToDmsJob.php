<?php

defined('BASEPATH') or exit('No direct script access allowed');

class ResyncAllStaffToDmsJob
{
    public function handle(?int $tenantId = null, bool $processImmediately = true): array
    {
        $summary = [
            'tenants' => 0,
            'staff_queued' => 0,
            'staff_skipped' => 0,
            'jobs_processed' => 0,
            'jobs_done' => 0,
            'jobs_failed' => 0,
            'jobs_retry' => 0,
            'reason_code' => '',
            'reason_message' => '',
            'active_staff' => 0,
        ];

        foreach ($this->getTenants($tenantId) as $tenant) {
            $resolvedTenantId = (int) ($tenant['id'] ?? 0);
            if ($resolvedTenantId <= 0) {
                continue;
            }

            $connection = $this->getSalesflowConnection($resolvedTenantId);
            if (!is_array($connection) || empty($connection['id'])) {
                $staff = $this->getActiveStaffMembers($resolvedTenantId);
                $summary['active_staff'] += count($staff);
                $summary['staff_skipped'] += count($staff);
                $summary['reason_code'] = 'connection_missing';
                $summary['reason_message'] = 'Kiểm tra kết nối DMS.';
                continue;
            }

            $summary['tenants']++;
            $staffMembers = $this->getActiveStaffMembers($resolvedTenantId);
            $summary['active_staff'] += count($staffMembers);
            if (empty($staffMembers)) {
                $summary['reason_code'] = 'staff_missing';
                $summary['reason_message'] = 'Gán nhân sự vào DMS.';
            }
            $accessMap = $this->getStaffAccessMap($resolvedTenantId);
            usort($staffMembers, static function ($left, $right) use ($accessMap) {
                $weights = ['tenant_admin' => 0, 'regional_manager' => 1, 'field_sales' => 2];
                $leftRole = (string) ($accessMap[(int) ($left['staffid'] ?? 0)]['dms_role_code'] ?? (!empty($left['admin']) ? 'tenant_admin' : 'field_sales'));
                $rightRole = (string) ($accessMap[(int) ($right['staffid'] ?? 0)]['dms_role_code'] ?? (!empty($right['admin']) ? 'tenant_admin' : 'field_sales'));
                return ($weights[$leftRole] ?? 2) <=> ($weights[$rightRole] ?? 2);
            });

            foreach ($staffMembers as $staff) {
                $staffId = (int) ($staff['staffid'] ?? 0);
                if ($staffId <= 0) {
                    $summary['staff_skipped']++;
                    continue;
                }

                $result = $this->queueStaffSyncJob($resolvedTenantId, $staffId, (int) ($connection['id'] ?? 0));
                if (!empty($result['success'])) {
                    $summary['staff_queued']++;
                } else {
                    $summary['staff_skipped']++;
                    $summary['reason_code'] = 'queue_failed';
                    $summary['reason_message'] = (string) ($result['message'] ?? 'Kiểm tra vai trò DMS và thử lại.');
                }
            }
        }

        if ($processImmediately) {
            do {
                $batch = $this->processDueJobs(100);
                $summary['jobs_processed'] += (int) ($batch['checked'] ?? 0);
                $summary['jobs_done'] += (int) ($batch['done'] ?? 0);
                $summary['jobs_failed'] += (int) ($batch['failed'] ?? 0);
                $summary['jobs_retry'] += (int) ($batch['retry'] ?? 0);
            } while ((int) ($batch['checked'] ?? 0) > 0);
        }

        return $summary;
    }

    protected function getTenants(?int $tenantId = null): array
    {
        $CI = &get_instance();
        $CI->load->model('kt_saas/Kt_saas_model');

        if ($tenantId !== null && (int) $tenantId > 0) {
            $tenant = $CI->Kt_saas_model->get_tenant((int) $tenantId);
            return is_array($tenant) ? [$tenant] : [];
        }

        return $CI->Kt_saas_model->get_tenants(false);
    }

    protected function getSalesflowConnection(int $tenantId): ?array
    {
        $CI = &get_instance();
        $CI->load->model(KT_INTEGRATION_HUB_MODULE . '/Kt_integration_model');

        $connection = $CI->Kt_integration_model->get_salesflow_connection_for_tenant($tenantId);
        return is_array($connection) ? $connection : null;
    }

    protected function getActiveStaffMembers(int $tenantId): array
    {
        $CI = &get_instance();
        $CI->load->model('kt_saas/Kt_saas_model');

        return $CI->Kt_saas_model->get_active_tenant_staff_members_for_tenant($tenantId);
    }

    protected function queueStaffSyncJob(int $tenantId, int $staffId, ?int $connectionId = null): array
    {
        $CI = &get_instance();
        $CI->load->model(KT_INTEGRATION_HUB_MODULE . '/Kt_integration_model');

        return $CI->Kt_integration_model->queue_staff_sync_job($tenantId, $staffId, $connectionId);
    }

    protected function getStaffAccessMap(int $tenantId): array
    {
        if (!function_exists('get_instance')) {
            return [];
        }
        $CI = &get_instance();
        $CI->load->model(KT_INTEGRATION_HUB_MODULE . '/Kt_integration_model');

        return $CI->Kt_integration_model->get_dms_staff_access_config_map($tenantId);
    }

    protected function processDueJobs(int $limit = 50): array
    {
        $CI = &get_instance();
        $CI->load->model(KT_INTEGRATION_HUB_MODULE . '/Kt_integration_model');

        return $CI->Kt_integration_model->process_due_jobs($limit);
    }
}
