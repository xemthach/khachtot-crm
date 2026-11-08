<?php defined('BASEPATH') or define('BASEPATH', __DIR__);

$model = file_get_contents(dirname(__DIR__) . '/models/Kt_saas_model.php');

if (strpos($model, 'private function is_core_tenant_provision_job') === false) {
    throw new RuntimeException('Missing core tenant provision job classifier.');
}

$required = [
    'if ($tenantId && $isCoreTenantProvision)' => 'create_provision_job must not update tenant provisioning_status for DMS add-on jobs.',
    "where('job_type', 'provision_tenant')" => 'repair_provisioning_state_consistency must only use core tenant jobs for tenant state.',
    'if (!empty($job[\'tenant_id\']) && $isCoreTenantProvision)' => 'job lifecycle methods must isolate DMS add-on jobs from CRM tenant state.',
    'if ($tenantReady && !empty($job[\'tenant_id\']) && $isCoreTenantProvision)' => 'tenant onboarding email dispatch must remain core-provision only.',
];

foreach ($required as $needle => $message) {
    if (strpos($model, $needle) === false) {
        throw new RuntimeException($message);
    }
}

echo "DmsAddonProvisioningStateIsolationTest passed.\n";
