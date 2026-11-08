<?php
defined('BASEPATH') or define('BASEPATH', __DIR__);
$hub=file_get_contents(dirname(__DIR__,2).'/kt_integration_hub/controllers/Kt_integration_hub.php'); $view=file_get_contents(dirname(__DIR__).'/views/tenant/dms_order_status.php');
foreach(['DMS chưa sẵn sàng','kt_saas/dms_order_status','Chọn gói DMS','Liên hệ hỗ trợ'] as $needle){if(strpos($hub.$view,$needle)===false)throw new RuntimeException('Missing blocked UX marker: '.$needle);}
if(strpos($hub,'DMS la module mua them')!==false)throw new RuntimeException('Legacy unaccented access denial remains.');
echo "DmsAccessBlockedUxSmokeTest passed.\n";
