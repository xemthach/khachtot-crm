# Changelog

## [1.0.0] - 2026-05-25

### Added
- Initial public release of the KT Inventory module for Perfex CRM.
- Warehouse management with staff manager assignment and active/inactive controls.
- Inventory item master data with SKU rules, min/max stock, activation state, and automatic sync from newly created core items.
- Multi-barcode management with primary barcode selection, generated internal barcodes, EAN-13 validation, Code 128 support, and GS1 parsing for lot, expiry, and serial data.
- Stock balance dashboard, stock transaction history, reservations, and low-stock reporting.
- Goods receipt, goods issue, stock adjustment, and stock transfer workflows with draft, post, and cancel actions.
- CSV and Excel exports for stock balances and transactions, plus CSV import/export templates for items and stock balances.
- Batch and lot tracking with expiry alerts, QC status management, and recall trace reporting.
- Invoice reservation automation hooks for create, update, cancel, and delete events.
- Installation and uninstall scripts for inventory tables, barcode tables, reservation tables, options, and legacy data migration.
- Built-in module test controller for GS1, reporting, reservation, and concurrency scenarios.

### Fixed
- None.

### Changed
- None.

### Removed
- None.

### Security
- Added capability-based access control for inventory menus, actions, APIs, and barcode scan endpoints.
