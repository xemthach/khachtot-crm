<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

$view = file_get_contents(dirname(__DIR__) . '/views/admin/dms/setup_wizard.php');
$controller = file_get_contents(dirname(__DIR__) . '/controllers/Kt_integration_hub.php');
if ($view === false || $controller === false) {
    throw new RuntimeException('Unable to read R27 setup wizard sources.');
}

foreach ([
    'Thiết lập DMS cho đội sales thị trường',
    'Tiến độ thiết lập',
    'Tiếp tục thiết lập',
    'Chi tiết kỹ thuật',
    'Gói DMS',
    'Nhân sự & vai trò',
    'Điểm bán & phân công',
    'Sản phẩm & tồn kho',
    'Bản đồ & vị trí',
    'Đơn hàng, hóa đơn & thanh toán',
    'Đồng bộ & sức khỏe hệ thống',
] as $needle) {
    if (strpos($view . $controller, $needle) === false) {
        throw new RuntimeException('Missing R27 wizard marker: ' . $needle);
    }
}

if (substr_count($controller, "'index' =>") < 7) {
    throw new RuntimeException('R27 wizard must expose seven onboarding steps.');
}

echo "DmsSetupWizardR27SmokeTest passed.\n";
