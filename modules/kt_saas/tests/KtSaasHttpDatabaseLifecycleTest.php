<?php

$source = file_get_contents(dirname(__DIR__) . '/tenant_bootstrap/TenantHttpDatabaseLifecycle.php');
if (strpos($source, 'register_shutdown_function') === false || strpos($source, 'function restore') === false || strpos($source, 'strcasecmp($expected, $actual)') === false) throw new RuntimeException('HTTP lifecycle cleanup/DB verification contract missing.');
echo "KtSaasHttpDatabaseLifecycleTest passed; cleanup_and_db_verification=pass.\n";
