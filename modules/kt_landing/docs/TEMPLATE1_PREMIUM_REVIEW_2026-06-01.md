# TEMPLATE 1 PREMIUM REVIEW

Ngày audit: 2026-06-01  
Phạm vi: `modules/kt_landing/views/public/templates/fastwork_inspired/*`  
Nguyên tắc: Chỉ đánh giá Template 1, không đụng Template 2/3/4.

## 1) Tại sao template hiện tại fail

### Bảng chấm điểm (0-10)

| Hạng mục | Điểm | Kết luận |
|---|---:|---|
| Hero | 6.5 | Chưa đủ “full first viewport”, headline và visual chưa tạo cảm giác sản phẩm premium |
| Product Visual | 6.0 | Mockup còn dạng card mô phỏng, chưa giống màn hình sản phẩm thực |
| Product Story | 6.0 | Có section nhưng narrative yếu, chưa dẫn flow từ pain -> solution -> outcome |
| Pricing | 6.5 | Card còn generic, thiếu giới hạn sâu/module matrix/featured hierarchy rõ |
| Conversion | 6.0 | CTA có nhưng chưa tối ưu conversion path theo hành vi khách |
| Branding | 6.5 | Chưa có bản sắc mạnh kiểu FastWork/Base/Zoho; typography và visual language còn phẳng |
| Trust | 5.5 | Social proof mỏng, thiếu logo wall, case metric, proof cụ thể theo ngành |
| Add-ons | 6.0 | Mới là danh sách card tĩnh, chưa thể hiện rõ package/value/outcome |
| CTA | 6.5 | Có nhiều CTA nhưng chưa có chiến lược thông điệp khác nhau theo vị trí |
| Overall SaaS Quality | 6.2 | Đang ở mức “template khá”, chưa đạt chuẩn landing SaaS bán hàng premium |

Kết luận cứng: Có nhiều mục dưới 8/10 => **Template không đạt** theo tiêu chí đã đặt.

## 2) Những phần phải bỏ

1. Bỏ tư duy “xếp section tuần tự rồi render text”.
2. Bỏ hero dạng mô tả nhẹ + visual dạng panel đơn.
3. Bỏ pricing generic (mô tả ngắn + CTA chung chung).
4. Bỏ add-ons dạng list không có chiều sâu use-case.
5. Bỏ trust section thiếu chứng cứ cụ thể.
6. Bỏ cấu trúc copy đồng mức (nhiều đoạn text ngang nhau, không phân cấp).

## 3) Thiết kế lại toàn bộ Template 1 (định hướng)

### Mục tiêu cảm giác
- Chuẩn sản phẩm: FastWork / Base.vn / Getfly / Zoho.
- Trọng tâm: “website bán SaaS”, không phải “UI demo”.

### Kiến trúc nội dung mới
1. Hero 50/50 full-screen:
   - Left: headline mạnh, subtitle rõ giá trị, CTA chính + CTA phụ, trust chips.
   - Right: product visual lớn kiểu dashboard thật (Pipeline, Revenue, Tasks, Invoice, Customer).
2. Section “Sản phẩm theo nghiệp vụ”:
   - CRM, DMS, Inventory, Invoice, Payment hiển thị trực quan ngay đầu trang.
3. Story section theo outcome:
   - “Chuẩn hóa quy trình” -> “Giảm thất thoát” -> “Mở rộng không đổi hệ thống”.
4. Proof section:
   - KPI lớn + logo doanh nghiệp + testimonial có số liệu.
5. Pricing 2 lớp:
   - Tầng marketing: badge, featured, CTA.
   - Tầng kỹ thuật: giới hạn user/storage/module/add-on quyền.
6. Add-ons marketplace:
   - MatBao Invoice, HSM, SePay, Domain, Hosting, Website dạng product cards thật.
7. CTA đa tầng:
   - Hero CTA, Mid CTA theo use-case, Final CTA chốt conversion.

## 4) Mockup structure mới

1. `TopNav`  
   - Brand, menu trọng yếu, CTA “Dùng thử miễn phí”
2. `Hero Premium` (viewport cao ~90-100vh)  
   - Left content stack + trust indicators  
   - Right dashboard mockup composite
3. `Trust Strip`  
   - KPI + customer logo line
4. `Product Value Grid`  
   - CRM / DMS / Inventory / Invoice / Payment
5. `Use-case Story`  
   - Theo ngành hoặc phòng ban, có visual và mini CTA
6. `Pricing Premium`  
   - Plan cards có badge, feature matrix mini, constraint highlights
7. `Add-ons Marketplace`  
   - Cards có icon, mô tả, lợi ích, CTA riêng
8. `Case Studies / Testimonials`  
   - Quote + before/after metric
9. `FAQ`
10. `Final CTA Banner`
11. `Footer nâng cấp`

## 5) Files sẽ thay đổi (bước implement kế tiếp)

1. `modules/kt_landing/views/public/templates/fastwork_inspired/index.php`
   - Rebuild layout + hierarchy + copy khung.
2. `modules/kt_landing/assets/templates/fastwork_inspired/style.css`
   - Rebuild design system: spacing scale, hero composition, pricing cards, add-on cards, proof band.
3. `modules/kt_landing/controllers/Kt_landing.php` (nhẹ)
   - Chỉ bổ sung data mapping nếu cần cho template mới (không đổi logic billing/provisioning).
4. (Nếu cần) `modules/kt_landing/views/public/templates/fastwork_inspired/partials/*.php`
   - Tách partial để quản lý maintainability.

## Chốt hướng triển khai

- Đây là bản **audit + design blueprint**.  
- Bước tiếp theo: implement full-template theo structure trên, không patch lặt vặt, giữ nguyên runtime tenant/admin/client hiện hữu.
