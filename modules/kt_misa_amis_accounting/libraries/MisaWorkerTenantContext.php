<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once dirname(__DIR__) . '/../kt_saas/tenant_bootstrap/DatabaseSwitcher.php';

final class MisaWorkerTenantContext
{
    private $CI;
    private $landlordDb;
    private $originalTenant;
    private $originalIsTenant;
    private $entered = false;

    public function __construct($CI = null)
    {
        $this->CI = $CI ?: get_instance();
        $this->landlordDb = $this->CI->config->item('kt_saas_landlord_db') ?: $this->CI->db;
        $this->originalTenant = $GLOBALS['kt_saas_current_tenant'] ?? null;
        $this->originalIsTenant = $this->CI->config->item('kt_saas_is_tenant_request');
    }

    public function enter(array $tenant)
    {
        $id = (int) ($tenant['id'] ?? 0);
        $status = strtolower((string) ($tenant['status'] ?? ''));
        $provisioning = strtolower((string) ($tenant['provisioning_status'] ?? 'done'));
        if ($id <= 0 || !in_array($status, ['active', 'trial'], true) || !in_array($provisioning, ['done', 'completed'], true)) return ['switched' => false, 'reason' => 'tenant_not_active'];
        $this->CI->config->set_item('kt_saas_landlord_db', $this->landlordDb);
        $switch = (new DatabaseSwitcher())->switchConnection($tenant);
        if (empty($switch['switched'])) return ['switched' => false, 'reason' => 'tenant_database_switch_failed'];
        $expected = strtolower(trim((string) ($tenant['db_name'] ?? '')));
        $actual = strtolower(trim((string) ($this->CI->db->database ?? '')));
        if ($expected === '' || $expected !== $actual || !$this->CI->db->table_exists(db_prefix() . 'kt_misa_requests')) {
            $this->restore();
            return ['switched' => false, 'reason' => 'tenant_database_verification_failed'];
        }
        $companyId = (int) ($tenant['crm_company_id'] ?? $tenant['landlord_client_id'] ?? 0);
        if ($companyId <= 0) { $this->restore(); return ['switched' => false, 'reason' => 'company_not_owned']; }
        $GLOBALS['kt_saas_current_tenant'] = $tenant;
        $this->CI->config->set_item('kt_saas_current_tenant', $tenant);
        $this->CI->config->set_item('kt_saas_is_tenant_request', true);
        $this->entered = true;
        return ['switched' => true, 'tenant_id' => $id, 'company_id' => $companyId, 'database' => $actual];
    }

    public function restore()
    {
        $this->CI->db = $this->landlordDb;
        $this->CI->config->set_item('kt_saas_landlord_db', $this->landlordDb);
        $this->CI->config->set_item('kt_saas_is_tenant_request', $this->originalIsTenant);
        if ($this->originalTenant === null) unset($GLOBALS['kt_saas_current_tenant']); else $GLOBALS['kt_saas_current_tenant'] = $this->originalTenant;
        $this->CI->config->set_item('kt_saas_current_tenant', $this->originalTenant);
        $this->entered = false;
        return true;
    }
}
