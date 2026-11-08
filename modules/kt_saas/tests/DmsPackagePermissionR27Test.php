<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

$saas = file_get_contents(dirname(__DIR__) . '/controllers/Kt_saas.php');
$hub = file_get_contents(dirname(__DIR__, 2) . '/kt_integration_hub/controllers/Kt_integration_hub.php');
if ($saas === false || $hub === false) {
    throw new RuntimeException('Unable to read R27 permission sources.');
}

foreach ([
    '$this->requireTenantContext();',
    '$this->requireTenantAdmin();',
    '$this->requirePost();',
    "'tenant_install_salesflow_dms'",
] as $needle) {
    if (strpos($saas, $needle) === false) {
        throw new RuntimeException('Missing tenant package permission guard: ' . $needle);
    }
}

$wizardOffset = strpos($hub, 'public function dms_setup_wizard');
$wizardSlice = $wizardOffset === false ? '' : substr($hub, $wizardOffset, 500);
foreach (['requireTenantContext', "requireCapability('kt_integration_hub_view')"] as $needle) {
    if (strpos($wizardSlice, $needle) === false) {
        throw new RuntimeException('Missing setup wizard permission guard: ' . $needle);
    }
}

echo "DmsPackagePermissionR27Test passed.\n";
