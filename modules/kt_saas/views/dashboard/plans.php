<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="tw-flex tw-items-center tw-justify-between">
                            <h4 class="no-margin"><?php echo html_escape($title); ?></h4>
                            <div class="tw-flex tw-gap-2">
                                <form action="<?php echo admin_url('kt_saas/rehydrate_plan_features'); ?>" method="post" style="display:inline;">
                                    <input type="hidden" name="<?php echo html_escape($this->security->get_csrf_token_name()); ?>" value="<?php echo html_escape($this->security->get_csrf_hash()); ?>">
                                    <button type="submit" class="btn btn-default">Rehydrate Features</button>
                                </form>
                                <a href="<?php echo admin_url('kt_saas/plans?create=1'); ?>" class="btn btn-primary">Thêm gói</a>
                            </div>
                        </div>

                        <?php if (!empty($show_form)) { ?>
                        <hr>
                        <h5><?php echo html_escape($edit_plan ? _l('kt_saas_edit_plan') : _l('kt_saas_add_plan')); ?></h5>
                        <?php echo form_open(admin_url('kt_saas/plans' . ($edit_plan ? '/' . $edit_plan['id'] : ''))); ?>

                        <div class="panel panel-default">
                            <div class="panel-heading"><strong>Tab 1 - Thông tin gói</strong></div>
                            <div class="panel-body row">
                                <div class="col-md-4"><?php echo render_input('plan_code', 'kt_saas_plan_code', $edit_plan['plan_code'] ?? '', 'text', ['required' => true]); ?></div>
                                <div class="col-md-4"><?php echo render_input('plan_name', 'kt_saas_plan_name', $edit_plan['plan_name'] ?? '', 'text', ['required' => true]); ?></div>
                                <div class="col-md-4"><?php echo render_textarea('notes', 'kt_saas_notes', $edit_plan['notes'] ?? ''); ?></div>
                                <div class="col-md-12">
                                    <div class="checkbox checkbox-primary"><input type="checkbox" id="is_active" name="is_active" <?php echo !isset($edit_plan['is_active']) || $edit_plan['is_active'] ? 'checked' : ''; ?>><label for="is_active"><?php echo _l('kt_saas_active'); ?></label></div>
                                    <div class="checkbox checkbox-primary"><input type="checkbox" id="is_public" name="is_public" <?php echo !isset($edit_plan['is_public']) || $edit_plan['is_public'] ? 'checked' : ''; ?>><label for="is_public"><?php echo _l('kt_saas_is_public'); ?></label></div>
                                </div>
                            </div>
                        </div>

                        <div class="panel panel-default">
                            <div class="panel-heading"><strong>Tab 2 - Giá và chu kỳ</strong></div>
                            <div class="panel-body row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="billing_cycle"><?php echo _l('kt_saas_billing_cycle'); ?></label>
                                        <select name="billing_cycle" id="billing_cycle" class="form-control selectpicker">
                                            <?php foreach ($billing_cycles as $key => $label) { ?>
                                                <option value="<?php echo html_escape($key); ?>" <?php echo ($edit_plan['billing_cycle'] ?? 'monthly') === $key ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4"><?php echo render_input('price', 'kt_saas_price', $edit_plan['price'] ?? '0', 'number', ['step' => '0.01']); ?></div>
                                <div class="col-md-4"><?php echo render_input('setup_fee', 'kt_saas_setup_fee', $edit_plan['setup_fee'] ?? '0', 'number', ['step' => '0.01']); ?></div>
                                <div class="col-md-4"><?php echo render_input('currency', 'kt_saas_currency', $edit_plan['currency'] ?? 'USD'); ?></div>
                                <div class="col-md-4"><?php echo render_input('trial_days', 'kt_saas_trial_days', $edit_plan['trial_days'] ?? '0', 'number'); ?></div>
                                <div class="col-md-4"><?php echo render_input('grace_days', 'kt_saas_grace_days', $edit_plan['grace_days'] ?? '0', 'number'); ?></div>
                            </div>
                        </div>

                        <?php
                        $workspaceFeatureCatalog = is_array($workspace_feature_catalog ?? null) ? $workspace_feature_catalog : [];
                        $selectedWorkspaceFeatures = is_array($edit_plan_workspace_features ?? null) ? $edit_plan_workspace_features : [];
                        $integrationFeatureCatalog = is_array($integration_feature_catalog ?? null) ? $integration_feature_catalog : [];
                        $selectedIntegrationFeatures = is_array($edit_plan_integration_features ?? null) ? $edit_plan_integration_features : [];
                        $selected_modules = isset($edit_plan['module_json']) ? json_decode($edit_plan['module_json'], true) ?: [] : [];
                        ?>

                        <div class="panel panel-default">
                            <div class="panel-heading"><strong>Tab 3 - Giới hạn sử dụng</strong></div>
                            <div class="panel-body row">
                                <div class="col-md-3"><?php echo render_input('limit_staff', 'kt_saas_limit_staff', $edit_plan['limit_staff'] ?? '0', 'number'); ?></div>
                                <div class="col-md-3"><?php echo render_input('limit_clients', 'kt_saas_limit_clients', $edit_plan['limit_clients'] ?? '0', 'number'); ?></div>
                                <div class="col-md-3"><?php echo render_input('limit_storage_mb', 'kt_saas_limit_storage_mb', $edit_plan['limit_storage_mb'] ?? '0', 'number'); ?></div>
                                <div class="col-md-3"><?php echo render_input('limit_invoices', 'kt_saas_limit_invoices', $edit_plan['limit_invoices'] ?? '0', 'number'); ?></div>
                                <div class="col-md-3"><?php echo render_input('limit_projects', 'kt_saas_limit_projects', $edit_plan['limit_projects'] ?? '0', 'number'); ?></div>
                                <div class="col-md-3"><?php echo render_input('limit_warehouses', 'kt_saas_limit_warehouses', $edit_plan['limit_warehouses'] ?? '0', 'number'); ?></div>
                                <div class="col-md-3"><?php echo render_input('limit_api_requests_daily', 'kt_saas_limit_api_requests_daily', $edit_plan['limit_api_requests_daily'] ?? '0', 'number'); ?></div>
                                <div class="col-md-3"><?php echo render_input('limit_automations', 'kt_saas_limit_automations', $edit_plan['limit_automations'] ?? '0', 'number'); ?></div>
                                <div class="col-md-3"><?php echo render_input('limit_roles', 'kt_saas_limit_roles', $edit_plan['limit_roles'] ?? '0', 'number'); ?></div>
                                <div class="col-md-3"><?php echo render_input('limit_departments', 'kt_saas_limit_departments', $edit_plan['limit_departments'] ?? '0', 'number'); ?></div>
                                <div class="col-md-3"><?php echo render_input('limit_governance_viewers', 'kt_saas_limit_governance_viewers', $edit_plan['limit_governance_viewers'] ?? '0', 'number'); ?></div>
                                <div class="col-md-3"><?php echo render_input('limit_governance_managers', 'kt_saas_limit_governance_managers', $edit_plan['limit_governance_managers'] ?? '0', 'number'); ?></div>
                            </div>
                        </div>

                        <div class="panel panel-default">
                            <div class="panel-heading"><strong>Tab 4 - Ứng dụng và Module</strong></div>
                            <div class="panel-body">
                                <div class="form-group">
                                    <label for="module_codes" class="control-label"><?php echo _l('kt_saas_module_codes'); ?></label>
                                    <select name="module_codes[]" id="module_codes" class="selectpicker" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" multiple data-actions-box="true" data-live-search="true">
                                        <?php foreach ($available_modules as $module) { ?>
                                        <option value="<?php echo html_escape($module['system_name']); ?>" <?php echo in_array($module['system_name'], $selected_modules, true) ? 'selected' : ''; ?>><?php echo html_escape($module['headers']['module_name'] ?? $module['system_name']); ?> (<?php echo html_escape($module['system_name']); ?>)</option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="control-label">Workspace governance capabilities</label>
                                        <div class="panel panel-default"><div class="panel-body" style="max-height:250px;overflow:auto;">
                                        <?php foreach ($workspaceFeatureCatalog as $featureKey => $feature) { ?>
                                            <div class="checkbox checkbox-primary">
                                                <input type="checkbox" id="feature_<?php echo html_escape(md5($featureKey)); ?>" name="workspace_feature_keys[]" value="<?php echo html_escape($featureKey); ?>" <?php echo in_array($featureKey, $selectedWorkspaceFeatures, true) ? 'checked' : ''; ?>>
                                                <label for="feature_<?php echo html_escape(md5($featureKey)); ?>"><?php echo html_escape((string) ($feature['label'] ?? $featureKey)); ?></label>
                                            </div>
                                        <?php } ?>
                                        </div></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="control-label">Integration module capabilities</label>
                                        <div class="panel panel-default"><div class="panel-body" style="max-height:250px;overflow:auto;">
                                        <?php foreach ($integrationFeatureCatalog as $featureKey => $feature) { ?>
                                            <div class="checkbox checkbox-primary">
                                                <input type="checkbox" id="integration_feature_<?php echo html_escape(md5($featureKey)); ?>" name="integration_feature_keys[]" value="<?php echo html_escape($featureKey); ?>" <?php echo in_array($featureKey, $selectedIntegrationFeatures, true) ? 'checked' : ''; ?>>
                                                <label for="integration_feature_<?php echo html_escape(md5($featureKey)); ?>"><?php echo html_escape((string) ($feature['label'] ?? $featureKey)); ?></label>
                                            </div>
                                        <?php } ?>
                                        </div></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary"><?php echo _l('kt_saas_save'); ?></button>
                        <a href="<?php echo admin_url('kt_saas/plans'); ?>" class="btn btn-default"><?php echo _l('kt_saas_cancel'); ?></a>
                        <?php echo form_close(); ?>
                        <?php } ?>

                        <hr>
                        <?php echo form_open(admin_url('kt_saas/bulk_plans'), ['id' => 'kt-saas-bulk-plans-form']); ?>
                        <div class="row mbot15" id="kt-saas-bulk-plans-toolbar" style="display:none;">
                            <div class="col-md-12">
                                <div class="input-group">
                                    <select name="bulk_action" id="kt-saas-bulk-plans-action" class="form-control">
                                        <option value="">Chọn thao tác hàng loạt</option>
                                        <option value="hide">Hide selected</option>
                                        <option value="show">Show selected</option>
                                        <option value="archive">Archive selected</option>
                                        <option value="duplicate">Duplicate selected</option>
                                        <?php if (!empty($can_delete_plans)) { ?><option value="delete">Delete selected</option><?php } ?>
                                        <option value="export">Export selected</option>
                                    </select>
                                    <span class="input-group-btn"><button type="submit" class="btn btn-primary">Áp dụng</button></span>
                                </div>
                                <p class="text-muted mtop10 mbot0" id="kt-saas-bulk-plans-count"></p>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead><tr><th width="40"><div class="checkbox checkbox-primary mtop0 mbot0"><input type="checkbox" id="kt-saas-bulk-plans-all"><label for="kt-saas-bulk-plans-all"></label></div></th><th>Gói</th><th>Giá</th><th>Trạng thái</th><th>Giới hạn chính</th><th>Actions</th></tr></thead>
                                <tbody>
                                <?php foreach ($plans as $plan) { ?>
                                    <?php $deps = $plan_dependency_map[(int) $plan['id']] ?? []; ?>
                                    <?php $tenantCount = (int) ($deps['tenants'] ?? 0); ?>
                                    <tr>
                                        <td><div class="checkbox checkbox-primary mtop0 mbot0"><input type="checkbox" class="kt-saas-bulk-plans-item" name="ids[]" value="<?php echo (int) $plan['id']; ?>" id="kt-saas-plan-<?php echo (int) $plan['id']; ?>"><label for="kt-saas-plan-<?php echo (int) $plan['id']; ?>"></label></div></td>
                                        <td><strong><?php echo html_escape($plan['plan_name']); ?></strong><br><small><?php echo html_escape($plan['plan_code']); ?> | Tenants dùng: <?php echo $tenantCount; ?></small></td>
                                        <td><?php echo html_escape($plan['price'] . ' ' . $plan['currency']); ?><br><small><?php echo html_escape($plan['billing_cycle']); ?></small></td>
                                        <td>
                                            <span class="label label-<?php echo !empty($plan['is_active']) ? 'success' : 'default'; ?>"><?php echo !empty($plan['is_active']) ? 'Active' : 'Inactive'; ?></span>
                                            <span class="label label-<?php echo !empty($plan['is_public']) ? 'info' : 'default'; ?>"><?php echo !empty($plan['is_public']) ? 'Public' : 'Hidden'; ?></span>
                                        </td>
                                        <td><small>Staff: <?php echo (int) $plan['limit_staff']; ?> | Clients: <?php echo (int) $plan['limit_clients']; ?> | Storage: <?php echo (int) $plan['limit_storage_mb']; ?>MB | API: <?php echo (int) $plan['limit_api_requests_daily']; ?></small></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?php echo admin_url('kt_saas/plans/' . $plan['id']); ?>" class="btn btn-default">Edit</a>
                                                <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown"><span class="caret"></span></button>
                                                <ul class="dropdown-menu dropdown-menu-right">
                                                    <li><a href="#" class="kt-plan-action-post" data-action="<?php echo admin_url('kt_saas/plan_duplicate/' . $plan['id']); ?>">Duplicate</a></li>
                                                    <li><a href="#" class="kt-plan-action-post" data-action="<?php echo admin_url('kt_saas/plan_visibility/' . $plan['id'] . '/' . (!empty($plan['is_public']) ? 'hide' : 'show')); ?>"><?php echo !empty($plan['is_public']) ? 'Hide' : 'Show'; ?></a></li>
                                                    <li><a href="#" class="kt-plan-action-post" data-action="<?php echo admin_url('kt_saas/archive_plan/' . $plan['id']); ?>" data-confirm="Xác nhận archive gói này?">Archive</a></li>
                                                    <?php if (!empty($can_delete_plans)) { ?><li><a href="#" class="kt-plan-action-post text-danger" data-action="<?php echo admin_url('kt_saas/delete_plan/' . $plan['id']); ?>" data-confirm="Xác nhận xóa gói này?">Delete</a></li><?php } ?>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                                <?php if (empty($plans)) { ?><tr><td colspan="6"><?php echo _l('kt_saas_no_records'); ?></td></tr><?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
(function(){
    function postTo(actionUrl){
        var f = document.createElement('form');
        f.method = 'POST';
        f.action = actionUrl;
        if (window.csrfData && csrfData.token_name && csrfData.hash) {
            var t = document.createElement('input');
            t.type = 'hidden';
            t.name = csrfData.token_name;
            t.value = csrfData.hash;
            f.appendChild(t);
        }
        document.body.appendChild(f);
        f.submit();
    }

    document.querySelectorAll('.kt-plan-action-post').forEach(function(el){
        el.addEventListener('click', function(e){
            e.preventDefault();
            var msg = el.getAttribute('data-confirm');
            if (msg && !window.confirm(msg)) {
                return;
            }
            postTo(el.getAttribute('data-action'));
        });
    });

    var form = document.getElementById('kt-saas-bulk-plans-form');
    var toolbar = document.getElementById('kt-saas-bulk-plans-toolbar');
    var all = document.getElementById('kt-saas-bulk-plans-all');
    var items = Array.prototype.slice.call(document.querySelectorAll('.kt-saas-bulk-plans-item'));
    var count = document.getElementById('kt-saas-bulk-plans-count');
    var action = document.getElementById('kt-saas-bulk-plans-action');

    function update(){
        var selected = items.filter(function(i){ return i.checked; }).length;
        toolbar.style.display = selected > 0 ? '' : 'none';
        count.textContent = selected > 0 ? ('Đã chọn ' + selected + ' gói.') : '';
        if (all) {
            all.checked = selected > 0 && selected === items.length;
        }
    }

    if (all) {
        all.addEventListener('change', function(){
            items.forEach(function(i){ i.checked = all.checked; });
            update();
        });
    }

    items.forEach(function(i){ i.addEventListener('change', update); });
    update();

    if (form) {
        form.addEventListener('submit', function(e){
            var selected = items.filter(function(i){ return i.checked; }).length;
            if (!action.value || selected === 0) {
                e.preventDefault();
                alert('Chọn ít nhất một gói và một thao tác.');
                return;
            }
            var dangerous = ['archive','delete'];
            if (dangerous.indexOf(action.value) !== -1 && !window.confirm('Xác nhận thao tác hàng loạt: ' + action.value + '?')) {
                e.preventDefault();
            }
        });
    }
})();
</script>
