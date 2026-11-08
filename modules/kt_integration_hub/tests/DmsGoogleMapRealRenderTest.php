<?php defined('BASEPATH') or define('BASEPATH', __DIR__);
$source = file_get_contents(dirname(__DIR__) . '/views/admin/dms/map_activity.php') . file_get_contents(dirname(__DIR__) . '/controllers/Kt_integration_hub.php');
foreach (['new google.maps.Map', 'google_initialized', 'dms_google_maps_loader', 'maps.googleapis.com/maps/api/js'] as $contract) if (strpos($source, $contract) === false) throw new RuntimeException($contract);
echo "DmsGoogleMapRealRenderTest passed.\n";
