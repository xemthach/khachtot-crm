<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$orderTypeMap = [
    'addon_purchase' => 'Mua thêm dịch vụ',
];
$statusMap = [
    'pending' => 'Đang chờ',
    'pending_payment' => 'Chờ thanh toán',
    'paid' => 'Đã thanh toán',
    'active' => 'Đang hoạt động',
    'inactive' => 'Ngưng hoạt động',
    'failed' => 'Thất bại',
    'completed' => 'Hoàn tất',
];
?>
<?php init_head(); ?><div id="wrapper"><div class="content"><div class="panel_s"><div class="panel-body"><h4><?php echo html_escape($title); ?></h4><div class="table-responsive"><table class="table table-striped"><thead><tr><th>ID</th><th>Mã đơn hàng</th><th>Loại đơn</th><th>Trạng thái</th><th>Thanh toán</th><th>Tổng tiền</th><th>Doanh nghiệp</th><th>Thao tác</th></tr></thead><tbody><?php foreach(($orders??[]) as $o){ ?><tr><td><?php echo (int)$o['id']; ?></td><td><?php echo html_escape($o['order_code']); ?></td><td><?php echo html_escape($orderTypeMap[$o['order_type']] ?? $o['order_type']); ?></td><td><?php echo html_escape($statusMap[$o['status']] ?? $o['status']); ?></td><td><?php echo html_escape($statusMap[$o['payment_status']] ?? $o['payment_status']); ?></td><td><?php echo html_escape($o['grand_total']); ?></td><td><?php echo html_escape($o['tenant_name'] ?? $o['tenant_code'] ?? $o['tenant_id']); ?></td><td><?php if(($o['payment_status']??'')!=='paid'){ ?><?php echo form_open(admin_url('kt_matbao_invoice/order_mark_paid/'.(int)$o['id']), ['style'=>'display:inline']); ?><button type="submit" class="btn btn-xs btn-success" onclick="return confirm('Xác nhận đơn hàng đã thanh toán và đưa vào hàng đợi cấp phát?');">Xác nhận đã thanh toán</button><?php echo form_close(); ?><?php }else{ ?><span class="label label-success">Đã thanh toán</span><?php } ?></td></tr><?php } ?></tbody></table></div></div></div></div></div><?php init_tail(); ?>
