<?php

defined('BASEPATH') or exit('No direct script access allowed');

final class MisaAuditService
{
    public function record(array $scope, $action, array $details = [])
    {
        if ((int) ($scope['tenant_id'] ?? 0) <= 0 || (int) ($scope['company_id'] ?? 0) <= 0) throw new InvalidArgumentException('MISA audit scope is required.');
        return ['tenant_id' => (int) $scope['tenant_id'], 'company_id' => (int) $scope['company_id'], 'action' => (string) $action, 'details_redacted' => kt_misa_redact($details), 'created_at' => date('Y-m-d H:i:s')];
    }
}

