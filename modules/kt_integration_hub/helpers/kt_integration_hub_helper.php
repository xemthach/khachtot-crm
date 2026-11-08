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

if (!function_exists('kt_integration_hub_crm_company_id')) {
    /** Resolve the CRM company linked to a SaaS tenant without equating both IDs. */
    function kt_integration_hub_crm_company_id(array $tenant = [])
    {
        if (empty($tenant)) {
            $current = kt_integration_hub_current_tenant();
            $tenant = is_array($current) ? $current : [];
        }

        foreach (['crm_company_id', 'landlord_client_id'] as $field) {
            $companyId = (int) ($tenant[$field] ?? 0);
            if ($companyId > 0) {
                return $companyId;
            }
        }

        return null;
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
        if ($decrypted === false || $decrypted === null || $decrypted === '') {
            $fallback = base64_decode($value, true);
            if (is_string($fallback) && $fallback !== '') {
                return trim($fallback);
            }
        }

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

if (!function_exists('kt_integration_hub_sanitize_error_message')) {
    function kt_integration_hub_sanitize_error_message($message)
    {
        $message = trim((string) $message);
        if ($message === '') {
            return '';
        }

        $patterns = [
            '/(Authorization\s*:\s*Bearer\s+)[^\s"\']+/i' => '$1[redacted]',
            '/(\bBearer\s+)[A-Za-z0-9._~+\/-]+/i' => '$1[redacted]',
            '/((?:X-|x-)?Signature\s*:\s*)[^\s"\']+/i' => '$1[redacted]',
            '/((?:X-|x-)?(?:Api-)?Token\s*:\s*)[^\s"\']+/i' => '$1[redacted]',
            '/([?&](?:token|secret|signature|api_key)=)[^&\s"\']+/i' => '$1[redacted]',
            '/(--header\s+["\']?(?:Authorization|X-Signature|X-.*Token)[^"\']*["\']?)/i' => '--header "[redacted]"',
        ];

        $sanitized = preg_replace(array_keys($patterns), array_values($patterns), $message);
        if (!is_string($sanitized)) {
            return 'Integration request failed; sensitive error details were removed.';
        }

        if (preg_match('/\b(?:curl(?:\.exe)?|Authorization|Bearer|Signature|Secret|Token)\b/i', $sanitized)) {
            return 'Integration transport failed; sensitive command details were removed.';
        }

        return mb_substr($sanitized, 0, 1000);
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

if (!function_exists('kt_integration_hub_provider_doc_slug')) {
    function kt_integration_hub_provider_doc_slug($providerCode)
    {
        $map = [
            'custom_webhook' => 'custom-webhook-guide',
            'website_form' => 'custom-webhook-guide',
            'zalo_oa' => 'zalo-oa-tenant-guide',
            'tiktok_shop' => 'tiktok-shop-tenant-guide',
            'facebook_page' => 'facebook-guide-planned',
            'facebook_lead_ads' => 'facebook-guide-planned',
            'shopee' => 'shopee-guide-planned',
        ];

        return $map[(string) $providerCode] ?? '';
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

if (!function_exists('kt_integration_hub_extract_host')) {
    function kt_integration_hub_extract_host($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $url)) {
            return strtolower((string) parse_url($url, PHP_URL_HOST));
        }

        return strtolower($url);
    }
}

if (!function_exists('kt_integration_hub_dms_base_domain')) {
    function kt_integration_hub_dms_base_domain($tenant = [], $connection = [])
    {
        $configured = strtolower(trim((string) getenv('DMS_BASE_DOMAIN')));
        if ($configured !== '') {
            return $configured;
        }

        $settings = [];
        if (is_array($connection) && !empty($connection['settings_json'])) {
            $settings = kt_integration_hub_json_decode((string) $connection['settings_json'], []);
        } elseif (is_array($tenant) && !empty($tenant['_bridge_connection_settings'])) {
            $settings = kt_integration_hub_json_decode((string) $tenant['_bridge_connection_settings'], []);
        }

        $candidates = [
            $settings['base_url'] ?? '',
            $settings['app_url'] ?? '',
            $tenant['base_url'] ?? '',
            $tenant['external_account_id'] ?? '',
            $tenant['external_account_name'] ?? '',
        ];
        $subdomain = strtolower(trim((string) ($tenant['subdomain'] ?? '')));

        foreach ($candidates as $candidate) {
            $host = kt_integration_hub_extract_host($candidate);
            if ($host === '') {
                continue;
            }

            if ($subdomain !== '' && preg_match('/^' . preg_quote($subdomain, '/') . '\.(.+)$/i', $host, $matches)) {
                return strtolower(trim((string) ($matches[1] ?? '')));
            }

            return $host;
        }

        return 'salesrep.test';
    }
}

if (!function_exists('kt_integration_hub_dms_workspace_host')) {
    function kt_integration_hub_dms_workspace_host($tenant, $connection = [])
    {
        if (!is_array($tenant)) {
            return '';
        }

        $subdomain = strtolower(trim((string) ($tenant['subdomain'] ?? '')));
        if ($subdomain === '') {
            return '';
        }

        return $subdomain . '.' . kt_integration_hub_dms_base_domain($tenant, $connection);
    }
}

if (!function_exists('kt_integration_hub_dms_workspace_base_url')) {
    function kt_integration_hub_dms_workspace_base_url($tenant, $connection = [])
    {
        $host = kt_integration_hub_dms_workspace_host($tenant, $connection);
        if ($host === '') {
            return '';
        }

        $scheme = parse_url(defined('APP_BASE_URL') ? APP_BASE_URL : site_url(), PHP_URL_SCHEME) ?: 'https';

        return $scheme . '://' . $host;
    }
}

if (!function_exists('kt_integration_hub_dms_control_plane_base_url')) {
    function kt_integration_hub_dms_control_plane_base_url($tenant = [], $connection = [])
    {
        $configured = trim((string) getenv('DMS_CONTROL_PLANE_BASE_URL'));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $settings = [];
        if (is_array($connection) && !empty($connection['settings_json'])) {
            $settings = kt_integration_hub_json_decode((string) $connection['settings_json'], []);
        } elseif (is_array($tenant) && !empty($tenant['_bridge_connection_settings'])) {
            $settings = kt_integration_hub_json_decode((string) $tenant['_bridge_connection_settings'], []);
        }

        foreach (['control_plane_base_url', 'platform_base_url'] as $key) {
            $candidate = trim((string) ($settings[$key] ?? ''));
            if ($candidate !== '') {
                return rtrim($candidate, '/');
            }
        }

        $scheme = parse_url(defined('APP_BASE_URL') ? APP_BASE_URL : site_url(), PHP_URL_SCHEME) ?: 'https';
        $domain = kt_integration_hub_dms_base_domain($tenant, $connection);
        if ($domain === '') {
            return '';
        }

        return $scheme . '://' . $domain;
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

if (!function_exists('kt_integration_hub_tiktok_test_curl')) {
    function kt_integration_hub_tiktok_test_curl(array $connection)
    {
        $url = kt_integration_hub_webhook_url($connection);
        $payload = '{"event_id":"tts-event-001","event_type":"ORDER_STATUS_CHANGE","order_id":"TTS-ORDER-001","shop_id":"TTS-SHOP-001","status":"AWAITING_SHIPMENT","timestamp":1780000000,"mock_order_detail":{"order_id":"TTS-ORDER-001","order_code":"TTS-ORDER-001","status":"AWAITING_SHIPMENT","payment_status":"PAID","fulfillment_status":"PENDING","buyer":{"name":"Nguyen Van TikTok","phone":"0909000002"},"currency":"VND","subtotal":250000,"shipping_fee":20000,"discount_total":10000,"grand_total":260000,"items":[{"item_id":"product_001","sku_id":"sku_001","sku":"SKU-TTS-001","name":"San pham TikTok Test","quantity":1,"unit_price":250000,"total_price":250000}],"ordered_at":"2026-06-08 10:00:00"}}';

        return "RAW='" . $payload . "'\n"
            . "curl -i -X POST \"" . $url . "\" \\\n"
            . "  -H \"Content-Type: application/json\" \\\n"
            . "  --data \"\$RAW\"";
    }
}

if (!function_exists('kt_dms_ui_status_label')) {
    function kt_dms_ui_status_label($domain, $status)
    {
        $status = str_replace([' ', '-'], '_', strtolower(trim((string) $status)));
        $maps = [
            'order' => [
                'draft' => 'Nháp', 'submitted' => 'Đã gửi', 'manager_review' => 'Chờ quản lý duyệt',
                'approved' => 'Đã duyệt', 'rejected' => 'Từ chối', 'cancelled' => 'Đã hủy',
                'synced_to_crm' => 'Đã đồng bộ CRM', 'invoice_created' => 'Đã tạo hóa đơn',
                'payment_pending' => 'Chờ thanh toán', 'partial_paid' => 'Thanh toán một phần',
                'paid' => 'Đã thanh toán', 'failed_sync' => 'Lỗi đồng bộ', 'returned' => 'Đã trả hàng',
                'voided' => 'Đã hủy hóa đơn',
            ],
            'payment' => [
                'pending' => 'Chờ thanh toán', 'payment_pending' => 'Chờ thanh toán', 'unpaid' => 'Chưa thanh toán',
                'partial_paid' => 'Thanh toán một phần', 'partially_paid' => 'Thanh toán một phần',
                'paid' => 'Đã thanh toán', 'failed' => 'Thanh toán thất bại', 'cancelled' => 'Đã hủy',
            ],
            'invoice' => [
                'draft' => 'Nháp', 'created' => 'Đã tạo hóa đơn', 'sent' => 'Đã gửi',
                'unpaid' => 'Chưa thanh toán', 'partially_paid' => 'Thanh toán một phần',
                'paid' => 'Đã thanh toán', 'cancelled' => 'Đã hủy hóa đơn', 'voided' => 'Đã hủy hóa đơn',
            ],
            'sync' => [
                'not_started' => 'Chưa bắt đầu', 'no_data' => 'Chưa có dữ liệu',
                'queued' => 'Đang chờ', 'pending' => 'Đang chờ', 'running' => 'Đang xử lý',
                'done' => 'Đã có hiệu lực', 'succeeded' => 'Đã có hiệu lực', 'success' => 'Đã xử lý', 'synced' => 'Đã có hiệu lực',
                'failed' => 'Lỗi đồng bộ', 'failed_sync' => 'Lỗi đồng bộ', 'retry' => 'Cần thử lại',
                'ready' => 'Sẵn sàng', 'enabled' => 'Đang bật', 'blocked' => 'Đang bị chặn',
                'active' => 'Đang hoạt động', 'suspended' => 'Tạm ngưng', 'cancelled' => 'Đã hủy',
            ],
            'tracking' => [
                'online' => 'Đang hoạt động', 'offline' => 'Ngoại tuyến', 'stale' => 'Dữ liệu cũ',
                'active' => 'Đang bật', 'disabled' => 'Đang tắt',
            ],
            'checkin' => [
                'pending' => 'Chờ xử lý', 'valid' => 'Hợp lệ', 'verified_passed' => 'Hợp lệ',
                'warning' => 'Cần lưu ý', 'invalid' => 'Không hợp lệ', 'verified_failed' => 'Không hợp lệ',
            ],
            'visit' => [
                'planned' => 'Đã lên lịch', 'in_progress' => 'Đang thực hiện', 'partial' => 'Hoàn thành một phần',
                'completed' => 'Hoàn tất', 'missed' => 'Bị lỡ', 'cancelled' => 'Đã hủy',
            ],
            'feedback' => [
                'new' => 'Mới', 'in_review' => 'Đang xử lý', 'assigned' => 'Đã phân công',
                'resolved' => 'Đã xử lý', 'rejected' => 'Từ chối', 'cancelled' => 'Đã hủy',
            ],
            'severity' => [
                'low' => 'Thấp', 'normal' => 'Bình thường', 'high' => 'Cao', 'urgent' => 'Khẩn cấp',
            ],
            'marker' => [
                'staff_live' => 'Đội sales', 'store' => 'Điểm bán', 'checkin' => 'Check-in',
                'checkout' => 'Check-out', 'visit' => 'Lịch viếng thăm',
                'order_store' => 'Điểm bán của đơn hàng', 'captured_order' => 'Vị trí gửi đơn hàng',
                'field_feedback' => 'Phản ánh thị trường', 'location_snapshot' => 'Vị trí nhân viên',
                'route_history' => 'Lịch sử di chuyển',
            ],
        ];

        if ($status === '') {
            return 'Chưa có dữ liệu';
        }

        return $maps[$domain][$status] ?? 'Chưa xác định';
    }
}

if (!function_exists('kt_dms_ui_feedback_type_label')) {
    function kt_dms_ui_feedback_type_label($type)
    {
        $type = str_replace([' ', '-'], '_', strtolower(trim((string) $type)));
        $labels = [
            'customer_feedback' => 'Phản hồi khách hàng',
            'competitor_activity' => 'Báo đối thủ',
            'price_feedback' => 'Báo giá thị trường',
            'display_photo_report' => 'Ảnh trưng bày',
            'stockout_report' => 'Báo hết hàng',
            'promotion_report' => 'Báo khuyến mãi',
            'complaint' => 'Khiếu nại',
            'market_opportunity' => 'Cơ hội bán hàng',
            'custom_note' => 'Ghi chú khác',
        ];

        return $labels[$type] ?? 'Phản ánh thị trường';
    }
}

if (!function_exists('kt_dms_ui_money')) {
    function kt_dms_ui_money($value)
    {
        if ($value === null || $value === '') {
            return 'Chưa có dữ liệu';
        }

        return number_format((float) $value, 0, ',', '.') . ' ₫';
    }
}
