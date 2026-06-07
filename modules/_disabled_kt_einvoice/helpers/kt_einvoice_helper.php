<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * kt_einvoice_helper.php
 * Helper functions cho module KT eInvoice
 */

if (!function_exists('kt_einvoice_status_badge')) {
    /**
     * Render HTML badge cho trạng thái hóa đơn
     */
    function kt_einvoice_status_badge(string $status): string
    {
        $map = [
            KT_EINVOICE_STATUS_PENDING_CREATE => ['warning', 'fa-clock-o',        'kt_einvoice_status_pending_create'],
            KT_EINVOICE_STATUS_DRAFT          => ['default', 'fa-file-o',         'kt_einvoice_status_draft'],
            KT_EINVOICE_STATUS_PENDING_ISSUE  => ['info',    'fa-spinner fa-spin','kt_einvoice_status_pending_issue'],
            KT_EINVOICE_STATUS_ISSUED         => ['success', 'fa-check-circle',   'kt_einvoice_status_issued'],
            KT_EINVOICE_STATUS_FAILED_CREATE  => ['danger',  'fa-times-circle',   'kt_einvoice_status_failed_create'],
            KT_EINVOICE_STATUS_FAILED_ISSUE   => ['danger',  'fa-exclamation-circle', 'kt_einvoice_status_failed_issue'],
            KT_EINVOICE_STATUS_DELETED        => ['default', 'fa-trash',          'kt_einvoice_status_deleted'],
            KT_EINVOICE_STATUS_PENDING_CANCEL => ['warning', 'fa-spinner fa-spin','kt_einvoice_status_pending_cancel'],
            KT_EINVOICE_STATUS_CANCELLED      => ['muted',   'fa-ban',            'kt_einvoice_status_cancelled'],
            KT_EINVOICE_STATUS_ADJUSTING      => ['info',    'fa-edit',           'kt_einvoice_status_adjusting'],
            KT_EINVOICE_STATUS_ADJUSTED       => ['primary', 'fa-check',          'kt_einvoice_status_adjusted'],
        ];

        $cfg   = $map[$status] ?? ['default', 'fa-question', $status];
        $label = function_exists('_l') ? _l($cfg[2]) : $cfg[2];

        return '<span class="label label-' . $cfg[0] . '">'
            . '<i class="fa ' . $cfg[1] . ' tw-mr-1"></i>'
            . htmlspecialchars($label)
            . '</span>';
    }
}

if (!function_exists('kt_einvoice_format_currency')) {
    /**
     * Format tiền tệ VND
     */
    function kt_einvoice_format_currency(float $amount, string $currency = 'VND'): string
    {
        return number_format($amount, 0, ',', '.') . ' ' . $currency;
    }
}

if (!function_exists('kt_einvoice_idempotency_key')) {
    /**
     * Build idempotency key — khớp với KtEinvoiceService::buildIdempotencyKey()
     */
    function kt_einvoice_idempotency_key(int $tenantId, int $perfexInvoiceId, string $environment): string
    {
        return hash('sha256', "kt_einvoice:{$tenantId}:{$perfexInvoiceId}:{$environment}");
    }
}

if (!function_exists('kt_einvoice_is_terminal_status')) {
    /**
     * Kiểm tra trạng thái là terminal (không thể retry)
     */
    function kt_einvoice_is_terminal_status(string $status): bool
    {
        return in_array($status, [
            KT_EINVOICE_STATUS_ISSUED,
            KT_EINVOICE_STATUS_CANCELLED,
            KT_EINVOICE_STATUS_DELETED,
            KT_EINVOICE_STATUS_ADJUSTED,
        ]);
    }
}

if (!function_exists('kt_einvoice_can_issue')) {
    /**
     * Kiểm tra record có thể phát hành không
     */
    function kt_einvoice_can_issue(?array $record): bool
    {
        if (!$record) return false;
        return $record['status'] === KT_EINVOICE_STATUS_DRAFT;
    }
}

if (!function_exists('kt_einvoice_can_cancel')) {
    /**
     * Kiểm tra record có thể hủy không
     */
    function kt_einvoice_can_cancel(?array $record): bool
    {
        if (!$record) return false;
        return $record['status'] === KT_EINVOICE_STATUS_ISSUED;
    }
}

if (!function_exists('kt_einvoice_can_delete')) {
    /**
     * Kiểm tra record có thể xóa không
     */
    function kt_einvoice_can_delete(?array $record): bool
    {
        if (!$record) return false;
        return $record['status'] === KT_EINVOICE_STATUS_DRAFT;
    }
}

if (!function_exists('kt_einvoice_validate_tax_code')) {
    /**
     * Validate MST Việt Nam
     */
    function kt_einvoice_validate_tax_code(string $code): bool
    {
        return (bool) preg_match('/^\d{10}(-\d{3})?$/', trim($code));
    }
}

if (!function_exists('kt_einvoice_retry_delay')) {
    /**
     * Tính thời gian retry tiếp theo (exponential backoff)
     * @param int $attempts Số lần đã thử
     * @return int Seconds
     */
    function kt_einvoice_retry_delay(int $attempts): int
    {
        return KT_EINVOICE_RETRY_BASE_DELAY * (2 ** max(0, $attempts - 1));
    }
}

if (!function_exists('kt_einvoice_next_retry_at')) {
    /**
     * Tính thời điểm retry tiếp theo (datetime string)
     */
    function kt_einvoice_next_retry_at(int $attempts): string
    {
        return date('Y-m-d H:i:s', time() + kt_einvoice_retry_delay($attempts));
    }
}
