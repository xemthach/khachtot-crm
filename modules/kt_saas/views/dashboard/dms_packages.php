<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$formatVnd = static function ($amount): string {
    $amount = (float) $amount;

    return $amount > 0 ? number_format($amount, 0, ',', '.') . ' VND' : 'Custom';
};
$formatLimit = static function ($value): string {
    $value = (int) $value;

    return $value > 0 ? number_format($value, 0, ',', '.') : 'Custom';
};
?>
<?php init_head(); ?>
<style>
    .dms-package-page .panel_s { border: 1px solid #e5e7eb; box-shadow: 0 1px 2px rgba(15, 23, 42, .05); }
    .dms-package-page .dms-package-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; margin-bottom: 22px; }
    .dms-package-page .dms-package-header h3 { margin: 0 0 6px; color: #172b4d; font-weight: 600; }
    .dms-package-page .dms-package-header p { margin: 0; color: #64748b; }
    .dms-package-page .dms-form-section { border-top: 1px solid #e5e7eb; padding: 22px 0 6px; }
    .dms-package-page .dms-form-section:first-child { border-top: 0; padding-top: 0; }
    .dms-package-page .dms-form-section h5 { margin: 0 0 4px; color: #172b4d; font-size: 15px; font-weight: 600; }
    .dms-package-page .dms-form-section > p { color: #64748b; margin-bottom: 16px; }
    .dms-package-page .dms-price-preview { margin-top: -10px; margin-bottom: 14px; color: #2563eb; font-weight: 600; }
    .dms-package-page .dms-package-code { color: #64748b; font-family: monospace; }
    .dms-package-page .dms-price-main { color: #172b4d; font-weight: 600; }
    .dms-package-page .dms-quota-list { margin: 0; padding: 0; list-style: none; color: #475569; line-height: 1.8; }
    .dms-package-page .dms-quota-list strong { color: #172b4d; }
    .dms-package-page .dms-usage-note { display: block; margin-top: 5px; color: #64748b; }
    .dms-package-page .table > tbody > tr > td { vertical-align: middle; }
    @media (max-width: 767px) {
        .dms-package-page .dms-package-header { flex-direction: column; }
        .dms-package-page .dms-package-header .btn { width: 100%; }
    }
</style>
<div id="wrapper">
    <div class="content dms-package-page">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="dms-package-header">
                            <div>
                                <h3>Quản lý gói DMS</h3>
                                <p>Danh mục thương mại chỉ dành cho Landlord: giá, chu kỳ thanh toán, tính năng, hạn mức và chính sách đổi gói.</p>
                            </div>
                            <a href="<?php echo admin_url('kt_saas/dms_packages?create=1'); ?>" class="btn btn-primary">
                                <?php echo html_escape(_l('kt_saas_dms_package_add')); ?>
                            </a>
                        </div>

                        <?php if (!empty($show_form)) { ?>
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <strong><?php echo !empty($edit_package) ? 'Chỉnh sửa gói DMS' : 'Tạo gói DMS'; ?></strong>
                                </div>
                                <div class="panel-body">
                                    <?php echo form_open(admin_url('kt_saas/dms_packages' . (!empty($edit_package['package_code']) ? '/' . rawurlencode((string) $edit_package['package_code']) : ''))); ?>

                                    <section class="dms-form-section">
                                        <h5>Thông tin gói</h5>
                                        <p>Tên và mã gói được dùng thống nhất cho Marketplace, thuê bao DMS và cấu hình runtime.</p>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <?php echo render_input('package_code', 'kt_saas_dms_package_code', $edit_package['package_code'] ?? '', 'text', ['required' => true]); ?>
                                            </div>
                                            <div class="col-md-6">
                                                <?php echo render_input('name', 'kt_saas_dms_package_name', $edit_package['name'] ?? '', 'text', ['required' => true]); ?>
                                            </div>
                                        </div>
                                    </section>

                                    <section class="dms-form-section">
                                        <h5>Giá & chu kỳ thanh toán</h5>
                                        <p>Chọn các chu kỳ tenant được phép dùng và một chu kỳ mặc định. Giá 0 hiển thị là Liên hệ.</p>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <?php echo render_input('monthly_price', 'kt_saas_dms_package_monthly_price', $edit_package['monthly_price'] ?? '0', 'number', ['step' => '1', 'min' => '0', 'data-money-input' => 'monthly']); ?>
                                                <div class="dms-price-preview" data-money-preview="monthly">
                                                    <?php echo html_escape($formatVnd($edit_package['monthly_price'] ?? 0)); ?> / thang
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <?php echo render_input('quarterly_price', 'Giá theo quý', $edit_package['quarterly_price'] ?? '0', 'number', ['step' => '1', 'min' => '0', 'data-money-input' => 'quarterly']); ?>
                                                <div class="dms-price-preview" data-money-preview="quarterly"><?php echo html_escape($formatVnd($edit_package['quarterly_price'] ?? 0)); ?> / quý</div>
                                            </div>
                                            <div class="col-md-4">
                                                <?php echo render_input('yearly_price', 'kt_saas_dms_package_yearly_price', $edit_package['yearly_price'] ?? '0', 'number', ['step' => '1', 'min' => '0', 'data-money-input' => 'yearly']); ?>
                                                <div class="dms-price-preview" data-money-preview="yearly">
                                                    <?php echo html_escape($formatVnd($edit_package['yearly_price'] ?? 0)); ?> / nam
                                                </div>
                                            </div>
                                        </div>
                                        <?php $allowedPeriods = (array) ($edit_package['allowed_billing_periods'] ?? ['monthly', 'yearly']); ?>
                                        <div class="row">
                                            <div class="col-md-8">
                                                <label>Chu kỳ được phép</label>
                                                <?php foreach (['monthly' => 'Tháng', 'quarterly' => 'Quý', 'yearly' => 'Năm'] as $period => $label) { ?>
                                                  <div class="checkbox checkbox-primary checkbox-inline"><input type="checkbox" id="period_<?php echo $period; ?>" name="allowed_billing_periods[]" value="<?php echo $period; ?>" <?php echo in_array($period, $allowedPeriods, true) ? 'checked' : ''; ?>><label for="period_<?php echo $period; ?>"><?php echo $label; ?></label></div>
                                                <?php } ?>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="default_billing_period">Chu kỳ mặc định</label>
                                                <select class="form-control" id="default_billing_period" name="default_billing_period">
                                                  <?php foreach (['monthly' => 'Tháng', 'quarterly' => 'Quý', 'yearly' => 'Năm'] as $period => $label) { ?><option value="<?php echo $period; ?>" <?php echo ($edit_package['default_billing_period'] ?? 'monthly') === $period ? 'selected' : ''; ?>><?php echo $label; ?></option><?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                    </section>

                                    <section class="dms-form-section">
                                        <h5>Hạn mức sử dụng</h5>
                                        <p>Hạn mức được truyền xuống DMS theo thuê bao đang hoạt động.</p>
                                        <div class="row">
                                            <div class="col-md-4"><?php echo render_input('user_limit', 'Nguoi dung DMS', $edit_package['user_limit'] ?? '0', 'number', ['min' => '0']); ?></div>
                                            <div class="col-md-4"><?php echo render_input('store_limit', 'Diem ban', $edit_package['store_limit'] ?? '0', 'number', ['min' => '0']); ?></div>
                                            <div class="col-md-4"><?php echo render_input('product_limit', 'San pham', $edit_package['product_limit'] ?? '0', 'number', ['min' => '0']); ?></div>
                                            <div class="col-md-4"><?php echo render_input('checkin_monthly_limit', 'Check-in / thang', $edit_package['checkin_monthly_limit'] ?? '0', 'number', ['min' => '0']); ?></div>
                                            <div class="col-md-4"><?php echo render_input('photo_limit', 'Anh check-in', $edit_package['photo_limit'] ?? '0', 'number', ['min' => '0']); ?></div>
                                            <div class="col-md-4"><?php echo render_input('report_export_limit', 'Export bao cao', $edit_package['report_export_limit'] ?? '0', 'number', ['min' => '0']); ?></div>
                                        </div>
                                    </section>

                                    <section class="dms-form-section">
                                        <h5>Tính năng</h5>
                                        <p>Các tính năng runtime được cấp cho tenant sử dụng gói này.</p>
                                        <div class="checkbox checkbox-primary">
                                            <input type="checkbox" id="live_tracking_enabled" name="live_tracking_enabled" <?php echo !empty($edit_package['live_tracking_enabled']) ? 'checked' : ''; ?>>
                                            <label for="live_tracking_enabled">Live Tracking</label>
                                        </div>
                                    </section>

                                    <section class="dms-form-section">
                                        <h5>Vận hành & lưu trữ</h5>
                                        <p>Quy định thời gian lưu dữ liệu vận hành của gói DMS.</p>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <?php echo render_input('log_retention_months', 'Luu log (thang)', $edit_package['log_retention_months'] ?? '0', 'number', ['min' => '0']); ?>
                                            </div>
                                        </div>
                                    </section>

                                    <div class="tw-mt-4">
                                        <button type="submit" class="btn btn-primary"><?php echo html_escape(_l('kt_saas_save')); ?></button>
                                        <a href="<?php echo admin_url('kt_saas/dms_packages'); ?>" class="btn btn-default"><?php echo html_escape(_l('kt_saas_cancel')); ?></a>
                                    </div>
                                    <?php echo form_close(); ?>
                                </div>
                            </div>
                        <?php } ?>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Gói DMS</th>
                                        <th>Giá & chu kỳ</th>
                                        <th>Hạn mức chính</th>
                                        <th>Tính năng</th>
                                        <th>Tenant đang dùng</th>
                                        <th class="text-right"><?php echo html_escape(_l('kt_saas_actions')); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ((array) $packages as $packageCode => $package) { ?>
                                    <?php
                                    $usageCount = (int) ($package_usage_map[$packageCode] ?? 0);
                                    $isCustom = (float) ($package['monthly_price'] ?? 0) <= 0
                                        && (float) ($package['yearly_price'] ?? 0) <= 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo html_escape((string) ($package['name'] ?? $packageCode)); ?></strong>
                                            <span class="label label-info tw-ml-1"><?php echo $isCustom ? 'Custom' : 'Active'; ?></span><br>
                                            <span class="dms-package-code"><?php echo html_escape((string) ($package['package_code'] ?? $packageCode)); ?></span>
                                            <span class="dms-usage-note">Benefit: field sales operations, check-ins, visits, orders, reports and runtime quota based on this package.</span>
                                        </td>
                                        <td>
                                            <?php if ($isCustom) { ?>
                                                <span class="dms-price-main">Lien he / Custom</span>
                                                <span class="dms-usage-note">Gia duoc thoa thuan theo hop dong.</span>
                                            <?php } else { ?>
                                                <div class="dms-price-main"><?php echo html_escape($formatVnd($package['monthly_price'] ?? 0)); ?> / tháng</div>
                                                <span class="dms-usage-note">Cho phép: <?php echo html_escape(implode(', ', (array) ($package['allowed_billing_periods'] ?? []))); ?></span>
                                                <span class="dms-usage-note">Mặc định: <?php echo html_escape((string) ($package['default_billing_period'] ?? 'monthly')); ?></span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <ul class="dms-quota-list">
                                                <li><strong><?php echo html_escape($formatLimit($package['user_limit'] ?? 0)); ?></strong> nguoi dung</li>
                                                <li><strong><?php echo html_escape($formatLimit($package['store_limit'] ?? 0)); ?></strong> diem ban</li>
                                                <li><strong><?php echo html_escape($formatLimit($package['product_limit'] ?? 0)); ?></strong> san pham</li>
                                                <li><strong><?php echo html_escape($formatLimit($package['checkin_monthly_limit'] ?? 0)); ?></strong> check-in / thang</li>
                                            </ul>
                                        </td>
                                        <td>
                                            <span class="label label-<?php echo !empty($package['live_tracking_enabled']) ? 'success' : 'default'; ?>">
                                                Live Tracking: <?php echo !empty($package['live_tracking_enabled']) ? 'Bat' : 'Tat'; ?>
                                            </span>
                                            <span class="dms-usage-note">Log: <?php echo html_escape($formatLimit($package['log_retention_months'] ?? 0)); ?> thang</span>
                                            <span class="dms-usage-note">Upgrade path: move tenant to a higher DMS package when quota is near limit.</span>
                                        </td>
                                        <td>
                                            <strong><?php echo $usageCount; ?></strong>
                                            <span class="dms-usage-note"><?php echo $usageCount > 0 ? 'Khong the xoa khi dang duoc tham chieu.' : 'Chua co subscription su dung.'; ?></span>
                                        </td>
                                        <td class="text-right">
                                            <a href="<?php echo admin_url('kt_saas/dms_packages/' . rawurlencode((string) $packageCode)); ?>" class="btn btn-default btn-sm">
                                                <?php echo html_escape(_l('kt_saas_edit')); ?>
                                            </a>
                                            <?php if ($usageCount === 0) { ?>
                                                <?php echo form_open(admin_url('kt_saas/delete_dms_package/' . rawurlencode((string) $packageCode)), ['style' => 'display:inline-block;margin-left:4px;']); ?>
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Xoa goi DMS nay?');">Xoa</button>
                                                <?php echo form_close(); ?>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                                <?php if (empty($packages)) { ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted tw-py-6">
                                            Chua co goi DMS. Tao goi dau tien de Marketplace co the ban add-on.
                                        </td>
                                    </tr>
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
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-money-input]').forEach(function (input) {
        var key = input.getAttribute('data-money-input');
        var preview = document.querySelector('[data-money-preview="' + key + '"]');
        var suffix = key === 'yearly' ? ' / năm' : (key === 'quarterly' ? ' / quý' : ' / tháng');
        var render = function () {
            var value = Number(input.value || 0);
            preview.textContent = value > 0
                ? new Intl.NumberFormat('vi-VN').format(value) + ' VND' + suffix
                : 'Custom' + suffix;
        };
        input.addEventListener('input', render);
        render();
    });
});
</script>
<?php init_tail(); ?>
