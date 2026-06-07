<?php

defined('BASEPATH') or exit('No direct script access allowed');

$active_group  = 'default';
$query_builder = true;

$db['default'] = [
    'dsn'      => '',
    'hostname' => 'localhost',
    'username' => 'khachtot_user',
    'password' => 'CHANGE_ME',
    'database' => 'khachtot',
    'dbdriver' => 'mysqli',
    'dbprefix' => 'tbl',
    'pconnect' => false,
    'db_debug' => false,
    'cache_on' => false,
    'cachedir' => '',
    'char_set' => 'utf8mb4',
    'dbcollat' => 'utf8mb4_unicode_ci',
    'swap_pre' => '',
    'encrypt' => false,
    'compress' => false,
    'stricton' => false,
    'failover' => [],
    'save_queries' => true,
];

