<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
                    <h4 class="tw-text-xl tw-font-bold tw-text-gray-800">
                        <i class="fa fa-list-alt tw-text-blue-600 tw-mr-2"></i>
                        Tất Cả Hóa Đơn Điện Tử
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
                                    <option value="<?php echo $t['tenant_id']; ?>" <?php echo $filters['tenant_id'] == $t['tenant_id'] ? 'selected' : ''; ?>>
                                        Tenant #<?php echo $t['tenant_id']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group tw-mr-2">
                                <select name="status" class="form-control input-sm">
                                    <option value="">-- Tất cả trạng thái --</option>
                                    <option value="issued"        <?php echo $filters['status'] === 'issued'        ? 'selected' : ''; ?>>Đã phát hành</option>
                                    <option value="draft"         <?php echo $filters['status'] === 'draft'         ? 'selected' : ''; ?>>Nháp</option>
                                    <option value="pending_issue" <?php echo $filters['status'] === 'pending_issue' ? 'selected' : ''; ?>>Đang phát hành</option>
                                    <option value="failed_issue"  <?php echo $filters['status'] === 'failed_issue'  ? 'selected' : ''; ?>>Lỗi phát hành</option>
                                    <option value="cancelled"     <?php echo $filters['status'] === 'cancelled'     ? 'selected' : ''; ?>>Đã hủy</option>
                                </select>
                            </div>
                            <div class="form-group tw-mr-2">
                                <select name="environment" class="form-control input-sm">
                                    <option value="production" <?php echo $filters['environment'] === 'production' ? 'selected' : ''; ?>>Production</option>
                                    <option value="sandbox"    <?php echo $filters['environment'] === 'sandbox'    ? 'selected' : ''; ?>>Sandbox</option>
                                </select>
                            </div>
                            <div class="form-group tw-mr-2">
                                <input type="date" name="date_from" class="form-control input-sm" value="<?php echo $filters['date_from'] ?? ''; ?>" placeholder="Từ ngày">
                            </div>
                            <div class="form-group tw-mr-2">
                                <input type="date" name="date_to" class="form-control input-sm" value="<?php echo $filters['date_to'] ?? ''; ?>" placeholder="Đến ngày">
                            </div>
                            <button type="submit" class="btn btn-default btn-sm"><i class="fa fa-search"></i> Lọc</button>
                            <a href="<?php echo admin_url('kt_einvoice/admin/all_records'); ?>" class="btn btn-link btn-sm">Xóa lọc</a>
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
                                    <th>Invoice CRM</th>
                                    <th>Số HĐĐT</th>
                                    <th>Người mua</th>
                                    <th>Tổng tiền</th>
                                    <th>Trạng thái</th>
                                    <th>Phát hành lúc</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($list)): ?>
                                <tr><td colspan="8" class="tw-text-center tw-py-8 text-muted">Không có dữ liệu</td></tr>
                                <?php else: ?>
                                <?php foreach ($list as $rec): ?>
                                <tr>
                                    <td class="text-muted">#<?php echo $rec['id']; ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('kt_einvoice/admin/tenant_settings/' . $rec['tenant_id']); ?>">
                                            T#<?php echo $rec['tenant_id']; ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($rec['perfex_invoice_number']); ?></td>
                                    <td><?php echo htmlspecialchars($rec['invoice_number'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($rec['buyer_name'] ?? '—'); ?></td>
                                    <td><?php echo kt_einvoice_format_currency((float)$rec['total_amount']); ?></td>
                                    <td><?php echo kt_einvoice_status_badge($rec['status']); ?></td>
                                    <td><?php echo $rec['issued_at'] ? date('d/m/Y H:i', strtotime($rec['issued_at'])) : '—'; ?></td>
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
                                <?php for ($p = 1; $p <= ceil($total / $limit); $p++):
                                    $q = http_build_query(array_merge($filters, ['page' => $p]));
                                ?>
                                <li class="<?php echo $p === $page ? 'active' : ''; ?>">
                                    <a href="<?php echo admin_url('kt_einvoice/admin/all_records?' . $q); ?>"><?php echo $p; ?></a>
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
