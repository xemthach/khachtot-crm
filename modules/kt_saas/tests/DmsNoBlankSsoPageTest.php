<?php
defined('BASEPATH') or define('BASEPATH', __DIR__);
$c=file_get_contents(dirname(__DIR__,2).'/kt_integration_hub/controllers/Kt_integration_sso.php');$v=file_get_contents(dirname(__DIR__,2).'/kt_integration_hub/views/admin/dms/sso_error.php');
foreach(['renderSsoFailure','data-r29-sso-error','support_code','retry_url','<!doctype html>']as$n)if(strpos($c.$v,$n)===false)throw new RuntimeException("Missing controlled SSO error: $n");
echo "DmsNoBlankSsoPageTest passed.\n";
