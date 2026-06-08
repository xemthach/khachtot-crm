# TikTok Shop Connector Tenant Setup Guide

## 1. TikTok Shop Connector là gì?

TikTok Shop Connector giúp CRM nhận sự kiện đơn hàng từ TikTok Shop và lưu vào khu vực staging để đội vận hành kiểm tra, mapping khách hàng/sản phẩm và quyết định bước xử lý tiếp theo.

V1 không tự tạo hóa đơn, không tự trừ kho, không xác nhận giao hàng và không xử lý hoàn/đổi trả.

## 2. Hiện tại hỗ trợ gì?

| Tính năng | Trạng thái |
| --- | --- |
| Dry-run/mock order webhook | Ready |
| Order staging | Ready |
| Chống trùng đơn hàng | Ready |
| Cập nhật trạng thái đơn | Ready |
| Real OAuth | Prepared / cần test credential thật |
| Real API order pull | Prepared / cần xác minh endpoint |
| Product sync | Planned |
| Inventory sync | Planned |
| Fulfillment update | Planned |
| Auto tạo hóa đơn | Không hỗ trợ V1 |

## 3. Cần chuẩn bị gì?

Tenant cần:

- Tài khoản TikTok Shop Seller/Partner.
- Ứng dụng trong TikTok Shop Partner Center.
- App Key.
- App Secret.
- Shop ID hoặc Shop Cipher.
- Quyền ủy quyền shop.
- Webhook URL từ CRM.
- OAuth callback URL từ CRM.
- Access Token / Refresh Token nếu dùng manual credential mode.

Tài liệu chính thức:

- TikTok Shop Partner Center: https://partner.tiktokshop.com/
- TikTok Shop API concepts: https://partner.tiktokshop.com/docv2/page/tts-api-concepts-overview
- TikTok Shop Seller API overview: https://partner.tiktokshop.com/docv2/page/seller-api-overview
- TikTok Shop API Testing Tool: https://partner.tiktokshop.com/api/document

## 4. Cấu hình trong CRM

Trong Tenant Admin:

```text
Kết nối -> TikTok Shop -> Tạo kết nối
```

Điền:

| Field | Cách lấy | Ghi chú |
| --- | --- | --- |
| Tên kết nối | Tự đặt | Ví dụ: TikTok Shop chính |
| Connection mode | Chọn Dry-run trước | Real API cần credential thật |
| App Key | Partner Center | Không phải secret |
| App Secret | Partner Center | Bảo mật |
| Shop ID / Shop Cipher | Partner Center | Tùy app/region |
| Shop Name | Tên shop | Dễ nhận diện |
| Region | Quốc gia/khu vực | Ví dụ VN |
| Access Token | Sau khi ủy quyền | Bảo mật |
| Refresh Token | Sau khi ủy quyền | Bảo mật |
| Sync orders | Bật | Đồng bộ vào staging |
| Dry-run mode | Bật khi test | Cho phép mock webhook |
| Active | Bật | Kết nối hoạt động |

## 5. Webhook URL

CRM hiển thị Webhook URL:

```text
https://khachtot.com/kt_integration_hub/webhook/tiktok_shop/{connection_public_key}
```

Dán URL này vào phần webhook/event callback trong TikTok Shop Partner Center khi dùng credential thật.

## 6. OAuth Callback URL

CRM hiển thị OAuth Callback URL:

```text
https://khachtot.com/kt_integration_hub/oauth/tiktok_shop/callback/{connection_public_key}
```

Dán URL này vào cấu hình app trong Partner Center nếu TikTok yêu cầu callback URL.

## 7. Test kết nối

Nên test theo thứ tự:

1. Tạo kết nối ở chế độ Dry-run / Mock.
2. Copy lệnh cURL test từ CRM.
3. Gửi mock webhook.
4. Chạy cron hoặc chờ cron xử lý.
5. Mở:

```text
Kết nối -> Đơn hàng kênh
```

Expected:

```text
Có đơn hàng TikTok Shop trong staging.
Không có hóa đơn CRM được tạo.
Không có tồn kho bị thay đổi.
```

## 8. Troubleshooting

| Lỗi | Nguyên nhân | Cách xử lý |
| --- | --- | --- |
| Không thấy đơn staging | Webhook chưa gửi hoặc cron chưa chạy | Kiểm tra Logs/Sync Queue |
| 401/signature fail | Sai cấu hình signature hoặc chưa bật dry-run | Kiểm tra mode và secret |
| Duplicate order | Payload thiếu order_id hoặc event_id | Kiểm tra payload |
| Không tạo hóa đơn | V1 chỉ staging | Đây là hành vi đúng |
| Không sync sản phẩm | Product sync chưa hỗ trợ | Chờ V2 |
| OAuth callback không connected | Token exchange chưa bật | Cần credential thật và endpoint xác minh |

## 9. Bảo mật

- Không gửi App Secret/Access Token/Refresh Token qua chat công khai.
- Không chụp màn hình có token đầy đủ.
- Nếu nghi ngờ lộ token, hãy rotate/cấp lại trong Partner Center.
- Chỉ tenant admin được cấu hình TikTok Shop.

## 10. Developer/Support Appendix

Webhook route:

```text
/kt_integration_hub/webhook/tiktok_shop/{connection_public_key}
```

OAuth callback route:

```text
/kt_integration_hub/oauth/tiktok_shop/callback/{connection_public_key}
```

Cron:

```bash
php index.php kt_integration_hub/cron/process_jobs
```

Tables:

```text
tblkt_integration_channel_orders
tblkt_integration_channel_order_items
tblkt_integration_webhook_events
tblkt_integration_sync_jobs
tblkt_integration_entity_links
tblkt_integration_logs
```

Production checklist:

- Verify TikTok Shop API endpoint in Partner Center.
- Verify request signing algorithm.
- Verify webhook signature headers.
- Disable unsigned dry-run mode.
- Confirm Cloudflare bypass for webhook path.

