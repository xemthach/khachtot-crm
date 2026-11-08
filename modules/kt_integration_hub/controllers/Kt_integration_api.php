<?php

defined('BASEPATH') or exit('No direct script access allowed');
defined('KT_INTEGRATION_HUB_MODULE') or define('KT_INTEGRATION_HUB_MODULE', 'kt_integration_hub');

class Kt_integration_api extends App_Controller
{
    private const TENANT_CONTEXT_PATH = '/api/v1/integration/tenant/context';
    private const DMS_WORKSPACE_PATH = '/api/v1/integration/dms/workspace';
    private const MAP_BROWSER_LOADER_PATH = '/api/v1/integration/map/browser-loader';
    private const PLATFORM_PROVIDER_MIRROR_PATH = '/api/v1/integration/platform/providers/mirror';
    private const DMS_PROVIDER_CODE = 'salesflow_dms';
    private const DMS_HEALTH_PATH = '/api/v1/integration/dms/health';
    private const DMS_MAPPING_PATH = '/api/v1/integration/dms/mappings';
    private const DMS_RETRY_PATH = '/api/v1/integration/dms/retry';
    private const DMS_PRODUCT_WRITEBACK_PATH = '/api/v1/integration/dms/products/writeback';
    private $dmsContractStore;

    public function __construct()
    {
        parent::__construct();
        $moduleRoot = dirname(__DIR__);
        require_once $moduleRoot . '/libraries/KtIntegrationSignedRequestValidator.php';
        require_once $moduleRoot . '/libraries/CrmInvoiceReadbackService.php';
        require_once $moduleRoot . '/libraries/CrmPaymentReadbackService.php';
        require_once $moduleRoot . '/libraries/ManualReviewService.php';
        require_once $moduleRoot . '/libraries/DmsCheckinBridgeException.php';
        require_once $moduleRoot . '/libraries/DmsCheckinBridgeProcessor.php';
        require_once $moduleRoot . '/libraries/DmsVisitBridgeException.php';
        require_once $moduleRoot . '/libraries/DmsVisitBridgeProcessor.php';
        require_once $moduleRoot . '/libraries/DmsFieldFeedbackBridgeException.php';
        require_once $moduleRoot . '/libraries/DmsFieldFeedbackBridgeProcessor.php';
        require_once $moduleRoot . '/libraries/DmsLiveTrackingBridgeException.php';
        require_once $moduleRoot . '/libraries/DmsLiveTrackingBridgeProcessor.php';
        require_once $moduleRoot . '/libraries/LiveTrackingAggregator.php';
        require_once $moduleRoot . '/libraries/RoutePerformanceAggregator.php';
        require_once $moduleRoot . '/libraries/NotificationBridgeService.php';
        require_once $moduleRoot . '/libraries/DmsMailBridgeService.php';
        require_once $moduleRoot . '/libraries/DmsOrderBridgeProcessor.php';
        require_once $moduleRoot . '/libraries/DmsIntegrationContractStore.php';
        require_once $moduleRoot . '/libraries/DmsProductWritebackService.php';
        require_once $moduleRoot . '/../kt_saas/services/LicensingService.php';
        require_once $moduleRoot . '/../kt_saas/services/TenantEntitlementService.php';
        $this->load->helper(KT_INTEGRATION_HUB_MODULE . '/kt_integration_hub');
        $this->load->model(KT_INTEGRATION_HUB_MODULE . '/Kt_integration_model');
        $this->load->model('kt_saas/Kt_saas_model');
        $this->dmsContractStore = new DmsIntegrationContractStore();
        $this->dmsContractStore->ensureSchema();
    }

    public function dms_management_health()
    {
        [$tenant] = $this->validateSalesflowRequest(self::DMS_HEALTH_PATH, '', 'GET', false);
        if (!$tenant) return;
        $companyId = $this->resolveRequestedCompanyId((array) $tenant, (int) ($this->input->get('crm_company_id', true) ?: 0));
        if ($companyId <= 0) { $this->errorResponse('TENANT_SCOPE_MISMATCH', 'Company does not match tenant scope.', 409); return; }
        $this->successResponse('DMS_READINESS', 'DMS readiness loaded.', $this->sourceAwareReadiness((int) $tenant['id'], $companyId));
    }

    public function dms_mapping_summary()
    {
        [$tenant] = $this->validateSalesflowRequest(self::DMS_MAPPING_PATH, '', 'GET', false);
        if (!$tenant) return;
        $companyId = $this->resolveRequestedCompanyId((array) $tenant, (int) ($this->input->get('crm_company_id', true) ?: 0));
        if ($companyId <= 0) { $this->errorResponse('TENANT_SCOPE_MISMATCH', 'Company does not match tenant scope.', 409); return; }
        $data = $this->sourceAwareReadiness((int) $tenant['id'], $companyId);
        $data['issues'] = $this->dmsContractStore->issues((int) $tenant['id'], $companyId, 100);
        $this->successResponse('DMS_MAPPING_SUMMARY', 'DMS mapping summary loaded.', $data);
    }

    public function dms_retry_sync()
    {
        $raw = (string) $this->input->raw_input_stream;
        [$tenant, , $validation] = $this->validateSalesflowRequest(self::DMS_RETRY_PATH, $raw, 'POST', true);
        if (!$tenant) return;
        $payload = json_decode($raw, true) ?: [];
        $companyId = $this->resolveRequestedCompanyId((array) $tenant, (int) ($payload['crm_company_id'] ?? 0));
        if ($companyId <= 0) { $this->errorResponse('TENANT_SCOPE_MISMATCH', 'Company does not match canonical tenant scope.', 409); return; }
        try {
            $request = $this->dmsContractStore->beginRequest((int) $tenant['id'], $companyId, 'retry_sync', (string) $validation['request_id'], (string) $validation['idempotency_key'], $payload);
            if (!empty($request['duplicate'])) { $this->successResponse('IDEMPOTENT_REPLAY', 'Prior retry result returned.', (array) $request['response']); return; }
            $jobId = (int) ($payload['job_id'] ?? 0);
            $job = $this->db->where(['id' => $jobId, 'tenant_id' => (int) $tenant['id']])->get(db_prefix().'kt_integration_sync_jobs')->row_array();
            if (!$job) { $this->errorResponse('SYNC_JOB_NOT_FOUND', 'Sync job not found in tenant scope.', 404); return; }
            $error = strtoupper((string) ($job['error_message'] ?? ''));
            foreach (['NOT_MAPPED','TENANT_SCOPE','IDEMPOTENCY','VALIDATION'] as $blocked) if (strpos($error, $blocked) !== false) { $this->errorResponse('DATA_CORRECTION_REQUIRED', 'Mapping/business conflict must be corrected before retry.', 409); return; }
            $ok = $this->Kt_integration_model->retry_job($jobId, (int) $tenant['id']);
            $result = ['job_id' => $jobId, 'status' => $ok ? 'queued' : 'not_retryable'];
            $this->dmsContractStore->completeRequest((int) $request['id'], $result);
            $this->successResponse('SYNC_RETRY_QUEUED', 'Safe retry processed.', $result);
        } catch (RuntimeException $e) {
            $code = $e->getMessage() === 'IDEMPOTENCY_KEY_REUSED' ? 'IDEMPOTENCY_KEY_REUSED' : 'INTERNAL_SERVER_ERROR';
            $this->errorResponse($code, $code === 'IDEMPOTENCY_KEY_REUSED' ? 'Idempotency key was used with another payload.' : 'Retry failed.', $code === 'IDEMPOTENCY_KEY_REUSED' ? 409 : 500);
        }
    }

    public function dms_product_writeback()
    {
        $raw = (string) $this->input->raw_input_stream;
        [$tenant, , $validation] = $this->validateSalesflowRequest(self::DMS_PRODUCT_WRITEBACK_PATH, $raw, 'POST', true);
        if (!$tenant) return;
        $payload = json_decode($raw, true) ?: [];
        $product = (array) ($payload['product'] ?? []);
        $companyId = $this->resolveRequestedCompanyId((array) $tenant, (int) ($product['crm_company_id'] ?? 0));
        if ($companyId <= 0) { $this->errorResponse('TENANT_SCOPE_MISMATCH', 'Company does not match canonical tenant scope.', 409); return; }
        try {
            $request = $this->dmsContractStore->beginRequest((int) $tenant['id'], $companyId, 'product_writeback', (string) $validation['request_id'], (string) $validation['idempotency_key'], $payload);
            if (!empty($request['duplicate'])) { $this->successResponse('IDEMPOTENT_REPLAY', 'Prior product result returned.', (array) $request['response']); return; }
            $scope = $this->Kt_integration_model->get_dms_sync_scope_settings((int) $tenant['id']);
            $result = (new DmsProductWritebackService())->write($tenant, $product, $scope);
            $this->dmsContractStore->completeRequest((int) $request['id'], $result);
            $this->dmsContractStore->recordHealth((int) $tenant['id'], $companyId, 'product', (string) $product['dms_product_id'], 'synced', (string) $result['crm_product_id'], null, null, (string) $validation['request_id']);
            $this->successResponse('CRM_PRODUCT_WRITTEN', 'CRM product mapping updated.', $result);
        } catch (RuntimeException $e) {
            $allowed = ['CRM_PRODUCT_WRITEBACK_DISABLED','TENANT_SCOPE_MISMATCH','INTEGRATION_NOT_CONFIGURED','VALIDATION_ERROR','IDEMPOTENCY_KEY_REUSED'];
            $code = in_array($e->getMessage(), $allowed, true) ? $e->getMessage() : 'INTERNAL_SERVER_ERROR';
            $this->errorResponse($code, $code, in_array($code, ['TENANT_SCOPE_MISMATCH','IDEMPOTENCY_KEY_REUSED'], true) ? 409 : ($code === 'CRM_PRODUCT_WRITEBACK_DISABLED' ? 403 : 422));
        }
    }

    public function tenant_context()
    {
        try {
            $this->handleTenantContext();
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub tenant context API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function licensing_tenant()
    {
        try {
            $this->handleLicensingTenant();
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub licensing tenant API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function dms_workspace()
    {
        try {
            $this->handleDmsWorkspace();
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub DMS workspace discovery API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function map_browser_loader()
    {
        try {
            [$tenant] = $this->validateSalesflowRequest(self::MAP_BROWSER_LOADER_PATH, '', 'GET', false);
            if (!$tenant) {
                return;
            }

            $config = $this->Kt_integration_model->get_dms_platform_config();
            $encrypted = (string) get_option('kt_dms_maps_runtime_browser_key_encrypted');
            if ($encrypted === '') {
                $encrypted = (string) get_option('kt_dms_google_maps_api_key_encrypted');
            }
            $key = $encrypted !== '' ? kt_integration_hub_decrypt_value($encrypted) : '';
            if (($config['map_provider'] ?? '') !== 'google'
                || ($config['map_status'] ?? '') !== 'ready'
                || $key === '') {
                $this->output
                    ->set_status_header(409)
                    ->set_content_type('application/javascript')
                    ->set_output('window.ktDmsGoogleMapLoadError="Cấu hình Google Maps chưa sẵn sàng.";');
                return;
            }

            $this->output
                ->set_header('Cache-Control: private, no-store, max-age=0')
                ->set_header('X-Content-Type-Options: nosniff')
                ->set_content_type('application/javascript')
                ->set_output('(function(){var s=document.createElement("script");s.src="https://maps.googleapis.com/maps/api/js?key='
                    . rawurlencode($key)
                    . '&callback=ktDmsInitGoogleMap";s.async=true;s.defer=true;s.onerror=function(){window.ktDmsGoogleMapLoadError="Không tải được Google Maps.";};document.head.appendChild(s);}());');
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub map browser loader failed: ' . $e->getMessage());
            $this->output
                ->set_status_header(503)
                ->set_content_type('application/javascript')
                ->set_output('window.ktDmsGoogleMapLoadError="Dịch vụ bản đồ tạm thời chưa khả dụng.";');
        }
    }

    public function platform_provider_mirror()
    {
        try {
            $raw = (string) $this->input->raw_input_stream;
            $validation = $this->validatePlatformProviderMirrorRequest($raw);
            if (!$validation) {
                return;
            }

            $payload = json_decode($raw, true);
            if (!is_array($payload)) {
                $this->errorResponse('INVALID_PAYLOAD', 'Provider mirror payload must be valid JSON.', 422);
                return;
            }

            $providers = $this->sanitizeProviderMirrorRows((array) ($payload['providers'] ?? []));
            if ($providers === []) {
                $this->errorResponse('INVALID_PAYLOAD', 'Provider mirror payload must include provider rows.', 422);
                return;
            }

            $browserRuntime = $this->sanitizeProviderBrowserRuntime((array) ($payload['browser_runtime'] ?? []));
            $result = $this->Kt_integration_model->save_dms_provider_safe_mirror($providers, $browserRuntime);

            $this->Kt_integration_model->log(
                'info',
                'api.platform_provider_mirror.accepted',
                'Salesrep platform provider mirror accepted.',
                [
                    'request_id' => (string) ($validation['request_id'] ?? ''),
                    'idempotency_key' => (string) ($validation['idempotency_key'] ?? ''),
                    'provider_count' => count($providers),
                    'config_version' => (int) ($result['config_version'] ?? 0),
                ],
                null,
                null,
                self::DMS_PROVIDER_CODE
            );

            $this->successResponse('PROVIDER_MIRROR_ACCEPTED', 'Provider mirror updated.', $result);
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub provider mirror API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function mail_send()
    {
        try {
            $this->handleMailSend();
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub mail bridge API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function dms_orders()
    {
        try {
            $this->handleDmsOrders();
        } catch (DmsOrderBridgeException $e) {
            $this->errorResponse($e->apiCode(), $e->getMessage(), $e->httpStatus(), $e->errors());
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub DMS order bridge API failed: ' . $e->getMessage());
            $this->Kt_integration_model->log(
                'error',
                'dms.order.bridge.exception',
                'DMS order bridge failed with an unexpected exception.',
                [
                    'exception_class' => get_class($e),
                    'exception_message' => $e->getMessage(),
                    'exception_file' => $e->getFile(),
                    'exception_line' => $e->getLine(),
                ],
                null,
                null,
                self::DMS_PROVIDER_CODE
            );
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function dms_order_cancel($orderId)
    {
        try {
            $this->handleDmsOrderCancellation((int) $orderId);
        } catch (DmsOrderBridgeException $e) {
            $this->errorResponse($e->apiCode(), $e->getMessage(), $e->httpStatus(), $e->errors());
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub DMS order cancellation API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function dms_collections()
    {
        try {
            $this->handleDmsCollections();
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub DMS collection bridge API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function dms_deliveries()
    {
        try {
            $this->handleDmsDeliveries();
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub DMS delivery projection API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function dms_returns()
    {
        try {
            $this->handleDmsReturns();
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub DMS return projection API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function crm_invoice($crmInvoiceId)
    {
        try {
            $this->handleCrmInvoiceReadback((int) $crmInvoiceId);
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub CRM invoice readback API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function crm_payment($crmInvoiceId)
    {
        try {
            $this->handleCrmPaymentReadback((int) $crmInvoiceId);
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub CRM payment readback API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function dms_checkins()
    {
        try {
            $this->handleDmsCheckins();
        } catch (DmsCheckinBridgeException $e) {
            $this->errorResponse($e->apiCode(), $e->getMessage(), $e->httpStatus(), $e->errors());
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub DMS check-in bridge API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function dms_visits()
    {
        try {
            $this->handleDmsVisits();
        } catch (DmsVisitBridgeException $e) {
            $this->errorResponse($e->apiCode(), $e->getMessage(), $e->httpStatus(), $e->errors());
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub DMS visit bridge API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function dms_field_feedback()
    {
        try {
            $this->handleDmsFieldFeedback();
        } catch (DmsFieldFeedbackBridgeException $e) {
            $this->errorResponse($e->apiCode(), $e->getMessage(), $e->httpStatus(), $e->errors());
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub DMS field feedback bridge API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function dms_live_tracking()
    {
        try {
            if (strtolower((string) $this->input->method()) === 'post') {
                $this->handleDmsLiveTracking();
                return;
            }

            if (strtolower((string) $this->input->method()) === 'get') {
                $this->handleDmsLiveTrackingReadback();
                return;
            }

            $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);
        } catch (DmsLiveTrackingBridgeException $e) {
            $this->errorResponse($e->apiCode(), $e->getMessage(), $e->httpStatus(), $e->errors());
        } catch (Throwable $e) {
            $this->Kt_integration_model->log(
                'error',
                'live_tracking.snapshot.exception',
                'Live tracking bridge threw an unexpected exception.',
                [
                    'error' => $e->getMessage(),
                    'type' => get_class($e),
                ],
                null,
                null,
                self::DMS_PROVIDER_CODE
            );
            log_message('error', 'KT Integration Hub DMS live tracking bridge API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function dms_route_performance()
    {
        try {
            $this->handleDmsRoutePerformance();
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub DMS route performance API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function manual_reviews()
    {
        try {
            $this->handleManualReviews();
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub manual reviews API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function manual_review($reviewId)
    {
        try {
            $this->handleManualReview((int) $reviewId);
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub manual review API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function manual_review_assign($reviewId)
    {
        try {
            $this->handleManualReviewAction((int) $reviewId, 'assign');
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub manual review assign API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function manual_review_resolve($reviewId)
    {
        try {
            $this->handleManualReviewAction((int) $reviewId, 'resolve');
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub manual review resolve API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function manual_review_close($reviewId)
    {
        try {
            $this->handleManualReviewAction((int) $reviewId, 'close');
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub manual review close API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function notifications()
    {
        try {
            $this->handleNotifications();
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub notifications API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function notification($notificationId)
    {
        try {
            $this->handleNotification((int) $notificationId);
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub notification API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function notification_read($notificationId)
    {
        try {
            $this->handleNotificationAction((int) $notificationId, 'read');
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub notification read API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function notification_ack($notificationId)
    {
        try {
            $this->handleNotificationAction((int) $notificationId, 'ack');
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub notification ack API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    private function handleTenantContext(): void
    {
        if (strtolower((string) $this->input->method()) !== 'get') {
            $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);

            return;
        }

        $headers = $this->requestHeaders();
        $tenantCode = strtoupper(trim((string) ($headers['x-tenant-code'] ?? $headers['X-Tenant-Code'] ?? '')));
        if ($tenantCode === '') {
            $this->errorResponse('TENANT_CODE_REQUIRED', 'Tenant code is required.', 422);

            return;
        }

        $bearerToken = $this->extractBearerToken($headers);
        if ($bearerToken === '') {
            $this->errorResponse('INVALID_TOKEN', 'Missing bearer token.', 401);

            return;
        }

        $connection = $this->Kt_integration_model->get_connection_by_public_key(self::DMS_PROVIDER_CODE, $bearerToken);
        if (!$connection) {
            $this->errorResponse('INVALID_TOKEN', 'Connection token is invalid.', 401);

            return;
        }

        $tenant = $this->db
            ->where('tenant_code', $tenantCode)
            ->where('deleted_at IS NULL', null, false)
            ->get(db_prefix() . 'kt_saas_tenants')
            ->row_array();

        if (!$tenant) {
            $this->errorResponse('TENANT_NOT_FOUND', 'Tenant not found.', 404);

            return;
        }

        if ((int) ($connection['tenant_id'] ?? 0) !== (int) ($tenant['id'] ?? 0)) {
            $this->errorResponse('TENANT_SCOPE_MISMATCH', 'Connection does not belong to this tenant.', 409);

            return;
        }

        $validator = new KtIntegrationSignedRequestValidator();
        $secret = kt_integration_hub_decrypt_value($connection['webhook_secret_encrypted'] ?? '');
        $rawBody = '';
        $validation = $validator->validate(
            $headers,
            'GET',
            self::TENANT_CONTEXT_PATH,
            $rawBody,
            (string) ($connection['public_key'] ?? ''),
            $secret,
            (string) ($tenant['tenant_code'] ?? ''),
            function ($requestId) use ($tenant, $connection) {
                return $this->Kt_integration_model->has_recent_request_log(
                    self::DMS_PROVIDER_CODE,
                    (int) ($tenant['id'] ?? 0),
                    (string) $requestId,
                    'api.request.accepted',
                    10
                );
            }
        );

        if (empty($validation['success'])) {
            $status = in_array((string) ($validation['code'] ?? ''), ['TENANT_SCOPE_MISMATCH'], true) ? 409 : 401;
            $this->Kt_integration_model->log(
                'warning',
                'api.request.rejected',
                (string) ($validation['message'] ?? 'Request rejected.'),
                [
                    'request_id' => (string) ($headers['x-request-id'] ?? $headers['X-Request-Id'] ?? ''),
                    'tenant_code' => $tenantCode,
                    'code' => (string) ($validation['code'] ?? 'INVALID_REQUEST'),
                ],
                (int) ($tenant['id'] ?? 0),
                (int) ($connection['id'] ?? 0),
                self::DMS_PROVIDER_CODE
            );
            $this->errorResponse((string) ($validation['code'] ?? 'UNAUTHORIZED'), (string) ($validation['message'] ?? 'Unauthorized.'), $status);

            return;
        }

        $subscription = $this->Kt_saas_model->get_tenant_subscription_profile((int) $tenant['id']);
        $entitlementService = new TenantEntitlementService();
        $profile = $entitlementService->getRuntimeProfile($tenant);

        $featureFlags = [];
        foreach ((array) ($profile['features'] ?? []) as $key => $enabled) {
            if (strpos((string) $key, 'dms.') === 0 || strpos((string) $key, 'workspace.') === 0) {
                $featureFlags[(string) $key] = (bool) $enabled;
            }
        }

        $this->Kt_integration_model->log(
            'info',
            'api.request.accepted',
            'Tenant context returned to Salesflow DMS.',
            [
                'request_id' => (string) ($validation['request_id'] ?? ''),
                'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
                'plan_code' => (string) ($subscription['plan_code'] ?? ''),
            ],
            (int) ($tenant['id'] ?? 0),
            (int) ($connection['id'] ?? 0),
            self::DMS_PROVIDER_CODE
        );

        $companyId = (int) (kt_integration_hub_crm_company_id((array) $tenant) ?? 0);
        if ($companyId <= 0) {
            $this->errorResponse('CANONICAL_COMPANY_REQUIRED', 'Canonical CRM company identity is required.', 409);
            return;
        }

        $this->successResponse('TENANT_CONTEXT_FETCHED', 'Tenant context fetched.', [
            'crm_tenant_id' => (int) ($tenant['id'] ?? 0),
            'crm_company_id' => $companyId,
            'canonical_crm_company_id' => $companyId,
            'compatibility_crm_company_id' => (int) ($tenant['id'] ?? 0),
            'identity_trust_level' => 'canonical',
            'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
            'company_name' => (string) ($tenant['company_name'] ?? ''),
            'plan_code' => (string) ($subscription['plan_code'] ?? ($profile['plan']['plan_code'] ?? '')),
            'entitlements' => array_values((array) ($profile['module_codes'] ?? [])),
            'feature_flags' => (object) $featureFlags,
            'status' => $this->normalizeTenantStatus($tenant, $subscription),
        ]);
    }

    private function handleDmsWorkspace(): void
    {
        if (strtolower((string) $this->input->method()) !== 'get') {
            $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);

            return;
        }

        $headers = $this->requestHeaders();

        $host = strtolower(trim((string) $this->input->get('host', true)));
        $slug = strtolower(trim((string) $this->input->get('slug', true)));
        $tenantCode = strtoupper(trim((string) $this->input->get('tenant_code', true)));

        if ($host === '' && $slug === '' && $tenantCode === '') {
            $this->errorResponse('WORKSPACE_LOOKUP_REQUIRED', 'A host, slug, or tenant code is required.', 422);

            return;
        }

        if ($slug === '' && $host !== '') {
            $slug = $this->workspaceSlugFromHost($host);
        }

        $tenant = null;
        if ($tenantCode !== '') {
            $tenant = $this->Kt_integration_model->get_tenant_by_code($tenantCode);
        }

        if (!$tenant && $slug !== '') {
            $tenant = $this->Kt_integration_model->get_tenant_by_subdomain($slug);
        }

        if (!$tenant) {
            $this->errorResponse('TENANT_NOT_FOUND', 'Tenant not found.', 404);

            return;
        }

        $connection = $this->Kt_integration_model->get_salesflow_connection_for_tenant((int) ($tenant['id'] ?? 0));
        if (!is_array($connection) || empty($connection['public_key']) || empty($connection['webhook_secret_encrypted'])) {
            $this->errorResponse('DMS_CONNECTION_NOT_CONFIGURED', 'Salesflow DMS connection is not configured for this tenant.', 409);

            return;
        }

        $platformRequest = $this->validateDmsWorkspaceRequest($headers, $connection);
        if (!$platformRequest['success']) {
            $this->errorResponse($platformRequest['code'], $platformRequest['message'], $platformRequest['status']);

            return;
        }

        $workspaceHost = kt_integration_hub_dms_workspace_host($tenant, $connection);
        if ($workspaceHost === '') {
            $this->errorResponse('WORKSPACE_HOST_UNAVAILABLE', 'Tenant DMS workspace host could not be resolved.', 409);

            return;
        }

        if ($host !== '' && $host !== strtolower($workspaceHost)) {
            $this->errorResponse('TENANT_SCOPE_MISMATCH', 'Workspace host does not match the requested tenant.', 409);

            return;
        }

        if ($slug !== '' && $slug !== strtolower(trim((string) ($tenant['subdomain'] ?? '')))) {
            $this->errorResponse('TENANT_SCOPE_MISMATCH', 'Workspace slug does not match the requested tenant.', 409);

            return;
        }

        $tenantDomain = function_exists('kt_saas_tenant_runtime_host')
            ? (string) kt_saas_tenant_runtime_host($tenant)
            : trim((string) ($tenant['custom_domain'] ?? $tenant['host'] ?? ''));

        $this->Kt_integration_model->log(
            'info',
            'api.workspace_discovery.accepted',
            'Tenant DMS workspace discovery succeeded.',
            [
                'request_id' => (string) ($platformRequest['request_id'] ?? ''),
                'workspace_key' => (string) ($platformRequest['workspace_key'] ?? ''),
                'workspace_host' => $workspaceHost,
                'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
            ],
            (int) ($tenant['id'] ?? 0),
            (int) ($connection['id'] ?? 0),
            self::DMS_PROVIDER_CODE
        );

        $companyId = (int) (kt_integration_hub_crm_company_id((array) $tenant) ?? 0);
        if ($companyId <= 0) {
            $this->errorResponse('CANONICAL_COMPANY_REQUIRED', 'Canonical CRM company identity is required.', 409);
            return;
        }
        $workspaceRows = $this->Kt_integration_model->get_dms_workspaces_for_canonical_tenant((int) ($tenant['id'] ?? 0));
        $workspace = $workspaceRows[0] ?? [];

        $this->successResponse('DMS_WORKSPACE_RESOLVED', 'DMS workspace resolved.', [
            'workspace_mode' => 'tenant',
            'dms_workspace_host' => strtolower($workspaceHost),
            'tenant_slug' => strtolower(trim((string) ($tenant['subdomain'] ?? ''))),
            'crm_tenant_id' => (int) ($tenant['id'] ?? 0),
            'crm_company_id' => $companyId,
            'canonical_crm_company_id' => $companyId,
            'compatibility_crm_company_id' => (int) ($tenant['id'] ?? 0),
            'identity_trust_level' => 'canonical',
            'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
            'tenant_domain' => $tenantDomain,
            'bridge_token' => (string) ($connection['public_key'] ?? ''),
            'bridge_secret' => kt_integration_hub_decrypt_value($connection['webhook_secret_encrypted'] ?? ''),
            'workspace_id' => (int) ($workspace['id'] ?? 0) ?: null,
            'workspace_slug' => (string) ($workspace['workspace_slug'] ?? strtolower(trim((string) ($tenant['subdomain'] ?? '')))),
            'workspace_host' => strtolower($workspaceHost),
            'package_code' => (string) ($workspace['package_code'] ?? ''),
            'license_status' => (string) ($workspace['entitlement_status'] ?? ''),
            'last_provisioned_at' => (string) ($workspace['last_synced_at'] ?? ''),
        ]);
    }

    private function handleLicensingTenant(): void
    {
        if (strtolower((string) $this->input->method()) !== 'get') {
            $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);

            return;
        }

        $headers = $this->requestHeaders();
        $tenantCode = strtoupper(trim((string) ($headers['x-tenant-code'] ?? $headers['X-Tenant-Code'] ?? '')));
        if ($tenantCode === '') {
            $this->errorResponse('TENANT_CODE_REQUIRED', 'Tenant code is required.', 422);

            return;
        }

        $bearerToken = $this->extractBearerToken($headers);
        if ($bearerToken === '') {
            $this->errorResponse('INVALID_TOKEN', 'Missing bearer token.', 401);

            return;
        }

        $connection = $this->Kt_integration_model->get_connection_by_public_key(self::DMS_PROVIDER_CODE, $bearerToken);
        if (!$connection) {
            $this->errorResponse('INVALID_TOKEN', 'Connection token is invalid.', 401);

            return;
        }

        $tenant = $this->db
            ->where('tenant_code', $tenantCode)
            ->where('deleted_at IS NULL', null, false)
            ->get(db_prefix() . 'kt_saas_tenants')
            ->row_array();

        if (!$tenant) {
            $this->errorResponse('TENANT_NOT_FOUND', 'Tenant not found.', 404);

            return;
        }

        if ((int) ($connection['tenant_id'] ?? 0) !== (int) ($tenant['id'] ?? 0)) {
            $this->errorResponse('TENANT_SCOPE_MISMATCH', 'Connection does not belong to this tenant.', 409);

            return;
        }

        $validator = new KtIntegrationSignedRequestValidator();
        $secret = kt_integration_hub_decrypt_value($connection['webhook_secret_encrypted'] ?? '');
        $validation = $validator->validate(
            $headers,
            'GET',
            '/api/v1/licensing/tenant',
            '',
            (string) ($connection['public_key'] ?? ''),
            $secret,
            (string) ($tenant['tenant_code'] ?? ''),
            function ($requestId) use ($tenant) {
                return $this->Kt_integration_model->has_recent_request_log(
                    self::DMS_PROVIDER_CODE,
                    (int) ($tenant['id'] ?? 0),
                    (string) $requestId,
                    'api.request.accepted',
                    10
                );
            },
            false
        );

        if (empty($validation['success'])) {
            $status = in_array((string) ($validation['code'] ?? ''), ['TENANT_SCOPE_MISMATCH'], true) ? 409 : 401;
            $this->Kt_integration_model->log(
                'warning',
                'api.request.rejected',
                (string) ($validation['message'] ?? 'Request rejected.'),
                [
                    'request_id' => (string) ($headers['x-request-id'] ?? $headers['X-Request-Id'] ?? ''),
                    'tenant_code' => $tenantCode,
                    'code' => (string) ($validation['code'] ?? 'INVALID_REQUEST'),
                ],
                (int) ($tenant['id'] ?? 0),
                (int) ($connection['id'] ?? 0),
                self::DMS_PROVIDER_CODE
            );
            $this->errorResponse((string) ($validation['code'] ?? 'UNAUTHORIZED'), (string) ($validation['message'] ?? 'Unauthorized.'), $status);

            return;
        }

        $subscription = $this->Kt_saas_model->get_tenant_subscription_profile((int) $tenant['id']);
        $entitlementService = new TenantEntitlementService();
        $profile = $entitlementService->getRuntimeProfile($tenant);
        $addonContext = method_exists($this->Kt_saas_model, 'resolve_tenant_dms_addon_context')
            ? $this->Kt_saas_model->resolve_tenant_dms_addon_context((int) ($tenant['id'] ?? 0))
            : [];
        $licensingService = new LicensingService();
        $payload = $licensingService->buildTenantLicensingPayload($tenant, $subscription, $profile, $entitlementService, $addonContext);
        $canonicalCompanyId = (int) (kt_integration_hub_crm_company_id((array) $tenant) ?? 0);
        if ($canonicalCompanyId > 0) {
            $payload['crm_company_id'] = $canonicalCompanyId;
            $payload['canonical_crm_company_id'] = $canonicalCompanyId;
            $payload['compatibility_crm_company_id'] = (int) ($tenant['id'] ?? 0);
        }

        $this->Kt_integration_model->log(
            'info',
            'api.request.accepted',
            'Tenant licensing returned to Salesflow DMS.',
            [
                'request_id' => (string) ($validation['request_id'] ?? ''),
                'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
                'plan_code' => (string) ($subscription['plan_code'] ?? ''),
            ],
            (int) ($tenant['id'] ?? 0),
            (int) ($connection['id'] ?? 0),
            self::DMS_PROVIDER_CODE
        );

        $this->successResponse('TENANT_LICENSE_FETCHED', 'Tenant licensing fetched.', $payload);
    }

    private function handleMailSend(): void
    {
        if (strtolower((string) $this->input->method()) !== 'post') {
            $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);

            return;
        }

        [$tenant, $connection, $validation] = $this->validateSalesflowRequest('/api/v1/integration/mail/send', $this->input->raw_input_stream, 'POST', true);
        if (! $tenant || ! $connection || ! $validation) {
            return;
        }

        $payload = json_decode((string) $this->input->raw_input_stream, true);
        if (!is_array($payload)) {
            $this->errorResponse('VALIDATION_ERROR', 'Payload JSON khong hop le.', 422, [
                'body' => ['Payload must be valid JSON.'],
            ]);

            return;
        }

        $service = new DmsMailBridgeService();
        $result = $service->send($tenant, $payload, [
            'request_id' => (string) ($validation['request_id'] ?? ''),
            'idempotency_key' => (string) ($validation['idempotency_key'] ?? ''),
            'connection_id' => (int) ($connection['id'] ?? 0),
        ]);

        if (empty($result['success'])) {
            $status = (int) ($result['status'] ?? 422);
            $this->errorResponse(
                (string) ($result['code'] ?? 'MAIL_SEND_FAILED'),
                (string) ($result['message'] ?? 'Mail bridge send failed.'),
                $status,
                (array) ($result['errors'] ?? [])
            );

            return;
        }

        $this->successResponse(
            (string) ($result['code'] ?? 'MAIL_SENT'),
            (string) ($result['message'] ?? 'Mail sent.'),
            $result
        );
    }

    private function normalizeTenantStatus(array $tenant, ?array $subscription): string
    {
        $tenantStatus = strtolower(trim((string) ($tenant['status'] ?? 'draft')));
        $subscriptionStatus = strtolower(trim((string) ($subscription['status'] ?? '')));

        if (in_array($tenantStatus, ['suspended', 'terminated', 'inactive'], true)) {
            return 'suspended';
        }

        if (in_array($subscriptionStatus, ['grace'], true)) {
            return 'grace_period';
        }

        if (in_array($subscriptionStatus, ['trial', 'active'], true) || in_array($tenantStatus, ['active', 'trial'], true)) {
            return 'active';
        }

        return 'suspended';
    }

    private function handleDmsOrders(): void
    {
        if (strtolower((string) $this->input->method()) !== 'post') {
            $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);

            return;
        }

        [$tenant, $connection, $validation] = $this->validateSalesflowRequest('/api/v1/dms/orders', $this->input->raw_input_stream);
        if (!$tenant || !$connection || !$validation) {
            return;
        }

        $payload = json_decode((string) $this->input->raw_input_stream, true);
        if (!is_array($payload)) {
            $this->errorResponse('VALIDATION_ERROR', 'Payload JSON khong hop le.', 422, [
                'body' => ['Payload must be valid JSON.'],
            ]);

            return;
        }

        $errors = $this->validateDmsOrderPayload($payload);
        if ($errors !== []) {
            $this->errorResponse('VALIDATION_ERROR', 'Payload khong hop le.', 422, $errors);

            return;
        }

        $tenantSection = (array) ($payload['tenant'] ?? []);
        if ((int) ($tenantSection['crm_tenant_id'] ?? 0) !== (int) ($tenant['id'] ?? 0)
            || strtoupper(trim((string) ($tenantSection['tenant_code'] ?? ''))) !== strtoupper(trim((string) ($tenant['tenant_code'] ?? '')))) {
            $this->errorResponse('TENANT_SCOPE_MISMATCH', 'Payload tenant context does not match CRM tenant runtime.', 409);

            return;
        }

        $processor = new DmsOrderBridgeProcessor();
        $result = $processor->handle($tenant, $connection, $payload, [
            'request_id' => (string) ($validation['request_id'] ?? ''),
            'idempotency_key' => (string) ($validation['idempotency_key'] ?? ''),
        ]);
        $this->dmsContractStore->recordHealth((int) $tenant['id'], (int) ($payload['tenant']['crm_company_id'] ?? $tenant['id']), 'order', (string) ($payload['order']['order_id'] ?? 0), 'synced', (string) ($result['crm_invoice_id'] ?? ''), null, null, (string) ($validation['request_id'] ?? ''));

        $this->successResponse('ORDER_ACCEPTED', 'DMS order accepted and invoice draft created', $result);
    }

    private function handleCrmInvoiceReadback(int $crmInvoiceId): void
    {
        if (strtolower((string) $this->input->method()) !== 'get') {
            $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);

            return;
        }

        if ($crmInvoiceId <= 0) {
            $this->errorResponse('VALIDATION_ERROR', 'crm_invoice_id khong hop le.', 422, [
                'crm_invoice_id' => ['crm_invoice_id must be a positive integer.'],
            ]);

            return;
        }

        $path = '/api/v1/crm/invoices/' . $crmInvoiceId;
        [$tenant, $connection, $validation] = $this->validateSalesflowRequest($path, '', 'GET', false);
        if (!$tenant || !$connection || !$validation) {
            return;
        }

        $this->activateTenantDatabaseForBridge($tenant);
        $service = new CrmInvoiceReadbackService();
        $payload = $service->getInvoiceReadbackPayload($crmInvoiceId, (int) ($tenant['id'] ?? 0));
        if (!is_array($payload)) {
            $this->Kt_integration_model->log(
                'warning',
                'crm.invoice.readback.not_found',
                'CRM invoice was not found for readback.',
                [
                    'request_id' => (string) ($validation['request_id'] ?? ''),
                    'crm_invoice_id' => $crmInvoiceId,
                ],
                (int) ($tenant['id'] ?? 0),
                (int) ($connection['id'] ?? 0),
                self::DMS_PROVIDER_CODE
            );
            $this->errorResponse('INVOICE_NOT_FOUND', 'CRM invoice not found.', 404);

            return;
        }

        $this->Kt_integration_model->log(
            'info',
            'crm.invoice.readback.success',
            'CRM invoice status returned to Salesflow DMS.',
            [
                'request_id' => (string) ($validation['request_id'] ?? ''),
                'crm_invoice_id' => $crmInvoiceId,
                'status' => (string) ($payload['status'] ?? ''),
            ],
            (int) ($tenant['id'] ?? 0),
            (int) ($connection['id'] ?? 0),
            self::DMS_PROVIDER_CODE
        );

        $this->successResponse('CRM_INVOICE_FOUND', 'CRM invoice found.', $payload);
    }

    private function handleCrmPaymentReadback(int $crmInvoiceId): void
    {
        if (strtolower((string) $this->input->method()) !== 'get') {
            $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);

            return;
        }

        if ($crmInvoiceId <= 0) {
            $this->errorResponse('VALIDATION_ERROR', 'crm_invoice_id khong hop le.', 422, [
                'crm_invoice_id' => ['crm_invoice_id must be a positive integer.'],
            ]);

            return;
        }

        $path = '/api/v1/crm/payments/' . $crmInvoiceId;
        [$tenant, $connection, $validation] = $this->validateSalesflowRequest($path, '', 'GET', false);
        if (!$tenant || !$connection || !$validation) {
            return;
        }

        $this->activateTenantDatabaseForBridge($tenant);
        $service = new CrmPaymentReadbackService();
        $payload = $service->getPaymentReadbackPayload($crmInvoiceId, (int) ($tenant['id'] ?? 0));
        if (!is_array($payload)) {
            $code = $service->hasInvoice($crmInvoiceId) ? 'PAYMENT_NOT_FOUND' : 'INVOICE_NOT_FOUND';
            $message = $code === 'PAYMENT_NOT_FOUND'
                ? 'CRM payment not found.'
                : 'CRM invoice not found.';

            $this->Kt_integration_model->log(
                'warning',
                'crm.payment.readback.not_found',
                $message,
                [
                    'request_id' => (string) ($validation['request_id'] ?? ''),
                    'crm_invoice_id' => $crmInvoiceId,
                    'code' => $code,
                ],
                (int) ($tenant['id'] ?? 0),
                (int) ($connection['id'] ?? 0),
                self::DMS_PROVIDER_CODE
            );
            $this->errorResponse($code, $message, 404);

            return;
        }

        $this->Kt_integration_model->log(
            'info',
            'crm.payment.readback.success',
            'CRM payment status returned to Salesflow DMS.',
            [
                'request_id' => (string) ($validation['request_id'] ?? ''),
                'crm_invoice_id' => $crmInvoiceId,
                'payment_status' => (string) ($payload['payment_status'] ?? ''),
            ],
            (int) ($tenant['id'] ?? 0),
            (int) ($connection['id'] ?? 0),
            self::DMS_PROVIDER_CODE
        );

        $this->successResponse('CRM_PAYMENT_FOUND', 'CRM payment found.', $payload);
    }

    private function handleDmsCheckins(): void
    {
        if (strtolower((string) $this->input->method()) !== 'post') {
            $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);

            return;
        }

        [$tenant, $connection, $validation] = $this->validateSalesflowRequest('/api/v1/dms/checkins', $this->input->raw_input_stream);
        if (!$tenant || !$connection || !$validation) {
            return;
        }

        $payload = json_decode((string) $this->input->raw_input_stream, true);
        if (!is_array($payload)) {
            $this->errorResponse('VALIDATION_ERROR', 'Payload JSON khong hop le.', 422, [
                'body' => ['Payload must be valid JSON.'],
            ]);

            return;
        }

        $errors = $this->validateDmsCheckinPayload($payload);
        if ($errors !== []) {
            $this->errorResponse('VALIDATION_ERROR', 'Payload khong hop le.', 422, $errors);

            return;
        }

        $tenantSection = (array) ($payload['tenant'] ?? []);
        if ((int) ($tenantSection['crm_tenant_id'] ?? 0) !== (int) ($tenant['id'] ?? 0)
            || strtoupper(trim((string) ($tenantSection['tenant_code'] ?? ''))) !== strtoupper(trim((string) ($tenant['tenant_code'] ?? '')))) {
            $this->errorResponse('TENANT_SCOPE_MISMATCH', 'Payload tenant context does not match CRM tenant runtime.', 409);

            return;
        }

        $processor = new DmsCheckinBridgeProcessor();
        $result = $processor->handle($tenant, $connection, $payload, [
            'request_id' => (string) ($validation['request_id'] ?? ''),
            'idempotency_key' => (string) ($validation['idempotency_key'] ?? ''),
        ]);
        $this->dmsContractStore->recordHealth((int) $tenant['id'], (int) ($payload['tenant']['crm_company_id'] ?? $tenant['id']), 'checkin', (string) ($payload['checkin']['dms_checkin_id'] ?? 0), 'synced', null, null, null, (string) ($validation['request_id'] ?? ''));

        $this->successResponse((string) ($result['code'] ?? 'CHECKIN_EVENT_SYNCED'), (string) ($result['message'] ?? 'Check-in event synchronized.'), $result);
    }

    private function handleDmsVisits(): void
    {
        if (strtolower((string) $this->input->method()) !== 'post') {
            $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);

            return;
        }

        [$tenant, $connection, $validation] = $this->validateSalesflowRequest('/api/v1/dms/visits', $this->input->raw_input_stream);
        if (! $tenant || ! $connection || ! $validation) {
            return;
        }

        $payload = json_decode((string) $this->input->raw_input_stream, true);
        if (! is_array($payload)) {
            $this->errorResponse('VALIDATION_ERROR', 'Payload JSON khong hop le.', 422, [
                'body' => ['Payload must be valid JSON.'],
            ]);

            return;
        }

        $errors = $this->validateDmsVisitPayload($payload);
        if ($errors !== []) {
            $this->errorResponse('VALIDATION_ERROR', 'Payload khong hop le.', 422, $errors);

            return;
        }

        $tenantSection = (array) ($payload['tenant'] ?? []);
        if ((int) ($tenantSection['crm_tenant_id'] ?? 0) !== (int) ($tenant['id'] ?? 0)
            || strtoupper(trim((string) ($tenantSection['tenant_code'] ?? ''))) !== strtoupper(trim((string) ($tenant['tenant_code'] ?? '')))) {
            $this->errorResponse('TENANT_SCOPE_MISMATCH', 'Payload tenant context does not match CRM tenant runtime.', 409);

            return;
        }

        $processor = new DmsVisitBridgeProcessor();
        $result = $processor->handle($tenant, $connection, $payload, [
            'request_id' => (string) ($validation['request_id'] ?? ''),
            'idempotency_key' => (string) ($validation['idempotency_key'] ?? ''),
        ]);
        $this->dmsContractStore->recordHealth((int) $tenant['id'], (int) ($payload['tenant']['crm_company_id'] ?? $tenant['id']), 'visit', (string) ($payload['visit']['visit_id'] ?? $payload['visit']['dms_visit_id'] ?? 0), 'synced', null, null, null, (string) ($validation['request_id'] ?? ''));

        $this->successResponse((string) ($result['code'] ?? 'VISIT_ACCEPTED'), (string) ($result['message'] ?? 'Visit summary synchronized.'), $result);
    }

    private function handleDmsFieldFeedback(): void
    {
        if (strtolower((string) $this->input->method()) !== 'post') {
            $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);

            return;
        }

        [$tenant, $connection, $validation] = $this->validateSalesflowRequest('/api/v1/dms/field-feedback', $this->input->raw_input_stream);
        if (! $tenant || ! $connection || ! $validation) {
            return;
        }

        $payload = json_decode((string) $this->input->raw_input_stream, true);
        if (! is_array($payload)) {
            $this->errorResponse('VALIDATION_ERROR', 'Payload JSON khong hop le.', 422, [
                'body' => ['Payload must be valid JSON.'],
            ]);

            return;
        }

        $tenantSection = (array) ($payload['tenant'] ?? []);
        if ((int) ($tenantSection['crm_tenant_id'] ?? 0) !== (int) ($tenant['id'] ?? 0)
            || strtoupper(trim((string) ($tenantSection['tenant_code'] ?? ''))) !== strtoupper(trim((string) ($tenant['tenant_code'] ?? '')))) {
            $this->errorResponse('TENANT_SCOPE_MISMATCH', 'Payload tenant context does not match CRM tenant runtime.', 409);

            return;
        }

        $processor = new DmsFieldFeedbackBridgeProcessor();
        $result = $processor->handle($tenant, $connection, $payload, [
            'request_id' => (string) ($validation['request_id'] ?? ''),
            'idempotency_key' => (string) ($validation['idempotency_key'] ?? ''),
        ]);
        $this->dmsContractStore->recordHealth((int) $tenant['id'], (int) ($payload['tenant']['crm_company_id'] ?? $tenant['id']), 'feedback', (string) ($payload['feedback']['feedback_id'] ?? 0), 'synced', null, null, null, (string) ($validation['request_id'] ?? ''));

        $this->successResponse((string) ($result['code'] ?? 'FIELD_FEEDBACK_SYNCED'), (string) ($result['message'] ?? 'Field feedback synchronized.'), $result);
    }

    private function handleDmsLiveTracking(): void
    {
        if (strtolower((string) $this->input->method()) !== 'post') {
            $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);

            return;
        }

        [$tenant, $connection, $validation] = $this->validateSalesflowRequest('/api/v1/dms/live-tracking', $this->input->raw_input_stream);
        if (! $tenant || ! $connection || ! $validation) {
            return;
        }

        $payload = json_decode((string) $this->input->raw_input_stream, true);
        if (! is_array($payload)) {
            $this->errorResponse('VALIDATION_ERROR', 'Payload JSON khong hop le.', 422, [
                'body' => ['Payload must be valid JSON.'],
            ]);

            return;
        }

        $errors = $this->validateDmsLiveTrackingPayload($payload);
        if ($errors !== []) {
            $this->errorResponse('VALIDATION_ERROR', 'Payload khong hop le.', 422, $errors);

            return;
        }

        $tenantSection = (array) ($payload['tenant'] ?? []);
        if ((int) ($tenantSection['crm_tenant_id'] ?? 0) !== (int) ($tenant['id'] ?? 0)
            || strtoupper(trim((string) ($tenantSection['tenant_code'] ?? ''))) !== strtoupper(trim((string) ($tenant['tenant_code'] ?? '')))) {
            $this->errorResponse('TENANT_SCOPE_MISMATCH', 'Payload tenant context does not match CRM tenant runtime.', 409);

            return;
        }

        $processor = new DmsLiveTrackingBridgeProcessor();
        $result = $processor->handle($tenant, $connection, $payload, [
            'request_id' => (string) ($validation['request_id'] ?? ''),
            'idempotency_key' => (string) ($validation['idempotency_key'] ?? ''),
        ]);
        $this->dmsContractStore->recordHealth((int) $tenant['id'], (int) ($payload['tenant']['crm_company_id'] ?? $tenant['id']), 'live_tracking', (string) ($payload['tracking']['dms_user_id'] ?? 0), 'synced', null, null, null, (string) ($validation['request_id'] ?? ''));

        $this->successResponse((string) ($result['code'] ?? 'LIVE_TRACKING_ACCEPTED'), (string) ($result['message'] ?? 'Live tracking snapshot synchronized.'), $result);
    }

    private function handleDmsLiveTrackingReadback(): void
    {
        if (strtolower((string) $this->input->method()) !== 'get') {
            $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);

            return;
        }

        [$tenant, $connection, $validation] = $this->validateSalesflowRequest('/api/v1/dms/live-tracking', '', 'GET', false);
        if (! $tenant || ! $connection || ! $validation) {
            return;
        }

        $filters = [
            'date' => trim((string) $this->input->get('date', true)),
            'staff_id' => (int) ($this->input->get('staff_id', true) ?: $this->input->get('staff', true)),
            'status' => trim((string) $this->input->get('status', true)),
        ];

        $rows = $this->fetchLiveTrackingRows((int) $tenant['id'], $filters);
        $aggregator = new LiveTrackingAggregator();
        $result = $aggregator->aggregate($rows, $filters);

        $this->Kt_integration_model->log(
            'info',
            'live_tracking.snapshot.readback',
            'Live tracking snapshot data fetched.',
            [
                'request_id' => (string) ($validation['request_id'] ?? ''),
                'total_users' => (int) ($result['summary']['total_users'] ?? 0),
                'online_users' => (int) ($result['summary']['online_users'] ?? 0),
                'stale_users' => (int) ($result['summary']['stale_users'] ?? 0),
                'offline_users' => (int) ($result['summary']['offline_users'] ?? 0),
            ],
            (int) $tenant['id'],
            (int) $connection['id'],
            self::DMS_PROVIDER_CODE
        );

        $this->successResponse('LIVE_TRACKING_FOUND', 'Live tracking data fetched.', $result);
    }

    private function handleDmsRoutePerformance(): void
    {
        if (strtolower((string) $this->input->method()) !== 'get') {
            $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);

            return;
        }

        [$tenant, $connection, $validation] = $this->validateSalesflowRequest('/api/v1/dms/route-performance', '', 'GET', false);
        if (! $tenant || ! $connection || ! $validation) {
            return;
        }

        $filters = [
            'date_from' => trim((string) $this->input->get('date_from', true)),
            'date_to' => trim((string) $this->input->get('date_to', true)),
            'staff_id' => (int) $this->input->get('staff_id', true),
            'customer_id' => (int) $this->input->get('customer_id', true),
            'store_id' => (int) $this->input->get('store_id', true),
        ];

        $checkinEvents = $this->fetchRoutePerformanceRows('dms_checkin_events', (int) $tenant['id'], $filters, 'checkin_at', [
            'id',
            'tenant_id',
            'dms_checkin_id',
            'crm_staff_id',
            'crm_customer_id',
            'store_id',
            'checkin_at',
            'status',
            'payload_json',
        ]);
        $visitSnapshots = $this->fetchRoutePerformanceRows('dms_visit_snapshots', (int) $tenant['id'], $filters, 'started_at', [
            'id',
            'tenant_id',
            'visit_id',
            'crm_staff_id',
            'crm_customer_id',
            'store_id',
            'started_at',
            'ended_at',
            'visit_status',
            'payload_json',
        ]);

        $storeIds = $this->loadScopedEntityIds((int) $tenant['id'], 'store');
        $customerIds = $this->loadScopedEntityIds((int) $tenant['id'], 'customer');
        if ($storeIds === []) {
            $storeIds = $this->extractDistinctIds($checkinEvents, $visitSnapshots, 'store_id');
        }
        if ($customerIds === []) {
            $customerIds = $this->extractDistinctIds($checkinEvents, $visitSnapshots, 'crm_customer_id');
        }

        $aggregator = new RoutePerformanceAggregator();
        $result = $aggregator->aggregate($checkinEvents, $visitSnapshots, $storeIds, $customerIds, $filters);
        $aggregator->persistSnapshots((int) $tenant['id'], $result['items'] ?? []);

        $this->Kt_integration_model->log(
            'info',
            'route.performance.aggregated',
            'Route performance dashboard foundation generated.',
            [
                'request_id' => (string) ($validation['request_id'] ?? ''),
                'total_checkins' => (int) ($result['summary']['total_checkins'] ?? 0),
                'total_visits' => (int) ($result['summary']['total_visits'] ?? 0),
            ],
            (int) $tenant['id'],
            (int) $connection['id'],
            self::DMS_PROVIDER_CODE
        );

        $this->successResponse('ROUTE_PERFORMANCE_FOUND', 'Route performance data fetched.', $result);
    }

    private function handleManualReviews(): void
    {
        if (strtolower((string) $this->input->method()) !== 'get') {
            $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);

            return;
        }

        [$tenant, $connection, $validation] = $this->validateSalesflowRequest('/api/v1/manual-reviews', '', 'GET', false);
        if (!$tenant || !$connection || !$validation) {
            return;
        }

        $service = new ManualReviewService();
        $filters = [
            'tenant_id' => (int) ($tenant['id'] ?? 0),
            'entity_type' => trim((string) $this->input->get('entity_type', true)),
            'entity_id' => (int) $this->input->get('entity_id', true),
            'review_status' => trim((string) $this->input->get('review_status', true)),
            'review_type' => trim((string) $this->input->get('review_type', true)),
        ];

        $items = $service->list($filters);

        $this->successResponse('MANUAL_REVIEWS_FOUND', 'Manual reviews fetched.', [
            'items' => $items,
            'total' => count($items),
        ]);
    }

    private function handleDmsCollections(): void
    {
        if (strtolower((string) $this->input->method()) !== 'post') {
            $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);
            return;
        }

        $raw = (string) $this->input->raw_input_stream;
        [$tenant, $connection, $validation] = $this->validateSalesflowRequest('/api/v1/dms/collections', $raw, 'POST', true);
        if (!$tenant || !$connection || !$validation) {
            return;
        }

        $payload = json_decode($raw, true) ?: [];
        $collection = (array) ($payload['collection'] ?? []);
        $companyId = $this->resolveRequestedCompanyId((array) $tenant, (int) ($payload['tenant']['crm_company_id'] ?? 0));
        $collectionId = (int) ($collection['collection_id'] ?? $collection['dms_collection_id'] ?? 0);
        if ($companyId <= 0 || $collectionId <= 0 || trim((string) ($collection['evidence_marker'] ?? $collection['evidence_status'] ?? '')) === '') {
            $this->errorResponse('VALIDATION_ERROR', 'Collection evidence requires tenant scope, collection identity, and evidence marker.', 422);
            return;
        }

        $request = $this->dmsContractStore->beginRequest((int) $tenant['id'], $companyId, 'collection_manual_review', (string) $validation['request_id'], (string) $validation['idempotency_key'], $payload);
        if (!empty($request['duplicate'])) {
            $this->successResponse('IDEMPOTENT_REPLAY', 'Prior collection evidence result returned.', (array) $request['response']);
            return;
        }

        $allowed = [
            'collection_id' => $collectionId,
            'order_id' => (int) ($collection['order_id'] ?? 0),
            'crm_invoice_id' => (int) ($collection['crm_invoice_id'] ?? 0),
            'status' => trim((string) ($collection['status'] ?? 'recorded')),
            'evidence_marker' => trim((string) ($collection['evidence_marker'] ?? $collection['evidence_status'] ?? 'submitted')),
            'exception_reason' => trim((string) ($collection['exception_reason'] ?? '')),
            'recorded_at' => trim((string) ($collection['recorded_at'] ?? kt_integration_hub_now())),
        ];
        $review = (new ManualReviewService())->createReview([
            'tenant_id' => (int) $tenant['id'],
            'entity_type' => 'collection',
            'entity_id' => $collectionId,
            'review_type' => ManualReviewService::TYPE_COLLECTION_EVIDENCE,
            'review_status' => ManualReviewService::STATUS_OPEN,
            'priority' => trim((string) ($collection['exception_reason'] ?? '')) !== '' ? 'MEDIUM' : 'LOW',
            'review_reason' => 'DMS collection evidence requires CRM manual review. No payment, invoice, accounting, or reconciliation is created.',
            'source_system' => self::DMS_PROVIDER_CODE,
            'payload_json' => kt_integration_hub_json_encode(['collection' => $allowed]),
            'suppress_notification' => true,
        ]);

        $this->Kt_integration_model->upsert_provider_entity_link((int) $tenant['id'], self::DMS_PROVIDER_CODE, (int) $connection['id'], 'collection_evidence', (int) $review['id'], (string) $collectionId, ['collection' => $allowed, 'manual_review_id' => (int) $review['id']], 'inbound');
        $this->dmsContractStore->recordHealth((int) $tenant['id'], $companyId, 'collection_evidence', (string) $collectionId, 'synced', (string) $review['id'], null, null, (string) $validation['request_id']);
        $result = ['collection_id' => $collectionId, 'manual_review_id' => (int) $review['id'], 'policy' => 'C2_COLLECTION_EVIDENCE_CRM_MANUAL_REVIEW'];
        $this->dmsContractStore->completeRequest((int) $request['id'], $result);
        $this->successResponse('COLLECTION_EVIDENCE_REVIEW_CREATED', 'Collection evidence recorded for CRM manual review only.', $result);
    }

    private function handleDmsDeliveries(): void
    {
        if (strtolower((string) $this->input->method()) !== 'post') {
            $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);
            return;
        }

        $raw = (string) $this->input->raw_input_stream;
        [$tenant, $connection, $validation] = $this->validateSalesflowRequest('/api/v1/dms/deliveries', $raw, 'POST', true);
        if (!$tenant || !$connection || !$validation) {
            return;
        }

        $payload = json_decode($raw, true) ?: [];
        $delivery = (array) ($payload['delivery'] ?? []);
        $companyId = $this->resolveRequestedCompanyId((array) $tenant, (int) ($payload['tenant']['crm_company_id'] ?? 0));
        $deliveryId = (int) ($delivery['delivery_id'] ?? $delivery['dms_delivery_id'] ?? 0);
        $status = trim((string) ($delivery['status'] ?? ''));
        if ($companyId <= 0 || $deliveryId <= 0 || $status === '') {
            $this->errorResponse('VALIDATION_ERROR', 'Delivery projection requires tenant scope, delivery identity, and status.', 422);
            return;
        }

        $request = $this->dmsContractStore->beginRequest((int) $tenant['id'], $companyId, 'delivery_projection', (string) $validation['request_id'], (string) $validation['idempotency_key'], $payload);
        if (!empty($request['duplicate'])) {
            $this->successResponse('IDEMPOTENT_REPLAY', 'Prior delivery projection result returned.', (array) $request['response']);
            return;
        }

        $projection = [
            'delivery_id' => $deliveryId,
            'order_id' => (int) ($delivery['order_id'] ?? 0),
            'status' => $status,
            'evidence_marker' => trim((string) ($delivery['evidence_marker'] ?? $delivery['evidence_status'] ?? '')),
            'exception_reason' => trim((string) ($delivery['exception_reason'] ?? '')),
            'recorded_at' => trim((string) ($delivery['recorded_at'] ?? $delivery['updated_at'] ?? kt_integration_hub_now())),
            'policy' => 'D3_STATUS_EVIDENCE_EXCEPTION_ONLY',
        ];
        $this->Kt_integration_model->upsert_provider_entity_link((int) $tenant['id'], self::DMS_PROVIDER_CODE, (int) $connection['id'], 'delivery_projection', $deliveryId, (string) $deliveryId, ['delivery' => $projection], 'inbound');
        $this->dmsContractStore->recordHealth((int) $tenant['id'], $companyId, 'delivery_projection', (string) $deliveryId, 'synced', (string) $deliveryId, null, null, (string) $validation['request_id']);
        $this->dmsContractStore->completeRequest((int) $request['id'], $projection);
        $this->successResponse('DELIVERY_PROJECTION_ACCEPTED', 'Delivery status projection accepted without warehouse, fulfillment, or inventory mutation.', $projection);
    }

    private function handleDmsReturns(): void
    {
        if (strtolower((string) $this->input->method()) !== 'post') {
            $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);
            return;
        }

        $raw = (string) $this->input->raw_input_stream;
        [$tenant, $connection, $validation] = $this->validateSalesflowRequest('/api/v1/dms/returns', $raw, 'POST', true);
        if (!$tenant || !$connection || !$validation) {
            return;
        }

        $payload = json_decode($raw, true) ?: [];
        $return = (array) ($payload['return'] ?? []);
        $companyId = $this->resolveRequestedCompanyId((array) $tenant, (int) ($payload['tenant']['crm_company_id'] ?? 0));
        $returnId = (int) ($return['return_id'] ?? $return['dms_return_id'] ?? 0);
        $status = trim((string) ($return['status'] ?? ''));
        if ($companyId <= 0 || $returnId <= 0 || $status === '') {
            $this->errorResponse('VALIDATION_ERROR', 'Return projection requires tenant scope, return identity, and status.', 422);
            return;
        }

        $request = $this->dmsContractStore->beginRequest((int) $tenant['id'], $companyId, 'return_projection', (string) $validation['request_id'], (string) $validation['idempotency_key'], $payload);
        if (!empty($request['duplicate'])) {
            $this->successResponse('IDEMPOTENT_REPLAY', 'Prior return projection result returned.', (array) $request['response']);
            return;
        }

        $projection = [
            'return_id' => $returnId,
            'order_id' => (int) ($return['order_id'] ?? 0),
            'delivery_id' => (int) ($return['delivery_id'] ?? 0),
            'return_request_status' => trim((string) ($return['return_request_status'] ?? $status)),
            'return_receipt_status' => trim((string) ($return['return_receipt_status'] ?? '')),
            'status' => $status,
            'exception_reason' => trim((string) ($return['exception_reason'] ?? '')),
            'recorded_at' => trim((string) ($return['recorded_at'] ?? $return['updated_at'] ?? kt_integration_hub_now())),
            'policy' => 'R3_RETURN_REQUEST_RECEIPT_ONLY',
        ];
        $this->Kt_integration_model->upsert_provider_entity_link((int) $tenant['id'], self::DMS_PROVIDER_CODE, (int) $connection['id'], 'return_projection', $returnId, (string) $returnId, ['return' => $projection], 'inbound');
        $this->dmsContractStore->recordHealth((int) $tenant['id'], $companyId, 'return_projection', (string) $returnId, 'synced', (string) $returnId, null, null, (string) $validation['request_id']);
        $this->dmsContractStore->completeRequest((int) $request['id'], $projection);
        $this->successResponse('RETURN_PROJECTION_ACCEPTED', 'Return request and receipt projection accepted without refund or history rewrite.', $projection);
    }

    private function handleManualReview(int $reviewId): void
    {
        if (strtolower((string) $this->input->method()) !== 'get') {
            $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);

            return;
        }

        if ($reviewId <= 0) {
            $this->errorResponse('VALIDATION_ERROR', 'review_id khong hop le.', 422, [
                'review_id' => ['review_id must be a positive integer.'],
            ]);

            return;
        }

        [$tenant, $connection, $validation] = $this->validateSalesflowRequest('/api/v1/manual-reviews/' . $reviewId, '', 'GET', false);
        if (!$tenant || !$connection || !$validation) {
            return;
        }

        $service = new ManualReviewService();
        $review = $service->find($reviewId, (int) ($tenant['id'] ?? 0));
        if (!is_array($review)) {
            $this->errorResponse('MANUAL_REVIEW_NOT_FOUND', 'Manual review not found.', 404);

            return;
        }

        $this->successResponse('MANUAL_REVIEW_FOUND', 'Manual review found.', $review);
    }

    private function handleManualReviewAction(int $reviewId, string $action): void
    {
        if (strtolower((string) $this->input->method()) !== 'post') {
            $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);

            return;
        }

        if ($reviewId <= 0) {
            $this->errorResponse('VALIDATION_ERROR', 'review_id khong hop le.', 422, [
                'review_id' => ['review_id must be a positive integer.'],
            ]);

            return;
        }

        [$tenant, $connection, $validation] = $this->validateSalesflowRequest('/api/v1/manual-reviews/' . $reviewId . '/' . $action, $this->input->raw_input_stream);
        if (!$tenant || !$connection || !$validation) {
            return;
        }

        $payload = json_decode((string) $this->input->raw_input_stream, true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $service = new ManualReviewService();
        if ($action === 'assign') {
            $review = $service->assignReview($reviewId, (int) ($payload['assigned_staff_id'] ?? 0));
            $code = 'MANUAL_REVIEW_ASSIGNED';
            $message = 'Manual review assigned.';
        } elseif ($action === 'resolve') {
            $review = $service->resolveReview($reviewId, (string) ($payload['resolution_note'] ?? ''), isset($payload['resolved_by']) ? (int) $payload['resolved_by'] : null);
            $code = 'MANUAL_REVIEW_RESOLVED';
            $message = 'Manual review resolved.';
        } else {
            $review = $service->closeReview($reviewId);
            $code = 'MANUAL_REVIEW_CLOSED';
            $message = 'Manual review closed.';
        }

        $this->successResponse($code, $message, $review);
    }

    private function handleNotifications(): void
    {
        if (strtolower((string) $this->input->method()) !== 'get') {
            $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);

            return;
        }

        [$tenant, $connection, $validation] = $this->validateSalesflowRequest('/api/v1/notifications', '', 'GET', false);
        if (!$tenant || !$connection || !$validation) {
            return;
        }

        $service = new NotificationBridgeService();
        $items = $service->list([
            'tenant_id' => (int) ($tenant['id'] ?? 0),
        ]);

        $this->successResponse('NOTIFICATIONS_FOUND', 'Notifications fetched.', [
            'items' => $items,
            'total' => count($items),
        ]);
    }

    private function handleNotification(int $notificationId): void
    {
        if (strtolower((string) $this->input->method()) !== 'get') {
            $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);

            return;
        }

        if ($notificationId <= 0) {
            $this->errorResponse('VALIDATION_ERROR', 'notification_id khong hop le.', 422, [
                'notification_id' => ['notification_id must be a positive integer.'],
            ]);

            return;
        }

        [$tenant, $connection, $validation] = $this->validateSalesflowRequest('/api/v1/notifications/' . $notificationId, '', 'GET', false);
        if (!$tenant || !$connection || !$validation) {
            return;
        }

        $service = new NotificationBridgeService();
        $notification = $service->find($notificationId, (int) ($tenant['id'] ?? 0));
        if (! $notification) {
            $this->Kt_integration_model->log(
                'warning',
                'crm.notification.not_found',
                'CRM notification was not found.',
                [
                    'request_id' => (string) ($validation['request_id'] ?? ''),
                    'notification_id' => $notificationId,
                ],
                (int) ($tenant['id'] ?? 0),
                (int) ($connection['id'] ?? 0),
                self::DMS_PROVIDER_CODE
            );
            $this->errorResponse('NOTIFICATION_NOT_FOUND', 'CRM notification not found.', 404);

            return;
        }

        $this->Kt_integration_model->log(
            'info',
            'crm.notification.readback.success',
            'CRM notification returned to Salesflow DMS.',
            [
                'request_id' => (string) ($validation['request_id'] ?? ''),
                'notification_id' => $notificationId,
            ],
            (int) ($tenant['id'] ?? 0),
            (int) ($connection['id'] ?? 0),
            self::DMS_PROVIDER_CODE
        );

        $this->successResponse('NOTIFICATION_FOUND', 'Notification fetched.', $notification);
    }

    private function handleNotificationAction(int $notificationId, string $action): void
    {
        if (strtolower((string) $this->input->method()) !== 'post') {
            $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);

            return;
        }

        if ($notificationId <= 0) {
            $this->errorResponse('VALIDATION_ERROR', 'notification_id khong hop le.', 422, [
                'notification_id' => ['notification_id must be a positive integer.'],
            ]);

            return;
        }

        [$tenant, $connection, $validation] = $this->validateSalesflowRequest('/api/v1/notifications/' . $notificationId . '/' . $action, $this->input->raw_input_stream);
        if (!$tenant || !$connection || !$validation) {
            return;
        }

        $service = new NotificationBridgeService();
        try {
            if ($action === 'ack') {
                $notification = $service->ack($notificationId, (int) ($tenant['id'] ?? 0));
                $code = 'NOTIFICATION_ACKED';
                $message = 'Notification acknowledged.';
            } else {
                $notification = $service->markRead($notificationId, (int) ($tenant['id'] ?? 0));
                $code = 'NOTIFICATION_READ';
                $message = 'Notification marked as read.';
            }
        } catch (Throwable $e) {
            $this->errorResponse('NOTIFICATION_NOT_FOUND', $e->getMessage(), 404);

            return;
        }

        $this->Kt_integration_model->log(
            'info',
            'crm.notification.status_changed',
            $message,
            [
                'request_id' => (string) ($validation['request_id'] ?? ''),
                'notification_id' => $notificationId,
                'action' => $action,
            ],
            (int) ($tenant['id'] ?? 0),
            (int) ($connection['id'] ?? 0),
            self::DMS_PROVIDER_CODE
        );

        $this->successResponse($code, $message, $notification);
    }

    private function handleDmsOrderCancellation(int $routeOrderId): void
    {
        if (strtolower((string) $this->input->method()) !== 'post') {
            $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);

            return;
        }

        if ($routeOrderId <= 0) {
            $this->errorResponse('VALIDATION_ERROR', 'order_id khong hop le.', 422, [
                'order.order_id' => ['order_id must be a positive integer.'],
            ]);

            return;
        }

        $path = '/api/v1/dms/orders/' . $routeOrderId . '/cancel';
        [$tenant, $connection, $validation] = $this->validateSalesflowRequest($path, $this->input->raw_input_stream);
        if (!$tenant || !$connection || !$validation) {
            return;
        }

        $payload = json_decode((string) $this->input->raw_input_stream, true);
        if (!is_array($payload)) {
            $this->errorResponse('VALIDATION_ERROR', 'Payload JSON khong hop le.', 422, [
                'body' => ['Payload must be valid JSON.'],
            ]);

            return;
        }

        $errors = $this->validateDmsOrderCancellationPayload($payload, $routeOrderId);
        if ($errors !== []) {
            $this->errorResponse('VALIDATION_ERROR', 'Payload khong hop le.', 422, $errors);

            return;
        }

        $tenantSection = (array) ($payload['tenant'] ?? []);
        if ((int) ($tenantSection['crm_tenant_id'] ?? 0) !== (int) ($tenant['id'] ?? 0)
            || strtoupper(trim((string) ($tenantSection['tenant_code'] ?? ''))) !== strtoupper(trim((string) ($tenant['tenant_code'] ?? '')))) {
            $this->errorResponse('TENANT_SCOPE_MISMATCH', 'Payload tenant context does not match CRM tenant runtime.', 409);

            return;
        }

        $processor = new DmsOrderBridgeProcessor();
        $result = $processor->handleCancellation($tenant, $connection, $payload, [
            'request_id' => (string) ($validation['request_id'] ?? ''),
            'idempotency_key' => (string) ($validation['idempotency_key'] ?? ''),
        ]);

        $this->successResponse((string) ($result['code'] ?? 'INVOICE_DRAFT_CANCELLED'), (string) ($result['message'] ?? 'Order cancellation processed.'), $result);
    }

    private function validateSalesflowRequest(string $path, string $rawBody, string $method = 'POST', bool $requireIdempotency = true): array
    {
        $headers = $this->requestHeaders();
        $tenantCode = strtoupper(trim((string) ($headers['x-tenant-code'] ?? $headers['X-Tenant-Code'] ?? '')));
        if ($tenantCode === '') {
            $this->errorResponse('TENANT_CODE_REQUIRED', 'Tenant code is required.', 422);

            return [null, null, null];
        }

        $bearerToken = $this->extractBearerToken($headers);
        if ($bearerToken === '') {
            $this->errorResponse('INVALID_TOKEN', 'Missing bearer token.', 401);

            return [null, null, null];
        }

        $connection = $this->Kt_integration_model->get_connection_by_public_key(self::DMS_PROVIDER_CODE, $bearerToken);
        if (!$connection) {
            $this->errorResponse('INVALID_TOKEN', 'Connection token is invalid.', 401);

            return [null, null, null];
        }

        $tenant = $this->db
            ->where('tenant_code', $tenantCode)
            ->where('deleted_at IS NULL', null, false)
            ->get(db_prefix() . 'kt_saas_tenants')
            ->row_array();

        if (!$tenant) {
            $this->errorResponse('TENANT_NOT_FOUND', 'Tenant not found.', 404);

            return [null, null, null];
        }

        if ((int) ($connection['tenant_id'] ?? 0) !== (int) ($tenant['id'] ?? 0)) {
            $this->errorResponse('TENANT_SCOPE_MISMATCH', 'Connection does not belong to this tenant.', 409);

            return [null, null, null];
        }

        $validator = new KtIntegrationSignedRequestValidator();
        $secret = kt_integration_hub_decrypt_value($connection['webhook_secret_encrypted'] ?? '');
        $validation = $validator->validate(
            $headers,
            strtoupper($method),
            $path,
            $rawBody,
            (string) ($connection['public_key'] ?? ''),
            $secret,
            (string) ($tenant['tenant_code'] ?? ''),
            function ($requestId) use ($tenant) {
                return $this->Kt_integration_model->has_recent_request_log(
                    self::DMS_PROVIDER_CODE,
                    (int) ($tenant['id'] ?? 0),
                    (string) $requestId,
                    'api.request.accepted',
                    10
                );
            },
            $requireIdempotency
        );

        if (empty($validation['success'])) {
            $status = in_array((string) ($validation['code'] ?? ''), ['TENANT_SCOPE_MISMATCH', 'IDEMPOTENCY_KEY_REUSED'], true) ? 409 : 401;
            if (in_array((string) ($validation['code'] ?? ''), ['REQUEST_ID_REQUIRED', 'IDEMPOTENCY_KEY_REQUIRED'], true)) {
                $status = 422;
            }

            $this->Kt_integration_model->log(
                'warning',
                'api.request.rejected',
                (string) ($validation['message'] ?? 'Request rejected.'),
                [
                    'request_id' => (string) ($headers['x-request-id'] ?? $headers['X-Request-Id'] ?? ''),
                    'tenant_code' => $tenantCode,
                    'idempotency_key' => (string) ($headers['x-idempotency-key'] ?? $headers['X-Idempotency-Key'] ?? ''),
                    'code' => (string) ($validation['code'] ?? 'INVALID_REQUEST'),
                ],
                (int) ($tenant['id'] ?? 0),
                (int) ($connection['id'] ?? 0),
                self::DMS_PROVIDER_CODE
            );
            $this->errorResponse((string) ($validation['code'] ?? 'UNAUTHORIZED'), (string) ($validation['message'] ?? 'Unauthorized.'), $status);

            return [null, null, null];
        }

        $this->Kt_integration_model->log(
            'info',
            'api.request.accepted',
            'Signed DMS bridge request accepted.',
            [
                'request_id' => (string) ($validation['request_id'] ?? ''),
                'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
                'idempotency_key' => (string) ($validation['idempotency_key'] ?? ''),
                'checksum' => sha1((string) $rawBody),
                'path' => $path,
            ],
            (int) ($tenant['id'] ?? 0),
            (int) ($connection['id'] ?? 0),
            self::DMS_PROVIDER_CODE
        );

        return [$tenant, $connection, $validation];
    }

    private function validatePlatformProviderMirrorRequest(string $rawBody): ?array
    {
        $headers = $this->requestHeaders();
        $bearerToken = $this->extractBearerToken($headers);
        if ($bearerToken === '') {
            $this->errorResponse('INVALID_TOKEN', 'Missing bearer token.', 401);
            return null;
        }

        $tenantCode = strtoupper(trim((string) ($headers['x-tenant-code'] ?? $headers['X-Tenant-Code'] ?? '')));
        if ($tenantCode === '') {
            $this->errorResponse('TENANT_CODE_REQUIRED', 'Tenant code is required.', 422);
            return null;
        }

        $pairs = $this->workspaceDiscoveryCredentialPairs([]);
        $matchedSecret = '';
        foreach ($pairs as $pair) {
            $expectedToken = trim((string) ($pair['token'] ?? ''));
            $candidateSecret = trim((string) ($pair['secret'] ?? ''));
            if ($expectedToken !== '' && $candidateSecret !== '' && hash_equals($expectedToken, $bearerToken)) {
                $matchedSecret = $candidateSecret;
                break;
            }
        }

        if ($matchedSecret === '') {
            $this->errorResponse('INVALID_TOKEN', 'Platform mirror token is invalid.', 401);
            return null;
        }

        $validator = new KtIntegrationSignedRequestValidator();
        $validation = $validator->validate(
            $headers,
            'POST',
            self::PLATFORM_PROVIDER_MIRROR_PATH,
            $rawBody,
            $bearerToken,
            $matchedSecret,
            $tenantCode,
            function ($requestId) {
                return $this->Kt_integration_model->has_recent_request_log(
                    self::DMS_PROVIDER_CODE,
                    0,
                    (string) $requestId,
                    'api.platform_provider_mirror.accepted',
                    10
                );
            },
            true
        );

        if (empty($validation['success'])) {
            $status = in_array((string) ($validation['code'] ?? ''), ['REQUEST_ID_REQUIRED', 'IDEMPOTENCY_KEY_REQUIRED'], true) ? 422 : 401;
            $this->Kt_integration_model->log(
                'warning',
                'api.platform_provider_mirror.rejected',
                (string) ($validation['message'] ?? 'Provider mirror request rejected.'),
                [
                    'request_id' => (string) ($headers['x-request-id'] ?? $headers['X-Request-Id'] ?? ''),
                    'tenant_code' => $tenantCode,
                    'idempotency_key' => (string) ($headers['x-idempotency-key'] ?? $headers['X-Idempotency-Key'] ?? ''),
                    'code' => (string) ($validation['code'] ?? 'INVALID_REQUEST'),
                ],
                null,
                null,
                self::DMS_PROVIDER_CODE
            );
            $this->errorResponse((string) ($validation['code'] ?? 'UNAUTHORIZED'), (string) ($validation['message'] ?? 'Unauthorized.'), $status);
            return null;
        }

        return $validation;
    }

    private function sanitizeProviderMirrorRows(array $rows): array
    {
        $allowed = ['google_maps', 'firebase', 'storage', 'mail', 'crm_bridge'];
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $provider = trim((string) ($row['provider'] ?? ''));
            if (!in_array($provider, $allowed, true)) {
                continue;
            }
            $result[] = [
                'provider' => $provider,
                'driver' => trim((string) ($row['driver'] ?? '')),
                'status' => trim((string) ($row['status'] ?? '')),
                'health_status' => trim((string) ($row['health_status'] ?? '')),
                'last_checked_at' => trim((string) ($row['last_checked_at'] ?? '')),
                'fingerprint' => trim((string) ($row['fingerprint'] ?? '')),
                'source' => 'salesrep_platform',
            ];
        }

        return $result;
    }

    private function sanitizeProviderBrowserRuntime(array $runtime): array
    {
        $maps = (array) ($runtime['google_maps'] ?? []);
        $domains = [];
        foreach ((array) ($maps['allowed_domains'] ?? []) as $domain) {
            $domain = strtolower(trim((string) $domain));
            if ($domain !== '' && preg_match('/^(?:\*\.)?[a-z0-9.-]+$/', $domain)) {
                $domains[] = $domain;
            }
        }

        return [
            'google_maps' => [
                'browser_key' => trim((string) ($maps['browser_key'] ?? '')),
                'allowed_domains' => array_values(array_unique($domains)),
                'features' => (array) ($maps['features'] ?? []),
                'fingerprint' => trim((string) ($maps['fingerprint'] ?? '')),
            ],
        ];
    }

    private function activateTenantDatabaseForBridge(array $tenant): void
    {
        $dbName = trim((string) ($tenant['db_name'] ?? ''));
        $dbHost = trim((string) ($tenant['db_host'] ?? ''));
        $dbUser = trim((string) ($tenant['db_user'] ?? ''));
        $dbPort = trim((string) ($tenant['db_port'] ?? '3306'));
        $password = '';

        if ($dbName === '' || $dbHost === '' || $dbUser === '') {
            throw new RuntimeException('Tenant database context is incomplete.');
        }

        if (! empty($tenant['db_password_encrypted'])) {
            $password = (string) $this->encryption->decrypt($tenant['db_password_encrypted']);
        }

        $config = [
            'dsn' => '',
            'hostname' => $dbHost,
            'username' => $dbUser,
            'password' => $password,
            'database' => $dbName,
            'dbdriver' => defined('APP_DB_DRIVER') ? APP_DB_DRIVER : 'mysqli',
            'dbprefix' => db_prefix(),
            'pconnect' => false,
            'db_debug' => false,
            'cache_on' => false,
            'cachedir' => '',
            'char_set' => defined('APP_DB_CHARSET') ? APP_DB_CHARSET : 'utf8mb4',
            'dbcollat' => defined('APP_DB_COLLATION') ? APP_DB_COLLATION : 'utf8mb4_unicode_ci',
            'swap_pre' => '',
            'encrypt' => false,
            'compress' => false,
            'stricton' => false,
            'failover' => [],
            'save_queries' => true,
            'port' => $dbPort,
        ];

        $landlordDb = $this->config->item('kt_saas_landlord_db') ?: $this->db;
        $tenantDb = $this->load->database($config, true);
        if (! $tenantDb || ! $tenantDb->initialize()) {
            throw new RuntimeException('Tenant database is unavailable.');
        }

        $this->config->set_item('kt_saas_landlord_db', $landlordDb);
        $this->db = $tenantDb;
    }

    private function validateDmsOrderPayload(array $payload): array
    {
        $errors = [];
        $tenant = (array) ($payload['tenant'] ?? []);
        $order = (array) ($payload['order'] ?? []);
        $items = array_values((array) ($order['items'] ?? []));

        if ((int) ($tenant['crm_tenant_id'] ?? 0) <= 0) {
            $errors['tenant.crm_tenant_id'][] = 'crm_tenant_id is required.';
        }
        if ((int) ($tenant['crm_company_id'] ?? 0) <= 0) {
            $errors['tenant.crm_company_id'][] = 'crm_company_id is required.';
        }
        if (trim((string) ($tenant['tenant_code'] ?? '')) === '') {
            $errors['tenant.tenant_code'][] = 'tenant_code is required.';
        }
        if ((int) ($order['order_id'] ?? 0) <= 0) {
            $errors['order.order_id'][] = 'order_id is required.';
        }
        if (trim((string) ($order['order_number'] ?? '')) === '') {
            $errors['order.order_number'][] = 'order_number is required.';
        }
        if ((int) ($order['crm_staff_id'] ?? 0) <= 0) {
            $errors['order.crm_staff_id'][] = 'crm_staff_id is required.';
        }
        if ((int) ($order['crm_customer_id'] ?? 0) <= 0) {
            $errors['order.crm_customer_id'][] = 'crm_customer_id is required.';
        }
        if ((int) ($order['store_id'] ?? 0) <= 0) {
            $errors['order.store_id'][] = 'store_id is required.';
        }
        if (trim((string) ($order['ordered_at'] ?? '')) === '') {
            $errors['order.ordered_at'][] = 'ordered_at is required.';
        }
        if (trim((string) ($order['currency'] ?? '')) === '') {
            $errors['order.currency'][] = 'currency is required.';
        }
        if (!isset($order['grand_total']) || !is_numeric($order['grand_total'])) {
            $errors['order.grand_total'][] = 'grand_total must be numeric.';
        }
        if (trim((string) ($order['updated_at'] ?? '')) === '') {
            $errors['order.updated_at'][] = 'updated_at is required.';
        }
        if ($items === []) {
            $errors['order.items'][] = 'At least one order item is required.';
        }

        foreach ($items as $index => $item) {
            if ((int) ($item['crm_product_id'] ?? 0) <= 0) {
                $errors['order.items.' . $index . '.crm_product_id'][] = 'crm_product_id is required.';
            }
            $qty = $item['qty'] ?? $item['quantity'] ?? null;
            if (!is_numeric($qty) || (float) $qty <= 0) {
                $errors['order.items.' . $index . '.qty'][] = 'qty must be greater than zero.';
            }
        }

        return $errors;
    }

    private function validateDmsOrderCancellationPayload(array $payload, int $routeOrderId): array
    {
        $errors = [];
        $tenant = (array) ($payload['tenant'] ?? []);
        $order = (array) ($payload['order'] ?? []);

        if ((int) ($tenant['crm_tenant_id'] ?? 0) <= 0) {
            $errors['tenant.crm_tenant_id'][] = 'crm_tenant_id is required.';
        }
        if ((int) ($tenant['crm_company_id'] ?? 0) <= 0) {
            $errors['tenant.crm_company_id'][] = 'crm_company_id is required.';
        }
        if (trim((string) ($tenant['tenant_code'] ?? '')) === '') {
            $errors['tenant.tenant_code'][] = 'tenant_code is required.';
        }
        if ((int) ($order['order_id'] ?? 0) <= 0) {
            $errors['order.order_id'][] = 'order_id is required.';
        } elseif ((int) ($order['order_id'] ?? 0) !== $routeOrderId) {
            $errors['order.order_id'][] = 'order_id must match route parameter.';
        }
        if (trim((string) ($order['order_number'] ?? '')) === '') {
            $errors['order.order_number'][] = 'order_number is required.';
        }
        if ((int) ($order['crm_invoice_id'] ?? 0) <= 0) {
            $errors['order.crm_invoice_id'][] = 'crm_invoice_id is required.';
        }
        if (strtolower(trim((string) ($order['status'] ?? ''))) !== 'cancelled') {
            $errors['order.status'][] = 'Only cancelled DMS orders can request cancellation.';
        }
        if (trim((string) ($order['updated_at'] ?? '')) === '') {
            $errors['order.updated_at'][] = 'updated_at is required.';
        }

        return $errors;
    }

    private function validateDmsCheckinPayload(array $payload): array
    {
        $errors = [];
        $tenant = (array) ($payload['tenant'] ?? []);
        $checkin = (array) ($payload['checkin'] ?? []);

        if ((int) ($tenant['crm_tenant_id'] ?? 0) <= 0) {
            $errors['tenant.crm_tenant_id'][] = 'crm_tenant_id is required.';
        }
        if ((int) ($tenant['crm_company_id'] ?? 0) <= 0) {
            $errors['tenant.crm_company_id'][] = 'crm_company_id is required.';
        }
        if (trim((string) ($tenant['tenant_code'] ?? '')) === '') {
            $errors['tenant.tenant_code'][] = 'tenant_code is required.';
        }
        if ((int) ($checkin['dms_checkin_id'] ?? 0) <= 0) {
            $errors['checkin.dms_checkin_id'][] = 'dms_checkin_id is required.';
        }
        if ((int) ($checkin['crm_staff_id'] ?? 0) <= 0) {
            $errors['checkin.crm_staff_id'][] = 'crm_staff_id is required.';
        }
        if ((int) ($checkin['crm_customer_id'] ?? 0) <= 0) {
            $errors['checkin.crm_customer_id'][] = 'crm_customer_id is required.';
        }
        if ((int) ($checkin['store_id'] ?? 0) <= 0) {
            $errors['checkin.store_id'][] = 'store_id is required.';
        }
        if (trim((string) ($checkin['checkin_at'] ?? '')) === '') {
            $errors['checkin.checkin_at'][] = 'checkin_at is required.';
        }
        if (trim((string) ($checkin['latitude'] ?? '')) === '') {
            $errors['checkin.latitude'][] = 'latitude is required.';
        }
        if (trim((string) ($checkin['longitude'] ?? '')) === '') {
            $errors['checkin.longitude'][] = 'longitude is required.';
        }
        if (trim((string) ($checkin['status'] ?? '')) === '') {
            $errors['checkin.status'][] = 'status is required.';
        } elseif (!in_array(strtolower(trim((string) $checkin['status'])), ['valid', 'warning', 'invalid'], true)) {
            $errors['checkin.status'][] = 'status must be valid, warning, or invalid.';
        }

        if (! empty($checkin['route_id']) && (int) $checkin['route_id'] <= 0) {
            $errors['checkin.route_id'][] = 'route_id must be a positive integer when provided.';
        }

        if (isset($checkin['accuracy_meter']) && $checkin['accuracy_meter'] !== null && ! is_numeric($checkin['accuracy_meter'])) {
            $errors['checkin.accuracy_meter'][] = 'accuracy_meter must be numeric.';
        }
        if (isset($checkin['distance_from_store_meter']) && $checkin['distance_from_store_meter'] !== null && ! is_numeric($checkin['distance_from_store_meter'])) {
            $errors['checkin.distance_from_store_meter'][] = 'distance_from_store_meter must be numeric.';
        }

        return $errors;
    }

    private function validateDmsVisitPayload(array $payload): array
    {
        $errors = [];
        $tenant = (array) ($payload['tenant'] ?? []);
        $visit = (array) ($payload['visit'] ?? []);

        if ((int) ($tenant['crm_tenant_id'] ?? 0) <= 0) {
            $errors['tenant.crm_tenant_id'][] = 'crm_tenant_id is required.';
        }
        if ((int) ($tenant['crm_company_id'] ?? 0) <= 0) {
            $errors['tenant.crm_company_id'][] = 'crm_company_id is required.';
        }
        if (trim((string) ($tenant['tenant_code'] ?? '')) === '') {
            $errors['tenant.tenant_code'][] = 'tenant_code is required.';
        }
        if ((int) ($visit['visit_id'] ?? 0) <= 0) {
            $errors['visit.visit_id'][] = 'visit_id is required.';
        }
        if ((int) ($visit['crm_staff_id'] ?? 0) <= 0) {
            $errors['visit.crm_staff_id'][] = 'crm_staff_id is required.';
        }
        if ((int) ($visit['crm_customer_id'] ?? 0) <= 0) {
            $errors['visit.crm_customer_id'][] = 'crm_customer_id is required.';
        }
        if ((int) ($visit['store_id'] ?? 0) <= 0) {
            $errors['visit.store_id'][] = 'store_id is required.';
        }
        if (trim((string) ($visit['checkin_at'] ?? '')) === '') {
            $errors['visit.checkin_at'][] = 'checkin_at is required.';
        }
        if (trim((string) ($visit['completion_status'] ?? '')) === '') {
            $errors['visit.completion_status'][] = 'completion_status is required.';
        } elseif (! in_array(strtolower(trim((string) $visit['completion_status'])), ['completed', 'partial', 'cancelled'], true)) {
            $errors['visit.completion_status'][] = 'completion_status must be completed, partial, or cancelled.';
        }
        if (trim((string) ($visit['updated_at'] ?? '')) === '') {
            $errors['visit.updated_at'][] = 'updated_at is required.';
        }
        if (isset($visit['route_id']) && $visit['route_id'] !== null && (int) $visit['route_id'] <= 0) {
            $errors['visit.route_id'][] = 'route_id must be a positive integer when provided.';
        }
        if (isset($visit['duration_minutes']) && $visit['duration_minutes'] !== null && ! is_numeric($visit['duration_minutes'])) {
            $errors['visit.duration_minutes'][] = 'duration_minutes must be numeric.';
        }
        if (isset($visit['photo_count']) && $visit['photo_count'] !== null && ! is_numeric($visit['photo_count'])) {
            $errors['visit.photo_count'][] = 'photo_count must be numeric.';
        }
        if (isset($visit['note_count']) && $visit['note_count'] !== null && ! is_numeric($visit['note_count'])) {
            $errors['visit.note_count'][] = 'note_count must be numeric.';
        }

        return $errors;
    }

    private function validateDmsLiveTrackingPayload(array $payload): array
    {
        $errors = [];
        $tenant = (array) ($payload['tenant'] ?? []);
        $tracking = (array) ($payload['tracking'] ?? []);

        if ((int) ($tenant['crm_tenant_id'] ?? 0) <= 0) {
            $errors['tenant.crm_tenant_id'][] = 'crm_tenant_id is required.';
        }
        if ((int) ($tenant['crm_company_id'] ?? 0) <= 0) {
            $errors['tenant.crm_company_id'][] = 'crm_company_id is required.';
        }
        if (trim((string) ($tenant['tenant_code'] ?? '')) === '') {
            $errors['tenant.tenant_code'][] = 'tenant_code is required.';
        }
        if ((int) ($tracking['dms_user_id'] ?? 0) <= 0) {
            $errors['tracking.dms_user_id'][] = 'dms_user_id is required.';
        }
        if ((int) ($tracking['crm_staff_id'] ?? 0) <= 0) {
            $errors['tracking.crm_staff_id'][] = 'crm_staff_id is required.';
        }
        if (trim((string) ($tracking['latitude'] ?? '')) === '') {
            $errors['tracking.latitude'][] = 'latitude is required.';
        }
        if (trim((string) ($tracking['longitude'] ?? '')) === '') {
            $errors['tracking.longitude'][] = 'longitude is required.';
        }
        if (trim((string) ($tracking['last_seen_at'] ?? '')) === '') {
            $errors['tracking.last_seen_at'][] = 'last_seen_at is required.';
        }
        if (trim((string) ($tracking['tracking_status'] ?? '')) === '') {
            $errors['tracking.tracking_status'][] = 'tracking_status is required.';
        } elseif (! in_array(strtolower(trim((string) $tracking['tracking_status'])), ['online', 'offline', 'stale'], true)) {
            $errors['tracking.tracking_status'][] = 'tracking_status must be online, offline, or stale.';
        }
        if (! is_numeric($tracking['latitude'] ?? null)) {
            $errors['tracking.latitude'][] = 'latitude must be numeric.';
        } elseif ((float) $tracking['latitude'] < -90 || (float) $tracking['latitude'] > 90) {
            $errors['tracking.latitude'][] = 'latitude must be between -90 and 90.';
        }
        if (! is_numeric($tracking['longitude'] ?? null)) {
            $errors['tracking.longitude'][] = 'longitude must be numeric.';
        } elseif ((float) $tracking['longitude'] < -180 || (float) $tracking['longitude'] > 180) {
            $errors['tracking.longitude'][] = 'longitude must be between -180 and 180.';
        }

        return $errors;
    }

    private function fetchRoutePerformanceRows(string $table, int $tenantId, array $filters, string $dateField, array $selectFields): array
    {
        $query = $this->db
            ->select(implode(',', $selectFields))
            ->from(db_prefix() . $table)
            ->where('tenant_id', $tenantId);

        if (! empty($filters['date_from'])) {
            $query->where($dateField . ' >=', trim((string) $filters['date_from']) . ' 00:00:00');
        }
        if (! empty($filters['date_to'])) {
            $query->where($dateField . ' <=', trim((string) $filters['date_to']) . ' 23:59:59');
        }
        if (! empty($filters['staff_id'])) {
            $query->where('crm_staff_id', (int) $filters['staff_id']);
        }
        if (! empty($filters['customer_id'])) {
            $query->where('crm_customer_id', (int) $filters['customer_id']);
        }
        if (! empty($filters['store_id'])) {
            $query->where('store_id', (int) $filters['store_id']);
        }

        return $query->order_by($dateField, 'asc')->get()->result_array();
    }

    private function fetchLiveTrackingRows(int $tenantId, array $filters): array
    {
        $query = $this->db
            ->select('id, tenant_id, dms_user_id, crm_staff_id, latitude, longitude, last_seen_at, tracking_status, payload_json, created_at, updated_at')
            ->from(db_prefix() . 'dms_live_tracking_snapshots')
            ->where('tenant_id', $tenantId);

        if (! empty($filters['staff_id'])) {
            $query->where('crm_staff_id', (int) $filters['staff_id']);
        }
        if (! empty($filters['date'])) {
            $date = trim((string) $filters['date']);
            $timestamp = strtotime($date);
            if ($timestamp !== false) {
                $query->where('last_seen_at >=', date('Y-m-d 00:00:00', $timestamp));
                $query->where('last_seen_at <=', date('Y-m-d 23:59:59', $timestamp));
            }
        }

        return $query->order_by('last_seen_at', 'desc')->get()->result_array();
    }

    private function loadScopedEntityIds(int $tenantId, string $entityType): array
    {
        $rows = $this->db
            ->select('local_id')
            ->from(db_prefix() . 'kt_integration_entity_links')
            ->where('tenant_id', $tenantId)
            ->where('provider_code', self::DMS_PROVIDER_CODE)
            ->where('entity_type', $entityType)
            ->get()
            ->result_array();

        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['local_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private function extractDistinctIds(array $firstRows, array $secondRows, string $field): array
    {
        $ids = [];
        foreach ([$firstRows, $secondRows] as $rows) {
            foreach ($rows as $row) {
                $id = (int) ($row[$field] ?? 0);
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        }

        return array_values($ids);
    }

    private function validateDmsWorkspaceRequest(array $headers, array $connection = []): array
    {
        $bearerToken = $this->extractBearerToken($headers);
        $timestamp = trim((string) ($headers['x-timestamp'] ?? $headers['X-Timestamp'] ?? ''));
        $signature = trim((string) ($headers['x-signature'] ?? $headers['X-Signature'] ?? ''));
        $workspaceKey = strtolower(trim((string) ($headers['x-workspace-key'] ?? $headers['X-Workspace-Key'] ?? '')));
        $requestId = trim((string) ($headers['x-request-id'] ?? $headers['X-Request-Id'] ?? ''));

        if ($timestamp === '' || $signature === '' || $workspaceKey === '' || $requestId === '') {
            return [
                'success' => false,
                'code' => 'INVALID_REQUEST',
                'message' => 'Workspace discovery signature headers are incomplete.',
                'status' => 422,
            ];
        }

        $credentialPairs = $this->workspaceDiscoveryCredentialPairs($connection);
        if ($bearerToken === '' || $credentialPairs === []) {
            return [
                'success' => false,
                'code' => 'INVALID_TOKEN',
                'message' => 'Workspace discovery token is invalid.',
                'status' => 401,
            ];
        }

        $matchedSecret = '';
        foreach ($credentialPairs as $pair) {
            $expectedToken = trim((string) ($pair['token'] ?? ''));
            $candidateSecret = trim((string) ($pair['secret'] ?? ''));
            if ($expectedToken === '' || $candidateSecret === '') {
                continue;
            }

            if (!hash_equals($expectedToken, $bearerToken)) {
                continue;
            }

            $matchedSecret = $candidateSecret;
            break;
        }

        if ($matchedSecret === '') {
            return [
                'success' => false,
                'code' => 'INVALID_TOKEN',
                'message' => 'Workspace discovery token is invalid.',
                'status' => 401,
            ];
        }

        $timestampValue = is_numeric($timestamp) ? (int) $timestamp : 0;
        $nowMs = (int) round(microtime(true) * 1000);
        if ($timestampValue <= 0 || abs($nowMs - $timestampValue) > 300000) {
            return [
                'success' => false,
                'code' => 'REQUEST_EXPIRED',
                'message' => 'Workspace discovery request expired.',
                'status' => 401,
            ];
        }

        $expectedSignature = $this->workspaceDiscoverySignature('GET', self::DMS_WORKSPACE_PATH, $timestamp, $workspaceKey, '', $matchedSecret);
        if (!hash_equals($expectedSignature, $signature)) {
            return [
                'success' => false,
                'code' => 'INVALID_SIGNATURE',
                'message' => 'Workspace discovery signature is invalid.',
                'status' => 401,
            ];
        }

        return [
            'success' => true,
            'request_id' => $requestId,
            'workspace_key' => $workspaceKey,
        ];
    }

    private function workspaceDiscoveryCredentialPairs(array $connection = []): array
    {
        $pairs = [];
        $pushPair = static function (string $token, string $secret) use (&$pairs): void {
            $token = trim($token);
            $secret = trim($secret);
            if ($token === '' || $secret === '') {
                return;
            }

            $pairs[$token . '|' . $secret] = [
                'token' => $token,
                'secret' => $secret,
            ];
        };

        $pushPair(
            (string) (getenv('KHACHTOT_BRIDGE_DISCOVERY_TOKEN') ?: (defined('KHACHTOT_BRIDGE_DISCOVERY_TOKEN') ? constant('KHACHTOT_BRIDGE_DISCOVERY_TOKEN') : '')),
            (string) (getenv('KHACHTOT_BRIDGE_DISCOVERY_SECRET') ?: (defined('KHACHTOT_BRIDGE_DISCOVERY_SECRET') ? constant('KHACHTOT_BRIDGE_DISCOVERY_SECRET') : ''))
        );
        $pushPair(
            (string) getenv('KHACHTOT_BRIDGE_TOKEN'),
            (string) getenv('KHACHTOT_BRIDGE_SECRET')
        );
        $pushPair(
            (string) ($connection['public_key'] ?? ''),
            kt_integration_hub_decrypt_value($connection['webhook_secret_encrypted'] ?? '')
        );

        return array_values($pairs);
    }

    private function resolveRequestedCompanyId(array $tenant, int $requestedCompanyId): int
    {
        $tenantId = (int) ($tenant['id'] ?? 0);
        $canonicalCompanyId = (int) (kt_integration_hub_crm_company_id($tenant) ?? 0);
        if ($canonicalCompanyId <= 0) {
            return 0;
        }

        if ($requestedCompanyId <= 0 || $requestedCompanyId === $canonicalCompanyId) {
            return $canonicalCompanyId;
        }

        return 0;
    }

    private function sourceAwareReadiness(int $tenantId, int $companyId): array
    {
        $readiness = $this->dmsContractStore->readiness($tenantId, $companyId);
        $sourceCounts = method_exists($this->Kt_integration_model, 'get_dms_bootstrap_source_counts')
            ? $this->Kt_integration_model->get_dms_bootstrap_source_counts($tenantId)
            : ['available' => false];
        if (empty($sourceCounts['available'])) {
            return $readiness;
        }

        $staffRequired = (int) ($sourceCounts['staff'] ?? 0) > 0;
        $customerRequired = (int) ($sourceCounts['customers'] ?? 0) > 0;
        $productRequired = (int) ($sourceCounts['products'] ?? 0) > 0;
        $coreReady = (!$staffRequired || (int) ($readiness['staff_mappings'] ?? 0) > 0)
            && (!$customerRequired || (int) ($readiness['customer_store_mappings'] ?? 0) > 0)
            && (!$productRequired || (int) ($readiness['product_mappings'] ?? 0) > 0);
        if ((int) ($readiness['open_issues'] ?? 0) === 0 && (int) ($readiness['failed_health_rows'] ?? 0) === 0) {
            $readiness['status'] = $coreReady ? 'READY' : 'PARTIAL';
        }
        $readiness['source_counts'] = $sourceCounts;
        $readiness['customer_store_required'] = $customerRequired;
        $readiness['product_required'] = $productRequired;

        return $readiness;
    }

    private function workspaceSlugFromHost(string $host): string
    {
        $host = strtolower(trim($host));
        if ($host === '' || strpos($host, '.') === false) {
            return '';
        }

        [$slug] = explode('.', $host, 2);

        return trim((string) $slug);
    }

    private function workspaceDiscoverySignature(string $method, string $path, string $timestamp, string $workspaceKey, string $rawBody, string $secret): string
    {
        $canonical = strtoupper(trim($method)) . "\n"
            . '/' . ltrim(trim($path), '/') . "\n"
            . trim($timestamp) . "\n"
            . strtolower(trim($workspaceKey)) . "\n"
            . hash('sha256', $rawBody);

        return hash_hmac('sha256', $canonical, $secret);
    }

    private function extractBearerToken(array $headers): string
    {
        $authorization = trim((string) ($headers['authorization'] ?? $headers['Authorization'] ?? ''));
        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return '';
        }

        return trim((string) ($matches[1] ?? ''));
    }

    private function requestHeaders(): array
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[strtolower((string) $key)] = (string) $value;
            $normalized[(string) $key] = (string) $value;
        }

        return $normalized;
    }

    private function successResponse($code, $message, array $data): void
    {
        $this->jsonResponse([
            'success' => true,
            'code' => (string) $code,
            'message' => (string) $message,
            'data' => $data,
        ], 200);
    }

    private function errorResponse($code, $message, $statusCode, array $errors = []): void
    {
        $requestId = trim((string) ($this->input->get_request_header('X-Request-Id', true) ?: ''));
        $payload = [
            'success' => false,
            'code' => (string) $code,
            'message' => (string) $message,
            'meta' => [
                'request_id' => $requestId,
                'timestamp' => gmdate('c'),
            ],
        ];
        if ($errors !== []) {
            $payload['errors'] = $errors;
        }
        $this->jsonResponse($payload, (int) $statusCode);
    }

    private function jsonResponse(array $payload, $statusCode = 200): void
    {
        set_status_header((int) $statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
