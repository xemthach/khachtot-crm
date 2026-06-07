<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$page = max(1, (int) ($page ?? 1));
$perPage = max(25, (int) ($per_page ?? 50));
$total = max(0, (int) ($total ?? 0));
$totalPages = max(1, (int) ($total_pages ?? 1));
$filters = is_array($filters ?? null) ? $filters : [];
$baseParams = array_filter([
    'event_key' => trim((string) ($filters['event_key'] ?? '')),
    'severity' => trim((string) ($filters['severity'] ?? '')),
    'tenant_id' => !empty($filters['tenant_id']) ? (int) $filters['tenant_id'] : null,
    'per_page' => $perPage,
], function ($value) {
    return $value !== null && $value !== '';
});
$pageUrl = function ($targetPage) use ($baseParams) {
    return admin_url('kt_saas/activity_logs?' . http_build_query(array_merge($baseParams, ['page' => max(1, (int) $targetPage)])));
};
?>
<div id="wrapper">
    <div class="content">
        <div class="panel_s">
            <div class="panel-body">
                <div class="tw-flex tw-items-center tw-justify-between tw-gap-4 tw-flex-wrap">
                    <h4 class="tw-mb-0"><?php echo html_escape($title); ?></h4>
                    <span class="text-muted">
                        Tổng: <?php echo number_format($total); ?> dòng
                    </span>
                </div>

                <hr>

                <form action="<?php echo admin_url('kt_saas/activity_logs'); ?>" method="get" class="row" accept-charset="utf-8">
                    <div class="col-md-3">
                        <label class="control-label">Mã sự kiện</label>
                        <input type="text" name="event_key" class="form-control" value="<?php echo html_escape($filters['event_key'] ?? ''); ?>" placeholder="tenant.purged">
                    </div>
                    <div class="col-md-2">
                        <label class="control-label">Mức độ</label>
                        <select name="severity" class="form-control">
                            <option value="">Tất cả</option>
                            <?php foreach (['info', 'success', 'warning', 'danger', 'error'] as $severity) { ?>
                                <option value="<?php echo html_escape($severity); ?>" <?php echo (($filters['severity'] ?? '') === $severity) ? 'selected' : ''; ?>>
                                    <?php echo html_escape(ucfirst($severity)); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="control-label">Tenant ID</label>
                        <input type="number" name="tenant_id" class="form-control" value="<?php echo !empty($filters['tenant_id']) ? (int) $filters['tenant_id'] : ''; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="control-label">Mỗi trang</label>
                        <select name="per_page" class="form-control">
                            <?php foreach ([25, 50, 100] as $size) { ?>
                                <option value="<?php echo (int) $size; ?>" <?php echo $perPage === $size ? 'selected' : ''; ?>><?php echo (int) $size; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="control-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">Lọc</button>
                            <a href="<?php echo admin_url('kt_saas/activity_logs'); ?>" class="btn btn-default">Đặt lại</a>
                        </div>
                    </div>
                </form>

                <hr>

                <div class="row">
                    <div class="col-md-6">
                        <?php echo form_open(admin_url('kt_saas/activity_logs_delete'), ['class' => 'form-inline']); ?>
                            <input type="hidden" name="mode" value="older_than">
                            <label class="control-label mright5">Xóa log cũ hơn</label>
                            <input type="number" name="days" class="form-control input-sm" value="30" min="1" style="width:90px;">
                            <span class="mleft5 mright5">ngày</span>
                            <input type="text" name="confirm" class="form-control input-sm" placeholder="DELETE ACTIVITY LOGS" required style="width:210px;">
                            <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Xóa các dòng nhật ký cũ theo số ngày đã chọn?');">Xóa log cũ</button>
                        <?php echo form_close(); ?>
                    </div>
                    <div class="col-md-6 text-right">
                        <?php echo form_open(admin_url('kt_saas/activity_logs_delete'), ['class' => 'form-inline']); ?>
                            <input type="hidden" name="mode" value="all">
                            <input type="hidden" name="days" value="1">
                            <input type="text" name="confirm" class="form-control input-sm" placeholder="DELETE ALL ACTIVITY LOGS" required style="width:250px;">
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Xóa toàn bộ nhật ký hoạt động? Thao tác này không thể hoàn tác.');">Xóa toàn bộ</button>
                        <?php echo form_close(); ?>
                    </div>
                </div>

                <div class="table-responsive mtop20">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th><?php echo _l('kt_saas_event_key'); ?></th>
                                <th><?php echo _l('kt_saas_severity'); ?></th>
                                <th><?php echo _l('kt_saas_tenant'); ?></th>
                                <th><?php echo _l('kt_saas_actor'); ?></th>
                                <th><?php echo _l('kt_saas_created_at'); ?></th>
                                <th>Ngữ cảnh</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log) { ?>
                                <tr>
                                    <td>#<?php echo (int) $log['id']; ?></td>
                                    <td><?php echo html_escape($log['event_key']); ?></td>
                                    <td><span class="label label-<?php echo kt_saas_status_badge_class($log['severity']); ?>"><?php echo html_escape(ucfirst($log['severity'])); ?></span></td>
                                    <td><?php echo $log['tenant_id'] ? (int) $log['tenant_id'] : '-'; ?></td>
                                    <td><?php echo html_escape($log['actor_type'] . ($log['actor_id'] ? ' #' . $log['actor_id'] : '')); ?></td>
                                    <td><?php echo _dt($log['created_at']); ?></td>
                                    <td style="min-width:360px;">
                                        <pre class="kt-saas-log-context"><?php echo html_escape($log['context_json']); ?></pre>
                                    </td>
                                </tr>
                            <?php } ?>
                            <?php if (empty($logs)) { ?><tr><td colspan="7"><?php echo _l('kt_saas_no_records'); ?></td></tr><?php } ?>
                        </tbody>
                    </table>
                </div>

                <div class="tw-flex tw-items-center tw-justify-between tw-flex-wrap">
                    <p class="text-muted">
                        Trang <?php echo (int) $page; ?> / <?php echo (int) $totalPages; ?>
                    </p>
                    <ul class="pagination pagination-sm mtop0">
                        <li class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a href="<?php echo $page <= 1 ? '#' : $pageUrl($page - 1); ?>">&laquo;</a>
                        </li>
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        for ($i = $start; $i <= $end; $i++) { ?>
                            <li class="<?php echo $i === $page ? 'active' : ''; ?>">
                                <a href="<?php echo $pageUrl($i); ?>"><?php echo (int) $i; ?></a>
                            </li>
                        <?php } ?>
                        <li class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a href="<?php echo $page >= $totalPages ? '#' : $pageUrl($page + 1); ?>">&raquo;</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
.kt-saas-log-context {
    max-height: 90px;
    overflow: auto;
    white-space: pre-wrap;
    word-break: break-word;
    margin: 0;
    font-size: 11px;
}
</style>
<?php init_tail(); ?>
