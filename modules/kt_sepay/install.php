<?php

defined('BASEPATH') or exit('No direct script access allowed');

function kt_sepay_run_install()
{
    $CI = &get_instance();
    $CI->load->dbforge();

    $tables = [];

    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_sepay_settings` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `environment` VARCHAR(32) NOT NULL DEFAULT 'sandbox',
        `bank_code` VARCHAR(64) NOT NULL DEFAULT '',
        `account_number` VARCHAR(64) NOT NULL DEFAULT '',
        `account_name` VARCHAR(191) NOT NULL DEFAULT '',
        `api_token_encrypted` TEXT NULL,
        `webhook_secret_encrypted` TEXT NULL,
        `qr_template` VARCHAR(32) NOT NULL DEFAULT 'compact',
        `reference_prefix_invoice` VARCHAR(24) NOT NULL DEFAULT 'KTINV',
        `reference_prefix_subscription` VARCHAR(24) NOT NULL DEFAULT 'KTSAAS',
        `reference_prefix_manual` VARCHAR(24) NOT NULL DEFAULT 'KTPAY',
        `auto_reconcile_enabled` TINYINT(1) NOT NULL DEFAULT 1,
        `reconcile_interval_minutes` INT UNSIGNED NOT NULL DEFAULT 15,
        `payment_request_expiry_minutes` INT UNSIGNED NOT NULL DEFAULT 60,
        `last_reconcile_transaction_id` VARCHAR(128) NULL,
        `last_reconcile_at` DATETIME NULL,
        `allow_partial_payment` TINYINT(1) NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";";

    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_sepay_payment_requests` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `context_type` VARCHAR(64) NOT NULL,
        `context_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
        `tenant_id` INT UNSIGNED NULL,
        `invoice_id` BIGINT UNSIGNED NULL,
        `subscription_id` BIGINT UNSIGNED NULL,
        `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `currency` VARCHAR(12) NOT NULL DEFAULT 'VND',
        `reference_code` VARCHAR(64) NOT NULL,
        `access_token` VARCHAR(64) NOT NULL,
        `description` VARCHAR(191) NOT NULL DEFAULT '',
        `qr_url` TEXT NULL,
        `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
        `paid_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `payment_mode` VARCHAR(64) NOT NULL DEFAULT 'sepay',
        `metadata_json` LONGTEXT NULL,
        `expires_at` DATETIME NULL,
        `processed_at` DATETIME NULL,
        `created_by` INT UNSIGNED NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_reference_code` (`reference_code`),
        UNIQUE KEY `uniq_access_token` (`access_token`),
        KEY `idx_tenant_id` (`tenant_id`),
        KEY `idx_invoice_id` (`invoice_id`),
        KEY `idx_subscription_id` (`subscription_id`),
        KEY `idx_status` (`status`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";";

    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_sepay_transactions` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `sepay_transaction_id` VARCHAR(128) NOT NULL,
        `gateway` VARCHAR(64) NOT NULL DEFAULT '',
        `transaction_date` DATETIME NULL,
        `account_number` VARCHAR(64) NOT NULL DEFAULT '',
        `code` VARCHAR(128) NULL,
        `content` TEXT NULL,
        `transfer_type` VARCHAR(16) NOT NULL DEFAULT '',
        `transfer_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `reference_code` VARCHAR(128) NULL,
        `matched_reference` VARCHAR(128) NULL,
        `matched_type` VARCHAR(64) NULL,
        `matched_id` BIGINT UNSIGNED NULL,
        `payment_request_id` BIGINT UNSIGNED NULL,
        `tenant_id` INT UNSIGNED NULL,
        `status` VARCHAR(32) NOT NULL DEFAULT 'received',
        `raw_payload` LONGTEXT NULL,
        `processed_at` DATETIME NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_sepay_transaction_id` (`sepay_transaction_id`),
        KEY `idx_matched_reference` (`matched_reference`),
        KEY `idx_tenant_id` (`tenant_id`),
        KEY `idx_status` (`status`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";";

    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_sepay_webhook_logs` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `source` VARCHAR(64) NOT NULL DEFAULT 'webhook',
        `headers` LONGTEXT NULL,
        `raw_body` LONGTEXT NULL,
        `parsed_payload` LONGTEXT NULL,
        `ip_address` VARCHAR(64) NOT NULL DEFAULT '',
        `status` VARCHAR(32) NOT NULL DEFAULT 'received',
        `error_message` TEXT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_status` (`status`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";";

    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_sepay_reconciliation_logs` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id` INT UNSIGNED NULL,
        `run_id` VARCHAR(64) NOT NULL,
        `environment` VARCHAR(32) NOT NULL DEFAULT 'sandbox',
        `from_time` DATETIME NULL,
        `to_time` DATETIME NULL,
        `total_fetched` INT UNSIGNED NOT NULL DEFAULT 0,
        `total_matched` INT UNSIGNED NOT NULL DEFAULT 0,
        `total_processed` INT UNSIGNED NOT NULL DEFAULT 0,
        `total_errors` INT UNSIGNED NOT NULL DEFAULT 0,
        `metadata_json` LONGTEXT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_tenant_id` (`tenant_id`),
        KEY `idx_run_id` (`run_id`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";";

    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_sepay_health_logs` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id` INT UNSIGNED NULL,
        `test_type` VARCHAR(64) NOT NULL,
        `environment` VARCHAR(32) NOT NULL DEFAULT 'sandbox',
        `status` VARCHAR(32) NOT NULL DEFAULT 'error',
        `http_code` INT NOT NULL DEFAULT 0,
        `latency_ms` INT UNSIGNED NOT NULL DEFAULT 0,
        `message` VARCHAR(255) NOT NULL DEFAULT '',
        `error_code` VARCHAR(64) NULL,
        `raw_response` LONGTEXT NULL,
        `created_by` INT UNSIGNED NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_tenant_id` (`tenant_id`),
        KEY `idx_test_type` (`test_type`),
        KEY `idx_status` (`status`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";";

    foreach ($tables as $sql) {
        $CI->db->query($sql);
    }

    $settingsTable = db_prefix() . 'kt_sepay_settings';
    if (!$CI->db->field_exists('tenant_id', $settingsTable)) {
        $CI->db->query("ALTER TABLE `" . $settingsTable . "` ADD `tenant_id` INT UNSIGNED NULL AFTER `id`");
    }
    if (!$CI->db->field_exists('last_reconcile_transaction_id', $settingsTable)) {
        $CI->db->query("ALTER TABLE `" . $settingsTable . "` ADD `last_reconcile_transaction_id` VARCHAR(128) NULL AFTER `payment_request_expiry_minutes`");
    }
    if (!$CI->db->field_exists('last_reconcile_at', $settingsTable)) {
        $CI->db->query("ALTER TABLE `" . $settingsTable . "` ADD `last_reconcile_at` DATETIME NULL AFTER `last_reconcile_transaction_id`");
    }

    $tenantIdIndexExists = $CI->db->query("SHOW INDEX FROM `" . $settingsTable . "` WHERE Key_name = 'uniq_tenant_settings'")->num_rows() > 0;
    if (!$tenantIdIndexExists) {
        $CI->db->query("ALTER TABLE `" . $settingsTable . "` ADD UNIQUE KEY `uniq_tenant_settings` (`tenant_id`)");
    }
    if ($CI->db->table_exists(db_prefix() . 'kt_sepay_reconciliation_logs') && !$CI->db->field_exists('tenant_id', db_prefix() . 'kt_sepay_reconciliation_logs')) {
        $CI->db->query("ALTER TABLE `" . db_prefix() . "kt_sepay_reconciliation_logs` ADD `tenant_id` INT UNSIGNED NULL AFTER `id`");
        $CI->db->query("ALTER TABLE `" . db_prefix() . "kt_sepay_reconciliation_logs` ADD KEY `idx_tenant_id` (`tenant_id`)");
    }
    if ($CI->db->table_exists(db_prefix() . 'kt_sepay_health_logs') && !$CI->db->field_exists('tenant_id', db_prefix() . 'kt_sepay_health_logs')) {
        $CI->db->query("ALTER TABLE `" . db_prefix() . "kt_sepay_health_logs` ADD `tenant_id` INT UNSIGNED NULL AFTER `id`");
        $CI->db->query("ALTER TABLE `" . db_prefix() . "kt_sepay_health_logs` ADD KEY `idx_tenant_id` (`tenant_id`)");
    }

    $exists = $CI->db->count_all_results($settingsTable);
    if ((int) $exists === 0) {
        $CI->db->insert($settingsTable, [
            'tenant_id'                  => null,
            'environment'                => 'sandbox',
            'bank_code'                  => '',
            'account_number'             => '',
            'account_name'               => '',
            'api_token_encrypted'        => null,
            'webhook_secret_encrypted'   => null,
            'qr_template'                => 'compact',
            'reference_prefix_invoice'   => 'KTINV',
            'reference_prefix_subscription' => 'KTSAAS',
            'reference_prefix_manual'    => 'KTPAY',
            'auto_reconcile_enabled'     => 1,
            'reconcile_interval_minutes' => 15,
            'payment_request_expiry_minutes' => 60,
            'last_reconcile_transaction_id' => null,
            'last_reconcile_at'         => null,
            'allow_partial_payment'      => 0,
            'is_active'                  => 0,
            'created_at'                 => date('Y-m-d H:i:s'),
            'updated_at'                 => date('Y-m-d H:i:s'),
        ]);
    }

    add_option('kt_sepay_schema_version', KT_SEPAY_VERSION);
    add_option('kt_sepay_last_reconcile_transaction_id', '');
    add_option('kt_sepay_last_reconcile_at', '');
}
