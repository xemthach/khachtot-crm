<?php defined('BASEPATH') or define('BASEPATH', __DIR__);
$source = file_get_contents(dirname(__DIR__) . '/views/admin/dms/setup_center.php') . file_get_contents(dirname(__DIR__) . '/controllers/Kt_integration_sso.php');
foreach (['Tác vụ đồng bộ gần nhất chưa thành công', 'Chưa thể mở DMS lúc này'] as $contract) if (strpos($source, $contract) === false) throw new RuntimeException($contract);
echo "DmsLanguageFontFinalCleanupTest passed.\n";
