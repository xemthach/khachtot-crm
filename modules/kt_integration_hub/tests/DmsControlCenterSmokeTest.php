<?php

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/controllers/Kt_integration_hub.php');
$menu = file_get_contents($root . '/kt_integration_hub.php');
$view = file_get_contents($root . '/views/admin/dms_landlord/control_center.php');

foreach (['dms_control_center', 'dms_control_center_save'] as $method) {
    if (strpos($controller, 'function ' . $method) === false) {
        throw new RuntimeException('Thiếu action control center: ' . $method);
    }
}
foreach (['requireLandlord', 'requireCapability', 'requirePostRequest'] as $guard) {
    if (strpos($controller, $guard) === false) {
        throw new RuntimeException('Thiếu landlord guard: ' . $guard);
    }
}
foreach (['provider_safe_mirror', 'platform_provider_url', 'Mở cấu hình nền tảng', 'Cấu hình nhà cung cấp được quản lý tại Salesrep DMS Platform', 'Chưa có dữ liệu kiểm tra từ Salesrep Platform'] as $needle) {
    if (strpos($view . $controller, $needle) === false) {
        throw new RuntimeException('Thiếu provider source-of-truth guidance: ' . $needle);
    }
}
foreach (['name="map_api_key"', 'name="browser_key"', 'dms_control_center_save'] as $forbidden) {
    if (stripos($view, $forbidden) !== false) {
        throw new RuntimeException('Control center không được render form provider: ' . $forbidden);
    }
}
foreach (['webhook_secret', 'bridge_secret', 'raw entitlement payload'] as $forbidden) {
    if (stripos($view, $forbidden) !== false) {
        throw new RuntimeException('Control center làm lộ dữ liệu nội bộ: ' . $forbidden);
    }
}

echo "DmsControlCenterSmokeTest passed.\n";
