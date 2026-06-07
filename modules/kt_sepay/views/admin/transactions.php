<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo html_escape($title ?? _l('kt_sepay_transactions')); ?></h4>
                        <hr class="hr-panel-heading" />

                        <?php if (!empty($transactions)) { ?>
                        <form id="kt-sepay-bulk-transactions-form" method="post" action="<?php echo admin_url('kt_sepay/bulk_transactions'); ?>">
                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                        </form>
                        <div class="row mbottom15">
                            <div class="col-md-8">
                                <div class="input-group">
                                    <select name="bulk_action" id="kt-sepay-bulk-transactions-action" class="form-control" form="kt-sepay-bulk-transactions-form">
                                        <option value="">Chọn thao tác hàng loạt</option>
                                        <option value="retry_processing">Xử lý lại</option>
                                        <option value="mark_unmatched">Đánh dấu chưa khớp</option>
                                        <option value="export_csv">Xuất CSV</option>
                                    </select>
                                    <span class="input-group-btn">
                                        <button type="submit" class="btn btn-primary" id="kt-sepay-bulk-transactions-submit" form="kt-sepay-bulk-transactions-form" disabled>Áp dụng</button>
                                    </span>
                                </div>
                                <p class="text-muted mtop10 mbot0" id="kt-sepay-bulk-transactions-count">Chưa chọn giao dịch nào.</p>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th width="40">
                                            <div class="checkbox checkbox-primary mtop0 mbot0">
                                                <input type="checkbox" id="kt-sepay-bulk-transactions-all">
                                                <label for="kt-sepay-bulk-transactions-all"></label>
                                            </div>
                                        </th>
                                        <th>ID</th>
                                        <th>Mã GD SePay</th>
                                        <th><?php echo _l('kt_sepay_gateway'); ?></th>
                                        <th><?php echo _l('kt_sepay_amount'); ?></th>
                                        <th><?php echo _l('kt_sepay_content'); ?></th>
                                        <th><?php echo _l('kt_sepay_status'); ?></th>
                                        <th><?php echo _l('kt_sepay_matched'); ?></th>
                                        <th><?php echo _l('kt_sepay_action'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $row) { ?>
                                    <tr>
                                        <td>
                                            <div class="checkbox checkbox-primary mtop0 mbot0">
                                                <input type="checkbox" class="kt-sepay-bulk-transactions-item" name="ids[]" value="<?php echo (int) $row['id']; ?>" id="kt-sepay-transaction-<?php echo (int) $row['id']; ?>" form="kt-sepay-bulk-transactions-form">
                                                <label for="kt-sepay-transaction-<?php echo (int) $row['id']; ?>"></label>
                                            </div>
                                        </td>
                                        <td><?php echo (int) $row['id']; ?></td>
                                        <td><?php echo html_escape($row['sepay_transaction_id']); ?></td>
                                        <td><?php echo html_escape($row['gateway']); ?></td>
                                        <td><?php echo html_escape($row['transfer_amount']); ?></td>
                                        <td><?php echo html_escape($row['content']); ?></td>
                                        <td><span class="label label-<?php echo kt_sepay_status_badge_class($row['status']); ?>"><?php echo html_escape(kt_sepay_status_label($row['status'])); ?></span></td>
                                        <td><?php echo html_escape($row['matched_reference'] ?: '-'); ?></td>
                                        <td>
                                            <?php if (($row['status'] ?? '') === 'unmatched' && !empty($open_requests)) { ?>
                                                <?php echo form_open(admin_url('kt_sepay/manual_match/' . (int) $row['id']), ['class' => 'kt-sepay-inline-form']); ?>
                                                    <select name="payment_request_id" class="form-control input-sm" required>
                                                        <?php foreach ($open_requests as $request) { ?>
                                                            <option value="<?php echo (int) $request['id']; ?>">
                                                                #<?php echo (int) $request['id']; ?> - <?php echo html_escape($request['reference_code']); ?>
                                                            </option>
                                                        <?php } ?>
                                                    </select>
                                                    <button type="submit" class="btn btn-default btn-sm mtop5"><?php echo _l('kt_sepay_match_now'); ?></button>
                                                <?php echo form_close(); ?>
                                            <?php } else { ?>
                                                -
                                            <?php } ?>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php } ?>

                        <?php if (!empty($requests)) { ?>
                        <form id="kt-sepay-bulk-requests-form" method="post" action="<?php echo admin_url('kt_sepay/bulk_payment_requests'); ?>">
                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                        </form>
                        <div class="row mbottom15">
                            <div class="col-md-8">
                                <div class="input-group">
                                    <select name="bulk_action" id="kt-sepay-bulk-requests-action" class="form-control" form="kt-sepay-bulk-requests-form">
                                        <option value="">Chọn thao tác hàng loạt</option>
                                        <option value="expire">Đánh dấu hết hạn</option>
                                        <option value="cancel">Hủy yêu cầu thanh toán</option>
                                    </select>
                                    <span class="input-group-btn">
                                        <button type="submit" class="btn btn-primary" id="kt-sepay-bulk-requests-submit" form="kt-sepay-bulk-requests-form" disabled>Áp dụng</button>
                                    </span>
                                </div>
                                <p class="text-muted mtop10 mbot0" id="kt-sepay-bulk-requests-count">Chưa chọn yêu cầu thanh toán nào.</p>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th width="40">
                                            <div class="checkbox checkbox-primary mtop0 mbot0">
                                                <input type="checkbox" id="kt-sepay-bulk-requests-all">
                                                <label for="kt-sepay-bulk-requests-all"></label>
                                            </div>
                                        </th>
                                        <th>ID</th>
                                        <th><?php echo _l('kt_sepay_context'); ?></th>
                                        <th><?php echo _l('kt_sepay_reference'); ?></th>
                                        <th><?php echo _l('kt_sepay_amount'); ?></th>
                                        <th><?php echo _l('kt_sepay_status'); ?></th>
                                        <th><?php echo _l('kt_sepay_tenant'); ?></th>
                                        <th><?php echo _l('kt_sepay_expires_at'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requests as $row) { ?>
                                    <tr>
                                        <td>
                                            <div class="checkbox checkbox-primary mtop0 mbot0">
                                                <input type="checkbox" class="kt-sepay-bulk-requests-item" name="ids[]" value="<?php echo (int) $row['id']; ?>" id="kt-sepay-request-<?php echo (int) $row['id']; ?>" form="kt-sepay-bulk-requests-form">
                                                <label for="kt-sepay-request-<?php echo (int) $row['id']; ?>"></label>
                                            </div>
                                        </td>
                                        <td><?php echo (int) $row['id']; ?></td>
                                        <td><?php echo html_escape($row['context_type']); ?></td>
                                        <td><?php echo html_escape($row['reference_code']); ?></td>
                                        <td><?php echo html_escape($row['amount'] . ' ' . $row['currency']); ?></td>
                                        <td><span class="label label-<?php echo kt_sepay_status_badge_class($row['status']); ?>"><?php echo html_escape(kt_sepay_status_label($row['status'])); ?></span></td>
                                        <td><?php echo (int) ($row['tenant_id'] ?? 0); ?></td>
                                        <td><?php echo html_escape($row['expires_at']); ?></td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function initBulk(formId, allId, itemSelector, actionId, submitId, countId, emptyMessage, itemLabel, confirmText) {
        var form = document.getElementById(formId);
        if (!form) {
            return;
        }
        var checkAll = document.getElementById(allId);
        var items = Array.prototype.slice.call(document.querySelectorAll(itemSelector));
        var action = document.getElementById(actionId);
        var submit = document.getElementById(submitId);
        var count = document.getElementById(countId);

        function updateState() {
            var selected = items.filter(function (item) { return item.checked; }).length;
            submit.disabled = selected === 0;
            count.textContent = selected > 0 ? ('Đã chọn ' + selected + ' ' + itemLabel + '.') : emptyMessage;
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
                alert('Chọn ít nhất một bản ghi và một thao tác.');
                return;
            }

            if (!window.confirm(confirmText)) {
                event.preventDefault();
            }
        });
    }

    initBulk(
        'kt-sepay-bulk-transactions-form',
        'kt-sepay-bulk-transactions-all',
        '.kt-sepay-bulk-transactions-item',
        'kt-sepay-bulk-transactions-action',
        'kt-sepay-bulk-transactions-submit',
        'kt-sepay-bulk-transactions-count',
        'Chưa chọn giao dịch nào.',
        'giao dịch',
        'Xác nhận thực hiện thao tác hàng loạt cho các giao dịch đã chọn?'
    );

    initBulk(
        'kt-sepay-bulk-requests-form',
        'kt-sepay-bulk-requests-all',
        '.kt-sepay-bulk-requests-item',
        'kt-sepay-bulk-requests-action',
        'kt-sepay-bulk-requests-submit',
        'kt-sepay-bulk-requests-count',
        'Chưa chọn yêu cầu thanh toán nào.',
        'yêu cầu thanh toán',
        'Xác nhận thực hiện thao tác hàng loạt cho các yêu cầu thanh toán đã chọn?'
    );
});
</script>
