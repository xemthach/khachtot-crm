<?php

$root = dirname(__DIR__, 3);
$salesrep = dirname($root) . '/salesrep';
$failures = [];
$assertContains = static function (string $source, string $needle, string $message) use (&$failures): void {
    if (strpos($source, $needle) === false) {
        $failures[] = $message;
    }
};

$controller = file_get_contents($salesrep . '/app/Http/Controllers/LiveTrackingController.php');
$view = file_get_contents($salesrep . '/resources/views/live-tracking/index.blade.php');
$bridge = file_get_contents($salesrep . '/app/Services/Integration/LiveTrackingBridgeService.php');
$processor = file_get_contents(__DIR__ . '/../libraries/DmsLiveTrackingBridgeProcessor.php');
$map = file_get_contents(__DIR__ . '/../views/admin/dms/map_activity.php');
$module = file_get_contents(__DIR__ . '/../kt_integration_hub.php');

$assertContains($controller, "'source' => 'web'", 'Web location must be labelled with a web source.');
$assertContains($controller, 'SyncLiveTrackingToCrmJob::dispatch', 'Web capture must queue CRM projection.');
$assertContains($view, 'navigator.geolocation.getCurrentPosition', 'Browser geolocation must be requested explicitly.');
$assertContains($view, "title: 'Vị trí của tôi'", 'Current-user marker must be rendered.');
$assertContains($bridge, 'getCachedTenantContext($tenantId, $companyId)', 'Worker bridge must resolve the exact tenant cache.');
$assertContains($bridge, "withBody((string) \$rawBody, 'application/json')", 'Signed bridge must send the exact signed body.');
$assertContains($processor, 'dms_live_tracking_snapshots', 'CRM must persist a live-tracking projection.');
$assertContains($map, 'Vị trí nhân viên gần nhất', 'CRM map must expose projected staff locations.');
$assertContains($module, "defined('KT_INTEGRATION_HUB_MODULE') || define", 'Module constants must not leak duplicate-definition warnings.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "DmsR35WebLocationProjectionTest PASS\n";
