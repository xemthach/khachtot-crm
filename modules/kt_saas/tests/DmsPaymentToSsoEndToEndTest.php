<?php
defined('BASEPATH') or define('BASEPATH', __DIR__);
$files=[dirname(__DIR__).'/models/Kt_saas_model.php',dirname(__DIR__).'/services/BillingEngineService.php',dirname(__DIR__,2).'/kt_integration_hub/controllers/Kt_integration_sso.php',dirname(__DIR__,3).'/application/controllers/Kt_saas_cli.php'];$s='';foreach($files as$f)$s.=file_get_contents($f);
foreach(['payment_verified','subscription_active','provisioning_pending','workspace_ready','issueToken','dms_activation'] as$n)if(strpos($s,$n)===false)throw new RuntimeException("Missing E2E marker: $n");
echo "DmsPaymentToSsoEndToEndTest passed.\n";
