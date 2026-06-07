<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$statusLabels = [
    'pending' => 'Chờ xử lý',
    'matched' => 'Đã đối soát',
    'paid' => 'Đã thanh toán',
    'unmatched' => 'Chưa xác định',
    'failed' => 'Có lỗi',
    'ignored' => 'Đã bỏ qua',
];
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo html_escape($title ?? _l('kt_sepay_transactions')); ?></h4>
                        <hr class="hr-panel-heading" />
                        <?php if (!empty($transactions)) { ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>STT</th>
                                            <th>Nguồn thanh toán</th>
                                            <th><?php echo _l('kt_sepay_amount'); ?></th>
                                            <th><?php echo _l('kt_sepay_content'); ?></th>
                                            <th><?php echo _l('kt_sepay_status'); ?></th>
                                            <th>Nội dung chuyển khoản</th>
                                            <th><?php echo _l('kt_sepay_created_at'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $index => $row) { ?>
                                            <tr>
                                                <td><?php echo (int) $index + 1; ?></td>
                                                <td>SePay</td>
                                                <td><?php echo html_escape(number_format((float) $row['transfer_amount'], 0, '.', ',')); ?></td>
                                                <td><?php echo html_escape($row['content']); ?></td>
                                                <td><span class="label label-<?php echo kt_sepay_status_badge_class($row['status']); ?>"><?php echo html_escape($statusLabels[(string) ($row['status'] ?? '')] ?? kt_sepay_status_label($row['status'])); ?></span></td>
                                                <td><?php echo html_escape($row['reference_code'] ?: '-'); ?></td>
                                                <td><?php echo html_escape(_dt((string) $row['created_at'])); ?></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <p class="text-muted"><?php echo _l('kt_sepay_no_transaction_received'); ?></p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
