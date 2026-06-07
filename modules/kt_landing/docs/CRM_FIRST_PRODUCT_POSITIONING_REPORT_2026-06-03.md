# CRM-FIRST PRODUCT POSITIONING REPORT

Scope:
- Customer-facing language normalization only.
- No database, route, API, billing, payment, provisioning, or entitlement changes.
- Goal: customers should read this as `CRM Khach Tot`, not as a technical SaaS platform.

## Technical Terms Found

| Location | Current Text | Audience | Recommended Replacement |
|---|---|---|---|
| Landing hero / comparison / marketplace | `KT SaaS`, `SaaS`, `workspace`, `tenant isolation`, `runtime` | Public visitors | `CRM Khach Tot`, `doanh nghiep`, `du lieu tach rieng`, `he thong thong nhat` |
| Signup | `SaaS Purchase Flow`, `Workspace`, `Subscription + setup fee` | Buyers | `Dang ky CRM`, `Doanh nghiep`, `Goi dich vu + phi trien khai` |
| Checkout / billing | `SaaS invoice`, `tenant`, `subscription` | Buyers / tenant admins | `hoa don dich vu`, `doanh nghiep`, `goi dich vu` |
| Tenant settings | `workspace branding`, `workspace localization`, `tenant email provider` | Tenant admins | `thuong hieu doanh nghiep`, `ngon ngu & dinh dang`, `kenh gui email rieng` |
| SePay tenant pages | `KT SePay`, technical plan warnings | Tenant admins | `Thanh toan & Doi soat`, `doi soat thu cong`, `yeu cau thanh toan thu cong` |
| MatBao tenant pages | `KT MatBao Invoice`, `tenant add-ons`, test connection labels | Tenant admins | `Hoa don dien tu`, `dich vu bo sung`, `kiem tra ket noi hoa don dien tu` |
| Public templates | module code names and platform wording | Public visitors | business product wording |

## CRM-first Replacements

Developer vs customer-facing mapping used in this pass:

| Internal / Technical | Customer-facing |
|---|---|
| `KT SaaS` | `CRM Khach Tot` |
| `tenant` | `doanh nghiep` / `tai khoan doanh nghiep` |
| `workspace` | `doanh nghiep` / `dia chi truy cap CRM` |
| `subscription` | `goi dich vu` / `goi CRM` |
| `provisioning` | `khoi tao he thong` |
| `quota` | `gioi han su dung` |
| `module` | `ung dung` / `tinh nang` |
| `KT Inventory` | `Quan ly kho` |
| `KT SePay` | `Thanh toan & Doi soat` |
| `KT MatBao Invoice` | `Hoa don dien tu` |
| `KT Landing` | `Website doanh nghiep` |

## Landlord Changes

Changed surfaces:
- `modules/kt_saas/language/english/kt_saas_lang.php`
- `modules/kt_saas/language/vietnamese/kt_saas_lang.php`
- `modules/kt_sepay/language/english/kt_sepay_lang.php`
- `modules/kt_sepay/language/vietnamese/kt_sepay_lang.php`
- `modules/kt_matbao_invoice/language/english/kt_matbao_invoice_lang.php`
- `modules/kt_matbao_invoice/language/vietnamese/kt_matbao_invoice_lang.php`
- `modules/kt_inventory/language/english/kt_inventory_lang.php`
- `modules/kt_inventory/language/vietnamese/kt_inventory_lang.php`

Key landlord/admin normalization:
- `KT SaaS` labels moved toward `Khach Tot CRM` / `CRM plan` / `Current service plan`.
- module labels moved away from raw code names where language files are used.
- billing-cycle and plan wording shifted from subscription-first to service-plan-first.

## Tenant Changes

Changed tenant-facing views:
- `modules/kt_saas/views/tenant/settings.php`
- `modules/kt_saas/views/tenant/subscription.php`
- `modules/kt_saas/views/tenant/billing.php`
- `modules/kt_saas/views/tenant/activity_logs.php`
- `modules/kt_saas/views/tenant/departments.php`
- `modules/kt_sepay/views/tenant/reconciliation.php`
- `modules/kt_sepay/views/tenant/payment_requests.php`
- `modules/kt_matbao_invoice/views/tenant/settings.php`
- `modules/kt_matbao_invoice/views/tenant/overview.php`
- `modules/kt_matbao_invoice/views/tenant/invoices.php`
- `modules/kt_matbao_invoice/views/invoice/einvoice_panel.php`
- `modules/kt_matbao_invoice/views/tenant/addons.php`

What changed:
- tenant settings tabs now read as business settings, not workspace internals.
- company profile, localization, branding, invoice defaults, and email identity use business wording.
- SePay and MatBao screens no longer expose module-code naming as primary customer language.
- department / governance / billing surfaces were shifted toward `doanh nghiep`, `goi CRM`, `hoa don dich vu`, `lich su thanh toan`.

## Landing Changes

Changed public views:
- `modules/kt_landing/views/public/home.php`
- `modules/kt_landing/views/public/pricing.php`
- `modules/kt_landing/views/public/signup.php`
- `modules/kt_landing/views/public/signup_status.php`
- `modules/kt_landing/views/public/templates/fastwork_inspired/index.php`
- `modules/kt_landing/views/public/templates/corporate_saas/index.php`
- `modules/kt_landing/views/public/templates/minimal_enterprise/index.php`
- `modules/kt_landing/views/public/templates/modern_growth/index.php`

What changed:
- brand fallback is now framed as `CRM Khach Tot`.
- hero and value blocks were moved from platform wording toward CRM/business outcomes.
- marketplace cards use customer-facing module names.
- comparison and trust blocks reduce `tenant/runtime/platform` emphasis.

## Signup Changes

Changed file:
- `modules/kt_landing/views/public/signup.php`

What changed:
- `SaaS Purchase Flow` -> `Dang ky CRM`
- `Workspace` step -> `Doanh nghiep`
- `Subscription + setup fee` -> `Goi dich vu + phi trien khai`
- `Workspace URL` -> `Dia chi truy cap CRM`
- review panel now describes `tao hoa don`, `trang thanh toan`, `khoi tao he thong`

Result:
- the flow reads more like buying a CRM service for a business, less like creating a technical workspace.

## Billing Changes

Changed files:
- `modules/kt_saas/views/public/checkout.php`
- `modules/kt_saas/views/tenant/billing.php`
- `modules/kt_saas/views/tenant/subscription.php`

What changed:
- checkout title shifted away from `SaaS invoice` wording.
- tenant billing labels were normalized toward `hoa don dich vu`, `lich su thanh toan`, `goi CRM`.
- plan and billing-cycle wording relies more on CRM/service-plan language through language files.

## Email Changes

Changed source surfaces:
- language files in `kt_saas`, `kt_sepay`, `kt_matbao_invoice`, `kt_inventory`
- tenant email settings wording in `modules/kt_saas/views/tenant/settings.php`

What changed:
- sender/provider configuration language is now business-facing.
- visible email settings no longer center `tenant email provider` as raw technical wording.

What this pass did not fully do:
- it did not bulk-rewrite all stored DB email template bodies.
- existing DB templates may still contain older wording if they were seeded earlier and then edited in database.

## PDF Changes

Audited/affected surfaces:
- `modules/kt_saas/views/public/checkout.php`
- shared document branding helpers remained unchanged
- subscription / invoice / estimate / contract templates were not structurally modified in this pass

Result:
- no route or document logic changed.
- customer-facing payment page wording was normalized.
- shared PDF branding logic remains as verified in earlier document-branding passes.

## Before vs After

| Before | After |
|---|---|
| `KT SaaS` | `CRM Khach Tot` |
| `Workspace` | `Doanh nghiep` / `Dia chi truy cap CRM` |
| `Subscription` | `Goi dich vu` / `Goi CRM` |
| `Provisioning` | `Khoi tao he thong` |
| `KT SePay` | `Thanh toan & Doi soat` |
| `KT MatBao Invoice` | `Hoa don dien tu` |
| `SaaS Purchase Flow` | `Dang ky CRM` |
| `tenant email provider` | `kenh gui email rieng` |

## Remaining Technical Leakage

Still remaining after this pass:
- some public and tenant files still contain old mojibake/mixed-encoding copy, which makes clean bulk replacement risky in one sweep.
- some tenant settings/governance areas still have scattered English labels beyond the core surfaces already normalized.
- DB-backed email template bodies were not mass-normalized here.
- Perfex core subscription/contact views still use Perfex `subscription` terminology unless overridden separately.

Most important residual surfaces to clean next if needed:
- long-form content in `modules/kt_saas/views/tenant/settings.php`
- remaining public-template copy with encoding damage
- DB email template body text
- selected core Perfex client subscription labels

## Ready For Soft Launch?

- **Yes, with conditions.**

Current assessment:
- customer-facing product impression is materially closer to `CRM Khach Tot` than to a developer-oriented SaaS platform.
- landing, signup, billing, tenant settings, SePay, and eInvoice screens now present substantially better business language.
- it is not a perfect 100% terminology cleanup yet.

Soft-launch recommendation:
- **ready for soft launch**
- **not yet final-copy complete for broad paid traffic scale**

Reason:
- the product can now be positioned as CRM-first without obvious technical leakage on the primary conversion surfaces.
- the remaining leaks are mostly cleanup debt, not product-definition blockers.
