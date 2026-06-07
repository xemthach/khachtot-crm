<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="panel_s"><div class="panel-body">
    <h4><?php echo html_escape($title); ?></h4>
    <?php echo form_open(admin_url('kt_inventory/transactions'), ['method' => 'get']); ?>
    <div class="row">
        <div class="col-md-2"><select name="warehouse_id" class="form-control selectpicker"><option value=""><?php echo _l('kt_inventory_warehouse'); ?></option><?php foreach ($warehouses as $warehouse) { ?><option value="<?php echo (int) $warehouse['id']; ?>" <?php echo ((int) ($filters['warehouse_id'] ?? 0) === (int) $warehouse['id']) ? 'selected' : ''; ?>><?php echo html_escape($warehouse['warehouse_name']); ?></option><?php } ?></select></div>
        <div class="col-md-2"><select name="inventory_item_id" class="form-control selectpicker" data-live-search="true"><option value=""><?php echo _l('kt_inventory_select_item'); ?></option><?php foreach ($inventory_items as $item) { ?><option value="<?php echo (int) $item['id']; ?>" <?php echo ((int) ($filters['inventory_item_id'] ?? 0) === (int) $item['id']) ? 'selected' : ''; ?>><?php echo html_escape($item['sku'] . ' - ' . $item['name']); ?></option><?php } ?></select></div>
        <div class="col-md-2"><select name="transaction_type" class="form-control selectpicker"><option value=""><?php echo _l('kt_inventory_transaction_receipt'); ?>/...</option><?php foreach ($transaction_types as $key => $label) { ?><option value="<?php echo html_escape($key); ?>" <?php echo (($filters['transaction_type'] ?? '') === $key) ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option><?php } ?></select></div>
        <div class="col-md-2"><input type="date" name="date_from" value="<?php echo html_escape($filters['date_from'] ?? ''); ?>" class="form-control"></div>
        <div class="col-md-2"><input type="date" name="date_to" value="<?php echo html_escape($filters['date_to'] ?? ''); ?>" class="form-control"></div>
        <div class="col-md-2"><button type="submit" class="btn btn-primary"><?php echo _l('kt_inventory_filters'); ?></button> <a href="<?php echo admin_url('kt_inventory/export_transactions_csv?' . http_build_query($filters)); ?>" class="btn btn-default"><?php echo _l('kt_inventory_export_csv'); ?></a> <a href="<?php echo admin_url('kt_inventory/export_transactions_excel?' . http_build_query($filters)); ?>" class="btn btn-default"><?php echo _l('kt_inventory_export_excel'); ?></a></div>
    </div>
    <?php echo form_close(); ?>
    <hr class="hr-panel-heading" />
    <div class="table-responsive"><table class="table table-striped"><thead><tr><th><?php echo _l('kt_inventory_date'); ?></th><th><?php echo _l('kt_inventory_transaction_receipt'); ?></th><th><?php echo _l('kt_inventory_warehouse'); ?></th><th><?php echo _l('kt_inventory_sku'); ?></th><th><?php echo _l('kt_inventory_quantity_before'); ?></th><th><?php echo _l('kt_inventory_quantity_change'); ?></th><th><?php echo _l('kt_inventory_quantity_after'); ?></th><th><?php echo _l('kt_inventory_reference'); ?></th></tr></thead><tbody>
    <?php foreach ($transactions as $row) { ?><tr><td><?php echo html_escape(_dt($row['created_at'])); ?></td><td><?php echo html_escape($transaction_types[$row['transaction_type']] ?? $row['transaction_type']); ?></td><td><?php echo html_escape($row['warehouse_name']); ?></td><td><?php echo html_escape($row['sku'] . ' - ' . $row['item_name']); ?></td><td><?php echo html_escape($row['quantity_before']); ?></td><td><?php echo html_escape($row['quantity_change']); ?></td><td><?php echo html_escape($row['quantity_after']); ?></td><td><?php echo html_escape(kt_inventory_reference_type_label($row['reference_type']) . ' #' . $row['reference_id']); ?></td></tr><?php } ?>
    <?php if (empty($transactions)) { ?><tr><td colspan="8"><?php echo _l('kt_inventory_no_records'); ?></td></tr><?php } ?>
    </tbody></table></div>
</div></div></div></div>
<?php init_tail(); ?>
