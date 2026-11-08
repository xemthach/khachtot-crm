<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/TenantResolver.php';
require_once __DIR__ . '/DatabaseSwitcher.php';
require_once __DIR__ . '/TenantHttpContext.php';

final class TenantHttpDatabaseLifecycle
{
    private $CI;
    private $originalDb;
    private $originalGlobals;
    private $originalConfig;
    private $switched = false;
    private $restored = false;

    public function __construct($CI = null)
    {
        $this->CI = $CI ?: get_instance();
    }

    public function start($host, $companyId = 0)
    {
        $host = TenantResolver::normalizeHost($host);
        if ($host === null) return ['landlord' => false, 'switched' => false, 'reason' => 'invalid_host', 'http_status' => 404];

        $this->captureOriginalState();
        $resolver = new TenantResolver();
        $tenant = $resolver->resolveByHost($host);
        if (empty($tenant['resolved'])) {
            if (($tenant['reason'] ?? '') === 'landlord_host') return ['landlord' => true, 'switched' => false, 'reason' => 'landlord_host'];
            return ['landlord' => false, 'switched' => false, 'reason' => $tenant['reason'] ?? 'tenant_not_found', 'http_status' => 404];
        }

        $status = strtolower((string) ($tenant['status'] ?? 'draft'));
        $provisioning = strtolower((string) ($tenant['provisioning_status'] ?? 'queued'));
        if (!in_array($status, kt_saas_tenant_runtime_statuses(), true) || $provisioning !== 'done') {
            return ['landlord' => false, 'switched' => false, 'tenant' => $tenant, 'reason' => 'tenant_disabled', 'http_status' => 403];
        }

        $switch = (new DatabaseSwitcher())->switchConnection($tenant);
        if (empty($switch['switched'])) {
            $this->restore();
            return ['landlord' => false, 'switched' => false, 'tenant' => $tenant, 'reason' => 'tenant_database_unavailable', 'http_status' => 503];
        }
        $this->switched = true;
        $expected = trim((string) ($tenant['db_name'] ?? ''));
        $actual = trim((string) ($this->CI->db->database ?? ''));
        if ($expected === '' || $actual === '' || strcasecmp($expected, $actual) !== 0 || !$this->CI->db->table_exists(db_prefix() . 'options')) {
            $this->restore();
            return ['landlord' => false, 'switched' => false, 'tenant' => $tenant, 'reason' => 'tenant_database_verification_failed', 'http_status' => 503];
        }

        $trustedCompanyId = (int) ($tenant['crm_company_id'] ?? $tenant['landlord_client_id'] ?? 0);
        if ($trustedCompanyId <= 0 || ((int) $companyId > 0 && (int) $companyId !== $trustedCompanyId)) {
            $this->restore();
            return ['landlord' => false, 'switched' => false, 'tenant' => $tenant, 'reason' => 'company_not_owned', 'http_status' => 403];
        }
        $context = new TenantHttpContext($tenant, $actual, $host, $trustedCompanyId);
        $this->publish($context);
        $this->registerShutdownRestore();
        return ['landlord' => false, 'switched' => true, 'tenant' => $tenant, 'context' => $context, 'database' => $actual];
    }

    public function restore()
    {
        if ($this->restored) return true;
        $this->restored = true;
        if ($this->originalDb) $this->CI->db = $this->originalDb;
        foreach ($this->originalGlobals as $key => $value) {
            if ($value === null) unset($GLOBALS[$key]); else $GLOBALS[$key] = $value;
        }
        foreach ($this->originalConfig as $key => $value) $this->CI->config->set_item($key, $value);
        return true;
    }

    private function captureOriginalState()
    {
        $this->originalDb = $this->CI->db;
        foreach (['kt_saas_current_tenant','kt_saas_current_profile','kt_saas_auth_context','kt_saas_runtime_branding','kt_saas_runtime_localization','kt_saas_cache_namespace','kt_saas_http_tenant_context'] as $key) $this->originalGlobals[$key] = $GLOBALS[$key] ?? null;
        foreach (['kt_saas_current_tenant','kt_saas_current_profile','kt_saas_auth_context','kt_saas_runtime_branding','kt_saas_runtime_localization','kt_saas_cache_namespace','kt_saas_is_tenant_request','kt_saas_http_tenant_context','kt_saas_landlord_db'] as $key) $this->originalConfig[$key] = $this->CI->config->item($key);
    }

    private function publish(TenantHttpContext $context)
    {
        $tenant = $context->tenant();
        $GLOBALS['kt_saas_current_tenant'] = $tenant;
        $GLOBALS['kt_saas_http_tenant_context'] = $context;
        $this->CI->config->set_item('kt_saas_current_tenant', $tenant);
        $this->CI->config->set_item('kt_saas_http_tenant_context', $context);
        $this->CI->config->set_item('kt_saas_is_tenant_request', true);
    }

    private function registerShutdownRestore()
    {
        $self = $this;
        register_shutdown_function(function () use ($self) { $self->restore(); });
    }
}
