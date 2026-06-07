# PRODUCT LANGUAGE NORMALIZATION REPORT

## Technical Terms Found

| Location | Current Text | Audience | Replacement |
|---|---|---|---|
| `modules/kt_saas/language/*/kt_saas_lang.php` | `KT SaaS`, `Tenant`, `Subscriptions`, `Provisioning`, `Modules`, `quota` labels | landlord admin + tenant portal | `Khach Tot SaaS`, `Business workspace` / `Khong gian lam viec`, `Service plans`, `Initial setup`, `Applications`, `usage allowance` |
| `modules/kt_sepay/language/*/kt_sepay_lang.php` | `KT SePay`, `Tenant webhook URL`, `Module activation` | tenant portal + landlord admin | `Payments & Reconciliation`, `Workspace webhook URL`, `Service activation` |
| `modules/kt_matbao_invoice/language/*/kt_matbao_invoice_lang.php` | `KT MatBao Invoice`, `Tenant Add-ons`, `Provisioning Queue` | tenant portal + landlord admin | `E-Invoicing`, `Service add-ons`, `Setup queue` |
| `modules/kt_inventory/language/*/kt_inventory_lang.php` | internal inventory naming | landlord admin + tenant portal | `Warehouse Management` / `Quan ly kho` |
| `modules/kt_landing/views/public/*` | `KT SaaS`, `workspace`, `tenant`, `provisioning`, `module`, `quota` | public visitors | business wording only |
| `modules/kt_saas/views/public/checkout.php` | `SaaS`, `tenant` | buyer | `service invoice`, `workspace` |
| `modules/kt_saas/views/tenant/*.php` | `tenant workspace`, `tenant admins`, `quota`, `module` | tenant user/admin | `workspace`, `workspace admins`, `usage limit`, `applications` |
| `modules/kt_matbao_invoice/views/tenant/*.php` | `Tenant`, `quota`, `MatBao eInvoice`, `CA/HSM` raw wording | tenant admin | `Khong gian`, `gioi han su dung`, `Hoa don dien tu`, `Chu ky so / HSM` |

## Customer-facing Replacements

| Developer Language | Customer-facing Language |
|---|---|
| `KT SAAS` / `KT SaaS` | `Khach Tot SaaS` |
| `tenant` | `Business workspace` / `Khong gian lam viec` / `Doanh nghiep` |
| `workspace` | `Khong gian lam viec` |
| `subscription` | `Service plan` / `Goi dich vu` |
| `provisioning` | `Initial setup` / `Khoi tao he thong` |
| `module` | `Application` / `Ung dung` |
| `quota` | `Usage allowance` / `Gioi han su dung` |
| `KT Inventory` | `Warehouse Management` / `Quan ly kho` |
| `KT SePay` | `Payments & Reconciliation` / `Thanh toan & Doi soat` |
| `KT MatBao Invoice` | `E-Invoicing` / `Hoa don dien tu` |
| `KT Landing` | `Business Website` / `Website doanh nghiep` |

## Landlord Changes

- Updated landlord/central language labels in:
  - `modules/kt_saas/language/english/kt_saas_lang.php`
  - `modules/kt_saas/language/vietnamese/kt_saas_lang.php`
  - `modules/kt_sepay/language/english/kt_sepay_lang.php`
  - `modules/kt_sepay/language/vietnamese/kt_sepay_lang.php`
  - `modules/kt_matbao_invoice/language/english/kt_matbao_invoice_lang.php`
  - `modules/kt_matbao_invoice/language/vietnamese/kt_matbao_invoice_lang.php`
  - `modules/kt_inventory/language/english/kt_inventory_lang.php`
  - `modules/kt_inventory/language/vietnamese/kt_inventory_lang.php`
- Normalized landlord-visible product names away from module-code naming.
- Normalized core nouns used in admin menus and shared labels: tenant, subscription, provisioning, modules, quota.

## Tenant Changes

- Tenant portal labels now resolve through normalized language keys for:
  - service plans
  - applications
  - workspace code
  - setup jobs
  - invoice/project/warehouse/API/automation allowances
- Direct tenant-facing hard-coded copy was normalized in:
  - `modules/kt_saas/views/tenant/activity_logs.php`
  - `modules/kt_saas/views/tenant/departments.php`
  - `modules/kt_sepay/views/tenant/reconciliation.php`
  - `modules/kt_sepay/views/tenant/payment_requests.php`
  - `modules/kt_matbao_invoice/views/tenant/settings.php`
  - `modules/kt_matbao_invoice/views/tenant/usage.php`
  - `modules/kt_matbao_invoice/views/tenant/addons.php`
  - `modules/kt_matbao_invoice/views/tenant/overview.php`
  - `modules/kt_matbao_invoice/views/tenant/invoices.php`
  - `modules/kt_matbao_invoice/views/invoice/einvoice_panel.php`

## Landing Changes

- Public product naming normalized in:
  - `modules/kt_landing/views/public/home.php`
  - `modules/kt_landing/views/public/pricing.php`
  - `modules/kt_landing/views/public/signup.php`
  - `modules/kt_landing/views/public/signup_status.php`
  - `modules/kt_landing/views/public/templates/modern_growth/index.php`
  - `modules/kt_landing/views/public/templates/minimal_enterprise/index.php`
  - `modules/kt_landing/views/public/templates/fastwork_inspired/index.php`
- Target wording:
  - no raw module-code names in hero/pricing/FAQ/marketplace
  - no `tenant`, `workspace`, `provisioning`, `module`, `quota` in public marketing copy
  - buyer-facing wording for signup flow and purchase confirmation

## Email Changes

- No mail engine, route, or service names were changed.
- This pass normalized language files and customer-facing UI surfaces only.
- Existing DB email template rows were not rewritten in this pass.
- Result:
  - email UI labels tied to language files are improved
  - existing seeded/runtime DB template body copy may still contain technical wording if it was already stored before this pass

## PDF Changes

- No document engine refactor was done.
- This pass did not alter invoice/estimate/contract rendering logic.
- PDF-facing module labels tied to customer-facing buttons and related tenant screens were normalized where they were hard-coded.

## Before vs After

| Before | After |
|---|---|
| `KT SaaS` | `Khach Tot SaaS` |
| `Tenant` | `Business workspace` / `Khong gian lam viec` |
| `Subscription` | `Service plan` / `Goi dich vu` |
| `Provisioning Queue` | `Setup queue` / `Hang doi khoi tao` |
| `KT SePay` | `Payments & Reconciliation` / `Thanh toan & Doi soat` |
| `KT MatBao Invoice` | `E-Invoicing` / `Hoa don dien tu` |
| `Modules` | `Applications` / `Ung dung` |
| `Quota` | `Usage allowance` / `Gioi han su dung` |

## Remaining Technical Leakage

1. Some older public view files still contain mojibake-encoded static copy, which made targeted string replacement incomplete in this pass.
2. Existing DB email template bodies were not bulk-migrated, so preexisting template content can still contain old wording.
3. A few tenant-facing screens still expose technical copy in long-form helper text, especially where older mixed-encoding strings remain in file bodies.
4. Hidden/internal variable names such as `tenant_host`, `kt_saas_*`, `module_json` remain in code as required and were not changed.

## Ready For Soft Launch?

- **Yes, with caveat.**
- Soft-launch ready for:
  - normalized menu/product naming
  - reduced module-code leakage across major tenant/admin labels
  - improved customer-facing wording on the main payment and tenant-addon surfaces
- Not fully clean yet for:
  - legacy public copy in mixed-encoding files
  - preexisting DB email template bodies

### Verification

- `php -l modules/kt_saas/language/english/kt_saas_lang.php` pass
- `php -l modules/kt_sepay/language/english/kt_sepay_lang.php` pass
- `php -l modules/kt_matbao_invoice/language/english/kt_matbao_invoice_lang.php` pass
- `php -l modules/kt_inventory/language/english/kt_inventory_lang.php` pass
