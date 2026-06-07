<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="panel_s">
            <div class="panel-body">
                <h4><?php echo html_escape($title); ?></h4>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?php echo _l('kt_saas_payment_reference'); ?></th>
                                <th><?php echo _l('kt_saas_tenant'); ?></th>
                                <th><?php echo _l('kt_saas_invoice_number'); ?></th>
                                <th><?php echo _l('kt_saas_status'); ?></th>
                                <th><?php echo _l('kt_saas_gateway'); ?></th>
                                <th><?php echo _l('kt_saas_amount'); ?></th>
                                <th><?php echo _l('kt_saas_paid_at'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment) { ?>
                                <tr>
                                    <td><?php echo html_escape($payment['payment_reference']); ?></td>
                                    <td><?php echo html_escape(($payment['tenant_code'] ?? '-') . ' - ' . ($payment['company_name'] ?? '')); ?></td>
                                    <td><?php echo html_escape($payment['invoice_number'] ?? '-'); ?></td>
                                    <td><span class="label label-<?php echo kt_saas_status_badge_class($payment['status']); ?>"><?php echo html_escape($statuses[$payment['status']] ?? ucfirst($payment['status'])); ?></span></td>
                                    <td><?php echo html_escape($payment['gateway']); ?></td>
                                    <td><?php echo app_format_money((float) $payment['amount'], $payment['currency']); ?></td>
                                    <td><?php echo !empty($payment['paid_at']) ? _dt($payment['paid_at']) : '-'; ?></td>
                                </tr>
                            <?php } ?>
                            <?php if (empty($payments)) { ?><tr><td colspan="7"><?php echo _l('kt_saas_no_records'); ?></td></tr><?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
