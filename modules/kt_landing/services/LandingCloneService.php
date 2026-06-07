<?php

defined('BASEPATH') or exit('No direct script access allowed');

class LandingCloneService
{
    protected $CI;
    protected $globalBlockService;

    public function __construct()
    {
        $this->CI = &get_instance();
        if (!isset($this->CI->Kt_landing_model)) {
            $this->CI->load->model('kt_landing/Kt_landing_model');
        }
        if (!isset($this->CI->Kt_saas_model)) {
            $this->CI->load->model('kt_saas/Kt_saas_model');
        }
        if (!function_exists('url_title')) {
            $this->CI->load->helper('url');
        }

        require_once __DIR__ . '/LandingGlobalBlockService.php';
        $this->globalBlockService = new LandingGlobalBlockService();
    }

    public function getWizardData()
    {
        $registry = $this->CI->Kt_landing_model->get_clone_registry();
        return [
            'source_templates' => $this->getSourceTemplates(),
            'brand_presets' => $this->getBrandPresets(),
            'industry_presets' => $this->getIndustryPresets(),
            'clone_registry' => $registry,
            'clone_rows' => $this->buildCloneRows($registry),
        ];
    }

    public function buildPreview(array $input)
    {
        $normalized = $this->normalizeInput($input);
        $validation = $this->validateRequest($normalized);
        $plan = $this->buildPlan($normalized);

        return [
            'success' => empty($validation['blockers']),
            'input' => $normalized,
            'validation' => $validation,
            'plan' => $plan,
            'registry' => $this->CI->Kt_landing_model->get_clone_registry(),
        ];
    }

    public function cloneLanding(array $input)
    {
        $normalized = $this->normalizeInput($input);
        $validation = $this->validateRequest($normalized);
        $plan = $this->buildPlan($normalized);

        $this->logCloneEvent('landing.clone.started', 'info', [
            'source_template_code' => $normalized['source_template_code'],
            'target_template_code' => $normalized['target_template_code'],
            'target_name' => $normalized['target_name'],
            'target_slug' => $normalized['target_slug'],
        ]);

        if (!empty($validation['blockers'])) {
            $this->logCloneEvent('landing.clone.failed', 'warning', [
                'source_template_code' => $normalized['source_template_code'],
                'target_template_code' => $normalized['target_template_code'],
                'reason' => 'validation_blocked',
                'issues' => $validation['blockers'],
            ]);
            return [
                'success' => false,
                'message' => 'Clone validation failed',
                'validation' => $validation,
                'plan' => $plan,
            ];
        }

        $db = $this->CI->Kt_landing_model->get_landlord_db();
        $db->trans_begin();

        try {
            $result = $this->runClone($normalized, $plan);
            if (empty($result['success'])) {
                $db->trans_rollback();
                $this->logCloneEvent('landing.clone.failed', 'warning', [
                    'source_template_code' => $normalized['source_template_code'],
                    'target_template_code' => $normalized['target_template_code'],
                    'reason' => 'clone_failed',
                    'message' => (string) ($result['message'] ?? 'Clone failed'),
                ]);
                return $result + [
                    'validation' => $validation,
                    'plan' => $plan,
                ];
            }

            if ($db->trans_status() === false) {
                $db->trans_rollback();
                $this->logCloneEvent('landing.clone.failed', 'warning', [
                    'source_template_code' => $normalized['source_template_code'],
                    'target_template_code' => $normalized['target_template_code'],
                    'reason' => 'transaction_failed',
                ]);
                return [
                    'success' => false,
                    'message' => 'Unable to complete clone transaction',
                    'validation' => $validation,
                    'plan' => $plan,
                ];
            }

            $db->trans_commit();
            $this->refreshReferencedGlobalBlockUsage((array) ($plan['global_blocks'] ?? []));
            $this->logCloneEvent('landing.clone.completed', 'success', [
                'source_template_code' => $normalized['source_template_code'],
                'target_template_code' => $normalized['target_template_code'],
                'pages_cloned' => (int) ($result['pages_cloned'] ?? 0),
                'sections_cloned' => (int) ($result['sections_cloned'] ?? 0),
                'menus_cloned' => (int) ($result['menus_cloned'] ?? 0),
            ]);

            return $result + [
                'validation' => $validation,
                'plan' => $plan,
            ];
        } catch (Throwable $e) {
            $db->trans_rollback();
            $this->logCloneEvent('landing.clone.failed', 'error', [
                'source_template_code' => $normalized['source_template_code'],
                'target_template_code' => $normalized['target_template_code'],
                'reason' => 'exception',
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => 'Clone crashed: ' . $e->getMessage(),
                'validation' => $validation,
                'plan' => $plan,
            ];
        }
    }

    public function getBrandPresets()
    {
        return [
            'crm_khach_tot' => [
                'label' => 'CRM Khách Tốt',
                'description' => 'Giữ thương hiệu Khách Tốt làm lớp hiển thị chính.',
            ],
            'neutral' => [
                'label' => 'Neutral',
                'description' => 'Dùng tên target làm brand chính.',
            ],
            'partner' => [
                'label' => 'Partner Branded',
                'description' => 'Dùng nhãn partner/white-label cho clone draft.',
            ],
        ];
    }

    public function getIndustryPresets()
    {
        return [
            'crm_hvac' => [
                'label' => 'CRM HVAC',
                'hero_title' => 'CRM cho doanh nghiệp HVAC',
                'hero_subtitle' => 'Quản lý khách hàng, bảo trì, báo giá và thanh toán trong một nền tảng.',
                'header_cta_text' => 'Đăng ký CRM HVAC',
                'brand_name' => 'CRM HVAC',
                'default_meta_title' => 'CRM HVAC cho doanh nghiệp',
                'default_meta_description' => 'Nền tảng CRM cho doanh nghiệp HVAC với lead, báo giá, bảo trì và thanh toán.',
                'footer_text' => 'CRM HVAC cho doanh nghiệp dịch vụ kỹ thuật.',
                'product_marketing' => $this->buildProductMarketingPreset('HVAC'),
                'features' => [
                    ['title' => 'Quản lý khách hàng HVAC', 'description' => 'Theo dõi lead, công trình và lịch bảo trì.'],
                    ['title' => 'Báo giá và hợp đồng', 'description' => 'Chuyển đổi báo giá thành hợp đồng và hóa đơn.'],
                    ['title' => 'Thanh toán & vận hành', 'description' => 'Đồng bộ thanh toán, nhắc việc và báo cáo.'],
                ],
                'faq' => [
                    ['question' => 'CRM HVAC dùng cho ai?', 'answer' => 'Cho doanh nghiệp lắp đặt, bảo trì và dịch vụ kỹ thuật HVAC.'],
                    ['question' => 'Có thể mở rộng thêm ứng dụng không?', 'answer' => 'Có. Clone draft vẫn dùng marketplace và global blocks.'],
                ],
                'testimonials' => [
                    ['name' => 'Giám đốc vận hành', 'company' => 'HVAC Service', 'quote' => 'Đội kỹ thuật, báo giá và thanh toán đi cùng một quy trình.'],
                ],
                'proof_metrics' => [
                    ['label' => 'Công trình đang vận hành', 'value' => '120+'],
                    ['label' => 'Bảo trì theo lịch', 'value' => '90%'],
                    ['label' => 'Tỷ lệ phản hồi', 'value' => '2h'],
                ],
            ],
            'crm_distributor' => [
                'label' => 'CRM Nhà phân phối',
                'hero_title' => 'CRM cho nhà phân phối',
                'hero_subtitle' => 'Quản lý đại lý, đơn hàng, kho và công nợ trên một hệ thống.',
                'header_cta_text' => 'Đăng ký CRM Nhà phân phối',
                'brand_name' => 'CRM Nhà phân phối',
                'default_meta_title' => 'CRM cho nhà phân phối',
                'default_meta_description' => 'Quản lý đơn hàng, đại lý, kho và công nợ cho doanh nghiệp phân phối.',
                'footer_text' => 'CRM cho nhà phân phối và vận hành kênh bán hàng.',
                'product_marketing' => $this->buildProductMarketingPreset('Distributor'),
                'features' => [
                    ['title' => 'Kênh đại lý', 'description' => 'Theo dõi đại lý, hạn mức và đơn hàng.'],
                    ['title' => 'Kho và điều phối', 'description' => 'Gắn đơn hàng với tồn kho thực tế.'],
                    ['title' => 'Công nợ và thanh toán', 'description' => 'Đối soát nhanh theo đơn và giao dịch.'],
                ],
                'faq' => [
                    ['question' => 'Có quản lý đại lý không?', 'answer' => 'Có, clone draft có thể mở rộng cho kênh phân phối.'],
                    ['question' => 'Có đồng bộ kho không?', 'answer' => 'Có, kho và đơn hàng có thể liên kết theo preset.'],
                ],
                'testimonials' => [
                    ['name' => 'Giám đốc kinh doanh', 'company' => 'Distribution Co.', 'quote' => 'Đơn hàng và công nợ rõ hơn ngay từ tuần đầu.'],
                ],
                'proof_metrics' => [
                    ['label' => 'Đại lý đang phục vụ', 'value' => '480+'],
                    ['label' => 'Đơn hàng mỗi ngày', 'value' => '1.2K'],
                    ['label' => 'Kho quản lý', 'value' => '24'],
                ],
            ],
            'crm_service' => [
                'label' => 'CRM Dịch vụ',
                'hero_title' => 'CRM cho doanh nghiệp dịch vụ',
                'hero_subtitle' => 'Quản lý khách hàng, lịch hẹn, hợp đồng và thanh toán trong một nền tảng.',
                'header_cta_text' => 'Đăng ký CRM Dịch vụ',
                'brand_name' => 'CRM Dịch vụ',
                'default_meta_title' => 'CRM cho doanh nghiệp dịch vụ',
                'default_meta_description' => 'Theo dõi khách hàng, lịch hẹn và doanh thu dịch vụ trên một hệ thống.',
                'footer_text' => 'CRM cho doanh nghiệp dịch vụ và chăm sóc khách hàng.',
                'product_marketing' => $this->buildProductMarketingPreset('Service'),
                'features' => [
                    ['title' => 'Lịch hẹn và điều phối', 'description' => 'Sắp lịch và giao việc cho đội ngũ.'],
                    ['title' => 'Chăm sóc khách hàng', 'description' => 'Theo dõi lịch sử và phản hồi sau bán.'],
                    ['title' => 'Doanh thu dịch vụ', 'description' => 'Kết nối báo giá, hợp đồng và thanh toán.'],
                ],
                'faq' => [
                    ['question' => 'Có phù hợp cho đội ngũ dịch vụ không?', 'answer' => 'Có, đặc biệt khi cần lịch hẹn và theo dõi workflow.'],
                    ['question' => 'Có thể thay đổi CTA không?', 'answer' => 'Có, CTA là override presentation của landing draft.'],
                ],
                'testimonials' => [
                    ['name' => 'Quản lý dịch vụ', 'company' => 'Service Co.', 'quote' => 'Lịch hẹn và thanh toán gọn hơn hẳn.'],
                ],
                'proof_metrics' => [
                    ['label' => 'Lịch hẹn mỗi ngày', 'value' => '86'],
                    ['label' => 'Khách hàng đang chăm sóc', 'value' => '6.4K'],
                    ['label' => 'Đội ngũ', 'value' => '34'],
                ],
            ],
            'crm_einvoice' => [
                'label' => 'CRM Hóa đơn điện tử',
                'hero_title' => 'CRM cho hóa đơn điện tử',
                'hero_subtitle' => 'Quản lý bán hàng, phát hành hóa đơn và thanh toán trong một luồng.',
                'header_cta_text' => 'Đăng ký CRM Hóa đơn điện tử',
                'brand_name' => 'CRM Hóa đơn điện tử',
                'default_meta_title' => 'CRM cho hóa đơn điện tử',
                'default_meta_description' => 'Bán hàng, hóa đơn điện tử và thanh toán cùng một quy trình.',
                'footer_text' => 'CRM cho doanh nghiệp cần hóa đơn điện tử.',
                'product_marketing' => $this->buildProductMarketingPreset('eInvoice'),
                'features' => [
                    ['title' => 'Phát hành hóa đơn', 'description' => 'Kết nối bán hàng và phát hành hóa đơn.'],
                    ['title' => 'Ký số và lưu trữ', 'description' => 'Giữ luồng chứng từ tập trung.'],
                    ['title' => 'Thanh toán & đối soát', 'description' => 'Gắn hóa đơn với giao dịch thực tế.'],
                ],
                'faq' => [
                    ['question' => 'Có dùng cho hóa đơn điện tử không?', 'answer' => 'Có, clone draft giữ luồng tích hợp hóa đơn.'],
                    ['question' => 'Có cần sửa billing không?', 'answer' => 'Không. Giá vẫn đi từ CRM plan source of truth.'],
                ],
                'testimonials' => [
                    ['name' => 'Kế toán trưởng', 'company' => 'Invoice Co.', 'quote' => 'Phát hành hóa đơn và đối soát nhanh hơn.'],
                ],
                'proof_metrics' => [
                    ['label' => 'Hóa đơn xử lý', 'value' => '120K+'],
                    ['label' => 'Tỷ lệ thành công', 'value' => '99.8%'],
                    ['label' => 'Thời gian xử lý', 'value' => '< 1m'],
                ],
            ],
        ];
    }

    private function buildCloneRows(array $registry)
    {
        $rows = [];
        foreach ($registry as $templateCode => $variant) {
            if (!is_array($variant)) {
                continue;
            }
            $rows[] = [
                'template_code' => (string) $templateCode,
                'source_template_code' => (string) ($variant['source_template_code'] ?? ''),
                'base_template_code' => (string) ($variant['base_template_code'] ?? ''),
                'target_name' => (string) ($variant['target_name'] ?? ''),
                'target_slug' => (string) ($variant['target_slug'] ?? ''),
                'industry_preset' => (string) ($variant['industry_preset'] ?? ''),
                'brand_preset' => (string) ($variant['brand_preset'] ?? ''),
                'status' => (string) ($variant['status'] ?? 'draft'),
                'updated_at' => (string) ($variant['updated_at'] ?? ''),
            ];
        }
        usort($rows, static function ($a, $b) {
            return strcmp((string) ($a['updated_at'] ?? ''), (string) ($b['updated_at'] ?? ''));
        });
        return array_reverse($rows);
    }

    private function normalizeInput(array $input)
    {
        return [
            'source_template_code' => trim((string) ($input['source_template_code'] ?? '')),
            'target_name' => trim((string) ($input['target_name'] ?? '')),
            'target_slug' => $this->normalizeSlug((string) ($input['target_slug'] ?? '')),
            'brand_preset' => trim((string) ($input['brand_preset'] ?? 'neutral')),
            'industry_preset' => trim((string) ($input['industry_preset'] ?? 'crm_hvac')),
        ];
    }

    private function validateRequest(array $input)
    {
        $blockers = [];
        $warnings = [];

        if ($input['source_template_code'] === '') {
            $blockers[] = 'Missing source template';
        }
        if ($input['target_name'] === '') {
            $blockers[] = 'Missing target name';
        }
        if ($input['target_slug'] === '') {
            $blockers[] = 'Missing target slug';
        }
        if (!$this->isKnownTemplate($input['source_template_code'])) {
            $blockers[] = 'Unknown source template';
        }
        if (!$this->isKnownBrandPreset($input['brand_preset'])) {
            $warnings[] = 'Unknown brand preset, falling back to neutral';
        }
        if (!$this->isKnownIndustryPreset($input['industry_preset'])) {
            $blockers[] = 'Unknown industry preset';
        }

        $existingPages = $this->CI->Kt_landing_model->get_pages();
        foreach ($existingPages as $page) {
            if ((string) ($page['slug'] ?? '') === $input['target_slug']) {
                $blockers[] = 'Duplicate target slug';
                break;
            }
        }

        $sourcePages = $this->getSourcePages($input['source_template_code']);
        if (empty($sourcePages)) {
            $blockers[] = 'Source pages not found';
        }
        $sourceSections = $this->getSourceSections($input['source_template_code']);
        if (empty($sourceSections)) {
            $warnings[] = 'Source sections are empty';
        }

        $broken = $this->detectBrokenBlockReferences($sourcePages, $sourceSections);
        if (!empty($broken)) {
            $blockers[] = 'Broken references: ' . implode(', ', array_slice($broken, 0, 5));
        }

        $missing = $this->detectMissingMediaAssets($sourcePages, $sourceSections);
        if (!empty($missing)) {
            $warnings[] = 'Missing media assets: ' . implode(', ', array_slice($missing, 0, 5));
        }

        return [
            'blockers' => array_values(array_unique($blockers)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function buildPlan(array $input)
    {
        $brand = $this->getBrandPreset($input['brand_preset']);
        $industry = $this->getIndustryPreset($input['industry_preset']);
        $sourcePages = $this->getSourcePages($input['source_template_code']);
        $sourceSections = $this->getSourceSections($input['source_template_code']);
        $sourceMenus = $this->getSourceMenus($input['source_template_code']);
        $settings = $this->buildRegistrySettings($input, $brand, $industry);

        return [
            'source_pages' => count($sourcePages),
            'source_sections' => count($sourceSections),
            'source_menus' => count($sourceMenus),
            'brand_preset' => $brand,
            'industry_preset' => $industry,
            'settings' => $settings,
            'global_blocks' => $this->buildReferencedBlocksSummary($sourceSections),
        ];
    }

    private function runClone(array $input, array $plan)
    {
        $registry = $this->CI->Kt_landing_model->get_clone_registry();
        $sourceTemplate = $input['source_template_code'];
        $targetCode = $input['target_slug'];
        $targetName = $input['target_name'];
        $brand = $this->getBrandPreset($input['brand_preset']);
        $industry = $this->getIndustryPreset($input['industry_preset']);
        $settings = $plan['settings'] ?? [];

        $sourcePages = $this->getSourcePages($sourceTemplate);
        $sourceSections = $this->getSourceSections($sourceTemplate);
        $sourceMenus = $this->getSourceMenus($sourceTemplate);
        $existingSlugs = array_map(static function ($row) {
            return (string) ($row['slug'] ?? '');
        }, $this->CI->Kt_landing_model->get_pages());

        $replacements = $this->buildTextReplacements($sourceTemplate, $targetName, $input['target_slug'], $brand, $industry);

        $pageSlugs = [];
        $pagesCloned = 0;
        foreach ($sourcePages as $index => $page) {
            $clone = $this->clonePageRow($page, $targetCode, $targetName, $input['target_slug'], $replacements, $existingSlugs, $index === 0);
            if (!$this->CI->Kt_landing_model->save_page($clone)) {
                return ['success' => false, 'message' => 'Unable to clone page: ' . (string) ($page['slug'] ?? $page['title'] ?? 'page')];
            }
            $newPage = $this->findPageBySlug($clone['slug']);
            if ($newPage) {
                $pageSlugs[] = (string) ($newPage['slug'] ?? '');
            }
            $pagesCloned++;
        }

        $sectionsCloned = 0;
        $sectionMap = [];
        foreach ($sourceSections as $section) {
            $clone = $this->cloneSectionRow($section, $targetCode, $replacements);
            if (!$this->CI->Kt_landing_model->save_section($clone)) {
                return ['success' => false, 'message' => 'Unable to clone section: ' . (string) ($section['section_key'] ?? 'section')];
            }
            $newSection = $this->findSectionByKey($targetCode, (string) ($section['section_key'] ?? ''));
            if (!$newSection) {
                return ['success' => false, 'message' => 'Unable to resolve cloned section id'];
            }
            $sectionMap[(string) ($section['section_key'] ?? '')] = $newSection;
            $sectionsCloned++;

            $items = $this->CI->Kt_landing_model->get_section_items((int) ($section['id'] ?? 0), null, false);
            foreach ($items as $item) {
                $cloneItem = $this->cloneSectionItemRow($item, (int) $newSection['id'], $replacements);
                if (!$this->CI->Kt_landing_model->save_section_item($cloneItem)) {
                    return ['success' => false, 'message' => 'Unable to clone section item: ' . (string) ($item['item_key'] ?? 'item')];
                }
            }
        }

        $menusCloned = 0;
        foreach ($sourceMenus as $menu) {
            $clone = $this->cloneMenuRow($menu, $targetCode, $replacements);
            if (!$this->CI->Kt_landing_model->save_menu($clone)) {
                return ['success' => false, 'message' => 'Unable to clone menu: ' . (string) ($menu['label'] ?? 'menu')];
            }
            $menusCloned++;
        }

        $registry[$targetCode] = [
            'template_code' => $targetCode,
            'source_template_code' => $sourceTemplate,
            'base_template_code' => $sourceTemplate,
            'target_name' => $targetName,
            'target_slug' => $targetCode,
            'brand_preset' => (string) $input['brand_preset'],
            'industry_preset' => (string) $input['industry_preset'],
            'status' => 'draft',
            'settings' => $settings,
            'pricing_overrides' => $this->buildPricingOverrides(),
            'page_slugs' => array_values(array_unique(array_filter($pageSlugs))),
            'section_keys' => array_values(array_unique(array_filter(array_keys($sectionMap)))),
            'menu_groups' => [$targetCode],
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ];
        $this->CI->Kt_landing_model->save_clone_registry($registry);

        return [
            'success' => true,
            'template_code' => $targetCode,
            'source_template_code' => $sourceTemplate,
            'target_name' => $targetName,
            'target_slug' => $targetCode,
            'pages_cloned' => $pagesCloned,
            'sections_cloned' => $sectionsCloned,
            'menus_cloned' => $menusCloned,
            'registry_entry' => $registry[$targetCode],
        ];
    }

    private function getSourceTemplates()
    {
        $templates = $this->CI->Kt_landing_model->get_registered_template_codes();
        sort($templates);
        return array_values($templates);
    }

    private function getBrandPreset($key)
    {
        $presets = $this->getBrandPresets();
        return $presets[$key] ?? $presets['neutral'];
    }

    private function getIndustryPreset($key)
    {
        $presets = $this->getIndustryPresets();
        return $presets[$key] ?? $presets['crm_hvac'];
    }

    private function isKnownBrandPreset($key)
    {
        return array_key_exists($key, $this->getBrandPresets());
    }

    private function isKnownIndustryPreset($key)
    {
        return array_key_exists($key, $this->getIndustryPresets());
    }

    private function isKnownTemplate($templateCode)
    {
        $templateCode = trim((string) $templateCode);
        if ($templateCode === '') {
            return false;
        }
        return in_array($templateCode, $this->CI->Kt_landing_model->get_registered_template_codes(), true);
    }

    private function getSourcePages($templateCode)
    {
        $rows = $this->CI->Kt_landing_model->get_pages_by_template($templateCode);
        if (empty($rows)) {
            $rows = $this->CI->Kt_landing_model->get_pages();
        }
        $landingSlugs = [
            'home',
            'pricing',
            'features',
            'solutions',
            'blog',
            'contact',
            'faq',
            'marketplace',
            'case-studies',
        ];
        $filtered = [];
        foreach ($rows as $row) {
            $slug = trim((string) ($row['slug'] ?? ''));
            $template = trim((string) ($row['template_code'] ?? ''));
            if ($template === $templateCode || in_array($slug, $landingSlugs, true)) {
                $filtered[] = $row;
            }
        }

        if (empty($filtered)) {
            return array_values($rows);
        }

        return array_values($filtered);
    }

    private function getSourceSections($templateCode)
    {
        $rows = $this->CI->Kt_landing_model->get_sections_by_page_key($templateCode);
        if (empty($rows)) {
            $rows = $this->CI->Kt_landing_model->get_sections_by_page_key('home');
        }
        return array_values($rows);
    }

    private function getSourceMenus($templateCode)
    {
        $rows = $this->CI->Kt_landing_model->get_menus_for_template($templateCode);
        if (empty($rows)) {
            $rows = $this->CI->Kt_landing_model->get_menus();
        }
        return array_values($rows);
    }

    private function findPageBySlug($slug)
    {
        foreach ($this->CI->Kt_landing_model->get_pages() as $page) {
            if ((string) ($page['slug'] ?? '') === (string) $slug) {
                return $page;
            }
        }
        return null;
    }

    private function findSectionByKey($pageKey, $sectionKey)
    {
        return $this->CI->Kt_landing_model->get_section_by_key($pageKey, $sectionKey);
    }

    private function clonePageRow(array $page, $targetCode, $targetName, $targetSlug, array $replacements, array &$existingSlugs, $isFirst)
    {
        $slug = trim((string) ($page['slug'] ?? ''));
        $slug = $slug === '' ? url_title((string) ($page['title'] ?? $targetName), '-', true) : $slug;
        if ($isFirst || in_array($slug, ['home', 'index', $targetCode], true)) {
            $slug = $targetSlug;
        } else {
            $slug = $targetSlug . '-' . $slug;
        }
        $slug = $this->uniqueSlug($slug, $existingSlugs);
        $existingSlugs[] = $slug;

        $pageTitle = $isFirst ? $targetName : $this->replaceTextRecursive((string) ($page['title'] ?? $targetName), $replacements);
        $seoTitle = $isFirst ? ($targetName . ' - CRM') : $this->replaceTextRecursive((string) ($page['seo_title'] ?? $targetName), $replacements);
        $seoDescription = $isFirst
            ? 'Landing draft cho ' . $targetName . '.'
            : $this->replaceTextRecursive((string) ($page['seo_description'] ?? ''), $replacements);

        return [
            'title' => $pageTitle,
            'slug' => $slug,
            'template_code' => $targetCode,
            'seo_title' => $seoTitle,
            'seo_description' => $seoDescription,
            'status' => 'draft',
            'sort_order' => (int) ($page['sort_order'] ?? 0),
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ];
    }

    private function cloneSectionRow(array $section, $targetCode, array $replacements)
    {
        return [
            'page_key' => $targetCode,
            'section_key' => (string) ($section['section_key'] ?? ''),
            'title' => $this->replaceTextRecursive((string) ($section['title'] ?? ''), $replacements),
            'subtitle' => $this->replaceTextRecursive((string) ($section['subtitle'] ?? ''), $replacements),
            'content' => $this->replaceTextRecursive((string) ($section['content'] ?? ''), $replacements),
            'image' => (string) ($section['image'] ?? ''),
            'icon' => (string) ($section['icon'] ?? ''),
            'button_text' => $this->replaceTextRecursive((string) ($section['button_text'] ?? ''), $replacements),
            'button_url' => (string) ($section['button_url'] ?? ''),
            'settings_json' => $this->replaceTextRecursive((string) ($section['settings_json'] ?? ''), $replacements),
            'is_enabled' => (int) ($section['is_enabled'] ?? 1),
            'sort_order' => (int) ($section['sort_order'] ?? 0),
            'updated_at' => $this->now(),
        ];
    }

    private function cloneSectionItemRow(array $item, $newSectionId, array $replacements)
    {
        return [
            'section_id' => (int) $newSectionId,
            'item_key' => (string) ($item['item_key'] ?? ''),
            'title' => $this->replaceTextRecursive((string) ($item['title'] ?? ''), $replacements),
            'subtitle' => $this->replaceTextRecursive((string) ($item['subtitle'] ?? ''), $replacements),
            'content' => $this->replaceTextRecursive((string) ($item['content'] ?? ''), $replacements),
            'icon' => (string) ($item['icon'] ?? ''),
            'image' => (string) ($item['image'] ?? ''),
            'badge' => $this->replaceTextRecursive((string) ($item['badge'] ?? ''), $replacements),
            'button_text' => $this->replaceTextRecursive((string) ($item['button_text'] ?? ''), $replacements),
            'button_url' => (string) ($item['button_url'] ?? ''),
            'settings_json' => $this->replaceTextRecursive((string) ($item['settings_json'] ?? ''), $replacements),
            'is_enabled' => (int) ($item['is_enabled'] ?? 1),
            'sort_order' => (int) ($item['sort_order'] ?? 0),
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ];
    }

    private function cloneMenuRow(array $menu, $targetCode, array $replacements)
    {
        return [
            'menu_area' => (string) ($menu['menu_area'] ?? 'header'),
            'label' => $this->replaceTextRecursive((string) ($menu['label'] ?? ''), $replacements),
            'url' => (string) ($menu['url'] ?? ''),
            'target' => (string) ($menu['target'] ?? '_self'),
            'group_name' => $targetCode,
            'icon' => (string) ($menu['icon'] ?? ''),
            'is_enabled' => (int) ($menu['is_enabled'] ?? 1),
            'sort_order' => (int) ($menu['sort_order'] ?? 0),
        ];
    }

    private function buildRegistrySettings(array $input, array $brand, array $industry)
    {
        $settings = array_merge(
            $this->buildBrandSettings($input, $brand),
            $this->buildIndustrySettings($input, $industry)
        );
        return $settings;
    }

    private function buildBrandSettings(array $input, array $brand)
    {
        $brandName = (string) ($input['target_name'] ?? 'CRM');
        if ((string) ($brand['label'] ?? '') === 'CRM Khách Tốt') {
            $brandName = 'CRM Khách Tốt';
        }

        return [
            'brand_name' => $brandName,
            'default_meta_title' => $input['target_name'] . ' - CRM',
            'default_meta_description' => 'Landing draft cho ' . $input['target_name'] . '.',
            'kt_landing_header_cta_text' => 'Đăng ký ' . $input['target_name'],
            'kt_landing_hero_title' => $input['target_name'],
            'kt_landing_hero_subtitle' => 'Khởi tạo landing draft cho ' . $input['target_name'] . ' trong vài phút.',
            'kt_landing_footer_text' => $input['target_name'] . ' - Landing draft.',
        ];
    }

    private function buildIndustrySettings(array $input, array $industry)
    {
        $settings = [
            'kt_landing_features_json' => json_encode($industry['features'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'kt_landing_faq_json' => json_encode($industry['faq'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'kt_landing_testimonials_json' => json_encode($industry['testimonials'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'kt_landing_product_marketing_json' => json_encode($industry['product_marketing'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'primary_color' => '#1f3a5f',
            'secondary_color' => '#4b5563',
            'accent_color' => '#2563eb',
        ];

        foreach ([
            'hero_title',
            'hero_subtitle',
            'header_cta_text',
            'brand_name',
            'default_meta_title',
            'default_meta_description',
            'footer_text',
        ] as $key) {
            if (!empty($industry[$key])) {
                $settings['kt_landing_' . $key] = (string) $industry[$key];
            }
        }

        if (!empty($industry['proof_metrics'])) {
            $settings['kt_landing_product_marketing_json'] = json_encode([
                'showcases' => $industry['product_marketing']['showcases'] ?? [],
                'journey' => $industry['product_marketing']['journey'] ?? [],
                'why_choose' => $industry['product_marketing']['why_choose'] ?? [],
                'proof_metrics' => $industry['proof_metrics'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $settings;
    }

    private function buildProductMarketingPreset($label)
    {
        return [
            'showcases' => [
                ['slug' => 'crm', 'title' => 'CRM ' . $label, 'headline' => 'Quản lý khách hàng, pipeline và công việc trong một màn hình', 'description' => 'Đội ngũ vận hành theo luồng rõ ràng.', 'bullets' => ['Pipeline rõ trạng thái', 'Theo dõi công việc', 'Báo cáo hiệu suất']],
            ],
            'journey' => [
                ['step' => '01', 'title' => 'Lead', 'text' => 'Tiếp nhận và phân loại khách hàng tiềm năng', 'status' => 'Active'],
                ['step' => '02', 'title' => 'Báo giá', 'text' => 'Tạo báo giá và theo dõi phản hồi', 'status' => 'Active'],
                ['step' => '03', 'title' => 'Thanh toán', 'text' => 'Ghi nhận thanh toán và đối soát', 'status' => 'Active'],
            ],
            'why_choose' => [
                ['title' => 'Triển khai nhanh', 'text' => 'Bắt đầu bằng draft clone và chỉnh copy theo ngành.'],
                ['title' => 'Dễ mở rộng', 'text' => 'Marketplace và global blocks vẫn được reference.'],
            ],
        ];
    }

    private function buildTextReplacements($sourceTemplate, $targetName, $targetSlug, array $brand, array $industry)
    {
        $sourceBrand = trim((string) $this->CI->Kt_landing_model->get_setting('brand_name', 'CRM Khách Tốt'));
        if ($sourceBrand === '') {
            $sourceBrand = 'CRM Khách Tốt';
        }

        $replacements = [
            $sourceBrand => $targetName,
            'CRM Khách Tốt' => $targetName,
            'Khách Tốt' => $targetName,
            'KT Landing' => $targetName,
            'KT SAAS' => $targetName,
            'SaaS' => 'CRM',
        ];

        if (($brand['label'] ?? '') === 'CRM Khách Tốt') {
            $replacements['CRM'] = 'CRM';
        }

        if (!empty($industry['label'])) {
            $replacements['Marketplace'] = 'Marketplace';
        }

        return $replacements;
    }

    private function replaceTextRecursive($value, array $replacements)
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[$key] = $this->replaceTextRecursive($item, $replacements);
            }
            return $out;
        }

        if (!is_string($value)) {
            return $value;
        }

        $text = $value;
        foreach ($replacements as $search => $replace) {
            if ($search === '') {
                continue;
            }
            $text = str_replace($search, $replace, $text);
        }

        return $text;
    }

    private function detectBrokenBlockReferences(array $pages, array $sections)
    {
        $tokens = [];
        foreach ($pages as $row) {
            foreach (['title', 'seo_title', 'seo_description'] as $field) {
                $tokens = array_merge($tokens, $this->extractBlockTokens((string) ($row[$field] ?? '')));
            }
        }
        foreach ($sections as $row) {
            foreach (['title', 'subtitle', 'content', 'button_text', 'settings_json'] as $field) {
                $tokens = array_merge($tokens, $this->extractBlockTokens((string) ($row[$field] ?? '')));
            }
        }

        $tokens = array_values(array_unique(array_filter($tokens)));
        $missing = [];
        foreach ($tokens as $token) {
            if (!$this->CI->Kt_landing_model->get_global_block($token)) {
                $missing[] = $token;
            }
        }
        return $missing;
    }

    private function extractBlockTokens($text)
    {
        $text = (string) $text;
        if ($text === '') {
            return [];
        }

        $tokens = [];
        if (preg_match_all('/\\{\\{(?:block|global_block|landing_block):([a-z0-9_-]+)\\}\\}/i', $text, $matches)) {
            $tokens = array_merge($tokens, $matches[1]);
        }
        if (preg_match_all('/\\[\\[block:([a-z0-9_-]+)\\]\\]/i', $text, $matches2)) {
            $tokens = array_merge($tokens, $matches2[1]);
        }
        return $tokens;
    }

    private function detectMissingMediaAssets(array $pages, array $sections)
    {
        $missing = [];
        foreach (array_merge($pages, $sections) as $row) {
            foreach (['image', 'icon'] as $field) {
                $path = trim((string) ($row[$field] ?? ''));
                if ($path === '') {
                    continue;
                }
                if (preg_match('#^https?://#i', $path)) {
                    continue;
                }
                $full = FCPATH . ltrim($path, '/');
                if (!is_file($full)) {
                    $missing[] = $path;
                }
            }
        }
        return array_values(array_unique($missing));
    }

    private function buildReferencedBlocksSummary(array $sections)
    {
        $tokens = [];
        foreach ($sections as $row) {
            foreach (['content', 'settings_json'] as $field) {
                $tokens = array_merge($tokens, $this->extractBlockTokens((string) ($row[$field] ?? '')));
            }
        }
        return array_values(array_unique(array_filter($tokens)));
    }

    private function buildPricingOverrides()
    {
        $rows = $this->CI->Kt_landing_model->get_plan_overrides();
        $overrides = [];
        foreach ($rows as $row) {
            $planId = (int) ($row['plan_id'] ?? 0);
            if ($planId <= 0) {
                continue;
            }
            $overrides[$planId] = [
                'marketing_title' => (string) ($row['marketing_title'] ?? ''),
                'marketing_subtitle' => (string) ($row['marketing_subtitle'] ?? ''),
                'marketing_description' => (string) ($row['marketing_description'] ?? ''),
                'badge_text' => (string) ($row['badge_text'] ?? ''),
                'cta_text' => (string) ($row['cta_text'] ?? ''),
                'cta_url' => (string) ($row['cta_url'] ?? ''),
                'is_visible' => (int) ($row['is_visible'] ?? 1),
                'is_featured' => (int) ($row['is_featured'] ?? 0),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ];
        }
        return $overrides;
    }

    private function uniqueSlug($slug, array $existingSlugs)
    {
        $slug = $this->normalizeSlug($slug);
        if ($slug === '') {
            $slug = 'landing-clone';
        }
        $candidate = $slug;
        $i = 2;
        while (in_array($candidate, $existingSlugs, true) || $this->slugExists($candidate)) {
            $candidate = $slug . '-' . $i;
            $i++;
        }
        return $candidate;
    }

    private function slugExists($slug)
    {
        foreach ($this->CI->Kt_landing_model->get_pages() as $page) {
            if ((string) ($page['slug'] ?? '') === (string) $slug) {
                return true;
            }
        }
        return false;
    }

    private function normalizeSlug($slug)
    {
        $slug = strtolower(trim((string) $slug));
        if ($slug === '') {
            return '';
        }
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
        return trim((string) $slug, '-');
    }

    private function refreshReferencedGlobalBlockUsage(array $blockKeys)
    {
        $blockKeys = array_values(array_unique(array_filter(array_map('trim', $blockKeys))));
        foreach ($blockKeys as $blockKey) {
            $block = $this->CI->Kt_landing_model->get_global_block($blockKey);
            if ($block) {
                $this->globalBlockService->syncUsage((int) $block['id']);
            }
        }
    }

    private function logCloneEvent($event, $level, array $context = [])
    {
        if (!isset($this->CI->Kt_saas_model) || !method_exists($this->CI->Kt_saas_model, 'log_activity')) {
            return;
        }

        $this->CI->Kt_saas_model->log_activity($event, $level, $context);
    }

    private function now()
    {
        return date('Y-m-d H:i:s');
    }
}
