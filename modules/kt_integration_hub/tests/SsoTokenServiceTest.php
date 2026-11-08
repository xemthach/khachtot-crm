<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);
defined('KT_INTEGRATION_HUB_MODULE') or define('KT_INTEGRATION_HUB_MODULE', 'kt_integration_hub');
if (!function_exists('module_dir_path')) {
    function module_dir_path($module, $concat = '')
    {
        return dirname(__DIR__) . '/' . ltrim((string) $concat, '/');
    }
}
if (!function_exists('db_prefix')) {
    function db_prefix(): string
    {
        return '';
    }
}
if (!function_exists('kt_integration_hub_json_encode')) {
    function kt_integration_hub_json_encode($value)
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
if (!function_exists('kt_integration_hub_json_decode')) {
    function kt_integration_hub_json_decode($value, $default = [])
    {
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : $default;
    }
}
if (!function_exists('kt_integration_hub_dms_workspace_base_url')) {
    function kt_integration_hub_dms_workspace_base_url(array $tenant, array $connection = [])
    {
        return (string) ($tenant['base_url'] ?? '');
    }
}

require_once dirname(__DIR__) . '/libraries/SsoTokenException.php';
require_once dirname(__DIR__) . '/libraries/SsoTokenService.php';

$service = new SsoTokenService();

$tenant = [
    'id' => 27,
    'crm_tenant_id' => 27,
    'crm_company_id' => 7,
    'compatibility_crm_company_id' => 27,
    'tenant_code' => 'ABC',
    'status' => 'active',
    'base_url' => 'https://dms.example.test',
];

$staff = [
    'staffid' => 345,
    'email' => 'sso.staff@example.test',
    'firstname' => 'An',
    'lastname' => 'Nguyen',
    'active' => 1,
];

$issued = $service->issueToken($tenant, $staff, 'bridge-secret', 300, gmdate('c'));

if (($issued['payload']['crm_staff_id'] ?? null) !== 345) {
    throw new RuntimeException('Expected crm_staff_id in issued payload.');
}

if (($issued['payload']['crm_company_id'] ?? null) !== 7 || ($issued['payload']['compatibility_crm_company_id'] ?? null) !== 27) {
    throw new RuntimeException('Expected canonical and compatibility company identities in issued payload.');
}

if (! str_contains((string) ($issued['redirect_url'] ?? ''), '/sso/login?token=')) {
    throw new RuntimeException('Expected redirect URL to target DMS SSO login.');
}

$expiredThrown = false;
try {
    $service->issueToken($tenant, $staff, 'bridge-secret', 60, '2020-06-10T08:00:00+00:00');
} catch (SsoTokenException $e) {
    $expiredThrown = $e->apiCode() === 'TOKEN_EXPIRED';
}

if (! $expiredThrown) {
    throw new RuntimeException('Expected expired token issuance to be rejected.');
}

$inactiveStaff = $staff;
$inactiveStaff['active'] = 0;

try {
    $service->issueToken($tenant, $inactiveStaff, 'bridge-secret');
    throw new RuntimeException('Expected inactive staff token issuance to fail.');
} catch (SsoTokenException $e) {
    if ($e->apiCode() !== 'STAFF_INACTIVE') {
        throw $e;
    }
}

echo "SsoTokenServiceTest passed.\n";
