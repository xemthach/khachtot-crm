<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
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
                        <h4><?php echo _l('kt_saas_limits'); ?></h4>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('kt_saas_metric'); ?></th>
                                        <th><?php echo _l('kt_saas_current'); ?></th>
                                        <th><?php echo _l('kt_saas_limit'); ?></th>
                                        <th><?php echo _l('kt_saas_remaining'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (($profile['limits'] ?? []) as $metric => $limitValue) { ?>
                                        <?php $currentValue = (float) ($usage[$metric] ?? 0); ?>
                                        <?php $remaining = (int) $limitValue === 0 ? _l('kt_saas_unlimited') : max((float) $limitValue - $currentValue, 0); ?>
                                        <tr>
                                            <td><?php echo html_escape(kt_saas_metric_label($metric)); ?></td>
                                            <td><?php echo html_escape(kt_saas_metric_value($metric, $currentValue)); ?></td>
                                            <td><?php echo html_escape(kt_saas_metric_value($metric, $limitValue, true)); ?></td>
                                            <td><?php echo html_escape(is_string($remaining) ? $remaining : kt_saas_metric_value($metric, $remaining)); ?></td>
                                        </tr>
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
                        <h4><?php echo _l('kt_saas_usage_history'); ?></h4>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('kt_saas_date'); ?></th>
                                        <th><?php echo _l('kt_saas_metric'); ?></th>
                                        <th><?php echo _l('kt_saas_value'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($history as $row) { ?>
                                        <tr>
                                            <td><?php echo html_escape($row['snapshot_date']); ?></td>
                                            <td><?php echo html_escape(kt_saas_metric_label($row['metric_key'])); ?></td>
                                            <td><?php echo html_escape(kt_saas_metric_value($row['metric_key'], $row['metric_value'])); ?></td>
                                        </tr>
                                    <?php } ?>
                                    <?php if (empty($history)) { ?>
                                        <tr><td colspan="3"><?php echo _l('kt_saas_no_records'); ?></td></tr>
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
