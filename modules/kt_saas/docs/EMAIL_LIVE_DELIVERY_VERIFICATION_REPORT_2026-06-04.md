# EMAIL LIVE DELIVERY VERIFICATION REPORT

## Tenant Lifecycle
Verified live:
- `tenant_welcome` -> sent, log id 8, recipient `xemthach+599305@gmail.com`, provider `brevo_api`, transport `smtp`, sender `no-reply@mail.khachtot.com`, Brevo message_id not captured.
- `tenant_trial_ending` -> sent, log id 6, recipient `xemthach+599305@gmail.com`, provider `brevo_api`, transport `smtp`, sender `no-reply@mail.khachtot.com`, Brevo message_id not captured.
- `webhook_failed` -> sent, log id 7, recipient `xemthach+599305@gmail.com`, provider `brevo_api`, transport `smtp`, sender `no-reply@mail.khachtot.com`, Brevo message_id not captured.

Not live verified in inbox from this environment:
- `provisioning_completed`
- `provisioning_failed`
- `tenant_trial_started`
- `tenant_subscription_renewed`
- `tenant_subscription_expired`

## Billing
Verified live:
- `payment_success` -> sent, log id 5, recipient `xemthach+599305@gmail.com`, provider `brevo_api`, transport `smtp`, sender `no-reply@mail.khachtot.com`, related `invoice/29`, Brevo message_id not captured.
- `unmatched_payment_alert` -> sent, log id 9, recipient `xemthach+599305@gmail.com`, provider `brevo_api`, transport `smtp`, sender `no-reply@mail.khachtot.com`, related `subscription/16`, Brevo message_id not captured.

Not live verified:
- `payment_failed`
- `invoice_sent`
- `invoice_paid`
- `invoice_overdue`
- `renewal_due`
- `renewal_success`
- `renewal_failed`

## Auth
Not live verified in this session:
- `forgot_password_staff`
- `forgot_password_client`
- `staff_created`
- `client_registration`
- `verification_email`
- `2FA`

## Ops
Verified live:
- `webhook_failed` -> sent, log id 7.

Not live verified:
- `backup_failed`
- `cron_failed`
- `provider_connection_failed`

## MatBao
Not live verified in this session:
- `einvoice_activated`
- `einvoice_quota_low`
- `einvoice_quota_exhausted`
- `hsm_expiry_warning`
- `invoice_issue_failed`
- `invoice_sign_failed`

## SePay
Verified live:
- `unmatched_payment_alert` -> sent, log id 9.

Not live verified:
- `webhook_failed`
- `reconcile_failed`

## Events Verified
- `payment_success`
- `tenant_trial_ending`
- `tenant_welcome`
- `webhook_failed`
- `unmatched_payment_alert`

## Events Failed
- None in the live sends performed this pass.

## Events Not Tested
- All other listed business events above.

## Message IDs
- Not captured for the live business-email sends.
- `tblkt_saas_email_logs.message_id` is `NULL` on the verified rows.

## Inbox Evidence
- Not directly verified from this environment.
- I verified dispatch + provider response path + local log only.

## Production Readiness Score
- 45/100
- Transport and logging are working for some real events.
- Inbox receipt, message_id capture, and full lifecycle coverage are not yet proven.

## Fix Order
1. Keep the event dispatch path that is already working.
2. Add Brevo message-id capture into `tblkt_saas_email_logs`.
3. Add live inbox verification for one mailbox per critical flow.
4. Run the remaining critical events.
5. Fix tenant provisioning so `provisioning_completed` is actually dispatched in normal signup.

## Can Production Email Be Declared Operational?
- No.
- The system is partially live and proven for a small set of events, but not operational end-to-end yet.
