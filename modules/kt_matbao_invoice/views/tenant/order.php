<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$statusLabels = [
    'pending' => 'Chờ xử lý',
    'pending_payment' => 'Chờ thanh toán',
    'provisioning' => 'Đang cấp phát',
    'paid' => 'Đã thanh toán',
    'failed' => 'Có lỗi',
    'cancelled' => 'Đã hủy',
    'completed' => 'Hoàn tất',
];
$itemTypeLabels = [
    'einvoice' => 'Hóa đơn điện tử',
    'hsm_signature' => 'Chữ ký số',
    'addon_einvoice' => 'Hóa đơn điện tử',
    'addon_hsm' => 'Chữ ký số HSM',
    'manual' => 'Dịch vụ bổ sung',
];
?>
<div id="wrapper">
    <div class="content">
        <div class="panel_s">
            <div class="panel-body">
                <h4><?php echo html_escape($title); ?></h4>
                <p><strong>Mã đơn:</strong> <?php echo html_escape($order['order_code']); ?></p>
                <p>
                    <strong>Trạng thái:</strong> <?php echo html_escape($statusLabels[$order['status'] ?? ''] ?? ($order['status'] ?? '-')); ?>
                    |
                    <strong>Thanh toán:</strong> <?php echo html_escape($statusLabels[$order['payment_status'] ?? ''] ?? ($order['payment_status'] ?? '-')); ?>
                </p>
                <p><strong>Tổng tiền:</strong> <?php echo html_escape($order['grand_total'] . ' ' . $order['currency']); ?></p>

                <?php if (($order['payment_status'] ?? '') !== 'paid') { ?>
                    <div class="alert alert-info">Đơn đang chờ thanh toán cho Khách Tốt, đơn vị cung cấp dịch vụ. Thông tin nhận tiền sẽ được hiển thị ở bước tiếp theo.</div>
                    <?php echo form_open(admin_url('kt_matbao_invoice/tenant/pay_order/' . (int) $order['id']), ['style' => 'display:inline-block;margin-bottom:15px']); ?>
                    <button type="submit" class="btn btn-primary">Thanh toán qua SePay</button>
                    <?php echo form_close(); ?>
                <?php } ?>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Hạng mục</th>
                                <th>Dịch vụ</th>
                                <th>Số lượng</th>
                                <th>Đơn giá</th>
                                <th>Tổng tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($items ?? []) as $it) { ?>
                                <tr>
                                    <td><?php echo html_escape($it['item_name']); ?></td>
                                    <td><?php echo html_escape($itemTypeLabels[$it['item_type'] ?? ''] ?? ($it['item_type'] ?? '-')); ?></td>
                                    <td><?php echo html_escape($it['quantity']); ?></td>
                                    <td><?php echo html_escape($it['unit_price']); ?></td>
                                    <td><?php echo html_escape($it['total']); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <p><a class="btn btn-default" href="<?php echo admin_url('kt_matbao_invoice/tenant/addons'); ?>">Quay lại dịch vụ bổ sung</a></p>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
