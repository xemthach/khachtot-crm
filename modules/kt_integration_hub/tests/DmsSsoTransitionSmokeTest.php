<?php

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/controllers/Kt_integration_hub.php');
$ssoController = file_get_contents($root . '/controllers/Kt_integration_sso.php');
$transition = file_get_contents($root . '/views/admin/dms/sso_transition.php');
$error = file_get_contents($root . '/views/admin/dms/sso_error.php');
$salesSso = file_get_contents(dirname($root, 3) . '/salesrep/app/Http/Controllers/SsoLoginController.php');

foreach (['data-r25-sso-transition', 'Thử lại', 'Đăng nhập DMS', 'Mã hỗ trợ'] as $label) {
    if (strpos($transition, $label) === false) throw new RuntimeException('Missing SSO transition UX: ' . $label);
}
foreach (['data-r25-sso-error', 'renderSsoFailure', 'Kiểm tra thiết lập DMS'] as $label) {
    if (strpos($error . $ssoController, $label) === false) throw new RuntimeException('Missing SSO failure UX: ' . $label);
}
if (strpos($salesSso, 'primeTenantRuntimeState') !== false) {
    throw new RuntimeException('SSO still performs redundant synchronous priming.');
}
if (strpos($controller, "renderDmsPage('sso_transition'") === false) {
    throw new RuntimeException('Open DMS does not use branded transition.');
}

echo "DmsSsoTransitionSmokeTest passed.\n";
