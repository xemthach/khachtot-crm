<?php
defined('BASEPATH') or define('BASEPATH', __DIR__);
$files=[dirname(__DIR__).'/views/tenant/dms_order_status.php',dirname(__DIR__).'/views/dashboard/dms_order_detail.php',dirname(__DIR__,2).'/kt_integration_hub/views/admin/dms/sso_error.php'];$s='';foreach($files as$f)$s.=file_get_contents($f);foreach(['APP_KEY','DB_PASSWORD','webhook_secret_encrypted','access_token','raw payment provider payload']as$n)if(stripos($s,$n)!==false)throw new RuntimeException("Sensitive marker in view: $n");
echo "DmsRuntimeRenderedSecurityTest passed.\n";
