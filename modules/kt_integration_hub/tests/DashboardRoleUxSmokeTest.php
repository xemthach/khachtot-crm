<?php

$root = dirname(__DIR__);
$views = [
    $root . '/views/admin/dms/overview.php',
    $root . '/views/admin/dms/setup_center.php',
];

foreach ($views as $viewPath) {
    $content = file_get_contents($viewPath);
    if (strpos($content, 'data-r24-chart') === false) {
        throw new RuntimeException(basename($viewPath) . ' has no R24 chart/readiness contract.');
    }
    if (preg_match('/lorem ipsum|fake data|demo chart/i', $content)) {
        throw new RuntimeException(basename($viewPath) . ' contains placeholder chart copy.');
    }
}

$overview = file_get_contents($views[0]);
if (strpos($overview, 'Không có dữ liệu biểu đồ trong khoảng thời gian này') === false) {
    throw new RuntimeException('Overview has no truthful chart empty state.');
}

echo "DashboardRoleUxSmokeTest passed.\n";
