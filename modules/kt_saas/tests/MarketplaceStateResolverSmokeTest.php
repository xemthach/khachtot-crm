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
        return dirname(dirname(__DIR__)) . '/kt_integration_hub/' . ltrim((string) $concat, '/');
    }
}

class App_Model
{
}

if (!defined('KT_INTEGRATION_HUB_MODULE')) {
    define('KT_INTEGRATION_HUB_MODULE', 'kt_integration_hub');
}

require_once dirname(dirname(__DIR__)) . '/kt_integration_hub/models/Kt_integration_model.php';

class MarketplaceStateResolverHarness extends Kt_integration_model
{
    public function __construct()
    {
    }
}

$resolver = new MarketplaceStateResolverHarness();

$cases = [
    'suspended' => [
        'settings' => ['status' => 'suspended', 'features' => ['DMS_ENABLED' => true]],
        'readiness' => ['code' => 'ACTIVE', 'connection_ready' => true, 'provisioned_users' => 3],
        'provisioning' => [],
        'expected' => 'SUSPENDED',
    ],
    'discovered' => [
        'settings' => ['status' => 'active', 'features' => ['DMS_ENABLED' => false]],
        'readiness' => ['code' => 'NOT_INSTALLED', 'connection_ready' => false, 'provisioned_users' => 0],
        'provisioning' => [],
        'expected' => 'DISCOVERED',
    ],
    'selected' => [
        'settings' => ['status' => 'active', 'features' => ['DMS_ENABLED' => true]],
        'readiness' => ['code' => 'PROVISIONING', 'connection_ready' => false, 'provisioned_users' => 0],
        'provisioning' => ['queued_jobs' => 0, 'running_jobs' => 0, 'failed_jobs' => 0, 'completed_jobs' => 0, 'provisioning_status' => ''],
        'expected' => 'SELECTED',
    ],
    'provisioning' => [
        'settings' => ['status' => 'active', 'features' => ['DMS_ENABLED' => true]],
        'readiness' => ['code' => 'PROVISIONING', 'connection_ready' => false, 'provisioned_users' => 0],
        'provisioning' => ['queued_jobs' => 1, 'running_jobs' => 0, 'failed_jobs' => 0, 'completed_jobs' => 0, 'provisioning_status' => 'queued'],
        'expected' => 'PROVISIONING',
    ],
    'setup_required' => [
        'settings' => ['status' => 'active', 'features' => ['DMS_ENABLED' => true]],
        'readiness' => ['code' => 'READY', 'connection_ready' => true, 'provisioned_users' => 5],
        'provisioning' => ['queued_jobs' => 0, 'running_jobs' => 0, 'failed_jobs' => 0, 'completed_jobs' => 1, 'provisioning_status' => 'completed'],
        'expected' => 'SETUP_REQUIRED',
    ],
    'active' => [
        'settings' => ['status' => 'active', 'features' => ['DMS_ENABLED' => true]],
        'readiness' => ['code' => 'ACTIVE', 'connection_ready' => true, 'provisioned_users' => 5],
        'provisioning' => ['queued_jobs' => 0, 'running_jobs' => 0, 'failed_jobs' => 0, 'completed_jobs' => 1, 'provisioning_status' => 'completed'],
        'expected' => 'ACTIVE',
    ],
];

foreach ($cases as $name => $case) {
    $resolved = $resolver->resolve_dms_marketplace_state($case['settings'], $case['readiness'], $case['provisioning']);
    if (($resolved['code'] ?? null) !== $case['expected']) {
        throw new RuntimeException('State resolver case failed for ' . $name . '. Expected ' . $case['expected'] . ', got ' . ($resolved['code'] ?? 'null'));
    }
}

echo "MarketplaceStateResolverSmokeTest passed.\n";
