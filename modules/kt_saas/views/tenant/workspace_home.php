<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$branding = is_array($branding_context ?? null) ? $branding_context : [];
$companyName = trim((string) ($company_name ?? 'Không gian CRM'));
$logoUrl = trim((string) ($branding['logo_url'] ?? ''));
$faviconUrl = trim((string) ($branding['favicon_url'] ?? ''));
$crmLoginUrl = trim((string) ($crm_login_url ?? site_url('admin')));
$customerPortalUrl = trim((string) ($customer_login_url ?? site_url('clients')));
$brandHighlightVariants = [
    'mark-swipe',
    'soft-pill',
    'brush-underline',
    'corner-ribbon',
    'double-underline',
    'blob-glow',
    'diagonal-marker',
    'clean-accent',
];
$brandHighlightVariant = $brandHighlightVariants[random_int(0, count($brandHighlightVariants) - 1)];
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($title ?? ('Chào mừng đến với ' . $companyName)); ?></title>
    <?php if ($faviconUrl !== '') { ?>
        <link rel="shortcut icon" href="<?php echo html_escape($faviconUrl); ?>">
    <?php } ?>
    <style>
        @font-face {
            font-family: 'Inter';
            src: url('<?php echo html_escape(base_url('assets/plugins/inter/Inter-roman.var.woff2')); ?>') format('woff2');
            font-weight: 100 900;
            font-style: normal;
            font-display: swap;
        }
        :root {
            --kt-primary: #0f4c81;
            --kt-primary-strong: #09345c;
            --kt-accent: #38bdf8;
            --kt-bg: #eef7fc;
            --kt-card: #ffffff;
            --kt-text: #0f172a;
            --kt-muted: #5d6b82;
            --kt-border: #d6e5ef;
            --kt-soft: #e8f5fb;
        }
        * { box-sizing: border-box; }
        html { background: #fff; }
        body {
            margin: 0;
            overflow-x: hidden;
            font-family: "Be Vietnam Pro", "Inter", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-feature-settings: "kern" 1;
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            color: var(--kt-text);
            background: #fff;
        }
        a { color: inherit; }
        .kt-shell {
            min-height: 0;
            background:
                radial-gradient(circle at 12% 8%, rgba(56,189,248,.10), transparent 30%),
                linear-gradient(180deg, #eaf8ff 0%, #f7fbfd 42%, #fff 100%);
        }
        .kt-container { width: min(1180px, calc(100% - 40px)); margin: 0 auto; }
        .kt-header { position: sticky; top: 0; z-index: 5; border-bottom: 1px solid rgba(214,229,239,.86); background: rgba(255,255,255,.88); backdrop-filter: blur(14px); }
        .kt-nav { min-height: 76px; display: flex; align-items: center; justify-content: space-between; gap: 18px; }
        .kt-brand { display: flex; align-items: center; gap: 12px; min-width: 0; text-decoration: none; }
        .kt-brand img { max-height: 42px; max-width: 180px; object-fit: contain; }
        .kt-brand-mark { width: 42px; height: 42px; border-radius: 13px; background: var(--kt-primary); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; }
        .kt-brand-name { font-weight: 700; font-size: 20px; line-height: 1.3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .kt-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
        .kt-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; padding: 11px 18px; border-radius: 10px; border: 1px solid var(--kt-border); background: #fff; color: var(--kt-primary-strong); text-decoration: none; font-weight: 600; letter-spacing: 0; line-height: 1.35; box-shadow: 0 6px 18px rgba(15,76,129,.06); }
        .kt-btn.primary { background: var(--kt-primary); border-color: var(--kt-primary); color: #fff; box-shadow: 0 12px 28px rgba(15,76,129,.22); }
        .kt-main { position: relative; isolation: isolate; overflow: hidden; padding: 44px 0 0; }
        .kt-main:before,
        .kt-main:after { content: ""; position: absolute; z-index: -1; border-radius: 999px; pointer-events: none; }
        .kt-main:before { top: 10px; left: -150px; width: 390px; height: 390px; background: rgba(125,211,252,.17); filter: blur(8px); }
        .kt-main:after { top: 150px; right: -130px; width: 340px; height: 340px; background: rgba(167,243,208,.13); filter: blur(10px); }
        .tenant-home-stage { position: relative; isolation: isolate; }
        .tenant-home-stage:before {
            content: "";
            position: absolute;
            z-index: -1;
            top: 370px;
            left: 50%;
            width: 124vw;
            height: 260px;
            border-radius: 50% 50% 0 0 / 34% 34% 0 0;
            background: linear-gradient(180deg, rgba(255,255,255,.58), rgba(255,255,255,.10));
            transform: translateX(-50%) rotate(-1deg);
            pointer-events: none;
        }
        .tenant-home-decor { position: absolute; inset: 0; z-index: 1; pointer-events: none; }
        .tenant-home-decor-icon {
            position: absolute;
            display: grid;
            width: 42px;
            height: 42px;
            place-items: center;
            border: 1px solid rgba(15,76,129,.10);
            border-radius: 14px;
            background: rgba(255,255,255,.72);
            box-shadow: 0 10px 26px rgba(15,76,129,.08);
            color: rgba(15,76,129,.48);
        }
        .tenant-home-decor-icon svg { width: 21px; height: 21px; fill: none; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.8; }
        .tenant-home-decor-icon--shield { top: 34px; left: -52px; transform: rotate(-8deg); }
        .tenant-home-decor-icon--document { top: 154px; right: -48px; transform: rotate(7deg); }
        .tenant-home-decor-icon--receipt { top: 398px; left: -44px; transform: rotate(6deg); }
        .tenant-home-decor-icon--chat { top: 430px; right: -40px; transform: rotate(-7deg); }
        .tenant-home-decor-icon--check { top: 310px; left: -30px; width: 34px; height: 34px; border-radius: 999px; transform: rotate(-5deg); }
        .kt-hero,
        .kt-grid { position: relative; z-index: 2; }
        .kt-hero { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(320px, .8fr); gap: 22px; align-items: stretch; }
        .kt-hero > * { min-width: 0; }
        .kt-panel { background: rgba(255,255,255,.94); border: 1px solid rgba(196,218,232,.72); border-radius: 18px; box-shadow: 0 16px 42px rgba(15,76,129,.085); }
        .kt-hero-main {
            padding: 42px;
            overflow: hidden;
            background:
                radial-gradient(circle at 94% 88%, rgba(56,189,248,.10), transparent 31%),
                linear-gradient(145deg, rgba(255,255,255,.99) 0%, rgba(247,252,255,.95) 100%);
        }
        .kt-kicker { display: inline-flex; align-items: center; gap: 8px; padding: 7px 12px; border-radius: 999px; background: #dcfce7; color: #166534; font-size: 13px; font-weight: 600; line-height: 1.35; margin-bottom: 20px; }
        .tenant-home-title { font-size: clamp(34px, 4.4vw, 56px); font-weight: 700; line-height: 1.1; margin: 0 0 20px; letter-spacing: -0.02em; max-width: 720px; }
        .tenant-home-title-prefix { display: block; margin-bottom: 8px; }
        .tenant-brand-highlight {
            color: var(--kt-primary-strong);
            line-height: 1.18;
            letter-spacing: -0.01em;
            overflow-wrap: anywhere;
            padding: 0 .08em .03em;
            border-radius: .2em;
            background: linear-gradient(
                178deg,
                transparent 0%,
                transparent 53%,
                rgba(56,189,248,.24) 53%,
                rgba(15,76,129,.18) 88%,
                transparent 88%
            );
            -webkit-box-decoration-break: clone;
            box-decoration-break: clone;
        }
        .tenant-brand-highlight--mark-swipe {
            background: linear-gradient(178deg, transparent 0 53%, rgba(56,189,248,.26) 53% 72%, rgba(15,76,129,.17) 72% 88%, transparent 88%);
        }
        .tenant-brand-highlight--soft-pill {
            padding: .02em .18em .08em;
            border-radius: .35em;
            background: linear-gradient(135deg, rgba(224,246,255,.92), rgba(246,252,255,.98));
            box-shadow: inset 0 0 0 1px rgba(15,76,129,.10);
        }
        .tenant-brand-highlight--brush-underline {
            background: linear-gradient(177deg, transparent 0 72%, rgba(56,189,248,.34) 72% 80%, rgba(15,76,129,.21) 80% 87%, transparent 87%);
        }
        .tenant-brand-highlight--corner-ribbon {
            padding-inline: .13em;
            background: linear-gradient(102deg, rgba(56,189,248,.20) 0 78%, rgba(15,76,129,.13) 78% 91%, transparent 91%);
        }
        .tenant-brand-highlight--double-underline {
            background:
                linear-gradient(177deg, transparent 0 76%, rgba(56,189,248,.34) 76% 83%, transparent 83%),
                linear-gradient(181deg, transparent 0 88%, rgba(15,76,129,.22) 88% 94%, transparent 94%);
        }
        .tenant-brand-highlight--blob-glow {
            padding-inline: .14em;
            background: radial-gradient(ellipse at 50% 68%, rgba(56,189,248,.24) 0 48%, rgba(56,189,248,.10) 66%, transparent 72%);
        }
        .tenant-brand-highlight--diagonal-marker {
            padding-inline: .12em;
            background: linear-gradient(168deg, transparent 0 28%, rgba(56,189,248,.23) 29% 61%, rgba(15,76,129,.14) 62% 75%, transparent 76%);
        }
        .tenant-brand-highlight--clean-accent {
            background: linear-gradient(180deg, transparent 0 81%, rgba(15,76,129,.34) 81% 88%, rgba(56,189,248,.23) 88% 93%, transparent 93%);
        }
        .kt-subtitle { color: var(--kt-muted); font-size: 17px; font-weight: 400; line-height: 1.65; max-width: 700px; margin: 0; }
        .kt-trust { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 26px; }
        .kt-trust-item { border: 0; background: rgba(232,245,251,.82); border-radius: 999px; padding: 7px 11px; color: var(--kt-primary-strong); font-size: 13px; font-weight: 500; line-height: 1.3; }
        .kt-access { position: relative; padding: 30px; display: flex; flex-direction: column; gap: 20px; }
        .kt-access h2 { margin: 0; font-size: 22px; font-weight: 700; line-height: 1.35; }
        .kt-access-list { display: grid; gap: 14px; margin: 0; padding: 0; list-style: none; }
        .kt-access-list li { position: relative; padding-left: 28px; color: var(--kt-muted); line-height: 1.55; }
        .kt-access-list li:before { content: "\2713"; position: absolute; top: 1px; left: 0; width: 19px; height: 19px; border-radius: 999px; background: #dcfce7; color: #166534; font-size: 12px; font-weight: 700; line-height: 19px; text-align: center; }
        .kt-access-arrow { align-self: flex-end; width: 72px; height: 30px; margin: -8px 8px -10px 0; color: rgba(15,76,129,.43); transform: rotate(-3deg); }
        .kt-access-arrow svg { display: block; width: 100%; height: 100%; fill: none; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2; }
        .kt-access .kt-btn { width: 100%; margin-top: 4px; }
        .kt-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; margin-top: 20px; }
        .kt-grid .kt-panel { background: rgba(255,255,255,.78); border-color: rgba(196,218,232,.58); box-shadow: 0 7px 22px rgba(15,76,129,.045); }
        .kt-card { position: relative; overflow: hidden; padding: 24px 22px 22px; min-height: 150px; display: flex; flex-direction: column; justify-content: space-between; }
        .kt-card:before { content: ""; position: absolute; top: 0; left: 22px; width: 42px; height: 3px; border-radius: 0 0 3px 3px; background: linear-gradient(90deg, var(--kt-primary), var(--kt-accent)); }
        .kt-card h3 { margin: 0 0 10px; font-size: 19px; font-weight: 600; line-height: 1.35; }
        .kt-card p { margin: 0; color: var(--kt-muted); line-height: 1.6; }
        .kt-card .kt-btn { margin-top: 18px; align-self: flex-start; }
        .kt-footer { margin-top: 38px; padding: 0 0 28px; color: var(--kt-muted); text-align: center; font-size: 14px; }
        @media (max-width: 920px) {
            .tenant-home-decor-icon,
            .kt-access-arrow { display: none; }
            .tenant-home-stage:before { top: 520px; height: 220px; opacity: .65; }
            .kt-hero { grid-template-columns: 1fr; }
            .kt-grid { grid-template-columns: 1fr; }
            .kt-hero-main { padding: 32px; }
            .tenant-home-title { font-size: clamp(32px, 7vw, 44px); line-height: 1.12; }
        }
        @media (max-width: 640px) {
            .kt-main:before { top: 40px; left: -180px; opacity: .58; }
            .kt-main:after { top: 430px; right: -190px; opacity: .45; }
            .tenant-home-stage:before { top: 610px; height: 180px; opacity: .48; }
            .kt-header { position: static; backdrop-filter: none; }
            .kt-container { width: min(100% - 22px, 1180px); }
            .kt-nav { min-height: 0; flex-direction: column; align-items: stretch; gap: 12px; padding: 14px 0 13px; }
            .kt-brand { min-height: 36px; gap: 10px; }
            .kt-brand img { max-height: 36px; max-width: 150px; }
            .kt-brand-mark { width: 36px; height: 36px; border-radius: 11px; }
            .kt-brand-name { font-size: 19px; }
            .kt-nav .kt-actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); width: 100%; gap: 8px; }
            .kt-nav .kt-btn { width: 100%; min-height: 42px; padding: 9px 10px; font-size: 14px; }
            .kt-main { padding-top: 20px; }
            .kt-hero { gap: 16px; }
            .kt-panel { border-radius: 16px; }
            .kt-hero-main {
                padding: 24px;
                background: linear-gradient(145deg, rgba(255,255,255,.99) 0%, rgba(247,252,255,.96) 100%);
            }
            .kt-kicker { padding: 6px 10px; margin-bottom: 16px; font-size: 12px; }
            .tenant-home-title { font-size: clamp(32px, 9vw, 36px); line-height: 1.16; letter-spacing: -0.01em; margin-bottom: 16px; }
            .tenant-home-title-prefix { margin-bottom: 6px; font-size: .9em; white-space: nowrap; }
            .tenant-brand-highlight { line-height: 1.2; padding-inline: .06em; }
            .tenant-brand-highlight--soft-pill,
            .tenant-brand-highlight--corner-ribbon,
            .tenant-brand-highlight--blob-glow,
            .tenant-brand-highlight--diagonal-marker { padding-inline: .1em; }
            .kt-subtitle { font-size: 15px; line-height: 1.58; }
            .kt-trust { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin-top: 20px; }
            .kt-trust-item { padding: 7px 6px; font-size: 12px; text-align: center; }
            .kt-access { padding: 24px; gap: 18px; }
            .kt-access h2 { font-size: 24px; line-height: 1.3; }
            .kt-access-list { gap: 12px; }
            .kt-access-list li { padding-left: 27px; font-size: 15px; line-height: 1.5; }
            .kt-access-list li:before { width: 18px; height: 18px; line-height: 18px; }
            .kt-access .kt-btn { min-height: 46px; margin-top: 2px; }
            .kt-grid { gap: 14px; margin-top: 16px; }
            .kt-card { min-height: 0; padding: 22px; }
            .kt-card h3 { font-size: 19px; }
            .kt-card p { font-size: 15px; line-height: 1.55; }
            .kt-footer { margin-top: 32px; padding-bottom: 24px; }
        }
        @media (max-width: 380px) {
            .kt-container { width: calc(100% - 20px); }
            .kt-nav .kt-btn { padding-inline: 8px; font-size: 13px; }
            .kt-hero-main,
            .kt-access,
            .kt-card { padding-left: 20px; padding-right: 20px; }
            .kt-access h2 { font-size: 22px; }
            .kt-card { min-height: 0; }
        }
    </style>
</head>
<body>
<div class="kt-shell">
    <header class="kt-header">
        <div class="kt-container kt-nav">
            <a class="kt-brand" href="<?php echo html_escape(site_url()); ?>">
                <?php if ($logoUrl !== '') { ?>
                    <img src="<?php echo html_escape($logoUrl); ?>" alt="<?php echo html_escape($companyName); ?>">
                <?php } else { ?>
                    <span class="kt-brand-mark"><?php echo html_escape(function_exists('mb_substr') ? mb_substr($companyName, 0, 1, 'UTF-8') : substr($companyName, 0, 1)); ?></span>
                <?php } ?>
                <span class="kt-brand-name"><?php echo html_escape($companyName); ?></span>
            </a>
            <div class="kt-actions">
                <a class="kt-btn" href="<?php echo html_escape($customerPortalUrl); ?>">Cổng khách hàng</a>
                <a class="kt-btn primary" href="<?php echo html_escape($crmLoginUrl); ?>">Đăng nhập CRM</a>
            </div>
        </div>
    </header>

    <main class="kt-main">
        <div class="kt-container tenant-home-stage">
            <div class="tenant-home-decor" aria-hidden="true">
                <span class="tenant-home-decor-icon tenant-home-decor-icon--shield">
                    <svg viewBox="0 0 24 24"><path d="M12 3 19 6v5c0 4.4-2.8 7.7-7 10-4.2-2.3-7-5.6-7-10V6l7-3Z"/><path d="m9 12 2 2 4-5"/></svg>
                </span>
                <span class="tenant-home-decor-icon tenant-home-decor-icon--document">
                    <svg viewBox="0 0 24 24"><path d="M6 3h8l4 4v14H6Z"/><path d="M14 3v5h5M9 13h6M9 17h5"/></svg>
                </span>
                <span class="tenant-home-decor-icon tenant-home-decor-icon--receipt">
                    <svg viewBox="0 0 24 24"><path d="M6 3h12v18l-2-1.5L14 21l-2-1.5L10 21l-2-1.5L6 21Z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
                </span>
                <span class="tenant-home-decor-icon tenant-home-decor-icon--chat">
                    <svg viewBox="0 0 24 24"><path d="M4 5h16v11H9l-5 4Z"/><path d="M8 10h8M8 13h5"/></svg>
                </span>
                <span class="tenant-home-decor-icon tenant-home-decor-icon--check">
                    <svg viewBox="0 0 24 24"><path d="m7 12 3 3 7-7"/></svg>
                </span>
            </div>
            <section class="kt-hero">
                <div class="kt-panel kt-hero-main">
                    <div class="kt-kicker">Cổng thông tin khách hàng</div>
                    <h1 class="tenant-home-title">
                        <span class="tenant-home-title-prefix">Chào mừng đến với</span>
                        <span class="tenant-brand-highlight tenant-brand-highlight--<?php echo html_escape($brandHighlightVariant); ?>"><?php echo html_escape($companyName); ?></span>
                    </h1>
                    <p class="kt-subtitle">Cổng thông tin dành cho khách hàng và đối tác của <?php echo html_escape($companyName); ?>, giúp theo dõi yêu cầu, tài liệu và giao dịch tại một nơi.</p>
                    <div class="kt-trust" aria-label="Lợi ích cổng khách hàng">
                        <div class="kt-trust-item">Bảo mật truy cập</div>
                        <div class="kt-trust-item">Theo dõi yêu cầu</div>
                        <div class="kt-trust-item">Quản lý giao dịch</div>
                        <div class="kt-trust-item">Hỗ trợ tập trung</div>
                    </div>
                </div>

                <aside class="kt-panel kt-access">
                    <h2>Trong cổng khách hàng, bạn có thể</h2>
                    <ul class="kt-access-list">
                        <li>Theo dõi yêu cầu hỗ trợ</li>
                        <li>Xem tài liệu hoặc hợp đồng được chia sẻ</li>
                        <li>Kiểm tra hóa đơn và giao dịch liên quan</li>
                        <li>Trao đổi tập trung với doanh nghiệp</li>
                    </ul>
                    <div class="kt-access-arrow" aria-hidden="true">
                        <svg viewBox="0 0 72 30"><path d="M4 5c18 0 24 5 31 11 6 5 13 7 27 6"/><path d="m55 16 8 6-9 4"/></svg>
                    </div>
                    <a class="kt-btn primary" href="<?php echo html_escape($customerPortalUrl); ?>">Mở cổng khách hàng</a>
                </aside>
            </section>

            <section class="kt-grid">
                <article class="kt-panel kt-card">
                    <div><h3>Thông tin được sắp xếp rõ ràng</h3><p>Yêu cầu, tài liệu và phản hồi được tập trung theo từng khách hàng, giúp việc theo dõi thuận tiện hơn.</p></div>
                </article>
                <article class="kt-panel kt-card">
                    <div><h3>Trao đổi có lịch sử</h3><p>Các cập nhật quan trọng được lưu lại, hạn chế thất lạc thông tin trong quá trình làm việc.</p></div>
                </article>
                <article class="kt-panel kt-card">
                    <div><h3>Truy cập an toàn</h3><p>Khách hàng đăng nhập bằng tài khoản riêng để xem các thông tin liên quan đến mình.</p></div>
                </article>
            </section>
        </div>
    </main>

    <footer class="kt-footer">
        <div class="kt-container">Powered by Khách Tốt</div>
    </footer>
</div>
</body>
</html>
