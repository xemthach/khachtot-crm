<?php

defined('BASEPATH') or exit('No direct script access allowed');

$brandName = trim((string) ($brand_name ?? 'Khách Tốt CRM'));
$landlordLogoUrl = trim((string) ($landlord_logo_url ?? ''));
$companyName = trim((string) ($company_name ?? ''));
$badge = trim((string) ($badge ?? 'Đang xử lý'));
$title = trim((string) ($title ?? 'Không gian làm việc chưa sẵn sàng'));
$message = trim((string) ($message ?? 'Vui lòng thử lại sau ít phút.'));
$supportText = trim((string) ($support_text ?? ''));
$tone = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) ($tone ?? 'info')));
$primaryAction = is_array($primary_action ?? null) ? $primary_action : [];
$secondaryAction = is_array($secondary_action ?? null) ? $secondary_action : [];
$steps = is_array($steps ?? null) ? $steps : [];

?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($title); ?></title>
    <style>
        :root {
            --kt-blue: #0f5c91;
            --kt-blue-dark: #0b3158;
            --kt-cyan: #7dd3fc;
            --kt-mint: #bbf7d0;
            --kt-text: #102033;
            --kt-muted: #607089;
            --kt-border: rgba(15, 92, 145, .14);
            --kt-shadow: 0 28px 80px rgba(15, 49, 88, .14);
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
        }

        body {
            font-family: "Be Vietnam Pro", "Inter", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--kt-text);
            background:
                radial-gradient(circle at 14% 18%, rgba(125, 211, 252, .34), transparent 34%),
                radial-gradient(circle at 88% 14%, rgba(187, 247, 208, .24), transparent 30%),
                linear-gradient(180deg, #eaf8ff 0%, #f7fbfd 46%, #fff 100%);
        }

        .kt-status-page {
            position: relative;
            min-height: 100vh;
            padding: 28px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .kt-status-page::before,
        .kt-status-page::after {
            content: "";
            position: absolute;
            border-radius: 999px;
            pointer-events: none;
            z-index: 0;
        }

        .kt-status-page::before {
            width: 240px;
            height: 240px;
            left: -80px;
            bottom: 8%;
            background: rgba(15, 92, 145, .08);
            filter: blur(8px);
        }

        .kt-status-page::after {
            width: 180px;
            height: 180px;
            right: -56px;
            top: 18%;
            background: rgba(125, 211, 252, .18);
            filter: blur(6px);
        }

        .kt-status-shell {
            position: relative;
            z-index: 1;
            width: min(100%, 920px);
        }

        .kt-status-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 22px;
            font-weight: 800;
            color: var(--kt-blue-dark);
        }

        .kt-status-brand img {
            display: block;
            max-height: 42px;
            max-width: 220px;
            width: auto;
            object-fit: contain;
        }

        .kt-status-brand-text {
            display: inline-flex;
            align-items: center;
            min-height: 42px;
            font-size: 17px;
        }

        .kt-status-card {
            background: rgba(255, 255, 255, .94);
            border: 1px solid var(--kt-border);
            border-radius: 30px;
            box-shadow: var(--kt-shadow);
            padding: 42px;
            overflow: hidden;
        }

        .kt-status-content {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(260px, .65fr);
            gap: 32px;
            align-items: center;
        }

        .kt-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
            font-size: 13px;
            font-weight: 700;
        }

        .kt-status-badge::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: currentColor;
        }

        .kt-status-card.is-warning .kt-status-badge {
            background: #fef3c7;
            color: #92400e;
        }

        .kt-status-card.is-danger .kt-status-badge {
            background: #fee2e2;
            color: #991b1b;
        }

        .kt-status-card.is-muted .kt-status-badge {
            background: #eef2f7;
            color: #475569;
        }

        h1 {
            margin: 22px 0 16px;
            color: #0b1225;
            font-size: clamp(32px, 5vw, 56px);
            line-height: 1.08;
            letter-spacing: -.025em;
            font-weight: 760;
        }

        .kt-status-company {
            color: var(--kt-blue);
            position: relative;
            display: inline;
            background: linear-gradient(transparent 58%, rgba(125, 211, 252, .35) 58%, rgba(125, 211, 252, .35) 88%, transparent 88%);
            box-decoration-break: clone;
            -webkit-box-decoration-break: clone;
            padding: 0 .04em;
        }

        .kt-status-message {
            margin: 0;
            color: var(--kt-muted);
            font-size: 17px;
            line-height: 1.75;
            max-width: 680px;
        }

        .kt-status-support {
            margin: 18px 0 0;
            color: #42536d;
            font-size: 14px;
            line-height: 1.65;
        }

        .kt-status-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 26px;
        }

        .kt-status-btn {
            min-height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 18px;
            border-radius: 13px;
            font-weight: 750;
            text-decoration: none;
            border: 1px solid rgba(15, 92, 145, .18);
            color: var(--kt-blue-dark);
            background: #fff;
        }

        .kt-status-btn.is-primary {
            color: #fff;
            border-color: var(--kt-blue);
            background: linear-gradient(135deg, #0f5c91, #155d95);
            box-shadow: 0 16px 34px rgba(15, 92, 145, .22);
        }

        .kt-status-steps {
            display: grid;
            gap: 12px;
            padding: 20px;
            border: 1px solid rgba(15, 92, 145, .12);
            border-radius: 22px;
            background:
                linear-gradient(135deg, rgba(234, 248, 255, .86), rgba(255, 255, 255, .92));
        }

        .kt-status-step {
            display: grid;
            grid-template-columns: 28px 1fr;
            gap: 10px;
            align-items: center;
            color: #304258;
            font-size: 14px;
            font-weight: 650;
        }

        .kt-status-step-mark {
            width: 28px;
            height: 28px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            color: var(--kt-blue);
            background: #fff;
            border: 1px solid rgba(15, 92, 145, .14);
        }

        .kt-status-note {
            margin-top: 16px;
            color: #6b7a91;
            font-size: 13px;
            line-height: 1.55;
        }

        @media (max-width: 760px) {
            .kt-status-page {
                align-items: flex-start;
                padding: 24px 16px 32px;
            }

            .kt-status-brand {
                justify-content: flex-start;
                margin-bottom: 16px;
            }

            .kt-status-card {
                padding: 26px 22px;
                border-radius: 24px;
            }

            .kt-status-content {
                grid-template-columns: 1fr;
                gap: 24px;
            }

            h1 {
                margin-top: 18px;
                font-size: clamp(32px, 10vw, 40px);
                line-height: 1.16;
            }

            .kt-status-message {
                font-size: 15.5px;
                line-height: 1.7;
            }

            .kt-status-actions {
                display: grid;
                grid-template-columns: 1fr;
            }

            .kt-status-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <main class="kt-status-page">
        <section class="kt-status-shell" aria-labelledby="tenant-status-title">
            <div class="kt-status-brand">
                <?php if ($landlordLogoUrl !== '') { ?>
                    <img src="<?php echo html_escape($landlordLogoUrl); ?>" alt="<?php echo html_escape($brandName); ?>">
                <?php } ?>
                <span class="kt-status-brand-text"><?php echo html_escape($brandName); ?></span>
            </div>

            <div class="kt-status-card is-<?php echo html_escape($tone); ?>">
                <div class="kt-status-content">
                    <div>
                        <span class="kt-status-badge"><?php echo html_escape($badge); ?></span>
                        <h1 id="tenant-status-title">
                            <?php echo html_escape($title); ?>
                            <?php if ($companyName !== '') { ?>
                                <br><span class="kt-status-company"><?php echo html_escape($companyName); ?></span>
                            <?php } ?>
                        </h1>
                        <p class="kt-status-message"><?php echo html_escape($message); ?></p>
                        <?php if ($supportText !== '') { ?>
                            <p class="kt-status-support"><?php echo html_escape($supportText); ?></p>
                        <?php } ?>

                        <div class="kt-status-actions">
                            <?php if (!empty($primaryAction['url']) && !empty($primaryAction['label'])) { ?>
                                <a class="kt-status-btn is-primary" href="<?php echo html_escape($primaryAction['url']); ?>">
                                    <?php echo html_escape($primaryAction['label']); ?>
                                </a>
                            <?php } ?>
                            <?php if (!empty($secondaryAction['url']) && !empty($secondaryAction['label'])) { ?>
                                <a class="kt-status-btn" href="<?php echo html_escape($secondaryAction['url']); ?>">
                                    <?php echo html_escape($secondaryAction['label']); ?>
                                </a>
                            <?php } ?>
                        </div>
                    </div>

                    <aside>
                        <div class="kt-status-steps" aria-label="Tiến trình khởi tạo">
                            <?php foreach ($steps as $index => $step) { ?>
                                <div class="kt-status-step">
                                    <span class="kt-status-step-mark"><?php echo (int) $index + 1; ?></span>
                                    <span><?php echo html_escape((string) $step); ?></span>
                                </div>
                            <?php } ?>
                        </div>
                        <p class="kt-status-note">Trang này chỉ hiển thị trạng thái công khai, không chứa thông tin kỹ thuật hoặc dữ liệu nội bộ.</p>
                    </aside>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
