# CRM 0.10.4-rc.1 — clean-clone follow-up

Verdict: **BLOCKED**. Candidate không thay số, không tạo tag/cutover.
Báo cáo baseline được giữ nguyên; các hiệu chỉnh dưới đây là current truth.

## Git baseline và thay đổi ngoài phiên

- Source: `D:\laragon\www\khachtot`, branch `audit/release-0.10.4-rc.1-20260927`.
- HEAD bắt đầu `0ba57e93ea6b69dbcc56c0d8377c929fbff0a8cc`; remote RC khớp.
- Baseline main lịch sử `c483dd279aac1128b7bdb414f78aeaa360e7e9ef`.
- `gh pr view 1 --json mergedAt,mergedBy,mergeCommit`: PR #1 đã MERGED bởi
  `xemthach` lúc `2026-09-26T19:07:44Z`, merge commit
  `faefd9b15c831b84305a843daca14b61d674d996`. `git ls-remote` xác nhận main mới.
  Agent không merge trong phiên này và không gọi PR đó là Draft đang mở.
- Không tìm thấy AGENTS/CONTEXT áp dụng. Đọc ADR index, không thay domain decisions.
- Clean clone từ đúng HTTPS remote vào thư mục mới, checkout detached đúng RC SHA:
  `C:\Users\Peter\AppData\Local\Temp\khachtot-rc-review-294136c9e0cb477e8499412834e8eaf2`.
  `git status --short` ban đầu rỗng. Không copy app-config, secrets, SQL hay user source.
- OS Windows; PHP 8.3.16, Node 24.14.0, Git Bash. Linux/FPM/TLS **NOT VERIFIED**.

## Ownership và hai cây source

Machine-readable [evidence](CRM_RELEASE_FOLLOWUP_20260927_EVIDENCE.json) chứa từng
file dirty/untracked, Git status, nhóm chức năng, reference, packaging decision,
ownership chưa xác định và checks cần chạy. Git không chứng minh tác giả/lý do dirty;
không bịa ownership từ timestamp. Các source có sẵn không được stage trong follow-up.

| File/nhóm | Reference | Đóng gói | Check còn thiếu |
|---|---|---|---|
| `application/hooks/KtSaasTenantBootstrap.php` dirty | tracked `InitHook.php:28-33` | Chờ owner review cả diff | host/database failure HTTP |
| `TenantHttpDatabaseLifecycle.php` untracked | dirty hook:54 | Chỉ cần khi nhận rewrite mới | resolver/context/switch integration |
| `TenantHttpContext.php` untracked | lifecycle:7,59 | Phụ thuộc rewrite, không add riêng | trusted company/session isolation |
| `TenantResolver.php`, SaaS helper/services/models dirty | lifecycle/entitlement/context/registry | review tập thay đổi, không suy ra đủ từ hai class | schema/registry/tenant HTTP |
| `index.php`, config/routes dirty | front controller, CSRF, module routes | cần diff review độc lập | boot/security regression |
| `package.json`, `package-lock.json`, assets dirty | Mix/build và admin/client view | không ghi đè hoặc stage theo quán tính | clean build, provenance outputs |
| MISA/hub/DMS-related source/test untracked | module bootstrap/routes/repositories | không thuộc bản sửa deployment này | secret/PII, dependency, business tests |
| deleted/moved reports, fixtures, artifacts | không phải boot dependencies | loại khỏi commit | owner retention review |

**Hiệu chỉnh CRM-004:** RC HEAD không require lifecycle untracked. HEAD hook:53-56
require `TenantResolver`, `DatabaseSwitcher`, `TenantEntitlementService`,
`TenantContextService`, đều có trong Git. Dirty hook thay DatabaseSwitcher bằng
lifecycle mới, kéo thêm context/helper/registry behavior. Không chứng minh clean RC
thiếu class đó bằng cách nhìn dirty source. Blocker là version/ownership/runtime
divergence; thiếu dependency backup là finding riêng CRM-008.

## Findings trước/sau

| ID | Severity | Evidence | Sau kiểm tra |
|---|---|---|---|
| CRM-001 | HIGH | smoke real Bash loopback 200/302/404/500/refused | VERIFIED 5/5 pass cả RC clone; fix giữ nguyên |
| CRM-002 | HIGH | `installed/index.php:3,6,13` → Install::go → `install.class.php:81` → sqlparser | BLOCKED: clean clone không có SQL; không chạy import |
| CRM-003 | HIGH nếu profiler bật | `system/libraries/Profiler.php:108`, property syntax `_compile_{$section}` | VERIFIED lint failure; không tắt/bỏ khỏi lint, không sửa vendor |
| CRM-004 | HIGH packaging | HEAD/diff hook và lifecycle references trên | STATIC ONLY; phân biệt HEAD/dirty, chưa nhận rewrite vào Git |
| CRM-005 | MEDIUM | setup chmod runtime-only | fixture PASS; Linux ownership NOT VERIFIED |
| CRM-006 | MEDIUM | diagnostics:57 bỏ raw tail log | STATIC ONLY current source, không claim leak đã xảy ra |
| CRM-007 | MEDIUM | config:485,490,497-500 | STATIC ONLY; auth/HTTP CSRF matrix NOT RUN |
| CRM-008 | HIGH setup | `modules/backup/.gitignore:1`, `backup.php:12` unconditional autoload | clean preflight FAIL; cài từ module composer.lock thành công, preflight PASS; guide sửa |
| CRM-009 | HIGH build | RC package.json:12; Mix BuildOutputPlugin requires missing SizeFormatHelpers | VERIFIED build FAIL; webpack 5.107.2/Mix6.0.49; `build.mjs` cũng NOT FOUND; không stage package dirty |
| CRM-010 | HIGH new-install runbook | setup tạo config trước `installed/index.php:6` guard | FIXED fresh mode và test red→green; không đồng nghĩa DB install pass |
| CRM-011 | LOW tooling artifact | `application/vendor/zbateson/stream-decorators/PhpCsFixer.php:2` chứa prose không phải PHP | VERIFIED parse failure; composer PSR-4 chỉ `src/`, chưa thấy autoload file này; không sửa/xóa/exclude vendor |

## Schema/provenance và installer

`git ls-files '*.sql'` chỉ có vendor elFinder MySQLStorage, không phải CRM schema.
Local ignored `installed/database.sql`: 224744 bytes; UTF-8 content SHA256
`5b25eb8e77a6209e3c6d36ed9493c69fd65d854b3cbe8070e8ee41c180802f48`.
Read-only structural scan: 117 CREATE TABLE, seed INSERTs vào countries/currencies/
emailtemplates/options/migrations và reference tables. Không xuất giá trị row.
Provenance/version/license package **UNVERIFIED**, không copy/commit/import.

Migrations là incremental (ví dụ `application/migrations/341_version_341.php:11`
ALTER user_auto_login), không chứng minh dựng empty DB. Không chế schema từ live.
Cần bộ cài Perfex 3.4.1 được cấp phép, release manifest/hash và xác nhận SQL là clean
seed cho core341; sau đó database staging rỗng/credentials qua vault/environment.
Không dùng DB đã sửa thủ công hoặc MISA-only schema để chứng nhận core install.

Installer không idempotent bằng cách POST lại: config guard và SQL/data writes;
chỉ được test rerun theo contract installer sau khi có schema. Setup filesystem
idempotency đã test, không đánh tráo thành DB migration idempotency.

## Framework provenance và hướng xử lý

Core khai báo CodeIgniter3.1.11 (`system/core/CodeIgniter.php:58`). Profiler header
có MIT; nguồn upstream cùng tag chứa cú pháp lỗi, không phải feature CRM mới:
[3.1.11](https://raw.githubusercontent.com/bcit-ci/CodeIgniter/3.1.11/system/libraries/Profiler.php),
[3.1.13](https://raw.githubusercontent.com/bcit-ci/CodeIgniter/3.1.13/system/libraries/Profiler.php).
Đây không phải bằng chứng toàn Perfex distribution tương thích khi thay riêng file.
Phương án: chủ sở hữu duyệt vendor distribution/update tương thích hoặc backport
được review kèm license notices và regression. Không thay toàn framework hoặc vá
hai dòng chỉ để xanh lint trong phiên. Profiler-enabled staging runtime **NOT RUN**.

## Lệnh thực thi và giới hạn

| Lệnh/môi trường | Kết quả |
|---|---|
| clone remote + checkout exact RC + status | VERIFIED sạch trước cài dependencies |
| `php scripts/deploy-preflight.php` clean clone | ban đầu FAIL backup autoload; sau module composer install PASS |
| `composer --working-dir=modules/backup install --no-dev --no-scripts --no-plugins --no-interaction --prefer-dist` | PASS 5 packages từ lock; chỉ clone temp |
| Composer check-platform-reqs application và backup | PASS CLI; không CVE audit |
| `npm ci --ignore-scripts --no-audit --no-fund` clone | PASS 860 packages; deprecated warnings không tự gán CVE |
| `npm run build` clone | FAIL missing webpack/lib/SizeFormatHelpers; chưa tới missing build.mjs |
| `node tests/release/clean-clone-http.cjs <clone>` | real PHP loopback GET / 200 uninstalled, /installed/ 200 installer page, no fatal; không có app-config/schema |
| release smoke test RC clone | PASS 5 actual HTTP cases |
| setup test RC clone | PASS 2 baseline checks |
| setup test modified source | initial FAIL fresh config assertion, after fix PASS missing dependencies/fresh absence/idempotency/configured-fresh rejection |
| 8 focused local tests | PASS; không thuộc clean clone; ContextRestorationTest chỉ string checks |
| `node tools/release-followup.cjs <clone> <evidence.json>` | 9.859 tracked PHP INCLUDING vendor: 9.857 PASS, 2 FAIL (Profiler + PhpCsFixer); exit1, không che lỗi |
| shell syntax từng script, Node syntax, scoped diff check | PASS |

[Checks artifact](CRM_RELEASE_FOLLOWUP_20260927_CHECKS.json): 23 command-level
checks, 22 PASS, 1 FAIL (build), timestamps/exit/output hashes. Không cộng test-case
con hay lint count vào số command để thổi phồng coverage. Lint chạy
2026-09-26T19:21:33Z–19:27:54Z, tương ứng ngày 27/09 timezone địa phương.

HTTP harness khởi động/stop riêng PHP bound127.0.0.1 ephemeral port, không dùng
8097 hoặc production config. Page uninstalled không có login/auth/tenant context:
login/permission/SaaS/company/browser/CSRF mutation **NOT VERIFIED**, không PASS.

CSRF code: default false khi thiếu APP_CSRF_PROTECTION; URI exemptions API/forms;
gateways substring tắt protection. Sample dot và hyphen cần kiểm từng revision;
hai template tại RC đều define true (kiểm tra riêng Git HEAD, không dùng dirty config).
không sửa config dirty/default mà chưa chạy login/API/webhook regression.

Cron `application/controllers/Cron.php:9-18` có option/hook side effects, optional
APP_CRON_KEY và throttle300s. Cron/worker **NOT RUN** vì chưa có clean installed
staging và network/provider guard. Backup module dependency install không phải
backup/restore certification: DB dump/restore **NOT RUN**, checksum/data parity và
RTO/RPO chưa có. Không tiếp cận production database để lấp chỗ trống.

## Thay đổi và release gate

Task-owned: setup fresh flag, regression fixture, unconfigured HTTP probe, tracked
lint/ownership runner, guide, README link, changelog và follow-up evidence/report.
Không đưa user source hoặc generated clone assets/node_modules/vendor vào commit.
Baseline report giữ nguyên; version vẫn candidate0.10.4-rc.1.

Secret scan file staged/diff: high-confidence key/token patterns + review config,
không xuất raw output/secret. Không bao phủ toàn Git history; không khẳng định
toàn repo sạch PII. Không commit local SQL/app-config/database credentials.

Clone temp/dependency files giữ để tái hiện; không có CRM DB/seed/accounting writes.
HTTP listener harness đã stop. Network có GitHub/docs và package registry download,
không gọi business provider/MISA. Linux deployment **NOT VERIFIED**.

Blockers theo thứ tự: (1) schema provenance + authorized isolated DB metadata;
(2) owner quyết định bộ tenant/source/build changes cần nhận; (3) vendor compatibility;
(4) clean build closure; (5) install/upgrade + HTTP/browser isolation/CSRF + cron;
(6) staging backup/restore + Linux/FPM/TLS. PR đã merged không xóa các blocker này.
Không đủ điều kiện release tag/cutover; nhiệm vụ không thực hiện merge/tag/deploy.
