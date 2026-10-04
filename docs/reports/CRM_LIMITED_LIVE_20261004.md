# Limited-live implementation checkpoint — 2026-10-04

**Overall: BLOCKED for limited-live cutover.** Independent code/runbook work done;
MISA/DMS certification no longer a prerequisite. Core installed runtime remains a gate.
Candidate0.10.4-rc.1 unchanged. No production DB, real provider/MISA or accounting write.

## Baseline and workspace provenance

Remote PR2 OPEN/DRAFT at67a852352cf333da833aa34a74b17125ff9eaa6f; main previously
faefd9b (PR1 merged). No merge/tag/cutover. User worktree remains741a660, 624 status
entries, untouched. Original temporary review clone unexpectedly had2070 deleted
tracked files on continuation (VERSION, setup-live, composer manifests among them).
Cause UNKNOWN; no reset/restore/delete there. New remote clone:
D:/laragon/www/khachtot-limited-review-20261004 at67a8523. Transferred only16 named
task edits/tests from our previous work; deletion set/assets/config secrets excluded.
Fresh clone has zero deleted tracked files. No Salesrep source touched.

## Feature and entry-point matrix

| Feature | Route/call path | Rights/data dependency | Evidence/status |
|---|---|---|---|
| Login/logout | admin/authentication; Authentication.php:33,236 | authentication model, staff/session | STATIC ONLY; installed HTTP not run |
| Customer CRUD | admin/clients/client; Clients.php:74 -> clients_model | staff capabilities, clients/contacts | STATIC ONLY; no live opening yet |
| Leads | admin/leads/lead; Leads.php:77 -> leads_model | view/create/edit/record access | STATIC ONLY; no live opening yet |
| Dashboard/tasks/projects | admin/dashboard,tasks,projects controllers | admin staff + scoped models | candidate scope only; tests required |
| SaaS lifecycle | admin/kt_saas; controller/model/provisioning runner | landlord capabilities, tenant/domain registry, DB users | retained; installed A/B not verified |
| MISA module | NOT FOUND in tracked artifact; dirty user module excluded | future module name blocked by policy | URL/activation-path deny tests, no MISA implementation imported |
| DMS adapter/SSO | NOT FOUND in tracked artifact; future dms/salesrep URL segments denied | no DMS credentials read | policy tests only |
| Hub admin/webhooks/oauth/cron | config/routes.php -> kt_integration_* controllers | module/model | early and resolved route guard;20 HTTP loopback denials |
| Hub bootstrap/after_cron_run | InitModules/InitHook -> kt_integration_hub.php | active module registry retained | actual module include registers zero hooks in test |
| Hub queue/claim/retry/manual model calls | Kt_integration_model constructor | all inherited DB operations | actual model constructor throws before DB-capable parent |
| Direct PHP files | Nginx exact index handler/deny PHP | server config | reference only; nginx runtime NOT VERIFIED |

No core feature is certified live merely because its controller exists.
Current package lacks dirty MISA/DMS transports; absence + guards is not blanket
proof against arbitrary future code. Re-audit new entrypoints before packaging them.
Hub closure intentionally includes Zalo/TikTok, outside limited scope.

Policy default is limited; only server constant KT_RELEASE_PROFILE='standard'
can opt out. Browser/query fields cannot set PHP constants. Disabled callbacks return
503 no-store JSON, not false success/ack. Retry scheduling by upstream is NOT PROVEN;
operator must suspend registered deliveries separately. No data/keys/ledger deleted.
Re-enable only by reviewed deployment with product contract/security/runtime evidence.

## Findings and patches

| ID | Evidence | Before/after | Remaining gate |
|---|---|---|---|
| CRM-002 | installed/install.class.php:81 | no schema imported; pathB restore accepted as alternative | licensed schema OR authorized existing snapshot/key/files/manifest |
| CRM-003 | system/libraries/Profiler.php:108,138,557 at baseline | parse failure reproduced; three property expressions + declared flags; actual class run passes under E_ALL | installed CRM profiler-enabled staging |
| CRM-004 | KtSaasTenantBootstrap:runtime disabled/not resolved/switch failure branches | limited profile exits instead of silently returning for non-landlord host; no dirty lifecycle copied | A/B real DB/session/file/cache/job tests |
| CRM-007 | config.php:497-500 | query containing gateways/ reproduced disabling CSRF; anchored parsed path fixes5 config cases | full HTTP valid/missing/invalid/role/session tests |
| CRM-009 | package.json:12,31 | prior pin unchanged; fresh clone ci/build/output contract passes Windows | Linux build/browser MIME |
| CRM-011 | vendor/zbateson/.../PhpCsFixer.php:2 | unchanged upstream tooling placeholder, parse exit255 retained as FAIL_PREEXISTING_TOOLING | not an independent runtime blocker; no autoload reference, src PSR4 evidence in prior report |
| LL-001 | DatabaseSwitcher.php:64-68 | initialize false/empty connection no longer assigned as successful tenant DB | real failed-DB reproduction |
| LL-002 | ProvisioningJobRunner.php:434+ | limited profile returns null before shared credential fallback/persistence | existing tenant credentials still must be audited; not retroactive cleanup |
| LL-003 | Kt_saas.php:892,900,924; views/dashboard/provision_jobs.php | run/retry/queue exposed as GET links without requirePost | OPEN: convert lifecycle/UI to CSRF POST with installed tests before provisioning live |

Profiler source: CodeIgniter3.1.11 local MIT notice retained; exact property form
matches [official3.1.13](https://raw.githubusercontent.com/bcit-ci/CodeIgniter/3.1.13/system/libraries/Profiler.php).
Declared section flags prevent PHP8.2 dynamic-property deprecation; no whole framework
upgrade, no licence bypass, no profiler suppression. Test collaborator CI object is
synthetic but the loaded class, constructor/set_sections/run are real.

Tenant negative tests use real classes with synthetic CI/DB collaborators and no
network/DB access. They are not database-per-tenant runtime certification.
Default landlord is APP_BASE_URL host; runbook aligns kt_saas_landlord_host to it.
Existing data whose registry differs requires review, not automatic normalization.

## Dependency risk assessment

CRM_LIMITED_DEPENDENCY_TRIAGE_20261004.json covers all39 preserved audit entries,
each with exact advisory/URL, severity/range, actual npm explain dependents and mitigation.
Not39 proven exploitable vulnerabilities. Not all devDependencies are harmless:
builder reads source/CSS/SVG/globs and could expose a dev server if misconfigured.
Run builder unprivileged without runtime secrets, do not ship node_modules or open
dev-server ports. Bootstrap is browser-relevant via committed assets; no claim that
npm package identity alone proves the committed bundle's vulnerable code path.
Polyfill/bundle reachability marked NOT PROVEN, not silently dismissed.
No broad npm upgrade was applied; runtime XSS/bundle and build advisory closure pending.

## Executed evidence and limits

- New clean clone npm ci (862 packages), two Mix production pipelines and asset
  contract exit0 on Windows PHP8.3.16/Node24.14.0. No package/lock edits this slice.
- Composer backup install5 packages from lock; backup/application platform checks
  exit0; deploy-preflight --fresh-install exit0. No DB connection.
- tests/release/profiler-compat.php: PASS real class enabled memory section rendering,
  then disabled; red before patch.
- limited-policy.php:10 denied/5 core policy routes; zero module hooks or model parent effects.
- limited-http.test.cjs:20 GET/POST/PUT/DELETE loopback denials503, query flag tampering,
  controlled body/no-store. Harness is explicitly NOT installed front controller.
- tenant-failclosed.php: failed initialization retains original DB; no shared-credential
  fallback. unknown-host case returns controlled TENANT_RUNTIME_UNAVAILABLE.
- csrf-gateway-config.php:5 actual config cases; query bypass red before/green after.
- release smoke/setup regression, Node syntax, changed PHP + entire framework lint,
  diff-check in machine evidence. Vendor tooling remains separately failed, not hidden.
- WSL absent; no Nginx/Linux/FPM server installed. Linux commands/reference config
  have NOT been executed or certified.

No schema/config credentials copied, restored or requested from production. Neither
database path has inputs: no permitted snapshot/manifest/key/staging target and no
licensed fresh schema. Login/browser/CSRF/tenant CRUD/cron/backup restore NOT RUN.
No production/accounting writes or business-provider tests executed.

## Runbook and required next inputs

[Full runbook](../LIMITED_LIVE_DEPLOYMENT.md): root khachtot.com, wildcard DNS, explicit
tenant host allowlist, root+wildcard DNS01 TLS, complete public vhost and loopback-only
installer vhost, config constants, A install/B restore, actual provisioning,
permissions/session, cron stop gate, backup/cutover/rollback and troubleshooting.
It deliberately stops at unverified credential/provisioning/cron gates rather than
inventing a safe command or granting app DB root. Provisioning currently reuses app
credentials for admin operations; precreated per-tenant DB/user needs rehearsal.

Needed next, precisely:
1. Choose A licensed Perfex3.4.1 schema with provenance/hash, or B authorized snapshot
   landlord+each tenant+uploads/key with source versions and permitted staging reuse.
2. Supply isolated staging host/name list, DB grants and secrets via vault; SMTP sink,
   no provider egress, two disposable tenant identities, restore/cleanup authorization.
3. Linux24.04 staging access, origin IP, admin IP, DNS provider/zone access (not token
   in report), TLS renewal ownership. Cloudflare runbook is conditional on actual DNS.
4. Fix/test provisioning GET mutations and prove tenant lifecycle/session/DB/file/cache
   isolation on installed CRM. Deactivate out-of-scope module registry through lifecycle.
5. Resolve browser dependency reachability, run acceptance and backup/restore rehearsal.

Only then assess LIMITED_LIVE_READY. Merge/tag/traffic cutover not performed.

## Final local check ledger

Run 2026-10-04T06:24:20.358Z–06:25:16.127Z in the new clone:
17 command checks PASS, one separately classified FAIL_PREEXISTING_TOOLING (exit255).
188 PHP files linted (all172 framework files plus changed/new PHP), zero failures in
that scoped set. This is NOT a new all9859-file lint claim; prior full lint remains
historical. The vendor tooling exception was explicitly run separately and retained.
20 denied HTTP requests and5 CSRF config inputs are subcases, not additional top-level
command totals. Result JSON records commands, output/hash and limitations.

Precommit pattern scan of27 named staged files: zero private-key/GitHub-token/
AWS-key/credential-URL pattern matches. Not an entropy/PII scan or whole Git-history
certification. No SQL, app config, generated asset or deletion was staged.
The main user worktree still has624 status entries at741a660; it was not updated
to this branch. New changes are in the separate limited-review clone and PR2.
