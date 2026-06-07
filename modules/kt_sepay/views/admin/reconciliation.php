<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="clearfix">
                            <h4 class="pull-left no-margin"><?php echo html_escape($title ?? _l('kt_sepay_reconciliation')); ?></h4>
                            <div class="pull-right">
                                <a href="<?php echo admin_url('kt_sepay/settings'); ?>" class="btn btn-default"><?php echo _l('kt_sepay_open_settings'); ?></a>
                                <a href="<?php echo admin_url('kt_sepay/test_mode'); ?>" class="btn btn-default"><?php echo _l('kt_sepay_test_mode'); ?></a>
                                <?php echo form_open(admin_url('kt_sepay/run_reconcile'), ['style' => 'display:inline-block;margin-left:10px;']); ?>
                                <button type="submit" class="btn btn-primary" onclick="return confirm('<?php echo html_escape(_l('kt_sepay_run_reconcile_confirm')); ?>');"><?php echo _l('kt_sepay_run_reconcile_now'); ?></button>
                                <?php echo form_close(); ?>
                            </div>
                        </div>
                        <hr class="hr-panel-heading" />

                        <?php $apiConfigured = !empty($settings['api_token']) && !empty($settings['bank_code']) && !empty($settings['account_number']); ?>
                        <?php if (!$apiConfigured) { ?>
                            <div class="alert alert-warning">
                                <?php echo _l('kt_sepay_reconciliation_missing_config'); ?>
                            </div>
                        <?php } ?>

                        <div class="row mtop15">
                            <div class="col-md-3">
                                <div class="well well-sm"><?php echo _l('kt_sepay_total_runs'); ?>: <strong><?php echo (int) ($summary['total_runs'] ?? 0); ?></strong></div>
                            </div>
                            <div class="col-md-3">
                                <div class="well well-sm"><?php echo _l('kt_sepay_total_fetched'); ?>: <strong><?php echo (int) ($summary['total_fetched'] ?? 0); ?></strong></div>
                            </div>
                            <div class="col-md-3">
                                <div class="well well-sm"><?php echo _l('kt_sepay_total_processed'); ?>: <strong><?php echo (int) ($summary['total_processed'] ?? 0); ?></strong></div>
                            </div>
                            <div class="col-md-3">
                                <div class="well well-sm"><?php echo _l('kt_sepay_total_errors'); ?>: <strong><?php echo (int) ($summary['total_errors'] ?? 0); ?></strong></div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tbody>
                                        <tr>
                                            <th width="40%"><?php echo _l('kt_sepay_environment'); ?></th>
                                            <td><?php echo html_escape(ucfirst((string) ($settings['environment'] ?? 'sandbox'))); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?php echo _l('kt_sepay_enable_auto_reconcile'); ?></th>
                                            <td><?php echo !empty($settings['auto_reconcile_enabled']) ? _l('kt_sepay_yes') : _l('kt_sepay_no'); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?php echo _l('kt_sepay_last_reconcile_at'); ?></th>
                                            <td><?php echo html_escape($last_reconcile_at !== '' ? $last_reconcile_at : '-'); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?php echo _l('kt_sepay_last_reconcile_transaction_id'); ?></th>
                                            <td><?php echo html_escape($last_reconcile_transaction_id !== '' ? $last_reconcile_transaction_id : '-'); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tbody>
                                        <tr>
                                            <th width="40%"><?php echo _l('kt_sepay_test_reconciliation_api'); ?></th>
                                            <td>
                                                <?php if (!empty($latest_reconciliation_health_check)) { ?>
                                                    <span class="label label-<?php echo kt_sepay_health_status_badge_class($latest_reconciliation_health_check['status'] ?? 'info'); ?>"><?php echo html_escape(kt_sepay_status_label($latest_reconciliation_health_check['status'] ?? '')); ?></span>
                                                    <span class="text-muted mleft10"><?php echo html_escape($latest_reconciliation_health_check['created_at'] ?? ''); ?></span>
                                                <?php } else { ?>
                                                    <span class="text-muted"><?php echo _l('kt_sepay_not_checked_yet'); ?></span>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php echo _l('kt_sepay_test_api_connection'); ?></th>
                                            <td>
                                                <?php if (!empty($latest_connection_health_check)) { ?>
                                                    <span class="label label-<?php echo kt_sepay_health_status_badge_class($latest_connection_health_check['status'] ?? 'info'); ?>"><?php echo html_escape(kt_sepay_status_label($latest_connection_health_check['status'] ?? '')); ?></span>
                                                    <span class="text-muted mleft10"><?php echo html_escape($latest_connection_health_check['created_at'] ?? ''); ?></span>
                                                <?php } else { ?>
                                                    <span class="text-muted"><?php echo _l('kt_sepay_not_checked_yet'); ?></span>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php echo _l('kt_sepay_message'); ?></th>
                                            <td><?php echo html_escape($latest_reconciliation_health_check['message'] ?? ($latest_connection_health_check['message'] ?? '-')); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?php echo _l('kt_sepay_detail'); ?></th>
                                            <td><a href="<?php echo admin_url('kt_sepay/settings'); ?>"><?php echo _l('kt_sepay_open_settings'); ?></a></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <hr />
                        <h5 class="no-margin"><?php echo _l('kt_sepay_reconciliation_history'); ?></h5>
                        <div class="mtop15">
                            <?php if (!empty($logs)) { ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th><?php echo _l('kt_sepay_created_at'); ?></th>
                                                <th><?php echo _l('kt_sepay_environment'); ?></th>
                                                <th><?php echo _l('kt_sepay_total_fetched'); ?></th>
                                                <th><?php echo _l('kt_sepay_matched'); ?></th>
                                                <th><?php echo _l('kt_sepay_total_processed'); ?></th>
                                                <th><?php echo _l('kt_sepay_total_errors'); ?></th>
                                                <th><?php echo _l('kt_sepay_detail'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($logs as $row) { ?>
                                                <tr>
                                                    <td><?php echo (int) $row['id']; ?></td>
                                                    <td><?php echo html_escape($row['created_at'] ?? ''); ?></td>
                                                    <td><?php echo html_escape(ucfirst((string) ($row['environment'] ?? 'sandbox'))); ?></td>
                                                    <td><?php echo (int) ($row['total_fetched'] ?? 0); ?></td>
                                                    <td><?php echo (int) ($row['total_matched'] ?? 0); ?></td>
                                                    <td><?php echo (int) ($row['total_processed'] ?? 0); ?></td>
                                                    <td><?php echo (int) ($row['total_errors'] ?? 0); ?></td>
                                                    <td><pre class="kt-sepay-pre"><?php echo html_escape($row['metadata_json'] ?? ''); ?></pre></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php } else { ?>
                                <div class="alert alert-info">
                                    <p class="no-margin"><?php echo _l('kt_sepay_reconciliation_empty'); ?></p>
                                    <p class="mtop10 no-margin">
                                        <a href="<?php echo admin_url('kt_sepay/settings'); ?>"><?php echo _l('kt_sepay_open_settings'); ?></a>
                                        <?php echo ' | '; ?>
                                        <a href="<?php echo admin_url('kt_sepay/test_mode'); ?>"><?php echo _l('kt_sepay_test_mode'); ?></a>
                                    </p>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
