<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_sepay_model extends App_Model
{
    public function get_settings($tenantId = null, $fallbackGlobal = true)
    {
        $tenantId = $this->normalizeTenantId($tenantId, $fallbackGlobal);
        $row = $this->find_settings_row($tenantId);
        if (!$row && $tenantId !== null && $fallbackGlobal) {
            $row = $this->find_settings_row(null);
        }
        if (!$row) {
            return [];
        }

        $row['api_token'] = kt_sepay_decrypt_value($row['api_token_encrypted'] ?? '');
        $row['webhook_secret'] = kt_sepay_decrypt_value($row['webhook_secret_encrypted'] ?? '');

        return $row;
    }

    public function save_settings(array $data, $tenantId = null)
    {
        $tenantId = $this->normalizeTenantId($tenantId);
        $existing = $this->find_settings_row($tenantId) ?: [];
        $payload = [
            'tenant_id'                  => $tenantId,
            'environment'                => in_array(($data['environment'] ?? 'sandbox'), ['sandbox', 'production'], true) ? $data['environment'] : 'sandbox',
            'bank_code'                  => trim((string) ($data['bank_code'] ?? '')),
            'account_number'             => trim((string) ($data['account_number'] ?? '')),
            'account_name'               => trim((string) ($data['account_name'] ?? '')),
            'api_token_encrypted'        => $this->mergeEncryptedValue($existing, 'api_token_encrypted', $data['api_token'] ?? null),
            'webhook_secret_encrypted'   => $this->mergeEncryptedValue($existing, 'webhook_secret_encrypted', $data['webhook_secret'] ?? null),
            'qr_template'                => in_array(($data['qr_template'] ?? 'compact'), ['compact', 'qronly', 'default'], true) ? $data['qr_template'] : 'compact',
            'reference_prefix_invoice'   => $this->normalizeReferencePrefix($data['reference_prefix_invoice'] ?? 'KTINV', 'KTINV'),
            'reference_prefix_subscription' => $this->normalizeReferencePrefix($data['reference_prefix_subscription'] ?? 'KTSAAS', 'KTSAAS'),
            'reference_prefix_manual'    => $this->normalizeReferencePrefix($data['reference_prefix_manual'] ?? 'KTPAY', 'KTPAY'),
            'auto_reconcile_enabled'     => !empty($data['auto_reconcile_enabled']) ? 1 : 0,
            'reconcile_interval_minutes' => max((int) ($data['reconcile_interval_minutes'] ?? 15), 1),
            'payment_request_expiry_minutes' => max((int) ($data['payment_request_expiry_minutes'] ?? 60), 5),
            'last_reconcile_transaction_id' => array_key_exists('last_reconcile_transaction_id', $data) ? $this->nullableString($data['last_reconcile_transaction_id']) : ($existing['last_reconcile_transaction_id'] ?? null),
            'last_reconcile_at'          => array_key_exists('last_reconcile_at', $data) ? $this->nullableString($data['last_reconcile_at']) : ($existing['last_reconcile_at'] ?? null),
            'allow_partial_payment'      => !empty($data['allow_partial_payment']) ? 1 : 0,
            'is_active'                  => !empty($data['is_active']) ? 1 : 0,
            'updated_at'                 => kt_sepay_now(),
        ];

        if ($existing) {
            $this->landlord_db()->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_sepay_settings', $payload);
            return true;
        }

        $payload['created_at'] = kt_sepay_now();
        $this->landlord_db()->insert(db_prefix() . 'kt_sepay_settings', $payload);

        return $this->landlord_db()->insert_id() > 0;
    }

    public function is_active($tenantId = null, $fallbackGlobal = true)
    {
        $settings = $this->get_settings($tenantId, $fallbackGlobal);

        return !empty($settings['is_active']) && !empty($settings['bank_code']) && !empty($settings['account_number']);
    }

    public function get_tenant_settings($tenantId)
    {
        return $this->get_settings($tenantId, false);
    }

    public function get_request_settings(array $request)
    {
        $tenantId = !empty($request['tenant_id']) ? (int) $request['tenant_id'] : null;
        $contextType = (string) ($request['context_type'] ?? '');

        if ($this->is_landlord_revenue_context($contextType)) {
            return $this->get_settings(null, false);
        }

        if ($tenantId !== null) {
            return $this->get_settings($tenantId, false);
        }

        return $this->get_settings(null, false);
    }

    public function is_landlord_revenue_context($contextType)
    {
        return in_array(trim((string) $contextType), [
            'kt_saas_subscription',
            'kt_matbao_invoice_order',
        ], true);
    }

    public function validate_webhook_authorization($authorization)
    {
        $authorization = trim((string) $authorization);
        if ($authorization === '') {
            return false;
        }

        $rows = $this->landlord_db()
            ->select('webhook_secret_encrypted')
            ->from(db_prefix() . 'kt_sepay_settings')
            ->get()
            ->result_array();

        foreach ($rows as $row) {
            $secret = trim((string) kt_sepay_decrypt_value($row['webhook_secret_encrypted'] ?? ''));
            if ($secret !== '' && hash_equals('Apikey ' . $secret, $authorization)) {
                return true;
            }
        }

        return false;
    }

    public function validate_tenant_webhook_authorization($tenantId, $authorization)
    {
        $tenantId = (int) $tenantId;
        $authorization = trim((string) $authorization);
        if ($tenantId <= 0 || $authorization === '') {
            return false;
        }

        $row = $this->landlord_db()
            ->select('webhook_secret_encrypted')
            ->from(db_prefix() . 'kt_sepay_settings')
            ->where('tenant_id', $tenantId)
            ->get()
            ->row_array();

        if (!$row) {
            return false;
        }

        $secret = trim((string) kt_sepay_decrypt_value($row['webhook_secret_encrypted'] ?? ''));

        return $secret !== '' && hash_equals('Apikey ' . $secret, $authorization);
    }

    public function create_payment_request(array $payload)
    {
        $payload = array_merge([
            'context_type'    => 'manual',
            'context_id'      => 0,
            'tenant_id'       => null,
            'invoice_id'      => null,
            'subscription_id' => null,
            'amount'          => 0,
            'currency'        => 'VND',
            'reference_code'  => '',
            'access_token'    => '',
            'description'     => '',
            'qr_url'          => '',
            'status'          => 'pending',
            'paid_amount'     => 0,
            'payment_mode'    => 'sepay',
            'metadata_json'   => null,
            'expires_at'      => null,
            'processed_at'    => null,
            'created_by'      => null,
            'created_at'      => kt_sepay_now(),
            'updated_at'      => kt_sepay_now(),
        ], $payload);

        $this->landlord_db()->insert(db_prefix() . 'kt_sepay_payment_requests', $payload);

        return (int) $this->landlord_db()->insert_id();
    }

    public function get_payment_request($id)
    {
        return $this->landlord_db()
            ->where('id', (int) $id)
            ->get(db_prefix() . 'kt_sepay_payment_requests')
            ->row_array();
    }

    public function get_payment_request_by_token($id, $token)
    {
        return $this->landlord_db()
            ->where('id', (int) $id)
            ->where('access_token', trim((string) $token))
            ->get(db_prefix() . 'kt_sepay_payment_requests')
            ->row_array();
    }

    public function get_payment_request_by_reference($referenceCode, $tenantId = null)
    {
        $db = $this->landlord_db()
            ->where('reference_code', trim((string) $referenceCode));

        $tenantId = (int) $tenantId;
        if ($tenantId > 0) {
            $db->where('tenant_id', $tenantId);
        } else {
            $db->where('tenant_id IS NULL', null, false);
        }

        return $db->get(db_prefix() . 'kt_sepay_payment_requests')->row_array();
    }

    public function get_payment_request_by_reference_any_owner($referenceCode)
    {
        return $this->landlord_db()
            ->where('reference_code', trim((string) $referenceCode))
            ->get(db_prefix() . 'kt_sepay_payment_requests')
            ->row_array();
    }

    public function get_latest_open_payment_request_for_context($contextType, $contextId)
    {
        return $this->landlord_db()
            ->where('context_type', trim((string) $contextType))
            ->where('context_id', (int) $contextId)
            ->where_in('status', ['pending', 'partial'])
            ->order_by('id', 'desc')
            ->get(db_prefix() . 'kt_sepay_payment_requests')
            ->row_array();
    }

    public function update_payment_request($id, array $payload)
    {
        $payload['updated_at'] = kt_sepay_now();
        $this->landlord_db()->where('id', (int) $id)->update(db_prefix() . 'kt_sepay_payment_requests', $payload);

        return $this->landlord_db()->affected_rows() >= 0;
    }

    public function get_payment_requests(array $filters = [])
    {
        $db = $this->landlord_db()
            ->select('*')
            ->from(db_prefix() . 'kt_sepay_payment_requests');

        if (!empty($filters['tenant_id'])) {
            $db->where('tenant_id', (int) $filters['tenant_id']);
        }
        if (!empty($filters['status'])) {
            $db->where('status', trim((string) $filters['status']));
        }
        if (!empty($filters['context_type'])) {
            $db->where('context_type', trim((string) $filters['context_type']));
        }

        return $db->order_by('id', 'desc')->get()->result_array();
    }

    public function get_pending_expired_payment_requests()
    {
        return $this->landlord_db()
            ->where_in('status', ['pending', 'partial'])
            ->where('expires_at IS NOT NULL', null, false)
            ->where('expires_at <', kt_sepay_now())
            ->get(db_prefix() . 'kt_sepay_payment_requests')
            ->result_array();
    }

    public function log_webhook(array $payload)
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $this->landlord_db()->insert(db_prefix() . 'kt_sepay_webhook_logs', [
            'source'         => trim((string) ($payload['source'] ?? 'webhook')),
            'headers'        => kt_sepay_json_encode($headers),
            'raw_body'       => (string) ($payload['raw_body'] ?? ''),
            'parsed_payload' => kt_sepay_json_encode($payload['parsed_payload'] ?? []),
            'ip_address'     => $this->input->ip_address(),
            'status'         => trim((string) ($payload['status'] ?? 'received')),
            'error_message'  => trim((string) ($payload['error_message'] ?? '')),
            'created_at'     => kt_sepay_now(),
        ]);

        return (int) $this->landlord_db()->insert_id();
    }

    public function update_webhook_log($id, array $payload)
    {
        $this->landlord_db()->where('id', (int) $id)->update(db_prefix() . 'kt_sepay_webhook_logs', $payload);

        return $this->landlord_db()->affected_rows() >= 0;
    }

    public function get_webhook_logs($limit = 200)
    {
        return $this->landlord_db()
            ->order_by('id', 'desc')
            ->limit((int) $limit)
            ->get(db_prefix() . 'kt_sepay_webhook_logs')
            ->result_array();
    }

    public function insert_transaction_if_new(array $payload)
    {
        $existing = $this->get_transaction_by_sepay_id((string) ($payload['sepay_transaction_id'] ?? ''));
        if ($existing) {
            return ['created' => false, 'id' => (int) $existing['id'], 'existing' => $existing];
        }

        $record = [
            'sepay_transaction_id' => trim((string) ($payload['sepay_transaction_id'] ?? '')),
            'gateway'              => trim((string) ($payload['gateway'] ?? '')),
            'transaction_date'     => $payload['transaction_date'] ?? null,
            'account_number'       => trim((string) ($payload['account_number'] ?? '')),
            'code'                 => $this->nullableString($payload['code'] ?? null),
            'content'              => $this->nullableString($payload['content'] ?? null),
            'transfer_type'        => trim((string) ($payload['transfer_type'] ?? '')),
            'transfer_amount'      => (float) ($payload['transfer_amount'] ?? 0),
            'reference_code'       => $this->nullableString($payload['reference_code'] ?? null),
            'matched_reference'    => $this->nullableString($payload['matched_reference'] ?? null),
            'matched_type'         => $this->nullableString($payload['matched_type'] ?? null),
            'matched_id'           => !empty($payload['matched_id']) ? (int) $payload['matched_id'] : null,
            'payment_request_id'   => !empty($payload['payment_request_id']) ? (int) $payload['payment_request_id'] : null,
            'tenant_id'            => !empty($payload['tenant_id']) ? (int) $payload['tenant_id'] : null,
            'status'               => trim((string) ($payload['status'] ?? 'received')),
            'raw_payload'          => kt_sepay_json_encode($payload['raw_payload'] ?? []),
            'processed_at'         => $payload['processed_at'] ?? null,
            'created_at'           => kt_sepay_now(),
        ];

        $this->landlord_db()->insert(db_prefix() . 'kt_sepay_transactions', $record);
        $insertId = (int) $this->landlord_db()->insert_id();
        if ($insertId > 0) {
            return ['created' => true, 'id' => $insertId];
        }

        $existing = $this->get_transaction_by_sepay_id($record['sepay_transaction_id']);

        return ['created' => false, 'id' => (int) ($existing['id'] ?? 0), 'existing' => $existing];
    }

    public function get_transaction_by_sepay_id($transactionId)
    {
        return $this->landlord_db()
            ->where('sepay_transaction_id', trim((string) $transactionId))
            ->get(db_prefix() . 'kt_sepay_transactions')
            ->row_array();
    }

    public function update_transaction($id, array $payload)
    {
        $this->landlord_db()->where('id', (int) $id)->update(db_prefix() . 'kt_sepay_transactions', $payload);

        return $this->landlord_db()->affected_rows() >= 0;
    }

    public function get_transaction($id)
    {
        return $this->landlord_db()
            ->where('id', (int) $id)
            ->get(db_prefix() . 'kt_sepay_transactions')
            ->row_array();
    }

    public function get_transactions(array $filters = [])
    {
        $db = $this->landlord_db()
            ->select('*')
            ->from(db_prefix() . 'kt_sepay_transactions');

        if (!empty($filters['tenant_id'])) {
            $db->where('tenant_id', (int) $filters['tenant_id']);
        }
        if (!empty($filters['status'])) {
            $db->where('status', trim((string) $filters['status']));
        }
        if (!empty($filters['payment_request_id'])) {
            $db->where('payment_request_id', (int) $filters['payment_request_id']);
        }

        return $db->order_by('id', 'desc')->get()->result_array();
    }

    public function get_settings_profiles($onlyActive = false): array
    {
        $db = $this->landlord_db()
            ->select('*')
            ->from(db_prefix() . 'kt_sepay_settings');

        if ($onlyActive) {
            $db->where('is_active', 1);
        }

        $rows = $db->order_by('tenant_id', 'asc')->get()->result_array();
        foreach ($rows as &$row) {
            $row['api_token'] = kt_sepay_decrypt_value($row['api_token_encrypted'] ?? '');
            $row['webhook_secret'] = kt_sepay_decrypt_value($row['webhook_secret_encrypted'] ?? '');
        }
        unset($row);

        return $rows;
    }

    public function create_reconciliation_log(array $payload)
    {
        $record = array_merge([
            'tenant_id'       => null,
            'run_id'          => '',
            'environment'     => 'sandbox',
            'from_time'       => null,
            'to_time'         => null,
            'total_fetched'   => 0,
            'total_matched'   => 0,
            'total_processed' => 0,
            'total_errors'    => 0,
            'metadata_json'   => null,
            'created_at'      => kt_sepay_now(),
        ], $payload);

        $this->landlord_db()->insert(db_prefix() . 'kt_sepay_reconciliation_logs', $record);

        return (int) $this->landlord_db()->insert_id();
    }

    public function get_reconciliation_logs($limit = 100, $tenantId = null)
    {
        $db = $this->landlord_db()
            ->from(db_prefix() . 'kt_sepay_reconciliation_logs');

        if ($tenantId === null) {
            $db->where('tenant_id IS NULL', null, false);
        } else {
            $db->where('tenant_id', (int) $tenantId);
        }

        return $db->order_by('id', 'desc')
            ->limit((int) $limit)
            ->get()
            ->result_array();
    }

    public function create_health_log(array $payload)
    {
        $record = array_merge([
            'tenant_id'     => null,
            'test_type'    => 'unknown',
            'environment'  => 'sandbox',
            'status'       => 'error',
            'http_code'    => 0,
            'latency_ms'   => 0,
            'message'      => '',
            'error_code'   => null,
            'raw_response' => null,
            'created_by'   => get_staff_user_id() ?: null,
            'created_at'   => kt_sepay_now(),
        ], $payload);

        $this->landlord_db()->insert(db_prefix() . 'kt_sepay_health_logs', $record);

        return (int) $this->landlord_db()->insert_id();
    }

    public function get_health_logs($limit = 50, $tenantId = null)
    {
        if (!$this->landlord_db()->table_exists(db_prefix() . 'kt_sepay_health_logs')) {
            return [];
        }

        $db = $this->landlord_db()
            ->from(db_prefix() . 'kt_sepay_health_logs');

        if ($tenantId === null) {
            $db->where('tenant_id IS NULL', null, false);
        } else {
            $db->where('tenant_id', (int) $tenantId);
        }

        return $db->order_by('id', 'desc')
            ->limit((int) $limit)
            ->get()
            ->result_array();
    }

    public function get_summary($tenantId = null)
    {
        $db = $this->landlord_db();
        $requestTable = db_prefix() . 'kt_sepay_payment_requests';
        $transactionTable = db_prefix() . 'kt_sepay_transactions';
        $webhookTable = db_prefix() . 'kt_sepay_webhook_logs';

        return [
            'pending_requests'    => (int) $this->count_summary_rows($requestTable, 'pending', $tenantId),
            'paid_requests'       => (int) $this->count_summary_rows($requestTable, 'paid', $tenantId),
            'unmatched_txs'       => (int) $this->count_summary_rows($transactionTable, 'unmatched', $tenantId),
            'error_txs'           => (int) $this->count_summary_rows($transactionTable, 'error', $tenantId),
            'webhook_logs'        => $tenantId === null ? (int) $db->count_all_results($webhookTable) : 0,
            'recent_transactions' => $this->get_transactions($tenantId === null ? [] : ['tenant_id' => (int) $tenantId]),
        ];
    }

    public function get_perfex_invoice($invoiceId)
    {
        return $this->landlord_db()
            ->where('id', (int) $invoiceId)
            ->get(db_prefix() . 'invoices')
            ->row_array();
    }

    public function get_kt_saas_invoice($invoiceId)
    {
        if (!$this->db->table_exists(db_prefix() . 'kt_saas_invoices')) {
            return null;
        }

        return $this->landlord_db()
            ->where('id', (int) $invoiceId)
            ->get(db_prefix() . 'kt_saas_invoices')
            ->row_array();
    }

    public function get_kt_saas_subscription($subscriptionId)
    {
        if (!$this->db->table_exists(db_prefix() . 'kt_saas_subscriptions')) {
            return null;
        }

        return $this->landlord_db()
            ->where('id', (int) $subscriptionId)
            ->get(db_prefix() . 'kt_saas_subscriptions')
            ->row_array();
    }

    public function get_kt_saas_tenant($tenantId)
    {
        if (!$this->db->table_exists(db_prefix() . 'kt_saas_tenants')) {
            return null;
        }

        return $this->landlord_db()
            ->where('id', (int) $tenantId)
            ->get(db_prefix() . 'kt_saas_tenants')
            ->row_array();
    }

    public function get_kt_saas_invoice_by_subscription($subscriptionId)
    {
        if (!$this->db->table_exists(db_prefix() . 'kt_saas_invoices')) {
            return null;
        }

        return $this->landlord_db()
            ->where('subscription_id', (int) $subscriptionId)
            ->where_in('status', ['issued', 'pending_payment', 'partial', 'overdue'])
            ->order_by('id', 'desc')
            ->get(db_prefix() . 'kt_saas_invoices')
            ->row_array();
    }

    private function normalizeReferencePrefix($value, $default)
    {
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/', '', trim((string) $value)));

        return $normalized !== '' ? substr($normalized, 0, 24) : $default;
    }

    private function mergeEncryptedValue(array $existing, $key, $plain)
    {
        if ($plain === null) {
            return $existing[$key] ?? null;
        }

        $plain = trim((string) $plain);
        if ($plain === '') {
            return $existing[$key] ?? null;
        }

        return kt_sepay_encrypt_value($plain);
    }

    private function nullableString($value)
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function landlord_db()
    {
        $landlordDb = $this->config->item('kt_saas_landlord_db');

        return $landlordDb ?: $this->db;
    }

    private function find_settings_row($tenantId)
    {
        $db = $this->landlord_db()->from(db_prefix() . 'kt_sepay_settings');
        if ($tenantId === null) {
            $db->where('tenant_id IS NULL', null, false);
        } else {
            $db->where('tenant_id', (int) $tenantId);
        }

        return $db->limit(1)->get()->row_array();
    }

    private function normalizeTenantId($tenantId, $fallbackGlobal = true)
    {
        if ($tenantId === null && $fallbackGlobal && function_exists('kt_saas_current_tenant') && function_exists('kt_saas_is_tenant_runtime') && kt_saas_is_tenant_runtime()) {
            $tenant = kt_saas_current_tenant();
            $tenantId = (int) ($tenant['id'] ?? 0);
        }

        $tenantId = (int) $tenantId;

        return $tenantId > 0 ? $tenantId : null;
    }

    private function count_summary_rows($table, $status, $tenantId)
    {
        $db = $this->landlord_db()
            ->from($table)
            ->where('status', $status);

        if ($tenantId !== null) {
            $db->where('tenant_id', (int) $tenantId);
        }

        return (int) $db->count_all_results();
    }
}
