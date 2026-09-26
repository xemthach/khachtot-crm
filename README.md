# Khách Tốt CRM

CRM nền Perfex/CodeIgniter 3 với các module Khách Tốt cho SaaS, trang giới thiệu,
kho, thanh toán và tích hợp. Salesrep DMS là sản phẩm riêng; repository này chỉ
chứa phía CRM của kết nối DMS.

## Trạng thái

Candidate **0.10.4-rc.1** (nguồn: `VERSION`) chưa đạt release gate, chưa có tag mới.
Core schema `341` (Perfex 3.4.1), version module và package frontend độc lập với
version sản phẩm. Không thay số schema/vendor để đánh dấu release CRM.

Audit 2026-09-27 kiểm chứng script triển khai bằng test loopback và filesystem tạm.
Chưa chứng nhận production, provider thật hoặc toàn bộ nghiệp vụ. Worktree local
có MISA và thành phần tenant chưa được track; clone GitHub không tự chứa các thay
đổi này. Xem inventory và giới hạn trong báo cáo audit.
Lint phát hiện `system/libraries/Profiler.php:108` không tương thích PHP 8.3;
giữ nguyên framework/vendor, cần bản tương thích được phép trước release.

## Yêu cầu từ source

- PHP >=8.1; môi trường audit: PHP CLI 8.3.16 Windows x64, Node 24.14.0.
- MySQL/MariaDB qua mysqli; phiên bản DB live chưa được xác nhận.
- Chạy `php scripts/deploy-preflight.php` để kiểm tra extensions và autoload.
- Vendor PHP được bundle trong application và modules; không có Composer ở root.
- Asset dùng Laravel Mix (`npm run build`); build Linux cần kiểm chứng trên staging.
- Document root là root repository có `index.php`, không phải `public/`.

## Tài liệu và kiểm tra

- [Clone → staging → live](docs/DEPLOYMENT_RELEASE.md)
- [Changelog](CHANGELOG.md)
- [Audit](docs/reports/CRM_RELEASE_AUDIT_20260927.md)
- [Inventory/evidence](docs/reports/CRM_RELEASE_AUDIT_20260927_EVIDENCE.json)

```sh
php scripts/deploy-preflight.php
node tests/release/deployment-smoke.test.cjs
node tests/release/setup-live.test.cjs
```

Giữ config, keys, DB dumps, sessions và upload ngoài Git. Quyền GitHub không thay
thế license Perfex/module; không sửa hoặc bypass cơ chế license/vendor.
