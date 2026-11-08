<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/NotificationBridgeService.php');

class CrmInvoiceReadbackService
{
    public function getInvoiceReadbackPayload(int $crmInvoiceId, ?int $tenantId = null): ?array
    {
        if ($crmInvoiceId <= 0) {
            return null;
        }

        $invoice = $this->invoicesModel()->get($crmInvoiceId);
        if (!$invoice) {
            return null;
        }

        $total = (float) ($invoice->total ?? 0);
        $paid = $this->resolvePaidAmount($invoice);
        $balance = isset($invoice->total_left_to_pay)
            ? (float) $invoice->total_left_to_pay
            : max($total - $paid, 0);

        $payload = [
            'crm_invoice_id' => (int) ($invoice->id ?? $crmInvoiceId),
            'invoice_number' => $this->resolveInvoiceNumber($invoice),
            'status' => $this->normalizeStatus((int) ($invoice->status ?? 0)),
            'total' => $total,
            'paid' => $paid,
            'balance' => $balance,
            'updated_at' => $this->resolveUpdatedAt($invoice),
        ];

        if ($tenantId !== null && $tenantId > 0) {
            $this->publishNotification($tenantId, $payload);
        }

        return $payload;
    }

    protected function resolvePaidAmount(object $invoice): float
    {
        $payments = is_array($invoice->payments ?? null) ? $invoice->payments : [];

        return (float) array_reduce($payments, function ($carry, $payment) {
            return $carry + (float) ($payment['amount'] ?? $payment->amount ?? 0);
        }, 0.0);
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

    protected function resolveUpdatedAt(object $invoice): string
    {
        $candidates = [
            (string) ($invoice->datecreated ?? ''),
            (string) ($invoice->datesend ?? ''),
            (string) ($invoice->last_overdue_reminder ?? ''),
            (string) ($invoice->last_due_reminder ?? ''),
        ];

        foreach ((array) ($invoice->payments ?? []) as $payment) {
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

    protected function normalizeStatus(int $status): string
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
            $service->publishInvoiceStatusChanged($tenantId, $payload);
        } catch (Throwable $e) {
            log_message('warning', 'Invoice status notification publish failed: ' . $e->getMessage());
        }
    }
}
