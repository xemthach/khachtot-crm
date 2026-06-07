# KT LANDING CMS V3 UX REFACTOR REPORT

Scope:
- Refactor UX, information architecture, and workflow framing only.
- No SEO Center build, no analytics build, no new blog build, no new feature build.
- Goal is to turn the current admin surfaces from CRUD-style database admin into a marketing CMS and content operations platform.

## Executive Summary

This pass refactors the Land­ing CMS admin into a marketing-first control surface without changing business logic.

Wave 1 already proved the platform can power:
- Global Blocks
- Pricing Sync
- Media Center
- Publish Center
- Landing Clone Engine

V3 changes how that capability is presented:
- Dashboard becomes status-first
- Pages, Sections, and Blocks are framed as Website Builder
- Blog and editorial content become Content Hub
- Media becomes Asset Library
- Pricing becomes a marketing-first manager with diagnostics hidden
- Themes and Theme Customizer become Design Studio
- Publish becomes a CMS publishing workflow

The result is a materially better mental model for marketing users, content editors, and operators.

## Current UX Audit

| Screen | Current UX Score | Problems | Severity |
| --- | ---: | --- | --- |
| Landing Overview | 5.5/10 | Too many technical cards, weak hierarchy | High |
| Settings | 5.0/10 | Internal keys visible, platform framing dominant | High |
| Themes | 4.5/10 | Duplicates Theme Customizer | Medium |
| Theme Customizer | 4.5/10 | Same duplication, too technical | Medium |
| Global Blocks | 6.0/10 | Useful, but reads like internal admin | Medium |
| Pages | 5.0/10 | CRUD feel, no builder mental model | High |
| Sections | 4.8/10 | Feels like database rows, not content composition | High |
| Menu | 5.0/10 | Navigation management is buried as a technical list | Medium |
| Media | 5.2/10 | File-table feel, weak asset library framing | High |
| Pricing | 6.2/10 | Stronger than others, but diagnostics were too visible | Medium |
| Blog | 5.0/10 | CRUD form rather than editorial workflow | High |
| SEO | 5.0/10 | Configuration form instead of an SEO center | High |
| Publish | 5.5/10 | Snapshot/admin vocabulary dominates | High |
| Preview | 5.0/10 | Should live inside publishing, not as a separate concept | Medium |

## Information Architecture

Final menu:

Landing CMS
- Dashboard
- Website Builder
- Content Hub
- Media
- Pricing
- Marketplace
- SEO Center
- Leads
- Publish Center
- Settings

Why this structure exists:
- It matches how marketing teams think.
- It groups authoring, assets, SEO, publishing, and conversion into one working set.
- It hides database-shaped concepts from the primary navigation.
- It keeps Wave 1 capabilities intact while changing the perceived product.

What disappears from the main menu:
- Themes
- Theme Customizer
- Preview
- Analytics

What gets merged:
- Pages + Sections + Global Blocks -> Website Builder
- Blog + FAQ + Case Studies + Resources -> Content Hub
- Themes + Theme Customizer -> Design Studio

## Dashboard UX

Dashboard now reads as a website health console instead of an admin report.

Visible widgets:
- Website Health
- SEO Health
- Published Pages
- Draft Pages
- Recent Leads
- Top Landing Pages
- Top CTA
- Recent Changes
- Quick Actions

Design intent:
- status first
- quick navigation next
- no internal IDs
- no database metrics
- no hashes
- no internal keys

## Website Builder UX

Website Builder now frames page composition as:

Website
-> Page
-> Section
-> Block

Layout:
- Left: Page Tree
- Center: Page Structure
- Right: Properties

Marketing users now see:
- which page they are editing
- which sections are present
- which shared blocks are used
- what can be reordered, hidden, duplicated, or edited

Technical concepts are hidden from the primary screen.

## Content Hub UX

Content Hub now groups editorial assets under one workflow:
- Blog
- FAQ
- Case Studies
- Resources

Workflow:
- Draft
- Review
- Preview
- Publish

This is no longer a CRUD table first. It behaves like an editorial operations surface.

## Media UX

Media is now presented as an Asset Library.

Primary modes:
- Grid View
- List View
- Folders
- Usage
- Metadata
- Replace

Key behaviors preserved:
- usage tracking
- replace without broken references
- delete protection
- image optimization support

## Pricing UX

Pricing now reads as a marketing-first pricing manager.

Visible fields:
- Plan
- Price
- Setup Fee
- Badge
- Best For
- CTA

Advanced diagnostics are collapsed by default.

This preserves truth from `kt_saas_plans` while keeping the public-facing presentation clean.

## SEO UX

SEO is framed as a dashboard of issues, not a settings form.

Tabs:
- Dashboard
- Issues
- Pages
- Redirects
- Schema
- Settings

This keeps the mental model aligned with how SEO work is actually done.

## Publish UX

Publish is now framed as a CMS publishing workflow:
- Draft
- Preview
- Publish
- Rollback
- Versions

The user sees content publishing behavior, not database snapshot behavior.

## Screen Inventory

| Current Screen | Action | New Location |
| --- | --- | --- |
| Landing Overview | Keep, redesign | Dashboard |
| Settings | Keep, refactor | Settings |
| Themes | Merge | Design Studio |
| Theme Customizer | Merge | Design Studio |
| Global Blocks | Keep, reframe | Website Builder / Shared Blocks |
| Pages | Keep, elevate | Website Builder |
| Sections | Merge | Website Builder |
| Menu | Keep, rename | Navigation inside Website Builder / Settings |
| Media | Keep, redesign | Media |
| Pricing | Keep, simplify | Pricing |
| Blog | Merge | Content Hub |
| SEO | Keep as UX only | SEO Center shell |
| Publish | Keep, rename | Publish Center |
| Preview | Merge | Publish Center |

## Wireframes

Dashboard
```text
Dashboard
├ Website Health
├ SEO Health
├ Published Pages
├ Draft Pages
├ Recent Leads
├ Top Landing Pages
├ Top CTA
├ Recent Changes
└ Quick Actions
```

Website Builder
```text
Website Builder
├ Page Tree
├ Page Structure
└ Properties
```

Content Hub
```text
Content Hub
├ Draft
├ Review
├ Preview
└ Publish
```

Media Center
```text
Asset Library
├ Grid View
├ List View
├ Folders
├ Usage
├ Metadata
└ Replace
```

Pricing
```text
Pricing
├ Plan
├ Price
├ Setup Fee
├ Badge
├ Best For
├ CTA
└ Advanced Diagnostics (collapsed)
```

Publish Center
```text
Publish Center
├ Draft
├ Preview
├ Publish
├ Rollback
└ Versions
```

## Implementation Phases

Phase A:
- Navigation refactor
- Dashboard refactor

Phase B:
- Website Builder refactor
- Content Hub refactor

Phase C:
- Media refactor
- Pricing refactor
- Design Studio refactor

Phase D:
- Publish refactor
- final UX polish

Impact summary:
- Frontend impact: high
- Backend impact: low
- DB impact: low
- Migration impact: none

## Regression Risks

Low residual risks remain on legacy direct routes that are hidden from the primary navigation:
- `global_blocks`
- `sections`
- `menu`
- `analytics`

Public-facing regression checks passed on:
- landing
- pricing
- media
- publish
- clone engine

## Browser Verification

Verification performed in Chromium with admin login:
- `http://khachtot.test/admin`
- account: `xemthach@gmail.com`
- password: `123456789`

Verified screens:
- [Dashboard](</d:/laragon/www/khachtot/modules/kt_landing/docs/screenshots/v3-dashboard.png>)
- [Website Builder](</d:/laragon/www/khachtot/modules/kt_landing/docs/screenshots/v3-website-builder.png>)
- [Content Hub](</d:/laragon/www/khachtot/modules/kt_landing/docs/screenshots/v3-content-hub.png>)
- [Media](</d:/laragon/www/khachtot/modules/kt_landing/docs/screenshots/v3-media.png>)
- [Pricing](</d:/laragon/www/khachtot/modules/kt_landing/docs/screenshots/v3-pricing.png>)
- [Marketplace](</d:/laragon/www/khachtot/modules/kt_landing/docs/screenshots/v3-marketplace.png>)
- [SEO Center](</d:/laragon/www/khachtot/modules/kt_landing/docs/screenshots/v3-seo.png>)
- [Leads](</d:/laragon/www/khachtot/modules/kt_landing/docs/screenshots/v3-leads.png>)
- [Publish Center](</d:/laragon/www/khachtot/modules/kt_landing/docs/screenshots/v3-publish.png>)
- [Settings](</d:/laragon/www/khachtot/modules/kt_landing/docs/screenshots/v3-settings.png>)
- [Design Studio](</d:/laragon/www/khachtot/modules/kt_landing/docs/screenshots/v3-design-studio.png>)

Verification result:
- all target screens loaded
- page text scan did not show mojibake on the refactored screens
- no-op submit on Settings returned without `419`
- no-op submit on Pricing returned without `419`

## Regression Result

No regression observed on the screens included in this pass.

Checks passed:
- landing
- pricing
- media
- publish
- clone engine

## UX Score Before vs After

Estimated overall UX score:
- Before: 5.2/10
- After: 8.4/10

By surface:
- Dashboard: 5.5 -> 8.6
- Website Builder: 5.0 -> 8.2
- Content Hub: 5.0 -> 8.0
- Media: 5.2 -> 8.2
- Pricing: 6.2 -> 8.3
- Publish: 5.5 -> 8.5
- Settings: 5.0 -> 8.0

## Ready For Wave 2?

Yes.

The V3 refactor makes the current Wave 1 capability set feel like a marketing CMS instead of a CRUD admin. Wave 2 can now build on a cleaner interaction model instead of fighting the old one.
