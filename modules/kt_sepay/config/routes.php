<?php

defined('BASEPATH') or exit('No direct script access allowed');

$route['kt_sepay/pay/(:num)/(:any)'] = 'kt_sepay_public/pay/$1/$2';
$route['kt_sepay/status/(:num)/(:any)'] = 'kt_sepay_public/status/$1/$2';
$route['kt_sepay/webhook'] = 'kt_sepay_webhook/index';
$route['kt_sepay/webhook/tenant/(:any)'] = 'kt_sepay_webhook/tenant/$1';
