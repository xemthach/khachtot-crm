# KT MatBao Invoice - Phase 2 E2E QA Checklist

## 1) Preconditions

- Landlord đã cấu hình:
  - HDDT account active
  - CA/HSM account active
  - `webhook_secret` khác rỗng
- Tenant có entitlement:
  - `matbao_invoice.enabled=ON`
  - `matbao_invoice.issue_invoice=ON`
  - `matbao_ca.enabled=ON` (nếu test CA mode)
  - `matbao_ca.sign_xml=ON` (nếu test CA mode)
- Tenant có ít nhất 1 invoice Perfex hợp lệ có item + tax.

## 2) Mode A - HDDT sign-invoice

1. Tenant settings: `signing_mode = hddt_sign_invoice`.
2. Tại tenant invoice panel, bấm Issue.
3. Kỳ vọng:
   - Record tạo thành công trong `kt_matbao_invoice_records`.
   - `local_status` chuyển `issued` (hoặc `signed` nếu provider trả signed ngay).
   - Có `ma_so_hdon`, `ma_tra_cuu`.
4. Bấm Sync Status + Download PDF/XML.
5. Kỳ vọng:
   - Sync không lỗi identity mismatch.
   - Download trả file hợp lệ, không lẫn tenant khác.

## 3) Mode B - Get XML then CA sign

1. Tenant settings: `signing_mode = get_xml_then_ca_sign`.
2. Issue cùng 1 invoice mới.
3. Kỳ vọng:
   - Create draft HDDT thành công (`LoaiHDon=0` branch).
   - Gọi `get-xml-not-sign` thành công.
   - Gọi CA `signature-xml` thành công.
   - Gọi HDDT `sign-xml` thành công.
   - Record có `ca_document_id` (nếu provider trả về).
   - `local_status = signed`.

## 4) Entitlement Deny Checks

1. Tắt `matbao_ca.enabled`, giữ mode CA.
2. Issue invoice.
3. Kỳ vọng: backend block với cảnh báo plan denied.
4. Bật lại `matbao_ca.enabled`, tắt `matbao_ca.sign_xml`.
5. Kỳ vọng: tiếp tục bị block đúng lý do.

## 5) Webhook Security + Idempotency

### Invoice webhook
1. POST `/admin/kt_matbao_invoice/webhook/invoice` không kèm secret header.
2. Kỳ vọng: `401` nếu `webhook_secret` đã cấu hình.
3. POST payload hợp lệ kèm `X-KT-MatBao-Secret`.
4. Kỳ vọng: update đúng record theo `MaSoHDon/MaTraCuu/InvID`.

### Signing webhook
1. POST `/admin/kt_matbao_invoice/webhook/signing` kèm `DocumentId`, `DocumentStatus`.
2. Kỳ vọng:
   - Match record qua `ca_document_id` ưu tiên.
   - `local_status` map đúng:
     - `SIGNED/COMPLETED/SUCCESS` -> `signed`
     - `FAILED/ERROR/REJECTED` -> `failed`
3. Gửi lại đúng payload lần 2.
4. Kỳ vọng: API trả `duplicate=true`, không update lặp.

## 6) Data Isolation

1. Tenant A thử download record của tenant B.
2. Kỳ vọng: bị chặn (404/denied), không lộ file.
3. Kiểm tra logs:
   - Không lưu password/token rõ ràng.
   - Request/response có mask token.

## 7) Pass/Fail Summary

- Total: ___
- Passed: ___
- Failed: ___
- Critical failed: ___
- Tested by: ___
- Date: ___

