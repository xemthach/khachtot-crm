<?php defined('BASEPATH') or exit('No direct script access allowed'); init_head(); ?>
<div id="wrapper"><div class="content">
  <div class="row"><div class="col-md-8"><h4 class="tw-mt-0">Mua / thêm workspace DMS</h4><p class="text-muted">Tạo yêu cầu thương mại có mã theo dõi; Salesrep chỉ khởi tạo sau khi CRM xác nhận quyền sử dụng.</p></div><div class="col-md-4 text-right"><a class="btn btn-default" href="<?php echo e(admin_url('kt_integration_hub/dms_workspaces')); ?>">Danh sách workspace</a></div></div>
  <?php if (!empty($last_request)) { ?><div class="alert alert-<?php echo !empty($last_request['success'])?'success':'warning'; ?>"><strong><?php echo e($last_request['message'] ?? 'Đã ghi nhận yêu cầu.'); ?></strong><br>Mã theo dõi: <code><?php echo e($last_request['correlation_id'] ?? ''); ?></code><br>Trạng thái: <?php echo e($last_request['status'] ?? ''); ?></div><?php } ?>
  <?php if (!empty($availability_result)) { ?><div class="alert alert-<?php echo !empty($availability_result['available'])?'success':'warning'; ?>"><strong><?php echo e($availability_result['message'] ?? 'Đã kiểm tra subdomain.'); ?></strong><br>Trạng thái: <code><?php echo e($availability_result['status'] ?? 'unavailable'); ?></code><br>Mã theo dõi: <code><?php echo e($availability_result['correlation_id'] ?? ''); ?></code></div><?php } ?>
  <div class="panel_s"><div class="panel-heading">Kiểm tra subdomain trước khi gửi yêu cầu</div><div class="panel-body">
    <?php echo form_open(admin_url('kt_integration_hub/dms_domain_availability'), ['class'=>'form-inline']); ?>
      <input type="hidden" name="domain_type" value="platform_subdomain">
      <div class="form-group"><label class="sr-only">Subdomain DMS</label><input class="form-control" name="requested_domain" required placeholder="mien-nam.salesrep.test"></div>
      <button class="btn btn-default" type="submit">Kiểm tra subdomain</button>
    <?php echo form_close(); ?>
    <p class="text-muted tw-mt-2 tw-mb-0">Kết quả được kiểm tra bằng quy tắc định dạng, danh sách dành riêng và dữ liệu domain/workspace thật trên Salesrep.</p>
  </div></div>
  <div class="row"><div class="col-md-7"><div class="panel_s"><div class="panel-heading">Thông tin workspace</div><div class="panel-body">
    <?php echo form_open(admin_url('kt_integration_hub/dms_workspace_request')); ?>
      <div class="form-group"><label>Tên workspace</label><input class="form-control" name="workspace_name" required maxlength="191" placeholder="Ví dụ: Đội bán hàng miền Nam"></div>
      <div class="form-group"><label>Mã workspace</label><input class="form-control" name="workspace_slug" required pattern="[a-z0-9][a-z0-9-]{2,62}" placeholder="mien-nam"></div>
      <div class="form-group"><label>Subdomain DMS</label><input class="form-control" name="workspace_host" required placeholder="mien-nam.salesrep.test"></div>
      <div class="form-group"><label>Gói DMS hiện tại</label><input class="form-control" value="<?php echo e($package_code ?: 'Chưa xác định'); ?>" readonly></div>
      <button class="btn btn-primary" type="submit">Gửi yêu cầu workspace</button>
    <?php echo form_close(); ?>
  </div></div></div><div class="col-md-5"><div class="panel_s"><div class="panel-heading">Điều kiện thương mại</div><div class="panel-body">
    <?php if (!$multi_workspace_enabled && count($workspaces)>0) { ?><div class="alert alert-warning"><strong>Gói hiện tại chưa cho phép nhiều workspace.</strong><br>Yêu cầu vẫn được ghi nhận với mã theo dõi để CRM xử lý phê duyệt; Salesrep không tạo workspace giả.</div><?php } else { ?><div class="alert alert-success">Gói hiện tại cho phép gửi lệnh khởi tạo có chữ ký.</div><?php } ?>
    <p>Workspace hiện có: <strong><?php echo count($workspaces); ?></strong></p><a class="btn btn-default" href="<?php echo e(admin_url('kt_saas/marketplace')); ?>">Xem gói DMS</a>
  </div></div></div></div>
</div></div><?php init_tail(); ?>
