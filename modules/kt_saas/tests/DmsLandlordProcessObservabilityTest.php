<?php
defined('BASEPATH') or define('BASEPATH', __DIR__);
$a=file_get_contents(dirname(__DIR__).'/views/dashboard/dms_order_detail.php');$b=file_get_contents(dirname(__DIR__,2).'/kt_integration_hub/views/admin/dms_landlord/tenant_detail.php');
foreach(['Tình trạng tiến trình kích hoạt','Cron/worker','Khởi tạo workspace','Đồng bộ nhân sự','Lỗi gần nhất']as$n)if(strpos($a.$b,$n)===false)throw new RuntimeException("Missing observability marker: $n");
echo "DmsLandlordProcessObservabilityTest passed.\n";
