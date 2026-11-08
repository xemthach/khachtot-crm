# Hướng dẫn cài đặt CRM Khách Tốt trên live server

Tài liệu này chỉ hướng dẫn thao tác cho operator. Không chạy các lệnh dưới đây trên máy phát triển hoặc live server trong phạm vi khảo sát này.

## 1. Giá trị cần quản trị viên xác nhận

Thay toàn bộ placeholder trước khi chạy lệnh: `<APP_DOMAIN>`, `<PROJECT_PATH>`, `<PHP_FPM_SOCKET_OR_PORT>`, `<DB_HOST>`, `<DB_NAME>`, `<DB_USER>`, `<DB_PASSWORD>`, `<MAIL_*>`, `<DMS_BASE_URL>` và `<MAP_API_KEY>`.

Không đặt secret vào Git hoặc tài liệu.

## 2. Yêu cầu runtime

Source ghi nhận PHP `>=8.1`, MySQL/MariaDB qua `mysqli`, PHP vendor bundle trong `application/vendor` và module vendor. Danh sách extension cần kiểm tra nằm tại `scripts/deploy-preflight.php:6-15`, gồm `mysqli`, `pdo`, `mbstring`, `curl`, `openssl`, `zip`, `gd`, `fileinfo`, `xml`, `dom`, `simplexml`, `json`, `imap`, `bcmath`, `ctype`, `filter`, `hash`, `xmlwriter`, `iconv` và `allow_url_fopen`.

Frontend có manifest Laravel Mix tại `package.json`. Node/npm chỉ cần trên máy build khi operator chọn build asset; source không có Composer manifest root.

## 3. Lấy source trên live server

Chạy trên live server, tại thư mục cha của project:

```sh
git clone https://github.com/xemthach/khachtot-crm.git <PROJECT_PATH>
cd <PROJECT_PATH>
git fetch origin
git checkout <SYNC_COMMIT>
git rev-parse HEAD
```

`<SYNC_COMMIT>` phải là commit đã được báo cáo sau khi push.

## 4. Cấu hình ứng dụng

Chạy trên live server, trong `<PROJECT_PATH>`. Tạo/cấu hình các file runtime từ file mẫu hiện có, giữ file ngoài Git:

```sh
cp application/config/database.example.php application/config/database.php
cp application/config/config.example.php application/config/config.php
cp application/config/app-config.sample.php application/config/app-config.php
```

Operator phải chỉnh placeholder trong các file runtime bằng editor an toàn, không ghi secret vào command history. Cấu hình gồm domain HTTPS, database, encryption/app keys, mail và các provider thực sự được cấp phép.

## 5. Nginx và PHP-FPM

Document root phải là `<PROJECT_PATH>` vì entry point là `<PROJECT_PATH>/index.php`, không phải `<PROJECT_PATH>/public`.

Mẫu server block trên live server; thay placeholder trước khi dùng và lưu vào file site chưa active để kiểm tra:

```nginx
server {
    listen 80;
    server_name <APP_DOMAIN>;
    root <PROJECT_PATH>;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~* ^/(uploads|temp|storage|backups)/.*\.(php|phtml|php3|php4|php5|php7|phar|cgi|pl|py|sh)$ {
        return 403;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass <PHP_FPM_SOCKET_OR_PORT>;
        fastcgi_param HTTP_HOST $host;
        fastcgi_param SERVER_NAME $host;
        fastcgi_param HTTPS $https;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

Chạy trên live server sau khi operator đã đặt site config:

```sh
sudo nginx -t
sudo systemctl reload nginx
```

Không thay thế file site đang chạy nếu chưa có bản sao và quy trình operator đã phê duyệt.

## 6. Quyền thư mục và cron

Operator cấp quyền ghi tối thiểu cho các thư mục runtime được source sử dụng: `uploads/`, `media/`, `temp/`, `application/cache/`, `application/logs/` và storage module tương ứng. Không cấp quyền ghi toàn bộ project nếu không cần.

Cron chính được source ghi nhận tại `docs/CRON_LIVE_SETUP.md:31-32`:

```cron
* * * * * cd <PROJECT_PATH> && flock -n /tmp/khachtot_core_cron.lock /usr/bin/php index.php cron/index >> <CRON_LOG_PATH>/core-cron.log 2>&1
```

`<CRON_LOG_PATH>` là thư mục log riêng do operator tạo và phân quyền. Các module hook vào core cron; không tự thêm route cron riêng nếu chưa có bằng chứng trong source.

## 7. TLS, cập nhật và rollback

- Cấp chứng chỉ hợp lệ cho `<APP_DOMAIN>` tại origin.
- Dùng Cloudflare `Full (strict)` nếu domain đi qua Cloudflare và origin có chứng chỉ hợp lệ.
- Không dùng chứng chỉ Laragon local trên live server.
- Khi cập nhật, checkout commit mới trong thư mục phát hành riêng và xác nhận `git rev-parse HEAD`.
- Rollback bằng cách trỏ lại commit phát hành trước; không xóa thư mục dữ liệu runtime và không chạy migration tự động.
- Kiểm tra sau triển khai: HTTPS, login route, static assets, route callback/API cần dùng, PHP-FPM error log, Nginx error log và cron log.

## 8. Preflight chỉ-đọc

Chạy trên live server tại `<PROJECT_PATH>` sau khi cấu hình runtime:

```sh
php scripts/deploy-preflight.php
```

Script được source mô tả là không kết nối database, không chạy migration và không gọi provider — `scripts/deploy-preflight.php:18`.
