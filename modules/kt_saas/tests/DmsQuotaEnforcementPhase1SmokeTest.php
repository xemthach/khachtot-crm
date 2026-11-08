<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

function assertContainsText(string $path, array $needles): void
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('Unable to read file: ' . $path);
    }

    foreach ($needles as $needle) {
        if (strpos($contents, $needle) === false) {
            throw new RuntimeException(sprintf('Expected to find "%s" in %s.', $needle, $path));
        }
    }
}

$root = dirname(__DIR__);
$integrationRoot = dirname($root) . '/kt_integration_hub';

assertContainsText($root . '/models/Kt_saas_model.php', [
    'function sync_tenant_dms_usage_snapshot',
    'function get_tenant_dms_usage_summary',
    "'dms_user'",
    "'dms_store'",
    "'dms_product'",
    "'dms_checkin'",
    "'dms_photo'",
    "'dms_export'",
    'kt_saas_usage',
]);

assertContainsText($root . '/controllers/Kt_saas.php', [
    'get_tenant_dms_usage_summary',
    "'usage_summary' => \$usageSummary",
]);

assertContainsText($root . '/views/tenant/marketplace_app.php', [
    'Usage &amp; Quota',
    'Quota Remaining',
    'Usage snapshot unavailable.',
]);

assertContainsText($integrationRoot . '/models/Kt_integration_model.php', [
    'function sync_tenant_dms_usage_snapshot',
    'Kt_saas_model->sync_tenant_dms_usage_snapshot',
]);

assertContainsText($integrationRoot . '/libraries/DmsCheckinBridgeProcessor.php', [
    'sync_tenant_dms_usage_snapshot($tenantId)',
]);

echo "DmsQuotaEnforcementPhase1SmokeTest passed.\n";
