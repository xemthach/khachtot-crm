<?php defined('BASEPATH') or exit('No direct script access allowed'); init_head(); ?>
<div id="wrapper"><div class="content">
  <div class="tw-flex tw-items-start tw-justify-between tw-gap-4 tw-mb-4">
    <div><h4 class="tw-m-0">Workspace DMS</h4><p class="text-muted tw-mt-2">Chọn workspace để mở hoặc kiểm tra gói, domain và trạng thái quyền sử dụng.</p></div>
    <a class="btn btn-primary" href="<?php echo e(admin_url('kt_integration_hub/dms_workspace_add')); ?>">Thêm workspace</a>
  </div>
  <?php if (empty($workspaces)) { ?>
    <div class="panel_s"><div class="panel-body text-center tw-py-8"><h4>Chưa có workspace DMS</h4><p class="text-muted">Bắt đầu từ gói DMS đang được cấp cho doanh nghiệp.</p><a class="btn btn-primary" href="<?php echo e(admin_url('kt_integration_hub/dms_workspace_add')); ?>">Mua / thêm workspace</a></div></div>
  <?php } else { ?><div class="row">
    <?php foreach ($workspaces as $workspace) { ?><div class="col-md-6"><div class="panel_s"><div class="panel-body">
      <div class="tw-flex tw-justify-between tw-gap-3"><div><h4 class="tw-mt-0"><?php echo e($workspace['workspace_name']); ?></h4><div class="text-muted"><?php echo e($workspace['workspace_host']); ?></div></div><?php if (!empty($workspace['is_primary'])) { ?><span class="label label-info">Workspace chính</span><?php } ?></div>
      <dl class="tw-grid tw-grid-cols-2 tw-gap-3 tw-mt-4"><div><dt>Gói</dt><dd><?php echo e($workspace['package_code'] ?: 'Chưa có bản sao'); ?></dd></div><div><dt>Quyền sử dụng</dt><dd><?php echo e($workspace['entitlement_status'] ?: 'Chưa xác định'); ?></dd></div><div><dt>Domain</dt><dd><?php echo e($workspace['domain_status'] ?: 'Chưa kiểm tra'); ?></dd></div><div><dt>Đồng bộ gần nhất</dt><dd><?php echo e($workspace['last_synced_at'] ?: 'Chưa ghi nhận'); ?></dd></div></dl>
      <a class="btn btn-primary tw-mt-3" href="<?php echo e(admin_url('kt_integration_hub/dms_open?workspace_id=' . (int) $workspace['id'])); ?>">Mở DMS</a>
      <a class="btn btn-default tw-mt-3" href="<?php echo e(admin_url('kt_integration_hub/dms_domains?workspace_id=' . (int) $workspace['id'])); ?>">Quản lý domain</a>
    </div></div></div><?php } ?>
  </div><?php } ?>
</div></div><?php init_tail(); ?>
