<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$dashboard = is_array($dashboard ?? null) ? $dashboard : [];
$summary = (array) ($dashboard['summary'] ?? []);
$packages = is_array($packages ?? null) ? $packages : [];
$validation = (array) ($config_validation ?? []);
$mirrors = is_array($provider_safe_mirror ?? null) ? $provider_safe_mirror : [];
$providerUrl = (string) ($platform_provider_url ?? 'https://salesrep.test/platform/providers');
$providerNames = [
    'google_maps' => 'Google Maps',
    'firebase' => 'Firebase',
    'mail' => 'Email / SMTP',
    'storage' => 'Lưu trữ',
    'crm_bridge' => 'Kết nối CRM',
];
$statusLabel = static function ($row) {
    $health = strtolower(trim((string) ($row['health_status'] ?? '')));
    $status = strtolower(trim((string) ($row['status'] ?? '')));
    if ($health === 'connection_failed') return 'Kiểm tra thất bại';
    if ($health === 'ready' || $health === 'pass_live' || $health === 'pass_mock') return 'Đã kiểm tra';
    if ($status === 'active' || $health === 'pending_check') return 'Đã lưu';
    return 'Chưa có dữ liệu kiểm tra';
};
?>
<div id="wrapper"><div class="content">
  <div class="row">
    <div class="col-md-8"><h4 class="tw-mb-1">Tổng quan vận hành DMS</h4><p class="text-muted">Theo dõi tenant, gói DMS, kết nối và trạng thái nhận cấu hình từ Salesrep DMS Platform.</p></div>
    <div class="col-md-4 text-right"><a class="btn btn-primary" href="<?php echo html_escape($providerUrl); ?>">Mở cấu hình nền tảng</a></div>
  </div>

  <div class="panel_s"><div class="panel-heading">Nguồn cấu hình nhà cung cấp</div><div class="panel-body">
    <div class="alert alert-info"><strong>Cấu hình nhà cung cấp được quản lý tại Salesrep DMS Platform.</strong><br>CRM Landlord chỉ đọc trạng thái mirror an toàn; không nhập hoặc chỉnh sửa API key, secret hay thông tin xác thực tại đây.</div>
    <p class="text-muted tw-mb-2">Platform host: <code><?php echo html_escape(parse_url($providerUrl, PHP_URL_HOST) ?: 'salesrep.test'); ?></code></p>
    <a class="btn btn-primary" href="<?php echo html_escape($providerUrl); ?>">Mở cấu hình nền tảng</a>
  </div></div>

  <div class="panel_s"><div class="panel-heading">Trạng thái nhà cung cấp từ Salesrep Platform</div><div class="panel-body">
    <?php if (!$mirrors) { ?><div class="alert alert-warning tw-mb-0">Chưa có dữ liệu kiểm tra từ Salesrep Platform.</div><?php } else { ?>
      <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Nhà cung cấp</th><th>Trạng thái</th><th>Kiểm tra gần nhất</th><th>Thông tin an toàn</th></tr></thead><tbody>
      <?php foreach ($mirrors as $mirror) { $code = (string) ($mirror['provider'] ?? ''); ?>
        <tr><td><?php echo html_escape($providerNames[$code] ?? $code); ?></td><td><span class="label label-<?php echo $statusLabel($mirror) === 'Kiểm tra thất bại' ? 'danger' : ($statusLabel($mirror) === 'Đã kiểm tra' ? 'success' : 'default'); ?>"><?php echo html_escape($statusLabel($mirror)); ?></span></td><td><?php echo html_escape((string) ($mirror['last_checked_at'] ?? 'Chưa có')); ?></td><td><?php echo !empty($mirror['fingerprint']) ? 'Đã nhận dấu vân tay an toàn' : 'Chưa có dữ liệu kiểm tra'; ?></td></tr>
      <?php } ?>
      </tbody></table></div>
    <?php } ?>
  </div></div>

  <?php if (!empty($validation)) { ?><div class="panel_s"><div class="panel-heading">Kết quả kiểm tra tenant</div><div class="panel-body"><p><strong>Lần kiểm tra:</strong> <?php echo html_escape((string) ($validation['tested_at'] ?? '')); ?> · <strong>Tenant cần xử lý:</strong> <?php echo (int) ($validation['failed_count'] ?? 0); ?></p><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Doanh nghiệp</th><th>Phiên bản</th><th>Kết nối</th><th>Kết quả</th></tr></thead><tbody><?php foreach ((array) ($validation['tenants'] ?? []) as $row) { ?><tr><td><?php echo html_escape((string) ($row['tenant_name'] ?? '')); ?></td><td>v<?php echo (int) ($row['config_version'] ?? 1); ?></td><td><?php echo !empty($row['success']) ? 'Đã nhận cấu hình' : 'Cần xử lý'; ?></td><td><?php echo html_escape((string) ($row['message'] ?? '')); ?></td></tr><?php } ?></tbody></table></div></div></div><?php } ?>

  <div class="row">
    <?php foreach ([['Tenant DMS','total','info'],['Đang hoạt động','active','success'],['Cần xử lý','pending_approval','warning'],['Lỗi đồng bộ','sync_error','danger']] as $card) { ?><div class="col-md-3 col-sm-6"><div class="panel_s"><div class="panel-body"><div class="text-uppercase text-muted small"><?php echo html_escape($card[0]); ?></div><div class="tw-text-3xl tw-font-semibold"><?php echo (int) ($summary[$card[1]] ?? 0); ?></div><span class="label label-<?php echo $card[2]; ?>">Toàn hệ thống</span></div></div></div><?php } ?>
  </div>

  <div class="panel_s"><div class="panel-heading">Khu vực quản lý</div><div class="panel-body"><div class="row">
    <?php foreach ([['Gói DMS','Cấu hình gói, hạn mức và tenant đang sử dụng.','kt_saas/dms_packages','fa-box'],['Tenant DMS','Xem trạng thái workspace và subscription theo tenant.','kt_integration_hub/dms_landlord_subscriptions','fa-building'],['Sức khỏe CRM ↔ DMS','Theo dõi đồng bộ, lỗi và tenant cần xử lý.','kt_integration_hub/dms_landlord_dashboard','fa-heart-pulse'],['Cấu hình nền tảng','Mở Salesrep DMS Platform để lưu hoặc kiểm tra provider.',$providerUrl,'fa-sliders']] as $item) { ?><div class="col-md-6"><a class="panel panel-default tw-block tw-p-4 tw-mb-3" href="<?php echo html_escape(str_starts_with($item[2], 'http') ? $item[2] : admin_url($item[2])); ?>"><h5 class="tw-mt-0"><i class="fa <?php echo html_escape($item[3]); ?> tw-mr-2"></i><?php echo html_escape($item[0]); ?></h5><p class="text-muted tw-mb-0"><?php echo html_escape($item[1]); ?></p></a></div><?php } ?>
  </div></div></div>

  <div class="panel_s"><div class="panel-heading">Gói DMS hiện có</div><div class="panel-body"><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Gói</th><th>Chu kỳ</th><th>Mặc định</th><th></th></tr></thead><tbody><?php foreach ($packages as $code => $package) { ?><tr><td><strong><?php echo html_escape((string) ($package['name'] ?? $code)); ?></strong><br><span class="text-muted"><?php echo html_escape((string) $code); ?></span></td><td><?php echo html_escape(implode(', ', array_map(static fn ($period) => ['monthly' => 'Tháng', 'quarterly' => 'Quý', 'yearly' => 'Năm'][$period] ?? $period, (array) ($package['allowed_billing_periods'] ?? [])))); ?></td><td><?php echo html_escape((string) ($package['default_billing_period'] ?? 'monthly')); ?></td><td class="text-right"><a class="btn btn-default btn-sm" href="<?php echo admin_url('kt_saas/dms_packages/' . rawurlencode((string) $code)); ?>">Cấu hình</a></td></tr><?php } ?></tbody></table></div></div></div>
</div></div>
<?php init_tail(); ?>
