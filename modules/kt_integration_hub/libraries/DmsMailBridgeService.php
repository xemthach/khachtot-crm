<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEmailProviderService.php');

class DmsMailBridgeService
{
    protected TenantEmailProviderService $providerService;

    public function __construct(?TenantEmailProviderService $providerService = null)
    {
        $this->providerService = $providerService ?: new TenantEmailProviderService();
    }

    public function send(array $tenant, array $payload, array $options = []): array
    {
        $templateKey = strtolower(trim((string) ($payload['template'] ?? '')));
        $preset = $this->resolvePreset($templateKey);
        if ($preset === null) {
            return $this->failure('INVALID_TEMPLATE', 'Template is not allowed.', 422, [
                'template' => ['Template is not allowlisted.'],
            ]);
        }

        $recipient = trim((string) ($payload['to'] ?? ''));
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return $this->failure('INVALID_RECIPIENT', 'Recipient email is invalid.', 422, [
                'to' => ['Recipient email is required and must be valid.'],
            ]);
        }

        $tenantId = (int) ($tenant['id'] ?? 0);
        if ($tenantId <= 0) {
            return $this->failure('TENANT_NOT_FOUND', 'Tenant context is missing.', 404);
        }

        $payloadTenant = (array) ($payload['tenant'] ?? []);
        $payloadTenantId = (int) ($payload['tenant_id'] ?? ($payloadTenant['id'] ?? 0));
        $payloadCrmTenantId = (int) ($payload['crm_tenant_id'] ?? ($payloadTenant['crm_tenant_id'] ?? 0));
        $payloadCrmCompanyId = (int) ($payload['crm_company_id'] ?? ($payloadTenant['crm_company_id'] ?? 0));
        $payloadTenantCode = strtoupper(trim((string) ($payload['tenant_code'] ?? ($payloadTenant['tenant_code'] ?? ''))));
        if ($payloadTenantId !== $tenantId
            || $payloadCrmTenantId !== $tenantId
            || $payloadCrmCompanyId !== $tenantId
            || $payloadTenantCode !== strtoupper(trim((string) ($tenant['tenant_code'] ?? '')))) {
            return $this->failure('TENANT_SCOPE_MISMATCH', 'Payload tenant context does not match CRM runtime.', 409);
        }

        $crmStaffId = (int) ($payload['crm_staff_id'] ?? 0);
        if ($crmStaffId <= 0) {
            return $this->failure('STAFF_NOT_MAPPED', 'crm_staff_id is required.', 422, [
                'crm_staff_id' => ['crm_staff_id is required.'],
            ]);
        }

        $subject = trim((string) ($preset['subject'] ?? ''));
        if ($subject === '') {
            $subject = trim((string) ($payload['subject'] ?? ''));
        }
        if ($subject === '') {
            return $this->failure('VALIDATION_ERROR', 'Subject is required.', 422, [
                'subject' => ['subject is required.'],
            ]);
        }

        $data = array_merge((array) ($preset['data'] ?? []), (array) ($payload['data'] ?? []));
        $actionUrl = trim((string) ($data['action_url'] ?? $data['reset_url'] ?? $data['set_password_url'] ?? $data['verification_url'] ?? ''));
        if ($actionUrl === '') {
            return $this->failure('VALIDATION_ERROR', 'Action URL is required.', 422, [
                'data.action_url' => ['action_url is required.'],
            ]);
        }

        $idempotencyKey = trim((string) ($options['idempotency_key'] ?? ($payload['idempotency_key'] ?? '')));
        if ($idempotencyKey === '') {
            $idempotencyKey = sha1($templateKey . '|' . $recipient . '|' . $actionUrl . '|' . $subject);
        }

        $context = array_merge($data, [
            'tenant_id' => $tenantId,
            'tenant' => $tenant,
            'recipient_email' => $recipient,
            'owner_email' => $recipient,
            'owner_name' => (string) ($data['name'] ?? $data['recipient_name'] ?? ($tenant['owner_name'] ?? '')),
            'subject' => $subject,
            'headline' => (string) ($preset['headline'] ?? ''),
            'message' => (string) ($preset['message'] ?? ''),
            'action_label' => (string) ($preset['action_label'] ?? 'Open link'),
            'action_url' => $actionUrl,
            'login_url' => (string) ($data['login_url'] ?? $this->defaultLoginUrl()),
            'expires_in_minutes' => (int) ($data['expires_in_minutes'] ?? 60),
            'support_email' => (string) ($data['support_email'] ?? $this->defaultSupportEmail()),
            'companyname' => (string) ($tenant['company_name'] ?? ($tenant['tenant_name'] ?? 'Khach Tot Platform')),
            'email_signature' => (string) ($data['email_signature'] ?? ''),
            'app_name' => (string) ($data['app_name'] ?? 'Salesflow DMS'),
            'template_key' => $templateKey,
            'template_variant' => (string) ($preset['variant'] ?? 'auth_link'),
            'dedupe_key' => $idempotencyKey,
            'related_type' => 'staff',
            'related_id' => (string) $crmStaffId,
        ]);

        $eventResult = $this->deliverBridgeMail($tenantId, $recipient, $subject, $context, $idempotencyKey);
        if (empty($eventResult['success']) && empty($eventResult['duplicate'])) {
            return $this->failure(
                'MAIL_SEND_FAILED',
                (string) ($eventResult['message'] ?? 'Mail send failed.'),
                502,
                [],
                $eventResult
            );
        }

        return [
            'success' => true,
            'code' => !empty($eventResult['duplicate']) ? 'DUPLICATE_EMAIL_EVENT' : 'MAIL_SENT',
            'message' => !empty($eventResult['duplicate']) ? 'Duplicate mail request ignored.' : 'Mail sent.',
            'duplicate' => !empty($eventResult['duplicate']),
            'template' => $templateKey,
            'message_id' => (string) ($eventResult['message_id'] ?? ''),
            'provider' => (string) ($eventResult['provider'] ?? ''),
            'recipient' => $recipient,
            'tenant_id' => $tenantId,
            'idempotency_key' => $idempotencyKey,
            'bridge_result' => $eventResult,
        ];
    }

    protected function resolvePreset(string $templateKey): ?array
    {
        return match ($templateKey) {
            'dms_password_reset' => [
                'subject' => 'Reset your DMS password',
                'headline' => 'Reset your DMS password',
                'message' => 'Use the link below to set a new password for your DMS account.',
                'action_label' => 'Reset password',
                'variant' => 'password_reset',
                'data' => [],
            ],
            'dms_password_setup' => [
                'subject' => 'Set up your DMS password',
                'headline' => 'Set up your DMS password',
                'message' => 'Your DMS account is ready. Use the link below to create your password.',
                'action_label' => 'Set password',
                'variant' => 'password_setup',
                'data' => [],
            ],
            'dms_user_welcome' => [
                'subject' => 'Your DMS account is ready',
                'headline' => 'Welcome to DMS',
                'message' => 'Your DMS account has been created. Use the link below to set your password and sign in.',
                'action_label' => 'Set password',
                'variant' => 'user_welcome',
                'data' => [],
            ],
            'dms_registration_verification' => [
                'subject' => 'Verify your DMS email',
                'headline' => 'Verify your DMS email',
                'message' => 'Use the link below to verify your DMS email address.',
                'action_label' => 'Verify email',
                'variant' => 'registration_verification',
                'data' => [],
            ],
            default => null,
        };
    }

    protected function defaultLoginUrl(): string
    {
        $baseUrl = '';
        $ci = &get_instance();
        if (isset($ci->config)) {
            $baseUrl = trim((string) $ci->config->item('app_base_url'));
        }

        if ($baseUrl === '' && defined('APP_BASE_URL')) {
            $baseUrl = trim((string) APP_BASE_URL);
        }

        return $baseUrl !== '' ? rtrim($baseUrl, '/') . '/login' : '';
    }

    protected function defaultSupportEmail(): string
    {
        $address = '';
        $ci = &get_instance();
        if (isset($ci->config)) {
            $address = trim((string) $ci->config->item('smtp_email'));
        }

        if ($address === '') {
            $address = trim((string) get_option('smtp_email'));
        }

        return $address !== '' ? $address : 'support@example.com';
    }

    protected function deliverBridgeMail(int $tenantId, string $recipient, string $subject, array $context, string $idempotencyKey): array
    {
        require_once module_dir_path(KT_SAAS_MODULE, 'helpers/kt_saas_helper.php');

        $guardContext = [
            'event_key' => 'dms_auth_mail',
            'resource_type' => 'staff',
            'resource_id' => (string) ($context['related_id'] ?? ''),
            'recipient_scope' => 'tenant_admin',
            'branding_context' => 'tenant',
            'provider_context' => 'tenant_custom',
            'template_slug' => 'dms-auth-link',
            'tenant_id' => $tenantId,
            'dedupe_key' => $idempotencyKey,
            'recipient_email' => $recipient,
            'related_type' => (string) ($context['related_type'] ?? 'staff'),
            'related_id' => (string) ($context['related_id'] ?? ''),
        ];

        $guard = kt_saas_reserve_email_event('dms_auth_mail', $guardContext);
        if (empty($guard['allowed'])) {
            return [
                'success' => false,
                'duplicate' => !empty($guard['duplicate']),
                'message' => (string) ($guard['message'] ?? 'Duplicate mail request ignored.'),
                'dedupe_key' => (string) ($guard['dedupe_key'] ?? $idempotencyKey),
                'guard' => $guard,
            ];
        }

        $ctx = $this->providerService->resolveForTenant($tenantId, 'transactional');
        if (($ctx['provider'] ?? '') === 'blocked') {
            kt_saas_mark_email_event_failed('dms_auth_mail', (string) ($guard['dedupe_key'] ?? $idempotencyKey), (string) ($ctx['error'] ?? 'Provider is blocked.'), $context);

            return [
                'success' => false,
                'message' => (string) ($ctx['error'] ?? 'Provider is blocked.'),
                'provider' => (string) ($ctx['provider'] ?? 'blocked'),
                'guard' => $guard,
            ];
        }

        $ci = &get_instance();
        $ci->load->model('Emails_model');

        $ctx['tenant_id'] = $tenantId;
        $ctx['related_type'] = (string) ($context['related_type'] ?? 'staff');
        $ctx['related_id'] = (string) ($context['related_id'] ?? '');
        $ctx['event_key'] = 'dms_auth_mail';
        $ctx['dedupe_key'] = (string) ($guard['dedupe_key'] ?? $idempotencyKey);
        $this->providerService->applyRuntimeTransport($ctx);

        try {
            $ok = $ci->Emails_model->send_simple_email(
                $recipient,
                $subject,
                $this->renderHtmlMessage($context)
            );

            $messageId = method_exists($ci->Emails_model, 'get_last_send_message_id')
                ? trim((string) $ci->Emails_model->get_last_send_message_id())
                : '';
            $error = method_exists($ci->Emails_model, 'get_last_send_error')
                ? trim((string) $ci->Emails_model->get_last_send_error())
                : '';
            $errorCode = method_exists($ci->Emails_model, 'get_last_send_error_code')
                ? (int) $ci->Emails_model->get_last_send_error_code()
                : 0;

            if ($ok) {
                kt_saas_mark_email_event_sent('dms_auth_mail', (string) ($guard['dedupe_key'] ?? $idempotencyKey), $context);

                return [
                    'success' => true,
                    'provider' => (string) ($ctx['provider'] ?? ''),
                    'message_id' => $messageId,
                    'guard' => $guard,
                ];
            }

            $errorMessage = $error !== '' ? $error : 'Email send failed.';
            kt_saas_mark_email_event_failed('dms_auth_mail', (string) ($guard['dedupe_key'] ?? $idempotencyKey), $errorMessage, $context);

            return [
                'success' => false,
                'provider' => (string) ($ctx['provider'] ?? ''),
                'message_id' => $messageId !== '' ? $messageId : ('local:' . uniqid('mail_', true)),
                'error' => $errorMessage,
                'error_code' => $errorCode,
                'guard' => $guard,
            ];
        } finally {
            $this->providerService->applyRuntimeTransport(['transport' => null]);
        }
    }

    protected function renderHtmlMessage(array $context): string
    {
        $headline = htmlspecialchars((string) ($context['headline'] ?? ''), ENT_QUOTES, 'UTF-8');
        $message = nl2br(htmlspecialchars((string) ($context['message'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $actionUrl = htmlspecialchars((string) ($context['action_url'] ?? ''), ENT_QUOTES, 'UTF-8');
        $actionLabel = htmlspecialchars((string) ($context['action_label'] ?? 'Open link'), ENT_QUOTES, 'UTF-8');
        $loginUrl = htmlspecialchars((string) ($context['login_url'] ?? ''), ENT_QUOTES, 'UTF-8');
        $expiresIn = (int) ($context['expires_in_minutes'] ?? 60);
        $signature = htmlspecialchars((string) ($context['email_signature'] ?? ''), ENT_QUOTES, 'UTF-8');

        return '<h2>' . $headline . '</h2>'
            . '<p>' . $message . '</p>'
            . '<p><a href="' . $actionUrl . '" style="display:inline-block;padding:10px 16px;background:#0f5b9d;color:#ffffff;text-decoration:none;border-radius:4px;">' . $actionLabel . '</a></p>'
            . '<p><strong>Login page:</strong> <a href="' . $loginUrl . '">' . $loginUrl . '</a></p>'
            . '<p><strong>Link expires in:</strong> ' . $expiresIn . ' minutes.</p>'
            . '<p>' . $signature . '</p>';
    }

    protected function failure(string $code, string $message, int $status = 422, array $errors = [], array $meta = []): array
    {
        return array_merge([
            'success' => false,
            'code' => $code,
            'message' => $message,
            'status' => $status,
            'errors' => $errors,
        ], $meta);
    }
}
