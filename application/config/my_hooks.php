<?php

defined('BASEPATH') or exit('No direct script access allowed');

$hook['pre_system'][] = [
    'class'    => 'KtSaasRuntimeBootstrap',
    'function' => 'bootstrap',
    'filename' => 'KtSaasRuntimeBootstrap.php',
    'filepath' => 'hooks',
    'params'   => [],
];

$hook['pre_controller_constructor'][] = [
    'class'    => 'KtSaasTenantBootstrap',
    'function' => 'handle',
    'filename' => 'KtSaasTenantBootstrap.php',
    'filepath' => 'hooks',
    'params'   => [],
];
