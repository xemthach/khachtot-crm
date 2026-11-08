<?php

defined('BASEPATH') or exit('No direct script access allowed');

$route['kt_integration_hub/webhook/(:any)/(:any)'] = 'kt_integration_webhooks/receive/$1/$2';
$route['kt_integration_hub/oauth/zalo_oa/callback/(:any)'] = 'kt_integration_webhooks/zalo_oauth_callback/$1';
$route['kt_integration_hub/oauth/tiktok_shop/callback/(:any)'] = 'kt_integration_webhooks/tiktok_oauth_callback/$1';
$route['kt_integration_hub/cron/process_jobs'] = 'kt_integration_cron/process_jobs';
$route['kt_integration_hub/cron/process_jobs/(:any)'] = 'kt_integration_cron/process_jobs/$1';
$route['kt_integration_hub/cron/reprovision_staff'] = 'kt_integration_cron/reprovision_staff';
$route['kt_integration_hub/cron/reprovision_staff/(:any)'] = 'kt_integration_cron/reprovision_staff/$1';
$route['kt_integration_hub/cron/m115_pilot_product/(:num)/(:any)'] = 'kt_integration_cron/m115_pilot_product/$1/$2';
$route['kt_integration_hub/cron/m115_accuracy/(:num)/(:any)'] = 'kt_integration_cron/m115_accuracy/$1/$2';
$route['api/v1/integration/tenant/context'] = 'kt_integration_api/tenant_context';
$route['api/v1/integration/map/browser-loader'] = 'kt_integration_api/map_browser_loader';
$route['api/v1/integration/platform/providers/mirror'] = 'kt_integration_api/platform_provider_mirror';
$route['api/v1/integration/dms/health'] = 'kt_integration_api/dms_management_health';
$route['api/v1/integration/dms/mappings'] = 'kt_integration_api/dms_mapping_summary';
$route['api/v1/integration/dms/retry'] = 'kt_integration_api/dms_retry_sync';
$route['api/v1/integration/dms/products/writeback'] = 'kt_integration_api/dms_product_writeback';
$route['api/v1/licensing/tenant'] = 'kt_integration_api/licensing_tenant';
$route['api/v1/dms/orders'] = 'kt_integration_api/dms_orders';
$route['api/v1/dms/orders/(:num)/cancel'] = 'kt_integration_api/dms_order_cancel/$1';
$route['api/v1/dms/collections'] = 'kt_integration_api/dms_collections';
$route['api/v1/dms/deliveries'] = 'kt_integration_api/dms_deliveries';
$route['api/v1/dms/returns'] = 'kt_integration_api/dms_returns';
$route['api/v1/manual-reviews'] = 'kt_integration_api/manual_reviews';
$route['api/v1/manual-reviews/(:num)'] = 'kt_integration_api/manual_review/$1';
$route['api/v1/manual-reviews/(:num)/assign'] = 'kt_integration_api/manual_review_assign/$1';
$route['api/v1/manual-reviews/(:num)/resolve'] = 'kt_integration_api/manual_review_resolve/$1';
$route['api/v1/manual-reviews/(:num)/close'] = 'kt_integration_api/manual_review_close/$1';
$route['api/v1/crm/invoices/(:num)'] = 'kt_integration_api/crm_invoice/$1';
$route['api/v1/crm/payments/(:num)'] = 'kt_integration_api/crm_payment/$1';
$route['api/v1/dms/checkins'] = 'kt_integration_api/dms_checkins';
$route['api/v1/dms/visits'] = 'kt_integration_api/dms_visits';
$route['api/v1/dms/live-tracking'] = 'kt_integration_api/dms_live_tracking';
$route['api/v1/dms/route-performance'] = 'kt_integration_api/dms_route_performance';
