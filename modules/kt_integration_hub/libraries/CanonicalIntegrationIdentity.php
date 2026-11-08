<?php

defined('BASEPATH') or exit('No direct script access allowed');

final class CanonicalIntegrationIdentity
{
    public static function build(array $tenant, string $entityType, int $numericId, string $businessKey, array $fingerprintFields, string $updatedAt, string $lifecycleState = 'active'): array
    {
        $tenantId = (int) ($tenant['id'] ?? $tenant['crm_tenant_id'] ?? 0);
        $companyId = (int) (kt_integration_hub_crm_company_id($tenant) ?? 0);
        $entityType = strtolower(trim($entityType));
        $businessKey = strtoupper(trim($businessKey));
        if ($tenantId <= 0 || $companyId <= 0 || $numericId <= 0 || $entityType === '' || $businessKey === '') {
            throw new RuntimeException('Canonical integration identity requires tenant, company, entity, numeric id, and a stable business key.');
        }

        $canonical = static function ($value) use (&$canonical) {
            if (!is_array($value)) {
                return is_string($value) ? trim($value) : $value;
            }
            if (array_keys($value) !== range(0, count($value) - 1)) {
                ksort($value);
            }
            foreach ($value as $key => $item) {
                $value[$key] = $canonical($item);
            }
            return $value;
        };
        $fingerprintFields = $canonical($fingerprintFields);
        $stableDigest = hash('sha256', implode('|', ['khachtot_crm', $tenantId, $companyId, $entityType, $businessKey]));
        $fingerprint = hash('sha256', kt_integration_hub_json_encode($fingerprintFields));
        $timestamp = strtotime($updatedAt) ?: time();

        return [
            'source_system' => 'khachtot_crm',
            'source_tenant_id' => $tenantId,
            'source_company_id' => $companyId,
            'entity_type' => $entityType,
            'source_numeric_id' => $numericId,
            'source_stable_key' => 'crm_' . $entityType . '_' . substr($stableDigest, 0, 40),
            'source_fingerprint' => $fingerprint,
            'source_version' => max(1, $timestamp),
            'lifecycle_state' => in_array($lifecycleState, ['active','deleted','recreated','merged','superseded','tombstoned'], true) ? $lifecycleState : 'active',
            'created_at_source' => null,
            'updated_at_source' => gmdate('c', $timestamp),
        ];
    }
}
