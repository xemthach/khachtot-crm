# WAVE 1 SPRINT 1 REPORT

Scope:
- Implement Global Block System only.
- No landing redesign.
- No pricing redesign.
- No signup logic change.
- No billing/provisioning logic change.

## DB Changes

### New tables
- `kt_landing_global_blocks`
  - `id`
  - `block_key`
  - `block_name`
  - `block_type`
  - `content_json`
  - `status`
  - `created_by`
  - `updated_by`
  - `created_at`
  - `updated_at`
- `kt_landing_block_usage`
  - `id`
  - `block_id`
  - `usage_type`
  - `usage_ref_type`
  - `usage_ref_id`
  - `usage_ref_key`
  - `usage_label`
  - `source_field`
  - `is_primary`
  - `created_at`
  - `updated_at`

### Notes
- `kt_landing_block_usage.block_id` has FK cascade to `kt_landing_global_blocks.id`.
- No breaking schema changes were made to landing, signup, billing, or provisioning tables.

## Services

### Added
- `modules/kt_landing/services/LandingGlobalBlockService.php`

### Methods
- `createBlock()`
- `updateBlock()`
- `duplicateBlock()`
- `disableBlock()`
- `canDeleteBlock()`
- `getBlockUsageGraph()`
- `syncUsage()`
- `getUsageSummary()`

### Behavior
- Creates and updates global blocks.
- Duplicates blocks with a unique key suffix.
- Disables blocks without removing them from the registry.
- Scans current landing content sources for explicit block references.
- Produces usage graphs for the admin dashboard.

## Controllers

### Updated
- `modules/kt_landing/controllers/Kt_landing_admin.php`

### Added endpoint
- `global_blocks()`

### Behavior
- Handles create, edit, duplicate, disable, and delete actions.
- Enforces delete protection through `canDeleteBlock()`.
- Loads usage graph and summary for the dashboard.
- Keeps existing admin routes unchanged.

## Views

### Added
- `modules/kt_landing/views/admin/global_blocks.php`

### Updated
- `modules/kt_landing/views/admin/overview.php`

### UI coverage
- Create form
- Edit form
- Duplicate action
- Disable action
- Preview panel
- Usage graph
- Block list
- Delete button with protection

## Validation

- `block_key` is normalized and preserved on update.
- `block_name` is required.
- `block_type` defaults to a controlled type list.
- `status` is restricted to `active` or `disabled`.
- `block_key` is preserved after creation to avoid breaking references.
- `content_json` is stored as raw JSON text or encoded JSON when passed as an array.
- `content_json` is validated as JSON before save when content is present.

## Usage Tracking

- Usage is tracked by scanning explicit block reference tokens in current landing data.
- Tracked reference scopes:
  - Landing
  - Page
  - Section
  - Template
  - Nested block references
- Dashboard shows:
  - total usage count
  - usage by type
  - reference list with source field

## Delete Protection

- Block deletion is blocked when usage rows exist.
- The delete action is disabled in the table when usage count is non-zero.
- The service exposes `canDeleteBlock()` for reuse by future admin flows.

## Audit Logs

- Logged through existing KT SAAS activity log pipeline:
  - `landing.global_block.created`
  - `landing.global_block.updated`
  - `landing.global_block.duplicated`
  - `landing.global_block.disabled`
  - `landing.global_block.deleted`

## Tests

### Syntax checks
- `php -l modules/kt_landing/install.php`
- `php -l modules/kt_landing/models/Kt_landing_model.php`
- `php -l modules/kt_landing/controllers/Kt_landing_admin.php`
- `php -l modules/kt_landing/kt_landing.php`
- `php -l modules/kt_landing/services/LandingGlobalBlockService.php`
- `php -l modules/kt_landing/views/admin/global_blocks.php`
- `php -l modules/kt_landing/views/admin/overview.php`

### Regression checks
- Existing Template 1 files were not edited in this sprint.
- Existing landing public routes were not modified.
- Pricing, signup, billing, and provisioning flows were not modified.
- Admin menu addition is isolated to the Landing CMS sidebar.

## Screenshots

- Not captured in this terminal session.
- The new dashboard view is ready for a manual admin browser pass.

## Regression Result

- No syntax regressions detected.
- No public landing, signup, billing, or provisioning code paths were changed.
- Global Block System is isolated behind a new admin capability and a new admin page.

## Ready For Sprint 2?

- Yes, from a code and lint standpoint.
- Recommended next step is a manual browser smoke on the new Global Blocks dashboard before expanding into the next Wave 1 item.
