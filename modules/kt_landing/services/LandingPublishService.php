<?php

defined('BASEPATH') or exit('No direct script access allowed');

class LandingPublishService
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

    public function buildDashboard($status = null)
    {
        $status = $this->normalizeStatusAllowAll($status);
        $snapshots = $this->CI->Kt_landing_model->get_publish_snapshots(100, $status);
        $counts = $this->CI->Kt_landing_model->get_publish_snapshot_counts();

        foreach ($snapshots as &$snapshot) {
            $payload = $this->decodePayload((string) ($snapshot['payload_json'] ?? ''));
            $snapshot['summary'] = $this->CI->Kt_landing_model->build_publish_snapshot_summary($payload);
            $snapshot['checklist'] = $this->decodePayload((string) ($snapshot['checklist_json'] ?? ''));
            $snapshot['summary_data'] = $this->decodePayload((string) ($snapshot['summary_json'] ?? ''));
        }

        return [
            'filters' => ['draft', 'published', 'archived'],
            'active_filter' => $status ?: 'all',
            'counts' => $counts,
            'snapshots' => $snapshots,
            'jobs' => $this->CI->Kt_landing_model->get_publish_jobs(100),
        ];
    }

    public function buildPreviewData($snapshotId = null)
    {
        $snapshot = null;
        if ($snapshotId !== null && (int) $snapshotId > 0) {
            $snapshot = $this->CI->Kt_landing_model->get_publish_snapshot((int) $snapshotId);
        }
        if (!$snapshot) {
            $snapshot = $this->CI->Kt_landing_model->get_publish_snapshots(1, 'published');
            $snapshot = !empty($snapshot) ? $snapshot[0] : null;
        }

        if (!$snapshot) {
            return [
                'snapshot' => null,
                'payload' => [],
                'summary' => ['pages' => 0, 'sections' => 0, 'global_blocks' => 0, 'pricing_overrides' => 0, 'menus' => 0],
                'checklist' => $this->buildPublishChecklist([]),
            ];
        }

        $payload = $this->decodePayload((string) ($snapshot['payload_json'] ?? ''));
        return [
            'snapshot' => $snapshot,
            'payload' => $payload,
            'summary' => $this->CI->Kt_landing_model->build_publish_snapshot_summary($payload),
            'checklist' => $this->buildPublishChecklist($payload),
        ];
    }

    public function createSnapshot(array $payload = [], $status = 'draft', array $meta = [])
    {
        $payload = $payload ?: $this->buildPublishPayload();
        $checklist = $this->buildPublishChecklist($payload);
        $status = $this->normalizeStatus($status);
        if ($status === 'published' && !empty($checklist['has_fail'])) {
            $status = 'draft';
        }

        $version = $this->CI->Kt_landing_model->get_next_publish_snapshot_version('full');
        $snapshotId = $this->CI->Kt_landing_model->create_publish_snapshot('full', $payload, $status, [
            'snapshot_version' => $version,
            'snapshot_name' => $meta['snapshot_name'] ?? $this->CI->Kt_landing_model->build_publish_snapshot_name('full', $version, $status),
            'checklist_json' => $checklist,
            'summary_json' => $this->CI->Kt_landing_model->build_publish_snapshot_summary($payload),
        ]);

        if (!$snapshotId) {
            return ['success' => false, 'message' => 'Unable to create publish snapshot'];
        }

        $snapshot = $this->CI->Kt_landing_model->get_publish_snapshot((int) $snapshotId);
        $this->logEvent('publish.created', 'info', [
            'snapshot_id' => (int) $snapshotId,
            'snapshot_version' => (int) ($snapshot['snapshot_version'] ?? $version),
            'snapshot_status' => (string) ($snapshot['snapshot_status'] ?? $status),
        ]);

        if (!empty($checklist['has_warning']) || !empty($checklist['has_fail'])) {
            $this->logEvent('publish.validation_warning', !empty($checklist['has_fail']) ? 'warning' : 'info', [
                'snapshot_id' => (int) $snapshotId,
                'issues' => $checklist['issues'] ?? [],
            ]);
        }

        if ($status === 'published') {
            $this->CI->Kt_landing_model->archive_other_publish_snapshots((int) $snapshotId);
            $this->CI->Kt_landing_model->set_publish_snapshot_status((int) $snapshotId, 'published', [
                'published_at' => $this->now(),
            ]);
            $this->logEvent('publish.completed', 'success', [
                'snapshot_id' => (int) $snapshotId,
                'snapshot_version' => (int) ($snapshot['snapshot_version'] ?? $version),
            ]);
        }

        return [
            'success' => true,
            'snapshot_id' => (int) $snapshotId,
            'snapshot' => $this->CI->Kt_landing_model->get_publish_snapshot((int) $snapshotId),
            'checklist' => $checklist,
        ];
    }

    public function publishSnapshot($snapshotId)
    {
        $snapshot = $this->CI->Kt_landing_model->get_publish_snapshot((int) $snapshotId);
        if (!$snapshot) {
            return ['success' => false, 'message' => 'Snapshot not found'];
        }

        $payload = $this->decodePayload((string) ($snapshot['payload_json'] ?? ''));
        $checklist = $this->buildPublishChecklist($payload);
        if (!empty($checklist['has_fail'])) {
            $this->logEvent('publish.validation_warning', 'warning', [
                'snapshot_id' => (int) $snapshotId,
                'issues' => $checklist['issues'] ?? [],
            ]);
            return ['success' => false, 'message' => 'Publish checklist has blocking issues', 'checklist' => $checklist];
        }

        $result = $this->CI->Kt_landing_model->apply_snapshot((int) $snapshotId);
        if (empty($result['success'])) {
            return $result;
        }

        $this->CI->Kt_landing_model->archive_other_publish_snapshots((int) $snapshotId);
        $this->CI->Kt_landing_model->set_publish_snapshot_status((int) $snapshotId, 'published', [
            'published_at' => $this->now(),
        ]);
        $this->logEvent('publish.completed', 'success', [
            'snapshot_id' => (int) $snapshotId,
            'snapshot_version' => (int) ($snapshot['snapshot_version'] ?? $snapshotId),
        ]);

        return ['success' => true, 'snapshot' => $snapshot, 'checklist' => $checklist];
    }

    public function rollbackSnapshot($snapshotId)
    {
        $result = $this->publishSnapshot($snapshotId);
        if (!empty($result['success'])) {
            $snapshot = $this->CI->Kt_landing_model->get_publish_snapshot((int) $snapshotId);
            $this->logEvent('publish.rollback', 'warning', [
                'snapshot_id' => (int) $snapshotId,
                'snapshot_version' => (int) ($snapshot['snapshot_version'] ?? $snapshotId),
            ]);
        }
        return $result;
    }

    public function buildPublishPayload()
    {
        return [
            'settings' => $this->CI->Kt_landing_model->get_settings_map([
                'default_template', 'landing_enabled', 'homepage_mode', 'site_title', 'site_description',
                'contact_email', 'contact_phone', 'company_address', 'default_language', 'default_currency',
                'enable_blog', 'enable_contact_form', 'enable_public_signup', 'enable_pricing',
                'enable_addons', 'enable_seo', 'maintenance_mode',
            ]),
            'pages' => $this->CI->Kt_landing_model->get_pages(),
            'sections' => $this->CI->Kt_landing_model->get_sections(),
            'menus' => $this->CI->Kt_landing_model->get_menus(),
            'pricing' => $this->CI->Kt_landing_model->get_plan_overrides(),
            'global_blocks' => $this->CI->Kt_landing_model->get_global_blocks(),
            'media' => $this->CI->Kt_landing_model->get_media(),
        ];
    }

    public function buildPublishChecklist(array $payload)
    {
        $issues = [];
        $checkpoints = [];
        $seoReport = $this->CI->Kt_landing_model->get_page_seo_report();

        $pages = (array) ($payload['pages'] ?? []);
        $sections = (array) ($payload['sections'] ?? []);
        $media = (array) ($payload['media'] ?? []);
        $globalBlocks = (array) ($payload['global_blocks'] ?? []);
        $settings = (array) ($payload['settings'] ?? []);

        $missingMeta = 0;
        foreach ($pages as $page) {
            $title = trim((string) ($page['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $missingTitle = trim((string) ($page['seo_title'] ?? '')) === '';
            $missingDescription = trim((string) ($page['seo_description'] ?? '')) === '';
            if ($missingTitle || $missingDescription) {
                $missingMeta++;
            }
        }
        $checkpoints[] = $this->checkpoint('SEO metadata', $missingMeta === 0 ? 'pass' : 'warning', $missingMeta === 0 ? 'All pages have SEO title and description.' : $missingMeta . ' page(s) are missing SEO title or description.');
        if ($missingMeta > 0) {
            $issues[] = 'Missing SEO title/description on ' . $missingMeta . ' page(s).';
        }

        $seoCritical = count((array) ($seoReport['critical_issues'] ?? []));
        $seoWarnings = count((array) ($seoReport['warnings'] ?? []));
        $checkpoints[] = $this->checkpoint(
            'SEO Center',
            $seoCritical > 0 ? 'fail' : ($seoWarnings > 0 ? 'warning' : 'pass'),
            $seoCritical > 0
                ? $seoCritical . ' critical SEO issue(s) detected.'
                : ($seoWarnings > 0 ? $seoWarnings . ' SEO warning(s) detected.' : 'SEO checks are clean.')
        );
        if ($seoCritical > 0) {
            $issues[] = 'SEO Center has blocking issues.';
            $this->logEvent('seo.publish_blocked', 'warning', [
                'critical_count' => $seoCritical,
                'warning_count' => $seoWarnings,
                'issues' => array_slice((array) ($seoReport['critical_issues'] ?? []), 0, 20),
            ]);
        } elseif ($seoWarnings > 0) {
            $this->logEvent('seo.warning', 'info', [
                'warning_count' => $seoWarnings,
                'issues' => array_slice((array) ($seoReport['warnings'] ?? []), 0, 20),
            ]);
        }

        $missingAlt = 0;
        $usedMedia = 0;
        foreach ($media as $row) {
            if ((int) ($row['usage_count'] ?? 0) > 0) {
                $usedMedia++;
                if (trim((string) ($row['alt_text'] ?? '')) === '') {
                    $missingAlt++;
                }
                $filePath = FCPATH . ltrim((string) ($row['file_path'] ?? ''), '/');
                if ($filePath !== FCPATH && !is_file($filePath)) {
                    $issues[] = 'Missing media file: ' . (string) ($row['file_path'] ?? '');
                }
            }
        }
        $checkpoints[] = $this->checkpoint('Media alt text', $missingAlt === 0 ? 'pass' : 'warning', $missingAlt === 0 ? 'All used media have alt text.' : $missingAlt . ' used media item(s) are missing alt text.');
        if ($missingAlt > 0) {
            $issues[] = $missingAlt . ' used media item(s) are missing alt text.';
        }

        $hasCta = false;
        foreach ($sections as $section) {
            if (trim((string) ($section['button_text'] ?? '')) !== '' || trim((string) ($section['button_url'] ?? '')) !== '') {
                $hasCta = true;
                break;
            }
        }
        foreach ($globalBlocks as $block) {
            $content = $this->decodePayload((string) ($block['content_json'] ?? ''));
            $json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (stripos((string) $json, 'cta') !== false || stripos((string) $json, 'button') !== false) {
                $hasCta = true;
                break;
            }
        }
        $checkpoints[] = $this->checkpoint('CTA presence', $hasCta ? 'pass' : 'fail', $hasCta ? 'At least one CTA is present in sections or blocks.' : 'No CTA content detected.');
        if (!$hasCta) {
            $issues[] = 'Missing CTA content.';
        }

        $brokenRefs = 0;
        $mediaPaths = [];
        foreach ($media as $row) {
            $mediaPaths[] = (string) ($row['file_path'] ?? '');
            $mediaPaths[] = base_url(ltrim((string) ($row['file_path'] ?? ''), '/'));
            $mediaPaths[] = basename((string) ($row['file_path'] ?? ''));
        }
        foreach (['pages', 'sections', 'menus', 'pricing', 'global_blocks'] as $bucket) {
            foreach ((array) ($payload[$bucket] ?? []) as $row) {
                $text = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($text === false) {
                    continue;
                }
                if (preg_match_all('#uploads/[A-Za-z0-9_./\\-]+#', $text, $matches)) {
                    foreach ($matches[0] as $path) {
                        if (!$this->mediaPathExists($path, $mediaPaths)) {
                            $brokenRefs++;
                        }
                    }
                }
            }
        }
        $checkpoints[] = $this->checkpoint('Broken references', $brokenRefs === 0 ? 'pass' : 'fail', $brokenRefs === 0 ? 'No broken media references detected.' : $brokenRefs . ' broken media reference(s) detected.');
        if ($brokenRefs > 0) {
            $issues[] = $brokenRefs . ' broken media reference(s) detected.';
        }

        $missingSettings = 0;
        if (trim((string) ($settings['site_title'] ?? '')) === '') {
            $missingSettings++;
        }
        if (trim((string) ($settings['site_description'] ?? '')) === '') {
            $missingSettings++;
        }
        $checkpoints[] = $this->checkpoint('Site basics', $missingSettings === 0 ? 'pass' : 'warning', $missingSettings === 0 ? 'Site title and description are present.' : 'Site title or description is missing.');
        if ($missingSettings > 0) {
            $issues[] = 'Site title or description missing.';
        }

        $hasWarning = false;
        foreach ($checkpoints as $checkpoint) {
            if (($checkpoint['status'] ?? '') === 'warning') {
                $hasWarning = true;
            }
        }

        return [
            'items' => $checkpoints,
            'issues' => $issues,
            'has_warning' => $hasWarning,
            'has_fail' => in_array('fail', array_column($checkpoints, 'status'), true),
            'summary' => [
                'pages' => count($pages),
                'sections' => count($sections),
                'media' => count($media),
                'used_media' => $usedMedia,
                'global_blocks' => count($globalBlocks),
            ],
        ];
    }

    public function now()
    {
        return date('Y-m-d H:i:s');
    }

    private function checkpoint($label, $status, $message)
    {
        return [
            'label' => (string) $label,
            'status' => in_array($status, ['pass', 'warning', 'fail'], true) ? $status : 'warning',
            'message' => (string) $message,
        ];
    }

    private function normalizeStatus($status)
    {
        $status = strtolower(trim((string) $status));
        return in_array($status, ['draft', 'published', 'archived'], true) ? $status : 'draft';
    }

    private function normalizeStatusAllowAll($status)
    {
        $status = strtolower(trim((string) $status));
        return in_array($status, ['draft', 'published', 'archived'], true) ? $status : null;
    }

    private function decodePayload($json)
    {
        $decoded = json_decode((string) $json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function mediaPathExists($needle, array $mediaPaths)
    {
        $needle = trim((string) $needle);
        if ($needle === '') {
            return true;
        }

        foreach ($mediaPaths as $path) {
            if ($path === $needle || basename($path) === basename($needle) || strpos($needle, basename($path)) !== false) {
                return true;
            }
        }

        return false;
    }

    private function logEvent($eventKey, $severity, array $context = [])
    {
        if (!isset($this->CI->Kt_saas_model) || !method_exists($this->CI->Kt_saas_model, 'log_activity')) {
            return;
        }

        $this->CI->Kt_saas_model->log_activity($eventKey, $severity, $context);
    }
}
