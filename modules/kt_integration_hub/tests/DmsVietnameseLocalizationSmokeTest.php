<?php

$root = dirname(__DIR__);
$menu = file_get_contents($root . '/kt_integration_hub.php');

foreach ([
    'Tổng quan DMS',
    'Chuẩn bị DMS',
    'Nhân sự',
    'Điểm bán & sản phẩm',
    'Đồng bộ',
    'Mở DMS',
    'Gói & hạn mức',
    'Vận hành DMS',
    'Tổng quan vận hành DMS',
    'Tenant DMS',
    'Đơn kích hoạt',
    'Gói DMS',
    'Sức khỏe CRM ↔ DMS',
    'Nhật ký',
    'Cấu hình kết nối',
] as $label) {
    if (strpos($menu, $label) === false) {
        throw new RuntimeException('Missing approved DMS sidebar label: ' . $label);
    }
}

foreach ([
    'Workspace DMS',
    'Mua / thêm workspace',
    'Kích hoạt DMS',
    'DMS Operations',
    'Đội sales hôm nay',
    'Bản đồ hoạt động',
    'Đơn hàng & Hóa đơn',
] as $hiddenMenuLabel) {
    if (strpos($menu, $hiddenMenuLabel) !== false) {
        throw new RuntimeException('Retired DMS label remains in sidebar menu: ' . $hiddenMenuLabel);
    }
}

$viewHeadings = [
    'views/admin/dms/overview.php' => 'Tổng quan thị trường',
    'views/admin/dms/sales_team_today.php' => 'Đội sales hôm nay',
    'views/admin/dms/map_activity.php' => 'Bản đồ hoạt động',
    'views/admin/dms/field_activity.php' => 'Check-in / Lượt ghé',
    'views/admin/dms/orders_invoices.php' => 'Đơn hàng DMS / Hóa đơn / Thanh toán',
    'views/admin/dms/store_products.php' => 'Điểm bán & Sản phẩm',
    'views/admin/dms/setup_center.php' => 'Thiết lập & Truy cập',
    'views/admin/dms/sync_health.php' => 'Đồng bộ & Sức khỏe',
];

foreach ($viewHeadings as $file => $heading) {
    $content = file_get_contents($root . '/' . $file);
    if (strpos($content, $heading) === false) {
        throw new RuntimeException('Missing localized heading "' . $heading . '" in ' . $file);
    }
}

foreach ([
    'Tong quan thi truong',
    'Doi sales hom nay',
    'Ban do hoat dong',
    'Diem ban & San pham',
    'Don hang DMS / Hoa don / Thanh toan',
] as $legacyLabel) {
    if (strpos($menu, $legacyLabel) !== false) {
        throw new RuntimeException('Legacy unaccented menu label remains: ' . $legacyLabel);
    }
}

echo "DmsVietnameseLocalizationSmokeTest passed.\n";
