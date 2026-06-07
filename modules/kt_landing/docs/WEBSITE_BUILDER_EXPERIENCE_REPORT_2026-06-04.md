# WEBSITE BUILDER EXPERIENCE REPORT

Scope:
- Assess the current Website Builder experience after the V3 UX refactor.
- No code changes.
- No new feature build.
- No Wave 2 work.
- Goal is to check whether the current screen feels like a CMS builder or still like CRUD.

## Current Builder Audit

Current screen: `Website Builder` at `admin/kt_landing/pages`.

What it currently does:
- shows a page tree on the left
- shows a page structure summary in the center
- shows composition properties on the right
- allows page creation
- allows preview/delete actions per page
- hides database tables, IDs, and JSON from the primary UI

Assessment:
- Marketing can understand the intent: page, sections, shared blocks.
- Content editors can use it for basic page administration.
- It is better than CRUD, but it is not yet a visual builder.
- The center panel still reads like a structured summary rather than a live page canvas.

Current UX score:
- 7.2/10

Main limitations:
- no true visual page canvas
- section cards are still text-heavy
- block usage is summarized, not visually represented
- preview is a separate action, not part of the main editing experience

## Visual Builder Model

Target mental model:

```text
Website Builder
├ Page Tree
├ Visual Structure
└ Properties
```

What marketing should see:
- Hero
- Trust
- Features
- Pricing
- FAQ
- CTA

What should not be visible by default:
- IDs
- internal keys
- JSON
- database references

Recommended hierarchy:
- Left: page navigation and page list
- Center: visual structure / live page canvas
- Right: properties and actions

## Section Cards

Section cards should behave like content cards, not table rows.

Each section card should show:
- icon
- name
- status
- visibility
- preview
- duplicate
- delete

What is missing today:
- section cards are implied through labels and structure lists
- no richer card visual with clear actions
- no distinct status/visibility chip system per section

Recommended UX change:
- render each section as a card with a compact content preview
- keep actions on the card surface
- avoid table-row presentation

## Live Preview

Current state:
- the builder shows structure, not a true live page preview
- page composition is understandable, but still abstract

Desired state:
- a preview panel should be visible inside the builder
- the admin should see the page shape while editing
- live preview should make section order and CTA placement obvious

Current score for preview experience:
- 6.8/10

## Block Experience

Global blocks currently need to feel like reusable CMS assets, not technical records.

Should show:
- Used By
- Preview
- Edit
- Duplicate

Should not show by default:
- raw JSON
- internal reference keys

Current state:
- shared blocks are represented indirectly through counts and links
- the builder does not yet surface them as first-class visual assets

## Marketing Workflow Test

Simulated flow:
- Marketing Admin opens Website Builder
- creates HVAC landing
- edits Hero
- edits CTA
- adjusts Pricing
- previews
- publishes

Observed outcome:
- the flow is understandable
- the user can move through page creation and page composition without reading technical docs
- however, the user still has to infer visual impact from text-oriented panels

Does it require reading documentation?
- not for the basic flow
- yes for any true layout composition beyond the current abstraction

## Before vs After

Before:
- CRUD admin
- page tables
- technical framing
- weak page-composition mental model

After V3:
- CMS framing
- page tree and composition framing
- no visible database language on the main screen
- much better for marketing users

## UX Score

Current Builder:
- 7.2/10

Proposed Builder:
- 9.0/10

Gap to close for the proposed score:
- live visual canvas
- richer section cards
- more obvious block usage visualization
- stronger preview integration

## Screenshots

Verified screenshot:
- [Website Builder current state](</d:/laragon/www/khachtot/modules/kt_landing/docs/screenshots/v31-website-builder.png>)

## Ready For Wave 2?

Yes.

The current builder is already good enough to support Wave 2 planning. The remaining delta is UX depth, not architecture correctness.
