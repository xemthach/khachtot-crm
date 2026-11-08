<?php defined('BASEPATH') or define('BASEPATH', __DIR__);
$root = dirname(__DIR__, 3) . '/tmp_r301';
foreach (glob($root . '/*.html') as $file) {
    if (preg_match('/Authorization\s*:\s*Bearer|APP_KEY\s*=|DB_PASSWORD\s*=|AIza[0-9A-Za-z_-]{20,}|SQLSTATE|PHP Warning/i', file_get_contents($file))) throw new RuntimeException(basename($file));
}
echo "DmsRenderedSecurityNoSecretsTest passed.\n";
