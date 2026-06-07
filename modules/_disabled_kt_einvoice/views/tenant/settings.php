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
                            <?php echo _l('kt_einvoice_settings_title'); ?>
                        </h4>
                        <p class="tw-text-gray-500 tw-mt-1">Kết nối và cấu hình tích hợp hóa đơn điện tử qua SePay</p>
                    </div>
                    <div>
                        <?php if ($environment === 'sandbox'): ?>
                            <span class="label label-warning"><i class="fa fa-flask"></i> Sandbox</span>
                        <?php else: ?>
                            <span class="label label-success"><i class="fa fa-check-circle"></i> Production</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php echo form_open(admin_url('kt_einvoice/settings'), ['id' => 'kt-einvoice-settings-form']); ?>

                <div class="row">
                    <!-- Cột trái: API + Nhà cung cấp -->
                    <div class="col-md-6">

                        <!-- Card: Kết nối SePay -->
                        <div class="panel_s">
                            <div class="panel-body">
                                <div class="tw-flex tw-items-center tw-mb-4">
                                    <div class="tw-w-10 tw-h-10 tw-bg-blue-100 tw-rounded-lg tw-flex tw-items-center tw-justify-center tw-mr-3">
                                        <i class="fa fa-plug tw-text-blue-600"></i>
                                    </div>
                                    <div>
                                        <h5 class="tw-font-semibold tw-text-gray-800 tw-mb-0">Kết Nối SePay API</h5>
                                        <small class="tw-text-gray-500">Thông tin đăng nhập tài khoản SePay</small>
                                    </div>
                                </div>
                                <hr class="tw-my-3">

                                <!-- Environment -->
                                <div class="form-group">
                                    <label class="control-label"><?php echo _l('kt_einvoice_environment'); ?></label>
                                    <div>
                                        <label class="radio-inline">
                                            <input type="radio" name="environment" value="sandbox"
                                                <?php echo ($environment === 'sandbox') ? 'checked' : ''; ?>>
                                            <i class="fa fa-flask tw-text-yellow-500"></i> <?php echo _l('kt_einvoice_environment_sandbox'); ?>
                                        </label>
                                        <label class="radio-inline tw-ml-4">
                                            <input type="radio" name="environment" value="production"
                                                <?php echo ($environment === 'production') ? 'checked' : ''; ?>>
                                            <i class="fa fa-globe tw-text-green-500"></i> <?php echo _l('kt_einvoice_environment_production'); ?>
                                        </label>
                                    </div>
                                </div>

                                <!-- Username -->
                                <div class="form-group">
                                    <label class="control-label"><?php echo _l('kt_einvoice_api_username'); ?> <span class="text-danger">*</span></label>
                                    <input type="text" name="api_username" class="form-control"
                                        value="<?php echo htmlspecialchars($settings['api_username'] ?? ''); ?>"
                                        placeholder="Tài khoản đăng nhập SePay eInvoice">
                                </div>

                                <!-- Password -->
                                <div class="form-group">
                                    <label class="control-label"><?php echo _l('kt_einvoice_api_password'); ?></label>
                                    <div class="input-group">
                                        <input type="password" name="api_password" id="kt-einvoice-password" class="form-control"
                                            placeholder="<?php echo !empty($settings['api_username']) ? '••••••••  (để trống nếu không đổi)' : 'Nhập mật khẩu SePay'; ?>">
                                        <span class="input-group-btn">
                                            <button type="button" class="btn btn-default" id="toggle-password">
                                                <i class="fa fa-eye"></i>
                                            </button>
                                        </span>
                                    </div>
                                    <small class="text-muted"><?php echo _l('kt_einvoice_api_password_help'); ?></small>
                                </div>

                                <!-- Test Connection -->
                                <div class="form-group">
                                    <button type="button" id="btn-test-connection" class="btn btn-info btn-sm">
                                        <i class="fa fa-wifi"></i> <?php echo _l('kt_einvoice_test_connection'); ?>
                                    </button>
                                    <span id="connection-result" class="tw-ml-3 tw-font-medium" style="display:none;"></span>
                                </div>

                                <!-- Provider Account -->
                                <div class="form-group">
                                    <label class="control-label"><?php echo _l('kt_einvoice_provider_account'); ?></label>
                                    <select name="provider_account_id" id="provider-account-select" class="form-control selectpicker" data-live-search="true">
                                        <option value=""><?php echo _l('kt_einvoice_provider_select'); ?></option>
                                        <?php if (!empty($settings['provider_account_id'])): ?>
                                            <option value="<?php echo $settings['provider_account_id']; ?>" selected>
                                                <?php echo htmlspecialchars($settings['provider_account_name'] ?? $settings['provider_account_id']); ?>
                                            </option>
                                        <?php endif; ?>
                                    </select>
                                    <small class="text-muted">
                                        <a href="#" id="load-providers"><i class="fa fa-refresh"></i> Tải danh sách từ SePay</a>
                                    </small>
                                </div>

                                <!-- Invoice Template & Series -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="control-label"><?php echo _l('kt_einvoice_invoice_template'); ?></label>
                                            <input type="text" name="invoice_template_code" class="form-control"
                                                value="<?php echo htmlspecialchars($settings['invoice_template_code'] ?? '01GTKT'); ?>"
                                                placeholder="01GTKT">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="control-label"><?php echo _l('kt_einvoice_invoice_series'); ?></label>
                                            <input type="text" name="invoice_series" class="form-control"
                                                value="<?php echo htmlspecialchars($settings['invoice_series'] ?? 'C'); ?>"
                                                placeholder="C">
                                        </div>
                                    </div>
                                </div>

                                <!-- Auto issue -->
                                <div class="form-group">
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="auto_issue_on_payment" value="1"
                                                <?php echo !empty($settings['auto_issue_on_payment']) ? 'checked' : ''; ?>>
                                            <?php echo _l('kt_einvoice_auto_issue_on_payment'); ?>
                                        </label>
                                        <p class="help-block"><?php echo _l('kt_einvoice_auto_issue_help'); ?></p>
                                    </div>
                                </div>

                                <!-- Active -->
                                <div class="form-group">
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="is_active" value="1"
                                                <?php echo !empty($settings['is_active']) ? 'checked' : ''; ?>>
                                            <?php echo _l('kt_einvoice_is_active'); ?>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div><!-- /col-left -->

                    <!-- Cột phải: Thông tin người bán -->
                    <div class="col-md-6">

                        <!-- Card: Seller Info -->
                        <div class="panel_s">
                            <div class="panel-body">
                                <div class="tw-flex tw-items-center tw-mb-4">
                                    <div class="tw-w-10 tw-h-10 tw-bg-green-100 tw-rounded-lg tw-flex tw-items-center tw-justify-center tw-mr-3">
                                        <i class="fa fa-building tw-text-green-600"></i>
                                    </div>
                                    <div>
                                        <h5 class="tw-font-semibold tw-text-gray-800 tw-mb-0"><?php echo _l('kt_einvoice_seller_info'); ?></h5>
                                        <small class="tw-text-gray-500">Thông tin xuất hiện trên hóa đơn của bạn</small>
                                    </div>
                                </div>
                                <hr class="tw-my-3">

                                <?php
                                $sellerFields = [
                                    ['name' => 'seller_tax_code',    'label' => _l('kt_einvoice_seller_tax_code'),    'required' => true,  'placeholder' => '0123456789'],
                                    ['name' => 'seller_name',        'label' => _l('kt_einvoice_seller_name'),        'required' => true,  'placeholder' => 'Tên công ty / hộ kinh doanh'],
                                    ['name' => 'seller_address',     'label' => _l('kt_einvoice_seller_address'),     'required' => true,  'placeholder' => 'Địa chỉ đầy đủ'],
                                    ['name' => 'seller_phone',       'label' => _l('kt_einvoice_seller_phone'),       'required' => false, 'placeholder' => '0901234567'],
                                    ['name' => 'seller_email',       'label' => _l('kt_einvoice_seller_email'),       'required' => false, 'placeholder' => 'email@congty.vn'],
                                    ['name' => 'seller_bank_name',   'label' => _l('kt_einvoice_seller_bank_name'),   'required' => false, 'placeholder' => 'Vietcombank - Chi nhánh HCM'],
                                    ['name' => 'seller_bank_account','label' => _l('kt_einvoice_seller_bank_account'),'required' => false, 'placeholder' => '1234567890'],
                                ];
                                foreach ($sellerFields as $field): ?>
                                    <div class="form-group">
                                        <label class="control-label">
                                            <?php echo $field['label']; ?>
                                            <?php if ($field['required']): ?><span class="text-danger">*</span><?php endif; ?>
                                        </label>
                                        <input type="text" name="<?php echo $field['name']; ?>" class="form-control"
                                            value="<?php echo htmlspecialchars($settings[$field['name']] ?? ''); ?>"
                                            placeholder="<?php echo $field['placeholder']; ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Card: Quota Info -->
                        <?php if (!empty($quota)): ?>
                        <div class="panel_s">
                            <div class="panel-body">
                                <h5 class="tw-font-semibold tw-mb-3"><i class="fa fa-tachometer"></i> <?php echo _l('kt_einvoice_quota_title'); ?></h5>
                                <div class="tw-flex tw-justify-between tw-mb-2">
                                    <span class="text-muted"><?php echo str_replace(['{month}', '{year}'], [date('n'), date('Y')], _l('kt_einvoice_quota_month')); ?></span>
                                    <?php if ($quota['unlimited']): ?>
                                        <strong class="text-success"><?php echo _l('kt_einvoice_quota_unlimited'); ?></strong>
                                    <?php else: ?>
                                        <strong><?php echo $quota['used']; ?> / <?php echo $quota['plan_quota']; ?></strong>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$quota['unlimited']): ?>
                                    <div class="progress progress-sm">
                                        <?php $pct = $quota['plan_quota'] > 0 ? round($quota['used'] / $quota['plan_quota'] * 100) : 0; ?>
                                        <div class="progress-bar <?php echo $pct >= 90 ? 'progress-bar-danger' : ($pct >= 70 ? 'progress-bar-warning' : 'progress-bar-success'); ?>"
                                             style="width: <?php echo $pct; ?>%"></div>
                                    </div>
                                    <?php if ($quota['remaining'] !== null && $quota['remaining'] <= _l('kt_einvoice_quota_low_threshold')): ?>
                                        <p class="text-warning tw-mt-2">
                                            <i class="fa fa-exclamation-triangle"></i>
                                            <?php echo str_replace('{remaining}', $quota['remaining'], _l('kt_einvoice_quota_warning')); ?>
                                        </p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div><!-- /col-right -->
                </div><!-- /row -->

                <!-- Submit -->
                <div class="tw-flex tw-justify-end tw-mb-6">
                    <a href="<?php echo admin_url('kt_einvoice/dashboard'); ?>" class="btn btn-default tw-mr-3">Huỷ</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> <?php echo _l('kt_einvoice_save_settings'); ?>
                    </button>
                </div>

                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    // Toggle password visibility
    $('#toggle-password').on('click', function() {
        var input = $('#kt-einvoice-password');
        var type = input.attr('type') === 'password' ? 'text' : 'password';
        input.attr('type', type);
        $(this).find('i').toggleClass('fa-eye fa-eye-slash');
    });

    // Test connection
    $('#btn-test-connection').on('click', function() {
        var $btn = $(this);
        var $result = $('#connection-result');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Đang kiểm tra...');
        $result.hide();

        $.post('<?php echo admin_url('kt_einvoice/test_connection'); ?>', {
            environment: $('input[name=environment]:checked').val()
        }, function(resp) {
            $result.show();
            if (resp.success) {
                $result.html('<i class="fa fa-check-circle text-success"></i> <span class="text-success">' + resp.message +
                    (resp.remaining !== null ? ' — Còn lại: <strong>' + resp.remaining + '</strong>' : '') + '</span>');
            } else {
                $result.html('<i class="fa fa-times-circle text-danger"></i> <span class="text-danger">' + resp.message + '</span>');
            }
        }, 'json').always(function() {
            $btn.prop('disabled', false).html('<i class="fa fa-wifi"></i> <?php echo _l('kt_einvoice_test_connection'); ?>');
        });
    });

    // Load providers
    $('#load-providers').on('click', function(e) {
        e.preventDefault();
        $.get('<?php echo admin_url('kt_einvoice/get_providers'); ?>', function(resp) {
            if (resp.success && resp.data) {
                var $sel = $('#provider-account-select');
                $sel.find('option:not(:first)').remove();
                $.each(resp.data, function(i, p) {
                    $sel.append('<option value="' + p.id + '">' + p.name + '</option>');
                });
                $sel.selectpicker('refresh');
                toastr.success('Đã tải ' + resp.data.length + ' nhà cung cấp.');
            }
        }, 'json');
    });
});
</script>

<?php init_tail(); ?>
