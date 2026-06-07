<?php

defined('BASEPATH') or exit('No direct script access allowed');

class EmailBrandingContextResolverService
{
    protected EmailTriggerRegistryService $registry;

    public function __construct()
    {
        $this->registry = new EmailTriggerRegistryService();
    }

    public function resolve($eventKey = null, array $context = [])
    {
        $eventKey = trim((string) $eventKey);
        if ($eventKey !== '') {
            $event = $this->registry->get($eventKey);
            if (is_array($event)) {
                return [
                    'event_key' => $eventKey,
                    'recipient_scope' => (string) ($event['recipient_scope'] ?? 'tenant_admin'),
                    'branding_context' => (string) ($event['branding_context'] ?? 'landlord'),
                    'provider_context' => (string) ($event['provider_context'] ?? 'landlord_global'),
                ];
            }
        }

        $scope = trim((string) ($context['recipient_scope'] ?? $context['scope'] ?? ''));
        if ($scope === '') {
            $scope = !empty($context['tenant_id']) ? 'tenant_admin' : 'landlord';
        }

        $brandingContext = 'landlord';
        if (in_array($scope, ['tenant', 'tenant_admin', 'customer', 'customer_contact', 'tenant_customer'], true)) {
            $brandingContext = 'tenant';
        }

        return [
            'event_key' => $eventKey !== '' ? $eventKey : null,
            'recipient_scope' => $scope,
            'branding_context' => $brandingContext,
            'provider_context' => $brandingContext === 'tenant' ? 'tenant_custom' : 'landlord_global',
        ];
    }

    public function resolveLandlord(array $context = [])
    {
        return $this->resolve(null, array_merge($context, [
            'recipient_scope' => 'landlord',
        ]));
    }

    public function resolveTenant(array $context = [])
    {
        return $this->resolve(null, array_merge($context, [
            'recipient_scope' => 'tenant_admin',
        ]));
    }
}
