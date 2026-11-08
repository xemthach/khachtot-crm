<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

$modelSource = file_get_contents(dirname(__DIR__) . '/models/Kt_saas_model.php');
$controllerSource = file_get_contents(dirname(__DIR__) . '/controllers/Kt_saas.php');
$billingSource = file_get_contents(dirname(__DIR__) . '/services/BillingEngineService.php');
$sepayGatewaySource = file_get_contents(dirname(__DIR__, 2) . '/kt_sepay/libraries/Kt_sepay_gateway.php');
$sepayProcessorSource = file_get_contents(dirname(__DIR__, 2) . '/kt_sepay/libraries/Kt_sepay_processor.php');

if ($modelSource === false || $controllerSource === false || $billingSource === false || $sepayGatewaySource === false || $sepayProcessorSource === false) {
    throw new RuntimeException('Unable to read DMS paid add-on lifecycle sources.');
}

foreach ([
    'create_or_refresh_tenant_dms_addon_invoice',
    'activate_tenant_dms_addon_from_invoice',
    'dms_addon_purchase',
    'tenant_marketplace_provisioning/salesflow-dms',
    'Xem thanh toán',
    'kt_saas_dms_addon',
] as $needle) {
    if (strpos($modelSource . $controllerSource . $billingSource . $sepayGatewaySource . $sepayProcessorSource, $needle) === false) {
        throw new RuntimeException('Expected DMS paid add-on lifecycle marker not found: ' . $needle);
    }
}

if (strpos($billingSource, 'handlePostPaymentSuccess') === false) {
    throw new RuntimeException('BillingEngineService does not expose a post-payment fulfillment hook.');
}

if (strpos($billingSource, 'activate_tenant_dms_addon_from_invoice') === false) {
    throw new RuntimeException('Payment success does not activate the DMS add-on.');
}

if (strpos($controllerSource, 'create_or_refresh_tenant_dms_addon_invoice') === false) {
    throw new RuntimeException('Marketplace controller does not create a DMS checkout invoice.');
}

echo "DmsAddonPurchaseLifecycleSmokeTest passed.\n";
