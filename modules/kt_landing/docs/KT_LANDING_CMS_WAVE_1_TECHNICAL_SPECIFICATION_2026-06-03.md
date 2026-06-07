# KT LANDING CMS WAVE 1 TECHNICAL SPECIFICATION

Scope:
- Technical specification for Wave 1 implementation.
- Wave 1 includes only:
  1. Global Block System
  2. Pricing Sync Hardening
  3. Landing Clone Engine
  4. Media Center Upgrade
  5. Publish Center Upgrade
- No code changes in this pass.

## 1. Architecture

### Target flow

```text
Admin UI
  -> Controller
    -> Service
      -> Model
        -> DB
          -> Publish Snapshot / Public Render
```

### Wave 1 system boundaries
- Landing CMS remains the landlord-owned marketing control plane
- CRM billing truth remains in `kt_saas_plans`
- public landing templates consume governed content and snapshots
- Wave 1 must not modify signup, billing, or provisioning behavior

### High-level components

| Component | Responsibility |
|---|---|
| Controller | route requests, validate input, call service layer |
| Service | business logic, inheritance rules, publish rules, clone rules |
| Model | read/write DB, query counts, usage graphs, snapshots |
| View | admin screens and controlled publish/preview UX |
| Public renderer | read published content only |

---

## 2. Database Design

### New tables

#### `kt_landing_global_blocks`
Purpose: reusable shared content blocks.

Key columns:
- `id`
- `block_key`
- `block_name`
- `block_type`
- `content_json`
- `status`
- `created_at`
- `updated_at`

Recommended index:
- unique on `block_key`
- index on `block_type`, `status`

#### `kt_landing_block_usage`
Purpose: block dependency graph and delete protection.

Key columns:
- `id`
- `block_id`
- `usage_type`
- `usage_ref_id`
- `usage_ref_key`
- `template_code`
- `page_slug`
- `section_key`
- `created_at`

Recommended index:
- index on `block_id`
- index on `usage_type`, `usage_ref_id`
- index on `template_code`, `page_slug`

### Modified tables

#### `kt_landing_media`
Wave 1 additions:
- usage tracking fields
- metadata fields for alt/title/caption/tags/category
- optional optimization state

#### `kt_landing_publish_snapshots`
Wave 1 additions:
- include resolved content references
- include block usage summary
- include pricing sync state
- include checklist results

### Migration order
1. create `kt_landing_global_blocks`
2. create `kt_landing_block_usage`
3. extend `kt_landing_media`
4. extend `kt_landing_publish_snapshots`

### FK / relationship strategy
- keep foreign keys light if existing deployment conventions avoid strict FK use
- if FK use is allowed, `block_usage.block_id` should reference `global_blocks.id`
- media usage may remain reference-based for compatibility

---

## 3. Controllers

### Existing controller surfaces to extend
- `modules/kt_landing/controllers/Kt_landing_admin.php`
- `modules/kt_landing/controllers/Kt_landing.php`

### Proposed admin endpoints

| Endpoint | Purpose |
|---|---|
| `admin/kt_landing/global_blocks` | list and manage blocks |
| `admin/kt_landing/global_blocks/{id}` | edit block |
| `admin/kt_landing/global_blocks/{id}/duplicate` | duplicate block |
| `admin/kt_landing/global_blocks/{id}/disable` | disable block |
| `admin/kt_landing/global_blocks/{id}/preview` | preview block |
| `admin/kt_landing/pricing_sync` | show sync status |
| `admin/kt_landing/clone` | clone landing wizard |
| `admin/kt_landing/media` | media manager upgrade UI |
| `admin/kt_landing/publish` | publish center upgrade UI |

### Controller responsibilities
- load view models
- enforce permissions
- parse form input
- call service layer
- redirect on success
- return validation errors on failure

### Public controller behavior
- never render draft block or draft snapshot content
- only use published snapshot / published content state
- no change to signup, billing, or provisioning routes

---

## 4. Services

### Recommended service layer

#### `LandingGlobalBlockService`
Responsibilities:
- create block
- update block
- duplicate block
- disable block
- preview block
- build usage graph
- prevent delete if referenced

Methods:
- `createBlock(array $data): array`
- `updateBlock(int $id, array $data): array`
- `duplicateBlock(int $id): array`
- `disableBlock(int $id): bool`
- `getBlockUsageGraph(int $id): array`
- `canDeleteBlock(int $id): bool`

Validation:
- unique block_key
- block_type in allowed list
- content_json valid JSON
- status in allowed list

#### `LandingPricingSyncService`
Responsibilities:
- compare landing overrides with CRM plans
- compute sync state
- lock forbidden fields
- expose mismatch warnings

Methods:
- `buildPricingSyncReport(): array`
- `getPlanSyncState(int $planId): array`
- `resolvePlanForLanding(array $plan): array`
- `detectMismatch(array $plan, array $override): array`

Validation:
- cannot override locked billing fields
- warnings must be explicit

#### `LandingCloneService`
Responsibilities:
- clone pages, sections, menus, SEO metadata
- preserve block references
- exclude analytics/leads/publish history

Methods:
- `cloneTemplate(string $sourceTemplate, array $targetMeta): array`
- `clonePage(int $pageId, array $options): array`
- `cloneSectionSet(array $sectionIds, array $options): array`
- `cloneSeoMeta(int $pageId, int $targetPageId): bool`

Validation:
- clone target name required
- cloned slug unique
- source template exists

#### `LandingMediaService`
Responsibilities:
- metadata management
- usage tracking
- delete protection
- replace asset safely

Methods:
- `updateMetadata(int $mediaId, array $data): bool`
- `trackUsage(int $mediaId, array $usageRef): bool`
- `getUsageSummary(int $mediaId): array`
- `canDeleteMedia(int $mediaId): bool`
- `replaceMedia(int $mediaId, array $fileData): array`

#### `LandingPublishService`
Responsibilities:
- create snapshot
- publish snapshot
- rollback snapshot
- generate publish checklist

Methods:
- `createSnapshot(string $type, array $payload): int`
- `publishSnapshot(int $snapshotId): array`
- `rollbackToSnapshot(int $snapshotId): array`
- `buildPublishChecklist(array $page): array`
- `getSnapshotList(int $limit = 50): array`

Validation:
- snapshot exists
- snapshot is publishable
- checklist passes or produces warnings

---

## 5. Views

### Admin UX views to build or upgrade

| View | Purpose |
|---|---|
| Global Blocks Dashboard | block list, usage counts, create/edit/duplicate/disable |
| Pricing Sync Status | synced/warning/mismatch table |
| Landing Clone Wizard | source template, target metadata, clone preview |
| Media Center Upgrade | asset metadata, usage, replace, delete protection |
| Publish Center Upgrade | snapshot list, publish checklist, rollback flow |

### View behavior rules
- admin descriptions stay admin-only
- publish checklist is visible before publish
- mismatch states are visible and action-oriented
- delete protection reasons are shown to the user

---

## 6. Validation Rules

### Global Block System
- `block_key` required and unique
- `block_type` must be from allowed set
- `content_json` must be valid JSON
- `status` must be active/disabled

### Pricing Sync Hardening
- landing override may only change:
  - display name
  - best for
  - badge
  - CTA
  - display order
  - visibility
- cannot override:
  - price
  - setup fee
  - billing cycle
  - trial days
  - plan code

### Landing Clone Engine
- source template must exist
- target slug must be unique
- clone excludes analytics/leads/publish history
- block references must remain valid

### Media Center
- alt text should be required for SEO-relevant images
- delete is blocked when usage exists
- file type and file size must pass validation

### Publish Center
- snapshot must exist
- publish checklist must be evaluated
- rollback target must exist and be safe to restore

---

## 7. Publish Rules

### Draft
- editable working state
- not public

### Preview
- preview only sees draft or selected snapshot
- public users never see preview-only content

### Publish
- applies a validated snapshot
- writes the active publish state
- emits audit log

### Rollback
- restores prior snapshot
- keeps history intact
- emits audit log

### Precedence at publish
1. CRM plan truth for locked pricing fields
2. Marketplace / content owner truth for catalog fields
3. Landing override for presentation fields
4. Draft snapshot for current working set
5. Published snapshot for public output

---

## 8. Regression Risks

| Risk | Description | Mitigation |
|---|---|---|
| Pricing drift | landing override conflicts with CRM plan truth | enforce sync report and lock fields |
| Block duplication | shared CTA/FAQ/footer changes propagate unexpectedly | usage graph + versioning + preview |
| Clone contamination | analytics/leads/history accidentally copied | explicit exclude list |
| Media deletion breakage | asset removed while referenced | usage tracking + delete protection |
| Publish regression | bad snapshot pushed live | checklist + rollback |
| Public render drift | template consumes wrong data source | strict publish-only rendering |

---

## 9. Test Plan

### Unit
- global block create/update/delete protection
- pricing sync state calculation
- clone validation
- media usage protection
- publish checklist generation

### Integration
- block usage graph across pages
- pricing sync report across CRM plan changes
- clone engine producing a new landing without copying analytics/leads/history
- publish snapshot list and rollback

### Regression
- Template 1 still renders correctly
- signup flow unchanged
- billing and provisioning unchanged
- pricing does not drift from CRM plan source

### Security
- permission checks by role
- delete protection checks
- publish authorization checks
- no unauthorized draft exposure

### Publish
- snapshot creation
- preview state
- publish state
- rollback to prior snapshot

### Clone
- clone landing from Template 1
- validate cloned SEO/menu references
- ensure non-cloned analytics/leads/history

### Rollback
- rollback restores previous snapshot
- public landing reflects restored content
- audit log records the action

---

## 10. Ready To Implement?

Yes, Wave 1 is ready to implement if the team keeps the scope limited to the five approved components and respects the inheritance/precedence rules.

### Implementation order recommendation
1. Global Block System
2. Pricing Sync Hardening
3. Media Center Upgrade
4. Publish Center Upgrade
5. Landing Clone Engine

This order minimizes drift risk and gives the team immediate operational value before introducing clone automation.

