<?php

defined('BASEPATH') or exit('No direct script access allowed');

class UsageRetentionService
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    public function cleanupOldSnapshots($days = null)
    {
        $days = $days !== null ? (int) $days : (int) kt_saas_get_option('kt_saas_usage_retention_days', '90');
        $days = max($days, 7);

        $cutoffDate = date('Y-m-d', strtotime('-' . $days . ' days'));

        $this->CI->db->where('updated_at <', $cutoffDate . ' 00:00:00');
        $this->CI->db->delete(db_prefix() . 'kt_saas_usage');
        $deletedSnapshots = (int) $this->CI->db->affected_rows();

        $this->CI->db->insert(db_prefix() . 'kt_saas_activity_logs', [
            'tenant_id'    => null,
            'actor_type'   => 'system',
            'actor_id'     => null,
            'event_key'    => 'usage.retention_cleanup',
            'severity'     => 'info',
            'ip_address'   => $this->CI->input->ip_address(),
            'user_agent'   => substr((string) $this->CI->input->user_agent(), 0, 255),
            'context_json' => json_encode([
                'retention_days'    => $days,
                'cutoff_date'       => $cutoffDate,
                'deleted_snapshots' => $deletedSnapshots,
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        return [
            'success'           => true,
            'retention_days'    => $days,
            'cutoff_date'       => $cutoffDate,
            'deleted_snapshots' => $deletedSnapshots,
        ];
    }
}
