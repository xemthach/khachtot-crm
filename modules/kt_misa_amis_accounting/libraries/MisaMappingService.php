<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/MisaScope.php';

final class MisaMappingService
{
    public function stableKey($objectType, array $source)
    {
        $key = trim((string) ($source['stable_key'] ?? $source['code'] ?? $source['sku'] ?? ''));
        if ($key === '') throw new InvalidArgumentException('Stable key is required for ' . $objectType . '.');
        return strtolower((string) $objectType) . ':' . $key;
    }

    public function idempotencyKey(MisaScope $scope, $connectionId, $objectType, array $source, $operation = 'upsert', $fingerprint = '')
    {
        return hash('sha256', implode('|', [$scope->tenantId(), $scope->companyId(), (int) $connectionId, $objectType, $this->stableKey($objectType, $source), $operation, $fingerprint]));
    }

    public function assertRemoteConflict(array $existing, $remoteId)
    {
        if (!empty($existing['remote_id']) && (string) $existing['remote_id'] !== (string) $remoteId) throw new LogicException('MISA mapping conflict: local object already maps to another remote object.');
        return true;
    }
}

