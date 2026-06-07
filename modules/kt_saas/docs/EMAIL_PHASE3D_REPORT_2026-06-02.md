# EMAIL PHASE 3D REPORT

Scope:
- Operational alerts only.
- No mail-engine refactor.
- No provider/SMTP/Brevo changes.
- No Notification Center.
- No Preferences UI.
- No new lifecycle templates beyond the Phase 3D operational set.

## 1. Files changed

- `modules/kt_saas/kt_saas.php`
- `modules/kt_saas/services/TenantEmailProviderService.php`
- `modules/kt_saas/libraries/merge_fields/Kt_saas_merge_fields.php`
- `modules/kt_saas/services/EmailTriggerRegistryService.php`
- `modules/kt_saas/helpers/kt_saas_helper.php`
- `modules/kt_saas/cron/Kt_saas_cron.php`
- `modules/kt_saas/services/TenantBackupService.php`
- `modules/kt_saas/controllers/Checkout.php`
- `modules/kt_sepay/controllers/Kt_sepay_webhook.php`
- `modules/kt_sepay/cron/Kt_sepay_cron.php`
- `modules/kt_sepay/libraries/Kt_sepay_processor.php`
- `modules/kt_matbao_invoice/kt_matbao_invoice.php`
- `modules/kt_matbao_invoice/models/Kt_matbao_invoice_model.php`
- `modules/kt_matbao_invoice/controllers/Kt_matbao_invoice_tenant.php`
- `modules/kt_matbao_invoice/libraries/Matbao_invoice_client.php`
- `modules/kt_matbao_invoice/libraries/Matbao_sign_client.php`
- `application/libraries/mails/Einvoice_activated.php`
- `application/libraries/mails/Einvoice_quota_low.php`
- `application/libraries/mails/Einvoice_quota_exhausted.php`
- `application/libraries/mails/Hsm_activated.php`
- `application/libraries/mails/Hsm_expiry_warning.php`
- `application/libraries/mails/Invoice_issue_failed.php`
- `application/libraries/mails/Invoice_sign_failed.php`
- `application/libraries/mails/Unmatched_payment_alert.php`
- `application/libraries/mails/Webhook_failed.php`
- `application/libraries/mails/Cron_failed.php`
- `application/libraries/mails/Backup_completed.php`
- `application/libraries/mails/Backup_failed.php`
- `application/libraries/mails/Provider_connection_failed.php`

## 2. Templates created

### Operational templates
- `einvoice_activated`
- `einvoice_quota_low`
- `einvoice_quota_exhausted`
- `hsm_activated`
- `hsm_expiry_warning`
- `invoice_issue_failed`
- `invoice_sign_failed`
- `unmatched_payment_alert`
- `webhook_failed`
- `cron_failed`
- `backup_completed`
- `backup_failed`
- `provider_connection_failed`

### Seeded template status
- English and Vietnamese rows are seeded through `kt_saas.php`.
- Vietnamese copy is UTF-8 and stored as proper accented text.
- Templates are active by default in the Phase 3D seed set.

## 3. Mail classes created

| Class | Slug | Recipient scope | Branding context | OK |
|---|---|---|---|---|
| `Einvoice_activated.php` | `einvoice_activated` | tenant admin | landlord | pass |
| `Einvoice_quota_low.php` | `einvoice_quota_low` | tenant admin | landlord | pass |
| `Einvoice_quota_exhausted.php` | `einvoice_quota_exhausted` | tenant admin | landlord | pass |
| `Hsm_activated.php` | `hsm_activated` | tenant admin | landlord | pass |
| `Hsm_expiry_warning.php` | `hsm_expiry_warning` | tenant admin | landlord | pass |
| `Invoice_issue_failed.php` | `invoice_issue_failed` | tenant admin | landlord | pass |
| `Invoice_sign_failed.php` | `invoice_sign_failed` | tenant admin | landlord | pass |
| `Unmatched_payment_alert.php` | `unmatched_payment_alert` | landlord ops | landlord | pass |
| `Webhook_failed.php` | `webhook_failed` | landlord ops | landlord | pass |
| `Cron_failed.php` | `cron_failed` | landlord ops | landlord | pass |
| `Backup_completed.php` | `backup_completed` | landlord ops | landlord | pass |
| `Backup_failed.php` | `backup_failed` | landlord ops | landlord | pass |
| `Provider_connection_failed.php` | `provider_connection_failed` | landlord ops | landlord | pass |

## 4. Trigger wiring

| Event | Trigger source | Send path | Guard | Log | OK |
|---|---|---|---|---|---|
| `einvoice_activated` | `Kt_matbao_invoice_model::mark_order_paid_and_activate_addons()` | `kt_saas_send_email_event()` | yes | yes | pass |
| `einvoice_quota_low` | `Kt_matbao_invoice_model::consume_einvoice_quota_fifo()` | `kt_saas_send_email_event()` | yes | yes | pass |
| `einvoice_quota_exhausted` | `Kt_matbao_invoice_model::consume_einvoice_quota_fifo()` | `kt_saas_send_email_event()` | yes | yes | pass |
| `hsm_activated` | `Kt_matbao_invoice_model::update_ca_access_token()` | `kt_saas_send_email_event()` | yes | yes | pass |
| `hsm_expiry_warning` | `kt_matbao_invoice_after_cron_run()` | `kt_saas_send_email_event()` | yes | yes | pass |
| `invoice_issue_failed` | `Kt_matbao_invoice_tenant::createOrIssue()` failure branches | `dispatchPhase3DEmail()` | yes | yes | pass |
| `invoice_sign_failed` | `Kt_matbao_invoice_tenant::createOrIssue()` sign branches | `dispatchPhase3DEmail()` | yes | yes | pass |
| `unmatched_payment_alert` | `Kt_sepay_processor::processIncomingTransaction()` unmatched branch | `kt_saas_send_email_event()` | yes | yes | pass |
| `webhook_failed` | `Kt_sepay_webhook::processWebhookRequest()` and `Checkout::webhook()` | `kt_saas_send_email_event()` | yes | yes | pass |
| `cron_failed` | `kt_saas_run_scheduled_jobs()` catch block | `kt_saas_send_email_event()` | yes | yes | pass |
| `backup_completed` | `TenantBackupService::createBackup()` success | `dispatchBackupEmail()` | yes | yes | pass |
| `backup_failed` | `TenantBackupService::createBackup()` failure | `dispatchBackupEmail()` | yes | yes | pass |
| `provider_connection_failed` | `Matbao_invoice_client::login()`, `Matbao_sign_client::login()`, `Kt_sepay_cron.php` reconcile failure | `kt_saas_send_email_event()` | yes | yes | pass |

## 5. Duplicate guard

Guard enforcement remains in `kt_saas_email_event_guards`.

Observed dedupe patterns:
- `einvoice_activated|tenant_id|order_id`
- `einvoice_quota_low|tenant_id|YYYY-MM|threshold`
- `einvoice_quota_exhausted|tenant_id|YYYY-MM|reference_type|reference_id`
- `hsm_activated|tenant_id|account_id`
- `hsm_expiry_warning|tenant_id|account_id|YYYY-MM-DD`
- `invoice_issue_failed|tenant_id|invoice_id|reason`
- `invoice_sign_failed|tenant_id|invoice_id|reason`
- `unmatched_payment_alert|transaction_id|YYYY-MM-DD`
- `webhook_failed|source|YYYY-MM-DD|reason`
- `cron_failed|YYYY-MM-DD`
- `backup_completed|backup_id`
- `backup_failed|backup_id`
- `provider_connection_failed|module|action|YYYY-MM-DD`

## 6. Logs

All Phase 3D sends flow through `TenantEmailProviderService`, so they continue to write:
- `tenant_id`
- `provider`
- `from_email`
- `recipient`
- `subject`
- `status`
- `error_message`
- `related_type`
- `related_id`
- `created_at`
- `sent_at`

No secret material is logged.

## 7. Test results

### Static checks
- `php -l modules/kt_sepay/libraries/Kt_sepay_processor.php`
- `php -l modules/kt_sepay/controllers/Kt_sepay_webhook.php`
- `php -l modules/kt_sepay/cron/Kt_sepay_cron.php`
- `php -l modules/kt_saas/cron/Kt_saas_cron.php`
- `php -l modules/kt_saas/controllers/Checkout.php`
- `php -l modules/kt_saas/services/TenantBackupService.php`
- `php -l modules/kt_matbao_invoice/kt_matbao_invoice.php`
- `php -l modules/kt_matbao_invoice/models/Kt_matbao_invoice_model.php`
- `php -l modules/kt_matbao_invoice/controllers/Kt_matbao_invoice_tenant.php`

Result:
- All listed files passed PHP syntax lint.

### Static verification
- Trigger registry includes all Phase 3D operational events.
- Mail class files exist for every Phase 3D slug.
- Template seed entries exist in `kt_saas.php`.
- All operational event paths call `kt_saas_send_email_event()` or a thin wrapper around it.

### Live smoke
- Not run in this pass.
- Reason: this phase was executed as a non-invasive code pass focused on trigger wiring and operational alert coverage.

## 8. Remaining risks

- `hsm_expiry_warning` depends on cron cadence and the freshness of the CA token expiry dataset.
- `backup_completed` / `backup_failed` are only as accurate as the backup directory/database state and filesystem permissions.
- `provider_connection_failed` depends on real external outages or authentication failures to surface in production.
- Some alert paths may still warrant a live smoke after credential rotation or environment change.

