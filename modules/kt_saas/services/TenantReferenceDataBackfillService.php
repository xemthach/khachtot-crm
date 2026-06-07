<?php

defined('BASEPATH') or exit('No direct script access allowed');

class TenantReferenceDataBackfillService
{
    protected $CI;
    protected $landlordDb;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->library('encryption');
        $this->landlordDb = $this->CI->config->item('kt_saas_landlord_db') ?: $this->CI->db;
    }

    public function run(array $options = [])
    {
        $tenantId = isset($options['tenant_id']) ? (int) $options['tenant_id'] : 0;
        $dryRun = array_key_exists('dry_run', $options) ? (bool) $options['dry_run'] : true;
        $tables = $this->referenceTables();

        $tenants = $this->resolveTenants($tenantId);
        $results = [];

        foreach ($tenants as $tenant) {
            $results[] = $this->backfillTenant($tenant, $tables, $dryRun);
        }

        return [
            'success' => true,
            'dry_run' => $dryRun,
            'tenant_count' => count($results),
            'results' => $results,
            'tables' => $tables,
        ];
    }

    protected function referenceTables()
    {
        return [
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
    }

    protected function resolveTenants($tenantId = 0)
    {
        $db = $this->landlordDb;
        $db->from(db_prefix() . 'kt_saas_tenants');
        $db->where('deleted_at IS NULL', null, false);
        if ($tenantId > 0) {
            $db->where('id', $tenantId);
        } else {
            $db->where_in('status', ['active', 'trial', 'grace', 'suspended']);
        }
        return $db->order_by('id', 'asc')->get()->result_array();
    }

    protected function backfillTenant(array $tenant, array $tables, $dryRun)
    {
        $tenantResult = [
            'tenant_id' => (int) ($tenant['id'] ?? 0),
            'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
            'database' => (string) ($tenant['db_name'] ?? ''),
            'success' => false,
            'message' => '',
            'tables' => [],
        ];

        $tenantDb = $this->connectTenantDatabase($tenant);
        if (!$tenantDb) {
            $tenantResult['message'] = 'Cannot connect tenant database.';
            return $tenantResult;
        }

        foreach ($tables as $table) {
            $row = [
                'table' => $table,
                'landlord_exists' => $this->landlordDb->table_exists($table),
                'tenant_exists' => $tenantDb->table_exists($table),
                'landlord_rows' => 0,
                'tenant_rows_before' => 0,
                'tenant_rows_after' => 0,
                'action' => 'skip',
                'error' => null,
            ];

            if (!$row['landlord_exists'] || !$row['tenant_exists']) {
                $tenantResult['tables'][] = $row;
                continue;
            }

            $row['landlord_rows'] = (int) $this->landlordDb->count_all_results($table);
            $row['tenant_rows_before'] = (int) $tenantDb->count_all_results($table);

            if ($dryRun) {
                $row['tenant_rows_after'] = $row['tenant_rows_before'];
                $row['action'] = 'dry_run';
                $tenantResult['tables'][] = $row;
                continue;
            }

            try {
                $rows = $this->landlordDb->get($table)->result_array();
                if ($table === db_prefix() . 'options') {
                    $rows = array_values(array_filter($rows, function ($item) {
                        $name = $item['name'] ?? '';
                        return strpos((string) $name, 'kt_saas_') !== 0;
                    }));
                }

                $tenantDb->query('SET FOREIGN_KEY_CHECKS=0');
                $tenantDb->truncate($table);
                if (!empty($rows)) {
                    $tenantDb->insert_batch($table, $rows);
                }
                $tenantDb->query('SET FOREIGN_KEY_CHECKS=1');

                $row['tenant_rows_after'] = (int) $tenantDb->count_all_results($table);
                $row['action'] = 'reseed';
            } catch (Throwable $e) {
                $row['error'] = $e->getMessage();
                $row['action'] = 'error';
            }

            $tenantResult['tables'][] = $row;
        }

        $errors = array_filter($tenantResult['tables'], function ($item) {
            return !empty($item['error']);
        });

        $tenantResult['success'] = empty($errors);
        $tenantResult['message'] = $tenantResult['success'] ? 'Backfill completed.' : 'Backfill completed with errors.';
        return $tenantResult;
    }

    protected function connectTenantDatabase(array $tenant)
    {
        $dbName = trim((string) ($tenant['db_name'] ?? ''));
        $dbHost = trim((string) ($tenant['db_host'] ?? ''));
        $dbUser = trim((string) ($tenant['db_user'] ?? ''));
        $dbPort = trim((string) ($tenant['db_port'] ?? '3306'));
        $encryptedPassword = $tenant['db_password_encrypted'] ?? null;

        if ($dbName === '' || $dbHost === '' || $dbUser === '' || empty($encryptedPassword)) {
            return null;
        }

        $password = $this->CI->encryption->decrypt($encryptedPassword);
        if ($password === false) {
            return null;
        }

        $config = [
            'dsn'          => '',
            'hostname'     => $dbHost,
            'username'     => $dbUser,
            'password'     => $password,
            'database'     => $dbName,
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
            'save_queries' => false,
            'port'         => $dbPort,
        ];

        try {
            $tenantDb = $this->CI->load->database($config, true);
            $tenantDb->initialize();
            return $tenantDb;
        } catch (Throwable $e) {
            return null;
        }
    }
}
