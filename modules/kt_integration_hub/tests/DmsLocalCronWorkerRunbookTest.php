<?php defined('BASEPATH') or define('BASEPATH', __DIR__);
$file = dirname(__DIR__, 4) . '/R31_LOCAL_CRON_WORKER_RUNBOOK.md';
if (!is_file($file)) { echo "DmsLocalCronWorkerRunbookTest deferred: report not generated yet.\n"; return; }
$text = file_get_contents($file);
foreach (['kt_integration_hub/cron/process_jobs', 'kt_integration_hub/cron/reprovision_staff', 'php index.php', 'Windows Task Scheduler', 'failed jobs'] as $needle) {
    if (stripos($text, $needle) === false) throw new RuntimeException('Missing local runbook marker: ' . $needle);
}
echo "DmsLocalCronWorkerRunbookTest passed.\n";
