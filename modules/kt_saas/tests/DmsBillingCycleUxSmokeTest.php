<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

$modelSource = file_get_contents(dirname(__DIR__) . '/models/Kt_saas_model.php');
$controllerSource = file_get_contents(dirname(__DIR__) . '/controllers/Kt_saas.php');
$viewSource = file_get_contents(dirname(__DIR__) . '/views/tenant/marketplace_app.php');

if ($modelSource === false || $controllerSource === false || $viewSource === false) {
    throw new RuntimeException('Unable to read DMS billing cycle UX sources.');
}

if (strpos($viewSource, 'type="hidden" name="billing_cycle" value="monthly"') !== false) {
    throw new RuntimeException('Marketplace app view still hardcodes a hidden monthly billing cycle.');
}

foreach ([
    'name="billing_cycle"',
    'Chọn gói DMS phù hợp',
    'Chi tiết kỹ thuật',
    'current_billing_cycle',
    'get_dms_addon_billing_cycle_options',
    'quarterly_price',
] as $needle) {
    if (strpos($modelSource . $controllerSource . $viewSource, $needle) === false) {
        throw new RuntimeException('Expected billing cycle UX marker not found: ' . $needle);
    }
}

if (strpos($modelSource, "Chu kỳ thanh toán không được phép") === false) {
    throw new RuntimeException('Unavailable billing cycles must be rejected server-side.');
}

echo "DmsBillingCycleUxSmokeTest passed.\n";
