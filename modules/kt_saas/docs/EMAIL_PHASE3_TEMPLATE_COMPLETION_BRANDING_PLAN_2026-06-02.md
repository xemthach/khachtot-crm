# EMAIL PHASE 3 - TEMPLATE COMPLETION & BRANDING ENFORCEMENT PLAN

Scope:
- Không code.
- Không refactor mail engine.
- Không sửa provider/queue/SMTP/Brevo logic đã chốt ở Phase 1 và Phase 2.5.
- Chỉ lập plan cho template coverage, branding context, merge fields, notification matrix, và implementation order.

## 1. Branding Context Enforcement

### Landlord context
Áp dụng cho:
- public signup
- pricing
- trial
- checkout
- payment
- provisioning
- subscription lifecycle
- system alerts

### Tenant context
Áp dụng cho:
- invoice / estimate / proposal / contract
- payment request
- customer-facing notifications
- eInvoice operational flows

### Enforcement rule
- Mọi email phải đi qua runtime resolver.
- Không dùng `get_option()` trực tiếp cho branding nếu request đang ở tenant/landlord scoped runtime.
- Sender identity, header/footer, logo, reply-to, CTA URL phải khớp context.

### Required output from resolver
- runtime sender identity
- runtime transport
- runtime branding context
- runtime recipient scope

## 2. Merge Field Plan

### KT SAAS core
- `{tenant_name}`
- `{tenant_code}`
- `{workspace_url}`
- `{workspace_domain}`
- `{owner_name}`
- `{owner_email}`
- `{plan_name}`
- `{subscription_status}`
- `{trial_end_date}`
- `{login_url}`

### Billing / checkout
- `{payment_url}`
- `{checkout_url}`
- `{invoice_url}`
- `{invoice_number}`
- `{invoice_total}`
- `{currency}`
- `{billing_cycle}`
- `{due_date}`

### SePay
- `{payment_reference}`
- `{payment_amount}`
- `{payment_qr_url}`
- `{bank_account}`
- `{transaction_code}`
- `{payment_status}`

### MatBao Invoice
- `{einvoice_quota}`
- `{einvoice_remaining}`
- `{hsm_status}`
- `{hsm_expiry_date}`
- `{pdf_url}`
- `{xml_url}`
- `{lookup_url}`

### Ops / error
- `{error_message}`
- `{event_time}`
- `{job_id}`
- `{webhook_url}`
- `{provider_name}`

## 3. Missing Template Plan

### P0
- `tenant_welcome`
- `tenant_provisioning_completed`
- `tenant_provisioning_failed`
- `payment_success`
- `payment_failed`

### P1
- `tenant_trial_started`
- `tenant_trial_ending`
- `tenant_trial_expired`
- `tenant_subscription_renewed`
- `tenant_subscription_expired`
- `tenant_plan_changed`
- `tenant_quota_warning`
- `tenant_quota_exceeded`

### P2
- `einvoice_activated`
- `einvoice_quota_low`
- `einvoice_quota_exhausted`
- `hsm_activated`
- `hsm_expiry_warning`
- `invoice_sign_failed`
- `invoice_issue_failed`

### P3
- `unmatched_payment_alert`
- `webhook_failed`
- `cron_failed`
- `backup_completed`
- `backup_failed`
- `provider_connection_failed`

## 4. Trigger / Recipient Matrix

| Event | Sender Context | Recipient | Channel | Priority |
|---|---|---|---|---|
| signup submitted | landlord | internal ops / owner | email + notification | P0 |
| payment success | landlord | tenant admin + ops | email + notification | P0 |
| payment failed | landlord | tenant admin + ops | email + notification | P0 |
| provisioning completed | landlord | tenant admin | email + notification | P0 |
| provisioning failed | landlord | tenant admin + ops | email + notification | P0 |
| trial ending | landlord | tenant admin | email + notification | P1 |
| quota warning | tenant | tenant admin | email + notification | P1 |
| invoice sent | tenant | customer | email | P1 |
| invoice overdue | tenant | customer + tenant admin | email + notification | P1 |
| eInvoice issue failed | tenant | tenant admin | email + notification | P2 |
| webhook failed | landlord | ops | email + notification | P3 |

## 5. Notification Center Design

### Event registry fields
- `event_key`
- `module`
- `default_template`
- `email_enabled`
- `notification_enabled`
- `preference_key`
- `landlord_enabled`
- `tenant_enabled`
- `customer_enabled`
- `priority`

### Registry behavior
- One event maps to one primary template.
- Optional notification payload may be attached.
- Recipient scope must be explicit.
- Fallback policy must be explicit when template is missing.
- UI should show current state, not infer state implicitly.
- Each event must declare delivery mode:
  - email
  - in-app
  - both

## 6. Notification Preferences

### Landlord
- Controls system-level templates, operational alerts, provisioning, billing, and platform notifications.
- Can enable or disable email and in-app delivery separately per event.

### Tenant
- Controls tenant-scoped business notifications such as invoice, estimate, proposal, contract, quota, and customer messaging.
- Can override delivery preference only within allowed tenant scope.

### Customer
- Controls customer-facing notifications where customer preference is permitted by product policy.
- Must not override landlord or tenant operational alerts.

## 7. In-App Notification Center

### Delivery mode matrix
Every event in the matrix must explicitly declare one of:
- email
- in-app
- both

### In-app requirements
- Visible event title
- Short body
- Related object link
- Timestamp
- Read / unread state
- Scope label: landlord / tenant / customer

### In-app priority behavior
- P0 and P1 events should surface immediately in the center.
- P2 and P3 events can remain in the list with lower visual priority.

## 8. Template Versioning

### Required fields per template
- version
- active_version
- upgrade_strategy

### Versioning rules
- A template may have multiple versions.
- Only one version is active at a time.
- Upgrades must preserve backward compatibility for merge fields when possible.
- If merge fields change, migration strategy must be explicit.
- Deprecated versions should remain readable for audit history.

## 9. Implementation Roadmap

### Phase 3A
- Freeze branding context rules.
- Freeze merge-field registry.
- Freeze recipient matrix.

### Phase 3B
- Add missing P0 templates.
- Wire provisioning / welcome / payment flows.

### Phase 3C
- Add P1 templates.
- Add trial / quota / subscription lifecycle coverage.

### Phase 3D
- Add P2 / P3 operational templates.
- Add failure / reconcile / backup coverage.

### Phase 3E
- Add notification center registry UI.
- Add template binding UI.
- Add duplicate-send guard and observability.
- Add delivery mode and preference key support.
- Add template versioning support.

## 10. Test Plan

- Template exists in DB.
- Mail class exists.
- Merge fields render without missing tokens.
- Vietnamese UTF-8 renders correctly.
- Branding source matches runtime context.
- Resolver is used for every send path.
- Log row is created for sent/failed mail.
- Duplicate-send guard blocks double fire.
- Fallback policy works when tenant config is missing.
- Recipient scope matches expected landlord / tenant / customer role.
- Delivery mode resolves correctly to email / in-app / both.
- Template version resolution returns active version consistently.

## 11. Go / No-Go Recommendation

### Go
Proceed only if Phase 3 is implemented in the order above.

### No-Go
Do not add ad hoc templates before:
1. branding context rules are frozen
2. merge fields are registered
3. trigger matrix is enforced
4. duplicate-send guard exists

## 12. Checklist after this phase
- [x] Branding context rules defined
- [x] Merge field plan defined
- [x] Missing template plan defined
- [x] Trigger / recipient matrix defined
- [x] Notification center design defined
- [x] Notification preferences defined
- [x] In-app notification center defined
- [x] Template versioning defined
- [x] Implementation roadmap defined
- [x] Test plan defined
- [x] Go / No-Go recommendation defined
