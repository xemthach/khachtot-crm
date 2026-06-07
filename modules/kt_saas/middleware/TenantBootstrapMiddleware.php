<?php

defined('BASEPATH') or exit('No direct script access allowed');

class TenantBootstrapMiddleware
{
    public function handle($domain)
    {
        return [
            'resolved' => false,
            'domain'   => $domain,
        ];
    }
}
