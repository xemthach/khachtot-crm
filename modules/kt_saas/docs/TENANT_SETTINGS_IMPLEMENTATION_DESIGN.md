# TENANT SETTINGS IMPLEMENTATION DESIGN

## 1. Mục tiêu

Tách dứt điểm `Landlord System Settings` khỏi `Tenant Workspace Settings`, đồng thời đưa `Plan Entitlement` xuống mức capability theo từng nhóm setting thay vì chỉ dừng ở `module access` và `usage limits`.

Tài liệu này là bản thiết kế implementation-level để triển khai lần lượt theo domain.

## 2. Kiến trúc mục tiêu

### 2.1 Các lớp bắt buộc

1. `Landlord System Settings`
- provisioning
- modules/app modules
- cron/queue/backup
- shared mail/payment/security infrastructure
- SaaS plans và billing

2. `Tenant Workspace Settings`
- company profile
- branding
- localization
- finance/invoice
- users/roles
- email identity
- module feature settings

3. `Plan Entitlement`
- module access
- settings capability access
- usage limits
- addon flags

4. `Backend Enforcement`
- route guard
- controller/service guard
- DB write guard
- usage/quota guard
- audit log

### 2.2 Quy ước capability

Capability theo module và theo setting phải tách riêng:

- `workspace.company.view`
- `workspace.company.edit`
- `workspace.branding.edit`
- `workspace.localization.edit`
- `workspace.finance.view`
- `workspace.finance.sequence.edit`
- `workspace.finance.collections.edit`
- `workspace.mail.identity.edit`
- `workspace.mail.smtp.edit`
- `workspace.roles.manage`
- `workspace.api_tokens.manage`
- `module.kt_inventory.settings.edit`
- `module.kt_sepay.settings.edit`
- `module.einvoice.settings.edit`

### 2.3 Quy ước storage

1. `tenant DB / tbloptions`
- dùng cho setting business và core tenant-local
- ví dụ: `companyname`, `invoice_prefix`, `active_language`

2. `landlord DB keyed table`
- dùng cho secret hoặc setting module cần tập trung
- ví dụ: `tblkt_sepay_settings`

3. `landlord DB / tenant directory mirror`
- chỉ mirror metadata tối thiểu
- ví dụ: `tblkt_saas_tenants.company_name`, `timezone`, `locale`, `currency`

### 2.4 Quy ước route

- landlord-only:
  - `/admin/settings`
  - `/admin/modules`
  - `/admin/kt_saas/settings`
- tenant-safe:
  - `/admin/kt_saas/tenant_settings`
  - `/admin/kt_saas/tenant_settings/{domain}`
  - route module tenant-safe riêng như `/admin/kt_sepay/tenant_settings`

## 3. Domain ownership matrix

| Domain | Owner | Storage chính | Plan gated |
| --- | --- | --- | --- |
| Company | Tenant | tenant `tbloptions` | Có thể |
| Branding | Tenant | tenant `tbloptions` + tenant upload path | Có |
| Localization | Tenant | tenant `tbloptions` | Có thể |
| Finance core | Tenant | tenant `tbloptions` | Có |
| Mail identity | Tenant | tenant `tbloptions` | Có |
| Mail transport | Landlord/Enterprise | secure table | Có |
| Roles/permissions | Tenant | tenant DB | Có |
| Inventory settings | Tenant | tenant `tbloptions` | Có |
| KT SePay settings | Tenant | landlord keyed table | Có |
| eInvoice settings | Hybrid | tenant options + secure provider config | Có |
| Modules/App_modules | Landlord | landlord/module registry | Không |
| Cron/backup/server info | Landlord | landlord | Không |

## 4. Phase roadmap

### Phase A - Workspace Foundation

1. Company profile
2. Branding
3. Localization
4. Finance basic
5. Activity log UI

### Phase B - Tenant Governance

1. Users
2. Roles
3. Permissions
4. Departments/teams
5. Plan-gated governance

### Phase C - Messaging and Billing Control

1. Mail identity
2. Notification preferences
3. Invoice advanced settings
4. eInvoice tenant settings

### Step C1 - Mail identity

Phạm vi:
- sender display name
- reply-to email
- BCC business copies
- default email signature
- predefined email header/footer

Không bao gồm:
- SMTP host/port/user/pass
- OAuth mail credentials
- queue worker/cron

Storage:
- tenant `tbloptions`
- custom keys:
  - `kt_saas_mail_from_name`
  - `kt_saas_mail_reply_to_email`
- reused tenant-local core keys:
  - `bcc_emails`
  - `email_signature`
  - `email_header`
  - `email_footer`

Enforcement:
- capability `workspace.mail.identity.edit`
- UI lock + backend save guard
- runtime mail hook:
  - override `fromname` theo tenant sender display name
  - inject `reply_to` theo tenant option
- không cho tenant đụng mail transport global

### Step C2 - Notification preferences

Phạm vi:
- invoice overdue reminder days
- invoice due reminder days
- estimate expiry reminder days
- contract expiry reminder days
- contract sign reminder interval
- attach invoice PDF to payment receipt email

Không bao gồm:
- cron execution hour
- queue worker
- desktop notifications
- pusher / realtime infra

Storage:
- tenant `tbloptions`

Enforcement:
- capability `workspace.notifications.edit`
- UI lock + backend save guard
- field bị khóa theo plan sẽ được giữ nguyên server-side, không làm fail việc lưu các nhóm setting khác

### Step C3 - Invoice advanced settings

Phạm vi:
- invoice visibility for logged-in clients
- hide draft invoices/estimates from customer area
- show sale agent / project on invoice and estimate
- show total paid / credits applied / amount due on invoice
- auto-convert estimate to invoice on client acceptance
- subscription payment follow-up action
- recurring invoice creation policy

Không bao gồm:
- delete-last / number decrement destructive controls
- cron execution timing
- payment gateway credentials
- SMTP / queue / landlord infrastructure

Storage:
- tenant `tbloptions`

Capability:
- `workspace.finance.advanced.edit`

Enforcement:
- UI lock + backend save guard
- field bị khóa theo plan sẽ được giữ nguyên server-side
- recurring action enum được whitelist:
  - `generate_and_send`
  - `generate_unpaid`
  - `generate_draft`

### Step C4 - eInvoice tenant settings

Phạm vi:
- default invoice template
- default credit note template
- attach e-Invoice to invoice emails
- attach e-Invoice to credit note emails
- tenant-local template CRUD routed back to tenant settings

Không bao gồm:
- provider API credentials
- tax authority submission flow
- SaaS landlord billing e-invoice issuance
- provider-specific reconciliation infrastructure

Storage:
- tenant `tbloptions` for defaults/toggles
- tenant DB `tbltemplates` for `type = einvoice`

Route:
- `/admin/einvoice/tenant_settings`

Enforcement:
- tenant route only
- do not expose `settings?group=einvoice` to tenant
- template redirect/back links must be tenant-aware

### Phase D - Integrations

1. KT SePay entitlement hardening
2. Tenant payment gateway architecture
3. API tokens
4. Webhooks

### Step D1 - KT SePay entitlement hardening

Phạm vi:
- settings edit
- health check actions
- manual reconciliation
- manual payment request creation
- compatibility gating cho tenant eInvoice settings

Capability:
- `kt_sepay.settings.edit`
- `kt_sepay.health.run`
- `kt_sepay.reconcile.run`
- `kt_sepay.payment_requests.create`
- `einvoice.settings.edit`

Plan UI:
- capability nằm trong nhóm integration/module capabilities
- lưu vào `tblkt_saas_plan_features`

Enforcement:
- menu + button lock
- controller POST guard
- health/test endpoints không chạy nếu plan không cho phép
- fallback tương thích ngược = `true` cho plan cũ chưa có capability rows

### Phase E - Enterprise Controls

1. Custom SMTP
2. White label
3. SSO
4. IP policy
5. Branch-level settings

## 5. Phase A breakdown

### Step A1 - Company profile

Phạm vi:
- `companyname`
- `company_email`
- `companyphonenumber`
- `company_vat`
- mirror tối thiểu sang `tblkt_saas_tenants`

Enforcement:
- tenant admin hoặc delegated workspace manager
- audit log `tenant.workspace_settings_updated`

### Step A2 - Branding

Phạm vi:
- `company_logo`
- `company_logo_dark`
- `favicon`
- preview + remove POST + CSRF

Enforcement:
- tenant upload path riêng
- không ghi đè landlord asset

### Step A3 - Localization

Phạm vi:
- `active_language`
- `default_timezone`
- `default_currency`
- `dateformat`
- `time_format`

Enforcement:
- whitelist giá trị hợp lệ

### Step A4 - Finance basic

Phạm vi:
- `invoice_prefix`
- `next_invoice_number`
- `invoice_number_format`
- `estimate_prefix`
- `next_estimate_number`
- `estimate_number_format`
- `credit_note_prefix`
- `next_credit_note_number`
- `credit_note_number_format`
- `predefined_clientnote_invoice`
- `predefined_terms_invoice`

Storage:
- tenant `tbloptions`

Enforcement:
- route chỉ qua `tenant_settings`
- validate prefix, next number, number format
- audit log cùng `workspace_settings_updated`

### Step A5 - Activity log UI

Phạm vi:
- route read-only cho tenant
- chỉ log của chính tenant
- filter theo `event_key`, `actor_type`, `created_at`

Storage:
- landlord `tblkt_saas_activity_logs`

## 6. Thiết kế plan entitlement cho settings

### 6.1 Cấu trúc feature key

- `workspace.branding.custom_logo`
- `workspace.finance.sequence.edit`
- `workspace.mail.identity.edit`
- `workspace.mail.smtp.edit`
- `workspace.roles.manage`
- `workspace.api_tokens.manage`
- `workspace.einvoice.manage`

### 6.2 Nguyên tắc enforce

1. UI chỉ hiển thị nếu có entitlement
2. Route vẫn phải guard lại
3. Service save phải guard lại lần cuối
4. Job/background process phải check capability trước khi dùng setting

## 7. Backward compatibility

1. Không mở lại `/admin/settings` cho tenant
2. Các setting đang tenant-local trong `tbloptions` tiếp tục được tái sử dụng
3. Với module đã có storage riêng như `KT SePay`, không migrate ngược về `tbloptions`
4. Mirror trong `tblkt_saas_tenants` chỉ giữ metadata runtime cần thiết

## 8. Test checklist chung

### Company/Branding/Localization
- tenant A đổi setting không ảnh hưởng tenant B
- landlord branding không bị tenant ghi đè
- login/admin/client-facing/PDF dùng đúng tenant logo và name

### Finance basic
- tenant lưu prefix/sequence riêng
- invoice/estimate/credit note mới dùng đúng sequence tenant
- tenant không đụng landlord numbering

### Security
- tenant không vào được `/admin/settings`
- tenant không vào được `/admin/modules`
- tenant không xem/sửa dữ liệu tenant khác

## 9. Thứ tự triển khai hiện tại

- Đang triển khai: `Step A4 - Finance basic`
- Kế tiếp: `Step A5 - Activity log UI`
