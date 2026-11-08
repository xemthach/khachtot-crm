<?php defined('BASEPATH') or define('BASEPATH', __DIR__);

$head = file_get_contents(dirname(__DIR__, 3) . '/application/views/admin/includes/head.php');

foreach (['<!DOCTYPE html>', 'rel="icon"', 'charset="UTF-8"'] as $needle) {
    if (stripos($head, $needle) === false) {
        throw new RuntimeException($needle);
    }
}

echo "DmsLanguageFontAssetCleanupTest passed.\n";
