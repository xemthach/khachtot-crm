<?php

defined('BASEPATH') or exit('No direct script access allowed');

class TenantLocalizationResolverService
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    public function resolveCurrent(array $context = [])
    {
        if (function_exists('kt_saas_is_tenant_runtime') && kt_saas_is_tenant_runtime()) {
            return $this->resolveTenant(null, $context);
        }

        return $this->resolveLandlord($context);
    }

    public function resolveTenant($tenant = null, array $context = [])
    {
        $tenant = $this->normalizeTenant($tenant);
        $currentProfile = function_exists('kt_saas_current_profile') ? kt_saas_current_profile() : null;

        $language = trim((string) ($tenant['locale'] ?? ''));
        $timezone = trim((string) ($tenant['timezone'] ?? ''));
        $currency = strtoupper(trim((string) ($tenant['currency'] ?? '')));
        $dateFormat = trim((string) get_option('dateformat'));
        $timeFormat = trim((string) get_option('time_format'));

        $fallbackFields = [];

        if ($language === '') {
            if ($language === '' && is_array($currentProfile)) {
                $language = trim((string) ($currentProfile['locale'] ?? ''));
            }
            if ($language === '') {
                $language = trim((string) get_option('active_language'));
            }
            if ($language === '') {
                $language = 'english';
                $fallbackFields[] = 'language';
            } else {
                $fallbackFields[] = 'language_tenant';
            }
        }

        if ($timezone === '') {
            if ($timezone === '' && is_array($currentProfile)) {
                $timezone = trim((string) ($currentProfile['timezone'] ?? ''));
            }
            if ($timezone === '') {
                $timezone = trim((string) get_option('default_timezone'));
            }
            if ($timezone === '') {
                $timezone = 'UTC';
                $fallbackFields[] = 'timezone';
            } else {
                $fallbackFields[] = 'timezone_tenant';
            }
        }

        if ($currency === '') {
            if ($currency === '' && is_array($currentProfile)) {
                $currency = strtoupper(trim((string) ($currentProfile['currency'] ?? '')));
            }
            if ($currency === '') {
                $currency = strtoupper(trim((string) get_option('default_currency')));
            }
            if ($currency === '') {
                $currency = 'USD';
                $fallbackFields[] = 'currency';
            } else {
                $fallbackFields[] = 'currency_tenant';
            }
        }

        if ($dateFormat === '') {
            $dateFormat = 'Y-m-d|%Y-%m-%d';
            $fallbackFields[] = 'dateformat';
        }

        if ($timeFormat !== '12') {
            $timeFormat = '24';
            if (trim((string) get_option('time_format')) === '') {
                $fallbackFields[] = 'time_format';
            }
        }

        $result = [
            'scope' => 'tenant',
            'source' => 'tenant_runtime',
            'language' => $language,
            'timezone' => $timezone,
            'currency' => $currency,
            'date_format' => $dateFormat,
            'time_format' => $timeFormat,
            'tenant_id' => (int) ($tenant['id'] ?? 0),
            'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
            'fallback_used' => !empty($fallbackFields),
            'fallback_fields' => array_values(array_unique($fallbackFields)),
        ];

        if (!empty($context['log_fallback']) && !empty($result['fallback_used'])) {
            log_message(
                'error',
                'KT SaaS localization fallback used for tenant [' . ($result['tenant_code'] ?: 'unknown') . ']: ' . implode(',', $result['fallback_fields'])
            );
        }

        return $result;
    }

    public function resolveLandlord(array $context = [])
    {
        $language = trim((string) get_option('active_language'));
        $timezone = trim((string) get_option('default_timezone'));
        $currency = strtoupper(trim((string) get_option('default_currency')));
        $dateFormat = trim((string) get_option('dateformat'));
        $timeFormat = trim((string) get_option('time_format'));

        return [
            'scope' => 'landlord',
            'source' => 'landlord_global',
            'language' => $language !== '' ? $language : 'english',
            'timezone' => $timezone !== '' ? $timezone : 'UTC',
            'currency' => $currency !== '' ? $currency : 'USD',
            'date_format' => $dateFormat !== '' ? $dateFormat : 'Y-m-d|%Y-%m-%d',
            'time_format' => $timeFormat === '12' ? '12' : '24',
            'tenant_id' => 0,
            'tenant_code' => '',
            'fallback_used' => false,
            'fallback_fields' => [],
        ];
    }

    protected function normalizeTenant($tenant)
    {
        if (is_array($tenant)) {
            return $tenant;
        }

        $current = function_exists('kt_saas_current_tenant') ? kt_saas_current_tenant() : null;
        return is_array($current) ? $current : [];
    }
}
