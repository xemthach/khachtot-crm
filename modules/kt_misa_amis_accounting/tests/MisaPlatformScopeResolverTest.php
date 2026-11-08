<?php

$source = file_get_contents(dirname(__DIR__) . '/controllers/Kt_misa_amis_accounting.php');
if (strpos($source, "\$tenant['crm_company_id'] ?? \$tenant['landlord_client_id']") === false) throw new RuntimeException('MISA trusted company context adapter missing.');
if (strpos($source, "input->get('company_id')") !== false) throw new RuntimeException('MISA still trusts posted/query company scope.');
echo "MisaPlatformScopeResolverTest passed; trusted_scope_only=pass.\n";
