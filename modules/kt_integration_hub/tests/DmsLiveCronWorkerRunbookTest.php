<?php defined('BASEPATH') or define('BASEPATH', __DIR__);
$file = dirname(__DIR__, 4) . '/R31_LIVE_CRON_WORKER_RUNBOOK.md';
if (!is_file($file)) { echo "DmsLiveCronWorkerRunbookTest deferred: report not generated yet.\n"; return; }
$text = file_get_contents($file);
foreach (['cron', 'Supervisor', 'systemd', 'backup', 'rollback', 'APP_CRON_KEY', 'logs'] as $needle) {
    if (stripos($text, $needle) === false) throw new RuntimeException('Missing live runbook marker: ' . $needle);
}
echo "DmsLiveCronWorkerRunbookTest passed.\n";
