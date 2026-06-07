# Ghi chú thiết kế module theo mẫu `warehouse` cho Perfex CRM

## Mục đích

Tài liệu này tóm tắt cách module `warehouse` trong dự án đang được tổ chức để có thể tái sử dụng ý tưởng khi xây module Perfex CRM khác.

Phạm vi tài liệu:
- Chỉ ghi lại những gì quan sát được từ source hiện tại.
- Không khuyến nghị sao chép nguyên xi phần active/license của `warehouse`.
- Tập trung vào kiến trúc module, hook, menu, permission, controller, model, view, install/migration, assets và integration.

## 1. Cấu trúc thư mục module

Mẫu thư mục thực tế của `warehouse`:

```text
modules/warehouse/
  assets/
  controllers/
  documents/
  helpers/
  language/
  libraries/
  migrations/
  models/
  uploads/
  views/
  install.php
  warehouse.php
```

Ý nghĩa:
- `warehouse.php`: file init chính của module.
- `install.php`: tạo bảng, cột, option mặc định.
- `controllers/`: route entry cho admin/client/utility.
- `models/`: business logic và truy vấn dữ liệu.
- `helpers/`: hook phụ, hàm dùng chung, utility.
- `libraries/`: class riêng như PDF, mail, verify, merge field support.
- `views/`: giao diện theo từng tính năng.
- `assets/`: JS/CSS/plugin riêng của module.
- `migrations/`: version hóa schema và update theo phiên bản.
- `uploads/`: file nghiệp vụ riêng của module.
- `language/`: đa ngôn ngữ.

## 2. File init module

File init là [modules/warehouse/warehouse.php](/d:/laragon/www/khachtot/modules/warehouse/warehouse.php:1).

Những phần chính mà file init này đang làm:
- Khai báo metadata module qua comment header.
- Khai báo constants dùng toàn module.
- Đăng ký hook menu, permission, assets, cron, integration.
- Đăng ký activation hook.
- Nạp helper chính của module.
- Chứa nhiều hàm hook callback.

Mẫu tư duy nên giữ:
- Dùng `warehouse.php` làm bootstrap duy nhất.
- Khai báo constants đường dẫn upload và revision tại đây.
- Gom toàn bộ hook đăng ký vào đầu file để dễ audit.

## 3. Hook hệ thống

Các hook thực tế đang dùng trong `warehouse.php`:
- `admin_init`
- `app_admin_head`
- `app_admin_footer`
- `after_invoice_view_as_client_link`
- `invoice_add_good_delivery_tab_content`
- `after_invoice_added`
- `after_invoice_updated`
- `invoice_marked_as_cancelled`
- `invoice_unmarked_as_cancelled`
- `before_invoice_deleted`
- `after_purchase_order_add`
- `after_purchase_order_approve`
- `after_cron_run`
- `customers_navigation_end`
- `app_customers_portal_head`
- `app_customers_portal_footer`
- `project_tabs`

Các hook helper-level trong [modules/warehouse/helpers/warehouse_helper.php](/d:/laragon/www/khachtot/modules/warehouse/helpers/warehouse_helper.php:4):
- `warehouse_init`
- `pre_activate_module`
- `pre_deactivate_module`
- `pre_uninstall_module`

Bài học thiết kế:
- Tách hook business và hook lifecycle rõ ràng.
- Hook business nên giữ ở `warehouse.php`.
- Hook hỗ trợ activate/deactivate có thể để trong helper nếu muốn tách bootstrap và utility.

## 4. Menu và permission

Menu admin được dựng trong `warehouse_module_init_menu_items()` tại [modules/warehouse/warehouse.php](/d:/laragon/www/khachtot/modules/warehouse/warehouse.php:193).

Permission staff được đăng ký trong `warehouse_permissions()` tại [modules/warehouse/warehouse.php](/d:/laragon/www/khachtot/modules/warehouse/warehouse.php:507).

Mẫu capability thực tế:
- `warehouse_item`
- `wh_stock_import`
- `wh_stock_export`
- `wh_stock_export_serial_number`
- `wh_packing_list`
- `wh_internal_delivery_note`
- `wh_loss_adjustment`
- `wh_receipt_return_order`
- `wh_warehouse`
- `wh_warehouse_history`
- `wh_report`
- `wh_setting`

Nguyên tắc nên tái sử dụng:
- Tách capability theo domain nghiệp vụ, không dồn hết vào một permission chung.
- Menu chỉ hiển thị nếu user có ít nhất một permission liên quan.
- Submenu map trực tiếp tới từng controller method chính.

## 5. Controller pattern

Controller thực tế:
- [modules/warehouse/controllers/Warehouse.php](/d:/laragon/www/khachtot/modules/warehouse/controllers/Warehouse.php:1): admin controller chính
- [modules/warehouse/controllers/Warehouse_client.php](/d:/laragon/www/khachtot/modules/warehouse/controllers/Warehouse_client.php:1): portal client
- [modules/warehouse/controllers/Gtsverify.php](/d:/laragon/www/khachtot/modules/warehouse/controllers/Gtsverify.php:1): controller phục vụ activate

Pattern rút ra:
- 1 admin controller lớn cho nghiệp vụ chính.
- 1 client controller riêng nếu có portal khách hàng.
- 1 utility controller riêng cho quy trình kỹ thuật riêng biệt.

Nên áp dụng cho module mới:
- Tách `AdminController` và `ClientsController`.
- Tách controller kỹ thuật riêng nếu có import, webhook, callback, sync.
- Với module mới, nên chia nhỏ controller hơn `warehouse`; tránh một controller quá lớn.

## 6. Model pattern

Model chính là [modules/warehouse/models/Warehouse_model.php](/d:/laragon/www/khachtot/modules/warehouse/models/Warehouse_model.php:1).

Vai trò hiện tại:
- Truy vấn kho, phiếu nhập, phiếu xuất, tồn kho, shipment, packing list, order return.
- Chứa business logic lớn của module.
- Thực hiện integration với hóa đơn, purchase, shipment, báo cáo.

Điểm nên học:
- Dồn business logic vào model thay vì nhét hết vào controller.

Điểm không nên lặp lại:
- File model quá lớn sẽ khó bảo trì.

Khuyến nghị cho module mới:
- Tách model theo bounded context:
  - `Inventory_model`
  - `Receipt_model`
  - `Delivery_model`
  - `Warehouse_settings_model`
  - `Shipment_model`
  - `Report_model`

## 7. View pattern

Thư mục `views/` của module được chia theo chức năng:
- `inventory/`
- `manage_goods_receipt/`
- `manage_goods_delivery/`
- `manage_internal_delivery/`
- `manage_stock_take/`
- `manage_warehouse/`
- `packing_lists/`
- `order_returns/`
- `report/`
- `client_shipments/`
- `serial_numbers/`

Ý tưởng nên tái sử dụng:
- Chia view theo domain tính năng thay vì dồn tất cả vào root `views/`.
- Assets JS/CSS cũng có thể map theo nhóm view tương ứng.

## 8. Install và migration pattern

Activation hook nằm ở [modules/warehouse/warehouse.php](/d:/laragon/www/khachtot/modules/warehouse/warehouse.php:167) và gọi [modules/warehouse/install.php](/d:/laragon/www/khachtot/modules/warehouse/install.php:1).

`install.php` hiện đang:
- Tạo bảng mới cho nghiệp vụ warehouse.
- Alter bảng core như `tblitems`, `tblitemable`, `tblproposals`, `tblleads`.
- Tạo option mặc định bằng `add_option(...)`.
- Kiểm tra module khác đang active bằng `get_status_modules_wh('purchase')`.

`migrations/` có `51` file versioned.

Mẫu nên áp dụng:
- Có `install.php` cho lần cài đầu.
- Có `migrations/` cho các thay đổi phiên bản sau.
- Kiểm tra `table_exists` và `field_exists` trước khi tạo/sửa schema.
- Tạo option mặc định ngay trong install/migration.

Khuyến nghị:
- Hạn chế sửa bảng core nếu không cần thiết.
- Nếu phải sửa bảng core, cần ghi rõ dependency và regression test.

## 9. Settings/options pattern

`warehouse` dùng `tbloptions` khá nhiều cho:
- prefix số chứng từ
- cài đặt cron
- rule làm tròn giá
- portal shipment
- serial number
- packing list
- currency rate

Mẫu nên áp dụng:
- Tất cả cấu hình business nên đưa vào `tbloptions`.
- Dùng prefix nhất quán theo module để tránh trùng tên.

Khuyến nghị cho module mới:
- Dùng namespace option rõ ràng, ví dụ:
  - `my_module_*`
  - `my_module_cron_*`
  - `my_module_prefix_*`

## 10. Assets pattern

`warehouse` load assets có điều kiện theo URL hiện tại trong:
- `warehouse_add_head_components()`
- `warehouse_load_js()`

Ý tưởng có thể dùng lại:
- Chỉ load JS/CSS ở đúng màn hình cần thiết.
- Tách file JS theo từng feature.
- Dùng `REVISION` để cache-busting.

Khuyến nghị:
- Với module mới, có thể giữ pattern condition-load này.
- Nếu module lớn, nên gom helper hàm build asset map để file init gọn hơn.

## 11. Integration pattern

Từ source hiện tại, `warehouse` đang tích hợp với:
- Invoice
- Purchase
- Customer portal
- Project tabs
- Omni sales/shipment flow
- Cron
- Email template / merge fields

Pattern nên tái sử dụng:
- Tích hợp qua hook thay vì sửa core trực tiếp.
- Chỉ gọi integration logic khi module phụ thuộc đang active.
- Tách check dependency thành helper như `get_status_modules_wh()`.

## 12. Luồng lifecycle module

Luồng đang có của `warehouse`:
1. Perfex load module active qua `InitHook`.
2. `warehouse.php` được include.
3. Hook/menu/permission/assets được đăng ký.
4. Khi admin vào controller `Warehouse`, constructor gọi `hooks()->do_action('warehouse_init')`.
5. Các business method render view và gọi model xử lý nghiệp vụ.
6. Cron và integration khác chạy qua hook.

Đây là một pattern rất điển hình cho module Perfex lớn:
- bootstrap qua init file
- phụ thuộc hook
- điều hướng bằng controller method
- persistence qua install/migration + options

## 13. Phần active/license của `warehouse`

Source hiện tại có thêm một lớp active/license riêng, gồm:
- pre-activate check
- runtime verify
- local `.lic`
- anti-tamper source check
- remote verify qua `WarehouseLic`

Những phần này nằm ở:
- [modules/warehouse/warehouse.php](/d:/laragon/www/khachtot/modules/warehouse/warehouse.php:626)
- [modules/warehouse/helpers/warehouse_helper.php](/d:/laragon/www/khachtot/modules/warehouse/helpers/warehouse_helper.php:2271)
- [modules/warehouse/helpers/warehouse_helper.php](/d:/laragon/www/khachtot/modules/warehouse/helpers/warehouse_helper.php:2521)
- [modules/warehouse/libraries/gtsslib.php](/d:/laragon/www/khachtot/modules/warehouse/libraries/gtsslib.php:35)

Nếu dùng `warehouse` làm mẫu cho module mới:
- Nên tách lifecycle business khỏi lifecycle license.
- Không nên trộn verify license vào các controller business quan trọng.
- Nếu cần license cho module riêng, nên thiết kế lớp riêng biệt, tối thiểu ảnh hưởng menu, route, permission và data.

## 14. Mẫu blueprint nên tái sử dụng cho module mới

Một module Perfex lớn nên có cấu trúc gần như sau:

```text
modules/my_module/
  assets/
    css/
    js/
  controllers/
    My_module.php
    My_module_client.php
    Webhook.php
  helpers/
    my_module_helper.php
  libraries/
  language/
  migrations/
  models/
    My_module_model.php
    My_module_settings_model.php
  views/
    dashboard/
    settings/
    reports/
    client/
  uploads/
  install.php
  my_module.php
```

## 15. Checklist thiết kế module mới

- Có file init module riêng.
- Có activation hook rõ ràng.
- Có install script idempotent.
- Có migration theo version.
- Có permission tách theo domain.
- Có menu condition theo permission.
- Có admin controller và client controller riêng nếu cần.
- Có helper cho hook dùng chung.
- Có assets load theo từng trang.
- Có option namespace rõ ràng.
- Có integration qua hook, không sửa core trực tiếp nếu tránh được.
- Có tách business logic khỏi license/activation logic.

## 16. Kết luận ngắn

`warehouse` là một mẫu module Perfex CRM lớn, nhiều tính năng, nhiều hook, nhiều integration và có đầy đủ bootstrap, install, migration, menu, permission, assets, client portal và cron.

Điểm nên tận dụng:
- cấu trúc thư mục
- cách bootstrap module
- cách đăng ký menu/permission/hook
- cách chia view theo feature
- cách dùng install + migration + options

Điểm nên cải tiến nếu xây module khác:
- chia nhỏ controller/model
- tách business logic theo domain
- tách hẳn active/license ra khỏi flow nghiệp vụ
- tránh gate runtime có thể tự deactive module trong lúc đang chạy
