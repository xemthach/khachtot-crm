<?php

defined('BASEPATH') or exit('No direct script access allowed');

class EmailDuplicateGuardService
{
    protected $CI;
    protected EmailTriggerRegistryService $registry;
    protected EmailBrandingContextResolverService $brandingResolver;

    public function __construct()
    {
        $this->CI = &get_instance();
        if (!isset($this->CI->Kt_saas_model)) {
            $this->CI->load->model('kt_saas/Kt_saas_model');
        }

        require_once module_dir_path(KT_SAAS_MODULE, 'services/EmailTriggerRegistryService.php');
        require_once module_dir_path(KT_SAAS_MODULE, 'services/EmailBrandingContextResolverService.php');
        $this->registry = new EmailTriggerRegistryService();
        $this->brandingResolver = new EmailBrandingContextResolverService();
    }

    public function reserve($eventKey, array $context = [])
    {
        $eventKey = trim((string) $eventKey);
        if ($eventKey === '') {
            return ['allowed' => false, 'message' => 'Event key is required.'];
        }

        $this->CI->Kt_saas_model->ensure_tenant_email_schema();
        $event = $this->registry->get($eventKey) ?: [];
        $resolved = $this->brandingResolver->resolve($eventKey, $context);
        $dedupeKey = $this->resolveDedupeKey($eventKey, $context, $event);

        $table = db_prefix() . 'kt_saas_email_event_guards';
        $existing = $this->CI->db
            ->where('event_key', $eventKey)
            ->where('dedupe_key', $dedupeKey)
            ->get($table)
            ->row_array();

        $payload = [
            'event_key' => $eventKey,
            'dedupe_key' => $dedupeKey,
            'tenant_id' => !empty($context['tenant_id']) ? (int) $context['tenant_id'] : null,
            'resource_type' => trim((string) ($context['resource_type'] ?? ($event['resource_type'] ?? ''))),
            'resource_id' => isset($context['resource_id']) ? trim((string) $context['resource_id']) : (isset($context['invoice_id']) ? trim((string) $context['invoice_id']) : null),
            'recipient_scope' => (string) ($resolved['recipient_scope'] ?? ($event['recipient_scope'] ?? 'tenant_admin')),
            'branding_context' => (string) ($resolved['branding_context'] ?? ($event['branding_context'] ?? 'landlord')),
            'provider_context' => (string) ($resolved['provider_context'] ?? ($event['provider_context'] ?? 'landlord_global')),
            'status' => 'reserved',
            'context_json' => json_encode($this->buildContextPayload($context, $event, $resolved), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'last_error_message' => null,
            'reserved_at' => date('Y-m-d H:i:s'),
            'sent_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $status = (string) ($existing['status'] ?? '');
            if (in_array($status, ['reserved', 'sent'], true)) {
                return [
                    'allowed' => false,
                    'duplicate' => true,
                    'event_key' => $eventKey,
                    'dedupe_key' => $dedupeKey,
                    'status' => $status,
                    'message' => 'Duplicate email event blocked.',
                ];
            }

            $this->CI->db->where('id', (int) $existing['id'])->update($table, $payload);
            return [
                'allowed' => true,
                'duplicate' => false,
                'event_key' => $eventKey,
                'dedupe_key' => $dedupeKey,
                'status' => 'reserved',
                'message' => 'Recovered failed email guard reservation.',
            ];
        }

        $this->CI->db->insert($table, $payload);

        return [
            'allowed' => true,
            'duplicate' => false,
            'event_key' => $eventKey,
            'dedupe_key' => $dedupeKey,
            'status' => 'reserved',
            'message' => 'Email event reserved.',
        ];
    }

    public function markSent($eventKey, $dedupeKey, array $context = [])
    {
        return $this->updateStatus($eventKey, $dedupeKey, 'sent', null, $context);
    }

    public function markFailed($eventKey, $dedupeKey, $errorMessage = '', array $context = [])
    {
        return $this->updateStatus($eventKey, $dedupeKey, 'failed', $errorMessage, $context);
    }

    protected function updateStatus($eventKey, $dedupeKey, $status, $errorMessage = '', array $context = [])
    {
        $eventKey = trim((string) $eventKey);
        $dedupeKey = trim((string) $dedupeKey);
        if ($eventKey === '' || $dedupeKey === '') {
            return false;
        }

        $table = db_prefix() . 'kt_saas_email_event_guards';
        $row = $this->CI->db
            ->where('event_key', $eventKey)
            ->where('dedupe_key', $dedupeKey)
            ->get($table)
            ->row_array();
        if (!$row) {
            return false;
        }

        $payload = [
            'status' => $status,
            'last_error_message' => $errorMessage !== '' ? substr((string) $errorMessage, 0, 2000) : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($status === 'sent') {
            $payload['sent_at'] = date('Y-m-d H:i:s');
        }

        $this->CI->db->where('id', (int) $row['id'])->update($table, $payload);
        return true;
    }

    protected function resolveDedupeKey($eventKey, array $context, array $event)
    {
        $explicit = trim((string) ($context['dedupe_key'] ?? ''));
        if ($explicit !== '') {
            return substr(sha1($explicit), 0, 64);
        }

        $resourceType = trim((string) ($context['resource_type'] ?? ($event['resource_type'] ?? 'resource')));
        $resourceId = trim((string) ($context['resource_id'] ?? ($context['invoice_id'] ?? $context['tenant_id'] ?? '0')));
        $tenantId = (int) ($context['tenant_id'] ?? 0);
        $scope = trim((string) ($context['recipient_scope'] ?? ($event['recipient_scope'] ?? 'tenant_admin')));
        $raw = implode('|', [
            $eventKey,
            $scope,
            $resourceType,
            $resourceId,
            $tenantId > 0 ? (string) $tenantId : '0',
        ]);

        return substr(sha1($raw), 0, 64);
    }

    protected function buildContextPayload(array $context, array $event, array $resolved)
    {
        $payload = array_merge([
            'event_key' => $event['event_key'] ?? null,
            'resource_type' => $context['resource_type'] ?? ($event['resource_type'] ?? null),
            'resource_id' => $context['resource_id'] ?? ($context['invoice_id'] ?? $context['tenant_id'] ?? null),
            'recipient_scope' => $resolved['recipient_scope'] ?? null,
            'branding_context' => $resolved['branding_context'] ?? null,
            'provider_context' => $resolved['provider_context'] ?? null,
            'template_slug' => $event['template_slug'] ?? null,
        ], $context);

        return $this->redactSensitiveContext($payload);
    }

    protected function redactSensitiveContext(array $payload)
    {
        foreach (['new_pass_key', 'set_password_url'] as $key) {
            if (array_key_exists($key, $payload)) {
                unset($payload[$key]);
                $payload['onboarding_link_generated'] = true;
            }
        }

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->redactSensitiveContext($value);
            }
        }

        return $payload;
    }
}
