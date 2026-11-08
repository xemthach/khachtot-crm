# Web installer an toàn qua SSH tunnel

Web installer không được đưa vào route public của CRM. Cách chạy mặc định là PHP built-in server bind 127.0.0.1, sau đó truy cập từ máy quản trị qua SSH tunnel.

## Trên live server

Sau khi clone đúng commit:

~~~bash
cd /home/lps_khachtotcom/public_html
chmod 750 scripts/run-web-installer.sh
sudo bash scripts/run-web-installer.sh
~~~

Script in ra URL local, lệnh SSH tunnel và token. Giữ token riêng tư.

## Trên máy quản trị

Mở terminal thứ hai:

~~~bash
ssh -L 8090:127.0.0.1:8090 <ssh-user>@<server>
~~~

Mở trình duyệt tại:

~~~text
http://127.0.0.1:8090/
~~~

Nhập token do script in ra. Điền domain, subdomain, project path, user/group, commit, database và encryption key.

## Quy trình trong GUI

1. Chọn Run plan.
2. Đọc toàn bộ output.
3. Kiểm tra project path, PHP-FPM socket, database và Nginx site.
4. Chọn Apply installation.
5. Sau khi hoàn tất, installer tự tạo lock file và không cho chạy lại.

## Bảo mật bắt buộc

- Không bind 0.0.0.0.
- Không mở port 8090 trên firewall.
- Không reverse proxy web installer ra Internet.
- Không lưu token hoặc file config chứa password vào Git.
- Không chạy installer trong thư mục project đang có dữ liệu; installer từ chối nếu project path đã tồn tại.
- Nếu cần chạy lại, phải kiểm tra lock/runtime bằng quy trình operator riêng; không xóa lock mù.

Web installer gọi scripts/install-live.sh, nên vẫn giữ các giới hạn: không migration, không import database dump, không cấp TLS/Cloudflare và không xóa dữ liệu.
