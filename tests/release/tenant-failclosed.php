<?php
// Real classes, synthetic collaborators. Not DB-per-tenant runtime certification.
define('BASEPATH', __DIR__ . '/../../system/');
define('APPPATH', __DIR__ . '/../../application/');
define('APP_MODULES_PATH', __DIR__ . '/../../modules/');
define('APP_BASE_URL', 'https://khachtot.com/');
define('ENVIRONMENT', 'production');
function &get_instance() { global $ci; return $ci; }
function db_prefix() { return 'tbl'; }
function is_cli() { return false; }
function uri_string() { return 'admin'; }
function kt_saas_get_option($key, $default = '') { return $key === 'kt_saas_runtime_enabled' ? '1' : $default; }
require APP_MODULES_PATH . 'kt_saas/tenant_bootstrap/DatabaseSwitcher.php';
$original = new stdClass();
$ci = (object) [
    'db' => $original,
    'encryption' => new class { public function decrypt($value) { return 'synthetic'; } },
    'load' => new class { public function database($config, $return) { return new class { public function initialize() { return false; } }; } },
];
$r = (new DatabaseSwitcher())->switchConnection(['db_name'=>'fixture','db_host'=>'unused','db_user'=>'fixture','db_password_encrypted'=>'synthetic']);
if ($r['switched'] || $ci->db !== $original) { throw new Exception('failed connection replaced landlord'); }
require APP_MODULES_PATH . 'kt_saas/provisioning/ProvisioningJobRunner.php';
$runner = (new ReflectionClass('ProvisioningJobRunner'))->newInstanceWithoutConstructor();
$method = new ReflectionMethod($runner, 'fallbackToSharedDatabaseCredentials');
if ($method->invoke($runner, [], new Exception('synthetic')) !== null) { throw new Exception('shared credential fallback'); }
if (($argv[1] ?? '') === 'unknown-host') {
    $ci->db = new class { public function table_exists($table) { return false; } };
    $_SERVER['HTTP_HOST'] = 'unknown.khachtot.com';
    require APPPATH . 'hooks/KtSaasTenantBootstrap.php';
    $bootstrap = new class extends KtSaasTenantBootstrap { protected function trace($message) {} };
    $bootstrap->handle();
    throw new Exception('unknown host reached controller');
}
echo "PASS failed switch retains DB; shared credential fallback disabled (synthetic collaborators)\n";
