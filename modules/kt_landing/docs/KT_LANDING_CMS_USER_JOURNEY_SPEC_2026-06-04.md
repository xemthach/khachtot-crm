# KT LANDING CMS USER JOURNEY SPEC

Scope:
- Define user journeys, workflow journeys, and daily operational journeys for V3.
- No new screens.
- No new features.
- No new menu items.
- No DB changes.

## Personas

### Marketing Admin
- Goal: operate landing pages, pricing, and campaigns without code
- Daily tasks:
  - create or clone landing pages
  - update CTA and promotional content
  - review publish status
  - check leads and top landing pages

### Content Editor
- Goal: publish content quickly with minimal friction
- Daily tasks:
  - create articles
  - update FAQs and case studies
  - add internal links
  - preview and submit for publish

### SEO Manager
- Goal: keep pages healthy and visible in search
- Daily tasks:
  - review SEO issues
  - fix missing meta and alt text
  - validate redirects and schema
  - check SEO health score

### Publisher
- Goal: ensure changes are validated and safely published
- Daily tasks:
  - review drafts
  - validate pages
  - publish approved changes
  - rollback if needed

### Owner
- Goal: monitor business outcomes and approve major changes
- Daily tasks:
  - review website health
  - check leads and traffic
  - approve major launches
  - monitor campaign impact

## Landing Creation Flow

Workflow:
Create Landing
↓
Clone Template
↓
Edit Sections
↓
Edit CTA
↓
Edit SEO
↓
Preview
↓
Publish

### Journey notes
- The user starts from an existing template rather than a blank page.
- Clone establishes structure, branding, and baseline sections.
- Sections are edited in place, with CTA and SEO as separate validation steps.
- Preview must be the last safe checkpoint before publish.

## Content Flow

Workflow:
Content Hub
↓
Create Article
↓
SEO Validation
↓
Preview
↓
Publish

### Journey notes
- Content creation should feel editorial.
- SEO validation happens before preview to avoid rework.
- Publish is the final commit point.

## SEO Flow

Workflow:
SEO Center
↓
Issues
↓
Fix
↓
Validate
↓
Publish

### Journey notes
- SEO managers work from issues, not raw settings.
- Validation should make it obvious whether the page is safe to publish.
- Publishing should be blocked only for major issues.

## Media Flow

Workflow:
Upload
↓
Metadata
↓
Usage
↓
Replace
↓
Publish

### Journey notes
- Upload is only the start.
- Metadata and usage context are required to make media reusable and safe.
- Replace should preserve references.
- Media changes should be publish-safe without breaking pages.

## Pricing Flow

Workflow:
CRM Plan
↓
Pricing Sync
↓
Landing
↓
Preview
↓
Publish

### Journey notes
- Pricing truth originates from the CRM plan source.
- Landing only controls presentation fields.
- Sync status should be checked before publish.
- Preview ensures the marketing presentation matches the source plan.

## Conversion Flow

Workflow:
Landing
↓
CTA
↓
Form
↓
Lead
↓
CRM

### Journey notes
- CTA should be the bridge from marketing page to capture.
- Form must capture source and campaign context.
- Lead should land in CRM without losing attribution.

## Publish Flow

Workflow:
Draft
↓
Preview
↓
Validation
↓
Publish
↓
Rollback

### Journey notes
- Draft is the default working state.
- Preview is the human approval checkpoint.
- Validation is the automated safety gate.
- Rollback is the recovery path, not the normal path.

## Daily Operations

### Marketing Admin
- review dashboard health
- update landing sections
- refresh CTA and promotions
- check lead intake
- publish approved changes

### SEO Manager
- review SEO issues
- fix metadata gaps
- check broken links and redirects
- validate pages before publish

### Content Editor
- draft articles
- update FAQ and case studies
- preview pages
- submit for publish

## UX Refactor Sequence

Recommended order:
1. Refactor Dashboard
2. Refactor Website Builder
3. Refactor Content Hub
4. Refactor Media
5. Refactor SEO
6. Refactor Publish

Rationale:
- Dashboard sets the mental model
- Website Builder is the core editing workflow
- Content Hub and Media support editorial throughput
- SEO and Publish are the safety and quality layers

## Ready To Refactor UI?

Yes.

The journeys are now clear enough to guide the UI refactor without introducing new scope.

