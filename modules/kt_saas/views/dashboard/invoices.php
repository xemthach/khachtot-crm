<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="panel_s">
            <div class="panel-body">
                <h4><?php echo html_escape($title); ?></h4>
                <?php echo form_open(admin_url('kt_saas/bulk_invoices'), ['id' => 'kt-saas-bulk-invoices-form']); ?>
                <div class="row mbottom15">
                    <div class="col-md-8">
                        <div class="input-group">
                            <select name="bulk_action" id="kt-saas-bulk-invoices-action" class="form-control">
                                <option value="">Chọn thao tác hàng loạt</option>
                                <option value="mark_paid"><?php echo _l('kt_saas_mark_paid'); ?></option>
                            </select>
                            <span class="input-group-btn">
                                <button type="submit" class="btn btn-primary" id="kt-saas-bulk-invoices-submit" disabled>Áp dụng</button>
                            </span>
                        </div>
                        <p class="text-muted mtop10 mbot0" id="kt-saas-bulk-invoices-count">Chưa chọn hóa đơn nào.</p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th width="40">
                                    <div class="checkbox checkbox-primary mtop0 mbot0">
                                        <input type="checkbox" id="kt-saas-bulk-invoices-all">
                                        <label for="kt-saas-bulk-invoices-all"></label>
                                    </div>
                                </th>
                                <th><?php echo _l('kt_saas_invoice_number'); ?></th>
                                <th><?php echo _l('kt_saas_tenant'); ?></th>
                                <th><?php echo _l('kt_saas_plan'); ?></th>
                                <th><?php echo _l('kt_saas_status'); ?></th>
                                <th><?php echo _l('kt_saas_due_date'); ?></th>
                                <th><?php echo _l('kt_saas_amount'); ?></th>
                                <th><?php echo _l('kt_saas_actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $invoice) { ?>
                                <tr>
                                    <td>
                                        <div class="checkbox checkbox-primary mtop0 mbot0">
                                            <input type="checkbox" class="kt-saas-bulk-invoices-item" name="ids[]" value="<?php echo (int) $invoice['id']; ?>" id="kt-saas-invoice-<?php echo (int) $invoice['id']; ?>">
                                            <label for="kt-saas-invoice-<?php echo (int) $invoice['id']; ?>"></label>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo html_escape($invoice['invoice_number']); ?></strong>
                                        <?php if (!empty($invoice['issued_at'])) { ?><br><small><?php echo _l('kt_saas_created_at'); ?>: <?php echo _dt($invoice['issued_at']); ?></small><?php } ?>
                                        <?php if (!empty($invoice['paid_at'])) { ?><br><small><?php echo _l('kt_saas_paid_at'); ?>: <?php echo _dt($invoice['paid_at']); ?></small><?php } ?>
                                    </td>
                                    <td><?php echo html_escape(($invoice['tenant_code'] ?? '-') . ' - ' . ($invoice['company_name'] ?? '')); ?></td>
                                    <td><?php echo html_escape($invoice['plan_name'] ?? '-'); ?></td>
                                    <td>
                                        <span class="label label-<?php echo kt_saas_status_badge_class($invoice['status']); ?>">
                                            <?php echo html_escape($statuses[$invoice['status']] ?? ucfirst(str_replace('_', ' ', $invoice['status']))); ?>
                                        </span>
                                        <?php if ((int) ($invoice['reminder_count'] ?? 0) > 0) { ?><br><small>Nhắc thanh toán: <?php echo (int) $invoice['reminder_count']; ?></small><?php } ?>
                                    </td>
                                    <td><?php echo !empty($invoice['due_date']) ? html_escape($invoice['due_date']) : '-'; ?></td>
                                    <td><?php echo app_format_money((float) $invoice['grand_total'], $invoice['currency']); ?></td>
                                    <td>
                                        <?php if (!empty($invoice['checkout_url'])) { ?>
                                            <a href="<?php echo html_escape($invoice['checkout_url']); ?>" class="btn btn-default btn-sm" target="_blank" rel="noopener">
                                                <?php echo _l('kt_saas_payment_link'); ?>
                                            </a>
                                        <?php } ?>
                                        <?php if (!empty($sepay_enabled) && !empty($invoice['sepay_url'])) { ?>
                                            <a href="<?php echo html_escape($invoice['sepay_url']); ?>" class="btn btn-primary btn-sm" target="_blank" rel="noopener">
                                                SePay VietQR
                                            </a>
                                        <?php } ?>
                                        <?php if (!in_array($invoice['status'], ['paid', 'cancelled'], true)) { ?>
                                            <a href="<?php echo admin_url('kt_saas/mark_invoice_paid/' . $invoice['id']); ?>" class="btn btn-success btn-sm">
                                                <?php echo _l('kt_saas_mark_paid'); ?>
                                            </a>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                            <?php if (empty($invoices)) { ?><tr><td colspan="8"><?php echo _l('kt_saas_no_records'); ?></td></tr><?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('kt-saas-bulk-invoices-form');
    if (!form) {
        return;
    }
    var checkAll = document.getElementById('kt-saas-bulk-invoices-all');
    var items = Array.prototype.slice.call(document.querySelectorAll('.kt-saas-bulk-invoices-item'));
    var submit = document.getElementById('kt-saas-bulk-invoices-submit');
    var count = document.getElementById('kt-saas-bulk-invoices-count');
    var action = document.getElementById('kt-saas-bulk-invoices-action');

    function updateState() {
        var selected = items.filter(function (item) { return item.checked; }).length;
        submit.disabled = selected === 0;
        count.textContent = selected > 0 ? ('Đã chọn ' + selected + ' hóa đơn.') : 'Chưa chọn hóa đơn nào.';
        if (checkAll) {
            checkAll.checked = selected > 0 && selected === items.length;
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            items.forEach(function (item) { item.checked = checkAll.checked; });
            updateState();
        });
    }

    items.forEach(function (item) {
        item.addEventListener('change', updateState);
    });

    form.addEventListener('submit', function (event) {
        if (!action.value || items.filter(function (item) { return item.checked; }).length === 0) {
            event.preventDefault();
            alert('Chọn ít nhất một hóa đơn và một thao tác.');
            return;
        }

        if (!window.confirm('Xác nhận xử lý hàng loạt các hóa đơn đã chọn?')) {
            event.preventDefault();
        }
    });
});
</script>
