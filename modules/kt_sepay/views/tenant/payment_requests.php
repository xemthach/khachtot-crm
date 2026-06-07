<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php $canCreateManualRequests = array_key_exists('can_create_manual_requests', get_defined_vars()) ? !empty($can_create_manual_requests) : true; ?>
<?php
$requestTypeLabels = [
    'invoice' => 'Thanh toán hóa đơn',
    'subscription' => 'Thanh toán gói CRM',
    'manual' => 'Thanh toán thủ công',
];
$statusLabels = [
    'pending' => 'Chờ thanh toán',
    'partial' => 'Thanh toán một phần',
    'paid' => 'Đã thanh toán',
    'expired' => 'Đã hết hạn',
    'cancelled' => 'Đã hủy',
    'failed' => 'Lỗi thanh toán',
];
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo html_escape($title ?? _l('kt_sepay_payment_requests')); ?></h4>
                        <hr class="hr-panel-heading" />
                        <div class="row">
                            <div class="col-md-5">
                                <h5>Tạo yêu cầu thanh toán</h5>
                                <?php if (!$canCreateManualRequests) { ?>
                                    <div class="alert alert-warning">Gói hiện tại chưa hỗ trợ tạo yêu cầu thanh toán thủ công.</div>
                                <?php } ?>
                                <?php echo form_open(admin_url('kt_sepay/tenant_create_payment_request')); ?>
                                <?php echo render_input('amount', 'kt_sepay_manual_request_amount', '', 'number', ['min' => 1, 'step' => '0.01', 'required' => true, 'disabled' => !$canCreateManualRequests]); ?>
                                <?php echo render_input('description', 'kt_sepay_manual_request_description', '', 'text', ['disabled' => !$canCreateManualRequests]); ?>
                                <?php echo render_input('expiry_minutes', 'kt_sepay_manual_request_expiry', $settings['payment_request_expiry_minutes'] ?? 60, 'number', ['min' => 5, 'disabled' => !$canCreateManualRequests]); ?>
                                <button type="submit" class="btn btn-primary" <?php echo !$canCreateManualRequests ? 'disabled' : ''; ?>><?php echo _l('kt_sepay_manual_request_create'); ?></button>
                                <?php echo form_close(); ?>
                            </div>
                            <div class="col-md-7">
                                <div class="well well-sm mtop25">
                                    <p class="no-margin"><strong><?php echo _l('kt_sepay_status'); ?>:</strong> <span class="label label-<?php echo !empty($settings['is_active']) ? 'success' : 'default'; ?>"><?php echo !empty($settings['is_active']) ? _l('kt_sepay_active') : _l('kt_sepay_not_checked_yet'); ?></span></p>
                                    <p class="mtop10 no-margin"><strong><?php echo _l('kt_sepay_bank'); ?>:</strong> <?php echo html_escape($settings['bank_code'] ?? ''); ?></p>
                                    <p class="mtop10 no-margin"><strong><?php echo _l('kt_sepay_account'); ?>:</strong> <code><?php echo html_escape($settings['account_number'] ?? ''); ?></code></p>
                                </div>
                            </div>
                        </div>
                        <hr />
                        <?php if (!empty($requests)) { ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>STT</th>
                                            <th>Loại thanh toán</th>
                                            <th>Nội dung chuyển khoản</th>
                                            <th><?php echo _l('kt_sepay_amount'); ?></th>
                                            <th><?php echo _l('kt_sepay_status'); ?></th>
                                            <th><?php echo _l('kt_sepay_expires_at'); ?></th>
                                            <th><?php echo _l('kt_sepay_action'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($requests as $index => $row) { ?>
                                            <?php $isPayable = in_array((string) ($row['status'] ?? ''), ['pending', 'partial'], true); ?>
                                            <tr>
                                                <td><?php echo (int) $index + 1; ?></td>
                                                <td><?php echo html_escape($requestTypeLabels[(string) ($row['context_type'] ?? 'manual')] ?? 'Thanh toán thủ công'); ?></td>
                                                <td><?php echo html_escape($row['reference_code']); ?></td>
                                                <td><?php echo html_escape(number_format((float) $row['amount'], 0, '.', ',')); ?> <?php echo html_escape($row['currency']); ?></td>
                                                <td><span class="label label-<?php echo kt_sepay_status_badge_class($row['status']); ?>"><?php echo html_escape($statusLabels[(string) ($row['status'] ?? '')] ?? kt_sepay_status_label($row['status'])); ?></span></td>
                                                <td><?php echo html_escape($row['expires_at'] ? _dt((string) $row['expires_at']) : '-'); ?></td>
                                                <td>
                                                    <?php if ($isPayable) { ?>
                                                        <a href="<?php echo admin_url('kt_sepay/tenant_payment/' . (int) $row['id']); ?>" class="btn btn-default btn-sm"><?php echo _l('kt_sepay_open_payment_qr'); ?></a>
                                                    <?php } else { ?>
                                                        <span class="text-muted"><?php echo _l('kt_sepay_no_action_required'); ?></span>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <p class="text-muted"><?php echo _l('kt_sepay_tenant_no_requests'); ?></p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>

