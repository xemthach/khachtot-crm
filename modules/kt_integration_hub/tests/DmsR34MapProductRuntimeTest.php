<?php

$root = dirname(__DIR__);
$api = file_get_contents($root . '/controllers/Kt_integration_api.php');
$routes = file_get_contents(dirname($root, 2) . '/application/config/my_routes.php');
$productProducer = file_get_contents($root . '/libraries/ProductPayloadProducer.php');
$orderBridge = file_get_contents($root . '/libraries/DmsOrderBridgeProcessor.php');

foreach ([
    'api/v1/integration/map/browser-loader',
    'map_browser_loader',
    'validateSalesflowRequest',
    'maps.googleapis.com/maps/api/js?key=',
] as $contract) {
    if (strpos($api . $routes, $contract) === false) {
        throw new RuntimeException('Missing R34 map loader contract: ' . $contract);
    }
}

foreach (['crm_product_id', 'description', 'base_price'] as $field) {
    if (strpos($productProducer, $field) === false) {
        throw new RuntimeException('Missing CRM product field contract: ' . $field);
    }
}

if (strpos($orderBridge, 'itemable') !== false) {
    throw new RuntimeException('DMS order bridge must not mutate historical invoice item snapshots directly.');
}

echo "DmsR34MapProductRuntimeTest passed.\n";
