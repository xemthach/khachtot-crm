<?php

defined('BASEPATH') or exit('No direct script access allowed');

$config['kt_saas'] = [
    'tenant_database_strategy' => 'database_per_tenant',
    'default_db_driver'        => 'mysqli',
    'queue_mode'               => 'database',
    'storage_driver'           => 'local',
    'allow_custom_domains'     => true,
    'allow_wildcard_subdomain' => true,
    'plan_codes'               => ['free', 'trial', 'basic', 'pro', 'enterprise'],
];
