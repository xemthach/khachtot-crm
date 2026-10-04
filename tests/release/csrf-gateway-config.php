<?php
// Executes the actual tracked config section with synthetic URI, no CRM/DB bootstrap.
$source = file_get_contents(__DIR__ . '/../../application/config/config.php');
$start = strpos($source, "$" . "config['csrf_protection']   =");
$end = strpos($source, "\n/*", $start);
if ($start === false || $end === false) { throw new Exception('config section not found'); }
$section = substr($source, $start, $end - $start);
define('APP_CSRF_PROTECTION', true);
foreach ([
    '/admin/clients/client?next=gateways/anything' => true,
    '/admin/clients/client' => true,
    '/gateways/example' => false,
    '/index.php/gateways/example' => false,
    '/admin/gateways/example' => true,
] as $uri => $expected) {
    $_SERVER['REQUEST_URI'] = $uri;
    $config = [];
    eval($section);
    if ($config['csrf_protection'] !== $expected) {
        throw new Exception('CSRF state mismatch for ' . $uri);
    }
}
echo "PASS 5 actual config URI cases; installed HTTP CSRF NOT VERIFIED\n";
