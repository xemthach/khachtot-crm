<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$activeTab = (string) ($active_tab ?? 'today');
$actions = is_array($actions ?? null) ? $actions : [];
$summaryCards = is_array($summary_cards ?? null) ? $summary_cards : [];
$checkins = is_array($checkins ?? null) ? $checkins : [];
$visits = is_array($visits ?? null) ? $visits : [];
$notifications = is_array($notifications ?? null) ? $notifications : [];
$emptyState = is_array($empty_state ?? null) ? $empty_state : [];
$tabs = [
    'today' => 'Hôm nay',
    'checkins' => 'Check-in',
    'visits' => 'Lượt ghé',
    'exceptions' => 'Ngoại lệ',
    'staff' => 'Nhân sự',
    'notifications' => 'Thông báo',
];
$statusBadge = static function ($value) {
    $v = strtolower(trim((string) $value));
    if (in_array($v, ['valid', 'completed', 'done', 'synced', 'read'], true)) {
        return 'success';
    }
    if (in_array($v, ['warning', 'partial', 'pending', 'new', 'open'], true)) {
        return 'warning';
    }
    if (in_array($v, ['invalid', 'cancelled', 'failed', 'rejected'], true)) {
        return 'danger';
    }
    return 'default';
};
$hasData = !empty($checkins) || !empty($visits) || !empty($notifications);
?>
<div id="wrapper"><div class="content">
  <div class="row">
    <div class="col-md-8">
      <h4 class="tw-mb-1">Check-in / Lượt ghé</h4>
      <p class="text-muted">Theo dõi check-in, lượt ghé, ngoại lệ và hoạt động đội sales trong một màn hình.</p>
    </div>
    <div class="col-md-4 text-right">
      <?php if (!empty($actions['open_dms_url']) && ($actions['open_dms_mode'] ?? 'disabled') !== 'disabled') { ?>
        <a href="<?php echo e($actions['open_dms_url']); ?>" class="btn btn-primary">Mở app DMS</a>
      <?php } ?>
      <a href="<?php echo e(admin_url('kt_integration_hub/dms_sync_health')); ?>" class="btn btn-default">Đồng bộ</a>
    </div>
  </div>

  <div class="row">
    <?php foreach ($summaryCards as $card) { ?>
      <div class="col-md-4 col-sm-6"><div class="panel_s"><div class="panel-body">
        <div class="text-uppercase text-muted small"><?php echo e((string) ($card['label'] ?? '')); ?></div>
        <div class="tw-text-2xl tw-font-semibold tw-mt-2"><?php echo e((string) ($card['value'] ?? 0)); ?></div>
        <div class="text-muted small tw-mt-1"><?php echo e((string) ($card['detail'] ?? '')); ?></div>
      </div></div></div>
    <?php } ?>
  </div>

  <div class="panel_s"><div class="panel-body">
    <ul class="nav nav-tabs">
      <?php foreach ($tabs as $key => $label) { ?>
        <li class="<?php echo $activeTab === $key ? 'active' : ''; ?>">
          <a href="<?php echo e(admin_url('kt_integration_hub/dms_field_activity?tab=' . $key)); ?>"><?php echo e($label); ?></a>
        </li>
      <?php } ?>
    </ul>
  </div></div>

  <?php if (!$hasData) { ?>
    <div class="panel_s"><div class="panel-body">
      <div class="alert alert-info">
        <h5 class="tw-mt-0"><?php echo e($emptyState['title'] ?? 'Chưa có hoạt động thực địa.'); ?></h5>
        <p><?php echo e($emptyState['reason'] ?? ''); ?></p>
        <ol>
          <li>Mở DMS.</li>
          <li>Chọn điểm bán.</li>
          <li>Tạo check-in hoặc hoàn tất lượt ghé.</li>
          <li>Quay lại CRM để xem dữ liệu đồng bộ.</li>
        </ol>
        <?php if (!empty($actions['open_dms_url'])) { ?><a class="btn btn-primary" href="<?php echo e($actions['open_dms_url']); ?>">Mở DMS</a><?php } ?>
      </div>
    </div></div>
  <?php } ?>

  <?php if ($activeTab === 'today' || $activeTab === 'checkins') { ?>
    <div class="panel_s"><div class="panel-heading">Check-in</div><div class="panel-body">
      <?php if (empty($checkins)) { ?>
        <div class="alert alert-info tw-mb-0">Chưa có check-in được đồng bộ.</div>
      <?php } else { ?>
        <div class="table-responsive"><table class="table table-striped">
          <thead><tr><th>Nhân viên</th><th>Điểm bán / khách hàng</th><th>Thời gian</th><th>Trạng thái</th><th>GPS / bằng chứng</th><th>Đồng bộ</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($checkins as $row) { ?>
            <tr>
              <td><?php echo e((string) ($row['staff_name'] ?? $row['crm_staff_id'] ?? '-')); ?></td>
              <td><?php echo e((string) ($row['customer_name'] ?? $row['crm_customer_id'] ?? '-')); ?><div class="text-muted small">Điểm bán: <?php echo e((string) ($row['store_id'] ?? '-')); ?></div></td>
              <td><?php echo e((string) ($row['checkin_at'] ?? '-')); ?></td>
              <td><span class="label label-<?php echo $statusBadge($row['status'] ?? ''); ?>"><?php echo e(kt_dms_ui_status_label('checkin', $row['status'] ?? '')); ?></span></td>
              <td><?php echo e(trim((string) ($row['latitude'] ?? '') . ', ' . (string) ($row['longitude'] ?? ''), ', ') ?: 'Không có vị trí GPS'); ?></td>
              <td>
                <span class="label label-<?php echo $statusBadge($row['sync_status'] ?? ''); ?>"><?php echo e(kt_dms_ui_status_label('sync', $row['sync_status'] ?? 'synced')); ?></span>
                <?php if (!empty($row['sync_error'])) { ?><div class="text-danger small">Đồng bộ chưa thành công. Vui lòng thử lại.</div><?php } ?>
              </td>
              <td>
                <?php if (($row['sync_status'] ?? '') === 'failed' && !empty($row['sync_job_id'])) { ?>
                  <?php echo form_open(admin_url('kt_integration_hub/dms_retry_bridge_record/checkin/' . (int) $row['sync_job_id']), ['class' => 'tw-inline']); ?>
                  <button type="submit" class="btn btn-default btn-xs">Thử lại</button>
                  <?php echo form_close(); ?>
                <?php } ?>
              </td>
            </tr>
          <?php } ?>
          </tbody>
        </table></div>
      <?php } ?>
    </div></div>
  <?php } ?>

  <?php if ($activeTab === 'today' || $activeTab === 'visits') { ?>
    <div class="panel_s"><div class="panel-heading">Lượt ghé</div><div class="panel-body">
      <?php if (empty($visits)) { ?>
        <div class="alert alert-info tw-mb-0">Chưa có lượt ghé được đồng bộ. Hoàn tất công việc trong DMS để CRM hiển thị dữ liệu.</div>
      <?php } else { ?>
        <div class="table-responsive"><table class="table table-striped">
          <thead><tr><th>Nhân viên</th><th>Điểm bán / khách hàng</th><th>Bắt đầu</th><th>Kết thúc</th><th>Trạng thái</th><th>Đồng bộ</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($visits as $row) { ?>
            <tr>
              <td><?php echo e((string) ($row['staff_name'] ?? $row['crm_staff_id'] ?? '-')); ?></td>
              <td><?php echo e((string) ($row['customer_name'] ?? $row['crm_customer_id'] ?? '-')); ?><div class="text-muted small">Điểm bán: <?php echo e((string) ($row['store_id'] ?? '-')); ?></div></td>
              <td><?php echo e((string) ($row['started_at'] ?? '-')); ?></td>
              <td><?php echo e((string) ($row['ended_at'] ?? '-')); ?></td>
              <td><span class="label label-<?php echo $statusBadge($row['visit_status'] ?? ''); ?>"><?php echo e(kt_dms_ui_status_label('visit', $row['visit_status'] ?? '')); ?></span></td>
              <td>
                <span class="label label-<?php echo $statusBadge($row['sync_status'] ?? ''); ?>"><?php echo e(kt_dms_ui_status_label('sync', $row['sync_status'] ?? 'synced')); ?></span>
                <?php if (!empty($row['sync_error'])) { ?><div class="text-danger small">Đồng bộ chưa thành công. Vui lòng thử lại.</div><?php } ?>
              </td>
              <td>
                <?php if (($row['sync_status'] ?? '') === 'failed' && !empty($row['sync_job_id'])) { ?>
                  <?php echo form_open(admin_url('kt_integration_hub/dms_retry_bridge_record/visit/' . (int) $row['sync_job_id']), ['class' => 'tw-inline']); ?>
                  <button type="submit" class="btn btn-default btn-xs">Thử lại</button>
                  <?php echo form_close(); ?>
                <?php } ?>
              </td>
            </tr>
          <?php } ?>
          </tbody>
        </table></div>
      <?php } ?>
    </div></div>
  <?php } ?>

  <?php if ($activeTab === 'exceptions') { ?>
    <div class="panel_s"><div class="panel-heading">Ngoại lệ</div><div class="panel-body">
      <p class="text-muted">Ngoại lệ gồm check-in cần chú ý, lượt ghé bị hủy hoặc chưa hoàn tất và bản ghi đồng bộ lỗi.</p>
      <a href="<?php echo e(admin_url('kt_integration_hub/dms_sync_health')); ?>" class="btn btn-default">Mở Đồng bộ & Sức khỏe</a>
    </div></div>
  <?php } ?>

  <?php if ($activeTab === 'staff') { ?>
    <div class="panel_s"><div class="panel-heading">Hoạt động nhân sự</div><div class="panel-body">
      <p class="text-muted">Dùng Tổng quan và Thiết lập & Truy cập để xem nhân sự sẵn sàng, cấu trúc quản lý và trạng thái đồng bộ. Màn hình DMS hiển thị chi tiết đội sales theo vai trò.</p>
    </div></div>
  <?php } ?>

  <?php if ($activeTab === 'notifications') { ?>
    <div class="panel_s"><div class="panel-heading">Thông báo</div><div class="panel-body">
      <?php if (empty($notifications)) { ?>
        <div class="alert alert-info tw-mb-0">Chưa có thông báo vận hành.</div>
      <?php } else { ?>
        <table class="table table-striped"><tbody>
        <?php foreach ($notifications as $row) { ?>
          <tr>
            <td><strong><?php echo e((string) ($row['title'] ?? '-')); ?></strong><div class="text-muted small"><?php echo e((string) ($row['message'] ?? '')); ?></div></td>
            <td><?php echo e((string) ($row['priority'] ?? '-')); ?></td>
            <td><?php echo e((string) ($row['status'] ?? '-')); ?></td>
            <td><?php echo e((string) ($row['created_at'] ?? '-')); ?></td>
          </tr>
        <?php } ?>
        </tbody></table>
      <?php } ?>
    </div></div>
  <?php } ?>
</div></div>
<?php init_tail(); ?>
