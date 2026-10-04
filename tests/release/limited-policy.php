<?php
define('BASEPATH', __DIR__ . '/../../system/');
define('APPPATH', __DIR__ . '/../../application/');
require APPPATH . 'libraries/KtReleasePolicy.php';
$cases = [
    '/misa/callback', '/admin/misa-amis/external-test', '/index.php/admin/kt_misa_amis_accounting',
    '/kt_integration_hub/cron/process_jobs/key', '/kt_integration_webhooks/receive/dms/key',
    '/api/dms/sso', '/admin/modules/activate/kt_misa_amis_accounting',
    '/admin/kt_saas/dms_orders', '/admin/%6disa/callback', '/kt_dms/sync',
];
foreach ($cases as $route) {
    if (!KtReleasePolicy::routeBlocked($route)) { throw new Exception('blocked route missed: ' . $route); }
}
foreach (['/admin', '/admin/authentication', '/admin/clients', '/admin/kt_saas/tenants', '/cron'] as $route) {
    if (KtReleasePolicy::routeBlocked($route)) { throw new Exception('core route blocked'); }
}
if (KtReleasePolicy::moduleBlocked('kt_saas')) { throw new Exception('SaaS disabled'); }
// Actual module must register zero hooks even if persisted active=1.
function hooks() { throw new Exception('disabled module registered a hook'); }
require __DIR__ . '/../../modules/kt_integration_hub/kt_integration_hub.php';
if (defined('KT_INTEGRATION_HUB_MODULE')) { throw new Exception('bootstrap did not stop'); }
// Actual model must stop before DB-capable parent constructor/helper/network.
class App_Model { public function __construct() { throw new LogicException('DB boundary crossed'); } }
require __DIR__ . '/../../modules/kt_integration_hub/models/Kt_integration_model.php';
try { new Kt_integration_model(); throw new LogicException('model allowed'); }
catch (RuntimeException $e) {
    if ($e->getMessage() !== 'INTEGRATION_DISABLED_BY_RELEASE_PROFILE') { throw $e; }
}
echo "PASS 10 denied routes, 5 core routes, SaaS retained, zero hub hooks and zero model side effects\n";
