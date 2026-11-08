<?php defined('BASEPATH') or define('BASEPATH', __DIR__);
$source = file_get_contents(dirname(__DIR__) . '/models/Kt_integration_model.php');
foreach (['get_salesflow_connection_status', 'provision_salesflow_connection', 'test_salesflow_connection', 'last_successful_handshake', 'secret_configured'] as $contract) if (strpos($source, $contract) === false) throw new RuntimeException($contract);
echo "DmsSalesrepConnectionModelTest passed.\n";
