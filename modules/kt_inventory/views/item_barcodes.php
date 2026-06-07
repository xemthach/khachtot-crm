<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="panel_s"><div class="panel-body">
    <div class="tw-flex tw-items-center tw-justify-between">
        <h4><?php echo html_escape($title); ?></h4>
        <a href="<?php echo admin_url('kt_inventory/items/' . $item['id'] . '#barcodes'); ?>" class="btn btn-default"><?php echo _l('kt_inventory_back_to_list'); ?></a>
    </div>
    <hr class="hr-panel-heading" />
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th><?php echo _l('kt_inventory_barcode'); ?></th>
                    <th><?php echo _l('kt_inventory_barcode_type'); ?></th>
                    <th><?php echo _l('kt_inventory_unit_type'); ?></th>
                    <th><?php echo _l('kt_inventory_package_quantity'); ?></th>
                    <th><?php echo _l('kt_inventory_source'); ?></th>
                    <th><?php echo _l('kt_inventory_primary'); ?></th>
                    <th><?php echo _l('kt_inventory_active'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($barcodes as $barcode) { ?>
                    <tr>
                        <td><?php echo html_escape($barcode['barcode']); ?></td>
                        <td><?php echo html_escape($barcode_types[$barcode['barcode_type']] ?? $barcode['barcode_type']); ?></td>
                        <td><?php echo html_escape($barcode['unit_type']); ?></td>
                        <td><?php echo html_escape($barcode['package_quantity']); ?></td>
                        <td><?php echo html_escape($barcode_sources[$barcode['source']] ?? $barcode['source']); ?></td>
                        <td><?php echo (int) $barcode['is_primary'] ? _l('yes') : ''; ?></td>
                        <td><?php echo (int) $barcode['is_active'] ? _l('yes') : _l('no'); ?></td>
                    </tr>
                <?php } ?>
                <?php if (empty($barcodes)) { ?>
                    <tr><td colspan="7"><?php echo _l('kt_inventory_no_records'); ?></td></tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div></div></div></div>
<?php init_tail(); ?>
