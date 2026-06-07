# KT MatBao Invoice Audit Report (2026-06-01)

## 1) Executive Summary

- Module hiện tại **mới triển khai HDDT API cơ bản**, chưa triển khai thực tế luồng MatBaoCA/HSM.
- Có lỗi kiến trúc nghiêm trọng: đang dùng **1 bảng settings chung** cho cả HDDT + CA (`invoice_base_url`, `sign_base_url`, `mst`, `username`, `password_encrypted`, `access_token_encrypted`).
- Flow ký tập trung (CA/HSM) thực tế **chưa hoạt động** vì `Matbao_sign_client` chưa implement.
- Webhook có tách route invoice/signing, nhưng parser/storage chưa tách đúng nghiệp vụ CA, chưa có xác thực nguồn webhook.

Kết luận: cần tách kiến trúc thành 2 account độc lập (HDDT vs CA), tách token, tách flow, tách trạng thái ký; hiện trạng chưa đạt yêu cầu vận hành production multi-tenant cho CA/HSM.

## 2) API Group Separation (Doc vs Code)

| Nhóm API | Endpoint chính | Credential | Token | Hiện trạng module |
|---|---|---|---|---|
| HDDT | `/api/auth/login`, `/api/invoice/*` | `MST`, `TDNhap`, `MKhau` | HDDT token | Đã có client và đang dùng |
| CA/HSM | `/auth/token-matbaoca`, `/signing-matbaoca/*` | `taxcode`, `username`, `password` | CA token | Chưa có implementation thực thi |

Nguồn docs đã đọc:
- `HoaDonBanRa/API HDDT - PROXY.postman_collection.json`
- `HopDongDienTu (Chữ ký số Tập Trung)/API-MatBaoCA.postman_collection.json`
- `HopDongDienTu (Chữ ký số Tập Trung)/HDDT_SIGNNINGAPI_CreateDocV2.postman_collection.json`

## 3) Code Audit Findings (Critical First)

| File | Function/Class | Vấn đề | Mức độ | Hướng xử lý |
|---|---|---|---|---|
| `models/Kt_matbao_invoice_model.php` | `save_settings`, schema `kt_matbao_invoice_settings` | Gộp chung HDDT + CA credential/token trong 1 record | Critical | Tách bảng HDDT account và CA account |
| `libraries/Matbao_sign_client.php` | toàn bộ class | Toàn bộ method trả `Not implemented yet` | Critical | Implement login/getcert/sign XML/PDF/hash + token lifecycle |
| `controllers/Kt_matbao_invoice_tenant.php` | `createOrIssue` | Chỉ dùng HDDT `create-invoice`, không có flow `get-xml-not-sign -> CA sign -> sign-xml` | Critical | Thêm `signing_mode` và branch flow rõ ràng |
| `models/Kt_matbao_invoice_model.php` | `resolve_tenant_effective_settings` | Chỉ resolve 1 settings scope cho HDDT, không resolve account CA riêng | High | Resolve riêng: `effective_hddt_account`, `effective_ca_account` |
| `controllers/Kt_matbao_invoice_webhook.php` | `handleWebhook` | Có route tách nhưng parser/status update dùng chung logic HDDT | High | Tách parser HDDT vs CA, bảng log/status riêng hoặc field phân biệt đầy đủ |
| `controllers/Kt_matbao_invoice_webhook.php` | `handleWebhook` | Không verify secret/signature nguồn webhook | High | Verify header secret/HMAC + reject source không hợp lệ |
| `models/Kt_matbao_invoice_model.php` | `log_api` | Lưu raw response có thể chứa token login | High | Mask token trong response trước khi log |
| `views/admin/settings.php`, `views/tenant/settings.php` | form settings | UI chỉ có 1 cụm `mst/username/password` dùng chung | High | Tách UI: “HDDT Settings” và “CA/HSM Settings” |
| `libraries/Matbao_invoice_mapper.php` | class rỗng | Mapper chưa tách thành layer độc lập, mapping nằm trong controller | Medium | Dời mapping + validation vào service chuyên dụng |

## 4) Database Audit

### Hiện trạng
- Có bảng: `kt_matbao_invoice_settings`, `kt_matbao_invoice_records`, `kt_matbao_invoice_logs`, `kt_matbao_invoice_webhook_logs`, cùng các bảng add-on/provisioning.
- **Chưa có bảng CA account riêng**.
- **Chưa có trường liên kết account_id riêng** cho HDDT/CA trong record.

### Gap chính
- Thiếu:
  - `hddt_accounts` tách biệt
  - `ca_accounts` tách biệt
  - liên kết `hddt_account_id`, `ca_account_id`, `signing_mode` rõ ràng trong record

## 5) Tenant/Landlord Isolation

- Điểm tốt:
  - `tenant_id` được gắn vào record và check ở tenant download/sync.
  - Có `resolve_tenant_effective_settings` theo scope tenant/landlord.
- Điểm thiếu:
  - Isolation mới áp dụng cho HDDT config; CA/HSM chưa có isolation vì chưa có account model riêng.

## 6) Entitlement / Add-on Audit

- Đang có feature keys: `matbao_invoice.enabled`, `tenant_config`, `issue_invoice`, `download_*`, quota...
- Có flow add-on `addon_einvoice` và `addon_hsm` + provisioning jobs.
- Gap:
  - Chưa enforce thực tế “HSM active bắt buộc” cho flow ký CA vì flow CA chưa có.
  - Chưa có entitlement nhóm `matbao_ca.*` độc lập.

## 7) Webhook/Status Audit

- Route:
  - `/kt_matbao_invoice/webhook/invoice`
  - `/kt_matbao_invoice/webhook/signing`
- Vấn đề:
  - Chưa có idempotency key rõ ràng.
  - Chưa xác thực nguồn webhook.
  - Signing webhook chưa update trạng thái tài liệu ký độc lập (đang map vào invoice record kiểu HDDT).

## 8) Security Audit

- Có encrypt password/token bằng helper (`app_encrypt` fallback base64).
- Rủi ro:
  - fallback base64 không đủ an toàn nếu deploy thiếu `app_encrypt`.
  - raw API log có nguy cơ lộ token response.
  - webhook không verify nguồn.

## 9) Required Refactor Direction

1. Tách cấu hình và token:
   - HDDT account table
   - CA/HSM account table
2. Tách luồng nghiệp vụ:
   - Flow A/B (HDDT-only)
   - Flow C (get XML -> CA sign -> sign-xml)
3. Tách entitlement:
   - `matbao_invoice.*`
   - `matbao_ca.*`
4. Tách webhook parser và status model HDDT vs CA.
5. Hardening:
   - webhook auth
   - token masking trong log
   - block downgrade về insecure base64 mode ở production.

## 10) Current Status vs Prompt Requirement

- Đã audit code thật: **Yes**
- Đã đối chiếu API docs thật: **Yes**
- Đã tách rõ HDDT vs CA trong kết luận: **Yes**
- Chưa code trước báo cáo: **Yes**

