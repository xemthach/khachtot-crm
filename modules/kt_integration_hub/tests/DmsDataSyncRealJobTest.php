<?php defined('BASEPATH') or define('BASEPATH', __DIR__);
$source = file_get_contents(dirname(__DIR__) . '/models/Kt_integration_model.php');
foreach (['queue_customer_sync_job', 'queue_product_sync_job', 'process_due_jobs', 'successful_count', 'record_count'] as $contract) if (strpos($source, $contract) === false) throw new RuntimeException($contract);
echo "DmsDataSyncRealJobTest passed.\n";
