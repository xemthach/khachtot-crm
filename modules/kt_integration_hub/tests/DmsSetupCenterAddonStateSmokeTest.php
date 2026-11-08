<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

$controllerSource = file_get_contents(dirname(__DIR__) . '/controllers/Kt_integration_hub.php');
$modelSource = file_get_contents(dirname(__DIR__) . '/models/Kt_integration_model.php');

if ($controllerSource === false || $modelSource === false) {
    throw new RuntimeException('Unable to read DMS setup center sources.');
}

foreach ([
    'DMS là dịch vụ mua thêm',
    'Gói DMS đang hoạt động cho tenant này',
    'DMS_SUBSCRIPTION_STATUS',
    'Gói DMS đã được chọn nhưng đang chờ thanh toán.',
] as $needle) {
    if (strpos($controllerSource . $modelSource, $needle) === false) {
        throw new RuntimeException('Expected setup-center add-on state marker not found: ' . $needle);
    }
}

echo "DmsSetupCenterAddonStateSmokeTest passed.\n";
