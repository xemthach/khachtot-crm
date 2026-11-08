<?php
defined('BASEPATH') or define('BASEPATH', __DIR__);
$billing=file_get_contents(dirname(__DIR__).'/services/BillingEngineService.php');$model=file_get_contents(dirname(__DIR__).'/models/Kt_saas_model.php');$processor=file_get_contents(dirname(__DIR__,2).'/kt_sepay/libraries/Kt_sepay_processor.php');
foreach(['markInvoicePaid','get_payment_by_reference','Payment amount does not match','Payment currency does not match','recheck_dms_commercial_order_payment',"? 'paid'"] as $n)if(strpos($billing.$model.$processor,$n)===false)throw new RuntimeException("Missing callback invariant: $n");
echo "DmsPaymentCallbackRecheckTest passed.\n";
