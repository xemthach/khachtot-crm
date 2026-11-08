<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

$overview = file_get_contents(dirname(__DIR__) . '/views/admin/dms/overview.php');
$list = file_get_contents(dirname(__DIR__) . '/views/admin/dms/list.php');
$setupCenter = file_get_contents(dirname(__DIR__) . '/views/admin/dms/setup_center.php');
$controller = file_get_contents(dirname(__DIR__) . '/controllers/Kt_integration_hub.php');

if (strpos($overview, 'Tổng quan thị trường') === false) {
    throw new RuntimeException('Expected overview to render the Vietnamese market overview heading.');
}

if (strpos($overview, 'Nhân viên sẵn sàng') === false) {
    throw new RuntimeException('Expected overview to render readiness state.');
}

if (strpos($overview, 'Cần xử lý') === false) {
    throw new RuntimeException('Expected overview to render action guidance.');
}

if (substr_count($overview, 'Mở app DMS') < 1) {
    throw new RuntimeException('Expected overview to expose the localized DMS CTA.');
}

if (strpos($list, 'Mở DMS') === false) {
    throw new RuntimeException('Expected list views to expose the localized DMS CTA.');
}

if (strpos($list, 'Mở Thiết lập DMS') === false) {
    throw new RuntimeException('Expected list views to expose DMS Settings CTA in empty state.');
}

if (strpos($setupCenter, 'Thiết lập & Truy cập') === false) {
    throw new RuntimeException('Expected Setup Center heading to render.');
}

if (strpos($setupCenter, 'Đồng bộ nhân sự') === false) {
    throw new RuntimeException('Expected Setup Center to expose staff provisioning action.');
}

if (strpos($setupCenter, 'Mở DMS') === false) {
    throw new RuntimeException('Expected Setup Center to expose Open DMS action.');
}

foreach (['settings_json', 'bridge_secret', 'payload_json'] as $technicalField) {
    if (strpos($setupCenter, $technicalField) !== false) {
        throw new RuntimeException('Expected technical field to stay out of tenant Setup Center: ' . $technicalField);
    }
}

if (strpos($setupCenter, 'DMS CRM Preflight') === false) {
    throw new RuntimeException('Expected tenant-safe support boundary copy.');
}

echo "DmsProductizationUiSmokeTest passed.\n";
