# KT SAAS TENANT RUNTIME ACCESS MATRIX REPORT

## Executive Summary

We verified the tenant runtime access layer at the route/entitlement boundary, not just the settings page.

The original P0 bug was real: `TenantEntitlementService::isTenantPortalRoute()` did not whitelist the tenant email settings endpoints, so the bootstrap guard treated them as landlord-only. That whitelist gap is already fixed in the codebase, and `tenant_email_settings_save` now reaches the controller and returns a redirect instead of `Application not enabled`.

The tenant runtime is still **not fully operational**, because at least one tenant POST flow still fails live:
- `tenant_email_settings_test` still fails at runtime in smoke testing.

So the current state is:
- tenant portal route whitelist is partially corrected
- some tenant settings routes now work end-to-end
- at least one tenant runtime POST path is still broken
- landlord-only KT SaaS admin pages still remain blocked under tenant context, which is expected

## Tenant Menu Inventory

| Menu | URL | Module |
| ---- | --- | ------ |
| CRM dashboard | `/admin/dashboard` | core CRM |
| Customers | `/admin/customers` | core CRM |
| Leads | `/admin/leads` | core CRM |
| Invoices | `/admin/invoices` | core CRM |
| Estimates | `/admin/estimates` | core CRM |
| Contracts | `/admin/contracts` | core CRM |
| Projects | `/admin/projects` | core CRM |
| Tasks | `/admin/tasks` | core CRM |
| Tickets | `/admin/tickets` | core CRM |
| Reports | `/admin/reports` | core CRM |
| KT SaaS dashboard | `/admin/kt_saas` | kt_saas |
| Subscription | `/admin/kt_saas/tenant_subscription` | kt_saas |
| Billing | `/admin/kt_saas/tenant_billing` | kt_saas |
| Usage | `/admin/kt_saas/tenant_usage` | kt_saas |
| Settings | `/admin/kt_saas/tenant_settings` | kt_saas |
| Activity Logs | `/admin/kt_saas/tenant_activity_logs` | kt_saas |
| Governance | `/admin/kt_saas/tenant_governance` | kt_saas |
| Departments | `/admin/kt_saas/tenant_departments` | kt_saas |
| Renewal request | `/admin/kt_saas/tenant_request_renewal` | kt_saas |
| Email settings save/reset/test | `/admin/kt_saas/tenant_email_settings_save` / `reset` / `test` | kt_saas |
| KT SePay | module root / tenant payment surface | kt_sepay |
| KT MatBao Invoice | module root / tenant settings surface | kt_matbao_invoice |
| Landing CMS | module root | kt_landing |

## Route Inventory

| Route | Controller | Method | Module |
| ----- | ---------- | ------ | ------ |
| `/admin/kt_saas/tenant_subscription` | `Kt_saas` | `tenant_subscription()` | kt_saas |
| `/admin/kt_saas/tenant_request_renewal` | `Kt_saas` | `tenant_request_renewal()` | kt_saas |
| `/admin/kt_saas/tenant_billing` | `Kt_saas` | `tenant_billing()` | kt_saas |
| `/admin/kt_saas/tenant_usage` | `Kt_saas` | `tenant_usage()` | kt_saas |
| `/admin/kt_saas/tenant_settings` | `Kt_saas` | `tenant_settings()` | kt_saas |
| `/admin/kt_saas/tenant_email_settings_save` | `Kt_saas` | `tenant_email_settings_save()` | kt_saas |
| `/admin/kt_saas/tenant_email_settings_reset` | `Kt_saas` | `tenant_email_settings_reset()` | kt_saas |
| `/admin/kt_saas/tenant_email_settings_test` | `Kt_saas` | `tenant_email_settings_test()` | kt_saas |
| `/admin/kt_saas/tenant_activity_logs` | `Kt_saas` | `tenant_activity_logs()` | kt_saas |
| `/admin/kt_saas/tenant_governance` | `Kt_saas` | `tenant_governance()` | kt_saas |
| `/admin/kt_saas/tenant_role/{id?}` | `Kt_saas` | `tenant_role()` | kt_saas |
| `/admin/kt_saas/tenant_departments` | `Kt_saas` | `tenant_departments()` | kt_saas |
| `/admin/kt_saas/tenant_delete_role/{id}` | `Kt_saas` | `tenant_delete_role()` | kt_saas |
| `/admin/kt_saas/tenant_delete_department/{id}` | `Kt_saas` | `tenant_delete_department()` | kt_saas |
| `/admin/kt_saas/tenant_remove_company_logo/{type?}` | `Kt_saas` | `tenant_remove_company_logo()` | kt_saas |
| `/admin/kt_saas/tenant_remove_favicon` | `Kt_saas` | `tenant_remove_favicon()` | kt_saas |
| `/admin/kt_saas/recalculate_usage` | `Kt_saas` | `recalculate_usage()` | kt_saas admin |
| `/admin/kt_saas/cleanup_usage` | `Kt_saas` | `cleanup_usage()` | kt_saas admin |
| `/admin/kt_saas/run_billing_cycle` | `Kt_saas` | `run_billing_cycle()` | kt_saas admin |

## Access Matrix

| Route | Tenant | Landlord | Whitelist | Result |
| ----- | ------ | -------- | --------- | ------ |
| `/admin/kt_saas/tenant_settings` | Yes | No | Yes | PASS |
| `/admin/kt_saas/tenant_email_settings_save` | Yes | No | Yes | PASS |
| `/admin/kt_saas/tenant_email_settings_reset` | Yes | No | Yes | PASS |
| `/admin/kt_saas/tenant_email_settings_test` | Yes | No | Yes | PARTIAL - live send still fails |
| `/admin/kt_saas/tenant_subscription` | Yes | No | Yes | PASS |
| `/admin/kt_saas/tenant_billing` | Yes | No | Yes | PASS |
| `/admin/kt_saas/tenant_usage` | Yes | No | Yes | PASS |
| `/admin/kt_saas/tenant_activity_logs` | Yes | No | Yes | PASS |
| `/admin/kt_saas/tenant_governance` | Yes | No | Yes | PASS |
| `/admin/kt_saas/tenant_departments` | Yes | No | Yes | PASS |
| `/admin/kt_saas/tenant_delete_role/{id}` | Yes | No | Yes | PASS if capability granted |
| `/admin/kt_saas/tenant_delete_department/{id}` | Yes | No | Yes | PASS if capability granted |
| `/admin/kt_saas/tenants` | No | Yes | No | BLOCKED in tenant runtime |
| `/admin/kt_saas/subscriptions` | No | Yes | No | BLOCKED in tenant runtime |
| `/admin/kt_saas/invoices` | No | Yes | No | BLOCKED in tenant runtime |
| `/admin/kt_saas/domains` | No | Yes | No | BLOCKED in tenant runtime |

## Entitlement Audit

| Route | Current Result | Expected Result | Risk |
| ----- | -------------- | --------------- | ---- |
| `admin/kt_saas/tenant_email_settings_save` | Allowed after whitelist fix | Tenant portal POST allowed | Low |
| `admin/kt_saas/tenant_email_settings_reset` | Allowed after whitelist fix | Tenant portal POST allowed | Low |
| `admin/kt_saas/tenant_email_settings_test` | Allowed by bootstrap, but runtime POST still fails | Tenant portal POST should send test mail | High |
| `admin/kt_saas/tenant_subscription` | Allowed | Tenant portal page | Low |
| `admin/kt_saas/tenant_billing` | Allowed | Tenant portal page | Low |
| `admin/kt_saas/tenant_usage` | Allowed | Tenant portal page | Low |
| `admin/kt_saas/tenant_governance` | Allowed when workspace feature permits | Tenant portal page | Medium |
| `admin/kt_saas/tenants` | Blocked in tenant runtime | Landlord-only | Expected |
| `admin/kt_saas/invoices` | Blocked in tenant runtime | Landlord-only | Expected |

Relevant whitelist logic now lives in:
- `modules/kt_saas/services/TenantEntitlementService.php`
- `application/hooks/KtSaasTenantBootstrap.php`

## Form Action Audit

| Page | Form Action | Route Status | Issue |
| ---- | ----------- | ------------ | ----- |
| `modules/kt_saas/views/tenant/settings.php` | `/admin/kt_saas/tenant_settings` | PASS | Workspace save reaches controller and persists |
| `modules/kt_saas/views/tenant/settings.php` | `/admin/kt_saas/tenant_email_settings_save` | PASS | Now whitelisted and reachable |
| `modules/kt_saas/views/tenant/settings.php` | `/admin/kt_saas/tenant_email_settings_reset` | PASS | Now whitelisted and reachable |
| `modules/kt_saas/views/tenant/settings.php` | `/admin/kt_saas/tenant_email_settings_test` | PARTIAL | Runtime test send still fails |
| `modules/kt_saas/views/tenant/governance.php` | role/dept create/update/delete | PASS under capability | Guarded by workspace governance capability |

## AJAX Audit

There are no tenant-runtime AJAX-only endpoints in the verified settings path. The main async-like flow is the tenant email test POST, which is still the broken runtime action.

## Live Smoke Results

| Page | Load | Save | Test Action | Status |
| ---- | ---- | ---- | ----------- | ------ |
| `/admin/kt_saas/tenant_settings` | 200 | workspace save OK | tenant email test still unstable | PASS / PARTIAL |
| `/admin/kt_saas/tenant_subscription` | 200 | renewal and plan-change actions are POST-gated | not in this pass | PASS |
| `/admin/kt_saas/tenant_activity_logs` | 200 | read-only | n/a | PASS |
| `/admin/kt_saas/tenants` | 403 under tenant cookie | n/a | n/a | EXPECTED BLOCK |
| `/admin/kt_saas/invoices` | 403 under tenant cookie | n/a | n/a | EXPECTED BLOCK |

## P0 Issues

1. `tenant_email_settings_test` still fails live in tenant runtime.
2. Any tenant-host POST path that still depends on email send/runtime transport should be re-smoked before declaring tenant portal operational.

## P1 Issues

1. Tenant runtime has landlord-only KT SaaS admin pages that still return 403 under tenant context.
2. Capability-gated routes need clearer UI separation so tenant users do not see dead-end actions.

## P2 Issues

1. Settings page UX still mixes workspace and email sections on one screen.
2. Some labels are still English or mixed-language.
3. Button text on tenant settings still reads like a mail-specific action in a multi-tab page.

## Recommended Fix Order

1. Fix the remaining live failure in `tenant_email_settings_test`.
2. Re-smoke tenant settings end-to-end with a real POST + inbox trace.
3. Validate every tenant portal route against the entitlement whitelist.
4. Separate landlord-only surfaces from tenant-visible navigation if any of them are still exposed in UI.
5. Clean up P2 language/label issues after runtime stability is confirmed.

## Can Tenant Runtime Be Declared Operational?

No.

Reason:
- the tenant email settings access-guard bug is fixed
- but at least one tenant POST runtime action still fails live
- and landlord-only surface separation is not yet clean enough to call the whole tenant runtime operational

