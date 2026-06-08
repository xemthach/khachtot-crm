<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$providerMap = [];
foreach ($providers as $provider) {
    $providerMap[$provider['provider_code']] = $provider;
}
$selectedProviderCode = (string) ($edit_connection['provider_code'] ?? 'custom_webhook');
$selectedProvider = $providerMap[$selectedProviderCode] ?? reset($providerMap);
$connectionSettings = kt_integration_hub_json_decode((string) ($edit_connection['settings_json'] ?? ''), []);
$storedAccessToken = $edit_connection ? kt_integration_hub_decrypt_value($edit_connection['access_token_encrypted'] ?? '') : '';
$storedRefreshToken = $edit_connection ? kt_integration_hub_decrypt_value($edit_connection['refresh_token_encrypted'] ?? '') : '';
$tokenExpiresAt = '';
if (!empty($connectionSettings['token_expires_at'])) {
    $tokenExpiresAt = str_replace(' ', 'T', substr((string) $connectionSettings['token_expires_at'], 0, 16));
}
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
                        <input type="hidden" name="auth_type" id="kt-auth-type" value="<?php echo html_escape($selectedProvider['auth_type'] ?? 'custom_hmac'); ?>">

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

                        <div id="kt-zalo-oa-form" style="display:none;">
                            <div class="alert alert-info">
                                <strong>Zalo OA V1 Beta</strong>
                                <p class="mbot0"><?php echo _l('kt_integration_hub_zalo_beta_note'); ?></p>
                            </div>
                            <div class="panel_s">
                                <div class="panel-body">
                                    <h5 class="tw-mt-0"><?php echo _l('kt_integration_hub_zalo_quick_guide'); ?></h5>
                                    <ol class="text-muted tw-pl-4">
                                        <li><?php echo _l('kt_integration_hub_zalo_quick_step_app'); ?></li>
                                        <li><?php echo _l('kt_integration_hub_zalo_quick_step_token'); ?></li>
                                        <li><?php echo _l('kt_integration_hub_zalo_quick_step_webhook'); ?></li>
                                        <li><?php echo _l('kt_integration_hub_zalo_quick_step_test'); ?></li>
                                    </ol>
                                    <p class="mbot0"><a href="<?php echo base_url('docs/ZALO_OA_TENANT_SETUP_GUIDE.md'); ?>" target="_blank" rel="noopener"><?php echo _l('kt_integration_hub_zalo_full_guide'); ?></a></p>
                                </div>
                            </div>

                            <?php echo render_input('connection_name', 'kt_integration_hub_connection_name', $edit_connection['connection_name'] ?? 'Zalo OA'); ?>
                            <div class="form-group">
                                <label><?php echo _l('kt_integration_hub_connection_mode'); ?></label>
                                <select name="connection_mode" class="form-control">
                                    <option value="manual_token" <?php echo (($connectionSettings['connection_mode'] ?? 'manual_token') === 'manual_token') ? 'selected' : ''; ?>><?php echo _l('kt_integration_hub_zalo_manual_token_mode'); ?></option>
                                    <option value="oauth_prepared" <?php echo (($connectionSettings['connection_mode'] ?? '') === 'oauth_prepared') ? 'selected' : ''; ?>><?php echo _l('kt_integration_hub_zalo_oauth_prepared_mode'); ?></option>
                                </select>
                            </div>
                            <?php echo render_input('app_id', 'kt_integration_hub_zalo_app_id', $connectionSettings['app_id'] ?? ''); ?>
                            <?php echo render_input('app_secret', 'kt_integration_hub_zalo_app_secret', '', 'password', ['autocomplete' => 'new-password']); ?>
                            <p class="text-muted"><?php echo _l('kt_integration_hub_zalo_secret_blank'); ?></p>
                            <?php echo render_input('oa_id', 'kt_integration_hub_zalo_oa_id', $edit_connection['external_account_id'] ?? ($connectionSettings['oa_id'] ?? '')); ?>
                            <?php echo render_input('external_account_name', 'kt_integration_hub_external_account', $edit_connection['external_account_name'] ?? ''); ?>
                            <?php echo render_input('access_token', 'kt_integration_hub_zalo_access_token', '', 'password', ['autocomplete' => 'new-password']); ?>
                            <?php if ($storedAccessToken !== '') { ?><p class="text-muted"><?php echo _l('kt_integration_hub_token_stored'); ?>: <code><?php echo html_escape(kt_integration_hub_mask_secret($storedAccessToken)); ?></code></p><?php } ?>
                            <?php echo render_input('refresh_token', 'kt_integration_hub_zalo_refresh_token', '', 'password', ['autocomplete' => 'new-password']); ?>
                            <?php if ($storedRefreshToken !== '') { ?><p class="text-muted"><?php echo _l('kt_integration_hub_refresh_token_stored'); ?>: <code><?php echo html_escape(kt_integration_hub_mask_secret($storedRefreshToken)); ?></code></p><?php } ?>
                            <?php echo render_input('token_expires_at', 'kt_integration_hub_token_expires_at', $tokenExpiresAt, 'datetime-local'); ?>
                            <?php echo render_input('default_lead_source', 'kt_integration_hub_default_lead_source', $connectionSettings['default_lead_source'] ?? 'Zalo OA'); ?>
                            <?php echo render_input('lead_assigned', 'kt_integration_hub_lead_assigned', $connectionSettings['lead_assigned'] ?? 0, 'number'); ?>
                            <?php echo render_input('lead_status', 'kt_integration_hub_lead_status', $connectionSettings['lead_status'] ?? 0, 'number'); ?>
                            <input type="hidden" name="allow_unsigned_test_webhook" value="0">
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" id="allow_unsigned_test_webhook" name="allow_unsigned_test_webhook" value="1" <?php echo !empty($connectionSettings['allow_unsigned_test_webhook']) || (!$edit_connection && ENVIRONMENT !== 'production') ? 'checked' : ''; ?>>
                                <label for="allow_unsigned_test_webhook"><?php echo _l('kt_integration_hub_allow_unsigned_test_webhook'); ?></label>
                            </div>
                            <input type="hidden" name="create_lead_on_follow" value="0">
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" id="create_lead_on_follow" name="create_lead_on_follow" value="1" <?php echo !isset($connectionSettings['create_lead_on_follow']) || !empty($connectionSettings['create_lead_on_follow']) ? 'checked' : ''; ?>>
                                <label for="create_lead_on_follow"><?php echo _l('kt_integration_hub_create_lead_on_follow'); ?></label>
                            </div>

                            <?php if ($edit_connection && $selectedProviderCode === 'zalo_oa') { ?>
                                <div class="form-group">
                                    <label><?php echo _l('kt_integration_hub_webhook_url'); ?></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" readonly id="kt-zalo-webhook-url" value="<?php echo html_escape(kt_integration_hub_webhook_url($edit_connection)); ?>">
                                        <span class="input-group-btn">
                                            <button type="button" class="btn btn-default kt-copy-btn" data-copy-target="#kt-zalo-webhook-url"><?php echo _l('kt_integration_hub_copy_url'); ?></button>
                                        </span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label><?php echo _l('kt_integration_hub_oauth_callback_url'); ?></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" readonly id="kt-zalo-oauth-url" value="<?php echo html_escape(kt_integration_hub_oauth_callback_url($edit_connection, 'zalo_oa')); ?>">
                                        <span class="input-group-btn">
                                            <button type="button" class="btn btn-default kt-copy-btn" data-copy-target="#kt-zalo-oauth-url"><?php echo _l('kt_integration_hub_copy_url'); ?></button>
                                        </span>
                                    </div>
                                </div>
                                <pre id="kt-zalo-test-curl"><?php echo html_escape(kt_integration_hub_zalo_test_curl($edit_connection)); ?></pre>
                                <button type="button" class="btn btn-default kt-copy-btn" data-copy-target="#kt-zalo-test-curl"><?php echo _l('kt_integration_hub_copy_test_curl'); ?></button>
                            <?php } else { ?>
                                <div class="alert alert-warning"><?php echo _l('kt_integration_hub_zalo_save_first'); ?></div>
                            <?php } ?>

                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" id="zalo_is_active" name="is_active" value="1" <?php echo (($edit_connection['status'] ?? 'connected') === 'connected') ? 'checked' : ''; ?>>
                                <label for="zalo_is_active"><?php echo _l('kt_integration_hub_active'); ?></label>
                            </div>
                            <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
                            <button type="button" class="btn btn-default disabled" data-keep-disabled="1" disabled><?php echo _l('kt_integration_hub_zalo_connect_button'); ?></button>
                            <p class="text-muted mtop10"><?php echo _l('kt_integration_hub_zalo_oauth_pending'); ?></p>
                        </div>

                        <div id="kt-tiktok-shop-form" style="display:none;">
                            <div class="alert alert-info">
                                <strong>TikTok Shop V1 Beta</strong>
                                <p class="mbot0"><?php echo _l('kt_integration_hub_tiktok_beta_note'); ?></p>
                            </div>
                            <div class="alert alert-warning">
                                <?php echo _l('kt_integration_hub_tiktok_staging_warning'); ?>
                            </div>

                            <?php echo render_input('connection_name', 'kt_integration_hub_connection_name', $edit_connection['connection_name'] ?? 'TikTok Shop'); ?>
                            <div class="form-group">
                                <label><?php echo _l('kt_integration_hub_connection_mode'); ?></label>
                                <select name="connection_mode" class="form-control">
                                    <option value="dry_run" <?php echo (($connectionSettings['connection_mode'] ?? 'dry_run') === 'dry_run') ? 'selected' : ''; ?>><?php echo _l('kt_integration_hub_tiktok_dry_run_mode'); ?></option>
                                    <option value="manual_credentials" <?php echo (($connectionSettings['connection_mode'] ?? '') === 'manual_credentials') ? 'selected' : ''; ?>><?php echo _l('kt_integration_hub_tiktok_manual_mode'); ?></option>
                                    <option value="oauth_prepared" <?php echo (($connectionSettings['connection_mode'] ?? '') === 'oauth_prepared') ? 'selected' : ''; ?>><?php echo _l('kt_integration_hub_tiktok_oauth_prepared_mode'); ?></option>
                                </select>
                            </div>
                            <?php echo render_input('app_key', 'kt_integration_hub_tiktok_app_key', $connectionSettings['app_key'] ?? ''); ?>
                            <?php echo render_input('app_secret', 'kt_integration_hub_tiktok_app_secret', '', 'password', ['autocomplete' => 'new-password']); ?>
                            <p class="text-muted"><?php echo _l('kt_integration_hub_tiktok_secret_blank'); ?></p>
                            <?php echo render_input('shop_id', 'kt_integration_hub_tiktok_shop_id', $edit_connection['external_account_id'] ?? ($connectionSettings['shop_id'] ?? '')); ?>
                            <?php echo render_input('shop_cipher', 'kt_integration_hub_tiktok_shop_cipher', $connectionSettings['shop_cipher'] ?? ''); ?>
                            <?php echo render_input('external_account_name', 'kt_integration_hub_external_account', $edit_connection['external_account_name'] ?? ($connectionSettings['shop_name'] ?? '')); ?>
                            <?php echo render_input('region', 'kt_integration_hub_tiktok_region', $connectionSettings['region'] ?? 'VN'); ?>
                            <?php echo render_input('access_token', 'kt_integration_hub_tiktok_access_token', '', 'password', ['autocomplete' => 'new-password']); ?>
                            <?php if ($storedAccessToken !== '') { ?><p class="text-muted"><?php echo _l('kt_integration_hub_token_stored'); ?>: <code><?php echo html_escape(kt_integration_hub_mask_secret($storedAccessToken)); ?></code></p><?php } ?>
                            <?php echo render_input('refresh_token', 'kt_integration_hub_tiktok_refresh_token', '', 'password', ['autocomplete' => 'new-password']); ?>
                            <?php if ($storedRefreshToken !== '') { ?><p class="text-muted"><?php echo _l('kt_integration_hub_refresh_token_stored'); ?>: <code><?php echo html_escape(kt_integration_hub_mask_secret($storedRefreshToken)); ?></code></p><?php } ?>
                            <?php echo render_input('token_expires_at', 'kt_integration_hub_token_expires_at', $tokenExpiresAt, 'datetime-local'); ?>

                            <input type="hidden" name="sync_orders_enabled" value="0">
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" id="sync_orders_enabled" name="sync_orders_enabled" value="1" <?php echo !isset($connectionSettings['sync_orders_enabled']) || !empty($connectionSettings['sync_orders_enabled']) ? 'checked' : ''; ?>>
                                <label for="sync_orders_enabled"><?php echo _l('kt_integration_hub_tiktok_sync_orders'); ?></label>
                            </div>
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" id="sync_products_disabled" disabled>
                                <label for="sync_products_disabled"><?php echo _l('kt_integration_hub_tiktok_sync_products_planned'); ?></label>
                            </div>
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" id="auto_invoice_disabled" disabled>
                                <label for="auto_invoice_disabled"><?php echo _l('kt_integration_hub_tiktok_auto_invoice_disabled'); ?></label>
                            </div>
                            <input type="hidden" name="dry_run_enabled" value="0">
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" id="dry_run_enabled" name="dry_run_enabled" value="1" <?php echo !isset($connectionSettings['dry_run_enabled']) || !empty($connectionSettings['dry_run_enabled']) ? 'checked' : ''; ?>>
                                <label for="dry_run_enabled"><?php echo _l('kt_integration_hub_tiktok_dry_run_enabled'); ?></label>
                            </div>

                            <?php if ($edit_connection && $selectedProviderCode === 'tiktok_shop') { ?>
                                <div class="form-group">
                                    <label><?php echo _l('kt_integration_hub_webhook_url'); ?></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" readonly id="kt-tiktok-webhook-url" value="<?php echo html_escape(kt_integration_hub_webhook_url($edit_connection)); ?>">
                                        <span class="input-group-btn">
                                            <button type="button" class="btn btn-default kt-copy-btn" data-copy-target="#kt-tiktok-webhook-url"><?php echo _l('kt_integration_hub_copy_url'); ?></button>
                                        </span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label><?php echo _l('kt_integration_hub_oauth_callback_url'); ?></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" readonly id="kt-tiktok-oauth-url" value="<?php echo html_escape(kt_integration_hub_oauth_callback_url($edit_connection, 'tiktok_shop')); ?>">
                                        <span class="input-group-btn">
                                            <button type="button" class="btn btn-default kt-copy-btn" data-copy-target="#kt-tiktok-oauth-url"><?php echo _l('kt_integration_hub_copy_url'); ?></button>
                                        </span>
                                    </div>
                                </div>
                                <pre id="kt-tiktok-test-curl"><?php echo html_escape(kt_integration_hub_tiktok_test_curl($edit_connection)); ?></pre>
                                <button type="button" class="btn btn-default kt-copy-btn" data-copy-target="#kt-tiktok-test-curl"><?php echo _l('kt_integration_hub_copy_test_curl'); ?></button>
                            <?php } else { ?>
                                <div class="alert alert-warning"><?php echo _l('kt_integration_hub_tiktok_save_first'); ?></div>
                            <?php } ?>

                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" id="tiktok_is_active" name="is_active" value="1" <?php echo (($edit_connection['status'] ?? 'connected') === 'connected') ? 'checked' : ''; ?>>
                                <label for="tiktok_is_active"><?php echo _l('kt_integration_hub_active'); ?></label>
                            </div>
                            <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
                            <button type="button" class="btn btn-default disabled" data-keep-disabled="1" disabled><?php echo _l('kt_integration_hub_tiktok_connect_button'); ?></button>
                            <p class="text-muted mtop10"><?php echo _l('kt_integration_hub_tiktok_oauth_pending'); ?></p>
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
        var authHidden = document.getElementById('kt-auth-type');
        var customForm = document.getElementById('kt-custom-webhook-form');
        var zaloForm = document.getElementById('kt-zalo-oa-form');
        var tiktokForm = document.getElementById('kt-tiktok-shop-form');
        var blocked = document.getElementById('kt-provider-not-ready');
        var selected = selector ? selector.options[selector.selectedIndex] : null;
        var readiness = selected ? selected.getAttribute('data-readiness') : '<?php echo html_escape($selectedProvider['readiness_status'] ?? 'ready'); ?>';
        var authType = selected ? selected.getAttribute('data-auth-type') : '<?php echo html_escape($selectedProvider['auth_type'] ?? 'custom_hmac'); ?>';

        if (hidden) {
            hidden.value = code;
        }
        if (authHidden) {
            authHidden.value = authType;
        }

        document.querySelectorAll('[data-provider-panel]').forEach(function(panel) {
            panel.style.display = panel.getAttribute('data-provider-panel') === code ? 'block' : 'none';
        });

        var canCreateCustom = code === 'custom_webhook' && readiness === 'ready' && authType === 'custom_hmac';
        var canCreateZalo = code === 'zalo_oa' && (readiness === 'ready' || readiness === 'beta') && authType === 'oauth';
        var canCreateTikTok = code === 'tiktok_shop' && (readiness === 'ready' || readiness === 'beta') && authType === 'partner_api';
        if (customForm) {
            customForm.style.display = canCreateCustom ? 'block' : 'none';
            customForm.querySelectorAll('input, select, textarea, button').forEach(function(field) {
                field.disabled = !canCreateCustom;
            });
        }
        if (zaloForm) {
            zaloForm.style.display = canCreateZalo ? 'block' : 'none';
            zaloForm.querySelectorAll('input, select, textarea, button').forEach(function(field) {
                if (field.getAttribute('data-keep-disabled') === '1') {
                    field.disabled = true;
                    return;
                }
                field.disabled = !canCreateZalo;
            });
        }
        if (tiktokForm) {
            tiktokForm.style.display = canCreateTikTok ? 'block' : 'none';
            tiktokForm.querySelectorAll('input, select, textarea, button').forEach(function(field) {
                if (field.getAttribute('data-keep-disabled') === '1') {
                    field.disabled = true;
                    return;
                }
                if (field.disabled && field.id !== 'sync_products_disabled' && field.id !== 'auto_invoice_disabled') {
                    field.disabled = !canCreateTikTok;
                    return;
                }
                if (field.id !== 'sync_products_disabled' && field.id !== 'auto_invoice_disabled') {
                    field.disabled = !canCreateTikTok;
                }
            });
        }
        if (blocked) {
            blocked.style.display = (canCreateCustom || canCreateZalo || canCreateTikTok) ? 'none' : 'block';
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
