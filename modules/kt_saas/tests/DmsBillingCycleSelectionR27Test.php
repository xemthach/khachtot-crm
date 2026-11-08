<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

$view = file_get_contents(dirname(__DIR__) . '/views/tenant/marketplace_app.php');
$model = file_get_contents(dirname(__DIR__) . '/models/Kt_saas_model.php');
if ($view === false || $model === false) {
    throw new RuntimeException('Unable to read R27 billing sources.');
}

foreach (['Hàng tháng', 'Hàng quý', 'Hàng năm', 'marketplace_cycle', 'data-cycle-prices', 'data-cycle-input'] as $needle) {
    if (strpos($view, $needle) === false) {
        throw new RuntimeException('Missing billing selector marker: ' . $needle);
    }
}

foreach (['get_dms_addon_billing_cycle_options', 'allowed_billing_periods', 'Chu kỳ thanh toán không được phép'] as $needle) {
    if (strpos($model, $needle) === false) {
        throw new RuntimeException('Missing server-side billing validation marker: ' . $needle);
    }
}

$validationOffset = strpos($model, 'create_or_refresh_tenant_dms_addon_invoice');
$validationSlice = $validationOffset === false ? '' : substr($model, $validationOffset, 3000);
if (strpos($validationSlice, "return ['success' => false") === false) {
    throw new RuntimeException('Unavailable billing cycle is not rejected by the purchase path.');
}

echo "DmsBillingCycleSelectionR27Test passed.\n";
