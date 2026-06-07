<?php

defined('BASEPATH') or exit('No direct script access allowed');

class TenantResolver
{
    public function resolveByHost($host)
    {
        $host = strtolower(trim((string) $host));
        if ($host === '') {
            return ['resolved' => false, 'reason' => 'empty_host'];
        }

        $CI = &get_instance();

        if (!$CI->db->table_exists(db_prefix() . 'kt_saas_domains')) {
            return ['resolved' => false, 'reason' => 'domain_table_missing'];
        }

        $landlordHost = strtolower(trim((string) kt_saas_get_option('kt_saas_landlord_host', parse_url(APP_BASE_URL, PHP_URL_HOST))));
        if ($landlordHost !== '' && $host === $landlordHost) {
            return ['resolved' => false, 'reason' => 'landlord_host'];
        }

        $row = $CI->db
            ->select('d.id as domain_id, d.domain, d.domain_type, d.is_primary, d.ssl_status, d.dns_status, t.*')
            ->from(db_prefix() . 'kt_saas_domains d')
            ->join(db_prefix() . 'kt_saas_tenants t', 't.id = d.tenant_id', 'inner')
            ->where('d.domain', $host)
            ->where('d.deleted_at IS NULL', null, false)
            ->where('t.deleted_at IS NULL', null, false)
            ->limit(1)
            ->get()
            ->row_array();

        if (!$row) {
            return ['resolved' => false, 'reason' => 'domain_not_found', 'host' => $host];
        }

        $row['resolved'] = true;
        $row['host'] = $host;
        return $row;
    }
}
