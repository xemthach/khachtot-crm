# KT LANDING CMS WAVE 1 IMPLEMENTATION REPORT

Scope:
- Wave 1 implementation plan for KT Landing CMS.
- No code changes in this pass.
- No DB changes in this pass.
- Designed to deliver immediate business value without risking the core product flows.

## 1. Executive Summary

Wave 1 should focus on the pieces that immediately improve marketing operations and reduce duplication without building the full Website/Section/Block builder yet:
- Global Block System
- Pricing Sync Hardening
- Landing Clone Engine
- Media Center Upgrade
- Publish Center Upgrade

This is the right cut because it:
- delivers value fast
- avoids the risk of a full page builder migration
- keeps Template 1 stable
- does not touch signup, billing, or provisioning behavior

---

## 2. Global Block System

### Goal
Eliminate duplication for reusable marketing content.

### Proposed table
- `kt_landing_global_blocks`
- `kt_landing_block_usage`

### Block types
- CTA
- FAQ
- Trust Metrics
- Footer
- Pricing Notes
- Marketplace CTA
- Contact CTA
- Demo CTA

### Required operations
- create
- edit
- duplicate
- disable
- preview

### Usage tracking
Block usage should record where each block is referenced:
- page
- section
- template
- optional block slot

### Delete protection
If a block is referenced by active content, deletion must be blocked.
Only disable or version it.

### Expected admin surface
- Global Blocks Dashboard
- block list
- usage list
- dependency count
- preview action
- duplicate action

### Value
- reduces CTA and FAQ duplication
- standardizes trust and footer content
- makes edits safer and more scalable

---

## 3. Pricing Sync Hardening

### Goal
Keep landing pricing aligned with CRM plans.

### Source of truth
- `kt_saas_plans`

### Allowed landing overrides
- marketing title
- best_for
- badge
- CTA
- display order
- visibility

### Disallowed landing overrides
- price
- setup fee
- billing cycle

### Sync behavior
- if CRM plan data changes, landing pricing should reflect it automatically
- landing only owns presentation and marketing copy

### Required status UI
- synced
- warning
- mismatch

### What admin should see
- plan name
- CRM source price
- landing marketing title
- landing badge
- landing CTA
- sync state

### Value
- avoids pricing drift
- prevents soft launch mistakes
- keeps marketing aligned with billing truth

---

## 4. Landing Clone Engine

### Goal
Create new vertical landing variants in minutes instead of days.

### Source
- Template 1

### Clone targets
- CRM HVAC
- CRM Nhà phân phối
- CRM Dịch vụ
- CRM Hóa đơn điện tử

### Clone scope
Clone:
- pages
- sections
- global block references
- SEO metadata
- menus

Do not clone:
- analytics
- leads
- publish history

### Wizard behavior
- choose source template
- choose clone target
- choose branding preset
- choose content profile
- preview before creating

### Value
- fast vertical launches
- better marketing productivity
- less manual copy/paste

---

## 5. Media Center Upgrade

### Goal
Turn the current media list into a usable asset manager.

### Metadata to add in the admin UX
- alt text
- title
- caption
- tags
- folder
- category

### Usage tracking
Media should show usage in:
- landing
- blog
- FAQ
- pricing
- marketplace

### Delete protection
If media is in use, do not allow delete.

### Optimization requirements
- WebP support
- image optimization
- responsive variants where appropriate

### Value
- avoids broken images
- improves SEO
- makes content operations safer

---

## 6. Publish Center Upgrade

### Goal
Make publishing safe and reversible.

### Core flow
- Draft
- Preview
- Publish
- Rollback

### Publish snapshot list
Show:
- version
- date
- author
- status

### Rollback
- restore the previous snapshot
- preserve snapshot history
- make rollback visible in the admin audit trail

### Publish checklist
Before publish, validate:
- SEO title present
- SEO description present
- broken links checked
- missing image alt checked
- missing CTA checked

### Value
- lowers release risk
- gives marketing safer operations
- creates a real release workflow

---

## 7. Database Changes

### New
- `kt_landing_global_blocks`
- `kt_landing_block_usage`

### Modified
- `kt_landing_media`
- `kt_landing_publish_snapshots`

### No breaking changes
- keep current tables intact
- do not remove current template data
- do not touch signup/billing/provisioning tables

### Risk profile
- low to medium if done incrementally
- high only if pricing sync or publish history are coupled too aggressively

---

## 8. Screenshots

Planned verification screenshots after implementation:
- Global Blocks Dashboard
- Pricing Sync Status
- Landing Clone Wizard
- Media Library with usage tracking
- Publish Snapshot List
- Publish Checklist

Current pass:
- no implementation screenshots yet, because this is a design/plan pass only

---

## 9. Regression Check

Wave 1 must not change:
- Template 1 rendering behavior
- landing signup flow
- billing logic
- checkout logic
- provisioning logic
- public pricing logic aside from synchronized presentation

Regression guardrails:
- no change to route shapes
- no change to CRM plan source of truth
- no change to lead capture behavior
- no change to invoice generation or payment capture

### Risk points
- pricing sync mismatch handling
- publish rollback snapshot scope
- media delete protection needing accurate usage graph
- clone engine accidentally importing analytics or leads

---

## 10. Ready For Wave 2?

Yes, if Wave 1 is delivered cleanly.

Wave 1 should be considered successful only if:
1. admin can create and reuse global blocks
2. pricing stays synced to CRM plans
3. admin can clone Template 1 into new vertical landing variants
4. media usage tracking prevents accidental deletion
5. publish supports rollback and checklist validation
6. Template 1 still renders correctly
7. signup, billing, and provisioning remain unchanged

If those pass, Wave 2 can safely focus on:
- SEO Center
- Blog / Content Hub
- Redirect Manager
- Schema Builder
- Analytics Center

