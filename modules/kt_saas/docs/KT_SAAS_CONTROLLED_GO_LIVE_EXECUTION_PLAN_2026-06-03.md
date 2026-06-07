# CONTROLLED GO-LIVE EXECUTION PLAN

Scope:
- Controlled production rollout for KT SAAS based on the existing runbook.
- No code changes.
- No refactor.
- No module additions.
- No feature changes.

## Overview

Rollout is split into three phases:
1. Staging Validation
2. Soft Launch
3. Public Launch

The goal is to move from verified runtime behavior to limited tenant exposure, then to full public availability with rollback controls already defined.

---

## Phase 1 - Staging Validation

### Goal
Verify the full go-live path in a controlled environment before exposing any broader traffic.

### Checklist

| Test | Owner | Expected | Pass/Fail | Evidence |
|---|---|---|---|---|
| Domain / wildcard DNS | Ops | Landlord and tenant subdomains resolve correctly | Pending | DNS lookup / browser response |
| SSL | Ops | Valid certificates on landlord and tenant hosts | Pending | Browser lock / certificate check |
| Cron | SRE | Scheduled hooks run on time | Pending | Dashboard freshness / log entry |
| Queue | SRE | Provision jobs and mail queue are visible and usable | Pending | Admin queue views |
| Global email | Admin | Test email returns detailed provider result | Pending | Email test result / logs |
| Tenant email | Tenant admin | Tenant test email returns tenant-specific result | Pending | Email test result / logs |
| SePay config | Payments owner | Sandbox/production config matches intended mode | Pending | Settings / health logs |
| MatBao config | Finance ops | Sandbox/production config matches intended mode | Pending | Settings / health logs |
| Backup create | SRE | Tenant backup is created successfully | Pending | Backup record / file |
| Backup restore | SRE | Test restore completes and tenant remains healthy | Pending | Restore result / tenant login |
| Signup test tenant | QA / Ops | Test tenant can be created from signup flow | Pending | Signup response / tenant record |
| Payment success | Payments owner | Invoice is marked paid once | Pending | Payment record / invoice state |
| Payment replay | Payments owner | Replay is blocked by duplicate guard | Pending | Guard record / invoice state |
| Provisioning retry | SRE | Failed provision job can be retried | Pending | Provision job status history |
| Tenant isolation | QA | Tenant A/B branding and locale remain separated | Pending | Tenant host screenshots / PDF |
| Invoice PDF branding | QA | Tenant invoice PDF renders tenant logo/data | Pending | PDF screenshot / extracted text |
| Landing pricing | Product / QA | Public pricing shows active public plans | Pending | Browser screenshot |
| Runbook review | All owners | Operators know the rollback and incident steps | Pending | Review sign-off |

### Exit criteria
- All critical checks pass.
- No unresolved payment, webhook, provisioning, email, or branding bleed defects.
- Operators can locate logs, retries, and rollback actions without assistance.

---

## Phase 2 - Soft Launch

### Goal
Expose the system to a small, controlled set of real tenants before opening public traffic.

### Scope
- Enable only 3 to 5 selected tenants.
- Keep the launch group tightly monitored.
- Do not broaden exposure until all success metrics are inside threshold.

### Monitoring metrics

| Metric | Threshold | Action if breached |
|---|---|---|
| Signup success rate | At or above expected baseline for test tenants | Pause new signups and inspect landing / signup / provisioning logs |
| Payment success rate | Majority of test payments succeed | Pause payment cutover and verify gateway / webhook / invoice state |
| Payment fail rate | Low and explainable | Check webhook payload, signature, and invoice status handling |
| Provisioning failed | Zero or near-zero after initial fixes | Retry failed jobs and stop expansion until root cause is known |
| Webhook failed | Near-zero | Inspect webhook logs, secret config, and provider connectivity |
| Email failed | Near-zero | Check provider mode, credentials, and email logs |
| SePay unmatched | Low and explainable | Reconcile or pause auto-match until reference mapping is fixed |
| MatBao issue/sign fail | Zero or tightly controlled | Pause issuing/signing and inspect provider health |
| Backup status | Successful periodic backups | Stop expansion if backups fail |
| Cron freshness | Fresh enough for billing / quota / lifecycle jobs | Restore cron before continuing |

### Exit criteria
- No critical incidents during the soft launch window.
- Retry paths are proven on at least one controlled failure.
- All operational dashboards show stable, explainable values.
- The support owner can handle a webhook, payment, or provisioning incident without engineering hand-holding.

---

## Phase 3 - Public Launch

### Goal
Open the platform to general traffic only after the controlled rollout has proven stable.

### Go / No-Go checklist

| Requirement | Status |
|---|---|
| 0 critical bugs open | Pending |
| Payment replay passes | Pending |
| Provisioning retry passes | Pending |
| Tenant isolation passes | Pending |
| Backup / restore passes | Pending |
| Support runbook is ready | Pending |
| Operators know incident handling | Pending |
| Email delivery is stable | Pending |
| SePay and MatBao health is stable | Pending |
| Cron freshness is acceptable | Pending |

### Go criteria
- All Phase 1 validation checks pass.
- Phase 2 soft launch is stable.
- No unresolved critical bugs remain.
- Rollback actions are documented and executable.

### No-Go criteria
- Any critical bug remains open.
- Payment replay or webhook idempotency is not proven.
- Provisioning retry is not proven.
- Tenant isolation shows bleed.
- Backup/restore is not proven.
- Operators cannot execute the runbook without escalation.

---

## Rollback

If a severe issue appears at any phase, execute rollback in this order:

1. Disable public signup.
2. Switch payment to manual mode.
3. Pause MatBao issue/sign flows.
4. Pause SePay automatic reconciliation.
5. Restore the affected tenant from the latest known-good backup.
6. Notify affected tenants or internal operators, depending on impact scope.

### Rollback triggers
- Critical payment processing failure
- Repeated webhook failure
- Provisioning failures that cannot be retried safely
- Tenant branding or localization bleed
- MatBao or SePay provider outage affecting production flow
- Backup/restore failure
- Cron stoppage that affects billing, lifecycle, or quota enforcement

---

## Recommended rollout order

1. Complete Phase 1 in staging.
2. Hold a short operator review.
3. Enable Phase 2 for 3 to 5 tenants only.
4. Monitor metrics continuously during the soft launch window.
5. Promote to Phase 3 only after thresholds remain stable.
6. Keep rollback actions ready even after public launch.

