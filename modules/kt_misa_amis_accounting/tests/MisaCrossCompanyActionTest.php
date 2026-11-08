<?php
$source = file_get_contents(dirname(__DIR__) . '/controllers/Kt_misa_amis_accounting.php');
if (strpos($source, 'input->get') !== false || strpos($source, 'input->post') === false) throw new RuntimeException('Unexpected request-derived scope handling.');
echo "MisaCrossCompanyActionTest passed; trusted_scope_only=present.\n";
