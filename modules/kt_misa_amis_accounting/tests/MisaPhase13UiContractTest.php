<?php
declare(strict_types=1);
$root = dirname(__DIR__, 3);
$routes = file_get_contents($root . '/application/config/my_routes.php');
$controller = file_get_contents(__DIR__ . '/../controllers/Kt_misa_amis_accounting.php');
$views = [__DIR__ . '/../views/readiness.php', __DIR__ . '/../views/external_test.php'];
foreach (['admin/misa-amis/readiness', 'admin/misa-amis/external-test'] as $route) if (strpos($routes, $route) === false) throw new RuntimeException('Missing Phase 13 route: ' . $route);
foreach (['function readiness', 'function external_test', 'external_transport_allowed'] as $needle) if (strpos($controller . file_get_contents(__DIR__ . '/../helpers/kt_misa_amis_accounting_helper.php'), $needle) === false) throw new RuntimeException('Missing Phase 13 guard contract: ' . $needle);
foreach ($views as $view) if (!is_file($view)) throw new RuntimeException('Missing Phase 13 view: ' . $view);
echo "MisaPhase13UiContractTest passed.\n";
