<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$summary = isset($summary) && is_array($summary) ? $summary : [];
$rows = isset($rows) && is_array($rows) ? $rows : [];
$filters = isset($filters) && is_array($filters) ? $filters : [];
$actions = isset($actions) && is_array($actions) ? $actions : [];
$sourceContext = isset($source_context) && is_array($source_context) ? $source_context : [];
$typeLabels = [
    'customer_feedback' => 'Khách hàng',
    'competitor_activity' => 'Đối thủ',
    'price_feedback' => 'Giá thị trường',
    'display_photo_report' => 'Ảnh trưng bày',
    'stockout_report' => 'Báo hết hàng',
    'promotion_report' => 'Khuyến mãi',
    'complaint' => 'Khiếu nại',
    'market_opportunity' => 'Cơ hội bán hàng',
    'custom_note' => 'Ghi chú khác',
];
$statusLabels = ['new' => 'Mới', 'in_review' => 'Đang xử lý', 'assigned' => 'Đã giao xử lý', 'resolved' => 'Đã xử lý', 'rejected' => 'Từ chối', 'cancelled' => 'Đã hủy'];
$severityLabels = ['low' => 'Thấp', 'normal' => 'Bình thường', 'high' => 'Cao', 'urgent' => 'Khẩn cấp'];
?>
<div id="wrapper">
  <div class="content">
    <div class="row"><div class="col-md-12">
      <div class="row">
        <div class="col-md-8">
          <h4 class="tw-mb-1">Phản ánh thị trường</h4>
          <p class="text-muted">Theo dõi phản ánh từ đội sales theo cửa hàng, mức độ và trạng thái xử lý.</p>
        </div>
        <div class="col-md-4 text-right">
          <?php if (!empty($actions['open_dms_url']) && ($actions['open_dms_mode'] ?? 'disabled') !== 'disabled') { ?>
            <a href="<?php echo e($actions['open_dms_url']); ?>" class="btn btn-primary">Mở app DMS</a>
          <?php } ?>
          <a href="<?php echo e(admin_url('kt_integration_hub/dms_sync_health')); ?>" class="btn btn-default">Đồng bộ</a>
        </div>
      </div>

      <div class="row">
        <?php
        $cards = [
            ['label' => 'Tổng phản ánh', 'value' => (int)($summary['total'] ?? 0), 'class' => 'info'],
            ['label' => 'Mới', 'value' => (int)($summary['new'] ?? 0), 'class' => 'warning'],
            ['label' => 'Khẩn cấp', 'value' => (int)($summary['urgent'] ?? 0), 'class' => 'danger'],
            ['label' => 'Đã xử lý', 'value' => (int)($summary['resolved'] ?? 0), 'class' => 'success'],
        ];
        foreach ($cards as $card) { ?>
          <div class="col-md-3 col-sm-6"><div class="panel_s"><div class="panel-body">
            <span class="text-muted"><?php echo e($card['label']); ?></span>
            <h3 class="tw-mt-2 tw-mb-0 text-<?php echo e($card['class']); ?>"><?php echo (int)$card['value']; ?></h3>
          </div></div></div>
        <?php } ?>
      </div>

      <div class="panel_s"><div class="panel-body">
        <?php echo form_open(admin_url('kt_integration_hub/dms_field_feedback'), ['method' => 'get', 'class' => 'row']); ?>
          <div class="col-md-3">
            <label>Loại phản ánh</label>
            <select name="feedback_type" class="form-control">
              <option value="">Tất cả</option>
              <?php foreach ($typeLabels as $key => $label) { ?>
                <option value="<?php echo e($key); ?>" <?php echo ($filters['feedback_type'] ?? '') === $key ? 'selected' : ''; ?>><?php echo e($label); ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="col-md-2">
            <label>Trạng thái</label>
            <select name="status" class="form-control">
              <option value="">Tất cả</option>
              <?php foreach ($statusLabels as $key => $label) { ?>
                <option value="<?php echo e($key); ?>" <?php echo ($filters['status'] ?? '') === $key ? 'selected' : ''; ?>><?php echo e($label); ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="col-md-2">
            <label>Mức độ</label>
            <select name="severity" class="form-control">
              <option value="">Tất cả</option>
              <?php foreach ($severityLabels as $key => $label) { ?>
                <option value="<?php echo e($key); ?>" <?php echo ($filters['severity'] ?? '') === $key ? 'selected' : ''; ?>><?php echo e($label); ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="col-md-2"><label>Từ ngày</label><input type="date" name="date_from" class="form-control" value="<?php echo e($filters['date_from'] ?? ''); ?>"></div>
          <div class="col-md-2"><label>Đến ngày</label><input type="date" name="date_to" class="form-control" value="<?php echo e($filters['date_to'] ?? ''); ?>"></div>
          <div class="col-md-1"><label>&nbsp;</label><button class="btn btn-primary btn-block">Lọc</button></div>
        <?php echo form_close(); ?>
      </div></div>

      <div class="panel_s"><div class="panel-body">
        <?php if (empty($rows)) { ?>
          <div class="text-center tw-py-8">
            <h4>Không có dữ liệu phản ánh</h4>
            <p class="text-muted">Khi field sales gửi phản ánh từ app DMS, dữ liệu sẽ xuất hiện tại đây sau khi đồng bộ.</p>
            <?php if (!empty($actions['open_dms_url'])) { ?><a href="<?php echo e($actions['open_dms_url']); ?>" class="btn btn-primary">Mở app DMS</a><?php } ?>
          </div>
        <?php } else { ?>
          <div class="table-responsive">
            <table class="table table-striped">
              <thead><tr>
                <th>Thời gian gửi</th><th>Loại</th><th>Mức độ</th><th>Trạng thái</th><th>Nhân viên</th><th>Cửa hàng</th><th>Nội dung</th><th>Bằng chứng</th>
              </tr></thead>
              <tbody>
              <?php foreach ($rows as $row) { ?>
                <tr>
                  <td><?php echo e($row['submitted_at'] ?? ''); ?></td>
                  <td><?php echo e($typeLabels[$row['feedback_type'] ?? ''] ?? ($row['feedback_type'] ?? '')); ?></td>
                  <td><?php echo e($severityLabels[$row['severity'] ?? ''] ?? ($row['severity'] ?? '')); ?></td>
                  <td><span class="label label-default"><?php echo e($statusLabels[$row['status'] ?? ''] ?? ($row['status'] ?? '')); ?></span></td>
                  <td><?php echo e($row['staff_name'] ?? ''); ?><br><small class="text-muted"><?php echo e($row['staff_email'] ?? ''); ?></small></td>
                  <td><?php echo e($row['store_name'] ?? 'Không gắn'); ?><br><small class="text-muted"><?php echo e($row['store_external_id'] ?? ''); ?></small></td>
                  <td><strong><?php echo e($row['title'] ?? ''); ?></strong><br><span class="text-muted"><?php echo e($row['description_summary'] ?? ''); ?></span></td>
                  <td><?php echo ((int)($row['photo_count'] ?? 0) > 0 ? 'Có ảnh' : 'Không ảnh'); ?> · <?php echo (!empty($row['latitude']) && !empty($row['longitude']) ? 'Có vị trí' : 'Không có vị trí'); ?></td>
                </tr>
              <?php } ?>
              </tbody>
            </table>
          </div>
        <?php } ?>
      </div></div>

      <div class="alert alert-info">
        Nguồn dữ liệu: phản ánh được gửi từ DMS và đồng bộ về CRM trong phạm vi doanh nghiệp hiện tại.
        <?php if (!empty($sourceContext['last_event_at'])) { ?>Lần đồng bộ gần nhất: <?php echo e($sourceContext['last_event_at']); ?>.<?php } ?>
      </div>
    </div></div>
  </div>
</div>
<?php init_tail(); ?>
