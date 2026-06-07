<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
$charset = $CI->db->char_set;

if (!$CI->db->table_exists(db_prefix() . 'kt_warehouses')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "kt_warehouses` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `warehouse_code` VARCHAR(50) NOT NULL,
        `warehouse_name` VARCHAR(191) NOT NULL,
        `address` TEXT NULL,
        `manager_staff_id` INT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `warehouse_code_unique` (`warehouse_code`),
        KEY `manager_staff_id_idx` (`manager_staff_id`),
        KEY `is_active_idx` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'kt_inventory_items')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "kt_inventory_items` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `item_id` INT NOT NULL UNIQUE,
        `sku` VARCHAR(80) NOT NULL,
        `barcode` VARCHAR(120) NULL,
        `track_lot` TINYINT(1) NOT NULL DEFAULT 0,
        `track_serial` TINYINT(1) NOT NULL DEFAULT 0,
        `min_stock` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `max_stock` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `sku_unique` (`sku`),
        CONSTRAINT `fk_kt_inventory_items_item_id` FOREIGN KEY (`item_id`) REFERENCES `" . db_prefix() . "items` (`id`) ON DELETE CASCADE,
        KEY `is_active_idx` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'kt_stock_balances')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "kt_stock_balances` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `warehouse_id` INT UNSIGNED NOT NULL,
        `inventory_item_id` INT UNSIGNED NOT NULL,
        `quantity` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `reserved_quantity` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `available_quantity` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `warehouse_item_unique` (`warehouse_id`,`inventory_item_id`),
        KEY `inventory_item_id_idx` (`inventory_item_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'kt_stock_transactions')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "kt_stock_transactions` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `transaction_type` VARCHAR(30) NOT NULL,
        `reference_type` VARCHAR(50) NOT NULL,
        `reference_id` INT UNSIGNED NOT NULL,
        `warehouse_id` INT UNSIGNED NOT NULL,
        `inventory_item_id` INT UNSIGNED NOT NULL,
        `quantity_before` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `quantity_change` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `quantity_after` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `note` TEXT NULL,
        `created_by` INT NOT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `warehouse_id_idx` (`warehouse_id`),
        KEY `inventory_item_id_idx` (`inventory_item_id`),
        KEY `transaction_type_idx` (`transaction_type`),
        KEY `reference_idx` (`reference_type`,`reference_id`),
        KEY `created_at_idx` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'kt_goods_receipts')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "kt_goods_receipts` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `receipt_code` VARCHAR(60) NOT NULL,
        `warehouse_id` INT UNSIGNED NOT NULL,
        `supplier_name` VARCHAR(191) NULL,
        `receipt_date` DATE NOT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
        `note` TEXT NULL,
        `created_by` INT NOT NULL,
        `created_at` DATETIME NOT NULL,
        `posted_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `receipt_code_unique` (`receipt_code`),
        KEY `warehouse_id_idx` (`warehouse_id`),
        KEY `status_idx` (`status`),
        KEY `receipt_date_idx` (`receipt_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'kt_goods_receipt_items')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "kt_goods_receipt_items` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `receipt_id` INT UNSIGNED NOT NULL,
        `inventory_item_id` INT UNSIGNED NOT NULL,
        `quantity` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `unit_cost` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `lot_number` VARCHAR(120) NULL,
        `serial_number` VARCHAR(191) NULL,
        `expiry_date` DATE NULL,
        `note` VARCHAR(500) NULL,
        PRIMARY KEY (`id`),
        KEY `receipt_id_idx` (`receipt_id`),
        KEY `inventory_item_id_idx` (`inventory_item_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'kt_goods_issues')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "kt_goods_issues` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `issue_code` VARCHAR(60) NOT NULL,
        `warehouse_id` INT UNSIGNED NOT NULL,
        `customer_id` INT NULL,
        `invoice_id` INT NULL,
        `issue_date` DATE NOT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
        `note` TEXT NULL,
        `created_by` INT NOT NULL,
        `created_at` DATETIME NOT NULL,
        `posted_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `issue_code_unique` (`issue_code`),
        KEY `warehouse_id_idx` (`warehouse_id`),
        KEY `customer_id_idx` (`customer_id`),
        KEY `invoice_id_idx` (`invoice_id`),
        KEY `status_idx` (`status`),
        KEY `issue_date_idx` (`issue_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'kt_goods_issue_items')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "kt_goods_issue_items` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `issue_id` INT UNSIGNED NOT NULL,
        `inventory_item_id` INT UNSIGNED NOT NULL,
        `quantity` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `lot_number` VARCHAR(120) NULL,
        `serial_number` VARCHAR(191) NULL,
        `expiry_date` DATE NULL,
        `note` VARCHAR(500) NULL,
        PRIMARY KEY (`id`),
        KEY `issue_id_idx` (`issue_id`),
        KEY `inventory_item_id_idx` (`inventory_item_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'kt_stock_adjustments')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "kt_stock_adjustments` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `adjustment_code` VARCHAR(60) NOT NULL,
        `warehouse_id` INT UNSIGNED NOT NULL,
        `adjustment_date` DATE NOT NULL,
        `reason` TEXT NOT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
        `created_by` INT NOT NULL,
        `created_at` DATETIME NOT NULL,
        `posted_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `adjustment_code_unique` (`adjustment_code`),
        KEY `warehouse_id_idx` (`warehouse_id`),
        KEY `status_idx` (`status`),
        KEY `adjustment_date_idx` (`adjustment_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'kt_stock_adjustment_items')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "kt_stock_adjustment_items` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `adjustment_id` INT UNSIGNED NOT NULL,
        `inventory_item_id` INT UNSIGNED NOT NULL,
        `old_quantity` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `new_quantity` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `difference_quantity` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `lot_number` VARCHAR(120) NULL,
        `serial_number` VARCHAR(191) NULL,
        `expiry_date` DATE NULL,
        `note` VARCHAR(500) NULL,
        PRIMARY KEY (`id`),
        KEY `adjustment_id_idx` (`adjustment_id`),
        KEY `inventory_item_id_idx` (`inventory_item_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'kt_stock_transfers')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "kt_stock_transfers` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `transfer_code` VARCHAR(60) NOT NULL,
        `from_warehouse_id` INT UNSIGNED NOT NULL,
        `to_warehouse_id` INT UNSIGNED NOT NULL,
        `transfer_date` DATE NOT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
        `note` TEXT NULL,
        `created_by` INT NOT NULL,
        `created_at` DATETIME NOT NULL,
        `posted_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `transfer_code_unique` (`transfer_code`),
        KEY `from_warehouse_id_idx` (`from_warehouse_id`),
        KEY `to_warehouse_id_idx` (`to_warehouse_id`),
        KEY `status_idx` (`status`),
        KEY `transfer_date_idx` (`transfer_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'kt_stock_transfer_items')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "kt_stock_transfer_items` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `transfer_id` INT UNSIGNED NOT NULL,
        `inventory_item_id` INT UNSIGNED NOT NULL,
        `quantity` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `lot_number` VARCHAR(120) NULL,
        `serial_number` VARCHAR(191) NULL,
        `expiry_date` DATE NULL,
        `note` VARCHAR(500) NULL,
        PRIMARY KEY (`id`),
        KEY `transfer_id_idx` (`transfer_id`),
        KEY `inventory_item_id_idx` (`inventory_item_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'kt_stock_reservations')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "kt_stock_reservations` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `warehouse_id` INT UNSIGNED NOT NULL,
        `inventory_item_id` INT UNSIGNED NOT NULL,
        `reference_type` VARCHAR(50) NULL,
        `reference_id` INT NULL,
        `customer_id` INT NULL,
        `invoice_id` INT NULL,
        `quantity` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `status` VARCHAR(20) NOT NULL DEFAULT 'active',
        `note` VARCHAR(500) NULL,
        `reserved_by` INT NOT NULL,
        `created_at` DATETIME NOT NULL,
        `released_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        KEY `warehouse_id_idx` (`warehouse_id`),
        KEY `inventory_item_id_idx` (`inventory_item_id`),
        KEY `reference_idx` (`reference_type`,`reference_id`),
        KEY `status_idx` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'kt_item_barcodes')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "kt_item_barcodes` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `inventory_item_id` INT UNSIGNED NOT NULL,
        `barcode` VARCHAR(191) NOT NULL,
        `barcode_type` VARCHAR(50) NOT NULL DEFAULT 'internal',
        `unit_type` VARCHAR(50) NULL,
        `package_quantity` DECIMAL(15,4) NOT NULL DEFAULT 1,
        `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
        `source` VARCHAR(50) NOT NULL DEFAULT 'manual',
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_by` INT NULL,
        `created_at` DATETIME NULL,
        `updated_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `barcode_unique` (`barcode`),
        KEY `inventory_item_id_idx` (`inventory_item_id`),
        KEY `barcode_type_idx` (`barcode_type`),
        KEY `is_primary_idx` (`is_primary`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ';');
}

if (!$CI->db->field_exists('barcode', db_prefix() . 'kt_inventory_items')) {
    $after = $CI->db->field_exists('unit', db_prefix() . 'kt_inventory_items') ? 'AFTER `unit`' : 'AFTER `sku`';
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'kt_inventory_items` ADD `barcode` VARCHAR(120) NULL ' . $after);
}
if (!$CI->db->field_exists('track_lot', db_prefix() . 'kt_inventory_items')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'kt_inventory_items` ADD `track_lot` TINYINT(1) NOT NULL DEFAULT 0 AFTER `barcode`');
}
if (!$CI->db->field_exists('track_serial', db_prefix() . 'kt_inventory_items')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'kt_inventory_items` ADD `track_serial` TINYINT(1) NOT NULL DEFAULT 0 AFTER `track_lot`');
}

$lineTables = [
    'kt_goods_receipt_items' => 'quantity',
    'kt_goods_issue_items' => 'quantity',
    'kt_stock_adjustment_items' => 'difference_quantity',
    'kt_stock_transfer_items' => 'quantity',
];

foreach ($lineTables as $lineTable => $afterColumn) {
    $fullTable = db_prefix() . $lineTable;
    if (!$CI->db->field_exists('lot_number', $fullTable)) {
        $CI->db->query('ALTER TABLE `' . $fullTable . '` ADD `lot_number` VARCHAR(120) NULL AFTER `' . $afterColumn . '`');
    }
    if (!$CI->db->field_exists('serial_number', $fullTable)) {
        $CI->db->query('ALTER TABLE `' . $fullTable . '` ADD `serial_number` VARCHAR(191) NULL AFTER `lot_number`');
    }
    if (!$CI->db->field_exists('expiry_date', $fullTable)) {
        $CI->db->query('ALTER TABLE `' . $fullTable . '` ADD `expiry_date` DATE NULL AFTER `serial_number`');
    }
    if (!$CI->db->field_exists('barcode_id', $fullTable)) {
        $CI->db->query('ALTER TABLE `' . $fullTable . '` ADD `barcode_id` INT NULL AFTER `inventory_item_id`');
    }
    if (!$CI->db->field_exists('scanned_barcode', $fullTable)) {
        $CI->db->query('ALTER TABLE `' . $fullTable . '` ADD `scanned_barcode` VARCHAR(191) NULL AFTER `barcode_id`');
    }
}

$barcodeMigrationsQuery = $CI->db
    ->select(db_prefix() . 'kt_inventory_items.id, ' . db_prefix() . 'kt_inventory_items.barcode, ' . db_prefix() . 'kt_inventory_items.created_at, ' . db_prefix() . 'kt_inventory_items.updated_at')
    ->where('barcode IS NOT NULL', null, false)
    ->where('barcode !=', '');

if ($CI->db->field_exists('unit', db_prefix() . 'kt_inventory_items')) {
    $barcodeMigrationsQuery->select('unit');
} else {
    $barcodeMigrationsQuery->select(db_prefix() . 'items.unit as unit');
    $barcodeMigrationsQuery->join(db_prefix() . 'items', db_prefix() . 'items.id = ' . db_prefix() . 'kt_inventory_items.item_id', 'left');
}

$barcodeMigrations = $barcodeMigrationsQuery->get(db_prefix() . 'kt_inventory_items')->result_array();

foreach ($barcodeMigrations as $row) {
    $exists = $CI->db->where('barcode', trim((string) $row['barcode']))->get(db_prefix() . 'kt_item_barcodes')->row_array();
    if ($exists) {
        continue;
    }

    $hasPrimary = $CI->db
        ->where('inventory_item_id', (int) $row['id'])
        ->where('is_primary', 1)
        ->where('is_active', 1)
        ->count_all_results(db_prefix() . 'kt_item_barcodes') > 0;

    $CI->db->insert(db_prefix() . 'kt_item_barcodes', [
        'inventory_item_id' => (int) $row['id'],
        'barcode'           => trim((string) $row['barcode']),
        'barcode_type'      => 'internal',
        'unit_type'         => trim((string) ($row['unit'] ?? '')),
        'package_quantity'  => 1,
        'is_primary'        => $hasPrimary ? 0 : 1,
        'source'            => 'manual',
        'is_active'         => 1,
        'created_by'        => get_staff_user_id() ?: null,
        'created_at'        => $row['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at'        => $row['updated_at'] ?? date('Y-m-d H:i:s'),
    ]);
}

add_option('kt_inventory_allow_negative_stock', '0');
add_option('kt_inventory_default_warehouse_id', '');
add_option('kt_inventory_low_stock_notification_enabled', '0');
add_option('kt_inventory_code_prefix_receipt', 'RCP');
add_option('kt_inventory_code_prefix_issue', 'ISS');
add_option('kt_inventory_code_prefix_adjustment', 'ADJ');
add_option('kt_inventory_code_prefix_transfer', 'TRF');
add_option('kt_inventory_internal_barcode_prefix', 'KTINV');
add_option('kt_inventory_internal_barcode_type', 'code128');
add_option('kt_inventory_next_barcode_number', '1');
