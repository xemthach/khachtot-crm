<?php

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/controllers/Kt_integration_hub.php');
$model = file_get_contents($root . '/models/Kt_integration_model.php');
$client = file_get_contents($root . '/libraries/DmsWorkspaceMutationClient.php');
$workspaceView = file_get_contents($root . '/views/tenant/dms/workspace_add.php');
$domainView = file_get_contents($root . '/views/tenant/dms/domains.php');

$required = [
    [$controller, 'function dms_domain_availability'],
    [$controller, "requireCapability('kt_integration_hub_manage')"],
    [$controller, 'function dms_domain_check'],
    [$client, '/api/v1/integration/domains/availability'],
    [$client, '/api/v1/integration/domains/check'],
    [$model, "'domain_status' => trim((string) (\$result['domain_status']"],
    [$model, "'domain_id' => !empty(\$result['domain_id'])"],
    [$workspaceView, 'Kiểm tra subdomain'],
    [$domainView, 'Kiểm tra kết nối DNS'],
];

foreach ($required as [$source, $marker]) {
    if (strpos($source, $marker) === false) {
        throw new RuntimeException('Missing M13.2 domain hardening marker: ' . $marker);
    }
}

if (strpos($model, "'domain_status' => 'active'") !== false) {
    throw new RuntimeException('CRM workspace write-back must not hardcode active domain status.');
}

echo "M132DomainContractHardeningTest PASS\n";
