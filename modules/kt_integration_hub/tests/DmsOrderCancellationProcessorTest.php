<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);
defined('KT_INTEGRATION_HUB_MODULE') or define('KT_INTEGRATION_HUB_MODULE', 'kt_integration_hub');
if (!function_exists('module_dir_path')) {
    function module_dir_path($module, $concat = '')
    {
        return dirname(__DIR__) . '/' . ltrim((string) $concat, '/');
    }
}
if (!function_exists('kt_integration_hub_json_encode')) {
    function kt_integration_hub_json_encode($value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
if (!function_exists('kt_integration_hub_now')) {
    function kt_integration_hub_now(): string
    {
        return '2026-06-09 19:30:00';
    }
}
if (!class_exists('Invoices_model', false)) {
    class Invoices_model
    {
        public const STATUS_UNPAID = 1;
        public const STATUS_PAID = 2;
        public const STATUS_PARTIALLY = 3;
        public const STATUS_OVERDUE = 4;
        public const STATUS_CANCELLED = 5;
        public const STATUS_DRAFT = 6;
    }
}

require_once dirname(__DIR__) . '/libraries/DmsOrderBridgeException.php';
require_once dirname(__DIR__) . '/libraries/DmsOrderBridgeProcessor.php';

class FakeDmsOrderCancellationProcessor extends DmsOrderBridgeProcessor
{
    public array $bridges = [];
    public array $logs = [];
    public array $invoices = [];
    public array $manualReviews = [];

    protected function getBridgeRecord(int $tenantId, int $dmsOrderId): ?array
    {
        return $this->bridges[$tenantId . ':' . $dmsOrderId] ?? null;
    }

    protected function findIdempotencyLog(int $tenantId, string $idempotencyKey): ?array
    {
        return $this->logs[$tenantId . ':' . $idempotencyKey] ?? null;
    }

    protected function getInvoice(int $invoiceId): ?object
    {
        return isset($this->invoices[$invoiceId]) ? (object) $this->invoices[$invoiceId] : null;
    }

    protected function invoicesModel()
    {
        return new class($this) {
            public function __construct(private readonly FakeDmsOrderCancellationProcessor $processor)
            {
            }

            public function mark_as_cancelled($id)
            {
                if (!isset($this->processor->invoices[$id])) {
                    return false;
                }

                $this->processor->invoices[$id]['status'] = Invoices_model::STATUS_CANCELLED;

                return true;
            }
        };
    }

    protected function integrationModel()
    {
        return new class($this) {
            public function __construct(private readonly FakeDmsOrderCancellationProcessor $processor)
            {
            }

            public function upsert_dms_order_bridge($tenantId, array $payload)
            {
                $this->processor->bridges[$tenantId . ':' . (int) ($payload['dms_order_id'] ?? 0)] = array_merge(
                    $this->processor->bridges[$tenantId . ':' . (int) ($payload['dms_order_id'] ?? 0)] ?? [],
                    ['tenant_id' => $tenantId],
                    $payload
                );

                return true;
            }

            public function upsert_provider_entity_link($tenantId, $providerCode, $connectionId, $entityType, $localId, $externalId, array $payload, $direction = null)
            {
                return true;
            }

            public function upsert_dms_order_projection_from_payload($tenantId, array $order, array $bridgePayload = [], array $sourcePayload = [])
            {
                return true;
            }

            public function log($level, $event, $message, array $context = [], $tenantId = null, $connectionId = null, $providerCode = null)
            {
                return true;
            }
        };
    }

    protected function createManualReviewForCancellation(
        int $tenantId,
        int $connectionId,
        array $order,
        int $invoiceId,
        string $invoiceStatus,
        string $reviewType,
        string $reason
    ): ?array {
        $review = [
            'id' => count($this->manualReviews) + 1,
            'tenant_id' => $tenantId,
            'entity_type' => 'order',
            'entity_id' => (int) ($order['order_id'] ?? 0),
            'review_type' => $reviewType,
            'review_status' => 'OPEN',
            'priority' => in_array($invoiceStatus, ['paid'], true) ? 'HIGH' : (in_array($invoiceStatus, ['partially', 'overdue'], true) ? 'MEDIUM' : 'LOW'),
            'review_reason' => $reason,
            'source_system' => self::PROVIDER_CODE,
            'payload_json' => kt_integration_hub_json_encode([
                'order' => $order,
                'invoice_status' => $invoiceStatus,
                'crm_invoice_id' => $invoiceId,
            ]),
        ];

        $this->manualReviews[] = $review;

        return $review;
    }
}

$tenant = ['id' => 1001, 'tenant_code' => 'ABC'];
$connection = ['id' => 77];
$basePayload = [
    'tenant' => [
        'crm_tenant_id' => 1001,
        'crm_company_id' => 1001,
        'tenant_code' => 'ABC',
    ],
    'order' => [
        'order_id' => 33001,
        'order_number' => 'ORD-20260609-0001',
        'crm_invoice_id' => 88001,
        'status' => 'cancelled',
        'cancel_reason' => 'Sales cancelled order',
        'updated_at' => '2026-06-09T19:30:00+07:00',
    ],
];

$processor = new FakeDmsOrderCancellationProcessor();
$processor->bridges['1001:33001'] = [
    'tenant_id' => 1001,
    'dms_order_id' => 33001,
    'crm_invoice_id' => 88001,
    'bridge_status' => 'synced',
    'invoice_status_snapshot' => 'draft',
];
$processor->invoices[88001] = [
    'id' => 88001,
    'status' => Invoices_model::STATUS_DRAFT,
];

$result = $processor->handleCancellation($tenant, $connection, $basePayload, [
    'request_id' => 'req-cancel-1',
    'idempotency_key' => 'cancel-order-33001-2026-06-09T19:30:00+07:00',
]);
if (($result['code'] ?? null) !== 'INVOICE_DRAFT_CANCELLED') {
    throw new RuntimeException('Expected draft invoice cancellation to succeed.');
}
if (($processor->invoices[88001]['status'] ?? null) !== Invoices_model::STATUS_CANCELLED) {
    throw new RuntimeException('Expected invoice draft to be marked cancelled.');
}

$alreadyCancelled = new FakeDmsOrderCancellationProcessor();
$alreadyCancelled->bridges['1001:33001'] = [
    'tenant_id' => 1001,
    'dms_order_id' => 33001,
    'crm_invoice_id' => 88001,
    'bridge_status' => 'synced',
    'invoice_status_snapshot' => 'cancelled',
];
$alreadyCancelled->invoices[88001] = [
    'id' => 88001,
    'status' => Invoices_model::STATUS_CANCELLED,
];
$alreadyResult = $alreadyCancelled->handleCancellation($tenant, $connection, $basePayload, [
    'request_id' => 'req-cancel-2',
    'idempotency_key' => 'cancel-order-33001-2026-06-09T19:30:00+07:00',
]);
if (($alreadyResult['code'] ?? null) !== 'INVOICE_ALREADY_CANCELLED') {
    throw new RuntimeException('Expected already cancelled invoice to return idempotent success.');
}

$manualReview = new FakeDmsOrderCancellationProcessor();
$manualReview->bridges['1001:33001'] = [
    'tenant_id' => 1001,
    'dms_order_id' => 33001,
    'crm_invoice_id' => 88001,
    'bridge_status' => 'synced',
];
$manualReview->invoices[88001] = [
    'id' => 88001,
    'status' => Invoices_model::STATUS_UNPAID,
];
try {
    $manualReview->handleCancellation($tenant, $connection, $basePayload, [
        'request_id' => 'req-cancel-3',
        'idempotency_key' => 'cancel-order-33001-2026-06-09T19:31:00+07:00',
    ]);
    throw new RuntimeException('Expected unpaid invoice cancellation to require manual review.');
} catch (DmsOrderBridgeException $e) {
    if ($e->apiCode() !== 'MANUAL_REVIEW_REQUIRED') {
        throw new RuntimeException('Expected MANUAL_REVIEW_REQUIRED for unpaid invoice.');
    }
}
if (count($manualReview->manualReviews) !== 1) {
    throw new RuntimeException('Expected unpaid invoice cancellation to create one manual review item.');
}
if (($manualReview->manualReviews[0]['review_type'] ?? null) !== ManualReviewService::TYPE_ORDER_CANCEL_UNPAID) {
    throw new RuntimeException('Expected unpaid invoice review type to be ORDER_CANCEL_UNPAID.');
}

$locked = new FakeDmsOrderCancellationProcessor();
$locked->bridges['1001:33001'] = [
    'tenant_id' => 1001,
    'dms_order_id' => 33001,
    'crm_invoice_id' => 88001,
    'bridge_status' => 'synced',
];
$locked->invoices[88001] = [
    'id' => 88001,
    'status' => Invoices_model::STATUS_PAID,
];
try {
    $locked->handleCancellation($tenant, $connection, $basePayload, [
        'request_id' => 'req-cancel-4',
        'idempotency_key' => 'cancel-order-33001-2026-06-09T19:32:00+07:00',
    ]);
    throw new RuntimeException('Expected paid invoice cancellation to be locked.');
} catch (DmsOrderBridgeException $e) {
    if ($e->apiCode() !== 'INVOICE_LOCKED') {
        throw new RuntimeException('Expected INVOICE_LOCKED for paid invoice.');
    }
}
if (count($locked->manualReviews) !== 1) {
    throw new RuntimeException('Expected paid invoice cancellation to create one manual review item.');
}
if (($locked->manualReviews[0]['review_type'] ?? null) !== ManualReviewService::TYPE_INVOICE_LOCKED) {
    throw new RuntimeException('Expected paid invoice review type to be INVOICE_LOCKED.');
}

$missingInvoice = new FakeDmsOrderCancellationProcessor();
$missingInvoice->bridges['1001:33001'] = [
    'tenant_id' => 1001,
    'dms_order_id' => 33001,
    'crm_invoice_id' => 88001,
    'bridge_status' => 'synced',
];
try {
    $missingInvoice->handleCancellation($tenant, $connection, $basePayload, [
        'request_id' => 'req-cancel-5',
        'idempotency_key' => 'cancel-order-33001-2026-06-09T19:33:00+07:00',
    ]);
    throw new RuntimeException('Expected missing invoice mapping to fail.');
} catch (DmsOrderBridgeException $e) {
    if ($e->apiCode() !== 'INVOICE_NOT_FOUND') {
        throw new RuntimeException('Expected INVOICE_NOT_FOUND for missing invoice.');
    }
}

echo "DmsOrderCancellationProcessorTest passed.\n";
