<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

$controller = file_get_contents(dirname(__DIR__) . '/controllers/Kt_saas.php');
$marketplace = file_get_contents(dirname(__DIR__) . '/views/tenant/marketplace.php');
$app = file_get_contents(dirname(__DIR__) . '/views/tenant/marketplace_app.php');
$installation = file_get_contents(dirname(__DIR__) . '/views/tenant/marketplace_provisioning.php');
$setupController = file_get_contents(dirname(__DIR__, 2) . '/kt_integration_hub/controllers/Kt_integration_hub.php');
$setupView = file_get_contents(dirname(__DIR__, 2) . '/kt_integration_hub/views/admin/dms/setup_center.php');

foreach ([$controller, $marketplace, $app, $installation, $setupController, $setupView] as $source) {
    if ($source === false) {
        throw new RuntimeException('A source-of-truth surface could not be read.');
    }
}

$required = [
    'CRM subscription',
    'Marketplace',
    'Setup Center',
    'Licensing',
    'Open DMS',
    'paid DMS add-on',
    'installation lifecycle',
    'operational readiness',
    'Runtime licensing',
    'Bridge Health',
];

$combined = implode("\n", [$controller, $marketplace, $app, $installation, $setupController, $setupView]);
foreach ($required as $needle) {
    if (strpos($combined, $needle) === false) {
        throw new RuntimeException('Missing source-of-truth vocabulary: ' . $needle);
    }
}

foreach (['Commercial Eligibility', 'CRM Plan grants', 'grants DMS access', 'operational activation', 'Licensing Status'] as $conflictedCopy) {
    if (strpos($combined, $conflictedCopy) !== false) {
        throw new RuntimeException('Conflicted source-of-truth copy remains: ' . $conflictedCopy);
    }
}

echo "DmsSourceOfTruthCopySmokeTest passed.\n";
