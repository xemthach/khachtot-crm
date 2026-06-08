# Hướng Dẫn Kết Nối Zalo OA Với Khách Tốt CRM

## 1. Zalo OA Connector Là Gì?

Zalo OA Connector giúp CRM nhận sự kiện từ Zalo Official Account, ví dụ người dùng nhắn tin hoặc quan tâm OA, sau đó tự động tạo hoặc gắn lead trong CRM.

Connector này thuộc KT Integration Hub và dùng webhook để nhận dữ liệu từ Zalo.

## 2. Hiện Tại Hỗ Trợ Gì?

| Tính năng | Trạng thái |
| --- | --- |
| Nhận webhook tin nhắn | Beta |
| Nhận sự kiện người dùng quan tâm OA | Beta |
| Tạo/gắn lead trong CRM | Beta |
| Chống trùng Zalo user | Có |
| Gửi tin nhắn từ CRM | Chưa hỗ trợ V1 |
| ZNS/ZBS | Chưa hỗ trợ V1 |
| Đồng bộ follower hàng loạt | Chưa hỗ trợ V1 |

V1 tập trung vào inbound webhook và lead capture. Các tính năng hội thoại, gửi tin, ZNS/ZBS sẽ được triển khai sau khi credential và quyền API thật đã được xác minh.

## 3. Cần Chuẩn Bị Gì?

Bạn cần có:

- Tài khoản Zalo Developers.
- Zalo App.
- Zalo Official Account.
- App ID.
- OA ID.
- OA Secret Key hoặc App Secret.
- OA Access Token.
- OA Refresh Token nếu có.
- Webhook URL do CRM sinh ra.

Tài liệu chính thức:

- Zalo Developers: https://developers.zalo.me/
- Zalo Docs: https://developers.zalo.me/docs
- Zalo OA Access Token: https://developers.zalo.me/docs/api/official-account-api/phu-luc/official-account-access-token-post-4307
- Zalo OA Webhook overview: https://developers.zalo.me/docs/official-account/webhook/tong-quan
- Zalo user sends message event: https://developers.zalo.me/docs/official-account/webhook/tin-nhan/su-kien-nguoi-dung-gui-tin-nhan
- Zalo user clicks "Nhắn tin" event: https://developers.zalo.me/docs/official-account/webhook/tin-nhan/su-kien-nguoi-dung-click-nut-nhan-tin-tren-official-account
- Zalo chatbot setup appendix: https://developers.zalo.me/docs/official-account/phu-luc/lam-the-nao-de-tao-chatbot-tra-loi-tu-dong-voi-zalo-api

## 4. Lấy Thông Tin Từ Zalo Developers

1. Đăng nhập Zalo Developers.
2. Tạo hoặc chọn ứng dụng Zalo App.
3. Liên kết ứng dụng với Zalo Official Account.
4. Cấp quyền OA cho ứng dụng theo hướng dẫn của Zalo.
5. Lấy OA Access Token qua API Explorer/OAuth theo tài liệu Zalo.
6. Copy App ID, OA ID, OA Secret Key/App Secret, Access Token và Refresh Token nếu có.
7. Không chia sẻ token hoặc secret cho người không có quyền quản trị.

Lưu ý: Để dùng Zalo OA OpenAPI, ứng dụng cần được OA liên kết và cấp quyền để lấy OA Access Token.

## 5. Cấu Hình Trong CRM

Trong Tenant Admin:

```text
Kết nối -> Zalo OA -> Tạo kết nối
```

Điền các trường:

| Field | Cách lấy | Ghi chú |
| --- | --- | --- |
| Tên kết nối | Tự đặt | Ví dụ: Zalo OA chính |
| Chế độ kết nối | Chọn Manual Token | V1 khuyến nghị Manual Token |
| App ID | Zalo Developers | Không phải secret |
| OA ID | Zalo OA dashboard | Dùng để nhận diện OA |
| OA Name | Tên OA | Giúp dễ quản lý |
| OA Secret Key/App Secret | Zalo Developers | Bảo mật |
| OA Access Token | API Explorer/OAuth | Bảo mật |
| OA Refresh Token | Nếu có | Bảo mật |
| Default lead source | Tự đặt | Khuyến nghị: Zalo OA |
| Default lead status | ID trạng thái lead | Có thể để 0 để dùng mặc định |
| Assigned staff | ID nhân sự | Có thể để 0 |
| Active | Bật | Cho phép nhận webhook |

Sau khi lưu:

- CRM sẽ không hiển thị lại token/secret đầy đủ.
- Token/secret được lưu mã hóa.
- Nếu cần thay token, nhập token mới rồi lưu lại.

## 6. Cấu Hình Webhook URL Trong Zalo

CRM hiển thị Webhook URL dạng:

```text
https://khachtot.com/kt_integration_hub/webhook/zalo_oa/{connection_public_key}
```

Trong Zalo Developers:

1. Mở cấu hình App/OA webhook.
2. Dán Webhook URL từ CRM.
3. Lưu cấu hình.
4. Bật các event cần nhận:
   - user sends message.
   - user follows OA.
   - user clicks "Nhắn tin" nếu có.

Zalo Webhook sẽ gửi HTTP request đến Webhook URL khi có tương tác từ người dùng hoặc OA.

## 7. Test Kết Nối

Cách test dễ nhất:

1. Dùng tài khoản Zalo cá nhân nhắn tin vào OA.
2. Quay lại CRM.
3. Mở:

```text
Kết nối -> Zalo OA -> Nhật ký
```

4. Kiểm tra event mới.
5. Chờ cron xử lý hoặc báo admin chạy hàng đợi thủ công.
6. Kiểm tra Lead mới trong CRM.

Kết quả mong đợi:

```text
Lead mới có tên Zalo User xxx hoặc được gắn vào lead cũ.
Nguồn: Zalo OA.
Mô tả có nội dung tin nhắn.
```

## 8. Troubleshooting Cho Tenant

| Lỗi | Nguyên nhân | Cách xử lý |
| --- | --- | --- |
| Không thấy event | Webhook URL sai hoặc chưa bật event | Kiểm tra Zalo webhook settings |
| Event có nhưng không tạo lead | Cron chưa chạy hoặc job lỗi | Báo admin/support kiểm tra Sync jobs |
| Token hết hạn | Access token expired | Lấy/cập nhật token mới |
| Tạo lead trùng | Thiếu mapping hoặc user ID đổi | Báo support kiểm tra entity link |
| 401/invalid signature | Secret sai hoặc signature config sai | Kiểm tra App Secret/OA Secret |
| Không gửi được tin nhắn | V1 chưa hỗ trợ outbound | Chờ V2 |

## 9. Bảo Mật

- Không gửi token/secret qua chat công khai.
- Không chụp màn hình có token đầy đủ.
- Nếu nghi ngờ lộ token, hãy rotate/cấp lại.
- Chỉ admin tenant mới được cấu hình Zalo OA.
- Khi báo lỗi cho support, chỉ gửi connection name, thời điểm lỗi và ảnh không chứa token/secret.

## 10. Developer/Support Appendix

Webhook route:

```text
/kt_integration_hub/webhook/zalo_oa/{connection_public_key}
```

OAuth callback route:

```text
/kt_integration_hub/oauth/zalo_oa/callback/{connection_public_key}
```

Cron command:

```bash
php index.php kt_integration_hub/cron/process_jobs
```

Tables:

```text
tblkt_integration_connections
tblkt_integration_webhook_events
tblkt_integration_sync_jobs
tblkt_integration_entity_links
tblkt_integration_logs
```

Expected simulated message payload:

```json
{
  "event_name": "user_send_text",
  "sender": {
    "id": "zalo_user_001"
  },
  "recipient": {
    "id": "oa_test_001"
  },
  "message": {
    "msg_id": "msg_001",
    "text": "Tôi cần tư vấn CRM"
  },
  "timestamp": 1780000000000
}
```

Local test command:

```bash
RAW='{"event_name":"user_send_text","sender":{"id":"zalo_user_001"},"recipient":{"id":"oa_test_001"},"message":{"msg_id":"msg_001","text":"Toi can tu van CRM"},"timestamp":1780000000000}'

curl -i -X POST "WEBHOOK_URL" \
  -H "Content-Type: application/json" \
  --data "$RAW"
```

Signature notes:

- Some Zalo webhook events may include `X-ZEvent-Signature`.
- For events using the documented formula, signature is based on:

```text
mac = sha256(appId + data + timeStamp + OAsecretKey)
```

- If signature exists and is invalid, the CRM rejects the request.
- If signature is missing, unsigned test mode may accept the event only when enabled for local/simulated testing.
- Production should not rely on unsigned webhook events.

Cloudflare/live note:

```text
Bypass cache/challenge for /kt_integration_hub/webhook/* and /kt_integration_hub/oauth/*.
```
