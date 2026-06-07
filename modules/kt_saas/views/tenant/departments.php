<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$departments = is_array($departments ?? null) ? $departments : [];
$departmentUsage = is_array($department_usage ?? null) ? $department_usage : [];
$editDepartment = $edit_department ?? null;
$canManageDepartments = !empty($can_manage_departments);
$isEditing = is_object($editDepartment) && !empty($editDepartment->departmentid);
$csrfTokenName = $this->security->get_csrf_token_name();
$csrfTokenHash = $this->security->get_csrf_hash();
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-5">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="tw-flex tw-justify-between tw-items-center tw-flex-wrap tw-gap-3">
                            <div>
                                <h4 class="tw-my-0"><?php echo html_escape($isEditing ? _l('edit_department') : _l('new_department')); ?></h4>
                                <p class="text-muted tw-mt-2 tw-mb-0">Dữ liệu phòng ban chỉ áp dụng cho doanh nghiệp hiện tại.</p>
                            </div>
                            <a href="<?php echo admin_url('kt_saas/tenant_governance'); ?>" class="btn btn-default btn-sm">
                                <?php echo _l('kt_saas_users_roles'); ?>
                            </a>
                        </div>

                        <hr class="hr-panel-heading" />
                        <?php if ($canManageDepartments) { ?>
                            <?php echo form_open(admin_url('kt_saas/tenant_departments')); ?>
                            <?php if ($isEditing) { ?>
                                <input type="hidden" name="id" value="<?php echo (int) $editDepartment->departmentid; ?>">
                            <?php } ?>
                            <?php echo render_input('name', 'department_name', $isEditing ? $editDepartment->name : ''); ?>
                            <?php echo render_input('email', 'department_email', $isEditing ? $editDepartment->email : '', 'email'); ?>
                            <?php echo render_input('calendar_id', 'department_calendar_id', $isEditing ? $editDepartment->calendar_id : ''); ?>
                            <?php echo render_input('imap_username', 'department_username', $isEditing ? $editDepartment->imap_username : ''); ?>
                            <?php echo render_input('password', 'password', '', 'password'); ?>
                            <?php echo render_input('host', 'host', $isEditing ? $editDepartment->host : ''); ?>
                            <?php echo render_select('encryption', [
                                ['id' => '', 'name' => 'Không có'],
                                ['id' => 'ssl', 'name' => 'SSL'],
                                ['id' => 'tls', 'name' => 'TLS'],
                            ], ['id', 'name'], 'encryption', $isEditing ? $editDepartment->encryption : ''); ?>
                            <?php echo render_input('folder', 'folder', $isEditing ? $editDepartment->folder : ''); ?>
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" name="hidefromclient" id="hidefromclient" value="1" <?php echo $isEditing && !empty($editDepartment->hidefromclient) ? 'checked' : ''; ?>>
                                <label for="hidefromclient"><?php echo _l('department_hide_from_client'); ?></label>
                            </div>
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" name="delete_after_import" id="delete_after_import" value="1" <?php echo $isEditing && !empty($editDepartment->delete_after_import) ? 'checked' : ''; ?>>
                                <label for="delete_after_import"><?php echo _l('department_delete_mail_after_import'); ?></label>
                            </div>
                            <div class="btn-bottom-toolbar text-right">
                                <?php if ($isEditing) { ?>
                                    <a href="<?php echo admin_url('kt_saas/tenant_departments'); ?>" class="btn btn-default"><?php echo _l('cancel'); ?></a>
                                <?php } ?>
                                <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
                            </div>
                            <?php echo form_close(); ?>
                        <?php } else { ?>
                            <div class="alert alert-info">Bạn có thể xem cấu trúc phòng ban tại đây. Chỉ người được phân quyền mới có thể tạo hoặc chỉnh sửa phòng ban.</div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="tw-flex tw-justify-between tw-items-center tw-flex-wrap tw-gap-3">
                            <div>
                                <h4 class="tw-my-0"><?php echo _l('departments'); ?></h4>
                                <p class="text-muted tw-mt-2 tw-mb-0">Số lượng nhân sự được tính theo danh sách nhân sự trong doanh nghiệp hiện tại.</p>
                            </div>
                        </div>

                        <div class="table-responsive tw-mt-4">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('department_list_name'); ?></th>
                                        <th><?php echo _l('department_email'); ?></th>
                                        <th><?php echo _l('kt_saas_assigned_staff'); ?></th>
                                        <th><?php echo _l('department_calendar_id'); ?></th>
                                        <th><?php echo _l('options'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($departments as $department) { ?>
                                        <?php $departmentId = (int) ($department['departmentid'] ?? 0); ?>
                                        <tr>
                                            <td><?php echo html_escape((string) ($department['name'] ?? '')); ?></td>
                                            <td><?php echo html_escape((string) ($department['email'] ?? '')); ?></td>
                                            <td><?php echo (int) ($departmentUsage[$departmentId] ?? 0); ?></td>
                                            <td><?php echo html_escape((string) ($department['calendar_id'] ?? '')); ?></td>
                                            <td>
                                                <div class="tw-flex tw-gap-2 tw-flex-wrap">
                                                    <?php if ($canManageDepartments) { ?>
                                                        <a href="<?php echo admin_url('kt_saas/tenant_departments?edit=' . $departmentId); ?>" class="btn btn-default btn-sm">
                                                            <?php echo _l('kt_saas_edit'); ?>
                                                        </a>
                                                    <?php } ?>
                                                    <?php if ($canManageDepartments) { ?>
                                                        <form action="<?php echo admin_url('kt_saas/tenant_delete_department/' . $departmentId); ?>" method="post" class="tw-inline">
                                                            <?php echo form_hidden($csrfTokenName, $csrfTokenHash); ?>
                                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('<?php echo html_escape(_l('confirm_action_prompt')); ?>');">
                                                                <?php echo _l('delete'); ?>
                                                            </button>
                                                        </form>
                                                    <?php } ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    <?php if (empty($departments)) { ?>
                                        <tr>
                                            <td colspan="5"><?php echo _l('kt_saas_no_records'); ?></td>
                                        </tr>
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
