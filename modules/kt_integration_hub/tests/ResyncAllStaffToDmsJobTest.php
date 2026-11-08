<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);
defined('KT_INTEGRATION_HUB_MODULE') or define('KT_INTEGRATION_HUB_MODULE', 'kt_integration_hub');

require_once dirname(__DIR__) . '/libraries/ResyncAllStaffToDmsJob.php';

class ResyncAllStaffToDmsJobSpy extends ResyncAllStaffToDmsJob
{
    public array $tenants = [];
    public array $staffByTenant = [];
    public array $queued = [];
    public bool $processedOnce = false;

    protected function getTenants(?int $tenantId = null): array
    {
        if ($tenantId !== null) {
            return array_values(array_filter($this->tenants, static function ($tenant) use ($tenantId) {
                return (int) ($tenant['id'] ?? 0) === $tenantId;
            }));
        }

        return $this->tenants;
    }

    protected function getSalesflowConnection(int $tenantId): ?array
    {
        return [
            'id' => 77,
            'tenant_id' => $tenantId,
        ];
    }

    protected function getActiveStaffMembers(int $tenantId): array
    {
        return $this->staffByTenant[$tenantId] ?? [];
    }

    protected function queueStaffSyncJob(int $tenantId, int $staffId, ?int $connectionId = null): array
    {
        $this->queued[] = [
            'tenant_id' => $tenantId,
            'staff_id' => $staffId,
            'connection_id' => $connectionId,
        ];

        return [
            'success' => true,
            'job_id' => count($this->queued),
            'request_id' => 'req-'.$tenantId.'-'.$staffId,
        ];
    }

    protected function processDueJobs(int $limit = 50): array
    {
        if ($this->processedOnce) {
            return [
                'checked' => 0,
                'done' => 0,
                'failed' => 0,
                'retry' => 0,
            ];
        }

        $this->processedOnce = true;

        return [
            'checked' => count($this->queued),
            'done' => count($this->queued),
            'failed' => 0,
            'retry' => 0,
        ];
    }
}

$job = new ResyncAllStaffToDmsJobSpy();
$job->tenants = [
    ['id' => 1001, 'tenant_code' => 'ABC'],
];
$job->staffByTenant = [
    1001 => [
        ['staffid' => 11, 'active' => 1],
        ['staffid' => 12, 'active' => 1],
    ],
];

$result = $job->handle();

if (($result['tenants'] ?? 0) !== 1) {
    throw new RuntimeException('Expected one tenant to be reprovisioned.');
}

if (($result['staff_queued'] ?? 0) !== 2) {
    throw new RuntimeException('Expected two active staff members to be queued.');
}

if (($result['jobs_done'] ?? 0) !== 2) {
    throw new RuntimeException('Expected queued jobs to be processed.');
}

echo "ResyncAllStaffToDmsJobTest passed.\n";
