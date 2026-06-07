<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($meta_title ?? $title ?? 'CRM Khách Tốt'); ?></title>
    <meta name="description" content="<?php echo html_escape($meta_description ?? ''); ?>">
    <link rel="canonical" href="<?php echo html_escape($canonical_url ?? current_url()); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/plugins/bootstrap/css/bootstrap.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/kt_public_typography.css'); ?>">
    <link rel="stylesheet" href="<?php echo module_dir_url('kt_landing', 'assets/templates/minimal_enterprise/style.css'); ?>">
    <?php if (!empty($custom_css)) { ?><style><?php echo $custom_css; ?></style><?php } ?>
</head>
<body class="kt-template-enterprise" style="--primary:<?php echo html_escape($primary_color); ?>;--secondary:<?php echo html_escape($secondary_color); ?>;--cta:<?php echo html_escape($cta_color); ?>;">
<header class="topbar container">
    <div class="brand"><?php echo html_escape($brand_name); ?></div>
    <a href="<?php echo site_url('signup'); ?>" class="btn-cta">Liên hệ triển khai</a>
</header>
<main class="container">
    <section class="hero">
        <h1><?php echo html_escape($hero_title); ?></h1>
        <p><?php echo html_escape($hero_subtitle); ?></p>
        <div class="enterprise-visual">
            <div class="grid-overlay"></div>
            <div class="security-panels">
                <article><strong>Security</strong><span>Access controls</span></article>
                <article><strong>Reliability</strong><span>Provisioning status</span></article>
                <article><strong>Insights</strong><span>Revenue pipeline</span></article>
            </div>
        </div>
    </section>

    <section class="section">
        <h2>Platform overview</h2>
        <div class="line-list">
            <?php foreach (($features ?? []) as $feature) { ?>
            <article>
                <h3><?php echo html_escape($feature['title'] ?? ''); ?></h3>
                <p><?php echo html_escape($feature['description'] ?? ''); ?></p>
            </article>
            <?php } ?>
        </div>
    </section>

    <section class="section">
        <h2>Use cases</h2>
        <div class="use-cases">
            <article><h3>Sales CRM</h3><p>Quản lý cơ hội, báo giá, hợp đồng theo chuỗi khép kín.</p></article>
            <article><h3>Operations</h3><p>Điều phối công việc, dự án và SLA theo phòng ban.</p></article>
            <article><h3>Finance</h3><p>Hóa đơn, thanh toán, đối soát theo chuẩn vận hành doanh nghiệp.</p></article>
        </div>
    </section>

    <section class="section">
        <h2>Security & reliability</h2>
        <p>Du lieu doanh nghiep tach rieng, quyen su dung ro rang va quy trinh thanh toan duoc kiem soat.</p>
    </section>

    <section class="section">
        <h2>Pricing</h2>
        <div class="plan-grid">
            <?php foreach (($public_plans ?? []) as $plan) {
                $price = (float) ($plan['price_monthly'] ?? $plan['price'] ?? 0);
                $title = (string) ($plan['landing_marketing_title'] ?? '') !== '' ? (string) $plan['landing_marketing_title'] : (string) ($plan['plan_name'] ?? '');
                $ctaText = (string) ($plan['landing_cta_text'] ?? '');
                $ctaUrl = (string) ($plan['landing_cta_url'] ?? '');
            ?>
            <article class="plan-card">
                <h3><?php echo html_escape($title); ?></h3>
                <p class="price"><?php echo app_format_money($price, ($plan['currency'] ?? 'VND'), true); ?>/<?php echo html_escape((string) ($plan['billing_cycle'] ?? 'monthly')); ?></p>
                <p>Users <?php echo (int)($plan['limit_staff'] ?? 0); ?> · Clients <?php echo (int)($plan['limit_clients'] ?? 0); ?> · Storage <?php echo number_format(((int)($plan['limit_storage_mb'] ?? 0))/1024,1); ?> GB</p>
                <a href="<?php echo html_escape($ctaUrl !== '' ? $ctaUrl : (site_url('signup') . '?plan_id=' . (int) ($plan['id'] ?? 0))); ?>"><?php echo html_escape($ctaText !== '' ? $ctaText : 'Đăng ký gói'); ?></a>
            </article>
            <?php } ?>
        </div>
    </section>

    <section class="section">
        <h2>Insights</h2>
        <p>Nếu chưa có dữ liệu blog CMS, phần này hiển thị bản mô tả ngắn để giữ bố cục trang.</p>
    </section>
    <section class="section final-cta">
        <h2>Trao đổi lộ trình triển khai theo đặc thù doanh nghiệp</h2>
        <a class="btn-cta" href="<?php echo site_url('signup'); ?>">Liên hệ tư vấn</a>
    </section>
</main>
<footer class="footer container"><?php echo html_escape($footer_text ?? ''); ?></footer>
<?php if (!empty($custom_js)) { ?><script><?php echo $custom_js; ?></script><?php } ?>
</body>
</html>
