<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * KT eInvoice — uninstall.php
 */

function kt_einvoice_run_uninstall()
{
    $CI = &get_instance();

    $tables = [
        'kt_einvoice_cron_logs',
        'kt_einvoice_batch_items',
        'kt_einvoice_batch_sessions',
        'kt_einvoice_quota_usage',
        'kt_einvoice_jobs',
        'kt_einvoice_api_logs',
        'kt_einvoice_records',
        'kt_einvoice_provider_settings',
    ];

    foreach ($tables as $table) {
        $CI->db->query("DROP TABLE IF EXISTS `" . db_prefix() . $table . "`");
    }

    // Xóa options
    $options = [
        'kt_einvoice_schema_version',
        'kt_einvoice_status_checker_last_run',
        'kt_einvoice_batch_issuer_last_run',
        'kt_einvoice_quota_sync_last_run',
    ];
    foreach ($options as $option) {
        delete_option($option);
    }
}

kt_einvoice_run_uninstall();
