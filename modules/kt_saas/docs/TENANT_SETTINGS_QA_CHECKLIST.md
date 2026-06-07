# KT SAAS Tenant Workspace Settings QA Checklist

## Scope
- Module: `modules/kt_saas`
- Area: Tenant Workspace Settings
- Goal: Verify tenant isolation, entitlement enforcement, and no landlord fallback leaks.

## Environment
- Landlord URL: `https://khachtot.test/admin`
- Tenant URL mẫu: `https://abc.khachtot.test/admin`
- Audit JSON: `/admin/kt_saas/workspace_isolation_audit/{tenantId}`
- Audit HTML: `/admin/kt_saas/workspace_isolation_audit_report/{tenantId}`

---

## Plan Matrix
- [ ] `P1-Free`: most `workspace.*` OFF, `email.*` OFF
- [ ] `P2-Standard`: company/localization/finance/mail identity/branding/notifications ON, finance advanced OFF
- [ ] `P3-Pro`: `P2` + finance advanced + governance ON
- [ ] `P4-Enterprise`: full `workspace.*`, full `email.*`, integrations ON

---

## Tab 1 - Company & Branding
- [ ] `workspace.company.edit=OFF` => company fields disabled + lock state visible
- [ ] `workspace.company.edit=ON` => save company fields success, reload persists
- [ ] `workspace.branding.edit=OFF` => upload/remove logo/favicon disabled
- [ ] `workspace.branding.edit=ON` => upload light/dark logo + favicon success
- [ ] Branding isolation pass: tenant logo/favicon not landlord (name/hash differ)

## Tab 2 - Localization
- [ ] `workspace.localization.edit=OFF` => controls disabled
- [ ] `workspace.localization.edit=ON` => language/timezone/currency/date/time save success
- [ ] Currency list from `tblcurrencies` (no hard-coded USD-only)
- [ ] Landlord fallback only when tenant value missing

## Tab 3 - Email & Notifications
- [ ] `workspace.mail.identity.edit=OFF` => sender/reply-to/BCC/header/footer disabled
- [ ] `workspace.mail.identity.edit=ON` => save mail identity/templates success
- [ ] `email.custom_sender=OFF` + `email.own_credentials=OFF` => Email Provider locked/hidden
- [ ] `email.custom_sender=ON` + `email.own_credentials=OFF` => sender identity only, no transport edit
- [ ] `email.own_credentials=ON` + `email.custom_smtp=ON` => SMTP block visible
- [ ] `email.brevo_api=ON` => Brevo API key block visible
- [ ] Email test connection respects entitlement; no bypass by direct POST
- [ ] `workspace.notifications.edit` OFF/ON => reminder fields lock/unlock correctly

## Tab 4 - Finance
- [ ] `workspace.finance.edit=OFF` => Invoice Defaults + Finance Basics disabled
- [ ] `workspace.finance.edit=ON` => prefixes/numbers/terms save success
- [ ] `workspace.finance.advanced.edit=OFF` => advanced toggles disabled and not persisted if forced in payload
- [ ] `workspace.finance.advanced.edit=ON` => advanced settings save success
- [ ] Persistence check: hard refresh after save keeps latest values

## Tab 5 - Users & Governance
- [ ] `workspace.governance.view=OFF` => governance section hidden
- [ ] `workspace.governance.view=ON`, `workspace.governance.manage=OFF` => view-only
- [ ] `workspace.roles.manage=ON` => role CRUD works
- [ ] `workspace.departments.manage=ON` => department CRUD works
- [ ] Limit checks for roles/departments/governance viewers/managers enforced

## Tab 6 - Modules & Integrations
- [ ] Module not in plan => section hidden
- [ ] `kt_sepay.settings.edit` ON/OFF => visibility/actionability correct
- [ ] `einvoice.settings.edit` ON/OFF => visibility/actionability correct
- [ ] Direct endpoint calls denied when entitlement OFF

## Tab 7 - Subscription (Readonly)
- [ ] Current plan/cycle/expiry displays correctly
- [ ] Usage table `Used/Limit` matches snapshot
- [ ] Upgrade/Renew CTA links valid

---

## Security / Bypass
- [ ] Direct POST to locked setting endpoint does not change values
- [ ] Cross-tenant access/write is denied
- [ ] No landlord-only settings appear in tenant workspace UI

---

## Branding Forensic (Critical)
- [ ] Audit JSON includes branding forensic details:
  - `landlord_files`
  - `details`
  - `file_issues`
- [ ] `view-source:https://{tenant-host}/admin/authentication` favicon link points to tenant file
- [ ] Favicon does not resolve to landlord `favicon.ico` unless explicitly intended
- [ ] Re-test in Incognito to avoid cache false positives

---

## Pass/Fail Summary
- Total cases: ______
- Passed: ______
- Failed: ______
- Critical failed cases: ________________________
- Tester: ________________________
- Date: ________________________

