<?php
defined('BASEPATH') or define('BASEPATH', __DIR__);
$sources=[dirname(__DIR__).'/views/tenant/dms_order_status.php',dirname(__DIR__).'/views/dashboard/dms_orders.php',dirname(__DIR__).'/views/dashboard/dms_order_detail.php']; $text=''; foreach($sources as $file)$text.=file_get_contents($file);
foreach(['APP_KEY','DB_PASSWORD','MAIL_PASSWORD','webhook_secret_encrypted','api_token_encrypted','gateway_payload_json','raw_payload','raw entitlement'] as $needle){if(stripos($text,$needle)!==false)throw new RuntimeException('Sensitive field appears in DMS order views: '.$needle);}
echo "DmsOrderRenderedSecurityTest passed.\n";
