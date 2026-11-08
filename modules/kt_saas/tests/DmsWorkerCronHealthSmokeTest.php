<?php
defined('BASEPATH') or define('BASEPATH', __DIR__);
$m=file_get_contents(dirname(__DIR__).'/models/Kt_saas_model.php');$v=file_get_contents(dirname(__DIR__).'/views/dashboard/dms_order_detail.php');$cron=file_get_contents(dirname(__DIR__).'/cron/Kt_saas_cron.php');
foreach(['get_dms_runtime_process_health','last_cron_run','stale_lock','get_due_provision_jobs','Cần chạy cron/worker'] as $n)if(strpos($m.$v.$cron,$n)===false)throw new RuntimeException("Missing worker health marker: $n");
echo "DmsWorkerCronHealthSmokeTest passed.\n";
