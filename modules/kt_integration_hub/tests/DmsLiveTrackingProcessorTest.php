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

require_once dirname(__DIR__) . '/libraries/DmsLiveTrackingBridgeException.php';
require_once dirname(__DIR__) . '/libraries/DmsLiveTrackingBridgeProcessor.php';

class FakeDmsLiveTrackingBridgeProcessor extends DmsLiveTrackingBridgeProcessor
{
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

    protected function findStaff($db, int $crmStaffId): ?array
    {
        return $this->staff[$crmStaffId] ?? null;
    }

    protected function getSnapshotRecord(int $tenantId, int $dmsUserId): ?array
    {
        return $this->snapshots[$tenantId . ':' . $dmsUserId] ?? null;
    }

    protected function upsertSnapshotRecord(int $tenantId, int $connectionId, array $tracking, array $payload, string $checksum): array
    {
        $key = $tenantId . ':' . (int) ($tracking['dms_user_id'] ?? 0);
        $record = $this->snapshots[$key] ?? [
            'id' => $this->nextSnapshotId++,
            'tenant_id' => $tenantId,
            'dms_user_id' => (int) ($tracking['dms_user_id'] ?? 0),
        ];

        $record['crm_staff_id'] = (int) ($tracking['crm_staff_id'] ?? 0);
        $record['latitude'] = (float) ($tracking['latitude'] ?? 0);
        $record['longitude'] = (float) ($tracking['longitude'] ?? 0);
        $record['last_seen_at'] = (string) ($tracking['last_seen_at'] ?? '');
        $record['tracking_status'] = (string) ($tracking['tracking_status'] ?? 'offline');
        $record['payload_json'] = kt_integration_hub_json_encode($payload);

        $this->snapshots[$key] = $record;

        return $record;
    }

    protected function getSyncJob(int $tenantId, int $externalId): ?array
    {
        return $this->jobs[$tenantId . ':' . $externalId] ?? null;
    }

    protected function persistSyncJob(int $tenantId, int $connectionId, array $tracking, string $requestId, string $idempotencyKey, array $payload, array $result, bool $duplicate): void
    {
        $key = $tenantId . ':' . (int) ($tracking['dms_user_id'] ?? 0);
        $existing = $this->jobs[$key] ?? null;
        if ($existing && (string) ($existing['idempotency_key'] ?? '') === $idempotencyKey) {
            $existingChecksum = sha1((string) ($existing['payload_json'] ?? ''));
            $incomingChecksum = sha1(kt_integration_hub_json_encode($payload));
            if ($existingChecksum !== $incomingChecksum) {
                throw new DmsLiveTrackingBridgeException('IDEMPOTENCY_KEY_REUSED', 'Idempotency key was already used with a different payload.', 409);
            }
        }

        $this->jobs[$key] = [
            'tenant_id' => $tenantId,
            'connection_id' => $connectionId,
            'provider_code' => 'salesflow_dms',
            'job_type' => 'sync_live_tracking_snapshot',
            'entity_type' => 'live_tracking',
            'external_id' => (string) ($tracking['dms_user_id'] ?? 0),
            'idempotency_key' => $idempotencyKey,
            'request_id' => $requestId,
            'payload_json' => kt_integration_hub_json_encode($payload),
            'result_json' => kt_integration_hub_json_encode($result),
            'status' => 'done',
        ];
    }

    protected function upsertEntityLinkRecord(int $tenantId, string $providerCode, int $connectionId, string $entityType, int $localId, string $externalId, array $payload, ?string $direction = null): void
    {
        $this->links[$tenantId . ':' . (int) $externalId] = [
            'tenant_id' => $tenantId,
            'connection_id' => $connectionId,
            'entity_type' => $entityType,
            'local_id' => $localId,
            'external_id' => $externalId,
            'direction' => $direction,
            'payload' => $payload,
        ];
    }

    protected function recordFailure(int $tenantId, int $connectionId, array $tracking, string $checksum, string $bridgeStatus, string $errorCode, string $errorMessage): void
    {
        $this->logs[] = compact('tenantId', 'connectionId', 'tracking', 'checksum', 'bridgeStatus', 'errorCode', 'errorMessage');
    }

    protected function logSuccess(int $tenantId, int $connectionId, array $tracking, string $requestId, string $idempotencyKey, array $result): void
    {
        $this->logs[] = compact('tenantId', 'connectionId', 'tracking', 'requestId', 'idempotencyKey', 'result');
    }

    protected function db()
    {
        throw new RuntimeException('Database access should not be used in fake live tracking processor.');
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
    'tracking' => [
        'dms_user_id' => 55001,
        'crm_staff_id' => 5001,
        'latitude' => 10.7769,
        'longitude' => 106.7009,
        'last_seen_at' => '2026-06-09T10:00:30+07:00',
        'tracking_status' => 'online',
        'battery_level' => 84,
        'device_info' => 'Mozilla/5.0',
        'user_local_time' => '2026-06-09 10:00:30',
        'updated_at' => '2026-06-09T10:00:30+07:00',
    ],
];

$processor = new FakeDmsLiveTrackingBridgeProcessor();
$processor->staff[5001] = ['staffid' => 5001];

$result = $processor->handle($tenant, $connection, $payload, [
    'request_id' => 'req-live-tracking-1',
    'idempotency_key' => 'tracking-55001-2026-06-09T10:00:30+07:00',
]);
if (($result['code'] ?? '') !== 'LIVE_TRACKING_ACCEPTED') {
    throw new RuntimeException('Expected first live tracking sync to succeed.');
}

$duplicate = $processor->handle($tenant, $connection, $payload, [
    'request_id' => 'req-live-tracking-2',
    'idempotency_key' => 'tracking-55001-2026-06-09T10:00:30+07:00',
]);
if (($duplicate['code'] ?? '') !== 'LIVE_TRACKING_ALREADY_SYNCED') {
    throw new RuntimeException('Expected duplicate live tracking sync to be idempotent.');
}

$updatedPayload = $payload;
$updatedPayload['tracking']['tracking_status'] = 'stale';
$updatedPayload['tracking']['last_seen_at'] = '2026-06-09T10:08:30+07:00';
$updatedResult = $processor->handle($tenant, $connection, $updatedPayload, [
    'request_id' => 'req-live-tracking-3',
    'idempotency_key' => 'tracking-55001-2026-06-09T10:08:30+07:00',
]);
if (($updatedResult['code'] ?? '') !== 'LIVE_TRACKING_ACCEPTED') {
    throw new RuntimeException('Expected live tracking snapshot update to succeed.');
}

$missingStaff = new FakeDmsLiveTrackingBridgeProcessor();
try {
    $missingStaff->handle($tenant, $connection, $payload, [
        'request_id' => 'req-live-tracking-4',
        'idempotency_key' => 'tracking-55001-2026-06-09T10:00:30+07:00',
    ]);
    throw new RuntimeException('Expected missing staff mapping to be rejected.');
} catch (DmsLiveTrackingBridgeException $e) {
    if ($e->apiCode() !== 'STAFF_NOT_MAPPED') {
        throw new RuntimeException('Expected STAFF_NOT_MAPPED for missing staff mapping.');
    }
}

$idempotencyConflict = new FakeDmsLiveTrackingBridgeProcessor();
$idempotencyConflict->staff[5001] = ['staffid' => 5001];
$idempotencyConflict->jobs['1001:55001'] = [
    'idempotency_key' => 'tracking-55001-fixed',
    'payload_json' => kt_integration_hub_json_encode($payload),
];
try {
    $idempotencyConflict->handle($tenant, $connection, $updatedPayload, [
        'request_id' => 'req-live-tracking-5',
        'idempotency_key' => 'tracking-55001-fixed',
    ]);
    throw new RuntimeException('Expected idempotency key reuse with different payload to be rejected.');
} catch (DmsLiveTrackingBridgeException $e) {
    if ($e->apiCode() !== 'IDEMPOTENCY_KEY_REUSED') {
        throw new RuntimeException('Expected IDEMPOTENCY_KEY_REUSED for changed payload.');
    }
}

echo "DmsLiveTrackingBridgeProcessorTest passed.\n";
