<?php defined('BASEPATH') or define('BASEPATH', __DIR__);
$source = file_get_contents(dirname(__DIR__) . '/models/Kt_integration_model.php') . file_get_contents(dirname(__DIR__) . '/views/admin/dms_landlord/dashboard.php');
foreach (['Tác vụ đồng bộ chưa thành công', 'Kiểm tra kết nối và thử lại', '$display'] as $contract) if (strpos($source, $contract) === false) throw new RuntimeException($contract);
echo "DmsLandlordErrorRootCauseTest passed.\n";
