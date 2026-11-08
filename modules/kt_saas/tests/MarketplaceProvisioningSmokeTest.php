<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

$controllerSource = file_get_contents(dirname(__DIR__) . '/controllers/Kt_saas.php');
$viewSource = file_get_contents(dirname(__DIR__) . '/views/tenant/marketplace_provisioning.php');
$detailSource = file_get_contents(dirname(__DIR__) . '/views/tenant/marketplace_app.php');

if ($controllerSource === false || strpos($controllerSource, 'tenant_install_salesflow_dms') === false) {
    throw new RuntimeException('Missing install action for Khach Tot DMS.');
}

if ($controllerSource === false || strpos($controllerSource, 'tenant_marketplace_provisioning') === false) {
    throw new RuntimeException('Missing provisioning screen controller method.');
}

if ($viewSource === false) {
    throw new RuntimeException('Provisioning view could not be read.');
}

$viewExpectations = [
    'Khach Tot DMS Installation Progress',
    'Bridge Health',
    'Marketplace Installation',
    'Staff Provisioning',
    'Setup Center Readiness',
    'Installation & Readiness Signals',
    'Install History',
];

foreach ($viewExpectations as $needle) {
    if (strpos($viewSource, $needle) === false) {
        throw new RuntimeException('Provisioning view is missing expected content: ' . $needle);
    }
}

$detailExpectations = [
    'Gói DMS hiện tại',
    'Workspace DMS',
    'Chi tiết kỹ thuật',
];

foreach ($detailExpectations as $needle) {
    if ($detailSource === false || strpos($detailSource, $needle) === false) {
        throw new RuntimeException('Marketplace detail view is missing expected M3 state experience: ' . $needle);
    }
}

echo "MarketplaceProvisioningSmokeTest passed.\n";
