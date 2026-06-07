<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$invoiceStatusLabels = [
    'draft' => 'Nháp',
    'pending_payment' => 'Chờ thanh toán',
    'overdue' => 'Quá hạn',
    'paid' => 'Đã thanh toán',
    'cancelled' => 'Đã hủy',
    'failed' => 'Có lỗi',
];
$paymentStatusLabels = [
    'pending' => 'Chờ xử lý',
    'paid' => 'Đã thanh toán',
    'failed' => 'Có lỗi',
    'cancelled' => 'Đã hủy',
    'refunded' => 'Đã hoàn tiền',
];
$gatewayLabels = [
    'sepay' => 'SePay VietQR',
    'manual' => 'Thanh toán thủ công',
    'bank_transfer' => 'Chuyển khoản ngân hàng',
];
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mb-2"><?php echo html_escape($title); ?></h4>
                <p class="text-muted"><?php echo html_escape($tenant['company_name'] ?? ''); ?></p>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4>Hóa đơn dịch vụ</h4>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('kt_saas_invoice_number'); ?></th>
                                        <th><?php echo _l('kt_saas_invoice_type'); ?></th>
                                        <th><?php echo _l('kt_saas_status'); ?></th>
                                        <th><?php echo _l('kt_saas_amount'); ?></th>
                                        <th><?php echo _l('kt_saas_due_date'); ?></th>
                                        <th><?php echo _l('kt_saas_paid_at'); ?></th>
                                        <th><?php echo _l('kt_saas_actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoices as $invoice) { ?>
                                        <tr>
                                            <td><?php echo html_escape($invoice['invoice_number']); ?></td>
                                            <td><?php echo html_escape(kt_saas_invoice_reason_label($invoice)); ?></td>
                                            <td><span class="label label-<?php echo kt_saas_status_badge_class($invoice['status']); ?>"><?php echo html_escape($invoiceStatusLabels[$invoice['status']] ?? $invoice['status']); ?></span></td>
                                            <td><?php echo app_format_money((float) $invoice['grand_total'], $invoice['currency'], true) . ' ' . html_escape($invoice['currency']); ?></td>
                                            <td><?php echo !empty($invoice['due_date']) ? html_escape($invoice['due_date']) : '-'; ?></td>
                                            <td><?php echo !empty($invoice['paid_at']) ? _dt($invoice['paid_at']) : '-'; ?></td>
                                            <td>
                                                <?php if (!empty($invoice['checkout_url'])) { ?>
                                                    <a href="<?php echo html_escape($invoice['checkout_url']); ?>" class="btn btn-default btn-sm" target="_blank" rel="noopener">
                                                        <?php echo _l('kt_saas_pay_now'); ?>
                                                    </a>
                                                    <?php if (!empty($sepay_enabled) && !empty($invoice['sepay_url'])) { ?>
                                                        <a href="<?php echo html_escape($invoice['sepay_url']); ?>" class="btn btn-primary btn-sm">
                                                            SePay VietQR
                                                        </a>
                                                    <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    <?php if (empty($invoices)) { ?>
                                        <tr><td colspan="7"><?php echo _l('kt_saas_no_records'); ?></td></tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4>Lịch sử thanh toán</h4>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Nội dung thanh toán</th>
                                        <th>Hình thức thanh toán</th>
                                        <th><?php echo _l('kt_saas_status'); ?></th>
                                        <th><?php echo _l('kt_saas_amount'); ?></th>
                                        <th><?php echo _l('kt_saas_paid_at'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment) { ?>
                                        <tr>
                                            <td><?php echo html_escape($payment['payment_reference']); ?></td>
                                            <td><?php echo html_escape($gatewayLabels[$payment['gateway']] ?? $payment['gateway']); ?></td>
                                            <td><span class="label label-<?php echo kt_saas_status_badge_class($payment['status']); ?>"><?php echo html_escape($paymentStatusLabels[$payment['status']] ?? $payment['status']); ?></span></td>
                                            <td><?php echo app_format_money((float) $payment['amount'], $payment['currency'], true) . ' ' . html_escape($payment['currency']); ?></td>
                                            <td><?php echo !empty($payment['paid_at']) ? _dt($payment['paid_at']) : '-'; ?></td>
                                        </tr>
                                    <?php } ?>
                                    <?php if (empty($payments)) { ?>
                                        <tr><td colspan="5"><?php echo _l('kt_saas_no_records'); ?></td></tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
