# WAVE 1 SPRINT 2 REPORT

Scope:
- Pricing Sync Hardening for KT Landing CMS Wave 1.
- Source of truth is `kt_saas_plans`.
- No redesign of landing, no SEO Center, no Clone Engine.
- No changes to billing, signup, provisioning, or plan pricing data.

## Pricing Audit

### Current pricing data flow
- Landing pricing and signup pricing resolve from `kt_saas_plans`.
- Tenant subscription pricing also resolves from the same CRM plan source.
- Billing and checkout continue to use the CRM plan source of truth.
- Landing-specific pricing overrides are now limited to presentation fields only.

### Audited surfaces
- Public landing pricing
- Public signup plan selection
- Tenant subscription plans
- Billing/checkout price display

### Source of truth
- `kt_saas_plans` owns:
  - `price`
  - `setup_fee`
  - `billing_cycle`
  - `trial_days`
  - `plan_code`

### Landing-owned overrides
- `display_name`
- `badge`
- `best_for`
- `cta_text`
- `cta_url`
- `display_order`
- `visibility`
- `marketing_description`
- `marketing_subtitle`

## Sync Service

### Service added
- `modules/kt_landing/services/LandingPricingSyncService.php`

### Methods implemented
- `getPlanSyncState()`
- `buildPricingSyncReport()`
- `detectMismatch()`
- `resolvePlanForLanding()`
- `syncPlanOverride()`
- `saveOverride()`

### Behavior
- Locked CRM fields are never overridden by landing:
  - price
  - setup_fee
  - billing_cycle
  - trial_days
  - plan_code
- Landing overrides are sanitized and merged through the sync service.
- Snapshot metadata is stored with each override row to detect drift.

### Snapshot metadata stored
- `source_plan_snapshot_hash`
- `source_plan_snapshot_json`
- `source_plan_updated_at`
- `last_synced_at`

## Sync Status UI

### Admin UI updated
- `modules/kt_landing/views/admin/pricing.php`

### UI states shown
- Synced
- Warning
- Mismatch

### UI now displays
- Plan
- Price
- Setup Fee
- Billing Cycle
- Sync State
- CRM source lock
- editable marketing override fields

### Actions available
- Save override
- Sync CRM source

## Locked Fields

The following fields are locked to CRM source truth:
- price
- setup_fee
- billing_cycle
- trial_days
- plan_code

Admin UI shows these as read-only CRM source values.

## Override Fields

The following fields remain editable for landing presentation:
- display_name
- badge
- best_for
- CTA text
- CTA URL
- display_order
- visibility
- marketing_description
- marketing_subtitle

## Mismatch Detection

### Detection rule
- A mismatch is raised when the stored landing snapshot hash differs from the current CRM plan snapshot hash.

### Warning behavior
- If a landing override attempts to write locked fields, the service strips the values and logs a warning.
- If the CRM plan changes after the landing snapshot was last synced, the row is marked mismatch.

### Browser verification
- A controlled snapshot-hash tamper was applied to one plan row to prove mismatch detection.
- The admin pricing UI showed:
  - 3 synced rows
  - 1 mismatch row
- The mismatch row displayed the expected warning state and reason.
- The row was restored to synced state after verification.

## Audit Logs

Pricing sync events now log through the landing activity log path:
- `pricing.sync`
- `pricing.warning`
- `pricing.mismatch`
- `pricing.override_updated`

## Tests

### Static checks
- `php -l` passed for:
  - `modules/kt_landing/install.php`
  - `modules/kt_landing/models/Kt_landing_model.php`
  - `modules/kt_landing/helpers/kt_landing_helper.php`
  - `modules/kt_landing/services/LandingPricingSyncService.php`
  - `modules/kt_landing/controllers/Kt_landing_admin.php`
  - `modules/kt_landing/controllers/Kt_landing_public.php`
  - `modules/kt_landing/views/admin/pricing.php`

### Database checks
- Pricing sync metadata columns exist in `kt_landing_plan_overrides`.
- A synced snapshot state was verified and restored for the test row.

### Browser checks
- Admin pricing sync UI loaded successfully.
- Sync summary rendered.
- Locked CRM source block rendered.
- Editable override fields rendered.
- Mismatch state rendered during controlled tamper test.

## Screenshots

Captured during verification:
- `modules/kt_landing/docs/screenshots/sprint2-pricing-admin-synced.png`
- `modules/kt_landing/docs/screenshots/sprint2-pricing-admin-mismatch.png`
- `modules/kt_landing/docs/screenshots/sprint2-pricing-admin-restored.png`
- `modules/kt_landing/docs/screenshots/sprint2-landing-home.png`
- `modules/kt_landing/docs/screenshots/sprint2-landing-pricing.png`
- `modules/kt_landing/docs/screenshots/sprint2-signup.png`
- `modules/kt_landing/docs/screenshots/sprint2-tenant-subscription.png`

## Regression Result

Verified no changes to:
- signup flow
- billing flow
- checkout flow
- provisioning flow
- Template 1 landing rendering

Pricing display remains consistent across:
- landing
- signup
- tenant subscription
- billing/checkout

## Ready For Sprint 2A Verification?

Yes.

Sprint 2 is functionally complete for pricing sync hardening:
- CRM plan truth is enforced
- landing overrides are presentation-only
- mismatch detection is active
- sync state UI is visible
- locked fields are protected
- audit logs are emitted
