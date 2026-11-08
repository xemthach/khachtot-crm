<?php
defined('BASEPATH') or define('BASEPATH', __DIR__);
$files=[dirname(__DIR__).'/views/tenant/dms_order_status.php',dirname(__DIR__).'/views/dashboard/dms_order_detail.php',dirname(__DIR__,2).'/kt_integration_hub/views/admin/dms/sso_error.php'];foreach($files as$f){$s=file_get_contents($f);if(preg_match('/Ãƒ|Ã„|Ã¡Â»|TÃ¡Â»|KhÃƒ|HÃ¡Â»|CÃƒ/u',$s))throw new RuntimeException("Mojibake: $f");}
echo "DmsRuntimeLanguageNoMojibakeTest passed.\n";
