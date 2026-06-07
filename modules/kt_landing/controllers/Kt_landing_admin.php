<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_landing_admin extends AdminController
{
    private $globalBlockService;

    public function __construct()
    {
        parent::__construct();
        $this->load->helper(KT_LANDING_MODULE . '/kt_landing');
        $this->load->model(KT_LANDING_MODULE . '/Kt_landing_model');
        $this->load->model('kt_saas/Kt_saas_model');
        require_once __DIR__ . '/../services/LandingGlobalBlockService.php';
        $this->globalBlockService = new LandingGlobalBlockService();
        $this->requireLandlord();
    }

    public function index()
    {
        $this->requireCap('kt_landing_view');
        $data['title'] = 'Dashboard';
        $data['themes'] = $this->Kt_landing_model->get_themes();
        $data['global_blocks'] = $this->Kt_landing_model->get_global_blocks();
        $data['sections'] = $this->Kt_landing_model->get_sections();
        $data['pages'] = $this->Kt_landing_model->get_pages();
        $data['media_count'] = count($this->Kt_landing_model->get_media());
        $data['analytics'] = $this->Kt_landing_model->get_analytics_overview(30);
        $data['leads'] = array_slice($this->Kt_landing_model->get_leads(), 0, 10);
        $data['posts'] = array_slice($this->Kt_landing_model->get_blog_posts(), 0, 10);
        $data['snapshots'] = $this->Kt_landing_model->get_publish_snapshots(5);
        $this->load->view(KT_LANDING_MODULE . '/admin/overview', $data);
    }

    public function settings()
    {
        $this->requireCap('kt_landing_configure');
        $keys = [
            'landing_enabled', 'homepage_mode', 'default_template', 'site_name', 'site_title',
            'site_description', 'contact_email', 'contact_phone', 'company_address', 'default_language',
            'default_currency', 'default_template_note', 'enable_blog', 'enable_contact_form', 'enable_public_signup', 'enable_pricing',
            'enable_addons', 'enable_seo', 'maintenance_mode',
        ];
        if ($this->input->post()) {
            foreach ($keys as $key) {
                $value = $this->input->post($key, false);
                if (strpos($key, 'enable_') === 0 || in_array($key, ['landing_enabled', 'maintenance_mode'], true)) {
                    $value = $value ? '1' : '0';
                }
                $this->Kt_landing_model->set_setting($key, (string) $value);
            }
            set_alert('success', 'Saved settings');
            redirect(admin_url('kt_landing/settings'));
        }

        $data['title'] = 'Settings';
        $data['settings'] = $this->Kt_landing_model->get_settings_map($keys);
        $data['themes'] = $this->Kt_landing_model->get_themes();
        $this->load->view(KT_LANDING_MODULE . '/admin/settings', $data);
    }

    public function themes()
    {
        $this->requireCap('kt_landing_theme');
        if ($this->input->post()) {
            $action = (string) $this->input->post('action');
            if ($action === 'set_default') {
                $this->Kt_landing_model->set_default_theme((string) $this->input->post('theme_code'));
            } elseif ($action === 'save_style') {
                $data = $this->input->post(null, false);
                if (!empty($data['custom_css'])) {
                    $data['custom_css'] = strip_tags((string) $data['custom_css']);
                }
                $this->Kt_landing_model->save_theme_style($data);
            }
            set_alert('success', 'Saved theme settings');
            redirect(admin_url('kt_landing/themes'));
        }
        $data['title'] = 'Design Studio';
        $data['themes'] = $this->Kt_landing_model->get_themes();
        $data['style'] = $this->Kt_landing_model->get_settings_map([
            'primary_color', 'secondary_color', 'accent_color', 'text_color', 'background_color',
            'button_radius', 'card_radius', 'font_family', 'custom_css', 'custom_js',
            'light_logo', 'dark_logo', 'favicon', 'og_image',
        ]);
        $this->load->view(KT_LANDING_MODULE . '/admin/themes', $data);
    }

    public function customizer()
    {
        return $this->themes();
    }

    public function global_blocks()
    {
        $this->requireCap('kt_landing_blocks');

        if ($this->input->post()) {
            $action = (string) $this->input->post('action', true);
            $blockId = (int) $this->input->post('block_id');

            if ($action === 'duplicate' && $blockId > 0) {
                $result = $this->globalBlockService->duplicateBlock($blockId);
                set_alert(!empty($result['success']) ? 'success' : 'warning', !empty($result['success']) ? 'Global block duplicated' : (string) ($result['message'] ?? 'Duplicate failed'));
            } elseif ($action === 'disable' && $blockId > 0) {
                $result = $this->globalBlockService->disableBlock($blockId);
                set_alert(!empty($result['success']) ? 'success' : 'warning', !empty($result['success']) ? 'Global block disabled' : (string) ($result['message'] ?? 'Disable failed'));
            } elseif ($action === 'delete' && $blockId > 0) {
                $block = $this->Kt_landing_model->get_global_block($blockId);
                if ($this->globalBlockService->canDeleteBlock($blockId) && $this->Kt_landing_model->delete_global_block($blockId)) {
                    if (method_exists($this->Kt_saas_model, 'log_activity')) {
                        $this->Kt_saas_model->log_activity('landing.global_block.deleted', 'warning', [
                            'block_id' => $blockId,
                            'block_key' => (string) ($block['block_key'] ?? ''),
                            'block_name' => (string) ($block['block_name'] ?? ''),
                        ]);
                    }
                    set_alert('success', 'Global block deleted');
                } else {
                    set_alert('warning', 'Block is in use and cannot be deleted');
                }
            } else {
                $payload = $this->input->post(null, false);
                $blockId = isset($payload['block_id']) ? (int) $payload['block_id'] : 0;
                unset($payload['action'], $payload['block_id']);
                $result = $blockId > 0
                    ? $this->globalBlockService->updateBlock($blockId, $payload)
                    : $this->globalBlockService->createBlock($payload);
                set_alert(!empty($result['success']) ? 'success' : 'warning', !empty($result['success']) ? 'Global block saved' : (string) ($result['message'] ?? 'Save failed'));
            }

            redirect(admin_url('kt_landing/global_blocks'));
        }

        $editId = (int) $this->input->get('edit_id');
        $previewId = (int) $this->input->get('preview_id');

        $data['title'] = 'Website Builder Blocks';
        $data['blocks'] = $this->Kt_landing_model->get_global_blocks();
        $data['edit_block'] = $editId > 0 ? $this->Kt_landing_model->get_global_block($editId) : null;
        if (!$data['edit_block'] && !empty($data['blocks'])) {
            $data['edit_block'] = $data['blocks'][0];
        }
        $previewBlockId = $previewId > 0 ? $previewId : (int) ($data['edit_block']['id'] ?? 0);
        $data['preview_block'] = $previewBlockId > 0 ? $this->Kt_landing_model->get_global_block($previewBlockId) : null;
        $data['usage_graph'] = $previewBlockId > 0 ? $this->globalBlockService->getBlockUsageGraph($previewBlockId) : ['total' => 0, 'by_type' => [], 'references' => []];
        $data['summary'] = $this->globalBlockService->getUsageSummary();
        $data['block_types'] = ['CTA', 'FAQ', 'Trust Metrics', 'Footer', 'Pricing Notes', 'Marketplace CTA', 'Contact CTA', 'Demo CTA'];
        $data['block_usage_counts'] = [];
        foreach ($data['blocks'] as $block) {
            $data['block_usage_counts'][(int) $block['id']] = (int) $this->globalBlockService->getBlockUsageGraph((int) $block['id'])['total'];
        }
        $data['edit_block_can_delete'] = !empty($data['edit_block']) ? $this->globalBlockService->canDeleteBlock((int) $data['edit_block']['id']) : false;
        $this->load->view(KT_LANDING_MODULE . '/admin/global_blocks', $data);
    }

    public function pages()
    {
        $this->requireCap('kt_landing_sections');
        if ($this->input->post()) {
            $id = (int) $this->input->post('id');
            $delete = (int) $this->input->post('delete');
            $action = (string) $this->input->post('action');
            $entity = (string) $this->input->post('entity');
            if ($entity === 'section') {
                if ($delete > 0 && $id > 0) {
                    $this->Kt_landing_model->delete_section($id);
                } elseif ($action === 'move_up' && $id > 0) {
                    $this->Kt_landing_model->move_section_sort_order($id, 'up');
                } elseif ($action === 'move_down' && $id > 0) {
                    $this->Kt_landing_model->move_section_sort_order($id, 'down');
                } elseif ($action === 'duplicate' && $id > 0 && ($section = $this->Kt_landing_model->get_section_by_id($id))) {
                    unset($section['id']);
                    $section['section_key'] = (string) ($section['section_key'] ?? '') . '-copy';
                    $section['title'] = trim((string) ($section['title'] ?? '')) . ' Copy';
                    $section['sort_order'] = (int) ($section['sort_order'] ?? 0) + 1;
                    $this->Kt_landing_model->save_section($section, null);
                } else {
                    $this->Kt_landing_model->save_section($this->input->post(null, false), $id ?: null);
                }
            } elseif ($entity === 'page') {
                if ($delete > 0 && $id > 0) {
                    $this->Kt_landing_model->delete_page($id);
                } elseif ($action === 'move_up' && $id > 0) {
                    $this->Kt_landing_model->move_page_sort_order($id, 'up');
                } elseif ($action === 'move_down' && $id > 0) {
                    $this->Kt_landing_model->move_page_sort_order($id, 'down');
                } else {
                    $this->Kt_landing_model->save_page($this->input->post(null, false), $id ?: null);
                }
            } else {
                if ($delete > 0 && $id > 0) {
                    $this->Kt_landing_model->delete_page($id);
                } elseif ($action === 'move_up' && $id > 0) {
                    $this->Kt_landing_model->move_page_sort_order($id, 'up');
                } elseif ($action === 'move_down' && $id > 0) {
                    $this->Kt_landing_model->move_page_sort_order($id, 'down');
                } else {
                    $this->Kt_landing_model->save_page($this->input->post(null, false), $id ?: null);
                }
            }
            set_alert('success', 'Saved page');
            redirect(admin_url('kt_landing/pages'));
        }
        $data['title'] = 'Website Builder';
        $data['pages'] = $this->Kt_landing_model->get_pages();
        $data['sections'] = $this->Kt_landing_model->get_sections();
        $data['global_blocks'] = $this->Kt_landing_model->get_global_blocks();
        $data['menus'] = $this->Kt_landing_model->get_menus();
        $data['themes'] = $this->Kt_landing_model->get_themes();
        $selectedPageId = (int) $this->input->get('page_id');
        $selectedSectionId = (int) $this->input->get('section_id');
        $activePage = null;
        foreach ($data['pages'] as $page) {
            if ($selectedPageId > 0 && (int) ($page['id'] ?? 0) === $selectedPageId) {
                $activePage = $page;
                break;
            }
        }
        if (!$activePage && !empty($data['pages'])) {
            $activePage = $data['pages'][0];
        }
        $data['active_page'] = $activePage;
        $data['active_page_sections'] = !empty($activePage['slug']) ? $this->Kt_landing_model->get_sections_by_page_key((string) $activePage['slug']) : [];
        $data['active_section'] = null;
        foreach ($data['active_page_sections'] as $section) {
            if ($selectedSectionId > 0 && (int) ($section['id'] ?? 0) === $selectedSectionId) {
                $data['active_section'] = $section;
                break;
            }
        }
        if (!$data['active_section'] && !empty($data['active_page_sections'])) {
            $data['active_section'] = $data['active_page_sections'][0];
        }
        $data['global_block_usage_counts'] = [];
        foreach ($data['global_blocks'] as $block) {
            $data['global_block_usage_counts'][(int) $block['id']] = (int) $this->Kt_landing_model->get_global_block_usage_graph((int) $block['id'])['total'];
        }
        $this->load->view(KT_LANDING_MODULE . '/admin/pages', $data);
    }

    public function sections()
    {
        $this->requireCap('kt_landing_sections');
        if ($this->input->post()) {
            $id = (int) $this->input->post('id');
            $delete = (int) $this->input->post('delete');
            if ($delete > 0 && $id > 0) {
                $this->Kt_landing_model->delete_section($id);
            } else {
                $this->Kt_landing_model->save_section($this->input->post(null, false), $id ?: null);
            }
            set_alert('success', 'Saved section');
            redirect(admin_url('kt_landing/sections'));
        }
        $data['title'] = 'Website Builder Sections';
        $data['sections'] = $this->Kt_landing_model->get_sections();
        $this->load->view(KT_LANDING_MODULE . '/admin/sections', $data);
    }

    public function section_items()
    {
        $this->requireCap('kt_landing_sections');
        if ($this->input->post()) {
            $id = (int) $this->input->post('id');
            $delete = (int) $this->input->post('delete');
            if ($delete > 0 && $id > 0) {
                $this->Kt_landing_model->delete_section_item($id);
            } else {
                $this->Kt_landing_model->save_section_item($this->input->post(null, false), $id ?: null);
            }
            set_alert('success', 'Saved section item');
            redirect(admin_url('kt_landing/section_items'));
        }
        $sectionId = (int) $this->input->get('section_id');
        $itemKey = (string) $this->input->get('item_key', true);
        $data['title'] = 'Section Content';
        $data['sections'] = $this->Kt_landing_model->get_sections();
        $data['selected_section_id'] = $sectionId;
        $data['selected_item_key'] = $itemKey;
        $data['items'] = $sectionId > 0 ? $this->Kt_landing_model->get_section_items($sectionId, $itemKey !== '' ? $itemKey : null, false) : [];
        $this->load->view(KT_LANDING_MODULE . '/admin/section_items', $data);
    }

    public function menu()
    {
        $this->requireCap('kt_landing_sections');
        if ($this->input->post()) {
            $id = (int) $this->input->post('id');
            $delete = (int) $this->input->post('delete');
            if ($delete > 0 && $id > 0) {
                $this->Kt_landing_model->delete_menu($id);
            } else {
                $this->Kt_landing_model->save_menu($this->input->post(null, false), $id ?: null);
            }
            set_alert('success', 'Saved menu');
            redirect(admin_url('kt_landing/menu'));
        }
        $data['title'] = 'Navigation';
        $data['menus'] = $this->Kt_landing_model->get_menus();
        $this->load->view(KT_LANDING_MODULE . '/admin/menu', $data);
    }

    public function media()
    {
        $this->requireCap('kt_landing_media');
        $mediaService = kt_landing_media_service();
        if ($this->input->post()) {
            $action = (string) $this->input->post('action', true);
            $id = (int) $this->input->post('id');
            $post = $this->input->post(null, false);
            if ($action === 'delete' && $id > 0) {
                $result = $mediaService->deleteMedia($id);
                set_alert(!empty($result['success']) ? 'success' : 'warning', (string) ($result['message'] ?? 'Delete failed'));
            } elseif ($action === 'refresh_usage') {
                $mediaService->refreshUsageIndex($id > 0 ? $id : null);
                set_alert('success', 'Usage index refreshed');
            } else {
                $result = $id > 0
                    ? $mediaService->updateMedia($id, $post, $_FILES)
                    : $mediaService->uploadMedia($post, $_FILES);
                set_alert(!empty($result['success']) ? 'success' : 'warning', (string) ($result['message'] ?? 'Save failed'));
            }
            redirect(admin_url('kt_landing/media'));
        }
        $data['title'] = 'Media';
        $data['media_report'] = $mediaService->buildMediaDashboard();
        $this->load->view(KT_LANDING_MODULE . '/admin/media', $data);
    }

    public function pricing()
    {
        $this->requireCap('kt_landing_configure');
        $pricingSync = kt_landing_pricing_sync_service();
        if ($this->input->post()) {
            $action = trim((string) $this->input->post('action', true));
            $planId = (int) $this->input->post('plan_id');
            if ($action === 'sync_now') {
                $result = $pricingSync->syncPlanOverride($planId);
                set_alert(!empty($result['success']) ? 'success' : 'warning', !empty($result['success']) ? 'Pricing synced from CRM' : (string) ($result['message'] ?? 'Sync failed'));
            } else {
                $result = $pricingSync->saveOverride($planId, $this->input->post(null, false));
                set_alert(!empty($result['success']) ? 'success' : 'warning', !empty($result['success']) ? 'Saved pricing override' : (string) ($result['message'] ?? 'Save failed'));
            }
            redirect(admin_url('kt_landing/pricing'));
        }
        $data['title'] = 'Pricing';
        $data['pricing_report'] = $pricingSync->buildPricingSyncReport();
        $data['plans'] = $this->Kt_saas_model->get_public_plans();
        $data['overrides'] = $this->indexByPlan($this->Kt_landing_model->get_plan_overrides());
        $this->load->view(KT_LANDING_MODULE . '/admin/pricing', $data);
    }

    public function addons()
    {
        $this->requireCap('kt_landing_configure');
        $defaults = [
            ['key' => 'addon_matbao_invoice', 'title' => 'MatBao Invoice'],
            ['key' => 'addon_hsm', 'title' => 'HSM / Chu ky so'],
            ['key' => 'addon_sepay', 'title' => 'KT SePay'],
            ['key' => 'addon_website', 'title' => 'Website'],
            ['key' => 'addon_hosting', 'title' => 'Hosting'],
            ['key' => 'addon_domain', 'title' => 'Domain'],
        ];
        if ($this->input->post()) {
            foreach ($defaults as $addon) {
                $key = $addon['key'];
                $this->Kt_landing_model->set_setting($key . '_visible', $this->input->post($key . '_visible') ? '1' : '0');
                $this->Kt_landing_model->set_setting($key . '_title', (string) $this->input->post($key . '_title', false));
                $this->Kt_landing_model->set_setting($key . '_description', (string) $this->input->post($key . '_description', false));
                $this->Kt_landing_model->set_setting($key . '_cta', (string) $this->input->post($key . '_cta', false));
            }
            set_alert('success', 'Saved add-ons');
            redirect(admin_url('kt_landing/addons'));
        }
        $data['title'] = 'Add-ons';
        $data['addons'] = [];
        foreach ($defaults as $addon) {
            $key = $addon['key'];
            $data['addons'][] = [
                'key' => $key,
                'title' => $this->Kt_landing_model->get_setting($key . '_title', $addon['title']),
                'description' => $this->Kt_landing_model->get_setting($key . '_description', ''),
                'cta' => $this->Kt_landing_model->get_setting($key . '_cta', site_url('signup')),
                'visible' => $this->Kt_landing_model->get_setting($key . '_visible', '1'),
            ];
        }
        $this->load->view(KT_LANDING_MODULE . '/admin/addons', $data);
    }

    public function blog()
    {
        $this->requireCap('kt_landing_blog');
        if ($this->input->post()) {
            $id = (int) $this->input->post('id');
            $delete = (int) $this->input->post('delete');
            if ($delete > 0 && $id > 0) {
                $this->Kt_landing_model->delete_blog_post($id);
            } else {
                $this->Kt_landing_model->save_blog_post($this->input->post(null, false), $id ?: null);
            }
            set_alert('success', 'Saved blog post');
            redirect(admin_url('kt_landing/blog'));
        }
        $data['title'] = 'Content Hub';
        $data['posts'] = $this->Kt_landing_model->get_blog_posts();
        $this->load->view(KT_LANDING_MODULE . '/admin/blog', $data);
    }

    public function leads()
    {
        $this->requireCap('kt_landing_leads');
        if ($this->input->post()) {
            $id = (int) $this->input->post('id');
            $delete = (int) $this->input->post('delete');
            $convert = (int) $this->input->post('convert');
            if ($delete > 0 && $id > 0) {
                $this->Kt_landing_model->delete_lead($id);
            } elseif ($convert > 0 && $id > 0) {
                $result = $this->Kt_landing_model->convert_lead_to_perfex_lead($id);
                set_alert(!empty($result['success']) ? 'success' : 'warning', !empty($result['success']) ? 'Converted to Perfex lead' : (string) ($result['message'] ?? 'Convert failed'));
            } elseif ($id > 0) {
                $this->Kt_landing_model->update_lead_status($id, (string) $this->input->post('status'));
                if ($this->input->post('note', false) !== null && trim((string) $this->input->post('note', false)) !== '') {
                    $this->Kt_landing_model->add_lead_activity($id, 'note', (string) $this->input->post('note', false));
                }
                set_alert('success', 'Saved lead');
            }
            redirect(admin_url('kt_landing/leads'));
        }
        $data['title'] = 'Conversion Center';
        $data['leads'] = $this->Kt_landing_model->get_leads();
        $this->load->view(KT_LANDING_MODULE . '/admin/leads', $data);
    }

    public function seo()
    {
        $this->requireCap('kt_landing_configure');
        $settingsKeys = [
            'default_meta_title', 'default_meta_description', 'default_og_image', 'canonical_url',
            'robots_index', 'robots_follow', 'google_analytics_id', 'facebook_pixel_id', 'custom_head_html',
        ];
        if ($this->input->post()) {
            $action = trim((string) $this->input->post('action', true));
            if ($action === 'save_page_seo') {
                $pageId = (int) $this->input->post('page_id');
                $result = $this->Kt_landing_model->save_page_seo($pageId, $this->input->post(null, false));
                if (!empty($result['success'])) {
                    set_alert('success', 'Saved page SEO');
                    $this->logSeoEvent('seo.updated', 'info', [
                        'page_id' => (int) ($result['page_id'] ?? $pageId),
                        'page_slug' => (string) ($result['page_slug'] ?? ''),
                        'page_title' => (string) ($result['page_title'] ?? ''),
                    ]);
                } else {
                    $message = (string) ($result['message'] ?? 'Unable to save page SEO');
                    set_alert('warning', $message);
                    $this->logSeoEvent('seo.critical', 'warning', [
                        'page_id' => $pageId,
                        'message' => $message,
                        'conflict_page_id' => (int) ($result['conflict_page_id'] ?? 0),
                        'conflict_page_slug' => (string) ($result['conflict_page_slug'] ?? ''),
                    ]);
                }
                redirect(admin_url('kt_landing/seo' . ($pageId > 0 ? '?page_id=' . $pageId : '')));
                return;
            }

            foreach ($settingsKeys as $key) {
                $this->Kt_landing_model->set_setting($key, (string) $this->input->post($key, false));
            }
            set_alert('success', 'Saved SEO settings');
            $this->logSeoEvent('seo.updated', 'info', [
                'scope' => 'settings',
                'settings_keys' => $settingsKeys,
            ]);
            redirect(admin_url('kt_landing/seo'));
            return;
        }
        $data['title'] = 'SEO Center';
        $report = $this->Kt_landing_model->get_page_seo_report();
        $pages = $this->Kt_landing_model->get_pages();
        $selectedPageId = (int) $this->input->get('page_id');
        $selectedPage = null;
        foreach ($pages as $page) {
            if ((int) ($page['id'] ?? 0) === $selectedPageId) {
                $selectedPage = $page;
                break;
            }
        }
        if (!$selectedPage && !empty($pages)) {
            $selectedPage = $pages[0];
            $selectedPageId = (int) ($selectedPage['id'] ?? 0);
        }
        $data['settings'] = $this->Kt_landing_model->get_settings_map($settingsKeys);
        $data['pages'] = $pages;
        $data['seo_report'] = $report;
        $data['selected_page'] = $selectedPage;
        $data['selected_page_seo'] = $selectedPage ? $this->Kt_landing_model->get_page_seo_for_page($selectedPage) : [];
        $data['selected_page_id'] = $selectedPageId;
        $data['publish_blockers'] = $this->Kt_landing_model->get_seo_publish_blockers();
        $data['seo_counts'] = [
            'pass' => (int) ($report['pages_healthy'] ?? 0),
            'warning' => count((array) ($report['warnings'] ?? [])),
            'critical' => count((array) ($report['critical_issues'] ?? [])),
            'audited' => (int) ($report['pages_audited'] ?? 0),
        ];
        if (!empty($report['critical_issues'])) {
            $this->logSeoEvent('seo.critical', 'warning', [
                'critical_count' => count((array) $report['critical_issues']),
                'warnings_count' => count((array) $report['warnings']),
            ]);
        } elseif (!empty($report['warnings'])) {
            $this->logSeoEvent('seo.warning', 'info', [
                'warning_count' => count((array) $report['warnings']),
            ]);
        }
        $this->load->view(KT_LANDING_MODULE . '/admin/seo', $data);
    }

    public function preview($template = null)
    {
        $this->requireCap('kt_landing_preview');
        $publishService = kt_landing_publish_service();
        $snapshotId = (int) $this->input->get('snapshot_id');
        $data = $publishService->buildPreviewData($snapshotId > 0 ? $snapshotId : null);
        if ($template !== null && $template !== '') {
            $data['template_code'] = (string) $template;
        }
        $data['title'] = 'Publish Preview';
        $data['preview_template'] = (string) ($data['snapshot']['snapshot_name'] ?? ($template ?: 'current'));
        if (!empty($data['snapshot']['id'])) {
            $this->Kt_saas_model->log_activity('publish.preview', 'info', [
                'snapshot_id' => (int) $data['snapshot']['id'],
                'snapshot_version' => (int) ($data['snapshot']['snapshot_version'] ?? $data['snapshot']['id']),
                'source' => 'preview_route',
            ]);
        }
        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $this->output->set_header('Cache-Control: post-check=0, pre-check=0', false);
        $this->output->set_header('Pragma: no-cache');
        $this->output->set_header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
        $this->output->set_header('X-Robots-Tag: noindex, nofollow, noarchive');
        $this->load->view(KT_LANDING_MODULE . '/admin/publish_preview', $data);
    }

    public function analytics()
    {
        $this->requireCap('kt_landing_analytics');
        if ($this->input->post('rebuild')) {
            $this->Kt_landing_model->rebuild_daily_analytics();
            set_alert('success', 'Rebuilt daily analytics');
            redirect(admin_url('kt_landing/analytics'));
        }
        $data['title'] = 'Analytics';
        $data['overview'] = $this->Kt_landing_model->get_analytics_overview(30);
        $this->load->view(KT_LANDING_MODULE . '/admin/analytics', $data);
    }

    public function clone_engine()
    {
        $this->requireCap('kt_landing_clone');
        $service = kt_landing_clone_service();
        $preview = null;
        $result = null;

        if ($this->input->post()) {
            $action = trim((string) $this->input->post('action', true));
            $payload = $this->input->post(null, false);
            unset($payload['action']);

            if ($action === 'preview') {
                $preview = $service->buildPreview($payload);
            } elseif ($action === 'clone') {
                $result = $service->cloneLanding($payload);
                if (!empty($result['success'])) {
                    set_alert('success', 'Landing clone created as draft');
                    redirect(admin_url('kt_landing/clone?template=' . urlencode((string) ($result['template_code'] ?? ''))));
                    return;
                }
                set_alert('warning', (string) ($result['message'] ?? 'Clone failed'));
            }
        }

        $selectedTemplate = trim((string) $this->input->get('template', true));
        $data = $service->getWizardData();
        $data['title'] = 'Landing Clone Engine';
        $data['selected_template'] = $selectedTemplate;
        $data['preview'] = $preview;
        $data['result'] = $result;
        $data['public_preview_url'] = $selectedTemplate !== '' ? site_url('?tpl=' . rawurlencode($selectedTemplate)) : '';
        $this->load->view(KT_LANDING_MODULE . '/admin/clone_engine', $data);
    }

    public function publish()
    {
        $this->requireCap('kt_landing_publish');
        $publishService = kt_landing_publish_service();
        if ($this->input->post()) {
            if ($this->input->post('publish_now')) {
                $result = $publishService->createSnapshot([], 'published');
                $createdStatus = (string) ($result['snapshot']['snapshot_status'] ?? '');
                if (!empty($result['success']) && $createdStatus === 'published') {
                    set_alert('success', 'Published snapshot created');
                } elseif (!empty($result['success'])) {
                    set_alert('warning', 'Snapshot created with validation warnings');
                } else {
                    set_alert('warning', (string) ($result['message'] ?? 'Publish failed'));
                }
            } elseif ($this->input->post('apply_snapshot')) {
                $this->requireCap('kt_landing_rollback');
                $snapshotId = (int) $this->input->post('snapshot_id');
                $result = $publishService->rollbackSnapshot($snapshotId);
                set_alert(!empty($result['success']) ? 'success' : 'warning', !empty($result['success']) ? 'Snapshot rolled back' : (string) ($result['message'] ?? 'Apply failed'));
            } elseif ($this->input->post('schedule_publish')) {
                $snapshotId = (int) $this->input->post('snapshot_id');
                $publishAt = trim((string) $this->input->post('publish_at', false));
                if ($snapshotId > 0 && $publishAt !== '') {
                    $this->Kt_landing_model->schedule_publish($snapshotId, $publishAt);
                    set_alert('success', 'Publish scheduled');
                } else {
                    set_alert('warning', 'Missing snapshot or publish_at');
                }
            } elseif ($this->input->post('preview_snapshot')) {
                $snapshotId = (int) $this->input->post('snapshot_id');
                if ($snapshotId > 0) {
                    redirect(admin_url('kt_landing/publish?preview_id=' . $snapshotId));
                }
            }
            redirect(admin_url('kt_landing/publish'));
        }

        $previewId = (int) $this->input->get('preview_id');
        if ($previewId > 0) {
            $data = $publishService->buildPreviewData($previewId);
            $data['title'] = 'Publish Preview';
            $data['preview_template'] = (string) ($data['snapshot']['snapshot_name'] ?? 'snapshot-preview');
            if (!empty($data['snapshot']['id'])) {
                $this->Kt_saas_model->log_activity('publish.preview', 'info', [
                    'snapshot_id' => (int) $data['snapshot']['id'],
                    'snapshot_version' => (int) ($data['snapshot']['snapshot_version'] ?? $data['snapshot']['id']),
                    'source' => 'publish_dashboard',
                ]);
            }
            $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            $this->output->set_header('Cache-Control: post-check=0, pre-check=0', false);
            $this->output->set_header('Pragma: no-cache');
            $this->output->set_header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
            $this->output->set_header('X-Robots-Tag: noindex, nofollow, noarchive');
            $this->load->view(KT_LANDING_MODULE . '/admin/publish_preview', $data);
            return;
        }

        $status = trim((string) $this->input->get('status', true));
        $data = $publishService->buildDashboard($status);
        $data['title'] = 'Publish Center';
        $this->load->view(KT_LANDING_MODULE . '/admin/publish', $data);
    }

    private function requireLandlord()
    {
        if (!kt_landing_is_landlord_context()) {
            access_denied('kt_landing');
        }
    }

    private function requireCap($cap)
    {
        if (!kt_landing_staff_can($cap)) {
            access_denied('kt_landing');
        }
    }

    private function logSeoEvent($eventKey, $severity, array $context = [])
    {
        if (method_exists($this->Kt_saas_model, 'log_activity')) {
            $this->Kt_saas_model->log_activity($eventKey, $severity, $context);
        }
    }

    private function indexByPlan(array $rows)
    {
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['plan_id']] = $row;
        }
        return $out;
    }
}
