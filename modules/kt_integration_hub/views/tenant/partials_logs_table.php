<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Time</th>
                <th>Level</th>
                <th>Provider</th>
                <th>Event</th>
                <th>Message</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log) { ?>
                <tr>
                    <td><?php echo html_escape($log['created_at']); ?></td>
                    <td><span class="label label-<?php echo $log['level'] === 'error' ? 'danger' : ($log['level'] === 'warning' ? 'warning' : 'info'); ?>"><?php echo html_escape($log['level']); ?></span></td>
                    <td><?php echo html_escape($log['provider_code'] ?? '-'); ?></td>
                    <td><?php echo html_escape($log['event']); ?></td>
                    <td><?php echo html_escape($log['message']); ?></td>
                </tr>
            <?php } ?>
            <?php if (empty($logs)) { ?>
                <tr><td colspan="5" class="text-muted"><?php echo _l('kt_integration_hub_no_records'); ?></td></tr>
            <?php } ?>
        </tbody>
    </table>
</div>
