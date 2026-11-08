<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once dirname(__DIR__) . '/helpers/kt_misa_amis_accounting_helper.php';
require_once __DIR__ . '/MisaScope.php';
require_once __DIR__ . '/MisaWorkerLeaseService.php';
require_once __DIR__ . '/MisaTokenRefreshLockService.php';
require_once __DIR__ . '/MisaMockTransport.php';
require_once __DIR__ . '/MisaTokenService.php';
require_once __DIR__ . '/MisaSecretVault.php';
require_once __DIR__ . '/MisaRequestStateMachine.php';

final class MisaWorkerEngine
{
    private $db;
    private $requests;
    private $connections;
    private $attempts;
    private $audits;
    private $lease;
    private $tokenLock;
    private $stateMachine;
    private $transport;
    private $vault;

    public function __construct($db = null, MisaTransportInterface $transport = null)
    {
        $this->db = $db ?: get_instance()->db;
        $p = db_prefix();
        $this->requests = $p . 'kt_misa_requests';
        $this->connections = $p . 'kt_misa_connections';
        $this->attempts = $p . 'kt_misa_attempts';
        $this->audits = $p . 'kt_misa_audit_logs';
        $this->lease = new MisaWorkerLeaseService($this->db);
        $this->tokenLock = new MisaTokenRefreshLockService($this->db);
        $this->stateMachine = new MisaRequestStateMachine();
        $this->transport = $transport ?: new MisaMockTransport();
        $this->vault = new MisaSecretVault();
    }

    public function runOnce(MisaScope $scope, $workerId, $limit = 10, $kind = 'dispatch')
    {
        $this->lease->recoverExpired($scope);
        $claims = $this->lease->claimNext($scope, $workerId, $kind, $limit, 60);
        $results = [];
        foreach ($claims as $claim) {
            $results[] = $kind === 'reconcile' ? $this->reconcileClaim($scope, $claim) : $this->dispatchClaim($scope, $claim);
        }
        return ['worker_id' => (string) $workerId, 'kind' => $kind, 'claimed' => count($claims), 'processed' => count($results), 'results' => $results, 'active_leases' => $this->lease->activeCount($scope), 'active_token_locks' => $this->tokenLock->activeCount($scope)];
    }

    public function runRequest(MisaScope $scope, $requestId, $workerId, $kind = 'dispatch')
    {
        $this->lease->recoverExpired($scope);
        $claim = $this->lease->claim($scope, $requestId, $workerId, $kind, 60);
        if (empty($claim['claimed'])) return ['worker_id' => (string) $workerId, 'kind' => $kind, 'claimed' => 0, 'processed' => 0, 'results' => [], 'reason' => $claim['reason'] ?? 'not_claimed', 'active_leases' => $this->lease->activeCount($scope), 'active_token_locks' => $this->tokenLock->activeCount($scope)];
        $result = $kind === 'reconcile' ? $this->reconcileClaim($scope, $claim) : $this->dispatchClaim($scope, $claim);
        return ['worker_id' => (string) $workerId, 'kind' => $kind, 'claimed' => 1, 'processed' => 1, 'results' => [$result], 'active_leases' => $this->lease->activeCount($scope), 'active_token_locks' => $this->tokenLock->activeCount($scope)];
    }

    public function claimForTest(MisaScope $scope, $requestId, $workerId, $kind = 'dispatch', $ttl = 60)
    {
        return $this->lease->claim($scope, $requestId, $workerId, $kind, $ttl);
    }

    public function heartbeatForTest(MisaScope $scope, $requestId, $workerId, $token, $ttl = 60)
    {
        return $this->lease->heartbeat($scope, $requestId, $workerId, $token, $ttl);
    }

    public function releaseForTest(MisaScope $scope, $requestId, $workerId, $token)
    {
        return $this->lease->release($scope, $requestId, $workerId, $token);
    }

    public function tokenLockForTest(MisaScope $scope, $connectionId, $owner, $ttl = 60)
    {
        return $this->tokenLock->acquire($scope, $connectionId, $owner, $ttl);
    }

    public function tokenUnlockForTest(MisaScope $scope, $connectionId, $owner, $token)
    {
        return $this->tokenLock->release($scope, $connectionId, $owner, $token);
    }

    public function refreshConnection(MisaScope $scope, $connectionId, $workerId)
    {
        $connection = $this->scopedConnection($scope, $connectionId);
        if (!$connection) return ['success' => false, 'error_code' => 'CONNECTION_NOT_FOUND'];
        return $this->ensureToken($scope, $connection, $workerId);
    }

    public function health(MisaScope $scope)
    {
        $row = $this->db->select('COUNT(*) AS total, SUM(status IN (\'queued\',\'retryable_failed\')) AS queued, SUM(status=\'awaiting_callback\') AS awaiting_callbacks, SUM(status=\'reconciliation_required\') AS reconciliation_required, SUM(status=\'permanent_failed\') AS permanent_failed, SUM(status=\'processing\') AS processing')->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->get($this->requests)->row_array();
        return ['tenant_id' => $scope->tenantId(), 'company_id' => $scope->companyId(), 'requests' => array_map('intval', $row ?: []), 'active_leases' => $this->lease->activeCount($scope), 'active_token_locks' => $this->tokenLock->activeCount($scope), 'last_run_at' => date('Y-m-d H:i:s'), 'external_calls' => false, 'secrets' => 'redacted'];
    }

    private function dispatchClaim(MisaScope $scope, array $claim)
    {
        $id = (int) $claim['request_id'];
        $worker = (string) $claim['worker_id'];
        $token = (string) $claim['lease_token'];
        try {
            $request = $this->scopedRequest($scope, $id);
            if (!$request) return ['request_id' => $id, 'status' => 'missing'];
            if ($request['status'] === 'retryable_failed') {
                if (!$this->transitionOwned($scope, $claim, 'retryable_failed', 'queued')) throw new RuntimeException('Retry claim transition failed.');
                $request['status'] = 'queued';
            }
            if (!$this->transitionOwned($scope, $claim, 'queued', 'dispatching')) throw new RuntimeException('Dispatch claim transition failed.');
            $request = $this->scopedRequest($scope, $id);
            $connection = $this->scopedConnection($scope, $request['connection_id']);
            if (!$connection || $connection['status'] === 'disabled') return $this->permanent($scope, $claim, 'connection', 'CONNECTION_DISABLED');
            $tokenResult = $this->ensureToken($scope, $connection, $worker);
            if (empty($tokenResult['success'])) return $this->retry($scope, $claim, 'authentication', $tokenResult['error_code'] ?? 'TOKEN_UNAVAILABLE', 5);
            $attemptNo = (int) $request['attempt_count'] + 1;
            $attemptId = $this->appendAttempt($scope, $request, $attemptNo, 'started');
            $this->setAttemptCount($scope, $claim, $attemptNo);
            $outcome = $this->mockOutcome($request);
            if ($outcome === 'accepted') {
                $response = $this->transport->request($request['operation'], ['request_id' => $id, 'org_refid' => $request['org_refid'], 'payload_hash' => $request['payload_hash']], ['tenant_id' => $scope->tenantId(), 'company_id' => $scope->companyId(), 'token' => 'redacted']);
                $this->finishAttempt($attemptId, 'accepted', 200, ['accepted' => !empty($response['Success'])]);
                if (!$this->transitionOwned($scope, $claim, 'dispatching', 'accepted_by_misa', ['accepted_at' => date('Y-m-d H:i:s')])) throw new RuntimeException('Accepted transition failed.');
                if (!$this->transitionOwned($scope, $claim, 'accepted_by_misa', 'awaiting_callback')) throw new RuntimeException('Awaiting callback transition failed.');
                $this->audit($scope, $connection['id'], 'worker.dispatch_accepted', ['request_id' => $id, 'attempt' => $attemptNo]);
                return ['request_id' => $id, 'status' => 'awaiting_callback', 'attempt' => $attemptNo];
            }
            $this->finishAttempt($attemptId, $outcome, null, ['outcome' => $outcome]);
            if (in_array($outcome, ['timeout_after_possible_send'], true)) return $this->reconcileRequired($scope, $claim, $attemptNo);
            if (in_array($outcome, ['validation_error', 'permanent_server_error', 'malformed_response'], true)) return $this->permanent($scope, $claim, 'permanent', strtoupper($outcome));
            return $this->retry($scope, $claim, 'retryable', strtoupper($outcome), min(300, 2 ** min(7, $attemptNo)));
        } catch (Throwable $e) {
            $message = preg_replace('/D:\\\\laragon\\\\www\\\\khachtot(?:-misa-runtime)?|khachtot_misa_[A-Za-z0-9_]+|[A-Za-z]:\\\\[^ ]+/i', '[REDACTED_PATH]', (string) $e->getMessage());
            $message = preg_replace('/(access[_ -]?code|access[_ -]?token|password|secret|authorization)[^,; ]*/i', '$1=[REDACTED]', $message);
            log_message('error', 'MISA worker exception class=' . get_class($e) . ' file=' . basename((string) $e->getFile()) . ' line=' . (int) $e->getLine() . ' message=' . substr($message, 0, 180));
            return $this->retry($scope, $claim, 'worker_exception', 'WORKER_EXCEPTION', 5);
        } finally {
            // All terminal helpers release the lease; this is a defensive cleanup for crashes handled locally.
            $this->lease->release($scope, $id, $worker, $token);
        }
    }

    private function reconcileClaim(MisaScope $scope, array $claim)
    {
        $id = (int) $claim['request_id'];
        try {
            $request = $this->scopedRequest($scope, $id);
            if (!$request) return ['request_id' => $id, 'status' => 'missing'];
            $items = $this->db->where('request_id', $id)->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->get(db_prefix() . 'kt_misa_request_items')->result_array();
            $outcome = $this->reconciliationOutcome($request);
            if ($outcome === 'not_found') return $this->permanentReconciliation($scope, $claim, 'NOT_FOUND');
            if ($outcome === 'still_processing' || $outcome === 'temporary_error' || $outcome === 'malformed_response') return ['request_id' => $id, 'status' => 'reconciliation_deferred'];
            foreach ($items as $index => $item) {
                $success = $outcome === 'all_success' || ($outcome === 'partial' && $index < max(1, (int) ceil(count($items) / 2)));
                $this->db->where('id', (int) $item['id'])->where('request_id', $id)->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->update(db_prefix() . 'kt_misa_request_items', ['result_status' => $success ? 'success' : 'failed', 'success' => $success ? 1 : 0, 'remote_id' => $success ? 'mock-reconciled-' . $item['id'] : null, 'error_code' => $success ? null : 'MOCK_RECONCILED_FAILURE', 'processed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
            }
            $next = $outcome === 'all_success' ? 'succeeded' : ($outcome === 'partial' ? 'partially_succeeded' : 'permanent_failed');
            $this->transitionOwned($scope, $claim, $request['status'], $next, $next === 'permanent_failed' ? ['completed_at' => date('Y-m-d H:i:s')] : []);
            $this->audit($scope, $request['connection_id'], 'worker.reconciled', ['request_id' => $id, 'outcome' => $outcome]);
            return ['request_id' => $id, 'status' => $next];
        } finally {
            $this->lease->release($scope, $id, $claim['worker_id'], $claim['lease_token']);
        }
    }

    private function ensureToken(MisaScope $scope, array $connection, $worker)
    {
        $expires = strtotime((string) ($connection['token_expires_at'] ?? ''));
        if (!empty($connection['access_token_encrypted']) && $expires > time() + 60) return ['success' => true, 'cached' => true];
        $lock = $this->tokenLock->acquire($scope, $connection['id'], $worker, 60);
        if (empty($lock['acquired'])) {
            $fresh = $this->scopedConnection($scope, $connection['id']);
            if ($fresh && strtotime((string) ($fresh['token_expires_at'] ?? '')) > time() + 60) return ['success' => true, 'cached' => true, 'waited' => true];
            return ['success' => false, 'error_code' => 'TOKEN_REFRESH_LOCK_BUSY'];
        }
        try {
            $fresh = $this->scopedConnection($scope, $connection['id']);
            if ($fresh && !empty($fresh['access_token_encrypted']) && strtotime((string) ($fresh['token_expires_at'] ?? '')) > time() + 60) return ['success' => true, 'cached' => true];
            $accessCode = $this->vault->decrypt((string) ($fresh['access_code_encrypted'] ?? ''));
            $result = (new MisaTokenService($this->transport, $this->vault))->connect(['app_id' => $fresh['app_id'], 'access_code' => $accessCode, 'org_company_code' => $fresh['org_company_code']]);
            if (empty($result['success'])) return $result;
            $this->db->where('id', (int) $fresh['id'])->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->update($this->connections, ['access_token_encrypted' => $result['token_encrypted'], 'token_expires_at' => $result['token_expires_at'], 'token_expires_ticks' => $result['token_expires_ticks'], 'token_version' => ((int) $fresh['token_version']) + 1, 'last_connected_at' => date('Y-m-d H:i:s'), 'last_healthcheck_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
            return ['success' => true, 'refreshed' => true, 'version' => ((int) $fresh['token_version']) + 1];
        } finally {
            $this->tokenLock->release($scope, $connection['id'], $lock['owner'], $lock['token']);
        }
    }

    private function transitionOwned(MisaScope $scope, array $claim, $from, $to, array $extra = [])
    {
        if (!$this->stateMachine->canTransition($from, $to)) return false;
        $data = array_merge(['status' => $to, 'updated_at' => date('Y-m-d H:i:s')], $extra);
        $set = [];
        $params = [];
        foreach ($data as $key => $value) { $set[] = "{$key}=?"; $params[] = $value; }
        $sql = "UPDATE `{$this->requests}` SET " . implode(',', $set) . " WHERE id=? AND tenant_id=? AND company_id=? AND status=? AND worker_id=? AND lease_token_hash=? AND lease_expires_at > NOW()";
        $params = array_merge($params, [(int) $claim['request_id'], $scope->tenantId(), $scope->companyId(), $from, $claim['worker_id'], hash('sha256', $claim['lease_token'])]);
        $this->db->query($sql, $params);
        return (int) $this->db->affected_rows() === 1;
    }

    private function appendAttempt(MisaScope $scope, array $request, $number, $outcome)
    {
        $this->db->insert($this->attempts, ['tenant_id' => $scope->tenantId(), 'company_id' => $scope->companyId(), 'connection_id' => (int) $request['connection_id'], 'request_id' => (int) $request['id'], 'attempt_number' => (int) $number, 'transport' => 'mock', 'request_payload_redacted' => kt_misa_json(['request_id' => (int) $request['id'], 'payload_hash' => $request['payload_hash']]), 'outcome' => $outcome, 'started_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s')]);
        return (int) $this->db->insert_id();
    }

    private function finishAttempt($attemptId, $outcome, $status, array $response)
    {
        if ($attemptId <= 0) return;
        $this->db->where('id', $attemptId)->update($this->attempts, ['outcome' => $outcome, 'response_status' => $status, 'response_body_redacted' => kt_misa_json(kt_misa_redact($response)), 'finished_at' => date('Y-m-d H:i:s')]);
    }

    private function setAttemptCount(MisaScope $scope, array $claim, $number)
    {
        $this->db->query("UPDATE `{$this->requests}` SET attempt_count=? WHERE id=? AND tenant_id=? AND company_id=? AND worker_id=? AND lease_token_hash=? AND lease_expires_at > NOW()", [$number, (int) $claim['request_id'], $scope->tenantId(), $scope->companyId(), $claim['worker_id'], hash('sha256', $claim['lease_token'])]);
    }

    private function retry(MisaScope $scope, array $claim, $class, $code, $delay)
    {
        $ok = $this->lease->scheduleRetry($scope, $claim['request_id'], $claim['worker_id'], $claim['lease_token'], $class, $code, $delay);
        return ['request_id' => (int) $claim['request_id'], 'status' => $ok ? 'retryable_failed' : 'retry_update_failed', 'retry_after_seconds' => (int) $delay];
    }

    private function permanent(MisaScope $scope, array $claim, $class, $code)
    {
        $ok = $this->lease->markPermanent($scope, $claim['request_id'], $claim['worker_id'], $claim['lease_token'], $class, $code);
        return ['request_id' => (int) $claim['request_id'], 'status' => $ok ? 'permanent_failed' : 'permanent_update_failed'];
    }

    private function reconcileRequired(MisaScope $scope, array $claim, $attemptNo)
    {
        $ok = $this->lease->markReconciliationRequired($scope, $claim['request_id'], $claim['worker_id'], $claim['lease_token'], 'TIMEOUT_AFTER_POSSIBLE_SEND');
        return ['request_id' => (int) $claim['request_id'], 'status' => $ok ? 'reconciliation_required' : 'reconciliation_update_failed', 'attempt' => $attemptNo];
    }

    private function permanentReconciliation(MisaScope $scope, array $claim, $code)
    {
        $where = "WHERE id=? AND tenant_id=? AND company_id=? AND worker_id=? AND lease_token_hash=? AND lease_expires_at > NOW()";
        $this->db->query("UPDATE `{$this->requests}` SET status='permanent_failed', completed_at=NOW(), error_class='reconciliation', error_code=?, worker_id=NULL, lease_token_hash=NULL, lease_expires_at=NULL, heartbeat_at=NULL, updated_at=NOW() {$where}", [$code, (int) $claim['request_id'], $scope->tenantId(), $scope->companyId(), $claim['worker_id'], hash('sha256', $claim['lease_token'])]);
        return ['request_id' => (int) $claim['request_id'], 'status' => (int) $this->db->affected_rows() === 1 ? 'permanent_failed' : 'reconciliation_update_failed'];
    }

    private function scopedRequest(MisaScope $scope, $id)
    {
        return $this->db->where('id', (int) $id)->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->get($this->requests)->row_array();
    }

    private function scopedConnection(MisaScope $scope, $id)
    {
        return $this->db->where('id', (int) $id)->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->get($this->connections)->row_array();
    }

    private function mockOutcome(array $request)
    {
        $key = strtolower((string) ($request['local_stable_key'] ?? ''));
        foreach (['validation_error', 'authentication_error', 'rate_limited', 'temporary_server_error', 'permanent_server_error', 'timeout_before_send', 'timeout_after_possible_send', 'malformed_response'] as $outcome) if (strpos($key, 'phase11-' . $outcome) !== false) return $outcome;
        return 'accepted';
    }

    private function reconciliationOutcome(array $request)
    {
        $key = strtolower((string) ($request['local_stable_key'] ?? ''));
        foreach (['all_success', 'partial', 'all_failure', 'not_found', 'still_processing', 'malformed_response', 'temporary_error'] as $outcome) if (strpos($key, 'phase11-reconcile-' . $outcome) !== false) return $outcome;
        return 'all_success';
    }

    private function audit(MisaScope $scope, $connectionId, $action, array $details)
    {
        $this->db->insert($this->audits, ['tenant_id' => $scope->tenantId(), 'company_id' => $scope->companyId(), 'connection_id' => (int) $connectionId, 'actor_type' => 'system', 'action' => $action, 'details_redacted_json' => kt_misa_json(kt_misa_redact($details)), 'created_at' => date('Y-m-d H:i:s')]);
    }
}
