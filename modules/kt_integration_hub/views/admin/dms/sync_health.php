<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$rows = is_array($health_rows ?? null) ? $health_rows : [];
$actions = is_array($actions ?? null) ? $actions : [];
$latestError = is_array($latest_error ?? null) ? $latest_error : null;
$contract = is_array($contract_readiness ?? null) ? $contract_readiness : [];
$contractIssues = is_array($mapping_issues ?? null) ? $mapping_issues : [];
$sourceCounts = (array) ($contract['source_counts'] ?? []);
$customerSourceCount = (int) ($sourceCounts['customers'] ?? 0);
$productSourceCount = (int) ($sourceCounts['products'] ?? 0);
$warehouseSourceCount = (int) ($sourceCounts['warehouses'] ?? 0);
$syncCountText = static function ($count) {
    return ((int) $count) . ' bản ghi đồng bộ';
};
$customerMappingText = $customerSourceCount > 0
    ? $syncCountText($contract['customer_store_mappings'] ?? 0)
    : 'Chưa có dữ liệu khách hàng/điểm bán trong CRM';
$productMappingText = $productSourceCount > 0
    ? $syncCountText($contract['product_mappings'] ?? 0)
    : 'Chưa có dữ liệu sản phẩm trong CRM';
$inventoryState = strtoupper(trim((string) ($contract['inventory'] ?? 'UNKNOWN')));
$warehouseCatalogState = strtoupper(trim((string) ($contract['warehouse_catalog'] ?? $contract['warehouse_catalog_readiness'] ?? 'UNKNOWN')));
$outboundCollectionState = strtoupper(trim((string) ($contract['outbound_collection'] ?? 'UNKNOWN')));
$warehouseCatalogText = match ($warehouseCatalogState) {
    'READY', 'CONNECTED', 'OK', 'WAREHOUSE_CATALOG_READY' => 'Danh mục kho đã đồng bộ',
    'WAREHOUSE_CATALOG_REQUIRED' => $warehouseSourceCount > 0 ? 'Kho CRM chưa được liên kết với kho DMS' : 'Chưa có dữ liệu kho trong CRM',
    default => 'Đang kiểm tra danh mục kho',
};
$inventoryText = match ($inventoryState) {
    'READY', 'CONNECTED', 'OK' => 'Kho đã sẵn sàng',
    'WAREHOUSE_STORE_MAPPING_READY' => 'Một kho DMS có thể phục vụ nhiều điểm bán CRM; liên kết kho/điểm bán đã có',
    'WAREHOUSE_STORE_MAPPING_REQUIRED', 'MANUAL_WAREHOUSE_STORE_MAPPING_REQUIRED' => 'Cần cấu hình kho phục vụ điểm bán',
    default => 'Đang kiểm tra vận hành kho',
};
$outboundCollectionText = match ($outboundCollectionState) {
    'READY', 'CONNECTED', 'OK' => 'Thu tiền đồng bộ về CRM',
    'MANUAL_REVIEW_ONLY', 'READY_MANUAL_REVIEW_ONLY' => 'Thu tiền từ DMS chỉ tạo bằng chứng để CRM xem xét thủ công; không tạo thanh toán',
    'NOT_IMPLEMENTED' => 'Thu tiền từ DMS về CRM chưa áp dụng trong phiên bản hiện tại',
    default => 'Đang kiểm tra thu tiền',
};
$stateLabel = static function ($row) {
    $v = (array) ($row['value'] ?? []);
    if (($row['type'] ?? '') === 'bridge') { return !empty($v['record_count']) ? 'synced' : 'no_data'; }
    return (string) ($v['last_job_status'] ?? 'not_started');
};
$lastAt = static function ($row) { $v=(array)($row['value'] ?? []); return (string)(($v['last_successful_sync_at'] ?? '') ?: ($v['last_event_at'] ?? '') ?: ($v['last_log_at'] ?? '') ?: ($v['last_job_at'] ?? '') ?: 'Chưa có'); };
$count = static function ($row) { $v=(array)($row['value'] ?? []); return (string)($v['successful_count'] ?? $v['record_count'] ?? 0); };
$businessStep = static function ($status) {
    $status = strtolower(trim((string) $status));
    if (in_array($status, ['queued', 'pending'], true)) return 'Đã tạo tác vụ';
    if (in_array($status, ['running', 'processing'], true)) return 'Hệ thống đang xử lý';
    if (in_array($status, ['done', 'succeeded', 'success', 'synced'], true)) return 'Đã có hiệu lực trong DMS';
    if ($status === 'no_data') return 'Chưa có dữ liệu nguồn';
    if (in_array($status, ['failed', 'retry', 'blocked'], true)) return 'Cần kiểm tra đồng bộ';
    return 'Đang kiểm tra';
};
$reason = static function ($row, $status) {
    $v=(array)($row['value']??[]);
    if ($status==='failed') return 'Lần đồng bộ gần nhất gặp lỗi.';
    if (in_array($status,['not_started','no_data'],true)) return empty($v['record_count']) ? 'Chưa có dữ liệu nguồn hoặc chưa chạy đồng bộ.' : 'Hệ thống đang chờ xử lý đồng bộ.';
    if (in_array($status,['queued','pending'],true)) return 'Hệ thống đang chờ xử lý đồng bộ.';
    return 'Dữ liệu đang hoạt động bình thường.';
};
$owner = static function ($status) { return in_array($status,['failed','queued','pending'],true)?'Hệ thống':'Doanh nghiệp'; };
$badge = static function($status){$s=strtolower(trim((string)$status)); if(in_array($s,['done','synced','ready','enabled'],true))return 'success'; if(in_array($s,['queued','retry','pending','no_data','not_started'],true))return 'warning'; if(in_array($s,['failed','blocked'],true))return 'danger'; return 'default';};
?>
<div id="wrapper"><div class="content">
  <div class="row"><div class="col-md-8"><h4 class="tw-mb-1">Đồng bộ & Sức khỏe</h4><p class="text-muted">Kiểm tra đồng bộ nhân sự, điểm bán, sản phẩm, hoạt động thị trường, workspace và quyền sử dụng DMS. Màn hình chỉ hiển thị trạng thái an toàn để vận hành.</p></div><div class="col-md-4 text-right"><a href="<?php echo e(admin_url('kt_integration_hub/dms_setup_center')); ?>" class="btn btn-default">Thiết lập đội DMS</a><?php if(!empty($actions['open_dms_url'])){ ?><a href="<?php echo e($actions['open_dms_url']); ?>" class="btn btn-primary">Mở DMS</a><?php } ?></div></div>
  <?php if ($latestError) { ?><div class="alert alert-warning"><strong>Lỗi gần nhất:</strong> Dữ liệu đồng bộ đang tạm thời chưa khả dụng. Vui lòng thử lại hoặc liên hệ hỗ trợ. <span class="text-muted"><?php echo e((string)($latestError['created_at'] ?? '')); ?></span></div><?php } ?>
  <div class="panel_s"><div class="panel-heading">Mức sẵn sàng CRM ↔ DMS</div><div class="panel-body">
    <div class="row"><div class="col-md-3"><strong>Trạng thái</strong><p><?php echo e(kt_dms_ui_status_label('sync', (string)($contract['status'] ?? 'UNKNOWN'))); ?></p></div><div class="col-md-3"><strong>Nhân sự</strong><p><?php echo e($syncCountText($contract['staff_mappings'] ?? 0)); ?></p></div><div class="col-md-3"><strong>Điểm bán</strong><p><?php echo e($customerMappingText); ?></p></div><div class="col-md-3"><strong>Sản phẩm</strong><p><?php echo e($productMappingText); ?></p></div></div>
    <p class="text-muted tw-mb-0">Danh mục kho: <?php echo e($warehouseCatalogText); ?> · Kho/điểm bán: <?php echo e($inventoryText); ?> · Thu tiền: <?php echo e($outboundCollectionText); ?> · Giao hàng: chỉ trạng thái/bằng chứng/ngoại lệ · Trả hàng: chỉ yêu cầu/biên nhận · Vấn đề mở: <?php echo count($contractIssues); ?></p>
  </div></div>
  <div class="panel_s"><div class="panel-body"><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Nhóm dữ liệu</th><th>Bước hiện tại</th><th>Nguyên nhân</th><th>Bước xử lý tiếp theo</th><th>Lần đồng bộ gần nhất</th><th>Bản ghi đã nhận</th><th>Phụ trách</th></tr></thead><tbody>
    <?php foreach($rows as $row){ $status=$stateLabel($row); ?><tr><td><strong><?php echo e((string)($row['label'] ?? 'Sức khỏe')); ?></strong></td><td><span class="label label-<?php echo $badge($status); ?>"><?php echo e($businessStep($status)); ?></span></td><td><?php echo e($reason($row,$status)); ?></td><td><?php if(!empty($row['action'])&&($row['type']??'')==='sync'){ ?><?php echo form_open($row['action'],['class'=>'tw-inline']); ?><button class="btn btn-default btn-xs" type="submit">Thử lại đồng bộ</button><?php echo form_close(); ?><?php }elseif(!empty($row['action'])){ ?><a class="btn btn-default btn-xs" href="<?php echo e($row['action']); ?>">Xem chi tiết</a><?php }else{ ?><span class="text-muted">Không cần thao tác</span><?php } ?></td><td><?php echo e($lastAt($row)); ?></td><td><?php echo e($count($row)); ?></td><td><?php echo e($owner($status)); ?></td></tr><?php } ?>
  </tbody></table></div></div></div>
  <details><summary>Thông tin dành cho hỗ trợ</summary><div class="alert alert-info tw-mt-2">Nhật ký chi tiết chỉ dành cho đội hỗ trợ khi cần xử lý sự cố.</div></details>
</div></div>
<?php init_tail(); ?>
