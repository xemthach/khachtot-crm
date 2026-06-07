<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
                    <h4 class="tw-text-xl tw-font-bold tw-text-gray-800">
                        <i class="fa fa-code tw-text-blue-600 tw-mr-2"></i>
                        API Logs eInvoice
                    </h4>
                    <a href="<?php echo admin_url('kt_einvoice/admin/overview'); ?>" class="btn btn-default btn-sm">← Tổng quan</a>
                </div>

                <!-- Filters -->
                <div class="panel_s">
                    <div class="panel-body">
                        <form method="GET" class="form-inline">
                            <div class="form-group tw-mr-2">
                                <select name="tenant_id" class="form-control input-sm">
                                    <option value="">-- Tất cả tenant --</option>
                                    <?php foreach ($tenants as $t): ?>
                                    <option value="<?php echo $t['tenant_id']; ?>" <?php echo $tenant_id == $t['tenant_id'] ? 'selected' : ''; ?>>
                                        Tenant #<?php echo $t['tenant_id']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-default btn-sm"><i class="fa fa-search"></i> Lọc</button>
                            <a href="<?php echo admin_url('kt_einvoice/admin/api_logs'); ?>" class="btn btn-link btn-sm">Xóa lọc</a>
                        </form>
                    </div>
                </div>

                <!-- Table -->
                <div class="panel_s">
                    <div class="panel-body no-padding">
                        <table class="table table-hover no-margin">
                            <thead class="tw-bg-gray-50">
                                <tr>
                                    <th>ID</th>
                                    <th>Tenant</th>
                                    <th>Hành động</th>
                                    <th>HTTP Method & URL</th>
                                    <th>HTTP Code</th>
                                    <th>Thời gian xử lý</th>
                                    <th>Kết quả</th>
                                    <th>Thời gian</th>
                                    <th>Chi tiết</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                <tr><td colspan="9" class="tw-text-center tw-py-8 text-muted">Không có dữ liệu logs</td></tr>
                                <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="text-muted">#<?php echo $log['id']; ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('kt_einvoice/admin/tenant_settings/' . $log['tenant_id']); ?>">
                                            T#<?php echo $log['tenant_id']; ?>
                                        </a>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($log['action']); ?></strong></td>
                                    <td>
                                        <span class="label label-default"><?php echo htmlspecialchars($log['method']); ?></span>
                                        <code class="tw-text-xs"><?php echo htmlspecialchars($log['endpoint']); ?></code>
                                    </td>
                                    <td>
                                        <?php if ($log['response_code']): ?>
                                            <span class="label label-<?php echo ($log['response_code'] >= 200 && $log['response_code'] < 300) ? 'success' : 'danger'; ?>">
                                                <?php echo $log['response_code']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $log['latency_ms'] ? $log['latency_ms'] . ' ms' : '—'; ?></td>
                                    <td>
                                        <?php if ($log['success']): ?>
                                            <span class="label label-success"><i class="fa fa-check"></i> Thành công</span>
                                        <?php else: ?>
                                            <span class="label label-danger" data-toggle="tooltip" title="<?php echo htmlspecialchars($log['error_message'] ?? ''); ?>">
                                                <i class="fa fa-times"></i> Thất bại
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="text-muted"><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></span></td>
                                    <td>
                                        <button class="btn btn-default btn-xs" type="button" data-toggle="collapse" data-target="#log-detail-<?php echo $log['id']; ?>">
                                            <i class="fa fa-eye"></i> Xem payload
                                        </button>
                                    </td>
                                </tr>
                                <tr class="collapse" id="log-detail-<?php echo $log['id']; ?>">
                                    <td colspan="9" class="tw-bg-gray-50 tw-p-4">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h5 class="tw-font-bold tw-text-sm tw-mb-2">Request Payload:</h5>
                                                <pre class="tw-text-xs tw-bg-white tw-p-3 tw-border tw-rounded tw-overflow-auto" style="max-height: 250px;"><?php 
                                                    $req = json_decode($log['request_json'] ?? '', true);
                                                    echo htmlspecialchars(json_encode($req ?: $log['request_json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); 
                                                ?></pre>
                                            </div>
                                            <div class="col-md-6">
                                                <h5 class="tw-font-bold tw-text-sm tw-mb-2">Response / Lỗi:</h5>
                                                <?php if (!$log['success'] && $log['error_code']): ?>
                                                    <div class="alert alert-danger tw-mb-2 tw-py-2 tw-px-3 tw-text-sm">
                                                        <strong>Mã lỗi:</strong> <?php echo htmlspecialchars($log['error_code']); ?><br>
                                                        <strong>Thông điệp:</strong> <?php echo htmlspecialchars($log['error_message'] ?? ''); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <pre class="tw-text-xs tw-bg-white tw-p-3 tw-border tw-rounded tw-overflow-auto" style="max-height: 250px;"><?php 
                                                    $resp = json_decode($log['response_json'] ?? '', true);
                                                    echo htmlspecialchars(json_encode($resp ?: $log['response_json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); 
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
                                    <a href="<?php echo admin_url('kt_einvoice/admin/api_logs?tenant_id=' . $tenant_id . '&page=' . $p); ?>"><?php echo $p; ?></a>
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
