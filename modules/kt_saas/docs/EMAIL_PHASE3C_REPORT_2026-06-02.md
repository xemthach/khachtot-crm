# EMAIL PHASE 3C REPORT

Scope:
- Lifecycle emails only.
- Foundation and lifecycle wiring only.
- No notification center, no preferences UI, no mail-engine refactor.

## 1. Audit results

- `tenant_trial_started` has a real trigger path in tenant creation / subscription bootstrap.
- `tenant_trial_ending` is now wired from the recurring billing cron scan over trial subscriptions.
- `tenant_trial_expired` is wired from trial expiration processing.
- `tenant_subscription_renewed` is wired from renewal reactivation after successful payment and from free renewal cron paths.
- `tenant_subscription_expired` is wired from suspension after grace expiry and explicit expired-status transitions.
- `tenant_plan_changed` is wired from paid plan change activation and scheduled plan change application.
- `tenant_quota_warning` is wired from usage snapshot recalculation and threshold checks at 80/90/95 percent.
- `tenant_quota_exceeded` is wired from overage detection / overage invoice flow.

## 2. Trigger source

| Template | Trigger source |
|---|---|
| `tenant_trial_started` | `Kt_saas_model::save_tenant()` create path after subscription bootstrap |
| `tenant_trial_ending` | `RecurringBillingRunner::processTrialEndingNotifications()` |
| `tenant_trial_expired` | `RecurringBillingRunner::processTrialExpirations()` |
| `tenant_subscription_renewed` | `BillingEngineService::reactivateSubscriptionAfterPayment()` and free-renewal branch in `RecurringBillingRunner::processDueRenewals()` |
| `tenant_subscription_expired` | grace-expiry path in `RecurringBillingRunner` and explicit expired-status transition in `Kt_saas_model::set_subscription_status()` |
| `tenant_plan_changed` | admin plan-change save path, `BillingEngineService::applyPaidPlanChange()`, `RecurringBillingRunner::applyScheduledPlanChangeIfDue()` |
| `tenant_quota_warning` | `UsageSnapshotRunner::dispatchQuotaWarnings()` |
| `tenant_quota_exceeded` | `OverageBillingService::createForTenant()` |

## 3. Templates created

- `tenant_trial_started`
- `tenant_trial_ending`
- `tenant_trial_expired`
- `tenant_subscription_renewed`
- `tenant_subscription_expired`
- `tenant_plan_changed`
- `tenant_quota_warning`
- `tenant_quota_exceeded`

Each template has:
- English
- Vietnamese UTF-8
- landlord branding context
- merge fields for lifecycle state

## 4. Trigger wiring

- Event registry extended in `EmailTriggerRegistryService`.
- Runtime mail context extended in `TenantEmailProviderService`.
- Mail class files added under `application/libraries/mails/`.
- Template seeding added in `kt_saas.php`.
- Lifecycle send calls added at the real source points above.

## 5. Duplicate guard

- Duplicate guard is enforced through `kt_saas_email_event_guards`.
- Guard is used on success-path lifecycle events and quota events.
- Replay-resistant dedupe keys were added for:
  - trial started
  - trial ending
  - trial expired
  - subscription renewed
  - subscription expired
  - plan changed
  - quota warning
  - quota exceeded

## 6. Logs

- Email sends continue to log into `tblkt_saas_email_logs`.
- Logged fields preserved:
  - tenant_id
  - provider
  - from_email
  - recipient
  - subject
  - status
  - error_message
  - related_type
  - related_id
  - created_at
  - sent_at

## 7. Files changed

- `modules/kt_saas/docs/EMAIL_PHASE3C_AUDIT_2026-06-02.md`
- `modules/kt_saas/docs/EMAIL_PHASE3C_REPORT_2026-06-02.md`
- `modules/kt_saas/kt_saas.php`
- `modules/kt_saas/models/Kt_saas_model.php`
- `modules/kt_saas/services/TenantEmailProviderService.php`
- `modules/kt_saas/services/EmailTriggerRegistryService.php`
- `modules/kt_saas/libraries/merge_fields/Kt_saas_merge_fields.php`
- `modules/kt_saas/services/BillingEngineService.php`
- `modules/kt_saas/billing/RecurringBillingRunner.php`
- `modules/kt_saas/services/UsageSnapshotRunner.php`
- `modules/kt_saas/services/OverageBillingService.php`
- `application/libraries/mails/Tenant_trial_started.php`
- `application/libraries/mails/Tenant_trial_ending.php`
- `application/libraries/mails/Tenant_trial_expired.php`
- `application/libraries/mails/Tenant_subscription_renewed.php`
- `application/libraries/mails/Tenant_subscription_expired.php`
- `application/libraries/mails/Tenant_plan_changed.php`
- `application/libraries/mails/Tenant_quota_warning.php`
- `application/libraries/mails/Tenant_quota_exceeded.php`

## 8. Test results

### Static checks
- `php -l modules/kt_saas/kt_saas.php`
- `php -l modules/kt_saas/models/Kt_saas_model.php`
- `php -l modules/kt_saas/services/TenantEmailProviderService.php`
- `php -l modules/kt_saas/services/BillingEngineService.php`
- `php -l modules/kt_saas/billing/RecurringBillingRunner.php`
- `php -l modules/kt_saas/services/UsageSnapshotRunner.php`
- `php -l modules/kt_saas/services/OverageBillingService.php`
- `php -l modules/kt_saas/services/EmailTriggerRegistryService.php`
- `php -l modules/kt_saas/libraries/merge_fields/Kt_saas_merge_fields.php`
- `php -l application/libraries/mails/Tenant_trial_started.php`
- `php -l application/libraries/mails/Tenant_trial_ending.php`
- `php -l application/libraries/mails/Tenant_trial_expired.php`
- `php -l application/libraries/mails/Tenant_subscription_renewed.php`
- `php -l application/libraries/mails/Tenant_subscription_expired.php`
- `php -l application/libraries/mails/Tenant_plan_changed.php`
- `php -l application/libraries/mails/Tenant_quota_warning.php`
- `php -l application/libraries/mails/Tenant_quota_exceeded.php`

Result:
- All listed files passed PHP syntax lint.

### Remaining verification
- No live browser smoke was run in this phase.
- Live credential / outbound mail verification should still be repeated if providers change.

## 9. Remaining risks

- Quota warning logic now exists, but it is threshold-based and relies on the daily snapshot runner.
- If a tenant changes plan or trial state outside the normal save / billing paths, future code paths may still need a consistency pass.
- Live mail verification is still needed if SMTP / Brevo credentials rotate.

