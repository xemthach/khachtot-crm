<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/MisaScope.php';
require_once __DIR__ . '/MisaSecretVault.php';

final class MisaConnectionService
{
    private $vault;

    public function __construct(MisaSecretVault $vault)
    {
        $this->vault = $vault;
    }

    public function validateInput(MisaScope $scope, array $input)
    {
        $errors = [];
        foreach (['name', 'environment', 'app_id', 'org_company_code'] as $field) {
            if (trim((string) ($input[$field] ?? '')) === '') $errors[$field] = $field . ' is required.';
        }
        foreach (['name', 'app_id', 'org_company_code'] as $field) {
            if (mb_strlen(trim((string) ($input[$field] ?? ''))) > 191) $errors[$field] = $field . ' exceeds 191 characters.';
        }
        if (trim((string) ($input['access_code'] ?? '')) === '' && empty($input['has_existing_access_code'])) $errors['access_code'] = 'access_code is required for a new connection.';
        if (!in_array((string) ($input['environment'] ?? ''), ['mock', 'test', 'production'], true)) $errors['environment'] = 'Unsupported environment.';
        if ((string) ($input['environment'] ?? '') === 'production') $errors['environment'] = 'Production connections are blocked in this milestone.';
        return $errors;
    }

    public function toStorage(MisaScope $scope, array $input, array $existing = [])
    {
        $errors = $this->validateInput($scope, $input + ['has_existing_access_code' => !empty($existing['access_code_encrypted'])]);
        if ($errors) throw new InvalidArgumentException(implode(' ', $errors));
        $now = date('Y-m-d H:i:s');
        $data = [
            'tenant_id' => $scope->tenantId(), 'company_id' => $scope->companyId(),
            'name' => trim((string) $input['name']), 'environment' => trim((string) $input['environment']),
            'app_id' => trim((string) $input['app_id']), 'org_company_code' => trim((string) $input['org_company_code']),
            'status' => !empty($input['enabled']) ? 'enabled' : 'disabled',
            'certification_level' => (string) ($existing['certification_level'] ?? 'NOT_IMPLEMENTED'),
            'updated_at' => $now,
        ];
        if (trim((string) ($input['access_code'] ?? '')) !== '') $data['access_code_encrypted'] = $this->vault->encrypt($input['access_code']);
        if (!$existing) { $data['created_at'] = $now; $data['created_by'] = (int) ($input['actor_id'] ?? 0) ?: null; }
        return $data;
    }
}
