<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('kt_misa_staff_can')) {
    function kt_misa_staff_can($capability)
    {
        return function_exists('staff_can') && (staff_can($capability, KT_MISA_AMIS_ACCOUNTING_MODULE) || is_admin());
    }
}

if (!function_exists('kt_misa_is_module_request')) {
    function kt_misa_is_module_request()
    {
        return strpos((string) ($_SERVER['REQUEST_URI'] ?? ''), '/admin/kt_misa_amis_accounting') !== false || strpos((string) ($_SERVER['REQUEST_URI'] ?? ''), '/admin/misa-amis') !== false;
    }
}

if (!function_exists('kt_misa_feature_enabled')) {
    function kt_misa_feature_enabled($name)
    {
        $defaults = [
            'module_enabled' => false,
            'external_calls_enabled' => false,
            'callback_enabled' => false,
            'master_data_sync_enabled' => false,
            'dry_run_enabled' => true,
        ];
        $key = 'kt_misa_' . $name;
        $value = get_option($key);
        if ($value === false || $value === null || $value === '') {
            return (bool) ($defaults[$name] ?? false);
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('kt_misa_current_scope')) {
    function kt_misa_current_scope($tenant = null, $companyId = null)
    {
        $tenant = is_array($tenant) ? $tenant : [];
        if (!$tenant && function_exists('kt_integration_hub_current_tenant')) {
            $current = kt_integration_hub_current_tenant();
            $tenant = is_array($current) ? $current : [];
        }
        $tenantId = (int) ($tenant['id'] ?? $tenant['tenant_id'] ?? 0);
        if ((int) $companyId <= 0 && function_exists('kt_integration_hub_crm_company_id')) {
            $companyId = kt_integration_hub_crm_company_id($tenant);
        }
        $companyId = (int) $companyId;
        if ($tenantId <= 0 || $companyId <= 0) {
            return null;
        }
        return ['tenant_id' => $tenantId, 'company_id' => $companyId];
    }
}

if (!function_exists('kt_misa_redact')) {
    function kt_misa_redact($value)
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $name = strtolower((string) $key);
                if (preg_match('/token|secret|access_code|authorization|password|api_key/', $name)) {
                    $out[$key] = '[REDACTED]';
                } else {
                    $out[$key] = kt_misa_redact($item);
                }
            }
            return $out;
        }
        return is_string($value) ? (strlen($value) > 8 ? substr($value, 0, 4) . '****' . substr($value, -4) : '[REDACTED]') : $value;
    }
}

if (!function_exists('kt_misa_json')) {
    function kt_misa_json($value)
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}

if (!function_exists('kt_misa_readiness_snapshot')) {
    function kt_misa_readiness_snapshot()
    {
        return [
            'mock_surface' => 'MOCK_VERIFIED',
            'local_worker_runtime' => 'LOCAL_RUNTIME_VERIFIED',
            'act_amis_documentation' => (string) get_option('kt_misa_act_amis_documentation_status') ?: 'ACT_AMIS_DOCUMENTATION_BLOCKED',
            'external_test' => (string) get_option('kt_misa_external_test_status') ?: 'NOT_RUN',
            'production' => (string) get_option('kt_misa_production_status') ?: 'BLOCKED',
            'external_transport_allowed' => false,
            'next_action' => 'readiness',
            'requirements' => [
                ['name' => 'Product family', 'status' => 'BLOCKED', 'evidence' => 'ACT_AMIS_CONFIRMATION_PACKAGE', 'blocker' => 'MISA product assignment is pending.', 'owner' => 'MISA / Khách Tốt', 'next' => 'Confirm AMIS Enterprise vs ASP.'],
                ['name' => 'Test base URL', 'status' => 'BLOCKED', 'evidence' => 'ACT_AMIS_ENVIRONMENT_RESOLUTION', 'blocker' => 'No host is approved for external calls.', 'owner' => 'MISA', 'next' => 'Provide exact test URI.'],
                ['name' => 'Token contract', 'status' => 'BLOCKED', 'evidence' => 'ACT_AMIS_TOKEN_CONTRACT', 'blocker' => 'Expiry authority and ticks units are unresolved.', 'owner' => 'MISA', 'next' => 'Confirm expiry semantics.'],
                ['name' => 'Callback registration', 'status' => 'BLOCKED', 'evidence' => 'ACT_AMIS_CALLBACK_CONTRACT', 'blocker' => 'Registration and retry delivery are not confirmed.', 'owner' => 'MISA', 'next' => 'Confirm callback procedure.'],
                ['name' => 'Supported test object', 'status' => 'BLOCKED', 'evidence' => 'ACT_AMIS_MASTER_DATA_MATRIX', 'blocker' => 'Complete object matrix is not confirmed.', 'owner' => 'MISA / Khách Tốt', 'next' => 'Select one low-risk object.'],
                ['name' => 'Authorization', 'status' => 'BLOCKED', 'evidence' => 'EXTERNAL_TEST_AUTHORIZATION', 'blocker' => 'No external test authorization is granted.', 'owner' => 'Khách Tốt', 'next' => 'Approve environment and request limit.'],
                ['name' => 'Local mock runtime', 'status' => 'PASS', 'evidence' => 'PHASE_11_EVIDENCE_INTEGRITY', 'blocker' => '', 'owner' => 'Khách Tốt', 'next' => 'Use mock test only.'],
                ['name' => 'Security boundary', 'status' => 'PASS', 'evidence' => 'MOCK_AND_LOCAL_CERTIFICATION', 'blocker' => '', 'owner' => 'Khách Tốt', 'next' => 'Keep external transport disabled.'],
            ],
        ];
    }
}

if (!function_exists('kt_misa_status_class')) {
    function kt_misa_status_class($status)
    {
        $map = ['MOCK_VERIFIED' => 'success', 'LOCAL_RUNTIME_VERIFIED' => 'success', 'PASS' => 'success', 'MOCK' => 'info', 'LOCAL_RUNTIME' => 'info', 'NOT_RUN' => 'muted', 'DISABLED' => 'muted', 'BLOCKED' => 'danger', 'ERROR' => 'danger', 'EXTERNAL_TEST_BLOCKED' => 'blocked'];
        return $map[strtoupper((string) $status)] ?? 'muted';
    }
}

if (!function_exists('kt_misa_status_label')) {
    function kt_misa_status_label($status)
    {
        $map = ['MOCK_VERIFIED' => 'Đã xác minh mock', 'LOCAL_RUNTIME_VERIFIED' => 'Đã xác minh runtime nội bộ', 'ACT_AMIS_DOCUMENTATION_BLOCKED' => 'Đang chờ tài liệu xác nhận', 'NOT_RUN' => 'Chưa chạy', 'BLOCKED' => 'Bị khóa', 'EXTERNAL_TEST_BLOCKED' => 'Kiểm tra kết nối thật bị khóa', 'PASS' => 'Đạt', 'MOCK' => 'Mô phỏng', 'LOCAL_RUNTIME' => 'Runtime nội bộ', 'DISABLED' => 'Đã tắt', 'ERROR' => 'Lỗi'];
        return $map[(string) $status] ?? (string) $status;
    }
}
