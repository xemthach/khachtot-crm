<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/MisaScope.php';

final class MisaTokenRefreshLockService
{
    private $db;
    private $table;

    public function __construct($db = null)
    {
        $this->db = $db ?: get_instance()->db;
        $this->table = db_prefix() . 'kt_misa_connections';
    }

    public function acquire(MisaScope $scope, $connectionId, $owner, $ttlSeconds = 60)
    {
        $owner = trim((string) $owner);
        $token = bin2hex(random_bytes(32));
        $ttlSeconds = max(5, min(3600, (int) $ttlSeconds));
        if ($owner === '' || (int) $connectionId <= 0) return ['acquired' => false, 'reason' => 'invalid_input'];
        $sql = "UPDATE `{$this->table}` SET refresh_lock_owner=?, refresh_lock_token_hash=?, refresh_lock_expires_at=DATE_ADD(NOW(), INTERVAL {$ttlSeconds} SECOND), updated_at=NOW() WHERE id=? AND tenant_id=? AND company_id=? AND (refresh_lock_owner IS NULL OR refresh_lock_expires_at IS NULL OR refresh_lock_expires_at < NOW())";
        $this->db->query($sql, [$owner, hash('sha256', $token), (int) $connectionId, $scope->tenantId(), $scope->companyId()]);
        if ((int) $this->db->affected_rows() !== 1) return ['acquired' => false, 'reason' => 'lock_owned'];
        return ['acquired' => true, 'owner' => $owner, 'token' => $token, 'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', time() + $ttlSeconds)];
    }

    public function release(MisaScope $scope, $connectionId, $owner, $token)
    {
        $sql = "UPDATE `{$this->table}` SET refresh_lock_owner=NULL, refresh_lock_token_hash=NULL, refresh_lock_expires_at=NULL, updated_at=NOW() WHERE id=? AND tenant_id=? AND company_id=? AND refresh_lock_owner=? AND refresh_lock_token_hash=?";
        $this->db->query($sql, [(int) $connectionId, $scope->tenantId(), $scope->companyId(), (string) $owner, hash('sha256', (string) $token)]);
        return (int) $this->db->affected_rows() === 1;
    }

    public function activeCount(MisaScope $scope)
    {
        return (int) $this->db->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->where('refresh_lock_expires_at >', date('Y-m-d H:i:s'))->count_all_results($this->table);
    }

    public function clearExpired(MisaScope $scope)
    {
        $this->db->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->where('refresh_lock_expires_at <', date('Y-m-d H:i:s'))->update($this->table, ['refresh_lock_owner' => null, 'refresh_lock_token_hash' => null, 'refresh_lock_expires_at' => null]);
        return (int) $this->db->affected_rows();
    }
}
