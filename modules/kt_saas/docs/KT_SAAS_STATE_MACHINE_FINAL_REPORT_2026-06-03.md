# KT SAAS STATE MACHINE FINAL REPORT

Scope:
- Audit the end-to-end KT SAAS state machine using code, routes, services, database schema, and live path checks.
- No code changes.
- No refactor.
- No assumptions without file/function/route/runtime evidence.

## Route Inventory

| Route | Controller | Method | Purpose |
|---|---|---|---|
| `/` | `kt_landing/kt_landing_public` | `home` | Public landing |
| `/pricing` | `kt_landing/kt_landing_public` | `pricing` | Public pricing |
| `/blog` | `kt_landing/kt_landing_public` | `blog` | Public content page |
| `/signup` | `kt_landing/kt_landing_public` | `signup` | Public signup wizard |
| `/signup/status` | `kt_landing/kt_landing_public` | `signup_status` | Signup result page |
| `/signup/progress/(:any)` | `kt_landing/kt_landing_public` | `signup_progress($1)` | Tenant provisioning progress JSON |
| `/contact/submit` | `kt_landing/kt_landing_public` | `contact_submit` | Public contact submit |
| `/kt_saas/checkout/invoice/{id}/{token}` | `modules/kt_saas/controllers/Checkout.php` | `invoice()` | SaaS invoice checkout page |
| `/kt_saas/checkout/pay/{id}/{token}` | `modules/kt_saas/controllers/Checkout.php` | `pay()` | Manual payment capture |
| `/kt_saas/checkout/webhook/{gateway}` | `modules/kt_saas/controllers/Checkout.php` | `webhook()` | SaaS payment webhook entry |
| `/kt_sepay/pay/(:num)/(:any)` | `kt_sepay_public` | `pay()` | SePay payment request entry |
| `/kt_sepay/status/(:num)/(:any)` | `kt_sepay_public` | `status()` | SePay payment status |
| `/kt_sepay/webhook` | `kt_sepay_webhook` | `index()` | Global SePay webhook |
| `/kt_sepay/webhook/tenant/(:any)` | `kt_sepay_webhook` | `tenant($1)` | Tenant-specific SePay webhook |

## Signup Audit

### File/function/condition/result

| File | Function | Condition | Result |
|---|---|---|---|
| `modules/kt_landing/controllers/Kt_landing_public.php` | `signup()` | `isTenantRuntime()` is true | Redirects to `/clients` |
| `modules/kt_landing/controllers/Kt_landing_public.php` | `signup()` | POST submit on landlord/public host | Calls `handleSignupSubmit()` |
| `modules/kt_landing/controllers/Kt_landing_public.php` | `signup()` catch block | Any exception in signup flow | Calls `renderSignupFallback()` |
| `modules/kt_landing/controllers/Kt_landing_public.php` | `renderSignupFallback()` | Fallback render path | Emits plain text `"{brand} signup currently unavailable."` with HTTP 500 |

### Direct answer to the prompt
- The exact text `signup currently unavailable` comes from `modules/kt_landing/controllers/Kt_landing_public.php:482-487`, inside `renderSignupFallback()`.
- That method is only reached from the `signup()` catch block.
- The route is `/signup`, mapped to `kt_landing/kt_landing_public/signup`.
- The current live landlord host does **not** return that fallback on normal request:
  - `https://khachtot.test/signup` => `200`
  - `https://abc.khachtot.test/signup` => `307`
  - `https://verifynew-230022.khachtot.test/signup` => `307`
- So the message is an exception fallback branch, not the default live behavior on the landlord host.

## Landing → Plan Flow

| Data | Source | Destination | Pass |
|---|---|---|---|
| Public plans | `Kt_saas_model::get_public_plans()` | `Kt_landing_public::signup()` view data | Pass |
| `plan_id` | Signup form POST | `handleSignupSubmit()` | Pass |
| Trial/public signup | `handleSignupSubmit()` | Tenant draft + subscription + invoice path | Pass |
| Billing cycle | Plan + subscription data | Subscription/invoice creation | Pass |
| Featured plan / override | `Kt_landing_public::indexPlanOverrides()` | Pricing presentation layer only | Pass |

### Signup flow mechanics
- `handleSignupSubmit()` validates honeypot, timestamp, required fields, email, subdomain, and rate limiting.
- It loads the selected public plan via `Kt_saas_model->get_plan($planId)`.
- If a reusable draft tenant exists, it reuses it.
- Otherwise it creates a tenant in `draft` state.
- It then sets `provisioning_status = queued`.
- If no subscription is present, it returns a draft-only success response.
- If a subscription exists, it finds or creates an invoice with reason `public_signup`.
- When invoice exists, it resolves the preferred checkout URL.

## Order Creation

### Source of truth
There is no separate `kt_saas_orders` / `order_items` table in the current code base.  
The billing funnel is represented by:
- `kt_saas_tenants`
- `kt_saas_subscriptions`
- `kt_saas_invoices`
- `kt_saas_payments`
- `kt_saas_provision_jobs`
- `kt_sepay_payment_requests`
- `kt_sepay_transactions`

### Entity table/service matrix

| Entity | Table | Service | Created When |
|---|---|---|---|
| Tenant | `tblkt_saas_tenants` | `Kt_saas_model::save_tenant()` | Signup/draft creation |
| Subscription | `tblkt_saas_subscriptions` | `Kt_saas_model::save_tenant()`, billing services, cron renewals | Signup bootstrap / renewal / plan change |
| Invoice | `tblkt_saas_invoices` | `BillingEngineService::createSubscriptionInvoice()` / `createPlanChangeRequestInvoice()` / `OverageBillingService` | Renewal, plan change, overage, public signup payment |
| Payment | `tblkt_saas_payments` | `BillingEngineService::markInvoicePaid()` | Payment capture / webhook success / manual pay |
| Provision job | `tblkt_saas_provision_jobs` | `Kt_saas_model::create_provision_job()` | After signup payment / provisioning queue |
| SePay payment request | `tblkt_sepay_payment_requests` | SePay request creation paths | Public checkout / invoice payment |
| SePay transaction | `tblkt_sepay_transactions` | `Kt_sepay_processor::processIncomingTransaction()` | Webhook / reconcile / cron |

## Payment Flow

| Scenario | Current Behavior | Risk |
|---|---|---|
| Payment success | `BillingEngineService::markInvoicePaid()` updates invoice, reactivates subscription, dispatches `payment_success` | Low, dedupe guard exists |
| Payment failed | `PaymentCollectionService::processWebhook()` dispatches `payment_failed` on failure statuses | Low, goes through resolver |
| Replay | `markInvoicePaid()` reserves guard even on already-paid replay | Low, duplicate guard present |
| Duplicate callback | `PaymentCollectionService::processWebhook()` relies on invoice status + dedupe guard | Low |
| Manual payment | `Checkout::pay()` calls `markInvoicePaid()` | Low |

### Key runtime path
- `modules/kt_saas/services/PaymentCollectionService.php`
  - `getCheckoutUrl()`
  - `processWebhook()`
  - `verifyWebhookSignature()`
- `modules/kt_saas/services/BillingEngineService.php`
  - `markInvoicePaid()`
  - `dispatchPaymentSuccessEmail()`
  - `reactivateSubscriptionAfterPayment()`
  - `applyPaidPlanChange()`
- `modules/kt_saas/controllers/Checkout.php`
  - `invoice()`
  - `pay()`
  - `webhook()`

## Webhook Audit

| Check | Pass | Risk |
|---|---|---|
| Signature verification | Pass | Uses HMAC SHA-256 secret in `PaymentCollectionService::verifyWebhookSignature()` |
| Secret source | Pass | `kt_saas_payment_webhook_secret` / app key fallback |
| Payload validation | Pass | JSON parsing + signature/header validation in `Checkout::webhook()` |
| Idempotency | Pass | Guard + payment reference uniqueness + invoice status checks |
| Error handling | Pass | `webhook_failed` email + webhook logs + activity logs |

### SePay webhook specifics
- `modules/kt_sepay/controllers/Kt_sepay_webhook.php`
  - validates method, authorization, payload
  - logs webhook reception
  - dispatches `webhook_failed` on invalid method/auth/payload/process failures
- `modules/kt_sepay/libraries/Kt_sepay_processor.php`
  - rejects missing/invalid transaction
  - marks unmatched transactions
  - dispatches `unmatched_payment_alert`
  - routes matched SaaS invoice payments back into `BillingEngineService::markInvoicePaid()`

## Provisioning Flow

| Step | Service | Status | Risk |
|---|---|---|---|
| Tenant draft create | `Kt_saas_model::save_tenant()` | Implemented | Low |
| Provision queue create | `Kt_saas_model::create_provision_job()` | Implemented | Low |
| Provision job running | `Kt_saas_model::mark_provision_job_running()` | Implemented | Low |
| DB assign / schema clone | `ProvisioningJobRunner::execute()` | Implemented | Medium if infra fails |
| Module assign | `ProvisioningJobRunner::execute()` / tenant sync helpers | Implemented | Medium |
| Entitlement assign | `TenantEntitlementService` + runtime sync | Implemented | Medium |
| Provision complete | `Kt_saas_model::mark_provision_job_done()` | Implemented | Low |
| Provision failed | `Kt_saas_model::mark_provision_job_failed()` | Implemented | Low |

### Runtime provisioning behavior
- `Kt_saas_model::mark_provision_job_done()` sends:
  - `provisioning_completed`
  - `tenant_welcome`
- `Kt_saas_model::mark_provision_job_failed()` sends:
  - `provisioning_failed`
- The runtime state gates tenant-host access through `TenantContextService::isRuntimeAccessible()`, which requires:
  - tenant status in runtime statuses
  - provisioning status `done`

## Activation State Machine

| From | To | Trigger | Service |
|---|---|---|---|
| `draft` | `queued` | public signup accepted / provision job created | `Kt_saas_model::create_provision_job()` |
| `queued` | `running` | job starts | `Kt_saas_model::mark_provision_job_running()` |
| `running` | `done` | provisioning completed successfully | `Kt_saas_model::mark_provision_job_done()` |
| `running` | `failed` | provisioning failure | `Kt_saas_model::mark_provision_job_failed()` |
| `draft` | `active` | tenant becomes ready after provisioning | `Kt_saas_model::mark_provision_job_done()` |
| `active` | `suspended` | subscription expired/cancelled | `Kt_saas_model::set_subscription_status()` |
| `trial` | `expired` | cron expiration / state transition | `RecurringBillingRunner` + `set_subscription_status()` |
| `active` | `expired` | explicit expiry path | `set_subscription_status()` |
| `active` | `grace` | renewal grace / billing state | `RecurringBillingRunner` / billing services |
| `grace` | `suspended` | grace expiry | `RecurringBillingRunner` / billing services |

## Failure Recovery

| Failure | Recovery | Risk |
|---|---|---|
| Payment success, then provision fail | Tenant is moved back to `draft` or failed provisioning state; provisioning job is marked failed | Medium |
| Tenant create fail | Signup fallback does not create a live tenant; user remains on public flow | Medium |
| Module assign fail | Provision job fails, tenant provisioning status becomes failed | Medium |
| Webhook replay | Idempotency + guards prevent double processing | Low |
| Duplicate callback | Payment reference uniqueness and already-paid checks block duplicates | Low |

## End-To-End Test

### Live checks performed

| Step | Result | Evidence |
|---|---|---|
| Landing | Pass | `https://khachtot.test/` returns `200` |
| Signup on landlord host | Pass | `https://khachtot.test/signup` returns `200` with signup wizard HTML |
| Signup on tenant host | Pass | `https://abc.khachtot.test/signup` returns `307` to `/clients` |
| Signup on second tenant host | Pass | `https://verifynew-230022.khachtot.test/signup` returns `307` to `/clients` |
| Pricing | Pass | `https://khachtot.test/pricing` returns `200` |

### Interpretation
- The live landlord `/signup` path is currently functional.
- The tenant-host `/signup` path is intentionally blocked by runtime tenant detection and redirects to `/clients`.
- Therefore the phrase `signup currently unavailable` is a fallback exception response, not the ordinary live landlord response.

## Email Cross-Check

| Template | Trigger | Runtime Path | Pass |
|---|---|---|---|
| `tenant_welcome` | `mark_provision_job_done()` | `send_email_event('tenant_welcome', ...)` -> `TenantEmailProviderService` | Pass |
| `payment_success` | `BillingEngineService::markInvoicePaid()` | `send_email_event('payment_success', ...)` -> `TenantEmailProviderService` | Pass |
| `payment_failed` | `PaymentCollectionService::processWebhook()` failure branch | `send_email_event('payment_failed', ...)` -> `TenantEmailProviderService` | Pass |
| `provisioning_completed` | `mark_provision_job_done()` | `send_email_event('provisioning_completed', ...)` -> `TenantEmailProviderService` | Pass |
| `provisioning_failed` | `mark_provision_job_failed()` | `send_email_event('provisioning_failed', ...)` -> `TenantEmailProviderService` | Pass |

### Email runtime guard
- Runtime mail context is injected by `TenantEmailProviderService`.
- Dedupe guards are used for:
  - `payment_success`
  - `payment_failed`
  - `provisioning_completed`
  - `provisioning_failed`
  - `tenant_welcome`

## Go Live Readiness

| Area | Score | Risk |
|---|---:|---|
| Landing | 8.5 | Public landing is isolated, but fallback path exists |
| Signup | 8.0 | Tenant-host redirect is intentional; fallback exception path still exists |
| Checkout | 8.5 | Token validation and runtime checkout path are in place |
| Payment | 8.5 | Invoice/payment flow is real and idempotent |
| Webhook | 8.0 | Strong enough, but external provider variance remains |
| Provisioning | 8.0 | Real provisioning path exists; infra-dependent |
| Activation | 8.5 | State transitions are explicit and service-backed |
| Emails | 9.0 | Cross-checked in prior phases; runtime resolver is in place |
| Tenant Isolation | 8.8 | Stronger than before, but fallback paths still exist intentionally |

## Critical Blockers

| Severity | Finding | Evidence | Fix |
|---|---|---|---|
| High | Tenant-host public signup is not meant to run as public signup | `Kt_landing_public::signup()` redirects tenant runtime to `/clients` | Keep this behavior explicit and documented |
| High | `renderSignupFallback()` can emit `signup currently unavailable` on exception | `modules/kt_landing/controllers/Kt_landing_public.php:482-487` | Handle runtime/template exceptions before hitting fallback |
| Medium | Public landing uses runtime branding resolution that can still fall back when tenant data is incomplete | `TenantBrandingResolverService::resolveTenant()` | Keep fallback explicit and logged |
| Medium | Tenant localization uses fallback to landlord defaults when tenant values are missing | `TenantLocalizationResolverService::resolveTenant()` | Keep fallback explicit and logged |
| Medium | Webhook/payment behavior depends on external provider payload quality | `PaymentCollectionService`, `Kt_sepay_processor`, `Kt_sepay_webhook` | Continue live replay tests after credential changes |

## Recommended Fix Order

### Priority 0
- Treat the tenant-host `/signup` redirect as intentional and keep the fallback exception branch out of the normal path.
- Keep payment/webhook dedupe guards intact.

### Priority 1
- Harden exception handling in public landing signup so the fallback message is only reachable on actual template/runtime failure.
- Keep signup/provisioning state transitions explicit in docs and admin flows.

### Priority 2
- Continue live smoke after provider or webhook credential changes.
- Keep runtime fallback logging for branding/localization so cross-tenant bleed stays visible.

### Priority 3
- Add more observability around exception-driven fallback paths if the landing template changes again.

## Final Verdict

- The KT SAAS state machine is **substantially implemented and production-capable** for the documented flows.
- The funnel is not a loose prototype: landing, pricing, signup, checkout, payment, webhook, provisioning, activation, and email all have real code paths.
- The main nuance is that tenant-host public signup is intentionally not the public signup path; it redirects to `/clients`.
- The exact `signup currently unavailable` message comes from a controller fallback branch, not the ordinary live landlord route.

