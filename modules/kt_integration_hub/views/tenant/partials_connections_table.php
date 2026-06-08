<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th><?php echo _l('kt_integration_hub_provider'); ?></th>
                <th><?php echo _l('kt_integration_hub_connection_name'); ?></th>
                <th><?php echo _l('kt_integration_hub_status'); ?></th>
                <th><?php echo _l('kt_integration_hub_webhook_url'); ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($connections as $connection) { ?>
                <tr>
                    <td><?php echo html_escape($connection['provider_name'] ?? $connection['provider_code']); ?></td>
                    <td><?php echo html_escape($connection['connection_name'] ?? ''); ?></td>
                    <td><span class="label label-<?php echo kt_integration_hub_status_badge_class($connection['status']); ?>"><?php echo html_escape($connection['status']); ?></span></td>
                    <td>
                        <?php if (in_array(($connection['provider_code'] ?? ''), ['custom_webhook', 'zalo_oa', 'tiktok_shop'], true)) { ?>
                            <code id="kt-webhook-url-<?php echo (int) $connection['id']; ?>"><?php echo html_escape(kt_integration_hub_webhook_url($connection)); ?></code>
                            <button type="button" class="btn btn-default btn-xs kt-copy-btn" data-copy-target="#kt-webhook-url-<?php echo (int) $connection['id']; ?>"><?php echo _l('kt_integration_hub_copy_url'); ?></button>
                            <?php
                            $curl = kt_integration_hub_test_curl($connection);
                            if (($connection['provider_code'] ?? '') === 'zalo_oa') {
                                $curl = kt_integration_hub_zalo_test_curl($connection);
                            } elseif (($connection['provider_code'] ?? '') === 'tiktok_shop') {
                                $curl = kt_integration_hub_tiktok_test_curl($connection);
                            }
                            ?>
                            <pre class="hide" id="kt-webhook-curl-<?php echo (int) $connection['id']; ?>"><?php echo html_escape($curl); ?></pre>
                            <button type="button" class="btn btn-default btn-xs kt-copy-btn" data-copy-target="#kt-webhook-curl-<?php echo (int) $connection['id']; ?>"><?php echo _l('kt_integration_hub_copy_test_curl'); ?></button>
                        <?php } else { ?>
                            <span class="text-muted"><?php echo _l('kt_integration_hub_not_applicable'); ?></span>
                        <?php } ?>
                    </td>
                    <td class="text-right">
                        <a href="<?php echo admin_url('kt_integration_hub/connections/' . (int) $connection['id']); ?>" class="btn btn-default btn-sm"><?php echo _l('edit'); ?></a>
                        <a href="<?php echo admin_url('kt_integration_hub/disconnect/' . (int) $connection['id']); ?>" class="btn btn-danger btn-sm _delete"><?php echo _l('kt_integration_hub_disconnect'); ?></a>
                    </td>
                </tr>
            <?php } ?>
            <?php if (empty($connections)) { ?>
                <tr><td colspan="5" class="text-muted"><?php echo _l('kt_integration_hub_no_records'); ?></td></tr>
            <?php } ?>
        </tbody>
    </table>
</div>
<script>
(function() {
    if (window.ktIntegrationHubCopyBound) {
        return;
    }
    window.ktIntegrationHubCopyBound = true;
    document.addEventListener('click', function(event) {
        var button = event.target.closest ? event.target.closest('.kt-copy-btn') : null;
        if (!button) {
            return;
        }
        var target = document.querySelector(button.getAttribute('data-copy-target'));
        if (!target) {
            return;
        }
        var value = target.value !== undefined ? target.value : target.textContent;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value);
        }
    });
})();
</script>
