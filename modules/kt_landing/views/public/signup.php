<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($title ?? 'Đăng ký CRM Khách Tốt'); ?></title>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/kt_public_typography.css'); ?>">
    <style>
        :root{--bg:#f4fbff;--text:#0b1f3a;--muted:#5d6d82;--line:rgba(18,90,146,.14);--primary:#125a92;--primary-dark:#0d4775;--primary-soft:#eef8ff;--success:#16a34a;--shadow:0 24px 70px rgba(20,61,96,.10)}
        *{box-sizing:border-box}
        body{margin:0;font-family:"Be Vietnam Pro","Inter",var(--kt-font-sans),system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:var(--text);background:radial-gradient(circle at 12% 6%,rgba(125,211,252,.24),transparent 28%),linear-gradient(180deg,#eaf8ff 0%,#f7fbfd 46%,#fff 100%);-webkit-font-smoothing:antialiased}
        .wrap{max-width:1180px;margin:0 auto;padding:26px 20px 56px}
        .top{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px}
        .top a{display:inline-flex;align-items:center;min-height:42px;padding:9px 14px;border:1px solid rgba(18,90,146,.16);border-radius:12px;background:rgba(255,255,255,.78);box-shadow:0 10px 24px rgba(20,61,96,.045);text-decoration:none;color:var(--primary);font-weight:700}
        .shell{position:relative;overflow:hidden;background:#fff;border:1px solid var(--line);border-radius:28px;box-shadow:var(--shadow)}
        .shell::before{content:"";position:absolute;right:-140px;top:-130px;width:360px;height:360px;border-radius:50%;background:rgba(125,211,252,.16);filter:blur(8px);pointer-events:none}
        .head{position:relative;padding:34px 38px 26px;border-bottom:1px solid var(--line);background:linear-gradient(180deg,#fff,#f8fcff)}
        .eyebrow{display:inline-flex;align-items:center;gap:8px;background:#dff7eb;color:#176b4f;padding:7px 13px;border-radius:999px;font-size:12px;font-weight:800}
        .head h1{margin:16px 0 12px;font-size:clamp(34px,4vw,52px);line-height:1.12;font-family:var(--kt-font-heading);letter-spacing:-.025em}
        .signup-marker{position:relative;z-index:1;display:inline-block;padding:0 .06em;white-space:nowrap}
        .signup-marker::before{content:"";position:absolute;z-index:-1;left:-.04em;right:-.06em;bottom:.05em;height:.42em;border-radius:999px;background:linear-gradient(90deg,rgba(86,203,245,.30),rgba(68,184,230,.42),rgba(115,215,239,.24));transform:rotate(-1deg)}
        .head p{margin:0;max-width:780px;color:#475569;font-size:17px;line-height:1.75}
        .stepbar{position:relative;display:grid;grid-template-columns:repeat(3,1fr);gap:12px;padding:20px 38px;border-bottom:1px solid var(--line);background:#fbfdff}
        .step{display:flex;align-items:center;gap:11px;padding:13px 15px;border:1px solid rgba(18,90,146,.13);border-radius:16px;color:var(--muted);background:#fff;min-width:0;box-shadow:0 8px 22px rgba(20,61,96,.035)}
        .step b{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:999px;background:#e2e8f0;color:#334155;font-size:12px;flex:0 0 auto}
        .step strong{display:block;font-size:14px;color:#334155}
        .step small{display:block;font-size:12px;color:var(--muted)}
        .step.active{border-color:rgba(18,90,146,.36);background:linear-gradient(180deg,#eef8ff,#fff);box-shadow:0 14px 30px rgba(18,90,146,.10)}
        .step.active b{background:var(--primary);color:#fff}
        .step.active strong{color:var(--primary)}
        .content{position:relative;display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:20px;padding:28px;align-items:start}
        .panel{border:1px solid rgba(18,90,146,.13);border-radius:22px;padding:20px;background:#fff;min-width:0;box-shadow:0 16px 40px rgba(20,61,96,.055)}
        .panel h3{margin:0 0 10px;font-size:24px;line-height:1.2;font-family:var(--kt-font-heading)}
        .panel-lead{margin:0 0 16px;color:#475569;font-size:var(--kt-text-md);line-height:1.65}
        .hidden{display:none}
        .plans{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;align-items:stretch}
        .plan-card{position:relative;border:1px solid rgba(18,90,146,.13);border-radius:22px;padding:20px;cursor:pointer;background:linear-gradient(180deg,#fff,#fbfdff);box-shadow:0 13px 34px rgba(20,61,96,.06);display:flex;flex-direction:column;min-width:0;min-height:100%;transition:border-color .18s ease,box-shadow .18s ease,transform .18s ease}
        .plan-card:hover{transform:translateY(-1px);border-color:rgba(18,90,146,.28)}
        .plan-card.active{border-color:var(--primary);box-shadow:0 24px 58px rgba(18,90,146,.18)}
        .plan-card.is-trial{background:linear-gradient(180deg,#fcfdff,#f8fafc);border-color:#e2e8f0;box-shadow:none}
        .plan-card.is-trial .plan-name,.plan-card.is-trial .price-box strong{color:#334155}
        .plan-card input{display:none}
        .plan-badge{position:absolute;right:16px;top:16px;border-radius:999px;padding:5px 10px;font-size:11px;font-weight:800;background:#dff7eb;color:#176b4f;border:1px solid rgba(22,101,52,.10);max-width:calc(100% - 32px);white-space:nowrap;text-overflow:ellipsis;overflow:hidden}
        .plan-name{font-size:clamp(22px,2vw,28px);font-weight:800;line-height:1.1;padding-right:112px;font-family:var(--kt-font-heading);letter-spacing:-.01em;overflow-wrap:anywhere}
        .plan-bestfor{margin:6px 0 14px;color:#475569;min-height:44px;line-height:1.55;font-size:var(--kt-text-sm);display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
        .price-stack{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,170px),1fr));gap:10px;align-items:stretch;min-width:0}
        .price-box{border:1px solid var(--line);border-radius:14px;padding:12px;background:#f8fbff;min-width:0;max-width:100%;display:flex;flex-direction:column;justify-content:space-between;overflow:hidden}
        .price-box.setup{background:#fff7ed;border-color:#fed7aa}
        .price-box span{display:block;font-size:11px;color:var(--muted);font-weight:800;text-transform:uppercase;letter-spacing:.06em}
        .price-box strong,.summary-box strong,.review-total strong{display:block;max-width:100%;margin-top:6px;font-size:clamp(22px,1.55vw,27px);line-height:1.08;font-family:var(--kt-font-heading);letter-spacing:-.01em;font-variant-numeric:tabular-nums;white-space:normal;overflow-wrap:anywhere;word-break:normal;hyphens:none}
        .price-box small{display:block;margin-top:6px;color:var(--muted);font-size:var(--kt-text-sm);line-height:1.35}
        .plan-core{margin-top:14px;min-width:0}
        .plan-core b,.plan-tech b{display:block;margin-bottom:8px;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.08em}
        .core-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
        .core-grid span{display:flex;align-items:flex-start;gap:8px;border:1px solid #dbe4ef;border-radius:12px;padding:10px 12px;background:#fff;font-size:14px;font-weight:700;color:#334155;line-height:1.45;min-width:0;overflow-wrap:anywhere}
        .core-grid span::before{content:"+";color:var(--success);font-weight:900}
        .plan-footnote{margin-top:14px;padding:12px 14px;border:1px solid #dbe4ef;border-radius:14px;background:#fbfdff;color:#475569;font-size:13px;line-height:1.55}
        .plan-tech{margin-top:14px}
        .plan-tech details{border-top:1px dashed var(--line);padding-top:10px}
        .plan-tech summary{cursor:pointer;font-weight:700;color:var(--primary)}
        .plan-tech ul{list-style:none;padding:0;margin:10px 0 0;display:grid;gap:6px;min-width:0}
        .plan-tech li{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;font-size:13px;color:#475569;align-items:start}
        .plan-tech li span,.plan-tech li strong{min-width:0;overflow-wrap:anywhere}
        .plan-cta-row{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:auto;padding-top:16px}
        .plan-chip{display:inline-flex;align-items:center;gap:8px;background:#eef6ff;border:1px solid #cfe0f3;border-radius:999px;padding:8px 10px;font-size:12px;font-weight:700;color:#0f4c81;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .grid{display:grid;gap:14px}
        .grid.two{grid-template-columns:repeat(2,minmax(0,1fr))}
        label{display:block;font-size:13px;font-weight:700;margin-bottom:6px}
        input{width:100%;height:46px;border:1px solid var(--line);border-radius:12px;padding:0 12px;font-size:14px;background:#fff}
        input:focus{outline:none;border-color:#93c5fd;box-shadow:0 0 0 4px rgba(59,130,246,.12)}
        .field-note{margin-top:6px;font-size:12px;color:var(--muted);line-height:1.5;overflow-wrap:anywhere}
        .hint-ok{color:var(--success);font-size:12px}
        .hint-err{color:#dc2626;font-size:12px}
        .trust-strip{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:14px}
        .trust-item{border:1px solid var(--line);border-radius:14px;padding:12px;background:#fbfdff;min-width:0}
        .trust-item strong{display:block;font-size:14px;margin-bottom:4px}
        .trust-item p{margin:0;color:#64748b;font-size:12px;line-height:1.55}
        .review{display:grid;gap:12px}
        .review-block{border:1px solid var(--line);border-radius:16px;padding:14px;background:#fbfdff;min-width:0}
        .review-block h4{margin:0 0 10px;font-size:15px}
        .review-line{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;padding:7px 0;border-bottom:1px dashed #dbe4ef;align-items:start}
        .review-line:last-child{border-bottom:0}
        .review-line span{color:#475569}
        .review-line strong{text-align:right;min-width:0;white-space:normal;overflow-wrap:normal;word-break:normal;hyphens:none}
        .review-total{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:14px 16px;border-radius:16px;background:linear-gradient(135deg,#0f4c81,#2563eb);color:#fff;flex-wrap:wrap}
        .review-total span{font-size:13px;opacity:.9}
        .actions{display:flex;justify-content:space-between;gap:10px;margin-top:18px}
        .btn{height:46px;border:0;border-radius:12px;padding:0 16px;font-weight:800;cursor:pointer}
        .btn.secondary{background:#eef2f7;color:#1e293b}
        .btn.primary{background:var(--primary);color:#fff;box-shadow:0 14px 28px rgba(15,76,129,.22)}
        .btn.primary:hover{background:var(--primary-dark)}
        .aside{position:sticky;top:18px;align-self:start}
        .summary-top{padding-bottom:14px;border-bottom:1px solid var(--line)}
        .summary-top h3{margin:0 0 6px;font-size:22px;line-height:1.2;font-family:var(--kt-font-heading)}
        .summary-top p{margin:0;color:#64748b;font-size:var(--kt-text-sm);line-height:1.55}
        .summary-price{display:grid;gap:10px;margin-top:14px}
        .summary-box{border:1px solid var(--line);border-radius:14px;padding:12px;background:#f8fbff;min-width:0}
        .summary-box.setup{background:#fff7ed;border-color:#fed7aa}
        .summary-box span{display:block;font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:800}
        .summary-box strong{font-size:clamp(18px,1.55vw,24px)}
        .summary-list{display:grid;gap:10px;margin-top:14px}
        .summary-row{display:grid;grid-template-columns:90px minmax(0,1fr);gap:10px;align-items:flex-start}
        .summary-row span{font-size:12px;color:var(--muted)}
        .summary-row strong{font-size:14px;text-align:right;min-width:0;white-space:normal;overflow-wrap:normal;word-break:normal;hyphens:none}
        .summary-total{margin-top:14px;padding-top:14px;border-top:1px dashed var(--line)}
        .summary-total .summary-row strong{font-size:clamp(24px,2.4vw,30px);color:var(--primary);line-height:1.05;font-family:var(--kt-font-heading);font-variant-numeric:tabular-nums}
        .summary-pill{display:inline-flex;align-items:center;gap:8px;margin-top:12px;background:#eef6ff;border:1px solid #cfe0f3;border-radius:999px;padding:8px 12px;font-size:12px;font-weight:700;color:var(--primary);max-width:100%;overflow-wrap:anywhere;white-space:normal}
        .summary-pill::before{content:"i";display:inline-flex;align-items:center;justify-content:center;width:17px;height:17px;border-radius:50%;background:var(--primary);color:#fff;font-size:11px;font-weight:800;flex:0 0 auto}
        @media (max-width:1280px){.content{grid-template-columns:minmax(0,1fr) 330px}.plan-name{padding-right:96px}}
        @media (max-width:1024px){.content{grid-template-columns:1fr}.aside{position:static;order:2}.plans{grid-template-columns:repeat(2,minmax(0,1fr))}.trust-strip{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media (max-width:720px){
            .wrap{padding:14px 12px 34px}
            .top a{flex:1;justify-content:center}
            .head{padding:24px 18px 18px}
            .head h1{font-size:32px}
            .signup-marker{white-space:normal}
            .stepbar{grid-template-columns:1fr;padding:14px 16px}
            .content{padding:14px}
            .panel{padding:14px}
            .grid.two,.price-stack,.core-grid,.trust-strip,.plans{grid-template-columns:1fr}
            .actions{flex-direction:column}
            .btn{width:100%}
            .plan-name{font-size:22px;padding-right:0}
            .plan-badge{position:static;display:inline-flex;margin-bottom:12px}
            .plan-cta-row{flex-direction:column;align-items:flex-start}
            .summary-row,.review-line{grid-template-columns:1fr}
            .summary-row strong,.review-line strong{text-align:left}
            .summary-total .summary-row strong{font-size:26px}
            .summary-box strong,.price-box strong{font-size:22px}
        }
    </style>
</head>
<body>
<?php
$preferredPlanId = (int) ($preferred_plan_id ?? 0);
$baseDomain = parse_url(site_url(), PHP_URL_HOST);
if (!$baseDomain) {
    $baseDomain = $_SERVER['HTTP_HOST'] ?? 'portal.local';
}
$formatPrice = static function ($price, $currency, $withCycle = '') {
    $amount = (float) $price;
    $currency = strtoupper((string) $currency);
    $formatted = $currency === 'VND'
        ? number_format($amount, 0, ',', '.') . ' VND'
        : number_format($amount, 2, '.', ',') . ' ' . $currency;
    return $withCycle !== '' ? $formatted . '/' . $withCycle : $formatted;
};
?>
<div class="wrap">
    <div class="top">
        <a href="<?php echo site_url(); ?>">&larr; Trang chủ</a>
        <a href="<?php echo site_url('pricing'); ?>">Bảng giá</a>
    </div>
    <div class="shell">
        <div class="head">
            <span class="eyebrow">Đăng ký CRM</span>
            <h1>Đăng ký <span class="signup-marker">CRM Khách Tốt</span></h1>
            <p>Chọn gói dịch vụ, nhập thông tin doanh nghiệp và xác nhận đơn hàng trước khi chuyển sang bước thanh toán.</p>
        </div>
        <div class="stepbar" id="stepbar"></div>
        <form method="post" action="<?php echo site_url('signup'); ?>" id="signupForm">
            <input type="hidden" name="<?php echo html_escape($this->security->get_csrf_token_name()); ?>" value="<?php echo html_escape($this->security->get_csrf_hash()); ?>">
            <input type="hidden" name="signup_ts" value="<?php echo time(); ?>">
            <div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
                <label for="website">Website</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>
            <div class="content">
                <div class="panel">
                    <section data-step="1">
                        <h3>Chọn gói phù hợp</h3>
                        <p class="panel-lead">Chọn gói dịch vụ theo quy mô doanh nghiệp, chi phí triển khai và các ứng dụng cốt lõi đi kèm.</p>
                        <div class="plans">
                            <?php foreach (($public_plans ?? []) as $plan) {
                                $pid = (int) ($plan['id'] ?? 0);
                                $planName = (string) ($plan['plan_name'] ?? 'Plan');
                                $name = (string) ($plan['landing_marketing_title'] ?? '') !== '' ? (string) $plan['landing_marketing_title'] : $planName;
                                $subtitle = trim((string) ($plan['landing_marketing_subtitle'] ?? ''));
                                $badge = trim((string) ($plan['landing_badge_text'] ?? ''));
                                $featured = (int) ($plan['landing_featured'] ?? 0) === 1;
                                $price = (float) ($plan['price_monthly'] ?? $plan['price'] ?? 0);
                                $setupFee = (float) ($plan['setup_fee'] ?? 0);
                                $currency = (string) ($plan['currency'] ?? 'VND');
                                $cycle = strtolower((string) ($plan['billing_cycle'] ?? 'monthly'));
                                $cycleLabelMap = ['monthly' => 'tháng', 'yearly' => 'năm', 'quarterly' => 'quý', 'semiannual' => '6 tháng', 'onetime' => 'một lần'];
                                $cycleLabel = $cycleLabelMap[$cycle] ?? $cycle;
                                $trialDays = (int) ($plan['trial_days'] ?? 0);
                                $moduleCodes = json_decode((string) ($plan['module_json'] ?? '[]'), true);
                                if (!is_array($moduleCodes)) { $moduleCodes = []; }
                                $limitStaff = (int) ($plan['limit_staff'] ?? 0);
                                $limitClients = (int) ($plan['limit_clients'] ?? 0);
                                $limitStorage = (int) ($plan['limit_storage_mb'] ?? 0);
                                $limitInvoices = (int) ($plan['limit_invoices'] ?? 0);
                                $limitApi = (int) ($plan['limit_api_requests_daily'] ?? 0);
                                $limitAutomations = (int) ($plan['limit_automations'] ?? 0);
                                $modulesText = !empty($moduleCodes) ? implode(', ', $moduleCodes) : '';
                                $planKey = strtolower($planName . ' ' . $name);
                                $bestFor = $subtitle;
                                if ($bestFor === '') {
                                    if (strpos($planKey, 'trial') !== false) {
                                        $bestFor = 'Trải nghiệm nền tảng trước khi triển khai chính thức.';
                                    } elseif (strpos($planKey, 'sme mini') !== false || strpos($planKey, 'starter') !== false) {
                                        $bestFor = 'Doanh nghiệp nhỏ bắt đầu số hóa CRM, khách hàng và quy trình bán hàng.';
                                    } elseif (strpos($planKey, 'sme plus') !== false || strpos($planKey, 'standard') !== false) {
                                        $bestFor = 'Doanh nghiệp nhiều phòng ban, nhiều người dùng và quy trình vận hành phức tạp.';
                                    } elseif (strpos($planKey, 'sme') !== false || strpos($planKey, 'basic') !== false) {
                                        $bestFor = 'Doanh nghiệp đang tăng trưởng cần quản lý CRM, kho, hóa đơn và vận hành trên một nền tảng thống nhất.';
                                    } else {
                                        $bestFor = 'Doanh nghiệp cần CRM và vận hành tập trung trên một nền tảng thống nhất.';
                                    }
                                }
                                if ($badge === '') {
                                    if (strpos($planKey, 'trial') !== false) {
                                        $badge = 'Dùng thử';
                                    } elseif (strpos($planKey, 'sme mini') !== false || strpos($planKey, 'starter') !== false) {
                                        $badge = 'Dễ bắt đầu';
                                    } elseif (strpos($planKey, 'sme plus') !== false || strpos($planKey, 'standard') !== false) {
                                        $badge = 'Cho doanh nghiệp mở rộng';
                                    } elseif ($featured || strpos($planKey, 'sme') !== false || strpos($planKey, 'basic') !== false) {
                                        $badge = 'Phổ biến nhất';
                                    }
                                }
                                $coreFeatures = ['CRM', 'Quản lý kho', 'Hóa đơn', 'Thanh toán'];
                                $isChecked = $preferredPlanId > 0 ? $preferredPlanId === $pid : $featured;
                                $isTrialPlan = strpos($planKey, 'trial') !== false;
                                ?>
                                <label class="plan-card<?php echo $isChecked ? ' active' : ''; ?><?php echo $isTrialPlan ? ' is-trial' : ''; ?>" data-plan="<?php echo $pid; ?>">
                                    <input type="radio" name="plan_id" value="<?php echo $pid; ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                                    <?php if ($badge !== '' || $featured) { ?><span class="plan-badge"><?php echo html_escape($badge !== '' ? $badge : 'Phổ biến nhất'); ?></span><?php } ?>
                                    <div class="plan-name"><?php echo html_escape($name); ?></div>
                                    <div class="plan-bestfor"><?php echo html_escape($bestFor); ?></div>
                                    <div class="price-stack">
                                        <div class="price-box">
                                            <span>Giá thuê bao</span>
                                            <strong><?php echo html_escape($formatPrice($price, $currency)); ?></strong>
                                            <small>/<?php echo html_escape($cycleLabel); ?></small>
                                        </div>
                                        <div class="price-box setup">
                                            <span>Phí triển khai</span>
                                            <strong><?php echo html_escape($formatPrice($setupFee, $currency)); ?></strong>
                                            <small>Một lần</small>
                                        </div>
                                    </div>
                                    <div class="plan-core">
                                        <b>Ứng dụng cốt lõi</b>
                                        <div class="core-grid">
                                            <?php foreach ($coreFeatures as $feature) { ?>
                                            <span><?php echo html_escape($feature); ?></span>
                                            <?php } ?>
                                        </div>
                                    </div>
                                    <div class="plan-footnote">Phí triển khai bao gồm khởi tạo hệ thống, cấu hình ban đầu, phân quyền cơ bản, hỗ trợ triển khai và kiểm tra trước khi bàn giao.</div>
                                    <div class="plan-tech">
                                        <b>Thông tin chi tiết</b>
                                        <details>
                                            <summary>Xem giới hạn sử dụng</summary>
                                            <ul>
                                                <li><span>Người dùng</span><strong><?php echo $limitStaff > 0 ? number_format($limitStaff) : 'Không giới hạn'; ?></strong></li>
                                                <li><span>Khách hàng</span><strong><?php echo $limitClients > 0 ? number_format($limitClients) : 'Không giới hạn'; ?></strong></li>
                                                <li><span>Dung lượng</span><strong><?php echo $limitStorage > 0 ? number_format($limitStorage / 1024, 1) . ' GB' : 'Không giới hạn'; ?></strong></li>
                                                <li><span>Hóa đơn</span><strong><?php echo $limitInvoices > 0 ? number_format($limitInvoices) : 'Không giới hạn'; ?></strong></li>
                                                <li><span>Tích hợp mỗi ngày</span><strong><?php echo $limitApi > 0 ? number_format($limitApi) . '/ngày' : 'Không giới hạn'; ?></strong></li>
                                                <li><span>Quy trình tự động</span><strong><?php echo $limitAutomations > 0 ? number_format($limitAutomations) : 'Không giới hạn'; ?></strong></li>
                                                <li><span>Dùng thử</span><strong><?php echo $trialDays > 0 ? $trialDays . ' ngày' : 'Không'; ?></strong></li>
                                            </ul>
                                        </details>
                                    </div>
                                    <div class="plan-cta-row"><span class="plan-chip"><?php echo html_escape($trialDays > 0 ? 'Trải nghiệm trước khi triển khai' : 'Sẵn sàng sang thanh toán'); ?></span></div>
                                    <span class="plan-payload hidden"
                                        data-name="<?php echo html_escape($name); ?>"
                                        data-price="<?php echo html_escape((string) $price); ?>"
                                        data-setup="<?php echo html_escape((string) $setupFee); ?>"
                                        data-currency="<?php echo html_escape($currency); ?>"
                                        data-cycle="<?php echo html_escape($cycleLabel); ?>"
                                        data-trial="<?php echo html_escape((string) $trialDays); ?>"
                                        data-bestfor="<?php echo html_escape($bestFor); ?>"
                                        data-users="<?php echo html_escape((string) $limitStaff); ?>"
                                        data-clients="<?php echo html_escape((string) $limitClients); ?>"
                                        data-storage="<?php echo html_escape((string) $limitStorage); ?>"
                                        data-invoices="<?php echo html_escape((string) $limitInvoices); ?>"
                                        data-api="<?php echo html_escape((string) $limitApi); ?>"
                                        data-automations="<?php echo html_escape((string) $limitAutomations); ?>"
                                        data-modules="<?php echo html_escape($modulesText); ?>">
                                    </span>
                                </label>
                            <?php } ?>
                        </div>
                    </section>

                    <section data-step="2" class="hidden">
                        <h3>Thông tin doanh nghiệp</h3>
                        <p class="panel-lead">Chỉ giữ lại dữ liệu thực sự cần để tạo đơn mua: doanh nghiệp, người liên hệ và địa chỉ truy cập CRM.</p>
                        <div class="grid two">
                            <div>
                                <label>Tên công ty *</label>
                                <input type="text" name="company_name" id="company_name" required>
                            </div>
                            <div>
                                <label>Người liên hệ *</label>
                                <input type="text" name="owner_name" id="owner_name" required>
                            </div>
                            <div>
                                <label>Email *</label>
                                <input type="email" name="owner_email" id="owner_email" required>
                            </div>
                            <div>
                                <label>Điện thoại</label>
                                <input type="text" name="phone" id="phone">
                            </div>
                            <div class="grid" style="grid-column:1 / -1;">
                                <div>
                                    <label>Địa chỉ truy cập CRM *</label>
                                    <input type="text" name="desired_subdomain" id="desired_subdomain" placeholder="vi-du-cong-ty">
                                    <div class="field-note" id="workspacePreview">Xem trước: ten-cong-ty.<?php echo html_escape($baseDomain); ?></div>
                                    <div id="subdomainHint" class="field-note">Chỉ dùng a-z, 0-9 và dấu gạch ngang.</div>
                                    <div id="subdomainStatus" class="field-note" style="margin-top:6px;"></div>
                                    <div id="subdomainSuggestions" class="field-note" style="margin-top:6px;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="trust-strip">
                            <article class="trust-item"><strong>Dữ liệu tách riêng</strong><p>Mỗi doanh nghiệp có dữ liệu, cấu hình và thương hiệu riêng.</p></article>
                            <article class="trust-item"><strong>Backup</strong><p>Có quy trình sao lưu để phục hồi khi cần.</p></article>
                            <article class="trust-item"><strong>SSL</strong><p>Truy cập an toàn trên tên miền hệ thống và địa chỉ riêng.</p></article>
                            <article class="trust-item"><strong>Hỗ trợ triển khai</strong><p>Phí triển khai bao gồm khởi tạo và hướng dẫn dùng hệ thống ban đầu.</p></article>
                        </div>
                    </section>

                    <section data-step="3" class="hidden">
                        <h3>Xác nhận và chuyển sang thanh toán</h3>
                        <p class="panel-lead">Kiểm tra lại toàn bộ thông tin mua hàng trước khi tạo hóa đơn và chuyển sang trang thanh toán.</p>
                        <div class="review">
                            <div class="review-block">
                                <h4>Tóm tắt đơn hàng</h4>
                                <div id="reviewBox"></div>
                            </div>
                            <div class="review-block">
                                <h4>Điều gì xảy ra tiếp theo?</h4>
                                <div class="review-line"><span>1. Tạo hóa đơn</span><strong>Hệ thống thanh toán</strong></div>
                                <div class="review-line"><span>2. Thanh toán</span><strong>Trang thanh toán</strong></div>
                                <div class="review-line"><span>3. Khởi tạo hệ thống</span><strong>Đang chờ xử lý</strong></div>
                                <div class="review-line"><span>4. Theo dõi trạng thái</span><strong>Trang cập nhật đăng ký</strong></div>
                            </div>
                        </div>
                    </section>

                    <div class="actions">
                        <button type="button" class="btn secondary" id="prevBtn">Quay lại</button>
                        <button type="button" class="btn primary" id="nextBtn">Tiếp tục</button>
                    </div>
                </div>

                <aside class="panel aside">
                    <div class="summary-top">
                        <h3>Tóm tắt mua hàng</h3>
                        <p>Bảng tóm tắt cập nhật tự động để bạn luôn thấy rõ gói đang chọn, thông tin doanh nghiệp và tổng chi phí dự kiến trước khi thanh toán.</p>
                    </div>
                    <div class="summary-price">
                        <div class="summary-box">
                            <span>Giá thuê bao</span>
                            <strong id="sumSubscription">-</strong>
                        </div>
                        <div class="summary-box setup">
                            <span>Phí triển khai</span>
                            <strong id="sumSetup">-</strong>
                        </div>
                    </div>
                    <div class="summary-list">
                        <div class="summary-row"><span>Gói</span><strong id="sumPlan">Chưa chọn</strong></div>
                        <div class="summary-row"><span>Chu kỳ</span><strong id="sumCycle">-</strong></div>
                        <div class="summary-row"><span>Phù hợp với</span><strong id="sumBestFor">-</strong></div>
                        <div class="summary-row"><span>Công ty</span><strong id="sumCompany">-</strong></div>
                        <div class="summary-row"><span>Email</span><strong id="sumEmail">-</strong></div>
                        <div class="summary-row"><span>Subdomain</span><strong id="sumSubdomain">-</strong></div>
                        <div class="summary-row"><span>Dùng thử</span><strong id="sumTrial">-</strong></div>
                    </div>
                    <div class="summary-total">
                        <div class="summary-row"><span>Tổng dự kiến</span><strong id="sumTotal">-</strong></div>
                    </div>
                    <span class="summary-pill">Không phát sinh phí ẩn ở bước thanh toán</span>
                </aside>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var steps = [
        {title: "Chọn gói", note: "Gói dịch vụ + phí triển khai"},
        {title: "Doanh nghiệp", note: "Công ty + liên hệ + địa chỉ truy cập"},
        {title: "Xác nhận", note: "Kiểm tra trước khi thanh toán"}
    ];
    var current = 1;
    var max = steps.length;
    var form = document.getElementById("signupForm");
    var stepbar = document.getElementById("stepbar");
    var nextBtn = document.getElementById("nextBtn");
    var prevBtn = document.getElementById("prevBtn");
    var subInput = document.getElementById("desired_subdomain");
    var companyInput = document.getElementById("company_name");
    var ownerEmailInput = document.getElementById("owner_email");
    var hint = document.getElementById("subdomainHint");
    var subdomainStatus = document.getElementById("subdomainStatus");
    var subdomainSuggestions = document.getElementById("subdomainSuggestions");
    var workspacePreview = document.getElementById("workspacePreview");
    var baseDomain = <?php echo json_encode($baseDomain); ?>;
    var subdomainCheckUrl = <?php echo json_encode('/signup/check-subdomain'); ?>;
    var subdomainCheckTimer = null;
    var subdomainCheckSeq = 0;
    var subdomainState = {available: null, reason: ''};

    function formatPrice(amount, currency, suffix) {
        var number = Number(amount || 0);
        var formatted = String(currency).toUpperCase() === "VND"
            ? number.toLocaleString("vi-VN", {maximumFractionDigits: 0}) + " VND"
            : number.toLocaleString("en-US", {minimumFractionDigits: 2, maximumFractionDigits: 2}) + " " + currency;
        return suffix ? formatted + "/" + suffix : formatted;
    }
    function slugify(v) {
        return (v || "")
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .replace(/[^a-z0-9]+/g, "-")
            .replace(/^-+|-+$/g, "");
    }
    function renderBar() {
        stepbar.innerHTML = "";
        steps.forEach(function (step, idx) {
            var node = document.createElement("div");
            node.className = "step" + ((idx + 1) === current ? " active" : "");
            node.innerHTML = "<b>" + (idx + 1) + "</b><div><strong>" + step.title + "</strong><small>" + step.note + "</small></div>";
            stepbar.appendChild(node);
        });
    }
    function showStep() {
        for (var i = 1; i <= max; i++) {
            var section = form.querySelector('[data-step="' + i + '"]');
            if (section) {
                section.classList.toggle("hidden", i !== current);
            }
        }
        prevBtn.style.visibility = current === 1 ? "hidden" : "visible";
        nextBtn.textContent = current === max ? "Tiếp tục đến thanh toán" : "Tiếp tục";
        renderBar();
        syncSummary();
        if (current === 3) {
            buildReview();
        }
    }
    function getSelectedPlanCard() {
        var checked = form.querySelector('input[name="plan_id"]:checked');
        return checked ? checked.closest(".plan-card") : null;
    }
    function getPlanData() {
        var card = getSelectedPlanCard();
        if (!card) {
            return null;
        }
        var payload = card.querySelector(".plan-payload");
        return {
            name: payload.dataset.name || "",
            price: Number(payload.dataset.price || 0),
            setup: Number(payload.dataset.setup || 0),
            currency: payload.dataset.currency || "VND",
            cycle: payload.dataset.cycle || "tháng",
            trial: Number(payload.dataset.trial || 0),
            bestfor: payload.dataset.bestfor || ""
        };
    }
    function validateStep() {
        if (current === 1 && !getSelectedPlanCard()) {
            alert("Vui lòng chọn gói.");
            return false;
        }
        if (current === 2) {
            var required = ["company_name", "owner_name", "owner_email", "desired_subdomain"];
            for (var i = 0; i < required.length; i++) {
                var field = form.querySelector('[name="' + required[i] + '"]');
                if (!field || !field.value.trim()) {
                    alert("Vui lòng nhập đầy đủ thông tin doanh nghiệp.");
                    return false;
                }
            }
            if (!/^[a-z0-9-]+$/.test((subInput.value || "").trim())) {
                alert("Subdomain không hợp lệ.");
                return false;
            }
            if (subdomainState && subdomainState.available === false) {
                alert(subdomainState.reason === 'reserved' ? 'Subdomain này đã bị cấm.' : 'Subdomain đã được sử dụng.');
                return false;
            }
        }
        return true;
    }
    function syncSummary() {
        var plan = getPlanData();
        var company = companyInput.value || "-";
        var email = ownerEmailInput.value || "-";
        var subdomain = subInput.value ? subInput.value + "." + baseDomain : "-";
        var total = plan ? plan.price + plan.setup : 0;

        document.getElementById("sumPlan").textContent = plan ? plan.name : "Chưa chọn";
        document.getElementById("sumCycle").textContent = plan ? "/" + plan.cycle : "-";
        document.getElementById("sumBestFor").textContent = plan ? plan.bestfor : "-";
        document.getElementById("sumSubscription").textContent = plan ? formatPrice(plan.price, plan.currency, plan.cycle) : "-";
        document.getElementById("sumSetup").textContent = plan ? formatPrice(plan.setup, plan.currency) : "-";
        document.getElementById("sumCompany").textContent = company;
        document.getElementById("sumEmail").textContent = email;
        document.getElementById("sumSubdomain").textContent = subdomain;
        document.getElementById("sumTrial").textContent = plan ? (plan.trial > 0 ? plan.trial + " ngày" : "Không") : "-";
        document.getElementById("sumTotal").textContent = plan ? formatPrice(total, plan.currency) : "-";
    }
    function buildReview() {
        var plan = getPlanData();
        var target = document.getElementById("reviewBox");
        if (!plan) {
            target.innerHTML = "<div class='review-line'><span>Chưa chọn gói</span><strong>-</strong></div>";
            return;
        }
        var subdomain = subInput.value ? subInput.value + "." + baseDomain : "-";
        var total = plan.price + plan.setup;
        target.innerHTML =
            "<div class='review-line'><span>Gói</span><strong>" + plan.name + "</strong></div>" +
            "<div class='review-line'><span>Phù hợp với</span><strong>" + plan.bestfor + "</strong></div>" +
            "<div class='review-line'><span>Giá thuê bao</span><strong>" + formatPrice(plan.price, plan.currency, plan.cycle) + "</strong></div>" +
            "<div class='review-line'><span>Phí triển khai</span><strong>" + formatPrice(plan.setup, plan.currency) + "</strong></div>" +
            "<div class='review-line'><span>Chu kỳ</span><strong>/" + plan.cycle + "</strong></div>" +
            "<div class='review-line'><span>Công ty</span><strong>" + (companyInput.value || "-") + "</strong></div>" +
            "<div class='review-line'><span>Liên hệ</span><strong>" + ((document.getElementById("owner_name").value || "-") + " / " + (ownerEmailInput.value || "-")) + "</strong></div>" +
            "<div class='review-line'><span>Địa chỉ truy cập CRM</span><strong>" + subdomain + "</strong></div>" +
            "<div class='review-total'><div><span>Tổng dự kiến</span><strong>" + formatPrice(total, plan.currency) + "</strong></div><div><span>Thanh toán trước khi khởi tạo hệ thống</span></div></div>";
    }
    function refreshSubdomainHint() {
        subInput.value = slugify(subInput.value);
        workspacePreview.textContent = "Xem trước: " + (subInput.value || "ten-cong-ty") + "." + baseDomain;
        if (!subInput.value) {
            hint.className = "field-note";
            hint.textContent = "Chỉ dùng a-z, 0-9 và dấu gạch ngang.";
            return;
        }
        if (/^[a-z0-9-]+$/.test(subInput.value)) {
            hint.className = "hint-ok";
            hint.textContent = "Subdomain hợp lệ.";
            return;
        }
        hint.className = "hint-err";
        hint.textContent = "Subdomain không hợp lệ.";
    }
    function renderSubdomainAvailability(result) {
        if (!subdomainStatus || !subdomainSuggestions) {
            return;
        }
        subdomainState = result || {available: null, reason: ''};
        var suggestions = (result && result.suggestions) ? result.suggestions : [];
        if (!subInput.value.trim()) {
            subdomainStatus.className = "field-note";
            subdomainStatus.textContent = "";
            subdomainSuggestions.className = "field-note";
            subdomainSuggestions.textContent = "";
            return;
        }
        if (!result || result.available) {
            subdomainStatus.className = "hint-ok";
            subdomainStatus.textContent = "🟢 Có thể sử dụng";
            subdomainSuggestions.className = "field-note";
            subdomainSuggestions.textContent = "";
            return;
        }
        subdomainStatus.className = "hint-err";
        if (result.reason === "reserved") {
            subdomainStatus.textContent = "🔴 Tên này đã bị cấm";
        } else {
            subdomainStatus.textContent = "🔴 Đã được sử dụng";
        }
        if (suggestions.length) {
            subdomainSuggestions.className = "field-note";
            subdomainSuggestions.textContent = "Gợi ý: " + suggestions.join(", ");
        } else {
            subdomainSuggestions.className = "field-note";
            subdomainSuggestions.textContent = "";
        }
    }
    function checkSubdomainAvailability() {
        if (!subInput.value.trim()) {
            renderSubdomainAvailability(null);
            return;
        }
        if (!/^[a-z0-9-]+$/.test(subInput.value.trim())) {
            renderSubdomainAvailability({available: false, reason: 'format', suggestions: []});
            return;
        }
        var requestId = ++subdomainCheckSeq;
        var url = subdomainCheckUrl + '?value=' + encodeURIComponent(subInput.value.trim());
        fetch(url, {headers: {'Accept': 'application/json'}})
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                if (requestId !== subdomainCheckSeq) {
                    return;
                }
                if (payload && payload.success && payload.data) {
                    renderSubdomainAvailability(payload.data);
                } else {
                    renderSubdomainAvailability({available: false, reason: 'error', suggestions: []});
                }
            })
            .catch(function () {
                if (requestId !== subdomainCheckSeq) {
                    return;
                }
                renderSubdomainAvailability({available: false, reason: 'error', suggestions: []});
            });
    }
    function scheduleSubdomainCheck() {
        if (subdomainCheckTimer) {
            clearTimeout(subdomainCheckTimer);
        }
        subdomainCheckTimer = setTimeout(checkSubdomainAvailability, 250);
    }

    form.querySelectorAll(".plan-card").forEach(function (card) {
        card.addEventListener("click", function () {
            form.querySelectorAll(".plan-card").forEach(function (other) {
                other.classList.remove("active");
            });
            card.classList.add("active");
            var radio = card.querySelector('input[name="plan_id"]');
            if (radio) {
                radio.checked = true;
            }
            syncSummary();
        });
    });

    [companyInput, ownerEmailInput, document.getElementById("owner_name"), document.getElementById("phone")].forEach(function (input) {
        if (!input) {
            return;
        }
        input.addEventListener("input", syncSummary);
    });

    subInput.addEventListener("input", function () {
        refreshSubdomainHint();
        scheduleSubdomainCheck();
        syncSummary();
    });

    companyInput.addEventListener("blur", function () {
        if (!subInput.value.trim()) {
            subInput.value = slugify(companyInput.value);
            refreshSubdomainHint();
            scheduleSubdomainCheck();
        }
        syncSummary();
    });

    nextBtn.addEventListener("click", function () {
        if (!validateStep()) {
            return;
        }
        if (current < max) {
            current++;
            showStep();
            return;
        }
        form.submit();
    });

    prevBtn.addEventListener("click", function () {
        if (current > 1) {
            current--;
            showStep();
        }
    });

    refreshSubdomainHint();
    scheduleSubdomainCheck();
    syncSummary();
    showStep();
})();
</script>
</body>
</html>

