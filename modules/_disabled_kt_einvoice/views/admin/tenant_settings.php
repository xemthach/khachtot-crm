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
                            <i class="fa fa-building tw-text-blue-600 tw-mr-2"></i>
                            eInvoice Settings - Tenant #<?php echo $tenant_id; ?>
                        </h4>
                        <p class="tw-text-gray-500 tw-mt-1">Giám sát cấu hình, hạn mức và trạng thái kết nối eInvoice của tenant</p>
                    </div>
                    <div class="tw-flex tw-gap-2">
                        <a href="<?php echo admin_url('kt_einvoice/admin/overview'); ?>" class="btn btn-default btn-sm">← Tổng quan</a>
                    </div>
                </div>

                <div class="row">
                    <!-- Cột Trái: Trạng thái & Quota -->
                    <div class="col-md-4">
                        <!-- Quota & Trạng thái hoạt động -->
                        <div class="panel_s">
                            <div class="panel-heading">
                                <h4 class="panel-title"><i class="fa fa-tachometer text-primary"></i> Quota & Hoạt động</h4>
                            </div>
                            <div class="panel-body">
                                <div class="tw-mb-4 tw-flex tw-justify-between tw-items-center">
                                    <span class="text-muted">Hạn mức tháng này:</span>
                                    <?php if (!empty($quota['unlimited'])): ?>
                                        <span class="label label-success">Không giới hạn</span>
                                    <?php else: ?>
                                        <strong class="tw-text-lg"><?php echo (int)($quota['used'] ?? 0); ?> / <?php echo (int)($quota['plan_quota'] ?? 0); ?></strong>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (empty($quota['unlimited']) && ($quota['plan_quota'] ?? 0) > 0): ?>
                                    <?php $pct = round(($quota['used'] / $quota['plan_quota']) * 100); ?>
                                    <div class="progress progress-sm tw-mb-4">
                                        <div class="progress-bar <?php echo $pct >= 90 ? 'progress-bar-danger' : ($pct >= 70 ? 'progress-bar-warning' : 'progress-bar-success'); ?>"
                                             style="width: <?php echo $pct; ?>%"></div>
                                    </div>
                                <?php endif; ?>

                                <table class="table table-condensed table-bordered no-margin tw-mb-4 tw-text-sm">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted">Đã phát hành (Tháng này)</td>
                                            <td><span class="label label-success"><?php echo (int)($quota['used'] ?? 0); ?> HĐ</span></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Hạn mức gói SaaS</td>
                                            <td><?php echo !empty($quota['unlimited']) ? 'Vô hạn' : ($quota['plan_quota'] ?? 0) . ' HĐ'; ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Hạn mức SePay API</td>
                                            <td><?php echo isset($quota['sepay_quota']) ? $quota['sepay_quota'] . ' HĐ' : 'Chưa đồng bộ'; ?></td>
                                        </tr>
                                    </tbody>
                                </table>

                                <!-- Quick Tenant Admin Actions -->
                                <div class="tw-flex tw-flex-col tw-gap-2">
                                    <button type="button" id="btn-reset-quota" class="btn btn-warning btn-block">
                                        <i class="fa fa-refresh"></i> Reset Quota Tháng Này
                                    </button>
                                    
                                    <?php if (!empty($settings['is_active']) || !empty($sandbox['is_active'])): ?>
                                        <button type="button" id="btn-deactivate" class="btn btn-danger btn-block">
                                            <i class="fa fa-ban"></i> Ngưng Kích Hoạt eInvoice
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Cần hỗ trợ? -->
                        <div class="panel_s">
                            <div class="panel-body">
                                <h5 class="tw-font-semibold tw-mb-2">Hỗ trợ xử lý sự cố</h5>
                                <p class="tw-text-xs text-muted tw-mb-3">
                                    Khi tenant gặp sự cố về quota hay kết nối API, bạn có thể reset hạn mức tháng hiện hành hoặc ngưng kích hoạt để tenant cập nhật lại thông tin kết nối mới.
                                </p>
                                <a href="<?php echo admin_url('kt_einvoice/admin/api_logs?tenant_id=' . $tenant_id); ?>" class="btn btn-default btn-xs btn-block">
                                    <i class="fa fa-code"></i> Xem API Logs của Tenant này
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Cột Phải: Cấu hình Production & Sandbox -->
                    <div class="col-md-8">
                        <div class="nav-tabs-custom">
                            <ul class="nav nav-tabs">
                                <li class="active"><a href="#tab_production" data-toggle="tab"><i class="fa fa-globe tw-text-green-500"></i> Production Settings</a></li>
                                <li><a href="#tab_sandbox" data-toggle="tab"><i class="fa fa-flask tw-text-yellow-500"></i> Sandbox Settings</a></li>
                            </ul>
                            
                            <div class="tab-content">
                                <!-- TAB Production -->
                                <div class="tab-pane active" id="tab_production">
                                    <?php if (empty($settings['created_at'])): ?>
                                        <div class="tw-text-center tw-py-8 text-muted">
                                            <i class="fa fa-exclamation-circle fa-2x tw-mb-2"></i>
                                            <p>Tenant chưa cấu hình môi trường Production</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="tw-flex tw-justify-between tw-items-center tw-mb-4">
                                            <h5 class="tw-font-bold tw-text-gray-800">Thông tin cấu hình SePay Production</h5>
                                            <span class="label label-<?php echo !empty($settings['is_active']) ? 'success' : 'default'; ?>">
                                                <?php echo !empty($settings['is_active']) ? 'Đang hoạt động' : 'Tắt'; ?>
                                            </span>
                                        </div>
                                        
                                        <table class="table table-hover table-bordered table-condensed tw-text-sm">
                                            <tbody>
                                                <tr>
                                                    <td class="tw-w-1/3 text-muted">Tài khoản API SePay</td>
                                                    <td><code><?php echo htmlspecialchars($settings['api_username'] ?? ''); ?></code></td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Nhà cung cấp hóa đơn</td>
                                                    <td><strong><?php echo htmlspecialchars($settings['provider_account_name'] ?? ''); ?></strong> (ID: <code><?php echo htmlspecialchars($settings['provider_account_id'] ?? ''); ?></code>)</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Ký hiệu / Mẫu HĐ</td>
                                                    <td>Ký hiệu: <code><?php echo htmlspecialchars($settings['invoice_series'] ?? ''); ?></code> | Mẫu: <code><?php echo htmlspecialchars($settings['invoice_template_code'] ?? ''); ?></code></td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Tự động phát hành khi thanh toán</td>
                                                    <td><?php echo !empty($settings['auto_issue_on_payment']) ? '<span class="text-success"><i class="fa fa-check"></i> Có</span>' : 'Không'; ?></td>
                                                </tr>
                                                <tr class="tw-bg-gray-50">
                                                    <td colspan="2" class="tw-font-bold">Thông tin người bán (doanh nghiệp tenant)</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Tên doanh nghiệp</td>
                                                    <td><?php echo htmlspecialchars($settings['seller_name'] ?? ''); ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Mã số thuế</td>
                                                    <td><code><?php echo htmlspecialchars($settings['seller_tax_code'] ?? ''); ?></code></td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Địa chỉ</td>
                                                    <td><?php echo htmlspecialchars($settings['seller_address'] ?? ''); ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Liên hệ</td>
                                                    <td>SĐT: <?php echo htmlspecialchars($settings['seller_phone'] ?? '—'); ?> | Email: <?php echo htmlspecialchars($settings['seller_email'] ?? '—'); ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Tài khoản ngân hàng</td>
                                                    <td><?php echo htmlspecialchars($settings['seller_bank_account'] ?? '—'); ?> tại <?php echo htmlspecialchars($settings['seller_bank_name'] ?? '—'); ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Ngày tạo / Ngày cập nhật</td>
                                                    <td>Tạo: <?php echo $settings['created_at']; ?> | Sửa: <?php echo $settings['updated_at']; ?></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>

                                <!-- TAB Sandbox -->
                                <div class="tab-pane" id="tab_sandbox">
                                    <?php if (empty($sandbox['created_at'])): ?>
                                        <div class="tw-text-center tw-py-8 text-muted">
                                            <i class="fa fa-exclamation-circle fa-2x tw-mb-2"></i>
                                            <p>Tenant chưa cấu hình môi trường Sandbox</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="tw-flex tw-justify-between tw-items-center tw-mb-4">
                                            <h5 class="tw-font-bold tw-text-gray-800">Thông tin cấu hình SePay Sandbox</h5>
                                            <span class="label label-<?php echo !empty($sandbox['is_active']) ? 'success' : 'default'; ?>">
                                                <?php echo !empty($sandbox['is_active']) ? 'Đang hoạt động' : 'Tắt'; ?>
                                            </span>
                                        </div>
                                        
                                        <table class="table table-hover table-bordered table-condensed tw-text-sm">
                                            <tbody>
                                                <tr>
                                                    <td class="tw-w-1/3 text-muted">Tài khoản API SePay (Sandbox)</td>
                                                    <td><code><?php echo htmlspecialchars($sandbox['api_username'] ?? ''); ?></code></td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Nhà cung cấp hóa đơn (Sandbox)</td>
                                                    <td><strong><?php echo htmlspecialchars($sandbox['provider_account_name'] ?? ''); ?></strong> (ID: <code><?php echo htmlspecialchars($sandbox['provider_account_id'] ?? ''); ?></code>)</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Ký hiệu / Mẫu HĐ</td>
                                                    <td>Ký hiệu: <code><?php echo htmlspecialchars($sandbox['invoice_series'] ?? ''); ?></code> | Mẫu: <code><?php echo htmlspecialchars($sandbox['invoice_template_code'] ?? ''); ?></code></td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Tự động phát hành khi thanh toán</td>
                                                    <td><?php echo !empty($sandbox['auto_issue_on_payment']) ? '<span class="text-success"><i class="fa fa-check"></i> Có</span>' : 'Không'; ?></td>
                                                </tr>
                                                <tr class="tw-bg-gray-50">
                                                    <td colspan="2" class="tw-font-bold">Thông tin người bán Sandbox</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Tên doanh nghiệp</td>
                                                    <td><?php echo htmlspecialchars($sandbox['seller_name'] ?? ''); ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Mã số thuế</td>
                                                    <td><code><?php echo htmlspecialchars($sandbox['seller_tax_code'] ?? ''); ?></code></td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Địa chỉ</td>
                                                    <td><?php echo htmlspecialchars($sandbox['seller_address'] ?? ''); ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Ngày tạo / Ngày cập nhật</td>
                                                    <td>Tạo: <?php echo $sandbox['created_at']; ?> | Sửa: <?php echo $sandbox['updated_at']; ?></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    // Reset quota
    $('#btn-reset-quota').on('click', function() {
        if (!confirm('Bạn có chắc chắn muốn RESET quota tháng này của tenant này về 0?')) return;
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Đang reset...');
        $.post('<?php echo admin_url('kt_einvoice/admin/reset_tenant_quota/' . $tenant_id); ?>', {
            environment: 'production'
        }, function(resp) {
            if (resp.success) {
                toastr.success(resp.message);
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                toastr.error(resp.message || 'Lỗi không xác định');
                $btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> Reset Quota Tháng Này');
            }
        }, 'json');
    });

    // Deactivate
    $('#btn-deactivate').on('click', function() {
        if (!confirm('Bạn có chắc chắn muốn NGƯNG KÍCH HOẠT cấu hình eInvoice của tenant này? (Tenant sẽ không thể phát hành hóa đơn cho đến khi kích hoạt lại)')) return;
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Đang tắt...');
        $.post('<?php echo admin_url('kt_einvoice/admin/deactivate_tenant/' . $tenant_id); ?>', {}, function(resp) {
            if (resp.success) {
                toastr.success('Đã ngưng kích hoạt cấu hình eInvoice');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                toastr.error('Có lỗi xảy ra');
                $btn.prop('disabled', false).html('<i class="fa fa-ban"></i> Ngưng Kích Hoạt eInvoice');
            }
        }, 'json');
    });
});
</script>

<?php init_tail(); ?>
