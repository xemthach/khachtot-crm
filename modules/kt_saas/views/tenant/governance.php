<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$summary = is_array($summary ?? null) ? $summary : [];
$staffMembers = is_array($staff_members ?? null) ? $staff_members : [];
$roles = is_array($roles ?? null) ? $roles : [];
$usage = is_array($usage ?? null) ? $usage : [];
$limits = is_array($profile['limits'] ?? null) ? $profile['limits'] : [];
$staffMetric = (float) ($usage['staff'] ?? 0);
$staffLimit = isset($staff_limit) && $staff_limit !== null ? (int) $staff_limit : null;
$canManageGovernance = !empty($can_manage_governance);
$canManageRoles = !empty($can_manage_roles);
$canViewStaffDirectory = !empty($can_view_staff_directory);
$canCreateStaff = !empty($can_create_staff);
$canManageDepartments = !empty($can_manage_departments);
$canViewDepartments = !empty($can_view_departments);
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-justify-between tw-items-center tw-flex-wrap tw-gap-3">
                    <div>
                        <h4 class="tw-my-0"><?php echo html_escape($title ?? _l('kt_saas_users_roles')); ?></h4>
                        <p class="text-muted tw-mt-2"><?php echo html_escape(_l('kt_saas_workspace_governance_intro')); ?></p>
                    </div>
                    <div class="tw-flex tw-gap-2 tw-flex-wrap">
                        <?php if ($canViewStaffDirectory) { ?>
                            <a href="<?php echo admin_url('staff'); ?>" class="btn btn-default">
                                <i class="fa fa-users tw-mr-1"></i>
                                <?php echo _l('kt_saas_staff_directory'); ?>
                            </a>
                        <?php } ?>
                        <?php if ($canCreateStaff) { ?>
                            <a href="<?php echo admin_url('staff/member'); ?>" class="btn btn-primary">
                                <i class="fa fa-user-plus tw-mr-1"></i>
                                <?php echo _l('kt_saas_add_staff'); ?>
                            </a>
                        <?php } ?>
                        <?php if ($canManageRoles) { ?>
                            <a href="<?php echo admin_url('kt_saas/tenant_role'); ?>" class="btn btn-success">
                                <i class="fa fa-shield tw-mr-1"></i>
                                <?php echo _l('kt_saas_add_role'); ?>
                            </a>
                        <?php } ?>
                        <?php if ($canViewDepartments) { ?>
                            <a href="<?php echo admin_url('kt_saas/tenant_departments'); ?>" class="btn btn-default">
                                <i class="fa fa-sitemap tw-mr-1"></i>
                                <?php echo _l('departments'); ?>
                            </a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row tw-mt-4">
            <div class="col-md-3">
                <div class="panel_s">
                    <div class="panel-body">
                        <p class="text-muted no-margin"><?php echo _l('kt_saas_active_staff'); ?></p>
                        <h3 class="tw-mt-2 tw-mb-0"><?php echo (int) ($summary['staff_count'] ?? 0); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel_s">
                    <div class="panel-body">
                        <p class="text-muted no-margin"><?php echo _l('kt_saas_tenant_admins'); ?></p>
                        <h3 class="tw-mt-2 tw-mb-0"><?php echo (int) ($summary['admin_count'] ?? 0); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel_s">
                    <div class="panel-body">
                        <p class="text-muted no-margin"><?php echo _l('kt_saas_custom_roles'); ?></p>
                        <h3 class="tw-mt-2 tw-mb-0"><?php echo (int) ($summary['role_count'] ?? 0); ?>
                            <small class="text-muted">/ <?php echo (int) ($limits['roles'] ?? 0) === 0 ? _l('kt_saas_unlimited') : (int) ($limits['roles'] ?? 0); ?></small>
                        </h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel_s">
                    <div class="panel-body">
                        <p class="text-muted no-margin"><?php echo _l('kt_saas_staff_limit_usage'); ?></p>
                        <h3 class="tw-mt-2 tw-mb-0">
                            <?php echo number_format($staffMetric, 0); ?>
                            <small class="text-muted">/ <?php echo $staffLimit === null ? _l('kt_saas_unlimited') : number_format($staffLimit, 0); ?></small>
                        </h3>
                        <p class="text-muted tw-mt-2 tw-mb-0"><?php echo _l('kt_saas_plan_limit'); ?>: <?php echo $staffLimit === null ? _l('kt_saas_unlimited') : number_format($staffLimit, 0); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3">
                <div class="panel_s">
                    <div class="panel-body">
                        <p class="text-muted no-margin"><?php echo _l('departments'); ?></p>
                        <h3 class="tw-mt-2 tw-mb-0"><?php echo (int) ($usage['departments'] ?? 0); ?>
                            <small class="text-muted">/ <?php echo (int) ($limits['departments'] ?? 0) === 0 ? _l('kt_saas_unlimited') : (int) ($limits['departments'] ?? 0); ?></small>
                        </h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel_s">
                    <div class="panel-body">
                        <p class="text-muted no-margin"><?php echo _l('kt_saas_limit_governance_viewers'); ?></p>
                        <h3 class="tw-mt-2 tw-mb-0"><?php echo (int) ($usage['governance_viewers'] ?? 0); ?>
                            <small class="text-muted">/ <?php echo (int) ($limits['governance_viewers'] ?? 0) === 0 ? _l('kt_saas_unlimited') : (int) ($limits['governance_viewers'] ?? 0); ?></small>
                        </h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel_s">
                    <div class="panel-body">
                        <p class="text-muted no-margin"><?php echo _l('kt_saas_limit_governance_managers'); ?></p>
                        <h3 class="tw-mt-2 tw-mb-0"><?php echo (int) ($usage['governance_managers'] ?? 0); ?>
                            <small class="text-muted">/ <?php echo (int) ($limits['governance_managers'] ?? 0) === 0 ? _l('kt_saas_unlimited') : (int) ($limits['governance_managers'] ?? 0); ?></small>
                        </h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-7">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="tw-flex tw-justify-between tw-items-center tw-flex-wrap tw-gap-3">
                            <div>
                                <h4 class="tw-my-0"><?php echo _l('kt_saas_staff_directory'); ?></h4>
                                <p class="text-muted tw-mt-2 tw-mb-0"><?php echo _l('kt_saas_manage_staff'); ?>: <code>/admin/staff</code></p>
                            </div>
                            <?php if ($canViewStaffDirectory) { ?>
                                <a href="<?php echo admin_url('staff'); ?>" class="btn btn-default btn-sm"><?php echo _l('view'); ?></a>
                            <?php } ?>
                        </div>

                        <div class="table-responsive tw-mt-4">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('staff_dt_name'); ?></th>
                                        <th><?php echo _l('email'); ?></th>
                                        <th><?php echo _l('role'); ?></th>
                                        <th><?php echo _l('admin'); ?></th>
                                        <th><?php echo _l('options'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($staffMembers as $staff) { ?>
                                        <tr>
                                            <td><?php echo html_escape(trim(((string) ($staff['firstname'] ?? '')) . ' ' . ((string) ($staff['lastname'] ?? '')))); ?></td>
                                            <td><?php echo html_escape((string) ($staff['email'] ?? '')); ?></td>
                                            <td>
                                                <?php
                                                $roleName = '-';
                                                foreach ($roles as $role) {
                                                    if ((int) ($role['roleid'] ?? 0) === (int) ($staff['role'] ?? 0)) {
                                                        $roleName = (string) ($role['name'] ?? '-');
                                                        break;
                                                    }
                                                }
                                                echo html_escape($roleName);
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ((int) ($staff['admin'] ?? 0) === 1) { ?>
                                                    <span class="label label-success">Co</span>
                                                <?php } else { ?>
                                                    <span class="label label-default">Không</span>
                                                <?php } ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo admin_url('staff/member/' . (int) ($staff['staffid'] ?? 0)); ?>" class="btn btn-default btn-sm">
                                                    <?php echo _l('kt_saas_edit'); ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    <?php if (empty($staffMembers)) { ?>
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

            <div class="col-md-5">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="tw-flex tw-justify-between tw-items-center tw-flex-wrap tw-gap-3">
                            <div>
                                <h4 class="tw-my-0"><?php echo _l('kt_saas_role_assignments'); ?></h4>
                                <p class="text-muted tw-mt-2 tw-mb-0"><?php echo html_escape(_l('kt_saas_role_delete_help')); ?></p>
                            </div>
                            <?php if ($canManageRoles) { ?>
                                <a href="<?php echo admin_url('kt_saas/tenant_role'); ?>" class="btn btn-success btn-sm">
                                    <?php echo _l('new_role'); ?>
                                </a>
                            <?php } ?>
                        </div>

                        <div class="table-responsive tw-mt-4">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('roles_dt_name'); ?></th>
                                        <th><?php echo _l('kt_saas_assigned_staff'); ?></th>
                                        <th><?php echo _l('kt_saas_capability_count'); ?></th>
                                        <th><?php echo _l('options'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($roles as $role) { ?>
                                        <tr>
                                            <td><?php echo html_escape((string) ($role['name'] ?? '')); ?></td>
                                            <td><?php echo (int) ($role['assigned_staff_count'] ?? 0); ?></td>
                                            <td><?php echo (int) ($role['capability_count'] ?? 0); ?></td>
                                            <td>
                                                <div class="tw-flex tw-gap-2 tw-flex-wrap">
                                                    <a href="<?php echo admin_url('kt_saas/tenant_role/' . (int) ($role['roleid'] ?? 0)); ?>" class="btn btn-default btn-sm">
                                                        <?php echo _l('kt_saas_manage_role'); ?>
                                                    </a>
                                                    <?php if ($canManageRoles) { ?>
                                                        <form action="<?php echo admin_url('kt_saas/tenant_delete_role/' . (int) ($role['roleid'] ?? 0)); ?>" method="post" class="tw-inline">
                                                            <?php echo form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
                                                            <button type="submit" class="btn btn-danger btn-sm" <?php echo (int) ($role['assigned_staff_count'] ?? 0) > 0 ? 'disabled' : ''; ?> onclick="return confirm('<?php echo html_escape(_l('confirm_action_prompt')); ?>');">
                                                                <?php echo _l('delete'); ?>
                                                            </button>
                                                        </form>
                                                    <?php } ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    <?php if (empty($roles)) { ?>
                                        <tr>
                                            <td colspan="4"><?php echo _l('kt_saas_no_records'); ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($canViewDepartments) { ?>
            <div class="row">
                <div class="col-md-12">
                    <div class="panel_s">
                        <div class="panel-body">
                            <div class="tw-flex tw-justify-between tw-items-center tw-flex-wrap tw-gap-3">
                                <div>
                                    <h4 class="tw-my-0"><?php echo _l('departments'); ?></h4>
                                    <p class="text-muted tw-mt-2 tw-mb-0">Phòng ban chỉ áp dụng trong doanh nghiệp này và được dùng để phân công nhân sự cũng như điều phối quy trình nội bộ.</p>
                                </div>
                                <a href="<?php echo admin_url('kt_saas/tenant_departments'); ?>" class="btn btn-default btn-sm">
                                    <?php echo _l('view'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
</div>
<?php init_tail(); ?>

