# KT LANDING CMS IMPLEMENTATION PRIORITIZATION REPORT

Scope:
- Prioritize the V2 + V2.5 roadmap for KT Landing CMS.
- No code changes.
- No DB changes.
- No migrations.
- Goal is to maximize value, minimize regression, and minimize unnecessary technical debt.

## 1. Executive Summary

The CMS should not be built as a broad set of features in parallel. The right approach is to ship the smallest set of capabilities that unlocks operational ownership for the landlord team, then layer in SEO/content governance, then conversion optimization, then enterprise governance, then multi-domain expansion.

The 80/20 answer is:
- first: structured page/section control, media, publish, pricing sync, and leads
- second: SEO center and content hub
- third: A/B testing and conversion analytics
- fourth: approvals, revision compare, and multi-domain governance
- fifth: marketplace, AI SEO, and advanced experimentation

In practical terms:
- the first release should let marketing operate the landing without code
- later releases should let marketing manage SEO/content and conversion
- only after traffic exists should advanced governance and multi-domain features be added

---

## 2. Feature Inventory

| Feature | Business Value | Marketing Value | SEO Value | Complexity | DB Impact | Frontend Impact | Risk | Maintenance Cost | Priority |
|---|---|---:|---:|---:|---:|---:|---:|---:|---|
| Website Builder | Very High | Very High | High | High | High | High | High | High | P0 |
| Section Builder | Very High | Very High | Medium | High | Medium | High | High | Medium | P0 |
| Publish Center | Very High | High | Medium | Medium | Medium | Medium | Medium | Medium | P0 |
| Media Center | Very High | High | High | Medium | Medium | High | Medium | Medium | P0 |
| Pricing Sync | Very High | Very High | Medium | Medium | Low | High | Medium | Low | P0 |
| Leads | Very High | Very High | Low | Medium | Medium | Medium | Medium | Medium | P0 |
| SEO Center | Very High | High | Very High | High | High | Medium | High | High | P1 |
| Blog | High | High | Very High | Medium | Medium | Medium | Medium | Medium | P1 |
| Case Study | High | High | High | Medium | Medium | Medium | Medium | Medium | P1 |
| Redirect Manager | High | Medium | Very High | Medium | Medium | Low | Medium | Medium | P1 |
| Schema Builder | High | Medium | Very High | Medium | Medium | Low | Medium | Medium | P1 |
| Versioning | Very High | Medium | Medium | High | High | Medium | Medium | High | P1 |
| Revision Compare | High | Medium | Medium | High | Medium | Medium | Medium | Medium | P2 |
| Global Blocks | High | High | Medium | High | Medium | High | Medium | Medium | P1 |
| Form Builder | Very High | Very High | Low | High | High | Medium | High | High | P0 |
| CTA Registry | High | Very High | Low | Medium | Medium | Medium | Medium | Medium | P0 |
| Analytics Center | High | Very High | Medium | Medium | Medium | Low | Medium | Medium | P1 |
| A/B Testing | High | Very High | Low | High | High | Medium | High | High | P2 |
| Heatmap | Medium | High | Low | Low | Low | Low | Low | Low | P3 |
| Multi-domain | High | High | High | High | High | Medium | High | High | P2 |
| Multi-language | High | Medium | High | High | High | Medium | High | High | P2 |
| Approval Workflow | High | Medium | Low | High | Medium | Medium | Medium | Medium | P2 |
| AI SEO Assistant | Medium | High | High | Medium | Low | Low | Medium | Medium | P3 |
| Content Calendar | Medium | High | Medium | Medium | Low | Low | Low | Low | P2 |
| Landing Clone Engine | High | High | Low | Medium | Low | Medium | Low | Low | P1 |
| Block Builder | High | Very High | Medium | High | Medium | High | High | Medium | P1 |

### Notes on the inventory
- `Website Builder`, `Section Builder`, `Publish Center`, `Media Center`, `Pricing Sync`, `Leads`, `Form Builder`, and `CTA Registry` are the core operating system.
- `SEO Center`, `Blog`, `Case Study`, `Redirect Manager`, `Schema Builder`, and `Versioning` are the next most important because they make the platform sustainable in production.
- `A/B Testing`, `Multi-domain`, `Multi-language`, and `Approval Workflow` are valuable, but not required to start generating business value.
- `Heatmap` and `AI SEO Assistant` are helpful, but they are not core platform unlocks.

---

## 3. Pareto Analysis

### P0 - Must have
These are the features that create most of the immediate value:
- Website Builder
- Section Builder
- Media Center
- Publish Center
- Pricing Sync
- Form Builder
- CTA Registry
- Leads

Why these first:
- they let the landlord team operate landing pages without code
- they remove the biggest source of hard-coded content
- they support conversion flow from visit to lead to signup
- they keep pricing aligned with CRM source of truth

### P1 - Very important
These features make the platform production-grade:
- SEO Center
- Blog
- Case Study
- Redirect Manager
- Schema Builder
- Versioning
- Global Blocks
- Analytics Center
- Landing Clone Engine
- Block Builder

Why:
- they make the system searchable, reusable, measurable, and safer to publish
- they reduce duplication
- they support marketing operations at a higher quality level

### P2 - Good to have later
- Revision Compare
- A/B Testing
- Multi-domain
- Multi-language
- Approval Workflow
- Content Calendar

Why later:
- they are meaningful, but they depend on a stronger core CMS foundation
- they add governance and optimization after the site is already operational

### P3 - Future
- Heatmap
- AI SEO Assistant

Why future:
- these are value-add layers, not first-order unlocks
- they are easiest to justify once traffic and content volume are meaningful

### 80/20 conclusion
Roughly 20% of the features produce most of the value:
- Website Builder
- Section Builder
- Media Center
- Publish Center
- Pricing Sync
- Form Builder
- CTA Registry
- Leads

That set alone gives the landlord team operational control over landing and conversion flows.

---

## 4. Recommended Build Order

### Wave 1 - Operational core
Goal:
- admin can operate landing without code

Build:
1. Website Builder
2. Section Builder
3. Media Center
4. Publish Center
5. Pricing Sync
6. Form Builder
7. CTA Registry
8. Leads

Why this order:
- it unlocks content ownership first
- it minimizes hard-code on the public landing
- it gives immediate marketing and conversion value

### Wave 2 - Marketing operations
Goal:
- marketing can operate SEO and content

Build:
1. SEO Center
2. Blog
3. Case Study
4. Redirect Manager
5. Schema Builder
6. Versioning
7. Global Blocks
8. Landing Clone Engine
9. Block Builder

Why this order:
- these features make the system searchable, reusable, and publish-safe
- they reduce duplication and content maintenance cost

### Wave 3 - Conversion optimization
Goal:
- increase conversion with measurement and experimentation

Build:
1. Analytics Center
2. A/B Testing
3. Content Calendar

Why this order:
- analytics first, experimentation second
- calendar helps coordinate campaigns after analytics exists

### Wave 4 - Enterprise governance
Goal:
- team workflow and change control

Build:
1. Approval Workflow
2. Revision Compare
3. Multi-language

Why this order:
- governance only matters after the platform is already usable
- revision compare becomes useful once publish and versioning are mature

### Wave 5 - Scale
Goal:
- run multiple marketing sites and advanced optimization

Build:
1. Multi-domain
2. Heatmap
3. AI SEO Assistant

Why this order:
- these are scaling and polish layers
- they should not block the first production CMS release

---

## 5. Database Impact

### Wave 1
New tables likely needed:
- pages/sections/blocks/items normalization tables if not already sufficient
- media usage tracking
- CTA registry
- form definitions and submissions
- event capture for leads

Tables to keep:
- current settings/themes/pages/sections/media/plan overrides/leads/publish tables

Risk:
- medium-high if builder and publish models are introduced too early

Rollback risk:
- medium

### Wave 2
New tables likely needed:
- SEO meta
- redirects
- schema
- blog categories/tags
- revision history
- block/global block tables

Tables to keep:
- current content tables remain as source during transition

Risk:
- medium

Rollback risk:
- medium

### Wave 3
New tables likely needed:
- analytics rollups
- test variants
- event assignments
- content calendar records

Risk:
- medium

Rollback risk:
- low to medium

### Wave 4
New tables likely needed:
- approval workflow states
- workflow actions
- compare snapshots

Risk:
- low to medium

Rollback risk:
- low

### Wave 5
New tables likely needed:
- site registry
- site-level branding/settings
- cross-site analytics isolation
- AI suggestion logs if retained

Risk:
- high

Rollback risk:
- medium-high

---

## 6. Frontend Impact

### Wave 1
Impact on current landing:
- high
- Template 1 should gradually stop hard-coding content and consume CMS content

Impact on SEO:
- indirect but important because content can now be edited safely

Impact on publish flow:
- major improvement because publish becomes meaningful

### Wave 2
Impact on current landing:
- moderate to high because SEO/content surfaces will affect page shape and metadata

Impact on Template 1:
- the template becomes a consumer of structured content instead of a content owner

Impact on publish flow:
- stronger preview/rollback confidence

### Wave 3
Impact on current landing:
- moderate

Impact on SEO:
- measurement and testing help optimize

Impact on publish flow:
- no major structural change, but more campaign coordination

### Wave 4
Impact on current landing:
- low to moderate

Impact on SEO:
- better quality control

Impact on publish flow:
- approval adds guardrails

### Wave 5
Impact on current landing:
- moderate

Impact on SEO:
- multi-domain and AI suggestions improve scaling and consistency

Impact on publish flow:
- governance becomes more important, but complexity also rises

---

## 7. Quick Wins

These are the best features to deliver quickly with high value and low risk:

| Feature | Effort | Value | Reason |
|---|---|---|---|
| Pricing Sync hardening | Low | High | keeps landing aligned with CRM plans |
| CTA Registry | Low | High | reduces CTA duplication and supports tracking |
| Media usage tracking | Low | High | prevents broken content and asset deletion issues |
| Publish snapshot visibility | Low | High | makes release operations safer |
| Lead source + UTM capture standardization | Low | High | improves attribution immediately |
| Menu restructuring | Low | Medium | reduces admin confusion |
| Theme Customizer merge into branding/settings | Low | Medium | removes redundant UI |
| Basic SEO score checklist | Low | High | catches obvious mistakes before publish |

### Best quick wins under 3 days
- CTA Registry
- Pricing Sync hardening
- Basic SEO score checklist
- Lead UTM capture normalization
- Media usage tracking view

---

## 8. MVP V2

If only five things can be built first, choose:
1. Website Builder
2. Section Builder
3. Media Center
4. Publish Center
5. Pricing Sync

### Why these five
- they unlock the biggest reduction in hard-code
- they let admin operate landing content without code
- they directly affect landing, signup, and pricing, which are the highest-value surfaces
- they minimize technical debt by centralizing content ownership

### What is intentionally excluded from MVP V2
- A/B testing
- heatmap
- AI SEO assistant
- multi-domain
- approval workflow
- advanced content calendar

Those can wait until the core content system is stable and generating traffic.

---

## 9. Post Soft Launch Roadmap

After soft launch, prioritize:
- SEO Center
- Blog
- Case Study
- Redirect Manager
- Schema Builder
- Global Blocks
- Analytics Center
- Versioning

Reason:
- once traffic exists, content quality and search performance matter more
- these features increase discoverability and reduce content maintenance cost

---

## 10. Long-term Roadmap

Later stage work:
- A/B testing
- Multi-language
- Multi-domain
- Approval Workflow
- Revision Compare
- Content Calendar
- Heatmap
- AI SEO Assistant

Reason:
- these are enterprise and scale features, not the first unlocking layer

---

## 11. Final Recommendation

### 1. Build right now
- Website Builder
- Section Builder
- Media Center
- Publish Center
- Pricing Sync

### 2. Build after soft launch
- SEO Center
- Blog
- Case Study
- Redirect Manager
- Schema Builder
- Global Blocks
- Analytics Center

### 3. Build after traffic exists
- A/B Testing
- Content Calendar
- Revision Compare
- Approval Workflow

### 4. Build when marketing team is separate
- Multi-language
- Multi-domain
- AI SEO Assistant
- stronger governance and workflow controls

### 5. Build when scaling multiple domains
- site registry
- site-level branding isolation
- multi-domain analytics isolation
- template marketplace / template import-export

## Estimated Completion Level After This Roadmap

If this prioritization is followed:
- KT Landing reaches the most valuable 20% first and captures most of the business value
- the platform becomes a practical internal CMS before it becomes a broad enterprise CMS
- technical debt stays controlled because high-complexity features are deferred until the core is proven

