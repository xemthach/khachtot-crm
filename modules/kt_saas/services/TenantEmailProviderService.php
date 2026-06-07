<?php

defined('BASEPATH') or exit('No direct script access allowed');

class TenantEmailProviderService
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        if (!isset($this->CI->Kt_saas_model)) {
            $this->CI->load->model('kt_saas/Kt_saas_model');
        }
        if (!isset($this->CI->emails_model)) {
            $this->CI->load->model('Emails_model', 'emails_model');
        }
        if (!isset($this->CI->encryption)) {
            $this->CI->load->library('encryption');
        }
    }

    public function resolveForCurrentTenant($emailType = 'transactional')
    {
        if (!function_exists('kt_saas_is_tenant_runtime') || !kt_saas_is_tenant_runtime()) {
            return $this->resolveGlobal($emailType);
        }

        $tenant = function_exists('kt_saas_current_tenant') ? kt_saas_current_tenant() : null;
        $tenantId = (int) ($tenant['id'] ?? 0);
        if ($tenantId <= 0) {
            return $this->resolveGlobal($emailType);
        }

        return $this->resolveForTenant($tenantId, $emailType);
    }

    public function resolveForTenant($tenantId, $emailType = 'transactional')
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            return $this->resolveGlobal($emailType);
        }

        $this->ensureSchema();
        $ent = $this->getTenantEmailEntitlements($tenantId);
        $global = $this->resolveGlobal($emailType);

        if (empty($ent['own_credentials'])) {
            return $global;
        }

        $row = $this->CI->Kt_saas_model->get_active_tenant_email_setting($tenantId);
        if (!$row) {
            return $global;
        }

        $provider = (string) ($row['provider'] ?? 'system_smtp');
        $status = (string) ($row['provider_status'] ?? 'inactive');

        if (!$this->providerAllowedByEntitlement($provider, $ent)) {
            $this->CI->Kt_saas_model->set_tenant_email_provider_status($tenantId, 'disabled_by_plan', 'Disabled by current plan.');
            return $global;
        }

        if ($status !== 'active') {
            $fallback = (string) ($row['fallback_policy'] ?? 'use_landlord');
            if ($fallback === 'block_sending') {
                return [
                    'provider' => 'blocked',
                    'source'   => 'tenant',
                    'tenant_id' => $tenantId,
                    'policy'   => 'block_sending',
                    'error'    => 'Tenant email provider is not active.',
                ];
            }

            return $global;
        }

        $resolved = $this->mapTenantProviderRow($row, $emailType, $ent);
        $quotaCheck = $this->assertQuota($tenantId, $emailType, $ent);
        if (!$quotaCheck['allowed']) {
            return [
                'provider' => 'blocked',
                'source'   => 'tenant',
                'tenant_id' => $tenantId,
                'policy'   => 'quota_exceeded',
                'error'    => $quotaCheck['message'],
            ];
        }

        return $resolved;
    }

    public function resolveGlobal($emailType = 'transactional')
    {
        $provider = (string) get_option('kt_saas_global_email_provider');
        if ($provider === '') {
            $coreProtocol = (string) get_option('email_protocol');
            if ($coreProtocol === 'brevo_api') {
                $provider = 'brevo_api';
            } elseif ($coreProtocol === 'smtp') {
                $provider = 'system_smtp';
            } else {
                $provider = 'system_smtp';
            }
        }

        $senderEmail = trim((string) get_option('kt_saas_global_sender_email'));
        $senderName = trim((string) get_option('kt_saas_global_sender_name'));
        $replyTo = trim((string) get_option('kt_saas_global_reply_to_email'));
        if ($provider === 'brevo_api') {
            if ($senderName === '') {
                $senderName = trim((string) get_option('kt_saas_global_brevo_sender_name'));
            }
            if ($senderEmail === '') {
                $senderEmail = trim((string) get_option('kt_saas_global_brevo_sender_email'));
            }
            if ($replyTo === '') {
                $replyTo = trim((string) get_option('kt_saas_global_brevo_reply_to_email'));
            }
        }

        $ctx = [
            'provider' => $provider,
            'source' => 'landlord_global',
            'email_type' => $emailType,
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'from_email' => $senderEmail !== '' ? $senderEmail : trim((string) get_option('smtp_email')),
            'from_name' => $senderName !== '' ? $senderName : trim((string) get_option('companyname')),
            'reply_to' => $replyTo,
        ];

        if ($provider === 'brevo_smtp') {
            $smtpHost = trim((string) get_option('kt_saas_global_brevo_smtp_host'));
            $smtpPort = trim((string) get_option('kt_saas_global_brevo_smtp_port'));
            $smtpUser = trim((string) get_option('kt_saas_global_brevo_smtp_user'));
            $smtpPass = (string) $this->CI->encryption->decrypt((string) get_option('kt_saas_global_brevo_smtp_pass_enc'));
            $smtpCrypto = trim((string) get_option('kt_saas_global_brevo_smtp_encryption'));

            // Fallback to core SMTP options when KT SaaS global options are not configured yet.
            if ($smtpHost === '') {
                $smtpHost = trim((string) get_option('smtp_host'));
            }
            if ($smtpPort === '') {
                $smtpPort = trim((string) get_option('smtp_port'));
            }
            if ($smtpUser === '') {
                $smtpUser = trim((string) get_option('smtp_username'));
            }
            if ($smtpPass === '') {
                $smtpPass = (string) get_option('smtp_password');
            }
            if ($smtpCrypto === '') {
                $smtpCrypto = trim((string) get_option('smtp_encryption'));
            }

            $ctx['transport'] = [
                'protocol' => 'smtp',
                'smtp_host' => $smtpHost !== '' ? $smtpHost : 'smtp-relay.brevo.com',
                'smtp_port' => (string) ($smtpPort !== '' ? $smtpPort : '587'),
                'smtp_user' => $smtpUser,
                'smtp_pass' => $smtpPass,
                'smtp_crypto' => $smtpCrypto !== '' ? $smtpCrypto : 'tls',
            ];
        } elseif ($provider === 'brevo_api') {
            $smtpHost = trim((string) get_option('kt_saas_global_brevo_smtp_host'));
            $smtpPort = trim((string) get_option('kt_saas_global_brevo_smtp_port'));
            $smtpUser = trim((string) get_option('kt_saas_global_brevo_smtp_user'));
            $smtpPass = (string) $this->CI->encryption->decrypt((string) get_option('kt_saas_global_brevo_smtp_pass_enc'));
            $smtpCrypto = trim((string) get_option('kt_saas_global_brevo_smtp_encryption'));
            $brevoApiKey = (string) $this->CI->encryption->decrypt((string) get_option('kt_saas_global_brevo_api_key_enc'));

            // Fallback to core SMTP/Brevo options when KT SaaS global options are not configured yet.
            if ($smtpHost === '') {
                $smtpHost = trim((string) get_option('smtp_host'));
            }
            if ($smtpPort === '') {
                $smtpPort = trim((string) get_option('smtp_port'));
            }
            if ($smtpUser === '') {
                $smtpUser = trim((string) get_option('smtp_username'));
            }
            if ($smtpPass === '') {
                $smtpPass = (string) get_option('smtp_password');
            }
            if ($smtpCrypto === '') {
                $smtpCrypto = trim((string) get_option('smtp_encryption'));
            }
            if ($brevoApiKey === '') {
                $brevoApiKey = (string) get_option('brevo_api_key');
            }

            $ctx['transport'] = [
                'protocol' => 'smtp',
                'smtp_host' => $smtpHost !== '' ? $smtpHost : 'smtp-relay.brevo.com',
                'smtp_port' => (string) ($smtpPort !== '' ? $smtpPort : '587'),
                'smtp_user' => $smtpUser,
                'smtp_pass' => $smtpPass,
                'smtp_crypto' => $smtpCrypto !== '' ? $smtpCrypto : 'tls',
                'brevo_api_key' => $brevoApiKey,
            ];
        } else {
            $ctx['transport'] = null;
        }

        return $ctx;
    }

    public function applyRuntimeTransport(array $resolvedContext)
    {
        $transport = $resolvedContext['transport'] ?? null;
        if (!is_array($transport)) {
            $this->CI->config->set_item('kt_saas_mail_runtime_transport', null);
            $this->CI->config->set_item('kt_saas_mail_runtime_identity', null);
            $this->CI->config->set_item('kt_saas_mail_runtime_branding_context', null);
            $this->CI->config->set_item('kt_saas_mail_runtime_provider_context', null);
            $this->CI->config->set_item('kt_saas_mail_runtime_tenant_id', null);
            $this->CI->config->set_item('kt_saas_mail_runtime_related_type', null);
            $this->CI->config->set_item('kt_saas_mail_runtime_related_id', null);
            $this->CI->config->set_item('kt_saas_mail_runtime_event_key', null);
            $this->CI->config->set_item('kt_saas_mail_runtime_dedupe_key', null);
            return;
        }

        $this->CI->config->set_item('kt_saas_mail_runtime_transport', $transport);
        $this->CI->config->set_item('kt_saas_mail_runtime_identity', [
            'provider'   => (string) ($resolvedContext['provider'] ?? 'system_smtp'),
            'from_email' => (string) ($resolvedContext['from_email'] ?? ''),
            'from_name'  => (string) ($resolvedContext['from_name'] ?? ''),
            'reply_to'   => (string) ($resolvedContext['reply_to'] ?? ''),
            'source'     => (string) ($resolvedContext['source'] ?? 'tenant_custom'),
        ]);
        $this->CI->config->set_item('kt_saas_mail_runtime_branding_context', (string) ($resolvedContext['branding_context'] ?? 'landlord'));
        $this->CI->config->set_item('kt_saas_mail_runtime_provider_context', (string) ($resolvedContext['provider_context'] ?? 'landlord_global'));
        $this->CI->config->set_item('kt_saas_mail_runtime_tenant_id', array_key_exists('tenant_id', $resolvedContext) ? (int) $resolvedContext['tenant_id'] : (int) (config_item('kt_saas_mail_runtime_tenant_id') ?: 0));
        $this->CI->config->set_item('kt_saas_mail_runtime_related_type', array_key_exists('related_type', $resolvedContext) ? (string) $resolvedContext['related_type'] : (string) (config_item('kt_saas_mail_runtime_related_type') ?: ''));
        $this->CI->config->set_item('kt_saas_mail_runtime_related_id', array_key_exists('related_id', $resolvedContext) ? (string) $resolvedContext['related_id'] : (string) (config_item('kt_saas_mail_runtime_related_id') ?: ''));
        $this->CI->config->set_item('kt_saas_mail_runtime_event_key', array_key_exists('event_key', $resolvedContext) ? (string) $resolvedContext['event_key'] : (string) (config_item('kt_saas_mail_runtime_event_key') ?: ''));
        $this->CI->config->set_item('kt_saas_mail_runtime_dedupe_key', array_key_exists('dedupe_key', $resolvedContext) ? (string) $resolvedContext['dedupe_key'] : (string) (config_item('kt_saas_mail_runtime_dedupe_key') ?: ''));
    }

    public function sendRegisteredEventEmail($eventKey, array $context = [], array $options = [])
    {
        $eventKey = trim((string) $eventKey);
        if ($eventKey === '') {
            return ['success' => false, 'message' => 'Event key is required.'];
        }

        require_once module_dir_path(KT_SAAS_MODULE, 'services/EmailTriggerRegistryService.php');
        require_once module_dir_path(KT_SAAS_MODULE, 'services/EmailBrandingContextResolverService.php');

        $registry = new EmailTriggerRegistryService();
        $event = $registry->get($eventKey);
        if (!$event) {
            return ['success' => false, 'message' => 'Email event is not registered.', 'event_key' => $eventKey];
        }

        $recipientEmail = $this->resolveRecipientEmail($context, $event);
        if ($recipientEmail === '') {
            return ['success' => false, 'message' => 'Recipient email is required.', 'event_key' => $eventKey];
        }

        $resolvedBranding = (new EmailBrandingContextResolverService())->resolve($eventKey, $context);
        $tenantId = (int) ($context['tenant_id'] ?? ($context['tenant']['id'] ?? 0));
        if ($tenantId <= 0 && !empty($context['invoice']['tenant_id'])) {
            $tenantId = (int) $context['invoice']['tenant_id'];
        }

        $dedupeKey = trim((string) ($context['dedupe_key'] ?? ''));
        $guard = $context['email_event_guard'] ?? null;
        if (is_array($guard) && empty($guard['allowed'])) {
            return [
                'success' => false,
                'duplicate' => !empty($guard['duplicate']),
                'event_key' => $eventKey,
                'dedupe_key' => (string) ($guard['dedupe_key'] ?? $dedupeKey),
                'message' => (string) ($guard['message'] ?? 'Duplicate email event blocked.'),
            ];
        }
        if (empty($guard) && !empty($event['duplicate_guard_key'])) {
            require_once module_dir_path(KT_SAAS_MODULE, 'helpers/kt_saas_helper.php');
            $guardContext = $this->buildEventGuardContext($eventKey, $context, $event, $resolvedBranding, $tenantId, $dedupeKey);
            $guard = kt_saas_reserve_email_event($eventKey, $guardContext);
            if (empty($guard['allowed'])) {
                return [
                    'success' => false,
                    'duplicate' => !empty($guard['duplicate']),
                    'event_key' => $eventKey,
                    'dedupe_key' => (string) ($guard['dedupe_key'] ?? $dedupeKey),
                    'message' => (string) ($guard['message'] ?? 'Duplicate email event blocked.'),
                ];
            }
        }

        $brandingContext = (string) ($resolvedBranding['branding_context'] ?? ($event['branding_context'] ?? 'landlord'));
        $providerContext = (string) ($resolvedBranding['provider_context'] ?? ($event['provider_context'] ?? 'landlord_global'));
        $runtimeContext = $brandingContext === 'tenant' && $tenantId > 0
            ? $this->resolveForTenant($tenantId, 'transactional')
            : $this->resolveGlobal('transactional');

        if (($runtimeContext['provider'] ?? '') === 'blocked') {
            if (is_array($guard) && !empty($guard['event_key']) && !empty($guard['dedupe_key'])) {
                require_once module_dir_path(KT_SAAS_MODULE, 'helpers/kt_saas_helper.php');
                kt_saas_mark_email_event_failed($guard['event_key'], $guard['dedupe_key'], (string) ($runtimeContext['error'] ?? 'Email provider blocked.'), $context);
            }

            return [
                'success' => false,
                'event_key' => $eventKey,
                'message' => (string) ($runtimeContext['error'] ?? 'Email provider is blocked.'),
            ];
        }

        $runtimeContext['tenant_id'] = $tenantId > 0 ? $tenantId : null;
        $runtimeContext['related_type'] = (string) ($context['related_type'] ?? ($event['resource_type'] ?? ''));
        $runtimeContext['related_id'] = isset($context['related_id']) ? (string) $context['related_id'] : (isset($context['invoice_id']) ? (string) $context['invoice_id'] : ($tenantId > 0 ? (string) $tenantId : ''));
        $runtimeContext['event_key'] = $eventKey;
        $runtimeContext['dedupe_key'] = (string) ($guard['dedupe_key'] ?? $dedupeKey);
        $runtimeContext['branding_context'] = $brandingContext;
        $runtimeContext['provider_context'] = $providerContext;

        $mailContext = $this->buildEventMailContext($eventKey, $context, $event, $runtimeContext, $recipientEmail);
        $class = $this->resolveMailClassName((string) ($event['template_slug'] ?? ''));
        if ($class === '') {
            if (is_array($guard) && !empty($guard['event_key']) && !empty($guard['dedupe_key'])) {
                require_once module_dir_path(KT_SAAS_MODULE, 'helpers/kt_saas_helper.php');
                kt_saas_mark_email_event_failed($guard['event_key'], $guard['dedupe_key'], 'Mail class is not registered.', $context);
            }

            return [
                'success' => false,
                'event_key' => $eventKey,
                'message' => 'Mail class is not registered.',
            ];
        }

        $this->applyRuntimeTransport($runtimeContext);
        if (!isset($this->CI->kt_saas_merge_fields)) {
            $this->CI->load->library('kt_saas/merge_fields/Kt_saas_merge_fields');
        }

        try {
            if (!function_exists('mail_template')) {
                require_once APPPATH . 'helpers/email_templates_helper.php';
            }

            $this->CI->config->set_item('kt_saas_mail_last_message_id', null);
            $template = mail_template($class, $mailContext);
            if (!$template || !is_object($template)) {
                if (is_array($guard) && !empty($guard['event_key']) && !empty($guard['dedupe_key'])) {
                    require_once module_dir_path(KT_SAAS_MODULE, 'helpers/kt_saas_helper.php');
                    kt_saas_mark_email_event_failed($guard['event_key'], $guard['dedupe_key'], 'Unable to instantiate mail template.', $mailContext);
                }

                return [
                    'success' => false,
                    'event_key' => $eventKey,
                    'message' => 'Unable to instantiate mail template.',
                ];
            }

            $sent = $template->send();
            $messageId = '';
            $error = '';
            $errorCode = 0;
            if (isset($this->CI->emails_model)) {
                $messageId = (string) $this->CI->emails_model->get_last_send_message_id();
                $error = (string) $this->CI->emails_model->get_last_send_error();
                $errorCode = (int) $this->CI->emails_model->get_last_send_error_code();
            }
            if ($messageId === '' && isset($this->CI->email) && method_exists($this->CI->email, 'get_last_message_id')) {
                $messageId = trim((string) $this->CI->email->get_last_message_id());
            }
            if ($messageId === '') {
                $messageId = trim((string) config_item('kt_saas_mail_last_message_id'));
            }
            if ($messageId === '') {
                $messageId = 'local:' . uniqid('mail_', true);
            }

            if ($sent) {
                if (is_array($guard) && !empty($guard['event_key']) && !empty($guard['dedupe_key'])) {
                    require_once module_dir_path(KT_SAAS_MODULE, 'helpers/kt_saas_helper.php');
                    kt_saas_mark_email_event_sent($guard['event_key'], $guard['dedupe_key'], $mailContext);
                }
                $this->logEmailResult(
                    $recipientEmail,
                    (string) ($mailContext['subject'] ?? $eventKey),
                    'sent',
                    '',
                    (string) ($runtimeContext['provider'] ?? ''),
                    $tenantId > 0 ? $tenantId : null,
                    (string) ($event['delivery_mode'] ?? 'transactional'),
                    $messageId,
                    (string) ($runtimeContext['from_email'] ?? ''),
                    (string) ($runtimeContext['related_type'] ?? ''),
                    (string) ($runtimeContext['related_id'] ?? '')
                );

                return [
                    'success' => true,
                    'event_key' => $eventKey,
                    'provider' => (string) ($runtimeContext['provider'] ?? ''),
                    'transport' => (string) ($runtimeContext['transport']['protocol'] ?? ''),
                    'recipient' => $recipientEmail,
                    'sender' => (string) ($runtimeContext['from_email'] ?? ''),
                    'message_id' => $messageId,
                    'dedupe_key' => (string) ($guard['dedupe_key'] ?? $dedupeKey),
                    'guard' => $guard,
                    'template_slug' => (string) ($event['template_slug'] ?? ''),
                ];
            }

            if (is_array($guard) && !empty($guard['event_key']) && !empty($guard['dedupe_key'])) {
                require_once module_dir_path(KT_SAAS_MODULE, 'helpers/kt_saas_helper.php');
                kt_saas_mark_email_event_failed($guard['event_key'], $guard['dedupe_key'], $error !== '' ? $error : 'Email send failed.', $mailContext);
            }
            $this->logEmailResult(
                $recipientEmail,
                (string) ($mailContext['subject'] ?? $eventKey),
                'failed',
                $error !== '' ? $error : 'Email send failed.',
                (string) ($runtimeContext['provider'] ?? ''),
                $tenantId > 0 ? $tenantId : null,
                (string) ($event['delivery_mode'] ?? 'transactional'),
                $messageId,
                (string) ($runtimeContext['from_email'] ?? ''),
                (string) ($runtimeContext['related_type'] ?? ''),
                (string) ($runtimeContext['related_id'] ?? '')
            );

            return [
                'success' => false,
                'event_key' => $eventKey,
                'provider' => (string) ($runtimeContext['provider'] ?? ''),
                'transport' => (string) ($runtimeContext['transport']['protocol'] ?? ''),
                'recipient' => $recipientEmail,
                'sender' => (string) ($runtimeContext['from_email'] ?? ''),
                'message_id' => $messageId,
                'error' => $error !== '' ? $error : 'Email send failed.',
                'error_code' => $errorCode,
                'dedupe_key' => (string) ($guard['dedupe_key'] ?? $dedupeKey),
                'guard' => $guard,
                'template_slug' => (string) ($event['template_slug'] ?? ''),
            ];
        } finally {
            $this->applyRuntimeTransport(['transport' => null]);
            $this->CI->config->set_item('kt_saas_mail_last_message_id', null);
        }
    }

    protected function resolveRecipientEmail(array $context, array $event = [])
    {
        $scope = trim((string) ($context['recipient_scope'] ?? ($event['recipient_scope'] ?? 'tenant_admin')));

        if (in_array($scope, ['tenant_admin', 'tenant', 'tenant_owner'], true)) {
            return $this->firstValidEmail($this->tenantRecipientCandidates($context));
        }

        if (in_array($scope, ['customer', 'customer_contact', 'tenant_customer'], true)) {
            return $this->firstValidEmail($this->customerRecipientCandidates($context));
        }

        if (in_array($scope, ['landlord_admin', 'landlord', 'ops', 'internal'], true)) {
            return $this->firstValidEmail($this->landlordRecipientCandidates($context));
        }

        return $this->firstValidEmail($this->tenantRecipientCandidates($context));
    }

    protected function tenantRecipientCandidates(array $context)
    {
        $candidates = [
            $context['owner_email'] ?? '',
            $context['tenant_owner_email'] ?? '',
        ];

        if (!empty($context['tenant']) && is_array($context['tenant'])) {
            $candidates[] = $context['tenant']['owner_email'] ?? '';
            $candidates[] = $context['tenant']['owner_email_address'] ?? '';
            $candidates[] = $context['tenant']['billing_email'] ?? '';
        }

        foreach (['tenant_admin', 'admin_staff', 'staff'] as $key) {
            if (!empty($context[$key]) && is_array($context[$key])) {
                $candidates[] = $context[$key]['email'] ?? '';
            }
        }

        $candidates[] = $context['billing_email'] ?? '';
        $candidates[] = $context['payment_email'] ?? '';
        $candidates[] = $context['recipient_email'] ?? '';
        $candidates[] = $context['email'] ?? '';
        $candidates[] = $context['send_to'] ?? '';
        $candidates[] = $context['to_email'] ?? '';

        return $candidates;
    }

    protected function customerRecipientCandidates(array $context)
    {
        $candidates = [
            $context['customer_email'] ?? '',
            $context['contact_email'] ?? '',
            $context['recipient_email'] ?? '',
            $context['email'] ?? '',
            $context['send_to'] ?? '',
            $context['to_email'] ?? '',
        ];

        if (!empty($context['invoice']) && is_array($context['invoice'])) {
            $candidates[] = $context['invoice']['recipient_email'] ?? '';
            $candidates[] = $context['invoice']['contact_email'] ?? '';
            $candidates[] = $context['invoice']['billing_email'] ?? '';
        }

        if (!empty($context['contact']) && is_array($context['contact'])) {
            $candidates[] = $context['contact']['email'] ?? '';
        }

        if (!empty($context['customer']) && is_array($context['customer'])) {
            $candidates[] = $context['customer']['email'] ?? '';
        }

        return $candidates;
    }

    protected function landlordRecipientCandidates(array $context)
    {
        return [
            $context['recipient_email'] ?? '',
            $context['ops_email'] ?? '',
            $context['admin_email'] ?? '',
            $context['support_email'] ?? '',
            $context['email'] ?? '',
            $context['send_to'] ?? '',
            $context['to_email'] ?? '',
            function_exists('kt_saas_landlord_ops_email') ? kt_saas_landlord_ops_email() : '',
        ];
    }

    protected function firstValidEmail(array $candidates)
    {
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        return '';
    }

    protected function resolveMailClassName($templateSlug)
    {
        $templateSlug = trim((string) $templateSlug);
        if ($templateSlug === '') {
            return '';
        }

        return str_replace('-', '_', $templateSlug);
    }

    protected function buildEventGuardContext($eventKey, array $context, array $event, array $resolvedBranding, $tenantId, $dedupeKey = '')
    {
        $payload = [
            'tenant_id' => $tenantId > 0 ? (int) $tenantId : null,
            'resource_type' => (string) ($context['related_type'] ?? ($event['resource_type'] ?? ($context['resource_type'] ?? ''))),
            'resource_id' => $context['related_id'] ?? ($context['invoice_id'] ?? $tenantId),
            'recipient_scope' => (string) ($resolvedBranding['recipient_scope'] ?? ($event['recipient_scope'] ?? 'tenant_admin')),
            'branding_context' => (string) ($resolvedBranding['branding_context'] ?? ($event['branding_context'] ?? 'landlord')),
            'provider_context' => (string) ($resolvedBranding['provider_context'] ?? ($event['provider_context'] ?? 'landlord_global')),
        ];

        if ($dedupeKey !== '') {
            $payload['dedupe_key'] = $dedupeKey;
        }

        return array_merge($payload, $context);
    }

    protected function buildEventMailContext($eventKey, array $context, array $event, array $runtimeContext, $recipientEmail)
    {
        $tenant = [];
        if (!empty($context['tenant']) && is_array($context['tenant'])) {
            $tenant = $context['tenant'];
        } elseif (!empty($context['tenant_id']) && isset($this->CI->Kt_saas_model)) {
            $tenant = (array) $this->CI->Kt_saas_model->get_tenant((int) $context['tenant_id']);
        }

        $invoice = [];
        if (!empty($context['invoice']) && is_array($context['invoice'])) {
            $invoice = $context['invoice'];
        } elseif (!empty($context['invoice_id']) && isset($this->CI->Kt_saas_model)) {
            $invoice = (array) $this->CI->Kt_saas_model->get_invoice((int) $context['invoice_id']);
        }

        $subscription = [];
        if (!empty($context['subscription']) && is_array($context['subscription'])) {
            $subscription = $context['subscription'];
        }

        $plan = [];
        if (!empty($context['plan']) && is_array($context['plan'])) {
            $plan = $context['plan'];
        } elseif (!empty($tenant['plan_id']) && isset($this->CI->Kt_saas_model)) {
            $plan = (array) $this->CI->Kt_saas_model->get_plan((int) $tenant['plan_id']);
        }

        return array_merge($context, [
            'event_key' => $eventKey,
            'template_slug' => (string) ($event['template_slug'] ?? ''),
            'recipient_email' => $recipientEmail,
            'tenant' => $tenant,
            'tenant_id' => $runtimeContext['tenant_id'] ?? ($tenant['id'] ?? null),
            'invoice' => $invoice,
            'invoice_id' => $context['invoice_id'] ?? ($invoice['id'] ?? null),
            'invoice_number' => $context['invoice_number'] ?? ($invoice['invoice_number'] ?? ($invoice['number'] ?? '')),
            'invoice_status' => $context['invoice_status'] ?? ($invoice['status'] ?? ''),
            'invoice_total' => $context['invoice_total'] ?? ($invoice['grand_total'] ?? null),
            'currency' => $context['currency'] ?? ($invoice['currency'] ?? ($tenant['currency'] ?? '')),
            'payment_reference' => $context['payment_reference'] ?? ($invoice['payment_reference'] ?? ''),
            'payment_amount' => $context['payment_amount'] ?? ($context['amount'] ?? ($invoice['amount_paid'] ?? ($invoice['grand_total'] ?? null))),
            'payment_status' => $context['payment_status'] ?? ($invoice['payment_status'] ?? ($invoice['status'] ?? '')),
            'transaction_code' => $context['transaction_code'] ?? ($invoice['transaction_code'] ?? ''),
            'bank_account' => $context['bank_account'] ?? ($context['account_number'] ?? ''),
            'webhook_url' => $context['webhook_url'] ?? '',
            'job_id' => $context['job_id'] ?? '',
            'provider_name' => $context['provider_name'] ?? (string) ($runtimeContext['provider'] ?? ($event['provider_context'] ?? '')),
            'module_name' => $context['module_name'] ?? '',
            'subscription' => $subscription,
            'plan' => $plan,
            'trial_end_date' => $context['trial_end_date'] ?? ($subscription['trial_ends_at'] ?? ''),
            'subscription_status' => $context['subscription_status'] ?? ($subscription['status'] ?? ''),
            'related_type' => $runtimeContext['related_type'] ?? ($event['resource_type'] ?? ''),
            'related_id' => $runtimeContext['related_id'] ?? ($context['related_id'] ?? ($context['invoice_id'] ?? ($tenant['id'] ?? null))),
            'branding_context' => $runtimeContext['branding_context'] ?? ($event['branding_context'] ?? 'landlord'),
            'provider_context' => $runtimeContext['provider_context'] ?? ($event['provider_context'] ?? 'landlord_global'),
            'dedupe_key' => $runtimeContext['dedupe_key'] ?? ($context['dedupe_key'] ?? ''),
            'workspace_url' => $context['workspace_url'] ?? '',
            'workspace_name' => $context['workspace_name'] ?? ($tenant['company_name'] ?? ($tenant['tenant_name'] ?? '')),
            'workspace_domain' => $context['workspace_domain'] ?? '',
            'owner_name' => $context['owner_name'] ?? ($tenant['owner_name'] ?? ''),
            'owner_email' => $context['owner_email'] ?? ($tenant['owner_email'] ?? ''),
            'admin_login_url' => $context['admin_login_url'] ?? '',
            'set_password_url' => $context['set_password_url'] ?? '',
            'support_email' => $context['support_email'] ?? '',
            'password_link_expires_in' => $context['password_link_expires_in'] ?? '48 giờ',
            'payment_url' => $context['payment_url'] ?? '',
            'invoice_url' => $context['invoice_url'] ?? '',
            'lookup_url' => $context['lookup_url'] ?? ($context['invoice_url'] ?? ($invoice['invoice_url'] ?? '')),
            'pdf_url' => $context['pdf_url'] ?? '',
            'xml_url' => $context['xml_url'] ?? '',
            'hsm_status' => $context['hsm_status'] ?? '',
            'hsm_expiry_date' => $context['hsm_expiry_date'] ?? ($context['token_expired_at'] ?? ''),
            'einvoice_quota' => $context['einvoice_quota'] ?? '',
            'einvoice_remaining' => $context['einvoice_remaining'] ?? '',
            'quota_remaining' => $context['quota_remaining'] ?? '',
            'quota_limit' => $context['quota_limit'] ?? '',
        ]);
    }

    public function prepareTemplateHeaders(array $headers)
    {
        $ctx = $this->resolveForCurrentTenant('transactional');
        if (($ctx['provider'] ?? '') === 'blocked') {
            return $headers;
        }

        $this->applyRuntimeTransport($ctx);
        if (!empty($ctx['from_name'])) {
            $headers['fromname'] = $ctx['from_name'];
        }
        if (!empty($ctx['from_email'])) {
            $headers['fromemail'] = $ctx['from_email'];
        }

        return $headers;
    }

    public function prepareSimpleEmailPayload(array $payload)
    {
        $ctx = $this->resolveForCurrentTenant('notification');
        if (($ctx['provider'] ?? '') === 'blocked') {
            $payload['prevent_sending'] = true;
            return $payload;
        }

        $this->applyRuntimeTransport($ctx);
        if (!empty($ctx['from_name'])) {
            $payload['from_name'] = $ctx['from_name'];
        }
        if (!empty($ctx['from_email'])) {
            $payload['from_email'] = $ctx['from_email'];
        }
        if (!empty($ctx['reply_to']) && empty($payload['reply_to'])) {
            $payload['reply_to'] = $ctx['reply_to'];
        }

        return $payload;
    }

    public function logEmailResult($recipient, $subject, $status, $error = '', $provider = '', $tenantId = null, $emailType = 'transactional', $messageId = '', $fromEmail = '', $relatedType = '', $relatedId = null)
    {
        $this->ensureSchema();
        $tenantId = $tenantId !== null ? (int) $tenantId : (function_exists('kt_saas_is_tenant_runtime') && kt_saas_is_tenant_runtime() ? (int) ((kt_saas_current_tenant()['id'] ?? 0)) : null);
        $db = $this->landlordDb();
        $db->insert(db_prefix() . 'kt_saas_email_logs', [
            'tenant_id'      => $tenantId > 0 ? $tenantId : null,
            'provider'       => substr((string) $provider, 0, 40),
            'email_type'     => substr((string) $emailType, 0, 30),
            'from_email'     => $fromEmail !== '' ? substr((string) $fromEmail, 0, 191) : null,
            'recipient'      => substr((string) $recipient, 0, 191),
            'subject'        => (string) $subject,
            'status'         => substr((string) $status, 0, 30),
            'error_message'  => $error !== '' ? (string) $error : null,
            'message_id'     => $messageId !== '' ? (string) $messageId : null,
            'related_type'   => $relatedType !== '' ? substr((string) $relatedType, 0, 50) : null,
            'related_id'     => $relatedId !== null && $relatedId !== '' ? substr((string) $relatedId, 0, 191) : null,
            'sent_at'        => date('Y-m-d H:i:s'),
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }

    protected function mapTenantProviderRow(array $row, $emailType, array $ent)
    {
        $provider = (string) ($row['provider'] ?? 'system_smtp');
        $ctx = [
            'provider'   => $provider,
            'source'     => 'tenant_custom',
            'tenant_id'  => (int) ($row['tenant_id'] ?? 0),
            'email_type' => $emailType,
            'branding_context' => 'tenant',
            'provider_context' => 'tenant_custom',
            'from_email' => trim((string) ($row['sender_email'] ?? '')),
            'from_name'  => trim((string) ($row['sender_name'] ?? '')),
            'reply_to'   => trim((string) ($row['reply_to_email'] ?? '')),
        ];

        if (in_array($provider, ['brevo_smtp', 'system_smtp'], true)) {
            $ctx['transport'] = [
                'protocol'    => 'smtp',
                'smtp_host'   => trim((string) ($row['smtp_host'] ?? '')),
                'smtp_port'   => (string) ($row['smtp_port'] ?? ''),
                'smtp_user'   => trim((string) ($row['smtp_username'] ?? '')),
                'smtp_pass'   => (string) $this->CI->encryption->decrypt((string) ($row['smtp_password_encrypted'] ?? '')),
                'smtp_crypto' => trim((string) ($row['smtp_encryption'] ?? '')),
            ];
        } else {
            $ctx['transport'] = [
                'protocol'    => 'smtp',
                'smtp_host'   => trim((string) ($row['smtp_host'] ?? 'smtp-relay.brevo.com')),
                'smtp_port'   => (string) ($row['smtp_port'] ?? '587'),
                'smtp_user'   => trim((string) ($row['smtp_username'] ?? '')),
                'smtp_pass'   => (string) $this->CI->encryption->decrypt((string) ($row['smtp_password_encrypted'] ?? '')),
                'smtp_crypto' => trim((string) ($row['smtp_encryption'] ?? 'tls')),
                'brevo_api_key' => (string) $this->CI->encryption->decrypt((string) ($row['brevo_api_key_encrypted'] ?? '')),
            ];
        }

        return $ctx;
    }

    protected function providerAllowedByEntitlement($provider, array $ent)
    {
        if ($provider === 'brevo_api') {
            return !empty($ent['brevo_api']);
        }
        if ($provider === 'brevo_smtp') {
            return !empty($ent['brevo_smtp']) || !empty($ent['custom_smtp']);
        }
        if ($provider === 'system_smtp') {
            return !empty($ent['custom_smtp']);
        }

        return false;
    }

    protected function getTenantEmailEntitlements($tenantId)
    {
        $tenantId = (int) $tenantId;
        require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');
        $entService = new TenantEntitlementService();

        $ownCreds = (bool) $entService->getFeatureValue($tenantId, 'email.own_credentials', false);
        $customSender = (bool) $entService->getFeatureValue($tenantId, 'email.custom_sender', false);
        $customSmtp = (bool) $entService->getFeatureValue($tenantId, 'email.custom_smtp', false);
        $brevoSmtp = (bool) $entService->getFeatureValue($tenantId, 'email.brevo_smtp', false);
        $brevoApi = (bool) $entService->getFeatureValue($tenantId, 'email.brevo_api', false);

        $dailyTran = (int) $entService->getFeatureValue($tenantId, 'email.daily_quota_transactional', 0);
        $dailyMarketing = (int) $entService->getFeatureValue($tenantId, 'email.daily_quota_marketing', 0);
        $monthlyTotal = (int) $entService->getFeatureValue($tenantId, 'email.monthly_quota_total', 0);

        return [
            'own_credentials' => $ownCreds,
            'custom_sender' => $customSender,
            'custom_smtp' => $customSmtp,
            'brevo_smtp' => $brevoSmtp,
            'brevo_api' => $brevoApi,
            'daily_quota_transactional' => max(0, $dailyTran),
            'daily_quota_marketing' => max(0, $dailyMarketing),
            'monthly_quota_total' => max(0, $monthlyTotal),
        ];
    }

    protected function assertQuota($tenantId, $emailType, array $ent)
    {
        $todayStart = date('Y-m-d 00:00:00');
        $monthStart = date('Y-m-01 00:00:00');

        $dailyTypeLimit = $emailType === 'marketing'
            ? (int) ($ent['daily_quota_marketing'] ?? 0)
            : (int) ($ent['daily_quota_transactional'] ?? 0);
        $monthlyLimit = (int) ($ent['monthly_quota_total'] ?? 0);

        if ($dailyTypeLimit > 0) {
            $countDaily = (int) $this->landlordDb()
                ->where('tenant_id', (int) $tenantId)
                ->where('email_type', (string) $emailType)
                ->where('status', 'sent')
                ->where('created_at >=', $todayStart)
                ->count_all_results(db_prefix() . 'kt_saas_email_logs');
            if ($countDaily >= $dailyTypeLimit) {
                return ['allowed' => false, 'message' => 'Daily email quota exceeded.'];
            }
        }

        if ($monthlyLimit > 0) {
            $countMonth = (int) $this->landlordDb()
                ->where('tenant_id', (int) $tenantId)
                ->where('status', 'sent')
                ->where('created_at >=', $monthStart)
                ->count_all_results(db_prefix() . 'kt_saas_email_logs');
            if ($countMonth >= $monthlyLimit) {
                return ['allowed' => false, 'message' => 'Monthly email quota exceeded.'];
            }
        }

        return ['allowed' => true, 'message' => ''];
    }

    protected function ensureSchema()
    {
        $this->CI->Kt_saas_model->ensure_tenant_email_schema();
    }

    protected function landlordDb()
    {
        $landlordDb = $this->CI->config->item('kt_saas_landlord_db');
        return $landlordDb ?: $this->CI->db;
    }
}
