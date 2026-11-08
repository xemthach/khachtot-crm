<?php

$root = dirname(__DIR__);
$controller = file_get_contents($root.'/controllers/Kt_integration_hub.php');
$tenantSetup = file_get_contents($root.'/views/admin/dms/setup_center.php');
$tenantHealth = file_get_contents($root.'/views/admin/dms/sync_health.php');
$landlord = file_get_contents($root.'/views/admin/dms_landlord/dashboard.php');
$detail = file_get_contents($root.'/views/admin/dms_landlord/tenant_detail.php');

foreach (['DmsIntegrationContractStore', 'crm_dms_readiness', 'mapping_issues', 'contract_readiness'] as $needle) {
    if (strpos($controller, $needle) === false) {
        throw new RuntimeException('Missing management controller contract '.$needle);
    }
}

foreach (['Kiểm tra sẵn sàng CRM ↔ DMS', 'CRM ↔ DMS Readiness', 'CRM ↔ DMS Health', 'CRM Readiness'] as $needle) {
    if (strpos($tenantSetup.$tenantHealth.$landlord.$detail, $needle) === false) {
        throw new RuntimeException('Missing management UI marker '.$needle);
    }
}

foreach (['raw_payload', 'payload_json', 'latitude', 'longitude', 'Authorization', 'Bearer'] as $forbidden) {
    if (strpos($landlord.$detail, $forbidden) !== false) {
        throw new RuntimeException('Landlord UI exposes forbidden marker '.$forbidden);
    }
}

if (strpos($controller, 'requirePostRequest') === false) {
    throw new RuntimeException('Mutation POST guard missing.');
}

echo "M8CrmDmsManagementUiTest passed.\n";
