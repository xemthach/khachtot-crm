<?php

defined('BASEPATH') or exit('No direct script access allowed');

class KtSaasTenantBootstrap
{
    protected $sessionTenantKey = '_kt_saas_tenant_id';
    protected $sessionHostKey = '_kt_saas_tenant_host';

    protected function trace($message)
    {
        $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'kt_saas_bootstrap_trace.log';
        @file_put_contents($path, '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
    }

    public function handle()
    {
        $this->trace('handle:start host=' . $this->currentHost() . ' uri=' . uri_string());
        if (!empty($GLOBALS['kt_saas_bootstrap_completed'])) {
            $this->trace('handle:already_completed');
            return;
        }

        if (is_cli()) {
            $this->trace('handle:cli_skip');
            return;
        }

        $moduleRoot = APP_MODULES_PATH . 'kt_saas' . DIRECTORY_SEPARATOR;
        if (!is_dir($moduleRoot)) {
            $this->trace('handle:module_missing');
            return;
        }

        $CI = &get_instance();

        if (!function_exists('kt_saas_get_option')) {
            $helperPath = $moduleRoot . 'helpers' . DIRECTORY_SEPARATOR . 'kt_saas_helper.php';
            if (file_exists($helperPath)) {
                require_once $helperPath;
            }
        }

        $runtimeEnabled = function_exists('kt_saas_get_option')
            ? kt_saas_get_option('kt_saas_runtime_enabled', '0')
            : '0';

        if ($runtimeEnabled !== '1') {
            $this->trace('handle:runtime_disabled value=' . $runtimeEnabled);
            return;
        }

        require_once $moduleRoot . 'tenant_bootstrap' . DIRECTORY_SEPARATOR . 'TenantResolver.php';
        require_once $moduleRoot . 'tenant_bootstrap' . DIRECTORY_SEPARATOR . 'DatabaseSwitcher.php';
        require_once $moduleRoot . 'services' . DIRECTORY_SEPARATOR . 'TenantEntitlementService.php';
        require_once $moduleRoot . 'services' . DIRECTORY_SEPARATOR . 'TenantContextService.php';

        $resolver = new TenantResolver();
        $tenant = $resolver->resolveByHost($this->currentHost());
        if (!$tenant || empty($tenant['resolved'])) {
            $this->trace('handle:tenant_not_resolved');
            return;
        }

        $this->trace('handle:tenant_resolved id=' . (int) ($tenant['id'] ?? 0) . ' code=' . (string) ($tenant['tenant_code'] ?? '') . ' status=' . (string) ($tenant['status'] ?? '') . ' prov=' . (string) ($tenant['provisioning_status'] ?? ''));

        $uri = uri_string();
        if (!$this->tenantCanServeRequests($tenant, $uri)) {
            $this->trace('handle:tenant_unavailable uri=' . $uri);
            $this->renderTenantUnavailable($tenant);
            return;
        }
        if ($this->isLandlordOnlyCoreRoute($uri)) {
            $this->trace('handle:blocked_landlord_route uri=' . $uri);
            log_message('error', 'KT SaaS Security: Blocked landlord-only core route for tenant ID: ' . ($tenant['id'] ?? 0) . ' URI=' . $uri);
            $this->renderLandlordOnlyCoreRouteForbidden($CI, $uri);
        }

        $switcher = new DatabaseSwitcher();
        $switchResult = $switcher->switchConnection($tenant);
        if (empty($switchResult['switched'])) {
            $this->trace('handle:switch_failed message=' . (string) ($switchResult['message'] ?? 'unknown'));
            log_message('error', 'KT SaaS database switch failed for tenant [' . ($tenant['tenant_code'] ?? 'unknown') . ']: ' . ($switchResult['message'] ?? 'unknown error'));
            return;
        }
        $this->trace('handle:switch_ok db=' . (string) ($switchResult['database'] ?? 'unknown'));

        $entitlements = new TenantEntitlementService();
        $routeAccess = $entitlements->canAccessRequestUri($uri, $tenant);
        if (empty($routeAccess['allowed'])) {
            $this->trace('handle:route_forbidden reason=' . (string) ($routeAccess['reason'] ?? 'unknown'));
            $this->renderRouteForbidden($routeAccess);
            return;
        }

        $profile = $routeAccess['profile'] ?? [];

        if (!empty($_FILES)) {
            $limits = $profile['limits'] ?? [];
            $storageLimit = (int) ($limits['storage_mb'] ?? 0);
            if ($storageLimit > 0) {
                $uploadBytes = 0;
                foreach ($_FILES as $fileData) {
                    if (!isset($fileData['error'])) {
                        continue;
                    }

                    if (is_array($fileData['error'])) {
                        foreach ($fileData['error'] as $idx => $err) {
                            if ($err === UPLOAD_ERR_OK) {
                                $uploadBytes += (int) $fileData['size'][$idx];
                            }
                        }
                    } elseif ($fileData['error'] === UPLOAD_ERR_OK) {
                        $uploadBytes += (int) $fileData['size'];
                    }
                }

                if ($uploadBytes > 0) {
                    $uploadMb = $uploadBytes / 1048576;
                    $usage = $entitlements->getTenantUsageSnapshot($tenant);
                    $currentMb = (float) ($usage['storage_mb'] ?? 0);
                    $projectedMb = $currentMb + $uploadMb;

                    if ($projectedMb > $storageLimit) {
                        $message = 'Tenant storage limit exceeded. Limit: ' . $storageLimit . ' MB, Current: ' . round($currentMb, 2) . ' MB, Uploading: ' . round($uploadMb, 2) . ' MB.';
                        log_message('error', 'KT SaaS limit exceeded [storage_mb] ' . $message);
                        if (function_exists('set_alert')) {
                            set_alert('warning', $message);
                        }
                        show_error($message, 403, 'Storage Limit Exceeded');
                        exit;
                    }
                }
            }
        }

        if (strpos($uri, 'api/') !== false || strpos($uri, 'api') === 0) {
            $entitlements->checkAndIncrementApiLimit($tenant, $profile);
        }

        $context = new TenantContextService();
        $context->setTenant($tenant);
        $context->setProfile($profile);

        $this->applyTenantRuntimeContext($CI, $tenant, $profile);
        $this->guardTenantSessionBoundary($CI, $tenant);
        $this->trace('handle:runtime_applied');
        $GLOBALS['kt_saas_bootstrap_completed'] = true;
        $this->trace('handle:complete');
    }

    protected function currentHost()
    {
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
        $host = strtolower(trim((string) $host));

        if (strpos($host, ':') !== false) {
            $parts = explode(':', $host);
            $host = $parts[0];
        }

        return $host;
    }

    protected function tenantCanServeRequests(array $tenant, $uri = '')
    {
        $status = (string) ($tenant['status'] ?? 'draft');
        $provisioningStatus = (string) ($tenant['provisioning_status'] ?? 'queued');

        $uri = ltrim((string) $uri, '/');
        if ($provisioningStatus !== 'done' && preg_match('#^kt_saas/checkout/(invoice|pay)/\d+/[a-f0-9]+$#', $uri)) {
            return true;
        }

        return in_array($status, kt_saas_tenant_runtime_statuses(), true) && $provisioningStatus === 'done';
    }

    protected function renderTenantUnavailable(array $tenant)
    {
        $status = (string) ($tenant['status'] ?? 'draft');
        $provisioningStatus = (string) ($tenant['provisioning_status'] ?? 'queued');

        log_message('error', 'KT SaaS tenant runtime blocked for [' . ($tenant['tenant_code'] ?? 'unknown') . '] status=' . $status . ' provisioning=' . $provisioningStatus);

        $data = $this->tenantUnavailableViewData($tenant);
        set_status_header((int) ($data['http_status'] ?? 503));
        header('Content-Type: text/html; charset=UTF-8');

        try {
            echo $this->renderTenantStatusView($data);
        } catch (Throwable $e) {
            log_message('error', 'KT SaaS tenant unavailable view failed: ' . $e->getMessage());
            echo $this->fallbackTenantStatusHtml($data);
        }

        exit;
    }

    protected function renderTenantStatusView(array $data)
    {
        $viewPath = APP_MODULES_PATH . 'kt_saas' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'tenant_status.php';
        if (!is_file($viewPath)) {
            throw new RuntimeException('Tenant status view is missing.');
        }

        ob_start();
        extract($data, EXTR_SKIP);
        include $viewPath;

        return (string) ob_get_clean();
    }

    protected function tenantUnavailableViewData(array $tenant)
    {
        $state = $this->tenantUnavailableState($tenant);
        $tenantBaseUrl = rtrim($this->tenantBaseUrl($tenant), '/');
        $uri = trim((string) uri_string(), '/');
        $currentUrl = $tenantBaseUrl . '/' . ($uri !== '' ? $uri : '');
        $companyName = $this->safeTenantPublicName($tenant);

        $states = [
            'provisioning_running' => [
                'http_status' => 202,
                'badge'       => 'Đang khởi tạo',
                'title'       => 'Không gian làm việc đang được khởi tạo',
                'message'     => 'Khách Tốt đang chuẩn bị hệ thống CRM riêng cho doanh nghiệp của bạn. Vui lòng quay lại sau ít phút.',
                'support'     => 'Bạn có thể tải lại trang sau 1-2 phút. Nếu trạng thái này kéo dài, vui lòng liên hệ đội ngũ hỗ trợ Khách Tốt.',
                'tone'        => 'info',
            ],
            'pending_payment' => [
                'http_status' => 402,
                'badge'       => 'Chờ thanh toán',
                'title'       => 'Không gian làm việc đang chờ kích hoạt',
                'message'     => 'Đơn đăng ký cần hoàn tất thanh toán trước khi hệ thống được mở sử dụng.',
                'support'     => 'Vui lòng kiểm tra email hướng dẫn thanh toán hoặc liên hệ đội ngũ hỗ trợ Khách Tốt nếu cần hỗ trợ thêm.',
                'tone'        => 'warning',
            ],
            'provisioning_failed' => [
                'http_status' => 503,
                'badge'       => 'Cần hỗ trợ',
                'title'       => 'Khởi tạo không gian làm việc chưa hoàn tất',
                'message'     => 'Hệ thống ghi nhận quá trình khởi tạo cần được kiểm tra thêm. Vui lòng liên hệ đội ngũ hỗ trợ Khách Tốt.',
                'support'     => 'Đội ngũ vận hành sẽ rà soát và kích hoạt lại không gian làm việc khi sự cố được xử lý.',
                'tone'        => 'danger',
            ],
            'suspended' => [
                'http_status' => 403,
                'badge'       => 'Tạm ngưng',
                'title'       => 'Không gian làm việc đang tạm ngưng',
                'message'     => 'Không gian làm việc hiện chưa thể truy cập. Vui lòng liên hệ quản trị viên doanh nghiệp hoặc bộ phận hỗ trợ.',
                'support'     => 'Trạng thái truy cập sẽ được khôi phục sau khi vấn đề tài khoản được xử lý.',
                'tone'        => 'warning',
            ],
            'unavailable' => [
                'http_status' => 404,
                'badge'       => 'Không khả dụng',
                'title'       => 'Không tìm thấy không gian làm việc',
                'message'     => 'Tên miền này chưa được liên kết với không gian làm việc đang hoạt động.',
                'support'     => 'Vui lòng kiểm tra lại địa chỉ truy cập hoặc quay về trang chủ Khách Tốt.',
                'tone'        => 'muted',
            ],
        ];

        $copy = $states[$state] ?? $states['provisioning_running'];

        return [
            'http_status'      => $copy['http_status'],
            'status_key'       => $state,
            'tone'             => $copy['tone'],
            'brand_name'       => 'Khách Tốt CRM',
            'landlord_logo_url' => $this->landlordLogoUrl(),
            'company_name'     => $companyName,
            'badge'            => $copy['badge'],
            'title'            => $copy['title'],
            'message'          => $copy['message'],
            'support_text'     => $copy['support'],
            'primary_action'   => [
                'label' => 'Thử tải lại',
                'url'   => $currentUrl,
            ],
            'secondary_action' => [
                'label' => 'Về trang chủ Khách Tốt',
                'url'   => rtrim(defined('APP_BASE_URL') ? APP_BASE_URL : site_url(), '/') . '/',
            ],
            'steps'            => [
                'Tiếp nhận đăng ký',
                'Chuẩn bị dữ liệu',
                'Cấu hình truy cập',
                'Sẵn sàng sử dụng',
            ],
        ];
    }

    protected function tenantUnavailableState(array $tenant)
    {
        $status = strtolower(trim((string) ($tenant['status'] ?? 'draft')));
        $provisioningStatus = strtolower(trim((string) ($tenant['provisioning_status'] ?? 'queued')));

        if (!empty($tenant['deleted_at']) || in_array($status, ['deleted', 'archived', 'terminated'], true)) {
            return 'unavailable';
        }

        if ($status === 'suspended' || !empty($tenant['suspended_at'])) {
            return 'suspended';
        }

        if (in_array($provisioningStatus, ['failed', 'error'], true) || $this->tenantHasFailedProvisionJob($tenant)) {
            return 'provisioning_failed';
        }

        if ($this->tenantHasPendingPayment($tenant)) {
            return 'pending_payment';
        }

        if ($provisioningStatus !== 'done') {
            return 'provisioning_running';
        }

        return 'unavailable';
    }

    protected function tenantHasPendingPayment(array $tenant)
    {
        $tenantId = (int) ($tenant['id'] ?? 0);
        if ($tenantId <= 0) {
            return false;
        }

        try {
            $CI = &get_instance();
            if (!isset($CI->db) || !$CI->db->table_exists(db_prefix() . 'kt_saas_invoices')) {
                return false;
            }

            $invoice = $CI->db
                ->select('status')
                ->from(db_prefix() . 'kt_saas_invoices')
                ->where('tenant_id', $tenantId)
                ->where('(deleted_at IS NULL OR deleted_at = "")', null, false)
                ->order_by('id', 'DESC')
                ->limit(1)
                ->get()
                ->row_array();

            if (!is_array($invoice) || empty($invoice['status'])) {
                return false;
            }

            return in_array(strtolower((string) $invoice['status']), ['draft', 'pending', 'pending_payment', 'unpaid', 'overdue'], true);
        } catch (Throwable $e) {
            log_message('error', 'KT SaaS pending payment status lookup failed: ' . $e->getMessage());
            return false;
        }
    }

    protected function tenantHasFailedProvisionJob(array $tenant)
    {
        $tenantId = (int) ($tenant['id'] ?? 0);
        if ($tenantId <= 0) {
            return false;
        }

        try {
            $CI = &get_instance();
            if (!isset($CI->db) || !$CI->db->table_exists(db_prefix() . 'kt_saas_provision_jobs')) {
                return false;
            }

            $job = $CI->db
                ->select('status')
                ->from(db_prefix() . 'kt_saas_provision_jobs')
                ->where('tenant_id', $tenantId)
                ->order_by('id', 'DESC')
                ->limit(1)
                ->get()
                ->row_array();

            return is_array($job) && in_array(strtolower((string) ($job['status'] ?? '')), ['failed', 'error'], true);
        } catch (Throwable $e) {
            log_message('error', 'KT SaaS provision job status lookup failed: ' . $e->getMessage());
            return false;
        }
    }

    protected function safeTenantPublicName(array $tenant)
    {
        $name = trim((string) ($tenant['company_name'] ?? ''));
        if ($name === '') {
            return '';
        }

        $name = strip_tags($name);
        $name = preg_replace('/\s+/', ' ', $name);

        return mb_substr($name, 0, 120);
    }

    protected function fallbackTenantStatusHtml(array $data)
    {
        $title = htmlspecialchars((string) ($data['title'] ?? 'Không gian làm việc chưa sẵn sàng'), ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars((string) ($data['message'] ?? 'Vui lòng thử lại sau ít phút.'), ENT_QUOTES, 'UTF-8');

        return '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $title . '</title></head><body><main style="font-family:Arial,sans-serif;max-width:720px;margin:80px auto;padding:24px;line-height:1.6"><h1>' . $title . '</h1><p>' . $message . '</p></main></body></html>';
    }

    protected function guardTenantSessionBoundary($CI, array $tenant)
    {
        if (!isset($CI->session)) {
            return;
        }

        $tenantId = (int) ($tenant['id'] ?? 0);
        $host = strtolower(trim((string) ($tenant['host'] ?? $this->currentHost())));

        $storedTenantId = (int) $CI->session->userdata($this->sessionTenantKey);
        $storedHost = strtolower(trim((string) $CI->session->userdata($this->sessionHostKey)));

        $hasAuthenticatedUser = (bool) (
            $CI->session->userdata('staff_logged_in')
            || $CI->session->userdata('client_logged_in')
            || $CI->session->userdata('tfa_staffid')
        );

        $boundaryMismatch = false;
        if ($hasAuthenticatedUser && ($storedTenantId === 0 || $storedTenantId !== $tenantId)) {
            $boundaryMismatch = true;
        }
        if ($hasAuthenticatedUser && $storedHost !== '' && $storedHost !== $host) {
            $boundaryMismatch = true;
        }

        if ($boundaryMismatch) {
            $CI->session->unset_userdata([
                'staff_user_id',
                'staff_logged_in',
                'client_user_id',
                'contact_user_id',
                'client_logged_in',
                'tfa_staffid',
                'tfa_remember',
            ]);

            if (method_exists($CI->session, 'sess_regenerate')) {
                $CI->session->sess_regenerate(true);
            }

            log_message('error', 'KT SaaS session boundary reset for tenant [' . ($tenant['tenant_code'] ?? 'unknown') . '] due to host/tenant mismatch.');
        }

        $CI->session->set_userdata([
            $this->sessionTenantKey => $tenantId,
            $this->sessionHostKey   => $host,
        ]);
    }

    protected function renderRouteForbidden(array $routeAccess)
    {
        $CI = &get_instance();
        $moduleCode = (string) ($routeAccess['module'] ?? 'module');
        $reason = (string) ($routeAccess['reason'] ?? 'access_denied');

        log_message('error', 'KT SaaS route blocked for module [' . $moduleCode . '] reason=' . $reason);

        $message = 'This application is not available in your current plan. Upgrade the subscription to continue.';
        if ($reason === 'landlord_only_module') {
            $message = 'This area is available only in landlord context.';
        }

        set_status_header(403);
        if ($CI->input->is_ajax_request() || strpos(uri_string(), 'api/') === 0) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'message' => $message,
            ]);
        } else {
            header('Content-Type: text/html; charset=UTF-8');
            show_error($message, 403, 'Application not enabled');
        }
        exit;
    }

    protected function applyTenantRuntimeContext($CI, array $tenant, array $profile)
    {
        $tenantId = (int) ($tenant['id'] ?? 0);
        $tenantCode = strtolower(trim((string) ($tenant['tenant_code'] ?? 'tenant')));
        $tenantCode = preg_replace('/[^a-z0-9_\-]/', '_', $tenantCode) ?: 'tenant';
        $tenantBaseUrl = $this->tenantBaseUrl($tenant);
        $branding = [];
        $localization = [];

        try {
            if (function_exists('kt_saas_resolve_tenant_branding_context')) {
                $branding = kt_saas_resolve_tenant_branding_context($tenant, ['scope' => 'bootstrap', 'log_fallback' => true]);
            }
        } catch (Throwable $e) {
            log_message('error', 'KT SaaS tenant bootstrap branding resolver failed for [' . ($tenant['tenant_code'] ?? 'unknown') . ']: ' . $e->getMessage());
        }

        try {
            if (function_exists('kt_saas_resolve_tenant_localization_context')) {
                $localization = kt_saas_resolve_tenant_localization_context($tenant, ['scope' => 'bootstrap', 'log_fallback' => true]);
            }
        } catch (Throwable $e) {
            log_message('error', 'KT SaaS tenant bootstrap localization resolver failed for [' . ($tenant['tenant_code'] ?? 'unknown') . ']: ' . $e->getMessage());
        }

        if (!is_array($branding)) {
            $branding = [];
        }
        if (!is_array($localization)) {
            $localization = [];
        }

        $cacheNamespace = 'tenant:' . $tenantId . ':' . $tenantCode;
        $tenantLogo = trim((string) ($branding['logo'] ?? $this->tenantOption($CI, 'company_logo', '')));
        $tenantLogoDark = trim((string) ($branding['dark_logo'] ?? $this->tenantOption($CI, 'company_logo_dark', '')));
        $tenantLogoUrl = trim((string) ($branding['logo_url'] ?? ''));
        $tenantLogoDarkUrl = trim((string) ($branding['dark_logo_url'] ?? ''));
        $tenantFaviconUrl = trim((string) ($branding['favicon_url'] ?? ''));

        $authContext = [
            'tenant_id'    => $tenantId,
            'tenant_code'  => (string) ($tenant['tenant_code'] ?? ''),
            'company_name' => (string) ($tenant['company_name'] ?? ''),
            'host'         => (string) ($tenant['host'] ?? $this->currentHost()),
            'is_tenant'    => true,
            'base_url'     => $tenantBaseUrl,
            'company_logo' => $tenantLogo,
            'company_logo_dark' => $tenantLogoDark !== '' ? $tenantLogoDark : $tenantLogo,
            'company_logo_url' => $tenantLogoUrl,
            'company_logo_dark_url' => $tenantLogoDarkUrl !== '' ? $tenantLogoDarkUrl : $tenantLogoUrl,
            'favicon_url' => $tenantFaviconUrl,
            'branding'     => $branding,
            'localization' => $localization,
        ];

        $CI->config->set_item('base_url', rtrim($tenantBaseUrl, '/') . '/');
        $CI->config->set_item('kt_saas_current_tenant', $tenant);
        $CI->config->set_item('kt_saas_is_tenant_request', true);
        $CI->config->set_item('kt_saas_current_profile', $profile);
        $CI->config->set_item('kt_saas_auth_context', $authContext);
        $CI->config->set_item('kt_saas_runtime_branding', $branding);
        $CI->config->set_item('kt_saas_runtime_localization', $localization);
        $CI->config->set_item('kt_saas_cache_namespace', $cacheNamespace);

        $GLOBALS['kt_saas_current_tenant'] = $tenant;
        $GLOBALS['kt_saas_current_profile'] = $profile;
        $GLOBALS['kt_saas_auth_context'] = $authContext;
        $GLOBALS['kt_saas_runtime_branding'] = $branding;
        $GLOBALS['kt_saas_runtime_localization'] = $localization;
        $GLOBALS['kt_saas_cache_namespace'] = $cacheNamespace;
    }

    protected function tenantOption($CI, $name, $default = '')
    {
        if (!isset($CI->db) || !$CI->db->table_exists(db_prefix() . 'options')) {
            return (string) $default;
        }

        $row = $CI->db
            ->select('value')
            ->from(db_prefix() . 'options')
            ->where('name', (string) $name)
            ->get()
            ->row_array();

        if (!is_array($row) || !array_key_exists('value', $row)) {
            return (string) $default;
        }

        return (string) $row['value'];
    }

    protected function tenantBaseUrl(array $tenant)
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : (parse_url(APP_BASE_URL, PHP_URL_SCHEME) ?: 'https');
        $host = trim((string) ($tenant['host'] ?? ''));

        if ($host === '') {
            $host = trim((string) ($tenant['custom_domain'] ?? ''));
        }

        if ($host === '') {
            $subdomain = trim((string) ($tenant['subdomain'] ?? ''));
            $baseDomain = trim((string) kt_saas_get_option('kt_saas_base_domain', 'crm.local'));
            if ($subdomain !== '' && strpos($subdomain, '.') === false && $baseDomain !== '') {
                $host = $subdomain . '.' . $baseDomain;
            } else {
                $host = $subdomain;
            }
        }

        return $scheme . '://' . $host;
    }

    protected function landlordLogoUrl()
    {
        try {
            $CI = &get_instance();

            foreach (['company_logo_dark', 'company_logo'] as $optionName) {
                $filename = '';
                if (isset($CI->db)) {
                    try {
                        $row = $CI->db
                            ->select('value')
                            ->from(db_prefix() . 'options')
                            ->where('name', $optionName)
                            ->get()
                            ->row_array();

                        if (is_array($row)) {
                            $filename = trim((string) ($row['value'] ?? ''));
                        }
                    } catch (Throwable $e) {
                        log_message('error', 'KT SaaS landlord logo option lookup failed: ' . $e->getMessage());
                    }
                }

                if ($filename === '' && function_exists('get_option')) {
                    $filename = trim((string) get_option($optionName));
                }

                if ($filename === '') {
                    continue;
                }

                $filename = basename($filename);
                $path = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'company' . DIRECTORY_SEPARATOR . $filename;
                if (!is_file($path)) {
                    continue;
                }

                return rtrim(defined('APP_BASE_URL') ? APP_BASE_URL : site_url(), '/') . '/uploads/company/' . rawurlencode($filename);
            }
        } catch (Throwable $e) {
            log_message('error', 'KT SaaS landlord logo lookup failed: ' . $e->getMessage());
        }

        return '';
    }

    protected function isLandlordOnlyCoreRoute($uri)
    {
        foreach (function_exists('kt_saas_landlord_only_admin_route_patterns') ? kt_saas_landlord_only_admin_route_patterns() : [] as $pattern) {
            if (preg_match($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }

    protected function renderLandlordOnlyCoreRouteForbidden($CI, $uri)
    {
        $message = 'This system area is available only in landlord context.';

        set_status_header(403);
        if ($CI->input->is_ajax_request() || strpos($uri, 'api/') === 0) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'message' => $message,
            ]);
        } else {
            header('Content-Type: text/html; charset=UTF-8');
            show_error($message, 403, 'Forbidden');
        }
        exit;
    }
}
