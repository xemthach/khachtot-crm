<?php
defined('BASEPATH') or define('BASEPATH', __DIR__);
$controller=file_get_contents(dirname(__DIR__).'/controllers/Kt_saas.php'); $view=file_get_contents(dirname(__DIR__).'/views/dashboard/dms_order_detail.php'); $model=file_get_contents(dirname(__DIR__).'/models/Kt_saas_model.php');
foreach(['function dms_order(','function dms_order_action(','Lịch sử sự kiện','recheck_payment','verify_payment','activate_subscription','retry_provisioning','retry_staff_sync','cancel_order','package_selected','invoice_created','payment_verified','subscription_activated','workspace_provision_started'] as $needle){if(strpos($controller.$view.$model,$needle)===false)throw new RuntimeException('Missing order timeline/action marker: '.$needle);}
echo "DmsLandlordOrderDetailTimelineTest passed.\n";
