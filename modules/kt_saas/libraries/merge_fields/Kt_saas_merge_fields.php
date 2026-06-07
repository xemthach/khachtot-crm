<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_saas_merge_fields extends App_merge_fields
{
    public function build()
    {
        $availableFor = hooks()->apply_filters('kt_saas_merge_fields_available_for', [
            'other',
            'notifications',
            'invoice',
            'estimate',
            'contract',
            'subscriptions',
        ]);

        return [
            [
                'name' => 'Tenant Name',
                'key' => '{tenant_name}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Tenant Code',
                'key' => '{tenant_code}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Workspace URL',
                'key' => '{workspace_url}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Workspace Name',
                'key' => '{workspace_name}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Workspace Domain',
                'key' => '{workspace_domain}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Owner Name',
                'key' => '{owner_name}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Owner Email',
                'key' => '{owner_email}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Plan Name',
                'key' => '{plan_name}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Admin Login URL',
                'key' => '{admin_login_url}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Set Password URL',
                'key' => '{set_password_url}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Support Email',
                'key' => '{support_email}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Password Link Expires In',
                'key' => '{password_link_expires_in}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Trial End Date',
                'key' => '{trial_end_date}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Subscription Status',
                'key' => '{subscription_status}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Payment URL',
                'key' => '{payment_url}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Invoice URL',
                'key' => '{invoice_url}',
                'available' => $availableFor,
            ],
            [
                'name' => 'PDF URL',
                'key' => '{pdf_url}',
                'available' => $availableFor,
            ],
            [
                'name' => 'XML URL',
                'key' => '{xml_url}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Quota Remaining',
                'key' => '{quota_remaining}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Quota Limit',
                'key' => '{quota_limit}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Invoice Total',
                'key' => '{invoice_total}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Currency',
                'key' => '{currency}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Invoice Number',
                'key' => '{invoice_number}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Invoice Status',
                'key' => '{invoice_status}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Payment Reference',
                'key' => '{payment_reference}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Payment Amount',
                'key' => '{payment_amount}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Payment Status',
                'key' => '{payment_status}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Transaction Code',
                'key' => '{transaction_code}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Bank Account',
                'key' => '{bank_account}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Webhook URL',
                'key' => '{webhook_url}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Job ID',
                'key' => '{job_id}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Provider Name',
                'key' => '{provider_name}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Module Name',
                'key' => '{module_name}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Lookup URL',
                'key' => '{lookup_url}',
                'available' => $availableFor,
            ],
            [
                'name' => 'HSM Status',
                'key' => '{hsm_status}',
                'available' => $availableFor,
            ],
            [
                'name' => 'HSM Expiry Date',
                'key' => '{hsm_expiry_date}',
                'available' => $availableFor,
            ],
            [
                'name' => 'eInvoice Quota',
                'key' => '{einvoice_quota}',
                'available' => $availableFor,
            ],
            [
                'name' => 'eInvoice Remaining',
                'key' => '{einvoice_remaining}',
                'available' => $availableFor,
            ],
            [
                'name' => 'Error Message',
                'key' => '{error_message}',
                'available' => $availableFor,
            ],
        ];
    }

    public function format(...$params)
    {
        $context = [];
        if (!empty($params[0]) && is_array($params[0])) {
            $context = $params[0];
        } elseif (!empty($params) && isset($params[0]) && is_object($params[0])) {
            $context = (array) $params[0];
        }

        $tenant = $this->extractTenant($context);
        $invoice = $this->extractInvoice($context);
        $subscription = $this->extractSubscription($context);
        $plan = $this->extractPlan($context);

        $fields = [
            '{tenant_name}' => $tenant['company_name'] ?? $tenant['tenant_name'] ?? '',
            '{tenant_code}' => $tenant['tenant_code'] ?? '',
            '{workspace_name}' => (string) ($context['workspace_name'] ?? ($tenant['company_name'] ?? ($tenant['tenant_name'] ?? ''))),
            '{workspace_domain}' => $tenant['custom_domain'] ?? $tenant['subdomain'] ?? '',
            '{owner_name}' => (string) ($context['owner_name'] ?? ($tenant['owner_name'] ?? '')),
            '{owner_email}' => (string) ($context['owner_email'] ?? ($tenant['owner_email'] ?? '')),
            '{plan_name}' => $plan['plan_name'] ?? $subscription['plan_name'] ?? '',
            '{admin_login_url}' => (string) ($context['admin_login_url'] ?? ''),
            '{set_password_url}' => (string) ($context['set_password_url'] ?? ''),
            '{support_email}' => (string) ($context['support_email'] ?? $this->defaultSupportEmail()),
            '{password_link_expires_in}' => (string) ($context['password_link_expires_in'] ?? '48 giờ'),
            '{trial_end_date}' => (string) ($context['trial_end_date'] ?? ($subscription['trial_ends_at'] ?? '')),
            '{subscription_status}' => (string) ($context['subscription_status'] ?? ($subscription['status'] ?? '')),
            '{quota_remaining}' => isset($context['quota_remaining']) ? (string) $context['quota_remaining'] : '',
            '{quota_limit}' => isset($context['quota_limit']) ? (string) $context['quota_limit'] : '',
            '{invoice_total}' => isset($context['invoice_total']) ? (string) $context['invoice_total'] : (isset($invoice['grand_total']) ? (string) $invoice['grand_total'] : ''),
            '{currency}' => (string) ($context['currency'] ?? ($invoice['currency'] ?? $subscription['currency'] ?? '')),
            '{invoice_number}' => (string) ($context['invoice_number'] ?? ($invoice['invoice_number'] ?? ($invoice['number'] ?? ''))),
            '{invoice_status}' => (string) ($context['invoice_status'] ?? ($invoice['status'] ?? '')),
            '{payment_reference}' => (string) ($context['payment_reference'] ?? ($invoice['payment_reference'] ?? '')),
            '{payment_amount}' => isset($context['payment_amount']) ? (string) $context['payment_amount'] : (isset($context['amount']) ? (string) $context['amount'] : (isset($invoice['amount_paid']) ? (string) $invoice['amount_paid'] : '')),
            '{payment_status}' => (string) ($context['payment_status'] ?? ($invoice['payment_status'] ?? ($invoice['status'] ?? ''))),
            '{transaction_code}' => (string) ($context['transaction_code'] ?? ($invoice['transaction_code'] ?? '')),
            '{bank_account}' => (string) ($context['bank_account'] ?? ($context['account_number'] ?? '')),
            '{webhook_url}' => (string) ($context['webhook_url'] ?? ''),
            '{job_id}' => (string) ($context['job_id'] ?? ''),
            '{provider_name}' => (string) ($context['provider_name'] ?? ''),
            '{module_name}' => (string) ($context['module_name'] ?? ''),
            '{lookup_url}' => (string) ($context['lookup_url'] ?? ''),
            '{hsm_status}' => (string) ($context['hsm_status'] ?? ''),
            '{hsm_expiry_date}' => (string) ($context['hsm_expiry_date'] ?? ''),
            '{einvoice_quota}' => isset($context['einvoice_quota']) ? (string) $context['einvoice_quota'] : '',
            '{einvoice_remaining}' => isset($context['einvoice_remaining']) ? (string) $context['einvoice_remaining'] : '',
            '{error_message}' => (string) ($context['error_message'] ?? ''),
        ];

        $fields['{workspace_url}'] = $this->buildWorkspaceUrl($tenant, $context);
        $fields['{payment_url}'] = $this->buildPaymentUrl($invoice, $tenant, $context);
        $fields['{invoice_url}'] = $this->buildInvoiceUrl($invoice, $context);
        $fields['{pdf_url}'] = (string) ($context['pdf_url'] ?? ($invoice['pdf_url'] ?? ''));
        $fields['{xml_url}'] = (string) ($context['xml_url'] ?? ($invoice['xml_url'] ?? ''));

        return hooks()->apply_filters('kt_saas_merge_fields', $fields, [
            'tenant' => $tenant,
            'invoice' => $invoice,
            'subscription' => $subscription,
            'plan' => $plan,
            'context' => $context,
        ]);
    }

    protected function defaultSupportEmail()
    {
        foreach (['smtp_email', 'email_from_address', 'companyemail'] as $option) {
            if (function_exists('get_option')) {
                $value = trim((string) get_option($option));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    protected function extractTenant(array $context)
    {
        foreach (['tenant', 'workspace', 'account'] as $key) {
            if (!empty($context[$key]) && is_array($context[$key])) {
                return $context[$key];
            }
        }

        if (!empty($context['tenant_id']) && function_exists('kt_saas_current_tenant')) {
            $tenant = kt_saas_current_tenant();
            if (is_array($tenant) && (int) ($tenant['id'] ?? 0) === (int) $context['tenant_id']) {
                return $tenant;
            }
        }

        if (function_exists('kt_saas_current_tenant')) {
            $tenant = kt_saas_current_tenant();
            if (is_array($tenant)) {
                return $tenant;
            }
        }

        return [];
    }

    protected function extractInvoice(array $context)
    {
        foreach (['invoice', 'saas_invoice'] as $key) {
            if (!empty($context[$key]) && is_array($context[$key])) {
                return $context[$key];
            }
        }

        return [];
    }

    protected function extractSubscription(array $context)
    {
        foreach (['subscription', 'saas_subscription'] as $key) {
            if (!empty($context[$key]) && is_array($context[$key])) {
                return $context[$key];
            }
        }

        return [];
    }

    protected function extractPlan(array $context)
    {
        if (!empty($context['plan']) && is_array($context['plan'])) {
            return $context['plan'];
        }

        return [];
    }

    protected function buildWorkspaceUrl(array $tenant, array $context)
    {
        if (!empty($context['workspace_url'])) {
            return (string) $context['workspace_url'];
        }

        if (!empty($tenant)) {
            if (function_exists('kt_saas_tenant_public_base_url')) {
                return rtrim((string) kt_saas_tenant_public_base_url($tenant), '/');
            }
        }

        return '';
    }

    protected function buildPaymentUrl(array $invoice, array $tenant, array $context)
    {
        if (!empty($context['payment_url'])) {
            return (string) $context['payment_url'];
        }

        if (!empty($invoice)) {
            if (!empty($invoice['checkout_url'])) {
                return (string) $invoice['checkout_url'];
            }

            if (!empty($invoice['id']) && !empty($invoice['token']) && function_exists('site_url')) {
                return site_url('kt_saas/checkout/invoice/' . (int) $invoice['id'] . '/' . rawurlencode((string) $invoice['token']));
            }

            if (!empty($invoice['id']) && !empty($tenant)) {
                if (!class_exists('PaymentCollectionService', false)) {
                    require_once module_dir_path(KT_SAAS_MODULE, 'services/PaymentCollectionService.php');
                }
                if (class_exists('PaymentCollectionService', false)) {
                    $service = new PaymentCollectionService();
                    return (string) $service->getCheckoutUrl($invoice, $tenant);
                }
            }
        }

        return '';
    }

    protected function buildInvoiceUrl(array $invoice, array $context)
    {
        if (!empty($context['invoice_url'])) {
            return (string) $context['invoice_url'];
        }

        if (!empty($invoice['invoice_url'])) {
            return (string) $invoice['invoice_url'];
        }

        if (!empty($invoice['id']) && !empty($invoice['hash']) && function_exists('site_url')) {
            return site_url('invoice/' . (int) $invoice['id'] . '/' . (string) $invoice['hash']);
        }

        return '';
    }
}
