# KT SAAS GO-LIVE RUNBOOK

Scope:
- Production operations runbook and go-live smoke checklist for KT SAAS.
- No code changes.
- No refactor.
- No feature work.
- Procedures are based on existing runtime paths, dashboards, logs, and services already audited.

## 1. Pre-Go-Live Checklist

| Item | Expected | Status |
|---|---|---|
| Primary domain | `khachtot.test` resolves correctly | Verify |
| Tenant wildcard DNS | `*.khachtot.test` resolves to app host | Verify |
| SSL | Valid certificate on landlord and tenant hosts | Verify |
| Database credentials | Landlord DB and tenant DB credentials valid | Verify |
| File permissions | Tenant uploads, backup directories, and cache writable | Verify |
| Cron | Platform cron executes `after_cron_run` hooks | Verify |
| Queue | Core mail queue and KT SAAS provision jobs accessible | Verify |
| Backup storage | Tenant backup path writable and retention configured | Verify |
| SMTP / Brevo | Global provider test succeeds | Verify |
| SePay | API token and webhook secret configured | Verify |
| MatBao | HDDT / CA settings configured and reachable | Verify |
| Webhook secrets | `kt_saas_payment_webhook_secret`, SePay secret, MatBao webhook secret set | Verify |
| Tenant default plan | Default public plan is published and active | Verify |
| Landing signup | Public landing signup enabled on landlord host | Verify |
| Pricing plans | Public pricing shows active public plans only | Verify |
| Payment mode | Production gateway settings enabled as intended | Verify |

### Pre-Go-Live operator checks
- Confirm `/pricing` renders on landlord host.
- Confirm `/signup` renders on landlord host.
- Confirm tenant-host `/signup` redirects to `/clients` by design.
- Confirm `kt_saas_activity_logs`, provision jobs, backups, and billing dashboards are reachable in admin.
- Confirm test email and provider health checks return detailed success/failure output.

## 2. Smoke Test Checklist

| Test | Steps | Expected |
|---|---|---|
| Landing loads | Open landlord home page | HTTP 200, landlord branding |
| Pricing loads | Open landlord pricing page | HTTP 200, public plans visible |
| Signup loads | Open landlord signup page | Signup wizard loads |
| Create test tenant | Submit one controlled test signup | Draft tenant or provisioning queue created |
| Create checkout invoice | Continue signup/payment flow | Invoice generated or checkout URL returned |
| Payment success | Complete a controlled successful payment | Invoice marked paid, subscription reactivated |
| Payment replay | Replay same success callback | Duplicate guard blocks double processing |
| Provisioning completed | Run or complete provisioning job | Tenant becomes active and welcome mail dispatched |
| Tenant login | Open tenant login | Tenant branding visible |
| Tenant settings isolation | Open tenant settings | Tenant branding and values isolated |
| Invoice PDF branding | Render invoice HTML/PDF | Tenant logo/name/currency visible |
| Email delivery | Send global and tenant test emails | Provider returns detailed result |
| SePay webhook | Send controlled webhook payload | Signature validation and payment flow work |
| MatBao test connection | Run module health/test connection | Provider health or failure log visible |
| Backup create | Trigger tenant backup | Backup record and file created |
| Restore test | Restore a test backup | Tenant DB restored and health confirmed |

## 3. Payment / Webhook Runbook

### Where to look
- `modules/kt_saas/controllers/Checkout.php`
- `modules/kt_saas/services/PaymentCollectionService.php`
- `modules/kt_saas/services/BillingEngineService.php`
- `modules/kt_saas/models/Kt_saas_model.php`
- `modules/kt_sepay/controllers/Kt_sepay_webhook.php`
- `modules/kt_sepay/libraries/Kt_sepay_processor.php`

### How to read failures
- Use admin billing dashboards and recent invoices/payments.
- Use `tblkt_saas_email_logs` for `payment_failed` and `webhook_failed`.
- Use SePay webhook/reconciliation/health logs for provider-side failures.

### Replay webhook
- Re-send the same payload only after confirming signature and invoice reference.
- `PaymentCollectionService::verifyWebhookSignature()` validates HMAC SHA-256 signature.
- Duplicate payment processing is blocked by:
  - invoice status checks
  - payment reference uniqueness
  - email duplicate guard

### Confirm invoice paid
- Check invoice status in `kt_saas_invoices`.
- Check payment row in `kt_saas_payments`.
- Confirm `payment_success` email log row exists.

### Handle `already_paid`
- Treat as idempotent success.
- Do not create a second payment row.
- Do not manually resend success mail unless the guard is intentionally reset.

### Handle unmatched transaction
- Check SePay transaction logs and reconciliation logs.
- Confirm transaction cannot be matched to an invoice reference.
- Escalate if it persists across repeated reconciliation runs.

### Check duplicate guard
- Verify `kt_saas_email_event_guards` has the relevant event key / dedupe key.
- If a duplicate appears, inspect the exact dedupe key before any retry.

## 4. Provisioning Runbook

### Where to look
- `modules/kt_saas/controllers/Kt_saas.php`
- `modules/kt_saas/models/Kt_saas_model.php`
- `modules/kt_saas/provisioning/ProvisioningJobRunner.php`

### View provision jobs
- Use the admin provision jobs page.
- Inspect statuses: `queued`, `running`, `done`, `failed`.

### Retry failed provision
- Use the provision jobs retry action for failed or queued jobs.
- Retry only after fixing the underlying issue:
  - DB creation
  - owner/admin creation
  - module assignment
  - entitlement sync

### Failure handling
- DB create fail:
  - job is marked failed
  - tenant should remain non-runtime accessible
- Owner create fail:
  - fix tenant metadata or owner records, then requeue
- Module assign fail:
  - check module registry and permissions, then retry

### Confirm tenant active
- Tenant provisioning status must be `done`.
- Tenant runtime status must be in an accessible state.
- Tenant login and settings should render with tenant branding.

## 5. Tenant Isolation Runbook

### Where to look
- `modules/kt_saas/services/TenantBrandingResolverService.php`
- `modules/kt_saas/services/TenantLocalizationResolverService.php`
- `application/helpers/template_helper.php`
- `modules/kt_landing/controllers/Kt_landing.php`
- `modules/kt_landing/controllers/Kt_landing_public.php`

### Test tenant A/B
- Compare two tenant hosts:
  - `abc.khachtot.test`
  - `verifynew-230022.khachtot.test`

### Check branding
- Confirm logo, company name, favicon, footer, and login header are tenant-specific.
- Confirm invoice HTML/PDF shows tenant logo and tenant company data.

### Check currency / localization
- Confirm tenant currency and language are scoped to the tenant.
- Confirm date/time formats are tenant-appropriate.

### Check email branding
- Send tenant test email and confirm sender identity / branding context.

### Check tenant settings
- Tenant settings pages must not show landlord branding unless explicit fallback is in use.

## 6. Email Runbook

### Where to look
- `modules/kt_saas/services/TenantEmailProviderService.php`
- `modules/kt_saas/models/Kt_saas_model.php`
- `tblkt_saas_email_logs`
- `kt_saas_email_event_guards`

### Test global email
- Use global email test in KT SAAS settings.
- Expect provider, transport, sender, recipient, and detailed error or message id.

### Test tenant email
- Use tenant email test from tenant settings.
- Expect tenant-specific identity and detailed result.

### View email logs
- Inspect `tblkt_saas_email_logs`.
- Confirm `tenant_id`, `provider`, `recipient`, `subject`, `status`, `error_message`, `related_type`, and `related_id`.

### Handle Brevo fail
- Check API key, sender, reply-to, and provider mode.
- Confirm provider context in the resolver.

### Handle SMTP fail
- Check host, port, encryption, username/password, and outbound connectivity.

### Handle UTF-8 template errors
- Verify template content is stored in UTF-8 and the mail class slug exists.

### Handle missing merge fields
- Confirm merge fields are registered in `Kt_saas_merge_fields.php`.
- Re-run the send path after fixing the field source.

## 7. SePay Runbook

### Where to look
- `modules/kt_sepay/controllers/Kt_sepay.php`
- `modules/kt_sepay/controllers/Kt_sepay_webhook.php`
- `modules/kt_sepay/libraries/Kt_sepay_processor.php`
- `modules/kt_sepay/models/Kt_sepay_model.php`

### Check webhook secret
- Confirm the configured webhook secret matches the request header secret.

### Check transaction logs
- Inspect webhook logs, reconciliation logs, and health logs.

### Reconciliation
- Run or inspect reconciliation jobs via SePay admin pages.

### Handle unmatched
- Confirm transaction payload does not match an invoice/reference.
- Use reconciliation / manual match flow only after validating the payload.

### Handle duplicate transaction
- Confirm uniqueness by SePay reference and invoice/payment status.
- Do not manually duplicate capture if the payment is already applied.

### Handle provider connection failed
- Check API token validity, network access, and provider status.
- Re-run health check after fixing credentials/connectivity.

## 8. MatBao / HSM Runbook

### Where to look
- `modules/kt_matbao_invoice/controllers/Kt_matbao_invoice_tenant.php`
- `modules/kt_matbao_invoice/controllers/Kt_matbao_invoice_webhook.php`
- `modules/kt_matbao_invoice/models/Kt_matbao_invoice_model.php`
- `modules/kt_matbao_invoice/libraries/Matbao_invoice_client.php`
- `modules/kt_matbao_invoice/libraries/Matbao_sign_client.php`

### Test connection
- Use module health / connection test paths.
- Confirm provider returns a valid login / token response.

### Check quota
- Monitor quota warning alerts and quota exhausted alerts.

### Check token
- Verify token expiry and refresh behavior.

### Handle issue fail
- Inspect invoice issue error logs and retry after fixing payload / credential / provider issue.

### Handle sign fail
- Inspect signing error logs and retry after fixing CA/HSM token or certificate state.

### Handle HSM expiry
- Renew certificate/token before expiry warning becomes an outage.

### Handle quota exhausted
- Stop issuing until quota is replenished or switching strategy is approved.

## 9. Backup / Restore Runbook

### Where to look
- `modules/kt_saas/services/TenantBackupService.php`
- `modules/kt_saas/controllers/Kt_saas.php`
- `kt_saas_backups` table

### Create backup
- Run tenant backup from admin backups page.

### Download backup
- Verify file exists and download succeeds.

### Verify checksum
- Confirm backup checksum is populated and file integrity is valid.

### Restore tenant
- Restore only a known-good backup with correct tenant id.

### Confirm after restore
- Tenant DB is accessible.
- Tenant login works.
- Branding, email, and billing state are consistent.

## 10. Incident Response

| Incident | Detection | Action | Escalation |
|---|---|---|---|
| Payment webhook down | Payment failure logs, webhook logs, missing invoice updates | Validate signature/secret and provider reachability | Platform/SRE |
| SePay down | SePay health logs and reconciliation failures | Pause automated reconciliation, use manual review | Payment owner / SRE |
| MatBao down | MatBao operational alerts and provider failure logs | Pause issuing/signing, confirm token/CA state | Finance ops / SRE |
| Brevo down | Email test fails, email logs show provider failure | Switch to fallback provider mode if approved | Platform admin |
| Provisioning queue stuck | Provision jobs dashboard shows queued/running backlog | Retry or rerun job after fixing infra issue | Platform admin |
| Cron stopped | Missing cron-driven alerts, stale job timestamps | Restore cron and rerun job queue | SRE |
| Tenant login down | Tenant host returns auth/runtime errors | Check tenant status, provisioning status, branding runtime | Platform admin |
| Tenant branding bleed | Tenant host shows landlord brand | Verify runtime resolver and tenant source data | Platform admin |
| Database restore required | Tenant DB corruption or failed restore test | Restore known-good backup | SRE / platform admin |

## 11. Rollback Plan

### Module update rollback
- Revert the module version/package if a post-deploy regression appears.
- Prefer versioned deploy artifacts over ad hoc file edits.

### Database migration rollback
- Restore schema from known-good backup if a migration breaks runtime paths.
- Validate tenant-specific tables before resuming traffic.

### Tenant backup restore
- Use `restoreBackup()` for tenant-level recovery.

### Disable public signup
- Temporarily disable public signup if funnel exceptions surface.

### Switch payment to manual
- Use manual capture mode if gateway/webhook instability affects production.

### Disable MatBao issuing
- Pause issuance/signing if provider health is broken.

### Fallback email provider
- Use the safe fallback provider mode already supported by KT SAAS settings and resolver paths.

## 12. Final Go-Live Approval Criteria

- Landing, pricing, and signup are reachable and behave as expected on landlord host.
- Tenant-host routing, branding, and settings are isolated.
- Payment success and replay paths are idempotent.
- Webhook signature validation and duplicate guard are active.
- Provisioning jobs can be queued, retried, and completed.
- Email test flows return detailed diagnostics and write logs.
- SePay and MatBao operational pages show logs and health state.
- Backup and restore can be executed on a test tenant.
- Cron is running and producing fresh usage/provisioning/billing updates.
- Operators know how to read dashboards, logs, and retry surfaces before traffic is opened broadly.

