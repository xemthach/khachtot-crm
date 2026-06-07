# KT SaaS Provisioning Seed Audit (2026-06-01)

## Scope
- Core Perfex data loading for tenant runtime (`get_all_countries`, client profile country field).
- KT SaaS provisioning pipeline:
  - schema clone
  - reference-data seed
  - options seed
  - module seed

## Executive Summary
- **Root cause confirmed:** tenant DB is created from landlord **schema-only**, then only a **small whitelist** of tables is seeded.
- This whitelist previously missed `tblcountries`, which caused country dropdown empty in tenant client profile.
- Current architecture still has a structural gap: **no full baseline seeding policy**, so similar missing-table regressions can continue to happen when new core features depend on additional lookup tables.

---

## Findings (Ordered by Severity)

### 1) Critical - Incomplete reference-data seed policy (structural)
- File: [ProvisioningJobRunner.php](d:/laragon/www/khachtot/modules/kt_saas/provisioning/ProvisioningJobRunner.php:526)
- Behavior:
  - `cloneLandlordSchema()` copies only table structures.
  - `seedReferenceData()` repopulates only a hardcoded table list.
- Impact:
  - Any core/feature table not in whitelist stays empty in tenant.
  - Regression pattern: every new dependency can break tenant UI/API later.
- Evidence:
  - Country list in Perfex is loaded from DB table `tblcountries` via [countries_helper.php](d:/laragon/www/khachtot/application/helpers/countries_helper.php:10).
  - Client profile country field uses `get_all_countries()` in [profile.php](d:/laragon/www/khachtot/application/views/admin/clients/groups/profile.php:197).

### 2) Critical - Country dataset previously missing in tenant seed
- File: [ProvisioningJobRunner.php](d:/laragon/www/khachtot/modules/kt_saas/provisioning/ProvisioningJobRunner.php:531)
- Status:
  - **Fixed in code**: `db_prefix().'countries'` added to seed list.
- Impact before fix:
  - Tenant country select showed empty/non-selected for all forms relying on `get_all_countries()`.

### 3) High - Whitelist-based seed is fragile against Perfex upgrades/modules
- File: [ProvisioningJobRunner.php](d:/laragon/www/khachtot/modules/kt_saas/provisioning/ProvisioningJobRunner.php:526)
- Behavior:
  - New lookup tables introduced by core/module are not auto-seeded unless manually added to `$tables`.
- Impact:
  - Hidden production bugs after upgrades (UI loads but selectors/relations silently empty).

### 4) Medium - No built-in backfill job for already-provisioned tenants
- Files:
  - [ProvisioningJobRunner.php](d:/laragon/www/khachtot/modules/kt_saas/provisioning/ProvisioningJobRunner.php:526)
  - [Kt_saas_cron.php](d:/laragon/www/khachtot/modules/kt_saas/cron/Kt_saas_cron.php:1)
- Behavior:
  - Existing tenants do not get missing reference tables auto-repaired.
- Impact:
  - Fixes in provisioning only help new tenants; old tenants remain inconsistent unless manually patched.

---

## Why This Keeps Reappearing
- Tenant runtime reads directly from tenant DB for reference datasets.
- Provisioning currently has no "authoritative seed contract" that guarantees all required baseline tables are populated.
- Result: each newly discovered dependency becomes a new incident.

---

## Required Direction (as requested: tenant seeded fully, package only limits permissions)

### A. Baseline data policy
1. Define a **baseline seed contract** for all shared lookup/config tables required by Perfex core + approved modules.
2. Enforce this contract in one place (single source of truth), not scattered hardcoded lists.

### B. Deterministic tenant backfill
1. Add a backfill command/job for existing tenants:
   - compare landlord vs tenant row counts for baseline tables
   - truncate+reseed or upsert deterministically
2. Run once for all active tenants after policy deployment.

### C. Permission model boundary
1. Keep tenant data complete.
2. Enforce package limitations only at capability/feature gate layer (UI + endpoint authorization), not by removing baseline reference data.

---

## Immediate Action Plan
1. Keep current country fix (already patched) for new provisioning.
2. Implement backfill for `tblcountries` on all existing tenants first.
3. Expand to full baseline contract and automated nightly drift check report.

---

## Verification Checklist (Post-fix)
- Tenant client profile `country`, `billing_country`, `shipping_country` are populated.
- Lead convert/customer create forms show country list.
- `get_all_countries()` count in tenant DB matches landlord DB.
- No package/plan behavior changes except capability gating.

