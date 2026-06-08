<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Provider</th>
                <th>Type</th>
                <th>External ID</th>
                <th>Status</th>
                <th>Attempts</th>
                <th>Updated</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($jobs as $job) { ?>
                <tr>
                    <td><?php echo (int) $job['id']; ?></td>
                    <td><?php echo html_escape($job['provider_code']); ?></td>
                    <td><?php echo html_escape($job['job_type']); ?></td>
                    <td><?php echo html_escape($job['external_id']); ?></td>
                    <td><span class="label label-<?php echo kt_integration_hub_status_badge_class($job['status']); ?>"><?php echo html_escape($job['status']); ?></span></td>
                    <td><?php echo (int) $job['attempts']; ?> / <?php echo (int) $job['max_attempts']; ?></td>
                    <td><?php echo html_escape($job['updated_at'] ?? '-'); ?></td>
                    <td class="text-right">
                        <?php if (in_array((string) $job['status'], ['failed', 'retry'], true)) { ?>
                            <a href="<?php echo admin_url('kt_integration_hub/retry_job/' . (int) $job['id']); ?>" class="btn btn-default btn-sm"><?php echo _l('kt_integration_hub_retry'); ?></a>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
            <?php if (empty($jobs)) { ?>
                <tr><td colspan="8" class="text-muted"><?php echo _l('kt_integration_hub_no_records'); ?></td></tr>
            <?php } ?>
        </tbody>
    </table>
</div>
