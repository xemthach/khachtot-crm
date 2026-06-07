<?php

defined('BASEPATH') or exit('No direct script access allowed');

class TenantContextService
{
    protected $tenant;
    protected $profile;

    public function setTenant(array $tenant)
    {
        $this->tenant = $tenant;
    }

    public function getTenant()
    {
        if ($this->tenant) {
            return $this->tenant;
        }

        return $GLOBALS['kt_saas_current_tenant'] ?? null;
    }

    public function setProfile(array $profile)
    {
        $this->profile = $profile;
    }

    public function getProfile()
    {
        if ($this->profile) {
            return $this->profile;
        }

        return $GLOBALS['kt_saas_current_profile'] ?? null;
    }

    public function getTenantId()
    {
        $tenant = $this->getTenant();

        return (int) ($tenant['id'] ?? 0);
    }

    public function getTenantCode()
    {
        $tenant = $this->getTenant();

        return (string) ($tenant['tenant_code'] ?? '');
    }

    public function getCacheNamespace()
    {
        $tenant = $this->getTenant();
        if (!$tenant) {
            return (string) ($GLOBALS['kt_saas_cache_namespace'] ?? 'landlord');
        }

        $tenantCode = strtolower(trim((string) ($tenant['tenant_code'] ?? 'tenant')));
        $tenantCode = preg_replace('/[^a-z0-9_\-]/', '_', $tenantCode) ?: 'tenant';

        return 'tenant:' . (int) ($tenant['id'] ?? 0) . ':' . $tenantCode;
    }

    public function isTenantRuntime()
    {
        return $this->getTenantId() > 0;
    }

    public function isRuntimeAccessible()
    {
        $tenant = $this->getTenant();
        if (!$tenant) {
            return false;
        }

        $status = (string) ($tenant['status'] ?? 'draft');
        $provisioningStatus = (string) ($tenant['provisioning_status'] ?? 'queued');

        return in_array($status, kt_saas_tenant_runtime_statuses(), true) && $provisioningStatus === 'done';
    }
}
