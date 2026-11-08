<?php defined('BASEPATH') or define('BASEPATH', __DIR__);
$runner = file_get_contents(dirname(__DIR__, 2) . '/kt_saas/provisioning/ProvisioningJobRunner.php');
foreach (['dropTenantDatabase', 'dropTenantDatabaseUsers', 'restoreTenantDatabaseCredentials'] as $needle) {
    if (strpos($runner, $needle) === false) throw new RuntimeException('Missing rollback marker: ' . $needle);
}
$execute = substr($runner, strpos($runner, 'public function execute'), 7000);
if (strpos($execute, '$createdDatabase') === false || strpos($execute, 'if ($createdDatabase)') === false) {
    throw new RuntimeException('Provision rollback is not gated by createdDatabase.');
}
$model = file_get_contents(dirname(__DIR__) . '/models/Kt_integration_model.php');
foreach (['queue_all_dms_domain_sync_jobs_legacy', 'selected_only', 'kt_dms_sync_scope'] as $needle) {
    if (strpos($model, $needle) === false) throw new RuntimeException('Missing non-destructive sync governance marker: ' . $needle);
}
echo "DmsNoDestructiveProvisioningTest passed.\n";
