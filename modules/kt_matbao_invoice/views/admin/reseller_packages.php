<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="panel_s"><div class="panel-body">
<h4><?php echo html_escape($title); ?></h4>
<?php echo form_open(admin_url('kt_matbao_invoice/reseller_packages')); ?>
<div class="row">
<div class="col-md-2"><?php echo render_input('package_code', 'Mã gói'); ?></div>
<div class="col-md-3"><?php echo render_input('package_name', 'Tên gói'); ?></div>
<div class="col-md-2"><?php echo render_select('service_type', [['id'=>'einvoice','name'=>'Hóa đơn điện tử'],['id'=>'hsm_signature','name'=>'Chữ ký số HSM']], ['id','name'], 'Loại dịch vụ', 'einvoice'); ?></div>
<div class="col-md-2"><?php echo render_input('quantity', 'Số lượng', '1', 'number', ['step'=>'0.01']); ?></div>
<div class="col-md-2"><?php echo render_input('price', 'Đơn giá', '0', 'number', ['step'=>'0.01']); ?></div>
<div class="col-md-1"><label>&nbsp;</label><button type="submit" class="btn btn-primary btn-block">Lưu</button></div>
</div>
<?php echo form_close(); ?>
<hr>
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>ID</th><th>Mã gói</th><th>Tên gói</th><th>Loại dịch vụ</th><th>Số lượng</th><th>Đơn giá</th><th>Trạng thái</th></tr></thead><tbody>
<?php foreach(($packages??[]) as $p){ ?><tr><td><?php echo (int)$p['id']; ?></td><td><?php echo html_escape($p['package_code']); ?></td><td><?php echo html_escape($p['package_name']); ?></td><td><?php echo html_escape(($p['service_type'] ?? '') === 'hsm_signature' ? 'Chữ ký số HSM' : 'Hóa đơn điện tử'); ?></td><td><?php echo app_format_number((float)($p['quantity'] ?? 0)); ?></td><td><?php echo app_format_money((float)($p['price'] ?? 0), (string)($p['currency'] ?? 'VND'), true) . ' ' . html_escape((string)($p['currency'] ?? 'VND')); ?></td><td><?php echo !empty($p['is_active'])?'Đang hoạt động':'Ngừng sử dụng'; ?></td></tr><?php } ?>
</tbody></table></div>
</div></div></div></div>
<?php init_tail(); ?>
