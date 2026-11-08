<?php

$root = dirname(__DIR__, 3);
$routes = file_get_contents($root . '/application/config/my_routes.php');
foreach (['misa/callback', 'admin/kt_misa_amis_accounting', 'admin/kt_misa_amis_accounting/connections', 'admin/kt_misa_amis_accounting/mappings', 'admin/kt_misa_amis_accounting/requests'] as $uri) {
    if (substr_count($routes, "'$uri'") !== 1) throw new RuntimeException('Expected one authoritative global route: ' . $uri);
}
if (substr_count($routes, "'misa/callback'") !== 1) throw new RuntimeException('Callback route duplicated.');
foreach (['Kt_misa_callback.php', 'Kt_misa_amis_accounting.php'] as $file) {
    if (!is_file(dirname(__DIR__) . '/controllers/' . $file)) throw new RuntimeException('Missing controller file: ' . $file);
}
if (is_file(dirname(__DIR__) . '/config/routes.php')) throw new RuntimeException('MISA module-local routes must not duplicate global routes.');
echo "MisaRouteContractTest passed.\n";
