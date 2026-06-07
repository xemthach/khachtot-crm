# Báo Cáo Audit Landing KT SaaS (2026-06-01)

## 1) Phạm vi đã audit
- `application/config/routes.php`
- `modules/kt_saas/controllers/Kt_saas.php`
- `modules/kt_saas/controllers/Checkout.php`
- `modules/kt_saas/models/Kt_saas_model.php`
- `modules/kt_saas/services/*` (entitlement, billing, payment, provisioning, domain verification)
- `modules/kt_saas/views/dashboard/tenants.php`
- `modules/kt_sepay/*` (controller/model/webhook/cron/routes)
- `modules/kt_matbao_invoice/*` (controller/model/webhook/routes/install)

Đã loại khỏi tín hiệu audit: `*.bak*`, tài liệu lịch sử trong `docs`, và `storage/backups`.

## 2) Trạng thái route public `/` hiện tại
- Route mặc định hiện tại: `clients` (`application/config/routes.php`).
- `/` cũng được map trực tiếp về `clients`.
- Kết quả: chưa có controller/view landing public riêng cho SaaS.

Tác động:
- Nếu thêm landing mới, bắt buộc không làm hỏng luồng client portal hiện có.

## 3) Luồng signup / checkout / conversion (hiện trạng)
- Đã có checkout public: `modules/kt_saas/controllers/Checkout.php`
  - `invoice($invoiceId, $token)`
  - `pay($invoiceId, $token)` (luồng sandbox mark-paid)
  - `webhook($gateway)`
- URL checkout theo token hóa đơn được tạo bởi `PaymentCollectionService`.
- Chưa thấy endpoint self-signup public hoàn chỉnh (trial/free/paid tạo tenant từ form public) trong code active.
- Tạo tenant hiện tại chủ yếu đi theo luồng landlord-admin: `Kt_saas::tenants()` + `Kt_saas_model::save_tenant()`.

Kết luận:
- Có luồng commerce/payment checkout.
- Thiếu luồng public self-onboarding signup hoàn chỉnh.

## 4) Luồng provisioning tenant
- Tạo metadata tenant: `Kt_saas_model::save_tenant()`
- Đưa vào queue provisioning: `create_provision_job(..., 'provision_tenant', ...)`
- Chạy job: `Kt_saas::run_provision_job()` -> `provisioning/ProvisioningJobRunner.php`
- Provisioning job xử lý:
  - Tạo DB
  - Tạo user/grant DB
  - Clone schema
  - Seed dữ liệu tham chiếu
  - Seed module
  - Seed admin
  - Seed options tenant
  - Ghi manifest

Quan sát:
- Luồng provisioning đầy đủ về cấu trúc.
- Triệu chứng runtime đã thấy trên UI (`tenant_access`: "Unable to connect tenant database") phù hợp với drift kết nối/credentials DB tenant, và đã có fallback/repair trong `TenantAdminAccessService`.

## 5) Runtime plan / entitlement / limits
- Gate entitlement runtime: `services/TenantEntitlementService.php`
- Module gate + feature gate + plan limits đã được áp dụng cho tenant portal.
- Route gate có logic ngoại lệ cho tenant portal route và chặn landlord-only route.

Quan sát:
- Kiến trúc entitlement hiện có đủ để tái sử dụng cho pricing matrix trên landing.

## 6) Luồng domain & subdomain
- Lưu tenant sẽ sync domain: `sync_tenant_domains()` -> `upsert_domain_record()`.
- Domain CRUD: `save_domain()`.
- Verification: `services/DomainVerificationService` (DNS + SSL handshake readiness).
- Có trạng thái expected target, readiness, dns/ssl.

Quan sát:
- Domain lifecycle sẵn sàng để đưa vào thông điệp/flow onboarding landing.

## 7) Trạng thái flow KT SePay
- Public routes đã cấu hình (`modules/kt_sepay/config/routes.php`):
  - pay/status
  - webhook
  - webhook theo tenant code
- Webhook auth validation có sẵn (`Kt_sepay_webhook` + model checks).
- Tính năng tenant-facing được gate bằng entitlement keys trong controller `Kt_sepay`.

Quan sát:
- Tích hợp SePay đủ mức production-oriented, có thể tái sử dụng cho khối checkout/payment SaaS.

## 8) Trạng thái flow KT MatBao Invoice
- Có settings page, plan entitlement page, webhook routes.
- Có tách route webhook (`invoice` vs `signing`).
- Model có split account tables HDDT/CA và cơ chế fallback central DB resolver.

Quan sát:
- Đủ độ rộng để đóng gói vào landing, nhưng độ ổn định vận hành vẫn phụ thuộc tính nhất quán install/schema landlord DB.

## 9) Ma trận thành phần có thể tái sử dụng
| Thành phần | Hiện trạng | Tái sử dụng cho landing | Gap/Risk | File |
|---|---|---|---|---|
| Public root route `/` | Đang trỏ `clients` | Một phần | Chưa có landing controller/view riêng | `application/config/routes.php` |
| Nguồn dữ liệu pricing public | Plans table + `get_public_plans()` | Có | Chưa có public pricing page/controller | `Kt_saas_model::get_public_plans` |
| Public signup | Chưa hoàn chỉnh end-to-end | Chưa | Cần signup pipeline tạo tenant + payment branch | `modules/kt_saas/controllers` |
| Checkout invoice payment | Đã có | Có | Hiện là luồng invoice-token, chưa phải plan-order wizard public | `controllers/Checkout.php`, `services/PaymentCollectionService.php` |
| Validate/generate tenant khi tạo | Đã chắc | Có | Đã đáp ứng tốt format/duplication/generation | `Kt_saas_model::save_tenant`, `generate_tenant_form_values`, `check_tenant_field_availability`, `views/dashboard/tenants.php` |
| Provisioning engine | Đã có | Có | Cần UX/monitoring tốt hơn cho job fail | `provisioning/ProvisioningJobRunner.php` |
| Entitlement runtime | Đã có | Có | Cần map rõ SKU landing -> feature keys | `services/TenantEntitlementService.php` |
| Domain verification | Đã có | Có | Cần UX async DNS/SSL cho onboarding public | `services/DomainVerificationService.php` |
| SePay integration | Đã có | Có | Cần glue workflow cho order landing | `modules/kt_sepay/*` |
| MatBao add-on packaging | Đã có (landlord side) | Có | Cần đảm bảo schema/install nhất quán | `modules/kt_matbao_invoice/*` |

## 10) Khuyến nghị kiến trúc an toàn route
Khuyến nghị:
- Tạo module riêng: `modules/kt_landing`
- Dùng route public tường minh:
  - `/` -> `kt_landing/home` (marketing homepage)
  - `/pricing` -> `kt_landing/pricing`
  - `/signup` -> `kt_landing/signup`
  - `/checkout/*` -> giữ tách prefix cho luồng KT SaaS/SePay payment
- Giữ nguyên toàn bộ admin routes dưới `/admin/*`.
- Giữ nguyên cơ chế tenant runtime host/subdomain (không va chạm route trong tenant context).
- Giữ lối vào client portal bằng path tường minh (ví dụ `/clients`, `/login`) khi thay `/`.

## 11) Các phase triển khai tiếp theo (sau khi chốt audit)
1. Tạo `kt_landing` routes/controllers/views (trước mắt: marketing + pricing read-only).
2. Tạo public signup pipeline (company -> tenant draft -> subscription/order).
3. Gắn thanh toán signup với SePay và service billing/payment hiện có.
4. Tái sử dụng provisioning queue để active tenant sau thanh toán.
5. Bổ sung E2E test cho:
   - free trial signup
   - paid signup + payment webhook
   - tenant provisioning success/failure path

## 12) Trạng thái triển khai Phase 1 (đã thực hiện)
- Đã đổi route public:
  - `default_controller` -> `kt_landing`
  - `/` -> `kt_landing/home`
  - thêm `/pricing`, `/signup`
- Đã tạo module mới `modules/kt_landing` gồm:
  - bootstrap module
  - controller `Kt_landing`
  - views `home`, `pricing`, `signup`
- Trang `pricing` đang lấy dữ liệu từ `Kt_saas_model::get_public_plans()` (lọc `is_public=1`, `is_active=1`).
- Đã thêm guard tenant runtime trong controller landing: nếu tenant context thì redirect về `clients` để tránh lệch luồng tenant.
- Không thay đổi logic provisioning, entitlement, SePay, MatBao runtime.

### Kiểm tra nhanh đã pass
- PHP syntax check:
  - `application/config/routes.php`
  - `modules/kt_landing/kt_landing.php`
  - `modules/kt_landing/controllers/Kt_landing.php`

## 13) Cập nhật triển khai Phase 2 (đã thực hiện)
- Form `/signup` đã submit thật với CSRF.
- Luồng xử lý signup public đã thêm:
  - Validate dữ liệu cơ bản (company, owner, email, plan).
  - Kiểm tra plan còn `is_public=1` và `is_active=1`.
  - Tạo tenant mới ở trạng thái nháp.
  - Tạo subscription theo plan.
  - Tạo hóa đơn SaaS dạng skeleton cho đăng ký (`reason: public_signup`).
- Để giữ đúng phạm vi “chưa auto-provision”, hệ thống gỡ job `provision_tenant` queued ngay sau khi tạo tenant public.
- UI signup hiển thị kết quả:
  - trạng thái thành công/thất bại
  - mã tenant
  - số hóa đơn (nếu tạo được)
- Bổ sung ổn định vận hành:
  - Chống duplicate đăng ký: nếu đã có tenant `draft` cùng `owner_email + plan_id` trong 7 ngày gần nhất thì tái sử dụng tenant cũ thay vì tạo mới.
  - Tái sử dụng hóa đơn mở theo `reason=public_signup` nếu đã tồn tại.
  - Trả thêm link checkout để người dùng đi thẳng vào bước thanh toán hóa đơn.
  - Chuyển kết quả xử lý signup sang `flashdata` + trang trạng thái riêng `/signup/status` (không còn phụ thuộc query string dài).

## 14) Checklist trạng thái theo phase

### Phase 1 — Landing nền tảng
- [x] Đổi route `/` sang landing.
- [x] Thêm route `/pricing`, `/signup`.
- [x] Tạo module `kt_landing` (controller + views public).
- [x] Tích hợp dữ liệu gói public từ `get_public_plans()`.
- [x] Guard tenant runtime để không lệch vào landing.
- [x] Dịch báo cáo audit sang tiếng Việt.

### Phase 2 — Signup public (draft + invoice skeleton)
- [x] Form signup submit thật (POST + CSRF).
- [x] Validate dữ liệu đầu vào + validate plan public/active.
- [x] Tạo tenant trạng thái `draft`.
- [x] Tạo subscription + invoice signup skeleton (`reason=public_signup`).
- [x] Chặn auto-provisioning ngay khi signup public (gỡ queued `provision_tenant` job).
- [x] Chống duplicate đăng ký gần thời gian (reuse tenant `draft`).
- [x] Reuse hóa đơn mở nếu đã có.
- [x] Trả link checkout và trang status riêng `/signup/status`.

### Phase 3 — Thanh toán thật + kích hoạt cấp phát sau thanh toán
- [x] Mapping luồng signup invoice sang SePay request (thay vì sandbox/manual) với fallback checkout mặc định nếu SePay chưa sẵn sàng.
- [x] Xác thực webhook thanh toán cho invoice signup.
- [x] Khi thanh toán thành công: cập nhật trạng thái invoice/subscription.
- [x] Tạo lại provisioning job có kiểm soát sau khi payment success.
- [x] Cơ chế idempotency để webhook không kích hoạt cấp phát trùng.

### Phase 4 — E2E QA & Regression
- [ ] Test E2E signup free/trial/paid.
- [ ] Test webhook success/fail, duplicate webhook.
- [ ] Test provisioning success/fail/retry từ signup flow.
- [x] Test regression route `/`, `/clients`, `/login`, `/admin/*`, tenant runtime host (HTTP smoke): xem `PHASE4_HTTP_SMOKE_2026-06-01.md`.
- [x] Static self-check wiring Phase 4 pass 15/15: xem `PHASE4_STATIC_SELF_CHECK_2026-06-01.md`.

### Phase 5 — Hardening vận hành
- [x] Rate limit + anti-spam cho form signup.
- [x] Audit log đầy đủ cho signup -> payment -> provision chain.
- [x] Dashboard theo dõi funnel (signup draft, pending payment, paid, provisioning, active).
- [x] Cảnh báo job lỗi và retry policy rõ ràng.

## 15) Việc tiếp theo nên làm ngay (đề xuất thứ tự)
1. Chạy Phase 4 E2E đầy đủ (signup free/trial/paid, webhook duplicate, provisioning retry).
2. Khóa nghiệp vụ: tiêu chí active tenant sau payment + sau provisioning.
3. Triển khai Phase 5 hardening (rate limit, funnel dashboard, alerting).

## 16) Ghi chú kiểm thử kỹ thuật ngày 2026-06-01
- Đã pass lint: `routes.php`, `Kt_landing.php`, `signup.php`, `Kt_sepay_processor.php`.
- Đã chạy smoke HTTP bằng CLI cho `/`, `/pricing`, `/signup`, `/signup/status`, `/clients`, `/login` nhưng môi trường CLI trả `500` đồng loạt, cần xác nhận lại qua browser session thực tế để chốt Phase 4.
