<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: KT SePay
Description: SePay payment gateway integration for Perfex CRM and KT SaaS.
Version: 0.1.1
Requires at least: 3.0.*
Author: Internal Engineering
*/

define('KT_SEPAY_MODULE', 'kt_sepay');
define('KT_SEPAY_VERSION', '0.1.2');
define('KT_SEPAY_GATEWAY_ID', 'Kt_sepay_gateway');
define('KT_SEPAY_ASSETS_URL', module_dir_url(KT_SEPAY_MODULE, 'assets/'));

register_language_files(KT_SEPAY_MODULE, [KT_SEPAY_MODULE]);
register_payment_gateway(KT_SEPAY_GATEWAY_ID, KT_SEPAY_MODULE);
register_activation_hook(KT_SEPAY_MODULE, 'kt_sepay_module_activation_hook');
register_uninstall_hook(KT_SEPAY_MODULE, 'kt_sepay_module_uninstall_hook');

hooks()->add_action('admin_init', 'kt_sepay_module_init');
hooks()->add_action('app_admin_head', 'kt_sepay_admin_head_assets');
hooks()->add_action('app_admin_footer', 'kt_sepay_admin_footer_assets');
hooks()->add_action('after_cron_run', 'kt_sepay_after_cron_run');

function kt_sepay_module_activation_hook()
{
    require_once __DIR__ . '/install.php';
    kt_sepay_run_install();
}

function kt_sepay_module_uninstall_hook()
{
    require_once __DIR__ . '/uninstall.php';
    kt_sepay_run_uninstall();
}

function kt_sepay_module_init()
{
    $CI = &get_instance();
    $CI->load->helper(KT_SEPAY_MODULE . '/kt_sepay');
    $CI->load->model(KT_SEPAY_MODULE . '/Kt_sepay_model');

    kt_sepay_maybe_upgrade_schema();
    kt_sepay_register_staff_capabilities();
    kt_sepay_register_menu_items();
}

function kt_sepay_maybe_upgrade_schema()
{
    if (!function_exists('get_option') || !function_exists('update_option')) {
        return;
    }

    if (function_exists('kt_saas_is_landlord_context') && !kt_saas_is_landlord_context()) {
        return;
    }

    $current = (string) get_option('kt_sepay_schema_version');
    if ($current === KT_SEPAY_VERSION) {
        return;
    }

    require_once __DIR__ . '/install.php';
    kt_sepay_run_install();
    update_option('kt_sepay_schema_version', KT_SEPAY_VERSION);
}

function kt_sepay_register_staff_capabilities()
{
    register_staff_capabilities(
        KT_SEPAY_MODULE,
        [
            'capabilities' => [
                'kt_sepay_view'            => _l('kt_sepay_permission_view'),
                'kt_sepay_manage_settings' => _l('kt_sepay_permission_manage_settings'),
                'kt_sepay_manage_payments' => _l('kt_sepay_permission_manage_payments'),
                'kt_sepay_manage_logs'     => _l('kt_sepay_permission_manage_logs'),
                'kt_sepay_run_reconcile'   => _l('kt_sepay_permission_run_reconcile'),
            ],
        ],
        _l('kt_sepay')
    );
}

function kt_sepay_register_menu_items()
{
    if (function_exists('kt_saas_is_tenant_runtime') && kt_saas_is_tenant_runtime()) {
        if (!is_admin()) {
            return;
        }

        $CI = &get_instance();
        $CI->app_menu->add_sidebar_menu_item('kt_sepay', [
            'slug'     => 'kt_sepay',
            'name'     => _l('kt_sepay'),
            'icon'     => 'fa fa-qrcode',
            'collapse' => true,
            'position' => 30.3,
        ]);

        $items = [
            ['slug' => 'kt_sepay_tenant_portal', 'name' => _l('kt_sepay_dashboard'), 'href' => admin_url('kt_sepay/tenant_portal')],
            ['slug' => 'kt_sepay_tenant_settings', 'name' => _l('kt_sepay_settings'), 'href' => admin_url('kt_sepay/tenant_settings')],
            ['slug' => 'kt_sepay_tenant_payment_requests', 'name' => _l('kt_sepay_payment_requests'), 'href' => admin_url('kt_sepay/tenant_payment_requests')],
            ['slug' => 'kt_sepay_tenant_transactions', 'name' => _l('kt_sepay_transactions'), 'href' => admin_url('kt_sepay/tenant_transactions')],
            ['slug' => 'kt_sepay_tenant_reconciliation', 'name' => _l('kt_sepay_reconciliation'), 'href' => admin_url('kt_sepay/tenant_reconciliation')],
        ];

        $position = 1;
        foreach ($items as $item) {
            $item['position'] = $position++;
            $CI->app_menu->add_sidebar_children_item('kt_sepay', $item);
        }

        return;
    }

    if (!kt_sepay_is_landlord_context()) {
        return;
    }

    if (!kt_sepay_staff_can('kt_sepay_view')) {
        return;
    }

    $CI = &get_instance();
    $CI->app_menu->add_sidebar_menu_item('kt_sepay', [
        'slug'     => 'kt_sepay',
        'name'     => _l('kt_sepay'),
        'icon'     => 'fa fa-qrcode',
        'collapse' => true,
        'position' => 32,
    ]);

    $items = [
        ['slug' => 'kt_sepay_dashboard', 'name' => _l('kt_sepay_dashboard'), 'href' => admin_url('kt_sepay')],
        ['slug' => 'kt_sepay_settings', 'name' => _l('kt_sepay_settings'), 'href' => admin_url('kt_sepay/settings'), 'cap' => 'kt_sepay_manage_settings'],
        ['slug' => 'kt_sepay_transactions', 'name' => _l('kt_sepay_transactions'), 'href' => admin_url('kt_sepay/transactions'), 'cap' => 'kt_sepay_manage_payments'],
        ['slug' => 'kt_sepay_payment_requests', 'name' => _l('kt_sepay_payment_requests'), 'href' => admin_url('kt_sepay/payment_requests'), 'cap' => 'kt_sepay_manage_payments'],
        ['slug' => 'kt_sepay_webhook_logs', 'name' => _l('kt_sepay_webhook_logs'), 'href' => admin_url('kt_sepay/logs'), 'cap' => 'kt_sepay_manage_logs'],
        ['slug' => 'kt_sepay_reconciliation', 'name' => _l('kt_sepay_reconciliation'), 'href' => admin_url('kt_sepay/reconciliation'), 'cap' => 'kt_sepay_run_reconcile'],
        ['slug' => 'kt_sepay_test_mode', 'name' => _l('kt_sepay_test_mode'), 'href' => admin_url('kt_sepay/test_mode'), 'cap' => 'kt_sepay_manage_settings'],
    ];

    $position = 1;
    foreach ($items as $item) {
        if (isset($item['cap']) && !kt_sepay_staff_can($item['cap'])) {
            continue;
        }

        $item['position'] = $position++;
        $CI->app_menu->add_sidebar_children_item('kt_sepay', $item);
    }
}

function kt_sepay_admin_head_assets()
{
    if (!kt_sepay_is_module_request()) {
        return;
    }

    echo '<link href="' . KT_SEPAY_ASSETS_URL . 'css/kt_sepay.css?v=' . KT_SEPAY_VERSION . '" rel="stylesheet" type="text/css" />';
}

function kt_sepay_admin_footer_assets()
{
    if (!kt_sepay_is_module_request()) {
        return;
    }

    echo '<script src="' . KT_SEPAY_ASSETS_URL . 'js/kt_sepay.js?v=' . KT_SEPAY_VERSION . '"></script>';
}

function kt_sepay_after_cron_run()
{
    require_once __DIR__ . '/cron/Kt_sepay_cron.php';
    kt_sepay_run_scheduled_jobs();
}
