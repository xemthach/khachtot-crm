<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Qa_auth_model extends App_Model
{
    private const MAX_TTL_SECONDS = 600;
    private $qaDatabase;

    public function use_database($database)
    {
        if ($database) {
            $this->qaDatabase = $database;
        }
    }

    public function is_enabled()
    {
        $environment = strtolower(defined('ENVIRONMENT') ? (string) ENVIRONMENT : '');
        $appEnv = strtolower((string) getenv('APP_ENV'));

        if ($environment === 'production' || $appEnv === 'production') {
            return false;
        }

        return in_array($environment, ['development', 'testing', 'local', 'staging'], true)
            || in_array($appEnv, ['local', 'staging', 'testing'], true)
            || filter_var(getenv('KT_QA_AUTH_BOOTSTRAP_ENABLED'), FILTER_VALIDATE_BOOLEAN);
    }

    public function create_token($role, $tenantId, $ttl, $purpose, $createdBy = 'cli')
    {
        $this->assert_enabled();
        $this->ensure_tables();

        $ttl = min(max((int) $ttl, 1), self::MAX_TTL_SECONDS);
        $staff = $this->resolve_staff($role);
        $rawToken = bin2hex(random_bytes(32));
        $tokenId = 'qa_' . bin2hex(random_bytes(9));
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

        $this->database()->insert(db_prefix() . 'kt_qa_auth_tokens', [
            'token_id' => $tokenId,
            'token_hash' => hash('sha256', $rawToken),
            'app' => 'crm',
            'role' => (string) $role,
            'tenant_id' => $tenantId > 0 ? (int) $tenantId : null,
            'staff_id' => (int) $staff['staffid'],
            'user_id' => null,
            'expires_at' => $expiresAt,
            'used_at' => null,
            'revoked_at' => null,
            'created_by' => (string) $createdBy,
            'created_at' => $now,
            'redeemed_ip' => null,
            'redeemed_user_agent' => null,
            'purpose' => (string) $purpose,
            'audit_status' => 'created',
        ]);

        $this->append_audit($tokenId, 'created', [
            'role' => (string) $role,
            'tenant_id' => $tenantId > 0 ? (int) $tenantId : null,
            'staff_id' => (int) $staff['staffid'],
        ]);

        return [
            'token_id' => $tokenId,
            'raw_token' => $rawToken,
            'expires_at' => $expiresAt,
            'staff_id' => (int) $staff['staffid'],
        ];
    }

    public function redeem($tokenId, $rawToken, $ip, $userAgent)
    {
        $this->assert_enabled();
        $this->ensure_tables();

        $row = $this->database()
            ->where('token_id', (string) $tokenId)
            ->where('app', 'crm')
            ->get(db_prefix() . 'kt_qa_auth_tokens')
            ->row_array();

        if (!$row || !hash_equals((string) $row['token_hash'], hash('sha256', (string) $rawToken))) {
            throw new RuntimeException('Token QA không hợp lệ.');
        }

        if (!empty($row['used_at']) || !empty($row['revoked_at']) || strtotime((string) $row['expires_at']) < time()) {
            if (empty($row['used_at']) && empty($row['revoked_at']) && strtotime((string) $row['expires_at']) < time()) {
                $this->database()->where('id', (int) $row['id'])->update(db_prefix() . 'kt_qa_auth_tokens', [
                    'audit_status' => 'expired',
                ]);
                $this->append_audit((string) $row['token_id'], 'expired');
            }
            throw new RuntimeException('Token QA đã hết hạn hoặc đã được sử dụng.');
        }

        $staff = $this->database()
            ->where('staffid', (int) $row['staff_id'])
            ->where('active', 1)
            ->get(db_prefix() . 'staff')
            ->row_array();

        if (!$staff) {
            throw new RuntimeException('Không tìm thấy nhân sự QA hợp lệ.');
        }

        $usedAt = date('Y-m-d H:i:s');
        $this->database()->where('id', (int) $row['id'])->update(db_prefix() . 'kt_qa_auth_tokens', [
            'used_at' => $usedAt,
            'redeemed_ip' => substr((string) $ip, 0, 64),
            'redeemed_user_agent' => substr((string) $userAgent, 0, 255),
            'audit_status' => 'used',
        ]);
        $this->append_audit((string) $row['token_id'], 'used', [
            'staff_id' => (int) $row['staff_id'],
            'tenant_id' => !empty($row['tenant_id']) ? (int) $row['tenant_id'] : null,
        ]);

        return $row;
    }

    public function revoke($tokenId)
    {
        $this->assert_enabled();
        $this->ensure_tables();

        $exists = $this->database()->where('token_id', (string) $tokenId)->count_all_results(db_prefix() . 'kt_qa_auth_tokens') > 0;
        if (!$exists) {
            return false;
        }

        $this->database()->where('token_id', (string) $tokenId)->update(db_prefix() . 'kt_qa_auth_tokens', [
            'revoked_at' => date('Y-m-d H:i:s'),
            'audit_status' => 'revoked',
        ]);
        $this->append_audit((string) $tokenId, 'revoked');

        return true;
    }

    public function cleanup()
    {
        $this->assert_enabled();
        $this->ensure_tables();

        $this->database()->group_start()
            ->where('used_at IS NOT NULL', null, false)
            ->or_where('revoked_at IS NOT NULL', null, false)
            ->or_where('expires_at <', date('Y-m-d H:i:s'))
            ->group_end()
            ->delete(db_prefix() . 'kt_qa_auth_tokens');

        return (int) $this->database()->affected_rows();
    }

    private function assert_enabled()
    {
        if (!$this->is_enabled()) {
            throw new RuntimeException('QA auth bootstrap is disabled for this environment.');
        }
    }

    private function resolve_staff($role)
    {
        $query = $this->database()->where('active', 1);
        if ((string) $role === 'denied_user') {
            $query->where('admin', 0);
        } else {
            $query->where('admin', 1);
        }

        $staff = $query->order_by('staffid', 'asc')->get(db_prefix() . 'staff')->row_array();
        if (!$staff) {
            throw new RuntimeException('No eligible CRM QA staff found for role ' . (string) $role);
        }

        return $staff;
    }

    private function append_audit($tokenId, $event, array $context = [])
    {
        $this->database()->insert(db_prefix() . 'kt_qa_auth_audit', [
            'token_id' => (string) $tokenId,
            'event' => (string) $event,
            'context_json' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function ensure_tables()
    {
        $tokens = db_prefix() . 'kt_qa_auth_tokens';
        if (!$this->database()->table_exists($tokens)) {
            $this->database()->query(
                'CREATE TABLE `' . $tokens . '` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `token_id` VARCHAR(80) NOT NULL,
                    `token_hash` VARCHAR(128) NOT NULL,
                    `app` VARCHAR(32) NOT NULL,
                    `role` VARCHAR(64) NOT NULL,
                    `tenant_id` BIGINT UNSIGNED NULL,
                    `staff_id` BIGINT UNSIGNED NULL,
                    `user_id` BIGINT UNSIGNED NULL,
                    `expires_at` DATETIME NOT NULL,
                    `used_at` DATETIME NULL,
                    `revoked_at` DATETIME NULL,
                    `created_by` VARCHAR(120) NULL,
                    `created_at` DATETIME NOT NULL,
                    `redeemed_ip` VARCHAR(64) NULL,
                    `redeemed_user_agent` VARCHAR(255) NULL,
                    `purpose` VARCHAR(255) NULL,
                    `audit_status` VARCHAR(40) NOT NULL DEFAULT "created",
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_qa_auth_token_id` (`token_id`),
                    KEY `idx_qa_auth_expires_at` (`expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }

        $audit = db_prefix() . 'kt_qa_auth_audit';
        if (!$this->database()->table_exists($audit)) {
            $this->database()->query(
                'CREATE TABLE `' . $audit . '` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `token_id` VARCHAR(80) NOT NULL,
                    `event` VARCHAR(40) NOT NULL,
                    `context_json` TEXT NULL,
                    `created_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_qa_auth_audit_token` (`token_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
    }

    private function database()
    {
        if ($this->qaDatabase) {
            return $this->qaDatabase;
        }

        return get_instance()->db;
    }
}
