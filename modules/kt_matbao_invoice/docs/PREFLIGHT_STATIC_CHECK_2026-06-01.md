# KT MatBao Invoice - Preflight Static Check (2026-06-01)

## Kết quả tổng quan

- Trạng thái: **PASS (static checks)**
- Phạm vi: kiểm tra tĩnh source code + route + syntax lint.
- Lưu ý: chưa bao gồm kết quả runtime call provider thực tế.

## Checklist pass/fail

| Hạng mục | Kết quả | Bằng chứng |
|---|---|---|
| Tách flow theo `signing_mode` | PASS | `Kt_matbao_invoice_tenant.php` có branch `hddt_sign_invoice` và `get_xml_then_ca_sign` |
| Enforce entitlement `matbao_ca.enabled` | PASS | Backend deny trong `createOrIssue` |
| Enforce entitlement `matbao_ca.sign_xml` | PASS | Backend deny trong `createOrIssue` |
| HDDT endpoint `sign-invoice` | PASS | `Matbao_invoice_client::signInvoice()` |
| HDDT endpoint `get-xml-not-sign` | PASS | `Matbao_invoice_client::getXmlNotSign()` |
| HDDT endpoint `sign-xml` | PASS | `Matbao_invoice_client::signXml()` |
| CA endpoint login token | PASS | `Matbao_sign_client::login()` -> `/auth/token-matbaoca` |
| CA endpoint ký XML | PASS | `Matbao_sign_client::signatureXml()` |
| Webhook secret guard | PASS | `Kt_matbao_invoice_webhook` check `X-KT-MatBao-Secret`/`X-Webhook-Secret` |
| Webhook idempotency | PASS | `Kt_matbao_invoice_model::is_duplicate_webhook()` |
| Signing webhook parser riêng | PASS | `update_record_status_from_signing_webhook()` |
| Lưu `DocumentId` | PASS | Cột `ca_document_id` + mapping webhook |
| Nâng schema tự động | PASS | `install.php` thêm ALTER cho cột mới |
| Route webhook invoice/signing tách riêng | PASS | `config/routes.php` |
| Syntax PHP | PASS | `php -l` toàn bộ file chính đều OK |

## Ghi chú vận hành

1. Set `webhook_secret` tại admin settings trước khi mở callback public.
2. Chạy migration bằng cách vào module admin để `kt_matbao_invoice_maybe_upgrade_schema()` thực thi.
3. Sau preflight này cần chạy manual E2E theo:
   - `PHASE2_E2E_QA_CHECKLIST.md`

