<?php

defined('BASEPATH') or exit('No direct script access allowed');

class LandingPricingSyncService
{
    /** @var CI_Controller */
    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model(KT_LANDING_MODULE . '/Kt_landing_model');
        $this->CI->load->model('kt_saas/Kt_saas_model');
    }

    public function buildPricingSyncReport()
    {
        $plans = $this->CI->Kt_saas_model->get_public_plans();
        $rows = [];
        $summary = [
            'synced' => 0,
            'warning' => 0,
            'mismatch' => 0,
            'total' => 0,
        ];

        foreach ($plans as $plan) {
            $state = $this->getPlanSyncState($plan);
            $rows[] = $state;
            $summary['total']++;
            $summary[$state['sync_state']] = ($summary[$state['sync_state']] ?? 0) + 1;
        }

        usort($rows, static function (array $a, array $b) {
            $aOrder = (int) ($a['landing_sort_order'] ?? 0);
            $bOrder = (int) ($b['landing_sort_order'] ?? 0);
            if ($aOrder === $bOrder) {
                return ((int) ($a['display_order'] ?? 0)) <=> ((int) ($b['display_order'] ?? 0));
            }
            return $aOrder <=> $bOrder;
        });

        return [
            'summary' => $summary,
            'rows' => $rows,
        ];
    }

    public function getPlanSyncState($planOrId)
    {
        $plan = is_array($planOrId) ? $planOrId : $this->CI->Kt_saas_model->get_plan((int) $planOrId);
        if (!$plan) {
            return [
                'sync_state' => 'warning',
                'sync_label' => 'Warning',
                'sync_badge' => 'warning',
                'sync_reasons' => ['CRM plan not found'],
            ];
        }

        $override = $this->CI->Kt_landing_model->get_plan_override((int) $plan['id']) ?: [];
        $snapshot = $this->buildSourceSnapshot($plan);
        $currentHash = $this->hashSnapshot($snapshot);
        $storedHash = trim((string) ($override['source_plan_snapshot_hash'] ?? ''));
        $hasOverride = !empty($override);
        $hasSnapshot = $storedHash !== '' && !empty($override['source_plan_snapshot_json']);

        $state = 'synced';
        $label = 'Synced';
        $badge = 'success';
        $reasons = [];

        if ($hasOverride && !$hasSnapshot) {
            $state = 'warning';
            $label = 'Warning';
            $badge = 'warning';
            $reasons[] = 'Landing override exists but no sync snapshot was saved yet.';
        } elseif ($hasSnapshot && $storedHash !== $currentHash) {
            $state = 'mismatch';
            $label = 'Mismatch';
            $badge = 'danger';
            $reasons[] = 'CRM plan changed after the last landing sync.';
        } elseif ($hasOverride) {
            $reasons[] = 'Landing override is synced to current CRM plan.';
        } else {
            $reasons[] = 'Landing uses CRM plan directly.';
        }

        $resolved = $this->resolvePlanForLanding($plan, $override);

        return array_merge($resolved, [
            'source_plan_snapshot' => $snapshot,
            'source_plan_snapshot_hash' => $currentHash,
            'stored_snapshot_hash' => $storedHash,
            'source_plan_updated_at' => (string) ($override['source_plan_updated_at'] ?? ''),
            'last_synced_at' => (string) ($override['last_synced_at'] ?? ''),
            'sync_state' => $state,
            'sync_label' => $label,
            'sync_badge' => $badge,
            'sync_reasons' => $reasons,
            'has_override' => $hasOverride,
        ]);
    }

    public function detectMismatch(array $state)
    {
        return (string) ($state['sync_state'] ?? '') === 'mismatch';
    }

    public function resolvePlanForLanding(array $plan, array $override = null)
    {
        $override = $override ?: [];
        if (!empty($override) && (int) ($override['is_visible'] ?? 1) !== 1) {
            return [];
        }

        $plan['landing_marketing_title'] = '';
        $plan['landing_marketing_subtitle'] = '';
        $plan['landing_marketing_description'] = '';
        $plan['landing_badge_text'] = '';
        $plan['landing_cta_text'] = '';
        $plan['landing_cta_url'] = '';
        $plan['landing_featured'] = 0;
        $plan['landing_sort_order'] = 0;

        if (!empty($override)) {
            if (!empty($override['marketing_title'])) {
                $plan['landing_marketing_title'] = $this->sanitizeMarketingText($override['marketing_title']);
            }
            $plan['landing_marketing_subtitle'] = $this->sanitizeMarketingText($override['marketing_subtitle'] ?? '');
            if (!empty($override['marketing_description'])) {
                $plan['landing_marketing_description'] = $this->sanitizeMarketingText($override['marketing_description']);
            }
            $plan['landing_badge_text'] = $this->sanitizeMarketingText($override['badge_text'] ?? '');
            $plan['landing_cta_text'] = $this->sanitizeMarketingText($override['cta_text'] ?? '');
            $plan['landing_cta_url'] = trim((string) ($override['cta_url'] ?? ''));
            $plan['landing_featured'] = (int) ($override['is_featured'] ?? 0);
            $plan['landing_sort_order'] = (int) ($override['sort_order'] ?? 0);
        }

        $plan['pricing_sync_state'] = 'synced';
        $plan['pricing_sync_label'] = 'Synced';
        $plan['pricing_sync_badge'] = 'success';
        if (!empty($override)) {
            $plan['pricing_sync_state'] = 'synced';
        }

        return $plan;
    }

    public function saveOverride($planId, array $input)
    {
        $planId = (int) $planId;
        if ($planId <= 0) {
            return ['success' => false, 'message' => 'Invalid plan id'];
        }

        $blocked = $this->extractBlockedFields($input);
        $allowed = $this->extractAllowedFields($input);
        $saved = $this->CI->Kt_landing_model->save_plan_override($planId, $allowed);
        if (!$saved) {
            return ['success' => false, 'message' => 'Failed to save pricing override'];
        }

        $plan = $this->CI->Kt_saas_model->get_plan($planId);
        if ($plan) {
            $this->writeSnapshot($planId, $plan);
        }

        $state = $this->getPlanSyncState($planId);
        $this->logPricingActivity('pricing.override_updated', 'info', $planId, $state, [
            'blocked_fields' => $blocked,
            'override_fields' => array_keys($allowed),
        ]);

        if (!empty($blocked)) {
            $this->logPricingActivity('pricing.warning', 'warning', $planId, $state, [
                'blocked_fields' => $blocked,
            ]);
        } elseif ($this->detectMismatch($state)) {
            $this->logPricingActivity('pricing.mismatch', 'warning', $planId, $state, []);
        } else {
            $this->logPricingActivity('pricing.sync', 'info', $planId, $state, []);
        }

        return [
            'success' => true,
            'message' => 'Saved pricing override',
            'state' => $state,
            'blocked_fields' => $blocked,
        ];
    }

    public function syncPlanOverride($planId)
    {
        $planId = (int) $planId;
        $plan = $this->CI->Kt_saas_model->get_plan($planId);
        if (!$plan) {
            return ['success' => false, 'message' => 'CRM plan not found'];
        }

        $override = $this->CI->Kt_landing_model->get_plan_override($planId) ?: [];
        $payload = $override;
        $snapshot = $this->buildSourceSnapshot($plan);
        $payload['source_plan_snapshot_hash'] = $this->hashSnapshot($snapshot);
        $payload['source_plan_snapshot_json'] = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $payload['source_plan_updated_at'] = (string) ($plan['updated_at'] ?? date('Y-m-d H:i:s'));
        $payload['last_synced_at'] = date('Y-m-d H:i:s');

        $ok = $this->CI->Kt_landing_model->save_plan_override($planId, $payload);
        if (!$ok) {
            return ['success' => false, 'message' => 'Failed to sync pricing override'];
        }

        $state = $this->getPlanSyncState($planId);
        $this->logPricingActivity('pricing.sync', 'info', $planId, $state, []);

        return [
            'success' => true,
            'message' => 'Pricing synced',
            'state' => $state,
        ];
    }

    private function writeSnapshot($planId, array $plan)
    {
        $override = $this->CI->Kt_landing_model->get_plan_override($planId) ?: [];
        $snapshot = $this->buildSourceSnapshot($plan);
        $currentHash = $this->hashSnapshot($snapshot);
        $storedHash = trim((string) ($override['source_plan_snapshot_hash'] ?? ''));
        if ($storedHash !== '') {
            return true;
        }

        $payload = $override;
        $payload['source_plan_snapshot_hash'] = $currentHash;
        $payload['source_plan_snapshot_json'] = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $payload['source_plan_updated_at'] = (string) ($plan['updated_at'] ?? date('Y-m-d H:i:s'));
        $payload['last_synced_at'] = date('Y-m-d H:i:s');
        return (bool) $this->CI->Kt_landing_model->save_plan_override($planId, $payload);
    }

    private function buildSourceSnapshot(array $plan)
    {
        return [
            'plan_id' => (int) ($plan['id'] ?? 0),
            'plan_code' => (string) ($plan['plan_code'] ?? ''),
            'price' => (float) ($plan['price'] ?? 0),
            'setup_fee' => (float) ($plan['setup_fee'] ?? 0),
            'billing_cycle' => (string) ($plan['billing_cycle'] ?? 'monthly'),
            'trial_days' => (int) ($plan['trial_days'] ?? 0),
            'currency' => (string) ($plan['currency'] ?? 'VND'),
            'is_public' => (int) ($plan['is_public'] ?? 0),
            'is_active' => (int) ($plan['is_active'] ?? 0),
            'updated_at' => (string) ($plan['updated_at'] ?? ''),
        ];
    }

    private function hashSnapshot(array $snapshot)
    {
        return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function extractAllowedFields(array $input)
    {
        $fields = [
            'marketing_title',
            'marketing_subtitle',
            'marketing_description',
            'badge_text',
            'cta_text',
            'cta_url',
            'is_visible',
            'is_featured',
            'sort_order',
        ];

        $payload = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $input)) {
                $payload[$field] = $input[$field];
            }
        }

        return $payload;
    }

    private function extractBlockedFields(array $input)
    {
        $blocked = [];
        foreach (['price', 'setup_fee', 'billing_cycle', 'trial_days', 'plan_code'] as $field) {
            if (array_key_exists($field, $input) && trim((string) $input[$field]) !== '') {
                $blocked[] = $field;
            }
        }
        return $blocked;
    }

    private function sanitizeMarketingText($value)
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

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

        $normalized = strtolower($text);
        foreach ($markers as $marker) {
            if (strpos($normalized, $marker) !== false) {
                return '';
            }
        }

        return $text;
    }

    private function logPricingActivity($eventKey, $severity, $planId, array $state, array $context = [])
    {
        if (!method_exists($this->CI->Kt_saas_model, 'log_activity')) {
            return;
        }

        $payload = array_merge([
            'plan_id' => (int) $planId,
            'plan_code' => (string) ($state['plan_code'] ?? ''),
            'plan_name' => (string) ($state['plan_name'] ?? ''),
            'sync_state' => (string) ($state['sync_state'] ?? ''),
            'stored_hash' => (string) ($state['stored_snapshot_hash'] ?? ''),
            'current_hash' => (string) ($state['source_plan_snapshot_hash'] ?? ''),
        ], $context);

        $this->CI->Kt_saas_model->log_activity($eventKey, $severity, $payload);
    }
}
