# KT SaaS Landing - Phase 4 Static Self Check (2026-06-01)

## Mục tiêu
Kiểm tra nhanh ở mức code wiring cho Phase 4 khi chưa thể chạy browser E2E đầy đủ từ CLI.

## Lệnh chạy

```bash
php modules/kt_saas/tools/phase4_static_self_check.php
```

## Kết quả
- `success`: `true`
- `pass/total`: `15/15`

Các check chính đã pass:
- Default route trỏ `kt_landing`.
- Route public `/pricing`, `/signup`, `/signup/status`.
- Tenant runtime guard + redirect `clients`.
- Signup form có CSRF + anti-spam fields (`signup_ts`, `website` honeypot).
- Backend rate-limit function + event log.
- SePay webhook hook sau thanh toán để queue provisioning.
- Guard idempotency chống queue trùng.
- Model provisioning có dedupe `queued/running`.

## Giới hạn
- Đây là kiểm tra static.
- E2E thật (browser/session + webhook thật + provisioning retry thật) vẫn cần chạy thủ công để đóng Phase 4 hoàn toàn.
