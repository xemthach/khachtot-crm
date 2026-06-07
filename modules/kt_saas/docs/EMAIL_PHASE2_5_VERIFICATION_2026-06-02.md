# EMAIL PHASE 2.5 VERIFICATION PASS

Scope:
- Verify the phase-1 email architecture fixes against the current codebase.
- No refactor, no UI redesign, no new mail feature work.
- Static verification plus live smoke for global/tenant mail paths in this pass.

## Verification checklist

### 1. Provider UI state
- [x] Global email provider UI is conditional by provider state in `modules/kt_saas/views/dashboard/settings.php`.
- [x] Tenant email provider UI is conditional by provider state in `modules/kt_saas/views/tenant/settings.php`.
- [x] Backend validation exists in `modules/kt_saas/models/Kt_saas_model.php` for provider-specific fields.
- [x] `system_smtp` keeps sender / reply-to / fallback policy and hides Brevo-specific fields.
- [x] `brevo_smtp` exposes SMTP host / port / encryption / username / password and hides Brevo API key.
- [x] `brevo_api` exposes Brevo API key plus sender identity / reply-to and hides SMTP fields.

### 2. Test email output
- [x] Global test email returns provider, transport, sender, recipient, and message ID when available.
- [x] Tenant test email returns provider, transport, sender, recipient, and message ID when available.
- [x] Failure path returns detailed mailer error text instead of a generic toast only.
- [x] `application/models/Emails_model.php` exposes `get_last_send_message_id()`, `get_last_send_error()`, and `get_last_send_error_code()`.
- [x] Live global smoke completed successfully against `brevo_api`.
- [x] Live tenant smoke completed successfully against tenant `verifynew-230022` via runtime resolver.

### 3. Template cleanup
- [x] `kt_saas_sync_email_template_states_once()` activates:
  - `estimate-request-received-to-user`
  - `new-web-to-lead-form-submitted`
- [x] The same sync deactivates:
  - `inventory-warning-to-staff`
  - `tenant-expiration-reminder`
  - `we-found-your-tenant-url`
- [x] The cleanup is guarded to run once in landlord context.

### 4. Resolver enforcement
- [x] `TenantEmailProviderService` is the runtime source of truth for provider selection.
- [x] Runtime transport and runtime identity are injected before send.
- [x] `send_simple_email()` consumes the runtime identity when present.
- [x] KT SAAS hooks are wired for:
  - `email_template_sent`
  - `failed_to_send_email_template`
  - `simple_email_sent`
  - `simple_email_failed`

### 5. Email logging
- [x] `tblkt_saas_email_logs` includes:
  - `tenant_id`
  - `provider`
  - `from_email`
  - `recipient`
  - `subject`
  - `status`
  - `error_message`
  - `related_type`
  - `related_id`
  - `created_at`
  - `sent_at`
- [x] Log insertion is done without secret leakage.
- [x] Live tenant log row verified in `tblkt_saas_email_logs`:
  - `tenant_id=3`
  - `provider=brevo_api`
  - `status=sent`
  - `related_type=simple_email`
  - `message_id=<202606020908.49042374738@smtp-relay.mailin.fr>`

## Static checks executed
- `php -l modules/kt_saas/controllers/Kt_saas.php`
- `php -l modules/kt_saas/models/Kt_saas_model.php`
- `php -l modules/kt_saas/services/TenantEmailProviderService.php`
- `php -l application/models/Emails_model.php`
- `php -l modules/kt_saas/kt_saas.php`

Result:
- All listed files passed PHP syntax lint.

## Remaining risks
- Live browser smoke is still useful for UI-level confirmation, but the mail path itself has been verified for both global and tenant smoke in this pass.
- If credentials rotate or outbound access changes, the smoke needs to be re-run.

## Checklist after this phase
- [x] Provider UI state verified
- [x] Test email output verified
- [x] Template cleanup verified
- [x] Resolver enforcement verified
- [x] Email logging verified
- [x] Static lint verified
- [x] Global live smoke verified
- [x] Tenant live smoke verified
