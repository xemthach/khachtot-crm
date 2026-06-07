<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: KT SaaS
Description: Clean-room multi-tenant SaaS foundation for Perfex CRM with landlord, subscription, billing, provisioning, and tenant control architecture.
Version: 0.2.2
Requires at least: 3.0.*
Author: Internal Engineering
*/

define('KT_SAAS_MODULE', 'kt_saas');
define('KT_SAAS_VERSION', '0.2.2');
define('KT_SAAS_ASSETS_URL', module_dir_url(KT_SAAS_MODULE, 'assets/'));

hooks()->add_action('admin_init', 'kt_saas_module_init');
hooks()->add_action('app_init', 'kt_saas_boot_runtime_hooks_early');
hooks()->add_action('app_admin_head', 'kt_saas_admin_head_assets');
hooks()->add_action('app_admin_footer', 'kt_saas_admin_footer_assets');
hooks()->add_action('after_cron_run', 'kt_saas_after_cron_run');
hooks()->add_filter('register_merge_fields', 'kt_saas_register_merge_fields');

register_activation_hook(KT_SAAS_MODULE, 'kt_saas_module_activation_hook');
register_uninstall_hook(KT_SAAS_MODULE, 'kt_saas_module_uninstall_hook');
register_language_files(KT_SAAS_MODULE, [KT_SAAS_MODULE]);

function kt_saas_module_init()
{
    $CI = &get_instance();
    $CI->load->helper(KT_SAAS_MODULE . '/kt_saas');

    if (kt_saas_is_tenant_runtime()) {
        hooks()->add_filter('sidebar_menu_items', 'kt_saas_filter_tenant_sidebar_menu_items');
        hooks()->add_filter('setup_menu_items', 'kt_saas_filter_tenant_setup_menu_items');
        hooks()->add_filter('show_setup_menu', 'kt_saas_filter_tenant_setup_menu_visibility');
    }

    kt_saas_maybe_upgrade_schema();
    kt_saas_register_staff_capabilities();
    kt_saas_register_menu_items();
    kt_saas_register_runtime_hooks();
    kt_saas_sync_email_template_states_once();
    kt_saas_seed_phase3b_email_templates_once();
    kt_saas_seed_phase3c_email_templates_once();
    kt_saas_seed_billing_lifecycle_email_templates_once();
    kt_saas_seed_phase3d_email_templates_once();
}

function kt_saas_boot_runtime_hooks_early()
{
    if (!function_exists('kt_saas_is_tenant_runtime')) {
        $helperPath = module_dir_path(KT_SAAS_MODULE, 'helpers/kt_saas_helper.php');
        if (is_file($helperPath)) {
            require_once $helperPath;
        }
    }

    if (function_exists('kt_saas_is_tenant_runtime') && kt_saas_is_tenant_runtime()) {
        kt_saas_register_runtime_hooks();
    }
}

function kt_saas_module_activation_hook()
{
    require_once __DIR__ . '/install.php';
}

function kt_saas_module_uninstall_hook()
{
    require_once __DIR__ . '/uninstall.php';
}

function kt_saas_maybe_upgrade_schema()
{
    if (!function_exists('get_option') || !function_exists('update_option')) {
        return;
    }

    if (!kt_saas_is_landlord_context()) {
        return;
    }

    $current = (string) get_option('kt_saas_schema_version');
    if ($current === KT_SAAS_VERSION) {
        return;
    }

    require_once __DIR__ . '/upgrade.php';
    kt_saas_run_upgrades();
    update_option('kt_saas_schema_version', KT_SAAS_VERSION);
}

function kt_saas_after_cron_run()
{
    require_once __DIR__ . '/cron/Kt_saas_cron.php';
    kt_saas_run_scheduled_jobs();
}

function kt_saas_register_staff_capabilities()
{
    register_staff_capabilities(
        KT_SAAS_MODULE,
        [
            'capabilities' => [
                'kt_saas_view'             => _l('kt_saas_permission_view'),
                'kt_saas_manage_tenants'   => _l('kt_saas_permission_manage_tenants'),
                'kt_saas_delete_tenants'   => _l('kt_saas_permission_delete_tenants'),
                'kt_saas_manage_plans'     => _l('kt_saas_permission_manage_plans'),
                'kt_saas_delete_plans'     => _l('kt_saas_permission_delete_plans'),
                'kt_saas_manage_billing'   => _l('kt_saas_permission_manage_billing'),
                'kt_saas_manage_domains'   => _l('kt_saas_permission_manage_domains'),
                'kt_saas_manage_modules'   => _l('kt_saas_permission_manage_modules'),
                'kt_saas_manage_usage'     => _l('kt_saas_permission_manage_usage'),
                'kt_saas_manage_backups'   => _l('kt_saas_permission_manage_backups'),
                'kt_saas_manage_settings'  => _l('kt_saas_permission_manage_settings'),
                'kt_saas_run_provisioning' => _l('kt_saas_permission_run_provisioning'),
            ],
        ],
        _l('kt_saas')
    );
}

function kt_saas_register_merge_fields($fields)
{
    $fields[] = 'kt_saas/merge_fields/Kt_saas_merge_fields';

    return $fields;
}

function kt_saas_register_menu_items()
{
    $CI = &get_instance();

    if (kt_saas_is_tenant_runtime()) {
        $canManageWorkspaceSettings = function_exists('kt_saas_can_manage_workspace_settings') && kt_saas_can_manage_workspace_settings();
        $canViewWorkspaceGovernance = function_exists('kt_saas_can_view_workspace_governance') && kt_saas_can_view_workspace_governance();
        if (!is_admin() && !$canManageWorkspaceSettings && !$canViewWorkspaceGovernance) {
            return;
        }

        $CI->app_menu->add_sidebar_menu_item('kt_saas_account', [
            'slug'     => 'kt_saas_account',
            'name'     => _l('kt_saas_my_account'),
            'icon'     => 'fa fa-credit-card',
            'collapse' => true,
            'position' => 31,
        ]);

        $tenantItems = [];
        if (is_admin()) {
            $tenantItems[] = ['slug' => 'kt_saas_account_subscription', 'name' => _l('kt_saas_my_subscription'), 'href' => admin_url('kt_saas/tenant_subscription')];
            $tenantItems[] = ['slug' => 'kt_saas_account_billing', 'name' => _l('kt_saas_my_billing'), 'href' => admin_url('kt_saas/tenant_billing')];
            $tenantItems[] = ['slug' => 'kt_saas_account_usage', 'name' => _l('kt_saas_my_usage'), 'href' => admin_url('kt_saas/tenant_usage')];
        }
        if ($canManageWorkspaceSettings) {
            $tenantItems[] = ['slug' => 'kt_saas_account_settings', 'name' => _l('settings'), 'href' => admin_url('kt_saas/tenant_settings')];
            $tenantItems[] = ['slug' => 'kt_saas_account_activity_logs', 'name' => _l('kt_saas_activity_logs'), 'href' => admin_url('kt_saas/tenant_activity_logs')];
        }
        if ($canViewWorkspaceGovernance) {
            $tenantItems[] = ['slug' => 'kt_saas_account_governance', 'name' => _l('kt_saas_users_roles'), 'href' => admin_url('kt_saas/tenant_governance')];
            if (function_exists('kt_saas_workspace_feature_allowed') && kt_saas_workspace_feature_allowed('workspace.departments.manage', false)) {
                $tenantItems[] = ['slug' => 'kt_saas_account_departments', 'name' => _l('departments'), 'href' => admin_url('kt_saas/tenant_departments')];
            }
        }

        $position = 1;
        foreach ($tenantItems as $item) {
            $item['position'] = $position++;
            $CI->app_menu->add_sidebar_children_item('kt_saas_account', $item);
        }

        return;
    }

    if (!kt_saas_is_landlord_context()) {
        return;
    }

    if (!kt_saas_staff_can('kt_saas_view')) {
        return;
    }

    $CI->app_menu->add_sidebar_menu_item('kt_saas', [
        'slug'     => 'kt_saas',
        'name'     => _l('kt_saas'),
        'icon'     => 'fa fa-cloud',
        'collapse' => true,
        'position' => 31,
    ]);

    $items = [
        ['slug' => 'kt_saas_dashboard', 'name' => _l('kt_saas_dashboard'), 'href' => admin_url('kt_saas')],
        ['slug' => 'kt_saas_tenants', 'name' => _l('kt_saas_tenants'), 'href' => admin_url('kt_saas/tenants'), 'cap' => 'kt_saas_manage_tenants'],
        ['slug' => 'kt_saas_plans', 'name' => _l('kt_saas_plans'), 'href' => admin_url('kt_saas/plans'), 'cap' => 'kt_saas_manage_plans'],
        ['slug' => 'kt_saas_subscriptions', 'name' => _l('kt_saas_subscriptions'), 'href' => admin_url('kt_saas/subscriptions'), 'cap' => 'kt_saas_manage_billing'],
        ['slug' => 'kt_saas_invoices', 'name' => _l('kt_saas_invoices'), 'href' => admin_url('kt_saas/invoices'), 'cap' => 'kt_saas_manage_billing'],
        ['slug' => 'kt_saas_payments', 'name' => _l('kt_saas_payments'), 'href' => admin_url('kt_saas/payments'), 'cap' => 'kt_saas_manage_billing'],
        ['slug' => 'kt_saas_domains', 'name' => _l('kt_saas_domains'), 'href' => admin_url('kt_saas/domains'), 'cap' => 'kt_saas_manage_domains'],
        ['slug' => 'kt_saas_modules', 'name' => _l('kt_saas_modules'), 'href' => admin_url('kt_saas/modules'), 'cap' => 'kt_saas_manage_modules'],
        ['slug' => 'kt_saas_backups', 'name' => _l('kt_saas_backups'), 'href' => admin_url('kt_saas/backups'), 'cap' => 'kt_saas_manage_backups'],
        ['slug' => 'kt_saas_provision_jobs', 'name' => _l('kt_saas_provision_jobs'), 'href' => admin_url('kt_saas/provision_jobs'), 'cap' => 'kt_saas_run_provisioning'],
        ['slug' => 'kt_saas_activity_logs', 'name' => _l('kt_saas_activity_logs'), 'href' => admin_url('kt_saas/activity_logs'), 'cap' => 'kt_saas_view'],
        ['slug' => 'kt_saas_settings', 'name' => _l('kt_saas_settings'), 'href' => admin_url('kt_saas/settings'), 'cap' => 'kt_saas_manage_settings'],
        ['slug' => 'kt_saas_architecture', 'name' => _l('kt_saas_architecture'), 'href' => admin_url('kt_saas/architecture')],
    ];

    $position = 1;
    foreach ($items as $item) {
        if (isset($item['cap']) && !kt_saas_staff_can($item['cap'])) {
            continue;
        }
        $item['position'] = $position++;
        $CI->app_menu->add_sidebar_children_item('kt_saas', $item);
    }
}

function kt_saas_admin_head_assets()
{
    if (!kt_saas_is_module_request()) {
        return;
    }

    echo '<link href="' . KT_SAAS_ASSETS_URL . 'css/kt_saas.css?v=' . KT_SAAS_VERSION . '" rel="stylesheet" type="text/css" />';
}

function kt_saas_admin_footer_assets()
{
    if (!kt_saas_is_module_request()) {
        return;
    }

    echo '<script src="' . KT_SAAS_ASSETS_URL . 'js/kt_saas.js?v=' . KT_SAAS_VERSION . '"></script>';
}

function kt_saas_register_runtime_hooks()
{
    static $registered = false;
    if ($registered) {
        return;
    }

    hooks()->add_filter('before_create_staff_member', 'kt_saas_limit_guard_before_create_staff_member');
    hooks()->add_filter('get_option', 'kt_saas_filter_tenant_scoped_option', 20, 2);
    hooks()->add_action('staff_member_created', 'kt_saas_after_staff_member_created');
    hooks()->add_action('staff_member_deleted', 'kt_saas_after_staff_member_deleted');
    hooks()->add_filter('email_template_from_headers', 'kt_saas_filter_tenant_email_template_from_headers', 10, 2);
    hooks()->add_filter('before_email_template_send', 'kt_saas_filter_tenant_email_template_send_payload');
    hooks()->add_filter('before_send_simple_email', 'kt_saas_filter_before_send_simple_email');
    hooks()->add_action('email_template_sent', 'kt_saas_log_email_template_sent');
    hooks()->add_action('failed_to_send_email_template', 'kt_saas_log_email_template_failed');
    hooks()->add_action('simple_email_sent', 'kt_saas_log_simple_email_sent');
    hooks()->add_action('simple_email_failed', 'kt_saas_log_simple_email_failed');

    hooks()->add_filter('before_client_added', 'kt_saas_limit_guard_before_client_added');
    hooks()->add_action('after_client_created', 'kt_saas_after_client_created');
    hooks()->add_action('after_client_deleted', 'kt_saas_after_client_deleted');

    hooks()->add_filter('before_add_project', 'kt_saas_limit_guard_before_add_project');
    hooks()->add_action('after_add_project', 'kt_saas_after_project_created');
    hooks()->add_action('after_project_deleted', 'kt_saas_after_project_deleted');

    hooks()->add_filter('before_invoice_added', 'kt_saas_limit_guard_before_invoice_added');
    hooks()->add_action('after_invoice_added', 'kt_saas_after_invoice_added');
    hooks()->add_action('after_invoice_deleted', 'kt_saas_after_invoice_deleted');

    $registered = true;
}

function kt_saas_should_log_email_event()
{
    $runtimeTransport = config_item('kt_saas_mail_runtime_transport');
    if (is_array($runtimeTransport) && !empty($runtimeTransport)) {
        return true;
    }

    $runtimeIdentity = config_item('kt_saas_mail_runtime_identity');
    if (!is_array($runtimeIdentity) || empty($runtimeIdentity)) {
        return false;
    }

    foreach ($runtimeIdentity as $value) {
        if (trim((string) $value) !== '') {
            return true;
        }
    }

    return false;
}

function kt_saas_email_runtime_meta()
{
    return [
        'tenant_id' => (int) (config_item('kt_saas_mail_runtime_tenant_id') ?: 0),
        'related_type' => trim((string) (config_item('kt_saas_mail_runtime_related_type') ?: '')),
        'related_id' => trim((string) (config_item('kt_saas_mail_runtime_related_id') ?: '')),
        'event_key' => trim((string) (config_item('kt_saas_mail_runtime_event_key') ?: '')),
        'dedupe_key' => trim((string) (config_item('kt_saas_mail_runtime_dedupe_key') ?: '')),
        'provider_context' => trim((string) (config_item('kt_saas_mail_runtime_provider_context') ?: '')),
        'branding_context' => trim((string) (config_item('kt_saas_mail_runtime_branding_context') ?: '')),
    ];
}

function kt_saas_sync_email_template_states_once()
{
    if (!function_exists('get_option') || !function_exists('update_option') || !function_exists('kt_saas_is_landlord_context') || !kt_saas_is_landlord_context()) {
        return;
    }

    $flag = 'kt_saas_email_template_state_sync_v1';
    if ((string) get_option($flag) === '1') {
        return;
    }

    $CI = &get_instance();
    $CI->load->model('emails_model');

    foreach (['estimate-request-received-to-user', 'new-web-to-lead-form-submitted'] as $slug) {
        $CI->emails_model->mark_as($slug, 1);
    }

    foreach (['inventory-warning-to-staff', 'tenant-expiration-reminder', 'we-found-your-tenant-url'] as $slug) {
        $CI->emails_model->mark_as($slug, 0);
    }

    update_option($flag, '1');
}

function kt_saas_normalize_vietnamese_email_template($language, $template)
{
    if ($language !== 'vietnamese') {
        return $template;
    }

    $replacements = [
        'Workspace URL' => 'Địa chỉ không gian làm việc',
        'Workspace' => 'Không gian làm việc',
        'Subscription' => 'Gói đăng ký',
        'subscription' => 'gói đăng ký',
        'Quota eInvoice' => 'Hạn mức eInvoice',
        'quota eInvoice' => 'hạn mức eInvoice',
        'Quota' => 'Hạn mức',
        'quota' => 'hạn mức',
        'Webhook URL' => 'Địa chỉ nhận dữ liệu',
        'Webhook' => 'Tiếp nhận dữ liệu tự động',
        'webhook' => 'tiếp nhận dữ liệu tự động',
        'Job ID' => 'Mã tác vụ',
        'Backup ID' => 'Mã bản sao lưu',
        'Backup' => 'Sao lưu',
        'backup' => 'sao lưu',
        'Cron' => 'Tác vụ định kỳ',
        'cron' => 'tác vụ định kỳ',
        'Tenant' => 'Doanh nghiệp',
        'tenant' => 'doanh nghiệp',
    ];

    foreach (['name', 'subject', 'message'] as $field) {
        if (!isset($template[$field]) || !is_string($template[$field])) {
            continue;
        }

        $placeholders = [];
        $value = preg_replace_callback('/\{[A-Za-z0-9_]+\}/', function ($match) use (&$placeholders) {
            $token = '@@KT_MERGE_' . count($placeholders) . '@@';
            $placeholders[$token] = $match[0];

            return $token;
        }, $template[$field]);

        $value = str_replace(array_keys($replacements), array_values($replacements), $value);
        $template[$field] = strtr($value, $placeholders);
    }

    return $template;
}

function kt_saas_seed_phase3b_email_templates_once()
{
    if (!function_exists('get_option') || !function_exists('update_option') || !function_exists('kt_saas_is_landlord_context') || !kt_saas_is_landlord_context()) {
        return;
    }

    $flag = 'kt_saas_email_template_phase3b_seed_v1';
    if ((string) get_option($flag) === '1') {
        return;
    }

    $CI = &get_instance();
    if (!isset($CI->emails_model)) {
        $CI->load->model('emails_model');
    }

    $definitions = kt_saas_phase3b_email_template_definitions();
    foreach ($definitions as $slug => $languages) {
        foreach ($languages as $language => $template) {
            $template = kt_saas_normalize_vietnamese_email_template($language, $template);
            $existing = $CI->emails_model->get([
                'slug' => $slug,
                'language' => $language,
            ], 'row_array');

            $payload = [
                'type' => (string) ($template['type'] ?? 'notifications'),
                'slug' => $slug,
                'language' => $language,
                'name' => (string) ($template['name'] ?? $slug),
                'subject' => (string) ($template['subject'] ?? ''),
                'message' => (string) ($template['message'] ?? ''),
                'fromname' => (string) ($template['fromname'] ?? '{companyname} | KT SaaS'),
                'fromemail' => (string) ($template['fromemail'] ?? ''),
                'plaintext' => (int) ($template['plaintext'] ?? 0),
                'active' => (int) ($template['active'] ?? 1),
                'order' => (int) ($template['order'] ?? 100),
            ];

            if ($existing) {
                $CI->db->where('emailtemplateid', (int) $existing['emailtemplateid'])->update(db_prefix() . 'emailtemplates', $payload);
            } else {
                $CI->emails_model->add_template($payload);
            }
        }
    }

    update_option($flag, '1');
}

function kt_saas_phase3b_email_template_definitions()
{
    return [
        'tenant_welcome' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'Tenant Welcome',
                'subject' => 'Your CRM workspace is ready: {workspace_name}',
                'message' => '<p>Dear {owner_name},</p><p>Your CRM workspace for <strong>{workspace_name}</strong> is ready.</p><p><strong>CRM plan:</strong> {plan_name}</p><p><strong>Login page:</strong> <a href="{admin_login_url}">{admin_login_url}</a></p><p><a href="{set_password_url}" style="display:inline-block;padding:10px 16px;background:#0f5b9d;color:#ffffff;text-decoration:none;border-radius:4px;">Create your admin password</a></p><p>This password setup link expires in {password_link_expires_in}.</p><p>After creating your password, sign in and review your company profile, users, and billing information.</p><p>Need help? Contact {support_email}.</p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'fromemail' => '',
                'plaintext' => 0,
                'active' => 1,
                'order' => 100,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Chào mừng doanh nghiệp',
                'subject' => 'Không gian CRM của {workspace_name} đã sẵn sàng',
                'message' => '<p>Xin chào {owner_name},</p><p>Không gian CRM của <strong>{workspace_name}</strong> đã sẵn sàng.</p><p><strong>Gói CRM:</strong> {plan_name}</p><p><strong>Trang đăng nhập:</strong> <a href="{admin_login_url}">{admin_login_url}</a></p><p><a href="{set_password_url}" style="display:inline-block;padding:10px 16px;background:#0f5b9d;color:#ffffff;text-decoration:none;border-radius:4px;">Tạo mật khẩu quản trị</a></p><p>Liên kết tạo mật khẩu hết hạn sau {password_link_expires_in}.</p><p>Sau khi tạo mật khẩu, hãy đăng nhập và kiểm tra hồ sơ doanh nghiệp, người dùng và thông tin thanh toán.</p><p>Cần hỗ trợ? Liên hệ {support_email}.</p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'fromemail' => '',
                'plaintext' => 0,
                'active' => 1,
                'order' => 100,
            ],
        ],
        'tenant_provisioning_completed' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'Tenant Provisioning Completed',
                'subject' => 'Provisioning completed for {workspace_name}',
                'message' => '<p>Dear {owner_name},</p><p>Your CRM workspace <strong>{workspace_name}</strong> has been provisioned successfully.</p><p><strong>CRM plan:</strong> {plan_name}</p><p><strong>Login page:</strong> <a href="{admin_login_url}">{admin_login_url}</a></p><p><a href="{set_password_url}" style="display:inline-block;padding:10px 16px;background:#0f5b9d;color:#ffffff;text-decoration:none;border-radius:4px;">Create your admin password</a></p><p>This password setup link expires in {password_link_expires_in}.</p><p>Need help? Contact {support_email}.</p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'fromemail' => '',
                'plaintext' => 0,
                'active' => 1,
                'order' => 101,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Cấp phát không gian làm việc hoàn tất',
                'subject' => 'Không gian CRM của {workspace_name} đã được cấp phát',
                'message' => '<p>Xin chào {owner_name},</p><p>Không gian CRM của <strong>{workspace_name}</strong> đã được cấp phát thành công.</p><p><strong>Gói CRM:</strong> {plan_name}</p><p><strong>Trang đăng nhập:</strong> <a href="{admin_login_url}">{admin_login_url}</a></p><p><a href="{set_password_url}" style="display:inline-block;padding:10px 16px;background:#0f5b9d;color:#ffffff;text-decoration:none;border-radius:4px;">Tạo mật khẩu quản trị</a></p><p>Liên kết tạo mật khẩu hết hạn sau {password_link_expires_in}.</p><p>Cần hỗ trợ? Liên hệ {support_email}.</p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'fromemail' => '',
                'plaintext' => 0,
                'active' => 1,
                'order' => 101,
            ],
        ],
        'tenant_provisioning_failed' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'Tenant Provisioning Failed',
                'subject' => 'Provisioning failed for {tenant_name}',
                'message' => '<p>Dear {owner_name},</p><p>We could not complete provisioning for <strong>{tenant_name}</strong>.</p><p><strong>Reason:</strong> {error_message}</p><p>Please contact the support team so we can help resolve the issue.</p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'fromemail' => '',
                'plaintext' => 0,
                'active' => 1,
                'order' => 102,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Cấp phát không gian làm việc thất bại',
                'subject' => 'Cấp phát không thành công cho {tenant_name}',
                'message' => '<p>Xin chào {owner_name},</p><p>Chúng tôi chưa thể hoàn tất việc cấp phát cho <strong>{tenant_name}</strong>.</p><p><strong>Nguyên nhân:</strong> {error_message}</p><p>Vui lòng liên hệ đội hỗ trợ để chúng tôi giúp xử lý sự cố.</p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'fromemail' => '',
                'plaintext' => 0,
                'active' => 1,
                'order' => 102,
            ],
        ],
        'payment_success' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'Payment Success',
                'subject' => 'Payment received for {tenant_name}',
                'message' => '<p>Dear {owner_name},</p><p>We have received your payment for <strong>{tenant_name}</strong>.</p><p><strong>Invoice total:</strong> {invoice_total} {currency}</p><p><strong>Invoice:</strong> <a href="{invoice_url}">{invoice_url}</a></p><p><strong>Payment link:</strong> <a href="{payment_url}">{payment_url}</a></p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'fromemail' => '',
                'plaintext' => 0,
                'active' => 1,
                'order' => 103,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Thanh toán thành công',
                'subject' => 'Đã nhận thanh toán cho {tenant_name}',
                'message' => '<p>Xin chào {owner_name},</p><p>Chúng tôi đã nhận được thanh toán cho <strong>{tenant_name}</strong>.</p><p><strong>Tổng hóa đơn:</strong> {invoice_total} {currency}</p><p><strong>Hóa đơn:</strong> <a href="{invoice_url}">{invoice_url}</a></p><p><strong>Liên kết thanh toán:</strong> <a href="{payment_url}">{payment_url}</a></p><p><strong>Không gian làm việc:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'fromemail' => '',
                'plaintext' => 0,
                'active' => 1,
                'order' => 103,
            ],
        ],
        'payment_failed' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'Payment Failed',
                'subject' => 'Payment failed for {tenant_name}',
                'message' => '<p>Dear {owner_name},</p><p>Your payment for <strong>{tenant_name}</strong> was not completed.</p><p><strong>Invoice total:</strong> {invoice_total} {currency}</p><p><strong>Invoice:</strong> <a href="{invoice_url}">{invoice_url}</a></p><p><strong>Payment link:</strong> <a href="{payment_url}">{payment_url}</a></p><p><strong>Reason:</strong> {error_message}</p><p>Please retry the payment or contact support if you need assistance.</p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'fromemail' => '',
                'plaintext' => 0,
                'active' => 1,
                'order' => 104,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Thanh toán thất bại',
                'subject' => 'Thanh toán chưa thành công cho {tenant_name}',
                'message' => '<p>Xin chào {owner_name},</p><p>Thanh toán cho <strong>{tenant_name}</strong> vẫn chưa hoàn tất.</p><p><strong>Tổng hóa đơn:</strong> {invoice_total} {currency}</p><p><strong>Hóa đơn:</strong> <a href="{invoice_url}">{invoice_url}</a></p><p><strong>Liên kết thanh toán:</strong> <a href="{payment_url}">{payment_url}</a></p><p><strong>Nguyên nhân:</strong> {error_message}</p><p>Vui lòng thanh toán lại hoặc liên hệ hỗ trợ nếu bạn cần giúp đỡ.</p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'fromemail' => '',
                'plaintext' => 0,
                'active' => 1,
                'order' => 104,
            ],
        ],
    ];
}

function kt_saas_seed_phase3c_email_templates_once()
{
    if (!function_exists('get_option') || !function_exists('update_option') || !function_exists('kt_saas_is_landlord_context') || !kt_saas_is_landlord_context()) {
        return;
    }

    $flag = 'kt_saas_email_template_phase3c_seed_v1';
    if ((string) get_option($flag) === '1') {
        return;
    }

    $CI = &get_instance();
    if (!isset($CI->emails_model)) {
        $CI->load->model('emails_model');
    }

    $definitions = kt_saas_phase3c_email_template_definitions();
    foreach ($definitions as $slug => $languages) {
        foreach ($languages as $language => $template) {
            $template = kt_saas_normalize_vietnamese_email_template($language, $template);
            $existing = $CI->emails_model->get([
                'slug' => $slug,
                'language' => $language,
            ], 'row_array');

            $payload = [
                'type' => (string) ($template['type'] ?? 'notifications'),
                'slug' => $slug,
                'language' => $language,
                'name' => (string) ($template['name'] ?? $slug),
                'subject' => (string) ($template['subject'] ?? ''),
                'message' => (string) ($template['message'] ?? ''),
                'fromname' => (string) ($template['fromname'] ?? '{companyname} | KT SaaS'),
                'fromemail' => (string) ($template['fromemail'] ?? ''),
                'plaintext' => (int) ($template['plaintext'] ?? 0),
                'active' => (int) ($template['active'] ?? 1),
                'order' => (int) ($template['order'] ?? 200),
            ];

            if ($existing) {
                $CI->db->where('emailtemplateid', (int) $existing['emailtemplateid'])->update(db_prefix() . 'emailtemplates', $payload);
            } else {
                $CI->emails_model->add_template($payload);
            }
        }
    }

    update_option($flag, '1');
}

function kt_saas_phase3c_email_template_definitions()
{
    return [
        'tenant_trial_started' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'Tenant Trial Started',
                'subject' => 'Trial started for {tenant_name}',
                'message' => '<p>Dear {owner_name},</p><p>Your trial workspace for <strong>{tenant_name}</strong> is now active.</p><p><strong>Workspace URL:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p><strong>Plan:</strong> {plan_name}</p><p><strong>Trial ends:</strong> {trial_end_date}</p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 200,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Bắt đầu dùng thử tenant',
                'subject' => 'Dùng thử đã bắt đầu cho {tenant_name}',
                'message' => '<p>Xin chào {owner_name},</p><p>Không gian dùng thử của <strong>{tenant_name}</strong> hiện đã sẵn sàng.</p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p><strong>Gói:</strong> {plan_name}</p><p><strong>Kết thúc dùng thử:</strong> {trial_end_date}</p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 200,
            ],
        ],
        'tenant_trial_ending' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'Tenant Trial Ending',
                'subject' => 'Trial ending soon for {tenant_name}',
                'message' => '<p>Dear {owner_name},</p><p>Your trial for <strong>{tenant_name}</strong> will end soon.</p><p><strong>Workspace URL:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p><strong>Plan:</strong> {plan_name}</p><p><strong>Trial ends:</strong> {trial_end_date}</p><p><strong>Status:</strong> {subscription_status}</p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 201,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Sắp hết hạn dùng thử',
                'subject' => 'Dùng thử sắp kết thúc cho {tenant_name}',
                'message' => '<p>Xin chào {owner_name},</p><p>Thời gian dùng thử của <strong>{tenant_name}</strong> sẽ sớm kết thúc.</p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p><strong>Gói:</strong> {plan_name}</p><p><strong>Kết thúc dùng thử:</strong> {trial_end_date}</p><p><strong>Trạng thái:</strong> {subscription_status}</p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 201,
            ],
        ],
        'tenant_trial_expired' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'Tenant Trial Expired',
                'subject' => 'Trial expired for {tenant_name}',
                'message' => '<p>Dear {owner_name},</p><p>The trial for <strong>{tenant_name}</strong> has expired.</p><p><strong>Workspace URL:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p><strong>Plan:</strong> {plan_name}</p><p><strong>Status:</strong> {subscription_status}</p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 202,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Hết hạn dùng thử',
                'subject' => 'Dùng thử đã hết hạn cho {tenant_name}',
                'message' => '<p>Xin chào {owner_name},</p><p>Thời gian dùng thử của <strong>{tenant_name}</strong> đã hết hạn.</p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p><strong>Gói:</strong> {plan_name}</p><p><strong>Trạng thái:</strong> {subscription_status}</p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 202,
            ],
        ],
        'tenant_subscription_renewed' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'Tenant Subscription Renewed',
                'subject' => 'Subscription renewed for {tenant_name}',
                'message' => '<p>Dear {owner_name},</p><p>Your subscription for <strong>{tenant_name}</strong> has been renewed.</p><p><strong>Workspace URL:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p><strong>Plan:</strong> {plan_name}</p><p><strong>Status:</strong> {subscription_status}</p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 203,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Gia hạn subscription',
                'subject' => 'Đã gia hạn cho {tenant_name}',
                'message' => '<p>Xin chào {owner_name},</p><p>Gói đăng ký của <strong>{tenant_name}</strong> đã được gia hạn thành công.</p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p><strong>Gói:</strong> {plan_name}</p><p><strong>Trạng thái:</strong> {subscription_status}</p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 203,
            ],
        ],
        'tenant_subscription_expired' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'Tenant Subscription Expired',
                'subject' => 'Subscription expired for {tenant_name}',
                'message' => '<p>Dear {owner_name},</p><p>The subscription for <strong>{tenant_name}</strong> is now expired.</p><p><strong>Workspace URL:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p><strong>Plan:</strong> {plan_name}</p><p><strong>Status:</strong> {subscription_status}</p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 204,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Hết hạn subscription',
                'subject' => 'Subscription đã hết hạn cho {tenant_name}',
                'message' => '<p>Xin chào {owner_name},</p><p>Gói đăng ký của <strong>{tenant_name}</strong> hiện đã hết hạn.</p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p><strong>Gói:</strong> {plan_name}</p><p><strong>Trạng thái:</strong> {subscription_status}</p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 204,
            ],
        ],
        'tenant_plan_changed' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'Tenant Plan Changed',
                'subject' => 'Plan changed for {tenant_name}',
                'message' => '<p>Dear {owner_name},</p><p>The plan for <strong>{tenant_name}</strong> has been changed successfully.</p><p><strong>Workspace URL:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p><strong>Plan:</strong> {plan_name}</p><p><strong>Status:</strong> {subscription_status}</p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 205,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Thay đổi gói tenant',
                'subject' => 'Đã thay đổi gói cho {tenant_name}',
                'message' => '<p>Xin chào {owner_name},</p><p>Gói của <strong>{tenant_name}</strong> đã được thay đổi thành công.</p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p><strong>Gói:</strong> {plan_name}</p><p><strong>Trạng thái:</strong> {subscription_status}</p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 205,
            ],
        ],
        'tenant_quota_warning' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'Tenant Quota Warning',
                'subject' => 'Quota warning for {tenant_name}',
                'message' => '<p>Dear {owner_name},</p><p>Your workspace <strong>{tenant_name}</strong> is approaching its limits.</p><p><strong>Workspace URL:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p><strong>Plan:</strong> {plan_name}</p><p><strong>Quota remaining:</strong> {quota_remaining}</p><p><strong>Quota limit:</strong> {quota_limit}</p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 206,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Cảnh báo vượt ngưỡng',
                'subject' => 'Cảnh báo quota cho {tenant_name}',
                'message' => '<p>Xin chào {owner_name},</p><p>Không gian <strong>{tenant_name}</strong> đang tiến gần đến giới hạn sử dụng.</p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p><strong>Gói:</strong> {plan_name}</p><p><strong>Số lượng còn lại:</strong> {quota_remaining}</p><p><strong>Giới hạn:</strong> {quota_limit}</p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 206,
            ],
        ],
        'tenant_quota_exceeded' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'Tenant Quota Exceeded',
                'subject' => 'Quota exceeded for {tenant_name}',
                'message' => '<p>Dear {owner_name},</p><p>Your workspace <strong>{tenant_name}</strong> has exceeded its limits.</p><p><strong>Workspace URL:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p><strong>Plan:</strong> {plan_name}</p><p><strong>Quota remaining:</strong> {quota_remaining}</p><p><strong>Quota limit:</strong> {quota_limit}</p><p>Please review your usage or upgrade your plan.</p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 207,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Vượt giới hạn sử dụng',
                'subject' => '{tenant_name} đã vượt quota',
                'message' => '<p>Xin chào {owner_name},</p><p>Không gian <strong>{tenant_name}</strong> đã vượt quá giới hạn sử dụng.</p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p><strong>Gói:</strong> {plan_name}</p><p><strong>Số lượng còn lại:</strong> {quota_remaining}</p><p><strong>Giới hạn:</strong> {quota_limit}</p><p>Vui lòng xem lại mức sử dụng hoặc nâng cấp gói.</p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 207,
            ],
        ],
    ];
}

function kt_saas_seed_billing_lifecycle_email_templates_once()
{
    if (!function_exists('get_option') || !function_exists('update_option') || !function_exists('kt_saas_is_landlord_context') || !kt_saas_is_landlord_context()) {
        return;
    }

    $flag = 'kt_saas_billing_lifecycle_email_seed_v1';
    if ((string) get_option($flag) === '1') {
        return;
    }

    $CI = &get_instance();
    if (!isset($CI->emails_model)) {
        $CI->load->model('emails_model');
    }

    foreach (kt_saas_billing_lifecycle_email_template_definitions() as $slug => $languages) {
        foreach ($languages as $language => $template) {
            $template = kt_saas_normalize_vietnamese_email_template($language, $template);
            $existing = $CI->emails_model->get([
                'slug' => $slug,
                'language' => $language,
            ], 'row_array');

            $payload = [
                'type' => (string) ($template['type'] ?? 'notifications'),
                'slug' => $slug,
                'language' => $language,
                'name' => (string) ($template['name'] ?? $slug),
                'subject' => (string) ($template['subject'] ?? ''),
                'message' => (string) ($template['message'] ?? ''),
                'fromname' => (string) ($template['fromname'] ?? '{companyname} | KT SaaS'),
                'fromemail' => (string) ($template['fromemail'] ?? ''),
                'plaintext' => (int) ($template['plaintext'] ?? 0),
                'active' => (int) ($template['active'] ?? 1),
                'order' => (int) ($template['order'] ?? 220),
            ];

            if ($existing) {
                $CI->db->where('emailtemplateid', (int) $existing['emailtemplateid'])->update(db_prefix() . 'emailtemplates', $payload);
            } else {
                $CI->emails_model->add_template($payload);
            }
        }
    }

    update_option($flag, '1');
}

function kt_saas_billing_lifecycle_email_template_definitions()
{
    return [
        'invoice_overdue' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'KT SaaS Invoice Overdue',
                'subject' => 'Invoice overdue for {tenant_name}',
                'message' => '<p>Dear {owner_name},</p><p>Your invoice for <strong>{tenant_name}</strong> is now overdue.</p><p><strong>Invoice total:</strong> {invoice_total} {currency}</p><p><strong>Payment link:</strong> <a href="{payment_url}">{payment_url}</a></p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p>Please complete payment to avoid service interruption.</p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'fromemail' => '',
                'plaintext' => 0,
                'active' => 1,
                'order' => 220,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Hóa đơn gói CRM quá hạn',
                'subject' => 'Hóa đơn quá hạn cho {tenant_name}',
                'message' => '<p>Xin chào {owner_name},</p><p>Hóa đơn của <strong>{tenant_name}</strong> hiện đã quá hạn thanh toán.</p><p><strong>Tổng hóa đơn:</strong> {invoice_total} {currency}</p><p><strong>Liên kết thanh toán:</strong> <a href="{payment_url}">{payment_url}</a></p><p><strong>Không gian CRM:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p>Vui lòng hoàn tất thanh toán để tránh gián đoạn dịch vụ.</p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'fromemail' => '',
                'plaintext' => 0,
                'active' => 1,
                'order' => 220,
            ],
        ],
        'renewal_failed' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'KT SaaS Renewal Failed',
                'subject' => 'Renewal payment pending for {tenant_name}',
                'message' => '<p>Dear {owner_name},</p><p>We could not complete the renewal payment for <strong>{tenant_name}</strong>.</p><p><strong>Invoice total:</strong> {invoice_total} {currency}</p><p><strong>Payment link:</strong> <a href="{payment_url}">{payment_url}</a></p><p><strong>Reason:</strong> {error_message}</p><p>Please complete payment to keep your CRM active.</p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'fromemail' => '',
                'plaintext' => 0,
                'active' => 1,
                'order' => 221,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Gia hạn gói CRM chưa thành công',
                'subject' => 'Gia hạn đang chờ thanh toán cho {tenant_name}',
                'message' => '<p>Xin chào {owner_name},</p><p>Chúng tôi chưa thể hoàn tất thanh toán gia hạn cho <strong>{tenant_name}</strong>.</p><p><strong>Tổng hóa đơn:</strong> {invoice_total} {currency}</p><p><strong>Liên kết thanh toán:</strong> <a href="{payment_url}">{payment_url}</a></p><p><strong>Lý do:</strong> {error_message}</p><p>Vui lòng hoàn tất thanh toán để duy trì dịch vụ CRM.</p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'fromemail' => '',
                'plaintext' => 0,
                'active' => 1,
                'order' => 221,
            ],
        ],
    ];
}

function kt_saas_seed_phase3d_email_templates_once()
{
    if (!function_exists('get_option') || !function_exists('update_option') || !function_exists('kt_saas_is_landlord_context') || !kt_saas_is_landlord_context()) {
        return;
    }

    $flag = 'kt_saas_email_template_phase3d_seed_v1';
    if ((string) get_option($flag) === '1') {
        return;
    }

    $CI = &get_instance();
    if (!isset($CI->emails_model)) {
        $CI->load->model('emails_model');
    }

    $definitions = kt_saas_phase3d_email_template_definitions();
    foreach ($definitions as $slug => $languages) {
        foreach ($languages as $language => $template) {
            $template = kt_saas_normalize_vietnamese_email_template($language, $template);
            $existing = $CI->emails_model->get([
                'slug' => $slug,
                'language' => $language,
            ], 'row_array');

            $payload = [
                'type' => (string) ($template['type'] ?? 'notifications'),
                'slug' => $slug,
                'language' => $language,
                'name' => (string) ($template['name'] ?? $slug),
                'subject' => (string) ($template['subject'] ?? ''),
                'message' => (string) ($template['message'] ?? ''),
                'fromname' => (string) ($template['fromname'] ?? '{companyname} | KT SaaS'),
                'fromemail' => (string) ($template['fromemail'] ?? ''),
                'plaintext' => (int) ($template['plaintext'] ?? 0),
                'active' => (int) ($template['active'] ?? 1),
                'order' => (int) ($template['order'] ?? 300),
            ];

            if ($existing) {
                $CI->db->where('emailtemplateid', (int) $existing['emailtemplateid'])->update(db_prefix() . 'emailtemplates', $payload);
            } else {
                $CI->emails_model->add_template($payload);
            }
        }
    }

    update_option($flag, '1');
}

function kt_saas_phase3d_email_template_definitions()
{
    return [
        'einvoice_activated' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'eInvoice Activated',
                'subject' => 'eInvoice activated for {tenant_name}',
                'message' => '<p>Dear {owner_name},</p><p>Your eInvoice service for <strong>{tenant_name}</strong> is now active.</p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p><strong>Plan:</strong> {plan_name}</p><p><strong>Lookup URL:</strong> <a href="{lookup_url}">{lookup_url}</a></p><p><strong>eInvoice quota:</strong> {einvoice_remaining} / {einvoice_quota}</p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 300,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Kích hoạt eInvoice',
                'subject' => 'eInvoice đã được kích hoạt cho {tenant_name}',
                'message' => '<p>Xin chào {owner_name},</p><p>Dịch vụ eInvoice của <strong>{tenant_name}</strong> hiện đã hoạt động.</p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p><strong>Gói dịch vụ:</strong> {plan_name}</p><p><strong>Tra cứu:</strong> <a href="{lookup_url}">{lookup_url}</a></p><p><strong>Quota eInvoice:</strong> {einvoice_remaining} / {einvoice_quota}</p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 300,
            ],
        ],
        'einvoice_quota_low' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'eInvoice Quota Low',
                'subject' => 'eInvoice quota is running low for {tenant_name}',
                'message' => '<p>Dear {owner_name},</p><p>The eInvoice quota for <strong>{tenant_name}</strong> is nearing its limit.</p><p><strong>Remaining:</strong> {einvoice_remaining}</p><p><strong>Limit:</strong> {einvoice_quota}</p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 301,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Quota eInvoice sắp hết',
                'subject' => 'Quota eInvoice đang giảm cho {tenant_name}',
                'message' => '<p>Xin chào {owner_name},</p><p>Quota eInvoice của <strong>{tenant_name}</strong> đang gần chạm giới hạn.</p><p><strong>Còn lại:</strong> {einvoice_remaining}</p><p><strong>Giới hạn:</strong> {einvoice_quota}</p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 301,
            ],
        ],
        'einvoice_quota_exhausted' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'eInvoice Quota Exhausted',
                'subject' => 'eInvoice quota exhausted for {tenant_name}',
                'message' => '<p>Dear {owner_name},</p><p>The eInvoice quota for <strong>{tenant_name}</strong> has been exhausted.</p><p><strong>Remaining:</strong> {einvoice_remaining}</p><p><strong>Limit:</strong> {einvoice_quota}</p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p>Please top up the quota to continue issuing invoices.</p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 302,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Quota eInvoice đã hết',
                'subject' => 'Quota eInvoice đã hết cho {tenant_name}',
                'message' => '<p>Xin chào {owner_name},</p><p>Quota eInvoice của <strong>{tenant_name}</strong> đã hết.</p><p><strong>Còn lại:</strong> {einvoice_remaining}</p><p><strong>Giới hạn:</strong> {einvoice_quota}</p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p>Vui lòng nạp thêm quota để tiếp tục xuất hóa đơn.</p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 302,
            ],
        ],
        'hsm_activated' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'HSM Activated',
                'subject' => 'HSM activated for {tenant_name}',
                'message' => '<p>Dear {owner_name},</p><p>The HSM / CA signing account for <strong>{tenant_name}</strong> is now active.</p><p><strong>Status:</strong> {hsm_status}</p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 303,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Kích hoạt HSM',
                'subject' => 'HSM đã được kích hoạt cho {tenant_name}',
                'message' => '<p>Xin chào {owner_name},</p><p>Tài khoản ký HSM / CA của <strong>{tenant_name}</strong> hiện đã hoạt động.</p><p><strong>Trạng thái:</strong> {hsm_status}</p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 303,
            ],
        ],
        'hsm_expiry_warning' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'HSM Expiry Warning',
                'subject' => 'HSM expires soon for {tenant_name}',
                'message' => '<p>Dear {owner_name},</p><p>The HSM / CA signing account for <strong>{tenant_name}</strong> will expire soon.</p><p><strong>Expiry date:</strong> {hsm_expiry_date}</p><p><strong>Status:</strong> {hsm_status}</p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 304,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Cảnh báo hết hạn HSM',
                'subject' => 'HSM sắp hết hạn cho {tenant_name}',
                'message' => '<p>Xin chào {owner_name},</p><p>Tài khoản ký HSM / CA của <strong>{tenant_name}</strong> sắp hết hạn.</p><p><strong>Ngày hết hạn:</strong> {hsm_expiry_date}</p><p><strong>Trạng thái:</strong> {hsm_status}</p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 304,
            ],
        ],
        'invoice_issue_failed' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'Invoice Issue Failed',
                'subject' => 'Invoice issue failed for {tenant_name}',
                'message' => '<p>Dear {owner_name},</p><p>We could not issue the invoice for <strong>{tenant_name}</strong>.</p><p><strong>Invoice number:</strong> {invoice_number}</p><p><strong>Status:</strong> {invoice_status}</p><p><strong>Reason:</strong> {error_message}</p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 305,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Xuất hóa đơn thất bại',
                'subject' => 'Xuất hóa đơn thất bại cho {tenant_name}',
                'message' => '<p>Xin chào {owner_name},</p><p>Chúng tôi không thể xuất hóa đơn cho <strong>{tenant_name}</strong>.</p><p><strong>Số hóa đơn:</strong> {invoice_number}</p><p><strong>Trạng thái:</strong> {invoice_status}</p><p><strong>Nguyên nhân:</strong> {error_message}</p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 305,
            ],
        ],
        'invoice_sign_failed' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'Invoice Sign Failed',
                'subject' => 'Invoice signing failed for {tenant_name}',
                'message' => '<p>Dear {owner_name},</p><p>Signing the invoice for <strong>{tenant_name}</strong> failed.</p><p><strong>Invoice number:</strong> {invoice_number}</p><p><strong>Reason:</strong> {error_message}</p><p><strong>PDF:</strong> <a href="{pdf_url}">{pdf_url}</a></p><p><strong>XML:</strong> <a href="{xml_url}">{xml_url}</a></p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 306,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Ký hóa đơn thất bại',
                'subject' => 'Ký hóa đơn thất bại cho {tenant_name}',
                'message' => '<p>Xin chào {owner_name},</p><p>Việc ký hóa đơn cho <strong>{tenant_name}</strong> đã thất bại.</p><p><strong>Số hóa đơn:</strong> {invoice_number}</p><p><strong>Nguyên nhân:</strong> {error_message}</p><p><strong>PDF:</strong> <a href="{pdf_url}">{pdf_url}</a></p><p><strong>XML:</strong> <a href="{xml_url}">{xml_url}</a></p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 306,
            ],
        ],
        'unmatched_payment_alert' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'Unmatched Payment Alert',
                'subject' => 'Unmatched payment detected',
                'message' => '<p>Dear Operations Team,</p><p>An unmatched payment was detected for <strong>{tenant_name}</strong>.</p><p><strong>Payment reference:</strong> {payment_reference}</p><p><strong>Amount:</strong> {payment_amount} {currency}</p><p><strong>Transaction code:</strong> {transaction_code}</p><p><strong>Provider:</strong> {provider_name}</p><p><strong>Webhook:</strong> {webhook_url}</p><p><strong>Job ID:</strong> {job_id}</p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 307,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Cảnh báo giao dịch không khớp',
                'subject' => 'Phát hiện giao dịch không khớp',
                'message' => '<p>Xin chào Đội vận hành,</p><p>Phát hiện một giao dịch không khớp cho <strong>{tenant_name}</strong>.</p><p><strong>Mã tham chiếu:</strong> {payment_reference}</p><p><strong>Số tiền:</strong> {payment_amount} {currency}</p><p><strong>Mã giao dịch:</strong> {transaction_code}</p><p><strong>Nhà cung cấp:</strong> {provider_name}</p><p><strong>Webhook:</strong> {webhook_url}</p><p><strong>Job ID:</strong> {job_id}</p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 307,
            ],
        ],
        'webhook_failed' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'Webhook Failed',
                'subject' => 'Webhook failed',
                'message' => '<p>Dear Operations Team,</p><p>A webhook failed for <strong>{module_name}</strong>.</p><p><strong>Provider:</strong> {provider_name}</p><p><strong>Reason:</strong> {error_message}</p><p><strong>Webhook URL:</strong> {webhook_url}</p><p><strong>Job ID:</strong> {job_id}</p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 308,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Webhook thất bại',
                'subject' => 'Webhook đã thất bại',
                'message' => '<p>Xin chào Đội vận hành,</p><p>Một webhook đã thất bại cho <strong>{module_name}</strong>.</p><p><strong>Nhà cung cấp:</strong> {provider_name}</p><p><strong>Nguyên nhân:</strong> {error_message}</p><p><strong>Webhook URL:</strong> {webhook_url}</p><p><strong>Job ID:</strong> {job_id}</p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 308,
            ],
        ],
        'cron_failed' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'Cron Failed',
                'subject' => 'KT SaaS cron failed',
                'message' => '<p>Dear Operations Team,</p><p>The KT SaaS cron runner failed.</p><p><strong>Reason:</strong> {error_message}</p><p><strong>Job ID:</strong> {job_id}</p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 309,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Cron thất bại',
                'subject' => 'Cron KT SaaS đã thất bại',
                'message' => '<p>Xin chào Đội vận hành,</p><p>Trình chạy cron của KT SaaS đã thất bại.</p><p><strong>Nguyên nhân:</strong> {error_message}</p><p><strong>Job ID:</strong> {job_id}</p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 309,
            ],
        ],
        'backup_completed' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'Backup Completed',
                'subject' => 'Backup completed for {tenant_name}',
                'message' => '<p>Dear Operations Team,</p><p>The backup for <strong>{tenant_name}</strong> completed successfully.</p><p><strong>Backup ID:</strong> {job_id}</p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 310,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Backup hoàn tất',
                'subject' => 'Backup đã hoàn tất cho {tenant_name}',
                'message' => '<p>Xin chào Đội vận hành,</p><p>Bản backup cho <strong>{tenant_name}</strong> đã hoàn tất thành công.</p><p><strong>Backup ID:</strong> {job_id}</p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 310,
            ],
        ],
        'backup_failed' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'Backup Failed',
                'subject' => 'Backup failed for {tenant_name}',
                'message' => '<p>Dear Operations Team,</p><p>The backup for <strong>{tenant_name}</strong> failed.</p><p><strong>Backup ID:</strong> {job_id}</p><p><strong>Reason:</strong> {error_message}</p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 311,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Backup thất bại',
                'subject' => 'Backup thất bại cho {tenant_name}',
                'message' => '<p>Xin chào Đội vận hành,</p><p>Bản backup cho <strong>{tenant_name}</strong> đã thất bại.</p><p><strong>Backup ID:</strong> {job_id}</p><p><strong>Nguyên nhân:</strong> {error_message}</p><p><strong>Workspace:</strong> <a href="{workspace_url}">{workspace_url}</a></p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 311,
            ],
        ],
        'provider_connection_failed' => [
            'english' => [
                'type' => 'notifications',
                'name' => 'Provider Connection Failed',
                'subject' => 'Provider connection failed for {module_name}',
                'message' => '<p>Dear Operations Team,</p><p>The provider connection failed for <strong>{module_name}</strong>.</p><p><strong>Provider:</strong> {provider_name}</p><p><strong>Reason:</strong> {error_message}</p><p><strong>Webhook URL:</strong> {webhook_url}</p><p><strong>Job ID:</strong> {job_id}</p><p>Kind regards,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 312,
            ],
            'vietnamese' => [
                'type' => 'notifications',
                'name' => 'Lỗi kết nối nhà cung cấp',
                'subject' => 'Kết nối nhà cung cấp thất bại cho {module_name}',
                'message' => '<p>Xin chào Đội vận hành,</p><p>Kết nối nhà cung cấp đã thất bại cho <strong>{module_name}</strong>.</p><p><strong>Nhà cung cấp:</strong> {provider_name}</p><p><strong>Nguyên nhân:</strong> {error_message}</p><p><strong>Webhook URL:</strong> {webhook_url}</p><p><strong>Job ID:</strong> {job_id}</p><p>Trân trọng,<br>{email_signature}</p>',
                'fromname' => '{companyname} | KT SaaS',
                'plaintext' => 0,
                'active' => 1,
                'order' => 312,
            ],
        ],
    ];
}

function kt_saas_clear_tenant_option_cache($tenantId = null)
{
    if (!isset($GLOBALS['kt_saas_tenant_option_cache']) || !is_array($GLOBALS['kt_saas_tenant_option_cache'])) {
        $GLOBALS['kt_saas_tenant_option_cache'] = [];
        return;
    }

    if ($tenantId === null) {
        $GLOBALS['kt_saas_tenant_option_cache'] = [];
        return;
    }

    $prefix = (int) $tenantId . '|';
    foreach (array_keys($GLOBALS['kt_saas_tenant_option_cache']) as $cacheKey) {
        if (strpos((string) $cacheKey, $prefix) === 0) {
            unset($GLOBALS['kt_saas_tenant_option_cache'][$cacheKey]);
        }
    }
}

function kt_saas_filter_tenant_scoped_option($value, $name)
{
    if (!kt_saas_is_tenant_runtime()) {
        return $value;
    }

    $name = trim((string) $name);
    if ($name === '') {
        return $value;
    }

    static $tenantScopedKeys = [
        'companyname' => true,
        'company_email' => true,
        'companyphonenumber' => true,
        'company_vat' => true,
        'company_logo' => true,
        'company_logo_dark' => true,
        'favicon' => true,

        'active_language' => true,
        'default_language' => true,
        'default_timezone' => true,
        'default_currency' => true,
        'dateformat' => true,
        'time_format' => true,

        'email_signature' => true,
        'email_header' => true,
        'email_footer' => true,
        'kt_saas_mail_from_name' => true,
        'kt_saas_mail_reply_to_email' => true,
        'bcc_emails' => true,

        'invoice_company_name' => true,
        'invoice_company_address' => true,
        'invoice_company_city' => true,
        'invoice_company_state' => true,
        'invoice_company_country_code' => true,
        'invoice_company_postal_code' => true,
        'invoice_company_phonenumber' => true,

        'invoice_due_after' => true,
        'estimate_due_after' => true,
        'invoice_prefix' => true,
        'next_invoice_number' => true,
        'invoice_number_format' => true,
        'estimate_prefix' => true,
        'next_estimate_number' => true,
        'estimate_number_format' => true,
        'credit_note_prefix' => true,
        'next_credit_note_number' => true,
        'credit_note_number_format' => true,
        'predefined_clientnote_invoice' => true,
        'predefined_terms_invoice' => true,

        'view_invoice_only_logged_in' => true,
        'exclude_invoice_from_client_area_with_draft_status' => true,
        'show_sale_agent_on_invoices' => true,
        'show_project_on_invoice' => true,
        'show_total_paid_on_invoice' => true,
        'show_credits_applied_on_invoice' => true,
        'show_amount_due_on_invoice' => true,

        'view_estimate_only_logged_in' => true,
        'show_sale_agent_on_estimates' => true,
        'show_project_on_estimate' => true,
        'estimate_auto_convert_to_invoice_on_client_accept' => true,
        'exclude_estimate_from_client_area_with_draft_status' => true,

        'show_subscriptions_in_customers_area' => true,
        'after_subscription_payment_captured' => true,
        'create_invoice_from_recurring_only_on_paid_invoices' => true,
        'new_recurring_invoice_action' => true,

        'attach_invoice_to_payment_receipt_email' => true,
        'automatically_send_invoice_overdue_reminder_after' => true,
        'automatically_resend_invoice_overdue_reminder_after' => true,
        'invoice_due_notice_before' => true,
        'invoice_due_notice_resend_after' => true,
        'send_estimate_expiry_reminder_before' => true,
        'contract_expiration_before' => true,
        'contract_sign_reminder_every_days' => true,
    ];

    if (!isset($tenantScopedKeys[$name])) {
        return $value;
    }

    $CI = &get_instance();
    if (!isset($CI->db) || !$CI->db->table_exists(db_prefix() . 'options')) {
        return $value;
    }

    $tenant = function_exists('kt_saas_current_tenant') ? kt_saas_current_tenant() : null;
    $tenantId = (int) ($tenant['id'] ?? 0);
    $databaseName = isset($CI->db->database) ? (string) $CI->db->database : '';
    $cacheKey = $tenantId . '|' . $databaseName . '|' . $name;

    if (!isset($GLOBALS['kt_saas_tenant_option_cache']) || !is_array($GLOBALS['kt_saas_tenant_option_cache'])) {
        $GLOBALS['kt_saas_tenant_option_cache'] = [];
    }

    if (array_key_exists($cacheKey, $GLOBALS['kt_saas_tenant_option_cache'])) {
        kt_saas_debug_option_resolution($name, 'cache', $GLOBALS['kt_saas_tenant_option_cache'][$cacheKey]);
        return $GLOBALS['kt_saas_tenant_option_cache'][$cacheKey];
    }

    $row = $CI->db
        ->select('value')
        ->from(db_prefix() . 'options')
        ->where('name', $name)
        ->get()
        ->row_array();

    if (is_array($row) && array_key_exists('value', $row)) {
        $GLOBALS['kt_saas_tenant_option_cache'][$cacheKey] = (string) $row['value'];
        kt_saas_debug_option_resolution($name, 'tenant_db', $GLOBALS['kt_saas_tenant_option_cache'][$cacheKey]);
        return $GLOBALS['kt_saas_tenant_option_cache'][$cacheKey];
    }

    kt_saas_debug_option_resolution($name, 'fallback', $value);
    return $value;
}

function kt_saas_debug_option_resolution($key, $source, $resolvedValue)
{
    if (!defined('KT_SAAS_DEBUG_OPTION_RESOLVER') || KT_SAAS_DEBUG_OPTION_RESOLVER !== true) {
        return;
    }

    static $logged = [];
    $key = (string) $key;
    $source = (string) $source;
    $index = $key . '|' . $source;
    if (isset($logged[$index])) {
        return;
    }
    $logged[$index] = true;

    $tenant = function_exists('kt_saas_current_tenant') ? kt_saas_current_tenant() : null;
    $tenantId = (int) ($tenant['id'] ?? 0);
    $tenantCode = (string) ($tenant['tenant_code'] ?? '');

    $preview = (string) $resolvedValue;
    if (in_array($key, ['email_header', 'email_footer', 'email_signature'], true)) {
        $preview = '[html:' . strlen($preview) . ' chars]';
    } else {
        $preview = function_exists('mb_substr') ? mb_substr($preview, 0, 120) : substr($preview, 0, 120);
    }

    log_message(
        'debug',
        sprintf(
            '[KT_SAAS_OPTION_RESOLVER] tenant_id=%d tenant_code=%s key=%s source=%s value_preview=%s',
            $tenantId,
            $tenantCode,
            $key,
            $source,
            str_replace(["\r", "\n"], ' ', $preview)
        )
    );
}

function kt_saas_runtime_entitlements()
{
    if (!kt_saas_is_tenant_runtime()) {
        return null;
    }

    require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');
    return new TenantEntitlementService();
}

function kt_saas_limit_guard_before_create_staff_member($data)
{
    $service = kt_saas_runtime_entitlements();
    if ($service) {
        $service->assertCanCreate('staff');
    }

    return $data;
}

function kt_saas_after_staff_member_created($staffId)
{
    $service = kt_saas_runtime_entitlements();
    if ($service) {
        $service->persistUsageSnapshot();
    }
}

function kt_saas_after_staff_member_deleted($payload)
{
    $service = kt_saas_runtime_entitlements();
    if ($service) {
        $service->persistUsageSnapshot();
    }
}

function kt_saas_limit_guard_before_client_added($data)
{
    $service = kt_saas_runtime_entitlements();
    if ($service) {
        $service->assertCanCreate('clients');
    }

    return $data;
}

function kt_saas_after_client_created($payload)
{
    $service = kt_saas_runtime_entitlements();
    if ($service) {
        $service->persistUsageSnapshot();
    }
}

function kt_saas_after_client_deleted($clientId)
{
    $service = kt_saas_runtime_entitlements();
    if ($service) {
        $service->persistUsageSnapshot();
    }
}

function kt_saas_limit_guard_before_add_project($data)
{
    $service = kt_saas_runtime_entitlements();
    if ($service) {
        $service->assertCanCreate('projects');
    }

    return $data;
}

function kt_saas_after_project_created($projectId)
{
    $service = kt_saas_runtime_entitlements();
    if ($service) {
        $service->persistUsageSnapshot();
    }
}

function kt_saas_after_project_deleted($projectId)
{
    $service = kt_saas_runtime_entitlements();
    if ($service) {
        $service->persistUsageSnapshot();
    }
}

function kt_saas_limit_guard_before_invoice_added($hook)
{
    $service = kt_saas_runtime_entitlements();
    if ($service) {
        $service->assertCanCreate('invoices');
    }

    return $hook;
}

function kt_saas_after_invoice_added($invoiceId)
{
    $service = kt_saas_runtime_entitlements();
    if ($service) {
        $service->persistUsageSnapshot();
    }
}

function kt_saas_after_invoice_deleted($invoiceId)
{
    $service = kt_saas_runtime_entitlements();
    if ($service) {
        $service->persistUsageSnapshot();
    }
}

function kt_saas_assert_automation_allowed($increment = 1)
{
    $service = kt_saas_runtime_entitlements();
    if ($service) {
        $service->assertCanCreate('automation', (int) $increment);
    }
}

function kt_saas_increment_automation_usage($value = 1)
{
    if (!kt_saas_is_tenant_runtime()) {
        return false;
    }

    require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantLimitService.php');
    $tenant = kt_saas_current_tenant();
    if (!$tenant) {
        return false;
    }

    $service = new TenantLimitService();
    return $service->incrementUsage((int) $tenant['id'], 'core', 'automation', (float) $value);
}

function kt_saas_decrement_automation_usage($value = 1)
{
    if (!kt_saas_is_tenant_runtime()) {
        return false;
    }

    require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantLimitService.php');
    $tenant = kt_saas_current_tenant();
    if (!$tenant) {
        return false;
    }

    $service = new TenantLimitService();
    return $service->decrementUsage((int) $tenant['id'], 'core', 'automation', (float) $value);
}

function kt_saas_filter_tenant_email_template_from_headers($headers, $template = null)
{
    $runtimeTransport = config_item('kt_saas_mail_runtime_transport');
    $runtimeIdentity = config_item('kt_saas_mail_runtime_identity');

    if (!is_array($headers) || (!kt_saas_is_tenant_runtime() && !(is_array($runtimeTransport) && !empty($runtimeTransport)) && !(is_array($runtimeIdentity) && !empty($runtimeIdentity)))) {
        return $headers;
    }

    require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEmailProviderService.php');
    $service = new TenantEmailProviderService();
    return $service->prepareTemplateHeaders($headers);
}

function kt_saas_filter_tenant_email_template_send_payload($payload)
{
    $runtimeTransport = config_item('kt_saas_mail_runtime_transport');
    $runtimeIdentity = config_item('kt_saas_mail_runtime_identity');

    if (!is_array($payload) || empty($payload['template']) || !is_object($payload['template']) || (!kt_saas_is_tenant_runtime() && !(is_array($runtimeTransport) && !empty($runtimeTransport)) && !(is_array($runtimeIdentity) && !empty($runtimeIdentity)))) {
        return $payload;
    }

    require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEmailProviderService.php');
    $service = new TenantEmailProviderService();
    $prepared = $service->prepareSimpleEmailPayload([
        'reply_to' => isset($payload['template']->reply_to) ? (string) $payload['template']->reply_to : '',
    ]);

    if (!empty($prepared['reply_to']) && empty($payload['template']->reply_to)) {
        $payload['template']->reply_to = $prepared['reply_to'];
    }

    return $payload;
}

function kt_saas_filter_before_send_simple_email($payload)
{
    $runtimeTransport = config_item('kt_saas_mail_runtime_transport');
    $runtimeIdentity = config_item('kt_saas_mail_runtime_identity');

    if (!is_array($payload) || (!kt_saas_is_tenant_runtime() && !(is_array($runtimeTransport) && !empty($runtimeTransport)) && !(is_array($runtimeIdentity) && !empty($runtimeIdentity)))) {
        return $payload;
    }

    require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEmailProviderService.php');
    $service = new TenantEmailProviderService();
    return $service->prepareSimpleEmailPayload($payload);
}

function kt_saas_log_email_template_sent($payload)
{
    if (!kt_saas_should_log_email_event() || !is_array($payload)) {
        return;
    }

    require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEmailProviderService.php');
    $service = new TenantEmailProviderService();
    $template = isset($payload['template']) && is_object($payload['template']) ? $payload['template'] : null;
    $runtimeIdentity = config_item('kt_saas_mail_runtime_identity');
    $runtimeMeta = kt_saas_email_runtime_meta();
    $messageId = trim((string) ($payload['message_id'] ?? ''));
    if ($messageId === '') {
        $messageId = trim((string) config_item('kt_saas_mail_last_message_id'));
    }
    $fromEmail = trim((string) ($template->fromemail ?? ''));
    if ($fromEmail === '' && is_array($runtimeIdentity) && !empty($runtimeIdentity['from_email'])) {
        $fromEmail = (string) $runtimeIdentity['from_email'];
    }
    $service->logEmailResult(
        (string) ($payload['email'] ?? ''),
        (string) ($template->subject ?? ''),
        'sent',
        '',
        (string) ($runtimeIdentity['provider'] ?? ($service->resolveForCurrentTenant('transactional')['provider'] ?? 'system_smtp')),
        $runtimeMeta['tenant_id'] > 0 ? $runtimeMeta['tenant_id'] : null,
        'transactional',
        $messageId,
        $fromEmail,
        $runtimeMeta['related_type'] !== '' ? $runtimeMeta['related_type'] : (string) ($template->slug ?? ($template->type ?? 'email_template')),
        $runtimeMeta['related_id'] !== '' ? $runtimeMeta['related_id'] : (string) ($template->emailtemplateid ?? '')
    );
}

function kt_saas_log_email_template_failed($payload)
{
    if (!kt_saas_should_log_email_event() || !is_array($payload)) {
        return;
    }

    require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEmailProviderService.php');
    $service = new TenantEmailProviderService();
    $template = isset($payload['template']) && is_object($payload['template']) ? $payload['template'] : null;
    $runtimeIdentity = config_item('kt_saas_mail_runtime_identity');
    $runtimeMeta = kt_saas_email_runtime_meta();
    $fromEmail = trim((string) ($template->fromemail ?? ''));
    if ($fromEmail === '' && is_array($runtimeIdentity) && !empty($runtimeIdentity['from_email'])) {
        $fromEmail = (string) $runtimeIdentity['from_email'];
    }
    $service->logEmailResult(
        (string) ($payload['send_to'] ?? ''),
        (string) ($template->subject ?? ''),
        'failed',
        (string) ($payload['error'] ?? 'template_send_failed'),
        (string) ($runtimeIdentity['provider'] ?? ($service->resolveForCurrentTenant('transactional')['provider'] ?? 'system_smtp')),
        $runtimeMeta['tenant_id'] > 0 ? $runtimeMeta['tenant_id'] : null,
        'transactional',
        '',
        $fromEmail,
        $runtimeMeta['related_type'] !== '' ? $runtimeMeta['related_type'] : (string) ($template->slug ?? ($template->type ?? 'email_template')),
        $runtimeMeta['related_id'] !== '' ? $runtimeMeta['related_id'] : (string) ($template->emailtemplateid ?? '')
    );
}

function kt_saas_log_simple_email_sent($payload)
{
    if (!kt_saas_should_log_email_event() || !is_array($payload)) {
        return;
    }

    require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEmailProviderService.php');
    $service = new TenantEmailProviderService();
    $cnf = isset($payload['cnf']) && is_array($payload['cnf']) ? $payload['cnf'] : [];
    $runtimeMeta = kt_saas_email_runtime_meta();
    $runtimeIdentity = config_item('kt_saas_mail_runtime_identity');
    $messageId = trim((string) ($payload['message_id'] ?? ''));
    if ($messageId === '') {
        $messageId = trim((string) config_item('kt_saas_mail_last_message_id'));
    }
    $service->logEmailResult(
        (string) ($payload['email'] ?? ''),
        (string) ($payload['subject'] ?? ''),
        'sent',
        '',
        (string) ($runtimeIdentity['provider'] ?? ($service->resolveForCurrentTenant('notification')['provider'] ?? 'system_smtp')),
        $runtimeMeta['tenant_id'] > 0 ? $runtimeMeta['tenant_id'] : null,
        'notification',
        $messageId,
        (string) ($cnf['from_email'] ?? ''),
        $runtimeMeta['related_type'] !== '' ? $runtimeMeta['related_type'] : 'simple_email',
        $runtimeMeta['related_id'] !== '' ? $runtimeMeta['related_id'] : null
    );
}

function kt_saas_log_simple_email_failed($payload)
{
    if (!kt_saas_should_log_email_event() || !is_array($payload)) {
        return;
    }

    require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEmailProviderService.php');
    $service = new TenantEmailProviderService();
    $cnf = isset($payload['cnf']) && is_array($payload['cnf']) ? $payload['cnf'] : [];
    $runtimeMeta = kt_saas_email_runtime_meta();
    $runtimeIdentity = config_item('kt_saas_mail_runtime_identity');
    $service->logEmailResult(
        (string) ($payload['email'] ?? ''),
        (string) ($payload['subject'] ?? ''),
        'failed',
        (string) ($payload['error'] ?? 'simple_email_failed'),
        (string) ($runtimeIdentity['provider'] ?? ($service->resolveForCurrentTenant('notification')['provider'] ?? 'system_smtp')),
        $runtimeMeta['tenant_id'] > 0 ? $runtimeMeta['tenant_id'] : null,
        'notification',
        '',
        (string) ($cnf['from_email'] ?? ''),
        $runtimeMeta['related_type'] !== '' ? $runtimeMeta['related_type'] : 'simple_email',
        $runtimeMeta['related_id'] !== '' ? $runtimeMeta['related_id'] : null
    );
}

function kt_saas_send_via_brevo_api($payload)
{
    $apiKey = trim((string) ($payload['api_key'] ?? ''));
    $toEmail = trim((string) ($payload['to_email'] ?? ''));
    if ($apiKey === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid Brevo API payload.'];
    }

    $endpoint = 'https://api.brevo.com/v3/smtp/email';
    $request = [
        'sender' => [
            'email' => (string) ($payload['from_email'] ?? ''),
            'name'  => (string) ($payload['from_name'] ?? ''),
        ],
        'to' => [
            ['email' => $toEmail],
        ],
        'subject' => (string) ($payload['subject'] ?? ''),
        'htmlContent' => (string) ($payload['html_content'] ?? ''),
    ];

    $textContent = trim((string) ($payload['text_content'] ?? ''));
    if ($textContent !== '') {
        $request['textContent'] = $textContent;
    }

    $replyTo = trim((string) ($payload['reply_to'] ?? ''));
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $request['replyTo'] = ['email' => $replyTo];
    }

    $cc = isset($payload['cc']) && is_array($payload['cc']) ? $payload['cc'] : [];
    if (!empty($cc)) {
        $request['cc'] = array_values(array_filter(array_map(static function ($email) {
            $email = trim((string) $email);
            return filter_var($email, FILTER_VALIDATE_EMAIL) ? ['email' => $email] : null;
        }, $cc)));
    }

    $bcc = isset($payload['bcc']) && is_array($payload['bcc']) ? $payload['bcc'] : [];
    if (!empty($bcc)) {
        $request['bcc'] = array_values(array_filter(array_map(static function ($email) {
            $email = trim((string) $email);
            return filter_var($email, FILTER_VALIDATE_EMAIL) ? ['email' => $email] : null;
        }, $bcc)));
    }

    $attachments = isset($payload['attachments']) && is_array($payload['attachments']) ? $payload['attachments'] : [];
    if (!empty($attachments)) {
        $apiAttachments = [];
        foreach ($attachments as $attachment) {
            $path = (string) ($attachment['attachment'] ?? '');
            if ($path === '' || !is_file($path) || !is_readable($path)) {
                continue;
            }
            $name = (string) ($attachment['filename'] ?? basename($path));
            $content = base64_encode((string) file_get_contents($path));
            $apiAttachments[] = ['name' => $name, 'content' => $content];
        }
        if (!empty($apiAttachments)) {
            $request['attachment'] = $apiAttachments;
        }
    }

    $json = json_encode($request);
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'content-type: application/json',
        'api-key: ' . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'message' => $error !== '' ? $error : 'cURL error', 'http_code' => $httpCode];
    }

    $decoded = json_decode((string) $response, true);
    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true,
            'message' => 'sent',
            'http_code' => $httpCode,
            'message_id' => (string) ($decoded['messageId'] ?? ''),
            'response' => $decoded,
        ];
    }

    return [
        'success' => false,
        'message' => (string) ($decoded['message'] ?? ('Brevo API HTTP ' . $httpCode)),
        'http_code' => $httpCode,
        'response' => $decoded,
    ];
}

function kt_saas_filter_tenant_sidebar_menu_items($items)
{
    require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantUiService.php');
    $uiService = new TenantUiService();
    return $uiService->filterSidebarMenu($items);
}

function kt_saas_filter_tenant_setup_menu_items($items)
{
    require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantUiService.php');
    $uiService = new TenantUiService();
    return $uiService->filterSetupMenu($items);
}

function kt_saas_filter_tenant_setup_menu_visibility($visible)
{
    if (kt_saas_is_tenant_runtime()) {
        return false;
    }

    return $visible;
}
