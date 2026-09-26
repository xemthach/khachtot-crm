<?php
// Read-only CLI deployment gate. Never boot the application or read secret values.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$checks = ['php_8_1_or_newer' => PHP_VERSION_ID >= 80100];
foreach (['mysqli','pdo','mbstring','curl','openssl','zip','gd','fileinfo','xml','dom','simplexml','json','imap','bcmath','ctype','filter','hash','xmlwriter'] as $extension) {
    $checks['extension:' . $extension] = extension_loaded($extension);
}
$checks['iconv'] = extension_loaded('iconv') || function_exists('iconv');
$checks['allow_url_fopen'] = filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN);
foreach (['application', 'modules/backup', 'modules/einvoice', 'modules/openai', 'modules/surveys'] as $path) {
    $checks['autoload:' . $path] = is_file($root . '/' . $path . '/vendor/autoload.php');
}
foreach (['index.php', 'application/config/database.php', 'application/config/config.php', 'application/config/app-config.sample.php'] as $path) {
    $checks['file:' . $path] = is_file($root . '/' . $path);
}
foreach ($checks as $name => $ok) echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
echo 'No database connection, migration or provider request was performed.' . PHP_EOL;
exit(in_array(false, $checks, true) ? 1 : 0);
