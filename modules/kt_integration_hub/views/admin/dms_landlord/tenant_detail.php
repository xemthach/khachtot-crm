<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$d = is_array($detail ?? null) ? $detail : [];
$tenant = (array) ($d['tenant'] ?? []);
$addon = (array) ($d['addon'] ?? []);
$package = (array) ($d['package'] ?? []);
$usage = (array) ($d['usage'] ?? []);
$features = (array) ($d['features'] ?? []);
$limits = (array) ($d['limits'] ?? []);
$overrides = (array) ($d['overrides'] ?? []);
$packages = (array) ($d['packages'] ?? []);
$billing = (array) ($d['billing'] ?? []);
$workspace = (array) ($d['workspace'] ?? []);
$health = (array) ($d['health'] ?? []);
$actions = (array) ($d['actions'] ?? []);
$latestOrder = (array) ($d['latest_dms_order'] ?? []);
$connection = (array) ($d['connection'] ?? []);
$crmReadiness = is_array($crm_dms_readiness ?? null) ? $crm_dms_readiness : [];
$mappingIssues = is_array($mapping_issues ?? null) ? $mapping_issues : [];
$tenantId = (int) ($tenant['id'] ?? 0);
$e = static fn ($v) => html_escape((string) ($v === '' || $v === null ? 'Chưa có dữ liệu' : $v));
$checked = static fn ($value) => !empty($value) ? 'checked' : '';
$statusLabels = [
  'none'=>'Chưa kích hoạt','payment_pending'=>'Chờ thanh toán','pending'=>'Đang chờ','verified'=>'Đã xác minh',
  'paid'=>'Đã thanh toán','active'=>'Đang hoạt động','provisioning_pending'=>'Chờ kích hoạt',
  'provisioning_running'=>'Đang kích hoạt','workspace_ready'=>'Đã sẵn sàng','ready'=>'Sẵn sàng',
  'blocked'=>'Cần xử lý','failed'=>'Cần xử lý','cancelled'=>'Đã hủy','suspended'=>'Tạm ngưng','not_started'=>'Chưa bắt đầu','not_ready'=>'Chưa sẵn sàng',
];
$statusLabel = static fn ($value) => $statusLabels[strtolower(trim((string)$value))] ?? 'Chưa xác định';
?>
<div id="wrapper">
  <div class="content">
    <div class="panel_s"><div class="panel-heading">CRM Readiness</div><div class="panel-body"><div class="row"><div class="col-md-3"><strong>Trạng thái</strong><p><?php echo $e($crmReadiness['status'] ?? 'UNKNOWN'); ?></p></div><div class="col-md-3"><strong>Nhân sự đồng bộ</strong><p><?php echo (int)($crmReadiness['staff_mappings'] ?? 0); ?></p></div><div class="col-md-3"><strong>Điểm bán đồng bộ</strong><p><?php echo (int)($crmReadiness['customer_store_mappings'] ?? 0); ?></p></div><div class="col-md-3"><strong>Sản phẩm đồng bộ</strong><p><?php echo (int)($crmReadiness['product_mappings'] ?? 0); ?></p></div></div><p class="text-muted tw-mb-0">Vấn đề mở: <?php echo count($mappingIssues); ?>. Landlord chỉ xem trạng thái, không sửa dữ liệu vận hành tenant tại đây.</p></div></div>
    <div class="panel_s" data-r301-salesrep-connection><div class="panel-heading">Kết nối Salesrep</div><div class="panel-body">
      <div class="row">
        <div class="col-md-3"><strong>Trạng thái</strong><p><?php echo !empty($connection['configured']) ? 'Đã cấu hình' : 'Cần cấu hình'; ?></p></div>
        <div class="col-md-3"><strong>Bridge URL</strong><p><?php echo $e($connection['base_url'] ?? ''); ?></p></div>
        <div class="col-md-2"><strong>Khóa kết nối</strong><p><?php echo !empty($connection['key_configured']) ? 'Đã cấu hình' : 'Còn thiếu'; ?></p></div>
        <div class="col-md-2"><strong>Secret</strong><p><?php echo !empty($connection['secret_configured']) ? 'Đã cấu hình an toàn' : 'Còn thiếu'; ?></p></div>
        <div class="col-md-2"><strong>Config</strong><p>v<?php echo (int) ($connection['config_version'] ?? 1); ?></p></div>
      </div>
      <p><strong>Handshake thành công gần nhất:</strong> <?php echo $e($connection['last_successful_handshake'] ?? ''); ?></p>
      <?php if (!empty($connection['last_error'])) { ?><div class="alert alert-warning"><?php echo $e($connection['last_error']); ?></div><?php } ?>
      <div class="row">
        <div class="col-md-8"><?php echo form_open(admin_url('kt_integration_hub/dms_landlord_connection_save/' . $tenantId)); ?>
          <div class="input-group"><input class="form-control" name="base_url" type="url" required value="<?php echo html_escape((string) ($connection['base_url'] ?? (!empty($workspace['host']) ? 'https://' . $workspace['host'] : ''))); ?>"><span class="input-group-btn"><button class="btn btn-primary" type="submit"><?php echo !empty($connection['configured']) ? 'Cập nhật kết nối' : 'Tạo kết nối'; ?></button></span></div>
        <?php echo form_close(); ?></div>
        <div class="col-md-4"><?php echo form_open(admin_url('kt_integration_hub/dms_landlord_connection_test/' . $tenantId)); ?><button class="btn btn-default" type="submit" <?php echo empty($connection['configured']) ? 'disabled' : ''; ?>>Kiểm tra kết nối</button><?php echo form_close(); ?></div>
      </div>
      <p class="text-muted tw-mt-2">Secret không được hiển thị. Thao tác cập nhật được ghi audit log.</p>
    </div></div>

    <div class="row">
      <div class="col-md-8">
        <h4 class="tw-mb-1">Chi tiết DMS tenant: <?php echo $e($tenant['company_name'] ?? ''); ?></h4>
        <p class="text-muted">Quản lý subscription, gói, feature/quota, billing, workspace và sync/projection health.</p>
      </div>
      <div class="col-md-4 text-right">
        <a href="<?php echo admin_url('kt_integration_hub/dms_landlord_subscriptions'); ?>" class="btn btn-default">Danh sách tenant DMS</a>
      </div>
    </div>

    <div class="row">
      <div class="col-md-4"><div class="panel_s"><div class="panel-body">
        <h5>Hồ sơ tenant</h5>
        <p><strong><?php echo $e($tenant['company_name'] ?? ''); ?></strong></p>
        <p class="text-muted"><?php echo $e($tenant['tenant_code'] ?? ''); ?> | <?php echo $e($tenant['owner_email'] ?? ''); ?></p>
        <p>CRM: <?php echo $e($workspace['crm_host'] ?? ''); ?></p>
        <p>DMS: <?php echo $e($workspace['host'] ?? ''); ?></p>
      </div></div></div>
      <div class="col-md-4"><div class="panel_s"><div class="panel-body">
        <h5>Gói DMS hiện tại</h5>
        <p><strong><?php echo $e($package['name'] ?? ($addon['package_code'] ?? 'Chưa mua')); ?></strong></p>
        <p>Trạng thái: <span class="label label-<?php echo strtolower((string) ($addon['status'] ?? '')) === 'active' ? 'success' : 'warning'; ?>"><?php echo html_escape($statusLabel($addon['status'] ?? 'none')); ?></span></p>
        <p>Hết hạn: <?php echo $e($addon['ends_at'] ?? ''); ?></p>
      </div></div></div>
      <div class="col-md-4"><div class="panel_s"><div class="panel-body">
        <h5>Đồng bộ / Sức khỏe projection</h5>
        <?php $projection = (array) ($health['projections'] ?? []); ?>
        <p>Projection: <span class="label label-<?php echo (($projection['record_count'] ?? 0) > 0) ? 'success' : 'default'; ?>"><?php echo $e($projection['last_job_status'] ?? 'no_data'); ?></span></p>
        <p>Bản ghi: <?php echo (int) ($projection['record_count'] ?? 0); ?></p>
        <p>Lần đồng bộ cuối: <?php echo $e($projection['last_successful_sync_at'] ?? ''); ?></p>
      </div></div></div>
    </div>

    <div class="panel_s" data-r28-tenant-latest-order><div class="panel-heading">Đơn DMS gần nhất & tiến trình kích hoạt</div><div class="panel-body">
      <?php if (empty($latestOrder)) { ?>
        <div class="alert alert-info tw-mb-0">Doanh nghiệp chưa có đơn kích hoạt DMS.</div>
      <?php } else { ?>
        <div class="row">
          <div class="col-md-3"><div class="text-muted small">Gói DMS</div><strong><?php echo $e($latestOrder['package_name'] ?? ''); ?></strong></div>
          <div class="col-md-2"><div class="text-muted small">Thanh toán</div><strong><?php echo html_escape($statusLabel($latestOrder['payment_status'] ?? '')); ?></strong></div>
          <div class="col-md-2"><div class="text-muted small">Xác minh</div><strong><?php echo html_escape($statusLabel($latestOrder['verification_status'] ?? '')); ?></strong></div>
          <div class="col-md-2"><div class="text-muted small">Khởi tạo</div><strong><?php echo html_escape($statusLabel($latestOrder['provisioning_status'] ?? '')); ?></strong></div>
          <div class="col-md-2"><div class="text-muted small">Workspace</div><strong><?php echo html_escape($statusLabel($latestOrder['workspace_status'] ?? '')); ?></strong></div>
          <div class="col-md-1 text-right"><a class="btn btn-default btn-sm" href="<?php echo admin_url('kt_saas/dms_order/' . (int)$latestOrder['id']); ?>">Chi tiết</a></div>
        </div>
      <?php } ?>
    </div></div>

    <div class="row">
      <div class="col-md-6"><div class="panel_s"><div class="panel-heading">Workspace / Domain</div><div class="panel-body">
        <p><strong>CRM host:</strong> <?php echo $e($workspace['crm_host'] ?? ''); ?></p>
        <p><strong>DMS host:</strong> <?php echo $e($workspace['host'] ?? ''); ?></p>
        <p><strong>Custom domain:</strong> <?php echo $e($workspace['custom_domain'] ?? ''); ?></p>
        <p class="text-muted">R17 chỉ hiển thị trạng thái workspace/domain. Luồng đổi domain/custom domain sẽ được xử lý ở sprint domain/map/provider.</p>
      </div></div></div>
      <div class="col-md-6"><div class="panel_s"><div class="panel-heading">Map/API provider & Support policy</div><div class="panel-body">
        <p><strong>Map provider:</strong> Chưa cấu hình</p>
        <p><strong>API key:</strong> <span class="label label-default">Không hiển thị</span></p>
        <p><strong>Support access:</strong> View-only placeholder</p>
          <p class="text-muted">Không hiển thị thông tin nhạy cảm trong UI tenant hoặc landlord. Support/Ops chỉ xem khi có quyền landlord; thao tác thay đổi phải qua POST/CSRF và audit log.</p>
      </div></div></div>
    </div>

    <div class="row">
      <div class="col-md-6"><div class="panel_s"><div class="panel-heading">Đổi gói DMS</div><div class="panel-body">
        <?php echo form_open(admin_url('kt_integration_hub/dms_landlord_change_package/' . $tenantId)); ?>
          <div class="form-group">
            <label>Gói đích</label>
            <select name="package_code" class="form-control">
              <?php foreach ($packages as $code => $pkg) { ?>
                <option value="<?php echo html_escape((string) $code); ?>" <?php echo ((string) ($addon['package_code'] ?? '') === (string) $code) ? 'selected' : ''; ?>><?php echo $e($pkg['name'] ?? $code); ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="form-group">
            <label>Loại thay đổi</label>
            <select name="change_type" class="form-control">
              <option value="upgrade">Nâng cấp</option>
              <option value="downgrade">Hạ cấp</option>
            </select>
          </div>
          <div class="checkbox checkbox-primary"><input type="checkbox" name="confirm_change" value="1" id="confirm_change"><label for="confirm_change">Tôi xác nhận thay đổi gói và đã xem tác động quota.</label></div>
          <button type="submit" class="btn btn-primary">Lưu thay đổi gói</button>
        <?php echo form_close(); ?>
      </div></div></div>

      <div class="col-md-6"><div class="panel_s"><div class="panel-heading">Thao tác subscription</div><div class="panel-body">
        <?php foreach (['active' => 'Kích hoạt', 'suspended' => 'Tạm ngưng', 'cancelled' => 'Hủy'] as $status => $label) { ?>
          <?php echo form_open(admin_url('kt_integration_hub/dms_landlord_update_status/' . $tenantId), ['style' => 'display:inline-block;margin-right:6px;margin-bottom:8px;']); ?>
            <input type="hidden" name="status" value="<?php echo html_escape($status); ?>">
            <input type="hidden" name="reason" value="Landlord action from DMS Control Center">
            <button type="submit" class="btn btn-<?php echo $status === 'active' ? 'success' : ($status === 'suspended' ? 'warning' : 'danger'); ?>" onclick="return confirm('Xác nhận <?php echo html_escape($label); ?> DMS tenant này?');"><?php echo html_escape($label); ?></button>
          <?php echo form_close(); ?>
        <?php } ?>
        <p class="text-muted">Tất cả action dùng POST/CSRF và được ghi audit log.</p>
      </div></div></div>
    </div>

    <div class="panel_s"><div class="panel-heading">Tính năng & hạn mức override</div><div class="panel-body">
      <?php echo form_open(admin_url('kt_integration_hub/dms_landlord_save_overrides/' . $tenantId)); ?>
      <div class="row">
        <?php foreach (['dms_enabled' => 'DMS enabled', 'orders_enabled' => 'Đơn hàng', 'visits_enabled' => 'Visit', 'checkin_review_enabled' => 'Duyệt check-in', 'live_tracking_enabled' => 'Live tracking', 'inventory_enabled' => 'Tồn kho', 'finance_view_enabled' => 'Tài chính', 'reports_enabled' => 'Báo cáo', 'map_enabled' => 'Bản đồ', 'mobile_app_enabled' => 'Mobile app'] as $key => $label) { ?>
          <div class="col-md-3"><div class="checkbox checkbox-primary"><input type="checkbox" name="features[<?php echo html_escape($key); ?>]" value="1" id="f_<?php echo html_escape($key); ?>" <?php echo $checked(($overrides['features'][$key] ?? null) !== null ? $overrides['features'][$key] : ($features[strtoupper($key)] ?? false)); ?>><label for="f_<?php echo html_escape($key); ?>"><?php echo html_escape($label); ?></label></div></div>
        <?php } ?>
      </div>
      <div class="row">
        <?php foreach (['user_limit' => 'User limit', 'store_limit' => 'Store limit', 'product_limit' => 'Product limit', 'checkin_monthly_limit' => 'Check-in monthly', 'photo_limit' => 'Photo limit', 'order_monthly_limit' => 'Order monthly', 'report_export_limit' => 'Report export', 'retention_months' => 'Retention months', 'api_webhook_limit' => 'API/Webhook limit'] as $key => $label) { ?>
          <div class="col-md-4"><?php echo render_input('quota[' . $key . ']', $label, (string) (($overrides['quota'][$key] ?? null) !== null ? $overrides['quota'][$key] : ($limits[strtoupper($key)] ?? 0)), 'number', ['min' => '0']); ?></div>
        <?php } ?>
      </div>
      <button type="submit" class="btn btn-primary">Lưu feature/quota</button>
      <?php echo form_close(); ?>
    </div></div>

    <div class="row">
      <div class="col-md-6"><div class="panel_s"><div class="panel-heading">Hạn mức & sử dụng</div><div class="panel-body">
        <?php foreach ((array) ($usage['rows'] ?? []) as $row) { ?>
          <p><?php echo $e($row['label'] ?? 'Usage'); ?>: <strong><?php echo (int) ($row['used'] ?? 0); ?> / <?php echo (int) ($row['limit'] ?? 0) > 0 ? (int) $row['limit'] : 'Không giới hạn'; ?></strong></p>
        <?php } ?>
      </div></div></div>
      <div class="col-md-6"><div class="panel_s"><div class="panel-heading">Lịch sử billing / mua hàng</div><div class="panel-body">
        <?php if (empty($billing['invoices'])) { ?><div class="alert alert-info">Chưa có dữ liệu thanh toán DMS.</div><?php } ?>
        <?php foreach ((array) ($billing['invoices'] ?? []) as $invoice) { ?><p>Hóa đơn <?php echo $e($invoice['invoice_number'] ?? $invoice['id'] ?? ''); ?> - <?php echo html_escape($statusLabel($invoice['status'] ?? '')); ?> - <?php echo number_format((float) ($invoice['grand_total'] ?? 0), 0, ',', '.'); ?> <?php echo $e($invoice['currency'] ?? 'VND'); ?></p><?php } ?>
        <?php foreach ((array) ($billing['payments'] ?? []) as $payment) { ?><p>Thanh toán <?php echo $e($payment['payment_reference'] ?? ''); ?> - <?php echo html_escape($statusLabel($payment['status'] ?? '')); ?> - <?php echo number_format((float) ($payment['amount'] ?? 0), 0, ',', '.'); ?> <?php echo $e($payment['currency'] ?? 'VND'); ?></p><?php } ?>
      </div></div></div>
    </div>

    <?php $health=(array)($process_health??[]); $cron=(array)($health['cron']??[]); $provision=(array)($health['provision']??[]); $staff=(array)($health['staff_sync']??[]); ?>
    <div class="panel_s" data-r29-tenant-process-health><div class="panel-heading">Tình trạng tiến trình DMS</div><div class="panel-body"><div class="row">
      <div class="col-md-3"><strong>Cron/worker</strong><p><?php echo ($cron['status']??'')==='running'?'Đang chạy':'Cần chạy cron/worker'; ?></p></div>
      <div class="col-md-3"><strong>Khởi tạo workspace</strong><p>Chờ: <?php echo (int)($provision['queued']??0); ?> · Chạy: <?php echo (int)($provision['running']??0); ?> · Lỗi: <?php echo (int)($provision['failed']??0); ?></p></div>
      <div class="col-md-3"><strong>Đồng bộ nhân sự</strong><p>Chờ: <?php echo (int)($staff['queued']??0); ?> · Lỗi: <?php echo (int)($staff['failed']??0); ?></p></div>
      <div class="col-md-3"><strong>SSO</strong><p><?php echo !empty($detail['connection'])?'Sẵn sàng':'Cần kiểm tra kết nối'; ?></p></div>
    </div></div></div>

    <?php $supportPreview = (array) ($support_override_preview ?? []); $preview = (array) ($supportPreview['preview'] ?? []); ?>
    <div class="panel_s" data-r31-support-override-preview><div class="panel-heading">Hỗ trợ có kiểm soát</div><div class="panel-body">
      <p class="text-muted">Landlord chỉ được hỗ trợ vận hành bằng hành động có preview, lý do, xác nhận và audit log. Hành động này không thay đổi phạm vi dữ liệu nội bộ do tenant quản lý.</p>
      <div class="row">
        <div class="col-md-3"><strong>Nhân sự trong phạm vi</strong><p><?php echo (int) ($preview['staff_rows'] ?? 0); ?></p></div>
        <div class="col-md-3"><strong>Lỗi khách hàng/điểm bán</strong><p><?php echo (int) ($preview['failed_customer_jobs'] ?? 0); ?></p></div>
        <div class="col-md-3"><strong>Lỗi sản phẩm</strong><p><?php echo (int) ($preview['failed_product_jobs'] ?? 0); ?></p></div>
        <div class="col-md-3"><strong>Tác động dữ liệu</strong><p><?php echo !empty($preview['destructive']) ? 'Có rủi ro' : 'Không phá dữ liệu'; ?></p></div>
      </div>
      <?php echo form_open(admin_url('kt_integration_hub/dms_landlord_support_override_run/' . (int) ($detail['tenant']['id'] ?? 0))); ?>
        <div class="row">
          <div class="col-md-4"><select name="support_action" class="form-control"><option value="retry_failed_jobs">Thử lại tác vụ lỗi</option><option value="run_worker_once">Chạy worker một vòng</option></select></div>
          <div class="col-md-5"><input type="text" name="support_reason" class="form-control" placeholder="Lý do hỗ trợ tenant"></div>
          <div class="col-md-3"><label><input type="checkbox" name="confirm_preview" value="1"> Đã xem preview</label></div>
        </div>
        <button type="submit" class="btn btn-default tw-mt-2">Thực hiện hỗ trợ</button>
      <?php echo form_close(); ?>
    </div></div>

    <div class="panel_s"><div class="panel-heading">Lịch sử thao tác landlord</div><div class="panel-body">
      <?php if (empty($actions)) { ?><div class="alert alert-info">Chưa có audit log DMS cho tenant này.</div><?php } else { ?>
      <table class="table table-striped"><thead><tr><th>Thời gian</th><th>Actor</th><th>Action</th><th>IP</th></tr></thead><tbody>
        <?php foreach ($actions as $action) { ?><tr><td><?php echo $e($action['created_at'] ?? ''); ?></td><td><?php echo $e($action['actor_email'] ?? $action['actor_user_id'] ?? ''); ?></td><td><?php echo $e($action['action'] ?? ''); ?></td><td><?php echo $e($action['ip'] ?? ''); ?></td></tr><?php } ?>
      </tbody></table>
      <?php } ?>
    </div></div>
  </div>
</div>
<?php init_tail(); ?>
