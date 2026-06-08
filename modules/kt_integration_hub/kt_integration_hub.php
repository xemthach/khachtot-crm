<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: KT Integration Hub
Description: Omnichannel connection foundation for tenant lead/event intake, webhook verification, sync queue, and CRM writing.
Version: 0.2.0
Requires at least: 3.0.*
Author: Internal Engineering
*/

define('KT_INTEGRATION_HUB_MODULE', 'kt_integration_hub');
define('KT_INTEGRATION_HUB_VERSION', '0.2.0');

hooks()->add_action('admin_init', 'kt_integration_hub_module_init');
hooks()->add_action('after_cron_run', 'kt_integration_hub_after_cron_run');

register_activation_hook(KT_INTEGRATION_HUB_MODULE, 'kt_integration_hub_activation_hook');
register_uninstall_hook(KT_INTEGRATION_HUB_MODULE, 'kt_integration_hub_uninstall_hook');
register_language_files(KT_INTEGRATION_HUB_MODULE, [KT_INTEGRATION_HUB_MODULE]);

function kt_integration_hub_module_init()
{
    $CI = &get_instance();
    $CI->load->helper(KT_INTEGRATION_HUB_MODULE . '/kt_integration_hub');

    kt_integration_hub_maybe_upgrade_schema();
    kt_integration_hub_register_staff_capabilities();
    kt_integration_hub_register_menu_items();
}

function kt_integration_hub_activation_hook()
{
    require_once __DIR__ . '/install.php';
}

function kt_integration_hub_maybe_upgrade_schema()
{
    $schemaVersion = get_option('kt_integration_hub_schema_version');
    if ((string) $schemaVersion === (string) KT_INTEGRATION_HUB_VERSION) {
        return;
    }

    require_once __DIR__ . '/install.php';
}

function kt_integration_hub_uninstall_hook()
{
    require_once __DIR__ . '/uninstall.php';
}

function kt_integration_hub_after_cron_run()
{
    $CI = &get_instance();
    $CI->load->model(KT_INTEGRATION_HUB_MODULE . '/Kt_integration_model');
    $CI->Kt_integration_model->process_due_jobs(25);
}

function kt_integration_hub_register_staff_capabilities()
{
    register_staff_capabilities(
        KT_INTEGRATION_HUB_MODULE,
        [
            'capabilities' => [
                'kt_integration_hub_view'        => _l('kt_integration_hub_permission_view'),
                'kt_integration_hub_manage'      => _l('kt_integration_hub_permission_manage'),
                'kt_integration_hub_connect'     => _l('kt_integration_hub_permission_connect'),
                'kt_integration_hub_disconnect'  => _l('kt_integration_hub_permission_disconnect'),
                'kt_integration_hub_logs'        => _l('kt_integration_hub_permission_logs'),
                'kt_integration_hub_retry_jobs'  => _l('kt_integration_hub_permission_retry_jobs'),
            ],
        ],
        _l('kt_integration_hub')
    );
}

function kt_integration_hub_register_menu_items()
{
    $CI = &get_instance();

    if (!kt_integration_hub_staff_can('kt_integration_hub_view')) {
        return;
    }

    if (function_exists('kt_saas_is_tenant_runtime') && kt_saas_is_tenant_runtime()) {
        $CI->app_menu->add_sidebar_menu_item('kt_integration_hub', [
            'slug'     => 'kt_integration_hub',
            'name'     => _l('kt_integration_hub_tenant_menu'),
            'icon'     => 'fa fa-plug',
            'collapse' => true,
            'position' => 32,
        ]);

        $items = [
            ['slug' => 'kt_integration_hub_dashboard', 'name' => _l('kt_integration_hub_dashboard'), 'href' => admin_url('kt_integration_hub')],
            ['slug' => 'kt_integration_hub_connections', 'name' => _l('kt_integration_hub_connections'), 'href' => admin_url('kt_integration_hub/connections'), 'cap' => 'kt_integration_hub_connect'],
            ['slug' => 'kt_integration_hub_channel_orders', 'name' => _l('kt_integration_hub_channel_orders'), 'href' => admin_url('kt_integration_hub/channel_orders'), 'cap' => 'kt_integration_hub_view'],
            ['slug' => 'kt_integration_hub_jobs', 'name' => _l('kt_integration_hub_jobs'), 'href' => admin_url('kt_integration_hub/jobs'), 'cap' => 'kt_integration_hub_retry_jobs'],
            ['slug' => 'kt_integration_hub_logs', 'name' => _l('kt_integration_hub_logs'), 'href' => admin_url('kt_integration_hub/logs'), 'cap' => 'kt_integration_hub_logs'],
        ];
    } else {
        $CI->app_menu->add_sidebar_menu_item('kt_integration_hub', [
            'slug'     => 'kt_integration_hub',
            'name'     => _l('kt_integration_hub_landlord_menu'),
            'icon'     => 'fa fa-random',
            'collapse' => true,
            'position' => 32,
        ]);

        $items = [
            ['slug' => 'kt_integration_hub_dashboard', 'name' => _l('kt_integration_hub_dashboard'), 'href' => admin_url('kt_integration_hub')],
            ['slug' => 'kt_integration_hub_providers', 'name' => _l('kt_integration_hub_providers'), 'href' => admin_url('kt_integration_hub/providers'), 'cap' => 'kt_integration_hub_manage'],
            ['slug' => 'kt_integration_hub_connections', 'name' => _l('kt_integration_hub_connections'), 'href' => admin_url('kt_integration_hub/connections'), 'cap' => 'kt_integration_hub_view'],
            ['slug' => 'kt_integration_hub_channel_orders', 'name' => _l('kt_integration_hub_channel_orders'), 'href' => admin_url('kt_integration_hub/channel_orders'), 'cap' => 'kt_integration_hub_view'],
            ['slug' => 'kt_integration_hub_jobs', 'name' => _l('kt_integration_hub_jobs'), 'href' => admin_url('kt_integration_hub/jobs'), 'cap' => 'kt_integration_hub_retry_jobs'],
            ['slug' => 'kt_integration_hub_logs', 'name' => _l('kt_integration_hub_logs'), 'href' => admin_url('kt_integration_hub/logs'), 'cap' => 'kt_integration_hub_logs'],
        ];
    }

    $position = 1;
    foreach ($items as $item) {
        if (isset($item['cap']) && !kt_integration_hub_staff_can($item['cap'])) {
            continue;
        }

        $item['position'] = $position++;
        $CI->app_menu->add_sidebar_children_item('kt_integration_hub', $item);
    }
}
