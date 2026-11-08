<?php defined('BASEPATH') or exit('Không direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$tenantSettings = is_array($tenant_settings ?? null) ? $tenant_settings : [];
$actions = is_array($actions ?? null) ? $actions : [];
$groups = is_array($settings_groups ?? null) ? $settings_groups : [];
$emptyState = is_array($empty_state ?? null) ? $empty_state : [];
$readiness = is_array($readiness ?? null) ? $readiness : [];
$openDmsExperience = is_array($open_dms_experience ?? null) ? $open_dms_experience : [];
$onboarding = is_array($onboarding ?? null) ? $onboarding : [];
$showOnboarding = !empty($onboarding) && empty($onboarding['hide']);
?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-8">
        <h4 class="tw-mb-1"><?php echo e($page_heading ?? ($title ?? 'Thiết lập vận hành DMS')); ?></h4>
        <p class="text-muted">Màn hình tương thích này hiển thị readiness vận hành và thiết lập có nguồn dữ liệu rõ ràng. Thiết lập DMS là màn hình tenant-facing chính.</p>
      </div>
      <div class="col-md-4 text-right">
        <?php if (($actions['open_dms_mode'] ?? '') === 'disabled') { ?>
          <span class="btn btn-default disabled">Chưa thể mở DMS</span>
        <?php } elseif (!empty($actions['open_dms_url'])) { ?>
          <a href="<?php echo e($actions['open_dms_url']); ?>" class="btn btn-primary">Mở DMS</a>
        <?php } ?>
        <?php if (!empty($actions['provisioning_url'])) { ?>
          <a href="<?php echo e($actions['setup_center_url'] ?? $actions['provisioning_url']); ?>" class="btn btn-default">Mở Thiết lập DMS</a>
        <?php } ?>
        <?php if (!empty($actions['licensing_url'])) { ?>
          <a href="<?php echo e($actions['licensing_url']); ?>" class="btn btn-default" target="_blank" rel="noopener noreferrer">Xem nguồn licensing</a>
        <?php } ?>
      </div>
    </div>

    <?php if ($showOnboarding) { ?>
      <div class="panel_s">
        <div class="panel-body">
          <div class="row">
            <div class="col-md-8">
              <h5 class="tw-mt-0"><?php echo e((string) ($onboarding['title'] ?? 'Bắt đầu với DMS')); ?></h5>
              <p class="text-muted tw-mb-2"><?php echo e((string) ($onboarding['summary'] ?? '')); ?></p>
              <p class="text-muted tw-mb-0">Tiến độ hiện tại: <strong><?php echo e((string) ($onboarding['progress_label'] ?? '0/5')); ?></strong>. Việc tiếp theo: <?php echo e((string) ($onboarding['next_action'] ?? 'Xem onboarding')); ?>.</p>
            </div>
            <div class="col-md-4 text-right">
              <?php if (!empty($actions['setup_wizard_url'])) { ?>
                <a href="<?php echo e($actions['setup_wizard_url']); ?>" class="btn btn-primary">Mở Thiết lập DMS</a>
              <?php } ?>
            </div>
          </div>
        </div>
      </div>
    <?php } ?>

    <?php if (empty($tenantSettings)) { ?>
      <div class="panel_s">
        <div class="panel-body">
          <div class="alert alert-info tw-mb-0">
            <h5 class="tw-mt-0 tw-mb-2"><?php echo e((string) ($emptyState['title'] ?? 'Chưa có payload entitlement DMS.')); ?></h5>
            <p class="tw-mb-2"><?php echo e((string) ($emptyState['reason'] ?? '')); ?></p>
            <p class="tw-mb-0"><?php echo e((string) ($emptyState['next'] ?? '')); ?></p>
          </div>
        </div>
      </div>
    <?php } else { ?>
      <div class="row">
        <div class="col-md-4">
          <div class="panel_s">
            <div class="panel-heading">Trạng thái sẵn sàng DMS</div>
            <div class="panel-body">
              <div class="text-uppercase text-muted small">Trạng thái hiện tại</div>
              <div class="tw-text-2xl tw-font-semibold tw-mt-2"><?php echo e($readiness['label'] ?? 'Chưa xác định'); ?></div>
              <p class="text-muted tw-mt-2"><?php echo e($readiness['summary'] ?? 'Chưa có tóm tắt readiness.'); ?></p>
              <table class="table table-striped">
                <tbody>
                  <tr><th>Licensing runtime</th><td><?php echo !empty($readiness['dms_enabled']) ? 'Đang bật' : 'Đang chặn'; ?></td></tr>
                  <tr><th>Kết nối sẵn sàng</th><td><?php echo !empty($readiness['connection_ready']) ? 'Có' : 'Không'; ?></td></tr>
                  <tr><th>User đã provisioning</th><td><?php echo e((string) ($readiness['provisioned_users'] ?? 0)); ?></td></tr>
                  <tr><th>Check-in đầu tiên</th><td><?php echo !empty($readiness['first_checkin_exists']) ? 'Có' : 'Không'; ?></td></tr>
                  <tr><th>Visit đầu tiên</th><td><?php echo !empty($readiness['first_visit_exists']) ? 'Có' : 'Không'; ?></td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="panel_s">
            <div class="panel-heading">Truy cập vận hành</div>
            <div class="panel-body">
              <p><strong>Chế độ:</strong> <?php echo e($openDmsExperience['mode'] ?? 'Chưa sẵn sàng'); ?></p>
              <p class="text-muted"><?php echo e($openDmsExperience['summary'] ?? ''); ?></p>
              <p class="text-muted"><?php echo e($openDmsExperience['fallback'] ?? ''); ?></p>
              <?php if (!empty($openDmsExperience['entry_url'])) { ?>
                <a href="<?php echo e($openDmsExperience['entry_url']); ?>" class="btn btn-primary">Mở DMS</a>
              <?php } ?>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="panel_s">
            <div class="panel-heading">Hướng dẫn thao tác đầu tiên</div>
            <div class="panel-body">
              <?php foreach ((array) ($readiness['steps'] ?? []) as $step) { ?>
                <div class="tw-mb-3">
                  <div>
                    <strong><?php echo e($step['label'] ?? 'Step'); ?></strong>
                    <span class="label label-<?php echo !empty($step['done']) ? 'success' : 'default'; ?>">
                      <?php echo !empty($step['done']) ? 'Xong' : 'Đang chờ'; ?>
                    </span>
                  </div>
                  <div class="text-muted small"><?php echo e($step['detail'] ?? ''); ?></div>
                </div>
              <?php } ?>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-4">
          <div class="panel_s">
            <div class="panel-heading">Trạng thái runtime</div>
            <div class="panel-body">
              <table class="table table-striped">
                <tbody>
                  <?php foreach (($groups['app_status'] ?? []) as $item) { ?>
                    <tr>
                      <th><?php echo e($item['label'] ?? ''); ?></th>
                      <td><?php echo e($item['value'] ?? '-'); ?></td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="panel_s">
            <div class="panel-heading">Đang bật Capabilities</div>
            <div class="panel-body">
              <table class="table table-striped">
                <tbody>
                  <?php foreach (($groups['enabled_capabilities'] ?? []) as $item) { ?>
                    <tr>
                      <th><?php echo e($item['label'] ?? ''); ?></th>
                      <td>
                        <span class="label label-<?php echo ($item['value'] ?? '') === 'Đang bật' ? 'success' : 'default'; ?>">
                          <?php echo e($item['value'] ?? '-'); ?>
                        </span>
                      </td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="panel_s">
            <div class="panel-heading">Thiết lập vận hành DMS</div>
            <div class="panel-body">
              <table class="table table-striped">
                <tbody>
                  <?php foreach (($groups['operational_settings'] ?? []) as $item) { ?>
                    <tr>
                      <th><?php echo e($item['label'] ?? ''); ?></th>
                      <td>
                        <div><?php echo e($item['value'] ?? 'Khôngt configured in DMS source'); ?></div>
                        <?php if (!empty($item['detail'])) { ?>
                          <div class="text-muted small"><?php echo e($item['detail']); ?></div>
                        <?php } ?>
                      </td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    <?php } ?>
  </div>
</div>
<?php init_tail(); ?>
