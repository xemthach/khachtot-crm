<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

$tables = [
    'kt_saas_backups',
    'kt_saas_provision_jobs',
    'kt_saas_activity_logs',
    'kt_saas_usage',
    'kt_saas_modules',
    'kt_saas_domains',
    'kt_saas_payments',
    'kt_saas_invoices',
    'kt_saas_subscriptions',
    'kt_saas_tenants',
    'kt_saas_plans',
];

foreach ($tables as $table) {
    if ($CI->db->table_exists(db_prefix() . $table)) {
        $CI->db->query('DROP TABLE `' . db_prefix() . $table . '`');
    }
}

$options = [
    'kt_saas_base_domain',
    'kt_saas_default_db_host',
    'kt_saas_default_db_port',
    'kt_saas_default_locale',
    'kt_saas_default_timezone',
    'kt_saas_default_currency',
    'kt_saas_default_storage_driver',
    'kt_saas_queue_mode',
    'kt_saas_allow_custom_domains',
    'kt_saas_runtime_enabled',
    'kt_saas_landlord_host',
];

foreach ($options as $option) {
    delete_option($option);
}
