<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_landing_model extends App_Model
{
    private $landlordDb;

    public function __construct()
    {
        parent::__construct();
        $this->landlordDb = null;
    }

    public function now()
    {
        return date('Y-m-d H:i:s');
    }
    public function get_setting($key, $default = '')
    {
        $row = $this->landlord_db()->where('setting_key', $key)->get(db_prefix() . 'kt_landing_settings')->row_array();
        if (!$row) {
            return $default;
        }
        if ((int) ($row['is_json'] ?? 0) === 1) {
            $decoded = json_decode((string) $row['setting_value'], true);
            return is_array($decoded) ? $decoded : $default;
        }
        return (string) ($row['setting_value'] ?? $default);
    }

    public function set_setting($key, $value, $isJson = false)
    {
        $payload = [
            'setting_key' => $key,
            'setting_value' => $isJson ? json_encode($value) : (string) $value,
            'is_json' => $isJson ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $table = db_prefix() . 'kt_landing_settings';
        $db = $this->landlord_db();
        $exists = $db->where('setting_key', $key)->count_all_results($table);
        if ($exists) {
            return (bool) $db->where('setting_key', $key)->update($table, $payload);
        }
        return (bool) $db->insert($table, $payload);
    }

    public function get_settings_map(array $keys)
    {
        $map = [];
        foreach ($keys as $key) {
            $map[$key] = $this->get_setting($key);
        }
        return $map;
    }

    public function get_clone_registry()
    {
        $registry = $this->get_setting('kt_landing_clone_registry_json', []);
        return is_array($registry) ? $registry : [];
    }

    public function save_clone_registry(array $registry)
    {
        return $this->set_setting('kt_landing_clone_registry_json', $registry, true);
    }

    public function get_template_clone_variant($templateCode)
    {
        $registry = $this->get_clone_registry();
        $templateCode = trim((string) $templateCode);
        if ($templateCode === '' || empty($registry[$templateCode]) || !is_array($registry[$templateCode])) {
            return null;
        }

        return $registry[$templateCode];
    }

    public function get_template_clone_overrides($templateCode)
    {
        $variant = $this->get_template_clone_variant($templateCode);
        if (!$variant) {
            return [];
        }

        $overrides = $variant['settings'] ?? [];
        return is_array($overrides) ? $overrides : [];
    }

    public function get_template_clone_pricing_overrides($templateCode)
    {
        $variant = $this->get_template_clone_variant($templateCode);
        if (!$variant) {
            return [];
        }

        $overrides = $variant['pricing_overrides'] ?? [];
        return is_array($overrides) ? $overrides : [];
    }

    public function get_registered_template_codes()
    {
        $codes = ['fastwork_inspired', 'corporate_saas', 'modern_growth', 'minimal_enterprise'];
        $registry = $this->get_clone_registry();
        foreach ($registry as $templateCode => $variant) {
            if (!is_string($templateCode) || trim($templateCode) === '') {
                continue;
            }
            $codes[] = $templateCode;
            if (is_array($variant) && !empty($variant['base_template_code'])) {
                $codes[] = (string) $variant['base_template_code'];
            }
        }

        return array_values(array_unique(array_filter($codes)));
    }

    public function get_themes()
    {
        return $this->landlord_db()->order_by('sort_order', 'asc')->get(db_prefix() . 'kt_landing_themes')->result_array();
    }

    public function set_default_theme($code)
    {
        $table = db_prefix() . 'kt_landing_themes';
        $db = $this->landlord_db();
        $db->update($table, ['is_default' => 0]);
        $db->where('code', $code)->update($table, ['is_default' => 1]);
        $this->set_setting('default_template', $code);
        $this->set_setting('kt_landing_template', $code);
        return $db->affected_rows() >= 0;
    }

    public function save_theme_style(array $data)
    {
        $keys = [
            'primary_color', 'secondary_color', 'accent_color', 'text_color', 'background_color',
            'button_radius', 'card_radius', 'font_family', 'custom_css', 'custom_js',
            'light_logo', 'dark_logo', 'favicon', 'og_image',
        ];
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $this->set_setting($key, $data[$key]);
            }
        }
        return true;
    }

    public function get_global_blocks($includeDisabled = true)
    {
        $table = db_prefix() . 'kt_landing_global_blocks';
        $db = $this->landlord_db();
        if (!$db->table_exists($table)) {
            return [];
        }

        if (!$includeDisabled) {
            $db->where('status', 'active');
        }

        return $db
            ->order_by('block_type', 'asc')
            ->order_by('block_name', 'asc')
            ->get($table)
            ->result_array();
    }

    public function get_global_block($idOrKey)
    {
        $table = db_prefix() . 'kt_landing_global_blocks';
        $db = $this->landlord_db();
        if (!$db->table_exists($table)) {
            return null;
        }

        $query = $db->from($table);
        if (is_numeric($idOrKey)) {
            $query->where('id', (int) $idOrKey);
        } else {
            $query->where('block_key', (string) $idOrKey);
        }

        $row = $query->get()->row_array();
        return $row ?: null;
    }

    public function save_global_block(array $data, $id = null)
    {
        $table = db_prefix() . 'kt_landing_global_blocks';
        $db = $this->landlord_db();
        if (!$db->table_exists($table)) {
            return false;
        }

        $existing = $id ? $this->get_global_block($id) : null;
        $blockName = trim((string) ($data['block_name'] ?? ($existing['block_name'] ?? '')));
        $blockKeyInput = $id ? (string) ($existing['block_key'] ?? '') : (string) ($data['block_key'] ?? '');
        if ($blockKeyInput === '') {
            $blockKeyInput = (string) ($data['block_key'] ?? '');
        }
        $blockKey = $this->normalize_block_key($blockKeyInput !== '' ? $blockKeyInput : $blockName);
        if ($blockKey === '' || $blockName === '') {
            return false;
        }

        $content = $data['content_json'] ?? ($existing['content_json'] ?? '');
        if (is_array($content)) {
            $content = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }

        $payload = [
            'block_key' => $blockKey,
            'block_name' => $blockName,
            'block_type' => trim((string) ($data['block_type'] ?? ($existing['block_type'] ?? 'CTA'))),
            'content_json' => (string) $content,
            'status' => trim((string) ($data['status'] ?? ($existing['status'] ?? 'active'))),
            'updated_by' => function_exists('get_staff_user_id') ? (int) get_staff_user_id() : null,
            'updated_at' => $this->now(),
        ];

        if (!in_array($payload['status'], ['active', 'disabled'], true)) {
            $payload['status'] = 'active';
        }

        if ($id) {
            return (bool) $db->where('id', (int) $id)->update($table, $payload);
        }

        $payload['created_by'] = function_exists('get_staff_user_id') ? (int) get_staff_user_id() : null;
        $payload['created_at'] = $this->now();
        return (bool) $db->insert($table, $payload);
    }

    public function delete_global_block($id)
    {
        if (!$this->can_delete_global_block($id)) {
            return false;
        }

        $table = db_prefix() . 'kt_landing_global_blocks';
        $db = $this->landlord_db();
        if (!$db->table_exists($table)) {
            return false;
        }

        return (bool) $db->where('id', (int) $id)->delete($table);
    }

    public function get_global_block_usage($blockId)
    {
        $table = db_prefix() . 'kt_landing_block_usage';
        $db = $this->landlord_db();
        if (!$db->table_exists($table)) {
            return [];
        }

        return $db
            ->where('block_id', (int) $blockId)
            ->order_by('usage_type', 'asc')
            ->order_by('usage_ref_type', 'asc')
            ->order_by('usage_ref_id', 'asc')
            ->get($table)
            ->result_array();
    }

    public function replace_global_block_usage($blockId, array $usages)
    {
        $table = db_prefix() . 'kt_landing_block_usage';
        $db = $this->landlord_db();
        if (!$db->table_exists($table)) {
            return false;
        }

        $db->where('block_id', (int) $blockId)->delete($table);
        foreach ($usages as $usage) {
            $payload = [
                'block_id' => (int) $blockId,
                'usage_type' => (string) ($usage['usage_type'] ?? 'landing'),
                'usage_ref_type' => (string) ($usage['usage_ref_type'] ?? ''),
                'usage_ref_id' => isset($usage['usage_ref_id']) ? (int) $usage['usage_ref_id'] : null,
                'usage_ref_key' => (string) ($usage['usage_ref_key'] ?? ''),
                'usage_label' => (string) ($usage['usage_label'] ?? ''),
                'source_field' => (string) ($usage['source_field'] ?? ''),
                'is_primary' => !empty($usage['is_primary']) ? 1 : 0,
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ];
            $db->insert($table, $payload);
        }

        return true;
    }

    public function record_global_block_usage($blockId, array $usage)
    {
        $table = db_prefix() . 'kt_landing_block_usage';
        $db = $this->landlord_db();
        if (!$db->table_exists($table)) {
            return false;
        }

        return (bool) $db->insert($table, [
            'block_id' => (int) $blockId,
            'usage_type' => (string) ($usage['usage_type'] ?? 'landing'),
            'usage_ref_type' => (string) ($usage['usage_ref_type'] ?? ''),
            'usage_ref_id' => isset($usage['usage_ref_id']) ? (int) $usage['usage_ref_id'] : null,
            'usage_ref_key' => (string) ($usage['usage_ref_key'] ?? ''),
            'usage_label' => (string) ($usage['usage_label'] ?? ''),
            'source_field' => (string) ($usage['source_field'] ?? ''),
            'is_primary' => !empty($usage['is_primary']) ? 1 : 0,
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ]);
    }

    public function clear_global_block_usage($blockId)
    {
        $table = db_prefix() . 'kt_landing_block_usage';
        $db = $this->landlord_db();
        if (!$db->table_exists($table)) {
            return false;
        }

        return (bool) $db->where('block_id', (int) $blockId)->delete($table);
    }

    public function get_global_block_usage_graph($blockId)
    {
        $rows = $this->get_global_block_usage($blockId);
        $graph = [
            'block_id' => (int) $blockId,
            'total' => count($rows),
            'by_type' => [],
            'references' => [],
        ];

        foreach ($rows as $row) {
            $type = (string) ($row['usage_type'] ?? 'landing');
            if (!isset($graph['by_type'][$type])) {
                $graph['by_type'][$type] = 0;
            }
            $graph['by_type'][$type]++;
            $graph['references'][] = [
                'usage_type' => $type,
                'usage_ref_type' => (string) ($row['usage_ref_type'] ?? ''),
                'usage_ref_id' => (int) ($row['usage_ref_id'] ?? 0),
                'usage_ref_key' => (string) ($row['usage_ref_key'] ?? ''),
                'usage_label' => (string) ($row['usage_label'] ?? ''),
                'source_field' => (string) ($row['source_field'] ?? ''),
                'is_primary' => (int) ($row['is_primary'] ?? 0),
            ];
        }

        return $graph;
    }

    public function can_delete_global_block($id)
    {
        $table = db_prefix() . 'kt_landing_block_usage';
        $db = $this->landlord_db();
        if (!$db->table_exists($table)) {
            return true;
        }

        return (int) $db->where('block_id', (int) $id)->count_all_results($table) === 0;
    }

    private function normalize_block_key($key)
    {
        $key = trim((string) $key);
        if ($key === '') {
            return '';
        }

        $key = strtolower($key);
        $key = preg_replace('/[^a-z0-9]+/i', '-', $key);
        $key = trim((string) $key, '-');
        return $key;
    }

    private function mediaPathExists($needle, array $mediaPaths)
    {
        $needle = trim((string) $needle);
        if ($needle === '') {
            return true;
        }

        foreach ($mediaPaths as $path) {
            $path = trim((string) $path);
            if ($path === '') {
                continue;
            }
            if ($path === $needle || basename($path) === basename($needle) || strpos($needle, basename($path)) !== false) {
                return true;
            }
        }

        return false;
    }

    public function get_sections()
    {
        return $this->landlord_db()->order_by('sort_order', 'asc')->get(db_prefix() . 'kt_landing_sections')->result_array();
    }

    public function get_sections_by_page_key($pageKey)
    {
        $pageKey = trim((string) $pageKey);
        $db = $this->landlord_db();
        $query = $db->from(db_prefix() . 'kt_landing_sections');
        if ($pageKey !== '') {
            $query->where('page_key', $pageKey);
        }
        return $query->order_by('sort_order', 'asc')->order_by('id', 'asc')->get()->result_array();
    }

    public function get_section_by_key($pageKey, $sectionKey)
    {
        return $this->landlord_db()
            ->where('page_key', (string) $pageKey)
            ->where('section_key', (string) $sectionKey)
            ->get(db_prefix() . 'kt_landing_sections')
            ->row_array();
    }

    public function save_section(array $data, $id = null)
    {
        $payload = [
            'page_key' => (string) ($data['page_key'] ?? 'home'),
            'section_key' => (string) ($data['section_key'] ?? ''),
            'title' => (string) ($data['title'] ?? ''),
            'subtitle' => (string) ($data['subtitle'] ?? ''),
            'content' => (string) ($data['content'] ?? ''),
            'image' => (string) ($data['image'] ?? ''),
            'icon' => (string) ($data['icon'] ?? ''),
            'button_text' => (string) ($data['button_text'] ?? ''),
            'button_url' => (string) ($data['button_url'] ?? ''),
            'settings_json' => (string) ($data['settings_json'] ?? ''),
            'is_enabled' => !empty($data['is_enabled']) ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $table = db_prefix() . 'kt_landing_sections';
        $db = $this->landlord_db();
        if ($id) {
            return (bool) $db->where('id', (int) $id)->update($table, $payload);
        }
        return (bool) $db->insert($table, $payload);
    }

    public function delete_section($id)
    {
        return (bool) $this->landlord_db()->where('id', (int) $id)->delete(db_prefix() . 'kt_landing_sections');
    }

    public function get_section_by_id($id)
    {
        $row = $this->landlord_db()
            ->where('id', (int) $id)
            ->get(db_prefix() . 'kt_landing_sections')
            ->row_array();

        return $row ?: null;
    }

    public function move_section_sort_order($id, $direction)
    {
        $db = $this->landlord_db();
        $table = db_prefix() . 'kt_landing_sections';
        $current = $this->get_section_by_id($id);
        if (!$current) {
            return false;
        }

        $direction = $direction === 'up' ? 'up' : 'down';
        $neighborQuery = $db->where('page_key', (string) ($current['page_key'] ?? ''))->where('id !=', (int) $id);
        if ($direction === 'up') {
            $neighbor = $neighborQuery->where('sort_order <', (int) ($current['sort_order'] ?? 0))->order_by('sort_order', 'desc')->order_by('id', 'desc')->get($table)->row_array();
        } else {
            $neighbor = $neighborQuery->where('sort_order >', (int) ($current['sort_order'] ?? 0))->order_by('sort_order', 'asc')->order_by('id', 'asc')->get($table)->row_array();
        }

        if (!$neighbor) {
            return false;
        }

        $currentSort = (int) ($current['sort_order'] ?? 0);
        $neighborSort = (int) ($neighbor['sort_order'] ?? 0);
        $db->where('id', (int) $current['id'])->update($table, ['sort_order' => $neighborSort, 'updated_at' => $this->now()]);
        $db->where('id', (int) $neighbor['id'])->update($table, ['sort_order' => $currentSort, 'updated_at' => $this->now()]);
        return true;
    }

    public function get_section_items($sectionId, $itemKey = null, $enabledOnly = true)
    {
        $db = $this->landlord_db();
        $table = db_prefix() . 'kt_landing_section_items';
        if (!$db->table_exists($table)) {
            return [];
        }

        $db->where('section_id', (int) $sectionId);
        if ($itemKey !== null && $itemKey !== '') {
            $db->where('item_key', (string) $itemKey);
        }
        if ($enabledOnly) {
            $db->where('is_enabled', 1);
        }
        return $db
            ->order_by('sort_order', 'asc')
            ->order_by('id', 'asc')
            ->get($table)
            ->result_array();
    }

    public function save_section_item(array $data, $id = null)
    {
        $payload = [
            'section_id' => (int) ($data['section_id'] ?? 0),
            'item_key' => (string) ($data['item_key'] ?? ''),
            'title' => (string) ($data['title'] ?? ''),
            'subtitle' => (string) ($data['subtitle'] ?? ''),
            'content' => (string) ($data['content'] ?? ''),
            'icon' => (string) ($data['icon'] ?? ''),
            'image' => (string) ($data['image'] ?? ''),
            'badge' => (string) ($data['badge'] ?? ''),
            'button_text' => (string) ($data['button_text'] ?? ''),
            'button_url' => (string) ($data['button_url'] ?? ''),
            'settings_json' => (string) ($data['settings_json'] ?? ''),
            'is_enabled' => !empty($data['is_enabled']) ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'updated_at' => $this->now(),
        ];
        if ($payload['section_id'] <= 0 || $payload['item_key'] === '') {
            return false;
        }

        $db = $this->landlord_db();
        $table = db_prefix() . 'kt_landing_section_items';
        if (!$db->table_exists($table)) {
            return false;
        }
        if ($id) {
            return (bool) $db->where('id', (int) $id)->update($table, $payload);
        }
        $payload['created_at'] = $this->now();
        return (bool) $db->insert($table, $payload);
    }

    public function delete_section_item($id)
    {
        $table = db_prefix() . 'kt_landing_section_items';
        $db = $this->landlord_db();
        if (!$db->table_exists($table)) {
            return false;
        }
        return (bool) $db->where('id', (int) $id)->delete($table);
    }

    public function get_menus($area = null)
    {
        $db = $this->landlord_db();
        if ($area !== null) {
            $db->where('menu_area', $area);
        }
        return $db->order_by('sort_order', 'asc')->get(db_prefix() . 'kt_landing_menus')->result_array();
    }

    public function get_menus_for_template($templateCode = null)
    {
        $db = $this->landlord_db();
        $table = db_prefix() . 'kt_landing_menus';
        $templateCode = trim((string) $templateCode);
        if ($templateCode !== '') {
            $rows = $db->where('group_name', $templateCode)->order_by('sort_order', 'asc')->order_by('id', 'asc')->get($table)->result_array();
            if (!empty($rows)) {
                return $rows;
            }
        }

        return $db->order_by('sort_order', 'asc')->order_by('id', 'asc')->get($table)->result_array();
    }

    public function save_menu(array $data, $id = null)
    {
        $payload = [
            'menu_area' => (string) ($data['menu_area'] ?? 'header'),
            'label' => (string) ($data['label'] ?? ''),
            'url' => (string) ($data['url'] ?? ''),
            'target' => (string) ($data['target'] ?? '_self'),
            'group_name' => (string) ($data['group_name'] ?? ''),
            'icon' => (string) ($data['icon'] ?? ''),
            'is_enabled' => !empty($data['is_enabled']) ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
        $db = $this->landlord_db();
        $table = db_prefix() . 'kt_landing_menus';
        if ($id) {
            return (bool) $db->where('id', (int) $id)->update($table, $payload);
        }
        return (bool) $db->insert($table, $payload);
    }

    public function delete_menu($id)
    {
        return (bool) $this->landlord_db()->where('id', (int) $id)->delete(db_prefix() . 'kt_landing_menus');
    }

    public function get_plan_overrides()
    {
        return $this->landlord_db()->get(db_prefix() . 'kt_landing_plan_overrides')->result_array();
    }

    public function get_plan_override($planId)
    {
        $row = $this->landlord_db()
            ->where('plan_id', (int) $planId)
            ->get(db_prefix() . 'kt_landing_plan_overrides')
            ->row_array();

        return $row ?: null;
    }

    public function save_plan_override($planId, array $data)
    {
        $existing = $this->get_plan_override($planId) ?: [];
        $payload = [
            'plan_id' => (int) $planId,
            'marketing_title' => array_key_exists('marketing_title', $data)
                ? (string) $data['marketing_title']
                : (string) ($existing['marketing_title'] ?? ''),
            'badge_text' => array_key_exists('badge_text', $data)
                ? (string) $data['badge_text']
                : (string) ($existing['badge_text'] ?? ''),
            'cta_text' => array_key_exists('cta_text', $data)
                ? (string) $data['cta_text']
                : (string) ($existing['cta_text'] ?? ''),
            'cta_url' => array_key_exists('cta_url', $data)
                ? (string) $data['cta_url']
                : (string) ($existing['cta_url'] ?? ''),
            'is_visible' => array_key_exists('is_visible', $data)
                ? (!empty($data['is_visible']) ? 1 : 0)
                : (int) ($existing['is_visible'] ?? 1),
            'is_featured' => array_key_exists('is_featured', $data)
                ? (!empty($data['is_featured']) ? 1 : 0)
                : (int) ($existing['is_featured'] ?? 0),
            'sort_order' => array_key_exists('sort_order', $data)
                ? (int) $data['sort_order']
                : (int) ($existing['sort_order'] ?? 0),
        ];
        $payload['marketing_description'] = array_key_exists('marketing_description', $data)
            ? (string) $data['marketing_description']
            : (string) ($existing['marketing_description'] ?? '');
        // Backward-compatible: only persist subtitle when column exists.
        if ($this->has_plan_override_subtitle_column()) {
            $payload['marketing_subtitle'] = array_key_exists('marketing_subtitle', $data)
                ? (string) $data['marketing_subtitle']
                : (string) ($existing['marketing_subtitle'] ?? '');
        }
        if ($this->has_plan_override_sync_columns()) {
            $syncFields = [
                'source_plan_snapshot_hash',
                'source_plan_snapshot_json',
                'source_plan_updated_at',
                'last_synced_at',
            ];
            foreach ($syncFields as $field) {
                if (array_key_exists($field, $data)) {
                    $payload[$field] = $data[$field];
                } elseif (array_key_exists($field, $existing)) {
                    $payload[$field] = $existing[$field];
                }
            }
        }
        $db = $this->landlord_db();
        $table = db_prefix() . 'kt_landing_plan_overrides';
        $exists = $db->where('plan_id', (int) $planId)->count_all_results($table);
        if ($exists) {
            return (bool) $db->where('plan_id', (int) $planId)->update($table, $payload);
        }
        return (bool) $db->insert($table, $payload);
    }

    private function has_plan_override_subtitle_column()
    {
        static $hasColumn = null;
        if ($hasColumn !== null) {
            return $hasColumn;
        }

        $table = db_prefix() . 'kt_landing_plan_overrides';
        $hasColumn = $this->landlord_db()->field_exists('marketing_subtitle', $table);
        return $hasColumn;
    }

    private function has_plan_override_sync_columns()
    {
        static $hasColumns = null;
        if ($hasColumns !== null) {
            return $hasColumns;
        }

        $table = db_prefix() . 'kt_landing_plan_overrides';
        $db = $this->landlord_db();
        $hasColumns = $db->field_exists('source_plan_snapshot_hash', $table)
            && $db->field_exists('source_plan_snapshot_json', $table)
            && $db->field_exists('source_plan_updated_at', $table)
            && $db->field_exists('last_synced_at', $table);
        return $hasColumns;
    }

    public function get_blog_posts($publishedOnly = false)
    {
        $db = $this->landlord_db();
        if ($publishedOnly) {
            $db->where('status', 'published');
        }
        return $db->order_by('sort_order', 'asc')->order_by('id', 'desc')->get(db_prefix() . 'kt_landing_blog_posts')->result_array();
    }

    public function save_blog_post(array $data, $id = null)
    {
        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = url_title((string) ($data['title'] ?? ''), '-', true);
        }

        $payload = [
            'title' => (string) ($data['title'] ?? ''),
            'slug' => $slug,
            'excerpt' => (string) ($data['excerpt'] ?? ''),
            'content' => (string) ($data['content'] ?? ''),
            'featured_image' => (string) ($data['featured_image'] ?? ''),
            'category' => (string) ($data['category'] ?? ''),
            'tags' => (string) ($data['tags'] ?? ''),
            'status' => (string) ($data['status'] ?? 'draft'),
            'seo_title' => (string) ($data['seo_title'] ?? ''),
            'seo_description' => (string) ($data['seo_description'] ?? ''),
            'published_at' => !empty($data['published_at']) ? (string) $data['published_at'] : null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $table = db_prefix() . 'kt_landing_blog_posts';
        if ($id) {
            return (bool) $this->landlord_db()->where('id', (int) $id)->update($table, $payload);
        }
        $payload['created_by'] = get_staff_user_id();
        $payload['created_at'] = date('Y-m-d H:i:s');
        return (bool) $this->landlord_db()->insert($table, $payload);
    }

    public function delete_blog_post($id)
    {
        return (bool) $this->landlord_db()->where('id', (int) $id)->delete(db_prefix() . 'kt_landing_blog_posts');
    }

    public function get_leads()
    {
        return $this->landlord_db()->order_by('id', 'desc')->get(db_prefix() . 'kt_landing_leads')->result_array();
    }

    public function save_lead(array $data)
    {
        $db = $this->landlord_db();
        $payload = [
            'name' => (string) ($data['name'] ?? ''),
            'company' => (string) ($data['company'] ?? ''),
            'phone' => (string) ($data['phone'] ?? ''),
            'email' => (string) ($data['email'] ?? ''),
            'message' => (string) ($data['message'] ?? ''),
            'desired_plan_id' => !empty($data['desired_plan_id']) ? (int) $data['desired_plan_id'] : null,
            'source' => (string) ($data['source'] ?? 'contact'),
            'utm_source' => (string) ($data['utm_source'] ?? ''),
            'utm_medium' => (string) ($data['utm_medium'] ?? ''),
            'utm_campaign' => (string) ($data['utm_campaign'] ?? ''),
            'status' => (string) ($data['status'] ?? 'new'),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        return (bool) $db->insert(db_prefix() . 'kt_landing_leads', $payload);
    }

    public function update_lead_status($id, $status)
    {
        $ok = (bool) $this->landlord_db()->where('id', (int) $id)->update(db_prefix() . 'kt_landing_leads', ['status' => (string) $status]);
        if ($ok) {
            $this->add_lead_activity((int) $id, 'status_changed', 'Updated to: ' . (string) $status);
        }
        return $ok;
    }

    public function delete_lead($id)
    {
        return (bool) $this->landlord_db()->where('id', (int) $id)->delete(db_prefix() . 'kt_landing_leads');
    }

    public function add_lead_activity($leadId, $action, $note = '')
    {
        return (bool) $this->landlord_db()->insert(db_prefix() . 'kt_landing_lead_activities', [
            'lead_id' => (int) $leadId,
            'action' => (string) $action,
            'note' => (string) $note,
            'created_by' => function_exists('get_staff_user_id') ? (int) get_staff_user_id() : null,
            'created_at' => $this->now(),
        ]);
    }

    public function get_lead_activities($leadId)
    {
        return $this->landlord_db()->where('lead_id', (int) $leadId)->order_by('id', 'desc')->get(db_prefix() . 'kt_landing_lead_activities')->result_array();
    }

    public function get_pages()
    {
        return $this->landlord_db()->order_by('sort_order', 'asc')->order_by('id', 'asc')->get(db_prefix() . 'kt_landing_pages')->result_array();
    }

    public function get_pages_by_template($templateCode = null)
    {
        $db = $this->landlord_db();
        $table = db_prefix() . 'kt_landing_pages';
        $templateCode = trim((string) $templateCode);
        if ($templateCode !== '') {
            $rows = $db->where('template_code', $templateCode)->order_by('sort_order', 'asc')->order_by('id', 'asc')->get($table)->result_array();
            if (!empty($rows)) {
                return $rows;
            }
        }

        return $db->order_by('sort_order', 'asc')->order_by('id', 'asc')->get($table)->result_array();
    }

    public function save_page(array $data, $id = null)
    {
        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = url_title((string) ($data['title'] ?? ''), '-', true);
        }
        $payload = [
            'title' => (string) ($data['title'] ?? ''),
            'slug' => $slug,
            'template_code' => (string) ($data['template_code'] ?? ''),
            'seo_title' => (string) ($data['seo_title'] ?? ''),
            'seo_description' => (string) ($data['seo_description'] ?? ''),
            'status' => (string) ($data['status'] ?? 'draft'),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'updated_at' => $this->now(),
        ];
        $db = $this->landlord_db();
        $table = db_prefix() . 'kt_landing_pages';
        if ($id) {
            return (bool) $db->where('id', (int) $id)->update($table, $payload);
        }
        $payload['created_at'] = $this->now();
        return (bool) $db->insert($table, $payload);
    }

    public function delete_page($id)
    {
        return (bool) $this->landlord_db()->where('id', (int) $id)->delete(db_prefix() . 'kt_landing_pages');
    }

    public function get_page_seo_registry()
    {
        $registry = $this->get_setting('kt_landing_page_seo_json', []);
        return is_array($registry) ? $registry : [];
    }

    public function save_page_seo_registry(array $registry)
    {
        return $this->set_setting('kt_landing_page_seo_json', $registry, true);
    }

    public function get_page_seo($pageIdOrSlug)
    {
        $registry = $this->get_page_seo_registry();
        if (is_numeric($pageIdOrSlug)) {
            $pageId = (int) $pageIdOrSlug;
            return isset($registry[$pageId]) && is_array($registry[$pageId]) ? $registry[$pageId] : [];
        }

        $page = $this->landlord_db()
            ->where('slug', (string) $pageIdOrSlug)
            ->get(db_prefix() . 'kt_landing_pages')
            ->row_array();

        if (!$page) {
            return [];
        }

        return $this->get_page_seo((int) ($page['id'] ?? 0));
    }

    public function get_page_seo_for_page(array $page)
    {
        $pageId = (int) ($page['id'] ?? 0);
        $pageSeo = $this->get_page_seo($pageId);
        $slug = trim((string) ($page['slug'] ?? ''));
        $title = trim((string) ($page['title'] ?? ''));
        $siteTitle = trim((string) $this->get_setting('site_title', ''));
        $siteDescription = trim((string) $this->get_setting('site_description', ''));
        $defaultOgImage = trim((string) $this->get_setting('default_og_image', ''));
        $canonicalDefault = trim((string) $this->get_setting('canonical_url', ''));

        $metaTitle = trim((string) ($pageSeo['meta_title'] ?? ''));
        if ($metaTitle === '') {
            $metaTitle = trim((string) ($page['seo_title'] ?? ''));
        }
        if ($metaTitle === '') {
            $metaTitle = $title !== '' ? $title : $siteTitle;
        }

        $metaDescription = trim((string) ($pageSeo['meta_description'] ?? ''));
        if ($metaDescription === '') {
            $metaDescription = trim((string) ($page['seo_description'] ?? ''));
        }
        if ($metaDescription === '') {
            $metaDescription = $siteDescription;
        }

        $canonical = trim((string) ($pageSeo['canonical_url'] ?? ''));
        if ($canonical === '' && $slug !== '') {
            $canonical = function_exists('site_url') ? site_url($slug) : base_url($slug);
        }
        if ($canonical === '') {
            $canonical = $canonicalDefault;
        }

        $robotsIndex = trim((string) ($pageSeo['robots_index'] ?? 'index'));
        $robotsFollow = trim((string) ($pageSeo['robots_follow'] ?? 'follow'));
        $ogTitle = trim((string) ($pageSeo['og_title'] ?? ''));
        if ($ogTitle === '') {
            $ogTitle = $metaTitle;
        }
        $ogDescription = trim((string) ($pageSeo['og_description'] ?? ''));
        if ($ogDescription === '') {
            $ogDescription = $metaDescription;
        }
        $ogImage = trim((string) ($pageSeo['og_image'] ?? ''));
        if ($ogImage === '') {
            $ogImage = $defaultOgImage;
        }

        return [
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'canonical_url' => $canonical,
            'robots_index' => in_array($robotsIndex, ['index', 'noindex'], true) ? $robotsIndex : 'index',
            'robots_follow' => in_array($robotsFollow, ['follow', 'nofollow'], true) ? $robotsFollow : 'follow',
            'og_title' => $ogTitle,
            'og_description' => $ogDescription,
            'og_image' => $ogImage,
            'twitter_card' => trim((string) ($pageSeo['twitter_card'] ?? 'summary_large_image')) ?: 'summary_large_image',
            'page_title' => $title,
            'page_slug' => $slug,
            'source' => $pageSeo,
        ];
    }

    public function save_page_seo($pageId, array $data)
    {
        $pageId = (int) $pageId;
        $page = $this->get_page_by_id($pageId);
        if (!$page) {
            return ['success' => false, 'message' => 'Page not found'];
        }

        $registry = $this->get_page_seo_registry();
        $payload = [
            'meta_title' => trim((string) ($data['meta_title'] ?? '')),
            'meta_description' => trim((string) ($data['meta_description'] ?? '')),
            'canonical_url' => trim((string) ($data['canonical_url'] ?? '')),
            'robots_index' => in_array(trim((string) ($data['robots_index'] ?? 'index')), ['index', 'noindex'], true) ? trim((string) ($data['robots_index'] ?? 'index')) : 'index',
            'robots_follow' => in_array(trim((string) ($data['robots_follow'] ?? 'follow')), ['follow', 'nofollow'], true) ? trim((string) ($data['robots_follow'] ?? 'follow')) : 'follow',
            'og_title' => trim((string) ($data['og_title'] ?? '')),
            'og_description' => trim((string) ($data['og_description'] ?? '')),
            'og_image' => trim((string) ($data['og_image'] ?? '')),
            'twitter_card' => trim((string) ($data['twitter_card'] ?? 'summary_large_image')) ?: 'summary_large_image',
        ];

        if ($payload['canonical_url'] !== '') {
            foreach ($registry as $otherPageId => $entry) {
                if ((int) $otherPageId === $pageId || !is_array($entry)) {
                    continue;
                }
                if (trim((string) ($entry['canonical_url'] ?? '')) === $payload['canonical_url']) {
                    $conflictPage = $this->get_page_by_id((int) $otherPageId);
                    return [
                        'success' => false,
                        'message' => 'Canonical URL already used by another page',
                        'conflict_page_id' => (int) $otherPageId,
                        'conflict_page_title' => (string) ($conflictPage['title'] ?? ''),
                        'conflict_page_slug' => (string) ($conflictPage['slug'] ?? ''),
                    ];
                }
            }
        }

        $registry[$pageId] = $payload;
        $saved = $this->save_page_seo_registry($registry);
        return [
            'success' => (bool) $saved,
            'message' => $saved ? 'Page SEO saved' : 'Unable to save page SEO',
            'page_id' => $pageId,
            'page_title' => (string) ($page['title'] ?? ''),
            'page_slug' => (string) ($page['slug'] ?? ''),
            'seo' => $payload,
        ];
    }

    public function get_page_seo_report()
    {
        $pages = $this->get_pages();
        $registry = $this->get_page_seo_registry();
        $media = $this->get_media();
        $report = [
            'health_score' => 100,
            'pages_audited' => count($pages),
            'pages_healthy' => 0,
            'missing_h1_total' => 0,
            'media_missing_alt_total' => 0,
            'broken_references_total' => 0,
            'warnings' => [],
            'critical_issues' => [],
            'issues' => [],
            'pages' => [],
        ];

        $seenCanonicals = [];
        $seenMetaTitles = [];
        $seenMetaDescriptions = [];
        $mediaPaths = [];
        foreach ($media as $row) {
            $mediaPaths[] = trim((string) ($row['file_path'] ?? ''));
            $mediaPaths[] = basename((string) ($row['file_path'] ?? ''));
            $mediaPaths[] = ltrim((string) ($row['file_path'] ?? ''), '/');
            if ((int) ($row['usage_count'] ?? 0) > 0 && trim((string) ($row['alt_text'] ?? '')) === '') {
                $report['media_missing_alt_total']++;
            }
        }

        foreach ($pages as $page) {
            $pageId = (int) ($page['id'] ?? 0);
            $pageSeo = isset($registry[$pageId]) && is_array($registry[$pageId]) ? $registry[$pageId] : [];
            $seo = $this->get_page_seo_for_page($page);
            $pageSlug = (string) ($page['slug'] ?? '');
            $pageSections = $pageSlug !== '' ? $this->get_sections_by_page_key($pageSlug) : [];
            $pageBlockJson = json_encode([
                'page' => $page,
                'sections' => $pageSections,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $issues = [];
            $status = 'PASS';
            $missingH1 = false;
            $hasSectionTitle = false;

            foreach ($pageSections as $section) {
                if (trim((string) ($section['title'] ?? '')) !== '') {
                    $hasSectionTitle = true;
                    break;
                }
            }
            if (!empty($pageSections) && !$hasSectionTitle) {
                $missingH1 = true;
            }

            if ($seo['meta_title'] === '') {
                $issues[] = ['type' => 'CRITICAL', 'code' => 'missing_title', 'message' => 'Missing Title'];
                $report['critical_issues'][] = ['page_id' => $pageId, 'page_slug' => $pageSlug, 'issue' => 'Missing Title'];
                $status = 'CRITICAL';
                $report['health_score'] -= 12;
            }
            if ($seo['meta_description'] === '') {
                $issues[] = ['type' => 'CRITICAL', 'code' => 'missing_description', 'message' => 'Missing Description'];
                $report['critical_issues'][] = ['page_id' => $pageId, 'page_slug' => $pageSlug, 'issue' => 'Missing Description'];
                $status = 'CRITICAL';
                $report['health_score'] -= 12;
            }
            if ($missingH1) {
                $issues[] = ['type' => 'WARNING', 'code' => 'missing_h1', 'message' => 'Missing H1'];
                $report['warnings'][] = ['page_id' => $pageId, 'page_slug' => $pageSlug, 'issue' => 'Missing H1'];
                if ($status !== 'CRITICAL') {
                    $status = 'WARNING';
                }
                $report['health_score'] -= 4;
                $report['missing_h1_total']++;
            }

            $canonical = trim((string) ($seo['canonical_url'] ?? ''));
            if ($canonical !== '') {
                if (isset($seenCanonicals[$canonical])) {
                    $issues[] = ['type' => 'CRITICAL', 'code' => 'duplicate_canonical', 'message' => 'Duplicate canonical'];
                    $report['critical_issues'][] = ['page_id' => $pageId, 'page_slug' => $pageSlug, 'issue' => 'Duplicate canonical'];
                    $status = 'CRITICAL';
                    $report['health_score'] -= 10;
                } else {
                    $seenCanonicals[$canonical] = $pageId;
                }
            }

            $metaTitle = trim((string) ($seo['meta_title'] ?? ''));
            if ($metaTitle !== '') {
                if (isset($seenMetaTitles[$metaTitle])) {
                    $issues[] = ['type' => 'WARNING', 'code' => 'duplicate_meta_title', 'message' => 'Duplicate Meta Title'];
                    $report['warnings'][] = ['page_id' => $pageId, 'page_slug' => $pageSlug, 'issue' => 'Duplicate Meta Title'];
                    if ($status !== 'CRITICAL') {
                        $status = 'WARNING';
                    }
                    $report['health_score'] -= 2;
                } else {
                    $seenMetaTitles[$metaTitle] = $pageId;
                }
            }

            $metaDescription = trim((string) ($seo['meta_description'] ?? ''));
            if ($metaDescription !== '') {
                if (isset($seenMetaDescriptions[$metaDescription])) {
                    $issues[] = ['type' => 'WARNING', 'code' => 'duplicate_meta_description', 'message' => 'Duplicate Meta Description'];
                    $report['warnings'][] = ['page_id' => $pageId, 'page_slug' => $pageSlug, 'issue' => 'Duplicate Meta Description'];
                    if ($status !== 'CRITICAL') {
                        $status = 'WARNING';
                    }
                    $report['health_score'] -= 2;
                } else {
                    $seenMetaDescriptions[$metaDescription] = $pageId;
                }
            }

            if ($seo['robots_index'] === 'noindex' || $seo['robots_follow'] === 'nofollow') {
                $issues[] = ['type' => 'WARNING', 'code' => 'robots_restricted', 'message' => 'Robots restricted'];
                $report['warnings'][] = ['page_id' => $pageId, 'page_slug' => $pageSlug, 'issue' => 'Robots restricted'];
                if ($status !== 'CRITICAL') {
                    $status = 'WARNING';
                }
                $report['health_score'] -= 2;
            }

            if ($seo['og_title'] === '' || $seo['og_description'] === '' || $seo['og_image'] === '') {
                $issues[] = ['type' => 'WARNING', 'code' => 'missing_og', 'message' => 'Missing OpenGraph data'];
                $report['warnings'][] = ['page_id' => $pageId, 'page_slug' => $pageSlug, 'issue' => 'Missing OpenGraph data'];
                if ($status !== 'CRITICAL') {
                    $status = 'WARNING';
                }
                $report['health_score'] -= 2;
            }

            $brokenRefs = 0;
            if (!empty($pageBlockJson) && preg_match_all('#uploads/[A-Za-z0-9_./\\-]+#', (string) $pageBlockJson, $matches)) {
                foreach ($matches[0] as $path) {
                    if (!$this->mediaPathExists($path, $mediaPaths)) {
                        $brokenRefs++;
                    }
                }
            }
            if ($brokenRefs > 0) {
                $issues[] = ['type' => 'CRITICAL', 'code' => 'broken_references', 'message' => 'Broken References'];
                $report['critical_issues'][] = ['page_id' => $pageId, 'page_slug' => $pageSlug, 'issue' => 'Broken References'];
                $status = 'CRITICAL';
                $report['health_score'] -= 10;
                $report['broken_references_total'] += $brokenRefs;
            }

            $missingAlt = (int) ($report['media_missing_alt_total'] ?? 0);
            if ($missingAlt > 0) {
                $issues[] = ['type' => 'WARNING', 'code' => 'missing_alt', 'message' => 'Missing Alt'];
                $report['warnings'][] = ['page_id' => $pageId, 'page_slug' => $pageSlug, 'issue' => 'Missing Alt'];
                if ($status !== 'CRITICAL') {
                    $status = 'WARNING';
                }
                $report['health_score'] -= 3;
            }

            if ($status === 'PASS') {
                $report['pages_healthy']++;
            }

            $report['pages'][] = [
                'page' => $page,
                'seo' => $seo,
                'seo_raw' => $pageSeo,
                'status' => $status,
                'issues' => $issues,
                'meta_title' => (string) $seo['meta_title'],
                'meta_description' => (string) $seo['meta_description'],
                'canonical_url' => (string) $seo['canonical_url'],
                'robots_index' => (string) $seo['robots_index'],
                'robots_follow' => (string) $seo['robots_follow'],
                'og_title' => (string) $seo['og_title'],
                'og_description' => (string) $seo['og_description'],
                'og_image' => (string) $seo['og_image'],
                'twitter_card' => (string) $seo['twitter_card'],
                'missing_h1' => $missingH1,
                'missing_alt_count' => $missingAlt,
                'broken_ref_count' => $brokenRefs,
                'section_count' => count($pageSections),
            ];
        }

        $report['health_score'] = max(0, min(100, (int) $report['health_score']));
        return $report;
    }

    public function get_seo_publish_blockers()
    {
        $report = $this->get_page_seo_report();
        $blockers = [];
        foreach ($report['critical_issues'] as $issue) {
            $blockers[] = [
                'page_id' => (int) ($issue['page_id'] ?? 0),
                'page_slug' => (string) ($issue['page_slug'] ?? ''),
                'type' => 'BLOCKER',
                'message' => (string) ($issue['issue'] ?? ''),
            ];
        }
        return $blockers;
    }

    public function get_page_by_id($id)
    {
        $row = $this->landlord_db()
            ->where('id', (int) $id)
            ->get(db_prefix() . 'kt_landing_pages')
            ->row_array();

        return $row ?: null;
    }

    public function move_page_sort_order($id, $direction)
    {
        $db = $this->landlord_db();
        $table = db_prefix() . 'kt_landing_pages';
        $current = $this->get_page_by_id($id);
        if (!$current) {
            return false;
        }

        $direction = $direction === 'up' ? 'up' : 'down';
        $neighborQuery = $db->where('id !=', (int) $id);
        if ($direction === 'up') {
            $neighbor = $neighborQuery->where('sort_order <', (int) ($current['sort_order'] ?? 0))->order_by('sort_order', 'desc')->order_by('id', 'desc')->get($table)->row_array();
        } else {
            $neighbor = $neighborQuery->where('sort_order >', (int) ($current['sort_order'] ?? 0))->order_by('sort_order', 'asc')->order_by('id', 'asc')->get($table)->row_array();
        }

        if (!$neighbor) {
            return false;
        }

        $currentSort = (int) ($current['sort_order'] ?? 0);
        $neighborSort = (int) ($neighbor['sort_order'] ?? 0);
        $db->where('id', (int) $current['id'])->update($table, ['sort_order' => $neighborSort, 'updated_at' => $this->now()]);
        $db->where('id', (int) $neighbor['id'])->update($table, ['sort_order' => $currentSort, 'updated_at' => $this->now()]);
        return true;
    }

    public function get_media($folder = null)
    {
        $db = $this->landlord_db();
        if ($folder !== null && $folder !== '') {
            $db->where('folder', (string) $folder);
        }
        return $db->order_by('id', 'desc')->get(db_prefix() . 'kt_landing_media')->result_array();
    }

    public function get_media_by_id($id)
    {
        $row = $this->landlord_db()
            ->where('id', (int) $id)
            ->get(db_prefix() . 'kt_landing_media')
            ->row_array();

        return $row ?: null;
    }

    public function save_media(array $data, $id = null)
    {
        $payload = [
            'folder' => (string) ($data['folder'] ?? 'landing'),
            'file_name' => (string) ($data['file_name'] ?? ''),
            'file_path' => (string) ($data['file_path'] ?? ''),
            'file_type' => (string) ($data['file_type'] ?? ''),
            'mime_type' => (string) ($data['mime_type'] ?? ''),
            'file_size' => (int) ($data['file_size'] ?? 0),
            'title' => (string) ($data['title'] ?? ''),
            'alt_text' => (string) ($data['alt_text'] ?? ''),
            'caption' => (string) ($data['caption'] ?? ''),
            'tags' => (string) ($data['tags'] ?? ''),
            'category' => (string) ($data['category'] ?? ''),
            'width' => !empty($data['width']) ? (int) $data['width'] : null,
            'height' => !empty($data['height']) ? (int) $data['height'] : null,
            'usage_count' => isset($data['usage_count']) ? (int) $data['usage_count'] : 0,
            'last_used_at' => array_key_exists('last_used_at', $data) ? ($data['last_used_at'] ?: null) : null,
            'uploaded_by' => function_exists('get_staff_user_id') ? (int) get_staff_user_id() : null,
            'created_at' => $this->now(),
        ];
        $db = $this->landlord_db();
        $table = db_prefix() . 'kt_landing_media';
        if ($id) {
            $payload['created_at'] = $this->get_media_by_id($id)['created_at'] ?? $this->now();
            return (bool) $db->where('id', (int) $id)->update($table, $payload);
        }
        $ok = $db->insert($table, $payload);
        return $ok ? (int) $db->insert_id() : false;
    }

    public function delete_media($id)
    {
        return (bool) $this->landlord_db()->where('id', (int) $id)->delete(db_prefix() . 'kt_landing_media');
    }

    public function get_media_usage($mediaId)
    {
        $table = db_prefix() . 'kt_landing_media_usage';
        $db = $this->landlord_db();
        if (!$db->table_exists($table)) {
            return [];
        }

        return $db
            ->where('media_id', (int) $mediaId)
            ->order_by('usage_type', 'asc')
            ->order_by('usage_ref_type', 'asc')
            ->order_by('usage_ref_id', 'asc')
            ->get($table)
            ->result_array();
    }

    public function replace_media_usage($mediaId, array $usages)
    {
        $table = db_prefix() . 'kt_landing_media_usage';
        $db = $this->landlord_db();
        if (!$db->table_exists($table)) {
            return false;
        }

        $db->where('media_id', (int) $mediaId)->delete($table);
        foreach ($usages as $usage) {
            $db->insert($table, [
                'media_id' => (int) $mediaId,
                'usage_type' => (string) ($usage['usage_type'] ?? 'page'),
                'usage_ref_type' => (string) ($usage['usage_ref_type'] ?? ''),
                'usage_ref_id' => isset($usage['usage_ref_id']) ? (int) $usage['usage_ref_id'] : null,
                'usage_ref_key' => (string) ($usage['usage_ref_key'] ?? ''),
                'usage_label' => (string) ($usage['usage_label'] ?? ''),
                'source_field' => (string) ($usage['source_field'] ?? ''),
                'source_value' => (string) ($usage['source_value'] ?? ''),
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);
        }

        return true;
    }

    public function clear_media_usage($mediaId)
    {
        $table = db_prefix() . 'kt_landing_media_usage';
        $db = $this->landlord_db();
        if (!$db->table_exists($table)) {
            return false;
        }

        return (bool) $db->where('media_id', (int) $mediaId)->delete($table);
    }

    public function record_media_usage($mediaId, array $usage)
    {
        $table = db_prefix() . 'kt_landing_media_usage';
        $db = $this->landlord_db();
        if (!$db->table_exists($table)) {
            return false;
        }

        return (bool) $db->insert($table, [
            'media_id' => (int) $mediaId,
            'usage_type' => (string) ($usage['usage_type'] ?? 'page'),
            'usage_ref_type' => (string) ($usage['usage_ref_type'] ?? ''),
            'usage_ref_id' => isset($usage['usage_ref_id']) ? (int) $usage['usage_ref_id'] : null,
            'usage_ref_key' => (string) ($usage['usage_ref_key'] ?? ''),
            'usage_label' => (string) ($usage['usage_label'] ?? ''),
            'source_field' => (string) ($usage['source_field'] ?? ''),
            'source_value' => (string) ($usage['source_value'] ?? ''),
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ]);
    }

    public function can_delete_media($id)
    {
        $table = db_prefix() . 'kt_landing_media_usage';
        $db = $this->landlord_db();
        if (!$db->table_exists($table)) {
            return true;
        }

        return (int) $db->where('media_id', (int) $id)->count_all_results($table) === 0;
    }

    public function get_media_usage_graph($mediaId)
    {
        $rows = $this->get_media_usage($mediaId);
        $graph = [
            'media_id' => (int) $mediaId,
            'total' => count($rows),
            'by_type' => [],
            'references' => [],
        ];

        foreach ($rows as $row) {
            $type = (string) ($row['usage_type'] ?? 'page');
            if (!isset($graph['by_type'][$type])) {
                $graph['by_type'][$type] = 0;
            }
            $graph['by_type'][$type]++;
            $graph['references'][] = [
                'usage_type' => $type,
                'usage_ref_type' => (string) ($row['usage_ref_type'] ?? ''),
                'usage_ref_id' => (int) ($row['usage_ref_id'] ?? 0),
                'usage_ref_key' => (string) ($row['usage_ref_key'] ?? ''),
                'usage_label' => (string) ($row['usage_label'] ?? ''),
                'source_field' => (string) ($row['source_field'] ?? ''),
                'source_value' => (string) ($row['source_value'] ?? ''),
            ];
        }

        return $graph;
    }

    public function create_publish_snapshot($type, array $payload, $status = 'draft', array $meta = [])
    {
        $table = db_prefix() . 'kt_landing_publish_snapshots';
        $db = $this->landlord_db();
        $summary = $meta['summary_json'] ?? $this->build_publish_snapshot_summary($payload);
        $checklist = $meta['checklist_json'] ?? null;
        $version = isset($meta['snapshot_version']) ? (int) $meta['snapshot_version'] : $this->get_next_publish_snapshot_version($type);
        $payloadRow = [
            'snapshot_name' => (string) ($meta['snapshot_name'] ?? $this->build_publish_snapshot_name($type, $version, $status)),
            'snapshot_type' => (string) $type,
            'snapshot_status' => in_array($status, ['draft', 'published', 'archived'], true) ? $status : 'draft',
            'snapshot_version' => $version,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            'checklist_json' => is_array($checklist) ? json_encode($checklist, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) : (string) $checklist,
            'summary_json' => is_array($summary) ? json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) : (string) $summary,
            'published_by' => function_exists('get_staff_user_id') ? (int) get_staff_user_id() : null,
            'published_at' => $status === 'published' ? $this->now() : null,
            'archived_at' => null,
            'created_at' => $this->now(),
        ];

        if (!$db->table_exists($table)) {
            return false;
        }

        $ok = (bool) $db->insert($table, $payloadRow);
        return $ok ? (int) $db->insert_id() : false;
    }

    public function get_publish_snapshots($limit = 20, $status = null)
    {
        $db = $this->landlord_db();
        $table = db_prefix() . 'kt_landing_publish_snapshots';
        if (!$db->table_exists($table)) {
            return [];
        }

        if ($status !== null && $status !== '') {
            $db->where('snapshot_status', (string) $status);
        }

        return $db->order_by('snapshot_version', 'desc')->order_by('id', 'desc')->limit((int) $limit)->get($table)->result_array();
    }

    public function get_publish_snapshot($id)
    {
        return $this->landlord_db()->where('id', (int) $id)->get(db_prefix() . 'kt_landing_publish_snapshots')->row_array();
    }

    public function get_publish_snapshot_counts()
    {
        $rows = $this->landlord_db()
            ->select('snapshot_status, COUNT(*) AS total', false)
            ->from(db_prefix() . 'kt_landing_publish_snapshots')
            ->group_by('snapshot_status')
            ->get()
            ->result_array();

        $counts = ['draft' => 0, 'published' => 0, 'archived' => 0, 'all' => 0];
        foreach ($rows as $row) {
            $status = (string) ($row['snapshot_status'] ?? 'draft');
            $counts[$status] = (int) ($row['total'] ?? 0);
            $counts['all'] += (int) ($row['total'] ?? 0);
        }

        return $counts;
    }

    public function set_publish_snapshot_status($snapshotId, $status, array $extra = [])
    {
        $status = in_array($status, ['draft', 'published', 'archived'], true) ? $status : 'draft';
        $payload = array_merge([
            'snapshot_status' => $status,
        ], $extra);
        if ($status === 'published' && !array_key_exists('published_at', $payload)) {
            $payload['published_at'] = $this->now();
        }
        if ($status === 'archived' && !array_key_exists('archived_at', $payload)) {
            $payload['archived_at'] = $this->now();
        }
        return (bool) $this->landlord_db()->where('id', (int) $snapshotId)->update(db_prefix() . 'kt_landing_publish_snapshots', $payload);
    }

    public function archive_other_publish_snapshots($snapshotId)
    {
        $db = $this->landlord_db();
        return (bool) $db
            ->where('id !=', (int) $snapshotId)
            ->where('snapshot_status', 'published')
            ->update(db_prefix() . 'kt_landing_publish_snapshots', [
                'snapshot_status' => 'archived',
                'archived_at' => $this->now(),
            ]);
    }

    public function get_next_publish_snapshot_version($type = 'full')
    {
        $row = $this->landlord_db()
            ->select_max('snapshot_version')
            ->where('snapshot_type', (string) $type)
            ->get(db_prefix() . 'kt_landing_publish_snapshots')
            ->row_array();

        return max(1, ((int) ($row['snapshot_version'] ?? 0)) + 1);
    }

    public function build_publish_snapshot_summary(array $payload)
    {
        $pages = isset($payload['pages']) && is_array($payload['pages']) ? $payload['pages'] : [];
        $sections = isset($payload['sections']) && is_array($payload['sections']) ? $payload['sections'] : [];
        $blocks = isset($payload['global_blocks']) && is_array($payload['global_blocks']) ? $payload['global_blocks'] : [];
        $pricing = isset($payload['pricing']) && is_array($payload['pricing']) ? $payload['pricing'] : [];
        $menus = isset($payload['menus']) && is_array($payload['menus']) ? $payload['menus'] : [];

        return [
            'pages' => count($pages),
            'sections' => count($sections),
            'global_blocks' => count($blocks),
            'pricing_overrides' => count($pricing),
            'menus' => count($menus),
            'generated_at' => $this->now(),
        ];
    }

    public function build_publish_snapshot_name($type, $version, $status = 'draft')
    {
        $label = strtoupper(substr((string) $type, 0, 1)) . substr((string) $type, 1);
        return trim($label . ' v' . (int) $version . ' [' . ucfirst((string) $status) . ']');
    }

    public function get_publish_snapshot_payload($id)
    {
        $snapshot = $this->get_publish_snapshot($id);
        if (!$snapshot) {
            return null;
        }

        $payload = json_decode((string) ($snapshot['payload_json'] ?? ''), true);
        return is_array($payload) ? $payload : null;
    }

    public function get_snapshot($id)
    {
        return $this->landlord_db()->where('id', (int) $id)->get(db_prefix() . 'kt_landing_publish_snapshots')->row_array();
    }

    public function apply_snapshot($snapshotId)
    {
        $snapshot = $this->get_snapshot($snapshotId);
        if (!$snapshot) {
            return ['success' => false, 'message' => 'Snapshot not found'];
        }
        $payload = json_decode((string) ($snapshot['payload_json'] ?? ''), true);
        if (!is_array($payload)) {
            return ['success' => false, 'message' => 'Invalid snapshot payload'];
        }

        $db = $this->landlord_db();
        $db->trans_begin();
        try {
            foreach (($payload['settings'] ?? []) as $key => $value) {
                $this->set_setting((string) $key, (string) $value);
            }

            if (isset($payload['sections']) && is_array($payload['sections'])) {
                $db->empty_table(db_prefix() . 'kt_landing_sections');
                foreach ($payload['sections'] as $row) {
                    unset($row['id']);
                    $row['updated_at'] = $this->now();
                    $db->insert(db_prefix() . 'kt_landing_sections', $row);
                }
            }

            if (isset($payload['menus']) && is_array($payload['menus'])) {
                $db->empty_table(db_prefix() . 'kt_landing_menus');
                foreach ($payload['menus'] as $row) {
                    unset($row['id']);
                    $db->insert(db_prefix() . 'kt_landing_menus', $row);
                }
            }

            if (isset($payload['pages']) && is_array($payload['pages'])) {
                $db->empty_table(db_prefix() . 'kt_landing_pages');
                foreach ($payload['pages'] as $row) {
                    unset($row['id']);
                    $row['created_at'] = $row['created_at'] ?? $this->now();
                    $row['updated_at'] = $this->now();
                    $db->insert(db_prefix() . 'kt_landing_pages', $row);
                }
            }

            if (isset($payload['pricing']) && is_array($payload['pricing'])) {
                $db->empty_table(db_prefix() . 'kt_landing_plan_overrides');
                foreach ($payload['pricing'] as $row) {
                    unset($row['id']);
                    $db->insert(db_prefix() . 'kt_landing_plan_overrides', $row);
                }
            }
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }

        if ($db->trans_status() === false) {
            $db->trans_rollback();
            return ['success' => false, 'message' => 'DB transaction failed'];
        }

        $db->trans_commit();
        return ['success' => true];
    }

    public function schedule_publish($snapshotId, $publishAt)
    {
        return (bool) $this->landlord_db()->insert(db_prefix() . 'kt_landing_publish_jobs', [
            'snapshot_id' => (int) $snapshotId,
            'publish_at' => (string) $publishAt,
            'status' => 'queued',
            'created_by' => function_exists('get_staff_user_id') ? (int) get_staff_user_id() : null,
            'created_at' => $this->now(),
        ]);
    }

    public function get_publish_jobs($limit = 100)
    {
        return $this->landlord_db()->order_by('id', 'desc')->limit((int) $limit)->get(db_prefix() . 'kt_landing_publish_jobs')->result_array();
    }

    public function process_due_publish_jobs()
    {
        $db = $this->landlord_db();
        $jobs = $db
            ->where('status', 'queued')
            ->where('publish_at <=', $this->now())
            ->order_by('id', 'asc')
            ->limit(20)
            ->get(db_prefix() . 'kt_landing_publish_jobs')
            ->result_array();

        foreach ($jobs as $job) {
            $result = $this->apply_snapshot((int) ($job['snapshot_id'] ?? 0));
            if (!empty($result['success'])) {
                $snapshotId = (int) ($job['snapshot_id'] ?? 0);
                $snapshot = $this->get_publish_snapshot($snapshotId);
                $this->archive_other_publish_snapshots($snapshotId);
                $this->set_publish_snapshot_status($snapshotId, 'published', [
                    'published_at' => $this->now(),
                ]);
                $this->log_activity('publish.completed', 'success', [
                    'snapshot_id' => $snapshotId,
                    'snapshot_version' => (int) ($snapshot['snapshot_version'] ?? $snapshotId),
                    'source' => 'cron',
                ]);
                $db->where('id', (int) $job['id'])->update(db_prefix() . 'kt_landing_publish_jobs', [
                    'status' => 'done',
                    'processed_at' => $this->now(),
                    'error_message' => null,
                ]);
            } else {
                $db->where('id', (int) $job['id'])->update(db_prefix() . 'kt_landing_publish_jobs', [
                    'status' => 'failed',
                    'processed_at' => $this->now(),
                    'error_message' => (string) ($result['message'] ?? 'apply_snapshot_failed'),
                ]);
            }
        }
    }

    public function track_event($eventName, array $context = [])
    {
        $this->landlord_db()->insert(db_prefix() . 'kt_landing_analytics_events', [
            'event_name' => (string) $eventName,
            'page_slug' => (string) ($context['page_slug'] ?? ''),
            'plan_id' => !empty($context['plan_id']) ? (int) $context['plan_id'] : null,
            'source' => (string) ($context['source'] ?? ''),
            'utm_source' => (string) ($context['utm_source'] ?? ''),
            'utm_medium' => (string) ($context['utm_medium'] ?? ''),
            'utm_campaign' => (string) ($context['utm_campaign'] ?? ''),
            'ip_address' => (string) ($context['ip_address'] ?? ''),
            'created_at' => $this->now(),
        ]);
        return true;
    }

    public function rebuild_daily_analytics($date = null)
    {
        $date = $date ?: date('Y-m-d');
        $eventsTable = db_prefix() . 'kt_landing_analytics_events';
        $dailyTable = db_prefix() . 'kt_landing_analytics_daily';
        $db = $this->landlord_db();
        $rows = $db
            ->select("DATE(created_at) as event_date,event_name,page_slug,plan_id,source,COUNT(*) as total", false)
            ->from($eventsTable)
            ->where('DATE(created_at)=', $date, false)
            ->group_by('DATE(created_at),event_name,page_slug,plan_id,source')
            ->get()
            ->result_array();
        $db->where('event_date', $date)->delete($dailyTable);
        foreach ($rows as $row) {
            $db->insert($dailyTable, $row);
        }
        return true;
    }

    public function get_analytics_overview($days = 30)
    {
        $from = date('Y-m-d', strtotime('-' . (int) $days . ' days'));
        $rows = $this->landlord_db()
            ->select('event_name,COUNT(*) as total', false)
            ->from(db_prefix() . 'kt_landing_analytics_events')
            ->where('DATE(created_at) >=', $from, false)
            ->group_by('event_name')
            ->get()
            ->result_array();
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['event_name']] = (int) $row['total'];
        }
        return $out;
    }

    public function convert_lead_to_perfex_lead($leadId)
    {
        $db = $this->landlord_db();
        $lead = $db->where('id', (int) $leadId)->get(db_prefix() . 'kt_landing_leads')->row_array();
        if (!$lead) {
            return ['success' => false, 'message' => 'Lead not found'];
        }
        if (!$db->table_exists(db_prefix() . 'leads')) {
            return ['success' => false, 'message' => 'Perfex leads table not found'];
        }

        $exists = $db
            ->where('email', (string) ($lead['email'] ?? ''))
            ->where('name', (string) ($lead['name'] ?? ''))
            ->count_all_results(db_prefix() . 'leads');
        if ($exists > 0) {
            return ['success' => false, 'message' => 'Lead already exists in Perfex'];
        }

        $payload = [
            'name' => (string) ($lead['name'] ?? ''),
            'title' => (string) ($lead['company'] ?? ''),
            'email' => (string) ($lead['email'] ?? ''),
            'phonenumber' => (string) ($lead['phone'] ?? ''),
            'description' => (string) ($lead['message'] ?? ''),
            'status' => 1,
            'source' => 1,
            'dateadded' => $this->now(),
        ];

        $ok = (bool) $db->insert(db_prefix() . 'leads', $payload);
        if ($ok) {
            $this->update_lead_status((int) $leadId, 'converted');
            $this->add_lead_activity((int) $leadId, 'converted_to_perfex', 'Converted to tblleads');
            return ['success' => true];
        }

        return ['success' => false, 'message' => 'Insert to Perfex leads failed'];
    }

    public function get_landlord_db()
    {
        return $this->landlord_db();
    }

    private function landlord_db()
    {
        if (is_object($this->landlordDb)) {
            return $this->landlordDb;
        }

        $cfgDb = $this->config->item('kt_saas_landlord_db');
        if (is_object($cfgDb)) {
            $this->landlordDb = $cfgDb;
            return $this->landlordDb;
        }

        $candidateDbNames = [];
        if (defined('APP_DB_NAME')) {
            $candidateDbNames[] = APP_DB_NAME;
        }
        if (defined('APP_DB_NAME') && defined('APP_DB_HOSTNAME') && defined('APP_DB_USERNAME')) {
            $params = [
                'hostname' => defined('APP_DB_HOSTNAME') ? APP_DB_HOSTNAME : 'localhost',
                'username' => defined('APP_DB_USERNAME') ? APP_DB_USERNAME : '',
                'password' => defined('APP_DB_PASSWORD') ? APP_DB_PASSWORD : '',
                'database' => APP_DB_NAME,
                'dbdriver' => defined('APP_DB_DRIVER') ? APP_DB_DRIVER : 'mysqli',
                'dbprefix' => db_prefix(),
                'pconnect' => false,
                'db_debug' => (ENVIRONMENT !== 'production'),
                'cache_on' => false,
                'char_set' => defined('APP_DB_CHARSET') ? APP_DB_CHARSET : 'utf8mb4',
                'dbcollat' => defined('APP_DB_COLLATION') ? APP_DB_COLLATION : 'utf8mb4_unicode_ci',
            ];

            try {
                $candidate = $this->load->database($params, true);
                if ($candidate && $candidate->table_exists(db_prefix() . 'kt_landing_settings')) {
                    $this->landlordDb = $candidate;
                    $this->config->set_item('kt_saas_landlord_db', $candidate);
                    return $this->landlordDb;
                }
            } catch (Throwable $e) {
                log_message('error', 'KT Landing landlord DB resolver failed: ' . $e->getMessage());
            }
        }

        return $this->db;
    }
}
