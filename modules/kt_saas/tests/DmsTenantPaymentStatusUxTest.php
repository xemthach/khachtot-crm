<?php
defined('BASEPATH') or define('BASEPATH', __DIR__);
$v=file_get_contents(dirname(__DIR__).'/views/tenant/dms_order_status.php').file_get_contents(dirname(__DIR__).'/models/Kt_saas_model.php');
foreach(['Chờ thanh toán','Tiếp tục thanh toán','Xem tiến độ kích hoạt','Workspace DMS đã sẵn sàng','Mở DMS','Thử lại','Liên hệ hỗ trợ']as$n)if(strpos($v,$n)===false)throw new RuntimeException("Missing tenant status marker: $n");
echo "DmsTenantPaymentStatusUxTest passed.\n";
