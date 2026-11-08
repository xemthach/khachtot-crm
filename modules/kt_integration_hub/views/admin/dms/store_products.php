<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$ops = is_array($operations ?? null) ? $operations : [];
$usageRows = is_array($ops['usage'] ?? null) ? $ops['usage'] : [];
$lowStockRows = is_array($ops['low_stock_rows'] ?? null) ? $ops['low_stock_rows'] : [];
$movementRows = is_array($ops['inventory_movement_rows'] ?? null) ? $ops['inventory_movement_rows'] : [];
$topProducts = is_array($ops['top_ordered_products'] ?? null) ? $ops['top_ordered_products'] : [];
$projectionSource = is_array($ops['projection_source'] ?? null) ? $ops['projection_source'] : [];
$actions = is_array($actions ?? null) ? $actions : [];
$money = static function ($value) {
    if ($value === null || $value === '') {
        return 'Chưa có dữ liệu';
    }

    return number_format((float) $value, 0) . ' VND';
};
?>
<div id="wrapper"><div class="content">
  <div class="row"><div class="col-md-8"><h4 class="tw-mb-1">Điểm bán & Sản phẩm</h4><p class="text-muted">Theo dõi điểm bán, sản phẩm, tồn kho và sản phẩm bán chạy từ bản sao đồng bộ an toàn trong CRM. Nếu chưa có dữ liệu đồng bộ, hệ thống không suy diễn số liệu từ nguồn khác.</p></div><div class="col-md-4 text-right"><a href="<?php echo e(admin_url('kt_integration_hub/dms_setup_center?tab=operations')); ?>" class="btn btn-default">Thiết lập đồng bộ</a><?php if (!empty($actions['open_dms_url'])) { ?><a href="<?php echo e($actions['open_dms_url']); ?>" class="btn btn-primary">Mở app DMS</a><?php } ?></div></div>
  <div class="alert alert-info">
    <strong>Nguồn dữ liệu:</strong>
    Tồn kho: <?php echo e((string) ($projectionSource['inventory_rows'] ?? 0)); ?> bản ghi;
    sản phẩm bán ra: <?php echo e((string) ($projectionSource['product_sales_rows'] ?? 0)); ?> bản ghi;
    độ phủ điểm bán: <?php echo e((string) ($projectionSource['store_coverage_rows'] ?? 0)); ?> bản ghi.
    Khi chưa có dữ liệu, hệ thống sẽ hiển thị trạng thái trống trung thực.
  </div>
  <p class="text-muted small">Danh mục sản phẩm chuẩn được quản lý trong CRM; dữ liệu hóa đơn lịch sử không phải danh mục sản phẩm và không bị đồng bộ sản phẩm sửa đổi. Đồng bộ tồn kho chỉ dùng kho DMS làm nguồn đúng và chỉ hiển thị cho điểm bán CRM sau khi có liên kết một kho DMS với các điểm bán CRM.</p>
  <div class="row">
    <?php foreach ([['Điểm bán đã đồng bộ', $ops['stores_synced'] ?? 0], ['Sản phẩm đã đồng bộ', $ops['products_synced'] ?? 0], ['Điểm bán ghé hôm nay', $ops['stores_visited_today'] ?? 0], ['Điểm bán ghé tháng này', $ops['stores_visited_month'] ?? 0], ['Sắp hết hàng', $ops['low_stock'] ?? null], ['Biến động tồn kho', $ops['inventory_movement'] ?? null]] as $card) { ?>
      <div class="col-md-2 col-sm-4"><div class="panel_s"><div class="panel-body"><div class="text-uppercase text-muted small"><?php echo e($card[0]); ?></div><div class="tw-text-2xl tw-font-semibold"><?php echo $card[1] === null ? 'Chưa có dữ liệu' : e((string) $card[1]); ?></div></div></div></div>
    <?php } ?>
  </div>
  <div class="panel_s"><div class="panel-heading">Hạn mức điểm bán / sản phẩm</div><div class="panel-body">
    <?php if (empty($usageRows)) { ?><div class="alert alert-info">Chưa có dữ liệu sử dụng hạn mức. Kiểm tra gói DMS trong Thiết lập đội DMS.</div><?php } else { ?><div class="row"><?php foreach ($usageRows as $row) { ?><div class="col-md-3"><span class="text-muted"><?php echo e((string) ($row['label'] ?? 'Sử dụng')); ?></span><br><strong><?php echo e((string) ($row['used'] ?? 0)); ?> / <?php echo (int) ($row['limit'] ?? 0) > 0 ? e((string) $row['limit']) : 'Không giới hạn'; ?></strong></div><?php } ?></div><?php } ?>
  </div></div>
  <div class="row">
    <div class="col-md-6"><div class="panel_s"><div class="panel-heading">Sản phẩm sắp hết</div><div class="panel-body">
      <?php if (empty($lowStockRows)) { ?>
        <div class="alert alert-info tw-mb-0">Chưa có sản phẩm sắp hết hàng được ghi nhận trong DMS, hoặc DMS chưa có snapshot tồn kho cho tenant này.</div>
      <?php } else { ?>
        <table class="table table-striped"><thead><tr><th>Sản phẩm</th><th>Điểm bán</th><th>Tồn</th><th>Ngưỡng</th></tr></thead><tbody>
          <?php foreach ($lowStockRows as $row) { ?><tr><td><?php echo e((string) ($row['product_name'] ?? '-')); ?></td><td><?php echo e((string) ($row['store_name'] ?? '-')); ?></td><td><?php echo e((string) ($row['stock_quantity'] ?? 0)); ?></td><td><?php echo e((string) ($row['low_stock_threshold'] ?? 0)); ?></td></tr><?php } ?>
        </tbody></table>
      <?php } ?>
    </div></div></div>
    <div class="col-md-6"><div class="panel_s"><div class="panel-heading">Top sản phẩm theo đơn hàng tháng này</div><div class="panel-body">
      <?php if (empty($topProducts)) { ?>
        <div class="alert alert-info tw-mb-0">Chưa có dòng đơn hàng để tính top sản phẩm.</div>
      <?php } else { ?>
        <table class="table table-striped"><thead><tr><th>Sản phẩm</th><th>Số lượng</th><th>Giá trị</th></tr></thead><tbody>
          <?php foreach ($topProducts as $row) { ?><tr><td><?php echo e((string) ($row['product_name'] ?? '-')); ?></td><td><?php echo e((string) ((int) ($row['quantity'] ?? 0))); ?></td><td><?php echo e($money($row['total_price'] ?? null)); ?></td></tr><?php } ?>
        </tbody></table>
      <?php } ?>
    </div></div></div>
  </div>
  <div class="panel_s"><div class="panel-heading">Biến động tồn kho gần nhất</div><div class="panel-body">
    <?php if (empty($movementRows)) { ?>
      <div class="alert alert-info tw-mb-0">Chưa có biến động tồn kho được ghi nhận. Khi DMS phát sinh nhập/xuất/giữ hàng, CRM sẽ hiển thị lịch sử tại đây.</div>
    <?php } else { ?>
      <table class="table table-striped"><thead><tr><th>Sản phẩm</th><th>Điểm bán</th><th>Loại</th><th>Số lượng</th><th>Trước/Sau</th><th>Thời gian</th></tr></thead><tbody>
        <?php foreach ($movementRows as $row) { ?><tr><td><?php echo e((string) ($row['product_name'] ?? '-')); ?></td><td><?php echo e((string) ($row['store_name'] ?? '-')); ?></td><td><?php echo e((string) ($row['transaction_type'] ?? '-')); ?></td><td><?php echo e((string) ($row['quantity'] ?? 0)); ?></td><td><?php echo e((string) ($row['quantity_before'] ?? 0)); ?> / <?php echo e((string) ($row['quantity_after'] ?? 0)); ?></td><td><?php echo e((string) ($row['created_at'] ?? '-')); ?></td></tr><?php } ?>
      </tbody></table>
    <?php } ?>
  </div></div>
</div></div>
<?php init_tail(); ?>
