# DOCUMENT BRANDING VERIFICATION REPORT

Scope:
- Verify invoice / estimate / contract / subscription PDF or HTML render against tenant branding isolation.
- No landing changes.
- No email changes.
- No signup changes.
- No refactor.

## Helper audit

| File/helper | Current source | Tenant-aware? | Risk |
|---|---|---|---|
| `application/helpers/template_helper.php::get_company_logo()` | Resolves `kt_saas_resolve_branding_context(['scope' => 'ui'])` first, then falls back to landlord options | Yes, when tenant runtime is active | Low to medium because fallback to landlord still exists intentionally |
| `application/helpers/template_helper.php::get_dark_company_logo()` | Resolves tenant branding context first, then falls back to `get_company_logo()` / landlord options | Yes | Low to medium for the same fallback reason |
| `application/views/themes/perfex/views/invoicehtml.php` | Uses `get_dark_company_logo()` and shared organization info helpers | Yes through shared helper path | Medium if runtime context is missing |
| `application/views/themes/perfex/views/estimatehtml.php` | Uses `get_dark_company_logo()` and shared organization info helpers | Yes through shared helper path | Medium if runtime context is missing |
| `application/views/themes/perfex/views/contracthtml.php` | Uses `get_dark_company_logo()` | Yes through shared helper path | Medium if runtime context is missing |
| `application/views/themes/perfex/views/subscriptionhtml.php` | Uses `get_dark_company_logo()` and shared organization info helpers | Yes through shared helper path | Medium, and live Stripe-backed preview was not fully available in this pass |

## Tenant A document test

Tenant:
- `abc.khachtot.test`
- tenant id `4`
- tenant DB: `khachtot_tenant_abc_4`

Created test documents:
- invoice `id=32`
- estimate `id=1`
- contract `id=1`

### HTML render

| Document | Observed branding | Pass |
|---|---|---|
| Invoice HTML | Tenant logo asset rendered from `abc.khachtot.test/uploads/company/...`, company block shows tenant branding for `CÔNG TY TNHH ĐIỀU HÒA GREE (VIỆT NAM)`, recipient/company info shows the test client, item table renders correctly | pass |
| Estimate HTML | Tenant logo asset rendered from `abc.khachtot.test/uploads/company/...`, company block shows tenant branding, item table renders correctly | pass |
| Contract HTML | Tenant logo asset rendered from `abc.khachtot.test/uploads/company/...`, contract body renders correctly | pass |

### PDF render

| Document | Observed branding | Pass |
|---|---|---|
| Invoice PDF | PDF export succeeded; extracted text shows tenant company header, address, item line, and `₫` currency | pass |
| Estimate PDF | PDF export succeeded; extracted text shows tenant company header, address, item line, and `₫` currency | pass |
| Contract PDF | PDF export succeeded; extracted text shows contract body content; branding is verified by the HTML render path and shared helper path | pass, with text-extraction limitation |

### Tenant A evidence
- Tenant-specific logo path rendered in HTML.
- Tenant company data rendered in invoice/estimate headers.
- Tenant currency rendered correctly as `₫`.
- No landlord brand bleed observed.

## Tenant B document test

Tenant:
- `verifynew-230022.khachtot.test`
- tenant id `3`
- tenant DB: `khachtot_tenant_verifynew_230022`

Created test documents:
- invoice `id=30`
- estimate `id=1`
- contract `id=1`

### HTML render

| Document | Observed branding | Pass |
|---|---|---|
| Invoice HTML | Tenant logo asset rendered from `verifynew-230022.khachtot.test/uploads/company/...`, company block shows tenant branding for `CÔNG TY TNHH ĐIỀU HÒA GREE (VIỆT NAM)`, item table renders correctly | pass |
| Estimate HTML | Tenant logo asset rendered from `verifynew-230022.khachtot.test/uploads/company/...`, company block shows tenant branding, item table renders correctly | pass |
| Contract HTML | Tenant logo asset rendered from `verifynew-230022.khachtot.test/uploads/company/...`, contract body renders correctly | pass |

### PDF render

| Document | Observed branding | Pass |
|---|---|---|
| Invoice PDF | PDF export succeeded; extracted text shows tenant company header, address, item line, and `$` currency | pass |
| Estimate PDF | PDF export succeeded; extracted text shows tenant company header, address, item line, and `$` currency | pass |
| Contract PDF | PDF export succeeded; extracted text shows contract body content; branding is verified by the HTML render path and shared helper path | pass, with text-extraction limitation |

### Tenant B evidence
- Tenant-specific logo path rendered in HTML.
- Tenant company data rendered in invoice/estimate headers.
- Tenant currency rendered correctly as `$`.
- No landlord brand bleed observed.

## Landlord regression

Landlord host:
- `khachtot.test`

Created landlord test documents:
- invoice `id=31`
- estimate `id=1`
- contract `id=1`

### HTML/PDF regression

| Document | Branding | Pass |
|---|---|---|
| Invoice | Landlord header/logo renders as `Atolgo Trading and Service Co., Ltd` and `Khách Tốt`; invoice body still renders correctly | pass |
| Estimate | Landlord header/logo renders as `Atolgo Trading and Service Co., Ltd` and `Khách Tốt`; estimate body still renders correctly | pass |
| Contract | Landlord contract render remains landlord-branded through the shared helper path | pass |

## Issues found

1. Subscription document preview was not fully verifiable in this pass because the current environment does not expose a safe local Stripe plan/subscription preview path.
2. PDF text extraction for contract output surfaced body text more clearly than header branding text, so contract branding was confirmed from the HTML render plus the shared helper path rather than PDF text alone.

## Fix required if any

- No code fix is required for invoice, estimate, or contract branding.
- Subscription verification still needs a dedicated Stripe-safe test fixture or sandbox plan path before it can be marked fully covered.

## Production readiness update

- Tenant branding isolation for invoice, estimate, and contract is verified on both tenant A and tenant B.
- Landlord regression remains intact.
- Production readiness for document branding is now **high**, with the only open gap being the subscription preview path due environment limitations, not a branding-helper defect.

