# KT SAAS SETTINGS END-TO-END AUDIT REPORT

## Executive Summary

Tenant settings on `somogyimarty322493.khachtot.test` were not failing because the form route was missing. The actual blocker was the tenant bootstrap entitlement gate in `modules/kt_saas/services/TenantEntitlementService.php`, which classified tenant email settings endpoints as landlord-only and returned `Application not enabled` / `This area is available only in landlord context.` before controller execution.

The tenant workspace settings flow and the tenant email settings flow have different sources of truth:

- Workspace settings are saved into the tenant DB `options` table and mirrored into landlord summary fields.
- Tenant email settings are saved into the landlord DB table `kt_saas_tenant_email_settings`.

The tenant settings page is a combined workspace surface with a separate email submit button, which is valid architecturally but confusing from a UX standpoint. That is a P2 polish issue, not the P0 blocker.

The entitlement whitelist has been updated to allow the tenant email settings endpoints.

## Route Inventory

| Page | Form Action | Controller Method | Context Required | Current Result | Correct Context |
|---|---|---|---|---|---|
| `/admin/kt_saas/tenant_settings` | `admin_url('kt_saas/tenant_settings')` | `Kt_saas::tenant_settings()` | Tenant runtime | OK when tenant-host route is allowed | Tenant |
| `/admin/kt_saas/tenant_email_settings_save` | `formaction="admin_url('kt_saas/tenant_email_settings_save')"` | `Kt_saas::tenant_email_settings_save()` | Tenant runtime | Previously blocked by bootstrap entitlement gate | Tenant |
| `/admin/kt_saas/tenant_email_settings_reset` | POST action from tenant settings email block | `Kt_saas::tenant_email_settings_reset()` | Tenant runtime | Previously blocked by bootstrap entitlement gate | Tenant |
| `/admin/kt_saas/tenant_email_settings_test` | POST action from tenant settings email block | `Kt_saas::tenant_email_settings_test()` | Tenant runtime | Previously blocked by bootstrap entitlement gate | Tenant |
| `/admin/kt_saas/settings` | landlord settings page | landlord settings controller path | Landlord runtime | Separate flow | Landlord |
| `/admin/kt_saas/settings_save` | landlord save action | landlord settings controller path | Landlord runtime | Separate flow | Landlord |

## Context Guard Audit

| Method | Current Guard | Expected Guard | Issue | Fix |
|---|---|---|---|---|
| `tenant_settings` | Tenant runtime entitlement check via bootstrap; allowed | Tenant runtime | OK for tenant workspace settings | No change required |
| `tenant_email_settings_save` | Bootstrap treated it as `landlord_only_module` | Tenant runtime | Blocked before controller execution | Added to tenant portal whitelist in `TenantEntitlementService::isTenantPortalRoute()` |
| `tenant_email_settings_reset` | Bootstrap treated it as `landlord_only_module` | Tenant runtime | Blocked before controller execution | Added to tenant portal whitelist |
| `tenant_email_settings_test` | Bootstrap treated it as `landlord_only_module` | Tenant runtime | Blocked before controller execution | Added to tenant portal whitelist |

### Root cause file

- `modules/kt_saas/services/TenantEntitlementService.php`
- Method: `isTenantPortalRoute($uri)`
- The whitelist previously omitted:
  - `admin/kt_saas/tenant_email_settings_save`
  - `admin/kt_saas/tenant_email_settings_reset`
  - `admin/kt_saas/tenant_email_settings_test`

## Form Action Audit

| View | Form | Current Action | Expected Action | Submit Label | Issue |
|---|---|---|---|---|---|
| `modules/kt_saas/views/tenant/settings.php` | Workspace settings form | `admin_url('kt_saas/tenant_settings')` | Same | N/A (main workspace save) | Valid, tenant-scoped |
| `modules/kt_saas/views/tenant/settings.php` | Tenant email settings submit | `formaction="admin_url('kt_saas/tenant_email_settings_save')"` | Same | `Luu cau hinh email` | Correct action, but label is confusing because it sits inside a broader tenant workspace page |
| `modules/kt_saas/views/tenant/settings.php` | Tenant email reset/test block | POST buttons inside the email area | `tenant_email_settings_reset` / `tenant_email_settings_test` | `Khoi phuc mac dinh` / `Gui email thu` (recommended) | Functionally fine, but UX text is inconsistent |

## Source Of Truth Matrix

| Field | Scope | Current Control | Source Of Truth | DB Target | Runtime Resolver | Risk |
|---|---|---|---|---|---|---|
| `companyname` | Tenant workspace | Text input | Tenant workspace settings | Tenant DB `options` | `get_tenant_workspace_settings()` | Low |
| `company_email` | Tenant workspace | Text input | Tenant workspace settings | Tenant DB `options` | `get_tenant_workspace_settings()` | Low |
| `companyphonenumber` | Tenant workspace | Text input | Tenant workspace settings | Tenant DB `options` | `get_tenant_workspace_settings()` | Low |
| `company_vat` | Tenant workspace | Text input | Tenant workspace settings | Tenant DB `options` | `get_tenant_workspace_settings()` | Low |
| `active_language` | Tenant workspace | Dropdown | Tenant workspace settings | Tenant DB `options` | `get_tenant_workspace_settings()` | Low |
| `default_timezone` | Tenant workspace | Dropdown | Tenant workspace settings | Tenant DB `options` | `get_tenant_workspace_settings()` | Low |
| `default_currency` | Tenant workspace | Dropdown | Tenant workspace settings | Tenant DB `options` | `get_tenant_workspace_settings()` | Low |
| `dateformat` | Tenant workspace | Dropdown | Tenant workspace settings | Tenant DB `options` | `get_tenant_workspace_settings()` | Low |
| `time_format` | Tenant workspace | Dropdown | Tenant workspace settings | Tenant DB `options` | `get_tenant_workspace_settings()` | Low |
| `company_logo` | Tenant workspace | Upload | Tenant workspace settings | Tenant DB `options` | `get_tenant_workspace_settings()` | Low |
| `favicon` | Tenant workspace | Upload | Tenant workspace settings | Tenant DB `options` | `get_tenant_workspace_settings()` | Low |
| `kt_saas_mail_from_name` | Tenant email | Text input | Tenant email settings table | `kt_saas_tenant_email_settings` | `TenantEmailProviderService` | Medium |
| `kt_saas_mail_reply_to_email` | Tenant email | Text input | Tenant email settings table | `kt_saas_tenant_email_settings` | `TenantEmailProviderService` | Medium |
| `provider` | Tenant email | Dropdown | Tenant email settings table | `kt_saas_tenant_email_settings` | `TenantEmailProviderService` | Medium |
| `brevo_api_key` | Tenant email | Secret input | Tenant email settings table | `kt_saas_tenant_email_settings` | `TenantEmailProviderService` | High |
| `smtp_*` | Tenant email | Inputs | Tenant email settings table | `kt_saas_tenant_email_settings` | `TenantEmailProviderService` | High |
| landlord defaults | Landlord global | Landlord settings | Landlord settings table/options | Landlord DB | Landlord runtime bootstrap | Low |

## Save / Reload Verification

### Verified control paths

- Tenant workspace settings save path is `Kt_saas::tenant_settings()` -> `Kt_saas_model::save_tenant_workspace_settings()`
- Tenant email settings save path is `Kt_saas::tenant_email_settings_save()` -> `Kt_saas_model::save_tenant_email_settings()`

### Runtime behavior

- Workspace settings are loaded back through `get_tenant_workspace_settings()`
- Email settings are loaded back through `get_tenant_email_setting()` / `get_active_tenant_email_setting()`
- Bootstrap entitlement gate was the missing piece for the email endpoints, not the save logic

### Verification status

| Setting | Changed To | DB Value | Reload Value | Runtime Value | Pass/Fail |
|---|---|---|---|---|---|
| Tenant workspace language | Vietnamese | Tenant DB `options` | Reads back from tenant DB | Used by tenant runtime resolver | PASS (code path) |
| Tenant timezone | Asia/Ho_Chi_Minh | Tenant DB `options` | Reads back from tenant DB | Used by tenant runtime resolver | PASS (code path) |
| Tenant currency | VND | Tenant DB `options` | Reads back from tenant DB | Used by finance/runtime resolver | PASS (code path) |
| Company name | tenant-specific | Tenant DB `options` | Reads back from tenant DB | Used by branding/runtime resolver | PASS (code path) |
| Tenant email provider | tenant-specific | `kt_saas_tenant_email_settings` | Reads back from landlord DB | Used by `TenantEmailProviderService` | PASS (code path after whitelist fix) |

## Email Settings Audit

| Flow | Expected | Actual | Root Cause | Fix |
|---|---|---|---|---|
| Tenant email settings save | Reach controller and persist tenant email config | Previously blocked with `Application not enabled` | Bootstrap entitlement whitelist omitted the endpoint | Added the endpoint to tenant portal whitelist |
| Tenant email settings reset | Reach controller and reset tenant provider to landlord global | Previously blocked with `Application not enabled` | Same as above | Added the endpoint to tenant portal whitelist |
| Tenant email settings test | Reach controller and send tenant test email | Previously blocked with `Application not enabled` | Same as above | Added the endpoint to tenant portal whitelist |
| Global email settings/test | Landlord-only path | Separate flow | Not a bug | No change required |

## UI/UX Language Audit

| Current Text | Correct Text | Location |
|---|---|---|
| `Ho so & Thuong hieu` | `Hồ sơ & Thương hiệu` | `modules/kt_saas/views/tenant/settings.php` |
| `Tai chinh` | `Tài chính` | `modules/kt_saas/views/tenant/settings.php` |
| `Thong bao` | `Thông báo` | `modules/kt_saas/views/tenant/settings.php` |
| `Truy cap & Dieu phoi` | `Truy cập & Điều phối` | `modules/kt_saas/views/tenant/settings.php` |
| `Luu cau hinh email` | `Lưu cài đặt email` or `Lưu cấu hình email` | `modules/kt_saas/views/tenant/settings.php` |
| `Workspace Settings` | `Cài đặt không gian làm việc` or `Cài đặt doanh nghiệp` | `modules/kt_saas/controllers/Kt_saas.php` page title |
| `Ho so doanh nghiep` | `Hồ sơ doanh nghiệp` | tenant settings view |
| `Tai chinh nang cao` | `Tài chính nâng cao` | tenant settings view |

These are UX cleanup items. They do not cause the P0 blocker.

## Root Causes

### P0

1. **Tenant email settings actions were blocked by the tenant bootstrap entitlement gate**
   - File: `modules/kt_saas/services/TenantEntitlementService.php`
   - Method: `isTenantPortalRoute($uri)`
   - Cause: missing whitelist entries for `tenant_email_settings_save`, `tenant_email_settings_reset`, and `tenant_email_settings_test`
   - Effect: bootstrap returned `landlord_only_module`, which surfaced as `Application not enabled`
   - Fix: whitelist the three tenant email endpoints

### P1

1. **Settings scope is split across two storage targets**
   - Workspace data -> tenant DB `options`
   - Tenant email data -> landlord DB `kt_saas_tenant_email_settings`
   - This is intentional, but it must be communicated in the admin UI

2. **Reload/runtime validation was not obvious to operators**
   - The save path exists, but the email endpoints were unreachable until the whitelist fix

### P2

1. **Submit labels are confusing on a mixed settings surface**
2. **Several labels still lack proper Vietnamese accents**

## Fix Plan

| Priority | Issue | Root Cause | Fix | Test |
|---|---|---|---|---|
| P0 | Tenant email settings save/reset/test blocked | Missing tenant portal whitelist entries | Add the three endpoints to `isTenantPortalRoute()` | POST endpoints on tenant host should reach controller and save without 403 |
| P1 | Workspace settings and email settings are split across two DB targets | Intentional architecture | Document source of truth in UI or help text | Save/reload verification for both scopes |
| P2 | Confusing button label on email block | Mixed surface UX | Rename button and tighten section labels | Browser review on tenant settings page |
| P2 | Romanized / mixed-language labels | Incomplete copy cleanup | Normalize labels to proper Vietnamese | Text regression pass |

## Tests Required

1. Browser test on tenant host:
   - open `/admin/kt_saas/tenant_settings`
   - submit workspace settings
   - submit tenant email settings
   - reset tenant email settings
   - send tenant test email

2. Verify DB persistence:
   - tenant DB `options`
   - landlord DB `kt_saas_tenant_email_settings`

3. Verify runtime effects:
   - workspace language/timezone/currency
   - tenant email provider and sender fields

4. Verify bootstrap behavior:
   - no more `Application not enabled`
   - no more `This area is available only in landlord context.` on tenant email endpoints

## Can Tenant Settings Be Declared Operational?

**Not yet as a full platform-wide statement**, but the P0 blocker has been identified and fixed in the entitlement whitelist.

Current status:

- Workspace settings path: operational by code path
- Tenant email settings path: operational after whitelist fix
- Landlord vs tenant storage split: intentional and understood
- UX copy: still needs cleanup

So the correct answer is:

- **P0 blocker: resolved**
- **Tenant settings overall: not yet fully polished**
- **Can be declared operational after live browser POST verification on the tenant host confirms save/reload/runtime behavior**
