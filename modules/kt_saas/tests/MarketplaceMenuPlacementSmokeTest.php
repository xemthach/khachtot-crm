<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

$source = file_get_contents(dirname(__DIR__) . '/kt_saas.php');
$english = file_get_contents(dirname(__DIR__) . '/language/english/kt_saas_lang.php');
$vietnamese = file_get_contents(dirname(__DIR__) . '/language/vietnamese/kt_saas_lang.php');
$entitlementService = file_get_contents(dirname(__DIR__) . '/services/TenantEntitlementService.php');

if ($source === false || $english === false || $vietnamese === false || $entitlementService === false) {
    throw new RuntimeException('Marketplace menu source files could not be read.');
}

if (strpos($source, "add_sidebar_menu_item('kt_saas_apps'") === false) {
    throw new RuntimeException('Missing tenant app menu group.');
}

if (strpos($source, "add_sidebar_children_item('kt_saas_apps'") === false) {
    throw new RuntimeException('Marketplace is not registered under the tenant app group.');
}

if (strpos($source, "add_sidebar_menu_item('kt_saas_marketplace'") !== false) {
    throw new RuntimeException('Marketplace still exists as a standalone top-level item.');
}

if (strpos($source, "add_sidebar_menu_item('kt_saas_account'") === false) {
    throw new RuntimeException('SaaS account menu is no longer separate.');
}

if (strpos($english, "\$lang['kt_saas_apps'] = 'Khach Tot Apps';") === false) {
    throw new RuntimeException('Missing English app-group label.');
}

if (strpos($vietnamese, "\$lang['kt_saas_apps'] = 'Ứng dụng Khách Tốt';") === false) {
    throw new RuntimeException('Missing Vietnamese app-group label.');
}

foreach ([
    'admin/kt_saas/tenant_marketplace',
    'admin/kt_saas/tenant_install_salesflow_dms',
    'tenant_marketplace_(app|provisioning)',
] as $routeEvidence) {
    if (strpos($entitlementService, $routeEvidence) === false) {
        throw new RuntimeException('Tenant bootstrap whitelist is missing Marketplace route evidence: ' . $routeEvidence);
    }
}

echo "MarketplaceMenuPlacementSmokeTest passed.\n";
