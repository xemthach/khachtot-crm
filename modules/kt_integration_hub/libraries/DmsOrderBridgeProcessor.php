<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/DmsOrderBridgeException.php');
require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/ManualReviewService.php');
require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/NotificationBridgeService.php');

class DmsOrderBridgeProcessor
{
    public const PROVIDER_CODE = 'salesflow_dms';
    private const CANCEL_SUCCESS_CODE = 'INVOICE_DRAFT_CANCELLED';
    private const CANCEL_ALREADY_CODE = 'INVOICE_ALREADY_CANCELLED';
    private const CANCEL_MANUAL_REVIEW_CODE = 'MANUAL_REVIEW_REQUIRED';
    private const CANCEL_LOCKED_CODE = 'INVOICE_LOCKED';
    private $tenantDatabase;

    public function handle(array $tenant, array $connection, array $payload, array $requestContext = []): array
    {
        $order = (array) ($payload['order'] ?? []);
        $items = array_values((array) ($order['items'] ?? []));
        $tenantId = (int) ($tenant['id'] ?? 0);
        $connectionId = (int) ($connection['id'] ?? 0);
        $dmsOrderId = (int) ($order['order_id'] ?? 0);
        $idempotencyKey = trim((string) ($requestContext['idempotency_key'] ?? ''));
        $requestId = trim((string) ($requestContext['request_id'] ?? ''));
        $checksum = sha1(kt_integration_hub_json_encode($payload));

        $this->guardValidPayload($tenantId, $order, $items);
        $this->guardIdempotency($tenantId, $idempotencyKey, $checksum);
        $this->activateTenantDatabase($tenant);

        $client = $this->findCustomer((int) ($order['crm_customer_id'] ?? 0));
        if (!$client) {
            $this->recordFailure($tenantId, $connectionId, $order, $checksum, 'failed', 'CUSTOMER_NOT_MAPPED', 'CRM customer is not mapped for this tenant.');
            throw new DmsOrderBridgeException('CUSTOMER_NOT_MAPPED', 'CRM customer is not mapped for this tenant.');
        }

        $staff = $this->findStaff((int) ($order['crm_staff_id'] ?? 0));
        if (!$staff) {
            $this->recordFailure($tenantId, $connectionId, $order, $checksum, 'failed', 'VALIDATION_ERROR', 'CRM staff is not mapped for this tenant.');
            throw new DmsOrderBridgeException('VALIDATION_ERROR', 'CRM staff is not mapped for this tenant.', 422, [
                'order.crm_staff_id' => ['CRM staff mapping is required.'],
            ]);
        }

        $productsById = $this->resolveProducts($items);
        $existingBridge = $this->getBridgeRecord($tenantId, $dmsOrderId);

        if ($existingBridge && (int) ($existingBridge['crm_invoice_id'] ?? 0) > 0) {
            $invoiceId = (int) $existingBridge['crm_invoice_id'];
            $invoice = $this->getInvoice($invoiceId);
            if (!$invoice) {
                $this->recordFailure($tenantId, $connectionId, $order, $checksum, 'failed', 'VALIDATION_ERROR', 'Mapped CRM invoice could not be found.');
                throw new DmsOrderBridgeException('VALIDATION_ERROR', 'Mapped CRM invoice could not be found.', 422);
            }

            $invoiceStatus = $this->normalizeInvoiceStatus((int) ($invoice->status ?? 0));
            if (($existingBridge['last_payload_checksum'] ?? '') === $checksum && $invoiceStatus === 'draft') {
                $result = $this->buildSuccessResult($order, $invoiceId, $invoiceStatus, 'synced');
                $this->persistSuccess($tenantId, $connectionId, $order, $invoiceId, $invoiceStatus, $checksum, $payload, $requestId, $idempotencyKey);

                return $result;
            }

            if ($invoiceStatus !== 'draft') {
                $this->recordFailure($tenantId, $connectionId, $order, $checksum, 'manual_review', 'INVOICE_ALREADY_ISSUED', 'Invoice is no longer draft and cannot be updated automatically.', $invoiceId, $invoiceStatus);
                throw new DmsOrderBridgeException('INVOICE_ALREADY_ISSUED', 'Invoice is no longer draft and cannot be updated automatically.');
            }

            $this->updateInvoiceDraft($invoiceId, $client, $staff, $order, $items, $productsById);
            $result = $this->buildSuccessResult($order, $invoiceId, 'draft', 'synced');
            $this->persistSuccess($tenantId, $connectionId, $order, $invoiceId, 'draft', $checksum, $payload, $requestId, $idempotencyKey);

            return $result;
        }

        $invoiceId = $this->createInvoiceDraft($client, $staff, $order, $items, $productsById);
        $result = $this->buildSuccessResult($order, $invoiceId, 'draft', 'synced');
        $this->persistSuccess($tenantId, $connectionId, $order, $invoiceId, 'draft', $checksum, $payload, $requestId, $idempotencyKey);

        return $result;
    }

    public function handleCancellation(array $tenant, array $connection, array $payload, array $requestContext = []): array
    {
        $tenantId = (int) ($tenant['id'] ?? 0);
        $connectionId = (int) ($connection['id'] ?? 0);
        $order = (array) ($payload['order'] ?? []);
        $dmsOrderId = (int) ($order['order_id'] ?? 0);
        $requestId = trim((string) ($requestContext['request_id'] ?? ''));
        $idempotencyKey = trim((string) ($requestContext['idempotency_key'] ?? ''));
        $checksum = sha1(kt_integration_hub_json_encode($payload));
        $invoiceId = (int) ($order['crm_invoice_id'] ?? 0);

        $this->guardValidCancellationPayload($tenantId, $order);
        $this->guardIdempotency($tenantId, $idempotencyKey, $checksum);
        $this->activateTenantDatabase($tenant);

        $bridge = $this->getBridgeRecord($tenantId, $dmsOrderId);
        if (!$bridge || (int) ($bridge['crm_invoice_id'] ?? 0) <= 0) {
            $this->recordFailure(
                $tenantId,
                $connectionId,
                $order,
                $checksum,
                'failed',
                'ORDER_NOT_SYNCED',
                'DMS order has not been synced to a CRM invoice draft.',
                null,
                null,
                [
                    'cancel_requested_at' => kt_integration_hub_now(),
                    'cancel_reason' => trim((string) ($order['cancel_reason'] ?? '')) ?: null,
                    'cancel_status' => 'rejected',
                ]
            );
            throw new DmsOrderBridgeException('ORDER_NOT_SYNCED', 'DMS order has not been synced to a CRM invoice draft.');
        }

        $bridgeInvoiceId = (int) ($bridge['crm_invoice_id'] ?? 0);
        if ($invoiceId > 0 && $bridgeInvoiceId > 0 && $invoiceId !== $bridgeInvoiceId) {
            $this->recordFailure(
                $tenantId,
                $connectionId,
                $order,
                $checksum,
                'failed',
                'VALIDATION_ERROR',
                'Payload crm_invoice_id does not match existing CRM bridge mapping.',
                $bridgeInvoiceId,
                (string) ($bridge['invoice_status_snapshot'] ?? null),
                [
                    'cancel_requested_at' => kt_integration_hub_now(),
                    'cancel_reason' => trim((string) ($order['cancel_reason'] ?? '')) ?: null,
                    'cancel_status' => 'rejected',
                ]
            );
            throw new DmsOrderBridgeException('VALIDATION_ERROR', 'Payload crm_invoice_id does not match existing CRM bridge mapping.', 422);
        }

        $invoiceId = $bridgeInvoiceId;
        $invoice = $this->getInvoice($invoiceId);
        if (!$invoice) {
            $this->recordFailure(
                $tenantId,
                $connectionId,
                $order,
                $checksum,
                'failed',
                'INVOICE_NOT_FOUND',
                'Mapped CRM invoice could not be found.',
                $invoiceId,
                null,
                [
                    'cancel_requested_at' => kt_integration_hub_now(),
                    'cancel_reason' => trim((string) ($order['cancel_reason'] ?? '')) ?: null,
                    'cancel_status' => 'failed',
                ]
            );
            throw new DmsOrderBridgeException('INVOICE_NOT_FOUND', 'Mapped CRM invoice could not be found.', 404);
        }

        $invoiceStatusCode = (int) ($invoice->status ?? 0);
        $invoiceStatus = $this->normalizeReadbackInvoiceStatus($invoiceStatusCode);
        $cancelReason = trim((string) ($order['cancel_reason'] ?? '')) ?: null;

        if ($invoiceStatusCode === Invoices_model::STATUS_CANCELLED) {
            $this->persistCancellationSuccess(
                $tenantId,
                $connectionId,
                $order,
                $invoiceId,
                $invoiceStatus,
                $checksum,
                $payload,
                $requestId,
                $idempotencyKey,
                self::CANCEL_ALREADY_CODE,
                'Invoice draft was already cancelled.',
                [
                    'cancel_requested_at' => kt_integration_hub_now(),
                    'cancelled_at' => kt_integration_hub_now(),
                    'cancel_reason' => $cancelReason,
                    'cancel_status' => 'cancelled',
                ]
            );

            return $this->buildCancellationResult($order, $invoiceId, $invoiceStatus, 'cancelled', self::CANCEL_ALREADY_CODE, 'Invoice draft was already cancelled.');
        }

        if ($invoiceStatusCode === Invoices_model::STATUS_DRAFT) {
            $cancelled = (bool) $this->invoicesModel()->mark_as_cancelled($invoiceId);
            if (!$cancelled) {
                $this->recordFailure(
                    $tenantId,
                    $connectionId,
                    $order,
                    $checksum,
                    'failed',
                    'VALIDATION_ERROR',
                    'CRM failed to cancel invoice draft.',
                    $invoiceId,
                    $invoiceStatus,
                    [
                        'cancel_requested_at' => kt_integration_hub_now(),
                        'cancel_reason' => $cancelReason,
                        'cancel_status' => 'failed',
                    ]
                );
                throw new DmsOrderBridgeException('VALIDATION_ERROR', 'CRM failed to cancel invoice draft.', 422);
            }

            $this->persistCancellationSuccess(
                $tenantId,
                $connectionId,
                $order,
                $invoiceId,
                'cancelled',
                $checksum,
                $payload,
                $requestId,
                $idempotencyKey,
                self::CANCEL_SUCCESS_CODE,
                'Invoice draft cancelled.',
                [
                    'cancel_requested_at' => kt_integration_hub_now(),
                    'cancelled_at' => kt_integration_hub_now(),
                    'cancel_reason' => $cancelReason,
                    'cancel_status' => 'cancelled',
                ]
            );

            return $this->buildCancellationResult($order, $invoiceId, 'cancelled', 'cancelled', self::CANCEL_SUCCESS_CODE, 'Invoice draft cancelled.');
        }

        if (in_array($invoiceStatusCode, [Invoices_model::STATUS_UNPAID, Invoices_model::STATUS_PARTIALLY, Invoices_model::STATUS_OVERDUE], true)) {
            $reviewType = match ($invoiceStatusCode) {
                Invoices_model::STATUS_UNPAID => ManualReviewService::TYPE_ORDER_CANCEL_UNPAID,
                Invoices_model::STATUS_PARTIALLY => ManualReviewService::TYPE_ORDER_CANCEL_PARTIALLY_PAID,
                Invoices_model::STATUS_OVERDUE => ManualReviewService::TYPE_ORDER_CANCEL_OVERDUE,
            };
            $this->createManualReviewForCancellation($tenantId, $connectionId, $order, $invoiceId, $invoiceStatus, $reviewType, 'Invoice requires manual review before cancellation.');
            $this->recordFailure(
                $tenantId,
                $connectionId,
                $order,
                $checksum,
                'manual_review',
                self::CANCEL_MANUAL_REVIEW_CODE,
                'Invoice requires manual review before cancellation.',
                $invoiceId,
                $invoiceStatus,
                [
                    'cancel_requested_at' => kt_integration_hub_now(),
                    'cancel_reason' => $cancelReason,
                    'cancel_status' => 'manual_review',
                ]
            );
            throw new DmsOrderBridgeException(self::CANCEL_MANUAL_REVIEW_CODE, 'Invoice requires manual review before cancellation.');
        }

        if ($invoiceStatusCode === Invoices_model::STATUS_PAID) {
            $this->createManualReviewForCancellation($tenantId, $connectionId, $order, $invoiceId, $invoiceStatus, ManualReviewService::TYPE_INVOICE_LOCKED, 'Invoice is locked and cannot be auto-cancelled.');
            $this->publishNotification($tenantId, NotificationBridgeService::EVENT_ORDER_LOCKED, 'order-locked-' . (int) ($order['order_id'] ?? 0) . '-' . sha1($checksum), [
                'order_id' => (int) ($order['order_id'] ?? 0),
                'crm_invoice_id' => $invoiceId,
                'invoice_status' => $invoiceStatus,
                'updated_at' => kt_integration_hub_now(),
                'cancel_status' => 'locked',
            ], 'Order locked');
            $this->recordFailure(
                $tenantId,
                $connectionId,
                $order,
                $checksum,
                'manual_review',
                self::CANCEL_LOCKED_CODE,
                'Invoice is locked and cannot be auto-cancelled.',
                $invoiceId,
                $invoiceStatus,
                [
                    'cancel_requested_at' => kt_integration_hub_now(),
                    'cancel_reason' => $cancelReason,
                    'cancel_status' => 'locked',
                ]
            );
            throw new DmsOrderBridgeException(self::CANCEL_LOCKED_CODE, 'Invoice is locked and cannot be auto-cancelled.');
        }

        $this->recordFailure(
            $tenantId,
            $connectionId,
            $order,
            $checksum,
            'manual_review',
            self::CANCEL_MANUAL_REVIEW_CODE,
            'Invoice requires manual review before cancellation.',
            $invoiceId,
            $invoiceStatus,
            [
                'cancel_requested_at' => kt_integration_hub_now(),
                'cancel_reason' => $cancelReason,
                'cancel_status' => 'manual_review',
            ]
        );
        $this->createManualReviewForCancellation($tenantId, $connectionId, $order, $invoiceId, $invoiceStatus, ManualReviewService::TYPE_INVOICE_LOCKED, 'Invoice requires manual review before cancellation.');
        throw new DmsOrderBridgeException(self::CANCEL_MANUAL_REVIEW_CODE, 'Invoice requires manual review before cancellation.');
    }

    protected function guardValidPayload(int $tenantId, array $order, array $items): void
    {
        if ($tenantId <= 0) {
            throw new DmsOrderBridgeException('TENANT_SCOPE_MISMATCH', 'Tenant context is invalid.');
        }

        if ((int) ($order['order_id'] ?? 0) <= 0 || trim((string) ($order['order_number'] ?? '')) === '') {
            throw new DmsOrderBridgeException('VALIDATION_ERROR', 'Order identity is required.', 422, [
                'order.order_id' => ['Order identity is required.'],
            ]);
        }

        if (strtolower(trim((string) ($order['status'] ?? ''))) !== 'submitted') {
            throw new DmsOrderBridgeException('VALIDATION_ERROR', 'Only submitted DMS orders can create CRM invoice drafts.', 422, [
                'order.status' => ['Only submitted orders can be bridged.'],
            ]);
        }

        if ($items === []) {
            throw new DmsOrderBridgeException('VALIDATION_ERROR', 'Order items are required.', 422, [
                'order.items' => ['At least one order item is required.'],
            ]);
        }
    }

    protected function guardValidCancellationPayload(int $tenantId, array $order): void
    {
        if ($tenantId <= 0) {
            throw new DmsOrderBridgeException('TENANT_SCOPE_MISMATCH', 'Tenant context is invalid.');
        }

        if ((int) ($order['order_id'] ?? 0) <= 0 || trim((string) ($order['order_number'] ?? '')) === '') {
            throw new DmsOrderBridgeException('VALIDATION_ERROR', 'Order identity is required.', 422, [
                'order.order_id' => ['Order identity is required.'],
            ]);
        }

        if (strtolower(trim((string) ($order['status'] ?? ''))) !== 'cancelled') {
            throw new DmsOrderBridgeException('VALIDATION_ERROR', 'Only cancelled DMS orders can request CRM invoice cancellation.', 422, [
                'order.status' => ['Only cancelled orders can request cancellation.'],
            ]);
        }

        if ((int) ($order['crm_invoice_id'] ?? 0) <= 0) {
            throw new DmsOrderBridgeException('VALIDATION_ERROR', 'crm_invoice_id is required for cancellation requests.', 422, [
                'order.crm_invoice_id' => ['crm_invoice_id is required.'],
            ]);
        }
    }

    protected function guardIdempotency(int $tenantId, string $idempotencyKey, string $checksum): void
    {
        if ($idempotencyKey === '') {
            throw new DmsOrderBridgeException('VALIDATION_ERROR', 'Idempotency key is required.', 422);
        }

        $existing = $this->findIdempotencyLog($tenantId, $idempotencyKey);
        if (!$existing) {
            return;
        }

        $existingChecksum = (string) ($existing['checksum'] ?? (($existing['context']['checksum'] ?? '') ?: ''));
        if ($existingChecksum !== '' && $existingChecksum !== $checksum) {
            throw new DmsOrderBridgeException('IDEMPOTENCY_KEY_REUSED', 'Idempotency key was already used with a different payload.');
        }
    }

    protected function resolveProducts(array $items): array
    {
        $productsById = [];

        foreach ($items as $item) {
            $crmProductId = (int) ($item['crm_product_id'] ?? 0);
            if ($crmProductId <= 0) {
                throw new DmsOrderBridgeException('PRODUCT_NOT_MAPPED', 'CRM product mapping is required for every order item.');
            }

            if (isset($productsById[$crmProductId])) {
                continue;
            }

            $product = $this->findProduct($crmProductId);
            if (!$product) {
                throw new DmsOrderBridgeException('PRODUCT_NOT_MAPPED', 'CRM product is not mapped for this tenant.');
            }

            $productsById[$crmProductId] = $product;
        }

        return $productsById;
    }

    protected function createInvoiceDraft(array $client, array $staff, array $order, array $items, array $productsById): int
    {
        $invoiceModel = $this->invoicesModel();
        $invoiceId = (int) $invoiceModel->add($this->buildInvoiceData($client, $staff, $order, $items, $productsById));

        if ($invoiceId <= 0) {
            throw new DmsOrderBridgeException('VALIDATION_ERROR', 'CRM failed to create invoice draft.', 422);
        }

        return $invoiceId;
    }

    protected function updateInvoiceDraft(int $invoiceId, array $client, array $staff, array $order, array $items, array $productsById): void
    {
        $invoice = $this->getInvoice($invoiceId);
        $removedItems = [];
        foreach ((array) ($invoice->items ?? []) as $existingItem) {
            $removedItems[] = (int) ($existingItem['id'] ?? 0);
        }

        $invoiceData = $this->buildInvoiceData($client, $staff, $order, $items, $productsById);
        unset($invoiceData['save_as_draft']);

        $updated = $this->invoicesModel()->update(array_merge(
            $invoiceData,
            [
                'removed_items' => array_values(array_filter($removedItems)),
                'items' => [],
            ]
        ), $invoiceId);

        if (!$updated) {
            throw new DmsOrderBridgeException('VALIDATION_ERROR', 'CRM failed to update invoice draft.', 422);
        }
    }

    protected function buildInvoiceData(array $client, array $staff, array $order, array $items, array $productsById): array
    {
        $orderDate = $this->normalizeDate((string) ($order['ordered_at'] ?? ''));
        $currency = $this->resolveCurrencyId((string) ($order['currency'] ?? 'VND'), $client);
        $subtotal = array_reduce($items, static function (float $sum, array $item): float {
            $quantity = (float) ($item['qty'] ?? $item['quantity'] ?? 0);
            $rate = (float) ($item['unit_price'] ?? 0);

            return $sum + ($quantity * $rate);
        }, 0.0);
        $discountTotal = max((float) ($order['discount_total'] ?? 0), 0);
        $taxTotal = max((float) ($order['tax_total'] ?? 0), 0);
        $grandTotal = (float) ($order['grand_total'] ?? ($subtotal - $discountTotal + $taxTotal));

        return [
            'clientid' => (int) ($client['userid'] ?? 0),
            'date' => $orderDate,
            'duedate' => $orderDate,
            'currency' => $currency,
            'sale_agent' => (int) ($staff['staffid'] ?? 0),
            'billing_street' => (string) ($client['billing_street'] ?? $client['address'] ?? ''),
            'billing_city' => (string) ($client['billing_city'] ?? $client['city'] ?? ''),
            'billing_state' => (string) ($client['billing_state'] ?? $client['state'] ?? ''),
            'billing_zip' => (string) ($client['billing_zip'] ?? $client['zip'] ?? ''),
            'billing_country' => (int) ($client['billing_country'] ?? $client['country'] ?? 0),
            'shipping_street' => (string) ($client['shipping_street'] ?? ''),
            'shipping_city' => (string) ($client['shipping_city'] ?? ''),
            'shipping_state' => (string) ($client['shipping_state'] ?? ''),
            'shipping_zip' => (string) ($client['shipping_zip'] ?? ''),
            'shipping_country' => (int) ($client['shipping_country'] ?? 0),
            'allowed_payment_modes' => [],
            'save_as_draft' => true,
            'subtotal' => $subtotal,
            'discount_percent' => 0,
            'discount_total' => $discountTotal,
            'discount_type' => 'before_tax',
            'total_tax' => $taxTotal,
            'adjustment' => 0,
            'total' => $grandTotal,
            'clientnote' => trim((string) ($order['notes'] ?? '')),
            'adminnote' => $this->buildAdminNote($order),
            'newitems' => $this->buildInvoiceItems($items, $productsById),
        ];
    }

    protected function buildInvoiceItems(array $items, array $productsById): array
    {
        $invoiceItems = [];
        foreach (array_values($items) as $index => $item) {
            $crmProductId = (int) ($item['crm_product_id'] ?? 0);
            $product = $productsById[$crmProductId] ?? [];
            $description = trim((string) ($item['name'] ?? $product['description'] ?? ('Product #' . $crmProductId)));
            $longDescriptionParts = array_filter([
                trim((string) ($item['sku'] ?? '')) !== '' ? 'SKU: ' . trim((string) ($item['sku'] ?? '')) : '',
                trim((string) ($item['notes'] ?? '')),
            ]);

            $invoiceItems[$index + 1] = [
                'description' => $description,
                'long_description' => implode("\n", $longDescriptionParts),
                'qty' => (float) ($item['qty'] ?? $item['quantity'] ?? 0),
                'unit' => trim((string) ($item['unit'] ?? $product['unit'] ?? '')),
                'taxname' => [],
                'rate' => (float) ($item['unit_price'] ?? 0),
                'order' => $index + 1,
            ];
        }

        return $invoiceItems;
    }

    protected function buildAdminNote(array $order): string
    {
        $parts = [
            'Salesflow DMS Order #' . trim((string) ($order['order_number'] ?? '')),
            'DMS Order ID: ' . (int) ($order['order_id'] ?? 0),
            'Store ID: ' . (int) ($order['store_id'] ?? 0),
        ];

        $storeContext = array_filter([
            trim((string) ($order['store_name'] ?? '')) !== '' ? 'Điểm bán: ' . trim((string) $order['store_name']) : '',
            trim((string) ($order['store_code'] ?? '')) !== '' ? 'Mã điểm bán: ' . trim((string) $order['store_code']) : '',
            trim((string) ($order['store_address'] ?? '')) !== '' ? 'Địa chỉ điểm bán: ' . trim((string) $order['store_address']) : '',
        ]);

        return implode("\n", array_merge($parts, $storeContext));
    }

    protected function normalizeDate(string $value): string
    {
        $timestamp = strtotime($value);

        return $timestamp !== false ? date('Y-m-d', $timestamp) : date('Y-m-d');
    }

    protected function resolveCurrencyId(string $currencyCode, array $client): int
    {
        $currencyCode = strtoupper(trim($currencyCode));
        if ($currencyCode !== '' && function_exists('get_currency')) {
            $currency = get_currency($currencyCode);
            if ($currency && isset($currency->id)) {
                return (int) $currency->id;
            }
        }

        $clientDefaultCurrency = (int) ($client['default_currency'] ?? 0);
        if ($clientDefaultCurrency > 0) {
            return $clientDefaultCurrency;
        }

        if (function_exists('get_base_currency')) {
            $baseCurrency = get_base_currency();
            if ($baseCurrency && isset($baseCurrency->id)) {
                return (int) $baseCurrency->id;
            }
        }

        return 0;
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
        $this->integrationModel()->upsert_dms_order_bridge($tenantId, [
            'provider_code' => self::PROVIDER_CODE,
            'dms_order_id' => (int) ($order['order_id'] ?? 0),
            'dms_order_number' => trim((string) ($order['order_number'] ?? '')),
            'crm_invoice_id' => $invoiceId,
            'bridge_status' => 'synced',
            'invoice_status_snapshot' => $invoiceStatus,
            'last_payload_checksum' => $checksum,
            'last_synced_at' => kt_integration_hub_now(),
            'error_code' => null,
            'error_message' => null,
        ]);
        $this->integrationModel()->upsert_dms_order_projection_from_payload($tenantId, $order, [
            'provider_code' => self::PROVIDER_CODE,
            'dms_order_id' => (int) ($order['order_id'] ?? 0),
            'dms_order_number' => trim((string) ($order['order_number'] ?? '')),
            'crm_invoice_id' => $invoiceId,
            'bridge_status' => 'synced',
            'invoice_status_snapshot' => $invoiceStatus,
            'last_payload_checksum' => $checksum,
            'last_synced_at' => kt_integration_hub_now(),
        ], $payload);

        $this->integrationModel()->upsert_provider_entity_link(
            $tenantId,
            self::PROVIDER_CODE,
            $connectionId,
            'order',
            $invoiceId,
            (string) ((int) ($order['order_id'] ?? 0)),
            [
                'payload' => $payload,
                'crm_invoice_id' => $invoiceId,
                'request_id' => $requestId,
                'idempotency_key' => $idempotencyKey,
            ],
            'inbound'
        );

        $this->integrationModel()->log('info', 'dms.order.bridge.synced', 'DMS order bridged to CRM invoice draft.', [
            'request_id' => $requestId,
            'idempotency_key' => $idempotencyKey,
            'checksum' => $checksum,
            'dms_order_id' => (int) ($order['order_id'] ?? 0),
            'crm_invoice_id' => $invoiceId,
            'invoice_status' => $invoiceStatus,
        ], $tenantId, $connectionId, self::PROVIDER_CODE);
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
        $payload = array_merge([
            'provider_code' => self::PROVIDER_CODE,
            'dms_order_id' => (int) ($order['order_id'] ?? 0),
            'dms_order_number' => trim((string) ($order['order_number'] ?? '')),
            'crm_invoice_id' => $invoiceId,
            'bridge_status' => $bridgeStatus,
            'invoice_status_snapshot' => $invoiceStatus,
            'last_payload_checksum' => $checksum,
            'last_synced_at' => kt_integration_hub_now(),
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ], $bridgeExtras);

        $this->integrationModel()->upsert_dms_order_bridge($tenantId, $payload);
        $this->integrationModel()->upsert_dms_order_projection_from_payload($tenantId, $order, $payload);

        $this->integrationModel()->log('warning', 'dms.order.bridge.failed', $errorMessage, [
            'dms_order_id' => (int) ($order['order_id'] ?? 0),
            'bridge_status' => $bridgeStatus,
            'error_code' => $errorCode,
            'crm_invoice_id' => $invoiceId,
            'invoice_status' => $invoiceStatus,
        ], $tenantId, $connectionId, self::PROVIDER_CODE);
    }

    protected function persistCancellationSuccess(
        int $tenantId,
        int $connectionId,
        array $order,
        int $invoiceId,
        string $invoiceStatus,
        string $checksum,
        array $payload,
        string $requestId,
        string $idempotencyKey,
        string $eventCode,
        string $message,
        array $bridgeExtras = []
    ): void {
        $bridgePayload = array_merge([
            'provider_code' => self::PROVIDER_CODE,
            'dms_order_id' => (int) ($order['order_id'] ?? 0),
            'dms_order_number' => trim((string) ($order['order_number'] ?? '')),
            'crm_invoice_id' => $invoiceId,
            'bridge_status' => 'synced',
            'invoice_status_snapshot' => $invoiceStatus,
            'last_payload_checksum' => $checksum,
            'last_synced_at' => kt_integration_hub_now(),
            'error_code' => null,
            'error_message' => null,
        ], $bridgeExtras);

        $this->integrationModel()->upsert_dms_order_bridge($tenantId, $bridgePayload);
        $this->integrationModel()->upsert_dms_order_projection_from_payload($tenantId, $order, $bridgePayload, $payload);
        $this->integrationModel()->upsert_provider_entity_link(
            $tenantId,
            self::PROVIDER_CODE,
            $connectionId,
            'order',
            $invoiceId,
            (string) ((int) ($order['order_id'] ?? 0)),
            [
                'payload' => $payload,
                'crm_invoice_id' => $invoiceId,
                'request_id' => $requestId,
                'idempotency_key' => $idempotencyKey,
                'cancel_status' => (string) ($bridgePayload['cancel_status'] ?? ''),
            ],
            'inbound'
        );
        $this->integrationModel()->log('info', 'dms.order.bridge.cancelled', $message, [
            'request_id' => $requestId,
            'idempotency_key' => $idempotencyKey,
            'checksum' => $checksum,
            'code' => $eventCode,
            'dms_order_id' => (int) ($order['order_id'] ?? 0),
            'crm_invoice_id' => $invoiceId,
            'invoice_status' => $invoiceStatus,
            'cancel_status' => (string) ($bridgePayload['cancel_status'] ?? ''),
        ], $tenantId, $connectionId, self::PROVIDER_CODE);

        $this->publishNotification($tenantId, NotificationBridgeService::EVENT_ORDER_CANCELLED, 'order-cancelled-' . (int) ($order['order_id'] ?? 0) . '-' . sha1($checksum), [
            'order_id' => (int) ($order['order_id'] ?? 0),
            'crm_invoice_id' => $invoiceId,
            'invoice_status' => $invoiceStatus,
            'updated_at' => kt_integration_hub_now(),
            'cancel_status' => (string) ($bridgePayload['cancel_status'] ?? 'cancelled'),
        ], 'Order cancelled');
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
        try {
            $service = new ManualReviewService();
            $priority = in_array($invoiceStatus, ['paid'], true) ? 'HIGH' : (in_array($invoiceStatus, ['partially', 'overdue'], true) ? 'MEDIUM' : 'LOW');

            return $service->createReview([
                'tenant_id' => $tenantId,
                'entity_type' => 'order',
                'entity_id' => (int) ($order['order_id'] ?? 0),
                'review_type' => $reviewType,
                'review_status' => ManualReviewService::STATUS_OPEN,
                'priority' => $priority,
                'review_reason' => $reason,
                'source_system' => self::PROVIDER_CODE,
                'payload_json' => kt_integration_hub_json_encode([
                    'tenant' => [
                        'tenant_id' => $tenantId,
                        'connection_id' => $connectionId,
                    ],
                    'order' => $order,
                    'invoice_status' => $invoiceStatus,
                    'crm_invoice_id' => $invoiceId,
                ]),
            ]);
        } catch (Throwable $e) {
            $this->integrationModel()->log('warning', 'manual_review.create.failed', 'Manual review could not be created.', [
                'tenant_id' => $tenantId,
                'connection_id' => $connectionId,
                'dms_order_id' => (int) ($order['order_id'] ?? 0),
                'crm_invoice_id' => $invoiceId,
                'review_type' => $reviewType,
                'message' => $e->getMessage(),
            ], $tenantId, $connectionId, self::PROVIDER_CODE);
        }

        return null;
    }

    protected function buildSuccessResult(array $order, int $invoiceId, string $invoiceStatus, string $mappingStatus): array
    {
        return [
            'order_id' => (int) ($order['order_id'] ?? 0),
            'crm_invoice_id' => $invoiceId,
            'invoice_status' => $invoiceStatus,
            'mapping_status' => $mappingStatus,
        ];
    }

    protected function buildCancellationResult(array $order, int $invoiceId, string $invoiceStatus, string $cancelStatus, string $code, string $message): array
    {
        return [
            'code' => $code,
            'message' => $message,
            'order_id' => (int) ($order['order_id'] ?? 0),
            'crm_invoice_id' => $invoiceId,
            'invoice_status' => $invoiceStatus,
            'cancel_status' => $cancelStatus,
        ];
    }

    protected function findCustomer(int $crmCustomerId): ?array
    {
        if ($crmCustomerId <= 0) {
            return null;
        }

        $row = $this->db()
            ->where('userid', $crmCustomerId)
            ->get(db_prefix() . 'clients')
            ->row_array();

        return $row ?: null;
    }

    protected function findStaff(int $crmStaffId): ?array
    {
        if ($crmStaffId <= 0) {
            return null;
        }

        $row = $this->db()
            ->where('staffid', $crmStaffId)
            ->get(db_prefix() . 'staff')
            ->row_array();

        return $row ?: null;
    }

    protected function findProduct(int $crmProductId): ?array
    {
        if ($crmProductId <= 0) {
            return null;
        }

        $row = $this->db()
            ->where('id', $crmProductId)
            ->get(db_prefix() . 'items')
            ->row_array();

        return $row ?: null;
    }

    protected function getInvoice(int $invoiceId): ?object
    {
        if ($invoiceId <= 0) {
            return null;
        }

        $invoice = $this->invoicesModel()->get($invoiceId);

        return $invoice ?: null;
    }

    protected function getBridgeRecord(int $tenantId, int $dmsOrderId): ?array
    {
        return $this->integrationModel()->get_dms_order_bridge($tenantId, $dmsOrderId);
    }

    protected function findIdempotencyLog(int $tenantId, string $idempotencyKey): ?array
    {
        return $this->integrationModel()->find_log_by_context_value(self::PROVIDER_CODE, $tenantId, 'idempotency_key', $idempotencyKey);
    }

    protected function normalizeInvoiceStatus(int $status): string
    {
        return match ($status) {
            Invoices_model::STATUS_DRAFT => 'draft',
            Invoices_model::STATUS_PAID => 'paid',
            Invoices_model::STATUS_PARTIALLY => 'partially_paid',
            Invoices_model::STATUS_OVERDUE => 'overdue',
            Invoices_model::STATUS_CANCELLED => 'cancelled',
            default => 'issued',
        };
    }

    protected function normalizeReadbackInvoiceStatus(int $status): string
    {
        return match ($status) {
            Invoices_model::STATUS_DRAFT => 'draft',
            Invoices_model::STATUS_PAID => 'paid',
            Invoices_model::STATUS_PARTIALLY => 'partially',
            Invoices_model::STATUS_OVERDUE => 'overdue',
            Invoices_model::STATUS_CANCELLED => 'cancelled',
            default => 'unpaid',
        };
    }

    protected function db()
    {
        return $this->tenantDatabase ?: get_instance()->db;
    }

    protected function invoicesModel()
    {
        $CI = get_instance();
        if (!class_exists('Invoices_model', false)) {
            $CI->load->model('invoices_model');
        }

        if ($this->tenantDatabase) {
            $CI->invoices_model->db = $this->tenantDatabase;
        }

        return $CI->invoices_model;
    }

    protected function tenantDb(array $tenant)
    {
        $CI = get_instance();
        $dbName = trim((string) ($tenant['db_name'] ?? ''));
        $dbHost = trim((string) ($tenant['db_host'] ?? ''));
        $dbUser = trim((string) ($tenant['db_user'] ?? ''));
        $dbPort = trim((string) ($tenant['db_port'] ?? '3306'));
        $password = '';

        if ($dbName === '' || $dbHost === '' || $dbUser === '') {
            throw new DmsOrderBridgeException('TENANT_SCOPE_MISMATCH', 'Tenant database context is incomplete.', 409);
        }

        if (! empty($tenant['db_password_encrypted'])) {
            $password = (string) $CI->encryption->decrypt($tenant['db_password_encrypted']);
        }

        $db = $this->connectTenantDb($dbHost, $dbUser, $password, $dbName, $dbPort);
        if ($db) {
            return $db;
        }

        $fallbackUser = defined('APP_DB_USERNAME') ? trim((string) APP_DB_USERNAME) : '';
        $fallbackPassword = defined('APP_DB_PASSWORD') ? (string) APP_DB_PASSWORD : '';
        if ($fallbackUser !== '') {
            $db = $this->connectTenantDb($dbHost, $fallbackUser, $fallbackPassword, $dbName, $dbPort);
            if ($db) {
                return $db;
            }
        }

        throw new DmsOrderBridgeException('TENANT_SCOPE_MISMATCH', 'Tenant database is unavailable.', 409);
    }

    protected function activateTenantDatabase(array $tenant): void
    {
        if (! function_exists('get_instance')) {
            return;
        }

        $CI = get_instance();
        $landlordDb = $CI->config->item('kt_saas_landlord_db') ?: $CI->db;
        $this->tenantDatabase = $this->tenantDb($tenant);
        $CI->config->set_item('kt_saas_landlord_db', $landlordDb);
        $CI->db = $this->tenantDatabase;
    }

    protected function connectTenantDb(string $host, string $user, string $password, string $database, string $port)
    {
        $CI = get_instance();
        $config = [
            'dsn' => '',
            'hostname' => $host,
            'username' => $user,
            'password' => $password,
            'database' => $database,
            'dbdriver' => defined('APP_DB_DRIVER') ? APP_DB_DRIVER : 'mysqli',
            'dbprefix' => db_prefix(),
            'pconnect' => false,
            'db_debug' => false,
            'cache_on' => false,
            'cachedir' => '',
            'char_set' => defined('APP_DB_CHARSET') ? APP_DB_CHARSET : 'utf8mb4',
            'dbcollat' => defined('APP_DB_COLLATION') ? APP_DB_COLLATION : 'utf8mb4_unicode_ci',
            'swap_pre' => '',
            'encrypt' => false,
            'compress' => false,
            'stricton' => false,
            'failover' => [],
            'save_queries' => true,
            'port' => $port,
        ];

        try {
            $db = $CI->load->database($config, true);

            return $db && $db->initialize() ? $db : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    protected function integrationModel()
    {
        $CI = get_instance();
        if (!isset($CI->Kt_integration_model)) {
            $CI->load->model(KT_INTEGRATION_HUB_MODULE . '/Kt_integration_model');
        }

        return $CI->Kt_integration_model;
    }

    private function publishNotification(int $tenantId, string $eventType, string $eventCode, array $payload, string $title, string $message = ''): void
    {
        try {
            $service = new NotificationBridgeService();
            $service->createNotification([
                'tenant_id' => $tenantId,
                'event_type' => $eventType,
                'event_code' => $eventCode,
                'title' => $title,
                'message' => $message !== '' ? $message : $title,
                'target_type' => 'tenant',
                'target_id' => $tenantId,
                'priority' => in_array($eventType, [NotificationBridgeService::EVENT_ORDER_LOCKED], true) ? 'HIGH' : 'MEDIUM',
                'payload' => $payload,
            ]);
        } catch (Throwable $e) {
            $this->integrationModel()->log('warning', 'notification.publish.failed', 'Notification event could not be published.', [
                'tenant_id' => $tenantId,
                'event_type' => $eventType,
                'event_code' => $eventCode,
                'message' => $e->getMessage(),
            ], $tenantId, 0, self::PROVIDER_CODE);
        }
    }
}
