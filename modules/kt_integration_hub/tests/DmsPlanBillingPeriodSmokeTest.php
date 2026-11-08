<?php

$model = file_get_contents(dirname(__DIR__, 2) . '/kt_saas/models/Kt_saas_model.php');
$view = file_get_contents(dirname(__DIR__, 2) . '/kt_saas/views/dashboard/dms_packages.php');

foreach (['allowed_billing_periods', 'default_billing_period', 'monthly', 'quarterly', 'yearly'] as $contract) {
    if (strpos($model . $view, $contract) === false) {
        throw new RuntimeException('Missing billing period contract: ' . $contract);
    }
}
if (strpos($model, 'Chu kỳ thanh toán không được phép') === false) {
    throw new RuntimeException('Unavailable billing period is not rejected server-side.');
}
if (strpos($view, 'name="allowed_billing_periods[]"') === false || strpos($view, 'name="default_billing_period"') === false) {
    throw new RuntimeException('Landlord billing period controls are missing.');
}

echo "DmsPlanBillingPeriodSmokeTest passed.\n";
