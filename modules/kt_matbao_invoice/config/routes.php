<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Explicit landlord admin routes to avoid HMVC fallback mismatches.
$route['kt_matbao_invoice'] = 'kt_matbao_invoice/index';
$route['kt_matbao_invoice/settings'] = 'kt_matbao_invoice/settings';
$route['kt_matbao_invoice/test_connection'] = 'kt_matbao_invoice/test_connection';
$route['kt_matbao_invoice/test_ca_connection'] = 'kt_matbao_invoice/test_ca_connection';
$route['kt_matbao_invoice/templates'] = 'kt_matbao_invoice/templates';
$route['kt_matbao_invoice/sync_templates'] = 'kt_matbao_invoice/sync_templates';
$route['kt_matbao_invoice/invoices'] = 'kt_matbao_invoice/invoices';
$route['kt_matbao_invoice/logs'] = 'kt_matbao_invoice/logs';
$route['kt_matbao_invoice/webhook_logs'] = 'kt_matbao_invoice/webhook_logs';
$route['kt_matbao_invoice/plan_entitlements'] = 'kt_matbao_invoice/plan_entitlements';
$route['kt_matbao_invoice/reseller_packages'] = 'kt_matbao_invoice/reseller_packages';
$route['kt_matbao_invoice/reseller_packages/(:any)'] = 'kt_matbao_invoice/reseller_packages/$1';
$route['kt_matbao_invoice/orders'] = 'kt_matbao_invoice/orders';
$route['kt_matbao_invoice/order_mark_paid/(:num)'] = 'kt_matbao_invoice/order_mark_paid/$1';
$route['kt_matbao_invoice/provisioning_queue'] = 'kt_matbao_invoice/provisioning_queue';
$route['kt_matbao_invoice/tenant_addons'] = 'kt_matbao_invoice/tenant_addons';

$route['kt_matbao_invoice/webhook/invoice'] = 'kt_matbao_invoice_webhook/invoice';
$route['kt_matbao_invoice/webhook/signing'] = 'kt_matbao_invoice_webhook/signing';

// Tenant runtime routes must stay under module prefix for MX router:
// /admin/kt_matbao_invoice/tenant...
$route['kt_matbao_invoice/tenant'] = 'kt_matbao_invoice_tenant/index';
$route['kt_matbao_invoice/tenant/settings'] = 'kt_matbao_invoice_tenant/settings';
$route['kt_matbao_invoice/tenant/test_connection'] = 'kt_matbao_invoice_tenant/test_connection';
$route['kt_matbao_invoice/tenant/test_ca_connection'] = 'kt_matbao_invoice_tenant/test_ca_connection';
$route['kt_matbao_invoice/tenant/invoices'] = 'kt_matbao_invoice_tenant/invoices';
$route['kt_matbao_invoice/tenant/usage'] = 'kt_matbao_invoice_tenant/usage';
$route['kt_matbao_invoice/tenant/logs'] = 'kt_matbao_invoice_tenant/logs';
$route['kt_matbao_invoice/tenant/addons'] = 'kt_matbao_invoice_tenant/addons';
$route['kt_matbao_invoice/tenant/buy_addons'] = 'kt_matbao_invoice_tenant/buy_addons';
$route['kt_matbao_invoice/tenant/buy_package'] = 'kt_matbao_invoice_tenant/buy_package';
$route['kt_matbao_invoice/tenant/checkout_addon'] = 'kt_matbao_invoice_tenant/checkout_addon';
$route['kt_matbao_invoice/tenant/order/(:num)'] = 'kt_matbao_invoice_tenant/order/$1';
$route['kt_matbao_invoice/tenant/pay_order/(:num)'] = 'kt_matbao_invoice_tenant/pay_order/$1';
$route['kt_matbao_invoice/tenant/create_draft/(:num)'] = 'kt_matbao_invoice_tenant/create_draft/$1';
$route['kt_matbao_invoice/tenant/issue/(:num)'] = 'kt_matbao_invoice_tenant/issue/$1';
$route['kt_matbao_invoice/tenant/sync_status/(:num)'] = 'kt_matbao_invoice_tenant/sync_status/$1';
$route['kt_matbao_invoice/tenant/download/(:num)/(:any)'] = 'kt_matbao_invoice_tenant/download/$1/$2';
