<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$settings = is_array($settings ?? null) ? $settings : [];
$templates = is_array($templates ?? null) ? $templates : [];
$canEditSettings = !empty($can_edit_settings);
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mb-2"><?php echo html_escape($title ?? _l('settings_group_einvoice')); ?></h4>
                <p class="text-muted">e-Invoice settings in this page are stored in the tenant database and only affect this workspace.</p>
            </div>
        </div>

        <div class="row">
            <div class="col-md-7">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo _l('settings_group_einvoice'); ?></h4>
                        <hr class="hr-panel-heading" />
                        <?php if (!$canEditSettings) { ?>
                            <div class="alert alert-warning">Your current plan does not allow editing tenant e-Invoice settings.</div>
                        <?php } ?>
                        <?php echo form_open(admin_url('einvoice/tenant_settings')); ?>
                            <div class="form-group">
                                <label for="einvoice_default_invoice_template" class="control-label"><?php echo _l('settings_einvoice_default_template'); ?></label>
                                <select name="einvoice_default_invoice_template" id="einvoice_default_invoice_template" class="form-control" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                                    <option value=""><?php echo _l('dropdown_non_selected_tex'); ?></option>
                                    <?php foreach ($templates as $template) { ?>
                                        <option value="<?php echo html_escape($template['id']); ?>" <?php echo (string) ($settings['einvoice_default_invoice_template'] ?? '') === (string) ($template['id'] ?? '') ? 'selected' : ''; ?>>
                                            <?php echo html_escape($template['name'] ?? ('Template #' . ($template['id'] ?? ''))); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>

                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" id="einvoice_send_as_invoice_email_attachment" name="einvoice_send_as_invoice_email_attachment" value="1" <?php echo ($settings['einvoice_send_as_invoice_email_attachment'] ?? '0') === '1' ? 'checked' : ''; ?> <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                                <label for="einvoice_send_as_invoice_email_attachment"><?php echo _l('settings_einvoice_send_as_invoice_email_attachment'); ?></label>
                            </div>

                            <hr />

                            <div class="form-group">
                                <label for="einvoice_default_credit_note_template" class="control-label"><?php echo _l('settings_einvoice_default_credit_note_template'); ?></label>
                                <select name="einvoice_default_credit_note_template" id="einvoice_default_credit_note_template" class="form-control" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                                    <option value=""><?php echo _l('dropdown_non_selected_tex'); ?></option>
                                    <?php foreach ($templates as $template) { ?>
                                        <option value="<?php echo html_escape($template['id']); ?>" <?php echo (string) ($settings['einvoice_default_credit_note_template'] ?? '') === (string) ($template['id'] ?? '') ? 'selected' : ''; ?>>
                                            <?php echo html_escape($template['name'] ?? ('Template #' . ($template['id'] ?? ''))); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>

                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" id="einvoice_send_as_credit_note_email_attachment" name="einvoice_send_as_credit_note_email_attachment" value="1" <?php echo ($settings['einvoice_send_as_credit_note_email_attachment'] ?? '0') === '1' ? 'checked' : ''; ?> <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                                <label for="einvoice_send_as_credit_note_email_attachment"><?php echo _l('settings_einvoice_send_as_credit_note_email_attachment'); ?></label>
                            </div>

                            <div class="alert alert-info mtop20">
                                Template files and option values remain tenant-local. Global module installation, cron jobs, provider infrastructure and SaaS billing e-invoice integration stay landlord-managed.
                            </div>

                            <div class="btn-bottom-toolbar text-right">
                                <button type="submit" class="btn btn-primary" <?php echo !$canEditSettings ? 'disabled' : ''; ?>><?php echo _l('submit'); ?></button>
                            </div>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>

            <div class="col-md-5">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="tw-flex tw-items-center tw-justify-between">
                            <h4 class="no-margin"><?php echo _l('settings_einvoice_templates'); ?></h4>
                            <a href="<?php echo admin_url('einvoice/template'); ?>" class="btn btn-primary btn-sm">
                                <i class="fa-regular fa-plus tw-mr-1"></i>
                                <?php echo _l('settings_einvoice_templates'); ?>
                            </a>
                        </div>
                        <hr class="hr-panel-heading" />

                        <?php if (empty($templates)) { ?>
                            <p class="text-muted no-margin"><?php echo _l('einvoice_no_template_set'); ?></p>
                        <?php } else { ?>
                            <div class="table-responsive">
                                <table class="table dt-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo _l('template_name'); ?></th>
                                            <th><?php echo _l('template_type'); ?></th>
                                            <th><?php echo _l('options'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($templates as $template) { ?>
                                            <tr>
                                                <td><?php echo html_escape($template['name'] ?? ''); ?></td>
                                                <td><?php echo html_escape(strtoupper((string) ($template['content_type'] ?? 'xml'))); ?></td>
                                                <td>
                                                    <div class="tw-flex tw-items-center tw-space-x-2">
                                                        <a href="<?php echo admin_url('einvoice/template/' . (int) ($template['id'] ?? 0)); ?>" class="tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700">
                                                            <i class="fa-regular fa-pen-to-square fa-lg"></i>
                                                        </a>
                                                        <a href="#" class="tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700 _delete" onclick="delete_template(this,'einvoice_invoice','<?php echo html_escape($template['id'] ?? '0'); ?>'); return false;">
                                                            <i class="fa-regular fa-trash-can"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
