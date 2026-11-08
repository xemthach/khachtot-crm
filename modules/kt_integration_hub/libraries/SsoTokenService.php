<?php

defined('BASEPATH') or exit('No direct script access allowed');

class SsoTokenService
{
    private const DEFAULT_TTL_SECONDS = 300;

    public function issueToken(array $tenant, array $staffRow, string $signingSecret, ?int $ttlSeconds = null, ?string $issuedAtIso = null): array
    {
        $ttlSeconds = $ttlSeconds ?? self::DEFAULT_TTL_SECONDS;
        if ($ttlSeconds <= 0 || $ttlSeconds > self::DEFAULT_TTL_SECONDS) {
            throw new SsoTokenException('INVALID_TTL', 'SSO token TTL is invalid.', 422);
        }

        $tenantId = (int) ($tenant['id'] ?? $tenant['crm_tenant_id'] ?? 0);
        $tenantCode = strtoupper(trim((string) ($tenant['tenant_code'] ?? '')));
        $staffId = (int) ($staffRow['staffid'] ?? $staffRow['crm_staff_id'] ?? 0);
        $email = strtolower(trim((string) ($staffRow['email'] ?? '')));
        $status = strtolower(trim((string) ($staffRow['active'] ?? '')));

        if ($tenantId <= 0 || $tenantCode === '') {
            throw new SsoTokenException('TENANT_CONTEXT_REQUIRED', 'Tenant context is required.', 409);
        }

        if ($staffId <= 0 || $email === '') {
            throw new SsoTokenException('STAFF_CONTEXT_REQUIRED', 'Staff context is required.', 422);
        }

        if ((int) ($staffRow['active'] ?? 0) !== 1) {
            throw new SsoTokenException('STAFF_INACTIVE', 'Staff account is inactive.', 403);
        }

        if (in_array($status, ['0', 'inactive', 'suspended'], true)) {
            throw new SsoTokenException('STAFF_INACTIVE', 'Staff account is inactive.', 403);
        }

        if (in_array(strtolower((string) ($tenant['status'] ?? 'active')), ['inactive', 'suspended', 'terminated'], true)) {
            throw new SsoTokenException('TENANT_INACTIVE', 'Tenant is inactive.', 403);
        }

        $issuedAt = $issuedAtIso ? gmdate('c', strtotime($issuedAtIso) ?: time()) : gmdate('c');
        $issuedAtTs = strtotime($issuedAt) ?: time();
        $expiresAtTs = $issuedAtTs + $ttlSeconds;
        if ($expiresAtTs <= time()) {
            throw new SsoTokenException('TOKEN_EXPIRED', 'Issued SSO token would already be expired.', 422);
        }
        $expiresAt = gmdate('c', $expiresAtTs);

        $canonicalCompanyId = (int) ($tenant['canonical_crm_company_id'] ?? $tenant['crm_company_id'] ?? 0);
        if ($canonicalCompanyId <= 0) {
            throw new SsoTokenException('CANONICAL_COMPANY_REQUIRED', 'Canonical CRM company identity is required.', 409);
        }

        $payload = [
            'iss' => 'khachtot_crm',
            'aud' => 'salesflow_dms',
            'jti' => bin2hex(random_bytes(16)),
            'crm_staff_id' => $staffId,
            'crm_tenant_id' => $tenantId,
            'crm_company_id' => $canonicalCompanyId,
            'canonical_crm_company_id' => $canonicalCompanyId,
            'compatibility_crm_company_id' => (int) ($tenant['compatibility_crm_company_id'] ?? $tenantId),
            'identity_trust_level' => (string) ($tenant['identity_trust_level'] ?? 'canonical'),
            'tenant_code' => $tenantCode,
            'email' => $email,
            'name' => trim((string) (($staffRow['firstname'] ?? '') . ' ' . ($staffRow['lastname'] ?? ''))),
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
        ];
        $workspaceId = (int) ($tenant['workspace_id'] ?? 0);
        $workspaceSlug = trim((string) ($tenant['workspace_slug'] ?? ''));
        $workspaceHost = strtolower(trim((string) ($tenant['workspace_host'] ?? '')));
        if ($workspaceId > 0 && $workspaceSlug !== '' && $workspaceHost !== '') {
            $payload['workspace_id'] = $workspaceId;
            $payload['workspace_slug'] = $workspaceSlug;
            $payload['workspace_host'] = $workspaceHost;
        }

        $rawPayload = kt_integration_hub_json_encode($payload);
        $token = $this->encodeToken($rawPayload, $signingSecret);

        return [
            'token' => $token,
            'payload' => $payload,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'redirect_url' => $this->buildRedirectUrl($tenant, $token),
        ];
    }

    public function buildRedirectUrl(array $tenant, string $token): string
    {
        $baseUrl = $this->resolveBaseUrl($tenant);
        if ($baseUrl === '') {
            throw new SsoTokenException('DMS_BASE_URL_REQUIRED', 'DMS base URL is required.', 422);
        }

        return rtrim($baseUrl, '/') . '/sso/login?token=' . rawurlencode($token);
    }

    private function resolveBaseUrl(array $tenant): string
    {
        $selectedHost = strtolower(trim((string) ($tenant['workspace_host'] ?? $tenant['dms_workspace_host'] ?? '')));
        if ($selectedHost !== '' && preg_match('/^[a-z0-9.-]+$/', $selectedHost)) {
            return 'https://' . $selectedHost;
        }
        $settings = kt_integration_hub_json_decode((string) ($tenant['_bridge_connection_settings'] ?? ''), []);
        $connection = [
            'settings_json' => kt_integration_hub_json_encode($settings),
        ];

        $workspaceUrl = kt_integration_hub_dms_workspace_base_url($tenant, $connection);
        if ($workspaceUrl !== '') {
            return $workspaceUrl;
        }

        $candidates = [
            trim((string) ($settings['base_url'] ?? '')),
            trim((string) ($tenant['base_url'] ?? '')),
            trim((string) ($tenant['external_account_id'] ?? '')),
            trim((string) ($tenant['external_account_name'] ?? '')),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && preg_match('/^https?:\\/\\//i', $candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private function encodeToken(string $rawPayload, string $signingSecret): string
    {
        $encodedPayload = rtrim(strtr(base64_encode($rawPayload), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $rawPayload, trim($signingSecret));

        return $encodedPayload . '.' . $signature;
    }
}
