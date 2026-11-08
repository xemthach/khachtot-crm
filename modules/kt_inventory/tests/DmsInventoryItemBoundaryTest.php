<?php defined('BASEPATH') or define('BASEPATH', __DIR__);

$source = file_get_contents(dirname(__DIR__) . '/kt_inventory.php');

foreach ([
    "table_exists(db_prefix() . 'items')",
    "table_exists(db_prefix() . 'kt_inventory_items')",
    "get(db_prefix() . 'items')",
    'core item is absent from the active database context',
    'ignored after non-authoritative inventory failure',
] as $needle) {
    if (strpos($source, $needle) === false) {
        throw new RuntimeException('Missing inventory database boundary guard: ' . $needle);
    }
}

echo "DmsInventoryItemBoundaryTest passed.\n";
