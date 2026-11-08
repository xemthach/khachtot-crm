<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$CI = &get_instance();
$orders = (array) ($orders ?? []);
$filters = (array) ($filters ?? []);
$label = static fn ($status) => $CI->Kt_saas_model->dms_order_status_label($status);
$cycleLabels = ['monthly' => 'Hàng tháng', 'quarterly' => 'Hàng quý', 'yearly' => 'Hàng năm'];
?>
<style>
  .dms-orders-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin-bottom:18px}.dms-orders-kpi{padding:16px;border:1px solid #dbe4ef;border-radius:8px;background:#fff}.dms-orders-kpi span{display:block;color:#64748b;font-size:12px}.dms-orders-kpi strong{display:block;margin-top:5px;color:#172b4d;font-size:22px}
  @media(max-width:991px){.dms-orders-summary{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:480px){.dms-orders-summary{grid-template-columns:1fr}}
</style>
<div id="wrapper"><div class="content" data-r28-landlord-order-list>
  <div class="row"><div class="col-md-8"><h4 class="tw-mb-1">Đơn kích hoạt DMS</h4><p class="text-muted">Theo dõi từ lúc doanh nghiệp chọn gói đến khi workspace và nhân sự sẵn sàng.</p></div><div class="col-md-4 text-right"><a class="btn btn-default" href="<?php echo admin_url('kt_integration_hub/dms_control_center'); ?>">Trung tâm điều khiển DMS</a></div></div>
  <?php
  $countBy = static function ($field, $values) use ($orders) {
      return count(array_filter($orders, static fn ($row) => in_array((string) ($row[$field] ?? ''), (array) $values, true)));
  };
  ?>
  <section class="dms-orders-summary">
    <div class="dms-orders-kpi"><span>Chờ thanh toán</span><strong><?php echo $countBy('payment_status', ['payment_pending', 'pending']); ?></strong></div>
    <div class="dms-orders-kpi"><span>Cần xác minh</span><strong><?php echo $countBy('verification_status', ['pending', 'submitted']); ?></strong></div>
    <div class="dms-orders-kpi"><span>Đang kích hoạt</span><strong><?php echo $countBy('status', ['subscription_active', 'provisioning_pending', 'provisioning_running']); ?></strong></div>
    <div class="dms-orders-kpi"><span>Đã sẵn sàng</span><strong><?php echo $countBy('workspace_status', ['ready']); ?></strong></div>
    <div class="dms-orders-kpi"><span>Cần xử lý</span><strong><?php echo $countBy('status', ['blocked', 'payment_failed']); ?></strong></div>
  </section>
  <div class="panel_s"><div class="panel-body">
    <form method="get" class="row">
      <div class="col-md-2"><label>Trạng thái</label><select name="status" class="form-control"><option value="">Tất cả</option><?php foreach (['payment_pending'=>'Chờ thanh toán','payment_verified'=>'Đã xác minh','provisioning_pending'=>'Chờ kích hoạt','provisioning_running'=>'Đang kích hoạt','workspace_ready'=>'Đã sẵn sàng','blocked'=>'Cần xử lý','cancelled'=>'Đã hủy'] as $value=>$text){ ?><option value="<?php echo $value; ?>" <?php echo ($filters['status']??'')===$value?'selected':''; ?>><?php echo $text; ?></option><?php } ?></select></div>
      <div class="col-md-2"><label>Gói DMS</label><select name="package_code" class="form-control"><option value="">Tất cả</option><?php foreach((array)$packages as $code=>$package){ ?><option value="<?php echo html_escape((string)$code); ?>" <?php echo ($filters['package_code']??'')===(string)$code?'selected':''; ?>><?php echo html_escape((string)($package['name']??$code)); ?></option><?php } ?></select></div>
      <div class="col-md-3"><label>Doanh nghiệp</label><select name="tenant_id" class="form-control"><option value="">Tất cả</option><?php foreach((array)$tenants as $tenant){ ?><option value="<?php echo (int)$tenant['id']; ?>" <?php echo (int)($filters['tenant_id']??0)===(int)$tenant['id']?'selected':''; ?>><?php echo html_escape((string)($tenant['company_name']??$tenant['tenant_code'])); ?></option><?php } ?></select></div>
      <div class="col-md-2 tw-pt-6"><button class="btn btn-primary" type="submit">Lọc đơn DMS</button></div>
    </form>
  </div></div>
  <div class="panel_s"><div class="panel-body"><div class="table-responsive"><table class="table table-striped table-hover"><thead><tr><th>Doanh nghiệp</th><th>Gói / chu kỳ</th><th>Số tiền</th><th>Thanh toán</th><th>Xác minh</th><th>Kích hoạt</th><th>Workspace</th><th>Cập nhật</th><th></th></tr></thead><tbody>
    <?php foreach($orders as $order){ ?><tr>
      <td><strong><?php echo html_escape((string)($order['company_name']??$order['tenant']['company_name']??'')); ?></strong><div class="text-muted small"><?php echo html_escape((string)($order['tenant_code']??$order['tenant']['tenant_code']??'')); ?></div></td>
      <td><?php echo html_escape((string)$order['package_name']); ?><div class="text-muted small"><?php echo html_escape($cycleLabels[$order['billing_cycle']??'']??'Chưa xác định'); ?></div></td>
      <td><?php echo number_format((float)$order['amount'],0,',','.'); ?> <?php echo html_escape((string)$order['currency']); ?></td>
      <td><?php echo html_escape($label($order['payment_status']??'')); ?></td><td><?php echo html_escape($label($order['verification_status']??'')); ?></td><td><?php echo html_escape($label($order['provisioning_status']??'')); ?></td><td><?php echo html_escape($label($order['workspace_status']??'')); ?></td>
      <td><?php echo html_escape(date('d/m/Y H:i',strtotime((string)$order['updated_at']))); ?></td><td><a class="btn btn-default btn-sm" href="<?php echo admin_url('kt_saas/dms_order/'.(int)$order['id']); ?>">Xem chi tiết</a></td>
    </tr><?php } ?>
    <?php if(empty($orders)){ ?><tr><td colspan="9" class="text-center text-muted">Chưa có đơn kích hoạt DMS phù hợp bộ lọc.</td></tr><?php } ?>
  </tbody></table></div></div></div>
</div></div>
<?php init_tail(); ?>
