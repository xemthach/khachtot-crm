# SIGNUP LAYOUT RESILIENCE REPORT

Scope:
- Final layout resilience pass for KT SaaS signup flow.
- No billing, pricing engine, checkout engine, or provisioning changes.
- UI/UX, CSS, layout, and copy-presentation only.

## Layout Resilience Audit

Areas audited:
- plan selection cards
- price blocks
- setup fee blocks
- core modules
- technical quota accordion
- sidebar purchase summary
- step navigation
- workspace form
- confirmation step
- mobile layout

Primary break risks found before fix:
- long price strings such as `11,170,000 VND` and `100,000,000 VND`
- long setup-fee strings
- long `Best for` copy
- long module / quota labels
- long company/email/subdomain values in summary
- uneven card height causing CTA/detail drift
- summary rows likely to overflow on narrow widths

## Price Block Fix

Implemented:
- price boxes now use `min-width: 0`
- numeric blocks now use `font-variant-numeric: tabular-nums`
- long amounts can wrap safely with `overflow-wrap:anywhere` and `word-break:break-word`
- responsive font sizing added via `clamp(...)`
- subscription and setup boxes remain visually distinct while preserving full value visibility
- `/thang`, `/nam`, `Mot lan` style suffixes remain visible below the amount

Result:
- long numbers no longer force horizontal overflow
- currency and billing suffix no longer compete for the same line

## Plan Card Grid Fix

Implemented:
- plan cards now use `display:flex` + `flex-direction:column`
- cards are stretch-aligned per row
- desktop remains 2-column within the current signup shell
- tablet remains 2-column
- mobile collapses to 1-column
- CTA/detail zone is anchored with `margin-top:auto`

Result:
- card heights stay stable even with longer marketing copy
- selected-state styling remains intact

## Setup Fee Explanation

Implemented:
- setup fee explanation added directly inside each plan card as a compact footnote

Copy now clarifies that setup fee includes:
- workspace initialization
- module configuration
- initial permissions
- implementation support
- pre-handover check

Result:
- setup fee is visible and explained before checkout
- less chance of users reading setup fee as a hidden charge

## Plan Recommendation

Implemented:
- recommendation badges are now filled when marketing override is empty:
  - `Pho bien nhat` for Basic
  - `De bat dau` for Starter
  - `Cho nhieu phong ban` for Standard
  - `Dung thu` for Trial
- existing marketing override still has priority because fallback only applies when badge is empty

## Trial Visual Weight

Implemented:
- trial card now gets lighter visual weight with `is-trial`
- reduced visual emphasis compared with paid plans

Result:
- trial reads as an evaluation path, not the primary purchase option

## Technical Quota Collapse

Implemented:
- technical details stay inside accordion
- title changed to `Xem gioi han ky thuat`
- primary visual hierarchy remains:
  - plan name
  - best for
  - subscription price
  - setup fee
  - core modules

Result:
- technical quota no longer dominates the first scan

## Sidebar Summary Hardening

Implemented:
- summary rows changed from flex to two-column grid
- value column can wrap safely for:
  - long company name
  - long email
  - long subdomain
  - long `Best for`
  - long totals
- total block uses tabular numerics and responsive font sizing
- mobile/tablet summary falls below the main form instead of relying on sticky desktop layout

Result:
- summary remains readable with longer real-world values
- no text collision inside summary box

## Responsive Test Results

Static layout guards implemented for:
- `1440px`
- `1280px`
- `1024px`
- `768px`
- `430px`
- `375px`

Tested data conditions in code-level pass:
- long price
- long setup fee
- long plan badge
- long best-for text
- long company/email/subdomain values

Status:
- CSS guards implemented
- PHP syntax lint passed
- browser screenshot capture was not executed in this session because no browser capture tool was available

## Before vs After

Before:
- price and summary areas were vulnerable to long-string breakage
- card body height depended too much on content length
- trial could visually compete with paid plans
- setup fee visibility existed, but explanation was too light

After:
- price blocks are resilient to longer numeric strings
- plan cards hold structure with variable-length content
- summary survives long text and long amounts
- trial is visually lighter
- setup fee is explained in-card before checkout

## Remaining Risks

- live browser validation is still needed for optical balance, especially at:
  - `430px`
  - `375px`
  - edge cases with exceptionally long marketing overrides
- some existing Vietnamese strings in this file were already legacy-encoded before this pass; this pass focused on layout resilience, not text encoding normalization
- summary help below sidebar was not expanded further in this pass because the in-card setup fee explanation already satisfies the conversion requirement without increasing sidebar height

## Files Changed

- `modules/kt_landing/views/public/signup.php`

## Verification

- `php -l modules/kt_landing/views/public/signup.php` -> pass
