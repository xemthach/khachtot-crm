# LANDING CONTENT CONSOLIDATION REPORT

Scope:
- Audit duplication across the active `fastwork_inspired` landing.
- No new section.
- No UI rewrite.
- No business-logic change.

## Duplicate Sections

| Section | Purpose | Unique Value | Overlap With | Keep? | Merge? | Remove? |
|---|---|---|---|---|---|---|
| Chỉ số tin cậy | Social proof via scale/usage metrics | Gives the first trust signal before product detail | Bảo mật, Vì sao chọn CRM Khách Tốt | Yes | No | No |
| Vì sao chọn CRM Khách Tốt | Business rationale and value props | Explains why the product is a CRM-first operating system | So sánh, Chỉ số tin cậy | Yes | Partial with So sánh if needed | No |
| So sánh | Direct objection handling against fragmented stacks | Makes the buying case explicit | Vì sao chọn CRM Khách Tốt, Bảo mật | Yes | No | No |
| Quy trình 6 bước | High-level workflow strip | Quick summary of the customer flow | Chi tiết hành trình khách hàng | No | Yes, into Chi tiết hành trình khách hàng | Yes |
| Chi tiết hành trình khách hàng | Detailed workflow cards | More useful than the 6-step strip because it shows state + operational detail | Quy trình 6 bước | Yes | Can absorb the high-level strip copy | No |
| CRM Showcase | Product demo | Redundant once the tabbed explorer exists | Khám phá sản phẩm | No | Yes, into Khám phá sản phẩm | Yes |
| Inventory Showcase | Product demo | Redundant once the tabbed explorer exists | Khám phá sản phẩm | No | Yes, into Khám phá sản phẩm | Yes |
| Invoice Showcase | Product demo | Redundant once the tabbed explorer exists | Khám phá sản phẩm | No | Yes, into Khám phá sản phẩm | Yes |
| Payment Showcase | Product demo | Redundant once the tabbed explorer exists | Khám phá sản phẩm | No | Yes, into Khám phá sản phẩm | Yes |
| Khám phá sản phẩm | Interactive product demo hub | Consolidates CRM, kho, hóa đơn, thanh toán, dự án, tài liệu in one place | CRM/Inventory/Invoice/Payment Showcase | Yes | No | No |
| Ứng dụng mở rộng | Add-on / ecosystem / marketplace | Shows expansion path beyond core CRM | Khám phá sản phẩm, Bảo mật | Yes | Partial with marketplace copy if desired | No |
| Bảo mật | Controls, access, backup, audit trail | Distinct from social proof and value proposition | Chỉ số tin cậy, So sánh | Yes | No | No |

## Merge Candidates

1. `Quy trình 6 bước` should be absorbed into `Chi tiết hành trình khách hàng`.
2. `CRM Showcase`, `Inventory Showcase`, `Invoice Showcase`, and `Payment Showcase` should be absorbed into `Khám phá sản phẩm`.
3. If more simplification is needed later, `Chỉ số tin cậy` can be tightened so it does not repeat messages already proven in `Bảo mật` and `So sánh`.

## Remove Candidates

1. `Quy trình 6 bước`
2. `CRM Showcase`
3. `Inventory Showcase`
4. `Invoice Showcase`
5. `Payment Showcase`

## Final Landing Structure

Recommended order after consolidation:
1. Hero
2. Chỉ số tin cậy
3. Vì sao chọn CRM Khách Tốt
4. So sánh
5. Chi tiết hành trình khách hàng
6. Khám phá sản phẩm
7. Ứng dụng mở rộng
8. Bảo mật
9. Pricing
10. Case studies
11. FAQ
12. Final CTA

## Before vs After

### Before
- Two adjacent workflow sections said the same thing at different depths.
- Four separate product showcase sections repeated the same app story in four fragments.
- The page carried more repeated narrative than necessary before pricing.

### After
- One workflow section remains, at the higher-value detail level.
- One product demo hub remains, with add-on ecosystem kept separate.
- The landing reads as a tighter CRM sales page with fewer repeated ideas and less scroll fatigue.

## Consolidation Verdict

- Keep `Chi tiết hành trình khách hàng`.
- Remove `Quy trình 6 bước`.
- Keep `Khám phá sản phẩm`.
- Remove the four standalone showcase sections.
- Keep `Ứng dụng mở rộng` and `Bảo mật` because they cover different objections.

