<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);
defined('KT_INTEGRATION_HUB_MODULE') or define('KT_INTEGRATION_HUB_MODULE', 'kt_integration_hub');
if (!function_exists('module_dir_path')) {
    function module_dir_path($module, $concat = '')
    {
        return dirname(__DIR__) . '/' . ltrim((string) $concat, '/');
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

require_once dirname(__DIR__) . '/libraries/CrmPaymentReadbackService.php';

class FakeCrmPaymentReadbackService extends CrmPaymentReadbackService
{
    public ?object $invoice = null;

    protected function invoicesModel()
    {
        return new class($this->invoice) {
            public function __construct(private readonly ?object $invoice)
            {
            }

            public function get($id)
            {
                return $this->invoice && (int) $this->invoice->id === (int) $id ? $this->invoice : null;
            }
        };
    }
}

$service = new FakeCrmPaymentReadbackService();
$service->invoice = (object) [
    'id' => 1001,
    'formatted_number' => 'INV-2026-001',
    'status' => Invoices_model::STATUS_PAID,
    'total' => 1500000,
    'total_left_to_pay' => 0,
    'datecreated' => '2026-06-09 10:00:00',
    'payments' => [
        [
            'amount' => 1500000,
            'date' => '2026-06-09 10:00:00',
        ],
    ],
];

$payload = $service->getPaymentReadbackPayload(1001);
if (($payload['crm_invoice_id'] ?? 0) !== 1001) {
    throw new RuntimeException('Expected payment readback payload to return crm_invoice_id.');
}
if (($payload['payment_status'] ?? null) !== 'paid') {
    throw new RuntimeException('Expected payment status to map to paid.');
}
if ((float) ($payload['paid_amount'] ?? 0) !== 1500000.0) {
    throw new RuntimeException('Expected payment readback payload to return paid amount.');
}

$service->invoice = (object) [
    'id' => 1002,
    'formatted_number' => 'INV-2026-002',
    'status' => Invoices_model::STATUS_PAID,
    'total' => 1500000,
    'total_left_to_pay' => 0,
    'datecreated' => '2026-06-09 10:00:00',
    'payments' => [],
];

$missing = $service->getPaymentReadbackPayload(1002);
if ($missing !== null) {
    throw new RuntimeException('Expected missing payment data to return null.');
}

$service->invoice = null;
$missingInvoice = $service->getPaymentReadbackPayload(9999);
if ($missingInvoice !== null) {
    throw new RuntimeException('Expected missing invoice to return null.');
}

echo "CrmPaymentReadbackServiceTest passed.\n";
