<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/DmsLiveTrackingBridgeException.php');

class DmsLiveTrackingBridgeProcessor
{
    public const PROVIDER_CODE = 'salesflow_dms';
    private const SNAPSHOT_TABLE = 'dms_live_tracking_snapshots';
    private const JOB_TYPE = 'sync_live_tracking_snapshot';

    public function handle(array $tenant, array $connection, array $payload, array $requestContext = []): array
    {
        $tracking = (array) ($payload['tracking'] ?? []);
        $tenantId = (int) ($tenant['id'] ?? 0);
        $connectionId = (int) ($connection['id'] ?? 0);
        $dmsUserId = (int) ($tracking['dms_user_id'] ?? 0);
        $requestId = trim((string) ($requestContext['request_id'] ?? ''));
        $idempotencyKey = trim((string) ($requestContext['idempotency_key'] ?? ''));
        $checksum = sha1(kt_integration_hub_json_encode($payload));

        $this->guardValidPayload($tenantId, $tracking);
        $this->guardIdempotency($tenantId, $idempotencyKey, $dmsUserId, $checksum);

        $tenantDb = $this->tenantDb($tenant);

        $staff = $this->findStaff($tenantDb, (int) ($tracking['crm_staff_id'] ?? 0));
        if (! $staff) {
            $this->recordFailure(
                $tenantId,
                $connectionId,
                $tracking,
                $checksum,
                'failed',
                'STAFF_NOT_MAPPED',
                'CRM staff is not mapped for this tenant.'
            );

            throw new DmsLiveTrackingBridgeException('STAFF_NOT_MAPPED', 'CRM staff is not mapped for this tenant.', 409);
        }

        $existingSnapshot = $this->getSnapshotRecord($tenantId, $dmsUserId);
        if ($existingSnapshot) {
            $existingChecksum = sha1((string) ($existingSnapshot['payload_json'] ?? ''));
            if ($existingChecksum === $checksum) {
                $result = $this->buildResult($tracking, (int) $existingSnapshot['id'], true);
                $this->persistSyncJob($tenantId, $connectionId, $tracking, $requestId, $idempotencyKey, $payload, $result, true);

                return $result;
            }
        }

        $record = $this->upsertSnapshotRecord($tenantId, $connectionId, $tracking, $payload, $checksum);
        $snapshotId = (int) ($record['id'] ?? 0);
        $result = $this->buildResult($tracking, $snapshotId, false);
        $this->persistSyncJob($tenantId, $connectionId, $tracking, $requestId, $idempotencyKey, $payload, $result, false);
        $this->upsertEntityLink($tenantId, $connectionId, $snapshotId, $tracking, $payload, $result);
        $this->logSuccess($tenantId, $connectionId, $tracking, $requestId, $idempotencyKey, $result);

        return $result;
    }

    protected function guardValidPayload(int $tenantId, array $tracking): void
    {
        if ($tenantId <= 0) {
            throw new DmsLiveTrackingBridgeException('TENANT_SCOPE_MISMATCH', 'Tenant context is invalid.', 409);
        }

        $requiredIntegerFields = [
            'dms_user_id',
            'crm_staff_id',
        ];

        foreach ($requiredIntegerFields as $field) {
            if ((int) ($tracking[$field] ?? 0) <= 0) {
                throw new DmsLiveTrackingBridgeException('VALIDATION_ERROR', $field . ' is required.', 422, [
                    'tracking.' . $field => [$field . ' is required.'],
                ]);
            }
        }

        foreach (['latitude', 'longitude', 'last_seen_at', 'tracking_status'] as $field) {
            if (! array_key_exists($field, $tracking) || trim((string) ($tracking[$field] ?? '')) === '') {
                throw new DmsLiveTrackingBridgeException('VALIDATION_ERROR', $field . ' is required.', 422, [
                    'tracking.' . $field => [$field . ' is required.'],
                ]);
            }
        }

        if (! is_numeric($tracking['latitude']) || ! is_numeric($tracking['longitude'])) {
            throw new DmsLiveTrackingBridgeException('VALIDATION_ERROR', 'Latitude and longitude must be numeric.', 422, [
                'tracking.latitude' => ['Latitude must be numeric.'],
                'tracking.longitude' => ['Longitude must be numeric.'],
            ]);
        }

        if (! in_array(strtolower(trim((string) ($tracking['tracking_status'] ?? ''))), ['online', 'offline', 'stale'], true)) {
            throw new DmsLiveTrackingBridgeException('VALIDATION_ERROR', 'Invalid tracking status.', 422, [
                'tracking.tracking_status' => ['tracking_status must be online, offline, or stale.'],
            ]);
        }
    }

    protected function guardIdempotency(int $tenantId, string $idempotencyKey, int $externalId, string $checksum): void
    {
        if ($idempotencyKey === '') {
            throw new DmsLiveTrackingBridgeException('VALIDATION_ERROR', 'Idempotency key is required.', 422);
        }

        $job = $this->getSyncJob($tenantId, $externalId);
        if (! is_array($job)) {
            return;
        }

        $existingKey = trim((string) ($job['idempotency_key'] ?? ''));
        $existingChecksum = sha1((string) ($job['payload_json'] ?? ''));
        if ($existingKey === $idempotencyKey && $existingChecksum !== '' && $existingChecksum !== $checksum) {
            throw new DmsLiveTrackingBridgeException('IDEMPOTENCY_KEY_REUSED', 'Idempotency key was already used with a different payload.', 409);
        }
    }

    protected function findStaff($db, int $crmStaffId): ?array
    {
        if ($crmStaffId <= 0) {
            return null;
        }

        $row = $db
            ->where('staffid', $crmStaffId)
            ->get(db_prefix() . 'staff')
            ->row_array();

        return $row ?: null;
    }

    protected function getSnapshotRecord(int $tenantId, int $dmsUserId): ?array
    {
        if ($tenantId <= 0 || $dmsUserId <= 0) {
            return null;
        }

        $row = $this->db()
            ->where('tenant_id', $tenantId)
            ->where('dms_user_id', $dmsUserId)
            ->get(db_prefix() . self::SNAPSHOT_TABLE)
            ->row_array();

        return $row ?: null;
    }

    protected function upsertSnapshotRecord(int $tenantId, int $connectionId, array $tracking, array $payload, string $checksum): array
    {
        $snapshotTable = db_prefix() . self::SNAPSHOT_TABLE;
        $record = [
            'tenant_id' => $tenantId,
            'dms_user_id' => (int) ($tracking['dms_user_id'] ?? 0),
            'crm_staff_id' => (int) ($tracking['crm_staff_id'] ?? 0),
            'latitude' => (float) ($tracking['latitude'] ?? 0),
            'longitude' => (float) ($tracking['longitude'] ?? 0),
            'last_seen_at' => $this->normalizeDateTime((string) ($tracking['last_seen_at'] ?? '')),
            'tracking_status' => strtolower(trim((string) ($tracking['tracking_status'] ?? 'offline'))),
            'payload_json' => kt_integration_hub_json_encode($payload),
        ];

        $existing = $this->getSnapshotRecord($tenantId, (int) ($tracking['dms_user_id'] ?? 0));
        if ($existing) {
            $this->db()->where('id', (int) $existing['id'])->update($snapshotTable, array_merge($record, [
                'updated_at' => kt_integration_hub_now(),
            ]));
            $record['id'] = (int) $existing['id'];

            return $record;
        }

        $record['created_at'] = kt_integration_hub_now();
        $record['updated_at'] = kt_integration_hub_now();
        $this->db()->insert($snapshotTable, $record);
        $record['id'] = (int) $this->db()->insert_id();

        return $record;
    }

    protected function getSyncJob(int $tenantId, int $externalId): ?array
    {
        if ($tenantId <= 0 || $externalId <= 0) {
            return null;
        }

        $row = $this->db()
            ->where('tenant_id', $tenantId)
            ->where('provider_code', self::PROVIDER_CODE)
            ->where('job_type', self::JOB_TYPE)
            ->where('external_id', (string) $externalId)
            ->get(db_prefix() . 'kt_integration_sync_jobs')
            ->row_array();

        return $row ?: null;
    }

    protected function persistSyncJob(int $tenantId, int $connectionId, array $tracking, string $requestId, string $idempotencyKey, array $payload, array $result, bool $duplicate): void
    {
        $jobTable = db_prefix() . 'kt_integration_sync_jobs';
        $externalId = (int) ($tracking['dms_user_id'] ?? 0);
        $record = [
            'tenant_id' => $tenantId,
            'connection_id' => $connectionId,
            'provider_code' => self::PROVIDER_CODE,
            'job_type' => self::JOB_TYPE,
            'entity_type' => 'live_tracking',
            'external_id' => (string) $externalId,
            'idempotency_key' => $idempotencyKey,
            'request_id' => $requestId,
            'status' => 'done',
            'attempts' => 1,
            'max_attempts' => 1,
            'payload_json' => kt_integration_hub_json_encode($payload),
            'result_json' => kt_integration_hub_json_encode($result),
            'error_message' => null,
            'available_at' => kt_integration_hub_now(),
            'updated_at' => kt_integration_hub_now(),
        ];

        $existing = $this->getSyncJob($tenantId, $externalId);
        if ($existing) {
            $existingChecksum = sha1((string) ($existing['payload_json'] ?? ''));
            $incomingChecksum = sha1((string) $record['payload_json']);
            if ((string) ($existing['idempotency_key'] ?? '') === $idempotencyKey && $existingChecksum !== $incomingChecksum) {
                throw new DmsLiveTrackingBridgeException('IDEMPOTENCY_KEY_REUSED', 'Idempotency key was already used with a different payload.', 409);
            }

            $this->db()->where('id', (int) $existing['id'])->update($jobTable, $record);

            return;
        }

        $this->db()->insert($jobTable, $record);
    }

    protected function upsertEntityLink(int $tenantId, int $connectionId, int $snapshotId, array $tracking, array $payload, array $result): void
    {
        $this->upsertEntityLinkRecord($tenantId, self::PROVIDER_CODE, $connectionId, 'live_tracking', $snapshotId, (string) ($tracking['dms_user_id'] ?? 0), $payload, 'inbound');
    }

    protected function upsertEntityLinkRecord(int $tenantId, string $providerCode, int $connectionId, string $entityType, int $localId, string $externalId, array $payload, ?string $direction = null): void
    {
        if ($tenantId <= 0 || $localId <= 0 || trim($externalId) === '') {
            return;
        }

        $table = db_prefix() . 'kt_integration_entity_links';
        $record = [
            'tenant_id' => $tenantId,
            'provider_code' => $providerCode,
            'connection_id' => $connectionId,
            'entity_type' => $entityType,
            'local_id' => (string) $localId,
            'external_id' => trim($externalId),
            'direction' => $direction,
            'metadata_json' => kt_integration_hub_json_encode([
                'last_payload' => $payload,
            ]),
            'updated_at' => kt_integration_hub_now(),
        ];

        $existing = $this->db()
            ->where('tenant_id', $tenantId)
            ->where('provider_code', $providerCode)
            ->where('entity_type', $entityType)
            ->where('external_id', trim($externalId))
            ->get($table)
            ->row_array();

        if ($existing) {
            $this->db()->where('id', (int) $existing['id'])->update($table, $record);

            return;
        }

        $record['created_at'] = kt_integration_hub_now();
        $this->db()->insert($table, $record);
    }

    protected function recordFailure(int $tenantId, int $connectionId, array $tracking, string $checksum, string $bridgeStatus, string $errorCode, string $errorMessage): void
    {
        $this->log(
            'warning',
            'live_tracking.snapshot.failure',
            $errorMessage,
            [
                'dms_user_id' => (int) ($tracking['dms_user_id'] ?? 0),
                'crm_staff_id' => (int) ($tracking['crm_staff_id'] ?? 0),
                'checksum' => $checksum,
                'bridge_status' => $bridgeStatus,
                'error_code' => $errorCode,
            ],
            $tenantId,
            $connectionId,
            self::PROVIDER_CODE
        );
    }

    protected function logSuccess(int $tenantId, int $connectionId, array $tracking, string $requestId, string $idempotencyKey, array $result): void
    {
        $this->log(
            'info',
            'live_tracking.snapshot.sync.success',
            'Live tracking snapshot synchronized.',
            [
                'request_id' => $requestId,
                'idempotency_key' => $idempotencyKey,
                'dms_user_id' => (int) ($tracking['dms_user_id'] ?? 0),
                'crm_staff_id' => (int) ($tracking['crm_staff_id'] ?? 0),
                'result' => $result,
            ],
            $tenantId,
            $connectionId,
            self::PROVIDER_CODE
        );
    }

    protected function buildResult(array $tracking, int $snapshotId, bool $duplicate): array
    {
        return [
            'code' => $duplicate ? 'LIVE_TRACKING_ALREADY_SYNCED' : 'LIVE_TRACKING_ACCEPTED',
            'message' => $duplicate ? 'Live tracking snapshot already synchronized.' : 'Live tracking snapshot accepted.',
            'dms_user_id' => (int) ($tracking['dms_user_id'] ?? 0),
            'crm_snapshot_id' => $snapshotId,
            'tracking_status' => (string) ($tracking['tracking_status'] ?? 'offline'),
            'duplicate' => $duplicate,
        ];
    }

    protected function normalizeDateTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    protected function log(string $level, string $event, string $message, array $context = [], ?int $tenantId = null, ?int $connectionId = null, ?string $providerCode = null): void
    {
        $CI = &get_instance();
        if (! isset($CI->Kt_integration_model)) {
            $CI->load->model(KT_INTEGRATION_HUB_MODULE . '/Kt_integration_model');
        }

        $CI->Kt_integration_model->log($level, $event, $message, $context, $tenantId, $connectionId, $providerCode);
    }

    protected function tenantDb(array $tenant)
    {
        $CI = &get_instance();
        $dbName = trim((string) ($tenant['db_name'] ?? ''));
        $dbHost = trim((string) ($tenant['db_host'] ?? ''));
        $dbUser = trim((string) ($tenant['db_user'] ?? ''));
        $dbPort = trim((string) ($tenant['db_port'] ?? '3306'));
        $password = '';

        if ($dbName === '' || $dbHost === '' || $dbUser === '') {
            throw new DmsLiveTrackingBridgeException('TENANT_SCOPE_MISMATCH', 'Tenant database context is incomplete.', 409);
        }

        if (! empty($tenant['db_password_encrypted'])) {
            $password = (string) $CI->encryption->decrypt($tenant['db_password_encrypted']);
        }

        $db = $this->connectTenantDb($dbHost, $dbUser, $password, $dbName, $dbPort);
        if ($db) {
            return $db;
        }

        $fallbackUser = defined('APP_DB_USERNAME') ? trim((string) APP_DB_USERNAME) : '';
        $fallbackPassword = defined('APP_DB_PASSWORD') ? (string) APP_DB_PASSWORD : '';
        if ($fallbackUser !== '') {
            $db = $this->connectTenantDb($dbHost, $fallbackUser, $fallbackPassword, $dbName, $dbPort);
            if ($db) {
                return $db;
            }
        }

        throw new DmsLiveTrackingBridgeException('TENANT_SCOPE_MISMATCH', 'Tenant database is unavailable.', 409);
    }

    protected function connectTenantDb(string $host, string $user, string $password, string $database, string $port)
    {
        $CI = &get_instance();
        $config = [
            'dsn' => '',
            'hostname' => $host,
            'username' => $user,
            'password' => $password,
            'database' => $database,
            'dbdriver' => defined('APP_DB_DRIVER') ? APP_DB_DRIVER : 'mysqli',
            'dbprefix' => db_prefix(),
            'pconnect' => false,
            'db_debug' => false,
            'cache_on' => false,
            'cachedir' => '',
            'char_set' => defined('APP_DB_CHARSET') ? APP_DB_CHARSET : 'utf8mb4',
            'dbcollat' => defined('APP_DB_COLLATION') ? APP_DB_COLLATION : 'utf8mb4_unicode_ci',
            'swap_pre' => '',
            'encrypt' => false,
            'compress' => false,
            'stricton' => false,
            'failover' => [],
            'save_queries' => true,
            'port' => $port,
        ];

        try {
            $db = $CI->load->database($config, true);
            $db->initialize();

            return $db->table_exists(db_prefix() . 'staff') ? $db : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    protected function db()
    {
        $CI = &get_instance();
        if (! isset($CI->db)) {
            throw new RuntimeException('Database instance is not available.');
        }

        return $CI->db;
    }
}
