<?php

defined('BASEPATH') or exit('No direct script access allowed');

class StaffPayloadProducer
{
    public function buildPayload(array $tenant, array $staffRow, ?array $roleRow = null, array $dmsAccess = []): array
    {
        require_once __DIR__ . '/CanonicalIntegrationIdentity.php';
        $crmStaffId = (int) ($staffRow['staffid'] ?? 0);
        if ($crmStaffId <= 0) {
            throw new RuntimeException('Staff payload producer requires a valid staff id.');
        }

        $accessEnabled = array_key_exists('dms_access_enabled', $dmsAccess)
            ? (bool) $dmsAccess['dms_access_enabled']
            : ((int) ($staffRow['active'] ?? 0) === 1);
        $roleCode = trim((string) ($dmsAccess['dms_role_code'] ?? ''));
        if ($roleCode === '') {
            $roleCode = $this->resolveRoleCode($staffRow, $roleRow);
        }
        $updatedAt = $this->resolveUpdatedAt($staffRow, $dmsAccess);

        $crmTenantId = (int) ($tenant['id'] ?? $tenant['crm_tenant_id'] ?? 0);
        $canonicalCompanyId = (int) ($tenant['canonical_crm_company_id']
            ?? $tenant['crm_company_id']
            ?? $tenant['landlord_client_id']
            ?? 0);
        if ($crmTenantId <= 0 || $canonicalCompanyId <= 0) {
            throw new RuntimeException('Staff payload producer requires canonical tenant and company identities.');
        }

        $email = strtolower(trim((string) ($staffRow['email'] ?? '')));
        $payload = [
            'tenant' => [
                'crm_tenant_id' => $crmTenantId,
                'crm_company_id' => $canonicalCompanyId,
                'canonical_crm_company_id' => $canonicalCompanyId,
                'identity_trust_level' => 'canonical',
                'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
            ],
            'staff' => [
                'crm_staff_id' => $crmStaffId,
                'email' => $email,
                'first_name' => trim((string) ($staffRow['firstname'] ?? '')),
                'last_name' => trim((string) ($staffRow['lastname'] ?? '')),
                'phone' => trim((string) ($staffRow['phonenumber'] ?? '')),
                'status' => $accessEnabled && (int) ($staffRow['active'] ?? 0) === 1 ? 'active' : 'inactive',
                'dms_access_enabled' => $accessEnabled,
                'role_code' => $roleCode,
                'manager_crm_staff_id' => !empty($dmsAccess['manager_crm_staff_id'])
                    ? (int) $dmsAccess['manager_crm_staff_id']
                    : null,
                'default_language' => trim((string) ($staffRow['default_language'] ?? '')),
                'updated_at' => $updatedAt,
            ],
            'identity' => CanonicalIntegrationIdentity::build($tenant, 'staff', $crmStaffId, $email, [
                'email' => $email,
                'datecreated' => (string) ($staffRow['datecreated'] ?? ''),
            ], $updatedAt, $accessEnabled && (int) ($staffRow['active'] ?? 0) === 1 ? 'active' : 'deleted'),
        ];

        $workspaceId = (int) ($dmsAccess['workspace_id'] ?? 0);
        if ($workspaceId > 0) {
            $payload['tenant']['workspace_id'] = $workspaceId;
        }

        return $payload;
    }

    public function buildIdempotencyKey(array $payload): string
    {
        $staff = (array) ($payload['staff'] ?? []);
        $crmStaffId = (int) ($staff['crm_staff_id'] ?? 0);
        $updatedAt = trim((string) ($staff['updated_at'] ?? ''));
        if ($crmStaffId <= 0 || $updatedAt === '') {
            throw new RuntimeException('Cannot build idempotency key without crm_staff_id and updated_at.');
        }

        return 'staff-v3-' . substr(hash('sha256', (string) ($payload['identity']['source_stable_key'] ?? '') . '|' . $updatedAt . '|' . kt_integration_hub_json_encode($payload)), 0, 48);
    }

    public function buildRequestId(array $payload): string
    {
        $staff = (array) ($payload['staff'] ?? []);

        return 'staff_sync_' . (int) ($staff['crm_staff_id'] ?? 0) . '_' . bin2hex(random_bytes(6));
    }

    private function resolveRoleCode(array $staffRow, ?array $roleRow): string
    {
        if ((int) ($staffRow['admin'] ?? 0) === 1) {
            return 'admin';
        }

        $roleName = trim((string) ($roleRow['name'] ?? ''));
        if ($roleName !== '') {
            $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $roleName));

            return trim($normalized, '_') ?: 'field_sales';
        }

        return 'field_sales';
    }

    private function resolveUpdatedAt(array $staffRow, array $dmsAccess = []): string
    {
        $candidates = [
            (string) ($dmsAccess['updated_at'] ?? ''),
            (string) ($dmsAccess['last_synced_at'] ?? ''),
            (string) ($staffRow['last_password_change'] ?? ''),
            (string) ($staffRow['last_activity'] ?? ''),
            (string) ($staffRow['last_login'] ?? ''),
            (string) ($staffRow['datecreated'] ?? ''),
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            $timestamp = strtotime($candidate);
            if ($timestamp !== false) {
                return gmdate('c', $timestamp);
            }
        }

        return gmdate('c');
    }
}
