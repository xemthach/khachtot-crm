<?php

defined('BASEPATH') or exit('No direct script access allowed');

define('APP_BASE_URL', 'https://khachtot.com/');
define('APP_ENC_KEY', 'CHANGE_ME_TO_A_RANDOM_32_CHAR_SECRET');

define('APP_DB_HOSTNAME', 'localhost');
define('APP_DB_USERNAME', 'khachtot_user');
define('APP_DB_PASSWORD', 'CHANGE_ME');
define('APP_DB_NAME', 'khachtot');
define('APP_DB_CHARSET', 'utf8mb4');
define('APP_DB_COLLATION', 'utf8mb4_unicode_ci');

define('KT_SAAS_ALLOW_HARD_DELETE', false);

define('SESS_DRIVER', 'database');
define('SESS_SAVE_PATH', 'sessions');
define('APP_SESSION_COOKIE_SAME_SITE', 'Lax');
define('APP_CSRF_PROTECTION', true);
