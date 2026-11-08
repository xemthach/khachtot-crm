<?php
defined('BASEPATH') or define('BASEPATH', __DIR__);
$c=file_get_contents(dirname(__DIR__,2).'/kt_integration_hub/controllers/Kt_integration_sso.php');$v=file_get_contents(dirname(__DIR__,2).'/kt_integration_hub/views/admin/dms/sso_error.php');
foreach(['redirectForCommercialState','Bạn chưa kích hoạt gói DMS','Gói DMS đang chờ thanh toán','Workspace DMS đang được kích hoạt','Chưa thể mở Khách Tốt DMS','Thử lại','Xem trạng thái DMS','Liên hệ hỗ trợ'] as $n)if(strpos($c.$v,$n)===false)throw new RuntimeException("Missing SSO hardening marker: $n");
echo "DmsSsoOpenDmsHardeningTest passed.\n";
