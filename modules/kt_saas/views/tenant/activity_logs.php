<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$logs = is_array($logs ?? null) ? $logs : [];
$filters = is_array($filters ?? null) ? $filters : [];
$eventKey = trim((string) ($filters['event_key'] ?? ''));
$severity = trim((string) ($filters['severity'] ?? ''));
$severityOptions = [
    '' => 'Tất cả mức độ',
    'info' => 'Thông tin',
    'success' => 'Thành công',
    'warning' => 'Cần chú ý',
    'danger' => 'Nghiêm trọng',
];
$actorLabels = [
    'system' => 'Hệ thống',
    'staff' => 'Nhân sự',
    'client' => 'Khách hàng',
    'tenant' => 'Doanh nghiệp',
];
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mb-2"><?php echo html_escape($title ?? _l('kt_saas_activity_logs')); ?></h4>
                <p class="text-muted">Chỉ hiển thị nhật ký hoạt động của doanh nghiệp này. Dữ liệu chỉ đọc và được lưu trong kho nhật ký tập trung.</p>
            </div>
        </div>

        <div class="panel_s">
            <div class="panel-body">
                <form action="<?php echo admin_url('kt_saas/tenant_activity_logs'); ?>" method="get" accept-charset="utf-8">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="form-group">
                                <label for="event_key" class="control-label">Tìm hoạt động</label>
                                <input type="text" class="form-control" id="event_key" name="event_key" value="<?php echo html_escape($eventKey); ?>" placeholder="Ví dụ: cài đặt, thanh toán, đăng nhập">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="severity" class="control-label">Mức độ</label>
                                <select name="severity" id="severity" class="form-control">
                                    <?php foreach ($severityOptions as $key => $label) { ?>
                                        <option value="<?php echo html_escape($key); ?>" <?php echo $severity === $key ? 'selected' : ''; ?>>
                                            <?php echo html_escape($label); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label">&nbsp;</label>
                                <div class="tw-flex tw-gap-2">
                                    <button type="submit" class="btn btn-primary btn-block">Lọc</button>
                                    <a href="<?php echo admin_url('kt_saas/tenant_activity_logs'); ?>" class="btn btn-default btn-block">Đặt lại</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>Hoạt động</th>
                                <th>Mức độ</th>
                                <th>Người thực hiện</th>
                                <th>Thời gian</th>
                                <th>Chi tiết</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log) { ?>
                                <?php
                                $eventText = trim(str_replace(['kt_saas.', '_', '.'], ['', ' ', ' '], (string) ($log['event_key'] ?? '')));
                                $actorType = (string) ($log['actor_type'] ?? 'system');
                                $context = (string) ($log['context_json'] ?? '');
                                ?>
                                <tr>
                                    <td>#<?php echo (int) ($log['id'] ?? 0); ?></td>
                                    <td><?php echo html_escape($eventText !== '' ? ucfirst($eventText) : '-'); ?></td>
                                    <td>
                                        <span class="label label-<?php echo kt_saas_status_badge_class((string) ($log['severity'] ?? 'info')); ?>">
                                            <?php echo html_escape($severityOptions[(string) ($log['severity'] ?? 'info')] ?? 'Thông tin'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo html_escape($actorLabels[$actorType] ?? 'Hệ thống'); ?></td>
                                    <td><?php echo !empty($log['created_at']) ? _dt($log['created_at']) : '-'; ?></td>
                                    <td>
                                        <?php if ($context !== '' && $context !== '{}') { ?>
                                            <details>
                                                <summary>Hiển thị chi tiết</summary>
                                                <pre class="mtop10"><?php echo html_escape($context); ?></pre>
                                            </details>
                                        <?php } else { ?>
                                            -
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                            <?php if (empty($logs)) { ?>
                                <tr>
                                    <td colspan="6"><?php echo _l('kt_saas_no_records'); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
