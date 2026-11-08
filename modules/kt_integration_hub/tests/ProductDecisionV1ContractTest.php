<?php

$root = dirname(__DIR__);

$routes = file_get_contents($root . '/config/routes.php');
$api = file_get_contents($root . '/controllers/Kt_integration_api.php');
$contract = file_get_contents($root . '/libraries/DmsIntegrationContractStore.php');
$manualReview = file_get_contents($root . '/libraries/ManualReviewService.php');
$model = file_get_contents($root . '/models/Kt_integration_model.php');

foreach ([$routes, $api, $contract, $manualReview, $model] as $source) {
    if ($source === false) {
        throw new RuntimeException('Could not read Product Decision V1 sources.');
    }
}

$assertContains = static function (string $source, string $needle, string $message): void {
    if (strpos($source, $needle) === false) {
        throw new RuntimeException($message . ': ' . $needle);
    }
};

$assertNotContains = static function (string $source, string $needle, string $message): void {
    if (strpos($source, $needle) !== false) {
        throw new RuntimeException($message . ': ' . $needle);
    }
};

foreach ([
    "'warehouse_policy' => 'W3_ONE_DMS_WAREHOUSE_MANY_CRM_STORES'",
    "'outbound_collection' => 'MANUAL_REVIEW_ONLY'",
    "'delivery_projection' => 'STATUS_EVIDENCE_EXCEPTION_ONLY'",
    "'return_projection' => 'REQUEST_RECEIPT_ONLY'",
    "'supervisor_policy' => 'MONITOR_ONLY'",
    "'notifications' => 'NO_V1'",
    "'reports' => 'OPERATIONAL_VIEWS_ONLY'",
] as $needle) {
    $assertContains($contract, $needle, 'Contract readiness must expose approved V1 Product Decision');
}

foreach ([
    "'api/v1/dms/collections'",
    "'api/v1/dms/deliveries'",
    "'api/v1/dms/returns'",
] as $needle) {
    $assertContains($routes, $needle, 'CRM bridge route must exist for approved V1 projection');
}

foreach ([
    'handleDmsCollections',
    'ManualReviewService::TYPE_COLLECTION_EVIDENCE',
    'C2_COLLECTION_EVIDENCE_CRM_MANUAL_REVIEW',
    'D3_STATUS_EVIDENCE_EXCEPTION_ONLY',
    'R3_RETURN_REQUEST_RECEIPT_ONLY',
    'No payment, invoice, accounting, or reconciliation is created.',
    'without warehouse, fulfillment, or inventory mutation',
    'without refund or history rewrite',
] as $needle) {
    $assertContains($api, $needle, 'API bridge must preserve approved Product Decision boundary');
}

$assertContains($manualReview, 'TYPE_COLLECTION_EVIDENCE', 'Manual review service must support collection evidence review type');
$assertContains($manualReview, "empty(\$attributes['suppress_notification'])", 'Collection evidence can avoid V1 notification side effects');
$assertContains($model, "'notifications' => false", 'Tenant DMS V1 must not expose Notification as a product module');

foreach (['createPayment', 'add_payment', 'payment->add', 'mark_as_paid', 'createRefund', 'refund->add'] as $forbidden) {
    $assertNotContains($api, $forbidden, 'V1 bridge must not introduce forbidden side effect');
}

echo "ProductDecisionV1ContractTest passed.\n";
