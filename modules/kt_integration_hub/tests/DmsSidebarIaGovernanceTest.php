<?php

$root = dirname(__DIR__);
$integration = file_get_contents($root . '/kt_integration_hub.php');
$saas = file_get_contents(dirname(__DIR__, 2) . '/kt_saas/kt_saas.php');
$inventory = file_get_contents(dirname(__DIR__, 2) . '/kt_inventory/kt_inventory.php');
$integrationController = file_get_contents($root . '/controllers/Kt_integration_hub.php');
$saasController = file_get_contents(dirname(__DIR__, 2) . '/kt_saas/controllers/Kt_saas.php');

if ($integration === false || $saas === false || $inventory === false || $integrationController === false || $saasController === false) {
    throw new RuntimeException('Could not read sidebar governance source files.');
}

$slice = static function (string $source, string $start, string $end): string {
    $startPos = strpos($source, $start);
    if ($startPos === false) {
        throw new RuntimeException('Missing source start marker: ' . $start);
    }
    $endPos = strpos($source, $end, $startPos);
    if ($endPos === false) {
        throw new RuntimeException('Missing source end marker: ' . $end);
    }

    return substr($source, $startPos, $endPos - $startPos);
};

$tenantDmsMenu = $slice($integration, '$dmsItems = [', '$dmsItems = array_values');
$landlordIntegrationMenu = $slice($integration, '$items = [', 'if (kt_integration_hub_staff_can');
$landlordDmsMenu = $slice($integration, '$dmsOperationsItems = [', '];' . "\n" . '        }');

foreach ([
    'kt_dms_overview',
    'kt_dms_setup_center',
    'kt_dms_staff',
    'kt_dms_store_products',
    'kt_dms_sync_health',
    'kt_dms_open',
    'kt_dms_plan_limits',
] as $slug) {
    if (strpos($tenantDmsMenu, $slug) === false) {
        throw new RuntimeException('Tenant DMS menu is missing approved business item: ' . $slug);
    }
}

foreach ([
    'kt_dms_workspaces',
    'kt_dms_workspace_add',
    'kt_dms_activation_status',
    'registry',
    'mapping',
    'projection',
    'compatibility',
    'worker',
    'cron',
] as $forbidden) {
    if (stripos($tenantDmsMenu, $forbidden) !== false) {
        throw new RuntimeException('Tenant DMS menu exposes technical or retired IA item: ' . $forbidden);
    }
}

foreach ([
    'kt_integration_hub_dashboard',
    'kt_integration_hub_providers',
    'kt_integration_hub_channel_orders',
] as $slug) {
    if (strpos($landlordIntegrationMenu, $slug) === false) {
        throw new RuntimeException('Landlord Integration Hub menu is missing connector item: ' . $slug);
    }
}

foreach (['kt_integration_hub_connections', 'kt_integration_hub_logs'] as $duplicateDmsRoute) {
    if (strpos($landlordIntegrationMenu, $duplicateDmsRoute) !== false) {
        throw new RuntimeException('Generic Integration Hub still duplicates DMS operator route: ' . $duplicateDmsRoute);
    }
}

foreach ([
    'kt_dms_control_center',
    'kt_dms_landlord_tenants',
    'kt_dms_commercial_orders',
    'kt_dms_packages',
    'kt_dms_landlord_dashboard',
    'kt_dms_logs',
    'kt_dms_connections',
] as $slug) {
    if (strpos($landlordIntegrationMenu, $slug) !== false) {
        throw new RuntimeException('Landlord Integration Hub still contains DMS Operations item: ' . $slug);
    }
    if (strpos($landlordDmsMenu, $slug) === false) {
        throw new RuntimeException('Landlord DMS Operations menu is missing item: ' . $slug);
    }
}

if (strpos($saas, 'kt_saas_dms_packages') !== false) {
    throw new RuntimeException('DMS package catalog remains duplicated under generic Khach Tot CRM SaaS menu.');
}

foreach ([
    "add_sidebar_menu_item('kt_dms_operations'",
    "add_sidebar_children_item('kt_dms_operations'",
    "'saas_cap' => 'kt_saas_manage_plans'",
    "'saas_cap' => 'kt_saas_view'",
    "function_exists('kt_saas_staff_can')",
] as $evidence) {
    if (strpos($integration, $evidence) === false) {
        throw new RuntimeException('Missing DMS Operations permission/menu evidence: ' . $evidence);
    }
}

foreach ([
    'dms_control_center' => "requireCapability('kt_integration_hub_manage')",
    'dms_landlord_subscriptions' => "requireCapability('kt_integration_hub_manage')",
    'dms_landlord_dashboard' => "requireCapability('kt_integration_hub_manage')",
] as $method => $capabilityEvidence) {
    $methodPos = strpos($integrationController, 'public function ' . $method);
    if ($methodPos === false) {
        throw new RuntimeException('Missing landlord DMS controller method: ' . $method);
    }
    $methodSlice = substr($integrationController, $methodPos, 500);
    if (strpos($methodSlice, 'requireLandlord()') === false || strpos($methodSlice, $capabilityEvidence) === false) {
        throw new RuntimeException('Landlord DMS route lacks landlord/manage guard: ' . $method);
    }
}

foreach ([
    'dms_packages' => "requireCapability('kt_saas_manage_plans')",
    'dms_orders' => "requireCapability('kt_saas_view')",
] as $method => $capabilityEvidence) {
    $methodPos = strpos($saasController, 'public function ' . $method);
    if ($methodPos === false) {
        throw new RuntimeException('Missing SaaS DMS controller method: ' . $method);
    }
    $methodSlice = substr($saasController, $methodPos, 500);
    if (strpos($methodSlice, $capabilityEvidence) === false) {
        throw new RuntimeException('SaaS DMS route lacks expected guard: ' . $method);
    }
}

if (strpos($saas, "add_sidebar_menu_item('kt_saas_apps'") === false || strpos($saas, "add_sidebar_children_item('kt_saas_apps'") === false) {
    throw new RuntimeException('Tenant App Marketplace is not isolated under Khach Tot Apps.');
}

if (strpos($saas, "add_sidebar_menu_item('kt_saas_marketplace'") !== false) {
    throw new RuntimeException('Tenant Marketplace is exposed as a standalone top-level menu.');
}

if (strpos($inventory, "add_sidebar_menu_item('kt_inventory'") === false) {
    throw new RuntimeException('Legacy CRM Inventory menu is missing its own product boundary.');
}

foreach (['DMS Warehouse', 'Salesrep DMS Warehouse', 'kt_dms_warehouse'] as $forbiddenInventoryLabel) {
    if (strpos($inventory, $forbiddenInventoryLabel) !== false) {
        throw new RuntimeException('Legacy CRM Inventory is mislabeled as DMS Warehouse: ' . $forbiddenInventoryLabel);
    }
}

echo "DmsSidebarIaGovernanceTest passed.\n";
