# KhachTot eInvoice Integration – Comprehensive Design Document

> **Version:** 1.0.0 | **Date:** 2025-05-28 | **Status:** DRAFT

---

## 1. Executive Summary

This document describes the design for integrating **electronic invoicing (eInvoice)** into the KhachTot multi-tenant SaaS platform built on Perfex CRM. The system has two invoice domains:

| Domain | DB | Table | Owner |
|--------|-----|-------|-------|
| **Landlord SaaS Invoices** | Landlord DB | `tblkt_saas_invoices` | Landlord bills tenants for subscriptions |
| **Tenant CRM Invoices** | Tenant DB | `tblinvoices` + `tblitemable` | Tenants bill their own customers |

The eInvoice feature must serve **both** domains, connecting to Vietnam eInvoice providers (VNPT, Viettel, BKAV, etc.) and optionally leveraging the existing **kt_sepay** payment gateway for reconciliation.

---

## 2. Current Architecture Summary

### 2.1 Perfex CRM Invoice Core (Tenant DB)

- **Model:** `application/models/Invoices_model.php`
- **Tables:** `tblinvoices`, `tblitemable`, `tblcreditnotes`, `tblpayments`
- **Statuses:** Draft(6), Sent(4), Unpaid(1), PartiallyPaid(3), Paid(2), Overdue(5), Cancelled(8)
- **Key fields:** `id`, `number`, `prefix`, `clientid`, `date`, `duedate`, `subtotal`, `total_tax`, `total`, `status`, `currency`, `billing_street`, `billing_city`, `billing_state`, `billing_zip`, `billing_country`
- **Items:** Stored in `tblitemable` with `rel_type='invoice'`, fields: `description`, `long_description`, `qty`, `rate`, `unit`, `item_order`, `tax_id`, `tax_name`, `tax_rate`
- **Tax:** Per-item tax in `tblitemable`, tax definitions in `tbltaxes` (name, rate, type)
- **Client data:** `tblclients` → `company`, `vat`, `phonenumber`, `address`, `city`, `state`, `zip`, `country`, custom fields for buyer tax code

### 2.2 KT SaaS Billing Engine (Landlord DB)

- **Service:** `modules/kt_saas/services/BillingEngineService.php`
- **Table:** `tblkt_saas_invoices` (landlord DB)
- **Fields:** `tenant_id`, `subscription_id`, `invoice_number`, `status`, `currency`, `subtotal`, `tax_total`, `discount_total`, `grand_total`, `issued_at`, `due_date`, `paid_at`, `gateway`, `payload_json`, `last_reminder_at`, `reminder_count`
- **Statuses:** `draft`, `pending_payment`, `paid`, `overdue`, `cancelled`
- **Payments:** `tblkt_saas_payments` → `tenant_id`, `invoice_id`, `payment_reference`, `gateway`, `amount`, `currency`, `status`, `paid_at`, `gateway_payload_json`

### 2.3 KT SePay Payment Gateway

- **API Client:** `modules/kt_sepay/libraries/Kt_sepay_api.php` — REST client with retry, rate-limit handling
- **Model:** `modules/kt_sepay/models/Kt_sepay_model.php` — settings per tenant with global fallback, encrypted API tokens
- **Payment Requests:** `tblkt_sepay_payment_requests` — tracks QR code payments with `context_type` (invoice, kt_saas_subscription, manual)
- **Webhook:** Receives bank transaction notifications, matches via reference code, auto-reconciles

### 2.4 Existing eInvoice Module

- **Module:** `modules/einvoice/` — XML/JSON template-based export
- **Purpose:** Generates eInvoice files from Perfex invoices for manual upload to tax portals
- **Limitation:** No API integration with eInvoice providers, no auto-submission, no status tracking

### 2.5 Tenant Entitlement System

- **Service:** `modules/kt_saas/services/TenantEntitlementService.php`
- **Pattern:** `canUseModule($tenantId, 'einvoice')` / `canUseFeature($tenantId, 'einvoice', 'einvoice.auto_submit')`
- **Tables:** `tblkt_saas_plan_features`, `tblkt_saas_tenant_entitlements`, `tblkt_saas_module_catalog`

---

## 3. eInvoice Domain Model

### 3.1 Vietnam eInvoice Requirements

Per Circular 78/2021/TT-BTC and Decree 123/2020/NĐ-CP:

| Field | Description |
|-------|-------------|
| `seller_tax_code` | Mã số thuế người bán |
| `seller_name` | Tên đơn vị bán hàng |
| `seller_address` | Địa chỉ người bán |
| `buyer_tax_code` | MST người mua (if B2B) |
| `buyer_name` | Tên người mua |
| `buyer_address` | Địa chỉ người mua |
| `invoice_series` | Ký hiệu hóa đơn (e.g., 1C24TAA) |
| `invoice_number` | Số hóa đơn |
| `invoice_date` | Ngày hóa đơn |
| `payment_method` | Hình thức thanh toán (TM/CK/TM/CK) |
| `currency` | Đồng tiền thanh toán |
| `exchange_rate` | Tỷ giá (if not VND) |
| `items[]` | Line items with: name, unit, qty, unit_price, amount, tax_rate, tax_amount |
| `total_before_tax` | Tổng tiền chưa thuế |
| `total_tax` | Tổng tiền thuế |
| `total_payment` | Tổng tiền thanh toán |

### 3.2 eInvoice Lifecycle

```
DRAFT → PENDING_SIGN → SUBMITTED → ISSUED → [ADJUSTMENT|REPLACEMENT|CANCELLED]
```

- **DRAFT:** eInvoice record created from CRM/SaaS invoice
- **PENDING_SIGN:** Awaiting digital signature (HSM or local cert)
- **SUBMITTED:** Sent to tax authority (GĐT) via provider API
- **ISSUED:** Tax authority accepted, official invoice number assigned
- **ADJUSTMENT:** Correction invoice issued (hóa đơn điều chỉnh)
- **REPLACEMENT:** Replacement invoice issued (hóa đơn thay thế)
- **CANCELLED:** Cancelled with tax authority

---

## 4. Database Schema Design

### 4.1 eInvoice Provider Settings (Landlord DB)

```sql
CREATE TABLE IF NOT EXISTS `tblkt_einvoice_settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NULL COMMENT 'NULL = global/landlord default',
  `provider` VARCHAR(50) NOT NULL DEFAULT 'vnpt' COMMENT 'vnpt|viettel|bkav|softdreams|custom',
  `environment` VARCHAR(20) NOT NULL DEFAULT 'sandbox',
  `api_url` VARCHAR(500) NULL,
  `api_username_encrypted` VARCHAR(500) NULL,
  `api_password_encrypted` VARCHAR(500) NULL,
  `api_token_encrypted` VARCHAR(500) NULL,
  `seller_tax_code` VARCHAR(20) NOT NULL DEFAULT '',
  `seller_name` VARCHAR(500) NOT NULL DEFAULT '',
  `seller_address` VARCHAR(1000) NOT NULL DEFAULT '',
  `seller_phone` VARCHAR(50) NULL,
  `seller_email` VARCHAR(255) NULL,
  `seller_bank_account` VARCHAR(100) NULL,
  `seller_bank_name` VARCHAR(255) NULL,
  `invoice_series` VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'Ký hiệu hóa đơn',
  `invoice_type` VARCHAR(10) NOT NULL DEFAULT '1' COMMENT '1=GTGT, 2=Bán hàng',
  `default_payment_method` VARCHAR(50) NOT NULL DEFAULT 'CK' COMMENT 'TM|CK|TM/CK',
  `auto_submit_on_send` TINYINT(1) NOT NULL DEFAULT 0,
  `auto_submit_on_payment` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  UNIQUE KEY `uk_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4.2 eInvoice Records (Tenant DB for CRM invoices, Landlord DB for SaaS invoices)

```sql
CREATE TABLE IF NOT EXISTS `tblkt_einvoices` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `source_type` VARCHAR(50) NOT NULL COMMENT 'crm_invoice|saas_invoice',
  `source_id` INT UNSIGNED NOT NULL COMMENT 'FK to tblinvoices.id or tblkt_saas_invoices.id',
  `tenant_id` INT UNSIGNED NULL COMMENT 'NULL for landlord self-billing',
  `provider` VARCHAR(50) NOT NULL,
  `environment` VARCHAR(20) NOT NULL DEFAULT 'sandbox',
  
  -- Seller info (snapshot at creation)
  `seller_tax_code` VARCHAR(20) NOT NULL,
  `seller_name` VARCHAR(500) NOT NULL,
  `seller_address` VARCHAR(1000) NOT NULL DEFAULT '',
  
  -- Buyer info (snapshot at creation)
  `buyer_tax_code` VARCHAR(20) NULL,
  `buyer_name` VARCHAR(500) NOT NULL DEFAULT '',
  `buyer_address` VARCHAR(1000) NOT NULL DEFAULT '',
  `buyer_email` VARCHAR(255) NULL,
  
  -- Invoice identification
  `invoice_series` VARCHAR(20) NULL COMMENT 'Ký hiệu (from provider)',
  `invoice_number` VARCHAR(50) NULL COMMENT 'Số HĐ (from tax authority)',
  `invoice_date` DATE NULL,
  `payment_method` VARCHAR(50) NOT NULL DEFAULT 'CK',
  `currency` VARCHAR(10) NOT NULL DEFAULT 'VND',
  `exchange_rate` DECIMAL(18,6) NOT NULL DEFAULT 1.000000,
  
  -- Amounts (snapshot)
  `total_before_tax` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `total_tax` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `total_payment` DECIMAL(18,2) NOT NULL DEFAULT 0,
  
  -- Status tracking
  `status` VARCHAR(30) NOT NULL DEFAULT 'draft' COMMENT 'draft|pending_sign|submitted|issued|adjustment|replacement|cancelled|error',
  `provider_invoice_id` VARCHAR(255) NULL COMMENT 'ID from provider system',
  `provider_lookup_code` VARCHAR(255) NULL COMMENT 'Mã tra cứu',
  `provider_response_json` TEXT NULL,
  `error_message` TEXT NULL,
  `submitted_at` DATETIME NULL,
  `issued_at` DATETIME NULL,
  `cancelled_at` DATETIME NULL,
  
  -- Linked eInvoice (for adjustment/replacement)
  `original_einvoice_id` INT UNSIGNED NULL COMMENT 'FK self-ref for adjustments',
  `adjustment_type` VARCHAR(20) NULL COMMENT 'adjustment|replacement',
  
  -- Items JSON (denormalized snapshot)
  `items_json` MEDIUMTEXT NULL,
  
  -- Audit
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  
  INDEX `idx_source` (`source_type`, `source_id`),
  INDEX `idx_tenant` (`tenant_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_provider_id` (`provider_invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4.3 eInvoice Activity Log

```sql
CREATE TABLE IF NOT EXISTS `tblkt_einvoice_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `einvoice_id` INT UNSIGNED NOT NULL,
  `action` VARCHAR(100) NOT NULL COMMENT 'created|submitted|issued|cancelled|error|retry',
  `status_before` VARCHAR(30) NULL,
  `status_after` VARCHAR(30) NULL,
  `response_json` TEXT NULL,
  `note` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_einvoice` (`einvoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 5. Service Architecture

### 5.1 Class Diagram

```
EInvoiceService (orchestrator)
├── EInvoiceProviderFactory
│   ├── VnptProvider implements EInvoiceProviderInterface
│   ├── ViettelProvider implements EInvoiceProviderInterface
│   ├── BkavProvider implements EInvoiceProviderInterface
│   └── CustomProvider implements EInvoiceProviderInterface
├── EInvoiceMapper
│   ├── mapCrmInvoice(invoice) → EInvoiceDTO
│   └── mapSaasInvoice(saasInvoice, tenant) → EInvoiceDTO
└── EInvoiceModel (DB operations)
```

### 5.2 Provider Interface

```php
interface EInvoiceProviderInterface
{
    public function authenticate(array $settings): bool;
    public function createInvoice(EInvoiceDTO $dto): ProviderResult;
    public function signAndSubmit(string $providerInvoiceId): ProviderResult;
    public function getStatus(string $providerInvoiceId): ProviderResult;
    public function cancelInvoice(string $providerInvoiceId, string $reason): ProviderResult;
    public function createAdjustment(string $originalId, EInvoiceDTO $adjustmentDto): ProviderResult;
    public function createReplacement(string $originalId, EInvoiceDTO $replacementDto): ProviderResult;
    public function downloadPdf(string $providerInvoiceId): ?string;
    public function downloadXml(string $providerInvoiceId): ?string;
}
```

### 5.3 EInvoiceDTO (Data Transfer Object)

```php
class EInvoiceDTO
{
    public string $sourceType;      // 'crm_invoice' | 'saas_invoice'
    public int    $sourceId;
    public ?int   $tenantId;
    
    // Seller
    public string $sellerTaxCode;
    public string $sellerName;
    public string $sellerAddress;
    
    // Buyer
    public ?string $buyerTaxCode;
    public string  $buyerName;
    public string  $buyerAddress;
    public ?string $buyerEmail;
    
    // Invoice meta
    public string $invoiceSeries;
    public string $invoiceDate;
    public string $paymentMethod;
    public string $currency;
    public float  $exchangeRate;
    
    // Amounts
    public float $totalBeforeTax;
    public float $totalTax;
    public float $totalPayment;
    
    // Line items
    public array $items; // EInvoiceItemDTO[]
}
```

### 5.4 Mapper Logic

**CRM Invoice → eInvoice:**
```
tblinvoices.id → source_id (source_type = 'crm_invoice')
tblclients.company → buyer_name
tblclients.vat → buyer_tax_code
tblclients.address + city + state + zip → buyer_address
tblitemable (rel_type=invoice) → items[]
tblinvoices.subtotal → total_before_tax
tblinvoices.total_tax → total_tax
tblinvoices.total → total_payment
seller info from tblkt_einvoice_settings (tenant_id = current)
```

**SaaS Invoice → eInvoice:**
```
tblkt_saas_invoices.id → source_id (source_type = 'saas_invoice')
tblkt_saas_tenants.company_name → buyer_name
tblkt_saas_tenants.tax_code → buyer_tax_code (new field needed)
tblkt_saas_invoices.subtotal → total_before_tax
tblkt_saas_invoices.tax_total → total_tax
tblkt_saas_invoices.grand_total → total_payment
seller info from tblkt_einvoice_settings (tenant_id = NULL, landlord)
```

---

## 6. Integration Points

### 6.1 CRM Invoice Hooks (Tenant Context)

| Perfex Hook | Trigger |
|-------------|---------|
| `after_invoice_sent` | Auto-submit eInvoice if `auto_submit_on_send=1` |
| `after_invoice_payment_added` | Auto-submit if `auto_submit_on_payment=1` and fully paid |
| `before_invoice_deleted` | Cancel eInvoice if issued |
| `invoice_status_changed` | Sync eInvoice status |

### 6.2 SaaS Invoice Integration (Landlord Context)

- After `BillingEngineService::markInvoicePaid()` → trigger eInvoice creation for landlord
- After `BillingEngineService::createSubscriptionInvoice()` → optionally create draft eInvoice
- Landlord admin can manually issue eInvoice for any SaaS invoice

### 6.3 SePay Reconciliation

When SePay webhook confirms payment:
1. Payment request marked paid
2. CRM invoice / SaaS invoice marked paid
3. If `auto_submit_on_payment=1` → eInvoice auto-submitted

---

## 7. Entitlement & Feature Flags

### 7.1 Module Catalog Entry

```sql
INSERT INTO tblkt_saas_module_catalog (module_name, display_name, slug, is_global_active)
VALUES ('einvoice', 'Hóa đơn điện tử', 'einvoice', 1);
```

### 7.2 Feature Keys

| Feature Key | Description | Default |
|-------------|-------------|---------|
| `einvoice.access` | Module access | Per plan |
| `einvoice.auto_submit` | Auto-submit on send/payment | Per plan |
| `einvoice.adjustment` | Create adjustment invoices | Per plan |
| `einvoice.replacement` | Create replacement invoices | Per plan |
| `einvoice.api_providers` | Allowed providers (comma-separated) | `vnpt` |

### 7.3 Plan Limits

| Limit Key | Column in `tblkt_saas_plans` |
|-----------|------------------------------|
| `einvoices_monthly` | `limit_einvoices_monthly` |

---

## 8. API Endpoints

### 8.1 Tenant Admin Routes

```
GET    admin/einvoice/settings          → Settings form
POST   admin/einvoice/settings          → Save settings
GET    admin/einvoice/list              → List eInvoices
GET    admin/einvoice/view/{id}         → View eInvoice detail
POST   admin/einvoice/create/{invoice_id} → Create eInvoice from CRM invoice
POST   admin/einvoice/submit/{id}       → Submit to provider
POST   admin/einvoice/cancel/{id}       → Cancel eInvoice
GET    admin/einvoice/download_pdf/{id} → Download PDF
GET    admin/einvoice/download_xml/{id} → Download XML
POST   admin/einvoice/check_status/{id} → Re-check status from provider
```

### 8.2 Landlord Admin Routes

```
GET    admin/kt_saas/einvoice_settings     → Landlord eInvoice settings
POST   admin/kt_saas/einvoice_settings     → Save landlord settings
GET    admin/kt_saas/einvoice_list         → List SaaS eInvoices
POST   admin/kt_saas/einvoice_create/{saas_invoice_id} → Create from SaaS invoice
POST   admin/kt_saas/einvoice_submit/{id}  → Submit to provider
```

---

## 9. Data Flow Diagrams

### 9.1 CRM Invoice → eInvoice (Tenant)

```
┌─────────────┐     ┌────────────────┐     ┌──────────────┐     ┌──────────────┐
│ Perfex CRM  │────▶│ EInvoiceService│────▶│ Provider API │────▶│ Tax Authority│
│ Invoice Sent│     │ mapCrmInvoice  │     │ (VNPT/etc)   │     │ (GĐT)       │
└─────────────┘     │ createInvoice  │     │ sign+submit  │     │ issue number │
                    │ store record   │     └──────┬───────┘     └──────────────┘
                    └────────────────┘            │
                                                  ▼
                                      ┌──────────────────┐
                                      │ tblkt_einvoices   │
                                      │ status → issued   │
                                      │ invoice_number set│
                                      └──────────────────┘
```

### 9.2 SaaS Invoice → eInvoice (Landlord)

```
┌──────────────────┐     ┌────────────────┐     ┌──────────────┐
│ BillingEngine    │────▶│ EInvoiceService│────▶│ Provider API │
│ markInvoicePaid  │     │ mapSaasInvoice │     │ (landlord    │
│                  │     │ createInvoice  │     │  provider)   │
└──────────────────┘     └────────────────┘     └──────────────┘
```

### 9.3 SePay → Payment → eInvoice

```
┌──────────┐     ┌──────────────┐     ┌──────────────────┐     ┌────────────────┐
│ Bank Txn │────▶│ SePay Webhook│────▶│ Payment Reconcile│────▶│ EInvoiceService│
│          │     │              │     │ mark invoice paid│     │ auto-submit    │
└──────────┘     └──────────────┘     └──────────────────┘     └────────────────┘
```

---

## 10. Migration Requirements

### 10.1 New Tables

1. `tblkt_einvoice_settings` — Provider settings (landlord DB)
2. `tblkt_einvoices` — eInvoice records (tenant DB for CRM, landlord DB for SaaS)
3. `tblkt_einvoice_logs` — Activity log (same DB as parent einvoice)

### 10.2 Schema Additions to Existing Tables

```sql
-- Add tax_code to tenants table for SaaS invoicing
ALTER TABLE `tblkt_saas_tenants`
  ADD COLUMN `tax_code` VARCHAR(20) NULL AFTER `company_name`,
  ADD COLUMN `billing_address` VARCHAR(1000) NULL AFTER `tax_code`;

-- Add eInvoice limit to plans
ALTER TABLE `tblkt_saas_plans`
  ADD COLUMN `limit_einvoices_monthly` INT NOT NULL DEFAULT 0 AFTER `limit_governance_managers`;
```

---

## 11. Settings Storage Pattern

Follow `kt_sepay` pattern: **settings stored in landlord DB** with `tenant_id` column.

- `tenant_id = NULL` → landlord/global default settings
- `tenant_id = X` → tenant-specific provider config
- Fallback: tenant settings → global settings (same as SePay)
- Encrypted fields for API credentials using same `kt_sepay_encrypt_value` / `kt_sepay_decrypt_value` helpers (or new `kt_einvoice_*` equivalents)

---

## 12. Security Considerations

1. **API credentials** stored encrypted (AES-256-CBC) in landlord DB
2. **Entitlement check** before every eInvoice operation via `TenantEntitlementService`
3. **Tenant isolation** — tenant can only access own eInvoices
4. **Rate limiting** — respect provider API rate limits with retry logic (same pattern as `Kt_sepay_api::request()`)
5. **Audit trail** — all eInvoice actions logged in `tblkt_einvoice_logs`
6. **Webhook verification** — if provider sends callbacks, verify signature

---

## 13. File Structure

```
modules/einvoice/
├── install.php
├── einvoice.php                          # Module init (hooks, menu)
├── controllers/
│   └── Einvoice.php                      # Admin controller
├── models/
│   └── Einvoice_model.php                # DB operations
├── services/
│   ├── EInvoiceService.php               # Orchestrator
│   ├── EInvoiceMapper.php                # Invoice → DTO mapping
│   └── EInvoiceProviderFactory.php       # Provider factory
├── providers/
│   ├── EInvoiceProviderInterface.php     # Contract
│   ├── VnptProvider.php                  # VNPT eInvoice API
│   ├── ViettelProvider.php               # Viettel S-Invoice API
│   ├── BkavProvider.php                  # BKAV eHoadon API
│   └── SoftdreamsProvider.php            # Softdreams API
├── libraries/
│   └── Einvoice_api.php                  # HTTP client (same pattern as Kt_sepay_api)
├── helpers/
│   └── einvoice_helper.php               # Utility functions
├── language/
│   ├── english/einvoice_lang.php
│   └── vietnamese/einvoice_lang.php
├── views/
│   ├── admin/
│   │   ├── settings.php
│   │   ├── list.php
│   │   └── view.php
│   └── tenant/
│       └── einvoice_list.php
├── migrations/
│   └── 001_version_001.php
└── docs/
    └── EINVOICE_INTEGRATION_DESIGN.md    # This file
```

---

## 14. Implementation Phases

### Phase 1: Foundation (MVP)
- [ ] Database tables creation (migration)
- [ ] Settings UI (admin, tenant-level)
- [ ] EInvoiceModel CRUD
- [ ] EInvoiceMapper for CRM invoices
- [ ] VNPT provider implementation
- [ ] Manual create/submit eInvoice from CRM invoice detail page
- [ ] eInvoice list view + status display
- [ ] Entitlement integration (`einvoice.access`)

### Phase 2: Automation
- [ ] Auto-submit on invoice send (hook `after_invoice_sent`)
- [ ] Auto-submit on payment confirmed (hook + SePay reconcile)
- [ ] PDF/XML download from provider
- [ ] Status polling / webhook handling
- [ ] Dunning integration (eInvoice for overdue SaaS invoices)

### Phase 3: SaaS Landlord Integration
- [ ] EInvoiceMapper for SaaS invoices
- [ ] Landlord settings UI
- [ ] Auto-issue eInvoice when SaaS subscription payment received
- [ ] `tax_code` + `billing_address` fields on tenant profile

### Phase 4: Advanced
- [ ] Adjustment/replacement invoice workflow
- [ ] Viettel/BKAV/Softdreams providers
- [ ] Batch eInvoice submission
- [ ] Monthly usage limit enforcement (`einvoices_monthly`)
- [ ] eInvoice reports/statistics dashboard

---

## 15. Key Design Decisions

| Decision | Rationale |
|----------|-----------|
| **Settings in landlord DB** | Consistent with kt_sepay; landlord controls provider access |
| **eInvoice records in same DB as source** | CRM einvoices in tenant DB (alongside tblinvoices), SaaS einvoices in landlord DB (alongside tblkt_saas_invoices) |
| **Provider interface pattern** | Swap providers without changing core logic |
| **Snapshot buyer/seller info** | eInvoice must reflect data at issuance time, not current |
| **items_json denormalized** | eInvoice items frozen at submission; no FK to mutable tblitemable |
| **Reuse kt_sepay encryption helpers** | Same security pattern, avoid duplication |
| **Hook-based integration** | Non-invasive; does not modify Perfex core files |

---

## 16. Dependencies

| Component | Purpose |
|-----------|---------|
| Perfex CRM Core | Invoice model, hooks system, client data |
| KT SaaS Module | Entitlements, billing engine, tenant context |
| KT SePay Module | Payment reconciliation triggers (optional) |
| PHP `curl` | Provider API communication |
| PHP `openssl` | Credential encryption |

---

## 17. Open Questions

1. **Which eInvoice provider first?** VNPT recommended as most common in Vietnam market.
2. **Should landlord be able to override tenant provider choice?** Suggested: yes, via entitlement `einvoice.api_providers`.
3. **Digital signature (HSM) integration?** Most providers handle signing server-side; no local cert needed for API-based flow.
4. **Multi-currency eInvoice?** Vietnam tax authority requires VND amounts with exchange rate for foreign currency invoices.
5. **Batch submission limits?** Depends on provider — VNPT allows ~100/batch, Viettel ~50/batch.