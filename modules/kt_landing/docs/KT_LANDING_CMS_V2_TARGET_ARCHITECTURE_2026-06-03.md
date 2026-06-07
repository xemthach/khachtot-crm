# KT LANDING CMS V2 TARGET ARCHITECTURE

Scope:
- Target architecture and implementation roadmap for KT Landing CMS V2.
- No code changes in this pass.
- Built from the existing audit set and current module state.

## 1. Executive Summary

KT Landing V2 should become a single marketing operating system for the landlord team:
- landing/page builder
- website CMS
- blog/content hub
- SEO platform
- lead generation platform
- conversion tracking center
- publish/version/rollback center

The V2 architecture should stop treating landing as a set of mostly static template views with some admin CRUD screens around it. Instead, it should become a structured content system with:
- pages
- sections
- blocks
- repeater items
- media assets
- SEO records
- publish snapshots
- revisions
- tracked lead/conversion events

The design principle is simple:
- admin users edit structured content
- public templates render only approved marketing content
- technical keys stay internal
- customer-facing UI stays business-first

Estimated outcome after V2:
- admin can run the marketing site without code edits for day-to-day changes
- page, content, SEO, media, lead, and publish operations become manageable in the landlord admin
- Template 1 and future templates consume the same content model rather than owning their own hard-coded copy

---

## 2. Target Architecture

```text
KT Landing CMS
├─ Dashboard
│  ├─ Publish health
│  ├─ SEO health
│  ├─ Draft changes
│  ├─ Latest leads
│  ├─ Traffic snapshot
│  └─ Broken links
├─ Website Builder
│  ├─ Pages
│  ├─ Sections
│  ├─ Blocks
│  ├─ Repeater items
│  ├─ Navigation menus
│  └─ CTA registry
├─ Media Center
│  ├─ Upload
│  ├─ Library
│  ├─ Folders
│  ├─ Optimization
│  └─ Usage tracking
├─ Content Hub
│  ├─ Blog
│  ├─ Case studies
│  ├─ FAQs
│  └─ Resources
├─ SEO Center
│  ├─ Metadata
│  ├─ Schema
│  ├─ Sitemap
│  ├─ Redirects
│  ├─ Robots
│  └─ 404 / broken links
├─ Leads & Conversion Center
│  ├─ Forms
│  ├─ CTA tracking
│  ├─ UTM attribution
│  ├─ Campaign attribution
│  └─ Funnel reporting
├─ Analytics Center
│  ├─ GA4
│  ├─ GTM
│  ├─ Pixels
│  └─ Event reporting
├─ Publish Center
│  ├─ Drafts
│  ├─ Preview
│  ├─ Publish now
│  ├─ Schedule publish
│  ├─ Rollback
│  └─ Version history
└─ Settings
   ├─ Theme/branding
   ├─ General CMS config
   ├─ Permissions
   └─ Integrations
```

### Module purpose

| Module | Purpose | Data | Permissions | Relationship |
|---|---|---|---|---|
| Dashboard | Operational landing CMS home | aggregates from pages, leads, SEO, analytics, publish jobs | view | top-level summary of the whole system |
| Website Builder | Page/section/block authoring | page, section, block, item tables | content editor, designer, marketing admin | core authoring surface |
| Media Center | Asset management | media table + usage graph | upload, delete, replace, optimize | used by pages, sections, blog, SEO |
| Content Hub | Long-form content management | blog/case study/FAQ/resource tables | editor, SEO manager | feeds pages and SEO |
| SEO Center | Search and technical SEO | meta, schema, redirect, robots, sitemap | SEO manager | influences every published page |
| Leads & Conversion | Lead capture and funnel data | leads, UTM, events | marketing admin, sales ops | connected to forms and CTAs |
| Analytics | Measurement/reporting | event logs, daily rollups, connector config | marketing admin, SEO manager | reads page/lead event stream |
| Publish Center | Release control | snapshots, revisions, jobs | publisher, admin | controls deploy lifecycle |
| Settings | Global CMS config | settings + theme config | super admin, marketing admin | base system configuration |

---

## 3. Website Builder

### Target model

```text
Page
└─ Sections
   └─ Blocks
      └─ Items
```

### Entity relationships

| Entity | Meaning | Parent | Children | Notes |
|---|---|---|---|---|
| Page | A public URL or route | none | sections | owns slug, template, SEO, publish state |
| Section | A logical page band | page | blocks/items | ordered, toggleable, variant-aware |
| Block | Reusable structural unit | section or page | items | useful for hero, pricing, CTA, FAQ, etc. |
| Item | Repeater row / card / metric / FAQ entry | block or section | none | atomic editable content |

### Page example

```text
Home Page
└─ Hero Section
   ├─ Headline block
   ├─ CTA card block
   └─ Button item
```

### Recommended JSON structure

```json
{
  "page": {
    "slug": "home",
    "template_code": "fastwork_inspired",
    "status": "draft",
    "seo": {
      "title": "CRM Khách Tốt",
      "description": "CRM cho doanh nghiệp"
    }
  },
  "sections": [
    {
      "key": "hero",
      "variant": "split-dashboard",
      "visible": true,
      "sort_order": 1,
      "content": {
        "title": "CRM cho doanh nghiệp",
        "subtitle": "Chuẩn hóa bán hàng và vận hành",
        "cta": [
          {"label": "Dùng thử miễn phí", "url": "/signup"},
          {"label": "Đặt lịch demo", "url": "/contact"}
        ]
      },
      "media": {
        "hero_image": "media://123"
      },
      "items": [
        {
          "key": "trust_metric_1",
          "title": "500+",
          "subtitle": "Doanh nghiệp đang vận hành"
        }
      ]
    }
  ]
}
```

### Builder capabilities
- create page
- clone page
- reorder sections by drag/drop
- toggle section visibility
- edit section text
- edit CTA label and link
- change image and icon
- edit cards, metrics, FAQ, app lists
- preview realtime
- publish

### Builder priority
1. page shell and section ordering
2. section content editing
3. repeater editing
4. media binding
5. preview/publish workflow

---

## 4. Section Library

Recommended reusable sections:
- Hero
- Trust Metrics
- Comparison Table
- FAQ
- Pricing
- Case Study
- Marketplace
- CTA
- Footer

| Section | Editable fields | Repeater items | Media bindings | SEO impact |
|---|---|---|---|---|
| Hero | title, subtitle, CTA, background, badge | stats, benefits, trust chips | hero image, background image, icon | high |
| Trust Metrics | headline, subtitle, layout variant | metrics cards | icons, logos | medium |
| Comparison Table | title, intro, row labels, footer CTA | rows, columns, badges | optional branding assets | medium |
| FAQ | title, subtitle, accordion style | questions/answers | optional icon | medium |
| Pricing | title, billing note, CTA, visibility | plan cards, bullets, badges | plan images/app logos | high |
| Case Study | headline, summary, proof copy | proof cards, KPI cards | logo, screenshot, author avatar | medium |
| Marketplace | heading, category tabs, CTA | app cards, benefits, badges | app icon, screenshot | medium |
| CTA | title, subtitle, button, background | trust chips, secondary links | background image/video | medium |
| Footer | columns, legal text, CTA | link groups | logo, social icons | low |

### Section rules
- every section has an internal key
- every section has a public title and admin description
- admin description is never rendered to the frontend
- every section can be toggled on/off
- every section supports at least one layout variant

---

## 5. Media Center

### Supported types
- image
- SVG
- WebP
- AVIF
- video
- document

### Required metadata
- alt
- title
- caption
- tags
- category
- usage count

### Upload flow
1. upload file
2. validate type, size, dimensions
3. store original
4. generate optimized variants if needed
5. attach metadata
6. record references
7. prevent delete if in use

### Storage structure
- logical folders by content type
- stable asset IDs for replacement without broken links
- CDN-ready file path or URL layer

### Optimization flow
- image compression
- responsive derivatives
- lazy-loading defaults
- focal point support
- safe SVG whitelist

---

## 6. Content Hub

Recommended scope:
- Blog
- Case Studies
- FAQs
- Resources

### Content hub model
- categories
- tags
- author
- status
- schedule
- SEO fields
- related content

### Recommended decision
- keep content hub inside the landing module if the scope is strictly marketing and website content
- split into a separate content module only if the team wants the CMS to manage multiple content properties beyond landing

### Editor strategy
- block editor is better than plain WYSIWYG for this system
- markdown support is optional, not mandatory
- import/export becomes useful only when editorial scale grows

---

## 7. SEO Center

### Core SEO surfaces
- meta title
- meta description
- schema
- sitemap
- redirects
- robots
- canonical
- 404 monitor
- broken link checker
- SEO audit

### SEO score engine

Score range: 0 to 100

Example checks:
- missing title
- missing H1
- missing schema
- missing image alt
- duplicate meta description
- missing canonical
- too long title/description
- broken link in page
- page not included in sitemap

### SEO object model
- page-level metadata
- section-level optional schema signals
- content hub metadata
- redirect mapping
- canonical policy

### SEO center output
- page SEO score
- issues list
- recommended fix list
- publish blocker when critical issues exist

---

## 8. Leads & Conversion Center

### Funnel model

```text
Visit
↓
CTA
↓
Lead
↓
Signup
↓
Payment
```

### Tracked surfaces
- landing
- pricing
- blog
- case studies
- marketplace

### Required event dimensions
- page
- section
- CTA key
- plan
- source
- campaign
- referrer
- UTM

### Conversion center responsibilities
- lead forms
- CTA tracking
- UTM tracking
- campaign attribution
- funnel reporting
- duplicate detection
- CRM lead creation

---

## 9. Analytics Center

### Integrations
- GA4
- GTM
- Meta Pixel
- TikTok Pixel
- LinkedIn Insight Tag

### Metric families
- page views
- conversions
- CTA performance
- traffic source
- campaign ROI

### Event pipeline
1. capture frontend event
2. normalize event name
3. attach attribution data
4. send to analytics provider
5. store summary in CMS analytics tables

### Recommended event naming
- `page_view`
- `cta_click`
- `plan_selected`
- `signup_start`
- `signup_submit`
- `lead_submit`
- `checkout_start`
- `payment_success`
- `demo_booking`

---

## 10. Publish Center

### Required states
- Draft
- Preview
- Publish
- Schedule
- Rollback
- Version history
- Diff viewer

### Admin should know
- who changed what
- when it changed
- which snapshot is active
- whether rollback is safe

### Publish lifecycle
1. edit draft
2. validate content
3. preview
4. publish now or schedule
5. store snapshot
6. clear caches
7. expose active version

### Snapshot should include
- page config
- sections
- blocks
- media references
- SEO
- menus
- theme settings
- pricing overrides

---

## 11. Permissions

| Role | Permissions |
|---|---|
| Super Admin | full access, publish, rollback, redirects, custom CSS/JS, permissions |
| Marketing Admin | content, pages, sections, publish, leads, analytics |
| SEO Manager | SEO center, redirects, schema, sitemap, audit, publish validation |
| Content Editor | pages, sections, blog, FAQs, media usage, draft edit |
| Designer | theme, layout variants, media, section styling, preview |
| Viewer | read-only dashboard and preview |

### Capability groups
- edit content
- edit SEO
- upload media
- publish
- rollback
- edit tracking code
- edit custom CSS/JS
- manage redirects
- delete content

---

## 12. Database Roadmap

### New tables recommended
- `kt_landing_templates`
- `kt_landing_pages`
- `kt_landing_sections`
- `kt_landing_blocks`
- `kt_landing_section_items`
- `kt_landing_media`
- `kt_landing_menus`
- `kt_landing_cta_registry`
- `kt_landing_marketplace_apps`
- `kt_landing_blog_categories`
- `kt_landing_blog_tags`
- `kt_landing_blog_posts`
- `kt_landing_seo_meta`
- `kt_landing_schema`
- `kt_landing_redirects`
- `kt_landing_revisions`
- `kt_landing_audit_log`

### Tables to keep
- `kt_landing_settings`
- `kt_landing_themes`
- `kt_landing_plan_overrides`
- `kt_landing_leads`
- `kt_landing_lead_activities`
- `kt_landing_publish_snapshots`
- `kt_landing_publish_jobs`
- `kt_landing_analytics_events`
- `kt_landing_analytics_daily`

### Tables to merge conceptually
- `kt_landing_sections` + `kt_landing_section_items` into the builder model
- `kt_landing_pages` + SEO meta into a page aggregate
- `kt_landing_menus` + CTA registry into nav/action config

### Tables that can be phased out later
- none immediately; keep current tables until V2 fully replaces the old rendering path

### Scope rules
- landlord-owned marketing content remains in landlord DB
- public frontend only consumes published snapshots or published records

---

## 13. Menu Restructure

Recommended V2 menu:

```text
KT Landing
├─ Dashboard
├─ Website Builder
├─ Media Center
├─ Content Hub
├─ SEO Center
├─ Leads & Conversion
├─ Analytics
├─ Publish Center
└─ Settings
```

### Menu decisions
- `Giao diện` and `Theme Customizer` should merge into `Website Builder` or `Settings`
- `Pages` and `Trang & Section` should merge into `Website Builder`
- `Add-ons` should become `Ứng dụng mở rộng`
- `Preview` should be a topbar action inside page/edit screens, not a standalone main menu item

### Suggested drop/merge list
- Keep: Dashboard, Website Builder, Media Center, Content Hub, SEO Center, Leads & Conversion, Analytics, Publish Center, Settings
- Merge: Giao diện + Theme Customizer
- Merge: Pages + Trang & Section
- Merge: Add-ons into Ứng dụng mở rộng
- Demote: Preview to action button

---

## 14. Phase Roadmap

### Phase 1 - Stabilization
Goal:
- remove Template 1 hard-code from critical public sections
- normalize page/section data flow
- stabilize preview/publish

Effort: medium
Risk: medium
DB impact: low to medium
Files impact: high
Acceptance:
- landing renders from CMS-backed content for key sections
- no visible admin placeholder text on frontend
- preview works against draft content

### Phase 2 - Website Builder
Goal:
- pages, sections, blocks, repeaters, drag/drop ordering, live preview

Effort: high
Risk: high
DB impact: medium to high
Files impact: high
Acceptance:
- admin can build and reorder pages without code

### Phase 3 - SEO Center
Goal:
- SEO metadata, schema, sitemap, redirects, broken link monitoring

Effort: medium
Risk: medium
DB impact: medium
Files impact: medium
Acceptance:
- all published pages can be audited and scored before publish

### Phase 4 - Content Hub
Goal:
- blog, case studies, FAQs, resources

Effort: medium
Risk: medium
DB impact: medium
Files impact: medium
Acceptance:
- editorial team can ship content without touching page code

### Phase 5 - Analytics
Goal:
- GA4/GTM/pixels, attribution, CTA tracking, funnel reporting

Effort: medium
Risk: medium
DB impact: low to medium
Files impact: medium
Acceptance:
- events are tracked consistently and tied to source/campaign/CTA

### Phase 6 - Enterprise Features
Goal:
- revisions, rollback, scheduled publish, roles, audit log, multi-template support

Effort: high
Risk: medium
DB impact: medium
Files impact: medium to high
Acceptance:
- marketing team can operate like a professional CMS team with rollback and governance

---

## 15. Competitor Benchmark

| Platform | Relative Strength | KT Landing Current | KT Landing V2 Target |
|---|---|---|---|
| Webflow | Visual builder, publishing, SEO, components | behind | partial convergence on page builder and publish |
| Framer | Fast design-led pages, simple publishing | behind | can match simple marketing page flow |
| WordPress + Elementor | Flexible content ops, huge ecosystem | behind on ecosystem, ahead on tight SaaS integration | competitive for core internal marketing CMS |
| HubSpot CMS | CRM + content + lead automation | behind in breadth | can get close in CRM-linked marketing workflow |
| Unbounce | Landing-page optimization | currently comparable only in narrow landing use case | stronger once builder + conversion tooling exist |
| Perfex CRM CMS plugins | Basic page/plugin surface | already ahead in product integration | V2 should remain stronger and more coherent |

### Position today
- KT Landing is more of a specialized SaaS marketing module than a full CMS platform.

### Position after V2
- KT Landing should be a credible enterprise-grade marketing CMS tightly integrated with the CRM product and billing model.

---

## 16. Final Recommendation

### 1. Ideal V2 architecture
Build KT Landing as a structured marketing CMS with:
- page builder
- section library
- media center
- content hub
- SEO center
- leads/conversion center
- analytics center
- publish/versioning center

### 2. Must-do features
- page builder with sections/blocks/items
- media center with usage tracking
- SEO center with metadata, redirects, sitemap, schema
- publish/preview/version/rollback
- lead capture and attribution
- pricing sync to CRM plans

### 3. Do later
- advanced blog workflow
- advanced schema editor
- enterprise audit log depth
- multi-template marketplace
- full block marketplace

### 4. Redundant features
- Theme Customizer as a separate menu
- Add-ons as a separate vague label
- Preview as its own main menu item
- duplicate workflow sections in landing templates

### 5. Shortest route to a professional CMS
1. stabilize content model and remove hard-code on public pages
2. build page/section editor and live preview
3. add SEO and publish controls
4. add content hub and media depth
5. wire analytics and leads attribution
6. finish with revisions, rollback, roles, and scheduled publishing

## Estimated Completion Level After V2

If V2 is implemented as designed:
- KT Landing will reach roughly **85-90% of a professional internal CMS target** for a SaaS marketing site
- it will be strong enough to replace most day-to-day landing operations without code edits
- it will still be below Webflow/HubSpot in ecosystem breadth, but stronger in product integration and CRM-native workflow

