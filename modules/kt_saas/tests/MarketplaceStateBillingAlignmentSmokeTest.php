<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

$catalogView = file_get_contents(dirname(__DIR__) . '/views/tenant/marketplace.php');
$detailView = file_get_contents(dirname(__DIR__) . '/views/tenant/marketplace_app.php');
$provisioningView = file_get_contents(dirname(__DIR__) . '/views/tenant/marketplace_provisioning.php');
$controllerSource = file_get_contents(dirname(__DIR__) . '/controllers/Kt_saas.php');

foreach ([$catalogView, $detailView, $provisioningView, $controllerSource] as $source) {
    if ($source === false) {
        throw new RuntimeException('Marketplace state alignment source could not be read.');
    }
}

$catalogExpectations = [
    'How Marketplace state works',
    'Plan eligibility',
    'Billing',
    'Install lifecycle',
    'Runtime licensing',
    'Purchase required',
    'No Marketplace install flow',
    'Request required',
];

foreach ($catalogExpectations as $needle) {
    if (strpos($catalogView, $needle) === false && strpos($controllerSource, $needle) === false) {
        throw new RuntimeException('Missing marketplace state/billing alignment evidence: ' . $needle);
    }
}

$detailExpectations = [
    'Gói DMS hiện tại',
    'Chu kỳ thanh toán',
    'Chi tiết kỹ thuật',
    'Thông tin workspace',
    'Nhật ký cài đặt',
];

foreach ($detailExpectations as $needle) {
    if (strpos($detailView, $needle) === false && strpos($controllerSource, $needle) === false) {
        throw new RuntimeException('Marketplace detail view is missing ownership evidence: ' . $needle);
    }
}

$provisioningExpectations = [
    'State alignment',
    'Lifecycle Ownership',
    'installation, and readiness',
];

foreach ($provisioningExpectations as $needle) {
    if (strpos($provisioningView, $needle) === false) {
        throw new RuntimeException('Provisioning state alignment is missing expected copy: ' . $needle);
    }
}

echo "MarketplaceStateBillingAlignmentSmokeTest passed.\n";
