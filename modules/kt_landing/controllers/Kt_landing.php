<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_landing extends App_Controller
{
    private $allowedTemplates = ['fastwork_inspired', 'corporate_saas', 'modern_growth', 'minimal_enterprise'];

    public function __construct()
    {
        register_shutdown_function(function () {
            $error = error_get_last();
            if (!$error) {
                return;
            }
            $message = sprintf(
                "[%s] %s in %s:%d\n",
                date('Y-m-d H:i:s'),
                (string) ($error['message'] ?? 'unknown'),
                (string) ($error['file'] ?? 'unknown'),
                (int) ($error['line'] ?? 0)
            );
            @file_put_contents(APPPATH . 'logs/kt_landing_shutdown.log', $message, FILE_APPEND);
        });
        parent::__construct();
        $this->load->helper(['url']);
        $this->load->helper('kt_saas/kt_saas');
        $this->load->helper(KT_LANDING_MODULE . '/kt_landing');
        $this->load->model('kt_saas/Kt_saas_model');
        $this->load->model(KT_LANDING_MODULE . '/Kt_landing_model');
    }

    public function index()
    {
        $this->home();
    }

    public function home()
    {
        if ((string) $this->input->get('debug_ping') === '1') {
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'landing-home-ok';
            return;
        }
        @file_put_contents(APPPATH . 'logs/kt_landing_web_trace.log', '[' . date('Y-m-d H:i:s') . '] home tenant=' . (int) $this->isTenantRuntime() . "\n", FILE_APPEND);
        try {
            error_log('KT_LANDING_HOME: start tenant_runtime=' . (int) $this->isTenantRuntime());
            if (!$this->isLandingEnabled()) {
                error_log('KT_LANDING_HOME: landing disabled');
                $mode = (string) $this->Kt_landing_model->get_setting('homepage_mode', 'default_perfex');
                if ($mode === 'redirect_login') {
                    redirect(site_url('login'));
                    return;
                }
                redirect(site_url('clients'));
                return;
            }

            error_log('KT_LANDING_HOME: landing enabled');
            $data = $this->safeBuildLandingData('home');
            error_log('KT_LANDING_HOME: data prepared template=' . (string) ($data['template_code'] ?? ''));
            if (!$this->isTenantRuntime()) {
                $this->safeTrackPageView('home');
            }
            error_log('KT_LANDING_HOME: track complete');
            $this->renderLandingTemplate($data);
            error_log('KT_LANDING_HOME: render complete');
        } catch (Throwable $e) {
            log_message('error', 'KT Landing home failed: ' . $e->getMessage());
            error_log('KT_LANDING_HOME: caught throwable ' . $e->getMessage());
            $this->renderLandingFallbackResponse('home');
        }
    }

    public function pricing()
    {
        if ((string) $this->input->get('debug_ping') === '1') {
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'landing-pricing-ok';
            return;
        }
        @file_put_contents(APPPATH . 'logs/kt_landing_web_trace.log', '[' . date('Y-m-d H:i:s') . '] pricing tenant=' . (int) $this->isTenantRuntime() . "\n", FILE_APPEND);
        try {
            error_log('KT_LANDING_PRICING: start tenant_runtime=' . (int) $this->isTenantRuntime());
            $data = $this->safeBuildLandingData('pricing');
            error_log('KT_LANDING_PRICING: data prepared template=' . (string) ($data['template_code'] ?? ''));
            if (!$this->isTenantRuntime()) {
                $this->safeTrackPageView('pricing');
            }
            error_log('KT_LANDING_PRICING: track complete');
            $this->renderLandingTemplate($data);
            error_log('KT_LANDING_PRICING: render complete');
        } catch (Throwable $e) {
            log_message('error', 'KT Landing pricing failed: ' . $e->getMessage());
            error_log('KT_LANDING_PRICING: caught throwable ' . $e->getMessage());
            $this->renderLandingFallbackResponse('pricing');
        }
    }

    public function signup()
    {
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

        $data['title'] = 'Đăng ký KT SaaS';
        $data['public_plans'] = $this->Kt_saas_model->get_public_plans();
        $data['public_addons'] = $this->buildSignupAddonCatalog();
        $this->trackPageView('signup');
        $this->load->view(KT_LANDING_MODULE . '/public/signup', $data);
    }

    public function signup_progress($tenantCode = '')
    {
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
    }

    public function blog()
    {
        try {
            $data['title'] = 'Blog';
            try {
                $data['posts'] = $this->Kt_landing_model->get_blog_posts(true);
            } catch (Throwable $e) {
                log_message('error', 'KT Landing blog data failed: ' . $e->getMessage());
                $data['posts'] = [];
            }
            if (!$this->isTenantRuntime()) {
                $this->safeTrackPageView('blog');
            }
            $this->load->view(KT_LANDING_MODULE . '/public/blog', $data);
        } catch (Throwable $e) {
            log_message('error', 'KT Landing blog failed: ' . $e->getMessage());
            $this->renderLandingFallbackResponse('blog');
        }
    }

    public function contact_submit()
    {
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
    }

    public function signup_status()
    {
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
    }

    private function isTenantRuntime()
    {
        return function_exists('kt_saas_is_tenant_runtime') && kt_saas_is_tenant_runtime();
    }

    private function renderLandingTemplate(array $data)
    {
        $template = $this->resolveTemplateCode();
        $data['template_code'] = $template;
        $viewPath = KT_LANDING_MODULE . '/public/templates/' . $template . '/index';
        $this->load->view($viewPath, $data);
    }

    private function safeBuildLandingData($page)
    {
        try {
            return $this->buildLandingData($page);
        } catch (Throwable $e) {
            log_message('error', 'KT Landing buildLandingData failed for ' . $page . ': ' . $e->getMessage());
            return $this->buildLandingFallbackData($page);
        }
    }

    private function safeTrackPageView($pageSlug)
    {
        try {
            $this->trackPageView($pageSlug);
        } catch (Throwable $e) {
            log_message('error', 'KT Landing trackPageView failed for ' . $pageSlug . ': ' . $e->getMessage());
        }
    }

    private function resolveTemplateCode()
    {
        $preview = trim((string) $this->input->get('tpl', true));
        if (in_array($preview, $this->allowedTemplates, true)) {
            return $preview;
        }

        $saved = trim((string) $this->Kt_landing_model->get_setting('default_template', ''));
        if ($saved === '') {
            $saved = trim((string) get_option('kt_landing_template'));
        }
        if (in_array($saved, $this->allowedTemplates, true)) {
            return $saved;
        }

        return 'fastwork_inspired';
    }

    private function buildLandingData($page)
    {
        $sections = $this->Kt_landing_model->get_sections();
        $menus = $this->Kt_landing_model->get_menus();
        $blogPosts = $this->Kt_landing_model->get_blog_posts(true);
        $planOverrides = $this->indexPlanOverrides($this->Kt_landing_model->get_plan_overrides());

        $brandingContext = function_exists('kt_saas_resolve_branding_context')
            ? kt_saas_resolve_branding_context(['scope' => 'landing', 'log_fallback' => true])
            : [];
        $localizationContext = function_exists('kt_saas_resolve_localization_context')
            ? kt_saas_resolve_localization_context(['scope' => 'landing'])
            : [];

        $brandName = trim((string) ($brandingContext['company_name'] ?? ''));
        if ($brandName === '') {
            $brandName = 'KT SaaS Platform';
        }

        $logo = trim((string) ($brandingContext['logo'] ?? ''));
        $favicon = trim((string) ($brandingContext['favicon'] ?? ''));
        $headerCta = trim((string) $this->Kt_landing_model->get_setting('kt_landing_header_cta_text', ''));
        if ($headerCta === '') {
            $headerCta = trim((string) get_option('kt_landing_header_cta_text'));
        }
        if ($headerCta === '') {
            $headerCta = 'Đăng ký';
        }

        $heroTitle = trim((string) $this->Kt_landing_model->get_setting('kt_landing_hero_title', ''));
        if ($heroTitle === '') {
            $heroTitle = trim((string) get_option('kt_landing_hero_title'));
        }
        if ($heroTitle === '') {
            $heroTitle = 'Nền tảng CRM và vận hành doanh nghiệp cho SME';
        }
        $heroSubtitle = trim((string) $this->Kt_landing_model->get_setting('kt_landing_hero_subtitle', ''));
        if ($heroSubtitle === '') {
            $heroSubtitle = trim((string) get_option('kt_landing_hero_subtitle'));
        }
        if ($heroSubtitle === '') {
            $heroSubtitle = 'Chuẩn hóa bán hàng, dịch vụ, tài chính và cộng tác trên một hệ thống thống nhất.';
        }
        $heroImage = trim((string) $this->Kt_landing_model->get_setting('kt_landing_hero_image', ''));
        if ($heroImage === '') {
            $heroImage = trim((string) get_option('kt_landing_hero_image'));
        }

        $primaryColor = trim((string) $this->Kt_landing_model->get_setting('primary_color', ''));
        if ($primaryColor === '') {
            $primaryColor = trim((string) get_option('kt_landing_primary_color'));
        }
        if ($primaryColor === '') {
            $primaryColor = '#1f3a5f';
        }
        $secondaryColor = trim((string) $this->Kt_landing_model->get_setting('secondary_color', ''));
        if ($secondaryColor === '') {
            $secondaryColor = trim((string) get_option('kt_landing_secondary_color'));
        }
        if ($secondaryColor === '') {
            $secondaryColor = '#4b5563';
        }
        $ctaColor = trim((string) $this->Kt_landing_model->get_setting('accent_color', ''));
        if ($ctaColor === '') {
            $ctaColor = trim((string) get_option('kt_landing_cta_color'));
        }
        if ($ctaColor === '') {
            $ctaColor = '#2563eb';
        }

        $metaTitle = trim((string) $this->Kt_landing_model->get_setting('default_meta_title', ''));
        if ($metaTitle === '') {
            $metaTitle = trim((string) get_option('kt_landing_meta_title'));
        }
        if ($metaTitle === '') {
            $metaTitle = $brandName . ' - SaaS CRM';
        }
        $metaDescription = trim((string) $this->Kt_landing_model->get_setting('default_meta_description', ''));
        if ($metaDescription === '') {
            $metaDescription = trim((string) get_option('kt_landing_meta_description'));
        }
        if ($metaDescription === '') {
            $metaDescription = 'Giải pháp SaaS CRM và quản trị doanh nghiệp cho đội ngũ vừa và nhỏ.';
        }

        $features = $this->buildFeatureItems();
        $faqs = $this->buildFaqItems();
        $testimonials = $this->buildTestimonialItems();
        $productMarketing = $this->buildProductMarketingData();
        $publicPlans = $this->applyPlanOverrides($this->Kt_saas_model->get_public_plans(), $planOverrides);
        $landingContent = $this->buildLandingContentFromCms();

        return [
            'title' => $metaTitle,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'canonical_url' => current_url(),
            'og_image' => $heroImage !== '' ? $heroImage : '',
            'brand_name' => $brandName,
            'logo' => $logo,
            'dark_logo' => (string) ($brandingContext['dark_logo'] ?? $logo),
            'favicon' => $favicon,
            'branding_context' => $brandingContext,
            'localization_context' => $localizationContext,
            'header_cta_text' => $headerCta,
            'hero_title' => $heroTitle,
            'hero_subtitle' => $heroSubtitle,
            'hero_image' => $heroImage,
            'primary_color' => $primaryColor,
            'secondary_color' => $secondaryColor,
            'cta_color' => $ctaColor,
            'features' => $features,
            'faqs' => $faqs,
            'testimonials' => $testimonials,
            'product_marketing' => $productMarketing,
            'landing_content' => $landingContent,
            'public_plans' => $publicPlans,
            'footer_text' => trim((string) $this->Kt_landing_model->get_setting('kt_landing_footer_text', '')) ?: (trim((string) get_option('kt_landing_footer_text')) ?: ('© ' . date('Y') . ' ' . $brandName)),
            'page' => $page,
            'sections' => $sections,
            'menus' => $menus,
            'blog_posts' => $blogPosts,
            'custom_css' => (string) $this->Kt_landing_model->get_setting('custom_css', ''),
            'custom_js' => (string) $this->Kt_landing_model->get_setting('custom_js', ''),
        ];
    }

    private function buildLandingFallbackData($page)
    {
        $brandName = trim((string) get_option('companyname'));
        if ($brandName === '') {
            $brandName = 'KT SaaS Platform';
        }

        $logo = trim((string) get_option('company_logo'));
        $darkLogo = trim((string) get_option('company_logo_dark'));
        $favicon = trim((string) get_option('favicon'));
        $language = trim((string) get_option('active_language'));
        if ($language === '') {
            $language = 'vi';
        }

        $brandingContext = [
            'company_name' => $brandName,
            'logo' => $logo,
            'dark_logo' => $darkLogo !== '' ? $darkLogo : $logo,
            'favicon' => $favicon,
            'source' => 'tenant_option',
            'fallback_used' => false,
        ];
        $localizationContext = [
            'language' => $language,
            'source' => 'tenant_option',
        ];

        return [
            'title' => $brandName . ' - SaaS CRM',
            'meta_title' => $brandName . ' - SaaS CRM',
            'meta_description' => 'Gi??i ph??p SaaS CRM v?? qu???n tr??? doanh nghi???p cho ?????i ng?? v???a v?? nh???.',
            'canonical_url' => current_url(),
            'og_image' => '',
            'brand_name' => $brandName,
            'logo' => $logo,
            'dark_logo' => $darkLogo !== '' ? $darkLogo : $logo,
            'favicon' => $favicon,
            'branding_context' => $brandingContext,
            'localization_context' => $localizationContext,
            'header_cta_text' => '????ng k??',
            'hero_title' => 'N???n t???ng CRM v?? v???n h??nh doanh nghi???p cho SME',
            'hero_subtitle' => 'Chu???n h??a b??n h??ng, d???ch v???, t??i ch??nh v?? c???ng t??c tr??n m???t h??? th???ng th???ng nh???t.',
            'hero_image' => '',
            'primary_color' => '#1f3a5f',
            'secondary_color' => '#4b5563',
            'cta_color' => '#2563eb',
            'features' => $this->buildFeatureItems(),
            'faqs' => $this->buildFaqItems(),
            'testimonials' => $this->buildTestimonialItems(),
            'product_marketing' => $this->buildProductMarketingData(),
            'landing_content' => [],
            'public_plans' => [],
            'footer_text' => '?? ' . date('Y') . ' ' . $brandName,
            'page' => $page,
            'sections' => [],
            'menus' => [],
            'blog_posts' => [],
            'custom_css' => '',
            'custom_js' => '',
        ];
    }

    private function renderLandingFallbackResponse($page)
    {
        $brandName = trim((string) get_option('companyname'));
        if ($brandName === '') {
            $brandName = 'KT SaaS Platform';
        }

        $title = $brandName . ' - SaaS CRM';
        $logo = trim((string) get_option('company_logo'));
        $favicon = trim((string) get_option('favicon'));
        $lang = trim((string) get_option('active_language'));
        if ($lang === '') {
            $lang = 'vi';
        }

        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
        if ($favicon !== '') {
            echo '<link rel="icon" href="' . htmlspecialchars($favicon, ENT_QUOTES, 'UTF-8') . '">';
        }
        echo '<style>body{font-family:Arial,sans-serif;margin:0;background:#f8fafc;color:#0f172a}.wrap{max-width:960px;margin:0 auto;padding:48px 24px}.brand{display:flex;align-items:center;gap:12px;margin-bottom:24px}.brand img{height:40px;width:auto}.hero{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:32px;box-shadow:0 10px 30px rgba(15,23,42,.06)}a.btn{display:inline-block;margin-right:12px;padding:12px 18px;border-radius:10px;background:#1f3a5f;color:#fff;text-decoration:none}.muted{color:#64748b}.pill{display:inline-block;background:#e2e8f0;border-radius:999px;padding:6px 12px;font-size:12px;margin-bottom:16px}</style></head><body>';
        echo '<div class="wrap">';
        echo '<div class="brand">';
        if ($logo !== '') {
            echo '<img src="' . htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') . '">';
        }
        echo '<strong>' . htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') . '</strong></div>';
        echo '<div class="hero">';
        echo '<div class="pill">Tenant isolated landing fallback</div>';
        echo '<h1>' . htmlspecialchars($brandName . ' - SaaS CRM', ENT_QUOTES, 'UTF-8') . '</h1>';
        echo '<p class="muted">Landing fallback rendered because the main template path failed in runtime. Branding is taken directly from the current option scope.</p>';
        echo '<p><a class="btn" href="' . htmlspecialchars(site_url('login'), ENT_QUOTES, 'UTF-8') . '">??ng nh?p</a><a class="btn" href="' . htmlspecialchars(site_url('signup'), ENT_QUOTES, 'UTF-8') . '" style="background:#2563eb">??t h?ng ngay</a></p>';
        echo '</div></div></body></html>';
        exit;
    }

    private function buildLandingContentFromCms()
    {
        $getSection = function ($key) {
            return $this->Kt_landing_model->get_section_by_key('home', $key) ?: [];
        };
        $getItems = function ($sectionKey, $itemKey = null) use ($getSection) {
            $section = $getSection($sectionKey);
            $sectionId = (int) ($section['id'] ?? 0);
            if ($sectionId <= 0) {
                return [];
            }
            return $this->Kt_landing_model->get_section_items($sectionId, $itemKey, true);
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

    private function buildProductMarketingData()
    {
        $raw = $this->Kt_landing_model->get_setting('kt_landing_product_marketing_json', []);
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }

        if (is_array($raw) && !empty($raw)) {
            return $raw;
        }

        return [
            'showcases' => [
                [
                    'slug' => 'crm',
                    'title' => 'CRM Showcase',
                    'headline' => 'Quan ly toan bo vong doi khach hang trong mot man hinh',
                    'description' => 'Doi sales theo doi lead, co hoi, bao gia va lich su cham soc theo pipeline truc quan.',
                    'bullets' => ['Pipeline theo trang thai Lead -> Won', 'Nhac viec tu dong theo SLA', 'Bao cao hieu suat theo nhan su/nhom'],
                ],
                [
                    'slug' => 'inventory',
                    'title' => 'Inventory Showcase',
                    'headline' => 'Kiem soat ton kho theo thoi gian thuc',
                    'description' => 'Quan ly nhap xuat ton, canh bao muc ton toi thieu va lien ket truc tiep don hang.',
                    'bullets' => ['Canh bao thieu hang theo nguong', 'Theo doi vong quay ton kho', 'Dong bo voi ban hang va hoa don'],
                ],
                [
                    'slug' => 'invoice',
                    'title' => 'Invoice Showcase',
                    'headline' => 'Phat hanh hoa don va ky so ngay trong luong cong viec',
                    'description' => 'Tu bao gia, hop dong den hoa don dien tu duoc xu ly lien tuc.',
                    'bullets' => ['Tich hop KT MatBao Invoice', 'Theo doi trang thai phat hanh', 'Luu tru chung tu tap trung'],
                ],
                [
                    'slug' => 'sepay',
                    'title' => 'SePay Showcase',
                    'headline' => 'Doi soat thanh toan tu dong, giam lech cong no',
                    'description' => 'KT SePay dong bo giao dich de doi soat nhanh voi hoa don.',
                    'bullets' => ['Sync giao dich tu dong', 'Mapping thanh toan theo hoa don', 'Bao cao dong tien chinh xac hon'],
                ],
            ],
            'journey' => [
                ['step' => '01', 'title' => 'Thu hut lead', 'text' => 'Lead vao tu website/campaign va do ve CRM.'],
                ['step' => '02', 'title' => 'Chot bao gia', 'text' => 'Doi sales quan ly co hoi, gui bao gia va theo doi phan hoi.'],
                ['step' => '03', 'title' => 'Ky va phat hanh', 'text' => 'Hop dong/hoa don di cung quy trinh ky so tap trung.'],
                ['step' => '04', 'title' => 'Thu tien va cham soc', 'text' => 'SePay doi soat tu dong, du lieu quay lai CRM de upsell.'],
            ],
            'why_choose' => [
                ['title' => 'Mot nen tang xuyen suot', 'text' => 'CRM, kho, hoa don, thanh toan chay tren cung du lieu nghiep vu.'],
                ['title' => 'Trien khai nhanh cho SME', 'text' => 'Bat dau tu trial, bat them module va add-on khi mo rong.'],
                ['title' => 'Toi uu van hanh dai han', 'text' => 'Giam he thong roi rac, tang kha nang do luong va ra quyet dinh.'],
            ],
        ];
    }

    private function buildFeatureItems()
    {
        $json = trim((string) get_option('kt_landing_features_json'));
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded) && !empty($decoded)) {
                return $decoded;
            }
        }

        return [
            ['title' => 'CRM bán hàng', 'description' => 'Quản lý lead, cơ hội và pipeline theo quy trình rõ ràng.'],
            ['title' => 'Vận hành dự án', 'description' => 'Theo dõi tiến độ, phân công công việc và chi phí theo thời gian thực.'],
            ['title' => 'Tài chính hợp nhất', 'description' => 'Hóa đơn, thanh toán và đối soát đồng bộ trên một nền tảng.'],
            ['title' => 'Mở rộng theo module', 'description' => 'Bật/tắt ứng dụng theo gói và nhu cầu vận hành thực tế.'],
        ];
    }

    private function buildFaqItems()
    {
        $json = trim((string) get_option('kt_landing_faq_json'));
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded) && !empty($decoded)) {
                return $decoded;
            }
        }

        return [
            ['q' => 'Mất bao lâu để triển khai?', 'a' => 'Thông thường 1-3 ngày cho cấu hình nền tảng và nhập dữ liệu ban đầu.'],
            ['q' => 'Có thể nâng cấp gói sau này không?', 'a' => 'Có. Hệ thống hỗ trợ thay đổi gói và giữ toàn bộ dữ liệu hiện tại.'],
            ['q' => 'Có hỗ trợ tích hợp thanh toán không?', 'a' => 'Có. Nền tảng hỗ trợ tích hợp SePay và các workflow hóa đơn liên quan.'],
        ];
    }

    private function buildTestimonialItems()
    {
        $json = trim((string) get_option('kt_landing_testimonials_json'));
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded) && !empty($decoded)) {
                return $decoded;
            }
        }

        return [
            ['name' => 'Giám đốc vận hành', 'company' => 'Doanh nghiệp thương mại', 'quote' => 'Chúng tôi chuẩn hóa quy trình bán hàng và báo cáo vận hành nhanh hơn đáng kể.'],
            ['name' => 'Trưởng phòng kinh doanh', 'company' => 'Công ty dịch vụ', 'quote' => 'Pipeline rõ ràng, đội ngũ phối hợp tốt hơn và giảm sai lệch dữ liệu.'],
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
            $this->Kt_saas_model->log_activity('landing.signup_blocked_honeypot', 'warning', [
                'ip' => (string) $this->input->ip_address(),
            ], null);
            return ['success' => false, 'message' => 'Yeu cau khong hop le.'];
        }

        if ($signupTs > 0 && (time() - $signupTs) < 3) {
            $this->Kt_saas_model->log_activity('landing.signup_blocked_too_fast', 'warning', [
                'ip' => (string) $this->input->ip_address(),
                'signup_ts' => $signupTs,
            ], null);
            return ['success' => false, 'message' => 'Thao tac qua nhanh. Vui long thu lai sau vai giay.'];
        }

        if ($companyName === '' || $ownerName === '' || $ownerEmail === '' || $planId <= 0) {
            return ['success' => false, 'message' => 'Vui lòng nhập đủ tên công ty, người liên hệ, email và gói dịch vụ.'];
        }

        if (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Email liên hệ không hợp lệ.'];
        }

        if ($desiredSubdomain !== '' && !preg_match('/^[a-z0-9-]+$/', $desiredSubdomain)) {
            return ['success' => false, 'message' => 'Subdomain chi duoc gom a-z, 0-9 va dau gach ngang.'];
        }

        if (!$this->canSubmitSignupNow($ownerEmail)) {
            $this->Kt_saas_model->log_activity('landing.signup_blocked_rate_limit', 'warning', [
                'ip' => (string) $this->input->ip_address(),
                'owner_email' => $ownerEmail,
            ], null);
            return ['success' => false, 'message' => 'Ban vua gui dang ky. Vui long doi 60 giay roi thu lai.'];
        }

        $plan = $this->Kt_saas_model->get_plan($planId);
        if (!$plan || (int) ($plan['is_active'] ?? 0) !== 1 || (int) ($plan['is_public'] ?? 0) !== 1) {
            return ['success' => false, 'message' => 'Gói dịch vụ không còn khả dụng công khai.'];
        }

        $tenant = $this->findReusableDraftTenant($ownerEmail, $planId);
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

        // Phase 2 scope: giữ tenant ở trạng thái draft và chưa provisioning tự động.
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

    private function buildSignupAddonCatalog()
    {
        return [
            ['code' => 'matbao_invoice', 'name' => 'KT MatBao Invoice', 'desc' => 'Hoa don dien tu va quy trinh ky so lien thong.'],
            ['code' => 'hsm_signing', 'name' => 'Chu ky so tap trung / HSM', 'desc' => 'Ky so tap trung cho hoa don va hop dong.'],
            ['code' => 'sepay', 'name' => 'KT SePay', 'desc' => 'Doi soat thanh toan va nhan tien nhanh.'],
            ['code' => 'domain', 'name' => 'Domain', 'desc' => 'Dang ky hoac transfer ten mien doanh nghiep.'],
            ['code' => 'hosting', 'name' => 'Hosting', 'desc' => 'Tai nguyen hosting bo sung cho website cong ty.'],
            ['code' => 'website', 'name' => 'Website Setup', 'desc' => 'Goi setup website nhanh theo mau doanh nghiep.'],
        ];
    }

    private function buildPreferredCheckoutUrl(array $invoice, array $tenant)
    {
        // Phase 3.1: ưu tiên SePay nếu module đang active và tạo được request.
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

    private function isLandingEnabled()
    {
        $enabled = (string) $this->Kt_landing_model->get_setting('landing_enabled', '1');
        return $enabled !== '0';
    }

    private function indexPlanOverrides(array $rows)
    {
        $out = [];
        foreach ($rows as $row) {
            $out[(int) ($row['plan_id'] ?? 0)] = $row;
        }
        return $out;
    }

    private function applyPlanOverrides(array $plans, array $overrides)
    {
        $result = [];
        foreach ($plans as $plan) {
            $planId = (int) ($plan['id'] ?? 0);
            $ov = $overrides[$planId] ?? null;
            if ($ov && (int) ($ov['is_visible'] ?? 1) !== 1) {
                continue;
            }
            $plan['landing_marketing_title'] = '';
            $plan['landing_marketing_subtitle'] = '';
            $plan['landing_marketing_description'] = '';
            $plan['landing_badge_text'] = '';
            $plan['landing_cta_text'] = '';
            $plan['landing_cta_url'] = '';
            $plan['landing_featured'] = 0;
            $plan['landing_sort_order'] = 0;
            if ($ov) {
                if (!empty($ov['marketing_title'])) {
                    $plan['landing_marketing_title'] = (string) $ov['marketing_title'];
                }
                $plan['landing_marketing_subtitle'] = (string) ($ov['marketing_subtitle'] ?? '');
                if (!empty($ov['marketing_description'])) {
                    $plan['landing_marketing_description'] = (string) $ov['marketing_description'];
                }
                $plan['landing_badge_text'] = (string) ($ov['badge_text'] ?? '');
                $plan['landing_cta_text'] = (string) ($ov['cta_text'] ?? '');
                $plan['landing_cta_url'] = (string) ($ov['cta_url'] ?? '');
                $plan['landing_featured'] = (int) ($ov['is_featured'] ?? 0);
                $plan['landing_sort_order'] = (int) ($ov['sort_order'] ?? 0);
            }
            $result[] = $plan;
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
}
