<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_matbao_invoice_model extends App_Model
{
    private $landlordDb;
    private $fallbackLandlordDb;
    private $verifiedCentralDb;

    public function __construct()
    {
        parent::__construct();
        $this->load->helper(KT_MATBAO_INVOICE_MODULE . '/kt_matbao_invoice');
        $this->landlordDb = $this->config->item('kt_saas_landlord_db') ?: $this->db;
        $this->fallbackLandlordDb = null;
        $this->verifiedCentralDb = null;
    }

    private function dbName($dbObj)
    {
        if (!is_object($dbObj)) {
            return '';
        }
        if (isset($dbObj->database) && is_string($dbObj->database) && $dbObj->database !== '') {
            return $dbObj->database;
        }
        try {
            $row = $dbObj->query('SELECT DATABASE() AS db_name')->row_array();
            return (string) ($row['db_name'] ?? '');
        } catch (Throwable $e) {
            return '';
        }
    }

    private function hasCoreMatbaoTables($dbObj)
    {
        if (!is_object($dbObj) || !method_exists($dbObj, 'table_exists')) {
            return false;
        }
        return $dbObj->table_exists(db_prefix() . 'kt_matbao_invoice_settings')
            && $dbObj->table_exists(db_prefix() . 'kt_matbao_invoice_records')
            && $dbObj->table_exists(db_prefix() . 'kt_saas_tenant_addons');
    }

    private function centralDb()
    {
        if (is_object($this->verifiedCentralDb) && $this->hasCoreMatbaoTables($this->verifiedCentralDb)) {
            return $this->verifiedCentralDb;
        }

        $isTenantSchema = function ($dbObj) {
            if (!is_object($dbObj)) {
                return false;
            }
            $databaseName = $this->dbName($dbObj);
            if (function_exists('kt_saas_is_tenant_runtime')
                && kt_saas_is_tenant_runtime()
                && isset($this->db)
                && $dbObj === $this->db) {
                return true;
            }
            return $databaseName !== '' && stripos($databaseName, 'tenant_') !== false;
        };

        // Preferred: landlord DB injected by KT SaaS tenant bootstrap.
        if (is_object($this->landlordDb) && !$isTenantSchema($this->landlordDb) && $this->hasCoreMatbaoTables($this->landlordDb)) {
            $this->verifiedCentralDb = $this->landlordDb;
            return $this->landlordDb;
        }

        $cfgDb = $this->config->item('kt_saas_landlord_db');
        if (is_object($cfgDb) && !$isTenantSchema($cfgDb) && $this->hasCoreMatbaoTables($cfgDb)) {
            $this->landlordDb = $cfgDb;
            $this->verifiedCentralDb = $this->landlordDb;
            return $this->landlordDb;
        }

        // Safety fallback:
        // In tenant runtime, $this->db may point to tenant schema.
        // MatBao + SaaS tables are landlord-scoped, so reconnect explicitly.
        if ($this->fallbackLandlordDb === null) {
            $databaseName = $this->dbName($this->db);

            if (function_exists('kt_saas_is_tenant_runtime') && kt_saas_is_tenant_runtime()
                && stripos($databaseName, 'tenant_') !== false) {
                $defaultDbName = defined('APP_DB_NAME') ? APP_DB_NAME : '';
                $candidateDbNames = array_values(array_unique(array_filter([
                    $defaultDbName,
                    preg_replace('/_tenant_.*/i', '', $databaseName),
                ])));

                $params = [
                    'hostname' => defined('APP_DB_HOSTNAME') ? APP_DB_HOSTNAME : 'localhost',
                    'username' => defined('APP_DB_USERNAME') ? APP_DB_USERNAME : '',
                    'password' => defined('APP_DB_PASSWORD') ? APP_DB_PASSWORD : '',
                    'database' => $databaseName,
                    'dbdriver' => defined('APP_DB_DRIVER') ? APP_DB_DRIVER : 'mysqli',
                    'dbprefix' => db_prefix(),
                    'pconnect' => false,
                    'db_debug' => (ENVIRONMENT !== 'production'),
                    'cache_on' => false,
                    'char_set' => defined('APP_DB_CHARSET') ? APP_DB_CHARSET : 'utf8mb4',
                    'dbcollat' => defined('APP_DB_COLLATION') ? APP_DB_COLLATION : 'utf8mb4_unicode_ci',
                ];
                foreach ($candidateDbNames as $dbNameCandidate) {
                    $params['database'] = $dbNameCandidate;
                    try {
                        $candidate = $this->load->database($params, true);
                        if ($this->hasCoreMatbaoTables($candidate)) {
                            $this->fallbackLandlordDb = $candidate;
                            $this->landlordDb = $candidate;
                            $this->verifiedCentralDb = $candidate;
                            return $candidate;
                        }
                    } catch (Throwable $e) {
                        // Try next candidate.
                    }
                }
            }
        }

        if ($this->fallbackLandlordDb !== null && !$isTenantSchema($this->fallbackLandlordDb) && $this->hasCoreMatbaoTables($this->fallbackLandlordDb)) {
            $this->verifiedCentralDb = $this->fallbackLandlordDb;
            return $this->fallbackLandlordDb;
        }

        if ($this->hasCoreMatbaoTables($this->db)) {
            $this->verifiedCentralDb = $this->db;
            return $this->db;
        }

        return $this->fallbackLandlordDb ?: $this->db;
    }

    private function ensureSplitAccountTables()
    {
        $db = $this->centralDb();
        if (!is_object($db) || !method_exists($db, 'table_exists')) {
            return;
        }

        $charset = isset($db->char_set) && $db->char_set ? $db->char_set : 'utf8mb4';

        $hddtTable = db_prefix() . 'kt_matbao_invoice_hddt_accounts';
        if (!$db->table_exists($hddtTable)) {
            $db->query("CREATE TABLE IF NOT EXISTS `{$hddtTable}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id` BIGINT UNSIGNED NULL,
                `account_scope` VARCHAR(20) NOT NULL DEFAULT 'landlord',
                `environment` VARCHAR(20) NOT NULL DEFAULT 'demo',
                `base_url` VARCHAR(255) NOT NULL DEFAULT '',
                `mst` VARCHAR(50) NOT NULL DEFAULT '',
                `username` VARCHAR(191) NOT NULL DEFAULT '',
                `password_encrypted` TEXT NULL,
                `access_token_encrypted` TEXT NULL,
                `token_expired_at` DATETIME NULL,
                `default_khmshdon` VARCHAR(100) NULL,
                `default_khhdon` VARCHAR(100) NULL,
                `default_year` INT NULL,
                `shared_account_enabled` TINYINT(1) NOT NULL DEFAULT 0,
                `allow_tenant_override` TINYINT(1) NOT NULL DEFAULT 0,
                `fallback_policy` VARCHAR(30) NOT NULL DEFAULT 'block',
                `auto_issue` TINYINT(1) NOT NULL DEFAULT 0,
                `auto_sign_by_hddt` TINYINT(1) NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 0,
                `last_test_status` VARCHAR(30) NULL,
                `last_test_message` TEXT NULL,
                `last_test_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_hddt_scope_tenant` (`account_scope`,`tenant_id`),
                KEY `idx_hddt_tenant_id` (`tenant_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset};");
        }

        $caTable = db_prefix() . 'kt_matbao_invoice_ca_accounts';
        if (!$db->table_exists($caTable)) {
            $db->query("CREATE TABLE IF NOT EXISTS `{$caTable}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id` BIGINT UNSIGNED NULL,
                `account_scope` VARCHAR(20) NOT NULL DEFAULT 'landlord',
                `environment` VARCHAR(20) NOT NULL DEFAULT 'demo',
                `base_url` VARCHAR(255) NOT NULL DEFAULT '',
                `taxcode` VARCHAR(50) NOT NULL DEFAULT '',
                `username` VARCHAR(191) NOT NULL DEFAULT '',
                `password_encrypted` TEXT NULL,
                `access_token_encrypted` TEXT NULL,
                `token_expired_at` DATETIME NULL,
                `cert_subject` VARCHAR(255) NULL,
                `cert_serial` VARCHAR(120) NULL,
                `cert_valid_from` DATETIME NULL,
                `cert_valid_to` DATETIME NULL,
                `hsm_package_code` VARCHAR(120) NULL,
                `hsm_order_id` VARCHAR(120) NULL,
                `hsm_status` VARCHAR(40) NOT NULL DEFAULT 'not_registered',
                `signing_mode` VARCHAR(40) NOT NULL DEFAULT 'hddt_sign_invoice',
                `is_active` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_ca_scope_tenant` (`account_scope`,`tenant_id`),
                KEY `idx_ca_tenant_id` (`tenant_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset};");
        }
    }

    private function normalizeFallbackPolicy($value)
    {
        $v = strtolower(trim((string) $value));
        if ($v === 'use_landlord') {
            return 'use_landlord';
        }
        return 'block';
    }

    public function get_settings($tenantId = null, $scope = 'landlord')
    {
        $hddt = $this->get_hddt_account($tenantId, $scope);
        if ($hddt) {
            // Compatibility shape for existing invoice client + controllers.
            $hddt['invoice_base_url'] = (string) ($hddt['base_url'] ?? '');
            $hddt['sign_base_url'] = (string) (($this->get_ca_account($tenantId, $scope)['base_url'] ?? ''));
            $hddt['scope'] = $scope;
            return $hddt;
        }

        // Legacy fallback.
        return $this->centralDb()->where('scope', $scope)->where('tenant_id', $tenantId)->get(db_prefix() . 'kt_matbao_invoice_settings')->row_array();
    }

    public function save_settings(array $data, $tenantId = null, $scope = 'landlord')
    {
        // New split storage (HDDT + CA), still compatible with old form payload.
        $this->save_hddt_account($data, $tenantId, $scope);
        $this->save_ca_account($data, $tenantId, $scope);

        // Keep legacy row synchronized for backward compatibility.
        $now = date('Y-m-d H:i:s');
        $existing = $this->get_settings($tenantId, $scope);
        $resolvedSignBaseUrl = trim((string) ($data['ca_base_url'] ?? ($data['sign_base_url'] ?? '')));
        $fallbackPolicy = $this->normalizeFallbackPolicy($data['fallback_policy'] ?? 'block');
        $payload = [
            'tenant_id' => $tenantId,
            'scope' => $scope,
            'environment' => in_array(($data['environment'] ?? 'demo'), ['demo', 'production'], true) ? $data['environment'] : 'demo',
            'invoice_base_url' => trim((string) ($data['invoice_base_url'] ?? '')),
            'sign_base_url' => $resolvedSignBaseUrl,
            'mst' => trim((string) ($data['mst'] ?? '')),
            'username' => trim((string) ($data['username'] ?? '')),
            'default_khmshdon' => trim((string) ($data['default_khmshdon'] ?? '')),
            'default_khhdon' => trim((string) ($data['default_khhdon'] ?? '')),
            'default_year' => !empty($data['default_year']) ? (int) $data['default_year'] : (int) date('Y'),
            'shared_account_enabled' => !empty($data['shared_account_enabled']) ? 1 : 0,
            'allow_tenant_override' => !empty($data['allow_tenant_override']) ? 1 : 0,
            'fallback_policy' => $fallbackPolicy,
            'auto_issue' => !empty($data['auto_issue']) ? 1 : 0,
            'auto_sign' => !empty($data['auto_sign']) ? 1 : 0,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'updated_at' => $now,
        ];

        $passwordRaw = trim((string) ($data['password'] ?? ''));
        if ($passwordRaw !== '') {
            $payload['password_encrypted'] = kt_matbao_invoice_encrypt($passwordRaw);
        } elseif (!$existing || empty($existing['password_encrypted'])) {
            $payload['password_encrypted'] = null;
        }

        if ($existing) {
            $this->centralDb()->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_matbao_invoice_settings', $payload);
            return (int) $existing['id'];
        }

        $payload['created_at'] = $now;
        $this->centralDb()->insert(db_prefix() . 'kt_matbao_invoice_settings', $payload);
        return (int) $this->centralDb()->insert_id();
    }

    public function get_hddt_account($tenantId = null, $scope = 'landlord')
    {
        $this->ensureSplitAccountTables();
        return $this->centralDb()
            ->where('account_scope', (string) $scope)
            ->where('tenant_id', $tenantId)
            ->get(db_prefix() . 'kt_matbao_invoice_hddt_accounts')
            ->row_array();
    }

    public function save_hddt_account(array $data, $tenantId = null, $scope = 'landlord')
    {
        $this->ensureSplitAccountTables();
        $now = date('Y-m-d H:i:s');
        $existing = $this->get_hddt_account($tenantId, $scope);
        $fallbackPolicy = $this->normalizeFallbackPolicy($data['fallback_policy'] ?? 'block');
        $payload = [
            'tenant_id' => $tenantId,
            'account_scope' => $scope,
            'environment' => in_array(($data['environment'] ?? 'demo'), ['demo', 'production'], true) ? $data['environment'] : 'demo',
            'base_url' => trim((string) ($data['invoice_base_url'] ?? '')),
            'mst' => trim((string) ($data['mst'] ?? '')),
            'username' => trim((string) ($data['username'] ?? '')),
            'default_khmshdon' => trim((string) ($data['default_khmshdon'] ?? '')),
            'default_khhdon' => trim((string) ($data['default_khhdon'] ?? '')),
            'default_year' => !empty($data['default_year']) ? (int) $data['default_year'] : (int) date('Y'),
            'shared_account_enabled' => !empty($data['shared_account_enabled']) ? 1 : 0,
            'allow_tenant_override' => !empty($data['allow_tenant_override']) ? 1 : 0,
            'fallback_policy' => $fallbackPolicy,
            'auto_issue' => !empty($data['auto_issue']) ? 1 : 0,
            'auto_sign_by_hddt' => !empty($data['auto_sign']) ? 1 : 0,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'updated_at' => $now,
        ];
        $passwordRaw = trim((string) ($data['password'] ?? ''));
        if ($passwordRaw !== '') {
            $payload['password_encrypted'] = kt_matbao_invoice_encrypt($passwordRaw);
        }

        if ($existing) {
            $this->centralDb()->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_matbao_invoice_hddt_accounts', $payload);
            return (int) $existing['id'];
        }
        $payload['created_at'] = $now;
        $this->centralDb()->insert(db_prefix() . 'kt_matbao_invoice_hddt_accounts', $payload);
        return (int) $this->centralDb()->insert_id();
    }

    public function get_ca_account($tenantId = null, $scope = 'landlord')
    {
        $this->ensureSplitAccountTables();
        return $this->centralDb()
            ->where('account_scope', (string) $scope)
            ->where('tenant_id', $tenantId)
            ->get(db_prefix() . 'kt_matbao_invoice_ca_accounts')
            ->row_array();
    }

    public function get_ca_accounts_expiring_soon($days = 7)
    {
        $this->ensureSplitAccountTables();
        $days = max((int) $days, 1);
        $now = date('Y-m-d H:i:s');
        $cutoff = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));

        return $this->centralDb()
            ->where('is_active', 1)
            ->where('token_expired_at IS NOT NULL', null, false)
            ->where('token_expired_at >=', $now)
            ->where('token_expired_at <=', $cutoff)
            ->get(db_prefix() . 'kt_matbao_invoice_ca_accounts')
            ->result_array();
    }

    public function save_ca_account(array $data, $tenantId = null, $scope = 'landlord')
    {
        $this->ensureSplitAccountTables();
        $now = date('Y-m-d H:i:s');
        $existing = $this->get_ca_account($tenantId, $scope);
        $payload = [
            'tenant_id' => $tenantId,
            'account_scope' => $scope,
            'environment' => in_array(($data['environment'] ?? 'demo'), ['demo', 'production'], true) ? $data['environment'] : 'demo',
            'base_url' => trim((string) ($data['ca_base_url'] ?? ($data['sign_base_url'] ?? ''))),
            'taxcode' => trim((string) ($data['ca_taxcode'] ?? '')),
            'username' => trim((string) ($data['ca_username'] ?? '')),
            'signing_mode' => trim((string) ($data['signing_mode'] ?? 'hddt_sign_invoice')),
            'is_active' => !empty($data['ca_is_active']) ? 1 : 0,
            'updated_at' => $now,
        ];
        $passwordRaw = trim((string) ($data['ca_password'] ?? ''));
        if ($passwordRaw !== '') {
            $payload['password_encrypted'] = kt_matbao_invoice_encrypt($passwordRaw);
        }

        if ($existing) {
            $this->centralDb()->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_matbao_invoice_ca_accounts', $payload);
            return (int) $existing['id'];
        }
        $payload['created_at'] = $now;
        $this->centralDb()->insert(db_prefix() . 'kt_matbao_invoice_ca_accounts', $payload);
        return (int) $this->centralDb()->insert_id();
    }

    public function update_ca_access_token($accountId, $accessToken, $expiredAt = null)
    {
        $token = trim((string) $accessToken);
        if ((int) $accountId < 1 || $token === '') {
            return false;
        }
        $existing = $this->get_ca_account(null, 'landlord');
        if ($existing && (int) $existing['id'] !== (int) $accountId) {
            $existing = $this->centralDb()->where('id', (int) $accountId)->get(db_prefix() . 'kt_matbao_invoice_ca_accounts')->row_array();
        }
        if (!$existing) {
            $existing = $this->centralDb()->where('id', (int) $accountId)->get(db_prefix() . 'kt_matbao_invoice_ca_accounts')->row_array();
        }
        $expired = trim((string) $expiredAt);
        $resolvedExpire = $expired !== '' ? date('Y-m-d H:i:s', strtotime($expired)) : date('Y-m-d H:i:s', strtotime('+6 hours'));
        if ($resolvedExpire === '1970-01-01 00:00:00') {
            $resolvedExpire = date('Y-m-d H:i:s', strtotime('+6 hours'));
        }
        $this->centralDb()->where('id', (int) $accountId)->update(db_prefix() . 'kt_matbao_invoice_ca_accounts', [
            'access_token_encrypted' => kt_matbao_invoice_encrypt($token),
            'token_expired_at' => $resolvedExpire,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        if ($existing && (string) ($existing['hsm_status'] ?? '') !== 'active') {
            $this->maybeDispatchHsmActivatedEmail($existing, $resolvedExpire);
        }
        return $this->centralDb()->affected_rows() >= 0;
    }

    public function update_test_result($settingsId, $status, $message)
    {
        $this->centralDb()->where('id', (int) $settingsId)->update(db_prefix() . 'kt_matbao_invoice_settings', [
            'last_test_status' => $status,
            'last_test_message' => $message,
            'last_test_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function update_access_token($settingsId, $accessToken, $expiredAt = null)
    {
        $token = trim((string) $accessToken);
        if ((int) $settingsId < 1 || $token === '') {
            return false;
        }

        $expired = trim((string) $expiredAt);
        $resolvedExpire = $expired !== '' ? date('Y-m-d H:i:s', strtotime($expired)) : date('Y-m-d H:i:s', strtotime('+6 hours'));
        if ($resolvedExpire === '1970-01-01 00:00:00') {
            $resolvedExpire = date('Y-m-d H:i:s', strtotime('+6 hours'));
        }

        $this->centralDb()->where('id', (int) $settingsId)->update(db_prefix() . 'kt_matbao_invoice_settings', [
            'access_token_encrypted' => kt_matbao_invoice_encrypt($token),
            'token_expired_at' => $resolvedExpire,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->centralDb()->affected_rows() >= 0;
    }

    public function replace_templates($tenantId, $scope, $year, array $rows)
    {
        $this->centralDb()->where('tenant_id', $tenantId)->where('scope', $scope)->where('year', (int) $year)->delete(db_prefix() . 'kt_matbao_invoice_templates');
        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $this->centralDb()->insert(db_prefix() . 'kt_matbao_invoice_templates', [
                'tenant_id' => $tenantId,
                'scope' => $scope,
                'year' => (int) $year,
                'khmshdon' => (string) ($row['KHMSHDon'] ?? $row['khmshDon'] ?? $row['khmshdon'] ?? ''),
                'khhdon' => (string) ($row['KHHDon'] ?? $row['khhDon'] ?? $row['khhdon'] ?? ''),
                'thdon' => (string) ($row['THDon'] ?? $row['thDon'] ?? $row['thdon'] ?? ''),
                'sluong' => isset($row['sLuong']) ? (int) $row['sLuong'] : (isset($row['SLuong']) ? (int) $row['SLuong'] : (isset($row['sluong']) ? (int) $row['sluong'] : null)),
                'clai' => isset($row['cLai']) ? (int) $row['cLai'] : (isset($row['CLai']) ? (int) $row['CLai'] : (isset($row['clai']) ? (int) $row['clai'] : null)),
                'raw_json' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'synced_at' => $now,
            ]);
        }
    }

    public function get_templates($tenantId = null, $scope = 'landlord')
    {
        return $this->centralDb()->where('tenant_id', $tenantId)->where('scope', $scope)->order_by('year', 'desc')->order_by('id', 'desc')->get(db_prefix() . 'kt_matbao_invoice_templates')->result_array();
    }

    public function log_api(array $payload)
    {
        $this->centralDb()->insert(db_prefix() . 'kt_matbao_invoice_logs', [
            'tenant_id' => $payload['tenant_id'] ?? null,
            'record_id' => $payload['record_id'] ?? null,
            'action' => (string) ($payload['action'] ?? ''),
            'endpoint' => (string) ($payload['endpoint'] ?? ''),
            'method' => (string) ($payload['method'] ?? ''),
            'request_payload' => isset($payload['request_payload']) ? json_encode($payload['request_payload'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null,
            'response_payload' => isset($payload['response_payload']) ? json_encode($payload['response_payload'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null,
            'http_code' => isset($payload['http_code']) ? (int) $payload['http_code'] : null,
            'success' => !empty($payload['success']) ? 1 : 0,
            'error_code' => (string) ($payload['error_code'] ?? ''),
            'error_message' => (string) ($payload['error_message'] ?? ''),
            'latency_ms' => isset($payload['latency_ms']) ? (int) $payload['latency_ms'] : null,
            'created_by' => is_staff_logged_in() ? get_staff_user_id() : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->centralDb()->insert_id();
    }

    public function get_logs($tenantId = null, $limit = 200)
    {
        if ($tenantId !== null) {
            $this->centralDb()->where('tenant_id', (int) $tenantId);
        }
        return $this->centralDb()->order_by('id', 'desc')->limit((int) $limit)->get(db_prefix() . 'kt_matbao_invoice_logs')->result_array();
    }

    public function log_webhook(array $payload)
    {
        $this->centralDb()->insert(db_prefix() . 'kt_matbao_invoice_webhook_logs', [
            'tenant_id' => $payload['tenant_id'] ?? null,
            'record_id' => $payload['record_id'] ?? null,
            'provider' => (string) ($payload['provider'] ?? 'matbao'),
            'payload' => isset($payload['payload']) ? json_encode($payload['payload'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null,
            'inv_id' => (string) ($payload['inv_id'] ?? ''),
            'document_id' => (string) ($payload['document_id'] ?? ''),
            'ma_so_hdon' => (string) ($payload['ma_so_hdon'] ?? ''),
            'ma_tra_cuu' => (string) ($payload['ma_tra_cuu'] ?? ''),
            'status_code' => (string) ($payload['status_code'] ?? ''),
            'status_name' => (string) ($payload['status_name'] ?? ''),
            'processed' => !empty($payload['processed']) ? 1 : 0,
            'error_message' => (string) ($payload['error_message'] ?? ''),
            'received_at' => date('Y-m-d H:i:s'),
            'processed_at' => !empty($payload['processed']) ? date('Y-m-d H:i:s') : null,
        ]);

        return (int) $this->centralDb()->insert_id();
    }

    public function mark_webhook_processed($id, $recordId = null)
    {
        $this->centralDb()->where('id', (int) $id)->update(db_prefix() . 'kt_matbao_invoice_webhook_logs', [
            'processed' => 1,
            'record_id' => $recordId,
            'processed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function get_webhook_logs($tenantId = null, $limit = 200)
    {
        if ($tenantId !== null) {
            $this->centralDb()->where('tenant_id', (int) $tenantId);
        }
        return $this->centralDb()->order_by('id', 'desc')->limit((int) $limit)->get(db_prefix() . 'kt_matbao_invoice_webhook_logs')->result_array();
    }

    public function get_records($tenantId = null, $limit = 200)
    {
        if ($tenantId !== null) {
            $this->centralDb()->where('tenant_id', (int) $tenantId);
        }
        return $this->centralDb()->order_by('id', 'desc')->limit((int) $limit)->get(db_prefix() . 'kt_matbao_invoice_records')->result_array();
    }

    public function get_record($id)
    {
        return $this->centralDb()->where('id', (int) $id)->get(db_prefix() . 'kt_matbao_invoice_records')->row_array();
    }

    public function get_record_by_source($sourceType, $sourceId, $tenantId = null)
    {
        $this->centralDb()->where('source_type', (string) $sourceType)->where('source_id', (int) $sourceId);
        if ($tenantId !== null) {
            $this->centralDb()->where('tenant_id', (int) $tenantId);
        }
        return $this->centralDb()->order_by('id', 'desc')->get(db_prefix() . 'kt_matbao_invoice_records')->row_array();
    }

    public function save_record(array $payload, array $items = [])
    {
        $now = date('Y-m-d H:i:s');
        $recordId = !empty($payload['id']) ? (int) $payload['id'] : 0;
        unset($payload['id']);
        $payload['updated_at'] = $now;
        if ($recordId > 0) {
            $this->centralDb()->where('id', $recordId)->update(db_prefix() . 'kt_matbao_invoice_records', $payload);
        } else {
            $payload['created_at'] = $now;
            $this->centralDb()->insert(db_prefix() . 'kt_matbao_invoice_records', $payload);
            $recordId = (int) $this->centralDb()->insert_id();
        }

        if (!empty($items)) {
            $this->centralDb()->where('record_id', $recordId)->delete(db_prefix() . 'kt_matbao_invoice_items_snapshot');
            foreach ($items as $row) {
                $this->centralDb()->insert(db_prefix() . 'kt_matbao_invoice_items_snapshot', [
                    'record_id' => $recordId,
                    'item_source_id' => $row['item_source_id'] ?? null,
                    'tchat' => $row['tchat'] ?? null,
                    'stt' => $row['stt'] ?? null,
                    'mhhdvu' => $row['mhhdvu'] ?? null,
                    'thhdvu' => $row['thhdvu'] ?? null,
                    'dvtinh' => $row['dvtinh'] ?? null,
                    'sluong' => (float) ($row['sluong'] ?? 0),
                    'dgia' => (float) ($row['dgia'] ?? 0),
                    'thtien_chua_ck' => (float) ($row['thtien_chua_ck'] ?? 0),
                    'tlckhau' => (float) ($row['tlckhau'] ?? 0),
                    'stckhau' => (float) ($row['stckhau'] ?? 0),
                    'thtien' => (float) ($row['thtien'] ?? 0),
                    'tsuat' => (float) ($row['tsuat'] ?? 0),
                    'tthue' => (float) ($row['tthue'] ?? 0),
                    'tgtien' => (float) ($row['tgtien'] ?? 0),
                    'raw_json' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                    'created_at' => $now,
                ]);
            }
        }

        return $recordId;
    }

    public function get_perfex_invoice_bundle($invoiceId)
    {
        $invoice = $this->db->where('id', (int) $invoiceId)->get(db_prefix() . 'invoices')->row_array();
        if (!$invoice) {
            return null;
        }

        $client = null;
        if (!empty($invoice['clientid'])) {
            $client = $this->db->where('userid', (int) $invoice['clientid'])->get(db_prefix() . 'clients')->row_array();
        }

        $items = $this->db
            ->where('rel_id', (int) $invoiceId)
            ->where('rel_type', 'invoice')
            ->order_by('item_order', 'asc')
            ->get(db_prefix() . 'itemable')
            ->result_array();

        $itemIds = [];
        foreach ($items as $it) {
            $id = (int) ($it['id'] ?? 0);
            if ($id > 0) {
                $itemIds[] = $id;
            }
        }
        $taxRows = [];
        if (!empty($itemIds)) {
            $taxRows = $this->db
                ->where('rel_id', (int) $invoiceId)
                ->where('rel_type', 'invoice')
                ->where_in('itemid', $itemIds)
                ->get(db_prefix() . 'item_tax')
                ->result_array();
        }
        $taxByItem = [];
        foreach ($taxRows as $tx) {
            $itemId = (int) ($tx['itemid'] ?? 0);
            if ($itemId < 1) {
                continue;
            }
            if (!isset($taxByItem[$itemId])) {
                $taxByItem[$itemId] = [
                    'taxrate' => 0.0,
                    'taxname' => [],
                    'rows' => [],
                ];
            }
            $taxByItem[$itemId]['taxrate'] += (float) ($tx['taxrate'] ?? 0);
            if (!empty($tx['taxname'])) {
                $taxByItem[$itemId]['taxname'][] = (string) $tx['taxname'];
            }
            $taxByItem[$itemId]['rows'][] = $tx;
        }
        foreach ($items as &$it) {
            $itemId = (int) ($it['id'] ?? 0);
            if ($itemId > 0 && isset($taxByItem[$itemId])) {
                $it['taxrate'] = (float) $taxByItem[$itemId]['taxrate'];
                $it['taxname'] = implode(',', array_unique($taxByItem[$itemId]['taxname']));
                $it['tax_rows'] = $taxByItem[$itemId]['rows'];
            } else {
                $it['taxrate'] = 0.0;
                $it['taxname'] = '';
                $it['tax_rows'] = [];
            }
        }
        unset($it);

        return [
            'invoice' => $invoice,
            'client' => $client,
            'items' => $items,
        ];
    }

    public function resolve_tenant_effective_settings(array $tenant)
    {
        $tenantId = (int) ($tenant['id'] ?? 0);
        $tenantSettings = $tenantId > 0 ? $this->get_settings($tenantId, 'tenant') : null;
        $landlordSettings = $this->get_settings(null, 'landlord');

        $canTenantConfig = function_exists('kt_matbao_invoice_tenant_can_configure') ? kt_matbao_invoice_tenant_can_configure() : false;
        if ($canTenantConfig && !empty($tenantSettings['is_active'])) {
            return ['scope' => 'tenant', 'settings' => $tenantSettings];
        }

        $tenantAllowsShared = true;
        if ($tenantId > 0) {
            $tenantAllowsShared = $this->tenant_feature_allowed($tenantId, 'matbao_invoice.shared_landlord_account', true)
                || $this->tenant_feature_allowed($tenantId, 'kt_matbao_invoice.shared_landlord_account', false);
        }

        // Backward-compatible behavior:
        // - if landlord shared mode is enabled, allow all tenants with module access
        // - if plan explicitly enables shared_landlord_account, allow fallback to landlord config
        if (!empty($landlordSettings['is_active']) && (!empty($landlordSettings['shared_account_enabled']) || $tenantAllowsShared)) {
            return ['scope' => 'landlord', 'settings' => $landlordSettings];
        }

        return ['scope' => 'none', 'settings' => null];
    }

    public function resolve_tenant_effective_ca_account(array $tenant)
    {
        $tenantId = (int) ($tenant['id'] ?? 0);
        $tenantSettings = $tenantId > 0 ? $this->get_ca_account($tenantId, 'tenant') : null;
        $landlordSettings = $this->get_ca_account(null, 'landlord');

        $canTenantConfig = function_exists('kt_matbao_invoice_tenant_can_configure') ? kt_matbao_invoice_tenant_can_configure() : false;
        if ($canTenantConfig && !empty($tenantSettings['is_active'])) {
            return ['scope' => 'tenant', 'settings' => $tenantSettings];
        }

        $tenantAllowsShared = true;
        if ($tenantId > 0) {
            $tenantAllowsShared = $this->tenant_feature_allowed($tenantId, 'matbao_ca.shared_landlord_hsm', true);
        }
        if (!empty($landlordSettings['is_active']) && $tenantAllowsShared) {
            return ['scope' => 'landlord', 'settings' => $landlordSettings];
        }

        return ['scope' => 'none', 'settings' => null];
    }

    public function find_record_for_webhook(array $payload, $type = 'invoice')
    {
        $maSo = trim((string) ($payload['MaSoHDon'] ?? $payload['ma_so_hdon'] ?? ''));
        $maTraCuu = trim((string) ($payload['MaTraCuu'] ?? $payload['Fkey'] ?? $payload['ma_tra_cuu'] ?? ''));
        $invId = trim((string) ($payload['InvID'] ?? $payload['inv_id'] ?? ''));
        $documentId = trim((string) ($payload['DocumentId'] ?? $payload['document_id'] ?? ''));
        $fkey = trim((string) ($payload['Fkey'] ?? $payload['fkey'] ?? ''));

        if ($maSo !== '') {
            $row = $this->centralDb()->where('ma_so_hdon', $maSo)->get(db_prefix() . 'kt_matbao_invoice_records')->row_array();
            if ($row) return $row;
        }
        if ($maTraCuu !== '') {
            $row = $this->centralDb()->where('ma_tra_cuu', $maTraCuu)->get(db_prefix() . 'kt_matbao_invoice_records')->row_array();
            if ($row) return $row;
        }
        if ($invId !== '') {
            $row = $this->centralDb()->where('inv_id', $invId)->get(db_prefix() . 'kt_matbao_invoice_records')->row_array();
            if ($row) return $row;
        }
        if ($fkey !== '') {
            $row = $this->centralDb()->where('fkey', $fkey)->get(db_prefix() . 'kt_matbao_invoice_records')->row_array();
            if ($row) return $row;
        }
        if ($type === 'signing' && $documentId !== '') {
            $row = $this->centralDb()
                ->group_start()
                    ->where('ca_document_id', $documentId)
                    ->or_where('fkey', $documentId)
                    ->or_where('inv_id', $documentId)
                    ->or_where('mt_chieu', $documentId)
                ->group_end()
                ->order_by('id', 'desc')
                ->get(db_prefix() . 'kt_matbao_invoice_records')
                ->row_array();
            if ($row) return $row;
        }

        return null;
    }

    public function is_duplicate_webhook($provider, array $payload)
    {
        $provider = trim((string) $provider);
        $invId = trim((string) ($payload['InvID'] ?? $payload['inv_id'] ?? ''));
        $maSo = trim((string) ($payload['MaSoHDon'] ?? $payload['ma_so_hdon'] ?? ''));
        $maTra = trim((string) ($payload['MaTraCuu'] ?? $payload['Fkey'] ?? $payload['ma_tra_cuu'] ?? ''));
        $documentId = trim((string) ($payload['DocumentId'] ?? $payload['document_id'] ?? ''));
        $statusCode = trim((string) ($payload['MaTTHDon'] ?? $payload['DocumentStatus'] ?? ''));
        $statusName = trim((string) ($payload['TenTTHDon'] ?? ''));

        // Never classify as duplicate when no stable identifier is present.
        if ($invId === '' && $maSo === '' && $maTra === '' && $documentId === '') {
            return false;
        }

        $db = $this->centralDb();
        $db->where('provider', $provider);
        $hasAnyIdentityFilter = false;
        if ($invId !== '') {
            $db->where('inv_id', $invId);
            $hasAnyIdentityFilter = true;
        }
        if ($maSo !== '') {
            $db->where('ma_so_hdon', $maSo);
            $hasAnyIdentityFilter = true;
        }
        if ($maTra !== '') {
            $db->where('ma_tra_cuu', $maTra);
            $hasAnyIdentityFilter = true;
        }
        if ($documentId !== '') {
            $db->where('document_id', $documentId);
            $hasAnyIdentityFilter = true;
        }
        if ($statusCode !== '') {
            $db->where('status_code', $statusCode);
        }
        if ($statusName !== '') {
            $db->where('status_name', $statusName);
        }
        if (!$hasAnyIdentityFilter) {
            return false;
        }
        $db->where('received_at >=', date('Y-m-d H:i:s', strtotime('-1 day')));

        return (bool) $db->count_all_results(db_prefix() . 'kt_matbao_invoice_webhook_logs');
    }

    public function update_record_status_from_webhook($recordId, array $payload)
    {
        $this->centralDb()->where('id', (int) $recordId)->update(db_prefix() . 'kt_matbao_invoice_records', [
            'inv_id' => (string) ($payload['InvID'] ?? ''),
            'fkey' => (string) ($payload['Fkey'] ?? ''),
            'mccqt' => (string) ($payload['MCCQT'] ?? ''),
            'pattern' => (string) ($payload['Pattern'] ?? ''),
            'serial' => (string) ($payload['Serial'] ?? ''),
            'so' => (string) ($payload['SO'] ?? $payload['No'] ?? ''),
            'tax_status_code' => (string) ($payload['MaTTHDon'] ?? ''),
            'tax_status_name' => (string) ($payload['TenTTHDon'] ?? ''),
            'local_status' => 'issued',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function update_record_status_from_signing_webhook($recordId, array $payload)
    {
        $documentStatus = strtoupper(trim((string) ($payload['DocumentStatus'] ?? $payload['document_status'] ?? '')));
        $nextStatus = 'created';
        if (in_array($documentStatus, ['SIGNED', 'COMPLETED', 'DONE', 'SUCCESS'], true)) {
            $nextStatus = 'signed';
        } elseif (in_array($documentStatus, ['FAILED', 'ERROR', 'REJECTED'], true)) {
            $nextStatus = 'failed';
        }

        $this->centralDb()->where('id', (int) $recordId)->update(db_prefix() . 'kt_matbao_invoice_records', [
            'local_status' => $nextStatus,
            'ca_document_id' => (string) ($payload['DocumentId'] ?? ($payload['document_id'] ?? '')),
            'signing_provider_status' => $documentStatus,
            'tax_status_name' => (string) ($payload['DocumentStatus'] ?? ''),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function get_reseller_packages($serviceType = null)
    {
        if ($serviceType !== null) {
            $this->centralDb()->where('service_type', (string) $serviceType);
        }
        return $this->centralDb()->order_by('sort_order', 'asc')->order_by('id', 'asc')->get(db_prefix() . 'kt_saas_reseller_packages')->result_array();
    }

    public function save_reseller_package(array $data, $id = null)
    {
        $now = date('Y-m-d H:i:s');
        $payload = [
            'provider' => trim((string) ($data['provider'] ?? 'matbao')),
            'service_type' => trim((string) ($data['service_type'] ?? 'einvoice')),
            'package_code' => strtoupper(trim((string) ($data['package_code'] ?? ''))),
            'package_name' => trim((string) ($data['package_name'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
            'quantity' => (float) ($data['quantity'] ?? 1),
            'unit' => trim((string) ($data['unit'] ?? '')),
            'duration_days' => !empty($data['duration_days']) ? (int) $data['duration_days'] : null,
            'duration_months' => !empty($data['duration_months']) ? (int) $data['duration_months'] : null,
            'duration_years' => !empty($data['duration_years']) ? (int) $data['duration_years'] : null,
            'price' => (float) ($data['price'] ?? 0),
            'currency' => trim((string) ($data['currency'] ?? 'VND')),
            'unit_price' => (float) ($data['unit_price'] ?? 0),
            'setup_fee' => (float) ($data['setup_fee'] ?? 0),
            'is_stackable' => !empty($data['is_stackable']) ? 1 : 0,
            'requires_registration' => !empty($data['requires_registration']) ? 1 : 0,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'raw_metadata' => !empty($data['raw_metadata']) ? (string) $data['raw_metadata'] : null,
            'updated_at' => $now,
        ];

        if ($id) {
            $this->centralDb()->where('id', (int) $id)->update(db_prefix() . 'kt_saas_reseller_packages', $payload);
            return (int) $id;
        }

        $payload['created_at'] = $now;
        $this->centralDb()->insert(db_prefix() . 'kt_saas_reseller_packages', $payload);
        return (int) $this->centralDb()->insert_id();
    }

    public function get_orders($tenantId = null)
    {
        if ($tenantId !== null) {
            $this->centralDb()->where('tenant_id', (int) $tenantId);
        }
        return $this->centralDb()->order_by('id', 'desc')->get(db_prefix() . 'kt_saas_orders')->result_array();
    }

    public function get_order_items($orderId)
    {
        return $this->centralDb()->where('order_id', (int) $orderId)->order_by('id', 'asc')->get(db_prefix() . 'kt_saas_order_items')->result_array();
    }

    public function get_tenant_addons($tenantId = null)
    {
        if ($tenantId !== null) {
            $this->centralDb()->where('tenant_id', (int) $tenantId);
        }
        return $this->centralDb()->order_by('id', 'desc')->get(db_prefix() . 'kt_saas_tenant_addons')->result_array();
    }

    public function get_active_reseller_packages_for_tenant()
    {
        return $this->centralDb()
            ->where('provider', 'matbao')
            ->where('is_active', 1)
            ->order_by('sort_order', 'asc')
            ->order_by('id', 'asc')
            ->get(db_prefix() . 'kt_saas_reseller_packages')
            ->result_array();
    }

    public function create_tenant_addon_order($tenantId, array $items)
    {
        $tenantId = (int) $tenantId;
        if ($tenantId < 1 || empty($items)) {
            return ['success' => false, 'message' => 'Invalid tenant/order payload'];
        }

        $tenant = $this->centralDb()->where('id', $tenantId)->get(db_prefix() . 'kt_saas_tenants')->row_array();
        if (!$tenant) {
            return ['success' => false, 'message' => 'Tenant not found'];
        }

        $now = date('Y-m-d H:i:s');
        $subtotal = 0.0;
        $orderItems = [];

        foreach ($items as $input) {
            $packageId = (int) ($input['package_id'] ?? 0);
            $qty = (float) ($input['quantity'] ?? 0);
            if ($packageId < 1 || $qty <= 0) {
                continue;
            }

            $pkg = $this->centralDb()
                ->where('id', $packageId)
                ->where('provider', 'matbao')
                ->where('is_active', 1)
                ->get(db_prefix() . 'kt_saas_reseller_packages')
                ->row_array();
            if (!$pkg) {
                continue;
            }

            $unitPrice = (float) ($pkg['price'] ?? 0);
            $lineSubtotal = round($unitPrice * $qty, 2);
            $subtotal += $lineSubtotal;

            $itemType = ((string) ($pkg['service_type'] ?? 'einvoice') === 'hsm_signature') ? 'addon_hsm' : 'addon_einvoice';
            $orderItems[] = [
                'item_type' => $itemType,
                'ref_id' => $packageId,
                'item_code' => (string) ($pkg['package_code'] ?? ''),
                'item_name' => (string) ($pkg['package_name'] ?? ''),
                'description' => (string) ($pkg['description'] ?? ''),
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'subtotal' => $lineSubtotal,
                'tax_rate' => 0,
                'tax_amount' => 0,
                'total' => $lineSubtotal,
                'metadata_json' => json_encode(['service_type' => $pkg['service_type'] ?? 'einvoice'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            ];
        }

        if (empty($orderItems)) {
            return ['success' => false, 'message' => 'No valid package selected'];
        }

        $orderCode = 'MBADD-' . $tenantId . '-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $this->centralDb()->insert(db_prefix() . 'kt_saas_orders', [
            'tenant_id' => $tenantId,
            'customer_id' => null,
            'order_code' => $orderCode,
            'order_type' => 'addon_purchase',
            'status' => 'pending_payment',
            'subtotal' => round($subtotal, 2),
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => round($subtotal, 2),
            'currency' => 'VND',
            'payment_method' => null,
            'payment_status' => 'pending',
            'paid_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $orderId = (int) $this->centralDb()->insert_id();

        foreach ($orderItems as $row) {
            $row['order_id'] = $orderId;
            $row['created_at'] = $now;
            $this->centralDb()->insert(db_prefix() . 'kt_saas_order_items', $row);
        }

        return ['success' => true, 'order_id' => $orderId, 'order_code' => $orderCode];
    }

    public function get_order_by_tenant($orderId, $tenantId)
    {
        return $this->centralDb()
            ->where('id', (int) $orderId)
            ->where('tenant_id', (int) $tenantId)
            ->get(db_prefix() . 'kt_saas_orders')
            ->row_array();
    }

    public function get_order($orderId)
    {
        return $this->centralDb()
            ->where('id', (int) $orderId)
            ->get(db_prefix() . 'kt_saas_orders')
            ->row_array();
    }

    public function get_tenant_addon_usage_summary($tenantId)
    {
        $tenantId = (int) $tenantId;
        $addons = $this->get_tenant_addons($tenantId);
        $summary = [
            'einvoice_total' => 0.0,
            'einvoice_used' => 0.0,
            'einvoice_remaining' => 0.0,
            'hsm_active' => 0,
        ];

        foreach ($addons as $row) {
            if ((string) ($row['service_type'] ?? '') === 'einvoice') {
                $summary['einvoice_total'] += (float) ($row['quantity_purchased'] ?? 0);
                $summary['einvoice_used'] += (float) ($row['quantity_used'] ?? 0);
                $summary['einvoice_remaining'] += (float) ($row['quantity_remaining'] ?? 0);
            }
            if ((string) ($row['service_type'] ?? '') === 'hsm_signature' && in_array((string) ($row['status'] ?? ''), ['active', 'provisioning', 'paid'], true)) {
                $summary['hsm_active']++;
            }
        }

        return $summary;
    }

    public function get_provider_jobs($tenantId = null)
    {
        if ($tenantId !== null) {
            $this->centralDb()->where('tenant_id', (int) $tenantId);
        }
        return $this->centralDb()->order_by('id', 'desc')->get(db_prefix() . 'kt_saas_provider_provisioning_jobs')->result_array();
    }

    public function tenant_feature_allowed($tenantId, $featureKey, $default = false)
    {
        if (!function_exists('kt_saas_runtime_entitlements')) {
            return $default;
        }
        $service = kt_saas_runtime_entitlements();
        if (!$service || !method_exists($service, 'getFeatureValue')) {
            return $default;
        }
        return (bool) $service->getFeatureValue((int) $tenantId, (string) $featureKey, $default);
    }

    public function get_feature_limit($tenantId, $featureKey, $default = 0)
    {
        if (!function_exists('kt_saas_runtime_entitlements')) {
            return (int) $default;
        }
        $service = kt_saas_runtime_entitlements();
        if (!$service || !method_exists($service, 'getFeatureValue')) {
            return (int) $default;
        }
        $value = $service->getFeatureValue((int) $tenantId, (string) $featureKey, $default);
        return (int) $value;
    }

    public function get_active_einvoice_addons_fifo($tenantId)
    {
        $now = date('Y-m-d H:i:s');
        return $this->centralDb()
            ->where('tenant_id', (int) $tenantId)
            ->where('service_type', 'einvoice')
            ->where_in('status', ['active', 'provisioning', 'paid'])
            ->where('quantity_remaining >', 0)
            ->group_start()
                ->where('ends_at IS NULL', null, false)
                ->or_where('ends_at >=', $now)
            ->group_end()
            ->order_by('starts_at', 'asc')
            ->order_by('id', 'asc')
            ->get(db_prefix() . 'kt_saas_tenant_addons')
            ->result_array();
    }

    public function total_einvoice_remaining_quota($tenantId)
    {
        $rows = $this->get_active_einvoice_addons_fifo($tenantId);
        $sum = 0.0;
        foreach ($rows as $r) {
            $sum += (float) ($r['quantity_remaining'] ?? 0);
        }
        return $sum;
    }

    public function has_active_einvoice_addon($tenantId)
    {
        $now = date('Y-m-d H:i:s');
        return (bool) $this->centralDb()
            ->where('tenant_id', (int) $tenantId)
            ->where('service_type', 'einvoice')
            ->where_in('status', ['active', 'provisioning', 'paid'])
            ->where('quantity_remaining >', 0)
            ->group_start()
                ->where('ends_at IS NULL', null, false)
                ->or_where('ends_at >=', $now)
            ->group_end()
            ->count_all_results(db_prefix() . 'kt_saas_tenant_addons');
    }

    public function get_issued_count_today($tenantId)
    {
        $start = date('Y-m-d 00:00:00');
        $end = date('Y-m-d 23:59:59');
        return (int) $this->centralDb()
            ->where('tenant_id', (int) $tenantId)
            ->where_in('local_status', ['issued', 'signed'])
            ->where('created_at >=', $start)
            ->where('created_at <=', $end)
            ->count_all_results(db_prefix() . 'kt_matbao_invoice_records');
    }

    public function get_issued_count_month($tenantId)
    {
        $start = date('Y-m-01 00:00:00');
        $end = date('Y-m-t 23:59:59');
        return (int) $this->centralDb()
            ->where('tenant_id', (int) $tenantId)
            ->where_in('local_status', ['issued', 'signed'])
            ->where('created_at >=', $start)
            ->where('created_at <=', $end)
            ->count_all_results(db_prefix() . 'kt_matbao_invoice_records');
    }

    public function consume_einvoice_quota_fifo($tenantId, $units, $referenceType, $referenceId)
    {
        $units = (float) $units;
        if ($units <= 0) {
            return ['success' => true, 'consumed' => 0];
        }

        $addons = $this->get_active_einvoice_addons_fifo($tenantId);
        $remaining = $units;
        $now = date('Y-m-d H:i:s');

        foreach ($addons as $addon) {
            if ($remaining <= 0) {
                break;
            }
            $addonId = (int) $addon['id'];
            $available = (float) ($addon['quantity_remaining'] ?? 0);
            if ($available <= 0) {
                continue;
            }
            $take = min($remaining, $available);
            $before = $available;
            $after = $available - $take;

            $this->centralDb()->where('id', $addonId)->update(db_prefix() . 'kt_saas_tenant_addons', [
                'quantity_used' => (float) ($addon['quantity_used'] ?? 0) + $take,
                'quantity_remaining' => $after,
                'updated_at' => $now,
            ]);

            $this->centralDb()->insert(db_prefix() . 'kt_saas_addon_usage_logs', [
                'tenant_id' => (int) $tenantId,
                'addon_id' => $addonId,
                'service_type' => 'einvoice',
                'action' => 'consume',
                'quantity_delta' => -$take,
                'before_quantity' => $before,
                'after_quantity' => $after,
                'reference_type' => (string) $referenceType,
                'reference_id' => (int) $referenceId,
                'created_by' => is_staff_logged_in() ? get_staff_user_id() : null,
                'created_at' => $now,
            ]);

            $remaining -= $take;
        }

        if ($remaining > 0) {
            $this->maybeDispatchEinvoiceQuotaExceededEmail((int) $tenantId, [
                'reference_type' => (string) $referenceType,
                'reference_id' => (int) $referenceId,
                'missing' => $remaining,
            ]);
            return ['success' => false, 'message' => 'Hạn mức hóa đơn điện tử không đủ', 'missing' => $remaining];
        }

        $this->maybeDispatchEinvoiceQuotaLowEmail((int) $tenantId, (int) $referenceId);
        return ['success' => true, 'consumed' => $units];
    }

    public function create_provider_job(array $payload)
    {
        $now = date('Y-m-d H:i:s');
        $this->centralDb()->insert(db_prefix() . 'kt_saas_provider_provisioning_jobs', [
            'tenant_id' => (int) ($payload['tenant_id'] ?? 0),
            'addon_id' => !empty($payload['addon_id']) ? (int) $payload['addon_id'] : null,
            'provider' => (string) ($payload['provider'] ?? 'matbao'),
            'service_type' => (string) ($payload['service_type'] ?? ''),
            'job_type' => (string) ($payload['job_type'] ?? ''),
            'status' => (string) ($payload['status'] ?? 'queued'),
            'request_payload' => isset($payload['request_payload']) ? json_encode($payload['request_payload'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null,
            'response_payload' => null,
            'error_message' => null,
            'attempts' => 0,
            'next_retry_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $this->centralDb()->insert_id();
    }

    public function mark_order_paid_and_activate_addons($orderId, $paymentMethod = 'manual', array $paymentData = [])
    {
        $orderId = (int) $orderId;
        $db = $this->centralDb();
        $order = $db->where('id', $orderId)->get(db_prefix() . 'kt_saas_orders')->row_array();
        if (!$order) {
            return ['success' => false, 'message' => 'Không tìm thấy đơn hàng.'];
        }
        if ((string) ($order['order_type'] ?? '') !== 'addon_purchase') {
            return ['success' => false, 'message' => 'Đơn hàng không thuộc luồng mua dịch vụ bổ sung.'];
        }
        if ((int) ($order['tenant_id'] ?? 0) < 1) {
            return ['success' => false, 'message' => 'Đơn hàng chưa gắn với doanh nghiệp hợp lệ.'];
        }
        $items = $this->get_order_items($orderId);
        if (empty($items)) {
            return ['success' => false, 'message' => 'Đơn hàng không có hạng mục dịch vụ hợp lệ.'];
        }
        $paidAmount = array_key_exists('amount', $paymentData)
            ? (float) $paymentData['amount']
            : (float) ($order['grand_total'] ?? 0);
        if (abs($paidAmount - (float) ($order['grand_total'] ?? 0)) > 0.01) {
            return ['success' => false, 'message' => 'Số tiền thanh toán không khớp tổng giá trị đơn hàng.'];
        }
        $paymentReference = trim((string) ($paymentData['payment_reference'] ?? ''));
        if ($paymentReference === '') {
            $paymentReference = 'MATBAO-' . strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $paymentMethod)) . '-' . $orderId;
        }

        $now = date('Y-m-d H:i:s');
        $activatedEinvoice = false;
        $activatedAddons = 0;
        $db->trans_begin();

        try {
            if ((string) ($order['payment_status'] ?? '') !== 'paid') {
                $db->where('id', $orderId)->update(db_prefix() . 'kt_saas_orders', [
                    'status' => 'paid',
                    'payment_status' => 'paid',
                    'payment_method' => (string) $paymentMethod,
                    'paid_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($items as $item) {
                $itemType = (string) ($item['item_type'] ?? '');
                if (!in_array($itemType, ['addon_einvoice', 'addon_hsm'], true)) {
                    continue;
                }
                $package = null;
                if (!empty($item['ref_id'])) {
                    $package = $db->where('id', (int) $item['ref_id'])->get(db_prefix() . 'kt_saas_reseller_packages')->row_array();
                }
                if (!$package) {
                    throw new RuntimeException('Không tìm thấy gói dịch vụ cho hạng mục #' . (int) ($item['id'] ?? 0) . '.');
                }

                $serviceType = (string) ($package['service_type'] ?? ($itemType === 'addon_hsm' ? 'hsm_signature' : 'einvoice'));
                $packageUnits = (float) ($item['quantity'] ?? 1);
                $packageQuota = (float) ($package['quantity'] ?? 1);
                $effectiveUnits = $serviceType === 'einvoice'
                    ? ($packageUnits * max(1, $packageQuota))
                    : $packageUnits;
                $durationDays = !empty($package['duration_days']) ? (int) $package['duration_days'] : null;
                if (!$durationDays && !empty($package['duration_years'])) {
                    $durationDays = (int) $package['duration_years'] * 365;
                }
                $endsAt = $durationDays ? date('Y-m-d H:i:s', strtotime('+' . $durationDays . ' days')) : null;

                $addon = $db
                    ->where('order_id', $orderId)
                    ->where('package_id', (int) $package['id'])
                    ->get(db_prefix() . 'kt_saas_tenant_addons')
                    ->row_array();

                if (!$addon) {
                    $db->insert(db_prefix() . 'kt_saas_tenant_addons', [
                        'tenant_id' => (int) $order['tenant_id'],
                        'subscription_id' => null,
                        'order_id' => $orderId,
                        'package_id' => (int) $package['id'],
                        'provider' => (string) ($package['provider'] ?? 'matbao'),
                        'service_type' => $serviceType,
                        'package_code' => (string) ($package['package_code'] ?? ($item['item_code'] ?? '')),
                        'quantity_purchased' => $effectiveUnits,
                        'quantity_used' => 0,
                        'quantity_remaining' => $effectiveUnits,
                        'starts_at' => $now,
                        'ends_at' => $endsAt,
                        'status' => 'paid',
                        'provider_account_id' => null,
                        'provider_order_code' => null,
                        'provider_status' => null,
                        'provisioning_job_id' => null,
                        'notes' => 'Created from order #' . $orderId . '; package_units=' . $packageUnits . '; package_quota=' . $packageQuota,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $addonId = (int) $db->insert_id();
                    $activatedAddons++;
                } else {
                    $addonId = (int) $addon['id'];
                }

                if ($serviceType === 'einvoice') {
                    $activatedEinvoice = true;
                }

                $job = $db
                    ->where('addon_id', $addonId)
                    ->where_in('status', ['queued', 'running', 'done'])
                    ->order_by('id', 'desc')
                    ->get(db_prefix() . 'kt_saas_provider_provisioning_jobs')
                    ->row_array();
                if (!$job) {
                    $jobType = $serviceType === 'hsm_signature' ? 'register_hsm' : 'add_invoice_quota';
                    $jobId = $this->create_provider_job([
                        'tenant_id' => (int) $order['tenant_id'],
                        'addon_id' => $addonId,
                        'provider' => (string) ($package['provider'] ?? 'matbao'),
                        'service_type' => $serviceType,
                        'job_type' => $jobType,
                        'status' => 'queued',
                        'request_payload' => ['order_id' => $orderId, 'order_item_id' => (int) $item['id']],
                    ]);

                    $db->where('id', $addonId)->update(db_prefix() . 'kt_saas_tenant_addons', [
                        'provisioning_job_id' => $jobId,
                        'status' => 'provisioning',
                        'updated_at' => $now,
                    ]);
                }
            }

            $this->load->model('kt_saas/Kt_saas_model');
            $paymentId = $this->Kt_saas_model->create_payment([
                'tenant_id' => (int) $order['tenant_id'],
                'invoice_id' => null,
                'payment_reference' => $paymentReference,
                'gateway' => (string) $paymentMethod,
                'status' => 'paid',
                'amount' => $paidAmount,
                'currency' => (string) ($order['currency'] ?? 'VND'),
                'gateway_payload_json' => json_encode([
                    'context_type' => 'kt_matbao_invoice_order',
                    'context_id' => $orderId,
                    'order_code' => (string) ($order['order_code'] ?? ''),
                    'source' => (string) ($paymentData['source'] ?? $paymentMethod),
                    'provider_transaction_id' => (string) ($paymentData['provider_transaction_id'] ?? ''),
                ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'paid_at' => (string) ($paymentData['paid_at'] ?? $now),
            ]);

            if ($db->trans_status() === false) {
                throw new RuntimeException('Không thể cập nhật trạng thái thanh toán và quyền sử dụng.');
            }
            $db->trans_commit();
        } catch (Throwable $e) {
            $db->trans_rollback();
            log_message('error', 'KT MatBao order payment failed for order #' . $orderId . ': ' . $e->getMessage());

            return ['success' => false, 'message' => $e->getMessage()];
        }

        $this->Kt_saas_model->log_activity('matbao.addon_order_paid', 'success', [
            'order_id' => $orderId,
            'order_code' => (string) ($order['order_code'] ?? ''),
            'payment_id' => (int) ($paymentId ?? 0),
            'payment_reference' => $paymentReference,
            'payment_method' => (string) $paymentMethod,
            'source' => (string) ($paymentData['source'] ?? $paymentMethod),
            'amount' => $paidAmount,
            'currency' => (string) ($order['currency'] ?? 'VND'),
            'activated_addons' => $activatedAddons,
        ], (int) $order['tenant_id']);

        if ($activatedEinvoice) {
            try {
                $this->maybeDispatchEinvoiceActivatedEmail((int) $order['tenant_id'], $orderId);
            } catch (Throwable $e) {
                log_message('error', 'KT MatBao activation email failed for order #' . $orderId . ': ' . $e->getMessage());
            }
        }

        return [
            'success' => true,
            'message' => (string) ($order['payment_status'] ?? '') === 'paid'
                ? 'Đơn hàng đã được thanh toán trước đó; quyền sử dụng đã được đối soát.'
                : 'Đã ghi nhận thanh toán và kích hoạt quyền sử dụng.',
            'activated_addons' => $activatedAddons,
            'payment_id' => (int) ($paymentId ?? 0),
        ];
    }

    protected function getSaasTenant($tenantId)
    {
        $this->load->model('kt_saas/Kt_saas_model');

        return $this->Kt_saas_model->get_tenant((int) $tenantId);
    }

    public function get_tenant($tenantId)
    {
        return $this->getSaasTenant((int) $tenantId);
    }

    protected function maybeDispatchEinvoiceActivatedEmail($tenantId, $orderId)
    {
        if ((int) $tenantId < 1) {
            return;
        }
        if (!function_exists('kt_saas_send_email_event')) {
            require_once module_dir_path('kt_saas', 'helpers/kt_saas_helper.php');
        }
        if (!function_exists('kt_saas_send_email_event')) {
            return;
        }

        $tenant = $this->getSaasTenant((int) $tenantId);
        if (!$tenant) {
            return;
        }
        $summary = $this->get_tenant_addon_usage_summary((int) $tenantId);
        $dedupeKey = 'einvoice_activated|' . (int) $tenantId . '|' . (int) $orderId;

        kt_saas_send_email_event('einvoice_activated', [
            'tenant_id' => (int) $tenantId,
            'tenant' => $tenant,
            'recipient_email' => (string) ($tenant['owner_email'] ?? $tenant['admin_email'] ?? $tenant['email'] ?? ''),
            'owner_email' => (string) ($tenant['owner_email'] ?? $tenant['email'] ?? ''),
            'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['contact_name'] ?? $tenant['company_name'] ?? $tenant['tenant_name'] ?? ''),
            'tenant_name' => (string) ($tenant['tenant_name'] ?? $tenant['company_name'] ?? $tenant['tenant_code'] ?? ''),
            'workspace_url' => (string) ($tenant['workspace_url'] ?? ''),
            'plan_name' => (string) ($tenant['plan_name'] ?? ''),
            'einvoice_quota' => (string) ($summary['einvoice_total'] ?? ''),
            'einvoice_remaining' => (string) ($summary['einvoice_remaining'] ?? ''),
            'related_type' => 'addon',
            'related_id' => (string) $orderId,
            'dedupe_key' => $dedupeKey,
        ], [
            'event_key' => 'einvoice_activated',
            'dedupe_key' => $dedupeKey,
        ]);
    }

    protected function maybeDispatchEinvoiceQuotaLowEmail($tenantId, $referenceId = 0)
    {
        if ((int) $tenantId < 1) {
            return;
        }
        if (!function_exists('kt_saas_send_email_event')) {
            require_once module_dir_path('kt_saas', 'helpers/kt_saas_helper.php');
        }
        if (!function_exists('kt_saas_send_email_event')) {
            return;
        }

        $summary = $this->get_tenant_addon_usage_summary((int) $tenantId);
        $total = (float) ($summary['einvoice_total'] ?? 0);
        $used = (float) ($summary['einvoice_used'] ?? 0);
        if ($total <= 0) {
            return;
        }

        $ratio = ($used / $total) * 100;
        $tenant = $this->getSaasTenant((int) $tenantId);
        if (!$tenant) {
            return;
        }

        foreach ([80, 90, 95] as $threshold) {
            if ($ratio < $threshold) {
                continue;
            }

            $dedupeKey = 'einvoice_quota_low|' . (int) $tenantId . '|' . date('Y-m') . '|' . $threshold;
            kt_saas_send_email_event('einvoice_quota_low', [
                'tenant_id' => (int) $tenantId,
                'tenant' => $tenant,
                'recipient_email' => (string) ($tenant['owner_email'] ?? $tenant['admin_email'] ?? $tenant['email'] ?? ''),
                'owner_email' => (string) ($tenant['owner_email'] ?? $tenant['email'] ?? ''),
                'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['contact_name'] ?? $tenant['company_name'] ?? $tenant['tenant_name'] ?? ''),
                'tenant_name' => (string) ($tenant['tenant_name'] ?? $tenant['company_name'] ?? $tenant['tenant_code'] ?? ''),
                'workspace_url' => (string) ($tenant['workspace_url'] ?? ''),
                'plan_name' => (string) ($tenant['plan_name'] ?? ''),
                'einvoice_quota' => (string) $total,
                'einvoice_remaining' => (string) ($summary['einvoice_remaining'] ?? ''),
                'related_type' => 'usage',
                'related_id' => (string) ($referenceId ?: $tenantId),
                'threshold' => $threshold,
                'dedupe_key' => $dedupeKey,
            ], [
                'event_key' => 'einvoice_quota_low',
                'dedupe_key' => $dedupeKey,
            ]);
        }
    }

    protected function maybeDispatchEinvoiceQuotaExceededEmail($tenantId, array $context = [])
    {
        if ((int) $tenantId < 1) {
            return;
        }
        if (!function_exists('kt_saas_send_email_event')) {
            require_once module_dir_path('kt_saas', 'helpers/kt_saas_helper.php');
        }
        if (!function_exists('kt_saas_send_email_event')) {
            return;
        }

        $tenant = $this->getSaasTenant((int) $tenantId);
        if (!$tenant) {
            return;
        }

        $summary = $this->get_tenant_addon_usage_summary((int) $tenantId);
        $dedupeKey = 'einvoice_quota_exhausted|' . (int) $tenantId . '|' . date('Y-m') . '|' . (string) ($context['reference_type'] ?? 'usage') . '|' . (string) ($context['reference_id'] ?? 0);

        kt_saas_send_email_event('einvoice_quota_exhausted', [
            'tenant_id' => (int) $tenantId,
            'tenant' => $tenant,
            'recipient_email' => (string) ($tenant['owner_email'] ?? $tenant['admin_email'] ?? $tenant['email'] ?? ''),
            'owner_email' => (string) ($tenant['owner_email'] ?? $tenant['email'] ?? ''),
            'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['contact_name'] ?? $tenant['company_name'] ?? $tenant['tenant_name'] ?? ''),
            'tenant_name' => (string) ($tenant['tenant_name'] ?? $tenant['company_name'] ?? $tenant['tenant_code'] ?? ''),
            'workspace_url' => (string) ($tenant['workspace_url'] ?? ''),
            'plan_name' => (string) ($tenant['plan_name'] ?? ''),
            'einvoice_quota' => (string) ($summary['einvoice_total'] ?? ''),
            'einvoice_remaining' => (string) ($summary['einvoice_remaining'] ?? ''),
            'related_type' => 'usage',
            'related_id' => (string) ($context['reference_id'] ?? $tenantId),
            'error_message' => 'Hạn mức hóa đơn điện tử không đủ',
            'dedupe_key' => $dedupeKey,
        ], [
            'event_key' => 'einvoice_quota_exhausted',
            'dedupe_key' => $dedupeKey,
        ]);
    }

    protected function tenantOwnerEmail($tenantId)
    {
        $tenant = $this->getSaasTenant((int) $tenantId);
        if (!$tenant) {
            return '';
        }
        foreach (['owner_email', 'admin_email', 'billing_email', 'email'] as $key) {
            $value = trim((string) ($tenant[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }
}
