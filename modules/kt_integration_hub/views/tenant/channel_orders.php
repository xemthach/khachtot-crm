<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0"><?php echo html_escape($title); ?></h4>
                        <p class="text-muted"><?php echo _l('kt_integration_hub_channel_orders_note'); ?></p>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('kt_integration_hub_time'); ?></th>
                                        <th><?php echo _l('kt_integration_hub_provider'); ?></th>
                                        <th><?php echo _l('kt_integration_hub_order_code'); ?></th>
                                        <th><?php echo _l('kt_integration_hub_buyer'); ?></th>
                                        <th><?php echo _l('kt_integration_hub_total'); ?></th>
                                        <th><?php echo _l('kt_integration_hub_status'); ?></th>
                                        <th><?php echo _l('kt_integration_hub_payment'); ?></th>
                                        <th><?php echo _l('kt_integration_hub_mapping'); ?></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $row) { ?>
                                        <tr>
                                            <td><?php echo html_escape($row['ordered_at'] ?: $row['created_at']); ?></td>
                                            <td><?php echo html_escape($row['provider_code']); ?></td>
                                            <td><code><?php echo html_escape($row['external_order_code'] ?: $row['external_order_id']); ?></code></td>
                                            <td>
                                                <?php echo html_escape($row['buyer_name'] ?: '-'); ?><br>
                                                <small class="text-muted"><?php echo html_escape($row['buyer_phone_masked'] ?: ''); ?></small>
                                            </td>
                                            <td><?php echo app_format_money((float) $row['grand_total'], $row['currency'] ?: 'VND'); ?></td>
                                            <td><span class="label label-info"><?php echo html_escape($row['order_status'] ?: '-'); ?></span></td>
                                            <td><?php echo html_escape($row['payment_status'] ?: '-'); ?></td>
                                            <td><?php echo html_escape($row['mapping_status'] ?: 'unmapped'); ?></td>
                                            <td class="text-right">
                                                <a class="btn btn-default btn-sm" href="<?php echo admin_url('kt_integration_hub/channel_orders/' . (int) $row['id']); ?>"><?php echo _l('view'); ?></a>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    <?php if (empty($orders)) { ?>
                                        <tr><td colspan="9" class="text-muted"><?php echo _l('kt_integration_hub_no_records'); ?></td></tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php if (!empty($order)) { ?>
                    <div class="panel_s">
                        <div class="panel-body">
                            <h4 class="tw-mt-0"><?php echo _l('kt_integration_hub_order_detail'); ?>: <?php echo html_escape($order['external_order_code'] ?: $order['external_order_id']); ?></h4>
                            <div class="alert alert-warning"><?php echo _l('kt_integration_hub_order_staging_warning'); ?></div>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th><?php echo _l('kt_integration_hub_sku'); ?></th>
                                            <th><?php echo _l('kt_integration_hub_item_name'); ?></th>
                                            <th><?php echo _l('kt_integration_hub_quantity'); ?></th>
                                            <th><?php echo _l('kt_integration_hub_unit_price'); ?></th>
                                            <th><?php echo _l('kt_integration_hub_total'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (($order['items'] ?? []) as $item) { ?>
                                            <tr>
                                                <td><?php echo html_escape($item['sku'] ?: '-'); ?></td>
                                                <td><?php echo html_escape($item['item_name'] ?: '-'); ?></td>
                                                <td><?php echo html_escape($item['quantity']); ?></td>
                                                <td><?php echo app_format_money((float) $item['unit_price'], $order['currency'] ?: 'VND'); ?></td>
                                                <td><?php echo app_format_money((float) $item['total_price'], $order['currency'] ?: 'VND'); ?></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                            <h5><?php echo _l('kt_integration_hub_raw_payload'); ?></h5>
                            <pre><?php echo html_escape($order['raw_json'] ?: '{}'); ?></pre>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
