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

if (!function_exists('kt_dms_ui_status_label')) {
    function kt_dms_ui_status_label($domain, $status)
    {
        return (string) $status;
    }
}

if (!function_exists('form_open')) {
    function form_open($action = '', $attributes = [])
    {
        return '<form method="post" action="'.e($action).'">';
    }
}

if (!function_exists('form_close')) {
    function form_close()
    {
        return '</form>';
    }
}

$view = dirname(__DIR__) . '/views/admin/dms/setup_center.php';
$page_heading = 'Setup & Access';
$setup_center = [
    'active_tab' => 'users',
    'tabs' => [
        'status' => 'Status',
        'users' => 'Users & Access',
        'capabilities' => 'Capabilities',
        'operations' => 'Operations',
        'support' => 'Support',
    ],
    'eligibility' => [
        'eligible' => true,
        'title' => 'DMS add-on is active for this tenant',
        'message' => 'Marketplace owns package activation.',
    ],
    'status' => [
        'billing_cycle' => 'YEARLY',
        'workspace_host' => 'co-dms.salesrep.test',
        'workspace_url' => 'https://co-dms.salesrep.test',
        'dns_status' => 'Platform-managed (.test local runtime)',
        'ssl_status' => 'Platform-managed (.test local runtime)',
        'catalog_sync_ready' => false,
        'catalog_sync_summary' => 'No successful customer/store or product sync signal is available yet.',
        'settings_ready' => false,
        'settings_summary' => 'No source-backed operational settings have been audited into CRM yet.',
    ],
    'users' => [
        'ready' => false,
        'provisioned_count' => 0,
        'message' => 'No successful staff provisioning signal is available yet.',
        'reprovision_url' => 'admin.php/kt_integration_hub/dms_run_staff_reprovision',
    ],
    'capabilities' => [],
    'operations' => [],
    'actions' => [
        'open_dms_mode' => 'sso',
        'open_dms_url' => 'https://salesrep.test/sso/login',
        'overview_url' => 'admin.php/kt_integration_hub/dms_overview',
    ],
    'support' => [
        'message' => 'Contact the tenant administrator.',
        'internal_url' => null,
    ],
];

ob_start();
include $view;
$html = ob_get_clean();

foreach (['Gói DMS &amp; Workspace', 'Quyền truy cập nhân viên', 'Cấu trúc quản lý', 'Đồng bộ điểm bán / sản phẩm', 'Kiểm tra hoạt động đầu tiên', 'Kiểm tra sức khỏe'] as $step) {
    if (strpos($html, $step) === false) {
        throw new RuntimeException('Missing guided setup step: ' . $step);
    }
}

if (strpos($html, 'Đồng bộ nhân sự') === false) {
    throw new RuntimeException('Expected staff sync action for an unprovisioned tenant.');
}

foreach (['settings_json', 'bridge_secret', 'payload_json', 'Advanced / Internal Diagnostics'] as $forbidden) {
    if (strpos($html, $forbidden) !== false) {
        throw new RuntimeException('Tenant Setup Center exposed internal content: ' . $forbidden);
    }
}

$setup_center['active_tab'] = 'status';
$setup_center['eligibility'] = [
    'eligible' => false,
    'title' => 'DMS add-on has not been activated',
    'message' => 'Choose a DMS package in Marketplace first.',
];
$setup_center['users']['reprovision_url'] = null;
$setup_center['actions'] = [
    'open_dms_mode' => 'disabled',
    'open_dms_url' => null,
    'overview_url' => null,
];

ob_start();
include $view;
$disabledHtml = ob_get_clean();

if (strpos($disabledHtml, 'DMS add-on has not been activated') === false) {
    throw new RuntimeException('Expected controlled eligibility blocker for a disabled tenant.');
}

if (strpos($disabledHtml, '>Open DMS<') !== false) {
    throw new RuntimeException('Disabled tenant must not receive an Open DMS action.');
}

echo "DmsSetupCenterUiSmokeTest passed.\n";
