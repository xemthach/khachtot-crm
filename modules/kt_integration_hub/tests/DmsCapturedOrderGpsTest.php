<?php

$crmRoot = dirname(__DIR__);
$salesrepRoot = dirname($crmRoot, 3) . '/salesrep';

$model = file_get_contents($crmRoot . '/models/Kt_integration_model.php');
$orderModel = file_get_contents($salesrepRoot . '/app/Models/Order.php');
$orderRequest = file_get_contents($salesrepRoot . '/app/Http/Requests/OrderRequest.php');
$orderService = file_get_contents($salesrepRoot . '/app/Services/OrderService.php');
$bridge = file_get_contents($salesrepRoot . '/app/Services/Integration/OrderInvoiceBridgeService.php');
$orderCreate = file_get_contents($salesrepRoot . '/resources/views/orders/create.blade.php');

$assertContains = function (string $haystack, string $needle, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

foreach ([$orderModel, $orderRequest, $orderService, $bridge, $orderCreate] as $source) {
    $assertContains($source, 'captured_latitude', 'Salesrep order flow must carry captured_latitude.');
    $assertContains($source, 'captured_longitude', 'Salesrep order flow must carry captured_longitude.');
}

$assertContains($bridge, 'order_latitude', 'Order bridge payload must send captured order latitude separately from store latitude.');
$assertContains($bridge, 'store_latitude', 'Order bridge payload must send store context separately.');
$assertContains($model, 'captured_order_gps', 'CRM projection must distinguish captured order GPS source.');
$assertContains($model, 'store coordinates for order context; not captured order GPS', 'CRM projection must not fake order GPS from store coordinates.');
$assertContains($orderCreate, 'không gọi đó là GPS đơn hàng', 'Order create UX must explain fallback honestly.');

echo "DmsCapturedOrderGpsTest passed\n";
