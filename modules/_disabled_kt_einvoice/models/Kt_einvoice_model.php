<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * KT eInvoice — Model
 * CRUD cho tất cả bảng CSDL của module (Landlord DB)
 */
class Kt_einvoice_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PROVIDER SETTINGS
    // ═══════════════════════════════════════════════════════════════════════════

    public function getSettings(int $tenantId, string $environment = 'production'): ?array
    {
        $row = $this->db
            ->where('tenant_id', $tenantId)
            ->where('environment', $environment)
            ->get(db_prefix() . 'kt_einvoice_provider_settings')
            ->row_array();

        return $row ?: null;
    }

    public function getActiveSettings(int $tenantId, string $environment = 'production'): ?array
    {
        $row = $this->db
            ->where('tenant_id', $tenantId)
            ->where('environment', $environment)
            ->where('is_active', 1)
            ->get(db_prefix() . 'kt_einvoice_provider_settings')
            ->row_array();

        return $row ?: null;
    }

    public function upsertSettings(int $tenantId, string $environment, array $data): bool
    {
        $existing = $this->getSettings($tenantId, $environment);
        $now      = date('Y-m-d H:i:s');

        $data['tenant_id']   = $tenantId;
        $data['environment'] = $environment;
        $data['updated_at']  = $now;

        if ($existing) {
            $this->db->where('tenant_id', $tenantId)->where('environment', $environment);
            return $this->db->update(db_prefix() . 'kt_einvoice_provider_settings', $data);
        }

        $data['created_at'] = $now;
        return $this->db->insert(db_prefix() . 'kt_einvoice_provider_settings', $data);
    }

    /**
     * Cache token sau khi refresh
     */
    public function cacheToken(int $tenantId, string $environment, string $token, string $expiresAt): void
    {
        /** @var KtEinvoiceEncryptionService $enc */
        $enc = new KtEinvoiceEncryptionService();
        ['ciphertext' => $ct, 'iv' => $iv] = $enc->encrypt($token);

        $this->db
            ->where('tenant_id', $tenantId)
            ->where('environment', $environment)
            ->update(db_prefix() . 'kt_einvoice_provider_settings', [
                'access_token_encrypted'  => $ct,
                'access_token_iv'         => $iv,
                'token_expires_at'        => $expiresAt,
                'last_token_refreshed_at' => date('Y-m-d H:i:s'),
                'updated_at'              => date('Y-m-d H:i:s'),
            ]);
    }

    public function updateQuotaCache(int $tenantId, string $environment, ?int $quotaRemaining): void
    {
        $this->db
            ->where('tenant_id', $tenantId)
            ->where('environment', $environment)
            ->update(db_prefix() . 'kt_einvoice_provider_settings', [
                'quota_remaining'       => $quotaRemaining,
                'last_quota_synced_at'  => date('Y-m-d H:i:s'),
                'updated_at'            => date('Y-m-d H:i:s'),
            ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // EINVOICE RECORDS
    // ═══════════════════════════════════════════════════════════════════════════

    public function getRecord(int $id, int $tenantId): ?array
    {
        $row = $this->db
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->where('deleted_at IS NULL')
            ->get(db_prefix() . 'kt_einvoice_records')
            ->row_array();

        return $row ?: null;
    }

    public function getRecordByPerfexInvoice(int $tenantId, int $perfexInvoiceId, string $environment = 'production'): ?array
    {
        $row = $this->db
            ->where('tenant_id', $tenantId)
            ->where('perfex_invoice_id', $perfexInvoiceId)
            ->where('environment', $environment)
            ->where('deleted_at IS NULL')
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get(db_prefix() . 'kt_einvoice_records')
            ->row_array();

        return $row ?: null;
    }

    public function getRecordByIdempotencyKey(string $key): ?array
    {
        $row = $this->db
            ->where('idempotency_key', $key)
            ->get(db_prefix() . 'kt_einvoice_records')
            ->row_array();

        return $row ?: null;
    }

    public function insertRecord(array $data): int
    {
        $now          = date('Y-m-d H:i:s');
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        $this->db->insert(db_prefix() . 'kt_einvoice_records', $data);
        return (int) $this->db->insert_id();
    }

    public function updateRecord(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->where('id', $id)->update(db_prefix() . 'kt_einvoice_records', $data);
    }

    public function updateRecordStatus(int $id, string $status, ?string $message = null): bool
    {
        $data = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];
        if ($message !== null) {
            $data['status_message'] = $message;
        }
        return $this->db->where('id', $id)->update(db_prefix() . 'kt_einvoice_records', $data);
    }

    /**
     * Lấy records đang chờ poll status
     */
    public function getPendingRecords(array $statuses, int $limit = 100): array
    {
        return $this->db
            ->where_in('status', $statuses)
            ->where('deleted_at IS NULL')
            ->where('(next_retry_at IS NULL OR next_retry_at <= NOW())')
            ->order_by('created_at', 'ASC')
            ->limit($limit)
            ->get(db_prefix() . 'kt_einvoice_records')
            ->result_array();
    }

    public function getRecordsList(int $tenantId, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $this->db->where('tenant_id', $tenantId)->where('deleted_at IS NULL');

        if (!empty($filters['status'])) {
            $this->db->where('status', $filters['status']);
        }
        if (!empty($filters['environment'])) {
            $this->db->where('environment', $filters['environment']);
        }
        if (!empty($filters['search'])) {
            $search = $this->db->escape_like_str($filters['search']);
            $this->db->group_start()
                ->like('perfex_invoice_number', $search)
                ->or_like('invoice_number', $search)
                ->or_like('buyer_name', $search)
                ->group_end();
        }

        return $this->db
            ->order_by('id', 'DESC')
            ->limit($limit, $offset)
            ->get(db_prefix() . 'kt_einvoice_records')
            ->result_array();
    }

    public function countRecords(int $tenantId, array $filters = []): int
    {
        $this->db->where('tenant_id', $tenantId)->where('deleted_at IS NULL');
        if (!empty($filters['status'])) {
            $this->db->where('status', $filters['status']);
        }
        return (int) $this->db->count_all_results(db_prefix() . 'kt_einvoice_records');
    }

    /**
     * Thống kê dashboard
     */
    public function getDashboardStats(int $tenantId, string $environment): array
    {
        $table = db_prefix() . 'kt_einvoice_records';
        $year  = date('Y');
        $month = date('m');

        $result = ['total_issued' => 0, 'pending' => 0, 'failed' => 0];

        // Đã phát hành tháng này
        $result['total_issued'] = (int) $this->db
            ->where('tenant_id', $tenantId)
            ->where('environment', $environment)
            ->where('status', KT_EINVOICE_STATUS_ISSUED)
            ->where("YEAR(issued_at) = $year")
            ->where("MONTH(issued_at) = $month")
            ->count_all_results($table);

        // Đang xử lý
        $result['pending'] = (int) $this->db
            ->where('tenant_id', $tenantId)
            ->where('environment', $environment)
            ->where_in('status', [KT_EINVOICE_STATUS_PENDING_CREATE, KT_EINVOICE_STATUS_PENDING_ISSUE])
            ->count_all_results($table);

        // Lỗi
        $result['failed'] = (int) $this->db
            ->where('tenant_id', $tenantId)
            ->where('environment', $environment)
            ->where_in('status', [KT_EINVOICE_STATUS_FAILED_CREATE, KT_EINVOICE_STATUS_FAILED_ISSUE])
            ->count_all_results($table);

        return $result;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // API LOGS
    // ═══════════════════════════════════════════════════════════════════════════

    public function insertApiLog(array $data): void
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        // Giới hạn response_json để tránh DB quá lớn
        if (isset($data['response_json']) && strlen((string) $data['response_json']) > 65535) {
            $data['response_json'] = substr($data['response_json'], 0, 65000) . '...[truncated]';
        }
        $this->db->insert(db_prefix() . 'kt_einvoice_api_logs', $data);
    }

    public function updateApiLogRecord(int $logId, int $recordId): void
    {
        $this->db->where('id', $logId)->update(db_prefix() . 'kt_einvoice_api_logs', ['record_id' => $recordId]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // JOB QUEUE
    // ═══════════════════════════════════════════════════════════════════════════

    public function enqueueJob(int $tenantId, string $jobType, array $payload, int $priority = 5, ?int $recordId = null, ?string $scheduledAt = null): int
    {
        $now  = date('Y-m-d H:i:s');
        $data = [
            'tenant_id'    => $tenantId,
            'record_id'    => $recordId,
            'job_type'     => $jobType,
            'priority'     => $priority,
            'status'       => 'queued',
            'payload_json' => json_encode($payload),
            'attempts'     => 0,
            'max_attempts' => KT_EINVOICE_MAX_CREATE_ATTEMPTS,
            'scheduled_at' => $scheduledAt ?? $now,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];
        $this->db->insert(db_prefix() . 'kt_einvoice_jobs', $data);
        return (int) $this->db->insert_id();
    }

    public function getReadyJobs(string $jobType, int $limit = 20): array
    {
        return $this->db
            ->where('job_type', $jobType)
            ->where('status', 'queued')
            ->where('scheduled_at <=', date('Y-m-d H:i:s'))
            ->order_by('priority', 'ASC')
            ->order_by('scheduled_at', 'ASC')
            ->limit($limit)
            ->get(db_prefix() . 'kt_einvoice_jobs')
            ->result_array();
    }

    public function updateJob(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', $id)->update(db_prefix() . 'kt_einvoice_jobs', $data);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // QUOTA USAGE
    // ═══════════════════════════════════════════════════════════════════════════

    public function getQuotaUsage(int $tenantId, string $environment, int $year, int $month): ?array
    {
        return $this->db
            ->where('tenant_id', $tenantId)
            ->where('environment', $environment)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->get(db_prefix() . 'kt_einvoice_quota_usage')
            ->row_array() ?: null;
    }

    public function incrementQuotaUsage(int $tenantId, string $environment): void
    {
        $year  = (int) date('Y');
        $month = (int) date('n');
        $now   = date('Y-m-d H:i:s');

        $existing = $this->getQuotaUsage($tenantId, $environment, $year, $month);

        if ($existing) {
            $this->db
                ->where('id', $existing['id'])
                ->set('used_count', 'used_count + 1', false)
                ->set('updated_at', $now)
                ->update(db_prefix() . 'kt_einvoice_quota_usage');
        } else {
            $this->db->insert(db_prefix() . 'kt_einvoice_quota_usage', [
                'tenant_id'    => $tenantId,
                'environment'  => $environment,
                'period_year'  => $year,
                'period_month' => $month,
                'plan_quota'   => 0,
                'used_count'   => 1,
                'failed_count' => 0,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    public function incrementFailedCount(int $tenantId, string $environment): void
    {
        $year  = (int) date('Y');
        $month = (int) date('n');
        $now   = date('Y-m-d H:i:s');

        $existing = $this->getQuotaUsage($tenantId, $environment, $year, $month);
        if ($existing) {
            $this->db
                ->where('id', $existing['id'])
                ->set('failed_count', 'failed_count + 1', false)
                ->set('updated_at', $now)
                ->update(db_prefix() . 'kt_einvoice_quota_usage');
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // BATCH SESSIONS
    // ═══════════════════════════════════════════════════════════════════════════

    public function createBatchSession(int $tenantId, string $environment, array $invoiceIds, int $createdBy): array
    {
        $sessionCode = 'BATCH-' . strtoupper(substr(md5(uniqid('', true)), 0, 12));
        $now         = date('Y-m-d H:i:s');

        $this->db->insert(db_prefix() . 'kt_einvoice_batch_sessions', [
            'tenant_id'        => $tenantId,
            'environment'      => $environment,
            'session_code'     => $sessionCode,
            'status'           => 'pending',
            'total_count'      => count($invoiceIds),
            'success_count'    => 0,
            'failed_count'     => 0,
            'invoice_ids_json' => json_encode($invoiceIds),
            'created_by'       => $createdBy,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        $batchId = (int) $this->db->insert_id();

        // Tạo batch items
        foreach ($invoiceIds as $invoiceId) {
            $this->db->insert(db_prefix() . 'kt_einvoice_batch_items', [
                'batch_id'           => $batchId,
                'tenant_id'          => $tenantId,
                'record_id'          => null,
                'perfex_invoice_id'  => $invoiceId,
                'status'             => 'queued',
                'created_at'         => $now,
            ]);
        }

        return ['batch_id' => $batchId, 'session_code' => $sessionCode];
    }

    public function getBatchSession(string $sessionCode, int $tenantId): ?array
    {
        return $this->db
            ->where('session_code', $sessionCode)
            ->where('tenant_id', $tenantId)
            ->get(db_prefix() . 'kt_einvoice_batch_sessions')
            ->row_array() ?: null;
    }

    public function updateBatchSession(int $batchId, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', $batchId)->update(db_prefix() . 'kt_einvoice_batch_sessions', $data);
    }

    public function getPendingBatchItems(int $batchId, int $limit = 10): array
    {
        return $this->db
            ->where('batch_id', $batchId)
            ->where('status', 'queued')
            ->order_by('id', 'ASC')
            ->limit($limit)
            ->get(db_prefix() . 'kt_einvoice_batch_items')
            ->result_array();
    }

    public function updateBatchItem(int $itemId, array $data): void
    {
        $this->db->where('id', $itemId)->update(db_prefix() . 'kt_einvoice_batch_items', $data);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CRON LOGS
    // ═══════════════════════════════════════════════════════════════════════════

    public function insertCronLog(array $data): void
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'kt_einvoice_cron_logs', $data);
    }

    /**
     * Xóa log cũ để tránh DB phình to
     */
    public function cleanOldLogs(): void
    {
        // API logs > 90 ngày
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . KT_EINVOICE_LOG_RETENTION_DAYS . ' days'));
        $this->db->where('created_at <', $cutoff)->delete(db_prefix() . 'kt_einvoice_api_logs');

        // Cron logs > 30 ngày
        $cutoffCron = date('Y-m-d H:i:s', strtotime('-' . KT_EINVOICE_CRON_LOG_RETENTION_DAYS . ' days'));
        $this->db->where('created_at <', $cutoffCron)->delete(db_prefix() . 'kt_einvoice_cron_logs');
    }
}
