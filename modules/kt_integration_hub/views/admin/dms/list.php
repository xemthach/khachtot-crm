<?php defined('BASEPATH') or exit('Không direct script access allowed'); ?>
<?php init_head(); ?>
<?php
if (!function_exists('dms_dashboard_value_badge_class')) {
    function dms_dashboard_value_badge_class($key, $value)
    {
        $value = strtolower(trim((string) $value));
        if ($key === 'priority') {
            if ($value === 'critical') {
                return 'danger';
            }
            if ($value === 'high') {
                return 'warning';
            }
            if ($value === 'medium') {
                return 'info';
            }
        }
        if (in_array($key, ['status', 'review_status', 'visit_status', 'tracking_status', 'sync_status'], true)) {
            if (in_array($value, ['valid', 'completed', 'online', 'resolved', 'done', 'synced'], true)) {
                return 'success';
            }
            if (in_array($value, ['warning', 'partial', 'stale', 'in_progress'], true)) {
                return 'warning';
            }
            if (in_array($value, ['invalid', 'cancelled', 'offline', 'rejected', 'failed'], true)) {
                return 'danger';
            }
            if ($value === 'new' || $value === 'open') {
                return 'info';
            }
        }

        return 'default';
    }
}

$summaryCards = is_array($summary_cards ?? null) ? $summary_cards : [];
$actions = is_array($actions ?? null) ? $actions : [];
$emptyState = is_array($empty_state ?? null) ? $empty_state : [];
$sourceContext = is_array($source_context ?? null) ? $source_context : [];
$pageHeading = trim((string) ($page_heading ?? $title ?? ''));
$statusLabels = [
    'valid' => 'Hợp lệ',
    'warning' => 'Cần chú ý',
    'invalid' => 'Không hợp lệ',
    'completed' => 'Hoàn thành',
    'partial' => 'Đang thực hiện',
    'cancelled' => 'Đã hủy',
    'online' => 'Trực tuyến',
    'stale' => 'Chậm cập nhật',
    'offline' => 'Ngoại tuyến',
    'new' => 'Mới',
    'open' => 'Đang mở',
    'in_progress' => 'Đang xử lý',
    'resolved' => 'Đã xử lý',
];
?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-8">
        <h4 class="tw-mb-1"><?php echo e($pageHeading); ?></h4>
        <?php if (!empty($page_note)) { ?>
          <p class="text-muted"><?php echo e((string) $page_note); ?></p>
        <?php } ?>
      </div>
      <div class="col-md-4 text-right">
        <?php if (($actions['open_dms_mode'] ?? '') === 'disabled') { ?>
          <span class="btn btn-default disabled">SSO is disabled for this tenant</span>
        <?php } elseif (!empty($actions['open_dms_url'])) { ?>
          <a href="<?php echo e($actions['open_dms_url']); ?>" class="btn btn-primary">Mở DMS</a>
        <?php } ?>
        <?php if (!empty($actions['provisioning_url'])) { ?>
          <a href="<?php echo e($actions['setup_center_url'] ?? $actions['provisioning_url']); ?>" class="btn btn-default">Mở Thiết lập DMS</a>
        <?php } ?>
      </div>
    </div>

    <?php if ($sourceContext !== []) { ?>
      <div class="alert alert-info">
        <strong>Nguồn dữ liệu:</strong> <?php echo e((string) ($sourceContext['label'] ?? 'DMS bridge')); ?>
        <span class="tw-ml-2">CRM đang đọc từ <code><?php echo e((string) ($sourceContext['table'] ?? '')); ?></code>.</span>
        <div class="small tw-mt-1">
          Bản ghi đã nhận: <strong><?php echo e((string) ($sourceContext['record_count'] ?? 0)); ?></strong>.
          Đồng bộ gần nhất:
          <strong><?php echo e((string) (($sourceContext['last_event_at'] ?? '') ?: 'Chưa có')); ?></strong>.
          <?php if (!empty($sourceContext['last_log_id'])) { ?>
            Log bridge gần nhất: #<?php echo e((string) $sourceContext['last_log_id']); ?>
            (<?php echo e((string) ($sourceContext['last_log_event'] ?? '')); ?>).
          <?php } ?>
        </div>
      </div>
    <?php } ?>

    <?php if ($summaryCards !== []) { ?>
      <div class="row">
        <?php foreach ($summaryCards as $card) { ?>
          <div class="col-md-4 col-sm-6">
            <div class="panel_s">
              <div class="panel-body">
                <div class="text-uppercase text-muted small"><?php echo e((string) ($card['label'] ?? '')); ?></div>
                <div class="tw-text-2xl tw-font-semibold tw-mt-2"><?php echo e((string) ($card['value'] ?? '0')); ?></div>
                <div class="text-muted small tw-mt-1"><?php echo e((string) ($card['detail'] ?? '')); ?></div>
              </div>
            </div>
          </div>
        <?php } ?>
      </div>
    <?php } ?>

    <div class="panel_s">
      <div class="panel-body">
        <form method="get" class="row">
          <?php foreach (($filter_fields ?? []) as $field) {
              $key = $field['key'] ?? '';
              if ($key === '') {
                  continue;
              }
              $label = $field['label'] ?? ucfirst(str_replace('_', ' ', $key));
              $type = in_array($key, ['date_from', 'date_to'], true) ? 'date' : 'text';
          ?>
            <div class="col-md-2 col-sm-6">
              <?php echo render_input($key, $label, $filters[$key] ?? '', $type); ?>
            </div>
          <?php } ?>
          <div class="col-md-2 col-sm-6">
            <label class="control-label">&nbsp;</label>
            <div>
              <button type="submit" class="btn btn-primary"><?php echo _l('filter'); ?></button>
              <a href="<?php echo admin_url('kt_integration_hub/' . ($section ?? 'dms_overview')); ?>" class="btn btn-default"><?php echo _l('clear'); ?></a>
            </div>
          </div>
        </form>
      </div>
    </div>

    <div class="panel_s">
      <div class="panel-body">
        <?php if (empty($rows)) { ?>
          <div class="alert alert-info">
            <h5 class="tw-mt-0 tw-mb-2"><?php echo e((string) ($emptyState['title'] ?? 'Chưa có dữ liệu.')); ?></h5>
            <p class="tw-mb-2"><?php echo e((string) ($emptyState['reason'] ?? '')); ?></p>
            <p class="tw-mb-0"><?php echo e((string) ($emptyState['next'] ?? '')); ?></p>
          </div>
          <div class="tw-flex tw-gap-2">
            <?php if (($actions['open_dms_mode'] ?? '') === 'disabled') { ?>
              <span class="btn btn-default disabled">SSO is disabled for this tenant</span>
            <?php } elseif (!empty($actions['open_dms_url'])) { ?>
              <a href="<?php echo e($actions['open_dms_url']); ?>" class="btn btn-primary">Mở DMS</a>
            <?php } ?>
            <?php if (!empty($actions['provisioning_url'])) { ?>
              <a href="<?php echo e($actions['setup_center_url'] ?? $actions['provisioning_url']); ?>" class="btn btn-default">Mở Thiết lập DMS</a>
            <?php } ?>
            <a href="<?php echo e(admin_url('kt_integration_hub/dms_settings')); ?>" class="btn btn-default">Check DMS Settings</a>
          </div>
        <?php } else { ?>
          <div class="table-responsive">
            <table class="table table-striped table-hover">
              <thead>
                <tr>
                  <?php foreach (($columns ?? []) as $label) { ?>
                    <th><?php echo e($label); ?></th>
                  <?php } ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach (($rows ?? []) as $row) { ?>
                  <tr>
                    <?php foreach (array_keys((array) ($columns ?? [])) as $key) {
                        $value = $row[$key] ?? '';
                        if (($key === 'staff_name' || $key === 'assigned_staff_name') && trim((string) $value) === '') {
                            $fallbackId = $row['crm_staff_id'] ?? $row['assigned_staff_id'] ?? '';
                            $value = $fallbackId !== '' ? (string) $fallbackId : $value;
                        }
                        if ($key === 'customer_name' && trim((string) $value) === '') {
                            $value = isset($row['crm_customer_id']) ? (string) $row['crm_customer_id'] : $value;
                        }
                        if ($key === 'gps') {
                            $lat = $row['latitude'] ?? '';
                            $lng = $row['longitude'] ?? '';
                            $value = trim((string) $lat . ', ' . (string) $lng, ', ');
                        }
                        if (is_array($value)) {
                            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }
                        $badge = dms_dashboard_value_badge_class($key, $value);
                        $displayValue = $statusLabels[strtolower(trim((string) $value))] ?? $value;
                    ?>
                      <td>
                        <?php if ($key === 'sync_action') { ?>
                          <?php if (($row['sync_status'] ?? '') === 'failed' && !empty($row['sync_job_id'])) { ?>
                            <?php echo form_open(admin_url('kt_integration_hub/dms_retry_bridge_record/' . rawurlencode((string) ($row['sync_entity_type'] ?? '')) . '/' . (int) $row['sync_job_id']), ['class' => 'tw-inline']); ?>
                            <button type="submit" class="btn btn-default btn-xs">Thử lại</button>
                            <?php echo form_close(); ?>
                          <?php } ?>
                        <?php } elseif ($badge !== 'default') { ?>
                          <span class="label label-<?php echo e($badge); ?>"><?php echo e((string) $displayValue); ?></span>
                          <?php if ($key === 'sync_status' && !empty($row['sync_error'])) { ?>
                            <div class="text-danger small tw-mt-1"><?php echo e((string) $row['sync_error']); ?></div>
                          <?php } ?>
                        <?php } else { ?>
                          <?php echo e((string) $displayValue); ?>
                        <?php } ?>
                      </td>
                    <?php } ?>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
          </div>

          <?php
          $total = (int) ($total ?? 0);
          $page = max(1, (int) ($page ?? 1));
          $limit = max(1, (int) ($limit ?? 25));
          $pages = max(1, (int) ceil($total / $limit));
          if ($pages > 1) {
              $query = $_GET;
              unset($query['page']);
              $base = current_url();
          ?>
            <nav>
              <ul class="pager">
                <li class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">
                  <a href="<?php echo $page <= 1 ? '#' : e($base . '?' . http_build_query(array_merge($query, ['page' => $page - 1]))); ?>"><?php echo _l('previous'); ?></a>
                </li>
                <li class="text-muted" style="padding-top: 7px;"><?php echo e($page . ' / ' . $pages); ?></li>
                <li class="<?php echo $page >= $pages ? 'disabled' : ''; ?>">
                  <a href="<?php echo $page >= $pages ? '#' : e($base . '?' . http_build_query(array_merge($query, ['page' => $page + 1]))); ?>"><?php echo _l('next'); ?></a>
                </li>
              </ul>
            </nav>
          <?php } ?>
        <?php } ?>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
