# BÁO CÁO LANDING PAGE TEMPLATE UPDATE

## 1. Code audit summary

| Thành phần | File/Table | Hiện trạng | Reuse |
| --- | --- | --- | --- |
| Landing module | `modules/kt_landing/` | Có controller + template views + assets template | Có |
| Template loader | `controllers/Kt_landing.php` | Load theo `resolveTemplateCode()` | Reuse + mở rộng |
| Selected template | `get_option('kt_landing_template')` + query `?tpl=` | Có sẵn cơ chế option/query trong controller | Reuse |
| Pricing source | `Kt_saas_model::get_public_plans()` | Lấy plan public/active | Reuse |
| Branding/hero/meta | `tbloptions` qua `get_option('kt_landing_*', 'company_logo', 'companyname'...)` | Đã map động | Reuse |
| Routes public | `application/config/routes.php` | `/`, `/pricing`, `/signup`, `/signup/status` | Giữ nguyên |
| Module routes riêng | `modules/kt_landing/config/routes.php` | Không có file riêng | Không đổi |
| Assets structure | `modules/kt_landing/assets/templates/*` | Tách theo template | Reuse |

## 2. Design file mapping

| Token | Value | Used in |
| --- | --- | --- |
| Primary | `#1ABC9C` | `fastwork_inspired` CTA, active, metrics |
| Secondary | `#FF6900` | `fastwork_inspired` secondary CTA |
| Gold accent | `#FCB900` | `fastwork_inspired` featured badge |
| Heading | `#333333` | All template headings |
| Body | `#777777` | All template body text |
| Font | `Roboto` + fallback sans-serif | `fastwork_inspired`, refined typography |
| Button radius | `200px` | `fastwork_inspired` and upgraded CTA consistency |
| Card padding | `24px` | All upgraded templates |
| Section spacing | `52px–92px` | `fastwork_inspired`; inherited spacing improvements for others |
| Container width | ~`1260px` | `fastwork_inspired` |

## 3. Template mới

### FastWork Inspired

- Files:
  - `modules/kt_landing/views/public/templates/fastwork_inspired/index.php`
  - `modules/kt_landing/assets/templates/fastwork_inspired/style.css`
- Sections:
  1) Header, 2) Hero, 3) Trust/Metrics, 4) Product modules, 5) Workflow, 6) Benefits, 7) Pricing, 8) Add-ons, 9) Testimonials, 10) FAQ, 11) Final CTA, 12) Footer.
- Visual:
  - CRM dashboard mockup, KPI cards, pipeline strip, chart bars, workflow nodes (HTML/CSS nội bộ).
- Dynamic data:
  - Branding/meta/colors/hero/options từ `get_option`.
  - Pricing từ `get_public_plans()`.
  - FAQ/testimonials/features từ option JSON + fallback.

## 4. Template nâng cấp

### Corporate SaaS
- Giữ style gì:
  - B2B corporate navy/blue, nghiêm túc.
- Nâng cấp gì:
  - Hero dashboard mockup rõ hơn, outcomes section, final CTA, pricing card cải thiện.

### Modern Growth
- Giữ style gì:
  - Growth-oriented, gradient nhẹ, card hiện đại.
- Nâng cấp gì:
  - Workflow visual nodes, add-on cards có nội dung, pricing featured badge, final demo CTA.

### Minimal Enterprise
- Giữ style gì:
  - Tối giản premium, typography mạnh, palette trầm.
- Nâng cấp gì:
  - Abstract enterprise visual strip, security panels, use-case cards, compact pricing cards đẹp hơn.

## 5. Template registration

- Danh sách sau cập nhật (controller allow-list):
  1. `fastwork_inspired`
  2. `corporate_saas`
  3. `modern_growth`
  4. `minimal_enterprise`
- Cơ chế chọn:
  - `get_option('kt_landing_template')`
  - hoặc preview `?tpl={code}`.

## 6. Dynamic data mapping

| Data | Source | Template |
| --- | --- | --- |
| Logo/brand/favicon | `get_option(...)` | All 4 |
| Hero/meta/color/cta/footer | `kt_landing_*` options | All 4 |
| Features/FAQ/Testimonials | option JSON + fallback | All 4 |
| Pricing plans | `Kt_saas_model::get_public_plans()` | All 4 |
| Signup CTA | `/signup` | All 4 |

## 7. Responsive test

| Viewport | Result |
| --- | --- |
| 375px | Hero stack, pricing 1 cột, CTA full-width ở template FastWork |
| 768px | Grid co 1-2 cột, visual không tràn |
| 1024px | Bố cục desktop vừa |
| 1440px | Bố cục rộng, spacing ổn định |

## 8. Issues / missing CMS fields

| Issue | Severity | Note |
| --- | --- | --- |
| Chưa có CMS table riêng cho landing sections/media | Medium | Đang lấy `options` + fallback nội bộ |
| Chưa có landlord UI metadata cho template catalog (preview thumbnail/description) | Medium | Hiện chọn qua option/query |
| Blog data source riêng cho landing chưa có | Low | Đang fallback text trong template Minimal |

## 9. Final checklist

- Tạo mới 1 template `fastwork_inspired` riêng.
- 3 template cũ được nâng cấp UI nhưng giữ style tách biệt.
- Không emoji.
- Không external image bắt buộc; visual fallback nội bộ.
- Không hard-code pricing plans.
- Không phá routes/signup/checkout flow.
