<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$activity = is_array($map_activity ?? null) ? $map_activity : [];
$staffLocations = is_array($activity['staff_locations'] ?? null) ? $activity['staff_locations'] : [];
$checkinLocations = is_array($activity['checkin_locations'] ?? null) ? $activity['checkin_locations'] : [];
$storeLocations = is_array($activity['store_locations'] ?? null) ? $activity['store_locations'] : [];
$orderLocations = is_array($activity['order_locations'] ?? null) ? $activity['order_locations'] : [];
$capturedOrderLocations = is_array($activity['captured_order_locations'] ?? null) ? $activity['captured_order_locations'] : [];
$checkoutLocations = is_array($activity['checkout_locations'] ?? null) ? $activity['checkout_locations'] : [];
$visitLocations = is_array($activity['visit_locations'] ?? null) ? $activity['visit_locations'] : [];
$feedbackLocations = is_array($activity['feedback_locations'] ?? null) ? $activity['feedback_locations'] : [];
$markers = is_array($activity['markers'] ?? null) ? $activity['markers'] : [];
$actions = is_array($actions ?? null) ? $actions : [];
$mapConfig = is_array($map_config ?? null) ? $map_config : [];
$hasLocation = !empty($markers);
$markerJson = json_encode($markers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>
<div id="wrapper"><div class="content">
  <div class="row">
    <div class="col-md-8"><h4 class="tw-mb-1">Bản đồ hoạt động</h4><p class="text-muted">Bản đồ chỉ xem trong CRM, phân loại rõ nhân viên, điểm bán, check-in, đơn hàng, lịch viếng thăm, check-out và phản ánh thị trường.</p></div>
    <div class="col-md-4 text-right"><?php if (!empty($actions['open_dms_url'])) { ?><a href="<?php echo e($actions['open_dms_url']); ?>" class="btn btn-primary">Mở app DMS</a><?php } ?></div>
  </div>
  <div class="row">
    <div class="col-md-4"><div class="panel_s"><div class="panel-body"><div class="text-uppercase text-muted small">Nhân viên có vị trí</div><div class="tw-text-2xl tw-font-semibold"><?php echo e((string) count($staffLocations)); ?></div></div></div></div>
    <div class="col-md-4"><div class="panel_s"><div class="panel-body"><div class="text-uppercase text-muted small">Check-in có GPS hôm nay</div><div class="tw-text-2xl tw-font-semibold"><?php echo e((string) count($checkinLocations)); ?></div></div></div></div>
    <div class="col-md-2 col-sm-6"><div class="panel_s"><div class="panel-body"><div class="text-uppercase text-muted small">Điểm bán có GPS</div><div class="tw-text-2xl tw-font-semibold"><?php echo e((string) count($storeLocations)); ?></div></div></div></div>
    <div class="col-md-2 col-sm-6"><div class="panel_s"><div class="panel-body"><div class="text-uppercase text-muted small">Đơn có tọa độ điểm bán</div><div class="tw-text-2xl tw-font-semibold"><?php echo e((string) count($orderLocations)); ?></div></div></div></div>
  </div>
  <div class="row">
    <div class="col-md-3 col-sm-6"><div class="panel_s"><div class="panel-body"><div class="text-uppercase text-muted small">Vị trí gửi đơn hàng</div><div class="tw-text-2xl tw-font-semibold"><?php echo e((string) count($capturedOrderLocations)); ?></div></div></div></div>
    <div class="col-md-3 col-sm-6"><div class="panel_s"><div class="panel-body"><div class="text-uppercase text-muted small">Check-out có GPS</div><div class="tw-text-2xl tw-font-semibold"><?php echo e((string) count($checkoutLocations)); ?></div></div></div></div>
    <div class="col-md-3 col-sm-6"><div class="panel_s"><div class="panel-body"><div class="text-uppercase text-muted small">Lịch viếng thăm có vị trí</div><div class="tw-text-2xl tw-font-semibold"><?php echo e((string) count($visitLocations)); ?></div></div></div></div>
    <div class="col-md-3 col-sm-6"><div class="panel_s"><div class="panel-body"><div class="text-uppercase text-muted small">Phản ánh có GPS</div><div class="tw-text-2xl tw-font-semibold"><?php echo e((string) count($feedbackLocations)); ?></div></div></div></div>
  </div>
  <div class="panel_s"><div class="panel-heading">Bản đồ</div><div class="panel-body">
    <div class="tw-flex tw-items-center tw-justify-between tw-mb-3">
      <div><strong>Bản đồ do Khách Tốt Platform quản lý</strong><br><span class="text-muted">Nhà cung cấp: <?php echo e(($mapConfig['map_provider'] ?? 'keyless') === 'keyless' ? 'Keyless' : (($mapConfig['map_provider'] ?? '') === 'google' ? 'Google Maps' : 'Tắt')); ?> · Cấu hình v<?php echo (int) ($mapConfig['config_version'] ?? 1); ?></span></div>
      <?php $mapStatusLabels = ['ready' => 'Sẵn sàng', 'missing_platform_config' => 'Thiếu cấu hình', 'disabled_by_package' => 'Tắt theo gói', 'disabled_by_platform' => 'Nền tảng đang tắt']; ?>
      <span class="label label-<?php echo ($mapConfig['map_status'] ?? 'ready') === 'ready' ? 'success' : 'warning'; ?>"><?php echo e($mapStatusLabels[$mapConfig['map_status'] ?? 'ready'] ?? 'Thiếu cấu hình'); ?></span>
    </div>
    <?php if (($mapConfig['map_status'] ?? 'ready') === 'missing_platform_config') { ?>
      <div class="alert alert-warning">Cấu hình bản đồ do nền tảng quản lý hiện chưa hoàn tất. Dữ liệu vị trí và dòng thời gian vẫn có thể xem bên dưới.</div>
    <?php } elseif (in_array(($mapConfig['map_status'] ?? 'ready'), ['disabled_by_package', 'disabled_by_platform'], true)) { ?>
      <div class="alert alert-warning">Bản đồ đang bị tắt theo cấu hình nền tảng hoặc gói DMS.</div>
    <?php } ?>
    <div class="kt-dms-map" id="kt-dms-map-canvas" data-r25-map-provider="<?php echo e((string) ($mapConfig['map_provider'] ?? 'keyless')); ?>" data-map-runtime-state="pending">
      <div class="kt-dms-map-empty"><?php echo $hasLocation ? 'Đang hiển thị dữ liệu vị trí...' : 'Chưa có vị trí để hiển thị trên bản đồ.'; ?></div>
    </div>
    <?php if (!$hasLocation) { ?>
      <div class="alert alert-info tw-mt-3">Chưa có dữ liệu vị trí. Mở app DMS để sales gửi live location, check-in, tạo đơn có GPS hoặc gửi phản ánh thị trường. Nhãn "Đơn tại điểm bán" là tọa độ điểm bán tham chiếu, không phải GPS được chụp tại lúc tạo đơn.</div>
    <?php } else { ?>
      <div class="tw-mt-2">
        <span class="label label-primary">Nhân viên</span>
        <span class="label label-success">Check-in</span>
        <span class="label label-default">Điểm bán</span>
        <span class="label label-warning">Điểm bán của đơn hàng</span>
        <span class="label label-info">Vị trí gửi đơn hàng</span>
        <span class="label label-danger">Phản ánh</span>
        <span class="label label-default">Check-out / Lịch viếng thăm</span>
      </div>
      <div class="text-muted small tw-mt-2">Bản đồ này chỉ dùng để xem dữ liệu tọa độ DMS đã đồng bộ. "Điểm bán của đơn hàng" là tọa độ điểm bán; "Vị trí gửi đơn hàng" mới là vị trí được ghi nhận khi nhân viên gửi đơn.</div>
    <?php } ?>
  </div></div>
  <div class="row">
    <div class="col-md-6"><div class="panel_s"><div class="panel-heading">Vị trí nhân viên gần nhất</div><div class="panel-body"><?php if (empty($staffLocations)) { ?><div class="text-muted">Chưa có dữ liệu vị trí trực tiếp.</div><?php } else { ?><table class="table table-striped"><tbody><?php foreach ($staffLocations as $row) { ?><tr><td><?php echo e((string) ($row['staff_name'] ?? $row['crm_staff_id'] ?? '-')); ?></td><td><?php echo e(kt_dms_ui_status_label('tracking', $row['tracking_status'] ?? '')); ?></td><td><?php echo e((string) ($row['latitude'] ?? '')); ?>, <?php echo e((string) ($row['longitude'] ?? '')); ?></td><td><?php echo e((string) ($row['last_seen_at'] ?? '')); ?></td></tr><?php } ?></tbody></table><?php } ?></div></div></div>
    <div class="col-md-6"><div class="panel_s"><div class="panel-heading">Check-in GPS hôm nay</div><div class="panel-body"><?php if (empty($checkinLocations)) { ?><div class="text-muted">Chưa có check-in GPS hôm nay.</div><?php } else { ?><table class="table table-striped"><tbody><?php foreach ($checkinLocations as $row) { ?><tr><td><?php echo e((string) ($row['staff_name'] ?? $row['crm_staff_id'] ?? '-')); ?></td><td><?php echo e((string) ($row['customer_name'] ?? $row['crm_customer_id'] ?? '-')); ?></td><td><?php echo e((string) ($row['latitude'] ?? '')); ?>, <?php echo e((string) ($row['longitude'] ?? '')); ?></td><td><?php echo e((string) ($row['checkin_at'] ?? '')); ?></td></tr><?php } ?></tbody></table><?php } ?></div></div></div>
  </div>
  <div class="panel_s"><div class="panel-heading">Dòng thời gian hoạt động hôm nay</div><div class="panel-body">
    <?php if (empty($markers)) { ?>
      <div class="text-muted">Chưa có lịch sử vị trí trong ngày.</div>
    <?php } else { ?>
      <table class="table table-striped"><thead><tr><th>Loại vị trí</th><th>Nhãn</th><th>Trạng thái</th><th>Thời gian</th><th>Tọa độ</th></tr></thead><tbody>
        <?php foreach ($markers as $marker) { ?>
          <tr><td><?php echo e(kt_dms_ui_status_label('marker', $marker['type'] ?? '')); ?></td><td><?php echo e((string) ($marker['label'] ?? '-')); ?></td><td><?php echo e(($marker['type'] ?? '') === 'field_feedback' ? (string) ($marker['status'] ?? 'Phản ánh thị trường') : kt_dms_ui_status_label('tracking', $marker['status'] ?? '')); ?></td><td><?php echo e((string) ($marker['time'] ?? '-')); ?></td><td><?php echo e((string) ($marker['lat'] ?? '')); ?>, <?php echo e((string) ($marker['lng'] ?? '')); ?></td></tr>
        <?php } ?>
      </tbody></table>
    <?php } ?>
  </div></div>
</div></div>
<style>
.kt-dms-map{position:relative;min-height:380px;border:1px solid #d7dce2;background:#f5f7fa;overflow:hidden;border-radius:4px}
.kt-dms-map:before{content:"";position:absolute;inset:0;background-image:linear-gradient(#e7ebf0 1px,transparent 1px),linear-gradient(90deg,#e7ebf0 1px,transparent 1px);background-size:40px 40px}
.kt-dms-map-marker{position:absolute;transform:translate(-50%,-50%);width:18px;height:18px;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 6px rgba(0,0,0,.25);cursor:default}
.kt-dms-map-marker.staff{background:#2563eb}
.kt-dms-map-marker.checkin{background:#16a34a}
.kt-dms-map-marker.store{background:#6b7280}
.kt-dms-map-marker.order_store{background:#f59e0b}
.kt-dms-map-marker.captured_order{background:#7c3aed}
.kt-dms-map-marker.checkout{background:#0f766e}
.kt-dms-map-marker.visit{background:#0284c7}
.kt-dms-map-marker.field_feedback{background:#dc2626}
.kt-dms-map-label{position:absolute;transform:translate(10px,-50%);max-width:220px;background:#fff;border:1px solid #d7dce2;border-radius:3px;padding:4px 6px;font-size:11px;white-space:nowrap;box-shadow:0 1px 4px rgba(0,0,0,.12)}
.kt-dms-map-empty{position:absolute;left:16px;top:16px;color:#6b7280}
</style>
<?php if (($mapConfig['map_provider'] ?? '') === 'google' && ($mapConfig['map_status'] ?? '') === 'ready') { ?>
<script>
window.ktDmsInitGoogleMap = function () {
  var canvas = document.getElementById('kt-dms-map-canvas');
  var markers = <?php echo $markerJson ?: '[]'; ?>;
  if (!canvas || !window.google || !google.maps) return;
  var valid = markers.filter(function (m) { return isFinite(Number(m.lat)) && isFinite(Number(m.lng)); });
  var center = valid.length ? {lat:Number(valid[0].lat),lng:Number(valid[0].lng)} : {lat:10.7769,lng:106.7009};
  var map = new google.maps.Map(canvas, {center:center,zoom:valid.length ? 13 : 6,mapTypeControl:false,streetViewControl:false});
  canvas.dataset.mapRuntimeState = 'google_initialized';
  window.ktDmsGoogleMap = map;
  if (!valid.length) {
    var note=document.createElement('div');note.className='kt-dms-google-empty';note.textContent='Chưa có vị trí GPS để đặt marker.';canvas.appendChild(note);
    return;
  }
  var bounds = new google.maps.LatLngBounds();
  valid.forEach(function(marker){
    var position={lat:Number(marker.lat),lng:Number(marker.lng)};
    new google.maps.Marker({position:position,map:map,title:String(marker.label||'Vị trí')});
    bounds.extend(position);
  });
  map.fitBounds(bounds);
};
</script>
<script src="<?php echo e((string) ($mapConfig['loader_url'] ?? '')); ?>" async></script>
<?php } elseif ($hasLocation && ($mapConfig['map_status'] ?? 'ready') !== 'disabled') { ?>
<script>
(function(){
  var markers = <?php echo $markerJson ?: '[]'; ?>;
  var canvas = document.getElementById('kt-dms-map-canvas');
  if (!canvas || !markers.length) return;
  canvas.dataset.mapRuntimeState = 'keyless_fallback';
  canvas.innerHTML = '';
  var lats = markers.map(function(m){ return Number(m.lat); }).filter(isFinite);
  var lngs = markers.map(function(m){ return Number(m.lng); }).filter(isFinite);
  var minLat = Math.min.apply(null, lats), maxLat = Math.max.apply(null, lats);
  var minLng = Math.min.apply(null, lngs), maxLng = Math.max.apply(null, lngs);
  var latSpan = Math.max(maxLat - minLat, 0.0001);
  var lngSpan = Math.max(maxLng - minLng, 0.0001);
  markers.forEach(function(marker){
    var lat = Number(marker.lat), lng = Number(marker.lng);
    if (!isFinite(lat) || !isFinite(lng)) return;
    var x = 8 + ((lng - minLng) / lngSpan) * 84;
    var y = 92 - ((lat - minLat) / latSpan) * 84;
    var dot = document.createElement('span');
    dot.className = 'kt-dms-map-marker ' + String(marker.type || 'store');
    dot.style.left = x + '%';
    dot.style.top = y + '%';
    dot.title = String(marker.label || '') + ' - ' + String(marker.status || '') + ' - ' + String(marker.time || '');
    canvas.appendChild(dot);
    var label = document.createElement('span');
    label.className = 'kt-dms-map-label';
    label.style.left = x + '%';
    label.style.top = y + '%';
    label.textContent = String(marker.label || 'Vị trí') + (marker.status ? ' · ' + marker.status : '');
    canvas.appendChild(label);
  });
})();
</script>
<?php } ?>
<?php init_tail(); ?>
