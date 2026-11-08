<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$order = is_array($order ?? null) ? $order : [];
$invoice = (array) ($order['invoice'] ?? []);
$events = (array) ($order['events'] ?? []);
$statusLabel = is_callable($status_label ?? null) ? $status_label : static fn ($status) => 'Chưa xác định';
$cycleLabels = ['monthly' => 'Hàng tháng', 'quarterly' => 'Hàng quý', 'yearly' => 'Hàng năm'];
$date = static function ($value) {
    $timestamp = strtotime((string) $value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : 'Chưa có dữ liệu';
};
$money = static fn ($amount, $currency = 'VND') => number_format((float) $amount, 0, ',', '.') . ' ' . html_escape((string) $currency);
$isPending = !empty($order) && in_array((string) ($order['payment_status'] ?? ''), ['payment_pending', 'pending'], true);
$isReady = !empty($order) && (string) ($order['workspace_status'] ?? '') === 'ready';
$isBlocked = !empty($order) && (string) ($order['status'] ?? '') === 'blocked';
$isCancelled = !empty($order) && (string) ($order['status'] ?? '') === 'cancelled';
$progress = $isReady ? 100 : ((string) ($order['subscription_status'] ?? '') === 'active' ? 75 : ((string) ($order['verification_status'] ?? '') === 'verified' ? 50 : (!empty($order) ? 25 : 0)));
?>
<style>
  .dms-order-shell{max-width:1040px;margin:0 auto}.dms-order-hero,.dms-order-card,.dms-order-timeline{border:1px solid #dbe4ef;border-radius:8px;background:#fff;box-shadow:0 2px 8px rgba(15,23,42,.05)}
  .dms-order-hero{padding:26px}.dms-order-hero h2{margin:8px 0;color:#172b4d}.dms-order-progress{height:10px;margin:20px 0 8px;border-radius:999px;background:#e8edf5;overflow:hidden}.dms-order-progress span{display:block;height:100%;border-radius:999px;background:#2563eb}
  .dms-order-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin-top:20px}.dms-order-card{padding:22px}.dms-order-facts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.dms-order-fact span{display:block;color:#64748b;font-size:12px}.dms-order-fact strong{display:block;margin-top:4px;color:#172b4d;overflow-wrap:anywhere}
  .dms-order-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:20px}.dms-order-timeline{margin-top:20px;padding:22px}.dms-event{position:relative;padding:0 0 18px 28px;border-left:2px solid #dbe4ef}.dms-event:last-child{padding-bottom:0}.dms-event:before{position:absolute;left:-7px;top:2px;width:12px;height:12px;border-radius:50%;background:#2563eb;content:''}.dms-event time{color:#64748b;font-size:12px}.dms-empty{padding:24px;border:1px solid #fde68a;border-radius:8px;background:#fffbeb}
  @media(max-width:767px){.dms-order-grid,.dms-order-facts{grid-template-columns:1fr}.dms-order-actions .btn,.dms-order-actions form{width:100%}.dms-order-actions form .btn{width:100%}}
</style>
<div id="wrapper"><div class="content"><div class="dms-order-shell" data-r28-tenant-order-status>
  <?php if (empty($order)) { ?>
    <section class="dms-empty">
      <span class="label label-warning">Chưa kích hoạt</span>
      <h2>DMS chưa sẵn sàng</h2>
      <p>Bạn chưa có đơn DMS. Hãy chọn gói phù hợp để bắt đầu thiết lập đội sales thị trường.</p>
      <a class="btn btn-primary" href="<?php echo admin_url('kt_saas/tenant_marketplace_app/salesflow-dms'); ?>">Chọn gói DMS</a>
      <p class="text-muted tw-mt-3 tw-mb-0">Nếu cần hỗ trợ chọn gói, vui lòng liên hệ quản trị viên Khách Tốt.</p>
    </section>
  <?php } else { ?>
    <section class="dms-order-hero">
      <span class="label label-<?php echo $isReady ? 'success' : ($isBlocked ? 'danger' : 'warning'); ?>"><?php echo html_escape($statusLabel($order['status'] ?? '')); ?></span>
      <h2><?php echo $isReady ? 'Workspace DMS đã sẵn sàng' : 'DMS chưa sẵn sàng'; ?></h2>
      <p><?php
        if ($isReady) echo 'Gói DMS đã được thanh toán, xác minh và kích hoạt. Bạn có thể mở workspace.';
        elseif ($isBlocked) echo 'Tiến trình kích hoạt cần được kiểm tra thủ công. Dữ liệu đơn và thanh toán vẫn được giữ an toàn.';
        elseif ($isPending) echo 'Gói DMS của bạn đang chờ thanh toán hoặc chưa hoàn tất xác minh.';
        elseif ($isCancelled) echo 'Đơn DMS này đã được hủy. Bạn có thể quay lại chọn gói khác.';
        else echo 'Hệ thống đang xác minh thanh toán và chuẩn bị workspace DMS.';
      ?></p>
      <div class="dms-order-progress" aria-label="Tiến độ kích hoạt"><span style="width:<?php echo (int) $progress; ?>%"></span></div>
      <div class="text-muted small">Tiến độ kích hoạt: <?php echo (int) $progress; ?>%</div>
      <div class="dms-order-actions">
        <?php if ($isPending && !empty($checkout_url)) { ?><a class="btn btn-primary" href="<?php echo html_escape((string) $checkout_url); ?>"><?php echo !empty($operational_source) ? 'Xem thanh toán' : 'Tiếp tục thanh toán'; ?></a><?php } ?>
        <?php if ($isReady) { ?><a class="btn btn-primary" href="<?php echo html_escape((string) $open_dms_url); ?>">Mở DMS</a><?php } ?>
        <?php if (!$isPending && !$isReady && !$isCancelled) { ?><a class="btn btn-primary" href="<?php echo current_url(); ?>">Xem tiến độ kích hoạt</a><?php } ?>
        <?php if ($isBlocked) { ?><a class="btn btn-default" href="<?php echo current_url(); ?>">Thử lại</a><?php } ?>
        <?php if ($isBlocked) { ?><a class="btn btn-warning" href="<?php echo admin_url('support'); ?>">Liên hệ hỗ trợ</a><?php } ?>
        <a class="btn btn-default" href="<?php echo admin_url('kt_saas/tenant_marketplace_app/salesflow-dms'); ?>">Quay lại chọn gói</a>
        <?php if ($isPending) { ?>
          <?php echo form_open(admin_url('kt_saas/tenant_cancel_dms_order/' . (int) $order['id']), ['style' => 'display:inline-block']); ?>
            <button class="btn btn-default" type="submit" onclick="return confirm('Hủy đơn DMS đang chờ thanh toán?');">Hủy đơn hiện tại</button>
          <?php echo form_close(); ?>
        <?php } ?>
      </div>
    </section>

    <div class="dms-order-grid">
      <section class="dms-order-card">
        <h4 class="tw-mt-0">Thông tin đơn DMS</h4>
        <div class="dms-order-facts">
          <div class="dms-order-fact"><span>Gói DMS đã chọn</span><strong><?php echo html_escape((string) ($order['package_name'] ?? '')); ?></strong></div>
          <div class="dms-order-fact"><span>Chu kỳ thanh toán</span><strong><?php echo html_escape($cycleLabels[$order['billing_cycle'] ?? ''] ?? 'Chưa xác định'); ?></strong></div>
          <div class="dms-order-fact"><span>Số tiền</span><strong><?php echo $money($order['amount'] ?? 0, $order['currency'] ?? 'VND'); ?></strong></div>
          <div class="dms-order-fact"><span>Ngày tạo</span><strong><?php echo html_escape($date($order['created_at'] ?? '')); ?></strong></div>
          <div class="dms-order-fact"><span>Mã hóa đơn</span><strong><?php echo html_escape((string) ($invoice['invoice_number'] ?? 'Chưa có')); ?></strong></div>
          <div class="dms-order-fact"><span>Mã thanh toán</span><strong><?php echo html_escape((string) ($order['payment_reference'] ?? 'Chưa có')); ?></strong></div>
        </div>
      </section>
      <section class="dms-order-card">
        <h4 class="tw-mt-0">Trạng thái kích hoạt</h4>
        <p><strong>Trạng thái thanh toán:</strong> <?php echo html_escape($statusLabel($order['payment_status'] ?? '')); ?></p>
        <p><strong>Xác minh:</strong> <?php echo html_escape($statusLabel($order['verification_status'] ?? '')); ?></p>
        <p><strong>Gói DMS:</strong> <?php echo html_escape($statusLabel($order['subscription_status'] ?? '')); ?></p>
        <p><strong>Khởi tạo workspace:</strong> <?php echo html_escape($statusLabel($order['provisioning_status'] ?? '')); ?></p>
        <p class="tw-mb-0"><strong>Workspace:</strong> <?php echo html_escape($statusLabel($order['workspace_status'] ?? '')); ?></p>
      </section>
    </div>

    <section class="dms-order-timeline">
      <h4 class="tw-mt-0">Tiến trình đơn DMS</h4>
      <?php foreach (array_reverse($events) as $event) { ?>
        <div class="dms-event"><strong><?php echo html_escape((string) ($event['message'] ?? 'Đã cập nhật trạng thái.')); ?></strong><br><time><?php echo html_escape($date($event['created_at'] ?? '')); ?></time></div>
      <?php } ?>
      <?php if (empty($events)) { ?><p class="text-muted tw-mb-0">Chưa có sự kiện cho đơn DMS này.</p><?php } ?>
    </section>
    <p class="text-muted small tw-mt-3">Mã tham chiếu hỗ trợ: DMS-<?php echo (int) $order['id']; ?>. Không chia sẻ thông tin đăng nhập hoặc mã xác thực thanh toán.</p>
  <?php } ?>
</div></div></div>
<?php init_tail(); ?>
