# P0 TEXT & LAYOUT INCIDENT REPORT

## Root Cause

- Primary cause: customer-facing public strings on the active landing path were corrupted in source/view/controller fallback content.
- This was not a font issue and not a typography-system issue.
- This was also not caused by billing, checkout, provisioning, or plan pricing logic.
- Signup price overflow came from layout constraints in the plan cards and summary sidebar:
  - price blocks were too rigid for long VND values
  - amount and currency could spill horizontally in narrow card widths
  - summary/sidebar text did not degrade safely on smaller widths

## Encoding Fix Applied

- Repaired active public rendering on:
  - landing template `fastwork_inspired`
  - public signup page
  - public landing controller fallback/default strings
- Applied `ftfy`-based UTF-8 source cleanup on the affected public files.
- Added public output normalization in `Kt_landing_public.php` so the rendered HTML is normalized before output on:
  - landing
  - pricing
  - signup
- Preserved existing content intent. This pass did not rewrite marketing copy or pricing data.

## Affected Files / DB Records

### Files changed
- `modules/kt_landing/controllers/Kt_landing_public.php`
- `modules/kt_landing/views/public/templates/fastwork_inspired/index.php`
- `modules/kt_landing/views/public/signup.php`
- `modules/kt_landing/assets/templates/fastwork_inspired/style.css`

### Database records
- No confirmed DB corruption was required to resolve this P0.
- Active evidence points to file/source render corruption on the public path, not pricing-plan DB values.

## Price Overflow Fix Applied

- Hardened signup plan-card price blocks and summary sidebar:
  - `font-variant-numeric: tabular-nums`
  - safer wrapping behavior for amount/currency
  - reduced heading scale on constrained widths
  - better mobile stacking for price blocks
  - summary rows and total block now tolerate long values without horizontal bleed
- Verified against the required values:
  - `0 VND`
  - `5.880.000 VND`
  - `7.500.000 VND`
  - `8.820.000 VND`
  - `11.170.000 VND`
  - `15.000.000 VND`
  - `100.000.000 VND` via the same hardened layout rules

## Signup Layout Fix

- Plan cards:
  - no cross-card overflow
  - no badge collision over core content
  - no broken button alignment
  - core features and detail accordion remain inside card bounds
- Summary sidebar:
  - no horizontal spill when totals are long
  - labels and values remain readable on desktop
  - mobile stack remains stable
- Verified key required text renders correctly:
  - `Đăng ký CRM Khách Tốt`
  - `Chọn gói`
  - `Doanh nghiệp`
  - `Xác nhận`
  - `Tóm tắt mua hàng`
  - `Quản lý kho`
  - `Hóa đơn`
  - `Thanh toán`
  - `Phù hợp với`
  - `Tổng dự kiến`
  - `Không phát sinh phí ẩn ở bước thanh toán`
  - `Xem giới hạn sử dụng`

## Landing Regression Check

- Re-checked the active landing surfaces:
  - Hero
  - Trust metrics
  - Why CRM Khách Tốt
  - Comparison
  - Showcase
  - Marketplace
  - Pricing
  - FAQ
  - CTA
- Result on the active public screenshots:
  - core Vietnamese headings/subheads are restored
  - key customer-facing product wording is readable
  - no price-card overflow in the visible pricing areas
- This pass also removed the most visible customer-facing technical leakage on the active landing path.

## Screenshots Verified

- Landing desktop:
  - [p0-landing-desktop-2026-06-03-v2.png](/d:/laragon/www/khachtot/modules/kt_landing/docs/screenshots/p0-landing-desktop-2026-06-03-v2.png)
- Landing full page:
  - [p0-landing-full-2026-06-03-v2.png](/d:/laragon/www/khachtot/modules/kt_landing/docs/screenshots/p0-landing-full-2026-06-03-v2.png)
- Signup desktop:
  - [p0-signup-desktop-2026-06-03-v2.png](/d:/laragon/www/khachtot/modules/kt_landing/docs/screenshots/p0-signup-desktop-2026-06-03-v2.png)
- Signup full page:
  - [p0-signup-full-2026-06-03-v2.png](/d:/laragon/www/khachtot/modules/kt_landing/docs/screenshots/p0-signup-full-2026-06-03-v2.png)
- Signup mobile:
  - [p0-signup-mobile-2026-06-03-v2.png](/d:/laragon/www/khachtot/modules/kt_landing/docs/screenshots/p0-signup-mobile-2026-06-03-v2.png)

## Remaining Risks

- A few lower-priority demo/mock literals in the landing template still warrant a later cleanup pass if the goal is source-level purity across every mock caption.
- The active public path is visually stabilized, but the template still contains legacy text debt that should be cleaned after soft launch pressure is gone.
- This pass intentionally did not touch:
  - billing logic
  - checkout logic
  - provisioning logic
  - pricing data

## P0 Closed?

- **Yes for active soft-launch surfaces.**
- Landing and signup are restored to a usable customer-facing state.
- Encoding corruption on the critical public path is contained.
- Signup card and sidebar overflow are fixed for the active responsive views.
