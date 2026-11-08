<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/DmsFieldFeedbackBridgeException.php');

class DmsFieldFeedbackBridgeProcessor
{
    public const PROVIDER_CODE = 'salesflow_dms';
    private const JOB_TYPE = 'sync_field_feedback';

    public function handle(array $tenant, array $connection, array $payload, array $requestContext = []): array
    {
        $feedback = (array) ($payload['feedback'] ?? []);
        $tenantId = (int) ($tenant['id'] ?? 0);
        $connectionId = (int) ($connection['id'] ?? 0);
        $feedbackId = (int) ($feedback['feedback_id'] ?? 0);
        $requestId = trim((string) ($requestContext['request_id'] ?? ''));
        $idempotencyKey = trim((string) ($requestContext['idempotency_key'] ?? ''));
        $checksum = sha1(kt_integration_hub_json_encode($payload));

        $this->guardValidPayload($tenantId, $feedback, $idempotencyKey);
        $this->guardIdempotency($tenantId, $feedbackId, $idempotencyKey, $checksum);

        $record = $this->buildProjectionRecord($tenant, $feedback, $payload);
        $ok = $this->model()->upsert_dms_field_feedback_projection($record);
        if (!$ok) {
            $this->persistSyncJob($tenantId, $connectionId, $feedback, $requestId, $idempotencyKey, $payload, ['success' => false], 'failed', 'Projection write failed.');
            throw new DmsFieldFeedbackBridgeException('PROJECTION_WRITE_FAILED', 'Field feedback projection could not be saved.', 500);
        }

        $result = [
            'success' => true,
            'code' => 'FIELD_FEEDBACK_SYNCED',
            'message' => 'Field feedback synchronized.',
            'feedback_id' => $feedbackId,
            'projection_version' => 1,
        ];
        $this->persistSyncJob($tenantId, $connectionId, $feedback, $requestId, $idempotencyKey, $payload, $result, 'done', null);
        $this->model()->log('info', 'field_feedback.sync.success', 'DMS field feedback synchronized to CRM projection.', [
            'feedback_id' => $feedbackId,
            'request_id' => $requestId,
            'idempotency_key' => $idempotencyKey,
        ], $tenantId, $connectionId, self::PROVIDER_CODE);

        return $result;
    }

    private function guardValidPayload(int $tenantId, array $feedback, string $idempotencyKey): void
    {
        if ($tenantId <= 0) {
            throw new DmsFieldFeedbackBridgeException('TENANT_SCOPE_MISMATCH', 'Tenant context is invalid.', 409);
        }
        if ($idempotencyKey === '') {
            throw new DmsFieldFeedbackBridgeException('VALIDATION_ERROR', 'Idempotency key is required.', 422);
        }
        foreach (['feedback_id', 'feedback_type', 'severity', 'status', 'title', 'submitted_at'] as $field) {
            $value = $feedback[$field] ?? null;
            if (($field === 'feedback_id' && (int) $value <= 0) || ($field !== 'feedback_id' && trim((string) $value) === '')) {
                throw new DmsFieldFeedbackBridgeException('VALIDATION_ERROR', $field . ' is required.', 422, [
                    'feedback.' . $field => [$field . ' is required.'],
                ]);
            }
        }
    }

    private function guardIdempotency(int $tenantId, int $feedbackId, string $idempotencyKey, string $checksum): void
    {
        $job = $this->getSyncJob($tenantId, $feedbackId);
        if (!$job) {
            return;
        }
        $existingKey = trim((string) ($job['idempotency_key'] ?? ''));
        $existingChecksum = sha1((string) ($job['payload_json'] ?? ''));
        if ($existingKey === $idempotencyKey && $existingChecksum !== '' && $existingChecksum !== $checksum) {
            throw new DmsFieldFeedbackBridgeException('IDEMPOTENCY_KEY_REUSED', 'Idempotency key was already used with a different payload.', 409);
        }
    }

    private function buildProjectionRecord(array $tenant, array $feedback, array $payload): array
    {
        $tenantId = (int) ($tenant['id'] ?? 0);
        $companyId = (int) ($payload['tenant']['crm_company_id'] ?? $tenantId);
        $metadata = kt_integration_hub_redact_secrets([
            'contract' => 'dms_field_feedback_projection_v1',
            'check_in_id' => $feedback['check_in_id'] ?? null,
            'visit_id' => $feedback['visit_id'] ?? null,
            'order_id' => $feedback['order_id'] ?? null,
            'photo_url_present' => !empty($feedback['photo_url']),
        ]);

        return [
            'tenant_id' => $tenantId,
            'company_id' => $companyId > 0 ? $companyId : $tenantId,
            'dms_workspace_host' => trim((string) ($payload['tenant']['dms_workspace_host'] ?? '')),
            'source' => 'salesrep',
            'source_record_id' => (int) ($feedback['feedback_id'] ?? 0),
            'source_record_type' => 'field_feedback',
            'projection_version' => 1,
            'sync_status' => 'synced',
            'last_synced_at' => kt_integration_hub_now(),
            'feedback_type' => strtolower(trim((string) ($feedback['feedback_type'] ?? 'custom_note'))),
            'severity' => strtolower(trim((string) ($feedback['severity'] ?? 'normal'))),
            'status' => strtolower(trim((string) ($feedback['status'] ?? 'new'))),
            'staff_name' => trim((string) ($feedback['staff_name'] ?? '')),
            'staff_email' => trim((string) ($feedback['staff_email'] ?? '')),
            'store_name' => trim((string) ($feedback['store_name'] ?? '')),
            'store_external_id' => trim((string) ($feedback['store_external_id'] ?? '')),
            'title' => mb_substr(trim((string) ($feedback['title'] ?? '')), 0, 255),
            'description_summary' => mb_substr(trim((string) ($feedback['description_summary'] ?? '')), 0, 500),
            'submitted_at' => $this->normalizeDateTime($feedback['submitted_at'] ?? null),
            'reviewed_at' => $this->normalizeDateTime($feedback['reviewed_at'] ?? null),
            'resolved_at' => $this->normalizeDateTime($feedback['resolved_at'] ?? null),
            'latitude' => is_numeric($feedback['latitude'] ?? null) ? (float) $feedback['latitude'] : null,
            'longitude' => is_numeric($feedback['longitude'] ?? null) ? (float) $feedback['longitude'] : null,
            'gps_accuracy' => is_numeric($feedback['gps_accuracy'] ?? null) ? (float) $feedback['gps_accuracy'] : null,
            'photo_count' => max(0, (int) ($feedback['photo_count'] ?? 0)),
            'metadata' => $metadata,
        ];
    }

    private function persistSyncJob(int $tenantId, int $connectionId, array $feedback, string $requestId, string $idempotencyKey, array $payload, array $result, string $status, ?string $error): void
    {
        $externalId = (int) ($feedback['feedback_id'] ?? 0);
        $record = [
            'tenant_id' => $tenantId,
            'connection_id' => $connectionId,
            'provider_code' => self::PROVIDER_CODE,
            'job_type' => self::JOB_TYPE,
            'entity_type' => 'field_feedback',
            'external_id' => (string) $externalId,
            'idempotency_key' => $idempotencyKey,
            'request_id' => $requestId,
            'status' => $status,
            'attempts' => 1,
            'max_attempts' => 1,
            'payload_json' => kt_integration_hub_json_encode($payload),
            'result_json' => kt_integration_hub_json_encode($result),
            'error_message' => $error,
            'available_at' => kt_integration_hub_now(),
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

    private function getSyncJob(int $tenantId, int $externalId): ?array
    {
        $row = $this->db()
            ->where('tenant_id', $tenantId)
            ->where('provider_code', self::PROVIDER_CODE)
            ->where('job_type', self::JOB_TYPE)
            ->where('external_id', (string) $externalId)
            ->get(db_prefix() . 'kt_integration_sync_jobs')
            ->row_array();

        return $row ?: null;
    }

    private function normalizeDateTime($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $timestamp = strtotime($value);
        return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    private function model()
    {
        $CI = &get_instance();
        if (!isset($CI->Kt_integration_model)) {
            $CI->load->model(KT_INTEGRATION_HUB_MODULE . '/Kt_integration_model');
        }

        return $CI->Kt_integration_model;
    }

    private function db()
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
}
