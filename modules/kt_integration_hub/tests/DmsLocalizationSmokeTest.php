<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

require_once dirname(__DIR__) . '/helpers/kt_integration_hub_helper.php';

$expectations = [
    ['order', 'submitted', 'Đã gửi'],
    ['payment', 'partial_paid', 'Thanh toán một phần'],
    ['sync', 'failed', 'Lỗi đồng bộ'],
    ['tracking', 'stale', 'Dữ liệu cũ'],
    ['marker', 'captured_order', 'Vị trí gửi đơn hàng'],
    ['feedback', 'in_review', 'Đang xử lý'],
];
foreach ($expectations as [$domain, $status, $label]) {
    if (kt_dms_ui_status_label($domain, $status) !== $label) {
        throw new RuntimeException('Unexpected localized label for ' . $domain . ':' . $status);
    }
}
if (kt_dms_ui_status_label('order', 'future_internal_enum') !== 'Chưa xác định') {
    throw new RuntimeException('Unknown enum leaked to customer-facing output.');
}
if (kt_dms_ui_money(24000) !== '24.000 ₫' || kt_dms_ui_money(null) !== 'Chưa có dữ liệu') {
    throw new RuntimeException('Vietnamese money formatting contract failed.');
}

echo "DmsLocalizationSmokeTest passed.\n";
