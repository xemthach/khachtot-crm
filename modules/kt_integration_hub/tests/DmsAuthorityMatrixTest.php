<?php defined('BASEPATH') or define('BASEPATH', __DIR__);
$root = dirname(__DIR__, 4);
$final = $root . '/R31_DMS_AUTHORITY_MATRIX.md';
if (!is_file($final)) {
    echo "DmsAuthorityMatrixTest deferred: report not generated yet.\n";
    return;
}
$text = file_get_contents($final);
foreach (['Landlord', 'Tenant Admin', 'System Worker', 'Salesrep Runtime'] as $needle) {
    if (strpos($text, $needle) === false) throw new RuntimeException('Missing authority owner: ' . $needle);
}
echo "DmsAuthorityMatrixTest passed.\n";
