<?php defined('BASEPATH') or exit('No direct script access allowed'); $header = $document['header'] ?? null; $lines = $document['items'] ?? []; ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="panel_s"><div class="panel-body">
    <a href="<?php echo admin_url('kt_inventory/stock_transfers'); ?>" class="btn btn-default"><?php echo _l('kt_inventory_back_to_list'); ?></a>
    <hr class="hr-panel-heading" />
    <?php echo form_open(current_url(), ['data-kt-barcode-form' => '1', 'data-barcode-url' => $barcode_ajax_url, 'data-document-type' => $document_type]); ?>
    <div class="row">
        <div class="col-md-3"><?php echo render_input('transfer_code', 'kt_inventory_code', $header['transfer_code'] ?? kt_inventory_generate_code('transfer'), 'text', ['readonly' => true]); ?></div>
        <div class="col-md-3"><div class="form-group"><label><?php echo _l('kt_inventory_from_warehouse'); ?></label><select name="from_warehouse_id" id="from_warehouse_id" class="form-control selectpicker" required><?php foreach ($warehouses as $warehouse) { ?><option value="<?php echo (int) $warehouse['id']; ?>" <?php echo ((int) ($header['from_warehouse_id'] ?? 0) === (int) $warehouse['id']) ? 'selected' : ''; ?>><?php echo html_escape($warehouse['warehouse_name']); ?></option><?php } ?></select></div></div>
        <div class="col-md-3"><div class="form-group"><label><?php echo _l('kt_inventory_to_warehouse'); ?></label><select name="to_warehouse_id" id="to_warehouse_id" class="form-control selectpicker" required><?php foreach ($warehouses as $warehouse) { ?><option value="<?php echo (int) $warehouse['id']; ?>" <?php echo ((int) ($header['to_warehouse_id'] ?? 0) === (int) $warehouse['id']) ? 'selected' : ''; ?>><?php echo html_escape($warehouse['warehouse_name']); ?></option><?php } ?></select></div></div>
        <div class="col-md-3"><?php echo render_input('transfer_date', 'kt_inventory_date', $header['transfer_date'] ?? date('Y-m-d'), 'date', ['required' => true]); ?></div>
    </div>
    <?php echo render_textarea('note', 'kt_inventory_note', $header['note'] ?? ''); ?>
    <div class="row">
        <div class="col-md-6"><?php echo render_input('barcode_scan', 'kt_inventory_scan_barcode', '', 'text', ['data-kt-scan-input' => '1', 'autocomplete' => 'off']); ?></div>
    </div>
    <h4><?php echo _l('kt_inventory_document_items'); ?></h4>
    <div class="table-responsive"><table class="table table-bordered kt-inventory-line-table"><thead><tr><th><?php echo _l('kt_inventory_select_item'); ?></th><th><?php echo _l('kt_inventory_quantity'); ?></th><th><?php echo _l('kt_inventory_batch'); ?></th><th><?php echo _l('kt_inventory_serial_number'); ?></th><th><?php echo _l('kt_inventory_expiry_date'); ?></th><th><?php echo _l('kt_inventory_line_note'); ?></th><th></th></tr></thead><tbody id="transfer-lines">
    <?php foreach ($lines as $index => $line) { ?><tr data-kt-line-row><td><select name="lines[<?php echo $index; ?>][inventory_item_id]" class="form-control selectpicker item-select" data-live-search="true"><?php foreach ($inventory_items as $item) { ?><option value="<?php echo (int) $item['id']; ?>" data-track-lot="<?php echo (int) ($item['track_lot'] ?? 0); ?>" data-track-serial="<?php echo (int) ($item['track_serial'] ?? 0); ?>" <?php echo ((int) $line['inventory_item_id'] === (int) $item['id']) ? 'selected' : ''; ?>><?php echo html_escape($item['sku'] . ' - ' . $item['name']); ?></option><?php } ?></select><input type="hidden" name="lines[<?php echo $index; ?>][barcode_id]" value="<?php echo html_escape($line['barcode_id'] ?? ''); ?>"><input type="hidden" name="lines[<?php echo $index; ?>][scanned_barcode]" value="<?php echo html_escape($line['scanned_barcode'] ?? ''); ?>"></td><td><input type="number" step="0.01" name="lines[<?php echo $index; ?>][quantity]" class="form-control" value="<?php echo html_escape($line['quantity']); ?>"></td><td><select name="lines[<?php echo $index; ?>][batch_id]" class="form-control selectpicker batch-select" data-live-search="true"><option value=""><?php echo _l('kt_inventory_select_batch'); ?></option><?php
        if (!empty($line['inventory_item_id'])) {
            $warehouse_id = $header['from_warehouse_id'] ?? null;
            $CI =& get_instance();
            $batches = $CI->kt_inventory_model->get_batches_by_item($line['inventory_item_id'], $warehouse_id);
            foreach ($batches as $b) {
                if ($b['qc_status'] === 'released' || $b['id'] == ($line['batch_id'] ?? 0)) {
                    $selected = ($b['id'] == ($line['batch_id'] ?? 0)) ? 'selected' : '';
                    $label = html_escape($b['lot_number']);
                    if (!empty($b['expiry_date'])) {
                        $label .= ' (' . $b['expiry_date'] . ')';
                    }
                    $label .= ' [' . _l('kt_inventory_available_stock') . ': ' . (float)($b['available_qty'] ?? 0) . ']';
                    echo '<option value="' . (int) $b['id'] . '" ' . $selected . ' data-expiry="' . html_escape($b['expiry_date'] ?? '') . '">' . $label . '</option>';
                }
            }
        }
    ?></select></td><td><input type="text" name="lines[<?php echo $index; ?>][serial_number]" class="form-control" value="<?php echo html_escape($line['serial_number'] ?? ''); ?>"></td><td><input type="date" name="lines[<?php echo $index; ?>][expiry_date]" class="form-control expiry-date-input" value="<?php echo html_escape($line['expiry_date'] ?? ''); ?>" readonly></td><td><input type="text" name="lines[<?php echo $index; ?>][note]" class="form-control" value="<?php echo html_escape($line['note']); ?>"></td><td><button class="btn btn-danger" data-kt-remove-line="1" type="button">-</button></td></tr><?php } ?>
    </tbody></table></div>
    <script type="text/template" id="transfer-lines-template">
        <tr data-kt-line-row><td><select name="lines[__INDEX__][inventory_item_id]" class="form-control selectpicker item-select" data-live-search="true"><?php foreach ($inventory_items as $item) { ?><option value="<?php echo (int) $item['id']; ?>" data-track-lot="<?php echo (int) ($item['track_lot'] ?? 0); ?>" data-track-serial="<?php echo (int) ($item['track_serial'] ?? 0); ?>"><?php echo html_escape($item['sku'] . ' - ' . $item['name']); ?></option><?php } ?></select><input type="hidden" name="lines[__INDEX__][barcode_id]" value=""><input type="hidden" name="lines[__INDEX__][scanned_barcode]" value=""></td><td><input type="number" step="0.01" name="lines[__INDEX__][quantity]" class="form-control" value=""></td><td><select name="lines[__INDEX__][batch_id]" class="form-control selectpicker batch-select" data-live-search="true"><option value=""><?php echo _l('kt_inventory_select_batch'); ?></option></select></td><td><input type="text" name="lines[__INDEX__][serial_number]" class="form-control" value=""></td><td><input type="date" name="lines[__INDEX__][expiry_date]" class="form-control expiry-date-input" value="" readonly></td><td><input type="text" name="lines[__INDEX__][note]" class="form-control" value=""></td><td><button class="btn btn-danger" data-kt-remove-line="1" type="button">-</button></td></tr>
    </script>
    <button type="button" class="btn btn-default" data-kt-add-line="transfer-lines"><?php echo _l('kt_inventory_add_new'); ?></button>
    <hr class="hr-panel-heading" /><button type="submit" class="btn btn-primary"><?php echo _l('kt_inventory_save'); ?></button>
    <?php echo form_close(); ?>
</div></div></div></div>
<?php init_tail(); ?>
