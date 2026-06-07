<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">

                <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
                    <h4 class="tw-text-xl tw-font-bold tw-text-gray-800">
                        <i class="fa-regular fa-file-invoice tw-text-blue-600 tw-mr-2"></i>
                        <?php echo _l('kt_einvoice_list_title'); ?>
                    </h4>
                    <?php if (staff_can('batch_issue', 'kt_einvoice')): ?>
                    <a href="<?php echo admin_url('kt_einvoice/batch_issue'); ?>" class="btn btn-primary btn-sm">
                        <i class="fa fa-paper-plane"></i> Phát hành theo lô
                    </a>
                    <?php endif; ?>
                </div>

                <!-- Filters -->
                <div class="panel_s">
                    <div class="panel-body">
                        <form method="GET" action="<?php echo admin_url('kt_einvoice/invoices'); ?>" class="form-inline">
                            <div class="form-group tw-mr-3">
                                <select name="status" class="form-control input-sm selectpicker">
                                    <option value=""><?php echo _l('kt_einvoice_filter_all'); ?></option>
                                    <option value="draft"          <?php echo ($filters['status'] === 'draft')          ? 'selected' : ''; ?>><?php echo _l('kt_einvoice_filter_draft'); ?></option>
                                    <option value="pending_create" <?php echo ($filters['status'] === 'pending_create') ? 'selected' : ''; ?>>Đang tạo</option>
                                    <option value="pending_issue"  <?php echo ($filters['status'] === 'pending_issue')  ? 'selected' : ''; ?>>Đang phát hành</option>
                                    <option value="issued"         <?php echo ($filters['status'] === 'issued')         ? 'selected' : ''; ?>><?php echo _l('kt_einvoice_filter_issued'); ?></option>
                                    <option value="failed_create"  <?php echo ($filters['status'] === 'failed_create')  ? 'selected' : ''; ?>>Lỗi tạo</option>
                                    <option value="failed_issue"   <?php echo ($filters['status'] === 'failed_issue')   ? 'selected' : ''; ?>>Lỗi phát hành</option>
                                    <option value="cancelled"      <?php echo ($filters['status'] === 'cancelled')      ? 'selected' : ''; ?>><?php echo _l('kt_einvoice_filter_cancelled'); ?></option>
                                </select>
                            </div>
                            <div class="form-group tw-mr-3">
                                <input type="text" name="search" class="form-control input-sm"
                                    placeholder="Tìm số HĐ, tên khách..." value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>">
                            </div>
                            <button type="submit" class="btn btn-default btn-sm"><i class="fa fa-search"></i> Lọc</button>
                            <a href="<?php echo admin_url('kt_einvoice/invoices'); ?>" class="btn btn-link btn-sm">Xóa lọc</a>
                        </form>
                    </div>
                </div>

                <!-- Table -->
                <div class="panel_s">
                    <div class="panel-body no-padding">
                        <table class="table table-hover no-margin">
                            <thead class="tw-bg-gray-50">
                                <tr>
                                    <th style="width:140px"><?php echo _l('kt_einvoice_col_perfex_invoice'); ?></th>
                                    <th style="width:150px"><?php echo _l('kt_einvoice_col_invoice_number'); ?></th>
                                    <th><?php echo _l('kt_einvoice_col_buyer'); ?></th>
                                    <th style="width:130px"><?php echo _l('kt_einvoice_col_amount'); ?></th>
                                    <th style="width:130px"><?php echo _l('kt_einvoice_col_status'); ?></th>
                                    <th style="width:140px"><?php echo _l('kt_einvoice_col_issued_at'); ?></th>
                                    <th style="width:100px"><?php echo _l('kt_einvoice_col_actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($list)): ?>
                                    <tr>
                                        <td colspan="7" class="tw-text-center tw-py-10 text-muted">
                                            <i class="fa fa-file-o fa-2x tw-block tw-mb-2"></i>
                                            <?php echo _l('kt_einvoice_no_invoices_yet'); ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($list as $rec): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo admin_url('invoices/list_invoices/' . $rec['perfex_invoice_id']); ?>">
                                                <?php echo htmlspecialchars($rec['perfex_invoice_number']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($rec['invoice_number'] ?? '—'); ?></td>
                                        <td>
                                            <div><?php echo htmlspecialchars($rec['buyer_name'] ?? '—'); ?></div>
                                            <?php if (!empty($rec['buyer_tax_code'])): ?>
                                                <small class="text-muted">MST: <?php echo htmlspecialchars($rec['buyer_tax_code']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="tw-font-medium"><?php echo kt_einvoice_format_currency((float)$rec['total_amount']); ?></td>
                                        <td><?php echo kt_einvoice_status_badge($rec['status']); ?></td>
                                        <td><?php echo $rec['issued_at'] ? date('d/m/Y H:i', strtotime($rec['issued_at'])) : '—'; ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="<?php echo admin_url('kt_einvoice/invoice_detail/' . $rec['id']); ?>"
                                                   class="btn btn-xs btn-default" title="Chi tiết">
                                                    <i class="fa fa-eye"></i>
                                                </a>
                                                <?php if ($rec['status'] === 'issued' && staff_can('download', 'kt_einvoice')): ?>
                                                <a href="<?php echo admin_url('kt_einvoice/download/' . $rec['id'] . '/pdf'); ?>"
                                                   class="btn btn-xs btn-danger" title="Tải PDF" target="_blank">
                                                    <i class="fa fa-file-pdf-o"></i>
                                                </a>
                                                <?php endif; ?>
                                                <?php if ($rec['status'] === 'draft' && staff_can('issue', 'kt_einvoice')): ?>
                                                <button type="button" class="btn btn-xs btn-success kt-einvoice-list-issue"
                                                        data-record-id="<?php echo $rec['id']; ?>" title="Phát hành">
                                                    <i class="fa fa-paper-plane"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total > $limit): ?>
                    <div class="panel-footer">
                        <div class="tw-flex tw-items-center tw-justify-between">
                            <span class="text-muted">Hiển thị <?php echo min($offset + $limit, $total); ?>/<?php echo $total; ?> kết quả</span>
                            <ul class="pagination pagination-sm tw-mb-0">
                                <?php
                                $totalPages  = ceil($total / $limit);
                                $currentPage = $page;
                                for ($p = 1; $p <= $totalPages; $p++):
                                    $q = http_build_query(array_merge($filters, ['page' => $p]));
                                ?>
                                <li class="<?php echo $p === $currentPage ? 'active' : ''; ?>">
                                    <a href="<?php echo admin_url('kt_einvoice/invoices?' . $q); ?>"><?php echo $p; ?></a>
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

<script>
$(function() {
    // Quick issue from list
    $('.kt-einvoice-list-issue').on('click', function() {
        if (!confirm('<?php echo _l('kt_einvoice_btn_issue_confirm'); ?>')) return;
        var recordId = $(this).data('record-id');
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        $.post('<?php echo admin_url('kt_einvoice/issue/'); ?>' + recordId, {}, function(resp) {
            if (resp.success) {
                toastr.success(resp.message);
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                toastr.error(resp.message);
                $btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i>');
            }
        }, 'json');
    });
});
</script>
<?php init_tail(); ?>
