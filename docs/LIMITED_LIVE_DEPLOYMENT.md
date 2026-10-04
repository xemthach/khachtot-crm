# Limited live — CRM cơ bản + SaaS/subdomain

**Candidate 0.10.4-rc.1; BLOCKED, không phải lệnh cho phép cutover.**
Ngày 2026-10-04. Runbook này thay phần triển khai trong DEPLOYMENT_RELEASE.md cho
phạm vi giới hạn. Không cần MISA/DMS được chứng nhận để mở CRM; phải chứng nhận
CRM và isolation trước. Không chép source dirty vào artifact.

## 0. Phạm vi, môi trường và điểm dừng

Môi trường tham chiếu: Ubuntu 24.04 LTS, Nginx distro, PHP-FPM/CLI 8.3,
MariaDB 10.11, Node 24.14.0/npm 11.13.0 trên máy build riêng.
**Linux/FPM/MariaDB/DNS/TLS chưa được chạy trong audit Windows này.**
Không suy ra các version đó tương thích ứng dụng chỉ từ package cài được.
Mỗi bước dưới chỉ áp dụng sau khi thay placeholder và làm trên staging trước.

| Phạm vi | Route/code | Trạng thái để mở traffic |
|---|---|---|
| Login/logout, staff/roles | admin/authentication; Authentication.php; AdminController | Có code; chờ HTTP role/session/CSRF |
| Dashboard, khách hàng, leads | admin/dashboard, admin/clients, admin/leads; models tương ứng | Có code; chờ CRUD/permission/data assertions |
| Tasks/projects | admin/tasks, admin/projects | Chưa đủ evidence; không tuyên bố đã mở |
| Landlord, tenant, plans/domains/provision | admin/kt_saas/*; Kt_saas_model; ProvisioningJobRunner | Bắt buộc staging A/B, không chứng nhận theo menu |
| MISA/DMS/Integration Hub | policy KtReleasePolicy; hooks InitModules/InitHook | Backend khóa trong limited profile; dữ liệu giữ nguyên |
| Các payment/einvoice/AI/connector khác | module registry từng DB | Không nằm scope; deactivate qua lifecycle, kiểm tra dependency trước |

Chưa có chức năng CRM nào được chứng nhận LIVE trong phiên này. Module SaaS,
resolver, entitlement và licence giữ nguyên. Hub bị khóa toàn bộ (gồm Zalo/TikTok);
không chỉ riêng DMS. Chỉ promote danh sách core sau khi test trong mục 12 đạt.

Đầu vào bắt buộc:
1. Một trong hai: schema Perfex 3.4.1 được cấp phép, provenance/hash/seed sạch;
   hoặc backup CRM hiện hữu được phép restore, version nguồn, danh sách DB/files.
2. Encryption key cũ, uploads/media và tenant mapping đi cùng backup, cấp qua vault.
3. Staging host/DB names, quyền restore/provision, IP quản trị và SMTP sink.
4. Quyền DNS khachtot.com, IP origin, nhà cung cấp DNS, Linux staging tương đương.
Không có đầu vào thì dừng phần phụ thuộc; không lấy SQL local chưa xác minh.

## 1. Chuẩn bị server (root/sudo; cwd /)

Chỉ chạy trên server staging mới đã xác nhận, không đổi firewall trên máy chưa có
console cứu hộ. Kỳ vọng SSH vẫn vào được, CLI/FPM cùng extensions. Sai version thì dừng.

```bash
sudo apt-get update
sudo apt-get install -y nginx mariadb-server php8.3-fpm php8.3-cli php8.3-mysql +  php8.3-curl php8.3-mbstring php8.3-xml php8.3-gd php8.3-zip php8.3-bcmath +  php8.3-imap git curl unzip acl ufw composer certbot python3-certbot-dns-cloudflare
sudo adduser --disabled-password --gecos '' deploy
sudo install -d -o deploy -g www-data -m 2750 /srv/khachtot/releases /srv/khachtot/shared
sudo install -d -o root -g root -m 0700 /srv/khachtot/backups
sudo ufw allow from <ADMIN_IP> to any port 22 proto tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
# Sau khi kiểm tra console/SSH mới: sudo ufw enable
php -v
php -m
sudo php-fpm8.3 -t
nginx -v
mariadb --version
```

Không expose 3306 hoặc PHP-FPM ra Internet. Không chmod777.
Build host Linux x86_64, user deploy, cwd thư mục toolchain mới:

```bash
mkdir -p /srv/khachtot/shared/build-toolchain
cd /srv/khachtot/shared/build-toolchain
test "$(uname -m)" = x86_64 || exit 1
curl --fail --proto '=https' --tlsv1.2 -O https://nodejs.org/dist/v24.14.0/node-v24.14.0-linux-x64.tar.xz
curl --fail --proto '=https' --tlsv1.2 -O https://nodejs.org/dist/v24.14.0/SHASUMS256.txt
grep ' node-v24.14.0-linux-x64.tar.xz$' SHASUMS256.txt | sha256sum -c - || exit 1
tar -xJf node-v24.14.0-linux-x64.tar.xz
export PATH="/srv/khachtot/shared/build-toolchain/node-v24.14.0-linux-x64/bin:$PATH"
node --version
npm --version
```

Kỳ vọng Node24.14.0; kiểm npm đi kèm, nếu khác11.13.0 phải pin/test trước tiếp.
Checksum tải qua HTTPS không thay signature verification theo policy tổ chức.
Không curl script | sudo bash. Download404/hash mismatch/architecture sai thì dừng.
Toolchain shared này dành staging/build, không symlink vào runtime public.

## 2. Git/build (deploy; cwd /srv/khachtot/releases)

Deploy key read-only, private key ngoài repo mode600; host key GitHub phải xác minh
qua nguồn chính thức, không tự tin kết quả ssh-keyscan. Không token trong URL.
Chọn SHA chứa limited policy sau review PR; 67a8523 chỉ là baseline build, chưa có policy.

```bash
cd /srv/khachtot/releases
git clone git@github.com:xemthach/khachtot-crm.git '<RELEASE_DIR>'
cd '<RELEASE_DIR>'
git checkout --detach '<APPROVED_LIMITED_SHA>'
test "$(git rev-parse HEAD)" = '<APPROVED_LIMITED_SHA>' || exit 1
test -z "$(git status --porcelain)" || exit 1
composer --working-dir=modules/backup install --no-dev --no-scripts --no-plugins --no-interaction --prefer-dist
composer --working-dir=modules/backup check-platform-reqs --no-dev
composer --working-dir=application check-platform-reqs --no-dev
npm ci --ignore-scripts --no-audit --no-fund
npm run build
node tests/release/asset-contract.test.cjs --built
php tests/release/profiler-compat.php
php tests/release/limited-policy.php
node tests/release/limited-http.test.cjs
php scripts/deploy-preflight.php --fresh-install
```

Kỳ vọng exit0 (riêng audit/lint full phải phân loại, không bỏ qua).
Bundle cả assets/builds, assets minified từ Mix, modules/einvoice/assets/builds và
mix-manifest.json; đối chiếu manifest/HTTP MIME. Không copy node_modules lên live;
build artifact riêng không chứa .git, tests, docs, tools, secrets/dumps. Giữ bản source
kiểm chứng riêng để chạy tests. Không xóa vendor runtime chỉ vì loại devDependency.
Không git pull trong current. Ghi SHA256 artifact, lock và output; đừng commit bundle
dirty tự động. Linux build chưa PASS thì chưa package để cutover.

## 3. Database — chọn A hoặc B (DBA; cwd thư mục backup ngoài web root)

### A. Cài mới

Verify schema publisher/version/hash/license trước; chỉ đặt schema hợp lệ vào
installed/database.sql trong staging, không commit. DBA tạo DB rỗng và user riêng,
ví dụ khung SQL sau, nhập mật khẩu qua phiên kín không lưu history:

```sql
CREATE DATABASE kt_landlord_stage CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'kt_app_stage'@'localhost' IDENTIFIED BY '<SECRET_FROM_VAULT>';
GRANT ALL PRIVILEGES ON kt_landlord_stage.* TO 'kt_app_stage'@'localhost';
```

Kỳ vọng DB trống trước installer. Quyền này **chưa đủ** cho provisioning tự động:
ProvisioningJobRunner::adminConnection dùng APP_DB_USERNAME/PASSWORD, tạo DB,
CREATE/ALTER USER và GRANT tenant.*; không có secret provisioning riêng.
Không cấp ALL ON *.* hoặc DB root cho application để né lỗi.
Đường ít quyền để thử: DBA chuẩn bị trước DB/user riêng cho từng tenant, grant đúng
DB; điền credentials qua form tenant để lifecycle dùng existing credentials.
Phải chứng minh createTenantDatabase thấy DB, canConnect pass và không fallback.
Nếu cần auto-create tùy ý: dừng, thiết kế tách privileged provisioning trước live.

### B. Restore hệ thống hiện hữu (không cần schema cài mới)

Được phép khi operator cung cấp snapshot nhất quán, quyền dùng dữ liệu staging,
key và manifest liệt kê landlord/tất cả tenant/files, version nguồn và checksum.
Không tự kết nối production để lấy backup. Operator tạo backup trong maintenance:

```bash
# DBA; cwd /srv/khachtot/backups/<SNAPSHOT>; mode700; credentials file mode600
umask 077
mariadb-dump --defaults-extra-file=/secure/backup.cnf --single-transaction +  --routines --triggers --events '<SOURCE_DB>' > '<SOURCE_DB>.sql'
sha256sum '<SOURCE_DB>.sql' > '<SOURCE_DB>.sql.sha256'
sha256sum -c '<SOURCE_DB>.sql.sha256'
# STAGING ONLY; target được DBA xác nhận mới/trống:
mariadb --defaults-extra-file=/secure/staging.cnf '<EMPTY_STAGING_DB>' < '<SOURCE_DB>.sql'
```

Lặp từng DB; non-InnoDB cần khóa/stop writes, single-transaction không đủ.
Không import event có lịch hoạt động khi event_scheduler bật. Giữ email/cron/provider
egress off tại staging, không gửi mail thật. Restore uploads/media/storage/key cùng
snapshot, mode phù hợp. Registry DB host/name phải trỏ staging qua lifecycle quản trị;
credentials tenant phải được re-encrypt bằng key bảo toàn. **STOP** nếu route quản trị
không sửa được mapping an toàn; không sửa SQL hàng loạt encrypted values.
Không dùng replay/provision để ghi đè tenant đã có dữ liệu.

Upgrade core theo admin lifecycle thật (migration config341), module theo lifecycle
riêng. Không artisan migrate. Ghi version/schema trước-sau từng DB; upgrade landlord
không chứng minh tenant đã upgrade. So sánh counts + checksums/business sample, restore
thử sang DB staging thứ hai. Chưa có baseline/backup hợp lệ: NOT RUN, không PASS.

## 4. Config và thư mục ghi (deploy + sudo; cwd root RELEASE)

Cài mới: không tạo app-config trước installer (file đó là cờ đã cài).
Restore: lấy config đã sanitize host/DB nhưng giữ APP_ENC_KEY, không generate key mới.
Sau cài/restore, lưu config ngoài web root /srv/khachtot/shared/app-config.php mode640
owner deploy:www-data, symlink vào application/config/app-config.php.
Template dưới là tên constant đã trace, placeholder phải thay qua editor/vault,
không paste secret vào Git/terminal ghi log:

```php
<?php
defined('BASEPATH') or exit('No direct script access allowed');
define('APP_BASE_URL', 'https://khachtot.com/');
define('APP_ENC_KEY', '<PRESERVED_OR_NEW_SECURE_KEY>');
define('APP_DB_HOSTNAME', 'localhost');
define('APP_DB_USERNAME', '<APP_DB_USER>');
define('APP_DB_PASSWORD', '<VAULT_SECRET>');
define('APP_DB_NAME', '<LANDLORD_DB>');
define('APP_DB_CHARSET', 'utf8mb4');
define('APP_DB_COLLATION', 'utf8mb4_unicode_ci');
define('KT_RELEASE_PROFILE', 'limited_live');
define('KT_SAAS_ALLOW_HARD_DELETE', false);
define('APP_CSRF_PROTECTION', true);
define('SESS_DRIVER', 'database');
define('SESS_SAVE_PATH', 'sessions');
define('APP_SESSION_COOKIE_NAME', 'kt_crm_session');
define('APP_SESSION_COOKIE_SAME_SITE', 'Lax');
define('APP_COOKIE_DOMAIN', '');
define('APP_COOKIE_SECURE', true);
define('APP_COOKIE_HTTPONLY', true);
define('APP_CRON_KEY', '<VAULT_CRON_KEY>');
```

Session table/prefix phải kiểm schema. Không giả định .env tự load. Không đặt cookie
domain .khachtot.com; kiểm tra Set-Cookie và copied-cookie A/B thực tế.
Profile mặc định limited khi constant thiếu hoặc sai; chỉ literal standard phía server
có thể bỏ khóa — không đổi qua UI/query. Bật lại cần deployment được duyệt, đủ contract,
credential, provider/tenant tests và quyền operator, không chỉ toggle.

Runtime dirs: uploads, media, temp, application/cache, application/logs,
modules/kt_saas/storage, tenant_bootstrap/{manifests,runtime,cache}.
Tạo cùng path dưới shared (giữ hierarchy), symlink khi release mới; restore dữ liệu trước.
FPM owner chỉ các runtime dirs (750/640, thư mục cần group-write dùng2770);
source deploy:www-data 750/640, FPM không ghi source/vendor.
Kiểm tra symlink không trỏ production khi staging. Installer quyền tạm ở mục7.

## 5. DNS và tenant hosts (DNS operator; DNS-01 Cloudflare chỉ là phương án tham chiếu)

| Record | Name | Value | Proxy |
|---|---|---|---|
| A | khachtot.com (@) | <ORIGIN_IPV4> | DNS-only giai đoạn chứng nhận |
| A | *.khachtot.com (*) | <ORIGIN_IPV4> | DNS-only |
| AAAA | @ và * | Chỉ thêm khi IPv6 origin đã test | DNS-only |

APP_BASE_URL + option kt_saas_landlord_host = khachtot.com;
kt_saas_base_domain = khachtot.com. Resolver lấy option landlord, không mặc định www.
Wildcard DNS không tạo tenant. abc chỉ phục vụ sau registry/lifecycle thành công.
CDN sau này cần review trusted proxy và bypass admin/auth/API; không bật cache full-page.
`dig +short khachtot.com`, `dig +short abc.khachtot.com` phải đúng origin trước tiếp.

## 6. TLS (root; cwd /root)

Chọn Cloudflare DNS nếu zone thực sự do Cloudflare quản lý; nếu không, dừng và chọn
plugin chính thức của DNS provider, không giả sử Cloudflare. Token giới hạn DNS Edit
đúng zone, file /root/.secrets/cloudflare.ini mode600 chứa dns_cloudflare_api_token.
Không ghi token vào shell history, repo/report.

```bash
sudo certbot certonly --dns-cloudflare +  --dns-cloudflare-credentials /root/.secrets/cloudflare.ini +  --cert-name khachtot.com -d khachtot.com -d '*.khachtot.com' +  --email '<OPS_EMAIL>' --agree-tos --non-interactive
sudo openssl x509 -in /etc/letsencrypt/live/khachtot.com/fullchain.pem +  -noout -dates -ext subjectAltName
sudo certbot renew --dry-run
sudo systemctl status certbot.timer
```

SAN phải có root VÀ wildcard; wildcard không bao phủ root hoặc a.b.khachtot.com.
DNS-01 cho wildcard; deploy hook reload nginx sau renewal thành công:
/etc/letsencrypt/renewal-hooks/deploy/nginx.sh (root755): shebang /bin/sh rồi
`nginx -t && systemctl reload nginx`. Test renewal/reload; không curl -k.

## 7. Nginx (root; /etc/nginx/sites-available/khachtot)

Thay socket nếu pool khác. Đây là config tham chiếu đầy đủ, chưa nginx -t trên Linux.
Tạo /srv/khachtot/current trỏ release staging đã config; không đổi live symlink ở audit.
Chỉ công bố hosts đã kiểm chứng: /etc/nginx/khachtot-hosts.map chứa
`khachtot.com 1;`, rồi mỗi tenant đã provision/test một dòng `abc.khachtot.com 1;`.
Không dùng wildcard allowlist: unknown tenant bị từ chối trước DB.

```nginx
map $host $kt_registered_host {
    default 0;
    include /etc/nginx/khachtot-hosts.map;
}
server {
    listen 80 default_server;
    server_name _;
    return 444;
}
server {
    listen 80;
    server_name khachtot.com *.khachtot.com;
    if ($kt_registered_host = 0) { return 444; }
    return 301 https://$host$request_uri;
}
server {
    listen 443 ssl default_server;
    server_name _;
    ssl_reject_handshake on;
}
server {
    listen 443 ssl;
    server_name khachtot.com *.khachtot.com;
    ssl_certificate /etc/letsencrypt/live/khachtot.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/khachtot.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    root /srv/khachtot/current;
    index index.php;
    client_max_body_size 20m;
    access_log /var/log/nginx/khachtot-access.log;
    error_log /var/log/nginx/khachtot-error.log;
    if ($kt_registered_host = 0) { return 444; }
    if (-f /srv/khachtot/shared/maintenance.flag) { return 503; }
    location = /install { return 403; }
    location ~ /\. { deny all; }
    location ~* ^/(application|system|installed|tests|tools|scripts|docs|resources|node_modules|temp|backups|storage|_archive_old_reports)(/|$) { deny all; }
    location ~* ^/modules/[^/]+/(storage|tenant_bootstrap|vendor|tests|docs|controllers|models|services|config|migrations|provisioning|cron)(/|$) { deny all; }
    location ~* \.(sql|log|env|bak[^/]*|old|backup|ini|md|json|ya?ml|lock|sh|ps1|cjs|map)$ { deny all; }
    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root/index.php;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_param HTTPS on;
        fastcgi_param HTTP_PROXY "";
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~* \.(php[0-9]*|phtml|phar)(/|$) { deny all; }
    location / { try_files $uri $uri/ /index.php?$query_string; }
}
```

Symlink site into sites-enabled, check conflicts with default vhost, run nginx -t
then reload only after success. Default root site disabled only after review.
No generic PHP handler. Test /.git/config, /application/config/app-config.php,
/modules/.../storage, SQL/backups and php uploads return403/404. Assets must correct MIME.
Some frontend JSON assets may need narrow reviewed exception; never open all JSON/PHP.

### Installer riêng, chỉ private access

Normal public vhost luôn chặn installer. A-only: thêm server riêng listen127.0.0.1:8089,
SSH tunnel operator, không expose port/firewall. Không tạo app-config trước bước cuối.

```nginx
server {
    listen 127.0.0.1:8089;
    server_name localhost;
    root /srv/khachtot/current;
    access_log off; # installer POST chứa credentials, không log body
    error_log /var/log/nginx/khachtot-installer-error.log;
    location = /installed/index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root/installed/index.php;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~* ^/(installed|assets)/.*\.(css|js|png|jpg|gif|woff2?|ttf|svg|ico)$ { try_files $uri =404; }
    location / { return 403; }
}
```

Operator máy quản trị: `ssh -L 8089:127.0.0.1:8089 deploy@<STAGING_SERVER>`,
mở http://127.0.0.1:8089/installed/index.php. Base URL nhập domain HTTPS đích,
không localhost tunnel. Không log form/HAR.
Chỉ cấp ACL tạm `setfacl -m u:www-data:rwx application/config` trong release mới;
source khác read-only. Sau thành công thu hồi ACL, chuyển config sang shared,
chmod640, bỏ private installer server, nginx -t/reload, kiểm port8089 đóng.
Nếu installer yêu cầu ghi rộng hơn: STOP và trace, không chmod toàn source.
Chưa chạy import thành công thì không đánh dấu fresh-install PASS.

## 8. SaaS provisioning (landlord operator; browser HTTPS root)

1. Login admin/khachtot.com; activate kt_saas bằng module lifecycle thật, license hợp lệ.
2. admin/kt_saas/settings: base domain, landlord host, runtime_enabled, DB host/port.
3. admin/kt_saas/plans: chọn module core cần thiết, không integrations; review limits.
4. admin/kt_saas/tenants: company/owner test, tenant_code, plan, subdomain abc,
   DB riêng đã DBA chuẩn bị và user/password của đúng DB (không app/DB root).
5. Dùng queue_provision_job/provision_jobs/run_provision_job qua UI hiện hữu.
   **STOP production provisioning** nếu method/CSRF chưa được chứng minh:
   các method run/retry/queue trong RC không có requirePost riêng, không sửa record
   hoặc tự gọi GET để né lifecycle.
6. Runner cloneLandlordSchema/seedReferenceData/seedModules/seedAdmin tạo dữ liệu;
   kiểm reference seed không copy customer/accounting/user cũ. Không replay lên DB có dữ liệu.
7. admin/kt_saas/domains: abc.khachtot.com phải gắn đúng tenant, active/provisioning done.
   Kiểm licence/entitlement từ lifecycle, không INSERT tay để bypass.
8. Đăng nhập bằng tài khoản tenant được lifecycle cấp an toàn, reset password;
   không ghi bootstrap password vào report. Chỉ sau smoke A/B thêm host vào map Nginx.

TenantResolver dùng domain registry; DatabaseSwitcher decrypt credential.
Patch limited chặn unknown host/switch thất bại và không fallback shared credential.
Đây chưa là chứng nhận isolation: phải kiểm DB query target, session, record, upload,
cache và job từng A/B. CLI hiện skip tenant bootstrap; không chạy cron landlord rồi
khẳng định tất cả tenant đã được xử lý.

## 9. Session/cache và provider lockdown

HTTP test login A/B, copy cookies hai chiều, wrong Host/query company, logout reuse,
inactive/limited staff, missing/invalid CSRF, token của user khác. Assert DB before/after,
redirect target và generic errors. Host-only cookies không tự chứng minh server isolation.
Không shared cache CDN cho admin/auth/API; Nginx config này không proxy_cache.
Static uploads công khai cần đánh giá file scope; không coi đường dẫn khó đoán là quyền.

Limited profile trả503 JSON không giả callback200, không khẳng định provider sẽ retry
bao nhiêu lần. Ngừng đăng ký callback upstream qua vận hành được phép trước cutover;
giữ inbox/ledger/credentials, không purge. Không khởi tạo hub hooks/model jobs mới.
Chứng nhận thiếu integration không chặn core nếu khóa được chứng minh; bypass khóa thì chặn.

## 10. Cron (CHƯA SCHEDULE; operator; cwd root release)

Core Cron::index kiểm APP_CRON_KEY, gọi Cron_model::run. Hook SaaS after_cron_run
chạy domain verification, billing, usage, backup cleanup, provisioning. Hub bị skip.
Core còn có email/reminder và modules khác: phải inventory active registry mỗi DB,
network spy + SMTP sink trên staging trước lịch. Không giả định không có outbound
chỉ vì hub disabled. Không chạy Kt_saas_cli certification methods trên live.

Sau khi gate đạt, dùng cron CLI qua wrapper mode750, user FPM, OS flock; key không
ghi trong crontab/process args. Wrapper bảo mật đọc key từ vault, gọi route đúng theo
CLI core đã test; **hiện chưa có wrapper đó được chứng nhận**, không cung cấp cron
giả chạy không key. Dừng lịch bằng disable timer/crontab đã inventory; kiểm process.
Log ngoài web root, rotate bằng logrotate distro, redact input/credentials.

## 11. Backup, cutover, rollback (operator/DBA; chỉ sau acceptance)

Maintenance proxy, stop cron/worker, snapshot DB và shared files/key cùng thời điểm.
Backup manifest phải gồm SHA cũ/mới, schema từng DB, timestamps, counts, checksums,
uploads hashes, key reference (không value), RPO/RTO. Restore rehearsal mục3B vào
staging thứ hai, login + record/tenant smoke, đo thời gian. File backup chmod600.
Không giữ backup trong uploads/public. Scheduled cleanup chưa được phép xóa rehearsal.

Cutover chỉ theo phê duyệt riêng: release mới immutable + shared symlinks; lưu target
current cũ; tạo next symlink rồi atomic rename, reload FPM, smoke private trước mở.
Không thực hiện cutover trong nhiệm vụ này. App-only rollback chỉ khi schema tương thích.
Nếu schema đổi: maintenance + stop jobs + restore đồng bộ DB/files/key tại snapshot
đã rehearsal, chấp nhận RPO được duyệt, không hứa down migration. Không đổi traffic
trước khi verify TLS/host/session của cả landlord/A/B.

## 12. Acceptance và troubleshooting

Mỗi test ghi SHA, actor, host, URL/method, expected/actual HTTP, DB before/after,
query target, secret/foreign marker scan. Thiếu evidence => NOT VERIFIED.

| Gate | Kỳ vọng |
|---|---|
| Build Linux + preflight + artifact boot | exit0, manifest/asset MIME200; no secret |
| Install A hoặc restore/upgrade B | baseline hợp lệ, schema/options/counts đúng; restore rehearsal |
| Login/logout + khách hàng/leads CRUD | quyền và record scope; login page không tính success |
| CSRF | POST valid thành công; thiếu/sai không mutation; audit không success giả |
| Tenant A/B | host→DB đúng; unknown host/failed switch không landlord; cookie/file/cache/job không leak |
| Integrations | trực tiếp route/alias/CLI/job bị chặn; network capture không provider business calls |
| DNS/TLS/vhost | SAN root+wildcard, renew dryrun, host lạ denied, nội bộ403/404 |
| Ops | backup/restore checksum/time, permissions, cron hooks inspected, rollback rehearsal |

| Lỗi | Kiểm tra an toàn / nơi xem |
|---|---|
| 502 | php-fpm8.3 -t; socket owner/path; journalctl -u php8.3-fpm; Nginx error log |
| Rewrite404 | nginx -T qua phiên kín; document root root-repo, try_files/index.php |
| Asset/MIME | curl -I đúng asset; build manifest, deny regex, không tải CSS từ login |
| Permission | namei -l file; FPM chỉ ghi runtime, ACL installer đã thu hồi |
| DB | host/grants/connectivity qua DBA; không in config/password; không fallback landlord |
| HTTPS/login loop | APP_BASE_URL, HTTPS FastCGI, Secure/host-only cookie; không tin arbitrary forwarded host |
| CSRF403 | token/cookie same session, exemptions đúng; không disable CSRF toàn cục |
| Tenant503 | registry/domain/status, runtime_enabled, DB credentials/key; unknown host expected denial |
| Cron | timer/lock/key, last_cron_run, module hook inventory; không thử job provider thật |

App logs application/logs; Nginx /var/log/nginx/khachtot-error.log; FPM journal.
Không paste raw logs chứa path/key/SQL/customer data vào issue.

Nguồn chính thức để review config:
[Nginx server names](https://nginx.org/en/docs/http/server_names.html),
[Nginx FastCGI](https://nginx.org/en/docs/http/ngx_http_fastcgi_module.html),
[Let's Encrypt DNS-01](https://letsencrypt.org/docs/challenge-types/),
[Certbot Cloudflare](https://certbot-dns-cloudflare.readthedocs.io/en/stable/).
Chúng xác minh cú pháp/cơ chế, không thay thế nginx -t/HTTPS runtime tại server thật.
