<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/MisaScope.php';
require_once __DIR__ . '/MisaCallbackVerifier.php';

final class MisaCallbackInboxService
{
    private $verifier;

    public function __construct(MisaCallbackVerifier $verifier)
    {
        $this->verifier = $verifier;
    }

    public function validateEnvelope(array $envelope, $rawData, MisaScope $scope, array $connection)
    {
        $rawData = (string) $rawData;
        $appId = trim((string) ($envelope['app_id'] ?? ''));
        $orgCode = trim((string) ($envelope['org_company_code'] ?? ''));
        if ($appId === '' || $orgCode === '' || $rawData === '') throw new InvalidArgumentException('MISA callback app_id, org_company_code and data are required.');
        if ($appId !== (string) ($connection['app_id'] ?? '') || $orgCode !== (string) ($connection['org_company_code'] ?? '')) throw new RuntimeException('MISA callback connection scope mismatch.');
        $valid = $this->verifier->verify($rawData, $envelope['signature'] ?? '', $appId);
        return [
            'tenant_id' => $scope->tenantId(), 'company_id' => $scope->companyId(), 'connection_id' => (int) ($connection['id'] ?? 0),
            'app_id' => $appId, 'org_company_code' => $orgCode, 'data_type' => (string) ($envelope['data_type'] ?? ''),
            'signature' => (string) ($envelope['signature'] ?? ''), 'signature_valid' => $valid ? 1 : 0,
            'payload_hash' => hash('sha256', $rawData), 'raw_data' => $rawData,
            'processing_status' => $valid ? 'received' : 'rejected', 'received_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s'),
        ];
    }
}

