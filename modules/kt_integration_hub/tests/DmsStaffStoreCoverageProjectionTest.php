<?php

$root = dirname(__DIR__);
$install = file_get_contents($root . '/install.php');
$model = file_get_contents($root . '/models/Kt_integration_model.php');
$controller = file_get_contents($root . '/controllers/Kt_integration_hub.php');
$salesTeamView = file_get_contents($root . '/views/admin/dms/sales_team_today.php');
$syncHealthView = file_get_contents($root . '/views/admin/dms/sync_health.php');

$assertContains = static function (string $haystack, string $needle, string $message): void {
    if (strpos($haystack, $needle) === false) {
        throw new RuntimeException($message);
    }
};

$assertNotContains = static function (string $haystack, string $needle, string $message): void {
    if (strpos($haystack, $needle) !== false) {
        throw new RuntimeException($message);
    }
};

$assertContains($install, 'dms_staff_activity_projection', 'Install must create staff activity projection table.');
$assertContains($install, 'dms_store_coverage_projection', 'Install must create store coverage projection table.');
$assertContains($install, 'UNIQUE KEY `tenant_staff_activity_source`', 'Staff activity projection must be idempotent per tenant/source/version.');
$assertContains($install, 'UNIQUE KEY `tenant_store_coverage_source`', 'Store coverage projection must be idempotent per tenant/source/version.');

$assertContains($model, 'upsert_dms_staff_activity_projection', 'Model must expose staff activity projection upsert.');
$assertContains($model, 'upsert_dms_store_coverage_projection', 'Model must expose store coverage projection upsert.');
$assertContains($model, 'backfill_dms_staff_store_coverage_projections', 'Model must expose staff/store coverage backfill.');
$assertContains($model, 'get_dms_projected_sales_team_today', 'Sales Team Today must read staff activity projection first.');
$assertContains($model, 'get_dms_projected_store_coverage_summary', 'Store coverage metrics must read projection first.');
$assertContains($model, 'get_dms_projection_health', 'Sync Health must expose projection layer status.');

$assertContains($syncHealthView, 'CRM ↔ DMS Readiness', 'Sync Health must show projection and mapping readiness.');
$assertContains($syncHealthView, 'Thông tin dành cho hỗ trợ', 'Sync Health must keep diagnostics in the controlled support surface.');
$assertContains($salesTeamView, 'Doi sales hom nay', 'Sales Team Today business view must remain present.');
$assertNotContains($salesTeamView, 'tbldms_staff_activity_projection', 'Tenant business view must not expose raw staff activity projection table name.');
$assertNotContains($salesTeamView, 'tbldms_store_coverage_projection', 'Tenant business view must not expose raw store coverage projection table name.');

echo "DmsStaffStoreCoverageProjectionTest passed.\n";
