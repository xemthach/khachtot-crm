<?php
$paths = [dirname(__DIR__, 3) . '/tests/phase10_6_browser_result.json', dirname(__DIR__, 3) . '/tests/phase10_5_browser_result.json'];
foreach ($paths as $path) if (is_file($path) && preg_match('/phase105-create-secret|misa-phase10-key|mock-token-redacted/', file_get_contents($path))) throw new RuntimeException('Known secret appeared in browser artifact.');
echo "MisaSecretScopeLeakTest passed; browser_artifact_secret_scan=clean.\n";
