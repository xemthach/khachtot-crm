<?php

defined('BASEPATH') or exit('No direct script access allowed');

class ProductPayloadProducer
{
    public function buildPayload(array $tenant, array $itemRow, ?array $inventoryRow = null, ?array $primaryBarcodeRow = null): array
    {
        require_once __DIR__ . '/CanonicalIntegrationIdentity.php';
        $crmProductId = (int) ($itemRow['id'] ?? 0);
        if ($crmProductId <= 0) {
            throw new RuntimeException('Product payload producer requires a valid product id.');
        }
        $crmTenantId = (int) ($tenant['id'] ?? $tenant['crm_tenant_id'] ?? 0);
        $companyId = (int) (kt_integration_hub_crm_company_id($tenant) ?? 0);
        if ($crmTenantId <= 0 || $companyId <= 0) {
            throw new RuntimeException('Product payload requires canonical tenant and company identities.');
        }

        $updatedAt = $this->resolveUpdatedAt($itemRow, $inventoryRow);
        $sku = $this->resolveSku($crmProductId, $inventoryRow);
        $stableBusinessKey = trim((string) ($itemRow['commodity_code'] ?? '')) ?: $sku;
        $identity = CanonicalIntegrationIdentity::build($tenant, 'product', $crmProductId, $stableBusinessKey, [
            'business_key' => strtoupper($stableBusinessKey),
            'sku' => $sku,
            'barcode' => $this->resolveBarcode($inventoryRow, $primaryBarcodeRow),
            'created_at' => (string) ($itemRow['datecreated'] ?? ''),
        ], $updatedAt, ((int) ($inventoryRow['is_active'] ?? 1) === 1) ? 'active' : 'deleted');

        return [
            'tenant' => [
                'crm_tenant_id' => $crmTenantId,
                'crm_company_id' => $companyId,
                'canonical_crm_company_id' => $companyId,
                'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
            ],
            'product' => [
                'crm_product_id' => $crmProductId,
                'sku' => $sku,
                'barcode' => $this->resolveBarcode($inventoryRow, $primaryBarcodeRow),
                'name' => trim((string) ($itemRow['description'] ?? '')) ?: ('Product #' . $crmProductId),
                'description' => trim((string) ($itemRow['long_description'] ?? '')),
                'variant_code' => $this->resolveVariantCode($inventoryRow, $primaryBarcodeRow),
                'unit' => trim((string) ($itemRow['unit'] ?? '')),
                'status' => $this->resolveStatus($inventoryRow),
                'base_price' => isset($itemRow['rate']) ? (float) $itemRow['rate'] : 0.0,
                'updated_at' => $updatedAt,
            ],
            'identity' => $identity,
        ];
    }

    public function buildIdempotencyKey(array $payload): string
    {
        $product = (array) ($payload['product'] ?? []);
        $crmProductId = (int) ($product['crm_product_id'] ?? 0);
        $updatedAt = trim((string) ($product['updated_at'] ?? ''));
        if ($crmProductId <= 0 || $updatedAt === '') {
            throw new RuntimeException('Cannot build idempotency key without crm_product_id and updated_at.');
        }

        $stableKey = (string) ($payload['identity']['source_stable_key'] ?? '');
        return 'product-v3-' . substr(hash('sha256', $stableKey . '|' . $updatedAt . '|' . kt_integration_hub_json_encode($payload)), 0, 48);
    }

    public function buildRequestId(array $payload): string
    {
        $product = (array) ($payload['product'] ?? []);

        return 'product_sync_' . (int) ($product['crm_product_id'] ?? 0) . '_' . bin2hex(random_bytes(6));
    }

    private function resolveSku(int $crmProductId, ?array $inventoryRow): string
    {
        $sku = trim((string) ($inventoryRow['sku'] ?? ''));
        if ($sku !== '') {
            return strtoupper($sku);
        }

        return 'CRM-ITEM-' . $crmProductId;
    }

    private function resolveBarcode(?array $inventoryRow, ?array $primaryBarcodeRow): ?string
    {
        $candidates = [
            trim((string) ($primaryBarcodeRow['barcode'] ?? '')),
            trim((string) ($inventoryRow['primary_barcode'] ?? '')),
            trim((string) ($inventoryRow['barcode'] ?? '')),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveVariantCode(?array $inventoryRow, ?array $primaryBarcodeRow): ?string
    {
        $candidates = [
            trim((string) ($primaryBarcodeRow['unit_type'] ?? '')),
            trim((string) ($inventoryRow['primary_barcode_unit_type'] ?? '')),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveStatus(?array $inventoryRow): string
    {
        return ((int) ($inventoryRow['is_active'] ?? 1) === 1) ? 'active' : 'inactive';
    }

    private function resolveUpdatedAt(array $itemRow, ?array $inventoryRow): string
    {
        $candidates = [
            (string) ($itemRow['_bridge_updated_at'] ?? ''),
            (string) ($inventoryRow['updated_at'] ?? ''),
            (string) ($itemRow['updated_at'] ?? ''),
            (string) ($itemRow['dateupdated'] ?? ''),
            (string) ($itemRow['datecreated'] ?? ''),
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            $timestamp = strtotime($candidate);
            if ($timestamp !== false) {
                return gmdate('c', $timestamp);
            }
        }

        return gmdate('c');
    }
}
