# KT LANDING CMS V3 SCREEN DESIGN SPEC

Scope:
- Define the target screen designs for V3 before UI refactor implementation.
- No audit.
- No new feature build.
- No Wave 2.
- No code.

## Dashboard

Dashboard should behave like a marketing control center, not a system console.

### Layout
- Top summary band
- Middle KPI grid
- Lower activity and action area

### Sections
- Website Health
- SEO Health
- Published Pages
- Draft Pages
- Recent Leads
- Top Landing Pages
- Top CTA
- Recent Changes
- Quick Actions

### Widget design
- Use compact cards with one primary metric and one supporting line
- Use visual health states: good, warning, critical
- Use action buttons only for immediate workflows such as preview, publish, fix SEO, review leads

### Dashboard wireframe
```text
Dashboard
├ Header Summary
│  ├ Website Health
│  ├ SEO Health
│  ├ Published Pages
│  └ Draft Pages
├ Performance Grid
│  ├ Recent Leads
│  ├ Top Landing Pages
│  ├ Top CTA
│  └ Recent Changes
└ Quick Actions
   ├ Preview Draft
   ├ Publish Changes
   ├ Review SEO Issues
   └ Open Conversion Center
```

## Website Builder

Website Builder should frame the site as a hierarchy:

Website
↓
Page
↓
Sections
↓
Blocks

### Layout
- Left: Page Tree
- Center: Page Structure
- Right: Properties

### Page tree
- Home
- Pricing
- Marketplace
- Blog
- Contact

### Page structure examples
- Hero
- Trust
- Features
- Pricing
- FAQ
- CTA

### User actions
- reorder
- hide
- duplicate
- edit
- preview

### Website Builder wireframe
```text
Website Builder
├ Left Panel
│  ├ Page Tree
│  └ Navigation
├ Center Panel
│  ├ Page Structure
│  ├ Section Stack
│  └ Live Preview
└ Right Panel
   ├ Properties
   ├ Media
   ├ CTA
   └ Visibility
```

## Content Hub

Content Hub should not feel like a table of posts.

### Content types
- Blog
- Case Studies
- FAQ
- Resources

### Workflow
- Draft
- Review
- Preview
- Publish

### Screen behavior
- list view for discovery
- editor view for authoring
- preview view for validation
- workflow status clearly visible at top

### Content Hub wireframe
```text
Content Hub
├ Content Type Tabs
│  ├ Blog
│  ├ Case Studies
│  ├ FAQ
│  └ Resources
├ Content List
├ Editor Workspace
└ Workflow Sidebar
   ├ Draft
   ├ Review
   ├ Preview
   └ Publish
```

## Media Center

Media Center should behave like an asset library.

### Views
- Grid View
- List View

### Core features
- Folders
- Usage
- Metadata
- Replace

### Metadata panel
- alt
- title
- caption
- tags
- category
- usage references

### Media Center wireframe
```text
Media Center
├ Toolbar
│  ├ Upload
│  ├ Grid / List Toggle
│  └ Filters
├ Folder Sidebar
├ Asset Grid
└ Detail Panel
   ├ Metadata
   ├ Usage
   └ Replace
```

## Pricing

Pricing should be marketing-first.

### Visible fields
- Plan
- Price
- Setup Fee
- Badge
- Best For
- CTA

### Hidden by default
- Advanced Diagnostics
- Sync details
- internal references

### Pricing screen behavior
- cards are the primary unit
- admin can edit presentation fields
- source-of-truth fields remain locked
- diagnostics open only in an advanced panel

### Pricing wireframe
```text
Pricing Manager
├ Plan Cards
├ Presentation Editor
├ Visibility / Order
└ Advanced Diagnostics
```

## Marketplace

Marketplace Manager should present app cards, not module keys.

### Components
- App Cards
- Categories
- Featured Apps
- Marketing Description

### Card fields
- display name
- icon
- short description
- category
- pricing note
- CTA

### What must stay hidden
- internal keys
- code module names

### Marketplace wireframe
```text
Marketplace Manager
├ Category Filters
├ Featured Apps
├ App Cards Grid
└ App Detail Drawer
```

## SEO Center

SEO Center should be a dashboard plus issue manager.

### Tabs
- Dashboard
- Issues
- Pages
- Redirects
- Schema
- Settings

### Tab behavior
- Dashboard: overview and health score
- Issues: missing titles, descriptions, alt text, broken links
- Pages: page-level SEO state
- Redirects: redirect management
- Schema: structured data control
- Settings: site defaults

### SEO Center wireframe
```text
SEO Center
├ Dashboard Tab
├ Issues Tab
├ Pages Tab
├ Redirects Tab
├ Schema Tab
└ Settings Tab
```

## Conversion Center

Rename Leads to Conversion Center.

### Components
- Leads
- Forms
- CTA
- Tracking
- UTM

### UX goals
- see funnel activity
- manage inbound forms
- track CTA performance
- capture source attribution

### Conversion Center wireframe
```text
Conversion Center
├ Leads
├ Forms
├ CTA
├ Tracking
└ UTM
```

## Publish Center

Publish Center should feel like CMS publishing, not snapshot administration.

### Core actions
- Draft
- Preview
- Publish
- Rollback
- Versions

### Screen behavior
- draft and published states visible
- preview isolated from public indexing
- rollback available per version

### Publish Center wireframe
```text
Publish Center
├ Draft
├ Preview
├ Publish
├ Rollback
└ Versions
```

## Settings

Settings should be business-facing first.

### Sections
- Website Name
- Tagline
- Logo
- Contact
- Brand Colors

### Advanced section
- internal keys
- system toggles
- fallback behavior

### Settings wireframe
```text
Settings
├ Website Name
├ Tagline
├ Logo
├ Contact
├ Brand Colors
└ Advanced
```

## Design Studio

Theme and Theme Customizer should collapse into Design Studio.

### Sections
- Branding
- Colors
- Typography
- Buttons
- Cards
- Templates

### Design Studio wireframe
```text
Design Studio
├ Branding
├ Colors
├ Typography
├ Buttons
├ Cards
└ Templates
```

## High Fidelity Wireframes

### Dashboard
```text
┌──────────────────────────────────────────────────────────────┐
│ Website Health | SEO Health | Published | Draft              │
├──────────────────────────────────────────────────────────────┤
│ Recent Leads        │ Top Landing Pages     │ Top CTA        │
├──────────────────────────────────────────────────────────────┤
│ Recent Changes                                              │
├──────────────────────────────────────────────────────────────┤
│ Quick Actions: Preview | Publish | Fix SEO | Conversion     │
└──────────────────────────────────────────────────────────────┘
```

### Website Builder
```text
┌──────────────┬──────────────────────────────┬───────────────┐
│ Page Tree    │ Page Structure               │ Properties    │
│ Home         │ Hero                         │ Section Title │
│ Pricing      │ Trust                        │ CTA           │
│ Marketplace  │ Features                     │ Media         │
│ Blog         │ Pricing                      │ Visibility    │
│ Contact      │ FAQ                          │ Duplicate     │
└──────────────┴──────────────────────────────┴───────────────┘
```

### Content Hub
```text
┌──────────────┬──────────────────────────────┬───────────────┐
│ Types        │ Content List                 │ Workflow      │
│ Blog         │ Draft / Review / Published   │ Preview       │
│ Cases        │ Editor workspace             │ Publish       │
│ FAQ          │ Related content              │ Status        │
│ Resources    │ SEO panel                    │               │
└──────────────┴──────────────────────────────┴───────────────┘
```

### Media Center
```text
┌──────────────┬──────────────────────────────┬───────────────┐
│ Folders      │ Asset Grid                   │ Metadata      │
│ Filters      │ Upload / Replace             │ Usage         │
│ Usage        │ Grid / List toggle           │ Alt / Title   │
└──────────────┴──────────────────────────────┴───────────────┘
```

### Pricing
```text
┌──────────────────────────────────────────────────────────────┐
│ Plan Cards                                                   │
├──────────────────────────────────────────────────────────────┤
│ Plan | Price | Setup Fee | Badge | Best For | CTA            │
├──────────────────────────────────────────────────────────────┤
│ Advanced Diagnostics (collapsed by default)                  │
└──────────────────────────────────────────────────────────────┘
```

### Marketplace
```text
┌──────────────┬──────────────────────────────┬───────────────┐
│ Categories   │ Featured Apps                │ App Detail    │
│ HVAC         │ App Cards Grid               │ Description   │
│ Distributor  │ Marketing copy               │ CTA           │
│ Service      │ Featured badges              │               │
└──────────────┴──────────────────────────────┴───────────────┘
```

### SEO Center
```text
┌──────────────┬──────────────────────────────┬───────────────┐
│ Tabs         │ Issues / Pages               │ Settings      │
│ Dashboard    │ Redirects / Schema           │ Health Score  │
└──────────────┴──────────────────────────────┴───────────────┘
```

### Conversion Center
```text
┌──────────────┬──────────────────────────────┬───────────────┐
│ Leads        │ Forms                        │ Tracking      │
│ CTA          │ UTM                         │ Attribution   │
└──────────────┴──────────────────────────────┴───────────────┘
```

### Publish Center
```text
┌──────────────────────────────────────────────────────────────┐
│ Draft | Preview | Publish | Rollback | Versions             │
├──────────────────────────────────────────────────────────────┤
│ Snapshot list with author, date, status                      │
└──────────────────────────────────────────────────────────────┘
```

### Settings
```text
┌──────────────────────────────────────────────────────────────┐
│ Website Name | Tagline | Logo | Contact | Brand Colors      │
├──────────────────────────────────────────────────────────────┤
│ Advanced settings                                             │
└──────────────────────────────────────────────────────────────┘
```

### Design Studio
```text
┌──────────────────────────────────────────────────────────────┐
│ Branding | Colors | Typography | Buttons | Cards | Templates│
└──────────────────────────────────────────────────────────────┘
```

## UX Refactor Roadmap

### Phase A
- Dashboard
- Navigation

Frontend impact:
- medium

Backend impact:
- low

Regression risk:
- low

### Phase B
- Website Builder
- Content Hub

Frontend impact:
- high

Backend impact:
- medium

Regression risk:
- medium

### Phase C
- SEO
- Conversion
- Publish

Frontend impact:
- high

Backend impact:
- medium

Regression risk:
- medium

## Ready For UI Refactor Build?

Yes.

The correct build sequence is:
1. rewrite navigation and screen naming
2. refactor screen layouts around the new model
3. normalize workflows so they feel like a CMS, not a database admin

That is the best path to a marketing-operations CMS without changing feature scope.

