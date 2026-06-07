<?php

defined('BASEPATH') or exit('No direct script access allowed');

class LandingGlobalBlockService
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        if (!isset($this->CI->Kt_landing_model)) {
            $this->CI->load->model('kt_landing/Kt_landing_model');
        }
        if (!isset($this->CI->Kt_saas_model)) {
            $this->CI->load->model('kt_saas/Kt_saas_model');
        }
    }

    public function createBlock(array $data)
    {
        $normalized = $this->normalizeInput($data);
        if (!$this->isValidContentJson($normalized['content_json'])) {
            return ['success' => false, 'message' => 'Content JSON is invalid'];
        }
        if (!$this->CI->Kt_landing_model->save_global_block($normalized)) {
            return ['success' => false, 'message' => 'Unable to create block'];
        }

        $block = $this->findBlockByKey($normalized['block_key']);
        if ($block) {
            $this->syncUsage((int) $block['id']);
            $this->logBlockEvent('landing.global_block.created', $block, ['action' => 'create']);
        }

        return ['success' => true, 'block' => $block];
    }

    public function updateBlock($id, array $data)
    {
        $existing = $this->CI->Kt_landing_model->get_global_block($id);
        if (!$existing) {
            return ['success' => false, 'message' => 'Block not found'];
        }

        $normalized = $this->normalizeInput($data, $existing);
        if (!$this->isValidContentJson($normalized['content_json'])) {
            return ['success' => false, 'message' => 'Content JSON is invalid'];
        }
        if (!$this->CI->Kt_landing_model->save_global_block($normalized, (int) $id)) {
            return ['success' => false, 'message' => 'Unable to update block'];
        }

        $block = $this->CI->Kt_landing_model->get_global_block($id);
        if ($block) {
            $this->syncUsage((int) $block['id']);
            $this->logBlockEvent('landing.global_block.updated', $block, ['action' => 'update']);
        }

        return ['success' => true, 'block' => $block];
    }

    public function duplicateBlock($id)
    {
        $source = $this->CI->Kt_landing_model->get_global_block($id);
        if (!$source) {
            return ['success' => false, 'message' => 'Block not found'];
        }

        $payload = [
            'block_key' => $this->uniqueDuplicateKey((string) $source['block_key']),
            'block_name' => (string) $source['block_name'] . ' (Copy)',
            'block_type' => (string) $source['block_type'],
            'content_json' => (string) ($source['content_json'] ?? ''),
            'status' => 'active',
        ];

        if (!$this->CI->Kt_landing_model->save_global_block($payload)) {
            return ['success' => false, 'message' => 'Unable to duplicate block'];
        }

        $copy = $this->findBlockByKey($payload['block_key']);
        if ($copy) {
            $this->syncUsage((int) $copy['id']);
            $this->logBlockEvent('landing.global_block.duplicated', $copy, [
                'action' => 'duplicate',
                'source_block_id' => (int) $source['id'],
                'source_block_key' => (string) $source['block_key'],
            ]);
        }

        return ['success' => true, 'block' => $copy];
    }

    public function disableBlock($id)
    {
        $block = $this->CI->Kt_landing_model->get_global_block($id);
        if (!$block) {
            return ['success' => false, 'message' => 'Block not found'];
        }

        $payload = [
            'block_key' => (string) $block['block_key'],
            'block_name' => (string) $block['block_name'],
            'block_type' => (string) $block['block_type'],
            'content_json' => (string) ($block['content_json'] ?? ''),
            'status' => 'disabled',
        ];

        if (!$this->CI->Kt_landing_model->save_global_block($payload, (int) $id)) {
            return ['success' => false, 'message' => 'Unable to disable block'];
        }

        $updated = $this->CI->Kt_landing_model->get_global_block($id);
        $this->logBlockEvent('landing.global_block.disabled', $updated ?: $block, ['action' => 'disable']);
        return ['success' => true, 'block' => $updated ?: $block];
    }

    public function canDeleteBlock($id)
    {
        return (bool) $this->CI->Kt_landing_model->can_delete_global_block($id);
    }

    public function getBlockUsageGraph($id)
    {
        $block = $this->CI->Kt_landing_model->get_global_block($id);
        if (!$block) {
            return [
                'block' => null,
                'total' => 0,
                'by_type' => [],
                'references' => [],
            ];
        }

        return array_merge([
            'block' => $block,
        ], $this->CI->Kt_landing_model->get_global_block_usage_graph((int) $block['id']));
    }

    public function syncUsage($blockId)
    {
        $block = $this->CI->Kt_landing_model->get_global_block($blockId);
        if (!$block) {
            return false;
        }

        $references = $this->discoverReferences((string) $block['block_key']);
        $this->CI->Kt_landing_model->replace_global_block_usage((int) $block['id'], $references);
        return true;
    }

    public function getUsageSummary()
    {
        $blocks = $this->CI->Kt_landing_model->get_global_blocks();
        $summary = [
            'total_blocks' => count($blocks),
            'active_blocks' => 0,
            'disabled_blocks' => 0,
            'usage_rows' => 0,
        ];

        foreach ($blocks as $block) {
            if ((string) ($block['status'] ?? 'active') === 'active') {
                $summary['active_blocks']++;
            } else {
                $summary['disabled_blocks']++;
            }
            $summary['usage_rows'] += (int) $this->CI->Kt_landing_model->get_global_block_usage_graph((int) $block['id'])['total'];
        }

        return $summary;
    }

    private function normalizeInput(array $data, array $existing = [])
    {
        $blockName = trim((string) ($data['block_name'] ?? ($existing['block_name'] ?? '')));
        $blockKeyInput = $existing ? (string) ($existing['block_key'] ?? '') : trim((string) ($data['block_key'] ?? ''));
        if ($blockKeyInput === '') {
            $blockKeyInput = trim((string) ($data['block_key'] ?? ''));
        }
        $blockKey = $this->normalizeBlockKey($blockKeyInput !== '' ? $blockKeyInput : $blockName);
        $content = $data['content_json'] ?? ($existing['content_json'] ?? '');

        if (is_array($content)) {
            $content = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }

        return [
            'block_key' => $blockKey,
            'block_name' => $blockName,
            'block_type' => trim((string) ($data['block_type'] ?? ($existing['block_type'] ?? 'CTA'))) ?: 'CTA',
            'content_json' => (string) $content,
            'status' => in_array(trim((string) ($data['status'] ?? ($existing['status'] ?? 'active'))), ['active', 'disabled'], true)
                ? trim((string) ($data['status'] ?? ($existing['status'] ?? 'active')))
                : 'active',
        ];
    }

    private function isValidContentJson($content)
    {
        $content = trim((string) $content);
        if ($content === '') {
            return true;
        }

        json_decode($content, true);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function findBlockByKey($key)
    {
        return $this->CI->Kt_landing_model->get_global_block($key);
    }

    private function logBlockEvent($eventKey, array $block, array $extra = [])
    {
        if (!isset($this->CI->Kt_saas_model) || !method_exists($this->CI->Kt_saas_model, 'log_activity')) {
            return;
        }

        $context = array_merge([
            'block_id' => (int) ($block['id'] ?? 0),
            'block_key' => (string) ($block['block_key'] ?? ''),
            'block_name' => (string) ($block['block_name'] ?? ''),
            'block_type' => (string) ($block['block_type'] ?? ''),
        ], $extra);

        $this->CI->Kt_saas_model->log_activity($eventKey, 'info', $context);
    }

    private function normalizeBlockKey($key)
    {
        $key = strtolower(trim((string) $key));
        if ($key === '') {
            return '';
        }

        $key = preg_replace('/[^a-z0-9]+/i', '-', $key);
        return trim((string) $key, '-');
    }

    private function uniqueDuplicateKey($baseKey)
    {
        $candidate = $this->normalizeBlockKey($baseKey . '-copy-' . date('YmdHis'));
        $attempt = 0;
        while ($this->CI->Kt_landing_model->get_global_block($candidate)) {
            $attempt++;
            $candidate = $this->normalizeBlockKey($baseKey . '-copy-' . date('YmdHis') . '-' . $attempt);
        }

        return $candidate;
    }

    private function discoverReferences($blockKey)
    {
        $tokens = [
            '{{block:' . $blockKey . '}}',
            '{{global_block:' . $blockKey . '}}',
            '{{landing_block:' . $blockKey . '}}',
            '[[block:' . $blockKey . ']]',
        ];

        $usages = [];
        $usages = array_merge($usages, $this->scanSections($tokens));
        $usages = array_merge($usages, $this->scanSectionItems($tokens));
        $usages = array_merge($usages, $this->scanPages($tokens));
        $usages = array_merge($usages, $this->scanThemes($tokens));
        $usages = array_merge($usages, $this->scanPlanOverrides($tokens));
        $usages = array_merge($usages, $this->scanBlogPosts($tokens));
        $usages = array_merge($usages, $this->scanMenus($tokens));
        $usages = array_merge($usages, $this->scanOtherBlocks($tokens, $blockKey));

        return $usages;
    }

    private function fieldContainsToken(array $row, array $fields, array $tokens)
    {
        foreach ($fields as $field) {
            $value = (string) ($row[$field] ?? '');
            if ($value === '') {
                continue;
            }
            foreach ($tokens as $token) {
                if (stripos($value, $token) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function scanSections(array $tokens)
    {
        $rows = $this->CI->Kt_landing_model->get_sections();
        $out = [];
        foreach ($rows as $row) {
            if (!$this->fieldContainsToken($row, ['title', 'subtitle', 'content', 'image', 'icon', 'button_text', 'button_url', 'settings_json'], $tokens)) {
                continue;
            }
            $out[] = $this->usageRow('section', 'section', $row['id'] ?? null, ($row['page_key'] ?? 'home') . ':' . ($row['section_key'] ?? ''), trim((string) ($row['title'] ?? $row['section_key'] ?? 'Section')), 'content_json');
        }
        return $out;
    }

    private function scanSectionItems(array $tokens)
    {
        $db = $this->CI->Kt_landing_model->get_landlord_db();
        $table = db_prefix() . 'kt_landing_section_items';
        if (!$db->table_exists($table)) {
            return [];
        }

        $rows = $db->get($table)->result_array();
        $out = [];
        foreach ($rows as $row) {
            if (!$this->fieldContainsToken($row, ['title', 'subtitle', 'content', 'icon', 'image', 'badge', 'button_text', 'button_url', 'settings_json'], $tokens)) {
                continue;
            }
            $out[] = $this->usageRow('section', 'section_item', $row['id'] ?? null, (string) ($row['section_id'] ?? '') . ':' . (string) ($row['item_key'] ?? ''), trim((string) ($row['title'] ?? $row['item_key'] ?? 'Section Item')), 'content_json');
        }
        return $out;
    }

    private function scanPages(array $tokens)
    {
        $rows = $this->CI->Kt_landing_model->get_pages();
        $out = [];
        foreach ($rows as $row) {
            if (!$this->fieldContainsToken($row, ['title', 'seo_title', 'seo_description'], $tokens)) {
                continue;
            }
            $out[] = $this->usageRow('page', 'page', $row['id'] ?? null, (string) ($row['slug'] ?? ''), trim((string) ($row['title'] ?? $row['slug'] ?? 'Page')), 'seo');
        }
        return $out;
    }

    private function scanThemes(array $tokens)
    {
        $rows = $this->CI->Kt_landing_model->get_themes();
        $out = [];
        foreach ($rows as $row) {
            if (!$this->fieldContainsToken($row, ['code', 'name', 'description'], $tokens)) {
                continue;
            }
            $out[] = $this->usageRow('template', 'theme', $row['id'] ?? null, (string) ($row['code'] ?? ''), trim((string) ($row['name'] ?? $row['code'] ?? 'Theme')), 'theme');
        }
        return $out;
    }

    private function scanPlanOverrides(array $tokens)
    {
        $rows = $this->CI->Kt_landing_model->get_plan_overrides();
        $out = [];
        foreach ($rows as $row) {
            if (!$this->fieldContainsToken($row, ['marketing_title', 'marketing_description', 'badge_text', 'cta_text', 'cta_url'], $tokens)) {
                continue;
            }
            $out[] = $this->usageRow('landing', 'pricing_override', $row['id'] ?? null, (string) ($row['plan_id'] ?? ''), 'Plan ' . (string) ($row['plan_id'] ?? ''), 'pricing_override');
        }
        return $out;
    }

    private function scanBlogPosts(array $tokens)
    {
        $rows = $this->CI->Kt_landing_model->get_blog_posts();
        $out = [];
        foreach ($rows as $row) {
            if (!$this->fieldContainsToken($row, ['title', 'excerpt', 'content', 'seo_title', 'seo_description'], $tokens)) {
                continue;
            }
            $out[] = $this->usageRow('landing', 'blog_post', $row['id'] ?? null, (string) ($row['slug'] ?? ''), trim((string) ($row['title'] ?? $row['slug'] ?? 'Blog Post')), 'content');
        }
        return $out;
    }

    private function scanMenus(array $tokens)
    {
        $rows = $this->CI->Kt_landing_model->get_menus();
        $out = [];
        foreach ($rows as $row) {
            if (!$this->fieldContainsToken($row, ['label', 'url', 'group_name'], $tokens)) {
                continue;
            }
            $out[] = $this->usageRow('landing', 'menu', $row['id'] ?? null, (string) ($row['menu_area'] ?? ''), trim((string) ($row['label'] ?? 'Menu')), 'menu');
        }
        return $out;
    }

    private function scanOtherBlocks(array $tokens, $currentBlockKey)
    {
        $rows = $this->CI->Kt_landing_model->get_global_blocks();
        $out = [];
        foreach ($rows as $row) {
            if ((string) ($row['block_key'] ?? '') === (string) $currentBlockKey) {
                continue;
            }
            if (!$this->fieldContainsToken($row, ['content_json', 'block_name', 'block_key', 'block_type'], $tokens)) {
                continue;
            }
            $out[] = $this->usageRow('block', 'global_block', $row['id'] ?? null, (string) ($row['block_key'] ?? ''), trim((string) ($row['block_name'] ?? 'Global Block')), 'content_json');
        }
        return $out;
    }

    private function usageRow($usageType, $refType, $refId, $refKey, $label, $sourceField)
    {
        return [
            'usage_type' => $usageType,
            'usage_ref_type' => $refType,
            'usage_ref_id' => $refId !== null ? (int) $refId : null,
            'usage_ref_key' => (string) $refKey,
            'usage_label' => (string) $label,
            'source_field' => (string) $sourceField,
            'is_primary' => 0,
        ];
    }
}
