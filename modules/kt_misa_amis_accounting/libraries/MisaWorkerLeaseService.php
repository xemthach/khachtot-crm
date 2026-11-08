<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/MisaScope.php';

final class MisaWorkerLeaseService
{
    private $db;
    private $table;

    public function __construct($db = null)
    {
        $this->db = $db ?: get_instance()->db;
        $this->table = db_prefix() . 'kt_misa_requests';
    }

    public function claim(MisaScope $scope, $requestId, $workerId, $kind = 'dispatch', $ttlSeconds = 60)
    {
        $requestId = (int) $requestId;
        $workerId = trim((string) $workerId);
        $kind = in_array($kind, ['dispatch', 'reconcile'], true) ? $kind : 'dispatch';
        $ttlSeconds = max(5, min(3600, (int) $ttlSeconds));
        if ($requestId <= 0 || $workerId === '') return ['claimed' => false, 'reason' => 'invalid_input'];
        $states = $kind === 'reconcile' ? ['awaiting_callback', 'reconciliation_required'] : ['queued', 'retryable_failed'];
        $stateSql = implode(',', array_fill(0, count($states), '?'));
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $sql = "UPDATE `{$this->table}` SET worker_id=?, lease_token_hash=?, lease_kind=?, claimed_at=NOW(), heartbeat_at=NOW(), lease_expires_at=DATE_ADD(NOW(), INTERVAL {$ttlSeconds} SECOND), updated_at=NOW()
            WHERE id=? AND tenant_id=? AND company_id=? AND status IN ({$stateSql})
            AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
            AND (lease_expires_at IS NULL OR lease_expires_at < NOW())";
        $params = [$workerId, $hash, $kind, $requestId, $scope->tenantId(), $scope->companyId()];
        foreach ($states as $state) $params[] = $state;
        $this->db->query($sql, $params);
        if ((int) $this->db->affected_rows() !== 1) return ['claimed' => false, 'reason' => 'not_eligible_or_owned'];
        return ['claimed' => true, 'request_id' => $requestId, 'worker_id' => $workerId, 'lease_token' => $token, 'lease_token_hash' => $hash, 'lease_kind' => $kind, 'expires_at' => date('Y-m-d H:i:s', time() + $ttlSeconds)];
    }

    public function claimNext(MisaScope $scope, $workerId, $kind = 'dispatch', $limit = 1, $ttlSeconds = 60)
    {
        $states = $kind === 'reconcile' ? ['awaiting_callback', 'reconciliation_required'] : ['queued', 'retryable_failed'];
        $rows = $this->db->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->where_in('status', $states)
            ->where('(next_attempt_at IS NULL OR next_attempt_at <= NOW())', null, false)
            ->where('(lease_expires_at IS NULL OR lease_expires_at < NOW())', null, false)
            ->order_by('priority', 'ASC')->order_by('id', 'ASC')->limit(max(1, min(100, (int) $limit)))->get($this->table)->result_array();
        $claimed = [];
        foreach ($rows as $row) {
            $result = $this->claim($scope, $row['id'], $workerId, $kind, $ttlSeconds);
            if (!empty($result['claimed'])) $claimed[] = $result;
        }
        return $claimed;
    }

    public function heartbeat(MisaScope $scope, $requestId, $workerId, $token, $ttlSeconds = 60)
    {
        // Add one second so an immediate renewal is still observable by MySQL's affected_rows.
        return $this->mutateLease($scope, $requestId, $workerId, $token, ['heartbeat_at' => date('Y-m-d H:i:s'), 'lease_expires_at' => date('Y-m-d H:i:s', time() + max(6, (int) $ttlSeconds + 1))], true);
    }

    public function release(MisaScope $scope, $requestId, $workerId, $token)
    {
        return $this->mutateLease($scope, $requestId, $workerId, $token, ['worker_id' => null, 'lease_token_hash' => null, 'lease_kind' => 'dispatch', 'claimed_at' => null, 'lease_expires_at' => null, 'heartbeat_at' => null], false);
    }

    public function scheduleRetry(MisaScope $scope, $requestId, $workerId, $token, $errorClass, $errorCode, $delaySeconds, $maxAttempts = 5)
    {
        $where = $this->leaseWhere($scope, $requestId, $workerId, $token);
        $sql = "UPDATE `{$this->table}` SET status='retryable_failed', attempt_count=attempt_count+1, next_attempt_at=?, last_error_class=?, last_error_code=?, error_class=?, error_code=?, error_message_redacted=?, worker_id=NULL, lease_token_hash=NULL, lease_expires_at=NULL, heartbeat_at=NULL, updated_at=NOW() {$where['sql']} AND status IN ('dispatching','queued','retryable_failed')";
        $this->db->query($sql, array_merge([date('Y-m-d H:i:s', time() + max(1, (int) $delaySeconds)), $errorClass, $errorCode, $errorClass, $errorCode, 'Retry scheduled by mock worker.'], $where['params']));
        return (int) $this->db->affected_rows() === 1;
    }

    public function markPermanent(MisaScope $scope, $requestId, $workerId, $token, $errorClass, $errorCode)
    {
        $where = $this->leaseWhere($scope, $requestId, $workerId, $token);
        $sql = "UPDATE `{$this->table}` SET status='permanent_failed', attempt_count=attempt_count+1, completed_at=NOW(), last_error_class=?, last_error_code=?, error_class=?, error_code=?, error_message_redacted=?, worker_id=NULL, lease_token_hash=NULL, lease_expires_at=NULL, heartbeat_at=NULL, updated_at=NOW() {$where['sql']} AND status IN ('dispatching','queued','retryable_failed')";
        $this->db->query($sql, array_merge([$errorClass, $errorCode, $errorClass, $errorCode, 'Permanent mock worker failure.'], $where['params']));
        return (int) $this->db->affected_rows() === 1;
    }

    public function markReconciliationRequired(MisaScope $scope, $requestId, $workerId, $token, $code = 'AMBIGUOUS_DISPATCH')
    {
        $where = $this->leaseWhere($scope, $requestId, $workerId, $token);
        $sql = "UPDATE `{$this->table}` SET status='reconciliation_required', last_error_class='reconciliation_required', last_error_code=?, error_class='reconciliation_required', error_code=?, error_message_redacted='Dispatch outcome is ambiguous; reconciliation required.', worker_id=NULL, lease_token_hash=NULL, lease_expires_at=NULL, heartbeat_at=NULL, updated_at=NOW() {$where['sql']} AND status IN ('dispatching','awaiting_callback')";
        $this->db->query($sql, array_merge([$code, $code], $where['params']));
        return (int) $this->db->affected_rows() === 1;
    }

    public function recoverExpired(MisaScope $scope)
    {
        $sql = "UPDATE `{$this->table}` SET status=CASE WHEN status IN ('dispatching','accepted_by_misa') THEN 'reconciliation_required' ELSE status END, last_error_class=CASE WHEN status IN ('dispatching','accepted_by_misa') THEN 'reconciliation_required' ELSE last_error_class END, last_error_code=CASE WHEN status IN ('dispatching','accepted_by_misa') THEN 'LEASE_EXPIRED' ELSE last_error_code END, worker_id=NULL, lease_token_hash=NULL, lease_expires_at=NULL, heartbeat_at=NULL, updated_at=NOW() WHERE tenant_id=? AND company_id=? AND status IN ('dispatching','accepted_by_misa') AND (lease_expires_at IS NULL OR lease_expires_at < NOW())";
        $this->db->query($sql, [$scope->tenantId(), $scope->companyId()]);
        return (int) $this->db->affected_rows();
    }

    public function activeCount(MisaScope $scope)
    {
        return (int) $this->db->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->where('lease_expires_at >', date('Y-m-d H:i:s'))->count_all_results($this->table);
    }

    private function leaseWhere(MisaScope $scope, $requestId, $workerId, $token)
    {
        return ['sql' => 'WHERE id=? AND tenant_id=? AND company_id=? AND worker_id=? AND lease_token_hash=? AND lease_expires_at > NOW()', 'params' => [(int) $requestId, $scope->tenantId(), $scope->companyId(), (string) $workerId, hash('sha256', (string) $token)]];
    }

    private function mutateLease(MisaScope $scope, $requestId, $workerId, $token, array $data, $heartbeat)
    {
        $where = $this->leaseWhere($scope, $requestId, $workerId, $token);
        $sets = [];
        $params = [];
        foreach ($data as $column => $value) {
            $sets[] = "{$column}=?";
            $params[] = $value;
        }
        $sql = "UPDATE `{$this->table}` SET " . implode(',', $sets) . " {$where['sql']}";
        $this->db->query($sql, array_merge($params, $where['params']));
        return (int) $this->db->affected_rows() === 1;
    }
}
