<?php

defined('BASEPATH') or exit('No direct script access allowed');

function kt_landing_run_install()
{
    $CI = &get_instance();
    $charset = $CI->db->char_set;

    $sql = [];
    $sql[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_landing_settings` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `setting_key` VARCHAR(120) NOT NULL,
        `setting_value` LONGTEXT NULL,
        `is_json` TINYINT(1) NOT NULL DEFAULT 0,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ";";

    $sql[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_landing_themes` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `code` VARCHAR(80) NOT NULL,
        `name` VARCHAR(191) NOT NULL,
        `description` TEXT NULL,
        `preview_image` VARCHAR(255) NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `is_default` TINYINT(1) NOT NULL DEFAULT 0,
        `sort_order` INT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_theme_code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ";";

    $sql[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_landing_sections` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `page_key` VARCHAR(60) NOT NULL DEFAULT 'home',
        `section_key` VARCHAR(80) NOT NULL,
        `title` VARCHAR(255) NULL,
        `subtitle` VARCHAR(255) NULL,
        `content` LONGTEXT NULL,
        `image` VARCHAR(255) NULL,
        `icon` VARCHAR(120) NULL,
        `button_text` VARCHAR(120) NULL,
        `button_url` VARCHAR(255) NULL,
        `settings_json` LONGTEXT NULL,
        `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order` INT NOT NULL DEFAULT 0,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_page_section` (`page_key`,`section_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ";";

    $sql[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_landing_global_blocks` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `block_key` VARCHAR(120) NOT NULL,
        `block_name` VARCHAR(191) NOT NULL,
        `block_type` VARCHAR(60) NOT NULL,
        `content_json` LONGTEXT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'active',
        `created_by` INT NULL,
        `updated_by` INT NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_block_key` (`block_key`),
        KEY `idx_block_type_status` (`block_type`,`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ";";

    $sql[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_landing_block_usage` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `block_id` BIGINT UNSIGNED NOT NULL,
        `usage_type` VARCHAR(30) NOT NULL,
        `usage_ref_type` VARCHAR(80) NOT NULL,
        `usage_ref_id` BIGINT UNSIGNED NULL,
        `usage_ref_key` VARCHAR(120) NULL,
        `usage_label` VARCHAR(191) NULL,
        `source_field` VARCHAR(120) NULL,
        `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_block_usage_block` (`block_id`,`usage_type`),
        KEY `idx_block_usage_ref` (`usage_ref_type`,`usage_ref_id`),
        CONSTRAINT `fk_kt_landing_block_usage_block`
            FOREIGN KEY (`block_id`) REFERENCES `" . db_prefix() . "kt_landing_global_blocks`(`id`)
            ON DELETE CASCADE
            ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ";";

    $sql[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_landing_section_items` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `section_id` BIGINT UNSIGNED NOT NULL,
        `item_key` VARCHAR(120) NOT NULL,
        `title` VARCHAR(255) NULL,
        `subtitle` VARCHAR(255) NULL,
        `content` LONGTEXT NULL,
        `icon` VARCHAR(120) NULL,
        `image` VARCHAR(255) NULL,
        `badge` VARCHAR(120) NULL,
        `button_text` VARCHAR(120) NULL,
        `button_url` VARCHAR(255) NULL,
        `settings_json` LONGTEXT NULL,
        `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order` INT NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_section_item` (`section_id`,`item_key`,`sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ";";

    $sql[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_landing_menus` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `menu_area` VARCHAR(20) NOT NULL,
        `label` VARCHAR(191) NOT NULL,
        `url` VARCHAR(255) NOT NULL,
        `target` VARCHAR(20) NOT NULL DEFAULT '_self',
        `group_name` VARCHAR(80) NULL,
        `icon` VARCHAR(120) NULL,
        `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order` INT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ";";

    $sql[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_landing_plan_overrides` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `plan_id` BIGINT UNSIGNED NOT NULL,
        `marketing_title` VARCHAR(255) NULL,
        `marketing_subtitle` VARCHAR(255) NULL,
        `marketing_description` TEXT NULL,
        `badge_text` VARCHAR(120) NULL,
        `cta_text` VARCHAR(120) NULL,
        `cta_url` VARCHAR(255) NULL,
        `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
        `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
        `sort_order` INT NOT NULL DEFAULT 0,
        `source_plan_snapshot_hash` VARCHAR(64) NULL,
        `source_plan_snapshot_json` LONGTEXT NULL,
        `source_plan_updated_at` DATETIME NULL,
        `last_synced_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_plan_override` (`plan_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ";";

    $sql[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_landing_blog_posts` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(255) NOT NULL,
        `slug` VARCHAR(255) NOT NULL,
        `excerpt` TEXT NULL,
        `content` LONGTEXT NULL,
        `featured_image` VARCHAR(255) NULL,
        `category` VARCHAR(120) NULL,
        `tags` VARCHAR(255) NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
        `seo_title` VARCHAR(255) NULL,
        `seo_description` TEXT NULL,
        `published_at` DATETIME NULL,
        `sort_order` INT NOT NULL DEFAULT 0,
        `created_by` INT NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_blog_slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ";";

    $sql[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_landing_leads` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(191) NULL,
        `company` VARCHAR(191) NULL,
        `phone` VARCHAR(80) NULL,
        `email` VARCHAR(191) NULL,
        `message` TEXT NULL,
        `desired_plan_id` BIGINT UNSIGNED NULL,
        `source` VARCHAR(80) NULL,
        `utm_source` VARCHAR(120) NULL,
        `utm_medium` VARCHAR(120) NULL,
        `utm_campaign` VARCHAR(120) NULL,
        `status` VARCHAR(30) NOT NULL DEFAULT 'new',
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ";";

    $sql[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_landing_pages` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(191) NOT NULL,
        `slug` VARCHAR(191) NOT NULL,
        `template_code` VARCHAR(80) NULL,
        `seo_title` VARCHAR(255) NULL,
        `seo_description` TEXT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
        `sort_order` INT NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_page_slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ";";

    $sql[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_landing_media` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `folder` VARCHAR(60) NOT NULL DEFAULT 'landing',
        `file_name` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(255) NOT NULL,
        `file_type` VARCHAR(40) NULL,
        `mime_type` VARCHAR(120) NULL,
        `file_size` BIGINT UNSIGNED NULL,
        `title` VARCHAR(191) NULL,
        `alt_text` VARCHAR(255) NULL,
        `caption` TEXT NULL,
        `tags` VARCHAR(255) NULL,
        `category` VARCHAR(120) NULL,
        `width` INT NULL,
        `height` INT NULL,
        `usage_count` INT NOT NULL DEFAULT 0,
        `last_used_at` DATETIME NULL,
        `uploaded_by` INT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ";";

    $sql[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_landing_media_usage` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `media_id` BIGINT UNSIGNED NOT NULL,
        `usage_type` VARCHAR(30) NOT NULL,
        `usage_ref_type` VARCHAR(80) NOT NULL,
        `usage_ref_id` BIGINT UNSIGNED NULL,
        `usage_ref_key` VARCHAR(120) NULL,
        `usage_label` VARCHAR(191) NULL,
        `source_field` VARCHAR(120) NULL,
        `source_value` LONGTEXT NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_media_usage_media` (`media_id`,`usage_type`),
        KEY `idx_media_usage_ref` (`usage_ref_type`,`usage_ref_id`),
        CONSTRAINT `fk_kt_landing_media_usage_media`
            FOREIGN KEY (`media_id`) REFERENCES `" . db_prefix() . "kt_landing_media`(`id`)
            ON DELETE CASCADE
            ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ";";

    $sql[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_landing_publish_snapshots` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `snapshot_type` VARCHAR(40) NOT NULL,
        `payload_json` LONGTEXT NOT NULL,
        `published_by` INT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ";";

    $sql[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_landing_publish_jobs` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `snapshot_id` BIGINT UNSIGNED NOT NULL,
        `publish_at` DATETIME NOT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'queued',
        `processed_at` DATETIME NULL,
        `error_message` TEXT NULL,
        `created_by` INT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_publish_due` (`status`,`publish_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ";";

    $sql[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_landing_analytics_events` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `event_name` VARCHAR(80) NOT NULL,
        `page_slug` VARCHAR(120) NULL,
        `plan_id` BIGINT UNSIGNED NULL,
        `source` VARCHAR(80) NULL,
        `utm_source` VARCHAR(120) NULL,
        `utm_medium` VARCHAR(120) NULL,
        `utm_campaign` VARCHAR(120) NULL,
        `ip_address` VARCHAR(64) NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_event_created` (`event_name`,`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ";";

    $sql[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_landing_analytics_daily` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `event_date` DATE NOT NULL,
        `event_name` VARCHAR(80) NOT NULL,
        `page_slug` VARCHAR(120) NULL,
        `plan_id` BIGINT UNSIGNED NULL,
        `source` VARCHAR(80) NULL,
        `total` INT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_daily` (`event_date`,`event_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ";";

    $sql[] = "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "kt_landing_lead_activities` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `lead_id` BIGINT UNSIGNED NOT NULL,
        `action` VARCHAR(60) NOT NULL,
        `note` TEXT NULL,
        `created_by` INT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_lead` (`lead_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ";";

    foreach ($sql as $statement) {
        $CI->db->query($statement);
    }

    kt_landing_ensure_pricing_sync_columns($CI);
    kt_landing_ensure_media_columns($CI);
    kt_landing_ensure_publish_columns($CI);

    $themes = [
        ['code' => 'fastwork_inspired', 'name' => 'Fastwork Inspired', 'description' => 'Modern enterprise style', 'sort_order' => 1],
        ['code' => 'corporate_saas', 'name' => 'Corporate SaaS', 'description' => 'Classic B2B SaaS', 'sort_order' => 2],
        ['code' => 'modern_growth', 'name' => 'Modern Growth', 'description' => 'Growth-focused style', 'sort_order' => 3],
        ['code' => 'minimal_enterprise', 'name' => 'Minimal Enterprise', 'description' => 'Clean enterprise style', 'sort_order' => 4],
    ];
    $themeTable = db_prefix() . 'kt_landing_themes';
    foreach ($themes as $theme) {
        $exists = (int) $CI->db->where('code', $theme['code'])->count_all_results($themeTable);
        if ($exists === 0) {
            $theme['is_default'] = $theme['code'] === 'fastwork_inspired' ? 1 : 0;
            $CI->db->insert($themeTable, $theme);
        }
    }

    $sectionTable = db_prefix() . 'kt_landing_sections';
    $defaults = [
        'hero', 'trust_bar', 'features', 'products', 'industry_solutions', 'workflow',
        'addons', 'pricing', 'testimonials', 'faq', 'blog_preview', 'contact_cta', 'footer',
    ];
    foreach ($defaults as $idx => $key) {
        $exists = (int) $CI->db->where('page_key', 'home')->where('section_key', $key)->count_all_results($sectionTable);
        if ($exists === 0) {
            $CI->db->insert($sectionTable, [
                'page_key' => 'home',
                'section_key' => $key,
                'title' => ucwords(str_replace('_', ' ', $key)),
                'updated_at' => date('Y-m-d H:i:s'),
                'sort_order' => $idx + 1,
                'is_enabled' => 1,
            ]);
        }
    }

    $pagesTable = db_prefix() . 'kt_landing_pages';
    $defaultPages = [
        ['title' => 'Home', 'slug' => 'home', 'status' => 'published', 'sort_order' => 1],
        ['title' => 'Pricing', 'slug' => 'pricing', 'status' => 'published', 'sort_order' => 2],
        ['title' => 'Features', 'slug' => 'features', 'status' => 'published', 'sort_order' => 3],
        ['title' => 'Solutions', 'slug' => 'solutions', 'status' => 'published', 'sort_order' => 4],
        ['title' => 'Blog', 'slug' => 'blog', 'status' => 'published', 'sort_order' => 5],
        ['title' => 'Contact', 'slug' => 'contact', 'status' => 'published', 'sort_order' => 6],
    ];
    foreach ($defaultPages as $page) {
        $exists = (int) $CI->db->where('slug', $page['slug'])->count_all_results($pagesTable);
        if ($exists === 0) {
            $page['created_at'] = date('Y-m-d H:i:s');
            $page['updated_at'] = date('Y-m-d H:i:s');
            $CI->db->insert($pagesTable, $page);
        }
    }
}

function kt_landing_ensure_pricing_sync_columns($CI)
{
    $table = db_prefix() . 'kt_landing_plan_overrides';
    $columns = [
        'marketing_subtitle' => "ALTER TABLE `" . $table . "` ADD COLUMN `marketing_subtitle` VARCHAR(255) NULL AFTER `marketing_title`",
        'source_plan_snapshot_hash' => "ALTER TABLE `" . $table . "` ADD COLUMN `source_plan_snapshot_hash` VARCHAR(64) NULL AFTER `sort_order`",
        'source_plan_snapshot_json' => "ALTER TABLE `" . $table . "` ADD COLUMN `source_plan_snapshot_json` LONGTEXT NULL AFTER `source_plan_snapshot_hash`",
        'source_plan_updated_at' => "ALTER TABLE `" . $table . "` ADD COLUMN `source_plan_updated_at` DATETIME NULL AFTER `source_plan_snapshot_json`",
        'last_synced_at' => "ALTER TABLE `" . $table . "` ADD COLUMN `last_synced_at` DATETIME NULL AFTER `source_plan_updated_at`",
    ];

    foreach ($columns as $column => $statement) {
        if (!$CI->db->field_exists($column, $table)) {
            $CI->db->query($statement);
        }
    }
}

function kt_landing_ensure_media_columns($CI)
{
    $table = db_prefix() . 'kt_landing_media';
    $columns = [
        'mime_type' => "ALTER TABLE `" . $table . "` ADD COLUMN `mime_type` VARCHAR(120) NULL AFTER `file_type`",
        'alt_text' => "ALTER TABLE `" . $table . "` ADD COLUMN `alt_text` VARCHAR(255) NULL AFTER `title`",
        'caption' => "ALTER TABLE `" . $table . "` ADD COLUMN `caption` TEXT NULL AFTER `alt_text`",
        'tags' => "ALTER TABLE `" . $table . "` ADD COLUMN `tags` VARCHAR(255) NULL AFTER `caption`",
        'category' => "ALTER TABLE `" . $table . "` ADD COLUMN `category` VARCHAR(120) NULL AFTER `tags`",
        'width' => "ALTER TABLE `" . $table . "` ADD COLUMN `width` INT NULL AFTER `category`",
        'height' => "ALTER TABLE `" . $table . "` ADD COLUMN `height` INT NULL AFTER `width`",
        'usage_count' => "ALTER TABLE `" . $table . "` ADD COLUMN `usage_count` INT NOT NULL DEFAULT 0 AFTER `height`",
        'last_used_at' => "ALTER TABLE `" . $table . "` ADD COLUMN `last_used_at` DATETIME NULL AFTER `usage_count`",
    ];

    foreach ($columns as $column => $statement) {
        if (!$CI->db->field_exists($column, $table)) {
            $CI->db->query($statement);
        }
    }

    $usageTable = db_prefix() . 'kt_landing_media_usage';
    if (!$CI->db->table_exists($usageTable)) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `" . $usageTable . "` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `media_id` BIGINT UNSIGNED NOT NULL,
            `usage_type` VARCHAR(30) NOT NULL,
            `usage_ref_type` VARCHAR(80) NOT NULL,
            `usage_ref_id` BIGINT UNSIGNED NULL,
            `usage_ref_key` VARCHAR(120) NULL,
            `usage_label` VARCHAR(191) NULL,
            `source_field` VARCHAR(120) NULL,
            `source_value` LONGTEXT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_media_usage_media` (`media_id`,`usage_type`),
            KEY `idx_media_usage_ref` (`usage_ref_type`,`usage_ref_id`),
            CONSTRAINT `fk_kt_landing_media_usage_media`
                FOREIGN KEY (`media_id`) REFERENCES `" . $table . "`(`id`)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
    }
}

function kt_landing_ensure_publish_columns($CI)
{
    $snapshotTable = db_prefix() . 'kt_landing_publish_snapshots';
    if (!$CI->db->table_exists($snapshotTable)) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `" . $snapshotTable . "` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `snapshot_name` VARCHAR(190) NULL,
            `snapshot_type` VARCHAR(40) NOT NULL,
            `snapshot_status` VARCHAR(20) NOT NULL DEFAULT 'draft',
            `snapshot_version` INT NOT NULL DEFAULT 1,
            `payload_json` LONGTEXT NOT NULL,
            `checklist_json` LONGTEXT NULL,
            `summary_json` LONGTEXT NULL,
            `published_by` INT NULL,
            `published_at` DATETIME NULL,
            `archived_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_publish_snapshot_status` (`snapshot_status`,`created_at`),
            KEY `idx_publish_snapshot_type` (`snapshot_type`,`snapshot_version`)
        ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
    } else {
        $columns = [
            'snapshot_name' => "ALTER TABLE `" . $snapshotTable . "` ADD COLUMN `snapshot_name` VARCHAR(190) NULL AFTER `id`",
            'snapshot_status' => "ALTER TABLE `" . $snapshotTable . "` ADD COLUMN `snapshot_status` VARCHAR(20) NOT NULL DEFAULT 'draft' AFTER `snapshot_type`",
            'snapshot_version' => "ALTER TABLE `" . $snapshotTable . "` ADD COLUMN `snapshot_version` INT NOT NULL DEFAULT 1 AFTER `snapshot_status`",
            'checklist_json' => "ALTER TABLE `" . $snapshotTable . "` ADD COLUMN `checklist_json` LONGTEXT NULL AFTER `payload_json`",
            'summary_json' => "ALTER TABLE `" . $snapshotTable . "` ADD COLUMN `summary_json` LONGTEXT NULL AFTER `checklist_json`",
            'published_at' => "ALTER TABLE `" . $snapshotTable . "` ADD COLUMN `published_at` DATETIME NULL AFTER `published_by`",
            'archived_at' => "ALTER TABLE `" . $snapshotTable . "` ADD COLUMN `archived_at` DATETIME NULL AFTER `published_at`",
        ];

        foreach ($columns as $column => $statement) {
            if (!$CI->db->field_exists($column, $snapshotTable)) {
                $CI->db->query($statement);
            }
        }
    }

    $jobsTable = db_prefix() . 'kt_landing_publish_jobs';
    if (!$CI->db->table_exists($jobsTable)) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `" . $jobsTable . "` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `snapshot_id` BIGINT UNSIGNED NOT NULL,
            `publish_at` DATETIME NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'queued',
            `processed_at` DATETIME NULL,
            `error_message` TEXT NULL,
            `created_by` INT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_publish_due` (`status`,`publish_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
    }
}
