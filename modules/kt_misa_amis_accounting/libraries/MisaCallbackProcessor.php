<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/MisaScope.php';
require_once __DIR__ . '/MisaErrorClassifier.php';
require_once __DIR__ . '/MisaRequestStateMachine.php';

final class MisaCallbackProcessor
{
    private $db;
    private $stateMachine;
    private $tables;

    public function __construct($db, MisaRequestStateMachine $stateMachine = null)
    {
        $this->db = $db;
        $this->stateMachine = $stateMachine ?: new MisaRequestStateMachine();
        $p = db_prefix();
        $this->tables = ['requests' => $p . 'kt_misa_requests', 'items' => $p . 'kt_misa_request_items', 'inbox' => $p . 'kt_misa_callback_inbox', 'audits' => $p . 'kt_misa_audit_logs'];
    }

    public function process(array $inbox, MisaScope $scope, $connectionId)
    {
        if ((int) ($inbox['signature_valid'] ?? 0) !== 1) throw new InvalidArgumentException('Only verified callback inbox rows may be processed.');
        $items = $this->parseItems((string) ($inbox['raw_data'] ?? ''));
        $this->db->trans_begin();
        try {
            $affected = [];
            foreach ($items as $item) {
                $matches = $this->findItemMatches($scope, $connectionId, $item['org_refid']);
                if (count($matches) !== 1) {
                    $this->markInbox($inbox['id'], 'manual_review', 'CALLBACK_UNKNOWN_ORG_REFID');
                    $this->writeAudit($scope, $connectionId, 'misa_callback_manual_review', ['org_refid' => $item['org_refid'], 'match_count' => count($matches)]);
                    $this->db->trans_commit();
                    return ['status' => 'manual_review', 'processed_items' => 0, 'unknown_items' => 1];
                }
                $match = $matches[0];
                $result = $this->normalizeResult($item);
                if ($this->isOlderOrDuplicateResult($match, $result['result_hash'])) continue;
                $this->db->where('id', (int) $match['item_id'])->where('request_id', (int) $match['request_id'])->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->update($this->tables['items'], [
                    'remote_id' => $result['remote_id'], 'result_status' => $result['result_status'], 'success' => $result['success'],
                    'error_code' => $result['error_code'], 'error_message_redacted' => $result['error_message_redacted'], 'result_hash' => $result['result_hash'],
                    'processed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $affected[(int) $match['request_id']] = true;
            }
            foreach (array_keys($affected) as $requestId) $this->aggregateRequest($scope, $connectionId, (int) $requestId);
            $this->markInbox($inbox['id'], 'processed', null);
            $this->writeAudit($scope, $connectionId, 'misa_callback_processed', ['inbox_id' => (int) $inbox['id'], 'processed_items' => count($items)]);
            if (!$this->db->trans_status()) throw new RuntimeException('Callback processing transaction failed.');
            $this->db->trans_commit();
            return ['status' => 'processed', 'processed_items' => count($items), 'unknown_items' => 0];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        }
    }

    public function parseItems($rawData)
    {
        $decoded = json_decode((string) $rawData, true);
        if (!is_array($decoded)) throw new InvalidArgumentException('Callback data must be a JSON array.');
        $items = [];
        foreach ($decoded as $item) {
            if (!is_array($item) || trim((string) ($item['org_refid'] ?? '')) === '') throw new InvalidArgumentException('Callback item org_refid is required.');
            $items[] = ['org_refid' => trim((string) $item['org_refid']), 'success' => !empty($item['success']), 'error_code' => (string) ($item['error_code'] ?? $item['ErrorCode'] ?? ''), 'error_message' => (string) ($item['error_message'] ?? $item['errorMessage'] ?? $item['ErrorMessage'] ?? ''), 'remote_id' => (string) ($item['remote_id'] ?? $item['remoteId'] ?? $item['id'] ?? '')];
        }
        return $items;
    }

    public function aggregateStatuses(array $items)
    {
        if (!$items) return 'awaiting_callback';
        $pending = $success = $retryable = $permanent = 0;
        foreach ($items as $item) {
            $status = (string) ($item['result_status'] ?? 'pending');
            if ($status === 'pending') $pending++;
            elseif (!empty($item['success']) || $status === 'success') $success++;
            elseif ($status === 'retryable_failed') $retryable++;
            else $permanent++;
        }
        if ($pending) return 'awaiting_callback';
        if ($success && ($retryable || $permanent)) return 'partially_succeeded';
        if ($success) return 'succeeded';
        if ($retryable) return 'retryable_failed';
        return 'permanent_failed';
    }

    private function findItemMatches(MisaScope $scope, $connectionId, $orgRefid)
    {
        return $this->db->select('i.id AS item_id, i.request_id, i.result_status AS item_status, i.result_hash AS item_hash, r.status AS request_status')->from($this->tables['items'] . ' i')->join($this->tables['requests'] . ' r', 'r.id = i.request_id', 'inner')->where('i.tenant_id', $scope->tenantId())->where('i.company_id', $scope->companyId())->where('r.tenant_id', $scope->tenantId())->where('r.company_id', $scope->companyId())->where('r.connection_id', (int) $connectionId)->where('i.org_refid', (string) $orgRefid)->get()->result_array();
    }

    private function normalizeResult(array $item)
    {
        $classifier = new MisaErrorClassifier();
        $class = $classifier->classify($item['error_code']);
        $success = !empty($item['success']);
        $status = $success ? 'success' : ($class === 'retryable' ? 'retryable_failed' : 'failed');
        $payload = json_encode(['org_refid' => $item['org_refid'], 'success' => $success, 'error_code' => $item['error_code'], 'remote_id' => $item['remote_id']]);
        return ['success' => $success ? 1 : 0, 'result_status' => $status, 'error_code' => $success ? null : ($item['error_code'] ?: 'CALLBACK_ITEM_FAILED'), 'error_message_redacted' => $success ? null : kt_misa_redact($item['error_message']), 'remote_id' => $item['remote_id'] ?: null, 'result_hash' => hash('sha256', $payload)];
    }

    private function isOlderOrDuplicateResult(array $match, $resultHash)
    {
        if (empty($match['item_hash'])) return false;
        if ((string) $match['item_hash'] === (string) $resultHash) return true;
        return in_array((string) ($match['item_status'] ?? ''), ['success', 'failed', 'retryable_failed'], true) && in_array((string) ($match['request_status'] ?? ''), ['succeeded', 'partially_succeeded', 'permanent_failed'], true);
    }

    private function aggregateRequest(MisaScope $scope, $connectionId, $requestId)
    {
        $request = $this->db->where('id', $requestId)->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->where('connection_id', (int) $connectionId)->get($this->tables['requests'])->row_array();
        if (!$request) throw new RuntimeException('Scoped callback request not found.');
        $items = $this->db->where('request_id', $requestId)->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->get($this->tables['items'])->result_array();
        $next = $this->aggregateStatuses($items);
        if ($next === (string) $request['status']) return;
        if (!$this->stateMachine->canTransition($request['status'], $next)) return;
        $data = ['status' => $next, 'updated_at' => date('Y-m-d H:i:s')];
        if (in_array($next, ['succeeded', 'partially_succeeded', 'permanent_failed'], true)) $data['completed_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', $requestId)->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->where('connection_id', (int) $connectionId)->update($this->tables['requests'], $data);
    }

    private function markInbox($id, $status, $errorCode)
    {
        $this->db->where('id', (int) $id)->update($this->tables['inbox'], ['processing_status' => $status, 'error_code' => $errorCode, 'processed_at' => in_array($status, ['processed', 'permanent_failed'], true) ? date('Y-m-d H:i:s') : null, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    private function writeAudit(MisaScope $scope, $connectionId, $action, array $details)
    {
        $this->db->insert($this->tables['audits'], ['tenant_id' => $scope->tenantId(), 'company_id' => $scope->companyId(), 'connection_id' => (int) $connectionId, 'actor_type' => 'system', 'action' => $action, 'details_redacted_json' => kt_misa_json(kt_misa_redact($details)), 'created_at' => date('Y-m-d H:i:s')]);
    }
}
