<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$serviceLabels = [
    'einvoice' => 'Hóa đơn điện tử',
    'hsm_signature' => 'Chữ ký số',
];
$statusLabels = [
    'active' => 'Đang sử dụng',
    'pending' => 'Chờ xử lý',
    'pending_payment' => 'Chờ thanh toán',
    'provisioning' => 'Đang cấp phát',
    'paid' => 'Đã thanh toán',
    'failed' => 'Có lỗi',
    'expired' => 'Hết hạn',
    'cancelled' => 'Đã hủy',
    'completed' => 'Hoàn tất',
    'cert_archived' => 'Đã lưu hồ sơ kiểm thử',
];
?>
<div id="wrapper">
    <div class="content">
        <div class="panel_s">
            <div class="panel-body">
                <h4><?php echo html_escape($title); ?></h4>
                <p class="text-muted">Mua thêm gói hóa đơn điện tử hoặc chữ ký số và theo dõi trạng thái dịch vụ.</p>

                <div class="row">
                    <div class="col-md-3"><div class="well"><strong>Số hóa đơn điện tử đã mua</strong><br><?php echo (float) ($summary['einvoice_total'] ?? 0); ?></div></div>
                    <div class="col-md-3"><div class="well"><strong>Đã dùng</strong><br><?php echo (float) ($summary['einvoice_used'] ?? 0); ?></div></div>
                    <div class="col-md-3"><div class="well"><strong>Còn lại</strong><br><?php echo (float) ($summary['einvoice_remaining'] ?? 0); ?></div></div>
                    <div class="col-md-3"><div class="well"><strong>Chữ ký số đang hoạt động</strong><br><?php echo (int) ($summary['hsm_active'] ?? 0); ?></div></div>
                </div>

                <h5>Gói đang mở bán</h5>
                <?php if (empty($packages)) { ?>
                    <div class="alert alert-warning">Hiện chưa có gói dịch vụ bổ sung công khai. Vui lòng liên hệ đội vận hành.</div>
                <?php } else { ?>
                    <?php echo form_open(admin_url('kt_matbao_invoice/tenant/buy_addons')); ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Gói</th>
                                    <th>Dịch vụ</th>
                                    <th>Số lượng/gói</th>
                                    <th>Giá/gói</th>
                                    <th>Số gói mua</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($packages as $pkg) { ?>
                                    <tr>
                                        <td><?php echo html_escape($pkg['package_name'] ?? $pkg['package_code']); ?></td>
                                        <td><?php echo html_escape($serviceLabels[$pkg['service_type'] ?? ''] ?? ($pkg['service_type'] ?? '-')); ?></td>
                                        <td><?php echo app_format_number((float) ($pkg['quantity'] ?? 0)); ?></td>
                                        <td><?php echo app_format_money((float) ($pkg['price'] ?? 0), ($pkg['currency'] ?? 'VND'), true) . ' ' . html_escape($pkg['currency'] ?? 'VND'); ?></td>
                                        <td><input type="number" min="0" step="1" class="form-control" name="items[<?php echo (int) $pkg['id']; ?>]" value="0"></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted">Lưu ý: số gói mua là số lượng gói bổ sung, hệ thống sẽ tự tính tổng số lượng sử dụng được.</p>
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Tạo đơn mua dịch vụ bổ sung?');">Tạo đơn mua dịch vụ bổ sung</button>
                    <?php echo form_close(); ?>
                <?php } ?>

                <hr>
                <h5>Đơn dịch vụ bổ sung gần đây</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead><tr><th>STT</th><th>Mã đơn</th><th>Trạng thái</th><th>Thanh toán</th><th>Tổng tiền</th><th>Thao tác</th></tr></thead>
                        <tbody>
                            <?php foreach (($orders ?? []) as $o) { ?>
                                <tr>
                                    <td><?php echo (int) $o['id']; ?></td>
                                    <td><?php echo html_escape($o['order_code']); ?></td>
                                    <td><?php echo html_escape($statusLabels[$o['status'] ?? ''] ?? ($o['status'] ?? '-')); ?></td>
                                    <td><?php echo html_escape($statusLabels[$o['payment_status'] ?? ''] ?? ($o['payment_status'] ?? '-')); ?></td>
                                    <td><?php echo app_format_money((float) ($o['grand_total'] ?? 0), ($o['currency'] ?? 'VND'), true) . ' ' . html_escape($o['currency'] ?? 'VND'); ?></td>
                                    <td>
                                        <a class="btn btn-default btn-sm" href="<?php echo admin_url('kt_matbao_invoice/tenant/order/' . (int) $o['id']); ?>">Chi tiết</a>
                                        <?php if (($o['payment_status'] ?? '') !== 'paid') { ?>
                                            <?php echo form_open(admin_url('kt_matbao_invoice/tenant/pay_order/' . (int) $o['id']), ['style' => 'display:inline-block']); ?>
                                            <button type="submit" class="btn btn-primary btn-sm">Thanh toán</button>
                                            <?php echo form_close(); ?>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <hr>
                <h5>Dịch vụ hiện có</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead><tr><th>STT</th><th>Dịch vụ</th><th>Gói</th><th>Trạng thái</th><th>Đã mua</th><th>Đã dùng</th><th>Còn lại</th><th>Bắt đầu</th><th>Hết hạn</th></tr></thead>
                        <tbody>
                            <?php foreach (($addons ?? []) as $a) { ?>
                                <tr>
                                    <td><?php echo (int) $a['id']; ?></td>
                                    <td><?php echo html_escape($serviceLabels[$a['service_type'] ?? ''] ?? ($a['service_type'] ?? '-')); ?></td>
                                    <td><?php echo html_escape($a['package_name'] ?? $a['package_code']); ?></td>
                                    <td><?php echo html_escape($statusLabels[$a['status'] ?? ''] ?? ($a['status'] ?? '-')); ?></td>
                                    <td><?php echo app_format_number((float) ($a['quantity_purchased'] ?? 0)); ?></td>
                                    <td><?php echo app_format_number((float) ($a['quantity_used'] ?? 0)); ?></td>
                                    <td><?php echo app_format_number((float) ($a['quantity_remaining'] ?? 0)); ?></td>
                                    <td><?php echo html_escape($a['starts_at']); ?></td>
                                    <td><?php echo html_escape($a['ends_at']); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
