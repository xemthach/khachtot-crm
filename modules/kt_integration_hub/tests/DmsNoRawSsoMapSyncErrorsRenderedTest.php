<?php defined('BASEPATH') or define('BASEPATH', __DIR__);
$root = dirname(__DIR__, 3) . '/tmp_r301';
foreach (glob($root . '/*.html') as $file) {
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', file_get_contents($file));
    if (preg_match('/Salesflow DMS connection is not configured|SSO_CONNECTION_NOT_CONFIGURED|Sync job failed|projection_stale|Support view-only/i', strip_tags($html))) throw new RuntimeException(basename($file));
}
echo "DmsNoRawSsoMapSyncErrorsRenderedTest passed.\n";
