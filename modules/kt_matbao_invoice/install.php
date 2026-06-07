<?php

defined('BASEPATH') or exit('No direct script access allowed');

function kt_matbao_invoice_run_install()
{
    $CI = &get_instance();

    $tables = [];
    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_matbao_invoice_settings` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id` BIGINT UNSIGNED NULL,
        `scope` VARCHAR(20) NOT NULL DEFAULT 'landlord',
        `environment` VARCHAR(20) NOT NULL DEFAULT 'demo',
        `invoice_base_url` VARCHAR(255) NOT NULL DEFAULT '',
        `sign_base_url` VARCHAR(255) NOT NULL DEFAULT '',
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
        `auto_sign` TINYINT(1) NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 0,
        `last_test_status` VARCHAR(30) NULL,
        `last_test_message` TEXT NULL,
        `last_test_at` DATETIME NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_scope_tenant` (`scope`,`tenant_id`),
        KEY `idx_tenant_id` (`tenant_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";";

    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_matbao_invoice_hddt_accounts` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";";

    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_matbao_invoice_ca_accounts` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";";

    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_matbao_invoice_records` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id` BIGINT UNSIGNED NULL,
        `source_type` VARCHAR(80) NOT NULL,
        `source_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
        `perfex_invoice_id` BIGINT UNSIGNED NULL,
        `customer_id` BIGINT UNSIGNED NULL,
        `seller_scope` VARCHAR(20) NOT NULL DEFAULT 'tenant',
        `credential_scope` VARCHAR(20) NOT NULL DEFAULT 'tenant',
        `khmshdon` VARCHAR(100) NULL,
        `khhdon` VARCHAR(100) NULL,
        `shdon` VARCHAR(100) NULL,
        `ma_tra_cuu` VARCHAR(120) NULL,
        `mt_chieu` VARCHAR(120) NULL,
        `ma_so_hdon` VARCHAR(120) NULL,
        `inv_id` VARCHAR(120) NULL,
        `fkey` VARCHAR(120) NULL,
        `mccqt` VARCHAR(120) NULL,
        `pattern` VARCHAR(100) NULL,
        `serial` VARCHAR(100) NULL,
        `so` VARCHAR(100) NULL,
        `invoice_type` VARCHAR(40) NULL,
        `local_status` VARCHAR(40) NOT NULL DEFAULT 'draft',
        `tax_status_code` VARCHAR(60) NULL,
        `tax_status_name` VARCHAR(191) NULL,
        `issue_mode` VARCHAR(40) NOT NULL DEFAULT 'draft_only',
        `nlap` DATETIME NULL,
        `total_before_tax` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `total_tax` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `pdf_url` TEXT NULL,
        `xml_url` TEXT NULL,
        `pdf_file_path` TEXT NULL,
        `xml_file_path` TEXT NULL,
        `raw_request_snapshot` LONGTEXT NULL,
        `raw_response_snapshot` LONGTEXT NULL,
        `error_code` VARCHAR(80) NULL,
        `error_message` TEXT NULL,
        `created_by` INT NULL,
        `issued_at` DATETIME NULL,
        `signed_at` DATETIME NULL,
        `cancelled_at` DATETIME NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_tenant_id` (`tenant_id`),
        KEY `idx_source` (`source_type`,`source_id`),
        KEY `idx_ma_so_hdon` (`ma_so_hdon`),
        KEY `idx_ma_tra_cuu` (`ma_tra_cuu`),
        KEY `idx_local_status` (`local_status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";";

    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_matbao_invoice_items_snapshot` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `record_id` BIGINT UNSIGNED NOT NULL,
        `item_source_id` BIGINT UNSIGNED NULL,
        `tchat` VARCHAR(20) NULL,
        `stt` VARCHAR(20) NULL,
        `mhhdvu` VARCHAR(100) NULL,
        `thhdvu` VARCHAR(191) NULL,
        `dvtinh` VARCHAR(50) NULL,
        `sluong` DECIMAL(15,4) NOT NULL DEFAULT 0,
        `dgia` DECIMAL(15,4) NOT NULL DEFAULT 0,
        `thtien_chua_ck` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `tlckhau` DECIMAL(8,4) NOT NULL DEFAULT 0,
        `stckhau` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `thtien` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `tsuat` DECIMAL(8,4) NOT NULL DEFAULT 0,
        `tthue` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `tgtien` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `raw_json` LONGTEXT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_record_id` (`record_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";";

    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_matbao_invoice_templates` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id` BIGINT UNSIGNED NULL,
        `scope` VARCHAR(20) NOT NULL DEFAULT 'landlord',
        `year` INT NOT NULL,
        `khmshdon` VARCHAR(100) NOT NULL,
        `khhdon` VARCHAR(100) NOT NULL,
        `thdon` VARCHAR(100) NULL,
        `sluong` INT NULL,
        `clai` INT NULL,
        `raw_json` LONGTEXT NULL,
        `synced_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_scope_tenant_year` (`scope`,`tenant_id`,`year`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";";

    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_matbao_invoice_logs` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id` BIGINT UNSIGNED NULL,
        `record_id` BIGINT UNSIGNED NULL,
        `action` VARCHAR(80) NOT NULL,
        `endpoint` VARCHAR(255) NULL,
        `method` VARCHAR(10) NULL,
        `request_payload` LONGTEXT NULL,
        `response_payload` LONGTEXT NULL,
        `http_code` INT NULL,
        `success` TINYINT(1) NOT NULL DEFAULT 0,
        `error_code` VARCHAR(80) NULL,
        `error_message` TEXT NULL,
        `latency_ms` INT NULL,
        `created_by` INT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_tenant_id` (`tenant_id`),
        KEY `idx_action` (`action`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";";

    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_matbao_invoice_webhook_logs` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id` BIGINT UNSIGNED NULL,
        `record_id` BIGINT UNSIGNED NULL,
        `provider` VARCHAR(50) NOT NULL DEFAULT 'matbao',
        `payload` LONGTEXT NULL,
        `inv_id` VARCHAR(120) NULL,
        `ma_so_hdon` VARCHAR(120) NULL,
        `ma_tra_cuu` VARCHAR(120) NULL,
        `status_code` VARCHAR(60) NULL,
        `status_name` VARCHAR(191) NULL,
        `processed` TINYINT(1) NOT NULL DEFAULT 0,
        `error_message` TEXT NULL,
        `received_at` DATETIME NOT NULL,
        `processed_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        KEY `idx_processed` (`processed`),
        KEY `idx_lookup` (`ma_so_hdon`,`ma_tra_cuu`,`inv_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";";

    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_matbao_invoice_usage` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id` BIGINT UNSIGNED NULL,
        `period_date` DATE NOT NULL,
        `period_month` VARCHAR(7) NOT NULL,
        `created_count` INT NOT NULL DEFAULT 0,
        `issued_count` INT NOT NULL DEFAULT 0,
        `signed_count` INT NOT NULL DEFAULT 0,
        `failed_count` INT NOT NULL DEFAULT 0,
        `quota_daily` INT NOT NULL DEFAULT 0,
        `quota_monthly` INT NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_tenant_period` (`tenant_id`,`period_date`,`period_month`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";";

    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_matbao_invoice_plan_entitlements` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `plan_id` BIGINT UNSIGNED NOT NULL,
        `feature_key` VARCHAR(120) NOT NULL,
        `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
        `limit_value` VARCHAR(191) NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_plan_feature` (`plan_id`,`feature_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";";

    // Reseller add-on billing foundation (Phase next)
    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_saas_reseller_packages` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `provider` VARCHAR(40) NOT NULL DEFAULT 'matbao',
        `service_type` VARCHAR(40) NOT NULL,
        `package_code` VARCHAR(120) NOT NULL,
        `package_name` VARCHAR(191) NOT NULL,
        `description` TEXT NULL,
        `quantity` DECIMAL(15,4) NOT NULL DEFAULT 1.0000,
        `unit` VARCHAR(30) NULL,
        `duration_days` INT NULL,
        `duration_months` INT NULL,
        `duration_years` INT NULL,
        `price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `currency` VARCHAR(10) NOT NULL DEFAULT 'VND',
        `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `setup_fee` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `is_stackable` TINYINT(1) NOT NULL DEFAULT 1,
        `requires_registration` TINYINT(1) NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order` INT NOT NULL DEFAULT 0,
        `raw_metadata` LONGTEXT NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_package_code` (`package_code`),
        KEY `idx_provider_service` (`provider`,`service_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";";

    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_saas_orders` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id` BIGINT UNSIGNED NULL,
        `customer_id` BIGINT UNSIGNED NULL,
        `order_code` VARCHAR(120) NOT NULL,
        `order_type` VARCHAR(40) NOT NULL DEFAULT 'tenant_signup',
        `status` VARCHAR(40) NOT NULL DEFAULT 'draft',
        `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `discount_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `tax_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `grand_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `currency` VARCHAR(10) NOT NULL DEFAULT 'VND',
        `payment_method` VARCHAR(40) NULL,
        `payment_status` VARCHAR(30) NOT NULL DEFAULT 'pending',
        `paid_at` DATETIME NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_order_code` (`order_code`),
        KEY `idx_tenant_status` (`tenant_id`,`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";";

    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_saas_order_items` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `order_id` BIGINT UNSIGNED NOT NULL,
        `item_type` VARCHAR(40) NOT NULL,
        `ref_id` BIGINT UNSIGNED NULL,
        `item_code` VARCHAR(120) NULL,
        `item_name` VARCHAR(191) NOT NULL,
        `description` TEXT NULL,
        `quantity` DECIMAL(15,4) NOT NULL DEFAULT 1.0000,
        `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `tax_rate` DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
        `tax_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `metadata_json` LONGTEXT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_order_id` (`order_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";";

    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_saas_tenant_addons` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id` BIGINT UNSIGNED NOT NULL,
        `subscription_id` BIGINT UNSIGNED NULL,
        `order_id` BIGINT UNSIGNED NULL,
        `package_id` BIGINT UNSIGNED NOT NULL,
        `provider` VARCHAR(40) NOT NULL DEFAULT 'matbao',
        `service_type` VARCHAR(40) NOT NULL,
        `package_code` VARCHAR(120) NOT NULL,
        `quantity_purchased` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
        `quantity_used` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
        `quantity_remaining` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
        `starts_at` DATETIME NULL,
        `ends_at` DATETIME NULL,
        `status` VARCHAR(40) NOT NULL DEFAULT 'pending_payment',
        `provider_account_id` VARCHAR(120) NULL,
        `provider_order_code` VARCHAR(120) NULL,
        `provider_status` VARCHAR(60) NULL,
        `provisioning_job_id` BIGINT UNSIGNED NULL,
        `notes` TEXT NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_tenant_service_status` (`tenant_id`,`service_type`,`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";";

    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_saas_addon_usage_logs` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id` BIGINT UNSIGNED NOT NULL,
        `addon_id` BIGINT UNSIGNED NOT NULL,
        `service_type` VARCHAR(40) NOT NULL,
        `action` VARCHAR(40) NOT NULL,
        `quantity_delta` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
        `before_quantity` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
        `after_quantity` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
        `reference_type` VARCHAR(60) NULL,
        `reference_id` BIGINT UNSIGNED NULL,
        `created_by` INT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_tenant_addon` (`tenant_id`,`addon_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";";

    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_saas_provider_provisioning_jobs` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id` BIGINT UNSIGNED NOT NULL,
        `addon_id` BIGINT UNSIGNED NULL,
        `provider` VARCHAR(40) NOT NULL DEFAULT 'matbao',
        `service_type` VARCHAR(40) NOT NULL,
        `job_type` VARCHAR(60) NOT NULL,
        `status` VARCHAR(40) NOT NULL DEFAULT 'queued',
        `request_payload` LONGTEXT NULL,
        `response_payload` LONGTEXT NULL,
        `error_message` TEXT NULL,
        `attempts` INT NOT NULL DEFAULT 0,
        `next_retry_at` DATETIME NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_status_retry` (`status`,`next_retry_at`),
        KEY `idx_tenant_provider` (`tenant_id`,`provider`,`service_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";";

    foreach ($tables as $sql) {
        $CI->db->query($sql);
    }

    $defaultPackages = [
        [
            'provider' => 'matbao',
            'service_type' => 'einvoice',
            'package_code' => 'EINV-300',
            'package_name' => 'Hóa đơn điện tử 300',
            'description' => 'Gói 300 hóa đơn điện tử',
            'quantity' => 300,
            'unit' => 'invoice',
            'duration_days' => 365,
            'price' => 450000,
            'currency' => 'VND',
            'unit_price' => 1500,
            'is_stackable' => 1,
            'requires_registration' => 0,
            'is_active' => 1,
            'sort_order' => 10,
        ],
        [
            'provider' => 'matbao',
            'service_type' => 'einvoice',
            'package_code' => 'EINV-1000',
            'package_name' => 'Hóa đơn điện tử 1.000',
            'description' => 'Gói 1.000 hóa đơn điện tử',
            'quantity' => 1000,
            'unit' => 'invoice',
            'duration_days' => 365,
            'price' => 1300000,
            'currency' => 'VND',
            'unit_price' => 1300,
            'is_stackable' => 1,
            'requires_registration' => 0,
            'is_active' => 1,
            'sort_order' => 20,
        ],
        [
            'provider' => 'matbao',
            'service_type' => 'hsm_signature',
            'package_code' => 'HSM-1Y-BUS',
            'package_name' => 'HSM 1 năm doanh nghiệp',
            'description' => 'Gói chữ ký số tập trung 1 năm',
            'quantity' => 1,
            'unit' => 'license',
            'duration_years' => 1,
            'price' => 1800000,
            'currency' => 'VND',
            'unit_price' => 1800000,
            'is_stackable' => 0,
            'requires_registration' => 1,
            'is_active' => 1,
            'sort_order' => 30,
        ],
        [
            'provider' => 'matbao',
            'service_type' => 'hsm_signature',
            'package_code' => 'HSM-3Y-BUS',
            'package_name' => 'HSM 3 năm doanh nghiệp',
            'description' => 'Gói chữ ký số tập trung 3 năm',
            'quantity' => 1,
            'unit' => 'license',
            'duration_years' => 3,
            'price' => 4200000,
            'currency' => 'VND',
            'unit_price' => 4200000,
            'is_stackable' => 0,
            'requires_registration' => 1,
            'is_active' => 1,
            'sort_order' => 40,
        ],
    ];
    $packagesTable = db_prefix() . 'kt_saas_reseller_packages';
    foreach ($defaultPackages as $package) {
        $existing = $CI->db->where('package_code', $package['package_code'])->get($packagesTable)->row_array();
        if ($existing) {
            $currentName = trim((string) ($existing['package_name'] ?? ''));
            $currentDescription = trim((string) ($existing['description'] ?? ''));
            if (
                $currentName === ''
                || $currentDescription === ''
                || strpos($currentName, '?') !== false
                || strpos($currentDescription, '?') !== false
            ) {
                $CI->db->where('id', (int) $existing['id'])->update($packagesTable, [
                    'package_name' => $package['package_name'],
                    'description' => $package['description'],
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            continue;
        }

        $package['setup_fee'] = 0;
        $package['raw_metadata'] = null;
        $package['created_at'] = date('Y-m-d H:i:s');
        $package['updated_at'] = $package['created_at'];
        $CI->db->insert($packagesTable, $package);
    }

    $recordsTable = db_prefix() . 'kt_matbao_invoice_records';
    if ($CI->db->table_exists($recordsTable)) {
        if (!$CI->db->field_exists('ca_document_id', $recordsTable)) {
            $CI->db->query("ALTER TABLE `{$recordsTable}` ADD COLUMN `ca_document_id` VARCHAR(120) NULL AFTER `fkey`;");
            $CI->db->query("ALTER TABLE `{$recordsTable}` ADD KEY `idx_ca_document_id` (`ca_document_id`);");
        }
        if (!$CI->db->field_exists('signing_provider_status', $recordsTable)) {
            $CI->db->query("ALTER TABLE `{$recordsTable}` ADD COLUMN `signing_provider_status` VARCHAR(60) NULL AFTER `tax_status_name`;");
        }
    }

    $webhookTable = db_prefix() . 'kt_matbao_invoice_webhook_logs';
    if ($CI->db->table_exists($webhookTable) && !$CI->db->field_exists('document_id', $webhookTable)) {
        $CI->db->query("ALTER TABLE `{$webhookTable}` ADD COLUMN `document_id` VARCHAR(120) NULL AFTER `inv_id`;");
        $CI->db->query("ALTER TABLE `{$webhookTable}` ADD KEY `idx_document_id` (`document_id`);");
    }

    add_option('kt_matbao_invoice_schema_version', KT_MATBAO_INVOICE_VERSION);
    add_option('kt_matbao_invoice_webhook_secret', '');
}
