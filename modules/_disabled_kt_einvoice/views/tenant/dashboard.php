<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">

                <!-- Header -->
                <div class="tw-flex tw-items-center tw-justify-between tw-mb-6">
                    <div>
                        <h4 class="tw-text-2xl tw-font-bold tw-text-gray-800">
                            <i class="fa-regular fa-file-invoice tw-text-blue-600 tw-mr-2"></i>
                            <?php echo _l('kt_einvoice_dashboard_title'); ?>
                        </h4>
                        <p class="text-muted">
                            <?php echo str_replace(['{month}', '{year}'], [date('n'), date('Y')], _l('kt_einvoice_quota_month')); ?>
                            &nbsp;|&nbsp;
                            <?php echo $environment === 'sandbox' ? '<span class="label label-warning">Sandbox</span>' : '<span class="label label-success">Production</span>'; ?>
                        </p>
                    </div>
                    <div>
                        <a href="<?php echo admin_url('kt_einvoice/invoices'); ?>" class="btn btn-default btn-sm">
                            <i class="fa fa-list"></i> Xem tất cả HĐ
                        </a>
                        <?php if (staff_can('create', 'kt_einvoice')): ?>
                        <a href="<?php echo admin_url('kt_einvoice/batch_issue'); ?>" class="btn btn-primary btn-sm tw-ml-2">
                            <i class="fa fa-paper-plane"></i> Phát hành theo lô
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row tw-mb-6">
                    <div class="col-md-3 col-sm-6">
                        <div class="panel_s">
                            <div class="panel-body tw-text-center tw-py-5">
                                <div class="tw-text-4xl tw-font-bold tw-text-blue-600"><?php echo $stats['total_issued']; ?></div>
                                <div class="text-muted tw-mt-1"><i class="fa fa-check-circle text-success"></i> <?php echo _l('kt_einvoice_total_issued'); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="panel_s">
                            <div class="panel-body tw-text-center tw-py-5">
                                <div class="tw-text-4xl tw-font-bold tw-text-yellow-500"><?php echo $stats['pending']; ?></div>
                                <div class="text-muted tw-mt-1"><i class="fa fa-clock-o text-warning"></i> <?php echo _l('kt_einvoice_pending'); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="panel_s">
                            <div class="panel-body tw-text-center tw-py-5">
                                <div class="tw-text-4xl tw-font-bold tw-text-red-500"><?php echo $stats['failed']; ?></div>
                                <div class="text-muted tw-mt-1"><i class="fa fa-exclamation-circle text-danger"></i> <?php echo _l('kt_einvoice_failed'); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="panel_s">
                            <div class="panel-body tw-text-center tw-py-5">
                                <?php if ($quota['unlimited']): ?>
                                    <div class="tw-text-2xl tw-font-bold tw-text-green-600">∞</div>
                                    <div class="text-muted tw-mt-1"><?php echo _l('kt_einvoice_quota_unlimited'); ?></div>
                                <?php else: ?>
                                    <div class="tw-text-4xl tw-font-bold <?php echo $quota['remaining'] <= 10 ? 'tw-text-red-500' : 'tw-text-green-600'; ?>">
                                        <?php echo $quota['remaining'] ?? 0; ?>
                                    </div>
                                    <div class="text-muted tw-mt-1"><?php echo _l('kt_einvoice_quota_remaining'); ?> / <?php echo $quota['plan_quota']; ?></div>
                                    <div class="progress progress-xs tw-mt-2">
                                        <?php $pct = $quota['plan_quota'] > 0 ? round($quota['used'] / $quota['plan_quota'] * 100) : 0; ?>
                                        <div class="progress-bar <?php echo $pct >= 90 ? 'progress-bar-danger' : ($pct >= 70 ? 'progress-bar-warning' : 'progress-bar-success'); ?>"
                                             style="width: <?php echo $pct; ?>%"></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Invoices -->
                <div class="panel_s">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <?php echo _l('kt_einvoice_recent_invoices'); ?>
                        </h4>
                        <a href="<?php echo admin_url('kt_einvoice/invoices'); ?>" class="pull-right text-muted">
                            <?php echo _l('kt_einvoice_view_all'); ?> →
                        </a>
                    </div>
                    <div class="panel-body no-padding">
                        <?php if (empty($recent_list)): ?>
                            <div class="tw-text-center tw-py-10 text-muted">
                                <i class="fa fa-file-o fa-3x tw-mb-3"></i>
                                <p><?php echo _l('kt_einvoice_no_invoices_yet'); ?></p>
                                <a href="<?php echo admin_url('kt_einvoice/settings'); ?>" class="btn btn-primary btn-sm">
                                    <i class="fa fa-cog"></i> Cài đặt eInvoice
                                </a>
                            </div>
                        <?php else: ?>
                            <table class="table table-hover no-margin">
                                <thead class="tw-bg-gray-50">
                                    <tr>
                                        <th><?php echo _l('kt_einvoice_col_perfex_invoice'); ?></th>
                                        <th><?php echo _l('kt_einvoice_col_invoice_number'); ?></th>
                                        <th><?php echo _l('kt_einvoice_col_buyer'); ?></th>
                                        <th><?php echo _l('kt_einvoice_col_amount'); ?></th>
                                        <th><?php echo _l('kt_einvoice_col_status'); ?></th>
                                        <th><?php echo _l('kt_einvoice_col_issued_at'); ?></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_list as $rec): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo admin_url('invoices/list_invoices/' . $rec['perfex_invoice_id']); ?>">
                                                <?php echo htmlspecialchars($rec['perfex_invoice_number']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($rec['invoice_number'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($rec['buyer_name'] ?? '—'); ?></td>
                                        <td><?php echo number_format((float)$rec['total_amount'], 0, ',', '.') . ' đ'; ?></td>
                                        <td><?php echo kt_einvoice_status_badge($rec['status']); ?></td>
                                        <td><?php echo $rec['issued_at'] ? date('d/m/Y H:i', strtotime($rec['issued_at'])) : '—'; ?></td>
                                        <td>
                                            <a href="<?php echo admin_url('kt_einvoice/invoice_detail/' . $rec['id']); ?>"
                                               class="btn btn-xs btn-default" title="Chi tiết">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="row">
                    <div class="col-md-4">
                        <a href="<?php echo admin_url('kt_einvoice/settings'); ?>" class="panel_s tw-block hover:tw-border-blue-300 tw-transition">
                            <div class="panel-body tw-text-center tw-py-4">
                                <i class="fa fa-cog fa-2x text-info tw-mb-2"></i>
                                <p class="tw-font-medium tw-mb-0"><?php echo _l('kt_einvoice_menu_settings'); ?></p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="<?php echo admin_url('kt_einvoice/invoices'); ?>" class="panel_s tw-block hover:tw-border-blue-300 tw-transition">
                            <div class="panel-body tw-text-center tw-py-4">
                                <i class="fa fa-list fa-2x text-primary tw-mb-2"></i>
                                <p class="tw-font-medium tw-mb-0"><?php echo _l('kt_einvoice_menu_invoices'); ?></p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="<?php echo admin_url('kt_einvoice/batch_issue'); ?>" class="panel_s tw-block hover:tw-border-blue-300 tw-transition">
                            <div class="panel-body tw-text-center tw-py-4">
                                <i class="fa fa-paper-plane fa-2x text-success tw-mb-2"></i>
                                <p class="tw-font-medium tw-mb-0"><?php echo _l('kt_einvoice_menu_batch_issue'); ?></p>
                            </div>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
