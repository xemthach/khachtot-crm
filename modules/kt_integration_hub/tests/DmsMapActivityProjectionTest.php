<?php

$root = dirname(__DIR__);
$model = file_get_contents($root . '/models/Kt_integration_model.php');
$view = file_get_contents($root . '/views/admin/dms/map_activity.php');
$controller = file_get_contents($root . '/controllers/Kt_integration_hub.php');

$assertContains = function (string $haystack, string $needle, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assertNotContains = function (string $haystack, string $needle, string $message): void {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

foreach (['staff', 'checkin', 'store', 'order_store', 'captured_order', 'checkout', 'visit', 'field_feedback'] as $markerType) {
    $assertContains($view, $markerType, "Map activity view must expose marker type {$markerType}.");
}

$assertContains($model, 'get_dms_projected_locations', 'Model must read generic location projection markers.');
$assertContains($model, 'get_dms_field_feedback_locations', 'Model must expose feedback markers.');
$assertContains($view, 'Vị trí gửi đơn hàng', 'View must label captured order GPS honestly.');
$assertContains($view, 'Điểm bán của đơn hàng', 'View must label order-store coordinates honestly.');
$assertContains($controller, 'Vị trí gửi đơn hàng', 'Sync Health must include captured order GPS readiness.');
$assertContains($controller, 'Lịch sử di chuyển', 'Sync Health must include route history readiness.');
$assertNotContains($view, 'Google Maps API Key', 'CRM map must not depend on tenant-facing Google key error.');

echo "DmsMapActivityProjectionTest passed\n";
