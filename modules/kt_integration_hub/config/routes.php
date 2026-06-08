<?php

defined('BASEPATH') or exit('No direct script access allowed');

$route['kt_integration_hub/webhook/(:any)/(:any)'] = 'kt_integration_webhooks/receive/$1/$2';
$route['kt_integration_hub/oauth/zalo_oa/callback/(:any)'] = 'kt_integration_webhooks/zalo_oauth_callback/$1';
$route['kt_integration_hub/cron/process_jobs'] = 'kt_integration_cron/process_jobs';
$route['kt_integration_hub/cron/process_jobs/(:any)'] = 'kt_integration_cron/process_jobs/$1';
