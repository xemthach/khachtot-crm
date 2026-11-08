<?php
defined('BASEPATH') or define('BASEPATH', __DIR__);
$controller=file_get_contents(dirname(__DIR__).'/controllers/Kt_saas.php');
$entitlements=file_get_contents(dirname(__DIR__).'/services/TenantEntitlementService.php');
foreach(['$this->requireTenantAdmin();','$this->requireCapability(\'kt_saas_view\')','$this->requireCapability(\'kt_saas_manage_billing\')','$this->requirePost();','get_dms_commercial_order((int) $orderId, $tenantId)'] as $needle){if(strpos($controller,$needle)===false)throw new RuntimeException('Missing order permission marker: '.$needle);}
foreach(['dms_order_(status|pay)','tenant_cancel_dms_order'] as $needle){if(strpos($entitlements,$needle)===false)throw new RuntimeException('Missing tenant portal entitlement route: '.$needle);}
echo "DmsOrderPermissionSecurityTest passed.\n";
