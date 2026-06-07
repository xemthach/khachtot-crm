<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-5">
                <div class="panel_s"><div class="panel-body">
                    <h4><?php echo html_escape($edit_subscription ? _l('kt_saas_edit_subscription') : _l('kt_saas_add_subscription')); ?></h4>
                    <?php echo form_open(admin_url('kt_saas/subscriptions' . ($edit_subscription ? '/' . $edit_subscription['id'] : ''))); ?>
                    <div class="form-group"><label for="tenant_id"><?php echo _l('kt_saas_tenant'); ?></label><select name="tenant_id" id="tenant_id" class="form-control selectpicker" data-live-search="true"><?php foreach ($tenants as $tenant) { ?><option value="<?php echo (int) $tenant['id']; ?>" <?php echo isset($edit_subscription['tenant_id']) && (int) $edit_subscription['tenant_id'] === (int) $tenant['id'] ? 'selected' : ''; ?>><?php echo html_escape($tenant['tenant_code'] . ' - ' . $tenant['company_name']); ?></option><?php } ?></select></div>
                    <div class="form-group"><label for="plan_id"><?php echo _l('kt_saas_plan'); ?></label><select name="plan_id" id="plan_id" class="form-control selectpicker"><?php foreach ($plans as $plan) { ?><option value="<?php echo (int) $plan['id']; ?>" <?php echo isset($edit_subscription['plan_id']) && (int) $edit_subscription['plan_id'] === (int) $plan['id'] ? 'selected' : ''; ?>><?php echo html_escape($plan['plan_name']); ?></option><?php } ?></select></div>
                    <div class="form-group"><label for="status"><?php echo _l('kt_saas_status'); ?></label><select name="status" id="status" class="form-control selectpicker"><?php foreach ($statuses as $key => $label) { ?><option value="<?php echo html_escape($key); ?>" <?php echo ($edit_subscription['status'] ?? 'trial') === $key ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option><?php } ?></select></div>
                    <div class="form-group"><label for="billing_cycle"><?php echo _l('kt_saas_billing_cycle'); ?></label><select name="billing_cycle" id="billing_cycle" class="form-control selectpicker"><?php foreach ($billing_cycles as $key => $label) { ?><option value="<?php echo html_escape($key); ?>" <?php echo ($edit_subscription['billing_cycle'] ?? 'monthly') === $key ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option><?php } ?></select></div>
                    <?php echo render_date_input('started_at', 'kt_saas_started_at', isset($edit_subscription['started_at']) ? _d(date('Y-m-d', strtotime($edit_subscription['started_at']))) : _d(date('Y-m-d'))); ?>
                    <?php echo render_date_input('trial_ends_at', 'kt_saas_trial_ends_at', isset($edit_subscription['trial_ends_at']) && $edit_subscription['trial_ends_at'] ? _d(date('Y-m-d', strtotime($edit_subscription['trial_ends_at']))) : ''); ?>
                    <?php echo render_date_input('current_period_start_at', 'kt_saas_period_start', isset($edit_subscription['current_period_start_at']) && $edit_subscription['current_period_start_at'] ? _d(date('Y-m-d', strtotime($edit_subscription['current_period_start_at']))) : ''); ?>
                    <?php echo render_date_input('current_period_end_at', 'kt_saas_period_end', isset($edit_subscription['current_period_end_at']) && $edit_subscription['current_period_end_at'] ? _d(date('Y-m-d', strtotime($edit_subscription['current_period_end_at']))) : ''); ?>
                    <?php echo render_date_input('grace_ends_at', 'kt_saas_grace_ends_at', isset($edit_subscription['grace_ends_at']) && $edit_subscription['grace_ends_at'] ? _d(date('Y-m-d', strtotime($edit_subscription['grace_ends_at']))) : ''); ?>
                    <?php echo render_date_input('next_billing_at', 'kt_saas_next_billing_at', isset($edit_subscription['next_billing_at']) && $edit_subscription['next_billing_at'] ? _d(date('Y-m-d', strtotime($edit_subscription['next_billing_at']))) : ''); ?>
                    <?php echo render_textarea('metadata_json', 'kt_saas_metadata_json', $edit_subscription['metadata_json'] ?? ''); ?>
                    <div class="checkbox checkbox-primary"><input type="checkbox" id="auto_renew" name="auto_renew" <?php echo !isset($edit_subscription['auto_renew']) || $edit_subscription['auto_renew'] ? 'checked' : ''; ?>><label for="auto_renew"><?php echo _l('kt_saas_auto_renew'); ?></label></div>
                    <button type="submit" class="btn btn-primary"><?php echo _l('kt_saas_save'); ?></button>
                    <?php if ($edit_subscription) { ?><a href="<?php echo admin_url('kt_saas/subscriptions'); ?>" class="btn btn-default"><?php echo _l('kt_saas_cancel'); ?></a><?php } ?>
                    <?php echo form_close(); ?>
                </div></div>
            </div>
            <div class="col-md-7">
                <div class="panel_s"><div class="panel-body">
                    <h4><?php echo html_escape($title); ?></h4>
                    <?php echo form_open(admin_url('kt_saas/bulk_subscriptions'), ['id' => 'kt-saas-bulk-subscriptions-form']); ?>
                    <div class="row mbottom15">
                        <div class="col-md-8">
                            <div class="input-group">
                                <select name="bulk_action" id="kt-saas-bulk-subscriptions-action" class="form-control">
                                    <option value="">Chọn thao tác hàng loạt</option>
                                    <option value="trial">Chuyển sang dùng thử</option>
                                    <option value="active">Kích hoạt</option>
                                    <option value="grace">Chuyển sang gia hạn tạm thời</option>
                                    <option value="suspended">Tạm ngưng</option>
                                    <option value="cancelled">Hủy</option>
                                    <option value="terminated">Chấm dứt</option>
                                </select>
                                <span class="input-group-btn">
                                    <button type="submit" class="btn btn-primary" id="kt-saas-bulk-subscriptions-submit" disabled>Áp dụng</button>
                                </span>
                            </div>
                            <p class="text-muted mtop10 mbot0" id="kt-saas-bulk-subscriptions-count">Chưa chọn gói đăng ký nào.</p>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th width="40">
                                        <div class="checkbox checkbox-primary mtop0 mbot0">
                                            <input type="checkbox" id="kt-saas-bulk-subscriptions-all">
                                            <label for="kt-saas-bulk-subscriptions-all"></label>
                                        </div>
                                    </th>
                                    <th><?php echo _l('kt_saas_tenant'); ?></th>
                                    <th><?php echo _l('kt_saas_plan'); ?></th>
                                    <th><?php echo _l('kt_saas_status'); ?></th>
                                    <th><?php echo _l('kt_saas_next_billing_at'); ?></th>
                                    <th><?php echo _l('kt_saas_actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subscriptions as $subscription) { ?>
                                    <tr>
                                        <td>
                                            <div class="checkbox checkbox-primary mtop0 mbot0">
                                                <input type="checkbox" class="kt-saas-bulk-subscriptions-item" name="ids[]" value="<?php echo (int) $subscription['id']; ?>" id="kt-saas-subscription-<?php echo (int) $subscription['id']; ?>">
                                                <label for="kt-saas-subscription-<?php echo (int) $subscription['id']; ?>"></label>
                                            </div>
                                        </td>
                                        <td><strong><?php echo html_escape($subscription['tenant_code']); ?></strong><br><small><?php echo html_escape($subscription['company_name']); ?></small></td>
                                        <td><?php echo html_escape($subscription['plan_name']); ?></td>
                                        <td><span class="label label-<?php echo kt_saas_status_badge_class($subscription['status']); ?>"><?php echo html_escape($statuses[$subscription['status']] ?? $subscription['status']); ?></span></td>
                                        <td><?php echo $subscription['next_billing_at'] ? _dt($subscription['next_billing_at']) : '-'; ?></td>
                                        <td><a href="<?php echo admin_url('kt_saas/subscriptions/' . $subscription['id']); ?>" class="btn btn-default btn-sm"><?php echo _l('kt_saas_edit'); ?></a></td>
                                    </tr>
                                <?php } ?>
                                <?php if (empty($subscriptions)) { ?><tr><td colspan="6"><?php echo _l('kt_saas_no_records'); ?></td></tr><?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php echo form_close(); ?>
                </div></div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('kt-saas-bulk-subscriptions-form');
    if (!form) {
        return;
    }
    var checkAll = document.getElementById('kt-saas-bulk-subscriptions-all');
    var items = Array.prototype.slice.call(document.querySelectorAll('.kt-saas-bulk-subscriptions-item'));
    var submit = document.getElementById('kt-saas-bulk-subscriptions-submit');
    var count = document.getElementById('kt-saas-bulk-subscriptions-count');
    var action = document.getElementById('kt-saas-bulk-subscriptions-action');

    function updateState() {
        var selected = items.filter(function (item) { return item.checked; }).length;
        submit.disabled = selected === 0;
        count.textContent = selected > 0 ? ('Đã chọn ' + selected + ' gói đăng ký.') : 'Chưa chọn gói đăng ký nào.';
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
            alert('Chọn ít nhất một gói đăng ký và một thao tác.');
            return;
        }

        if (!window.confirm('Xác nhận thực hiện thao tác hàng loạt cho các gói đăng ký đã chọn?')) {
            event.preventDefault();
        }
    });
});
</script>
