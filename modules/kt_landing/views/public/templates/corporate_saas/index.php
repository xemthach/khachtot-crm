<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($meta_title ?? $title ?? 'CRM Khách Tốt'); ?></title>
    <meta name="description" content="<?php echo html_escape($meta_description ?? ''); ?>">
    <link rel="canonical" href="<?php echo html_escape($canonical_url ?? current_url()); ?>">
    <?php if (!empty($og_image)) { ?><meta property="og:image" content="<?php echo html_escape($og_image); ?>"><?php } ?>
    <link rel="stylesheet" href="<?php echo base_url('assets/plugins/bootstrap/css/bootstrap.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/kt_public_typography.css'); ?>">
    <link rel="stylesheet" href="<?php echo module_dir_url('kt_landing', 'assets/templates/corporate_saas/style.css'); ?>">
    <?php if (!empty($custom_css)) { ?><style><?php echo $custom_css; ?></style><?php } ?>
</head>
<body class="kt-template-corporate" style="--primary:<?php echo html_escape($primary_color); ?>;--secondary:<?php echo html_escape($secondary_color); ?>;--cta:<?php echo html_escape($cta_color); ?>;">
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
        ['label' => 'Trang chủ', 'url' => site_url(), 'target' => '_self'],
        ['label' => 'Bảng giá', 'url' => site_url('pricing'), 'target' => '_self'],
    ];
}
$brandingContext = is_array($branding_context ?? null) ? $branding_context : [];
$logoUrl = trim((string) ($brandingContext['logo_url'] ?? ''));
if ($logoUrl === '' && !empty($logo) && empty($brandingContext['is_tenant'])) {
    $logoUrl = base_url('uploads/company/' . $logo);
}
?>
<header class="topbar">
    <div class="container topbar-inner">
        <a class="brand" href="<?php echo site_url(); ?>">
            <?php if ($logoUrl !== '') { ?><img src="<?php echo html_escape($logoUrl); ?>" alt="<?php echo html_escape($brand_name); ?>" loading="lazy"><?php } ?>
            <span><?php echo html_escape($brand_name); ?></span>
        </a>
        <nav class="menu">
            <?php foreach ($headerMenu as $m) { ?>
                <a target="<?php echo html_escape($m['target'] ?? '_self'); ?>" href="<?php echo html_escape($m['url'] ?? '#'); ?>"><?php echo html_escape($m['label'] ?? ''); ?></a>
            <?php } ?>
            <a class="btn-cta" href="<?php echo site_url('signup'); ?>"><?php echo html_escape($header_cta_text); ?></a>
        </nav>
    </div>
</header>

<main>
    <section class="hero">
        <div class="container hero-grid">
            <div>
                <h1><?php echo html_escape($hero_title); ?></h1>
                <p><?php echo html_escape($hero_subtitle); ?></p>
                <div class="cta-row">
                    <a class="btn-cta" href="<?php echo site_url('signup'); ?>">Bắt đầu</a>
                    <a class="btn-outline" href="<?php echo site_url('pricing'); ?>">Xem gói dịch vụ</a>
                </div>
            </div>
            <div class="hero-media">
                <?php if (!empty($hero_image)) { ?>
                    <img src="<?php echo html_escape($hero_image); ?>" alt="Hero" loading="lazy">
                <?php } else { ?>
                    <div class="dashboard-mockup">
                        <div class="mockup-head">
                            <span></span><span></span><span></span>
                        </div>
                        <div class="mockup-grid">
                            <div class="stat-card"><strong>Pipeline</strong><em>42 deals</em></div>
                            <div class="stat-card"><strong>Revenue</strong><em>+18%</em></div>
                            <div class="chart-card">
                                <div class="bar b1"></div><div class="bar b2"></div><div class="bar b3"></div><div class="bar b4"></div>
                            </div>
                            <div class="task-card">
                                <p>Tasks</p>
                                <ul><li>Follow-up khách hàng</li><li>Duyệt báo giá</li><li>Đối soát thanh toán</li></ul>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </section>

    <section class="trust container">
        <div>Trien khai nhanh</div><div>Quan tri tap trung</div><div>Mo rong theo ung dung</div><div>Bao mat du lieu</div>
    </section>

    <section class="container section">
        <h2>Năng lực nền tảng</h2>
        <div class="grid-4">
            <?php foreach (($features ?? []) as $feature) { ?>
            <article class="card">
                <h3><?php echo html_escape($feature['title'] ?? ''); ?></h3>
                <p><?php echo html_escape($feature['description'] ?? ''); ?></p>
            </article>
            <?php } ?>
        </div>
    </section>

    <section class="container section section-alt">
        <h2>Kết quả kinh doanh</h2>
        <div class="outcomes">
            <article><h3>Tăng tỷ lệ chốt đơn</h3><p>Theo dõi pipeline minh bạch, không bỏ sót cơ hội.</p></article>
            <article><h3>Giảm thời gian vận hành</h3><p>Tự động hóa các bước lặp lại ở bán hàng và tài chính.</p></article>
            <article><h3>Ra quyết định nhanh</h3><p>Báo cáo tập trung theo thời gian thực cho quản lý.</p></article>
        </div>
    </section>

    <section class="container section">
        <h2>Bảng giá</h2>
        <div class="grid-3">
            <?php foreach (($public_plans ?? []) as $plan) {
                $price = (float) ($plan['price_monthly'] ?? $plan['price'] ?? 0);
                $featured = (int) ($plan['landing_featured'] ?? 0) === 1;
                $title = (string) ($plan['landing_marketing_title'] ?? '') !== '' ? (string) $plan['landing_marketing_title'] : (string) ($plan['plan_name'] ?? '');
                $desc = (string) ($plan['landing_marketing_description'] ?? '');
                $badge = (string) ($plan['landing_badge_text'] ?? '');
                $ctaText = (string) ($plan['landing_cta_text'] ?? '');
                $ctaUrl = (string) ($plan['landing_cta_url'] ?? '');
            ?>
            <article class="plan <?php echo $featured ? 'featured' : ''; ?>">
                <h3><?php echo html_escape($title); ?></h3>
                <?php if ($badge !== '') { ?><span class="badge-pop"><?php echo html_escape($badge); ?></span><?php } ?>
                <p class="price"><?php echo app_format_money($price, ($plan['currency'] ?? 'VND'), true); ?>/<?php echo html_escape((string) ($plan['billing_cycle'] ?? 'monthly')); ?></p>
                <p class="muted"><?php echo html_escape($desc); ?></p>
                <p class="muted">Users <?php echo (int)($plan['limit_staff'] ?? 0); ?> · Clients <?php echo (int)($plan['limit_clients'] ?? 0); ?> · Storage <?php echo number_format(((int)($plan['limit_storage_mb'] ?? 0))/1024,1); ?> GB</p>
                <a class="btn-cta" href="<?php echo html_escape($ctaUrl !== '' ? $ctaUrl : (site_url('signup') . '?plan_id=' . (int) ($plan['id'] ?? 0))); ?>"><?php echo html_escape($ctaText !== '' ? $ctaText : 'Đăng ký gói'); ?></a>
            </article>
            <?php } ?>
            <?php if (empty($public_plans)) { ?><p>Chưa có gói công khai.</p><?php } ?>
        </div>
    </section>

    <section class="container section">
        <h2>FAQ</h2>
        <div class="faq-list">
            <?php foreach (($faqs ?? []) as $faq) { ?>
            <details>
                <summary><?php echo html_escape($faq['q'] ?? ''); ?></summary>
                <p><?php echo html_escape($faq['a'] ?? ''); ?></p>
            </details>
            <?php } ?>
        </div>
    </section>

    <section class="container section final-cta">
        <h2>Sẵn sàng chuẩn hóa vận hành doanh nghiệp?</h2>
        <p>Khoi tao he thong trong vai phut va mo rong theo dung giai doan tang truong.</p>
        <a class="btn-cta" href="<?php echo site_url('signup'); ?>">Bắt đầu dùng thử</a>
    </section>
</main>

<footer class="footer">
    <div class="container">
        <p><?php echo html_escape($footer_text ?? ''); ?></p>
    </div>
</footer>
<?php if (!empty($custom_js)) { ?><script><?php echo $custom_js; ?></script><?php } ?>
</body>
</html>
