<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">

                <h4 class="tw-text-xl tw-font-bold tw-text-gray-800 tw-mb-5">
                    <i class="fa fa-globe tw-text-blue-600 tw-mr-2"></i>
                    Tổng Quan eInvoice Toàn Hệ Thống
                </h4>

                <!-- Stats Cards -->
                <div class="row tw-mb-5">
                    <?php
                    $cards = [
                        ['label' => 'Tenant đang hoạt động',     'value' => $stats['active_tenants'],          'icon' => 'fa-users',         'color' => 'tw-text-blue-600'],
                        ['label' => 'HĐ phát hành (tháng này)', 'value' => $stats['total_issued_this_month'],  'icon' => 'fa-check-circle',  'color' => 'tw-text-green-600'],
                        ['label' => 'Đang xử lý',                'value' => $stats['total_pending'],            'icon' => 'fa-clock-o',       'color' => 'tw-text-yellow-500'],
                        ['label' => 'Lỗi',                       'value' => $stats['total_failed'],             'icon' => 'fa-times-circle',  'color' => 'tw-text-red-500'],
                        ['label' => 'Tổng hóa đơn',              'value' => $stats['total_records'],            'icon' => 'fa-file',          'color' => 'tw-text-gray-600'],
                    ];
                    foreach ($cards as $card): ?>
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="panel_s">
                            <div class="panel-body tw-text-center tw-py-4">
                                <i class="fa <?php echo $card['icon']; ?> fa-2x <?php echo $card['color']; ?> tw-mb-2"></i>
                                <div class="tw-text-3xl tw-font-bold"><?php echo number_format($card['value']); ?></div>
                                <div class="text-muted tw-text-xs tw-mt-1"><?php echo $card['label']; ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Top Tenants -->
                <div class="panel_s">
                    <div class="panel-heading">
                        <h4 class="panel-title">Top Tenant Dùng eInvoice (tháng <?php echo date('n/Y'); ?>)</h4>
                    </div>
                    <div class="panel-body no-padding">
                        <table class="table table-hover no-margin">
                            <thead class="tw-bg-gray-50">
                                <tr>
                                    <th>#</th>
                                    <th>Tenant ID</th>
                                    <th>Tổng HĐ</th>
                                    <th>Đã phát hành</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($top_tenants)): ?>
                                <tr><td colspan="5" class="tw-text-center text-muted tw-py-6">Chưa có dữ liệu</td></tr>
                                <?php else: ?>
                                <?php foreach ($top_tenants as $i => $t): ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td><strong>#<?php echo $t['tenant_id']; ?></strong></td>
                                    <td><?php echo $t['total']; ?></td>
                                    <td><span class="label label-success"><?php echo $t['issued']; ?></span></td>
                                    <td>
                                        <a href="<?php echo admin_url('kt_einvoice/admin/tenant_settings/' . $t['tenant_id']); ?>" class="btn btn-xs btn-default">
                                            <i class="fa fa-eye"></i> Xem settings
                                        </a>
                                        <a href="<?php echo admin_url('kt_einvoice/admin/all_records?tenant_id=' . $t['tenant_id']); ?>" class="btn btn-xs btn-default">
                                            <i class="fa fa-list"></i> Xem HĐ
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row tw-mt-4">
                    <div class="col-md-3">
                        <a href="<?php echo admin_url('kt_einvoice/admin/plan_features'); ?>" class="panel_s tw-block">
                            <div class="panel-body tw-text-center tw-py-4">
                                <i class="fa fa-sliders fa-2x text-info tw-mb-2"></i>
                                <p class="tw-font-medium">Cấu hình theo gói</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?php echo admin_url('kt_einvoice/admin/all_records'); ?>" class="panel_s tw-block">
                            <div class="panel-body tw-text-center tw-py-4">
                                <i class="fa fa-list fa-2x text-primary tw-mb-2"></i>
                                <p class="tw-font-medium">Tất cả HĐ</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?php echo admin_url('kt_einvoice/admin/api_logs'); ?>" class="panel_s tw-block">
                            <div class="panel-body tw-text-center tw-py-4">
                                <i class="fa fa-code fa-2x text-warning tw-mb-2"></i>
                                <p class="tw-font-medium">API Logs</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?php echo admin_url('kt_einvoice/admin/cron_logs'); ?>" class="panel_s tw-block">
                            <div class="panel-body tw-text-center tw-py-4">
                                <i class="fa fa-clock-o fa-2x text-success tw-mb-2"></i>
                                <p class="tw-font-medium">Cron Logs</p>
                            </div>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
