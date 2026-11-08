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

if (!function_exists('kt_integration_hub_json_encode')) {
    function kt_integration_hub_json_encode($value)
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}

if (!function_exists('kt_integration_hub_json_decode')) {
    function kt_integration_hub_json_decode($value, $default = [])
    {
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : $default;
    }
}

class App_Model
{
}

class TenantEntitlementService
{
    public function getFeatureValue($tenantId, $featureKey, $default = null)
    {
        $map = [
            'dms.enabled' => true,
            'dms.checkin_enabled' => true,
            'dms.visit_enabled' => false,
            'dms.live_tracking_enabled' => true,
            'dms.route_analytics_enabled' => true,
            'dms.notification_bridge_enabled' => true,
            'dms.sso_enabled' => true,
            'dms.package_code' => 'DMS_STANDARD',
            'dms.user_limit' => 20,
            'dms.store_limit' => 1000,
            'dms.product_limit' => 5000,
            'dms.checkin_monthly_limit' => 10000,
            'dms.photo_limit' => 25000,
            'dms.report_export_limit' => 50,
            'dms.log_retention_months' => 6,
        ];

        return $map[$featureKey] ?? $default;
    }
}

require_once dirname(__DIR__) . '/models/Kt_saas_model.php';
require_once dirname(__DIR__) . '/services/LicensingService.php';

class FakePlanFeatureDb
{
    public array $rows = [];

    public function where($field, $value)
    {
        return $this;
    }

    public function delete($table)
    {
        $this->rows = [];
        return true;
    }

    public function insert($table, array $payload)
    {
        $this->rows[] = $payload;
        return true;
    }
}

class KtSaasPlanFeatureSyncHarness extends Kt_saas_model
{
    private FakePlanFeatureDb $fakeDb;
    public $config;

    public function __construct(FakePlanFeatureDb $fakeDb)
    {
        $this->fakeDb = $fakeDb;
        $this->config = new class ($fakeDb) {
            private $fakeDb;

            public function __construct($fakeDb)
            {
                $this->fakeDb = $fakeDb;
            }

            public function item($key)
            {
                return $key === 'kt_saas_landlord_db' ? $this->fakeDb : null;
            }
        };
    }

    public function syncForTest($planId, $moduleJson, array $workspaceFeatureKeys = [], array $integrationFeatureKeys = [], array $integrationFeatureValues = [])
    {
        return $this->sync_plan_features($planId, $moduleJson, $workspaceFeatureKeys, $integrationFeatureKeys, $integrationFeatureValues);
    }
}

$model = new Kt_saas_model();
$catalog = $model->get_dms_addon_package_catalog();
if (!isset($catalog['dms_standard'])) {
    throw new RuntimeException('Expected dms_standard to exist in the DMS package catalog.');
}

if ((int) ($catalog['dms_standard']['user_limit'] ?? 0) !== 20) {
    throw new RuntimeException('Expected dms_standard user limit to resolve from the DMS package catalog.');
}

$featureViewPath = dirname(__DIR__) . '/views/dashboard/plans.php';
$viewContents = file_get_contents($featureViewPath);
if ($viewContents === false) {
    throw new RuntimeException('Unable to read the plan editor view.');
}

foreach (['kt_integration_hub_dms_features', 'DMS package & quota payload'] as $legacyNeedle) {
    if (strpos($viewContents, $legacyNeedle) !== false) {
        throw new RuntimeException('Plan editor still exposes legacy DMS ownership copy: ' . $legacyNeedle);
    }
}

$fakeDb = new FakePlanFeatureDb();
$harness = new KtSaasPlanFeatureSyncHarness($fakeDb);
$harness->syncForTest(1001, json_encode(['crm'], JSON_UNESCAPED_UNICODE), [], [
    'dms.enabled',
    'dms.checkin_enabled',
    'kt_sepay.settings.edit',
], [
    'dms.package_code' => 'DMS_STANDARD',
    'dms.user_limit' => '20',
]);

$dmsRows = array_values(array_filter($fakeDb->rows, static function (array $row) {
    return ($row['module_name'] ?? '') === 'dms' || strpos((string) ($row['feature_key'] ?? ''), 'dms.') === 0;
}));

if (!empty($dmsRows)) {
    throw new RuntimeException('Expected sync_plan_features() to ignore all DMS commercial/runtime ownership rows.');
}

$nonDmsRows = array_values(array_filter($fakeDb->rows, static function (array $row) {
    return ($row['feature_key'] ?? '') === 'kt_sepay.settings.edit';
}));

if (count($nonDmsRows) !== 1 || (int) ($nonDmsRows[0]['is_enabled'] ?? 0) !== 1) {
    throw new RuntimeException('Expected non-DMS integration capabilities to remain syncable in CRM plans.');
}

$licensing = new LicensingService();
$tenant = [
    'id' => 1001,
    'tenant_code' => 'ABC',
    'company_name' => 'ABC Company',
    'status' => 'active',
];
$subscription = [
    'status' => 'active',
    'plan_code' => 'SME_PLUS',
];
$profile = [
    'module_codes' => ['crm', 'dms'],
];
$payload = $licensing->buildTenantLicensingPayload($tenant, $subscription, $profile, new TenantEntitlementService());

if (($payload['features']['DMS_ENABLED'] ?? null) !== false) {
    throw new RuntimeException('Expected plan feature catalog to not activate DMS without an active add-on.');
}

if (($payload['DMS_PACKAGE'] ?? null) !== '') {
    throw new RuntimeException('Expected DMS_PACKAGE to stay empty without an active DMS add-on.');
}

echo "DmsPlanEntitlementSmokeTest passed.\n";
