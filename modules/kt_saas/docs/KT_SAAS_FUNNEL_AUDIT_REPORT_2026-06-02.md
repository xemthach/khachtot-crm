# KT SAAS FUNNEL AUDIT REPORT

Scope:
- Audit toàn bộ funnel từ Landing -> Pricing -> Signup -> Checkout -> Payment -> Provisioning -> Activation -> Emails -> Tenant Isolation.
- Chỉ audit. Không sửa code.
- Không refactor mail engine, provider, billing, provisioning, hay UI.

## 1. Landing

### Hiện trạng
- Route public landing đã được map vào `Kt_landing`:
  - `/` -> `kt_landing/home`
  - `/pricing` -> `kt_landing/pricing`
  - `/signup` -> `kt_landing/signup`
- `home()` và `pricing()` đều tự redirect sang `clients` nếu request đang ở tenant runtime.
- Landing data được build từ CMS content + public plans + plan overrides.
- Landing template có đủ hero, trust, why-change, use case, journey, marketplace, FAQ, CTA.

### Đánh giá
| Hạng mục | Nhận định | Rủi ro |
|---|---|---|
| Public route control | pass | tenant runtime phải resolve đúng để tránh bleed brand |
| Content structure | pass | section nhiều, nhưng vẫn phụ thuộc dữ liệu CMS |
| Product marketing | pass | đã có product visual/showcase, chưa phải vấn đề chính |
| Context isolation | partial | một số option vẫn đọc trực tiếp từ landlord/global |

### Kết luận
- Landing đã đủ cấu trúc để bán SaaS.
- Vấn đề chính không nằm ở layout, mà ở **context resolution** và **funnel continuity**.

## 2. Pricing

### Hiện trạng
- Pricing page lấy data từ `Kt_saas_model::get_public_plans()`.
- Landing có override layer riêng:
  - CTA text
  - CTA URL
  - featured badge
  - marketing title/subtitle/description
  - sort order
- View pricing render các limit quan trọng:
  - staff
  - clients
  - storage
  - invoices
  - projects
  - warehouses
  - API requests
  - automations
  - trial days
  - modules / extra features

### Đánh giá
| Hạng mục | Nhận định | Rủi ro |
|---|---|---|
| Source of truth | pass | public pricing không tự tạo dữ liệu riêng |
| Override layer | pass | chỉ marketing layer được override |
| Sales usefulness | pass | đã có limits + modules, đủ để bán |
| Duplication risk | low | nếu landlord đổi plan thì landing cập nhật theo source |

### Kết luận
- Pricing kiến trúc đúng hướng: **KT SAAS plans là source of truth**.
- Landing chỉ là lớp trình bày và marketing override.

## 3. Signup

### Hiện trạng
- `Kt_landing::signup()` nhận submit form public.
- Luồng submit có:
  - validation company/owner/email/plan/subdomain
  - anti-honeypot
  - rate limiting
  - re-use draft tenant nếu có
  - create tenant nếu chưa có
- Tenant mới tạo ở signup **được giữ draft** và provisioning được queue.
- `signup_progress()` và `signup_status()` có sẵn để theo dõi tiến trình.

### Đánh giá
| Hạng mục | Nhận định | Rủi ro |
|---|---|---|---|
| Lead capture | pass | form public có đủ dữ liệu đầu vào |
| Immediate activation | fail by design | signup không activate ngay, phải chờ provisioning |
| Idempotency | partial | có draft reuse, nhưng vẫn phụ thuộc các nhánh queue/provision |
| UX continuity | partial | người dùng cần theo dõi progress sau submit |

### Kết luận
- Signup hiện là **entry point vào provisioning**, không phải self-activate flow.
- Đây là điểm thiết kế quan trọng cần được hiểu đúng khi audit checkout/payment.

## 4. Checkout

### Hiện trạng
- `modules/kt_saas/controllers/Checkout.php` là checkout runtime controller.
- `invoice()` resolve invoice by token.
- `pay()` chỉ hỗ trợ manual mode.
- `webhook()`:
  - verify signature
  - parse payload
  - call `PaymentCollectionService::processWebhook()`
  - failure sẽ bắn `webhook_failed`
- SePay checkout URL được build lazily nếu module active.

### Đánh giá
| Hạng mục | Nhận định | Rủi ro |
|---|---|---|---|
| Invoice landing | pass | có token-based checkout access |
| Manual pay path | pass | chỉ mở khi manual mode bật |
| Webhook safety | pass | có verify signature và error dispatch |
| Replay protection | partial | replay safety còn phụ thuộc service-level guard |

### Kết luận
- Checkout không nằm trong một module độc lập; nó là phần runtime của KT SAAS.
- Luồng webhook là điểm nhạy nhất cho replay/fail/duplicate.

## 5. Payment

### Hiện trạng
- `PaymentCollectionService::processWebhook()` điều phối payment state.
- `BillingEngineService::markInvoicePaid()` cập nhật invoice paid, re-activate subscription, và bắn `payment_success`.
- `PaymentCollectionService` cũng có branch xử lý payment failure và bắn `payment_failed`.
- SePay processor xử lý matched/unmatched transaction, và unmatched có operational alert.

### Đánh giá
| Hạng mục | Nhận định | Rủi ro |
|---|---|---|---|
| Success path | pass | `payment_success` đi qua billing engine |
| Failure path | pass | `payment_failed` đi qua payment collection |
| Replay handling | partial | guard đã có, nhưng webhook replay vẫn cần đúng dedupe key |
| Split ownership | partial | logic payment trải qua nhiều lớp, cần kiểm soát chặt context |

### Kết luận
- Payment flow hoạt động thật, nhưng bị phân mảnh qua nhiều service.
- Rủi ro chính là replay, callback failure, và context mismatch.

## 6. Provisioning

### Hiện trạng
- `Kt_saas_model::save_tenant()` khi tạo tenant sẽ:
  - sanitize/generate tenant code, subdomain, db name, db user
  - validate uniqueness
  - tạo provisioning job `provision_tenant`
  - tạo subscription trial nếu có
- `ProvisioningJobRunner` thực thi:
  - create DB
  - clone schema
  - seed data
  - seed admin
  - prepare storage
  - activate modules
  - send welcome email
- Provisioning failure được bắt và rollback theo job runner.

### Đánh giá
| Hạng mục | Nhận định | Rủi ro |
|---|---|---|---|
| DB bootstrap | pass | provisioning real, không phải stub |
| Job-based flow | pass | có queue/job execution rõ ràng |
| Failure handling | pass | có rollback path |
| Activation coupling | partial | activation phụ thuộc state machine và job completion |

### Kết luận
- Provisioning là xương sống của funnel.
- Đây là bước quyết định tenant có thực sự usable hay chỉ mới được tạo bản ghi.

## 7. Activation

### Hiện trạng
- Activation không diễn ra ngay ở signup.
- Tenant active sau các bước:
  - provisioning completed
  - subscription/payment state hợp lệ
  - module registry/runtime sync
- `save_tenant()` và provisioning runner đều có các nhánh status/provisioning status.

### Đánh giá
| Hạng mục | Nhận định | Rủi ro |
|---|---|---|---|
| Activation source | pass | có state machine thực |
| Timing clarity | partial | activation phụ thuộc nhiều event nối tiếp |
| State consistency | partial | nếu một bước fail, tenant có thể ở trạng thái trung gian |

### Kết luận
- Activation là kết quả của provisioning + billing + runtime sync, không phải action đơn lẻ.

## 8. Emails

### Hiện trạng
- Email runtime trong KT SAAS đi qua `TenantEmailProviderService`.
- `payment_success`, `payment_failed`, `provisioning_completed`, `provisioning_failed`, `tenant_welcome` đã có trong chain.
- Lifecycle operational templates cũng đã có theo các phase trước.
- Logs ghi vào `tblkt_saas_email_logs`.

### Đánh giá
| Hạng mục | Nhận định | Rủi ro |
|---|---|---|---|
| Provider path | pass | resolver là source of truth |
| Log coverage | pass | có log chi tiết, không lộ secret |
| Branding context | pass | landlord context cho billing/ops mail |
| Customer vs ops separation | partial | cần giữ ranh giới template/recipient rất chặt |

### Kết luận
- Email architecture đã đủ để vận hành funnel.
- Vấn đề hiện tại không phải thiếu email engine, mà là **đúng context, đúng recipient, đúng replay guard**.

## 9. Tenant Isolation

### Hiện trạng
- Có sẵn method `run_workspace_isolation_audit($tenantId)`.
- Audit này kiểm tra:
  - branding
  - company profile
  - localization
  - mail identity
  - invoice defaults
- Tenant workspace settings có option riêng, nhưng public views vẫn có chỗ đọc landlord/global option trực tiếp.

### Đánh giá
| Hạng mục | Nhận định | Rủi ro |
|---|---|---|---|
| Isolation audit tool | pass | có method kiểm tra rõ ràng |
| Tenant branding | pass | có settings riêng |
| Public fallback risk | fail/partial | public/landing code vẫn có thể bleed landlord option |
| Workspace/company/mail separation | partial | cần xác nhận runtime resolver ở mọi view |

### Kết luận
- Tenant isolation có khung audit, nhưng chưa đủ để coi là tuyệt đối.
- Đây là một trong các điểm rủi ro cao nhất của toàn funnel.

## 10. Go Live Readiness

### Đánh giá tổng thể
| Mảng | Trạng thái |
|---|---|
| Landing | ready |
| Pricing | ready |
| Signup | partial |
| Checkout | partial |
| Payment | partial |
| Provisioning | partial |
| Activation | partial |
| Emails | ready |
| Tenant Isolation | partial |

### Nhận định
- Hệ thống đã có đủ module thật để chạy funnel.
- Tuy nhiên go-live chưa nên được xem là “fully green” vì:
  - activation phụ thuộc nhiều trạng thái nối tiếp
  - landing/public context còn có nguy cơ bleed brand
  - payment/replay path trải qua nhiều lớp
  - provisioning là điểm trung gian nhiều failure mode

## 11. Critical Issues

1. **Public signup không activate ngay**
   - Signup tạo tenant draft và queue provisioning.
   - Nếu UI/ops kỳ vọng “đăng ký xong là xong”, sẽ hiểu sai luồng.

2. **Tenant isolation chưa tuyệt đối trên public path**
   - Một số view public vẫn đọc option landlord trực tiếp.
   - Nếu runtime resolver sai, branding sẽ bleed.

3. **Payment/webhook flow bị phân mảnh**
   - Checkout, PaymentCollectionService, BillingEngineService, SePay processor cùng tham gia.
   - Điều này đúng kiến trúc hiện tại nhưng tăng rủi ro replay và debugging.

4. **Provisioning là điểm chết giữa signup và activation**
   - Nếu job fail, tenant có thể bị treo ở trạng thái trung gian.

5. **Customer-facing vs ops email boundary cần giữ chặt**
   - Hệ thống đã có email đủ nhiều; sai recipient/context sẽ tạo noise hoặc leak.

## 12. Recommended Fix Order

1. **Khóa tenant/public context isolation**
   - Đảm bảo landing/public view không bleed landlord branding.

2. **Chuẩn hóa activation state machine**
   - Làm rõ các trạng thái draft / queued / provisioning / active / failed.

3. **Siết replay/idempotency cho payment + webhook**
   - Tập trung vào webhook retry, payment replay, provisioning retry.

4. **Rà soát lại UX signup expectation**
   - Nói rõ signup là start of provisioning, không phải activate ngay.

5. **Chốt boundary giữa customer mail và ops mail**
   - Customer-facing templates phải tách khỏi operational alerts.

6. **Chạy live smoke sau khi khóa context**
   - Ít nhất:
     - signup -> invoice ready -> provision queued
     - payment success replay guard
     - tenant isolation audit for a real tenant

## 13. Summary

- Funnel code đã có thật, không phải mock.
- Landing và pricing đã khá ổn.
- Signup/provisioning/activation/payment là phần cần kiểm soát chặt nhất.
- Rủi ro lớn nhất không phải thiếu tính năng, mà là **state consistency**, **runtime context**, và **replay safety**.

