<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$business = is_array($business_dashboard ?? null) ? $business_dashboard : [];
$today = (array) ($business['today'] ?? []);
$field = (array) ($business['field'] ?? []);
$attention = (array) ($business['attention'] ?? []);
$month = (array) ($business['month'] ?? []);
$tenantSettings = is_array($tenant_settings ?? null) ? $tenant_settings : [];
$actions = is_array($actions ?? null) ? $actions : [];
$readiness = is_array($readiness ?? null) ? $readiness : [];
$consoleStatus = is_array($console_status ?? null) ? $console_status : [];
$workspace = is_array($workspace ?? null) ? $workspace : [];
$recentCheckins = is_array($recent_checkins ?? null) ? $recent_checkins : [];
$recentVisits = is_array($recent_visits ?? null) ? $recent_visits : [];
$usageRows = (array) ($consoleStatus['usage']['rows'] ?? []);
$displayValue = static function ($value) {
    if ($value === null || $value === '') {
        return 'Chưa có dữ liệu';
    }
    if (is_numeric($value)) {
        return number_format((float) $value, 0);
    }
    return (string) $value;
};
$displayMoney = static fn ($value) => kt_dms_ui_money($value);
$cardsToday = [
    ['label' => 'Đơn hàng hôm nay', 'value' => $today['orders'] ?? 0, 'hint' => 'Đã đồng bộ từ DMS'],
    ['label' => 'Doanh số hôm nay', 'value' => $today['sales_amount'] ?? null, 'hint' => 'Từ đơn hàng DMS', 'money' => true],
    ['label' => 'Giá trị đã có hóa đơn', 'value' => $today['revenue'] ?? null, 'hint' => 'Từ đơn đã có hóa đơn CRM', 'money' => true],
    ['label' => 'Thanh toán hôm nay', 'value' => $today['payment'] ?? null, 'hint' => 'Nguồn thanh toán: CRM', 'money' => true],
    ['label' => 'Điểm bán hoạt động', 'value' => $today['customers_or_stores'] ?? 0, 'hint' => 'Check-in + visit hôm nay'],
    ['label' => 'Điểm bán mới', 'value' => $today['new_customers_or_stores'] ?? null, 'hint' => 'Cần đồng bộ ngày tạo customer/store'],
];
$fieldCards = [
    ['label' => 'Sales online', 'value' => $field['online_sales'] ?? 0],
    ['label' => 'Sales offline', 'value' => $field['offline_sales'] ?? 0],
    ['label' => 'Check-in hôm nay', 'value' => $field['checkins'] ?? 0],
    ['label' => 'Lịch viếng thăm hoàn tất', 'value' => $field['visits_completed'] ?? 0],
    ['label' => 'Lịch viếng thăm bị lỡ', 'value' => $field['missed_visits'] ?? 0],
    ['label' => 'Cần chú ý', 'value' => $field['needs_attention'] ?? 0],
];
$attentionRows = [
    ['label' => 'Đơn chờ xử lý', 'value' => $attention['pending_orders'] ?? 0, 'url' => admin_url('kt_integration_hub/dms_orders_invoices')],
    ['label' => 'Đơn lỗi đồng bộ', 'value' => $attention['failed_orders'] ?? 0, 'url' => admin_url('kt_integration_hub/dms_orders_invoices?tab=errors')],
    ['label' => 'Check-in bất thường', 'value' => $attention['abnormal_checkins'] ?? 0, 'url' => admin_url('kt_integration_hub/dms_field_activity?tab=exceptions')],
    ['label' => 'Lịch viếng thăm chưa hoàn tất', 'value' => $attention['unfinished_visits'] ?? 0, 'url' => admin_url('kt_integration_hub/dms_field_activity?tab=visits')],
    ['label' => 'Khu vực sync cần xem', 'value' => $attention['failed_sync_areas'] ?? 0, 'url' => admin_url('kt_integration_hub/dms_sync_health')],
    ['label' => 'Sản phẩm sắp hết', 'value' => $attention['low_stock'] ?? null, 'url' => admin_url('kt_integration_hub/dms_store_products')],
];
$monthCards = [
    ['label' => 'Đơn hàng tháng này', 'value' => $month['orders'] ?? 0],
    ['label' => 'Doanh số tháng này', 'value' => $month['sales_amount'] ?? null, 'money' => true],
    ['label' => 'Giá trị đã có hóa đơn', 'value' => $month['revenue'] ?? null, 'money' => true],
    ['label' => 'Thanh toán tháng này', 'value' => $month['payment'] ?? null, 'money' => true],
    ['label' => 'Khách hàng mới', 'value' => $month['new_customers_or_stores'] ?? null],
    ['label' => 'Lượt phủ điểm bán', 'value' => $month['store_coverage'] ?? 0],
];
$activityChart = [
    ['label' => 'Đơn hàng hôm nay', 'value' => (int) ($today['orders'] ?? 0)],
    ['label' => 'Check-in hôm nay', 'value' => (int) ($field['checkins'] ?? 0)],
    ['label' => 'Lịch viếng thăm hoàn tất', 'value' => (int) ($field['visits_completed'] ?? 0)],
    ['label' => 'Điểm bán hoạt động', 'value' => (int) ($today['customers_or_stores'] ?? 0)],
];
$activityMaximum = max(array_map(static fn ($row) => (int) $row['value'], $activityChart));
?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-8">
        <h4 class="tw-mb-1">Tổng quan thị trường</h4>
        <p class="text-muted">Trung tâm vận hành DMS trong CRM: bán hàng, đội sales, vị trí, check-in/visit, đơn hàng và việc cần xử lý.</p>
        <p class="text-muted small">Số liệu thanh toán ưu tiên hóa đơn và thanh toán CRM; dữ liệu DMS chỉ là readback khi CRM chưa có bản ghi tương ứng.</p>
      </div>
      <div class="col-md-4 text-right">
        <a href="<?php echo e(admin_url('kt_integration_hub/dms_sales_team_today')); ?>" class="btn btn-default">Đội sales</a>
        <a href="<?php echo e(admin_url('kt_integration_hub/dms_map_activity')); ?>" class="btn btn-default">Bản đồ</a>
        <?php if (!empty($actions['open_dms_url']) && ($actions['open_dms_mode'] ?? 'disabled') !== 'disabled') { ?>
          <a href="<?php echo e($actions['open_dms_url']); ?>" class="btn btn-primary">Mở app DMS</a>
        <?php } ?>
      </div>
    </div>

    <div class="panel_s"><div class="panel-heading">Tình hình bán hàng hôm nay</div><div class="panel-body">
      <div class="row">
        <?php foreach ($cardsToday as $card) { ?>
          <div class="col-md-2 col-sm-4"><div class="tw-py-2">
            <div class="text-uppercase text-muted small"><?php echo e($card['label']); ?></div>
            <div class="tw-text-2xl tw-font-semibold"><?php echo e(!empty($card['money']) ? $displayMoney($card['value']) : $displayValue($card['value'])); ?></div>
            <div class="text-muted small"><?php echo e($card['hint']); ?></div>
          </div></div>
        <?php } ?>
      </div>
    </div></div>

    <div class="panel_s" data-r24-chart="hoat-dong-hom-nay"><div class="panel-heading">Hoạt động hôm nay</div><div class="panel-body">
      <?php if ($activityMaximum <= 0) { ?>
        <div class="alert alert-info tw-mb-0">Không có dữ liệu biểu đồ trong khoảng thời gian này.</div>
      <?php } else { ?>
        <?php foreach ($activityChart as $row) { $width = max(3, min(100, (int) round($row['value'] * 100 / $activityMaximum))); ?>
          <div class="tw-mb-3"><div class="tw-flex tw-justify-between tw-gap-4 tw-text-sm"><span><?php echo e($row['label']); ?></span><strong><?php echo e((string) $row['value']); ?></strong></div><div class="r24-chart-track" role="img" aria-label="<?php echo e($row['label'] . ': ' . $row['value']); ?>"><span style="width:<?php echo $width; ?>%"></span></div></div>
        <?php } ?>
      <?php } ?>
    </div></div>

    <div class="row">
      <div class="col-md-6"><div class="panel_s"><div class="panel-heading">Bản đồ vị trí đơn hàng hôm nay</div><div class="panel-body">
        <div class="alert alert-info tw-mb-0">Chưa có vị trí GPS lúc gửi đơn. CRM chỉ hiển thị vị trí điểm bán của đơn hàng khi điểm bán có tọa độ.</div>
      </div></div></div>
      <div class="col-md-6"><div class="panel_s"><div class="panel-heading">Bản đồ vị trí nhân viên hôm nay</div><div class="panel-body">
        <?php if (((int) ($field['online_sales'] ?? 0) + (int) ($field['stale_sales'] ?? 0)) <= 0) { ?>
          <div class="alert alert-info tw-mb-0">Chưa có live location. Mở app DMS để sales check-in hoặc bật live tracking.</div>
        <?php } else { ?>
          <div class="text-muted">Có <?php echo e((string) ((int) ($field['online_sales'] ?? 0) + (int) ($field['stale_sales'] ?? 0))); ?> nhân viên có dữ liệu vị trí. Xem chi tiết trong Bản đồ hoạt động.</div>
          <a class="btn btn-default tw-mt-2" href="<?php echo e(admin_url('kt_integration_hub/dms_map_activity')); ?>">Mở bản đồ hoạt động</a>
        <?php } ?>
      </div></div></div>
    </div>

    <div class="panel_s"><div class="panel-heading">Tình hình hoạt động thị trường</div><div class="panel-body">
      <div class="row">
        <?php foreach ($fieldCards as $card) { ?>
          <div class="col-md-2 col-sm-4"><div class="text-uppercase text-muted small"><?php echo e($card['label']); ?></div><div class="tw-text-2xl tw-font-semibold"><?php echo e(!empty($card['money']) ? $displayMoney($card['value']) : $displayValue($card['value'])); ?></div></div>
        <?php } ?>
      </div>
    </div></div>

    <div class="panel_s"><div class="panel-heading">Cần xử lý</div><div class="panel-body">
      <div class="row">
        <?php foreach ($attentionRows as $row) { ?>
          <div class="col-md-4"><a class="tw-block tw-py-2" href="<?php echo e($row['url']); ?>"><strong><?php echo e($row['label']); ?></strong><span class="pull-right"><?php echo e($displayValue($row['value'])); ?></span></a></div>
        <?php } ?>
      </div>
    </div></div>

    <div class="panel_s"><div class="panel-heading">Tháng này</div><div class="panel-body">
      <div class="row">
        <?php foreach ($monthCards as $card) { ?>
          <div class="col-md-2 col-sm-4"><div class="text-uppercase text-muted small"><?php echo e($card['label']); ?></div><div class="tw-text-2xl tw-font-semibold"><?php echo e(!empty($card['money']) ? $displayMoney($card['value']) : $displayValue($card['value'])); ?></div></div>
        <?php } ?>
      </div>
    </div></div>

    <div class="row">
      <div class="col-md-6"><div class="panel_s"><div class="panel-heading">Check-in mới nhất</div><div class="panel-body">
        <?php if (empty($recentCheckins)) { ?><div class="alert alert-info tw-mb-0">Chưa có check-in. Mở app DMS để tạo check-in đầu tiên.</div><?php } else { ?>
          <table class="table table-striped"><tbody><?php foreach ($recentCheckins as $row) { ?><tr><td><strong><?php echo e((string) ($row['staff_name'] ?? $row['crm_staff_id'] ?? '-')); ?></strong><br><span class="text-muted"><?php echo e((string) ($row['customer_name'] ?? $row['crm_customer_id'] ?? '-')); ?></span></td><td><?php echo e((string) ($row['checkin_at'] ?? '-')); ?></td><td><?php echo e((string) ($row['status'] ?? '-')); ?></td></tr><?php } ?></tbody></table>
        <?php } ?>
      </div></div></div>
      <div class="col-md-6"><div class="panel_s"><div class="panel-heading">Lịch viếng thăm mới nhất</div><div class="panel-body">
        <?php if (empty($recentVisits)) { ?><div class="alert alert-info tw-mb-0">Chưa có lịch viếng thăm. Hoàn tất lịch viếng thăm trong DMS để CRM hiển thị dữ liệu.</div><?php } else { ?>
          <table class="table table-striped"><tbody><?php foreach ($recentVisits as $row) { ?><tr><td><strong><?php echo e((string) ($row['staff_name'] ?? $row['crm_staff_id'] ?? '-')); ?></strong><br><span class="text-muted"><?php echo e((string) ($row['customer_name'] ?? $row['crm_customer_id'] ?? '-')); ?></span></td><td><?php echo e((string) (($row['ended_at'] ?? '') ?: ($row['started_at'] ?? '-'))); ?></td><td><?php echo e((string) ($row['visit_status'] ?? '-')); ?></td></tr><?php } ?></tbody></table>
        <?php } ?>
      </div></div></div>
    </div>

    <div class="panel_s"><div class="panel-heading">Gói DMS và quota</div><div class="panel-body">
      <div class="row">
        <div class="col-md-3"><strong>Gói</strong><br><?php echo e($tenantSettings['dms_package_name'] ?? ($tenantSettings['DMS_PACKAGE'] ?? 'Chưa có gói DMS')); ?></div>
        <div class="col-md-3"><strong>Subscription</strong><br><?php echo e(strtoupper((string) ($tenantSettings['DMS_SUBSCRIPTION_STATUS'] ?? 'none'))); ?></div>
        <div class="col-md-3"><strong>Workspace</strong><br><?php echo e((string) ($workspace['host'] ?? 'Chưa có dữ liệu')); ?></div>
        <div class="col-md-3"><strong>Nhân viên sẵn sàng</strong><br><?php echo e((string) ($readiness['provisioned_users'] ?? 0)); ?></div>
      </div>
      <?php if (!empty($usageRows)) { ?><hr><div class="row"><?php foreach ($usageRows as $usageRow) { ?><div class="col-md-3"><span class="text-muted"><?php echo e((string) ($usageRow['label'] ?? 'Sử dụng')); ?></span><br><strong><?php echo e((string) ($usageRow['used'] ?? 0)); ?> / <?php echo (int) ($usageRow['limit'] ?? 0) > 0 ? e((string) $usageRow['limit']) : 'Không giới hạn'; ?></strong></div><?php } ?></div><?php } ?>
    </div></div>

    <?php /* Source-contract smoke phrase: so lieu thanh toan uu tien hoa don/thanh toan CRM. */ ?>
    <div class="alert alert-info">Chi tiết đồng bộ nằm tại <a href="<?php echo e(admin_url('kt_integration_hub/dms_sync_health')); ?>">Đồng bộ & Sức khỏe</a>. Màn hình này chỉ hiển thị trạng thái kinh doanh và việc cần xử lý; hóa đơn và thanh toán lấy từ CRM.</div>
  </div>
</div>
<?php init_tail(); ?>
<style>.r24-chart-track{height:10px;margin-top:6px;overflow:hidden;border-radius:999px;background:#eef2f7}.r24-chart-track span{display:block;height:100%;border-radius:999px;background:#2563eb}</style>
