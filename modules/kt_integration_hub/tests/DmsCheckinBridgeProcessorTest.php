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

require_once dirname(__DIR__) . '/libraries/DmsCheckinBridgeException.php';
require_once dirname(__DIR__) . '/libraries/DmsCheckinBridgeProcessor.php';

class FakeDmsCheckinBridgeProcessor extends DmsCheckinBridgeProcessor
{
    public array $customers = [];
    public array $staff = [];
    public array $events = [];
    public array $jobs = [];
    public array $links = [];
    public array $logs = [];
    public int $nextEventId = 91001;

    protected function tenantDb(array $tenant)
    {
        return new stdClass();
    }

    protected function integrationModel()
    {
        return new class {
            public function sync_tenant_dms_usage_snapshot($tenantId)
            {
                return [];
            }
        };
    }

    protected function findCustomer($db, int $crmCustomerId): ?array
    {
        return $this->customers[$crmCustomerId] ?? null;
    }

    protected function findStaff($db, int $crmStaffId): ?array
    {
        return $this->staff[$crmStaffId] ?? null;
    }

    protected function getEventRecord(int $tenantId, int $dmsCheckinId): ?array
    {
        return $this->events[$tenantId . ':' . $dmsCheckinId] ?? null;
    }

    protected function upsertEventRecord(int $tenantId, int $connectionId, array $checkin, array $payload, string $checksum): array
    {
        $key = $tenantId . ':' . (int) ($checkin['dms_checkin_id'] ?? 0);
        $record = $this->events[$key] ?? [
            'id' => $this->nextEventId++,
            'tenant_id' => $tenantId,
            'dms_checkin_id' => (int) ($checkin['dms_checkin_id'] ?? 0),
        ];

        $record['crm_staff_id'] = (int) ($checkin['crm_staff_id'] ?? 0);
        $record['crm_customer_id'] = (int) ($checkin['crm_customer_id'] ?? 0);
        $record['store_id'] = (int) ($checkin['store_id'] ?? 0);
        $record['route_id'] = isset($checkin['route_id']) ? (int) $checkin['route_id'] : null;
        $record['checkin_at'] = (string) ($checkin['checkin_at'] ?? '');
        $record['latitude'] = (float) ($checkin['latitude'] ?? 0);
        $record['longitude'] = (float) ($checkin['longitude'] ?? 0);
        $record['accuracy_meter'] = $checkin['accuracy_meter'] ?? null;
        $record['distance_from_store_meter'] = $checkin['distance_from_store_meter'] ?? null;
        $record['photo_url'] = $checkin['photo_url'] ?? null;
        $record['status'] = $checkin['status'] ?? 'warning';
        $record['notes'] = $checkin['notes'] ?? null;
        $record['payload_json'] = kt_integration_hub_json_encode($payload);

        $this->events[$key] = $record;

        return $record;
    }

    protected function getSyncJob(int $tenantId, int $externalId): ?array
    {
        return $this->jobs[$tenantId . ':' . $externalId] ?? null;
    }

    protected function persistSyncJob(int $tenantId, int $connectionId, array $checkin, string $requestId, string $idempotencyKey, array $payload, array $result, bool $duplicate): void
    {
        $key = $tenantId . ':' . (int) ($checkin['dms_checkin_id'] ?? 0);
        $existing = $this->jobs[$key] ?? null;
        if ($existing && (string) ($existing['idempotency_key'] ?? '') === $idempotencyKey) {
            $existingChecksum = sha1((string) ($existing['payload_json'] ?? ''));
            $incomingChecksum = sha1(kt_integration_hub_json_encode($payload));
            if ($existingChecksum !== $incomingChecksum) {
                throw new DmsCheckinBridgeException('IDEMPOTENCY_KEY_REUSED', 'Idempotency key was already used with a different payload.', 409);
            }
        }

        $this->jobs[$key] = [
            'tenant_id' => $tenantId,
            'connection_id' => $connectionId,
            'provider_code' => 'salesflow_dms',
            'job_type' => 'sync_checkin_event',
            'entity_type' => 'checkin',
            'external_id' => (string) ($checkin['dms_checkin_id'] ?? 0),
            'idempotency_key' => $idempotencyKey,
            'request_id' => $requestId,
            'payload_json' => kt_integration_hub_json_encode($payload),
            'result_json' => kt_integration_hub_json_encode($result),
            'status' => 'done',
        ];
    }

    protected function upsertEntityLink(int $tenantId, int $connectionId, int $eventId, array $checkin, array $payload, array $result): void
    {
        $this->links[$tenantId . ':' . (int) ($checkin['dms_checkin_id'] ?? 0)] = [
            'tenant_id' => $tenantId,
            'connection_id' => $connectionId,
            'entity_type' => 'checkin',
            'local_id' => $eventId,
            'external_id' => (string) ($checkin['dms_checkin_id'] ?? 0),
            'payload' => $payload,
            'response' => $result,
        ];
    }

    protected function recordFailure(int $tenantId, int $connectionId, array $checkin, string $checksum, string $bridgeStatus, string $errorCode, string $errorMessage): void
    {
        $this->logs[] = compact('tenantId', 'connectionId', 'checkin', 'checksum', 'bridgeStatus', 'errorCode', 'errorMessage');
    }

    protected function logSuccess(int $tenantId, int $connectionId, array $checkin, string $requestId, string $idempotencyKey, array $result): void
    {
        $this->logs[] = compact('tenantId', 'connectionId', 'checkin', 'requestId', 'idempotencyKey', 'result');
    }

    protected function db()
    {
        throw new RuntimeException('Database access should not be used in fake check-in processor.');
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
    'checkin' => [
        'dms_checkin_id' => 55001,
        'crm_staff_id' => 5001,
        'crm_customer_id' => 9001,
        'store_id' => 61,
        'route_id' => null,
        'checkin_at' => '2026-06-09T10:00:00+07:00',
        'latitude' => 10.7769,
        'longitude' => 106.7009,
        'accuracy_meter' => null,
        'distance_from_store_meter' => 120,
        'photo_url' => 'https://cdn.example.test/checkins/checkin.jpg',
        'status' => 'warning',
        'notes' => 'Morning visit',
        'updated_at' => '2026-06-09T10:01:00+07:00',
    ],
];

$processor = new FakeDmsCheckinBridgeProcessor();
$processor->customers[9001] = ['userid' => 9001];
$processor->staff[5001] = ['staffid' => 5001];

$result = $processor->handle($tenant, $connection, $payload, [
    'request_id' => 'req-checkin-1',
    'idempotency_key' => 'checkin-55001-2026-06-09T10:01:00+07:00',
]);
if (($result['code'] ?? '') !== 'CHECKIN_EVENT_SYNCED') {
    throw new RuntimeException('Expected first check-in sync to succeed.');
}

$duplicate = $processor->handle($tenant, $connection, $payload, [
    'request_id' => 'req-checkin-2',
    'idempotency_key' => 'checkin-55001-2026-06-09T10:01:00+07:00',
]);
if (($duplicate['code'] ?? '') !== 'CHECKIN_ALREADY_SYNCED') {
    throw new RuntimeException('Expected duplicate check-in sync to be idempotent.');
}

$updatedPayload = $payload;
$updatedPayload['checkin']['status'] = 'valid';
$updatedPayload['checkin']['updated_at'] = '2026-06-09T10:05:00+07:00';
$updatedResult = $processor->handle($tenant, $connection, $updatedPayload, [
    'request_id' => 'req-checkin-3',
    'idempotency_key' => 'checkin-55001-2026-06-09T10:05:00+07:00',
]);
if (($updatedResult['code'] ?? '') !== 'CHECKIN_EVENT_SYNCED') {
    throw new RuntimeException('Expected check-in update sync to succeed.');
}

$missingStaff = new FakeDmsCheckinBridgeProcessor();
$missingStaff->customers[9001] = ['userid' => 9001];
try {
    $missingStaff->handle($tenant, $connection, $payload, [
        'request_id' => 'req-checkin-4',
        'idempotency_key' => 'checkin-55001-2026-06-09T10:01:00+07:00',
    ]);
    throw new RuntimeException('Expected missing staff mapping to be rejected.');
} catch (DmsCheckinBridgeException $e) {
    if ($e->apiCode() !== 'STAFF_NOT_MAPPED') {
        throw new RuntimeException('Expected STAFF_NOT_MAPPED for missing staff mapping.');
    }
}

$missingCustomer = new FakeDmsCheckinBridgeProcessor();
$missingCustomer->staff[5001] = ['staffid' => 5001];
try {
    $missingCustomer->handle($tenant, $connection, $payload, [
        'request_id' => 'req-checkin-5',
        'idempotency_key' => 'checkin-55001-2026-06-09T10:01:00+07:00',
    ]);
    throw new RuntimeException('Expected missing customer mapping to be rejected.');
} catch (DmsCheckinBridgeException $e) {
    if ($e->apiCode() !== 'CUSTOMER_NOT_MAPPED') {
        throw new RuntimeException('Expected CUSTOMER_NOT_MAPPED for missing customer mapping.');
    }
}

$idempotencyConflict = new FakeDmsCheckinBridgeProcessor();
$idempotencyConflict->customers[9001] = ['userid' => 9001];
$idempotencyConflict->staff[5001] = ['staffid' => 5001];
$idempotencyConflict->jobs['1001:55001'] = [
    'idempotency_key' => 'checkin-55001-fixed',
    'payload_json' => kt_integration_hub_json_encode($payload),
];
try {
    $idempotencyConflict->handle($tenant, $connection, $updatedPayload, [
        'request_id' => 'req-checkin-6',
        'idempotency_key' => 'checkin-55001-fixed',
    ]);
    throw new RuntimeException('Expected idempotency key reuse with different payload to be rejected.');
} catch (DmsCheckinBridgeException $e) {
    if ($e->apiCode() !== 'IDEMPOTENCY_KEY_REUSED') {
        throw new RuntimeException('Expected IDEMPOTENCY_KEY_REUSED for changed payload.');
    }
}

echo "DmsCheckinBridgeProcessorTest passed.\n";
