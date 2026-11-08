<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
$charset = $CI->db->char_set ?: 'utf8mb4';
$collation = $CI->db->dbcollat ?: 'utf8mb4_unicode_ci';
if (stripos($collation, '0900') !== false) {
    $collation = 'utf8mb4_unicode_ci';
}

$tables = [];

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_integration_providers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider_code` VARCHAR(64) NOT NULL,
  `provider_name` VARCHAR(191) NOT NULL,
  `provider_type` VARCHAR(64) NOT NULL DEFAULT 'channel',
  `auth_type` VARCHAR(40) NOT NULL DEFAULT 'custom_hmac',
  `readiness_status` VARCHAR(30) NOT NULL DEFAULT 'planned',
  `status_message` VARCHAR(255) NULL,
  `metadata_json` MEDIUMTEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `supports_oauth` TINYINT(1) NOT NULL DEFAULT 0,
  `supports_webhook` TINYINT(1) NOT NULL DEFAULT 0,
  `supports_polling` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `provider_code` (`provider_code`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_integration_connections` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `provider_code` VARCHAR(64) NOT NULL,
  `connection_name` VARCHAR(191) NULL,
  `public_key` VARCHAR(80) NOT NULL,
  `external_account_id` VARCHAR(191) NULL,
  `external_account_name` VARCHAR(191) NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'disconnected',
  `auth_type` VARCHAR(32) NULL,
  `access_token_encrypted` MEDIUMTEXT NULL,
  `refresh_token_encrypted` MEDIUMTEXT NULL,
  `webhook_secret_encrypted` MEDIUMTEXT NULL,
  `settings_json` MEDIUMTEXT NULL,
  `last_sync_at` DATETIME NULL,
  `last_error` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_key` (`public_key`),
  KEY `tenant_provider` (`tenant_id`, `provider_code`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_integration_webhook_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NULL,
  `connection_id` INT UNSIGNED NULL,
  `provider_code` VARCHAR(64) NOT NULL,
  `event_type` VARCHAR(128) NULL,
  `external_event_id` VARCHAR(191) NULL,
  `signature_status` VARCHAR(32) NOT NULL DEFAULT 'unchecked',
  `processing_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `raw_payload` LONGTEXT NULL,
  `headers_json` MEDIUMTEXT NULL,
  `received_at` DATETIME NULL,
  `processed_at` DATETIME NULL,
  `error_message` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `provider_event` (`provider_code`, `external_event_id`),
  KEY `tenant_status` (`tenant_id`, `processing_status`),
  KEY `received_at` (`received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_integration_sync_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `connection_id` INT UNSIGNED NULL,
  `webhook_event_id` BIGINT UNSIGNED NULL,
  `provider_code` VARCHAR(64) NOT NULL,
  `job_type` VARCHAR(128) NOT NULL,
  `entity_type` VARCHAR(64) NULL,
  `external_id` VARCHAR(191) NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'queued',
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` INT UNSIGNED NOT NULL DEFAULT 5,
  `payload_json` LONGTEXT NULL,
  `result_json` MEDIUMTEXT NULL,
  `error_message` TEXT NULL,
  `available_at` DATETIME NULL,
  `locked_at` DATETIME NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dedupe_job` (`tenant_id`, `provider_code`, `job_type`, `external_id`),
  KEY `status_available` (`status`, `available_at`),
  KEY `tenant_provider` (`tenant_id`, `provider_code`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_integration_entity_links` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `provider_code` VARCHAR(64) NOT NULL,
  `connection_id` INT UNSIGNED NULL,
  `entity_type` VARCHAR(64) NOT NULL,
  `local_id` INT UNSIGNED NOT NULL,
  `external_id` VARCHAR(191) NOT NULL,
  `external_hash` VARCHAR(191) NULL,
  `last_synced_at` DATETIME NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_external` (`tenant_id`, `provider_code`, `entity_type`, `external_id`),
  KEY `local_entity` (`tenant_id`, `entity_type`, `local_id`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_integration_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NULL,
  `connection_id` INT UNSIGNED NULL,
  `provider_code` VARCHAR(64) NULL,
  `level` VARCHAR(32) NOT NULL DEFAULT 'info',
  `event` VARCHAR(128) NOT NULL,
  `message` TEXT NULL,
  `context_json` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_created` (`tenant_id`, `created_at`),
  KEY `provider_created` (`provider_code`, `created_at`),
  KEY `level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_dms_workspaces` (
  `id` BIGINT UNSIGNED NOT NULL,
  `tenant_id` INT UNSIGNED NOT NULL,
  `company_id` INT UNSIGNED NOT NULL,
  `workspace_slug` VARCHAR(100) NOT NULL,
  `workspace_name` VARCHAR(191) NOT NULL,
  `workspace_host` VARCHAR(191) NOT NULL,
  `package_code` VARCHAR(100) NULL,
  `entitlement_status` VARCHAR(32) NULL,
  `domain_id` BIGINT UNSIGNED NULL,
  `domain_name` VARCHAR(191) NULL,
  `domain_status` VARCHAR(32) NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `source_version` VARCHAR(64) NULL,
  `last_synced_at` DATETIME NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workspace_host` (`workspace_host`),
  UNIQUE KEY `tenant_workspace_slug` (`tenant_id`, `company_id`, `workspace_slug`),
  KEY `tenant_primary` (`tenant_id`, `company_id`, `is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_dms_staff_access` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `company_id` INT UNSIGNED NOT NULL,
  `crm_staff_id` BIGINT UNSIGNED NOT NULL,
  `dms_access_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `dms_role_code` VARCHAR(64) NOT NULL DEFAULT 'field_sales',
  `manager_crm_staff_id` BIGINT UNSIGNED NULL,
  `sync_status` VARCHAR(32) NOT NULL DEFAULT 'not_started',
  `sync_error` TEXT NULL,
  `last_synced_at` DATETIME NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_staff` (`tenant_id`, `crm_staff_id`),
  KEY `tenant_access` (`tenant_id`, `dms_access_enabled`),
  KEY `tenant_manager` (`tenant_id`, `manager_crm_staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "dms_order_bridge` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `provider_code` VARCHAR(64) NOT NULL,
  `dms_order_id` BIGINT UNSIGNED NOT NULL,
  `dms_order_number` VARCHAR(191) NULL,
  `crm_invoice_id` INT UNSIGNED NULL,
  `bridge_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `invoice_status_snapshot` VARCHAR(64) NULL,
  `last_payload_checksum` VARCHAR(191) NULL,
  `last_synced_at` DATETIME NULL,
  `error_code` VARCHAR(64) NULL,
  `error_message` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_provider_dms_order` (`tenant_id`, `provider_code`, `dms_order_id`),
  KEY `tenant_bridge_status` (`tenant_id`, `bridge_status`),
  KEY `crm_invoice_id` (`crm_invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "dms_order_projection` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `company_id` INT UNSIGNED NOT NULL,
  `dms_workspace_host` VARCHAR(191) NULL,
  `source` VARCHAR(64) NOT NULL DEFAULT 'salesrep',
  `source_record_id` BIGINT UNSIGNED NOT NULL,
  `source_record_type` VARCHAR(64) NOT NULL DEFAULT 'order',
  `projection_version` INT UNSIGNED NOT NULL DEFAULT 1,
  `sync_status` VARCHAR(32) NOT NULL DEFAULT 'synced',
  `dms_order_id` BIGINT UNSIGNED NOT NULL,
  `dms_order_number` VARCHAR(191) NULL,
  `crm_invoice_id` INT UNSIGNED NULL,
  `store_id` BIGINT UNSIGNED NULL,
  `staff_id` BIGINT UNSIGNED NULL,
  `order_status` VARCHAR(64) NULL,
  `order_total` DECIMAL(15,2) NULL,
  `currency` VARCHAR(12) NOT NULL DEFAULT 'VND',
  `invoice_status` VARCHAR(64) NULL,
  `invoice_total` DECIMAL(15,2) NULL,
  `payment_status` VARCHAR(64) NULL,
  `paid_amount` DECIMAL(15,2) NULL,
  `balance_amount` DECIMAL(15,2) NULL,
  `payment_source` VARCHAR(64) NOT NULL DEFAULT 'unknown',
  `submitted_at` DATETIME NULL,
  `approved_at` DATETIME NULL,
  `synced_at` DATETIME NULL,
  `last_readback_at` DATETIME NULL,
  `last_synced_at` DATETIME NULL,
  `checksum` VARCHAR(64) NULL,
  `metadata_json` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_source_record` (`tenant_id`, `source_record_type`, `source_record_id`, `projection_version`),
  KEY `tenant_order` (`tenant_id`, `dms_order_id`),
  KEY `tenant_invoice` (`tenant_id`, `crm_invoice_id`),
  KEY `tenant_synced` (`tenant_id`, `last_synced_at`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "dms_location_projection` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `company_id` INT UNSIGNED NOT NULL,
  `dms_workspace_host` VARCHAR(191) NULL,
  `source` VARCHAR(64) NOT NULL DEFAULT 'salesrep',
  `source_record_id` BIGINT UNSIGNED NOT NULL,
  `source_record_type` VARCHAR(64) NOT NULL,
  `projection_version` INT UNSIGNED NOT NULL DEFAULT 1,
  `sync_status` VARCHAR(32) NOT NULL DEFAULT 'synced',
  `marker_type` VARCHAR(32) NOT NULL,
  `staff_id` BIGINT UNSIGNED NULL,
  `store_id` BIGINT UNSIGNED NULL,
  `order_id` BIGINT UNSIGNED NULL,
  `latitude` DECIMAL(10,8) NOT NULL,
  `longitude` DECIMAL(11,8) NOT NULL,
  `accuracy` DECIMAL(10,2) NULL,
  `location_source` VARCHAR(64) NOT NULL DEFAULT 'store_coordinates',
  `recorded_at` DATETIME NULL,
  `label` VARCHAR(191) NULL,
  `status` VARCHAR(64) NULL,
  `last_synced_at` DATETIME NULL,
  `checksum` VARCHAR(64) NULL,
  `metadata_json` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_marker_source` (`tenant_id`, `marker_type`, `source_record_type`, `source_record_id`, `projection_version`),
  KEY `tenant_marker` (`tenant_id`, `marker_type`),
  KEY `tenant_recorded` (`tenant_id`, `recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "dms_inventory_projection` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `company_id` INT UNSIGNED NOT NULL,
  `dms_workspace_host` VARCHAR(191) NULL,
  `store_id` BIGINT UNSIGNED NULL,
  `store_name` VARCHAR(191) NULL,
  `product_id` BIGINT UNSIGNED NULL,
  `product_name` VARCHAR(191) NULL,
  `sku` VARCHAR(191) NULL,
  `stock_on_hand` DECIMAL(15,2) NULL,
  `reserved_quantity` DECIMAL(15,2) NULL,
  `low_stock_threshold` DECIMAL(15,2) NULL,
  `is_low_stock` TINYINT(1) NOT NULL DEFAULT 0,
  `movement_type` VARCHAR(64) NULL,
  `movement_quantity` DECIMAL(15,2) NULL,
  `movement_at` DATETIME NULL,
  `reference_order_id` BIGINT UNSIGNED NULL,
  `staff_id` BIGINT UNSIGNED NULL,
  `source` VARCHAR(64) NOT NULL DEFAULT 'salesrep',
  `source_record_id` BIGINT UNSIGNED NOT NULL,
  `source_record_type` VARCHAR(64) NOT NULL DEFAULT 'inventory',
  `projection_version` INT UNSIGNED NOT NULL DEFAULT 1,
  `sync_status` VARCHAR(32) NOT NULL DEFAULT 'synced',
  `last_synced_at` DATETIME NULL,
  `checksum` VARCHAR(64) NULL,
  `metadata_json` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_inventory_source` (`tenant_id`, `source_record_type`, `source_record_id`, `projection_version`),
  KEY `tenant_low_stock` (`tenant_id`, `is_low_stock`),
  KEY `tenant_store_product` (`tenant_id`, `store_id`, `product_id`),
  KEY `tenant_movement` (`tenant_id`, `movement_at`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "dms_product_sales_projection` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `company_id` INT UNSIGNED NOT NULL,
  `dms_workspace_host` VARCHAR(191) NULL,
  `product_id` BIGINT UNSIGNED NULL,
  `product_name` VARCHAR(191) NULL,
  `sku` VARCHAR(191) NULL,
  `sold_quantity_day` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `sold_amount_day` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `sold_quantity_month` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `sold_amount_month` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `order_count_day` INT UNSIGNED NOT NULL DEFAULT 0,
  `order_count_month` INT UNSIGNED NOT NULL DEFAULT 0,
  `source` VARCHAR(64) NOT NULL DEFAULT 'salesrep',
  `source_record_id` BIGINT UNSIGNED NOT NULL,
  `source_record_type` VARCHAR(64) NOT NULL DEFAULT 'product_sales',
  `projection_version` INT UNSIGNED NOT NULL DEFAULT 1,
  `sync_status` VARCHAR(32) NOT NULL DEFAULT 'synced',
  `last_synced_at` DATETIME NULL,
  `checksum` VARCHAR(64) NULL,
  `metadata_json` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_product_sales_source` (`tenant_id`, `source_record_type`, `source_record_id`, `projection_version`),
  KEY `tenant_product` (`tenant_id`, `product_id`),
  KEY `tenant_sold_month` (`tenant_id`, `sold_quantity_month`, `sold_amount_month`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "dms_staff_activity_projection` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `company_id` INT UNSIGNED NOT NULL,
  `dms_workspace_host` VARCHAR(191) NULL,
  `staff_id` BIGINT UNSIGNED NOT NULL,
  `staff_name` VARCHAR(191) NULL,
  `crm_staff_id` BIGINT UNSIGNED NULL,
  `manager_staff_id` BIGINT UNSIGNED NULL,
  `role_code` VARCHAR(64) NULL,
  `online_state` VARCHAR(32) NOT NULL DEFAULT 'offline',
  `last_seen_at` DATETIME NULL,
  `last_checkin_at` DATETIME NULL,
  `last_visit_at` DATETIME NULL,
  `today_checkin_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `today_visit_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `today_order_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `today_order_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `missed_visit_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `source` VARCHAR(64) NOT NULL DEFAULT 'salesrep',
  `source_record_id` BIGINT UNSIGNED NOT NULL,
  `source_record_type` VARCHAR(64) NOT NULL DEFAULT 'staff_activity',
  `projection_version` INT UNSIGNED NOT NULL DEFAULT 1,
  `sync_status` VARCHAR(32) NOT NULL DEFAULT 'synced',
  `last_synced_at` DATETIME NULL,
  `checksum` VARCHAR(64) NULL,
  `metadata_json` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_staff_activity_source` (`tenant_id`, `source_record_type`, `source_record_id`, `projection_version`),
  KEY `tenant_staff` (`tenant_id`, `staff_id`),
  KEY `tenant_online` (`tenant_id`, `online_state`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "dms_store_coverage_projection` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `company_id` INT UNSIGNED NOT NULL,
  `dms_workspace_host` VARCHAR(191) NULL,
  `store_id` BIGINT UNSIGNED NOT NULL,
  `store_name` VARCHAR(191) NULL,
  `crm_customer_id` BIGINT UNSIGNED NULL,
  `assigned_staff_id` BIGINT UNSIGNED NULL,
  `visited_today` TINYINT(1) NOT NULL DEFAULT 0,
  `visited_this_month` TINYINT(1) NOT NULL DEFAULT 0,
  `last_visit_at` DATETIME NULL,
  `last_checkin_at` DATETIME NULL,
  `today_order_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `month_order_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `coverage_date` DATE NULL,
  `coverage_month` CHAR(7) NULL,
  `source` VARCHAR(64) NOT NULL DEFAULT 'salesrep',
  `source_record_id` BIGINT UNSIGNED NOT NULL,
  `source_record_type` VARCHAR(64) NOT NULL DEFAULT 'store_coverage',
  `projection_version` INT UNSIGNED NOT NULL DEFAULT 1,
  `sync_status` VARCHAR(32) NOT NULL DEFAULT 'synced',
  `last_synced_at` DATETIME NULL,
  `checksum` VARCHAR(64) NULL,
  `metadata_json` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_store_coverage_source` (`tenant_id`, `source_record_type`, `source_record_id`, `projection_version`),
  KEY `tenant_store` (`tenant_id`, `store_id`),
  KEY `tenant_coverage` (`tenant_id`, `coverage_date`, `coverage_month`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "dms_field_feedback_projection` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `company_id` INT UNSIGNED NOT NULL,
  `dms_workspace_host` VARCHAR(191) NULL,
  `source` VARCHAR(64) NOT NULL DEFAULT 'salesrep',
  `source_record_id` BIGINT UNSIGNED NOT NULL,
  `source_record_type` VARCHAR(64) NOT NULL DEFAULT 'field_feedback',
  `projection_version` INT UNSIGNED NOT NULL DEFAULT 1,
  `sync_status` VARCHAR(32) NOT NULL DEFAULT 'synced',
  `last_synced_at` DATETIME NULL,
  `checksum` VARCHAR(64) NULL,
  `feedback_type` VARCHAR(64) NOT NULL,
  `severity` VARCHAR(32) NOT NULL DEFAULT 'normal',
  `status` VARCHAR(32) NOT NULL DEFAULT 'new',
  `staff_name` VARCHAR(191) NULL,
  `staff_email` VARCHAR(191) NULL,
  `store_name` VARCHAR(191) NULL,
  `store_external_id` VARCHAR(191) NULL,
  `title` VARCHAR(255) NOT NULL,
  `description_summary` TEXT NULL,
  `submitted_at` DATETIME NULL,
  `reviewed_at` DATETIME NULL,
  `resolved_at` DATETIME NULL,
  `latitude` DECIMAL(10,8) NULL,
  `longitude` DECIMAL(11,8) NULL,
  `gps_accuracy` DECIMAL(10,2) NULL,
  `photo_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `metadata_json` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_feedback_source` (`tenant_id`, `source_record_type`, `source_record_id`, `projection_version`),
  KEY `tenant_status` (`tenant_id`, `status`),
  KEY `tenant_type` (`tenant_id`, `feedback_type`),
  KEY `tenant_submitted` (`tenant_id`, `submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_dms_tenant_overrides` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NULL,
  `feature_overrides_json` MEDIUMTEXT NULL,
  `quota_overrides_json` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_unique` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_dms_landlord_actions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_user_id` INT NULL,
  `actor_email` VARCHAR(191) NULL,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NULL,
  `action` VARCHAR(80) NOT NULL,
  `before_json` MEDIUMTEXT NULL,
  `after_json` MEDIUMTEXT NULL,
  `ip` VARCHAR(64) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_action` (`tenant_id`, `action`),
  KEY `actor_created` (`actor_user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_manual_reviews` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `entity_type` VARCHAR(64) NOT NULL,
  `entity_id` BIGINT UNSIGNED NOT NULL,
  `review_type` VARCHAR(64) NOT NULL,
  `review_status` VARCHAR(32) NOT NULL DEFAULT 'OPEN',
  `priority` VARCHAR(16) NOT NULL DEFAULT 'LOW',
  `assigned_staff_id` BIGINT UNSIGNED NULL,
  `review_reason` TEXT NULL,
  `source_system` VARCHAR(64) NOT NULL DEFAULT 'salesflow_dms',
  `payload_json` LONGTEXT NULL,
  `resolution_note` TEXT NULL,
  `resolved_by` BIGINT UNSIGNED NULL,
  `resolved_at` DATETIME NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_entity` (`tenant_id`, `entity_type`, `entity_id`),
  KEY `tenant_status` (`tenant_id`, `review_status`),
  KEY `tenant_priority` (`tenant_id`, `priority`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_notification_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `event_type` VARCHAR(64) NOT NULL,
  `event_code` VARCHAR(191) NOT NULL,
  `title` VARCHAR(191) NOT NULL,
  `message` TEXT NULL,
  `target_type` VARCHAR(64) NOT NULL DEFAULT 'tenant',
  `target_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `priority` VARCHAR(32) NOT NULL DEFAULT 'LOW',
  `payload_json` LONGTEXT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'NEW',
  `created_at` DATETIME NULL,
  `read_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_event_identity` (`tenant_id`, `event_type`, `event_code`, `target_type`, `target_id`),
  KEY `tenant_status_created` (`tenant_id`, `status`, `created_at`),
  KEY `tenant_event_type` (`tenant_id`, `event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "dms_checkin_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `dms_checkin_id` BIGINT UNSIGNED NOT NULL,
  `crm_staff_id` BIGINT UNSIGNED NOT NULL,
  `crm_customer_id` BIGINT UNSIGNED NOT NULL,
  `store_id` BIGINT UNSIGNED NOT NULL,
  `route_id` BIGINT UNSIGNED NULL,
  `checkin_at` DATETIME NOT NULL,
  `latitude` DECIMAL(10,8) NOT NULL,
  `longitude` DECIMAL(11,8) NOT NULL,
  `accuracy_meter` DECIMAL(10,2) NULL,
  `distance_from_store_meter` INT UNSIGNED NULL,
  `photo_url` VARCHAR(255) NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'warning',
  `notes` TEXT NULL,
  `payload_json` LONGTEXT NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_checkin` (`tenant_id`, `dms_checkin_id`),
  KEY `tenant_status` (`tenant_id`, `status`),
  KEY `tenant_staff_customer` (`tenant_id`, `crm_staff_id`, `crm_customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "dms_visit_snapshots` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `visit_id` BIGINT UNSIGNED NOT NULL,
  `crm_staff_id` BIGINT UNSIGNED NOT NULL,
  `crm_customer_id` BIGINT UNSIGNED NOT NULL,
  `store_id` BIGINT UNSIGNED NOT NULL,
  `route_id` BIGINT UNSIGNED NULL,
  `started_at` DATETIME NOT NULL,
  `ended_at` DATETIME NOT NULL,
  `duration_minutes` INT UNSIGNED NULL,
  `visit_status` VARCHAR(32) NOT NULL DEFAULT 'partial',
  `notes` TEXT NULL,
  `payload_json` LONGTEXT NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_visit` (`tenant_id`, `visit_id`),
  KEY `tenant_status` (`tenant_id`, `visit_status`),
  KEY `tenant_staff_customer` (`tenant_id`, `crm_staff_id`, `crm_customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "dms_route_performance_snapshots` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `snapshot_date` DATE NOT NULL,
  `crm_staff_id` BIGINT UNSIGNED NOT NULL,
  `crm_customer_id` BIGINT UNSIGNED NOT NULL,
  `store_id` BIGINT UNSIGNED NOT NULL,
  `total_checkins` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_visits` INT UNSIGNED NOT NULL DEFAULT 0,
  `completed_visits` INT UNSIGNED NOT NULL DEFAULT 0,
  `partial_visits` INT UNSIGNED NOT NULL DEFAULT 0,
  `cancelled_visits` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_snapshot_scope` (`tenant_id`, `snapshot_date`, `crm_staff_id`, `crm_customer_id`, `store_id`),
  KEY `tenant_snapshot_date` (`tenant_id`, `snapshot_date`),
  KEY `tenant_staff_customer_store` (`tenant_id`, `crm_staff_id`, `crm_customer_id`, `store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "dms_live_tracking_snapshots` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `dms_user_id` BIGINT UNSIGNED NOT NULL,
  `crm_staff_id` BIGINT UNSIGNED NOT NULL,
  `latitude` DECIMAL(10,8) NOT NULL,
  `longitude` DECIMAL(11,8) NOT NULL,
  `last_seen_at` DATETIME NOT NULL,
  `tracking_status` VARCHAR(32) NOT NULL DEFAULT 'offline',
  `payload_json` LONGTEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_tracking_user` (`tenant_id`, `dms_user_id`),
  KEY `tenant_status` (`tenant_id`, `tracking_status`),
  KEY `tenant_last_seen` (`tenant_id`, `last_seen_at`),
  KEY `tenant_staff` (`tenant_id`, `crm_staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_integration_channel_orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `connection_id` INT UNSIGNED NOT NULL,
  `provider_code` VARCHAR(64) NOT NULL,
  `external_order_id` VARCHAR(191) NOT NULL,
  `external_order_code` VARCHAR(191) NULL,
  `order_status` VARCHAR(64) NULL,
  `payment_status` VARCHAR(64) NULL,
  `fulfillment_status` VARCHAR(64) NULL,
  `buyer_name` VARCHAR(191) NULL,
  `buyer_phone_masked` VARCHAR(64) NULL,
  `buyer_email` VARCHAR(191) NULL,
  `currency` VARCHAR(16) NOT NULL DEFAULT 'VND',
  `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `shipping_fee` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `discount_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `ordered_at` DATETIME NULL,
  `synced_at` DATETIME NULL,
  `raw_json` LONGTEXT NULL,
  `mapping_status` VARCHAR(32) NOT NULL DEFAULT 'unmapped',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_provider_order` (`tenant_id`, `provider_code`, `external_order_id`),
  KEY `tenant_status` (`tenant_id`, `order_status`),
  KEY `connection_id` (`connection_id`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_integration_channel_order_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `channel_order_id` BIGINT UNSIGNED NOT NULL,
  `provider_code` VARCHAR(64) NOT NULL,
  `external_item_id` VARCHAR(191) NULL,
  `external_sku_id` VARCHAR(191) NULL,
  `sku` VARCHAR(191) NULL,
  `item_name` VARCHAR(255) NULL,
  `quantity` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `raw_json` LONGTEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_order` (`tenant_id`, `channel_order_id`),
  KEY `sku` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

$tables[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_integration_heartbeats` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service` VARCHAR(64) NOT NULL,
  `instance_id` VARCHAR(191) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'healthy',
  `started_at` DATETIME NULL,
  `last_seen_at` DATETIME NOT NULL,
  `last_success_at` DATETIME NULL,
  `current_job_id` BIGINT UNSIGNED NULL,
  `safe_meta_json` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service` (`service`),
  KEY `last_seen_at` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";";

foreach ($tables as $sql) {
    $CI->db->query($sql);
}

require_once __DIR__ . '/libraries/DmsIntegrationContractStore.php';
(new DmsIntegrationContractStore())->ensureSchema();

$providerTable = db_prefix() . 'kt_integration_providers';
$providerColumns = [
    'auth_type'        => "ALTER TABLE `{$providerTable}` ADD `auth_type` VARCHAR(40) NOT NULL DEFAULT 'custom_hmac' AFTER `provider_type`",
    'readiness_status' => "ALTER TABLE `{$providerTable}` ADD `readiness_status` VARCHAR(30) NOT NULL DEFAULT 'planned' AFTER `auth_type`",
    'status_message'   => "ALTER TABLE `{$providerTable}` ADD `status_message` VARCHAR(255) NULL AFTER `readiness_status`",
    'metadata_json'    => "ALTER TABLE `{$providerTable}` ADD `metadata_json` MEDIUMTEXT NULL AFTER `status_message`",
];

foreach ($providerColumns as $column => $sql) {
    if (!$CI->db->field_exists($column, $providerTable)) {
        $CI->db->query($sql);
    }
}

$syncJobTable = db_prefix() . 'kt_integration_sync_jobs';
$syncJobColumns = [
    'idempotency_key' => "ALTER TABLE `{$syncJobTable}` ADD `idempotency_key` VARCHAR(191) NULL AFTER `external_id`",
    'request_id'      => "ALTER TABLE `{$syncJobTable}` ADD `request_id` VARCHAR(191) NULL AFTER `idempotency_key`",
    'correlation_id' => "ALTER TABLE `{$syncJobTable}` ADD `correlation_id` VARCHAR(191) NULL AFTER `request_id`",
    'source_stable_key' => "ALTER TABLE `{$syncJobTable}` ADD `source_stable_key` VARCHAR(191) NULL AFTER `correlation_id`",
    'source_fingerprint' => "ALTER TABLE `{$syncJobTable}` ADD `source_fingerprint` VARCHAR(64) NULL AFTER `source_stable_key`",
    'source_version' => "ALTER TABLE `{$syncJobTable}` ADD `source_version` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `source_fingerprint`",
    'attempt_count_current' => "ALTER TABLE `{$syncJobTable}` ADD `attempt_count_current` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `attempts`",
    'terminal_status' => "ALTER TABLE `{$syncJobTable}` ADD `terminal_status` VARCHAR(32) NULL AFTER `status`",
    'last_safe_error_code' => "ALTER TABLE `{$syncJobTable}` ADD `last_safe_error_code` VARCHAR(64) NULL AFTER `error_message`",
];

foreach ($syncJobColumns as $column => $sql) {
    if (!$CI->db->field_exists($column, $syncJobTable)) {
        $CI->db->query($sql);
    }
}

$syncJobIndexes = [
    'idx_request_id'      => "ALTER TABLE `{$syncJobTable}` ADD KEY `idx_request_id` (`tenant_id`, `provider_code`, `request_id`)",
    'idx_idempotency_key' => "ALTER TABLE `{$syncJobTable}` ADD KEY `idx_idempotency_key` (`tenant_id`, `provider_code`, `idempotency_key`)",
];

foreach ($syncJobIndexes as $indexName => $sql) {
    $exists = $CI->db->query("SHOW INDEX FROM `{$syncJobTable}` WHERE Key_name = " . $CI->db->escape($indexName))->row_array();
    if (!$exists) {
        $CI->db->query($sql);
    }
}

$entityLinkTable = db_prefix() . 'kt_integration_entity_links';
$entityLinkColumns = [
    'sync_status'   => "ALTER TABLE `{$entityLinkTable}` ADD `sync_status` VARCHAR(32) NOT NULL DEFAULT 'active' AFTER `external_hash`",
    'direction'     => "ALTER TABLE `{$entityLinkTable}` ADD `direction` VARCHAR(32) NOT NULL DEFAULT 'bidirectional' AFTER `sync_status`",
    'metadata_json' => "ALTER TABLE `{$entityLinkTable}` ADD `metadata_json` MEDIUMTEXT NULL AFTER `direction`",
    'company_id' => "ALTER TABLE `{$entityLinkTable}` ADD `company_id` INT UNSIGNED NULL AFTER `tenant_id`",
    'source_system' => "ALTER TABLE `{$entityLinkTable}` ADD `source_system` VARCHAR(64) NOT NULL DEFAULT 'khachtot_crm' AFTER `entity_type`",
    'source_numeric_id' => "ALTER TABLE `{$entityLinkTable}` ADD `source_numeric_id` BIGINT UNSIGNED NULL AFTER `source_system`",
    'source_stable_key' => "ALTER TABLE `{$entityLinkTable}` ADD `source_stable_key` VARCHAR(191) NULL AFTER `source_numeric_id`",
    'source_fingerprint' => "ALTER TABLE `{$entityLinkTable}` ADD `source_fingerprint` VARCHAR(64) NULL AFTER `source_stable_key`",
    'source_version' => "ALTER TABLE `{$entityLinkTable}` ADD `source_version` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `source_fingerprint`",
    'target_system' => "ALTER TABLE `{$entityLinkTable}` ADD `target_system` VARCHAR(64) NOT NULL DEFAULT 'salesrep_dms' AFTER `external_id`",
    'target_entity_id' => "ALTER TABLE `{$entityLinkTable}` ADD `target_entity_id` VARCHAR(191) NULL AFTER `target_system`",
    'target_stable_key' => "ALTER TABLE `{$entityLinkTable}` ADD `target_stable_key` VARCHAR(191) NULL AFTER `target_entity_id`",
    'lifecycle_state' => "ALTER TABLE `{$entityLinkTable}` ADD `lifecycle_state` VARCHAR(32) NOT NULL DEFAULT 'active' AFTER `target_stable_key`",
    'first_seen_at' => "ALTER TABLE `{$entityLinkTable}` ADD `first_seen_at` DATETIME NULL AFTER `lifecycle_state`",
    'last_seen_at' => "ALTER TABLE `{$entityLinkTable}` ADD `last_seen_at` DATETIME NULL AFTER `first_seen_at`",
    'deleted_at' => "ALTER TABLE `{$entityLinkTable}` ADD `deleted_at` DATETIME NULL AFTER `last_seen_at`",
    'tombstoned_at' => "ALTER TABLE `{$entityLinkTable}` ADD `tombstoned_at` DATETIME NULL AFTER `deleted_at`",
    'superseded_by' => "ALTER TABLE `{$entityLinkTable}` ADD `superseded_by` BIGINT UNSIGNED NULL AFTER `tombstoned_at`",
    'correlation_id' => "ALTER TABLE `{$entityLinkTable}` ADD `correlation_id` VARCHAR(191) NULL AFTER `superseded_by`",
    'last_request_id' => "ALTER TABLE `{$entityLinkTable}` ADD `last_request_id` VARCHAR(191) NULL AFTER `correlation_id`",
    'last_idempotency_key' => "ALTER TABLE `{$entityLinkTable}` ADD `last_idempotency_key` VARCHAR(191) NULL AFTER `last_request_id`",
];

foreach ($entityLinkColumns as $column => $sql) {
    if (!$CI->db->field_exists($column, $entityLinkTable)) {
        $CI->db->query($sql);
    }
}

$entityLinkIndexes = [
    'idx_sync_status' => "ALTER TABLE `{$entityLinkTable}` ADD KEY `idx_sync_status` (`tenant_id`, `provider_code`, `sync_status`)",
    'idx_source_numeric_trace' => "ALTER TABLE `{$entityLinkTable}` ADD KEY `idx_source_numeric_trace` (`tenant_id`,`company_id`,`entity_type`,`source_numeric_id`)",
    'idx_source_stable' => "ALTER TABLE `{$entityLinkTable}` ADD KEY `idx_source_stable` (`tenant_id`,`company_id`,`entity_type`,`source_system`,`source_stable_key`)",
    'uq_source_stable' => "ALTER TABLE `{$entityLinkTable}` ADD UNIQUE KEY `uq_source_stable` (`tenant_id`,`company_id`,`entity_type`,`source_system`,`source_stable_key`)",
];

foreach ($entityLinkIndexes as $indexName => $sql) {
    $exists = $CI->db->query("SHOW INDEX FROM `{$entityLinkTable}` WHERE Key_name = " . $CI->db->escape($indexName))->row_array();
    if (!$exists) {
        $CI->db->query($sql);
    }
}

$attemptTable = db_prefix() . 'kt_integration_sync_attempts';
$CI->db->query("CREATE TABLE IF NOT EXISTS `{$attemptTable}` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `job_id` BIGINT UNSIGNED NOT NULL,
 `tenant_id` INT UNSIGNED NOT NULL, `company_id` INT UNSIGNED NULL, `entity_type` VARCHAR(64) NULL,
 `source_stable_key` VARCHAR(191) NULL, `attempt_no` INT UNSIGNED NOT NULL,
 `request_id` VARCHAR(191) NULL, `correlation_id` VARCHAR(191) NULL, `idempotency_key` VARCHAR(191) NULL,
 `started_at` DATETIME NOT NULL, `ended_at` DATETIME NULL, `status` VARCHAR(32) NOT NULL,
 `http_status` INT UNSIGNED NULL, `safe_error_code` VARCHAR(64) NULL, `safe_error_message` TEXT NULL,
 `retryable` TINYINT(1) NOT NULL DEFAULT 0, `payload_hash` VARCHAR(64) NULL, `response_hash` VARCHAR(64) NULL,
 `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `job_attempt` (`job_id`,`attempt_no`),
 KEY `tenant_entity_created` (`tenant_id`,`entity_type`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}");

$dmsOrderBridgeTable = db_prefix() . 'dms_order_bridge';
$dmsOrderBridgeColumns = [
    'cancel_requested_at' => "ALTER TABLE `{$dmsOrderBridgeTable}` ADD `cancel_requested_at` DATETIME NULL AFTER `last_synced_at`",
    'cancelled_at'        => "ALTER TABLE `{$dmsOrderBridgeTable}` ADD `cancelled_at` DATETIME NULL AFTER `cancel_requested_at`",
    'cancel_reason'       => "ALTER TABLE `{$dmsOrderBridgeTable}` ADD `cancel_reason` TEXT NULL AFTER `cancelled_at`",
    'cancel_status'       => "ALTER TABLE `{$dmsOrderBridgeTable}` ADD `cancel_status` VARCHAR(64) NULL AFTER `cancel_reason`",
];

foreach ($dmsOrderBridgeColumns as $column => $sql) {
    if (!$CI->db->field_exists($column, $dmsOrderBridgeTable)) {
        $CI->db->query($sql);
    }
}

$providers = [
    ['facebook_page', 'Facebook Page / Messenger', 'social', 'oauth', 'planned', 'Facebook App, OAuth and Page permissions are required before this connector can be enabled.', 1, 1, 1],
    ['facebook_lead_ads', 'Facebook Lead Ads', 'ads', 'oauth', 'planned', 'Facebook Lead Ads OAuth and lead retrieval permissions are required before this connector can be enabled.', 1, 1, 1],
    ['zalo_oa', 'Zalo OA', 'social', 'oauth', 'beta', 'Zalo OA V1 beta supports inbound webhook intake and local simulated tests.', 1, 1, 1],
    ['tiktok_shop', 'TikTok Shop', 'commerce', 'partner_api', 'beta', 'TikTok Shop V1 beta supports dry-run order webhook intake into channel order staging.', 1, 1, 1],
    ['salesflow_dms', 'Salesflow DMS Bridge', 'internal_app', 'custom_hmac', 'beta', 'Salesflow DMS tenant context bridge uses signed server-to-server requests without shared session.', 0, 0, 1],
    ['shopee', 'Shopee', 'commerce', 'partner_api', 'planned', 'Shopee Open Platform partner API credentials are required before this connector can be enabled.', 1, 1, 1],
    ['website_form', 'Website Form', 'form', 'custom_hmac', 'planned', 'Website Form will reuse Custom Webhook after an embed/API flow is implemented.', 0, 1, 0],
    ['custom_webhook', 'Custom Webhook', 'generic', 'custom_hmac', 'ready', 'Ready to receive HMAC-SHA256 webhook events through the global public endpoint.', 0, 1, 0],
];

foreach ($providers as $provider) {
    $exists = $CI->db
        ->where('provider_code', $provider[0])
        ->count_all_results(db_prefix() . 'kt_integration_providers') > 0;
    $payload = [
        'provider_code'    => $provider[0],
        'provider_name'    => $provider[1],
        'provider_type'    => $provider[2],
        'auth_type'        => $provider[3],
        'readiness_status' => $provider[4],
        'status_message'   => $provider[5],
        'metadata_json'    => json_encode(['mvp_note' => $provider[5]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'supports_oauth'   => $provider[6],
        'supports_webhook' => $provider[7],
        'supports_polling' => $provider[8],
        'is_active'        => 1,
        'updated_at'       => date('Y-m-d H:i:s'),
    ];

    if ($exists) {
        $CI->db->where('provider_code', $provider[0])->update(db_prefix() . 'kt_integration_providers', $payload);
        continue;
    }

    $payload['created_at'] = date('Y-m-d H:i:s');
    $CI->db->insert(db_prefix() . 'kt_integration_providers', $payload);
}

if ($CI->db->table_exists(db_prefix() . 'kt_saas_module_catalog')) {
    $exists = $CI->db
        ->where('module_name', KT_INTEGRATION_HUB_MODULE)
        ->count_all_results(db_prefix() . 'kt_saas_module_catalog') > 0;

    $catalogPayload = [
        'module_name'      => KT_INTEGRATION_HUB_MODULE,
        'display_name'     => 'KT Integration Hub',
        'slug'             => 'kt-integration-hub',
        'description'      => 'Omnichannel integration hub for tenant CRM lead and event intake.',
        'category'         => 'integration',
        'version'          => KT_INTEGRATION_HUB_VERSION,
        'is_core'          => 0,
        'is_global_active' => 1,
        'has_ui'           => 1,
        'has_routes'       => 1,
        'has_cron'         => 1,
        'detected_from'    => 'module_install',
        'synced_at'        => date('Y-m-d H:i:s'),
    ];

    if ($exists) {
        $CI->db->where('module_name', KT_INTEGRATION_HUB_MODULE)
            ->update(db_prefix() . 'kt_saas_module_catalog', $catalogPayload);
    } else {
        $catalogPayload['created_at'] = date('Y-m-d H:i:s');
        $CI->db->insert(db_prefix() . 'kt_saas_module_catalog', $catalogPayload);
    }
}

if (get_option('kt_integration_hub_schema_version') === false) {
    add_option('kt_integration_hub_schema_version', KT_INTEGRATION_HUB_VERSION);
} else {
    update_option('kt_integration_hub_schema_version', KT_INTEGRATION_HUB_VERSION);
}
