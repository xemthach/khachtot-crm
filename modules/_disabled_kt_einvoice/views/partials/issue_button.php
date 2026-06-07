<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php
// Lấy record hiện tại cho invoice này
$CI  = &get_instance();
$tenantId = kt_saas_current_tenant_id();
$env = 'production'; // hoặc từ settings

if (!isset($CI->Kt_einvoice_model)) {
    $CI->load->model('kt_einvoice/Kt_einvoice_model');
}
$record = $CI->Kt_einvoice_model->getRecordByPerfexInvoice($tenantId, (int) $invoice->id, $env);
$status = $record ? $record['status'] : null;

// Kiểm tra permission
$canCreate = staff_can('create', 'kt_einvoice');
$canIssue  = staff_can('issue', 'kt_einvoice');
$canDownload = staff_can('download', 'kt_einvoice');
$canCancel = staff_can('cancel', 'kt_einvoice');
?>

<li class="dropdown" id="kt-einvoice-invoice-menu">
    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button">
        <i class="fa-regular fa-file-invoice"></i>
        HĐĐT
        <?php if ($record): ?>
            <?php echo kt_einvoice_status_badge($status); ?>
        <?php endif; ?>
        <span class="caret"></span>
    </a>
    <ul class="dropdown-menu dropdown-menu-right" style="min-width: 220px;">

        <?php if (!$record || in_array($status, ['failed_create', 'deleted'])): ?>
            <!-- Chưa có hoặc thất bại → Tạo mới -->
            <?php if ($canCreate): ?>
            <li>
                <a href="#" class="kt-einvoice-action" data-action="create_draft"
                   data-invoice-id="<?php echo (int)$invoice->id; ?>"
                   data-record-id="">
                    <i class="fa fa-file-plus text-primary"></i>
                    <?php echo _l('kt_einvoice_btn_create_draft'); ?>
                </a>
            </li>
            <?php endif; ?>

        <?php elseif ($status === 'draft'): ?>
            <!-- Nháp → Phát hành -->
            <?php if ($canIssue): ?>
            <li>
                <a href="#" class="kt-einvoice-action" data-action="issue"
                   data-record-id="<?php echo (int)$record['id']; ?>">
                    <i class="fa fa-paper-plane text-success"></i>
                    <?php echo _l('kt_einvoice_btn_issue'); ?>
                </a>
            </li>
            <?php endif; ?>
            <li>
                <a href="#" class="kt-einvoice-action" data-action="delete_draft"
                   data-record-id="<?php echo (int)$record['id']; ?>">
                    <i class="fa fa-trash text-danger"></i>
                    <?php echo _l('kt_einvoice_btn_delete'); ?>
                </a>
            </li>

        <?php elseif (in_array($status, ['pending_create', 'pending_issue'])): ?>
            <!-- Đang xử lý -->
            <li class="disabled">
                <a href="#"><i class="fa fa-spinner fa-spin"></i> Đang xử lý...</a>
            </li>
            <li>
                <a href="#" class="kt-einvoice-action" data-action="check_status"
                   data-record-id="<?php echo (int)$record['id']; ?>">
                    <i class="fa fa-refresh"></i> <?php echo _l('kt_einvoice_btn_check_status'); ?>
                </a>
            </li>

        <?php elseif ($status === 'issued'): ?>
            <!-- Đã phát hành -->
            <li class="dropdown-header">
                <i class="fa fa-check-circle text-success"></i>
                Số HĐ: <strong><?php echo htmlspecialchars($record['invoice_number'] ?? '---'); ?></strong>
            </li>
            <li class="divider"></li>
            <?php if ($canDownload): ?>
            <li>
                <a href="<?php echo admin_url('kt_einvoice/download/' . $record['id'] . '/pdf'); ?>" target="_blank">
                    <i class="fa fa-file-pdf-o text-danger"></i>
                    <?php echo _l('kt_einvoice_btn_download_pdf'); ?>
                </a>
            </li>
            <?php if (kt_einvoice_tenant_has_feature($tenantId, KT_EINVOICE_FEATURE_DOWNLOAD_XML)): ?>
            <li>
                <a href="<?php echo admin_url('kt_einvoice/download/' . $record['id'] . '/xml'); ?>" target="_blank">
                    <i class="fa fa-file-code-o text-info"></i>
                    <?php echo _l('kt_einvoice_btn_download_xml'); ?>
                </a>
            </li>
            <?php endif; ?>
            <?php endif; ?>
            <?php if ($canCancel): ?>
            <li class="divider"></li>
            <li>
                <a href="#" class="kt-einvoice-action" data-action="cancel_invoice"
                   data-record-id="<?php echo (int)$record['id']; ?>">
                    <i class="fa fa-ban text-warning"></i>
                    <?php echo _l('kt_einvoice_btn_cancel_invoice'); ?>
                </a>
            </li>
            <?php endif; ?>

        <?php elseif ($status === 'failed_issue'): ?>
            <!-- Lỗi phát hành -->
            <li class="dropdown-header text-danger">
                <i class="fa fa-exclamation-circle"></i> Lỗi phát hành
            </li>
            <?php if (!empty($record['status_message'])): ?>
            <li class="dropdown-header">
                <small class="text-muted"><?php echo htmlspecialchars(substr($record['status_message'], 0, 60)); ?></small>
            </li>
            <?php endif; ?>
            <li class="divider"></li>
            <?php if ($canIssue): ?>
            <li>
                <a href="#" class="kt-einvoice-action" data-action="issue"
                   data-record-id="<?php echo (int)$record['id']; ?>">
                    <i class="fa fa-refresh text-warning"></i>
                    <?php echo _l('kt_einvoice_btn_retry'); ?>
                </a>
            </li>
            <?php endif; ?>

        <?php elseif (in_array($status, ['cancelled', 'pending_cancel'])): ?>
            <li class="dropdown-header">
                <i class="fa fa-ban text-muted"></i> Đã hủy
            </li>
        <?php endif; ?>

        <li class="divider"></li>
        <li>
            <?php if ($record): ?>
            <a href="<?php echo admin_url('kt_einvoice/invoice_detail/' . $record['id']); ?>">
                <i class="fa fa-external-link"></i> Xem chi tiết HĐĐT
            </a>
            <?php else: ?>
            <a href="<?php echo admin_url('kt_einvoice/invoices'); ?>">
                <i class="fa fa-list"></i> Danh sách HĐĐT
            </a>
            <?php endif; ?>
        </li>
    </ul>
</li>

<script>
(function() {
    // Handler cho tất cả action buttons
    $(document).on('click', '.kt-einvoice-action', function(e) {
        e.preventDefault();
        var action   = $(this).data('action');
        var recordId = $(this).data('record-id');
        var invoiceId = $(this).data('invoice-id');

        switch(action) {
            case 'create_draft':
                _ktEinvoiceCreateDraft(invoiceId);
                break;
            case 'issue':
                if (!confirm('<?php echo _l('kt_einvoice_btn_issue_confirm'); ?>')) return;
                _ktEinvoiceAction('<?php echo admin_url('kt_einvoice/issue/'); ?>' + recordId);
                break;
            case 'delete_draft':
                if (!confirm('<?php echo _l('kt_einvoice_btn_delete_confirm'); ?>')) return;
                _ktEinvoiceAction('<?php echo admin_url('kt_einvoice/delete_draft/'); ?>' + recordId);
                break;
            case 'cancel_invoice':
                var reason = prompt('<?php echo _l('kt_einvoice_cancel_reason'); ?>:');
                if (reason === null) return;
                _ktEinvoiceAction('<?php echo admin_url('kt_einvoice/cancel_invoice/'); ?>' + recordId, {reason: reason});
                break;
            case 'check_status':
                _ktEinvoiceCheckStatus(recordId);
                break;
        }
    });

    function _ktEinvoiceCreateDraft(invoiceId) {
        _ktShowLoading();
        $.post('<?php echo admin_url('kt_einvoice/create_draft/'); ?>' + invoiceId, {}, function(resp) {
            _ktHideLoading();
            if (resp.success) {
                toastr.success(resp.message);
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                toastr.error(resp.message);
            }
        }, 'json').fail(function() {
            _ktHideLoading();
            toastr.error('Có lỗi xảy ra khi kết nối máy chủ.');
        });
    }

    function _ktEinvoiceAction(url, extra) {
        _ktShowLoading();
        $.post(url, extra || {}, function(resp) {
            _ktHideLoading();
            if (resp.success) {
                toastr.success(resp.message);
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                toastr.error(resp.message);
            }
        }, 'json').fail(function() {
            _ktHideLoading();
            toastr.error('Có lỗi xảy ra.');
        });
    }

    function _ktEinvoiceCheckStatus(recordId) {
        $.post('<?php echo admin_url('kt_einvoice/check_status/'); ?>' + recordId, {}, function(resp) {
            if (resp.success) {
                toastr.info('Trạng thái: ' + resp.data.status + (resp.data.invoice_number ? ' — Số HĐ: ' + resp.data.invoice_number : ''));
                if (['issued', 'cancelled', 'deleted'].indexOf(resp.data.status) >= 0) {
                    setTimeout(function() { location.reload(); }, 1000);
                }
            }
        }, 'json');
    }

    function _ktShowLoading() {
        $('#kt-einvoice-invoice-menu > a').prepend('<i class="fa fa-spinner fa-spin" id="kt-einvoice-spinner"></i> ');
    }
    function _ktHideLoading() {
        $('#kt-einvoice-spinner').remove();
    }
})();
</script>
