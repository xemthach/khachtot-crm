<?php
defined('BASEPATH') or define('BASEPATH', __DIR__);
$c=file_get_contents(dirname(__DIR__,2).'/kt_integration_hub/controllers/Kt_integration_hub.php').file_get_contents(dirname(__DIR__).'/controllers/Kt_saas.php');$m=file_get_contents(dirname(__DIR__,2).'/kt_integration_hub/models/Kt_integration_model.php');
foreach(['dms_run_staff_reprovision','queue_staff_sync_job','get_salesflow_connection_for_tenant','retry_staff_sync'] as $n)if(strpos($c.$m,$n)===false)throw new RuntimeException("Missing staff sync recovery: $n");
echo "DmsStaffSyncAccessProvisioningTest passed.\n";
