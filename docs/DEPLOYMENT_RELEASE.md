# Khách Tốt CRM: clone → staging → live

Candidate **0.10.4-rc.1 — release gate BLOCKED**. Guide này thay các chỉ dẫn live
cũ. Chưa được phép coi clone thành công là chứng nhận production.

## 1. Môi trường và checklist

Phương án có điều kiện: Debian/Ubuntu, Nginx, PHP-FPM 8.3, MySQL/MariaDB, Bash,
Git, curl, sudo. Distro/package version/server thật chưa xác nhận; kiểm tra package
của distro trước cài. Audit đã chạy trên Windows/Git Bash, PHP 8.3.16, Node 24.14.0.
Linux/FPM/TLS/migration/restore/tenant live chưa chạy trong audit.

- Chốt ref và SHA từ PR/tag được duyệt; candidate này chưa có release tag.
- Xác nhận license, quyền clone private bằng SSH deploy key hoặc `gh auth login`;
  không chèn token vào URL, log hay command history.
- Chuẩn bị domain landlord, wildcard tenant, DNS/TLS, trusted proxy/host mapping.
- Chốt DB host/version và user landlord/tenant; tenant dùng DB riêng theo code.
- Đủ dung lượng hai release, DB và backup; sao lưu config/key, landlord, tất cả
  tenant DB, uploads/media/manifests; restore thử trước production.
- Giữ encryption key cũ khi nâng cấp; mất key sẽ làm encrypted data không đọc được.

Ví dụ backup có điều kiện (cwd: thư mục backup quyền 700 ngoài web root):

```sh
umask 077
mysqldump --host='<DB_HOST>' --user='<BACKUP_USER>' -p \
  --single-transaction --routines --triggers '<DB_NAME>' > '<DB_NAME>.sql'
sha256sum '<DB_NAME>.sql' > '<DB_NAME>.sql.sha256'
```

Lặp từng tenant. Snapshot transactional không bảo đảm cho nontransactional tables;
tạm dừng ghi khi cần và đồng bộ snapshot file. Không lưu dump dưới public_html.

## 2. Clone trên build/staging host

Deploy user phải có thư mục mới; không di chuyển/xóa live directory để clone.

```sh
# cwd ban đầu: /srv/khachtot/releases đã được cấp quyền
cd /srv/khachtot/releases
git clone https://github.com/xemthach/khachtot-crm.git '<RELEASE_DIR>'
cd '<RELEASE_DIR>'
git fetch origin --tags
git checkout --detach '<RELEASE_REF>'
test "$(git rev-parse HEAD)" = '<EXPECTED_COMMIT>'
git status --short
php scripts/deploy-preflight.php
```

Thay mọi placeholder trước chạy. Nếu SHA không khớp, dừng. Branch audit chỉ mang
bản sửa/tài liệu đã review; không chứa mọi source local chưa commit.

## 3. Dependency/build

Vendor bundle: `application/vendor`, `modules/{backup,einvoice,openai,surveys}/vendor`.
Không chạy composer root/update khi deploy. Preflight kiểm tra autoload; thêm:

```sh
# cwd: root release; chỉ đọc platform requirements
composer --working-dir=application check-platform-reqs --no-dev
```

PHP core >=8.1; extensions từ installer/composer: mysqli, PDO, curl, openssl,
mbstring, iconv, imap, gd, zip, bcmath, ctype, filter, hash, XML/DOM/XMLWriter,
fileinfo, JSON; `allow_url_fopen` bật. So sánh CLI/FPM php.ini và extension list.

Asset đã bundle trong Git. Khi cần rebuild, dùng checkout BUILD riêng và Node/npm
pin theo staging, không ghi đè bundle đang live:

```sh
# cwd: root checkout BUILD
npm ci
npm run build
git diff --stat -- assets mix-manifest.json
```

Đây là lệnh từ package/lock thật; build Linux chưa được chứng nhận. Review bundle,
browser/MIME rồi đóng gói artifact. Không dùng npm update.

## 4. Permission và config

Document root là root release (`index.php`). Source thuộc deploy user, FPM chỉ
đọc; chỉ runtime folder được FPM ghi. Không chown toàn repo về FPM/chmod 777.
Ví dụ cho release directory mới (điều chỉnh user FPM theo server):

```sh
# cwd: root release
runtime_dirs=(uploads media temp application/cache application/logs \
  modules/kt_saas/storage modules/kt_saas/tenant_bootstrap/manifests \
  modules/kt_saas/tenant_bootstrap/runtime modules/kt_saas/tenant_bootstrap/cache)
mkdir -p "${runtime_dirs[@]}"
sudo chown -R www-data:www-data "${runtime_dirs[@]}"
sudo bash scripts/setup-live.sh
sudo chown '<DEPLOY_USER>':www-data application/config/app-config.php
sudo chmod 640 application/config/app-config.php
```

Setup tạo folder/config khi thiếu, không migrate. Khi upgrade phải mount/copy
runtime snapshot an toàn; không dùng folder trống thay file live. Script đã test
idempotency filesystem, chưa test owner/ACL Linux.

Sửa `application/config/app-config.php` qua kênh quản trị an toàn. Setup copy
`app-config.sample.php`; không thay `database.php` bằng template DB có giá trị cố định.

| Constant | Mục đích / giá trị giả |
|---|---|
| APP_BASE_URL | `https://crm.example.invalid/`, thay domain thật, slash cuối |
| APP_ENC_KEY | secret 32 ký tự từ vault; upgrade giữ key cũ |
| APP_DB_HOSTNAME | `localhost` hoặc host được duyệt |
| APP_DB_USERNAME / APP_DB_PASSWORD / APP_DB_NAME | credential vault / DB đã tạo |
| APP_DB_CHARSET / APP_DB_COLLATION | `utf8mb4` / `utf8mb4_unicode_ci` theo schema |
| SESS_DRIVER / SESS_SAVE_PATH | `database` / `sessions`; xác minh prefix/table |
| APP_SESSION_COOKIE_SAME_SITE | `Lax`; test Secure/HttpOnly/path/domain thực tế |
| APP_CSRF_PROTECTION | `true`, phải thử POST invalid token |
| KT_SAAS_ALLOW_HARD_DELETE | `false` |

Không mặc định `.env` được framework đọc; app-config PHP là config đã trace.
Không thay URL hàng loạt trong encrypted/JSON options; review từng option.

## 5. Web server/TLS

Nginx vhost cần root release, `index index.php`, và
`try_files $uri $uri/ /index.php?$query_string`. Fragment dưới phải review trên staging
và đặt trước handler PHP tổng quát:

```nginx
location ~ /\. { deny all; }
location ~* ^/(application|system|installed|tests|tools|scripts|docs|_archive_old_reports)(/|$) { deny all; }
location ~* ^/modules/[^/]+/(storage|tenant_bootstrap|tests|vendor)(/|$) { deny all; }
location ~* ^/(resources|node_modules)(/|$) { deny all; }
location ~* \.(sql|log|env|bak|old|backup|ini|md|json|ya?ml|lock|sh|ps1|cjs|map)$ { deny all; }
location ~* ^/(uploads|media|temp|storage|backups)/.*\.(php[0-9]*|phtml|phar|cgi|pl|py|sh)$ { deny all; }
location = /index.php {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    fastcgi_param CI_ENV production;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}
location ~ \.php(?:/|$) { deny all; }
```

JSON/assets ngoại lệ hoặc PHP vendor endpoint trực tiếp phải review riêng; không
mở toàn source để chữa 404. Chạy `sudo nginx -t` rồi reload service theo distro;
chưa chạy các bước này trong audit. Dùng TLS hợp lệ, không curl -k. CDN/proxy không
cache admin/auth/client/API/webhook/cron. Hosting panel không được ghi đè deny rules.

## 6. DB install/upgrade: cổng bắt buộc

**Cài mới BLOCKED**: `installed/install.class.php:81` đọc `database.sql` nhưng
`installed/database.sql` không nằm trong Git. Cần schema từ bộ cài được cấp phép,
hash và kiểm tra credential/data trước dùng. Không commit dump production làm seed.

Trên staging có schema hợp lệ: DBA tạo landlord DB/user rỗng → cho phép installer
gốc chỉ trong private/IP quản trị → chạy theo license → kiểm tra core/schema →
khóa installer → activate modules qua lifecycle thật → provision hai tenant giả →
chứng minh DB tách biệt, session/file/cache/job/record scope → restore thử backup.
Chưa có chứng nhận toàn bộ chuỗi, nên không có lệnh auto-migrate live trong guide.

Upgrade: maintenance ở proxy, dừng cron/worker, backup landlord+từng tenant+files+key,
restore staging. Core version nằm ở `application/config/migration.php` (341); SaaS
và module có upgrade hook riêng. Không `artisan migrate`. Thử upgrade gốc và chạy
lại trên staging, kiểm tra bảng/options/data trước/sau. Không suy luận upgrade
landlord đã upgrade mọi tenant. Khi gate này chưa đạt: dừng trước production.

## 7. Cron/worker

Core `application/controllers/Cron.php` throttles 300 giây, kiểm APP_CRON_KEY nếu
được đặt. `php index.php cron` có thể gửi email/chạy hooks thật; chỉ schedule sau
inventory hook/provider của môi trường được duyệt. Không chạy để audit production.
Nếu APP_CRON_KEY được đặt, dùng wrapper/vault đã duyệt cấp key, không bỏ key để chạy.
Dùng OS lock tránh chồng job, log ngoài web root. Hub process_jobs cần explicit
tenant context; không khai báo Redis/worker service theo tên thư mục. MISA external
transport giữ blocked. Guide không tự cấp phép external cron/provider.

## 8. Smoke/gate trước cutover

```sh
# cwd: root release; chạy staging trước
php scripts/deploy-preflight.php
bash scripts/live-smoke-check.sh 'https://<DOMAIN>'
```

Smoke chấp nhận 2xx/redirect; phải kiểm redirect target/body để không nhận login page
thay kết quả nghiệp vụ. Kiểm chứng thêm: login/logout/inactive/limited roles; POST
CSRF sai không đổi DB; Tenant A/B cookie/ID chéo bị từ chối; assets MIME/console;
upload; worker retry/lease cleanup; health; logs không fatal/secret.

GET `/.git/config`, `/application/config/app-config.php`, `/docs/`, `/tests/`, dump
và manifests phải 403/404. Không in body secret ra terminal chung.
Logs: `application/logs`, Nginx/FPM path theo server. Diagnostic chỉ liệt kê file,
không tự in nội dung. Khi tất cả gate đạt: đổi symlink/vhost theo runbook server,
reload FPM, smoke lại, bật từng cron đã duyệt, bỏ maintenance.

## 9. Rollback và lần cập nhật sau

Giữ artifact/SHA cũ và backup đã restore thử. App-only rollback chỉ an toàn khi
schema tương thích. Nếu schema/data đổi: maintenance, stop workers, restore đồng bộ
landlord+tenant+files+key từ snapshot, kiểm scope rồi mở traffic. Không hứa downgrade
migration an toàn; ghi RPO/mất dữ liệu sau backup để người vận hành duyệt.

Lần sau tạo release directory mới, fetch ref/SHA duyệt, preflight, build riêng khi
cần, staging migration/scope, backup, maintenance/cutover/smoke. Không `git pull`
trong cây live dirty hoặc force/reset/clean để bỏ conflict.
