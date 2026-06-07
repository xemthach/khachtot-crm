<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-4">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4><?php echo _l('kt_inventory_add_new'); ?></h4>
                        <?php echo form_open(admin_url('kt_inventory/reservations')); ?>
                        <div class="form-group">
                            <label><?php echo _l('kt_inventory_warehouse'); ?></label>
                            <select name="warehouse_id" class="form-control selectpicker" required>
                                <?php foreach ($warehouses as $warehouse) { ?>
                                    <option value="<?php echo (int) $warehouse['id']; ?>"><?php echo html_escape($warehouse['warehouse_name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?php echo _l('kt_inventory_select_item'); ?></label>
                            <select name="inventory_item_id" class="form-control selectpicker" data-live-search="true" required>
                                <?php foreach ($inventory_items as $item) { ?>
                                    <option value="<?php echo (int) $item['id']; ?>"><?php echo html_escape($item['sku'] . ' - ' . $item['name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <?php echo render_input('quantity', 'kt_inventory_quantity', '', 'number', ['step' => '0.01', 'required' => true]); ?>
                        <div class="form-group">
                            <label><?php echo _l('kt_inventory_customer'); ?></label>
                            <select name="customer_id" class="form-control selectpicker" data-live-search="true">
                                <option value=""></option>
                                <?php foreach ($customers as $customer) { ?>
                                    <option value="<?php echo (int) $customer['userid']; ?>"><?php echo html_escape($customer['company']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?php echo _l('kt_inventory_invoice'); ?></label>
                            <select name="invoice_id" class="form-control selectpicker" data-live-search="true">
                                <option value=""></option>
                                <?php foreach ($invoices as $invoice) { ?>
                                    <option value="<?php echo (int) $invoice['id']; ?>">#<?php echo (int) ($invoice['number'] ?: $invoice['id']); ?><?php echo !empty($invoice['company']) ? ' - ' . html_escape($invoice['company']) : ''; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <?php echo render_input('reference_type', 'kt_inventory_reference_type', 'manual'); ?>
                        <?php echo render_input('reference_id', 'kt_inventory_reference_id', '', 'number'); ?>
                        <?php echo render_textarea('note', 'kt_inventory_note', ''); ?>
                        <button type="submit" class="btn btn-primary"><?php echo _l('kt_inventory_save'); ?></button>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4><?php echo html_escape($title); ?></h4>
                        <?php echo form_open(admin_url('kt_inventory/reservations'), ['method' => 'get']); ?>
                        <div class="row">
                            <div class="col-md-4">
                                <select name="warehouse_id" class="form-control selectpicker">
                                    <option value=""><?php echo _l('kt_inventory_select_warehouse'); ?></option>
                                    <?php foreach ($warehouses as $warehouse) { ?>
                                        <option value="<?php echo (int) $warehouse['id']; ?>" <?php echo ((int) ($filters['warehouse_id'] ?? 0) === (int) $warehouse['id']) ? 'selected' : ''; ?>><?php echo html_escape($warehouse['warehouse_name']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select name="status" class="form-control selectpicker">
                                    <option value=""><?php echo _l('kt_inventory_reservation_status'); ?></option>
                                    <option value="active" <?php echo (($filters['status'] ?? '') === 'active') ? 'selected' : ''; ?>>Đang giữ</option>
                                    <option value="fulfilled" <?php echo (($filters['status'] ?? '') === 'fulfilled') ? 'selected' : ''; ?>>Đã hoàn tất</option>
                                    <option value="released" <?php echo (($filters['status'] ?? '') === 'released') ? 'selected' : ''; ?>>Đã giải phóng</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary"><?php echo _l('kt_inventory_filters'); ?></button>
                            </div>
                        </div>
                        <?php echo form_close(); ?>
                        <hr class="hr-panel-heading" />
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('kt_inventory_date'); ?></th>
                                        <th><?php echo _l('kt_inventory_warehouse'); ?></th>
                                        <th><?php echo _l('kt_inventory_sku'); ?></th>
                                        <th><?php echo _l('kt_inventory_quantity'); ?></th>
                                        <th><?php echo _l('kt_inventory_customer'); ?></th>
                                        <th><?php echo _l('kt_inventory_invoice'); ?></th>
                                        <th><?php echo _l('kt_inventory_reservation_status'); ?></th>
                                        <th><?php echo _l('kt_inventory_actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reservations as $reservation) { ?>
                                        <tr>
                                            <td><?php echo html_escape(_dt($reservation['created_at'])); ?></td>
                                            <td><?php echo html_escape($reservation['warehouse_name']); ?></td>
                                            <td><?php echo html_escape(($reservation['sku'] ?? '') . ' - ' . ($reservation['item_name'] ?? '')); ?></td>
                                            <td><?php echo html_escape($reservation['quantity']); ?></td>
                                            <td><?php echo html_escape($reservation['customer_name'] ?? ''); ?></td>
                                            <td><?php echo !empty($reservation['invoice_id']) ? '#' . (int) $reservation['invoice_id'] : ''; ?></td>
                                            <td>
                                                <?php
                                                $statusLabel = $reservation['status'];
                                                if ($reservation['status'] === 'active') {
                                                    $statusLabel = 'Đang giữ';
                                                } elseif ($reservation['status'] === 'fulfilled') {
                                                    $statusLabel = 'Đã hoàn tất';
                                                } elseif ($reservation['status'] === 'released') {
                                                    $statusLabel = 'Đã giải phóng';
                                                }
                                                ?>
                                                <span class="label label-<?php echo $reservation['status'] === 'active' ? 'warning' : ($reservation['status'] === 'fulfilled' ? 'success' : 'default'); ?>"><?php echo html_escape($statusLabel); ?></span>
                                            </td>
                                            <td>
                                                <?php if ($reservation['status'] === 'active') { ?>
                                                    <a href="<?php echo admin_url('kt_inventory/release_reservation/' . $reservation['id']); ?>" class="btn btn-default btn-sm"><?php echo _l('kt_inventory_cancel'); ?></a>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    <?php if (empty($reservations)) { ?>
                                        <tr><td colspan="8"><?php echo _l('kt_inventory_no_records'); ?></td></tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
