<?php

defined('BASEPATH') or exit('No direct script access allowed');

function kt_misa_amis_accounting_install()
{
    $CI = &get_instance();
    $charset = $CI->db->char_set ?: 'utf8mb4';
    $collation = $CI->db->dbcollat ?: 'utf8mb4_unicode_ci';
    if (stripos($collation, '0900') !== false) {
        $collation = 'utf8mb4_unicode_ci';
    }
    $p = db_prefix();
    $tables = [
        "CREATE TABLE IF NOT EXISTS `{$p}kt_misa_connections` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `tenant_id` INT UNSIGNED NOT NULL,
            `company_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(191) NOT NULL,
            `environment` VARCHAR(32) NOT NULL DEFAULT 'mock',
            `app_id` VARCHAR(191) NOT NULL DEFAULT '',
            `access_code_encrypted` MEDIUMTEXT NULL,
            `access_token_encrypted` MEDIUMTEXT NULL,
            `org_company_code` VARCHAR(191) NOT NULL DEFAULT '',
            `token_expires_at` DATETIME NULL,
            `token_expires_ticks` BIGINT NULL,
            `status` VARCHAR(32) NOT NULL DEFAULT 'disabled',
            `certification_level` VARCHAR(40) NOT NULL DEFAULT 'NOT_IMPLEMENTED',
            `last_connected_at` DATETIME NULL,
            `last_healthcheck_at` DATETIME NULL,
            `last_error_code` VARCHAR(64) NULL,
            `last_error_message_redacted` VARCHAR(500) NULL,
            `token_version` INT UNSIGNED NOT NULL DEFAULT 0,
            `refresh_lock_owner` VARCHAR(128) NULL,
            `refresh_lock_expires_at` DATETIME NULL,
            `disabled_at` DATETIME NULL,
            `callback_key_encrypted` MEDIUMTEXT NULL,
            `metadata_json` MEDIUMTEXT NULL,
            `created_by` INT UNSIGNED NULL,
            `updated_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_scope_app_org` (`tenant_id`,`company_id`,`app_id`,`org_company_code`),
            KEY `idx_scope_status` (`tenant_id`,`company_id`,`status`),
            KEY `idx_certification` (`certification_level`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};",
        "CREATE TABLE IF NOT EXISTS `{$p}kt_misa_requests` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `tenant_id` INT UNSIGNED NOT NULL,
            `company_id` INT UNSIGNED NOT NULL,
            `connection_id` BIGINT UNSIGNED NOT NULL,
            `direction` VARCHAR(16) NOT NULL DEFAULT 'push',
            `operation` VARCHAR(64) NOT NULL,
            `object_type` VARCHAR(64) NOT NULL,
            `local_object_id` BIGINT UNSIGNED NULL,
            `local_stable_key` VARCHAR(191) NULL,
            `org_refid` VARCHAR(191) NULL,
            `idempotency_key` VARCHAR(191) NOT NULL,
            `correlation_id` VARCHAR(128) NOT NULL,
            `status` VARCHAR(40) NOT NULL DEFAULT 'draft',
            `priority` INT NOT NULL DEFAULT 100,
            `payload_hash` CHAR(64) NULL,
            `payload_redacted_json` LONGTEXT NULL,
            `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `max_attempts` INT UNSIGNED NOT NULL DEFAULT 5,
            `next_attempt_at` DATETIME NULL,
            `accepted_at` DATETIME NULL,
            `completed_at` DATETIME NULL,
            `error_class` VARCHAR(64) NULL,
            `error_code` VARCHAR(64) NULL,
            `error_message_redacted` VARCHAR(500) NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_request_idempotency` (`tenant_id`,`company_id`,`connection_id`,`idempotency_key`),
            KEY `idx_request_status` (`tenant_id`,`company_id`,`status`,`next_attempt_at`),
            KEY `idx_request_org_refid` (`tenant_id`,`company_id`,`connection_id`,`org_refid`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};",
        "CREATE TABLE IF NOT EXISTS `{$p}kt_misa_request_items` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `request_id` BIGINT UNSIGNED NOT NULL,
            `tenant_id` INT UNSIGNED NOT NULL,
            `company_id` INT UNSIGNED NOT NULL,
            `org_refid` VARCHAR(191) NOT NULL,
            `remote_id` VARCHAR(191) NULL,
            `result_status` VARCHAR(40) NOT NULL DEFAULT 'pending',
            `success` TINYINT(1) NOT NULL DEFAULT 0,
            `error_code` VARCHAR(64) NULL,
            `error_message_redacted` VARCHAR(500) NULL,
            `result_hash` CHAR(64) NULL,
            `processed_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_request_org_refid` (`request_id`,`org_refid`),
            KEY `idx_item_scope` (`tenant_id`,`company_id`,`result_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};",
        "CREATE TABLE IF NOT EXISTS `{$p}kt_misa_callback_inbox` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `tenant_id` INT UNSIGNED NOT NULL,
            `company_id` INT UNSIGNED NOT NULL,
            `connection_id` BIGINT UNSIGNED NULL,
            `app_id` VARCHAR(191) NOT NULL DEFAULT '',
            `org_company_code` VARCHAR(191) NOT NULL DEFAULT '',
            `data_type` VARCHAR(128) NOT NULL DEFAULT '',
            `signature` VARCHAR(255) NOT NULL DEFAULT '',
            `signature_valid` TINYINT(1) NOT NULL DEFAULT 0,
            `payload_hash` CHAR(64) NOT NULL,
            `raw_data` LONGTEXT NOT NULL,
            `processing_status` VARCHAR(32) NOT NULL DEFAULT 'received',
            `duplicate_of` BIGINT UNSIGNED NULL,
            `received_at` DATETIME NOT NULL,
            `processed_at` DATETIME NULL,
            `error_code` VARCHAR(64) NULL,
            `error_message_redacted` VARCHAR(500) NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_callback_scope_hash` (`tenant_id`,`company_id`,`connection_id`,`payload_hash`),
            KEY `idx_callback_status` (`tenant_id`,`company_id`,`processing_status`),
            KEY `idx_callback_org` (`tenant_id`,`company_id`,`org_company_code`,`app_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};",
        "CREATE TABLE IF NOT EXISTS `{$p}kt_misa_mappings` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `tenant_id` INT UNSIGNED NOT NULL,
            `company_id` INT UNSIGNED NOT NULL,
            `connection_id` BIGINT UNSIGNED NOT NULL,
            `object_type` VARCHAR(64) NOT NULL,
            `local_id` BIGINT UNSIGNED NOT NULL,
            `local_stable_key` VARCHAR(191) NOT NULL,
            `remote_id` VARCHAR(191) NULL,
            `remote_code` VARCHAR(191) NULL,
            `direction` VARCHAR(16) NOT NULL DEFAULT 'push',
            `mapping_status` VARCHAR(32) NOT NULL DEFAULT 'unmapped',
            `local_fingerprint` CHAR(64) NULL,
            `remote_fingerprint` CHAR(64) NULL,
            `deleted_at_remote` DATETIME NULL,
            `metadata_json` MEDIUMTEXT NULL,
            `last_synced_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_mapping_local` (`tenant_id`,`company_id`,`connection_id`,`object_type`,`local_stable_key`),
            UNIQUE KEY `uniq_mapping_remote` (`tenant_id`,`company_id`,`connection_id`,`object_type`,`remote_id`),
            KEY `idx_mapping_status` (`tenant_id`,`company_id`,`object_type`,`mapping_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};",
        "CREATE TABLE IF NOT EXISTS `{$p}kt_misa_pull_cursors` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `tenant_id` INT UNSIGNED NOT NULL,
            `company_id` INT UNSIGNED NOT NULL,
            `connection_id` BIGINT UNSIGNED NOT NULL,
            `data_type` VARCHAR(128) NOT NULL,
            `branch_id` BIGINT UNSIGNED NULL,
            `last_sync_time` DATETIME NULL,
            `overlap_seconds` INT UNSIGNED NOT NULL DEFAULT 60,
            `last_successful_page` INT UNSIGNED NOT NULL DEFAULT 0,
            `last_run_at` DATETIME NULL,
            `last_completed_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_pull_scope_type_branch` (`tenant_id`,`company_id`,`connection_id`,`data_type`,`branch_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};",
        "CREATE TABLE IF NOT EXISTS `{$p}kt_misa_attempts` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `tenant_id` INT UNSIGNED NOT NULL,
            `company_id` INT UNSIGNED NOT NULL,
            `connection_id` BIGINT UNSIGNED NOT NULL,
            `request_id` BIGINT UNSIGNED NOT NULL,
            `attempt_number` INT UNSIGNED NOT NULL,
            `transport` VARCHAR(32) NOT NULL DEFAULT 'mock',
            `request_headers_redacted` MEDIUMTEXT NULL,
            `request_payload_redacted` LONGTEXT NULL,
            `response_status` INT NULL,
            `response_body_redacted` LONGTEXT NULL,
            `outcome` VARCHAR(40) NOT NULL DEFAULT 'started',
            `started_at` DATETIME NOT NULL,
            `finished_at` DATETIME NULL,
            `duration_ms` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_attempt` (`request_id`,`attempt_number`),
            KEY `idx_attempt_scope` (`tenant_id`,`company_id`,`outcome`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};",
        "CREATE TABLE IF NOT EXISTS `{$p}kt_misa_audit_logs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `tenant_id` INT UNSIGNED NOT NULL,
            `company_id` INT UNSIGNED NOT NULL,
            `connection_id` BIGINT UNSIGNED NULL,
            `actor_type` VARCHAR(32) NOT NULL DEFAULT 'system',
            `actor_id` BIGINT UNSIGNED NULL,
            `action` VARCHAR(128) NOT NULL,
            `object_type` VARCHAR(64) NULL,
            `object_id` BIGINT UNSIGNED NULL,
            `correlation_id` VARCHAR(128) NULL,
            `details_redacted_json` MEDIUMTEXT NULL,
            `ip_address` VARCHAR(64) NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_audit_scope_time` (`tenant_id`,`company_id`,`created_at`),
            KEY `idx_audit_action` (`action`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};",
    ];

    foreach ($tables as $sql) {
        $CI->db->query($sql);
    }

    // Phase 11 additive async-runtime fields. These are module-owned, nullable/defaulted,
    // and guarded so repeated activation is idempotent. No existing CRM or hub table is touched.
    $requestTable = $p . 'kt_misa_requests';
    $connectionTable = $p . 'kt_misa_connections';
    $addColumn = static function ($db, $table, $column, $definition) {
        if (!$db->field_exists($column, $table)) {
            $db->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    };
    $addIndex = static function ($db, $table, $index, $definition) {
        $result = $db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $db->escape($index));
        if ($result && $result->num_rows() === 0) {
            $db->query("ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$definition})");
        }
    };
    $addColumn($CI->db, $requestTable, 'worker_id', 'VARCHAR(128) NULL');
    $addColumn($CI->db, $requestTable, 'lease_token_hash', 'CHAR(64) NULL');
    $addColumn($CI->db, $requestTable, 'lease_kind', "VARCHAR(32) NOT NULL DEFAULT 'dispatch'");
    $addColumn($CI->db, $requestTable, 'claimed_at', 'DATETIME NULL');
    $addColumn($CI->db, $requestTable, 'lease_expires_at', 'DATETIME NULL');
    $addColumn($CI->db, $requestTable, 'heartbeat_at', 'DATETIME NULL');
    $addColumn($CI->db, $requestTable, 'last_error_class', 'VARCHAR(64) NULL');
    $addColumn($CI->db, $requestTable, 'last_error_code', 'VARCHAR(64) NULL');
    $addIndex($CI->db, $requestTable, 'idx_misa_worker_claim', '`tenant_id`,`company_id`,`status`,`next_attempt_at`,`lease_expires_at`');
    $addColumn($CI->db, $connectionTable, 'refresh_lock_token_hash', 'CHAR(64) NULL');
    $addIndex($CI->db, $connectionTable, 'idx_misa_refresh_lock', '`tenant_id`,`company_id`,`refresh_lock_expires_at`');

    // Safe-forward upgrade from 0.1.0: duplicate callbacks must remain append-only evidence.
    $callbackTable = $p . 'kt_misa_callback_inbox';
    $legacyIndex = $CI->db->query("SHOW INDEX FROM `{$callbackTable}` WHERE Key_name = 'uniq_callback_scope_hash'");
    $newIndex = $CI->db->query("SHOW INDEX FROM `{$callbackTable}` WHERE Key_name = 'idx_callback_scope_hash'");
    if ($legacyIndex && $legacyIndex->num_rows() > 0) {
        $CI->db->query("ALTER TABLE `{$callbackTable}` DROP INDEX `uniq_callback_scope_hash`");
    }
    if ($newIndex && $newIndex->num_rows() === 0) {
        $CI->db->query("ALTER TABLE `{$callbackTable}` ADD KEY `idx_callback_scope_hash` (`tenant_id`,`company_id`,`connection_id`,`payload_hash`)");
    }

    $defaults = [
        'kt_misa_module_enabled' => '0',
        'kt_misa_external_calls_enabled' => '0',
        'kt_misa_callback_enabled' => '0',
        'kt_misa_master_data_sync_enabled' => '0',
        'kt_misa_dry_run_enabled' => '1',
        'kt_misa_certification_level' => 'NOT_IMPLEMENTED',
        'kt_misa_schema_version' => KT_MISA_AMIS_ACCOUNTING_VERSION,
    ];
    foreach ($defaults as $key => $value) {
        if (get_option($key) === false) {
            add_option($key, $value);
        }
    }
    if (version_compare((string) get_option('kt_misa_schema_version'), KT_MISA_AMIS_ACCOUNTING_VERSION, '<')) {
        update_option('kt_misa_schema_version', KT_MISA_AMIS_ACCOUNTING_VERSION);
    }
}

if (defined('KT_MISA_AMIS_ACCOUNTING_MODULE') && function_exists('get_instance')) {
    // Activation invokes this function explicitly; no schema writes occur on ordinary include.
}
