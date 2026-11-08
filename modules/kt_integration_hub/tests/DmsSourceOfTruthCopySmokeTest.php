<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

$root = dirname(__DIR__);

$files = [
    'overview' => $root . '/views/admin/dms/overview.php',
    'orders_invoices' => $root . '/views/admin/dms/orders_invoices.php',
    'map_activity' => $root . '/views/admin/dms/map_activity.php',
    'store_products' => $root . '/views/admin/dms/store_products.php',
];

foreach ($files as $name => $file) {
    if (!is_file($file)) {
        throw new RuntimeException('Missing DMS business view: ' . $name);
    }
}

$overview = file_get_contents($files['overview']);
$orders = file_get_contents($files['orders_invoices']);
$map = file_get_contents($files['map_activity']);
$storeProducts = file_get_contents($files['store_products']);

foreach ([
    'overview business owner' => [$overview, 'CRM'],
    'orders business owner' => [$orders, 'CRM'],
    'orders source label' => [$orders, 'Ngu'],
    'map order context' => [$map, 'GPS'],
    'product master contract' => [$storeProducts, 'tblitems'],
    'inventory mapping contract' => [$storeProducts, 'DMS'],
    'store commercial context' => [$storeProducts, 'CRM'],
] as $label => [$content, $needle]) {
    if (strpos($content, $needle) === false) {
        throw new RuntimeException('Missing source-of-truth copy: ' . $label);
    }
}

foreach ([
    'overview' => $overview,
    'orders_invoices' => $orders,
    'map_activity' => $map,
    'store_products' => $storeProducts,
] as $name => $content) {
    foreach (['tbldms_', 'dms_order_bridge', 'dms_checkin_events', 'dms_visit_snapshots', 'bridge payload', 'signed headers', 'bridge_secret'] as $technicalLabel) {
        if (stripos($content, $technicalLabel) !== false) {
            throw new RuntimeException('Raw technical label leaked in ' . $name . ': ' . $technicalLabel);
        }
    }
}

echo "DmsSourceOfTruthCopySmokeTest passed.\n";
