<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="kt-inventory-card">
            <h4 class="tw-mb-4"><?php echo html_escape($title); ?></h4>
            <div class="kt-inventory-summary">
                <div class="metric"><span class="label"><?php echo _l('kt_inventory_warehouses'); ?></span><span class="value"><?php echo (int) $summary['warehouse_count']; ?></span></div>
                <div class="metric"><span class="label"><?php echo _l('kt_inventory_items'); ?></span><span class="value"><?php echo (int) $summary['item_count']; ?></span></div>
                <div class="metric"><span class="label"><?php echo _l('kt_inventory_goods_receipts'); ?></span><span class="value"><?php echo (int) $summary['draft_receipts']; ?></span></div>
                <div class="metric"><span class="label"><?php echo _l('kt_inventory_goods_issues'); ?></span><span class="value"><?php echo (int) $summary['draft_issues']; ?></span></div>
                <div class="metric"><span class="label"><?php echo _l('kt_inventory_stock_adjustments'); ?></span><span class="value"><?php echo (int) $summary['draft_adjustments']; ?></span></div>
                <div class="metric"><span class="label"><?php echo _l('kt_inventory_stock_transfers'); ?></span><span class="value"><?php echo (int) $summary['draft_transfers']; ?></span></div>
                <div class="metric"><span class="label"><?php echo _l('kt_inventory_reservations'); ?></span><span class="value"><?php echo (int) $summary['active_reservations']; ?></span></div>
                <div class="metric"><span class="label"><?php echo _l('kt_inventory_low_stock'); ?></span><span class="value"><?php echo (int) $summary['low_stock_count']; ?></span></div>
            </div>
        </div>

        <div class="panel_s">
            <div class="panel-body">
                <h4 class="no-margin"><?php echo _l('kt_inventory_transactions'); ?></h4>
                <hr class="hr-panel-heading" />
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?php echo _l('kt_inventory_date'); ?></th>
                                <th><?php echo _l('kt_inventory_warehouse'); ?></th>
                                <th><?php echo _l('kt_inventory_sku'); ?></th>
                                <th><?php echo _l('kt_inventory_name'); ?></th>
                                <th><?php echo _l('kt_inventory_quantity_change'); ?></th>
                                <th><?php echo _l('kt_inventory_reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summary['recent_transactions'] as $row) { ?>
                                <tr>
                                    <td><?php echo html_escape(_dt($row['created_at'])); ?></td>
                                    <td><?php echo html_escape($row['warehouse_name']); ?></td>
                                    <td><?php echo html_escape($row['sku']); ?></td>
                                    <td><?php echo html_escape($row['item_name']); ?></td>
                                    <td><?php echo html_escape($row['quantity_change']); ?></td>
                                    <td><?php echo html_escape(kt_inventory_reference_type_label($row['reference_type']) . ' #' . $row['reference_id']); ?></td>
                                </tr>
                            <?php } ?>
                            <?php if (empty($summary['recent_transactions'])) { ?>
                                <tr><td colspan="6"><?php echo _l('kt_inventory_no_records'); ?></td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
