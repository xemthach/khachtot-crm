<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('kt_integration_hub_staff_can')) {
    function kt_integration_hub_staff_can($capability)
    {
        return staff_can($capability, KT_INTEGRATION_HUB_MODULE) || is_admin();
    }
}

if (!function_exists('kt_integration_hub_is_tenant_runtime')) {
    function kt_integration_hub_is_tenant_runtime()
    {
        return function_exists('kt_saas_is_tenant_runtime') && kt_saas_is_tenant_runtime();
    }
}

if (!function_exists('kt_integration_hub_current_tenant')) {
    function kt_integration_hub_current_tenant()
    {
        return function_exists('kt_saas_current_tenant') ? kt_saas_current_tenant() : null;
    }
}

if (!function_exists('kt_integration_hub_encrypt_value')) {
    function kt_integration_hub_encrypt_value($value)
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

if (!function_exists('kt_integration_hub_decrypt_value')) {
    function kt_integration_hub_decrypt_value($value)
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

if (!function_exists('kt_integration_hub_json_encode')) {
    function kt_integration_hub_json_encode($value)
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}

if (!function_exists('kt_integration_hub_json_decode')) {
    function kt_integration_hub_json_decode($value, $default = [])
    {
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : $default;
    }
}

if (!function_exists('kt_integration_hub_now')) {
    function kt_integration_hub_now()
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('kt_integration_hub_redact_secrets')) {
    function kt_integration_hub_redact_secrets($value)
    {
        $secretKeys = [
            'access_token',
            'refresh_token',
            'client_secret',
            'app_secret',
            'oa_secret_key',
            'webhook_secret',
            'authorization',
            'x-kt-signature',
            'x_kt_signature',
            'x-signature',
            'x_signature',
            'x-zevent-signature',
            'x_zevent_signature',
            'signature',
            'password',
            'api_key',
            'token',
        ];

        if (is_array($value)) {
            $redacted = [];
            foreach ($value as $key => $item) {
                $normalized = strtolower(str_replace([' ', '-'], '_', (string) $key));
                $redacted[$key] = in_array($normalized, $secretKeys, true)
                    ? '[redacted]'
                    : kt_integration_hub_redact_secrets($item);
            }

            return $redacted;
        }

        return $value;
    }
}

if (!function_exists('kt_integration_hub_mask_secret')) {
    function kt_integration_hub_mask_secret($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $length = strlen($value);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 4) . str_repeat('*', max($length - 8, 4)) . substr($value, -4);
    }
}

if (!function_exists('kt_integration_hub_status_badge_class')) {
    function kt_integration_hub_status_badge_class($status)
    {
        $map = [
            'connected' => 'success',
            'active' => 'success',
            'pending' => 'warning',
            'queued' => 'warning',
            'retry' => 'warning',
            'processing' => 'info',
            'done' => 'success',
            'ready' => 'success',
            'beta' => 'info',
            'planned' => 'warning',
            'disabled' => 'default',
            'failed' => 'danger',
            'disconnected' => 'default',
            'auth_failed' => 'danger',
        ];

        return $map[(string) $status] ?? 'default';
    }
}

if (!function_exists('kt_integration_hub_webhook_url')) {
    function kt_integration_hub_webhook_url(array $connection)
    {
        return kt_integration_hub_landlord_base_url() . 'kt_integration_hub/webhook/' . rawurlencode((string) ($connection['provider_code'] ?? 'custom_webhook')) . '/' . rawurlencode((string) ($connection['public_key'] ?? ''));
    }
}

if (!function_exists('kt_integration_hub_oauth_callback_url')) {
    function kt_integration_hub_oauth_callback_url(array $connection, $providerCode = null)
    {
        $providerCode = $providerCode ?: (string) ($connection['provider_code'] ?? 'zalo_oa');

        return kt_integration_hub_landlord_base_url() . 'kt_integration_hub/oauth/' . rawurlencode((string) $providerCode) . '/callback/' . rawurlencode((string) ($connection['public_key'] ?? ''));
    }
}

if (!function_exists('kt_integration_hub_landlord_base_url')) {
    function kt_integration_hub_landlord_base_url()
    {
        $baseUrl = defined('APP_BASE_URL') ? APP_BASE_URL : config_item('base_url');
        $baseUrl = trim((string) $baseUrl);
        if ($baseUrl === '') {
            $baseUrl = site_url();
        }

        return rtrim($baseUrl, '/') . '/';
    }
}

if (!function_exists('kt_integration_hub_test_curl')) {
    function kt_integration_hub_test_curl(array $connection, $secretPlaceholder = 'WEBHOOK_SECRET')
    {
        $url = kt_integration_hub_webhook_url($connection);
        $payload = '{"event_id":"test-lead-001","event_type":"lead.created","lead":{"name":"Nguyen Van Test","phone":"0909000001","email":"test.integration@example.com","company":"Cong ty Test Integration","message":"Lead tu custom webhook"}}';

        return "RAW='" . $payload . "'\n"
            . "TS=$(date +%s)\n"
            . "SIG=$(php -r 'echo hash_hmac(\"sha256\", $argv[1] . \".\" . $argv[2], $argv[3]);' \"\$TS\" \"\$RAW\" \"" . $secretPlaceholder . "\")\n"
            . "curl -i -X POST \"" . $url . "\" \\\n"
            . "  -H \"Content-Type: application/json\" \\\n"
            . "  -H \"X-KT-Timestamp: \$TS\" \\\n"
            . "  -H \"X-KT-Signature: \$SIG\" \\\n"
            . "  --data \"\$RAW\"";
    }
}

if (!function_exists('kt_integration_hub_zalo_test_curl')) {
    function kt_integration_hub_zalo_test_curl(array $connection)
    {
        $url = kt_integration_hub_webhook_url($connection);
        $payload = '{"event_name":"user_send_text","sender":{"id":"zalo_user_001"},"recipient":{"id":"oa_test_001"},"message":{"msg_id":"msg_001","text":"Toi can tu van CRM"},"timestamp":1780000000000}';

        return "RAW='" . $payload . "'\n"
            . "curl -i -X POST \"" . $url . "\" \\\n"
            . "  -H \"Content-Type: application/json\" \\\n"
            . "  --data \"\$RAW\"";
    }
}
