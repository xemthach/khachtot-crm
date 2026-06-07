<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$statusLabels = [
    'draft' => 'Nháp',
    'issued' => 'Đã phát hành',
    'signed' => 'Đã ký',
    'synced' => 'Đã đồng bộ',
    'failed' => 'Có lỗi',
    'cancelled' => 'Đã hủy',
    'pending' => 'Chờ xử lý',
];
?>
<div id="wrapper">
    <div class="content">
        <div class="panel_s">
            <div class="panel-body">
                <h4><?php echo html_escape($title); ?></h4>
                <p class="text-muted">Doanh nghiệp: <?php echo html_escape($tenant['company_name'] ?? ''); ?></p>
                <p>
                    <a class="btn btn-default btn-sm" href="<?php echo admin_url('kt_matbao_invoice/tenant/addons'); ?>">Mua gói hóa đơn / chữ ký số</a>
                    <a class="btn btn-default btn-sm" href="<?php echo admin_url('kt_matbao_invoice/tenant/usage'); ?>">Xem giới hạn sử dụng</a>
                    <?php if (function_exists('kt_matbao_invoice_tenant_can_configure') && kt_matbao_invoice_tenant_can_configure()) { ?>
                        <a class="btn btn-default btn-sm" href="<?php echo admin_url('kt_matbao_invoice/tenant/settings'); ?>">Cấu hình tài khoản</a>
                    <?php } ?>
                </p>

                <?php
                $perfexInvoices = $perfex_invoices ?? [];
                $records = $records ?? [];
                ?>

                <h5>Hóa đơn CRM có thể phát hành</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead><tr><th>STT</th><th>Số hóa đơn</th><th>Khách hàng</th><th>Tổng tiền</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                        <tbody>
                            <?php foreach ($perfexInvoices as $inv) { ?>
                                <tr>
                                    <td><?php echo (int) $inv['id']; ?></td>
                                    <td><?php echo html_escape($inv['number']); ?></td>
                                    <td><?php echo html_escape($inv['clientid']); ?></td>
                                    <td><?php echo app_format_money((float) ($inv['total'] ?? 0), (string) ($inv['currency_name'] ?? 'VND'), true); ?></td>
                                    <td><?php echo html_escape($statusLabels[$inv['status'] ?? ''] ?? ($inv['status'] ?? '-')); ?></td>
                                    <td>
                                        <?php echo form_open(admin_url('kt_matbao_invoice/tenant/create_draft/' . (int) $inv['id']), ['style' => 'display:inline-block']); ?>
                                            <button type="submit" class="btn btn-default btn-sm">Tạo nháp</button>
                                        <?php echo form_close(); ?>
                                        <?php echo form_open(admin_url('kt_matbao_invoice/tenant/issue/' . (int) $inv['id']), ['style' => 'display:inline-block', 'onsubmit' => "return confirm('Phát hành hóa đơn ngay?');"]); ?>
                                            <button type="submit" class="btn btn-primary btn-sm">Phát hành</button>
                                        <?php echo form_close(); ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <h5>Hóa đơn điện tử đã ghi nhận</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead><tr><th>STT</th><th>Nguồn</th><th>Mã hóa đơn</th><th>Mã tra cứu</th><th>Trạng thái</th><th>Số tiền</th><th>Thao tác</th></tr></thead>
                        <tbody>
                            <?php foreach ($records as $row) { ?>
                                <tr>
                                    <td><?php echo (int) $row['id']; ?></td>
                                    <td><?php echo html_escape($row['source_type']); ?>#<?php echo html_escape($row['source_id']); ?></td>
                                    <td><?php echo html_escape($row['ma_so_hdon']); ?></td>
                                    <td><?php echo html_escape($row['ma_tra_cuu']); ?></td>
                                    <td><?php echo html_escape($statusLabels[$row['local_status'] ?? ''] ?? ($row['local_status'] ?? '-')); ?></td>
                                    <td><?php echo app_format_money((float) ($row['total_amount'] ?? 0), 'VND', true); ?></td>
                                    <td>
                                        <?php echo form_open(admin_url('kt_matbao_invoice/tenant/sync_status/' . (int) $row['id']), ['style' => 'display:inline-block']); ?>
                                            <button type="submit" class="btn btn-default btn-sm">Đồng bộ</button>
                                        <?php echo form_close(); ?>
                                        <a class="btn btn-default btn-sm" href="<?php echo admin_url('kt_matbao_invoice/tenant/download/' . (int) $row['id'] . '/pdf'); ?>">PDF</a>
                                        <a class="btn btn-default btn-sm" href="<?php echo admin_url('kt_matbao_invoice/tenant/download/' . (int) $row['id'] . '/xml'); ?>">XML</a>
                                    </td>
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
