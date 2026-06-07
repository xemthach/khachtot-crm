# KT Inventory

Standalone inventory management module for Perfex CRM.

## Overview

`KT Inventory` adds warehouse and stock operations to Perfex CRM, packaged as an independent module repository so it can be versioned, released, and deployed separately across multiple servers.

## Main features

- Warehouse management
- Inventory item master data
- Multi-barcode support
- GS1 barcode parsing
- Batch, lot, expiry, and serial tracking
- Goods receipts
- Goods issues
- Stock adjustments
- Stock transfers
- Reservations and stock balance tracking
- Transaction history and reporting
- CSV and Excel import/export helpers

## Repository structure

- `kt_inventory.php`: module bootstrap
- `install.php`: schema and option installer
- `uninstall.php`: cleanup logic
- `controllers/`, `models/`, `views/`: module MVC code
- `assets/`: module CSS and JS
- `language/`: translation files
- `VERSION`: current module version
- `CHANGELOG.md`: release history
- `docs/`: release, packaging, and live server update guides

## Installation

1. Copy this module into:
   `modules/kt_inventory`
2. Log in to Perfex CRM as admin.
3. Open the Modules page.
4. Activate `KT Inventory`.
5. Assign permissions to the appropriate staff roles.

## Update flow

1. Back up the database and current module folder.
2. Replace `modules/kt_inventory` with the new tagged release package.
3. Re-activate the module if schema update logic needs to run.
4. Run smoke tests on warehouse, item, receipt, issue, adjustment, transfer, and reports.

## Version

Current version: `1.0.0`

## Release docs

- See [CHANGELOG.md](CHANGELOG.md)
- See [docs/RELEASE.md](docs/RELEASE.md)
- See [docs/PACKAGING.md](docs/PACKAGING.md)
- See [docs/LIVE_SERVER_UPDATE.md](docs/LIVE_SERVER_UPDATE.md)
