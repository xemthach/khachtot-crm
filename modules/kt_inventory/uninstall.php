<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

$tables = [
    'kt_stock_transfer_items',
    'kt_stock_transfers',
    'kt_stock_adjustment_items',
    'kt_stock_adjustments',
    'kt_goods_issue_items',
    'kt_goods_issues',
    'kt_goods_receipt_items',
    'kt_goods_receipts',
    'kt_item_barcodes',
    'kt_stock_reservations',
    'kt_stock_transactions',
    'kt_stock_balances',
    'kt_inventory_items',
    'kt_warehouses',
];

foreach ($tables as $table) {
    if ($CI->db->table_exists(db_prefix() . $table)) {
        $CI->db->query('DROP TABLE `' . db_prefix() . $table . '`');
    }
}

$options = [
    'kt_inventory_allow_negative_stock',
    'kt_inventory_default_warehouse_id',
    'kt_inventory_low_stock_notification_enabled',
    'kt_inventory_code_prefix_receipt',
    'kt_inventory_code_prefix_issue',
    'kt_inventory_code_prefix_adjustment',
    'kt_inventory_code_prefix_transfer',
    'kt_inventory_internal_barcode_prefix',
    'kt_inventory_internal_barcode_type',
    'kt_inventory_next_barcode_number',
];

foreach ($options as $option) {
    delete_option($option);
}
