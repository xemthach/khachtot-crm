<?php
defined('BASEPATH') or define('BASEPATH', __DIR__);
$sources=[dirname(__DIR__).'/views/tenant/dms_order_status.php',dirname(__DIR__).'/views/dashboard/dms_orders.php',dirname(__DIR__).'/views/dashboard/dms_order_detail.php']; $text=''; foreach($sources as $file){$source=file_get_contents($file);$source=preg_replace('/<\?php[\s\S]*?\?>/',' ',$source);$text.=' '.html_entity_decode(strip_tags($source),ENT_QUOTES,'UTF-8');}
foreach(['DMS access denied','Payment pending','Runtime blocked','Bridge not ready','Staff access pending','Not installed','PAYMENT_PENDING','PROVISIONING','Open DMS blocked','Job Type','Commercial Model','Lifecycle summary'] as $needle){if(stripos($text,$needle)!==false)throw new RuntimeException('Banned customer copy remains: '.$needle);}
if(preg_match('/Ã[\x80-\xBF]|Ä[\x80-\xBF]|á[º»]|Tá»|KhÃ|Há»|CÃ|�/u',$text))throw new RuntimeException('Mojibake remains in DMS order views.');
echo "DmsOrderLanguageNoMojibakeTest passed.\n";
