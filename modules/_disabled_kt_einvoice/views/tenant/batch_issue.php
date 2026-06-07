<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-10 col-md-offset-1">

                <div class="tw-mb-6">
                    <h4 class="tw-text-xl tw-font-bold tw-text-gray-800">
                        <i class="fa fa-paper-plane tw-text-blue-600 tw-mr-2"></i>
                        <?php echo _l('kt_einvoice_batch_title'); ?>
                    </h4>
                    <p class="text-muted">Chọn các invoice cần phát hành hóa đơn điện tử theo lô (tối đa <?php echo $max_batch_size; ?> invoice/lô)</p>
                </div>

                <?php if (empty($invoices)): ?>
                    <div class="panel_s">
                        <div class="panel-body tw-text-center tw-py-10 text-muted">
                            <i class="fa fa-check-circle fa-3x text-success tw-mb-3"></i>
                            <p><?php echo _l('kt_einvoice_batch_no_eligible'); ?></p>
                            <a href="<?php echo admin_url('kt_einvoice/invoices'); ?>" class="btn btn-default">Xem danh sách HĐĐT</a>
                        </div>
                    </div>
                <?php else: ?>

                <!-- Batch Session Progress (hidden by default) -->
                <div id="kt-batch-progress" style="display:none;" class="panel_s tw-mb-4">
                    <div class="panel-body">
                        <h5 class="tw-font-semibold tw-mb-3"><i class="fa fa-spinner fa-spin text-info"></i> Đang phát hành theo lô...</h5>
                        <div class="progress active">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" id="kt-batch-progress-bar" style="width: 0%"></div>
                        </div>
                        <div class="tw-flex tw-justify-between tw-mt-2">
                            <span id="kt-batch-progress-text" class="text-muted">Đang khởi tạo...</span>
                            <span id="kt-batch-count" class="text-muted"></span>
                        </div>
                    </div>
                </div>

                <!-- Batch Result (hidden by default) -->
                <div id="kt-batch-result" style="display:none;" class="alert"></div>

                <div id="kt-batch-form-section">
                    <!-- Select All + Counter -->
                    <div class="panel_s">
                        <div class="panel-body">
                            <div class="tw-flex tw-items-center tw-justify-between tw-mb-3">
                                <div>
                                    <label class="tw-font-medium">
                                        <input type="checkbox" id="kt-select-all" class="tw-mr-2">
                                        Chọn tất cả
                                    </label>
                                    <span id="kt-selected-count" class="label label-info tw-ml-3" style="display:none;">0 đã chọn</span>
                                </div>
                                <div>
                                    <input type="text" id="kt-batch-search" class="form-control input-sm" placeholder="Tìm invoice..." style="width:200px;">
                                </div>
                            </div>

                            <table class="table table-bordered table-hover table-sm" id="kt-batch-table">
                                <thead class="tw-bg-gray-50">
                                    <tr>
                                        <th style="width:40px;"></th>
                                        <th>Số Invoice</th>
                                        <th>Khách hàng</th>
                                        <th style="width:140px;">Ngày</th>
                                        <th style="width:130px;">Tổng tiền</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoices as $inv): ?>
                                    <tr class="kt-batch-row">
                                        <td class="tw-text-center">
                                            <input type="checkbox" class="kt-batch-checkbox" value="<?php echo (int)$inv['id']; ?>">
                                        </td>
                                        <td>
                                            <a href="<?php echo admin_url('invoices/list_invoices/' . $inv['id']); ?>" target="_blank">
                                                <?php echo htmlspecialchars($inv['number'] ?? '#' . $inv['id']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($inv['company'] ?: trim($inv['firstname'] . ' ' . $inv['lastname'])); ?></td>
                                        <td><?php echo $inv['date'] ? date('d/m/Y', strtotime($inv['date'])) : '—'; ?></td>
                                        <td><?php echo kt_einvoice_format_currency((float)$inv['total']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="tw-flex tw-justify-between tw-items-center">
                        <a href="<?php echo admin_url('kt_einvoice/dashboard'); ?>" class="btn btn-default">← Quay lại</a>
                        <button type="button" id="kt-btn-batch-issue" class="btn btn-primary btn-lg" disabled>
                            <i class="fa fa-paper-plane"></i> Phát Hành Theo Lô (<span id="kt-btn-count">0</span>)
                        </button>
                    </div>
                </div>

                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    var maxBatch = <?php echo (int)$max_batch_size; ?>;
    var selectedIds = [];

    // Select all toggle
    $('#kt-select-all').on('change', function() {
        var checked = $(this).prop('checked');
        $('.kt-batch-checkbox').prop('checked', checked);
        _updateSelected();
    });

    // Individual checkbox
    $(document).on('change', '.kt-batch-checkbox', function() {
        _updateSelected();
    });

    // Row click
    $(document).on('click', '.kt-batch-row td:not(:first-child)', function() {
        var $cb = $(this).closest('tr').find('.kt-batch-checkbox');
        $cb.prop('checked', !$cb.prop('checked'));
        _updateSelected();
    });

    // Search
    $('#kt-batch-search').on('input', function() {
        var term = $(this).val().toLowerCase();
        $('.kt-batch-row').each(function() {
            var text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(term) >= 0);
        });
    });

    // Submit batch
    $('#kt-btn-batch-issue').on('click', function() {
        if (selectedIds.length === 0) return;
        if (selectedIds.length > maxBatch) {
            toastr.error('Tối đa ' + maxBatch + ' invoice mỗi lô.');
            return;
        }

        if (!confirm('Xác nhận phát hành ' + selectedIds.length + ' hóa đơn điện tử theo lô?')) return;

        $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Đang xử lý...');
        $('#kt-batch-form-section').hide();
        $('#kt-batch-progress').show();
        $('#kt-batch-progress-text').text('Đang thêm vào hàng chờ...');

        $.post('<?php echo admin_url('kt_einvoice/batch_issue'); ?>', {
            invoice_ids: selectedIds
        }, function(resp) {
            if (resp.success) {
                toastr.success(resp.message);
                _startPolling(resp.session_code);
            } else {
                toastr.error(resp.message);
                $('#kt-batch-progress').hide();
                $('#kt-batch-form-section').show();
                $('#kt-btn-batch-issue').prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Phát Hành Theo Lô (<span id="kt-btn-count">' + selectedIds.length + '</span>)');
            }
        }, 'json');
    });

    function _startPolling(sessionCode) {
        var total = selectedIds.length;
        var pollInterval = setInterval(function() {
            $.get('<?php echo admin_url('kt_einvoice/batch_status/'); ?>' + sessionCode, function(resp) {
                if (!resp.success || !resp.data) return;
                var s = resp.data;
                var done = parseInt(s.success_count) + parseInt(s.failed_count);
                var pct  = total > 0 ? Math.round(done / total * 100) : 0;

                $('#kt-batch-progress-bar').css('width', pct + '%').attr('aria-valuenow', pct);
                $('#kt-batch-progress-text').text('Đã xử lý ' + done + '/' + total + ' hóa đơn');
                $('#kt-batch-count').text('✓ ' + s.success_count + ' thành công, ✗ ' + s.failed_count + ' thất bại');

                if (s.status === 'completed' || s.status === 'failed') {
                    clearInterval(pollInterval);
                    $('#kt-batch-progress').hide();
                    var msg = 'Hoàn thành: ' + s.success_count + '/' + total + ' thành công.';
                    $('#kt-batch-result').show()
                        .removeClass('alert-success alert-warning alert-danger')
                        .addClass(s.failed_count > 0 ? 'alert-warning' : 'alert-success')
                        .html('<i class="fa fa-check-circle"></i> ' + msg +
                              ' <a href="<?php echo admin_url('kt_einvoice/invoices'); ?>">Xem danh sách</a>');
                }
            }, 'json');
        }, 3000);
    }

    function _updateSelected() {
        selectedIds = [];
        $('.kt-batch-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });

        var count = selectedIds.length;
        var $btn = $('#kt-btn-batch-issue');
        var $counter = $('#kt-selected-count');

        $btn.prop('disabled', count === 0);
        $btn.find('#kt-btn-count').text(count);

        if (count > 0) {
            $counter.show().text(count + ' đã chọn' + (count > maxBatch ? ' (vượt giới hạn ' + maxBatch + ')' : ''));
            $counter.toggleClass('label-info label-danger', count <= maxBatch, count > maxBatch);
        } else {
            $counter.hide();
        }

        $btn.toggleClass('btn-danger', count > maxBatch);
        $btn.toggleClass('btn-primary', count <= maxBatch);
    }
});
</script>
<?php init_tail(); ?>
