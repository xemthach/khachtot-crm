<?php
defined('BASEPATH') or define('BASEPATH', __DIR__);
$controller=file_get_contents(dirname(__DIR__).'/controllers/Kt_saas.php'); $view=file_get_contents(dirname(__DIR__).'/views/tenant/dms_order_status.php');
foreach(['function dms_order_status(','Trạng thái đơn DMS','Gói DMS đã chọn','Trạng thái thanh toán','Mã thanh toán','Tiếp tục thanh toán','Quay lại chọn gói','Mở DMS'] as $needle){if(strpos($controller.$view,$needle)===false)throw new RuntimeException('Missing order status page marker: '.$needle);}
echo "DmsOrderStatusPageSmokeTest passed.\n";
