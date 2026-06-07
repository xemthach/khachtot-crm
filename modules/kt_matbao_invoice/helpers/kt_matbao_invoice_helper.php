<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('kt_matbao_invoice_staff_can')) {
    function kt_matbao_invoice_staff_can($capability)
    {
        if (is_admin()) {
            return true;
        }

        return staff_can($capability, KT_MATBAO_INVOICE_MODULE);
    }
}

if (!function_exists('kt_matbao_invoice_is_module_request')) {
    function kt_matbao_invoice_is_module_request()
    {
        $CI = &get_instance();
        $class = strtolower((string) $CI->router->fetch_class());
        return strpos($class, 'kt_matbao_invoice') !== false;
    }
}

if (!function_exists('kt_matbao_invoice_is_landlord_context')) {
    function kt_matbao_invoice_is_landlord_context()
    {
        if (function_exists('kt_saas_is_landlord_context')) {
            return kt_saas_is_landlord_context();
        }

        return true;
    }
}

if (!function_exists('kt_matbao_invoice_tenant_can_access')) {
    function kt_matbao_invoice_tenant_can_access()
    {
        if (!function_exists('kt_saas_is_tenant_runtime') || !kt_saas_is_tenant_runtime()) {
            return false;
        }

        if (!function_exists('kt_saas_current_tenant')) {
            return false;
        }

        $tenant = kt_saas_current_tenant();
        if (empty($tenant['id'])) {
            return false;
        }

        if (!function_exists('kt_saas_runtime_entitlements')) {
            return false;
        }

        $service = kt_saas_runtime_entitlements();
        if (!$service || !method_exists($service, 'getFeatureValue')) {
            return false;
        }

        $tenantId = (int) $tenant['id'];

        // Primary entitlement key.
        $enabled = (bool) $service->getFeatureValue($tenantId, 'matbao_invoice.enabled', false);
        if ($enabled) {
            return true;
        }

        // Backward-compatible key used by kt_saas module registry.
        $legacy = (bool) $service->getFeatureValue($tenantId, 'kt_matbao_invoice.access', false);
        if ($legacy) {
            return true;
        }

        // Final fallback: module-level access check if available.
        if (method_exists($service, 'canUseModule')) {
            return (bool) $service->canUseModule($tenantId, KT_MATBAO_INVOICE_MODULE);
        }

        return false;
    }
}

if (!function_exists('kt_matbao_invoice_tenant_can_configure')) {
    function kt_matbao_invoice_tenant_can_configure()
    {
        if (!function_exists('kt_saas_is_tenant_runtime') || !kt_saas_is_tenant_runtime()) {
            return false;
        }

        $tenant = function_exists('kt_saas_current_tenant') ? kt_saas_current_tenant() : null;
        if (empty($tenant['id']) || !function_exists('kt_saas_runtime_entitlements')) {
            return false;
        }

        $service = kt_saas_runtime_entitlements();
        if (!$service || !method_exists($service, 'getFeatureValue')) {
            return false;
        }

        $tenantId = (int) $tenant['id'];
        if ((bool) $service->getFeatureValue($tenantId, 'matbao_invoice.tenant_config', false)) {
            return true;
        }

        // Backward compatibility for prefixed feature naming.
        if ((bool) $service->getFeatureValue($tenantId, 'kt_matbao_invoice.tenant_config', false)) {
            return true;
        }

        $CI = &get_instance();
        if (!isset($CI->Kt_matbao_invoice_model)) {
            $CI->load->model(KT_MATBAO_INVOICE_MODULE . '/Kt_matbao_invoice_model');
        }

        $landlordSettings = $CI->Kt_matbao_invoice_model->get_settings(null, 'landlord');
        return !empty($landlordSettings['is_active']) && !empty($landlordSettings['allow_tenant_override']);
    }
}

if (!function_exists('kt_matbao_invoice_mask_secret')) {
    function kt_matbao_invoice_mask_secret($value)
    {
        $value = (string) $value;
        $len = strlen($value);
        if ($len <= 6) {
            return str_repeat('*', $len);
        }

        return substr($value, 0, 3) . str_repeat('*', max(0, $len - 6)) . substr($value, -3);
    }
}

if (!function_exists('kt_matbao_invoice_encrypt')) {
    function kt_matbao_invoice_encrypt($value)
    {
        $value = (string) $value;
        if ($value === '') {
            return null;
        }

        if (function_exists('app_encrypt')) {
            return app_encrypt($value);
        }

        return base64_encode($value);
    }
}

if (!function_exists('kt_matbao_invoice_decrypt')) {
    function kt_matbao_invoice_decrypt($value)
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }

        if (function_exists('app_decrypt')) {
            $decrypted = (string) app_decrypt($value);
            if ($decrypted !== '') {
                return $decrypted;
            }
            // Backward compatibility: old data may be stored as base64 instead of app_encrypt.
            $decodedFallback = base64_decode($value, true);
            return $decodedFallback === false ? '' : $decodedFallback;
        }

        $decoded = base64_decode($value, true);
        return $decoded === false ? '' : $decoded;
    }
}
