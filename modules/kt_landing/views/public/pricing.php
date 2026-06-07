<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($title ?? 'Bảng giá CRM Khách Tốt'); ?></title>
    <link rel="stylesheet" href="<?php echo base_url('assets/plugins/bootstrap/css/bootstrap.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/kt_public_typography.css'); ?>">
    <style>
        body { background: #f7f9fc; font-family: var(--kt-font-sans); }
        .box { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 18px; margin-bottom: 12px; }
        h1 { font-family: var(--kt-font-heading); font-size: var(--kt-text-3xl); line-height: var(--kt-leading-tight); font-weight: 800; }
        h3 { font-family: var(--kt-font-heading); font-size: 22px; line-height: 1.25; font-weight: 700; margin: 0 0 10px; }
        .price-line strong { display: inline-block; font-family: var(--kt-font-heading); font-size: 30px; line-height: 1.08; font-weight: 800; letter-spacing: -.01em; }
        .price-line small { font-size: var(--kt-text-sm); color: #64748b; }
        ul { line-height: var(--kt-leading-copy); }
    </style>
</head>
<body>
<div class="container" style="padding-top:24px;padding-bottom:24px;">
    <h1>Bảng giá CRM Khách Tốt</h1>
    <p><a href="<?php echo site_url(); ?>">Trang chủ</a> | <a href="<?php echo site_url('signup'); ?>">Đăng ký</a></p>

    <?php foreach (($public_plans ?? []) as $plan) { ?>
        <div class="box">
            <h3><?php echo html_escape($plan['plan_name'] ?? ''); ?></h3>
            <p class="price-line"><strong><?php echo app_format_money((float) ($plan['price'] ?? 0), $plan['currency'] ?? 'USD'); ?></strong> <small>/ <?php echo html_escape($plan['billing_cycle'] ?? 'monthly'); ?></small></p>
            <ul>
                <li>Giới hạn nhân sự: <?php echo (int) ($plan['limit_staff'] ?? 0) === 0 ? 'Không giới hạn' : (int) ($plan['limit_staff'] ?? 0); ?></li>
                <li>Giới hạn khách hàng: <?php echo (int) ($plan['limit_clients'] ?? 0) === 0 ? 'Không giới hạn' : (int) ($plan['limit_clients'] ?? 0); ?></li>
                <li>Dung lượng: <?php echo (int) ($plan['limit_storage_mb'] ?? 0) === 0 ? 'Không giới hạn' : (int) ($plan['limit_storage_mb'] ?? 0) . ' MB'; ?></li>
                <li>Dùng thử: <?php echo (int) ($plan['trial_days'] ?? 0); ?> ngày</li>
            </ul>
        </div>
    <?php } ?>

    <?php if (empty($public_plans ?? [])) { ?>
        <div class="alert alert-info">Không có gói công khai để hiển thị.</div>
    <?php } ?>
</div>
</body>
</html>
