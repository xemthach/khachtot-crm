<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

require_once dirname(__DIR__) . '/helpers/kt_integration_hub_helper.php';

$assert = static function ($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(
    kt_integration_hub_crm_company_id(['id' => 32, 'landlord_client_id' => 901]) === 901,
    'CRM company must resolve from landlord_client_id rather than tenant id.'
);
$assert(
    kt_integration_hub_crm_company_id(['id' => 32, 'crm_company_id' => 902, 'landlord_client_id' => 901]) === 902,
    'Explicit crm_company_id must have precedence.'
);
$assert(
    kt_integration_hub_crm_company_id(['id' => 32]) === null,
    'Missing CRM company context must fail closed.'
);

$model = file_get_contents(dirname(__DIR__) . '/models/Kt_integration_model.php');
$menu = file_get_contents(dirname(__DIR__) . '/kt_integration_hub.php');
$controller = file_get_contents(dirname(__DIR__) . '/controllers/Kt_integration_hub.php');
$sso = file_get_contents(dirname(__DIR__) . '/controllers/Kt_integration_sso.php');

$assert(strpos($model, "'setup_center' => \$dmsEnabled") !== false, 'DMS setup menu must follow active entitlement.');
$assert(strpos($menu, 'if (!empty($dmsItems))') !== false, 'Empty DMS parent menu must not be registered.');
$assert(substr_count($menu, "'setup_center' => false") >= 1, 'DMS menu visibility must fail closed before entitlement resolution.');
$assert(strpos($sso, "'canonical_crm_company_id'") !== false, 'SSO must emit the canonical CRM company identity.');
$assert(strpos($sso, "'compatibility_crm_company_id'") !== false, 'SSO must preserve the compatibility company identity separately.');
$assert(strpos($controller, "'canonical_crm_company_id'=>\$companyId") !== false, 'Signed mutations must use canonical company scope.');

echo "DmsLandlordControlHardeningTest passed.\n";
