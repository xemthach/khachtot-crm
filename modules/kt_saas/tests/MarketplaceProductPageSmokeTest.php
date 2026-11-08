<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

$catalogView = file_get_contents(dirname(__DIR__) . '/views/tenant/marketplace.php');
$detailView = file_get_contents(dirname(__DIR__) . '/views/tenant/marketplace_app.php');

if ($catalogView === false) {
    throw new RuntimeException('Marketplace catalog view could not be read.');
}

if ($detailView === false) {
    throw new RuntimeException('Marketplace detail view could not be read.');
}

$catalogExpectations = [
    'Khach Tot DMS',
    'View Product Page',
    'Marketplace Installation',
    'Commercial Access',
    'Operational Readiness',
];

foreach ($catalogExpectations as $needle) {
    if (strpos($catalogView, $needle) === false) {
        throw new RuntimeException('Marketplace catalog view is missing expected content: ' . $needle);
    }
}

$detailExpectations = [
    'Gói DMS cho đội sales thị trường',
    'Chọn gói DMS phù hợp',
    'Nhân sự',
    'Điểm bán',
    'Theo dõi trực tiếp',
    'Chi tiết kỹ thuật',
    'Nhật ký cài đặt',
];

foreach ($detailExpectations as $needle) {
    if (strpos($detailView, $needle) === false) {
        throw new RuntimeException('Marketplace detail view is missing expected content: ' . $needle);
    }
}

if (strpos($detailView, 'Preview Coming Soon') !== false) {
    throw new RuntimeException('Marketplace detail view must not expose an internal preview placeholder.');
}

echo "MarketplaceProductPageSmokeTest passed.\n";
