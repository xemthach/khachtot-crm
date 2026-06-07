# EMAIL PHASE 3C AUDIT

Scope:
- Lifecycle emails only.
- Audit first, then implementation.
- No notification center, no preferences UI, no mail-engine refactor.

## 1. Audit results

### Trial data source
- `kt_saas_subscriptions.trial_ends_at` is the authoritative source.
- Subscription rows are created in `Kt_saas_model::ensure_tenant_subscription()`.
- Trial status is assigned when `plan.trial_days > 0`.

### Subscription data source
- `kt_saas_subscriptions.status`, `current_period_end_at`, `next_billing_at`, `grace_ends_at`, `trial_ends_at` are the lifecycle source fields.
- Renewal and expiry state transitions are handled in `RecurringBillingRunner`.
- Paid renewal reactivation is handled in `BillingEngineService`.

### Quota data source
- `kt_saas_usage` is the persisted usage snapshot source.
- Usage snapshots are rebuilt by `UsageSnapshotRunner`.
- Overages are computed by `TenantEntitlementService::buildOverageSummary()`.
- There is no existing email warning trigger for 80/90/95 percent thresholds, so this must be wired from the snapshot/overage layer.

### Cron jobs
- `after_cron_run` is wired in `kt_saas.php`.
- `modules/kt_saas/cron/Kt_saas_cron.php` already runs:
  - domain readiness checks
  - recurring billing
  - usage snapshot recalculation
  - usage cleanup
  - backup cleanup
  - provisioning jobs

### Renewal flow
- `PaymentCollectionService::processWebhook()` dispatches payment failure handling.
- `BillingEngineService::markInvoicePaid()` dispatches payment success handling.
- `BillingEngineService::reactivateSubscriptionAfterPayment()` is the real renewal reactivation point.
- `RecurringBillingRunner::processDueRenewals()` handles free renewals and renewal invoice creation.
- `RecurringBillingRunner::applyScheduledPlanChangeIfDue()` and `BillingEngineService::applyPaidPlanChange()` are the real plan-change flow points.

## 2. Trigger source mapping

| Template | Trigger source | Audit verdict |
|---|---|---|
| `tenant_trial_started` | tenant subscription creation in `Kt_saas_model::ensure_tenant_subscription()` / tenant create flow | real trigger |
| `tenant_trial_ending` | cron scan over `kt_saas_subscriptions.trial_ends_at` for 7/3/1 day windows | needs wiring, data source is real |
| `tenant_trial_expired` | `RecurringBillingRunner::processTrialExpirations()` | real trigger |
| `tenant_subscription_renewed` | `BillingEngineService::reactivateSubscriptionAfterPayment()` and free renewal branch in `RecurringBillingRunner::processDueRenewals()` | real trigger |
| `tenant_subscription_expired` | grace expiration / suspension transition in `RecurringBillingRunner` | real trigger |
| `tenant_plan_changed` | `BillingEngineService::applyPaidPlanChange()` and `RecurringBillingRunner::applyScheduledPlanChangeIfDue()` | real trigger |
| `tenant_quota_warning` | usage snapshot / threshold scan over `kt_saas_usage` | needs wiring, data source is real |
| `tenant_quota_exceeded` | overage summary / overage invoice path | real trigger |

## 3. Duplicate guard audit
- Duplicate guard foundation already exists in `kt_saas_email_event_guards`.
- Critical success-path events must be guarded:
  - `payment_success`
  - `provisioning_completed`
  - `tenant_trial_started`
  - `tenant_trial_ending`
  - `tenant_trial_expired`
  - `tenant_subscription_renewed`
  - `tenant_subscription_expired`
  - `tenant_plan_changed`
  - `tenant_quota_warning`
  - `tenant_quota_exceeded`

## 4. Logs audit
- `tblkt_saas_email_logs` already exists and must continue receiving:
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

## 5. Audit conclusion
- Trial / renewal / expiry / plan-change triggers are real and can be wired safely.
- Quota warning needs new threshold dispatch in the snapshot/overage layer.
- Branding must stay landlord-context for billing / provisioning / lifecycle notifications.
- All Phase 3C lifecycle emails should flow through `TenantEmailProviderService` and duplicate guard.

