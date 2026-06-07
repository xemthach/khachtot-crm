<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('kt_sepay_is_module_request')) {
    function kt_sepay_is_module_request()
    {
        $uri = uri_string();

        return strpos($uri, 'admin/kt_sepay') === 0 || strpos($uri, 'kt_sepay/') === 0;
    }
}

if (!function_exists('kt_sepay_is_landlord_context')) {
    function kt_sepay_is_landlord_context()
    {
        if (function_exists('kt_saas_is_landlord_context')) {
            return kt_saas_is_landlord_context();
        }

        return true;
    }
}

if (!function_exists('kt_sepay_staff_can')) {
    function kt_sepay_staff_can($capability)
    {
        if (!kt_sepay_is_landlord_context()) {
            return false;
        }

        return staff_can($capability, KT_SEPAY_MODULE) || is_admin();
    }
}

if (!function_exists('kt_sepay_landlord_only')) {
    function kt_sepay_landlord_only($message = 'This area is available only in landlord context.')
    {
        if (!kt_sepay_is_landlord_context()) {
            show_error($message, 403, 'Forbidden');
            exit;
        }
    }
}

if (!function_exists('kt_sepay_encrypt_value')) {
    function kt_sepay_encrypt_value($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $CI = &get_instance();
        $CI->load->library('encryption');

        return $CI->encryption->encrypt($value);
    }
}

if (!function_exists('kt_sepay_decrypt_value')) {
    function kt_sepay_decrypt_value($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $CI = &get_instance();
        $CI->load->library('encryption');
        $decrypted = $CI->encryption->decrypt($value);

        return is_string($decrypted) ? trim($decrypted) : '';
    }
}

if (!function_exists('kt_sepay_qr_url')) {
    function kt_sepay_qr_url($accountNumber, $bankCode, $amount, $description, $template = 'compact', $download = false)
    {
        $query = [
            'acc'      => trim((string) $accountNumber),
            'bank'     => trim((string) $bankCode),
            'amount'   => max((int) $amount, 0) ?: null,
            'des'      => trim((string) $description),
            'template' => trim((string) $template) ?: 'compact',
        ];

        if ($download) {
            $query['download'] = 'true';
        }

        $query = array_filter($query, static function ($value) {
            return $value !== null && $value !== '';
        });

        return 'https://qr.sepay.vn/img?' . http_build_query($query);
    }
}

if (!function_exists('kt_sepay_api_base_url')) {
    function kt_sepay_api_base_url($environment)
    {
        return strtolower(trim((string) $environment)) === 'production'
            ? 'https://userapi.sepay.vn/v2'
            : 'https://userapi-sandbox.sepay.vn/v2';
    }
}

if (!function_exists('kt_sepay_now')) {
    function kt_sepay_now()
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('kt_sepay_json_encode')) {
    function kt_sepay_json_encode($value)
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}

if (!function_exists('kt_sepay_json_decode')) {
    function kt_sepay_json_decode($value, $default = [])
    {
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : $default;
    }
}

if (!function_exists('kt_sepay_reference_prefixes')) {
    function kt_sepay_reference_prefixes()
    {
        return [
            'perfex_invoice'       => 'KTINV',
            'kt_saas_subscription' => 'KTSAAS',
            'manual'               => 'KTPAY',
        ];
    }
}

if (!function_exists('kt_sepay_status_badge_class')) {
    function kt_sepay_status_badge_class($status)
    {
        $map = [
            'pending'   => 'warning',
            'paid'      => 'success',
            'partial'   => 'info',
            'failed'    => 'danger',
            'expired'   => 'default',
            'cancelled' => 'default',
            'received'  => 'info',
            'matched'   => 'info',
            'processed' => 'success',
            'duplicate' => 'default',
            'unmatched' => 'warning',
            'error'     => 'danger',
        ];

        return $map[(string) $status] ?? 'default';
    }
}

if (!function_exists('kt_sepay_status_label')) {
    function kt_sepay_status_label($status)
    {
        $map = [
            'pending'   => _l('kt_sepay_pending'),
            'paid'      => _l('kt_sepay_paid'),
            'partial'   => 'Thanh toán một phần',
            'failed'    => _l('kt_sepay_failed'),
            'expired'   => 'Hết hạn',
            'cancelled' => 'Đã hủy',
            'received'  => 'Đã nhận',
            'matched'   => _l('kt_sepay_matched'),
            'processed' => _l('kt_sepay_processed'),
            'duplicate' => _l('kt_sepay_duplicate'),
            'unmatched' => _l('kt_sepay_unmatched'),
            'error'     => 'Lỗi',
            'success'   => 'Thành công',
            'warning'   => 'Cảnh báo',
            'info'      => 'Thông tin',
        ];

        $status = (string) $status;

        return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }
}

if (!function_exists('kt_sepay_health_status_badge_class')) {
    function kt_sepay_health_status_badge_class($status)
    {
        $map = [
            'success' => 'success',
            'warning' => 'warning',
            'error'   => 'danger',
            'info'    => 'info',
        ];

        return $map[(string) $status] ?? 'default';
    }
}

if (!function_exists('kt_sepay_webhook_url')) {
    function kt_sepay_webhook_url($tenant = null)
    {
        if (is_array($tenant) && !empty($tenant['tenant_code'])) {
            return rtrim(APP_BASE_URL, '/') . '/kt_sepay/webhook/tenant/' . rawurlencode((string) $tenant['tenant_code']);
        }

        return site_url('kt_sepay/webhook');
    }
}

if (!function_exists('kt_sepay_is_local_url')) {
    function kt_sepay_is_local_url($url)
    {
        $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));
        if ($host === '') {
            return true;
        }

        return in_array($host, ['localhost', '127.0.0.1'], true)
            || substr($host, -5) === '.test'
            || substr($host, -6) === '.local';
    }
}
