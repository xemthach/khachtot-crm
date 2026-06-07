<?php

defined('BASEPATH') or exit('No direct script access allowed');

class TenantAdminAccessService
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model(KT_SAAS_MODULE . '/Kt_saas_model');
        $this->CI->load->helper(KT_SAAS_MODULE . '/kt_saas');
        $this->CI->load->library('encryption');
    }

    public function getTenantAdminAccessProfile(array $tenant)
    {
        $db = $this->connectTenantDatabase($tenant);
        if (!$db) {
            return ['success' => false, 'message' => 'Unable to connect tenant database.'];
        }

        $staff = $this->resolveTenantAdminStaff($db, $tenant);
        if (!$staff) {
            return ['success' => false, 'message' => 'Tenant admin staff account was not found.'];
        }

        $staffProfile = $staff;
        $staffProfile['new_pass_key'] = !empty($staff['new_pass_key']) ? '[redacted]' : null;

        return [
            'success' => true,
            'staff'   => $staffProfile,
            'login_url' => $this->tenantAdminLoginUrl($tenant),
            'onboarding_link_generated' => !empty($staff['new_pass_key']),
        ];
    }

    public function regenerateOnboarding(array $tenant)
    {
        $db = $this->connectTenantDatabase($tenant);
        if (!$db) {
            return ['success' => false, 'message' => 'Unable to connect tenant database.'];
        }

        $staff = $this->resolveTenantAdminStaff($db, $tenant);
        if (!$staff) {
            return ['success' => false, 'message' => 'Tenant admin staff account was not found.'];
        }

        $key = app_generate_hash();
        $requestedAt = date('Y-m-d H:i:s');
        $db->where('staffid', (int) $staff['staffid']);
        $updated = $db->update(db_prefix() . 'staff', [
            'new_pass_key'           => $key,
            'new_pass_key_requested' => $requestedAt,
        ]);
        if (!$updated) {
            return ['success' => false, 'message' => 'Unable to generate tenant onboarding link.'];
        }

        $staff['new_pass_key'] = $key;
        $staff['new_pass_key_requested'] = $requestedAt;
        $manifestSaved = $this->updateManifestOnboarding($tenant, $staff);
        $setPasswordUrl = $this->tenantSetPasswordUrl($tenant, (int) $staff['staffid'], $key);
        $loginUrl = $this->tenantAdminLoginUrl($tenant);
        $emailDispatch = $this->sendOnboardingEmail($tenant, [
            'staff' => $staff,
            'set_password_url' => $setPasswordUrl,
            'admin_login_url' => $loginUrl,
            'dedupe_key' => 'tenant_onboarding_resend|' . (int) $tenant['id'] . '|' . $requestedAt,
        ]);

        $this->CI->Kt_saas_model->log_activity('tenant_admin.onboarding_resent', 'info', [
            'tenant_id'      => (int) $tenant['id'],
            'tenant_code'    => $tenant['tenant_code'],
            'staff_id'       => (int) $staff['staffid'],
            'staff_email'    => $staff['email'],
            'manifest_saved' => $manifestSaved,
            'onboarding_link_generated' => true,
            'email_status' => !empty($emailDispatch['success']) ? 'sent' : 'failed',
            'email_message_id' => (string) ($emailDispatch['message_id'] ?? ''),
        ], (int) $tenant['id']);

        $staffProfile = $staff;
        $staffProfile['new_pass_key'] = '[redacted]';

        return [
            'success'          => true,
            'staff'            => $staffProfile,
            'login_url'        => $loginUrl,
            'onboarding_link_generated' => true,
            'manifest_saved'   => $manifestSaved,
            'email_dispatch'   => $emailDispatch,
        ];
    }

    public function setManualPassword(array $tenant, $password)
    {
        $password = (string) $password;
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Manual password must be at least 8 characters.'];
        }

        $db = $this->connectTenantDatabase($tenant);
        if (!$db) {
            return ['success' => false, 'message' => 'Unable to connect tenant database.'];
        }

        $staff = $this->resolveTenantAdminStaff($db, $tenant);
        if (!$staff) {
            return ['success' => false, 'message' => 'Tenant admin staff account was not found.'];
        }

        $db->where('staffid', (int) $staff['staffid']);
        $db->update(db_prefix() . 'staff', [
            'password'             => app_hash_password($password),
            'new_pass_key'         => null,
            'new_pass_key_requested' => null,
            'last_password_change' => date('Y-m-d H:i:s'),
        ]);

        $staff['new_pass_key'] = null;
        $staff['new_pass_key_requested'] = null;
        $manifestSaved = $this->updateManifestOnboarding($tenant, $staff);

        $this->CI->Kt_saas_model->log_activity('tenant_admin.password_set_manually', 'warning', [
            'tenant_id'      => (int) $tenant['id'],
            'tenant_code'    => $tenant['tenant_code'],
            'staff_id'       => (int) $staff['staffid'],
            'staff_email'    => $staff['email'],
            'manifest_saved' => $manifestSaved,
        ], (int) $tenant['id']);

        return [
            'success'        => true,
            'staff'          => $staff,
            'login_url'      => $this->tenantAdminLoginUrl($tenant),
            'manifest_saved' => $manifestSaved,
        ];
    }

    protected function connectTenantDatabase(array $tenant)
    {
        $dbName = trim((string) ($tenant['db_name'] ?? ''));
        $dbHost = trim((string) ($tenant['db_host'] ?? ''));
        $dbUser = trim((string) ($tenant['db_user'] ?? ''));
        if ($dbName === '' || $dbHost === '' || $dbUser === '') {
            return null;
        }

        $password = '';
        if (!empty($tenant['db_password_encrypted'])) {
            $password = $this->CI->encryption->decrypt($tenant['db_password_encrypted']);
        }
        $db = $this->tryConnectTenantDatabase($dbHost, $dbUser, (string) $password, $dbName, (string) ($tenant['db_port'] ?? '3306'));
        if ($db) {
            return $db;
        }

        // Fallback to shared app credentials if tenant credentials drifted.
        $sharedUser = defined('APP_DB_USERNAME') ? trim((string) APP_DB_USERNAME) : '';
        $sharedPass = defined('APP_DB_PASSWORD') ? (string) APP_DB_PASSWORD : '';
        if ($sharedUser === '') {
            return null;
        }

        $fallbackDb = $this->tryConnectTenantDatabase($dbHost, $sharedUser, $sharedPass, $dbName, (string) ($tenant['db_port'] ?? '3306'));
        if (!$fallbackDb) {
            return null;
        }

        // Persist repaired credentials so future tenant access/provision tools remain stable.
        $tenantId = (int) ($tenant['id'] ?? 0);
        if ($tenantId > 0) {
            $this->CI->db->where('id', $tenantId)->update(db_prefix() . 'kt_saas_tenants', [
                'db_user' => $sharedUser,
                'db_password_encrypted' => $this->CI->encryption->encrypt($sharedPass),
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => get_staff_user_id() ?: null,
            ]);
            $this->CI->Kt_saas_model->log_activity('tenant_access.db_credentials_fallback', 'warning', [
                'tenant_id' => $tenantId,
                'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
                'fallback_db_user' => $sharedUser,
            ], $tenantId);
        }

        return $fallbackDb;
    }

    protected function tryConnectTenantDatabase($host, $user, $password, $database, $port)
    {
        $config = [
            'dsn'          => '',
            'hostname'     => $host,
            'username'     => $user,
            'password'     => $password,
            'database'     => $database,
            'dbdriver'     => defined('APP_DB_DRIVER') ? APP_DB_DRIVER : 'mysqli',
            'dbprefix'     => db_prefix(),
            'pconnect'     => false,
            'db_debug'     => false,
            'cache_on'     => false,
            'cachedir'     => '',
            'char_set'     => defined('APP_DB_CHARSET') ? APP_DB_CHARSET : 'utf8mb4',
            'dbcollat'     => defined('APP_DB_COLLATION') ? APP_DB_COLLATION : 'utf8mb4_unicode_ci',
            'swap_pre'     => '',
            'encrypt'      => false,
            'compress'     => false,
            'stricton'     => false,
            'failover'     => [],
            'save_queries' => true,
            'port'         => $port,
        ];

        try {
            $db = $this->CI->load->database($config, true);
            $db->initialize();
            if (!$db->table_exists(db_prefix() . 'staff')) {
                return null;
            }
            return $db;
        } catch (Throwable $e) {
            return null;
        }
    }

    protected function resolveTenantAdminStaff($db, array $tenant)
    {
        $manifest = kt_saas_tenant_manifest($tenant);
        $manifestStaffId = (int) (($manifest['onboarding']['staff_id'] ?? 0));
        if ($manifestStaffId > 0) {
            $row = $db->where('staffid', $manifestStaffId)->get(db_prefix() . 'staff')->row_array();
            if ($row) {
                return $row;
            }
        }

        $ownerEmail = trim((string) ($tenant['owner_email'] ?? ''));
        if ($ownerEmail !== '') {
            $row = $db->where('email', $ownerEmail)->get(db_prefix() . 'staff')->row_array();
            if ($row) {
                return $row;
            }
        }

        return $db->order_by('admin', 'desc')->order_by('staffid', 'asc')->get(db_prefix() . 'staff')->row_array();
    }

    protected function tenantAdminLoginUrl(array $tenant)
    {
        $url = rtrim($this->tenantBaseUrl($tenant), '/') . '/' . trim(get_admin_uri(), '/') . '/authentication';

        return kt_saas_url_with_tenant_host($url, $tenant);
    }

    protected function sendOnboardingEmail(array $tenant, array $profile)
    {
        $staff = isset($profile['staff']) && is_array($profile['staff']) ? $profile['staff'] : [];
        $plan = !empty($tenant['plan_id']) ? $this->CI->Kt_saas_model->get_plan((int) $tenant['plan_id']) : [];
        $workspaceUrl = rtrim($this->tenantBaseUrl($tenant), '/');
        $recipient = trim((string) ($staff['email'] ?? ($tenant['owner_email'] ?? '')));

        if ($recipient === '') {
            return ['status' => 'failed', 'error_message' => 'Missing onboarding recipient email.'];
        }

        return $this->CI->Kt_saas_model->send_email_event('tenant_welcome', [
            'tenant_id' => (int) $tenant['id'],
            'tenant' => $tenant,
            'plan' => is_array($plan) ? $plan : [],
            'recipient_email' => $recipient,
            'owner_name' => (string) ($tenant['owner_name'] ?? ''),
            'owner_email' => (string) ($tenant['owner_email'] ?? $recipient),
            'tenant_name' => (string) ($tenant['company_name'] ?? ''),
            'workspace_name' => (string) ($tenant['company_name'] ?? ($tenant['tenant_name'] ?? '')),
            'workspace_url' => $workspaceUrl,
            'workspace_domain' => trim((string) ($tenant['custom_domain'] ?? $tenant['subdomain'] ?? '')),
            'plan_name' => (string) ($plan['plan_name'] ?? ''),
            'admin_login_url' => (string) ($profile['admin_login_url'] ?? ''),
            'set_password_url' => (string) ($profile['set_password_url'] ?? ''),
            'support_email' => $this->supportEmail(),
            'password_link_expires_in' => '48 giờ',
            'related_type' => 'tenant',
            'related_id' => (string) $tenant['id'],
            'dedupe_key' => (string) ($profile['dedupe_key'] ?? ''),
        ]);
    }

    protected function tenantSetPasswordUrl(array $tenant, $staffId, $setPasswordKey)
    {
        if ($setPasswordKey === '') {
            return '';
        }

        $url = rtrim($this->tenantBaseUrl($tenant), '/') . '/' . trim(get_admin_uri(), '/') . '/authentication/set_password/1/' . (int) $staffId . '/' . $setPasswordKey;

        return kt_saas_url_with_tenant_host($url, $tenant);
    }

    protected function tenantBaseUrl(array $tenant)
    {
        $scheme = parse_url(APP_BASE_URL, PHP_URL_SCHEME) ?: 'https';
        $host = trim((string) ($tenant['custom_domain'] ?? ''));

        if ($host === '') {
            $subdomain = trim((string) ($tenant['subdomain'] ?? ''));
            $baseDomain = trim((string) kt_saas_get_option('kt_saas_base_domain', 'crm.local'));
            if ($subdomain !== '' && strpos($subdomain, '.') === false && $baseDomain !== '') {
                $host = $subdomain . '.' . $baseDomain;
            } else {
                $host = $subdomain;
            }
        }

        return $scheme . '://' . $host;
    }

    protected function updateManifestOnboarding(array $tenant, array $staff)
    {
        $manifest = kt_saas_tenant_manifest($tenant);
        if (!is_array($manifest)) {
            return false;
        }

        $manifest['onboarding'] = [
            'admin_email'       => (string) ($staff['email'] ?? ($tenant['owner_email'] ?? '')),
            'staff_id'          => (int) ($staff['staffid'] ?? 0),
            'admin_login_url'   => $this->tenantAdminLoginUrl($tenant),
            'onboarding_link_generated' => !empty($staff['new_pass_key']),
            'token_requested_at'=> (string) ($staff['new_pass_key_requested'] ?? ''),
        ];

        $path = kt_saas_tenant_manifest_path($tenant);
        if (!$path) {
            return false;
        }

        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return false;
        }

        return (bool) @file_put_contents($path, $json);
    }

    protected function supportEmail()
    {
        foreach (['smtp_email', 'email_from_address', 'companyemail'] as $option) {
            $value = trim((string) get_option($option));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
