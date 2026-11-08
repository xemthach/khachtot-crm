<?php

$root = dirname(__DIR__);
$integrationModel = file_get_contents($root . '/models/Kt_integration_model.php');
$saasModel = file_get_contents(dirname($root) . '/kt_saas/models/Kt_saas_model.php');

$assertContains = static function (string $haystack, string $needle, string $message): void {
    if (strpos($haystack, $needle) === false) {
        throw new RuntimeException($message);
    }
};

foreach ([
    'downgrade_blocked',
    'block_reasons',
    "record_landlord_dms_action",
    "'downgrade_blocked'",
    "'dms_order' => (int)",
    "order_monthly_limit",
    "'success' => false",
    "'blocked' => true",
] as $needle) {
    $assertContains($integrationModel, $needle, 'Downgrade block contract missing: ' . $needle);
}

foreach ([
    'count_tenant_dms_orders_this_month',
    "'dms_order' => max(0, (int)",
    'count_tenant_dms_orders_this_month($tenantId)',
    "'dms_order' => 'Orders Used'",
    "'order_monthly_limit' => max(0, (int)",
] as $needle) {
    $assertContains($saasModel, $needle, 'Order usage quota support missing: ' . $needle);
}

echo "DmsDowngradeBlockedRuntimeSmokeTest passed.\n";
