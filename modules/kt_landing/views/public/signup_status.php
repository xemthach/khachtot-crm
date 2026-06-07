<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($title ?? 'Trạng thái đăng ký'); ?></title>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/kt_public_typography.css'); ?>">
    <style>
        body { margin:0; font-family:var(--kt-font-sans); background:#f4f7fb; color:#0f172a; }
        .wrap { max-width:980px; margin:0 auto; padding:24px 16px; }
        .box { background:#fff; border:1px solid #dbe3ef; border-radius:12px; padding:18px; }
        h1 { font-family:var(--kt-font-heading); font-size:var(--kt-text-3xl); line-height:var(--kt-leading-tight); font-weight:800; }
        .ok { background:#ecfdf3; border:1px solid #86efac; color:#166534; padding:12px; border-radius:8px; }
        .err { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; padding:12px; border-radius:8px; }
        .grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-top:14px; }
        .card { border:1px solid #dbe3ef; border-radius:10px; padding:12px; }
        .muted { font-size:12px; color:#64748b; text-transform:uppercase; letter-spacing:.08em; font-weight:800; }
        .k { font-weight:800; margin-top:4px; font-family:var(--kt-font-heading); word-break:break-word; }
        .amounts { margin-top:14px; border:1px solid #dbe3ef; border-radius:10px; padding:12px; background:#f8fbff; }
        .amounts .row { display:flex; justify-content:space-between; gap:16px; padding:8px 0; border-bottom:1px dashed #dbe3ef; }
        .amounts .row:last-child { border-bottom:0; }
        .amounts .label { color:#475569; font-weight:700; }
        .amounts .value { font-family:var(--kt-font-heading); font-weight:800; color:#0f172a; white-space:nowrap; }
        .timeline { margin-top:14px; border:1px solid #dbe3ef; border-radius:10px; padding:12px; background:#f8fbff; }
        .row { display:flex; justify-content:space-between; align-items:center; padding:7px 0; border-bottom:1px dashed #dbe3ef; gap:12px; }
        .row:last-child { border-bottom:0; }
        .status { font-weight:800; }
        .status.ok { color:#166534; }
        .status.warn { color:#92400e; }
        .status.info { color:#1d4ed8; }
        .btn { display:inline-block; margin-top:14px; padding:10px 14px; background:#1d4ed8; color:#fff; border-radius:8px; text-decoration:none; font-weight:600; }
        .btn.alt { background:#e2e8f0; color:#0f172a; margin-left:8px; }
        @media(max-width:760px){ .grid { grid-template-columns:1fr; } .amounts .row, .row { flex-direction:column; align-items:flex-start; } .amounts .value { white-space:normal; } }
    </style>
</head>
<body>
<?php $r = $signup_result ?? []; $ok = (($r['ok'] ?? '') === '1'); ?>
<?php
$lineItems = is_array($r['line_items'] ?? null) ? array_values($r['line_items']) : [];
$subscriptionPrice = (float) ($r['subscription_price'] ?? 0);
$setupFee = (float) ($r['setup_fee'] ?? 0);
$invoiceTotal = (float) ($r['invoice_total'] ?? ($subscriptionPrice + $setupFee));
?>
<div class="wrap">
    <div class="box">
        <h1 style="margin:0 0 10px;">Trạng thái đăng ký CRM</h1>
        <div class="<?php echo $ok ? 'ok' : 'err'; ?>">
            <?php echo html_escape($r['msg'] ?? ($ok ? 'Đăng ký thành công.' : 'Đăng ký thất bại.')); ?>
        </div>

        <div class="grid">
            <div class="card"><div class="muted">Mã doanh nghiệp</div><div class="k"><?php echo html_escape($r['tenant_code'] ?? '-'); ?></div></div>
            <div class="card"><div class="muted">Số hóa đơn</div><div class="k"><?php echo html_escape($r['invoice_number'] ?? '-'); ?></div></div>
            <div class="card"><div class="muted">Gói dịch vụ</div><div class="k"><?php echo html_escape($r['plan_name'] ?? '-'); ?></div></div>
            <div class="card"><div class="muted">Subdomain</div><div class="k"><?php echo html_escape($r['desired_subdomain'] ?? '-'); ?></div></div>
        </div>

        <div class="amounts">
            <div class="row"><span class="label">Giá gói</span><strong class="value"><?php echo app_format_money($subscriptionPrice, 'VND'); ?></strong></div>
            <div class="row"><span class="label">Phí triển khai ban đầu</span><strong class="value"><?php echo app_format_money($setupFee, 'VND'); ?></strong></div>
            <div class="row"><span class="label">Tổng dự kiến</span><strong class="value"><?php echo app_format_money($invoiceTotal, 'VND'); ?></strong></div>
            <?php if (!empty($lineItems)) { ?>
                <div style="margin-top:10px; color:#64748b; font-size:13px; font-weight:700;">Chi tiết thanh toán</div>
                <?php foreach ($lineItems as $item) { ?>
                    <div class="row">
                        <span class="label"><?php echo html_escape($item['label'] ?? '-'); ?></span>
                        <strong class="value"><?php echo app_format_money((float) ($item['amount'] ?? 0), 'VND'); ?></strong>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>

        <div class="timeline">
            <div class="row"><span>Trạng thái hóa đơn</span><strong id="invoiceStatus" class="status info">Đang kiểm tra...</strong></div>
            <div class="row"><span>Trạng thái gói dịch vụ</span><strong id="subStatus" class="status info">Đang kiểm tra...</strong></div>
            <div class="row"><span>Thanh toán</span><strong id="paymentStatus" class="status warn">Chờ xử lý</strong></div>
            <div class="row"><span>Khởi tạo hệ thống</span><strong id="provStatus" class="status warn">Đang chờ khởi tạo</strong></div>
            <div class="row"><span>Tiến độ khởi tạo</span><strong id="jobStatus" class="status info">Đang kiểm tra...</strong></div>
            <div class="row"><span>Cập nhật gần nhất</span><strong id="jobUpdated">-</strong></div>
        </div>

        <?php if (!empty($r['checkout_url'])) { ?>
            <a class="btn" href="<?php echo html_escape($r['checkout_url']); ?>">Đi tới thanh toán</a>
        <?php } ?>
        <a class="btn alt" href="<?php echo site_url('signup'); ?>">Tạo đăng ký mới</a>
    </div>
</div>

<script>
(function(){
    var tenantCode = <?php echo json_encode((string) ($r['tenant_code'] ?? '')); ?>;
    var invoiceNumber = <?php echo json_encode((string) ($r['invoice_number'] ?? '')); ?>;
    if (!tenantCode) { return; }

    function setText(id, v) {
        var el = document.getElementById(id);
        if (el) { el.textContent = (v === null || v === undefined || v === '') ? '-' : String(v); }
    }
    function setClass(id, cls) {
        var el = document.getElementById(id);
        if (el) { el.className = 'status ' + cls; }
    }
    function mapStatus(s) {
        s = (s || '').toLowerCase();
        if (s === 'paid' || s === 'active' || s === 'done' || s === 'success') { return 'ok'; }
        if (s === 'failed' || s === 'cancelled' || s === 'terminated') { return 'warn'; }
        return 'info';
    }

    function poll() {
        var url = <?php echo json_encode(site_url('signup/progress/')); ?> + encodeURIComponent(tenantCode);
        if (invoiceNumber) { url += '?invoice=' + encodeURIComponent(invoiceNumber); }
        fetch(url, { credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (!d || !d.success) { return; }
                var invoice = d.invoice ? d.invoice.status : '';
                var sub = d.subscription ? d.subscription.status : '';
                var prov = d.tenant ? d.tenant.provisioning_status : '';
                var job = d.provision_job ? d.provision_job.status : '';

                setText('invoiceStatus', invoice || '-');
                setText('subStatus', sub || '-');
                setText('provStatus', prov || '-');
                setText('jobStatus', job || '-');
                setText('jobUpdated', d.provision_job ? (d.provision_job.updated_at || '-') : '-');

                setText('paymentStatus', (invoice === 'paid') ? 'Đã thanh toán' : 'Chờ thanh toán');
                setClass('invoiceStatus', mapStatus(invoice));
                setClass('subStatus', mapStatus(sub));
                setClass('paymentStatus', mapStatus(invoice));
                setClass('provStatus', mapStatus(prov));
                setClass('jobStatus', mapStatus(job));
            })
            .catch(function(){});
    }

    poll();
    setInterval(poll, 8000);
})();
</script>
</body>
</html>
