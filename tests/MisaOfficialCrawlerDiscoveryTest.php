<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$crawler = file_get_contents($root . '/tools/misa_official_crawl.php');
foreach (['actdocs.misa.vn', 'asp.misa.vn', 'www.misa.vn/155021', 'developer.misa.vn', 'MISA_ACT_AMIS_DOCUMENT_INVENTORY'] as $needle) {
    if (strpos($crawler, $needle) === false) throw new RuntimeException('Missing discovery source: ' . $needle);
}
echo "MisaOfficialCrawlerDiscoveryTest passed.\n";
