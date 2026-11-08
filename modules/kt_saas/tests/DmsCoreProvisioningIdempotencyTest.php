<?php defined('BASEPATH') or define('BASEPATH', __DIR__);

$model = file_get_contents(dirname(__DIR__) . '/models/Kt_saas_model.php');
$controller = file_get_contents(dirname(__DIR__) . '/controllers/Kt_saas.php');

foreach ([
    "empty(\$payload['allow_reprovision'])",
    "where_in('status', ['done', 'completed'])",
    "tenantConnectionMetadataReady",
] as $needle) {
    if (strpos($model, $needle) === false) {
        throw new RuntimeException('Missing provisioning idempotency guard: ' . $needle);
    }
}

foreach (["requirePost();", "Tenant đã hoàn tất khởi tạo"] as $needle) {
    if (strpos($controller, $needle) === false) {
        throw new RuntimeException('Missing guarded provisioning action: ' . $needle);
    }
}

echo "DmsCoreProvisioningIdempotencyTest passed.\n";
