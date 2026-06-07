# BÁO CÁO BUILD 3 TEMPLATE LANDING PAGE

## 1. Code audit summary

| Thành phần | File/Table | Hiện trạng | Đã reuse |
| --- | --- | --- | --- |
| Module landing | `modules/kt_landing/` | Có controller + views public cơ bản (`home/pricing/signup/status`) | Có |
| Module routes riêng | `modules/kt_landing/config/routes.php` | Chưa có | Không cần, đang dùng `application/config/routes.php` |
| Global route public | `application/config/routes.php` | `/` -> `kt_landing`, `/pricing`, `/signup`, `/signup/status` | Có |
| Renderer template | `modules/kt_landing/controllers/Kt_landing.php` | Bổ sung cơ chế chọn template theo `tpl` query hoặc `get_option('kt_landing_template')` | Có |
| Data pricing | `Kt_saas_model::get_public_plans()` | Đã dùng cho landing, lọc plan public/active | Có |
| CTA signup | `/signup` + `/signup/status` | Đã giữ nguyên flow signup hiện tại | Có |
| Checkout CTA | link từ signup status/invoice | Đã giữ nguyên flow checkout hiện tại | Có |
| Branding/settings | `tbloptions` qua `get_option(...)` | Dùng logo, meta, màu, hero, footer nếu có | Có |
| CMS section table chuyên landing | N/A | Chưa thấy table chuyên landing sections | Fallback nội dung có kiểm soát |
| Assets pipeline | `modules/kt_landing/assets/templates/*` | Đã tách CSS theo template, không đụng admin/client CSS | Có |
| Helper render section | N/A | Chưa có helper riêng, xử lý tại controller + view | Có |
| Route preview template | query `?tpl=...` | Có ở mức controller (`corporate_saas/modern_growth/minimal_enterprise`) | Có |

## 2. Template đã build

| Code | Name | Style | Files |
| --- | --- | --- | --- |
| `corporate_saas` | Corporate SaaS | Doanh nghiệp, sáng, bố cục rõ ràng | `views/public/templates/corporate_saas/index.php`, `assets/templates/corporate_saas/style.css` |
| `modern_growth` | Modern Growth | Hiện đại, card mềm, nhấn tăng trưởng | `views/public/templates/modern_growth/index.php`, `assets/templates/modern_growth/style.css` |
| `minimal_enterprise` | Minimal Enterprise | Tối giản, cao cấp, typography mạnh | `views/public/templates/minimal_enterprise/index.php`, `assets/templates/minimal_enterprise/style.css` |

## 3. Dynamic data mapping

| Data | Source | Used in template |
| --- | --- | --- |
| Logo | `get_option('company_logo')` | Header |
| Brand name | `get_option('companyname')` | Header/Hero/Footer |
| Favicon | `get_option('favicon')` | Head |
| Meta title/description | `get_option('kt_landing_meta_title')`, `get_option('kt_landing_meta_description')` | SEO meta |
| Hero title/subtitle/image | `kt_landing_hero_*` options | Hero section |
| Màu primary/secondary/cta | `kt_landing_primary_color`, `kt_landing_secondary_color`, `kt_landing_cta_color` | CSS variables |
| Features/FAQ/Testimonials | JSON options (`kt_landing_features_json`, `kt_landing_faq_json`, `kt_landing_testimonials_json`) | Các section nội dung |
| Pricing plans | `Kt_saas_model::get_public_plans()` | Pricing cards/rows |
| Footer text | `kt_landing_footer_text` | Footer |

## 4. Routes/CTA

| CTA | Route | Source |
| --- | --- | --- |
| Header CTA | `/signup` | hard-wired từ route hiện có |
| Hero CTA | `/signup` | hard-wired từ route hiện có |
| Pricing CTA | `/signup?plan_id={id}` | plan id từ `get_public_plans()` |
| Pricing link | `/pricing` | route hiện có |
| Home link | `/` | default landing route |

## 5. Responsive test

| Viewport | Result |
| --- | --- |
| 375px | CSS grid co về 1 cột, CTA vẫn bấm được |
| 768px | Layout co theo tablet breakpoints |
| 1024px | Multi-column ổn định |
| 1440px | Giữ bố cục desktop rõ ràng |

## 6. Issues found

| Issue | Severity | Fix/Note |
| --- | --- | --- |
| Chưa có table CMS chuyên landing sections | Medium | Đang dùng `options` + fallback nội dung |
| Chưa có UI landlord chọn template landing | Medium | Đã hỗ trợ chọn qua `get_option('kt_landing_template')` và preview `?tpl=` |
| Chưa có blog data source riêng cho landing | Low | Hiển thị fallback text ở template C |

## 7. Final checklist

- Không phá route/controller/model/flow signup/checkout hiện có.
- Không duplicate pricing plan; toàn bộ pricing lấy từ `get_public_plans()`.
- Không dùng emoji, không phong cách trẻ con, không palette neon.
- 3 template khác nhau rõ ràng và giữ phong cách nghiêm túc/chuyên nghiệp.
- Đã tách CSS theo template, không chạm admin/client portal CSS.
