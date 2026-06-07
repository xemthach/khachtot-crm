<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
                    <h4 class="tw-text-xl tw-font-bold tw-text-gray-800">
                        <i class="fa fa-clock-o tw-text-blue-600 tw-mr-2"></i>
                        Cron Logs eInvoice
                    </h4>
                    <a href="<?php echo admin_url('kt_einvoice/admin/overview'); ?>" class="btn btn-default btn-sm">← Tổng quan</a>
                </div>

                <!-- Table -->
                <div class="panel_s">
                    <div class="panel-body no-padding">
                        <table class="table table-hover no-margin">
                            <thead class="tw-bg-gray-50">
                                <tr>
                                    <th>ID</th>
                                    <th>Tên Cron Job</th>
                                    <th>Tenant</th>
                                    <th>Trạng thái</th>
                                    <th>Đã xử lý</th>
                                    <th>Đã cập nhật</th>
                                    <th>Số lỗi</th>
                                    <th>Thời gian chạy</th>
                                    <th>Duration</th>
                                    <th>Chi tiết</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                <tr><td colspan="10" class="tw-text-center tw-py-8 text-muted">Không có dữ liệu cron logs</td></tr>
                                <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="text-muted">#<?php echo $log['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($log['cron_name']); ?></strong></td>
                                    <td>
                                        <?php if ($log['tenant_id']): ?>
                                            <a href="<?php echo admin_url('kt_einvoice/admin/tenant_settings/' . $log['tenant_id']); ?>">
                                                T#<?php echo $log['tenant_id']; ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="label label-info">Hệ thống (All)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($log['status'] === 'success'): ?>
                                            <span class="label label-success"><i class="fa fa-check"></i> Thành công</span>
                                        <?php else: ?>
                                            <span class="label label-danger"><i class="fa fa-times"></i> Có lỗi</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo (int)$log['total_processed']; ?></td>
                                    <td><?php echo (int)$log['total_updated']; ?></td>
                                    <td>
                                        <?php if ((int)$log['total_errors'] > 0): ?>
                                            <span class="label label-danger"><?php echo $log['total_errors']; ?> lỗi</span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="text-muted"><?php echo date('d/m/Y H:i:s', strtotime($log['started_at'])); ?></span></td>
                                    <td><?php echo $log['duration_ms'] ? number_format($log['duration_ms']) . ' ms' : '—'; ?></td>
                                    <td>
                                        <button class="btn btn-default btn-xs" type="button" data-toggle="collapse" data-target="#cron-detail-<?php echo $log['id']; ?>">
                                            <i class="fa fa-eye"></i> Xem chi tiết
                                        </button>
                                    </td>
                                </tr>
                                <tr class="collapse" id="cron-detail-<?php echo $log['id']; ?>">
                                    <td colspan="10" class="tw-bg-gray-50 tw-p-4">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h5 class="tw-font-bold tw-text-sm tw-mb-2">Thông tin chi tiết phiên chạy:</h5>
                                                <pre class="tw-text-xs tw-bg-white tw-p-3 tw-border tw-rounded tw-overflow-auto" style="max-height: 350px;"><?php 
                                                    $details = json_decode($log['details_json'] ?? '', true);
                                                    echo htmlspecialchars(json_encode($details ?: $log['details_json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); 
                                                ?></pre>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total > $limit): ?>
                    <div class="panel-footer">
                        <div class="tw-flex tw-justify-between tw-items-center">
                            <span class="text-muted">Tổng: <?php echo number_format($total); ?> bản ghi</span>
                            <ul class="pagination pagination-sm tw-mb-0">
                                <?php for ($p = 1; $p <= ceil($total / $limit); $p++): ?>
                                <li class="<?php echo $p === $page ? 'active' : ''; ?>">
                                    <a href="<?php echo admin_url('kt_einvoice/admin/cron_logs?page=' . $p); ?>"><?php echo $p; ?></a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
