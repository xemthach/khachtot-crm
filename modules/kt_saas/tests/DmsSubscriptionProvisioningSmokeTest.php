<?php
defined('BASEPATH') or define('BASEPATH', __DIR__);
$model=file_get_contents(dirname(__DIR__).'/models/Kt_saas_model.php');
foreach(['subscription_active','provisioning_pending','provisioning_running','workspace_ready','retry_dms_commercial_order_provisioning','provision_job_id','install_salesflow_dms'] as $needle){if(strpos($model,$needle)===false)throw new RuntimeException('Missing subscription/provision marker: '.$needle);}
echo "DmsSubscriptionProvisioningSmokeTest passed.\n";
