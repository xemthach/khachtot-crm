<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-10 col-md-offset-1">

                <!-- Breadcrumb -->
                <ol class="breadcrumb tw-mb-4">
                    <li><a href="<?php echo admin_url('kt_einvoice/dashboard'); ?>">Hóa Đơn Điện Tử</a></li>
                    <li><a href="<?php echo admin_url('kt_einvoice/invoices'); ?>">Danh sách</a></li>
                    <li class="active">Chi tiết #<?php echo $record['id']; ?></li>
                </ol>

                <!-- Header -->
                <div class="tw-flex tw-items-start tw-justify-between tw-mb-5">
                    <div>
                        <h4 class="tw-text-2xl tw-font-bold tw-text-gray-800 tw-mb-1">
                            <?php echo _l('kt_einvoice_detail_title'); ?>
                        </h4>
                        <div class="tw-flex tw-items-center tw-gap-3">
                            <?php echo kt_einvoice_status_badge($record['status']); ?>
                            <?php if ($record['environment'] === 'sandbox'): ?>
                                <span class="label label-warning"><i class="fa fa-flask"></i> Sandbox</span>
                            <?php endif; ?>
                            <?php if (!empty($record['invoice_number'])): ?>
                                <span class="text-muted">Số HĐ: <strong class="text-dark"><?php echo htmlspecialchars($record['invoice_number']); ?></strong></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- Action buttons -->
                    <div class="tw-flex tw-gap-2">
                        <?php if ($record['status'] === KT_EINVOICE_STATUS_DRAFT && staff_can('issue', 'kt_einvoice')): ?>
                            <button type="button" class="btn btn-success" id="btn-issue" data-record-id="<?php echo $record['id']; ?>">
                                <i class="fa fa-paper-plane"></i> <?php echo _l('kt_einvoice_btn_issue'); ?>
                            </button>
                        <?php endif; ?>

                        <?php if ($record['status'] === KT_EINVOICE_STATUS_ISSUED && staff_can('download', 'kt_einvoice')): ?>
                            <a href="<?php echo admin_url('kt_einvoice/download/' . $record['id'] . '/pdf'); ?>"
                               target="_blank" class="btn btn-danger">
                                <i class="fa fa-file-pdf-o"></i> <?php echo _l('kt_einvoice_btn_download_pdf'); ?>
                            </a>
                            <?php if (kt_einvoice_tenant_has_feature(kt_saas_current_tenant_id(), KT_EINVOICE_FEATURE_DOWNLOAD_XML)): ?>
                            <a href="<?php echo admin_url('kt_einvoice/download/' . $record['id'] . '/xml'); ?>"
                               target="_blank" class="btn btn-info">
                                <i class="fa fa-file-code-o"></i> <?php echo _l('kt_einvoice_btn_download_xml'); ?>
                            </a>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($record['status'] === KT_EINVOICE_STATUS_ISSUED && staff_can('cancel', 'kt_einvoice')): ?>
                            <button type="button" class="btn btn-warning" id="btn-cancel" data-record-id="<?php echo $record['id']; ?>">
                                <i class="fa fa-ban"></i> <?php echo _l('kt_einvoice_btn_cancel_invoice'); ?>
                            </button>
                        <?php endif; ?>

                        <?php if ($record['status'] === KT_EINVOICE_STATUS_DRAFT && staff_can('delete', 'kt_einvoice')): ?>
                            <button type="button" class="btn btn-default btn-sm" id="btn-delete" data-record-id="<?php echo $record['id']; ?>">
                                <i class="fa fa-trash"></i> <?php echo _l('kt_einvoice_btn_delete'); ?>
                            </button>
                        <?php endif; ?>

                        <?php if (in_array($record['status'], [KT_EINVOICE_STATUS_PENDING_CREATE, KT_EINVOICE_STATUS_PENDING_ISSUE, KT_EINVOICE_STATUS_PENDING_CANCEL])): ?>
                            <button type="button" class="btn btn-default btn-sm" id="btn-check-status" data-record-id="<?php echo $record['id']; ?>">
                                <i class="fa fa-refresh"></i> <?php echo _l('kt_einvoice_btn_check_status'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row">

                    <!-- Cột trái: Thông tin HĐ -->
                    <div class="col-md-7">

                        <!-- Thông tin tổng quan -->
                        <div class="panel_s">
                            <div class="panel-heading">
                                <h4 class="panel-title"><i class="fa fa-info-circle text-info"></i> Thông Tin Hóa Đơn Điện Tử</h4>
                            </div>
                            <div class="panel-body">
                                <table class="table table-condensed no-margin">
                                    <tbody>
                                        <?php
                                        $fields = [
                                            [_l('kt_einvoice_detail_invoice_num'),  $record['invoice_number'] ?? '—'],
                                            [_l('kt_einvoice_detail_series'),       $record['invoice_series'] ?? '—'],
                                            [_l('kt_einvoice_detail_template'),     $record['invoice_template'] ?? '—'],
                                            [_l('kt_einvoice_detail_invoice_date'), $record['invoice_date'] ? date('d/m/Y', strtotime($record['invoice_date'])) : '—'],
                                            [_l('kt_einvoice_detail_issued_at'),    $record['issued_at'] ? date('d/m/Y H:i:s', strtotime($record['issued_at'])) : '—'],
                                            [_l('kt_einvoice_detail_sepay_id'),     $record['sepay_invoice_id'] ?? '—'],
                                            [_l('kt_einvoice_detail_tracking'),     $record['sepay_tracking_code'] ?? '—'],
                                        ];
                                        foreach ($fields as [$label, $value]): ?>
                                        <tr>
                                            <td class="text-muted tw-w-1/3"><?php echo $label; ?></td>
                                            <td class="tw-font-medium"><?php echo htmlspecialchars((string)$value); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Người mua / Người bán -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="panel_s">
                                    <div class="panel-heading">
                                        <h4 class="panel-title"><i class="fa fa-user text-success"></i> <?php echo _l('kt_einvoice_detail_seller'); ?></h4>
                                    </div>
                                    <div class="panel-body">
                                        <?php
                                        $CI = &get_instance();
                                        $tenantId = kt_saas_current_tenant_id();
                                        $settings = (new KtEinvoiceService())->getSettingsForDisplay($tenantId, $record['environment']);
                                        ?>
                                        <p class="tw-font-semibold tw-mb-1"><?php echo htmlspecialchars($settings['seller_name'] ?? ''); ?></p>
                                        <p class="text-muted tw-mb-1 tw-text-sm">MST: <?php echo htmlspecialchars($settings['seller_tax_code'] ?? '—'); ?></p>
                                        <p class="text-muted tw-mb-0 tw-text-sm"><?php echo htmlspecialchars($settings['seller_address'] ?? ''); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="panel_s">
                                    <div class="panel-heading">
                                        <h4 class="panel-title"><i class="fa fa-building text-primary"></i> <?php echo _l('kt_einvoice_detail_buyer'); ?></h4>
                                    </div>
                                    <div class="panel-body">
                                        <p class="tw-font-semibold tw-mb-1"><?php echo htmlspecialchars($record['buyer_name'] ?? '—'); ?></p>
                                        <?php if (!empty($record['buyer_tax_code'])): ?>
                                        <p class="text-muted tw-mb-1 tw-text-sm">MST: <?php echo htmlspecialchars($record['buyer_tax_code']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tổng tiền -->
                        <div class="panel_s">
                            <div class="panel-heading">
                                <h4 class="panel-title"><i class="fa fa-money text-warning"></i> Giá Trị Hóa Đơn</h4>
                            </div>
                            <div class="panel-body">
                                <table class="table table-condensed no-margin">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted">Tổng tiền hàng</td>
                                            <td class="text-right tw-font-medium">
                                                <?php echo kt_einvoice_format_currency((float)$record['total_amount'] - (float)$record['tax_amount']); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><?php echo _l('kt_einvoice_detail_tax_amount'); ?></td>
                                            <td class="text-right tw-font-medium">
                                                <?php echo kt_einvoice_format_currency((float)$record['tax_amount']); ?>
                                            </td>
                                        </tr>
                                        <tr class="tw-bg-gray-50">
                                            <td class="tw-font-bold"><?php echo _l('kt_einvoice_detail_total'); ?></td>
                                            <td class="text-right tw-font-bold tw-text-lg text-success">
                                                <?php echo kt_einvoice_format_currency((float)$record['total_amount']); ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div><!-- /col-left -->

                    <!-- Cột phải: Trạng thái & Log -->
                    <div class="col-md-5">

                        <!-- Invoice gốc -->
                        <div class="panel_s">
                            <div class="panel-heading">
                                <h4 class="panel-title"><i class="fa fa-link text-muted"></i> Invoice CRM</h4>
                            </div>
                            <div class="panel-body">
                                <a href="<?php echo admin_url('invoices/list_invoices/' . $record['perfex_invoice_id']); ?>" class="btn btn-block btn-default">
                                    <i class="fa fa-external-link"></i>
                                    Invoice #<?php echo htmlspecialchars($record['perfex_invoice_number']); ?>
                                </a>
                            </div>
                        </div>

                        <!-- Retry info -->
                        <?php if (!empty($record['status_message'])): ?>
                        <div class="panel_s">
                            <div class="panel-heading" style="background: #fff5f5;">
                                <h4 class="panel-title text-danger"><i class="fa fa-exclamation-triangle"></i> <?php echo _l('kt_einvoice_detail_error'); ?></h4>
                            </div>
                            <div class="panel-body">
                                <p class="tw-text-sm text-danger"><?php echo htmlspecialchars($record['status_message']); ?></p>
                                <table class="table table-condensed table-bordered no-margin">
                                    <tr>
                                        <td class="text-muted tw-text-sm">Số lần tạo</td>
                                        <td class="tw-text-sm"><?php echo $record['create_attempts']; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted tw-text-sm">Số lần phát hành</td>
                                        <td class="tw-text-sm"><?php echo $record['issue_attempts']; ?></td>
                                    </tr>
                                    <?php if (!empty($record['next_retry_at'])): ?>
                                    <tr>
                                        <td class="text-muted tw-text-sm">Thử lại lúc</td>
                                        <td class="tw-text-sm"><?php echo date('d/m/Y H:i', strtotime($record['next_retry_at'])); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                                <?php if (in_array($record['status'], [KT_EINVOICE_STATUS_FAILED_CREATE, KT_EINVOICE_STATUS_FAILED_ISSUE]) && staff_can('issue', 'kt_einvoice')): ?>
                                <button type="button" class="btn btn-warning btn-block tw-mt-3" id="btn-retry">
                                    <i class="fa fa-refresh"></i> Thử Lại Ngay
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Hủy info -->
                        <?php if ($record['status'] === KT_EINVOICE_STATUS_CANCELLED): ?>
                        <div class="panel_s">
                            <div class="panel-body">
                                <p class="tw-font-medium text-muted"><i class="fa fa-ban"></i> Hóa đơn đã bị hủy</p>
                                <?php if (!empty($record['cancel_reason'])): ?>
                                <p class="tw-text-sm">Lý do: <?php echo htmlspecialchars($record['cancel_reason']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($record['cancelled_at'])): ?>
                                <p class="tw-text-sm text-muted">Thời điểm: <?php echo date('d/m/Y H:i', strtotime($record['cancelled_at'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Timestamps -->
                        <div class="panel_s">
                            <div class="panel-heading">
                                <h4 class="panel-title"><i class="fa fa-clock-o text-muted"></i> Lịch sử thời gian</h4>
                            </div>
                            <div class="panel-body">
                                <table class="table table-condensed no-margin tw-text-sm">
                                    <tr>
                                        <td class="text-muted">Tạo lúc</td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($record['created_at'])); ?></td>
                                    </tr>
                                    <?php if (!empty($record['issued_at'])): ?>
                                    <tr>
                                        <td class="text-muted">Phát hành lúc</td>
                                        <td class="text-success"><?php echo date('d/m/Y H:i', strtotime($record['issued_at'])); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td class="text-muted">Cập nhật lúc</td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($record['updated_at'])); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- API Response debug (chỉ admin) -->
                        <?php if (!empty($record['response_payload_json'])): ?>
                        <div class="panel_s">
                            <div class="panel-heading">
                                <h4 class="panel-title">
                                    <a data-toggle="collapse" href="#kt-api-response">
                                        <i class="fa fa-code text-muted"></i> API Response (debug)
                                    </a>
                                </h4>
                            </div>
                            <div id="kt-api-response" class="panel-collapse collapse">
                                <div class="panel-body">
                                    <pre class="tw-text-xs tw-bg-gray-100 tw-p-3 tw-rounded tw-overflow-auto" style="max-height:200px;"><?php echo htmlspecialchars(json_encode(json_decode($record['response_payload_json']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div><!-- /col-right -->

                </div><!-- /row -->

            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    // Issue
    $('#btn-issue').on('click', function() {
        if (!confirm('<?php echo _l('kt_einvoice_btn_issue_confirm'); ?>')) return;
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Đang phát hành...');
        $.post('<?php echo admin_url('kt_einvoice/issue/' . $record['id']); ?>', {}, function(resp) {
            if (resp.success) {
                toastr.success(resp.message);
                setTimeout(function() { location.reload(); }, 2000);
            } else {
                toastr.error(resp.message);
                $btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> <?php echo _l('kt_einvoice_btn_issue'); ?>');
            }
        }, 'json');
    });

    // Cancel
    $('#btn-cancel').on('click', function() {
        var reason = prompt('<?php echo _l('kt_einvoice_cancel_reason'); ?>:\n(Bắt buộc nhập lý do)');
        if (reason === null || reason.trim() === '') {
            if (reason !== null) toastr.warning('Vui lòng nhập lý do hủy.');
            return;
        }
        if (!confirm('<?php echo _l('kt_einvoice_btn_cancel_confirm'); ?>')) return;
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        $.post('<?php echo admin_url('kt_einvoice/cancel_invoice/' . $record['id']); ?>', {reason: reason}, function(resp) {
            if (resp.success) {
                toastr.success(resp.message);
                setTimeout(function() { location.reload(); }, 2000);
            } else {
                toastr.error(resp.message);
                $btn.prop('disabled', false).html('<i class="fa fa-ban"></i> <?php echo _l('kt_einvoice_btn_cancel_invoice'); ?>');
            }
        }, 'json');
    });

    // Delete draft
    $('#btn-delete').on('click', function() {
        if (!confirm('<?php echo _l('kt_einvoice_btn_delete_confirm'); ?>')) return;
        $.post('<?php echo admin_url('kt_einvoice/delete_draft/' . $record['id']); ?>', {}, function(resp) {
            if (resp.success) {
                toastr.success(resp.message);
                setTimeout(function() { window.location = '<?php echo admin_url('kt_einvoice/invoices'); ?>'; }, 1500);
            } else {
                toastr.error(resp.message);
            }
        }, 'json');
    });

    // Check status
    $('#btn-check-status').on('click', function() {
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        $.post('<?php echo admin_url('kt_einvoice/check_status/' . $record['id']); ?>', {}, function(resp) {
            if (resp.success) {
                toastr.info('Trạng thái: ' + resp.data.status);
                if (['issued', 'cancelled', 'deleted', 'draft'].indexOf(resp.data.status) >= 0) {
                    setTimeout(function() { location.reload(); }, 1000);
                }
            }
            $btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> <?php echo _l('kt_einvoice_btn_check_status'); ?>');
        }, 'json');
    });

    // Retry
    $('#btn-retry').on('click', function() {
        $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        $.post('<?php echo admin_url('kt_einvoice/issue/' . $record['id']); ?>', {}, function(resp) {
            if (resp.success) {
                toastr.success(resp.message);
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                toastr.error(resp.message);
            }
        }, 'json');
    });

    // Auto-refresh khi đang pending
    <?php if (in_array($record['status'], [KT_EINVOICE_STATUS_PENDING_CREATE, KT_EINVOICE_STATUS_PENDING_ISSUE, KT_EINVOICE_STATUS_PENDING_CANCEL])): ?>
    var autoRefresh = setInterval(function() {
        $.post('<?php echo admin_url('kt_einvoice/check_status/' . $record['id']); ?>', {}, function(resp) {
            if (resp.success && ['issued', 'draft', 'cancelled', 'failed_create', 'failed_issue'].indexOf(resp.data.status) >= 0) {
                clearInterval(autoRefresh);
                location.reload();
            }
        }, 'json');
    }, 5000); // Poll mỗi 5 giây
    <?php endif; ?>
});
</script>
<?php init_tail(); ?>
