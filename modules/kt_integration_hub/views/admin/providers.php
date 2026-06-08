<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="panel_s">
            <div class="panel-body">
                <h4 class="tw-mt-0"><?php echo html_escape($title); ?></h4>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?php echo _l('kt_integration_hub_provider'); ?></th>
                                <th>Code</th>
                                <th>Type</th>
                                <th>OAuth</th>
                                <th>Webhook</th>
                                <th>Polling</th>
                                <th><?php echo _l('kt_integration_hub_status'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($providers as $provider) { ?>
                                <tr>
                                    <td><?php echo html_escape($provider['provider_name']); ?></td>
                                    <td><code><?php echo html_escape($provider['provider_code']); ?></code></td>
                                    <td><?php echo html_escape($provider['provider_type']); ?></td>
                                    <td><?php echo !empty($provider['supports_oauth']) ? 'Yes' : 'No'; ?></td>
                                    <td><?php echo !empty($provider['supports_webhook']) ? 'Yes' : 'No'; ?></td>
                                    <td><?php echo !empty($provider['supports_polling']) ? 'Yes' : 'No'; ?></td>
                                    <td><span class="label label-<?php echo !empty($provider['is_active']) ? 'success' : 'default'; ?>"><?php echo !empty($provider['is_active']) ? 'active' : 'inactive'; ?></span></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
