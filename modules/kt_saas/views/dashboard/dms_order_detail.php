<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$CI = &get_instance();
$order = (array) ($order ?? []); $tenant=(array)($order['tenant']??[]); $invoice=(array)($order['invoice']??[]); $payment=(array)($order['payment']??[]); $job=(array)($order['provision_job']??[]); $events=(array)($order['events']??[]);
$health=(array)($process_health??[]); $cron=(array)($health['cron']??[]); $staffHealth=(array)($health['staff_sync']??[]);
$label=static fn($status)=>$CI->Kt_saas_model->dms_order_status_label($status); $date=static fn($v)=>strtotime((string)$v)?date('d/m/Y H:i',strtotime((string)$v)):'Chưa có dữ liệu'; $cycleLabels=['monthly'=>'Hàng tháng','quarterly'=>'Hàng quý','yearly'=>'Hàng năm'];
?>
<style>
 .dms-detail-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.dms-detail-card{padding:20px;border:1px solid #dbe4ef;border-radius:8px;background:#fff}.dms-detail-card h5{margin-top:0;color:#172b4d}.dms-detail-actions{display:flex;flex-wrap:wrap;gap:8px}.dms-detail-actions form{display:inline-block}.dms-ledger{position:relative;margin-left:8px;padding-left:24px;border-left:2px solid #dbe4ef}.dms-ledger-item{position:relative;padding-bottom:20px}.dms-ledger-item:before{position:absolute;left:-31px;top:3px;width:12px;height:12px;border-radius:50%;background:#2563eb;content:''}@media(max-width:991px){.dms-detail-grid{grid-template-columns:1fr}}
</style>
<div id="wrapper"><div class="content" data-r28-landlord-order-detail>
 <div class="row"><div class="col-md-8"><h4 class="tw-mb-1">Chi tiết đơn DMS #<?php echo (int)$order['id']; ?></h4><p class="text-muted"><?php echo html_escape((string)($tenant['company_name']??'')); ?> · <?php echo html_escape((string)$order['package_name']); ?></p></div><div class="col-md-4 text-right"><a class="btn btn-default" href="<?php echo admin_url('kt_saas/dms_orders'); ?>">Danh sách đơn DMS</a><a class="btn btn-default" href="<?php echo admin_url('kt_integration_hub/dms_landlord_tenant/'.(int)$order['tenant_id']); ?>">Chi tiết doanh nghiệp</a></div></div>
 <section class="dms-detail-grid">
  <div class="dms-detail-card"><h5>Doanh nghiệp & gói</h5><p><strong><?php echo html_escape((string)($tenant['company_name']??'')); ?></strong></p><p>Gói: <?php echo html_escape((string)$order['package_name']); ?></p><p>Chu kỳ: <?php echo html_escape($cycleLabels[$order['billing_cycle']??'']??'Chưa xác định'); ?></p><p>Số tiền: <?php echo number_format((float)$order['amount'],0,',','.').' '.html_escape((string)$order['currency']); ?></p></div>
  <div class="dms-detail-card"><h5>Hóa đơn & thanh toán</h5><p>Hóa đơn: <strong><?php echo html_escape((string)($invoice['invoice_number']??'Chưa có')); ?></strong></p><p>Mã thanh toán: <?php echo html_escape((string)($order['payment_reference']??'Chưa có')); ?></p><p>Thanh toán: <?php echo html_escape($label($order['payment_status']??'')); ?></p><p>Xác minh: <?php echo html_escape($label($order['verification_status']??'')); ?></p><p>Kiểm tra gần nhất: <?php echo html_escape($date($order['last_payment_checked_at']??'')); ?></p></div>
  <div class="dms-detail-card"><h5>Kích hoạt & workspace</h5><p>Gói DMS: <?php echo html_escape($label($order['subscription_status']??'')); ?></p><p>Khởi tạo: <?php echo html_escape($label($order['provisioning_status']??'')); ?></p><p>Workspace: <?php echo html_escape($label($order['workspace_status']??'')); ?></p><p>Tiến trình #<?php echo (int)($job['id']??0); ?>: <?php echo html_escape($label($job['status']??'')); ?></p></div>
 </section>
 <div class="panel_s tw-mt-4" data-r29-process-health><div class="panel-heading">Tình trạng tiến trình kích hoạt</div><div class="panel-body">
  <div class="dms-detail-grid">
   <div><strong>Cron nền tảng</strong><p><?php echo ($cron['status']??'')==='running'?'Đang chạy':'Cần chạy cron/worker'; ?></p><small>Lần chạy gần nhất: <?php echo html_escape($date($cron['last_run_at']??'')); ?></small></div>
   <div><strong>Khởi tạo workspace</strong><p><?php echo !empty($health['stale_lock'])?'Bị khóa do lock cũ':html_escape($label($job['status']??'not_started')); ?></p><small>Lỗi gần nhất: <?php echo html_escape((string)($job['error_message']??'Không có')); ?></small></div>
   <div><strong>Đồng bộ nhân sự</strong><p><?php echo (int)($staffHealth['failed']??0)>0?'Lỗi gần nhất':((int)($staffHealth['queued']??0)>0?'Đang chờ xử lý':'Sẵn sàng'); ?></p><small>Đang chờ: <?php echo (int)($staffHealth['queued']??0); ?> · Lỗi: <?php echo (int)($staffHealth['failed']??0); ?></small></div>
  </div>
 </div></div>
 <div class="panel_s tw-mt-4"><div class="panel-heading">Hành động vận hành</div><div class="panel-body"><div class="dms-detail-actions">
  <?php echo form_open(admin_url('kt_saas/dms_order_action/'.(int)$order['id'])); ?><input type="hidden" name="action" value="recheck_payment"><button class="btn btn-default">Kiểm tra lại thanh toán</button><?php echo form_close(); ?>
  <?php echo form_open(admin_url('kt_saas/dms_order_action/'.(int)$order['id'])); ?><input type="hidden" name="action" value="activate_subscription"><button class="btn btn-success">Kích hoạt sau xác minh</button><?php echo form_close(); ?>
  <?php echo form_open(admin_url('kt_saas/dms_order_action/'.(int)$order['id'])); ?><input type="hidden" name="action" value="retry_provisioning"><button class="btn btn-default">Thử lại khởi tạo workspace</button><?php echo form_close(); ?>
  <?php echo form_open(admin_url('kt_saas/dms_order_action/'.(int)$order['id'])); ?><input type="hidden" name="action" value="retry_staff_sync"><button class="btn btn-default">Đồng bộ lại nhân sự</button><?php echo form_close(); ?>
  <?php if(!in_array((string)$order['status'],['cancelled','workspace_ready'],true)){ echo form_open(admin_url('kt_saas/dms_order_action/'.(int)$order['id'])); ?><input type="hidden" name="action" value="cancel_order"><input type="hidden" name="reason" value="Landlord cancelled pending DMS order"><button class="btn btn-danger" onclick="return confirm('Hủy đơn DMS này?');">Hủy đơn</button><?php echo form_close(); } ?>
 </div><details class="tw-mt-4"><summary class="tw-font-semibold tw-cursor-pointer">Xác minh thanh toán thủ công</summary><?php echo form_open(admin_url('kt_saas/dms_order_action/'.(int)$order['id']),['class'=>'tw-mt-3']); ?><input type="hidden" name="action" value="verify_payment"><div class="form-group"><label>Mã giao dịch đã đối soát</label><input class="form-control" name="payment_reference" required></div><div class="checkbox checkbox-primary"><input id="confirm_payment_<?php echo (int)$order['id']; ?>" type="checkbox" name="confirm_payment" value="1"><label for="confirm_payment_<?php echo (int)$order['id']; ?>">Tôi xác nhận đã đối soát đúng số tiền và hóa đơn.</label></div><button class="btn btn-warning">Xác minh và kích hoạt</button><?php echo form_close(); ?></details>
 </div></div>
 <div class="panel_s"><div class="panel-heading">Lịch sử sự kiện</div><div class="panel-body"><div class="dms-ledger">
  <?php foreach(array_reverse($events) as $event){ ?><div class="dms-ledger-item"><strong><?php echo html_escape((string)$event['message']); ?></strong><div class="text-muted small"><?php echo html_escape($date($event['created_at']??'')); ?> · <?php echo ($event['actor_type']??'system')==='staff'?'Quản trị viên':'Hệ thống'; ?></div></div><?php } ?>
  <?php if(empty($events)){ ?><p class="text-muted">Chưa có sự kiện.</p><?php } ?>
 </div></div></div>
 <div class="alert alert-info">Thanh toán chỉ được kích hoạt khi hóa đơn đã được SePay xác minh hoặc quản trị viên xác nhận thủ công với mã đối soát. Không hiển thị payload hoặc khóa nhà cung cấp.</div>
</div></div>
<?php init_tail(); ?>
