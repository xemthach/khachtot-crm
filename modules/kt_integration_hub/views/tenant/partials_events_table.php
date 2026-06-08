<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Provider</th>
                <th>Type</th>
                <th>External ID</th>
                <th>Signature</th>
                <th>Status</th>
                <th>Received</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($events as $event) { ?>
                <tr>
                    <td><?php echo (int) $event['id']; ?></td>
                    <td><?php echo html_escape($event['provider_code']); ?></td>
                    <td><?php echo html_escape($event['event_type']); ?></td>
                    <td><?php echo html_escape($event['external_event_id']); ?></td>
                    <td><?php echo html_escape($event['signature_status']); ?></td>
                    <td><span class="label label-<?php echo kt_integration_hub_status_badge_class($event['processing_status']); ?>"><?php echo html_escape($event['processing_status']); ?></span></td>
                    <td><?php echo html_escape($event['received_at']); ?></td>
                </tr>
            <?php } ?>
            <?php if (empty($events)) { ?>
                <tr><td colspan="7" class="text-muted"><?php echo _l('kt_integration_hub_no_records'); ?></td></tr>
            <?php } ?>
        </tbody>
    </table>
</div>
