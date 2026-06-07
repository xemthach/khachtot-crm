<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * my_routes.php
 *
 * Custom routes loaded by application/config/routes.php via include_once.
 * Place module-level routes here so they are picked up by the main CI router.
 *
 * IMPORTANT: In Perfex CRM HMVC (MX Router), route targets for module
 * controllers must use the format: module_name/controller_name/method
 * e.g. 'kt_einvoice/kt_einvoice_admin/overview'
 * NOT 'kt_einvoice_admin/overview' (module 'kt_einvoice_admin' does not exist).
 */

// ---------------------------------------------------------------------------
// KT eInvoice – Landlord admin routes (browser URL: /admin/kt_einvoice/admin/*)
// ---------------------------------------------------------------------------
$route['admin/kt_einvoice/admin']                                = 'kt_einvoice/kt_einvoice_admin/overview';
$route['admin/kt_einvoice/admin/overview']                       = 'kt_einvoice/kt_einvoice_admin/overview';
$route['admin/kt_einvoice/admin/plan_features']                  = 'kt_einvoice/kt_einvoice_admin/plan_features';
$route['admin/kt_einvoice/admin/save_plan_features/(:num)']      = 'kt_einvoice/kt_einvoice_admin/save_plan_features/$1';
$route['admin/kt_einvoice/admin/records']                        = 'kt_einvoice/kt_einvoice_admin/all_records';
$route['admin/kt_einvoice/admin/all_records']                    = 'kt_einvoice/kt_einvoice_admin/all_records';
$route['admin/kt_einvoice/admin/api_logs']                       = 'kt_einvoice/kt_einvoice_admin/api_logs';
$route['admin/kt_einvoice/admin/cron_logs']                      = 'kt_einvoice/kt_einvoice_admin/cron_logs';
$route['admin/kt_einvoice/admin/tenant_settings/(:num)']         = 'kt_einvoice/kt_einvoice_admin/tenant_settings/$1';
$route['admin/kt_einvoice/admin/reset_tenant_quota/(:num)']      = 'kt_einvoice/kt_einvoice_admin/reset_tenant_quota/$1';
$route['admin/kt_einvoice/admin/deactivate_tenant/(:num)']       = 'kt_einvoice/kt_einvoice_admin/deactivate_tenant/$1';

// ---------------------------------------------------------------------------
// KT eInvoice – Tenant admin routes (browser URL: /admin/kt_einvoice/*)
// ---------------------------------------------------------------------------
$route['admin/kt_einvoice']                                      = 'kt_einvoice/kt_einvoice/dashboard';
$route['admin/kt_einvoice/dashboard']                            = 'kt_einvoice/kt_einvoice/dashboard';
$route['admin/kt_einvoice/invoices']                             = 'kt_einvoice/kt_einvoice/invoices';
$route['admin/kt_einvoice/invoice_detail/(:num)']                = 'kt_einvoice/kt_einvoice/invoice_detail/$1';
$route['admin/kt_einvoice/settings']                             = 'kt_einvoice/kt_einvoice/settings';
$route['admin/kt_einvoice/test_connection']                      = 'kt_einvoice/kt_einvoice/test_connection';
$route['admin/kt_einvoice/get_providers']                        = 'kt_einvoice/kt_einvoice/get_providers';
$route['admin/kt_einvoice/create_draft/(:num)']                  = 'kt_einvoice/kt_einvoice/create_draft/$1';
$route['admin/kt_einvoice/issue/(:num)']                         = 'kt_einvoice/kt_einvoice/issue/$1';
$route['admin/kt_einvoice/delete_draft/(:num)']                  = 'kt_einvoice/kt_einvoice/delete_draft/$1';
$route['admin/kt_einvoice/cancel_invoice/(:num)']                = 'kt_einvoice/kt_einvoice/cancel_invoice/$1';
$route['admin/kt_einvoice/download/(:num)']                      = 'kt_einvoice/kt_einvoice/download/$1';
$route['admin/kt_einvoice/download/(:num)/(:any)']               = 'kt_einvoice/kt_einvoice/download/$1/$2';
$route['admin/kt_einvoice/batch_issue']                          = 'kt_einvoice/kt_einvoice/batch_issue';
$route['admin/kt_einvoice/batch_status/(:any)']                  = 'kt_einvoice/kt_einvoice/batch_status/$1';
$route['admin/kt_einvoice/check_status/(:num)']                  = 'kt_einvoice/kt_einvoice/check_status/$1';
