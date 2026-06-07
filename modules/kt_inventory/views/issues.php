<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="panel_s"><div class="panel-body">
    <div class="tw-flex tw-items-center tw-justify-between"><h4><?php echo html_escape($title); ?></h4><a href="<?php echo admin_url('kt_inventory/issue'); ?>" class="btn btn-primary"><?php echo _l('kt_inventory_create_issue'); ?></a></div>
    <hr class="hr-panel-heading" />
    <?php echo form_open(admin_url('kt_inventory/bulk_issues'), ['id' => 'kt-inventory-bulk-issues-form']); ?>
    <div class="row mbottom15"><div class="col-md-8"><div class="input-group"><select name="bulk_action" id="kt-inventory-bulk-issues-action" class="form-control"><option value="">Chọn thao tác hàng loạt</option><option value="post"><?php echo _l('kt_inventory_post'); ?></option><option value="cancel"><?php echo _l('kt_inventory_cancel'); ?></option></select><span class="input-group-btn"><button type="submit" class="btn btn-primary" id="kt-inventory-bulk-issues-submit" disabled>Áp dụng</button></span></div><p class="text-muted mtop10 mbot0" id="kt-inventory-bulk-issues-count">Chưa chọn phiếu xuất kho nào.</p></div></div>
    <div class="table-responsive"><table class="table table-striped"><thead><tr><th width="40"><div class="checkbox checkbox-primary mtop0 mbot0"><input type="checkbox" id="kt-inventory-bulk-issues-all"><label for="kt-inventory-bulk-issues-all"></label></div></th><th><?php echo _l('kt_inventory_code'); ?></th><th><?php echo _l('kt_inventory_warehouse'); ?></th><th><?php echo _l('kt_inventory_customer'); ?></th><th><?php echo _l('kt_inventory_date'); ?></th><th><?php echo _l('status'); ?></th><th><?php echo _l('kt_inventory_actions'); ?></th></tr></thead><tbody>
    <?php foreach ($issues as $row) { ?><tr>
        <td><div class="checkbox checkbox-primary mtop0 mbot0"><input type="checkbox" class="kt-inventory-bulk-issues-item" name="ids[]" value="<?php echo (int) $row['id']; ?>" id="kt-inventory-issue-<?php echo (int) $row['id']; ?>"><label for="kt-inventory-issue-<?php echo (int) $row['id']; ?>"></label></div></td>
        <td><?php echo html_escape($row['issue_code']); ?></td><td><?php echo html_escape($row['warehouse_name']); ?></td><td><?php echo html_escape($row['customer_id']); ?></td><td><?php echo html_escape(_d($row['issue_date'])); ?></td><td><span class="label label-<?php echo kt_inventory_status_badge_class($row['status']); ?>"><?php echo _l('kt_inventory_status_' . $row['status']); ?></span></td><td class="kt-inventory-table-actions"><a href="<?php echo admin_url('kt_inventory/issue/' . $row['id']); ?>" class="btn btn-default btn-sm"><?php echo _l('kt_inventory_edit'); ?></a><?php if ($row['status'] === 'draft') { ?><a href="<?php echo admin_url('kt_inventory/post_issue/' . $row['id']); ?>" class="btn btn-success btn-sm"><?php echo _l('kt_inventory_post'); ?></a><a href="<?php echo admin_url('kt_inventory/cancel_issue/' . $row['id']); ?>" class="btn btn-warning btn-sm"><?php echo _l('kt_inventory_cancel'); ?></a><?php } ?></td>
    </tr><?php } ?>
    <?php if (empty($issues)) { ?><tr><td colspan="7"><?php echo _l('kt_inventory_no_records'); ?></td></tr><?php } ?>
    </tbody></table></div>
    <?php echo form_close(); ?>
</div></div></div></div>
<?php init_tail(); ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('kt-inventory-bulk-issues-form');
    if (!form) return;
    var checkAll = document.getElementById('kt-inventory-bulk-issues-all');
    var items = Array.prototype.slice.call(document.querySelectorAll('.kt-inventory-bulk-issues-item'));
    var submit = document.getElementById('kt-inventory-bulk-issues-submit');
    var count = document.getElementById('kt-inventory-bulk-issues-count');
    var action = document.getElementById('kt-inventory-bulk-issues-action');
    function updateState() {
        var selected = items.filter(function (item) { return item.checked; }).length;
        submit.disabled = selected === 0;
        count.textContent = selected > 0 ? ('Đã chọn ' + selected + ' phiếu xuất kho.') : 'Chưa chọn phiếu xuất kho nào.';
        if (checkAll) checkAll.checked = selected > 0 && selected === items.length;
    }
    if (checkAll) checkAll.addEventListener('change', function () { items.forEach(function (item) { item.checked = checkAll.checked; }); updateState(); });
    items.forEach(function (item) { item.addEventListener('change', updateState); });
    form.addEventListener('submit', function (event) {
        if (!action.value || items.filter(function (item) { return item.checked; }).length === 0) { event.preventDefault(); alert('Chọn ít nhất một phiếu xuất kho và một thao tác.'); return; }
        if (!window.confirm('Xác nhận xử lý hàng loạt cho các phiếu xuất kho đã chọn?')) event.preventDefault();
    });
});
</script>
