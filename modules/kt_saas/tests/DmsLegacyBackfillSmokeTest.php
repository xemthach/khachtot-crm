<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

$modelSource = file_get_contents(dirname(__DIR__) . '/models/Kt_saas_model.php');
$licensingSource = file_get_contents(dirname(__DIR__) . '/services/LicensingService.php');

if ($modelSource === false || $licensingSource === false) {
    throw new RuntimeException('Unable to read DMS legacy backfill sources.');
}

foreach ([
    'get_legacy_dms_tenant_inventory',
    'backfill_legacy_dms_addon_subscriptions',
    'legacy_shim',
    'legacy-migration-',
    'resolve_legacy_dms_package_code',
] as $needle) {
    if (strpos($modelSource . $licensingSource, $needle) === false) {
        throw new RuntimeException('Expected DMS legacy backfill marker not found: ' . $needle);
    }
}

echo "DmsLegacyBackfillSmokeTest passed.\n";
