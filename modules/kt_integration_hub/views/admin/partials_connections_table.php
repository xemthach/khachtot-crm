<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Tenant</th>
                <th><?php echo _l('kt_integration_hub_provider'); ?></th>
                <th><?php echo _l('kt_integration_hub_connection_name'); ?></th>
                <th><?php echo _l('kt_integration_hub_status'); ?></th>
                <th><?php echo _l('kt_integration_hub_last_sync'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($connections as $connection) { ?>
                <tr>
                    <td><?php echo (int) $connection['id']; ?></td>
                    <td><?php echo (int) $connection['tenant_id']; ?></td>
                    <td><?php echo html_escape($connection['provider_name'] ?? $connection['provider_code']); ?></td>
                    <td><?php echo html_escape($connection['connection_name'] ?? ''); ?></td>
                    <td><span class="label label-<?php echo kt_integration_hub_status_badge_class($connection['status']); ?>"><?php echo html_escape($connection['status']); ?></span></td>
                    <td><?php echo html_escape($connection['last_sync_at'] ?? '-'); ?></td>
                </tr>
            <?php } ?>
            <?php if (empty($connections)) { ?>
                <tr><td colspan="6" class="text-muted"><?php echo _l('kt_integration_hub_no_records'); ?></td></tr>
            <?php } ?>
        </tbody>
    </table>
</div>
