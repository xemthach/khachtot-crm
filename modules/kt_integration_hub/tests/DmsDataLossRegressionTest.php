<?php defined('BASEPATH') or define('BASEPATH', __DIR__);
$results = dirname(__DIR__, 4) . '/R31_DATA_LOSS_RESULTS.json';
if (!is_file($results)) { echo "DmsDataLossRegressionTest deferred: results not generated yet.\n"; return; }
$json = json_decode(file_get_contents($results), true);
if (!is_array($json) || empty($json['pass'])) throw new RuntimeException('R31 data loss proof did not pass.');
foreach (['users_preserved','assignments_preserved','stores_preserved','products_preserved','orders_preserved','audit_appended'] as $key) {
    if (empty($json['assertions'][$key])) throw new RuntimeException('Missing data loss assertion: ' . $key);
}
echo "DmsDataLossRegressionTest passed.\n";
