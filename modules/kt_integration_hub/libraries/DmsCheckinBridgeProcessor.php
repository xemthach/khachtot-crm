<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/DmsCheckinBridgeException.php');

class DmsCheckinBridgeProcessor
{
    public const PROVIDER_CODE = 'salesflow_dms';
    private const EVENT_TABLE = 'dms_checkin_events';
    private const JOB_TYPE = 'sync_checkin_event';
    protected array $currentPayload = [];
    protected string $currentRequestId = '';
    protected string $currentIdempotencyKey = '';

    public function handle(array $tenant, array $connection, array $payload, array $requestContext = []): array
    {
        $checkin = (array) ($payload['checkin'] ?? []);
        $tenantId = (int) ($tenant['id'] ?? 0);
        $connectionId = (int) ($connection['id'] ?? 0);
        $dmsCheckinId = (int) ($checkin['dms_checkin_id'] ?? 0);
        $requestId = trim((string) ($requestContext['request_id'] ?? ''));
        $idempotencyKey = trim((string) ($requestContext['idempotency_key'] ?? ''));
        $checksum = sha1(kt_integration_hub_json_encode($payload));
        $this->currentPayload = $payload;
        $this->currentRequestId = $requestId;
        $this->currentIdempotencyKey = $idempotencyKey;

        $this->guardValidPayload($tenantId, $checkin);
        $this->guardIdempotency($tenantId, $idempotencyKey, $dmsCheckinId, $checksum);

        $tenantDb = $this->tenantDb($tenant);

        $staff = $this->findStaff($tenantDb, (int) ($checkin['crm_staff_id'] ?? 0));
        if (! $staff) {
            $this->recordFailure(
                $tenantId,
                $connectionId,
                $checkin,
                $checksum,
                'failed',
                'STAFF_NOT_MAPPED',
                'CRM staff is not mapped for this tenant.'
            );

            throw new DmsCheckinBridgeException('STAFF_NOT_MAPPED', 'CRM staff is not mapped for this tenant.', 409);
        }

        $customer = $this->findCustomer($tenantDb, (int) ($checkin['crm_customer_id'] ?? 0));
        if (! $customer) {
            $this->recordFailure(
                $tenantId,
                $connectionId,
                $checkin,
                $checksum,
                'failed',
                'CUSTOMER_NOT_MAPPED',
                'CRM customer is not mapped for this tenant.'
            );

            throw new DmsCheckinBridgeException('CUSTOMER_NOT_MAPPED', 'CRM customer is not mapped for this tenant.', 409);
        }

        $existingEvent = $this->getEventRecord($tenantId, $dmsCheckinId);
        if ($existingEvent) {
            $existingChecksum = sha1((string) ($existingEvent['payload_json'] ?? ''));
            if ($existingChecksum === $checksum) {
                $result = $this->buildResult($checkin, (int) $existingEvent['id'], true);
                $this->persistSyncJob($tenantId, $connectionId, $checkin, $requestId, $idempotencyKey, $payload, $result, true);

                return $result;
            }
        }

        $record = $this->upsertEventRecord($tenantId, $connectionId, $checkin, $payload, $checksum);
        $eventId = (int) ($record['id'] ?? 0);
        $result = $this->buildResult($checkin, $eventId, false);
        $this->persistSyncJob($tenantId, $connectionId, $checkin, $requestId, $idempotencyKey, $payload, $result, false);
        $this->upsertEntityLink($tenantId, $connectionId, $eventId, $checkin, $payload, $result);
        $this->integrationModel()->sync_tenant_dms_usage_snapshot($tenantId);
        $this->logSuccess($tenantId, $connectionId, $checkin, $requestId, $idempotencyKey, $result);

        return $result;
    }

    protected function guardValidPayload(int $tenantId, array $checkin): void
    {
        if ($tenantId <= 0) {
            throw new DmsCheckinBridgeException('TENANT_SCOPE_MISMATCH', 'Tenant context is invalid.', 409);
        }

        $requiredIntegerFields = [
            'dms_checkin_id',
            'crm_staff_id',
            'crm_customer_id',
            'store_id',
        ];

        foreach ($requiredIntegerFields as $field) {
            if ((int) ($checkin[$field] ?? 0) <= 0) {
                throw new DmsCheckinBridgeException('VALIDATION_ERROR', $field . ' is required.', 422, [
                    'checkin.' . $field => [$field . ' is required.'],
                ]);
            }
        }

        foreach (['checkin_at', 'latitude', 'longitude', 'status'] as $field) {
            if (! array_key_exists($field, $checkin) || trim((string) ($checkin[$field] ?? '')) === '') {
                throw new DmsCheckinBridgeException('VALIDATION_ERROR', $field . ' is required.', 422, [
                    'checkin.' . $field => [$field . ' is required.'],
                ]);
            }
        }

        if (! is_numeric($checkin['latitude']) || ! is_numeric($checkin['longitude'])) {
            throw new DmsCheckinBridgeException('VALIDATION_ERROR', 'Latitude and longitude must be numeric.', 422, [
                'checkin.latitude' => ['Latitude must be numeric.'],
                'checkin.longitude' => ['Longitude must be numeric.'],
            ]);
        }

        if (! in_array(strtolower(trim((string) ($checkin['status'] ?? ''))), ['valid', 'warning', 'invalid'], true)) {
            throw new DmsCheckinBridgeException('VALIDATION_ERROR', 'Invalid check-in status.', 422, [
                'checkin.status' => ['Status must be valid, warning, or invalid.'],
            ]);
        }
    }

    protected function guardIdempotency(int $tenantId, string $idempotencyKey, int $externalId, string $checksum): void
    {
        if ($idempotencyKey === '') {
            throw new DmsCheckinBridgeException('VALIDATION_ERROR', 'Idempotency key is required.', 422);
        }

        $job = $this->getSyncJob($tenantId, $externalId);
        if (! is_array($job)) {
            return;
        }

        $existingKey = trim((string) ($job['idempotency_key'] ?? ''));
        $existingChecksum = sha1((string) ($job['payload_json'] ?? ''));
        if ($existingKey === $idempotencyKey && $existingChecksum !== '' && $existingChecksum !== $checksum) {
            throw new DmsCheckinBridgeException('IDEMPOTENCY_KEY_REUSED', 'Idempotency key was already used with a different payload.', 409);
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

    protected function getEventRecord(int $tenantId, int $dmsCheckinId): ?array
    {
        if ($tenantId <= 0 || $dmsCheckinId <= 0) {
            return null;
        }

        $row = $this->db()
            ->where('tenant_id', $tenantId)
            ->where('dms_checkin_id', $dmsCheckinId)
            ->get(db_prefix() . self::EVENT_TABLE)
            ->row_array();

        return $row ?: null;
    }

    protected function upsertEventRecord(int $tenantId, int $connectionId, array $checkin, array $payload, string $checksum): array
    {
        $eventTable = db_prefix() . self::EVENT_TABLE;
        $record = [
            'tenant_id' => $tenantId,
            'dms_checkin_id' => (int) ($checkin['dms_checkin_id'] ?? 0),
            'crm_staff_id' => (int) ($checkin['crm_staff_id'] ?? 0),
            'crm_customer_id' => (int) ($checkin['crm_customer_id'] ?? 0),
            'store_id' => (int) ($checkin['store_id'] ?? 0),
            'route_id' => ! empty($checkin['route_id']) ? (int) $checkin['route_id'] : null,
            'checkin_at' => $this->normalizeDateTime((string) ($checkin['checkin_at'] ?? '')),
            'latitude' => (float) ($checkin['latitude'] ?? 0),
            'longitude' => (float) ($checkin['longitude'] ?? 0),
            'accuracy_meter' => isset($checkin['accuracy_meter']) && is_numeric($checkin['accuracy_meter']) ? (float) $checkin['accuracy_meter'] : null,
            'distance_from_store_meter' => isset($checkin['distance_from_store_meter']) && is_numeric($checkin['distance_from_store_meter']) ? (int) $checkin['distance_from_store_meter'] : null,
            'photo_url' => trim((string) ($checkin['photo_url'] ?? '')) ?: null,
            'status' => strtolower(trim((string) ($checkin['status'] ?? 'warning'))),
            'notes' => trim((string) ($checkin['notes'] ?? '')) ?: null,
            'payload_json' => kt_integration_hub_json_encode($payload),
        ];

        $existing = $this->getEventRecord($tenantId, (int) ($checkin['dms_checkin_id'] ?? 0));
        if ($existing) {
            $this->db()->where('id', (int) $existing['id'])->update($eventTable, $record);
            $record['id'] = (int) $existing['id'];
            return $record;
        }

        $record['created_at'] = kt_integration_hub_now();
        $this->db()->insert($eventTable, $record);
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

    protected function persistSyncJob(int $tenantId, int $connectionId, array $checkin, string $requestId, string $idempotencyKey, array $payload, array $result, bool $duplicate): void
    {
        $jobTable = db_prefix() . 'kt_integration_sync_jobs';
        $externalId = (int) ($checkin['dms_checkin_id'] ?? 0);
        $record = [
            'tenant_id' => $tenantId,
            'connection_id' => $connectionId,
            'provider_code' => self::PROVIDER_CODE,
            'job_type' => self::JOB_TYPE,
            'entity_type' => 'checkin',
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
                throw new DmsCheckinBridgeException('IDEMPOTENCY_KEY_REUSED', 'Idempotency key was already used with a different payload.', 409);
            }

            $this->db()->where('id', (int) $existing['id'])->update($jobTable, $record);

            return;
        }

        $record['created_at'] = kt_integration_hub_now();
        $this->db()->insert($jobTable, $record);
    }

    protected function upsertEntityLink(int $tenantId, int $connectionId, int $eventId, array $checkin, array $payload, array $result): void
    {
        $this->integrationModel()->upsert_provider_entity_link(
            $tenantId,
            self::PROVIDER_CODE,
            $connectionId,
            'checkin',
            $eventId,
            (string) ((int) ($checkin['dms_checkin_id'] ?? 0)),
            [
                'payload' => $payload,
                'response' => $result,
                'dms_checkin_id' => (int) ($checkin['dms_checkin_id'] ?? 0),
            ],
            'inbound'
        );
    }

    protected function recordFailure(int $tenantId, int $connectionId, array $checkin, string $checksum, string $bridgeStatus, string $errorCode, string $errorMessage): void
    {
        $this->persistFailedSyncJob($tenantId, $connectionId, $checkin, $this->currentPayload, $this->currentRequestId, $this->currentIdempotencyKey, $errorMessage);
        $this->integrationModel()->log(
            'warning',
            'checkin.sync.failed',
            $errorMessage,
            [
                'dms_checkin_id' => (int) ($checkin['dms_checkin_id'] ?? 0),
                'checksum' => $checksum,
                'bridge_status' => $bridgeStatus,
                'error_code' => $errorCode,
            ],
            $tenantId,
            $connectionId,
            self::PROVIDER_CODE
        );
    }

    protected function persistFailedSyncJob(int $tenantId, int $connectionId, array $checkin, array $payload, string $requestId, string $idempotencyKey, string $errorMessage): void
    {
        $externalId = (int) ($checkin['dms_checkin_id'] ?? 0);
        $record = [
            'tenant_id' => $tenantId,
            'connection_id' => $connectionId,
            'provider_code' => self::PROVIDER_CODE,
            'job_type' => self::JOB_TYPE,
            'entity_type' => 'checkin',
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

    protected function logSuccess(int $tenantId, int $connectionId, array $checkin, string $requestId, string $idempotencyKey, array $result): void
    {
        $this->integrationModel()->log(
            'info',
            'checkin.sync.success',
            'DMS check-in event synchronized.',
            [
                'dms_checkin_id' => (int) ($checkin['dms_checkin_id'] ?? 0),
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

    protected function buildResult(array $checkin, int $eventId, bool $duplicate): array
    {
        return [
            'success' => true,
            'code' => $duplicate ? 'CHECKIN_ALREADY_SYNCED' : 'CHECKIN_EVENT_SYNCED',
            'message' => $duplicate ? 'Check-in event already synchronized.' : 'Check-in event synchronized.',
            'checkin_event_id' => $eventId,
            'dms_checkin_id' => (int) ($checkin['dms_checkin_id'] ?? 0),
            'status' => (string) ($checkin['status'] ?? 'warning'),
            'duplicate' => $duplicate,
        ];
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
            throw new DmsCheckinBridgeException('TENANT_SCOPE_MISMATCH', 'Tenant database context is incomplete.', 409);
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

        throw new DmsCheckinBridgeException('TENANT_SCOPE_MISMATCH', 'Tenant database is unavailable.', 409);
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
