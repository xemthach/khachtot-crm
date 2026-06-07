<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$statusMap = [
    'pending' => 'Đang chờ',
    'running' => 'Đang xử lý',
    'paid' => 'Đã thanh toán',
    'active' => 'Đang hoạt động',
    'failed' => 'Thất bại',
    'completed' => 'Hoàn tất',
    'done' => 'Hoàn tất',
];
$serviceTypeMap = [
    'hsm_signature' => 'Chữ ký số HSM',
    'einvoice' => 'Hóa đơn điện tử',
];
?>
<?php init_head(); ?><div id="wrapper"><div class="content"><div class="panel_s"><div class="panel-body"><h4><?php echo html_escape($title); ?></h4><div class="table-responsive"><table class="table table-striped"><thead><tr><th>ID</th><th>Loại công việc</th><th>Trạng thái</th><th>Nhà cung cấp</th><th>Dịch vụ</th><th>Doanh nghiệp</th><th>Số lần thử</th></tr></thead><tbody><?php foreach(($jobs??[]) as $j){ ?><tr><td><?php echo (int)$j['id']; ?></td><td><?php echo html_escape($j['job_type']); ?></td><td><?php echo html_escape($statusMap[$j['status']] ?? $j['status']); ?></td><td><?php echo html_escape($j['provider']); ?></td><td><?php echo html_escape($serviceTypeMap[$j['service_type']] ?? $j['service_type']); ?></td><td><?php echo html_escape($j['tenant_name'] ?? $j['tenant_code'] ?? $j['tenant_id']); ?></td><td><?php echo html_escape($j['attempts']); ?></td></tr><?php } ?></tbody></table></div></div></div></div></div><?php init_tail(); ?>
