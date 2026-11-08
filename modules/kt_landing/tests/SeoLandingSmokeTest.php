<?php

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/controllers/Kt_landing_public.php');
$view = file_get_contents($root . '/views/public/seo_marketing.php');
$routes = file_get_contents(dirname($root, 2) . '/application/config/routes.php');

foreach (['crm', 'features', 'dms', 'bang-gia', 'lien-he', 'dang-nhap', 'robots.txt', 'sitemap.xml'] as $route) {
    if (strpos($routes, "['" . $route . "']") === false) {
        throw new RuntimeException('Missing public route: ' . $route);
    }
}
foreach (['rel="canonical"', 'name="description"', 'property="og:title"', 'application/ld+json'] as $contract) {
    if (strpos($view, $contract) === false) {
        throw new RuntimeException('Missing SEO view contract: ' . $contract);
    }
}
if (strpos($controller, "'/dashboard'") !== false || strpos($controller, "'/admin'") !== false) {
    throw new RuntimeException('Private route was included in the marketing sitemap contract.');
}

foreach ([
    dirname($root, 2) . '/application/views/admin/includes/head.php',
    dirname($root, 2) . '/application/views/authentication/includes/head.php',
] as $privateHead) {
    if (strpos(file_get_contents($privateHead), 'noindex, nofollow, noarchive') === false) {
        throw new RuntimeException('Private CRM head is missing noindex: ' . $privateHead);
    }
}

echo "SeoLandingSmokeTest passed.\n";
