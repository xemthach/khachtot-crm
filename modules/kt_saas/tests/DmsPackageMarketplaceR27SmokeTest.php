<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

$view = file_get_contents(dirname(__DIR__) . '/views/tenant/marketplace_app.php');
$model = file_get_contents(dirname(__DIR__) . '/models/Kt_saas_model.php');
if ($view === false || $model === false) {
    throw new RuntimeException('Unable to read R27 marketplace sources.');
}

foreach ([
    'Gói DMS cho đội sales thị trường',
    'Gói DMS hiện tại',
    'Chọn gói DMS phù hợp',
    'DMS Mini',
    'DMS Standard',
    'DMS Pro',
    'DMS Enterprise',
    'Nhân sự',
    'Điểm bán',
    'Theo dõi trực tiếp',
    'Báo cáo và đồng bộ CRM',
    'data-r27-cycle-selector',
    'data-r27-technical-details',
] as $needle) {
    if (strpos($view . $model, $needle) === false) {
        throw new RuntimeException('Missing R27 marketplace marker: ' . $needle);
    }
}

if (strpos($view, 'type="hidden" name="billing_cycle" value="monthly"') !== false) {
    throw new RuntimeException('R27 marketplace must not hardcode monthly billing.');
}

echo "DmsPackageMarketplaceR27SmokeTest passed.\n";
