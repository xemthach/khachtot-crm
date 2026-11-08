<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$tenantSettings = is_array($tenant_settings ?? null) ? $tenant_settings : [];
$actions = is_array($actions ?? null) ? $actions : [];
$readiness = is_array($readiness ?? null) ? $readiness : [];
$onboarding = is_array($onboarding ?? null) ? $onboarding : [];
$openDmsExperience = is_array($open_dms_experience ?? null) ? $open_dms_experience : [];
$workspace = is_array($workspace ?? null) ? $workspace : [];
$mapConfig = is_array($map_config ?? null) ? $map_config : [];
$steps = array_values((array) ($onboarding['steps'] ?? []));
$progressPercent = max(0, min(100, (int) ($onboarding['progress_percent'] ?? 0)));
$nextStep = null;
foreach ($steps as $candidate) {
    if (empty($candidate['done'])) {
        $nextStep = $candidate;
        break;
    }
}
$cycleLabels = ['monthly' => 'Hàng tháng', 'quarterly' => 'Hàng quý', 'yearly' => 'Hàng năm'];
$allowedCycles = array_map(static function ($cycle) use ($cycleLabels) {
    $cycle = strtolower(trim((string) $cycle));
    return $cycleLabels[$cycle] ?? 'Chu kỳ khác';
}, (array) ($tenantSettings['allowed_billing_periods'] ?? []));
$mapLabels = [
    'ready' => 'Sẵn sàng',
    'missing_platform_config' => 'Thiếu cấu hình nền tảng',
    'disabled_by_package' => 'Bị khóa theo gói',
    'disabled_by_platform' => 'Nền tảng đang tắt',
];
$stepIcons = [1 => 'fa-cube', 2 => 'fa-users', 3 => 'fa-map-marker', 4 => 'fa-cubes', 5 => 'fa-map', 6 => 'fa-file-text', 7 => 'fa-refresh'];
?>
<style>
  .dms-wizard-shell { max-width:1180px; margin:0 auto; }
  .dms-wizard-hero { padding:26px; border:1px solid #dbe4ef; border-radius:8px; background:#fff; box-shadow:0 2px 8px rgba(15,23,42,.05); }
  .dms-wizard-hero h2 { margin:0 0 8px; color:#172b4d; font-size:24px; }
  .dms-wizard-hero p { margin:0; color:#52637a; line-height:1.6; }
  .dms-wizard-progress { display:flex; align-items:center; gap:18px; margin-top:22px; }
  .dms-wizard-progress .progress { flex:1; height:10px; margin:0; border-radius:999px; background:#e8edf5; }
  .dms-wizard-progress .progress-bar { border-radius:999px; }
  .dms-wizard-actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:20px; }
  .dms-wizard-steps { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; margin-top:20px; }
  .dms-wizard-step { position:relative; display:flex; gap:16px; min-height:206px; padding:20px; border:1px solid #dbe4ef; border-radius:8px; background:#fff; box-shadow:0 2px 8px rgba(15,23,42,.04); }
  .dms-wizard-step.is-done { border-color:#bbdfc7; }
  .dms-step-icon { display:flex; align-items:center; justify-content:center; flex:0 0 42px; width:42px; height:42px; border-radius:8px; background:#eef4ff; color:#2563eb; font-size:17px; }
  .dms-wizard-step.is-done .dms-step-icon { background:#e9f7ee; color:#15803d; }
  .dms-step-main { display:flex; flex:1; min-width:0; flex-direction:column; }
  .dms-step-eyebrow { color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; }
  .dms-step-main h3 { margin:4px 0 8px; color:#172b4d; font-size:17px; }
  .dms-step-main p { color:#52637a; line-height:1.5; }
  .dms-step-footer { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-top:auto; padding-top:12px; }
  .dms-step-status { display:inline-block; padding:4px 9px; border-radius:999px; font-size:11px; font-weight:700; }
  .dms-step-status.done { background:#e9f7ee; color:#15803d; }
  .dms-step-status.action { background:#fff4d8; color:#a16207; }
  .dms-step-status.locked { background:#f1f5f9; color:#64748b; }
  .dms-step-checks { margin-top:12px; border-top:1px solid #edf2f7; padding-top:10px; }
  .dms-step-checks summary { color:#475569; cursor:pointer; font-size:12px; font-weight:600; }
  .dms-step-checks ul { margin:10px 0 0; padding:0; list-style:none; }
  .dms-step-checks li { display:flex; gap:7px; margin-bottom:7px; color:#52637a; font-size:12px; }
  .dms-step-checks i { margin-top:2px; }
  .dms-wizard-technical { margin-top:20px; border:1px solid #dbe4ef; border-radius:8px; background:#fff; }
  .dms-wizard-technical > summary { padding:17px 20px; color:#334155; cursor:pointer; font-weight:700; }
  .dms-wizard-technical-body { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:20px; padding:0 20px 20px; }
  .dms-safe-note { padding:14px; border-radius:8px; background:#f8fafc; color:#52637a; }
  @media (max-width:767px) {
    .dms-wizard-steps, .dms-wizard-technical-body { grid-template-columns:1fr; }
    .dms-wizard-step { min-height:0; }
    .dms-wizard-progress { align-items:flex-start; flex-direction:column; }
    .dms-wizard-progress .progress { width:100%; }
    .dms-wizard-actions .btn, .dms-step-footer .btn { width:100%; }
    .dms-step-footer { align-items:stretch; flex-direction:column; }
  }
</style>
<div id="wrapper">
  <div class="content">
    <div class="dms-wizard-shell" data-r27-setup-wizard="ready">
      <section class="dms-wizard-hero">
        <span class="label label-primary">Hướng dẫn khởi tạo workspace</span>
        <h2>Thiết lập DMS cho đội sales thị trường</h2>
        <p>Thực hiện các bước bên dưới để bắt đầu vận hành check-in, điểm bán, đơn hàng và bản đồ.</p>
        <div class="dms-wizard-progress">
          <strong>Tiến độ thiết lập: <?php echo e((string) ($onboarding['progress_label'] ?? '0/7')); ?></strong>
          <div class="progress" aria-label="Tiến độ thiết lập">
            <div class="progress-bar progress-bar-primary no-percent-text not-dynamic" role="progressbar" aria-valuenow="<?php echo $progressPercent; ?>" aria-valuemin="0" aria-valuemax="100" data-percent="<?php echo $progressPercent; ?>" style="width:<?php echo $progressPercent; ?>%"></div>
          </div>
          <span class="text-muted"><?php echo $progressPercent; ?>%</span>
        </div>
        <div class="dms-wizard-actions">
          <?php if (!empty($nextStep['cta']['url'])) { ?>
            <a class="btn btn-primary" href="<?php echo e((string) $nextStep['cta']['url']); ?>"><i class="fa fa-arrow-right tw-mr-1"></i> Tiếp tục thiết lập</a>
          <?php } elseif (!empty($actions['open_dms_url']) && ($actions['open_dms_mode'] ?? '') !== 'disabled') { ?>
            <a class="btn btn-primary" href="<?php echo e((string) $actions['open_dms_url']); ?>"><i class="fa fa-external-link tw-mr-1"></i> Mở DMS</a>
          <?php } ?>
          <?php if (!empty($actions['open_dms_url']) && ($actions['open_dms_mode'] ?? '') !== 'disabled') { ?>
            <a class="btn btn-default" href="<?php echo e((string) $actions['open_dms_url']); ?>">Mở DMS</a>
          <?php } ?>
          <a class="btn btn-default" href="<?php echo e(admin_url('kt_integration_hub/dms_sync_health')); ?>">Xem trạng thái đồng bộ</a>
        </div>
      </section>

      <section class="dms-wizard-steps">
        <?php foreach ($steps as $step) { ?>
          <?php
          $index = (int) ($step['index'] ?? 0);
          $done = !empty($step['done']);
          $locked = ($index === 5 && ($mapConfig['map_status'] ?? '') === 'disabled_by_package')
              || ($index === 6 && empty($tenantSettings['features']['ORDERS_ENABLED']));
          $statusLabel = $done ? 'Đã xong bước này' : ($locked ? 'Bị khóa theo gói' : 'Cần thao tác');
          $statusClass = $done ? 'done' : ($locked ? 'locked' : 'action');
          ?>
          <article class="dms-wizard-step <?php echo $done ? 'is-done' : ''; ?>" id="dms-step-<?php echo $index; ?>">
            <div class="dms-step-icon"><i class="fa <?php echo e($stepIcons[$index] ?? 'fa-check'); ?>"></i></div>
            <div class="dms-step-main">
              <div class="dms-step-eyebrow">Bước <?php echo $index; ?> trong 7</div>
              <h3><?php echo e((string) ($step['title'] ?? 'Bước thiết lập')); ?></h3>
              <p><?php echo e((string) ($step['summary'] ?? 'Kiểm tra trạng thái và hoàn tất bước này.')); ?></p>
              <?php if (!empty($step['checks'])) { ?>
                <details class="dms-step-checks">
                  <summary>Xem nội dung kiểm tra</summary>
                  <ul>
                    <?php foreach ((array) $step['checks'] as $check) { ?>
                      <li><i class="fa <?php echo !empty($check['done']) ? 'fa-check-circle text-success' : 'fa-circle-o text-muted'; ?>"></i><span><?php echo e((string) ($check['label'] ?? 'Nội dung kiểm tra')); ?></span></li>
                    <?php } ?>
                  </ul>
                </details>
              <?php } ?>
              <div class="dms-step-footer">
                <span class="dms-step-status <?php echo e($statusClass); ?>"><?php echo e($statusLabel); ?></span>
                <?php if (!empty($step['cta']['url']) && !$done) { ?>
                  <a href="<?php echo e((string) $step['cta']['url']); ?>" class="btn btn-sm btn-<?php echo e((string) ($step['cta']['style'] ?? 'default')); ?>"><?php echo e((string) ($step['cta']['label'] ?? 'Thực hiện')); ?></a>
                <?php } elseif ($done) { ?>
                  <span class="text-success small"><i class="fa fa-check tw-mr-1"></i>Đã kiểm tra</span>
                <?php } ?>
              </div>
            </div>
          </article>
        <?php } ?>
      </section>

      <details class="dms-wizard-technical" data-r27-technical-details>
        <summary><i class="fa fa-cog tw-mr-2"></i>Chi tiết kỹ thuật</summary>
        <div class="dms-wizard-technical-body">
          <section>
            <h4>Trạng thái đồng bộ</h4>
            <p><strong>Phiên bản cấu hình:</strong> <?php echo (int) ($tenantSettings['config_version'] ?? 1); ?></p>
            <p><strong>Kết nối mở DMS:</strong> <?php echo ($actions['open_dms_mode'] ?? '') === 'disabled' ? 'Chưa sẵn sàng' : 'Sẵn sàng'; ?></p>
            <p><strong>Bản đồ:</strong> <?php echo e($mapLabels[$mapConfig['map_status'] ?? ''] ?? 'Chưa kiểm tra'); ?></p>
            <div class="dms-safe-note">Nhà cung cấp bản đồ, khóa API và thông tin kết nối do Khách Tốt Platform quản lý. Doanh nghiệp không thể xem hoặc thay đổi thông tin xác thực.</div>
          </section>
          <section>
            <h4>Thông tin workspace</h4>
            <p><strong>Gói DMS:</strong> <?php echo e((string) ($tenantSettings['dms_package_name'] ?? $tenantSettings['DMS_PACKAGE'] ?? 'Chưa chọn')); ?></p>
            <p><strong>Chu kỳ hiện tại:</strong> <?php $cycle = strtolower((string) ($tenantSettings['dms_billing_cycle'] ?? '')); echo e($cycleLabels[$cycle] ?? 'Chưa chọn'); ?></p>
            <p><strong>Chu kỳ được phép:</strong> <?php echo e(!empty($allowedCycles) ? implode(', ', $allowedCycles) : 'Chưa có dữ liệu'); ?></p>
            <p><strong>Địa chỉ workspace:</strong> <?php echo e((string) ($workspace['host'] ?? 'Chưa sẵn sàng')); ?></p>
            <p><strong>Trạng thái tên miền:</strong> <?php echo !empty($workspace['ready']) ? 'Sẵn sàng' : 'Chưa sẵn sàng'; ?></p>
          </section>
        </div>
      </details>
    </div>
  </div>
</div>
<?php init_tail(); ?>
