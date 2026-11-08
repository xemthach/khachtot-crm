<?php

$root = dirname(__DIR__);
$install = file_get_contents($root . '/install.php');
foreach (['kt_misa_connections','kt_misa_requests','kt_misa_request_items','kt_misa_callback_inbox','kt_misa_mappings','kt_misa_pull_cursors','kt_misa_attempts','kt_misa_audit_logs'] as $table) {
    if (strpos($install, $table) === false) throw new RuntimeException('Missing table: ' . $table);
}
if (substr_count($install, '`tenant_id`') < 8 || substr_count($install, '`company_id`') < 8) throw new RuntimeException('Scope columns missing from additive schema.');
foreach (['kt_misa_module_enabled\' => \'0', 'kt_misa_external_calls_enabled\' => \'0', 'kt_misa_callback_enabled\' => \'0', 'kt_misa_master_data_sync_enabled\' => \'0', 'kt_misa_dry_run_enabled\' => \'1'] as $needle) {
    if (strpos($install, $needle) === false) throw new RuntimeException('Feature flag default missing.');
}
$bootstrap = file_get_contents($root . '/kt_misa_amis_accounting.php');
if (preg_match('/kt_misa_module_enabled\s*[^\n]*true/i', $bootstrap)) throw new RuntimeException('Module must not default enabled.');
foreach (glob($root . '/fixtures/*.json') as $fixture) {
    $raw = file_get_contents($fixture);
    if (preg_match('/(-----BEGIN|AKIA[0-9A-Z]{16}|real-token|real-access-code)/i', $raw)) throw new RuntimeException('Secret-like fixture found: ' . basename($fixture));
}
echo "MisaSecurityStaticTest passed.\n";
