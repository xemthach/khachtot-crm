<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="panel_s"><div class="panel-body">
            <h4><?php echo html_escape($title); ?></h4>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead><tr><th>ID</th><th><?php echo _l('kt_saas_tenant'); ?></th><th><?php echo _l('kt_saas_job_type'); ?></th><th><?php echo _l('kt_saas_status'); ?></th><th><?php echo _l('kt_saas_attempts'); ?></th><th><?php echo _l('kt_saas_scheduled_at'); ?></th><th><?php echo _l('kt_saas_actions'); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($jobs as $job) { ?>
                            <tr>
                                <td>#<?php echo (int) $job['id']; ?></td>
                                <td><?php echo html_escape(($job['tenant_code'] ?: '-') . ($job['company_name'] ? ' - ' . $job['company_name'] : '')); ?></td>
                                <td><?php echo html_escape($job['job_type']); ?></td>
                                <td><span class="label label-<?php echo kt_saas_status_badge_class($job['status']); ?>"><?php echo html_escape((kt_saas_provision_job_statuses()[$job['status']] ?? $job['status'])); ?></span></td>
                                <td><?php echo (int) $job['attempts']; ?>/<?php echo (int) $job['max_attempts']; ?></td>
                                <td><?php echo $job['scheduled_at'] ? _dt($job['scheduled_at']) : '-'; ?></td>
                                <td>
                                    <?php
                                        $jobResult = !empty($job['result_json']) ? json_decode($job['result_json'], true) : [];
                                        $tenantAdmin = is_array($jobResult) ? ($jobResult['tenant_admin'] ?? []) : [];
                                    ?>
                                    <?php if (in_array($job['status'], ['queued', 'failed'], true)) { ?>
                                        <a href="<?php echo admin_url('kt_saas/run_provision_job/' . $job['id']); ?>" class="btn btn-success btn-sm"><?php echo _l('kt_saas_run_now'); ?></a>
                                    <?php } ?>
                                    <?php if (in_array($job['status'], ['failed', 'done'], true)) { ?>
                                        <a href="<?php echo admin_url('kt_saas/retry_provision_job/' . $job['id']); ?>" class="btn btn-default btn-sm"><?php echo _l('kt_saas_retry'); ?></a>
                                    <?php } ?>
                                    <?php if (!empty($tenantAdmin['onboarding_link_generated'])) { ?>
                                        <span class="label label-info">Đã tạo link</span>
                                    <?php } ?>
                                    <?php if (!empty($tenantAdmin['admin_login_url'])) { ?>
                                        <a href="<?php echo html_escape($tenantAdmin['admin_login_url']); ?>" class="btn btn-default btn-sm" target="_blank" rel="noopener noreferrer">Đăng nhập</a>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                        <?php if (empty($jobs)) { ?><tr><td colspan="7"><?php echo _l('kt_saas_no_records'); ?></td></tr><?php } ?>
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
</div>
<?php init_tail(); ?>
