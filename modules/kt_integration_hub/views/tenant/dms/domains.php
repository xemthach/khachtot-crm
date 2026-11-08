<?php defined('BASEPATH') or exit('No direct script access allowed'); init_head(); ?>
<div id="wrapper"><div class="content"><h4>Domain DMS</h4><p class="text-muted">Yêu cầu subdomain hoặc custom domain cho đúng workspace. Salesrep xác minh quyền sở hữu và trả hướng dẫn DNS có chữ ký.</p>
<?php if (!empty($last_request)) { ?><div class="alert alert-<?php echo !empty($last_request['success'])?'success':'warning'; ?>"><strong><?php echo e($last_request['message'] ?? 'Đã ghi nhận yêu cầu domain.'); ?></strong><br>Mã theo dõi: <code><?php echo e($last_request['correlation_id'] ?? ''); ?></code><br>Trạng thái: <?php echo e($last_request['status'] ?? ''); ?>
<?php foreach ((array)($last_request['dns_instructions']??[]) as $dns) { ?><div class="tw-mt-2"><strong>Hướng dẫn DNS:</strong> <?php echo e($dns['type']??''); ?> · <?php echo e($dns['name']??''); ?> → <code><?php echo e($dns['value']??''); ?></code> · TTL <?php echo (int)($dns['ttl']??300); ?> giây</div><?php } ?>
<?php if (!empty($last_request['verification_blocker'])) { ?><div class="tw-mt-2 text-warning">Chưa thể phát hướng dẫn DNS: <code><?php echo e($last_request['verification_blocker']); ?></code>.</div><?php } ?>
<div class="tw-mt-2 text-muted">Nếu dùng Cloudflare, hãy để proxy ở chế độ chỉ DNS trong lúc xác minh. Thay đổi DNS có thể cần thời gian để lan truyền.</div></div><?php } ?>
<?php if (!empty($last_check)) { ?><div class="alert alert-<?php echo !empty($last_check['success'])?'success':'warning'; ?>"><strong><?php echo e($last_check['message'] ?? 'Đã kiểm tra kết nối domain.'); ?></strong><br>Trạng thái: <code><?php echo e($last_check['status'] ?? 'pending_dns'); ?></code><br>Mã lỗi: <code><?php echo e($last_check['code'] ?? ''); ?></code><br>Mã theo dõi: <code><?php echo e($last_check['correlation_id'] ?? ''); ?></code></div><?php } ?>
<?php if (!empty($last_request['domain_id']) && !empty($last_request['workspace_id'])) { ?>
  <?php echo form_open(admin_url('kt_integration_hub/dms_domain_check'), ['class'=>'tw-mb-4']); ?>
    <input type="hidden" name="domain_id" value="<?php echo (int)$last_request['domain_id']; ?>"><input type="hidden" name="workspace_id" value="<?php echo (int)$last_request['workspace_id']; ?>">
    <button class="btn btn-info" type="submit">Kiểm tra kết nối DNS</button>
  <?php echo form_close(); ?>
<?php } ?>
<?php if (empty($workspaces)) { ?><div class="alert alert-warning"><strong>Chưa có workspace để cấu hình domain.</strong><br>Hãy hoàn tất quyền sử dụng và khởi tạo workspace trước.</div><?php } else { ?>
<div class="panel_s"><div class="panel-heading">Gửi yêu cầu domain</div><div class="panel-body"><?php echo form_open(admin_url('kt_integration_hub/dms_domain_request')); ?>
  <div class="form-group"><label>Workspace</label><select class="form-control" name="workspace_id" required><?php foreach($workspaces as $w){ ?><option value="<?php echo (int)$w['id']; ?>"><?php echo e($w['workspace_name'].' · '.$w['workspace_host']); ?></option><?php } ?></select></div>
  <div class="form-group"><label>Loại domain</label><select class="form-control" name="domain_type"><option value="platform_subdomain">Subdomain nền tảng</option><option value="custom_domain">Custom domain</option></select></div>
  <div class="form-group"><label>Domain yêu cầu</label><input class="form-control" name="requested_domain" required placeholder="doi-ban-hang.salesrep.test hoặc dms.doanhnghiep.vn"></div>
  <input type="hidden" name="verification_method" value="txt"><button class="btn btn-primary" type="submit">Gửi yêu cầu và nhận hướng dẫn DNS</button>
<?php echo form_close(); ?></div></div>
<?php foreach($workspaces as $w){ ?><div class="panel_s"><div class="panel-body"><h4 class="tw-mt-0"><?php echo e($w['workspace_name']); ?></h4><p><strong>Domain hiện tại:</strong> <?php echo e($w['workspace_host']); ?></p><p><strong>Trạng thái:</strong> <?php echo e($w['domain_status'] ?: 'Chưa kiểm tra'); ?></p></div></div><?php } } ?>
</div></div><?php init_tail(); ?>
