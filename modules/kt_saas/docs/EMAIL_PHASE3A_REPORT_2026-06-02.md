# EMAIL PHASE 3A REPORT

Scope:
- Foundation layer only.
- No template creation or redesign.
- No notification center or preferences UI.
- No changes to core Perfex mail engine beyond existing KT SAAS runtime integration points.

## 1. Files changed

- `modules/kt_saas/services/EmailBrandingContextResolverService.php`
- `modules/kt_saas/services/EmailTriggerRegistryService.php`
- `modules/kt_saas/services/EmailDuplicateGuardService.php`
- `modules/kt_saas/libraries/merge_fields/Kt_saas_merge_fields.php`
- `modules/kt_saas/services/TenantEmailProviderService.php`
- `modules/kt_saas/models/Kt_saas_model.php`
- `modules/kt_saas/install.php`
- `modules/kt_saas/services/BillingEngineService.php`
- `modules/kt_saas/provisioning/ProvisioningJobRunner.php`
- `modules/kt_saas/helpers/kt_saas_helper.php`
- `modules/kt_saas/kt_saas.php`

## 2. Tables changed

### New table
- `kt_saas_email_event_guards`

Purpose:
- Reserve and track email event dedupe state for critical lifecycle events.
- Prevent duplicate dispatch for success-path events.

Columns:
- `event_key`
- `dedupe_key`
- `tenant_id`
- `resource_type`
- `resource_id`
- `recipient_scope`
- `branding_context`
- `provider_context`
- `status`
- `context_json`
- `last_error_message`
- `reserved_at`
- `sent_at`
- `updated_at`

Keys:
- unique `event_key + dedupe_key`
- indexes on `tenant_id`, `status`, `event_key`

## 3. Resolver implementation

### Branding Context Resolver
- Service: `modules/kt_saas/services/EmailBrandingContextResolverService.php`
- It resolves a runtime email context from:
  - event key
  - recipient scope
  - tenant-aware fallback context

Returned runtime context:
- `event_key`
- `recipient_scope`
- `branding_context`
- `provider_context`

### Runtime transport propagation
- Service: `modules/kt_saas/services/TenantEmailProviderService.php`
- Global context now carries:
  - `branding_context = landlord`
  - `provider_context = landlord_global`
- Tenant context now carries:
  - `branding_context = tenant`
  - `provider_context = tenant_custom`
- Runtime config items set before send:
  - `kt_saas_mail_runtime_transport`
  - `kt_saas_mail_runtime_identity`
  - `kt_saas_mail_runtime_branding_context`
  - `kt_saas_mail_runtime_provider_context`

## 4. Merge field registry

### Registry file
- `modules/kt_saas/libraries/merge_fields/Kt_saas_merge_fields.php`

### Registered fields
- `{tenant_name}`
- `{tenant_code}`
- `{workspace_url}`
- `{workspace_domain}`
- `{owner_name}`
- `{plan_name}`
- `{payment_url}`
- `{invoice_url}`
- `{pdf_url}`
- `{xml_url}`
- `{quota_remaining}`
- `{quota_limit}`

### Merge field wiring
- Hook registration added in `modules/kt_saas/kt_saas.php`.
- Checkout fallback now resolves `PaymentCollectionService` safely only when the class is actually available.

## 5. Trigger registry

### Registry file
- `modules/kt_saas/services/EmailTriggerRegistryService.php`

### Source of truth events
- `payment_success`
- `payment_failed`
- `provisioning_completed`
- `provisioning_failed`
- `tenant_welcome`

Each registry row carries:
- `event_key`
- `recipient_scope`
- `branding_context`
- `provider_context`
- `resource_type`
- `delivery_mode`
- `template_slug`
- `duplicate_guard_key`
- `priority`

## 6. Duplicate guard

### Guard service
- `modules/kt_saas/services/EmailDuplicateGuardService.php`

### Behavior
- Reserves an event before dispatch.
- Blocks duplicate events when the same `event_key + dedupe_key` is already:
  - `reserved`
  - `sent`
- Allows recovery if the previous row is `failed`.

### Critical guarded flows
- `payment_success`
- `provisioning_completed`

### Success-path integration
- `BillingEngineService::markInvoicePaid()`
- `ProvisioningJobRunner::execute()`

Both now create a guard reservation payload and return the guard result in the response.
`markInvoicePaid()` also reserves the guard on replayed `already_paid` success responses so duplicate success mail paths can be blocked consistently.

## 7. Test results

### Static checks
- `php -l modules/kt_saas/services/TenantEmailProviderService.php`
- `php -l modules/kt_saas/libraries/merge_fields/Kt_saas_merge_fields.php`
- `php -l modules/kt_saas/services/EmailDuplicateGuardService.php`
- `php -l modules/kt_saas/models/Kt_saas_model.php`
- `php -l modules/kt_saas/install.php`
- `php -l modules/kt_saas/services/BillingEngineService.php`
- `php -l modules/kt_saas/provisioning/ProvisioningJobRunner.php`
- `php -l modules/kt_saas/kt_saas.php`
- `php -l modules/kt_saas/helpers/kt_saas_helper.php`

Result:
- All listed files passed PHP syntax lint.

### Static registry verification
- Confirmed trigger registry entries for the Phase 3A foundation events.
- Confirmed schema references for `kt_saas_email_event_guards`.
- Confirmed runtime branding context is threaded into KT SAAS mail transport config.

### Remaining verification
- No live browser smoke was run in this phase.
- This phase intentionally stays in the foundation layer; template/UI work is out of scope.

## 8. Notes

- This phase does not create new templates.
- This phase does not add notification center UI.
- This phase does not add preferences UI.
- This phase does not alter existing Perfex template content.
- The duplicate guard is now available for future email dispatch integration and already covers the two critical success paths in the current KT SAAS flows.
