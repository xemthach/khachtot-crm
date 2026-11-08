<?php

$landingRoot = dirname(__DIR__);
$tenantView = file_get_contents(dirname($landingRoot) . '/kt_saas/views/tenant/workspace_home.php');
$loginView = file_get_contents($landingRoot . '/views/public/login_entry.php');

foreach ([$tenantView, $loginView] as $view) {
    if (strpos($view, 'noindex, nofollow, noarchive') === false) {
        throw new RuntimeException('Tenant/login entry must be noindex.');
    }
    if (preg_match('/Authorization|Bearer|X-Signature|DB_PASSWORD|APP_KEY\s*=/i', $view)) {
        throw new RuntimeException('Sensitive configuration leaked in entry view source.');
    }
}

echo "TenantEntryPageSmokeTest passed.\n";
