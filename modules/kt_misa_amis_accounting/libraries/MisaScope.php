<?php

defined('BASEPATH') or exit('No direct script access allowed');

final class MisaScope
{
    private $tenantId;
    private $companyId;

    public function __construct($tenantId, $companyId)
    {
        $this->tenantId = (int) $tenantId;
        $this->companyId = (int) $companyId;
        if ($this->tenantId <= 0 || $this->companyId <= 0) {
            throw new InvalidArgumentException('MISA tenant_id and company_id are required.');
        }
    }

    public function tenantId() { return $this->tenantId; }
    public function companyId() { return $this->companyId; }
    public function asArray() { return ['tenant_id' => $this->tenantId, 'company_id' => $this->companyId]; }
    public function key() { return $this->tenantId . ':' . $this->companyId; }
}

