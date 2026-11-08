# Hướng dẫn Cloudflare DNS và domain CRM Khách Tốt

Tài liệu này chỉ hướng dẫn operator. Không thay đổi zone, nameserver, DNS record, proxy hoặc chứng chỉ trong phạm vi khảo sát.

## 1. Sơ đồ record theo bằng chứng

| Hostname | Dịch vụ/ứng dụng | Origin | Proxy đề xuất | Căn cứ/trạng thái |
|---|---|---|---|---|
| `<APP_DOMAIN>` | CRM web/HTTPS | `<ORIGIN_IP_OR_HOST>` | Proxied nếu chỉ phục vụ HTTP/HTTPS | Domain production chưa được xác nhận trong source |
| `<API_HOSTNAME>` | API/integration routes | `<ORIGIN_IP_OR_HOST>` | Proxied nếu API tương thích Cloudflare | Routes `/api/v1/...` tại `application/config/my_routes.php:81-104`; hostname production chưa xác định |
| `<CALLBACK_HOSTNAME>` | SePay/MISA callback | `<ORIGIN_IP_OR_HOST>` | Proxied nếu callback là HTTP(S) | `kt_sepay/webhook` và `misa/callback` được source tham chiếu; hostname production chưa xác định |
| `<MAIL_HOSTNAME>` | Mail SMTP | `<MAIL_ORIGIN>` | DNS-only | Mail hostname thực tế chưa có bằng chứng |
| `<SSH_HOSTNAME>` | SSH | `<ORIGIN_IP_OR_HOST>` | DNS-only hoặc không đưa qua proxy | SSH service không được xác định trong source |
| `khachtot.test`, `*.khachtot.test` | Local development only | `127.0.0.1` | Không đưa vào public Cloudflare zone | Nginx local `auto.khachtot.test.conf:4`, Windows hosts |
| `khachtot.com` | Cấu hình mẫu production | `<ORIGIN_IP_OR_HOST>` | Cần xác nhận | `.env.example:3-4,16`; chưa đủ bằng chứng domain đang hoạt động |

Không tạo record cho hostname chưa được operator xác nhận.

## 2. Thêm zone và kiểm tra nameserver

Trên Cloudflare Dashboard:

1. Add domain zone `<PRODUCTION_ZONE>`.
2. Ghi nhận nameserver Cloudflare được cấp.
3. Cập nhật nameserver tại registrar nếu operator được ủy quyền.
4. Chờ trạng thái zone Active.
5. Xác minh từ máy quản trị:

```sh
dig NS <PRODUCTION_ZONE>
dig A <APP_DOMAIN>
dig AAAA <APP_DOMAIN>
```

Nếu Windows không có `dig`, dùng công cụ DNS được operator phê duyệt hoặc `nslookup`.

## 3. Tạo record

Chỉ sau khi có origin thực tế:

- A/AAAA cho website/API nếu origin có IP tương ứng.
- CNAME khi provider cung cấp hostname origin.
- MX, SPF, DKIM, DMARC và SMTP host phải theo mail provider; các record này DNS-only.
- SSH hostname DNS-only nếu operator cần DNS name cho SSH.
- Không proxy các dịch vụ không phải HTTP/HTTPS.

Sau mỗi thay đổi, kiểm tra record authoritative và hostname HTTPS. Không đưa API token Cloudflare vào Git, shell history hoặc tài liệu.

## 4. SSL/TLS

- Cài chứng chỉ hợp lệ tại origin cho tất cả hostname HTTPS được dùng.
- Chọn `Full (strict)` khi origin có chứng chỉ hợp lệ và hostname khớp.
- Không dùng `Flexible` cho ứng dụng đã cấu hình HTTPS ở origin.
- Nếu có redirect HTTP→HTTPS ở Nginx, kiểm tra Cloudflare SSL mode để tránh redirect loop.
- Kiểm tra trình duyệt, `curl -I https://<APP_DOMAIN>/`, Nginx log và origin certificate chain.

## 5. API, callback, upload và cache

- Các route `/api/v1/...`, `sso/dms`, `misa/callback` và SePay webhook cần được xác nhận hostname, method, body size và timeout với operator/provider trước khi thêm rule.
- Không cache API, callback hoặc response chứa session/authentication nếu chưa có rule cụ thể.
- Giới hạn upload production phải được xác nhận từ PHP/Nginx thực tế trước khi đặt Cloudflare upload rule.
- WebSocket không được xác định trong source; không bật rule WebSocket chỉ dựa trên suy đoán.
- Firewall/Access chỉ cấu hình khi có danh sách IP, hostname hoặc route được operator xác nhận.

## 6. Xác minh cuối

```sh
dig A <APP_DOMAIN>
curl -I https://<APP_DOMAIN>/
curl -I https://<API_HOSTNAME>/<KNOWN_READ_ONLY_ROUTE>
```

Xác nhận DNS resolution, TLS chain, status code, redirect chain, origin access log và Cloudflare event log. Không gửi secret hoặc payload khách hàng trong bước xác minh.

## 7. Thông tin còn thiếu

- Zone production và danh sách hostname chính thức.
- Origin IPv4/IPv6 hoặc hostname origin.
- Mail provider và record mail cần giữ DNS-only.
- Callback URL chính thức của SePay/MISA/DMS.
- Chính sách cache, upload size, firewall và Access của operator.
