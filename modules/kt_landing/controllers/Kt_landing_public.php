<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!defined('KT_LANDING_MODULE')) {
    define('KT_LANDING_MODULE', 'kt_landing');
}

class Kt_landing_public extends App_Controller
{
    protected $allowedTemplates = ['fastwork_inspired', 'corporate_saas', 'modern_growth', 'minimal_enterprise'];

    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $this->home();
    }

    public function home()
    {
        try {
            $this->bootPublicLanding();

            if ($this->isTenantRuntime()) {
                $this->renderTenantWorkspaceHome();
                return;
            }

            $data = $this->buildLandingData('home');
            $this->trackPageView('home');
            $this->renderLandingTemplate($data);
        } catch (Throwable $e) {
            log_message('error', 'KT Landing public home failed: ' . $e->getMessage());
            error_log('KT_LANDING_PUBLIC_HOME: ' . $e->getMessage());
            $this->renderPublicFallback('home');
        }
    }

    public function pricing()
    {
        try {
            $this->bootPublicLanding();

            $data = $this->buildLandingData('pricing');
            if (!$this->isTenantRuntime()) {
                $this->trackPageView('pricing');
            }
            $this->renderLandingTemplate($data);
        } catch (Throwable $e) {
            log_message('error', 'KT Landing public pricing failed: ' . $e->getMessage());
            error_log('KT_LANDING_PUBLIC_PRICING: ' . $e->getMessage());
            $this->renderPublicFallback('pricing');
        }
    }

    public function signup()
    {
        try {
            $this->bootPublicLanding();

            if ($this->isTenantRuntime()) {
                redirect(site_url('clients'));
                return;
            }

            if (strtolower((string) $this->input->method()) === 'post') {
                $result = $this->handleSignupSubmit($this->input->post());
                if (!empty($result['success'])) {
                    $this->Kt_landing_model->track_event('signup_submit', [
                        'page_slug' => 'signup',
                        'plan_id' => (int) $this->input->post('plan_id'),
                        'source' => 'landing_signup',
                        'utm_source' => (string) $this->input->post('utm_source', false),
                        'utm_medium' => (string) $this->input->post('utm_medium', false),
                        'utm_campaign' => (string) $this->input->post('utm_campaign', false),
                        'ip_address' => (string) $this->input->ip_address(),
                    ]);
                }
                $this->session->set_flashdata('kt_landing_signup_result', [
                    'ok' => !empty($result['success']) ? '1' : '0',
                    'msg' => (string) ($result['message'] ?? ''),
                    'tenant_code' => (string) ($result['tenant_code'] ?? ''),
                    'invoice_number' => (string) ($result['invoice_number'] ?? ''),
                    'checkout_url' => (string) ($result['checkout_url'] ?? ''),
                    'plan_name' => (string) ($result['plan_name'] ?? ''),
                    'desired_subdomain' => (string) ($result['desired_subdomain'] ?? ''),
                    'subscription_price' => (float) ($result['subscription_price'] ?? 0),
                    'setup_fee' => (float) ($result['setup_fee'] ?? 0),
                    'invoice_total' => (float) ($result['invoice_total'] ?? 0),
                    'line_items' => (array) ($result['line_items'] ?? []),
                    'addons' => (array) ($result['addons'] ?? []),
                ]);
                redirect(site_url('signup/status'));
                return;
            }

            $data['title'] = 'Đăng ký CRM Khách Tốt';
            $data['meta_title'] = 'Đăng ký CRM Khách Tốt';
            $data['meta_description'] = 'Đăng ký CRM cho doanh nghiệp và bắt đầu quy trình triển khai chính thức.';
            $data['brand_name'] = $this->resolveBrandingContext()['company_name'] ?: 'CRM Khách Tốt';
            $data['logo'] = $this->resolveBrandingContext()['logo'] ?? '';
            $data['favicon'] = $this->resolveBrandingContext()['favicon'] ?? '';
            $data['public_plans'] = $this->Kt_saas_model->get_public_plans();
            $data['public_addons'] = $this->buildSignupAddonCatalog();
            $data['preferred_plan_id'] = (int) $this->input->get('plan_id');
            $this->trackPageView('signup');
            $html = $this->load->view(KT_LANDING_MODULE . '/public/signup', $data, true);
            $this->output->set_output($this->normalizePublicOutputText($html));
        } catch (Throwable $e) {
            log_message('error', 'KT Landing public signup failed: ' . $e->getMessage());
            error_log('KT_LANDING_PUBLIC_SIGNUP: ' . $e->getMessage());
            $this->renderSignupFallback();
        }
    }

    public function signup_subdomain_check()
    {
        try {
            $this->bootPublicLanding();
            $value = (string) $this->input->get('value', true);
            $result = $this->Kt_saas_model->checkSubdomainAvailability($value, null);
            $this->output
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode([
                    'success' => true,
                    'data' => $result,
                ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        } catch (Throwable $e) {
            log_message('error', 'KT Landing public signup subdomain check failed: ' . $e->getMessage());
            $this->output
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'Không thể kiểm tra subdomain lúc này.',
                ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        }
    }

    public function signup_status()
    {
        try {
            $this->bootPublicLanding();

            if ($this->isTenantRuntime()) {
                redirect(site_url('clients'));
                return;
            }

            $result = $this->session->flashdata('kt_landing_signup_result');
            if (!is_array($result) || empty($result)) {
                redirect(site_url('signup'));
                return;
            }

            $data['title'] = 'Trạng thái đăng ký';
            $data['signup_result'] = $result;
            $this->load->view(KT_LANDING_MODULE . '/public/signup_status', $data);
        } catch (Throwable $e) {
            log_message('error', 'KT Landing public signup status failed: ' . $e->getMessage());
            redirect(site_url('signup'));
        }
    }

    public function signup_progress($tenantCode = '')
    {
        try {
            $this->bootPublicLanding();

            if ($this->isTenantRuntime()) {
                $this->output->set_status_header(404)->set_content_type('application/json')->set_output(json_encode(['success' => false, 'message' => 'Not found']));
                return;
            }

            $tenantCode = trim((string) $tenantCode);
            if ($tenantCode === '') {
                $this->output->set_content_type('application/json')->set_output(json_encode(['success' => false, 'message' => 'Tenant code is required']));
                return;
            }

            $tenant = $this->db
                ->where('tenant_code', $tenantCode)
                ->where('deleted_at IS NULL', null, false)
                ->get(db_prefix() . 'kt_saas_tenants')
                ->row_array();
            if (!$tenant) {
                $this->output->set_content_type('application/json')->set_output(json_encode(['success' => false, 'message' => 'Tenant not found']));
                return;
            }

            $tenantId = (int) $tenant['id'];
            $subscription = $this->Kt_saas_model->get_current_subscription($tenantId);
            $invoiceNumber = trim((string) $this->input->get('invoice', true));
            if ($invoiceNumber !== '') {
                $invoice = $this->db
                    ->where('tenant_id', $tenantId)
                    ->where('invoice_number', $invoiceNumber)
                    ->get(db_prefix() . 'kt_saas_invoices')
                    ->row_array();
            } else {
                $invoice = $this->db
                    ->where('tenant_id', $tenantId)
                    ->order_by('id', 'desc')
                    ->get(db_prefix() . 'kt_saas_invoices')
                    ->row_array();
            }

            $latestJob = $this->db
                ->where('tenant_id', $tenantId)
                ->where('job_type', 'provision_tenant')
                ->order_by('id', 'desc')
                ->get(db_prefix() . 'kt_saas_provision_jobs')
                ->row_array();

            $this->output->set_content_type('application/json')->set_output(json_encode([
                'success' => true,
                'tenant' => [
                    'id' => $tenantId,
                    'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
                    'status' => (string) ($tenant['status'] ?? ''),
                    'provisioning_status' => (string) ($tenant['provisioning_status'] ?? ''),
                    'subdomain' => (string) ($tenant['subdomain'] ?? ''),
                ],
                'subscription' => [
                    'status' => (string) ($subscription['status'] ?? ''),
                    'plan_id' => (int) ($subscription['plan_id'] ?? 0),
                ],
                'invoice' => [
                    'id' => (int) ($invoice['id'] ?? 0),
                    'invoice_number' => (string) ($invoice['invoice_number'] ?? ''),
                    'status' => (string) ($invoice['status'] ?? ''),
                ],
                'provision_job' => [
                    'id' => (int) ($latestJob['id'] ?? 0),
                    'status' => (string) ($latestJob['status'] ?? ''),
                    'attempts' => (int) ($latestJob['attempts'] ?? 0),
                    'error_message' => (string) ($latestJob['error_message'] ?? ''),
                    'updated_at' => (string) ($latestJob['updated_at'] ?? ''),
                ],
            ]));
        } catch (Throwable $e) {
            log_message('error', 'KT Landing public signup progress failed: ' . $e->getMessage());
            $this->output->set_content_type('application/json')->set_output(json_encode(['success' => false, 'message' => 'Unable to load progress']));
        }
    }

    public function blog()
    {
        $this->bootPublicLanding();
        $data = $this->buildLandingData('blog');
        if (!$this->isTenantRuntime()) {
            $this->trackPageView('blog');
        }
        $this->renderLandingTemplate($data);
    }

    public function contact_submit()
    {
        try {
            $this->bootPublicLanding();

            if (strtolower((string) $this->input->method()) !== 'post') {
                show_404();
                return;
            }

            $payload = [
                'name' => (string) $this->input->post('name', false),
                'company' => (string) $this->input->post('company', false),
                'phone' => (string) $this->input->post('phone', false),
                'email' => (string) $this->input->post('email', false),
                'message' => (string) $this->input->post('message', false),
                'desired_plan_id' => (int) $this->input->post('desired_plan_id'),
                'source' => (string) $this->input->post('source', false) ?: 'contact',
                'utm_source' => (string) $this->input->post('utm_source', false),
                'utm_medium' => (string) $this->input->post('utm_medium', false),
                'utm_campaign' => (string) $this->input->post('utm_campaign', false),
                'status' => 'new',
            ];
            $ok = $this->Kt_landing_model->save_lead($payload);
            if ($ok) {
                $this->Kt_landing_model->track_event('lead_submit', [
                    'page_slug' => 'contact',
                    'plan_id' => (int) $payload['desired_plan_id'],
                    'source' => (string) $payload['source'],
                    'utm_source' => (string) $payload['utm_source'],
                    'utm_medium' => (string) $payload['utm_medium'],
                    'utm_campaign' => (string) $payload['utm_campaign'],
                    'ip_address' => (string) $this->input->ip_address(),
                ]);
            }
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['success' => $ok ? true : false]));
        } catch (Throwable $e) {
            log_message('error', 'KT Landing public contact submit failed: ' . $e->getMessage());
            $this->output->set_content_type('application/json')->set_output(json_encode(['success' => false]));
        }
    }

    private function bootPublicLanding()
    {
        $this->load->helper(['url']);
        $this->load->helper('kt_saas/kt_saas');
        $this->load->helper(KT_LANDING_MODULE . '/kt_landing');
        $this->load->model('kt_saas/Kt_saas_model');
        $this->load->model(KT_LANDING_MODULE . '/Kt_landing_model');
        $this->ensureRuntimeContext();
    }

    private function ensureRuntimeContext()
    {
        if (function_exists('kt_saas_is_tenant_runtime') && kt_saas_is_tenant_runtime()) {
            return;
        }

        $bootstrapPath = APPPATH . 'hooks' . DIRECTORY_SEPARATOR . 'KtSaasTenantBootstrap.php';
        if (is_file($bootstrapPath)) {
            require_once $bootstrapPath;
            if (class_exists('KtSaasTenantBootstrap', false)) {
                try {
                    $bootstrap = new KtSaasTenantBootstrap();
                    $bootstrap->handle();
                } catch (Throwable $e) {
                    log_message('error', 'KT Landing public bootstrap retry failed: ' . $e->getMessage());
                }
            }
        }
    }

    private function isTenantRuntime()
    {
        return function_exists('kt_saas_is_tenant_runtime') && kt_saas_is_tenant_runtime();
    }

    private function resolveBrandingContext()
    {
        if (function_exists('kt_saas_resolve_branding_context')) {
            $context = kt_saas_resolve_branding_context(['scope' => 'landing', 'log_fallback' => true]);
            if (is_array($context)) {
                return $context;
            }
        }

        if (function_exists('kt_saas_resolve_tenant_branding_context') && $this->isTenantRuntime()) {
            $context = kt_saas_resolve_tenant_branding_context(null, ['scope' => 'landing', 'log_fallback' => true]);
            if (is_array($context)) {
                return $context;
            }
        }

        return [
            'company_name' => 'CRM Khách Tốt',
            'logo' => '',
            'dark_logo' => '',
            'favicon' => '',
        ];
    }

    private function resolveLocalizationContext()
    {
        if (function_exists('kt_saas_resolve_localization_context')) {
            $context = kt_saas_resolve_localization_context(['scope' => 'landing']);
            if (is_array($context)) {
                return $context;
            }
        }

        if (function_exists('kt_saas_resolve_tenant_localization_context') && $this->isTenantRuntime()) {
            $context = kt_saas_resolve_tenant_localization_context(null, ['scope' => 'landing']);
            if (is_array($context)) {
                return $context;
            }
        }

        return [
            'language' => trim((string) get_option('active_language')) ?: 'vi',
            'timezone' => trim((string) get_option('default_timezone')) ?: 'UTC',
            'currency' => strtoupper(trim((string) get_option('default_currency'))) ?: 'VND',
        ];
    }

    private function resolveTemplateCode()
    {
        $preview = trim((string) $this->input->get('tpl', true));
        if ($this->isRegisteredTemplateCode($preview)) {
            return $preview;
        }

        $saved = trim((string) $this->Kt_landing_model->get_setting('default_template', ''));
        if ($saved === '') {
            $saved = trim((string) get_option('kt_landing_template'));
        }
        if ($this->isRegisteredTemplateCode($saved)) {
            return $saved;
        }

        return 'fastwork_inspired';
    }

    private function resolveTemplateViewCode($template)
    {
        $template = trim((string) $template);
        if ($template === '') {
            return 'fastwork_inspired';
        }

        $variant = $this->Kt_landing_model->get_template_clone_variant($template);
        if (is_array($variant) && !empty($variant['base_template_code'])) {
            $baseTemplate = trim((string) $variant['base_template_code']);
            if ($baseTemplate !== '') {
                return $baseTemplate;
            }
        }

        return $template;
    }

    private function isRegisteredTemplateCode($template)
    {
        $template = trim((string) $template);
        if ($template === '') {
            return false;
        }

        if (in_array($template, $this->allowedTemplates, true)) {
            return true;
        }

        return in_array($template, $this->Kt_landing_model->get_registered_template_codes(), true);
    }

    private function getTemplateSetting($templateCode, $key, $default = '')
    {
        $templateCode = trim((string) $templateCode);
        if ($templateCode !== '') {
            $variant = $this->Kt_landing_model->get_template_clone_variant($templateCode);
            if (is_array($variant)) {
                $settings = (array) ($variant['settings'] ?? []);
                if (array_key_exists($key, $settings)) {
                    return $settings[$key];
                }
            }
        }

        return $this->Kt_landing_model->get_setting($key, $default);
    }

    private function buildLandingData($page)
    {
        $template = $this->resolveTemplateCode();
        $brandingContext = $this->resolveBrandingContext();
        $localizationContext = $this->resolveLocalizationContext();
        $publicPlans = $this->applyPlanOverrides(
            $this->Kt_saas_model->get_public_plans(),
            $this->indexPlanOverrides($this->Kt_landing_model->get_plan_overrides()),
            $this->indexPlanOverrides($this->Kt_landing_model->get_template_clone_pricing_overrides($template))
        );

        $brandName = trim((string) $this->getTemplateSetting($template, 'brand_name', (string) ($brandingContext['company_name'] ?? '')));
        if ($brandName === '') {
            $brandName = 'CRM Khách Tốt';
        }

        $metaTitle = trim((string) $this->getTemplateSetting($template, 'default_meta_title', ''));
        if ($metaTitle === '') {
            $metaTitle = $brandName . ' - CRM';
        }
        $metaDescription = trim((string) $this->getTemplateSetting($template, 'default_meta_description', ''));
        if ($metaDescription === '') {
            $metaDescription = 'Giải pháp CRM và vận hành doanh nghiệp cho đội ngũ vừa và nhỏ.';
        }

        $headerCta = $this->sanitizeMarketingText($this->getTemplateSetting($template, 'kt_landing_header_cta_text', ''));
        if ($headerCta === '') {
            $headerCta = 'Đăng ký';
        }
        $heroTitle = $this->sanitizeMarketingText($this->getTemplateSetting($template, 'kt_landing_hero_title', ''));
        if ($heroTitle === '') {
            $heroTitle = 'Nền tảng CRM và vận hành doanh nghiệp cho SME';
        }
        $heroSubtitle = $this->sanitizeMarketingText($this->getTemplateSetting($template, 'kt_landing_hero_subtitle', ''));
        if ($heroSubtitle === '') {
            $heroSubtitle = 'Chuẩn hóa bán hàng, dịch vụ, tài chính và cộng tác trên một hệ thống thống nhất.';
        }
        $heroImage = trim((string) $this->getTemplateSetting($template, 'kt_landing_hero_image', ''));
        $primaryColor = trim((string) $this->getTemplateSetting($template, 'primary_color', ''));
        if ($primaryColor === '') {
            $primaryColor = '#1f3a5f';
        }
        $secondaryColor = trim((string) $this->getTemplateSetting($template, 'secondary_color', ''));
        if ($secondaryColor === '') {
            $secondaryColor = '#4b5563';
        }
        $ctaColor = trim((string) $this->getTemplateSetting($template, 'accent_color', ''));
        if ($ctaColor === '') {
            $ctaColor = '#2563eb';
        }

        return [
            'title' => $metaTitle,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'canonical_url' => current_url(),
            'og_image' => $heroImage !== '' ? $heroImage : '',
            'brand_name' => $brandName,
            'logo' => (string) ($brandingContext['logo'] ?? ''),
            'dark_logo' => (string) ($brandingContext['dark_logo'] ?? ''),
            'favicon' => (string) ($brandingContext['favicon'] ?? ''),
            'branding_context' => $brandingContext,
            'localization_context' => $localizationContext,
            'header_cta_text' => $headerCta,
            'hero_title' => $heroTitle,
            'hero_subtitle' => $heroSubtitle,
            'hero_image' => $heroImage,
            'primary_color' => $primaryColor,
            'secondary_color' => $secondaryColor,
            'cta_color' => $ctaColor,
            'features' => $this->buildFeatureItems($template),
            'faqs' => $this->buildFaqItems($template),
            'testimonials' => $this->buildTestimonialItems($template),
            'product_marketing' => $this->buildProductMarketingData($template),
            'landing_content' => $this->buildLandingContentFromCms($template),
            'public_plans' => $publicPlans,
            'footer_text' => trim((string) $this->getTemplateSetting($template, 'kt_landing_footer_text', '')) ?: ('© ' . date('Y') . ' ' . $brandName),
            'page' => $page,
            'sections' => $this->Kt_landing_model->get_sections_by_page_key($template),
            'menus' => $this->Kt_landing_model->get_menus_for_template($template),
            'blog_posts' => [],
            'custom_css' => (string) $this->getTemplateSetting($template, 'custom_css', ''),
            'custom_js' => (string) $this->getTemplateSetting($template, 'custom_js', ''),
            'template_variant' => $this->Kt_landing_model->get_template_clone_variant($template),
        ];
    }

    private function renderTenantWorkspaceHome()
    {
        $tenant = function_exists('kt_saas_current_tenant') ? kt_saas_current_tenant() : [];
        $brandingContext = $this->resolveBrandingContext();
        $companyName = trim((string) ($brandingContext['company_name'] ?? ''));
        if ($companyName === '') {
            $companyName = trim((string) ($tenant['company_name'] ?? ''));
        }
        if ($companyName === '') {
            $companyName = 'Không gian CRM';
        }

        $data = [
            'title' => 'Chào mừng đến với ' . $companyName,
            'company_name' => $companyName,
            'tenant' => is_array($tenant) ? $tenant : [],
            'branding_context' => is_array($brandingContext) ? $brandingContext : [],
            'company_email' => trim((string) get_option('company_email')),
            'company_phone' => trim((string) get_option('companyphonenumber')),
            'company_address' => trim((string) get_option('companyaddress')),
            'crm_login_url' => site_url('admin'),
            'customer_login_url' => site_url('clients'),
        ];

        $html = $this->load->view('kt_saas/tenant/workspace_home', $data, true);
        $this->output->set_content_type('text/html', 'UTF-8');
        $this->output->set_output($html);
    }

    private function sanitizeMarketingText($value)
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }
        $normalized = strtolower($text);
        $markers = [
            'editor note',
            'admin description',
            'cms hint',
            'helper text',
            'placeholder',
            'internal documentation',
            'internal note',
            'admin only',
        ];
        foreach ($markers as $marker) {
            if (strpos($normalized, $marker) !== false) {
                return '';
            }
        }
        return $text;
    }

    private function sanitizeLandingSection(array $section)
    {
        return [
            'title' => $this->sanitizeMarketingText($section['title'] ?? ''),
            'subtitle' => $this->sanitizeMarketingText($section['subtitle'] ?? ''),
            'content' => $this->sanitizeMarketingText($section['content'] ?? ''),
            'image' => trim((string) ($section['image'] ?? '')),
            'icon' => trim((string) ($section['icon'] ?? '')),
            'button_text' => $this->sanitizeMarketingText($section['button_text'] ?? ''),
            'button_url' => trim((string) ($section['button_url'] ?? '')),
        ];
    }

    private function sanitizeLandingItem(array $item)
    {
        return [
            'title' => $this->sanitizeMarketingText($item['title'] ?? ''),
            'subtitle' => $this->sanitizeMarketingText($item['subtitle'] ?? ''),
            'content' => $this->sanitizeMarketingText($item['content'] ?? ''),
            'icon' => trim((string) ($item['icon'] ?? '')),
            'image' => trim((string) ($item['image'] ?? '')),
            'badge' => $this->sanitizeMarketingText($item['badge'] ?? ''),
            'button_text' => $this->sanitizeMarketingText($item['button_text'] ?? ''),
            'button_url' => trim((string) ($item['button_url'] ?? '')),
        ];
    }

    private function sanitizeMarketingList(array $rows, array $allowedKeys)
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $clean = [];
            foreach ($allowedKeys as $key) {
                $value = $row[$key] ?? '';
                if (in_array($key, ['image', 'icon', 'button_url', 'slug', 'status', 'step'], true)) {
                    $clean[$key] = trim((string) $value);
                } elseif ($key === 'bullets' && is_array($value)) {
                    $clean[$key] = array_values(array_filter(array_map([$this, 'sanitizeMarketingText'], $value), static function ($v) {
                        return $v !== '';
                    }));
                } else {
                    $clean[$key] = $this->sanitizeMarketingText($value);
                }
            }
            $out[] = $clean;
        }
        return $out;
    }

    private function buildLandingContentFromCms($templateCode = null)
    {
        $pageKey = trim((string) $templateCode);
        if ($pageKey === '') {
            $pageKey = 'home';
        }

        $getSection = function ($key) use ($pageKey) {
            $section = $this->Kt_landing_model->get_section_by_key($pageKey, $key) ?: $this->Kt_landing_model->get_section_by_key('home', $key) ?: [];
            return $this->sanitizeLandingSection($section);
        };
        $getItems = function ($sectionKey, $itemKey = null) use ($pageKey) {
            $section = $this->Kt_landing_model->get_section_by_key($pageKey, $sectionKey) ?: $this->Kt_landing_model->get_section_by_key('home', $sectionKey) ?: [];
            $sectionId = (int) ($section['id'] ?? 0);
            if ($sectionId <= 0) {
                return [];
            }
            $items = $this->Kt_landing_model->get_section_items($sectionId, $itemKey, true);
            return array_map([$this, 'sanitizeLandingItem'], $items);
        };

        return [
            'hero' => $getSection('hero'),
            'why_change' => $getSection('why_change'),
            'use_case' => $getSection('use_case_flow'),
            'final_cta' => $getSection('final_cta'),
            'trust_metrics' => $getItems('trust_bar', 'trust_metric'),
            'trust_badges' => $getItems('trust_bar', 'trust_badge'),
            'trust_logos' => $getItems('trust_bar', 'trust_logo'),
            'marketplace_cards' => $getItems('marketplace', 'marketplace_card'),
            'faq_items' => $getItems('faq', 'faq_item'),
            'journey_steps' => $getItems('customer_journey', 'journey_step'),
            'why_change_problems' => $getItems('why_change', 'problem'),
            'why_change_solutions' => $getItems('why_change', 'solution'),
            'case_studies' => $getItems('case_studies', 'case_study'),
        ];
    }
    private function buildProductMarketingData($templateCode = null)
    {
        $raw = $this->getTemplateSetting($templateCode, 'kt_landing_product_marketing_json', []);
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }
        if (!is_array($raw)) {
            $raw = [];
        }
        if (!empty($raw)) {
            return [
                'showcases' => $this->sanitizeMarketingList((array) ($raw['showcases'] ?? []), ['slug', 'title', 'headline', 'description', 'bullets']),
                'journey' => $this->sanitizeMarketingList((array) ($raw['journey'] ?? []), ['step', 'title', 'text', 'status']),
                'why_choose' => $this->sanitizeMarketingList((array) ($raw['why_choose'] ?? []), ['title', 'text']),
            ];
        }

        return [
            'showcases' => [
                ['slug' => 'crm', 'title' => 'CRM bán hàng', 'headline' => 'Quản lý toàn bộ vòng đời khách hàng trong một màn hình', 'description' => 'Đội sales theo dõi lead, cơ hội, báo giá và lịch sử chăm sóc theo pipeline trực quan.', 'bullets' => ['Pipeline theo trạng thái Lead → Won', 'Nhắc việc tự động theo SLA', 'Báo cáo hiệu suất theo nhân sự/nhóm']],
                ['slug' => 'inventory', 'title' => 'Quản lý kho', 'headline' => 'Kiểm soát tồn kho theo thời gian thực', 'description' => 'Quản lý nhập xuất tồn, cảnh báo mức tồn tối thiểu và liên kết trực tiếp đến hàng.', 'bullets' => ['Cảnh báo thiếu hàng theo ngưỡng', 'Theo dõi vòng quay tồn kho', 'Đồng bộ với bán hàng và hóa đơn']],
                ['slug' => 'invoice', 'title' => 'Hóa đơn điện tử', 'headline' => 'Phát hành hóa đơn và ký số ngay trong luồng công việc', 'description' => 'Từ báo giá, hợp đồng đến hóa đơn điện tử được xử lý liên tục.', 'bullets' => ['Tích hợp hóa đơn điện tử', 'Theo dõi trạng thái phát hành', 'Lưu trữ chứng từ tập trung']],
                ['slug' => 'sepay', 'title' => 'Thanh toán & Đối soát', 'headline' => 'Đối soát thanh toán tự động, giảm lệch công nợ', 'description' => 'Thanh toán & đối soát đồng bộ giao dịch để đối soát nhanh với hóa đơn.', 'bullets' => ['Đồng bộ giao dịch tự động', 'Đối chiếu thanh toán theo hóa đơn', 'Báo cáo dòng tiền chính xác hơn']],
            ],
            'journey' => [
                ['step' => '01', 'title' => 'Thu hút lead', 'text' => 'Lead vào từ website/campaign và đẩy về CRM.', 'status' => 'Active'],
                ['step' => '02', 'title' => 'Chốt báo giá', 'text' => 'Đội sales quản lý cơ hội, gửi báo giá và theo dõi phản hồi.', 'status' => 'Active'],
                ['step' => '03', 'title' => 'Ký và phát hành', 'text' => 'Hợp đồng/hóa đơn đi cùng quy trình ký số tập trung.', 'status' => 'Active'],
                ['step' => '04', 'title' => 'Thu tiền và chăm sóc', 'text' => 'Thanh toán được đối soát tự động, dữ liệu quay lại CRM để upsell.', 'status' => 'Active'],
            ],
            'why_choose' => [
                ['title' => 'Một nền tảng xuyên suốt', 'text' => 'CRM, kho, hóa đơn, thanh toán chạy trên cùng dữ liệu nghiệp vụ.'],
                ['title' => 'Triển khai nhanh cho SME', 'text' => 'Bắt đầu từ trial, bật thêm ứng dụng và add-on khi mở rộng.'],
                ['title' => 'Tối ưu vận hành dài hạn', 'text' => 'Giảm hệ thống rời rạc, tăng khả năng đo lường và ra quyết định.'],
            ],
        ];
    }
    private function buildFeatureItems($templateCode = null)
    {
        $json = trim((string) $this->getTemplateSetting($templateCode, 'kt_landing_features_json', ''));
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded) && !empty($decoded)) {
                return $this->sanitizeMarketingList($decoded, ['title', 'description']);
            }
        }
        return [
            ['title' => 'CRM bán hàng', 'description' => 'Quản lý lead, cơ hội và pipeline theo quy trình rõ ràng.'],
            ['title' => 'Vận hành dự án', 'description' => 'Theo dõi tiến độ, phân công công việc và chi phí theo thời gian thực.'],
            ['title' => 'Tài chính hợp nhất', 'description' => 'Hóa đơn, thanh toán và đối soát đồng bộ trên một nền tảng.'],
            ['title' => 'Mở rộng theo nhu cầu', 'description' => 'Bật/tắt ứng dụng theo gói và nhu cầu vận hành thực tế.'],
        ];
    }
    private function buildFaqItems($templateCode = null)
    {
        $json = trim((string) $this->getTemplateSetting($templateCode, 'kt_landing_faq_json', ''));
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded) && !empty($decoded)) {
                return $this->sanitizeMarketingList($decoded, ['q', 'a']);
            }
        }
        return [
            ['q' => 'Mất bao lâu để triển khai?', 'a' => 'Thường 1-3 ngày cho cấu hình nền tảng và nhập dữ liệu ban đầu.'],
            ['q' => 'Có thể nâng cấp gói sau này không?', 'a' => 'Có. Hệ thống hỗ trợ thay đổi gói và giữ toàn bộ dữ liệu hiện tại.'],
            ['q' => 'Có hỗ trợ tích hợp thanh toán không?', 'a' => 'Có. Nền tảng hỗ trợ tích hợp SePay và các workflow hóa đơn liên quan.'],
        ];
    }
    private function buildTestimonialItems($templateCode = null)
    {
        $json = trim((string) $this->getTemplateSetting($templateCode, 'kt_landing_testimonials_json', ''));
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded) && !empty($decoded)) {
                return $this->sanitizeMarketingList($decoded, ['name', 'company', 'quote']);
            }
        }
        return [
            ['name' => 'Giám đốc vận hành', 'company' => 'Doanh nghiệp thương mại', 'quote' => 'Chúng tôi chuẩn hóa quy trình bán hàng và báo cáo vận hành nhanh hơn đáng kể.'],
            ['name' => 'Trưởng phòng kinh doanh', 'company' => 'Công ty dịch vụ', 'quote' => 'Pipeline rõ ràng, đội ngũ phối hợp tốt hơn và giảm sai lệch dữ liệu.'],
        ];
    }
    private function renderLandingTemplate(array $data)
    {
        $template = $this->resolveTemplateCode();
        $data['template_code'] = $template;
        $viewCode = $this->resolveTemplateViewCode($template);
        $viewPath = KT_LANDING_MODULE . '/public/templates/' . $viewCode . '/index';
        try {
            $html = $this->load->view($viewPath, $data, true);
            $this->output->set_output($this->normalizePublicOutputText($html));
        } catch (Throwable $e) {
            error_log('KT_LANDING_PUBLIC_RENDER: ' . get_class($e) . ': ' . $e->getMessage() . ' | view=' . $viewPath . ' | template=' . $template);
            throw $e;
        }
    }
    private function normalizePublicOutputText($html)
    {
        $html = (string) $html;
        if ($html === '') {
            return $html;
        }

        $replacements = [
            'CRM cho doanh nghiệp' => 'CRM cho doanh nghiệp',
            'D?ng th? mi?n ph?' => 'D?ng th? mi?n ph?',
            '??t l?ch demo' => 'Đặt lịch demo',
            'Kh?ng c?n c?i ??t' => 'Không cần cài đặt',
            'K?ch ho?t trong v?i ph?t' => 'Kích hoạt trong vài phút',
            'H? tr? tri?n khai' => 'Hỗ trợ triển khai',
            'M? r?ng b?ng Marketplace' => 'Mở rộng bằng Marketplace',
            'Quản lý doanh nghiệp, sao lưu, email và nhật ký vận hành từ một điểm điều khiển.' => 'Quản lý doanh nghiệp, sao lưu, email và nhật ký vận hành từ một điểm điều khiển.',
            'B? sung h?a ??n ?i?n t?, HSM, thanh to?n, website v? h? t?ng theo nhu c?u t?ng giai ?o?n.' => 'Bổ sung hóa đơn điện tử, HSM, thanh toán, website và hạ tầng theo nhu cầu từng giai đoạn.',
            'Vận hành doanh nghiệp' => 'Vận hành doanh nghiệp',
            'Bảng điều khiển doanh nghiệp' => 'Bảng điều khiển doanh nghiệp',
            'Email & ??ng b?' => 'Email & đồng bộ',
            '4,8 t?' => '4,8 t?',
            '+18,4% so v?i th?ng tr??c' => '+18,4% so với tháng trước',
            'Mới: 18 ? VIP: 42 ? Ch?m s?c: 71' => 'Mới: 18 · VIP: 42 · Chăm sóc: 71',
            'Đã thu: 436 · Chờ thu: 72 · Quá hạn: 9' => 'Đã thu: 436 · Chờ thu: 72 · Quá hạn: 9',
            '??n h?ng m?i t? ABC' => 'Đơn hàng mới từ ABC',
            'Thanh toán ?? ??ng b?' => 'Thanh toán đã đồng bộ',
            'Khách tiềm năng m?i t? landing' => 'Khách tiềm năng mới từ landing',
            'Doanh nghiệp ?ang v?n h?nh' => 'Doanh nghiệp đang vận hành',
            'Doanh nghiệp t?ch ri?ng' => 'Doanh nghiệp tách riêng',
            'V? sao ch?n CRM Kh?ch T?t' => 'Vì sao chọn CRM Khách Tốt',
            'CRM cho doanh nghiệp c?n qu?n l? b?n h?ng, kho, h?a ??n v? thanh to?n tr?n m?t h? th?ng th?ng nh?t.' => 'CRM cho doanh nghiệp cần quản lý bán hàng, kho, hóa đơn và thanh toán trên một hệ thống thống nhất.',
            'Gi?m th?t tho?t doanh thu v? c?ng n? b?ng m?t lu?ng d? li?u xuy?n su?t t? b?n h?ng ??n ph?t h?nh h?a ??n.' => 'Giảm thất thoát doanh thu và công nợ bằng một luồng dữ liệu xuyên suốt từ bán hàng đến phát hành hóa đơn.',
            'Kh?ng c?n l?m vi?c tr?n nhi?u h? th?ng t?ch r?i.' => 'Không còn làm việc trên nhiều hệ thống tách rời.',
            'Dữ liệu tách riêng theo doanh nghi?p' => 'Dữ liệu tách riêng theo doanh nghiệp',
            'Vận hành nhiều doanh nghiệp nhưng vẫn giữ thương hiệu, dữ liệu và quyền truy cập tách riêng.' => 'Vận hành nhiều doanh nghiệp nhưng vẫn giữ thương hiệu, dữ liệu và quyền truy cập tách riêng.',
            'D? m? r?ng kh?ch h?ng m? kh?ng l?m r?i m? h?nh v?n h?nh.' => 'Dễ mở rộng khách hàng mà không làm rối mô hình vận hành.',
            'Lu?ng thanh to?n g?n tr?c ti?p v?i h?a ??n ?? ??i so?t nhanh v? r? tr?ng th?i.' => 'Luồng thanh toán gắn trực tiếp với hóa đơn để đối soát nhanh và rõ trạng thái.',
            'Gi?m sai l?ch thanh to?n v? t?ng t?c ghi nh?n doanh thu.' => 'Giảm sai lệch thanh toán và tăng tốc ghi nhận doanh thu.',
            'Hóa đơn ?i?n t?' => 'Hóa đơn điện tử',
            'Gi?m thao t?c tay v? r?i ro sai s?t ph?p l?.' => 'Giảm thao tác tay và rủi ro sai sót pháp lý.',
            '?t c?ng vi?c th? c?ng h?n, v?n h?nh ?n ??nh h?n.' => 'Ít công việc thủ công hơn, vận hành ổn định hơn.',
            'Hóa đơn ?i?n t?, HSM, website, t?n mi?n v? hosting c? th? m? r?ng theo t?ng giai ?o?n t?ng tr??ng.' => 'Hóa đơn điện tử, HSM, website, tên miền và hosting có thể mở rộng theo từng giai đoạn tăng trưởng.',
            'N?ng c?p n?ng l?c h? th?ng m? kh?ng ph?i ??i n?n t?ng.' => 'Nâng cấp năng lực hệ thống mà không phải đổi nền tảng.',
            'T? ??ng h?a' => 'Tự động hóa',
            'Khách tiềm năng moi' => 'Khách tiềm năng mới',
            'Ghi ch?' => 'Ghi chú',
            'Cu?c g?i' => 'Cuộc gọi',
            'L?ch h?n' => 'Lịch hẹn',
            'T?n' => 'Tồn',
            'Nh?p' => 'Nhập',
            'Xu?t' => 'Xuất',
            'PDF/XML sẵn sàng' => 'PDF/XML sẵn sàng',
            'Ph? bi?n' => 'Phổ biến',
            'Khám phá app' => 'Khám phá ứng dụng',
            'C?t l?i' => 'Cốt lõi',
            'Ph?n quy?n r? r?ng cho qu?n tr?, v?n h?nh, t?i ch?nh v? ng??i ph? tr?ch doanh nghi?p.' => 'Phân quyền rõ ràng cho quản trị, vận hành, tài chính và người phụ trách doanh nghiệp.',
            'Thanh toán, email, kh?i t?o h? th?ng v? webhook ??u c? nh?t k? ?? truy v?t.' => 'Thanh toán, email, khởi tạo hệ thống và webhook đều có nhật ký để truy vết.',
            'Nền tảng SaaS để chuẩn hóa bán hàng và vận hành doanh nghiệp.' => 'Nền tảng CRM để chuẩn hóa bán hàng và vận hành doanh nghiệp.',
            '<h5>Product</h5>' => '<h5>Sản phẩm</h5>',
            '<li>Inventory</li>' => '<li>Quản lý kho</li>',
            '<li>Hóa đơn</li>' => '<li>Hóa đơn</li>',
            'Ch? s? tin c?y' => 'Chỉ số tin cậy',
            'V? sao ch?n CRM Khách T?t' => 'Vì sao chọn CRM Khách Tốt',
            'T?i sao dùng m?t nền t?ng thay vì nhiều phần mềm rời r?c?' => 'Tại sao dùng một nền tảng thay vì nhiều phần mềm rời rạc?',
            'Quy tr?nh 6 b??c' => 'Quy trình 6 bước',
            'Chi ti?t h?nh tr?nh kh?ch h?ng' => 'Chi tiết hành trình khách hàng',
            'Khám ph? s?n ph?m' => 'Khám phá sản phẩm',
            'Kh?m ph? s?n ph?m' => 'Khám phá sản phẩm',
            '?ng d?ng m? r?ng' => 'Ứng dụng mở rộng',
            'B?o m?t v? ki?m so?t doanh nghi?p' => 'Bảo mật và kiểm soát doanh nghiệp',
            'Doanh nghi?p ?ang v?n h?nh' => 'Doanh nghiệp đang vận hành',
            'H?a ??n ?? x? l?' => 'Hóa đơn đã xử lý',
            'Giao d?ch' => 'Giao dịch',
            'CRM cho doanh nghi?p' => 'CRM cho doanh nghiệp',
            'CRM t?p trung' => 'CRM tập trung',
            'Ki?m so?t v?n h?nh' => 'Kiểm soát vận hành',
            'M? r?ng theo nhu c?u' => 'Mở rộng theo nhu cầu',
            'V?n h?nh doanh nghi?p' => 'Vận hành doanh nghiệp',
            'B?ng ?i?u khi?n doanh nghi?p' => 'Bảng điều khiển doanh nghiệp',
            'T?ng tr??ng' => 'Tăng trưởng',
            'Khách tiềm năng' => 'Khách tiềm năng',
            'Ti?m n?ng' => 'Tiềm năng',
            '?? t? v?n' => 'Đã tư vấn',
            '?? xu?t' => 'Đề xuất',
            '?? ch?t' => 'Đã chốt',
            'Kh?ch h?ng' => 'Khách hàng',
            'Ho?t ??ng g?n ??y' => 'Hoạt động gần đây',
            'C?ng vi?c' => 'Công việc',
            'Ki?m tra h?a ??n' => 'Kiểm tra hóa đơn',
            'Ch? duy?t' => 'Chờ duyệt',
            'Ho?n t?t' => 'Hoàn tất',
            'Qu?n l? kho' => 'Quản lý kho',
            'H?a ??n' => 'Hóa đơn',
            'Thanh to?n' => 'Thanh toán',
            'B?o c?o' => 'Báo cáo',
            'Tài liệu' => 'Tài liệu',
            'D? li?u t?ch ri?ng theo doanh nghi?p' => 'Dữ liệu tách riêng theo doanh nghiệp',
            'L?i ?ch kinh doanh' => 'Lợi ích kinh doanh',
            'H?a ??n ?i?n t?' => 'Hóa đơn điện tử',
            'T? ??ng h?a v?n h?nh' => 'Tự động hóa vận hành',
            'H? sinh th?i m? r?ng' => 'Hệ sinh thái mở rộng',
            'S?n s?ng' => 'Sẵn sàng',
            'T?ch ri?ng' => 'Tách riêng',
            '?? ki?m tra' => 'Đã kiểm tra',
            'Bao mat va kiem soat doanh nghiep' => 'Bảo mật và kiểm soát doanh nghiệp',
            'D? li?u t?ch ri?ng' => 'Dữ liệu tách riêng',
            'Ph?n quy?n truy c?p' => 'Phân quyền truy cập',
            'Nh?t k? v?n h?nh' => 'Nhật ký vận hành',
            'Sao l?u & kh?i ph?c' => 'Sao lưu & khôi phục',
            'Sao l?u ???c chu?n h?a ?? ph?c h?i nhanh khi c? s? c?.' => 'Sao lưu được chuẩn hóa để phục hồi nhanh khi có sự cố.',
            'Ph?n quy?n theo vai tr?' => 'Phân quyền theo vai trò',
            'D? li?u, th??ng hi?u, email v? c?u h?nh ???c t?ch ri?ng theo t?ng doanh nghi?p.' => 'Dữ liệu, thương hiệu, email và cấu hình được tách riêng theo từng doanh nghiệp.',
            'Qu?n l? ng??i d?ng' => 'Quản lý người dùng',
            'D?ng th?' => 'Dùng thử',
            'Ph? bi?n nh?t' => 'Phổ biến nhất',
            'T?i ch?nh' => 'Tài chính',
            'Th??ng m?i' => 'Thương mại',
            'Khuy?n d?ng' => 'Khuyên dùng',
            'M?i' => 'M?i',
            'H? t?ng' => 'Hạ tầng',
            'KT MatBao Invoice' => 'Hóa đơn điện tử',
            'KT SePay' => 'Thanh toán & Đối soát',
            'Inventory Showcase' => 'Quản lý kho',
            'SePay Showcase' => 'Thanh toán & Đối soát',
            'Invoice Showcase' => 'Hóa đơn điện tử',
            'CRM cho doanh nghiệp' => 'CRM cho doanh nghi&#7879;p',
            'Dùng thử mi?n ph?' => 'D&#249;ng th&#7917; mi&#7877;n ph&#237;',
            '??t l?ch demo' => '&#272;&#7863;t l&#7883;ch demo',
            'Kh?ng c?n c?i ??t' => 'Kh&#244;ng c&#7847;n c&#224;i &#273;&#7863;t',
            'K?ch ho?t trong v?i ph?t' => 'K&#237;ch ho&#7841;t trong v&#224;i ph&#250;t',
            'H? tr? tri?n khai' => 'H&#7895; tr&#7907; tri&#7875;n khai',
            'M? r?ng b?ng Marketplace' => 'M&#7903; r&#7897;ng b&#7857;ng Marketplace',
            'B? sung h?a ??n ?i?n t?, HSM, thanh to?n, website v? h? t?ng theo nhu c?u t?ng giai ?o?n.' => 'B&#7893; sung h&#243;a &#273;&#417;n &#273;i&#7879;n t&#7917;, HSM, thanh to&#225;n, website v&#224; h&#7841; t&#7847;ng theo nhu c&#7847;u t&#7915;ng giai &#273;o&#7841;n.',
            'Email & ??ng b?' => 'Email & &#273;&#7891;ng b&#7897;',
            '4,8 t?' => '4,8 t&#7927;',
            '+18,4% so v?i th?ng tr??c' => '+18,4% so v&#7899;i th&#225;ng tr&#432;&#7899;c',
            'M?i: 18 ? VIP: 42 ? Ch?m s?c: 71' => 'M?i: 18 ? VIP: 42 ? Ch?m s?c: 71',
            'Đã thu: 436 · Chờ thu: 72 · Quá hạn: 9' => '&#272;&#227; thu: 436 &middot; Ch&#7901; thu: 72 &middot; Qu&#225; h&#7841;n: 9',
            '??n h?ng m?i t? ABC' => '&#272;&#417;n h&#224;ng m&#7899;i t&#7915; ABC',
            'Thanh toán ?? ??ng b?' => 'Thanh to&#225;n &#273;&#227; &#273;&#7891;ng b&#7897;',
            'Khách tiềm năng m?i t? landing' => 'Kh&#225;ch ti&#7873;m n&#259;ng m&#7899;i t&#7915; landing',
            'Doanh nghiệp ?ang v?n h?nh' => 'Doanh nghi&#7879;p &#273;ang v&#7853;n h&#224;nh',
            'Doanh nghiệp t?ch ri?ng' => 'Doanh nghi&#7879;p t&#225;ch ri&#234;ng',
            'V? sao ch?n CRM Kh?ch T?t' => 'V&#236; sao ch&#7885;n CRM Kh&#225;ch T&#7889;t',
            'CRM cho doanh nghiệp c?n qu?n l? b?n h?ng, kho, h?a ??n v? thanh to?n tr?n m?t h? th?ng th?ng nh?t.' => 'CRM cho doanh nghi&#7879;p c&#7847;n qu&#7843;n l&#253; b&#225;n h&#224;ng, kho, h&#243;a &#273;&#417;n v&#224; thanh to&#225;n tr&#234;n m&#7897;t h&#7879; th&#7889;ng th&#7889;ng nh&#7845;t.',
            'Gi?m th?t tho?t doanh thu v? c?ng n? b?ng m?t lu?ng d? li?u xuy?n su?t t? b?n h?ng ??n ph?t h?nh h?a ??n.' => 'Gi&#7843;m th&#7845;t tho&#225;t doanh thu v&#224; c&#244;ng n&#7907; b&#7857;ng m&#7897;t lu&#7891;ng d&#7919; li&#7879;u xuy&#234;n su&#7889;t t&#7915; b&#225;n h&#224;ng &#273;&#7871;n ph&#225;t h&#224;nh h&#243;a &#273;&#417;n.',
            'Kh?ng c?n l?m vi?c tr?n nhi?u h? th?ng t?ch r?i.' => 'Kh&#244;ng c&#242;n l&#224;m vi&#7879;c tr&#234;n nhi&#7873;u h&#7879; th&#7889;ng t&#225;ch r&#7901;i.',
            'Dữ liệu tách riêng theo doanh nghi?p' => 'D&#7919; li&#7879;u t&#225;ch ri&#234;ng theo doanh nghi&#7879;p',
            'Vận hành nhiều doanh nghiệp nhưng vẫn giữ thương hiệu, dữ liệu và quyền truy cập tách riêng.' => 'V&#7853;n h&#224;nh nhi&#7873;u doanh nghi&#7879;p nh&#432;ng v&#7851;n gi&#7919; th&#432;&#417;ng hi&#7879;u, d&#7919; li&#7879;u v&#224; quy&#7873;n truy c&#7853;p t&#225;ch ri&#234;ng.',
            'D? m? r?ng kh?ch h?ng m? kh?ng l?m r?i m? h?nh v?n h?nh.' => 'D&#7877; m&#7903; r&#7897;ng kh&#225;ch h&#224;ng m&#224; kh&#244;ng l&#224;m r&#7889;i m&#244; h&#236;nh v&#7853;n h&#224;nh.',
            'Lu?ng thanh to?n g?n tr?c ti?p v?i h?a ??n ?? ??i so?t nhanh v? r? tr?ng th?i.' => 'Lu&#7891;ng thanh to&#225;n g&#7855;n tr&#7921;c ti&#7871;p v&#7899;i h&#243;a &#273;&#417;n &#273;&#7875; &#273;&#7889;i so&#225;t nhanh v&#224; r&#245; tr&#7841;ng th&#225;i.',
            'Gi?m sai l?ch thanh to?n v? t?ng t?c ghi nh?n doanh thu.' => 'Gi&#7843;m sai l&#7879;ch thanh to&#225;n v&#224; t&#259;ng t&#7889;c ghi nh&#7853;n doanh thu.',
            'Hóa đơn ?i?n t?' => 'H&#243;a &#273;&#417;n &#273;i&#7879;n t&#7917;',
            'Gi?m thao t?c tay v? r?i ro sai s?t ph?p l?.' => 'Gi&#7843;m thao t&#225;c tay v&#224; r&#7911;i ro sai s&#243;t ph&#225;p l&#253;.',
            '?t c?ng vi?c th? c?ng h?n, v?n h?nh ?n ??nh h?n.' => '&#205;t c&#244;ng vi&#7879;c th&#7911; c&#244;ng h&#417;n, v&#7853;n h&#224;nh &#7893;n &#273;&#7883;nh h&#417;n.',
            'Hóa đơn ?i?n t?, HSM, website, t?n mi?n v? hosting c? th? m? r?ng theo t?ng giai ?o?n t?ng tr??ng.' => 'H&#243;a &#273;&#417;n &#273;i&#7879;n t&#7917;, HSM, website, t&#234;n mi&#7873;n v&#224; hosting c&#243; th&#7875; m&#7903; r&#7897;ng theo t&#7915;ng giai &#273;o&#7841;n t&#259;ng tr&#432;&#7903;ng.',
            'N?ng c?p n?ng l?c h? th?ng m? kh?ng ph?i ??i n?n t?ng.' => 'N&#226;ng c&#7845;p n&#259;ng l&#7921;c h&#7879; th&#7889;ng m&#224; kh&#244;ng ph&#7843;i &#273;&#7893;i n&#7873;n t&#7843;ng.',
            'Khách tiềm năng moi' => 'Kh&#225;ch ti&#7873;m n&#259;ng m&#7899;i',
            'Ghi ch?' => 'Ghi ch&#250;',
            'Cu?c g?i' => 'Cu&#7897;c g&#7885;i',
            'L?ch h?n' => 'L&#7883;ch h&#7865;n',
            'T?n' => 'T&#7891;n',
            'Nh?p' => 'Nh&#7853;p',
            'Xu?t' => 'Xu&#7845;t',
            'Ph? bi?n' => 'Ph&#7893; bi&#7871;n',
            'Khám phá app' => 'Kh&#225;m ph&#225; &#7913;ng d&#7909;ng',
            'C?t l?i' => 'C&#7889;t l&#245;i',
            'Ph?n quy?n r? r?ng cho qu?n tr?, v?n h?nh, t?i ch?nh v? ng??i ph? tr?ch doanh nghi?p.' => 'Ph&#226;n quy&#7873;n r&#245; r&#224;ng cho qu&#7843;n tr&#7883;, v&#7853;n h&#224;nh, t&#224;i ch&#237;nh v&#224; ng&#432;&#7901;i ph&#7909; tr&#225;ch doanh nghi&#7879;p.',
            'Thanh toán, email, kh?i t?o h? th?ng v? webhook ??u c? nh?t k? ?? truy v?t.' => 'Thanh to&#225;n, email, kh&#7903;i t&#7841;o h&#7879; th&#7889;ng v&#224; webhook &#273;&#7873;u c&#243; nh&#7853;t k&#253; &#273;&#7875; truy v&#7871;t.',
            '<h5>Product</h5>' => '<h5>S&#7843;n ph&#7849;m</h5>',
            '<li>Inventory</li>' => '<li>Qu&#7843;n l&#253; kho</li>',
            '<li>Hóa đơn</li>' => '<li>H&#243;a &#273;&#417;n</li>',
        ];

        $html = strtr($html, $replacements);

        $html = strtr($html, [
            'CRM Showcase' => 'CRM bán hàng',
            'Dashboard' => 'Bảng điều khiển',
            'Khách tiềm năngs' => 'Khách tiềm năng',
            'Khách hàngs' => 'Khách hàng',
            'Ph?t h?nhd' => 'Phát hành',
            '?? thu' => 'Đã thu',
            'Overdue' => 'Quá hạn',
            'Pending' => 'Chờ thu',
            'B?ng ?i?u khi?n CRM' => 'Bảng điều khiển CRM',
            'Deals' => 'C? h?i',
            'Activities' => 'Hoạt động',
            'Pipeline active' => 'Đang theo dõi',
            'Calls and tasks' => 'Cuộc gọi và công việc',
            'B?ng ?i?u khi?n' => 'Bảng điều khiển',
            'M? h?ng' => 'Mã hàng',
            'Theo d?ied live' => 'Theo dõi trực tiếp',
            'C?nh b?o' => 'Cảnh báo',
            'Lu?n chuy?ns' => 'Luân chuyển',
            'Lu?n chuy?n' => 'Luân chuyển',
            'Low stock' => 'Tồn thấp',
            'Inbound/outbound' => 'Nhập/xuất',
            'Hi?n th? kho r? r?ng' => 'Hiển thị kho rõ ràng',
            'C?nh b?o t?n th?p' => 'Cảnh báo tồn thấp',
            'Lu?ng nh?p xu?t' => 'Luồng nhập xuất',
            '??i so?t nhanh' => 'Đối soát nhanh',
            '?i?u chuy?n' => 'Điều chuyển',
            'C?nh b?o thi?u h?ng' => 'Cảnh báo thiếu hàng',
            'Xu&#7845;t b?o c?o' => 'Xuất báo cáo',
            'Thu ti?n' => 'Thu tiền',
            'Matched' => 'Đã khớp',
            '??n h?n' => 'Đến hạn',
            '?? k?t n?i' => 'Đã kết nối',
            'C?nh b?o qu? h?n' => 'Cảnh báo quá hạn',
            'Ph?t h?nh' => 'Phát hành',
            'G?i' => 'G?i',
            'B?ng ?i?u khi?n thanh to?n' => 'Bảng điều khiển thanh toán',
            'Giao dịch/ng?y' => 'Giao dịch/ngày',
            'SePay ops' => 'Dòng tiền trực tiếp',
            'Ch?a kh?p' => 'Chưa khớp',
            'Failed' => 'L?i',
            'T? l? th?nh c?ng' => 'Tỷ lệ thành công',
            'Auto reconcile' => 'Đối soát tự động',
            '??i so?t' => 'Đối soát',
            'Fast release' => 'Xử lý nhanh',
            'Webhook th?i gian th?c' => 'Webhook thời gian thực',
            '?? kh?p v? ch?a kh?p' => 'Đã khớp và chưa khớp',
            '??i so?t t? ??ng' => 'Đối soát tự động',
            'Nh?n webhook' => 'Nhận webhook',
            'Kh?p h?a ??n' => 'Khớp hóa đơn',
            'B?ng ?i?u khi?n d? ?n' => 'Bảng điều khiển dự án',
            'Ti?n ??' => 'Tiến độ',
            'T?c ?? b?n giao' => 'Tốc độ bàn giao',
            'M?c ti?n ??' => 'Mốc tiến độ',
            'Nh?m' => 'Nhóm',
            '?ang l?m' => 'Đang làm',
            'H?ng m?c m?' => 'Hạng mục mở',
            '?? b?n giao' => 'Đã bàn giao',
            'C?n ch? ?' => 'Cần chú ý',
            'C?n r? so?t' => 'Cần rà soát',
            'Ti?n ?? r? r?ng' => 'Tiến độ rõ ràng',
            'Giao vi?c r? r?ng' => 'Giao việc rõ ràng',
            'Theo d?i m?c' => 'Theo dõi mốc',
            'Ph?i h?p nh?m' => 'Phối hợp nhóm',
            'L?p k? ho?ch' => 'Lập kế hoạch',
            'Ph?n c?ng' => 'Phân công',
            'B?n giao' => 'Bàn giao',
            'Qu?n l? t?i li?u' => 'Quản lý tài liệu',
            'C? ph?n quy?n' => 'Có phân quyền',
            'Danh m?c' => 'Danh mục',
            'Ph? duy?t' => 'Phê duyệt',
            'Ph?n quy?n' => 'Phân quyền',
            'Tài li?u m?i' => 'Tài liệu mới',
            'H?m nay' => 'Hôm nay',
            'Tài li?u d?ng chung' => 'Tài liệu dùng chung',
            'Active' => 'Đang hoạt động',
            'Controlled' => 'Được kiểm soát',
            'Lu?ng ph? duy?t' => 'Luồng phê duyệt',
            '5 b??c' => '5 bước',
            'H?p ??ng / Ch?nh s?ch / T?i s?n' => 'Hợp đồng / Chính sách / Tài sản',
            'Lu?ng ch? ph? duy?t' => 'Luồng chờ phê duyệt',
            '?? ?p d?ng ph?n quy?n' => 'Đã áp dụng phân quyền',
            'C?y danh m?c' => 'Cây danh mục',
            'Ki?m so?t quy?n' => 'Kiểm soát quyền',
            'T?i l?n' => 'Tải lên',
            'C?ng b?' => 'Công bố',
            'L?u tr?' => 'Lưu trữ',
            'Today' => 'Hôm nay',
            'Shared docs' => 'Tài liệu dùng chung',
            'Approval flow' => 'Luồng phê duyệt',
        ]);

        return $html;
    }

    private function renderPublicFallback($page)
    {
        $branding = $this->resolveBrandingContext();
        $localization = $this->resolveLocalizationContext();
        $brandName = trim((string) ($branding['company_name'] ?? '')) ?: 'CRM Khách Tốt';
        $title = $brandName . ' - CRM';
        $logo = trim((string) ($branding['logo'] ?? ''));
        $favicon = trim((string) ($branding['favicon'] ?? ''));
        $lang = trim((string) ($localization['language'] ?? '')) ?: 'vi';

        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
        if ($favicon !== '') {
            echo '<link rel="icon" href="' . htmlspecialchars(base_url('uploads/company/' . $favicon), ENT_QUOTES, 'UTF-8') . '">';
        }
        echo '<style>body{font-family:Arial,sans-serif;margin:0;background:#f8fafc;color:#0f172a}.wrap{max-width:960px;margin:0 auto;padding:48px 24px}.brand{display:flex;align-items:center;gap:12px;margin-bottom:24px}.brand img{height:40px;width:auto}.hero{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:32px;box-shadow:0 10px 30px rgba(15,23,42,.06)}a.btn{display:inline-block;margin-right:12px;padding:12px 18px;border-radius:10px;background:#1f3a5f;color:#fff;text-decoration:none}.muted{color:#64748b}.pill{display:inline-block;background:#e2e8f0;border-radius:999px;padding:6px 12px;font-size:12px;margin-bottom:16px}</style></head><body>';
        echo '<div class="wrap">';
        echo '<div class="brand">';
        if ($logo !== '') {
            echo '<img src="' . htmlspecialchars(base_url('uploads/company/' . $logo), ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') . '">';
        }
        echo '<strong>' . htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') . '</strong></div>';
        echo '<div class="hero">';
        echo '<div class="pill">Ch? ?? d? ph?ng landing</div>';
        echo '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
        echo '<p class="muted">Landing d? ph?ng ???c hi?n th? khi template ch?nh g?p l?i khi render.</p>';
        echo '<p><a class="btn" href="' . htmlspecialchars(site_url('login'), ENT_QUOTES, 'UTF-8') . '">Đăng nhập</a><a class="btn" href="' . htmlspecialchars(site_url('signup'), ENT_QUOTES, 'UTF-8') . '" style="background:#2563eb">Đặt hàng ngay</a></p>';
        echo '</div></div></body></html>';
        exit;
    }

    private function renderSignupFallback()
    {
        $this->output->set_status_header(500);
        $branding = $this->resolveBrandingContext();
        $brandName = trim((string) ($branding['company_name'] ?? '')) ?: 'CRM Khách Tốt';
        $logo = trim((string) ($branding['logo'] ?? ''));
        $favicon = trim((string) ($branding['favicon'] ?? ''));

        $this->output->set_content_type('text/html', 'utf-8');
        echo '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . htmlspecialchars($brandName . ' - Đăng ký tạm gián đoạn', ENT_QUOTES, 'UTF-8') . '</title>';
        if ($favicon !== '') {
            echo '<link rel="icon" href="' . htmlspecialchars(base_url('uploads/company/' . $favicon), ENT_QUOTES, 'UTF-8') . '">';
        }
        echo '<style>body{font-family:Arial,sans-serif;margin:0;background:#f8fafc;color:#0f172a}.wrap{max-width:860px;margin:0 auto;padding:56px 20px}.card{background:#fff;border:1px solid #dbe4ef;border-radius:18px;padding:32px;box-shadow:0 18px 48px rgba(15,23,42,.08)}.brand{display:flex;align-items:center;gap:12px;margin-bottom:20px}.brand img{height:40px;width:auto}.pill{display:inline-block;background:#fee2e2;color:#991b1b;border-radius:999px;padding:6px 12px;font-size:12px;font-weight:700;margin-bottom:16px}.muted{color:#64748b;line-height:1.7}.actions{margin-top:24px;display:flex;gap:12px;flex-wrap:wrap}.btn{display:inline-block;padding:12px 16px;border-radius:10px;text-decoration:none;font-weight:700}.btn.primary{background:#1f4c81;color:#fff}.btn.secondary{background:#e2e8f0;color:#0f172a}</style></head><body>';
        echo '<div class="wrap"><div class="card">';
        echo '<div class="brand">';
        if ($logo !== '') {
            echo '<img src="' . htmlspecialchars(base_url('uploads/company/' . $logo), ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') . '">';
        }
        echo '<strong>' . htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') . '</strong></div>';
        echo '<span class="pill">Đăng ký tạm gián đoạn</span>';
        echo '<h1 style="margin:0 0 12px;font-size:32px;line-height:1.15;">Hệ thống đăng ký đang tạm thời không sẵn sàng</h1>';
        echo '<p class="muted">Vui lòng tải lại trang sau ít phút. Nếu lỗi vẫn còn, hãy liên hệ quản trị viên để kiểm tra trạng thái hệ thống.</p>';
        echo '<div class="actions">';
        echo '<a class="btn primary" href="' . htmlspecialchars(site_url('signup'), ENT_QUOTES, 'UTF-8') . '">Tải lại đăng ký</a>';
        echo '<a class="btn secondary" href="' . htmlspecialchars(site_url('pricing'), ENT_QUOTES, 'UTF-8') . '">Xem bảng giá</a>';
        echo '</div></div></div></body></html>';
        exit;
    }

    private function isLandingEnabled()
    {
        $enabled = (string) $this->Kt_landing_model->get_setting('landing_enabled', '1');
        return $enabled !== '0';
    }

    private function trackPageView($pageSlug)
    {
        $this->Kt_landing_model->track_event('page_view', [
            'page_slug' => (string) $pageSlug,
            'source' => 'landing',
            'utm_source' => (string) $this->input->get('utm_source', true),
            'utm_medium' => (string) $this->input->get('utm_medium', true),
            'utm_campaign' => (string) $this->input->get('utm_campaign', true),
            'ip_address' => (string) $this->input->ip_address(),
        ]);
    }

    private function indexPlanOverrides(array $rows)
    {
        $out = [];
        foreach ($rows as $row) {
            $out[(int) ($row['plan_id'] ?? 0)] = $row;
        }
        return $out;
    }

    private function applyPlanOverrides(array $plans, array $overrides, array $templateOverrides = [])
    {
        $result = [];
        $service = kt_landing_pricing_sync_service();
        foreach ($plans as $plan) {
            $planId = (int) ($plan['id'] ?? 0);
            $landingOverride = $overrides[$planId] ?? null;
            if (!empty($templateOverrides[$planId]) && is_array($templateOverrides[$planId])) {
                $landingOverride = array_merge((array) $landingOverride, $templateOverrides[$planId]);
            }
            $resolved = $service->resolvePlanForLanding($plan, $landingOverride);
            if (empty($resolved)) {
                continue;
            }
            $result[] = $resolved;
        }

        usort($result, static function ($a, $b) {
            $aSort = (int) ($a['landing_sort_order'] ?? 0);
            $bSort = (int) ($b['landing_sort_order'] ?? 0);
            if ($aSort === $bSort) {
                return ((int) ($a['display_order'] ?? 0)) <=> ((int) ($b['display_order'] ?? 0));
            }
            return $aSort <=> $bSort;
        });

        return $result;
    }
    private function buildSignupAddonCatalog()
    {
        return [
            ['code' => 'matbao_invoice', 'name' => 'Hóa đơn điện tử', 'desc' => 'Hóa đơn điện tử và quy trình ký số liên thông.'],
            ['code' => 'hsm_signing', 'name' => 'Chữ ký số tập trung / HSM', 'desc' => 'Ký số tập trung cho hóa đơn và hợp đồng.'],
            ['code' => 'sepay', 'name' => 'Thanh toán & Đối soát', 'desc' => 'Đối soát thanh toán và nhận tiền nhanh.'],
            ['code' => 'domain', 'name' => 'Domain', 'desc' => 'Đăng ký hoặc transfer tên miền doanh nghiệp.'],
            ['code' => 'hosting', 'name' => 'Hosting', 'desc' => 'Tài nguyên hosting bổ sung cho website công ty.'],
            ['code' => 'website', 'name' => 'Website Setup', 'desc' => 'Gói setup website nhanh theo mẫu doanh nghiệp.'],
        ];
    }

    private function handleSignupSubmit(array $data)
    {
        require_once module_dir_path(KT_SAAS_MODULE, 'services/BillingEngineService.php');
        require_once module_dir_path(KT_SAAS_MODULE, 'services/PaymentCollectionService.php');

        $companyName = trim((string) ($data['company_name'] ?? ''));
        $ownerName = trim((string) ($data['owner_name'] ?? ''));
        $ownerEmail = trim((string) ($data['owner_email'] ?? ''));
        $planId = (int) ($data['plan_id'] ?? 0);
        $phone = trim((string) ($data['phone'] ?? ''));
        $desiredSubdomain = strtolower(trim((string) ($data['desired_subdomain'] ?? '')));
        $selectedAddons = $data['addons'] ?? [];
        $honeypot = trim((string) ($data['website'] ?? ''));
        $signupTs = (int) ($data['signup_ts'] ?? 0);

        if (!is_array($selectedAddons)) {
            $selectedAddons = [];
        }
        $selectedAddons = array_values(array_filter(array_map('strval', $selectedAddons), static function ($v) {
            return trim($v) !== '';
        }));

        if ($honeypot !== '') {
            $this->Kt_landing_model->log_activity('landing.signup_blocked_honeypot', 'warning', [
                'ip' => (string) $this->input->ip_address(),
            ], null);
            return ['success' => false, 'message' => 'Yêu cầu không hợp lệ.'];
        }

        if ($signupTs > 0 && (time() - $signupTs) < 3) {
            $this->Kt_landing_model->log_activity('landing.signup_blocked_too_fast', 'warning', [
                'ip' => (string) $this->input->ip_address(),
                'signup_ts' => $signupTs,
            ], null);
            return ['success' => false, 'message' => 'Thao tác quá nhanh. Vui lòng thử lại sau vài giây.'];
        }

        if ($companyName === '' || $ownerName === '' || $ownerEmail === '' || $planId <= 0) {
            return ['success' => false, 'message' => 'Vui lòng nhập đủ tên công ty, người liên hệ, email và gói dịch vụ.'];
        }

        if (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Email liên hệ không hợp lệ.'];
        }

        if ($desiredSubdomain !== '' && !preg_match('/^[a-z0-9-]+$/', $desiredSubdomain)) {
            return ['success' => false, 'message' => 'Subdomain chỉ được gồm a-z, 0-9 và dấu gạch ngang.'];
        }

        if (!$this->canSubmitSignupNow($ownerEmail)) {
            $this->Kt_landing_model->log_activity('landing.signup_blocked_rate_limit', 'warning', [
                'ip' => (string) $this->input->ip_address(),
                'owner_email' => $ownerEmail,
            ], null);
            return ['success' => false, 'message' => 'Bạn vừa gửi đăng ký. Vui lòng đợi 60 giây rồi thử lại.'];
        }

        $plan = $this->Kt_saas_model->get_plan($planId);
        if (!$plan || (int) ($plan['is_active'] ?? 0) !== 1 || (int) ($plan['is_public'] ?? 0) !== 1) {
            return ['success' => false, 'message' => 'Gói dịch vụ không còn khả dụng công khai.'];
        }

        $tenant = $this->findReusableDraftTenant($ownerEmail, $planId);
        if ($desiredSubdomain !== '') {
            $subdomainCheck = $this->Kt_saas_model->checkSubdomainAvailability($desiredSubdomain, $tenant ? (int) ($tenant['id'] ?? 0) : null);
            if (empty($subdomainCheck['available'])) {
                $suggestion = !empty($subdomainCheck['suggestions'][0]) ? (string) $subdomainCheck['suggestions'][0] : '';
                return [
                    'success' => false,
                    'message' => (string) ($subdomainCheck['message'] ?? 'Subdomain is unavailable.'),
                    'field' => 'desired_subdomain',
                    'suggestions' => (array) ($subdomainCheck['suggestions'] ?? []),
                    'suggestion' => $suggestion,
                    'reason' => (string) ($subdomainCheck['reason'] ?? 'occupied'),
                ];
            }
        }
        $isNewTenant = false;
        if (!$tenant) {
            $createResult = $this->Kt_saas_model->save_tenant([
                'company_name' => $companyName,
                'owner_name' => $ownerName,
                'owner_email' => $ownerEmail,
                'phone' => $phone,
                'subdomain' => $desiredSubdomain,
                'plan_id' => $planId,
                'status' => 'draft',
            ]);

            if (empty($createResult['success']) || empty($createResult['id'])) {
                return [
                    'success' => false,
                    'message' => (string) ($createResult['message'] ?? 'Không thể tạo đăng ký. Vui lòng thử lại.'),
                ];
            }
            $tenant = $this->Kt_saas_model->get_tenant((int) $createResult['id']);
            $isNewTenant = true;
        } else {
            $tenantId = (int) $tenant['id'];
            $this->db->where('id', $tenantId)->update(db_prefix() . 'kt_saas_tenants', [
                'company_name' => $companyName,
                'owner_name' => $ownerName,
                'phone' => $phone,
                'subdomain' => $desiredSubdomain !== '' ? $desiredSubdomain : ($tenant['subdomain'] ?? null),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $tenant = $this->Kt_saas_model->get_tenant($tenantId);
        }

        if (!$tenant) {
            return ['success' => false, 'message' => 'Đã tạo đăng ký nhưng không đọc được tenant vừa tạo.'];
        }

        $tenantId = (int) $tenant['id'];
        $this->Kt_saas_model->log_activity('landing.signup_accepted', 'info', [
            'tenant_id' => $tenantId,
            'plan_id' => $planId,
            'owner_email' => $ownerEmail,
            'desired_subdomain' => $desiredSubdomain,
            'addons' => implode(',', $selectedAddons),
            'is_new_tenant' => $isNewTenant ? 1 : 0,
        ], $tenantId);

        $this->db
            ->where('tenant_id', $tenantId)
            ->where('status', 'queued')
            ->where('job_type', 'provision_tenant')
            ->delete(db_prefix() . 'kt_saas_provision_jobs');
        $this->db->where('id', $tenantId)->update(db_prefix() . 'kt_saas_tenants', [
            'status' => 'draft',
            'provisioning_status' => 'queued',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $subscription = $this->Kt_saas_model->get_current_subscription($tenantId);
        if (!$subscription) {
            return [
                'success' => true,
                'message' => $isNewTenant
                    ? 'Đăng ký đã được ghi nhận ở trạng thái nháp. Chưa tạo được subscription.'
                    : 'Đăng ký đã tồn tại ở trạng thái nháp và đã được cập nhật thông tin.',
                'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
                'plan_name' => (string) ($plan['plan_name'] ?? ''),
                'desired_subdomain' => (string) ($tenant['subdomain'] ?? ''),
                'addons' => $selectedAddons,
            ];
        }

        $invoice = $this->Kt_saas_model->find_open_tenant_invoice_by_reason($tenantId, (int) $subscription['id'], 'public_signup');
        if (!$invoice) {
            $billing = new BillingEngineService();
            $invoiceResult = $billing->createSubscriptionInvoice($tenant, $subscription, $plan, [
                'source' => 'public_signup_phase2',
                'reason' => 'public_signup',
            ]);
            if (!empty($invoiceResult['success']) && !empty($invoiceResult['invoice_id'])) {
                $invoice = $this->Kt_saas_model->get_invoice((int) $invoiceResult['invoice_id']);
            }
        }

        if (empty($invoice['id'])) {
            return [
                'success' => true,
                'message' => 'Đăng ký đã tạo thành công (nháp), nhưng chưa tạo được hóa đơn.',
                'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
            ];
        }

        $checkoutUrl = $this->buildPreferredCheckoutUrl($invoice, $tenant);
        $invoicePayload = json_decode((string) ($invoice['payload_json'] ?? ''), true);
        if (!is_array($invoicePayload)) {
            $invoicePayload = [];
        }
        $billingSummary = is_array($invoicePayload['billing_summary'] ?? null) ? $invoicePayload['billing_summary'] : [];
        $lineItems = is_array($invoicePayload['line_items'] ?? null) ? array_values($invoicePayload['line_items']) : [];
        $subscriptionPrice = (float) ($billingSummary['plan_price'] ?? ($plan['price'] ?? 0));
        $setupFee = (float) ($billingSummary['setup_fee'] ?? ($plan['setup_fee'] ?? 0));
        $invoiceTotal = (float) ($billingSummary['grand_total'] ?? ($invoice['grand_total'] ?? ($subscriptionPrice + $setupFee)));
        $this->Kt_saas_model->log_activity('landing.signup_invoice_ready', 'info', [
            'tenant_id' => $tenantId,
            'invoice_id' => (int) ($invoice['id'] ?? 0),
            'invoice_number' => (string) ($invoice['invoice_number'] ?? ''),
        ], $tenantId);
        return [
            'success' => true,
            'message' => $isNewTenant
                ? 'Đăng ký đã được tạo. Hệ thống đã tạo hóa đơn chờ xử lý.'
                : 'Đăng ký đã tồn tại dạng nháp. Hệ thống dùng lại hóa đơn chờ xử lý.',
            'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
            'invoice_number' => (string) ($invoice['invoice_number'] ?? ''),
            'checkout_url' => (string) $checkoutUrl,
            'plan_name' => (string) ($plan['plan_name'] ?? ''),
            'desired_subdomain' => (string) ($tenant['subdomain'] ?? ''),
            'subscription_price' => $subscriptionPrice,
            'setup_fee' => $setupFee,
            'invoice_total' => $invoiceTotal,
            'line_items' => $lineItems,
            'addons' => $selectedAddons,
        ];
    }

    private function buildPreferredCheckoutUrl(array $invoice, array $tenant)
    {
        try {
            if (function_exists('module_is_active') && module_is_active('kt_sepay')) {
                $this->load->library('kt_sepay/Kt_sepay_gateway');
                $this->load->model('kt_sepay/Kt_sepay_model');
                $requestId = (int) $this->kt_sepay_gateway->createKtSaasInvoiceRequest($invoice, $tenant, [
                    'source' => 'kt_landing_signup_phase3_1',
                ]);
                if ($requestId > 0) {
                    $request = $this->Kt_sepay_model->get_payment_request($requestId);
                    if (!empty($request['id']) && !empty($request['access_token'])) {
                        return site_url('kt_sepay/pay/' . (int) $request['id'] . '/' . rawurlencode((string) $request['access_token']));
                    }
                }
            }
        } catch (Throwable $e) {
            // Fallback to default checkout URL below.
        }

        $payment = new PaymentCollectionService();
        return $payment->getCheckoutUrl($invoice, $tenant);
    }

    private function findReusableDraftTenant($ownerEmail, $planId)
    {
        $ownerEmail = trim((string) $ownerEmail);
        $planId = (int) $planId;
        if ($ownerEmail === '' || $planId <= 0) {
            return null;
        }

        $tenant = $this->db
            ->where('owner_email', $ownerEmail)
            ->where('plan_id', $planId)
            ->where('status', 'draft')
            ->where('deleted_at IS NULL', null, false)
            ->where('created_at >=', date('Y-m-d H:i:s', strtotime('-7 days')))
            ->order_by('id', 'desc')
            ->get(db_prefix() . 'kt_saas_tenants')
            ->row_array();

        if (!$tenant) {
            return null;
        }

        return $this->Kt_saas_model->get_tenant((int) $tenant['id']);
    }

    private function canSubmitSignupNow($ownerEmail)
    {
        $ip = (string) $this->input->ip_address();
        $keyRaw = strtolower(trim((string) $ownerEmail)) . '|' . $ip;
        $key = 'kt_landing_signup_' . md5($keyRaw);
        $path = rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $key . '.lock';
        $now = time();
        $window = 60;

        if (is_file($path)) {
            $last = (int) @file_get_contents($path);
            if ($last > 0 && ($now - $last) < $window) {
                return false;
            }
        }

        @file_put_contents($path, (string) $now, LOCK_EX);
        return true;
    }
}
