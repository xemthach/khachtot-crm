<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_integration_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(KT_INTEGRATION_HUB_MODULE . '/kt_integration_hub');
    }

    public function get_summary($tenantId = null)
    {
        $db = $this->landlord_db();

        return [
            'providers' => (int) $db->count_all_results(db_prefix() . 'kt_integration_providers'),
            'connections' => $this->count_rows('kt_integration_connections', $tenantId),
            'events_pending' => $this->count_rows('kt_integration_webhook_events', $tenantId, ['processing_status' => 'pending']),
            'jobs_queued' => $this->count_rows('kt_integration_sync_jobs', $tenantId, ['status' => 'queued']),
            'jobs_failed' => $this->count_rows('kt_integration_sync_jobs', $tenantId, ['status' => 'failed']),
            'logs_error' => $this->count_rows('kt_integration_logs', $tenantId, ['level' => 'error']),
        ];
    }

    public function get_providers($activeOnly = false)
    {
        $db = $this->landlord_db()->from(db_prefix() . 'kt_integration_providers');
        if ($activeOnly) {
            $db->where('is_active', 1);
        }

        return $db->order_by('provider_name', 'asc')->get()->result_array();
    }

    public function get_connections($tenantId = null)
    {
        $db = $this->landlord_db()
            ->select('c.*, p.provider_name, p.provider_type, p.auth_type as provider_auth_type, p.readiness_status, p.status_message')
            ->from(db_prefix() . 'kt_integration_connections c')
            ->join(db_prefix() . 'kt_integration_providers p', 'p.provider_code = c.provider_code', 'left');

        if ($tenantId !== null) {
            $db->where('c.tenant_id', (int) $tenantId);
        }

        return $db->order_by('c.id', 'desc')->get()->result_array();
    }

    public function get_connection($id)
    {
        return $this->landlord_db()
            ->where('id', (int) $id)
            ->get(db_prefix() . 'kt_integration_connections')
            ->row_array();
    }

    public function get_connection_by_public_key($providerCode, $publicKey)
    {
        return $this->landlord_db()
            ->where('provider_code', trim((string) $providerCode))
            ->where('public_key', trim((string) $publicKey))
            ->where('status', 'connected')
            ->get(db_prefix() . 'kt_integration_connections')
            ->row_array();
    }

    public function save_connection(array $data, $tenantId, $id = null)
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            return ['success' => false, 'message' => 'Tenant is required.'];
        }

        $existing = $id ? $this->get_connection((int) $id) : [];
        $providerCode = trim((string) ($data['provider_code'] ?? ($existing['provider_code'] ?? 'custom_webhook')));
        $provider = $this->get_provider($providerCode);
        if (!$provider) {
            return ['success' => false, 'message' => 'Provider is not available.'];
        }

        if (!in_array((string) ($provider['readiness_status'] ?? 'planned'), ['ready', 'beta'], true)) {
            return ['success' => false, 'message' => 'This provider is not ready yet. No connection was created.'];
        }

        if (!in_array($providerCode, ['custom_webhook', 'zalo_oa', 'tiktok_shop'], true)) {
            return ['success' => false, 'message' => 'This connector is not implemented yet.'];
        }

        $authType = trim((string) ($provider['auth_type'] ?? 'custom_hmac'));
        $plainSecret = $data['webhook_secret'] ?? null;
        if ($providerCode === 'zalo_oa') {
            $plainSecret = $data['app_secret'] ?? null;
        }
        if ($providerCode === 'tiktok_shop') {
            $plainSecret = $data['app_secret'] ?? null;
        }
        $generatedSecret = null;
        if ($providerCode === 'custom_webhook' && !$existing && in_array($authType, ['custom_hmac', 'hmac'], true) && trim((string) $plainSecret) === '') {
            $generatedSecret = $this->generate_webhook_secret();
            $plainSecret = $generatedSecret;
        }
        $settings = $this->connection_settings_payload($providerCode, $data, $existing);

        $payload = [
            'tenant_id' => $tenantId,
            'provider_code' => $providerCode,
            'connection_name' => trim((string) ($data['connection_name'] ?? '')),
            'external_account_id' => $this->external_account_id_payload($providerCode, $data, $existing),
            'external_account_name' => trim((string) ($data['external_account_name'] ?? '')),
            'status' => !empty($data['is_active']) ? 'connected' : 'disconnected',
            'auth_type' => $authType,
            'access_token_encrypted' => $this->merge_encrypted_value($existing, 'access_token_encrypted', $data['access_token'] ?? null),
            'refresh_token_encrypted' => $this->merge_encrypted_value($existing, 'refresh_token_encrypted', $data['refresh_token'] ?? null),
            'webhook_secret_encrypted' => $this->merge_encrypted_value($existing, 'webhook_secret_encrypted', $plainSecret),
            'settings_json' => kt_integration_hub_json_encode($settings),
            'updated_at' => kt_integration_hub_now(),
        ];

        if ($id && $existing) {
            $this->landlord_db()->where('id', (int) $id)->update(db_prefix() . 'kt_integration_connections', $payload);
            $this->log('info', 'connection.updated', 'Connection updated.', ['connection_id' => (int) $id], $tenantId, (int) $id, $payload['provider_code']);

            return ['success' => true, 'id' => (int) $id, 'generated_secret' => $generatedSecret];
        }

        $payload['public_key'] = $this->generate_public_key();
        $payload['created_at'] = kt_integration_hub_now();
        $this->landlord_db()->insert(db_prefix() . 'kt_integration_connections', $payload);
        $insertId = (int) $this->landlord_db()->insert_id();
        if ($insertId > 0 && in_array($providerCode, ['zalo_oa', 'tiktok_shop'], true)) {
            $payload['id'] = $insertId;
            $payload['settings_json'] = kt_integration_hub_json_encode($this->connection_settings_payload($providerCode, $data, $payload));
            $this->landlord_db()->where('id', $insertId)->update(db_prefix() . 'kt_integration_connections', [
                'settings_json' => $payload['settings_json'],
            ]);
        }
        $this->log('info', 'connection.created', 'Connection created.', ['connection_id' => $insertId], $tenantId, $insertId, $payload['provider_code']);

        return ['success' => $insertId > 0, 'id' => $insertId, 'generated_secret' => $generatedSecret];
    }

    public function get_provider($providerCode)
    {
        return $this->landlord_db()
            ->where('provider_code', trim((string) $providerCode))
            ->where('is_active', 1)
            ->get(db_prefix() . 'kt_integration_providers')
            ->row_array();
    }

    public function rotate_connection_secret($id, $tenantId = null)
    {
        $connection = $this->get_connection((int) $id);
        if (!$connection) {
            return ['success' => false, 'message' => 'Connection not found.'];
        }
        if ($tenantId !== null && (int) $connection['tenant_id'] !== (int) $tenantId) {
            return ['success' => false, 'message' => 'Connection not found.'];
        }
        if ((string) $connection['provider_code'] !== 'custom_webhook') {
            return ['success' => false, 'message' => 'Secret rotation is only available for Custom Webhook.'];
        }

        $secret = $this->generate_webhook_secret();
        $this->landlord_db()->where('id', (int) $id)->update(db_prefix() . 'kt_integration_connections', [
            'webhook_secret_encrypted' => kt_integration_hub_encrypt_value($secret),
            'updated_at' => kt_integration_hub_now(),
        ]);
        $this->log('warning', 'connection.secret_rotated', 'Webhook secret rotated.', ['connection_id' => (int) $id], (int) $connection['tenant_id'], (int) $id, (string) $connection['provider_code']);

        return ['success' => true, 'id' => (int) $id, 'generated_secret' => $secret];
    }

    public function disconnect_connection($id, $tenantId = null)
    {
        $connection = $this->get_connection((int) $id);
        if (!$connection) {
            return false;
        }
        if ($tenantId !== null && (int) $connection['tenant_id'] !== (int) $tenantId) {
            return false;
        }

        $this->landlord_db()
            ->where('id', (int) $id)
            ->update(db_prefix() . 'kt_integration_connections', [
                'status' => 'disconnected',
                'updated_at' => kt_integration_hub_now(),
            ]);
        $this->log('warning', 'connection.disconnected', 'Connection disconnected.', [], (int) $connection['tenant_id'], (int) $id, (string) $connection['provider_code']);

        return true;
    }

    public function verify_custom_webhook(array $connection, $rawBody, array $headers)
    {
        $secret = kt_integration_hub_decrypt_value($connection['webhook_secret_encrypted'] ?? '');
        if ($secret === '') {
            return ['success' => false, 'message' => 'Webhook secret is not configured.'];
        }

        $timestamp = trim((string) ($headers['x-kt-timestamp'] ?? $headers['X-KT-Timestamp'] ?? ''));
        if ($timestamp === '' || !ctype_digit($timestamp)) {
            return ['success' => false, 'message' => 'Missing or invalid webhook timestamp.'];
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return ['success' => false, 'message' => 'Webhook timestamp is outside the allowed window.'];
        }

        $signature = trim((string) ($headers['x-kt-signature'] ?? $headers['X-KT-Signature'] ?? ''));
        if ($signature === '') {
            return ['success' => false, 'message' => 'Missing webhook signature.'];
        }

        $provided = preg_replace('/^sha256=/i', '', $signature);
        $expected = hash_hmac('sha256', $timestamp . '.' . (string) $rawBody, $secret);
        if (!is_string($provided) || !preg_match('/^[a-f0-9]{64}$/i', $provided) || !hash_equals($expected, strtolower($provided))) {
            return ['success' => false, 'message' => 'Invalid webhook signature.'];
        }

        return ['success' => true];
    }

    public function verify_zalo_webhook(array $connection, array $payload, $rawBody, array $headers)
    {
        $signature = trim((string) ($headers['x-zevent-signature'] ?? $headers['X-ZEvent-Signature'] ?? $headers['X-Zevent-Signature'] ?? ''));
        if ($signature === '') {
            $allowUnsigned = (bool) $this->connection_setting($connection, 'allow_unsigned_test_webhook', ENVIRONMENT !== 'production');
            if (!$allowUnsigned) {
                return ['success' => false, 'status' => 'invalid', 'message' => 'Zalo signature header is not present.'];
            }

            return ['success' => true, 'status' => 'unchecked', 'message' => 'Unsigned Zalo webhook accepted for testing.'];
        }

        $secret = kt_integration_hub_decrypt_value($connection['webhook_secret_encrypted'] ?? '');
        if ($secret === '') {
            return ['success' => false, 'status' => 'invalid', 'message' => 'Zalo OA secret is not configured.'];
        }

        $appId = trim((string) ($payload['app_id'] ?? $this->connection_setting($connection, 'app_id')));
        $timestamp = trim((string) ($payload['timestamp'] ?? ''));
        if ($appId === '' || $timestamp === '') {
            return ['success' => false, 'status' => 'invalid', 'message' => 'Zalo signature cannot be verified without app_id and timestamp.'];
        }

        $provided = preg_replace('/^mac=/i', '', $signature);
        $provided = preg_replace('/^sha256=/i', '', (string) $provided);
        $provided = strtolower(trim((string) $provided));
        $expected = hash('sha256', $appId . (string) $rawBody . $timestamp . $secret);

        if (!preg_match('/^[a-f0-9]{64}$/', $provided) || !hash_equals($expected, $provided)) {
            return ['success' => false, 'status' => 'invalid', 'message' => 'Invalid Zalo webhook signature.'];
        }

        return ['success' => true, 'status' => 'valid'];
    }

    public function verify_tiktok_webhook(array $connection, array $payload, $rawBody, array $headers)
    {
        $mode = (string) $this->connection_setting($connection, 'connection_mode', 'dry_run');
        $dryRun = $mode === 'dry_run' || (bool) $this->connection_setting($connection, 'dry_run_enabled', true);
        if ($dryRun) {
            return ['success' => true, 'status' => 'unchecked', 'message' => 'Unsigned TikTok Shop dry-run webhook accepted.'];
        }

        return [
            'success' => false,
            'status' => 'invalid',
            'message' => 'TikTok Shop webhook signature verification requires Partner Center credential verification.',
        ];
    }

    public function store_webhook_event(array $connection, array $payload, array $headers, $rawBody, $signatureStatus = 'verified')
    {
        $providerCode = (string) ($connection['provider_code'] ?? 'custom_webhook');
        $externalEventId = $this->external_event_id($payload, $rawBody);
        $eventType = trim((string) ($payload['event_type'] ?? $payload['event_name'] ?? $payload['type'] ?? 'lead'));

        $existing = $this->landlord_db()
            ->where('tenant_id', (int) ($connection['tenant_id'] ?? 0))
            ->where('connection_id', (int) ($connection['id'] ?? 0))
            ->where('provider_code', $providerCode)
            ->where('external_event_id', $externalEventId)
            ->get(db_prefix() . 'kt_integration_webhook_events')
            ->row_array();
        if ($existing) {
            return ['success' => true, 'duplicate' => true, 'event_id' => (int) $existing['id']];
        }

        $record = [
            'tenant_id' => (int) ($connection['tenant_id'] ?? 0),
            'connection_id' => (int) ($connection['id'] ?? 0),
            'provider_code' => $providerCode,
            'event_type' => $eventType,
            'external_event_id' => $externalEventId,
            'signature_status' => $signatureStatus,
            'processing_status' => 'pending',
            'raw_payload' => kt_integration_hub_json_encode($payload),
            'headers_json' => kt_integration_hub_json_encode(kt_integration_hub_redact_secrets($headers)),
            'received_at' => kt_integration_hub_now(),
        ];
        $this->landlord_db()->insert(db_prefix() . 'kt_integration_webhook_events', $record);
        $eventId = (int) $this->landlord_db()->insert_id();
        if ($eventId > 0 && !empty($connection['id']) && in_array($providerCode, ['zalo_oa', 'tiktok_shop'], true)) {
            $settings = kt_integration_hub_json_decode((string) ($connection['settings_json'] ?? ''), []);
            $settings['last_webhook_at'] = kt_integration_hub_now();
            $this->landlord_db()->where('id', (int) $connection['id'])->update(db_prefix() . 'kt_integration_connections', [
                'settings_json' => kt_integration_hub_json_encode(kt_integration_hub_redact_secrets($settings)),
                'updated_at' => kt_integration_hub_now(),
            ]);
        }
        $this->queue_job_from_event($eventId, $connection, $payload, $eventType, $externalEventId);

        return ['success' => $eventId > 0, 'event_id' => $eventId];
    }

    public function queue_job_from_event($eventId, array $connection, array $payload, $eventType, $externalId)
    {
        $entityType = $this->entity_type_from_event($eventType, $payload);
        $jobType = 'upsert_' . $entityType;
        $tenantId = (int) ($connection['tenant_id'] ?? 0);
        if ((string) ($connection['provider_code'] ?? '') === 'zalo_oa') {
            $normalized = $this->normalize_zalo_payload($payload);
            $entityType = 'zalo_user';
            $jobType = $normalized['event_type'] === 'follow' ? 'lead_candidate' : 'inbound_message';
            $externalId = $normalized['external_message_id'] ?: ($normalized['external_user_id'] ?: (string) $externalId);
        }
        if ((string) ($connection['provider_code'] ?? '') === 'tiktok_shop') {
            $normalized = $this->normalize_tiktok_order_event($payload);
            $entityType = 'tiktok_order';
            $jobType = 'tiktok_order_event';
            $externalId = $normalized['external_order_id'] ?: (string) $externalId;
        }

        $existing = $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->where('provider_code', (string) $connection['provider_code'])
            ->where('job_type', $jobType)
            ->where('external_id', (string) $externalId)
            ->get(db_prefix() . 'kt_integration_sync_jobs')
            ->row_array();
        if ($existing) {
            if ((string) ($connection['provider_code'] ?? '') === 'tiktok_shop' && in_array((string) ($existing['status'] ?? ''), ['done', 'failed'], true)) {
                $this->landlord_db()->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_integration_sync_jobs', [
                    'webhook_event_id' => (int) $eventId,
                    'payload_json' => kt_integration_hub_json_encode($payload),
                    'status' => 'queued',
                    'attempts' => 0,
                    'error_message' => null,
                    'available_at' => kt_integration_hub_now(),
                    'updated_at' => kt_integration_hub_now(),
                ]);
            }
            return (int) $existing['id'];
        }

        $this->landlord_db()->insert(db_prefix() . 'kt_integration_sync_jobs', [
            'tenant_id' => $tenantId,
            'connection_id' => (int) ($connection['id'] ?? 0),
            'webhook_event_id' => (int) $eventId,
            'provider_code' => (string) ($connection['provider_code'] ?? 'custom_webhook'),
            'job_type' => $jobType,
            'entity_type' => $entityType,
            'external_id' => (string) $externalId,
            'status' => 'queued',
            'attempts' => 0,
            'max_attempts' => 5,
            'payload_json' => kt_integration_hub_json_encode($payload),
            'available_at' => kt_integration_hub_now(),
            'created_at' => kt_integration_hub_now(),
            'updated_at' => kt_integration_hub_now(),
        ]);

        return (int) $this->landlord_db()->insert_id();
    }

    public function process_due_jobs($limit = 50)
    {
        $jobs = $this->claim_due_jobs($limit);
        $summary = ['checked' => count($jobs), 'done' => 0, 'failed' => 0, 'retry' => 0];

        foreach ($jobs as $job) {
            $result = $this->process_job($job);
            if (!empty($result['success'])) {
                $summary['done']++;
            } elseif (($result['status'] ?? '') === 'retry') {
                $summary['retry']++;
            } else {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    public function claim_due_jobs($limit = 50)
    {
        $rows = $this->landlord_db()
            ->where_in('status', ['queued', 'retry'])
            ->group_start()
                ->where('available_at IS NULL', null, false)
                ->or_where('available_at <=', kt_integration_hub_now())
            ->group_end()
            ->order_by('id', 'asc')
            ->limit(max((int) $limit, 1))
            ->get(db_prefix() . 'kt_integration_sync_jobs')
            ->result_array();

        $claimed = [];
        foreach ($rows as $row) {
            $this->landlord_db()
                ->where('id', (int) $row['id'])
                ->where_in('status', ['queued', 'retry'])
                ->update(db_prefix() . 'kt_integration_sync_jobs', [
                    'status' => 'processing',
                    'locked_at' => kt_integration_hub_now(),
                    'updated_at' => kt_integration_hub_now(),
                ]);
            if ($this->landlord_db()->affected_rows() > 0) {
                $claimed[] = $row;
            }
        }

        return $claimed;
    }

    public function process_job(array $job)
    {
        try {
            if ((string) ($job['provider_code'] ?? '') === 'tiktok_shop') {
                $payload = kt_integration_hub_json_decode((string) ($job['payload_json'] ?? ''), []);
                $connection = $this->get_connection((int) ($job['connection_id'] ?? 0));
                $orderId = (string) ($job['external_id'] ?? '');
                $channelOrderId = $this->write_tiktok_order_staging((int) $job['tenant_id'], (int) ($job['connection_id'] ?? 0), $payload, $connection ?: [], $orderId);

                return $this->mark_job_done($job, ['channel_order_id' => $channelOrderId]);
            }

            if ((string) ($job['provider_code'] ?? '') === 'zalo_oa') {
                $payload = kt_integration_hub_json_decode((string) ($job['payload_json'] ?? ''), []);
                $normalized = $this->normalize_zalo_payload($payload);
                if (trim((string) ($normalized['external_user_id'] ?? '')) === '') {
                    return $this->mark_job_done($job, ['ignored' => true, 'message' => 'Zalo event has no user id.']);
                }
                if ((string) ($normalized['event_type'] ?? '') === 'follow') {
                    $connection = $this->get_connection((int) ($job['connection_id'] ?? 0));
                    $createLeadOnFollow = (bool) $this->connection_setting($connection ?: [], 'create_lead_on_follow', true);
                    if (!$createLeadOnFollow) {
                        return $this->mark_job_done($job, ['ignored' => true, 'message' => 'Follow event stored without lead creation.']);
                    }
                }
                $leadId = $this->write_zalo_lead_to_tenant((int) $job['tenant_id'], (int) ($job['connection_id'] ?? 0), $payload);

                return $this->mark_job_done($job, ['lead_id' => $leadId]);
            }

            $entityType = (string) ($job['entity_type'] ?? '');
            if ($entityType !== 'lead') {
                return $this->mark_job_done($job, ['message' => 'Stored event only. No writer for this entity yet.']);
            }

            $payload = kt_integration_hub_json_decode((string) ($job['payload_json'] ?? ''), []);
            $leadId = $this->write_lead_to_tenant((int) $job['tenant_id'], (string) $job['provider_code'], (int) ($job['connection_id'] ?? 0), (string) ($job['external_id'] ?? ''), $payload);

            return $this->mark_job_done($job, ['lead_id' => $leadId]);
        } catch (Throwable $e) {
            return $this->mark_job_failed_or_retry($job, $e->getMessage());
        }
    }

    public function get_jobs($tenantId = null, $limit = 200)
    {
        $db = $this->landlord_db()->from(db_prefix() . 'kt_integration_sync_jobs');
        if ($tenantId !== null) {
            $db->where('tenant_id', (int) $tenantId);
        }

        return $db->order_by('id', 'desc')->limit((int) $limit)->get()->result_array();
    }

    public function get_logs($tenantId = null, $limit = 200)
    {
        $db = $this->landlord_db()->from(db_prefix() . 'kt_integration_logs');
        if ($tenantId !== null) {
            $db->where('tenant_id', (int) $tenantId);
        }

        return $db->order_by('id', 'desc')->limit((int) $limit)->get()->result_array();
    }

    public function get_events($tenantId = null, $limit = 100)
    {
        $db = $this->landlord_db()->from(db_prefix() . 'kt_integration_webhook_events');
        if ($tenantId !== null) {
            $db->where('tenant_id', (int) $tenantId);
        }

        return $db->order_by('id', 'desc')->limit((int) $limit)->get()->result_array();
    }

    public function get_channel_orders($tenantId = null, $limit = 200)
    {
        $db = $this->landlord_db()
            ->select('o.*, c.connection_name, c.external_account_name')
            ->from(db_prefix() . 'kt_integration_channel_orders o')
            ->join(db_prefix() . 'kt_integration_connections c', 'c.id = o.connection_id', 'left');
        if ($tenantId !== null) {
            $db->where('o.tenant_id', (int) $tenantId);
        }

        return $db->order_by('o.id', 'desc')->limit((int) $limit)->get()->result_array();
    }

    public function get_channel_order($id, $tenantId = null)
    {
        $db = $this->landlord_db()->where('id', (int) $id);
        if ($tenantId !== null) {
            $db->where('tenant_id', (int) $tenantId);
        }
        $order = $db->get(db_prefix() . 'kt_integration_channel_orders')->row_array();
        if (!$order) {
            return null;
        }
        $order['items'] = $this->landlord_db()
            ->where('channel_order_id', (int) $order['id'])
            ->order_by('id', 'asc')
            ->get(db_prefix() . 'kt_integration_channel_order_items')
            ->result_array();

        return $order;
    }

    public function retry_job($id, $tenantId = null)
    {
        $db = $this->landlord_db()->where('id', (int) $id);
        if ($tenantId !== null) {
            $db->where('tenant_id', (int) $tenantId);
        }

        $db->update(db_prefix() . 'kt_integration_sync_jobs', [
            'status' => 'retry',
            'available_at' => kt_integration_hub_now(),
            'error_message' => null,
            'locked_at' => null,
            'updated_at' => kt_integration_hub_now(),
        ]);

        return $this->landlord_db()->affected_rows() > 0;
    }

    public function log($level, $event, $message, array $context = [], $tenantId = null, $connectionId = null, $providerCode = null)
    {
        $this->landlord_db()->insert(db_prefix() . 'kt_integration_logs', [
            'tenant_id' => $tenantId !== null ? (int) $tenantId : null,
            'connection_id' => $connectionId !== null ? (int) $connectionId : null,
            'provider_code' => $providerCode !== null ? (string) $providerCode : null,
            'level' => trim((string) $level) ?: 'info',
            'event' => trim((string) $event),
            'message' => trim((string) $message),
            'context_json' => kt_integration_hub_json_encode(kt_integration_hub_redact_secrets($context)),
            'created_at' => kt_integration_hub_now(),
        ]);
    }

    private function write_lead_to_tenant($tenantId, $providerCode, $connectionId, $externalId, array $payload)
    {
        $existingLink = $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->where('provider_code', $providerCode)
            ->where('entity_type', 'lead')
            ->where('external_id', $externalId)
            ->get(db_prefix() . 'kt_integration_entity_links')
            ->row_array();
        if ($existingLink) {
            return (int) $existingLink['local_id'];
        }

        $tenant = $this->get_tenant($tenantId);
        if (!$tenant) {
            throw new RuntimeException('Tenant not found.');
        }

        $landlordDb = $this->landlord_db();
        $CI = &get_instance();
        $previousDb = $CI->db;
        $normalized = $this->normalize_lead_payload($payload, $providerCode);

        if (!class_exists('DatabaseSwitcher', false)) {
            require_once module_dir_path('kt_saas', 'tenant_bootstrap/DatabaseSwitcher.php');
        }

        $switcher = new DatabaseSwitcher();
        $switchResult = $switcher->switchConnection($tenant);
        if (empty($switchResult['switched'])) {
            throw new RuntimeException('Tenant DB switch failed: ' . (string) ($switchResult['message'] ?? 'unknown'));
        }

        $this->db = $CI->db;
        try {
            $leadId = $this->upsert_tenant_lead($normalized, $payload);
        } finally {
            $CI->db = $landlordDb ?: $previousDb;
            $this->db = $CI->db;
            $CI->config->set_item('kt_saas_landlord_db', $landlordDb ?: $previousDb);
        }

        $this->landlord_db()->insert(db_prefix() . 'kt_integration_entity_links', [
            'tenant_id' => (int) $tenantId,
            'provider_code' => $providerCode,
            'connection_id' => $connectionId ?: null,
            'entity_type' => 'lead',
            'local_id' => (int) $leadId,
            'external_id' => $externalId,
            'external_hash' => sha1(kt_integration_hub_json_encode($payload)),
            'last_synced_at' => kt_integration_hub_now(),
            'created_at' => kt_integration_hub_now(),
            'updated_at' => kt_integration_hub_now(),
        ]);

        return (int) $leadId;
    }

    private function write_zalo_lead_to_tenant($tenantId, $connectionId, array $payload)
    {
        $normalized = $this->normalize_zalo_payload($payload);
        $externalUserId = trim((string) ($normalized['external_user_id'] ?? ''));
        if ($externalUserId === '') {
            throw new RuntimeException('Zalo user id is missing.');
        }

        $existingLink = $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->where('provider_code', 'zalo_oa')
            ->where('entity_type', 'zalo_user')
            ->where('external_id', $externalUserId)
            ->get(db_prefix() . 'kt_integration_entity_links')
            ->row_array();
        if ($existingLink) {
            $leadId = (int) $existingLink['local_id'];
            $this->link_zalo_message($tenantId, $connectionId, $leadId, $normalized, $payload);
            $this->log('info', 'zalo.lead_reused', 'Existing Zalo lead reused.', [
                'lead_id' => $leadId,
                'external_user_id' => $externalUserId,
                'external_message_id' => $normalized['external_message_id'] ?? '',
            ], (int) $tenantId, (int) $connectionId, 'zalo_oa');

            return $leadId;
        }

        $tenant = $this->get_tenant($tenantId);
        if (!$tenant) {
            throw new RuntimeException('Tenant not found.');
        }

        $connection = $this->get_connection($connectionId);
        $settings = kt_integration_hub_json_decode((string) ($connection['settings_json'] ?? ''), []);
        $landlordDb = $this->landlord_db();
        $CI = &get_instance();
        $previousDb = $CI->db;

        if (!class_exists('DatabaseSwitcher', false)) {
            require_once module_dir_path('kt_saas', 'tenant_bootstrap/DatabaseSwitcher.php');
        }

        $switcher = new DatabaseSwitcher();
        $switchResult = $switcher->switchConnection($tenant);
        if (empty($switchResult['switched'])) {
            throw new RuntimeException('Tenant DB switch failed: ' . (string) ($switchResult['message'] ?? 'unknown'));
        }

        $this->db = $CI->db;
        try {
            $leadId = $this->create_zalo_tenant_lead($normalized, $payload, $settings);
        } finally {
            $CI->db = $landlordDb ?: $previousDb;
            $this->db = $CI->db;
            $CI->config->set_item('kt_saas_landlord_db', $landlordDb ?: $previousDb);
        }

        $this->landlord_db()->insert(db_prefix() . 'kt_integration_entity_links', [
            'tenant_id' => (int) $tenantId,
            'provider_code' => 'zalo_oa',
            'connection_id' => $connectionId ?: null,
            'entity_type' => 'zalo_user',
            'local_id' => (int) $leadId,
            'external_id' => $externalUserId,
            'external_hash' => sha1(kt_integration_hub_json_encode($payload)),
            'last_synced_at' => kt_integration_hub_now(),
            'created_at' => kt_integration_hub_now(),
            'updated_at' => kt_integration_hub_now(),
        ]);
        $this->link_zalo_message($tenantId, $connectionId, $leadId, $normalized, $payload);

        return (int) $leadId;
    }

    private function create_zalo_tenant_lead(array $normalized, array $rawPayload, array $settings)
    {
        $statusId = (int) ($settings['lead_status'] ?? 0);
        if ($statusId <= 0) {
            $statusId = $this->default_lead_status_id();
        }
        $sourceName = trim((string) ($settings['default_lead_source'] ?? 'Zalo OA')) ?: 'Zalo OA';
        $sourceId = (int) ($settings['lead_source'] ?? 0);
        if ($sourceId <= 0) {
            $sourceId = $this->integration_lead_source_id($sourceName);
        }

        $externalUserId = trim((string) ($normalized['external_user_id'] ?? ''));
        $messageText = trim((string) ($normalized['message_text'] ?? ''));
        $description = "Nguồn: Zalo OA\n";
        $description .= "Zalo User ID: " . $externalUserId . "\n";
        if (!empty($normalized['external_message_id'])) {
            $description .= "Zalo Message ID: " . (string) $normalized['external_message_id'] . "\n";
        }
        if ($messageText !== '') {
            $description .= "Tin nhắn: " . $messageText . "\n";
        } elseif ((string) ($normalized['event_type'] ?? '') === 'click_message_button') {
            $description .= "Người dùng bấm Nhắn tin trên Zalo OA.\n";
        } elseif ((string) ($normalized['event_type'] ?? '') === 'follow') {
            $description .= "Người dùng quan tâm Zalo OA.\n";
        }
        $description .= "\nIntegration payload:\n" . kt_integration_hub_json_encode(kt_integration_hub_redact_secrets($rawPayload));

        $this->db->insert(db_prefix() . 'leads', [
            'hash' => app_generate_hash(),
            'name' => trim((string) ($normalized['name'] ?? '')) ?: 'Zalo User ' . $externalUserId,
            'company' => '',
            'description' => nl2br($description),
            'assigned' => (int) ($settings['lead_assigned'] ?? 0),
            'dateadded' => kt_integration_hub_now(),
            'status' => $statusId,
            'source' => $sourceId,
            'addedfrom' => get_staff_user_id() ?: 0,
            'email' => '',
            'phonenumber' => '',
            'is_public' => 0,
            'country' => 0,
            'address' => '',
        ]);

        $leadId = (int) $this->db->insert_id();
        if ($leadId <= 0) {
            throw new RuntimeException('Unable to create tenant lead.');
        }

        if ($this->db->table_exists(db_prefix() . 'lead_activity_log')) {
            $this->db->insert(db_prefix() . 'lead_activity_log', [
                'leadid' => $leadId,
                'description' => 'Integration Hub lead created from Zalo OA',
                'date' => kt_integration_hub_now(),
                'staffid' => get_staff_user_id() ?: 0,
                'additional_data' => '',
                'custom_activity' => 1,
            ]);
        }

        return $leadId;
    }

    private function link_zalo_message($tenantId, $connectionId, $leadId, array $normalized, array $payload)
    {
        $messageId = trim((string) ($normalized['external_message_id'] ?? ''));
        if ($messageId === '') {
            return;
        }

        $exists = $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->where('provider_code', 'zalo_oa')
            ->where('entity_type', 'zalo_message')
            ->where('external_id', $messageId)
            ->count_all_results(db_prefix() . 'kt_integration_entity_links') > 0;
        if ($exists) {
            return;
        }

        $this->landlord_db()->insert(db_prefix() . 'kt_integration_entity_links', [
            'tenant_id' => (int) $tenantId,
            'provider_code' => 'zalo_oa',
            'connection_id' => $connectionId ?: null,
            'entity_type' => 'zalo_message',
            'local_id' => (int) $leadId,
            'external_id' => $messageId,
            'external_hash' => sha1(kt_integration_hub_json_encode($payload)),
            'last_synced_at' => kt_integration_hub_now(),
            'created_at' => kt_integration_hub_now(),
            'updated_at' => kt_integration_hub_now(),
        ]);
    }

    private function write_tiktok_order_staging($tenantId, $connectionId, array $payload, array $connection, $externalOrderId = '')
    {
        $event = $this->normalize_tiktok_order_event($payload);
        $detailPayload = is_array($payload['mock_order_detail'] ?? null) ? $payload['mock_order_detail'] : $payload;
        if (empty($payload['mock_order_detail'])) {
            $client = $this->tiktok_client();
            $detailResult = $client->fetchOrderDetail($connection, $externalOrderId ?: $event['external_order_id'], $payload);
            if (!empty($detailResult['success']) && is_array($detailResult['order'] ?? null)) {
                $detailPayload = $detailResult['order'];
            }
        }
        $detail = $this->normalize_tiktok_order_detail($detailPayload);
        if ($detail['external_order_id'] === '' && $externalOrderId !== '') {
            $detail['external_order_id'] = (string) $externalOrderId;
        }
        if ($detail['external_order_id'] === '' && $event['external_order_id'] !== '') {
            $detail['external_order_id'] = $event['external_order_id'];
        }
        if ($detail['external_order_id'] === '') {
            throw new RuntimeException('TikTok order id is missing.');
        }

        if (empty($payload['mock_order_detail']) && (string) $this->connection_setting($connection, 'connection_mode', 'dry_run') !== 'dry_run') {
            throw new RuntimeException('TikTok real order detail pull is pending Partner Center endpoint verification.');
        }

        if ($detail['order_status'] === '' && $event['order_status'] !== '') {
            $detail['order_status'] = $event['order_status'];
        }

        $now = kt_integration_hub_now();
        $record = [
            'tenant_id' => (int) $tenantId,
            'connection_id' => (int) $connectionId,
            'provider_code' => 'tiktok_shop',
            'external_order_id' => $detail['external_order_id'],
            'external_order_code' => $detail['order_code'],
            'order_status' => $detail['order_status'],
            'payment_status' => $detail['payment_status'],
            'fulfillment_status' => $detail['fulfillment_status'],
            'buyer_name' => $detail['buyer_name'],
            'buyer_phone_masked' => $this->mask_phone($detail['buyer_phone']),
            'buyer_email' => $detail['buyer_email'],
            'currency' => $detail['currency'] ?: 'VND',
            'subtotal' => (float) $detail['subtotal'],
            'shipping_fee' => (float) $detail['shipping_fee'],
            'discount_total' => (float) $detail['discount_total'],
            'grand_total' => (float) $detail['grand_total'],
            'ordered_at' => $detail['ordered_at'] ?: null,
            'synced_at' => $now,
            'raw_json' => kt_integration_hub_json_encode(kt_integration_hub_redact_secrets($payload)),
            'mapping_status' => 'unmapped',
            'updated_at' => $now,
        ];

        $existing = $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->where('provider_code', 'tiktok_shop')
            ->where('external_order_id', $detail['external_order_id'])
            ->get(db_prefix() . 'kt_integration_channel_orders')
            ->row_array();

        if ($existing) {
            $this->landlord_db()->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_integration_channel_orders', $record);
            $channelOrderId = (int) $existing['id'];
        } else {
            $record['created_at'] = $now;
            $this->landlord_db()->insert(db_prefix() . 'kt_integration_channel_orders', $record);
            $channelOrderId = (int) $this->landlord_db()->insert_id();
        }

        if ($channelOrderId <= 0) {
            throw new RuntimeException('Unable to upsert TikTok channel order.');
        }

        $this->landlord_db()->where('channel_order_id', $channelOrderId)->delete(db_prefix() . 'kt_integration_channel_order_items');
        foreach ($detail['items'] as $item) {
            $this->landlord_db()->insert(db_prefix() . 'kt_integration_channel_order_items', [
                'tenant_id' => (int) $tenantId,
                'channel_order_id' => $channelOrderId,
                'provider_code' => 'tiktok_shop',
                'external_item_id' => (string) ($item['external_item_id'] ?? ''),
                'external_sku_id' => (string) ($item['external_sku_id'] ?? ''),
                'sku' => (string) ($item['sku'] ?? ''),
                'item_name' => (string) ($item['item_name'] ?? ''),
                'quantity' => (float) ($item['quantity'] ?? 0),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'total_price' => (float) ($item['total_price'] ?? 0),
                'raw_json' => kt_integration_hub_json_encode(kt_integration_hub_redact_secrets($item['raw'] ?? $item)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->upsert_entity_link((int) $tenantId, 'tiktok_shop', (int) $connectionId, 'tiktok_order', $channelOrderId, $detail['external_order_id'], $payload);
        if ($detail['buyer_phone'] !== '' || $detail['buyer_email'] !== '') {
            $customerKey = $detail['buyer_email'] !== '' ? $detail['buyer_email'] : $detail['buyer_phone'];
            $this->upsert_entity_link((int) $tenantId, 'tiktok_shop', (int) $connectionId, 'tiktok_customer', $channelOrderId, $customerKey, [
                'buyer_name' => $detail['buyer_name'],
                'buyer_phone_present' => $detail['buyer_phone'] !== '',
                'buyer_email_present' => $detail['buyer_email'] !== '',
            ]);
        }

        $this->log('info', 'tiktok.order_staged', 'TikTok Shop order staged.', [
            'channel_order_id' => $channelOrderId,
            'external_order_id' => $detail['external_order_id'],
            'order_status' => $detail['order_status'],
            'grand_total' => $detail['grand_total'],
        ], (int) $tenantId, (int) $connectionId, 'tiktok_shop');

        return $channelOrderId;
    }

    private function normalize_tiktok_order_event(array $payload)
    {
        return [
            'source_provider' => 'tiktok_shop',
            'event_id' => (string) ($payload['event_id'] ?? $payload['id'] ?? ''),
            'event_type' => (string) ($payload['event_type'] ?? $payload['type'] ?? 'tiktok_order_event'),
            'external_order_id' => (string) ($payload['order_id'] ?? $payload['order']['id'] ?? $payload['external_order_id'] ?? ''),
            'shop_id' => (string) ($payload['shop_id'] ?? $payload['shop_cipher'] ?? ''),
            'order_status' => (string) ($payload['status'] ?? $payload['order_status'] ?? ''),
            'timestamp' => (string) ($payload['timestamp'] ?? ''),
            'raw' => $payload,
        ];
    }

    private function normalize_tiktok_order_detail(array $payload)
    {
        $buyer = is_array($payload['buyer'] ?? null) ? $payload['buyer'] : [];
        $items = [];
        foreach ((array) ($payload['items'] ?? $payload['line_items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $items[] = [
                'external_item_id' => (string) ($item['item_id'] ?? $item['product_id'] ?? ''),
                'external_sku_id' => (string) ($item['sku_id'] ?? ''),
                'sku' => (string) ($item['sku'] ?? $item['seller_sku'] ?? ''),
                'item_name' => (string) ($item['name'] ?? $item['item_name'] ?? $item['product_name'] ?? ''),
                'quantity' => (float) ($item['quantity'] ?? 0),
                'unit_price' => (float) ($item['unit_price'] ?? $item['price'] ?? 0),
                'total_price' => (float) ($item['total_price'] ?? 0),
                'raw' => $item,
            ];
        }

        return [
            'source_provider' => 'tiktok_shop',
            'external_order_id' => (string) ($payload['order_id'] ?? $payload['id'] ?? ''),
            'order_code' => (string) ($payload['order_code'] ?? $payload['order_id'] ?? $payload['id'] ?? ''),
            'order_status' => (string) ($payload['status'] ?? $payload['order_status'] ?? ''),
            'payment_status' => (string) ($payload['payment_status'] ?? ''),
            'fulfillment_status' => (string) ($payload['fulfillment_status'] ?? ''),
            'buyer_name' => (string) ($buyer['name'] ?? $payload['buyer_name'] ?? ''),
            'buyer_phone' => (string) ($buyer['phone'] ?? $payload['buyer_phone'] ?? ''),
            'buyer_email' => (string) ($buyer['email'] ?? $payload['buyer_email'] ?? ''),
            'currency' => (string) ($payload['currency'] ?? 'VND'),
            'subtotal' => (float) ($payload['subtotal'] ?? 0),
            'shipping_fee' => (float) ($payload['shipping_fee'] ?? 0),
            'discount_total' => (float) ($payload['discount_total'] ?? 0),
            'grand_total' => (float) ($payload['grand_total'] ?? 0),
            'ordered_at' => (string) ($payload['ordered_at'] ?? ''),
            'items' => $items,
            'raw' => $payload,
        ];
    }

    private function upsert_entity_link($tenantId, $providerCode, $connectionId, $entityType, $localId, $externalId, array $payload)
    {
        $externalId = trim((string) $externalId);
        if ($externalId === '') {
            return;
        }

        $record = [
            'connection_id' => $connectionId ?: null,
            'local_id' => (int) $localId,
            'external_hash' => sha1(kt_integration_hub_json_encode($payload)),
            'last_synced_at' => kt_integration_hub_now(),
            'updated_at' => kt_integration_hub_now(),
        ];
        $existing = $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->where('provider_code', (string) $providerCode)
            ->where('entity_type', (string) $entityType)
            ->where('external_id', $externalId)
            ->get(db_prefix() . 'kt_integration_entity_links')
            ->row_array();
        if ($existing) {
            $this->landlord_db()->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_integration_entity_links', $record);
            return;
        }

        $record['tenant_id'] = (int) $tenantId;
        $record['provider_code'] = (string) $providerCode;
        $record['entity_type'] = (string) $entityType;
        $record['external_id'] = $externalId;
        $record['created_at'] = kt_integration_hub_now();
        $this->landlord_db()->insert(db_prefix() . 'kt_integration_entity_links', $record);
    }

    private function mask_phone($phone)
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);
        if ($phone === '') {
            return '';
        }
        if (strlen($phone) <= 6) {
            return str_repeat('*', strlen($phone));
        }

        return substr($phone, 0, 3) . str_repeat('*', max(strlen($phone) - 6, 3)) . substr($phone, -3);
    }

    private function upsert_tenant_lead(array $lead, array $rawPayload)
    {
        $email = trim((string) ($lead['email'] ?? ''));
        $phone = trim((string) ($lead['phone'] ?? ''));
        if ($email !== '') {
            $existing = $this->db->where('email', $email)->get(db_prefix() . 'leads')->row_array();
            if ($existing) {
                return (int) $existing['id'];
            }
        }
        if ($phone !== '') {
            $existing = $this->db->where('phonenumber', $phone)->get(db_prefix() . 'leads')->row_array();
            if ($existing) {
                return (int) $existing['id'];
            }
        }

        $statusId = $this->default_lead_status_id();
        $sourceId = $this->integration_lead_source_id();
        $description = trim((string) ($lead['message'] ?? ''));
        $description .= "\n\nIntegration payload:\n" . kt_integration_hub_json_encode(kt_integration_hub_redact_secrets($rawPayload));

        $this->db->insert(db_prefix() . 'leads', [
            'hash' => app_generate_hash(),
            'name' => trim((string) ($lead['name'] ?? 'Integration Lead')) ?: 'Integration Lead',
            'company' => trim((string) ($lead['company'] ?? '')),
            'description' => nl2br($description),
            'assigned' => 0,
            'dateadded' => kt_integration_hub_now(),
            'status' => $statusId,
            'source' => $sourceId,
            'addedfrom' => get_staff_user_id() ?: 0,
            'email' => $email,
            'phonenumber' => $phone,
            'is_public' => 0,
            'country' => 0,
            'address' => '',
        ]);

        $leadId = (int) $this->db->insert_id();
        if ($leadId <= 0) {
            throw new RuntimeException('Unable to create tenant lead.');
        }

        if ($this->db->table_exists(db_prefix() . 'lead_activity_log')) {
            $this->db->insert(db_prefix() . 'lead_activity_log', [
                'leadid' => $leadId,
                'description' => 'Integration Hub lead created from ' . (string) ($lead['source_provider'] ?? 'webhook'),
                'date' => kt_integration_hub_now(),
                'staffid' => get_staff_user_id() ?: 0,
                'additional_data' => '',
                'custom_activity' => 1,
            ]);
        }

        return $leadId;
    }

    private function normalize_lead_payload(array $payload, $providerCode)
    {
        $lead = is_array($payload['lead'] ?? null) ? $payload['lead'] : $payload;

        return [
            'source_provider' => (string) $providerCode,
            'external_id' => (string) ($payload['external_id'] ?? $payload['id'] ?? $payload['event_id'] ?? ''),
            'name' => (string) ($lead['name'] ?? $lead['full_name'] ?? $lead['customer_name'] ?? 'Integration Lead'),
            'phone' => (string) ($lead['phone'] ?? $lead['phonenumber'] ?? $lead['mobile'] ?? ''),
            'email' => (string) ($lead['email'] ?? ''),
            'company' => (string) ($lead['company'] ?? ''),
            'message' => (string) ($lead['message'] ?? $lead['note'] ?? $payload['message'] ?? ''),
        ];
    }

    private function normalize_zalo_payload(array $payload)
    {
        $eventType = trim((string) ($payload['event_name'] ?? $payload['event_type'] ?? 'zalo_event'));
        $sender = is_array($payload['sender'] ?? null) ? $payload['sender'] : [];
        $follower = is_array($payload['follower'] ?? null) ? $payload['follower'] : [];
        $message = is_array($payload['message'] ?? null) ? $payload['message'] : [];
        $externalUserId = trim((string) ($sender['id'] ?? $payload['user_id_by_app'] ?? $follower['id'] ?? $payload['user_id'] ?? ''));
        $messageId = trim((string) ($message['msg_id'] ?? $payload['msg_id'] ?? $payload['message_id'] ?? ''));
        $messageText = trim((string) ($message['text'] ?? $message['message'] ?? $payload['text'] ?? ''));
        $normalizedEvent = strtolower($eventType);
        if (strpos($normalizedEvent, 'click') !== false && (strpos($normalizedEvent, 'message') !== false || strpos($normalizedEvent, 'nhan') !== false)) {
            $eventType = 'click_message_button';
            if ($messageText === '') {
                $messageText = 'Người dùng bấm Nhắn tin trên Zalo OA.';
            }
        }

        return [
            'source_provider' => 'zalo_oa',
            'external_user_id' => $externalUserId,
            'external_message_id' => $messageId,
            'event_type' => $eventType,
            'message_text' => $messageText,
            'name' => $externalUserId !== '' ? 'Zalo User ' . $externalUserId : 'Zalo User',
            'phone' => '',
            'email' => '',
            'description' => trim('Zalo OA message: ' . $messageText),
            'raw' => $payload,
        ];
    }

    private function connection_settings_payload($providerCode, array $data, array $existing)
    {
        $existingSettings = kt_integration_hub_json_decode((string) ($existing['settings_json'] ?? ''), []);
        if ($providerCode === 'zalo_oa') {
            $connectionForUrls = [
                'provider_code' => 'zalo_oa',
                'public_key' => (string) ($existing['public_key'] ?? ''),
            ];
            $hasAccessToken = !empty($existing['access_token_encrypted']) || trim((string) ($data['access_token'] ?? '')) !== '';

            return kt_integration_hub_redact_secrets([
                'app_id' => trim((string) ($data['app_id'] ?? ($existingSettings['app_id'] ?? ''))),
                'oa_id' => trim((string) ($data['oa_id'] ?? ($existing['external_account_id'] ?? ($existingSettings['oa_id'] ?? '')))),
                'oa_name' => trim((string) ($data['external_account_name'] ?? ($existing['external_account_name'] ?? ($existingSettings['oa_name'] ?? '')))),
                'connection_mode' => in_array(($data['connection_mode'] ?? ($existingSettings['connection_mode'] ?? 'manual_token')), ['manual_token', 'oauth_prepared'], true)
                    ? (string) ($data['connection_mode'] ?? ($existingSettings['connection_mode'] ?? 'manual_token'))
                    : 'manual_token',
                'oauth_callback_url' => kt_integration_hub_oauth_callback_url($connectionForUrls, 'zalo_oa'),
                'webhook_url' => kt_integration_hub_webhook_url($connectionForUrls),
                'default_lead_source' => trim((string) ($data['default_lead_source'] ?? ($existingSettings['default_lead_source'] ?? 'Zalo OA'))) ?: 'Zalo OA',
                'lead_assigned' => (int) ($data['lead_assigned'] ?? ($existingSettings['lead_assigned'] ?? 0)),
                'lead_status' => (int) ($data['lead_status'] ?? ($existingSettings['lead_status'] ?? 0)),
                'lead_source' => (int) ($data['lead_source'] ?? ($existingSettings['lead_source'] ?? 0)),
                'allow_unsigned_test_webhook' => array_key_exists('allow_unsigned_test_webhook', $data) ? (int) !empty($data['allow_unsigned_test_webhook']) : (int) ($existingSettings['allow_unsigned_test_webhook'] ?? (ENVIRONMENT !== 'production' ? 1 : 0)),
                'create_lead_on_follow' => array_key_exists('create_lead_on_follow', $data) ? (int) !empty($data['create_lead_on_follow']) : (int) ($existingSettings['create_lead_on_follow'] ?? 1),
                'token_expires_at' => trim((string) ($data['token_expires_at'] ?? ($existingSettings['token_expires_at'] ?? ''))),
                'last_webhook_at' => $existingSettings['last_webhook_at'] ?? '',
                'last_connected_at' => $hasAccessToken ? ($existingSettings['last_connected_at'] ?? kt_integration_hub_now()) : ($existingSettings['last_connected_at'] ?? ''),
                'oauth_status' => $hasAccessToken ? 'token_stored' : 'not_connected',
                'connector_scope' => 'zalo_oa_v1_webhook_intake',
            ]);
        }

        if ($providerCode === 'tiktok_shop') {
            $connectionForUrls = [
                'provider_code' => 'tiktok_shop',
                'public_key' => (string) ($existing['public_key'] ?? ''),
            ];
            $mode = (string) ($data['connection_mode'] ?? ($existingSettings['connection_mode'] ?? 'dry_run'));
            if (!in_array($mode, ['dry_run', 'manual_credentials', 'oauth_prepared'], true)) {
                $mode = 'dry_run';
            }
            $hasAccessToken = !empty($existing['access_token_encrypted']) || trim((string) ($data['access_token'] ?? '')) !== '';

            return kt_integration_hub_redact_secrets([
                'connection_mode' => $mode,
                'app_key' => trim((string) ($data['app_key'] ?? ($existingSettings['app_key'] ?? ''))),
                'shop_id' => trim((string) ($data['shop_id'] ?? ($existing['external_account_id'] ?? ($existingSettings['shop_id'] ?? '')))),
                'shop_cipher' => trim((string) ($data['shop_cipher'] ?? ($existingSettings['shop_cipher'] ?? ''))),
                'shop_name' => trim((string) ($data['external_account_name'] ?? ($existing['external_account_name'] ?? ($existingSettings['shop_name'] ?? '')))),
                'region' => strtoupper(trim((string) ($data['region'] ?? ($existingSettings['region'] ?? 'VN')))),
                'token_expires_at' => trim((string) ($data['token_expires_at'] ?? ($existingSettings['token_expires_at'] ?? ''))),
                'webhook_url' => kt_integration_hub_webhook_url($connectionForUrls),
                'oauth_callback_url' => kt_integration_hub_oauth_callback_url($connectionForUrls, 'tiktok_shop'),
                'sync_orders_enabled' => array_key_exists('sync_orders_enabled', $data) ? (int) !empty($data['sync_orders_enabled']) : (int) ($existingSettings['sync_orders_enabled'] ?? 1),
                'sync_products_enabled' => 0,
                'auto_create_customer' => 0,
                'auto_create_invoice' => 0,
                'dry_run_enabled' => array_key_exists('dry_run_enabled', $data) ? (int) !empty($data['dry_run_enabled']) : (int) ($existingSettings['dry_run_enabled'] ?? 1),
                'last_webhook_at' => $existingSettings['last_webhook_at'] ?? '',
                'last_connected_at' => $hasAccessToken ? ($existingSettings['last_connected_at'] ?? kt_integration_hub_now()) : ($existingSettings['last_connected_at'] ?? ''),
                'oauth_status' => $hasAccessToken ? 'token_stored' : 'not_connected',
                'api_status' => 'mock_ready_real_endpoint_pending_verification',
                'connector_scope' => 'tiktok_shop_v1_order_staging',
            ]);
        }

        return kt_integration_hub_redact_secrets([
            'lead_assigned' => (int) ($data['lead_assigned'] ?? ($existingSettings['lead_assigned'] ?? 0)),
            'lead_status' => (int) ($data['lead_status'] ?? ($existingSettings['lead_status'] ?? 0)),
            'lead_source' => (int) ($data['lead_source'] ?? ($existingSettings['lead_source'] ?? 0)),
        ]);
    }

    private function connection_setting(array $connection, $key, $default = '')
    {
        $settings = kt_integration_hub_json_decode((string) ($connection['settings_json'] ?? ''), []);

        return $settings[$key] ?? $default;
    }

    private function external_account_id_payload($providerCode, array $data, array $existing)
    {
        if ($providerCode === 'zalo_oa') {
            return trim((string) ($data['oa_id'] ?? ($existing['external_account_id'] ?? '')));
        }
        if ($providerCode === 'tiktok_shop') {
            return trim((string) ($data['shop_id'] ?? $data['shop_cipher'] ?? ($existing['external_account_id'] ?? '')));
        }

        return trim((string) ($data['external_account_id'] ?? ($existing['external_account_id'] ?? '')));
    }

    private function default_lead_status_id()
    {
        $table = db_prefix() . 'leads_status';
        if ($this->db->field_exists('statusorder', $table)) {
            $this->db->order_by('statusorder', 'asc');
        } else {
            $this->db->order_by('id', 'asc');
        }

        $row = $this->db->limit(1)->get($table)->row_array();

        return (int) ($row['id'] ?? 1);
    }

    private function integration_lead_source_id($sourceName = 'Integration Hub')
    {
        $sourceName = trim((string) $sourceName) ?: 'Integration Hub';
        $row = $this->db->where('name', $sourceName)->get(db_prefix() . 'leads_sources')->row_array();
        if ($row) {
            return (int) $row['id'];
        }

        $this->db->insert(db_prefix() . 'leads_sources', [
            'name' => $sourceName,
        ]);

        return (int) $this->db->insert_id();
    }

    private function mark_job_done(array $job, array $result)
    {
        $this->landlord_db()->where('id', (int) $job['id'])->update(db_prefix() . 'kt_integration_sync_jobs', [
            'status' => 'done',
            'result_json' => kt_integration_hub_json_encode($result),
            'error_message' => null,
            'locked_at' => null,
            'updated_at' => kt_integration_hub_now(),
        ]);
        if (!empty($job['webhook_event_id'])) {
            $this->landlord_db()->where('id', (int) $job['webhook_event_id'])->update(db_prefix() . 'kt_integration_webhook_events', [
                'processing_status' => 'processed',
                'processed_at' => kt_integration_hub_now(),
            ]);
        }
        if (!empty($job['connection_id'])) {
            $this->landlord_db()->where('id', (int) $job['connection_id'])->update(db_prefix() . 'kt_integration_connections', [
                'last_sync_at' => kt_integration_hub_now(),
                'updated_at' => kt_integration_hub_now(),
            ]);
        }
        $this->log('info', 'job.done', 'Sync job completed.', ['job_id' => (int) $job['id'], 'result' => $result], (int) $job['tenant_id'], (int) ($job['connection_id'] ?? 0), (string) $job['provider_code']);

        return ['success' => true, 'status' => 'done'];
    }

    private function mark_job_failed_or_retry(array $job, $message)
    {
        $attempts = (int) ($job['attempts'] ?? 0) + 1;
        $maxAttempts = max((int) ($job['max_attempts'] ?? 5), 1);
        $status = $attempts >= $maxAttempts ? 'failed' : 'retry';
        $delayMinutes = min(60, max(1, $attempts * 5));

        $this->landlord_db()->where('id', (int) $job['id'])->update(db_prefix() . 'kt_integration_sync_jobs', [
            'status' => $status,
            'attempts' => $attempts,
            'error_message' => (string) $message,
            'available_at' => $status === 'retry' ? date('Y-m-d H:i:s', time() + ($delayMinutes * 60)) : null,
            'locked_at' => null,
            'updated_at' => kt_integration_hub_now(),
        ]);
        $this->log('error', 'job.' . $status, 'Sync job failed.', ['job_id' => (int) $job['id'], 'error' => $message], (int) $job['tenant_id'], (int) ($job['connection_id'] ?? 0), (string) $job['provider_code']);

        return ['success' => false, 'status' => $status, 'message' => (string) $message];
    }

    private function get_tenant($tenantId)
    {
        return $this->landlord_db()
            ->where('id', (int) $tenantId)
            ->where('deleted_at IS NULL', null, false)
            ->get(db_prefix() . 'kt_saas_tenants')
            ->row_array();
    }

    private function count_rows($table, $tenantId = null, array $where = [])
    {
        $db = $this->landlord_db()->from(db_prefix() . $table);
        if ($tenantId !== null) {
            $db->where('tenant_id', (int) $tenantId);
        }
        foreach ($where as $field => $value) {
            $db->where($field, $value);
        }

        return (int) $db->count_all_results();
    }

    private function external_event_id(array $payload, $rawBody)
    {
        if (($payload['event_name'] ?? '') !== '' || isset($payload['sender']) || isset($payload['follower'])) {
            $normalized = $this->normalize_zalo_payload($payload);
            if (!empty($normalized['external_message_id'])) {
                return (string) $normalized['external_message_id'];
            }
            $parts = array_filter([
                (string) ($normalized['event_type'] ?? ''),
                (string) ($normalized['external_user_id'] ?? ''),
                (string) ($payload['timestamp'] ?? ''),
            ]);
            if (!empty($parts)) {
                return implode(':', $parts);
            }
        }

        $id = trim((string) ($payload['event_id'] ?? $payload['external_event_id'] ?? $payload['external_id'] ?? $payload['id'] ?? ''));

        return $id !== '' ? $id : sha1((string) $rawBody);
    }

    private function entity_type_from_event($eventType, array $payload)
    {
        $candidate = strtolower(trim((string) ($payload['entity_type'] ?? $eventType)));
        if (strpos($candidate, 'order') !== false) {
            return 'order';
        }
        if (strpos($candidate, 'message') !== false) {
            return 'message';
        }

        return 'lead';
    }

    private function generate_public_key()
    {
        do {
            $key = bin2hex(random_bytes(16));
            $exists = $this->landlord_db()
                ->where('public_key', $key)
                ->count_all_results(db_prefix() . 'kt_integration_connections') > 0;
        } while ($exists);

        return $key;
    }

    private function generate_webhook_secret()
    {
        return 'whsec_' . bin2hex(random_bytes(24));
    }

    private function merge_encrypted_value(array $existing, $field, $plain)
    {
        if ($plain === null) {
            return $existing[$field] ?? null;
        }

        $plain = trim((string) $plain);
        if ($plain === '') {
            return $existing[$field] ?? null;
        }

        return kt_integration_hub_encrypt_value($plain);
    }

    private function landlord_db()
    {
        $landlordDb = $this->config->item('kt_saas_landlord_db');

        return $landlordDb ?: $this->db;
    }

    private function tiktok_client()
    {
        if (!class_exists('TikTokShopApiClient', false)) {
            require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/TikTokShopApiClient.php');
        }

        return new TikTokShopApiClient();
    }
}


