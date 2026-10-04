<?php

defined('BASEPATH') or exit('No direct script access allowed');
require_once APPPATH . 'libraries/KtReleasePolicy.php';

class KtLimitedRelease
{
    public function early()
    {
        $route = PHP_SAPI === 'cli'
            ? implode('/', array_slice($_SERVER['argv'] ?? [], 1))
            : ($_SERVER['REQUEST_URI'] ?? '');
        if (KtReleasePolicy::routeBlocked($route)) {
            KtReleasePolicy::deny();
        }
    }

    public function resolved()
    {
        $router = &load_class('Router', 'core');
        $route = $router->fetch_directory() . '/' . $router->fetch_class() . '/' . $router->fetch_method();
        if (KtReleasePolicy::routeBlocked($route)) {
            KtReleasePolicy::deny();
        }
    }
}
