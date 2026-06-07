<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-4">
                <div class="panel_s"><div class="panel-body">
                    <h4><?php echo html_escape($edit_domain ? _l('kt_saas_edit_domain') : _l('kt_saas_add_domain')); ?></h4>
                    <?php echo form_open(admin_url('kt_saas/domains' . ($edit_domain ? '/' . $edit_domain['id'] : ''))); ?>
                    <div class="form-group">
                        <label for="tenant_id"><?php echo _l('kt_saas_tenant'); ?></label>
                        <select name="tenant_id" id="tenant_id" class="form-control selectpicker" data-live-search="true">
                            <?php foreach ($tenants as $tenant) { ?>
                                <option value="<?php echo (int) $tenant['id']; ?>" <?php echo isset($edit_domain['tenant_id']) && (int) $edit_domain['tenant_id'] === (int) $tenant['id'] ? 'selected' : ''; ?>>
                                    <?php echo html_escape($tenant['tenant_code'] . ' - ' . $tenant['company_name']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <?php echo render_input('domain', 'kt_saas_domain', $edit_domain['domain'] ?? '', 'text', ['required' => true]); ?>
                    <div class="form-group">
                        <label for="domain_type"><?php echo _l('kt_saas_domain_type'); ?></label>
                        <select name="domain_type" id="domain_type" class="form-control selectpicker">
                            <option value="subdomain" <?php echo ($edit_domain['domain_type'] ?? 'subdomain') === 'subdomain' ? 'selected' : ''; ?>>Tên miền phụ</option>
                            <option value="custom" <?php echo ($edit_domain['domain_type'] ?? '') === 'custom' ? 'selected' : ''; ?>>Tên miền riêng</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="readiness_status"><?php echo _l('kt_saas_readiness_status'); ?></label>
                        <select name="readiness_status" id="readiness_status" class="form-control selectpicker">
                            <?php foreach ($readiness_statuses as $key => $label) { ?>
                                <option value="<?php echo html_escape($key); ?>" <?php echo ($edit_domain['readiness_status'] ?? 'pending') === $key ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ssl_status"><?php echo _l('kt_saas_ssl_status'); ?></label>
                        <select name="ssl_status" id="ssl_status" class="form-control selectpicker">
                            <?php foreach ($domain_statuses as $key => $label) { ?>
                                <option value="<?php echo html_escape($key); ?>" <?php echo ($edit_domain['ssl_status'] ?? 'pending') === $key ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="dns_status"><?php echo _l('kt_saas_dns_status'); ?></label>
                        <select name="dns_status" id="dns_status" class="form-control selectpicker">
                            <?php foreach ($domain_statuses as $key => $label) { ?>
                                <option value="<?php echo html_escape($key); ?>" <?php echo ($edit_domain['dns_status'] ?? 'pending') === $key ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <?php echo render_input('expected_target', 'kt_saas_expected_target', $edit_domain['expected_target'] ?? ''); ?>
                    <?php echo render_input('last_checked_at', 'kt_saas_last_checked_at', $edit_domain['last_checked_at'] ?? ''); ?>
                    <?php echo render_date_input('verified_at', 'kt_saas_verified_at', isset($edit_domain['verified_at']) && $edit_domain['verified_at'] ? _d(date('Y-m-d', strtotime($edit_domain['verified_at']))) : ''); ?>
                    <?php echo render_textarea('verification_message', 'kt_saas_verification_message', $edit_domain['verification_message'] ?? ''); ?>
                    <div class="checkbox checkbox-primary"><input type="checkbox" id="is_primary" name="is_primary" <?php echo !isset($edit_domain['is_primary']) || $edit_domain['is_primary'] ? 'checked' : ''; ?>><label for="is_primary"><?php echo _l('kt_saas_is_primary'); ?></label></div>
                    <button type="submit" class="btn btn-primary"><?php echo _l('kt_saas_save'); ?></button>
                    <?php if ($edit_domain) { ?><a href="<?php echo admin_url('kt_saas/domains'); ?>" class="btn btn-default"><?php echo _l('kt_saas_cancel'); ?></a><?php } ?>
                    <?php echo form_close(); ?>
                </div></div>
            </div>
            <div class="col-md-8">
                <div class="panel_s"><div class="panel-body">
                    <h4><?php echo html_escape($title); ?></h4>
                    <div class="mbot15">
                        <a href="<?php echo admin_url('kt_saas/verify_domains'); ?>" class="btn btn-info">
                            <?php echo _l('kt_saas_verify_all_domains'); ?>
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead><tr><th><?php echo _l('kt_saas_domain'); ?></th><th><?php echo _l('kt_saas_tenant'); ?></th><th><?php echo _l('kt_saas_domain_type'); ?></th><th><?php echo _l('kt_saas_readiness_status'); ?></th><th><?php echo _l('kt_saas_dns_status'); ?></th><th><?php echo _l('kt_saas_ssl_status'); ?></th><th><?php echo _l('kt_saas_last_checked_at'); ?></th><th><?php echo _l('kt_saas_actions'); ?></th></tr></thead>
                            <tbody>
                                <?php foreach ($domains as $domain) { ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo html_escape($domain['domain']); ?></strong>
                                            <?php if ($domain['is_primary']) { ?><br><small><?php echo _l('kt_saas_primary_domain'); ?></small><?php } ?>
                                            <?php if (!empty($domain['expected_target'])) { ?><br><small><?php echo _l('kt_saas_expected_target'); ?>: <?php echo html_escape($domain['expected_target']); ?></small><?php } ?>
                                            <?php if (!empty($domain['verification_message'])) { ?><br><small><?php echo html_escape($domain['verification_message']); ?></small><?php } ?>
                                        </td>
                                        <td><?php echo html_escape(($domain['tenant_code'] ?: '-') . ($domain['company_name'] ? ' - ' . $domain['company_name'] : '')); ?></td>
                                        <td><?php echo html_escape(ucfirst($domain['domain_type'])); ?></td>
                                        <td><span class="label label-<?php echo kt_saas_status_badge_class($domain['readiness_status'] ?? 'pending'); ?>"><?php echo html_escape(ucfirst(str_replace('_', ' ', $domain['readiness_status'] ?? 'pending'))); ?></span></td>
                                        <td><span class="label label-<?php echo kt_saas_status_badge_class($domain['dns_status']); ?>"><?php echo html_escape(ucfirst($domain['dns_status'])); ?></span></td>
                                        <td><span class="label label-<?php echo kt_saas_status_badge_class($domain['ssl_status']); ?>"><?php echo html_escape(ucfirst($domain['ssl_status'])); ?></span></td>
                                        <td><?php echo !empty($domain['last_checked_at']) ? _dt($domain['last_checked_at']) : '-'; ?><?php if (!empty($domain['verified_at'])) { ?><br><small><?php echo _l('kt_saas_verified_at'); ?>: <?php echo _dt($domain['verified_at']); ?></small><?php } ?></td>
                                        <td>
                                            <a href="<?php echo admin_url('kt_saas/domains/' . $domain['id']); ?>" class="btn btn-default btn-sm"><?php echo _l('kt_saas_edit'); ?></a>
                                            <a href="<?php echo admin_url('kt_saas/verify_domain/' . $domain['id']); ?>" class="btn btn-info btn-sm"><?php echo _l('kt_saas_verify_domain'); ?></a>
                                        </td>
                                    </tr>
                                <?php } ?>
                                <?php if (empty($domains)) { ?><tr><td colspan="8"><?php echo _l('kt_saas_no_records'); ?></td></tr><?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div></div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
