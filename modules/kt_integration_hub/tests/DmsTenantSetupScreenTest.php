<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

require_once dirname(__DIR__) . '/helpers/kt_integration_hub_helper.php';

foreach (['init_head', 'init_tail'] as $function) {
    if (!function_exists($function)) {
        eval('function ' . $function . '() {}');
    }
}
if (!function_exists('e')) {
    function e($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('admin_url')) {
    function admin_url($uri = '') { return 'admin.php/' . ltrim((string) $uri, '/'); }
}

$page_heading = 'Thiết lập đội DMS';
$setup_center = [
    'eligibility' => ['eligible' => true, 'title' => 'Gói DMS đang hoạt động', 'message' => 'Workspace sẵn sàng.'],
    'status' => [
        'package_name' => 'DMS Pilot', 'subscription_status' => 'ACTIVE',
        'workspace_host' => 'tenant-a.salesrep.test', 'catalog_sync_ready' => true,
        'first_activity' => true, 'quota_rows' => [
            ['label' => 'User limit', 'value' => 10], ['label' => 'Store limit', 'value' => 100],
        ],
    ],
    'users' => [
        'ready' => true, 'rows' => [], 'used_count' => 4, 'limit' => 10,
        'role_counts' => ['tenant_admin' => 1, 'manager' => 1, 'field_sales' => 1, 'finance' => 1],
        'manager_candidates' => [],
    ],
    'capabilities' => [
        ['label' => 'Live tracking', 'enabled' => true],
        ['label' => 'Route analytics', 'enabled' => false],
    ],
    'sync_status' => [], 'actions' => ['open_dms_url' => 'https://tenant-a.salesrep.test', 'open_dms_mode' => 'sso'],
];

ob_start();
include dirname(__DIR__) . '/views/admin/dms/setup_center.php';
$html = ob_get_clean();

foreach (['Danh sách chuẩn bị vận hành', 'Quản trị workspace', 'Quản lý sales', 'Nhân viên thị trường', 'Tài chính', 'Hạn mức & tính năng đang được cấp', 'Không được cấp'] as $label) {
    if (strpos($html, $label) === false) {
        throw new RuntimeException('Missing tenant setup label: ' . $label);
    }
}
foreach (['bridge_secret', 'payload_json', 'Authorization', 'Bearer', 'package mutation'] as $forbidden) {
    if (strpos($html, $forbidden) !== false) {
        throw new RuntimeException('Tenant setup exposed internal detail: ' . $forbidden);
    }
}

echo "DmsTenantSetupScreenTest passed.\n";
