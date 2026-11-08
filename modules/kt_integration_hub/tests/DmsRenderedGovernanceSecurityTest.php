<?php defined('BASEPATH') or define('BASEPATH', __DIR__);
$files = array_merge(glob(dirname(__DIR__, 4) . '/R31_*.md') ?: [], glob(dirname(__DIR__, 4) . '/R31_*.json') ?: []);
$patterns = ['/Authorization\s*:\s*Bearer/i','/APP_KEY\s*=/i','/DB_PASSWORD\s*=/i','/MAIL_PASSWORD\s*=/i','/bridge secret/i','/Google Maps raw key/i','/SSO token/i','/raw config payload/i','/raw tenant payload/i'];
foreach ($files as $file) {
    $text = file_get_contents($file);
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text)) throw new RuntimeException('Sensitive pattern in ' . basename($file) . ': ' . $pattern);
    }
}
echo "DmsRenderedGovernanceSecurityTest passed.\n";
