# Live server command runbook

Runbook dành cho operator. Chạy từng khối theo thứ tự; nếu một lệnh lỗi thì dừng và giữ nguyên trạng thái.

## 1. Xác nhận commit

Chạy trên máy quản trị viên:

```sh
git ls-remote https://github.com/xemthach/khachtot-crm.git refs/heads/main
```

Gán hash trả về vào `<SYNC_COMMIT>`. Hash đã xác nhận tại thời điểm lập tài liệu: `25d56dee5233997e859921a275ce0a53841ab5a5`.

## 2. Kiểm tra live server

Chạy trên live server:

```sh
cat /etc/os-release
php -v
php -m
nginx -v
mysql --version
systemctl is-active nginx
systemctl is-active php8.1-fpm || true
systemctl is-active php8.2-fpm || true
systemctl is-active php8.3-fpm || true
```

Nếu PHP-FPM dùng service name khác, thay bằng tên thực tế.

## 3. Clone đúng commit

Thay `<PROJECT_PATH>` và chạy trên live server tại thư mục cha của project:

```sh
test ! -e <PROJECT_PATH> || { echo "PROJECT_PATH already exists"; exit 1; }
git clone --branch main --single-branch https://github.com/xemthach/khachtot-crm.git <PROJECT_PATH>
cd <PROJECT_PATH>
git fetch --no-tags origin main
git checkout --detach <SYNC_COMMIT>
test "$(git rev-parse HEAD)" = "<SYNC_COMMIT>"
git status --short --branch
```

Không chạy `git clean`, `git reset --hard` hoặc lệnh xóa file.

## 4. Tạo config runtime

Chạy trong `<PROJECT_PATH>` trên live server:

```sh
cp -i application/config/database.example.php application/config/database.php
cp -i application/config/config.example.php application/config/config.php
cp -i application/config/app-config.sample.php application/config/app-config.php
sudoedit application/config/database.php
sudoedit application/config/config.php
sudoedit application/config/app-config.php
```

Không đặt secret trong command history hoặc Git. Kiểm tra quyền mà không in secret:

```sh
stat -c '%A %U:%G %n' application/config/database.php application/config/config.php application/config/app-config.php
```

## 5. Runtime directories

Thay `<DEPLOY_USER>` và `<DEPLOY_GROUP>`, chạy trong `<PROJECT_PATH>`:

```sh
for d in uploads media temp application/cache application/logs modules/kt_saas/storage; do
    sudo install -d -o <DEPLOY_USER> -g <DEPLOY_GROUP> -m 0750 "$d"
done
```

Không chạy `chmod -R 777`.

## 6. Nginx và PHP-FPM

Đọc endpoint hiện tại:

```sh
sudo nginx -T 2>/dev/null | grep -E 'listen |server_name|fastcgi_pass' | less
find /run/php -maxdepth 1 -type s -name 'php*-fpm.sock' -print 2>/dev/null
```

Tạo site config bằng editor, không dùng redirect shell:

```sh
sudoedit /etc/nginx/sites-available/<APP_DOMAIN>.conf
```

Dùng server block trong `docs/DEPLOYMENT_LIVE_SERVER.md`, thay các placeholder, rồi chạy:

```sh
test ! -e /etc/nginx/sites-enabled/<APP_DOMAIN>.conf || { echo "Nginx link already exists; inspect it first"; exit 1; }
sudo ln -s /etc/nginx/sites-available/<APP_DOMAIN>.conf /etc/nginx/sites-enabled/<APP_DOMAIN>.conf
sudo nginx -t
sudo systemctl reload nginx
```

## 7. Preflight và kiểm tra

Trong `<PROJECT_PATH>` trên live server:

```sh
php scripts/deploy-preflight.php
```

Sau khi DNS/TLS sẵn sàng:

```sh
curl -I https://<APP_DOMAIN>/
curl -sS -o /dev/null -w 'status=%{http_code} effective_url=%{url_effective}\n' https://<APP_DOMAIN>/
sudo tail -n 100 /var/log/nginx/error.log
sudo journalctl -u nginx --since '15 minutes ago' --no-pager
sudo journalctl -u <PHP_FPM_SERVICE> --since '15 minutes ago' --no-pager
```

## 8. Cron

Chạy trên live server:

```sh
sudo install -d -o <DEPLOY_USER> -g <DEPLOY_GROUP> -m 0750 <CRON_LOG_PATH>
crontab -e
```

Thêm đúng một dòng:

```cron
* * * * * cd <PROJECT_PATH> && flock -n /tmp/khachtot_core_cron.lock /usr/bin/php index.php cron/index >> <CRON_LOG_PATH>/core-cron.log 2>&1
```

Đọc log:

```sh
tail -n 100 <CRON_LOG_PATH>/core-cron.log
```

## 9. Update và rollback

Không clone đè release đang chạy. Dùng `<NEW_RELEASE_PATH>`:

```sh
test ! -e <NEW_RELEASE_PATH> || { echo "NEW_RELEASE_PATH already exists"; exit 1; }
git clone --branch main --single-branch https://github.com/xemthach/khachtot-crm.git <NEW_RELEASE_PATH>
cd <NEW_RELEASE_PATH>
git fetch --no-tags origin main
git checkout --detach <NEW_SYNC_COMMIT>
test "$(git rev-parse HEAD)" = "<NEW_SYNC_COMMIT>"
```

Sau khi kiểm tra release mới, cập nhật `root` bằng `sudoedit`, chạy `sudo nginx -t`, `sudo systemctl reload nginx`, rồi `curl -I https://<APP_DOMAIN>/`. Rollback chỉ trỏ Nginx về `<PREVIOUS_RELEASE_PATH>`; không xóa release cũ, upload hoặc database.
