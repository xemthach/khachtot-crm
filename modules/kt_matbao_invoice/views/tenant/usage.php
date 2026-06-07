<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="panel_s">
            <div class="panel-body">
                <h4><?php echo html_escape($title); ?></h4>
                <div class="row">
                    <div class="col-md-3"><div class="well"><strong>Số hóa đơn điện tử đã mua</strong><br><?php echo (float) ($summary['einvoice_total'] ?? 0); ?></div></div>
                    <div class="col-md-3"><div class="well"><strong>Đã dùng</strong><br><?php echo (float) ($summary['einvoice_used'] ?? 0); ?></div></div>
                    <div class="col-md-3"><div class="well"><strong>Còn lại</strong><br><?php echo (float) ($summary['einvoice_remaining'] ?? 0); ?></div></div>
                    <div class="col-md-3"><div class="well"><strong>Chữ ký số đang hoạt động</strong><br><?php echo (int) ($summary['hsm_active'] ?? 0); ?></div></div>
                </div>
                <p class="mtop10"><a class="btn btn-primary" href="<?php echo admin_url('kt_matbao_invoice/tenant/addons'); ?>">Mua thêm gói / quản lý dịch vụ</a></p>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
