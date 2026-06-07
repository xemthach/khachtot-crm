<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('kt_saas_is_module_request')) {
    function kt_saas_is_module_request()
    {
        $uri = uri_string();

        return strpos($uri, 'admin/kt_saas') === 0;
    }
}

if (!function_exists('kt_saas_settings_url')) {
    function kt_saas_settings_url()
    {
        if (kt_saas_is_tenant_runtime()) {
            return admin_url('kt_saas/tenant_settings');
        }

        return admin_url('settings');
    }
}

if (!function_exists('kt_saas_workspace_settings_allowed_staff_ids')) {
    function kt_saas_workspace_settings_allowed_staff_ids()
    {
        return kt_saas_allowed_active_staff_ids_from_option('kt_saas_workspace_settings_staff_ids');
    }
}

if (!function_exists('kt_saas_workspace_governance_allowed_staff_ids')) {
    function kt_saas_workspace_governance_allowed_staff_ids()
    {
        return kt_saas_workspace_governance_manage_allowed_staff_ids();
    }
}

if (!function_exists('kt_saas_workspace_governance_view_allowed_staff_ids')) {
    function kt_saas_workspace_governance_view_allowed_staff_ids()
    {
        return kt_saas_allowed_active_staff_ids_from_option('kt_saas_workspace_governance_view_staff_ids');
    }
}

if (!function_exists('kt_saas_workspace_governance_manage_allowed_staff_ids')) {
    function kt_saas_workspace_governance_manage_allowed_staff_ids()
    {
        $ids = kt_saas_allowed_active_staff_ids_from_option('kt_saas_workspace_governance_manage_staff_ids');
        if (!empty($ids)) {
            return $ids;
        }

        return kt_saas_allowed_active_staff_ids_from_option('kt_saas_workspace_governance_staff_ids');
    }
}

if (!function_exists('kt_saas_allowed_active_staff_ids_from_option')) {
    function kt_saas_allowed_active_staff_ids_from_option($optionName)
    {
        if (!kt_saas_is_tenant_runtime()) {
            return [];
        }

        $raw = (string) get_option($optionName);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        $ids = is_array($decoded) ? $decoded : preg_split('/[\s,]+/', $raw);
        if (!is_array($ids)) {
            return [];
        }

        $allowed = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $allowed[$id] = $id;
            }
        }

        $CI = &get_instance();
        if (!isset($CI->db) || !$CI->db->table_exists(db_prefix() . 'staff') || empty($allowed)) {
            return array_values($allowed);
        }

        $rows = $CI->db
            ->select('staffid')
            ->from(db_prefix() . 'staff')
            ->where('active', 1)
            ->where_in('staffid', array_values($allowed))
            ->get()
            ->result_array();

        $active = [];
        foreach ($rows as $row) {
            $staffId = (int) ($row['staffid'] ?? 0);
            if ($staffId > 0) {
                $active[$staffId] = $staffId;
            }
        }

        return array_values($active);
    }
}

if (!function_exists('kt_saas_can_manage_workspace_settings')) {
    function kt_saas_can_manage_workspace_settings($staffId = null)
    {
        if (!kt_saas_is_tenant_runtime() || !is_staff_logged_in()) {
            return false;
        }

        $staffId = $staffId !== null ? (int) $staffId : (int) get_staff_user_id();
        if ($staffId <= 0) {
            return false;
        }

        if (is_admin($staffId)) {
            return true;
        }

        return in_array($staffId, kt_saas_workspace_settings_allowed_staff_ids(), true);
    }
}

if (!function_exists('kt_saas_can_manage_workspace_governance')) {
    function kt_saas_can_view_workspace_governance($staffId = null)
    {
        if (!kt_saas_is_tenant_runtime() || !is_staff_logged_in()) {
            return false;
        }

        $staffId = $staffId !== null ? (int) $staffId : (int) get_staff_user_id();
        if ($staffId <= 0) {
            return false;
        }

        if (is_admin($staffId)) {
            return kt_saas_workspace_feature_allowed('workspace.governance.view', false);
        }

        if (in_array($staffId, kt_saas_workspace_governance_manage_allowed_staff_ids(), true)) {
            return kt_saas_workspace_feature_allowed('workspace.governance.view', false);
        }

        return in_array($staffId, kt_saas_workspace_governance_view_allowed_staff_ids(), true)
            && kt_saas_workspace_feature_allowed('workspace.governance.view', false);
    }
}

if (!function_exists('kt_saas_can_manage_workspace_governance')) {
    function kt_saas_can_manage_workspace_governance($staffId = null)
    {
        if (!kt_saas_is_tenant_runtime() || !is_staff_logged_in()) {
            return false;
        }

        $staffId = $staffId !== null ? (int) $staffId : (int) get_staff_user_id();
        if ($staffId <= 0) {
            return false;
        }

        if (is_admin($staffId)) {
            return kt_saas_workspace_feature_allowed('workspace.governance.manage', false);
        }

        return in_array($staffId, kt_saas_workspace_governance_manage_allowed_staff_ids(), true)
            && kt_saas_workspace_feature_allowed('workspace.governance.manage', false);
    }
}

if (!function_exists('kt_saas_landlord_capabilities')) {
    function kt_saas_landlord_capabilities()
    {
        return [
            'kt_saas_view',
            'kt_saas_manage_tenants',
            'kt_saas_delete_tenants',
            'kt_saas_manage_plans',
            'kt_saas_delete_plans',
            'kt_saas_manage_billing',
            'kt_saas_manage_domains',
            'kt_saas_manage_modules',
            'kt_saas_manage_usage',
            'kt_saas_manage_backups',
            'kt_saas_manage_settings',
            'kt_saas_run_provisioning',
        ];
    }
}

if (!function_exists('kt_saas_is_landlord_context')) {
    function kt_saas_is_landlord_context()
    {
        return !kt_saas_is_tenant_runtime();
    }
}

if (!function_exists('kt_saas_is_tenant_context')) {
    function kt_saas_is_tenant_context()
    {
        return kt_saas_is_tenant_runtime();
    }
}

if (!function_exists('kt_saas_landlord_only')) {
    function kt_saas_landlord_only($message = 'This area is available only in landlord context.')
    {
        if (!kt_saas_is_landlord_context()) {
            show_error($message, 403, 'Forbidden');
            exit;
        }
    }
}

if (!function_exists('kt_saas_is_landlord_only_admin_route')) {
    function kt_saas_landlord_only_admin_route_patterns()
    {
        return [
            '#^admin/modules(?:/|$)#',
            '#^admin/app_modules(?:/|$)#',
            '#^admin/settings(?:/|$)#',
            '#^admin/custom_fields(?:/|$)#',
            '#^admin/roles(?:/|$)#',
            '#^admin/gdpr(?:/|$)#',
            '#^admin/emails(?:/|$)#',
        ];
    }
}

if (!function_exists('kt_saas_is_landlord_only_admin_route')) {
    function kt_saas_is_landlord_only_admin_route($uri = null)
    {
        $uri = $uri === null ? uri_string() : trim((string) $uri, '/');

        foreach (kt_saas_landlord_only_admin_route_patterns() as $pattern) {
            if (preg_match($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('kt_saas_staff_can')) {
    function kt_saas_staff_can($capability)
    {
        $capability = trim((string) $capability);

        if ($capability !== '' && in_array($capability, kt_saas_landlord_capabilities(), true) && !kt_saas_is_landlord_context()) {
            return false;
        }

        return staff_can($capability, KT_SAAS_MODULE) || is_admin();
    }
}

if (!function_exists('kt_saas_email_trigger_registry_service')) {
    function kt_saas_email_trigger_registry_service()
    {
        static $service = null;
        if ($service === null) {
            require_once module_dir_path(KT_SAAS_MODULE, 'services/EmailTriggerRegistryService.php');
            $service = new EmailTriggerRegistryService();
        }

        return $service;
    }
}

if (!function_exists('kt_saas_email_branding_context_service')) {
    function kt_saas_email_branding_context_service()
    {
        static $service = null;
        if ($service === null) {
            require_once module_dir_path(KT_SAAS_MODULE, 'services/EmailBrandingContextResolverService.php');
            $service = new EmailBrandingContextResolverService();
        }

        return $service;
    }
}

if (!function_exists('kt_saas_email_duplicate_guard_service')) {
    function kt_saas_email_duplicate_guard_service()
    {
        static $service = null;
        if ($service === null) {
            require_once module_dir_path(KT_SAAS_MODULE, 'services/EmailDuplicateGuardService.php');
            $service = new EmailDuplicateGuardService();
        }

        return $service;
    }
}

if (!function_exists('kt_saas_branding_resolver_service')) {
    function kt_saas_branding_resolver_service()
    {
        static $service = null;
        if ($service === null) {
            require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantBrandingResolverService.php');
            $service = new TenantBrandingResolverService();
        }

        return $service;
    }
}

if (!function_exists('kt_saas_localization_resolver_service')) {
    function kt_saas_localization_resolver_service()
    {
        static $service = null;
        if ($service === null) {
            require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantLocalizationResolverService.php');
            $service = new TenantLocalizationResolverService();
        }

        return $service;
    }
}

if (!function_exists('kt_saas_email_trigger_registry')) {
    function kt_saas_email_trigger_registry($eventKey = null)
    {
        $service = kt_saas_email_trigger_registry_service();
        if ($eventKey === null) {
            return $service->all();
        }

        return $service->get($eventKey);
    }
}

if (!function_exists('kt_saas_resolve_email_branding_context')) {
    function kt_saas_resolve_email_branding_context($eventKey = null, array $context = [])
    {
        return kt_saas_email_branding_context_service()->resolve($eventKey, $context);
    }
}

if (!function_exists('kt_saas_resolve_branding_context')) {
    function kt_saas_resolve_branding_context(array $context = [])
    {
        try {
            return kt_saas_branding_resolver_service()->resolveCurrent($context);
        } catch (Throwable $e) {
            log_message('error', 'KT SaaS branding resolver failed: ' . $e->getMessage());

            return [
                'scope' => kt_saas_is_tenant_runtime() ? 'tenant' : 'landlord',
                'source' => 'resolver_fallback',
                'company_name' => '',
                'logo' => '',
                'dark_logo' => '',
                'favicon' => '',
                'address' => '',
                'phone' => '',
                'tax_code' => '',
                'website' => '',
                'tenant_id' => 0,
                'tenant_code' => '',
                'fallback_used' => true,
                'fallback_fields' => ['resolver_error'],
            ];
        }
    }
}

if (!function_exists('kt_saas_resolve_tenant_branding_context')) {
    function kt_saas_resolve_tenant_branding_context($tenant = null, array $context = [])
    {
        try {
            return kt_saas_branding_resolver_service()->resolveTenant($tenant, $context);
        } catch (Throwable $e) {
            log_message('error', 'KT SaaS tenant branding resolver failed: ' . $e->getMessage());

            return [
                'scope' => 'tenant',
                'source' => 'resolver_fallback',
                'company_name' => '',
                'logo' => '',
                'dark_logo' => '',
                'favicon' => '',
                'address' => '',
                'phone' => '',
                'tax_code' => '',
                'website' => '',
                'tenant_id' => (int) (is_array($tenant) ? ($tenant['id'] ?? 0) : 0),
                'tenant_code' => (string) (is_array($tenant) ? ($tenant['tenant_code'] ?? '') : ''),
                'fallback_used' => true,
                'fallback_fields' => ['resolver_error'],
            ];
        }
    }
}

if (!function_exists('kt_saas_resolve_localization_context')) {
    function kt_saas_resolve_localization_context(array $context = [])
    {
        try {
            return kt_saas_localization_resolver_service()->resolveCurrent($context);
        } catch (Throwable $e) {
            log_message('error', 'KT SaaS localization resolver failed: ' . $e->getMessage());

            return [
                'scope' => kt_saas_is_tenant_runtime() ? 'tenant' : 'landlord',
                'source' => 'resolver_fallback',
                'language' => 'english',
                'timezone' => 'UTC',
                'currency' => 'USD',
                'date_format' => 'Y-m-d|%Y-%m-%d',
                'time_format' => '24',
                'tenant_id' => 0,
                'tenant_code' => '',
                'fallback_used' => true,
                'fallback_fields' => ['resolver_error'],
            ];
        }
    }
}

if (!function_exists('kt_saas_resolve_tenant_localization_context')) {
    function kt_saas_resolve_tenant_localization_context($tenant = null, array $context = [])
    {
        try {
            return kt_saas_localization_resolver_service()->resolveTenant($tenant, $context);
        } catch (Throwable $e) {
            log_message('error', 'KT SaaS tenant localization resolver failed: ' . $e->getMessage());

            return [
                'scope' => 'tenant',
                'source' => 'resolver_fallback',
                'language' => 'english',
                'timezone' => 'UTC',
                'currency' => 'USD',
                'date_format' => 'Y-m-d|%Y-%m-%d',
                'time_format' => '24',
                'tenant_id' => (int) (is_array($tenant) ? ($tenant['id'] ?? 0) : 0),
                'tenant_code' => (string) (is_array($tenant) ? ($tenant['tenant_code'] ?? '') : ''),
                'fallback_used' => true,
                'fallback_fields' => ['resolver_error'],
            ];
        }
    }
}

if (!function_exists('kt_saas_reserve_email_event')) {
    function kt_saas_reserve_email_event($eventKey, array $context = [])
    {
        return kt_saas_email_duplicate_guard_service()->reserve($eventKey, $context);
    }
}

if (!function_exists('kt_saas_send_email_event')) {
    function kt_saas_send_email_event($eventKey, array $context = [], array $options = [])
    {
        $CI = &get_instance();
        if (!isset($CI->Kt_saas_model)) {
            $CI->load->model('kt_saas/Kt_saas_model');
        }

        return $CI->Kt_saas_model->send_email_event($eventKey, $context, $options);
    }
}

if (!function_exists('kt_saas_landlord_ops_email')) {
    function kt_saas_landlord_ops_email($default = '')
    {
        $candidates = [
            get_option('company_email'),
            get_option('companyemail'),
            get_option('support_email'),
            get_option('smtp_email'),
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        return trim((string) $default);
    }
}

if (!function_exists('kt_saas_mark_email_event_sent')) {
    function kt_saas_mark_email_event_sent($eventKey, $dedupeKey, array $context = [])
    {
        return kt_saas_email_duplicate_guard_service()->markSent($eventKey, $dedupeKey, $context);
    }
}

if (!function_exists('kt_saas_mark_email_event_failed')) {
    function kt_saas_mark_email_event_failed($eventKey, $dedupeKey, $errorMessage = '', array $context = [])
    {
        return kt_saas_email_duplicate_guard_service()->markFailed($eventKey, $dedupeKey, $errorMessage, $context);
    }
}

if (!function_exists('kt_saas_status_badge_class')) {
    function kt_saas_status_badge_class($status)
    {
        $map = [
            'draft'      => 'default',
            'active'     => 'success',
            'trial'      => 'info',
            'grace'      => 'warning',
            'issued'     => 'info',
            'paid'       => 'success',
            'partial'    => 'warning',
            'pending_payment' => 'warning',
            'overdue'    => 'danger',
            'cancelled'  => 'default',
            'expired'    => 'default',
            'info'       => 'info',
            'success'    => 'success',
            'warning'    => 'warning',
            'danger'     => 'danger',
            'suspended'  => 'danger',
            'terminated' => 'danger',
            'archived'   => 'default',
            'failed'     => 'danger',
            'queued'     => 'warning',
            'running'    => 'info',
            'pending'    => 'warning',
            'verified'   => 'success',
            'mismatch'   => 'danger',
            'ready'      => 'success',
            'attention'  => 'danger',
            'ssl_pending'=> 'warning',
            'dns_pending'=> 'warning',
        ];

        return $map[$status] ?? 'default';
    }
}

if (!function_exists('kt_saas_domain_readiness_statuses')) {
    function kt_saas_domain_readiness_statuses()
    {
        return [
            'pending'     => 'Đang chờ',
            'dns_pending' => 'Chờ DNS',
            'ssl_pending' => 'Chờ SSL',
            'ready'       => 'Sẵn sàng',
            'attention'   => 'Cần xử lý',
        ];
    }
}

if (!function_exists('kt_saas_domain_statuses')) {
    function kt_saas_domain_statuses()
    {
        return [
            'pending'  => 'Đang chờ',
            'verified' => 'Đã xác minh',
            'mismatch' => 'Không khớp',
            'failed'   => 'Thất bại',
            'active'   => 'Hoạt động',
        ];
    }
}

if (!function_exists('kt_saas_plan_catalog')) {
    function kt_saas_plan_catalog()
    {
        return [
            'free'       => 'Miễn phí',
            'trial'      => 'Dùng thử',
            'basic'      => 'Basic',
            'pro'        => 'Pro',
            'enterprise' => 'Enterprise',
        ];
    }
}

if (!function_exists('kt_saas_tenant_statuses')) {
    function kt_saas_tenant_statuses()
    {
        return [
            'draft'      => 'Nháp',
            'trial'      => 'Dùng thử',
            'active'     => 'Hoạt động',
            'grace'      => 'Ân hạn',
            'suspended'  => 'Tạm ngưng',
            'terminated' => 'Chấm dứt',
            'archived'   => 'Lưu trữ',
        ];
    }
}

if (!function_exists('kt_saas_subscription_statuses')) {
    function kt_saas_subscription_statuses()
    {
        return [
            'trial'      => 'Dùng thử',
            'active'     => 'Hoạt động',
            'grace'      => 'Ân hạn',
            'suspended'  => 'Tạm ngưng',
            'cancelled'  => 'Đã hủy',
            'expired'    => 'Hết hạn',
            'terminated' => 'Chấm dứt',
        ];
    }
}

if (!function_exists('kt_saas_billing_cycles')) {
    function kt_saas_billing_cycles()
    {
        return [
            'monthly'   => 'Hàng tháng',
            'quarterly' => 'Hàng quý',
            'yearly'    => 'Hàng năm',
        ];
    }
}

if (!function_exists('kt_saas_invoice_statuses')) {
    function kt_saas_invoice_statuses()
    {
        return [
            'draft'           => 'Nháp',
            'issued'          => 'Đã phát hành',
            'pending_payment' => 'Đang chờ thanh toán',
            'partial'         => 'Thanh toán một phần',
            'paid'            => 'Đã thanh toán',
            'overdue'         => 'Quá hạn',
            'cancelled'       => 'Đã hủy',
        ];
    }
}

if (!function_exists('kt_saas_payment_statuses')) {
    function kt_saas_payment_statuses()
    {
        return [
            'pending' => 'Đang chờ',
            'paid'    => 'Đã thanh toán',
            'failed'  => 'Thất bại',
            'void'    => 'Hủy',
        ];
    }
}

if (!function_exists('kt_saas_metric_labels')) {
    function kt_saas_metric_labels()
    {
        return [
            'staff'        => 'Nhân sự',
            'clients'      => 'Khách hàng',
            'storage_mb'   => 'Dung lượng lưu trữ',
            'invoices'     => 'Hóa đơn',
            'projects'     => 'Dự án',
            'api_daily'    => 'API hằng ngày',
            'warehouses'   => 'Kho hàng',
            'automation'   => 'Tự động hóa',
            'automations'  => 'Tự động hóa',
        ];
    }
}

if (!function_exists('kt_saas_metric_label')) {
    function kt_saas_metric_label($metricKey)
    {
        $metricKey = (string) $metricKey;
        $labels = kt_saas_metric_labels();

        return $labels[$metricKey] ?? ucwords(str_replace('_', ' ', $metricKey));
    }
}

if (!function_exists('kt_saas_metric_value')) {
    function kt_saas_metric_value($metricKey, $value, $allowUnlimited = false)
    {
        $metricKey = (string) $metricKey;
        $value = (float) $value;

        if ($allowUnlimited && (int) $value === 0) {
            return _l('kt_saas_unlimited');
        }

        if ($metricKey === 'storage_mb') {
            return number_format($value, 2) . ' MB';
        }

        if ($metricKey === 'api_daily') {
            return number_format($value, 0) . ' yêu cầu/ngày';
        }

        return number_format($value, 0);
    }
}

if (!function_exists('kt_saas_module_display_name')) {
    function kt_saas_module_display_name($moduleCode)
    {
        $moduleCode = trim((string) $moduleCode);

        $map = [
            'kt_inventory' => 'Quản lý kho',
            'kt_sepay'     => 'Thanh toán SePay',
            'einvoice'     => 'Hóa đơn điện tử',
            'exports'      => 'Xuất dữ liệu CSV',
            'brevo_mail'   => 'Tích hợp email Brevo',
            'backup'       => 'Sao lưu dữ liệu',
            'goals'        => 'Mục tiêu',
            'menu_setup'   => 'Thiết lập menu',
            'openai'       => 'Tích hợp OpenAI',
            'ideal'        => 'Stripe iDEAL',
            'surveys'      => 'Khảo sát',
            'theme_style'  => 'Giao diện',
        ];

        return $map[$moduleCode] ?? $moduleCode;
    }
}

if (!function_exists('kt_saas_landlord_only_modules')) {
    function kt_saas_landlord_only_modules()
    {
        return [];
    }
}

if (!function_exists('kt_saas_is_tenant_safe_module')) {
    function kt_saas_is_tenant_safe_module($moduleCode)
    {
        $moduleCode = strtolower(trim((string) $moduleCode));
        if ($moduleCode === '') {
            return false;
        }

        return !in_array($moduleCode, kt_saas_landlord_only_modules(), true);
    }
}

if (!function_exists('kt_saas_default_overage_rates')) {
    function kt_saas_default_overage_rates()
    {
        return [
            'staff'      => 5.00,
            'clients'    => 0.10,
            'projects'   => 1.00,
            'invoices'   => 0.20,
            'warehouses' => 10.00,
            'storage_mb' => 0.05,
        ];
    }
}

if (!function_exists('kt_saas_overage_rates')) {
    function kt_saas_overage_rates()
    {
        $default = kt_saas_default_overage_rates();
        $json = trim((string) kt_saas_get_option('kt_saas_overage_rate_json', ''));
        if ($json === '') {
            return $default;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return $default;
        }

        foreach ($default as $metric => $amount) {
            if (isset($decoded[$metric]) && is_numeric($decoded[$metric])) {
                $default[$metric] = (float) $decoded[$metric];
            }
        }

        return $default;
    }
}

if (!function_exists('kt_saas_invoice_reason_label')) {
    function kt_saas_invoice_reason_label($invoice)
    {
        $payload = json_decode((string) ($invoice['payload_json'] ?? ''), true);
        if (!is_array($payload)) {
            return 'Tiêu chuẩn';
        }

        $reason = (string) ($payload['reason'] ?? '');
        $map = [
            'subscription_renewal' => 'Gia hạn gói',
            'plan_change_request'  => 'Đổi gói dịch vụ',
            'overage_charge'       => 'Vượt giới hạn',
        ];

        return $map[$reason] ?? 'Tiêu chuẩn';
    }
}

if (!function_exists('kt_saas_provision_job_statuses')) {
    function kt_saas_provision_job_statuses()
    {
        return [
            'queued'  => 'Đang chờ',
            'running' => 'Đang chạy',
            'failed'  => 'Thất bại',
            'done'    => 'Hoàn tất',
        ];
    }
}

if (!function_exists('kt_saas_get_option')) {
    function kt_saas_get_option($name, $default = '')
    {
        $name = (string) $name;
        if (
            strpos($name, 'kt_saas_') === 0
            && function_exists('kt_saas_is_tenant_runtime')
            && kt_saas_is_tenant_runtime()
        ) {
            try {
                $CI = &get_instance();
                $landlordDb = $CI->load->database([
                    'dsn'      => '',
                    'hostname' => APP_DB_HOSTNAME,
                    'username' => APP_DB_USERNAME,
                    'password' => APP_DB_PASSWORD,
                    'database' => APP_DB_NAME,
                    'dbdriver' => defined('APP_DB_DRIVER') ? APP_DB_DRIVER : 'mysqli',
                    'dbprefix' => db_prefix(),
                    'pconnect' => false,
                    'db_debug' => false,
                    'cache_on' => false,
                    'cachedir' => '',
                    'char_set' => defined('APP_DB_CHARSET') ? APP_DB_CHARSET : 'utf8mb4',
                    'dbcollat' => defined('APP_DB_COLLATION') ? APP_DB_COLLATION : 'utf8mb4_unicode_ci',
                    'swap_pre' => '',
                    'encrypt'  => defined('APP_DB_ENCRYPT') ? APP_DB_ENCRYPT : false,
                    'compress' => false,
                    'stricton' => false,
                    'failover' => [],
                    'save_queries' => false,
                ], true);

                $row = $landlordDb
                    ->select('value')
                    ->where('name', $name)
                    ->get(db_prefix() . 'options')
                    ->row_array();

                $value = is_array($row) ? (string) ($row['value'] ?? '') : '';
                return $value === '' ? $default : $value;
            } catch (Throwable $e) {
                log_message('error', 'KT SaaS landlord option lookup failed for ' . $name . ': ' . $e->getMessage());
            }
        }

        $value = get_option($name);

        return $value === '' ? $default : $value;
    }
}

if (!function_exists('kt_saas_current_tenant')) {
    function kt_saas_current_tenant()
    {
        return $GLOBALS['kt_saas_current_tenant'] ?? null;
    }
}

if (!function_exists('kt_saas_is_tenant_runtime')) {
    function kt_saas_is_tenant_runtime()
    {
        return !empty($GLOBALS['kt_saas_current_tenant']);
    }
}

if (!function_exists('kt_saas_tenant_runtime_statuses')) {
    function kt_saas_tenant_runtime_statuses()
    {
        return ['active', 'trial', 'grace'];
    }
}

if (!function_exists('kt_saas_tenant_code_slug')) {
    function kt_saas_tenant_code_slug($tenant = null)
    {
        $tenant = is_array($tenant) ? $tenant : kt_saas_current_tenant();
        $tenantCode = strtolower(trim((string) ($tenant['tenant_code'] ?? 'tenant')));

        return preg_replace('/[^a-z0-9_\-]/', '_', $tenantCode) ?: 'tenant';
    }
}

if (!function_exists('kt_saas_tenant_cache_prefix')) {
    function kt_saas_tenant_cache_prefix($tenant = null)
    {
        $tenant = is_array($tenant) ? $tenant : kt_saas_current_tenant();
        if (!$tenant) {
            return 'landlord';
        }

        $tenantId = (int) ($tenant['id'] ?? 0);
        return 'tenant:' . $tenantId . ':' . kt_saas_tenant_code_slug($tenant);
    }
}

if (!function_exists('kt_saas_cache_key')) {
    function kt_saas_cache_key($suffix, $tenant = null)
    {
        $suffix = trim((string) $suffix);
        $suffix = ltrim($suffix, ':');

        return kt_saas_tenant_cache_prefix($tenant) . ($suffix !== '' ? ':' . $suffix : '');
    }
}

if (!function_exists('kt_saas_tenant_storage_path')) {
    function kt_saas_tenant_storage_path($append = '', $tenant = null)
    {
        $tenant = is_array($tenant) ? $tenant : kt_saas_current_tenant();
        $basePath = APPPATH . '../uploads/tenants';

        if ($tenant) {
            $configuredPath = trim((string) ($tenant['storage_path'] ?? ''));
            $basePath = $configuredPath !== '' ? $configuredPath : $basePath . '/' . kt_saas_tenant_code_slug($tenant);
        }

        $append = trim(str_replace('\\', '/', (string) $append), '/');
        return $append !== '' ? rtrim($basePath, '/\\') . '/' . $append : rtrim($basePath, '/\\');
    }
}

if (!function_exists('kt_saas_tenant_branding_path')) {
    function kt_saas_tenant_branding_path($tenant = null, $append = '')
    {
        $tenant = is_array($tenant) ? $tenant : kt_saas_current_tenant();
        $tenantId = (int) ($tenant['id'] ?? $tenant ?? 0);
        if ($tenantId <= 0) {
            return '';
        }

        $append = trim(str_replace('\\', '/', (string) $append), '/');
        $basePath = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'kt_saas' . DIRECTORY_SEPARATOR . 'tenants' . DIRECTORY_SEPARATOR . $tenantId . DIRECTORY_SEPARATOR . 'branding';

        return $append !== '' ? $basePath . DIRECTORY_SEPARATOR . $append : $basePath;
    }
}

if (!function_exists('kt_saas_tenant_branding_url')) {
    function kt_saas_tenant_branding_url($tenant = null, $filename = '')
    {
        $tenant = is_array($tenant) ? $tenant : kt_saas_current_tenant();
        $tenantId = (int) ($tenant['id'] ?? $tenant ?? 0);
        $filename = trim(str_replace('\\', '/', (string) $filename), '/');
        if ($tenantId <= 0 || $filename === '') {
            return '';
        }

        return base_url('uploads/kt_saas/tenants/' . $tenantId . '/branding/' . rawurlencode($filename));
    }
}

if (!function_exists('kt_saas_current_profile')) {
    function kt_saas_current_profile()
    {
        return $GLOBALS['kt_saas_current_profile'] ?? null;
    }
}

if (!function_exists('kt_saas_auth_context')) {
    function kt_saas_auth_context()
    {
        return $GLOBALS['kt_saas_auth_context'] ?? null;
    }
}

if (!function_exists('kt_saas_tenant_manifest_path')) {
    function kt_saas_tenant_manifest_path($tenant)
    {
        if (!is_array($tenant) || empty($tenant['tenant_code'])) {
            return null;
        }

        return module_dir_path(KT_SAAS_MODULE, 'tenant_bootstrap/manifests/' . strtolower((string) $tenant['tenant_code']) . '.json');
    }
}

if (!function_exists('kt_saas_tenant_manifest')) {
    function kt_saas_tenant_manifest($tenant)
    {
        $path = kt_saas_tenant_manifest_path($tenant);
        if (!$path || !is_file($path)) {
            return null;
        }

        $json = file_get_contents($path);
        if ($json === false || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }
}

if (!function_exists('kt_saas_tenant_runtime_host')) {
    function kt_saas_tenant_runtime_host($tenant)
    {
        if (!is_array($tenant)) {
            return '';
        }

        $host = trim((string) ($tenant['custom_domain'] ?? ''));
        if ($host !== '') {
            return $host;
        }

        $host = trim((string) ($tenant['host'] ?? ''));
        if ($host !== '') {
            return $host;
        }

        $subdomain = trim((string) ($tenant['subdomain'] ?? ''));
        $baseDomain = trim((string) kt_saas_get_option('kt_saas_base_domain', 'crm.local'));

        if ($subdomain !== '' && strpos($subdomain, '.') === false && $baseDomain !== '') {
            return $subdomain . '.' . $baseDomain;
        }

        return $subdomain;
    }
}

if (!function_exists('kt_saas_tenant_public_base_url')) {
    function kt_saas_tenant_public_base_url($tenant)
    {
        $scheme = parse_url(APP_BASE_URL, PHP_URL_SCHEME) ?: 'https';
        $host = kt_saas_tenant_runtime_host($tenant);

        if ($host === '') {
            return rtrim(APP_BASE_URL, '/');
        }

        return $scheme . '://' . $host;
    }
}

if (!function_exists('kt_saas_url_with_tenant_host')) {
    function kt_saas_url_with_tenant_host($url, $tenant)
    {
        $url = trim((string) $url);
        $tenantHost = kt_saas_tenant_runtime_host($tenant);

        if ($url === '' || $tenantHost === '') {
            return $url;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return $url;
        }

        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $query['tenant_host'] = $tenantHost;
        $scheme = $parts['scheme'] ?? (parse_url(APP_BASE_URL, PHP_URL_SCHEME) ?: 'https');
        $rebuilt = $scheme . '://' . $tenantHost;
        if (!empty($parts['port'])) {
            $rebuilt .= ':' . $parts['port'];
        }

        $rebuilt .= $parts['path'] ?? '';
        $queryString = http_build_query($query);
        if ($queryString !== '') {
            $rebuilt .= '?' . $queryString;
        }

        if (!empty($parts['fragment'])) {
            $rebuilt .= '#' . $parts['fragment'];
        }

        return $rebuilt;
    }
}

if (!function_exists('kt_saas_tenant_onboarding')) {
    function kt_saas_tenant_onboarding($tenant)
    {
        $manifest = kt_saas_tenant_manifest($tenant);
        $onboarding = $manifest['onboarding'] ?? null;

        if (is_array($onboarding)) {
            unset($onboarding['set_password_url'], $onboarding['new_pass_key']);
            if (!empty($onboarding['admin_login_url'])) {
                $onboarding['admin_login_url'] = kt_saas_url_with_tenant_host($onboarding['admin_login_url'], $tenant);
            }
        }

        return is_array($onboarding) ? $onboarding : null;
    }
}

if (!function_exists('kt_saas_tenant_module_allowed')) {
    function kt_saas_tenant_module_allowed($moduleCode)
    {
        $profile = kt_saas_current_profile();
        if (!$profile) {
            return true;
        }

        $moduleCode = strtolower(trim((string) $moduleCode));
        if ($moduleCode === KT_SAAS_MODULE) {
            return false;
        }

        $overrides = $profile['module_overrides'] ?? [];
        if (array_key_exists($moduleCode, $overrides)) {
            return $overrides[$moduleCode] === 'enabled';
        }

        $moduleCodes = $profile['module_codes'] ?? [];
        if (empty($moduleCodes)) {
            $managedModules = $profile['managed_modules'] ?? [];
            if (in_array($moduleCode, $managedModules, true)) {
                $planCode = strtolower(trim((string) ($profile['plan']['plan_code'] ?? '')));
                $fallback = [
                    'free'       => [],
                    'trial'      => [],
                    'basic'      => [],
                    'pro'        => ['kt_inventory'],
                    'enterprise' => ['kt_inventory'],
                ];

                return in_array($moduleCode, $fallback[$planCode] ?? [], true);
            }

            return true;
        }

        return in_array($moduleCode, $moduleCodes, true);
    }
}

if (!function_exists('kt_saas_tenant_plan_limit')) {
    function kt_saas_tenant_plan_limit($limitKey, $default = 0)
    {
        $profile = kt_saas_current_profile();
        if (!$profile) {
            return $default;
        }

        return (int) ($profile['limits'][$limitKey] ?? $default);
    }
}

if (!function_exists('kt_saas_workspace_feature_allowed')) {
    function kt_saas_workspace_feature_allowed($featureKey, $default = true)
    {
        $profile = kt_saas_current_profile();
        if (!$profile) {
            return $default;
        }

        $featureKey = strtolower(trim((string) $featureKey));
        if ($featureKey === '') {
            return $default;
        }

        $workspaceFeatures = $profile['workspace_features'] ?? [];
        if (array_key_exists($featureKey, $workspaceFeatures)) {
            return (bool) $workspaceFeatures[$featureKey];
        }

        $features = $profile['features'] ?? [];
        if (array_key_exists($featureKey, $features)) {
            return (bool) $features[$featureKey];
        }

        return $default;
    }
}

if (!function_exists('kt_saas_feature_allowed')) {
    function kt_saas_feature_allowed($moduleName, $featureKey, $default = true)
    {
        $moduleName = strtolower(trim((string) $moduleName));
        $featureKey = strtolower(trim((string) $featureKey));
        if ($moduleName === '' || $featureKey === '') {
            return $default;
        }

        $profile = kt_saas_current_profile();
        if ($profile) {
            $features = $profile['features'] ?? [];
            if (array_key_exists($featureKey, $features)) {
                return (bool) $features[$featureKey];
            }
        }

        if (function_exists('kt_saas_is_tenant_runtime') && kt_saas_is_tenant_runtime() && function_exists('kt_saas_current_tenant')) {
            $tenant = kt_saas_current_tenant();
            if (!empty($tenant['id'])) {
                require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');
                $service = new TenantEntitlementService();

                return $service->canUseFeature((int) $tenant['id'], $moduleName, $featureKey);
            }
        }

        return $default;
    }
}
