# Khách Tốt CRM — audit phát hành 2026-09-27

## Verdict: BLOCKED (phát hành/live), bản sửa deployment có thể review riêng

Không chứng nhận toàn bộ chức năng CRM hoạt động. Đây là inventory toàn source và
lint toàn PHP ngoài dependency/runtime, kèm audit sâu có giới hạn và kiểm thử script
triển khai. Runtime nghiệp vụ, browser, migration/restore và production **NOT VERIFIED**.
Không kế thừa verdict MISA trong báo cáo lịch sử.

## Baseline và phương pháp

- Source: `D:\laragon\www\khachtot`; Windows/PowerShell, Git Bash.
- Branch gốc `main`; HEAD và remote main:
  `c483dd279aac1128b7bdb414f78aeaa360e7e9ef`.
- Origin `https://github.com/xemthach/khachtot-crm.git`; GitHub permission `ADMIN`;
  default branch `main`, protection API trả `false`. Không thay protection.
- `git status --short --branch`, `git remote -v`, `git branch -vv`,
  `git log -1 --format=fuller`, `git tag --list`, `git ls-remote --symref origin HEAD`
  đã kiểm tra. Snapshot path/status trong JSON bên dưới chứa cả worktree không sạch.
- Không tìm thấy AGENTS.md áp dụng trong repo/ancestors. Không reset, stash, clean,
  force push; không stage các sửa đổi module/framework/assets của người dùng.
- PHP 8.3.16, Composer 2.8.9, Node 24.14.0; server/DB production chưa xác minh.
- JSON: [inventory, lint, status, test hashes](CRM_RELEASE_AUDIT_20260927_EVIDENCE.json).
  Hash output kiểm thử không thay thế log raw; chỉ lưu hash để tránh lộ dữ liệu.
- Trước/sau smoke test dùng server loopback độc lập, không bootstrap CRM/DB.
  Setup test dùng temp fixture và fake dependency markers, không chứng minh cài CRM.

## Version và release boundary

HEAD mang tag `v0.10.2-tiktok-shop-dry-run-staging`; tag
`v0.10.3-zalo-webhook-probe` tồn tại trên ancestor `edf0afaf42e9655bcb0c95deb3f0ad8647953d4c`.
Không có product VERSION thống nhất; thêm `VERSION=0.10.4-rc.1` và đồng bộ README,
changelog, guide. Đây là **candidate chưa phát hành**, không tạo tag khi gate chưa đạt.
Perfex migration 341, CodeIgniter 3.1.11, frontend package 1.0.0 và module versions
là các trục khác; không tăng chúng khi không đổi schema/module/vendor.

## Inventory và trace chức năng

| Nhóm | Evidence/call path | Trạng thái | Giới hạn/rủi ro |
|---|---|---|---|
| Bootstrap/framework | `index.php` → `system/core/CodeIgniter.php:58`; `application/config/config.php` | STATIC ONLY | Không chứng nhận request runtime |
| Cài mới | `installed/install.class.php:81` → parser `database.sql` | VERIFIED thiếu artifact Git | SQL có local nhưng `git ls-files installed/database.sql` rỗng |
| Upgrade | `application/config/migration.php:72`; app migrations và module install hooks | STATIC ONLY | Không chạy migration vào DB thật |
| Auth/role | `application/models/Authentication_model.php:31,66,90,136`; `application/core/AdminController.php` | STATIC ONLY | Session/CSRF/record access cần HTTP fixture |
| Tenant HTTP | `application/hooks/KtSaasTenantBootstrap.php:54,59` → `TenantHttpDatabaseLifecycle` → `DatabaseSwitcher` | STATIC ONLY + context test VERIFIED | Lifecycle có file chưa track; host/database fail-closed chưa chứng nhận live |
| Tenant DB | `modules/kt_saas/tenant_bootstrap/TenantHttpDatabaseLifecycle.php:40,52`; `DatabaseSwitcher.php:7,25` | STATIC ONLY | Database-per-tenant, không giả định mọi bảng có tenant_id; company từ trusted context |
| Provisioning | `modules/kt_saas/services/ProvisioningJobRunner.php:172,266,524,647` | STATIC ONLY | Create/drop DB, clone schema/install side effects; không chạy audit |
| CRM customers/invoices/tasks | `application/controllers/admin/`, `application/models/`, `application/views/admin/` | STATIC ONLY | Inventory không chứng minh transaction/import/concurrency từng nghiệp vụ |
| Inventory | `modules/kt_inventory/kt_inventory.php:125` và invoice hooks 126–129 | STATIC ONLY | Hooks có thể gây tồn kho side effect; chưa runtime |
| Integration/DMS | hub routes → controller/model + signed validator | STATIC ONLY; sanitizer/route/security tests VERIFIED | Không đưa source Salesrep vào CRM, không gọi DMS |
| SePay | `modules/kt_sepay/controllers/Kt_sepay_webhook.php:15,20,35,106,163` | STATIC ONLY | Public webhook → security → tenant resolution; chưa replay HTTP/provider |
| Landing | `modules/kt_landing/controllers/Kt_landing_public.php`; module hooks | STATIC ONLY | Public route logic có user modifications |
| MISA | local untracked `modules/kt_misa_amis_accounting/`; api client gate + contract tests | STATIC ONLY + 3 focused tests VERIFIED | Không kết luận MOCK/LOCAL/production certification từ unit/static tests |
| Cron | `application/controllers/Cron.php:9,11,17` → cron models/hooks | STATIC ONLY | APP_CRON_KEY chỉ kiểm tra khi defined; cron có email/provider side effects |
| Storage/cache/session | `application/config/config.php`, SaaS manifests/runtime/cache; setup script | STATIC ONLY | Tenant file/cache/log isolation toàn hệ thống chưa verified |
| Build/dependencies | `package.json` build scripts; `application/composer.json`; 5 bundled vendors | platform check VERIFIED | Asset build chưa chạy vì worktree assets đang dirty |
| Deployment | `scripts/setup-live.sh`, `live-smoke-check.sh`, `live-diagnose.sh` | fixture/loopback VERIFIED | Linux owner/FPM/Nginx/TLS chưa chạy |
| CI/Docker | root project configuration search | NOT FOUND | Không nhầm workflow trong vendor thành pipeline CRM |

Inventory JSON liệt kê 17 module directories, bootstrap/version/controllers/routes/tests
và tracked status. Số directory không phải số module active hay production-proven.

## Findings

| ID | Severity | Evidence/điều kiện/tác động | Xử lý và kiểm chứng |
|---|---|---|---|
| CRM-001 | HIGH | `scripts/live-smoke-check.sh` baseline bỏ qua curl lỗi, 404/500/refused đều exit 0: deployment gate xanh giả | FIXED: GET, TLS verify, timeout, kiểm status/exit. Real script loopback trước 3 fail; sau 5/5 PASS |
| CRM-002 | HIGH | `installed/install.class.php:81` yêu cầu database.sql không có trong Git; clone mới thiếu schema | BLOCKER: cần schema clean hợp lệ/license và isolated install/upgrade test; không commit SQL local chưa phân loại |
| CRM-003 | HIGH khi profiler bật | `system/libraries/Profiler.php:108` curly-brace string offset không còn hợp lệ PHP 8.3 | VERIFIED lint FAIL_PREEXISTING; framework không sửa theo ranh giới vendor; cần bản vendor tương thích được phép |
| CRM-004 | HIGH release packaging | `application/hooks/KtSaasTenantBootstrap.php:54` require lifecycle đang untracked; nhiều modules/tests local khác Git HEAD | BLOCKER: không stage user work tự động; branch audit không phải bản đầy đủ của runtime local |
| CRM-005 | MEDIUM | setup baseline chmod rộng source/bootstrap và che lỗi chmod | FIXED phạm vi runtime dirs, fail closed; temp fixture 2 checks PASS; quyền Linux thực chưa verified |
| CRM-006 | MEDIUM potential exposure | `scripts/live-diagnose.sh` baseline tail nội dung log không redaction | FIXED bỏ tự in nội dung log. Chưa khẳng định có secret thực tế bị leak |
| CRM-007 | MEDIUM configuration risk | `application/config/config.php:485` CSRF mặc định false nếu APP_CSRF_PROTECTION thiếu | STATIC ONLY; sample live bật true; không sửa file dirty. Cần direct-POST/session tests trước live |

Không gắn exploit label cho SQLi/XSS/SSRF/upload chỉ bằng grep. Chưa hoàn tất
dynamic penetration, dependency CVE audit, mọi tenant/company record path, backup
restore và all-feature runtime suite; các phần đó **NOT VERIFIED**, không phải PASS.

## Thay đổi có chủ đích

- 3 script live: fail-closed smoke, preflight/setup permissions, diagnostics không tự dump log.
- `scripts/deploy-preflight.php`: kiểm PHP/extensions/files, không bootstrap hoặc DB.
- 2 Node regression tests ở `tests/release/`: real Bash scripts trên loopback/temp.
- `tools/release-audit.cjs`: read-only inventory/lint/focused tests; tạo JSON evidence.
- `VERSION`, `README.md`, `CHANGELOG.md`, `docs/DEPLOYMENT_RELEASE.md`.
- Banner lịch sử ở `README_DEPLOY.md`, `docs/DEPLOYMENT.md`, `docs/DEPLOYMENT_V2.md`.
- Báo cáo này và evidence JSON. Không source DMS, schema, fixture CRM hay accounting mutation.

## Verification

| Lệnh | Kết quả/phạm vi |
|---|---|
| `node tests/release/deployment-smoke.test.cjs` | PASS 5 cases, actual Bash + loopback HTTP 200/302/404/500/refused |
| `node tests/release/setup-live.test.cjs` | PASS missing-dependency fail-before-write, idempotent setup/config preservation temp fixture |
| `node tools/release-audit.cjs docs/reports/CRM_RELEASE_AUDIT_20260927_EVIDENCE.json` | 2.282/2.283 PHP lint PASS, 8/8 focused tests PASS; framework Profiler fail giữ nguyên; runner trả nonzero khi gate fail |
| `composer --working-dir=application check-platform-reqs --no-dev` | PASS local installed platform; không phải vulnerability audit |
| `php scripts/deploy-preflight.php` | PASS local prerequisites; không chứng nhận schema/service |
| `bash -n` mỗi script sửa; `node --check` mỗi JS thêm; `php -l` preflight | Kiểm syntax riêng, không dùng bash -n nhiều filenames như nhiều test |
| `git diff --check` | Không whitespace errors; Git cảnh báo LF/CRLF là line-ending warning |

Focused tests: IntegrationErrorSanitizerTest, DmsRoutePermissionGovernanceTest,
DmsRenderedSecurityNoSecretsTest, KtSaasContextRestorationTest,
DmsSourceOfTruthCopySmokeTest, MisaFoundationContractTest, MisaStateMachineTest,
MisaSecurityStaticTest. DmsSourceOfTruthCopySmokeTest **PASS trong lần audit này**;
không gán fail chỉ vì lịch sử nói fail. Các test local untracked không được đưa vào
release; JSON ghi đường dẫn và output hash cho mỗi lần chạy.

Secret scan chỉ nhận diện private-key/GitHub-token/AWS-key patterns trong tracked text
<=2 MiB, không phải forensic scan Git history hay mọi credential. Không tìm thấy match
trong phạm vi này; không có tracked file >10 MiB trong inventory. Không in secret.
Không chạy application build, browser, Phase11 DB worker, provider integration,
migration hay production smoke để tránh mutation chưa xác minh target.

## GitHub và next gates

Chỉ xuất bản branch chuẩn bị audit/deployment để review, không merge main hoặc tạo
release tag. Commit/SHA/PR được xác nhận trong handoff sau push; báo cáo này không tự
ghi SHA của chính commit chứa nó. Repository description trước audit: rỗng; sau:
“Khách Tốt CRM — nền tảng CRM/SaaS và tích hợp; Salesrep DMS là sản phẩm riêng.”
Đã xác nhận lại bằng `gh repo view`, không thay visibility hoặc permissions.

Thứ tự tiếp theo:

1. Review riêng user-owned dirty/untracked changes; quyết định source đủ dùng cần phát hành.
2. Cung cấp clean installer schema/license và vendor version PHP-compatible.
3. Chạy clean-clone isolated install + repeated upgrade + host/tenant/company/role/CSRF
   runtime + browser/asset + queue/cron/retry; không dùng live DB.
4. Build assets trong clean staging; secret/history/dependency security audit đầy đủ.
5. Xác nhận actual Linux/FPM/DB/TLS; backup/restore rehearsal; duyệt release/tag và live window.

Tài liệu từng bước: [clone → staging → live](../DEPLOYMENT_RELEASE.md).
Không có production deploy, production DB access, real provider/MISA calls hay accounting writes.
