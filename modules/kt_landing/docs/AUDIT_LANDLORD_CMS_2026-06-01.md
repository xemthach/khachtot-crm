# BAO CAO AUDIT & KE HOACH BO SUNG LANDLORD CMS CHO KT LANDING

Ngay audit: 2026-06-01

## 1) Executive Summary

KT Landing hien tai da co lop public (`/`, `/pricing`, `/signup`) va 4 template render duoc. Tuy nhien module **chua co landlord CMS** de quan ly landing tu admin: chua co menu admin, chua co controller admin, chua co model admin, chua co bang du lieu rieng cho theme/section/menu/blog/leads/seo/publish.

Ket luan: Module dang o muc "public rendering + signup flow", chua dat yeu cau "Landlord CMS day du".

## 2) Current Module Audit

Da scan:

- `modules/kt_landing/kt_landing.php`
- `modules/kt_landing/controllers/Kt_landing.php`
- `modules/kt_landing/views/public/*`
- `modules/kt_landing/views/public/templates/*`
- `modules/kt_landing/assets/templates/*`
- `modules/kt_landing/language/*`
- `application/config/routes.php`
- tim kiem codebase theo key: `kt_landing`, `tblkt_landing*`, permission/menu hooks

### Bang tong hop hien trang

| Thanh phan | File/Table | Hien trang | Thieu gi |
| --- | --- | --- | --- |
| Module bootstrap | `modules/kt_landing/kt_landing.php` | Co activation/uninstall hook + lang register | Chua co `admin_init` hook, chua co permission register, chua co menu register |
| Public controller | `modules/kt_landing/controllers/Kt_landing.php` | Co `home/pricing/signup/signup_status`; load plan tu `Kt_saas_model->get_public_plans()` | Chua co admin CRUD/settings CMS |
| Route public | `application/config/routes.php` | `default_controller=kt_landing`; co `/pricing`, `/signup`, `/signup/status` | Chua co route admin `admin/kt_landing/*` |
| Module routes rieng | `modules/kt_landing/config/routes.php` | Khong ton tai | Chua co route map cho admin pages |
| Model | `modules/kt_landing/models/` | Khong co | Chua co model quan ly settings/themes/sections/menu/blog/leads/seo |
| DB tables KT Landing | `tblkt_landing_*` | Khong thay trong code | Chua co schema CMS rieng |
| Theme selector | `resolveTemplateCode()` | Doc `?tpl=` va `get_option('kt_landing_template')` | Chua co giao dien landlord de set/publish |
| Settings source | `get_option('kt_landing_*')` + company options | Co fallback data | Chua co validate/save workflow, chua co phan quyen |
| Pricing data | `Kt_saas_model::get_public_plans()` | Reuse dung nguon billing | Chua co marketing override (`badge`, `featured`, `CTA override`, sort/visibility) |
| Menu nav landing | Template views | Dang hard-code menu render theo template | Chua co bang/menu manager |
| Sections | Template views + option json fallback | Co mot phan JSON option (`features/faq/testimonials`) | Chua co section manager tong quat (enable/sort/image/button/settings_json) |
| Blog | N/A | Chua co | Chua co post model/bang/admin/public list |
| Leads | Signup flow co tao tenant/invoice theo luong public signup | Chua co module leads contact/demo/newsletter tach rieng | Chua co lead inbox/convert/status |
| SEO | Meta title/desc + og image co ban | Co muc toi thieu | Chua co trang SEO day du (`canonical`, robots, GA, Pixel, custom head governance) |
| Preview/Publish | Co `?tpl=` preview theo request | Chua co publish flow admin | Chua co draft/published per section/config |

## 3) Admin Menu Proposal (Landlord only)

De xuat menu landlord:

- Tong quan
- Cai dat chung
- Giao dien
- Trang & Section
- Menu dieu huong
- Bang gia
- Bai viet
- Leads
- SEO
- Preview

Phan quyen de xuat:

- `kt_landing.view`
- `kt_landing.configure`
- `kt_landing.theme`
- `kt_landing.sections`
- `kt_landing.blog`
- `kt_landing.leads`
- `kt_landing.publish`

Luu y: menu nay chi render trong landlord context; tenant runtime khong duoc thay.

## 4) General Settings Gap

Can bo sung UI + backend luu cac key:

- `landing_enabled`, `homepage_mode`, `default_template`
- `site_name`, `site_title`, `site_description`
- contact/company fields
- feature flags: blog/contact/public_signup/pricing/addons/seo
- `maintenance_mode`

Backend bat buoc:

- sanitize + validate
- flash success/error that that bai dung
- khong bao success gia

## 5) Theme/Style Manager Gap

Can co:

- Danh sach template: `fastwork_inspired`, `corporate_saas`, `modern_growth`, `minimal_enterprise`
- Preview thumbnail + Set default
- Style controls: color palette, radius, font, custom CSS/JS governance
- Branding upload: logo light/dark, favicon, og image

Public templates phai doc data tu CMS truoc, fallback sau.

## 6) Section Manager Gap

Can them CRUD/bat-tat/sap xep:

- Hero, trust bar, features, modules, industry, workflow, addons, pricing, testimonials, faq, blog preview, contact cta, footer

Du lieu section can co:

- `section_key`, `title`, `subtitle`, `content`, `image`, `icon`, `button_text`, `button_url`, `enabled`, `sort_order`, `settings_json`

## 7) Menu Manager Gap

Can bo sung manager cho:

- Header menu
- Footer menu
- Social links

Public template khong duoc hard-code menu neu da co du lieu CMS.

## 8) Pricing Manager Gap

Nguon billing plan: **giu nguyen** tu KT SaaS (`get_public_plans`).

Can bo sung overlay marketing:

- visibility tren landing
- marketing title/description
- badge, featured
- CTA text/url override
- sort order
- show/hide limits va modules

Khong sua bang billing goc khi sua marketing.

## 9) Add-on Manager Gap

Can co lop marketing cho add-on display (visible/title/desc/price display/cta/sort).
Neu addon engine chua co, chi hien thi trang thai "chua co du lieu", khong hard-code gia gia.

## 10) Blog Manager Gap

Can bo sung bang + admin CRUD:

- title/slug/excerpt/content/image/category/tags/status/published_at/seo/sort

Bat buoc:

- slug unique
- sanitize/XSS safe
- preview + publish/unpublish

## 11) Lead Manager Gap

Can bo sung lead inbox cho:

- contact/demo/trial/newsletter
- UTM/source tracking
- status + actions (view/mark contacted/convert/delete)

## 12) SEO Manager Gap

Can bo sung:

- default title/description/og
- canonical
- robots index/follow
- GA id / Pixel id
- custom head html (co governance on/off)

## 13) Preview/Publish Flow Gap

Can bo sung:

- preview template truoc khi set default
- preview section draft
- publish change

Phase dau co the chua can full draft workflow, nhung bat buoc co preview template trong admin.

## 14) Database Gap

Hien tai: chua co bang `tblkt_landing_*`.

De xuat them (idempotent migration):

- `tblkt_landing_settings`
- `tblkt_landing_themes`
- `tblkt_landing_sections`
- `tblkt_landing_menus`
- `tblkt_landing_plan_overrides`
- `tblkt_landing_blog_posts`
- `tblkt_landing_leads`

Nguyen tac:

- khong tao trung neu da ton tai
- khong xoa du lieu hien co
- migration an toan, co rollback strategy theo release process

## 15) Public Template Integration Target

Sau khi co CMS, public template phai lay:

- default template
- global style + branding
- menu
- section data
- pricing overrides
- blog preview
- CTA + SEO

Fallback chi dung khi DB chua co du lieu.

## 16) Test Cases (Mandatory)

- Toggle `landing_enabled` -> homepage fallback dung mode.
- Set default template -> `/` doi template dung.
- Doi style/logo -> render dung tren public.
- Tat section pricing -> pricing section an.
- Hide plan -> khong hien tren landing.
- Featured plan -> co badge.
- Blog draft/published -> public render dung.
- Contact submit -> lead duoc luu.
- Tenant runtime/subdomain -> khong bi route landing chiem.
- Tenant user -> khong thay menu landlord landing.

## 17) Implementation Roadmap

### Phase 1 (Nen tang admin + template control)

1. Them `admin_init` hook + permission register + landlord menu register trong `modules/kt_landing/kt_landing.php`.
2. Them admin controller:
   - `modules/kt_landing/controllers/Kt_landing_admin.php`
3. Them routes admin:
   - `modules/kt_landing/config/routes.php` hoac map qua core route strategy dang dung.
4. Them schema migration idempotent toi thieu:
   - `settings`, `themes`
5. Them trang:
   - `/admin/kt_landing`
   - `/admin/kt_landing/settings`
   - `/admin/kt_landing/themes`
6. Public render uu tien DB settings; fallback ve `get_option` cu de tranh gay vo.

### Phase 2 (Noi dung dynamic)

1. Section manager + menu manager + pricing override + SEO manager.
2. Bo sung upload asset governance + sanitizer cho css/js custom.
3. Tich hop render day du cho 4 template.

### Phase 3 (Marketing ops)

1. Blog manager + leads manager.
2. Preview/publish workflow.
3. Add-on manager.
4. Regression test + docs.

---

## File/Function da audit (tham chieu nhanh)

- `modules/kt_landing/kt_landing.php`
- `modules/kt_landing/controllers/Kt_landing.php`
- `application/config/routes.php`
- `modules/kt_saas/models/Kt_saas_model.php::get_public_plans()`

## Ket luan cuoi

Yeu cau "Landlord quan ly day du Landing Page CMS" hien **chua dat**.  
Can trien khai theo roadmap tren, bat dau tu Phase 1 de bo sung menu, permission, settings, theme selector va route admin ma khong pha runtime public/tenant hien co.
