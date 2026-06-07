# KT LANDING CMS V2.5 ENTERPRISE EXPANSION PLAN

Scope:
- Strategic expansion layer for KT Landing CMS V2.
- No code changes, no DB changes, no refactor in this pass.
- Designed to move the platform closer to Webflow / Framer / HubSpot CMS / Unbounce / Elementor while preserving CRM-native and billing-native strengths.

## 1. Executive Summary

V2 made KT Landing into a structured marketing CMS. V2.5 should add the enterprise growth layer:
- global reusable blocks
- template marketplace
- form builder
- A/B testing
- SEO intelligence
- AI-assisted SEO/content drafting
- content calendar
- multi-domain CMS
- revision compare
- approval workflow

The key architectural principle:
- V2.5 should not become a generic website builder that loses product integration.
- It should instead become a marketing platform tightly attached to CRM, billing, lead capture, conversion measurement, and publishing governance.

The strongest strategic position is:
- easier to operate than WordPress + Elementor
- more product-native than Webflow/Framer
- more lead/billing-aware than HubSpot CMS for this product line

---

## 2. Global Block System

### Concept

Global blocks are reusable content units that can be shared across pages and templates.

Examples:
- CTA Demo
- CTA Signup
- CTA Contact
- Footer
- FAQ CRM
- FAQ Hóa đơn điện tử
- Trust Metrics
- Marketplace CTA

### Behavior
- editing a block updates all pages that reference it
- blocks support versioning
- blocks support dependency graphs
- blocks can be locked, duplicated, or published as variants

### Data model concept
- block type
- block instance
- block version
- block usage reference
- dependency graph
- deletion protection

### Dependency rules
- if a block is referenced by live pages, deletion is blocked
- if a block is reused by more than one page, version updates require a diff/confirm step
- block usage graph must show impacted pages before publish

### Reusability matrix

| Block Type | Reusable? | Dependencies | Risk |
|---|---|---|---|
| CTA Demo | Yes | page, section, tracking event | medium |
| CTA Signup | Yes | page, form, tracking event | high |
| CTA Contact | Yes | page, lead form, routing | medium |
| Footer | Yes | site-wide menus, legal pages, branding | high |
| FAQ CRM | Yes | page, SEO schema, content hub | medium |
| FAQ Hóa đơn điện tử | Yes | page, schema, product positioning | medium |
| Trust Metrics | Yes | page, proof data, analytics snapshot | high |
| Marketplace CTA | Yes | page, app catalog, conversion tracking | medium |

### Why this matters
- reduces duplication
- creates consistent CTA behavior
- lets marketing edit one shared object instead of many page fragments

---

## 3. Template Marketplace

### Concept

Templates become distributable packages, not just theme codes.

Suggested templates:
- CRM Generic
- HVAC
- Nhà phân phối
- Dịch vụ
- Hóa đơn điện tử
- Webinar
- Lead Magnet

### Template lifecycle
- create
- clone
- duplicate
- import
- export
- publish

### Template architecture
Each template should have:
- manifest
- assets bundle
- section mapping
- block defaults
- variable map
- optional schema defaults
- style tokens

### Template manifest concept

```json
{
  "code": "crm_generic",
  "name": "CRM Generic",
  "version": "1.0.0",
  "sections": ["hero", "trust_metrics", "pricing", "faq"],
  "variables": {
    "brand_name": "CRM Khách Tốt",
    "primary_cta": "Dùng thử miễn phí"
  },
  "assets": {
    "preview_image": "media://..."
  }
}
```

### Template variables
- brand name
- CTA labels
- industry name
- proof counters
- pricing note
- hero image
- background style

### Why this matters
- enables fast vertical landing launches
- reduces template code forks
- supports future marketplace expansion

---

## 4. Form Builder

### Concept

Drag and drop form builder for all conversion surfaces.

Supported form types:
- Contact
- Demo Request
- Pricing Request
- Consultation Booking
- Download Resource

### Field types
- text
- email
- phone
- select
- checkbox
- radio
- textarea
- hidden field
- UTM capture

### Form integrations
- CRM Leads
- Email notifications
- Webhook
- Zapier
- Make

### Data model concept
- form definition
- form field definition
- form submission
- submission metadata
- integration mapping

### Priority forms
1. Demo request
2. Contact form
3. Pricing request
4. Consultation booking
5. Resource download

### Why this matters
- unlocks lead capture without code
- standardizes attribution
- makes CTA and form performance measurable across pages

---

## 5. A/B Testing Engine

### Concept

Test page variants, section variants, or CTA variants with traffic split and conversion tracking.

Examples:
- Hero A vs Hero B
- CTA A vs CTA B
- Pricing A vs Pricing B

### Core features
- traffic split
- conversion tracking
- winner detection
- optional auto-promote winner

### Tracking methods
- page assignment cookie
- event tracking by variant
- conversion goal mapping

### Test support matrix

| Test Type | Supported | Tracking Method |
|---|---|---|
| Hero variant | Yes | page assignment + CTA conversion |
| CTA variant | Yes | CTA event + form submit |
| Pricing variant | Yes | plan selection + checkout start |
| Full page variant | Yes | page-level assignment |

### Why this matters
- brings KT Landing closer to Unbounce / Webflow optimization loops
- allows marketing-driven experimentation instead of template guesses

---

## 6. Heatmap & Session Recording

### Strategic role

This layer is best treated as integration-driven, not as a custom recording engine.

Recommended integrations:
- Microsoft Clarity
- Hotjar
- Lucky Orange

### Data questions supported
- click map
- scroll depth
- rage clicks
- dead clicks
- session replay

### Architecture recommendation
- do not build session recording natively in V2.5
- build a connector layer and dashboard summary only
- keep privacy and consent controls centralized

### Why this matters
- gives behavior visibility without reinventing a complex analytics product

---

## 7. SEO Dashboard Pro

### Required dashboard metrics
- missing title
- missing H1
- missing schema
- missing alt
- duplicate title
- duplicate meta
- broken links
- orphan pages
- redirect issues
- sitemap status

### SEO Health Score
- range 0 to 100
- page-level score
- site-level score
- publish blockers for critical issues

### SEO score engine inputs
- metadata completeness
- heading structure
- schema presence
- image alt presence
- duplicate detection
- redirect integrity
- sitemap inclusion

### Why this matters
- turns SEO from a settings page into an operational dashboard
- allows admin to catch content issues before publish

---

## 8. AI SEO Assistant

### Concept

AI-assisted content planning and SEO drafting, not autonomous publishing.

Example prompt:
- user enters "CRM HVAC"
- assistant suggests:
  - meta title
  - meta description
  - FAQ ideas
  - internal links
  - schema type
  - CTA copy
  - keyword set

### Guardrails
- AI suggests, human approves
- AI never publishes directly
- AI output is stored as draft suggestions only

### Why this matters
- accelerates content production
- helps non-SEO admins produce structured landing content

---

## 9. Content Calendar

### Calendar surfaces
- blog schedule
- case study schedule
- landing schedule
- publish queue

### Content states
- draft
- review
- approved
- scheduled
- published

### Why this matters
- marketing teams need release planning, not just isolated edit screens

---

## 10. Multi-domain CMS

### Concept

One CMS managing multiple branded websites:
- khachtot.com
- crmhvac.vn
- crmphanphoi.vn
- crmservice.vn

### Per-site isolation
- branding
- pages
- SEO
- analytics

### Shared platform layers
- content model
- media store
- reusable blocks
- lead conversion
- publish engine

### Recommendation
- introduce site scope as a first-class dimension
- every page, block, SEO record, and analytics event should know its site

### Why this matters
- turns KT Landing into a multi-brand marketing platform
- still keeps one control plane

---

## 11. Revision Compare

### Function

Version Compare should behave like content diff:
- text changes
- SEO changes
- image changes
- CTA changes
- section changes

### Comparison output
- what changed
- where it changed
- who changed it
- when it changed

### Why this matters
- rollback is useful only if admins can inspect differences first

---

## 12. Enterprise Approval Workflow

### Workflow

```text
Draft
↓
Review
↓
Approved
↓
Publish
↓
Rollback if needed
```

### Roles
- Editor
- Reviewer
- Publisher

### Governance rules
- editor can draft and submit
- reviewer can approve or request changes
- publisher can publish approved content
- rollback should be restricted to higher privilege roles

### Why this matters
- makes the CMS safe enough for larger teams

---

## 13. Competitor Gap Analysis

| Platform | Strength | KT Landing V2 | KT Landing V2.5 target |
|---|---|---|---|
| Webflow | design-first CMS | behind in visual builder breadth | narrower but stronger in CRM integration |
| Framer | speed, visual iteration | behind in builder polish | competitive for marketing pages, less broad |
| HubSpot CMS | CRM + marketing automation | behind in ecosystem | closer in lead/conversion workflow |
| Unbounce | landing optimization | behind in testing depth | competitive once A/B and forms land |
| Elementor | flexible page building | behind in ecosystem breadth | better integrated with business logic |

### Strategic conclusion
- V2.5 should not try to beat every competitor on breadth.
- It should be the best option for businesses that need:
  - CRM-native landing
  - billing-native conversion
  - lead-native tracking
  - product-aware marketing content

---

## 14. Must Have vs Nice To Have

### Must Have
- global block system
- form builder
- SEO dashboard
- publish/version/rollback
- multi-site awareness
- content compare
- approval workflow

### Should Have
- template marketplace
- A/B testing
- content calendar
- AI SEO assistant
- heatmap/session integrations

### Nice To Have
- block marketplace
- advanced template import/export ecosystem
- auto-promote winning variants
- deep editorial workflow analytics

### Not recommended as first wave
- building a native heatmap engine
- building a native session recording engine
- fully autonomous AI publishing

---

## 15. Implementation Waves

### Wave 1
Global Blocks, Form Builder, SEO Dashboard

- Effort: high
- Risk: medium
- DB impact: medium to high
- Frontend impact: medium
- Admin UX impact: high

Why first:
- these are the highest leverage enterprise controls
- they immediately reduce duplication, improve conversion capture, and improve publish safety

### Wave 2
Template Marketplace, A/B Testing

- Effort: medium to high
- Risk: medium
- DB impact: medium
- Frontend impact: medium
- Admin UX impact: medium to high

Why second:
- unlocks speed of launch and experimentation

### Wave 3
Multi-domain, AI SEO, Enterprise Workflow

- Effort: high
- Risk: medium
- DB impact: medium to high
- Frontend impact: medium
- Admin UX impact: high

Why third:
- these are platform expansion and governance layers after the core system is stable

---

## 16. Recommended Next Build Order

1. Global Block System
2. Form Builder
3. SEO Dashboard Pro
4. Publish diff/version compare
5. Template Marketplace
6. A/B Testing Engine
7. Multi-domain CMS
8. AI SEO Assistant
9. Content Calendar
10. Enterprise approval workflow

This sequence keeps the platform practical and avoids overbuilding analytics or AI before the content system is strong.

---

## 17. Estimated Maturity After V2.5

If V2.5 is delivered as designed:
- KT Landing becomes a serious enterprise marketing platform for a CRM-native SaaS business
- it will still not be as broad as WordPress + Elementor or as polished as Webflow at ecosystem level
- but it will be stronger for this company’s actual operating model:
  - CRM-led acquisition
  - billing-aware landing flows
  - lead-native conversion tracking
  - product-aware marketing pages

Estimated maturity:
- V2: 85-90% of a serious internal marketing CMS
- V2.5: 92-95% of an enterprise marketing platform for this product line

