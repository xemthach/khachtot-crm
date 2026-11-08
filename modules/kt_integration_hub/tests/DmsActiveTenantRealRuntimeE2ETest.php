<?php defined('BASEPATH') or define('BASEPATH', __DIR__);
$root = dirname(__DIR__, 4);
$result = json_decode(file_get_contents($root . '/R301_BROWSER_RESULTS.json'), true);
if (($result['failed'] ?? 1) !== 0 || ($result['passed'] ?? 0) < 15) throw new RuntimeException('R301 browser E2E is not clean.');
echo "DmsActiveTenantRealRuntimeE2ETest passed.\n";
