<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="panel_s">
            <div class="panel-body">
                <h4><?php echo html_escape($title); ?></h4>
                <?php echo form_open(admin_url('kt_inventory/stock_balance'), ['method' => 'get']); ?>
                <div class="row">
                    <div class="col-md-3">
                        <select name="warehouse_id" class="form-control selectpicker">
                            <option value=""><?php echo _l('kt_inventory_select_warehouse'); ?></option>
                            <?php foreach ($warehouses as $warehouse) { ?>
                                <option value="<?php echo (int) $warehouse['id']; ?>" <?php echo ((int) ($filters['warehouse_id'] ?? 0) === (int) $warehouse['id']) ? 'selected' : ''; ?>><?php echo html_escape($warehouse['warehouse_name']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="inventory_item_id" class="form-control selectpicker" data-live-search="true">
                            <option value=""><?php echo _l('kt_inventory_select_item'); ?></option>
                            <?php foreach ($inventory_items as $item) { ?>
                                <option value="<?php echo (int) $item['id']; ?>" <?php echo ((int) ($filters['inventory_item_id'] ?? 0) === (int) $item['id']) ? 'selected' : ''; ?>><?php echo html_escape($item['sku'] . ' - ' . $item['name']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="keyword" value="<?php echo html_escape($filters['keyword'] ?? ''); ?>" class="form-control" placeholder="Mã hàng / Hàng hóa / Kho">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary"><?php echo _l('kt_inventory_filters'); ?></button>
                        <a href="<?php echo admin_url('kt_inventory/export_stock_balance_csv?' . http_build_query($filters)); ?>" class="btn btn-default"><?php echo _l('kt_inventory_export_csv'); ?></a>
                        <a href="<?php echo admin_url('kt_inventory/export_stock_balance_excel?' . http_build_query($filters)); ?>" class="btn btn-default"><?php echo _l('kt_inventory_export_excel'); ?></a>
                    </div>
                </div>
                <?php echo form_close(); ?>
                <hr class="hr-panel-heading" />
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?php echo _l('kt_inventory_warehouse'); ?></th>
                                <th><?php echo _l('kt_inventory_sku'); ?></th>
                                <th><?php echo _l('kt_inventory_name'); ?></th>
                                <th><?php echo _l('kt_inventory_barcode'); ?></th>
                                <th><?php echo _l('kt_inventory_current_stock'); ?></th>
                                <th><?php echo _l('kt_inventory_reserved_stock'); ?></th>
                                <th><?php echo _l('kt_inventory_available_stock'); ?></th>
                                <th><?php echo _l('kt_inventory_min_stock'); ?></th>
                                <th><?php echo _l('kt_inventory_low_stock'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($balances as $row) { ?>
                                <tr>
                                    <td><?php echo html_escape($row['warehouse_name']); ?></td>
                                    <td><?php echo html_escape($row['sku']); ?></td>
                                    <td><?php echo html_escape($row['name']); ?></td>
                                    <td><?php echo html_escape($row['primary_barcode'] ?? ''); ?></td>
                                    <td><?php echo html_escape($row['quantity']); ?></td>
                                    <td><?php echo html_escape($row['reserved_quantity']); ?></td>
                                    <td><?php echo html_escape($row['available_quantity']); ?></td>
                                    <td><?php echo html_escape($row['min_stock']); ?></td>
                                    <td>
                                        <?php if (kt_inventory_low_stock_label($row)) { ?>
                                            <span class="label label-danger"><?php echo _l('kt_inventory_low_stock'); ?></span>
                                        <?php } else { ?>
                                            <span class="label label-success">Ổn định</span>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                            <?php if (empty($balances)) { ?>
                                <tr><td colspan="9"><?php echo _l('kt_inventory_no_records'); ?></td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
