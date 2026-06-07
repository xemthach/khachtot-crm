<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * KT eInvoice Module Routes
 * 
 * Maps admin URLs to the Kt_einvoice_admin controller for landlord management.
 */

// Landlord admin routes
$route['kt_einvoice/admin']                               = 'kt_einvoice_admin/overview';
$route['kt_einvoice/admin/overview']                      = 'kt_einvoice_admin/overview';
$route['kt_einvoice/admin/plan_features']                 = 'kt_einvoice_admin/plan_features';
$route['kt_einvoice/admin/save_plan_features/(:num)']      = 'kt_einvoice_admin/save_plan_features/$1';
$route['kt_einvoice/admin/all_records']                   = 'kt_einvoice_admin/all_records';
$route['kt_einvoice/admin/api_logs']                      = 'kt_einvoice_admin/api_logs';
$route['kt_einvoice/admin/cron_logs']                     = 'kt_einvoice_admin/cron_logs';
$route['admin/kt_einvoice/admin'] = 'kt_einvoice_admin/overview';
$route['admin/kt_einvoice/admin/overview'] = 'kt_einvoice_admin/overview';
$route['admin/kt_einvoice/admin/plan_features'] = 'kt_einvoice_admin/plan_features';
$route['admin/kt_einvoice/admin/records'] = 'kt_einvoice_admin/all_records';
$route['admin/kt_einvoice/admin/api_logs'] = 'kt_einvoice_admin/api_logs';
$route['admin/kt_einvoice/admin/cron_logs'] = 'kt_einvoice_admin/cron_logs';
$route['kt_einvoice/admin/tenant_settings/(:num)']        = 'kt_einvoice_admin/tenant_settings/$1';
$route['kt_einvoice/admin/reset_tenant_quota/(:num)']      = 'kt_einvoice_admin/reset_tenant_quota/$1';
$route['kt_einvoice/admin/deactivate_tenant/(:num)']      = 'kt_einvoice_admin/deactivate_tenant/$1';

// Tenant admin routes
$route['admin/kt_einvoice']                               = 'kt_einvoice/dashboard';
$route['admin/kt_einvoice/dashboard']                     = 'kt_einvoice/dashboard';
$route['admin/kt_einvoice/invoices']                      = 'kt_einvoice/invoices';
$route['admin/kt_einvoice/invoice_detail/(:num)']        = 'kt_einvoice/invoice_detail/$1';
$route['admin/kt_einvoice/settings']                      = 'kt_einvoice/settings';
$route['admin/kt_einvoice/test_connection']               = 'kt_einvoice/test_connection';
$route['admin/kt_einvoice/get_providers']                 = 'kt_einvoice/get_providers';
$route['admin/kt_einvoice/create_draft/(:num)']          = 'kt_einvoice/create_draft/$1';
$route['admin/kt_einvoice/issue/(:num)']                 = 'kt_einvoice/issue/$1';
$route['admin/kt_einvoice/delete_draft/(:num)']          = 'kt_einvoice/delete_draft/$1';
$route['admin/kt_einvoice/cancel_invoice/(:num)']        = 'kt_einvoice/cancel_invoice/$1';
$route['admin/kt_einvoice/download/(:num)']              = 'kt_einvoice/download/$1';
$route['admin/kt_einvoice/download/(:num)/(:any)']       = 'kt_einvoice/download/$1/$2';
$route['admin/kt_einvoice/batch_issue']                  = 'kt_einvoice/batch_issue';
$route['admin/kt_einvoice/batch_status/(:any)']          = 'kt_einvoice/batch_status/$1';
$route['admin/kt_einvoice/check_status/(:num)']          = 'kt_einvoice/check_status/$1';
