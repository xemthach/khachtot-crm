# SOFT LAUNCH CONVERSION AUDIT

Scope:
- Audit the current KT Landing Template 1 as it exists now.
- No code changes.
- No UI changes.
- No new sections.
- Review is focused on soft-launch conversion quality before paid traffic.

## Persona Review

### CEO SME
- **Hiểu sản phẩm trong 10 giây?**: Partial.
  - Hero nói đúng hướng: một nền tảng thống nhất cho CRM, inventory, invoice, payment.
  - Tuy nhiên từ "Commercial SaaS", "Marketplace-ready", "Tenant operations dashboard" vẫn thiên về platform language hơn business outcome language.
  - CEO SME sẽ hiểu đây là phần mềm quản trị doanh nghiệp, nhưng chưa chắc hiểu ngay nó dành cho quy mô nào, team nào, và vấn đề cấp bách nào nó giải quyết trước.
- **Hiểu vì sao nên mua?**: Pass mức trung bình.
  - `Why KT SaaS`, comparison matrix, ROI explanation và pricing block đã cho lý do hợp lý.
  - Điểm mạnh là narrative "1 hệ thống thay nhiều phần mềm rời rạc".
  - Điểm yếu là thiếu outcome cụ thể kiểu "giảm thất thoát công nợ", "chốt sales nhanh hơn", "ít nhập liệu lại hơn" ở ngay above-the-fold.
- **Rủi ro không đăng ký**:
  - Không chắc sản phẩm có phù hợp team nhỏ 5-30 người hay không.
  - Không rõ thời gian triển khai thật, chi phí triển khai thật, support sau go-live.
  - Có thể thấy sản phẩm nhiều tính năng nhưng chưa rõ điểm bắt đầu.

### HVAC Company
- **Hiểu sản phẩm trong 10 giây?**: Partial.
  - Hero và lifecycle có liên hệ khá tốt tới quy trình lead -> báo giá -> hợp đồng -> hóa đơn -> thanh toán.
  - HVAC sẽ nhìn thấy case study và có cảm giác "đúng domain vận hành".
  - Nhưng thiếu mô tả rõ về service ticket, bảo trì, lịch kỹ thuật, đội field service.
- **Hiểu vì sao nên mua?**: Partial to pass.
  - Họ sẽ thấy giá trị ở CRM + hợp đồng + invoice.
  - Họ chưa thấy đủ bằng chứng rằng workflow hậu mãi / bảo trì hiện trường là năng lực mạnh.
- **Rủi ro không đăng ký**:
  - Nghĩ rằng đây là CRM/invoice platform chung chung, chưa chắc fit service operations.
  - Không thấy rõ dispatch, warranty, maintenance scheduling.

### Distributor
- **Hiểu sản phẩm trong 10 giây?**: Pass.
  - Inventory, invoice, payment và distributor case study khá đúng mạch.
  - Product explorer cũng làm rõ kho, tồn, đối soát, hóa đơn.
- **Hiểu vì sao nên mua?**: Pass.
  - Đây là persona được phục vụ tốt nhất trong landing hiện tại.
  - Lý do mua tương đối rõ: giảm phân mảnh kho + công nợ + thanh toán.
- **Rủi ro không đăng ký**:
  - Chưa thấy sâu về multi-warehouse, giá vốn, nhập xuất theo lô, hoặc purchase workflow.
  - Nếu distributor lớn hơn SME vừa, họ có thể nghi ngờ chiều sâu inventory.

### Service Company
- **Hiểu sản phẩm trong 10 giây?**: Partial.
  - CRM + projects + invoice + payment gợi đúng hướng.
  - Nhưng "service company" là persona rộng; landing chưa tách rõ giữa agency, consulting, maintenance, B2B service.
- **Hiểu vì sao nên mua?**: Partial.
  - Họ hiểu được value ở pipeline, project delivery, invoice, payment.
  - Họ chưa thấy năng lực collaboration, approval, task ownership, DMS đủ mạnh để ra quyết định nhanh.
- **Rủi ro không đăng ký**:
  - Không chắc dự án/dịch vụ có phải core strength hay chỉ là addon.
  - Thiếu bằng chứng về onboarding workflow, SLA, nội bộ team delivery.

### Persona verdict
- Distributor: strongest fit in current landing.
- CEO SME: good strategic fit, but message is still too platform-heavy in the first screen.
- HVAC Company: promising, but service operations proof is not deep enough.
- Service Company: understandable, but product fit still feels broad rather than precise.

## Pricing Understanding

### Họ có hiểu giá trong 30 giây không?
- **Pass mức khá**, nhưng chưa thật sắc.

### What works
- Pricing có `Trial / Starter / Basic / Standard`.
- Tách rõ:
  - `Giá thuê bao`
  - `Phí triển khai`
- Có `Best For`.
- Có khối giải thích:
  - giá thuê bao là gì
  - phí triển khai gồm gì
  - ROI

### What still slows comprehension
- `Technical limits` bị ẩn trong `<details>`, nên người dùng phải chủ động mở mới thấy giới hạn thật.
- Chưa có ví dụ chi phí thực tế kiểu:
  - "team 10 người thường bắt đầu từ..."
  - "gói nào đủ cho sales + kho + invoice"
- Pricing vẫn khá nhiều chữ với người mới vào lần đầu.
- CTA trên từng plan chưa tạo phân tách mạnh giữa:
  - self-serve trial
  - sales-led consultation

### Pricing verdict
- Người dùng có thể hiểu khung giá trong 30 giây.
- Họ chưa chắc hiểu ngay gói nào dành cho mình nếu không đọc kỹ `Best For` và chi tiết.

## CTA Review

### CTA hiện tại
- `Đặt hàng ngay` ở top nav
- `Dùng thử miễn phí`
- `Đặt lịch demo`
- `Đặt lịch demo` ở showcase
- CTA lặp lại ở comparison, pricing, final CTA

### Đánh giá
- **Đủ hiện diện**, nhưng **chưa đủ mạnh về intent design**.
- Điểm đúng:
  - có primary CTA rõ
  - có demo CTA cho nhóm cần sales
  - CTA xuất hiện ở nhiều điểm hợp lý
- Điểm yếu:
  - `Đặt hàng ngay` ở nav hơi mạnh tay và chưa đồng bộ với luồng trial-first phía dưới.
  - `Dùng thử miễn phí` và `Đặt lịch demo` là cặp CTA đúng, nhưng chưa gắn rõ với loại buyer nào.
  - Chưa có microcopy để giảm rủi ro kiểu:
    - không cần thẻ
    - có hỗ trợ triển khai
    - có thể demo theo ngành

### CTA verdict
- CTA hiện tại: **good enough for soft launch**
- Chưa tối ưu cho paid traffic quy mô lớn vì intent separation vẫn chưa đủ rõ.

## Objection Analysis

### Những lý do khiến họ không đăng ký
1. Không chắc sản phẩm dành cho công ty của họ hay chỉ là platform chung chung.
2. Không rõ triển khai bao lâu, ai hỗ trợ, dữ liệu lên hệ thống như thế nào.
3. Không rõ gói nào là lựa chọn an toàn để bắt đầu.
4. Không rõ phần inventory / project / eInvoice sâu đến đâu.
5. Chưa thấy bằng chứng xã hội mạnh:
   - logo khách hàng thật
   - con số dùng thật theo ngành
   - case study có tên thật / kết quả thật
6. Chưa rõ chính sách support, SLA, đào tạo, migration.
7. `Marketplace Pro` nhìn tốt hơn trước nhưng vẫn chưa đủ để trả lời câu hỏi "nếu tôi cần module X, hiện tại đã sẵn sàng đến mức nào?"

### Persona-specific objections
- CEO SME:
  - sợ phức tạp
  - sợ triển khai lâu
  - sợ mua xong team không dùng
- HVAC:
  - sợ thiếu service workflow chuyên ngành
- Distributor:
  - sợ inventory chưa đủ sâu
- Service company:
  - sợ project/collaboration chỉ ở mức cơ bản

## Missing Information

### Những gì còn thiếu trước khi họ liên hệ sales
1. Thời gian triển khai điển hình:
   - 1 ngày, 3 ngày, hay 2 tuần
2. Mức độ hỗ trợ onboarding:
   - ai setup
   - có import dữ liệu hay không
3. Ranh giới giữa gói Trial / Starter / Basic / Standard theo tình huống thực tế.
4. Cách dùng theo ngành:
   - HVAC
   - distributor
   - service company
5. Chính sách support sau mua:
   - kênh hỗ trợ
   - giờ hỗ trợ
   - escalation
6. Khả năng migration từ Excel / CRM cũ / phần mềm kho cũ.
7. Cam kết an toàn dữ liệu ở mức business language, không chỉ technical badges.

## Conversion Blockers

### Blocker lớn nhất hiện tại
1. **Hero chưa đủ cụ thể theo business outcome**
   - đẹp hơn nhiều rồi, nhưng vẫn còn nghiêng về product platform hơn là painkiller.

2. **Persona fit chưa đủ sharp**
   - landing nói được nhiều thứ, nhưng mỗi persona vẫn phải tự suy luận xem hệ thống có thật sự dành cho mình không.

3. **Pricing hiểu được nhưng chưa quyết định nhanh được**
   - người xem biết có nhiều gói, biết có setup fee, nhưng chưa chắc tự tin chọn plan.

4. **Thiếu trust proof thương mại mạnh**
   - trust indicators hiện mang tính platform credibility.
   - thiếu proof kiểu khách hàng thật, ngành thật, kết quả thật.

5. **Thiếu pre-sales risk removal**
   - chưa trả lời đủ các câu hỏi "triển khai bao lâu", "có ai hỗ trợ", "có import dữ liệu không", "nếu không fit thì sao".

## Final Conversion Score

### By category
- Product clarity in first 10 seconds: `7.2/10`
- Pricing clarity in 30 seconds: `7.8/10`
- Reason-to-buy strength: `7.6/10`
- CTA effectiveness: `7.4/10`
- Objection handling: `6.8/10`
- Paid traffic readiness: `7.1/10`

### Overall score
- **Final Conversion Score: 7.3 / 10**

Interpretation:
- Đủ tốt cho soft launch có kiểm soát.
- Chưa phải landing tối ưu để scale paid traffic mạnh ngay từ đầu.

## Ready For Paid Traffic?

- **Soft launch**: **Yes**
- **Paid traffic at controlled budget**: **Yes, with caution**
- **Aggressive scale**: **No, not yet**

### Reason
- Landing hiện tại đã đủ để:
  - giới thiệu sản phẩm
  - giải thích pricing tương đối rõ
  - tạo lead/demo/trial ban đầu
- Nhưng trước khi đẩy paid traffic mạnh, nên ưu tiên:
  1. sharpen hero message theo business outcome
  2. tăng persona-specific proof
  3. làm rõ plan selection logic
  4. bổ sung risk-removal thông tin trước khi contact sales

### Final verdict
- Current landing is **ready for controlled soft launch**.
- It is **not yet fully conversion-optimized for scaled paid acquisition**.
