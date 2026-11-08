<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$rows = is_array($rows ?? null) ? $rows : [];
$fmt = static function ($value) {
    return $value === null || $value === '' ? 'Chưa có dữ liệu' : html_escape((string) $value);
};
$quota = static function ($usage, $key) {
    foreach ((array) ($usage['rows'] ?? []) as $row) {
        if (($row['metric_key'] ?? '') === $key) {
            $limit = (int) ($row['limit'] ?? 0);
            return (int) ($row['used'] ?? 0) . ' / ' . ($limit > 0 ? $limit : 'Không giới hạn');
        }
    }
    return 'Chưa có dữ liệu';
};
?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-8">
        <h4 class="tw-mb-1">Quản lý DMS theo tenant</h4>
        <p class="text-muted">Landlord control center cho DMS add-on: gói, trạng thái subscription, quota, billing, workspace và sức khỏe đồng bộ.</p>
      </div>
      <div class="col-md-4 text-right">
        <a href="<?php echo admin_url('kt_integration_hub/dms_landlord_dashboard'); ?>" class="btn btn-default">Tổng quan DMS</a>
        <a href="<?php echo admin_url('kt_saas/dms_packages'); ?>" class="btn btn-default">Gói DMS</a>
      </div>
    </div>
    <div class="panel_s">
      <div class="panel-body">
        <div class="table-responsive">
          <table class="table table-striped">
            <thead>
              <tr>
                <th>Tenant</th>
                <th>Workspace</th>
                <th>Gói DMS</th>
                <th>Trạng thái</th>
                <th>Quota</th>
                <th>Live Tracking</th>
                <th>Thanh toán gần nhất</th>
                <th class="text-right">Hành động</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row) {
                  $tenant = (array) ($row['tenant'] ?? []);
                  $package = (array) ($row['package'] ?? []);
                  $addon = (array) ($row['addon'] ?? []);
                  $usage = (array) ($row['usage'] ?? []);
                  $payment = (array) ($row['last_payment'] ?? []);
              ?>
                <tr>
                  <td>
                    <strong><?php echo $fmt($tenant['company_name'] ?? ''); ?></strong><br>
                    <span class="text-muted"><?php echo $fmt($tenant['tenant_code'] ?? ''); ?></span>
                  </td>
                  <td>
                    <div><?php echo $fmt($tenant['subdomain'] ?? ''); ?>.khachtot.test</div>
                    <small class="text-muted"><?php echo $fmt($tenant['dms_workspace_host'] ?? ''); ?></small>
                  </td>
                  <td>
                    <strong><?php echo $fmt($package['name'] ?? ($row['package_code'] ?? 'Chưa mua')); ?></strong><br>
                    <span class="text-muted"><?php echo $fmt($addon['package_code'] ?? ''); ?></span>
                  </td>
                  <td>
                    <span class="label label-<?php echo !empty($row['dms_enabled']) ? 'success' : 'default'; ?>"><?php echo strtoupper(html_escape((string) ($row['status'] ?? 'none'))); ?></span>
                  </td>
                  <td>
                    <div>Users: <?php echo html_escape($quota($usage, 'dms_user')); ?></div>
                    <div>Stores: <?php echo html_escape($quota($usage, 'dms_store')); ?></div>
                    <div>Products: <?php echo html_escape($quota($usage, 'dms_product')); ?></div>
                    <div>Check-ins: <?php echo html_escape($quota($usage, 'dms_checkin')); ?></div>
                    <div>Photos: <?php echo html_escape($quota($usage, 'dms_photo')); ?></div>
                    <div>Exports: <?php echo html_escape($quota($usage, 'dms_export')); ?></div>
                  </td>
                  <td><?php echo !empty($package['live_tracking_enabled']) ? '<span class="label label-success">Bật</span>' : '<span class="label label-default">Tắt</span>'; ?></td>
                  <td>
                    <?php if (!empty($payment)) { ?>
                      <?php echo $fmt($payment['status'] ?? ''); ?> - <?php echo number_format((float) ($payment['amount'] ?? 0), 0, ',', '.'); ?> <?php echo $fmt($payment['currency'] ?? 'VND'); ?>
                    <?php } else { ?>
                      <span class="text-muted">Chưa có dữ liệu thanh toán DMS</span>
                    <?php } ?>
                  </td>
                  <td class="text-right">
                    <a href="<?php echo admin_url('kt_integration_hub/dms_landlord_tenant/' . (int) ($tenant['id'] ?? 0)); ?>" class="btn btn-primary btn-sm">Xem chi tiết</a>
                    <?php if (!empty($row['open_crm_url'])) { ?><a href="<?php echo html_escape((string) $row['open_crm_url']); ?>" class="btn btn-default btn-sm">CRM</a><?php } ?>
                    <?php if (!empty($row['open_dms_url'])) { ?><a href="<?php echo html_escape((string) $row['open_dms_url']); ?>" class="btn btn-default btn-sm">DMS</a><?php } ?>
                  </td>
                </tr>
              <?php } ?>
              <?php if (empty($rows)) { ?>
                <tr><td colspan="8" class="text-center text-muted">Chưa có tenant nào trong hệ thống.</td></tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
