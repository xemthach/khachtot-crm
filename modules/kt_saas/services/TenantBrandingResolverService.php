<?php

defined('BASEPATH') or exit('No direct script access allowed');

class TenantBrandingResolverService
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
        $currentTenant = function_exists('kt_saas_current_tenant') ? kt_saas_current_tenant() : null;
        $currentProfile = function_exists('kt_saas_current_profile') ? kt_saas_current_profile() : null;

        $companyName = trim((string) ($tenant['company_name'] ?? ''));
        $logo = trim((string) get_option('company_logo'));
        $darkLogo = trim((string) get_option('company_logo_dark'));
        $favicon = trim((string) get_option('favicon'));
        $tenantId = (int) ($tenant['id'] ?? ($currentTenant['id'] ?? 0));
        $logo = $this->tenantBrandingFilename($tenantId, $logo);
        $darkLogo = $this->tenantBrandingFilename($tenantId, $darkLogo);
        $favicon = $this->tenantBrandingFilename($tenantId, $favicon);
        $address = trim((string) get_option('companyaddress'));
        $phone = trim((string) get_option('companyphonenumber'));
        $taxCode = trim((string) get_option('company_vat'));
        $website = $this->resolveWebsite($tenant);

        $fallbackFields = [];

        if ($companyName === '') {
            if ($companyName === '' && is_array($currentTenant)) {
                $companyName = trim((string) ($currentTenant['company_name'] ?? ''));
            }
            if ($companyName === '') {
                $companyName = trim((string) get_option('companyname'));
            }
            if ($companyName === '') {
                $companyName = 'Tenant';
                $fallbackFields[] = 'company_name';
            } else {
                $fallbackFields[] = 'company_name_tenant';
            }
        }

        if ($logo === '') {
            $fallbackFields[] = 'logo';
        }

        if ($darkLogo === '') {
            $darkLogo = $logo;
            if ($darkLogo !== '') {
                $fallbackFields[] = 'dark_logo';
            }
        }

        if ($favicon === '') {
            $fallbackFields[] = 'favicon';
        }

        if ($address === '') {
            $address = trim((string) ($tenant['invoice_company_address'] ?? ''));
            if ($address === '' && is_array($currentProfile)) {
                $address = trim((string) ($currentProfile['address'] ?? ''));
            }
            if ($address !== '') {
                $fallbackFields[] = 'address';
            }
        }

        if ($phone === '') {
            $phone = trim((string) ($tenant['invoice_company_phonenumber'] ?? ''));
            if ($phone === '' && is_array($currentProfile)) {
                $phone = trim((string) ($currentProfile['phone'] ?? ''));
            }
            if ($phone !== '') {
                $fallbackFields[] = 'phone';
            }
        }

        if ($taxCode === '') {
            $taxCode = trim((string) ($tenant['invoice_company_vat'] ?? ''));
            if ($taxCode !== '') {
                $fallbackFields[] = 'tax_code';
            }
        }

        $result = [
            'scope' => 'tenant',
            'source' => 'tenant_runtime',
            'company_name' => $companyName,
            'logo' => $logo,
            'dark_logo' => $darkLogo,
            'favicon' => $favicon,
            'logo_url' => $this->tenantBrandingUrl($tenantId, $logo),
            'dark_logo_url' => $this->tenantBrandingUrl($tenantId, $darkLogo),
            'favicon_url' => $this->tenantBrandingUrl($tenantId, $favicon),
            'logo_path' => $this->tenantBrandingPath($tenantId, $logo),
            'dark_logo_path' => $this->tenantBrandingPath($tenantId, $darkLogo),
            'favicon_path' => $this->tenantBrandingPath($tenantId, $favicon),
            'address' => $address,
            'phone' => $phone,
            'tax_code' => $taxCode,
            'website' => $website,
            'tenant_id' => $tenantId,
            'tenant_code' => (string) ($tenant['tenant_code'] ?? ($currentTenant['tenant_code'] ?? '')),
            'fallback_used' => !empty($fallbackFields),
            'fallback_fields' => array_values(array_unique($fallbackFields)),
        ];

        if (!empty($context['log_fallback']) && !empty($result['fallback_used'])) {
            log_message(
                'error',
                'KT SaaS branding fallback used for tenant [' . ($result['tenant_code'] ?: 'unknown') . ']: ' . implode(',', $result['fallback_fields'])
            );
        }

        return $result;
    }

    public function resolveLandlord(array $context = [])
    {
        $companyName = trim((string) get_option('companyname'));
        $logo = trim((string) get_option('company_logo'));
        $darkLogo = trim((string) get_option('company_logo_dark'));
        $favicon = trim((string) get_option('favicon'));

        $result = [
            'scope' => 'landlord',
            'source' => 'landlord_global',
            'company_name' => $companyName,
            'logo' => $logo,
            'dark_logo' => $darkLogo !== '' ? $darkLogo : $logo,
            'favicon' => $favicon,
            'logo_url' => $logo !== '' ? base_url('uploads/company/' . $logo) : '',
            'dark_logo_url' => ($darkLogo !== '' ? base_url('uploads/company/' . $darkLogo) : ($logo !== '' ? base_url('uploads/company/' . $logo) : '')),
            'favicon_url' => $favicon !== '' ? base_url('uploads/company/' . $favicon) : '',
            'logo_path' => $logo !== '' ? rtrim(get_upload_path_by_type('company'), '/\\') . DIRECTORY_SEPARATOR . $logo : '',
            'dark_logo_path' => $darkLogo !== '' ? rtrim(get_upload_path_by_type('company'), '/\\') . DIRECTORY_SEPARATOR . $darkLogo : '',
            'favicon_path' => $favicon !== '' ? rtrim(get_upload_path_by_type('company'), '/\\') . DIRECTORY_SEPARATOR . $favicon : '',
            'address' => trim((string) get_option('companyaddress')),
            'phone' => trim((string) get_option('companyphonenumber')),
            'tax_code' => trim((string) get_option('company_vat')),
            'website' => $this->resolveWebsite(null),
            'tenant_id' => 0,
            'tenant_code' => '',
            'fallback_used' => false,
            'fallback_fields' => [],
        ];

        if (!empty($context['log_fallback']) && trim((string) $result['company_name']) === '') {
            log_message('error', 'KT SaaS landlord branding fallback used because company name is empty.');
        }

        return $result;
    }

    protected function resolveWebsite($tenant = null)
    {
        if (function_exists('kt_saas_is_tenant_runtime') && kt_saas_is_tenant_runtime() && function_exists('kt_saas_tenant_public_base_url')) {
            $currentTenant = function_exists('kt_saas_current_tenant') ? kt_saas_current_tenant() : null;
            if (is_array($currentTenant)) {
                $website = trim((string) kt_saas_tenant_public_base_url($currentTenant));
                if ($website !== '') {
                    return $website;
                }
            }
        }

        if (is_array($tenant) && !empty($tenant['host'])) {
            return trim((string) $tenant['host']);
        }

        return trim((string) get_option('website'));
    }

    protected function tenantBrandingFilename($tenantId, $filename)
    {
        $tenantId = (int) $tenantId;
        $filename = basename(trim((string) $filename));
        if ($tenantId <= 0 || $filename === '') {
            return '';
        }

        $path = $this->tenantBrandingPath($tenantId, $filename);
        return $path !== '' && is_file($path) ? $filename : '';
    }

    protected function tenantBrandingPath($tenantId, $filename)
    {
        if (!function_exists('kt_saas_tenant_branding_path')) {
            return '';
        }

        return kt_saas_tenant_branding_path((int) $tenantId, $filename);
    }

    protected function tenantBrandingUrl($tenantId, $filename)
    {
        if (!function_exists('kt_saas_tenant_branding_url') || trim((string) $filename) === '') {
            return '';
        }

        return kt_saas_tenant_branding_url((int) $tenantId, $filename);
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
