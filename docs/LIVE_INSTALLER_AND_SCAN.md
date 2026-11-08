# Live scan và installer tự động

## Scan chỉ-đọc

Sau khi clone repository trên live server:

```bash
cd /var/www/khachtot
chmod 750 scripts/live-system-scan.sh
sudo bash scripts/live-system-scan.sh /var/www/khachtot /root/khachtot-scan-$(date +%Y%m%d-%H%M%S).txt
```

Script chỉ đọc OS, PHP extensions, Nginx/PHP-FPM, Git, dependency, runtime directory, permission, disk và DNS. Nội dung config/secret không được in ra.

## Installer

Installer chỉ hỗ trợ server Linux Debian/Ubuntu có Nginx, PHP-FPM và MySQL/MariaDB client đã sẵn sàng. Mặc định là plan mode, không thay đổi server.

### 1. Tạo file thông tin riêng trên live server

```bash
cd /var/www/khachtot
cp scripts/live-install.env.example /root/khachtot-install.env
chmod 600 /root/khachtot-install.env
sudoedit /root/khachtot-install.env
```

Điền domain, subdomain, project path, user/group, commit, database, encryption key và PHP-FPM socket nếu không muốn auto-detect. Không commit file `/root/khachtot-install.env`.

### 2. Chạy plan trước

```bash
cd /var/www/khachtot
sudo bash scripts/install-live.sh --config /root/khachtot-install.env --plan
```

Plan mode chỉ in các hành động dự kiến. Kiểm tra lại các giá trị trước khi apply.

### 3. Chạy installer

```bash
cd /var/www/khachtot
sudo bash scripts/install-live.sh --config /root/khachtot-install.env --apply
```

Installer sẽ clone đúng commit vào project path mới, tạo runtime config từ file mẫu, tạo database nếu `CREATE_DATABASE=true`, tạo Nginx site, kiểm tra `nginx -t`, reload Nginx và tạo thư mục cron log.

Installer cố ý từ chối nếu project path đã tồn tại. Không có lệnh `git clean`, `git reset --hard`, xóa database, xóa upload hoặc chạy migration.

## Giới hạn cần operator kiểm tra

- Installer chưa cấp TLS hoặc thay đổi Cloudflare.
- Installer chưa import database schema/dump và không chạy migration.
- Giá trị `DB_PASSWORD` hoặc `DB_ADMIN_PASSWORD` có thể chứa ký tự SQL đặc biệt; dùng password policy phù hợp và kiểm tra client trước khi apply.
- Sau installer phải chạy `php scripts/deploy-preflight.php`, kiểm tra HTTP, log, cron và cấu hình Cloudflare theo tài liệu riêng.
