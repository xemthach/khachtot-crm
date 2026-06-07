<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$brandingContext = is_array($branding_context ?? null) ? $branding_context : [];
$logoUrl = trim((string) ($brandingContext['logo_url'] ?? ''));
if ($logoUrl === '' && !empty($logo) && empty($brandingContext['is_tenant'])) {
    $logoUrl = base_url('uploads/company/' . ltrim((string) $logo, '/'));
}
$faviconUrl = trim((string) ($brandingContext['favicon_url'] ?? ''));
$brandName = trim((string) ($brand_name ?? '')) ?: 'Khách Tốt CRM';
$signupUrl = site_url('signup');
$pricingUrl = site_url('#pricing');

$headerMenu = [];
foreach ((array) ($menus ?? []) as $menu) {
    if (($menu['menu_area'] ?? '') === 'header' && (int) ($menu['is_enabled'] ?? 1) === 1) {
        $headerMenu[] = $menu;
    }
}
if (empty($headerMenu)) {
    $headerMenu = [
        ['label' => 'Giải pháp', 'url' => '#solutions', 'target' => '_self'],
        ['label' => 'Quy trình', 'url' => '#journey', 'target' => '_self'],
        ['label' => 'Sản phẩm', 'url' => '#product', 'target' => '_self'],
        ['label' => 'Bảng giá', 'url' => '#pricing', 'target' => '_self'],
        ['label' => 'FAQ', 'url' => '#faq', 'target' => '_self'],
    ];
}

$faqList = [];
foreach ((array) ($faqs ?? []) as $faq) {
    if (!is_array($faq)) {
        continue;
    }
    $question = trim((string) ($faq['q'] ?? $faq['title'] ?? ''));
    $answer = trim((string) ($faq['a'] ?? $faq['description'] ?? $faq['content'] ?? ''));
    if ($question !== '' && $answer !== '') {
        $faqList[] = ['q' => $question, 'a' => $answer];
    }
}
if (empty($faqList)) {
    $faqList = [
        ['q' => 'CRM Khách Tốt phù hợp với ai?', 'a' => 'Nền tảng phù hợp với doanh nghiệp vừa và nhỏ cần quản lý khách hàng, bán hàng, công nợ và vận hành trên một hệ thống thống nhất.'],
        ['q' => 'Có thể dùng thử trước khi chọn gói không?', 'a' => 'Có. Doanh nghiệp có thể bắt đầu bằng gói dùng thử và nâng cấp khi quy trình đã sẵn sàng.'],
        ['q' => 'Khách Tốt có hỗ trợ triển khai không?', 'a' => 'Có. Đội ngũ hỗ trợ cấu hình ban đầu, phân quyền và hướng dẫn các luồng vận hành cốt lõi.'],
        ['q' => 'Có thể mở rộng thêm ứng dụng không?', 'a' => 'Có. Các ứng dụng như thanh toán, hóa đơn điện tử, kho và dự án có thể được bật theo nhu cầu.'],
        ['q' => 'Dữ liệu doanh nghiệp có được tách riêng không?', 'a' => 'Có. Không gian làm việc, dữ liệu và cấu hình được tách theo từng doanh nghiệp.'],
    ];
}

$whyChoose = [
    ['icon' => 'customer', 'title' => 'Quản lý khách hàng tập trung', 'text' => 'Hồ sơ, lịch sử trao đổi và cơ hội bán hàng được đặt trong cùng một luồng dữ liệu.'],
    ['icon' => 'sales', 'title' => 'Bán hàng có quy trình', 'text' => 'Theo dõi lead, báo giá và trạng thái chốt đơn rõ ràng cho từng nhân sự.'],
    ['icon' => 'finance', 'title' => 'Tài chính và công nợ rõ ràng', 'text' => 'Kết nối hóa đơn, thanh toán và công nợ để giảm thao tác đối chiếu thủ công.'],
    ['icon' => 'einvoice', 'title' => 'Hóa đơn điện tử sẵn sàng', 'text' => 'Phát hành và theo dõi hóa đơn ngay trong quy trình vận hành hiện có.'],
    ['icon' => 'inventory_project', 'title' => 'Kho và dự án liền mạch', 'text' => 'Gắn bán hàng với hàng hóa, công việc, tiến độ và trách nhiệm thực hiện.'],
    ['icon' => 'expansion', 'title' => 'Mở rộng theo ngành', 'text' => 'Bắt đầu từ nghiệp vụ cốt lõi rồi bật thêm ứng dụng khi doanh nghiệp phát triển.'],
];

$journey = [
    ['number' => '01', 'title' => 'Thu hút lead', 'text' => 'Tập trung khách tiềm năng từ các nguồn vào CRM.'],
    ['number' => '02', 'title' => 'Tư vấn', 'text' => 'Giao người phụ trách và lưu lịch sử chăm sóc.'],
    ['number' => '03', 'title' => 'Báo giá', 'text' => 'Tạo đề xuất và theo dõi phản hồi khách hàng.'],
    ['number' => '04', 'title' => 'Chốt đơn', 'text' => 'Chuyển cơ hội thành khách hàng và đơn hàng.'],
    ['number' => '05', 'title' => 'Xuất hóa đơn', 'text' => 'Phát hành chứng từ từ dữ liệu đã xác nhận.'],
    ['number' => '06', 'title' => 'Thu tiền', 'text' => 'Ghi nhận giao dịch và đối soát công nợ.'],
    ['number' => '07', 'title' => 'Chăm sóc lại', 'text' => 'Dùng lịch sử để duy trì quan hệ và bán thêm.'],
];

$modules = [
    ['icon' => 'payment', 'badge' => 'Có sẵn', 'title' => 'KT SePay', 'text' => 'Tạo yêu cầu thanh toán và hỗ trợ đối soát giao dịch theo ngữ cảnh.'],
    ['icon' => 'matbao_invoice', 'badge' => 'Có sẵn', 'title' => 'KT Mắt Bão Invoice', 'text' => 'Kết nối nghiệp vụ hóa đơn điện tử và chữ ký số trong cùng hệ thống.'],
    ['icon' => 'inventory', 'badge' => 'Có sẵn', 'title' => 'Kho nội bộ', 'text' => 'Theo dõi nhập, xuất, tồn và luân chuyển hàng hóa theo kho.'],
    ['icon' => 'project', 'badge' => 'Có sẵn', 'title' => 'Quản lý dự án', 'text' => 'Kiểm soát đầu việc, tiến độ và phối hợp giữa các nhóm phụ trách.'],
    ['icon' => 'reports', 'badge' => 'Có sẵn', 'title' => 'Báo cáo', 'text' => 'Tổng hợp dữ liệu kinh doanh và vận hành thành các chỉ số dễ theo dõi.'],
    ['icon' => 'marketplace', 'badge' => 'Đang mở rộng', 'title' => 'Marketplace', 'text' => 'Bổ sung ứng dụng theo ngành và quy mô mà không thay đổi nền tảng lõi.'],
];

$securityItems = [
    ['title' => 'Phân quyền nhân sự', 'text' => 'Giới hạn dữ liệu và thao tác theo vai trò, phòng ban và trách nhiệm.'],
    ['title' => 'Tách dữ liệu doanh nghiệp', 'text' => 'Mỗi doanh nghiệp vận hành trong không gian dữ liệu và cấu hình riêng.'],
    ['title' => 'Nhật ký hoạt động', 'text' => 'Các sự kiện quan trọng được ghi nhận để phục vụ kiểm tra và vận hành.'],
    ['title' => 'Kiểm soát thanh toán', 'text' => 'Yêu cầu, trạng thái và giao dịch được gắn với đúng nghiệp vụ phát sinh.'],
    ['title' => 'Sao lưu và khôi phục', 'text' => 'Quy trình sao lưu hỗ trợ bảo vệ dữ liệu và xử lý tình huống vận hành.'],
    ['title' => 'Cấu hình theo doanh nghiệp', 'text' => 'Thương hiệu, email và quy trình có thể điều chỉnh theo từng tổ chức.'],
];

$industries = [
    ['title' => 'Bán buôn và B2B', 'text' => 'Theo dõi khách hàng, báo giá, công nợ và hàng hóa trong một luồng xuyên suốt.'],
    ['title' => 'Doanh nghiệp dịch vụ', 'text' => 'Kết nối lead, hợp đồng, dự án, công việc và chăm sóc sau bán.'],
    ['title' => 'Phân phối', 'text' => 'Kiểm soát đội bán hàng, kho, hóa đơn và thanh toán theo từng điểm vận hành.'],
];

$formatPrice = static function ($price, $currency) {
    $amount = (float) $price;
    $currency = strtoupper(trim((string) $currency)) ?: 'VND';
    if ($currency === 'VND') {
        return number_format($amount, 0, ',', '.') . ' VND';
    }
    return number_format($amount, 2, '.', ',') . ' ' . $currency;
};

if (!function_exists('kt_landing_fastwork_icon')) {
    function kt_landing_fastwork_icon($name)
    {
        $name = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $name));
        $icons = [
            'customer' => '<path d="M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path d="M3.5 19a4.5 4.5 0 0 1 9 0"/><path d="M16.5 10.5a2.5 2.5 0 1 0 0-5"/><path d="M15 15.2c2.7.3 4.5 1.6 5.5 3.8"/>',
            'sales' => '<path d="M4 17.5 9 12l3.5 3.2L20 7"/><path d="M15 7h5v5"/><path d="M4 6h6"/><path d="M4 10h3"/>',
            'finance' => '<path d="M7 5h10a2 2 0 0 1 2 2v11H5V7a2 2 0 0 1 2-2Z"/><path d="M8 9h8"/><path d="M8 13h4"/><path d="M15.5 15.5a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/>',
            'einvoice' => '<path d="M7 3h7l4 4v14H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M14 3v5h5"/><path d="m8.5 15 2.2 2.2 4.8-5.2"/><path d="M8 10h4"/>',
            'inventory_project' => '<path d="m12 3 7 4-7 4-7-4 7-4Z"/><path d="M5 7v8l7 4 7-4V7"/><path d="M12 11v8"/><path d="M16 13h4"/><path d="M16 16h3"/>',
            'expansion' => '<path d="M5 5h5v5H5z"/><path d="M14 5h5v5h-5z"/><path d="M5 14h5v5H5z"/><path d="M15 16h4"/><path d="M17 14v4"/>',
            'payment' => '<path d="M4 7h16v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Z"/><path d="M4 10h16"/><path d="M7 15h4"/><path d="m15 16 1.4 1.4L20 14"/>',
            'matbao_invoice' => '<path d="M7 3h8l4 4v14H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M15 3v5h5"/><path d="M8 11h7"/><path d="M8 15h4"/><path d="M15.5 17.5c.7-1 1.7-1 2.4 0 .5.7 1.2.7 1.7.1"/>',
            'inventory' => '<path d="m12 3 8 4.4-8 4.4-8-4.4L12 3Z"/><path d="M4 7.5v8.8L12 21l8-4.7V7.5"/><path d="M12 12v9"/><path d="m8 5.2 8 4.5"/>',
            'project' => '<path d="M5 5h14v14H5z"/><path d="M8 9h3"/><path d="M8 13h8"/><path d="M8 17h5"/><path d="m15 8 1 1 2-2"/>',
            'reports' => '<path d="M5 19V5"/><path d="M5 19h15"/><path d="M9 16v-5"/><path d="M13 16V8"/><path d="M17 16v-8"/><path d="m8 7 4-2 4 1.5 3-3"/>',
            'marketplace' => '<path d="M5 5h6v6H5z"/><path d="M13 5h6v6h-6z"/><path d="M5 13h6v6H5z"/><path d="M15 15h4"/><path d="M17 13v8"/>',
        ];
        $paths = $icons[$name] ?? $icons['expansion'];
        return '<span class="fw-icon fw-icon--' . html_escape($name) . '" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false" role="img">' . $paths . '</svg></span>';
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($meta_title ?? $title ?? $brandName); ?></title>
    <meta name="description" content="<?php echo html_escape($meta_description ?? 'CRM và nền tảng vận hành doanh nghiệp dành cho SME.'); ?>">
    <link rel="canonical" href="<?php echo html_escape($canonical_url ?? current_url()); ?>">
    <?php if ($faviconUrl !== '') { ?><link rel="icon" href="<?php echo html_escape($faviconUrl); ?>"><?php } ?>
    <link rel="stylesheet" href="<?php echo base_url('assets/plugins/bootstrap/css/bootstrap.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/kt_public_typography.css'); ?>">
    <link rel="stylesheet" href="<?php echo module_dir_url('kt_landing', 'assets/templates/fastwork_inspired/style.css'); ?>">
    <?php if (trim((string) ($custom_css ?? '')) !== '') { ?><style><?php echo (string) $custom_css; ?></style><?php } ?>
</head>
<body class="kt-template-fastwork">
<header class="fw-topbar">
    <div class="fw-container fw-nav">
        <a class="fw-brand" href="<?php echo site_url(); ?>">
            <?php if ($logoUrl !== '') { ?><img src="<?php echo html_escape($logoUrl); ?>" alt="<?php echo html_escape($brandName); ?>"><?php } ?>
            <span><?php echo html_escape($brandName); ?></span>
        </a>
        <input type="checkbox" id="fw-nav-toggle" class="fw-nav-toggle-input">
        <label for="fw-nav-toggle" class="fw-nav-toggle-label" aria-label="Mở điều hướng"><span></span><span></span><span></span></label>
        <nav class="fw-nav-links" aria-label="Điều hướng chính">
            <?php foreach ($headerMenu as $menu) { ?>
                <a target="<?php echo html_escape($menu['target'] ?? '_self'); ?>" href="<?php echo html_escape($menu['url'] ?? '#'); ?>"><?php echo html_escape($menu['label'] ?? ''); ?></a>
            <?php } ?>
            <a class="fw-btn fw-btn-primary" href="<?php echo html_escape($signupUrl); ?>">Dùng thử miễn phí</a>
        </nav>
    </div>
</header>

<main>
    <section class="fw-hero">
        <span class="fw-decor fw-decor-a" aria-hidden="true">+</span>
        <span class="fw-decor fw-decor-b" aria-hidden="true">✓</span>
        <div class="fw-container fw-hero-grid">
            <div class="fw-hero-copy">
                <span class="fw-eyebrow">CRM và vận hành doanh nghiệp</span>
                <h1>
                    <span class="fw-hero-line"><span class="fw-marker">Một nền tảng CRM</span></span>
                    <span class="fw-hero-line fw-hero-line-wide">vận hành cả doanh nghiệp</span>
                    <span class="fw-hero-line fw-hero-line-sm">cho SME</span>
                </h1>
                <p>Chuẩn hóa khách hàng, bán hàng, tài chính, hóa đơn, kho và công việc trên một hệ thống thống nhất.</p>
                <div class="fw-cta-row">
                    <a class="fw-btn fw-btn-primary" href="<?php echo html_escape($signupUrl); ?>">Dùng thử miễn phí</a>
                    <a class="fw-btn fw-btn-outline" href="<?php echo html_escape($pricingUrl); ?>">Xem bảng giá</a>
                </div>
                <div class="fw-benefits" aria-label="Lợi ích triển khai">
                    <span>Không cần cài đặt phức tạp</span>
                    <span>Kích hoạt nhanh</span>
                    <span>Hỗ trợ triển khai</span>
                    <span>Mở rộng bằng module</span>
                </div>
            </div>

            <div class="fw-product-stage" aria-label="Mô phỏng bảng điều khiển CRM">
                <div class="fw-float-card fw-float-card-a"><b>+18,4%</b><span>Chỉ số minh họa</span></div>
                <div class="fw-float-card fw-float-card-b"><b>9</b><span>Việc cần xử lý</span></div>
                <div class="fw-dash">
                    <div class="fw-dash-head">
                        <div><small>KHÁCH TỐT CRM</small><strong>Tổng quan doanh nghiệp</strong></div>
                        <span>Hôm nay</span>
                    </div>
                    <aside class="fw-dash-side">
                        <b>Điều hành</b>
                        <span class="active">Tổng quan</span><span>Khách hàng</span><span>Bán hàng</span><span>Tài chính</span><span>Công việc</span>
                    </aside>
                    <div class="fw-dash-main">
                        <div class="fw-revenue-card">
                            <div><span>Doanh thu tháng</span><strong>4,8 tỷ</strong><small>Dữ liệu minh họa</small></div>
                            <div class="fw-trend" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
                        </div>
                        <div class="fw-kpis">
                            <article><span>Khách tiềm năng</span><strong>368</strong></article>
                            <article><span>Khách hàng</span><strong>124</strong></article>
                            <article><span>Hóa đơn</span><strong>1.286</strong></article>
                        </div>
                        <div class="fw-pipeline"><span>Mới</span><span>Đã tư vấn</span><span>Đã báo giá</span><span>Đã chốt</span></div>
                        <div class="fw-dash-grid">
                            <article><b>Công nợ</b><span>Đã thu 86%</span><em></em></article>
                            <article><b>Hoạt động gần đây</b><span>Báo giá mới được gửi</span><small>2 phút trước</small></article>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="fw-trust">
        <div class="fw-container">
            <div class="fw-section-heading compact">
                <span>Chỉ số tin cậy</span>
                <h2>Thiết kế để doanh nghiệp vận hành gọn hơn</h2>
            </div>
            <div class="fw-trust-grid">
                <article><strong>SME</strong><p>Thiết kế cho doanh nghiệp vừa và nhỏ</p></article>
                <article><strong>Đa quy trình</strong><p>Kết nối bán hàng, tài chính và vận hành</p></article>
                <article><strong>Theo module</strong><p>Mở rộng theo nhu cầu thay vì thay hệ thống</p></article>
                <article><strong>Linh hoạt</strong><p>Triển khai theo từng giai đoạn tăng trưởng</p></article>
            </div>
        </div>
    </section>

    <section class="fw-section" id="solutions">
        <div class="fw-container">
            <div class="fw-section-heading">
                <span>Giải pháp thống nhất</span>
                <h2>Vì sao chọn CRM Khách Tốt?</h2>
                <p>Một nguồn dữ liệu chung giúp các bộ phận phối hợp tốt hơn và giảm công việc lặp lại.</p>
            </div>
            <div class="fw-feature-grid">
                <?php foreach ($whyChoose as $item) { ?>
                    <article>
                        <?php echo kt_landing_fastwork_icon((string) $item['icon']); ?>
                        <h3><?php echo html_escape($item['title']); ?></h3>
                        <p><?php echo html_escape($item['text']); ?></p>
                    </article>
                <?php } ?>
            </div>
        </div>
    </section>

    <section class="fw-section fw-soft-section" id="comparison">
        <div class="fw-container">
            <div class="fw-section-heading">
                <span>Giảm phân mảnh dữ liệu</span>
                <h2>Một nền tảng thay cho nhiều phần mềm rời rạc</h2>
                <p>So sánh theo khả năng kết nối nghiệp vụ, không chỉ theo số lượng tính năng.</p>
            </div>
            <div class="fw-table-wrap" role="region" aria-label="Bảng so sánh nền tảng" tabindex="0">
                <table class="fw-comparison-table">
                    <thead><tr><th>Nghiệp vụ</th><th class="featured">CRM Khách Tốt</th><th>CRM riêng lẻ</th><th>ERP lớn</th><th>Excel / thủ công</th></tr></thead>
                    <tbody>
                    <?php
                    $comparisonRows = [
                        ['Khách hàng', 'Có', 'Có', 'Có', 'Rời rạc'],
                        ['Lead và bán hàng', 'Có', 'Có', 'Tùy cấu hình', 'Thủ công'],
                        ['Hóa đơn', 'Liền mạch', 'Tích hợp thêm', 'Có', 'Tách rời'],
                        ['Thanh toán', 'Đối soát theo ngữ cảnh', 'Tích hợp thêm', 'Tùy cấu hình', 'Đối chiếu tay'],
                        ['Kho hàng', 'Theo module', 'Không phổ biến', 'Có', 'Tệp riêng'],
                        ['Dự án và công việc', 'Có', 'Hạn chế', 'Tùy cấu hình', 'Phân tán'],
                        ['Báo cáo', 'Dữ liệu thống nhất', 'Theo CRM', 'Chuyên sâu', 'Tổng hợp tay'],
                        ['Phân quyền', 'Theo vai trò', 'Theo CRM', 'Chuyên sâu', 'Hạn chế'],
                        ['Mở rộng module', 'Linh hoạt', 'Phụ thuộc tích hợp', 'Chi phí cao', 'Khó kiểm soát'],
                    ];
                    foreach ($comparisonRows as $row) { ?>
                        <tr><th><?php echo html_escape($row[0]); ?></th><td class="featured"><?php echo html_escape($row[1]); ?></td><td><?php echo html_escape($row[2]); ?></td><td><?php echo html_escape($row[3]); ?></td><td><?php echo html_escape($row[4]); ?></td></tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            <p class="fw-scroll-hint">Vuốt ngang để xem đầy đủ bảng so sánh.</p>
        </div>
    </section>

    <section class="fw-section" id="journey">
        <div class="fw-container">
            <div class="fw-section-heading">
                <span>Hành trình khách hàng</span>
                <h2>Từ lead đến chăm sóc sau bán trong một luồng</h2>
            </div>
            <div class="fw-journey">
                <?php foreach ($journey as $step) { ?>
                    <article><b><?php echo html_escape($step['number']); ?></b><h3><?php echo html_escape($step['title']); ?></h3><p><?php echo html_escape($step['text']); ?></p></article>
                <?php } ?>
            </div>
        </div>
    </section>

    <section class="fw-section fw-product-section" id="product">
        <div class="fw-container fw-product-grid">
            <div class="fw-product-copy">
                <span class="fw-eyebrow">Khám phá sản phẩm</span>
                <h2>Một bảng điều khiển cho những việc cần quyết định</h2>
                <p>KPI kinh doanh, công nợ, công việc và sức khỏe vận hành được tổ chức để người quản lý nhìn thấy ưu tiên ngay khi mở bảng điều khiển.</p>
                <ul><li>Tổng quan theo vai trò</li><li>Hành động ưu tiên theo dữ liệu</li><li>Liên kết trực tiếp đến nghiệp vụ cần xử lý</li></ul>
                <a class="fw-text-link" href="<?php echo html_escape($signupUrl); ?>">Khởi tạo không gian dùng thử <span>→</span></a>
            </div>
            <div class="fw-preview">
                <div class="fw-preview-tabs"><span class="active">Tổng quan</span><span>Khách hàng</span><span>Bán hàng</span><span>Tài chính</span><span>Kho</span></div>
                <div class="fw-preview-body">
                    <div class="fw-preview-alert"><b>Việc cần xử lý hôm nay</b><span>3 hóa đơn quá hạn · 5 lead cần chăm sóc</span></div>
                    <div class="fw-preview-metrics"><article><span>Khách hàng</span><strong>248</strong></article><article><span>Đã thu tháng này</span><strong>82%</strong></article><article><span>Công việc đúng hạn</span><strong>91%</strong></article></div>
                    <div class="fw-preview-chart"><span>Doanh thu 6 tháng</span><div aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i></div></div>
                </div>
            </div>
        </div>
    </section>

    <section class="fw-section" id="modules">
        <div class="fw-container">
            <div class="fw-section-heading">
                <span>Ứng dụng mở rộng</span>
                <h2>Bật thêm năng lực khi doanh nghiệp cần</h2>
                <p>Giữ một nền tảng lõi và mở rộng theo đúng giai đoạn vận hành.</p>
            </div>
            <div class="fw-module-grid">
                <?php foreach ($modules as $module) { ?>
                    <article><div><?php echo kt_landing_fastwork_icon((string) $module['icon']); ?><em><?php echo html_escape($module['badge']); ?></em></div><h3><?php echo html_escape($module['title']); ?></h3><p><?php echo html_escape($module['text']); ?></p></article>
                <?php } ?>
            </div>
        </div>
    </section>

    <section class="fw-section fw-security" id="security">
        <div class="fw-container">
            <div class="fw-section-heading">
                <span>Quản trị tin cậy</span>
                <h2>Bảo mật và kiểm soát doanh nghiệp</h2>
            </div>
            <div class="fw-security-grid">
                <?php foreach ($securityItems as $index => $item) { ?>
                    <article><span>0<?php echo (int) $index + 1; ?></span><div><h3><?php echo html_escape($item['title']); ?></h3><p><?php echo html_escape($item['text']); ?></p></div></article>
                <?php } ?>
            </div>
        </div>
    </section>

    <section class="fw-section fw-pricing-section" id="pricing">
        <div class="fw-container">
            <div class="fw-section-heading centered">
                <span>Hỗ trợ chọn gói</span>
                <h2>Bắt đầu vừa đủ, mở rộng đúng lúc</h2>
                <p>Giá và giới hạn bên dưới được lấy trực tiếp từ cấu hình gói đang mở bán.</p>
            </div>
            <?php if (!empty($public_plans)) { ?>
                <div class="fw-plans">
                    <?php
                    $hasFeaturedPlan = false;
                    foreach ((array) $public_plans as $featuredCandidate) {
                        if ((int) ($featuredCandidate['landing_featured'] ?? 0) === 1) {
                            $hasFeaturedPlan = true;
                            break;
                        }
                    }
                    foreach ((array) $public_plans as $plan) {
                        $planId = (int) ($plan['id'] ?? 0);
                        $planName = trim((string) ($plan['landing_marketing_title'] ?? '')) ?: trim((string) ($plan['plan_name'] ?? 'Gói CRM'));
                        $subtitle = trim((string) ($plan['landing_marketing_subtitle'] ?? '')) ?: 'Phù hợp với nhu cầu vận hành hiện tại của doanh nghiệp.';
                        $price = (float) ($plan['price_monthly'] ?? $plan['price'] ?? 0);
                        $currency = (string) ($plan['currency'] ?? 'VND');
                        $cycle = strtolower((string) ($plan['billing_cycle'] ?? 'monthly'));
                        $cycleLabels = ['monthly' => 'tháng', 'yearly' => 'năm', 'quarterly' => 'quý', 'semiannual' => '6 tháng', 'onetime' => 'một lần'];
                        $featured = (int) ($plan['landing_featured'] ?? 0) === 1
                            || (!$hasFeaturedPlan && strcasecmp(trim((string) ($plan['plan_name'] ?? '')), 'SME') === 0);
                        $trialDays = (int) ($plan['trial_days'] ?? 0);
                        $ctaText = trim((string) ($plan['landing_cta_text'] ?? '')) ?: ($trialDays > 0 ? 'Dùng thử miễn phí' : 'Chọn gói này');
                        $ctaUrl = trim((string) ($plan['landing_cta_url'] ?? '')) ?: ($signupUrl . '?plan_id=' . $planId);
                        $staffLimit = (int) ($plan['limit_staff'] ?? 0);
                        $clientLimit = (int) ($plan['limit_clients'] ?? 0);
                        $storageLimit = (int) ($plan['limit_storage_mb'] ?? 0);
                    ?>
                        <article class="<?php echo $featured ? 'featured' : ''; ?>">
                            <?php if ($featured) { ?><span class="fw-plan-badge">Phổ biến</span><?php } ?>
                            <h3><?php echo html_escape($planName); ?></h3>
                            <p><?php echo html_escape($subtitle); ?></p>
                            <div class="fw-plan-price"><strong><?php echo html_escape($formatPrice($price, $currency)); ?></strong><span>/<?php echo html_escape($cycleLabels[$cycle] ?? $cycle); ?></span></div>
                            <ul>
                                <li><?php echo $staffLimit > 0 ? number_format($staffLimit) . ' nhân sự' : 'Không giới hạn nhân sự'; ?></li>
                                <li><?php echo $clientLimit > 0 ? number_format($clientLimit) . ' khách hàng' : 'Không giới hạn khách hàng'; ?></li>
                                <li><?php echo $storageLimit > 0 ? number_format($storageLimit / 1024, 1) . ' GB dung lượng' : 'Dung lượng theo chính sách gói'; ?></li>
                                <?php if ($trialDays > 0) { ?><li>Dùng thử <?php echo (int) $trialDays; ?> ngày</li><?php } ?>
                            </ul>
                            <a class="fw-btn <?php echo $featured ? 'fw-btn-primary' : 'fw-btn-outline'; ?>" href="<?php echo html_escape($ctaUrl); ?>"><?php echo html_escape($ctaText); ?></a>
                        </article>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <div class="fw-pricing-empty"><h3>Chọn gói phù hợp với quy mô doanh nghiệp</h3><p>Xem đầy đủ gói đang mở bán, giới hạn sử dụng và chính sách dùng thử.</p><a class="fw-btn fw-btn-primary" href="<?php echo html_escape($pricingUrl); ?>">Xem bảng giá</a></div>
            <?php } ?>
        </div>
    </section>

    <section class="fw-section fw-soft-section" id="cases">
        <div class="fw-container">
            <div class="fw-section-heading">
                <span>Tình huống ứng dụng</span>
                <h2>Một nền tảng, nhiều mô hình vận hành</h2>
                <p>Các ví dụ ngành minh họa cách tổ chức quy trình, không phải lời chứng thực của khách hàng cụ thể.</p>
            </div>
            <div class="fw-case-grid">
                <?php foreach ($industries as $industry) { ?><article><h3><?php echo html_escape($industry['title']); ?></h3><p><?php echo html_escape($industry['text']); ?></p><span>Khám phá quy trình phù hợp</span></article><?php } ?>
            </div>
        </div>
    </section>

    <section class="fw-section" id="faq">
        <div class="fw-container fw-faq-layout">
            <div class="fw-section-heading">
                <span>Câu hỏi thường gặp</span>
                <h2>Thông tin trước khi bắt đầu</h2>
                <p>Chưa tìm thấy câu trả lời? Đội ngũ triển khai sẽ hỗ trợ làm rõ theo quy mô và quy trình thực tế.</p>
            </div>
            <div>
                <?php foreach ($faqList as $faq) { ?><details class="fw-faq"><summary><?php echo html_escape($faq['q']); ?><span>+</span></summary><p><?php echo html_escape($faq['a']); ?></p></details><?php } ?>
            </div>
        </div>
    </section>

    <section class="fw-final-cta">
        <div class="fw-container">
            <span>Sẵn sàng bắt đầu?</span>
            <h2>Chuẩn hóa vận hành và tạo nền tảng cho tăng trưởng</h2>
            <p>Khởi tạo không gian dùng thử hoặc xem gói phù hợp với doanh nghiệp của bạn.</p>
            <div class="fw-cta-row"><a class="fw-btn fw-btn-primary" href="<?php echo html_escape($signupUrl); ?>">Dùng thử miễn phí</a><a class="fw-btn fw-btn-outline" href="<?php echo html_escape($pricingUrl); ?>">Xem bảng giá</a></div>
        </div>
    </section>
</main>

<footer class="fw-footer">
    <div class="fw-container fw-footer-grid">
        <div class="fw-footer-brand">
            <a class="fw-brand" href="<?php echo site_url(); ?>"><?php if ($logoUrl !== '') { ?><img src="<?php echo html_escape($logoUrl); ?>" alt=""><?php } ?><span><?php echo html_escape($brandName); ?></span></a>
            <p>Nền tảng CRM giúp doanh nghiệp chuẩn hóa khách hàng, bán hàng, tài chính và vận hành.</p>
        </div>
        <div><h3>Sản phẩm</h3><a href="#solutions">Giải pháp</a><a href="#product">Khám phá sản phẩm</a><a href="#modules">Ứng dụng mở rộng</a></div>
        <div><h3>Bắt đầu</h3><a href="<?php echo html_escape($signupUrl); ?>">Dùng thử miễn phí</a><a href="<?php echo html_escape($pricingUrl); ?>">Bảng giá</a></div>
        <div><h3>Thông tin</h3><a href="#security">Bảo mật</a><a href="#faq">Câu hỏi thường gặp</a><a href="#journey">Quy trình</a></div>
    </div>
    <div class="fw-container fw-copyright"><?php echo html_escape(trim((string) ($footer_text ?? '')) ?: ('© ' . date('Y') . ' ' . $brandName)); ?></div>
</footer>
</body>
</html>
