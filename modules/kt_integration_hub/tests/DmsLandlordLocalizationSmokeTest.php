<?php

$root = dirname(__DIR__);

$files = [
    'views/admin/dms/list.php',
    'views/admin/dms/settings.php',
    'views/admin/dms/setup_wizard.php',
    'views/admin/dms_landlord/tenant_subscriptions.php',
    'views/admin/dms_landlord/tenant_detail.php',
    'views/admin/dms_landlord/dashboard.php',
    'kt_integration_hub.php',
];

$legacyNeedles = [
    'Open DMS unavailable',
    'Runtime Status',
    'This setup wizard',
    'Open DMS verification',
    'Chua co du lieu',
    'Mo app DMS',
    'Tong quan',
    'Doi sales',
    'Ban do',
    'Diem ban',
    'Dong bo',
    'Suc khoe',
    'Thiet lap',
    'Hoa don',
    'Thanh toan',
    'Don hang',
    'Can xu ly',
    'DMS Tenant Subscriptions',
];

$mojibakeNeedles = ['Ã', 'Â', 'áº', 'á»', 'Ä', 'Æ', 'ChÆ'];

foreach ($files as $file) {
    $content = file_get_contents($root . '/' . $file);
    foreach ($legacyNeedles as $needle) {
        if (strpos($content, $needle) !== false) {
            throw new RuntimeException('Legacy localization string remains in ' . $file . ': ' . $needle);
        }
    }
    foreach ($mojibakeNeedles as $needle) {
        if (strpos($content, $needle) !== false) {
            throw new RuntimeException('Mojibake remains in ' . $file . ': ' . $needle);
        }
    }
}

foreach ([
    'Mở DMS',
    'Thiết lập',
    'Đồng bộ',
    'Trạng thái',
    'Gói DMS',
    'Tổng quan DMS',
    'Danh sách tenant DMS',
] as $localized) {
    $found = false;
    foreach ($files as $file) {
        if (strpos(file_get_contents($root . '/' . $file), $localized) !== false) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        throw new RuntimeException('Expected localized string missing: ' . $localized);
    }
}

echo "DmsLandlordLocalizationSmokeTest passed.\n";

