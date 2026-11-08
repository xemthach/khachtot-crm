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
        $base = $module === 'kt_integration_hub'
            ? dirname(dirname(__DIR__)) . '/kt_integration_hub'
            : dirname(__DIR__);

        return $base . '/' . ltrim((string) $concat, '/');
    }
}

if (!function_exists('module_dir_url')) {
    function module_dir_url($module, $concat = '')
    {
        return 'modules/' . trim((string) $module, '/') . '/' . ltrim((string) $concat, '/');
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

                public function add_filter($hook, $callback)
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

if (!function_exists('kt_saas_is_tenant_runtime')) {
    function kt_saas_is_tenant_runtime()
    {
        return true;
    }
}

if (!function_exists('kt_saas_is_landlord_context')) {
    function kt_saas_is_landlord_context()
    {
        return false;
    }
}

if (!function_exists('kt_saas_can_manage_workspace_settings')) {
    function kt_saas_can_manage_workspace_settings()
    {
        return true;
    }
}

if (!function_exists('kt_saas_can_view_workspace_governance')) {
    function kt_saas_can_view_workspace_governance()
    {
        return false;
    }
}

if (!function_exists('kt_saas_staff_can')) {
    function kt_saas_staff_can($capability)
    {
        return true;
    }
}

if (!function_exists('is_admin')) {
    function is_admin()
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

                public function load()
                {
                    return $this;
                }

                public function helper($name)
                {
                }
            };
        }

        return $ci;
    }
}

class AdminController
{
}

class App_Model
{
}

if (!defined('KT_INTEGRATION_HUB_MODULE')) {
    define('KT_INTEGRATION_HUB_MODULE', 'kt_integration_hub');
}

require_once dirname(__DIR__) . '/kt_saas.php';
require_once dirname(__DIR__) . '/controllers/Kt_saas.php';
require_once dirname(dirname(__DIR__)) . '/kt_integration_hub/models/Kt_integration_model.php';

kt_saas_register_menu_items();

$ci = get_instance();
if (!isset($ci->app_menu->items['kt_saas_apps'])) {
    throw new RuntimeException('Khach Tot Apps menu group was not registered.');
}

if (isset($ci->app_menu->items['kt_saas_marketplace'])) {
    throw new RuntimeException('Marketplace must not remain a standalone top-level menu item.');
}

$marketplaceItem = null;
foreach ($ci->app_menu->children['kt_saas_apps'] ?? [] as $item) {
    if (($item['slug'] ?? '') === 'kt_saas_marketplace') {
        $marketplaceItem = $item;
        break;
    }
}

if (!$marketplaceItem || ($marketplaceItem['href'] ?? '') !== admin_url('kt_saas/tenant_marketplace')) {
    throw new RuntimeException('Marketplace was not registered inside Khach Tot Apps.');
}

if (!isset($ci->app_menu->items['kt_saas_account'])) {
    throw new RuntimeException('SaaS account and billing menu must remain separate from the app group.');
}

if (!method_exists('Kt_saas', 'tenant_marketplace') || !method_exists('Kt_saas', 'tenant_marketplace_app') || !method_exists('Kt_saas', 'tenant_marketplace_provisioning') || !method_exists('Kt_saas', 'tenant_install_salesflow_dms')) {
    throw new RuntimeException('Marketplace controller methods are missing.');
}

if (!method_exists('Kt_integration_model', 'get_dms_marketplace_state') || !method_exists('Kt_integration_model', 'resolve_dms_marketplace_state')) {
    throw new RuntimeException('Marketplace state resolver methods are missing.');
}

$viewFiles = [
    dirname(__DIR__) . '/views/tenant/marketplace.php',
    dirname(__DIR__) . '/views/tenant/marketplace_app.php',
    dirname(__DIR__) . '/views/tenant/marketplace_provisioning.php',
];

foreach ($viewFiles as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('Missing marketplace view file: ' . $file);
    }
}

$catalogView = file_get_contents(dirname(__DIR__) . '/views/tenant/marketplace.php');
$detailView = file_get_contents(dirname(__DIR__) . '/views/tenant/marketplace_app.php');
if (
    $catalogView === false
    || strpos($catalogView, 'Khach Tot DMS') === false
    || strpos($catalogView, 'What this app does') === false
    || strpos($catalogView, 'Marketplace Installation') === false
    || strpos($catalogView, 'View Product Page') === false
) {
    throw new RuntimeException('Marketplace catalog view does not contain expected M2 app card content.');
}

if (
    $detailView === false
    || strpos($detailView, 'Gói DMS cho đội sales thị trường') === false
    || strpos($detailView, 'Chọn gói DMS phù hợp') === false
    || strpos($detailView, 'Gói DMS hiện tại') === false
    || strpos($detailView, 'Chi tiết kỹ thuật') === false
    || strpos($detailView, 'Nhật ký cài đặt') === false
) {
    throw new RuntimeException('Marketplace app detail view does not contain expected M3 product page sections.');
}

echo "MarketplaceFoundationSmokeTest passed.\n";
