<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$summary = is_array($summary ?? null) ? $summary : [];
$rows = is_array($rows ?? null) ? $rows : [];
$actions = is_array($actions ?? null) ? $actions : [];
$source = is_array($source_context ?? null) ? $source_context : [];
$activeTab = (string) ($active_tab ?? 'orders');
$badge = static function ($status) { $s=strtolower(trim((string)$status)); if (in_array($s,['done','synced','paid','approved','created'],true)) return 'success'; if (in_array($s,['pending','draft','unpaid','partially_paid'],true)) return 'warning'; if (in_array($s,['failed','cancelled','rejected'],true)) return 'danger'; return 'default'; };
$money = static function ($value) { return kt_dms_ui_money($value); };
$sourceLabel = static function ($source) {
    $source = strtolower(trim((string) $source));
    if ($source === 'crm.invoice' || $source === 'crm.invoices') {
        return 'Hóa đơn / thanh toán CRM';
    }
    if ($source === 'salesrep.readback' || $source === 'salesrep.orders.readback') {
        return 'Dữ liệu đọc lại từ DMS';
    }

    return 'Chưa có dữ liệu thanh toán';
};
$tabs = ['orders' => 'Đơn hàng DMS', 'finance' => 'Hóa đơn CRM & thanh toán', 'errors' => 'Lỗi đồng bộ'];
?>
<div id="wrapper"><div class="content">
  <div class="row"><div class="col-md-8"><h4 class="tw-mb-1">Đơn hàng DMS / Hóa đơn / Thanh toán</h4><p class="text-muted">Theo dõi đơn hàng DMS, hóa đơn CRM và dữ liệu thanh toán đọc từ CRM. Hóa đơn và thanh toán CRM được ưu tiên; khi CRM chưa có bản ghi tương ứng, màn hình fallback sang DMS readback và ghi rõ nguồn dữ liệu.</p></div><div class="col-md-4 text-right"><?php if (!empty($actions['open_dms_url'])) { ?><a href="<?php echo e($actions['open_dms_url']); ?>" class="btn btn-primary">Mở app DMS</a><?php } ?><a href="<?php echo e(admin_url('kt_integration_hub/dms_sync_health')); ?>" class="btn btn-default">Đồng bộ</a></div></div>

  <div class="row">
    <?php foreach ([['Đơn DMS','total','Tất cả đơn đã đồng bộ'],['Đã tạo hóa đơn','with_invoice','Đã có hóa đơn CRM'],['Chờ đồng bộ','pending','Cần đợi xử lý'],['Lỗi đồng bộ','failed','Cần kiểm tra'],['Đã hủy','cancelled','Đơn hoặc hóa đơn đã hủy']] as $card) { ?><div class="col-md-2 col-sm-4"><div class="panel_s"><div class="panel-body"><div class="text-uppercase text-muted small"><?php echo e($card[0]); ?></div><div class="tw-text-2xl tw-font-semibold"><?php echo e((string)($summary[$card[1]] ?? 0)); ?></div><div class="text-muted small"><?php echo e($card[2]); ?></div></div></div></div><?php } ?>
  </div>
  <div class="row">
    <?php foreach ([['Doanh số DMS','sales_amount','Từ đơn hàng DMS'],['Giá trị đã có hóa đơn','invoiced_amount','Đơn đã liên kết hóa đơn CRM'],['Đã thanh toán','paid_amount','Nguồn thanh toán: CRM'],['Còn phải thu','balance_amount','Nguồn thanh toán: CRM']] as $card) { ?><div class="col-md-3 col-sm-6"><div class="panel_s"><div class="panel-body"><div class="text-uppercase text-muted small"><?php echo e($card[0]); ?></div><div class="tw-text-xl tw-font-semibold"><?php echo e($money($summary[$card[1]] ?? null)); ?></div><div class="text-muted small"><?php echo e($card[2]); ?></div></div></div></div><?php } ?>
  </div>

  <div class="panel_s"><div class="panel-body"><ul class="nav nav-tabs"><?php foreach ($tabs as $key => $label) { ?><li class="<?php echo $activeTab === $key ? 'active' : ''; ?>"><a href="<?php echo e(admin_url('kt_integration_hub/dms_orders_invoices?tab=' . $key)); ?>"><?php echo e($label); ?></a></li><?php } ?></ul></div></div>

  <?php if (!empty($source)) { ?><div class="alert alert-info"><strong>Nguồn dữ liệu:</strong> Trạng thái đơn hàng được đồng bộ từ DMS; hóa đơn và thanh toán lấy từ CRM. Khi CRM chưa có bản ghi, màn hình chỉ hiển thị trạng thái đọc lại đã được xác minh. Bản ghi: <?php echo e((string)($source['record_count'] ?? 0)); ?>. Lần gần nhất: <?php echo e((string)(($source['last_event_at'] ?? '') ?: 'Chưa có')); ?>.</div><?php } ?>

  <div class="panel_s"><div class="panel-body"><form method="get" class="row">
    <input type="hidden" name="tab" value="<?php echo e($activeTab); ?>">
    <?php foreach (($filter_fields ?? []) as $field) { $key=$field['key'] ?? ''; $type=in_array($key,['date_from','date_to'],true)?'date':'text'; ?><div class="col-md-2 col-sm-6"><?php echo render_input($key, $field['label'] ?? $key, $filters[$key] ?? '', $type); ?></div><?php } ?>
    <div class="col-md-2"><label>&nbsp;</label><div><button class="btn btn-primary" type="submit">Lọc</button> <a class="btn btn-default" href="<?php echo e(admin_url('kt_integration_hub/dms_orders_invoices?tab=' . $activeTab)); ?>">Xóa</a></div></div>
  </form></div></div>

  <div class="panel_s"><div class="panel-body">
    <?php if (empty($rows)) { ?><div class="alert alert-info"><strong>Chưa có đơn hàng DMS nào.</strong><div>Khi nhân viên tạo đơn và quản lý duyệt, hệ thống sẽ đồng bộ đơn sang CRM và hiển thị hóa đơn tại đây.</div></div><?php } else { ?>
      <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Đơn DMS</th><th>Hóa đơn CRM</th><th>Trạng thái invoice</th><th>Thanh toán</th><th>Đồng bộ</th><th>Lần cập nhật</th><th>Hành động</th></tr></thead><tbody>
        <?php foreach($rows as $row){ $failed = strtolower((string)($row['bridge_status'] ?? '')) === 'failed'; if ($activeTab === 'errors' && !$failed) { continue; } ?><tr>
          <td><strong><?php echo e((string)($row['dms_order_number'] ?? ('#' . ($row['dms_order_id'] ?? '-')))); ?></strong><div class="text-muted small">DMS ID: <?php echo e((string)($row['dms_order_id'] ?? '-')); ?></div></td>
          <td><?php echo (int)($row['crm_invoice_id'] ?? 0) > 0 ? '#' . e((string)$row['crm_invoice_id']) : '<span class="text-muted">Chưa tạo</span>'; ?></td>
          <td><span class="label label-<?php echo $badge(($row['crm_invoice_status_readback'] ?? '') ?: ($row['invoice_status_snapshot'] ?? '')); ?>"><?php echo e(kt_dms_ui_status_label('invoice', (($row['crm_invoice_status_readback'] ?? '') ?: ($row['invoice_status_snapshot'] ?? '')))); ?></span></td>
          <td>
            <div><strong>Giá trị:</strong> <?php echo e($money($row['total_amount'] ?? null)); ?></div>
            <div><strong>Trạng thái:</strong> <?php echo e(kt_dms_ui_status_label('payment', $row['crm_payment_status'] ?? '')); ?></div>
            <div><strong>Đã trả:</strong> <?php echo e($money($row['crm_paid_amount'] ?? null)); ?> <span class="text-muted">/</span> <strong>Còn lại:</strong> <?php echo e($money($row['crm_balance_amount'] ?? null)); ?></div>
            <div class="text-muted small">Nguồn: <?php echo e($sourceLabel($row['payment_data_source'] ?? '')); ?></div>
          </td>
          <td><span class="label label-<?php echo $badge($row['bridge_status'] ?? ''); ?>"><?php echo e(kt_dms_ui_status_label('sync', $row['bridge_status'] ?? '')); ?></span><?php if (!empty($row['error_message'])) { ?><div class="text-danger small">Không thể đồng bộ bản ghi này. Vui lòng mở Sức khỏe đồng bộ để kiểm tra.</div><?php } ?></td>
          <td><?php echo e((string)(($row['last_synced_at'] ?? '') ?: ($row['updated_at'] ?? '-'))); ?></td>
          <td><?php if ($failed) { ?><a href="<?php echo e(admin_url('kt_integration_hub/dms_sync_health')); ?>" class="btn btn-default btn-xs">Kiểm tra</a><?php } elseif ((int)($row['crm_invoice_id'] ?? 0) > 0) { ?><span class="text-muted">Mở hóa đơn trong CRM nếu cần</span><?php } ?></td>
        </tr><?php } ?>
      </tbody></table></div>
    <?php } ?>
  </div></div>
</div></div>
<?php init_tail(); ?>
