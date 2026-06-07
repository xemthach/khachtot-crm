<?php

defined('BASEPATH') or exit('No direct script access allowed');

class TenantLifecycleEvents
{
    public function availableEvents()
    {
        return [
            'tenant.created',
            'tenant.provisioned',
            'tenant.suspended',
            'tenant.reactivated',
            'tenant.terminated',
            'subscription.renewed',
            'subscription.expired',
            'payment.succeeded',
            'payment.failed',
        ];
    }
}
