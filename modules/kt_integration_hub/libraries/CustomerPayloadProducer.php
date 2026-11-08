<?php

defined('BASEPATH') or exit('No direct script access allowed');

class CustomerPayloadProducer
{
    public function buildPayload(array $tenant, array $clientRow, ?array $primaryContact = null, array $contacts = []): array
    {
        require_once __DIR__ . '/CanonicalIntegrationIdentity.php';
        $crmCustomerId = (int) ($clientRow['userid'] ?? 0);
        if ($crmCustomerId <= 0) {
            throw new RuntimeException('Customer payload producer requires a valid customer id.');
        }
        $crmTenantId = (int) ($tenant['id'] ?? $tenant['crm_tenant_id'] ?? 0);
        $companyId = (int) (kt_integration_hub_crm_company_id($tenant) ?? 0);
        if ($crmTenantId <= 0 || $companyId <= 0) {
            throw new RuntimeException('Customer payload requires canonical tenant and company identities.');
        }

        $updatedAt = $this->resolveUpdatedAt($clientRow);
        $customerCode = $this->resolveCustomerCode($clientRow);
        $identity = CanonicalIntegrationIdentity::build($tenant, 'customer', $crmCustomerId, $customerCode, [
            'customer_code' => $customerCode,
            'datecreated' => (string) ($clientRow['datecreated'] ?? ''),
            'tax_code' => (string) ($clientRow['vat'] ?? ''),
        ], $updatedAt, ((int) ($clientRow['active'] ?? 0) === 1) ? 'active' : 'deleted');
        return [
            'tenant' => [
                'crm_tenant_id' => $crmTenantId,
                'crm_company_id' => $companyId,
                'canonical_crm_company_id' => $companyId,
                'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
            ],
            'customer' => [
                'crm_customer_id' => $crmCustomerId,
                'customer_code' => $customerCode,
                'name' => trim((string) ($clientRow['company'] ?? '')) ?: ('Customer #' . $crmCustomerId),
                'tax_code' => trim((string) ($clientRow['vat'] ?? '')),
                'status' => ((int) ($clientRow['active'] ?? 0) === 1) ? 'active' : 'inactive',
                'address' => [
                    'line1' => trim((string) ($clientRow['address'] ?? $clientRow['billing_street'] ?? '')),
                    'ward' => '',
                    'district' => trim((string) ($clientRow['city'] ?? $clientRow['billing_city'] ?? '')),
                    'province' => trim((string) ($clientRow['state'] ?? $clientRow['billing_state'] ?? '')),
                ],
                'primary_contact' => $this->normalizePrimaryContact($primaryContact),
                'contacts' => $this->normalizeContacts($contacts),
                'updated_at' => $updatedAt,
            ],
            'identity' => $identity,
        ];
    }

    public function buildIdempotencyKey(array $payload): string
    {
        $customer = (array) ($payload['customer'] ?? []);
        $crmCustomerId = (int) ($customer['crm_customer_id'] ?? 0);
        $updatedAt = trim((string) ($customer['updated_at'] ?? ''));
        if ($crmCustomerId <= 0 || $updatedAt === '') {
            throw new RuntimeException('Cannot build idempotency key without crm_customer_id and updated_at.');
        }

        return 'customer-v3-' . substr(hash('sha256', (string) ($payload['identity']['source_stable_key'] ?? '') . '|' . $updatedAt . '|' . kt_integration_hub_json_encode($payload)), 0, 48);
    }

    public function buildRequestId(array $payload): string
    {
        $customer = (array) ($payload['customer'] ?? []);

        return 'customer_sync_' . (int) ($customer['crm_customer_id'] ?? 0) . '_' . bin2hex(random_bytes(6));
    }

    private function resolveCustomerCode(array $clientRow): string
    {
        $candidates = [
            trim((string) ($clientRow['customer_code'] ?? '')),
            trim((string) ($clientRow['client_code'] ?? '')),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return strtoupper($candidate);
            }
        }

        return 'CRM-CLIENT-' . (int) ($clientRow['userid'] ?? 0);
    }

    private function normalizePrimaryContact(?array $contact): ?array
    {
        if (! is_array($contact) || $contact === []) {
            return null;
        }

        return [
            'name' => trim((string) (($contact['firstname'] ?? '') . ' ' . ($contact['lastname'] ?? ''))),
            'phone' => trim((string) ($contact['phonenumber'] ?? '')),
            'email' => strtolower(trim((string) ($contact['email'] ?? ''))),
        ];
    }

    private function normalizeContacts(array $contacts): array
    {
        $normalized = [];

        foreach ($contacts as $contact) {
            if (! is_array($contact)) {
                continue;
            }

            $normalized[] = [
                'crm_contact_id' => isset($contact['id']) ? (int) $contact['id'] : null,
                'name' => trim((string) (($contact['firstname'] ?? '') . ' ' . ($contact['lastname'] ?? ''))),
                'phone' => trim((string) ($contact['phonenumber'] ?? '')),
                'email' => strtolower(trim((string) ($contact['email'] ?? ''))),
                'is_primary' => ! empty($contact['is_primary']),
            ];
        }

        return $normalized;
    }

    private function resolveUpdatedAt(array $clientRow): string
    {
        $candidates = [
            (string) ($clientRow['_bridge_updated_at'] ?? ''),
            (string) ($clientRow['datecreated'] ?? ''),
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
