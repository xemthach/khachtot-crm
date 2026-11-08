<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);
defined('KT_INTEGRATION_HUB_MODULE') or define('KT_INTEGRATION_HUB_MODULE', 'kt_integration_hub');
if (!function_exists('module_dir_path')) {
    function module_dir_path($module, $concat = '')
    {
        return dirname(__DIR__) . '/' . ltrim((string) $concat, '/');
    }
}
if (!function_exists('kt_integration_hub_json_encode')) {
    function kt_integration_hub_json_encode($value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

require_once dirname(__DIR__) . '/libraries/DmsVisitBridgeException.php';
require_once dirname(__DIR__) . '/libraries/DmsVisitBridgeProcessor.php';

class FakeDmsVisitBridgeProcessor extends DmsVisitBridgeProcessor
{
    public array $customers = [];
    public array $staff = [];
    public array $snapshots = [];
    public array $jobs = [];
    public array $links = [];
    public array $logs = [];
    public int $nextSnapshotId = 91001;

    protected function tenantDb(array $tenant)
    {
        return new stdClass();
    }

    protected function findCustomer($db, int $crmCustomerId): ?array
    {
        return $this->customers[$crmCustomerId] ?? null;
    }

    protected function findStaff($db, int $crmStaffId): ?array
    {
        return $this->staff[$crmStaffId] ?? null;
    }

    protected function getSnapshotRecord(int $tenantId, int $visitId): ?array
    {
        return $this->snapshots[$tenantId . ':' . $visitId] ?? null;
    }

    protected function upsertSnapshotRecord(int $tenantId, int $connectionId, array $visit, array $payload): array
    {
        $key = $tenantId . ':' . (int) ($visit['visit_id'] ?? 0);
        $record = $this->snapshots[$key] ?? [
            'id' => $this->nextSnapshotId++,
            'tenant_id' => $tenantId,
            'visit_id' => (int) ($visit['visit_id'] ?? 0),
        ];

        $record['crm_staff_id'] = (int) ($visit['crm_staff_id'] ?? 0);
        $record['crm_customer_id'] = (int) ($visit['crm_customer_id'] ?? 0);
        $record['store_id'] = (int) ($visit['store_id'] ?? 0);
        $record['route_id'] = isset($visit['route_id']) ? (int) $visit['route_id'] : null;
        $record['started_at'] = (string) ($visit['started_at'] ?? '');
        $record['ended_at'] = (string) ($visit['ended_at'] ?? '');
        $record['duration_minutes'] = $visit['duration_minutes'] ?? null;
        $record['visit_status'] = $visit['completion_status'] ?? 'partial';
        $record['notes'] = $visit['notes'] ?? null;
        $record['payload_json'] = kt_integration_hub_json_encode($payload);

        $this->snapshots[$key] = $record;

        return $record;
    }

    protected function getSyncJob(int $tenantId, int $externalId): ?array
    {
        return $this->jobs[$tenantId . ':' . $externalId] ?? null;
    }

    protected function persistSyncJob(int $tenantId, int $connectionId, array $visit, string $requestId, string $idempotencyKey, array $payload, array $result, bool $duplicate): void
    {
        $key = $tenantId . ':' . (int) ($visit['visit_id'] ?? 0);
        $existing = $this->jobs[$key] ?? null;
        if ($existing && (string) ($existing['idempotency_key'] ?? '') === $idempotencyKey) {
            $existingChecksum = sha1((string) ($existing['payload_json'] ?? ''));
            $incomingChecksum = sha1(kt_integration_hub_json_encode($payload));
            if ($existingChecksum !== $incomingChecksum) {
                throw new DmsVisitBridgeException('IDEMPOTENCY_KEY_REUSED', 'Idempotency key was already used with a different payload.', 409);
            }
        }

        $this->jobs[$key] = [
            'tenant_id' => $tenantId,
            'connection_id' => $connectionId,
            'provider_code' => 'salesflow_dms',
            'job_type' => 'sync_visit_snapshot',
            'entity_type' => 'visit',
            'external_id' => (string) ($visit['visit_id'] ?? 0),
            'idempotency_key' => $idempotencyKey,
            'request_id' => $requestId,
            'payload_json' => kt_integration_hub_json_encode($payload),
            'result_json' => kt_integration_hub_json_encode($result),
            'status' => 'done',
        ];
    }

    protected function upsertEntityLink(int $tenantId, int $connectionId, int $snapshotId, array $visit, array $payload, array $result): void
    {
        $this->links[$tenantId . ':' . (int) ($visit['visit_id'] ?? 0)] = [
            'tenant_id' => $tenantId,
            'connection_id' => $connectionId,
            'entity_type' => 'visit',
            'local_id' => $snapshotId,
            'external_id' => (string) ($visit['visit_id'] ?? 0),
            'payload' => $payload,
            'response' => $result,
        ];
    }

    protected function recordFailure(int $tenantId, int $connectionId, array $visit, string $checksum, string $bridgeStatus, string $errorCode, string $errorMessage): void
    {
        $this->logs[] = compact('tenantId', 'connectionId', 'visit', 'checksum', 'bridgeStatus', 'errorCode', 'errorMessage');
    }

    protected function logSuccess(int $tenantId, int $connectionId, array $visit, string $requestId, string $idempotencyKey, array $result): void
    {
        $this->logs[] = compact('tenantId', 'connectionId', 'visit', 'requestId', 'idempotencyKey', 'result');
    }

    protected function upsertLocationMarkers(int $tenantId, int $companyId, array $visit): void
    {
        // Projection persistence is covered by dedicated projection tests.
    }

    protected function db()
    {
        throw new RuntimeException('Database access should not be used in fake visit processor.');
    }
}

$tenant = ['id' => 1001, 'tenant_code' => 'ABC'];
$connection = ['id' => 77];
$payload = [
    'tenant' => [
        'crm_tenant_id' => 1001,
        'crm_company_id' => 1001,
        'tenant_code' => 'ABC',
    ],
    'visit' => [
        'visit_id' => 55001,
        'route_id' => null,
        'crm_staff_id' => 5001,
        'crm_customer_id' => 9001,
        'store_id' => 61,
        'started_at' => '2026-06-09T10:00:00+07:00',
        'ended_at' => '2026-06-09T10:18:00+07:00',
        'duration_minutes' => 18,
        'completion_status' => 'completed',
        'survey_completed' => false,
        'order_created' => false,
        'checkin_id' => 55001,
        'checkin_at' => '2026-06-09T10:00:00+07:00',
        'checkout_at' => '2026-06-09T10:18:00+07:00',
        'distance_from_store_meter' => 120,
        'photo_count' => 2,
        'note_count' => 1,
        'notes' => 'Morning visit',
        'updated_at' => '2026-06-09T10:19:00+07:00',
    ],
];

$processor = new FakeDmsVisitBridgeProcessor();
$processor->customers[9001] = ['userid' => 9001];
$processor->staff[5001] = ['staffid' => 5001];

$result = $processor->handle($tenant, $connection, $payload, [
    'request_id' => 'req-visit-1',
    'idempotency_key' => 'snapshot-61-2026-06-09T10:19:00+07:00',
]);
if (($result['code'] ?? '') !== 'VISIT_ACCEPTED') {
    throw new RuntimeException('Expected first visit sync to succeed.');
}

$duplicate = $processor->handle($tenant, $connection, $payload, [
    'request_id' => 'req-visit-2',
    'idempotency_key' => 'snapshot-61-2026-06-09T10:19:00+07:00',
]);
if (($duplicate['code'] ?? '') !== 'VISIT_ALREADY_SYNCED') {
    throw new RuntimeException('Expected duplicate visit sync to be idempotent.');
}

$updatedPayload = $payload;
$updatedPayload['visit']['completion_status'] = 'partial';
$updatedPayload['visit']['updated_at'] = '2026-06-09T10:25:00+07:00';
$updatedResult = $processor->handle($tenant, $connection, $updatedPayload, [
    'request_id' => 'req-visit-3',
    'idempotency_key' => 'snapshot-61-2026-06-09T10:25:00+07:00',
]);
if (($updatedResult['code'] ?? '') !== 'VISIT_ACCEPTED') {
    throw new RuntimeException('Expected visit snapshot update to succeed.');
}

$missingStaff = new FakeDmsVisitBridgeProcessor();
$missingStaff->customers[9001] = ['userid' => 9001];
try {
    $missingStaff->handle($tenant, $connection, $payload, [
        'request_id' => 'req-visit-4',
        'idempotency_key' => 'snapshot-61-2026-06-09T10:19:00+07:00',
    ]);
    throw new RuntimeException('Expected missing staff mapping to be rejected.');
} catch (DmsVisitBridgeException $e) {
    if ($e->apiCode() !== 'STAFF_NOT_MAPPED') {
        throw new RuntimeException('Expected STAFF_NOT_MAPPED for missing staff mapping.');
    }
}

$missingCustomer = new FakeDmsVisitBridgeProcessor();
$missingCustomer->staff[5001] = ['staffid' => 5001];
try {
    $missingCustomer->handle($tenant, $connection, $payload, [
        'request_id' => 'req-visit-5',
        'idempotency_key' => 'snapshot-61-2026-06-09T10:19:00+07:00',
    ]);
    throw new RuntimeException('Expected missing customer mapping to be rejected.');
} catch (DmsVisitBridgeException $e) {
    if ($e->apiCode() !== 'CUSTOMER_NOT_MAPPED') {
        throw new RuntimeException('Expected CUSTOMER_NOT_MAPPED for missing customer mapping.');
    }
}

$idempotencyConflict = new FakeDmsVisitBridgeProcessor();
$idempotencyConflict->customers[9001] = ['userid' => 9001];
$idempotencyConflict->staff[5001] = ['staffid' => 5001];
$idempotencyConflict->jobs['1001:55001'] = [
    'idempotency_key' => 'snapshot-61-fixed',
    'payload_json' => kt_integration_hub_json_encode($payload),
];
try {
    $idempotencyConflict->handle($tenant, $connection, $updatedPayload, [
        'request_id' => 'req-visit-6',
        'idempotency_key' => 'snapshot-61-fixed',
    ]);
    throw new RuntimeException('Expected idempotency key reuse with different payload to be rejected.');
} catch (DmsVisitBridgeException $e) {
    if ($e->apiCode() !== 'IDEMPOTENCY_KEY_REUSED') {
        throw new RuntimeException('Expected IDEMPOTENCY_KEY_REUSED for changed payload.');
    }
}

echo "DmsVisitBridgeProcessorTest passed.\n";
