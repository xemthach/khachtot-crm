<?php

$root = dirname(__DIR__, 3);
$model = file_get_contents($root . '/application/models/Qa_auth_model.php');
$controller = file_get_contents($root . '/application/controllers/admin/Qa_auth.php');
$cli = file_get_contents($root . '/application/controllers/Kt_saas_cli.php');
$index = file_get_contents($root . '/index.php');

$required = [
    "hash('sha256', \$rawToken)",
    'MAX_TTL_SECONDS = 600',
    "'used_at' =>",
    "'revoked_at' =>",
    "'audit_status' => 'used'",
    "'audit_status' => 'revoked'",
    "ENVIRONMENT') ? (string) ENVIRONMENT",
    "=== 'production'",
    'qa_auth_create',
    'qa_auth_revoke',
    'qa_auth_cleanup',
    '/admin/qa-auth/redeem',
    'staff_logged_in',
];

$source = $model . $controller . $cli . $index;
foreach ($required as $needle) {
    if (strpos($source, $needle) === false) {
        throw new RuntimeException('Missing CRM QA auth security marker: ' . $needle);
    }
}

if (strpos($model, "'token_hash' => \$rawToken") !== false) {
    throw new RuntimeException('CRM QA auth stores a raw token.');
}

echo "DmsQaAuthBootstrapSecurityTest PASS\n";
