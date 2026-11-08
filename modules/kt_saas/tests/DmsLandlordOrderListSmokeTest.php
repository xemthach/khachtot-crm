<?php
defined('BASEPATH') or define('BASEPATH', __DIR__);
$controller=file_get_contents(dirname(__DIR__).'/controllers/Kt_saas.php'); $view=file_get_contents(dirname(__DIR__).'/views/dashboard/dms_orders.php'); $menu=file_get_contents(dirname(__DIR__,2).'/kt_integration_hub/kt_integration_hub.php');
foreach(['function dms_orders(','Đơn kích hoạt DMS','Chờ thanh toán','Cần xác minh','Đang kích hoạt','Đã sẵn sàng','kt_saas/dms_orders'] as $needle){if(strpos($controller.$view.$menu,$needle)===false)throw new RuntimeException('Missing landlord order list marker: '.$needle);}
echo "DmsLandlordOrderListSmokeTest passed.\n";
