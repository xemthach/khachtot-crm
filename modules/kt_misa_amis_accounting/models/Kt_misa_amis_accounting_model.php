<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_misa_amis_accounting_model extends App_Model
{
    private $tables;

    public function __construct()
    {
        parent::__construct();
        $p = db_prefix();
        $this->tables = [
            'connections' => $p . 'kt_misa_connections', 'requests' => $p . 'kt_misa_requests', 'items' => $p . 'kt_misa_request_items',
            'callbacks' => $p . 'kt_misa_callback_inbox', 'mappings' => $p . 'kt_misa_mappings', 'cursors' => $p . 'kt_misa_pull_cursors',
            'attempts' => $p . 'kt_misa_attempts', 'audits' => $p . 'kt_misa_audit_logs',
        ];
    }

    public function get_connection(MisaScope $scope, $id)
    {
        return $this->db->where('id', (int) $id)->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->get($this->tables['connections'])->row_array();
    }

    public function get_connections(MisaScope $scope)
    {
        return $this->db->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->order_by('id', 'DESC')->get($this->tables['connections'])->result_array();
    }

    public function save_connection(MisaScope $scope, array $data)
    {
        $id = (int) ($data['id'] ?? 0);
        if ($id > 0) {
            $this->db->where('id', $id)->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->update($this->tables['connections'], $data);
            return $this->db->affected_rows() >= 0;
        }
        return $this->db->insert($this->tables['connections'], $data);
    }

    public function create_request(MisaScope $scope, array $data)
    {
        $data['tenant_id'] = $scope->tenantId(); $data['company_id'] = $scope->companyId();
        $this->db->insert($this->tables['requests'], $data);
        return (int) $this->db->insert_id();
    }

    public function get_request(MisaScope $scope, $id)
    {
        return $this->db->where('id', (int) $id)->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->get($this->tables['requests'])->row_array();
    }

    public function get_requests(MisaScope $scope, $limit = 100)
    {
        return $this->db->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->order_by('id', 'DESC')->limit(min(500, max(1, (int) $limit)))->get($this->tables['requests'])->result_array();
    }

    public function get_mappings(MisaScope $scope, $limit = 100)
    {
        return $this->db->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->order_by('id', 'DESC')->limit(min(500, max(1, (int) $limit)))->get($this->tables['mappings'])->result_array();
    }

    public function get_mapping(MisaScope $scope, $id)
    {
        return $this->db->where('id', (int) $id)->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->get($this->tables['mappings'])->row_array();
    }

    public function save_mapping(MisaScope $scope, array $data)
    {
        $data['tenant_id'] = $scope->tenantId();
        $data['company_id'] = $scope->companyId();
        return $this->db->insert($this->tables['mappings'], $data) ? (int) $this->db->insert_id() : 0;
    }

    public function update_connection_health(MisaScope $scope, $id, array $data)
    {
        return $this->db->where('id', (int) $id)->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->update($this->tables['connections'], $data);
    }

    public function update_request_status(MisaScope $scope, $id, $from, $to, array $data = [])
    {
        $allowed = [
            'draft' => ['queued', 'cancelled'], 'queued' => ['dispatching', 'cancelled'],
            'retryable_failed' => ['queued', 'cancelled'], 'dispatching' => ['retryable_failed', 'accepted_by_misa'],
            'accepted_by_misa' => ['awaiting_callback', 'succeeded', 'partially_succeeded', 'failed'],
            'awaiting_callback' => ['succeeded', 'partially_succeeded', 'failed', 'cancelled'],
        ];
        if (!in_array($to, $allowed[$from] ?? [], true)) return false;
        $data['status'] = $to;
        $data['updated_at'] = date('Y-m-d H:i:s');
        $updated = $this->db->where('id', (int) $id)->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->where('status', $from)->update($this->tables['requests'], $data);
        return $updated && (int) $this->db->affected_rows() === 1;
    }

    public function store_callback(array $data)
    {
        if ((int) ($data['tenant_id'] ?? 0) <= 0 || (int) ($data['company_id'] ?? 0) <= 0) throw new InvalidArgumentException('Callback scope is required.');
        return $this->db->insert($this->tables['callbacks'], $data) ? (int) $this->db->insert_id() : 0;
    }

    public function find_callback_connections($appId, $orgCompanyCode)
    {
        return $this->db->where('app_id', trim((string) $appId))->where('org_company_code', trim((string) $orgCompanyCode))->where('status !=', 'disabled')->get($this->tables['connections'])->result_array();
    }

    public function get_summary(MisaScope $scope)
    {
        $base = $this->db->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId());
        return [
            'connections' => (int) $base->count_all_results($this->tables['connections']),
            'requests' => (int) $this->db->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->count_all_results($this->tables['requests']),
            'pending_callbacks' => (int) $this->db->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->where_in('processing_status', ['received', 'pending'])->count_all_results($this->tables['callbacks']),
            'mapping_conflicts' => (int) $this->db->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->where('mapping_status', 'conflict')->count_all_results($this->tables['mappings']),
            'queued_requests' => (int) $this->db->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->where_in('status', ['draft', 'queued', 'dispatching'])->count_all_results($this->tables['requests']),
            'failed_requests' => (int) $this->db->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->where_in('status', ['retryable_failed', 'permanent_failed'])->count_all_results($this->tables['requests']),
            'reconciliation_required' => (int) $this->db->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->where('status', 'reconciliation_required')->count_all_results($this->tables['requests']),
        ];
    }

    public function audit(MisaScope $scope, $action, array $details = [], $connectionId = null, $actorId = null)
    {
        return $this->db->insert($this->tables['audits'], [
            'tenant_id' => $scope->tenantId(), 'company_id' => $scope->companyId(), 'connection_id' => $connectionId ? (int) $connectionId : null,
            'actor_type' => $actorId ? 'staff' : 'system', 'actor_id' => $actorId ? (int) $actorId : null, 'action' => (string) $action, 'correlation_id' => (string) ($details['correlation_id'] ?? ''),
            'details_redacted_json' => kt_misa_json(kt_misa_redact($details)), 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
