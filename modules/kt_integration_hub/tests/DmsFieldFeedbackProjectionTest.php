<?php

$root = dirname(__DIR__);
$install = file_get_contents($root . '/install.php');
$routes = file_get_contents(dirname(dirname($root)) . '/application/config/my_routes.php');
$api = file_get_contents($root . '/controllers/Kt_integration_api.php');
$controller = file_get_contents($root . '/controllers/Kt_integration_hub.php');
$model = file_get_contents($root . '/models/Kt_integration_model.php');
$menu = file_get_contents($root . '/kt_integration_hub.php');
$view = file_get_contents($root . '/views/admin/dms/field_feedback.php');
$processor = file_get_contents($root . '/libraries/DmsFieldFeedbackBridgeProcessor.php');

$assertContains = static function (string $haystack, string $needle, string $message): void {
    if (strpos($haystack, $needle) === false) {
        throw new RuntimeException($message);
    }
};

$assertNotContains = static function (string $haystack, string $needle, string $message): void {
    if (strpos($haystack, $needle) !== false) {
        throw new RuntimeException($message);
    }
};

$assertContains($install, 'dms_field_feedback_projection', 'Install must create field feedback projection table.');
$assertContains($install, 'UNIQUE KEY `tenant_feedback_source`', 'Feedback projection must be idempotent by tenant/source/version.');
$assertContains($routes, "api/v1/dms/field-feedback", 'CRM API route for field feedback must exist.');
$assertContains($api, 'dms_field_feedback', 'API controller must expose field feedback handler.');
$assertContains($api, "validateSalesflowRequest('/api/v1/dms/field-feedback'", 'Field feedback must use signed Salesrep request validation.');
$assertContains($processor, 'FIELD_FEEDBACK_SYNCED', 'Processor must return field feedback sync result.');
$assertContains($processor, 'kt_integration_sync_jobs', 'Processor must persist sync job/audit state.');
$assertContains($model, 'upsert_dms_field_feedback_projection', 'Model must expose projection upsert.');
$assertContains($model, 'get_dms_field_feedback_summary', 'Model must expose feedback business summary.');
$assertContains($controller, 'dms_field_feedback', 'Tenant CRM controller must expose feedback screen.');
$assertContains($controller, 'field_feedback_bridge', 'Sync Health must include feedback bridge state.');
$assertContains($menu, "'feedback' => false", 'Tenant DMS module visibility must explicitly keep feedback controlled.');
$assertContains($view, 'Phản ánh thị trường', 'Feedback screen must be customer-facing.');
$assertContains($view, 'Không có dữ liệu phản ánh', 'Feedback screen must have truthful empty state.');

foreach ([$view, $processor] as $source) {
    $assertNotContains($source, 'Authorization', 'Feedback surface must not expose Authorization header.');
    $assertNotContains($source, 'Bearer', 'Feedback surface must not expose bearer token.');
    $assertNotContains($source, 'password', 'Feedback surface must not expose password text.');
}
$assertNotContains($view, 'tbldms_field_feedback_projection', 'Tenant screen must not expose raw table name.');

echo "DmsFieldFeedbackProjectionTest passed.\n";
