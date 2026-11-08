<?php

$root = dirname(__DIR__);
$processor = file_get_contents($root . '/libraries/DmsVisitBridgeProcessor.php');
$model = file_get_contents($root . '/models/Kt_integration_model.php');
$salesrepVisitBridge = file_get_contents(dirname($root, 3) . '/salesrep/app/Services/Integration/VisitBridgeService.php');
$salesrepCheckoutService = file_get_contents(dirname($root, 3) . '/salesrep/app/Services/CheckOutService.php');

$assertContains = function ($haystack, $needle, $message) {
    if (strpos($haystack, $needle) === false) {
        throw new RuntimeException($message);
    }
};

$assertContains($model, 'upsert_dms_location_projection_marker', 'CRM model must expose generic location projection upsert.');
$assertContains($processor, 'upsertLocationMarkers', 'Visit bridge processor must upsert checkout/visit location markers.');
$assertContains($processor, "'marker_type' => 'checkout'", 'Visit bridge processor must write checkout marker type.');
$assertContains($processor, "'marker_type' => 'visit'", 'Visit bridge processor must write visit marker type.');
$assertContains($processor, 'is_numeric($visit[\'checkout_latitude\']', 'Checkout marker must require real checkout latitude.');
$assertContains($processor, 'is_numeric($visit[\'visit_latitude\']', 'Visit marker must require real visit latitude.');
$assertContains($salesrepVisitBridge, "'checkout_latitude'", 'Salesrep visit payload must include checkout latitude when available.');
$assertContains($salesrepVisitBridge, "'visit_latitude'", 'Salesrep visit payload must include visit latitude when available.');
$assertContains($salesrepCheckoutService, "SyncVisitToCrmJob::dispatch", 'Checkout creation must queue visit bridge sync.');

echo "DmsCheckoutVisitProjectionTest passed\n";
