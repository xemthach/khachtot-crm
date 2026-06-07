<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$currency = (string) ($business_kpis['currency'] ?? 'VND');
$money = function ($amount) use ($currency) {
    return app_format_money((float) $amount, $currency, true);
};
$kpiCards = [
    ['label' => 'Doanh nghiệp đang hoạt động', 'value' => number_format((int) ($business_kpis['active_tenants'] ?? 0)), 'url' => admin_url('kt_saas/tenants?status=active')],
    ['label' => 'MRR', 'value' => $money($business_kpis['mrr'] ?? 0), 'url' => admin_url('kt_saas/subscriptions')],
    ['label' => 'ARR', 'value' => $money($business_kpis['arr'] ?? 0), 'url' => admin_url('kt_saas/subscriptions')],
    ['label' => 'Doanh thu tháng này', 'value' => $money($business_kpis['revenue_this_month'] ?? 0), 'url' => admin_url('kt_saas/payments')],
];
$customerCards = [
    ['label' => 'Dùng thử', 'value' => (int) ($customer_status['trial'] ?? 0), 'url' => admin_url('kt_saas/subscriptions?status=trial'), 'class' => 'info'],
    ['label' => 'Đang hoạt động', 'value' => (int) ($customer_status['active'] ?? 0), 'url' => admin_url('kt_saas/tenants?status=active'), 'class' => 'success'],
    ['label' => 'Sắp hết hạn 7 ngày', 'value' => (int) ($customer_status['expiring_soon'] ?? 0), 'url' => admin_url('kt_saas/subscriptions'), 'class' => 'warning'],
    ['label' => 'Quá hạn thanh toán', 'value' => (int) ($customer_status['overdue'] ?? 0), 'url' => admin_url('kt_saas/invoices?status=overdue'), 'class' => 'danger'],
    ['label' => 'Tạm ngưng', 'value' => (int) ($customer_status['suspended'] ?? 0), 'url' => admin_url('kt_saas/tenants?status=suspended'), 'class' => 'default'],
];
$billingCards = [
    ['label' => 'Hóa đơn chờ thanh toán', 'value' => (int) ($billing_health['pending_invoices'] ?? 0), 'url' => admin_url('kt_saas/invoices?status=pending_payment'), 'class' => 'warning'],
    ['label' => 'Hóa đơn quá hạn', 'value' => (int) ($billing_health['overdue_invoices'] ?? 0), 'url' => admin_url('kt_saas/invoices?status=overdue'), 'class' => 'danger'],
    ['label' => 'Thanh toán thành công tháng này', 'value' => (int) ($billing_health['paid_this_month'] ?? 0), 'url' => admin_url('kt_saas/payments?status=paid'), 'class' => 'success'],
    ['label' => 'Thanh toán lỗi/chưa khớp', 'value' => (int) ($billing_health['payment_issues'] ?? 0), 'url' => admin_url('kt_sepay/transactions'), 'class' => 'danger'],
];
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-items-start tw-justify-between tw-gap-4 tw-flex-wrap">
                    <div>
                        <h4 class="tw-mb-1">Bảng điều khiển CRM</h4>
                        <p class="text-muted tw-mb-0">Theo dõi doanh thu, khách hàng và các cảnh báo vận hành chính.</p>
                    </div>
                    <div class="btn-group">
                        <a href="<?php echo admin_url('kt_saas/tenants'); ?>" class="btn btn-primary">Tạo doanh nghiệp</a>
                        <a href="<?php echo admin_url('kt_saas/invoices'); ?>" class="btn btn-default">Hóa đơn gói CRM</a>
                        <a href="<?php echo admin_url('kt_saas/run_billing_cycle'); ?>" class="btn btn-default">Chạy chu kỳ thanh toán</a>
                        <a href="<?php echo admin_url('kt_saas/architecture'); ?>" class="btn btn-default">Kiểm tra hệ thống</a>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($summary['tenants'])) { ?>
        <div class="alert alert-info mtop20">
            Chưa có doanh nghiệp nào. Hãy tạo tenant đầu tiên hoặc chạy đăng ký thử nghiệm.
        </div>
        <?php } ?>

        <div class="row mtop20">
            <?php foreach ($kpiCards as $card) { ?>
            <div class="col-md-3">
                <a class="kt-dashboard-card" href="<?php echo html_escape($card['url']); ?>">
                    <span><?php echo html_escape($card['label']); ?></span>
                    <strong><?php echo html_escape($card['value']); ?></strong>
                </a>
            </div>
            <?php } ?>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4>Tình trạng khách hàng</h4>
                        <div class="row">
                            <?php foreach ($customerCards as $card) { ?>
                            <div class="col-md-15 col-sm-6">
                                <a class="kt-status-card" href="<?php echo html_escape($card['url']); ?>">
                                    <span class="label label-<?php echo html_escape($card['class']); ?>"><?php echo (int) $card['value']; ?></span>
                                    <?php echo html_escape($card['label']); ?>
                                </a>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4>Sức khỏe thanh toán</h4>
                        <div class="row">
                            <?php foreach ($billingCards as $card) { ?>
                            <div class="col-md-6">
                                <a class="kt-small-card" href="<?php echo html_escape($card['url']); ?>">
                                    <span class="label label-<?php echo html_escape($card['class']); ?>"><?php echo (int) $card['value']; ?></span>
                                    <?php echo html_escape($card['label']); ?>
                                </a>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <h4>Phễu đăng ký 7 ngày</h4>
                        <table class="table table-condensed">
                            <tbody>
                                <tr><th>Đăng ký mới</th><td><?php echo (int) ($signup_funnel['signup_created'] ?? 0); ?></td></tr>
                                <tr><th>Đã có hóa đơn</th><td><?php echo (int) ($signup_funnel['invoice_public_signup'] ?? 0); ?></td></tr>
                                <tr><th>Đã thanh toán</th><td><?php echo (int) ($signup_funnel['paid_public_signup'] ?? 0); ?></td></tr>
                                <tr><th>Đang khởi tạo</th><td><?php echo (int) ($signup_funnel['provisioning_in_queue'] ?? 0); ?></td></tr>
                                <tr><th>Khởi tạo xong</th><td><?php echo (int) ($signup_funnel['provisioning_done'] ?? 0); ?></td></tr>
                                <tr><th>Đã hoạt động</th><td><?php echo (int) ($signup_funnel['tenant_active'] ?? 0); ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4>Cảnh báo vận hành</h4>
                        <?php if (!empty($operations_alerts['items'])) { ?>
                            <?php foreach ($operations_alerts['items'] as $alert) { ?>
                            <a href="<?php echo html_escape($alert['url']); ?>" class="kt-alert-row">
                                <span class="label label-<?php echo html_escape($alert['level']); ?>"><?php echo (int) $alert['count']; ?></span>
                                <?php echo html_escape($alert['label']); ?>
                            </a>
                            <?php } ?>
                        <?php } else { ?>
                            <p class="text-muted">Không có cảnh báo cần xử lý.</p>
                        <?php } ?>

                        <?php if (!empty($operations_alerts['overage_rows'])) { ?>
                        <hr>
                        <h5>Doanh nghiệp vượt giới hạn</h5>
                        <div class="table-responsive">
                            <table class="table table-condensed">
                                <tbody>
                                <?php foreach (array_slice($operations_alerts['overage_rows'], 0, 5) as $row) { ?>
                                    <tr>
                                        <td><?php echo html_escape($row['company_name'] ?? $row['tenant_code']); ?></td>
                                        <td><?php echo html_escape(kt_saas_metric_label($row['metric_key'])); ?></td>
                                        <td><span class="label label-warning"><?php echo html_escape(kt_saas_metric_value($row['metric_key'], $row['excess_value'])); ?></span></td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php } ?>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <h4>Hoạt động kinh doanh gần đây</h4>
                        <?php if (!empty($business_activity)) { ?>
                            <ul class="kt-activity-feed">
                                <?php foreach ($business_activity as $activity) { ?>
                                <li>
                                    <span class="label label-<?php echo kt_saas_status_badge_class($activity['severity']); ?>"><?php echo html_escape(ucfirst($activity['severity'])); ?></span>
                                    <strong><?php echo html_escape($activity['message']); ?></strong>
                                    <small><?php echo _dt($activity['created_at']); ?></small>
                                </li>
                                <?php } ?>
                            </ul>
                        <?php } else { ?>
                            <p class="text-muted">Chưa có hoạt động kinh doanh gần đây.</p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel_s">
            <div class="panel-body">
                <h4>
                    <a data-toggle="collapse" href="#kt-saas-technical-diagnostics" aria-expanded="false">
                        Thông tin kỹ thuật
                    </a>
                </h4>
                <div class="collapse" id="kt-saas-technical-diagnostics">
                    <div class="row">
                        <div class="col-md-7">
                            <h5>Trạng thái dữ liệu landlord</h5>
                            <div class="table-responsive">
                                <table class="table table-condensed">
                                    <thead>
                                        <tr><th>Bảng</th><th>Trạng thái</th><th>Số bản ghi</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($landlord_tables as $row) { ?>
                                        <tr>
                                            <td><?php echo html_escape($row['table']); ?></td>
                                            <td><span class="label label-<?php echo $row['exists'] ? 'success' : 'danger'; ?>"><?php echo $row['exists'] ? 'Sẵn sàng' : 'Thiếu'; ?></span></td>
                                            <td><?php echo (int) $row['records']; ?></td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <h5>Runtime</h5>
                            <p><strong>Tenant runtime:</strong> <?php echo $runtime['runtime_enabled'] === '1' ? 'Bật' : 'Tắt'; ?></p>
                            <p><strong>Landlord host:</strong> <?php echo html_escape($runtime['landlord_host']); ?></p>
                            <p><strong>Tên miền gốc:</strong> <?php echo html_escape($runtime['base_domain']); ?></p>
                            <p><strong>Queue mode:</strong> <?php echo html_escape($runtime['queue_mode']); ?></p>
                            <p><strong>Ngày lưu dữ liệu sử dụng:</strong> <?php echo (int) $runtime['usage_retention_days']; ?></p>
                            <p><strong>Manifest:</strong> <?php echo (int) $runtime['manifests']; ?></p>
                            <p><strong>Dữ liệu usage chờ dọn:</strong> <?php echo (int) $old_usage_rows; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
.kt-dashboard-card,
.kt-small-card,
.kt-status-card,
.kt-alert-row {
    display: block;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 14px;
    margin-bottom: 15px;
    background: #fff;
    color: #1f2937;
}
.kt-dashboard-card:hover,
.kt-small-card:hover,
.kt-status-card:hover,
.kt-alert-row:hover {
    text-decoration: none;
    border-color: #cbd5e1;
}
.kt-dashboard-card span {
    display: block;
    color: #6b7280;
    font-size: 12px;
    text-transform: uppercase;
}
.kt-dashboard-card strong {
    display: block;
    margin-top: 8px;
    font-size: 24px;
}
.kt-status-card .label,
.kt-small-card .label,
.kt-alert-row .label {
    margin-right: 8px;
}
.kt-activity-feed {
    list-style: none;
    padding-left: 0;
    margin-bottom: 0;
}
.kt-activity-feed li {
    padding: 10px 0;
    border-bottom: 1px solid #f1f5f9;
}
.kt-activity-feed li:last-child {
    border-bottom: 0;
}
.kt-activity-feed small {
    display: block;
    color: #6b7280;
    margin-top: 4px;
}
@media (min-width: 992px) {
    .col-md-15 {
        width: 20%;
        float: left;
    }
}
</style>
<?php init_tail(); ?>
