<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php echo payment_gateway_head(); ?>
<body class="gateway-sepay">
<div class="container">
    <div class="col-md-8 col-md-offset-2 mtop30">
        <div class="mbot30 text-center"><?php echo payment_gateway_logo(); ?></div>
        <div class="panel_s">
            <div class="panel-heading">
                <h4 class="panel-title"><?php echo html_escape($title ?? _l('kt_sepay_pay_with_sepay')); ?></h4>
            </div>
            <div class="panel-body text-center">
                <p><strong><?php echo _l('kt_sepay_status'); ?>:</strong> <span id="kt-sepay-status"><?php echo html_escape(kt_sepay_status_label($request['status'])); ?></span></p>
                <img src="<?php echo html_escape($request['qr_url']); ?>" alt="Mã QR thanh toán" class="img-responsive center-block" style="max-width:320px;">
                <hr />
                <?php if (($request['context_type'] ?? '') === 'kt_matbao_invoice_order') { ?>
                    <p class="text-muted">Thông tin nhận tiền của Khách Tốt, đơn vị cung cấp dịch vụ.</p>
                <?php } ?>
                <p><strong><?php echo _l('kt_sepay_bank'); ?>:</strong> <?php echo html_escape($settings['bank_code'] ?? ''); ?></p>
                <p><strong><?php echo _l('kt_sepay_account'); ?>:</strong> <span id="kt-sepay-account"><?php echo html_escape($settings['account_number'] ?? ''); ?></span></p>
                <p><strong><?php echo _l('kt_sepay_amount'); ?>:</strong> <span id="kt-sepay-amount"><?php echo html_escape(number_format((float) $request['amount'], 0, '.', ',')); ?></span> VND</p>
                <p><strong><?php echo _l('kt_sepay_transfer_content'); ?>:</strong> <span id="kt-sepay-ref"><?php echo html_escape($request['reference_code']); ?></span></p>
                <div class="btn-group">
                    <button type="button" class="btn btn-default" data-copy-target="#kt-sepay-account"><?php echo _l('kt_sepay_copy_account'); ?></button>
                    <button type="button" class="btn btn-default" data-copy-target="#kt-sepay-amount"><?php echo _l('kt_sepay_copy_amount'); ?></button>
                    <button type="button" class="btn btn-default" data-copy-target="#kt-sepay-ref"><?php echo _l('kt_sepay_copy_content'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function() {
    var statusUrl = <?php echo json_encode($status_url); ?>;
    function pollStatus() {
        fetch(statusUrl, {credentials: 'same-origin'})
            .then(function(response) { return response.json(); })
            .then(function(payload) {
                if (!payload || !payload.success || !payload.request) return;
                var statusNode = document.getElementById('kt-sepay-status');
                if (statusNode) statusNode.textContent = payload.request.status || '';
                if (payload.request.status === 'paid') {
                    window.location.reload();
                }
            }).catch(function() {});
    }
    setInterval(pollStatus, 10000);
    document.querySelectorAll('[data-copy-target]').forEach(function(button) {
        button.addEventListener('click', function() {
            var node = document.querySelector(button.getAttribute('data-copy-target'));
            if (!node) return;
            navigator.clipboard.writeText(node.textContent || '');
        });
    });
})();
</script>
</body>
<?php echo payment_gateway_footer(); ?>
