# KT LANDING CONTENT INHERITANCE MODEL

Scope:
- Define who owns each content class, what can be overridden, what is locked, and what wins at publish time.
- No code changes.
- No migration.
- No DB changes.

## 1. Content Classification

| Content | Type | Owner |
|---|---|---|
| Hero | Owned | Landing CMS |
| Pricing Price | Inherited + Locked | CRM Plan Engine |
| Pricing Badge | Inherited + Overridable | CRM Plan Engine + Landing CMS |
| Pricing Best For | Inherited + Overridable | CRM Plan Engine + Landing CMS |
| Pricing CTA | Inherited + Overridable | CRM Plan Engine + Landing CMS |
| Marketplace Description | Inherited + Overridable | Marketplace Registry + Landing CMS |
| Marketplace Internal Key | Inherited + Locked | Marketplace Registry |
| FAQ Content | Owned or Inherited depending on reuse | Global Blocks / Content Hub |
| CTA Content | Inherited + Overridable | Global Blocks / CTA Registry |
| Trust Metrics | Inherited + Overridable | Global Blocks |
| Blog Post | Owned | Content Hub |
| Case Study | Owned | Content Hub |
| SEO Meta | Inherited + Overridable | SEO Center |
| SEO Canonical | Locked | SEO Center / routing policy |
| Menu Labels | Inherited + Overridable | Menu Registry |
| Media Asset | Owned | Media Center |
| Form Definition | Owned | Form Builder |

## 2. Ownership Matrix

| Content | Owner | Source Of Truth |
|---|---|---|
| Hero | Landing CMS | Landing sections / page records |
| Pricing | CRM Plan Engine | `kt_saas_plans` |
| Marketplace | Marketplace Registry | Marketplace catalog / add-on registry |
| FAQ | Global Blocks / Content Hub | Global block registry or FAQ records |
| CTA | Global Blocks / CTA Registry | CTA registry / reusable blocks |
| Trust Metrics | Global Blocks | global reusable block registry |
| Blog | Content Hub | blog post records |
| Case Study | Content Hub | case study records |
| SEO | SEO Center | SEO meta / schema / redirect records |
| Menu | Navigation Registry | menu records |
| Media | Media Center | media asset records |
| Forms | Form Builder | form definition records |

## 3. Inheritance Matrix

| Content | Inherited From | Editable | Override | Locked |
|---|---|---:|---|---|
| Hero | Landing CMS template/page config | Yes | Full | None by default |
| Pricing | `kt_saas_plans` | No for core billing fields | Badge, CTA, Best For, display name, order, visibility | price, setup fee, billing cycle, trial days, plan_code |
| Marketplace | Marketplace Registry | Partial | marketing_title, marketing_description, icon, CTA, featured | internal_key, availability, linked_product |
| FAQ | Global Blocks / Content Hub | Yes | question wording, answer wording, order, visibility | underlying truth if centrally managed |
| CTA | Global Blocks / CTA Registry | Yes | label, URL, style variant, tracking key | route ownership, internal tracking policy |
| Trust Metrics | Global Blocks | Yes | label, proof wording, order, icon | actual business truth if not approved |
| Blog | Content Hub | Yes | excerpt, hero image, CTA blocks, related posts | published slug after publish |
| Case Study | Content Hub | Yes | headline, summary, proof bullets, CTA, image | proof numbers if sourced from ops without approval |
| SEO Meta | SEO Center | Yes, per page | meta title, meta description, schema, robots | canonical, routing policy |
| Menu | Menu Registry | Yes | label, url, target, order, visibility | published route policy |
| Media | Media Center | Yes | alt, title, caption, tags, category | asset ID / reference key |
| Forms | Form Builder | Yes | fields, labels, submit CTA, integrations | source event contracts |

## 4. Pricing Inheritance

### Source
- `kt_saas_plans`

### Locked fields
- `price`
- `setup_fee`
- `billing_cycle`
- `trial_days`
- `plan_code`

### Overridable fields
- `display_name`
- `badge`
- `best_for`
- `CTA`
- `marketing_description`
- `display_order`
- `visibility`

### Precedence
1. CRM plan engine owns billing truth.
2. Landing pricing override can only affect presentation.
3. Publish snapshot records the resolved result for the page.

### Rule
- if landing override conflicts with plan truth, the plan truth wins for locked fields
- admin must see a mismatch warning instead of being able to overwrite billing truth

## 5. Marketplace Inheritance

### Source
- Marketplace Registry

### Locked fields
- `internal_key`
- `availability`
- `linked_product`

### Overridable fields
- `marketing_title`
- `marketing_description`
- `icon`
- `CTA`
- `featured`

### Rule
- landing can market the application differently
- landing cannot redefine what the application is or whether it exists

## 6. Global Block Inheritance

### Block types
- CTA
- FAQ
- Footer
- Trust Metrics
- Marketplace CTA
- Contact CTA
- Demo CTA

### Inheritance behavior
- a block has one owner and can be referenced by many pages
- pages inherit block content by reference
- if a page needs a different version, it uses a block override or block variant, not a silent copy

### Override risk
- overriding a shared block affects all pages that reference it
- for this reason, shared blocks must support explicit versioning and preview before publish

### Example
- Footer block used by Landing A, Landing B, Landing C
- changing the block updates all three only after publish
- draft changes should not leak to public pages until published

## 7. SEO Inheritance

### Source
- SEO Center

### Locked
- canonical
- routing policy

### Overridable
- meta title
- meta description
- schema

### Rule
- SEO is page-owned but center-governed
- landing pages can customize metadata within policy
- canonical and route policy remain centralized

## 8. Media Inheritance

### Source
- Media Center

### Ownership
- an image is owned by the media library, not by the page
- a page, blog post, case study, or global block can reference the asset

### Implications
- if an asset changes, all references should update safely
- if the asset is in use, deletion is blocked
- replacement should preserve references through a stable asset identity

## 9. Clone Rules

### Clone Landing should clone
- pages
- sections
- SEO metadata
- menus
- global block references

### Clone Landing should not clone
- analytics
- leads
- publish history

### Global block reference rule
- clone reference, not raw content, unless the clone is explicitly asked to fork the block

### Why
- keeps content reuse intact
- avoids unnecessary duplication
- preserves governance

## 10. Publish Precedence Rules

### Precedence order
1. CRM Plan Engine for locked pricing truth
2. Marketplace Registry for product truth
3. SEO Center for canonical/routing policy
4. Global Blocks / Content Hub for reusable marketing content
5. Page-level landing overrides for presentation
6. Draft snapshot for the current unpublished working set

### Publish rule
- if a landing override conflicts with locked truth, the locked truth wins
- if a block version is unpublished, it does not replace the live block
- publish snapshot stores the resolved result after precedence is applied

### Example
CRM Plan -> Landing Override -> Publish Snapshot

What wins?
- price: CRM Plan
- badge: Landing Override if allowed
- CTA: Landing Override if allowed
- billing cycle: CRM Plan

## 11. Admin UX Rules

Admin should see the following states:
- Inherited
- Overridden
- Locked
- Synced
- Mismatch

### Example labels
- Price: 🔒 CRM Source
- Badge: ✏ Editable
- Best For: ✏ Editable
- Setup Fee: 🔒 CRM Source

### UI requirement
- admin must see at a glance what is safe to edit
- admin must see what is synced from source of truth
- admin must see what is locked and cannot be changed in landing

## 12. Governance Enforcement

### Validation rules
- block saving locked fields from landing overrides
- block publishing if critical mismatch exists
- block deletion of shared/global content in use
- block cloning of analytics/leads/publish history unless explicitly requested

### Publish checks
- pricing sync status
- media usage safety
- SEO required fields
- canonical policy
- missing CTA warnings
- block dependency impact

### Audit checks
- log when a content override is changed
- log when a block is published
- log when a pricing mismatch is detected
- log when a page is cloned

### Hard rule
- marketing may edit presentation
- marketing may not edit business truth

## 13. Wave 1 Dependencies

Wave 1 can be built safely only if the inheritance model is enforced conceptually:
- Global Block System needs clear ownership and usage graph rules
- Pricing Sync Hardening needs locked pricing precedence
- Landing Clone Engine needs clone-vs-reference rules
- Media Center Upgrade needs asset ownership and delete protection
- Publish Center Upgrade needs precedence and snapshot rules

## 14. Ready To Build Wave 1?

Yes, with one condition:
- Wave 1 must implement the above precedence rules from day one, even if only as admin validation and publish logic first

### Direct answers
1. Who owns the data?
   - the domain owner: CRM Plan Engine, Landing CMS, Marketplace Registry, Content Hub, SEO Center, Media Center, or Global Blocks

2. Who can edit the data?
   - only the owner domain and any explicitly allowed marketing override surface

3. What can be overridden?
   - presentation fields only: badge, CTA, best-for, description, ordering, visibility, metadata

4. What is locked?
   - pricing truth, setup fee, billing cycle, internal keys, canonical policy, availability, source product identity

5. What wins at publish time?
   - locked source of truth wins over landing override
   - published snapshot wins over draft
   - explicit owner policy wins over local template copy

6. Can Wave 1 be built safely?
   - yes, if the publish and validation layer enforces inheritance and precedence rules before the UI becomes more complex

