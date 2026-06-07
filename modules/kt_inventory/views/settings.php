<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="panel_s"><div class="panel-body">
    <h4><?php echo html_escape($title); ?></h4>
    <?php echo form_open(current_url()); ?>
    <div class="checkbox checkbox-primary"><input type="checkbox" id="kt_inventory_allow_negative_stock" name="kt_inventory_allow_negative_stock" <?php echo kt_inventory_get_option('kt_inventory_allow_negative_stock', '0') === '1' ? 'checked' : ''; ?>><label for="kt_inventory_allow_negative_stock"><?php echo _l('kt_inventory_allow_negative_stock'); ?></label></div>
    <div class="checkbox checkbox-primary"><input type="checkbox" id="kt_inventory_low_stock_notification_enabled" name="kt_inventory_low_stock_notification_enabled" <?php echo kt_inventory_get_option('kt_inventory_low_stock_notification_enabled', '0') === '1' ? 'checked' : ''; ?>><label for="kt_inventory_low_stock_notification_enabled"><?php echo _l('kt_inventory_low_stock_notification_enabled'); ?></label></div>
    <div class="form-group"><label><?php echo _l('kt_inventory_default_warehouse'); ?></label><select name="kt_inventory_default_warehouse_id" class="form-control selectpicker"><option value=""></option><?php foreach ($warehouses as $warehouse) { ?><option value="<?php echo (int) $warehouse['id']; ?>" <?php echo ((int) kt_inventory_get_option('kt_inventory_default_warehouse_id', 0) === (int) $warehouse['id']) ? 'selected' : ''; ?>><?php echo html_escape($warehouse['warehouse_name']); ?></option><?php } ?></select></div>
    <?php echo render_input('kt_inventory_code_prefix_receipt', 'kt_inventory_code_prefix_receipt', kt_inventory_get_option('kt_inventory_code_prefix_receipt', 'RCP')); ?>
    <?php echo render_input('kt_inventory_code_prefix_issue', 'kt_inventory_code_prefix_issue', kt_inventory_get_option('kt_inventory_code_prefix_issue', 'ISS')); ?>
    <?php echo render_input('kt_inventory_code_prefix_adjustment', 'kt_inventory_code_prefix_adjustment', kt_inventory_get_option('kt_inventory_code_prefix_adjustment', 'ADJ')); ?>
    <?php echo render_input('kt_inventory_code_prefix_transfer', 'kt_inventory_code_prefix_transfer', kt_inventory_get_option('kt_inventory_code_prefix_transfer', 'TRF')); ?>
    <?php echo render_input('kt_inventory_internal_barcode_prefix', 'kt_inventory_internal_barcode_prefix', kt_inventory_get_option('kt_inventory_internal_barcode_prefix', 'KTINV')); ?>
    <div class="form-group">
        <label><?php echo _l('kt_inventory_internal_barcode_type'); ?></label>
        <select name="kt_inventory_internal_barcode_type" class="form-control selectpicker">
            <?php foreach ($barcode_types as $key => $label) { ?>
                <option value="<?php echo html_escape($key); ?>" <?php echo kt_inventory_get_option('kt_inventory_internal_barcode_type', 'code128') === $key ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
            <?php } ?>
        </select>
    </div>
    <?php echo render_input('kt_inventory_next_barcode_number', 'kt_inventory_next_barcode_number', kt_inventory_get_option('kt_inventory_next_barcode_number', '1'), 'number', ['min' => '1']); ?>
    <button type="submit" class="btn btn-primary"><?php echo _l('kt_inventory_save'); ?></button>
    <?php echo form_close(); ?>
    <hr class="hr-panel-heading" />
    <h5><?php echo _l('kt_inventory_legacy_tools'); ?></h5>
    <p class="text-muted"><?php echo _l('kt_inventory_legacy_tools_desc'); ?></p>
    <a href="<?php echo admin_url('kt_inventory/migrate_legacy_data'); ?>" class="btn btn-default"><?php echo _l('kt_inventory_run_legacy_migration'); ?></a>
    <hr class="hr-panel-heading" />
    <h5><?php echo _l('kt_inventory_import_tools'); ?></h5>
    <p class="text-muted"><?php echo _l('kt_inventory_import_tools_desc'); ?></p>
    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-body">
                    <h5><?php echo _l('kt_inventory_items'); ?></h5>
                    <p class="text-muted"><?php echo _l('kt_inventory_import_items_desc'); ?></p>
                    <a href="<?php echo admin_url('kt_inventory/download_items_csv_template'); ?>" class="btn btn-default mright5"><?php echo _l('kt_inventory_download_template'); ?></a>
                    <a href="<?php echo admin_url('kt_inventory/export_items_csv'); ?>" class="btn btn-default"><?php echo _l('kt_inventory_export_csv'); ?></a>
                    <?php echo form_open_multipart(admin_url('kt_inventory/import_items_csv'), ['class' => 'mtop15']); ?>
                    <input type="file" name="items_csv" accept=".csv" class="form-control" required>
                    <button type="submit" class="btn btn-primary mtop15"><?php echo _l('kt_inventory_import_csv'); ?></button>
                    <?php echo form_close(); ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-body">
                    <h5><?php echo _l('kt_inventory_stock_balance'); ?></h5>
                    <p class="text-muted"><?php echo _l('kt_inventory_import_balances_desc'); ?></p>
                    <a href="<?php echo admin_url('kt_inventory/download_stock_balances_csv_template'); ?>" class="btn btn-default mright5"><?php echo _l('kt_inventory_download_template'); ?></a>
                    <?php echo form_open_multipart(admin_url('kt_inventory/import_stock_balances_csv'), ['class' => 'mtop15']); ?>
                    <input type="file" name="balances_csv" accept=".csv" class="form-control" required>
                    <button type="submit" class="btn btn-primary mtop15"><?php echo _l('kt_inventory_import_csv'); ?></button>
                    <?php echo form_close(); ?>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-body">
                    <h5><?php echo _l('kt_inventory_barcodes'); ?></h5>
                    <p class="text-muted"><?php echo _l('kt_inventory_export_item_barcodes_desc'); ?></p>
                    <a href="<?php echo admin_url('kt_inventory/export_item_barcodes_csv'); ?>" class="btn btn-default"><?php echo _l('kt_inventory_export_csv'); ?></a>
                </div>
            </div>
        </div>
    </div>
</div></div></div></div>
<?php init_tail(); ?>
