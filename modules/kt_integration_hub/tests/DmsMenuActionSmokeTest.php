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
                        public function add_sidebar_menu_item($slug, array $item)
                        {
                        }

                        public function add_sidebar_children_item($parent, array $item)
                        {
                        }
                    };
                }
            };
        }

        return $ci;
    }
}

if (!function_exists('_l')) {
    function _l($key)
    {
        return $key;
    }
}

if (!function_exists('kt_integration_hub_json_decode')) {
    function kt_integration_hub_json_decode($value, $default = [])
    {
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : $default;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($uri = '')
    {
        return 'admin.php/' . ltrim((string) $uri, '/');
    }
}

if (!function_exists('site_url')) {
    function site_url($uri = '')
    {
        return 'http://example.test/' . ltrim((string) $uri, '/');
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

if (!function_exists('kt_saas_feature_allowed')) {
    function kt_saas_feature_allowed($moduleName, $featureKey, $default = true)
    {
        return true;
    }
}

if (!function_exists('kt_integration_hub_current_tenant')) {
    function kt_integration_hub_current_tenant()
    {
        return [
            'id' => 1001,
            'tenant_code' => 'ABC',
        ];
    }
}

if (!function_exists('access_denied')) {
    function access_denied($module = '')
    {
        throw new RuntimeException('Unexpected access denied: ' . $module);
    }
}

if (!function_exists('show_error')) {
    function show_error($message = '', $status_code = 500, $heading = 'Error')
    {
        throw new RuntimeException('Unexpected show_error: ' . $status_code . ' ' . $heading . ' ' . $message);
    }
}

class AdminController
{
}

class App_Model
{
}

class InputStub
{
    public function get($key, $xss = null)
    {
        return null;
    }
}

class LoaderStub
{
    public string $lastView = '';
    public array $lastData = [];

    public function view($view, array $data = [])
    {
        $this->lastView = $view;
        $this->lastData = $data;
    }
}

class SaasModelStub
{
    public function get_tenant($tenantId)
    {
        return ['id' => (int) $tenantId, 'subdomain' => 'abc'];
    }
}

class DmsModelStub
{
    public function get_salesflow_connection_for_tenant($tenantId)
    {
        return [
            'settings_json' => json_encode(['base_url' => 'http://salesrep.test']),
        ];
    }

    public function get_dms_overview_metrics($tenantId)
    {
        return [];
    }

    public function get_dms_business_dashboard($tenantId)
    {
        return [
            'today' => [],
            'field' => [],
            'attention' => [],
            'month' => [],
            'sources' => [],
        ];
    }

    public function get_dms_sales_team_today($tenantId)
    {
        return [];
    }

    public function get_dms_map_activity($tenantId)
    {
        return ['staff_locations' => [], 'checkin_locations' => [], 'order_locations' => []];
    }

    public function get_dms_store_product_operations($tenantId)
    {
        return ['usage' => []];
    }

    public function get_dms_checkins($tenantId, array $filters = [], $limit = 25, $offset = 0)
    {
        return ['rows' => [], 'total' => 0];
    }

    public function get_dms_checkin_summary($tenantId, array $filters = [])
    {
        return [];
    }

    public function get_dms_visits($tenantId, array $filters = [], $limit = 25, $offset = 0)
    {
        return ['rows' => [], 'total' => 0];
    }

    public function get_dms_visit_summary($tenantId, array $filters = [])
    {
        return [];
    }

    public function get_dms_route_performance_rows($tenantId, array $filters = [], $limit = 25, $offset = 0)
    {
        return ['rows' => [], 'total' => 0];
    }

    public function get_dms_route_performance_summary($tenantId, array $filters = [])
    {
        return [];
    }

    public function get_dms_live_tracking($tenantId, array $filters = [], $limit = 25, $offset = 0)
    {
        return ['rows' => [], 'total' => 0];
    }

    public function get_dms_live_tracking_summary($tenantId, array $filters = [])
    {
        return [];
    }

    public function get_dms_manual_reviews($tenantId, array $filters = [], $limit = 25, $offset = 0)
    {
        return ['rows' => [], 'total' => 0];
    }

    public function get_dms_manual_review_summary($tenantId, array $filters = [])
    {
        return [];
    }

    public function get_dms_notifications($tenantId, array $filters = [], $limit = 25, $offset = 0)
    {
        return ['rows' => [], 'total' => 0];
    }

    public function get_dms_notification_summary($tenantId, array $filters = [])
    {
        return [];
    }


    public function get_dms_orders_invoices($tenantId, array $filters = [], $limit = 25, $offset = 0)
    {
        return ['rows' => [], 'total' => 0];
    }

    public function get_dms_orders_invoice_summary($tenantId)
    {
        return ['total' => 0, 'with_invoice' => 0, 'failed' => 0, 'cancelled' => 0, 'pending' => 0];
    }

    public function get_dms_tenant_settings($tenantId)
    {
        return [
            'tenant_code' => 'ABC',
            'plan' => 'SME_PLUS',
            'status' => 'active',
            'features' => [
                'DMS_ENABLED' => true,
                'CHECKIN_ENABLED' => true,
                'VISIT_ENABLED' => true,
                'LIVE_TRACKING_ENABLED' => true,
                'ROUTE_ANALYTICS_ENABLED' => true,
                'NOTIFICATION_BRIDGE_ENABLED' => true,
                'SSO_ENABLED' => true,
            ],
        ];
    }

    public function get_dms_tenant_module_visibility($tenantId, array $tenantSettings = [])
    {
        return [
            'setup_center' => true,
            'overview' => true,
            'checkins' => true,
            'visits' => true,
            'live_tracking' => true,
            'notifications' => true,
            'orders' => true,
            'sync_health' => true,
            'open_dms' => true,
            'route_analytics' => false,
            'manual_reviews' => false,
        ];
    }

    public function get_dms_workspaces_for_tenant($tenantId, $companyId = null)
    {
        return [];
    }

    public function get_dms_bridge_source_status($tenantId, $source)
    {
        return ['source' => $source, 'record_count' => 0];
    }

    public function get_dms_console_status($tenantId, array $tenantSettings = [])
    {
        return [
            'staff_sync' => [],
            'customer_sync' => [],
            'product_sync' => [],
            'checkin_bridge' => [],
            'visit_bridge' => [],
            'order_bridge' => [],
            'usage' => ['metrics' => [], 'limits' => [], 'rows' => []],
            'latest_error' => null,
        ];
    }

    public function get_dms_staff_access_rows($tenantId)
    {
        return [];
    }

    public function get_dms_readiness_state($tenantId, array $tenantSettings = [])
    {
        return [
            'code' => 'READY',
            'label' => 'Ready',
            'summary' => 'Harness readiness state.',
            'dms_enabled' => true,
            'connection_ready' => true,
            'provisioned_users' => 1,
            'first_checkin_exists' => false,
            'first_visit_exists' => false,
            'tracking_exists' => false,
            'steps' => [
                ['label' => 'Provision users', 'done' => true, 'detail' => 'Done'],
            ],
        ];
    }

    public function get_dms_operational_settings_audit()
    {
        return [
            ['label' => 'Check-in validation radius', 'value' => 'Configurable in DMS source, default 200 meters', 'evidence' => 'settings source'],
        ];
    }
}

require_once dirname(__DIR__) . '/kt_integration_hub.php';
require_once dirname(__DIR__) . '/controllers/Kt_integration_hub.php';

class DmsMenuActionHarness extends Kt_integration_hub
{
    public $load;
    public $input;
    public $Kt_integration_model;
    public $Kt_saas_model;
}

$controllerClass = new ReflectionClass('DmsMenuActionHarness');
$controller = $controllerClass->newInstanceWithoutConstructor();
$controller->load = new LoaderStub();
$controller->input = new InputStub();
$controller->Kt_integration_model = new DmsModelStub();
$controller->Kt_saas_model = new SaasModelStub();

$actions = [
    'dms_setup_wizard' => 'kt_integration_hub/admin/dms/setup_wizard',
    'dms_setup_center' => 'kt_integration_hub/admin/dms/setup_center',
    'dms_overview' => 'kt_integration_hub/admin/dms/overview',
    'dms_sales_team_today' => 'kt_integration_hub/admin/dms/sales_team_today',
    'dms_map_activity' => 'kt_integration_hub/admin/dms/map_activity',
    'dms_checkins' => 'kt_integration_hub/admin/dms/field_activity',
    'dms_visits' => 'kt_integration_hub/admin/dms/field_activity',
    'dms_field_activity' => 'kt_integration_hub/admin/dms/field_activity',
    'dms_orders_invoices' => 'kt_integration_hub/admin/dms/orders_invoices',
    'dms_store_products' => 'kt_integration_hub/admin/dms/store_products',
    'dms_sync_health' => 'kt_integration_hub/admin/dms/sync_health',
    'dms_live_tracking' => 'kt_integration_hub/admin/dms/list',
    'dms_notifications' => 'kt_integration_hub/admin/dms/list',
    'dms_settings' => 'kt_integration_hub/admin/dms/setup_center',
];

foreach ($actions as $method => $expectedView) {
    $controller->load->lastView = '';
    $controller->load->lastData = [];
    $controller->$method();
    if ($controller->load->lastView !== $expectedView) {
        throw new RuntimeException('Expected view ' . $expectedView . ' for ' . $method . ', got ' . $controller->load->lastView);
    }
}

foreach (['dms_route_analytics', 'dms_manual_reviews'] as $hiddenAction) {
    try {
        $controller->$hiddenAction();
        throw new RuntimeException('Expected hidden module to be unavailable: ' . $hiddenAction);
    } catch (RuntimeException $e) {
        if (strpos($e->getMessage(), '404 DMS module unavailable') === false) {
            throw $e;
        }
    }
}

echo "DmsMenuActionSmokeTest passed.\n";
