<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
$charset = $CI->db->char_set;

$tables = [
    'kt_saas_tenants' => "CREATE TABLE `%skt_saas_tenants` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_code` VARCHAR(64) NOT NULL,
        `company_name` VARCHAR(191) NOT NULL,
        `owner_name` VARCHAR(191) NOT NULL,
        `owner_email` VARCHAR(191) NOT NULL,
        `phone` VARCHAR(50) NULL,
        `status` VARCHAR(30) NOT NULL DEFAULT 'draft',
        `plan_id` BIGINT UNSIGNED NULL,
        `db_name` VARCHAR(191) NULL,
        `db_host` VARCHAR(191) NULL,
        `db_port` VARCHAR(10) NULL,
        `db_user` VARCHAR(191) NULL,
        `db_password_encrypted` TEXT NULL,
        `subdomain` VARCHAR(191) NULL,
        `custom_domain` VARCHAR(191) NULL,
        `timezone` VARCHAR(64) NULL,
        `locale` VARCHAR(32) NULL,
        `currency` VARCHAR(10) NULL,
        `storage_driver` VARCHAR(30) NOT NULL DEFAULT 'local',
        `storage_path` VARCHAR(255) NULL,
        `provisioning_status` VARCHAR(30) NOT NULL DEFAULT 'queued',
        `last_provisioned_at` DATETIME NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        `expires_at` DATETIME NULL,
        `suspended_at` DATETIME NULL,
        `terminated_at` DATETIME NULL,
        `deleted_at` DATETIME NULL,
        `created_by` INT NULL,
        `updated_by` INT NULL,
        `deleted_by` INT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `tenant_code_unique` (`tenant_code`),
        UNIQUE KEY `owner_email_unique` (`owner_email`),
        UNIQUE KEY `subdomain_unique` (`subdomain`),
        KEY `status_idx` (`status`),
        KEY `plan_id_idx` (`plan_id`),
        KEY `expires_at_idx` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=%s;",
    'kt_saas_plans' => "CREATE TABLE `%skt_saas_plans` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `plan_code` VARCHAR(64) NOT NULL,
        `plan_name` VARCHAR(191) NOT NULL,
        `billing_cycle` VARCHAR(30) NOT NULL DEFAULT 'monthly',
        `price` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `setup_fee` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
        `trial_days` INT NOT NULL DEFAULT 0,
        `grace_days` INT NOT NULL DEFAULT 0,
        `is_public` TINYINT(1) NOT NULL DEFAULT 1,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `module_json` LONGTEXT NULL,
        `limit_staff` INT NOT NULL DEFAULT 0,
        `limit_clients` INT NOT NULL DEFAULT 0,
        `limit_storage_mb` INT NOT NULL DEFAULT 0,
        `limit_invoices` INT NOT NULL DEFAULT 0,
        `limit_projects` INT NOT NULL DEFAULT 0,
        `limit_api_requests_daily` INT NOT NULL DEFAULT 0,
        `limit_warehouses` INT NOT NULL DEFAULT 0,
        `limit_automations` INT NOT NULL DEFAULT 0,
        `limit_roles` INT NOT NULL DEFAULT 0,
        `limit_departments` INT NOT NULL DEFAULT 0,
        `limit_governance_viewers` INT NOT NULL DEFAULT 0,
        `limit_governance_managers` INT NOT NULL DEFAULT 0,
        `sort_order` INT NOT NULL DEFAULT 0,
        `notes` TEXT NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        `deleted_at` DATETIME NULL,
        `created_by` INT NULL,
        `updated_by` INT NULL,
        `deleted_by` INT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `plan_code_unique` (`plan_code`),
        KEY `is_active_idx` (`is_active`),
        KEY `is_public_idx` (`is_public`)
    ) ENGINE=InnoDB DEFAULT CHARSET=%s;",
    'kt_saas_subscriptions' => "CREATE TABLE `%skt_saas_subscriptions` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id` BIGINT UNSIGNED NOT NULL,
        `plan_id` BIGINT UNSIGNED NOT NULL,
        `status` VARCHAR(30) NOT NULL DEFAULT 'trial',
        `billing_cycle` VARCHAR(30) NOT NULL DEFAULT 'monthly',
        `started_at` DATETIME NOT NULL,
        `trial_ends_at` DATETIME NULL,
        `current_period_start_at` DATETIME NULL,
        `current_period_end_at` DATETIME NULL,
        `grace_ends_at` DATETIME NULL,
        `cancelled_at` DATETIME NULL,
        `terminated_at` DATETIME NULL,
        `next_billing_at` DATETIME NULL,
        `renewal_attempts` INT NOT NULL DEFAULT 0,
        `auto_renew` TINYINT(1) NOT NULL DEFAULT 1,
        `metadata_json` LONGTEXT NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        `deleted_at` DATETIME NULL,
        `created_by` INT NULL,
        `updated_by` INT NULL,
        `deleted_by` INT NULL,
        PRIMARY KEY (`id`),
        KEY `tenant_id_idx` (`tenant_id`),
        KEY `plan_id_idx` (`plan_id`),
        KEY `status_idx` (`status`),
        KEY `next_billing_at_idx` (`next_billing_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=%s;",
    'kt_saas_invoices' => "CREATE TABLE `%skt_saas_invoices` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id` BIGINT UNSIGNED NOT NULL,
        `subscription_id` BIGINT UNSIGNED NULL,
        `invoice_number` VARCHAR(64) NOT NULL,
        `status` VARCHAR(30) NOT NULL DEFAULT 'draft',
        `currency` VARCHAR(10) NOT NULL,
        `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `tax_total` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `discount_total` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `grand_total` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `issued_at` DATETIME NULL,
        `due_date` DATE NULL,
        `paid_at` DATETIME NULL,
        `last_reminder_at` DATETIME NULL,
        `reminder_count` INT NOT NULL DEFAULT 0,
        `gateway` VARCHAR(50) NULL,
        `coupon_code` VARCHAR(64) NULL,
        `payload_json` LONGTEXT NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        `deleted_at` DATETIME NULL,
        `created_by` INT NULL,
        `updated_by` INT NULL,
        `deleted_by` INT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `invoice_number_unique` (`invoice_number`),
        KEY `tenant_id_idx` (`tenant_id`),
        KEY `subscription_id_idx` (`subscription_id`),
        KEY `status_idx` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=%s;",
    'kt_saas_payments' => "CREATE TABLE `%skt_saas_payments` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id` BIGINT UNSIGNED NOT NULL,
        `invoice_id` BIGINT UNSIGNED NULL,
        `payment_reference` VARCHAR(128) NOT NULL,
        `gateway` VARCHAR(50) NOT NULL,
        `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
        `amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `currency` VARCHAR(10) NOT NULL,
        `gateway_payload_json` LONGTEXT NULL,
        `paid_at` DATETIME NULL,
        `failed_at` DATETIME NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        `deleted_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `payment_reference_unique` (`payment_reference`),
        KEY `tenant_id_idx` (`tenant_id`),
        KEY `invoice_id_idx` (`invoice_id`),
        KEY `status_idx` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=%s;",
    'kt_saas_domains' => "CREATE TABLE `%skt_saas_domains` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id` BIGINT UNSIGNED NOT NULL,
        `domain` VARCHAR(191) NOT NULL,
        `domain_type` VARCHAR(30) NOT NULL DEFAULT 'subdomain',
        `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
        `readiness_status` VARCHAR(30) NOT NULL DEFAULT 'pending',
        `expected_target` VARCHAR(191) NULL,
        `ssl_status` VARCHAR(30) NOT NULL DEFAULT 'pending',
        `dns_status` VARCHAR(30) NOT NULL DEFAULT 'pending',
        `last_checked_at` DATETIME NULL,
        `verified_at` DATETIME NULL,
        `dns_records_json` LONGTEXT NULL,
        `ssl_details_json` LONGTEXT NULL,
        `verification_message` TEXT NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        `deleted_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `domain_unique` (`domain`),
        KEY `tenant_id_idx` (`tenant_id`),
        KEY `domain_type_idx` (`domain_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=%s;",
    'kt_saas_modules' => "CREATE TABLE `%skt_saas_modules` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id` BIGINT UNSIGNED NULL,
        `module_name` VARCHAR(191) NOT NULL,
        `module_type` VARCHAR(30) NOT NULL DEFAULT 'core',
        `status` VARCHAR(30) NOT NULL DEFAULT 'enabled',
        `source` VARCHAR(30) NOT NULL DEFAULT 'internal',
        `price` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `dependency_json` LONGTEXT NULL,
        `notes` TEXT NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        `deleted_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `tenant_module_unique` (`tenant_id`,`module_name`),
        KEY `module_name_idx` (`module_name`),
        KEY `status_idx` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=%s;",
    'kt_saas_usage' => "CREATE TABLE `%skt_saas_usage` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id` BIGINT UNSIGNED NOT NULL,
        `module_name` VARCHAR(191) NOT NULL,
        `metric_key` VARCHAR(100) NOT NULL,
        `used_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `limit_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `period_start` DATETIME DEFAULT NULL,
        `period_end` DATETIME DEFAULT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `tenant_usage_unique` (`tenant_id`, `module_name`, `metric_key`),
        KEY `tenant_id_idx` (`tenant_id`),
        KEY `module_name_idx` (`module_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=%s;",
    'kt_saas_module_catalog' => "CREATE TABLE `%skt_saas_module_catalog` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `module_name` VARCHAR(191) NOT NULL,
        `display_name` VARCHAR(191) NOT NULL,
        `slug` VARCHAR(100) NOT NULL,
        `description` TEXT NULL,
        `category` VARCHAR(50) DEFAULT 'general',
        `version` VARCHAR(30) NOT NULL,
        `is_core` TINYINT NOT NULL DEFAULT 0,
        `is_global_active` TINYINT NOT NULL DEFAULT 1,
        `has_ui` TINYINT NOT NULL DEFAULT 1,
        `has_routes` TINYINT NOT NULL DEFAULT 1,
        `has_cron` TINYINT NOT NULL DEFAULT 0,
        `detected_from` VARCHAR(50) DEFAULT 'system',
        `synced_at` DATETIME NOT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `module_name_unique` (`module_name`),
        UNIQUE KEY `slug_unique` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=%s;",
    'kt_saas_plan_features' => "CREATE TABLE `%skt_saas_plan_features` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `plan_id` BIGINT UNSIGNED NOT NULL,
        `module_name` VARCHAR(191) NOT NULL,
        `feature_key` VARCHAR(100) NOT NULL,
        `is_enabled` TINYINT NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `plan_feature_unique` (`plan_id`, `module_name`, `feature_key`),
        KEY `plan_id_idx` (`plan_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=%s;",
    'kt_saas_tenant_entitlements' => "CREATE TABLE `%skt_saas_tenant_entitlements` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id` BIGINT UNSIGNED NOT NULL,
        `module_name` VARCHAR(191) NOT NULL,
        `feature_key` VARCHAR(100) NOT NULL,
        `is_enabled` TINYINT NOT NULL DEFAULT 1,
        `source_plan_id` BIGINT UNSIGNED DEFAULT NULL,
        `overridden` TINYINT NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `tenant_entitlement_unique` (`tenant_id`, `module_name`, `feature_key`),
        KEY `tenant_id_idx` (`tenant_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=%s;",
    'kt_saas_email_event_guards' => "CREATE TABLE `%skt_saas_email_event_guards` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `event_key` VARCHAR(100) NOT NULL,
        `dedupe_key` VARCHAR(64) NOT NULL,
        `tenant_id` BIGINT UNSIGNED NULL,
        `resource_type` VARCHAR(100) NULL,
        `resource_id` VARCHAR(191) NULL,
        `recipient_scope` VARCHAR(50) NOT NULL DEFAULT 'tenant_admin',
        `branding_context` VARCHAR(30) NOT NULL DEFAULT 'landlord',
        `provider_context` VARCHAR(50) NOT NULL DEFAULT 'landlord_global',
        `status` VARCHAR(30) NOT NULL DEFAULT 'reserved',
        `context_json` LONGTEXT NULL,
        `last_error_message` TEXT NULL,
        `reserved_at` DATETIME NULL,
        `sent_at` DATETIME NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `event_guard_unique` (`event_key`, `dedupe_key`),
        KEY `tenant_idx` (`tenant_id`),
        KEY `status_idx` (`status`),
        KEY `event_key_idx` (`event_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=%s;",
    'kt_saas_activity_logs' => "CREATE TABLE `%skt_saas_activity_logs` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id` BIGINT UNSIGNED NULL,
        `actor_type` VARCHAR(30) NOT NULL,
        `actor_id` BIGINT NULL,
        `event_key` VARCHAR(64) NOT NULL,
        `severity` VARCHAR(20) NOT NULL DEFAULT 'info',
        `ip_address` VARCHAR(64) NULL,
        `user_agent` VARCHAR(255) NULL,
        `context_json` LONGTEXT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `tenant_id_idx` (`tenant_id`),
        KEY `event_key_idx` (`event_key`),
        KEY `created_at_idx` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=%s;",
    'kt_saas_provision_jobs' => "CREATE TABLE `%skt_saas_provision_jobs` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id` BIGINT UNSIGNED NULL,
        `job_type` VARCHAR(50) NOT NULL,
        `status` VARCHAR(30) NOT NULL DEFAULT 'queued',
        `attempts` INT NOT NULL DEFAULT 0,
        `max_attempts` INT NOT NULL DEFAULT 5,
        `payload_json` LONGTEXT NULL,
        `result_json` LONGTEXT NULL,
        `error_message` TEXT NULL,
        `scheduled_at` DATETIME NULL,
        `started_at` DATETIME NULL,
        `finished_at` DATETIME NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `tenant_id_idx` (`tenant_id`),
        KEY `status_idx` (`status`),
        KEY `scheduled_at_idx` (`scheduled_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=%s;",
    'kt_saas_backups' => "CREATE TABLE `%skt_saas_backups` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id` BIGINT UNSIGNED NOT NULL,
        `backup_type` VARCHAR(30) NOT NULL DEFAULT 'db',
        `status` VARCHAR(30) NOT NULL DEFAULT 'queued',
        `storage_driver` VARCHAR(30) NOT NULL DEFAULT 'local',
        `file_path` VARCHAR(255) NULL,
        `file_size_bytes` BIGINT NOT NULL DEFAULT 0,
        `checksum` VARCHAR(128) NULL,
        `started_at` DATETIME NULL,
        `completed_at` DATETIME NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `tenant_id_idx` (`tenant_id`),
        KEY `status_idx` (`status`),
        KEY `backup_type_idx` (`backup_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=%s;",
];

foreach ($tables as $table => $sql) {
    if (isset($CI->db->data_cache)) {
        $CI->db->data_cache = [];
    }
    if (!$CI->db->table_exists(db_prefix() . $table)) {
        $CI->db->query(sprintf($sql, db_prefix(), $charset));
    }
}

$domainTable = db_prefix() . 'kt_saas_domains';
$domainColumns = [
    'readiness_status'     => "ALTER TABLE `{$domainTable}` ADD `readiness_status` VARCHAR(30) NOT NULL DEFAULT 'pending' AFTER `is_primary`",
    'expected_target'      => "ALTER TABLE `{$domainTable}` ADD `expected_target` VARCHAR(191) NULL AFTER `readiness_status`",
    'last_checked_at'      => "ALTER TABLE `{$domainTable}` ADD `last_checked_at` DATETIME NULL AFTER `dns_status`",
    'dns_records_json'     => "ALTER TABLE `{$domainTable}` ADD `dns_records_json` LONGTEXT NULL AFTER `verified_at`",
    'ssl_details_json'     => "ALTER TABLE `{$domainTable}` ADD `ssl_details_json` LONGTEXT NULL AFTER `dns_records_json`",
    'verification_message' => "ALTER TABLE `{$domainTable}` ADD `verification_message` TEXT NULL AFTER `ssl_details_json`",
];

foreach ($domainColumns as $column => $sql) {
    if (!$CI->db->field_exists($column, $domainTable)) {
        $CI->db->query($sql);
    }
}

$invoiceTable = db_prefix() . 'kt_saas_invoices';
$invoiceColumns = [
    'issued_at'         => "ALTER TABLE `{$invoiceTable}` ADD `issued_at` DATETIME NULL AFTER `grand_total`",
    'last_reminder_at'  => "ALTER TABLE `{$invoiceTable}` ADD `last_reminder_at` DATETIME NULL AFTER `paid_at`",
    'reminder_count'    => "ALTER TABLE `{$invoiceTable}` ADD `reminder_count` INT NOT NULL DEFAULT 0 AFTER `last_reminder_at`",
];

foreach ($invoiceColumns as $column => $sql) {
    if ($CI->db->table_exists($invoiceTable) && !$CI->db->field_exists($column, $invoiceTable)) {
        $CI->db->query($sql);
    }
}

$now = date('Y-m-d H:i:s');
$plans = [
    ['free', 'Dùng thử nhanh', 0, 0, 0, 1, 3, 50, 512, 30, 2, 100, 1, 0, []],
    ['trial', 'Dùng thử', 0, 14, 3, 1, 5, 100, 1024, 100, 5, 250, 2, 1, []],
    ['basic', 'SME Mini', 29, 0, 5, 1, 10, 500, 5120, 500, 25, 1000, 5, 5, []],
    ['pro', 'SME', 79, 0, 7, 1, 30, 2000, 20480, 5000, 100, 10000, 20, 20, ['kt_inventory']],
    ['enterprise', 'SME Plus', 199, 0, 10, 1, 0, 0, 102400, 0, 0, 0, 0, 0, ['kt_inventory']],
];

foreach ($plans as $plan) {
    $exists = $CI->db->where('plan_code', $plan[0])->get(db_prefix() . 'kt_saas_plans')->row_array();
    if ($exists) {
        continue;
    }

    $CI->db->insert(db_prefix() . 'kt_saas_plans', [
        'plan_code'                => $plan[0],
        'plan_name'                => $plan[1],
        'billing_cycle'            => 'monthly',
        'price'                    => $plan[2],
        'currency'                 => get_base_currency() ? get_base_currency()->name : 'USD',
        'trial_days'               => $plan[3],
        'grace_days'               => $plan[4],
        'is_public'                => $plan[5],
        'is_active'                => 1,
        'module_json'              => json_encode($plan[14]),
        'limit_staff'              => $plan[6],
        'limit_clients'            => $plan[7],
        'limit_storage_mb'         => $plan[8],
        'limit_invoices'           => $plan[9],
        'limit_projects'           => $plan[10],
        'limit_api_requests_daily' => $plan[11],
        'limit_warehouses'         => $plan[12],
        'limit_automations'        => $plan[13],
        'sort_order'               => 0,
        'created_at'               => $now,
        'updated_at'               => $now,
    ]);
}

add_option('kt_saas_base_domain', 'crm.local');
add_option('kt_saas_default_db_host', '127.0.0.1');
add_option('kt_saas_default_db_port', '3306');
add_option('kt_saas_default_locale', 'english');
add_option('kt_saas_default_timezone', 'UTC');
add_option('kt_saas_default_currency', 'USD');
add_option('kt_saas_default_storage_driver', 'local');
add_option('kt_saas_queue_mode', 'database');
add_option('kt_saas_auto_create_db_user', '1');
add_option('kt_saas_db_user_prefix', 'tenant_');
add_option('kt_saas_default_db_client_hosts', 'localhost,127.0.0.1');
add_option('kt_saas_allow_custom_domains', '1');
add_option('kt_saas_runtime_enabled', '0');
add_option('kt_saas_landlord_host', parse_url(APP_BASE_URL, PHP_URL_HOST) ?: '');
add_option('kt_saas_usage_retention_days', '90');
add_option('kt_saas_backup_retention_days', '30');
add_option('kt_saas_billing_due_days', '7');
add_option('kt_saas_billing_dunning_interval_days', '2');
add_option('kt_saas_billing_dunning_max_attempts', '3');
add_option('kt_saas_payment_link_secret', APP_ENC_KEY);
add_option('kt_saas_payment_webhook_secret', APP_ENC_KEY);
add_option('kt_saas_overage_rate_json', '{"staff":5,"clients":0.1,"projects":1,"invoices":0.2,"warehouses":10,"storage_mb":0.05}');
update_option('kt_saas_schema_version', KT_SAAS_VERSION);
