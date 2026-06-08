<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_integration_hub extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(KT_INTEGRATION_HUB_MODULE . '/kt_integration_hub');
        $this->load->model(KT_INTEGRATION_HUB_MODULE . '/Kt_integration_model');
    }

    public function index()
    {
        $this->requireCapability('kt_integration_hub_view');
        $tenantId = $this->scopeTenantId();

        $data['title'] = _l('kt_integration_hub_dashboard');
        $data['summary'] = $this->Kt_integration_model->get_summary($tenantId);
        $data['connections'] = array_slice($this->Kt_integration_model->get_connections($tenantId), 0, 8);
        $data['jobs'] = array_slice($this->Kt_integration_model->get_jobs($tenantId, 20), 0, 10);
        $data['logs'] = array_slice($this->Kt_integration_model->get_logs($tenantId, 20), 0, 10);
        $data['is_tenant'] = kt_integration_hub_is_tenant_runtime();
        $this->render(KT_INTEGRATION_HUB_MODULE . '/' . ($data['is_tenant'] ? 'tenant' : 'admin') . '/dashboard', $data);
    }

    public function providers()
    {
        $this->requireLandlord();
        $this->requireCapability('kt_integration_hub_manage');

        $data['title'] = _l('kt_integration_hub_providers');
        $data['providers'] = $this->Kt_integration_model->get_providers();
        $this->render(KT_INTEGRATION_HUB_MODULE . '/admin/providers', $data);
    }

    public function connections($id = null)
    {
        $tenantId = $this->scopeTenantId();
        $this->requireCapability($tenantId === null ? 'kt_integration_hub_view' : 'kt_integration_hub_connect');

        if ($this->input->post()) {
            if ($tenantId === null) {
                set_alert('warning', 'Create tenant connections from a tenant workspace.');
                redirect(admin_url('kt_integration_hub/connections'));
            }

            $result = $this->Kt_integration_model->save_connection($this->input->post(), $tenantId, $id ? (int) $id : null);
            if (!empty($result['generated_secret'])) {
                $this->session->set_flashdata('kt_integration_hub_generated_secret', (string) $result['generated_secret']);
                $this->session->set_flashdata('kt_integration_hub_connection_id', (int) $result['id']);
            }
            set_alert(!empty($result['success']) ? 'success' : 'warning', !empty($result['success']) ? _l('kt_integration_hub_connection_saved') : ($result['message'] ?? _l('kt_integration_hub_invalid_request')));
            redirect(admin_url('kt_integration_hub/connections'));
        }

        $data['title'] = _l('kt_integration_hub_connections');
        $data['providers'] = $this->Kt_integration_model->get_providers(true);
        $data['connections'] = $this->Kt_integration_model->get_connections($tenantId);
        $data['edit_connection'] = $id ? $this->Kt_integration_model->get_connection((int) $id) : null;
        $data['generated_secret'] = $this->session->flashdata('kt_integration_hub_generated_secret');
        $data['generated_secret_connection_id'] = (int) $this->session->flashdata('kt_integration_hub_connection_id');
        if ($tenantId !== null && $data['edit_connection'] && (int) $data['edit_connection']['tenant_id'] !== (int) $tenantId) {
            show_404();
        }
        $data['is_tenant'] = kt_integration_hub_is_tenant_runtime();
        $this->render(KT_INTEGRATION_HUB_MODULE . '/' . ($data['is_tenant'] ? 'tenant' : 'admin') . '/connections', $data);
    }

    public function disconnect($id)
    {
        $this->requireCapability('kt_integration_hub_disconnect');
        $tenantId = $this->scopeTenantId();
        if ($this->Kt_integration_model->disconnect_connection((int) $id, $tenantId)) {
            set_alert('success', _l('kt_integration_hub_connection_disconnected'));
        } else {
            set_alert('warning', _l('kt_integration_hub_invalid_request'));
        }

        redirect(admin_url('kt_integration_hub/connections'));
    }

    public function rotate_secret($id)
    {
        $this->requireCapability('kt_integration_hub_connect');
        $tenantId = $this->scopeTenantId();
        if ($tenantId === null) {
            access_denied(KT_INTEGRATION_HUB_MODULE);
        }

        $result = $this->Kt_integration_model->rotate_connection_secret((int) $id, $tenantId);
        if (!empty($result['success']) && !empty($result['generated_secret'])) {
            $this->session->set_flashdata('kt_integration_hub_generated_secret', (string) $result['generated_secret']);
            $this->session->set_flashdata('kt_integration_hub_connection_id', (int) $result['id']);
        }

        set_alert(!empty($result['success']) ? 'success' : 'warning', !empty($result['success']) ? _l('kt_integration_hub_secret_rotated') : ($result['message'] ?? _l('kt_integration_hub_invalid_request')));
        redirect(admin_url('kt_integration_hub/connections'));
    }

    public function jobs()
    {
        $this->requireCapability('kt_integration_hub_retry_jobs');
        $tenantId = $this->scopeTenantId();

        $data['title'] = _l('kt_integration_hub_jobs');
        $data['jobs'] = $this->Kt_integration_model->get_jobs($tenantId, 200);
        $data['is_tenant'] = kt_integration_hub_is_tenant_runtime();
        $this->render(KT_INTEGRATION_HUB_MODULE . '/' . ($data['is_tenant'] ? 'tenant' : 'admin') . '/jobs', $data);
    }

    public function channel_orders($id = null)
    {
        $this->requireCapability('kt_integration_hub_view');
        $tenantId = $this->scopeTenantId();

        $data['title'] = _l('kt_integration_hub_channel_orders');
        $data['orders'] = $this->Kt_integration_model->get_channel_orders($tenantId, 200);
        $data['order'] = $id ? $this->Kt_integration_model->get_channel_order((int) $id, $tenantId) : null;
        $data['is_tenant'] = kt_integration_hub_is_tenant_runtime();
        $this->render(KT_INTEGRATION_HUB_MODULE . '/' . ($data['is_tenant'] ? 'tenant' : 'admin') . '/channel_orders', $data);
    }

    public function retry_job($id)
    {
        $this->requireCapability('kt_integration_hub_retry_jobs');
        if ($this->Kt_integration_model->retry_job((int) $id, $this->scopeTenantId())) {
            set_alert('success', _l('kt_integration_hub_job_queued'));
        } else {
            set_alert('warning', _l('kt_integration_hub_invalid_request'));
        }

        redirect(admin_url('kt_integration_hub/jobs'));
    }

    public function logs()
    {
        $this->requireCapability('kt_integration_hub_logs');
        $tenantId = $this->scopeTenantId();

        $data['title'] = _l('kt_integration_hub_logs');
        $data['logs'] = $this->Kt_integration_model->get_logs($tenantId, 200);
        $data['events'] = $this->Kt_integration_model->get_events($tenantId, 100);
        $data['is_tenant'] = kt_integration_hub_is_tenant_runtime();
        $this->render(KT_INTEGRATION_HUB_MODULE . '/' . ($data['is_tenant'] ? 'tenant' : 'admin') . '/logs', $data);
    }

    public function cron_process_jobs()
    {
        if (!is_cli()) {
            $this->requireCapability('kt_integration_hub_retry_jobs');
        }

        $summary = $this->Kt_integration_model->process_due_jobs(50);
        if (is_cli()) {
            echo kt_integration_hub_json_encode($summary) . PHP_EOL;
            return;
        }

        set_alert('success', _l('kt_integration_hub_cron_processed'));
        redirect(admin_url('kt_integration_hub/jobs'));
    }

    private function scopeTenantId()
    {
        $tenant = kt_integration_hub_current_tenant();

        return !empty($tenant['id']) ? (int) $tenant['id'] : null;
    }

    private function requireLandlord()
    {
        if (kt_integration_hub_is_tenant_runtime()) {
            access_denied(KT_INTEGRATION_HUB_MODULE);
        }
    }

    private function requireCapability($capability)
    {
        if (!kt_integration_hub_staff_can($capability)) {
            access_denied(KT_INTEGRATION_HUB_MODULE);
        }
    }

    private function render($view, $data = [])
    {
        $this->load->view($view, $data);
    }
}
