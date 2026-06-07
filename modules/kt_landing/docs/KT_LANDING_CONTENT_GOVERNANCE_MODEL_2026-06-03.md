# CONTENT GOVERNANCE REPORT

Scope:
- Content governance model for KT Landing before Wave 1.
- No code changes.
- No migration.
- No DB changes.

## 1. Ownership Matrix

| Content Type | Owner | Source of Truth | Override Allowed? | Where Edited? | Where Rendered? |
|---|---|---|---|---|---|
| Landing hero | Landing CMS | Landing sections / page config | Yes, full | Landing admin page builder / section editor | Public landing template |
| Pricing | CRM Plan Engine | `kt_saas_plans` | Yes, marketing badge/title/best-for/CTA only | Pricing admin sync screen | Public pricing, signup, tenant subscription surfaces |
| Marketplace | Marketplace Catalog | Marketplace registry / add-on catalog | Yes, marketing description only | Marketplace/add-on admin | Public landing marketplace section, signup add-on selection |
| FAQ | Global Blocks / Content Hub | Global block registry or FAQ content records | Yes, copy and ordering via block/content owner | Global blocks / FAQ editor | Public landing, blog, help pages |
| CTA | Global Blocks | Global block registry / CTA registry | Yes, label/url/variant only | Global blocks / CTA registry | Public landing, pricing, blog, case studies, footer |
| Trust Metrics | Global Blocks | Global block registry | Yes, number text and label, but controlled | Global blocks dashboard | Public landing, pricing, case studies |
| Blog | Content Hub | Blog post records | Yes, editorial copy only | Blog editor | Public blog pages, related content blocks |
| SEO | SEO Center | Page SEO records + schema/redirect metadata | Limited, per-page meta and schema only | SEO center | Public page head, sitemap, schema output |
| Case Studies | Content Hub | Case study records | Yes, editorial copy and proof fields | Case study editor | Landing case study sections, blog, resources |

## 2. Source Of Truth Matrix

| Content Type | Source Of Truth | Notes |
|---|---|---|
| Landing hero | Landing sections/page records | Landing CMS owns hero copy, assets, and layout variant |
| Pricing | `kt_saas_plans` | Actual price, billing cycle, and setup fee must come from CRM plans |
| Marketplace | Marketplace registry | App name and availability come from catalog; marketing text may override |
| FAQ | Global blocks or FAQ content hub | Reusable FAQ should be centrally managed |
| CTA | Global blocks / CTA registry | Shared CTA text and link should not be duplicated in templates |
| Trust Metrics | Global blocks | One source for trust counters and proof chips |
| Blog | Blog post table | Editorial content record is the source |
| SEO | SEO meta records | Page-level SEO fields should be centralized |
| Case Studies | Case study records | Proof data and narrative should be centrally managed |

## 3. Allowed Overrides

| Content Type | Allowed Overrides |
|---|---|
| Landing hero | full override of title, subtitle, CTA, image, variant, order |
| Pricing | marketing title, best_for, badge, CTA text/link, display order, visibility |
| Marketplace | marketing description, CTA text/link, ordering, featured badge |
| FAQ | question wording, answer wording, ordering, visibility |
| CTA | label, URL, styling variant, tracking key, visibility |
| Trust Metrics | label text, proof wording, display order, icon |
| Blog | excerpt, hero image, CTA block placement, related posts |
| SEO | meta title, meta description, canonical, robots, schema type |
| Case Studies | headline, subtitle, proof bullets, CTA, image/screenshot |

## 4. Forbidden Overrides

| Content Type | Forbidden Overrides |
|---|---|
| Pricing | actual price, setup fee, billing cycle, trial days, invoice/plan source fields |
| Marketplace | internal key, product availability source, linked catalog identity |
| FAQ | underlying knowledge ownership, schema ownership if auto-generated |
| CTA | internal tracking keys, source-of-truth route ownership |
| Trust Metrics | hidden business truth such as actual totals if not approved by data owner |
| SEO | canonical ownership if tied to routing policy; redirect rules if managed centrally |
| Blog | published slug after publish without revision flow |
| Case Studies | proof metrics if those are sourced from operational data without approval |

## 5. Recommended Governance Model

### Principle 1 - Domain owner owns the truth
Each content domain must have exactly one source-of-truth owner:
- pricing is owned by CRM plan engine
- landing hero is owned by Landing CMS
- CTA is owned by Global Blocks / CTA Registry
- blog and case studies are owned by Content Hub
- SEO is owned by SEO Center

### Principle 2 - Marketing can override presentation, not operational truth
Marketing can change:
- wording
- badge text
- CTA label
- ordering
- visibility
- copy tone

Marketing cannot change:
- actual billing price
- billing cycle
- setup fee
- source plan identity
- internal catalog keys
- canonical routing policy

### Principle 3 - Reusable elements must be centrally controlled
CTA, FAQ, trust metrics, and footer-like shared content should be block-based or registry-based, not duplicated per page.

### Principle 4 - Page content and platform truth are different layers
Landing pages consume data. They should not become the owner of the truth for pricing, marketplace identity, or SEO routing policy.

### Principle 5 - Overrides must be explicit
Any override must be visible in admin and ideally marked as:
- inherited
- overridden
- locked
- synced

## 6. Wave 1 Dependencies

Wave 1 features depend on the governance model above:

### Global Block System
Depends on:
- CTA owner definition
- FAQ ownership definition
- Trust metrics ownership definition

### Pricing Sync Hardening
Depends on:
- pricing source-of-truth ownership
- explicit list of allowed and forbidden overrides
- sync state model (`synced`, `warning`, `mismatch`)

### Landing Clone Engine
Depends on:
- clear ownership of sections versus reusable blocks
- template vs page content boundary
- permission to clone published content without cloning analytics/leads/history

### Media Center Upgrade
Depends on:
- clear asset ownership and usage graph
- which content types are allowed to bind media

### Publish Center Upgrade
Depends on:
- content ownership and override policy
- which sources are authoritative during snapshot creation

## 7. Final Recommendation

The governance model should be enforced as a rule system in the admin UI and publish checks:
- ownership is defined once
- overrides are only allowed where explicitly listed
- operational truth stays with CRM, marketplace registry, or content hub owners
- landing templates consume governed content rather than owning it

