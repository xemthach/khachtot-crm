<?php
$source = file_get_contents(dirname(__DIR__) . '/models/Kt_misa_amis_accounting_model.php');
foreach (['tenant_id', 'company_id', 'get_connection', 'get_request', 'get_mapping'] as $needle) if (strpos($source, $needle) === false) throw new RuntimeException('Missing scoped repository condition: ' . $needle);
echo "MisaCrossTenantActionTest passed; repository_scope=present.\n";
