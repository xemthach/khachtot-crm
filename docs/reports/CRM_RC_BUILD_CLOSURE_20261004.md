# RC build closure — 2026-10-04

Overall release gate: **BLOCKED**. CRM-009 build command fixed in isolated clone;
this is not installed CRM, dependency security or production certification.
Prior reports/evidence remain immutable; 27/09 execution and 04/10 recheck have
separate evidence files. No schema/provider/accounting/production mutation.

## Git and isolation

Main remote `faefd9b15c831b84305a843daca14b61d674d996` is PR#1 merge.
RC parent `741a660be4e710052d09b541a2cf990a195df084`. Merge-base is
`0ba57e93ea6b69dbcc56c0d8377c929fbff0a8cc`; `git log --left-right origin/main...RC`
shows main-only merge commit, RC-only e37a8ad and741a660. Those two follow-ups were
NOT already in main. New branch `audit/rc-0.10.4-build-closure` starts at741a660;
new PR versus main therefore contains only those unmerged follow-ups plus this work.
No merge/rebase/reset/clean/force push/tag/cutover performed.

All implementation changes made in separate remote clone
`C:\Users\Peter\AppData\Local\Temp\khachtot-rc-review-294136c9e0cb477e8499412834e8eaf2`.
Original `D:\laragon\www\khachtot` remains on its dirty RC branch unchanged.
Source/package/lock/assets from that dirty tree were not copied/staged. The clone
contains no app-config/schema and has no database configuration to bootstrap CRM.

## CRM-009: reproduce, minimize, fix

Baseline `npm ci --ignore-scripts --no-audit --no-fund` exit0 (860 packages).
Node24.14.0/npm11.13.0, Mix6.0.49, lock webpack5.107.2. `npm run build` exit1:

```text
Error: Cannot find module 'webpack/lib/SizeFormatHelpers'
Require stack (paths relative to isolated clone):
node_modules/laravel-mix/src/webpackPlugins/BuildOutputPlugin.js:6
node_modules/laravel-mix/src/builder/webpack-plugins.js
node_modules/laravel-mix/src/builder/WebpackConfig.js
node_modules/laravel-mix/src/Mix.js
node_modules/laravel-mix/setup/webpack.config.js
node_modules/webpack-cli/lib/webpack-cli.js
node_modules/webpack-cli/lib/bootstrap.js
node_modules/webpack-cli/bin/cli.js
node_modules/webpack/bin/webpack.js
```

Minimal repro `node -e "require('webpack/lib/SizeFormatHelpers')"` exit1.
Hypotheses tested: corrupt install (clean ci still fails); custom callsite (require
is in Mix, not CRM custom code); dependency compatibility (pin restores required API).
Mix declares webpack `^5.60.0`, allowing the incompatible resolution. Upstream
[webpack5.105.0 file](https://raw.githubusercontent.com/webpack/webpack/v5.105.0/lib/SizeFormatHelpers.js)
exists; same path in5.107.2 returns404. No patch inside node_modules/vendor.

Fix `package.json`: exact devDependency webpack5.105.0; npm-generated lockfile.
Lock diff adds webpack-required eslint type packages and removes an extraneous yaml
entry, not arbitrary upgrades. Pin-only experiment: both Mix pipelines compile,
then original tail fails:

```text
Error: Cannot find module '<isolated clone>/build.mjs'
code: MODULE_NOT_FOUND
requireStack: []
```

`git log --all -- build.mjs` and tracked filename search have no file/history in
fetched repo; only package command and audit docs refer to it. No authoritative
third build domain/output found. Removed only trailing `&& node build.mjs`, keeping
root Mix and modules/einvoice Mix. Regression test first failed `missing build
entrypoint: build.mjs`; after fix API/entrypoints/generated-output checks pass.

Fresh ci + full build passed on the stated Windows/Node version. No claim of Node18,
20,22 or Linux compatibility. Do not use npm update to reproduce this result.

## Output consumers and artifact safety

`application/helpers/assets_helper.php:36,44,63,72,79` registers tailwind, vendor,
common and app bundles; admin view shell consumes app asset services.
`modules/einvoice/controllers/Einvoice.php:57-59` registers codemirror/template.
Both pipelines write these exact paths. Test checks files exist and nonempty;
evidence contains actual output hashes/sizes. This is build+STATIC consumer tracing,
not browser asset/render PASS. Generated files remain uncommitted in clone to avoid
mixing a build recipe fix with unchecked UI artifact rollout. Guide requires build
and artifact review before packaging this candidate.

Output scan: private-key/GitHub-token/AWS-key patterns only; no matches in scanned
outputs. Clone contains no runtime credential/app-config and does not read ignored
SQL. This is not full secret-history/PII or embedded-public-key analysis.

## Framework and vendor findings (not patched)

CRM-003: core `system/core/CodeIgniter.php:58` is3.1.11. Profiler:108 uses deprecated
dynamic-property syntax; lint PHP8.3 exit255. Upstream
[3.1.13 Profiler](https://raw.githubusercontent.com/bcit-ci/CodeIgniter/3.1.13/system/libraries/Profiler.php)
uses concatenated property names. MIT notice permits modification with notice
retention, but does not certify compatibility of the Perfex distribution. Owner
must select approved distribution update vs scoped backport with retention of notices,
set_sections/run tests and installed profiler-enabled staging. No disabling/excluding
Profiler or replacing the framework in this task. Runtime Profiler NOT RUN.

CRM-011: composer installed/lock metadata identifies stream-decorators1.2.1,
source783b034024fda8eafa19675fb2552f8654d3a3e9. Official
[PhpCsFixer.php](https://raw.githubusercontent.com/zbateson/stream-decorators/783b034024fda8eafa19675fb2552f8654d3a3e9/PhpCsFixer.php)
has the same prose placeholder. Official
[gitattributes](https://raw.githubusercontent.com/zbateson/stream-decorators/783b034024fda8eafa19675fb2552f8654d3a3e9/.gitattributes)
does not export-ignore it. Local composer PSR-4 loads src/, not this root tooling
file; no generated autoload reference found. Git introduced it in baseline807a614;
intent behind bundling is UNKNOWN. BSD-2-Clause package, not CRM runtime source.
Keep lint failure visible; owner decision: approved package refresh or documented
distribution cleanup (not removal here merely to green lint).

Lint separately: application1301/1301, framework171/172, vendor7899/7900,
modules/other486/486; total9857/9859 PASS. Exact paths/hashes and current run counts
in evidence. No PHP implementation was changed by this PR.

## CSRF / tenant / schema / runtime

[Decision matrix](CRM_RC_CSRF_TENANT_DECISIONS_20260927.md) traces actual App_Security,
active-module exclusions, POST-only enforcement, gateway substring and specific
mutation surfaces. [Candidate inventory](CRM_RC_MUTATION_CANDIDATES_20260927.json)
has493 static candidates, not493 verified routes. HTTP valid/missing/invalid token,
role/record/tenant/company attacks all NOT RUN without installed staging.

The decision matrix also proposes a separate four-file tenant-lifecycle review,
with exact other dirty hunks excluded pending owner split. HEAD does not require
untracked lifecycle; company identity policy/registry is not inferred from names.
Owner/source reason UNKNOWN; no silent packaging. Schema local ignored hash still
matches prior evidence but remains unproven; no schema copied/imported.

Staging install/upgrade/login/browser/worker/backup-restore and Linux/Nginx/FPM/TLS:
**NOT RUN / NOT VERIFIED**, blocked by licensed schema + explicit isolated DB intake.
Uninstalled HTTP results from earlier phase are not reused as business-runtime PASS.

## Evidence and gates

- `CRM_RC_BUILD_CLOSURE_20260927_EVIDENCE.json`: original completed build/lint/audit run.
- `CRM_RC_BUILD_CLOSURE_20261004_EVIDENCE.json`: fresh confirmation, command exit codes,
  output hashes, locked versions, lint groups, generated artifact hashes and scan limits.
- `tools/rc-closure-evidence.cjs` is fail-closed: npm audit/lint failures yield exit1
  despite successful build. npm audit warnings require separate reachability/security
  triage; no automatic audit fix or broad dependency migration in this scope.
- `tests/release/asset-contract.test.cjs --built`: regression exercises actual webpack
  API and tracked build entrypoints, not a mocked success flag.

Release remains BLOCKED. Reviewable fixes do not authorize merge/tag/live. Next:
approved schema/staging metadata; tenant source decision; framework/package remedy;
dependency advisory triage; installed HTTP/browser/security + worker/restore gates.
Only Git/docs/package registry network used; no business provider/MISA or accounting writes.

## Completed 04/10 execution

Evidence run: 2026-10-04T05:20:51.088Z to 05:30:22.716Z. Clean ci, full build,
asset contract, release smoke and setup regression each exit0. Lock SHA256 before
and after is identical: c4311ec384a9cffaeace80bee656e94c481d7d34b9124959a5221ccfac0c918a.
17 generated artifacts scanned; zero matches for the bounded patterns above.
All-file lint repeats the same two failures (9857/9859 pass).
Current npm audit exit1: 39 reported vulnerable package entries (6 low, 10 moderate,
22 high, 1 critical). Registry results differ from September evidence; do not
reinterpret historical counts or treat this as proof of application exploitability.
The evidence runner exits1 intentionally because audit/lint are not green.

Node syntax checks for all three new scripts and `git diff --check` exit0.
Reviewed staged files were scanned for private keys, GitHub tokens, AWS access-key
IDs and credential-bearing URLs; no pattern matches. This excludes full Git history
and is not a comprehensive secret/PII certification. No generated assets, SQL,
app config, framework/vendor edits or dirty tenant rewrites belong to this commit.
