<?php

defined('BASEPATH') or exit('No direct script access allowed');

class WarehousePayloadProducer
{
    public function buildPayload(array $tenant, array $warehouseRow): array
    {
        require_once __DIR__ . '/CanonicalIntegrationIdentity.php';
        $crmWarehouseId = (int) ($warehouseRow['id'] ?? 0);
        if ($crmWarehouseId <= 0) {
            throw new RuntimeException('Warehouse payload producer requires a valid warehouse id.');
        }
        $crmTenantId = (int) ($tenant['id'] ?? $tenant['crm_tenant_id'] ?? 0);
        $companyId = (int) (kt_integration_hub_crm_company_id($tenant) ?? 0);
        if ($crmTenantId <= 0 || $companyId <= 0) {
            throw new RuntimeException('Warehouse payload requires canonical tenant and company identities.');
        }

        $updatedAt = $this->resolveUpdatedAt($warehouseRow);
        $warehouseCode = trim((string) ($warehouseRow['warehouse_code'] ?? '')) ?: ('CRM-WAREHOUSE-' . $crmWarehouseId);
        return [
            'tenant' => [
                'crm_tenant_id' => $crmTenantId,
                'crm_company_id' => $companyId,
                'canonical_crm_company_id' => $companyId,
                'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
            ],
            'warehouse' => [
                'crm_warehouse_id' => $crmWarehouseId,
                'warehouse_code' => $warehouseCode,
                'warehouse_name' => trim((string) ($warehouseRow['warehouse_name'] ?? '')),
                'status' => ((int) ($warehouseRow['is_active'] ?? 1) === 1) ? 'active' : 'inactive',
                'address' => trim((string) ($warehouseRow['address'] ?? '')),
                'updated_at' => $updatedAt,
            ],
            'identity' => CanonicalIntegrationIdentity::build($tenant, 'warehouse', $crmWarehouseId, $warehouseCode, [
                'warehouse_code' => $warehouseCode,
                'created_at' => (string) ($warehouseRow['created_at'] ?? ''),
            ], $updatedAt, ((int) ($warehouseRow['is_active'] ?? 1) === 1) ? 'active' : 'deleted'),
        ];
    }

    public function buildIdempotencyKey(array $payload): string
    {
        $warehouse = (array) ($payload['warehouse'] ?? []);
        $crmWarehouseId = (int) ($warehouse['crm_warehouse_id'] ?? 0);
        $updatedAt = trim((string) ($warehouse['updated_at'] ?? ''));
        if ($crmWarehouseId <= 0 || $updatedAt === '') {
            throw new RuntimeException('Cannot build idempotency key without crm_warehouse_id and updated_at.');
        }

        return 'warehouse-catalog-v2-' . substr(hash('sha256', (string) ($payload['identity']['source_stable_key'] ?? '') . '|' . $updatedAt . '|' . kt_integration_hub_json_encode($payload)), 0, 48);
    }

    public function buildRequestId(array $payload): string
    {
        $warehouse = (array) ($payload['warehouse'] ?? []);

        return 'warehouse_sync_' . (int) ($warehouse['crm_warehouse_id'] ?? 0) . '_' . bin2hex(random_bytes(6));
    }

    private function resolveUpdatedAt(array $warehouseRow): string
    {
        foreach (['updated_at', 'created_at'] as $field) {
            $candidate = trim((string) ($warehouseRow[$field] ?? ''));
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
