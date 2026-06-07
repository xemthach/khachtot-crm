# TYPOGRAPHY SYSTEM REPORT

Scope:
- Landing
- Signup
- Pricing
- Checkout
- Public pages
- No logic change
- No data change
- No CMS change

## Current Font Audit

### Before
- Landing templates were inconsistent:
  - `fastwork_inspired`: `Inter, Roboto, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`
  - `modern_growth`: `Inter, Arial, sans-serif`
  - `corporate_saas`: `Inter, Arial, sans-serif`
  - `minimal_enterprise`: `Inter, Arial, sans-serif`
- Public pages (`home`, `pricing`, `signup_status`) had their own inline styles and partial font rules.
- Signup had its own inline `Inter, Arial, sans-serif`.
- Checkout relied on payment gateway head, which already loaded Inter, but did not define a clear typography hierarchy for title, body, or amount.

### Problems
- Inconsistent fallback stacks.
- Heading hierarchy was template-local rather than system-level.
- Pricing numbers did not follow one visual scale across templates/pages.
- Signup and checkout had acceptable readability but not a clearly standardized SaaS B2B hierarchy.

## Vietnamese Readability

### Selection criteria
- Full Vietnamese diacritic support.
- Clean dấu rendering at both 14px-16px body sizes and 28px-48px heading sizes.
- Strong readability on desktop and mobile.
- Neutral B2B SaaS tone.

### Result
- Chosen system:
  - **Primary**: `Inter`
  - **Fallback**: `system-ui, "Segoe UI", Roboto, Arial, sans-serif`
- I did **not** split heading/body into `Manrope + Inter` because there is no guaranteed Manrope asset already wired in this stack. Using it without a proper font source would create inconsistency instead of improving hierarchy.

## Font Selection

### Standardized stack
- `--kt-font-sans: "Inter", system-ui, "Segoe UI", Roboto, Arial, sans-serif`
- `--kt-font-heading: "Inter", system-ui, "Segoe UI", Roboto, Arial, sans-serif`

### Shared stylesheet
- New file:
  - [assets/css/kt_public_typography.css](/d:/laragon/www/khachtot/assets/css/kt_public_typography.css)

### Applied to
- `fastwork_inspired`
- `corporate_saas`
- `minimal_enterprise`
- `modern_growth`
- public `home`
- public `pricing`
- public `signup`
- public `signup_status`
- public `checkout`

## Typography Scale

### System scale
- `Caption`: `12px`
- `Body`: `16px`
- `Body small / Button`: `14px`
- `H3`: `20px`
- `H2`: `28px`
- `H1`: `36px`
- `Display / large landing hero`: template-specific overrides still exist, but now anchored to the shared heading family and hierarchy
- `Pricing number`: `28px-34px` depending on surface

### Shared rules
- body copy uses `Inter`
- headings use `Inter` with heavier weight and tighter line-height
- pricing numbers use heading family, stronger weight, and tighter spacing
- responsive reduction is defined for tablet/mobile

## Pricing Typography

### What changed
- Pricing numbers were strengthened across public surfaces:
  - larger size
  - tighter line-height
  - heading-family treatment
  - clearer visual separation between amount and cycle label

### Affected areas
- `fastwork_inspired` pricing cards
- `modern_growth` pricing cards
- `corporate_saas` pricing cards
- `minimal_enterprise` pricing cards
- public `pricing.php`

### Result
- Price is now visually dominant.
- Cycle/billing label is subordinate.
- Better scan speed for SaaS B2B buyers.

## Signup Typography

### What changed
- Signup now uses the shared public typography stylesheet plus local hierarchy tuning.
- Strengthened:
  - page title
  - step titles
  - plan names
  - setup/subscription price blocks
  - summary panel totals
  - review total

### Result
- Purchase flow feels less like an admin form and more like a commercial SaaS signup.
- Setup fee and subscription fee now read as commercial numbers, not plain form text.

## Checkout Typography

### What changed
- Checkout received a dedicated hierarchy layer:
  - stronger panel title
  - cleaner body copy
  - emphasized invoice amount using a large number scale
  - button text standardized to the same B2B weight/size

### Result
- Checkout now matches the tone of signup and landing more closely.
- Amount due is visually clear at first glance.

## Responsive Typography

### Shared responsive behavior
- `H1` reduces from `48/42/36` style surfaces down to `32px` on mobile.
- `H2` reduces to `26px` on mobile.
- `H3` reduces to `18px` on mobile.
- pricing numbers reduce to preserve fit without flattening emphasis.

### Mobile impact
- Better readability for Vietnamese body copy.
- Less crowding in signup plan cards and summary areas.
- Checkout amount remains prominent without breaking layout.

## Before vs After

| Area | Before | After |
|---|---|---|
| Font stack | inconsistent across templates/pages | shared `Inter + system-ui + Segoe UI + Roboto + Arial` |
| Heading family | partially implicit | standardized through shared typography system |
| Pricing numbers | mixed emphasis | strong, consistent B2B pricing hierarchy |
| Signup hierarchy | readable but form-like | clearer SaaS purchase hierarchy |
| Checkout hierarchy | minimal | commercial title + amount emphasis |
| Mobile typography | okay but uneven | normalized scale with responsive reductions |

## Files Updated

- [assets/css/kt_public_typography.css](/d:/laragon/www/khachtot/assets/css/kt_public_typography.css)
- [modules/kt_landing/views/public/templates/fastwork_inspired/index.php](/d:/laragon/www/khachtot/modules/kt_landing/views/public/templates/fastwork_inspired/index.php)
- [modules/kt_landing/views/public/templates/corporate_saas/index.php](/d:/laragon/www/khachtot/modules/kt_landing/views/public/templates/corporate_saas/index.php)
- [modules/kt_landing/views/public/templates/minimal_enterprise/index.php](/d:/laragon/www/khachtot/modules/kt_landing/views/public/templates/minimal_enterprise/index.php)
- [modules/kt_landing/views/public/templates/modern_growth/index.php](/d:/laragon/www/khachtot/modules/kt_landing/views/public/templates/modern_growth/index.php)
- [modules/kt_landing/assets/templates/fastwork_inspired/style.css](/d:/laragon/www/khachtot/modules/kt_landing/assets/templates/fastwork_inspired/style.css)
- [modules/kt_landing/assets/templates/corporate_saas/style.css](/d:/laragon/www/khachtot/modules/kt_landing/assets/templates/corporate_saas/style.css)
- [modules/kt_landing/assets/templates/minimal_enterprise/style.css](/d:/laragon/www/khachtot/modules/kt_landing/assets/templates/minimal_enterprise/style.css)
- [modules/kt_landing/assets/templates/modern_growth/style.css](/d:/laragon/www/khachtot/modules/kt_landing/assets/templates/modern_growth/style.css)
- [modules/kt_landing/views/public/home.php](/d:/laragon/www/khachtot/modules/kt_landing/views/public/home.php)
- [modules/kt_landing/views/public/pricing.php](/d:/laragon/www/khachtot/modules/kt_landing/views/public/pricing.php)
- [modules/kt_landing/views/public/signup.php](/d:/laragon/www/khachtot/modules/kt_landing/views/public/signup.php)
- [modules/kt_landing/views/public/signup_status.php](/d:/laragon/www/khachtot/modules/kt_landing/views/public/signup_status.php)
- [modules/kt_saas/views/public/checkout.php](/d:/laragon/www/khachtot/modules/kt_saas/views/public/checkout.php)
