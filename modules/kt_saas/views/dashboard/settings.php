<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="panel_s"><div class="panel-body">
            <h4><?php echo html_escape($title); ?></h4>
            <?php echo form_open(current_url()); ?>
            <?php echo render_input('kt_saas_base_domain', 'kt_saas_base_domain', kt_saas_get_option('kt_saas_base_domain', 'crm.local')); ?>
            <?php echo render_input('kt_saas_default_db_host', 'kt_saas_default_db_host', kt_saas_get_option('kt_saas_default_db_host', '127.0.0.1')); ?>
            <?php echo render_input('kt_saas_default_db_port', 'kt_saas_default_db_port', kt_saas_get_option('kt_saas_default_db_port', '3306')); ?>
            <?php echo render_input('kt_saas_default_locale', 'kt_saas_default_locale', kt_saas_get_option('kt_saas_default_locale', 'english')); ?>
            <?php echo render_input('kt_saas_default_timezone', 'kt_saas_default_timezone', kt_saas_get_option('kt_saas_default_timezone', 'UTC')); ?>
            <?php echo render_input('kt_saas_default_currency', 'kt_saas_default_currency', kt_saas_get_option('kt_saas_default_currency', 'USD')); ?>
            <?php echo render_input('kt_saas_default_storage_driver', 'kt_saas_default_storage_driver', kt_saas_get_option('kt_saas_default_storage_driver', 'local')); ?>
            <?php echo render_input('kt_saas_queue_mode', 'kt_saas_queue_mode', kt_saas_get_option('kt_saas_queue_mode', 'database')); ?>
            <?php echo render_input('kt_saas_db_user_prefix', 'kt_saas_db_user_prefix', kt_saas_get_option('kt_saas_db_user_prefix', 'tenant_')); ?>
            <?php echo render_input('kt_saas_default_db_client_hosts', 'kt_saas_default_db_client_hosts', kt_saas_get_option('kt_saas_default_db_client_hosts', 'localhost,127.0.0.1')); ?>
            <?php echo render_input('kt_saas_landlord_host', 'kt_saas_landlord_host', kt_saas_get_option('kt_saas_landlord_host', parse_url(APP_BASE_URL, PHP_URL_HOST))); ?>
            <?php echo render_input('kt_saas_usage_retention_days', 'kt_saas_usage_retention_days', kt_saas_get_option('kt_saas_usage_retention_days', '90'), 'number', ['min' => 7]); ?>
            <?php echo render_input('kt_saas_backup_retention_days', 'kt_saas_backup_retention_days', kt_saas_get_option('kt_saas_backup_retention_days', '30'), 'number', ['min' => 1]); ?>
            <?php echo render_input('kt_saas_billing_due_days', 'kt_saas_billing_due_days', kt_saas_get_option('kt_saas_billing_due_days', '7'), 'number', ['min' => 0]); ?>
            <?php echo render_input('kt_saas_billing_dunning_interval_days', 'kt_saas_billing_dunning_interval_days', kt_saas_get_option('kt_saas_billing_dunning_interval_days', '2'), 'number', ['min' => 1]); ?>
            <?php echo render_input('kt_saas_billing_dunning_max_attempts', 'kt_saas_billing_dunning_max_attempts', kt_saas_get_option('kt_saas_billing_dunning_max_attempts', '3'), 'number', ['min' => 1]); ?>
            <?php echo render_input('kt_saas_payment_link_secret', 'kt_saas_payment_link_secret', kt_saas_get_option('kt_saas_payment_link_secret', APP_ENC_KEY)); ?>
            <?php echo render_input('kt_saas_payment_webhook_secret', 'kt_saas_payment_webhook_secret', kt_saas_get_option('kt_saas_payment_webhook_secret', APP_ENC_KEY)); ?>
            <?php echo render_textarea('kt_saas_overage_rate_json', 'kt_saas_overage_rate_json', kt_saas_get_option('kt_saas_overage_rate_json', json_encode(kt_saas_default_overage_rates(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))); ?>
            <hr />
            <h4>Global Email Provider</h4>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="kt_saas_global_email_provider">Provider</label>
                        <select class="form-control" name="kt_saas_global_email_provider" id="kt_saas_global_email_provider">
                            <option value="system_smtp" <?php echo ($global_email_provider ?? '') === 'system_smtp' ? 'selected' : ''; ?>>System SMTP</option>
                            <option value="brevo_smtp" <?php echo ($global_email_provider ?? '') === 'brevo_smtp' ? 'selected' : ''; ?>>Brevo SMTP</option>
                            <option value="brevo_api" <?php echo ($global_email_provider ?? '') === 'brevo_api' ? 'selected' : ''; ?>>Brevo API</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4"><?php echo render_input('kt_saas_global_sender_name', 'Sender name', $global_sender_name ?? ''); ?></div>
                <div class="col-md-4"><?php echo render_input('kt_saas_global_sender_email', 'Sender email', $global_sender_email ?? ''); ?></div>
            </div>
            <div class="row">
                <div class="col-md-4"><?php echo render_input('kt_saas_global_reply_to_email', 'Reply-to email', $global_reply_to_email ?? ''); ?></div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="kt_saas_global_email_fallback_policy">Fallback policy</label>
                        <select class="form-control" name="kt_saas_global_email_fallback_policy" id="kt_saas_global_email_fallback_policy">
                            <option value="use_landlord" <?php echo ($global_email_fallback_policy ?? 'use_landlord') === 'use_landlord' ? 'selected' : ''; ?>>Use landlord global</option>
                            <option value="block_sending" <?php echo ($global_email_fallback_policy ?? '') === 'block_sending' ? 'selected' : ''; ?>>Block sending</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="alert alert-info global-email-provider-group" data-provider-group="system_smtp">
                System SMTP uses core Perfex email settings in <strong>Setup → Settings → Email</strong>. Brevo-specific fields are not required for this provider.
            </div>
            <div class="row global-email-provider-group" data-provider-group="brevo_smtp">
                <div class="col-md-4"><?php echo render_input('kt_saas_global_brevo_smtp_host', 'Brevo SMTP host', $global_brevo_smtp_host ?? 'smtp-relay.brevo.com'); ?></div>
                <div class="col-md-2"><?php echo render_input('kt_saas_global_brevo_smtp_port', 'Brevo SMTP port', $global_brevo_smtp_port ?? '587'); ?></div>
                <div class="col-md-2"><?php echo render_input('kt_saas_global_brevo_smtp_encryption', 'Encryption', $global_brevo_smtp_encryption ?? 'tls'); ?></div>
                <div class="col-md-4"><?php echo render_input('kt_saas_global_brevo_smtp_user', 'Brevo SMTP username', $global_brevo_smtp_user ?? ''); ?></div>
            </div>
            <div class="row global-email-provider-group" data-provider-group="brevo_smtp">
                <div class="col-md-6">
                    <?php echo render_input('kt_saas_global_brevo_smtp_pass', 'Brevo SMTP password', '', 'password'); ?>
                    <?php if (!empty($global_brevo_smtp_has_password)) { ?><p class="text-muted">Password saved. Keep blank to retain.</p><?php } ?>
                </div>
            </div>
            <div class="row global-email-provider-group" data-provider-group="brevo_api">
                <div class="col-md-6">
                    <?php echo render_input('kt_saas_global_brevo_sender_name', 'Brevo API sender name', (string) get_option('kt_saas_global_brevo_sender_name')); ?>
                </div>
                <div class="col-md-6">
                    <?php echo render_input('kt_saas_global_brevo_sender_email', 'Brevo API sender email', (string) get_option('kt_saas_global_brevo_sender_email')); ?>
                </div>
            </div>
            <div class="row global-email-provider-group" data-provider-group="brevo_api">
                <div class="col-md-6">
                    <?php echo render_input('kt_saas_global_brevo_reply_to_email', 'Brevo API reply-to email', (string) get_option('kt_saas_global_brevo_reply_to_email')); ?>
                </div>
                <div class="col-md-6">
                    <?php echo render_input('kt_saas_global_brevo_api_key', 'Brevo API key', '', 'password'); ?>
                    <?php if (!empty($global_brevo_api_has_key)) { ?><p class="text-muted">API key saved. Keep blank to retain.</p><?php } ?>
                </div>
            </div>
            <div class="checkbox checkbox-primary"><input type="checkbox" id="kt_saas_auto_create_db_user" name="kt_saas_auto_create_db_user" value="1" <?php echo kt_saas_get_option('kt_saas_auto_create_db_user', '1') === '1' ? 'checked' : ''; ?>><label for="kt_saas_auto_create_db_user"><?php echo _l('kt_saas_auto_create_db_user'); ?></label></div>
            <div class="checkbox checkbox-primary"><input type="checkbox" id="kt_saas_allow_custom_domains" name="kt_saas_allow_custom_domains" value="1" <?php echo kt_saas_get_option('kt_saas_allow_custom_domains', '1') === '1' ? 'checked' : ''; ?>><label for="kt_saas_allow_custom_domains"><?php echo _l('kt_saas_allow_custom_domains'); ?></label></div>
            <div class="checkbox checkbox-primary"><input type="checkbox" id="kt_saas_runtime_enabled" name="kt_saas_runtime_enabled" value="1" <?php echo kt_saas_get_option('kt_saas_runtime_enabled', '0') === '1' ? 'checked' : ''; ?>><label for="kt_saas_runtime_enabled"><?php echo _l('kt_saas_runtime_enabled'); ?></label></div>
            <button type="submit" class="btn btn-primary"><?php echo _l('kt_saas_save'); ?></button>
            <?php echo form_close(); ?>
            <hr />
            <?php echo form_open(admin_url('kt_saas/settings_email_test')); ?>
            <div class="row">
                <div class="col-md-6"><?php echo render_input('test_email', 'Test email recipient', ''); ?></div>
                <div class="col-md-6 tw-pt-7"><button type="submit" class="btn btn-default">Test global email connection</button></div>
            </div>
            <?php echo form_close(); ?>
        </div></div>
    </div>
</div>
<?php init_tail(); ?>
<script>
(function () {
    'use strict';
    var provider = document.getElementById('kt_saas_global_email_provider');
    if (!provider) {
        return;
    }
    var groups = document.querySelectorAll('.global-email-provider-group');
    function setGroupInputsEnabled(group, enabled) {
        var inputs = group.querySelectorAll('input, select, textarea');
        for (var i = 0; i < inputs.length; i++) {
            inputs[i].disabled = !enabled;
        }
    }
    function refresh() {
        var value = provider.value || 'system_smtp';
        for (var i = 0; i < groups.length; i++) {
            var group = groups[i];
            var target = group.getAttribute('data-provider-group');
            var show = target === value;
            group.style.display = show ? '' : 'none';
            setGroupInputsEnabled(group, show);
        }
    }
    provider.addEventListener('change', refresh);
    refresh();
})();
</script>
