<?php defined('BASEPATH') or exit('No direct script access allowed'); init_head(); ?>
<div id="wrapper"><div class="content kt-misa-admin"><div class="row"><div class="col-md-8"><h1 class="h3">Chưa đủ điều kiện kết nối thử với MISA</h1><p class="text-muted">Trang này chỉ hiển thị checklist. Không có thao tác gửi request thật.</p></div><div class="col-md-4 text-right"><a class="btn btn-default" href="<?php echo admin_url('misa-amis/readiness'); ?>">Xem checklist</a> <a class="btn btn-default" href="<?php echo admin_url('kt_misa_amis_accounting/connections'); ?>">Quay lại thiết lập</a></div></div>
<div class="alert alert-danger misa-blocked-banner"><strong>Kiểm tra kết nối thật đang bị khóa.</strong><br>Dòng sản phẩm, URL kiểm thử, đăng ký URL phản hồi, thông tin bí mật và phê duyệt kiểm thử chưa được xác nhận.</div>
<ul><?php foreach ((array) $readiness['requirements'] as $requirement) if (($requirement['status'] ?? '') !== 'PASS') { ?><li><?php echo html_escape($requirement['name']); ?>: <?php echo html_escape($requirement['blocker']); ?></li><?php } ?></ul>
<p><strong>Trạng thái phê duyệt:</strong> Chưa cấp · <strong>Thông tin bí mật:</strong> Chưa được phê duyệt</p>
<p class="text-muted">Mô phỏng/runtime nội bộ vẫn có thể dùng tại trang Thiết lập kết nối. Mô phỏng thành công không có nghĩa là đã kết nối MISA.</p>
<p><code>ACT_AMIS_CONFIRMATION_PACKAGE</code> · <code>EXTERNAL_TEST_ENTRY_GATE</code> · <code>EXTERNAL_TEST_AUTHORIZATION</code></p>
</div></div><?php init_tail(); ?>
