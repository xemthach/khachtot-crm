<?php

$root = dirname(__DIR__);
$install = file_get_contents($root . '/install.php');
$model = file_get_contents($root . '/models/Kt_integration_model.php');
$view = file_get_contents($root . '/views/admin/dms/store_products.php');

$assertContains = static function (string $haystack, string $needle, string $message): void {
    if (strpos($haystack, $needle) === false) {
        throw new RuntimeException($message);
    }
};

$assertNotContains = static function (string $haystack, string $needle, string $message): void {
    if (strpos($haystack, $needle) !== false) {
        throw new RuntimeException($message);
    }
};

$assertContains($install, 'dms_inventory_projection', 'Install must create inventory projection table.');
$assertContains($install, 'dms_product_sales_projection', 'Install must create product-sales projection table.');
$assertContains($install, 'UNIQUE KEY `tenant_inventory_source`', 'Inventory projection must be idempotent per tenant/source/version.');
$assertContains($install, 'UNIQUE KEY `tenant_product_sales_source`', 'Product-sales projection must be idempotent per tenant/source/version.');
$assertContains($install, '`tenant_id` INT UNSIGNED NOT NULL', 'Projection schema must carry tenant_id.');
$assertContains($install, '`company_id` INT UNSIGNED NOT NULL', 'Projection schema must carry company_id.');

$assertContains($model, 'upsert_dms_inventory_projection', 'Model must expose inventory projection upsert.');
$assertContains($model, 'upsert_dms_product_sales_projection', 'Model must expose product-sales projection upsert.');
$assertContains($model, 'backfill_dms_inventory_product_projections', 'Model must expose inventory/product backfill.');
$assertContains($model, 'get_dms_projected_inventory_insight', 'Store/Product screen must read inventory projection first.');
$assertContains($model, 'get_dms_projected_top_ordered_products', 'Store/Product screen must read product-sales projection first.');
$assertContains($model, 'fetch_salesrep_inventory_projection_rows', 'Pilot backfill may use guarded Salesrep reads.');
$assertContains($model, 'fetch_salesrep_product_sales_projection_rows', 'Pilot backfill may use guarded Salesrep reads for product sales.');

$assertContains($view, 'bản sao đồng bộ an toàn trong CRM', 'Store/Product copy must communicate projection-first behavior.');
$assertContains($view, 'Nếu chưa có dữ liệu đồng bộ', 'Store/Product empty state must be truthful about missing projection data.');
$assertNotContains($view, 'tbldms_inventory_projection', 'Tenant business view must not expose raw inventory projection table name.');
$assertNotContains($view, 'tbldms_product_sales_projection', 'Tenant business view must not expose raw product-sales projection table name.');

echo "DmsInventoryProductProjectionTest passed.\n";
