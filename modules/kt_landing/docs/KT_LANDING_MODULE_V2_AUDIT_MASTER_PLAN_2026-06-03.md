# KT LANDING MODULE V2 AUDIT & MASTER PLAN

Scope:
- Audit current KT Landing module in landlord admin.
- Propose an ideal CMS V2 for full landing/page/content ownership without code changes.
- No code changes in this pass.

## 1. Executive Summary

The current module is a working CMS shell with real persistence for settings, sections, items, menus, pages, media, blog posts, leads, plan overrides, publish snapshots, analytics, and basic SEO fields.

However, it is not yet a full landing CMS V2:
- the public landing still carries a lot of hard-coded front-end content in the template files
- section/page editing exists, but not as a unified section/page builder with variants, repeater controls, and live preview
- publish/preview/versioning exist, but revision diff, rollback UX, and scheduled publishing are still thin
- media, SEO, analytics, and leads are present, but each is a lightweight admin surface rather than a production-grade content platform
- Theme Customizer is effectively a redirect to theme settings, not a standalone builder

Short answer:
- Admin can operate the landing today, but not fully without code literacy.
- Hard-code remains material, especially in Template 1 public views.
- Best next work is Phase 1 stabilization, then a real section/page builder, then SEO/content hub, then analytics/conversion, then enterprise governance.

## 2. Current Module Inventory

| Feature | Current State | Data Source | Frontend Impact | Keep / Merge / Remove / Upgrade | Reason |
|---|---|---|---|---|---|
| Tổng quan | Real dashboard counters and recent records | `kt_landing_*` tables + `kt_saas` plans | Indirect | Upgrade | Useful operational view, but needs SEO/content health and publish health |
| Cài đặt chung | Real persisted config | `kt_landing_settings` | Yes | Keep / Upgrade | Core control surface |
| Giao diện | Real theme list and default theme selection | `kt_landing_themes`, settings | Yes | Merge with Theme Customizer | Overlaps with branding/customizer |
| Theme Customizer | Currently a redirect alias to themes() | themes/settings | Yes | Merge / Upgrade | Not a distinct experience today |
| Pages | Real CRUD | `kt_landing_pages` | Partial | Keep / Upgrade | Needs page builder, SEO, revision history |
| Trang & Section | Real CRUD for sections | `kt_landing_sections`, `kt_landing_section_items` | Yes | Keep / Upgrade | This is the core of V2 builder |
| Menu điều hướng | Real CRUD | `kt_landing_menus` | Yes | Keep / Upgrade | Needs structured header/footer/mobile menus |
| Media Library | Real CRUD metadata only | `kt_landing_media` | Yes | Keep / Upgrade | Needs asset workflow, usage tracking, cropping, alt text |
| Bảng giá | Real marketing overrides | `kt_landing_plan_overrides` + `kt_saas` plans | Yes | Keep / Upgrade | Should remain source-of-truth aware |
| Add-ons | Real add-on visibility/CTA config | `kt_landing_settings` | Yes | Rename / Upgrade | Better as “Ứng dụng mở rộng” |
| Bài viết | Real blog post CRUD | `kt_landing_blog_posts` | Yes | Keep / Upgrade | Needs categories, tags, author, schema, related posts |
| Leads | Real lead CRUD + conversion | `kt_landing_leads`, `kt_landing_lead_activities` | Yes | Keep / Upgrade | Needs UTM, source attribution, spam defense |
| SEO | Real basic SEO fields | `kt_landing_settings` | Yes | Keep / Upgrade | Needs sitemap, redirects, schema, audits |
| Analytics | Real event capture / daily rollup | `kt_landing_analytics_events`, `kt_landing_analytics_daily` | Indirect | Keep / Upgrade | Needs GA4/GTM integration and conversion mapping |
| Publish | Real snapshots + jobs | `kt_landing_publish_snapshots`, `kt_landing_publish_jobs` | Yes | Keep / Upgrade | Needs diff, rollback, preview, schedule UX |
| Preview | Controller redirect to current template preview path | route redirect | Yes | Keep / Upgrade | Needs stable preview URL and draft mode |

## 3. Feature-by-feature Audit

| Feature | Current State | Data Source | Frontend Impact | Keep / Merge / Remove / Upgrade | Reason |
|---|---|---|---|---|---|
| Overview | Real counts only | multiple tables | None directly | Upgrade | Needs publish health, SEO health, broken links, latest changes |
| Settings | Real config store | `kt_landing_settings` | Yes | Keep / Upgrade | Current core config surface |
| Themes | Real default template selector | `kt_landing_themes` | Yes | Merge with customizer | Branding and theme selection overlap |
| Theme Customizer | Alias to themes() | themes/settings | Yes | Merge | Not a distinct control surface |
| Pages | CRUD exists | `kt_landing_pages` | Yes | Upgrade | Needs page sections, SEO, schema, revision history |
| Sections | CRUD exists | `kt_landing_sections` | Yes | Upgrade | Needs visibility, variants, content JSON, media bindings |
| Section items | CRUD exists | `kt_landing_section_items` | Yes | Upgrade | This is where repeater controls belong |
| Menu navigation | CRUD exists | `kt_landing_menus` | Yes | Upgrade | Needs menu role/area presets and validation |
| Media Library | Metadata only | `kt_landing_media` | Yes | Upgrade | Needs asset management workflow |
| Pricing | Marketing override only | `kt_landing_plan_overrides` | Yes | Upgrade | Must stay synced to CRM plans |
| Add-ons | Setting-driven card metadata | `kt_landing_settings` | Yes | Rename + Upgrade | Better modeled as marketplace applications |
| Blog | Basic post CRUD | `kt_landing_blog_posts` | Yes | Upgrade | Needs content hub features |
| Leads | Basic lead capture + status | `kt_landing_leads` | Yes | Upgrade | Needs UTM, dedupe, CRM integration |
| SEO | Basic fields | settings | Yes | Upgrade | Needs technical SEO tooling |
| Analytics | Basic events + daily rollup | analytics tables | Indirect | Upgrade | Needs GA4/GTM/Pixel mapping |
| Publish | Snapshot and scheduled job | snapshot/job tables | Yes | Upgrade | Needs preview and rollback UX |
| Preview | Redirect only | controller logic | Yes | Upgrade | Should be draft-aware and snapshot-aware |

## 4. Template 1 Editability Audit

| Section | Editable Now? | Hard-coded Fields | Missing Controls | Priority |
|---|---|---|---|---|
| Header | Partial | nav labels, CTA defaults, logo fallback | menu variants, CTA registry, visibility toggle | High |
| Hero | Partial | title/subtitle fallback, mockup data | image binding, CTA binding, layout variant | High |
| Hero dashboard mockup | Partial | mostly hard-coded demo values | per-card editor, data binding, variant switch | High |
| Trust indicators | Partial | metrics and badges mostly hard-coded | repeater, ordering, icon binding | High |
| Why CRM Khách Tốt | Partial | section text/copy in template | card editor, variant, link binding | High |
| Comparison table | Partial | matrix rows/cells mostly hard-coded | row editor, plan column editor | High |
| Customer journey | Partial | workflow steps hard-coded | repeater + order + visibility | High |
| Product explorer | Partial | tabs, cards, labels, KPI blocks hard-coded | tab editor, screenshot binding, icon/media binding | High |
| Marketplace / ứng dụng mở rộng | Partial | app cards hard-coded / settings-driven mix | app registry, category, pricing note, screenshot | High |
| Security / trust | Partial | trust copy and metric blocks hard-coded | metric repeater, trust badge editor | Medium |
| Pricing | Partial | plan rendering uses plan source, but marketing copy is mixed | best-for, setup fee, limits, CTA per plan, visibility, order | High |
| Case studies | Partial | case entries mostly hard-coded | case editor, logo/media, metric fields | Medium |
| FAQ | Partial | static fallback text exists | FAQ repeater, collapse state, search | High |
| Final CTA | Partial | CTA text/link fallback | CTA registry, variant, background/media | Medium |
| Footer | Partial | footer links and columns mostly template-driven | menu binding, social links, legal pages | Medium |

## 5. Current Hard-coded Content

The public landing still contains hard-coded content in the active template:
- hero and dashboard mockup text
- trust metrics and trust badges
- why-choose cards
- comparison matrix labels and values
- product explorer tab labels, demo values, workflow labels
- marketplace/app showcase labels
- security cards
- pricing guide cards and explanatory copy
- case studies
- FAQ fallback items
- footer columns

The admin module also has hard-coded labels in UI shell views:
- `Theme Customizer` is only a redirect to themes
- admin forms still use placeholder labels (`title`, `slug`, `sort_order`, etc.) instead of business copy
- several admin screens are CRUD shells, not guided builder experiences

## 6. Missing CMS Controls

What is missing for a real V2:
- section visibility toggle per section with live preview
- drag/drop section ordering
- section variant selection
- repeater management in UI for cards, FAQs, trust metrics, pricing notes, case studies
- media binding per block
- CTA registry per block and per page
- page-specific SEO metadata per page/section
- schema editor
- reusable content blocks
- draft/published states for pages and sections
- publish diff and rollback
- content validation before publish
- image alt/title/caption/focal point
- menu areas with header/footer/mobile variants
- blog categories/tags/author/canonical/related posts
- redirect manager
- analytics event mapping
- lead source and UTM capture
- safe custom CSS/JS guardrails

## 7. Redundant / Overlapping Features

| Pair | Observation | Recommendation |
|---|---|---|
| Giao diện vs Theme Customizer | Same branding domain | Merge |
| Pages vs Trang & Section | Both express page content | Keep both, but unify under Page Builder UX |
| Publish vs Preview | Both belong to publishing flow | Keep both, but move into page-level workflow and topbar actions |
| Add-ons vs Marketplace | Same product surface | Rename Add-ons to Ứng dụng mở rộng |
| SEO vs Settings | Some SEO fields live in settings | Keep SEO as separate center, move SEO-specific config out of general settings |
| Analytics vs Overview | Overview shows metrics, analytics stores events | Keep both, but overview should become a health dashboard over analytics data |
| Customer journey vs 6-step workflow | Same story in prior landing drafts | Remove duplication from landing template |

## 8. Recommended Menu Structure

Recommended V2 menu:
- Tổng quan
- Trang & Section
- Media
- Menu
- Bảng giá
- Ứng dụng mở rộng
- Bài viết
- Leads
- SEO Center
- Analytics
- Publish
- Settings

Optional advanced/admin-only:
- Redirects
- Revisions
- Schema
- Custom CSS/JS
- Audit log

Rename recommendations:
- Add-ons -> Ứng dụng mở rộng
- Theme Customizer -> Giao diện / Branding
- Pages + Trang & Section -> Page Builder
- Publish + Preview -> Publish Center

## 9. CMS Data Model Proposal

Proposed tables:

| Table | Purpose | Key Columns | Relationships | Indexing | Scope | Publish State |
|---|---|---|---|---|---|---|
| `kt_landing_templates` | Template catalog | id, code, name, status, description, preview_image | 1:N pages, sections | unique code | landlord | draft/active |
| `kt_landing_pages` | Page registry | id, slug, title, template_code, status, seo fields | 1:N sections | unique slug | landlord/public | draft/published/scheduled |
| `kt_landing_sections` | Section definitions | id, page_id/page_key, section_key, title, subtitle, layout_variant, visibility, sort_order, settings_json | 1:N blocks/items | unique page+key, sort idx | page-scoped | draft/published |
| `kt_landing_blocks` | Reusable blocks | id, block_key, block_type, content_json, media_id, cta_id, sort_order | N:1 media/cta | block key, type | landlord/public | draft/published |
| `kt_landing_section_items` | Repeater items | id, section_id, item_key, title, subtitle, content, icon, image, badge, button fields, settings_json | N:1 section | section_id + item_key + sort | section-scoped | draft/published |
| `kt_landing_media` | Media assets | id, folder, file_name, file_path, mime, size, title, alt_text, caption, focal_point, usage_count | referenced by blocks/sections/pages | file_path, folder | landlord/public | n/a |
| `kt_landing_menus` | Navigation menus | id, menu_area, label, url, target, group_name, icon, sort_order | used by pages/layout | area + sort | landlord/public | published-only |
| `kt_landing_cta_registry` | CTA presets | id, cta_key, label, url, style, tracking_key | referenced by pages/blocks | unique key | landlord/public | draft/published |
| `kt_landing_pricing_overrides` | Marketing pricing layer | id, plan_id, title, subtitle, badge, cta, visibility, sort_order | references CRM plans | plan_id unique | landlord/public | draft/published |
| `kt_landing_marketplace_apps` | App catalog | id, app_key, marketing_name, category, description, pricing_note, screenshot, cta | linked to add-ons | app_key unique | landlord/public | draft/published |
| `kt_landing_blog_posts` | Blog/content hub posts | id, slug, title, excerpt, content, category_id, author_id, status, meta, schema | N:1 category | slug, status, publish date | landlord/public | draft/published/scheduled |
| `kt_landing_blog_categories` | Blog categories | id, slug, name, sort_order | 1:N posts | slug | landlord/public | active |
| `kt_landing_seo_meta` | Page SEO | id, page_id, title, description, canonical, robots, og, twitter, hreflang | 1:1 page | page_id unique | page-scoped | draft/published |
| `kt_landing_schema` | Structured data | id, page_id, schema_type, json_ld | 1:1 or 1:N page | page_id + schema_type | page-scoped | draft/published |
| `kt_landing_redirects` | Redirect manager | id, source, target, status_code, enabled | standalone | source unique | landlord/public | active |
| `kt_landing_revisions` | Revision history | id, entity_type, entity_id, snapshot_json, created_by, created_at | all entities | entity_type + entity_id | landlord/public | n/a |
| `kt_landing_publish_snapshots` | Publish snapshots | already exists | page/sections/menu/settings payload | snapshot type | landlord | published snapshot |
| `kt_landing_publish_jobs` | Scheduled publish jobs | already exists | snapshot -> publish_at | status + publish_at | landlord | queued/running/done |

Notes:
- Current schema already covers a subset of these ideas, but not with builder-grade separation.
- The next design should normalize sections/blocks/repeaters/media/SEO/revisions.

## 10. Section Builder Plan

Builder requirements:
- section enable/disable
- section ordering via drag/drop
- layout variant selector
- per-section title/subtitle/description/CTA editors
- media picker binding
- icon picker
- repeater editor for cards/FAQs/trust metrics
- section-level SEO relevance flag
- validation rules
- draft/published state
- live preview

Suggested section model:
- internal_key
- public_title
- admin_description
- visibility
- sort_order
- layout_variant
- content_json
- media_binding_json
- seo_relevance
- validation_rules_json
- published_state

Admin description must remain admin-only metadata.
Frontend must render only public marketing content.

## 11. Media Library Plan

| Capability | Exists? | Gap | Recommendation |
|---|---|---|---|
| Upload image | Partial | No guided workflow | Add uploader with validation |
| SVG icon | Partial | No icon library UX | Add icon picker and SVG whitelist |
| WebP/AVIF | Partial | No smart derivatives | Support responsive derivatives |
| Alt text | No | Missing metadata | Add required alt text |
| Title/caption | No | Missing metadata | Add optional title/caption |
| Focal point | No | Missing crop focus | Add focal point selector |
| Crop | No | No in-app crop | Add crop presets if needed |
| Responsive sizes | No | Not managed centrally | Generate responsive variants |
| Lazy loading | Partial | Template-level only | Standardize in renderer |
| File size validation | Partial | Weak | Enforce at upload |
| Compression | Partial | Manual only | Add optimization pipeline |
| Replace without breaking links | No | No asset versioning | Add stable asset IDs |
| Folder/category | Partial | Folder string only | Add folder taxonomy |
| Used-in tracking | No | No references graph | Track references |
| Delete protection | No | Risk of breaking pages | Block delete if referenced |
| CDN-ready path | Partial | Depends on file path | Store CDN-safe URLs |

## 12. Blog / Content Hub Plan

Current state:
- basic CRUD post editor exists
- posts are stored in `kt_landing_blog_posts`
- public blog route exists
- no strong editorial workflow

Recommended content hub design:
- keep blog in landing module for now if scope is marketing content only
- move to a separate content module only if the team wants a general CMS for multiple properties
- use block editor or structured blocks over plain WYSIWYG for landing-aligned content
- support markdown import/export only if editorial team actually uses it; otherwise it adds complexity

Must-have blog fields:
- title
- slug
- excerpt
- featured image
- content
- category
- tags
- author
- publish date
- updated date
- status
- related posts
- canonical URL
- meta title
- meta description
- OG image
- schema Article/BlogPosting
- reading time
- TOC
- internal links
- FAQ block
- CTA block
- sitemap inclusion

## 13. Advanced SEO Plan

| SEO Feature | Current | Gap | Priority | Implementation Note |
|---|---|---|---|---|
| Meta title | Partial | Per page only, not audited centrally | High | Add page SEO center |
| Meta description | Partial | Same as above | High | Validate length and uniqueness |
| Slug | Partial | Basic page/blog slug only | High | Enforce uniqueness and format |
| Canonical | Partial | Global defaults exist | High | Per page canonical management |
| Robots index/follow | Partial | Stored in settings | High | Per page override |
| OG title/description/image | Partial | Not structured enough | High | Add dedicated OG fields |
| Twitter card | No | Missing | Medium | Add with OG mapping |
| Favicon | Yes | Branding setting only | Medium | Keep in branding settings |
| Language/hreflang | Partial | No real hreflang builder | Medium | Add for multi-language pages |
| XML sitemap | No | Missing | High | Generate from published pages/posts |
| robots.txt editor | No | Missing | Medium | Add guarded editor |
| Redirect manager | No | Missing | High | 301/302 source-target table |
| 404 monitor | No | Missing | Medium | Log missing URL hits |
| Broken link checker | No | Missing | High | Pre-publish check |
| Trailing slash policy | No | Missing | Medium | Define at app level |
| Duplicate content detection | No | Missing | Medium | Add publish checks |
| Pagination canonical | No | Missing | Low | Relevant mainly for blog |
| Image alt audit | No | Missing | High | Part of media workflow |
| Heading audit | No | Missing | Medium | Optional content validation |
| Meta length validation | No | Missing | High | Pre-publish validation |
| Slug validation | Partial | Basic only | High | Enforce canonical rules |

Schema coverage to support:
- Organization
- LocalBusiness
- SoftwareApplication
- Product
- Offer
- FAQPage
- BreadcrumbList
- Article
- BlogPosting
- WebSite
- WebPage
- Review / AggregateRating when real data exists

Performance SEO:
- lazy-load images
- WebP/AVIF derivation
- preload critical assets
- defer non-critical JS
- minify CSS/JS
- cache headers
- core web vitals checks
- font loading optimization
- critical CSS where it matters

## 14. Analytics & Tracking Plan

Recommended events:

| Event | Trigger | Payload | Destination |
|---|---|---|---|
| `signup_start` | first step of signup | page, plan, utm, referrer | GA4/GTM/Meta |
| `plan_selected` | plan card click | plan_id, plan_name, price, cycle | GA4/GTM |
| `signup_submitted` | signup form submit | company, plan, source, campaign | CRM + GA4 |
| `checkout_started` | checkout handoff | invoice_id, plan, amount | GA4/GTM |
| `payment_success` | payment callback success | invoice_id, tenant_id, gateway | CRM + GA4 |
| `contact_submit` | contact form submit | lead source, page, utm | CRM + GA4 |
| `demo_booking` | demo booking submit | company, page, campaign | CRM + GA4 |
| `cta_click` | CTA click | cta_key, page, section | GA4/GTM |
| `scroll_depth` | milestone scroll | page, depth | GA4 |
| `outbound_link` | external link click | url, source page | GA4 |
| `lead_source` | lead created | referrer, utm, landing page | CRM |
| `campaign_attribution` | first-touch assignment | utm set | CRM |

Recommended stack:
- GA4
- Google Tag Manager
- Meta Pixel
- optional TikTok Pixel
- optional LinkedIn Insight Tag for B2B
- privacy/consent mode if required by deployment

## 15. Leads & Conversion Plan

Current state:
- lead form and landing lead capture exist
- lead table and activity table exist
- conversion to Perfex lead exists

Gaps:
- UTM capture is present but not standardized into a full attribution model
- spam protection is basic
- CRM lead creation should be more tightly tied to landing source and selected plan
- no webhook/export automation framework in the module

Recommended capabilities:
- contact form
- demo booking
- lead source
- UTM capture
- referrer
- landing page
- selected plan
- selected CTA
- validation
- honeypot
- rate limit
- optional reCAPTCHA/Turnstile
- auto-assign sales
- CRM lead creation
- email notification
- webhook/Zapier/Make optional
- CSV export
- duplicate detection

## 16. Pricing / Plan Sync Plan

Current state:
- landing pricing is synced to public plans with marketing overrides
- plan cards are driven by `kt_saas` public plans plus `kt_landing_plan_overrides`

This is the correct direction, but V2 should formalize:
- plan code
- display name
- price
- billing cycle
- setup fee
- trial days
- description
- best for
- badge
- included apps
- limits
- CTA
- visibility
- order
- featured flag

Rule:
- billing engine remains source of truth for actual billing
- landing module only controls presentation and marketing overrides
- no direct hard-code in templates

## 17. Marketplace / Add-ons Plan

Recommended app catalog:
- Hóa đơn điện tử
- Thanh toán & Đối soát
- Quản lý kho
- Website doanh nghiệp
- Chữ ký số
- Tên miền
- Hosting
- Tích hợp OpenAI
- Báo cáo nâng cao

Per app fields:
- marketing name
- internal key
- icon
- short description
- long description
- category
- pricing note
- availability
- CTA
- linked plan/add-on
- screenshot
- SEO slug

Guideline:
- customer-facing UI should not expose internal keys like `kt_matbao_invoice`

## 18. Publish / Preview / Versioning Plan

Current state:
- publish snapshots exist
- publish jobs exist
- preview is a redirect to the current template

Missing for V2:
- draft mode
- stable preview URL
- publish now
- schedule publish
- rollback previous version
- revision comparison
- change history
- publish checklist
- SEO checklist before publish
- broken link check before publish
- cache clear after publish

Snapshot should include:
- page config
- sections
- media references
- SEO
- menus
- theme settings
- pricing overrides

## 19. Permissions Plan

Recommended roles:
- Super admin
- Marketing admin
- Content editor
- SEO manager
- Designer
- Viewer

Suggested capability groups:
- edit content
- edit SEO
- upload media
- publish
- rollback
- edit tracking code
- edit custom CSS/JS
- manage redirects
- delete content

## 20. V2 Roadmap

### Phase 1 - Audit & Stabilize
Goal:
- remove hard-code from Template 1
- map section content to CMS
- fix media references
- fix SEO basics
- stabilize preview/publish

Likely affected files/modules:
- `modules/kt_landing/controllers/Kt_landing.php`
- `modules/kt_landing/controllers/Kt_landing_admin.php`
- `modules/kt_landing/models/Kt_landing_model.php`
- `modules/kt_landing/views/public/templates/*`
- `modules/kt_landing/views/admin/*`

DB changes:
- minimal if possible
- maybe add missing metadata columns only after audit

Risks:
- partial hard-code remains visible
- regression in public landing render

Test plan:
- desktop/mobile screenshot verification
- publish snapshot creation
- preview redirect tests

Acceptance:
- template output is CMS-driven for critical sections
- no public hard-code for obvious customer-facing copy

### Phase 2 - Page Builder
Goal:
- full sections CRUD with variants and reorder
- block editor and repeater fields
- live preview

Files/modules:
- landing admin controller/model/views
- public template renderer

DB:
- add block/revision tables if needed

Risks:
- complexity increase
- builder UX can become too large

Test plan:
- create/update/reorder sections
- publish preview
- rollback

Acceptance:
- admin can build pages without code

### Phase 3 - SEO Center
Goal:
- sitemap
- robots
- redirects
- schema
- page SEO scoring

Files/modules:
- landing SEO controller/view
- sitemap generator
- redirect middleware

DB:
- SEO meta
- redirects
- schema tables

Risks:
- canonical conflicts
- bad redirects if unmanaged

Acceptance:
- all pages have SEO metadata and can be validated before publish

### Phase 4 - Blog & Content Hub
Goal:
- blog, case studies, FAQ manager, editorial workflow

Files/modules:
- blog controller/view/model
- taxonomy tables

DB:
- categories/tags/related content tables

Risks:
- editorial scope creep

Acceptance:
- marketing team can manage long-form content and related CTA blocks

### Phase 5 - Analytics & Conversion
Goal:
- GA4/GTM
- UTM attribution
- CTA tracking
- lead source tracking

Files/modules:
- analytics hooks
- lead capture forms

DB:
- event mapping / attribution tables if required

Risks:
- duplicate event firing

Acceptance:
- events can be measured and attributed

### Phase 6 - Enterprise Polish
Goal:
- revisions
- rollback
- scheduled publish
- roles/permissions
- audit log
- multi-template support

Files/modules:
- publish controller/model
- permission layer
- audit logging

DB:
- revisions
- audit log

Acceptance:
- enterprise-grade operational control

## 21. Risks

- The module is already useful, but the current public landing still carries too much hard-coded template content.
- Theme Customizer is not a real customizer yet.
- Media is only a metadata list, not a full asset manager.
- SEO is mostly a settings layer, not a real SEO center.
- Publish/rollback needs a more explicit UX and revision diffing.
- Blog/content hub is basic and needs editorial structure.
- Landing and pricing are partially synced to CRM plan data, but the marketing layer can still drift without guardrails.

## 22. Recommended Next Action

Build order I recommend:
1. Stabilize Template 1 and convert remaining hard-coded sections into CMS-backed sections/blocks.
2. Merge Theme Customizer into a real branding/page-builder workflow.
3. Add revisions, preview, publish, and rollback as first-class controls.
4. Add SEO Center and redirects before expanding blog/content volume.
5. Add media management depth and analytics attribution.
6. Expand into marketplace/app catalog and advanced conversion tooling.

## Final Assessment

- Can admin fully operate landing without code today? Partially, not completely.
- Approximate hard-code remaining? Still material, especially on Template 1 public rendering. I would treat it as roughly 40-60% hard-coded depending on section and page surface.
- What should come first? Section/page builder stabilization and hard-code reduction.
- What is redundant? Theme Customizer vs Giao diện, Add-ons vs Marketplace, Publish vs Preview shells.
- How many phases to reach ideal 100%? Six phases is the right plan.

