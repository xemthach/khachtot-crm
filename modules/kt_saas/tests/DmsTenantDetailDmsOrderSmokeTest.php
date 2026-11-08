<?php
defined('BASEPATH') or define('BASEPATH', __DIR__);
$model=file_get_contents(dirname(__DIR__,2).'/kt_integration_hub/models/Kt_integration_model.php'); $view=file_get_contents(dirname(__DIR__,2).'/kt_integration_hub/views/admin/dms_landlord/tenant_detail.php');
foreach(['latest_dms_order','Đơn DMS gần nhất & tiến trình kích hoạt','Thanh toán','Xác minh','Khởi tạo','Workspace'] as $needle){if(strpos($model.$view,$needle)===false)throw new RuntimeException('Missing tenant DMS order summary: '.$needle);}
echo "DmsTenantDetailDmsOrderSmokeTest passed.\n";
