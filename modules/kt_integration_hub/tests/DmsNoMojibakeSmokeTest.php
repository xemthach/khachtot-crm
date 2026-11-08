<?php

$root = dirname(__DIR__);
$files = array_merge(
    glob($root . '/views/admin/dms/*.php'),
    [$root . '/controllers/Kt_integration_hub.php', $root . '/helpers/kt_integration_hub_helper.php']
);
$mojibake = '/Ã|Æ|Â|áº|á»|Ä‘|â€|�/u';
$forbiddenPrimaryCopy = ['DMS Don hang / Hoa don', 'Diem ban & San pham', 'Save & Đồng bộ', 'Orders & Invoices'];

foreach ($files as $file) {
    $content = file_get_contents($file);
    if (!mb_check_encoding($content, 'UTF-8')) {
        throw new RuntimeException('Invalid UTF-8: ' . $file);
    }
    if (preg_match($mojibake, $content)) {
        throw new RuntimeException('Mojibake found: ' . $file);
    }
    foreach ($forbiddenPrimaryCopy as $copy) {
        if (strpos($content, $copy) !== false) {
            throw new RuntimeException('Unlocalized primary copy found in ' . $file . ': ' . $copy);
        }
    }
}

echo "DmsNoMojibakeSmokeTest passed.\n";
