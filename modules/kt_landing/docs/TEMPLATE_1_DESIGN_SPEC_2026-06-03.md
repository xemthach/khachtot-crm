# TEMPLATE 1 DESIGN SPEC

Scope:
- Design specification for `fastwork_inspired`.
- No audit, no review, no code changes.
- Intended as an implementation-ready spec for frontend work inside the current Template 1 only.
- All content remains CMS-driven and uses the existing KT Landing / KT SAAS data sources.

## 1. Hero

### Layout
- Desktop frame: 1440px.
- Grid: 12 columns.
- Left block: 5 columns.
- Right block: 7 columns.
- Height: 720px to 820px.

### Left content order
1. Badge
2. Headline
3. Subheadline
4. Trust indicators
5. Primary CTA
6. Secondary CTA

### Right content
- Product canvas, not a card.
- Visual composition should feel like a live SaaS dashboard.
- Include:
  - Revenue KPI
  - Pipeline
  - Tasks
  - Activity feed
  - Recent customers
  - Invoice summary

### Visual rules
- Do not use a plain white card.
- Do not use a table-like grid as the hero center.
- Use layered panels, chart surfaces, chips, mini cards, and density.
- The canvas must feel closer to HubSpot, Monday, Getfly, or Fastwork than a generic dashboard mockup.

### CTA hierarchy
- Primary CTA: strongest visual weight, filled style.
- Secondary CTA: outline or subtle ghost style.
- Secondary CTA should remain visible but clearly lower priority.

### Desktop visual proportions
- Hero copy should remain compact enough to keep the visual canvas dominant.
- The right visual should occupy about 55 to 60 percent of the hero area.

## 2. CRM Showcase

### Layout
- Two-column section.
- Left: CRM screenshot / mockup.
- Right: benefits and concise supporting copy.

### CRM screenshot content
- Pipeline stages:
  - Lead
  - Qualified
  - Proposal
  - Won
  - Lost
- Deal values
- Activity list
- Revenue panel
- KPI strip

### Visual intent
- Must read as a working CRM product.
- Do not present as a text block.
- Do not present as a static feature card.

### Supporting benefits block
- 3 to 5 short benefit bullets.
- Keep the copy short and readable.
- The visual should lead; the text should support.

## 3. Inventory Showcase

### Layout
- Two-column or asymmetrical split.
- Prefer a larger inventory dashboard screenshot with a smaller supporting column.

### Inventory dashboard content
- Warehouse cards
- Stock summary
- Low stock alerts
- Inventory movement
- Stock chart

### Visual intent
- It must feel like inventory software in active use.
- Show operational states, not just labels.

## 4. Invoice Showcase

### Layout
- Dashboard-style billing panel.

### Invoice dashboard content
- Paid
- Pending
- Overdue
- Invoice list
- Revenue summary
- Collection summary

### Visual intent
- This must feel like a billing product screen.
- Include enough density to communicate accounting operations without becoming cluttered.

## 5. Payment Showcase

### Layout
- SePay dashboard-inspired operations screen.

### Payment dashboard content
- Transactions
- Matched
- Unmatched
- Reconciliation
- Daily volume

### Visual intent
- Make payment reconciliation feel real.
- Avoid generic finance cards with no workflow.

## 6. Platform Explorer

### Layout
- Interactive tab explorer.
- Desktop: horizontal tab row.
- Tablet: wrapped tab row or segmented scroll.
- Mobile: condensed chips / slider tabs.

### Tabs
- CRM
- Inventory
- Invoice
- Payments
- Projects
- DMS

### Each tab should switch
- Screenshot or mockup
- KPI summary
- Workflow summary
- Benefit bullets

### Visual rules
- Changing tab must visibly change more than text.
- The screenshot area must update on every tab switch.

## 7. Marketplace

### Layout
- Card grid, not a list.
- Desktop: 3-column or 4-column depending on content count.
- Tablet: 2-column.
- Mobile: 1-column.

### Card fields
- Icon
- Category
- Title
- Description
- Badge
- CTA

### Example items
- MatBao Invoice
- HSM
- SePay
- Website
- Hosting
- Domain
- Extra Storage
- Extra Users

### Visual intent
- Make it feel like a mini app store.
- Cards should have depth, hover state, and a clear call to action.

## 8. Comparison Matrix

### Purpose
- Explain the value of a unified platform versus fragmented point solutions.

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
- Visually emphasize KT SaaS as the consolidation option.
- Keep the matrix scannable at a glance.

## 9. Security & Trust

### Layout
- New section with 6 visual cards.

### Cards
- Multi Tenant
- Backup
- Monitoring
- Audit Logs
- Access Control
- Payment Security

### Card content
- Icon
- Title
- Description

### Visual intent
- This section should remove operational and security hesitation.

## 10. Pricing

### Data rules
- Do not change pricing data.
- Use the existing plan engine and CMS sources only.

### Layout
- Card-based pricing grid.
- One featured plan must be visually dominant.

### Featured plan styling
- Stronger border or background accent
- Badge
- More visible CTA
- More visible module badges

### Module badges
- CRM
- Inventory
- Invoice
- Payments
- Projects
- Automation

### Quota layout
- Limits and included modules should be easier to scan.
- Add-ons and quota information should be visually grouped, not buried.

### CTA layout
- Primary CTA must be visually obvious.
- Secondary CTA should stay available for consultation or demo.

## 11. Social Proof

### No fake customer logos
- Do not use fake logos as customer proof if not verified.

### Preferred metric cards
- Tenant Count
- Invoice Count
- Transaction Count
- Uptime
- API Calls

### Layout
- Metrics band with 4 to 5 cards.
- Cards should be compact, readable, and high trust.

## 12. CTA System

### CTA hierarchy
- Primary CTA
- Secondary CTA

### Primary CTA copy
- `Đăng ký dùng thử`

### Secondary CTA copy
- `Đặt lịch demo`

### Placement
- Hero
- After CRM showcase
- After Marketplace
- After Pricing
- Footer

### Visual rule
- Users should never need to scroll to the bottom to find an action.

## 13. Design Tokens

### Typography
- Headings: strong, commercial, modern SaaS style.
- Body: compact and readable.
- Avoid oversized hero typography that makes the page feel like a marketing splash without product substance.

### Spacing
- Consistent section rhythm.
- Enough whitespace for scanning.
- Avoid overly tall empty gaps.

### Border radius
- Moderate radius, consistent across cards and surfaces.
- Keep the system cohesive.

### Shadow
- Layered but restrained.
- Use shadow depth to signal product surfaces, not decorative flourish.

### Card depth
- Cards should have enough depth to separate surfaces, but not look glossy or toy-like.

### Color discipline
- Keep one clear primary accent.
- Use subdued supporting neutrals.
- Use badge colors sparingly and with meaning.

## 14. Responsive Rules

### Desktop 1440px
- Hero should stay 5/7 split.
- Showcases should remain product-led and readable.
- Marketplace should use a multi-column card grid.

### Laptop 1280px
- Preserve the same hierarchy.
- Allow visual canvas and cards to compress gracefully.

### Tablet 768px
- Hero should stack or reduce to a tighter split.
- Showcases should simplify to one-column or near-one-column layouts.
- Marketplace should become 2-column.
- Tabs in platform explorer may scroll horizontally.

### Mobile 375px
- Hero copy first, then product visual.
- Product canvases must scale without clipping.
- Comparison matrix should become a vertical stacked comparison or horizontally scrollable table.
- Pricing should remain readable and not overflow.

## 15. Implementation Priority

1. Hero product canvas
2. CRM showcase
3. Inventory showcase
4. Invoice showcase
5. Payment showcase
6. Platform explorer tabs
7. Marketplace card system
8. Pricing emphasis and module badges
9. Comparison matrix
10. Security & trust section
11. Social proof metric band
12. CTA distribution tuning

## 16. Before vs After

### Before
- Landing prototype feel
- Section-first hierarchy
- Weak product surface realism
- Weak trust story

### After
- Professional SaaS product website
- Product demo-led experience
- Better conversion rhythm
- Better credibility under B2B scrutiny

## 17. Final Spec Summary

- Template 1 already has the right content model and landing architecture.
- The implementation target is not more content.
- The target is stronger product visuals, stronger product storytelling, and clearer conversion hierarchy.
- Every section should look like a live product surface, not a static explanation block.

