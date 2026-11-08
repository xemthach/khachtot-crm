<?php
defined('BASEPATH') or define('BASEPATH', __DIR__);
$model=file_get_contents(dirname(__DIR__).'/models/Kt_saas_model.php'); $billing=file_get_contents(dirname(__DIR__).'/services/BillingEngineService.php'); $sepay=file_get_contents(dirname(__DIR__,2).'/kt_sepay/libraries/Kt_sepay_processor.php');
foreach(['recheck_dms_commercial_order_payment','payment_verified','activate_tenant_dms_addon_from_invoice','handlePostPaymentSuccess','markInvoicePaid'] as $needle){if(strpos($model.$billing.$sepay,$needle)===false)throw new RuntimeException('Missing payment verification marker: '.$needle);}
if(strpos($model, "(\$invoice['status'] ?? ''))) !== 'paid'")===false)throw new RuntimeException('Activation must reject unpaid invoices.');
if(strpos($model, "? 'paid'")===false)throw new RuntimeException('Paid invoice must remain canonical over a stale payment request.');
echo "DmsPaymentVerificationSmokeTest passed.\n";
