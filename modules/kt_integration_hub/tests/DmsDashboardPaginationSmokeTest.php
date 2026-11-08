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
    public $input;
}

class App_Model
{
}

require_once dirname(__DIR__) . '/controllers/Kt_integration_hub.php';

$controller = (new ReflectionClass('Kt_integration_hub'))->newInstanceWithoutConstructor();
$controller->input = new class {
    private array $values = [
        'page' => '0',
        'limit' => '250',
        'status' => 'warning ',
        'crm_staff_id' => ' 99 ',
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-10',
    ];

    public function get($key, $xssClean = true)
    {
        return $this->values[$key] ?? null;
    }
};

$resolvePaging = new ReflectionMethod('Kt_integration_hub', 'resolvePaging');
$resolvePaging->setAccessible(true);
$paging = $resolvePaging->invoke($controller, 25);

if (($paging['page'] ?? null) !== 1) {
    throw new RuntimeException('Expected page to clamp to 1.');
}

if (($paging['limit'] ?? null) !== 100) {
    throw new RuntimeException('Expected limit to clamp to 100.');
}

$collectFilters = new ReflectionMethod('Kt_integration_hub', 'collectFilters');
$collectFilters->setAccessible(true);
$filters = $collectFilters->invoke($controller, ['status', 'crm_staff_id', 'date_from', 'date_to']);

if (($filters['status'] ?? null) !== 'warning') {
    throw new RuntimeException('Expected status to be trimmed.');
}

if (($filters['crm_staff_id'] ?? null) !== '99') {
    throw new RuntimeException('Expected crm_staff_id to be trimmed.');
}

if (($filters['date_from'] ?? null) !== '2026-06-01') {
    throw new RuntimeException('Expected date_from to be preserved.');
}

if (($filters['date_to'] ?? null) !== '2026-06-10') {
    throw new RuntimeException('Expected date_to to be preserved.');
}

echo "DmsDashboardPaginationSmokeTest passed.\n";
