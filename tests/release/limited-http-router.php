<?php
// Harness for real policy HTTP semantics, NOT an installed CRM front controller.
if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(404); exit;
}
define('BASEPATH', __DIR__ . '/../../system/');
define('APPPATH', __DIR__ . '/../../application/');
require APPPATH . 'hooks/KtLimitedRelease.php';
(new KtLimitedRelease())->early();
header('Content-Type: application/json');
echo '{"harness_only":true}';
