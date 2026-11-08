<?php

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/controllers/Kt_integration_hub.php');
$view = file_get_contents($root . '/views/admin/dms/setup_center.php');
$contractStore = file_get_contents($root . '/libraries/DmsIntegrationContractStore.php');
$model = file_get_contents($root . '/models/Kt_integration_model.php');

if ($controller === false || $view === false || $contractStore === false || $model === false) {
    throw new RuntimeException('Could not read sync readiness source files.');
}

foreach ([
    'queue_staff_sync_job',
    'Salesflow DMS connection is not configured for this tenant.',
    'kt_integration_sync_jobs',
    'process_due_jobs',
    'sync_staff_to_dms',
    'sync_customer_to_dms',
    'sync_product_to_dms',
] as $needle) {
    if (strpos($model, $needle) === false) {
        throw new RuntimeException('Missing queue/job evidence: ' . $needle);
    }
}

foreach ([
    'sync_operator_guidance',
    'inventory_guidance',
    'outbound_collection_guidance',
    'contractReadinessForTenant',
    'MANUAL_REVIEW_ONLY',
    'STATUS_EVIDENCE_EXCEPTION_ONLY',
    'REQUEST_RECEIPT_ONLY',
] as $needle) {
    if (strpos($controller, $needle) === false && strpos($view, $needle) === false) {
        throw new RuntimeException('Missing setup center guidance evidence: ' . $needle);
    }
}

foreach ([
    'sync_operator_guidance',
    'inventory_readiness',
    'outbound_collection_readiness',
    'Giao h',
    'V1 kh',
] as $needle) {
    if (strpos($view, $needle) === false) {
        throw new RuntimeException('Missing setup center display evidence: ' . $needle);
    }
}

foreach ([
    '$coreMappingsReady = $staffMappings > 0 && $customerStoreMappings > 0 && $productMappings > 0;',
    "\$status = (\$issues + \$health) > 0 ? 'BLOCKED' : (\$coreMappingsReady ? 'READY' : 'PARTIAL');",
    "'inventory' => \$inventoryState",
    "'inventory_readiness' => \$inventoryState",
    "'outbound_collection' => 'MANUAL_REVIEW_ONLY'",
    "'outbound_collection_readiness' => 'READY_MANUAL_REVIEW_ONLY'",
    "'delivery_projection' => 'STATUS_EVIDENCE_EXCEPTION_ONLY'",
    "'return_projection' => 'REQUEST_RECEIPT_ONLY'",
    "'supervisor_policy' => 'MONITOR_ONLY'",
    "'notifications' => 'NO_V1'",
    "'reports' => 'OPERATIONAL_VIEWS_ONLY'",
] as $needle) {
    if (strpos($contractStore, $needle) === false) {
        throw new RuntimeException('Missing contract readiness evidence: ' . $needle);
    }
}

echo "DmsSyncReadinessGuidanceTest passed.\n";
