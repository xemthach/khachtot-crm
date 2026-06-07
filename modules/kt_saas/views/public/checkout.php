<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$invoicePayload = json_decode((string) ($invoice['payload_json'] ?? ''), true);
if (!is_array($invoicePayload)) {
    $invoicePayload = [];
}
$billingSummary = is_array($invoicePayload['billing_summary'] ?? null) ? $invoicePayload['billing_summary'] : [];
$lineItems = is_array($invoicePayload['line_items'] ?? null) ? array_values($invoicePayload['line_items']) : [];
$subscriptionPrice = (float) ($billingSummary['plan_price'] ?? max(0, (float) ($invoice['grand_total'] ?? 0) - (float) ($billingSummary['setup_fee'] ?? 0)));
$setupFee = (float) ($billingSummary['setup_fee'] ?? 0);
$invoiceTotal = (float) ($invoice['grand_total'] ?? $subscriptionPrice + $setupFee);
?>
<?php echo payment_gateway_head('Thanh toán hóa đơn dịch vụ'); ?>
<style>
    :root{
        --kt-font-sans:"Inter",system-ui,"Segoe UI",Roboto,Arial,sans-serif;
        --kt-font-heading:"Inter",system-ui,"Segoe UI",Roboto,Arial,sans-serif;
    }
    body.gateway-stripe-ideal{font-family:var(--kt-font-sans);background:#f4f7fb;color:#0f172a}
    .kt-checkout-shell{padding-top:28px;padding-bottom:36px}
    .kt-checkout-card{border-radius:18px;overflow:hidden;box-shadow:0 24px 60px rgba(15,23,42,.08)}
    .kt-checkout-card .panel-heading{padding:18px 22px;background:linear-gradient(180deg,#fff,#f8fbff);border-bottom:1px solid #dbe4ef}
    .kt-checkout-card .panel-title{font-family:var(--kt-font-heading);font-size:28px;line-height:1.15;font-weight:800;color:#0f172a}
    .kt-checkout-card .panel-body{padding:22px}
    .kt-checkout-card p{font-size:15px;line-height:1.7}
    .kt-checkout-card p strong{font-weight:800}
    .kt-checkout-amount{font-family:var(--kt-font-heading);font-size:34px;line-height:1.05;font-weight:800;letter-spacing:-.01em;color:#0f4c81}
    .kt-checkout-line-items{margin-top:18px;border-top:1px solid #dbe4ef;padding-top:16px}
    .kt-checkout-line-items h5{margin:0 0 10px;font-family:var(--kt-font-heading);font-size:16px;font-weight:800;color:#0f172a}
    .kt-checkout-line-item{display:flex;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px dashed #dbe4ef}
    .kt-checkout-line-item:last-child{border-bottom:0}
    .kt-checkout-line-item .label{font-weight:700;color:#334155;min-width:0}
    .kt-checkout-line-item .amount{font-family:var(--kt-font-heading);font-weight:800;color:#0f172a;white-space:nowrap}
    .kt-checkout-card .btn{font-weight:800;font-size:14px;border-radius:12px;padding:12px 16px}
    .kt-checkout-note{padding:12px 14px;border-radius:10px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-weight:600;margin-bottom:14px}
    @media (max-width:768px){
        .kt-checkout-card .panel-title{font-size:24px}
        .kt-checkout-amount{font-size:30px}
        .kt-checkout-line-item{flex-direction:column;gap:4px}
        .kt-checkout-line-item .amount{white-space:normal}
    }
</style>
<body class="gateway-stripe-ideal">
<div class="container">
    <div class="col-md-8 col-md-offset-2 mtop30 kt-checkout-shell">
        <div class="mbot30 text-center">
            <?php echo payment_gateway_logo(); ?>
        </div>
        <div class="panel_s kt-checkout-card">
            <div class="panel-heading">
                <h4 class="panel-title">Thanh toán hóa đơn dịch vụ</h4>
            </div>
            <div class="panel-body">
                <p><strong><?php echo _l('kt_saas_invoice_number'); ?>:</strong> <?php echo html_escape($invoice['invoice_number']); ?></p>
                <p><strong>Doanh nghiệp:</strong> <?php echo html_escape($invoice['company_name'] ?? '-'); ?></p>
                <p><strong><?php echo _l('kt_saas_invoice_type'); ?>:</strong> <?php echo html_escape(kt_saas_invoice_reason_label($invoice)); ?></p>
                <p><strong><?php echo _l('kt_saas_status'); ?>:</strong> <span class="label label-<?php echo kt_saas_status_badge_class($invoice['status']); ?>"><?php echo html_escape((kt_saas_invoice_statuses()[$invoice['status']] ?? $invoice['status'])); ?></span></p>
                <p><strong><?php echo _l('kt_saas_due_date'); ?>:</strong> <?php echo !empty($invoice['due_date']) ? html_escape($invoice['due_date']) : '-'; ?></p>
                <p><strong><?php echo _l('kt_saas_amount'); ?>:</strong> <span class="kt-checkout-amount"><?php echo app_format_money((float) $invoiceTotal, $invoice['currency'] ?? 'VND'); ?></span></p>

                <?php if (!empty($lineItems)) { ?>
                    <div class="kt-checkout-line-items">
                        <h5>Chi tiết thanh toán</h5>
                        <?php foreach ($lineItems as $item) { ?>
                            <div class="kt-checkout-line-item">
                                <div class="label"><?php echo html_escape($item['label'] ?? '-'); ?></div>
                                <div class="amount"><?php echo app_format_money((float) ($item['amount'] ?? 0), $invoice['currency'] ?? 'VND'); ?></div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>

                <?php if ($status_message === 'paid') { ?>
                    <div class="alert alert-success">Thanh toán đã được xác nhận thành công.</div>
                <?php } elseif ($status_message === 'failed') { ?>
                    <div class="alert alert-danger">Không thể hoàn tất thanh toán.</div>
                <?php } elseif ($status_message === 'already_paid') { ?>
                    <div class="alert alert-info">Hóa đơn này đã được thanh toán trước đó.</div>
                <?php } elseif ($status_message === 'manual_disabled') { ?>
                    <div class="alert alert-warning">Chế độ thanh toán thủ công đã tắt. Vui lòng thanh toán qua SePay.</div>
                <?php } ?>

                <?php if ($payable) { ?>
                    <?php if (!empty($sepay_url)) { ?>
                        <div class="kt-checkout-note">Hệ thống sẽ chuyển bạn sang cổng SePay để quét QR và xác nhận thanh toán theo thời gian thực.</div>
                        <a href="<?php echo html_escape($sepay_url); ?>" class="btn btn-success">Thanh toán qua SePay</a>
                    <?php } elseif (!empty($manual_checkout_enabled)) { ?>
                        <div class="alert alert-warning">SePay chưa sẵn sàng. Bạn có thể dùng xác nhận thủ công tạm thời (chỉ dành cho vận hành nội bộ).</div>
                        <form action="<?php echo site_url('kt_saas/checkout/pay/' . (int) $invoice['id'] . '/' . rawurlencode((string) $token)); ?>" method="post">
                            <input type="hidden" name="<?php echo html_escape($this->security->get_csrf_token_name()); ?>" value="<?php echo html_escape($this->security->get_csrf_hash()); ?>">
                            <button type="submit" class="btn btn-warning">Xác nhận thanh toán thủ công</button>
                        </form>
                    <?php } else { ?>
                        <div class="alert alert-danger">Không tạo được yêu cầu thanh toán SePay. Vui lòng liên hệ quản trị viên hệ thống.</div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="alert alert-info">Hóa đơn này hiện không thể thanh toán.</div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
</body>
<?php echo payment_gateway_footer(); ?>
