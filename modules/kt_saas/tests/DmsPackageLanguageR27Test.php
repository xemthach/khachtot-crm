<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

$sources = [
    dirname(__DIR__) . '/views/tenant/marketplace_app.php',
    dirname(__DIR__, 2) . '/kt_integration_hub/views/admin/dms/setup_wizard.php',
];
$combined = '';
foreach ($sources as $source) {
    $content = file_get_contents($source);
    if ($content === false) {
        throw new RuntimeException('Unable to read localization source: ' . $source);
    }
    $combined .= "\n" . $content;
}

$banned = [
    'Choose package', 'Current service package', '>Marketplace<', '>Billing<', '>Trial<',
    'Not installed', 'Runtime blocked', '>Open DMS<', 'Lifecycle summary', 'Commercial model',
    'Bridge not ready', 'Staff access pending', '>Current package<', '>Change package<',
    'Install History', 'Job Type', 'Preview Coming Soon', '>Pending<',
];
foreach ($banned as $needle) {
    if (stripos($combined, $needle) !== false) {
        throw new RuntimeException('Customer-facing English remains in R27 views: ' . $needle);
    }
}

if (preg_match('/Ã[\x80-\xBF]|Ä[\x80-\xBF]|á[º»]|Tá»|KhÃ|Há»|CÃ|�/u', $combined)) {
    throw new RuntimeException('Mojibake remains in R27 customer-facing views.');
}

echo "DmsPackageLanguageR27Test passed.\n";
