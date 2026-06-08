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

foreach ($tables as $sql) {
    $CI->db->query($sql);
}

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

$providers = [
    ['facebook_page', 'Facebook Page / Messenger', 'social', 'oauth', 'planned', 'Cần cấu hình Facebook App, OAuth và quyền Page trước khi bật kết nối thật.', 1, 1, 1],
    ['facebook_lead_ads', 'Facebook Lead Ads', 'ads', 'oauth', 'planned', 'Cần cấu hình Facebook App, OAuth và quyền Lead Ads trước khi bật kết nối thật.', 1, 1, 1],
    ['zalo_oa', 'Zalo OA', 'social', 'oauth', 'planned', 'Cần cấu hình Zalo App/OA OAuth trước khi bật kết nối thật.', 1, 1, 1],
    ['tiktok_shop', 'TikTok Shop', 'commerce', 'partner_api', 'planned', 'Cần cấu hình TikTok Shop partner API trước khi bật kết nối thật.', 1, 1, 1],
    ['shopee', 'Shopee', 'commerce', 'partner_api', 'planned', 'Cần cấu hình Shopee Open Platform partner API trước khi bật kết nối thật.', 1, 1, 1],
    ['website_form', 'Website Form', 'form', 'custom_hmac', 'planned', 'Có thể dùng Custom Webhook trong MVP; Website Form riêng sẽ được chuẩn hóa sau.', 0, 1, 0],
    ['custom_webhook', 'Custom Webhook', 'generic', 'custom_hmac', 'ready', 'Sẵn sàng nhận lead/event qua webhook HMAC.', 0, 1, 0],
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

add_option('kt_integration_hub_schema_version', KT_INTEGRATION_HUB_VERSION);
