<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="panel_s">
            <div class="panel-body">
                <h4><?php echo html_escape($title); ?></h4>

                <?php echo form_open(admin_url('kt_matbao_invoice/tenant/settings')); ?>
                <?php echo render_select('environment', [['id' => 'demo', 'name' => 'Môi trường thử nghiệm'], ['id' => 'production', 'name' => 'Môi trường thật']], ['id', 'name'], 'Môi trường sử dụng', $settings['environment'] ?? 'demo'); ?>
                <?php echo render_input('invoice_base_url', 'Địa chỉ kết nối hóa đơn điện tử', $settings['invoice_base_url'] ?? 'https://demo-api-hddt.matbao.in:11443'); ?>
                <?php echo render_input('mst', 'Mã số thuế', $settings['mst'] ?? ''); ?>
                <?php echo render_input('username', 'Tên đăng nhập', $settings['username'] ?? ''); ?>
                <?php echo render_input('password', 'Mật khẩu (để trống nếu không đổi)', '', 'password'); ?>

                <hr>
                <h5>Cấu hình chữ ký số</h5>
                <?php echo render_input('ca_base_url', 'Địa chỉ kết nối ký số', $ca_settings['base_url'] ?? ($settings['sign_base_url'] ?? 'https://demo-api-econtract-mbc.matbao.in')); ?>
                <input type="hidden" name="sign_base_url" value="<?php echo html_escape($ca_settings['base_url'] ?? ($settings['sign_base_url'] ?? 'https://demo-api-econtract-mbc.matbao.in')); ?>">
                <?php echo render_input('ca_taxcode', 'Mã số thuế ký số', $ca_settings['taxcode'] ?? ''); ?>
                <?php echo render_input('ca_username', 'Tên đăng nhập ký số', $ca_settings['username'] ?? ''); ?>
                <?php echo render_input('ca_password', 'Mật khẩu ký số (để trống nếu không đổi)', '', 'password'); ?>
                <?php echo render_select('signing_mode', [['id' => 'hddt_sign_invoice', 'name' => 'Ký trực tiếp trên hóa đơn điện tử'], ['id' => 'get_xml_then_ca_sign', 'name' => 'Lấy XML rồi ký số'], ['id' => 'manual', 'name' => 'Thủ công']], ['id', 'name'], 'Cách ký hóa đơn', $ca_settings['signing_mode'] ?? 'hddt_sign_invoice'); ?>

                <div class="checkbox checkbox-primary">
                    <input type="checkbox" id="ca_is_active" name="ca_is_active" value="1" <?php echo !empty($ca_settings['is_active']) ? 'checked' : ''; ?>>
                    <label for="ca_is_active">Bật chữ ký số</label>
                </div>
                <div class="checkbox checkbox-primary">
                    <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo !empty($settings['is_active']) ? 'checked' : ''; ?>>
                    <label for="is_active">Đang sử dụng</label>
                </div>

                <button type="submit" class="btn btn-primary">Lưu cấu hình</button>
                <?php echo form_close(); ?>

                <?php echo form_open(admin_url('kt_matbao_invoice/tenant/test_connection'), ['class' => 'mtop10', 'style' => 'display:inline-block;margin-right:8px;']); ?>
                    <button type="submit" class="btn btn-info">Kiểm tra kết nối hóa đơn điện tử</button>
                <?php echo form_close(); ?>

                <?php echo form_open(admin_url('kt_matbao_invoice/tenant/test_ca_connection'), ['class' => 'mtop10', 'style' => 'display:inline-block;']); ?>
                    <button type="submit" class="btn btn-default">Kiểm tra kết nối chữ ký số</button>
                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
