# Báo cáo quét hiện trạng localhost CRM Khách Tốt

Ngày khảo sát: 2026-11-08 (Asia/Bangkok)

## Nhận diện runtime

- Virtual host Nginx: `khachtot.test` và `*.khachtot.test` — `D:\laragon\etc\nginx\sites-enabled\auto.khachtot.test.conf:4`.
- Project root/web root: `D:\laragon\www\khachtot` — cùng file, dòng 5.
- Entry point và fallback router: `index.php`, `try_files ... /index.php$is_args$args` — cùng file, dòng 10.
- PHP-FPM local upstream: `127.0.0.1:9003` và `127.0.0.1:9004` — `D:\laragon\etc\nginx\php_upstream.conf:5-6`.
- Local TLS certificate: Laragon certificate paths — `auto.khachtot.test.conf:28-29`. Đây là cấu hình local, không phải chứng chỉ production.

## Phiên bản và Git

- Version source: `0.10.4-rc.1` — `VERSION:1`.
- Mô tả stack: Perfex/CodeIgniter 3 — `README.md:3`.
- Core schema được mô tả là `341` — `README.md:10`.
- PHP tối thiểu được source yêu cầu: `>=8.1`; database driver là MySQL/MariaDB qua `mysqli` — `README.md:22-23`.
- PHP vendor được bundle; không có Composer manifest ở root — `README.md:25`.
- Frontend dùng Laravel Mix — `README.md:26` và `package.json`.
- Document root là repository root có `index.php`, không phải `public/` — `README.md:27`.
- Branch localhost: `audit/release-0.10.4-rc.1-20260927`.
- Commit localhost trước đồng bộ: `741a660be4e710052d09b541a2cf990a195df084`.
- Remote: `https://github.com/xemthach/khachtot-crm.git`.
- Snapshot localhost có thay đổi chưa commit; version không được coi là chỉ một commit cho đến khi snapshot sync được commit trên GitHub.

## Thành phần source được tìm thấy

- Core CRM: `application/`, `system/`, `index.php`.
- Module CRM/SaaS/integration: `modules/`, gồm các module có route/config/install/schema riêng.
- Frontend và static assets: `assets/`, `resources/`, `package.json`, `package-lock.json`, `webpack.mix.js`, `tailwind.config.js`.
- Deployment/operator scripts: `scripts/deploy-preflight.php`, `scripts/setup-live.sh`, `scripts/live-diagnose.sh`, `scripts/check-permissions.sh`.
- Database/config inputs: `application/config/database.example.php`, `application/config/config.example.php`, `application/config/app-config.sample.php`, `.env.example`.
- Cron/worker: core `application/controllers/Cron.php`, cron hooks trong module, và các worker route trong `application/config/my_routes.php`.

## Routes và dịch vụ được tham chiếu

- Core cron: `/cron/index` và CLI `php index.php cron/index` — `docs/CRON_LIVE_SETUP.md:31-32`.
- SePay webhook: `kt_sepay/webhook` — `docs/CRON_LIVE_SETUP.md:155`.
- MISA callback: `misa/callback` — `application/config/my_routes.php:57`.
- Integration/API routes: nhóm `/api/v1/...` — `application/config/my_routes.php:81-104`.
- DMS SSO: `sso/dms` — `application/config/my_routes.php:105`.
- Local hostnames trong Windows hosts gồm `khachtot.test` và các tenant host dưới `*.khachtot.test`; đây là hostname local, chưa phải bằng chứng DNS production.
- `.env.example` tham chiếu mẫu `https://khachtot.com/`, `khachtot.com`, `DMS_BASE_URL`, mail, SePay, MatBao, map, Cloudflare và bridge credentials. Các giá trị mẫu này không được coi là credential hoặc xác nhận dịch vụ production.

## Phân loại working tree

Tại thời điểm khảo sát có 1.209 mục thay đổi: 141 tracked changes và 1.068 untracked files.

- Được xem xét để đồng bộ: source trong `application/`, `modules/`, `assets/`, `resources/`, `system/`, `scripts/`, `tests/`, các manifest/config mẫu và tài liệu triển khai/audit có tính chất source documentation.
- Không đồng bộ: `.env`, config runtime chứa secret, upload/media/cache/log/session/cookie, backup/dump/storage runtime, `node_modules`, file `vps_khachtot` và `vps_khachtot.pub`, HTML/JSON runtime probe, browser artifacts, temporary files và archive không cần thiết cho source snapshot.
- `application/config/database.php` và `application/config/app-config.php` được xem là runtime config; chỉ file example/sample được đưa vào repository.
- Không đọc hoặc ghi giá trị bí mật vào báo cáo.

Snapshot đồng bộ được tạo từ worktree sạch dựa trên `origin/main`: 405 file được stage; 755 mục localhost bị loại khỏi snapshot theo các nhóm trên. Các file GitHub-only không xung đột được giữ nguyên trong worktree sạch.

## Giới hạn khảo sát

- Chưa có bằng chứng local về origin IP production, PHP-FPM socket/port production, phiên bản DB production, mail provider thực tế hoặc zone Cloudflare được quản lý.
- Không chạy test, migration, deployment, lệnh thay đổi dữ liệu hay thao tác trên live server.
