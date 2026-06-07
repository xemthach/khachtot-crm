<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="panel_s"><div class="panel-body">
    <div class="tw-flex tw-items-center tw-justify-between"><h4><?php echo html_escape($title); ?></h4><a href="<?php echo admin_url('kt_inventory/transfer'); ?>" class="btn btn-primary"><?php echo _l('kt_inventory_create_transfer'); ?></a></div>
    <hr class="hr-panel-heading" />
    <?php echo form_open(admin_url('kt_inventory/bulk_transfers'), ['id' => 'kt-inventory-bulk-transfers-form']); ?>
    <div class="row mbottom15"><div class="col-md-8"><div class="input-group"><select name="bulk_action" id="kt-inventory-bulk-transfers-action" class="form-control"><option value="">Chọn thao tác hàng loạt</option><option value="post"><?php echo _l('kt_inventory_post'); ?></option><option value="cancel"><?php echo _l('kt_inventory_cancel'); ?></option></select><span class="input-group-btn"><button type="submit" class="btn btn-primary" id="kt-inventory-bulk-transfers-submit" disabled>Áp dụng</button></span></div><p class="text-muted mtop10 mbot0" id="kt-inventory-bulk-transfers-count">Chưa chọn phiếu chuyển kho nào.</p></div></div>
    <div class="table-responsive"><table class="table table-striped"><thead><tr><th width="40"><div class="checkbox checkbox-primary mtop0 mbot0"><input type="checkbox" id="kt-inventory-bulk-transfers-all"><label for="kt-inventory-bulk-transfers-all"></label></div></th><th><?php echo _l('kt_inventory_code'); ?></th><th><?php echo _l('kt_inventory_from_warehouse'); ?></th><th><?php echo _l('kt_inventory_to_warehouse'); ?></th><th><?php echo _l('kt_inventory_date'); ?></th><th><?php echo _l('status'); ?></th><th><?php echo _l('kt_inventory_actions'); ?></th></tr></thead><tbody>
    <?php foreach ($transfers as $row) { ?><tr><td><div class="checkbox checkbox-primary mtop0 mbot0"><input type="checkbox" class="kt-inventory-bulk-transfers-item" name="ids[]" value="<?php echo (int) $row['id']; ?>" id="kt-inventory-transfer-<?php echo (int) $row['id']; ?>"><label for="kt-inventory-transfer-<?php echo (int) $row['id']; ?>"></label></div></td><td><?php echo html_escape($row['transfer_code']); ?></td><td><?php echo html_escape($row['from_warehouse_name']); ?></td><td><?php echo html_escape($row['to_warehouse_name']); ?></td><td><?php echo html_escape(_d($row['transfer_date'])); ?></td><td><span class="label label-<?php echo kt_inventory_status_badge_class($row['status']); ?>"><?php echo _l('kt_inventory_status_' . $row['status']); ?></span></td><td class="kt-inventory-table-actions"><a href="<?php echo admin_url('kt_inventory/transfer/' . $row['id']); ?>" class="btn btn-default btn-sm"><?php echo _l('kt_inventory_edit'); ?></a><?php if ($row['status'] === 'draft') { ?><a href="<?php echo admin_url('kt_inventory/post_transfer/' . $row['id']); ?>" class="btn btn-success btn-sm"><?php echo _l('kt_inventory_post'); ?></a><a href="<?php echo admin_url('kt_inventory/cancel_transfer/' . $row['id']); ?>" class="btn btn-warning btn-sm"><?php echo _l('kt_inventory_cancel'); ?></a><?php } ?></td></tr><?php } ?>
    <?php if (empty($transfers)) { ?><tr><td colspan="7"><?php echo _l('kt_inventory_no_records'); ?></td></tr><?php } ?>
    </tbody></table></div>
    <?php echo form_close(); ?>
</div></div></div></div>
<?php init_tail(); ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('kt-inventory-bulk-transfers-form');
    if (!form) return;
    var checkAll = document.getElementById('kt-inventory-bulk-transfers-all');
    var items = Array.prototype.slice.call(document.querySelectorAll('.kt-inventory-bulk-transfers-item'));
    var submit = document.getElementById('kt-inventory-bulk-transfers-submit');
    var count = document.getElementById('kt-inventory-bulk-transfers-count');
    var action = document.getElementById('kt-inventory-bulk-transfers-action');
    function updateState() {
        var selected = items.filter(function (item) { return item.checked; }).length;
        submit.disabled = selected === 0;
        count.textContent = selected > 0 ? ('Đã chọn ' + selected + ' phiếu chuyển kho.') : 'Chưa chọn phiếu chuyển kho nào.';
        if (checkAll) checkAll.checked = selected > 0 && selected === items.length;
    }
    if (checkAll) checkAll.addEventListener('change', function () { items.forEach(function (item) { item.checked = checkAll.checked; }); updateState(); });
    items.forEach(function (item) { item.addEventListener('change', updateState); });
    form.addEventListener('submit', function (event) {
        if (!action.value || items.filter(function (item) { return item.checked; }).length === 0) { event.preventDefault(); alert('Chọn ít nhất một phiếu chuyển kho và một thao tác.'); return; }
        if (!window.confirm('Xác nhận xử lý hàng loạt cho các phiếu chuyển kho đã chọn?')) event.preventDefault();
    });
});
</script>
