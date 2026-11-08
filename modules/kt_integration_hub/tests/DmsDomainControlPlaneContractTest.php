<?php

$root = dirname(__DIR__);
$helper = file_get_contents($root . '/helpers/kt_integration_hub_helper.php');
$mutationClient = file_get_contents($root . '/libraries/DmsWorkspaceMutationClient.php');
$registryClient = file_get_contents($root . '/libraries/DmsWorkspaceRegistryClient.php');
$providerClient = file_get_contents($root . '/libraries/ProviderSafeMirrorClient.php');
$staffJob = file_get_contents($root . '/libraries/SyncStaffToDmsJob.php');
$customerJob = file_get_contents($root . '/libraries/SyncCustomerToDmsJob.php');
$productJob = file_get_contents($root . '/libraries/SyncProductToDmsJob.php');
$controller = file_get_contents($root . '/controllers/Kt_integration_hub.php');
$model = file_get_contents($root . '/models/Kt_integration_model.php');

$required = [
    [$helper, 'function kt_integration_hub_dms_control_plane_base_url'],
    [$helper, 'DMS_CONTROL_PLANE_BASE_URL'],
    [$helper, 'control_plane_base_url'],
    [$mutationClient, 'kt_integration_hub_dms_control_plane_base_url'],
    [$registryClient, 'kt_integration_hub_dms_control_plane_base_url'],
    [$providerClient, 'kt_integration_hub_dms_control_plane_base_url'],
    [$staffJob, 'kt_integration_hub_dms_control_plane_base_url'],
    [$customerJob, 'kt_integration_hub_dms_control_plane_base_url'],
    [$productJob, 'kt_integration_hub_dms_control_plane_base_url'],
    [$controller, 'save_dms_workspace_mutation_result($tenantId,$companyId,$result)'],
    [$model, "\$result['workspace_host'] ?? (\$existing['workspace_host'] ?? '')"],
    [$model, "\$result['domain_status'] ?? \$result['status']"],
];

foreach ($required as [$source, $marker]) {
    if (strpos($source, $marker) === false) {
        throw new RuntimeException('Missing DMS domain control-plane marker: ' . $marker);
    }
}

if (strpos($mutationClient, 'kt_integration_hub_dms_workspace_base_url($tenant, $connection) ?: trim') !== false) {
    throw new RuntimeException('Domain mutation client must not depend on tenant workspace URL as primary control plane.');
}

echo "DmsDomainControlPlaneContractTest PASS\n";
