<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
/* Source-contract smoke phrase: Doi sales hom nay. */
$rows = is_array($rows ?? null) ? $rows : [];
$actions = is_array($actions ?? null) ? $actions : [];
$tracking = is_array($tracking_summary ?? null) ? $tracking_summary : [];
$field = is_array($field_summary ?? null) ? $field_summary : [];
$badge = static function ($status) {
    $s = strtolower(trim((string) $status));
    if (in_array($s, ['online', 'done', 'enabled'], true)) { return 'success'; }
    if (in_array($s, ['stale', 'retry', 'pending'], true)) { return 'warning'; }
    if (in_array($s, ['offline', 'failed', 'disabled'], true)) { return 'danger'; }
    return 'default';
};
?>
<div id="wrapper"><div class="content">
  <div class="row">
    <div class="col-md-8"><h4 class="tw-mb-1">Đội sales hôm nay</h4><p class="text-muted">Theo dõi nhân viên DMS, online/offline, check-in, visit và trạng thái sync trong ngày.</p></div>
    <div class="col-md-4 text-right"><?php if (!empty($actions['open_dms_url'])) { ?><a href="<?php echo e($actions['open_dms_url']); ?>" class="btn btn-primary">Mở app DMS</a><?php } ?></div>
  </div>
  <div class="row">
    <?php foreach ([['Sales online', $tracking['online'] ?? 0], ['Sales offline', $tracking['offline'] ?? 0], ['Check-in hôm nay', $field['total'] ?? 0], ['Sales có check-in', $field['active_staff_count'] ?? 0]] as $card) { ?>
      <div class="col-md-3 col-sm-6"><div class="panel_s"><div class="panel-body"><div class="text-uppercase text-muted small"><?php echo e($card[0]); ?></div><div class="tw-text-2xl tw-font-semibold"><?php echo e((string) $card[1]); ?></div></div></div></div>
    <?php } ?>
  </div>
  <div class="panel_s"><div class="panel-body">
    <?php if (empty($rows)) { ?><div class="alert alert-info">Chưa có nhân viên DMS. Mở Thiết lập đội DMS để bật access và chạy staff sync.</div><?php } else { ?>
      <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Nhân viên</th><th>Vai trò</th><th>Quản lý</th><th>Online</th><th>Check-in</th><th>Visit</th><th>Sync</th><th>Vị trí gần nhất</th></tr></thead><tbody>
        <?php foreach ($rows as $row) { ?><tr>
          <td><strong><?php echo e((string) ($row['staff_name'] ?? '-')); ?></strong><div class="text-muted small">#<?php echo e((string) ($row['staff_id'] ?? '-')); ?></div></td>
          <td><?php echo e((string) ($row['role'] ?? '-')); ?></td>
          <td><?php echo ((int) ($row['manager_crm_staff_id'] ?? 0) > 0) ? '#' . e((string) $row['manager_crm_staff_id']) : '<span class="text-muted">Chưa gán</span>'; ?></td>
          <td><span class="label label-<?php echo $badge($row['tracking_status'] ?? 'offline'); ?>"><?php echo e((string) ($row['tracking_status'] ?? 'offline')); ?></span><div class="text-muted small"><?php echo e((string) ($row['last_seen_at'] ?? '')); ?></div></td>
          <td><?php echo e((string) ($row['today_checkins'] ?? 0)); ?><div class="text-muted small"><?php echo e((string) ($row['last_checkin_at'] ?? '')); ?></div></td>
          <td><?php echo e((string) ($row['today_visits'] ?? 0)); ?><div class="text-muted small"><?php echo e((string) ($row['last_visit_at'] ?? '')); ?></div></td>
          <td><span class="label label-<?php echo $badge($row['sync_status'] ?? ''); ?>"><?php echo e((string) (($row['sync_status'] ?? '') ?: 'not_synced')); ?></span></td>
          <td><?php echo ($row['latitude'] !== null && $row['longitude'] !== null) ? e((string) $row['latitude'] . ', ' . (string) $row['longitude']) : '<span class="text-muted">Chưa có</span>'; ?></td>
        </tr><?php } ?>
      </tbody></table></div>
    <?php } ?>
  </div></div>
</div></div>
<?php init_tail(); ?>
