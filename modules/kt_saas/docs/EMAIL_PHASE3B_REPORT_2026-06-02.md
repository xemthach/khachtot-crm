# EMAIL PHASE 3B REPORT

Scope:
- P0 templates only.
- No mail-engine refactor.
- No provider/SMTP/Brevo changes.
- No notification center.
- No preferences UI.
- No P1/P2/P3 templates.

## 1. Files changed

### New mail classes
- `application/libraries/mails/Tenant_welcome.php`
- `application/libraries/mails/Tenant_provisioning_completed.php`
- `application/libraries/mails/Tenant_provisioning_failed.php`
- `application/libraries/mails/Payment_success.php`
- `application/libraries/mails/Payment_failed.php`

### Runtime / registry / merge fields
- `modules/kt_saas/services/EmailBrandingContextResolverService.php`
- `modules/kt_saas/services/EmailTriggerRegistryService.php`
- `modules/kt_saas/services/EmailDuplicateGuardService.php`
- `modules/kt_saas/libraries/merge_fields/Kt_saas_merge_fields.php`
- `modules/kt_saas/services/TenantEmailProviderService.php`

### Orchestration / flow wiring
- `modules/kt_saas/models/Kt_saas_model.php`
- `modules/kt_saas/services/BillingEngineService.php`
- `modules/kt_saas/services/PaymentCollectionService.php`
- `modules/kt_saas/provisioning/ProvisioningJobRunner.php`
- `modules/kt_saas/kt_saas.php`
- `modules/kt_saas/helpers/kt_saas_helper.php`
- `modules/kt_saas/install.php`

## 2. Templates created

### Seeded template keys
- `tenant_welcome`
- `tenant_provisioning_completed`
- `tenant_provisioning_failed`
- `payment_success`
- `payment_failed`

### Seed behavior
- English and Vietnamese rows are seeded.
- Vietnamese content is UTF-8 and contains accented text.
- Seed runs once in landlord context through `kt_saas_seed_phase3b_email_templates_once()`.
- Existing rows are updated in place when the template key already exists.

### Template context
- Billing / payment / provisioning / welcome mail uses landlord branding context.
- No tenant branding is used for landlord-originated SaaS billing mail.

## 3. Mail classes created

### Class mapping
- `Tenant_welcome` -> `tenant_welcome`
- `Tenant_provisioning_completed` -> `tenant_provisioning_completed`
- `Tenant_provisioning_failed` -> `tenant_provisioning_failed`
- `Payment_success` -> `payment_success`
- `Payment_failed` -> `payment_failed`

### Shared behavior
- All classes extend `App_mail_template`.
- All classes use the `kt_saas_merge_fields` registry.
- All classes resolve recipients from event context.
- All classes are wired through `TenantEmailProviderService`.

### Specific notes
- `Tenant_provisioning_failed` supports CC recipients when the landlord ops address is passed in context.
- `Payment_success` and `Payment_failed` support invoice-related merge fields.
- `Tenant_welcome` uses workspace URL context for onboarding CTA.

## 4. Trigger wiring

### Payment success
- Wired from `BillingEngineService::markInvoicePaid()`.
- Duplicate guard applied with event key `payment_success`.
- Replay-safe path: existing paid / already paid responses cannot resend the success mail.

### Payment failed
- Wired from `PaymentCollectionService::processWebhook()`.
- Failure statuses now dispatch `payment_failed`.
- Landlord context is used for the billing/payment failure path.

### Provisioning completed
- Wired from `ProvisioningJobRunner::execute()` and `Kt_saas_model::mark_provision_job_done()`.
- Duplicate guard applied with event key `provisioning_completed`.
- Replay-safe path is preserved for repeated provisioning success responses.

### Provisioning failed
- Wired from `Kt_saas_model::mark_provision_job_failed()`.
- Dispatch includes tenant, plan, workspace, and error context.

### Tenant welcome
- Wired from successful provisioning flow and tenant activation flow.
- Uses landlord context and workspace URL.

### Email path guarantee
- All five P0 flows go through `TenantEmailProviderService`.
- No direct mail send path was added for these flows outside the resolver.

## 5. Duplicate guard verification

### Guarded events
- `payment_success`
- `provisioning_completed`

### Guard behavior
- The guard reserves `event_key + dedupe_key` before dispatch.
- If the same event is already `reserved` or `sent`, the send is blocked.
- If a previous attempt failed, the event can recover and retry.

### Replay safety
- Payment replay does not resend `payment_success`.
- Provisioning replay does not resend `provisioning_completed`.

### Guard storage
- Guard rows are stored in `kt_saas_email_event_guards`.

## 6. Branding verification

### Branding rule
- SaaS billing, payment, provisioning, and welcome mail use landlord branding context.
- Tenant branding is not used for landlord-originated SaaS billing mail.

### Runtime propagation
- `TenantEmailProviderService` carries runtime branding/provider context with the dispatch.
- Runtime metadata includes:
  - `kt_saas_mail_runtime_branding_context`
  - `kt_saas_mail_runtime_provider_context`
  - `kt_saas_mail_runtime_tenant_id`
  - `kt_saas_mail_runtime_related_type`
  - `kt_saas_mail_runtime_related_id`

### Result
- Mail delivery now has explicit branding context rather than relying on incidental global options.

## 7. Email log verification

### Log target
- `tblkt_saas_email_logs`

### Logged fields
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

### Log behavior
- All P0 event sends route through the KT SAAS runtime logger.
- Secret material is not written into logs.
- Related object metadata is carried into log rows when available.

## 8. Test results

### Static checks
- `php -l modules/kt_saas/services/TenantEmailProviderService.php`
- `php -l modules/kt_saas/libraries/merge_fields/Kt_saas_merge_fields.php`
- `php -l modules/kt_saas/services/EmailDuplicateGuardService.php`
- `php -l modules/kt_saas/models/Kt_saas_model.php`
- `php -l modules/kt_saas/install.php`
- `php -l modules/kt_saas/services/BillingEngineService.php`
- `php -l modules/kt_saas/services/PaymentCollectionService.php`
- `php -l modules/kt_saas/provisioning/ProvisioningJobRunner.php`
- `php -l modules/kt_saas/kt_saas.php`
- `php -l modules/kt_saas/helpers/kt_saas_helper.php`
- `php -l application/libraries/mails/Tenant_welcome.php`
- `php -l application/libraries/mails/Tenant_provisioning_completed.php`
- `php -l application/libraries/mails/Tenant_provisioning_failed.php`
- `php -l application/libraries/mails/Payment_success.php`
- `php -l application/libraries/mails/Payment_failed.php`

### Result
- All listed files passed PHP syntax lint.

### Verification scope
- Code-level dispatch wiring is in place.
- No live browser smoke was run in this phase.

## 9. Remaining risks

- Live credential-dependent delivery still needs browser/runtime smoke with the real provider.
- Final template wording can still be adjusted after reviewing the seeded mail rows in the DB.
- If webhook replay behavior changes upstream, the duplicate guard should be rechecked against those callbacks.
- If ops recipients are not configured in landlord settings, failed provisioning mail will still go to the primary owner only.

