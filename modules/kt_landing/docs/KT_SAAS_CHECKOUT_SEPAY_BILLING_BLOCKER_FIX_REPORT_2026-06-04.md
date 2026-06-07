# KT SAAS CHECKOUT / SEPAY / BILLING BLOCKER FIX REPORT

## Executive Summary
Đã đóng blocker thanh toán trên money path public signup -> invoice -> checkout -> SePay QR.

Kết quả đã verify:
- SME Mini invoice total = 13,380,000 VND
- checkout invoice page hiển thị đúng tiếng Việt
- checkout dẫn sang SePay QR thay vì dừng ở invoice info page
- payment request amount = invoice total
- admin invoice list hiển thị đúng tổng tiền cho invoice mới

## Reproduction Evidence
Test signup mới với plan SME Mini đã tạo invoice mới:
- plan price: 5,880,000 VND
- setup fee: 7,500,000 VND
- expected total: 13,380,000 VND
- invoice_id: 30 trong test cuối

Kết quả signup status:
- Gói dịch vụ: SME Mini
- Giá gói: 5,880,000 VND
- Phí triển khai: 7,500,000 VND
- Tổng dự kiến: 13,380,000 VND

## Root Cause 1 — Amount Calculation
`BillingEngineService::createSubscriptionInvoice()` trước đây chỉ lấy `plan.price`, bỏ qua `plan.setup_fee` ở public signup path.

Fix:
- cộng `subscription_price + setup_fee` cho invoice khởi tạo
- lưu `line_items` và `billing_summary` vào `payload_json`
- giữ renewal không tính setup fee lại
- sync lại open invoice nếu tổng cũ sai

## Root Cause 2 — SePay Checkout Flow
`Checkout::buildSepayCheckoutUrl()` trước đây chặn quá chặt bằng gate active/module, khiến URL SePay rỗng và flow rơi về invoice info page.

Fix:
- bỏ gate quá chặt ở public checkout
- tạo payment request theo invoice/tenant context
- `Checkout::invoice()` redirect sang SePay QR nếu có `sepay_url`

## Root Cause 3 — UTF-8 Encoding
Checkout view và signup status view có mojibake hard-coded.

Fix:
- rewrite checkout public view sang UTF-8 chuẩn
- rewrite signup status view sang UTF-8 chuẩn
- set title checkout thành `Thanh toán hóa đơn dịch vụ`
- set title SePay thành `Thanh toán qua SePay`

## Files Checked
- [modules/kt_saas/services/BillingEngineService.php](../kt_saas/services/BillingEngineService.php)
- [modules/kt_saas/controllers/Checkout.php](../kt_saas/controllers/Checkout.php)
- [modules/kt_saas/views/public/checkout.php](../kt_saas/views/public/checkout.php)
- [modules/kt_sepay/controllers/Kt_sepay_public.php](../kt_sepay/controllers/Kt_sepay_public.php)
- [modules/kt_sepay/views/payment/qr.php](../kt_sepay/views/payment/qr.php)
- [modules/kt_landing/views/public/signup_status.php](../kt_landing/views/public/signup_status.php)
- [modules/kt_landing/controllers/Kt_landing_public.php](../kt_landing/controllers/Kt_landing_public.php)
- [modules/kt_landing/controllers/Kt_landing.php](../kt_landing/controllers/Kt_landing.php)

## Fix Applied
- invoice total calculation now includes setup fee for initial signup
- invoice payload stores line items and billing summary
- checkout route now redirects to SePay QR when payment URL exists
- checkout invoice page renders line items and correct total
- signup status page shows price, setup fee, and total
- UTF-8 mojibake removed from checkout and payment surfaces

## Billing Rule After Fix
- public signup invoice: `subscription_price + setup_fee`
- trial: total follows plan config
- renewal: setup fee not charged again
- plan change invoice: continues to use plan-change breakdown
- invoice sync keeps stale open invoices aligned to current plan data

## Invoice Line Items
For SME Mini:
1. Gói dịch vụ: SME Mini
   - 5,880,000 VND
2. Phí triển khai ban đầu
   - 7,500,000 VND

Total:
- 13,380,000 VND

## SePay Flow Verification
Verified in browser:
- checkout invoice URL redirects to SePay QR
- QR page shows amount `13,380,000 VND`
- transfer content and bank details render correctly

## UTF-8 Verification
Verified pages show correct Vietnamese:
- `Thanh toán hóa đơn dịch vụ`
- `Không tạo được yêu cầu thanh toán SePay. Vui lòng liên hệ quản trị viên hệ thống.`
- `Thanh toán qua SePay`
- `Trang thái đăng ký CRM`

## Database Verification
Verified by browser/runtime behavior:
- new invoice stored with total `13,380,000`
- SePay payment request amount matches invoice total
- admin invoice list shows correct total for new invoice rows

## Browser Screenshots
- [Signup status](../screenshots/blocker-signup-status.png)
- [Checkout invoice page](../screenshots/blocker-checkout-invoice.png)
- [SePay QR page](../screenshots/blocker-sepay-qr.png)
- [Admin invoice list](../screenshots/blocker-admin-invoices.png)

## Test Cases
Passed in browser:
- SME Mini: 5,880,000 + 7,500,000 = 13,380,000
- invoice checkout -> SePay QR
- admin invoice list shows 13,380,000

## Regression Result
No regression observed in:
- Landing public
- Signup wizard
- Tenant creation/provisioning queue
- Admin invoice list
- SePay QR page
- Payment request creation

## Final Status
Blocker fixed and verified on the live local browser flow.
