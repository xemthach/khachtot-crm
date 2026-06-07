<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$settings = is_array($settings ?? null) ? $settings : [];
$staffMembers = is_array($staff_members ?? null) ? $staff_members : [];
$formOptions = is_array($form_options ?? null) ? $form_options : [];
$languageOptions = is_array($formOptions['languages'] ?? null) ? $formOptions['languages'] : ['english'];
$timezoneOptions = is_array($formOptions['timezones'] ?? null) ? $formOptions['timezones'] : ['UTC'];
$dateFormatOptions = is_array($formOptions['date_formats'] ?? null) ? $formOptions['date_formats'] : ['Y-m-d|%Y-%m-%d' => '2026-05-27'];
$numberFormatOptions = is_array($formOptions['number_formats'] ?? null) ? $formOptions['number_formats'] : ['1' => 'Number based (000001)'];
$prefixSuggestions = is_array($formOptions['prefix_suggestions'] ?? null) ? $formOptions['prefix_suggestions'] : [];
$quickLinks = is_array($quick_links ?? null) ? $quick_links : [];
$selectedStaffIds = json_decode((string) ($settings['workspace_settings_staff_ids'] ?? '[]'), true);
$selectedStaffIds = is_array($selectedStaffIds) ? array_map('intval', $selectedStaffIds) : [];
$selectedGovernanceViewStaffIds = json_decode((string) ($settings['workspace_governance_view_staff_ids'] ?? '[]'), true);
$selectedGovernanceViewStaffIds = is_array($selectedGovernanceViewStaffIds) ? array_map('intval', $selectedGovernanceViewStaffIds) : [];
$selectedGovernanceManageStaffIds = json_decode((string) ($settings['workspace_governance_manage_staff_ids'] ?? '[]'), true);
$selectedGovernanceManageStaffIds = is_array($selectedGovernanceManageStaffIds) ? array_map('intval', $selectedGovernanceManageStaffIds) : [];
$currentLogo = trim((string) ($settings['company_logo'] ?? ''));
$currentLogoDark = trim((string) ($settings['company_logo_dark'] ?? ''));
$currentFavicon = trim((string) ($settings['favicon'] ?? ''));
$currentTenant = function_exists('kt_saas_current_tenant') ? kt_saas_current_tenant() : null;
$currentTenantId = (int) ($currentTenant['id'] ?? 0);
$currentLogoUrl = function_exists('kt_saas_tenant_branding_url') ? kt_saas_tenant_branding_url($currentTenantId, $currentLogo) : '';
$currentLogoDarkUrl = function_exists('kt_saas_tenant_branding_url') ? kt_saas_tenant_branding_url($currentTenantId, $currentLogoDark) : '';
$currentFaviconUrl = function_exists('kt_saas_tenant_branding_url') ? kt_saas_tenant_branding_url($currentTenantId, $currentFavicon) : '';
$canEditBranding = !empty($can_edit_branding);
$canEditCompanyProfile = !empty($can_edit_company_profile);
$canEditLocalization = !empty($can_edit_localization);
$canEditFinance = !empty($can_edit_finance);
$canEditFinanceAdvanced = !empty($can_edit_finance_advanced);
$canEditMailIdentity = !empty($can_edit_mail_identity);
$canEditNotifications = !empty($can_edit_notifications);
$canViewGovernance = !empty($can_view_governance);
$canManageGovernance = !empty($can_manage_governance);
$localizationWarning = trim((string) ($localization_warning ?? ''));
$tenantEmailEntitlements = is_array($tenant_email_entitlements ?? null) ? $tenant_email_entitlements : [];
$tenantEmailSettings = is_array($tenant_email_settings ?? null) ? $tenant_email_settings : [];
$tenantOwnCreds = !empty($tenantEmailEntitlements['own_credentials']);
$tenantCustomSender = !empty($tenantEmailEntitlements['custom_sender']);
$tenantCustomSmtp = !empty($tenantEmailEntitlements['custom_smtp']) || !empty($tenantEmailEntitlements['brevo_smtp']);
$tenantBrevoApi = !empty($tenantEmailEntitlements['brevo_api']);
$tenantCanConfigureTransport = $tenantOwnCreds;
$csrfTokenName = $this->security->get_csrf_token_name();
$csrfTokenHash = $this->security->get_csrf_hash();
$csrfField = '<input type="hidden" name="' . html_escape($csrfTokenName) . '" value="' . html_escape($csrfTokenHash) . '">';
$useEmailIdentity = ($settings['kt_saas_use_custom_email_identity'] ?? '0') === '1';
$useInvoiceSettings = ($settings['kt_saas_use_invoice_settings'] ?? '0') === '1';
$useBranding = ($settings['kt_saas_use_custom_branding'] ?? '0') === '1';
$setupChecks = [
    'Hồ sơ doanh nghiệp' => trim((string) ($settings['companyname'] ?? '')) !== '',
    'Ngôn ngữ & định dạng' => trim((string) ($settings['active_language'] ?? '')) !== '' && trim((string) ($settings['default_timezone'] ?? '')) !== '' && trim((string) ($settings['default_currency'] ?? '')) !== '',
    'Thông tin người gửi' => $useEmailIdentity,
    'Kênh gửi email' => !empty($tenantEmailSettings['is_active']),
    'Thương hiệu' => $useBranding && ($currentLogo !== '' || $currentLogoDark !== '' || $currentFavicon !== ''),
    'Thông tin hóa đơn' => $useInvoiceSettings,
];
$setupCompleted = 0;
foreach ($setupChecks as $isComplete) {
    if ($isComplete) {
        $setupCompleted++;
    }
}
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mb-2"><?php echo html_escape($title ?? 'Cài đặt doanh nghiệp'); ?></h4>
                <p class="text-muted">Các cài đặt của doanh nghiệp được tách riêng với cấu hình hệ thống trung tâm.</p>
            </div>
        </div>

        <?php if (!empty($quickLinks)) { ?>
            <div class="row">
                <div class="col-md-12">
                    <div class="panel_s">
                        <div class="panel-body">
                            <h4 class="no-margin">Lối tắt doanh nghiệp</h4>
                            <hr class="hr-panel-heading" />
                            <div class="tw-flex tw-flex-wrap tw-gap-3">
                                <?php foreach ($quickLinks as $link) { ?>
                                    <a href="<?php echo html_escape($link['href']); ?>" class="btn btn-default">
                                        <?php echo html_escape($link['label']); ?>
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>

        <div class="panel_s">
            <div class="panel-body">
                <div class="tw-flex tw-items-center tw-justify-between tw-flex-wrap tw-gap-4">
                    <div>
                        <h4 class="no-margin">Tiến độ hoàn thiện cấu hình</h4>
                        <p class="text-muted mtop5">Mỗi mục lưu độc lập và có trạng thái riêng.</p>
                    </div>
                    <strong><?php echo (int) $setupCompleted; ?>/<?php echo (int) count($setupChecks); ?> mục đã xong</strong>
                </div>
                <hr class="hr-panel-heading" />
                <div class="row">
                    <?php foreach ($setupChecks as $label => $isComplete) { ?>
                        <div class="col-md-2 col-sm-4 col-xs-6 mtop10">
                            <span class="label label-<?php echo $isComplete ? 'success' : 'warning'; ?>"><?php echo $isComplete ? 'Đã xong' : 'Cần bổ sung'; ?></span>
                            <div class="mtop5"><?php echo html_escape($label); ?></div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>

            <ul class="nav nav-tabs" role="tablist">
                <li role="presentation" class="active"><a href="#ws-tab-profile" aria-controls="ws-tab-profile" role="tab" data-toggle="tab">Hồ sơ & thương hiệu</a></li>
                <li role="presentation"><a href="#ws-tab-finance" aria-controls="ws-tab-finance" role="tab" data-toggle="tab">Tài chính</a></li>
                <li role="presentation"><a href="#ws-tab-notifications" aria-controls="ws-tab-notifications" role="tab" data-toggle="tab">Thông báo</a></li>
                <li role="presentation"><a href="#ws-tab-access" aria-controls="ws-tab-access" role="tab" data-toggle="tab">Truy cập & Điều phối</a></li>
            </ul>
            <div class="tab-content mtop15">
                <div role="tabpanel" class="tab-pane active" id="ws-tab-profile">

            <div class="row">
                <div class="col-md-6">
                    <div class="panel_s">
                        <div class="panel-body">
                            <form action="<?php echo admin_url('kt_saas/tenant_settings_profile_save'); ?>" method="post" accept-charset="utf-8">
                            <?php echo $csrfField; ?>
                            <h4 class="no-margin">Hồ sơ doanh nghiệp</h4>
                            <hr class="hr-panel-heading" />
                            <?php if (!$canEditCompanyProfile) { ?>
                                <div class="alert alert-warning">Gói hiện tại chưa hỗ trợ cập nhật hồ sơ doanh nghiệp.</div>
                            <?php } ?>
                            <div class="form-group">
                                <label for="companyname" class="control-label">Tên doanh nghiệp</label>
                                <input type="text" class="form-control" id="companyname" name="companyname" value="<?php echo html_escape($settings['companyname'] ?? ''); ?>" required <?php echo !$canEditCompanyProfile ? 'disabled' : ''; ?>>
                            </div>
                            <div class="form-group">
                                <label for="company_email" class="control-label">Email doanh nghiệp</label>
                                <input type="email" class="form-control" id="company_email" name="company_email" value="<?php echo html_escape($settings['company_email'] ?? ''); ?>" <?php echo !$canEditCompanyProfile ? 'disabled' : ''; ?>>
                            </div>
                            <div class="form-group">
                                <label for="companyphonenumber" class="control-label">Số điện thoại doanh nghiệp</label>
                                <input type="text" class="form-control" id="companyphonenumber" name="companyphonenumber" value="<?php echo html_escape($settings['companyphonenumber'] ?? ''); ?>" <?php echo !$canEditCompanyProfile ? 'disabled' : ''; ?>>
                            </div>
                            <div class="form-group">
                                <label for="company_vat" class="control-label">Mã số thuế</label>
                                <input type="text" class="form-control" id="company_vat" name="company_vat" value="<?php echo html_escape($settings['company_vat'] ?? ''); ?>" <?php echo !$canEditCompanyProfile ? 'disabled' : ''; ?>>
                            </div>
                            <div class="text-right">
                                <button type="submit" class="btn btn-primary" <?php echo !$canEditCompanyProfile ? 'disabled' : ''; ?>>Lưu hồ sơ</button>
                            </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="panel_s">
                        <div class="panel-body">
                            <form action="<?php echo admin_url('kt_saas/tenant_settings_localization_save'); ?>" method="post" accept-charset="utf-8">
                            <?php echo $csrfField; ?>
                            <h4 class="no-margin">Ngôn ngữ & định dạng</h4>
                            <hr class="hr-panel-heading" />
                            <?php if (!$canEditLocalization) { ?>
                                <div class="alert alert-warning">Gói hiện tại chưa hỗ trợ cập nhật ngôn ngữ và định dạng.</div>
                            <?php } ?>
                            <?php if ($localizationWarning !== '') { ?>
                                <div class="alert alert-warning"><?php echo html_escape($localizationWarning); ?></div>
                            <?php } ?>
                            <div class="form-group">
                                <label for="active_language" class="control-label">Ngôn ngữ mặc định</label>
                                <select name="active_language" id="active_language" class="form-control" <?php echo !$canEditLocalization ? 'disabled' : ''; ?>>
                                    <?php foreach ($languageOptions as $language) { ?>
                                        <option value="<?php echo html_escape($language); ?>" <?php echo ($settings['active_language'] ?? 'english') === $language ? 'selected' : ''; ?>>
                                            <?php echo html_escape(ucfirst($language)); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="default_timezone" class="control-label">Múi giỠmặc định</label>
                                <select name="default_timezone" id="default_timezone" class="form-control" <?php echo !$canEditLocalization ? 'disabled' : ''; ?>>
                                    <?php foreach ($timezoneOptions as $timezone) { ?>
                                        <option value="<?php echo html_escape($timezone); ?>" <?php echo ($settings['default_timezone'] ?? 'UTC') === $timezone ? 'selected' : ''; ?>>
                                            <?php echo html_escape($timezone); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="default_currency" class="control-label">Tiền tệ mặc định</label>
                                <select name="default_currency" id="default_currency" class="form-control" <?php echo !$canEditLocalization ? 'disabled' : ''; ?>>
                                    <?php foreach ((array) ($form_options['currencies'] ?? []) as $currency) { ?>
                                        <?php
                                        $currencyCode = strtoupper((string) ($currency['code'] ?? ''));
                                        $currencySymbol = trim((string) ($currency['symbol'] ?? ''));
                                        $label = $currencyCode . ($currencySymbol !== '' ? ' - ' . $currencySymbol : '');
                                        ?>
                                        <option value="<?php echo html_escape($currencyCode); ?>" <?php echo strtoupper((string) ($settings['default_currency'] ?? 'USD')) === $currencyCode ? 'selected' : ''; ?>>
                                            <?php echo html_escape($label); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="dateformat" class="control-label">Định dạng ngày</label>
                                <select name="dateformat" id="dateformat" class="form-control" <?php echo !$canEditLocalization ? 'disabled' : ''; ?>>
                                    <?php foreach ($dateFormatOptions as $key => $label) { ?>
                                        <option value="<?php echo html_escape($key); ?>" <?php echo ($settings['dateformat'] ?? 'Y-m-d|%Y-%m-%d') === $key ? 'selected' : ''; ?>>
                                            <?php echo html_escape($label); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="time_format" class="control-label">Định dạng giờ</label>
                                <select name="time_format" id="time_format" class="form-control" <?php echo !$canEditLocalization ? 'disabled' : ''; ?>>
                                    <option value="24" <?php echo ($settings['time_format'] ?? '24') === '24' ? 'selected' : ''; ?>>24 giờ</option>
                                    <option value="12" <?php echo ($settings['time_format'] ?? '24') === '12' ? 'selected' : ''; ?>>12 giờ</option>
                                </select>
                            </div>
                            <div class="text-right">
                                <button type="submit" class="btn btn-primary" <?php echo !$canEditLocalization ? 'disabled' : ''; ?>>Lưu ngôn ngữ</button>
                            </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="panel_s">
                        <div class="panel-body">
                            <form action="<?php echo admin_url('kt_saas/tenant_settings_email_identity_save'); ?>" method="post" accept-charset="utf-8">
                            <?php echo $csrfField; ?>
                            <h4 class="no-margin">Thông tin người gửi</h4>
                            <hr class="hr-panel-heading" />
                            <?php if (!$canEditMailIdentity) { ?>
                                <div class="alert alert-warning">Gói hiện tại chưa hỗ trợ cập nhật thông tin người gửi.</div>
                            <?php } ?>
                            <p class="text-muted"><?php echo $tenantOwnCreds
                                ? 'Gói hiện tại cho phép cấu hình kênh gửi email, tên người gửi, địa chỉ phản hồi và bố cục email riêng cho doanh nghiệp.'
                                : 'Kênh gửi email được quản lý tập trung. Mục này chỉ dùng để cập nhật tên người gửi, địa chỉ phản hồi, email nhận bản sao và bố cục email của doanh nghiệp.'; ?></p>
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" id="use_custom_email_identity" name="use_custom_email_identity" value="1" data-toggle-target="#tenant-email-identity-fields" <?php echo $useEmailIdentity ? 'checked' : ''; ?> <?php echo !$canEditMailIdentity ? 'disabled' : ''; ?>>
                                <label for="use_custom_email_identity">Dùng thông tin người gửi riêng</label>
                            </div>
                            <div id="tenant-email-identity-fields">
                            <div class="form-group">
                                <label for="kt_saas_mail_from_name" class="control-label">Tên hiển thị người gửi</label>
                                <input type="text" class="form-control" id="kt_saas_mail_from_name" name="kt_saas_mail_from_name" value="<?php echo html_escape($settings['kt_saas_mail_from_name'] ?? ''); ?>" maxlength="191" <?php echo !$canEditMailIdentity ? 'disabled' : ''; ?>>
                                <p class="help-block">Nếu để trống, hệ thống sẽ dùng tên doanh nghiệp làm tên người gửi.</p>
                            </div>
                            <div class="form-group">
                                <label for="kt_saas_mail_reply_to_email" class="control-label">Email nhận phản hồi</label>
                                <input type="email" class="form-control" id="kt_saas_mail_reply_to_email" name="kt_saas_mail_reply_to_email" value="<?php echo html_escape($settings['kt_saas_mail_reply_to_email'] ?? ''); ?>" maxlength="191" <?php echo !$canEditMailIdentity ? 'disabled' : ''; ?>>
                            </div>
                            <div class="form-group">
                                <label for="bcc_emails" class="control-label">Email nhận bản sao</label>
                                <input type="text" class="form-control" id="bcc_emails" name="bcc_emails" value="<?php echo html_escape($settings['bcc_emails'] ?? ''); ?>" <?php echo !$canEditMailIdentity ? 'disabled' : ''; ?>>
                                <p class="help-block">Nhập nhiều email, ngăn cách bằng dấu phẩy.</p>
                            </div>
                            <div class="form-group">
                                <label for="email_signature" class="control-label">Chữ ký email mặc định</label>
                                <?php echo render_textarea('email_signature', '', $settings['email_signature'] ?? '', ['rows' => 5, 'id' => 'email_signature', 'data-entities-encode' => 'true', $canEditMailIdentity ? 'data-enabled' : 'disabled' => $canEditMailIdentity ? '1' : 'disabled'], [], '', 'tinymce-tenant-email-signature'); ?>
                            </div>
                            <div class="form-group">
                                <label for="email_header" class="control-label">Phần đầu email</label>
                                <?php echo render_textarea('email_header', '', $settings['email_header'] ?? '', ['rows' => 6, 'id' => 'email_header', 'data-entities-encode' => 'true', $canEditMailIdentity ? 'data-enabled' : 'disabled' => $canEditMailIdentity ? '1' : 'disabled'], [], '', 'tinymce-tenant-email-header'); ?>
                            </div>
                            <div class="form-group">
                                <label for="email_footer" class="control-label">Phần cuối email</label>
                                <?php echo render_textarea('email_footer', '', $settings['email_footer'] ?? '', ['rows' => 6, 'id' => 'email_footer', 'data-entities-encode' => 'true', $canEditMailIdentity ? 'data-enabled' : 'disabled' => $canEditMailIdentity ? '1' : 'disabled'], [], '', 'tinymce-tenant-email-footer'); ?>
                            </div>
                            </div>
                            <div class="text-right">
                                <button type="submit" class="btn btn-primary" <?php echo !$canEditMailIdentity ? 'disabled' : ''; ?>>Lưu thông tin người gửi</button>
                            </div>
                            </form>
                        </div>
                    </div>

                            <div class="panel_s">
                                <div class="panel-body">
                                    <form action="<?php echo admin_url('kt_saas/tenant_email_settings_save'); ?>" method="post" accept-charset="utf-8">
                                    <?php echo $csrfField; ?>
                                    <h4 class="no-margin">Kênh gửi email</h4>
                                    <hr class="hr-panel-heading" />
                                    <?php if (!$tenantCanConfigureTransport) { ?>
                                        <div class="alert alert-warning">Gói hiện tại chưa hỗ trợ cấu hình kênh gửi email riêng.</div>
                                        <p class="text-muted">Kênh gửi email được quản lý tập trung. Bạn vẫn có thể cập nhật thông tin người gửi trong mục Thông tin người gửi.</p>
                                    <?php } else { ?>
                                        <div class="tenant-email-provider-fields">
                                            <div class="form-group">
                                                <label for="tenant_email_provider" class="control-label">Cách gửi email</label>
                                                <select name="provider" id="tenant_email_provider" class="form-control">
                                                    <option value="system_smtp" <?php echo (($tenantEmailSettings['provider'] ?? 'system_smtp') === 'system_smtp') ? 'selected' : ''; ?>>Kênh gửi mặc định</option>
                                                    <?php if ($tenantCustomSmtp) { ?>
                                                        <option value="brevo_smtp" <?php echo (($tenantEmailSettings['provider'] ?? '') === 'brevo_smtp') ? 'selected' : ''; ?>>Kênh Brevo</option>
                                                    <?php } ?>
                                                    <?php if ($tenantBrevoApi) { ?>
                                                        <option value="brevo_api" <?php echo (($tenantEmailSettings['provider'] ?? '') === 'brevo_api') ? 'selected' : ''; ?>>Kết nối Brevo</option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6"><div class="form-group"><label class="control-label">Tên người gửi</label><input type="text" name="sender_name" class="form-control" value="<?php echo html_escape($tenantEmailSettings['sender_name'] ?? ''); ?>"></div></div>
                                                <div class="col-md-6"><div class="form-group"><label class="control-label">Email người gửi</label><input type="email" name="sender_email" class="form-control" value="<?php echo html_escape($tenantEmailSettings['sender_email'] ?? ''); ?>"></div></div>
                                            </div>
                                            <div class="form-group"><label class="control-label">Email nhận phản hồi</label><input type="email" name="reply_to_email" class="form-control" value="<?php echo html_escape($tenantEmailSettings['reply_to_email'] ?? ''); ?>"></div>
                                            <?php if ($tenantCustomSmtp) { ?>
                                                <div class="row tenant-email-provider-group" data-provider-group="brevo_smtp">
                                                    <div class="col-md-6"><div class="form-group"><label class="control-label">Máy chủ gửi mail</label><input type="text" name="smtp_host" class="form-control" value="<?php echo html_escape($tenantEmailSettings['smtp_host'] ?? ''); ?>"></div></div>
                                                    <div class="col-md-3"><div class="form-group"><label class="control-label">Cổng</label><input type="number" name="smtp_port" class="form-control" value="<?php echo html_escape((string) ($tenantEmailSettings['smtp_port'] ?? '587')); ?>"></div></div>
                                                    <div class="col-md-3"><div class="form-group"><label class="control-label">Bảo mật</label><input type="text" name="smtp_encryption" class="form-control" value="<?php echo html_escape($tenantEmailSettings['smtp_encryption'] ?? 'tls'); ?>"></div></div>
                                                </div>
                                                <div class="row tenant-email-provider-group" data-provider-group="brevo_smtp">
                                                    <div class="col-md-6"><div class="form-group"><label class="control-label">Tài khoản gửi mail</label><input type="text" name="smtp_username" class="form-control" value="<?php echo html_escape($tenantEmailSettings['smtp_username'] ?? ''); ?>"></div></div>
                                                    <div class="col-md-6"><div class="form-group"><label class="control-label">Mật khẩu gửi mail</label><input type="password" name="smtp_password" class="form-control" value=""></div></div>
                                                </div>
                                            <?php } ?>
                                            <?php if ($tenantBrevoApi) { ?>
                                                <div class="tenant-email-provider-group" data-provider-group="brevo_api">
                                                    <div class="form-group"><label class="control-label">Mã kết nối Brevo</label><input type="password" name="brevo_api_key" class="form-control" value=""></div>
                                                </div>
                                            <?php } ?>
                                            <div class="checkbox checkbox-primary">
                                                <input type="checkbox" id="tenant_email_is_active" name="is_active" value="1" <?php echo !empty($tenantEmailSettings['is_active']) ? 'checked' : ''; ?>>
                                                <label for="tenant_email_is_active">Dùng kênh gửi riêng</label>
                                            </div>
                                            <div class="text-right mtop15">
                                                <button type="submit" class="btn btn-primary">Lưu kênh gửi email</button>
                                            </div>
                                        </div>
                                    <?php } ?>
                                    </form>
                                </div>
                            </div>
                </div>

                <div class="col-md-6">
                    <div class="panel_s">
                        <div class="panel-body">
                            <form action="<?php echo admin_url('kt_saas/tenant_settings_invoice_save'); ?>" method="post" accept-charset="utf-8">
                            <?php echo $csrfField; ?>
                            <h4 class="no-margin">Thông tin hóa đơn mặc định</h4>
                            <hr class="hr-panel-heading" />
                            <?php if (!$canEditFinance) { ?>
                                <div class="alert alert-warning">Gói hiện tại chưa hỗ trợ cập nhật thông tin hóa đơn mặc định.</div>
                            <?php } ?>
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" id="use_invoice_settings" name="use_invoice_settings" value="1" data-toggle-target="#tenant-invoice-settings-fields" <?php echo $useInvoiceSettings ? 'checked' : ''; ?> <?php echo !$canEditFinance ? 'disabled' : ''; ?>>
                                <label for="use_invoice_settings">Dùng thông tin hóa đơn riêng</label>
                            </div>
                            <div id="tenant-invoice-settings-fields">
                            <div class="form-group">
                                <label for="invoice_company_name" class="control-label">Tên đơn vị trên hóa đơn</label>
                                <input type="text" class="form-control" id="invoice_company_name" name="invoice_company_name" value="<?php echo html_escape($settings['invoice_company_name'] ?? ''); ?>" <?php echo !$canEditFinance ? 'disabled' : ''; ?>>
                            </div>
                            <div class="form-group">
                                <label for="invoice_company_address" class="control-label">Địa chỉ trên hóa đơn</label>
                                <input type="text" class="form-control" id="invoice_company_address" name="invoice_company_address" value="<?php echo html_escape($settings['invoice_company_address'] ?? ''); ?>" <?php echo !$canEditFinance ? 'disabled' : ''; ?>>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="invoice_company_city" class="control-label">Thành phố</label>
                                        <input type="text" class="form-control" id="invoice_company_city" name="invoice_company_city" value="<?php echo html_escape($settings['invoice_company_city'] ?? ''); ?>" <?php echo !$canEditFinance ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="invoice_company_state" class="control-label">Tỉnh / bang</label>
                                        <input type="text" class="form-control" id="invoice_company_state" name="invoice_company_state" value="<?php echo html_escape($settings['invoice_company_state'] ?? ''); ?>" <?php echo !$canEditFinance ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="invoice_company_country_code" class="control-label">Mã quốc gia</label>
                                        <input type="text" class="form-control" id="invoice_company_country_code" name="invoice_company_country_code" value="<?php echo html_escape($settings['invoice_company_country_code'] ?? ''); ?>" <?php echo !$canEditFinance ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="invoice_company_postal_code" class="control-label">Mã bưu chính</label>
                                        <input type="text" class="form-control" id="invoice_company_postal_code" name="invoice_company_postal_code" value="<?php echo html_escape($settings['invoice_company_postal_code'] ?? ''); ?>" <?php echo !$canEditFinance ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="invoice_company_phonenumber" class="control-label">Số điện thoại trên hóa đơn</label>
                                <input type="text" class="form-control" id="invoice_company_phonenumber" name="invoice_company_phonenumber" value="<?php echo html_escape($settings['invoice_company_phonenumber'] ?? ''); ?>" <?php echo !$canEditFinance ? 'disabled' : ''; ?>>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="invoice_due_after" class="control-label">Hạn thanh toán hóa đơn (ngày)</label>
                                        <input type="number" min="0" class="form-control" id="invoice_due_after" name="invoice_due_after" value="<?php echo html_escape($settings['invoice_due_after'] ?? '30'); ?>" <?php echo !$canEditFinance ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="estimate_due_after" class="control-label">Hạn báo giá (ngày)</label>
                                        <input type="number" min="0" class="form-control" id="estimate_due_after" name="estimate_due_after" value="<?php echo html_escape($settings['estimate_due_after'] ?? '7'); ?>" <?php echo !$canEditFinance ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                            </div>
                            <div class="text-right">
                                <button type="submit" class="btn btn-primary" <?php echo !$canEditFinance ? 'disabled' : ''; ?>>Lưu hóa đơn</button>
                            </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="panel_s">
                        <div class="panel-body">
                            <form action="<?php echo admin_url('kt_saas/tenant_settings_branding_save'); ?>" method="post" enctype="multipart/form-data" accept-charset="utf-8">
                            <?php echo $csrfField; ?>
                            <h4 class="no-margin">Thương hiệu</h4>
                            <hr class="hr-panel-heading" />
                            <?php if (!$canEditBranding) { ?>
                                <div class="alert alert-warning">Gói hiện tại chưa hỗ trợ cập nhật thương hiệu doanh nghiệp.</div>
                            <?php } ?>
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" id="use_custom_branding" name="use_custom_branding" value="1" data-toggle-target="#tenant-branding-fields" <?php echo $useBranding ? 'checked' : ''; ?> <?php echo !$canEditBranding ? 'disabled' : ''; ?>>
                                <label for="use_custom_branding">Dùng thương hiệu riêng</label>
                            </div>
                            <div id="tenant-branding-fields">
                            <div class="form-group">
                                <label class="control-label">Logo nền sáng hiện tại</label>
                                <?php if ($currentLogoUrl !== '') { ?>
                                    <div class="well well-sm">
                                        <img src="<?php echo html_escape($currentLogoUrl); ?>" alt="Logo doanh nghiệp" style="max-height:50px;max-width:220px;" data-preview-target="company_logo">
                                        <div class="mtop10">
                                            <?php if ($canEditBranding) { ?>
                                                <small class="text-muted">Sử dụng công cụ xóa logo trong mục cài đặt doanh nghiệp.</small>
                                            <?php } ?>
                                        </div>
                                    </div>
                                <?php } else { ?>
                                    <p class="text-muted" data-empty-preview="company_logo">Chưa tải lên logo nền sáng.</p>
                                <?php } ?>
                                <input type="file" name="company_logo" class="form-control" accept=".png,.jpg,.jpeg,.webp" data-preview-input="company_logo" <?php echo !$canEditBranding ? 'disabled' : ''; ?>>
                                <p class="help-block">Khuyến nghị dùng PNG nền trong suốt, JPG hoặc WebP, ngang tối đa khoảng 400px.</p>
                            </div>
                            <div class="form-group">
                                <label class="control-label">Logo nền tối hiện tại</label>
                                <?php if ($currentLogoDarkUrl !== '') { ?>
                                    <div class="well well-sm">
                                        <img src="<?php echo html_escape($currentLogoDarkUrl); ?>" alt="Logo nền tối doanh nghiệp" style="max-height:50px;max-width:220px;" data-preview-target="company_logo_dark">
                                        <div class="mtop10">
                                            <?php if ($canEditBranding) { ?>
                                                <small class="text-muted">Sử dụng công cụ xóa logo trong mục cài đặt doanh nghiệp.</small>
                                            <?php } ?>
                                        </div>
                                    </div>
                                <?php } else { ?>
                                    <p class="text-muted" data-empty-preview="company_logo_dark">Chưa tải lên logo nền tối.</p>
                                <?php } ?>
                                <input type="file" name="company_logo_dark" class="form-control" accept=".png,.jpg,.jpeg,.webp" data-preview-input="company_logo_dark" <?php echo !$canEditBranding ? 'disabled' : ''; ?>>
                                <p class="help-block">Dùng phiên bản logo sáng nếu doanh nghiệp sử dụng nền tối.</p>
                            </div>
                            <div class="form-group">
                                <label class="control-label">Biểu tượng trình duyệt hiện tại</label>
                                <?php if ($currentFaviconUrl !== '') { ?>
                                    <div class="well well-sm">
                                        <img src="<?php echo html_escape($currentFaviconUrl); ?>" alt="Biểu tượng trình duyệt doanh nghiệp" style="max-height:32px;max-width:32px;" data-preview-target="favicon">
                                        <div class="mtop10">
                                            <?php if ($canEditBranding) { ?>
                                                <small class="text-muted">Sử dụng công cụ xóa biểu tượng trong mục cài đặt doanh nghiệp.</small>
                                            <?php } ?>
                                        </div>
                                    </div>
                                <?php } else { ?>
                                    <p class="text-muted" data-empty-preview="favicon">Chưa tải lên biểu tượng trình duyệt.</p>
                                <?php } ?>
                                <input type="file" name="favicon" class="form-control" accept=".png,.ico" data-preview-input="favicon" <?php echo !$canEditBranding ? 'disabled' : ''; ?>>
                                <p class="help-block">Khuyến nghị biểu tượng vuông 32x32 hoặc 64x64.</p>
                            </div>
                            </div>
                            <div class="text-right">
                                <button type="submit" class="btn btn-primary" <?php echo !$canEditBranding ? 'disabled' : ''; ?>>Lưu thương hiệu</button>
                            </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="ws-tab-finance">

            <div class="row">
                <div class="col-md-12">
                    <div class="panel_s">
                        <div class="panel-body">
                            <form action="<?php echo admin_url('kt_saas/tenant_settings_finance_save'); ?>" method="post" accept-charset="utf-8">
                            <?php echo $csrfField; ?>
                            <h4 class="no-margin">Tài chính nâng cao</h4>
                            <hr class="hr-panel-heading" />
                            <?php if (!$canEditFinanceAdvanced) { ?>
                                <div class="alert alert-warning">Gói hiện tại chưa hỗ trợ cập nhật cấu hình hóa đơn và báo giá nâng cao.</div>
                            <?php } ?>
                            <p class="text-muted">Các tùy chọn này chỉ áp dụng cho doanh nghiệp hiện tại và dùng để điều chỉnh cách hiển thị hóa đơn, tài liệu PDF và khu vực khách hàng.</p>

                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="tw-font-semibold">Cách hiển thị hóa đơn</h5>
                                    <div class="checkbox checkbox-primary">
                                        <input type="checkbox" id="view_invoice_only_logged_in" name="view_invoice_only_logged_in" value="1" <?php echo ($settings['view_invoice_only_logged_in'] ?? '0') === '1' ? 'checked' : ''; ?> <?php echo !$canEditFinanceAdvanced ? 'disabled' : ''; ?>>
                                        <label for="view_invoice_only_logged_in">Yêu cầu khách hàng đăng nhập để xem hóa đơn</label>
                                    </div>
                                    <div class="checkbox checkbox-primary">
                                        <input type="checkbox" id="exclude_invoice_from_client_area_with_draft_status" name="exclude_invoice_from_client_area_with_draft_status" value="1" <?php echo ($settings['exclude_invoice_from_client_area_with_draft_status'] ?? '1') === '1' ? 'checked' : ''; ?> <?php echo !$canEditFinanceAdvanced ? 'disabled' : ''; ?>>
                                        <label for="exclude_invoice_from_client_area_with_draft_status">Ẩn hóa đơn nháp khỏi khu vực khách hàng</label>
                                    </div>
                                    <div class="checkbox checkbox-primary">
                                        <input type="checkbox" id="show_sale_agent_on_invoices" name="show_sale_agent_on_invoices" value="1" <?php echo ($settings['show_sale_agent_on_invoices'] ?? '1') === '1' ? 'checked' : ''; ?> <?php echo !$canEditFinanceAdvanced ? 'disabled' : ''; ?>>
                                        <label for="show_sale_agent_on_invoices">Hiển thị người phụ trách bán hàng trên hóa đơn</label>
                                    </div>
                                    <div class="checkbox checkbox-primary">
                                        <input type="checkbox" id="show_project_on_invoice" name="show_project_on_invoice" value="1" <?php echo ($settings['show_project_on_invoice'] ?? '1') === '1' ? 'checked' : ''; ?> <?php echo !$canEditFinanceAdvanced ? 'disabled' : ''; ?>>
                                        <label for="show_project_on_invoice">Hiển thị tên dự án trên hóa đơn</label>
                                    </div>
                                    <div class="checkbox checkbox-primary">
                                        <input type="checkbox" id="show_total_paid_on_invoice" name="show_total_paid_on_invoice" value="1" <?php echo ($settings['show_total_paid_on_invoice'] ?? '1') === '1' ? 'checked' : ''; ?> <?php echo !$canEditFinanceAdvanced ? 'disabled' : ''; ?>>
                                        <label for="show_total_paid_on_invoice">Hiển thị tổng đã thanh toán trên hóa đơn</label>
                                    </div>
                                    <div class="checkbox checkbox-primary">
                                        <input type="checkbox" id="show_credits_applied_on_invoice" name="show_credits_applied_on_invoice" value="1" <?php echo ($settings['show_credits_applied_on_invoice'] ?? '1') === '1' ? 'checked' : ''; ?> <?php echo !$canEditFinanceAdvanced ? 'disabled' : ''; ?>>
                                        <label for="show_credits_applied_on_invoice">Hiển thị công nợ đã cấn trừ trên hóa đơn</label>
                                    </div>
                                    <div class="checkbox checkbox-primary">
                                        <input type="checkbox" id="show_amount_due_on_invoice" name="show_amount_due_on_invoice" value="1" <?php echo ($settings['show_amount_due_on_invoice'] ?? '1') === '1' ? 'checked' : ''; ?> <?php echo !$canEditFinanceAdvanced ? 'disabled' : ''; ?>>
                                        <label for="show_amount_due_on_invoice">Hiển thị số tiền còn phải thanh toán</label>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <h5 class="tw-font-semibold">Cách hiển thị báo giá</h5>
                                    <div class="checkbox checkbox-primary">
                                        <input type="checkbox" id="view_estimate_only_logged_in" name="view_estimate_only_logged_in" value="1" <?php echo ($settings['view_estimate_only_logged_in'] ?? '0') === '1' ? 'checked' : ''; ?> <?php echo !$canEditFinanceAdvanced ? 'disabled' : ''; ?>>
                                        <label for="view_estimate_only_logged_in">Yêu cầu khách hàng đăng nhập để xem báo giá</label>
                                    </div>
                                    <div class="checkbox checkbox-primary">
                                        <input type="checkbox" id="show_sale_agent_on_estimates" name="show_sale_agent_on_estimates" value="1" <?php echo ($settings['show_sale_agent_on_estimates'] ?? '1') === '1' ? 'checked' : ''; ?> <?php echo !$canEditFinanceAdvanced ? 'disabled' : ''; ?>>
                                        <label for="show_sale_agent_on_estimates">Hiển thị người phụ trách bán hàng trên báo giá</label>
                                    </div>
                                    <div class="checkbox checkbox-primary">
                                        <input type="checkbox" id="show_project_on_estimate" name="show_project_on_estimate" value="1" <?php echo ($settings['show_project_on_estimate'] ?? '1') === '1' ? 'checked' : ''; ?> <?php echo !$canEditFinanceAdvanced ? 'disabled' : ''; ?>>
                                        <label for="show_project_on_estimate">Hiển thị tên dự án trên báo giá</label>
                                    </div>
                                    <div class="checkbox checkbox-primary">
                                        <input type="checkbox" id="estimate_auto_convert_to_invoice_on_client_accept" name="estimate_auto_convert_to_invoice_on_client_accept" value="1" <?php echo ($settings['estimate_auto_convert_to_invoice_on_client_accept'] ?? '1') === '1' ? 'checked' : ''; ?> <?php echo !$canEditFinanceAdvanced ? 'disabled' : ''; ?>>
                                        <label for="estimate_auto_convert_to_invoice_on_client_accept">Tự tạo hóa đơn sau khi khách hàng chấp nhận báo giá</label>
                                    </div>
                                    <div class="checkbox checkbox-primary">
                                        <input type="checkbox" id="exclude_estimate_from_client_area_with_draft_status" name="exclude_estimate_from_client_area_with_draft_status" value="1" <?php echo ($settings['exclude_estimate_from_client_area_with_draft_status'] ?? '1') === '1' ? 'checked' : ''; ?> <?php echo !$canEditFinanceAdvanced ? 'disabled' : ''; ?>>
                                        <label for="exclude_estimate_from_client_area_with_draft_status">Ẩn báo giá nháp khỏi khu vực khách hàng</label>
                                    </div>
                                    <div class="checkbox checkbox-primary">
                                        <input type="checkbox" id="show_subscriptions_in_customers_area" name="show_subscriptions_in_customers_area" value="1" <?php echo ($settings['show_subscriptions_in_customers_area'] ?? '1') === '1' ? 'checked' : ''; ?> <?php echo !$canEditFinanceAdvanced ? 'disabled' : ''; ?>>
                                        <label for="show_subscriptions_in_customers_area">Hiển thị gói dịch vụ trong khu vực khách hàng</label>
                                    </div>
                                    <div class="checkbox checkbox-primary">
                                        <input type="checkbox" id="create_invoice_from_recurring_only_on_paid_invoices" name="create_invoice_from_recurring_only_on_paid_invoices" value="1" <?php echo ($settings['create_invoice_from_recurring_only_on_paid_invoices'] ?? '0') === '1' ? 'checked' : ''; ?> <?php echo !$canEditFinanceAdvanced ? 'disabled' : ''; ?>>
                                        <label for="create_invoice_from_recurring_only_on_paid_invoices">Chỉ tạo hóa đơn định kỳ khi hóa đơn trước đã thanh toán</label>
                                    </div>
                                    <div class="form-group mtop15">
                                        <label for="new_recurring_invoice_action" class="control-label">Cách tạo hóa đơn định kỳ</label>
                                        <select name="new_recurring_invoice_action" id="new_recurring_invoice_action" class="form-control" <?php echo !$canEditFinanceAdvanced ? 'disabled' : ''; ?>>
                                            <option value="generate_and_send" <?php echo ($settings['new_recurring_invoice_action'] ?? 'generate_and_send') === 'generate_and_send' ? 'selected' : ''; ?>>Tạo và gửi cho khách hàng</option>
                                            <option value="generate_unpaid" <?php echo ($settings['new_recurring_invoice_action'] ?? 'generate_and_send') === 'generate_unpaid' ? 'selected' : ''; ?>>Tạo ở trạng thái chưa thanh toán</option>
                                            <option value="generate_draft" <?php echo ($settings['new_recurring_invoice_action'] ?? 'generate_and_send') === 'generate_draft' ? 'selected' : ''; ?>>Tạo nháp</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="after_subscription_payment_captured" class="control-label">Sau khi thanh toán gói dịch vụ thành công</label>
                                        <select name="after_subscription_payment_captured" id="after_subscription_payment_captured" class="form-control" <?php echo !$canEditFinanceAdvanced ? 'disabled' : ''; ?>>
                                            <option value="send_invoice_and_receipt" <?php echo ($settings['after_subscription_payment_captured'] ?? 'send_invoice_and_receipt') === 'send_invoice_and_receipt' ? 'selected' : ''; ?>>Gửi hóa đơn và biên nhận thanh toán</option>
                                            <option value="send_invoice" <?php echo ($settings['after_subscription_payment_captured'] ?? 'send_invoice_and_receipt') === 'send_invoice' ? 'selected' : ''; ?>>Chỉ gửi hóa đơn</option>
                                            <option value="send_payment_receipt" <?php echo ($settings['after_subscription_payment_captured'] ?? 'send_invoice_and_receipt') === 'send_payment_receipt' ? 'selected' : ''; ?>>Chỉ gửi biên nhận thanh toán</option>
                                            <option value="nothing" <?php echo ($settings['after_subscription_payment_captured'] ?? 'send_invoice_and_receipt') === 'nothing' ? 'selected' : ''; ?>>Không gửi tự động</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                Các quy tắc tài chính nâng cao chỉ áp dụng trong dữ liệu riêng của doanh nghiệp và không làm lộ thông tin vận hành hệ thống trung tâm.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="ws-tab-notifications">

            <div class="row">
                <div class="col-md-12">
                    <div class="panel_s">
                        <div class="panel-body">
                            <h4 class="no-margin">Thiết lập tài chính cơ bản</h4>
                            <hr class="hr-panel-heading" />
                            <?php if (!$canEditFinance) { ?>
                                <div class="alert alert-warning">Gói hiện tại chưa hỗ trợ cập nhật thiết lập tài chính cơ bản.</div>
                            <?php } ?>
                            <p class="text-muted">Các thiết lập này quản lý số chứng từ và nội dung mặc định của hóa đơn cho doanh nghiệp mà không làm lộ hạ tầng thanh toán trung tâm.</p>
                            <div class="row">
                                <div class="col-md-4">
                                    <h5 class="tw-font-semibold">Hóa đơn</h5>
                                    <div class="form-group">
                                        <label for="invoice_prefix" class="control-label">Tiền tố hóa đơn</label>
                                        <input type="text" class="form-control" id="invoice_prefix" name="invoice_prefix" value="<?php echo html_escape($settings['invoice_prefix'] ?? ($prefixSuggestions['invoice'] ?? 'INV-')); ?>" maxlength="20" <?php echo !$canEditFinance ? 'disabled' : ''; ?>>
                                    </div>
                                    <div class="form-group">
                                        <label for="next_invoice_number" class="control-label">Số hóa đơn tiếp theo</label>
                                        <input type="number" min="1" class="form-control" id="next_invoice_number" name="next_invoice_number" value="<?php echo html_escape($settings['next_invoice_number'] ?? '1'); ?>" <?php echo !$canEditFinance ? 'disabled' : ''; ?>>
                                    </div>
                                    <div class="form-group">
                                        <label for="invoice_number_format" class="control-label">Định dạng số hóa đơn</label>
                                        <select name="invoice_number_format" id="invoice_number_format" class="form-control" <?php echo !$canEditFinance ? 'disabled' : ''; ?>>
                                            <?php foreach ($numberFormatOptions as $key => $label) { ?>
                                                <option value="<?php echo html_escape($key); ?>" <?php echo ($settings['invoice_number_format'] ?? '1') === (string) $key ? 'selected' : ''; ?>>
                                                    <?php echo html_escape($label); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <h5 class="tw-font-semibold">Báo giá</h5>
                                    <div class="form-group">
                                        <label for="estimate_prefix" class="control-label">Tiền tố báo giá</label>
                                        <input type="text" class="form-control" id="estimate_prefix" name="estimate_prefix" value="<?php echo html_escape($settings['estimate_prefix'] ?? ($prefixSuggestions['estimate'] ?? 'EST-')); ?>" maxlength="20" <?php echo !$canEditFinance ? 'disabled' : ''; ?>>
                                    </div>
                                    <div class="form-group">
                                        <label for="next_estimate_number" class="control-label">Số báo giá tiếp theo</label>
                                        <input type="number" min="1" class="form-control" id="next_estimate_number" name="next_estimate_number" value="<?php echo html_escape($settings['next_estimate_number'] ?? '1'); ?>" <?php echo !$canEditFinance ? 'disabled' : ''; ?>>
                                    </div>
                                    <div class="form-group">
                                        <label for="estimate_number_format" class="control-label">Định dạng số báo giá</label>
                                        <select name="estimate_number_format" id="estimate_number_format" class="form-control" <?php echo !$canEditFinance ? 'disabled' : ''; ?>>
                                            <?php foreach ($numberFormatOptions as $key => $label) { ?>
                                                <option value="<?php echo html_escape($key); ?>" <?php echo ($settings['estimate_number_format'] ?? '1') === (string) $key ? 'selected' : ''; ?>>
                                                    <?php echo html_escape($label); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <h5 class="tw-font-semibold">Điều chỉnh công nợ</h5>
                                    <div class="form-group">
                                        <label for="credit_note_prefix" class="control-label">Tiền tố điều chỉnh</label>
                                        <input type="text" class="form-control" id="credit_note_prefix" name="credit_note_prefix" value="<?php echo html_escape($settings['credit_note_prefix'] ?? ($prefixSuggestions['credit_note'] ?? 'CN-')); ?>" maxlength="20" <?php echo !$canEditFinance ? 'disabled' : ''; ?>>
                                    </div>
                                    <div class="form-group">
                                        <label for="next_credit_note_number" class="control-label">Số điều chỉnh tiếp theo</label>
                                        <input type="number" min="1" class="form-control" id="next_credit_note_number" name="next_credit_note_number" value="<?php echo html_escape($settings['next_credit_note_number'] ?? '1'); ?>" <?php echo !$canEditFinance ? 'disabled' : ''; ?>>
                                    </div>
                                    <div class="form-group">
                                        <label for="credit_note_number_format" class="control-label">Định dạng số điều chỉnh</label>
                                        <select name="credit_note_number_format" id="credit_note_number_format" class="form-control" <?php echo !$canEditFinance ? 'disabled' : ''; ?>>
                                            <?php foreach ($numberFormatOptions as $key => $label) { ?>
                                                <option value="<?php echo html_escape($key); ?>" <?php echo ($settings['credit_note_number_format'] ?? '1') === (string) $key ? 'selected' : ''; ?>>
                                                    <?php echo html_escape($label); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="predefined_clientnote_invoice" class="control-label">Ghi chú mặc định trên hóa đơn</label>
                                        <textarea class="form-control" id="predefined_clientnote_invoice" name="predefined_clientnote_invoice" rows="4" <?php echo !$canEditFinance ? 'disabled' : ''; ?>><?php echo html_escape($settings['predefined_clientnote_invoice'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="predefined_terms_invoice" class="control-label">Điều khoản mặc định trên hóa đơn</label>
                                        <textarea class="form-control" id="predefined_terms_invoice" name="predefined_terms_invoice" rows="4" <?php echo !$canEditFinance ? 'disabled' : ''; ?>><?php echo html_escape($settings['predefined_terms_invoice'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-warning">
                                Các thay đổi đánh số chỉ áp dụng cho doanh nghiệp này vì dữ liệu được lưu riêng, không dùng cấu hình chung của hệ thống.
                            </div>
                            <div class="text-right">
                                <button type="submit" class="btn btn-primary" <?php echo !$canEditFinanceAdvanced ? 'disabled' : ''; ?>>Lưu tài chính nâng cao</button>
                            </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="ws-tab-access">

            <div class="row">
                <div class="col-md-12">
                    <div class="panel_s">
                        <div class="panel-body">
                            <form action="<?php echo admin_url('kt_saas/tenant_settings_notifications_save'); ?>" method="post" accept-charset="utf-8">
                            <?php echo $csrfField; ?>
                            <h4 class="no-margin">Tùy chọn thông báo</h4>
                            <hr class="hr-panel-heading" />
                            <?php if (!$canEditNotifications) { ?>
                                <div class="alert alert-warning">Gói hiện tại chưa hỗ trợ cập nhật tùy chọn thông báo.</div>
                            <?php } ?>
                            <p class="text-muted">Các thiết lập này điều khiển lịch nhắc và cách gửi thông báo tới khách hàng. Hạ tầng gửi thông báo vẫn do hệ thống trung tâm quản lý.</p>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="automatically_send_invoice_overdue_reminder_after" class="control-label">Nhắc hóa đơn quá hạn sau (ngày)</label>
                                        <input type="number" min="0" class="form-control" id="automatically_send_invoice_overdue_reminder_after" name="automatically_send_invoice_overdue_reminder_after" value="<?php echo html_escape($settings['automatically_send_invoice_overdue_reminder_after'] ?? '0'); ?>" <?php echo !$canEditNotifications ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="automatically_resend_invoice_overdue_reminder_after" class="control-label">Gửi lại nhắc quá hạn sau (ngày)</label>
                                        <input type="number" min="0" class="form-control" id="automatically_resend_invoice_overdue_reminder_after" name="automatically_resend_invoice_overdue_reminder_after" value="<?php echo html_escape($settings['automatically_resend_invoice_overdue_reminder_after'] ?? '0'); ?>" <?php echo !$canEditNotifications ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="invoice_due_notice_before" class="control-label">Nhắc trước hạn hóa đơn (ngày)</label>
                                        <input type="number" min="0" class="form-control" id="invoice_due_notice_before" name="invoice_due_notice_before" value="<?php echo html_escape($settings['invoice_due_notice_before'] ?? '0'); ?>" <?php echo !$canEditNotifications ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="invoice_due_notice_resend_after" class="control-label">Gửi lại nhắc trước hạn sau (ngày)</label>
                                        <input type="number" min="0" class="form-control" id="invoice_due_notice_resend_after" name="invoice_due_notice_resend_after" value="<?php echo html_escape($settings['invoice_due_notice_resend_after'] ?? '0'); ?>" <?php echo !$canEditNotifications ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="send_estimate_expiry_reminder_before" class="control-label">Nhắc trước hạn báo giá (ngày)</label>
                                        <input type="number" min="0" class="form-control" id="send_estimate_expiry_reminder_before" name="send_estimate_expiry_reminder_before" value="<?php echo html_escape($settings['send_estimate_expiry_reminder_before'] ?? '0'); ?>" <?php echo !$canEditNotifications ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="contract_expiration_before" class="control-label">Nhắc trước hạn hợp đồng (ngày)</label>
                                        <input type="number" min="0" class="form-control" id="contract_expiration_before" name="contract_expiration_before" value="<?php echo html_escape($settings['contract_expiration_before'] ?? '0'); ?>" <?php echo !$canEditNotifications ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="contract_sign_reminder_every_days" class="control-label">Nhắc ký hợp đồng mới (ngày)</label>
                                        <input type="number" min="0" class="form-control" id="contract_sign_reminder_every_days" name="contract_sign_reminder_every_days" value="<?php echo html_escape($settings['contract_sign_reminder_every_days'] ?? '0'); ?>" <?php echo !$canEditNotifications ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" id="attach_invoice_to_payment_receipt_email" name="attach_invoice_to_payment_receipt_email" value="1" <?php echo ($settings['attach_invoice_to_payment_receipt_email'] ?? '0') === '1' ? 'checked' : ''; ?> <?php echo !$canEditNotifications ? 'disabled' : ''; ?>>
                                <label for="attach_invoice_to_payment_receipt_email">Đính kèm PDF hóa đơn khi gửi biên nhận thanh toán</label>
                            </div>
                            <div class="text-right">
                                <button type="submit" class="btn btn-primary" <?php echo !$canEditNotifications ? 'disabled' : ''; ?>>Lưu thông báo</button>
                            </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="panel_s">
                        <div class="panel-body">
                            <form action="<?php echo admin_url('kt_saas/tenant_settings_governance_save'); ?>" method="post" accept-charset="utf-8">
                            <?php echo $csrfField; ?>
                            <h4 class="no-margin">Truy cập doanh nghiệp</h4>
                            <hr class="hr-panel-heading" />
                            <p class="text-muted">Quản trị viên doanh nghiệp luôn giữ quyền truy cập. Chọn thêm nhân sự có thể quản lý phần cài đặt này.</p>
                            <div class="form-group">
                                <label for="workspace_settings_staff_ids" class="control-label">Nhân sự được phép quản lý</label>
                                <select name="workspace_settings_staff_ids[]" id="workspace_settings_staff_ids" class="form-control" multiple size="6">
                                    <?php foreach ($staffMembers as $staff) { ?>
                                        <?php $staffId = (int) ($staff['staffid'] ?? 0); ?>
                                        <option value="<?php echo $staffId; ?>" <?php echo in_array($staffId, $selectedStaffIds, true) ? 'selected' : ''; ?>>
                                            <?php echo html_escape(trim((string) (($staff['firstname'] ?? '') . ' ' . ($staff['lastname'] ?? ''))) ?: ($staff['email'] ?? ('Nhân sự #' . $staffId))); ?>
                                            <?php echo !empty($staff['admin']) ? ' [quản trị]' : ''; ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>

                            <hr />
                            <p class="text-muted">Phân quyền quản trị nhân sự, vai trò và phòng ban chỉ áp dụng trong doanh nghiệp này, không mở các trang cấu hình của hệ thống trung tâm.</p>
                            <?php if (!$canViewGovernance) { ?>
                                <div class="alert alert-warning">Gói hiện tại chưa cho phép dùng mục quản trị nhân sự và phân quyền.</div>
                            <?php } else { ?>
                                <?php if (!$canManageGovernance) { ?>
                                    <div class="alert alert-info">Gói hiện tại chỉ cho phép xem mục quản trị nhân sự và phân quyền.</div>
                                <?php } ?>
                                <div class="form-group">
                                    <label for="workspace_governance_view_staff_ids" class="control-label">Nhân sự được xem</label>
                                    <select name="workspace_governance_view_staff_ids[]" id="workspace_governance_view_staff_ids" class="form-control" multiple size="6" <?php echo !$canManageGovernance ? 'disabled' : ''; ?>>
                                        <?php foreach ($staffMembers as $staff) { ?>
                                            <?php $staffId = (int) ($staff['staffid'] ?? 0); ?>
                                            <option value="<?php echo $staffId; ?>" <?php echo in_array($staffId, $selectedGovernanceViewStaffIds, true) ? 'selected' : ''; ?>>
                                                <?php echo html_escape(trim((string) (($staff['firstname'] ?? '') . ' ' . ($staff['lastname'] ?? ''))) ?: ($staff['email'] ?? ('Nhân sự #' . $staffId))); ?>
                                                <?php echo !empty($staff['admin']) ? ' [quản trị]' : ''; ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="workspace_governance_manage_staff_ids" class="control-label">Nhân sự được quản lý</label>
                                    <select name="workspace_governance_manage_staff_ids[]" id="workspace_governance_manage_staff_ids" class="form-control" multiple size="6" <?php echo !$canManageGovernance ? 'disabled' : ''; ?>>
                                        <?php foreach ($staffMembers as $staff) { ?>
                                            <?php $staffId = (int) ($staff['staffid'] ?? 0); ?>
                                            <option value="<?php echo $staffId; ?>" <?php echo in_array($staffId, $selectedGovernanceManageStaffIds, true) ? 'selected' : ''; ?>>
                                                <?php echo html_escape(trim((string) (($staff['firstname'] ?? '') . ' ' . ($staff['lastname'] ?? ''))) ?: ($staff['email'] ?? ('Nhân sự #' . $staffId))); ?>
                                                <?php echo !empty($staff['admin']) ? ' [quản trị]' : ''; ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            <?php } ?>

                            <div class="alert alert-info">
                                Cài đặt doanh nghiệp này vẫn tách biệt khỏi các mục chỉ dành cho hệ thống trung tâm.
                            </div>

                            <div class="text-right">
                                <button type="submit" class="btn btn-primary" <?php echo !$canManageGovernance ? 'disabled' : ''; ?>>Lưu truy cập</button>
                            </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
                </div>
            </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
if (typeof init_editor === 'function') {
    if (<?php echo $canEditMailIdentity ? 'true' : 'false'; ?>) {
        init_editor('.tinymce-tenant-email-signature,.tinymce-tenant-email-header,.tinymce-tenant-email-footer');
    }
}
document.querySelectorAll('[data-toggle-target]').forEach(function (toggle) {
    function refreshToggleTarget() {
        var target = document.querySelector(toggle.getAttribute('data-toggle-target'));
        if (!target) {
            return;
        }
        target.style.display = toggle.checked ? '' : 'none';
    }
    toggle.addEventListener('change', refreshToggleTarget);
    refreshToggleTarget();
});
document.querySelectorAll('[data-preview-input]').forEach(function (input) {
    input.addEventListener('change', function () {
        var key = input.getAttribute('data-preview-input');
        var file = input.files && input.files[0] ? input.files[0] : null;
        if (!file || !file.type.match(/^image\//)) {
            return;
        }

        var target = document.querySelector('[data-preview-target="' + key + '"]');
        var emptyPreview = document.querySelector('[data-empty-preview="' + key + '"]');
        if (!target) {
            target = document.createElement('img');
            target.setAttribute('data-preview-target', key);
            target.setAttribute('style', key === 'favicon' ? 'max-height:32px;max-width:32px;' : 'max-height:50px;max-width:220px;');
            target.setAttribute('alt', 'Preview');
            input.parentNode.insertBefore(target, input);
        }

        if (emptyPreview) {
            emptyPreview.style.display = 'none';
        }

        var reader = new FileReader();
        reader.onload = function (event) {
            target.src = event.target.result;
            target.classList.add('tw-mb-3');
        };
        reader.readAsDataURL(file);
    });
});

(function () {
    var providerEl = document.getElementById('tenant_email_provider');
    if (!providerEl) {
        return;
    }

    var groups = document.querySelectorAll('.tenant-email-provider-group');
    function setInputsEnabled(group, enabled) {
        var inputs = group.querySelectorAll('input, select, textarea');
        for (var i = 0; i < inputs.length; i++) {
            inputs[i].disabled = !enabled;
        }
    }

    function refreshEmailProviderGroups() {
        var provider = providerEl.value || 'system_smtp';
        for (var i = 0; i < groups.length; i++) {
            var group = groups[i];
            var targetProvider = group.getAttribute('data-provider-group');
            var show = targetProvider === provider;
            group.style.display = show ? '' : 'none';
            setInputsEnabled(group, show);
        }
    }

    providerEl.addEventListener('change', refreshEmailProviderGroups);
    refreshEmailProviderGroups();
})();
</script>
