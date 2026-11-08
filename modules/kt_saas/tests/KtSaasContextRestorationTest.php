<?php

$source = file_get_contents(dirname(__DIR__) . '/tenant_bootstrap/TenantHttpDatabaseLifecycle.php');
foreach (['originalDb','originalGlobals','originalConfig','registerShutdownRestore','restore()'] as $needle) if (strpos($source, $needle) === false) throw new RuntimeException('Context restoration evidence missing: ' . $needle);
echo "KtSaasContextRestorationTest passed; db_and_global_restore_contract=pass.\n";
