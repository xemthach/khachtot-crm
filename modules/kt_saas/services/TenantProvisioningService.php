<?php

defined('BASEPATH') or exit('No direct script access allowed');

class TenantProvisioningService
{
    public function buildJobPayload(array $tenant, array $plan)
    {
        return [
            'tenant' => $tenant,
            'plan'   => $plan,
            'steps'  => [
                'create_database',
                'import_schema',
                'seed_data',
                'create_admin',
                'prepare_storage',
                'configure_domain',
                'activate_modules',
                'send_welcome_email',
            ],
        ];
    }
}
