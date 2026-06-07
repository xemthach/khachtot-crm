# EMAIL MATRIX & NOTIFICATION COVERAGE AUDIT

Phạm vi audit:
- Core Perfex mail engine và template registry.
- `KT SAAS`, `KT SePay`, `KT MatBao Invoice`, `KT Inventory`, `KT Landing`.
- Các luồng signup / payment / provisioning / invoice / notification liên quan.

Ngoài phạm vi:
- Không sửa mail engine, provider resolver, queue, hay UI trong báo cáo này.
- `modules/kt_signup` và `modules/kt_checkout` không tồn tại trong repo hiện tại, nên không có code để scan.

## 1. Kết luận nhanh

- Core Perfex đang gửi mail thật qua `App_mail_template`, `Emails_model`, `App_email`, `application/config/email.php`.
- `KT SAAS` đã có resolver thật cho provider/fallback/log/entitlement tại `TenantEmailProviderService`.
- `KT SAAS` đang là lớp điều phối email/runtime context chính cho tenant, không phải UI giả.
- `KT Inventory`, `KT MatBao Invoice`, `KT SePay` chủ yếu bám vào core Perfex + hook nghiệp vụ, không có mail engine riêng.
- `KT Landing`/signup đang là lớp public onboarding + billing/provisioning entry, không có mail class riêng.
- Có 3 template orphan/inactive phải giữ trạng thái `inactive` nếu chưa muốn xóa:
  - `inventory-warning-to-staff`
  - `tenant-expiration-reminder`
  - `we-found-your-tenant-url`
- Hai template trước đó bị tắt nhầm đã được bật lại và nên giữ:
  - `estimate-request-received-to-user`
  - `new-web-to-lead-form-submitted`

## 2. Audit nguồn gửi mail

### Core Perfex
- `application/helpers/email_templates_helper.php:107` - `send_mail_template()` là wrapper chuẩn vào mail class.
- `application/models/Emails_model.php:174` - `send_simple_email()`.
- `application/models/Emails_model.php:464` - `send_email_template()`.
- `application/libraries/mails/App_mail_template.php:167` - hook `before_email_template_send`.
- `application/libraries/mails/App_mail_template.php:224` - hook `email_template_sent`.
- `application/libraries/mails/App_mail_template.php:234` - hook `failed_to_send_email_template`.
- `application/libraries/mails/App_mail_template.php:448` - hook `email_template_from_headers`.
- `application/libraries/App_email.php:10` - queue engine `mail_queue`.

### KT SAAS
- `modules/kt_saas/services/TenantEmailProviderService.php:20` - resolve tenant runtime.
- `modules/kt_saas/services/TenantEmailProviderService.php:93` - resolve global runtime.
- `modules/kt_saas/services/TenantEmailProviderService.php:207` - apply runtime transport/identity.
- `modules/kt_saas/services/TenantEmailProviderService.php:266` - email log insert.
- `modules/kt_saas/services/TenantEmailProviderService.php:324` - entitlement gate.
- `modules/kt_saas/services/TenantEmailProviderService.php:367` - quota gate.
- `modules/kt_saas/services/TenantEmailProviderService.php:403` - schema bootstrap.
- `modules/kt_saas/kt_saas.php:238` - email hooks wired.
- `modules/kt_saas/kt_saas.php:279` - one-time template state sync.
- `modules/kt_saas/kt_saas.php:683` - template sent logger.
- `modules/kt_saas/kt_saas.php:741` - simple email sent logger.
- `modules/kt_saas/kt_saas.php:765` - simple email failed logger.

### KT SePay
- `modules/kt_sepay/libraries/Kt_sepay_processor.php:148` - invoice payment context.
- `modules/kt_sepay/libraries/Kt_sepay_processor.php:187` - queue provisioning after public signup payment.
- `modules/kt_sepay/libraries/Kt_sepay_processor.php:233` - provisioning queue helper.

### KT MatBao Invoice
- `modules/kt_matbao_invoice/kt_matbao_invoice.php:21` - module init.
- `modules/kt_matbao_invoice/kt_matbao_invoice.php:142` - menu registration.
- `modules/kt_matbao_invoice/controllers/Kt_matbao_invoice_tenant.php:207` - tenant settings flow.
- `modules/kt_matbao_invoice/controllers/Kt_matbao_invoice_tenant.php:649` - create/issue invoice flow.

### KT Landing
- `modules/kt_landing/controllers/Kt_landing.php:45` - pricing.
- `modules/kt_landing/controllers/Kt_landing.php:57` - signup.
- `modules/kt_landing/controllers/Kt_landing.php:637` - signup stays draft, provisioning deferred.
- `modules/kt_landing/controllers/Kt_landing.php:663` - invoice ready logic.
- `modules/kt_landing/controllers/Kt_landing.php:715` - preferred checkout URL.

## 3. Event inventory

### Core Perfex events

| Event | Trigger file | Recipient | Email? | Notification? | Template / class | Status |
|---|---|---:|---|---|---|---|
| Estimate request submitted | `application/controllers/Forms.php:231` | Staff | Yes | No | `estimate_request_form_submitted` | Active |
| Estimate request received | `application/controllers/Forms.php:237` | User | Yes | No | `estimate_request_received_to_user` / `Estimate_request_received_to_user.php` | Active |
| Lead web form submitted | `application/controllers/Forms.php:481` / `:570` | Staff / lead | Yes | No | `lead_web_form_submitted` / `Lead_web_form_submitted.php` | Active |
| GDPA removal request | `application/controllers/Forms.php:647` / `application/controllers/Clients.php:1411` | Staff | Yes | No | `gdpr_removal_request_by_lead`, `gdpr_removal_request_by_customer` | Active |
| Ticket reply / created / auto-close | `application/models/Tickets_model.php:556,592,869,898,932,1156` | Staff / customer | Yes | No | `ticket_new_reply_to_staff`, `ticket_new_reply_to_customer`, `ticket_assigned_to_staff`, `ticket_created_to_staff`, `ticket_auto_close_to_customer` | Active |
| Task assigned / reminder / deadline | `application/models/Tasks_model.php:1054,1137,1753,1780,2335` + `application/models/Cron_model.php:1035` | Staff / customer | Yes | No | `task_added_as_follower_to_staff`, `task_assigned_to_staff`, `task_deadline_reminder_to_staff`, `task_status_changed_to_*` | Active |
| Invoice send / overdue / due / batch / payment recorded | `application/models/Invoices_model.php:1445,1529,1661` + `application/models/Payments_model.php:363,540,561` | Customer / staff | Yes | No | `invoice_send_to_customer`, `invoice_overdue_notice`, `invoice_due_notice`, `invoice_batch_payments`, `invoice_payment_recorded_to_customer` | Active |
| Estimate accepted / declined / expiry | `application/models/Estimates_model.php:872,890,921,1200,1319` | Staff / customer | Yes | No | `estimate_accepted_to_customer`, `estimate_accepted_to_staff`, `estimate_declined_to_staff`, `estimate_expiration_reminder` | Active |
| Contract signed / reminder / expiration / comments | `application/models/Contracts_model.php:301,313,571` + `application/models/Cron_model.php:384,451` | Staff / customer | Yes | No | `contract_send_to_customer`, `contract_sign_reminder_to_customer`, `contract_expiration_reminder_to_staff`, `contract_expiration_reminder_to_customer` | Active |
| Proposal flow | `application/models/Proposals_model.php:518,528,808,811,817,1037` | Staff / customer | Yes | No | `proposal_send_to_customer`, `proposal_comment_to_staff`, `proposal_comment_to_customer`, `proposal_accepted_to_*`, `proposal_declined_to_staff` | Active |
| Subscription flow | `application/models/Subscriptions_model.php:95` + `application/models/Cron_model.php:906-932` + `application/controllers/gateways/Stripe.php:324,363,420` | Customer / staff | Yes | No | `subscription_send_to_customer`, `subscription_payment_succeeded`, `subscription_payment_failed`, `subscription_payment_requires_action` | Active |
| Customer registration / verification / password reset | `application/models/Clients_model.php:604,702,1589,1605` + `application/models/Authentication_model.php:332,392,506,508` | Contact / staff | Yes | No | `customer_created_welcome_mail`, `customer_registration_confirmed`, `customer_contact_verification`, `customer_contact_set_password`, `customer_contact_forgot_password`, `staff_forgot_password`, `staff_password_resetted` | Active |
| Staff onboarding / 2FA / reminder | `application/models/Staff_model.php:463` + `application/controllers/admin/Authentication.php:64` + `application/models/Cron_model.php:1085` | Staff | Yes | No | `staff_created`, `staff_two_factor_auth_key`, `staff_reminder` | Active |

### KT SAAS events

| Event | Trigger file | Recipient | Email? | Notification? | Template / class | Status |
|---|---|---:|---|---|---|---|
| Global email test | `modules/kt_saas/controllers/Kt_saas.php:874` | Admin | Yes | No | Simple email test via resolver | Active |
| Tenant email test | `modules/kt_saas/controllers/Kt_saas.php:1238` | Tenant admin | Yes | No | Simple email test via resolver | Active |
| Public signup submit | `modules/kt_landing/controllers/Kt_landing.php:57` + `:628` | System / tenant owner | No direct mail class in landing | Yes (activity/log) | Signup flow / invoice ready / provision queue | Active |
| Signup invoice ready | `modules/kt_landing/controllers/Kt_landing.php:663-684` | Public signup owner | No direct mail class in landing | Yes (activity/log) | Uses KT SAAS billing + checkout URL | Active |
| Provisioning queued after payment | `modules/kt_sepay/libraries/Kt_sepay_processor.php:187,233` | Internal ops | No direct mail class in scan | Yes (activity/log) | Queue job `provision_tenant` | Active |
| Tenant email provider change / save | `modules/kt_saas/controllers/Kt_saas.php:1200,1238` + model | Tenant admin | No direct mail class | No | Provider config only | Active |
| Quota exceeded | `modules/kt_saas/services/TenantEmailProviderService.php:367` | N/A | No | No | Block at resolver/log layer | Active |

### KT MatBao Invoice / KT Inventory / KT SePay

| Event | Trigger file | Recipient | Email? | Notification? | Template / class | Status |
|---|---|---:|---|---|---|---|
| eInvoice create / issue / sign / download | `modules/kt_matbao_invoice/controllers/Kt_matbao_invoice_tenant.php:649-1028` | Tenant admin / backend user | No dedicated mail class found | Yes (status/log) | Uses invoice settings + provider credentials | Active |
| eInvoice quota exceeded | `modules/kt_matbao_invoice/controllers/Kt_matbao_invoice_tenant.php:663-677` | Tenant admin | No | Yes (alert) | Quota guard | Active |
| Inventory low stock / warning | `modules/kt_inventory/kt_inventory.php:126-149` | Staff | No direct mail send call found | Yes (workflow) | Orphan template exists only | Weak / orphan |
| SePay webhook payment / reconcile | `modules/kt_sepay/libraries/Kt_sepay_processor.php:56-114,148-187` | Internal / tenant flow | No direct mail class found | Yes (webhook logs + queue job) | Payment request / provisioning | Active |

## 4. Template inventory

### Kept active

| Slug | Module / file | Trigger | Recipient | Status |
|---|---|---|---|---|
| `estimate-request-received-to-user` | `application/libraries/mails/Estimate_request_received_to_user.php:5,11` | `application/controllers/Forms.php:237` | User | Keep active |
| `new-web-to-lead-form-submitted` | `application/libraries/mails/Lead_web_form_submitted.php:5,9` | `application/controllers/Forms.php:481,570` | Staff / lead | Keep active |
| Core Perfex templates | `application/migrations/*`, `application/libraries/mails/*` | Core models/controllers | Staff / customer / contact | Active |

### Keep inactive

| Slug | Module / file | Reason | Status |
|---|---|---|---|
| `inventory-warning-to-staff` | orphan only, no class found in `application/libraries/mails` | no code path found in scan | Keep inactive |
| `tenant-expiration-reminder` | orphan only, no class found in `application/libraries/mails` | no code path found in scan | Keep inactive |
| `we-found-your-tenant-url` | orphan only, no class found in `application/libraries/mails` | no code path found in scan | Keep inactive |

### Missing / not implemented as dedicated template classes

| Needed template | Current state | Gap |
|---|---|---|
| Tenant welcome email | handled only by signup/provisioning flows | no dedicated template class found |
| Provisioning success / failed | handled via activity/status, not email | missing template + trigger |
| Payment success / failure for tenant signup | partially covered by core invoice/payment templates | no tenant-specific template |
| Quota warning / quota exhausted | resolver blocks, but no dedicated email class | missing dedicated notification template |
| Backup success / failed | no mail class found | missing |
| Webhook failed / reconciliation failed | webhook logs exist, mail class missing | missing |
| HSM expiry warning | no mail class found | missing |
| MatBao invoice issue / sign completion mail | no dedicated mail class found | missing |

## 5. Branding audit

| Thành phần | Source of truth | Hiện trạng | Risk |
|---|---|---|---|
| Global email header/footer | `application/views/admin/settings/includes/email.php:251-252` + `application/controllers/admin/Emails.php:246,261-262` | core Perfex vẫn dùng landlord options | landlord branding lấn tenant nếu runtime context không được set đúng |
| Tenant branding | `modules/kt_saas/views/tenant/settings.php:19-21,353-397` | tenant có logo light/dark + favicon riêng | tốt, nhưng phụ thuộc option resolution đúng |
| Landing branding | `modules/kt_landing/controllers/Kt_landing.php:277-283,360-363` | landing đang lấy `companyname/company_logo/favicon` từ option | nếu tenant runtime active, có thể hiển thị sai brand |
| Tenant branding in landing template | `modules/kt_landing/views/public/templates/fastwork_inspired/index.php:71-72,376,381` | reuse `brand_name`, `logo`, `favicon` | phải đảm bảo runtime option resolver đúng host/context |
| Email sender identity | `modules/kt_saas/services/TenantEmailProviderService.php:207,226,244` | runtime identity được đẩy vào mail payload | đúng kiến trúc |

Nhận định:
- Landlord branding và tenant branding đang cùng tồn tại.
- Điểm cần kiểm tra kỹ là các view public/landing đang dùng `get_option('companyname')`, `company_logo`, `favicon`.
- Nếu request tenant runtime không bật đúng, branding sẽ rơi về landlord.

## 6. Merge field audit

### Đã có sẵn
- `invoice_link` - `application/libraries/merge_fields/Invoice_merge_fields.php:12,203`
- `estimate_link` - `application/libraries/merge_fields/Estimate_merge_fields.php:12,103`
- `contract_link` - `application/libraries/merge_fields/Contract_merge_fields.php:54,110`
- `lead_name` - `application/libraries/merge_fields/Leads_merge_fields.php:12,181,214`
- `plan_name` - nhiều query/setting path trong `modules/kt_saas/models/Kt_saas_model.php:804,996,1476,1550,2273...`
- `pdf_url` / `xml_url` - `modules/kt_matbao_invoice/controllers/Kt_matbao_invoice_tenant.php:476-477,915-916,1002-1003`

### Chưa thấy merge field riêng trong scan
- `tenant_url`
- `workspace_url`
- `payment_url`
- `quota_remaining`
- `quota_limit`
- `hsm_expiry`

### Kết luận merge field
- Core Perfex đã có các link merge field chuẩn cho invoice/estimate/contract.
- KT MatBao Invoice có `pdf_url`/`xml_url` ở controller data layer, nhưng chưa thấy merge field email riêng.
- KT SAAS chưa có bộ merge field riêng cho tenant URL / workspace URL / quota warning / HSM expiry trong scan hiện tại.

## 7. Notification coverage audit

| Module | Email coverage | Notification coverage | Nhận xét |
|---|---|---|---|
| Core Perfex | mạnh | mạnh | có mail class đầy đủ, hook phong phú |
| KT SAAS | mạnh ở resolver + log | vừa | có email context/log/provider, nhưng nhiều luồng vẫn phụ thuộc core template |
| KT SePay | yếu | mạnh | webhook / reconcile / payment request chủ yếu là trạng thái, không có mail class riêng |
| KT MatBao Invoice | yếu | mạnh | quota / issue / sign / download là workflow + alert + log |
| KT Inventory | rất yếu | trung bình | có orphan template nhưng scan không thấy consumer mail rõ ràng |
| KT Landing | vừa | mạnh | public signup / invoice ready / status flow có log + redirect, không có mail class riêng |

## 8. Duplicate notifications

### Không thấy duplicate send trực tiếp cùng slug trong cùng 1 trigger path

### Overlap/risk được ghi nhận
1. `estimate_request_form_submitted` và `estimate_request_received_to_user`
   - cùng domain “estimate request”, nhưng khác recipient.
   - không phải duplicate sai, chỉ là hai template cho 2 audience khác nhau.

2. `lead_web_form_submitted` và `new-web-to-lead-form-submitted`
   - cùng domain “web to lead”.
   - một class hiện tại đã map về slug chuẩn `new-web-to-lead-form-submitted`.

3. Invoice payment path
   - Core Perfex payment/invoice emails và KT SAAS landing/signup invoice path có thể cùng chạm vào một sự kiện tài chính.
   - cần guard runtime để tránh gửi trùng khi workflow public signup đi qua SePay + billing engine.

4. KT SAAS logging hooks
   - `email_template_sent`, `failed_to_send_email_template`, `simple_email_sent`, `simple_email_failed`
   - nếu runtime transport không được reset đúng, có thể log trùng về cùng trạng thái.

## 9. Missing templates / missing coverage

Ưu tiên cao:
- Provisioning success/failure email.
- Tenant welcome email sau signup / payment.
- Quota warning email thực sự cho KT SAAS.
- Backup completed/failed email.
- Webhook failed / reconcile failed email.
- HSM expiry warning email.
- MatBao Invoice issuance / signing / download failure email.

Ưu tiên trung bình:
- `tenant_url` / `workspace_url` merge field chuẩn.
- `payment_url` merge field chuẩn cho public signup/checkout.
- `invoice_url` alias riêng cho KT SAAS/landing nếu cần.

## 10. Roadmap ưu tiên

### P0
1. Chốt 2 template giữ lại và 3 template giữ inactive như trên.
2. Bảo đảm mọi email runtime của KT SAAS đi qua `TenantEmailProviderService`.
3. Chuẩn hóa UI provider theo state để không lộ field sai ngữ cảnh.

### P1
1. Bổ sung missing template cho provisioning / welcome / quota / backup / webhook failure.
2. Bổ sung merge field cho tenant/workspace/payment URL.
3. Chuẩn hóa guard chống duplicate send ở public signup + payment flows.

### P2
1. Nếu cần, tách mailbox observability report riêng cho tenant vs landlord.
2. Nếu cần, chuẩn hóa notification center cho module KT MatBao Invoice / KT SePay / KT Inventory.

## 11. Ghi chú kỹ thuật

- `application/helpers/email_helper.php` không tồn tại trong repo.
- `modules/kt_signup` và `modules/kt_checkout` không tồn tại trong repo.
- Core mail queue thực sự tồn tại tại `application/libraries/App_email.php`.
- KT SAAS đã có logging table `tblkt_saas_email_logs` và audit table `tblkt_saas_tenant_email_config_audit` qua schema bootstrap.

