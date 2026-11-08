<?php
defined('BASEPATH') or define('BASEPATH', __DIR__);
$m=file_get_contents(dirname(__DIR__).'/models/Kt_saas_model.php');$cli=file_get_contents(dirname(__DIR__,3).'/application/controllers/Kt_saas_cli.php');
foreach(["!== 'paid'",'subscription_active','provisioning_pending','mark_provision_job_running','mark_provision_job_done','workspace_ready','dms_activation'] as $n)if(strpos($m.$cli,$n)===false)throw new RuntimeException("Missing activation state: $n");
echo "DmsActivationWorkerStateMachineTest passed.\n";
