<?php defined('BASEPATH') or define('BASEPATH', __DIR__);
$source = file_get_contents(dirname(__DIR__) . '/controllers/Kt_integration_sso.php');
foreach (['Kết nối DMS chưa hoàn tất', 'Cấu hình kết nối DMS', "'support_code' => 'SSO-'"] as $contract) if (strpos($source, $contract) === false) throw new RuntimeException($contract);
echo "DmsSsoConnectionNotConfiguredFixTest passed.\n";
