<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php $canRunReconcile = array_key_exists('can_run_reconcile', get_defined_vars()) ? !empty($can_run_reconcile) : true; ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">Đối soát thanh toán</h4>
                        <hr class="hr-panel-heading" />
                        <div class="row mbot15">
                            <div class="col-md-3"><div class="well well-sm">Số lần đối soát: <strong><?php echo (int) ($summary['total_runs'] ?? 0); ?></strong></div></div>
                            <div class="col-md-3"><div class="well well-sm">Giao dịch nhận: <strong><?php echo (int) ($summary['total_fetched'] ?? 0); ?></strong></div></div>
                            <div class="col-md-3"><div class="well well-sm">Giao dịch xử lý: <strong><?php echo (int) ($summary['total_processed'] ?? 0); ?></strong></div></div>
                            <div class="col-md-3"><div class="well well-sm">Lỗi cần kiểm tra: <strong><?php echo (int) ($summary['total_errors'] ?? 0); ?></strong></div></div>
                        </div>
                        <p><strong>Lần đối soát gần nhất:</strong> <?php echo html_escape($last_reconcile_at ?: _l('kt_sepay_not_checked_yet')); ?></p>
                        <p><strong>Giao dịch gần nhất:</strong> <?php echo html_escape($last_reconcile_transaction_id ?: '-'); ?></p>
                        <?php if (!$canRunReconcile) { ?>
                            <div class="alert alert-warning">Gói hiện tại chưa hỗ trợ đối soát thủ công.</div>
                        <?php } ?>
                        <?php echo form_open(admin_url('kt_sepay/tenant_run_reconcile')); ?>
                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                            <button type="submit" class="btn btn-primary" onclick="return confirm('<?php echo html_escape(_l('kt_sepay_run_reconcile_confirm')); ?>');" <?php echo !$canRunReconcile ? 'disabled' : ''; ?>><?php echo _l('kt_sepay_run_reconcile_now'); ?></button>
                        <?php echo form_close(); ?>
                        <hr />
                        <?php if (!empty($logs)) { ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>STT</th>
                                            <th>Thời gian</th>
                                            <th>Giao dịch nhận</th>
                                            <th>Giao dịch xử lý</th>
                                            <th>Lỗi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logs as $index => $log) { ?>
                                            <tr>
                                                <td><?php echo (int) $index + 1; ?></td>
                                                <td><?php echo html_escape(_dt((string) $log['created_at'])); ?></td>
                                                <td><?php echo (int) ($log['total_fetched'] ?? 0); ?></td>
                                                <td><?php echo (int) ($log['total_processed'] ?? 0); ?></td>
                                                <td><?php echo (int) ($log['total_errors'] ?? 0); ?></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <p class="text-muted"><?php echo _l('kt_sepay_reconciliation_empty'); ?></p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>

