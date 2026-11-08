<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

$combined = file_get_contents(dirname(__DIR__) . '/views/tenant/marketplace_app.php')
    . file_get_contents(dirname(__DIR__, 2) . '/kt_integration_hub/views/admin/dms/setup_wizard.php');

foreach (['APP_KEY', 'DB_PASSWORD', 'MAIL_PASSWORD', 'GOOGLE_MAPS_API_KEY', 'bridge_secret', 'raw_entitlement_payload'] as $needle) {
    if (stripos($combined, $needle) !== false) {
        throw new RuntimeException('Sensitive configuration marker appears in R27 tenant views: ' . $needle);
    }
}

foreach (['get_csrf_token_name', 'Không có khóa API', 'không thể xem hoặc thay đổi thông tin xác thực'] as $needle) {
    if (strpos($combined, $needle) === false) {
        throw new RuntimeException('Missing safe security marker: ' . $needle);
    }
}

echo "DmsPackageSecurityR27Test passed.\n";
