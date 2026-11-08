<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($title); ?></title>
    <meta name="description" content="Chọn cổng đăng nhập phù hợp cho Khách Tốt CRM.">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <link rel="canonical" href="<?php echo html_escape($canonical_url); ?>">
    <style>body{margin:0;background:#f8fafc;color:#0f172a;font-family:Inter,system-ui,sans-serif}.wrap{width:min(900px,calc(100% - 32px));margin:auto;padding:72px 0}.shell{display:grid;grid-template-columns:1fr 1fr;border:1px solid #dbe4ef;border-radius:8px;overflow:hidden;background:#fff}.intro,.choices{padding:36px}.intro{background:#0f172a;color:#fff}.intro p{color:#cbd5e1;line-height:1.7}.choices{display:grid;gap:14px}.choice{display:block;border:1px solid #dbe4ef;border-radius:7px;padding:18px;text-decoration:none;color:#0f172a}.choice strong{display:block}.choice span{display:block;margin-top:6px;color:#64748b;font-size:14px}.back{display:inline-block;margin-top:24px;color:#2563eb}@media(max-width:680px){.wrap{padding:32px 0}.shell{grid-template-columns:1fr}.intro,.choices{padding:26px}}</style>
</head>
<body><main class="wrap"><section class="shell"><div class="intro"><p>Khách Tốt CRM</p><h1>Đăng nhập đúng không gian làm việc</h1><p>Trang đăng nhập không hiển thị dữ liệu doanh nghiệp. Hãy sử dụng đúng tenant host được quản trị viên cung cấp.</p></div><div class="choices"><a class="choice" href="<?php echo html_escape($crm_login_url); ?>"><strong>Nhân viên và quản trị CRM</strong><span>Quản lý khách hàng, bán hàng, hóa đơn và DMS console.</span></a><a class="choice" href="<?php echo html_escape($customer_login_url); ?>"><strong>Cổng khách hàng</strong><span>Xem thông tin được doanh nghiệp chia sẻ qua CRM.</span></a><a class="back" href="<?php echo html_escape($canonical_url); ?>">Tìm hiểu Khách Tốt CRM</a></div></section></main></body>
</html>
