<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/MisaScope.php';
require_once __DIR__ . '/MisaRequestStateMachine.php';
require_once __DIR__ . '/MisaMappingService.php';

final class MisaRequestLedgerService
{
    private $states;
    private $mapping;

    public function __construct(MisaRequestStateMachine $states = null, MisaMappingService $mapping = null)
    {
        $this->states = $states ?: new MisaRequestStateMachine();
        $this->mapping = $mapping ?: new MisaMappingService();
    }

    public function draft(MisaScope $scope, $connectionId, $objectType, array $source, $payloadHash, $operation = 'upsert')
    {
        $key = $this->mapping->idempotencyKey($scope, $connectionId, $objectType, $source, $operation, $payloadHash);
        return [
            'tenant_id' => $scope->tenantId(), 'company_id' => $scope->companyId(), 'connection_id' => (int) $connectionId,
            'operation' => (string) $operation, 'object_type' => (string) $objectType, 'local_stable_key' => $this->mapping->stableKey($objectType, $source),
            'idempotency_key' => $key, 'correlation_id' => 'misa-' . substr($key, 0, 20), 'status' => 'draft', 'payload_hash' => $payloadHash,
        ];
    }

    public function transition(array $request, $to)
    {
        $request['status'] = $this->states->transition($request['status'] ?? 'draft', $to);
        return $request;
    }
}

