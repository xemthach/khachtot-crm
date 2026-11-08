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

// ---------------------------------------------------------------------------
// KT Integration Hub public bridge API routes
// These routes must be loaded by application/config/routes.php because the
// module-local config/routes.php is not parsed by the main Perfex router.
// ---------------------------------------------------------------------------
$route['misa/callback']                                      = 'kt_misa_amis_accounting/kt_misa_callback/index';
$route['admin/kt_misa_amis_accounting']                      = 'kt_misa_amis_accounting/kt_misa_amis_accounting/index';
$route['admin/misa-amis/readiness']                          = 'kt_misa_amis_accounting/kt_misa_amis_accounting/readiness';
$route['admin/misa-amis/external-test']                      = 'kt_misa_amis_accounting/kt_misa_amis_accounting/external_test';
$route['admin/kt_misa_amis_accounting/connections']          = 'kt_misa_amis_accounting/kt_misa_amis_accounting/connections';
$route['admin/kt_misa_amis_accounting/connections/create']   = 'kt_misa_amis_accounting/kt_misa_amis_accounting/connections';
$route['admin/kt_misa_amis_accounting/connections/(:num)']   = 'kt_misa_amis_accounting/kt_misa_amis_accounting/connections/$1';
$route['admin/kt_misa_amis_accounting/connections/(:num)/disable'] = 'kt_misa_amis_accounting/kt_misa_amis_accounting/disable/$1';
$route['admin/kt_misa_amis_accounting/connections/(:num)/enable'] = 'kt_misa_amis_accounting/kt_misa_amis_accounting/enable/$1';
$route['admin/kt_misa_amis_accounting/connections/(:num)/test'] = 'kt_misa_amis_accounting/kt_misa_amis_accounting/mock_test/$1';
$route['admin/kt_misa_amis_accounting/mappings']             = 'kt_misa_amis_accounting/kt_misa_amis_accounting/mappings';
$route['admin/kt_misa_amis_accounting/requests']             = 'kt_misa_amis_accounting/kt_misa_amis_accounting/requests';
$route['admin/kt_misa_amis_accounting/dry-run']              = 'kt_misa_amis_accounting/kt_misa_amis_accounting/dry_run';
$route['admin/kt_misa_amis_accounting/requests/(:num)']     = 'kt_misa_amis_accounting/kt_misa_amis_accounting/request_detail/$1';
$route['admin/kt_misa_amis_accounting/requests/(:num)/retry'] = 'kt_misa_amis_accounting/kt_misa_amis_accounting/retry/$1';
$route['admin/kt_misa_amis_accounting/requests/(:num)/cancel'] = 'kt_misa_amis_accounting/kt_misa_amis_accounting/cancel/$1';
$route['admin/kt_misa_amis_accounting/requests/(:num)/payload'] = 'kt_misa_amis_accounting/kt_misa_amis_accounting/payload/$1';
$route['admin/kt_misa_amis_accounting/requests/(:num)/sensitive-metadata'] = 'kt_misa_amis_accounting/kt_misa_amis_accounting/sensitive_metadata/$1';
$route['kt_misa_amis_accounting/worker/work'] = 'kt_misa_amis_accounting/kt_misa_worker/work';
$route['kt_misa_amis_accounting/worker/work_tenant'] = 'kt_misa_amis_accounting/kt_misa_worker/work_tenant';
$route['kt_misa_amis_accounting/worker/reconcile'] = 'kt_misa_amis_accounting/kt_misa_worker/reconcile';
$route['kt_misa_amis_accounting/worker/refresh_token'] = 'kt_misa_amis_accounting/kt_misa_worker/refresh_token';
$route['kt_misa_amis_accounting/worker/health'] = 'kt_misa_amis_accounting/kt_misa_worker/health';

$route['api/v1/integration/tenant/context']                      = 'kt_integration_hub/kt_integration_api/tenant_context';
$route['api/v1/integration/dms/workspace']                      = 'kt_integration_hub/kt_integration_api/dms_workspace';
$route['api/v1/integration/map/browser-loader']                 = 'kt_integration_hub/kt_integration_api/map_browser_loader';
$route['api/v1/licensing/tenant']                                = 'kt_integration_hub/kt_integration_api/licensing_tenant';
$route['api/v1/integration/mail/send']                           = 'kt_integration_hub/kt_integration_api/mail_send';
$route['api/v1/dms/orders']                                      = 'kt_integration_hub/kt_integration_api/dms_orders';
$route['api/v1/dms/orders/(:num)/cancel']                        = 'kt_integration_hub/kt_integration_api/dms_order_cancel/$1';
$route['api/v1/manual-reviews']                                  = 'kt_integration_hub/kt_integration_api/manual_reviews';
$route['api/v1/manual-reviews/(:num)']                           = 'kt_integration_hub/kt_integration_api/manual_review/$1';
$route['api/v1/manual-reviews/(:num)/assign']                    = 'kt_integration_hub/kt_integration_api/manual_review_assign/$1';
$route['api/v1/manual-reviews/(:num)/resolve']                   = 'kt_integration_hub/kt_integration_api/manual_review_resolve/$1';
$route['api/v1/manual-reviews/(:num)/close']                     = 'kt_integration_hub/kt_integration_api/manual_review_close/$1';
$route['api/v1/notifications']                                   = 'kt_integration_hub/kt_integration_api/notifications';
$route['api/v1/notifications/(:num)']                            = 'kt_integration_hub/kt_integration_api/notification/$1';
$route['api/v1/notifications/(:num)/read']                       = 'kt_integration_hub/kt_integration_api/notification_read/$1';
$route['api/v1/notifications/(:num)/ack']                        = 'kt_integration_hub/kt_integration_api/notification_ack/$1';
$route['api/v1/crm/invoices/(:num)']                             = 'kt_integration_hub/kt_integration_api/crm_invoice/$1';
$route['api/v1/crm/payments/(:num)']                              = 'kt_integration_hub/kt_integration_api/crm_payment/$1';
$route['api/v1/dms/checkins']                                    = 'kt_integration_hub/kt_integration_api/dms_checkins';
$route['api/v1/dms/visits']                                      = 'kt_integration_hub/kt_integration_api/dms_visits';
$route['api/v1/dms/field-feedback']                              = 'kt_integration_hub/kt_integration_api/dms_field_feedback';
$route['api/v1/dms/live-tracking']                               = 'kt_integration_hub/kt_integration_api/dms_live_tracking';
$route['api/v1/dms/route-performance']                           = 'kt_integration_hub/kt_integration_api/dms_route_performance';
$route['api/v1/sso/token']                                       = 'kt_integration_hub/kt_integration_sso/token';
$route['sso/dms']                                                = 'kt_integration_hub/kt_integration_sso/dms';
