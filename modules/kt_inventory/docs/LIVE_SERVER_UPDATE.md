# KT Inventory Live Server Update Guide

## Use case

Deploy or update only `kt_inventory` on another Perfex CRM server without moving the full source project.

## Before deployment

1. Back up the live database.
2. Back up the current `modules/kt_inventory` directory on the target server if it already exists.
3. Confirm the release version in `VERSION` and `CHANGELOG.md`.
4. Prepare the zip package from the release process, for example `kt_inventory-v1.0.0.zip`.

## First install on another server

1. Upload the package to the target server.
2. Extract it into:
   `modules/kt_inventory`
3. Log in to Perfex CRM as admin.
4. Go to Modules and activate `KT Inventory`.
5. Confirm the install hook creates the required tables and options.
6. Open the module pages and assign permissions to the needed staff roles.

## Update an existing server

1. Put the target system into a maintenance window if users are active.
2. Back up:
   - database
   - current `modules/kt_inventory`
3. Upload the new package.
4. Replace the files inside `modules/kt_inventory` with the new release contents.
5. Keep the database intact; the module install script will handle missing tables or fields when activation/install logic runs.
6. If needed, deactivate and reactivate the module once from the admin panel so schema update logic can run.
7. Check the current version by comparing:
   - `modules/kt_inventory/VERSION`
   - module header version in `kt_inventory.php`

## Smoke test after update

1. Open:
   - Dashboard
   - Warehouses
   - Items
   - Stock balance
   - Reports
2. Verify core module functions:
   - create/edit warehouse
   - create/edit inventory item
   - barcode lookup
   - goods receipt draft/post
   - goods issue draft/post
   - adjustment draft/post
   - transfer draft/post
   - import/export
3. Confirm dependent business flows are not broken:
   - lead
   - bao gia
   - R2
   - mail
4. Check browser console and server logs for:
   - HTTP 500
   - missing assets
   - UTF-8 issues

## Recommended update strategy for many servers

1. Keep `modules/kt_inventory` as its own Git repository.
2. Tag every stable version, for example `v1.0.0`, `v1.0.1`, `v1.1.0`.
3. Attach a zip package to each GitHub Release.
4. On each target server, store:
   - current deployed version
   - deployment date
   - rollback package
5. Always deploy from a tagged package, not from an untagged working folder.

## Rollback

1. Restore the previous module files from backup or the previous release zip.
2. Restore the database backup if the failed update changed data or schema in a bad way.
3. Reopen the module and rerun the smoke test.
