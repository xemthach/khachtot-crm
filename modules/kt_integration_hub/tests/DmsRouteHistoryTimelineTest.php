<?php

$salesrepRoot = dirname(__DIR__, 4) . '/salesrep';
$controller = file_get_contents($salesrepRoot . '/app/Http/Controllers/LiveTrackingController.php');
$routes = file_get_contents($salesrepRoot . '/routes/web.php');
$view = file_get_contents($salesrepRoot . '/resources/views/live-tracking/index.blade.php');
$timeline = file_get_contents($salesrepRoot . '/resources/views/live-tracking/timeline.blade.php');
$api = file_get_contents($salesrepRoot . '/app/Http/Controllers/Api/LiveLocationController.php');
$seeder = file_get_contents($salesrepRoot . '/database/seeders/DynamicPermissionSeeder.php');

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

$assertContains($routes, 'live-tracking.timeline', 'Salesrep must expose route history timeline route.');
$assertContains($controller, 'location_snapshot', 'Timeline must include live location snapshots.');
$assertContains($controller, 'field_feedback', 'Timeline must include feedback events.');
$assertContains($controller, 'captured_order', 'Timeline must distinguish captured order GPS events.');
$assertContains($view, 'Quyền riêng tư vị trí', 'Live map must show privacy/consent copy.');
$assertContains($view, 'Lưu lịch sử di chuyển', 'Live map must show route retention copy.');
$assertContains($timeline, 'Không có tọa độ cho sự kiện này', 'Timeline must degrade when event lacks GPS.');
$assertContains($api, 'TenantContext', 'Live location API must use the canonical tenant context.');
$assertContains($api, '$this->tenantContext->scope()', 'Live location API must fail closed through tenant scope resolution.');
foreach (['view_team_live_tracking', 'view_tenant_live_tracking', 'submit_live_location', 'view_route_history', 'view_team_route_history', 'view_tenant_route_history', 'view_map_activity'] as $permission) {
    $assertContains($seeder, $permission, "Seeder must define {$permission}.");
}
$assertNotContains($view, 'maps.googleapis.com', 'Live tracking view must not render a raw map provider key.');

echo "DmsRouteHistoryTimelineTest passed\n";
