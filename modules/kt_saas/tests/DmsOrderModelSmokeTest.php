<?php
defined('BASEPATH') or define('BASEPATH', __DIR__);
$install=file_get_contents(dirname(__DIR__).'/install.php'); $model=file_get_contents(dirname(__DIR__).'/models/Kt_saas_model.php');
foreach(['kt_saas_dms_orders','kt_saas_dms_order_events','idempotency_key','payment_status','verification_status','subscription_status','provisioning_status','workspace_status','activated_at','cancelled_at'] as $needle){if(strpos($install.$model,$needle)===false)throw new RuntimeException('Missing DMS order model marker: '.$needle);}
foreach(['ensure_dms_commercial_order_schema','create_dms_commercial_order','get_dms_commercial_order','append_dms_order_event'] as $method){if(strpos($model,'function '.$method.'(')===false)throw new RuntimeException('Missing DMS order method: '.$method);}
echo "DmsOrderModelSmokeTest passed.\n";
