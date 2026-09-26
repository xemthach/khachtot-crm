# Changelog

## 0.10.4-rc.1 — 2026-09-27 (chưa phát hành)

- Thêm `VERSION` cho candidate sản phẩm, độc lập schema Perfex/version module.
- Smoke HTTP trả nonzero khi lỗi HTTP/kết nối; giữ xác thực TLS, yêu cầu URL rõ ràng.
- Preflight CLI chỉ đọc, chạy trước setup; kiểm tra PHP/extensions/vendor autoload.
- Setup không cấp quyền ghi toàn cây tenant bootstrap PHP hoặc che lỗi chmod;
  runtime directory/file dùng 750/640, cần owner PHP-FPM phù hợp.
- Diagnostic không tự in log có thể chứa thông tin nhạy cảm.
- Test loopback HTTP và setup chạy lại trên cây tạm; hướng dẫn deployment mới.
- Follow-up clean clone: thêm `setup-live.sh --fresh-install` không tạo config
  trước installer; từ chối fresh mode khi deployment đã có config.
- Guide bổ sung cài dependency backup từ lock và phân biệt install/upgrade;
  ghi nhận build RC thất bại, không coi asset local là artifact đã được release.
- Không migrate DB, bật provider, sửa schema hoặc source Salesrep DMS.

Blocker: installer SQL không nằm trong HEAD Git; worktree và checkout chưa tương
đương; cần chứng minh install/upgrade và runtime nghiệp vụ trên staging trước tag/live.
PHP 8.3 lint phát hiện lỗi framework `system/libraries/Profiler.php:108`; chưa sửa
vendor, cần bản tương thích được cấp phép trước release.

## Mốc từ Git

HEAD gốc `c483dd279aac1128b7bdb414f78aeaa360e7e9ef` có tag
`v0.10.2-tiktok-shop-dry-run-staging`; lịch sử cũng có
`v0.10.3-zalo-webhook-probe`. Chọn candidate 0.10.4-rc.1 để không đánh số lùi/ghi đè
tag tồn tại. Giữ nguyên lịch sử và các version core/module/vendor.
