<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: KT MISA AMIS Accounting
Description: Mock-first, tenant/company-scoped MISA AMIS accounting foundation.
Version: 0.1.1
Requires at least: 3.0.*
Author: Internal Engineering
*/

defined('KT_MISA_AMIS_ACCOUNTING_MODULE') || define('KT_MISA_AMIS_ACCOUNTING_MODULE', 'kt_misa_amis_accounting');
defined('KT_MISA_AMIS_ACCOUNTING_VERSION') || define('KT_MISA_AMIS_ACCOUNTING_VERSION', '0.1.1');
defined('KT_MISA_AMIS_ACCOUNTING_ASSETS_URL') || define('KT_MISA_AMIS_ACCOUNTING_ASSETS_URL', module_dir_url(KT_MISA_AMIS_ACCOUNTING_MODULE, 'assets/'));

require_once __DIR__ . '/helpers/kt_misa_amis_accounting_helper.php';

hooks()->add_action('admin_init', 'kt_misa_amis_accounting_module_init');
hooks()->add_action('app_admin_head', 'kt_misa_amis_accounting_admin_head_assets');
hooks()->add_action('app_admin_footer', 'kt_misa_amis_accounting_admin_footer_assets');
hooks()->add_action('after_cron_run', 'kt_misa_amis_accounting_after_cron_run');

register_activation_hook(KT_MISA_AMIS_ACCOUNTING_MODULE, 'kt_misa_amis_accounting_activation_hook');
register_uninstall_hook(KT_MISA_AMIS_ACCOUNTING_MODULE, 'kt_misa_amis_accounting_uninstall_hook');
register_language_files(KT_MISA_AMIS_ACCOUNTING_MODULE, [KT_MISA_AMIS_ACCOUNTING_MODULE]);

function kt_misa_amis_accounting_module_init()
{
    $CI = &get_instance();
    $CI->load->helper(KT_MISA_AMIS_ACCOUNTING_MODULE . '/kt_misa_amis_accounting');
    kt_misa_amis_accounting_maybe_upgrade_schema();
    kt_misa_amis_accounting_register_capabilities();
    kt_misa_amis_accounting_register_menu_items();
}

function kt_misa_amis_accounting_activation_hook()
{
    require_once __DIR__ . '/install.php';
    kt_misa_amis_accounting_install();
}

function kt_misa_amis_accounting_uninstall_hook()
{
    // Deliberately preserve accounting/audit data. Uninstall is non-destructive.
    update_option('kt_misa_module_enabled', '0');
}

function kt_misa_amis_accounting_maybe_upgrade_schema()
{
    $version = (string) get_option('kt_misa_schema_version');
    if ($version !== '' && version_compare($version, KT_MISA_AMIS_ACCOUNTING_VERSION, '>=')) {
        return;
    }

    // Schema installation is performed by activation/install tooling, not every request.
}

function kt_misa_amis_accounting_after_cron_run()
{
    // Foundation has no external worker. Any future worker must claim an explicit scope.
}

function kt_misa_amis_accounting_admin_head_assets()
{
    if (kt_misa_is_module_request()) echo '<link rel="stylesheet" href="' . html_escape(KT_MISA_AMIS_ACCOUNTING_ASSETS_URL . 'css/misa_admin.css?v=1.0.0') . '" type="text/css">';
}

function kt_misa_amis_accounting_admin_footer_assets()
{
    if (kt_misa_is_module_request()) echo '<script src="' . html_escape(KT_MISA_AMIS_ACCOUNTING_ASSETS_URL . 'js/misa_admin.js?v=1.0.0') . '"></script>';
}

function kt_misa_amis_accounting_register_capabilities()
{
    register_staff_capabilities(
        KT_MISA_AMIS_ACCOUNTING_MODULE,
        [
            'capabilities' => [
                'kt_misa_view' => _l('kt_misa_permission_view'),
                'kt_misa_configure' => _l('kt_misa_permission_configure'),
                'kt_misa_test_connection' => _l('kt_misa_permission_test_connection'),
                'kt_misa_preview' => _l('kt_misa_permission_preview'),
                'kt_misa_sync' => _l('kt_misa_permission_sync'),
                'kt_misa_retry' => _l('kt_misa_permission_retry'),
                'kt_misa_cancel' => _l('kt_misa_permission_cancel'),
                'kt_misa_reconcile' => _l('kt_misa_permission_reconcile'),
                'kt_misa_resolve_conflict' => _l('kt_misa_permission_resolve_conflict'),
                'kt_misa_view_payload' => _l('kt_misa_permission_view_payload'),
                'kt_misa_view_sensitive_metadata' => _l('kt_misa_permission_view_sensitive_metadata'),
            ],
        ],
        _l('kt_misa_amis_accounting')
    );
}

function kt_misa_amis_accounting_register_menu_items()
{
    if (!kt_misa_staff_can('kt_misa_view')) {
        return;
    }

    $CI = &get_instance();
    $CI->app_menu->add_sidebar_menu_item('kt_misa_amis_accounting', [
        'slug' => 'kt_misa_amis_accounting',
        'name' => _l('kt_misa_amis_accounting'),
        'icon' => 'fa fa-calculator',
        'collapse' => true,
        'position' => 34,
    ]);

    $items = [
        ['slug' => 'kt_misa_dashboard', 'name' => _l('kt_misa_dashboard'), 'href' => admin_url('kt_misa_amis_accounting'), 'cap' => 'kt_misa_view'],
        ['slug' => 'kt_misa_connections', 'name' => _l('kt_misa_connections'), 'href' => admin_url('kt_misa_amis_accounting/connections'), 'cap' => 'kt_misa_configure'],
        ['slug' => 'kt_misa_readiness', 'name' => _l('kt_misa_readiness'), 'href' => admin_url('misa-amis/readiness'), 'cap' => 'kt_misa_view'],
        ['slug' => 'kt_misa_external_test', 'name' => _l('kt_misa_external_test'), 'href' => admin_url('misa-amis/external-test'), 'cap' => 'kt_misa_view'],
        ['slug' => 'kt_misa_mappings', 'name' => _l('kt_misa_mappings'), 'href' => admin_url('kt_misa_amis_accounting/mappings'), 'cap' => 'kt_misa_preview'],
        ['slug' => 'kt_misa_requests', 'name' => _l('kt_misa_requests'), 'href' => admin_url('kt_misa_amis_accounting/requests'), 'cap' => 'kt_misa_view'],
    ];

    $position = 1;
    foreach ($items as $item) {
        if (!kt_misa_staff_can($item['cap'])) {
            continue;
        }
        $item['position'] = $position++;
        $CI->app_menu->add_sidebar_children_item('kt_misa_amis_accounting', $item);
    }
}
