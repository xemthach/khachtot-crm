# EMAIL PHASE 3C VERIFICATION REPORT

Scope:
- Verify the lifecycle email work added in Phase 3C.
- No new features.
- No template redesign.
- No notification center.
- No preferences UI.
- No refactor.

## 1. Template DB verification

Direct DB verification was performed against `tblemailtemplates` for the Phase 3C lifecycle slugs.

| Template | English | Vietnamese | Active | Mail class | UTF-8 OK |
|---|---|---|---|---|---|
| `tenant_trial_started` | pass | pass | pass | pass | pass |
| `tenant_trial_ending` | pass | pass | pass | pass | pass |
| `tenant_trial_expired` | pass | pass | pass | pass | pass |
| `tenant_subscription_renewed` | pass | pass | pass | pass | pass |
| `tenant_subscription_expired` | pass | pass | pass | pass | pass |
| `tenant_plan_changed` | pass | pass | pass | pass | pass |
| `tenant_quota_warning` | pass | pass | pass | pass | pass |
| `tenant_quota_exceeded` | pass | pass | pass | pass | pass |

Verification notes:
- Both language rows exist for all 8 slugs.
- Rows are active.
- Vietnamese content is stored correctly in UTF-8. The database query returned proper Vietnamese text, not mojibake.
- Mail class filenames map one-to-one with the slugs.

## 2. Mail class verification

| Class | Slug | Recipient | Merge fields | Context | OK |
|---|---|---|---|---|---|
| `Tenant_trial_started.php` | `tenant_trial_started` | tenant owner/admin fallback | KT SAAS merge fields | landlord context | pass |
| `Tenant_trial_ending.php` | `tenant_trial_ending` | tenant owner/admin fallback | KT SAAS merge fields | landlord context | pass |
| `Tenant_trial_expired.php` | `tenant_trial_expired` | tenant owner/admin fallback | KT SAAS merge fields | landlord context | pass |
| `Tenant_subscription_renewed.php` | `tenant_subscription_renewed` | tenant owner/admin fallback | KT SAAS merge fields | landlord context | pass |
| `Tenant_subscription_expired.php` | `tenant_subscription_expired` | tenant owner/admin fallback | KT SAAS merge fields | landlord context | pass |
| `Tenant_plan_changed.php` | `tenant_plan_changed` | tenant owner/admin fallback | KT SAAS merge fields | landlord context | pass |
| `Tenant_quota_warning.php` | `tenant_quota_warning` | tenant owner/admin fallback | KT SAAS merge fields | landlord context | pass |
| `Tenant_quota_exceeded.php` | `tenant_quota_exceeded` | tenant owner/admin fallback | KT SAAS merge fields | landlord context | pass |

Verification notes:
- All classes extend `App_mail_template`.
- All classes use `kt_saas_merge_fields`.
- All classes resolve recipient from tenant owner/admin style fields.
- All classes are aligned with landlord branding context for lifecycle/billing mail.

## 3. Trigger verification

| Event | File/function | Có gọi send? | Có guard? | Có log? |
|---|---|---|---|---|
| `tenant_trial_started` | `Kt_saas_model::save_tenant()` | pass | pass | pass |
| `tenant_trial_ending` | `RecurringBillingRunner::processTrialEndingNotifications()` | pass | pass | pass |
| `tenant_trial_expired` | `RecurringBillingRunner::processTrialExpirations()` | pass | pass | pass |
| `tenant_subscription_renewed` | `BillingEngineService::reactivateSubscriptionAfterPayment()` and `RecurringBillingRunner::processDueRenewals()` | pass | pass | pass |
| `tenant_subscription_expired` | `RecurringBillingRunner::processTrialExpirations()`, `RecurringBillingRunner::processDueRenewals()`, `Kt_saas_model::set_subscription_status()` | pass | pass | pass |
| `tenant_plan_changed` | `Kt_saas_model::save_tenant()`, `BillingEngineService::applyPaidPlanChange()`, `RecurringBillingRunner::applyScheduledPlanChangeIfDue()` | pass | pass | pass |
| `tenant_quota_warning` | `UsageSnapshotRunner::dispatchQuotaWarnings()` | pass | pass | pass |
| `tenant_quota_exceeded` | `OverageBillingService::createForTenant()` | pass | pass | pass |

Verification notes:
- Trigger paths are real and present in code.
- Every lifecycle event is dispatched through `Kt_saas_model::send_email_event()`.
- Logs are emitted through `TenantEmailProviderService`.

## 4. Duplicate guard verification

| Event | Dedupe key | Replay behavior | OK |
|---|---|---|---|
| `tenant_trial_ending` | `tenant_trial_ending|tenant_id|subscription_id|trial_ends_at|daysLeft` | repeated same-day scan is blocked | pass |
| `tenant_trial_expired` | `tenant_trial_expired|tenant_id|subscription_id|trial_ends_at` | repeated expiration scan is blocked | pass |
| `tenant_subscription_renewed` | `tenant_subscription_renewed|tenant_id|subscription_id|period_end` | repeated renewal replay is blocked | pass |
| `tenant_plan_changed` | `tenant_plan_changed|tenant_id|subscription_id|plan_id|period_end` | repeated plan-change replay is blocked | pass |
| `tenant_quota_warning` | `tenant_quota_warning|tenant_id|metric|threshold|YYYY-MM` | same threshold does not resend in the same month | pass |
| `tenant_quota_exceeded` | `tenant_quota_exceeded|tenant_id|subscription_id|period` | repeated snapshot runs do not spam the same period | pass |

Verification notes:
- Guard foundation is backed by `kt_saas_email_event_guards`.
- Success-path email events use guard reservation before dispatch.
- Replayed success flows are blocked by the unique `event_key + dedupe_key` constraint.

## 5. Quota logic verification

| Quota type | Usage source | Limit source | Threshold | Risk |
|---|---|---|---|---|
| Quota warning | `kt_saas_usage` snapshot rebuilt by `UsageSnapshotRunner` | `TenantEntitlementService::getRuntimeProfile()` limits | 80 / 90 / 95 percent | threshold warnings depend on snapshot freshness |
| Quota exceeded | `TenantEntitlementService::buildOverageSummary()` | entitlement limits / overage rates | over 100 percent | overage email is tied to overage detection and invoice creation |

Verification notes:
- Snapshot-based thresholding is real and wired.
- The warning path selects the highest-utilized metric and sends one threshold email per threshold reached.
- The exceeded path sends only when the overage summary is non-empty.

## 6. Billing / cron safety

| Flow | Before behavior | After behavior | Risk |
|---|---|---|---|
| Renewal | renewal logic updates subscription and tenant state | unchanged state flow, added lifecycle email dispatch only | low |
| Trial expiration | trial expiration transitions to grace or suspended | unchanged state flow, added lifecycle email dispatch only | low |
| Grace expiry | grace expiry suspends tenant | unchanged state flow, added lifecycle email dispatch only | low |
| Overage invoice | overage invoice creation | unchanged invoice flow, added quota exceeded email dispatch only | low |
| Scheduled plan change | scheduled plan change application | unchanged plan state flow, added lifecycle email dispatch only | low |
| Free renewal | free renewal extends period | unchanged state flow, added renewal email dispatch only | low |

Verification notes:
- No billing state machine refactor was introduced.
- Email dispatch is appended after the existing business action.
- Duplicate guard prevents replay spam on webhook or cron retries.

## 7. Branding / provider verification

| Event | Branding | Provider | Log | OK |
|---|---|---|---|---|
| `tenant_trial_started` | landlord | `TenantEmailProviderService` | `tblkt_saas_email_logs` | pass |
| `tenant_trial_ending` | landlord | `TenantEmailProviderService` | `tblkt_saas_email_logs` | pass |
| `tenant_trial_expired` | landlord | `TenantEmailProviderService` | `tblkt_saas_email_logs` | pass |
| `tenant_subscription_renewed` | landlord | `TenantEmailProviderService` | `tblkt_saas_email_logs` | pass |
| `tenant_subscription_expired` | landlord | `TenantEmailProviderService` | `tblkt_saas_email_logs` | pass |
| `tenant_plan_changed` | landlord | `TenantEmailProviderService` | `tblkt_saas_email_logs` | pass |
| `tenant_quota_warning` | landlord | `TenantEmailProviderService` | `tblkt_saas_email_logs` | pass |
| `tenant_quota_exceeded` | landlord | `TenantEmailProviderService` | `tblkt_saas_email_logs` | pass |

Verification notes:
- Lifecycle/billing emails resolve in landlord context.
- Runtime provider context is threaded through `TenantEmailProviderService`.
- Logs continue to record provider, sender, recipient, subject, status, and related metadata.

## 8. Live smoke

- No new live browser smoke was run in this verification pass.
- Reason: this pass stayed non-invasive and focused on static + DB verification of the lifecycle mail paths.

## 9. Remaining risks

- Quota warnings rely on snapshot freshness; stale usage snapshots can delay threshold mail timing.
- If the tenant/subscription state is mutated by an external path that bypasses the audited service methods, lifecycle dispatch may need one more consistency pass.
- Live outbound verification should be repeated if SMTP / Brevo credentials rotate.

## 10. Go / No-Go for Phase 3D

- **Go** for Phase 3D.
- Conditions:
  1. keep landlord branding context for lifecycle mail
  2. preserve duplicate guard on all success-path events
  3. add only new lifecycle coverage, not a mail-engine refactor

