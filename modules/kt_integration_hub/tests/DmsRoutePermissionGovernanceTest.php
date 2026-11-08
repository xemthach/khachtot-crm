<?php defined('BASEPATH') or define('BASEPATH', __DIR__);
$controller = file_get_contents(dirname(__DIR__) . '/controllers/Kt_integration_hub.php');
$required = [
    'public function disconnect' => 'requirePostRequest',
    'public function retry_job' => 'requirePostRequest',
    'public function dms_save_staff_access' => 'requirePostRequest',
    'public function dms_save_sync_scope' => 'requirePostRequest',
    'public function dms_retry_bridge_record' => 'requirePostRequest',
    'public function cron_process_jobs' => 'requirePostRequest',
    'public function dms_landlord_support_override_run' => 'requirePostRequest',
];
foreach ($required as $signature => $guard) {
    $pos = strpos($controller, $signature);
    if ($pos === false) throw new RuntimeException('Missing method: ' . $signature);
    $slice = substr($controller, $pos, 700);
    if (strpos($slice, $guard) === false) throw new RuntimeException('Missing POST guard for ' . $signature);
}
$saas = file_get_contents(dirname(__DIR__, 2) . '/kt_saas/controllers/Kt_saas.php');
foreach (['retry_provision_job', 'run_provision_job', 'queue_provision_job'] as $method) {
    $pos = strpos($saas, 'public function ' . $method);
    if ($pos === false || strpos(substr($saas, $pos, 500), 'requirePost') === false) {
        throw new RuntimeException('Missing SaaS POST guard: ' . $method);
    }
}
echo "DmsRoutePermissionGovernanceTest passed.\n";
