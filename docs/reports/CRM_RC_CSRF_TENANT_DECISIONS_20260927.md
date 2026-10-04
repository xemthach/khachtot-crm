# CSRF, tenant source decisions — STATIC ONLY

Scope: tracked RC741a660 plus read-only diff of user worktree. No config default,
tenant behavior or route changed by this PR. No installed staging DB available.

## CRM-007 actual call path

`application/config/config.php:485` constant fallback false; both tracked templates
define APP_CSRF_PROTECTION=true. Core Input invokes Security if config enabled;
actual subclass `application/core/App_Security.php:12-55` checks POST only, merges
`csrf_exclude_uris` filter, regex anchored URI, cookie/POST hash_equals, failure error.
`application/hooks/InitModules.php:16-31` loads exclusions only for active modules
and only when include returns array. Active-module DB state NOT VERIFIED.
`config.php:497-500` disables CSRF when REQUEST_URI contains `gateways/`; path/query
matching impact must be tested with real router, not inferred from grep.

| Route/surface | Auth | CSRF expectation/exemption | Evidence | HTTP required (all NOT RUN) |
|---|---|---|---|---|
| admin/authentication POST | public login + credential validation | token if enabled, no explicit exemption | Authentication.php:38-55, App_Security.php:15 | valid/missing/wrong token; inactive/login/logout |
| admin/clients/client/{id} POST | AdminController + customer capabilities | enabled POST check | Clients.php; AdminController.php:8 | own/foreign record, missing token |
| admin/clients/delete/{id} | staff delete capability | NOT safe to assume POST-only; method lacks guard | Clients.php:634-646 | GET/POST and CSRF/role side effects |
| admin/invoices/invoice/{id}, delete/{id} | staff invoice capabilities | POST token; verify non-POST delete separately | Invoices.php:300,614 | valid/invalid token, no unauthorized mutation |
| admin/tasks/timer_tracking | staff session/handler rules | explicitly exempt in config:490 | Tasks controller + config | forged request/ownership and justification |
| kt_saas tenant_status/archive/delete/bulk | landlord + capability + requirePost | no explicit exemption | Kt_saas.php:106,214,227,236 | host/role/POST/token validation |
| forms/wtl/{key}, forms/ticket, forms/quote/{key} | public form-specific rules | explicit URI exemption | config:490 | abuse validation and approved public flow |
| api/.+ | provider-specific, cannot assume token from URI | broad explicit exemption | config:490 | enumerate real handlers; unauthenticated denial |
| razorpay/success/.+ | gateway contract | explicit exemption | config:490 | signature/session/invoice match with local fixtures |
| URI containing gateways/ | mixed, route dependent | config-level disable | config:497-500 | path AND query tampering, wrong-method behavior |
| kt_sepay/webhook[/tenant/{code}] | validateWebhookSecurity (API key/HMAC) | module return-array when active | config/routes.php, csrf_exclude_uris.php; Kt_sepay_webhook.php:106 | signed/invalid/replay/tenant match |
| kt_integration_hub/webhook/{provider}/{key} | provider handler validation | module return-array when active | hub config/routes.php:5, csrf_exclude_uris.php | provider fixture auth, body tamper |
| kt_matbao_invoice/webhook/invoice, signing | webhook-specific rules | file assigns config, does NOT return array to InitModules | matbao config/csrf_exclude_uris.php:5; InitModules.php:26-29 | confirm actual exclusion vs request rejected; STATIC observation |
| misa/callback and new API routes | NOT in tracked RC surface | present only in user dirty my_routes/config/module | dirty config:596, my_routes:57 onward | separate owner-approved module release, not certified here |

Machine inventory `CRM_RC_MUTATION_CANDIDATES_20260927.json` lists static controller
method candidates, line/parent and tests needed. Regex inventory is not exhaustive
runtime route coverage. Dynamic hooks, inherited methods and provider-specific auth
need installed staging. No STATIC result is promoted to CSRF HTTP PASS.

## CRM-004 dependency graph and review proposal

```text
tracked hooks.php pre_controller_constructor
  -> InitHook::_app_init():28
    -> KtSaasTenantBootstrap::handle()
      HEAD: Resolver -> DatabaseSwitcher -> entitlement/context services
      dirty: Resolver.normalizeHost -> TenantHttpDatabaseLifecycle
        -> DatabaseSwitcher (tracked)
        -> TenantHttpContext (untracked)
        -> tenant status helper + domains/tenants registry + trusted company
        -> original DB/globals/config snapshot -> shutdown restore
```

All local modification authorship/reason: **UNKNOWN**, not inferable from Git status.
HEAD boot does not require the two untracked classes. Proposed separate tenant-runtime
PR only if owner chooses the new behavior; it is not a prerequisite inferred for RC.

| File/hunk | Purpose/boot vs behavior | Proposed packaging | Mandatory test/decision |
|---|---|---|---|
| application/hooks/KtSaasTenantBootstrap.php:54-83,194+ dirty | replace switching with fail-closed lifecycle; behavior | include reviewed rewrite together with next 3 files, not alone | unknown host/DB failure cannot retain landlord; HTTP error redaction |
| modules/kt_saas/tenant_bootstrap/TenantHttpDatabaseLifecycle.php new | snapshot, switch verify, company ownership, restore | include only after owner approval | exceptions, failed switch, shutdown, sequential A/B jobs |
| modules/kt_saas/tenant_bootstrap/TenantHttpContext.php new | immutable context container, publishes DB/name | include with lifecycle; audit metadata exposure | no foreign context/secret in rendering |
| modules/kt_saas/tenant_bootstrap/TenantResolver.php:7-25 dirty | normalize trusted host/port; behavior | include normalization hunk, verify registry assumptions | malformed host, port, landlord, nonexistent domains |
| modules/kt_saas/tenant_bootstrap/DatabaseSwitcher.php tracked unchanged | decrypt/open tenant DB | no automatic source change | actual query target and failed-connection recovery |
| modules/kt_saas/services/TenantContextService.php tracked | existing context API | no automatic source change | compatibility with new published globals |
| modules/kt_saas/helpers/kt_saas_helper.php dirty | adds integration helper dependency | exclude unrelated helper addition from minimal proposal | verify tenant runtime status helper already exists in HEAD |
| modules/kt_saas/services/TenantEntitlementService.php:780,808 dirty | extra DMS route/capability policies | separate product/authorization review | do not bundle with host normalization |
| modules/kt_saas/models/Kt_saas_model.php dirty | large DMS/marketplace/business diff | exclude from minimal host PR | owner split by domain, schema/tests needed |
| application/config/my_routes.php:51+ dirty | MISA and DMS APIs | separate integrations PR | endpoint auth, module packaging, signed payload tests |
| application/config/routes.php dirty | QA auth/marketing routes | exclude from minimal tenant PR | QA auth availability/security, route collisions |
| application/config/config.php:590+ dirty | Facebook/MISA CSRF exclusions | exclude pending provider review | active module and signature/CSRF tests |
| index.php dirty | CLI named args/environment detection/error mode | separate bootstrap change review | APP_ENV/trusted host spoof/error disclosure |
| SaaS install.php/migration022/controller/language/UI dirty | schema/product/version changes | not proven necessary for minimal lifecycle | owner schema compatibility and approved staging migration |
| modules/kt_saas/tests/* untracked | mix of static/runtime fixtures | select tests with assertions, not whole folder | classify real DB vs source-string tests; no credentials |

Do not transplant files without their reviewed tests/registry schema. Minimum logical
rewrite is 4 files, not proof that 4-file deployment is safe; dry source dependency
analysis cannot settle company identity policy (`crm_company_id`/landlord_client_id).

## CRM-002 required intake (no SQL copied/imported)

Ignored local SQL structural evidence retained from follow-up: 117 CREATE TABLE;
content hash `5b25eb8e77a6209e3c6d36ed9493c69fd65d854b3cbe8070e8ee41c180802f48`.
Installer `installed/install.class.php:81` needs schema, migrations are incremental.
Required owner inputs: licensed Perfex3.4.1 package/download provenance, file version,
publisher/archive hash and clean-seed confirmation; isolated DB host/name/purpose,
least-privilege credentials via approved secret channel, empty-DB snapshot, upgrade
baseline and cleanup/restore approval. License proof/secret values stay outside Git.
No authoritative package found in tracked source; schema gate remains BLOCKED.
