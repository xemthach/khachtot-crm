<?php

defined('BASEPATH') or exit('No direct script access allowed');

class DatabaseSwitcher
{
    public function switchConnection(array $tenant)
    {
        $CI = &get_instance();

        $dbName = trim((string) ($tenant['db_name'] ?? ''));
        $dbHost = trim((string) ($tenant['db_host'] ?? ''));
        $dbPort = trim((string) ($tenant['db_port'] ?? '3306'));
        $dbUser = trim((string) ($tenant['db_user'] ?? ''));
        $dbPasswordEncrypted = $tenant['db_password_encrypted'] ?? null;

        if ($dbName === '' || $dbHost === '' || $dbUser === '' || !$dbPasswordEncrypted) {
            return [
                'switched'    => false,
                'tenant_code' => $tenant['tenant_code'] ?? null,
                'message'     => 'Tenant database credentials are incomplete.',
            ];
        }

        $password = $CI->encryption->decrypt($dbPasswordEncrypted);
        if ($password === false) {
            return [
                'switched'    => false,
                'tenant_code' => $tenant['tenant_code'] ?? null,
                'message'     => 'Unable to decrypt tenant database password.',
            ];
        }

        $current = $CI->db;
        $config = [
            'dsn'          => '',
            'hostname'     => $dbHost,
            'username'     => $dbUser,
            'password'     => $password,
            'database'     => $dbName,
            'dbdriver'     => defined('APP_DB_DRIVER') ? APP_DB_DRIVER : 'mysqli',
            'dbprefix'     => db_prefix(),
            'pconnect'     => false,
            'db_debug'     => (ENVIRONMENT !== 'production'),
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
            'port'         => $dbPort,
        ];

        try {
            $tenantDb = $CI->load->database($config, true);
            $tenantDb->initialize();
            $CI->db = $tenantDb;
            $CI->config->set_item('kt_saas_landlord_db', $current);

            return [
                'switched'    => true,
                'tenant_code' => $tenant['tenant_code'] ?? null,
                'database'    => $dbName,
            ];
        } catch (Throwable $e) {
            return [
                'switched'    => false,
                'tenant_code' => $tenant['tenant_code'] ?? null,
                'message'     => $e->getMessage(),
            ];
        }
    }
}
