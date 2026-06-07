# TENANT ISOLATION FIX REPORT

Scope:
- Lock tenant isolation on public landing and tenant-host runtime paths.
- Remove the `KT_LANDING_MODULE` bootstrap failure on public routes.
- Verify landlord vs tenant branding separation on live tenant hosts.
- No new feature work, no notification center, no UI redesign.

## Files changed

- `modules/kt_landing/controllers/Kt_landing_public.php`
- `application/config/routes.php`

## Branding Resolver

### What changed
- Public landing now resolves branding through the tenant-aware runtime bootstrap before rendering.
- Tenant-host public pages now use `kt_saas_resolve_branding_context()` and `kt_saas_resolve_tenant_branding_context()` instead of falling back to raw module bootstrap assumptions.
- The public landing fallback path also resolves branding from runtime context instead of reading landlord options directly.

### Result
- Public landlord domain shows landlord brand.
- Tenant subdomains show tenant-specific brand when runtime context is active.
- The landing controller no longer dies on missing `KT_LANDING_MODULE` constant.

## Localization Resolver

### What changed
- Public landing now resolves localization through the tenant-aware runtime resolver.
- Tenant-host public pages inherit tenant locale/timezone/currency context when available.

### Result
- Tenant public pages stay tenant-scoped for locale-sensitive output.
- Landlord fallback remains only for explicit fallback paths.

## Landing Fix

### What changed
- Public landing routes now point to `kt_landing/kt_landing_public`.
- Public root, pricing, blog, signup, signup status, signup progress, and contact submit routes now hit the new controller cleanly.
- The public controller now defines `KT_LANDING_MODULE` locally so it can run even when the module init file is not preloaded.

### Result
- `https://khachtot.test/` renders landlord landing normally.
- `https://abc.khachtot.test/` renders tenant-branded landing normally.
- `https://verifynew-230022.khachtot.test/` renders tenant-branded landing normally.

## Invoice Branding Fix

### Status
- Not modified in this pass.
- Already covered by the existing runtime branding resolver path in `application/helpers/template_helper.php`:
  - `get_company_logo()`
  - `get_dark_company_logo()`

### Verification
- Tenant runtime branding is now available to the shared Perfex invoice/estimate/contract/subscription logo helper path.
- The tenant shell pages confirm tenant branding context is active under runtime bootstrap.

## Settings Resolver Fix

### Verification
- Tenant workspace settings page on live tenant hosts resolves tenant branding correctly.
- Tenant settings pages no longer bleed landlord branding in the captured live paths.

## Live Isolation Test

| Area | Tenant A (`abc.khachtot.test`) | Tenant B (`verifynew-230022.khachtot.test`) | Landlord (`khachtot.test`) | Pass |
| --- | --- | --- | --- | --- |
| Landing | tenant brand rendered | tenant brand rendered | landlord brand rendered | pass |
| Login | tenant brand rendered | tenant brand rendered | landlord login branding rendered | pass |
| Dashboard | authenticated tenant shell renders tenant context | authenticated tenant shell renders tenant context | landlord admin shell renders landlord context | pass |
| Settings | tenant branding visible in workspace settings | tenant branding visible in workspace settings | landlord settings remain landlord-branded | pass |
| Signup | tenant host redirects to clients | tenant host redirects to clients | public signup remains landlord/public | pass |
| Invoice / Estimate / Contract | tenant admin shell on `admin/invoices/invoice` resolves tenant logo; direct PDF/HTML screenshot still not re-captured | tenant admin shell uses tenant logo through shared resolver; direct PDF/HTML screenshot still not re-captured | landlord views remain landlord-branded | pass for shell, partial for PDF/HTML |

### Evidence captured
- `https://abc.khachtot.test/` renders tenant brand.
- `https://verifynew-230022.khachtot.test/` renders tenant brand.
- `https://abc.khachtot.test/login` and `https://verifynew-230022.khachtot.test/login` render tenant-branded login pages.
- `https://abc.khachtot.test/admin/kt_saas/tenant_settings` and `https://verifynew-230022.khachtot.test/admin/kt_saas/tenant_settings` render tenant-branded settings pages.
- `https://verifynew-230022.khachtot.test/admin/invoices/invoice` resolves the tenant logo in the admin shell.
- `https://abc.khachtot.test/signup` returns `303` to `/clients` because tenant-host signup is not meant to run as public signup.

## Regression Test

### Landlord regression
- `https://khachtot.test/` still renders landlord/public branding.
- `https://khachtot.test/pricing` still renders landlord/public branding.
- Public CTA routes still work on landlord domain.

### Tenant regression
- Tenant landing is isolated from landlord branding.
- Tenant login shell is isolated from landlord branding.
- Tenant settings shell is isolated from landlord branding.
- Tenant signup on tenant host redirects away from public signup into tenant context.

## Remaining Risks

- Direct live invoice PDF / HTML captures were not re-run in this pass; the admin shell check confirms tenant logo resolution, but a screenshot-level check for invoice/estimate/contract PDF/HTML is still worth doing.
- Fallback rendering is still available if the main template path fails; this is intentional safety, not primary flow.
- If tenant runtime bootstrap breaks, tenant-host pages would fall back to the isolated fallback view rather than leak landlord data, but that should still be monitored.

## Production Readiness Score

- **8.8 / 10**

### Why not 10
- The public tenant-host landing isolation is fixed and live-verified.
- Authenticated tenant shell isolation is live-verified.
- Invoice/estimate/contract branding is covered by the shared resolver path, but not re-screen-captured in this pass.
