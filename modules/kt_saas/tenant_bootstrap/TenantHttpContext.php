<?php

defined('BASEPATH') or exit('No direct script access allowed');

final class TenantHttpContext
{
    private $tenant;
    private $companyId;
    private $database;
    private $host;
    private $resolvedAt;

    public function __construct(array $tenant, $database, $host, $companyId = 0)
    {
        $this->tenant = $tenant;
        $this->database = (string) $database;
        $this->host = (string) $host;
        $this->companyId = (int) $companyId;
        $this->resolvedAt = date('c');
    }

    public function tenant() { return $this->tenant; }
    public function tenantId() { return (int) ($this->tenant['id'] ?? 0); }
    public function tenantCode() { return (string) ($this->tenant['tenant_code'] ?? ''); }
    public function companyId() { return $this->companyId; }
    public function database() { return $this->database; }
    public function host() { return $this->host; }
    public function runtimeType() { return 'tenant'; }
    public function resolvedAt() { return $this->resolvedAt; }
    public function asArray() { return ['tenant_id' => $this->tenantId(), 'tenant_code' => $this->tenantCode(), 'company_id' => $this->companyId(), 'database_name' => $this->database(), 'runtime_type' => $this->runtimeType(), 'request_host' => $this->host(), 'resolved_at' => $this->resolvedAt()]; }
}
