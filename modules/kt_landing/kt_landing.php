<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: KT Landing
Description: Public marketing landing for KT SaaS (home, pricing, signup).
Version: 0.1.0
Requires at least: 3.0.*
Author: Internal Engineering
*/

define('KT_LANDING_MODULE', 'kt_landing');
define('KT_LANDING_VERSION', '0.1.0');
define('KT_LANDING_ASSETS_URL', module_dir_url(KT_LANDING_MODULE, 'assets/'));

register_activation_hook(KT_LANDING_MODULE, 'kt_landing_module_activation_hook');
register_uninstall_hook(KT_LANDING_MODULE, 'kt_landing_module_uninstall_hook');
register_language_files(KT_LANDING_MODULE, [KT_LANDING_MODULE]);
hooks()->add_action('admin_init', 'kt_landing_module_admin_init');
hooks()->add_action('after_cron_run', 'kt_landing_after_cron_run');

function kt_landing_module_activation_hook()
{
    require_once __DIR__ . '/install.php';
    kt_landing_run_install();
}

function kt_landing_module_uninstall_hook()
{
    // Keep content/config for now.
}

function kt_landing_module_admin_init()
{
    $CI = &get_instance();
    $CI->load->helper(KT_LANDING_MODULE . '/kt_landing');
    require_once __DIR__ . '/install.php';
    kt_landing_run_install();

    if (!kt_landing_is_landlord_context()) {
        return;
    }

    kt_landing_register_staff_capabilities();
    kt_landing_register_menu_items();
}

function kt_landing_register_staff_capabilities()
{
    register_staff_capabilities(
        KT_LANDING_MODULE,
        [
            'capabilities' => [
                'kt_landing_view' => 'View Landing CMS',
                'kt_landing_configure' => 'Configure Landing settings',
                'kt_landing_theme' => 'Manage Design Studio',
                'kt_landing_blocks' => 'Manage Website Builder shared blocks',
                'kt_landing_sections' => 'Manage Website Builder pages and sections',
                'kt_landing_blog' => 'Manage Content Hub',
                'kt_landing_leads' => 'Manage Conversion Center',
                'kt_landing_media' => 'Manage Media Library',
                'kt_landing_analytics' => 'View Landing analytics',
                'kt_landing_clone' => 'Clone Landing templates',
                'kt_landing_preview' => 'Preview publish snapshots',
                'kt_landing_rollback' => 'Rollback publish snapshots',
                'kt_landing_publish' => 'Publish Landing changes',
            ],
        ],
        'KT Landing'
    );
}

function kt_landing_register_menu_items()
{
    if (!kt_landing_staff_can('kt_landing_view')) {
        return;
    }

    $CI = &get_instance();
    $CI->app_menu->add_sidebar_menu_item('kt_landing', [
        'slug' => 'kt_landing',
        'name' => 'Landing CMS',
        'icon' => 'fa fa-globe',
        'collapse' => true,
        'position' => 34,
    ]);

    $items = [
        ['slug' => 'kt_landing_overview', 'name' => 'Dashboard', 'href' => admin_url('kt_landing')],
        ['slug' => 'kt_landing_website_builder', 'name' => 'Website Builder', 'href' => admin_url('kt_landing/pages'), 'cap' => 'kt_landing_sections'],
        ['slug' => 'kt_landing_content_hub', 'name' => 'Content Hub', 'href' => admin_url('kt_landing/blog'), 'cap' => 'kt_landing_blog'],
        ['slug' => 'kt_landing_media', 'name' => 'Media', 'href' => admin_url('kt_landing/media'), 'cap' => 'kt_landing_media'],
        ['slug' => 'kt_landing_pricing', 'name' => 'Pricing', 'href' => admin_url('kt_landing/pricing'), 'cap' => 'kt_landing_configure'],
        ['slug' => 'kt_landing_marketplace', 'name' => 'Marketplace', 'href' => admin_url('kt_landing/addons'), 'cap' => 'kt_landing_configure'],
        ['slug' => 'kt_landing_seo', 'name' => 'SEO Center', 'href' => admin_url('kt_landing/seo'), 'cap' => 'kt_landing_configure'],
        ['slug' => 'kt_landing_conversion', 'name' => 'Leads', 'href' => admin_url('kt_landing/leads'), 'cap' => 'kt_landing_leads'],
        ['slug' => 'kt_landing_publish', 'name' => 'Publish Center', 'href' => admin_url('kt_landing/publish'), 'cap' => 'kt_landing_publish'],
        ['slug' => 'kt_landing_design_studio', 'name' => 'Design Studio', 'href' => admin_url('kt_landing/themes'), 'cap' => 'kt_landing_theme'],
        ['slug' => 'kt_landing_settings', 'name' => 'Settings', 'href' => admin_url('kt_landing/settings'), 'cap' => 'kt_landing_configure'],
    ];

    $position = 1;
    foreach ($items as $item) {
        if (isset($item['cap']) && !kt_landing_staff_can($item['cap'])) {
            continue;
        }
        $item['position'] = $position++;
        $CI->app_menu->add_sidebar_children_item('kt_landing', $item);
    }
}

function kt_landing_after_cron_run()
{
    if (!kt_landing_is_landlord_context()) {
        return;
    }

    $CI = &get_instance();
    $CI->load->model(KT_LANDING_MODULE . '/Kt_landing_model');
    $CI->Kt_landing_model->process_due_publish_jobs();
}
