<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$CI = &get_instance();
$app = is_array($salesflow_app ?? null) ? $salesflow_app : [];
$state = is_array($marketplace_state ?? null) ? $marketplace_state : [];
$actions = is_array($marketplace_actions ?? null) ? $marketplace_actions : [];
$billing = is_array($app['billing'] ?? null) ? $app['billing'] : [];
$workspace = is_array($app['workspace'] ?? null) ? $app['workspace'] : [];
$health = is_array($app['health'] ?? null) ? $app['health'] : [];
$installHistory = is_array($app['install_history'] ?? null) ? $app['install_history'] : [];
$usageSummary = is_array($app['usage_summary'] ?? null) ? $app['usage_summary'] : [];
$usageRows = is_array($usageSummary['rows'] ?? null) ? $usageSummary['rows'] : [];
$latestCommercialOrder = is_array($app['latest_commercial_order'] ?? null) ? $app['latest_commercial_order'] : [];
$packageCatalog = is_array($app['package_catalog'] ?? null) ? array_values($app['package_catalog']) : [];
$packageOrder = ['dms_mini' => 10, 'dms_standard' => 20, 'dms_pro' => 30, 'dms_enterprise' => 40];
usort($packageCatalog, static function ($left, $right) use ($packageOrder) {
    $leftCode = strtolower(trim((string) ($left['package_code'] ?? '')));
    $rightCode = strtolower(trim((string) ($right['package_code'] ?? '')));
    return ($packageOrder[$leftCode] ?? 999) <=> ($packageOrder[$rightCode] ?? 999);
});
$selectedPackageCode = strtolower(trim((string) ($app['selected_package_code'] ?? '')));
$selectedBillingCycle = strtolower(trim((string) ($app['selected_billing_cycle'] ?? ($billing['current_billing_cycle'] ?? 'monthly'))));
$addonStatus = strtolower(trim((string) ($billing['subscription_status'] ?? 'none')));
$stateCode = strtoupper(trim((string) ($state['code'] ?? 'DISCOVERED')));

$cycleLabels = ['monthly' => 'Hàng tháng', 'quarterly' => 'Hàng quý', 'yearly' => 'Hàng năm'];
$cycleShortLabels = ['monthly' => 'tháng', 'quarterly' => 'quý', 'yearly' => 'năm'];
$statusLabels = [
    'none' => 'Chưa kích hoạt',
    'trial' => 'Dùng thử',
    'active' => 'Đang hoạt động',
    'selected' => 'Đã chọn gói',
    'payment_pending' => 'Chờ thanh toán',
    'pending_payment' => 'Chờ thanh toán',
    'provisioning' => 'Đang khởi tạo',
    'setup_required' => 'Cần hoàn tất thiết lập',
    'suspended' => 'Tạm ngưng',
    'cancelled' => 'Đã hủy',
    'failed' => 'Cần kiểm tra',
    'completed' => 'Hoàn tất',
    'ready' => 'Sẵn sàng',
    'not_started' => 'Chưa bắt đầu',
];
$stateLabels = [
    'DISCOVERED' => 'Chưa kích hoạt',
    'SELECTED' => 'Đã chọn gói',
    'PROVISIONING' => 'Đang khởi tạo',
    'SETUP_REQUIRED' => 'Cần hoàn tất thiết lập',
    'ACTIVE' => 'Đang hoạt động',
    'SUSPENDED' => 'Tạm ngưng',
];
$statusLabel = $statusLabels[$addonStatus] ?? ($stateLabels[$stateCode] ?? 'Chưa xác định');
$statusClass = in_array($addonStatus, ['active'], true) || $stateCode === 'ACTIVE'
    ? 'success'
    : (in_array($addonStatus, ['payment_pending', 'pending_payment', 'provisioning'], true) ? 'warning' : 'default');

$packageCopy = [
    'dms_mini' => ['description' => 'Phù hợp đội sales thị trường nhỏ, cần quản lý check-in, điểm bán và đơn hàng cơ bản.', 'badge' => 'Phù hợp đội nhỏ', 'icon' => 'fa-leaf'],
    'dms_standard' => ['description' => 'Phù hợp doanh nghiệp đang mở rộng đội sales và cần kiểm soát tuyến bán hàng.', 'badge' => 'Đề xuất', 'icon' => 'fa-line-chart'],
    'dms_pro' => ['description' => 'Phù hợp đội ngũ nhiều khu vực, cần theo dõi hoạt động, báo cáo và đồng bộ CRM.', 'badge' => 'Phù hợp nhiều khu vực', 'icon' => 'fa-map'],
    'dms_enterprise' => ['description' => 'Phù hợp mô hình phân phối lớn, nhiều đội sales, nhiều điểm bán và yêu cầu kiểm soát nâng cao.', 'badge' => 'Quy mô lớn', 'icon' => 'fa-building'],
];
$limitValue = static function ($value) {
    $value = (int) $value;
    return $value > 0 ? number_format($value, 0, ',', '.') : 'Không giới hạn';
};
$dateValue = static function ($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return 'Chưa có dữ liệu';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : $value;
};
$jobLabel = static function ($value) {
    $map = [
        'install_salesflow_dms' => 'Khởi tạo DMS',
        'provision_tenant' => 'Khởi tạo workspace',
        'sync_staff_to_dms' => 'Đồng bộ nhân sự',
        'pending' => 'Đang chờ',
        'processing' => 'Đang xử lý',
        'running' => 'Đang xử lý',
        'completed' => 'Hoàn tất',
        'success' => 'Hoàn tất',
        'failed' => 'Cần kiểm tra',
    ];
    $normalized = strtolower(trim((string) $value));
    return $map[$normalized] ?? 'Thông tin hệ thống';
};

$availableCycles = [];
$packageOptions = [];
foreach ($packageCatalog as $package) {
    $code = strtolower(trim((string) ($package['package_code'] ?? '')));
    $options = isset($CI->Kt_saas_model) && method_exists($CI->Kt_saas_model, 'get_dms_addon_billing_cycle_options')
        ? $CI->Kt_saas_model->get_dms_addon_billing_cycle_options($package)
        : [];
    $packageOptions[$code] = $options;
    foreach (array_keys($options) as $cycle) {
        $availableCycles[$cycle] = true;
    }
}
if (!isset($availableCycles[$selectedBillingCycle])) {
    $selectedBillingCycle = isset($availableCycles['monthly']) ? 'monthly' : (string) (array_key_first($availableCycles) ?? 'monthly');
}
?>
<style>
  .dms-commercial-shell { max-width: 1440px; margin: 0 auto; }
  .dms-commercial-hero { display:flex; align-items:flex-start; justify-content:space-between; gap:24px; padding:24px; border:1px solid #dbe4ef; border-radius:8px; background:#fff; box-shadow:0 2px 8px rgba(15,23,42,.05); }
  .dms-commercial-hero h2 { margin:0 0 8px; color:#172b4d; font-size:24px; }
  .dms-commercial-hero p { max-width:760px; margin:0; color:#52637a; line-height:1.6; }
  .dms-current-plan { margin-top:20px; padding:20px; border:1px solid #dbe4ef; border-left:4px solid #2563eb; border-radius:8px; background:#fff; }
  .dms-current-grid { display:grid; grid-template-columns:repeat(5,minmax(130px,1fr)); gap:16px; margin-top:16px; }
  .dms-current-item span { display:block; color:#64748b; font-size:12px; margin-bottom:5px; }
  .dms-current-item strong { display:block; color:#172b4d; font-size:14px; overflow-wrap:anywhere; }
  .dms-cycle-toolbar { display:flex; align-items:center; justify-content:space-between; gap:20px; margin:24px 0 18px; }
  .dms-cycle-control { display:inline-flex; max-width:100%; padding:4px; border:1px solid #cbd5e1; border-radius:8px; background:#f8fafc; }
  .dms-cycle-control label { position:relative; margin:0; cursor:pointer; }
  .dms-cycle-control input { position:absolute; opacity:0; pointer-events:none; }
  .dms-cycle-control span { display:block; padding:9px 16px; border-radius:6px; color:#475569; font-weight:600; white-space:nowrap; }
  .dms-cycle-control input:checked + span { background:#2563eb; color:#fff; box-shadow:0 1px 3px rgba(37,99,235,.25); }
  .dms-package-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:18px; }
  .dms-package-card { position:relative; display:flex; flex-direction:column; min-height:480px; padding:22px; border:1px solid #dbe4ef; border-radius:8px; background:#fff; box-shadow:0 2px 8px rgba(15,23,42,.04); }
  .dms-package-card.is-current { border:2px solid #2563eb; box-shadow:0 8px 20px rgba(37,99,235,.10); }
  .dms-package-badge { display:inline-block; align-self:flex-start; padding:4px 9px; border-radius:999px; background:#eef4ff; color:#1d4ed8; font-size:11px; font-weight:700; }
  .dms-package-card h3 { margin:14px 0 2px; color:#172b4d; font-size:20px; }
  .dms-package-code { color:#718096; font-size:11px; text-transform:uppercase; }
  .dms-package-description { min-height:62px; margin:12px 0 16px; color:#52637a; line-height:1.5; }
  .dms-package-price { padding:15px 0; border-top:1px solid #edf2f7; border-bottom:1px solid #edf2f7; }
  .dms-package-price strong { color:#172b4d; font-size:26px; }
  .dms-package-price span { color:#64748b; font-size:13px; }
  .dms-package-features { flex:1; list-style:none; margin:16px 0 20px; padding:0; }
  .dms-package-features li { display:flex; gap:9px; margin:0 0 10px; color:#334155; line-height:1.35; }
  .dms-package-features i { margin-top:3px; color:#16a34a; }
  .dms-package-card .btn { width:100%; border-radius:6px; font-weight:600; }
  .dms-package-unavailable { display:none; min-height:34px; margin:8px 0; color:#a16207; font-size:12px; }
  .dms-package-card.is-unavailable .dms-package-unavailable { display:block; }
  .dms-package-card.is-unavailable .dms-package-action { opacity:.55; pointer-events:none; }
  .dms-empty-state { margin-top:20px; padding:24px; border:1px solid #fde68a; border-radius:8px; background:#fffbeb; }
  .dms-technical { margin-top:22px; border:1px solid #dbe4ef; border-radius:8px; background:#fff; }
  .dms-technical > summary { padding:17px 20px; color:#334155; font-weight:700; cursor:pointer; }
  .dms-technical-body { padding:0 20px 20px; }
  .dms-technical-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:20px; }
  .dms-technical h4 { margin:0 0 12px; font-size:15px; color:#172b4d; }
  @media (max-width:1199px) { .dms-package-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .dms-current-grid { grid-template-columns:repeat(3,minmax(130px,1fr)); } }
  @media (max-width:767px) {
    .dms-commercial-hero, .dms-cycle-toolbar { display:block; }
    .dms-commercial-hero .btn { width:100%; margin-top:14px; }
    .dms-current-grid, .dms-package-grid, .dms-technical-grid { grid-template-columns:1fr; }
    .dms-cycle-control { display:grid; grid-template-columns:repeat(3,1fr); width:100%; margin-top:12px; }
    .dms-cycle-control span { padding:9px 7px; text-align:center; white-space:normal; }
    .dms-package-card { min-height:0; }
    .dms-package-description { min-height:0; }
  }
</style>
<div id="wrapper">
  <div class="content">
    <div class="dms-commercial-shell" data-r27-marketplace="ready">
      <section class="dms-commercial-hero">
        <div>
          <span class="label label-primary">DMS thuộc hệ sinh thái Khách Tốt CRM</span>
          <h2>Gói DMS cho đội sales thị trường</h2>
          <p>DMS là dịch vụ bổ sung cho CRM, giúp doanh nghiệp quản lý nhân sự thị trường, điểm bán, check-in, đơn hàng và hoạt động theo khu vực. Gói CRM hiện tại vẫn được quản lý độc lập.</p>
        </div>
        <div>
          <a href="<?php echo html_escape((string) ($actions['setup_wizard_url'] ?? admin_url('kt_integration_hub/dms_setup_wizard'))); ?>" class="btn btn-default">
            <i class="fa fa-list-check tw-mr-1"></i> Thiết lập DMS
          </a>
        </div>
      </section>

      <section class="dms-current-plan" data-r27-current-package>
        <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-2">
          <div>
            <div class="text-muted small">Gói DMS hiện tại</div>
            <h4 class="tw-mt-1 tw-mb-0"><?php echo html_escape(trim((string) ($billing['current_package'] ?? '')) ?: 'Chưa kích hoạt gói DMS'); ?></h4>
          </div>
          <span class="label label-<?php echo html_escape($statusClass); ?>"><?php echo html_escape($statusLabel); ?></span>
        </div>
        <div class="dms-current-grid">
          <div class="dms-current-item"><span>Chu kỳ thanh toán</span><strong><?php echo html_escape($cycleLabels[$selectedBillingCycle] ?? 'Chưa chọn'); ?></strong></div>
          <div class="dms-current-item"><span>Ngày bắt đầu</span><strong><?php echo html_escape($dateValue($installHistory['installed_at'] ?? '')); ?></strong></div>
          <div class="dms-current-item"><span>Ngày gia hạn tiếp theo</span><strong><?php echo html_escape($dateValue($billing['expires_at'] ?? '')); ?></strong></div>
          <div class="dms-current-item"><span>Hạn mức đang dùng</span><strong><?php echo html_escape(!empty($usageRows) ? count($usageRows) . ' nhóm hạn mức' : 'Chưa có dữ liệu'); ?></strong></div>
          <div class="dms-current-item"><span>Workspace DMS</span><strong><?php echo !empty($workspace['ready']) ? 'Sẵn sàng' : 'Chưa sẵn sàng'; ?></strong></div>
        </div>
        <div class="tw-mt-4 tw-flex tw-flex-wrap tw-gap-2">
          <?php if ($addonStatus === 'active' && !empty($actions['open_dms_url'])) { ?>
            <a href="<?php echo html_escape((string) $actions['open_dms_url']); ?>" class="btn btn-primary"><i class="fa fa-external-link tw-mr-1"></i> Mở DMS</a>
          <?php } ?>
          <a href="#dms-package-options" class="btn btn-default"><?php echo $addonStatus === 'active' ? 'Yêu cầu đổi gói' : 'Chọn gói DMS'; ?></a>
          <?php if (!empty($latestCommercialOrder['id'])) { ?><a href="<?php echo admin_url('kt_saas/dms_order_status/' . (int) $latestCommercialOrder['id']); ?>" class="btn btn-default">Xem đơn DMS</a><?php } ?>
        </div>
      </section>

      <?php if ($addonStatus === 'none' || $selectedPackageCode === '') { ?>
        <section class="dms-empty-state">
          <h4 class="tw-mt-0">Bạn chưa kích hoạt gói DMS</h4>
          <p class="tw-mb-0">Chọn gói phù hợp để bắt đầu quản lý đội sales thị trường, check-in, điểm bán và đơn hàng.</p>
        </section>
      <?php } elseif (in_array($addonStatus, ['payment_pending', 'pending_payment'], true)) { ?>
        <section class="dms-empty-state">
          <h4 class="tw-mt-0">Gói DMS đang chờ thanh toán</h4>
          <p><strong>Bạn đã có đơn DMS đang chờ thanh toán.</strong></p>
          <p>Sau khi thanh toán hoàn tất, workspace DMS sẽ được kích hoạt.</p>
          <?php if (!empty($latestCommercialOrder['id'])) { ?><a class="btn btn-warning" href="<?php echo admin_url('kt_saas/dms_order_pay/' . (int) $latestCommercialOrder['id']); ?>">Tiếp tục thanh toán</a><?php } ?>
          <?php if (!empty($latestCommercialOrder['id'])) { ?><a class="btn btn-default" href="<?php echo admin_url('kt_saas/dms_order_status/' . (int) $latestCommercialOrder['id']); ?>">Xem thanh toán</a><?php } ?>
        </section>
      <?php } elseif ($stateCode !== 'ACTIVE' && $addonStatus === 'active') { ?>
        <section class="dms-empty-state">
          <h4 class="tw-mt-0">Workspace DMS chưa sẵn sàng</h4>
          <p>Hệ thống đang chờ đồng bộ nhân sự hoặc kiểm tra kết nối. Vui lòng xem trạng thái thiết lập.</p>
          <a class="btn btn-default" href="<?php echo html_escape((string) ($actions['setup_wizard_url'] ?? '#')); ?>">Kiểm tra trạng thái</a>
        </section>
      <?php } ?>

      <div id="dms-package-options" class="dms-cycle-toolbar">
        <div>
          <h3 class="tw-m-0">Chọn gói DMS phù hợp</h3>
          <p class="text-muted tw-mt-1 tw-mb-0">Giá và hạn mức được lấy từ cấu hình gói hiện hành.</p>
        </div>
        <fieldset>
          <legend class="sr-only">Chu kỳ thanh toán</legend>
          <div class="dms-cycle-control" data-r27-cycle-selector>
            <?php foreach ($cycleLabels as $cycle => $label) { ?>
              <?php if (!isset($availableCycles[$cycle])) { continue; } ?>
              <label>
                <input type="radio" name="marketplace_cycle" value="<?php echo html_escape($cycle); ?>" <?php echo $selectedBillingCycle === $cycle ? 'checked' : ''; ?>>
                <span><?php echo html_escape($label); ?></span>
              </label>
            <?php } ?>
          </div>
        </fieldset>
      </div>

      <section class="dms-package-grid">
        <?php foreach ($packageCatalog as $package) { ?>
          <?php
          $packageCode = strtolower(trim((string) ($package['package_code'] ?? '')));
          $copy = $packageCopy[$packageCode] ?? ['description' => 'Gói DMS dành cho hoạt động sales thị trường.', 'badge' => 'Gói DMS', 'icon' => 'fa-map-signs'];
          $isCurrent = $selectedPackageCode === $packageCode && $addonStatus !== 'none';
          $cycleOptions = $packageOptions[$packageCode] ?? [];
          $priceMap = [];
          foreach ($cycleOptions as $cycle => $option) {
              $priceMap[$cycle] = (float) ($option['price'] ?? 0);
          }
          $initialAvailable = isset($cycleOptions[$selectedBillingCycle]);
          $initialPrice = $initialAvailable ? (float) ($cycleOptions[$selectedBillingCycle]['price'] ?? 0) : 0;
          $isEnterprise = $packageCode === 'dms_enterprise' && $initialPrice <= 0;
          ?>
          <article class="dms-package-card <?php echo $isCurrent ? 'is-current' : ''; ?> <?php echo !$initialAvailable ? 'is-unavailable' : ''; ?>"
                   data-package-code="<?php echo html_escape($packageCode); ?>"
                   data-cycle-prices="<?php echo html_escape(json_encode($priceMap, JSON_UNESCAPED_UNICODE)); ?>">
            <span class="dms-package-badge"><?php echo html_escape($isCurrent ? 'Gói hiện tại' : $copy['badge']); ?></span>
            <h3><i class="fa <?php echo html_escape($copy['icon']); ?> tw-mr-1 text-primary"></i><?php echo html_escape((string) ($package['name'] ?? strtoupper($packageCode))); ?></h3>
            <div class="dms-package-code"><?php echo html_escape(strtoupper($packageCode)); ?></div>
            <p class="dms-package-description"><?php echo html_escape($copy['description']); ?></p>
            <div class="dms-package-price">
              <strong data-price><?php echo $isEnterprise ? 'Liên hệ' : number_format($initialPrice, 0, ',', '.') . ' ₫'; ?></strong>
              <span data-price-period><?php echo $isEnterprise ? ' để nhận tư vấn' : '/ ' . html_escape($cycleShortLabels[$selectedBillingCycle] ?? 'chu kỳ'); ?></span>
            </div>
            <ul class="dms-package-features">
              <li><i class="fa fa-check-circle"></i><span>Nhân sự: <strong><?php echo html_escape($limitValue($package['user_limit'] ?? 0)); ?></strong></span></li>
              <li><i class="fa fa-check-circle"></i><span>Điểm bán: <strong><?php echo html_escape($limitValue($package['store_limit'] ?? 0)); ?></strong></span></li>
              <li><i class="fa fa-check-circle"></i><span>Sản phẩm: <strong><?php echo html_escape($limitValue($package['product_limit'] ?? 0)); ?></strong></span></li>
              <li><i class="fa fa-check-circle"></i><span>Check-in mỗi tháng: <strong><?php echo html_escape($limitValue($package['checkin_monthly_limit'] ?? 0)); ?></strong></span></li>
              <li><i class="fa fa-check-circle"></i><span>Theo dõi trực tiếp: <strong><?php echo !empty($package['live_tracking_enabled']) ? 'Có' : 'Không'; ?></strong></span></li>
              <li><i class="fa fa-check-circle"></i><span>Báo cáo và đồng bộ CRM: <strong>Có</strong></span></li>
            </ul>
            <div class="dms-package-unavailable">Gói này chưa hỗ trợ chu kỳ đã chọn. Vui lòng chọn chu kỳ khác.</div>
            <?php if ($isCurrent && $addonStatus === 'active') { ?>
              <button type="button" class="btn btn-success" disabled>Gói hiện tại</button>
            <?php } elseif ($isCurrent && in_array($addonStatus, ['payment_pending', 'pending_payment'], true) && !empty($latestCommercialOrder['id'])) { ?>
              <a href="<?php echo admin_url('kt_saas/dms_order_pay/' . (int) $latestCommercialOrder['id']); ?>" class="btn btn-warning dms-package-action">Tiếp tục thanh toán</a>
            <?php } else { ?>
              <form action="<?php echo admin_url('kt_saas/tenant_install_salesflow_dms'); ?>" method="post" class="dms-package-action" data-package-form>
                <input type="hidden" name="<?php echo html_escape($CI->security->get_csrf_token_name()); ?>" value="<?php echo html_escape($CI->security->get_csrf_hash()); ?>">
                <input type="hidden" name="package_code" value="<?php echo html_escape($packageCode); ?>">
                <input type="hidden" name="billing_cycle" value="<?php echo html_escape($selectedBillingCycle); ?>" data-cycle-input>
                <button type="submit" class="btn btn-primary"><?php echo $isCurrent ? 'Tiếp tục kích hoạt' : ($packageCode === 'dms_enterprise' ? 'Yêu cầu tư vấn' : 'Chọn gói'); ?></button>
              </form>
            <?php } ?>
          </article>
        <?php } ?>
      </section>

      <details class="dms-technical" data-r27-technical-details>
        <summary><i class="fa fa-cog tw-mr-2"></i>Chi tiết kỹ thuật</summary>
        <div class="dms-technical-body">
          <p class="text-muted">Thông tin dưới đây hỗ trợ quản trị kết nối. Không có khóa API hoặc thông tin xác thực được hiển thị.</p>
          <div class="dms-technical-grid">
            <section>
              <h4>Thông tin workspace</h4>
              <div class="table-responsive"><table class="table table-striped tw-mb-0"><tbody>
                <tr><th>Địa chỉ workspace</th><td><?php echo html_escape((string) ($workspace['host'] ?? 'Chưa sẵn sàng')); ?></td></tr>
                <tr><th>Trạng thái tên miền</th><td><?php echo !empty($workspace['ready']) ? 'Sẵn sàng' : 'Chưa sẵn sàng'; ?></td></tr>
                <tr><th>Trạng thái kết nối</th><td><?php echo !empty($health['connection_status']) && stripos((string) $health['connection_status'], 'not ready') === false ? 'Đã kết nối' : 'Cần kiểm tra'; ?></td></tr>
                <tr><th>Lần đồng bộ gần nhất</th><td><?php echo html_escape($dateValue($health['last_sync'] ?? '')); ?></td></tr>
              </tbody></table></div>
            </section>
            <section>
              <h4>Nhật ký cài đặt</h4>
              <div class="table-responsive"><table class="table table-striped tw-mb-0"><tbody>
                <tr><th>Khởi tạo lần đầu</th><td><?php echo html_escape($dateValue($installHistory['installed_at'] ?? '')); ?></td></tr>
                <tr><th>Cập nhật workspace</th><td><?php echo html_escape($dateValue($installHistory['last_provisioned_at'] ?? '')); ?></td></tr>
                <?php foreach (array_slice((array) ($installHistory['jobs'] ?? []), 0, 5) as $job) { ?>
                  <tr><th><?php echo html_escape($jobLabel($job['job_type'] ?? '')); ?></th><td><?php echo html_escape($jobLabel($job['status'] ?? '')); ?> · <?php echo html_escape($dateValue($job['updated_at'] ?? ($job['created_at'] ?? ''))); ?></td></tr>
                <?php } ?>
              </tbody></table></div>
            </section>
          </div>
        </div>
      </details>
    </div>
  </div>
</div>
<script>
(function () {
  var radios = document.querySelectorAll('[data-r27-cycle-selector] input[name="marketplace_cycle"]');
  var cards = document.querySelectorAll('.dms-package-card[data-cycle-prices]');
  var periodLabels = {monthly: 'tháng', quarterly: 'quý', yearly: 'năm'};
  function formatPrice(value) {
    return new Intl.NumberFormat('vi-VN').format(Number(value || 0)) + ' ₫';
  }
  function applyCycle(cycle) {
    cards.forEach(function (card) {
      var prices = {};
      try { prices = JSON.parse(card.getAttribute('data-cycle-prices') || '{}'); } catch (error) { prices = {}; }
      var available = Object.prototype.hasOwnProperty.call(prices, cycle);
      card.classList.toggle('is-unavailable', !available);
      var input = card.querySelector('[data-cycle-input]');
      var button = card.querySelector('[data-package-form] button');
      if (input) input.value = cycle;
      if (button) button.disabled = !available;
      var price = card.querySelector('[data-price]');
      var period = card.querySelector('[data-price-period]');
      if (!price || !period) return;
      if (!available) {
        price.textContent = 'Không áp dụng';
        period.textContent = '';
      } else if (Number(prices[cycle]) <= 0 && card.getAttribute('data-package-code') === 'dms_enterprise') {
        price.textContent = 'Liên hệ';
        period.textContent = ' để nhận tư vấn';
      } else {
        price.textContent = formatPrice(prices[cycle]);
        period.textContent = '/ ' + (periodLabels[cycle] || 'chu kỳ');
      }
    });
  }
  radios.forEach(function (radio) {
    radio.addEventListener('change', function () { if (radio.checked) applyCycle(radio.value); });
  });
  var selected = document.querySelector('[data-r27-cycle-selector] input:checked');
  if (selected) applyCycle(selected.value);
}());
</script>
<?php init_tail(); ?>
