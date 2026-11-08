<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/NotificationBridgeService.php');

class CrmPaymentReadbackService
{
    public function getPaymentReadbackPayload(int $crmInvoiceId, ?int $tenantId = null): ?array
    {
        if ($crmInvoiceId <= 0) {
            return null;
        }

        $invoice = $this->invoicesModel()->get($crmInvoiceId);
        if (! $invoice) {
            return null;
        }

        $payments = $this->resolvePayments($invoice);
        $paid = $this->resolvePaidAmount($payments);
        if ($this->isPaymentMissingForConfirmedInvoice((int) ($invoice->status ?? 0), $paid, $payments)) {
            return null;
        }

        $total = (float) ($invoice->total ?? 0);
        $paymentStatus = $this->normalizePaymentStatus((int) ($invoice->status ?? 0), $paid, $total);

        $payload = [
            'crm_invoice_id' => (int) ($invoice->id ?? $crmInvoiceId),
            'invoice_number' => $this->resolveInvoiceNumber($invoice),
            'invoice_status' => $this->normalizeInvoiceStatus((int) ($invoice->status ?? 0)),
            'payment_status' => $paymentStatus,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'balance_amount' => max($total - $paid, 0),
            'last_payment_date' => $this->resolveLastPaymentDate($payments),
            'updated_at' => $this->resolveUpdatedAt($invoice, $payments),
        ];

        if ($tenantId !== null && $tenantId > 0) {
            $this->publishNotification($tenantId, $payload);
        }

        return $payload;
    }

    public function hasInvoice(int $crmInvoiceId): bool
    {
        if ($crmInvoiceId <= 0) {
            return false;
        }

        return (bool) $this->invoicesModel()->get($crmInvoiceId);
    }

    protected function resolvePayments(object $invoice): array
    {
        return is_array($invoice->payments ?? null) ? $invoice->payments : [];
    }

    protected function resolvePaidAmount(array $payments): float
    {
        return (float) array_reduce($payments, function ($carry, $payment) {
            return $carry + (float) ($payment['amount'] ?? $payment->amount ?? 0);
        }, 0.0);
    }

    protected function isPaymentMissingForConfirmedInvoice(int $status, float $paid, array $payments): bool
    {
        if (! in_array($status, [Invoices_model::STATUS_PAID, Invoices_model::STATUS_PARTIALLY], true)) {
            return false;
        }

        return $paid <= 0 && count($payments) === 0;
    }

    protected function normalizePaymentStatus(int $status, float $paid, float $total): string
    {
        if ($status === Invoices_model::STATUS_CANCELLED) {
            return 'cancelled';
        }

        if ($paid > $total) {
            return 'overpaid';
        }

        if ($paid >= $total && $paid > 0) {
            return 'paid';
        }

        if ($paid > 0) {
            return 'partial';
        }

        return 'unpaid';
    }

    protected function normalizeInvoiceStatus(int $status): string
    {
        return match ($status) {
            Invoices_model::STATUS_DRAFT => 'draft',
            Invoices_model::STATUS_UNPAID => 'unpaid',
            Invoices_model::STATUS_PARTIALLY => 'partially',
            Invoices_model::STATUS_PAID => 'paid',
            Invoices_model::STATUS_OVERDUE => 'overdue',
            Invoices_model::STATUS_CANCELLED => 'cancelled',
            default => 'unpaid',
        };
    }

    protected function resolveInvoiceNumber(object $invoice): string
    {
        $formatted = trim((string) ($invoice->formatted_number ?? ''));
        if ($formatted !== '') {
            return $formatted;
        }

        $prefix = trim((string) ($invoice->prefix ?? ''));
        $number = trim((string) ($invoice->number ?? ''));
        if ($prefix !== '' || $number !== '') {
            return trim($prefix . $number);
        }

        return 'INV-' . (int) ($invoice->id ?? 0);
    }

    protected function resolveLastPaymentDate(array $payments): ?string
    {
        $latest = 0;

        foreach ($payments as $payment) {
            $candidates = [
                (string) ($payment['daterecorded'] ?? $payment->daterecorded ?? ''),
                (string) ($payment['date'] ?? $payment->date ?? ''),
            ];

            foreach ($candidates as $candidate) {
                $candidate = trim($candidate);
                if ($candidate === '') {
                    continue;
                }

                $timestamp = strtotime($candidate);
                if ($timestamp !== false && $timestamp > $latest) {
                    $latest = $timestamp;
                }
            }
        }

        return $latest > 0 ? gmdate('c', $latest) : null;
    }

    protected function resolveUpdatedAt(object $invoice, array $payments): string
    {
        $candidates = [
            (string) ($invoice->datecreated ?? ''),
            (string) ($invoice->datesend ?? ''),
            (string) ($invoice->last_overdue_reminder ?? ''),
            (string) ($invoice->last_due_reminder ?? ''),
        ];

        foreach ($payments as $payment) {
            $candidates[] = (string) ($payment['daterecorded'] ?? $payment->daterecorded ?? '');
            $candidates[] = (string) ($payment['date'] ?? $payment->date ?? '');
        }

        $latest = 0;
        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            $timestamp = strtotime($candidate);
            if ($timestamp !== false && $timestamp > $latest) {
                $latest = $timestamp;
            }
        }

        return $latest > 0 ? gmdate('c', $latest) : gmdate('c');
    }

    protected function invoicesModel()
    {
        $CI = get_instance();
        if (!class_exists('Invoices_model', false)) {
            $CI->load->model('invoices_model');
        }

        return $CI->invoices_model;
    }

    private function publishNotification(int $tenantId, array $payload): void
    {
        try {
            $service = new NotificationBridgeService();
            $service->publishPaymentStatusChanged($tenantId, $payload);
        } catch (Throwable $e) {
            log_message('warning', 'Payment status notification publish failed: ' . $e->getMessage());
        }
    }
}
