<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * KT eInvoice — install.php
 * Tạo tất cả bảng DB và seed dữ liệu mặc định
 */

function kt_einvoice_run_install()
{
    $CI      = &get_instance();
    $charset = $CI->db->char_set . ' COLLATE utf8mb4_unicode_ci';

    $tables = [];

    // ── 1. Provider Settings (cấu hình SePay per-tenant) ─────────────────────
    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_einvoice_provider_settings` (
        `id`                        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id`                 BIGINT UNSIGNED NOT NULL,
        `environment`               VARCHAR(16) NOT NULL DEFAULT 'sandbox',

        -- SePay credentials (mã hóa AES-256)
        `api_username_encrypted`    TEXT NULL,
        `api_username_iv`           VARCHAR(64) NULL,
        `api_password_encrypted`    TEXT NULL,
        `api_password_iv`           VARCHAR(64) NULL,

        -- Cached access token
        `access_token_encrypted`    TEXT NULL,
        `access_token_iv`           VARCHAR(64) NULL,
        `token_expires_at`          DATETIME NULL,

        -- Cấu hình nhà cung cấp
        `provider_account_id`       VARCHAR(64) NULL,
        `provider_account_name`     VARCHAR(255) NULL,
        `invoice_series`            VARCHAR(32) NULL,
        `invoice_template_code`     VARCHAR(64) NULL DEFAULT '01GTKT',

        -- Thông tin người bán (seller) — tenant tự nhập
        `seller_tax_code`           VARCHAR(20) NULL,
        `seller_name`               VARCHAR(255) NULL,
        `seller_address`            VARCHAR(500) NULL,
        `seller_phone`              VARCHAR(50) NULL,
        `seller_email`              VARCHAR(191) NULL,
        `seller_bank_name`          VARCHAR(191) NULL,
        `seller_bank_account`       VARCHAR(64) NULL,

        -- Tùy chọn
        `auto_issue_on_payment`     TINYINT(1) NOT NULL DEFAULT 0,
        `is_active`                 TINYINT(1) NOT NULL DEFAULT 0,

        -- Cache quota từ SePay
        `quota_remaining`           INT UNSIGNED NULL,
        `last_quota_synced_at`      DATETIME NULL,
        `last_token_refreshed_at`   DATETIME NULL,

        `metadata_json`             LONGTEXT NULL,
        `created_at`                DATETIME NOT NULL,
        `updated_at`                DATETIME NOT NULL,

        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_tenant_env` (`tenant_id`, `environment`),
        KEY `idx_tenant_id` (`tenant_id`),
        KEY `idx_is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // ── 2. eInvoice Records (bản ghi từng hóa đơn điện tử) ───────────────────
    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_einvoice_records` (
        `id`                        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id`                 BIGINT UNSIGNED NOT NULL,
        `perfex_invoice_id`         BIGINT UNSIGNED NOT NULL,
        `perfex_invoice_number`     VARCHAR(64) NOT NULL DEFAULT '',
        `environment`               VARCHAR(16) NOT NULL DEFAULT 'sandbox',

        -- SePay tracking
        `sepay_invoice_id`          VARCHAR(128) NULL,
        `sepay_tracking_code`       VARCHAR(128) NULL,
        `sepay_issue_tracking`      VARCHAR(128) NULL,
        `sepay_cancel_tracking`     VARCHAR(128) NULL,

        -- Trạng thái
        `status`                    VARCHAR(32) NOT NULL DEFAULT 'pending_create',
        `status_message`            VARCHAR(500) NULL,

        -- Thông tin hóa đơn đã phát hành
        `invoice_number`            VARCHAR(64) NULL,
        `invoice_series`            VARCHAR(32) NULL,
        `invoice_template`          VARCHAR(64) NULL,
        `invoice_date`              DATE NULL,
        `buyer_tax_code`            VARCHAR(20) NULL,
        `buyer_name`                VARCHAR(255) NULL,
        `total_amount`              DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `tax_amount`                DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `currency`                  VARCHAR(10) NOT NULL DEFAULT 'VND',

        -- Files
        `pdf_url`                   TEXT NULL,
        `xml_url`                   TEXT NULL,
        `pdf_downloaded_at`         DATETIME NULL,

        -- Hủy / Điều chỉnh
        `cancelled_at`              DATETIME NULL,
        `cancel_reason`             VARCHAR(500) NULL,
        `adjustment_of_record_id`   BIGINT UNSIGNED NULL,

        -- Idempotency (UNIQUE: hash tenant+invoice+env)
        `idempotency_key`           VARCHAR(128) NOT NULL,

        -- Retry tracking
        `create_attempts`           INT UNSIGNED NOT NULL DEFAULT 0,
        `issue_attempts`            INT UNSIGNED NOT NULL DEFAULT 0,
        `cancel_attempts`           INT UNSIGNED NOT NULL DEFAULT 0,
        `last_attempt_at`           DATETIME NULL,
        `next_retry_at`             DATETIME NULL,

        -- Payloads (debug / audit)
        `request_payload_json`      LONGTEXT NULL,
        `response_payload_json`     LONGTEXT NULL,

        `issued_at`                 DATETIME NULL,
        `created_by`                INT UNSIGNED NULL,
        `created_at`                DATETIME NOT NULL,
        `updated_at`                DATETIME NOT NULL,
        `deleted_at`                DATETIME NULL,

        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_idempotency` (`idempotency_key`),
        KEY `uniq_sepay_id` (`sepay_invoice_id`),
        KEY `idx_tenant_invoice` (`tenant_id`, `perfex_invoice_id`),
        KEY `idx_status` (`status`),
        KEY `idx_sepay_tracking` (`sepay_tracking_code`),
        KEY `idx_sepay_issue_tracking` (`sepay_issue_tracking`),
        KEY `idx_issued_at` (`issued_at`),
        KEY `idx_next_retry` (`next_retry_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // ── 3. API Logs ───────────────────────────────────────────────────────────
    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_einvoice_api_logs` (
        `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id`     BIGINT UNSIGNED NOT NULL,
        `record_id`     BIGINT UNSIGNED NULL,
        `environment`   VARCHAR(16) NOT NULL DEFAULT 'sandbox',
        `action`        VARCHAR(64) NOT NULL,
        `endpoint`      VARCHAR(255) NOT NULL DEFAULT '',
        `method`        VARCHAR(8) NOT NULL DEFAULT 'POST',
        `request_json`  LONGTEXT NULL,
        `response_code` INT UNSIGNED NULL,
        `response_json` LONGTEXT NULL,
        `latency_ms`    INT UNSIGNED NULL,
        `success`       TINYINT(1) NOT NULL DEFAULT 0,
        `error_code`    VARCHAR(64) NULL,
        `error_message` TEXT NULL,
        `created_at`    DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_tenant_id` (`tenant_id`),
        KEY `idx_record_id` (`record_id`),
        KEY `idx_action` (`action`),
        KEY `idx_success` (`success`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // ── 4. Job Queue (async operations) ──────────────────────────────────────
    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_einvoice_jobs` (
        `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id`     BIGINT UNSIGNED NOT NULL,
        `record_id`     BIGINT UNSIGNED NULL,
        `job_type`      VARCHAR(64) NOT NULL,
        `priority`      INT NOT NULL DEFAULT 5,
        `status`        VARCHAR(32) NOT NULL DEFAULT 'queued',
        `payload_json`  LONGTEXT NULL,
        `attempts`      INT NOT NULL DEFAULT 0,
        `max_attempts`  INT NOT NULL DEFAULT 3,
        `last_error`    TEXT NULL,
        `scheduled_at`  DATETIME NOT NULL,
        `started_at`    DATETIME NULL,
        `finished_at`   DATETIME NULL,
        `created_at`    DATETIME NOT NULL,
        `updated_at`    DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_status_scheduled` (`status`, `scheduled_at`),
        KEY `idx_tenant_id` (`tenant_id`),
        KEY `idx_job_type` (`job_type`),
        KEY `idx_record_id` (`record_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // ── 5. Quota Usage (theo dõi usage per-tenant per-month) ─────────────────
    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_einvoice_quota_usage` (
        `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id`     BIGINT UNSIGNED NOT NULL,
        `environment`   VARCHAR(16) NOT NULL DEFAULT 'production',
        `period_year`   SMALLINT UNSIGNED NOT NULL,
        `period_month`  TINYINT UNSIGNED NOT NULL,
        `plan_quota`    INT UNSIGNED NOT NULL DEFAULT 0,
        `sepay_quota`   INT UNSIGNED NULL,
        `used_count`    INT UNSIGNED NOT NULL DEFAULT 0,
        `failed_count`  INT UNSIGNED NOT NULL DEFAULT 0,
        `created_at`    DATETIME NOT NULL,
        `updated_at`    DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_tenant_period` (`tenant_id`, `period_year`, `period_month`, `environment`),
        KEY `idx_tenant_id` (`tenant_id`),
        KEY `idx_period` (`period_year`, `period_month`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // ── 6. Batch Sessions ─────────────────────────────────────────────────────
    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_einvoice_batch_sessions` (
        `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id`         BIGINT UNSIGNED NOT NULL,
        `environment`       VARCHAR(16) NOT NULL DEFAULT 'production',
        `session_code`      VARCHAR(64) NOT NULL,
        `status`            VARCHAR(32) NOT NULL DEFAULT 'pending',
        `total_count`       INT UNSIGNED NOT NULL DEFAULT 0,
        `success_count`     INT UNSIGNED NOT NULL DEFAULT 0,
        `failed_count`      INT UNSIGNED NOT NULL DEFAULT 0,
        `invoice_ids_json`  LONGTEXT NULL,
        `error_summary`     TEXT NULL,
        `started_at`        DATETIME NULL,
        `finished_at`       DATETIME NULL,
        `created_by`        INT UNSIGNED NULL,
        `created_at`        DATETIME NOT NULL,
        `updated_at`        DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_session_code` (`session_code`),
        KEY `idx_tenant_id` (`tenant_id`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // ── 7. Batch Items ────────────────────────────────────────────────────────
    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_einvoice_batch_items` (
        `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `batch_id`              BIGINT UNSIGNED NOT NULL,
        `tenant_id`             BIGINT UNSIGNED NOT NULL,
        `record_id`             BIGINT UNSIGNED NULL,
        `perfex_invoice_id`     BIGINT UNSIGNED NOT NULL,
        `status`                VARCHAR(32) NOT NULL DEFAULT 'queued',
        `error_message`         TEXT NULL,
        `processed_at`          DATETIME NULL,
        `created_at`            DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_batch_id` (`batch_id`),
        KEY `idx_tenant_id` (`tenant_id`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // ── 8. Cron Logs ──────────────────────────────────────────────────────────
    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_einvoice_cron_logs` (
        `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `cron_name`         VARCHAR(64) NOT NULL,
        `tenant_id`         BIGINT UNSIGNED NULL,
        `status`            VARCHAR(32) NOT NULL DEFAULT 'success',
        `total_processed`   INT UNSIGNED NOT NULL DEFAULT 0,
        `total_updated`     INT UNSIGNED NOT NULL DEFAULT 0,
        `total_errors`      INT UNSIGNED NOT NULL DEFAULT 0,
        `details_json`      LONGTEXT NULL,
        `started_at`        DATETIME NOT NULL,
        `finished_at`       DATETIME NULL,
        `duration_ms`       INT UNSIGNED NULL,
        `created_at`        DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_cron_name` (`cron_name`),
        KEY `idx_status` (`status`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // eInvoice plan feature values (typed values per plan)
    $tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_einvoice_plan_features` (
        `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `plan_id`       BIGINT UNSIGNED NOT NULL,
        `feature_key`   VARCHAR(100) NOT NULL,
        `feature_value` VARCHAR(191) NOT NULL,
        `created_at`    DATETIME NOT NULL,
        `updated_at`    DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_plan_feature` (`plan_id`, `feature_key`),
        KEY `idx_plan_id` (`plan_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // ── Chạy tất cả CREATE TABLE ──────────────────────────────────────────────
    foreach ($tables as $sql) {
        $CI->db->query($sql);
    }

    // ── Seed options ──────────────────────────────────────────────────────────
    add_option('kt_einvoice_schema_version',          KT_EINVOICE_VERSION);
    add_option('kt_einvoice_status_checker_last_run', '');
    add_option('kt_einvoice_batch_issuer_last_run',   '');
    add_option('kt_einvoice_quota_sync_last_run',     '');

    log_message('info', '[kt_einvoice] Installation completed — version ' . KT_EINVOICE_VERSION);
}

// Autoload config nếu chưa có
if (!defined('KT_EINVOICE_VERSION')) {
    require_once __DIR__ . '/config/kt_einvoice_config.php';
}
