<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-4">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4><?php echo html_escape($edit_warehouse ? _l('kt_inventory_edit') : _l('kt_inventory_add_new')); ?></h4>
                        <?php echo form_open(admin_url('kt_inventory/warehouses' . ($edit_warehouse ? '/' . $edit_warehouse['id'] : ''))); ?>
                        <?php echo render_input('warehouse_code', 'kt_inventory_code', $edit_warehouse['warehouse_code'] ?? '', 'text', ['required' => true]); ?>
                        <?php echo render_input('warehouse_name', 'kt_inventory_name', $edit_warehouse['warehouse_name'] ?? '', 'text', ['required' => true]); ?>
                        <?php echo render_textarea('address', 'kt_inventory_address', $edit_warehouse['address'] ?? ''); ?>
                        <div class="form-group">
                            <label for="manager_staff_id"><?php echo _l('kt_inventory_manager'); ?></label>
                            <select name="manager_staff_id" id="manager_staff_id" class="form-control selectpicker" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
                                <option value=""></option>
                                <?php foreach ($staff_members as $staff) { ?>
                                    <option value="<?php echo (int) $staff['staffid']; ?>" <?php echo isset($edit_warehouse['manager_staff_id']) && (int) $edit_warehouse['manager_staff_id'] === (int) $staff['staffid'] ? 'selected' : ''; ?>>
                                        <?php echo html_escape(trim($staff['firstname'] . ' ' . $staff['lastname'])); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="is_active" id="is_active" <?php echo !isset($edit_warehouse['is_active']) || $edit_warehouse['is_active'] ? 'checked' : ''; ?>>
                            <label for="is_active"><?php echo _l('kt_inventory_active'); ?></label>
                        </div>
                        <button type="submit" class="btn btn-primary"><?php echo _l('kt_inventory_save'); ?></button>
                        <?php if ($edit_warehouse) { ?>
                            <a href="<?php echo admin_url('kt_inventory/warehouses'); ?>" class="btn btn-default"><?php echo _l('kt_inventory_cancel'); ?></a>
                        <?php } ?>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4><?php echo html_escape($title); ?></h4>
                        <?php echo form_open(admin_url('kt_inventory/bulk_warehouses'), ['id' => 'kt-inventory-bulk-warehouses-form']); ?>
                        <div class="row mbottom15">
                            <div class="col-md-9">
                                <div class="row">
                                    <div class="col-md-5">
                                        <select name="bulk_action" id="kt-inventory-bulk-warehouses-action" class="form-control">
                                            <option value="">Chọn thao tác hàng loạt</option>
                                            <option value="activate">Kích hoạt</option>
                                            <option value="deactivate">Ngừng hoạt động</option>
                                            <option value="assign_manager">Gán người quản lý</option>
                                            <option value="delete">Xóa</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <select name="manager_staff_id" id="kt-inventory-bulk-warehouses-manager" class="form-control selectpicker" data-none-selected-text="Chọn người quản lý">
                                            <option value=""></option>
                                            <?php foreach ($staff_members as $staff) { ?>
                                                <option value="<?php echo (int) $staff['staffid']; ?>">
                                                    <?php echo html_escape(trim($staff['firstname'] . ' ' . $staff['lastname'])); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-primary btn-block" id="kt-inventory-bulk-warehouses-submit" disabled>Áp dụng</button>
                                    </div>
                                </div>
                                <p class="text-muted mtop10 mbot0" id="kt-inventory-bulk-warehouses-count">Chưa chọn kho nào.</p>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th width="40">
                                            <div class="checkbox checkbox-primary mtop0 mbot0">
                                                <input type="checkbox" id="kt-inventory-bulk-warehouses-all">
                                                <label for="kt-inventory-bulk-warehouses-all"></label>
                                            </div>
                                        </th>
                                        <th><?php echo _l('kt_inventory_code'); ?></th>
                                        <th><?php echo _l('kt_inventory_name'); ?></th>
                                        <th><?php echo _l('kt_inventory_manager'); ?></th>
                                        <th><?php echo _l('kt_inventory_active'); ?></th>
                                        <th><?php echo _l('kt_inventory_actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($warehouses as $warehouse) { ?>
                                        <tr>
                                            <td>
                                                <div class="checkbox checkbox-primary mtop0 mbot0">
                                                    <input type="checkbox" class="kt-inventory-bulk-warehouses-item" name="ids[]" value="<?php echo (int) $warehouse['id']; ?>" id="kt-inventory-warehouse-<?php echo (int) $warehouse['id']; ?>">
                                                    <label for="kt-inventory-warehouse-<?php echo (int) $warehouse['id']; ?>"></label>
                                                </div>
                                            </td>
                                            <td><?php echo html_escape($warehouse['warehouse_code']); ?></td>
                                            <td><?php echo html_escape($warehouse['warehouse_name']); ?></td>
                                            <td><?php echo html_escape($warehouse['manager_name']); ?></td>
                                            <td>
                                                <span class="label label-<?php echo $warehouse['is_active'] ? 'success' : 'default'; ?>">
                                                    <?php echo $warehouse['is_active'] ? _l('kt_inventory_active') : _l('kt_inventory_inactive'); ?>
                                                </span>
                                            </td>
                                            <td class="kt-inventory-table-actions">
                                                <a href="<?php echo admin_url('kt_inventory/warehouses/' . $warehouse['id']); ?>" class="btn btn-default btn-sm"><?php echo _l('kt_inventory_edit'); ?></a>
                                                <a href="<?php echo admin_url('kt_inventory/delete_warehouse/' . $warehouse['id']); ?>" class="btn btn-danger btn-sm _delete"><?php echo _l('kt_inventory_delete'); ?></a>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    <?php if (empty($warehouses)) { ?>
                                        <tr><td colspan="6"><?php echo _l('kt_inventory_no_records'); ?></td></tr>
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
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('kt-inventory-bulk-warehouses-form');
    if (!form) {
        return;
    }
    var checkAll = document.getElementById('kt-inventory-bulk-warehouses-all');
    var items = Array.prototype.slice.call(document.querySelectorAll('.kt-inventory-bulk-warehouses-item'));
    var submit = document.getElementById('kt-inventory-bulk-warehouses-submit');
    var count = document.getElementById('kt-inventory-bulk-warehouses-count');
    var action = document.getElementById('kt-inventory-bulk-warehouses-action');
    var manager = document.getElementById('kt-inventory-bulk-warehouses-manager');

    function updateState() {
        var selected = items.filter(function (item) { return item.checked; }).length;
        submit.disabled = selected === 0;
        count.textContent = selected > 0 ? ('Đã chọn ' + selected + ' kho.') : 'Chưa chọn kho nào.';
        if (checkAll) {
            checkAll.checked = selected > 0 && selected === items.length;
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            items.forEach(function (item) { item.checked = checkAll.checked; });
            updateState();
        });
    }

    items.forEach(function (item) {
        item.addEventListener('change', updateState);
    });

    form.addEventListener('submit', function (event) {
        var selected = items.filter(function (item) { return item.checked; }).length;
        if (!action.value || selected === 0) {
            event.preventDefault();
            alert('Chọn ít nhất một kho và một thao tác.');
            return;
        }
        if (action.value === 'assign_manager' && !manager.value) {
            event.preventDefault();
            alert('Chọn người quản lý trước khi áp dụng.');
            return;
        }
        if (!window.confirm('Xác nhận thực hiện thao tác hàng loạt cho các kho đã chọn?')) {
            event.preventDefault();
        }
    });
});
</script>
