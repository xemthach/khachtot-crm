<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($title ?? 'CRM Khách Tốt'); ?></title>
    <link rel="stylesheet" href="<?php echo base_url('assets/plugins/bootstrap/css/bootstrap.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/kt_public_typography.css'); ?>">
    <style>
        body { background: #f7f9fc; font-family: var(--kt-font-sans); }
        .hero { padding: 48px 0 24px; }
        .hero h1 { font-family: var(--kt-font-heading); font-size: var(--kt-text-3xl); line-height: var(--kt-leading-tight); font-weight: 800; }
        .hero p { font-size: var(--kt-text-md); line-height: var(--kt-leading-copy); max-width: 70ch; }
        .plan-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; height: 100%; }
        .plan-card h4 { font-family: var(--kt-font-heading); font-size: 20px; line-height: 1.25; font-weight: 700; }
        .plan-price { font-family: var(--kt-font-heading); font-size: 28px; line-height: 1.08; font-weight: 800; letter-spacing: -.01em; }
        .top-nav { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 10px 0; }
        .top-nav strong { font-family: var(--kt-font-heading); font-size: 18px; }
    </style>
</head>
<body>
<div class="top-nav">
    <div class="container">
        <strong>CRM Khách Tốt</strong>
        <span class="pull-right">
            <a href="<?php echo site_url('pricing'); ?>">Bảng giá</a> |
            <a href="<?php echo site_url('signup'); ?>">Đăng ký</a> |
            <a href="<?php echo site_url('clients/login'); ?>">Đăng nhập</a>
        </span>
    </div>
</div>

<div class="container hero">
    <h1>CRM Khách Tốt cho doanh nghiệp dịch vụ</h1>
    <p>Giải pháp CRM và quản lý doanh nghiệp với dữ liệu tách riêng, thanh toán tích hợp và vận hành trên một hệ thống thống nhất.</p>
    <p>
        <a class="btn btn-primary" href="<?php echo site_url('signup'); ?>">Bắt đầu đăng ký</a>
        <a class="btn btn-default" href="<?php echo site_url('pricing'); ?>">Xem bảng giá</a>
    </p>
</div>

<div class="container">
    <div class="row">
        <?php foreach (($public_plans ?? []) as $plan) { ?>
            <div class="col-md-4 col-sm-6 mtop10">
                <div class="plan-card">
                    <h4><?php echo html_escape($plan['plan_name'] ?? ''); ?></h4>
                    <p class="plan-price"><?php echo app_format_money((float) ($plan['price'] ?? 0), $plan['currency'] ?? 'USD'); ?> <small>/ <?php echo html_escape($plan['billing_cycle'] ?? 'monthly'); ?></small></p>
                    <p>Nhân sự: <?php echo (int) ($plan['limit_staff'] ?? 0) === 0 ? 'Không giới hạn' : (int) ($plan['limit_staff'] ?? 0); ?></p>
                    <p>Khách hàng: <?php echo (int) ($plan['limit_clients'] ?? 0) === 0 ? 'Không giới hạn' : (int) ($plan['limit_clients'] ?? 0); ?></p>
                </div>
            </div>
        <?php } ?>
        <?php if (empty($public_plans ?? [])) { ?>
            <div class="col-md-12">
                <div class="alert alert-info">Hiện chưa có gói công khai.</div>
            </div>
        <?php } ?>
    </div>
</div>
</body>
</html>
