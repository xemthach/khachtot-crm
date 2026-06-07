# KT SAAS TENANT ISOLATION FINAL REPORT

Scope:
- Audit tenant isolation end-to-end across `application/`, `kt_saas`, `kt_landing`, `kt_sepay`, `kt_matbao_invoice`, `kt_inventory`.
- No code changes.
- Focus on landlord bleed, tenant source of truth, branding, localization, email, invoice, landing, activation, and production blockers.

Tenant reviewed:
- `ABC Company` as requested in the prompt.

## 1. Source of Truth Audit

### Direct `get_option()` / `update_option()` audit

| File | Function | Option | Context | Risk |
|---|---|---|---|---|
| `modules/kt_landing/controllers/Kt_landing.php:270-377` | `buildLandingData()` | `companyname`, `company_logo`, `favicon`, `kt_landing_*` | Public landing branding/meta | **High**. Public landing reads landlord/global branding directly. |
| `modules/kt_landing/controllers/Kt_landing.php:528-812` | `handleSignupSubmit()`, `applyPlanOverrides()` | public plan / signup / CTA routing | Signup + public pricing | Medium. Safe if runtime context is correct, but still lands in public flow. |
| `modules/kt_saas/models/Kt_saas_model.php:278-445` | `save_tenant_workspace_settings()` | `default_timezone`, `dateformat`, `default_currency`, `companyname`, `email_header`, `email_footer`, `invoice_*` | Tenant workspace write path | Medium. Tenant settings are stored in tenant DB, but fallback logic still consults landlord defaults. |
| `modules/kt_saas/models/Kt_saas_model.php:4633-4799` | `tenant_option()`, `run_workspace_isolation_audit()` | `options` table values + landlord `get_option()` comparison | Runtime audit and read path | High. This is the key runtime source-of-truth layer. |
| `modules/kt_saas/controllers/Kt_saas.php:874-1273` | `settings_email_test()`, `tenant_settings()`, `tenant_email_settings_test()` | `companyname` fallback for sender name | Email provider test/runtime | Medium. Intentional landlord fallback only if no sender name is defined. |
| `modules/kt_saas/kt_saas.php:310-1240` | template seed/sync helpers | `companyname`, `company_logo`, `favicon`, `invoice_*`, `default_timezone`, `default_currency` | Template seed + runtime helpers | Medium. Used for seed/context, but needs strict runtime resolution. |
| `modules/kt_saas/models/Kt_saas_model.php:4006-4007` | settings save | `kt_saas_default_timezone`, `kt_saas_default_currency` | Landlord defaults | Low. These are landlord-level defaults, not tenant runtime. |
| `modules/kt_sepay/controllers/Kt_sepay.php:228-229` | admin dashboard | `kt_sepay_last_reconcile_at`, `kt_sepay_last_reconcile_transaction_id` | Landlord ops | Low. Operational only. |

### Read / write target summary

| Setting | Read Source | Write Target | Runtime Source | Match |
|---|---|---|---|---|
| Company Profile | `tenant_option()` in `get_tenant_workspace_settings()` | `save_tenant_workspace_settings()` -> tenant `tbloptions` | Tenant DB `options` | Partial. Tenant-owned, but fallback uses landlord defaults when missing. |
| Branding | `tenant_option()` + uploads in tenant DB | `handle_tenant_workspace_branding_uploads()` / `remove_tenant_workspace_branding()` | Tenant DB `options` + upload paths | Partial. Correct source exists, but public landing still reads landlord options directly. |
| Localization | `tenant_option()` + fallback to landlord `get_option()` | `save_tenant_workspace_settings()` | Tenant DB `options` | Partial. Runtime isolates, but fallback path is landlord-derived. |
| Mail Identity | `tenant_option()` + tenant email settings table | `save_tenant_email_settings()` | `TenantEmailProviderService` runtime context | Pass for tenant-controlled email; intentional landlord fallback exists for ops mail. |
| Invoice Defaults | `tenant_option()` + `save_tenant_workspace_settings()` | Tenant DB `options` | Tenant DB `options` + billing/runtime sync | Partial. Mostly tenant-owned, but fallback to company name and landlord defaults exists. |

## 2. Branding Isolation

### Areas audited
- Landlord
- Tenant
- Public Landing
- Tenant Login
- Tenant Dashboard
- Tenant Invoice
- Tenant Email

### Current state

| Area | Current Branding | Correct Branding | Risk |
|---|---|---|---|
| Landlord admin | `get_option('companyname')`, `company_logo`, `favicon` | Landlord branding | Low |
| Tenant workspace settings | `tenant_option('companyname')`, `company_logo`, `company_logo_dark`, `favicon` | Tenant branding | Low to medium, because it is tenant-scoped but governed by entitlements. |
| Public landing | `get_option('companyname')`, `company_logo`, `favicon` in `Kt_landing::buildLandingData()` | Landlord branding on public domain; tenant branding only if tenant subdomain runtime is intentional | **High**. This is the clearest landlord bleed risk. |
| Tenant login | Uses tenant landing/public context and brand assets from runtime data | Tenant branding on tenant host | Partial. Correct if runtime resolver is active. |
| Tenant dashboard | Tenant settings view renders tenant logo/favicon from tenant DB | Tenant branding | Low |
| Tenant invoice | Tenant invoice data should use tenant workspace/company settings and tenant issuer data | Tenant invoice issuer branding | Partial. Core Perfex invoice HTML still uses global logo hooks unless runtime isolation is enforced. |
| Tenant email | `TenantEmailProviderService` runtime branding context + tenant mail identity | Tenant branding for tenant-owned mail; landlord branding for operational alerts | Low to medium. Intentional context split is correct, but must remain consistent. |

### Specific bleed candidates
1. `modules/kt_landing/controllers/Kt_landing.php:277-283`
   - reads `companyname`, `company_logo`, `favicon` from landlord `get_option()` directly.
   - Risk: public landing on tenant host can show landlord brand if runtime context is wrong.

2. `application/views/themes/perfex/views/invoicehtml.php:8`
   - uses `get_dark_company_logo()`.
   - Risk: tenant invoice PDF/HTML can inherit landlord logo unless tenant runtime/company context is switched.

3. `application/views/themes/perfex/views/estimatehtml.php:7`
   - same logo path risk as invoice HTML.

4. `application/views/themes/perfex/views/contracthtml.php:7`
   - same logo path risk as invoice HTML.

5. `modules/kt_landing/views/public/templates/fastwork_inspired/index.php:112`
   - hard-coded logo wall tokens (`ATG`, `GREE`, `HVAC PRO`, etc.).
   - This is marketing content, but not tenant-specific. It is acceptable only if intentionally used as generic social proof placeholders.

## 3. Localization Isolation

### Audited fields
- Language
- Timezone
- Currency
- Date format
- Time format

### Runtime summary

| Setting | Tenant A | Tenant B | Landlord | Isolation |
|---|---|---|---|---|
| Language | resolved via `tenant_option('active_language')` / fallback to landlord active language | resolved via tenant DB | landlord global | Partial. Tenant-specific, but fallback exists. |
| Timezone | `tenant_option('default_timezone', landlordTimezone)` | tenant DB with landlord fallback | landlord global default timezone | Partial. |
| Currency | `tenant_option('default_currency', landlordCurrency)` | tenant DB with landlord fallback | landlord global default currency | Partial. |
| Date format | `tenant_option('dateformat', landlordDateFormat)` | tenant DB with landlord fallback | landlord global date format | Partial. |
| Time format | `tenant_option('time_format', landlordTimeFormat)` | tenant DB with landlord fallback | landlord global time format | Partial. |

### Evidence
- `modules/kt_saas/models/Kt_saas_model.php:63-75`
- `modules/kt_saas/models/Kt_saas_model.php:93-114`
- `modules/kt_saas/models/Kt_saas_model.php:173-176`
- `modules/kt_saas/models/Kt_saas_model.php:330-369`
- `modules/kt_saas/models/Kt_saas_model.php:430-444`

### Conclusion
- Localization is **tenant-scoped**, but not “no-fallback” isolated.
- That is acceptable only if fallback is intentional and documented.

## 4. Email Isolation

### Context from Email Phase 3
- Email runtime already goes through `TenantEmailProviderService`.
- Operational/billing mail uses landlord branding context by design.
- Tenant-owned configuration uses tenant email identity and tenant runtime context.

### Audit result

| Email Type | Current | Correct | Risk |
|---|---|---|---|
| Tenant operational alerts | landlord branding context | landlord branding context | Low |
| Tenant welcome / provisioning | landlord branding context | landlord branding context | Low |
| Tenant user-facing mail | tenant runtime identity/context | tenant runtime identity/context | Low to medium if resolver breaks |
| Landing/signup mail | landlord context | landlord context | Low |

### Evidence
- `modules/kt_saas/services/TenantEmailProviderService.php:129`
- `modules/kt_saas/controllers/Kt_saas.php:874-1273`
- `modules/kt_saas/models/Kt_saas_model.php:2720`
- `modules/kt_saas/kt_saas.php:359,386,397,410,421,...`

### Risk statement
- Tenant email isolation is structurally present, but the controller/views must not bypass the resolver.
- If a path calls `get_option('companyname')` or `get_dark_company_logo()` directly outside runtime context, landlord bleed can happen.

## 5. Invoice Isolation

### Components audited
- Invoice PDF / HTML
- Invoice email
- Invoice defaults
- eInvoice
- MatBao

### Findings

| Invoice Component | Source | Correct? |
|---|---|---|
| Tenant invoice issuer name/address/phone/tax code | `modules/kt_saas/models/Kt_saas_model.php:126-187`, `:376-445`, `:4711-4799` | Mostly yes, tenant DB is the source of truth. |
| Default invoice company fields | `save_tenant_workspace_settings()` | Yes, tenant DB write path exists. |
| Invoice PDF HTML logo | `application/views/themes/perfex/views/invoicehtml.php:8` | Partial. Depends on runtime logo resolution. |
| Estimate / contract / subscription PDF logos | `application/views/themes/perfex/views/estimatehtml.php:7`, `contracthtml.php:7`, `subscriptionhtml.php:7` | Partial. Same runtime logo risk. |
| MatBao invoice tenant fields | `modules/kt_matbao_invoice/controllers/Kt_matbao_invoice_tenant.php:684-758, 984-1015` | Mostly yes, tenant name/owner values are tenant-aware. |
| MatBao invoice seller data | `modules/kt_matbao_invoice/models/Kt_matbao_invoice_model.php:1347-1441` | Mostly yes; uses tenant/owner/company fallbacks, not raw landlord branding. |

### Risk
- Tenant invoice can still inherit landlord visual branding if core logo hooks are not switched at runtime.
- Functional invoice fields are mostly tenant-scoped; visual branding is the higher risk.

## 6. Landing Isolation

### Current source selection

| Page | Current Source | Correct Source |
|---|---|---|
| Public landlord landing | `get_option('companyname')`, `company_logo`, `favicon` | Landlord branding |
| Tenant subdomain landing | runtime tenant branding data, if tenant context is resolved | Tenant branding |
| Footer/company text | `kt_landing_footer_text` or `companyname` fallback | Correct only when runtime context is intentional |

### Evidence
- `modules/kt_landing/controllers/Kt_landing.php:277-283`
- `modules/kt_landing/controllers/Kt_landing.php:377`
- `modules/kt_landing/views/public/templates/fastwork_inspired/index.php:71-72, 112, 376-381`

### Conclusion
- Landing is the clearest public bleed surface.
- It is acceptable for landlord public marketing pages, but risky on tenant subdomains if runtime context is not forced.

## 7. Activation Defaults

### After provisioning, tenant should receive:
- branding defaults
- localization defaults
- invoice defaults
- mail defaults

### Evidence of provisioning path
- `modules/kt_saas/models/Kt_saas_model.php:1832-2143` (`save_tenant()`)
- `modules/kt_saas/models/Kt_saas_model.php:3052-3172` (`create_provision_job()`, `mark_provision_job_done()`)
- `modules/kt_saas/models/Kt_saas_model.php:3608-3829` (`normalizeTenantStateOnSave()`, `sync_tenant_module_registry()`, `sync_tenant_runtime_modules()`)
- `modules/kt_saas/provisioning/ProvisioningJobRunner.php`

### Findings

| Setting Group | Provisioned? | Source |
|---|---|---|
| Branding defaults | yes | tenant workspace settings + branding upload storage |
| Localization defaults | yes | tenant workspace settings with landlord fallback |
| Invoice defaults | yes | tenant workspace settings + sync functions |
| Mail defaults | yes | tenant email settings + runtime provider resolver |

### Risk
- Defaults are provisioned, but fallback logic can still pull landlord values where tenant data is missing.

## 8. Hard-Code Audit

### Hard-coded / demo values found

| File | Hard-coded Value | Risk |
|---|---|---|
| `modules/kt_landing/controllers/Kt_landing.php:279` | `KT SaaS Platform` | Low. Fallback label, but should only be used when brand name is missing. |
| `modules/kt_landing/controllers/Kt_landing.php:294-342` | default hero/meta marketing copy | Low. Generic marketing fallback, not tenant data. |
| `modules/kt_landing/views/public/templates/fastwork_inspired/index.php:112` | `ATG`, `GREE`, `HVAC PRO`, `Retail 247`, `BuildCom`, `SME Logistic` | Medium. Marketing placeholders; acceptable only if intentionally generic. |
| `modules/kt_matbao_invoice/views/tenant/settings.php:5` | `Demo` / `Production` environment labels | Low. This is an environment selector, not branding. |
| `modules/kt_sepay/cron/Kt_sepay_cron.php:88` | `Landlord` / `Tenant #...` | Low. Operational label only. |

### Verdict
- No obvious malicious hard-coded tenant bleed found in the scanned modules beyond known public landing fallback paths.
- The main hard-code risk is **branding fallback**, not data corruption.

## 9. Live Isolation Test

### Existing audit route
- JSON: `admin/kt_saas/workspace_isolation_audit/{tenantId}`
- HTML: `admin/kt_saas/workspace_isolation_audit_report/{tenantId}`

### Evidence
- `modules/kt_saas/controllers/Kt_saas.php:251-262`
- `modules/kt_saas/views/dashboard/tenants.php:148-149`
- `modules/kt_saas/models/Kt_saas_model.php:4711-4800`

### Status
- Live tenant A / tenant B / landlord comparison was **not executed in this pass**.
- Reason: this audit pass was performed statically from code and repo artifacts only.

### What should be tested live
| Area | Tenant A | Tenant B | Landlord | Pass |
|---|---|---|---|---|
| Dashboard | tenant DB/runtime | tenant DB/runtime | landlord DB/runtime | pending live execution |
| Invoice | tenant issuer/logo/currency | tenant issuer/logo/currency | landlord issuer/logo/currency | pending live execution |
| Email | tenant/landlord split by context | tenant/landlord split by context | landlord ops | pending live execution |
| Landing | tenant subdomain brand vs landlord public brand | tenant subdomain brand vs landlord public brand | landlord public brand | pending live execution |
| Settings | tenant workspace settings | tenant workspace settings | landlord settings | pending live execution |
| Login | tenant brand | tenant brand | landlord login | pending live execution |

## 10. Critical Findings

1. **Public landing reads landlord branding directly**
   - `modules/kt_landing/controllers/Kt_landing.php:277-283`
   - High risk if tenant runtime context is not forced on tenant subdomains.

2. **Tenant isolation is built on fallback, not absolute separation**
   - `modules/kt_saas/models/Kt_saas_model.php:63-114`, `:330-369`, `:4711-4799`
   - Tenant values can fall back to landlord defaults.

3. **Invoice visual branding still depends on global logo hooks in core Perfex views**
   - `application/views/themes/perfex/views/invoicehtml.php:8`
   - Same for estimate/contract/subscription HTML.

4. **Public funnel and tenant runtime share the same underlying brand/company options**
   - `companyname`, `company_logo`, `favicon` are reused across public and runtime layers.
   - This is acceptable only when runtime context is correctly scoped.

5. **Live isolation evidence is missing in this pass**
   - The audit route exists, but tenant A/B comparison was not executed here.

## 11. Fix Plan

### Priority 0
- Remove landlord bleed on public/tenant subdomain paths.
- Force runtime tenant branding resolver on tenant-host public pages.

### Priority 1
- Normalize tenant setting resolver so tenant DB is the primary source of truth.
- Reduce landlord fallback to explicit, logged intentional fallback only.

### Priority 2
- Normalize branding resolver for:
  - landing
  - invoice HTML/PDF
  - email sender identity

### Priority 3
- Normalize localization resolver:
  - language
  - timezone
  - currency
  - date/time format

### Priority 4
- Add regression tests and live tenant A/B isolation smoke tests.

## 12. Production Blockers

- Public landing can bleed landlord branding on tenant-hosted pages if runtime context is wrong.
- Invoice PDF/HTML branding still depends on core logo hooks that are not tenant-isolated by default.
- Tenant settings are tenant-scoped, but fallback-to-landlord behavior is still present.
- Live tenant A/B isolation test was not executed in this static pass.

## 13. Final Verdict

- **Tenant isolation is partially implemented, not fully locked.**
- The most serious blocker is **branding bleed on public/tenant runtime paths**.
- The system is not production-ready until the runtime resolver is enforced across landing, invoice, and tenant-facing public views.

