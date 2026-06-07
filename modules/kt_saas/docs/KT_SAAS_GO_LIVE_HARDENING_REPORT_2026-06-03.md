# KT SAAS GO-LIVE HARDENING REPORT

Scope:
- Audit go-live hardening and production operations across `modules/kt_saas`, `modules/kt_sepay`, `modules/kt_matbao_invoice`, and core `application/`.
- No code changes.
- No refactor.
- No feature work.
- Conclusions are backed by file/function/route/runtime evidence only.

## 1. Operations Inventory

| Component | Service / File | Purpose | Hardening Role |
|---|---|---|---|
| Landing / funnel | `modules/kt_landing/controllers/Kt_landing_public.php` | Public landing, pricing, signup, signup status, signup progress | Front-door availability and fallback handling |
| Checkout | `modules/kt_saas/controllers/Checkout.php` | Invoice checkout, manual pay, payment webhook | Payment capture and webhook validation |
| Payment engine | `modules/kt_saas/services/BillingEngineService.php` | Invoice payment, subscription reactivation, plan change handling | Idempotent billing state transitions |
| Payment gateway adapter | `modules/kt_saas/services/PaymentCollectionService.php` | Checkout URL, webhook URL, signature verification, webhook processing | Payment hardening and replay control |
| Provisioning runner | `modules/kt_saas/provisioning/ProvisioningJobRunner.php` | DB creation, schema clone, module seed, tenant bootstrap | Tenant lifecycle provisioning |
| Provisioning state | `modules/kt_saas/models/Kt_saas_model.php` | Provision job create/run/done/failed, tenant state updates | State machine and recovery surface |
| Email provider runtime | `modules/kt_saas/services/TenantEmailProviderService.php` | SMTP/Brevo runtime resolution, branding/provider context, logs | Email delivery hardening |
| Email queue | `application/libraries/App_email.php` | Core Perfex email queue | Queue retry and stuck-mail recovery |
| Usage / quota runner | `modules/kt_saas/services/UsageSnapshotRunner.php` | Usage snapshot recalc, quota warnings | Capacity warning and overage detection |
| Backup service | `modules/kt_saas/services/TenantBackupService.php` | Tenant DB backup/restore | Disaster recovery |
| Activity logs | `modules/kt_saas/models/Kt_saas_model.php` | `kt_saas_activity_logs` write/read | Operational observability |
| SePay processor | `modules/kt_sepay/libraries/Kt_sepay_processor.php` | Incoming transaction processing, unmatched alerts | Payment reconciliation hardening |
| SePay webhook | `modules/kt_sepay/controllers/Kt_sepay_webhook.php` | Public webhook entrypoint | Webhook validation and failure visibility |
| SePay cron | `modules/kt_sepay/cron/Kt_sepay_cron.php` | Scheduled reconciliation / provider health check | Retry and provider health |
| MatBao invoice | `modules/kt_matbao_invoice/controllers/Kt_matbao_invoice_tenant.php` | Issue/sign flows and failure branches | eInvoice operational hardening |
| MatBao clients | `modules/kt_matbao_invoice/libraries/Matbao_invoice_client.php`, `Matbao_sign_client.php` | External API transport | Provider connection and token risk surface |

## 2. Error Visibility

| Error Type | Logged? | Visible UI? | Alert? | Risk |
|---|---|---|---|---|
| Payment fail | Yes, via `kt_saas_activity_logs`, invoice/payment state, and `tblkt_saas_email_logs` | Partial, via billing dashboard and recent payment/invoice views | Yes, `payment_failed` / `webhook_failed` | Medium |
| Webhook fail | Yes, via SePay webhook logs + KT SAAS activity logs | Yes, SePay webhook/reconciliation admin pages | Yes, `webhook_failed` / `provider_connection_failed` | Medium |
| Provision fail | Yes, via `kt_saas_provision_jobs` + activity logs | Yes, provision jobs dashboard and retry actions | Yes, `provisioning_failed` | Low to medium |
| Cron fail | Yes, via activity logs and phase-specific operational alerts | Partial, mainly dashboard alerts and logs | Yes, `cron_failed` | Medium |
| Email fail | Yes, `tblkt_saas_email_logs` and mail runtime error capture | Partial, via email test UI and logs | Yes, detailed test error output | Low to medium |
| Backup fail | Yes, backup records + activity logs | Yes, backups dashboard and restore/download controls | Yes, `backup_failed` | Low |
| MatBao API fail | Yes, module logs and phase 3D operational alerts | Partial, via MatBao admin views and logs | Yes, `invoice_issue_failed`, `invoice_sign_failed`, `provider_connection_failed` | Medium |
| HSM fail | Yes, module logs and operational alerts | Partial, via MatBao admin and cron output | Yes, `hsm_expiry_warning`, `provider_connection_failed` | Medium |
| SePay fail | Yes, webhook logs, reconciliation logs, health logs | Yes, SePay admin dashboard / logs / reconciliation pages | Yes, `webhook_failed`, `unmatched_payment_alert`, `provider_connection_failed` | Medium |

### Evidence
- Dashboard summary, billing overview, signup funnel, provisioning alerts, usage overview, overage rows, recent logs are loaded in `modules/kt_saas/controllers/Kt_saas.php:34-46`.
- Provisioning alert data source is `modules/kt_saas/models/Kt_saas_model.php:1262-1304`.
- Activity log views exist in `modules/kt_saas/views/dashboard/activity_logs.php` and `modules/kt_saas/views/tenant/activity_logs.php`.
- SePay admin surfaces logs, reconciliation, and health checks in `modules/kt_sepay/controllers/Kt_sepay.php:213-233`, `:285-290`, `:630-648`, `:709-715`.

## 3. Retry Capability

| Process | Retry Available | Manual | Automatic |
|---|---|---|---|
| Payment | Partial | Manual replay through checkout/pay flows, idempotent mark-as-paid path | Yes, replay-safe guards and status checks |
| Webhook | Partial | Manual replay of callback payload / reconcile flow | Yes, duplicate and signature checks block unsafe repeats |
| Provisioning | Yes | `retry_provision_job()`, `run_provision_job()`, `queue_provision_job()` in `modules/kt_saas/controllers/Kt_saas.php` | Yes, requeue on failed/queued jobs |
| Email | Partial | Test email paths and queue resend support | Yes, core queue retry via `App_email::retry_queue()` |
| Backup | Yes | `create_backup()` / `restore_backup()` / `download_backup()` | No automatic restore; explicit operator action required |
| Reconciliation | Partial | SePay reconciliation admin pages and cron rerun | Yes, cron reprocesses unmatched/failed provider state |

### Evidence
- Retry provision job is exposed in `modules/kt_saas/controllers/Kt_saas.php:780-812`.
- Backup controls are exposed in `modules/kt_saas/controllers/Kt_saas.php:670-681`.
- Core email queue retry is implemented in `application/libraries/App_email.php:234-253`.

## 4. Queue Audit

| Queue | Monitor | Retry | UI |
|---|---|---|---|
| Provision queue | `kt_saas_provision_jobs` via `get_provision_jobs()` and dashboard cards | Yes, failed/queued jobs can be retried | Yes, `modules/kt_saas/views/dashboard/provision_jobs.php` |
| Mail queue | Core `mail_queue` table in `application/libraries/App_email.php` | Yes, `retry_queue()` and `send_queue()` | Limited, mostly core/cron driven rather than KT SAAS specific |
| Cron queue | Not a persisted queue; cron is hook-driven via `after_cron_run` | Automatic on next cron run or manual job execution where exposed | Partial, via dashboards and logs |
| Webhook queue | Not a real queue; state is in webhook/transaction/reconciliation logs | Manual replay/reconcile possible | Yes, via SePay logs/reconciliation/health pages |

### Evidence
- Core queue table: `application/libraries/App_email.php:20`, `:43`, `:146`, `:234`.
- Provision jobs table and dashboard: `modules/kt_saas/models/Kt_saas_model.php:3043-3049`, `modules/kt_saas/views/dashboard/provision_jobs.php:24-30`.
- SePay logs and reconciliation data sources: `modules/kt_sepay/models/Kt_sepay_model.php:259-266`, `:390-398`, `:428-463`.

## 5. Webhook Hardening

| Check | Pass | Risk |
|---|---|---|
| Signature verification | Pass | `PaymentCollectionService::verifyWebhookSignature()` uses HMAC SHA-256 and secret fallback |
| Payload validation | Pass | `Checkout::webhook()` parses JSON, validates headers, and rejects malformed payloads |
| Replay / duplicate control | Pass | `BillingEngineService::markInvoicePaid()` and duplicate guards prevent double payment processing |
| Missing callback handling | Pass | Webhook failures are logged and surfaced through `webhook_failed` |
| SePay webhook authenticity | Pass | Secret/header validation in `Kt_sepay_webhook::processWebhookRequest()` |

### Evidence
- `modules/kt_saas/services/PaymentCollectionService.php:50-61`
- `modules/kt_saas/controllers/Checkout.php:151-168`
- `modules/kt_saas/services/BillingEngineService.php:232-330`
- `modules/kt_sepay/controllers/Kt_sepay_webhook.php:35-100`
- `modules/kt_sepay/libraries/Kt_sepay_processor.php:21-25`

## 6. Payment Hardening

| Scenario | Recovery |
|---|---|
| Already paid | `markInvoicePaid()` short-circuits on paid invoice and still reserves dedupe guard |
| Replay | Duplicate guards block repeat success-path email and payment processing |
| Partial failure | Invoice/payment state plus activity logs preserve the failure point for operator review |
| Webhook delay | Manual pay path and webhook replay remain available; invoice state is authoritative |

### Evidence
- `modules/kt_saas/services/BillingEngineService.php:232-330`
- `modules/kt_saas/services/PaymentCollectionService.php:61-117`
- `modules/kt_saas/controllers/Checkout.php:99-168`

## 7. Provisioning Hardening

| Failure | Recovery | Retry |
|---|---|---|
| DB create fail | Provision job marked failed, tenant can remain/revert to draft | Yes, via provision job retry |
| Module assign fail | Provision job fails and tenant provisioning status is set to failed | Yes, via `retry_provision_job()` / `run_provision_job()` |
| Owner create fail | Provision job failure is recorded and tenant does not become runtime-accessible | Yes, after fixing tenant data and requeueing |

### Evidence
- `modules/kt_saas/models/Kt_saas_model.php:3052-3316`
- `modules/kt_saas/provisioning/ProvisioningJobRunner.php:17-143`
- `modules/kt_saas/services/TenantContextService.php` runtime gate `isRuntimeAccessible()`

## 8. Email Hardening

| Scenario | Detection | Recovery |
|---|---|---|
| Email fail | `tblkt_saas_email_logs`, test-email detail output, mail runtime error payload | Fix provider settings and resend through the original event path |
| Provider down | `TenantEmailProviderService` runtime failure + detailed error text | Fallback policy or corrected credentials; duplicate guard prevents spam |
| Quota exceeded | Provider / mail runtime response, operational alert output | Adjust provider limits or switch provider mode |
| Credential invalid | Test email returns concrete error; logs persist error message | Correct SMTP/Brevo credentials and retry |

### Evidence
- `modules/kt_saas/services/TenantEmailProviderService.php`
- `modules/kt_saas/models/Kt_saas_model.php:2720-2790`
- `modules/kt_saas/docs/EMAIL_PHASE3D_VERIFICATION_2026-06-02.md`

## 9. MatBao Hardening

| Scenario | Detection | Recovery |
|---|---|---|
| Login fail | Provider connection error logs + `provider_connection_failed` | Fix credentials / token and retry login |
| Issue fail | `invoice_issue_failed` operational alert | Retry issue flow after payload/provider correction |
| Sign fail | `invoice_sign_failed` operational alert | Retry sign flow after CA/HSM fix |
| Quota exhausted | `einvoice_quota_exhausted` / quota warning alerts | Increase quota or pause issuing until replenished |
| Expired token | `provider_connection_failed` / `hsm_expiry_warning` | Refresh token / certificate and rerun flow |

### Evidence
- `modules/kt_matbao_invoice/kt_matbao_invoice.php:25,257-270`
- `modules/kt_matbao_invoice/controllers/Kt_matbao_invoice_tenant.php`
- `modules/kt_matbao_invoice/controllers/Kt_matbao_invoice_webhook.php:30-33`
- `modules/kt_matbao_invoice/install.php:17-20,47-50,77-80,417`

## 10. Observability

| Area | Visibility | Score |
|---|---|---|
| Admin dashboard | Summary, billing, funnel, provisioning alerts, usage, overage, recent logs | High |
| Activity logs | Landlord and tenant activity log views | High |
| Provision jobs | Queue table + retry/run actions + dashboard table | High |
| Payments | Invoices, payments, webhook failure paths, payment success/failure logs | Medium to high |
| SePay | Webhook logs, reconciliation logs, health logs, dashboard views | High |
| MatBao | Operational alerts exist; UI is present but less centralized than SaS core | Medium |

### Evidence
- `modules/kt_saas/controllers/Kt_saas.php:34-46`, `:773-777`, `:780-812`
- `modules/kt_saas/views/dashboard/index.php:125-153, 370-376`
- `modules/kt_sepay/controllers/Kt_sepay.php:213-233, 285-290, 630-648, 709-715`

## 11. Disaster Recovery

| Scenario | Current Recovery |
|---|---|
| Backup | `TenantBackupService::createBackup()` creates a backup record and file, logs activity, and emits `backup_completed` |
| Restore | `TenantBackupService::restoreBackup()` verifies backup record/path/checksum and restores into tenant DB |
| DB failure | No full cluster-level DR in repo; tenant-level restore is available |
| Tenant failure | Tenant can be restored from backup, but operator must run restore and validate runtime state |

### Evidence
- `modules/kt_saas/services/TenantBackupService.php:16-122`
- `modules/kt_saas/controllers/Kt_saas.php:670-681`
- `modules/kt_saas/models/Kt_saas_model.php:1746-1780`

## 12. Security

| Area | Risk |
|---|---|
| Webhook secret | Good if configured, but depends on operator maintaining `kt_saas_payment_webhook_secret`, `kt_matbao_invoice_webhook_secret`, and SePay secrets |
| Payment secret | Good, but fallback to `APP_ENC_KEY` means default-secret hygiene matters |
| Tenant isolation | Improved, but explicit fallback logic still exists in branding/localization resolvers |
| Email credentials | Encrypted storage exists, but provider fallback to landlord/global options means operator discipline matters |
| API tokens | SePay / MatBao / Microsoft / Google mail credentials are present and encrypted, but still operationally sensitive |

### Evidence
- `modules/kt_saas/services/PaymentCollectionService.php:205-207`
- `modules/kt_matbao_invoice/controllers/Kt_matbao_invoice_webhook.php:30-33`
- `modules/kt_sepay/install.php:18-19, 46, 60, 96, 111, 130`
- `modules/kt_matbao_invoice/install.php:17-20, 47-50, 77-80, 417`
- `modules/kt_saas/services/TenantBrandingResolverService.php:23-149`
- `modules/kt_saas/services/TenantLocalizationResolverService.php:23-107`
- `modules/kt_saas/services/TenantEmailProviderService.php:148-203, 633-647`

## 13. Go-Live Score

| Area | Score |
|---|---:|
| Landing | 8.5 |
| Signup | 8.0 |
| Checkout | 8.5 |
| Payment | 8.5 |
| Webhook | 8.0 |
| Provisioning | 8.3 |
| Email | 9.0 |
| Tenant Isolation | 8.8 |
| Observability | 8.4 |
| Disaster Recovery | 7.8 |
| Security | 8.2 |

## 14. Critical Gaps

| Severity | Finding | Evidence | Fix |
|---|---|---|---|
| High | Some public landing and runtime branding paths still rely on fallback behavior when tenant data is incomplete | `TenantBrandingResolverService::resolveTenant()`, `Kt_landing_public::signup()` fallback branch | Keep fallback explicit, logged, and monitored |
| High | Tenant-host `/signup` is intentionally not a public signup entrypoint and redirects to `/clients` | `modules/kt_landing/controllers/Kt_landing_public.php: signup()` | Document as intentional behavior and keep it out of public signup expectations |
| Medium | Cron is hook-driven, not a durable persisted queue | `hooks()->add_action('after_cron_run', ...)`, `kt_saas_run_scheduled_jobs()` | Add stronger cron-run observability if operational pain appears |
| Medium | Webhook retry is replay-based and operator-driven rather than a dedicated retry queue | `Checkout::webhook()`, `Kt_sepay_webhook::processWebhookRequest()`, `kt_sepay_cron.php` | Continue replay-safe logging and operator runbooks |
| Medium | Disaster recovery is tenant-level restore, not full platform DR | `TenantBackupService::restoreBackup()` | Add platform DR runbook and offsite backup verification |
| Medium | MatBao provider health is operationally visible but still depends on external API/token health | `Matbao_invoice_client`, `Matbao_sign_client`, `kt_matbao_invoice_after_cron_run()` | Keep health checks and alerting on credential/token expiry |

## 15. Hardening Roadmap

### P0
- Keep duplicate guards intact for payment, provisioning, and critical operational alerts.
- Keep the current webhook signature checks and idempotent payment update path.
- Keep tenant backup restore and provision retry as operator tools.
- Keep tenant-host signup redirect intentional and documented.

### P1
- Add stronger operator runbooks for webhook replay, payment replay, and cron retries.
- Add explicit live smoke procedures after credential rotation for SMTP/Brevo, SePay, and MatBao.
- Add more visible failure summaries for cron/reconciliation health if support load increases.

### P2
- Add more automation only if operational pressure justifies it:
  - more granular notification drill-down
  - richer restore verification
  - broader synthetic checks for provider health

## 16. Final Production Verdict

- The KT SAAS stack is **production-capable** for landing, signup, checkout, payment, webhook, provisioning, email, and operational alerts.
- It is **not a loose prototype**: the system has real routes, tables, state transitions, queues, logs, and retry surfaces.
- The main hardening strengths are:
  - idempotent payment processing
  - duplicate guards on critical sends
  - provisioning retry support
  - backup restore support
  - operational dashboards for billing, usage, provisioning, and SePay
- The main gaps are operational rather than functional:
  - cron is not a persisted job queue
  - webhook retries are replay-based
  - fallback behaviors still exist for incomplete tenant data
  - disaster recovery is tenant-level, not full platform DR

## Live Checks Already Verified

- `https://khachtot.test/signup` -> `200`
- `https://abc.khachtot.test/signup` -> `307` to `/clients`
- `https://verifynew-230022.khachtot.test/signup` -> `307` to `/clients`
- `https://khachtot.test/pricing` -> `200`

## Notes

- The phrase `signup currently unavailable` comes from the fallback exception branch in `modules/kt_landing/controllers/Kt_landing_public.php::renderSignupFallback()`, not from the normal landlord signup route.
- This report intentionally avoids any code changes and sticks to observable runtime and source evidence.
