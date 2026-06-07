<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-4">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4><?php echo html_escape($edit_tenant ? _l('kt_saas_edit_tenant') : _l('kt_saas_add_tenant')); ?></h4>
                        <?php echo form_open(admin_url('kt_saas/tenants' . ($edit_tenant ? '/' . $edit_tenant['id'] : ''))); ?>
                        <div class="form-group">
                            <label for="company_name"><?php echo _l('kt_saas_company_name'); ?></label>
                            <div class="input-group">
                                <input type="text" name="company_name" id="company_name" class="form-control" required value="<?php echo html_escape($edit_tenant['company_name'] ?? ''); ?>">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default" id="kt-auto-generate-all">Tạo tự động</button>
                                </span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="tenant_code"><?php echo _l('kt_saas_tenant_code'); ?></label>
                            <div class="input-group">
                                <input type="text" name="tenant_code" id="tenant_code" class="form-control" required value="<?php echo html_escape($edit_tenant['tenant_code'] ?? ''); ?>">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default" id="kt-generate-tenant-code">Generate mã</button>
                                </span>
                            </div>
                            <small class="text-muted" id="status-tenant_code"></small>
                        </div>
                        <?php echo render_input('owner_name', 'kt_saas_owner_name', $edit_tenant['owner_name'] ?? '', 'text', ['required' => true]); ?>
                        <?php echo render_input('owner_email', 'kt_saas_owner_email', $edit_tenant['owner_email'] ?? '', 'email', ['required' => true]); ?>
                        <?php echo render_input('phone', 'kt_saas_phone', $edit_tenant['phone'] ?? ''); ?>
                        <div class="form-group">
                            <label for="plan_id"><?php echo _l('kt_saas_plan'); ?></label>
                            <select name="plan_id" id="plan_id" class="form-control selectpicker" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
                                <option value=""></option>
                                <?php foreach ($plans as $plan) { ?>
                                    <option value="<?php echo (int) $plan['id']; ?>" <?php echo isset($edit_tenant['plan_id']) && (int) $edit_tenant['plan_id'] === (int) $plan['id'] ? 'selected' : ''; ?>><?php echo html_escape($plan['plan_name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status"><?php echo _l('kt_saas_status'); ?></label>
                            <select name="status" id="status" class="form-control selectpicker">
                                <?php foreach ($statuses as $key => $label) { ?>
                                    <option value="<?php echo html_escape($key); ?>" <?php echo ($edit_tenant['status'] ?? 'draft') === $key ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="subdomain"><?php echo _l('kt_saas_subdomain'); ?></label>
                            <div class="input-group">
                                <input type="text" name="subdomain" id="subdomain" class="form-control" value="<?php echo html_escape($edit_tenant['subdomain'] ?? ''); ?>">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default" id="kt-generate-subdomain">Generate subdomain</button>
                                </span>
                            </div>
                            <small class="text-muted" id="status-subdomain"></small>
                        </div>
                        <div class="form-group">
                            <label for="custom_domain"><?php echo _l('kt_saas_custom_domain'); ?></label>
                            <input type="text" name="custom_domain" id="custom_domain" class="form-control" value="<?php echo html_escape($edit_tenant['custom_domain'] ?? ''); ?>">
                            <small class="text-muted" id="status-custom_domain"></small>
                        </div>
                        <div class="form-group">
                            <label for="db_name"><?php echo _l('kt_saas_db_name'); ?></label>
                            <input type="text" name="db_name" id="db_name" class="form-control" value="<?php echo html_escape($edit_tenant['db_name'] ?? ''); ?>">
                            <small class="text-muted" id="status-db_name"></small>
                        </div>
                        <?php echo render_input('db_host', 'kt_saas_db_host', $edit_tenant['db_host'] ?? kt_saas_get_option('kt_saas_default_db_host', '127.0.0.1')); ?>
                        <?php echo render_input('db_port', 'kt_saas_db_port', $edit_tenant['db_port'] ?? kt_saas_get_option('kt_saas_default_db_port', '3306')); ?>
                        <div class="form-group">
                            <label for="db_user"><?php echo _l('kt_saas_db_user'); ?></label>
                            <input type="text" name="db_user" id="db_user" class="form-control" value="<?php echo html_escape($edit_tenant['db_user'] ?? ''); ?>">
                            <small class="text-muted" id="status-db_user"></small>
                        </div>
                        <div class="form-group">
                            <label for="db_password"><?php echo _l('kt_saas_db_password'); ?></label>
                            <div class="input-group">
                                <input type="password" name="db_password" id="db_password" class="form-control" <?php echo empty($edit_tenant) ? 'required' : ''; ?>>
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default" id="kt-generate-db-info">Generate database info</button>
                                    <button type="button" class="btn btn-default" id="kt-generate-password">Generate password</button>
                                </span>
                            </div>
                            <small class="text-muted">Tạo mới bắt buộc password >= 20 ký tự. Khi sửa: để trống sẽ giữ password cũ.</small>
                        </div>
                        <?php echo render_date_input('expires_at', 'kt_saas_expires_at', isset($edit_tenant['expires_at']) && $edit_tenant['expires_at'] ? _d(date('Y-m-d', strtotime($edit_tenant['expires_at']))) : ''); ?>
                        <button type="submit" class="btn btn-primary"><?php echo _l('kt_saas_save'); ?></button>
                        <?php if ($edit_tenant) { ?><a href="<?php echo admin_url('kt_saas/tenants'); ?>" class="btn btn-default"><?php echo _l('kt_saas_cancel'); ?></a><?php } ?>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-flex tw-items-center tw-justify-between"><?php echo html_escape($title); ?></h4>
                        <div class="mbot15">
                            <?php if (!empty($show_deleted)) { ?>
                                <a href="<?php echo admin_url('kt_saas/tenants'); ?>" class="btn btn-default btn-sm">Ẩn tenant đã xóa mềm</a>
                            <?php } else { ?>
                                <a href="<?php echo admin_url('kt_saas/tenants?include_deleted=1'); ?>" class="btn btn-default btn-sm">Hiện tenant đã xóa mềm</a>
                            <?php } ?>
                            <?php if (!empty($can_purge_tenants)) { ?>
                                <a href="<?php echo admin_url('kt_saas/tenant_orphans'); ?>" class="btn btn-warning btn-sm">Scan orphan tenant data</a>
                            <?php } ?>
                        </div>

                        <?php if (!empty($orphan_report)) { ?>
                        <div class="alert alert-warning">
                            <strong>Orphan tenant data scan:</strong>
                            <div class="table-responsive mtop10">
                                <table class="table table-condensed">
                                    <thead>
                                        <tr>
                                            <th>Resource</th>
                                            <th>Key</th>
                                            <th>Count</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orphan_report as $orphan) { ?>
                                        <tr>
                                            <td><?php echo html_escape($orphan['resource'] ?? ''); ?></td>
                                            <td><?php echo html_escape($orphan['key'] ?? ''); ?></td>
                                            <td><?php echo (int) ($orphan['count'] ?? 0); ?></td>
                                            <td><?php echo html_escape($orphan['action'] ?? 'report_only'); ?></td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="mbot0">Report only. No data was deleted.</p>
                        </div>
                        <?php } ?>

                        <?php echo form_open(admin_url('kt_saas/bulk_tenants'), ['id' => 'kt-saas-bulk-tenants-form']); ?>
                        <div class="row mbot15" id="kt-saas-bulk-tenants-toolbar" style="display:none;">
                            <div class="col-md-12">
                                <div class="input-group">
                                    <select name="bulk_action" id="kt-saas-bulk-tenants-action" class="form-control">
                                        <option value="">Chọn thao tác hàng loạt</option>
                                        <option value="activate">Activate selected</option>
                                        <option value="suspend">Suspend selected</option>
                                        <option value="archive">Archive selected</option>
                                        <?php if (!empty($can_delete_tenants)) { ?><option value="delete">Delete selected</option><?php } ?>
                                        <option value="queue_provision">Queue provisioning selected</option>
                                        <option value="export">Export selected</option>
                                    </select>
                                    <span class="input-group-btn">
                                        <button type="submit" class="btn btn-primary" id="kt-saas-bulk-tenants-submit">Áp dụng</button>
                                    </span>
                                </div>
                                <p class="text-muted mtop10 mbot0" id="kt-saas-bulk-tenants-count"></p>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th width="40"><div class="checkbox checkbox-primary mtop0 mbot0"><input type="checkbox" id="kt-saas-bulk-tenants-all"><label for="kt-saas-bulk-tenants-all"></label></div></th>
                                        <th><?php echo _l('kt_saas_tenant_code'); ?></th>
                                        <th><?php echo _l('kt_saas_company_name'); ?></th>
                                        <th><?php echo _l('kt_saas_plan'); ?></th>
                                        <th><?php echo _l('kt_saas_status'); ?></th>
                                        <th><?php echo _l('kt_saas_actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tenants as $tenant) { $onboarding = kt_saas_tenant_onboarding($tenant); ?>
                                    <tr>
                                        <td><div class="checkbox checkbox-primary mtop0 mbot0"><input type="checkbox" class="kt-saas-bulk-tenants-item" name="ids[]" value="<?php echo (int) $tenant['id']; ?>" id="kt-saas-tenant-<?php echo (int) $tenant['id']; ?>"><label for="kt-saas-tenant-<?php echo (int) $tenant['id']; ?>"></label></div></td>
                                        <td><strong><?php echo html_escape($tenant['tenant_code']); ?></strong></td>
                                        <td><?php echo html_escape($tenant['company_name']); ?><br><small><?php echo html_escape($tenant['owner_email']); ?></small></td>
                                        <td><?php echo html_escape($tenant['plan_name'] ?: '-'); ?></td>
                                        <td>
                                            <span class="label label-<?php echo kt_saas_status_badge_class($tenant['status']); ?>"><?php echo html_escape((kt_saas_tenant_statuses()[$tenant['status']] ?? $tenant['status'])); ?></span>
                                            <?php if (!empty($tenant['deleted_at'])) { ?><br><small class="text-danger">Deleted: <?php echo html_escape($tenant['deleted_at']); ?></small><?php } ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?php echo admin_url('kt_saas/tenant_access/' . $tenant['id']); ?>" class="btn btn-warning">Truy cập</a>
                                                <a href="<?php echo admin_url('kt_saas/tenants/' . $tenant['id']); ?>" class="btn btn-default"><?php echo _l('kt_saas_edit'); ?></a>
                                                <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-expanded="false">Thao tác khác <span class="caret"></span></button>
                                                <ul class="dropdown-menu dropdown-menu-right">
                                                    <li><a href="#" class="kt-tenant-action-post" data-action="<?php echo admin_url('kt_saas/queue_provision_job/' . $tenant['id']); ?>">Xếp hàng khởi tạo</a></li>
                                                    <li><a target="_blank" rel="noopener" href="<?php echo admin_url('kt_saas/workspace_isolation_audit/' . $tenant['id']); ?>">Kiểm tra tách biệt dữ liệu (JSON)</a></li>
                                                    <li><a target="_blank" rel="noopener" href="<?php echo admin_url('kt_saas/workspace_isolation_audit_report/' . $tenant['id']); ?>">Kiểm tra tách biệt dữ liệu (HTML)</a></li>
                                                    <?php if (!empty($onboarding['admin_login_url'])) { ?><li><a target="_blank" rel="noopener" href="<?php echo html_escape($onboarding['admin_login_url']); ?>">Đăng nhập doanh nghiệp</a></li><?php } ?>
                                                    <?php if (!empty($onboarding['set_password_url'])) { ?><li><a target="_blank" rel="noopener" href="<?php echo html_escape($onboarding['set_password_url']); ?>">Thiết lập ban đầu</a></li><?php } ?>
                                                    <li class="divider"></li>
                                                    <li><a href="#" class="kt-tenant-action-post" data-action="<?php echo admin_url('kt_saas/tenant_status/' . $tenant['id'] . '/active'); ?>">Activate</a></li>
                                                    <li><a href="#" class="kt-tenant-action-post text-warning" data-action="<?php echo admin_url('kt_saas/tenant_status/' . $tenant['id'] . '/suspended'); ?>" data-confirm="Xác nhận suspend tenant này?">Suspend</a></li>
                                                    <li><a href="#" class="kt-tenant-action-post text-danger" data-action="<?php echo admin_url('kt_saas/tenant_status/' . $tenant['id'] . '/terminated'); ?>" data-confirm="Xác nhận terminate tenant này?">Terminate</a></li>
                                                    <li><a href="#" class="kt-tenant-action-post" data-action="<?php echo admin_url('kt_saas/archive_tenant/' . $tenant['id']); ?>" data-confirm="Xác nhận archive tenant này?">Archive</a></li>
                                                    <?php if (!empty($can_delete_tenants) && empty($tenant['deleted_at'])) { ?>
                                                    <li class="divider"></li>
                                                    <li><a href="#" class="text-danger kt-open-delete-tenant" data-id="<?php echo (int) $tenant['id']; ?>" data-code="<?php echo html_escape($tenant['tenant_code']); ?>">Delete</a></li>
                                                     <?php } ?>
                                                     <?php if (!empty($can_purge_tenants)) { ?>
                                                     <li class="divider"></li>
                                                     <li><a href="#" class="text-danger kt-open-purge-tenant" data-id="<?php echo (int) $tenant['id']; ?>" data-code="<?php echo html_escape($tenant['tenant_code']); ?>">Xóa vĩnh viễn / Purge</a></li>
                                                     <?php } ?>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                    <?php if (empty($tenants)) { ?><tr><td colspan="6"><?php echo _l('kt_saas_no_records'); ?></td></tr><?php } ?>
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

<div class="modal fade" id="kt-tenant-delete-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <?php echo form_open('', ['id' => 'kt-tenant-delete-form']); ?>
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title">Xóa tenant</h4>
            </div>
            <div class="modal-body">
                <p class="text-danger">Hành động này sẽ xóa mềm/lưu trữ tenant. Nếu tenant đang hoạt động hoặc có dữ liệu liên quan, hệ thống sẽ chặn hard delete.</p>
                <p>Nhập lại mã tenant để xác nhận: <strong id="kt-tenant-delete-code-label"></strong></p>
                <input type="text" name="confirm_code" id="kt-tenant-delete-code-input" class="form-control" required>
                <div class="checkbox checkbox-primary mtop10">
                    <input type="checkbox" id="kt-tenant-force-delete" name="force_delete" value="1">
                    <label for="kt-tenant-force-delete">Tôi hiểu tenant có thể có dữ liệu liên quan và yêu cầu ép xóa mềm.</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-danger">Xác nhận xóa</button>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>
</div>

<?php if (!empty($can_purge_tenants)) { ?>
<div class="modal fade" id="kt-tenant-purge-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <?php echo form_open('', ['id' => 'kt-tenant-purge-form']); ?>
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title text-danger">Danger Zone: Xóa vĩnh viễn tenant</h4>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    Thao tác này sẽ xóa database, file, backup, domain, lịch sử test và toàn bộ dữ liệu liên quan tenant. Không thể hoàn tác.
                </div>
                <p>Nhập chính xác một trong hai chuỗi xác nhận:</p>
                <ul>
                    <li><code id="kt-tenant-purge-code-confirm"></code></li>
                    <li><code id="kt-tenant-purge-id-confirm"></code></li>
                </ul>
                <input type="text" name="purge_confirmation" id="kt-tenant-purge-input" class="form-control" required autocomplete="off">
                <p class="text-muted mtop10 mbot0">Purge chỉ hoạt động khi môi trường không phải production hoặc bật flag KT_SAAS_ALLOW_HARD_DELETE.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-danger">Xóa vĩnh viễn</button>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>
</div>
<?php } ?>

<?php init_tail(); ?>
<script>
(function(){
    var tenantEditId = <?php echo (int) ($edit_tenant['id'] ?? 0); ?>;
    var genBase = '<?php echo admin_url('kt_saas/tenant_generate_profile'); ?>';
    var checkBase = '<?php echo admin_url('kt_saas/tenant_field_check'); ?>';
    if (tenantEditId > 0) {
        genBase += '/' + tenantEditId;
    }
    function q(sel){ return document.querySelector(sel); }
    function setVal(id, val){ var el = q('#'+id); if (el) { el.value = val || ''; } }
    function setStatus(field, text, cls){
        var el = q('#status-' + field);
        if (!el) { return; }
        el.className = cls || 'text-muted';
        el.textContent = text || '';
    }
    function url(path, params){
        var u = path;
        var qs = new URLSearchParams(params || {});
        var qv = qs.toString();
        return qv ? (u + (u.indexOf('?') === -1 ? '?' : '&') + qv) : u;
    }
    function fetchJson(endpoint, cb){
        fetch(endpoint, {credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(cb)
            .catch(function(){});
    }
    function checkField(field, value){
        if (!value) {
            setStatus(field, '', 'text-muted');
            return;
        }
        var endpoint = checkBase + '/' + encodeURIComponent(field) + (tenantEditId > 0 ? '/' + tenantEditId : '');
        fetchJson(url(endpoint, {value: value}), function(resp){
            if (!resp || !resp.success || !resp.data) { return; }
            var d = resp.data;
            if (!d.valid) {
                setStatus(field, 'Invalid', 'text-danger');
                return;
            }
            if (d.normalized) {
                setVal(field, d.normalized);
            }
            setStatus(field, d.available ? 'Available' : 'Duplicated', d.available ? 'text-success' : 'text-danger');
        });
    }
    function generateAll(){
        var company = (q('#company_name') && q('#company_name').value) ? q('#company_name').value : '';
        fetchJson(url(genBase, {company_name: company}), function(resp){
            if (!resp || !resp.success || !resp.data) { return; }
            setVal('tenant_code', resp.data.tenant_code);
            setVal('subdomain', resp.data.subdomain);
            setVal('db_name', resp.data.db_name);
            setVal('db_user', resp.data.db_user);
            setVal('db_password', resp.data.db_password);
            checkField('tenant_code', resp.data.tenant_code);
            checkField('subdomain', resp.data.subdomain);
            checkField('db_name', resp.data.db_name);
            checkField('db_user', resp.data.db_user);
        });
    }

    var form = document.getElementById('kt-saas-bulk-tenants-form');
    var toolbar = document.getElementById('kt-saas-bulk-tenants-toolbar');
    var checkAll = document.getElementById('kt-saas-bulk-tenants-all');
    var items = Array.prototype.slice.call(document.querySelectorAll('.kt-saas-bulk-tenants-item'));
    var count = document.getElementById('kt-saas-bulk-tenants-count');
    var action = document.getElementById('kt-saas-bulk-tenants-action');

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

    function updateBulk(){
        var selected = items.filter(function(i){ return i.checked; }).length;
        toolbar.style.display = selected > 0 ? '' : 'none';
        count.textContent = selected > 0 ? ('Đã chọn ' + selected + ' tenant.') : '';
        if (checkAll) {
            checkAll.checked = selected > 0 && selected === items.length;
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function(){
            items.forEach(function(i){ i.checked = checkAll.checked; });
            updateBulk();
        });
    }
    items.forEach(function(i){ i.addEventListener('change', updateBulk); });
    updateBulk();

    if (form) {
        form.addEventListener('submit', function(e){
            var selected = items.filter(function(i){ return i.checked; }).length;
            if (!action.value || selected === 0) {
                e.preventDefault();
                alert('Chọn ít nhất một tenant và một thao tác.');
                return;
            }
            var dangerous = ['suspend', 'archive', 'delete'];
            if (dangerous.indexOf(action.value) !== -1 && !window.confirm('Xác nhận thao tác hàng loạt: ' + action.value + '?')) {
                e.preventDefault();
            }
        });
    }

    document.querySelectorAll('.kt-tenant-action-post').forEach(function(el){
        el.addEventListener('click', function(e){
            e.preventDefault();
            var msg = el.getAttribute('data-confirm');
            if (msg && !window.confirm(msg)) {
                return;
            }
            postTo(el.getAttribute('data-action'));
        });
    });

    var deleteForm = document.getElementById('kt-tenant-delete-form');
    var codeLabel = document.getElementById('kt-tenant-delete-code-label');
    var codeInput = document.getElementById('kt-tenant-delete-code-input');

    document.querySelectorAll('.kt-open-delete-tenant').forEach(function(el){
        el.addEventListener('click', function(e){
            e.preventDefault();
            var id = el.getAttribute('data-id');
            var code = el.getAttribute('data-code') || '';
            codeLabel.textContent = code;
            codeInput.value = '';
            deleteForm.action = '<?php echo admin_url('kt_saas/delete_tenant/'); ?>' + id;
            $('#kt-tenant-delete-modal').modal('show');
        });
    });

    var purgeForm = document.getElementById('kt-tenant-purge-form');
    var purgeCodeConfirm = document.getElementById('kt-tenant-purge-code-confirm');
    var purgeIdConfirm = document.getElementById('kt-tenant-purge-id-confirm');
    var purgeInput = document.getElementById('kt-tenant-purge-input');

    document.querySelectorAll('.kt-open-purge-tenant').forEach(function(el){
        el.addEventListener('click', function(e){
            e.preventDefault();
            if (!purgeForm) { return; }
            var id = el.getAttribute('data-id');
            var code = el.getAttribute('data-code') || '';
            purgeCodeConfirm.textContent = 'PURGE ' + code;
            purgeIdConfirm.textContent = 'PURGE ' + id;
            purgeInput.value = '';
            purgeForm.action = '<?php echo admin_url('kt_saas/purge_tenant/'); ?>' + id;
            $('#kt-tenant-purge-modal').modal('show');
        });
    });

    var autoAllBtn = q('#kt-auto-generate-all');
    var genCodeBtn = q('#kt-generate-tenant-code');
    var genSubBtn = q('#kt-generate-subdomain');
    var genDbBtn = q('#kt-generate-db-info');
    var genPassBtn = q('#kt-generate-password');
    var companyInput = q('#company_name');

    if (autoAllBtn) { autoAllBtn.addEventListener('click', generateAll); }
    if (genCodeBtn) { genCodeBtn.addEventListener('click', function(){ fetchJson(genBase, function(r){ if (r && r.success && r.data) { setVal('tenant_code', r.data.tenant_code); checkField('tenant_code', r.data.tenant_code); } }); }); }
    if (genSubBtn) { genSubBtn.addEventListener('click', function(){ var company = companyInput ? companyInput.value : ''; fetchJson(url(genBase, {company_name: company}), function(r){ if (r && r.success && r.data) { setVal('subdomain', r.data.subdomain); checkField('subdomain', r.data.subdomain); } }); }); }
    if (genDbBtn) { genDbBtn.addEventListener('click', function(){ var company = companyInput ? companyInput.value : ''; fetchJson(url(genBase, {company_name: company}), function(r){ if (r && r.success && r.data) { setVal('db_name', r.data.db_name); setVal('db_user', r.data.db_user); setVal('db_password', r.data.db_password); checkField('db_name', r.data.db_name); checkField('db_user', r.data.db_user); } }); }); }
    if (genPassBtn) { genPassBtn.addEventListener('click', function(){ fetchJson(genBase, function(r){ if (r && r.success && r.data) { setVal('db_password', r.data.db_password); } }); }); }
    if (companyInput) { companyInput.addEventListener('blur', function(){ if (companyInput.value.trim() !== '') { generateAll(); } }); }

    ['tenant_code', 'subdomain', 'db_name', 'db_user', 'custom_domain'].forEach(function(field){
        var el = q('#' + field);
        if (!el) { return; }
        el.addEventListener('blur', function(){ checkField(field, el.value); });
    });
})();
</script>
