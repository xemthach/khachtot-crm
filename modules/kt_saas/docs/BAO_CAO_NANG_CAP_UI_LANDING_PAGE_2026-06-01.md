# BÁO CÁO NÂNG CẤP UI LANDING PAGE

## 1. Files đã sửa

| Template | File | Nội dung sửa |
| --- | --- | --- |
| Corporate SaaS | `modules/kt_landing/views/public/templates/corporate_saas/index.php` | Nâng hero có dashboard mockup, thêm outcomes, final CTA, cải thiện pricing/faq |
| Corporate SaaS | `modules/kt_landing/assets/templates/corporate_saas/style.css` | Theme corporate B2B, card/shadow nhẹ, mockup chart/task/pipeline, responsive 1-2-3 cột |
| Modern Growth | `modules/kt_landing/views/public/templates/modern_growth/index.php` | Hero split + flow nodes, add-ons card chuyên nghiệp, pricing badge phổ biến, final CTA |
| Modern Growth | `modules/kt_landing/assets/templates/modern_growth/style.css` | Gradient nhẹ, feature visual marks, addon/pricing emphasis, responsive |
| Minimal Enterprise | `modules/kt_landing/views/public/templates/minimal_enterprise/index.php` | Hero typography + enterprise visual, use cases card, pricing compact card, final CTA |
| Minimal Enterprise | `modules/kt_landing/assets/templates/minimal_enterprise/style.css` | Tối giản cao cấp, abstract grid + security panels, compact cards, responsive |
| Shared render | `modules/kt_landing/controllers/Kt_landing.php` | Giữ flow cũ, thêm data mapping động + template selector `kt_landing_template`/`?tpl=` |

## 2. Visual đã thêm

| Template | Visual | Cách render |
| --- | --- | --- |
| Corporate SaaS | Dashboard mockup (pipeline/revenue/chart/tasks) | HTML/CSS nội bộ |
| Corporate SaaS | Business outcomes section | Card + spacing corporate |
| Modern Growth | Workflow nodes + process visual | HTML/CSS chip nodes |
| Modern Growth | Feature visual cues + addon cards | CSS markers + card layout |
| Minimal Enterprise | Abstract grid + security panels | HTML/CSS nội bộ |
| Minimal Enterprise | Use-case panels + compact pricing cards | Card grid tối giản |

## 3. Dynamic data vẫn giữ

| Data | Source | Template |
| --- | --- | --- |
| Pricing plans (public/active) | `Kt_saas_model::get_public_plans()` | Cả 3 template |
| Brand name/logo/favicon | `get_option('companyname/company_logo/favicon')` | Cả 3 template |
| Hero/meta/colors/footer | `get_option('kt_landing_*')` | Cả 3 template |
| CTA/signup route | `/signup`, `/pricing`, `/signup/status` | Cả 3 template |

## 4. Responsive test

| Viewport | Result |
| --- | --- |
| 375px | Hero stack 1 cột, pricing 1 cột, CTA bấm tốt |
| 768px | Grid co giãn 1-2 cột ổn định |
| 1024px | Layout desktop trung bình đúng bố cục |
| 1440px | Bố cục đầy đủ, spacing và hierarchy rõ |

## 5. Checklist

- Không emoji.
- Không external image bắt buộc (visual fallback nội bộ HTML/CSS khi thiếu CMS image).
- Không hard-code pricing plans (lấy từ KT SAAS model).
- Không phá route/signup/checkout flow hiện có.
- CSS scoped theo template classes:
  - `.kt-template-corporate`
  - `.kt-template-growth`
  - `.kt-template-enterprise`
- UI đã nâng cấp rõ rệt so với wireframe text/card cơ bản.
