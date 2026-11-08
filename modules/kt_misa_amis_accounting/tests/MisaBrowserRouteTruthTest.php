<?php
$path = dirname(__DIR__, 3) . '/tests/phase10_6_browser_result.json';
if (!is_file($path)) throw new RuntimeException('Phase 10.6 browser result is missing.');
$results = json_decode(file_get_contents($path), true);
if (!is_array($results) || count($results) !== 2) throw new RuntimeException('Unexpected browser result schema.');
foreach ($results as $result) if (empty($result['pass']) || !empty($result['secret_leaks'])) throw new RuntimeException('Browser route truth or secret scan failed.');
echo "MisaBrowserRouteTruthTest passed; tenant_route_truth=consistent.\n";
