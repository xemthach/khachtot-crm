<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * KT eInvoice — Language (Vietnamese)
 */

// ── Chung ──────────────────────────────────────────────────────────────────────
$lang['kt_einvoice']                         = 'Hóa Đơn Điện Tử';
$lang['kt_einvoice_module_name']             = 'KT eInvoice';
$lang['kt_einvoice_module_description']      = 'Phát hành hóa đơn điện tử qua SePay eInvoice API';

// ── Menu ───────────────────────────────────────────────────────────────────────
$lang['kt_einvoice_menu_dashboard']          = 'Tổng Quan';
$lang['kt_einvoice_menu_invoices']           = 'Danh Sách Hóa Đơn';
$lang['kt_einvoice_menu_batch_issue']        = 'Phát Hành Theo Lô';
$lang['kt_einvoice_menu_settings']          = 'Cài Đặt eInvoice';
$lang['kt_einvoice_menu_reports']           = 'Báo Cáo';

// ── Dashboard ──────────────────────────────────────────────────────────────────
$lang['kt_einvoice_dashboard_title']         = 'Tổng Quan Hóa Đơn Điện Tử';
$lang['kt_einvoice_this_month']              = 'Tháng này';
$lang['kt_einvoice_total_issued']            = 'Đã phát hành';
$lang['kt_einvoice_pending']                 = 'Đang xử lý';
$lang['kt_einvoice_failed']                  = 'Thất bại';
$lang['kt_einvoice_quota_used']              = 'Hạn mức đã dùng';
$lang['kt_einvoice_quota_remaining']         = 'Hạn mức còn lại';
$lang['kt_einvoice_recent_invoices']         = 'Hóa đơn gần nhất';
$lang['kt_einvoice_view_all']                = 'Xem tất cả';
$lang['kt_einvoice_environment_badge']       = 'Môi trường';
$lang['kt_einvoice_no_invoices_yet']         = 'Chưa có hóa đơn điện tử nào.';

// ── Settings ───────────────────────────────────────────────────────────────────
$lang['kt_einvoice_settings_title']          = 'Cài Đặt Hóa Đơn Điện Tử';
$lang['kt_einvoice_environment']             = 'Môi trường';
$lang['kt_einvoice_environment_sandbox']     = 'Sandbox (Thử nghiệm)';
$lang['kt_einvoice_environment_production']  = 'Production (Thật)';
$lang['kt_einvoice_api_username']            = 'Tài khoản SePay';
$lang['kt_einvoice_api_password']            = 'Mật khẩu SePay';
$lang['kt_einvoice_api_password_help']       = 'Nhập mật khẩu để cập nhật. Để trống nếu không thay đổi.';
$lang['kt_einvoice_test_connection']         = 'Kiểm Tra Kết Nối';
$lang['kt_einvoice_connection_ok']           = 'Kết nối thành công';
$lang['kt_einvoice_connection_fail']         = 'Kết nối thất bại';
$lang['kt_einvoice_provider_account']        = 'Nhà cung cấp hóa đơn';
$lang['kt_einvoice_provider_select']         = '-- Chọn nhà cung cấp --';
$lang['kt_einvoice_invoice_series']          = 'Ký hiệu hóa đơn';
$lang['kt_einvoice_invoice_template']        = 'Mẫu hóa đơn';
$lang['kt_einvoice_seller_info']             = 'Thông Tin Người Bán';
$lang['kt_einvoice_seller_tax_code']         = 'Mã số thuế (MST)';
$lang['kt_einvoice_seller_name']             = 'Tên công ty / cá nhân';
$lang['kt_einvoice_seller_address']          = 'Địa chỉ';
$lang['kt_einvoice_seller_phone']            = 'Số điện thoại';
$lang['kt_einvoice_seller_email']            = 'Email';
$lang['kt_einvoice_seller_bank_name']        = 'Tên ngân hàng';
$lang['kt_einvoice_seller_bank_account']     = 'Số tài khoản ngân hàng';
$lang['kt_einvoice_auto_issue_on_payment']   = 'Tự động phát hành khi thanh toán';
$lang['kt_einvoice_auto_issue_help']         = 'Tự động phát hành HĐĐT khi invoice được đánh dấu đã thanh toán.';
$lang['kt_einvoice_save_settings']           = 'Lưu Cài Đặt';
$lang['kt_einvoice_settings_saved']          = 'Đã lưu cài đặt thành công.';
$lang['kt_einvoice_settings_save_error']     = 'Không thể lưu cài đặt. Vui lòng thử lại.';
$lang['kt_einvoice_is_active']               = 'Kích hoạt tích hợp eInvoice';

// ── Invoice List ───────────────────────────────────────────────────────────────
$lang['kt_einvoice_list_title']              = 'Danh Sách Hóa Đơn Điện Tử';
$lang['kt_einvoice_col_invoice_number']      = 'Số HĐ điện tử';
$lang['kt_einvoice_col_perfex_invoice']      = 'Số Invoice CRM';
$lang['kt_einvoice_col_buyer']               = 'Người mua';
$lang['kt_einvoice_col_amount']              = 'Thành tiền';
$lang['kt_einvoice_col_status']              = 'Trạng thái';
$lang['kt_einvoice_col_issued_at']           = 'Ngày phát hành';
$lang['kt_einvoice_col_actions']             = 'Thao tác';
$lang['kt_einvoice_filter_all']              = 'Tất cả';
$lang['kt_einvoice_filter_draft']            = 'Nháp';
$lang['kt_einvoice_filter_issued']           = 'Đã phát hành';
$lang['kt_einvoice_filter_pending']          = 'Đang xử lý';
$lang['kt_einvoice_filter_failed']           = 'Thất bại';
$lang['kt_einvoice_filter_cancelled']        = 'Đã hủy';

// ── Statuses ───────────────────────────────────────────────────────────────────
$lang['kt_einvoice_status_pending_create']   = 'Đang tạo';
$lang['kt_einvoice_status_draft']            = 'Nháp';
$lang['kt_einvoice_status_pending_issue']    = 'Đang phát hành';
$lang['kt_einvoice_status_issued']           = 'Đã phát hành';
$lang['kt_einvoice_status_failed_create']    = 'Lỗi tạo';
$lang['kt_einvoice_status_failed_issue']     = 'Lỗi phát hành';
$lang['kt_einvoice_status_deleted']          = 'Đã xóa';
$lang['kt_einvoice_status_pending_cancel']   = 'Đang hủy';
$lang['kt_einvoice_status_cancelled']        = 'Đã hủy';
$lang['kt_einvoice_status_adjusting']        = 'Đang điều chỉnh';
$lang['kt_einvoice_status_adjusted']         = 'Đã điều chỉnh';

// ── Actions ────────────────────────────────────────────────────────────────────
$lang['kt_einvoice_btn_create_draft']        = 'Tạo Hóa Đơn Nháp';
$lang['kt_einvoice_btn_issue']               = 'Phát Hành HĐĐT';
$lang['kt_einvoice_btn_issue_confirm']       = 'Xác nhận phát hành hóa đơn điện tử?';
$lang['kt_einvoice_btn_delete']              = 'Xóa Hóa Đơn Nháp';
$lang['kt_einvoice_btn_delete_confirm']      = 'Xác nhận xóa hóa đơn nháp này?';
$lang['kt_einvoice_btn_cancel_invoice']      = 'Hủy Hóa Đơn';
$lang['kt_einvoice_btn_cancel_confirm']      = 'Xác nhận hủy hóa đơn đã phát hành?';
$lang['kt_einvoice_btn_download_pdf']        = 'Tải PDF';
$lang['kt_einvoice_btn_download_xml']        = 'Tải XML';
$lang['kt_einvoice_btn_retry']               = 'Thử Lại';
$lang['kt_einvoice_btn_check_status']        = 'Kiểm Tra Trạng Thái';
$lang['kt_einvoice_cancel_reason']           = 'Lý do hủy';
$lang['kt_einvoice_cancel_reason_placeholder'] = 'Nhập lý do hủy hóa đơn...';

// ── Batch Issue ────────────────────────────────────────────────────────────────
$lang['kt_einvoice_batch_title']             = 'Phát Hành Theo Lô';
$lang['kt_einvoice_batch_select_invoices']   = 'Chọn hóa đơn cần phát hành';
$lang['kt_einvoice_batch_selected_count']    = 'Đã chọn {count} hóa đơn';
$lang['kt_einvoice_batch_start']             = 'Bắt Đầu Phát Hành Lô';
$lang['kt_einvoice_batch_processing']        = 'Đang xử lý lô {session}...';
$lang['kt_einvoice_batch_completed']         = 'Hoàn thành: {success}/{total} thành công';
$lang['kt_einvoice_batch_max_exceeded']      = 'Vượt quá giới hạn {max} hóa đơn/lô.';
$lang['kt_einvoice_batch_no_eligible']       = 'Không có hóa đơn nào đủ điều kiện phát hành.';

// ── Quota ──────────────────────────────────────────────────────────────────────
$lang['kt_einvoice_quota_title']             = 'Hạn Mức Sử Dụng';
$lang['kt_einvoice_quota_month']             = 'Tháng {month}/{year}';
$lang['kt_einvoice_quota_unlimited']         = 'Không giới hạn';
$lang['kt_einvoice_quota_exceeded']          = 'Đã hết hạn mức phát hành HĐĐT tháng này.';
$lang['kt_einvoice_quota_warning']           = 'Còn {remaining} hóa đơn trong tháng này.';
$lang['kt_einvoice_quota_low_threshold']     = 10;

// ── Errors ─────────────────────────────────────────────────────────────────────
$lang['kt_einvoice_error_not_entitled']      = 'Gói dịch vụ của bạn không bao gồm tính năng Hóa Đơn Điện Tử.';
$lang['kt_einvoice_error_quota_exceeded']    = 'Hạn mức phát hành HĐĐT tháng này đã hết. Vui lòng liên hệ nâng cấp gói.';
$lang['kt_einvoice_error_not_configured']    = 'Chưa cấu hình tích hợp eInvoice. Vui lòng vào Cài Đặt để thiết lập.';
$lang['kt_einvoice_error_seller_incomplete'] = 'Thông tin người bán chưa đầy đủ. Vui lòng cập nhật trong Cài Đặt eInvoice.';
$lang['kt_einvoice_error_invalid_status']    = 'Trạng thái hóa đơn không cho phép thực hiện thao tác này.';
$lang['kt_einvoice_error_already_issued']    = 'Hóa đơn này đã được phát hành.';
$lang['kt_einvoice_error_already_exists']    = 'Đã tồn tại hóa đơn điện tử cho invoice này.';
$lang['kt_einvoice_error_api_failed']        = 'Gọi API SePay thất bại: {message}';
$lang['kt_einvoice_error_token_failed']      = 'Không thể lấy token xác thực SePay. Kiểm tra lại tài khoản/mật khẩu.';
$lang['kt_einvoice_error_download_failed']   = 'Không thể tải file hóa đơn.';
$lang['kt_einvoice_error_cancel_issued_only']= 'Chỉ có thể hủy hóa đơn đã phát hành.';
$lang['kt_einvoice_error_batch_not_enabled'] = 'Gói dịch vụ của bạn không hỗ trợ phát hành theo lô.';
$lang['kt_einvoice_error_permission']        = 'Bạn không có quyền thực hiện thao tác này.';

// ── Success ────────────────────────────────────────────────────────────────────
$lang['kt_einvoice_success_draft_created']   = 'Đã gửi yêu cầu tạo hóa đơn nháp. Hệ thống đang xử lý.';
$lang['kt_einvoice_success_issued']          = 'Đã gửi yêu cầu phát hành. Hóa đơn đang được xử lý bởi CQT.';
$lang['kt_einvoice_success_deleted']         = 'Đã xóa hóa đơn nháp.';
$lang['kt_einvoice_success_cancelled']       = 'Đã gửi yêu cầu hủy hóa đơn.';
$lang['kt_einvoice_success_batch_queued']    = 'Đã thêm {count} hóa đơn vào hàng chờ phát hành.';

// ── Invoice Detail ─────────────────────────────────────────────────────────────
$lang['kt_einvoice_detail_title']            = 'Chi Tiết Hóa Đơn Điện Tử';
$lang['kt_einvoice_detail_sepay_id']         = 'ID SePay';
$lang['kt_einvoice_detail_tracking']         = 'Tracking Code';
$lang['kt_einvoice_detail_invoice_num']      = 'Số hóa đơn (CQT)';
$lang['kt_einvoice_detail_series']           = 'Ký hiệu';
$lang['kt_einvoice_detail_template']         = 'Mẫu số';
$lang['kt_einvoice_detail_invoice_date']     = 'Ngày hóa đơn';
$lang['kt_einvoice_detail_issued_at']        = 'Thời điểm phát hành';
$lang['kt_einvoice_detail_seller']           = 'Người bán';
$lang['kt_einvoice_detail_buyer']            = 'Người mua';
$lang['kt_einvoice_detail_tax_code']         = 'Mã số thuế';
$lang['kt_einvoice_detail_total']            = 'Tổng tiền';
$lang['kt_einvoice_detail_tax_amount']       = 'Tiền thuế';
$lang['kt_einvoice_detail_attempts']         = 'Số lần thử';
$lang['kt_einvoice_detail_error']            = 'Thông báo lỗi';

// ── Permissions ────────────────────────────────────────────────────────────────
$lang['kt_einvoice_perm_view']               = 'Xem danh sách hóa đơn điện tử';
$lang['kt_einvoice_perm_create']             = 'Tạo hóa đơn điện tử nháp';
$lang['kt_einvoice_perm_issue']              = 'Phát hành hóa đơn lên CQT';
$lang['kt_einvoice_perm_delete']             = 'Xóa hóa đơn nháp';
$lang['kt_einvoice_perm_download']           = 'Tải hóa đơn PDF/XML';
$lang['kt_einvoice_perm_batch_issue']        = 'Phát hành theo lô';
$lang['kt_einvoice_perm_cancel']             = 'Hủy hóa đơn đã phát hành';
$lang['kt_einvoice_perm_configure']          = 'Cấu hình cài đặt eInvoice';
$lang['kt_einvoice_perm_view_reports']       = 'Xem báo cáo thống kê';
