# KT LANDING CMS V3 UX REARCHITECTURE REPORT

Scope:
- Re-architect the UX of KT Landing CMS after Wave 1.
- No code.
- No migration.
- No new feature build.
- Goal is to move the admin experience from CRUD-style database admin toward a marketing-first CMS and website builder model.

## Executive Summary

Wave 1 delivered core capabilities:
- Global Blocks
- Pricing Sync
- Media Center
- Publish Center
- Clone Engine

However, the current UX still reads like an internal admin console in several places:
- screen labels expose technical concepts
- navigation is fragmented across CRUD-like pages
- dashboard emphasizes operational data instead of marketing status
- content authoring is not organized around page/section/block hierarchy

V3 should not add features. It should reframe the same capabilities into a marketing CMS mental model:
- Website Builder
- Content Hub
- Media
- Pricing
- Marketplace
- SEO Center
- Leads
- Publish Center
- Settings

The main architectural change is UX layering, not backend scope.

## Current UX Audit

| Screen | Current UX Score | Problems | Severity |
| --- | ---: | --- | --- |
| Dashboard | 5.5/10 | too technical, too many operational widgets, weak marketing status framing | High |
| Settings | 5.0/10 | mixed platform config and content config, unclear ownership | High |
| Themes | 4.5/10 | theme vocabulary is closer to developer tooling than CMS operations | Medium |
| Theme Customizer | 5.0/10 | too many low-level controls exposed without marketing context | Medium |
| Pages | 5.5/10 | page authoring exists but feels like table-driven CRUD | High |
| Sections | 4.5/10 | section management is not presented as page composition | High |
| Menu | 5.0/10 | navigation editing is functional but not content-first | Medium |
| Media | 5.5/10 | works like a file table instead of an asset library | Medium |
| Pricing | 6.0/10 | strongest area, but still exposes internal sync concepts in places | Medium |
| Blog | 4.5/10 | content entry feels form-centric, not editorial | High |
| SEO | 4.5/10 | config-driven rather than guidance-driven | High |

## Information Architecture

Recommended top-level menu:

Landing CMS
├ Dashboard
├ Website Builder
├ Content Hub
├ Media
├ Pricing
├ Marketplace
├ SEO Center
├ Leads
├ Publish Center
└ Settings

Why this structure:
- it matches the mental model of marketing teams
- it groups page composition, content, SEO, and publishing by workflow
- it hides database-oriented terms from the primary navigation
- it keeps Wave 1 functionality but repackages it into recognizable CMS concepts

## Dashboard UX

Current problem:
- dashboard mixes system health, operational numbers, and content status without a clear hierarchy.

Target dashboard:
- Website Status
- SEO Health
- Published Pages
- Draft Pages
- Leads
- Traffic
- Recent Changes

Dashboard should answer:
- Is the site healthy?
- What is draft vs published?
- Are leads coming in?
- Is SEO acceptable?
- What changed recently?

Dashboard should not foreground:
- database metrics
- internal identifiers
- sync hashes
- snapshot IDs

Text wireframe:

Landing CMS Dashboard
├ Website Status
├ SEO Health
├ Published Pages
├ Draft Pages
├ Leads
├ Traffic
├ Recent Changes
└ Quick Actions

## Website Builder UX

Target hierarchy:

Website
↓
Page
↓
Section
↓
Block

This is the key mental model shift.

Marketing users should think in terms of:
- choose a page
- edit a section
- edit the blocks inside it
- preview the page
- publish the page

Not:
- manage rows
- edit JSON
- inspect references
- manipulate templates

Text wireframe:

Website Builder
├ Home
├ Pricing
├ Marketplace
├ Blog
└ Contact

Selected Page
├ Page Settings
├ Section List
├ Reorder
├ Visibility
├ Preview
└ Publish

Section
├ Title
├ Subtitle
├ CTA
├ Media
├ Cards
├ Stats
└ Layout Variant

## Content Hub UX

Content Hub should include:
- Blog
- Case Studies
- FAQ
- Resources

The UX must be editorial, not CRUD-form-centric.

Preferred content editor model:
- title
- slug
- excerpt
- body
- featured media
- category
- tags
- SEO
- status
- preview
- publish

What to avoid:
- exposing raw technical labels as the primary workflow
- forcing editors to work from database-style lists without context
- splitting content authoring from preview/publish flow

## Media UX

Media Center should behave like an asset library, not a file table.

Required UX framing:
- searchable assets
- folders/categories
- usage context
- replace asset without breaking references
- alt/title/caption metadata
- image optimization indicators

Text wireframe:

Media Center
├ Asset Grid
├ Folders
├ Filters
├ Usage
├ Replace
└ Metadata Panel

## Pricing UX

Pricing is already close to the right mental model.

V3 should keep it marketing-first:
- Plan
- Price
- Setup Fee
- Badge
- Best For
- CTA

Should not foreground:
- sync state
- hash
- snapshot IDs
- internal field names

Sync warnings belong in a secondary admin-only layer, not the default presentation layer.

## SEO UX

SEO Center should be a dashboard plus issue list, not a settings dump.

Recommended screens:
- SEO Dashboard
- Issues
- Pages
- Settings

What users should see first:
- missing title
- missing description
- missing H1
- missing alt
- broken references
- canonical issues

Not:
- raw defaults in an unstructured form
- implementation-only option lists

## Publish UX

Publish Center should be framed as content publishing, not database snapshots.

Core actions:
- Draft
- Preview
- Publish
- Rollback

Core metadata:
- version
- date
- author
- status

Wireframe:

Publish Center
├ Drafts
├ Preview
├ Publish Queue
├ Published Versions
└ Rollback History

## Screen Inventory

| Screen | Action | Reason |
| --- | --- | --- |
| Dashboard | Keep and redesign | needs a marketing status view |
| Settings | Keep and split mentally | too broad, but still required |
| Themes | Merge into Settings or de-emphasize | too technical as a standalone top-level item |
| Theme Customizer | Rename / subsume | should feel like brand settings, not developer tooling |
| Pages | Keep and elevate into Website Builder | core authoring surface |
| Sections | Merge into Website Builder | section editing belongs inside page composition |
| Menu | Keep but rename as Navigation | marketing-friendly wording |
| Media | Keep and redesign as Media Center | should feel like an asset library |
| Pricing | Keep and simplify | already business-friendly |
| Blog | Keep and move under Content Hub | editorial flow needed |
| SEO | Keep and elevate into SEO Center | needs guidance-driven UX |
| Leads | Keep and group into conversion tools | should be treated as conversion center, not raw leads table |
| Publish | Keep and rename as Publish Center | workflow framing needed |
| Preview | Merge into Publish Center | should not be a top-level destination |

## Wireframes

### Dashboard
```text
Landing CMS Dashboard
├ Website Status
├ SEO Health
├ Published Pages
├ Draft Pages
├ Leads
├ Traffic
├ Recent Changes
└ Quick Actions
```

### Website Builder
```text
Website Builder
├ Pages
│  ├ Home
│  ├ Pricing
│  ├ Blog
│  └ Contact
├ Page Editor
│  ├ Section List
│  ├ Block List
│  ├ Media
│  └ Preview
└ Publish
```

### Media Center
```text
Media Center
├ Asset Grid
├ Filters
├ Folders
├ Usage Panel
├ Replace Action
└ Metadata Panel
```

### SEO Center
```text
SEO Center
├ Dashboard
├ Issues
├ Pages
└ Settings
```

### Publish Center
```text
Publish Center
├ Draft
├ Preview
├ Publish
├ Versions
└ Rollback
```

## Migration Impact

Frontend impact:
- high at the admin UI layer
- low on public landing behavior if refactor is disciplined

Backend impact:
- moderate
- mostly view/controller/menu restructuring

DB impact:
- low for UX-only rearchitecture
- no schema change required for this phase

Migration impact:
- none required for this planning pass
- future implementation should avoid schema churn unless the page/section builder is explicitly expanded

## Recommended Build Order

1. Dashboard redesign
2. Navigation restructure
3. Website Builder framing
4. Content Hub framing
5. Media Center polish
6. SEO Center framing
7. Publish Center polish
8. Settings cleanup

## Ready For UX Refactor?

Yes.

But the refactor should be treated as a UX re-packaging effort first:
- rename screens
- regroup menus
- reframe workflows
- reduce technical surface language
- keep backend behavior stable

That is the shortest path from a CRUD-style admin into a marketing CMS experience.

