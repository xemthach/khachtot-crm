<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
 * Module Name: KT eInvoice
 * Description: Phát hành hóa đơn điện tử qua SePay eInvoice API cho tenant
 * Version: 1.0.0
 * Requires at least: 3.0.0
 */

require_once __DIR__ . '/config/kt_einvoice_config.php';

define('KT_EINVOICE_MODULE', 'kt_einvoice');

register_activation_hook(KT_EINVOICE_MODULE, 'kt_einvoice_module_activation_hook');
register_uninstall_hook(KT_EINVOICE_MODULE, 'kt_einvoice_module_uninstall_hook');

function kt_einvoice_module_activation_hook(): void
{
    require_once __DIR__ . '/install.php';
}

function kt_einvoice_module_uninstall_hook(): void
{
    require_once __DIR__ . '/uninstall.php';
    kt_einvoice_run_uninstall();
}

function kt_einvoice_maybe_install_schema(): void
{
    if (!function_exists('get_option')) {
        return;
    }

    $CI = &get_instance();
    if (isset($CI->db) && !$CI->db->table_exists(db_prefix() . 'kt_einvoice_plan_features')) {
        require_once __DIR__ . '/install.php';
        kt_einvoice_run_install();
        return;
    }

    $current = (string) get_option('kt_einvoice_schema_version');
    if ($current !== '') {
        return;
    }

    require_once __DIR__ . '/install.php';
    kt_einvoice_run_install();
}

// ── Module Action Links ───────────────────────────────────────────────────────
hooks()->add_filter('module_kt_einvoice_action_links', 'kt_einvoice_action_links');
function kt_einvoice_action_links(array $actions): array
{
    if (function_exists('kt_saas_is_tenant_runtime') && kt_saas_is_tenant_runtime()) {
        $actions[] = '<a href="' . admin_url('kt_einvoice/settings') . '">' . _l('settings') . '</a>';
    } else {
        $actions[] = '<a href="' . admin_url('kt_einvoice/admin/overview') . '">' . _l('settings') . '</a>';
    }
    return $actions;
}

// ── Admin Init (Menu + Capabilities) ─────────────────────────────────────────
hooks()->add_action('admin_init', 'kt_einvoice_module_init');
function kt_einvoice_module_init(): void
{
    $CI = &get_instance();
    $CI->load->language('kt_einvoice/vietnamese/kt_einvoice');
    kt_einvoice_maybe_install_schema();

    // ── Staff Capabilities ────────────────────────────────────────────────────
    register_staff_capabilities(
        'kt_einvoice',
        [
            'capabilities' => [
                'view'         => _l('kt_einvoice_perm_view'),
                'create'       => _l('kt_einvoice_perm_create'),
                'issue'        => _l('kt_einvoice_perm_issue'),
                'delete'       => _l('kt_einvoice_perm_delete'),
                'download'     => _l('kt_einvoice_perm_download'),
                'batch_issue'  => _l('kt_einvoice_perm_batch_issue'),
                'cancel'       => _l('kt_einvoice_perm_cancel'),
                'configure'    => _l('kt_einvoice_perm_configure'),
                'view_reports' => _l('kt_einvoice_perm_view_reports'),
            ],
        ],
        _l('kt_einvoice')
    );

    if (!is_admin()) {
        return;
    }

    // CHECK RUNTIME CONTEXT
    if (function_exists('kt_saas_is_tenant_runtime') && kt_saas_is_tenant_runtime()) {
        // Kiểm tra entitlement — không hiện menu nếu gói không có eInvoice
        $tenantId = kt_saas_current_tenant_id();
        if (!kt_einvoice_tenant_has_feature($tenantId, KT_EINVOICE_FEATURE_ENABLED)) {
            return;
        }

        // ── Tenant Menu ──────────────────────────────────────────────────────────
        $CI->app_menu->add_sidebar_menu_item('kt_einvoice', [
            'slug'     => 'kt_einvoice',
            'name'     => _l('kt_einvoice'),
            'icon'     => 'fa-regular fa-file-invoice',
            'collapse' => true,
            'position' => 31,
        ]);

        $menuItems = [
            ['slug' => 'kt_einvoice_dashboard', 'name' => _l('kt_einvoice_menu_dashboard'),  'href' => admin_url('kt_einvoice/dashboard'),   'position' => 1],
            ['slug' => 'kt_einvoice_list',      'name' => _l('kt_einvoice_menu_invoices'),   'href' => admin_url('kt_einvoice/invoices'),    'position' => 2],
            ['slug' => 'kt_einvoice_settings',  'name' => _l('kt_einvoice_menu_settings'),   'href' => admin_url('kt_einvoice/settings'),    'position' => 5],
        ];

        // Batch issue — chỉ nếu plan có
        if (kt_einvoice_tenant_has_feature($tenantId, KT_EINVOICE_FEATURE_BATCH_ISSUE)) {
            $menuItems[] = ['slug' => 'kt_einvoice_batch', 'name' => _l('kt_einvoice_menu_batch_issue'), 'href' => admin_url('kt_einvoice/batch_issue'), 'position' => 3];
        }

        foreach ($menuItems as $item) {
            $CI->app_menu->add_sidebar_children_item('kt_einvoice', $item);
        }
    } else {
        // ── Landlord Menu ─────────────────────────────────────────────────────────
        $CI->app_menu->add_sidebar_menu_item('kt_einvoice_admin', [
            'slug'     => 'kt_einvoice_admin',
            'name'     => 'eInvoice Landlord',
            'icon'     => 'fa fa-globe',
            'collapse' => true,
            'position' => 33,
        ]);

        $menuItems = [
            ['slug' => 'kt_einvoice_admin_overview', 'name' => 'Tổng quan',          'href' => admin_url('kt_einvoice/admin/overview'),      'position' => 1],
            ['slug' => 'kt_einvoice_admin_plans',    'name' => 'Cấu hình theo gói',  'href' => admin_url('kt_einvoice/admin/plan_features'),  'position' => 2],
            ['slug' => 'kt_einvoice_admin_records',  'name' => 'Tất cả hóa đơn',     'href' => admin_url('kt_einvoice/admin/all_records'),    'position' => 3],
            ['slug' => 'kt_einvoice_admin_api_logs', 'name' => 'API Logs',           'href' => admin_url('kt_einvoice/admin/api_logs'),       'position' => 4],
            ['slug' => 'kt_einvoice_admin_cron_logs','name' => 'Cron Logs',          'href' => admin_url('kt_einvoice/admin/cron_logs'),      'position' => 5],
        ];

        foreach ($menuItems as $item) {
            $CI->app_menu->add_sidebar_children_item('kt_einvoice_admin', $item);
        }
    }
}

// ── Hook: Nút phát hành HĐĐT trong Invoice Detail ────────────────────────────
hooks()->add_action('before_invoice_preview_more_menu_button', 'kt_einvoice_invoice_action_button');
function kt_einvoice_invoice_action_button($invoice): void
{
    if (!function_exists('kt_saas_is_tenant_runtime') || !kt_saas_is_tenant_runtime()) {
        return;
    }

    $tenantId = kt_saas_current_tenant_id();
    if (!kt_einvoice_tenant_has_feature($tenantId, KT_EINVOICE_FEATURE_ENABLED)) {
        return;
    }

    if (!staff_can('create', 'kt_einvoice') && !staff_can('issue', 'kt_einvoice')) {
        return;
    }

    $CI = &get_instance();
    $CI->load->view('kt_einvoice/partials/issue_button', ['invoice' => $invoice]);
}

// ── Hook: Auto-issue khi invoice được thanh toán ──────────────────────────────
hooks()->add_action('invoice_payment_recorded', 'kt_einvoice_auto_issue_on_payment');
function kt_einvoice_auto_issue_on_payment($data): void
{
    if (!function_exists('kt_saas_is_tenant_runtime') || !kt_saas_is_tenant_runtime()) {
        return;
    }

    $tenantId  = kt_saas_current_tenant_id();
    $invoiceId = (int) ($data['invoiceid'] ?? 0);

    if (!$invoiceId || !$tenantId) {
        return;
    }

    // Kiểm tra feature + setting auto_issue
    if (!kt_einvoice_tenant_has_feature($tenantId, KT_EINVOICE_FEATURE_AUTO_ISSUE)) {
        return;
    }

    // Load settings để kiểm tra auto_issue_on_payment
    if (!class_exists('KtEinvoiceService')) {
        require_once APPPATH . '../modules/kt_einvoice/services/KtEinvoiceEncryptionService.php';
        require_once APPPATH . '../modules/kt_einvoice/services/SepayEinvoiceApiClient.php';
        require_once APPPATH . '../modules/kt_einvoice/models/Kt_einvoice_model.php';
        require_once APPPATH . '../modules/kt_einvoice/services/KtEinvoiceMapperService.php';
        require_once APPPATH . '../modules/kt_einvoice/services/KtEinvoiceQuotaService.php';
        require_once APPPATH . '../modules/kt_einvoice/services/KtEinvoiceService.php';
    }

    $CI       = &get_instance();
    $service  = new KtEinvoiceService();
    $settings = $service->getSettingsForDisplay($tenantId, 'production');

    if (empty($settings['auto_issue_on_payment'])) {
        return;
    }

    // Kiểm tra đã có record chưa
    if (!isset($CI->Kt_einvoice_model)) {
        $CI->load->model('kt_einvoice/Kt_einvoice_model');
    }
    $existing = $CI->Kt_einvoice_model->getRecordByPerfexInvoice($tenantId, $invoiceId, 'production');

    if ($existing && $existing['status'] === KT_EINVOICE_STATUS_ISSUED) {
        return; // Đã phát hành rồi
    }

    if ($existing && $existing['status'] === KT_EINVOICE_STATUS_DRAFT) {
        // Đã có draft → issue luôn
        $service->issueInvoice($tenantId, (int) $existing['id'], 'production');
        return;
    }

    // Chưa có → create draft + enqueue issue
    $result = $service->createDraft($tenantId, $invoiceId, 'production', get_staff_user_id());
    if ($result['success'] && !empty($result['record_id'])) {
        // Issue sẽ được trigger sau khi cron poll create status và status = draft
        // Đánh dấu để cron biết cần auto-issue
        $CI->Kt_einvoice_model->updateRecord((int) $result['record_id'], ['metadata_json' => json_encode(['auto_issue' => true])]);
    }
}

// ── Cron ──────────────────────────────────────────────────────────────────────
hooks()->add_action('app_cron', 'kt_einvoice_run_crons');
function kt_einvoice_run_crons(): void
{
    // Status checker — mỗi 2 phút
    $lastStatus = get_option('kt_einvoice_status_checker_last_run');
    if (!$lastStatus || (time() - strtotime($lastStatus)) >= KT_EINVOICE_CRON_STATUS_INTERVAL) {
        require_once APPPATH . '../modules/kt_einvoice/cron/KtEinvoiceStatusChecker.php';
        (new KtEinvoiceStatusChecker())->run();
        update_option('kt_einvoice_status_checker_last_run', date('Y-m-d H:i:s'));
    }

    // Batch issuer — mỗi 5 phút
    $lastBatch = get_option('kt_einvoice_batch_issuer_last_run');
    if (!$lastBatch || (time() - strtotime($lastBatch)) >= KT_EINVOICE_CRON_BATCH_INTERVAL) {
        require_once APPPATH . '../modules/kt_einvoice/cron/KtEinvoiceBatchIssuer.php';
        (new KtEinvoiceBatchIssuer())->run();
        update_option('kt_einvoice_batch_issuer_last_run', date('Y-m-d H:i:s'));
    }
}

// ── Helper function ───────────────────────────────────────────────────────────
function kt_einvoice_tenant_has_feature(int $tenantId, string $featureKey): bool
{
    if (!class_exists('TenantEntitlementService')) {
        $svcPath = APPPATH . '../modules/kt_saas/services/TenantEntitlementService.php';
        if (!file_exists($svcPath)) return false;
        require_once $svcPath;
    }
    $svc = new TenantEntitlementService();
    return (bool) $svc->getFeatureValue($tenantId, $featureKey);
}

function kt_saas_current_tenant_id(): int
{
    if (function_exists('kt_saas_get_current_tenant')) {
        $tenant = kt_saas_get_current_tenant();
        return (int) ($tenant['id'] ?? 0);
    }
    return 0;
}
