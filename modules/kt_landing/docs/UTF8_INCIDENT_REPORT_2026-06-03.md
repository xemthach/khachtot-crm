# UTF8 INCIDENT REPORT

## Root Cause

Primary root cause:
- customer-facing Vietnamese text in the active landing template was stored in source files with mixed corruption patterns:
  - double-encoded UTF-8 mojibake
  - partial fallback to literal `?` characters where diacritics were already lost

Confirmed incident characteristics:
- this was **not** a font issue
- this was **not** a typography issue
- this was **not** a database charset issue on the audited landing/email tables
- this was **not** a browser-only render issue

Evidence:
- live HTML from `https://khachtot.test/` decoded cleanly as UTF-8 after the fix
- before the fix, the active public template contained strings like:
  - `CÃ³ ...`
  - `Triá»ƒn khai ...`
  - `Kh?ch T?t`
  - `Thanh to?n`

Secondary root cause:
- parts of custom Vietnamese language files had also been saved in a mojibake state, which created fallback risk on customer-facing surfaces outside the main landing template.

## Affected Files

Files directly fixed in this pass:
- [modules/kt_landing/views/public/templates/fastwork_inspired/index.php](/d:/laragon/www/khachtot/modules/kt_landing/views/public/templates/fastwork_inspired/index.php)
- [modules/kt_landing/controllers/Kt_landing_public.php](/d:/laragon/www/khachtot/modules/kt_landing/controllers/Kt_landing_public.php)
- [modules/kt_landing/views/public/templates/corporate_saas/index.php](/d:/laragon/www/khachtot/modules/kt_landing/views/public/templates/corporate_saas/index.php)
- [modules/kt_landing/views/public/templates/minimal_enterprise/index.php](/d:/laragon/www/khachtot/modules/kt_landing/views/public/templates/minimal_enterprise/index.php)
- [modules/kt_saas/language/vietnamese/kt_saas_lang.php](/d:/laragon/www/khachtot/modules/kt_saas/language/vietnamese/kt_saas_lang.php)
- [modules/kt_sepay/language/vietnamese/kt_sepay_lang.php](/d:/laragon/www/khachtot/modules/kt_sepay/language/vietnamese/kt_sepay_lang.php)

Key affected public areas:
- hero
- trust indicators
- FAQ
- comparison section
- pricing explainer
- footer/copyright
- signup title and fallback public metadata

## Affected Database Records

Audited tables:
- `tblkt_landing_settings`
- `tblkt_landing_sections`
- `tblkt_landing_section_items`
- `tblkt_landing_plan_overrides`
- `tblemailtemplates`

Result:
- no mojibake or `latin1`-style corruption was found in the audited landing CMS tables
- no mojibake was found in audited `tblemailtemplates.subject` and `tblemailtemplates.message`
- application DB remains configured for `utf8mb4`

Conclusion:
- the P0 landing incident was primarily **source-file corruption**, not corrupted CMS/database content

## Fix Applied

What we changed:
- restored corrupted source literals in the active landing template
- repaired customer-facing fallback/default strings in the public landing controller
- restored UTF-8 title fallbacks in additional public templates
- normalized corrupted custom Vietnamese language strings that could leak into customer-facing surfaces

Technical method:
- used deterministic UTF-8 restoration for double-encoded text
- used explicit Unicode-safe rewrites for strings that had already degraded into `?`
- revalidated PHP syntax after repair

Important constraint honored:
- no wording rewrite
- no marketing rewrite
- no logic change
- no billing/payment/provisioning change
- encoding repair only

## Verification Screenshots

Captured screenshots:
- [utf8-home-2026-06-03.png](/d:/laragon/www/khachtot/modules/kt_landing/docs/screenshots/utf8-home-2026-06-03.png)
- [utf8-signup-2026-06-03.png](/d:/laragon/www/khachtot/modules/kt_landing/docs/screenshots/utf8-signup-2026-06-03.png)

Notes:
- `home` screenshot confirms visible Vietnamese text is restored in the live landing hero/product canvas
- `signup` screenshot confirms the live signup flow is restored in UTF-8
- section-anchor screenshots for deeper landing sections were unreliable in headless Chrome, so those sections were verified directly from the live UTF-8 HTML response instead

Live HTML verification confirmed these restored phrases on `https://khachtot.test/`:
- `Nền tảng CRM và vận hành doanh nghiệp cho SME`
- `Vì sao chọn CRM Khách Tốt`
- `CRM Khách Tốt`
- `Chi phí sử dụng theo chu kỳ gói, bao gồm quyền truy cập, ứng dụng đi kèm, giới hạn sử dụng và vận hành liên tục.`
- footer copyright text with proper Vietnamese accents

Live HTML verification confirmed these restored phrases on `https://khachtot.test/signup`:
- `Đăng ký CRM Khách Tốt`

## Remaining Encoding Risks

Low residual risks:
- some legacy public files still contain BOM at file start; this did not break rendering in this pass, but it is worth cleaning in a separate non-P0 sweep
- custom Vietnamese language surfaces outside the verified public landing/signup path may still deserve a broader normalization pass
- headless section-anchor screenshots were not reliable evidence for every lower landing section, though the live response text for those sections is now UTF-8 clean

No remaining high-risk finding was observed on:
- landlord landing `/`
- landlord pricing `/pricing`
- landlord signup `/signup`

## UTF8 Restored?

- **Yes** for the active public landing path.
- **Yes** for signup.
- **Yes** for the audited landing CMS and email-template database content.
- **Yes** for the repaired public template/controller fallback strings.

Operational verification summary:
- live UTF-8 decode succeeded on `/`, `/pricing`, `/signup`
- no mojibake patterns remained in the verified live response text
- repaired files passed `php -l`
