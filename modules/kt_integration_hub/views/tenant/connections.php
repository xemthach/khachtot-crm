<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$providerMap = [];
foreach ($providers as $provider) {
    $providerMap[$provider['provider_code']] = $provider;
}
$selectedProviderCode = (string) ($edit_connection['provider_code'] ?? 'custom_webhook');
$selectedProvider = $providerMap[$selectedProviderCode] ?? reset($providerMap);
$generatedConnection = null;
if (!empty($generated_secret_connection_id)) {
    foreach ($connections as $connection) {
        if ((int) $connection['id'] === (int) $generated_secret_connection_id) {
            $generatedConnection = $connection;
            break;
        }
    }
}
?>
<div id="wrapper">
    <div class="content">
        <?php if (!empty($generated_secret) && $generatedConnection) { ?>
            <div class="alert alert-warning">
                <h4 class="tw-mt-0"><?php echo _l('kt_integration_hub_secret_copy_once'); ?></h4>
                <p><?php echo _l('kt_integration_hub_secret_copy_once_note'); ?></p>
                <div class="input-group mtop10">
                    <input type="text" class="form-control" readonly value="<?php echo html_escape($generated_secret); ?>" id="kt-generated-secret">
                    <span class="input-group-btn">
                        <button type="button" class="btn btn-default kt-copy-btn" data-copy-target="#kt-generated-secret"><?php echo _l('kt_integration_hub_copy_secret'); ?></button>
                    </span>
                </div>
                <pre class="mtop15" id="kt-generated-curl"><?php echo html_escape(kt_integration_hub_test_curl($generatedConnection, $generated_secret)); ?></pre>
                <button type="button" class="btn btn-default kt-copy-btn" data-copy-target="#kt-generated-curl"><?php echo _l('kt_integration_hub_copy_test_curl'); ?></button>
            </div>
        <?php } ?>

        <div class="row">
            <div class="col-md-4">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0"><?php echo $edit_connection ? _l('edit') : _l('kt_integration_hub_new_connection'); ?></h4>
                        <p class="text-muted"><?php echo _l('kt_integration_hub_provider_step_help'); ?></p>

                        <div class="form-group">
                            <label><?php echo _l('kt_integration_hub_provider'); ?></label>
                            <?php if ($edit_connection) { ?>
                                <input type="text" class="form-control" readonly value="<?php echo html_escape($selectedProvider['provider_name'] ?? $selectedProviderCode); ?>">
                            <?php } else { ?>
                                <select id="kt-provider-selector" class="form-control">
                                    <?php foreach ($providers as $provider) { ?>
                                        <option
                                            value="<?php echo html_escape($provider['provider_code']); ?>"
                                            data-auth-type="<?php echo html_escape($provider['auth_type'] ?? 'custom_hmac'); ?>"
                                            data-readiness="<?php echo html_escape($provider['readiness_status'] ?? 'planned'); ?>"
                                            <?php echo $selectedProviderCode === $provider['provider_code'] ? 'selected' : ''; ?>>
                                            <?php echo html_escape($provider['provider_name']); ?> - <?php echo html_escape($provider['readiness_status'] ?? 'planned'); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            <?php } ?>
                        </div>

                        <?php foreach ($providers as $provider) { ?>
                            <div class="kt-provider-panel" data-provider-panel="<?php echo html_escape($provider['provider_code']); ?>" style="display:none;">
                                <div class="alert alert-<?php echo ($provider['readiness_status'] ?? '') === 'ready' ? 'success' : 'info'; ?>">
                                    <strong><?php echo html_escape($provider['provider_name']); ?></strong>
                                    <div class="mtop5">
                                        <span class="label label-<?php echo kt_integration_hub_status_badge_class($provider['readiness_status'] ?? 'planned'); ?>"><?php echo html_escape($provider['readiness_status'] ?? 'planned'); ?></span>
                                        <span class="label label-default"><?php echo html_escape($provider['auth_type'] ?? 'custom_hmac'); ?></span>
                                        <?php if (!empty($provider['supports_webhook'])) { ?><span class="label label-info">webhook</span><?php } ?>
                                        <?php if (!empty($provider['supports_oauth'])) { ?><span class="label label-primary">oauth</span><?php } ?>
                                    </div>
                                    <?php if (!empty($provider['status_message'])) { ?>
                                        <p class="mtop10 mbot0"><?php echo html_escape($provider['status_message']); ?></p>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>

                        <div id="kt-provider-not-ready" class="alert alert-warning" style="display:none;">
                            <?php echo _l('kt_integration_hub_provider_not_ready'); ?>
                        </div>

                        <?php echo form_open(admin_url('kt_integration_hub/connections' . ($edit_connection ? '/' . (int) $edit_connection['id'] : '')), ['id' => 'kt-connection-form']); ?>
                        <input type="hidden" name="provider_code" id="kt-provider-code" value="<?php echo html_escape($selectedProviderCode); ?>">
                        <input type="hidden" name="auth_type" value="custom_hmac">

                        <div id="kt-custom-webhook-form">
                            <?php echo render_input('connection_name', 'kt_integration_hub_connection_name', $edit_connection['connection_name'] ?? 'Test Custom Webhook'); ?>
                            <?php echo render_input('external_account_name', 'kt_integration_hub_external_account', $edit_connection['external_account_name'] ?? ''); ?>

                            <?php if ($edit_connection) { ?>
                                <div class="form-group">
                                    <label><?php echo _l('kt_integration_hub_webhook_url'); ?></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" readonly id="kt-edit-webhook-url" value="<?php echo html_escape(kt_integration_hub_webhook_url($edit_connection)); ?>">
                                        <span class="input-group-btn">
                                            <button type="button" class="btn btn-default kt-copy-btn" data-copy-target="#kt-edit-webhook-url"><?php echo _l('kt_integration_hub_copy_url'); ?></button>
                                        </span>
                                    </div>
                                </div>
                                <pre id="kt-edit-test-curl"><?php echo html_escape(kt_integration_hub_test_curl($edit_connection)); ?></pre>
                                <button type="button" class="btn btn-default kt-copy-btn" data-copy-target="#kt-edit-test-curl"><?php echo _l('kt_integration_hub_copy_test_curl'); ?></button>
                                <hr>
                            <?php } else { ?>
                                <div class="alert alert-info">
                                    <?php echo _l('kt_integration_hub_secret_generated_note'); ?>
                                </div>
                            <?php } ?>

                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo (($edit_connection['status'] ?? 'connected') === 'connected') ? 'checked' : ''; ?>>
                                <label for="is_active"><?php echo _l('kt_integration_hub_active'); ?></label>
                            </div>
                            <button type="submit" id="kt-submit-connection" class="btn btn-primary"><?php echo _l('submit'); ?></button>
                        </div>
                        <?php echo form_close(); ?>

                        <?php if ($edit_connection && $selectedProviderCode === 'custom_webhook') { ?>
                            <div class="mtop15">
                                <?php echo form_open(admin_url('kt_integration_hub/rotate_secret/' . (int) $edit_connection['id'])); ?>
                                <button type="submit" class="btn btn-warning"><?php echo _l('kt_integration_hub_rotate_secret'); ?></button>
                                <?php echo form_close(); ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0"><?php echo _l('kt_integration_hub_local_testing'); ?></h4>
                        <p class="text-muted"><?php echo _l('kt_integration_hub_local_testing_note'); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0"><?php echo html_escape($title); ?></h4>
                        <?php $this->load->view('kt_integration_hub/tenant/partials_connections_table', ['connections' => $connections]); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
(function() {
    function selectProvider(code) {
        var selector = document.getElementById('kt-provider-selector');
        var hidden = document.getElementById('kt-provider-code');
        var form = document.getElementById('kt-custom-webhook-form');
        var blocked = document.getElementById('kt-provider-not-ready');
        var selected = selector ? selector.options[selector.selectedIndex] : null;
        var readiness = selected ? selected.getAttribute('data-readiness') : '<?php echo html_escape($selectedProvider['readiness_status'] ?? 'ready'); ?>';
        var authType = selected ? selected.getAttribute('data-auth-type') : '<?php echo html_escape($selectedProvider['auth_type'] ?? 'custom_hmac'); ?>';

        if (hidden) {
            hidden.value = code;
        }

        document.querySelectorAll('[data-provider-panel]').forEach(function(panel) {
            panel.style.display = panel.getAttribute('data-provider-panel') === code ? 'block' : 'none';
        });

        var canCreate = code === 'custom_webhook' && readiness === 'ready' && authType === 'custom_hmac';
        if (form) {
            form.style.display = canCreate ? 'block' : 'none';
        }
        if (blocked) {
            blocked.style.display = canCreate ? 'none' : 'block';
        }
    }

    var selector = document.getElementById('kt-provider-selector');
    if (selector) {
        selector.addEventListener('change', function() {
            selectProvider(this.value);
        });
        selectProvider(selector.value);
    } else {
        selectProvider('<?php echo html_escape($selectedProviderCode); ?>');
    }
})();
</script>
