# KT LANDING LANDLORD CMS - PHASE CHECKLIST (2026-06-01)

## Phase 1 - Admin foundation

- [x] Them permission `kt_landing_*` trong bootstrap module.
- [x] Them menu landlord "Landing Page" + cac submenu.
- [x] Them route admin:
  - `/admin/kt_landing`
  - `/admin/kt_landing/settings`
  - `/admin/kt_landing/themes`
  - `/admin/kt_landing/sections`
  - `/admin/kt_landing/menu`
  - `/admin/kt_landing/pricing`
  - `/admin/kt_landing/blog`
  - `/admin/kt_landing/leads`
  - `/admin/kt_landing/seo`
  - `/admin/kt_landing/preview/{template}`
- [x] Them install idempotent cho bang `tblkt_landing_*`.
- [x] Them admin controller + views co ban.
- [x] Them schema bo sung cho `pages`, `media`, `publish_snapshots`, `analytics_events`, `analytics_daily`, `lead_activities`.

## Phase 2 - Dynamic content managers

- [x] Section manager (CRUD co ban + enable/sort fields).
- [x] Menu manager (header/footer/social table).
- [x] Pricing marketing override theo `plan_id`.
- [x] SEO settings page.
- [x] Public controller doc du lieu CMS + fallback.
- [x] Template public doc menu + custom css/js tu CMS (khong phu thuoc hard-code 100% cho nav).
- [x] Them page manager (`/admin/kt_landing/pages`).
- [x] Them media library manager (`/admin/kt_landing/media`).
- [x] Them add-on manager (`/admin/kt_landing/addons`).
- [x] Theme customizer route (`/admin/kt_landing/customizer`).

## Phase 3 - Marketing ops

- [x] Blog manager co ban (draft/published, slug, delete).
- [x] Leads manager co ban (list/update status/delete).
- [x] Preview template flow (`/admin/kt_landing/preview/{template}` -> `?tpl=`).
- [x] Publish snapshot workflow (`/admin/kt_landing/publish`).
- [x] Analytics dashboard co ban (`/admin/kt_landing/analytics`) + event tracking `page_view`, `signup_submit`, `lead_submit`.
- [x] Public blog route `/blog`.
- [x] Public lead capture endpoint `/contact/submit`.
- [x] Rollback/apply snapshot (apply snapshot action trong trang publish).
- [x] Schedule publish + cron processor (`after_cron_run`).
- [x] Lead convert to Perfex lead (action convert trong trang leads).
- [ ] Full drag-drop builder UI (chua co).

## Test results

### Static / syntax

- [x] `php -l` pass toan bo `modules/kt_landing/**/*.php`
- [x] `php -l application/config/routes.php`

### Existing static self-check

- [x] `modules/kt_saas/tools/phase4_static_self_check.php` -> `15/15` pass

### HTTP smoke (HEAD)

- [x] `https://khachtot.test/` -> `200`
- [x] `https://khachtot.test/pricing` -> `200`
- [x] `https://khachtot.test/signup` -> `200`
- [x] `https://khachtot.test/blog` -> `200`
- [x] `https://khachtot.test/admin/kt_landing` -> `303` (redirect auth expected)
- [x] `https://khachtot.test/admin/kt_landing/settings` -> `303` (redirect auth expected)
- [x] `https://khachtot.test/admin/kt_landing/pages` -> `303`
- [x] `https://khachtot.test/admin/kt_landing/media` -> `303`
- [x] `https://khachtot.test/admin/kt_landing/analytics` -> `303`
- [x] `https://khachtot.test/admin/kt_landing/publish` -> `303`
- [x] `https://khachtot.test/contact/submit` -> `404` cho HEAD (expected; endpoint chi nhan POST)

## Files added/updated (high level)

- `modules/kt_landing/kt_landing.php`
- `modules/kt_landing/install.php`
- `modules/kt_landing/helpers/kt_landing_helper.php`
- `modules/kt_landing/models/Kt_landing_model.php`
- `modules/kt_landing/controllers/Kt_landing_admin.php`
- `modules/kt_landing/views/admin/*`
- `modules/kt_landing/controllers/Kt_landing.php`
- `modules/kt_landing/views/public/templates/*/index.php` (tich hop CMS data co ban)
- `application/config/routes.php`

## Remaining backlog (next hardening)

1. Tach upload branding asset thanh flow upload an toan (khong chi text URL).
2. Section rendering engine theo `section_key` + `is_enabled` dong cho ca 4 template.
3. Add-on manager UI rieng + map tu addon engine.
4. Blog public listing/detail route + template.
5. Lead capture endpoint contact/demo/newsletter bo sung ngoai signup flow.
6. Publish workflow (draft -> published) co version snapshot.
