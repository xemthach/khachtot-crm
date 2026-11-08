<?php defined('BASEPATH') or define('BASEPATH', __DIR__);
$service = file_get_contents(dirname(__DIR__, 4) . '/salesrep/app/Services/Integration/StaffSyncService.php');
foreach (['where(\'crm_tenant_id\'', 'where(\'crm_staff_id\'', '$user ??= new User()', 'dms_access_enabled', 'sync_status'] as $needle) {
    if (strpos($service, $needle) === false) throw new RuntimeException('Missing staff upsert safety marker: ' . $needle);
}
if (preg_match('/->delete\s*\(|forceDelete\s*\(/', $service)) {
    throw new RuntimeException('Staff sync service must not delete users.');
}
echo "DmsStaffSyncUpsertSafetyTest passed.\n";
