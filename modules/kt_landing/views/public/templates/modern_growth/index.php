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
    <link rel="stylesheet" href="<?php echo module_dir_url('kt_landing', 'assets/templates/modern_growth/style.css'); ?>">
    <?php if (!empty($custom_css)) { ?><style><?php echo $custom_css; ?></style><?php } ?>
</head>
<body class="kt-template-growth" style="--primary:<?php echo html_escape($primary_color); ?>;--secondary:<?php echo html_escape($secondary_color); ?>;--cta:<?php echo html_escape($cta_color); ?>;">
<?php
$headerMenu = [];
if (!empty($menus) && is_array($menus)) {
    foreach ($menus as $m) {
        if (($m['menu_area'] ?? '') === 'header' && (int) ($m['is_enabled'] ?? 1) === 1) {
            $headerMenu[] = $m;
        }
    }
}
if (empty($headerMenu)) {
    $headerMenu = [
        ['label' => 'Bảng giá', 'url' => site_url('pricing'), 'target' => '_self'],
    ];
}
?>
<header class="topbar">
    <div class="container topbar-inner">
        <a class="brand" href="<?php echo site_url(); ?>"><?php echo html_escape($brand_name); ?></a>
        <nav class="menu">
            <?php foreach ($headerMenu as $m) { ?>
                <a target="<?php echo html_escape($m['target'] ?? '_self'); ?>" href="<?php echo html_escape($m['url'] ?? '#'); ?>"><?php echo html_escape($m['label'] ?? ''); ?></a>
            <?php } ?>
            <a href="<?php echo site_url('signup'); ?>" class="btn-cta">Đặt lịch demo</a>
        </nav>
    </div>
</header>
<main>
    <section class="hero">
        <div class="container hero-grid">
            <div>
                <h1><?php echo html_escape($hero_title); ?></h1>
                <p><?php echo html_escape($hero_subtitle); ?></p>
                <a class="btn-cta" href="<?php echo site_url('signup'); ?>">Tạo tài khoản</a>
            </div>
            <div class="panel">
                <h3>Workflow</h3>
                <ol>
                    <li>Lead vào pipeline</li>
                    <li>Tạo báo giá và hợp đồng</li>
                    <li>Xuất hóa đơn và theo dõi thanh toán</li>
                </ol>
                <div class="flow-nodes">
                    <span>Lead</span><span>Deal</span><span>Invoice</span><span>Payment</span>
                </div>
            </div>
        </div>
    </section>

    <section class="container section">
        <h2>Tính năng nổi bật</h2>
        <div class="grid">
            <?php foreach (($features ?? []) as $feature) { ?>
            <article class="card feature-card">
                <h3><?php echo html_escape($feature['title'] ?? ''); ?></h3>
                <p><?php echo html_escape($feature['description'] ?? ''); ?></p>
            </article>
            <?php } ?>
        </div>
    </section>

    <section class="container section">
        <h2>Ứng dụng mở rộng</h2>
        <div class="addons">
            <div class="card addon-card"><h3>Hóa đơn điện tử</h3><p>Phát hành hóa đơn điện tử tích hợp quy trình bán hàng.</p></div>
            <div class="card addon-card"><h3>Thanh toán & Đối soát</h3><p>Thu tiền và đối soát thanh toán theo thời gian thực.</p></div>
            <div class="card addon-card"><h3>Domain & Hosting</h3><p>Hạ tầng sẵn sàng cho vận hành ổn định dài hạn.</p></div>
        </div>
    </section>

    <section class="container section">
        <h2>Bảng giá</h2>
        <div class="pricing">
            <?php foreach (($public_plans ?? []) as $plan) {
                $price = (float) ($plan['price_monthly'] ?? $plan['price'] ?? 0);
                $featured = (int) ($plan['landing_featured'] ?? 0) === 1;
                $title = (string) ($plan['landing_marketing_title'] ?? '') !== '' ? (string) $plan['landing_marketing_title'] : (string) ($plan['plan_name'] ?? '');
                $badge = (string) ($plan['landing_badge_text'] ?? '');
                $ctaText = (string) ($plan['landing_cta_text'] ?? '');
                $ctaUrl = (string) ($plan['landing_cta_url'] ?? '');
            ?>
            <article class="plan <?php echo $featured ? 'featured' : ''; ?>">
                <h3><?php echo html_escape($title); ?></h3>
                <p class="price"><?php echo app_format_money($price, ($plan['currency'] ?? 'VND'), true); ?>/<?php echo html_escape((string) ($plan['billing_cycle'] ?? 'monthly')); ?></p>
                <?php if ($badge !== '') { ?><span class="badge-pop"><?php echo html_escape($badge); ?></span><?php } ?>
                <p>Người dùng <?php echo (int)($plan['limit_staff'] ?? 0); ?> · API <?php echo (int)($plan['limit_api_requests_daily'] ?? 0); ?>/ngày · Dùng thử <?php echo (int)($plan['trial_days'] ?? 0); ?> ngày</p>
                <a class="btn-cta" href="<?php echo html_escape($ctaUrl !== '' ? $ctaUrl : (site_url('signup') . '?plan_id=' . (int) ($plan['id'] ?? 0))); ?>"><?php echo html_escape($ctaText !== '' ? $ctaText : 'Đăng ký gói'); ?></a>
            </article>
            <?php } ?>
        </div>
    </section>

    <section class="container section">
        <h2>Khách hàng nói gì</h2>
        <div class="grid">
            <?php foreach (($testimonials ?? []) as $item) { ?>
            <article class="card">
                <p>"<?php echo html_escape($item['quote'] ?? ''); ?>"</p>
                <strong><?php echo html_escape(($item['name'] ?? '') . ' - ' . ($item['company'] ?? '')); ?></strong>
            </article>
            <?php } ?>
        </div>
    </section>
    <section class="container section final-cta">
        <div class="panel">
            <h2>Đặt lịch demo cùng chuyên gia triển khai</h2>
            <p>Đánh giá quy trình hiện tại và đề xuất lộ trình vận hành phù hợp theo quy mô doanh nghiệp.</p>
            <a class="btn-cta" href="<?php echo site_url('signup'); ?>">Liên hệ demo</a>
        </div>
    </section>
</main>
<footer class="footer"><div class="container"><?php echo html_escape($footer_text ?? ''); ?></div></footer>
<?php if (!empty($custom_js)) { ?><script><?php echo $custom_js; ?></script><?php } ?>
</body>
</html>
