<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-6">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4><?php echo html_escape($title); ?></h4>
                        <p><strong><?php echo html_escape($tenant['tenant_code']); ?></strong> - <?php echo html_escape($tenant['company_name']); ?></p>
                        <p><strong>CSDL tenant:</strong> <?php echo html_escape($tenant['db_name'] ?: '-'); ?></p>
                        <p><strong><?php echo _l('kt_saas_status'); ?>:</strong> <?php echo html_escape((kt_saas_tenant_statuses()[$tenant['status']] ?? $tenant['status'])); ?> / <?php echo html_escape((kt_saas_provision_job_statuses()[$tenant['provisioning_status']] ?? $tenant['provisioning_status'])); ?></p>

                        <?php if (!empty($access_profile['success'])) { ?>
                            <?php $staff = $access_profile['staff']; ?>
                            <hr />
                            <p><strong>ID quản trị viên:</strong> <?php echo (int) $staff['staffid']; ?></p>
                            <p><strong>Email quản trị viên:</strong> <?php echo html_escape($staff['email']); ?></p>
                            <p><strong>Liên kết thiết lập mật khẩu:</strong> <?php echo !empty($staff['new_pass_key']) ? 'Đã tạo' : 'Chưa tạo'; ?></p>
                            <p><strong>Yêu cầu lúc:</strong> <?php echo !empty($staff['new_pass_key_requested']) ? html_escape($staff['new_pass_key_requested']) : '-'; ?></p>
                            <p>
                                <a href="<?php echo html_escape($access_profile['login_url']); ?>" class="btn btn-default" target="_blank" rel="noopener noreferrer">Mở trang đăng nhập tenant</a>
                            </p>
                        <?php } else { ?>
                            <div class="alert alert-warning"><?php echo html_escape($access_profile['message'] ?? 'Không thể lấy hồ sơ truy cập quản trị tenant.'); ?></div>
                        <?php } ?>

                        <p><a href="<?php echo admin_url('kt_saas/tenants'); ?>" class="btn btn-default"><?php echo _l('kt_saas_cancel'); ?></a></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4>Gửi lại email hướng dẫn</h4>
                        <p class="text-muted">Tạo liên kết thiết lập mật khẩu mới và gửi lại email hướng dẫn đăng nhập cho quản trị viên tenant.</p>
                        <?php echo form_open(admin_url('kt_saas/tenant_access/' . (int) $tenant['id'])); ?>
                        <input type="hidden" name="regenerate_onboarding" value="1">
                        <button type="submit" class="btn btn-info">Gửi lại email hướng dẫn</button>
                        <?php echo form_close(); ?>

                        <hr />

                        <h4>Đặt mật khẩu thủ công</h4>
                        <p class="text-muted">Đặt trực tiếp mật khẩu tạm thời cho tài khoản quản trị tenant.</p>
                        <?php echo form_open(admin_url('kt_saas/tenant_access/' . (int) $tenant['id'])); ?>
                        <input type="hidden" name="manual_password_submit" value="1">
                        <?php echo render_input('manual_password', 'Mật khẩu tạm thời', '', 'password', ['required' => true, 'minlength' => 8]); ?>
                        <button type="submit" class="btn btn-warning">Lưu mật khẩu thủ công</button>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
