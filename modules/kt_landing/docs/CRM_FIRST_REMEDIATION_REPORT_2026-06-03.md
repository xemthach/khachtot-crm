# CRM-FIRST REMEDIATION REPORT

Scope:
- Remediation only.
- No re-audit from scratch.
- No logic change to billing, checkout, payment, provisioning, routes, APIs, or module internals.
- Focused on remaining CRM-first wording, UTF-8 cleanup, and plan-description mapping on customer-facing surfaces.

## UTF8 Issues Fixed

Files fixed for visible UTF-8 output:
- `modules/kt_landing/views/public/signup.php`
- `modules/kt_landing/controllers/Kt_landing_public.php`
- `modules/kt_landing/views/public/home.php`
- `modules/kt_landing/views/public/pricing.php`
- `modules/kt_landing/views/public/templates/fastwork_inspired/index.php`
- `modules/kt_landing/views/public/templates/modern_growth/index.php`
- `modules/kt_saas/views/public/checkout.php`
- `modules/kt_saas/views/tenant/subscription.php`
- `modules/kt_saas/views/tenant/billing.php`
- `modules/kt_saas/views/tenant/activity_logs.php`
- `modules/kt_saas/views/tenant/settings.php`
- `modules/kt_saas/views/tenant/governance.php`
- `modules/kt_saas/views/dashboard/tenants.php`
- `modules/kt_saas/language/english/kt_saas_lang.php`
- `modules/kt_saas/language/vietnamese/kt_saas_lang.php`

Direct UTF-8 remediations included:
- `Khach Tot` -> `Khách Tốt`
- `Thanh toan` -> `Thanh toán`
- `Hoa don` -> `Hóa đơn`
- `Goi dich vu` -> `Gói dịch vụ`
- visible mojibake cleanup on signup flow headings, trust copy, summary panel, review step, home page, pricing page, and tenant billing/subscription surfaces

## Plan Description Mapping Fixed

The signup purchase flow now uses corrected best-for mapping for the four target plans:

| Plan | Best For |
|---|---|
| `Trial` | `Trải nghiệm nền tảng trước khi triển khai chính thức.` |
| `SME Mini` | `Doanh nghiệp nhỏ bắt đầu số hóa CRM, khách hàng và quy trình bán hàng.` |
| `SME` | `Doanh nghiệp đang tăng trưởng cần quản lý CRM, kho, hóa đơn và vận hành trên một nền tảng thống nhất.` |
| `SME Plus` | `Doanh nghiệp nhiều phòng ban, nhiều người dùng và quy trình vận hành phức tạp.` |

Applied in:
- `modules/kt_landing/views/public/signup.php`
- pricing guidance copy in `modules/kt_landing/views/public/templates/fastwork_inspired/index.php`

Result:
- trial / starter / basic / standard style fallback descriptions no longer collapse into the same generic business copy.

## Technical Leakage Removed

Business wording replacements applied on customer-facing or operator-facing UI:

| Current / Technical | Replacement |
|---|---|
| `Current Plan` | `Current CRM package` / `Gói CRM hiện tại` |
| `Request plan change` | `Change CRM package` / `Đổi gói CRM` |
| `Included Applications` | `Included business apps` / `Ứng dụng đi kèm` |
| `Invoice allowance` | `Included invoices` / `Số lượng hóa đơn` |
| `Warehouse allowance` | `Included warehouses` / `Số kho` |
| `API allowance` | `Daily integrations` / `Lượt tích hợp mỗi ngày` |
| `Automation allowance` | `Workflow automations` / `Quy trình tự động` |
| `workspace governance` on visible tenant settings copy | `quản trị nhân sự và phân quyền` |
| `tenant` on visible business-facing copy | `doanh nghiệp` |
| `workspace` on visible business-facing copy | `doanh nghiệp` / `địa chỉ truy cập CRM` depending on context |

Surfaces remediated:
- signup UX
- tenant subscription screen
- landlord tenant list actions
- tenant settings explanatory copy
- tenant activity logs
- tenant billing wording

## Landlord UI Changes

Changed on landlord/admin-facing surfaces where wording was still too technical:
- `modules/kt_saas/views/dashboard/index.php`
  - signup/provisioning dashboard labels already normalized in the prior pass and preserved here
- `modules/kt_saas/views/dashboard/tenants.php`
  - `Queue job` -> `Xếp hàng khởi tạo`
  - `Workspace Audit (JSON)` -> `Kiểm tra tách biệt dữ liệu (JSON)`
  - `Workspace Audit (HTML)` -> `Kiểm tra tách biệt dữ liệu (HTML)`
  - `Đăng nhập tenant` -> `Đăng nhập doanh nghiệp`
  - `Onboarding` -> `Thiết lập ban đầu`

Also corrected landlord/public signup metadata:
- `Đăng ký KT SaaS` -> `Đăng ký CRM Khách Tốt`
- `Tạo workspace SaaS cho doanh nghiệp.` -> `Đăng ký CRM cho doanh nghiệp và bắt đầu quy trình triển khai chính thức.`

## Tenant UI Changes

Changed on tenant-facing surfaces:
- `modules/kt_saas/views/tenant/subscription.php`
  - `Gói CRM`
  - `Giới hạn sử dụng`
  - `Các gói CRM sẵn có`
  - usage rows now read as business wording instead of technical quota wording
- `modules/kt_saas/views/tenant/billing.php`
  - `Hóa đơn dịch vụ`
  - `Lịch sử thanh toán`
  - webhook helper note normalized
- `modules/kt_saas/views/tenant/settings.php`
  - finance basics copy rewritten in business language
  - notification preferences copy rewritten in business language
  - access/governance copy rewritten to avoid `tenant/workspace/governance` leakage on visible text
- `modules/kt_saas/views/tenant/activity_logs.php`
  - intro, filters, and labels localized to business wording
- `modules/kt_saas/views/tenant/governance.php`
  - department help text rewritten to business language

## Remaining Technical Terms

Residual items still present after this remediation:

1. `modules/kt_landing/views/public/templates/minimal_enterprise/index.php`
   - title fallback still uses `CRM Khach Tot`

2. `modules/kt_landing/views/public/templates/corporate_saas/index.php`
   - title fallback still uses `CRM Khach Tot`

3. `modules/kt_landing/controllers/Kt_landing.php`
   - internal marketplace catalog metadata still contains `KT MatBao Invoice` and `Hoa don dien tu...`
   - this is controller seed/config metadata, not primary public runtime copy, but it should still be cleaned in a follow-up pass.

4. `modules/kt_matbao_invoice/views/invoice/einvoice_panel.php`
   - visible button text still uses ASCII `Hoa don dien tu`

5. Core Perfex subscription HTML and subscription mail classes
   - `application/views/themes/perfex/views/subscriptionhtml.php`
   - `application/libraries/mails/Subscription_*`
   - these are still core subscription surfaces and need a separate pass if the requirement is absolute elimination of the word `subscription` from every customer-facing output.

6. `modules/kt_sepay/language/english/kt_sepay_lang.php`
   - `Subscription reference prefix` remains in admin/developer-oriented language, not the main customer-facing funnel

## Soft Launch Ready?

- **Yes, for soft launch.**

Reasoning:
- signup flow is now readable as a CRM purchase flow, not a technical SaaS wizard
- best-for copy is differentiated correctly between Trial / SME Mini / SME / SME Plus
- the most visible UTF-8 issues in landing, signup, billing, and tenant UI were fixed
- landlord and tenant surfaces reduced technical leakage substantially

Constraints still worth noting:
- cleanup is not yet absolute across every legacy landing template and every core subscription surface
- email template bodies in DB were not bulk-normalized in this pass
- a final polish pass is still warranted if the bar is “no technical wording anywhere”

Verification:
- `php -l` passed on all edited PHP files in this remediation pass
