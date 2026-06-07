# LANDING CMS CONTENT SEPARATION REPORT

Scope:
- Audit KT Landing CMS content flow for admin-only vs public marketing rendering.
- Check hero, why KT SaaS, comparison, marketplace, security, pricing, FAQ, case studies, CTA.
- Fix real frontend leaks after the audit.

## Admin-only Fields

| Field | Current Value | Render Frontend? | Admin Only? | Public Content? |
|---|---|---:|---:|---:|
| `kt_landing_sections.id` | numeric row id | no after fix | yes | no |
| `kt_landing_sections.page_key` | `home`, other page keys | no after fix | yes | no |
| `kt_landing_sections.section_key` | internal section key | no after fix | yes | no |
| `kt_landing_sections.settings_json` | internal config JSON | no after fix | yes | no |
| `kt_landing_sections.sort_order` | display order integer | no after fix | yes | no |
| `kt_landing_sections.is_enabled` | enable flag | no after fix | yes | no |
| `kt_landing_section_items.id` | numeric row id | no after fix | yes | no |
| `kt_landing_section_items.section_id` | internal FK | no after fix | yes | no |
| `kt_landing_section_items.item_key` | internal item key | no after fix | yes | no |
| `kt_landing_section_items.settings_json` | internal config JSON | no after fix | yes | no |
| `kt_landing_section_items.sort_order` | display order integer | no after fix | yes | no |
| `kt_landing_section_items.is_enabled` | enable flag | no after fix | yes | no |
| plan `notes` | internal plan note from SaaS plan layer | **was yes**, now no | yes | no |
| raw plan `description` | generic internal plan description | **was yes**, now no | yes | no |
| admin placeholders/hints in `views/admin/*` | `page_key`, `section_key`, `sort_order`, `marketing_description`, `item_key`, etc. | no | yes | no |

Notes:
- Admin placeholders and helper inputs are present in:
  - `modules/kt_landing/views/admin/sections.php`
  - `modules/kt_landing/views/admin/section_items.php`
  - `modules/kt_landing/views/admin/pricing.php`
  - related admin CMS screens
- These are valid admin guidance and should not be reused as frontend copy.

## Public Marketing Fields

| Field | Current Value | Render Frontend? | Admin Only? | Public Content? |
|---|---|---:|---:|---:|
| `hero_title` | marketing headline | yes | no | yes |
| `hero_subtitle` | marketing supporting copy | yes | no | yes |
| `hero_image` | hero visual | yes | no | yes |
| `header_cta_text` | public CTA label | yes | no | yes |
| section `title` | public section heading | yes | no | yes |
| section `subtitle` | public section subheading | yes | no | yes |
| section `content` | public body copy | yes | no | yes |
| item `badge` | public badge text | yes | no | yes |
| item `button_text` | public CTA label | yes | no | yes |
| item `button_url` | public CTA URL | yes | no | yes |
| item `image` / `icon` | public visual metadata | yes | no | yes |
| plan `marketing_title` | pricing headline | yes | no | yes |
| plan `marketing_subtitle` | best-for copy | yes | no | yes |
| plan `marketing_description` | public pricing description | yes | no | yes |
| plan `badge_text` | pricing badge | yes | no | yes |
| plan `cta_text` | pricing CTA | yes | no | yes |
| plan `cta_url` | pricing CTA URL | yes | no | yes |

## Incorrectly Rendered Internal Text

### Findings

| Field | Current Value | Render Frontend? | Admin Only? | Public Content? |
|---|---|---:|---:|---:|
| `public_plans[].notes` in `fastwork_inspired` | plan internal note | **was yes** | yes | no |
| `public_plans[].description` in `corporate_saas` | generic/raw plan description | **was yes** | yes | no |
| raw section/item arrays from CMS | contained internal metadata keys | indirectly exposed to template layer before sanitation | mixed | mixed |

### Evidence before fix
- `modules/kt_landing/views/public/templates/fastwork_inspired/index.php`
  - pricing block used `($plan['notes'] ?? $displayDescription)`
- `modules/kt_landing/views/public/templates/corporate_saas/index.php`
  - pricing block used `($desc !== '' ? $desc : ($plan['description'] ?? ''))`
- `modules/kt_landing/controllers/Kt_landing_public.php`
  - public build path was not strongly restricting section/item payloads to marketing-safe keys

## Fixed Frontend Rendering

### Files changed
- `modules/kt_landing/controllers/Kt_landing_public.php`
- `modules/kt_landing/views/public/templates/fastwork_inspired/index.php`
- `modules/kt_landing/views/public/templates/corporate_saas/index.php`

### Controller fix
- `Kt_landing_public::buildLandingData()` now feeds frontend from sanitized CMS/public data.
- Added public-safety sanitation helpers:
  - `sanitizeMarketingText()`
  - `sanitizeLandingSection()`
  - `sanitizeLandingItem()`
  - `sanitizeMarketingList()`
- Public builders now whitelist marketing keys only for:
  - landing sections/items
  - product marketing
  - features
  - FAQ
  - testimonials
  - plan marketing overrides
- Text matching internal guidance markers such as `editor note`, `admin description`, `cms hint`, `helper text`, `placeholder`, `internal documentation`, `internal note`, `admin only` is stripped from the public path.

### Template fix
- `fastwork_inspired`
  - top CTA now uses `header_cta_text` instead of hardcoded sales text
  - marketplace cards now prefer sanitized CMS item content and CTA text
  - FAQ now prefers sanitized FAQ data from CMS/public controller
  - case studies now prefer sanitized CMS case-study items
  - pricing no longer renders `plan['notes']`
- `corporate_saas`
  - pricing no longer falls back to raw `plan['description']`

## Before vs After

| Area | Before | After |
|---|---|---|
| Public controller payload | mixed hardcoded/default data and broad CMS payloads | marketing-only sanitized payload |
| Fastwork pricing note | could render internal `notes` | renders only public marketing description |
| Corporate pricing description | could render raw generic plan `description` | renders only public marketing description |
| Marketplace/FAQ/case studies | mostly hardcoded fallback, limited CMS separation | prefer sanitized public CMS fields |
| Admin guidance leakage risk | present in data path and pricing fallbacks | blocked at controller and template level |

## Verification

- `php -l modules/kt_landing/controllers/Kt_landing_public.php` -> pass
- `php -l modules/kt_landing/views/public/templates/fastwork_inspired/index.php` -> pass
- `php -l modules/kt_landing/views/public/templates/corporate_saas/index.php` -> pass

## Final State

- Admin guidance stays in admin.
- Frontend now renders public marketing copy only.
- The known pricing leaks from plan internal fields are removed.
