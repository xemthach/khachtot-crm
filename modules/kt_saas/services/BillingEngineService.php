<?php

defined('BASEPATH') or exit('No direct script access allowed');

class BillingEngineService
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model(KT_SAAS_MODULE . '/Kt_saas_model');
    }

    public function nextInvoiceDate($billingCycle, DateTimeInterface $from)
    {
        $date = new DateTimeImmutable($from->format('Y-m-d H:i:s'));

        switch ($billingCycle) {
            case 'yearly':
                return $date->modify('+1 year');
            case 'quarterly':
                return $date->modify('+3 months');
            case 'monthly':
            default:
                return $date->modify('+1 month');
        }
    }

    public function graceEndDate($graceDays, ?DateTimeInterface $from = null)
    {
        $from = $from ?: new DateTimeImmutable(date('Y-m-d H:i:s'));
        $days = max(0, (int) $graceDays);

        if ($days === 0) {
            return null;
        }

        return (new DateTimeImmutable($from->format('Y-m-d H:i:s')))
            ->modify('+' . $days . ' days')
            ->setTime(23, 59, 59);
    }

    public function buildInvoiceNumber(array $tenant, array $subscription)
    {
        $tenantCode = strtoupper(trim((string) ($tenant['tenant_code'] ?? 'TENANT')));
        $datePart = date('Ymd');
        $sequence = str_pad((string) (1 + $this->countInvoicesForTenant((int) $tenant['id'])), 4, '0', STR_PAD_LEFT);

        return 'SaaS-' . $tenantCode . '-' . $datePart . '-' . $sequence;
    }

    public function createSubscriptionInvoice(array $tenant, array $subscription, array $plan, array $context = [])
    {
        $reason = trim((string) ($context['reason'] ?? 'subscription_renewal'));
        $amounts = $this->buildSubscriptionInvoiceBreakdown($tenant, $subscription, $plan, $context);
        $existing = $this->findOpenInvoice((int) $tenant['id'], (int) $subscription['id'], $reason);
        if ($existing) {
            $this->syncInvoiceAmountIfNeeded((int) $existing['id'], $amounts, $tenant, $subscription, $plan, $context);

            return [
                'success'        => true,
                'invoice_id'     => (int) $existing['id'],
                'created'        => false,
                'invoice_number' => (string) ($existing['invoice_number'] ?? ''),
                'paid'           => (float) $amounts['grand_total'] <= 0,
            ];
        }

        $now = date('Y-m-d H:i:s');
        $invoiceNumber = $this->buildInvoiceNumber($tenant, $subscription);
        $currency = trim((string) ($amounts['currency'] ?? $plan['currency'] ?? $tenant['currency'] ?? 'USD'));
        $dueDays = max((int) kt_saas_get_option('kt_saas_billing_due_days', '7'), 0);
        $dueDate = date('Y-m-d', strtotime('+' . $dueDays . ' days'));
        $status = ((float) $amounts['grand_total'] > 0) ? 'pending_payment' : 'paid';

        $this->landlordDb()->insert(db_prefix() . 'kt_saas_invoices', [
            'tenant_id'       => (int) $tenant['id'],
            'subscription_id' => (int) $subscription['id'],
            'invoice_number'  => $invoiceNumber,
            'status'          => $status,
            'currency'        => $currency,
            'subtotal'        => (float) $amounts['subtotal'],
            'tax_total'       => 0,
            'discount_total'  => 0,
            'grand_total'     => (float) $amounts['grand_total'],
            'issued_at'       => $now,
            'due_date'        => $dueDate,
            'paid_at'         => ((float) $amounts['grand_total'] > 0) ? null : $now,
            'payload_json'    => json_encode([
                'source'             => trim((string) ($context['source'] ?? 'recurring_billing_runner')),
                'reason'             => $reason,
                'billing_cycle'      => $subscription['billing_cycle'],
                'tenant_code'        => $tenant['tenant_code'],
                'plan_code'          => $plan['plan_code'] ?? null,
                'current_period_end' => $subscription['current_period_end_at'] ?? null,
                'line_items'         => $amounts['line_items'],
                'billing_summary'    => $amounts,
                'context'            => $context,
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'created_at'      => $now,
            'updated_at'      => $now,
            'created_by'      => null,
            'updated_by'      => null,
        ]);

        return [
            'success'    => true,
            'invoice_id' => (int) $this->landlordDb()->insert_id(),
            'created'    => true,
            'invoice_number' => $invoiceNumber,
            'paid'       => ((float) $amounts['grand_total'] <= 0),
        ];
    }

    public function createPlanChangeRequestInvoice(array $tenant, array $subscription, array $targetPlan, array $context = [])
    {
        $reason = trim((string) ($context['reason'] ?? 'plan_change_request'));
        $existing = $this->findOpenInvoice((int) $tenant['id'], (int) $subscription['id'], $reason, (int) $targetPlan['id']);
        if ($existing) {
            return ['success' => true, 'invoice_id' => (int) $existing['id'], 'created' => false];
        }

        $currentPlanId = (int) ($subscription['plan_id'] ?? 0);
        $targetPlanId = (int) ($targetPlan['id'] ?? 0);
        $currentPlanPrice = (float) ($subscription['price'] ?? 0);
        $targetPlanPrice = (float) ($targetPlan['price'] ?? 0);
        $changeType = $targetPlanId === $currentPlanId
            ? 'renewal'
            : ($targetPlanPrice > $currentPlanPrice ? 'upgrade' : 'downgrade');

        if ($changeType === 'downgrade') {
            return $this->schedulePlanChange($tenant, $subscription, $targetPlan, $context, $reason, $changeType);
        }

        $now = date('Y-m-d H:i:s');
        $invoiceNumber = $this->buildInvoiceNumber($tenant, $subscription);
        $breakdown = $this->buildPlanChangeInvoiceBreakdown($tenant, $subscription, $targetPlan, $context);
        $currency = trim((string) ($breakdown['currency'] ?? $targetPlan['currency'] ?? $tenant['currency'] ?? 'USD'));
        $dueDays = max((int) kt_saas_get_option('kt_saas_billing_due_days', '7'), 0);
        $dueDate = date('Y-m-d', strtotime('+' . $dueDays . ' days'));

        $this->landlordDb()->insert(db_prefix() . 'kt_saas_invoices', [
            'tenant_id'       => (int) $tenant['id'],
            'subscription_id' => (int) $subscription['id'],
            'invoice_number'  => $invoiceNumber,
            'status'          => 'draft',
            'currency'        => $currency,
            'subtotal'        => (float) $breakdown['subtotal'],
            'tax_total'       => 0,
            'discount_total'  => 0,
            'grand_total'     => (float) $breakdown['grand_total'],
            'issued_at'       => $now,
            'due_date'        => $dueDate,
            'payload_json'    => json_encode([
                'source'                 => 'tenant_portal',
                'reason'                 => $reason,
                'change_type'            => $changeType,
                'tenant_code'            => $tenant['tenant_code'],
                'subscription_id'        => (int) $subscription['id'],
                'current_plan_id'        => $currentPlanId,
                'current_plan_name'      => $subscription['plan_name'] ?? null,
                'target_plan_id'         => $targetPlanId,
                'target_plan_code'       => $targetPlan['plan_code'] ?? null,
                'target_plan_name'       => $targetPlan['plan_name'] ?? null,
                'target_billing_cycle'   => $targetPlan['billing_cycle'] ?? null,
                'line_items'             => $breakdown['line_items'],
                'billing_summary'        => $breakdown,
                'context'                => $context,
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'created_at'      => $now,
            'updated_at'      => $now,
            'created_by'      => null,
            'updated_by'      => null,
        ]);

        $invoiceId = (int) $this->landlordDb()->insert_id();
        $this->CI->Kt_saas_model->log_activity('subscription.plan_change_requested', 'info', [
            'tenant_id'         => (int) $tenant['id'],
            'subscription_id'   => (int) $subscription['id'],
            'invoice_id'        => $invoiceId,
            'current_plan_id'   => $currentPlanId,
            'target_plan_id'    => $targetPlanId,
            'change_type'       => $changeType,
        ], (int) $tenant['id']);

        return [
            'success'        => true,
            'invoice_id'     => $invoiceId,
            'created'        => true,
            'invoice_number' => $invoiceNumber,
            'change_type'    => $changeType,
        ];
    }

    protected function buildSubscriptionInvoiceBreakdown(array $tenant, array $subscription, array $plan, array $context = [])
    {
        $reason = trim((string) ($context['reason'] ?? 'subscription_renewal'));
        $price = max(0.0, (float) ($plan['price'] ?? 0));
        $setupFee = $this->shouldApplySetupFeeForInvoiceReason($reason) ? max(0.0, (float) ($plan['setup_fee'] ?? 0)) : 0.0;
        $currency = trim((string) ($plan['currency'] ?? $tenant['currency'] ?? 'VND'));

        $lineItems = [
            [
                'type'     => 'subscription',
                'label'    => (string) ($plan['plan_name'] ?? 'Gói dịch vụ'),
                'amount'   => round($price, 2),
                'quantity' => 1,
            ],
        ];
        if ($setupFee > 0) {
            $lineItems[] = [
                'type'     => 'setup_fee',
                'label'    => 'Phí triển khai ban đầu',
                'amount'   => round($setupFee, 2),
                'quantity' => 1,
            ];
        }

        $subtotal = round($price + $setupFee, 2);

        return [
            'reason'         => $reason,
            'currency'       => $currency,
            'plan_price'     => round($price, 2),
            'setup_fee'      => round($setupFee, 2),
            'subtotal'       => $subtotal,
            'grand_total'    => $subtotal,
            'line_items'     => $lineItems,
            'invoice_mode'   => $reason === 'subscription_renewal' ? 'renewal' : 'initial',
        ];
    }

    protected function buildPlanChangeInvoiceBreakdown(array $tenant, array $subscription, array $targetPlan, array $context = [])
    {
        $price = max(0.0, (float) ($targetPlan['price'] ?? 0));
        $setupFee = max(0.0, (float) ($targetPlan['setup_fee'] ?? 0));
        $currency = trim((string) ($targetPlan['currency'] ?? $tenant['currency'] ?? 'VND'));

        $lineItems = [
            [
                'type'     => 'subscription',
                'label'    => (string) ($targetPlan['plan_name'] ?? 'Gói dịch vụ'),
                'amount'   => round($price, 2),
                'quantity' => 1,
            ],
        ];
        if ($setupFee > 0) {
            $lineItems[] = [
                'type'     => 'setup_fee',
                'label'    => 'Phí triển khai ban đầu',
                'amount'   => round($setupFee, 2),
                'quantity' => 1,
            ];
        }

        $subtotal = round($price + $setupFee, 2);

        return [
            'currency'    => $currency,
            'plan_price'  => round($price, 2),
            'setup_fee'   => round($setupFee, 2),
            'subtotal'    => $subtotal,
            'grand_total' => $subtotal,
            'line_items'  => $lineItems,
        ];
    }

    protected function shouldApplySetupFeeForInvoiceReason($reason)
    {
        $reason = trim((string) $reason);
        if ($reason === '') {
            return false;
        }

        return !in_array($reason, ['subscription_renewal', 'renewal', 'renewal_invoice'], true);
    }

    protected function syncInvoiceAmountIfNeeded($invoiceId, array $amounts, array $tenant, array $subscription, array $plan, array $context = [])
    {
        $invoice = $this->CI->Kt_saas_model->get_invoice((int) $invoiceId);
        if (!$invoice) {
            return false;
        }

        $currentSubtotal = round((float) ($invoice['subtotal'] ?? 0), 2);
        $currentGrandTotal = round((float) ($invoice['grand_total'] ?? 0), 2);
        $expectedSubtotal = round((float) ($amounts['subtotal'] ?? 0), 2);
        $expectedGrandTotal = round((float) ($amounts['grand_total'] ?? 0), 2);

        if (abs($currentSubtotal - $expectedSubtotal) <= 0.01 && abs($currentGrandTotal - $expectedGrandTotal) <= 0.01) {
            return false;
        }

        $reason = trim((string) ($context['reason'] ?? 'subscription_renewal'));
        $now = date('Y-m-d H:i:s');
        $status = $expectedGrandTotal > 0 ? 'pending_payment' : 'paid';
        $paidAt = $expectedGrandTotal > 0 ? null : ($invoice['paid_at'] ?? $now);

        $payload = json_decode((string) ($invoice['payload_json'] ?? ''), true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $payload['reason'] = $reason;
        $payload['billing_cycle'] = $subscription['billing_cycle'] ?? ($payload['billing_cycle'] ?? null);
        $payload['tenant_code'] = $tenant['tenant_code'] ?? ($payload['tenant_code'] ?? null);
        $payload['plan_code'] = $plan['plan_code'] ?? ($payload['plan_code'] ?? null);
        $payload['line_items'] = $amounts['line_items'];
        $payload['billing_summary'] = $amounts;
        $payload['context'] = $context;

        $this->CI->Kt_saas_model->update_invoice((int) $invoiceId, [
            'status'       => $status,
            'currency'     => (string) ($amounts['currency'] ?? $invoice['currency'] ?? 'VND'),
            'subtotal'     => $expectedSubtotal,
            'tax_total'    => 0,
            'discount_total' => 0,
            'grand_total'  => $expectedGrandTotal,
            'paid_at'      => $paidAt,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'updated_at'   => $now,
        ]);

        $this->CI->Kt_saas_model->log_activity('invoice.amount_synced', 'info', [
            'invoice_id'        => (int) $invoiceId,
            'tenant_id'         => (int) ($invoice['tenant_id'] ?? 0),
            'invoice_number'    => (string) ($invoice['invoice_number'] ?? ''),
            'expected_subtotal' => $expectedSubtotal,
            'expected_total'    => $expectedGrandTotal,
            'reason'            => $reason,
        ], (int) ($invoice['tenant_id'] ?? 0));

        return true;
    }

    public function markInvoiceOverdue(array $invoice, array $context = [])
    {
        if (empty($invoice['id']) || in_array($invoice['status'], ['paid', 'cancelled'], true)) {
            return false;
        }

        $payload = [
            'status'     => 'overdue',
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->CI->Kt_saas_model->update_invoice((int) $invoice['id'], $payload);

        $this->CI->Kt_saas_model->log_activity('invoice.overdue', 'warning', array_merge([
            'invoice_id'      => (int) $invoice['id'],
            'tenant_id'       => (int) $invoice['tenant_id'],
            'invoice_number'  => $invoice['invoice_number'],
            'due_date'        => $invoice['due_date'],
        ], $context), (int) $invoice['tenant_id']);

        return true;
    }

    public function recordDunningAttempt(array $invoice, array $context = [])
    {
        if (empty($invoice['id'])) {
            return false;
        }

        $count = (int) ($invoice['reminder_count'] ?? 0) + 1;
        $now = date('Y-m-d H:i:s');

        $this->CI->Kt_saas_model->update_invoice((int) $invoice['id'], [
            'last_reminder_at' => $now,
            'reminder_count'   => $count,
            'updated_at'       => $now,
        ]);

        $this->CI->Kt_saas_model->log_activity('invoice.dunning_attempted', 'warning', array_merge([
            'invoice_id'      => (int) $invoice['id'],
            'tenant_id'       => (int) $invoice['tenant_id'],
            'invoice_number'  => $invoice['invoice_number'],
            'reminder_count'  => $count,
        ], $context), (int) $invoice['tenant_id']);

        return true;
    }

    public function markInvoicePaid(array $invoice, array $paymentData = [])
    {
        if (empty($invoice['id'])) {
            return ['success' => false, 'message' => 'Invoice not found.'];
        }

        $now = date('Y-m-d H:i:s');
        $amount = isset($paymentData['amount']) ? (float) $paymentData['amount'] : (float) ($invoice['grand_total'] ?? 0);
        $currency = trim((string) ($paymentData['currency'] ?? $invoice['currency'] ?? 'USD'));
        $reference = trim((string) ($paymentData['payment_reference'] ?? ''));
        if ($reference === '') {
            $reference = 'MAN-' . date('YmdHis') . '-' . (int) $invoice['id'];
        }

        $invoiceAmount = (float) ($invoice['grand_total'] ?? 0);
        if (abs($amount - $invoiceAmount) > 0.01) {
            return ['success' => false, 'message' => 'Payment amount does not match invoice total.'];
        }

        if ($currency !== '' && strcasecmp($currency, (string) ($invoice['currency'] ?? 'USD')) !== 0) {
            return ['success' => false, 'message' => 'Payment currency does not match invoice currency.'];
        }

        $existingPayment = $this->CI->Kt_saas_model->get_payment_by_reference($reference);
        if ($existingPayment) {
            if ((int) ($existingPayment['invoice_id'] ?? 0) !== (int) $invoice['id']) {
                return ['success' => false, 'message' => 'Payment reference is already used by another invoice.'];
            }

            if (($invoice['status'] ?? '') !== 'paid') {
                $this->CI->Kt_saas_model->update_invoice((int) $invoice['id'], [
                    'status'     => 'paid',
                    'paid_at'    => $paymentData['paid_at'] ?? ($existingPayment['paid_at'] ?? $now),
                    'gateway'    => trim((string) ($paymentData['gateway'] ?? ($existingPayment['gateway'] ?? 'manual'))),
                    'updated_at' => $now,
                ]);
                $this->reactivateSubscriptionAfterPayment($invoice, $now);
            }

            $emailGuard = $this->reserveEmailEventGuard('payment_success', [
                'tenant_id' => (int) $invoice['tenant_id'],
                'resource_type' => 'invoice',
                'resource_id' => (int) $invoice['id'],
                'dedupe_key' => 'payment_success|' . (int) $invoice['id'] . '|' . $reference,
            ]);

            return [
                'success'      => true,
                'invoice_id'    => (int) $invoice['id'],
                'payment_id'    => (int) $existingPayment['id'],
                'already_paid'  => ($invoice['status'] ?? '') === 'paid',
                'recovered'     => ($invoice['status'] ?? '') !== 'paid',
                'email_event_guard' => $emailGuard ?? null,
            ];
        }

        if (($invoice['status'] ?? '') === 'paid') {
            $emailGuard = $this->reserveEmailEventGuard('payment_success', [
                'tenant_id' => (int) $invoice['tenant_id'],
                'resource_type' => 'invoice',
                'resource_id' => (int) $invoice['id'],
                'dedupe_key' => 'payment_success|' . (int) $invoice['id'] . '|' . $reference,
            ]);

            return $this->dispatchPaymentSuccessEmail($invoice, $paymentData, ['success' => true, 'invoice_id' => (int) $invoice['id'], 'payment_id' => null, 'already_paid' => true, 'email_event_guard' => $emailGuard]);
        }

        $paymentId = $this->CI->Kt_saas_model->create_payment([
            'tenant_id'            => (int) $invoice['tenant_id'],
            'invoice_id'           => (int) $invoice['id'],
            'payment_reference'    => $reference,
            'gateway'              => trim((string) ($paymentData['gateway'] ?? 'manual')),
            'status'               => trim((string) ($paymentData['status'] ?? 'paid')),
            'amount'               => $amount,
            'currency'             => $currency,
            'gateway_payload_json' => json_encode([
                'source'  => 'landlord_manual_collection',
                'context' => $paymentData,
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'paid_at'              => $paymentData['paid_at'] ?? $now,
        ]);

        $this->CI->Kt_saas_model->update_invoice((int) $invoice['id'], [
            'status'           => 'paid',
            'paid_at'          => $paymentData['paid_at'] ?? $now,
            'gateway'          => trim((string) ($paymentData['gateway'] ?? 'manual')),
            'last_reminder_at' => $invoice['last_reminder_at'] ?? null,
            'updated_at'       => $now,
        ]);

        $this->reactivateSubscriptionAfterPayment($invoice, $now);
        $emailGuard = $this->reserveEmailEventGuard('payment_success', [
            'tenant_id' => (int) $invoice['tenant_id'],
            'resource_type' => 'invoice',
            'resource_id' => (int) $invoice['id'],
            'dedupe_key' => 'payment_success|' . (int) $invoice['id'] . '|' . $reference,
        ]);

        $this->CI->Kt_saas_model->log_activity('invoice.paid', 'success', [
            'invoice_id'      => (int) $invoice['id'],
            'tenant_id'       => (int) $invoice['tenant_id'],
            'invoice_number'  => $invoice['invoice_number'],
            'payment_id'      => $paymentId,
            'amount'          => $amount,
            'currency'        => $currency,
            'gateway'         => trim((string) ($paymentData['gateway'] ?? 'manual')),
        ], (int) $invoice['tenant_id']);

        return $this->dispatchPaymentSuccessEmail($invoice, $paymentData, ['success' => true, 'invoice_id' => (int) $invoice['id'], 'payment_id' => $paymentId, 'already_paid' => false, 'email_event_guard' => $emailGuard]);
    }

    protected function dispatchPaymentSuccessEmail(array $invoice, array $paymentData, array $result)
    {
        if (empty($result['success']) || empty($invoice['id']) || empty($invoice['tenant_id'])) {
            return $result;
        }

        $tenant = $this->CI->Kt_saas_model->get_tenant((int) $invoice['tenant_id']);
        if (!$tenant) {
            return $result;
        }

        $plan = !empty($tenant['plan_id']) ? $this->CI->Kt_saas_model->get_plan((int) $tenant['plan_id']) : null;
        $workspaceUrl = function_exists('kt_saas_tenant_public_base_url') ? rtrim((string) kt_saas_tenant_public_base_url($tenant), '/') : '';
        $guard = isset($result['email_event_guard']) && is_array($result['email_event_guard']) ? $result['email_event_guard'] : null;
        $paymentUrl = '';
        if (!class_exists('PaymentCollectionService', false)) {
            require_once module_dir_path(KT_SAAS_MODULE, 'services/PaymentCollectionService.php');
        }
        if (class_exists('PaymentCollectionService', false)) {
            $paymentService = new PaymentCollectionService();
            $paymentUrl = (string) $paymentService->getCheckoutUrl($invoice, $tenant);
        }

        $context = [
            'tenant_id' => (int) $tenant['id'],
            'tenant' => $tenant,
            'invoice' => $invoice,
            'plan' => $plan ?: [],
            'owner_name' => (string) ($tenant['owner_name'] ?? ''),
            'owner_email' => (string) ($tenant['owner_email'] ?? ''),
            'tenant_name' => (string) ($tenant['company_name'] ?? ''),
            'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
            'workspace_url' => $workspaceUrl,
            'workspace_domain' => trim((string) ($tenant['custom_domain'] ?? $tenant['subdomain'] ?? '')),
            'payment_url' => $paymentUrl,
            'invoice_url' => $paymentUrl,
            'invoice_total' => (string) ($paymentData['amount'] ?? ($invoice['grand_total'] ?? '')),
            'currency' => (string) ($paymentData['currency'] ?? ($invoice['currency'] ?? '')),
            'related_type' => 'invoice',
            'related_id' => (string) $invoice['id'],
        ];
        if ($guard) {
            $context['email_event_guard'] = $guard;
            $context['dedupe_key'] = (string) ($guard['dedupe_key'] ?? '');
        }

        $result['email_dispatch']['payment_success'] = $this->CI->Kt_saas_model->send_email_event('payment_success', $context);
        return $result;
    }

    protected function reactivateSubscriptionAfterPayment(array $invoice, $now)
    {
        if (empty($invoice['subscription_id'])) {
            return;
        }

        $subscription = $this->CI->Kt_saas_model->get_subscription((int) $invoice['subscription_id']);
        if (!$subscription) {
            return;
        }

        $payload = json_decode((string) ($invoice['payload_json'] ?? ''), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $reason = (string) ($payload['reason'] ?? ($payload['context']['reason'] ?? 'subscription_renewal'));
        if ($reason === 'plan_change_request' && !empty($payload['target_plan_id'])) {
            $targetPlan = $this->CI->Kt_saas_model->get_plan((int) $payload['target_plan_id']);
            if ($targetPlan) {
                $this->applyPaidPlanChange($invoice, $subscription, $targetPlan, $payload, $now);
                return;
            }
        }

        if ($reason === 'overage_charge') {
            $this->CI->Kt_saas_model->log_activity('invoice.overage_paid', 'success', [
                'tenant_id'       => (int) $invoice['tenant_id'],
                'subscription_id' => (int) $subscription['id'],
                'invoice_id'      => (int) $invoice['id'],
                'paid_at'         => $now,
                'overage_period'  => $payload['overage_period'] ?? null,
            ], (int) $invoice['tenant_id']);

            return;
        }

        if (!$this->isRenewalInvoiceReason($reason)) {
            $periodStart = !empty($subscription['current_period_start_at'])
                ? new DateTimeImmutable($subscription['current_period_start_at'])
                : new DateTimeImmutable($invoice['paid_at'] ?? $now);
            $periodEnd = !empty($subscription['current_period_end_at'])
                ? new DateTimeImmutable($subscription['current_period_end_at'])
                : $this->nextInvoiceDate($subscription['billing_cycle'] ?: 'monthly', $periodStart);

            $this->CI->Kt_saas_model->update_subscription((int) $subscription['id'], [
                'status'                  => 'active',
                'current_period_start_at' => $periodStart->format('Y-m-d H:i:s'),
                'current_period_end_at'   => $periodEnd->format('Y-m-d H:i:s'),
                'next_billing_at'         => $periodEnd->format('Y-m-d H:i:s'),
                'grace_ends_at'           => null,
                'renewal_attempts'        => 0,
                'updated_at'              => $now,
            ]);

            $this->CI->Kt_saas_model->update_tenant((int) $invoice['tenant_id'], [
                'status'       => 'active',
                'suspended_at' => null,
                'updated_at'   => $now,
            ]);

            $this->CI->Kt_saas_model->log_activity('subscription.activated', 'success', [
                'tenant_id'       => (int) $invoice['tenant_id'],
                'subscription_id' => (int) $subscription['id'],
                'invoice_id'      => (int) $invoice['id'],
                'activated_at'    => $now,
                'period_start_at' => $periodStart->format('Y-m-d H:i:s'),
                'period_end_at'   => $periodEnd->format('Y-m-d H:i:s'),
                'reason'          => $reason,
            ], (int) $invoice['tenant_id']);

            return;
        }

        $periodStart = !empty($subscription['current_period_end_at'])
            ? new DateTimeImmutable($subscription['current_period_end_at'])
            : (!empty($subscription['next_billing_at'])
                ? new DateTimeImmutable($subscription['next_billing_at'])
                : new DateTimeImmutable($now));
        $periodEnd = $this->nextInvoiceDate($subscription['billing_cycle'] ?: 'monthly', $periodStart);

        $this->CI->Kt_saas_model->update_subscription((int) $subscription['id'], [
            'status'                  => 'active',
            'current_period_start_at' => $periodStart->format('Y-m-d H:i:s'),
            'current_period_end_at'   => $periodEnd->format('Y-m-d H:i:s'),
            'next_billing_at'         => $periodEnd->format('Y-m-d H:i:s'),
            'grace_ends_at'           => null,
            'renewal_attempts'        => 0,
            'updated_at'              => $now,
        ]);

        $this->CI->Kt_saas_model->update_tenant((int) $invoice['tenant_id'], [
            'status'       => 'active',
            'suspended_at' => null,
            'updated_at'   => $now,
        ]);

        $this->CI->Kt_saas_model->log_activity('subscription.reactivated', 'success', [
            'tenant_id'       => (int) $invoice['tenant_id'],
            'subscription_id' => (int) $subscription['id'],
            'invoice_id'      => (int) $invoice['id'],
            'reactivated_at'  => $now,
            'next_billing_at' => $periodEnd->format('Y-m-d H:i:s'),
        ], (int) $invoice['tenant_id']);

        $tenant = $this->CI->Kt_saas_model->get_tenant((int) $invoice['tenant_id']);
        if ($tenant) {
            $plan = $this->CI->Kt_saas_model->get_plan((int) ($subscription['plan_id'] ?? $tenant['plan_id'] ?? 0));
            $renewedContext = [
                'tenant_id' => (int) $invoice['tenant_id'],
                'tenant' => $tenant,
                'subscription' => $this->CI->Kt_saas_model->get_current_subscription((int) $invoice['tenant_id']) ?: $subscription,
                'invoice' => $invoice,
                'plan' => $plan ?: [],
                'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['company_name'] ?? ''),
                'owner_email' => (string) ($tenant['owner_email'] ?? ''),
                'tenant_name' => (string) ($tenant['company_name'] ?? ''),
                'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
                'trial_end_date' => (string) (($subscription['trial_ends_at'] ?? '') ?: ''),
                'subscription_status' => 'active',
                'workspace_url' => function_exists('kt_saas_tenant_public_base_url') ? rtrim((string) kt_saas_tenant_public_base_url($tenant), '/') : '',
                'workspace_domain' => trim((string) ($tenant['custom_domain'] ?? $tenant['subdomain'] ?? '')),
                'plan_name' => (string) ($plan['plan_name'] ?? ''),
                'invoice_total' => (string) ($invoice['grand_total'] ?? ''),
                'currency' => (string) ($invoice['currency'] ?? $tenant['currency'] ?? ''),
                'related_type' => 'subscription',
                'related_id' => (string) ($subscription['id'] ?? $invoice['subscription_id'] ?? $invoice['id']),
                'dedupe_key' => 'tenant_subscription_renewed|' . (int) $invoice['tenant_id'] . '|' . (int) ($subscription['id'] ?? $invoice['subscription_id'] ?? 0) . '|' . (string) $periodEnd->format('Y-m-d H:i:s'),
            ];
            $this->CI->Kt_saas_model->send_email_event('tenant_subscription_renewed', $renewedContext);
        }
    }

    protected function applyPaidPlanChange(array $invoice, array $subscription, array $targetPlan, array $payload, $now)
    {
        $periodStart = new DateTimeImmutable($now);
        $periodEnd = $this->nextInvoiceDate($targetPlan['billing_cycle'] ?: 'monthly', $periodStart);
        $metadata = $this->withoutScheduledPlanChange($subscription['metadata_json'] ?? null);

        $this->CI->Kt_saas_model->update_subscription((int) $subscription['id'], [
            'plan_id'                  => (int) $targetPlan['id'],
            'billing_cycle'            => $targetPlan['billing_cycle'] ?: 'monthly',
            'status'                   => 'active',
            'current_period_start_at'  => $periodStart->format('Y-m-d H:i:s'),
            'current_period_end_at'    => $periodEnd->format('Y-m-d H:i:s'),
            'next_billing_at'          => $periodEnd->format('Y-m-d H:i:s'),
            'grace_ends_at'            => null,
            'renewal_attempts'         => 0,
            'metadata_json'            => $metadata,
            'updated_at'               => $now,
        ]);

        $this->CI->Kt_saas_model->update_tenant((int) $invoice['tenant_id'], [
            'plan_id'      => (int) $targetPlan['id'],
            'status'       => 'active',
            'suspended_at' => null,
            'updated_at'   => $now,
        ]);

        $this->CI->Kt_saas_model->log_activity('subscription.plan_changed', 'success', [
            'tenant_id'         => (int) $invoice['tenant_id'],
            'subscription_id'   => (int) $subscription['id'],
            'invoice_id'        => (int) $invoice['id'],
            'previous_plan_id'  => (int) ($subscription['plan_id'] ?? 0),
            'target_plan_id'    => (int) $targetPlan['id'],
            'change_type'       => $payload['change_type'] ?? 'upgrade',
            'effective_from'    => $periodStart->format('Y-m-d H:i:s'),
            'next_billing_at'   => $periodEnd->format('Y-m-d H:i:s'),
        ], (int) $invoice['tenant_id']);

        $tenant = $this->CI->Kt_saas_model->get_tenant((int) $invoice['tenant_id']);
        if ($tenant) {
            $planContext = [
                'tenant_id' => (int) $invoice['tenant_id'],
                'tenant' => $tenant,
                'subscription' => $this->CI->Kt_saas_model->get_current_subscription((int) $invoice['tenant_id']) ?: $subscription,
                'invoice' => $invoice,
                'plan' => $targetPlan,
                'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['company_name'] ?? ''),
                'owner_email' => (string) ($tenant['owner_email'] ?? ''),
                'tenant_name' => (string) ($tenant['company_name'] ?? ''),
                'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
                'trial_end_date' => (string) (($targetPlan['trial_days'] ?? 0) > 0 ? ($subscription['trial_ends_at'] ?? '') : ''),
                'subscription_status' => 'active',
                'workspace_url' => function_exists('kt_saas_tenant_public_base_url') ? rtrim((string) kt_saas_tenant_public_base_url($tenant), '/') : '',
                'workspace_domain' => trim((string) ($tenant['custom_domain'] ?? $tenant['subdomain'] ?? '')),
                'plan_name' => (string) ($targetPlan['plan_name'] ?? ''),
                'invoice_total' => (string) ($invoice['grand_total'] ?? ''),
                'currency' => (string) ($invoice['currency'] ?? $tenant['currency'] ?? ''),
                'related_type' => 'subscription',
                'related_id' => (string) $subscription['id'],
                'dedupe_key' => 'tenant_plan_changed|' . (int) $invoice['tenant_id'] . '|' . (int) $subscription['id'] . '|' . (int) $targetPlan['id'] . '|' . $periodEnd->format('Y-m-d H:i:s'),
            ];
            $this->CI->Kt_saas_model->send_email_event('tenant_plan_changed', $planContext);
        }
    }

    protected function isRenewalInvoiceReason($reason)
    {
        return in_array(trim((string) $reason), ['subscription_renewal', 'renewal', 'renewal_invoice'], true);
    }

    protected function findOpenInvoice($tenantId, $subscriptionId, $reason = 'subscription_renewal', $targetPlanId = null)
    {
        return $this->CI->Kt_saas_model->find_open_tenant_invoice_by_reason((int) $tenantId, (int) $subscriptionId, (string) $reason, $targetPlanId !== null ? (int) $targetPlanId : null);
    }

    protected function schedulePlanChange(array $tenant, array $subscription, array $targetPlan, array $context, $reason, $changeType)
    {
        $metadata = $this->decodeMetadata($subscription['metadata_json'] ?? null);
        $scheduledAt = $subscription['next_billing_at'] ?? $subscription['current_period_end_at'] ?? date('Y-m-d H:i:s');
        $metadata['scheduled_plan_change'] = [
            'reason'               => (string) $reason,
            'change_type'          => (string) $changeType,
            'current_plan_id'      => (int) ($subscription['plan_id'] ?? 0),
            'target_plan_id'       => (int) ($targetPlan['id'] ?? 0),
            'target_plan_code'     => (string) ($targetPlan['plan_code'] ?? ''),
            'target_plan_name'     => (string) ($targetPlan['plan_name'] ?? ''),
            'target_billing_cycle' => (string) ($targetPlan['billing_cycle'] ?? 'monthly'),
            'scheduled_at'         => $scheduledAt,
            'created_at'           => date('Y-m-d H:i:s'),
            'context'              => $context,
        ];

        $this->CI->Kt_saas_model->update_subscription((int) $subscription['id'], [
            'metadata_json' => $this->encodeMetadata($metadata),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        $this->CI->Kt_saas_model->log_activity('subscription.plan_change_scheduled', 'info', [
            'tenant_id'         => (int) $tenant['id'],
            'subscription_id'   => (int) $subscription['id'],
            'current_plan_id'   => (int) ($subscription['plan_id'] ?? 0),
            'target_plan_id'    => (int) ($targetPlan['id'] ?? 0),
            'change_type'       => (string) $changeType,
            'scheduled_at'      => $scheduledAt,
        ], (int) $tenant['id']);

        return [
            'success'        => true,
            'created'        => false,
            'scheduled'      => true,
            'change_type'    => (string) $changeType,
            'scheduled_at'   => $scheduledAt,
            'invoice_id'     => null,
        ];
    }

    protected function decodeMetadata($metadataJson)
    {
        $metadata = json_decode((string) $metadataJson, true);
        return is_array($metadata) ? $metadata : [];
    }

    protected function encodeMetadata(array $metadata)
    {
        if (empty($metadata)) {
            return null;
        }

        return json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    protected function withoutScheduledPlanChange($metadataJson)
    {
        $metadata = $this->decodeMetadata($metadataJson);
        unset($metadata['scheduled_plan_change']);
        return $this->encodeMetadata($metadata);
    }

    protected function countInvoicesForTenant($tenantId)
    {
        return (int) $this->landlordDb()
            ->where('tenant_id', (int) $tenantId)
            ->count_all_results(db_prefix() . 'kt_saas_invoices');
    }

    protected function landlordDb()
    {
        $landlordDb = $this->CI->config->item('kt_saas_landlord_db');

        return $landlordDb ?: $this->CI->db;
    }

    protected function reserveEmailEventGuard($eventKey, array $context)
    {
        if (function_exists('kt_saas_reserve_email_event')) {
            return kt_saas_reserve_email_event($eventKey, $context);
        }

        require_once module_dir_path(KT_SAAS_MODULE, 'helpers/kt_saas_helper.php');
        if (function_exists('kt_saas_reserve_email_event')) {
            return kt_saas_reserve_email_event($eventKey, $context);
        }

        return ['allowed' => false, 'message' => 'Email guard helper unavailable.'];
    }
}
