# KT PLATFORM UTF-8 HARDENING REPORT

## Executive Summary
Platform UTF-8 was hardened at the source, layout, response, and connection layers. The main change is not a one-off text fix: a guardrail script now fails the build if active source files contain BOM, invalid UTF-8, mojibake byte patterns, `utf8_encode()`, `utf8_decode()`, or unsafe `htmlentities()` usage in PHP/HTML layouts.

## Root Cause
The recurring mojibake came from a mix of:
- source files saved with corrupted text
- runtime fallback strings written in mixed encoding
- a few layout files missing explicit UTF-8 charset declarations
- tenant/provisioning DB defaults that could fall back to `utf8` / `utf8_general_ci`

## Files Audited
Active source surface checked:
- `application/views`
- `application/language/vietnamese`
- `application/config`
- `application/controllers`
- `application/helpers`
- `application/libraries`
- `modules/kt_landing`
- `modules/kt_saas`
- `modules/kt_sepay`
- `modules/kt_matbao_invoice`
- `modules/kt_inventory`

## Source Encoding Fixes
Files repaired to UTF-8 without BOM:
- `modules/kt_landing/views/public/signup.php`
- `modules/kt_landing/controllers/Kt_landing_public.php`
- `modules/kt_landing/views/public/signup_status.php`
- `modules/kt_landing/views/public/home.php`
- `modules/kt_landing/views/public/pricing.php`
- `modules/kt_landing/views/public/blog.php`
- landing template files under `modules/kt_landing/views/public/templates/*`
- `modules/kt_saas/views/public/checkout.php`
- `modules/kt_saas/views/dashboard/*`
- `modules/kt_saas/views/tenant/*`
- `modules/kt_saas/controllers/Checkout.php`
- `modules/kt_saas/controllers/Kt_saas.php`
- `modules/kt_sepay/controllers/*`
- `modules/kt_matbao_invoice/controllers/*`
- `modules/kt_saas/language/vietnamese/kt_saas_lang.php`
- `modules/kt_sepay/language/vietnamese/kt_sepay_lang.php`
- `modules/kt_matbao_invoice/language/vietnamese/kt_matbao_invoice_lang.php`
- `modules/kt_inventory/language/vietnamese/kt_inventory_lang.php`
- `application/language/vietnamese/vietnamese_lang.php`
- `modules/kt_saas/helpers/kt_saas_helper.php`

## HTML Header Fixes
Added explicit UTF-8 charset to the active admin shell:
- `application/views/admin/includes/head.php`

Landing/public layouts already expose `<meta charset="utf-8">` or equivalent in the active rendered views.

## HTTP Header Fixes
Verified live responses return UTF-8 HTML for the public surfaces:
- `http://khachtot.test/`
- `http://khachtot.test/pricing`
- `http://khachtot.test/signup`
- `http://khachtot.test/signup?plan_id=4`
- public invoice checkout URL for SePay / invoice payment

Observed headers:
- `Content-Type: text/html; charset=utf-8`

Relevant runtime output paths already set UTF-8 / UTF-8+JSON:
- `modules/kt_landing/controllers/Kt_landing_public.php`
- `modules/kt_landing/controllers/Kt_landing.php`
- `application/hooks/KtSaasTenantBootstrap.php`
- `modules/kt_saas/controllers/Kt_saas.php`
- `modules/kt_saas/controllers/Checkout.php`

## Database Charset/Collation Audit
Runtime DB defaults were hardened to `utf8mb4` / `utf8mb4_unicode_ci`:
- `application/config/database.php`
- `modules/kt_saas/models/Kt_saas_model.php`
- `modules/kt_saas/tenant_bootstrap/DatabaseSwitcher.php`
- `modules/kt_saas/provisioning/ProvisioningJobRunner.php`
- `modules/kt_saas/services/TenantReferenceDataBackfillService.php`
- `modules/kt_saas/services/TenantBackupService.php`
- `modules/kt_saas/services/TenantAdminAccessService.php`

Current config source also defines:
- `APP_DB_CHARSET=utf8mb4`
- `APP_DB_COLLATION=utf8mb4_unicode_ci`

No schema migration was performed in this pass.

## DB Content Cleanup
I did not bulk-update production content blindly. The active source files were repaired instead, and the public checkout/invoice path was verified live.

## Language File Policy
Policy enforced:
- Vietnamese content stays in UTF-8 language files or UTF-8 DB content
- no `utf8_encode()` / `utf8_decode()`
- no runtime "replace mojibake" workaround
- use `html_escape()` or explicit UTF-8-safe output

## AJAX/JSON Fixes
The guardrail script checks PHP/HTML files for unsafe encoding patterns and warns on missing UTF-8 charset near HTML `<head>` blocks in real layouts.

## Checkout/Payment Verification
Verified live checkout invoice page:
- HTTP 200
- `Content-Type: text/html; charset=utf-8`
- title rendered correctly as `Thanh toán hóa đơn dịch vụ`

Verified public landing/signup responses:
- HTTP 200
- UTF-8 HTML responses
- titles render correctly on `/`, `/pricing`, `/signup`, `/signup?plan_id=4`

## Prevention Script
Added:
- `tools/check_encoding.php`

What it checks:
- UTF-8 BOM
- invalid UTF-8
- mojibake byte sequences
- `utf8_encode()`
- `utf8_decode()`
- unsafe `htmlentities()` in PHP/HTML files
- missing `<meta charset="UTF-8">` in real layout heads

Current status:
- `php tools/check_encoding.php` => `PASS`

## Browser Screenshots
Not captured in this pass. Verification was done through live HTTP responses and source/layout checks.

## Regression Result
No regression observed on the verified public surfaces:
- landing
- pricing
- signup
- checkout invoice page

No syntax regressions on the edited files.

## Final Status
UTF-8 hardening is in place for the active platform surface.
The source, header, and DB default layers are now aligned to UTF-8 / utf8mb4, and the guardrail script will fail if future code reintroduces common encoding bugs.
