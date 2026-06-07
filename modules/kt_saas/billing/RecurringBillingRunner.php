<?php

defined('BASEPATH') or exit('No direct script access allowed');

class RecurringBillingRunner
{
    protected $CI;
    protected $billing;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model(KT_SAAS_MODULE . '/Kt_saas_model');

        require_once module_dir_path(KT_SAAS_MODULE, 'services/BillingEngineService.php');
        $this->billing = new BillingEngineService();
    }

    public function run($limit = 100)
    {
        require_once module_dir_path(KT_SAAS_MODULE, 'services/OverageBillingService.php');
        $overageBilling = new OverageBillingService();

        $summary = [
            'success'                 => true,
            'trial_ending_notifications' => 0,
            'trial_to_grace'          => 0,
            'trial_to_suspended'      => 0,
            'free_renewals'           => 0,
            'renewal_invoices'        => 0,
            'grace_started'           => 0,
            'grace_to_suspended'      => 0,
            'reactivated_by_free_renewal' => 0,
            'invoices_overdue'        => 0,
            'dunning_attempts'        => 0,
            'overage_invoices'        => 0,
            'overage_existing'        => 0,
        ];

        $summary['trial_ending_notifications'] = $this->processTrialEndingNotifications($limit);
        $this->processTrialExpirations($limit, $summary);
        $this->processDueRenewals($limit, $summary);
        $overageSummary = $overageBilling->run($limit);
        $summary['overage_invoices'] = (int) ($overageSummary['created'] ?? 0);
        $summary['overage_existing'] = (int) ($overageSummary['existing'] ?? 0);
        $summary['grace_to_suspended'] = $this->processExpiredGracePeriods($limit);
        $this->processOverdueInvoices($limit, $summary);

        return $summary;
    }

    protected function processTrialEndingNotifications($limit)
    {
        $rows = $this->CI->db
            ->select('s.*, t.tenant_code, t.company_name, t.owner_email, t.owner_name, p.plan_name, p.plan_code, p.price, p.grace_days, p.currency')
            ->from(db_prefix() . 'kt_saas_subscriptions s')
            ->join(db_prefix() . 'kt_saas_tenants t', 't.id = s.tenant_id', 'inner')
            ->join(db_prefix() . 'kt_saas_plans p', 'p.id = s.plan_id', 'left')
            ->where('s.deleted_at IS NULL', null, false)
            ->where('t.deleted_at IS NULL', null, false)
            ->where('s.status', 'trial')
            ->where('s.trial_ends_at IS NOT NULL', null, false)
            ->where('s.trial_ends_at >', date('Y-m-d H:i:s'))
            ->limit(max(1, (int) $limit))
            ->get()
            ->result_array();

        $today = new DateTimeImmutable(date('Y-m-d 00:00:00'));
        $windows = [7, 3, 1];
        $processed = 0;

        foreach ($rows as $row) {
            $trialEnd = new DateTimeImmutable(substr((string) $row['trial_ends_at'], 0, 10) . ' 00:00:00');
            $daysLeft = (int) $today->diff($trialEnd)->format('%r%a');
            if (!in_array($daysLeft, $windows, true)) {
                continue;
            }

            $tenant = $this->CI->Kt_saas_model->get_tenant((int) $row['tenant_id']);
            $plan = $this->CI->Kt_saas_model->get_plan((int) $row['plan_id']);
            if (!$tenant || !$plan) {
                continue;
            }

            $context = [
                'tenant_id' => (int) $tenant['id'],
                'tenant' => $tenant,
                'subscription' => $row,
                'plan' => $plan,
                'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['company_name'] ?? ''),
                'owner_email' => (string) ($tenant['owner_email'] ?? ''),
                'tenant_name' => (string) ($tenant['company_name'] ?? ''),
                'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
                'trial_end_date' => (string) $row['trial_ends_at'],
                'subscription_status' => (string) ($row['status'] ?? 'trial'),
                'workspace_url' => function_exists('kt_saas_tenant_public_base_url') ? rtrim((string) kt_saas_tenant_public_base_url($tenant), '/') : '',
                'workspace_domain' => trim((string) ($tenant['custom_domain'] ?? $tenant['subdomain'] ?? '')),
                'plan_name' => (string) ($plan['plan_name'] ?? ''),
                'related_type' => 'subscription',
                'related_id' => (string) $row['id'],
                'dedupe_key' => 'tenant_trial_ending|' . (int) $tenant['id'] . '|' . (int) $row['id'] . '|' . (string) $row['trial_ends_at'] . '|' . $daysLeft,
            ];

            $result = $this->CI->Kt_saas_model->send_email_event('tenant_trial_ending', $context);
            if (!empty($result['success'])) {
                $processed++;
            }
        }

        return $processed;
    }

    protected function processTrialExpirations($limit, array &$summary)
    {
        $rows = $this->CI->db
            ->select('s.*, t.tenant_code, t.company_name, t.status as tenant_status, p.plan_name, p.plan_code, p.price, p.grace_days, p.currency')
            ->from(db_prefix() . 'kt_saas_subscriptions s')
            ->join(db_prefix() . 'kt_saas_tenants t', 't.id = s.tenant_id', 'inner')
            ->join(db_prefix() . 'kt_saas_plans p', 'p.id = s.plan_id', 'left')
            ->where('s.deleted_at IS NULL', null, false)
            ->where('t.deleted_at IS NULL', null, false)
            ->where('s.status', 'trial')
            ->where('s.trial_ends_at IS NOT NULL', null, false)
            ->where('s.trial_ends_at <=', date('Y-m-d H:i:s'))
            ->limit(max(1, (int) $limit))
            ->get()
            ->result_array();

        $processed = 0;
        foreach ($rows as $row) {
            $tenant = $this->CI->Kt_saas_model->get_tenant((int) $row['tenant_id']);
            $plan = $this->CI->Kt_saas_model->get_plan((int) $row['plan_id']);
            if (!$tenant || !$plan) {
                continue;
            }

            $graceEndsAt = $this->billing->graceEndDate((int) ($plan['grace_days'] ?? 0));
            $subscriptionStatus = $graceEndsAt ? 'grace' : 'suspended';
            $tenantStatus = $graceEndsAt ? 'grace' : 'suspended';

            $this->updateSubscription((int) $row['id'], [
                'status'        => $subscriptionStatus,
                'grace_ends_at' => $graceEndsAt ? $graceEndsAt->format('Y-m-d H:i:s') : null,
                'next_billing_at' => null,
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);

            $this->updateTenantStatus((int) $tenant['id'], $tenantStatus);

            $eventKey = $graceEndsAt ? 'subscription.grace_started' : 'subscription.suspended';
            $this->logActivity($eventKey, $graceEndsAt ? 'warning' : 'danger', [
                'tenant_id'        => (int) $tenant['id'],
                'subscription_id'  => (int) $row['id'],
                'trial_ends_at'    => $row['trial_ends_at'],
                'grace_ends_at'    => $graceEndsAt ? $graceEndsAt->format('Y-m-d H:i:s') : null,
            ], (int) $tenant['id']);

            if ($graceEndsAt) {
                $summary['trial_to_grace']++;
            } else {
                $summary['trial_to_suspended']++;
            }

            $trialContext = [
                'tenant_id' => (int) $tenant['id'],
                'tenant' => $tenant,
                'subscription' => $row,
                'plan' => $plan,
                'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['company_name'] ?? ''),
                'owner_email' => (string) ($tenant['owner_email'] ?? ''),
                'tenant_name' => (string) ($tenant['company_name'] ?? ''),
                'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
                'trial_end_date' => (string) $row['trial_ends_at'],
                'subscription_status' => (string) $subscriptionStatus,
                'workspace_url' => function_exists('kt_saas_tenant_public_base_url') ? rtrim((string) kt_saas_tenant_public_base_url($tenant), '/') : '',
                'workspace_domain' => trim((string) ($tenant['custom_domain'] ?? $tenant['subdomain'] ?? '')),
                'plan_name' => (string) ($plan['plan_name'] ?? ''),
                'related_type' => 'subscription',
                'related_id' => (string) $row['id'],
                'dedupe_key' => 'tenant_trial_expired|' . (int) $tenant['id'] . '|' . (int) $row['id'] . '|' . (string) $row['trial_ends_at'],
            ];
            $this->CI->Kt_saas_model->send_email_event('tenant_trial_expired', $trialContext);
            if (!$graceEndsAt) {
                $expiredContext = $trialContext;
                $expiredContext['subscription_status'] = 'suspended';
                $expiredContext['dedupe_key'] = 'tenant_subscription_expired|' . (int) $tenant['id'] . '|' . (int) $row['id'] . '|' . (string) $row['trial_ends_at'];
                $this->CI->Kt_saas_model->send_email_event('tenant_subscription_expired', $expiredContext);
            }

            $processed++;
        }

        return $processed;
    }

    protected function processDueRenewals($limit, array &$summary)
    {
        $rows = $this->CI->db
            ->select('s.*, t.tenant_code, t.company_name, t.status as tenant_status, p.plan_name, p.plan_code, p.price, p.grace_days, p.currency')
            ->from(db_prefix() . 'kt_saas_subscriptions s')
            ->join(db_prefix() . 'kt_saas_tenants t', 't.id = s.tenant_id', 'inner')
            ->join(db_prefix() . 'kt_saas_plans p', 'p.id = s.plan_id', 'left')
            ->where('s.deleted_at IS NULL', null, false)
            ->where('t.deleted_at IS NULL', null, false)
            ->where_in('s.status', ['active', 'grace'])
            ->where('s.auto_renew', 1)
            ->where('s.next_billing_at IS NOT NULL', null, false)
            ->where('s.next_billing_at <=', date('Y-m-d H:i:s'))
            ->limit(max(1, (int) $limit))
            ->get()
            ->result_array();

        $processed = 0;
        foreach ($rows as $row) {
            $tenant = $this->CI->Kt_saas_model->get_tenant((int) $row['tenant_id']);
            if (!$tenant) {
                continue;
            }

            $this->applyScheduledPlanChangeIfDue($row, $tenant);
            $plan = $this->CI->Kt_saas_model->get_plan((int) $row['plan_id']);
            if (!$plan) {
                continue;
            }

            $price = (float) ($plan['price'] ?? 0);
            $now = new DateTimeImmutable(date('Y-m-d H:i:s'));
            $periodStart = $row['next_billing_at'] ? new DateTimeImmutable($row['next_billing_at']) : $now;
            $periodEnd = $this->billing->nextInvoiceDate($row['billing_cycle'] ?: 'monthly', $periodStart);

            if ($price <= 0) {
                $this->updateSubscription((int) $row['id'], [
                    'status'                  => 'active',
                    'current_period_start_at' => $periodStart->format('Y-m-d H:i:s'),
                    'current_period_end_at'   => $periodEnd->format('Y-m-d H:i:s'),
                    'next_billing_at'         => $periodEnd->format('Y-m-d H:i:s'),
                    'grace_ends_at'           => null,
                    'renewal_attempts'        => 0,
                    'updated_at'              => date('Y-m-d H:i:s'),
                ]);

                $this->updateTenantStatus((int) $tenant['id'], 'active');
                $this->logActivity('subscription.renewed', 'info', [
                    'tenant_id'       => (int) $tenant['id'],
                    'subscription_id' => (int) $row['id'],
                    'price'           => $price,
                    'next_billing_at' => $periodEnd->format('Y-m-d H:i:s'),
                ], (int) $tenant['id']);

                $summary['free_renewals']++;
                if (($row['tenant_status'] ?? '') !== 'active') {
                    $summary['reactivated_by_free_renewal']++;
                }

                $renewedContext = [
                    'tenant_id' => (int) $tenant['id'],
                    'tenant' => $tenant,
                    'subscription' => $this->CI->Kt_saas_model->get_current_subscription((int) $tenant['id']) ?: $row,
                    'plan' => $plan,
                    'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['company_name'] ?? ''),
                    'owner_email' => (string) ($tenant['owner_email'] ?? ''),
                    'tenant_name' => (string) ($tenant['company_name'] ?? ''),
                    'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
                    'trial_end_date' => (string) ($row['trial_ends_at'] ?? ''),
                    'subscription_status' => 'active',
                    'workspace_url' => function_exists('kt_saas_tenant_public_base_url') ? rtrim((string) kt_saas_tenant_public_base_url($tenant), '/') : '',
                    'workspace_domain' => trim((string) ($tenant['custom_domain'] ?? $tenant['subdomain'] ?? '')),
                    'plan_name' => (string) ($plan['plan_name'] ?? ''),
                    'related_type' => 'subscription',
                    'related_id' => (string) $row['id'],
                    'dedupe_key' => 'tenant_subscription_renewed|' . (int) $tenant['id'] . '|' . (int) $row['id'] . '|' . $periodEnd->format('Y-m-d H:i:s'),
                ];
                $this->CI->Kt_saas_model->send_email_event('tenant_subscription_renewed', $renewedContext);
                $processed++;
                continue;
            }

            $invoice = $this->billing->createSubscriptionInvoice($tenant, $row, $plan, [
                'reason' => 'subscription_renewal',
            ]);

            if (!empty($invoice['created'])) {
                $summary['renewal_invoices']++;
            }

            $graceEndsAt = $this->billing->graceEndDate((int) ($plan['grace_days'] ?? 0), $now);
            $nextStatus = $graceEndsAt ? 'grace' : 'suspended';

            $this->updateSubscription((int) $row['id'], [
                'status'           => $nextStatus,
                'grace_ends_at'    => $graceEndsAt ? $graceEndsAt->format('Y-m-d H:i:s') : null,
                'next_billing_at'  => $periodEnd->format('Y-m-d H:i:s'),
                'renewal_attempts' => (int) $row['renewal_attempts'] + (!empty($invoice['created']) ? 1 : 0),
                'updated_at'       => date('Y-m-d H:i:s'),
            ]);
            $this->updateTenantStatus((int) $tenant['id'], $nextStatus);

            $this->logActivity(
                $graceEndsAt ? 'subscription.grace_started' : 'subscription.suspended',
                $graceEndsAt ? 'warning' : 'danger',
                [
                    'tenant_id'       => (int) $tenant['id'],
                    'subscription_id' => (int) $row['id'],
                    'invoice_id'      => $invoice['invoice_id'] ?? null,
                    'grace_ends_at'   => $graceEndsAt ? $graceEndsAt->format('Y-m-d H:i:s') : null,
                    'price'           => $price,
                ],
                (int) $tenant['id']
            );

            if ($graceEndsAt) {
                $summary['grace_started']++;
            }

            $renewalFailedContext = $this->buildInvoiceEmailContext($row, $tenant, $plan, $invoice, [
                'event_key' => 'renewal_failed',
                'error_message' => 'Renewal payment is pending.',
                'subscription_status' => $nextStatus,
                'dedupe_key' => 'renewal_failed|' . (int) $tenant['id'] . '|' . (int) $row['id'] . '|' . $periodEnd->format('Y-m-d H:i:s'),
            ]);
            $this->CI->Kt_saas_model->send_email_event('renewal_failed', $renewalFailedContext);

            if ($nextStatus === 'suspended') {
                $expiredContext = $this->buildSubscriptionEmailContext($row, $tenant, $plan, [
                    'subscription_status' => 'suspended',
                    'dedupe_key' => 'tenant_subscription_expired|' . (int) $tenant['id'] . '|' . (int) $row['id'] . '|' . $periodEnd->format('Y-m-d H:i:s'),
                ]);
                $this->CI->Kt_saas_model->send_email_event('tenant_subscription_expired', $expiredContext);
            }

            $processed++;
        }

        return $processed;
    }

    protected function processExpiredGracePeriods($limit)
    {
        $rows = $this->CI->db
            ->select('s.*, t.tenant_code, t.company_name')
            ->from(db_prefix() . 'kt_saas_subscriptions s')
            ->join(db_prefix() . 'kt_saas_tenants t', 't.id = s.tenant_id', 'inner')
            ->where('s.deleted_at IS NULL', null, false)
            ->where('t.deleted_at IS NULL', null, false)
            ->where('s.status', 'grace')
            ->where('s.grace_ends_at IS NOT NULL', null, false)
            ->where('s.grace_ends_at <=', date('Y-m-d H:i:s'))
            ->limit(max(1, (int) $limit))
            ->get()
            ->result_array();

        $processed = 0;
        foreach ($rows as $row) {
            $tenant = $this->CI->Kt_saas_model->get_tenant((int) $row['tenant_id']);
            $plan = $this->CI->Kt_saas_model->get_plan((int) $row['plan_id']);

            $this->updateSubscription((int) $row['id'], [
                'status'      => 'suspended',
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);

            $this->updateTenantStatus((int) $row['tenant_id'], 'suspended');
            $this->logActivity('tenant.suspended', 'danger', [
                'tenant_id'       => (int) $row['tenant_id'],
                'subscription_id' => (int) $row['id'],
                'grace_ends_at'   => $row['grace_ends_at'],
                'reason'          => 'grace_expired',
            ], (int) $row['tenant_id']);

            if ($tenant && $plan) {
                $expiredContext = $this->buildSubscriptionEmailContext($row, $tenant, $plan, [
                    'subscription_status' => 'suspended',
                    'dedupe_key' => 'tenant_subscription_expired|' . (int) $tenant['id'] . '|' . (int) $row['id'] . '|grace_expired|' . (string) $row['grace_ends_at'],
                ]);
                $this->CI->Kt_saas_model->send_email_event('tenant_subscription_expired', $expiredContext);
            }

            $processed++;
        }

        return $processed;
    }

    protected function processOverdueInvoices($limit, array &$summary)
    {
        $intervalDays = max((int) kt_saas_get_option('kt_saas_billing_dunning_interval_days', '2'), 1);
        $maxAttempts = max((int) kt_saas_get_option('kt_saas_billing_dunning_max_attempts', '3'), 1);
        $rows = $this->CI->Kt_saas_model->get_overdue_invoices($limit);

        $processed = 0;
        foreach ($rows as $row) {
            $justMarkedOverdue = false;
            if (($row['status'] ?? '') !== 'overdue') {
                if ($this->billing->markInvoiceOverdue($row, ['reason' => 'due_date_passed'])) {
                    $summary['invoices_overdue']++;
                    $justMarkedOverdue = true;
                }
                $row['status'] = 'overdue';
            }

            if ($justMarkedOverdue) {
                $tenant = !empty($row['tenant_id']) ? $this->CI->Kt_saas_model->get_tenant((int) $row['tenant_id']) : null;
                $subscription = !empty($row['subscription_id']) ? $this->CI->Kt_saas_model->get_subscription((int) $row['subscription_id']) : [];
                $plan = !empty($subscription['plan_id']) ? $this->CI->Kt_saas_model->get_plan((int) $subscription['plan_id']) : [];
                if ($tenant) {
                    $overdueContext = $this->buildInvoiceEmailContext($subscription ?: [], $tenant, $plan ?: [], $row, [
                        'event_key' => 'invoice_overdue',
                        'invoice_status' => 'overdue',
                        'error_message' => 'Invoice is overdue.',
                        'dedupe_key' => 'invoice_overdue|' . (int) $row['id'] . '|' . (string) ($row['due_date'] ?? ''),
                    ]);
                    $this->CI->Kt_saas_model->send_email_event('invoice_overdue', $overdueContext);
                }
            }

            $lastReminderAt = !empty($row['last_reminder_at']) ? strtotime($row['last_reminder_at']) : null;
            $shouldRemind = $lastReminderAt === null || $lastReminderAt <= strtotime('-' . $intervalDays . ' days');
            $currentReminders = (int) ($row['reminder_count'] ?? 0);

            if ($shouldRemind && $currentReminders < $maxAttempts) {
                $this->billing->recordDunningAttempt($row, [
                    'reason'             => 'invoice_overdue',
                    'interval_days'      => $intervalDays,
                    'max_attempts'       => $maxAttempts,
                    'current_attempt'    => $currentReminders + 1,
                ]);
                $summary['dunning_attempts']++;
            }

            $processed++;
        }

        return $processed;
    }

    protected function applyScheduledPlanChangeIfDue(array &$subscriptionRow, array $tenant)
    {
        $metadata = json_decode((string) ($subscriptionRow['metadata_json'] ?? ''), true);
        if (!is_array($metadata) || empty($metadata['scheduled_plan_change']) || !is_array($metadata['scheduled_plan_change'])) {
            return;
        }

        $scheduled = $metadata['scheduled_plan_change'];
        $scheduledAt = trim((string) ($scheduled['scheduled_at'] ?? ''));
        if ($scheduledAt !== '' && strtotime($scheduledAt) > time()) {
            return;
        }

        $targetPlanId = (int) ($scheduled['target_plan_id'] ?? 0);
        if ($targetPlanId <= 0) {
            return;
        }

        $targetPlan = $this->CI->Kt_saas_model->get_plan($targetPlanId);
        if (!$targetPlan || (int) ($targetPlan['is_active'] ?? 0) !== 1) {
            $this->logActivity('subscription.plan_change_schedule_invalid', 'warning', [
                'tenant_id'       => (int) $tenant['id'],
                'subscription_id' => (int) $subscriptionRow['id'],
                'target_plan_id'  => $targetPlanId,
            ], (int) $tenant['id']);
            return;
        }

        unset($metadata['scheduled_plan_change']);
        $metadataJson = empty($metadata)
            ? null
            : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        $this->updateSubscription((int) $subscriptionRow['id'], [
            'plan_id'       => (int) $targetPlan['id'],
            'billing_cycle' => $targetPlan['billing_cycle'] ?: 'monthly',
            'metadata_json' => $metadataJson,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        $this->CI->Kt_saas_model->update_tenant((int) $tenant['id'], [
            'plan_id'    => (int) $targetPlan['id'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $subscriptionRow['plan_id'] = (int) $targetPlan['id'];
        $subscriptionRow['billing_cycle'] = $targetPlan['billing_cycle'] ?: 'monthly';
        $subscriptionRow['metadata_json'] = $metadataJson;

        $this->logActivity('subscription.plan_change_applied', 'success', [
            'tenant_id'         => (int) $tenant['id'],
            'subscription_id'   => (int) $subscriptionRow['id'],
            'previous_plan_id'  => (int) ($scheduled['current_plan_id'] ?? 0),
            'target_plan_id'    => (int) $targetPlan['id'],
            'change_type'       => (string) ($scheduled['change_type'] ?? 'downgrade'),
            'applied_at'        => date('Y-m-d H:i:s'),
        ], (int) $tenant['id']);

        $currentSubscription = $this->CI->Kt_saas_model->get_current_subscription((int) $tenant['id']) ?: $subscriptionRow;
        $planChangedContext = [
            'tenant_id' => (int) $tenant['id'],
            'tenant' => $tenant,
            'subscription' => $currentSubscription,
            'plan' => $targetPlan,
            'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['company_name'] ?? ''),
            'owner_email' => (string) ($tenant['owner_email'] ?? ''),
            'tenant_name' => (string) ($tenant['company_name'] ?? ''),
            'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
            'trial_end_date' => (string) ($subscriptionRow['trial_ends_at'] ?? ''),
            'subscription_status' => (string) ($currentSubscription['status'] ?? $subscriptionRow['status'] ?? 'active'),
            'workspace_url' => function_exists('kt_saas_tenant_public_base_url') ? rtrim((string) kt_saas_tenant_public_base_url($tenant), '/') : '',
            'workspace_domain' => trim((string) ($tenant['custom_domain'] ?? $tenant['subdomain'] ?? '')),
            'plan_name' => (string) ($targetPlan['plan_name'] ?? ''),
            'related_type' => 'subscription',
            'related_id' => (string) $subscriptionRow['id'],
            'dedupe_key' => 'tenant_plan_changed|' . (int) $tenant['id'] . '|' . (int) $subscriptionRow['id'] . '|' . (int) $targetPlan['id'] . '|' . (string) ($scheduled['scheduled_at'] ?? date('Y-m-d H:i:s')),
        ];
        $this->CI->Kt_saas_model->send_email_event('tenant_plan_changed', $planChangedContext);
    }

    protected function buildSubscriptionEmailContext(array $subscription, array $tenant, array $plan, array $overrides = [])
    {
        $context = [
            'tenant_id' => (int) $tenant['id'],
            'tenant' => $tenant,
            'subscription' => $subscription,
            'plan' => $plan,
            'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['company_name'] ?? ''),
            'owner_email' => (string) ($tenant['owner_email'] ?? ''),
            'tenant_name' => (string) ($tenant['company_name'] ?? ''),
            'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
            'trial_end_date' => (string) ($subscription['trial_ends_at'] ?? ''),
            'subscription_status' => (string) ($subscription['status'] ?? ''),
            'workspace_url' => function_exists('kt_saas_tenant_public_base_url') ? rtrim((string) kt_saas_tenant_public_base_url($tenant), '/') : '',
            'workspace_domain' => trim((string) ($tenant['custom_domain'] ?? $tenant['subdomain'] ?? '')),
            'plan_name' => (string) ($plan['plan_name'] ?? ''),
            'related_type' => 'subscription',
            'related_id' => (string) ($subscription['id'] ?? ''),
        ];

        return array_merge($context, $overrides);
    }

    protected function buildInvoiceEmailContext(array $subscription, array $tenant, array $plan, array $invoice, array $overrides = [])
    {
        require_once module_dir_path(KT_SAAS_MODULE, 'services/PaymentCollectionService.php');
        $payments = new PaymentCollectionService();

        $invoiceId = (int) ($invoice['invoice_id'] ?? $invoice['id'] ?? 0);
        $invoiceRow = !empty($invoice['invoice_id']) ? $this->CI->Kt_saas_model->get_invoice($invoiceId) : $invoice;
        if (!$invoiceRow) {
            $invoiceRow = $invoice;
        }

        $paymentUrl = $invoiceId > 0 ? (string) $payments->getCheckoutUrl($invoiceRow, $tenant) : '';
        $context = $this->buildSubscriptionEmailContext($subscription, $tenant, $plan, [
            'invoice' => $invoiceRow,
            'invoice_id' => $invoiceId,
            'invoice_number' => (string) ($invoiceRow['invoice_number'] ?? ''),
            'invoice_status' => (string) ($invoiceRow['status'] ?? ''),
            'invoice_total' => (string) ($invoiceRow['grand_total'] ?? $invoiceRow['total'] ?? ''),
            'currency' => (string) ($invoiceRow['currency'] ?? $plan['currency'] ?? ''),
            'payment_url' => $paymentUrl,
            'invoice_url' => $paymentUrl,
            'related_type' => 'invoice',
            'related_id' => (string) $invoiceId,
        ]);

        return array_merge($context, $overrides);
    }

    protected function updateSubscription($subscriptionId, array $payload)
    {
        $payload['updated_at'] = $payload['updated_at'] ?? date('Y-m-d H:i:s');

        $this->CI->db
            ->where('id', (int) $subscriptionId)
            ->update(db_prefix() . 'kt_saas_subscriptions', $payload);
    }

    protected function updateTenantStatus($tenantId, $status)
    {
        $payload = [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($status === 'suspended') {
            $payload['suspended_at'] = date('Y-m-d H:i:s');
        } else {
            $payload['suspended_at'] = null;
        }

        $this->CI->db
            ->where('id', (int) $tenantId)
            ->update(db_prefix() . 'kt_saas_tenants', $payload);
    }

    protected function logActivity($eventKey, $severity, array $context, $tenantId = null)
    {
        $this->CI->Kt_saas_model->log_activity($eventKey, $severity, $context, $tenantId);
    }
}
