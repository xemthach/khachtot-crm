<?php defined('BASEPATH') or define('BASEPATH', __DIR__);
$files = array_merge(glob(dirname(__DIR__, 4) . '/R31_*.md') ?: [], glob(dirname(__DIR__, 4) . '/R31_*.json') ?: []);
$banned = ['delete all', 'truncate', 'reset tenant', 'Staff access pending', 'Support view-only', 'raw payload', 'internal exception'];
foreach ($files as $file) {
    $text = file_get_contents($file);
    foreach ($banned as $needle) {
        if (stripos($text, $needle) !== false) throw new RuntimeException('Banned governance language in ' . basename($file) . ': ' . $needle);
    }
}
echo "DmsRenderedGovernanceLanguageTest passed.\n";
