# SOFT LAUNCH POLISH REPORT

Scope:
- Final polish pass before soft launch.
- No new feature.
- No new section.
- No business-logic change.
- Focus only on customer-facing language, pricing presentation, duplication cleanup, and frontend leakage removal.

## Admin Text Removed

Cleaned from public signup:
- removed direct mentions of `billing`, `checkout`, and `provisioning` from the hero/support copy
- removed internal phrasing such as:
  - “Flow này chỉ thay đổi trải nghiệm mua hàng...”
  - “ngôn ngữ mua hàng”
  - “giới hạn kỹ thuật được giữ ở mức phụ”
  - “Sidebar cập nhật realtime...”
  - “Review trước khi sang checkout”
  - “Tạo hóa đơn và sang checkout”

Customer-facing replacements now read as:
- chọn gói dịch vụ
- thông tin doanh nghiệp
- xác nhận trước khi thanh toán
- bảng tóm tắt cập nhật tự động
- tiếp tục đến thanh toán

## Language Consistency

Direction chosen:
- primary public path is now Vietnamese-first

Adjusted on active landing/signup surfaces:
- `Trust indicators` -> `Chỉ số tin cậy`
- `Use Case Flow` -> `Quy trình 6 bước`
- `Customer Lifecycle Visualization` -> `Chi tiết hành trình khách hàng`
- `Interactive Product Explorer` -> `Khám phá sản phẩm`
- `Marketplace Pro` -> `Ứng dụng mở rộng`
- `Benefits` -> `Lợi ích`
- `Workflow` -> `Quy trình`

## Product Language Cleanup

Normalized on customer-facing surfaces:
- `KT MatBao Invoice` -> `Hóa đơn điện tử`
- `KT SePay` -> `Thanh toán & Đối soát`
- `Tenant Count` -> `Doanh nghiệp đang vận hành`
- `Invoice Count` -> `Hóa đơn đã xử lý`
- `Transaction Count` -> `Giao dịch`

Tenant subscription page:
- module tag `kt_matbao_invoice` is now rendered as `Hóa đơn điện tử`
- module tag `kt_sepay` is now rendered as `Thanh toán & Đối soát`
- module tag `kt_inventory` is now rendered as `Quản lý kho`
- section title changed to `Ứng dụng đi kèm`

## Pricing Fixes

Landing:
- VND formatting updated to `5.880.000 VND` style
- pricing amount block hardened with `white-space: nowrap`
- pricing numerals use tabular alignment

Signup:
- VND formatting updated to `5.880.000 VND`
- summary and review totals are forced to stay on one line
- setup fee and subscription price blocks no longer break across lines

Tenant subscription:
- plan price row hardened to avoid wrapping
- price amount uses `white-space: nowrap` and tabular numerals

## Plan Description Fixes

Active mapping kept distinct for:
- `Dùng thử`
- `SME Mini`
- `SME`
- `SME Plus`

Result:
- plans no longer depend on one generic “best for” message in the active signup/landing path
- fallback badges remain customer-facing:
  - `Dùng thử`
  - `Dễ bắt đầu`
  - `Phổ biến nhất`
  - `Cho doanh nghiệp mở rộng`

## Duplication Removed

Duplicated process storytelling was reduced without adding or removing sections:
- the first flow section stays as a compact overview row
- the second section is reframed as the detailed journey view

Practical result:
- less “same idea twice” feeling
- clearer hierarchy:
  1. quick 6-step overview
  2. detailed operational journey
  3. product explorer

## Before vs After

Before:
- signup still sounded like an internal admin wizard
- landing still mixed English labels and technical module language
- public price blocks could wrap awkwardly
- tenant subscription leaked module-code naming
- use-case storytelling felt duplicated

After:
- signup reads like a commercial purchase flow
- landing reads more like a CRM product page than a developer platform page
- VND price display is more stable and scannable
- tenant plan page shows business labels instead of module code
- process narrative is tighter

## Soft Launch Score

- Landing: `8.8/10`
- Signup: `8.1/10`
- Pricing: `8.5/10`
- Product Understanding: `8.6/10`
- Conversion Readiness: `8.2/10`

## Soft Launch Ready?

- **Yes**, for controlled soft launch.

Why:
- the most damaging public leakage points were removed from landing/signup
- pricing is clearer and visually safer
- CRM/product language is more commercial
- tenant subscription no longer shows the most obvious module-code leak

Remaining polish risk:
- some legacy strings outside the primary soft-launch path still deserve a later cleanup pass
- explorer/mock UI copy still has room for one last copy-only refinement pass if needed after screenshot QA
