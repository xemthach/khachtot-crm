# KT Inventory Packaging Guide

## Create a package manually

Run from `modules/kt_inventory`:

```powershell
$version = Get-Content VERSION
Compress-Archive -Path assets,controllers,docs,helpers,language,models,views,install.php,kt_inventory.php,uninstall.php,VERSION,CHANGELOG.md -DestinationPath "..\\kt_inventory-v$version.zip" -Force
```

## Package naming

- Use `kt_inventory-vX.X.X.zip`
- Example: `kt_inventory-v1.0.0.zip`

## Package contents

The zip should include:

- `assets/`
- `controllers/`
- `docs/`
- `helpers/`
- `language/`
- `models/`
- `views/`
- `install.php`
- `kt_inventory.php`
- `uninstall.php`
- `VERSION`
- `CHANGELOG.md`

## Do not include

- `.git/`
- local IDE files
- temporary logs
- unrelated project files outside `modules/kt_inventory`
