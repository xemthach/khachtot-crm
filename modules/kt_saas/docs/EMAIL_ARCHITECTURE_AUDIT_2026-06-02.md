# EMAIL ARCHITECTURE AUDIT

Ngày audit: 2026-06-02  
Phạm vi: Perfex core + `KT SAAS` + `KT Inventory` + `KT SePay` + `KT Mắt Bão Invoice` + các luồng landing/signup/checkout liên quan email

## Kết luận ngắn

Hệ thống email của repo này **không phải UI-only**. Có đủ 3 lớp thật:

1. **Core mail engine**: `App_email`, `App_mail_template`, `Emails_model`, `application/config/email.php`
2. **Template mailer**: `tblemailtemplates` + `application/libraries/mails/*`
3. **KT SAAS runtime resolver**: `TenantEmailProviderService` + hooks + log table

Điểm sai chính không nằm ở việc “có gửi mail hay không”, mà nằm ở:

- Provider / fallback / runtime transport bị phân tán giữa core và module
- UI chưa phản ánh đầy đủ context provider
- Một số template trong DB không có class tương ứng
- Một số template có trong DB nhưng đang inactive
- Một số module nghiệp vụ không có mailer riêng, chủ yếu chỉ là webhook/log/provisioning

---

## 1) File audit theo prompt

| Thành phần | File | Hiện trạng | Sai ở đâu | Đề xuất |
|---|---|---|---|---|
| Email queue engine | `application/libraries/App_email.php` | Có queue thật qua `tblmail_queue`, hỗ trợ pending/sending/sent/failed | Queue là core chung, chưa có lớp quan sát riêng cho KT SAAS | Giữ core queue, thêm correlation id/log mapping nếu cần truy vết tenant/provider |
| Mail template engine | `application/libraries/mails/App_mail_template.php` | Có gửi template thật, hỗ trợ Brevo API và CI mailer | Provider decision nằm ở nhiều nơi, dễ lệch runtime state | Chuẩn hóa provider decision qua resolver output trước khi gửi |
| Simple mail sender | `application/models/Emails_model.php` | Có gửi mail thật, support Brevo API/SMTP, có hook sent/failed | Error handling trước đây quá chung chung | Giữ detailed last error, map lỗi ra UI/debug cụ thể |
| Core email config | `application/config/email.php` | Có config runtime thật, đọc `email_protocol`, SMTP, OAuth, runtime transport | Brevo API bị map về `smtp` ở config layer, dễ gây hiểu nhầm | Tách rõ “transport mode” và “provider mode” trong audit/diagnostic |
| Email template helpers | `application/helpers/email_templates_helper.php` | Có parse, create, active check template | Không phải nơi quyết định provider | Chỉ dùng cho template lifecycle, không dùng để quyết định transport |
| Email helper | `application/helpers/email_helper.php` | Không tồn tại | Prompt có nhắc file này nhưng repo không có | Ghi rõ “missing file”, không suy diễn |
| KT SAAS resolver | `modules/kt_saas/services/TenantEmailProviderService.php` | Có resolver thật cho tenant/global, fallback, entitlement, quota, runtime transport, logs | Resolver và UI chưa cùng một nguồn state | Trả metadata rõ hơn cho UI để hide/show field theo provider |
| KT SAAS hooks | `modules/kt_saas/kt_saas.php` | Có hooks before_send / sent / failed cho template và simple mail | Nếu hook fail thì UI vẫn có thể chỉ báo chung chung | Chuẩn hóa error surface ở controller/action test |
| KT SAAS model | `modules/kt_saas/models/Kt_saas_model.php` | Lưu global/tenant email settings, enforce provider-specific fields | Nếu lưu field sai ngữ cảnh sẽ làm bẩn option set | Validate theo whitelist provider, null field không liên quan |
| KT SAAS landlord settings UI | `modules/kt_saas/views/dashboard/settings.php` | Có conditional UI theo provider | Vẫn cần đảm bảo group field hide/disable chính xác | Render theo provider, disable field không dùng |
| KT SAAS tenant settings UI | `modules/kt_saas/views/tenant/settings.php` | Có conditional UI theo provider | Cần khớp với entitlement + provider runtime | Chỉ show field hợp lệ theo provider hiện tại |
| Perfex core email settings UI | `application/views/admin/settings/includes/email.php` | Có SMTP, Microsoft, Google, Sendmail/Mail, Brevo API | Dễ lẫn giữa core settings và KT SAAS overlay | Audit rõ core vs module overlay, tránh double-source |

---

## 2) Source of truth thực sự

### Core transport

- `application/config/email.php` là runtime config cho mailer core.
- `App_email` là queue engine dùng `tblmail_queue`.
- `App_mail_template` là template sender chính.
- `Emails_model` là simple email sender + test path.

### KT SAAS overlay

- `TenantEmailProviderService` là lớp quyết định provider theo:
  - tenant options
  - landlord/global options
  - entitlement
  - quota
  - fallback policy
- `kt_saas_mail_runtime_transport` là runtime override vào `application/config/email.php`
- `tblkt_saas_email_logs` là log layer của module

### Kết luận kiến trúc

Không có một file duy nhất làm “email architecture source of truth”.  
Hiện tại là mô hình nhiều lớp:

1. Core engine
2. Core template system
3. KT SAAS resolver/overlay
4. Module hooks

Đây là kiến trúc hợp lệ, nhưng phải audit rõ:

- ai quyết định provider
- ai quyết định fallback
- ai quyết định template scope
- ai ghi log
- ai test connection

---

## 3) Provider / Fallback / UI audit

### Xác định provider thực sự đang dùng

Hệ thống support thật:

- `SMTP`
- `Brevo SMTP`
- `Brevo API`

Không phải UI giả.

### UI chọn provider có ảnh hưởng không?

Có. Nhưng chỉ đúng nếu:

- resolver runtime đã được set
- controller/action test đi qua resolver
- field lưu đúng theo provider
- template/simple mail path không tự rơi về core config cũ

### Có EmailProviderResolver không?

Có:

- `modules/kt_saas/services/TenantEmailProviderService.php`

### Có Tenant Email Override không?

Có:

- tenant-specific email settings
- landlord/global fallback
- hooks inject payload/header

### Có Fallback Policy thật không?

Có. Hiện có ít nhất:

- `use_landlord`
- `block_sending`

Fallback được quyết định ở resolver, không chỉ ở UI.

### Có Mail Logs thật không?

Có:

- `tblkt_saas_email_logs`
- log sent/failed qua hooks

### Có Queue riêng cho email không?

Không có queue riêng của KT SAAS.

Có queue core:

- `tblmail_queue`
- do `App_email` quản lý

---

## 4) Template inventory

### Tổng quan DB templates

`tblemailtemplates` hiện có:

- `english`: 85
- `vietnamese`: 85
- distinct slug: 85

Type breakdown:

- `client`: 18
- `contract`: 14
- `credit_note`: 2
- `estimate`: 12
- `estimate_request`: 6
- `gdpr`: 4
- `inventory_warning`: 2
- `invoice`: 14
- `leads`: 4
- `notifications`: 2
- `project`: 18
- `proposals`: 14
- `staff`: 12
- `subscriptions`: 12
- `superadmin`: 4
- `tasks`: 18
- `ticket`: 14

### Inactive templates

Chỉ có 2 slug đang inactive cả 2 ngôn ngữ:

- `estimate-request-received-to-user`
- `new-web-to-lead-form-submitted`

### Orphan templates trong DB nhưng không có mail class tương ứng

Có 3 slug trong DB không tìm thấy class file tương ứng:

- `inventory-warning-to-staff`
- `tenant-expiration-reminder`
- `we-found-your-tenant-url`

### Mail class vs DB consistency

Mình đã đối chiếu class file trong `application/libraries/mails/` với slug DB.
Kết quả:

- gần như 1:1
- lỗi chính là **3 orphan templates** ở trên
- không phát hiện tình trạng “class có mà DB không có” theo scan hiện tại

---

## 5) Action inventory: nơi nào gửi mail

### Core `send_mail_template()`

Các điểm gọi chính:

- `application/helpers/subscriptions_helper.php`
- `application/helpers/gdpr_helper.php`
- `application/helpers/contracts_helper.php`
- `application/helpers/clients_helper.php`
- `application/controllers/gateways/Stripe.php`
- `application/controllers/Forms.php`
- `application/controllers/admin/Authentication.php`
- `application/models/Tickets_model.php`
- `application/models/Tasks_model.php`
- `application/models/Subscriptions_model.php`
- `application/models/Staff_model.php`
- `application/models/Proposals_model.php`
- `application/models/Projects_model.php`
- `application/models/Payments_model.php`
- `application/models/Leads_model.php`
- `application/models/Estimate_request_model.php`
- `application/models/Estimates_model.php`
- `application/models/Cron_model.php`
- `application/models/Clients_model.php`
- `application/models/Authentication_model.php`

### `send_simple_email()` callers

- `application/models/Cron_model.php`
- `application/controllers/admin/Misc.php`
- `modules/kt_saas/controllers/Kt_saas.php`

### Notification vs email

Rất nhiều luồng trong core là **internal notification** chứ không phải email:

- tasks
- projects
- tickets
- payments
- proposals
- estimates
- newsfeed

Điểm này quan trọng vì prompt yêu cầu audit “email architecture”, không được nhầm notification nội bộ thành email transport.

---

## 6) KT SAAS email architecture

### Tình trạng hiện tại

KT SAAS đã làm đúng một phần lớn kiến trúc:

- có resolver
- có fallback
- có runtime override
- có logs
- có entitlement check
- có test endpoint

### Điểm còn rủi ro

1. Core config và module runtime cùng tác động lên mailer.
2. UI state chưa luôn phản ánh runtime provider.
3. Test email path có thể trả lỗi chung chung nếu controller nuốt lỗi.
4. Field lưu settings phải chặt theo provider, nếu không sẽ làm bẩn state.

### Provider support thật

- `system_smtp`
- `brevo_smtp`
- `brevo_api`

### Entitlement

Resolver có check entitlement cho:

- `email.brevo_api`
- `email.brevo_smtp`
- `email.custom_smtp`

### Log

Mỗi email có thể được log theo outcome:

- sent
- failed

---

## 7) Module audit

### KT Inventory

Kết quả scan:

- không thấy direct mail send path trong module root scan
- module có low-stock notification setting
- hiện chủ yếu là inventory/reporting, chưa phải email-heavy module

Kết luận:

- không có evidence rằng module này tự quản mail architecture riêng
- nếu có mail, nhiều khả năng đi qua core notification/email stack

### KT SePay

Kết quả scan:

- webhook / reconciliation / health check / logs là trọng tâm
- không thấy direct email send path trong module root scan

Kết luận:

- module này hiện là payment integration layer
- không có mail architecture riêng đáng kể trong scan hiện tại

### KT Mắt Bão Invoice

Kết quả scan:

- trọng tâm là invoice / CA-HSM / signing / provisioning / templates / logs
- không thấy direct email send path trong module root scan

Kết luận:

- module này là integration/provisioning layer
- email nếu có sẽ đi qua core

### Landing / Signup / Checkout

Scan hiện tại cho thấy:

- landing module tồn tại
- signup/checkout flow đang được xây dựng
- email liên quan chủ yếu là onboarding / provisioning / notification / lead flow

Nhưng **không phải nơi chính của mail engine**.

---

## 8) UI audit: provider fields

### Core settings UI

File:

- `application/views/admin/settings/includes/email.php`

Hiện trạng:

- SMTP fields
- Microsoft OAuth
- Google OAuth
- Sendmail / Mail
- Brevo API

Đây là UI core, không phải KT SAAS.

### KT SAAS landlord UI

File:

- `modules/kt_saas/views/dashboard/settings.php`

Hiện trạng:

- provider-specific render đã có
- vẫn cần giữ field group thật chặt theo provider

### KT SAAS tenant UI

File:

- `modules/kt_saas/views/tenant/settings.php`

Hiện trạng:

- provider-specific render đã có
- cần đồng bộ thêm với entitlement + runtime context

### Sai kiến trúc UI khi provider = Brevo API nhưng vẫn thấy SMTP fields

Đúng là sai mental model nếu lộ field không liên quan.

Tuy nhiên:

- backend support là thật
- vấn đề nằm ở render / hide / disable / validation

Đề xuất:

- SMTP -> hiện SMTP fields
- Brevo SMTP -> hiện SMTP relay fields
- Brevo API -> chỉ hiện API key / sender / reply-to

---

## 9) Gaps còn lại cần xử lý

1. **Report hóa orphan templates**
   - `inventory-warning-to-staff`
   - `tenant-expiration-reminder`
   - `we-found-your-tenant-url`

2. **Inactive templates**
   - `estimate-request-received-to-user`
   - `new-web-to-lead-form-submitted`

3. **Email test error surface**
   - cần trả lỗi cụ thể thay vì toast chung

4. **Keep one source of truth for provider decision**
   - resolver phải là nguồn chính
   - UI chỉ phản ánh trạng thái runtime

5. **Separate core config vs KT SAAS runtime override**
   - tránh hiểu nhầm giữa `email_protocol` core và provider overlay của KT SAAS

---

## 10) Chốt template: giữ / bổ sung / tắt

### Giữ

Giữ toàn bộ template đang active và có class xử lý tương ứng trong core mailer.

Những template này đã là nguồn chuẩn hiện tại, không cần tạo bản mới trùng lặp.

### Bổ sung

Các template có DB row nhưng chưa có mail class tương ứng cần được bổ sung handler hoặc xác nhận là legacy-only:

| Slug | Hiện trạng | Cần làm |
|---|---|---|
| `inventory-warning-to-staff` | Có template DB, chưa có mail class | Bổ sung class gửi mail hoặc tách sang notification-only nếu không dùng email |
| `tenant-expiration-reminder` | Có template DB, chưa có mail class | Bổ sung flow gửi mail nếu feature tenant expiry reminder còn dùng |
| `we-found-your-tenant-url` | Có template DB, chưa có mail class | Bổ sung flow onboarding/mail recovery nếu feature này còn dùng |

### Tắt

Các template đang inactive nhưng vẫn có reference trong code/migration:

| Slug | Hiện trạng | Kết luận |
|---|---|---|
| `estimate-request-received-to-user` | Inactive | Giữ trạng thái tắt cho đến khi estimate request user flow được kích hoạt lại |
| `new-web-to-lead-form-submitted` | Inactive | Giữ trạng thái tắt cho đến khi web-to-lead flow được bật lại |

### Kết luận template

- Không nên duplicate template chỉ để “cho đủ danh sách”.
- Cái cần làm thật là:
  - bổ sung handler cho 3 slug orphan nếu business vẫn cần
  - giữ 2 template inactive ở trạng thái tắt, không xóa bừa
  - tiếp tục dùng 80+ template active còn lại làm source of truth

---

## 11) Kiến trúc đúng đề xuất

### Lớp 1: Core transport

- `application/config/email.php`
- `App_email`
- `Emails_model`
- `App_mail_template`

### Lớp 2: Template repository

- `tblemailtemplates`
- `application/libraries/mails/*`

### Lớp 3: Module resolver

- `TenantEmailProviderService`
- landlord/tenant settings
- entitlement
- quota
- fallback

### Lớp 4: Observability

- `tblkt_saas_email_logs`
- hooks sent/failed
- test endpoints

### Lớp 5: UI

- chỉ render field hợp lệ theo provider
- không để field thừa gây nhiễu

---

## 12) Kết luận cuối

Audit cho thấy hệ thống email đã có đủ cấu trúc thật để vận hành:

- core mail engine có thật
- template system có thật
- KT SAAS provider resolver có thật
- logs có thật
- queue có thật

Nhưng kiến trúc hiện tại vẫn cần chuẩn hóa ở 3 điểm:

1. **Provider decision phải tập trung vào resolver**
2. **UI phải phản ánh đúng provider, không lộ field thừa**
3. **Template inventory phải được dọn orphan/inactive rõ ràng**

Nếu muốn làm tiếp đúng thứ tự, bước sau nên là:

1. chốt danh sách template cần giữ / cần bổ sung / cần tắt
2. chuẩn hóa provider UI theo state
3. chuẩn hóa email test/error output
