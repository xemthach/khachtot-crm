<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: KT Inventory
Description: Internal inventory management module for warehouses, stock balances, receipts, issues, adjustments, transfers and reports.
Version: 1.0.0
Requires at least: 3.0.*
Author: Internal Engineering
*/

define('KT_INVENTORY_MODULE', 'kt_inventory');
define('KT_INVENTORY_ASSETS_URL', module_dir_url(KT_INVENTORY_MODULE, 'assets/'));

hooks()->add_action('admin_init', 'kt_inventory_module_init');
hooks()->add_action('app_admin_head', 'kt_inventory_admin_head_assets');
hooks()->add_action('app_admin_footer', 'kt_inventory_admin_footer_assets');

register_activation_hook(KT_INVENTORY_MODULE, 'kt_inventory_module_activation_hook');
register_uninstall_hook(KT_INVENTORY_MODULE, 'kt_inventory_module_uninstall_hook');
register_language_files(KT_INVENTORY_MODULE, [KT_INVENTORY_MODULE]);

function kt_inventory_module_init()
{
    $CI = &get_instance();
    $CI->load->helper(KT_INVENTORY_MODULE . '/kt_inventory');

    kt_inventory_register_staff_capabilities();
    kt_inventory_register_menu_items();
}

function kt_inventory_module_activation_hook()
{
    require_once __DIR__ . '/install.php';
}

function kt_inventory_module_uninstall_hook()
{
    require_once __DIR__ . '/uninstall.php';
}

function kt_inventory_register_staff_capabilities()
{
    register_staff_capabilities(
        KT_INVENTORY_MODULE,
        [
            'capabilities' => [
                'kt_inventory_view'              => _l('kt_inventory_permission_view'),
                'kt_inventory_manage_warehouses' => _l('kt_inventory_permission_manage_warehouses'),
                'kt_inventory_manage_items'      => _l('kt_inventory_permission_manage_items'),
                'kt_inventory_goods_receipt'     => _l('kt_inventory_permission_goods_receipt'),
                'kt_inventory_goods_issue'       => _l('kt_inventory_permission_goods_issue'),
                'kt_inventory_adjustment'        => _l('kt_inventory_permission_adjustment'),
                'kt_inventory_transfer'          => _l('kt_inventory_permission_transfer'),
                'kt_inventory_reports'           => _l('kt_inventory_permission_reports'),
                'kt_inventory_settings'          => _l('kt_inventory_permission_settings'),
            ],
        ],
        _l('kt_inventory')
    );
}

function kt_inventory_register_menu_items()
{
    $CI = &get_instance();

    if (!kt_inventory_user_can_access_module()) {
        return;
    }

    $CI->app_menu->add_sidebar_menu_item('kt_inventory', [
        'slug'     => 'kt_inventory',
        'name'     => _l('kt_inventory'),
        'icon'     => 'fa fa-cubes',
        'collapse' => true,
        'position' => 30,
    ]);

    $items = [
        ['slug' => 'kt_inventory_dashboard', 'name' => _l('kt_inventory_dashboard'), 'href' => admin_url('kt_inventory')],
        ['slug' => 'kt_inventory_warehouses', 'name' => _l('kt_inventory_warehouses'), 'href' => admin_url('kt_inventory/warehouses'), 'cap' => 'kt_inventory_manage_warehouses'],
        ['slug' => 'kt_inventory_items', 'name' => _l('kt_inventory_items'), 'href' => admin_url('kt_inventory/items'), 'cap' => 'kt_inventory_manage_items'],
        ['slug' => 'kt_inventory_batches', 'name' => _l('kt_inventory_batches'), 'href' => admin_url('kt_inventory/batches'), 'cap' => 'kt_inventory_view'],
        ['slug' => 'kt_inventory_balances', 'name' => _l('kt_inventory_stock_balance'), 'href' => admin_url('kt_inventory/stock_balance'), 'cap' => 'kt_inventory_view'],
        ['slug' => 'kt_inventory_reservations', 'name' => _l('kt_inventory_reservations'), 'href' => admin_url('kt_inventory/reservations'), 'cap' => 'kt_inventory_goods_issue'],
        ['slug' => 'kt_inventory_receipts', 'name' => _l('kt_inventory_goods_receipts'), 'href' => admin_url('kt_inventory/goods_receipts'), 'cap' => 'kt_inventory_goods_receipt'],
        ['slug' => 'kt_inventory_issues', 'name' => _l('kt_inventory_goods_issues'), 'href' => admin_url('kt_inventory/goods_issues'), 'cap' => 'kt_inventory_goods_issue'],
        ['slug' => 'kt_inventory_adjustments', 'name' => _l('kt_inventory_stock_adjustments'), 'href' => admin_url('kt_inventory/stock_adjustments'), 'cap' => 'kt_inventory_adjustment'],
        ['slug' => 'kt_inventory_transfers', 'name' => _l('kt_inventory_stock_transfers'), 'href' => admin_url('kt_inventory/stock_transfers'), 'cap' => 'kt_inventory_transfer'],
        ['slug' => 'kt_inventory_transactions', 'name' => _l('kt_inventory_transactions'), 'href' => admin_url('kt_inventory/transactions'), 'cap' => 'kt_inventory_view'],
        ['slug' => 'kt_inventory_reports', 'name' => _l('kt_inventory_reports'), 'href' => admin_url('kt_inventory/reports'), 'cap' => 'kt_inventory_reports'],
        ['slug' => 'kt_inventory_settings', 'name' => _l('kt_inventory_settings'), 'href' => admin_url('kt_inventory/settings'), 'cap' => 'kt_inventory_settings'],
    ];

    $position = 1;
    foreach ($items as $item) {
        if (isset($item['cap']) && !kt_inventory_staff_can($item['cap'])) {
            continue;
        }

        $item['position'] = $position++;
        $CI->app_menu->add_sidebar_children_item('kt_inventory', $item);
    }
}

function kt_inventory_admin_head_assets()
{
    if (!kt_inventory_is_module_request()) {
        return;
    }

    echo '<link href="' . KT_INVENTORY_ASSETS_URL . 'css/kt_inventory.css?v=1.0.1" rel="stylesheet" type="text/css" />';
}

function kt_inventory_admin_footer_assets()
{
    if (!kt_inventory_is_module_request()) {
        return;
    }

    echo '<script src="' . KT_INVENTORY_ASSETS_URL . 'js/kt_inventory.js?v=1.0.5"></script>';
}

hooks()->add_action('item_created', 'kt_inventory_sync_on_item_created');
hooks()->add_action('after_invoice_added', 'kt_inventory_invoice_created_or_updated');
hooks()->add_action('after_invoice_updated', 'kt_inventory_invoice_created_or_updated');
hooks()->add_action('invoice_marked_as_cancelled', 'kt_inventory_invoice_cancelled');
hooks()->add_action('before_invoice_deleted', 'kt_inventory_invoice_deleted');

function kt_inventory_invoice_created_or_updated($id)
{
    $CI = &get_instance();
    $CI->load->model(KT_INVENTORY_MODULE . '/kt_inventory_model');
    $CI->kt_inventory_model->auto_reserve_from_invoice($id);
}

function kt_inventory_invoice_cancelled($id)
{
    $CI = &get_instance();
    $CI->load->model(KT_INVENTORY_MODULE . '/kt_inventory_model');
    $CI->kt_inventory_model->clear_invoice_reservations($id);
}

function kt_inventory_invoice_deleted($id)
{
    $CI = &get_instance();
    $CI->load->model(KT_INVENTORY_MODULE . '/kt_inventory_model');
    $CI->kt_inventory_model->clear_invoice_reservations($id);
}

function kt_inventory_sync_on_item_created($id)
{
    $CI = &get_instance();
    
    // Check if the item already has an inventory master record
    $exists = $CI->db->where('item_id', $id)->get(db_prefix() . 'kt_inventory_items')->row_array();
    if ($exists) {
        return;
    }

    $sku = 'ITEM-' . $id;
    // Ensure SKU is unique
    $count = 1;
    while ($CI->db->where('sku', $sku)->count_all_results(db_prefix() . 'kt_inventory_items') > 0) {
        $sku = 'ITEM-' . $id . '-' . $count;
        $count++;
    }

    $CI->db->insert(db_prefix() . 'kt_inventory_items', [
        'item_id'      => $id,
        'sku'          => $sku,
        'track_lot'    => 0,
        'track_serial' => 0,
        'min_stock'    => 0,
        'max_stock'    => 0,
        'is_active'    => 1,
        'created_at'   => date('Y-m-d H:i:s'),
        'updated_at'   => date('Y-m-d H:i:s'),
    ]);

    $inventory_item_id = $CI->db->insert_id();

    // Check if core item has barcode to sync
    if (!class_exists('Invoice_items_model', false)) {
        $CI->load->model('invoice_items_model');
    }
    $core_item = $CI->invoice_items_model->get($id);
    if ($core_item && !empty($core_item->commodity_barcode)) {
        $barcode = trim((string) $core_item->commodity_barcode);
        if ($barcode !== '') {
            $CI->load->model(KT_INVENTORY_MODULE . '/kt_inventory_model');
            $CI->kt_inventory_model->add_item_barcode([
                'inventory_item_id' => $inventory_item_id,
                'barcode'           => $barcode,
                'barcode_type'      => 'internal',
                'unit_type'         => trim((string) ($core_item->unit ?? '')),
                'package_quantity'  => 1,
                'is_primary'        => 1,
                'source'            => 'manual',
                'is_active'         => 1,
            ]);
        }
    }
}
