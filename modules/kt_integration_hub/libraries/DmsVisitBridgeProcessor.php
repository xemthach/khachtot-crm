<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/DmsVisitBridgeException.php');

class DmsVisitBridgeProcessor
{
    public const PROVIDER_CODE = 'salesflow_dms';
    private const SNAPSHOT_TABLE = 'dms_visit_snapshots';
    private const JOB_TYPE = 'sync_visit_snapshot';
    protected array $currentPayload = [];
    protected string $currentRequestId = '';
    protected string $currentIdempotencyKey = '';

    public function handle(array $tenant, array $connection, array $payload, array $requestContext = []): array
    {
        $visit = (array) ($payload['visit'] ?? []);
        $tenantId = (int) ($tenant['id'] ?? 0);
        $connectionId = (int) ($connection['id'] ?? 0);
        $visitId = (int) ($visit['visit_id'] ?? 0);
        $requestId = trim((string) ($requestContext['request_id'] ?? ''));
        $idempotencyKey = trim((string) ($requestContext['idempotency_key'] ?? ''));
        $checksum = sha1(kt_integration_hub_json_encode($payload));
        $this->currentPayload = $payload;
        $this->currentRequestId = $requestId;
        $this->currentIdempotencyKey = $idempotencyKey;

        $this->guardValidPayload($tenantId, $visit);
        $this->guardIdempotency($tenantId, $idempotencyKey, $visitId, $checksum);

        $tenantDb = $this->tenantDb($tenant);

        $staff = $this->findStaff($tenantDb, (int) ($visit['crm_staff_id'] ?? 0));
        if (! $staff) {
            $this->recordFailure($tenantId, $connectionId, $visit, $checksum, 'failed', 'STAFF_NOT_MAPPED', 'CRM staff is not mapped for this tenant.');

            throw new DmsVisitBridgeException('STAFF_NOT_MAPPED', 'CRM staff is not mapped for this tenant.', 409);
        }

        $customer = $this->findCustomer($tenantDb, (int) ($visit['crm_customer_id'] ?? 0));
        if (! $customer) {
            $this->recordFailure($tenantId, $connectionId, $visit, $checksum, 'failed', 'CUSTOMER_NOT_MAPPED', 'CRM customer is not mapped for this tenant.');

            throw new DmsVisitBridgeException('CUSTOMER_NOT_MAPPED', 'CRM customer is not mapped for this tenant.', 409);
        }

        $existing = $this->getSnapshotRecord($tenantId, $visitId);
        if ($existing) {
            $existingChecksum = sha1((string) ($existing['payload_json'] ?? ''));
            if ($existingChecksum === $checksum) {
                $result = $this->buildResult($visit, (int) $existing['id'], true);
                $this->persistSyncJob($tenantId, $connectionId, $visit, $requestId, $idempotencyKey, $payload, $result, true);
                $this->upsertLocationMarkers($tenantId, (int) ($payload['tenant']['crm_company_id'] ?? $tenantId), $visit);

                return $result;
            }
        }

        $record = $this->upsertSnapshotRecord($tenantId, $connectionId, $visit, $payload);
        $snapshotId = (int) ($record['id'] ?? 0);
        $result = $this->buildResult($visit, $snapshotId, false);
        $this->persistSyncJob($tenantId, $connectionId, $visit, $requestId, $idempotencyKey, $payload, $result, false);
        $this->upsertEntityLink($tenantId, $connectionId, $snapshotId, $visit, $payload, $result);
        $this->upsertLocationMarkers($tenantId, (int) ($payload['tenant']['crm_company_id'] ?? $tenantId), $visit);
        $this->logSuccess($tenantId, $connectionId, $visit, $requestId, $idempotencyKey, $result);

        return $result;
    }

    protected function guardValidPayload(int $tenantId, array $visit): void
    {
        if ($tenantId <= 0) {
            throw new DmsVisitBridgeException('TENANT_SCOPE_MISMATCH', 'Tenant context is invalid.', 409);
        }

        foreach (['visit_id', 'crm_staff_id', 'crm_customer_id', 'store_id'] as $field) {
            if ((int) ($visit[$field] ?? 0) <= 0) {
                throw new DmsVisitBridgeException('VALIDATION_ERROR', $field . ' is required.', 422, [
                    'visit.' . $field => [$field . ' is required.'],
                ]);
            }
        }

        if (trim((string) ($visit['checkin_at'] ?? '')) === '') {
            throw new DmsVisitBridgeException('VALIDATION_ERROR', 'checkin_at is required.', 422, [
                'visit.checkin_at' => ['checkin_at is required.'],
            ]);
        }

        if (trim((string) ($visit['completion_status'] ?? '')) === '') {
            throw new DmsVisitBridgeException('VALIDATION_ERROR', 'completion_status is required.', 422, [
                'visit.completion_status' => ['completion_status is required.'],
            ]);
        }

        $completionStatus = strtolower(trim((string) ($visit['completion_status'] ?? '')));
        if (! in_array($completionStatus, ['completed', 'partial', 'cancelled'], true)) {
            throw new DmsVisitBridgeException('VALIDATION_ERROR', 'Invalid completion status.', 422, [
                'visit.completion_status' => ['completion_status must be completed, partial, or cancelled.'],
            ]);
        }

        if (trim((string) ($visit['updated_at'] ?? '')) === '') {
            throw new DmsVisitBridgeException('VALIDATION_ERROR', 'updated_at is required.', 422, [
                'visit.updated_at' => ['updated_at is required.'],
            ]);
        }

        if (isset($visit['route_id']) && $visit['route_id'] !== null && (int) $visit['route_id'] <= 0) {
            throw new DmsVisitBridgeException('VALIDATION_ERROR', 'route_id must be a positive integer when provided.', 422, [
                'visit.route_id' => ['route_id must be a positive integer when provided.'],
            ]);
        }
    }

    protected function guardIdempotency(int $tenantId, string $idempotencyKey, int $externalId, string $checksum): void
    {
        if ($idempotencyKey === '') {
            throw new DmsVisitBridgeException('VALIDATION_ERROR', 'Idempotency key is required.', 422);
        }

        $job = $this->getSyncJob($tenantId, $externalId);
        if (! is_array($job)) {
            return;
        }

        $existingKey = trim((string) ($job['idempotency_key'] ?? ''));
        $existingChecksum = sha1((string) ($job['payload_json'] ?? ''));
        if ($existingKey === $idempotencyKey && $existingChecksum !== '' && $existingChecksum !== $checksum) {
            throw new DmsVisitBridgeException('IDEMPOTENCY_KEY_REUSED', 'Idempotency key was already used with a different payload.', 409);
        }
    }

    protected function findCustomer($db, int $crmCustomerId): ?array
    {
        if ($crmCustomerId <= 0) {
            return null;
        }

        $row = $db
            ->where('userid', $crmCustomerId)
            ->get(db_prefix() . 'clients')
            ->row_array();

        return $row ?: null;
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

    protected function getSnapshotRecord(int $tenantId, int $visitId): ?array
    {
        if ($tenantId <= 0 || $visitId <= 0) {
            return null;
        }

        $row = $this->db()
            ->where('tenant_id', $tenantId)
            ->where('visit_id', $visitId)
            ->get(db_prefix() . self::SNAPSHOT_TABLE)
            ->row_array();

        return $row ?: null;
    }

    protected function upsertSnapshotRecord(int $tenantId, int $connectionId, array $visit, array $payload): array
    {
        $table = db_prefix() . self::SNAPSHOT_TABLE;
        $startedAt = $this->normalizeDateTime((string) ($visit['started_at'] ?? $visit['checkin_at'] ?? ''));
        $endedAt = $this->normalizeDateTime((string) ($visit['ended_at'] ?? $visit['updated_at'] ?? $visit['checkin_at'] ?? ''));
        $durationMinutes = isset($visit['duration_minutes']) && $visit['duration_minutes'] !== null && is_numeric($visit['duration_minutes'])
            ? (int) $visit['duration_minutes']
            : $this->computeDurationMinutes($startedAt, $endedAt);
        $record = [
            'tenant_id' => $tenantId,
            'visit_id' => (int) ($visit['visit_id'] ?? 0),
            'crm_staff_id' => (int) ($visit['crm_staff_id'] ?? 0),
            'crm_customer_id' => (int) ($visit['crm_customer_id'] ?? 0),
            'store_id' => (int) ($visit['store_id'] ?? 0),
            'route_id' => isset($visit['route_id']) && $visit['route_id'] !== null && (int) $visit['route_id'] > 0 ? (int) $visit['route_id'] : null,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_minutes' => $durationMinutes,
            'visit_status' => strtolower(trim((string) ($visit['completion_status'] ?? 'partial'))),
            'notes' => trim((string) ($visit['notes'] ?? '')) ?: null,
            'payload_json' => kt_integration_hub_json_encode($payload),
        ];

        $existing = $this->getSnapshotRecord($tenantId, (int) ($visit['visit_id'] ?? 0));
        if ($existing) {
            $this->db()->where('id', (int) $existing['id'])->update($table, $record);
            $record['id'] = (int) $existing['id'];

            return $record;
        }

        $record['created_at'] = kt_integration_hub_now();
        $this->db()->insert($table, $record);
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

    protected function persistSyncJob(int $tenantId, int $connectionId, array $visit, string $requestId, string $idempotencyKey, array $payload, array $result, bool $duplicate): void
    {
        $jobTable = db_prefix() . 'kt_integration_sync_jobs';
        $externalId = (int) ($visit['visit_id'] ?? 0);
        $record = [
            'tenant_id' => $tenantId,
            'connection_id' => $connectionId,
            'provider_code' => self::PROVIDER_CODE,
            'job_type' => self::JOB_TYPE,
            'entity_type' => 'visit',
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
            $incomingChecksum = sha1(kt_integration_hub_json_encode($payload));
            if ((string) ($existing['idempotency_key'] ?? '') === $idempotencyKey && $existingChecksum !== $incomingChecksum) {
                throw new DmsVisitBridgeException('IDEMPOTENCY_KEY_REUSED', 'Idempotency key was already used with a different payload.', 409);
            }

            $this->db()->where('id', (int) $existing['id'])->update($jobTable, $record);

            return;
        }

        $record['created_at'] = kt_integration_hub_now();
        $this->db()->insert($jobTable, $record);
    }

    protected function upsertEntityLink(int $tenantId, int $connectionId, int $snapshotId, array $visit, array $payload, array $result): void
    {
        $this->integrationModel()->upsert_provider_entity_link(
            $tenantId,
            self::PROVIDER_CODE,
            $connectionId,
            'visit',
            $snapshotId,
            (string) ((int) ($visit['visit_id'] ?? 0)),
            [
                'payload' => $payload,
                'response' => $result,
                'visit_id' => (int) ($visit['visit_id'] ?? 0),
            ],
            'inbound'
        );
    }

    protected function upsertLocationMarkers(int $tenantId, int $companyId, array $visit): void
    {
        $model = $this->integrationModel();
        $now = kt_integration_hub_now();
        $visitId = (int) ($visit['visit_id'] ?? 0);
        $staffId = (int) ($visit['crm_staff_id'] ?? 0);
        $storeId = (int) ($visit['store_id'] ?? 0);
        $status = strtolower(trim((string) ($visit['completion_status'] ?? 'partial')));

        if (is_numeric($visit['checkout_latitude'] ?? null) && is_numeric($visit['checkout_longitude'] ?? null)) {
            $checkoutId = (int) ($visit['checkout_id'] ?? 0);
            $model->upsert_dms_location_projection_marker($tenantId, $companyId > 0 ? $companyId : $tenantId, [
                'marker_type' => 'checkout',
                'source_record_type' => 'checkout',
                'source_record_id' => $checkoutId > 0 ? $checkoutId : $visitId,
                'staff_id' => $staffId,
                'store_id' => $storeId,
                'latitude' => (float) $visit['checkout_latitude'],
                'longitude' => (float) $visit['checkout_longitude'],
                'accuracy' => isset($visit['checkout_gps_accuracy']) && is_numeric($visit['checkout_gps_accuracy']) ? (float) $visit['checkout_gps_accuracy'] : null,
                'location_source' => 'checkout_gps',
                'recorded_at' => $visit['checkout_at'] ?? ($visit['ended_at'] ?? $now),
                'label' => 'Check-out #' . ($checkoutId > 0 ? $checkoutId : $visitId),
                'status' => $status,
                'metadata' => [
                    'visit_id' => $visitId,
                    'checkin_id' => (int) ($visit['checkin_id'] ?? 0),
                    'location_contract' => 'captured checkout GPS from DMS payload',
                ],
            ]);
        }

        if (is_numeric($visit['visit_latitude'] ?? null) && is_numeric($visit['visit_longitude'] ?? null)) {
            $model->upsert_dms_location_projection_marker($tenantId, $companyId > 0 ? $companyId : $tenantId, [
                'marker_type' => 'visit',
                'source_record_type' => 'visit',
                'source_record_id' => $visitId,
                'staff_id' => $staffId,
                'store_id' => $storeId,
                'latitude' => (float) $visit['visit_latitude'],
                'longitude' => (float) $visit['visit_longitude'],
                'accuracy' => isset($visit['visit_gps_accuracy']) && is_numeric($visit['visit_gps_accuracy']) ? (float) $visit['visit_gps_accuracy'] : null,
                'location_source' => 'visit_gps',
                'recorded_at' => $visit['ended_at'] ?? ($visit['updated_at'] ?? $now),
                'label' => 'Visit #' . $visitId,
                'status' => $status,
                'metadata' => [
                    'checkin_id' => (int) ($visit['checkin_id'] ?? 0),
                    'location_contract' => 'captured visit/check-in GPS from DMS payload',
                ],
            ]);
        }
    }

    protected function recordFailure(int $tenantId, int $connectionId, array $visit, string $checksum, string $bridgeStatus, string $errorCode, string $errorMessage): void
    {
        $this->persistFailedSyncJob($tenantId, $connectionId, $visit, $this->currentPayload, $this->currentRequestId, $this->currentIdempotencyKey, $errorMessage);
        $this->integrationModel()->log(
            'warning',
            'visit.sync.failed',
            $errorMessage,
            [
                'visit_id' => (int) ($visit['visit_id'] ?? 0),
                'checksum' => $checksum,
                'bridge_status' => $bridgeStatus,
                'error_code' => $errorCode,
            ],
            $tenantId,
            $connectionId,
            self::PROVIDER_CODE
        );
    }

    protected function persistFailedSyncJob(int $tenantId, int $connectionId, array $visit, array $payload, string $requestId, string $idempotencyKey, string $errorMessage): void
    {
        $externalId = (int) ($visit['visit_id'] ?? 0);
        $record = [
            'tenant_id' => $tenantId,
            'connection_id' => $connectionId,
            'provider_code' => self::PROVIDER_CODE,
            'job_type' => self::JOB_TYPE,
            'entity_type' => 'visit',
            'external_id' => (string) $externalId,
            'idempotency_key' => $idempotencyKey,
            'request_id' => $requestId,
            'status' => 'failed',
            'attempts' => 1,
            'max_attempts' => 5,
            'payload_json' => kt_integration_hub_json_encode($payload),
            'result_json' => null,
            'error_message' => $errorMessage,
            'available_at' => null,
            'updated_at' => kt_integration_hub_now(),
        ];
        $existing = $this->getSyncJob($tenantId, $externalId);
        if ($existing) {
            $this->db()->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_integration_sync_jobs', $record);
            return;
        }
        $record['created_at'] = kt_integration_hub_now();
        $this->db()->insert(db_prefix() . 'kt_integration_sync_jobs', $record);
    }

    protected function logSuccess(int $tenantId, int $connectionId, array $visit, string $requestId, string $idempotencyKey, array $result): void
    {
        $this->integrationModel()->log(
            'info',
            'visit.sync.success',
            'DMS visit snapshot synchronized.',
            [
                'visit_id' => (int) ($visit['visit_id'] ?? 0),
                'request_id' => $requestId,
                'idempotency_key' => $idempotencyKey,
                'code' => (string) ($result['code'] ?? ''),
                'duplicate' => (bool) ($result['duplicate'] ?? false),
            ],
            $tenantId,
            $connectionId,
            self::PROVIDER_CODE
        );
    }

    protected function buildResult(array $visit, int $snapshotId, bool $duplicate): array
    {
        return [
            'success' => true,
            'code' => $duplicate ? 'VISIT_ALREADY_SYNCED' : 'VISIT_ACCEPTED',
            'message' => $duplicate ? 'Visit summary already synchronized.' : 'Visit summary accepted.',
            'visit_id' => (int) ($visit['visit_id'] ?? 0),
            'crm_snapshot_id' => $snapshotId,
            'mapping_status' => 'synced',
            'duplicate' => $duplicate,
        ];
    }

    protected function computeDurationMinutes(string $startedAt, string $endedAt): ?int
    {
        $start = strtotime($startedAt);
        $end = strtotime($endedAt);
        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        return (int) round(($end - $start) / 60);
    }

    protected function normalizeDateTime(string $value): string
    {
        $timestamp = strtotime($value);

        return $timestamp !== false ? gmdate('Y-m-d H:i:s', $timestamp) : gmdate('Y-m-d H:i:s');
    }

    protected function integrationModel()
    {
        $CI = &get_instance();
        $CI->load->model(KT_INTEGRATION_HUB_MODULE . '/Kt_integration_model');

        return $CI->Kt_integration_model;
    }

    protected function db()
    {
        $CI = &get_instance();
        $landlordDb = isset($CI->config) ? $CI->config->item('kt_saas_landlord_db') : null;
        if (is_object($landlordDb)) {
            return $landlordDb;
        }
        if (! isset($CI->db)) {
            throw new RuntimeException('Database instance is not available.');
        }

        return $CI->db;
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
            throw new DmsVisitBridgeException('TENANT_SCOPE_MISMATCH', 'Tenant database context is incomplete.', 409);
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

        throw new DmsVisitBridgeException('TENANT_SCOPE_MISMATCH', 'Tenant database is unavailable.', 409);
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
            return $db->table_exists(db_prefix() . 'staff') && $db->table_exists(db_prefix() . 'clients') ? $db : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}
