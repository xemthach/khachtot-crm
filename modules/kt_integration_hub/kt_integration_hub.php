<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: KT Integration Hub
Description: Omnichannel connection foundation for tenant lead/event intake, webhook verification, sync queue, and CRM writing.
Version: 0.2.3
Requires at least: 3.0.*
Author: Internal Engineering
*/

defined('KT_INTEGRATION_HUB_MODULE') || define('KT_INTEGRATION_HUB_MODULE', 'kt_integration_hub');
defined('KT_INTEGRATION_HUB_VERSION') || define('KT_INTEGRATION_HUB_VERSION', '0.2.3');

$ktIntegrationHelperPath = __DIR__ . '/helpers/kt_integration_hub_helper.php';
if (is_file($ktIntegrationHelperPath)) {
    require_once $ktIntegrationHelperPath;
}

hooks()->add_action('admin_init', 'kt_integration_hub_module_init');
hooks()->add_action('after_cron_run', 'kt_integration_hub_after_cron_run');
hooks()->add_action('staff_member_created', 'kt_integration_hub_queue_staff_sync_job');
hooks()->add_action('staff_member_updated', 'kt_integration_hub_queue_staff_sync_job');
hooks()->add_action('staff_member_profile_updated', 'kt_integration_hub_queue_staff_sync_job');
hooks()->add_action('after_staff_status_change', 'kt_integration_hub_queue_staff_sync_job');
hooks()->add_action('after_client_created', 'kt_integration_hub_queue_customer_sync_job');
hooks()->add_action('client_updated', 'kt_integration_hub_queue_customer_sync_job');
hooks()->add_action('client_status_changed', 'kt_integration_hub_queue_customer_sync_job');
hooks()->add_action('item_created', 'kt_integration_hub_queue_product_sync_job');
hooks()->add_action('after_item_updated', 'kt_integration_hub_queue_product_sync_job');

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

function kt_integration_hub_queue_staff_sync_job($staffId)
{
    $staffId = (int) $staffId;
    if ($staffId <= 0 || !kt_integration_hub_is_tenant_runtime()) {
        return;
    }

    $tenant = kt_integration_hub_current_tenant();
    $tenantId = (int) ($tenant['id'] ?? 0);
    if ($tenantId <= 0) {
        return;
    }

    $CI = &get_instance();
    $CI->load->model(KT_INTEGRATION_HUB_MODULE . '/Kt_integration_model');
    $result = $CI->Kt_integration_model->queue_staff_sync_job($tenantId, $staffId);

    if (!empty($result['success'])) {
        $CI->Kt_integration_model->log('info', 'staff.sync.queued', 'Queued staff sync job for Salesflow DMS.', [
            'staff_id' => $staffId,
            'job_id' => (int) ($result['job_id'] ?? 0),
            'request_id' => (string) ($result['request_id'] ?? ''),
        ], $tenantId, null, 'salesflow_dms');

        return;
    }

    $CI->Kt_integration_model->log('warning', 'staff.sync.queue_skipped', 'Skipped queueing staff sync job.', [
        'staff_id' => $staffId,
        'message' => (string) ($result['message'] ?? 'unknown'),
    ], $tenantId, null, 'salesflow_dms');
}

function kt_integration_hub_queue_customer_sync_job($payload)
{
    $customerId = is_array($payload) ? (int) ($payload['id'] ?? 0) : (int) $payload;
    if ($customerId <= 0 || !kt_integration_hub_is_tenant_runtime()) {
        return;
    }

    $tenant = kt_integration_hub_current_tenant();
    $tenantId = (int) ($tenant['id'] ?? 0);
    if ($tenantId <= 0) {
        return;
    }

    $CI = &get_instance();
    $CI->load->model(KT_INTEGRATION_HUB_MODULE . '/Kt_integration_model');
    $result = $CI->Kt_integration_model->queue_customer_sync_job($tenantId, $customerId);

    if (!empty($result['success'])) {
        $CI->Kt_integration_model->log('info', 'customer.sync.queued', 'Queued customer sync job for Salesflow DMS.', [
            'customer_id' => $customerId,
            'job_id' => (int) ($result['job_id'] ?? 0),
            'request_id' => (string) ($result['request_id'] ?? ''),
        ], $tenantId, null, 'salesflow_dms');

        return;
    }

    $CI->Kt_integration_model->log('warning', 'customer.sync.queue_skipped', 'Skipped queueing customer sync job.', [
        'customer_id' => $customerId,
        'message' => (string) ($result['message'] ?? 'unknown'),
    ], $tenantId, null, 'salesflow_dms');
}

function kt_integration_hub_queue_product_sync_job($payload)
{
    $productId = is_array($payload) ? (int) ($payload['id'] ?? 0) : (int) $payload;
    if ($productId <= 0 || !kt_integration_hub_is_tenant_runtime()) {
        return;
    }

    $tenant = kt_integration_hub_current_tenant();
    $tenantId = (int) ($tenant['id'] ?? 0);
    if ($tenantId <= 0) {
        return;
    }

    $CI = &get_instance();
    $CI->load->model(KT_INTEGRATION_HUB_MODULE . '/Kt_integration_model');
    $result = $CI->Kt_integration_model->queue_product_sync_job($tenantId, $productId);

    if (!empty($result['success'])) {
        $CI->Kt_integration_model->log('info', 'product.sync.queued', 'Queued product sync job for Salesflow DMS.', [
            'product_id' => $productId,
            'job_id' => (int) ($result['job_id'] ?? 0),
            'request_id' => (string) ($result['request_id'] ?? ''),
        ], $tenantId, null, 'salesflow_dms');

        return;
    }

    $CI->Kt_integration_model->log('warning', 'product.sync.queue_skipped', 'Skipped queueing product sync job.', [
        'product_id' => $productId,
        'message' => (string) ($result['message'] ?? 'unknown'),
    ], $tenantId, null, 'salesflow_dms');
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
        $items = [];

        $tenant = function_exists('kt_integration_hub_current_tenant') ? kt_integration_hub_current_tenant() : [];
        $tenantId = (int) ($tenant['id'] ?? 0);
        $moduleVisibility = [
            'setup_center' => false,
            'overview' => false,
            'checkins' => false,
            'visits' => false,
            'live_tracking' => false,
            'notifications' => false,
            'feedback' => false,
            'orders' => false,
            'sync_health' => false,
            'open_dms' => false,
        ];
        if ($tenantId > 0 && isset($CI->load) && method_exists($CI->load, 'model')) {
            $CI->load->model(KT_INTEGRATION_HUB_MODULE . '/Kt_integration_model');
            if (isset($CI->Kt_integration_model)) {
                $tenantSettings = $CI->Kt_integration_model->get_dms_tenant_settings($tenantId);
                $moduleVisibility = $CI->Kt_integration_model->get_dms_tenant_module_visibility($tenantId, $tenantSettings);
            }
        }

        $dmsItems = [
            ['module' => 'overview', 'slug' => 'kt_dms_overview', 'name' => 'Tổng quan DMS', 'href' => admin_url('kt_integration_hub/dms_overview'), 'cap' => 'kt_integration_hub_view'],
            ['module' => 'setup_center', 'slug' => 'kt_dms_setup_center', 'name' => 'Chuẩn bị DMS', 'href' => admin_url('kt_integration_hub/dms_setup_wizard'), 'cap' => 'kt_integration_hub_view'],
            ['module' => 'setup_center', 'slug' => 'kt_dms_staff', 'name' => 'Nhân sự', 'href' => admin_url('kt_integration_hub/dms_setup_center?tab=users'), 'cap' => 'kt_integration_hub_view'],
            ['module' => 'sync_health', 'slug' => 'kt_dms_store_products', 'name' => 'Điểm bán & sản phẩm', 'href' => admin_url('kt_integration_hub/dms_store_products'), 'cap' => 'kt_integration_hub_view'],
            ['module' => 'sync_health', 'slug' => 'kt_dms_sync_health', 'name' => 'Đồng bộ', 'href' => admin_url('kt_integration_hub/dms_sync_health'), 'cap' => 'kt_integration_hub_view'],
            ['module' => 'open_dms', 'slug' => 'kt_dms_open', 'name' => 'Mở DMS', 'href' => admin_url('kt_integration_hub/dms_open'), 'cap' => 'kt_integration_hub_view'],
            ['module' => 'setup_center', 'slug' => 'kt_dms_plan_limits', 'name' => 'Gói & hạn mức', 'href' => admin_url('kt_saas/dms_order_status'), 'cap' => 'kt_integration_hub_view'],
        ];
        $dmsItems = array_values(array_filter($dmsItems, static function ($item) use ($moduleVisibility) {
            return !empty($moduleVisibility[$item['module'] ?? '']);
        }));
        if (!empty($dmsItems)) {
            $CI->app_menu->add_sidebar_menu_item('kt_dms', [
                'slug'     => 'kt_dms',
                'name'     => _l('kt_integration_hub_dms_menu'),
                'icon'     => 'fa fa-truck',
                'collapse' => true,
                'position' => 33,
            ]);
        }
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
            ['slug' => 'kt_integration_hub_channel_orders', 'name' => _l('kt_integration_hub_channel_orders'), 'href' => admin_url('kt_integration_hub/channel_orders'), 'cap' => 'kt_integration_hub_view'],
        ];

        if (kt_integration_hub_staff_can('kt_integration_hub_manage')) {
            $CI->app_menu->add_sidebar_menu_item('kt_dms_operations', [
                'slug'     => 'kt_dms_operations',
                'name'     => 'Vận hành DMS',
                'icon'     => 'fa fa-truck',
                'collapse' => true,
                'position' => 33,
            ]);

            $dmsOperationsItems = [
                ['slug' => 'kt_dms_control_center', 'name' => 'Tổng quan vận hành DMS', 'href' => admin_url('kt_integration_hub/dms_control_center'), 'cap' => 'kt_integration_hub_manage'],
                ['slug' => 'kt_dms_landlord_tenants', 'name' => 'Tenant DMS', 'href' => admin_url('kt_integration_hub/dms_landlord_subscriptions'), 'cap' => 'kt_integration_hub_manage'],
                ['slug' => 'kt_dms_commercial_orders', 'name' => 'Đơn kích hoạt', 'href' => admin_url('kt_saas/dms_orders'), 'cap' => 'kt_integration_hub_manage', 'saas_cap' => 'kt_saas_view'],
                ['slug' => 'kt_dms_packages', 'name' => 'Gói DMS', 'href' => admin_url('kt_saas/dms_packages'), 'cap' => 'kt_integration_hub_manage', 'saas_cap' => 'kt_saas_manage_plans'],
                ['slug' => 'kt_dms_landlord_dashboard', 'name' => 'Sức khỏe CRM ↔ DMS', 'href' => admin_url('kt_integration_hub/dms_landlord_dashboard'), 'cap' => 'kt_integration_hub_manage'],
                ['slug' => 'kt_dms_logs', 'name' => 'Nhật ký', 'href' => admin_url('kt_integration_hub/logs'), 'cap' => 'kt_integration_hub_logs'],
                ['slug' => 'kt_dms_connections', 'name' => 'Cấu hình kết nối', 'href' => admin_url('kt_integration_hub/connections'), 'cap' => 'kt_integration_hub_view'],
            ];
        }
    }

    $position = 1;
    foreach ($items as $item) {
        if (isset($item['cap']) && !kt_integration_hub_staff_can($item['cap'])) {
            continue;
        }

        $item['position'] = $position++;
        $CI->app_menu->add_sidebar_children_item('kt_integration_hub', $item);
    }

    if (isset($dmsItems)) {
        $position = 1;
        foreach ($dmsItems as $item) {
            if (isset($item['cap']) && !kt_integration_hub_staff_can($item['cap'])) {
                continue;
            }

            unset($item['module']);
            $item['position'] = $position++;
            $CI->app_menu->add_sidebar_children_item('kt_dms', $item);
        }
    }

    if (isset($dmsOperationsItems)) {
        $position = 1;
        foreach ($dmsOperationsItems as $item) {
            if (isset($item['cap']) && !kt_integration_hub_staff_can($item['cap'])) {
                continue;
            }
            if (isset($item['saas_cap']) && function_exists('kt_saas_staff_can') && !kt_saas_staff_can($item['saas_cap'])) {
                continue;
            }

            unset($item['saas_cap']);
            $item['position'] = $position++;
            $CI->app_menu->add_sidebar_children_item('kt_dms_operations', $item);
        }
    }
}
