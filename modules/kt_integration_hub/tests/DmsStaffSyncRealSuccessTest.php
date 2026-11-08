<?php defined('BASEPATH') or define('BASEPATH', __DIR__);
$source = file_get_contents(dirname(__DIR__) . '/libraries/SyncStaffToDmsJob.php') . file_get_contents(dirname(__DIR__) . '/models/Kt_integration_model.php');
foreach (['/api/v1/integration/staff/upsert', 'queue_staff_sync_job', 'idempotency_key', "'status' => 'synced'"] as $contract) if (strpos($source, $contract) === false) throw new RuntimeException($contract);
echo "DmsStaffSyncRealSuccessTest passed.\n";
