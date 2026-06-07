<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="panel_s"><div class="panel-body">
<h4><?php echo html_escape($title); ?></h4>
<?php echo form_open(admin_url('kt_matbao_invoice/settings')); ?>
<?php echo render_select('environment', [['id'=>'demo','name'=>'Thử nghiệm'],['id'=>'production','name'=>'Thật']], ['id','name'], 'Môi trường', $settings['environment'] ?? 'demo'); ?>
<?php echo render_input('invoice_base_url', 'Địa chỉ kết nối hóa đơn', $settings['invoice_base_url'] ?? 'https://demo-api-hddt.matbao.in:11443'); ?>
<?php echo render_input('mst', 'MST', $settings['mst'] ?? ''); ?>
<?php echo render_input('username', 'Tên đăng nhập', $settings['username'] ?? ''); ?>
<?php echo render_input('password', 'Mật khẩu (để trống nếu giữ nguyên)', '', 'password'); ?>
<?php echo render_input('default_khmshdon', 'Default KHMSHDon', $settings['default_khmshdon'] ?? ''); ?>
<?php echo render_input('default_khhdon', 'Default KHHDon', $settings['default_khhdon'] ?? ''); ?>
<?php echo render_input('default_year', 'Năm mặc định', $settings['default_year'] ?? date('Y'), 'number'); ?>
<div class="checkbox checkbox-primary"><input type="checkbox" id="shared_account_enabled" name="shared_account_enabled" value="1" <?php echo !empty($settings['shared_account_enabled']) ? 'checked' : ''; ?>><label for="shared_account_enabled">Cho phép tenant dùng chung tài khoản landlord</label></div>
<div class="checkbox checkbox-primary"><input type="checkbox" id="allow_tenant_override" name="allow_tenant_override" value="1" <?php echo !empty($settings['allow_tenant_override']) ? 'checked' : ''; ?>><label for="allow_tenant_override">Cho phép tenant dùng cấu hình riêng</label></div>
<div class="checkbox checkbox-primary"><input type="checkbox" id="is_active" name="is_active" value="1" <?php echo !empty($settings['is_active']) ? 'checked' : ''; ?>><label for="is_active">Đang hoạt động</label></div>
<hr>
<h5>Cấu hình MatBaoCA / HSM</h5>
<?php echo render_input('ca_base_url', 'Địa chỉ kết nối CA', $ca_settings['base_url'] ?? ($settings['sign_base_url'] ?? 'https://demo-api-econtract-mbc.matbao.in')); ?>
<input type="hidden" name="sign_base_url" value="<?php echo html_escape($ca_settings['base_url'] ?? ($settings['sign_base_url'] ?? 'https://demo-api-econtract-mbc.matbao.in')); ?>">
<?php echo render_input('ca_taxcode', 'Mã số thuế CA', $ca_settings['taxcode'] ?? ''); ?>
<?php echo render_input('ca_username', 'Tên đăng nhập CA', $ca_settings['username'] ?? ''); ?>
<?php echo render_input('ca_password', 'Mật khẩu CA (để trống nếu giữ nguyên)', '', 'password'); ?>
<?php echo render_select('signing_mode', [['id'=>'hddt_sign_invoice','name'=>'Ký trực tiếp trên hệ thống HĐĐT'],['id'=>'get_xml_then_ca_sign','name'=>'Lấy XML rồi ký CA/HSM'],['id'=>'manual','name'=>'Thủ công']], ['id','name'], 'Chế độ ký', $ca_settings['signing_mode'] ?? 'hddt_sign_invoice'); ?>
<div class="checkbox checkbox-primary"><input type="checkbox" id="ca_is_active" name="ca_is_active" value="1" <?php echo !empty($ca_settings['is_active']) ? 'checked' : ''; ?>><label for="ca_is_active">Bật CA/HSM</label></div>
<?php echo render_input('webhook_secret', 'Mã xác thực kết nối (Header X-KT-MatBao-Secret)', $webhook_secret ?? ''); ?>
<button type="submit" class="btn btn-primary">Lưu</button>
<a class="btn btn-default" href="<?php echo admin_url('kt_matbao_invoice/templates'); ?>">Mẫu hóa đơn</a>
<?php echo form_close(); ?>
<hr>
<p>Lần kiểm tra gần nhất: <?php echo html_escape($settings['last_test_status'] ?? '-'); ?> - <?php echo html_escape($settings['last_test_message'] ?? '-'); ?></p>
<?php echo form_open(admin_url('kt_matbao_invoice/test_connection'), ['style' => 'display:inline-block;margin-right:8px;']); ?><button type="submit" class="btn btn-info">Kiểm tra kết nối HĐĐT</button><?php echo form_close(); ?>
<?php echo form_open(admin_url('kt_matbao_invoice/test_ca_connection'), ['style' => 'display:inline-block;']); ?><button type="submit" class="btn btn-default">Kiểm tra kết nối CA/HSM</button><?php echo form_close(); ?>
</div></div></div></div>
<?php init_tail(); ?>
