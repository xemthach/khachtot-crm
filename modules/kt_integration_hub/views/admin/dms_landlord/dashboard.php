<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$dashboard = is_array($dashboard ?? null) ? $dashboard : [];
$summary = (array) ($dashboard['summary'] ?? []);
$attention = (array) ($dashboard['attention'] ?? []);
$actions = (array) ($dashboard['recent_actions'] ?? []);
$syncFailures = (array) ($dashboard['sync_failures'] ?? []);
$mapProvider = (array) ($dashboard['map_provider'] ?? []);
$supportPolicy = (array) ($dashboard['support_policy'] ?? []);
$crmReadinessRows = is_array($crm_dms_readiness ?? null) ? $crm_dms_readiness : [];
$providerUrl = function_exists('kt_integration_hub_dms_base_domain') ? 'https://' . kt_integration_hub_dms_base_domain([], []) . '/platform/providers' : 'https://salesrep.test/platform/providers';
$providerNames = ['google_maps' => 'Google Maps', 'firebase' => 'Firebase', 'mail' => 'Email / SMTP', 'storage' => 'Lưu trữ', 'crm_bridge' => 'Kết nối CRM'];
$safeProviders = is_array($provider_safe_mirror ?? null) ? $provider_safe_mirror : [];
$providerStatus = static function (array $provider): string {
    $health = strtolower(trim((string) ($provider['health_status'] ?? '')));
    $status = strtolower(trim((string) ($provider['status'] ?? '')));
    if ($health === 'connection_failed') return 'Kiểm tra thất bại';
    if (in_array($health, ['ready', 'pass_live', 'pass_mock'], true)) return 'Đã kiểm tra';
    if ($status === 'active' || $health === 'pending_check') return 'Đã lưu';
    return 'Chưa có dữ liệu kiểm tra';
};
$e = static fn ($v) => html_escape((string) ($v === '' || $v === null ? 'Chưa có dữ liệu' : $v));
$display = static function ($value) use ($e) {
    $value = (string) $value;
    $map = [
        'Sync job failed.' => 'Tác vụ đồng bộ chưa thành công.',
        'job.retry' => 'Thử lại tác vụ đồng bộ',
        'projection_stale' => 'Dữ liệu đồng bộ đã cũ',
        'sync_error' => 'Lỗi đồng bộ',
        'Support view-only' => 'Chỉ xem khi được phân quyền',
    ];
    return $e($map[$value] ?? $value);
};
$cards = [
    ['label' => 'Tổng tenant DMS', 'key' => 'total', 'class' => 'info'],
    ['label' => 'Đang hoạt động', 'key' => 'active', 'class' => 'success'],
    ['label' => 'Tạm dừng', 'key' => 'suspended', 'class' => 'warning'],
    ['label' => 'Đã hủy', 'key' => 'cancelled', 'class' => 'danger'],
    ['label' => 'Chờ thanh toán', 'key' => 'pending_payment', 'class' => 'warning'],
    ['label' => 'Chờ duyệt/thay đổi gói', 'key' => 'pending_approval', 'class' => 'warning'],
    ['label' => 'Cần kiểm tra hạn mức', 'key' => 'quota_warning', 'class' => 'danger'],
    ['label' => 'Lỗi đồng bộ', 'key' => 'sync_error', 'class' => 'danger'],
    ['label' => 'Dữ liệu đồng bộ đã cũ', 'key' => 'projection_stale', 'class' => 'warning'],
    ['label' => 'Cấu hình workspace cần kiểm tra', 'key' => 'workspace_warning', 'class' => 'warning'],
    ['label' => 'Thiếu trạng thái thanh toán', 'key' => 'missing_payment_state', 'class' => 'default'],
];
?>
<div id="wrapper">
  <div class="content">
    <div class="panel_s"><div class="panel-heading">CRM ↔ DMS Health</div><div class="panel-body"><div class="row"><div class="col-md-4"><strong>Tenant đã kiểm tra</strong><p><?php echo count($crmReadinessRows); ?></p></div><div class="col-md-4"><strong>Có blocker</strong><p><?php echo count(array_filter($crmReadinessRows, static fn($row) => ($row['status'] ?? '') === 'BLOCKED')); ?></p></div><div class="col-md-4"><strong>Vấn đề đồng bộ</strong><p><?php echo array_sum(array_map(static fn($row) => (int)($row['open_issues'] ?? 0), $crmReadinessRows)); ?></p></div></div><p class="text-muted tw-mb-0">Chỉ hiển thị số lượng và trạng thái an toàn; không hiển thị payload đơn hàng, sản phẩm hoặc GPS.</p></div></div>
    <div class="row">
      <div class="col-md-8">
        <h4 class="tw-mb-1">Tổng quan DMS</h4>
        <p class="text-muted">Theo dõi gói DMS, trạng thái doanh nghiệp, hạn mức, thanh toán và sức khỏe đồng bộ toàn hệ thống.</p>
      </div>
      <div class="col-md-4 text-right">
        <a href="<?php echo admin_url('kt_integration_hub/dms_landlord_subscriptions'); ?>" class="btn btn-default">Danh sách tenant DMS</a>
        <a href="<?php echo admin_url('kt_saas/dms_packages'); ?>" class="btn btn-primary">Gói DMS</a>
      </div>
    </div>

    <div class="row">
      <?php foreach ($cards as $card) { ?>
        <div class="col-md-2 col-sm-4">
          <div class="panel_s">
            <div class="panel-body">
              <div class="text-uppercase text-muted small"><?php echo $e($card['label']); ?></div>
              <div class="tw-text-2xl tw-font-semibold"><?php echo (int) ($summary[$card['key']] ?? 0); ?></div>
              <span class="label label-<?php echo html_escape($card['class']); ?>"><?php echo (int) ($summary[$card['key']] ?? 0) > 0 ? 'Cần xử lý' : 'Ổn định'; ?></span>
            </div>
          </div>
        </div>
      <?php } ?>
    </div>

    <div class="row">
      <div class="col-md-8">
        <div class="panel_s">
          <div class="panel-heading">Tenant cần xử lý hôm nay</div>
          <div class="panel-body">
            <?php if (empty($attention)) { ?>
              <div class="alert alert-success tw-mb-0">Chưa có cảnh báo fleet cần xử lý.</div>
            <?php } else { ?>
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead><tr><th>Doanh nghiệp</th><th>Vấn đề</th><th>Nguyên nhân</th><th>Bước xử lý</th><th>Lần phát hiện</th><th></th></tr></thead>
                  <tbody>
                    <?php foreach ($attention as $row) { ?>
                      <tr>
                        <td><strong><?php echo $e($row['tenant_name'] ?? ''); ?></strong><br><span class="text-muted"><?php echo $e($row['tenant_code'] ?? ''); ?></span></td>
                        <td><?php echo $e($row['issue'] ?? ''); ?></td><td><?php echo $e($row['reason'] ?? 'Cần kiểm tra trạng thái chi tiết.'); ?></td>
                        <td><?php echo $e($row['next_action'] ?? ''); ?></td><td><?php echo $e($row['detected_at'] ?? 'Chưa có dữ liệu'); ?></td>
                        <td class="text-right"><a class="btn btn-default btn-sm" href="<?php echo $e($row['detail_url'] ?? '#'); ?>">Xem chi tiết</a></td>
                      </tr>
                    <?php } ?>
                  </tbody>
                </table>
              </div>
            <?php } ?>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="panel_s">
          <div class="panel-heading">Cấu hình nhà cung cấp toàn nền tảng</div>
          <div class="panel-body">
            <p class="text-muted">Trạng thái bên dưới chỉ lấy từ mirror an toàn của Salesrep DMS Platform. CRM không suy diễn từ cấu hình local cũ.</p>
            <?php if (!$safeProviders) { ?><div class="alert alert-warning">Chưa có dữ liệu kiểm tra từ Salesrep Platform.</div><?php } else { ?><p><strong>Số nhà cung cấp đã nhận trạng thái:</strong> <?php echo count($safeProviders); ?></p><?php } ?>
            <a class="btn btn-primary" href="<?php echo html_escape($providerUrl); ?>">Mở cấu hình nền tảng</a>
          </div>
        </div>
        <div class="panel_s">
          <div class="panel-heading">Quyền hỗ trợ vận hành</div>
          <div class="panel-body">
            <p><strong><?php echo $display($supportPolicy['label'] ?? 'Chỉ xem khi được phân quyền'); ?></strong></p>
            <p class="text-muted"><?php echo $e($supportPolicy['policy'] ?? 'Chưa cấu hình impersonation.'); ?></p>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-6">
        <div class="panel_s">
          <div class="panel-heading">Lỗi đồng bộ gần đây</div>
          <div class="panel-body">
            <?php if (empty($syncFailures)) { ?>
              <div class="alert alert-info tw-mb-0">Chưa có lỗi sync/projection gần đây.</div>
            <?php } else { ?>
              <table class="table table-striped"><thead><tr><th>Tenant</th><th>Event</th><th>Lỗi</th><th>Thời gian</th></tr></thead><tbody>
                <?php foreach ($syncFailures as $row) { $tenant = (array) ($row['tenant'] ?? []); ?>
                  <tr><td><?php echo $e($tenant['company_name'] ?? ''); ?></td><td><?php echo $display($row['event'] ?? ''); ?></td><td><?php echo $display($row['message'] ?? ''); ?></td><td><?php echo $e($row['created_at'] ?? ''); ?></td></tr>
                <?php } ?>
              </tbody></table>
            <?php } ?>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="panel_s">
          <div class="panel-heading">Lịch sử thao tác landlord gần đây</div>
          <div class="panel-body">
            <?php if (empty($actions)) { ?>
              <div class="alert alert-info tw-mb-0">Chưa có lịch sử thao tác landlord.</div>
            <?php } else { ?>
              <table class="table table-striped"><thead><tr><th>Thời gian</th><th>Actor</th><th>Tenant</th><th>Action</th></tr></thead><tbody>
                <?php foreach ($actions as $action) { ?>
                  <tr><td><?php echo $e($action['created_at'] ?? ''); ?></td><td><?php echo $e($action['actor_email'] ?? $action['actor_user_id'] ?? ''); ?></td><td><?php echo (int) ($action['tenant_id'] ?? 0); ?></td><td><?php echo $e($action['action'] ?? ''); ?></td></tr>
                <?php } ?>
              </tbody></table>
            <?php } ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="row"><div class="col-md-12"><div class="panel_s"><div class="panel-body">
  <div class="tw-flex tw-items-center tw-justify-between"><div><h4 class="tw-mt-0">Trạng thái nhà cung cấp DMS</h4><p class="text-muted">Chỉ đọc từ Salesrep DMS Platform. CRM không lưu hoặc chỉnh sửa khóa bí mật.</p></div><a class="btn btn-primary" href="<?php echo html_escape($providerUrl); ?>">Mở cấu hình nền tảng</a></div>
  <?php $safeProviders = is_array($provider_safe_mirror ?? null) ? $provider_safe_mirror : []; ?>
  <?php if (!$safeProviders) { ?><div class="alert alert-info">Chưa có dữ liệu kiểm tra từ Salesrep Platform.</div><?php } else { ?>
  <table class="table"><thead><tr><th>Nhà cung cấp</th><th>Trạng thái</th><th>Kiểm tra gần nhất</th><th>Thông tin an toàn</th></tr></thead><tbody>
  <?php foreach ($safeProviders as $provider) { $providerCode = (string) ($provider['provider'] ?? ''); $label = $providerStatus($provider); ?><tr><td><?php echo html_escape($providerNames[$providerCode] ?? $providerCode); ?></td><td><?php echo html_escape($label); ?></td><td><?php echo html_escape((string) ($provider['last_checked_at'] ?? 'Chưa có')); ?></td><td><?php echo !empty($provider['fingerprint']) ? 'Đã nhận dấu vân tay an toàn' : 'Chưa có dữ liệu kiểm tra'; ?></td></tr><?php } ?>
  </tbody></table><?php } ?>
</div></div></div></div>
<?php init_tail(); ?>
