<?php

defined('BASEPATH') or exit('No direct script access allowed');

function kt_sepay_run_uninstall()
{
    $CI = &get_instance();

    $tables = [
        db_prefix() . 'kt_sepay_reconciliation_logs',
        db_prefix() . 'kt_sepay_webhook_logs',
        db_prefix() . 'kt_sepay_transactions',
        db_prefix() . 'kt_sepay_payment_requests',
        db_prefix() . 'kt_sepay_settings',
    ];

    foreach ($tables as $table) {
        $CI->db->query('DROP TABLE IF EXISTS `' . $table . '`');
    }

    delete_option('kt_sepay_schema_version');
    delete_option('kt_sepay_last_reconcile_transaction_id');
    delete_option('kt_sepay_last_reconcile_at');
}
