<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">

                <div class="tw-flex tw-items-center tw-justify-between tw-mb-5">
                    <div>
                        <h4 class="tw-text-2xl tw-font-bold tw-text-gray-800">
                            <i class="fa fa-sliders tw-text-blue-600 tw-mr-2"></i>
                            Cấu Hình eInvoice Theo Gói Dịch Vụ
                        </h4>
                        <p class="text-muted">Bật/tắt tính năng hóa đơn điện tử và thiết lập hạn mức cho từng gói SaaS</p>
                    </div>
                </div>

                <!-- Alert hướng dẫn -->
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i>
                    <strong>Lưu ý:</strong> Cấu hình tại đây sẽ áp dụng cho tất cả tenant đang sử dụng gói tương ứng.
                    Thay đổi có hiệu lực ngay sau khi lưu.
                </div>

                <!-- Plans Grid -->
                <?php if (empty($plans)): ?>
                    <div class="alert alert-warning">Chưa có gói nào. Vào <a href="<?php echo admin_url('kt_saas/plans'); ?>">Quản lý gói</a> để tạo.</div>
                <?php else: ?>

                <div class="row" id="kt-plan-features-container">
                    <?php foreach ($plans as $plan):
                        // Parse features_raw
                        $currentFeatures = [];
                        if (!empty($plan['features_raw'])) {
                            foreach (explode(',', $plan['features_raw']) as $pair) {
                                [$k, $v] = explode('=', $pair, 2) + ['', ''];
                                $currentFeatures[trim($k)] = trim($v);
                            }
                        }
                        $isEnabled = !empty($currentFeatures[KT_EINVOICE_FEATURE_ENABLED]) && $currentFeatures[KT_EINVOICE_FEATURE_ENABLED] !== 'false';
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="panel_s kt-plan-card <?php echo $isEnabled ? 'tw-border-l-4 tw-border-l-blue-500' : ''; ?>">
                            <div class="panel-heading tw-flex tw-items-center tw-justify-between">
                                <div>
                                    <h5 class="panel-title tw-font-bold">
                                        <?php echo htmlspecialchars($plan['plan_name'] ?? $plan['plan_code'] ?? ('Plan #' . (int) $plan['id'])); ?>
                                        <?php if (!empty($plan['price'])): ?>
                                            <small class="text-muted">/ <?php echo number_format($plan['price'], 0, ',', '.'); ?>đ</small>
                                        <?php endif; ?>
                                    </h5>
                                </div>
                                <div>
                                    <span class="label <?php echo $isEnabled ? 'label-success' : 'label-default'; ?>">
                                        <?php echo $isEnabled ? 'eInvoice: BẬT' : 'eInvoice: TẮT'; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="panel-body">
                                <form class="kt-plan-feature-form" data-plan-id="<?php echo $plan['id']; ?>">

                                    <?php foreach ($feature_keys as $key => $meta): ?>
                                    <div class="form-group tw-mb-3">
                                        <label class="tw-flex tw-items-center tw-justify-between">
                                            <span class="tw-text-sm tw-font-medium">
                                                <?php echo $meta['label']; ?>
                                                <?php if (!empty($meta['description'])): ?>
                                                    <i class="fa fa-question-circle text-muted tw-ml-1"
                                                       title="<?php echo htmlspecialchars($meta['description']); ?>"
                                                       data-toggle="tooltip"></i>
                                                <?php endif; ?>
                                            </span>

                                            <?php
                                            $currentVal = $currentFeatures[$key] ?? $meta['default'];
                                            if ($meta['type'] === 'boolean'):
                                                $checked = !empty($currentVal) && $currentVal !== 'false' && $currentVal !== '0';
                                            ?>
                                                <div class="tw-flex tw-items-center">
                                                    <label class="switch-ios switch-sm">
                                                        <input type="checkbox" name="features[<?php echo $key; ?>]"
                                                               value="true" class="kt-feature-toggle"
                                                               <?php echo $checked ? 'checked' : ''; ?>
                                                               <?php echo $key === KT_EINVOICE_FEATURE_ENABLED ? 'data-master="true"' : ''; ?>>
                                                        <span></span>
                                                    </label>
                                                </div>
                                            <?php elseif ($meta['type'] === 'integer'): ?>
                                                <div style="width:90px;">
                                                    <input type="number" name="features[<?php echo $key; ?>]"
                                                           class="form-control form-control-sm input-sm kt-feature-number"
                                                           value="<?php echo (int)$currentVal; ?>"
                                                           min="0" step="1"
                                                           placeholder="<?php echo $meta['default']; ?>">
                                                </div>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>

                                    <div class="tw-mt-4">
                                        <button type="submit" class="btn btn-primary btn-block btn-sm kt-save-plan-btn">
                                            <i class="fa fa-save"></i> Lưu cấu hình gói này
                                        </button>
                                    </div>
                                    <div class="kt-save-result tw-mt-2" style="display:none;"></div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php endif; ?>

                <!-- Quick Reference -->
                <div class="panel_s tw-mt-4">
                    <div class="panel-heading">
                        <h4 class="panel-title"><i class="fa fa-book"></i> Giải thích các tính năng</h4>
                    </div>
                    <div class="panel-body">
                        <table class="table table-condensed">
                            <thead>
                                <tr><th>Feature Key</th><th>Mô tả</th><th>Giá trị</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($feature_keys as $key => $meta): ?>
                                <tr>
                                    <td><code><?php echo $key; ?></code></td>
                                    <td><?php echo $meta['label']; ?> — <span class="text-muted"><?php echo $meta['description']; ?></span></td>
                                    <td><span class="label label-default"><?php echo $meta['type']; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    $('[data-toggle="tooltip"]').tooltip();

    // Master toggle: khi tắt eInvoice thì disable tất cả field khác
    $(document).on('change', '[data-master="true"]', function() {
        var $form = $(this).closest('form');
        var enabled = $(this).is(':checked');
        $form.find('.kt-feature-toggle:not([data-master]), .kt-feature-number').prop('disabled', !enabled);
    });
    $('[data-master="true"]').trigger('change'); // Chạy ngay để set state đúng khi load

    // Save plan features
    $(document).on('submit', '.kt-plan-feature-form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var planId = $form.data('plan-id');
        var $btn = $form.find('.kt-save-plan-btn');
        var $result = $form.find('.kt-save-result');

        // Serialize features
        var features = {};
        $form.find('[name^="features["]').each(function() {
            var key = $(this).attr('name').match(/\[([^\]]+)\]/)[1];
            if ($(this).is(':checkbox')) {
                features[key] = $(this).is(':checked') ? 'true' : 'false';
            } else {
                features[key] = $(this).val();
            }
        });

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Đang lưu...');
        $result.hide();

        $.ajax({
            url: '<?php echo admin_url('kt_einvoice/admin/save_plan_features/'); ?>' + planId,
            method: 'POST',
            data: {features: features},
            dataType: 'json',
            success: function(resp) {
                $result.show().removeClass('text-danger').addClass('text-success')
                    .html('<i class="fa fa-check"></i> ' + (resp.message || 'Đã lưu!'));
                // Cập nhật badge
                var enabled = features['<?php echo KT_EINVOICE_FEATURE_ENABLED; ?>'] === 'true';
                $form.closest('.panel_s')
                    .find('.label')
                    .removeClass('label-success label-default')
                    .addClass(enabled ? 'label-success' : 'label-default')
                    .text(enabled ? 'eInvoice: BẬT' : 'eInvoice: TẮT');
            },
            error: function() {
                $result.show().removeClass('text-success').addClass('text-danger')
                    .html('<i class="fa fa-times"></i> Lỗi khi lưu!');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Lưu cấu hình gói này');
                setTimeout(function() { $result.fadeOut(); }, 3000);
            }
        });
    });
});
</script>
<?php init_tail(); ?>
