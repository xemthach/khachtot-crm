<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$center = is_array($setup_center ?? null) ? $setup_center : [];
$eligibility = (array) ($center['eligibility'] ?? []);
$status = (array) ($center['status'] ?? []);
$users = (array) ($center['users'] ?? []);
$actions = (array) ($center['actions'] ?? []);
$managerCandidates = (array) ($users['manager_candidates'] ?? []);
$roleCounts = (array) ($users['role_counts'] ?? []);
$syncRows = (array) ($center['sync_status'] ?? []);
$workspaceRows = is_array($dms_workspaces ?? null) ? $dms_workspaces : [];
$contractReadiness = (array) ($center['contract_readiness'] ?? []);
$sourceCounts = (array) ($contractReadiness['source_counts'] ?? []);
$staffSourceCount = (int) ($sourceCounts['staff'] ?? count((array) ($users['rows'] ?? [])));
$customerSourceCount = (int) ($sourceCounts['customers'] ?? 0);
$productSourceCount = (int) ($sourceCounts['products'] ?? 0);
$warehouseSourceCount = (int) ($sourceCounts['warehouses'] ?? 0);
$staffSyncedCount = (int) ($contractReadiness['staff_mappings'] ?? 0);
$customerSyncedCount = (int) ($contractReadiness['customer_store_mappings'] ?? 0);
$productSyncedCount = (int) ($contractReadiness['product_mappings'] ?? 0);
$warehouseCatalogSyncedCount = (int) ($contractReadiness['warehouse_catalog_mappings'] ?? 0);
$syncRowsByLabel = [];
foreach ($syncRows as $syncRow) {
    $labelText = (string) ($syncRow['label'] ?? '');
    $label = function_exists('mb_strtolower') ? mb_strtolower($labelText) : strtolower($labelText);
    if (strpos($label, 'nhân') !== false) {
        $syncRowsByLabel['staff'] = (array) ($syncRow['value'] ?? []);
    } elseif (strpos($label, 'khách') !== false || strpos($label, 'điểm') !== false) {
        $syncRowsByLabel['customer'] = (array) ($syncRow['value'] ?? []);
    } elseif (strpos($label, 'sản') !== false) {
        $syncRowsByLabel['product'] = (array) ($syncRow['value'] ?? []);
    }
}
$businessStatusLabel = static function ($status) {
    $status = strtolower(trim((string) $status));
    if (in_array($status, ['done', 'synced', 'ready', 'salesrep_received', 'dms_recorded'], true)) {
        return 'Đã có hiệu lực';
    }
    if (in_array($status, ['queued', 'pending'], true)) {
        return 'Đã tạo tác vụ';
    }
    if (in_array($status, ['processing', 'running', 'worker_done'], true)) {
        return 'Hệ thống đang xử lý';
    }
    if (in_array($status, ['failed', 'blocked'], true)) {
        return 'Cần xử lý';
    }
    if (in_array($status, ['no_data'], true)) {
        return 'Chưa có dữ liệu nguồn';
    }
    return 'Đang kiểm tra';
};
$businessBadge = static function ($status) {
    $status = strtolower(trim((string) $status));
    if (in_array($status, ['done', 'synced', 'ready', 'salesrep_received', 'dms_recorded'], true)) {
        return 'success';
    }
    if (in_array($status, ['failed', 'blocked'], true)) {
        return 'danger';
    }
    if (in_array($status, ['queued', 'pending', 'processing', 'running', 'worker_done', 'no_data'], true)) {
        return 'warning';
    }
    return 'default';
};
$domainSummary = static function ($key, $sourceCount, $syncedCount, $sync) use ($businessStatusLabel, $businessBadge) {
    $sync = (array) $sync;
    $status = strtolower(trim((string) ($sync['last_job_status'] ?? ($syncedCount > 0 ? 'synced' : 'not_started'))));
    $processing = in_array($status, ['queued', 'pending', 'processing', 'running'], true) ? 1 : 0;
    $needsAction = in_array($status, ['failed', 'blocked'], true) ? 1 : 0;
    $noSource = $sourceCount <= 0;
    if ($noSource) {
        $status = 'no_data';
    } elseif ($syncedCount > 0 && !in_array($status, ['queued', 'pending', 'processing', 'running', 'failed', 'blocked'], true)) {
        $status = 'synced';
    }

    return [
        'key' => $key,
        'source' => (int) $sourceCount,
        'synced' => (int) $syncedCount,
        'processing' => $processing,
        'needs_action' => $needsAction,
        'no_source' => $noSource,
        'mapping_gap' => $sourceCount > 0 ? max(0, (int) $sourceCount - (int) $syncedCount) : 0,
        'status' => $status,
        'label' => $businessStatusLabel($status),
        'badge' => $businessBadge($status),
        'last_sync' => (string) (($sync['last_successful_sync_at'] ?? '') ?: ($sync['last_event_at'] ?? '') ?: ($sync['last_log_at'] ?? '') ?: ($sync['last_job_at'] ?? '') ?: 'Chưa có'),
    ];
};
$staffSummary = $domainSummary('staff', $staffSourceCount, $staffSyncedCount, $syncRowsByLabel['staff'] ?? []);
$customerSummary = $domainSummary('customer', $customerSourceCount, $customerSyncedCount, $syncRowsByLabel['customer'] ?? []);
$productSummary = $domainSummary('product', $productSourceCount, $productSyncedCount, $syncRowsByLabel['product'] ?? []);
$warehouseSummary = $domainSummary('warehouse', $warehouseSourceCount, $warehouseCatalogSyncedCount, []);
$warehouseCatalogStateForSummary = strtoupper(trim((string) ($contractReadiness['warehouse_catalog_readiness'] ?? $contractReadiness['warehouse_catalog'] ?? 'UNKNOWN')));
$warehouseCatalogReady = in_array($warehouseCatalogStateForSummary, ['READY', 'CONNECTED', 'OK', 'WAREHOUSE_CATALOG_READY'], true);
$inventoryStateForSummary = strtoupper(trim((string) ($contractReadiness['inventory_readiness'] ?? $contractReadiness['inventory'] ?? 'UNKNOWN')));
$warehouseStoreReady = in_array($inventoryStateForSummary, ['READY', 'CONNECTED', 'OK', 'WAREHOUSE_STORE_MAPPING_READY'], true);
$workspaceReadyForSummary = !empty($eligibility['eligible']) && !empty($status['workspace_host']) && !in_array($status['workspace_host'], ['Not available', 'Chưa có'], true);
$openDmsReady = $workspaceReadyForSummary && !empty($users['ready']) && (($actions['open_dms_mode'] ?? 'disabled') !== 'disabled');
$syncStages = [
    ['key' => 'crm_saved', 'label' => 'Đã lưu trong CRM'],
    ['key' => 'queued', 'label' => 'Đã tạo tác vụ'],
    ['key' => 'processing', 'label' => 'Hệ thống đang xử lý'],
    ['key' => 'salesrep_received', 'label' => 'Salesrep đã nhận dữ liệu'],
    ['key' => 'dms_effective', 'label' => 'Đã có hiệu lực trong DMS'],
    ['key' => 'user_seen', 'label' => 'Người dùng đã thấy thay đổi'],
];
$stageClass = static function ($stageKey, $summary) {
    $status = strtolower((string) ($summary['status'] ?? ''));
    $synced = (int) ($summary['synced'] ?? 0) > 0;
    if ($stageKey === 'crm_saved') {
        return 'success';
    }
    if ($stageKey === 'queued') {
        return in_array($status, ['queued', 'pending', 'processing', 'running', 'synced'], true) || $synced ? 'success' : 'default';
    }
    if ($stageKey === 'processing') {
        return in_array($status, ['processing', 'running'], true) ? 'warning' : (($synced || $status === 'synced') ? 'success' : 'default');
    }
    if (in_array($stageKey, ['salesrep_received', 'dms_effective'], true)) {
        return $synced || $status === 'synced' ? 'success' : 'default';
    }
    return 'default';
};
$steps = [
    ['title' => '1. Gói DMS & Workspace', 'done' => !empty($eligibility['eligible']) && !empty($status['workspace_host']) && !in_array($status['workspace_host'], ['Not available', 'Chưa có'], true), 'action' => !empty($eligibility['eligible']) ? 'Kiểm tra workspace và gói DMS.' : 'Chọn gói DMS trong kho ứng dụng.', 'failure' => $eligibility['message'] ?? 'Gói DMS chưa sẵn sàng.'],
    ['title' => '2. Quyền truy cập nhân viên', 'done' => !empty($users['ready']), 'action' => 'Bật quyền truy cập DMS, gán vai trò rồi lưu và đồng bộ.', 'failure' => 'Chưa có nhân viên DMS được đồng bộ thành công.'],
    ['title' => '3. Cấu trúc quản lý', 'done' => !empty($users['rows']), 'action' => 'Gán quản lý cho nhân viên thị trường nếu có đội nhóm.', 'failure' => 'Chưa có dữ liệu nhân viên và quản lý để cấu hình.'],
    ['title' => '4. Đồng bộ điểm bán / sản phẩm', 'done' => !empty($status['catalog_sync_ready']), 'action' => 'Chạy đồng bộ khách hàng, điểm bán và sản phẩm.', 'failure' => $status['catalog_sync_summary'] ?? 'Chưa có dữ liệu đồng bộ danh mục.'],
    ['title' => '5. Kiểm tra hoạt động đầu tiên', 'done' => !empty($status['first_activity']), 'action' => 'Mở DMS và tạo check-in/visit đầu tiên.', 'failure' => 'CRM chưa nhận hoạt động thực địa đầu tiên.'],
    ['title' => '6. Kiểm tra sức khỏe', 'done' => empty($center['latest_error']), 'action' => 'Mở Đồng bộ & Sức khỏe để xem lỗi và thử lại nếu cần.', 'failure' => !empty($center['latest_error']) ? 'Có lỗi đồng bộ cần kiểm tra.' : 'Chưa có lỗi hiện tại.'],
];
$nextStep = null;
foreach ($steps as $step) { if (empty($step['done'])) { $nextStep = $step; break; } }
$syncStepClass = static function ($status) {
    $status = strtolower(trim((string) $status));
    if ($status === 'done') {
        return 'success';
    }
    if ($status === 'failed') {
        return 'danger';
    }
    if ($status === 'warning') {
        return 'warning';
    }
    if ($status === 'current') {
        return 'info';
    }
    return 'default';
};
$syncProgressClass = static function ($state) {
    $state = strtolower(trim((string) $state));
    if (in_array($state, ['dms_recorded', 'salesrep_received'], true)) {
        return 'success';
    }
    if ($state === 'failed') {
        return 'danger';
    }
    if ($state === 'delayed') {
        return 'warning';
    }
    if (in_array($state, ['queued', 'processing', 'worker_done', 'crm_saved'], true)) {
        return 'info';
    }
    return 'default';
};
?>
<div id="wrapper"><div class="content" id="dms-setup-center">
  <div class="row"><div class="col-md-8">
    <h4 class="tw-mb-1"><?php echo e($page_heading ?? 'Thiết lập & Truy cập'); ?></h4>
    <p class="text-muted">Hướng dẫn chuẩn bị DMS cho doanh nghiệp: gói DMS, workspace, nhân viên, quản lý, đồng bộ và kiểm tra vận hành.</p>
  </div><div class="col-md-4 text-right">
    <a href="<?php echo e(admin_url('kt_integration_hub/dms_overview')); ?>" class="btn btn-default">Tổng quan</a>
    <?php if (!empty($users['reprovision_url']) || !empty($users['customer_resync_url']) || !empty($users['product_resync_url']) || !empty($users['warehouse_resync_url'])) { ?><a href="#sync-actions" class="btn btn-default" data-sync-jump>Đồng bộ ngay</a><?php } ?>
    <a href="<?php echo e(admin_url('kt_integration_hub/dms_sync_health')); ?>" class="btn btn-default">Kiểm tra hệ thống</a>
    <?php if ($openDmsReady && !empty($actions['open_dms_url'])) { ?><a href="<?php echo e($actions['open_dms_url']); ?>" class="btn btn-primary">Mở DMS</a><?php } else { ?><a href="#workspace-readiness" class="btn btn-default">Kiểm tra mở DMS</a><?php } ?>
  </div></div>

  <div class="alert alert-<?php echo !empty($eligibility['eligible']) ? 'success' : 'warning'; ?>">
    <strong><?php echo e($eligibility['title'] ?? 'Chưa có trạng thái gói DMS'); ?></strong>
    <div><?php echo e($eligibility['message'] ?? ''); ?></div>
  </div>

  <?php if ($nextStep) { ?><div class="panel_s"><div class="panel-body">
    <div class="text-uppercase text-muted small">Hành động tiếp theo</div>
    <h4 class="tw-mt-1"><?php echo e($nextStep['title']); ?></h4>
    <p class="text-muted"><?php echo e($nextStep['action']); ?></p>
  </div></div><?php } ?>

  <div id="workspace-readiness" class="panel_s"><div class="panel-heading">Sẵn sàng vận hành DMS</div><div class="panel-body">
    <p class="text-muted">Màn hình này cho biết workspace đã sẵn sàng chưa, DMS đã nhận dữ liệu chưa và doanh nghiệp còn cần xử lý gì.</p>
    <div class="row">
      <div class="col-md-4"><div class="panel_s"><div class="panel-body">
        <div class="text-muted small">Workspace</div>
        <h4 class="tw-mt-1"><?php echo $workspaceReadyForSummary ? 'Đã sẵn sàng' : 'Chưa sẵn sàng'; ?></h4>
        <p class="text-muted tw-mb-0"><?php echo e((string) (($status['workspace_host'] ?? '') ?: 'Chưa phát hiện workspace DMS')); ?></p>
      </div></div></div>
      <div class="col-md-4"><div class="panel_s"><div class="panel-body">
        <div class="text-muted small">Gói DMS</div>
        <h4 class="tw-mt-1"><?php echo !empty($eligibility['eligible']) ? 'Đang hoạt động' : 'Chưa hoạt động'; ?></h4>
        <p class="text-muted tw-mb-0"><?php echo e(($status['package_name'] ?? '') ?: 'Chưa có gói DMS hoạt động'); ?></p>
      </div></div></div>
      <div class="col-md-4"><div class="panel_s"><div class="panel-body">
        <div class="text-muted small">Mở DMS</div>
        <h4 class="tw-mt-1"><?php echo $openDmsReady ? 'Sẵn sàng' : 'Đang chờ đồng bộ'; ?></h4>
        <p class="text-muted tw-mb-0"><?php echo $openDmsReady ? 'Nhân sự và workspace đủ điều kiện để mở DMS.' : 'Hệ thống đang hoàn tất đồng bộ dữ liệu trước khi mở DMS.'; ?></p>
      </div></div></div>
    </div>
    <div class="row">
      <?php foreach ([
        'Nhân sự' => $staffSummary,
        'Điểm bán' => $customerSummary,
        'Sản phẩm' => $productSummary,
      ] as $groupLabel => $summary) { ?>
        <div class="col-md-4"><div class="panel_s"><div class="panel-body">
          <div class="tw-flex tw-items-center tw-justify-between">
            <h5 class="tw-mt-0"><?php echo e($groupLabel); ?></h5>
            <span class="label label-<?php echo e($summary['badge']); ?>"><?php echo e($summary['label']); ?></span>
          </div>
          <div class="row">
            <div class="col-xs-4"><strong><?php echo (int) $summary['synced']; ?></strong><div class="small text-muted">Đã đồng bộ</div></div>
            <div class="col-xs-4"><strong><?php echo (int) $summary['processing']; ?></strong><div class="small text-muted">Đang xử lý</div></div>
            <div class="col-xs-4"><strong><?php echo (int) $summary['needs_action']; ?></strong><div class="small text-muted">Cần xử lý</div></div>
          </div>
          <?php if (!empty($summary['no_source'])) { ?><div class="alert alert-info tw-mt-3 tw-mb-0">Chưa có dữ liệu nguồn trong CRM.</div><?php } ?>
          <?php if ($summary['key'] === 'customer' && empty($summary['no_source']) && (int) $summary['mapping_gap'] > 0) { ?><div class="alert alert-warning tw-mt-3 tw-mb-0"><?php echo (int) $summary['mapping_gap']; ?> bản ghi cần kiểm tra liên kết điểm bán.</div><?php } ?>
          <div class="small text-muted tw-mt-2">Lần đồng bộ gần nhất: <?php echo e($summary['last_sync']); ?></div>
        </div></div></div>
      <?php } ?>
    </div>
    <div class="row">
      <div class="col-md-6"><div class="panel_s"><div class="panel-body">
        <div class="text-muted small">Danh mục kho</div>
        <h5><?php echo $warehouseCatalogReady ? 'Danh mục kho đã đồng bộ' : 'Kho CRM chưa được liên kết với kho DMS'; ?></h5>
        <p class="text-muted tw-mb-0"><?php echo $warehouseCatalogReady ? 'DMS đã nhận danh mục kho. Tồn kho vẫn ghi nhận theo sổ kho DMS.' : 'Phiên bản hiện tại chỉ đồng bộ danh mục kho, không đồng bộ tồn kho.'; ?></p>
        <div class="small text-muted tw-mt-2"><?php echo (int) $warehouseSummary['synced']; ?> kho đã đồng bộ.</div>
      </div></div></div>
      <div class="col-md-6"><div class="panel_s"><div class="panel-body">
        <div class="text-muted small">Kiểm tra lại</div>
        <h5>Không tự refresh liên tục</h5>
        <p class="text-muted">Dùng nút kiểm tra để xem trạng thái mới nhất khi hệ thống xử lý xong.</p>
        <a class="btn btn-default btn-sm" href="<?php echo e(admin_url('kt_integration_hub/dms_setup_center')); ?>">Kiểm tra lại</a>
        <a class="btn btn-default btn-sm" href="<?php echo e(admin_url('kt_integration_hub/dms_sync_health')); ?>">Xem sức khỏe đồng bộ</a>
      </div></div></div>
    </div>
  </div></div>

  <details class="panel_s dms-setup-details"><summary class="panel-heading">Xem chi tiết tiến trình đồng bộ</summary><div class="panel-body">
    <?php foreach (['Nhân sự' => $staffSummary, 'Điểm bán' => $customerSummary, 'Sản phẩm' => $productSummary] as $groupLabel => $summary) { ?>
      <h5><?php echo e($groupLabel); ?></h5>
      <div class="row">
        <?php foreach ($syncStages as $stage) { ?>
          <div class="col-md-2 col-sm-4"><div class="tw-mb-3">
            <span class="label label-<?php echo e($stageClass($stage['key'], $summary)); ?>"><?php echo e($stage['label']); ?></span>
          </div></div>
        <?php } ?>
      </div>
      <p class="text-muted">Bước “Người dùng đã thấy thay đổi” chỉ được xác nhận bằng browser/runtime proof, không tự đánh dấu từ CRM.</p>
    <?php } ?>
  </div></details>

  <details class="dms-setup-details tw-mt-4"><summary class="tw-font-semibold">Danh sách chuẩn bị vận hành</summary>
  <div class="row">
    <?php foreach ($steps as $step) { ?><div class="col-md-4"><div class="panel_s"><div class="panel-body">
      <span class="label label-<?php echo !empty($step['done']) ? 'success' : 'default'; ?>"><?php echo !empty($step['done']) ? 'Đã xong bước này' : 'Cần xử lý'; ?></span>
      <h5><?php echo e($step['title']); ?></h5>
      <p class="text-muted"><?php echo e(!empty($step['done']) ? $step['action'] : $step['failure']); ?></p>
    </div></div></div><?php } ?>
  </div></details>

  <div class="row">
    <?php foreach ([
      'tenant_admin' => 'Quản trị workspace',
      'manager' => 'Quản lý sales',
      'field_sales' => 'Nhân viên thị trường',
      'finance' => 'Tài chính',
    ] as $roleKey => $roleLabel) { ?>
      <div class="col-md-3"><div class="panel_s"><div class="panel-body">
        <div class="text-muted small"><?php echo e($roleLabel); ?></div>
        <div class="tw-text-2xl tw-font-semibold"><?php echo (int) ($roleCounts[$roleKey] ?? 0); ?></div>
      </div></div></div>
    <?php } ?>
  </div>

  <details class="panel_s dms-setup-details" data-r24-chart="han-muc-tinh-nang"><summary class="panel-heading">Hạn mức & tính năng đang được cấp</summary><div class="panel-body">
    <div class="row">
      <div class="col-md-6"><div class="table-responsive"><table class="table table-striped tw-mb-0"><thead><tr><th>Hạn mức</th><th>Giá trị</th></tr></thead><tbody>
        <?php foreach ((array) ($status['quota_rows'] ?? []) as $quotaRow) { ?><tr><td><?php echo e((string) ($quotaRow['label'] ?? '')); ?></td><td><?php echo (int) ($quotaRow['value'] ?? 0) > 0 ? e((string) $quotaRow['value']) : 'Không giới hạn / chưa cấu hình'; ?></td></tr><?php } ?>
      </tbody></table></div></div>
      <div class="col-md-6"><div class="table-responsive"><table class="table table-striped tw-mb-0"><thead><tr><th>Tính năng</th><th>Trạng thái</th></tr></thead><tbody>
        <?php foreach ((array) ($center['capabilities'] ?? []) as $capability) { ?><tr><td><?php echo e((string) ($capability['label'] ?? '')); ?></td><td><span class="label label-<?php echo !empty($capability['enabled']) ? 'success' : 'default'; ?>"><?php echo !empty($capability['enabled']) ? 'Đang bật' : 'Không được cấp'; ?></span></td></tr><?php } ?>
      </tbody></table></div></div>
    </div>
    <p class="text-muted tw-mt-3 tw-mb-0">Chỉ hiển thị quyền sử dụng và hạn mức đang có hiệu lực. Thao tác đổi gói và quản trị nền tảng không xuất hiện tại đây.</p>
  </div></details>

  <div class="row">
    <div class="col-md-4"><div class="panel_s"><div class="panel-heading">Gói DMS & Workspace</div><div class="panel-body">
      <table class="table table-striped tw-mb-0"><tbody>
        <tr><th>Gói DMS</th><td><?php echo e(($status['package_name'] ?? '') ?: 'Chưa có gói DMS hoạt động'); ?></td></tr>
        <tr><th>Trạng thái</th><td><?php echo e(kt_dms_ui_status_label('sync', $status['subscription_status'] ?? '')); ?></td></tr>
        <tr><th>Workspace</th><td><?php echo e(($status['workspace_host'] ?? '') ?: 'Chưa có dữ liệu'); ?></td></tr>
        <tr><th>Hạn mức người dùng</th><td><?php echo e((string) ($users['used_count'] ?? 0)); ?> / <?php echo (int) ($users['limit'] ?? 0) > 0 ? e((string) $users['limit']) : 'Không giới hạn'; ?></td></tr>
      </tbody></table>
    </div></div></div>
    <div class="col-md-8"><div class="panel_s" id="sync-actions"><div class="panel-heading">Đồng bộ ngay</div><div class="panel-body">
      <div class="alert alert-info"><?php echo e((string) ($status['sync_operator_guidance'] ?? 'Lưu & Đồng bộ sẽ gửi yêu cầu đồng bộ để hệ thống xử lý.')); ?></div>
      <div class="table-responsive"><table class="table table-striped tw-mb-0"><thead><tr><th>Luồng</th><th>Trạng thái</th><th>Lần gần nhất</th><th>Số bản ghi</th></tr></thead><tbody>
        <?php foreach ($syncRows as $syncRow) { $sync=(array)($syncRow['value'] ?? []); $state=$sync['last_job_status'] ?? (!empty($sync['record_count']) ? 'synced' : 'not_started'); $last=$sync['last_successful_sync_at'] ?? $sync['last_event_at'] ?? $sync['last_log_at'] ?? $sync['last_job_at'] ?? 'Chưa có'; $count=$sync['successful_count'] ?? $sync['record_count'] ?? 0; ?>
          <tr><td><?php echo e((string) ($syncRow['label'] ?? 'Đồng bộ')); ?></td><td><?php echo e(kt_dms_ui_status_label('sync', $state)); ?></td><td><?php echo e((string) $last); ?></td><td><?php echo e((string) $count); ?></td></tr>
        <?php } ?>
      </tbody></table></div>
      <div class="tw-mt-3">
        <?php if (!empty($users['reprovision_url'])) { echo form_open($users['reprovision_url'], ['class'=>'tw-inline-block']); ?><button type="submit" class="btn btn-primary">Đồng bộ nhân sự</button><?php echo form_close(); } ?>
        <?php if (!empty($users['customer_resync_url'])) { echo form_open($users['customer_resync_url'], ['class'=>'tw-inline-block']); ?><button type="submit" class="btn btn-default">Đồng bộ điểm bán</button><?php echo form_close(); } ?>
        <?php if (!empty($users['product_resync_url'])) { echo form_open($users['product_resync_url'], ['class'=>'tw-inline-block']); ?><button type="submit" class="btn btn-default">Đồng bộ sản phẩm</button><?php echo form_close(); } ?>
        <?php if (!empty($users['warehouse_resync_url'])) { echo form_open($users['warehouse_resync_url'], ['class'=>'tw-inline-block']); ?><button type="submit" class="btn btn-default">Đồng bộ danh mục kho</button><?php echo form_close(); } ?>
      </div>
    </div></div></div>
  </div>

  <?php $syncScope = (array) ($center['sync_scope']['rows'] ?? []); ?>
  <?php $contractReadiness = (array) ($center['contract_readiness'] ?? []); ?>
  <?php
    $sourceCounts = (array) ($contractReadiness['source_counts'] ?? []);
    $customerSourceCount = (int) ($sourceCounts['customers'] ?? 0);
    $productSourceCount = (int) ($sourceCounts['products'] ?? 0);
    $syncCountText = static function ($count) {
        return ((int) $count) . ' bản ghi đồng bộ';
    };
    $customerMappingText = $customerSourceCount > 0 ? $syncCountText($contractReadiness['customer_store_mappings'] ?? 0) : 'Chưa có dữ liệu khách hàng/điểm bán trong CRM';
    $productMappingText = $productSourceCount > 0 ? $syncCountText($contractReadiness['product_mappings'] ?? 0) : 'Chưa có dữ liệu sản phẩm trong CRM';
    $inventoryState = strtoupper(trim((string) ($contractReadiness['inventory_readiness'] ?? $contractReadiness['inventory'] ?? 'UNKNOWN')));
    $outboundCollectionState = strtoupper(trim((string) ($contractReadiness['outbound_collection_readiness'] ?? $contractReadiness['outbound_collection'] ?? 'UNKNOWN')));
    $warehouseCatalogState = strtoupper(trim((string) ($contractReadiness['warehouse_catalog_readiness'] ?? $contractReadiness['warehouse_catalog'] ?? 'UNKNOWN')));
    $warehouseCatalogText = in_array($warehouseCatalogState, ['READY', 'CONNECTED', 'OK', 'WAREHOUSE_CATALOG_READY'], true)
        ? 'Danh mục kho đã đồng bộ'
        : ($warehouseSourceCount > 0 ? 'Kho CRM chưa được liên kết với kho DMS' : 'Chưa có dữ liệu kho trong CRM');
    $inventoryText = in_array($inventoryState, ['READY', 'CONNECTED', 'OK', 'WAREHOUSE_STORE_MAPPING_READY'], true)
        ? 'Cấu hình kho phục vụ điểm bán đã sẵn sàng'
        : (in_array($inventoryState, ['WAREHOUSE_STORE_MAPPING_REQUIRED', 'MANUAL_WAREHOUSE_STORE_MAPPING_REQUIRED'], true)
            ? 'Cần cấu hình kho phục vụ điểm bán'
            : 'Đang kiểm tra vận hành kho');
    $outboundCollectionText = in_array($outboundCollectionState, ['READY', 'CONNECTED', 'OK', 'MANUAL_REVIEW_ONLY', 'READY_MANUAL_REVIEW_ONLY'], true)
        ? 'Thu tiền chỉ gửi bằng chứng cho CRM xem xét thủ công; không tạo thanh toán'
        : ($outboundCollectionState === 'NOT_IMPLEMENTED'
            ? 'Thu tiền từ DMS về CRM chưa áp dụng trong phiên bản hiện tại'
            : 'Đang kiểm tra thu tiền');
  ?>
  <div class="panel_s"><div class="panel-heading">Kiểm tra sẵn sàng CRM ↔ DMS</div><div class="panel-body"><div class="row"><div class="col-md-3"><strong>Trạng thái</strong><p><?php echo e(kt_dms_ui_status_label('sync', (string)($contractReadiness['status'] ?? 'UNKNOWN'))); ?></p></div><div class="col-md-3"><strong>Nhân sự</strong><p><?php echo e($syncCountText($contractReadiness['staff_mappings'] ?? 0)); ?></p></div><div class="col-md-3"><strong>Điểm bán</strong><p><?php echo e($customerMappingText); ?></p></div><div class="col-md-3"><strong>Sản phẩm</strong><p><?php echo e($productMappingText); ?></p></div></div><div class="row tw-mt-3"><div class="col-md-4"><strong>Danh mục kho</strong><p class="text-muted"><?php echo e($warehouseCatalogText); ?>.</p></div><div class="col-md-4"><strong>Kho phục vụ điểm bán</strong><p class="text-muted"><?php echo e($inventoryText); ?>.</p></div><div class="col-md-4"><strong>Thu tiền</strong><p class="text-muted"><?php echo e($outboundCollectionText); ?>.</p></div></div><p class="text-muted tw-mb-0">Danh mục kho không phải đồng bộ tồn kho. Giao hàng chỉ đồng bộ trạng thái/bằng chứng/ngoại lệ. Trả hàng chỉ đồng bộ yêu cầu/biên nhận. V1 không tạo thanh toán, hoàn tiền, báo cáo chính thức hoặc thông báo mới.</p></div></div>
  <details class="panel_s dms-setup-details" data-r31-tenant-sync-scope><summary class="panel-heading">Cài đặt phạm vi đồng bộ</summary><div class="panel-body">
    <p class="text-muted">Tenant Admin quyết định nhóm dữ liệu vận hành nào tham gia DMS. Landlord chỉ xem trạng thái an toàn và không chọn thay nhân sự, điểm bán hay sản phẩm nội bộ.</p>
    <?php if (!empty($center['sync_scope']['save_url'])) { echo form_open($center['sync_scope']['save_url']); } ?>
    <div class="row">
      <?php foreach (['customer' => 'Khách hàng / điểm bán', 'product' => 'Sản phẩm', 'warehouse' => 'Danh mục kho', 'store' => 'Điểm bán', 'region' => 'Khu vực'] as $domain => $label) { $scope = (array) ($syncScope[$domain] ?? []); $mode = (string) ($scope['mode'] ?? 'all_eligible'); ?>
        <div class="col-md-6"><div class="panel_s"><div class="panel-body">
          <h5 class="tw-mt-0"><?php echo e($label); ?></h5>
          <label><input type="checkbox" name="<?php echo e($domain); ?>_enabled" value="1" <?php echo !array_key_exists('enabled', $scope) || !empty($scope['enabled']) ? 'checked' : ''; ?>> Cho phép đồng bộ</label>
          <select class="form-control tw-mt-2" name="<?php echo e($domain); ?>_mode">
            <option value="all_eligible" <?php echo $mode === 'all_eligible' ? 'selected' : ''; ?>>Tất cả dữ liệu đủ điều kiện</option>
            <option value="selected_only" <?php echo $mode === 'selected_only' ? 'selected' : ''; ?>>Chỉ các ID được chọn</option>
            <option value="disabled" <?php echo $mode === 'disabled' ? 'selected' : ''; ?>>Tắt đồng bộ nhóm này</option>
          </select>
          <input type="text" class="form-control tw-mt-2" name="<?php echo e($domain); ?>_selected_ids" value="<?php echo e(implode(',', array_map('intval', (array) ($scope['selected_ids'] ?? [])))); ?>" placeholder="ID cách nhau bằng dấu phẩy khi chọn chế độ ID">
        </div></div></div>
      <?php } ?>
    </div>
    <div class="alert alert-info">
      <label class="tw-mb-0"><input type="checkbox" name="product_writeback_enabled" value="1" <?php echo !empty($syncScope['product_writeback_enabled']) ? 'checked' : ''; ?>> Cho phép DMS tạo/cập nhật sản phẩm CRM trong bảng sản phẩm chuẩn</label>
      <div class="small tw-mt-1">Không cập nhật dữ liệu dòng hóa đơn lịch sử. Tắt mặc định.</div>
    </div>
    <?php if (!empty($center['sync_scope']['save_url'])) { ?><button type="submit" class="btn btn-primary">Lưu phạm vi đồng bộ</button><?php echo form_close(); } ?>
  </div></details>

  <div class="panel_s"><div class="panel-heading">Quyền truy cập nhân viên & Cấu trúc quản lý</div><div class="panel-body">
    <p class="text-muted">CRM là nơi bật/tắt quyền DMS, gán vai trò và quản lý. Lưu & Đồng bộ sẽ đẩy thay đổi sang DMS tenant workspace.</p>
    <?php if (empty($users['rows'])) { ?><div class="alert alert-info tw-mb-0">Chưa có nhân viên để cấu hình. Hãy kiểm tra nhân sự CRM và chạy đồng bộ nhân sự.</div><?php } else { ?>
      <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Nhân viên</th><th>Vai trò DMS</th><th>Truy cập</th><th>Quản lý</th><th>Tiến trình đồng bộ</th><th></th></tr></thead><tbody>
      <?php foreach ((array) $users['rows'] as $accessRow) { $staffId=(int)($accessRow['crm_staff_id'] ?? 0); ?>
        <tr>
          <td><strong><?php echo e((string) ($accessRow['name'] ?? '')); ?></strong><div class="text-muted small"><?php echo e((string) ($accessRow['email'] ?? '')); ?></div></td>
          <td><select name="dms_role_code" class="form-control input-sm" form="dms-staff-<?php echo $staffId; ?>"><option value="field_sales" <?php echo ($accessRow['dms_role_code'] ?? '') === 'field_sales' ? 'selected' : ''; ?>>Nhân viên thị trường</option><option value="regional_manager" <?php echo ($accessRow['dms_role_code'] ?? '') === 'regional_manager' ? 'selected' : ''; ?>>Quản lý sales</option><option value="warehouse_operator" <?php echo ($accessRow['dms_role_code'] ?? '') === 'warehouse_operator' ? 'selected' : ''; ?>>Nhân viên kho</option><option value="finance" <?php echo ($accessRow['dms_role_code'] ?? '') === 'finance' ? 'selected' : ''; ?>>Tài chính</option><option value="tenant_admin" <?php echo ($accessRow['dms_role_code'] ?? '') === 'tenant_admin' ? 'selected' : ''; ?>>Quản trị workspace</option></select></td>
          <td><label><input type="checkbox" name="dms_access_enabled" value="1" form="dms-staff-<?php echo $staffId; ?>" <?php echo !empty($accessRow['dms_access_enabled']) ? 'checked' : ''; ?>> Đang bật</label></td>
          <td><select name="manager_crm_staff_id" class="form-control input-sm" form="dms-staff-<?php echo $staffId; ?>"><option value="">Chưa có quản lý</option><?php foreach ($managerCandidates as $manager) { $managerId=(int)($manager['crm_staff_id'] ?? 0); if ($managerId === $staffId) { continue; } ?><option value="<?php echo $managerId; ?>" <?php echo (int)($accessRow['manager_crm_staff_id'] ?? 0) === $managerId ? 'selected' : ''; ?>><?php echo e((string) ($manager['name'] ?? ('Nhân viên #' . $managerId))); ?></option><?php } ?></select></td>
          <td>
            <?php $progress = (array) ($accessRow['sync_progress'] ?? []); $progressSteps = (array) ($progress['steps'] ?? []); ?>
            <div><span class="label label-<?php echo $syncProgressClass($progress['overall'] ?? 'not_started'); ?>"><?php echo e((string) ($progress['overall_label'] ?? 'Đang kiểm tra')); ?></span></div>
            <details class="tw-mt-2 dms-setup-details"><summary class="small">Xem chi tiết</summary><ol class="list-unstyled tw-mt-2 tw-mb-1">
              <?php foreach ($progressSteps as $progressStep) { ?>
                <li class="tw-mb-1">
                  <span class="label label-<?php echo $syncStepClass($progressStep['status'] ?? 'pending'); ?>"><?php echo e((string) ($progressStep['label'] ?? 'Bước đồng bộ')); ?></span>
                  <span class="small text-muted"><?php echo e((string) ($progressStep['message'] ?? '')); ?></span>
                </li>
              <?php } ?>
            </ol></details>
            <div class="small text-muted">
              Lần đồng bộ gần nhất: <?php echo e((string) (($progress['last_sync_at'] ?? '') ?: 'Chưa có')); ?>.
              <?php echo e((string) ($progress['estimated_processing_time'] ?? '')); ?>
            </div>
            <?php if (!empty($progress['sla_warning'])) { ?><div class="text-warning small">Tác vụ đang chậm hơn thời gian dự kiến. Vui lòng kiểm tra lại hoặc thử đồng bộ lại.</div><?php } ?>
            <?php if (!empty($progress['safe_error'])) { ?><div class="text-danger small"><?php echo e((string) $progress['safe_error']); ?></div><?php } ?>
            <a class="btn btn-default btn-xs tw-mt-2" href="<?php echo e(admin_url('kt_integration_hub/dms_sync_health')); ?>">Kiểm tra lại</a>
          </td>
          <td><?php echo form_open(admin_url('kt_integration_hub/dms_save_staff_access/' . $staffId), ['id'=>'dms-staff-' . $staffId]); ?><button type="submit" class="btn btn-default btn-sm">Lưu & Đồng bộ</button><?php echo form_close(); ?></td>
        </tr>
      <?php } ?>
      </tbody></table></div>
    <?php } ?>
  </div></div>

  <?php if (!empty($center['latest_error'])) { ?><div class="alert alert-warning"><strong>Cần xử lý:</strong> Tác vụ đồng bộ gần nhất chưa thành công. Mở Đồng bộ &amp; Sức khỏe để xem nguyên nhân và thử lại.</div><?php } ?>
  <div class="panel_s"><div class="panel-body">
    <h4 class="tw-mt-0">Workspace DMS</h4>
    <p class="text-muted">Chọn workspace cần mở. Danh sách được giới hạn theo tenant hiện tại.</p>
    <?php if (!$workspaceRows) { ?><div class="alert alert-info">Chưa phát hiện workspace DMS trong CRM.</div><?php } else { ?>
    <div class="table-responsive"><table class="table"><thead><tr><th>Tên</th><th>Slug</th><th>Địa chỉ</th><th>Trạng thái</th><th></th></tr></thead><tbody>
    <?php foreach ($workspaceRows as $workspaceRow) { ?><tr>
      <td><?php echo e($workspaceRow['workspace_name']); ?> <?php if (!empty($workspaceRow['is_primary'])) { ?><span class="label label-info">Chính</span><?php } ?></td>
      <td><?php echo e($workspaceRow['workspace_slug']); ?></td><td><?php echo e($workspaceRow['workspace_host']); ?></td>
      <td><?php echo e(($workspaceRow['entitlement_status'] ?: 'Chưa có dữ liệu') . ' / ' . ($workspaceRow['domain_status'] ?: 'Chưa ghi nhận')); ?></td>
      <td>
        <?php if ($openDmsReady) { ?>
          <a class="btn btn-primary btn-sm" href="<?php echo site_url('kt_integration_hub/kt_integration_sso/dms?workspace_id=' . (int) $workspaceRow['id']); ?>">Mở DMS</a>
        <?php } else { ?>
          <a class="btn btn-default btn-sm" href="#workspace-readiness">Kiểm tra trạng thái</a>
          <div class="small text-muted tw-mt-1">Hệ thống đang hoàn tất đồng bộ dữ liệu.</div>
        <?php } ?>
      </td>
    </tr><?php } ?></tbody></table></div><?php } ?>
  </div></div>
  <div class="alert alert-info">Thông tin kỹ thuật được ẩn khỏi doanh nghiệp. Khi cần xử lý, hãy dùng “Đồng bộ ngay” hoặc “Kiểm tra hệ thống”.</div>
</div></div>
<style>
  #dms-setup-center .dms-setup-details > summary { cursor: pointer; list-style: none; }
  #dms-setup-center .dms-setup-details > summary::-webkit-details-marker { display: none; }
  #dms-setup-center .dms-setup-details > summary::after { content: ' +'; float: right; }
  #dms-setup-center .dms-setup-details[open] > summary::after { content: ' −'; }
  #dms-setup-center [data-sync-jump] { margin-left: 4px; }
  #dms-setup-center [data-sync-busy] { opacity: .65; pointer-events: none; }
</style>
<script>
(function () {
  var root = document.getElementById('dms-setup-center');
  if (!root) return;
  var storageKey = 'dms_setup_sync_started_at';
  root.querySelectorAll('form').forEach(function (form) {
    var action = form.getAttribute('action') || '';
    if (!/dms_(save_staff_access|run_customer_resync|run_product_resync|run_warehouse_resync|reprovision)/i.test(action)) return;
    form.addEventListener('submit', function () {
      sessionStorage.setItem(storageKey, String(Date.now()));
      form.setAttribute('data-sync-busy', '1');
      var button = form.querySelector('button[type="submit"]');
      if (button) { button.disabled = true; button.textContent = 'Đang xử lý...'; }
    });
  });
  var started = Number(sessionStorage.getItem(storageKey) || 0);
  if (started > 0) {
    sessionStorage.removeItem(storageKey);
    var notice = document.createElement('div');
    notice.className = 'alert alert-info';
    notice.textContent = 'Đang xử lý đồng bộ. Hệ thống sẽ tự kiểm tra lại trạng thái.';
    root.insertBefore(notice, root.firstChild);
    window.setTimeout(function () { window.location.reload(); }, 2500);
  }
}());
</script>
<?php init_tail(); ?>
