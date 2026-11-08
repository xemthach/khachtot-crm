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

if (!function_exists('get_staff_user_id')) {
    function get_staff_user_id()
    {
        return 0;
    }
}

if (!function_exists('log_message')) {
    function log_message($level, $message)
    {
    }
}

if (!function_exists('get_option')) {
    function get_option($key)
    {
        return $GLOBALS['kt_saas_test_options'][$key] ?? '';
    }
}

if (!function_exists('update_option')) {
    function update_option($key, $value)
    {
        $GLOBALS['kt_saas_test_options'][$key] = $value;
        return true;
    }
}

if (!function_exists('add_option')) {
    function add_option($key, $value)
    {
        $GLOBALS['kt_saas_test_options'][$key] = $value;
        return true;
    }
}

class App_Model
{
}

require_once dirname(__DIR__) . '/models/Kt_saas_model.php';

class FakeDmsAddonDb
{
    public array $usageByCode = [];
    private string $packageCode = '';

    public function where($field, $value)
    {
        if ($field === 'package_code') {
            $this->packageCode = strtolower(trim((string) $value));
        }

        return $this;
    }

    public function count_all_results($table)
    {
        return (int) ($this->usageByCode[$this->packageCode] ?? 0);
    }
}

class DmsPackageManagerHarness extends Kt_saas_model
{
    public $config;
    private FakeDmsAddonDb $fakeDb;

    public function __construct(FakeDmsAddonDb $fakeDb)
    {
        $this->fakeDb = $fakeDb;
        $this->config = new class ($fakeDb) {
            private FakeDmsAddonDb $fakeDb;

            public function __construct(FakeDmsAddonDb $fakeDb)
            {
                $this->fakeDb = $fakeDb;
            }

            public function item($key)
            {
                return $key === 'kt_saas_landlord_db' ? $this->fakeDb : null;
            }
        };
    }
}

$GLOBALS['kt_saas_test_options'] = [];
$fakeDb = new FakeDmsAddonDb();
$model = new DmsPackageManagerHarness($fakeDb);

$defaultCatalog = $model->get_dms_addon_package_catalog();
if (!isset($defaultCatalog['dms_standard'])) {
    throw new RuntimeException('Expected default DMS package catalog to expose dms_standard.');
}

$save = $model->save_dms_addon_package([
    'package_code' => 'dms_growth',
    'name' => 'DMS Growth',
    'monthly_price' => '1990000',
    'yearly_price' => '19900000',
    'user_limit' => '30',
    'store_limit' => '700',
    'product_limit' => '7000',
    'checkin_monthly_limit' => '15000',
    'photo_limit' => '30000',
    'report_export_limit' => '150',
    'log_retention_months' => '9',
    'live_tracking_enabled' => '1',
]);

if (empty($save['success'])) {
    throw new RuntimeException('Expected save_dms_addon_package() to persist a new package.');
}

$catalog = $model->get_dms_addon_package_catalog();
if (($catalog['dms_growth']['name'] ?? '') !== 'DMS Growth') {
    throw new RuntimeException('Expected saved DMS package to be returned from the package manager source.');
}

$fakeDb->usageByCode['dms_growth'] = 2;
$delete = $model->delete_dms_addon_package('dms_growth');
if (!empty($delete['success'])) {
    throw new RuntimeException('Expected used DMS package deletion to be blocked.');
}

$fakeDb->usageByCode['dms_growth'] = 0;
$delete = $model->delete_dms_addon_package('dms_growth');
if (empty($delete['success'])) {
    throw new RuntimeException('Expected unused DMS package deletion to succeed.');
}

echo "DmsPackageManagerSmokeTest passed.\n";
