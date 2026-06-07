<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$healthEndpoints = $health_endpoints ?? [
    'test_connection'      => admin_url('kt_sepay/test_connection'),
    'test_bank_account'    => admin_url('kt_sepay/test_bank_account'),
    'test_qr'              => admin_url('kt_sepay/test_qr'),
    'test_webhook_url'     => admin_url('kt_sepay/test_webhook_url'),
    'test_webhook_payload' => admin_url('kt_sepay/test_webhook_payload'),
    'test_reconciliation'  => admin_url('kt_sepay/test_reconciliation'),
];
$canEditSettings = array_key_exists('can_edit_settings', get_defined_vars()) ? !empty($can_edit_settings) : true;
$canRunHealthChecks = array_key_exists('can_run_health_checks', get_defined_vars()) ? !empty($can_run_health_checks) : true;
$canRunReconcile = array_key_exists('can_run_reconcile', get_defined_vars()) ? !empty($can_run_reconcile) : true;
$canCreateManualRequests = array_key_exists('can_create_manual_requests', get_defined_vars()) ? !empty($can_create_manual_requests) : true;
$lockAttrs = $canEditSettings ? [] : ['disabled' => 'disabled'];
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo html_escape($title ?? _l('kt_sepay_settings')); ?></h4>
                        <hr class="hr-panel-heading" />
                        <?php if (!$canEditSettings) { ?>
                            <div class="alert alert-warning"><?php echo html_escape($readonly_notice ?? 'Bạn chỉ có quyền xem. Để thay đổi cấu hình KT SePay, cần quyền quản lý cấu hình.'); ?></div>
                        <?php } ?>
                        <?php if (!$canRunHealthChecks) { ?>
                            <div class="alert alert-info">Gói hiện tại chưa hỗ trợ kiểm tra kết nối tự động.</div>
                        <?php } ?>
                        <?php if (!$canRunReconcile) { ?>
                            <div class="alert alert-info">Gói hiện tại chưa hỗ trợ đối soát thủ công.</div>
                        <?php } ?>
                        <?php if (!empty($summary) && is_array($summary)) { ?>
                            <div class="row mtop15">
                                <div class="col-md-3"><div class="well well-sm">Chờ thanh toán: <strong><?php echo (int) ($summary['pending_requests'] ?? 0); ?></strong></div></div>
                                <div class="col-md-3"><div class="well well-sm">Đã thanh toán: <strong><?php echo (int) ($summary['paid_requests'] ?? 0); ?></strong></div></div>
                                <div class="col-md-3"><div class="well well-sm">Chưa đối soát: <strong><?php echo (int) ($summary['unmatched_txs'] ?? 0); ?></strong></div></div>
                                <div class="col-md-3"><div class="well well-sm">Cần kiểm tra: <strong><?php echo (int) ($summary['error_txs'] ?? 0); ?></strong></div></div>
                            </div>
                        <?php } ?>

                        <?php if (!empty($setup_checklist) && is_array($setup_checklist)) { ?>
                            <div class="row mtop15">
                                <div class="col-md-12">
                                    <h5>Kiểm tra cấu hình thanh toán</h5>
                                </div>
                                <?php foreach ($setup_checklist as $item) { ?>
                                    <div class="col-md-4">
                                        <div class="well well-sm">
                                            <p class="no-margin">
                                                <span class="label label-<?php echo !empty($item['done']) ? 'success' : 'default'; ?>">
                                                    <?php echo !empty($item['done']) ? _l('kt_sepay_yes') : _l('kt_sepay_no'); ?>
                                                </span>
                                                <strong><?php echo html_escape($item['label']); ?></strong>
                                            </p>
                                            <p class="text-muted mtop10 no-margin"><?php echo html_escape($item['help']); ?></p>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } ?>

                        <div class="checkbox checkbox-primary mtop15">
                            <input type="checkbox" id="kt-sepay-advanced-mode">
                            <label for="kt-sepay-advanced-mode">Chế độ nâng cao</label>
                        </div>
                        <p class="text-muted">Chỉ bật mục này khi đội kỹ thuật yêu cầu kiểm tra kết nối hoặc thay đổi thông tin tích hợp.</p>

                        <?php echo form_open($form_action ?? admin_url('kt_sepay/settings')); ?>
                        <div class="row">
                            <div class="col-md-4">
                                <?php echo render_select('environment', [['id' => 'sandbox', 'name' => 'Kiểm thử'], ['id' => 'production', 'name' => 'Đang sử dụng thật']], ['id', 'name'], 'Môi trường thanh toán', $settings['environment'] ?? 'sandbox', $lockAttrs); ?>
                            </div>
                            <div class="col-md-4">
                                <?php echo render_input('bank_code', 'Ngân hàng nhận tiền', $settings['bank_code'] ?? '', 'text', $lockAttrs); ?>
                            </div>
                            <div class="col-md-4">
                                <?php echo render_input('account_number', 'Số tài khoản nhận tiền', $settings['account_number'] ?? '', 'text', $lockAttrs); ?>
                            </div>
                        </div>
                        <div class="row kt-sepay-advanced-fields hide">
                            <?php if (!empty($tenant_webhook_url)) { ?>
                                <div class="col-md-12">
                                    <?php echo render_input('tenant_webhook_url_readonly', 'Đường dẫn nhận thông báo thanh toán', $tenant_webhook_url, 'text', ['readonly' => true]); ?>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="row kt-sepay-advanced-fields hide">
                            <div class="col-md-4">
                                <?php echo render_input('account_name', 'Tên chủ tài khoản', $settings['account_name'] ?? '', 'text', $lockAttrs); ?>
                            </div>
                            <div class="col-md-4">
                                <?php echo render_input('api_token', 'Mã kết nối SePay', '', 'password', array_merge(['autocomplete' => 'off', 'placeholder' => _l('kt_sepay_leave_token_blank')], $lockAttrs)); ?>
                            </div>
                            <div class="col-md-4">
                                <?php echo render_input('webhook_secret', 'Khóa xác thực thông báo', '', 'password', array_merge(['autocomplete' => 'off', 'placeholder' => _l('kt_sepay_leave_secret_blank')], $lockAttrs)); ?>
                            </div>
                        </div>
                        <div class="row kt-sepay-advanced-fields hide">
                            <div class="col-md-3">
                                <?php echo render_select('qr_template', [['id' => 'compact', 'name' => 'Gọn'], ['id' => 'qronly', 'name' => 'Chỉ mã QR'], ['id' => 'default', 'name' => 'Đầy đủ']], ['id', 'name'], 'Mẫu mã QR', $settings['qr_template'] ?? 'compact', $lockAttrs); ?>
                            </div>
                            <div class="col-md-3">
                                <?php echo render_input('reference_prefix_invoice', 'Tiền tố thanh toán hóa đơn', $settings['reference_prefix_invoice'] ?? 'KTINV', 'text', $lockAttrs); ?>
                            </div>
                            <div class="col-md-3">
                                <?php echo render_input('reference_prefix_subscription', 'Tiền tố thanh toán gói CRM', $settings['reference_prefix_subscription'] ?? 'KTSAAS', 'text', $lockAttrs); ?>
                            </div>
                            <div class="col-md-3">
                                <?php echo render_input('reference_prefix_manual', 'Tiền tố thanh toán thủ công', $settings['reference_prefix_manual'] ?? 'KTPAY', 'text', $lockAttrs); ?>
                            </div>
                        </div>
                        <div class="row kt-sepay-advanced-fields hide">
                            <div class="col-md-4">
                                <?php echo render_input('reconcile_interval_minutes', 'Chu kỳ đối soát tự động', $settings['reconcile_interval_minutes'] ?? 15, 'number', array_merge(['min' => 1], $lockAttrs)); ?>
                            </div>
                            <div class="col-md-4">
                                <?php echo render_input('payment_request_expiry_minutes', 'Thời hạn thanh toán', $settings['payment_request_expiry_minutes'] ?? 60, 'number', array_merge(['min' => 5], $lockAttrs)); ?>
                            </div>
                        </div>
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo !empty($settings['is_active']) ? 'checked' : ''; ?> <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                            <label for="is_active">Bật thanh toán SePay</label>
                        </div>
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" id="auto_reconcile_enabled" name="auto_reconcile_enabled" value="1" <?php echo !empty($settings['auto_reconcile_enabled']) ? 'checked' : ''; ?> <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                            <label for="auto_reconcile_enabled">Tự động đối soát thanh toán</label>
                        </div>
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" id="allow_partial_payment" name="allow_partial_payment" value="1" <?php echo !empty($settings['allow_partial_payment']) ? 'checked' : ''; ?> <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                            <label for="allow_partial_payment">Cho phép thanh toán một phần</label>
                        </div>
                        <?php if ($canEditSettings) { ?>
                            <div class="btn-bottom-toolbar text-right">
                                <button type="submit" class="btn btn-primary">Lưu cấu hình</button>
                            </div>
                        <?php } ?>
                        <?php echo form_close(); ?>

                        <?php if (!empty($health_checks_enabled)) { ?>
                            <hr />
                            <div class="kt-sepay-advanced-fields hide">
                            <h5>Kiểm tra kết nối</h5>
                            <div class="row mtop15">
                                <div class="col-md-12">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-default kt-sepay-health-btn" data-endpoint="<?php echo html_escape($healthEndpoints['test_connection']); ?>" data-test-label="<?php echo html_escape(_l('kt_sepay_test_api_connection')); ?>" <?php echo !$canRunHealthChecks ? 'disabled' : ''; ?>><?php echo _l('kt_sepay_test_api_connection'); ?></button>
                                        <button type="button" class="btn btn-default kt-sepay-health-btn" data-endpoint="<?php echo html_escape($healthEndpoints['test_bank_account']); ?>" data-test-label="<?php echo html_escape(_l('kt_sepay_test_bank_account')); ?>" <?php echo !$canRunHealthChecks ? 'disabled' : ''; ?>><?php echo _l('kt_sepay_test_bank_account'); ?></button>
                                        <button type="button" class="btn btn-default kt-sepay-health-btn" data-endpoint="<?php echo html_escape($healthEndpoints['test_qr']); ?>" data-test-label="<?php echo html_escape(_l('kt_sepay_test_qr_generation')); ?>" <?php echo !$canRunHealthChecks ? 'disabled' : ''; ?>><?php echo _l('kt_sepay_test_qr_generation'); ?></button>
                                        <button type="button" class="btn btn-default kt-sepay-health-btn" data-endpoint="<?php echo html_escape($healthEndpoints['test_webhook_url']); ?>" data-test-label="<?php echo html_escape(_l('kt_sepay_test_webhook_url')); ?>" <?php echo !$canRunHealthChecks ? 'disabled' : ''; ?>><?php echo _l('kt_sepay_test_webhook_url'); ?></button>
                                        <button type="button" class="btn btn-default kt-sepay-health-btn" data-endpoint="<?php echo html_escape($healthEndpoints['test_webhook_payload']); ?>" data-test-label="<?php echo html_escape(_l('kt_sepay_test_webhook_payload')); ?>" <?php echo !$canRunHealthChecks ? 'disabled' : ''; ?>><?php echo _l('kt_sepay_test_webhook_payload'); ?></button>
                                        <button type="button" class="btn btn-default kt-sepay-health-btn" data-endpoint="<?php echo html_escape($healthEndpoints['test_reconciliation']); ?>" data-test-label="<?php echo html_escape(_l('kt_sepay_test_reconciliation_api')); ?>" <?php echo !$canRunHealthChecks ? 'disabled' : ''; ?>><?php echo _l('kt_sepay_test_reconciliation_api'); ?></button>
                                    </div>
                                </div>
                            </div>
                            <div class="row mtop15">
                                <div class="col-md-12">
                                    <div id="kt-sepay-health-result" class="alert alert-info hide"></div>
                                    <div id="kt-sepay-health-qr-preview" class="hide mtop15">
                                        <p><strong><?php echo _l('kt_sepay_qr_preview'); ?></strong></p>
                                        <p><a href="#" target="_blank" rel="noopener" id="kt-sepay-health-qr-link"></a></p>
                                        <img src="" alt="SePay QR Preview" id="kt-sepay-health-qr-image" style="max-width:280px;height:auto;" />
                                    </div>
                                    <div class="table-responsive mtop15">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th><?php echo _l('kt_sepay_test'); ?></th>
                                                    <th><?php echo _l('kt_sepay_status'); ?></th>
                                                    <th><?php echo _l('kt_sepay_message'); ?></th>
                                                    <th><?php echo _l('kt_sepay_latency'); ?></th>
                                                    <th><?php echo _l('kt_sepay_detail'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody id="kt-sepay-health-live-table">
                                                <tr>
                                                    <td colspan="5" class="text-muted"><?php echo _l('kt_sepay_no_health_result'); ?></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($health_logs) && is_array($health_logs)) { ?>
                                <div class="row mtop15">
                                    <div class="col-md-12">
                                        <h5><?php echo _l('kt_sepay_health_history'); ?></h5>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th><?php echo _l('kt_sepay_test'); ?></th>
                                                        <th><?php echo _l('kt_sepay_status'); ?></th>
                                                        <th><?php echo _l('kt_sepay_http_code'); ?></th>
                                                        <th><?php echo _l('kt_sepay_latency'); ?></th>
                                                        <th><?php echo _l('kt_sepay_message'); ?></th>
                                                        <th><?php echo _l('kt_sepay_created_at'); ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php foreach ($health_logs as $log) { ?>
                                                    <tr>
                                                        <td><?php echo (int) $log['id']; ?></td>
                                                        <td><?php echo html_escape($log['test_type']); ?></td>
                                                        <td><span class="label label-<?php echo kt_sepay_health_status_badge_class($log['status']); ?>"><?php echo html_escape(kt_sepay_status_label($log['status'])); ?></span></td>
                                                        <td><?php echo (int) ($log['http_code'] ?? 0); ?></td>
                                                        <td><?php echo (int) ($log['latency_ms'] ?? 0); ?> ms</td>
                                                        <td><?php echo html_escape($log['message']); ?></td>
                                                        <td><?php echo html_escape($log['created_at']); ?></td>
                                                    </tr>
                                                <?php } ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                            </div>
                        <?php } ?>

                        <?php if (!empty($api_accounts) && is_array($api_accounts)) { ?>
                            <hr />
                            <div class="kt-sepay-advanced-fields hide">
                            <h5>Tài khoản nhận tiền từ SePay</h5>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead><tr><th>ID</th><th><?php echo _l('kt_sepay_bank'); ?></th><th><?php echo _l('kt_sepay_account'); ?></th><th><?php echo _l('kt_sepay_holder'); ?></th><th><?php echo _l('kt_sepay_active'); ?></th></tr></thead>
                                    <tbody>
                                    <?php foreach ($api_accounts as $account) { ?>
                                        <tr>
                                            <td><?php echo html_escape($account['id'] ?? ''); ?></td>
                                            <td><?php echo html_escape($account['bank_short_name'] ?? ''); ?></td>
                                            <td><?php echo html_escape($account['account_number'] ?? ''); ?></td>
                                            <td><?php echo html_escape($account['account_holder_name'] ?? ''); ?></td>
                                            <td><?php echo !empty($account['active']) ? _l('kt_sepay_yes') : _l('kt_sepay_no'); ?></td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
(function () {
    var advancedToggle = document.getElementById('kt-sepay-advanced-mode');
    if (!advancedToggle) {
        return;
    }
    var advancedFields = document.querySelectorAll('.kt-sepay-advanced-fields');
    function refreshAdvancedMode() {
        for (var i = 0; i < advancedFields.length; i++) {
            advancedFields[i].classList.toggle('hide', !advancedToggle.checked);
        }
    }
    advancedToggle.addEventListener('change', refreshAdvancedMode);
    refreshAdvancedMode();
})();
</script>
