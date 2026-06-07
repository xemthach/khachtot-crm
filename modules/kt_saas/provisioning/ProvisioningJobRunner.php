<?php

defined('BASEPATH') or exit('No direct script access allowed');

class ProvisioningJobRunner
{
    protected $CI;
    protected $landlordDb;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model(KT_SAAS_MODULE . '/Kt_saas_model');
        $this->landlordDb = $this->CI->config->item('kt_saas_landlord_db') ?: $this->CI->db;
    }

    public function execute(array $job)
    {
        $tenantId = (int) ($job['tenant_id'] ?? 0);
        $tenant = $tenantId ? $this->CI->Kt_saas_model->get_tenant($tenantId) : null;
        $originalTenant = $tenant;

        if (!$tenant) {
            return [
                'success' => false,
                'message' => 'Tenant not found for provisioning job.',
                'steps'   => [],
            ];
        }

        $plan = !empty($tenant['plan_id']) ? $this->CI->Kt_saas_model->get_plan((int) $tenant['plan_id']) : null;
        $steps = [];

        $steps[] = $this->step('validate_tenant', true, 'Tenant metadata validated.');
        $steps[] = $this->step('ensure_plan', $plan !== null, $plan ? 'Plan resolved.' : 'Tenant has no plan assigned.');
        if (!$plan) {
            return [
                'success' => false,
                'message' => 'Provisioning requires an assigned plan.',
                'steps'   => $steps,
            ];
        }

        $createdDatabase = false;
        $createdDatabaseUsers = [];
        $tenantCredentialsUpdated = false;
        $tenantDb = null;
        $bootstrapPassword = $this->generateTemporaryPassword();

        try {
            $dbResult = $this->createTenantDatabase($tenant);
            $createdDatabase = !empty($dbResult['created']);
            $steps[] = $this->step('create_database', true, $createdDatabase ? 'Tenant database created.' : 'Tenant database already exists.');

            $accessResult = $this->ensureTenantDatabaseAccess($tenant);
            $tenant = $accessResult['tenant'];
            $createdDatabaseUsers = $accessResult['created_users'];
            $tenantCredentialsUpdated = !empty($accessResult['tenant_credentials_updated']);
            $steps[] = $this->step('db_access', $accessResult['success'], $accessResult['message']);
            if (!$accessResult['success']) {
                throw new RuntimeException($accessResult['message']);
            }

            $tenantDb = $this->connectTenantDatabase($tenant);
            $steps[] = $this->step('connect_database', $tenantDb !== null, $tenantDb ? 'Tenant database connection established with tenant credentials.' : 'Unable to connect with tenant database credentials.');
            if (!$tenantDb) {
                throw new RuntimeException('Unable to connect to tenant database after creation.');
            }

            $schemaResult = $this->cloneLandlordSchema($tenantDb);
            $steps[] = $this->step('clone_schema', $schemaResult['success'], $schemaResult['message']);
            if (!$schemaResult['success']) {
                throw new RuntimeException($schemaResult['message']);
            }

            $seedResult = $this->seedReferenceData($tenantDb, $tenant);
            $steps[] = $this->step('seed_reference_data', $seedResult['success'], $seedResult['message']);
            if (!$seedResult['success']) {
                throw new RuntimeException($seedResult['message']);
            }

            $moduleSeedResult = $this->seedTenantModules($tenantDb, $plan);
            $steps[] = $this->step('seed_modules', $moduleSeedResult['success'], $moduleSeedResult['message']);
            if (!$moduleSeedResult['success']) {
                throw new RuntimeException($moduleSeedResult['message']);
            }

            $staffResult = $this->seedTenantAdmin($tenantDb, $tenant, $bootstrapPassword);
            $steps[] = $this->step('create_admin_user', $staffResult['success'], $staffResult['message']);
            if (!$staffResult['success']) {
                throw new RuntimeException($staffResult['message']);
            }

            $optionsResult = $this->seedTenantOptions($tenantDb, $tenant);
            $steps[] = $this->step('seed_options', $optionsResult['success'], $optionsResult['message']);
            if (!$optionsResult['success']) {
                throw new RuntimeException($optionsResult['message']);
            }

            $steps[] = $this->step('prepare_storage', $this->ensureStoragePath($tenant), 'Storage namespace prepared.');
        } catch (Throwable $e) {
            if ($createdDatabase) {
                $this->dropTenantDatabase($tenant);
            }

            if (!empty($createdDatabaseUsers)) {
                $this->dropTenantDatabaseUsers($tenant, $createdDatabaseUsers);
            }

            if ($tenantCredentialsUpdated && is_array($originalTenant)) {
                $this->restoreTenantDatabaseCredentials($originalTenant);
            }

            $steps[] = $this->step('rollback', $createdDatabase, $createdDatabase ? 'Tenant database rolled back.' : 'No database rollback required.');
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'steps'   => $steps,
            ];
        }

        $tenant = $this->CI->Kt_saas_model->get_tenant((int) $tenant['id']) ?: $tenant;
        $manifest = $this->buildManifest($tenant, $plan, $job, $staffResult);
        $manifestSaved = $this->writeManifest($tenant, $manifest);
        $steps[] = $this->step('write_manifest', $manifestSaved, $manifestSaved ? 'Bootstrap manifest written.' : 'Unable to write tenant bootstrap manifest.');

        if (!$manifestSaved) {
            return [
                'success' => false,
                'message' => 'Failed to write bootstrap manifest.',
                'steps'   => $steps,
            ];
        }

        $steps[] = $this->step('queue_ready', true, 'Tenant bootstrap artifacts are ready for runtime integration.');
        $emailGuard = $this->reserveEmailEventGuard('provisioning_completed', [
            'tenant_id' => (int) $tenant['id'],
            'resource_type' => 'tenant',
            'resource_id' => (int) $tenant['id'],
            'dedupe_key' => 'provisioning_completed|' . (int) $tenant['id'],
        ]);

        return [
            'success'  => true,
            'message'  => 'Provisioning foundation completed successfully.',
            'steps'    => $steps,
            'manifest' => $manifest['manifest_path'],
            'admin_seeded' => true,
            'tenant_admin' => $staffResult,
            'email_event_guard' => $emailGuard,
        ];
    }

    protected function createTenantDatabase(array $tenant)
    {
        $database = trim((string) ($tenant['db_name'] ?? ''));
        $host = trim((string) ($tenant['db_host'] ?? APP_DB_HOSTNAME));
        $port = (int) trim((string) ($tenant['db_port'] ?? '3306'));
        if ($database === '') {
            throw new RuntimeException('Tenant database name is required.');
        }

        $mysqli = $this->adminConnection($host, $port);
        $escaped = str_replace('`', '``', $database);
        $exists = $mysqli->query("SHOW DATABASES LIKE '" . $mysqli->real_escape_string($database) . "'");
        $created = false;
        if (!$exists || $exists->num_rows === 0) {
            $charset = defined('APP_DB_CHARSET') ? APP_DB_CHARSET : 'utf8mb4';
            $collation = defined('APP_DB_COLLATION') ? APP_DB_COLLATION : 'utf8mb4_unicode_ci';
            if (!$mysqli->query("CREATE DATABASE `{$escaped}` CHARACTER SET {$charset} COLLATE {$collation}")) {
                throw new RuntimeException('Unable to create tenant database: ' . $mysqli->error);
            }
            $created = true;
        }
        $mysqli->close();

        return ['created' => $created];
    }

    protected function ensureTenantDatabaseAccess(array $tenant)
    {
        $dbUser = trim((string) ($tenant['db_user'] ?? ''));
        $dbPassword = $tenant['db_password_encrypted'] ? $this->CI->encryption->decrypt($tenant['db_password_encrypted']) : '';
        $createdUsers = [];
        $tenantCredentialsUpdated = false;

        if ($dbUser !== '' && $dbPassword !== '' && $this->canConnectTenantDatabase($tenant)) {
            return [
                'success' => true,
                'message' => 'Existing tenant DB credentials are already usable.',
                'tenant' => $tenant,
                'created_users' => [],
                'tenant_credentials_updated' => false,
            ];
        }

        try {
            if ($this->shouldAutoCreateTenantDbUser($tenant, $dbUser, $dbPassword)) {
                $generated = $this->generateTenantDbCredentials($tenant);
                $this->createTenantDatabaseUsers($tenant, $generated['username'], $generated['password'], $generated['hosts']);
                $this->grantTenantDatabasePrivileges($tenant, $generated['username'], $generated['hosts']);
                $this->persistTenantDatabaseCredentials((int) $tenant['id'], $generated['username'], $generated['password']);

                $tenant['db_user'] = $generated['username'];
                $tenant['db_password_encrypted'] = $this->CI->encryption->encrypt($generated['password']);
                $createdUsers = $generated['hosts'];
                $tenantCredentialsUpdated = true;

                return [
                    'success' => true,
                    'message' => 'Isolated tenant DB users created and granted privileges.',
                    'tenant' => $tenant,
                    'created_users' => $createdUsers,
                    'tenant_credentials_updated' => $tenantCredentialsUpdated,
                ];
            }

            if ($dbUser === '') {
                return [
                    'success' => false,
                    'message' => 'Tenant DB user is empty and auto-create DB user is disabled.',
                    'tenant' => $tenant,
                    'created_users' => [],
                    'tenant_credentials_updated' => false,
                ];
            }

            $hosts = $this->tenantDbClientHosts();
            $this->grantTenantDatabasePrivileges($tenant, $dbUser, $hosts);

            return [
                'success' => true,
                'message' => 'Existing tenant DB user privileges ensured.',
                'tenant' => $tenant,
                'created_users' => [],
                'tenant_credentials_updated' => false,
            ];
        } catch (Throwable $e) {
            $fallback = $this->fallbackToSharedDatabaseCredentials($tenant, $e);
            if ($fallback) {
                return $fallback;
            }

            throw $e;
        }
    }

    protected function dropTenantDatabase(array $tenant)
    {
        $database = trim((string) ($tenant['db_name'] ?? ''));
        if ($database === '') {
            return false;
        }

        $host = trim((string) ($tenant['db_host'] ?? APP_DB_HOSTNAME));
        $port = (int) trim((string) ($tenant['db_port'] ?? '3306'));
        $mysqli = $this->adminConnection($host, $port);
        $escaped = str_replace('`', '``', $database);
        $result = $mysqli->query("DROP DATABASE IF EXISTS `{$escaped}`");
        $mysqli->close();

        return (bool) $result;
    }

    protected function dropTenantDatabaseUsers(array $tenant, array $hosts)
    {
        $username = trim((string) ($tenant['db_user'] ?? ''));
        if ($username === '' || empty($hosts)) {
            return false;
        }

        $host = trim((string) ($tenant['db_host'] ?? APP_DB_HOSTNAME));
        $port = (int) trim((string) ($tenant['db_port'] ?? '3306'));
        $mysqli = $this->adminConnection($host, $port);
        $quotedUser = $this->quoteSqlIdentifierValue($mysqli, $username);

        foreach ($hosts as $clientHost) {
            $quotedHost = $this->quoteSqlIdentifierValue($mysqli, $clientHost);
            $mysqli->query("DROP USER IF EXISTS {$quotedUser}@{$quotedHost}");
        }

        @$mysqli->query('FLUSH PRIVILEGES');
        $mysqli->close();

        return true;
    }

    protected function adminConnection($host, $port)
    {
        $mysqli = mysqli_init();
        $connected = @$mysqli->real_connect($host, APP_DB_USERNAME, defined('APP_DB_PASSWORD') ? APP_DB_PASSWORD : '', null, $port);
        if (!$connected) {
            throw new RuntimeException('Unable to connect with provisioning admin credentials.');
        }
        return $mysqli;
    }

    protected function createTenantDatabaseUsers(array $tenant, $username, $password, array $hosts)
    {
        $host = trim((string) ($tenant['db_host'] ?? APP_DB_HOSTNAME));
        $port = (int) trim((string) ($tenant['db_port'] ?? '3306'));
        $mysqli = $this->adminConnection($host, $port);
        $quotedUser = $this->quoteSqlIdentifierValue($mysqli, $username);
        $quotedPassword = $this->quoteSqlLiteral($mysqli, $password);

        foreach ($hosts as $clientHost) {
            $quotedHost = $this->quoteSqlIdentifierValue($mysqli, $clientHost);
            if (!$mysqli->query("CREATE USER IF NOT EXISTS {$quotedUser}@{$quotedHost} IDENTIFIED BY {$quotedPassword}")) {
                $mysqli->close();
                throw new RuntimeException('Unable to create tenant DB user [' . $username . '@' . $clientHost . ']: ' . $mysqli->error);
            }

            // Keep the password in sync when the user already exists.
            $mysqli->query("ALTER USER {$quotedUser}@{$quotedHost} IDENTIFIED BY {$quotedPassword}");
        }

        @$mysqli->query('FLUSH PRIVILEGES');
        $mysqli->close();
    }

    protected function grantTenantDatabasePrivileges(array $tenant, $username, array $hosts)
    {
        $database = trim((string) ($tenant['db_name'] ?? ''));
        $host = trim((string) ($tenant['db_host'] ?? APP_DB_HOSTNAME));
        $port = (int) trim((string) ($tenant['db_port'] ?? '3306'));
        $mysqli = $this->adminConnection($host, $port);
        $quotedUser = $this->quoteSqlIdentifierValue($mysqli, $username);
        $escapedDb = str_replace('`', '``', $database);

        foreach ($hosts as $clientHost) {
            $quotedHost = $this->quoteSqlIdentifierValue($mysqli, $clientHost);
            if (!$mysqli->query("GRANT ALL PRIVILEGES ON `{$escapedDb}`.* TO {$quotedUser}@{$quotedHost}")) {
                $mysqli->close();
                throw new RuntimeException('Unable to grant tenant DB privileges for [' . $username . '@' . $clientHost . ']: ' . $mysqli->error);
            }
        }

        @$mysqli->query('FLUSH PRIVILEGES');
        $mysqli->close();
    }

    protected function persistTenantDatabaseCredentials($tenantId, $username, $password)
    {
        $this->CI->db->where('id', (int) $tenantId)->update(db_prefix() . 'kt_saas_tenants', [
            'db_user' => $username,
            'db_password_encrypted' => $this->CI->encryption->encrypt($password),
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => get_staff_user_id() ?: null,
        ]);
    }

    protected function restoreTenantDatabaseCredentials(array $tenant)
    {
        $this->CI->db->where('id', (int) $tenant['id'])->update(db_prefix() . 'kt_saas_tenants', [
            'db_user' => $tenant['db_user'] ?? null,
            'db_password_encrypted' => $tenant['db_password_encrypted'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => get_staff_user_id() ?: null,
        ]);
    }

    protected function connectTenantDatabase(array $tenant)
    {
        $password = $tenant['db_password_encrypted'] ? $this->CI->encryption->decrypt($tenant['db_password_encrypted']) : '';
        $config = [
            'dsn'          => '',
            'hostname'     => $tenant['db_host'],
            'username'     => $tenant['db_user'],
            'password'     => $password,
            'database'     => $tenant['db_name'],
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
            'port'         => $tenant['db_port'],
        ];

        try {
            $db = $this->CI->load->database($config, true);
            $db->initialize();
            return $db;
        } catch (Throwable $e) {
            return null;
        }
    }

    protected function shouldAutoCreateTenantDbUser(array $tenant, $dbUser, $dbPassword)
    {
        if (kt_saas_get_option('kt_saas_auto_create_db_user', '1') !== '1') {
            return false;
        }

        if ($dbUser === '' || $dbPassword === '') {
            return true;
        }

        if (defined('APP_DB_USERNAME') && $dbUser === APP_DB_USERNAME) {
            return true;
        }

        if (defined('APP_DB_PASSWORD') && $dbPassword === (string) APP_DB_PASSWORD) {
            return true;
        }

        return false;
    }

    protected function canConnectTenantDatabase(array $tenant)
    {
        $db = $this->connectTenantDatabase($tenant);
        if (!$db) {
            return false;
        }

        try {
            $db->query('SELECT 1');
        } catch (Throwable $e) {
            return false;
        }

        return true;
    }

    protected function fallbackToSharedDatabaseCredentials(array $tenant, Throwable $exception)
    {
        if (!defined('APP_DB_USERNAME') || trim((string) APP_DB_USERNAME) === '') {
            return null;
        }

        $sharedUser = trim((string) APP_DB_USERNAME);
        $sharedPassword = defined('APP_DB_PASSWORD') ? (string) APP_DB_PASSWORD : '';
        $tenantId = (int) ($tenant['id'] ?? 0);

        $this->persistTenantDatabaseCredentials($tenantId, $sharedUser, $sharedPassword);
        $tenant['db_user'] = $sharedUser;
        $tenant['db_password_encrypted'] = $this->CI->encryption->encrypt($sharedPassword);

        if (!$this->canConnectTenantDatabase($tenant)) {
            return null;
        }

        $this->CI->Kt_saas_model->log_activity('provision.db_credentials_fallback', 'warning', [
            'tenant_id' => $tenantId,
            'tenant_code' => $tenant['tenant_code'] ?? null,
            'reason' => $exception->getMessage(),
            'fallback_db_user' => $sharedUser,
        ], $tenantId);

        return [
            'success' => true,
            'message' => 'Provisioning fell back to shared application DB credentials.',
            'tenant' => $tenant,
            'created_users' => [],
            'tenant_credentials_updated' => true,
        ];
    }

    protected function generateTenantDbCredentials(array $tenant)
    {
        $tenantCode = strtolower(trim((string) ($tenant['tenant_code'] ?? 'tenant')));
        $tenantCode = preg_replace('/[^a-z0-9_]/', '_', $tenantCode) ?: 'tenant';
        $prefix = strtolower(trim((string) kt_saas_get_option('kt_saas_db_user_prefix', 'tenant_')));
        $prefix = preg_replace('/[^a-z0-9_]/', '_', $prefix) ?: 'tenant_';
        $base = $prefix . $tenantCode . '_' . (int) ($tenant['id'] ?? 0);
        $username = substr($base, 0, 28) . substr(md5($base . microtime(true)), 0, 4);
        $username = substr($username, 0, 32);

        return [
            'username' => $username,
            'password' => 'KtDb!' . bin2hex(random_bytes(12)),
            'hosts' => $this->tenantDbClientHosts(),
        ];
    }

    protected function tenantDbClientHosts()
    {
        $configured = trim((string) kt_saas_get_option('kt_saas_default_db_client_hosts', 'localhost,127.0.0.1'));
        $parts = preg_split('/[\s,]+/', $configured);
        $parts = array_values(array_unique(array_filter(array_map(function ($value) {
            return trim((string) $value);
        }, $parts))));

        return !empty($parts) ? $parts : ['localhost', '127.0.0.1'];
    }

    protected function quoteSqlIdentifierValue(mysqli $mysqli, $value)
    {
        return "'" . $mysqli->real_escape_string((string) $value) . "'";
    }

    protected function quoteSqlLiteral(mysqli $mysqli, $value)
    {
        return "'" . $mysqli->real_escape_string((string) $value) . "'";
    }

    protected function cloneLandlordSchema($tenantDb)
    {
        $landlordTables = $this->landlordDb->query('SHOW TABLES')->result_array();
        if (empty($landlordTables)) {
            return ['success' => false, 'message' => 'No source tables found in landlord database.'];
        }

        $tenantDb->query('SET FOREIGN_KEY_CHECKS=0');
        foreach ($landlordTables as $row) {
            $table = array_values($row)[0];
            if (strpos($table, db_prefix() . 'kt_saas_') === 0) {
                continue;
            }

            $createRow = $this->landlordDb->query('SHOW CREATE TABLE `' . $table . '`')->row_array();
            if (!$createRow || empty($createRow['Create Table'])) {
                continue;
            }

            $tenantDb->query('DROP TABLE IF EXISTS `' . $table . '`');
            $tenantDb->query($createRow['Create Table']);
        }
        $tenantDb->query('SET FOREIGN_KEY_CHECKS=1');

        return ['success' => true, 'message' => 'Landlord schema cloned into tenant database.'];
    }

    protected function seedReferenceData($tenantDb, array $tenant)
    {
        $tables = [
            db_prefix() . 'migrations',
            db_prefix() . 'options',
            db_prefix() . 'countries',
            db_prefix() . 'currencies',
            db_prefix() . 'payment_modes',
            db_prefix() . 'roles',
            db_prefix() . 'emailtemplates',
            db_prefix() . 'taxes',
            db_prefix() . 'tickets_priorities',
            db_prefix() . 'tickets_status',
            db_prefix() . 'services',
            db_prefix() . 'leads_sources',
            db_prefix() . 'leads_status',
            db_prefix() . 'items_groups',
            db_prefix() . 'expenses_categories',
            db_prefix() . 'consent_purposes',
        ];

        foreach ($tables as $table) {
            if (!$this->landlordDb->table_exists($table) || !$tenantDb->table_exists($table)) {
                continue;
            }

            $rows = $this->landlordDb->get($table)->result_array();
            if (empty($rows)) {
                continue;
            }

            if ($table === db_prefix() . 'options') {
                $rows = array_values(array_filter($rows, function ($row) {
                    $name = $row['name'] ?? '';
                    return strpos($name, 'kt_saas_') !== 0;
                }));
            }

            $tenantDb->query('SET FOREIGN_KEY_CHECKS=0');
            $tenantDb->truncate($table);
            if (!empty($rows)) {
                $tenantDb->insert_batch($table, $rows);
            }
            $tenantDb->query('SET FOREIGN_KEY_CHECKS=1');
        }

        return ['success' => true, 'message' => 'Reference data seeded from landlord database.'];
    }

    protected function seedTenantModules($tenantDb, array $plan)
    {
        $modulesTable = db_prefix() . 'modules';
        if (!$tenantDb->table_exists($modulesTable)) {
            return ['success' => true, 'message' => 'Modules table not present, module seeding skipped.'];
        }

        $landlordModules = $this->landlordDb->order_by('module_name', 'asc')->get($modulesTable)->result_array();
        if (empty($landlordModules)) {
            return ['success' => true, 'message' => 'No landlord modules available to seed.'];
        }

        $tenantDb->truncate($modulesTable);
        $tenantDb->insert_batch($modulesTable, $landlordModules);

        $moduleCodes = json_decode((string) ($plan['module_json'] ?? '[]'), true) ?: [];
        if (empty($moduleCodes)) {
            return ['success' => true, 'message' => 'Tenant modules seeded from landlord registry.'];
        }

        foreach ($moduleCodes as $moduleCode) {
            $moduleCode = trim((string) $moduleCode);
            if ($moduleCode === '' || $moduleCode === KT_SAAS_MODULE) {
                continue;
            }

            $exists = $tenantDb->where('module_name', $moduleCode)->get($modulesTable)->row_array();
            if ($exists) {
                $tenantDb->where('module_name', $moduleCode)->update($modulesTable, ['active' => 1]);
                continue;
            }

            $tenantDb->insert($modulesTable, [
                'module_name'       => $moduleCode,
                'installed_version' => '0.0.0',
                'active'            => 1,
            ]);
        }

        $this->installTenantModuleSchemas($tenantDb, $moduleCodes);

        return ['success' => true, 'message' => 'Tenant modules seeded from landlord registry and plan entitlements.'];
    }

    protected function installTenantModuleSchemas($tenantDb, array $moduleCodes)
    {
        $moduleCodes = array_map(function ($moduleCode) {
            return strtolower(trim((string) $moduleCode));
        }, $moduleCodes);

        foreach (['goals', 'kt_inventory', 'kt_sepay'] as $moduleName) {
            if (!in_array($moduleName, $moduleCodes, true)) {
                continue;
            }

            $this->runTenantModuleInstaller($tenantDb, $moduleName);
        }
    }

    protected function runTenantModuleInstaller($tenantDb, $moduleName)
    {
        $installPath = module_dir_path($moduleName, 'install.php');
        if (!file_exists($installPath)) {
            return;
        }

        $originalDb = $this->CI->db;
        $this->CI->db = $tenantDb;

        try {
            if ($moduleName === 'kt_sepay') {
                require_once $installPath;
                if (function_exists('kt_sepay_run_install')) {
                    kt_sepay_run_install();
                }
            } else {
                include $installPath;
            }
        } catch (Throwable $e) {
            log_message('error', 'KT SaaS provisioning module install failed for [' . $moduleName . ']: ' . $e->getMessage());
        }

        $this->CI->db = $originalDb;
    }

    protected function seedTenantAdmin($tenantDb, array $tenant, $temporaryPassword)
    {
        $staffTable = db_prefix() . 'staff';
        if (!$tenantDb->table_exists($staffTable)) {
            return ['success' => false, 'message' => 'Staff table not found in tenant database.'];
        }

        if ($tenantDb->count_all_results($staffTable) > 0) {
            return ['success' => true, 'message' => 'Tenant admin skipped because staff already exists.'];
        }

        $fields = array_column($tenantDb->field_data($staffTable), 'name');
        $roleId = 0;
        $rolesTable = db_prefix() . 'roles';
        if ($tenantDb->table_exists($rolesTable)) {
            $role = $tenantDb->order_by('roleid', 'asc')->get($rolesTable)->row_array();
            $roleId = (int) ($role['roleid'] ?? 0);
        }

        $nameParts = preg_split('/\s+/', trim((string) $tenant['owner_name']));
        $firstName = $nameParts[0] ?? 'Tenant';
        $lastName = trim(str_replace($firstName, '', trim((string) $tenant['owner_name'])));
        if ($lastName === '') {
            $lastName = 'Admin';
        }

        $setPasswordKey = app_generate_hash();
        $requestedAt = date('Y-m-d H:i:s');

        $payload = [
            'firstname'             => $firstName,
            'lastname'              => $lastName,
            'email'                 => $tenant['owner_email'],
            'password'              => app_hash_password($temporaryPassword),
            'role'                  => $roleId,
            'admin'                 => 1,
            'active'                => 1,
            'default_language'      => $tenant['locale'] ?: 'english',
            'direction'             => 'ltr',
            'media_path_slug'       => md5($tenant['tenant_code'] . $tenant['owner_email']),
            'is_not_staff'          => 0,
            'hourly_rate'           => 0,
            'datecreated'           => date('Y-m-d H:i:s'),
            'email_signature'       => '',
            'two_factor_auth_enabled' => 0,
            'new_pass_key'          => $setPasswordKey,
            'new_pass_key_requested'=> $requestedAt,
        ];

        $insert = [];
        foreach ($payload as $field => $value) {
            if (in_array($field, $fields, true)) {
                $insert[$field] = $value;
            }
        }

        $tenantDb->insert($staffTable, $insert);
        $staffId = (int) $tenantDb->insert_id();

        return [
            'success'          => true,
            'message'          => 'Tenant admin user created with onboarding token.',
            'staff_id'         => $staffId,
            'email'            => $tenant['owner_email'],
            'new_pass_key'     => $setPasswordKey,
            'requested_at'     => $requestedAt,
            'set_password_url' => $this->tenantSetPasswordUrl($tenant, $staffId, $setPasswordKey),
            'admin_login_url'  => $this->tenantAdminLoginUrl($tenant),
        ];
    }

    protected function seedTenantOptions($tenantDb, array $tenant)
    {
        $optionsTable = db_prefix() . 'options';
        if (!$tenantDb->table_exists($optionsTable)) {
            return ['success' => false, 'message' => 'Options table not found in tenant database.'];
        }

        $updates = [
            'companyname'         => $tenant['company_name'],
            'company_email'       => $tenant['owner_email'],
            'companyphonenumber'  => $tenant['phone'],
            'default_currency'    => $tenant['currency'],
            'active_language'     => $tenant['locale'],
            'default_language'    => $tenant['locale'],
            'default_timezone'    => $tenant['timezone'],
            'invoice_company_name'=> $tenant['company_name'],
        ];

        $optionFields = array_column($tenantDb->field_data($optionsTable), 'name');

        foreach ($updates as $name => $value) {
            $existing = $tenantDb->where('name', $name)->get($optionsTable)->row_array();
            if ($existing) {
                $tenantDb->where('name', $name)->update($optionsTable, ['value' => (string) $value]);
            } else {
                $payload = ['name' => $name, 'value' => (string) $value];
                if (in_array('autoload', $optionFields, true)) {
                    $payload['autoload'] = 1;
                }
                $tenantDb->insert($optionsTable, $payload);
            }
        }

        foreach (['company_logo', 'company_logo_dark', 'favicon'] as $brandingOption) {
            $existing = $tenantDb->where('name', $brandingOption)->get($optionsTable)->row_array();
            if ($existing) {
                $tenantDb->where('name', $brandingOption)->update($optionsTable, ['value' => '']);
            } else {
                $payload = ['name' => $brandingOption, 'value' => ''];
                if (in_array('autoload', $optionFields, true)) {
                    $payload['autoload'] = 1;
                }
                $tenantDb->insert($optionsTable, $payload);
            }
        }

        return ['success' => true, 'message' => 'Tenant options updated.'];
    }

    protected function generateTemporaryPassword()
    {
        $bytes = bin2hex(random_bytes(6));
        return 'Kt!' . strtoupper($bytes);
    }

    protected function ensureStoragePath(array $tenant)
    {
        $path = trim((string) ($tenant['storage_path'] ?? ''));
        if ($path === '') {
            $path = FCPATH . 'uploads/tenants/' . strtolower($tenant['tenant_code']);
            $this->CI->db->where('id', (int) $tenant['id'])->update(db_prefix() . 'kt_saas_tenants', [
                'storage_path' => $path,
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
        }

        if (is_dir($path)) {
            return true;
        }

        return mkdir($path, 0775, true);
    }

    protected function buildManifest(array $tenant, array $plan, array $job, array $staffResult = [])
    {
        $filename = strtolower($tenant['tenant_code']) . '.json';
        $manifestPath = module_dir_path(KT_SAAS_MODULE, 'tenant_bootstrap/manifests/' . $filename);
        $runtimeStatus = $this->predictedTenantRuntimeStatus($tenant, $plan);

        return [
            'tenant_id'        => (int) $tenant['id'],
            'tenant_code'      => $tenant['tenant_code'],
            'company_name'     => $tenant['company_name'],
            'owner_email'      => $tenant['owner_email'],
            'status'           => $runtimeStatus,
            'plan'             => [
                'id'         => (int) $plan['id'],
                'code'       => $plan['plan_code'],
                'name'       => $plan['plan_name'],
                'limits'     => [
                    'staff'         => (int) $plan['limit_staff'],
                    'clients'       => (int) $plan['limit_clients'],
                    'storage_mb'    => (int) $plan['limit_storage_mb'],
                    'invoices'      => (int) $plan['limit_invoices'],
                    'projects'      => (int) $plan['limit_projects'],
                    'api_daily'     => (int) $plan['limit_api_requests_daily'],
                    'warehouses'    => (int) $plan['limit_warehouses'],
                    'automations'   => (int) $plan['limit_automations'],
                ],
                'modules'    => json_decode((string) $plan['module_json'], true) ?: [],
            ],
            'database'         => [
                'host' => $tenant['db_host'],
                'port' => $tenant['db_port'],
                'name' => $tenant['db_name'],
                'user' => $tenant['db_user'],
            ],
            'runtime'          => [
                'subdomain'      => $tenant['subdomain'],
                'custom_domain'  => $tenant['custom_domain'],
                'timezone'       => $tenant['timezone'],
                'locale'         => $tenant['locale'],
                'currency'       => $tenant['currency'],
                'storage_driver' => $tenant['storage_driver'],
                'storage_path'   => $tenant['storage_path'],
            ],
            'provision_job'    => [
                'id'        => (int) ($job['id'] ?? 0),
                'job_type'  => $job['job_type'] ?? 'provision_tenant',
                'created_at'=> date('c'),
            ],
            'provisioning'     => [
                'mode'       => 'database_clone',
                'generated_at' => date('c'),
            ],
            'onboarding'       => [
                'admin_email'       => (string) ($staffResult['email'] ?? $tenant['owner_email']),
                'staff_id'          => (int) ($staffResult['staff_id'] ?? 0),
                'admin_login_url'   => (string) ($staffResult['admin_login_url'] ?? $this->tenantAdminLoginUrl($tenant)),
                'onboarding_link_generated' => !empty($staffResult['set_password_url']),
                'token_requested_at'=> (string) ($staffResult['requested_at'] ?? ''),
            ],
            'manifest_path'    => $manifestPath,
        ];
    }

    protected function writeManifest(array $tenant, array $manifest)
    {
        $path = $manifest['manifest_path'];
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return false;
        }

        return file_put_contents($path, $json) !== false;
    }

    protected function step($key, $success, $message)
    {
        return [
            'step'    => $key,
            'success' => (bool) $success,
            'message' => $message,
        ];
    }

    protected function tenantBaseUrl(array $tenant)
    {
        $scheme = (string) parse_url(APP_BASE_URL, PHP_URL_SCHEME);
        if ($scheme === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
        }
        $host = trim((string) ($tenant['custom_domain'] ?? ''));

        if ($host === '') {
            $host = trim((string) ($tenant['subdomain'] ?? ''));
            if ($host !== '' && strpos($host, '.') === false) {
                $baseDomain = trim((string) kt_saas_get_option('kt_saas_base_domain', ''));
                if ($baseDomain !== '') {
                    $host .= '.' . $baseDomain;
                }
            }
        }

        if ($host === '') {
            $host = parse_url(APP_BASE_URL, PHP_URL_HOST);
        }

        return rtrim($scheme . '://' . $host, '/');
    }

    protected function predictedTenantRuntimeStatus(array $tenant, array $plan)
    {
        $currentStatus = (string) ($tenant['status'] ?? 'draft');
        if (in_array($currentStatus, ['suspended', 'terminated', 'archived'], true)) {
            return $currentStatus;
        }

        return ((int) ($plan['trial_days'] ?? 0) > 0) ? 'trial' : 'active';
    }

    protected function tenantAdminLoginUrl(array $tenant)
    {
        $url = $this->tenantBaseUrl($tenant) . '/' . trim(get_admin_uri(), '/\\') . '/authentication';

        return function_exists('kt_saas_url_with_tenant_host')
            ? kt_saas_url_with_tenant_host($url, $tenant)
            : $url;
    }

    protected function tenantSetPasswordUrl(array $tenant, $staffId, $newPassKey)
    {
        $url = $this->tenantBaseUrl($tenant) . '/' . trim(get_admin_uri(), '/\\') . '/authentication/set_password/1/' . (int) $staffId . '/' . rawurlencode((string) $newPassKey);

        return function_exists('kt_saas_url_with_tenant_host')
            ? kt_saas_url_with_tenant_host($url, $tenant)
            : $url;
    }

    protected function reserveEmailEventGuard($eventKey, array $context)
    {
        if (function_exists('kt_saas_reserve_email_event')) {
            return kt_saas_reserve_email_event($eventKey, $context);
        }

        require_once module_dir_path(KT_SAAS_MODULE, 'helpers/kt_saas_helper.php');
        if (function_exists('kt_saas_reserve_email_event')) {
            return kt_saas_reserve_email_event($eventKey, $context);
        }

        return ['allowed' => false, 'message' => 'Email guard helper unavailable.'];
    }
}
