# KT Inventory Release Guide

## Release goal

Release this repository as a standalone Perfex CRM module so it can be:

- versioned independently
- packaged as a zip file
- deployed to another server without copying the whole project
- updated later by replacing the module files and running smoke tests

## Manual release checklist

1. Enter the module repository:
   `cd modules/kt_inventory`
2. Audit local changes:
   `git status`
   `git diff --stat`
   `git diff`
3. Compare with GitHub:
   `git fetch origin`
   If `origin/main` exists:
   `git log origin/main..HEAD`
   `git diff origin/main`
   If the GitHub repo is empty, treat the release as the first public package.
4. Update release metadata:
   - Update `VERSION`
   - Update `CHANGELOG.md`
   - Keep the module header version in `kt_inventory.php` aligned with the release version
5. Run module verification:
   `php -l kt_inventory.php`
   `Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }`
   Optional on a real Perfex instance:
   - activate module
   - open dashboard, items, receipts, issues, reports
   - test import/export and barcode lookup
6. Create the release commit:
   `git add -A`
   `git commit -m "release: vX.X.X"`
7. Create the release tag:
   `git tag vX.X.X`
8. Push code and tag:
   `git push -u origin main`
   `git push origin vX.X.X`
9. Create the package zip:
   `Compress-Archive -Path assets,controllers,docs,helpers,language,models,views,install.php,kt_inventory.php,uninstall.php,VERSION,CHANGELOG.md -DestinationPath ..\\kt_inventory-vX.X.X.zip -Force`
10. Create GitHub Release:
   - Title: `vX.X.X`
   - Body: copy the matching section from `CHANGELOG.md`
   - Attach the zip package `kt_inventory-vX.X.X.zip`

## Versioning rules

- Bug fix only: bump PATCH
- New feature without breaking change: bump MINOR
- Breaking change: bump MAJOR
- First public release to an empty GitHub repo: use `1.0.0`

## Notes for this repository

- This is a module repository, not a full Laravel application.
- `php artisan test` is not a required release step here.
- `npm run build` is not a required release step here unless this module later gets its own real asset build pipeline.
