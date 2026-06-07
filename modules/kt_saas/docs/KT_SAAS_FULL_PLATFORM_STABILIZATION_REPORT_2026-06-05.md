# KT SAAS FULL PLATFORM STABILIZATION REPORT

## Executive Summary

KT SaaS is not production ready yet.

The platform has working public signup, checkout, SePay QR/status, tenant portal reads, and several tenant module screens. However, UAT shows the product is still in stabilization mode because several operational paths are either unverified or failing at runtime:

- Tenant email test POST returns `500`.
- Tenant email logs contain failed business sends caused by Brevo / sender authorization.
- Provisioning has 1 queued job and 1 historical failed job.
- Domains are mostly pending: 16 of 20 domain rows are still pending.
- MatBao tenant settings are denied for the tested tenant, while read-only MatBao tenant pages load.
- Several save/reload/runtime effects are not yet proven by live UAT, especially governance, backups, renewal, quota, overage, suspension, reactivation, and MatBao issue/sign flows.

Live smoke was performed on:

- landlord public host: `khachtot.test`
- tenant host: `somogyimarty322493.khachtot.test`
- tenant id: `21`
- tenant staff session: staff id `3`

Database snapshot:

| Metric | Count |
| ------ | ----: |
| Tenants | 20 |
| Subscriptions | 20 |
| Invoices | 19 |
| Payments | 22 |
| Provision jobs | 12 |
| Email logs | 30 |
| Backups | 1 |
| Domains | 20 |
| SePay payment requests | 18 |
| SePay transactions | 8 |
| MatBao invoice records | 2 |
| MatBao logs | 151 |

## Feature Inventory

| Module | Feature | URL | Type |
| ------ | ------- | --- | ---- |
| KT SaaS | Dashboard | `/admin/kt_saas` | Landlord admin |
| KT SaaS | Tenants | `/admin/kt_saas/tenants` | Landlord admin |
| KT SaaS | Tenant access | `/admin/kt_saas/tenant_access/{id}` | Landlord admin |
| KT SaaS | Tenant status | `/admin/kt_saas/tenant_status/{id}/{status}` | Landlord action |
| KT SaaS | Plans | `/admin/kt_saas/plans` | Landlord admin |
| KT SaaS | Subscriptions | `/admin/kt_saas/subscriptions` | Landlord admin |
| KT SaaS | Invoices | `/admin/kt_saas/invoices` | Landlord admin |
| KT SaaS | Payments | `/admin/kt_saas/payments` | Landlord admin |
| KT SaaS | Domains | `/admin/kt_saas/domains` | Landlord admin |
| KT SaaS | Modules / apps | `/admin/kt_saas/modules` | Landlord admin |
| KT SaaS | Backups | `/admin/kt_saas/backups` | Landlord admin |
| KT SaaS | Provision jobs | `/admin/kt_saas/provision_jobs` | Landlord admin |
| KT SaaS | Activity logs | `/admin/kt_saas/activity_logs` | Landlord admin |
| KT SaaS | Settings | `/admin/kt_saas/settings` | Landlord admin |
| KT SaaS | Tenant subscription | `/admin/kt_saas/tenant_subscription` | Tenant portal |
| KT SaaS | Tenant billing | `/admin/kt_saas/tenant_billing` | Tenant portal |
| KT SaaS | Tenant usage | `/admin/kt_saas/tenant_usage` | Tenant portal |
| KT SaaS | Tenant settings | `/admin/kt_saas/tenant_settings` | Tenant portal |
| KT SaaS | Tenant email save/reset/test | `/admin/kt_saas/tenant_email_settings_*` | Tenant portal POST |
| KT SaaS | Tenant activity logs | `/admin/kt_saas/tenant_activity_logs` | Tenant portal |
| KT SaaS | Tenant governance | `/admin/kt_saas/tenant_governance` | Tenant portal |
| KT SaaS | Tenant roles | `/admin/kt_saas/tenant_role/{id?}` | Tenant portal |
| KT SaaS | Tenant departments | `/admin/kt_saas/tenant_departments` | Tenant portal |
| Landing | Public landing | `/` | Public |
| Landing | Pricing | `/pricing` | Public |
| Landing | Signup | `/signup` | Public |
| Landing | Blog | `/blog` | Public |
| Checkout | Invoice checkout | `/kt_saas/checkout/invoice/{id}/{token}` | Public payment |
| Checkout | Manual webhook | `/kt_saas/checkout/webhook/{gateway}` | Public webhook |
| KT SePay | Public QR payment | `/kt_sepay/pay/{id}/{token}` | Public payment |
| KT SePay | Public status | `/kt_sepay/status/{id}/{token}` | Public JSON |
| KT SePay | Tenant portal | `/admin/kt_sepay/tenant_portal` | Tenant portal |
| KT SePay | Tenant settings | `/admin/kt_sepay/tenant_settings` | Tenant portal |
| KT SePay | Tenant transactions | `/admin/kt_sepay/tenant_transactions` | Tenant portal |
| KT SePay | Tenant requests | `/admin/kt_sepay/tenant_payment_requests` | Tenant portal |
| KT SePay | Tenant reconciliation | `/admin/kt_sepay/tenant_reconciliation` | Tenant portal |
| KT MatBao Invoice | Tenant overview | `/admin/kt_matbao_invoice/tenant` | Tenant portal |
| KT MatBao Invoice | Tenant settings | `/admin/kt_matbao_invoice/tenant/settings` | Tenant portal |
| KT MatBao Invoice | Tenant invoices | `/admin/kt_matbao_invoice/tenant/invoices` | Tenant portal |
| KT MatBao Invoice | Tenant usage | `/admin/kt_matbao_invoice/tenant/usage` | Tenant portal |
| KT MatBao Invoice | Tenant add-ons | `/admin/kt_matbao_invoice/tenant/addons` | Tenant portal |
| KT MatBao Invoice | Tenant logs | `/admin/kt_matbao_invoice/tenant/logs` | Tenant portal |

## UAT Matrix

| Feature | Open | Save | Runtime | Result |
| ------- | ---- | ---- | ------- | ------ |
| Public landing | PASS 200 | n/a | Public render | PASS |
| Public pricing | PASS 200 | n/a | Public render | PASS |
| Signup | PASS 200 | not re-run fully in this pass | plan/subdomain path previously hardened | PARTIAL |
| Signup status | 307 to `/signup` without session | n/a | expected if no signup state | PASS |
| Checkout invoice | PASS 200 | n/a | invoice checkout renders | PASS |
| SePay QR payment | PASS 200 | n/a | payment page renders | PASS |
| SePay status | PASS 200 | n/a | JSON status reachable | PASS |
| Tenant subscription | PASS 200 | renewal not run | billing integration present | PARTIAL |
| Tenant billing | PASS 200 | payment action not run | invoice/payment links present | PARTIAL |
| Tenant usage | PASS 200 | n/a | usage snapshot displayed | PASS |
| Tenant settings | PASS 200 | workspace save previously reached controller | runtime effect not fully rechecked | PARTIAL |
| Tenant email save | route whitelisted | save previously redirected | source-of-truth validated | PASS |
| Tenant email test | form reachable | POST returns 500 | email runtime fails | FAIL |
| Tenant activity logs | PASS 200 | n/a | logs render | PASS |
| Tenant governance | PASS 200 | role/dept save not UATed | capability guarded | PARTIAL |
| Tenant departments | PASS 200 | save/delete not UATed | capability guarded | PARTIAL |
| Landlord KT SaaS admin | 307 to login without landlord session | not tested | auth guard correct | AUTH REQUIRED |
| Landlord KT Landing admin | 307 to login without landlord session | not tested | auth guard correct | AUTH REQUIRED |
| Tenant SePay portal | PASS 200 | not submitted | tenant SePay views load | PARTIAL |
| Tenant SePay settings | PASS 200 | not submitted | config page loads | PARTIAL |
| Tenant SePay transactions | PASS 200 | n/a | list loads | PASS |
| Tenant SePay requests | PASS 200 | create not run | list loads | PARTIAL |
| Tenant SePay reconciliation | PASS 200 | run not triggered | page loads | PARTIAL |
| Tenant MatBao overview | PASS 200 | n/a | page loads | PASS |
| Tenant MatBao settings | 307 to access_denied | save/test not possible | entitlement/config denied | BLOCKED |
| Tenant MatBao invoices | PASS 200 | issue/sign not run | page loads | PARTIAL |
| Tenant MatBao usage | PASS 200 | n/a | page loads | PASS |
| Tenant MatBao add-ons | PASS 200 | buy flow not run | page loads | PARTIAL |
| Tenant MatBao logs | PASS 200 | n/a | page loads | PASS |

## Landlord Audit

Landlord KT SaaS routes are registered and protected by admin authentication/capabilities:

| Area | Route | Current Evidence | Result |
| ---- | ----- | ---------------- | ------ |
| Dashboard | `/admin/kt_saas` | unauthenticated request redirects to `/admin/authentication` | PASS guard |
| Tenants | `/admin/kt_saas/tenants` | controller/view present | STATIC PASS |
| Plans | `/admin/kt_saas/plans` | controller/view present | STATIC PASS |
| Subscriptions | `/admin/kt_saas/subscriptions` | controller/view present | STATIC PASS |
| Invoices | `/admin/kt_saas/invoices` | controller/view present | STATIC PASS |
| Payments | `/admin/kt_saas/payments` | controller/view present | STATIC PASS |
| Domains | `/admin/kt_saas/domains` | controller/view present | STATIC PASS |
| Modules/apps | `/admin/kt_saas/modules` | controller/view present | STATIC PASS |
| Backups | `/admin/kt_saas/backups` | controller/view present | STATIC PASS |
| Provision jobs | `/admin/kt_saas/provision_jobs` | controller/view present | STATIC PASS |
| Settings/email test | `/admin/kt_saas/settings`, `/settings_email_test` | controller/view present | STATIC PASS |

Landlord full create/edit/delete UAT was not completed in this pass because no landlord-authenticated browser session was used from CLI. This remains required before production sign-off.

## Tenant Audit

| Area | URL | Live Result | Notes |
| ---- | --- | ----------- | ----- |
| Subscription | `/admin/kt_saas/tenant_subscription` | 200 | renewal/plan-change not executed |
| Billing | `/admin/kt_saas/tenant_billing` | 200 | invoice/payment action not executed |
| Usage | `/admin/kt_saas/tenant_usage` | 200 | usage page loads |
| Settings | `/admin/kt_saas/tenant_settings` | 200 | workspace/email settings page loads |
| Email test | `/admin/kt_saas/tenant_email_settings_test` | 500 | P0 |
| Activity Logs | `/admin/kt_saas/tenant_activity_logs` | 200 | page loads |
| Governance | `/admin/kt_saas/tenant_governance` | 200 | save/delete not UATed |
| Departments | `/admin/kt_saas/tenant_departments` | 200 | save/delete not UATed |
| Landlord tenants page | `/admin/kt_saas/tenants` | 403 | expected tenant isolation |
| Landlord invoices page | `/admin/kt_saas/invoices` | 403 | expected tenant isolation |
| Landlord domains page | `/admin/kt_saas/domains` | 403 | expected tenant isolation |

## Auth Audit

| Flow | URL | Live Result | Status |
| ---- | --- | ----------- | ------ |
| Staff login | `/admin/authentication` | 200 without tenant session | PASS |
| Staff forgot password | `/admin/authentication/forgot_password` | 200 without tenant session | PASS load |
| Staff reset password | `/admin/authentication/reset_password/...` | 307 to login for invalid/expired token | PASS load |
| Staff logout | not exercised | n/a | NOT TESTED |
| Client login | `/clients/login` | 200 | PASS |
| Client forgot password | `/clients/forgot_password` | 200 | PASS load |
| Client reset password | not exercised | n/a | NOT TESTED |
| Verification | not exercised | n/a | NOT TESTED |
| 2FA | not exercised | n/a | NOT TESTED |

Auth route 500 is not currently reproducible on GET. Email dispatch for auth flows still needs a live delivery test.

## Email Audit

Email is not production ready yet.

Database evidence:

| Status | Count |
| ------ | ----: |
| sent | 18 |
| failed | 12 |

Recent failures include:

| Tenant | Recipient | Subject/Event | Error |
| ------ | --------- | ------------- | ----- |
| 21 | `somogyimarty322493@gmail.com` | `tenant_welcome` | Email send failed / Brevo authorization warning |
| 21 | `somogyimarty322493@gmail.com` | `provisioning_completed` | Email send failed / Brevo authorization warning |
| 21 | `somogyimarty322493@gmail.com` | `payment_success` | Email send failed / Brevo authorization warning |
| 16 | `xemthach+470442@gmail.com` | `payment_success` | Email send failed |

Important log gap:

`tblkt_saas_email_logs` does not store a dedicated `event_key` column. Some event values are currently stored in `subject`, which makes traceability weaker than it should be.

## Billing Audit

| Flow | Evidence | Result |
| ---- | -------- | ------ |
| Signup plan pricing | `/signup`, `/signup?plan_id=4` return 200 | PASS display |
| Signup invoice amount | previously fixed and verified for setup fee inclusion | PASS previous evidence |
| Checkout invoice | `/kt_saas/checkout/invoice/...` returns 200 | PASS |
| SePay payment page | `/kt_sepay/pay/...` returns 200 | PASS |
| SePay status | `/kt_sepay/status/...` returns 200 | PASS |
| Renewal | `RecurringBillingRunner` exists, not executed in this pass | NOT VERIFIED |
| Plan change | controller path exists, not executed | NOT VERIFIED |
| Quota/overage | services exist, not executed | NOT VERIFIED |
| Suspension/reactivation | status actions exist, not executed | NOT VERIFIED |

Invoice status snapshot:

| Status | Count |
| ------ | ----: |
| draft | 1 |
| paid | 13 |
| pending_payment | 5 |

## Provisioning Audit

Provisioning is partially operational but not production-clean.

| Status | Count |
| ------ | ----: |
| done | 10 |
| failed | 1 |
| queued | 1 |

Recent provisioning evidence:

| Job | Tenant | Status | Attempts | Notes |
| --- | ------ | ------ | -------- | ----- |
| 26 | 21 | done | 1 | tenant provisioned |
| 22 | 19 | queued | 0 | still pending |
| older | n/a | failed | n/a | historical failed job exists |

Welcome/provisioning emails for tenant 21 failed after the job completed, so provisioning and lifecycle email readiness are not aligned yet.

## SePay Audit

Tenant SePay pages load:

| Area | URL | Result |
| ---- | --- | ------ |
| Portal | `/admin/kt_sepay/tenant_portal` | 200 |
| Settings | `/admin/kt_sepay/tenant_settings` | 200 |
| Transactions | `/admin/kt_sepay/tenant_transactions` | 200 |
| Payment requests | `/admin/kt_sepay/tenant_payment_requests` | 200 |
| Reconciliation | `/admin/kt_sepay/tenant_reconciliation` | 200 |

Operational snapshot:

| Item | Count |
| ---- | ----: |
| Payment requests | 18 |
| Transactions | 8 |
| Pending payment requests | 16 |
| Cancelled payment requests | 2 |
| Unmatched transactions | 8 |

P1 risk: all current SePay transactions are `unmatched`, so reconciliation/matching requires deeper UAT before go-live.

## MatBao Audit

Tenant MatBao pages:

| Area | URL | Result |
| ---- | --- | ------ |
| Overview | `/admin/kt_matbao_invoice/tenant` | 200 |
| Settings | `/admin/kt_matbao_invoice/tenant/settings` | 307 to access denied |
| Invoices | `/admin/kt_matbao_invoice/tenant/invoices` | 200 |
| Usage | `/admin/kt_matbao_invoice/tenant/usage` | 200 |
| Add-ons | `/admin/kt_matbao_invoice/tenant/addons` | 200 |
| Logs | `/admin/kt_matbao_invoice/tenant/logs` | 200 |

Operational snapshot:

| Item | Count |
| ---- | ----: |
| MatBao invoice records | 2 |
| MatBao logs | 151 |

Issue/sign/quota/HSM provider health were not live-executed in this pass.

## Backup Audit

| Flow | Evidence | Result |
| ---- | -------- | ------ |
| Backup list | controller/view/service present | STATIC PASS |
| Existing backups | DB count = 1 | DATA EXISTS |
| Create backup | not executed | NOT VERIFIED |
| Download backup | not executed | NOT VERIFIED |
| Restore backup | not executed | NOT VERIFIED |
| Checksum | service code exists, not verified | NOT VERIFIED |

Backup is not production-certified until create/download/restore/checksum are run against a disposable tenant.

## Security Audit

| Check | Evidence | Result |
| ----- | -------- | ------ |
| Tenant isolation guard | tenant host gets 403 on `/admin/kt_saas/tenants`, `/invoices`, `/domains` | PASS |
| Landlord auth guard | landlord admin routes redirect to `/admin/authentication` without login | PASS |
| Tenant portal whitelist | `tenant_email_settings_save/reset/test` now whitelisted | PASS |
| Tenant-only pages | tenant subscription/billing/usage/settings/logs load 200 | PASS |
| Module entitlements | MatBao settings access denied for tested tenant | PASS or P1 depending plan |
| Email runtime | tenant email test returns 500 | FAIL |
| CSRF | tenant email test reaches runtime only with valid token | PASS |

## P0 Issues

| Issue | Root Cause | File / Method | Priority | Regression Risk |
| ----- | ---------- | ------------- | -------- | --------------- |
| Tenant email test returns 500 | Request passes CSRF/entitlement, then fails inside email runtime send/test path | `modules/kt_saas/controllers/Kt_saas.php::tenant_email_settings_test()` and `modules/kt_saas/services/TenantEmailProviderService.php` | P0 | Medium, touches tenant mail runtime |
| Business lifecycle emails failing for tenant 21 | Brevo rejects sender/IP/account state for some sends; email log shows provider authorization warning | `TenantEmailProviderService`, `Emails_model`, Brevo account config | P0 | High, affects provisioning/payment lifecycle |
| Provisioning not fully drained | 1 queued and 1 failed job remain | `modules/kt_saas/provisioning/ProvisioningJobRunner.php`, `Kt_saas_model` provision job methods | P0 | Medium, affects tenant creation |

## P1 Issues

| Issue | Root Cause | File / Method | Priority | Regression Risk |
| ----- | ---------- | ------------- | -------- | --------------- |
| Domain readiness mostly pending | DNS/SSL verification not completed for most tenant subdomains | `DomainVerificationService`, `tblkt_saas_domains` | P1 | Medium |
| SePay transactions all unmatched | Reconciliation/match flow not verified or configured for current data | `Kt_sepay_processor`, `Kt_sepay::tenant_reconciliation()` | P1 | Medium |
| MatBao tenant settings denied | Tenant lacks configure entitlement or module gate blocks configure page | `Kt_matbao_invoice_tenant::settings()` | P1 | Low if entitlement is intentional |
| Email logs lack dedicated event key | Event trace is stored indirectly via subject/related fields | `tblkt_saas_email_logs` schema / logging service | P1 | Medium |
| Renewal/overage/suspension not live-verified | Runner exists, but UAT not executed | `RecurringBillingRunner`, `OverageBillingService` | P1 | High |
| Backup restore not verified | Existing backup data but no restore/checksum UAT | `TenantBackupService` | P1 | High |

## P2 Issues

| Issue | Root Cause | File / Method | Priority | Regression Risk |
| ----- | ---------- | ------------- | -------- | --------------- |
| Tenant settings mixed labels | Page combines workspace/email controls and some labels are not localized | `modules/kt_saas/views/tenant/settings.php` | P2 | Low |
| Some KT SaaS language strings still mojibake in alerts | Hard-coded legacy strings remain in controller paths | `modules/kt_saas/controllers/Kt_saas.php` | P2 | Low |
| MatBao tenant labels include mixed English/Vietnamese | Legacy copy in views/language | `modules/kt_matbao_invoice/views/tenant/*` | P2 | Low |

## Stabilization Roadmap

1. Fix tenant email test 500.
   - Reproduce with valid CSRF.
   - Capture PHP/FPM stack trace.
   - Fix `tenant_email_settings_test()` / runtime mail transport.
   - Verify log, message id, and inbox.

2. Stabilize business email delivery.
   - Fix Brevo sender authorization issue.
   - Re-run `tenant_welcome`, `provisioning_completed`, `payment_success`, renewal, quota and failed webhook events.
   - Require `sent`, `message_id`, and inbox evidence.

3. Drain and harden provisioning.
   - Resolve queued/failed jobs.
   - Verify tenant DB, tenant login, domain, welcome mail, and activity log after provision.

4. Verify billing lifecycle.
   - Renewal due, renewal success/fail, overdue, grace, suspension, reactivation, plan change.
   - Confirm no duplicate setup fee on renewal.

5. Verify domains.
   - Run domain verification on pending records.
   - Confirm DNS/SSL/readiness transitions.

6. Verify SePay operations.
   - Manual match, unmatched alert, reconcile, webhook replay, idempotency.

7. Verify MatBao operations.
   - Entitlement state, tenant settings if allowed, issue, sign, quota, HSM expiry, provider health.

8. Verify backups.
   - Create, download, checksum, restore on disposable tenant.

9. Finish P2 UX/language cleanup.
   - Tenant settings labels.
   - MatBao/KT SaaS mixed English/Vietnamese.
   - Remaining hard-coded mojibake alerts.

## Production Readiness Score

Current score: **63/100**

Rationale:

- Public landing/signup/checkout/SePay public payment surface is mostly stable.
- Tenant portal read routes are mostly stable.
- Landlord admin surfaces are registered and guarded, but full landlord UAT was not executed in this pass.
- Email and provisioning still have live failures.
- Billing lifecycle beyond initial invoice/payment has not been proven.
- Domain readiness and SePay reconciliation are not production-clean.
- Backup restore and MatBao issue/sign are not verified.

## Can KT SaaS Be Declared Production Ready?

No.

KT SaaS can move toward production readiness only after:

- tenant email test no longer returns 500
- lifecycle emails send with Brevo message ids and inbox evidence
- provision queue is drained and failed jobs are resolved
- renewal/overage/suspension/reactivation are live-verified
- SePay reconciliation/matching is verified
- MatBao issue/sign/quota/HSM flows are verified
- backup restore is verified
- landlord and tenant UAT matrices pass with save/reload/runtime effects

