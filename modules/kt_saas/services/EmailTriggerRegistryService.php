<?php

defined('BASEPATH') or exit('No direct script access allowed');

class EmailTriggerRegistryService
{
    protected array $registry = [
        'payment_success' => [
            'event_key' => 'payment_success',
            'recipient_scope' => 'tenant_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'invoice',
            'delivery_mode' => 'email',
            'template_slug' => 'payment_success',
            'duplicate_guard_key' => 'payment_success',
            'priority' => 'p0',
        ],
        'payment_failed' => [
            'event_key' => 'payment_failed',
            'recipient_scope' => 'tenant_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'invoice',
            'delivery_mode' => 'email',
            'template_slug' => 'payment_failed',
            'duplicate_guard_key' => 'payment_failed',
            'priority' => 'p0',
        ],
        'renewal_failed' => [
            'event_key' => 'renewal_failed',
            'recipient_scope' => 'tenant_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'invoice',
            'delivery_mode' => 'email',
            'template_slug' => 'renewal_failed',
            'duplicate_guard_key' => 'renewal_failed',
            'priority' => 'p0',
        ],
        'invoice_overdue' => [
            'event_key' => 'invoice_overdue',
            'recipient_scope' => 'tenant_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'invoice',
            'delivery_mode' => 'email',
            'template_slug' => 'invoice_overdue',
            'duplicate_guard_key' => 'invoice_overdue',
            'priority' => 'p0',
        ],
        'provisioning_completed' => [
            'event_key' => 'provisioning_completed',
            'recipient_scope' => 'tenant_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'tenant',
            'delivery_mode' => 'email',
            'template_slug' => 'tenant_provisioning_completed',
            'duplicate_guard_key' => 'provisioning_completed',
            'priority' => 'p0',
        ],
        'provisioning_failed' => [
            'event_key' => 'provisioning_failed',
            'recipient_scope' => 'tenant_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'tenant',
            'delivery_mode' => 'email',
            'template_slug' => 'tenant_provisioning_failed',
            'duplicate_guard_key' => 'provisioning_failed',
            'priority' => 'p0',
        ],
        'tenant_welcome' => [
            'event_key' => 'tenant_welcome',
            'recipient_scope' => 'tenant_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'tenant',
            'delivery_mode' => 'email',
            'template_slug' => 'tenant_welcome',
            'duplicate_guard_key' => 'tenant_welcome',
            'priority' => 'p0',
        ],
        'tenant_trial_started' => [
            'event_key' => 'tenant_trial_started',
            'recipient_scope' => 'tenant_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'tenant',
            'delivery_mode' => 'email',
            'template_slug' => 'tenant_trial_started',
            'duplicate_guard_key' => 'tenant_trial_started',
            'priority' => 'p0',
        ],
        'tenant_trial_ending' => [
            'event_key' => 'tenant_trial_ending',
            'recipient_scope' => 'tenant_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'subscription',
            'delivery_mode' => 'email',
            'template_slug' => 'tenant_trial_ending',
            'duplicate_guard_key' => 'tenant_trial_ending',
            'priority' => 'p0',
        ],
        'tenant_trial_expired' => [
            'event_key' => 'tenant_trial_expired',
            'recipient_scope' => 'tenant_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'subscription',
            'delivery_mode' => 'email',
            'template_slug' => 'tenant_trial_expired',
            'duplicate_guard_key' => 'tenant_trial_expired',
            'priority' => 'p0',
        ],
        'tenant_subscription_renewed' => [
            'event_key' => 'tenant_subscription_renewed',
            'recipient_scope' => 'tenant_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'subscription',
            'delivery_mode' => 'email',
            'template_slug' => 'tenant_subscription_renewed',
            'duplicate_guard_key' => 'tenant_subscription_renewed',
            'priority' => 'p0',
        ],
        'tenant_subscription_expired' => [
            'event_key' => 'tenant_subscription_expired',
            'recipient_scope' => 'tenant_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'subscription',
            'delivery_mode' => 'email',
            'template_slug' => 'tenant_subscription_expired',
            'duplicate_guard_key' => 'tenant_subscription_expired',
            'priority' => 'p0',
        ],
        'tenant_plan_changed' => [
            'event_key' => 'tenant_plan_changed',
            'recipient_scope' => 'tenant_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'subscription',
            'delivery_mode' => 'email',
            'template_slug' => 'tenant_plan_changed',
            'duplicate_guard_key' => 'tenant_plan_changed',
            'priority' => 'p0',
        ],
        'tenant_quota_warning' => [
            'event_key' => 'tenant_quota_warning',
            'recipient_scope' => 'tenant_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'usage',
            'delivery_mode' => 'email',
            'template_slug' => 'tenant_quota_warning',
            'duplicate_guard_key' => 'tenant_quota_warning',
            'priority' => 'p0',
        ],
        'tenant_quota_exceeded' => [
            'event_key' => 'tenant_quota_exceeded',
            'recipient_scope' => 'tenant_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'usage',
            'delivery_mode' => 'email',
            'template_slug' => 'tenant_quota_exceeded',
            'duplicate_guard_key' => 'tenant_quota_exceeded',
            'priority' => 'p0',
        ],
        'einvoice_activated' => [
            'event_key' => 'einvoice_activated',
            'recipient_scope' => 'tenant_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'addon',
            'delivery_mode' => 'email',
            'template_slug' => 'einvoice_activated',
            'duplicate_guard_key' => 'einvoice_activated',
            'priority' => 'p0',
        ],
        'einvoice_quota_low' => [
            'event_key' => 'einvoice_quota_low',
            'recipient_scope' => 'tenant_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'usage',
            'delivery_mode' => 'email',
            'template_slug' => 'einvoice_quota_low',
            'duplicate_guard_key' => 'einvoice_quota_low',
            'priority' => 'p1',
        ],
        'einvoice_quota_exhausted' => [
            'event_key' => 'einvoice_quota_exhausted',
            'recipient_scope' => 'tenant_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'usage',
            'delivery_mode' => 'email',
            'template_slug' => 'einvoice_quota_exhausted',
            'duplicate_guard_key' => 'einvoice_quota_exhausted',
            'priority' => 'p0',
        ],
        'hsm_activated' => [
            'event_key' => 'hsm_activated',
            'recipient_scope' => 'tenant_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'hsm',
            'delivery_mode' => 'email',
            'template_slug' => 'hsm_activated',
            'duplicate_guard_key' => 'hsm_activated',
            'priority' => 'p0',
        ],
        'hsm_expiry_warning' => [
            'event_key' => 'hsm_expiry_warning',
            'recipient_scope' => 'tenant_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'hsm',
            'delivery_mode' => 'email',
            'template_slug' => 'hsm_expiry_warning',
            'duplicate_guard_key' => 'hsm_expiry_warning',
            'priority' => 'p0',
        ],
        'invoice_issue_failed' => [
            'event_key' => 'invoice_issue_failed',
            'recipient_scope' => 'tenant_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'invoice',
            'delivery_mode' => 'email',
            'template_slug' => 'invoice_issue_failed',
            'duplicate_guard_key' => 'invoice_issue_failed',
            'priority' => 'p0',
        ],
        'invoice_sign_failed' => [
            'event_key' => 'invoice_sign_failed',
            'recipient_scope' => 'tenant_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'invoice',
            'delivery_mode' => 'email',
            'template_slug' => 'invoice_sign_failed',
            'duplicate_guard_key' => 'invoice_sign_failed',
            'priority' => 'p0',
        ],
        'unmatched_payment_alert' => [
            'event_key' => 'unmatched_payment_alert',
            'recipient_scope' => 'landlord_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'payment',
            'delivery_mode' => 'email',
            'template_slug' => 'unmatched_payment_alert',
            'duplicate_guard_key' => 'unmatched_payment_alert',
            'priority' => 'p0',
        ],
        'webhook_failed' => [
            'event_key' => 'webhook_failed',
            'recipient_scope' => 'landlord_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'webhook',
            'delivery_mode' => 'email',
            'template_slug' => 'webhook_failed',
            'duplicate_guard_key' => 'webhook_failed',
            'priority' => 'p0',
        ],
        'cron_failed' => [
            'event_key' => 'cron_failed',
            'recipient_scope' => 'landlord_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'cron',
            'delivery_mode' => 'email',
            'template_slug' => 'cron_failed',
            'duplicate_guard_key' => 'cron_failed',
            'priority' => 'p0',
        ],
        'backup_completed' => [
            'event_key' => 'backup_completed',
            'recipient_scope' => 'landlord_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'backup',
            'delivery_mode' => 'email',
            'template_slug' => 'backup_completed',
            'duplicate_guard_key' => 'backup_completed',
            'priority' => 'p1',
        ],
        'backup_failed' => [
            'event_key' => 'backup_failed',
            'recipient_scope' => 'landlord_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'backup',
            'delivery_mode' => 'email',
            'template_slug' => 'backup_failed',
            'duplicate_guard_key' => 'backup_failed',
            'priority' => 'p0',
        ],
        'provider_connection_failed' => [
            'event_key' => 'provider_connection_failed',
            'recipient_scope' => 'landlord_admin',
            'branding_context' => 'landlord',
            'provider_context' => 'landlord_global',
            'resource_type' => 'provider',
            'delivery_mode' => 'email',
            'template_slug' => 'provider_connection_failed',
            'duplicate_guard_key' => 'provider_connection_failed',
            'priority' => 'p0',
        ],
    ];

    public function all()
    {
        return hooks()->apply_filters('kt_saas_email_trigger_registry', $this->registry);
    }

    public function get($eventKey)
    {
        $eventKey = trim((string) $eventKey);
        if ($eventKey === '') {
            return null;
        }

        $registry = $this->all();
        return $registry[$eventKey] ?? null;
    }

    public function getRecipientScope($eventKey, $default = 'tenant_admin')
    {
        $row = $this->get($eventKey);
        return (string) ($row['recipient_scope'] ?? $default);
    }

    public function getBrandingContext($eventKey, $default = 'landlord')
    {
        $row = $this->get($eventKey);
        return (string) ($row['branding_context'] ?? $default);
    }

    public function getProviderContext($eventKey, $default = 'landlord_global')
    {
        $row = $this->get($eventKey);
        return (string) ($row['provider_context'] ?? $default);
    }

    public function getTemplateSlug($eventKey, $default = '')
    {
        $row = $this->get($eventKey);
        return (string) ($row['template_slug'] ?? $default);
    }

    public function getDeliveryMode($eventKey, $default = 'email')
    {
        $row = $this->get($eventKey);
        return (string) ($row['delivery_mode'] ?? $default);
    }

    public function getDuplicateGuardKey($eventKey, $default = '')
    {
        $row = $this->get($eventKey);
        return (string) ($row['duplicate_guard_key'] ?? $default);
    }
}
