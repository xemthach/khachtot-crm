<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php $CI = &get_instance(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mb-2"><?php echo html_escape($title); ?></h4>
                <p class="text-muted"><?php echo html_escape($tenant['company_name'] ?? ''); ?></p>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4>Gói CRM</h4>
                        <p><strong><?php echo _l('kt_saas_plan_name'); ?>:</strong> <?php echo html_escape($subscription['plan_name'] ?? '-'); ?></p>
                        <p><strong><?php echo _l('kt_saas_status'); ?>:</strong> <span class="label label-<?php echo kt_saas_status_badge_class($subscription['status'] ?? 'draft'); ?>"><?php echo html_escape(kt_saas_subscription_statuses()[$subscription['status'] ?? 'draft'] ?? ucfirst((string) ($subscription['status'] ?? 'draft'))); ?></span></p>
                        <p><strong><?php echo _l('kt_saas_billing_cycle'); ?>:</strong> <?php echo html_escape(kt_saas_billing_cycles()[$subscription['billing_cycle'] ?? ''] ?? ($subscription['billing_cycle'] ?? '-')); ?></p>
                        <?php $subscriptionCurrency = ($subscription['currency'] ?? ($subscription['currency_code'] ?? ($tenant['currency'] ?? ''))); ?>
                        <p><strong><?php echo _l('kt_saas_price'); ?>:</strong> <?php echo app_format_money((float) ($subscription['price'] ?? 0), $subscriptionCurrency, true) . ' ' . html_escape($subscriptionCurrency); ?></p>
                        <p><strong><?php echo _l('kt_saas_started_at'); ?>:</strong> <?php echo !empty($subscription['started_at']) ? html_escape($subscription['started_at']) : '-'; ?></p>
                        <p><strong><?php echo _l('kt_saas_trial_ends_at'); ?>:</strong> <?php echo !empty($subscription['trial_ends_at']) ? html_escape($subscription['trial_ends_at']) : '-'; ?></p>
                        <p><strong><?php echo _l('kt_saas_period_end'); ?>:</strong> <?php echo !empty($subscription['current_period_end_at']) ? html_escape($subscription['current_period_end_at']) : '-'; ?></p>
                        <p><strong><?php echo _l('kt_saas_next_billing_at'); ?>:</strong> <?php echo !empty($subscription['next_billing_at']) ? html_escape($subscription['next_billing_at']) : '-'; ?></p>
                        <hr>
                        <form action="<?php echo admin_url('kt_saas/tenant_request_renewal'); ?>" method="post" class="tw-inline-block">
                            <input type="hidden" name="<?php echo html_escape($CI->security->get_csrf_token_name()); ?>" value="<?php echo html_escape($CI->security->get_csrf_hash()); ?>">
                            <button type="submit" class="btn btn-primary"><?php echo _l('kt_saas_renew_now'); ?></button>
                        </form>
                        <?php if (!empty($scheduled_plan_change)) { ?>
                            <hr>
                            <div class="alert alert-warning tw-mb-0">
                                <strong><?php echo _l('kt_saas_plan_change_pending'); ?>:</strong>
                                <?php echo html_escape($scheduled_plan_change['target_plan_name'] ?? $scheduled_plan_change['target_plan_code'] ?? '-'); ?>
                                <?php if (!empty($scheduled_plan_change['scheduled_at'])) { ?>
                                    <br><small><?php echo _l('kt_saas_downgrade_effective_at'); ?>: <?php echo html_escape($scheduled_plan_change['scheduled_at']); ?></small>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4>Giới hạn sử dụng</h4>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('kt_saas_metric'); ?></th>
                                        <th><?php echo _l('kt_saas_used'); ?></th>
                                        <th><?php echo _l('kt_saas_limit'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (($profile['limits'] ?? []) as $metric => $limitValue) { ?>
                                        <tr>
                                            <td><?php echo html_escape(kt_saas_metric_label($metric)); ?></td>
                                            <td><?php echo html_escape(kt_saas_metric_value($metric, $usage[$metric] ?? 0)); ?></td>
                                            <td><?php echo html_escape(kt_saas_metric_value($metric, $limitValue, true)); ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .kt-saas-pricing-container { margin-top: 30px; margin-bottom: 30px; }
            .kt-saas-pricing-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; margin-top: 20px; }
            .kt-saas-pricing-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 30px 24px; display: flex; flex-direction: column; justify-content: space-between; position: relative; transition: all .3s cubic-bezier(.4,0,.2,1); box-shadow: 0 4px 6px rgba(0,0,0,.01), 0 1px 3px rgba(0,0,0,.01); }
            .kt-saas-pricing-card:hover { transform: translateY(-6px); box-shadow: 0 20px 25px -5px rgba(0,0,0,.05), 0 10px 10px -5px rgba(0,0,0,.02); border-color: #cbd5e1; }
            .kt-saas-pricing-card.current-active-plan { border: 2px solid #2563eb; box-shadow: 0 10px 25px rgba(37,99,235,.08); }
            .kt-saas-card-badge { position: absolute; top: 15px; right: 15px; font-size: 10px; font-weight: 700; padding: 4px 10px; border-radius: 9999px; text-transform: uppercase; letter-spacing: .05em; }
            .kt-saas-card-badge.current-badge { background-color: #dbeafe; color: #1e40af; }
            .kt-saas-card-badge.pending-badge { background-color: #fef3c7; color: #92400e; }
            .kt-saas-plan-title { font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 5px; margin-bottom: 2px; }
            .kt-saas-plan-code-label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 18px; display: inline-block; }
            .kt-saas-price-box { display: flex; align-items: baseline; flex-wrap: nowrap; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 20px; }
            .kt-saas-price-amount { font-size: 32px; font-weight: 800; color: #0f172a; line-height: 1; white-space: nowrap; font-variant-numeric: tabular-nums; }
            .kt-saas-price-currency { font-size: 14px; font-weight: 600; color: #475569; margin-left: 6px; }
            .kt-saas-price-period { font-size: 13px; color: #64748b; margin-left: 4px; }
            .kt-saas-features-list { list-style: none; padding: 0; margin: 0 0 25px 0; flex-grow: 1; }
            .kt-saas-feature-item { display: flex; align-items: flex-start; margin-bottom: 12px; font-size: 13.5px; color: #334155; }
            .kt-saas-feature-icon { color: #10b981; margin-right: 10px; font-size: 14px; line-height: 1.4; flex-shrink: 0; margin-top: 3px; }
            .kt-saas-feature-text { line-height: 1.4; }
            .kt-saas-feature-text strong { color: #0f172a; }
            .kt-saas-modules-section { margin-top: 15px; padding-top: 15px; border-top: 1px dashed #e2e8f0; }
            .kt-saas-modules-title { font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 8px; text-transform: uppercase; letter-spacing: .05em; }
            .kt-saas-module-tag { display: inline-block; background-color: #f8fafc; color: #334155; font-size: 11px; padding: 3px 8px; border-radius: 4px; margin-right: 5px; margin-bottom: 5px; border: 1px solid #e2e8f0; font-weight: 500; }
            .kt-saas-action-btn-wrapper { margin-top: auto; padding-top: 20px; }
            .kt-saas-action-btn-wrapper .btn { width: 100%; padding: 10px 16px; font-weight: 600; border-radius: 8px; transition: all .2s ease; display: block; }
            .kt-saas-action-btn-wrapper .btn-default { background-color: #f8fafc; border-color: #cbd5e1; color: #334155; }
            .kt-saas-action-btn-wrapper .btn-default:hover { background-color: #f1f5f9; border-color: #94a3b8; color: #0f172a; }
        </style>

        <div class="row kt-saas-pricing-container">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mb-4" style="font-weight: 700; color: #1e293b;">Các gói CRM sẵn có</h4>

                        <?php if (!empty($public_plans)) { ?>
                            <div class="kt-saas-pricing-grid">
                                <?php foreach ($public_plans as $plan) { ?>
                                    <?php
                                    $isCurrentPlan = (int) ($plan['id'] ?? 0) === (int) ($subscription['plan_id'] ?? 0);
                                    $openRequest = $open_plan_change_requests[(int) ($plan['id'] ?? 0)] ?? null;
                                    $scheduledRequest = !empty($scheduled_plan_change) && (int) ($scheduled_plan_change['target_plan_id'] ?? 0) === (int) ($plan['id'] ?? 0);
                                    $allowedModules = array_values(array_filter(
                                        json_decode($plan['module_json'] ?? '[]', true) ?: [],
                                        static function ($moduleCode) {
                                            return function_exists('kt_saas_is_tenant_safe_module')
                                                ? kt_saas_is_tenant_safe_module($moduleCode)
                                                : true;
                                        }
                                    ));
                                    ?>
                                    <div class="kt-saas-pricing-card <?php echo $isCurrentPlan ? 'current-active-plan' : ''; ?>">
                                        <?php if ($isCurrentPlan) { ?>
                                            <span class="kt-saas-card-badge current-badge"><?php echo _l('kt_saas_current_plan'); ?></span>
                                        <?php } elseif ($scheduledRequest || !empty($openRequest)) { ?>
                                            <span class="kt-saas-card-badge pending-badge"><?php echo _l('kt_saas_plan_change_pending'); ?></span>
                                        <?php } ?>

                                        <div>
                                            <h3 class="kt-saas-plan-title"><?php echo html_escape($plan['plan_name']); ?></h3>
                                            <span class="kt-saas-plan-code-label"><?php echo html_escape($plan['plan_code']); ?></span>

                                            <div class="kt-saas-price-box">
                                                <span class="kt-saas-price-amount"><?php echo app_format_money((float) ($plan['price'] ?? 0), ($plan['currency'] ?? ($tenant['currency'] ?? '')), true); ?></span>
                                                <span class="kt-saas-price-currency"><?php echo html_escape($plan['currency'] ?? ($tenant['currency'] ?? '')); ?></span>
                                                <span class="kt-saas-price-period">/ <?php echo html_escape(kt_saas_billing_cycles()[$plan['billing_cycle'] ?? ''] ?? ($plan['billing_cycle'] ?? '-')); ?></span>
                                            </div>

                                            <ul class="kt-saas-features-list">
                                                <li class="kt-saas-feature-item"><i class="fa fa-check-circle kt-saas-feature-icon"></i><span class="kt-saas-feature-text"><?php echo _l('kt_saas_staff_accounts'); ?>: <strong><?php echo (int) ($plan['limit_staff'] ?? 0) === 0 ? _l('kt_saas_unlimited') : number_format((float) ($plan['limit_staff'] ?? 0), 0) . ' tài khoản'; ?></strong></span></li>
                                                <li class="kt-saas-feature-item"><i class="fa fa-check-circle kt-saas-feature-icon"></i><span class="kt-saas-feature-text"><?php echo _l('kt_saas_customer_accounts'); ?>: <strong><?php echo (int) ($plan['limit_clients'] ?? 0) === 0 ? _l('kt_saas_unlimited') : number_format((float) ($plan['limit_clients'] ?? 0), 0) . ' khách hàng'; ?></strong></span></li>
                                                <li class="kt-saas-feature-item"><i class="fa fa-check-circle kt-saas-feature-icon"></i><span class="kt-saas-feature-text"><?php echo _l('kt_saas_storage_capacity'); ?>: <strong><?php echo (int) ($plan['limit_storage_mb'] ?? 0) === 0 ? _l('kt_saas_unlimited') : number_format((float) ($plan['limit_storage_mb'] ?? 0) / 1024, 1) . ' GB'; ?></strong></span></li>
                                                <li class="kt-saas-feature-item"><i class="fa fa-check-circle kt-saas-feature-icon"></i><span class="kt-saas-feature-text"><?php echo _l('kt_saas_invoice_quota'); ?>: <strong><?php echo (int) ($plan['limit_invoices'] ?? 0) === 0 ? _l('kt_saas_unlimited') : number_format((float) ($plan['limit_invoices'] ?? 0), 0) . ' hóa đơn'; ?></strong></span></li>
                                                <li class="kt-saas-feature-item"><i class="fa fa-check-circle kt-saas-feature-icon"></i><span class="kt-saas-feature-text"><?php echo _l('kt_saas_project_quota'); ?>: <strong><?php echo (int) ($plan['limit_projects'] ?? 0) === 0 ? _l('kt_saas_unlimited') : number_format((float) ($plan['limit_projects'] ?? 0), 0) . ' dự án'; ?></strong></span></li>
                                                <li class="kt-saas-feature-item"><i class="fa fa-check-circle kt-saas-feature-icon"></i><span class="kt-saas-feature-text"><?php echo _l('kt_saas_warehouse_quota'); ?>: <strong><?php echo (int) ($plan['limit_warehouses'] ?? 0) === 0 ? _l('kt_saas_unlimited') : number_format((float) ($plan['limit_warehouses'] ?? 0), 0) . ' kho'; ?></strong></span></li>
                                                <li class="kt-saas-feature-item"><i class="fa fa-check-circle kt-saas-feature-icon"></i><span class="kt-saas-feature-text"><?php echo _l('kt_saas_api_quota'); ?>: <strong><?php echo (int) ($plan['limit_api_requests_daily'] ?? 0) === 0 ? _l('kt_saas_unlimited') : number_format((float) ($plan['limit_api_requests_daily'] ?? 0), 0) . ' lượt/ngày'; ?></strong></span></li>
                                                <li class="kt-saas-feature-item"><i class="fa fa-check-circle kt-saas-feature-icon"></i><span class="kt-saas-feature-text"><?php echo _l('kt_saas_automation_quota'); ?>: <strong><?php echo (int) ($plan['limit_automations'] ?? 0) === 0 ? _l('kt_saas_unlimited') : number_format((float) ($plan['limit_automations'] ?? 0), 0) . ' quy trình'; ?></strong></span></li>
                                                <?php if ((int) ($plan['trial_days'] ?? 0) > 0) { ?>
                                                    <li class="kt-saas-feature-item"><i class="fa fa-check-circle kt-saas-feature-icon"></i><span class="kt-saas-feature-text"><?php echo _l('kt_saas_trial_label'); ?>: <strong><?php echo (int) $plan['trial_days']; ?> ngày</strong></span></li>
                                                <?php } ?>
                                                <?php if (!empty($plan['notes'])) { ?>
                                                    <li class="kt-saas-feature-item"><i class="fa fa-info-circle kt-saas-feature-icon text-info" style="color: #3b82f6;"></i><span class="kt-saas-feature-text"><?php echo html_escape($plan['notes']); ?></span></li>
                                                <?php } ?>
                                            </ul>

                                            <div class="kt-saas-modules-section">
                                                <h4 class="kt-saas-modules-title">Ứng dụng đi kèm</h4>
                                                <?php if (!empty($allowedModules)) { ?>
                                                    <?php foreach ($allowedModules as $moduleCode) { ?>
                                                        <?php $moduleLabel = kt_saas_module_display_name($moduleCode); ?>
                                                        <?php $moduleLabelMap = ['kt_matbao_invoice' => 'Hóa đơn điện tử', 'kt_sepay' => 'Thanh toán & đối soát', 'kt_inventory' => 'Quản lý kho']; ?>
                                                        <span class="kt-saas-module-tag"><?php echo html_escape($moduleLabelMap[strtolower((string) $moduleCode)] ?? $moduleLabel); ?></span>
                                                    <?php } ?>
                                                <?php } else { ?>
                                                    <span class="text-muted" style="font-size: 11px; font-style: italic;"><?php echo _l('kt_saas_no_modules'); ?></span>
                                                <?php } ?>
                                            </div>
                                        </div>

                                        <div class="kt-saas-action-btn-wrapper">
                                            <?php if ($isCurrentPlan) { ?>
                                                <button class="btn btn-success" disabled><?php echo _l('kt_saas_current_plan'); ?></button>
                                            <?php } elseif ($scheduledRequest) { ?>
                                                <button class="btn btn-warning" disabled><?php echo _l('kt_saas_plan_downgrade_scheduled'); ?></button>
                                            <?php } elseif (!empty($openRequest)) { ?>
                                                <button class="btn btn-warning" disabled style="white-space: normal;"><?php echo _l('kt_saas_plan_change_pending'); ?> (<?php echo html_escape($openRequest['invoice_number'] ?? ''); ?>)</button>
                                            <?php } else { ?>
                                                <form action="<?php echo admin_url('kt_saas/tenant_request_plan_change/' . (int) $plan['id']); ?>" method="post">
                                                    <input type="hidden" name="<?php echo html_escape($CI->security->get_csrf_token_name()); ?>" value="<?php echo html_escape($CI->security->get_csrf_hash()); ?>">
                                                    <button type="submit" class="btn btn-default btn-block"><?php echo _l('kt_saas_request_plan_change'); ?></button>
                                                </form>
                                            <?php } ?>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } else { ?>
                            <p class="text-center text-muted tw-py-8"><?php echo _l('kt_saas_no_records'); ?></p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
