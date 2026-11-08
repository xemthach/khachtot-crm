<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/MisaScope.php';

final class MisaConnectionRepository
{
    private $db;
    private $table;

    public function __construct($db = null)
    {
        $this->db = $db ?: (function_exists('get_instance') ? get_instance()->db : null);
        if (!$this->db) throw new RuntimeException('Database runtime is required for MISA repository.');
        $this->table = db_prefix() . 'kt_misa_connections';
    }

    public function find(MisaScope $scope, $id)
    {
        return $this->db->where('id', (int) $id)->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->get($this->table)->row_array();
    }

    public function findCallbackCandidates($appId, $orgCompanyCode)
    {
        return $this->db->where('app_id', trim((string) $appId))->where('org_company_code', trim((string) $orgCompanyCode))->where('status !=', 'disabled')->get($this->table)->result_array();
    }
}

