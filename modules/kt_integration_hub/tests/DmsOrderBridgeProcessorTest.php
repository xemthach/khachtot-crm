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
if (!class_exists('Invoices_model', false)) {
    class Invoices_model
    {
        public const STATUS_DRAFT = 6;
        public const STATUS_PAID = 2;
        public const STATUS_PARTIALLY = 3;
        public const STATUS_OVERDUE = 4;
        public const STATUS_CANCELLED = 5;
    }
}

require_once dirname(__DIR__) . '/libraries/DmsOrderBridgeException.php';
require_once dirname(__DIR__) . '/libraries/DmsOrderBridgeProcessor.php';

class FakeDmsOrderBridgeProcessor extends DmsOrderBridgeProcessor
{
    public array $clients = [];
    public array $staff = [];
    public array $products = [];
    public array $bridges = [];
    public array $logs = [];
    public array $invoices = [];
    public int $nextInvoiceId = 88001;

    public function exposedAdminNote(array $order): string
    {
        return $this->buildAdminNote($order);
    }

    protected function findCustomer(int $crmCustomerId): ?array
    {
        return $this->clients[$crmCustomerId] ?? null;
    }

    protected function findStaff(int $crmStaffId): ?array
    {
        return $this->staff[$crmStaffId] ?? null;
    }

    protected function findProduct(int $crmProductId): ?array
    {
        return $this->products[$crmProductId] ?? null;
    }

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

    protected function createInvoiceDraft(array $client, array $staff, array $order, array $items, array $productsById): int
    {
        $invoiceId = $this->nextInvoiceId++;
        $this->invoices[$invoiceId] = [
            'id' => $invoiceId,
            'status' => Invoices_model::STATUS_DRAFT,
            'items' => [],
        ];

        return $invoiceId;
    }

    protected function updateInvoiceDraft(int $invoiceId, array $client, array $staff, array $order, array $items, array $productsById): void
    {
        if (!isset($this->invoices[$invoiceId])) {
            throw new RuntimeException('Invoice not found in fake processor.');
        }

        $this->invoices[$invoiceId]['items'] = $items;
    }

    protected function persistSuccess(
        int $tenantId,
        int $connectionId,
        array $order,
        int $invoiceId,
        string $invoiceStatus,
        string $checksum,
        array $payload,
        string $requestId,
        string $idempotencyKey
    ): void {
        $this->bridges[$tenantId . ':' . (int) $order['order_id']] = [
            'tenant_id' => $tenantId,
            'dms_order_id' => (int) $order['order_id'],
            'crm_invoice_id' => $invoiceId,
            'bridge_status' => 'synced',
            'invoice_status_snapshot' => $invoiceStatus,
            'last_payload_checksum' => $checksum,
        ];
        $this->logs[$tenantId . ':' . $idempotencyKey] = [
            'context' => [
                'idempotency_key' => $idempotencyKey,
                'checksum' => $checksum,
            ],
            'checksum' => $checksum,
        ];
    }

    protected function recordFailure(
        int $tenantId,
        int $connectionId,
        array $order,
        string $checksum,
        string $bridgeStatus,
        string $errorCode,
        string $errorMessage,
        ?int $invoiceId = null,
        ?string $invoiceStatus = null,
        array $bridgeExtras = []
    ): void {
        $this->bridges[$tenantId . ':' . (int) $order['order_id']] = [
            'tenant_id' => $tenantId,
            'dms_order_id' => (int) $order['order_id'],
            'crm_invoice_id' => $invoiceId,
            'bridge_status' => $bridgeStatus,
            'invoice_status_snapshot' => $invoiceStatus,
            'last_payload_checksum' => $checksum,
            'error_code' => $errorCode,
        ] + $bridgeExtras;
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
        'crm_staff_id' => 5001,
        'crm_customer_id' => 9001,
        'store_id' => 61,
        'store_name' => 'Nhà thuốc Trung Tâm',
        'store_code' => 'STORE-61',
        'store_address' => '123 Nguyễn Trãi',
        'ordered_at' => '2026-06-09T10:00:00+07:00',
        'currency' => 'VND',
        'grand_total' => 24000,
        'status' => 'submitted',
        'updated_at' => '2026-06-09T10:01:00+07:00',
        'items' => [
            [
                'crm_product_id' => 7001,
                'qty' => 2,
                'unit_price' => 12000,
                'name' => 'Coca Cola 330ml',
                'sku' => 'SKU-COCA-330',
            ],
        ],
    ],
];

$processor = new FakeDmsOrderBridgeProcessor();
$processor->clients[9001] = ['userid' => 9001, 'billing_street' => '123 Nguyen Trai', 'default_currency' => 0];
$processor->staff[5001] = ['staffid' => 5001];
$processor->products[7001] = ['id' => 7001, 'description' => 'Coca Cola 330ml'];

$storeNote = $processor->exposedAdminNote($basePayload['order']);
foreach (['Điểm bán: Nhà thuốc Trung Tâm', 'Mã điểm bán: STORE-61', 'Địa chỉ điểm bán: 123 Nguyễn Trãi'] as $expectedStoreContext) {
    if (strpos($storeNote, $expectedStoreContext) === false) {
        throw new RuntimeException('Expected invoice admin note to retain safe DMS store context.');
    }
}

$result = $processor->handle($tenant, $connection, $basePayload, [
    'request_id' => 'req-order-1',
    'idempotency_key' => 'order-33001-2026-06-09T10:01:00+07:00',
]);
if (($result['crm_invoice_id'] ?? 0) !== 88001) {
    throw new RuntimeException('Expected first order bridge call to create invoice draft 88001.');
}

$sameResult = $processor->handle($tenant, $connection, $basePayload, [
    'request_id' => 'req-order-2',
    'idempotency_key' => 'order-33001-2026-06-09T10:01:00+07:00',
]);
if (($sameResult['crm_invoice_id'] ?? 0) !== 88001) {
    throw new RuntimeException('Expected same order replay to return existing invoice draft.');
}

$updatedPayload = $basePayload;
$updatedPayload['order']['grand_total'] = 26000;
$updatedPayload['order']['updated_at'] = '2026-06-09T10:05:00+07:00';
$updatedResult = $processor->handle($tenant, $connection, $updatedPayload, [
    'request_id' => 'req-order-3',
    'idempotency_key' => 'order-33001-2026-06-09T10:05:00+07:00',
]);
if (($updatedResult['crm_invoice_id'] ?? 0) !== 88001) {
    throw new RuntimeException('Expected draft invoice update to keep same crm_invoice_id.');
}

$processor->invoices[88001]['status'] = Invoices_model::STATUS_PAID;
try {
    $processor->handle($tenant, $connection, $updatedPayload, [
        'request_id' => 'req-order-4',
        'idempotency_key' => 'order-33001-2026-06-09T10:06:00+07:00',
    ]);
    throw new RuntimeException('Expected issued/paid invoice update to be rejected.');
} catch (DmsOrderBridgeException $e) {
    if ($e->apiCode() !== 'INVOICE_ALREADY_ISSUED') {
        throw new RuntimeException('Expected INVOICE_ALREADY_ISSUED for non-draft invoice update.');
    }
}

$missingCustomer = new FakeDmsOrderBridgeProcessor();
$missingCustomer->staff[5001] = ['staffid' => 5001];
$missingCustomer->products[7001] = ['id' => 7001, 'description' => 'Coca Cola 330ml'];
try {
    $missingCustomer->handle($tenant, $connection, $basePayload, [
        'request_id' => 'req-order-5',
        'idempotency_key' => 'order-33001-2026-06-09T10:01:00+07:00',
    ]);
    throw new RuntimeException('Expected missing customer mapping to be rejected.');
} catch (DmsOrderBridgeException $e) {
    if ($e->apiCode() !== 'CUSTOMER_NOT_MAPPED') {
        throw new RuntimeException('Expected CUSTOMER_NOT_MAPPED for missing customer mapping.');
    }
}

$missingProduct = new FakeDmsOrderBridgeProcessor();
$missingProduct->clients[9001] = ['userid' => 9001, 'billing_street' => '123 Nguyen Trai', 'default_currency' => 0];
$missingProduct->staff[5001] = ['staffid' => 5001];
try {
    $missingProduct->handle($tenant, $connection, $basePayload, [
        'request_id' => 'req-order-6',
        'idempotency_key' => 'order-33001-2026-06-09T10:01:00+07:00',
    ]);
    throw new RuntimeException('Expected missing product mapping to be rejected.');
} catch (DmsOrderBridgeException $e) {
    if ($e->apiCode() !== 'PRODUCT_NOT_MAPPED') {
        throw new RuntimeException('Expected PRODUCT_NOT_MAPPED for missing product mapping.');
    }
}

$idempotencyConflict = new FakeDmsOrderBridgeProcessor();
$idempotencyConflict->clients[9001] = ['userid' => 9001, 'billing_street' => '123 Nguyen Trai', 'default_currency' => 0];
$idempotencyConflict->staff[5001] = ['staffid' => 5001];
$idempotencyConflict->products[7001] = ['id' => 7001, 'description' => 'Coca Cola 330ml'];
$idempotencyConflict->logs['1001:order-33001-fixed'] = [
    'checksum' => 'different-checksum',
    'context' => [
        'idempotency_key' => 'order-33001-fixed',
        'checksum' => 'different-checksum',
    ],
];
try {
    $idempotencyConflict->handle($tenant, $connection, $basePayload, [
        'request_id' => 'req-order-7',
        'idempotency_key' => 'order-33001-fixed',
    ]);
    throw new RuntimeException('Expected idempotency key reuse with different payload to be rejected.');
} catch (DmsOrderBridgeException $e) {
    if ($e->apiCode() !== 'IDEMPOTENCY_KEY_REUSED') {
        throw new RuntimeException('Expected IDEMPOTENCY_KEY_REUSED for changed payload.');
    }
}

echo "DmsOrderBridgeProcessorTest passed.\n";
