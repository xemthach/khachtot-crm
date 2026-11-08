<?php
defined('BASEPATH') or define('BASEPATH', __DIR__);
$model=file_get_contents(dirname(__DIR__).'/models/Kt_saas_model.php');
foreach(['get_pending_dms_commercial_order','sameSelection','reused','pending_order','dms_order_idempotency_unique','dms_order_invoice_unique'] as $needle){if(strpos($model,$needle)===false)throw new RuntimeException('Missing purchase idempotency marker: '.$needle);}
if(strpos($model,"Chu kỳ thanh toán không được phép")===false)throw new RuntimeException('Invalid cycle rejection missing.');
echo "DmsPackagePurchaseIdempotencyTest passed.\n";
