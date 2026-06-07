<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$statusLabels = [
    'pending' => 'Chờ thanh toán',
    'partial' => 'Thanh toán một phần',
    'paid' => 'Đã thanh toán',
    'expired' => 'Đã hết hạn',
    'cancelled' => 'Đã hủy',
    'failed' => 'Lỗi thanh toán',
    'matched' => 'Đã đối soát',
    'unmatched' => 'Chưa xác định',
];
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8">
                <div class="panel_s">
                    <div class="panel-body text-center">
                        <h4 class="no-margin"><?php echo _l('kt_sepay_pay_with_sepay'); ?></h4>
                        <hr class="hr-panel-heading" />
                        <p><strong><?php echo _l('kt_sepay_status'); ?>:</strong> <span class="label label-<?php echo kt_sepay_status_badge_class($request['status']); ?>"><?php echo html_escape($statusLabels[(string) ($request['status'] ?? '')] ?? kt_sepay_status_label($request['status'])); ?></span></p>
                        <img src="<?php echo html_escape($request['qr_url']); ?>" alt="Mã QR thanh toán" class="img-responsive center-block" style="max-width:320px;">
                        <hr />
                        <p><strong><?php echo _l('kt_sepay_bank'); ?>:</strong> <?php echo html_escape($settings['bank_code'] ?? ''); ?></p>
                        <p><strong><?php echo _l('kt_sepay_account'); ?>:</strong> <code><?php echo html_escape($settings['account_number'] ?? ''); ?></code></p>
                        <p><strong><?php echo _l('kt_sepay_amount'); ?>:</strong> <?php echo html_escape(number_format((float) $request['amount'], 0, '.', ',')); ?> VND</p>
                        <p><strong>Nội dung chuyển khoản:</strong> <?php echo html_escape($request['reference_code']); ?></p>
                        <p><a href="<?php echo admin_url('kt_saas/tenant_billing'); ?>" class="btn btn-default"><?php echo _l('kt_sepay_back_to_billing'); ?></a></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo _l('kt_sepay_transaction_history'); ?></h4>
                        <hr class="hr-panel-heading" />
                        <?php if (!empty($transactions)) { ?>
                            <?php foreach ($transactions as $row) { ?>
                                <div class="well well-sm">
                                    <strong>SePay</strong><br>
                                    <?php echo _l('kt_sepay_amount'); ?>: <?php echo html_escape($row['transfer_amount']); ?><br>
                                    <?php echo _l('kt_sepay_status'); ?>: <span class="label label-<?php echo kt_sepay_status_badge_class($row['status']); ?>"><?php echo html_escape($statusLabels[(string) ($row['status'] ?? '')] ?? kt_sepay_status_label($row['status'])); ?></span><br>
                                    Nội dung chuyển khoản: <?php echo html_escape($row['reference_code']); ?>
                                </div>
                            <?php } ?>
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
