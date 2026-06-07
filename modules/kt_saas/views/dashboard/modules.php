<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mb-2"><?php echo html_escape($title); ?></h4>
                <p>
                    <a href="<?php echo admin_url('kt_saas/modules'); ?>" class="btn btn-default">Làm mới</a>
                </p>
            </div>
        </div>

        <div class="row">
            <div class="col-md-7">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="clearfix">
                            <h4 class="pull-left"><?php echo _l('kt_saas_modules'); ?></h4>
                            <?php echo form_open(admin_url('kt_saas/modules'), ['class' => 'pull-right']); ?>
                            <input type="hidden" name="sync_catalog" value="1">
                            <button type="submit" class="btn btn-info">Đồng bộ danh mục</button>
                            <?php echo form_close(); ?>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Ứng dụng</th>
                                        <th>Phiên bản</th>
                                        <th>Trạng thái chung</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($catalog as $module) { ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo html_escape($module['display_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo html_escape($module['module_name']); ?></small>
                                            </td>
                                            <td><?php echo html_escape($module['version']); ?></td>
                                            <td>
                                                <span class="label label-<?php echo (int) $module['is_global_active'] === 1 ? 'success' : 'default'; ?>">
                                                    <?php echo (int) $module['is_global_active'] === 1 ? 'đang bật' : 'đang tắt'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo form_open(admin_url('kt_saas/modules')); ?>
                                                <input type="hidden" name="catalog_submit" value="1">
                                                <input type="hidden" name="module_name" value="<?php echo html_escape($module['module_name']); ?>">
                                                <input type="hidden" name="is_global_active" value="<?php echo (int) $module['is_global_active'] === 1 ? '0' : '1'; ?>">
                                                <button type="submit" class="btn btn-xs btn-<?php echo (int) $module['is_global_active'] === 1 ? 'warning' : 'success'; ?>">
                                                    <?php echo (int) $module['is_global_active'] === 1 ? 'Tắt ứng dụng' : 'Bật ứng dụng'; ?>
                                                </button>
                                                <?php echo form_close(); ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    <?php if (empty($catalog)) { ?><tr><td colspan="4"><?php echo _l('kt_saas_no_records'); ?></td></tr><?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4>Thiết lập riêng theo tenant</h4>
                        <?php echo form_open(admin_url('kt_saas/modules'), ['method' => 'get']); ?>
                        <div class="form-group">
                            <label for="tenant_id">Không gian SaaS</label>
                            <select name="tenant_id" id="tenant_id" class="form-control" onchange="this.form.submit();">
                                <option value="">Chọn không gian SaaS</option>
                                <?php foreach ($tenants as $tenant) { ?>
                                    <option value="<?php echo (int) $tenant['id']; ?>" <?php echo (int) $selected_tenant_id === (int) $tenant['id'] ? 'selected' : ''; ?>>
                                        <?php echo html_escape($tenant['tenant_code'] . ' - ' . $tenant['company_name']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <?php echo form_close(); ?>

                        <?php if ($selected_tenant_id > 0) { ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Ứng dụng</th>
                                            <th>Trạng thái</th>
                                            <th>Nguồn áp dụng</th>
                                            <th>Thiết lập riêng</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tenant_modules as $row) { ?>
                                            <tr>
                                                <td><?php echo html_escape($row['display_name'] ?: $row['module_name']); ?></td>
                                                <td>
                                                    <span class="label label-<?php echo $row['status'] === 'enabled' ? 'success' : 'default'; ?>">
                                                        <?php echo html_escape($row['status'] === 'enabled' ? 'Đang bật' : 'Đang tắt'); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo html_escape($row['source'] === 'override' ? 'Thiết lập riêng' : 'Kế thừa'); ?></td>
                                                <td>
                                                    <?php echo form_open(admin_url('kt_saas/modules')); ?>
                                                    <input type="hidden" name="tenant_override_submit" value="1">
                                                    <input type="hidden" name="tenant_id" value="<?php echo (int) $selected_tenant_id; ?>">
                                                    <input type="hidden" name="module_name" value="<?php echo html_escape($row['module_name']); ?>">
                                                    <select name="override_mode" class="form-control input-sm" onchange="this.form.submit();">
                                                        <option value="inherit">Kế thừa</option>
                                                        <option value="enable" <?php echo (int) ($row['overridden'] ?? 0) === 1 && (int) ($row['override_enabled'] ?? 0) === 1 ? 'selected' : ''; ?>>Bắt buộc bật</option>
                                                        <option value="disable" <?php echo (int) ($row['overridden'] ?? 0) === 1 && (int) ($row['override_enabled'] ?? 1) === 0 ? 'selected' : ''; ?>>Bắt buộc tắt</option>
                                                    </select>
                                                    <?php echo form_close(); ?>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                        <?php if (empty($tenant_modules)) { ?><tr><td colspan="4"><?php echo _l('kt_saas_no_records'); ?></td></tr><?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <p class="text-muted">Chọn một không gian SaaS để xem quyền sử dụng ứng dụng đang áp dụng.</p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
