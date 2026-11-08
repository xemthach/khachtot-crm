<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

$controllerSource = file_get_contents(dirname(__DIR__) . '/controllers/Kt_saas.php');
$viewSource = file_get_contents(dirname(__DIR__) . '/views/tenant/marketplace.php');

if ($controllerSource === false || strpos($controllerSource, 'tenant_request_marketplace_service') === false) {
    throw new RuntimeException('Missing tenant_request_marketplace_service action.');
}

$controllerExpectations = [
    'domain-service',
    'hosting-service',
    'website-setup-service',
    'marketplace.service_request_created',
    "admin_url('kt_matbao_invoice/tenant/addons')",
];

foreach ($controllerExpectations as $needle) {
    if (strpos($controllerSource, $needle) === false) {
        throw new RuntimeException('Marketplace manual add-on controller logic is missing expected content: ' . $needle);
    }
}

$viewExpectations = [
    'Requestable Services',
    "\$itemPrimary['fields']",
    'tw-inline-block',
    'Catalog note',
];

foreach ($viewExpectations as $needle) {
    if ($viewSource === false || strpos($viewSource, $needle) === false) {
        throw new RuntimeException('Marketplace view is missing expected manual add-on content: ' . $needle);
    }
}

echo "MarketplaceManualAddonFlowSmokeTest passed.\n";
