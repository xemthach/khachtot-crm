<?php defined('BASEPATH') or define('BASEPATH', __DIR__);
$source = file_get_contents(dirname(__DIR__) . '/models/Kt_integration_model.php') . file_get_contents(dirname(__DIR__) . '/controllers/Kt_integration_hub.php');
foreach (['validate_dms_global_config', 'missing_allowed_domains', 'failed_count', 'dms_control_center_validate'] as $contract) if (strpos($source, $contract) === false) throw new RuntimeException($contract);
echo "DmsGlobalConfigValidatorApplyAllTest passed.\n";
