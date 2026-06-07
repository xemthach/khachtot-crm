<?php

defined('BASEPATH') or exit('No direct script access allowed');

class TenantBackupService
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->helper('file');
        $this->CI->load->model('kt_saas/Kt_saas_model');
    }

    public function createBackup($tenantId)
    {
        $tenant = $this->CI->Kt_saas_model->get_tenant((int) $tenantId);
        if (!$tenant) {
            return ['success' => false, 'message' => 'Tenant not found.'];
        }

        $runningBackup = $this->CI->Kt_saas_model->get_running_backup_for_tenant((int) $tenantId);
        if ($runningBackup) {
            return ['success' => false, 'message' => 'A backup or restore operation is already running for this tenant.'];
        }

        $backupId = $this->CI->Kt_saas_model->create_backup_record((int) $tenantId, 'db', 'local');
        $startedAt = date('Y-m-d H:i:s');
        $this->CI->Kt_saas_model->update_backup_record($backupId, [
            'status'     => 'running',
            'started_at' => $startedAt,
        ]);

        $tenantDb = $this->connectTenantDatabase($tenant);
        if (!$tenantDb) {
            $this->CI->Kt_saas_model->update_backup_record($backupId, [
                'status'       => 'failed',
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
            return ['success' => false, 'message' => 'Tenant database connection is unavailable.'];
        }

        try {
            $dir = $this->backupDirectoryForTenant($tenant);
            if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new RuntimeException('Unable to create backup directory.');
            }

            $fileName = sprintf(
                '%s_backup_%d_%s.sql',
                strtolower((string) ($tenant['tenant_code'] ?? 'tenant')),
                (int) $backupId,
                date('Ymd_His')
            );
            $filePath = $dir . DIRECTORY_SEPARATOR . $fileName;

            $sql = $this->buildDump($tenantDb, (string) ($tenant['db_name'] ?? ''));
            if (write_file($filePath, $sql) === false) {
                throw new RuntimeException('Unable to write backup file.');
            }

            $this->CI->Kt_saas_model->update_backup_record($backupId, [
                'status'          => 'done',
                'file_path'       => $filePath,
                'file_size_bytes' => (int) filesize($filePath),
                'checksum'        => hash_file('sha256', $filePath),
                'completed_at'    => date('Y-m-d H:i:s'),
            ]);

            $this->CI->Kt_saas_model->log_activity('backup.created', 'info', [
                'backup_id'  => $backupId,
                'tenant_id'  => (int) $tenantId,
                'file_path'  => $filePath,
            ], (int) $tenantId);

            $this->dispatchBackupEmail('backup_completed', [
                'backup_id' => (int) $backupId,
                'tenant_id' => (int) $tenantId,
                'tenant_name' => (string) ($tenant['tenant_name'] ?? $tenant['company_name'] ?? $tenant['tenant_code'] ?? ''),
                'recipient_email' => function_exists('kt_saas_landlord_ops_email') ? kt_saas_landlord_ops_email() : '',
                'owner_name' => 'Operations',
                'provider_name' => 'KT SaaS',
                'module_name' => KT_SAAS_MODULE,
                'webhook_url' => site_url('admin/kt_saas/backups'),
                'job_id' => (string) $backupId,
                'related_type' => 'backup',
                'related_id' => (string) $backupId,
                'dedupe_key' => 'backup_completed|' . (int) $backupId,
            ]);

            return ['success' => true, 'backup_id' => $backupId, 'tenant_id' => (int) $tenantId];
        } catch (Throwable $e) {
            $this->CI->Kt_saas_model->update_backup_record($backupId, [
                'status'       => 'failed',
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
            $this->CI->Kt_saas_model->log_activity('backup.failed', 'error', [
                'backup_id'     => (int) $backupId,
                'tenant_id'     => (int) $tenantId,
                'error_message' => $e->getMessage(),
            ], (int) $tenantId);
            log_message('error', 'KT SaaS backup failed: ' . $e->getMessage());
            $this->dispatchBackupEmail('backup_failed', [
                'backup_id' => (int) $backupId,
                'tenant_id' => (int) $tenantId,
                'tenant_name' => (string) ($tenant['tenant_name'] ?? $tenant['company_name'] ?? $tenant['tenant_code'] ?? ''),
                'recipient_email' => function_exists('kt_saas_landlord_ops_email') ? kt_saas_landlord_ops_email() : '',
                'owner_name' => 'Operations',
                'provider_name' => 'KT SaaS',
                'module_name' => KT_SAAS_MODULE,
                'error_message' => $e->getMessage(),
                'webhook_url' => site_url('admin/kt_saas/backups'),
                'job_id' => (string) $backupId,
                'related_type' => 'backup',
                'related_id' => (string) $backupId,
                'dedupe_key' => 'backup_failed|' . (int) $backupId,
            ]);
            return ['success' => false, 'message' => $e->getMessage(), 'tenant_id' => (int) $tenantId];
        } finally {
            if (method_exists($tenantDb, 'close')) {
                $tenantDb->close();
            }
        }
    }

    public function restoreBackup($backupId)
    {
        $backup = $this->CI->Kt_saas_model->get_backup((int) $backupId);
        if (!$backup) {
            return ['success' => false, 'message' => 'Backup record not found.'];
        }

        $tenant = $this->CI->Kt_saas_model->get_tenant((int) $backup['tenant_id']);
        if (!$tenant) {
            return ['success' => false, 'message' => 'Tenant not found.', 'tenant_id' => (int) $backup['tenant_id']];
        }

        $filePath = (string) ($backup['file_path'] ?? '');
        if ($filePath === '' || !is_file($filePath)) {
            return $this->restoreFailure($backup, 'Backup file is missing.', $filePath);
        }

        if (!$this->isSafeBackupPath($filePath)) {
            return $this->restoreFailure($backup, 'Backup file path is invalid.', $filePath);
        }

        $expectedChecksum = trim((string) ($backup['checksum'] ?? ''));
        if ($expectedChecksum !== '') {
            $actualChecksum = hash_file('sha256', $filePath);
            if (!hash_equals($expectedChecksum, (string) $actualChecksum)) {
                return $this->restoreFailure($backup, 'Backup checksum mismatch.', $filePath);
            }
        }

        $tenantDb = $this->connectTenantDatabase($tenant);
        if (!$tenantDb || empty($tenantDb->conn_id) || !($tenantDb->conn_id instanceof mysqli)) {
            return $this->restoreFailure($backup, 'Tenant database connection is unavailable.', $filePath);
        }

        $startedAt = date('Y-m-d H:i:s');
        $this->CI->Kt_saas_model->update_backup_record((int) $backupId, [
            'status'     => 'running',
            'started_at' => $startedAt,
        ]);

        try {
            $sql = file_get_contents($filePath);
            if ($sql === false || trim($sql) === '') {
                throw new RuntimeException('Backup file is empty.');
            }

            $mysqli = $tenantDb->conn_id;
            $mysqli->begin_transaction();
            if (!$mysqli->multi_query($sql)) {
                throw new RuntimeException('Restore query failed: ' . $mysqli->error);
            }

            do {
                if ($result = $mysqli->store_result()) {
                    $result->free();
                }
            } while ($mysqli->more_results() && $mysqli->next_result());

            if ($mysqli->errno) {
                throw new RuntimeException('Restore execution failed: ' . $mysqli->error);
            }

            if (!$this->tenantDatabaseLooksHealthy($tenantDb)) {
                throw new RuntimeException('Restore completed but tenant database health validation failed.');
            }

            $mysqli->commit();

            $this->CI->Kt_saas_model->update_backup_record((int) $backupId, [
                'status'       => 'done',
                'completed_at' => date('Y-m-d H:i:s'),
            ]);

            $this->CI->Kt_saas_model->log_activity('backup.restored', 'warning', [
                'backup_id' => (int) $backupId,
                'tenant_id' => (int) $backup['tenant_id'],
                'file_path' => $filePath,
            ], (int) $backup['tenant_id']);

            return ['success' => true, 'tenant_id' => (int) $backup['tenant_id']];
        } catch (Throwable $e) {
            if (isset($tenantDb->conn_id) && $tenantDb->conn_id instanceof mysqli) {
                @ $tenantDb->conn_id->rollback();
            }
            $this->CI->Kt_saas_model->update_backup_record((int) $backupId, [
                'status'       => 'failed',
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
            $this->CI->Kt_saas_model->log_activity('backup.restore_failed', 'error', [
                'backup_id'     => (int) $backupId,
                'tenant_id'     => (int) $backup['tenant_id'],
                'file_path'     => $filePath,
                'error_message' => $e->getMessage(),
            ], (int) $backup['tenant_id']);
            log_message('error', 'KT SaaS restore failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage(), 'tenant_id' => (int) $backup['tenant_id']];
        } finally {
            if (method_exists($tenantDb, 'close')) {
                $tenantDb->close();
            }
        }
    }

    protected function buildDump($tenantDb, $databaseName)
    {
        $tables = $tenantDb->list_tables();
        $lines = [];
        $lines[] = '-- KT SaaS tenant backup';
        $lines[] = '-- Database: ' . $databaseName;
        $lines[] = '-- Created at: ' . date('Y-m-d H:i:s');
        $lines[] = 'SET FOREIGN_KEY_CHECKS=0;';
        $lines[] = '';

        foreach ($tables as $table) {
            $table = (string) $table;
            $createRow = $tenantDb->query('SHOW CREATE TABLE `' . $table . '`')->row_array();
            $createSql = $createRow['Create Table'] ?? array_values($createRow)[1] ?? null;
            if (!$createSql) {
                continue;
            }

            $lines[] = 'DROP TABLE IF EXISTS `' . $table . '`;';
            $lines[] = $createSql . ';';

            $escapedTable = str_replace('`', '``', $table);
            $rows = $tenantDb->query('SELECT * FROM `' . $escapedTable . '`')->result_array();
            if ($rows) {
                $columns = array_keys($rows[0]);
                $columnSql = '`' . implode('`,`', $columns) . '`';

                foreach (array_chunk($rows, 100) as $chunk) {
                    $valueSets = [];
                    foreach ($chunk as $row) {
                        $values = [];
                        foreach ($columns as $column) {
                            $value = $row[$column];
                            if ($value === null) {
                                $values[] = 'NULL';
                            } else {
                                $values[] = $tenantDb->escape($value);
                            }
                        }
                        $valueSets[] = '(' . implode(',', $values) . ')';
                    }

                    $lines[] = 'INSERT INTO `' . $table . '` (' . $columnSql . ') VALUES ' . implode(',', $valueSets) . ';';
                }
            }

            $lines[] = '';
        }

        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }

    protected function backupDirectoryForTenant(array $tenant)
    {
        $base = module_dir_path(KT_SAAS_MODULE, 'storage/backups');
        return $base . DIRECTORY_SEPARATOR . strtolower((string) ($tenant['tenant_code'] ?? ('tenant_' . (int) $tenant['id'])));
    }

    public function cleanupExpiredBackups($days = null)
    {
        $days = $days === null
            ? max((int) kt_saas_get_option('kt_saas_backup_retention_days', '30'), 1)
            : max((int) $days, 1);

        $result = $this->CI->Kt_saas_model->cleanup_expired_backups($days);
        $this->CI->Kt_saas_model->log_activity('backup.retention_cleanup', 'info', [
            'retention_days' => $days,
            'deleted'        => (int) ($result['deleted'] ?? 0),
            'cutoff'         => (string) ($result['cutoff'] ?? ''),
        ]);

        return $result;
    }

    protected function connectTenantDatabase(array $tenant)
    {
        $dbName = trim((string) ($tenant['db_name'] ?? ''));
        $dbHost = trim((string) ($tenant['db_host'] ?? ''));
        $dbPort = trim((string) ($tenant['db_port'] ?? '3306'));
        $dbUser = trim((string) ($tenant['db_user'] ?? ''));
        $dbPasswordEncrypted = $tenant['db_password_encrypted'] ?? null;
        if ($dbName === '' || $dbHost === '' || $dbUser === '') {
            return null;
        }

        $password = $dbPasswordEncrypted ? $this->CI->encryption->decrypt($dbPasswordEncrypted) : '';
        if ($password === false) {
            return null;
        }

        $config = [
            'dsn'          => '',
            'hostname'     => $dbHost,
            'username'     => $dbUser,
            'password'     => $password ?: '',
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

        $db = $this->CI->load->database($config, true);
        if (!$db || !$db->initialize()) {
            return null;
        }

        return $db;
    }

    protected function isSafeBackupPath($filePath)
    {
        $base = realpath(module_dir_path(KT_SAAS_MODULE, 'storage/backups'));
        $realFile = realpath($filePath);
        if ($base === false || $realFile === false) {
            return false;
        }

        return strpos($realFile, $base . DIRECTORY_SEPARATOR) === 0 || $realFile === $base;
    }

    protected function restoreFailure(array $backup, $message, $filePath = '')
    {
        $this->CI->Kt_saas_model->log_activity('backup.restore_failed', 'error', [
            'backup_id'     => (int) ($backup['id'] ?? 0),
            'tenant_id'     => (int) ($backup['tenant_id'] ?? 0),
            'file_path'     => (string) $filePath,
            'error_message' => (string) $message,
        ], !empty($backup['tenant_id']) ? (int) $backup['tenant_id'] : null);

        return [
            'success'   => false,
            'message'   => (string) $message,
            'tenant_id' => (int) ($backup['tenant_id'] ?? 0),
        ];
    }

    protected function tenantDatabaseLooksHealthy($tenantDb)
    {
        if (!$tenantDb->table_exists(db_prefix() . 'staff') || !$tenantDb->table_exists(db_prefix() . 'modules')) {
            return false;
        }

        return true;
    }

    protected function dispatchBackupEmail($eventKey, array $context)
    {
        if (!function_exists('kt_saas_send_email_event')) {
            require_once module_dir_path('kt_saas', 'helpers/kt_saas_helper.php');
        }
        if (!function_exists('kt_saas_send_email_event') || !function_exists('kt_saas_landlord_ops_email')) {
            return;
        }

        $dedupeKey = (string) ($context['dedupe_key'] ?? ($eventKey . '|' . ($context['backup_id'] ?? 0)));
        kt_saas_send_email_event($eventKey, $context, [
            'event_key' => $eventKey,
            'dedupe_key' => $dedupeKey,
        ]);
    }
}
