<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-4">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4><?php echo html_escape($edit_item ? _l('kt_inventory_edit') : _l('kt_inventory_add_new')); ?></h4>
                        <?php echo form_open(admin_url('kt_inventory/items' . ($edit_item ? '/' . $edit_item['id'] : ''))); ?>
                        <div class="form-group">
                            <label for="item_id"><?php echo _l('kt_inventory_item_link'); ?> <span class="text-danger">*</span></label>
                            <select name="item_id" id="item_id" class="form-control selectpicker" data-live-search="true" required="required" <?php echo $edit_item ? 'disabled' : ''; ?>>
                                <option value=""></option>
                                <?php foreach ($core_items as $core) { ?>
                                    <option value="<?php echo (int) $core['id']; ?>" data-unit="<?php echo html_escape($core['unit'] ?? ''); ?>" <?php echo isset($edit_item['item_id']) && (int) $edit_item['item_id'] === (int) $core['id'] ? 'selected' : ''; ?>>
                                        <?php echo html_escape($core['description']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <?php if ($edit_item) { ?>
                                <input type="hidden" name="item_id" value="<?php echo (int) $edit_item['item_id']; ?>">
                            <?php } ?>
                        </div>
                        <?php echo render_input('sku', 'kt_inventory_sku', $edit_item['sku'] ?? '', 'text', ['required' => true]); ?>
                        <?php echo render_input('name', 'kt_inventory_name', $edit_item['name'] ?? '', 'text', ['readonly' => true]); ?>
                        <?php echo render_input('unit', 'kt_inventory_unit', $edit_item['unit'] ?? '', 'text', ['readonly' => true]); ?>
                        <?php echo render_input('min_stock', 'kt_inventory_min_stock', $edit_item['min_stock'] ?? '0', 'number', ['step' => '0.01']); ?>
                        <?php echo render_input('max_stock', 'kt_inventory_max_stock', $edit_item['max_stock'] ?? '0', 'number', ['step' => '0.01']); ?>
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="track_lot" id="track_lot" <?php echo !empty($edit_item['track_lot']) ? 'checked' : ''; ?>>
                            <label for="track_lot"><?php echo _l('kt_inventory_track_lot'); ?></label>
                        </div>
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="track_serial" id="track_serial" <?php echo !empty($edit_item['track_serial']) ? 'checked' : ''; ?>>
                            <label for="track_serial"><?php echo _l('kt_inventory_track_serial'); ?></label>
                        </div>
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="is_active" id="is_active" <?php echo !isset($edit_item['is_active']) || $edit_item['is_active'] ? 'checked' : ''; ?>>
                            <label for="is_active"><?php echo _l('kt_inventory_active'); ?></label>
                        </div>
                        <button type="submit" class="btn btn-primary"><?php echo _l('kt_inventory_save'); ?></button>
                        <?php if ($edit_item) { ?>
                            <a href="<?php echo admin_url('kt_inventory/items'); ?>" class="btn btn-default"><?php echo _l('kt_inventory_cancel'); ?></a>
                        <?php } ?>
                        <?php echo form_close(); ?>

                        <?php if ($edit_item) { ?>
                            <hr class="hr-panel-heading" />
                            <div id="barcodes">
                                <div class="tw-flex tw-items-center tw-justify-between">
                                    <h4><?php echo _l('kt_inventory_barcodes'); ?></h4>
                                    <a href="<?php echo admin_url('kt_inventory/generate_item_barcode/' . $edit_item['id']); ?>" class="btn btn-default btn-sm"><?php echo _l('kt_inventory_generate_internal_barcode'); ?></a>
                                </div>
                                <div class="table-responsive mtop15">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th><?php echo _l('kt_inventory_barcode'); ?></th>
                                                <th><?php echo _l('kt_inventory_barcode_type'); ?></th>
                                                <th><?php echo _l('kt_inventory_unit_type'); ?></th>
                                                <th><?php echo _l('kt_inventory_package_quantity'); ?></th>
                                                <th><?php echo _l('kt_inventory_source'); ?></th>
                                                <th><?php echo _l('kt_inventory_primary'); ?></th>
                                                <th><?php echo _l('kt_inventory_active'); ?></th>
                                                <th><?php echo _l('kt_inventory_actions'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($item_barcodes as $barcodeRow) { ?>
                                                <tr>
                                                    <td><?php echo html_escape($barcodeRow['barcode']); ?></td>
                                                    <td><?php echo html_escape($barcode_types[$barcodeRow['barcode_type']] ?? $barcodeRow['barcode_type']); ?></td>
                                                    <td><?php echo html_escape($barcodeRow['unit_type']); ?></td>
                                                    <td><?php echo html_escape($barcodeRow['package_quantity']); ?></td>
                                                    <td><?php echo html_escape($barcode_sources[$barcodeRow['source']] ?? $barcodeRow['source']); ?></td>
                                                    <td><?php echo (int) $barcodeRow['is_primary'] ? '<span class="label label-success">' . _l('yes') . '</span>' : ''; ?></td>
                                                    <td><?php echo (int) $barcodeRow['is_active'] ? '<span class="label label-success">' . _l('yes') . '</span>' : '<span class="label label-default">' . _l('no') . '</span>'; ?></td>
                                                    <td class="kt-inventory-table-actions">
                                                        <?php if (!(int) $barcodeRow['is_primary'] && (int) $barcodeRow['is_active']) { ?>
                                                            <a href="<?php echo admin_url('kt_inventory/set_primary_barcode/' . $edit_item['id'] . '/' . $barcodeRow['id']); ?>" class="btn btn-default btn-sm"><?php echo _l('kt_inventory_set_primary'); ?></a>
                                                        <?php } ?>
                                                        <a href="<?php echo admin_url('kt_inventory/delete_item_barcode/' . $barcodeRow['id']); ?>" class="btn btn-warning btn-sm"><?php echo _l('kt_inventory_inactive'); ?></a>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                            <?php if (empty($item_barcodes)) { ?>
                                                <tr><td colspan="8"><?php echo _l('kt_inventory_no_records'); ?></td></tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>

                                <h5 class="mtop20"><?php echo _l('kt_inventory_add_barcode'); ?></h5>
                                <?php echo form_open(admin_url('kt_inventory/add_item_barcode/' . $edit_item['id'])); ?>
                                <?php echo render_input('barcode', 'kt_inventory_barcode', '', 'text', ['required' => true, 'maxlength' => 191]); ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><?php echo _l('kt_inventory_barcode_type'); ?></label>
                                            <select name="barcode_type" class="form-control selectpicker">
                                                <?php foreach ($barcode_types as $key => $label) { ?>
                                                    <option value="<?php echo html_escape($key); ?>"><?php echo html_escape($label); ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <?php echo render_input('unit_type', 'kt_inventory_unit_type', $edit_item['unit'] ?? ''); ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <?php echo render_input('package_quantity', 'kt_inventory_package_quantity', '1', 'number', ['step' => '0.0001', 'min' => '0.0001']); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><?php echo _l('kt_inventory_source'); ?></label>
                                            <select name="source" class="form-control selectpicker">
                                                <?php foreach ($barcode_sources as $key => $label) { ?>
                                                    <option value="<?php echo html_escape($key); ?>"><?php echo html_escape($label); ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="checkbox checkbox-primary">
                                    <input type="checkbox" name="is_primary" id="barcode_is_primary" value="1">
                                    <label for="barcode_is_primary"><?php echo _l('kt_inventory_primary'); ?></label>
                                </div>
                                <div class="checkbox checkbox-primary">
                                    <input type="checkbox" name="is_active" id="barcode_is_active" value="1" checked>
                                    <label for="barcode_is_active"><?php echo _l('kt_inventory_active'); ?></label>
                                </div>
                                <button type="submit" class="btn btn-primary"><?php echo _l('kt_inventory_save'); ?></button>
                                <?php echo form_close(); ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4><?php echo html_escape($title); ?></h4>
                        <?php echo form_open(admin_url('kt_inventory/bulk_items'), ['id' => 'kt-inventory-bulk-items-form']); ?>
                        <div class="row mbottom15">
                            <div class="col-md-8">
                                <div class="input-group">
                                    <select name="bulk_action" id="kt-inventory-bulk-items-action" class="form-control">
                                        <option value="">Chọn thao tác hàng loạt</option>
                                        <option value="activate">Kích hoạt</option>
                                        <option value="deactivate">Ngừng hoạt động</option>
                                        <option value="delete_clear">Xóa + clear kho</option>
                                        <option value="export_csv">Xuất CSV</option>
                                    </select>
                                    <span class="input-group-btn">
                                        <button type="submit" class="btn btn-primary" id="kt-inventory-bulk-items-submit" disabled>Áp dụng</button>
                                    </span>
                                </div>
                                <p class="text-muted mtop10 mbot0" id="kt-inventory-bulk-items-count">Chưa chọn hàng hóa nào.</p>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th width="40">
                                            <div class="checkbox checkbox-primary mtop0 mbot0">
                                                <input type="checkbox" id="kt-inventory-bulk-items-all">
                                                <label for="kt-inventory-bulk-items-all"></label>
                                            </div>
                                        </th>
                                        <th><?php echo _l('kt_inventory_sku'); ?></th>
                                        <th><?php echo _l('kt_inventory_name'); ?></th>
                                        <th><?php echo _l('kt_inventory_unit'); ?></th>
                                        <th><?php echo _l('kt_inventory_barcode'); ?></th>
                                        <th><?php echo _l('kt_inventory_min_stock'); ?></th>
                                        <th><?php echo _l('kt_inventory_active'); ?></th>
                                        <th><?php echo _l('kt_inventory_actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item) { ?>
                                        <tr>
                                            <td>
                                                <div class="checkbox checkbox-primary mtop0 mbot0">
                                                    <input type="checkbox" class="kt-inventory-bulk-items-item" name="ids[]" value="<?php echo (int) $item['id']; ?>" id="kt-inventory-item-<?php echo (int) $item['id']; ?>">
                                                    <label for="kt-inventory-item-<?php echo (int) $item['id']; ?>"></label>
                                                </div>
                                            </td>
                                            <td><?php echo html_escape($item['sku']); ?></td>
                                            <td><?php echo html_escape($item['name']); ?></td>
                                            <td><?php echo html_escape($item['unit']); ?></td>
                                            <td><?php echo html_escape($item['primary_barcode'] ?? ''); ?></td>
                                            <td><?php echo html_escape($item['min_stock']); ?></td>
                                            <td>
                                                <span class="label label-<?php echo $item['is_active'] ? 'success' : 'default'; ?>">
                                                    <?php echo $item['is_active'] ? _l('kt_inventory_active') : _l('kt_inventory_inactive'); ?>
                                                </span>
                                            </td>
                                            <td class="kt-inventory-table-actions">
                                                <a href="<?php echo admin_url('kt_inventory/items/' . $item['id']); ?>" class="btn btn-default btn-sm"><?php echo _l('kt_inventory_edit'); ?></a>
                                                <a href="<?php echo admin_url('kt_inventory/item_barcodes/' . $item['id']); ?>" class="btn btn-default btn-sm"><?php echo _l('kt_inventory_barcodes'); ?></a>
                                                <a href="<?php echo admin_url('kt_inventory/deactivate_item/' . $item['id']); ?>" class="btn btn-warning btn-sm"><?php echo _l('kt_inventory_inactive'); ?></a>
                                                <a href="<?php echo admin_url('kt_inventory/delete_item/' . $item['id']); ?>"
                                                   class="btn btn-danger btn-sm"
                                                   style="margin-left:4px;"
                                                   onclick="return confirm('<?php echo html_escape(_l('kt_inventory_delete_item_confirm')); ?>');">
                                                    <?php echo _l('kt_inventory_delete_item_and_clear'); ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    <?php if (empty($items)) { ?>
                                        <tr><td colspan="8"><?php echo _l('kt_inventory_no_records'); ?></td></tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var itemSelect = document.getElementById('item_id');
    var nameInput = document.getElementById('name');
    var unitInput = document.getElementById('unit');

    function updateFields() {
        var selectedOption = itemSelect.options[itemSelect.selectedIndex];
        if (selectedOption && selectedOption.value !== '') {
            nameInput.value = selectedOption.text.trim();
            unitInput.value = selectedOption.getAttribute('data-unit') || '';
        } else {
            nameInput.value = '';
            unitInput.value = '';
        }
    }

    if (itemSelect) {
        if (!itemSelect.disabled) {
            updateFields();
        }

        $(itemSelect).on('change', function() {
            updateFields();
        });
    }

    var form = document.getElementById('kt-inventory-bulk-items-form');
    if (!form) {
        return;
    }
    var checkAll = document.getElementById('kt-inventory-bulk-items-all');
    var items = Array.prototype.slice.call(document.querySelectorAll('.kt-inventory-bulk-items-item'));
    var submit = document.getElementById('kt-inventory-bulk-items-submit');
    var count = document.getElementById('kt-inventory-bulk-items-count');
    var action = document.getElementById('kt-inventory-bulk-items-action');

    function updateBulkState() {
        var selected = items.filter(function (item) { return item.checked; }).length;
        var hasAction = !!(action && action.value);
        submit.disabled = selected === 0 || !hasAction;
        count.textContent = selected > 0 ? ('Đã chọn ' + selected + ' hàng hóa.') : 'Chưa chọn hàng hóa nào.';
        if (checkAll) {
            checkAll.checked = selected > 0 && selected === items.length;
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            items.forEach(function (item) { item.checked = checkAll.checked; });
            updateBulkState();
        });
    }
    items.forEach(function (item) {
        item.addEventListener('change', updateBulkState);
    });
    if (action) {
        action.addEventListener('change', updateBulkState);
    }
    form.addEventListener('submit', function (event) {
        if (!action.value || items.filter(function (item) { return item.checked; }).length === 0) {
            event.preventDefault();
            alert('Chọn ít nhất một hàng hóa và một thao tác.');
            return;
        }
        if (!window.confirm('Xác nhận thực hiện thao tác hàng loạt cho các hàng hóa đã chọn?')) {
            event.preventDefault();
        }
    });
    updateBulkState();
});
</script>

