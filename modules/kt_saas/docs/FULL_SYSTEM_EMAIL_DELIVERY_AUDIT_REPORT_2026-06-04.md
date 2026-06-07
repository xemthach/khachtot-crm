# FULL SYSTEM EMAIL DELIVERY AUDIT REPORT

## Executive Summary

Brevo is connected and the simple-email path can send successfully, but the business email system is not proven end-to-end yet. The strongest root cause found is the public signup controller deleting the queued `provision_tenant` job immediately after tenant creation, which prevents the cron/provisioning path from ever dispatching `tenant_welcome` and `provisioning_completed`.

Live evidence:
- `tblkt_saas_email_logs` contains only 4 rows, and all are test emails.
- `tblmail_queue` is empty.
- `tblkt_saas_email_event_guards` contains reserved rows, but no live tenant welcome / provisioning success rows for the latest tenants.
- `tblkt_saas_provision_jobs` has a queued job for tenant 19, but it was not processed during the audit window.

Conclusion:
- Brevo transport works for test mail.
- Business lifecycle email delivery is not yet verified as working.
- Tenant creation email failure is explained by a concrete code path, not by Brevo connectivity.

## Email Architecture Baseline

| Component | Role | Current Behavior | Risk |
| --- | --- | --- | --- |
| `application/libraries/App_email.php` | Queue-backed email wrapper | Manages `mail_queue` and cron retry for queued mail | Queue can mask whether an event actually sent |
| `application/libraries/mails/App_mail_template.php` | Mail template transport abstraction | Builds and sends template-based mail through CI email runtime | Template send path may not expose message id unless wrapper captures it |
| `application/models/Emails_model.php` | Core mail sender | `send_simple_email()` can send via direct Brevo API; `send_email_template()` uses CI mailer path | Mixed transport behavior makes tracing harder |
| `application/config/email.php` | Email transport config | `email_protocol = brevo_api` is translated into Brevo runtime transport | Configuration drift can silently alter runtime |
| `application/helpers/email_templates_helper.php` | Template loader | Resolves template mail classes and merge fields | Broken merge fields can silently degrade content |
| `modules/kt_saas/services/TenantEmailProviderService.php` | Tenant/provider resolver | Selects provider, transport, sender, reply-to, guard, and logs results | A resolver can block sends before Brevo receives anything |
| `modules/kt_saas/services/EmailTriggerRegistryService.php` | Event registry | Maps event key to template slug, recipient scope, provider context, dedupe key | Registry presence does not prove runtime delivery |
| `tblemailtemplates` | Template storage | 222 templates total, 216 active, 6 inactive | Orphan/inactive templates can leave events unhandled |
| `tblmail_queue` | System queue | Empty at audit time | No queued mail to inspect or retry |
| `tblkt_saas_email_logs` | KT SaaS email audit log | Only test emails logged; no live business events | No business-event traceability |
| `tblkt_saas_email_event_guards` | Deduplication guard | Contains reserved rows for a few events | Guard can prevent resend if stale or mis-keyed |

## Brevo API Runtime Audit

| Test | Provider | Transport | Sender | Recipient | Brevo Response | Message ID | Local Log | Result |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Global email test | `brevo_api` | Brevo API / runtime transport | `no-reply@mail.khachtot.com` / landlord identity | `xemthach@gmail.com` | HTTP 2xx | `<202606040424.42551990397@smtp-relay.mailin.fr>` | Yes | Sent |
| Global email test (earlier run) | `brevo_api` | Brevo API / runtime transport | same | `xemthach@gmail.com` | Brevo IP authorization error | None | Yes | Failed |
| Tenant email test | `brevo_api` | Brevo API / runtime transport | tenant/global runtime identity | `codex.tenant.verify2@example.com` | HTTP 2xx | `<202606020908.49042374738@smtp-relay.mailin.fr>` | Yes | Sent |

Observed transport split:
- `send_simple_email()` can call Brevo directly through `https://api.brevo.com/v3/smtp/email`.
- Template events go through `TenantEmailProviderService` -> `mail_template(...)` -> CI mailer runtime configured for Brevo.

Implication:
- A Brevo test email proves transport.
- It does not prove tenant lifecycle, payment, or renewal events are reaching the mailer.

## Email Event Inventory

### Tenant / CRM package lifecycle

| Event | Module | Trigger File/Function | Template Slug | Mail Class | Recipient | Guard | Log | Actually Sends? | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `tenant_welcome` | KT SaaS | `mark_provision_job_done()` | `tenant_welcome` | exists | tenant admin | yes | no live log | Not proven live | Configured |
| `provisioning_completed` | KT SaaS | `mark_provision_job_done()` | `tenant_provisioning_completed` | exists | tenant admin | yes | no live log | Not proven live | Configured |
| `provisioning_failed` | KT SaaS | `mark_provision_job_failed()` | `tenant_provisioning_failed` | exists | tenant admin | yes | no live log | Not proven live | Configured |
| `tenant_trial_started` | KT SaaS | `save_tenant()` | `tenant_trial_started` | exists | tenant admin | yes | reserved row exists | Not proven live | Configured |
| `tenant_trial_ending` | KT SaaS | billing runner | `tenant_trial_ending` | exists | tenant admin | yes | no live log | Not proven live | Configured |
| `tenant_trial_expired` | KT SaaS | billing runner | `tenant_trial_expired` | exists | tenant admin | yes | no live log | Not proven live | Configured |
| `tenant_subscription_renewed` | KT SaaS | billing runner / plan lifecycle | `tenant_subscription_renewed` | exists | tenant admin | yes | reserved row exists for tenant 19 | Not proven live | Configured |
| `tenant_subscription_expired` | KT SaaS | billing runner | `tenant_subscription_expired` | exists | tenant admin | yes | no live log | Not proven live | Configured |
| `tenant_plan_changed` | KT SaaS | `save_tenant()` update branch | `tenant_plan_changed` | exists | tenant admin | yes | no live log | Not proven live | Configured |
| `tenant_quota_warning` | KT SaaS | usage snapshot / overage path | `tenant_quota_warning` | exists | tenant admin | yes | no live log | Not proven live | Configured |
| `tenant_quota_exceeded` | KT SaaS | usage snapshot / overage path | `tenant_quota_exceeded` | exists | tenant admin | yes | no live log | Not proven live | Configured |

### Signup / Landing / Checkout

| Event | Module | Trigger File/Function | Template Slug | Mail Class | Recipient | Guard | Log | Actually Sends? | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| signup submitted | KT Landing | public signup controller | n/a | n/a | n/a | no | no | no proof | Not implemented as email |
| signup invoice ready | KT SaaS | signup / invoice creation path | invoice template(s) | core/KT | tenant admin | possible | no live log | Not proven live | Configured |
| checkout started | KT SaaS | checkout controller | n/a | n/a | n/a | no | no | no proof | Not implemented as email |
| checkout paid | KT SaaS | payment success handler | `payment_success` | exists | tenant admin | yes | no live log | Not proven live | Configured |
| checkout failed | KT SaaS | payment failure handler | `payment_failed` | exists | tenant admin | yes | no live log | Not proven live | Configured |

### Billing / Renewal

| Event | Module | Trigger File/Function | Template Slug | Mail Class | Recipient | Guard | Log | Actually Sends? | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| invoice created | Perfex / KT SaaS | billing/invoice flow | core invoice template | exists | client / tenant admin | maybe | no live log | Not proven live | Configured |
| invoice sent | Perfex / KT SaaS | invoice send action | core invoice template | exists | recipient | maybe | no live log | Not proven live | Configured |
| invoice paid | Perfex / KT SaaS | payment success flow | core invoice template / KT event | exists | recipient | maybe | no live log | Not proven live | Configured |
| invoice overdue | Perfex / KT SaaS | cron / invoice reminders | core invoice template | exists | recipient | maybe | no live log | Not proven live | Configured |
| renewal due | KT SaaS | billing runner | renewal template | exists | tenant admin | yes | no live log | Not proven live | Configured |
| renewal success | KT SaaS | billing runner | `tenant_subscription_renewed` | exists | tenant admin | yes | reserved guard exists | Not proven live | Configured |
| renewal failed | KT SaaS | billing runner | renewal failure template | exists | tenant admin | yes | no live log | Not proven live | Configured |
| grace period warning | KT SaaS | billing runner | warning template | exists | tenant admin | yes | no live log | Not proven live | Configured |
| suspension warning | KT SaaS | billing runner | warning template | exists | tenant admin | yes | no live log | Not proven live | Configured |
| service suspended | KT SaaS | billing runner | suspension template | exists | tenant admin | yes | no live log | Not proven live | Configured |

### User / Client / Staff auth

| Event | Module | Trigger File/Function | Template Slug | Mail Class | Recipient | Guard | Log | Actually Sends? | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| client registration | Perfex | client auth flow | core auth template | exists | client | maybe | no live log | Not proven live | Configured |
| client verification | Perfex | client auth flow | core auth template | exists | client | maybe | no live log | Not proven live | Configured |
| forgot password client | Perfex | auth reset flow | core auth template | exists | client | maybe | no live log | Not proven live | Configured |
| reset password client | Perfex | auth reset flow | core auth template | exists | client | maybe | no live log | Not proven live | Configured |
| staff created | Perfex | staff admin flow | core auth template | exists | staff | maybe | no live log | Not proven live | Configured |
| staff forgot password | Perfex | staff auth flow | core auth template | exists | staff | maybe | no live log | Not proven live | Configured |
| staff password reset | Perfex | staff auth flow | core auth template | exists | staff | maybe | no live log | Not proven live | Configured |
| staff 2FA | Perfex | staff auth flow | core auth template | depends | staff | maybe | no live log | Not proven live | Configured |

### KT SePay / KT MatBao Invoice / KT Inventory / Ops

| Event | Module | Trigger File/Function | Template Slug | Mail Class | Recipient | Guard | Log | Actually Sends? | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| payment matched | KT SePay | reconciliation flow | `unmatched_payment_alert` related flow | exists | landlord admin / tenant admin | yes | no live log | Not proven live | Configured |
| unmatched payment alert | KT SePay | webhook/reconcile | `unmatched_payment_alert` | exists | landlord admin | yes | reserved row exists | Not proven live | Configured |
| webhook failed | KT SePay | webhook handler | `webhook_failed` | exists | landlord admin | yes | no live log | Not proven live | Configured |
| reconcile failed | KT SePay | reconcile job | alert template | exists | admin | yes | no live log | Not proven live | Configured |
| provider connection failed | KT SePay / KT SaaS | provider health check | `provider_connection_failed` | exists | admin | yes | reserved row exists | Not proven live | Configured |
| einvoice activated | KT MatBao Invoice | e-invoice activation | `einvoice_activated` | exists | tenant admin | yes | no live log | Not proven live | Configured |
| einvoice quota low | KT MatBao Invoice | quota monitor | `einvoice_quota_low` | exists | tenant admin | yes | no live log | Not proven live | Configured |
| einvoice quota exhausted | KT MatBao Invoice | quota monitor | `einvoice_quota_exhausted` | exists | tenant admin | yes | no live log | Not proven live | Configured |
| hsm activated | KT MatBao Invoice | HSM activation | `hsm_activated` | exists | tenant admin | yes | no live log | Not proven live | Configured |
| hsm expiry warning | KT MatBao Invoice | HSM monitor | `hsm_expiry_warning` | exists | tenant admin | yes | no live log | Not proven live | Configured |
| invoice issue failed | KT MatBao Invoice | invoice issue flow | `invoice_issue_failed` | exists | tenant admin | yes | no live log | Not proven live | Configured |
| invoice sign failed | KT MatBao Invoice | sign flow | `invoice_sign_failed` | exists | tenant admin | yes | no live log | Not proven live | Configured |
| low stock warning | KT Inventory | stock monitor | inventory warning | legacy/partial | staff | maybe | no live log | Not proven live | Legacy/partial |
| backup completed | KT SaaS | backup cron | `backup_completed` | exists | admin | yes | no live log | Not proven live | Configured |
| backup failed | KT SaaS | backup cron | `backup_failed` | exists | admin | yes | no live log | Not proven live | Configured |
| cron failed | KT SaaS | cron runner | `cron_failed` | exists | admin | yes | no live log | Not proven live | Configured |

## Template Inventory

Audit summary:
- `tblemailtemplates`: 222 templates total, 216 active, 6 inactive.
- Inactive templates found:
  - `inventory-warning-to-staff` (English, Vietnamese)
  - `tenant-expiration-reminder` (English, Vietnamese)
  - `we-found-your-tenant-url` (English, Vietnamese)

| Slug | Active | Mail Class | Trigger | Recipient | Merge Fields | Send Path | Risk |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `tenant_welcome` | yes | yes | yes (post-provision) | tenant admin | resolved in mail context | KT SaaS provider service | live delivery blocked by provision-job deletion |
| `tenant_provisioning_completed` | yes | yes | yes (post-provision) | tenant admin | resolved in mail context | KT SaaS provider service | live delivery blocked by provision-job deletion |
| `tenant_provisioning_failed` | yes | yes | yes | tenant admin | resolved in mail context | KT SaaS provider service | not live-verified |
| `payment_success` | yes | yes | yes | tenant admin | resolved in mail context | KT SaaS provider service | not live-verified |
| `payment_failed` | yes | yes | yes | tenant admin | resolved in mail context | KT SaaS provider service | not live-verified |
| `tenant_trial_started` | yes | yes | yes | tenant admin | resolved in mail context | KT SaaS provider service | reserved guard exists, not live-verified |
| `tenant_trial_ending` | yes | yes | yes | tenant admin | resolved in mail context | KT SaaS provider service | not live-verified |
| `tenant_trial_expired` | yes | yes | yes | tenant admin | resolved in mail context | KT SaaS provider service | not live-verified |
| `tenant_subscription_renewed` | yes | yes | yes | tenant admin | resolved in mail context | KT SaaS provider service | reserved guard exists, not live-verified |
| `tenant_subscription_expired` | yes | yes | yes | tenant admin | resolved in mail context | KT SaaS provider service | not live-verified |
| `tenant_plan_changed` | yes | yes | yes | tenant admin | resolved in mail context | KT SaaS provider service | not live-verified |
| `tenant_quota_warning` | yes | yes | yes | tenant admin | resolved in mail context | KT SaaS provider service | not live-verified |
| `tenant_quota_exceeded` | yes | yes | yes | tenant admin | resolved in mail context | KT SaaS provider service | not live-verified |
| `inventory-warning-to-staff` | no | legacy | unclear | staff | likely legacy | legacy Perfex path | orphan/inactive |
| `tenant-expiration-reminder` | no | legacy | unclear | tenant admin | likely legacy | legacy Perfex path | inactive / no verified trigger |
| `we-found-your-tenant-url` | no | legacy | unclear | tenant owner/admin | likely legacy | legacy Perfex path | inactive / no verified trigger |

## Tenant Creation Email Root Cause

### Step-by-step

| Step | Expected | Actual | Evidence | Pass/Fail |
| --- | --- | --- | --- | --- |
| 1. Public signup submits tenant data | Create tenant draft and queue provisioning | Tenant is created | `modules/kt_landing/controllers/Kt_landing_public.php:1272-1280` | Pass |
| 2. Provision job should remain queued | Cron should later process `provision_tenant` | Public signup deletes queued provision job | `modules/kt_landing/controllers/Kt_landing_public.php:1272-1276` | Fail |
| 3. Cron processes queued job | `mark_provision_job_done()` should run | No business mail observed | `tblkt_saas_provision_jobs` + no matching mail log | Fail |
| 4. `tenant_welcome` dispatch | Tenant owner/admin should receive welcome email | No live log row | `tblkt_saas_email_logs` | Fail |
| 5. `provisioning_completed` dispatch | Provision completion email should send | No live log row | `tblkt_saas_email_logs` | Fail |

Root cause:
- The public signup flow deletes the queued provisioning job immediately after tenant creation:
  - `modules/kt_landing/controllers/Kt_landing_public.php:1272-1279`
- The lifecycle emails are only dispatched when the provisioning job is later completed:
  - `modules/kt_saas/models/Kt_saas_model.php:2152-2155`
  - `modules/kt_saas/models/Kt_saas_model.php:3395-3405`

Result:
- tenant creation exists
- provisioning job is not allowed to complete
- welcome/provisioning-completed email never reaches the mailer

## Payment & Renewal Email Audit

Findings:
- The registry contains payment and renewal events (`payment_success`, `payment_failed`, `tenant_subscription_renewed`, `tenant_subscription_expired`, `tenant_trial_ending`, `tenant_trial_expired`).
- There are no live rows in `tblkt_saas_email_logs` proving those business emails were sent during this audit.
- Payment/renewal mail therefore remains **configured** but not **live-verified**.

| Flow | Trigger | Template | Recipient | Guard | Brevo Result | Local Log | Status |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Payment success | present in registry | present | tenant admin | yes | no live proof | none | configured |
| Payment failed | present in registry | present | tenant admin | yes | no live proof | none | configured |
| Subscription renewed | present in registry | present | tenant admin | yes | no live proof | none | configured |
| Subscription expired | present in registry | present | tenant admin | yes | no live proof | none | configured |
| Trial ending / expired | present in registry | present | tenant admin | yes | no live proof | none | configured |

## Auth Email Audit

The core Perfex auth paths exist, but this audit did not produce live business-event logs proving they used Brevo in a real end-user flow.

| Auth Event | Sender Path | Provider | Recipient | Template | Result |
| --- | --- | --- | --- | --- | --- |
| Staff forgot password | Perfex core auth | depends on runtime transport | staff | core mail template | not live-verified |
| Client forgot password | Perfex core auth | depends on runtime transport | client | core mail template | not live-verified |
| Registration / verification | Perfex core auth | depends on runtime transport | client/staff | core mail template | not live-verified |
| 2FA | Perfex core auth | depends on runtime transport | staff | core mail template | not live-verified |

## Queue Audit

`tblmail_queue` is empty.

| Queue Status | Count | Oldest | Newest | Common Error | Action |
| --- | ---: | --- | --- | --- | --- |
| Pending | 0 | n/a | n/a | n/a | none |
| Failed | 0 | n/a | n/a | n/a | none |
| Sent | 0 | n/a | n/a | n/a | none |

Implication:
- queue is not the reason the tenant welcome email failed
- the queued provisioning job is the relevant queue-like object in this flow, and it is deleted too early

## Duplicate Guard Audit

| Event | Dedupe Key | Status | Send Result | Risk |
| --- | --- | --- | --- | --- |
| `tenant_trial_started` | `83835dcb...` | reserved | not sent in live log | guard exists and can suppress resend |
| `tenant_subscription_renewed` | `9657871b...` | reserved | not sent in live log | guard exists and can suppress resend |
| `unmatched_payment_alert` | `b5f3c9da...` | reserved | not sent in live log | guard exists and can suppress resend |
| `provider_connection_failed` | `83c04d64...` | reserved | not sent in live log | guard exists and can suppress resend |

Guard risk:
- If a guard is created before a send fails, resend logic can be blocked unless failure rolls the guard back or marks it failed correctly.

## Logging Audit

| Log Table | Coverage | Missing Fields | Risk |
| --- | --- | --- | --- |
| `tblkt_saas_email_logs` | test emails only | no live business event rows | no traceability for actual lifecycle emails |
| `tblkt_saas_email_event_guards` | partial dedupe trace | no send/receive proof | can suppress resends without proving delivery |
| `tblmail_queue` | empty | no pending/failed rows | nothing to inspect for queue issues |
| core activity logs | present for provisioning / tenant actions | no email payload/message trace | useful but not enough for mail delivery proof |

## Live Smoke Test Matrix

Verified in audit:
- Brevo global test mail
- Brevo tenant test mail
- HTTP runtime config and message id capture for simple-email path

Not live-verified during this audit:
- tenant welcome on real signup
- provisioning completed on real signup
- payment success / payment failed
- invoice sent / overdue / renewal
- auth emails
- webhook / backup / MatBao / SePay operational alerts

| Smoke Test | Triggered? | Sent? | Brevo Message ID | Local Log | Recipient Received? | Status |
| --- | --- | --- | --- | --- | --- | --- |
| Global test email | yes | yes | yes | yes | yes | pass |
| Tenant test email | yes | yes | yes | yes | yes | pass |
| Tenant welcome on signup | trigger exists | no live proof | no live proof | no log row | unknown | fail / blocked |
| Provisioning completed | trigger exists | no live proof | no live proof | no log row | unknown | fail / blocked |
| Payment success | trigger exists | no live proof | no live proof | no log row | unknown | not yet verified |
| Forgot password staff | trigger exists | no live proof | no live proof | no log row | unknown | not yet verified |

## Deliverability Audit

Observed:
- Brevo accepted test mails and returned `message_id`.
- One earlier test failed because Brevo rejected the IP as unauthorized.
- Sender domain verification/SPF/DKIM/DMARC status was not re-audited in this pass.

| Domain/Auth | Status | Risk |
| --- | --- | --- |
| Brevo API connectivity | working for test mail | low |
| Brevo sender IP authorization | previously failed on one test | medium |
| SPF / DKIM / DMARC | not fully revalidated in this pass | medium |
| Suppression/bounce/spam folder | not live-verified | medium |

## Issues Found

| Priority | Issue | Root Cause | Fix | File/Table | Test |
| --- | --- | --- | --- | --- | --- |
| P0 | Tenant welcome / provisioning emails do not go out after signup | Public signup deletes queued `provision_tenant` job before cron can process it | Keep queued provision job; do not delete it in public signup; let cron finish and dispatch `tenant_welcome` / `provisioning_completed` | `modules/kt_landing/controllers/Kt_landing_public.php`, `tblkt_saas_provision_jobs` | Real signup -> cron -> email log |
| P1 | Business emails cannot be traced end-to-end from logs | Live business sends are not recorded in `tblkt_saas_email_logs` | Ensure all event sends write `provider`, `recipient`, `message_id`, `status`, `related_type`, `related_id` | `tblkt_saas_email_logs` | Trigger actual business event and inspect log |
| P1 | Mixed mail transports complicate tracing | `send_simple_email()` direct Brevo API vs template path via CI mailer/runtime | Standardize telemetry and log message_id for both paths | `application/models/Emails_model.php`, `TenantEmailProviderService.php` | Compare simple mail and template mail |
| P2 | Some templates are inactive/orphaned | Legacy templates not aligned with current triggers | Reconcile active template set with registry | `tblemailtemplates` | Template inventory audit |
| P2 | Guard can suppress resend if stale | Dedupe row reserved before a successful send | Ensure failure path rolls back or marks guard failed correctly | `tblkt_saas_email_event_guards` | Force failure, then retry event |

## Root Causes

1. Brevo transport is not the issue for test emails.
2. The main tenant email blocker is the public signup controller deleting the queued provisioning job.
3. The system lacks live business-email logs, so many flows are configured but not proven.
4. Duplicate guards and entitlement-based provider resolution can suppress or reroute mail, which is correct in principle but needs better traceability.

## Fix Plan

1. Stop deleting the queued `provision_tenant` job in public signup.
2. Add/keep explicit email logs for every business-event send with message id and recipient.
3. Run a real tenant signup after fixing the job handling and confirm:
   - `provisioning_completed`
   - `tenant_welcome`
4. Then run payment and renewal smoke tests.
5. Only after live business-event proofs exist should the system be considered operational.

## Recommended Implementation Order

1. Fix tenant provisioning job deletion in public signup.
2. Re-run tenant creation and confirm welcome/provisioning emails.
3. Re-run payment success / failure smoke.
4. Re-run auth email smoke.
5. Reconcile template inventory with registry.
6. Tighten logging for message id / recipient / provider / send result.

## Go / No-Go For Production Email

No-go for production email messaging as a system-wide claim.

Reason:
- Only simple Brevo test mails are proven.
- Real business emails are not yet end-to-end verified.
- Tenant lifecycle email currently has a confirmed logic blocker.

Go only after:
- a real tenant signup produces actual welcome/provisioning mail,
- payment mail is confirmed with logs and message ids,
- and the failure/guard paths are verified to be traceable.

