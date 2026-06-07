# TEMPLATE 1 IMPLEMENTATION SPEC

Scope:
- Implementation specification for `fastwork_inspired`.
- No audit.
- No review.
- No code changes.
- No CMS changes.
- No architecture changes.
- No new template.

The purpose of this document is to give frontend implementation instructions that can be built directly inside the current Template 1.

## 1. Hero Component

### Component name
- `HeroComponent`

### Layout
- Desktop container: `1440px`
- Grid: `12 columns`
- Left column span: `5 columns`
- Right column span: `7 columns`
- Hero height: `720px` to `820px`

### Left content order
1. Badge
2. Headline
3. Subheadline
4. Trust indicators
5. Primary CTA
6. Secondary CTA

### Right content
- `HeroCanvas`
- Not a card
- Not a simple table
- Not a placeholder block

### HeroCanvas widgets
- Revenue Widget
- Pipeline Widget
- Tasks Widget
- Activity Widget
- Customer Widget
- Invoice Widget

### Widget structure
Each widget must include:
- title
- value
- status
- trend

### Layout style
- Use stacked panels.
- Use layered depth.
- Use mixed panel sizes to avoid a uniform dashboard grid.
- The hero canvas must visually resemble a live SaaS product surface such as HubSpot, Monday, or Getfly.

### CTA behavior
- Primary CTA: filled and dominant
- Secondary CTA: outline or ghost
- Both CTAs visible above the fold

## 2. CRM Component

### Component name
- `CRMShowcaseComponent`

### Layout
- Desktop: `6/6`
- Left: CRM screenshot
- Right: benefits + CTA

### CRM screenshot content
- Pipeline
- Lead
- Qualified
- Proposal
- Won
- Lost
- Deal Value
- Revenue
- Recent Activities

### Right side content
- 3 to 5 benefit bullets
- one CTA block

### Requirement
- No placeholder blocks.
- No text-only representation.
- The screenshot must look like an active CRM screen.

## 3. Inventory Component

### Component name
- `InventoryShowcaseComponent`

### Layout
- Desktop ratio: `7/5`
- Visual side larger than text side

### Inventory visual content
- Warehouse Cards
- Stock Summary
- Low Stock Alert
- Inventory Movement
- Stock Chart

### Requirement
- The component must feel like inventory software in active use.
- The visual surface must dominate the section.

## 4. Invoice Component

### Component name
- `InvoiceShowcaseComponent`

### Visual content
- Paid
- Pending
- Overdue
- Invoice Table
- Revenue Summary
- Collection Summary

### Requirement
- The screen must look like a billing dashboard.
- The component must not degrade into a descriptive text block.

## 5. Payment Component

### Component name
- `PaymentShowcaseComponent`

### Visual content
- Transactions
- Matched
- Unmatched
- Reconciliation
- Daily Volume

### Requirement
- The screen must look like a payment operations console.
- Reconciliation state must be visibly represented.

## 6. Platform Explorer Component

### Component name
- `PlatformExplorerComponent`

### Tabs
- CRM
- Inventory
- Invoice
- Payments
- Projects
- DMS

### Each tab must contain
- Screenshot
- KPIs
- Benefits
- Workflow

### Tab switch behavior
- Switching tabs must change:
  - screenshot
  - KPI block
  - workflow block
- Switching text only is not acceptable.

### Responsive behavior
- Desktop: horizontal tabs
- Tablet: scrollable or wrapped tabs
- Mobile: compact segmented control or scrollable pill tabs

## 7. Marketplace Component

### Component name
- `MarketplaceCatalogComponent`

### Grid
- Desktop: `4 columns`
- Tablet: `2 columns`
- Mobile: `1 column`

### Card fields
- Icon
- Category
- Badge
- Title
- Description
- CTA

### Items
- MatBao Invoice
- HSM
- SePay
- Website
- Hosting
- Domain
- Storage
- Users

### Requirement
- The marketplace must look like a mini app store.
- Cards must have visible hierarchy and hover state.

## 8. Comparison Component

### Component name
- `ComparisonMatrixComponent`

### Columns
- KT SaaS
- CRM riêng
- Inventory riêng
- Invoice riêng
- Payment riêng

### Rows
- CRM
- Inventory
- Invoice
- Payments
- Reporting
- Automation
- User Management

### Visual rules
- Use checkmarks, highlights, and badges.
- KT SaaS must be visually dominant in the matrix.

## 9. Security Component

### Component name
- `SecurityTrustComponent`

### Grid
- `3 x 2`

### Cards
- Multi Tenant
- Backup
- Monitoring
- Audit Logs
- Access Control
- Payment Security

### Card structure
- Icon
- Title
- Description

### Requirement
- Cards must be fully visual, not just text labels.

## 10. Pricing Component

### Component name
- `PricingComponent`

### Data source
- Keep the KT SAAS Plan Engine as the only pricing source.
- Do not change pricing data source.

### Layout
- Pricing cards in a visually balanced grid.
- One featured plan must be highlighted more strongly than the others.

### Featured plan treatment
- Larger visual prominence
- Distinct badge
- Stronger border or background accent
- More visible CTA

### Module badges
- CRM
- Inventory
- Invoice
- Payments
- Projects
- Automation

### Quota layout
- Make the quota block scannable.
- Group quota, limits, and add-ons clearly.

### CTA layout
- Primary CTA
- Secondary CTA

## 11. Social Proof Component

### Component name
- `MetricsBandComponent`

### Cards
- Tenant Count
- Invoice Count
- Transaction Count
- Uptime
- API Calls

### Rule
- Do not fake customer logos.
- Use metric cards when real logos are not available.

## 12. CTA System

### Component name
- `CTAComponent`

### Primary CTA
- `Đăng ký dùng thử`

### Secondary CTA
- `Đặt lịch demo`

### Placement
- Hero
- CRM
- Marketplace
- Pricing
- Footer

### Rule
- CTA must be repeated after major proof sections.
- Users must not be required to scroll to the bottom to find a call to action.

## 13. Design Tokens

### Typography scale
- Define a consistent hierarchy for:
  - hero headline
  - section heading
  - card title
  - body copy
  - meta labels

### Spacing scale
- Use a fixed section rhythm.
- Keep whitespace consistent across sections.

### Shadow levels
- Use a small set of shadow strengths.
- Shadows must create depth without looking decorative.

### Border radius
- Use one coherent radius system across cards, panels, buttons, and badges.

### Card depth
- Cards must feel layered and tactile.
- Avoid flat admin-like surfaces.

## 14. Responsive Rules

### Desktop 1440
- Full hero split.
- Multi-column product sections.
- Market grid at 4 columns where content count supports it.

### Laptop 1280
- Preserve the same visual hierarchy.
- Reduce spacing only where needed.

### Tablet 768
- Stack or compress two-column sections.
- Make tabbed explorer horizontally scrollable or compact.
- Market grid becomes 2 columns.

### Mobile 375
- Hero copy first, canvas second.
- Sections collapse into a single column.
- Comparison matrix becomes stacked or horizontally scrollable.
- Pricing cards remain readable without overflow.

## 15. Screen Mockups

### 1. Hero Canvas
- Layout:
  - 5-column left copy
  - 7-column product canvas
- Widgets:
  - Revenue KPI
  - Pipeline
  - Tasks
  - Activity
  - Customer
  - Invoice
- Goal:
  - real product surface
  - not a placeholder panel

### 2. CRM Screen
- Layout:
  - screenshot left
  - benefits right
- Widgets:
  - pipeline stages
  - deal values
  - revenue
  - recent activities
- Goal:
  - active CRM demo

### 3. Inventory Screen
- Widgets:
  - warehouse cards
  - stock summary
  - low stock alert
  - movement list
  - stock chart
- Goal:
  - operational inventory software

### 4. Invoice Screen
- Widgets:
  - paid / pending / overdue
  - invoice table
  - revenue summary
  - collection summary
- Goal:
  - billing dashboard

### 5. Payment Screen
- Widgets:
  - transactions
  - matched
  - unmatched
  - reconciliation
  - daily volume
- Goal:
  - payment operations console

## 16. Frontend Build Order

1. HeroComponent
2. CRMShowcaseComponent
3. InventoryShowcaseComponent
4. InvoiceShowcaseComponent
5. PaymentShowcaseComponent
6. PlatformExplorerComponent
7. MarketplaceCatalogComponent
8. ComparisonMatrixComponent
9. SecurityTrustComponent
10. PricingComponent polish
11. MetricsBandComponent
12. CTAComponent placement pass
13. Design tokens
14. Responsive pass
15. Final screen mockup refinement

## 17. Final Implementation Notes

- Use only the current Template 1 route and view structure.
- Keep CMS-driven values intact.
- Do not add new content models or new template branches.
- The implementation target is a SaaS product website with stronger visuals and clearer conversion flow, not a new landing architecture.

