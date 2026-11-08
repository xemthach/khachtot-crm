<?php

$root = dirname(__DIR__);
$salesrepRoot = dirname(dirname($root)) . '/salesrep';
if (!is_dir($salesrepRoot)) {
    $salesrepRoot = dirname(dirname(dirname($root))) . '/salesrep';
}

$policy = file_get_contents($salesrepRoot . '/app/Policies/FieldFeedbackPolicy.php');
$controller = file_get_contents($salesrepRoot . '/app/Http/Controllers/FieldFeedbackController.php');
$service = file_get_contents($salesrepRoot . '/app/Services/FieldFeedbackService.php');
$routes = file_get_contents($salesrepRoot . '/routes/web.php');
$seeder = file_get_contents($salesrepRoot . '/database/seeders/DynamicPermissionSeeder.php');

$assertContains = static function (string $haystack, string $needle, string $message): void {
    if (strpos($haystack, $needle) === false) {
        throw new RuntimeException($message);
    }
};

$assertContains($policy, 'view_own_field_feedback', 'Policy must allow own feedback view.');
$assertContains($policy, 'view_team_field_feedback', 'Policy must allow manager-scoped feedback view.');
$assertContains($policy, 'view_tenant_field_feedback', 'Policy must allow tenant-wide feedback view.');
$assertContains($policy, 'review_field_feedback', 'Policy must guard review action.');
$assertContains($service, 'assertStoreAllowed', 'Service must enforce store scope beyond menu visibility.');
$assertContains($service, 'assertRelatedRecordAllowed', 'Service must enforce related record scope.');
$assertContains($controller, '$this->authorize', 'Controller must use policy authorization.');
$assertContains($routes, '/feedback', 'Salesrep feedback routes must exist.');
$assertContains($seeder, 'submit_field_feedback', 'Permissions seeder must register submit permission.');
$assertContains($seeder, 'resolve_field_feedback', 'Permissions seeder must register resolve permission.');

echo "DmsFieldFeedbackPermissionTest passed.\n";
