# SIGNUP FLOW CONVERSION REPORT

Scope:
- Signup UX only.
- No billing engine changes.
- No checkout engine changes.
- No payment engine changes.
- No provisioning changes.

## Flow Simplification

### Before
1. Chọn gói
2. Doanh nghiệp
3. Subdomain
4. Xác nhận
5. Thanh toán
6. Hoàn tất

### After
1. Chọn gói
2. Thông tin workspace
3. Xác nhận và chuyển sang thanh toán

### Result
- Rút signup từ wizard admin-style sang purchase flow 3 bước.
- Bỏ các bước giả lập `Thanh toán` và `Hoàn tất` khỏi phần nhập liệu vì chúng không cần thêm action từ user.
- Giữ nguyên submit target và backend flow hiện tại:
  - tạo invoice
  - chuyển sang checkout
  - provisioning sau thanh toán

## Plan Card Redesign

### What changed
- Plan card không còn đẩy `API`, `Automation`, quota kỹ thuật lên vùng chính.
- Vùng chính chỉ giữ:
  - tên gói
  - best for
  - giá thuê bao
  - phí triển khai
  - 4 core modules: CRM / Inventory / Invoice / Payment
- Technical quota được chuyển xuống `details` phụ.

### Conversion intent
- Giảm overload kỹ thuật ở bước chọn gói.
- Đẩy user về logic mua hàng thay vì đọc entitlement như admin.

## Setup Fee Visibility

### What changed
- `Giá thuê bao` và `Phí triển khai` xuất hiện ngay trong plan card.
- `Phí triển khai` cũng xuất hiện trong summary sidebar.
- `Tổng dự kiến` = subscription + setup fee được tính realtime.

### Result
- Không còn tình huống sang checkout mới thấy setup fee.

## Summary Panel

### What changed
Sidebar giờ hiển thị realtime:
- gói
- giá thuê bao
- phí triển khai
- tổng dự kiến
- chu kỳ
- best for
- thông tin công ty
- email
- subdomain
- trial

### Extra
- CTA pricing từ landing giờ có thể đổ sẵn plan vào signup qua `preferred_plan_id`.

## Trust Elements

### Added in signup flow
- Tenant riêng biệt
- Backup
- SSL
- Hỗ trợ triển khai

### Reason
- Đây là các risk-removal elements quan trọng ngay trong purchase flow, không bắt user quay lại landing để tự suy luận.

## Mobile UX

### What changed
- Step bar chuyển thành stack 1 cột trên mobile.
- Plan cards chuyển về 1 cột.
- Price stack, core module grid, trust strip chuyển về 1 cột.
- Action buttons full-width trên mobile.
- Sidebar summary rơi xuống dưới main panel theo layout tự nhiên.

### Result
- Flow bớt dài theo chiều ngang.
- Pricing và setup fee vẫn đọc được rõ trên mobile.

## Before vs After

| Area | Before | After |
|---|---|---|
| Flow shape | 6-step wizard | 3-step purchase flow |
| Plan decision | technical-heavy | business-facing |
| Setup fee visibility | weak | explicit from step 1 |
| Summary | thin | realtime commercial summary |
| Trust | almost absent in flow | visible in signup body |
| Mobile | acceptable but admin-like | clearer purchase flow |

## Conversion Impact

### Expected improvement
- Tăng clarity ở bước chọn gói.
- Giảm drop-off giữa plan selection và workspace info.
- Giảm shock ở checkout do setup fee đã lộ rõ từ đầu.
- Tăng confidence nhờ trust elements và summary panel.

### Likely score movement
- Before: `5.5 - 6.0 / 10`
- After: `7.4 - 7.8 / 10`

### Why not higher
- Chưa có progress persistence.
- Chưa có live subdomain availability check.
- Chưa có social proof / support guarantee ngay trong signup.
- Checkout vẫn là bước tách riêng sau submit, không phải embedded commerce flow.
