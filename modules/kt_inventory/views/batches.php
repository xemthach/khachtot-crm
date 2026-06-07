<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-4">
                <div class="panel_s">
                    <div class="panel-body">
                        <?php if ($edit_batch) { ?>
                            <h4><?php echo html_escape(_l('kt_inventory_batch_edit')); ?></h4>
                            <p class="text-muted"><?php echo html_escape($edit_batch['item_name']); ?> (Mã hàng: <?php echo html_escape($edit_batch['sku']); ?>)</p>
                            <hr class="hr-10" />

                            <?php echo form_open(admin_url('kt_inventory/batches/' . $edit_batch['id'])); ?>

                            <div class="form-group">
                                <label for="lot_number" class="control-label"><?php echo _l('kt_inventory_lot_number'); ?></label>
                                <input type="text" id="lot_number" class="form-control" value="<?php echo html_escape($edit_batch['lot_number']); ?>" readonly disabled />
                            </div>

                            <div class="form-group">
                                <label for="qc_status" class="control-label"><?php echo _l('kt_inventory_qc_status'); ?></label>
                                <select name="qc_status" id="qc_status" class="form-control selectpicker" required>
                                    <?php foreach ($qc_statuses as $key => $label) { ?>
                                        <option value="<?php echo html_escape($key); ?>" <?php echo $edit_batch['qc_status'] === $key ? 'selected' : ''; ?>>
                                            <?php echo html_escape($label); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>

                            <?php echo render_date_input('expiry_date', 'kt_inventory_expiry_date', $edit_batch['expiry_date'] ?? ''); ?>
                            <?php echo render_date_input('manufacturing_date', 'kt_inventory_manufacturing_date', $edit_batch['manufacturing_date'] ?? ''); ?>

                            <button type="submit" class="btn btn-primary"><?php echo _l('kt_inventory_batch_save'); ?></button>
                            <a href="<?php echo admin_url('kt_inventory/batches'); ?>" class="btn btn-default"><?php echo _l('kt_inventory_cancel'); ?></a>

                            <?php echo form_close(); ?>
                        <?php } else { ?>
                            <div class="text-center padding-30">
                                <i class="fa-solid fa-file-invoice text-muted" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>
                                <p class="text-muted">Chọn một lô hàng từ bảng bên phải để cập nhật kiểm soát chất lượng hoặc hạn dùng.</p>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4><?php echo html_escape($title); ?></h4>
                        <hr class="hr-10" />
                        <?php echo form_open(admin_url('kt_inventory/bulk_batches'), ['id' => 'kt-inventory-bulk-batches-form']); ?>
                        <div class="row mbottom15">
                            <div class="col-md-8">
                                <div class="input-group">
                                    <select name="bulk_action" id="kt-inventory-bulk-batches-action" class="form-control">
                                        <option value="">Chọn thao tác hàng loạt</option>
                                        <option value="mark_released">Đánh dấu đã duyệt QC</option>
                                        <option value="mark_quarantine">Đánh dấu cách ly</option>
                                        <option value="mark_blocked">Đánh dấu khóa</option>
                                    </select>
                                    <span class="input-group-btn">
                                        <button type="submit" class="btn btn-primary" id="kt-inventory-bulk-batches-submit" disabled>Áp dụng</button>
                                    </span>
                                </div>
                                <p class="text-muted mtop10 mbot0" id="kt-inventory-bulk-batches-count">Chưa chọn lô hàng nào.</p>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover dataTable">
                                <thead>
                                    <tr>
                                        <th width="40">
                                            <div class="checkbox checkbox-primary mtop0 mbot0">
                                                <input type="checkbox" id="kt-inventory-bulk-batches-all">
                                                <label for="kt-inventory-bulk-batches-all"></label>
                                            </div>
                                        </th>
                                        <th><?php echo _l('kt_inventory_item'); ?></th>
                                        <th><?php echo _l('kt_inventory_lot_number'); ?></th>
                                        <th><?php echo _l('kt_inventory_expiry_date'); ?></th>
                                        <th><?php echo _l('kt_inventory_qc_status'); ?></th>
                                        <th>Tổng tồn</th>
                                        <th><?php echo _l('kt_inventory_actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($batches as $batch) { ?>
                                        <tr>
                                            <td>
                                                <div class="checkbox checkbox-primary mtop0 mbot0">
                                                    <input type="checkbox" class="kt-inventory-bulk-batches-item" name="ids[]" value="<?php echo (int) $batch['id']; ?>" id="kt-inventory-batch-<?php echo (int) $batch['id']; ?>">
                                                    <label for="kt-inventory-batch-<?php echo (int) $batch['id']; ?>"></label>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="font-medium"><?php echo html_escape($batch['item_name']); ?></span>
                                                <div class="text-muted text-xs">Mã hàng: <?php echo html_escape($batch['sku']); ?></div>
                                            </td>
                                            <td class="bold font-medium"><?php echo html_escape($batch['lot_number']); ?></td>
                                            <td>
                                                <?php
                                                if (!empty($batch['expiry_date'])) {
                                                    $isExpired = strtotime($batch['expiry_date']) < time();
                                                    $isNearExpiry = strtotime($batch['expiry_date']) < strtotime('+6 months') && !$isExpired;

                                                    $dateClass = '';
                                                    if ($isExpired) {
                                                        $dateClass = 'text-danger bold';
                                                    } elseif ($isNearExpiry) {
                                                        $dateClass = 'text-warning bold';
                                                    }

                                                    echo '<span class="' . $dateClass . '">' . _d($batch['expiry_date']) . '</span>';
                                                    if ($isExpired) {
                                                        echo ' <span class="label label-danger">Hết hạn</span>';
                                                    } elseif ($isNearExpiry) {
                                                        echo ' <span class="label label-warning">Sắp hết hạn</span>';
                                                    }
                                                } else {
                                                    echo '<span class="text-muted">Không có</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                $qcClass = 'default';
                                                $qcLabel = $batch['qc_status'];
                                                if ($batch['qc_status'] === 'released') {
                                                    $qcClass = 'success';
                                                    $qcLabel = 'Đã duyệt';
                                                } elseif ($batch['qc_status'] === 'quarantine') {
                                                    $qcClass = 'warning';
                                                    $qcLabel = 'Cách ly';
                                                } elseif ($batch['qc_status'] === 'blocked') {
                                                    $qcClass = 'danger';
                                                    $qcLabel = 'Đã khóa';
                                                }
                                                echo '<span class="label label-' . $qcClass . '">' . $qcLabel . '</span>';
                                                ?>
                                            </td>
                                            <td class="bold"><?php echo html_escape(app_format_number($batch['total_quantity'])) . ' ' . html_escape($batch['unit']); ?></td>
                                            <td>
                                                <a href="<?php echo admin_url('kt_inventory/batches/' . $batch['id']); ?>" class="btn btn-default btn-xs">
                                                    <i class="fa fa-edit"></i> <?php echo _l('kt_inventory_edit'); ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    <?php if (empty($batches)) { ?>
                                        <tr><td colspan="7" class="text-center"><?php echo _l('kt_inventory_no_records'); ?></td></tr>
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
    var form = document.getElementById('kt-inventory-bulk-batches-form');
    if (!form) {
        return;
    }
    var checkAll = document.getElementById('kt-inventory-bulk-batches-all');
    var items = Array.prototype.slice.call(document.querySelectorAll('.kt-inventory-bulk-batches-item'));
    var submit = document.getElementById('kt-inventory-bulk-batches-submit');
    var count = document.getElementById('kt-inventory-bulk-batches-count');
    var action = document.getElementById('kt-inventory-bulk-batches-action');

    function updateState() {
        var selected = items.filter(function (item) { return item.checked; }).length;
        submit.disabled = selected === 0;
        count.textContent = selected > 0 ? ('Đã chọn ' + selected + ' lô hàng.') : 'Chưa chọn lô hàng nào.';
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
        if (!action.value || items.filter(function (item) { return item.checked; }).length === 0) {
            event.preventDefault();
            alert('Chọn ít nhất một lô hàng và một thao tác.');
            return;
        }
        if (!window.confirm('Xác nhận cập nhật hàng loạt cho các lô hàng đã chọn?')) {
            event.preventDefault();
        }
    });
});
</script>
