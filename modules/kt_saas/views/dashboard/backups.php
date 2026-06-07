<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mb-2"><?php echo html_escape($title); ?></h4>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4>Tạo bản sao lưu</h4>
                        <?php echo form_open(admin_url('kt_saas/backups'), ['method' => 'get']); ?>
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
                            <p>
                                <a href="<?php echo admin_url('kt_saas/create_backup/' . (int) $selected_tenant_id); ?>" class="btn btn-primary">Tạo bản sao lưu CSDL</a>
                            </p>
                        <?php } else { ?>
                            <p class="text-muted">Chọn một không gian SaaS để tạo bản sao lưu.</p>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4>Lịch sử sao lưu</h4>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Không gian SaaS</th>
                                        <th>Trạng thái</th>
                                        <th>Kích thước</th>
                                        <th>Hoàn tất</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backups as $backup) { ?>
                                        <tr>
                                            <td><?php echo (int) $backup['id']; ?></td>
                                            <td><?php echo html_escape(($backup['tenant_code'] ?? '-') . ' - ' . ($backup['company_name'] ?? '')); ?></td>
                                            <td><span class="label label-<?php echo kt_saas_status_badge_class($backup['status']); ?>"><?php echo html_escape((kt_saas_provision_job_statuses()[$backup['status']] ?? $backup['status'])); ?></span></td>
                                            <td><?php echo number_format(((int) $backup['file_size_bytes']) / 1048576, 2); ?> MB</td>
                                            <td><?php echo !empty($backup['completed_at']) ? _dt($backup['completed_at']) : '-'; ?></td>
                                            <td>
                                                <?php if (!empty($backup['file_path'])) { ?>
                                                    <a href="<?php echo admin_url('kt_saas/download_backup/' . (int) $backup['id']); ?>" class="btn btn-xs btn-default">Tải xuống</a>
                                                    <a href="<?php echo admin_url('kt_saas/restore_backup/' . (int) $backup['id']); ?>" class="btn btn-xs btn-warning" onclick="return confirm('Khôi phục bản sao lưu này sẽ ghi đè CSDL tenant hiện tại. Tiếp tục?');">Khôi phục</a>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    <?php if (empty($backups)) { ?><tr><td colspan="6"><?php echo _l('kt_saas_no_records'); ?></td></tr><?php } ?>
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
