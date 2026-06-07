<?php

defined('BASEPATH') or exit('No direct script access allowed');

class OverageBillingService
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model(KT_SAAS_MODULE . '/Kt_saas_model');
    }

    public function run($limit = 100)
    {
        require_once module_dir_path(KT_SAAS_MODULE, 'services/UsageSnapshotRunner.php');

        $runner = new UsageSnapshotRunner();
        $tenants = $runner->listEligibleTenants($limit);

        $summary = [
            'success'          => true,
            'processed'        => 0,
            'created'          => 0,
            'existing'         => 0,
            'skipped'          => 0,
            'failed'           => 0,
            'tenant_results'   => [],
        ];

        foreach ($tenants as $tenant) {
            $result = $this->createForTenant($tenant);
            $summary['tenant_results'][] = $result;
            $summary['processed']++;

            if (!empty($result['success']) && !empty($result['created'])) {
                $summary['created']++;
                continue;
            }

            if (!empty($result['success']) && !empty($result['existing'])) {
                $summary['existing']++;
                continue;
            }

            if (!empty($result['success'])) {
                $summary['skipped']++;
                continue;
            }

            $summary['failed']++;
        }

        return $summary;
    }

    public function createForTenant(array $tenant)
    {
        require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');

        $subscription = $this->CI->Kt_saas_model->get_tenant_subscription_profile((int) $tenant['id']);
        if (!$subscription || empty($subscription['id'])) {
            return $this->skip($tenant, 'No active subscription profile.');
        }

        $usageSnapshot = $this->CI->Kt_saas_model->get_latest_tenant_usage_snapshot((int) $tenant['id']);
        if (empty($usageSnapshot['snapshot_date']) || empty($usageSnapshot['metrics'])) {
            return $this->skip($tenant, 'No usage snapshot available.');
        }

        $entitlements = new TenantEntitlementService();
        $profile = $entitlements->getRuntimeProfile($tenant);
        $overages = $entitlements->buildOverageSummary($tenant, $profile, $usageSnapshot['metrics']);
        if (empty($overages)) {
            return $this->skip($tenant, 'No overage detected.');
        }

        $period = $this->normalizePeriod((string) ($subscription['billing_cycle'] ?? 'monthly'), (string) $usageSnapshot['snapshot_date']);

        $bestOverage = null;
        foreach ($overages as $metricKey => $overage) {
            $limit = (float) ($overage['limit_value'] ?? 0);
            $current = (float) ($overage['current_value'] ?? 0);
            $ratio = $limit > 0 ? ($current / $limit) * 100 : 0;
            if (!$bestOverage || $ratio > $bestOverage['ratio']) {
                $bestOverage = [
                    'metric_key' => $metricKey,
                    'current_value' => $current,
                    'limit_value' => $limit,
                    'remaining_value' => 0,
                    'ratio' => $ratio,
                ];
            }
        }

        if ($bestOverage) {
            $quotaContext = [
                'tenant_id' => (int) $tenant['id'],
                'tenant' => $tenant,
                'subscription' => $subscription,
                'plan' => $profile['plan'] ?? [],
                'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['company_name'] ?? ''),
                'owner_email' => (string) ($tenant['owner_email'] ?? ''),
                'tenant_name' => (string) ($tenant['company_name'] ?? ''),
                'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
                'workspace_url' => function_exists('kt_saas_tenant_public_base_url') ? rtrim((string) kt_saas_tenant_public_base_url($tenant), '/') : '',
                'workspace_domain' => trim((string) ($tenant['custom_domain'] ?? $tenant['subdomain'] ?? '')),
                'plan_name' => (string) ($subscription['plan_name'] ?? ''),
                'quota_remaining' => '0',
                'quota_limit' => (string) $bestOverage['limit_value'],
                'subscription_status' => (string) ($subscription['status'] ?? ''),
                'related_type' => 'usage',
                'related_id' => (string) $tenant['id'],
                'quota_metric' => (string) $bestOverage['metric_key'],
                'quota_ratio' => number_format((float) $bestOverage['ratio'], 2, '.', ''),
                'dedupe_key' => 'tenant_quota_exceeded|' . (int) $tenant['id'] . '|' . (int) $subscription['id'] . '|' . $period,
            ];
            $this->CI->Kt_saas_model->send_email_event('tenant_quota_exceeded', $quotaContext);
        }
        $existing = $this->CI->Kt_saas_model->find_tenant_invoice_by_reason_period((int) $tenant['id'], (int) $subscription['id'], 'overage_charge', $period);
        if ($existing) {
            return [
                'success'      => true,
                'tenant_id'    => (int) $tenant['id'],
                'tenant_code'  => $tenant['tenant_code'],
                'existing'     => true,
                'created'      => false,
                'invoice_id'   => (int) $existing['id'],
                'period'       => $period,
                'message'      => 'Existing overage invoice found for period.',
            ];
        }

        $lineItems = $this->buildLineItems($overages);
        if (empty($lineItems)) {
            return $this->skip($tenant, 'Overage metrics have no configured rates.');
        }

        $total = array_sum(array_column($lineItems, 'line_total'));
        if ($total <= 0) {
            return $this->skip($tenant, 'Overage total is zero.');
        }

        $invoiceId = $this->insertInvoice($tenant, $subscription, $usageSnapshot, $period, $lineItems, $total);
        $this->CI->Kt_saas_model->log_activity('invoice.overage_created', 'warning', [
            'tenant_id'       => (int) $tenant['id'],
            'subscription_id' => (int) $subscription['id'],
            'invoice_id'      => $invoiceId,
            'overage_period'  => $period,
            'snapshot_date'   => $usageSnapshot['snapshot_date'],
            'line_items'      => $lineItems,
            'grand_total'     => $total,
        ], (int) $tenant['id']);

        return [
            'success'      => true,
            'tenant_id'    => (int) $tenant['id'],
            'tenant_code'  => $tenant['tenant_code'],
            'created'      => true,
            'existing'     => false,
            'invoice_id'   => $invoiceId,
            'period'       => $period,
            'grand_total'  => $total,
        ];
    }

    protected function buildLineItems(array $overages)
    {
        $rates = kt_saas_overage_rates();
        $labels = [
            'staff'      => 'Additional staff',
            'clients'    => 'Additional clients',
            'projects'   => 'Additional projects',
            'invoices'   => 'Additional invoices',
            'warehouses' => 'Additional warehouses',
            'storage_mb' => 'Additional storage (MB)',
        ];

        $items = [];
        foreach ($overages as $metricKey => $overage) {
            if (!isset($rates[$metricKey]) || (float) $rates[$metricKey] <= 0) {
                continue;
            }

            $units = (float) ($overage['excess_value'] ?? 0);
            if ($units <= 0) {
                continue;
            }

            $unitPrice = (float) $rates[$metricKey];
            $items[] = [
                'metric_key'     => $metricKey,
                'label'          => $labels[$metricKey] ?? ucwords(str_replace('_', ' ', $metricKey)),
                'units'          => $units,
                'unit_price'     => $unitPrice,
                'line_total'     => round($units * $unitPrice, 2),
                'current_value'  => (float) ($overage['current_value'] ?? 0),
                'limit_value'    => (float) ($overage['limit_value'] ?? 0),
            ];
        }

        return $items;
    }

    protected function insertInvoice(array $tenant, array $subscription, array $usageSnapshot, $period, array $lineItems, $total)
    {
        $now = date('Y-m-d H:i:s');
        $dueDays = max((int) kt_saas_get_option('kt_saas_billing_due_days', '7'), 0);
        $dueDate = date('Y-m-d', strtotime('+' . $dueDays . ' days'));
        $invoiceNumber = 'OVR-' . strtoupper(trim((string) $tenant['tenant_code'])) . '-' . date('Ymd') . '-' . str_pad((string) (1 + $this->countTenantInvoices((int) $tenant['id'])), 4, '0', STR_PAD_LEFT);

        $db = $this->landlordDb();
        $db->insert(db_prefix() . 'kt_saas_invoices', [
            'tenant_id'       => (int) $tenant['id'],
            'subscription_id' => (int) $subscription['id'],
            'invoice_number'  => $invoiceNumber,
            'status'          => 'draft',
            'currency'        => trim((string) ($subscription['currency'] ?? $tenant['currency'] ?? 'USD')),
            'subtotal'        => $total,
            'tax_total'       => 0,
            'discount_total'  => 0,
            'grand_total'     => $total,
            'issued_at'       => $now,
            'due_date'        => $dueDate,
            'payload_json'    => json_encode([
                'source'          => 'overage_billing_runner',
                'reason'          => 'overage_charge',
                'tenant_code'     => $tenant['tenant_code'],
                'subscription_id' => (int) $subscription['id'],
                'plan_id'         => (int) ($subscription['plan_id'] ?? 0),
                'plan_name'       => $subscription['plan_name'] ?? null,
                'overage_period'  => $period,
                'snapshot_date'   => $usageSnapshot['snapshot_date'],
                'items'           => $lineItems,
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'created_at'      => $now,
            'updated_at'      => $now,
            'created_by'      => null,
            'updated_by'      => null,
        ]);

        return (int) $db->insert_id();
    }

    protected function normalizePeriod($billingCycle, $snapshotDate)
    {
        $timestamp = strtotime($snapshotDate ?: date('Y-m-d'));
        switch ($billingCycle) {
            case 'yearly':
                return date('Y', $timestamp);
            case 'quarterly':
                $month = (int) date('n', $timestamp);
                $quarter = (int) ceil($month / 3);
                return date('Y', $timestamp) . '-Q' . $quarter;
            case 'monthly':
            default:
                return date('Y-m', $timestamp);
        }
    }

    protected function countTenantInvoices($tenantId)
    {
        return (int) $this->landlordDb()
            ->where('tenant_id', (int) $tenantId)
            ->count_all_results(db_prefix() . 'kt_saas_invoices');
    }

    protected function skip(array $tenant, $message)
    {
        return [
            'success'     => true,
            'tenant_id'   => (int) $tenant['id'],
            'tenant_code' => $tenant['tenant_code'],
            'created'     => false,
            'existing'    => false,
            'message'     => $message,
        ];
    }

    protected function landlordDb()
    {
        $landlordDb = $this->CI->config->item('kt_saas_landlord_db');

        return $landlordDb ?: $this->CI->db;
    }
}
