<?php

$root = dirname(__DIR__);
$install = file_get_contents($root . '/install.php');
$model = file_get_contents($root . '/models/Kt_integration_model.php');
$controller = file_get_contents($root . '/controllers/Kt_integration_hub.php');
$ordersView = file_get_contents($root . '/views/admin/dms/orders_invoices.php');
$mapView = file_get_contents($root . '/views/admin/dms/map_activity.php');

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

$assertContains($install, 'dms_order_projection', 'Install must create DMS order projection table.');
$assertContains($install, 'dms_location_projection', 'Install must create DMS location projection table.');
$assertContains($install, 'UNIQUE KEY `tenant_source_record`', 'Order projection must be idempotent by tenant/source/version.');
$assertContains($install, 'UNIQUE KEY `tenant_marker_source`', 'Location projection must be idempotent by tenant/marker/source/version.');

$assertContains($model, 'upsert_dms_order_projection_from_payload', 'Model must expose order projection upsert.');
$assertContains($model, 'backfill_dms_order_location_projections', 'Model must expose projection backfill/reconciliation.');
$assertContains($model, 'get_dms_projected_orders_invoices', 'Orders & Invoices must support projection reads.');
$assertContains($model, 'get_dms_projected_order_store_locations', 'Map Activity must support projected order-store locations.');
$assertContains($model, 'crm_invoice_then_dms_readback_then_unknown', 'Payment precedence contract must be encoded.');
$assertContains($model, 'store coordinates for order context; not captured order GPS', 'Order-store location must not be labeled as captured order GPS.');
$assertContains($model, "payment_source IN ('crm.invoice','crm.invoices','crm.projection','salesrep.readback','salesrep.orders.readback')", 'Only known CRM/readback payment sources may be counted as paid/balance totals.');

$assertContains($controller, 'DMS map activity presenter unavailable', 'Map controller must fail safe if presenter fails.');
$assertContains($controller, 'reset_query', 'Map controller must reset query builder after presenter failure.');

$assertContains($ordersView, 'Hóa đơn và thanh toán CRM được ưu tiên', 'Orders view must explain payment source precedence.');
$assertContains($ordersView, 'Chưa có dữ liệu thanh toán', 'Orders view must show unknown payment state truthfully.');
$assertContains($mapView, 'Điểm bán của đơn hàng', 'Map view must label order-store marker clearly.');
$assertContains($mapView, 'Vị trí gửi đơn hàng', 'Map view must distinguish captured order GPS from store coordinates.');

foreach ([$ordersView, $mapView] as $view) {
    $assertNotContains($view, 'tbldms_order_projection', 'Tenant business view must not expose raw order projection table name.');
    $assertNotContains($view, 'tbldms_location_projection', 'Tenant business view must not expose raw location projection table name.');
}

echo "DmsProjectionContractSmokeTest passed.\n";
