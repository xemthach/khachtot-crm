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
            <div class="col-md-4">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo _l('kt_sepay'); ?></h4>
                        <hr class="hr-panel-heading" />
                        <p class="text-muted"><?php echo _l('kt_sepay_tenant_dashboard_help'); ?></p>
                        <p><strong><?php echo _l('kt_sepay_status'); ?>:</strong> <span class="label label-<?php echo !empty($settings['is_active']) ? 'success' : 'default'; ?>"><?php echo !empty($settings['is_active']) ? _l('kt_sepay_active') : _l('kt_sepay_not_checked_yet'); ?></span></p>
                        <p><strong><?php echo _l('kt_sepay_bank'); ?>:</strong> <?php echo html_escape($settings['bank_code'] ?? ''); ?></p>
                        <p><strong><?php echo _l('kt_sepay_account'); ?>:</strong> <code><?php echo html_escape($settings['account_number'] ?? ''); ?></code></p>
                        <p><a href="<?php echo admin_url('kt_sepay/tenant_settings'); ?>" class="btn btn-default"><?php echo _l('kt_sepay_settings'); ?></a></p>
                        <p><a href="<?php echo admin_url('kt_sepay/tenant_payment_requests'); ?>" class="btn btn-default"><?php echo _l('kt_sepay_payment_requests'); ?></a></p>
                        <?php if (!empty($latest_open_request)) { ?>
                            <a href="<?php echo admin_url('kt_sepay/tenant_payment/' . (int) $latest_open_request['id']); ?>" class="btn btn-primary">
                                <?php echo _l('kt_sepay_open_payment_qr'); ?>
                            </a>
                        <?php } else { ?>
                            <a href="<?php echo admin_url('kt_saas/tenant_billing'); ?>" class="btn btn-default">
                                <?php echo _l('kt_sepay_back_to_billing'); ?>
                            </a>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo _l('kt_sepay_payment_requests'); ?></h4>
                        <hr class="hr-panel-heading" />
                        <?php if ($canCreateManualRequests) { ?>
                            <p><a href="<?php echo admin_url('kt_sepay/tenant_payment_requests'); ?>" class="btn btn-primary"><?php echo _l('kt_sepay_manual_request_create'); ?></a></p>
                        <?php } ?>
                        <?php if (!empty($requests)) { ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Nội dung chuyển khoản</th>
                                            <th>Loại thanh toán</th>
                                            <th><?php echo _l('kt_sepay_amount'); ?></th>
                                            <th><?php echo _l('kt_sepay_status'); ?></th>
                                            <th><?php echo _l('kt_sepay_created_at'); ?></th>
                                            <th><?php echo _l('kt_sepay_action'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($requests as $request) { ?>
                                            <?php $isPayable = in_array((string) ($request['status'] ?? ''), ['pending', 'partial'], true); ?>
                                            <tr>
                                                <td><?php echo html_escape($request['reference_code']); ?></td>
                                                <td><?php echo html_escape($requestTypeLabels[(string) ($request['context_type'] ?? 'manual')] ?? 'Thanh toán thủ công'); ?></td>
                                                <td><?php echo html_escape(number_format((float) $request['amount'], 0, '.', ',')); ?> <?php echo html_escape($request['currency'] ?? 'VND'); ?></td>
                                                <td><span class="label label-<?php echo kt_sepay_status_badge_class($request['status']); ?>"><?php echo html_escape($statusLabels[(string) ($request['status'] ?? '')] ?? kt_sepay_status_label($request['status'])); ?></span></td>
                                                <td><?php echo html_escape(_dt((string) $request['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($isPayable) { ?>
                                                        <a href="<?php echo admin_url('kt_sepay/tenant_payment/' . (int) $request['id']); ?>" class="btn btn-default btn-sm">
                                                            <?php echo _l('kt_sepay_open_payment_qr'); ?>
                                                        </a>
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
