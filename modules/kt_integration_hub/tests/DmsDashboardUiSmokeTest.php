<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);
if (!function_exists('db_prefix')) {
    function db_prefix(): string
    {
        return '';
    }
}

if (!function_exists('module_dir_path')) {
    function module_dir_path($module, $concat = '')
    {
        return dirname(__DIR__) . '/' . ltrim((string) $concat, '/');
    }
}

if (!function_exists('hooks')) {
    function hooks()
    {
        static $hooks;
        if (!$hooks) {
            $hooks = new class {
                public function add_action($hook, $callback)
                {
                }
            };
        }

        return $hooks;
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($module, $callback)
    {
    }
}

if (!function_exists('register_uninstall_hook')) {
    function register_uninstall_hook($module, $callback)
    {
    }
}

if (!function_exists('register_language_files')) {
    function register_language_files($module, array $modules)
    {
    }
}

if (!function_exists('get_instance')) {
    function &get_instance()
    {
        static $ci;
        if (!$ci) {
            $ci = new class {
                public $app_menu;

                public function __construct()
                {
                    $this->app_menu = new class {
                        public array $items = [];
                        public array $children = [];

                        public function add_sidebar_menu_item($slug, array $item)
                        {
                            $this->items[$slug] = $item;
                        }

                        public function add_sidebar_children_item($parent, array $item)
                        {
                            $this->children[$parent][] = $item;
                        }
                    };
                }
            };
        }

        return $ci;
    }
}

if (!function_exists('kt_integration_hub_staff_can')) {
    function kt_integration_hub_staff_can($capability)
    {
        return true;
    }
}

if (!function_exists('kt_saas_is_tenant_runtime')) {
    function kt_saas_is_tenant_runtime()
    {
        return true;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($uri = '')
    {
        return 'admin.php/' . ltrim((string) $uri, '/');
    }
}

if (!function_exists('_l')) {
    function _l($key)
    {
        return $key;
    }
}

class AdminController
{
}

class App_Model
{
}

$menuSource = file_get_contents(dirname(__DIR__) . '/kt_integration_hub.php');
require_once dirname(__DIR__) . '/kt_integration_hub.php';
require_once dirname(__DIR__) . '/controllers/Kt_integration_hub.php';
require_once dirname(__DIR__) . '/models/Kt_integration_model.php';

kt_integration_hub_register_menu_items();

$ci = get_instance();
$menu = $ci->app_menu;

$expectedDmsItems = [
    'kt_dms_overview',
    'kt_dms_setup_center',
    'kt_dms_staff',
    'kt_dms_store_products',
    'kt_dms_sync_health',
    'kt_dms_open',
    'kt_dms_plan_limits',
];

foreach ($expectedDmsItems as $slug) {
    if (strpos($menuSource, "'slug' => '" . $slug . "'") === false) {
        throw new RuntimeException('Missing DMS menu definition: ' . $slug);
    }
}

if (isset($menu->items['kt_dms']) || !empty($menu->children['kt_dms'])) {
    throw new RuntimeException('DMS menu must fail closed when tenant entitlement context is unavailable.');
}

$controllerMethods = [
    'dms_setup_wizard',
    'dms_setup_center',
    'dms_overview',
    'dms_checkins',
    'dms_visits',
    'dms_field_activity',
    'dms_orders_invoices',
    'dms_sync_health',
    'dms_open',
    'dms_route_analytics',
    'dms_live_tracking',
    'dms_manual_reviews',
    'dms_notifications',
    'dms_settings',
];

foreach ($controllerMethods as $method) {
    if (!method_exists('Kt_integration_hub', $method)) {
        throw new RuntimeException('Missing controller method: ' . $method);
    }
}

$modelMethods = [
    'get_dms_overview_metrics',
    'get_dms_checkins',
    'get_dms_visits',
    'get_dms_route_performance_summary',
    'get_dms_route_performance_rows',
    'get_dms_live_tracking',
    'get_dms_manual_reviews',
    'get_dms_notifications',
    'get_dms_tenant_settings',
    'get_dms_tenant_module_visibility',
    'get_dms_bridge_source_status',
    'get_dms_console_status',
    'get_dms_staff_access_rows',
    'get_dms_orders_invoices',
    'get_dms_orders_invoice_summary',
];

foreach ($modelMethods as $method) {
    if (!method_exists('Kt_integration_model', $method)) {
        throw new RuntimeException('Missing model method: ' . $method);
    }
}

$viewFiles = [
    dirname(__DIR__) . '/views/admin/dms/overview.php',
    dirname(__DIR__) . '/views/admin/dms/field_activity.php',
    dirname(__DIR__) . '/views/admin/dms/orders_invoices.php',
    dirname(__DIR__) . '/views/admin/dms/sync_health.php',
    dirname(__DIR__) . '/views/admin/dms/setup_wizard.php',
    dirname(__DIR__) . '/views/admin/dms/settings.php',
];

foreach ($viewFiles as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('Missing DMS view file: ' . $file);
    }
}

echo "DmsDashboardUiSmokeTest passed.\n";
