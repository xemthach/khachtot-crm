# BAO CAO AUDIT LANDING CMS

Ngay audit: 2026-06-01  
Pham vi: `modules/kt_landing/*`, `application/config/routes.php` (routes lien quan KT Landing)

## Current Architecture

### Module structure
- Co bootstrap module: `modules/kt_landing/kt_landing.php`
- Co public controller: `modules/kt_landing/controllers/Kt_landing.php`
- Co admin controller: `modules/kt_landing/controllers/Kt_landing_admin.php`
- Co 1 model du lieu CMS: `modules/kt_landing/models/Kt_landing_model.php`
- Co install schema: `modules/kt_landing/install.php`
- Co views admin/public va 4 template public.

### Routing
- Public:
  - `/` -> `kt_landing`
  - `/pricing` -> `kt_landing/pricing`
  - `/signup` -> `kt_landing/signup`
  - `/signup/status` -> `kt_landing/signup_status`
- Admin:
  - `/admin/kt_landing/*` -> `kt_landing/kt_landing_admin/*`

### Database (hien co theo install)
- `tblkt_landing_settings`
- `tblkt_landing_themes`
- `tblkt_landing_sections`
- `tblkt_landing_menus`
- `tblkt_landing_plan_overrides`
- `tblkt_landing_blog_posts`
- `tblkt_landing_leads`

## PHAN 1 - AUDIT LANDING CMS HIEN TAI

| Feature | Current State (from code) | Limitation |
|---|---|---|
| Settings | Form save key-value qua `set_setting` trong `Kt_landing_admin::settings()` | Chua co grouping UX/validation chi tiet/field-level rules |
| Themes | Co list template + Set Default + Preview + form style key-value | Chua co card gallery, metadata theme (version/author), live preview |
| Sections | Co CRUD co ban theo table `kt_landing_sections` | Chua co page-builder UX, chua co block-specific schema, chua drag-drop |
| Menu | Co CRUD label/url/target/sort | Chua nested menu, chua drag-drop, chua social builder UI |
| Pricing | Co marketing override luu o `kt_landing_plan_overrides` | Chua co card editor UX, chua preview theo state truoc publish |
| Blog | Co CRUD co ban (`title/slug/content/status`) | Chua WYSIWYG workflow day du, chua media picker, chua taxonomy manager |
| Leads | Co table + update status + delete | Chua lead detail timeline/assign/convert-to-perfex-lead flow |
| SEO | Co form key-value (`meta/og/canonical/robots/ga/pixel`) | Chua dashboard SEO, chua validation script injection governance |

## Missing Features

1. Chua co **CMS Dashboard** dung nghia (KPI, publish status, content health).
2. Chua co **Theme Customizer UI** chuyen dung (color picker, typography controls, layout slider).
3. Chua co **Pages layer** (Home/Pricing/Features/Contact...), hien tai section gan truc tiep.
4. Chua co **Media Library** (upload manager, search/filter, folder, reuse assets).
5. Chua co **Analytics layer** cho landing funnel.
6. Chua co **Publish workflow** (draft/published/schedule/rollback snapshots).
7. Chua co **Addon manager** UI rieng cho marketing addon catalog.

## CMS Redesign

### Theme Manager
- Hien tai: table basic.
- De xuat:
  - Theme cards + thumbnail + author + version + status.
  - Action: Preview, Set default, Duplicate theme preset.
  - Theme status: draft/active/deprecated.

### Theme Customizer
- Tach trang rieng:
  - Color picker (primary/secondary/accent/text/background)
  - Typography (font family, heading size, body size)
  - Buttons (radius, shadow)
  - Layout (container width, section spacing)
  - Branding assets (logo/favicon/og)
- Governance:
  - custom_css/custom_js co policy allow/deny + sanitizer.

### Page Builder
- Them entity `pages`:
  - `title`, `slug`, `template`, `seo`, `status`, `sort_order`
- Moi page mapping nhieu sections.

### Section Builder
- Section schema theo type (hero/features/pricing/faq/testimonials/cta...).
- UI theo block config thay vi raw field.
- Sort drag-drop.
- Component-level validation.

### Media Library
- Them module media:
  - Upload, list, filter, copy URL, folder labels.
  - Scope: landing/blog/theme.
- Dung chung cho logo, hero image, blog thumbnails, icons.

### Pricing Manager
- Tach 2 lop:
  - Billing source: KT SaaS plans
  - Marketing layer: visibility/badge/featured/cta/copy/sort
- Co card preview theo template.

### Blog CMS
- Danh sach card/table co thumbnail/status/author/date.
- Editor WYSIWYG + SEO box + preview.
- Category/tags manager rieng.

### Lead Manager
- Lead detail page:
  - profile + source + UTM + activity log
- Action:
  - assign owner
  - mark contacted
  - convert to Perfex Lead

### SEO Manager
- General SEO + OG + Twitter + Robots + Tracking IDs.
- Rule engine cho custom head html (allowlist).
- Per-page SEO override.

### Analytics
- Dashboard:
  - visitors, leads, signup, conversion
  - breakdown by page/plan/source/utm
- Bat dau tu event logging + aggregate tables.

### Publish Workflow
- Versioned config:
  - draft
  - preview token
  - publish snapshot
  - rollback
  - optional schedule publish

## Database Gap Analysis

### Da co
- settings/themes/sections/menus/plan_overrides/blog_posts/leads

### Con thieu de dat CMS builder
- `tblkt_landing_pages`
- `tblkt_landing_section_blocks` (neu section builder componentized)
- `tblkt_landing_media`
- `tblkt_landing_media_folders` (optional)
- `tblkt_landing_publish_snapshots`
- `tblkt_landing_analytics_events`
- `tblkt_landing_analytics_daily`
- `tblkt_landing_lead_activities` (assign/contact timeline)

## UI/UX Redesign

1. Chuyen tu form/table placeholder sang:
   - card-based manager (themes/pricing/blog)
   - builder-based UI (pages/sections/menu)
2. Dung control phu hop:
   - color picker, slider, switch, drag-drop
3. Co preview nhieu cap:
   - theme preview
   - page preview
   - draft preview
4. Co publishing state ro rang:
   - Draft / Published / Scheduled

## Recommended Roadmap

### Phase 1 - CMS Foundation
- Tạo pages entity + media library base + theme customizer nâng cấp UI.
- Chuẩn hóa settings schema + validation + permission matrix.
- Hoàn thiện theme manager card view.

### Phase 2 - Builder
- Page builder + section block builder + menu drag-drop/nested.
- Pricing cards preview.
- Blog CMS nâng cấp (WYSIWYG + taxonomy + preview).
- Lead detail + assign + convert flow.

### Phase 3 - Analytics + Publishing
- Event tracking + analytics dashboard.
- Publish workflow: draft/publish/snapshot/rollback/schedule.
- SEO dashboard nâng cao + per-page SEO.

## Ket luan

KT Landing hien da vuot muc "chi co public template", nhung admin CMS van dang o muc CRUD ky thuat.  
De tro thanh Website Builder/CMS thuc su cho landlord, can chuyen sang kien truc builder + media + publish workflow + analytics nhu roadmap tren.
