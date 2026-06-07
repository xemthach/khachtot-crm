<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$isTenantRuntime = function_exists('kt_saas_is_tenant_runtime') && kt_saas_is_tenant_runtime();
$tenantProfile = $isTenantRuntime && function_exists('kt_saas_current_tenant') ? kt_saas_current_tenant() : null;
?>
<div id="wrapper">
    <div class="screen-options-area"></div>
    <div class="screen-options-btn">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="tw-w-5 tw-h-5 ltr:tw-mr-1 rtl:tw-ml-1">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <?= _l('dashboard_options'); ?>
    </div>
    <div class="content">
        <div class="row">
            <?php $this->load->view('admin/includes/alerts'); ?>
            <?php hooks()->do_action('before_start_render_dashboard_content'); ?>

            <?php if ($isTenantRuntime) { ?>
                <?php
                $tenantDashboard = is_array($tenant_business_dashboard ?? null) ? $tenant_business_dashboard : [];
                $companyName = (string) ($tenantDashboard['company_name'] ?? $tenantProfile['company_name'] ?? get_option('companyname') ?: 'Doanh nghiệp');
                $currency = (string) ($tenantDashboard['currency'] ?? get_base_currency()->name ?? 'VND');
                $snapshot = array_values(array_filter((array) ($tenantDashboard['snapshot'] ?? []), static function ($card) {
                    return !isset($card['visible']) || !empty($card['visible']);
                }));
                $businessHealth = (array) ($tenantDashboard['business_health'] ?? []);
                $todayActions = (array) ($tenantDashboard['today_actions'] ?? []);
                $salesFunnel = (array) ($tenantDashboard['sales_funnel'] ?? []);
                $salesFunnelRows = isset($salesFunnel['rows']) ? (array) $salesFunnel['rows'] : $salesFunnel;
                $finance = (array) ($tenantDashboard['finance'] ?? []);
                $usage = (array) ($tenantDashboard['usage_health'] ?? []);
                $integrations = (array) ($tenantDashboard['integration_health'] ?? []);
                $quickActions = (array) ($tenantDashboard['quick_actions'] ?? []);
                $checklist = (array) ($tenantDashboard['onboarding_checklist'] ?? $tenantDashboard['onboarding'] ?? []);
                $isNewTenant = empty($tenantDashboard['has_minimum_data']);
                $formatNumber = static function ($value) {
                    return number_format((float) $value, 0, ',', '.');
                };
                $formatMoney = static function ($value) use ($currency) {
                    if ($value === null) {
                        return 'Không có quyền xem';
                    }
                    return number_format((float) $value, 0, ',', '.') . ' ' . html_escape($currency);
                };
                $levelClass = static function ($level) {
                    if ($level === 'danger') {
                        return 'kt-dashboard-danger';
                    }
                    if ($level === 'warning') {
                        return 'kt-dashboard-warning';
                    }
                    if ($level === 'muted') {
                        return 'kt-dashboard-muted-pill';
                    }
                    return 'kt-dashboard-ok';
                };
                ?>
                <style>
                    .kt-dashboard-header { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; margin-bottom:16px; }
                    .kt-dashboard-muted { color:#64748b; }
                    .kt-dashboard-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; }
                    .kt-dashboard-two { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
                    .kt-dashboard-three { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; }
                    .kt-dashboard-card { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:16px; box-shadow:0 1px 2px rgba(15,23,42,.04); }
                    .kt-dashboard-value { font-size:24px; font-weight:700; color:#111827; margin:8px 0 2px; }
                    .kt-dashboard-score { font-size:38px; line-height:1; font-weight:800; color:#111827; margin:10px 0; }
                    .kt-dashboard-row { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid #eef2f7; }
                    .kt-dashboard-row:last-child { border-bottom:0; }
                    .kt-dashboard-pill { display:inline-block; border-radius:999px; padding:3px 9px; font-size:12px; font-weight:600; white-space:nowrap; }
                    .kt-dashboard-ok { background:#dcfce7; color:#166534; }
                    .kt-dashboard-warning { background:#fef3c7; color:#92400e; }
                    .kt-dashboard-danger { background:#fee2e2; color:#991b1b; }
                    .kt-dashboard-muted-pill { background:#f1f5f9; color:#475569; }
                    .kt-dashboard-progress { height:8px; border-radius:999px; background:#e5e7eb; overflow:hidden; margin-top:8px; }
                    .kt-dashboard-progress span { display:block; height:100%; background:#22c55e; }
                    .kt-dashboard-progress .warning { background:#f59e0b; }
                    .kt-dashboard-progress .danger { background:#ef4444; }
                    .kt-dashboard-actions { display:flex; flex-wrap:wrap; gap:8px; }
                    .kt-dashboard-priority { border-left:4px solid #ef4444; }
                    @media (max-width: 991px) {
                        .kt-dashboard-grid, .kt-dashboard-two, .kt-dashboard-three { grid-template-columns:1fr; }
                        .kt-dashboard-header { display:block; }
                    }
                </style>

                <div class="col-md-12">
                    <div class="kt-dashboard-header">
                        <div>
                            <h3 class="no-margin">Trung tâm điều hành doanh nghiệp</h3>
                            <p class="kt-dashboard-muted mtop10 no-margin"><?php echo html_escape($companyName); ?></p>
                        </div>
                        <div class="kt-dashboard-actions">
                            <a class="btn btn-default" href="<?php echo admin_url('clients/client'); ?>">Thêm khách hàng</a>
                            <a class="btn btn-default" href="<?php echo admin_url('leads'); ?>">Thêm lead</a>
                            <a class="btn btn-default" href="<?php echo admin_url('invoices/invoice'); ?>">Tạo hóa đơn</a>
                            <a class="btn btn-primary" href="<?php echo admin_url('kt_saas/tenant_subscription'); ?>">Gói CRM</a>
                        </div>
                    </div>
                </div>

                <?php if ($isNewTenant && $checklist) { ?>
                    <div class="col-md-12 mtop15">
                        <div class="kt-dashboard-card kt-dashboard-priority">
                            <h4 class="no-margin">Chào mừng đến với Khách Tốt CRM</h4>
                            <p class="kt-dashboard-muted mtop10">Hãy hoàn tất các bước khởi tạo để bắt đầu vận hành CRM.</p>
                            <div class="kt-dashboard-three mtop15">
                                <?php foreach ($checklist as $item) { ?>
                                    <div class="kt-dashboard-row">
                                        <span>
                                            <?php if (!empty($item['done'])) { ?>
                                                <span class="label label-success">&#10003; Hoàn tất</span>
                                            <?php } else { ?>
                                                <span class="label label-default">&#9675; Chưa xong</span>
                                            <?php } ?>
                                            <?php echo html_escape((string) ($item['label'] ?? '')); ?>
                                        </span>
                                        <?php if (!isset($item['action_visible']) || !empty($item['action_visible'])) { ?>
                                            <a href="<?php echo html_escape((string) ($item['url'] ?? '#')); ?>" class="btn btn-default btn-xs">
                                                <?php echo !empty($item['done']) ? 'Xem' : 'Mở'; ?>
                                            </a>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                <?php } ?>

                <?php if ($isNewTenant && $quickActions) { ?>
                    <div class="col-md-12 mtop15">
                        <div class="kt-dashboard-card">
                            <h4 class="no-margin">Thao tác nhanh</h4>
                            <div class="kt-dashboard-actions mtop15">
                                <?php foreach ($quickActions as $action) { ?>
                                    <?php if (!isset($action['visible']) || !empty($action['visible'])) { ?>
                                        <a class="btn btn-default" href="<?php echo html_escape((string) ($action['url'] ?? '#')); ?>"><?php echo html_escape((string) ($action['label'] ?? '')); ?></a>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                <?php } ?>

                <div class="col-md-12 mtop15">
                    <div class="kt-dashboard-grid">
                        <?php foreach ($snapshot as $card) { ?>
                            <a class="kt-dashboard-card" href="<?php echo html_escape((string) ($card['url'] ?? '#')); ?>">
                                <div class="kt-dashboard-muted"><?php echo html_escape((string) ($card['label'] ?? '')); ?></div>
                                <div class="kt-dashboard-value">
                                    <?php echo !empty($card['is_money']) ? $formatMoney($card['value'] ?? 0) : $formatNumber($card['value'] ?? 0); ?>
                                </div>
                                <div class="kt-dashboard-muted"><?php echo html_escape((string) ($card['hint'] ?? '')); ?></div>
                            </a>
                        <?php } ?>
                    </div>
                </div>

                <div class="col-md-12 mtop15">
                    <div class="kt-dashboard-two">
                        <div class="kt-dashboard-card kt-dashboard-priority">
                            <h4 class="no-margin">Việc cần xử lý hôm nay</h4>
                            <div class="mtop10">
                                <?php if ($todayActions) { ?>
                                    <?php foreach ($todayActions as $action) { ?>
                                        <a class="kt-dashboard-row" href="<?php echo html_escape((string) ($action['url'] ?? '#')); ?>">
                                            <span><?php echo html_escape((string) ($action['label'] ?? '')); ?></span>
                                            <span class="kt-dashboard-pill <?php echo $levelClass($action['level'] ?? 'ok'); ?>">
                                                <?php echo $formatNumber($action['count'] ?? 0); ?>
                                            </span>
                                        </a>
                                    <?php } ?>
                                <?php } else { ?>
                                    <p class="kt-dashboard-muted mtop10">Không có việc khẩn cấp cần xử lý.</p>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="kt-dashboard-card">
                            <h4 class="no-margin">Sức khỏe doanh nghiệp</h4>
                            <?php if ($isNewTenant || !isset($businessHealth['score']) || $businessHealth['score'] === null) { ?>
                                <div class="kt-dashboard-score" style="font-size:26px;"><?php echo html_escape((string) ($businessHealth['status_label'] ?? 'Sẵn sàng khởi tạo')); ?></div>
                            <?php } else { ?>
                                <div class="kt-dashboard-score"><?php echo $formatNumber($businessHealth['score']); ?>/100</div>
                            <?php } ?>
                            <span class="kt-dashboard-pill <?php echo $levelClass($businessHealth['level'] ?? 'success'); ?>">
                                <?php echo html_escape((string) ($businessHealth['message'] ?? 'Doanh nghiệp đang vận hành tốt')); ?>
                            </span>
                            <p class="kt-dashboard-muted mtop15 no-margin">
                                <?php echo $isNewTenant ? 'Hoàn tất checklist khởi tạo để bắt đầu đo sức khỏe doanh nghiệp.' : 'Điểm được tính từ công nợ, lead, ticket, hợp đồng, hạn mức CRM và sức khỏe tích hợp.'; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-12 mtop15">
                    <div class="kt-dashboard-two">
                        <div class="kt-dashboard-card">
                            <h4 class="no-margin">Phễu bán hàng</h4>
                            <div class="mtop10">
                                <?php foreach ($salesFunnelRows as $stage) { ?>
                                    <a class="kt-dashboard-row" href="<?php echo html_escape((string) ($stage['url'] ?? admin_url('leads'))); ?>">
                                        <span><?php echo html_escape((string) ($stage['label'] ?? '')); ?></span>
                                        <strong><?php echo $formatNumber($stage['count'] ?? 0); ?></strong>
                                    </a>
                                <?php } ?>
                                <?php if (!$salesFunnelRows) { ?>
                                    <p class="kt-dashboard-muted mtop10">Chưa có dữ liệu lead.</p>
                                <?php } ?>
                                <?php if ((int) ($salesFunnel['total_statuses'] ?? 0) > count($salesFunnelRows)) { ?>
                                    <a class="btn btn-default btn-sm mtop10" href="<?php echo html_escape((string) ($salesFunnel['all_url'] ?? admin_url('leads'))); ?>">Xem toàn bộ pipeline</a>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="kt-dashboard-card">
                            <h4 class="no-margin">Kiểm soát tài chính</h4>
                            <?php foreach ($finance as $row) { ?>
                                <?php if ((!isset($row['visible']) || !empty($row['visible'])) && (empty($row['hide_zero']) || (float) ($row['value'] ?? 0) > 0)) { ?>
                                    <div class="kt-dashboard-row">
                                        <span><?php echo html_escape((string) ($row['label'] ?? '')); ?></span>
                                        <strong><?php echo !empty($row['is_money']) ? $formatMoney($row['value'] ?? null) : html_escape((string) ($row['value'] ?? '')); ?></strong>
                                    </div>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-12 mtop15">
                    <div class="kt-dashboard-two">
                        <div class="kt-dashboard-card">
                            <h4 class="no-margin">Gói CRM & hạn mức</h4>
                            <?php if (!empty($usage['plan_name'])) { ?>
                                <div class="kt-dashboard-row"><span>Gói hiện tại</span><strong><?php echo html_escape((string) $usage['plan_name']); ?></strong></div>
                            <?php } ?>
                            <?php if (!empty($usage['period_end'])) { ?>
                                <div class="kt-dashboard-row"><span>Ngày hết hạn</span><strong><?php echo html_escape((string) $usage['period_end']); ?></strong></div>
                            <?php } ?>
                            <?php if (isset($usage['days_left'])) { ?>
                                <div class="kt-dashboard-row">
                                    <span>Số ngày còn lại</span>
                                    <strong class="<?php echo (int) $usage['days_left'] <= 7 ? 'text-danger' : ''; ?>"><?php echo $formatNumber($usage['days_left']); ?> ngày</strong>
                                </div>
                            <?php } ?>
                            <?php foreach ((array) ($usage['limits'] ?? []) as $limit) { ?>
                                <div class="mtop10">
                                    <div class="kt-dashboard-row">
                                        <span><?php echo html_escape((string) ($limit['label'] ?? '')); ?></span>
                                        <strong><?php echo html_escape((string) ($limit['display'] ?? '')); ?></strong>
                                    </div>
                                    <?php if (isset($limit['percent'])) { ?>
                                        <div class="kt-dashboard-progress"><span class="<?php echo html_escape((string) ($limit['level'] ?? '')); ?>" style="width:<?php echo min(100, max(0, (float) $limit['percent'])); ?>%"></span></div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                            <div class="mtop15">
                                <a href="<?php echo admin_url('kt_saas/tenant_usage'); ?>" class="btn btn-default btn-sm">Xem hạn mức</a>
                                <a href="<?php echo admin_url('kt_saas/tenant_subscription'); ?>" class="btn btn-primary btn-sm">Nâng cấp gói</a>
                            </div>
                        </div>
                        <div class="kt-dashboard-card">
                            <h4 class="no-margin">Sức khỏe tích hợp</h4>
                            <div class="mtop10">
                                <?php foreach ($integrations as $integration) { ?>
                                    <a class="kt-dashboard-row" href="<?php echo html_escape((string) ($integration['url'] ?? '#')); ?>">
                                        <span>
                                            <?php echo html_escape((string) ($integration['label'] ?? '')); ?>
                                            <?php if (!empty($integration['detail'])) { ?><br><small class="kt-dashboard-muted"><?php echo html_escape((string) $integration['detail']); ?></small><?php } ?>
                                        </span>
                                        <span class="kt-dashboard-pill <?php echo $levelClass($integration['level'] ?? 'ok'); ?>"><?php echo html_escape((string) ($integration['status_label'] ?? '')); ?></span>
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!$isNewTenant) { ?>
                <div class="col-md-12 mtop15">
                    <div class="kt-dashboard-card">
                        <h4 class="no-margin">Thao tác nhanh</h4>
                        <div class="kt-dashboard-actions mtop15">
                            <?php foreach ($quickActions as $action) { ?>
                                <?php if (!isset($action['visible']) || !empty($action['visible'])) { ?>
                                    <a class="btn btn-default" href="<?php echo html_escape((string) ($action['url'] ?? '#')); ?>"><?php echo html_escape((string) ($action['label'] ?? '')); ?></a>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <?php } ?>
            <?php } else { ?>
                <div class="clearfix"></div>

                <div class="col-md-12 mtop20" data-container="top-12">
                    <?php render_dashboard_widgets('top-12'); ?>
                </div>

                <?php hooks()->do_action('after_dashboard_top_container'); ?>

                <div class="col-md-6" data-container="middle-left-6">
                    <?php render_dashboard_widgets('middle-left-6'); ?>
                </div>
                <div class="col-md-6" data-container="middle-right-6">
                    <?php render_dashboard_widgets('middle-right-6'); ?>
                </div>

                <?php hooks()->do_action('after_dashboard_half_container'); ?>

                <div class="col-md-8" data-container="left-8">
                    <?php render_dashboard_widgets('left-8'); ?>
                </div>
                <div class="col-md-4" data-container="right-4">
                    <?php render_dashboard_widgets('right-4'); ?>
                </div>

                <div class="clearfix"></div>

                <div class="col-md-4" data-container="bottom-left-4">
                    <?php render_dashboard_widgets('bottom-left-4'); ?>
                </div>
                <div class="col-md-4" data-container="bottom-middle-4">
                    <?php render_dashboard_widgets('bottom-middle-4'); ?>
                </div>
                <div class="col-md-4" data-container="bottom-right-4">
                    <?php render_dashboard_widgets('bottom-right-4'); ?>
                </div>
            <?php } ?>

            <?php hooks()->do_action('after_dashboard'); ?>
        </div>
    </div>
</div>
<script>
    app.calendarIDs = '<?= json_encode($google_ids_calendars); ?>';
</script>
<?php init_tail(); ?>
<?php $this->load->view('admin/utilities/calendar_template'); ?>
<?php $this->load->view('admin/dashboard/dashboard_js'); ?>
</body>

</html>
