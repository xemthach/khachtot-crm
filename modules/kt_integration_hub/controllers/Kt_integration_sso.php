<?php

defined('BASEPATH') or exit('No direct script access allowed');
defined('KT_INTEGRATION_HUB_MODULE') or define('KT_INTEGRATION_HUB_MODULE', 'kt_integration_hub');

class Kt_integration_sso extends App_Controller
{
    public function __construct()
    {
        parent::__construct();

        $moduleRoot = dirname(__DIR__);
        require_once $moduleRoot . '/libraries/SsoTokenException.php';
        require_once $moduleRoot . '/libraries/SsoTokenService.php';
        $this->load->helper(KT_INTEGRATION_HUB_MODULE . '/kt_integration_hub');
        $this->load->model(KT_INTEGRATION_HUB_MODULE . '/Kt_integration_model');
        $this->load->model('kt_saas/Kt_saas_model');
    }

    public function token()
    {
        try {
            $this->handleTokenIssuance(false);
        } catch (SsoTokenException $e) {
            $this->errorResponse($e->apiCode(), $e->getMessage(), $e->httpStatus());
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub SSO token API failed: ' . $e->getMessage());
            $this->errorResponse('INTERNAL_SERVER_ERROR', ENVIRONMENT === 'production' ? 'Internal server error.' : $e->getMessage(), 500);
        }
    }

    public function dms()
    {
        try {
            if ($this->redirectForCommercialState()) {
                return;
            }
            $result = $this->handleTokenIssuance(true);
            if (is_array($result) && ! empty($result['redirect_url'])) {
                redirect($result['redirect_url']);
                return;
            }

            redirect(admin_url('authentication'));
        } catch (SsoTokenException $e) {
            if (! is_staff_logged_in()) {
                redirect(admin_url('authentication'));
                return;
            }

            $this->renderSsoFailure($e->apiCode(), $e->getMessage());
        } catch (Throwable $e) {
            log_message('error', 'KT Integration Hub SSO redirect failed: ' . $e->getMessage());
            $this->renderSsoFailure('SSO_UNAVAILABLE', 'DMS đang tạm thời chưa phản hồi. Vui lòng thử lại.');
        }
    }

    private function redirectForCommercialState(): bool
    {
        if (!is_staff_logged_in() || !function_exists('kt_integration_hub_is_tenant_runtime') || !kt_integration_hub_is_tenant_runtime()) {
            return false;
        }

        $tenant = kt_integration_hub_current_tenant();
        $tenantId = (int) ($tenant['id'] ?? 0);
        if ($tenantId <= 0) {
            return false;
        }

        $order = $this->Kt_saas_model->get_latest_dms_commercial_order($tenantId);
        if (!$order) {
            set_alert('warning', 'Bạn chưa kích hoạt gói DMS.');
            redirect(admin_url('kt_saas/tenant_marketplace_app/salesflow-dms'));
            return true;
        }

        $workspaceStatus = strtolower(trim((string) ($order['workspace_status'] ?? 'not_ready')));
        if ($workspaceStatus === 'ready') {
            return false;
        }

        $paymentStatus = strtolower(trim((string) ($order['payment_status'] ?? 'pending')));
        $provisioningStatus = strtolower(trim((string) ($order['provisioning_status'] ?? 'not_started')));
        if (in_array($paymentStatus, ['pending', 'payment_pending', 'payment_submitted'], true)) {
            set_alert('warning', 'Gói DMS đang chờ thanh toán.');
        } elseif (in_array($provisioningStatus, ['provisioning_pending', 'provisioning_running', 'queued', 'running'], true)) {
            set_alert('info', 'Workspace DMS đang được kích hoạt.');
        } else {
            set_alert('warning', 'Workspace DMS chưa sẵn sàng. Vui lòng xem tiến độ kích hoạt.');
        }
        redirect(admin_url('kt_saas/dms_order_status/' . (int) $order['id'] . '?source=sso'));
        return true;
    }

    private function handleTokenIssuance(bool $asRedirect = false)
    {
        if (strtolower((string) $this->input->method()) !== 'get') {
            throw new SsoTokenException('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);
        }

        if (! is_staff_logged_in()) {
            throw new SsoTokenException('UNAUTHENTICATED', 'Staff login is required.', 401);
        }

        if (! function_exists('kt_integration_hub_is_tenant_runtime') || ! kt_integration_hub_is_tenant_runtime()) {
            throw new SsoTokenException('TENANT_RUNTIME_REQUIRED', 'Tenant runtime is required.', 403);
        }

        $tenant = kt_integration_hub_current_tenant();
        if (! is_array($tenant) || (int) ($tenant['id'] ?? 0) <= 0) {
            throw new SsoTokenException('TENANT_CONTEXT_REQUIRED', 'Tenant context is required.', 409);
        }

        $tenant['crm_tenant_id'] = (int) ($tenant['id'] ?? 0);
        $canonicalCompanyId = (int) ($tenant['landlord_client_id'] ?? $tenant['canonical_company_id'] ?? 0);
        if ($canonicalCompanyId <= 0) {
            throw new SsoTokenException('CANONICAL_COMPANY_REQUIRED', 'Canonical CRM company identity is required.', 409);
        }
        $tenant['crm_company_id'] = $canonicalCompanyId;
        $tenant['canonical_crm_company_id'] = $canonicalCompanyId;
        $tenant['compatibility_crm_company_id'] = (int) ($tenant['id'] ?? 0);
        $tenant['identity_trust_level'] = 'canonical';
        $selectedWorkspaceId = (int) $this->input->get('workspace_id');
        $workspace = $this->Kt_integration_model->get_dms_workspace_for_tenant((int) $tenant['id'], $selectedWorkspaceId);
        if (is_array($workspace)) {
            $tenant['workspace_id'] = (int) ($workspace['id'] ?? 0);
            $tenant['workspace_slug'] = (string) ($workspace['workspace_slug'] ?? '');
            $tenant['workspace_host'] = (string) ($workspace['workspace_host'] ?? '');
        } elseif ($selectedWorkspaceId > 0 || count($this->Kt_integration_model->get_dms_workspaces_for_canonical_tenant((int) $tenant['id'])) > 1) {
            throw new SsoTokenException('WORKSPACE_REQUIRED', 'A valid DMS workspace must be selected.', 422);
        }

        $connection = $this->Kt_integration_model->get_salesflow_connection_for_tenant((int) $tenant['id']);
        if (! is_array($connection) || empty($connection['webhook_secret_encrypted'])) {
            throw new SsoTokenException('SSO_CONNECTION_NOT_CONFIGURED', 'Salesflow DMS connection is not configured.', 422);
        }

        $staffId = (int) get_staff_user_id();
        $staff = $this->db
            ->select('staffid, email, firstname, lastname, active')
            ->from(db_prefix() . 'staff')
            ->where('staffid', $staffId)
            ->get()
            ->row_array();

        if (! is_array($staff) || (int) ($staff['staffid'] ?? 0) <= 0) {
            throw new SsoTokenException('STAFF_CONTEXT_REQUIRED', 'Staff context is required.', 422);
        }

        if ((int) ($staff['active'] ?? 0) !== 1) {
            throw new SsoTokenException('STAFF_INACTIVE', 'Staff account is inactive.', 403);
        }

        if (in_array(strtolower((string) ($tenant['status'] ?? 'active')), ['inactive', 'suspended', 'terminated'], true)) {
            throw new SsoTokenException('TENANT_INACTIVE', 'Tenant is inactive.', 403);
        }

        $settings = kt_integration_hub_json_decode((string) ($connection['settings_json'] ?? ''), []);
        $tenant['_bridge_connection_settings'] = kt_integration_hub_json_encode($settings);

        $service = new SsoTokenService();
        $result = $service->issueToken(
            $tenant,
            $staff,
            kt_integration_hub_decrypt_value($connection['webhook_secret_encrypted'] ?? ''),
            300
        );

        $this->Kt_integration_model->log('info', 'sso.token_issued', 'Internal SSO token issued for DMS login.', [
            'crm_staff_id' => (int) $staff['staffid'],
            'crm_tenant_id' => (int) ($tenant['id'] ?? 0),
            'crm_company_id' => (int) ($tenant['crm_company_id'] ?? $tenant['id']),
        ], (int) ($tenant['id'] ?? 0), (int) ($connection['id'] ?? 0), 'salesflow_dms');

        if ($asRedirect) {
            return $result;
        }

        $this->jsonResponse([
            'success' => true,
            'code' => 'SSO_TOKEN_ISSUED',
            'message' => 'SSO token issued successfully.',
            'data' => [
                'token' => $result['token'],
                'issued_at' => $result['issued_at'],
                'expires_at' => $result['expires_at'],
                'redirect_url' => $result['redirect_url'],
            ],
        ]);

        return $result;
    }

    private function jsonResponse(array $payload, int $statusCode = 200): void
    {
        $this->output
            ->set_status_header($statusCode)
            ->set_content_type('application/json')
            ->set_output(kt_integration_hub_json_encode($payload));
    }

    private function errorResponse(string $code, string $message, int $statusCode): void
    {
        $this->jsonResponse([
            'success' => false,
            'code' => $code,
            'message' => $message,
        ], $statusCode);
    }

    private function renderSsoFailure(string $code, string $message): void
    {
        $tenant = function_exists('kt_integration_hub_current_tenant') ? kt_integration_hub_current_tenant() : [];
        $tenantId = (int) ($tenant['id'] ?? 0);
        $safeMessages = [
            'SSO_CONNECTION_NOT_CONFIGURED' => 'Kết nối DMS chưa hoàn tất. Quản trị nền tảng cần cấu hình kết nối Salesrep trước khi mở workspace.',
            'SSO_UNAVAILABLE' => 'DMS đang tạm thời chưa phản hồi. Vui lòng thử lại.',
            'TENANT_CONTEXT_REQUIRED' => 'Không xác định được doanh nghiệp hiện tại. Vui lòng đăng nhập lại.',
        ];
        $safeMessage = $safeMessages[strtoupper($code)] ?? 'Chưa thể mở DMS lúc này. Vui lòng kiểm tra thiết lập DMS hoặc thử lại.';
        $this->Kt_integration_model->log('warning', 'sso.connection_failed', $code, [
            'reason_code' => $code,
            'tenant_action' => 'Kiểm tra thiết lập DMS',
            'landlord_action' => 'Cấu hình kết nối DMS',
        ], $tenantId, null, 'salesflow_dms');
        $this->load->view(KT_INTEGRATION_HUB_MODULE . '/admin/dms/sso_error', [
            'title' => 'Chưa thể mở Khách Tốt DMS',
            'message' => $safeMessage,
            'support_code' => 'SSO-' . strtoupper(substr(sha1((string) $tenantId . '|' . microtime(true)), 0, 8)),
            'retry_url' => site_url('sso/dms'),
            'fallback_url' => admin_url('kt_integration_hub/dms_setup_wizard'),
            'status_url' => admin_url('kt_saas/dms_order_status'),
            'support_url' => admin_url('support'),
        ]);
    }
}
