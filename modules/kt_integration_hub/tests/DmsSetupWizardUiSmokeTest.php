<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

if (!function_exists('init_head')) {
    function init_head()
    {
    }
}

if (!function_exists('init_tail')) {
    function init_tail()
    {
    }
}

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('admin_url')) {
    function admin_url($uri = '')
    {
        return 'admin.php/' . ltrim((string) $uri, '/');
    }
}

if (!function_exists('_l')) {
    function _l($key)
    {
        return $key;
    }
}

$wizardView = dirname(__DIR__) . '/views/admin/dms/setup_wizard.php';
$overviewView = dirname(__DIR__) . '/views/admin/dms/overview.php';

$tenant_settings = ['tenant_code' => 'ABC', 'plan' => 'SME_PLUS'];
$actions = [
    'open_dms_mode' => 'sso',
    'open_dms_url' => 'http://salesrep.test/sso/login',
    'setup_wizard_url' => 'admin.php/kt_integration_hub/dms_setup_wizard',
    'reprovision_staff_url' => 'admin.php/kt_integration_hub/dms_run_staff_reprovision',
];
$readiness = [
    'label' => 'Ready',
    'summary' => 'Ready for first field activity.',
    'dms_enabled' => true,
    'provisioned_users' => 1,
    'first_checkin_exists' => false,
    'first_visit_exists' => false,
    'tracking_exists' => false,
];
$open_dms_experience = [
    'mode' => 'SSO',
    'summary' => 'SSO route exists.',
    'fallback' => 'Fallback not required.',
];
$workspace = [
    'tenant_subdomain' => 'co-dms',
    'host' => 'co-dms.salesrep.test',
    'workspace_url' => 'https://co-dms.salesrep.test',
    'dns_status' => 'Platform-managed (.test local runtime)',
    'ssl_status' => 'Platform-managed (.test local runtime)',
    'message' => 'Workspace host is derived automatically.',
];

$onboarding = [
    'title' => 'Getting Started With DMS',
    'hide' => false,
    'progress_label' => '2/7',
    'progress_percent' => 28,
    'summary' => 'Provisioning still in progress.',
    'steps' => [
        [
            'index' => 2,
            'title' => 'Choose DMS workspace subdomain',
            'done' => false,
            'summary' => 'Verify the tenant workspace host before go-live.',
            'checks' => [
                ['label' => 'DMS workspace host is derived', 'done' => false, 'detail' => 'co-dms.salesrep.test'],
            ],
            'cta' => [
                'label' => 'Return to Marketplace',
                'url' => 'admin.php/kt_saas/tenant_marketplace_app/salesflow-dms',
                'style' => 'default',
            ],
        ],
    ],
];

ob_start();
include $wizardView;
$wizardHtml = ob_get_clean();

if (strpos($wizardHtml, 'Thiết lập DMS cho đội sales thị trường') === false) {
    throw new RuntimeException('Expected localized onboarding wizard heading to render.');
}

foreach ([
    'Choose DMS workspace subdomain',
    'Tiến độ thiết lập',
    'Chi tiết kỹ thuật',
] as $needle) {
    if (strpos($wizardHtml, $needle) === false) {
        throw new RuntimeException('Expected wizard onboarding content to render: ' . $needle);
    }
}

$onboarding = [
    'title' => 'Getting Started With DMS',
    'hide' => true,
    'progress_label' => '5/5',
    'progress_percent' => 100,
    'summary' => 'Tenant is already active.',
    'steps' => [],
];
$readiness['summary'] = 'Active tenant.';

ob_start();
include $wizardView;
$activeWizardHtml = ob_get_clean();

if (strpos($activeWizardHtml, 'Thiết lập DMS cho đội sales thị trường') === false) {
    throw new RuntimeException('Expected active wizard state to keep the localized readiness surface.');
}

echo "DmsSetupWizardUiSmokeTest passed.\n";
