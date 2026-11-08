<?php

defined('BASEPATH') or exit('No direct script access allowed');

defined('KT_MISA_AMIS_ACCOUNTING_MODULE') or define('KT_MISA_AMIS_ACCOUNTING_MODULE', 'kt_misa_amis_accounting');

require_once dirname(__DIR__) . '/libraries/MisaScope.php';
require_once dirname(__DIR__) . '/libraries/MisaConnectionService.php';
require_once dirname(__DIR__) . '/libraries/MisaSecretVault.php';
require_once dirname(__DIR__) . '/libraries/MisaMockTransport.php';
require_once dirname(__DIR__) . '/libraries/MisaTokenService.php';
require_once dirname(__DIR__) . '/libraries/MisaPayloadValidator.php';
require_once dirname(__DIR__) . '/libraries/MisaPayloadBuilders.php';
require_once dirname(__DIR__) . '/libraries/MisaDryRunService.php';

class Kt_misa_amis_accounting extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(KT_MISA_AMIS_ACCOUNTING_MODULE . '/kt_misa_amis_accounting');
        $this->load->model(KT_MISA_AMIS_ACCOUNTING_MODULE . '/Kt_misa_amis_accounting_model');
    }

    public function index()
    {
        $this->requireCapability('kt_misa_view');
        $scope = $this->scopeFromRequest();
        $summary = $scope ? $this->Kt_misa_amis_accounting_model->get_summary($scope) : ['connections' => 0, 'requests' => 0, 'pending_callbacks' => 0, 'mapping_conflicts' => 0];
        $readiness = kt_misa_readiness_snapshot();
        $this->render(KT_MISA_AMIS_ACCOUNTING_MODULE . '/dashboard', ['title' => _l('kt_misa_dashboard'), 'summary' => $summary, 'scope' => $scope, 'certification' => $readiness, 'readiness' => $readiness]);
    }

    public function readiness()
    {
        $this->requireCapability('kt_misa_view');
        $this->render(KT_MISA_AMIS_ACCOUNTING_MODULE . '/readiness', ['title' => _l('kt_misa_readiness'), 'readiness' => kt_misa_readiness_snapshot()]);
    }

    public function external_test()
    {
        $this->requireCapability('kt_misa_view');
        if (strtolower((string) $this->input->method()) !== 'get') show_error('External test is not enabled.', 403);
        $this->render(KT_MISA_AMIS_ACCOUNTING_MODULE . '/external_test', ['title' => _l('kt_misa_external_test'), 'readiness' => kt_misa_readiness_snapshot()]);
    }

    public function connections($id = 0)
    {
        if ($this->input->method() === 'post') $this->requireCapability('kt_misa_configure'); else $this->requireAnyCapability(['kt_misa_view', 'kt_misa_configure']);
        $scope = $this->scopeFromRequest();
        $connections = $scope ? $this->Kt_misa_amis_accounting_model->get_connections($scope) : [];
        $editing = $id ? $this->Kt_misa_amis_accounting_model->get_connection($scope, (int) $id) : null;
        if ($id && !$editing) show_404();
        if ($this->input->method() === 'post') {
            if (!$scope) show_error('Tenant and company context are required.', 409);
            $this->requireCapability('kt_misa_configure');
            $service = new MisaConnectionService(new MisaSecretVault());
            $input = $this->input->post(null, true);
            $input['actor_id'] = get_staff_user_id();
            try {
                $existing = $editing ? (array) $editing : (!empty($input['id']) ? (array) $this->Kt_misa_amis_accounting_model->get_connection($scope, (int) $input['id']) : []);
                $input['id'] = $existing ? (int) $existing['id'] : 0;
                $data = $service->toStorage($scope, $input, $existing);
                if (!empty($existing)) $data['id'] = (int) $existing['id'];
                if (!$this->Kt_misa_amis_accounting_model->save_connection($scope, $data)) {
                    throw new RuntimeException('MISA connection could not be saved.');
                }
                $this->Kt_misa_amis_accounting_model->audit($scope, $existing ? 'connection.updated' : 'connection.created', ['name' => $data['name'], 'app_id' => $data['app_id'], 'org_company_code' => $data['org_company_code']], $existing ? (int) $existing['id'] : null, get_staff_user_id());
                set_alert('success', 'MISA connection saved in mock-only foundation.');
            } catch (Throwable $e) { set_alert('warning', $e->getMessage()); }
            redirect(admin_url(KT_MISA_AMIS_ACCOUNTING_MODULE . '/connections'));
        }
        $this->render(KT_MISA_AMIS_ACCOUNTING_MODULE . '/connections', ['title' => _l('kt_misa_connections'), 'connections' => $connections, 'scope' => $scope, 'editing' => $editing, 'external_calls_enabled' => kt_misa_feature_enabled('external_calls_enabled')]);
    }

    public function disable($id = 0)
    {
        $this->requireCapability('kt_misa_configure'); $this->requirePostRequest(); $scope = $this->scopeFromRequest();
        if (!$scope || !$this->Kt_misa_amis_accounting_model->get_connection($scope, (int) $id)) show_404();
        $this->Kt_misa_amis_accounting_model->update_connection_health($scope, $id, ['status' => 'disabled', 'disabled_at' => date('Y-m-d H:i:s'), 'updated_by' => get_staff_user_id()]);
        $this->Kt_misa_amis_accounting_model->audit($scope, 'connection.disabled', [], $id, get_staff_user_id());
        redirect(admin_url(KT_MISA_AMIS_ACCOUNTING_MODULE . '/connections'));
    }

    public function enable($id = 0)
    {
        $this->requireCapability('kt_misa_configure'); $this->requirePostRequest(); $scope = $this->scopeFromRequest();
        if (!$scope || !$this->Kt_misa_amis_accounting_model->get_connection($scope, (int) $id)) show_404();
        $this->Kt_misa_amis_accounting_model->update_connection_health($scope, $id, ['status' => 'enabled', 'disabled_at' => null, 'updated_by' => get_staff_user_id(), 'updated_at' => date('Y-m-d H:i:s')]);
        $this->Kt_misa_amis_accounting_model->audit($scope, 'connection.enabled', [], $id, get_staff_user_id());
        redirect(admin_url(KT_MISA_AMIS_ACCOUNTING_MODULE . '/connections'));
    }

    public function mappings()
    {
        $this->requireCapability('kt_misa_preview'); $scope = $this->scopeFromRequest();
        if ($this->input->method() === 'post') {
            $this->requireCapability('kt_misa_resolve_conflict'); $this->requirePostRequest(); if (!$scope) show_error('Tenant and company context are required.', 409);
            $input = $this->input->post(null, true); $connection = $this->Kt_misa_amis_accounting_model->get_connection($scope, (int) ($input['connection_id'] ?? 0));
            if (!$connection || (string) ($input['object_type'] ?? '') !== 'customer') show_error('Only scoped customer mappings are supported.', 422);
            $localKey = trim((string) ($input['local_stable_key'] ?? ''));
            $remoteId = trim((string) ($input['remote_id'] ?? '')) ?: null;
            if ((int) ($input['local_id'] ?? 0) <= 0 || $localKey === '' || ($remoteId === null && trim((string) ($input['remote_code'] ?? '')) === '')) show_error('A valid local key and remote identifier are required.', 422);
            if (mb_strlen($localKey) > 191 || ($remoteId !== null && mb_strlen($remoteId) > 191) || mb_strlen(trim((string) ($input['remote_code'] ?? ''))) > 191) show_error('Mapping identifiers must not exceed 191 characters.', 422);
            $mappingId = $this->Kt_misa_amis_accounting_model->save_mapping($scope, ['connection_id' => (int) $connection['id'], 'object_type' => 'customer', 'local_id' => (int) $input['local_id'], 'local_stable_key' => $localKey, 'remote_id' => $remoteId, 'remote_code' => trim((string) ($input['remote_code'] ?? '')) ?: null, 'direction' => 'push', 'mapping_status' => 'manual', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
            if (!$mappingId) show_error('Mapping already exists or could not be saved.', 409);
            $this->Kt_misa_amis_accounting_model->audit($scope, 'mapping.created', ['object_type' => 'customer'], $connection['id'], get_staff_user_id()); redirect(admin_url(KT_MISA_AMIS_ACCOUNTING_MODULE . '/mappings'));
        }
        $this->render(KT_MISA_AMIS_ACCOUNTING_MODULE . '/mappings', ['title' => _l('kt_misa_mappings'), 'scope' => $scope, 'mappings' => $scope ? $this->Kt_misa_amis_accounting_model->get_mappings($scope) : [], 'connections' => $scope ? $this->Kt_misa_amis_accounting_model->get_connections($scope) : []]);
    }
    public function requests() { $this->requireCapability('kt_misa_view'); $scope = $this->scopeFromRequest(); $this->render(KT_MISA_AMIS_ACCOUNTING_MODULE . '/requests', ['title' => _l('kt_misa_requests'), 'requests' => $scope ? $this->Kt_misa_amis_accounting_model->get_requests($scope) : [], 'scope' => $scope]); }

    public function mock_test($id = 0)
    {
        $this->requireCapability('kt_misa_test_connection');
        $this->requirePostRequest();
        $scope = $this->scopeFromRequest();
        if (!$scope) show_error('Tenant and company context are required.', 409);
        $connection = $this->Kt_misa_amis_accounting_model->get_connection($scope, (int) $id);
        if (!$connection) show_404();
        $result = ['success' => false, 'error_code' => 'MOCK_ONLY'];
        try {
            if ((string) $connection['environment'] !== 'mock' || (string) $connection['status'] === 'disabled') throw new RuntimeException('Only enabled mock connections can be tested.');
            $vault = new MisaSecretVault(); $service = new MisaTokenService(new MisaMockTransport(), $vault);
            $result = $service->connect(['app_id' => $connection['app_id'], 'access_code' => $vault->decrypt($connection['access_code_encrypted']), 'org_company_code' => $connection['org_company_code']]);
            if (!empty($result['success'])) { $this->Kt_misa_amis_accounting_model->update_connection_health($scope, $id, ['access_token_encrypted' => $result['token_encrypted'], 'token_expires_at' => $result['token_expires_at'], 'token_expires_ticks' => $result['token_expires_ticks'], 'token_version' => ((int) $connection['token_version']) + 1, 'last_connected_at' => date('Y-m-d H:i:s'), 'last_healthcheck_at' => date('Y-m-d H:i:s'), 'last_error_code' => null, 'last_error_message_redacted' => null, 'certification_level' => 'MOCK_VERIFIED', 'status' => 'enabled']); $this->Kt_misa_amis_accounting_model->audit($scope, 'connection.mock_test', ['result' => 'success'], $id, get_staff_user_id()); }
            else { $this->Kt_misa_amis_accounting_model->update_connection_health($scope, $id, ['last_healthcheck_at' => date('Y-m-d H:i:s'), 'last_error_code' => (string) ($result['error_code'] ?? 'MOCK_FAILED'), 'last_error_message_redacted' => (string) ($result['error_message'] ?? 'Mock connection failed.'), 'updated_at' => date('Y-m-d H:i:s')]); $this->Kt_misa_amis_accounting_model->audit($scope, 'connection.mock_test_failed', ['error_code' => (string) ($result['error_code'] ?? 'MOCK_FAILED')], $id, get_staff_user_id()); }
        } catch (Throwable $e) { $result = ['success' => false, 'error_code' => 'MOCK_EXCEPTION', 'error_message' => 'Mock transport failure.']; $this->Kt_misa_amis_accounting_model->update_connection_health($scope, $id, ['last_healthcheck_at' => date('Y-m-d H:i:s'), 'last_error_code' => 'MOCK_EXCEPTION', 'last_error_message_redacted' => 'Mock transport failure.', 'updated_at' => date('Y-m-d H:i:s')]); $this->Kt_misa_amis_accounting_model->audit($scope, 'connection.mock_test_failed', ['error_code' => 'MOCK_EXCEPTION'], $id, get_staff_user_id()); }
        set_alert(!empty($result['success']) ? 'success' : 'warning', !empty($result['success']) ? 'Mock connection passed.' : (string) ($result['error_message'] ?? 'Mock connection failed.'));
        redirect(admin_url(KT_MISA_AMIS_ACCOUNTING_MODULE . '/connections'));
    }

    public function dry_run()
    {
        $this->requireCapability('kt_misa_preview'); $this->requirePostRequest(); $scope = $this->scopeFromRequest();
        if (!$scope || (string) $this->input->post('object_type', true) !== 'customer') show_error('Only scoped customer dry-run is supported.', 422);
        $source = $this->input->post(null, true); unset($source['tenant_id'], $source['company_id'], $source['object_type']);
        $result = (new MisaDryRunService(new MisaPayloadBuilders(), new MisaPayloadValidator()))->run('customer', $source);
        $this->Kt_misa_amis_accounting_model->audit($scope, 'customer.dry_run', ['valid' => $result['valid'], 'payload_hash' => $result['payload_hash']], null, get_staff_user_id());
        $this->render(KT_MISA_AMIS_ACCOUNTING_MODULE . '/dry_run', ['title' => 'Customer dry-run', 'result' => $result, 'scope' => $scope]);
    }

    public function request_detail($id = 0)
    {
        $this->requireCapability('kt_misa_view'); $scope = $this->scopeFromRequest(); $request = $scope ? $this->Kt_misa_amis_accounting_model->get_request($scope, $id) : null; if (!$request) show_404();
        $this->render(KT_MISA_AMIS_ACCOUNTING_MODULE . '/request_detail', ['title' => 'MISA request', 'request' => $request]);
    }

    public function retry($id = 0) { $this->requestTransition($id, 'retryable_failed', 'queued', 'kt_misa_retry'); }
    public function cancel($id = 0) { $this->requestTransition($id, null, 'cancelled', 'kt_misa_cancel'); }
    public function payload($id = 0) { $this->requireCapability('kt_misa_view_payload'); $this->renderRequestDetail($id, 'payload'); }
    public function sensitive_metadata($id = 0) { $this->requireCapability('kt_misa_view_sensitive_metadata'); $this->renderRequestDetail($id, 'sensitive_metadata'); }

    private function requestTransition($id, $from, $to, $capability)
    {
        $this->requireCapability($capability); $this->requirePostRequest(); $scope = $this->scopeFromRequest(); $request = $scope ? $this->Kt_misa_amis_accounting_model->get_request($scope, $id) : null; if (!$request) show_404();
        $source = $from ?: $request['status']; if (!$this->Kt_misa_amis_accounting_model->update_request_status($scope, $id, $source, $to)) show_error('Invalid request state transition.', 409);
        $this->Kt_misa_amis_accounting_model->audit($scope, 'request.' . $to, ['request_id' => (int) $id], $request['connection_id'], get_staff_user_id()); redirect(admin_url(KT_MISA_AMIS_ACCOUNTING_MODULE . '/requests/' . (int) $id));
    }

    private function scopeFromRequest()
    {
        $tenant = function_exists('kt_integration_hub_current_tenant') ? kt_integration_hub_current_tenant() : null;
        $tenant = is_array($tenant) ? $tenant : [];
        $trustedCompanyId = (int) ($tenant['crm_company_id'] ?? $tenant['landlord_client_id'] ?? 0);
        $scope = function_exists('kt_misa_current_scope') ? kt_misa_current_scope($tenant, $trustedCompanyId) : null;
        return $scope ? new MisaScope($scope['tenant_id'], $scope['company_id']) : null;
    }

    private function render($view, array $data = [])
    {
        $this->load->view($view, $data);
    }

    private function requireCapability($capability)
    {
        if (!kt_misa_staff_can($capability)) {
            access_denied(KT_MISA_AMIS_ACCOUNTING_MODULE);
        }
    }

    private function requireAnyCapability(array $capabilities)
    {
        foreach ($capabilities as $capability) {
            if (kt_misa_staff_can($capability)) return;
        }
        access_denied(KT_MISA_AMIS_ACCOUNTING_MODULE);
    }

    private function renderRequestDetail($id, $mode)
    {
        $scope = $this->scopeFromRequest();
        $request = $scope ? $this->Kt_misa_amis_accounting_model->get_request($scope, $id) : null;
        if (!$request) show_404();
        $this->render(KT_MISA_AMIS_ACCOUNTING_MODULE . '/request_detail', ['title' => 'MISA request ' . $mode, 'request' => $request]);
    }

    private function requirePostRequest()
    {
        if (strtolower((string) $this->input->method()) !== 'post') {
            show_error('POST is required.', 405);
        }
    }
}
