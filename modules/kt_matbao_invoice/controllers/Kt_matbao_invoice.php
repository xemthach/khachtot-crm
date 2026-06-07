<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_matbao_invoice extends AdminController
{
    private $planFeatureTable;

    public function __construct()
    {
        parent::__construct();
        $this->load->helper(KT_MATBAO_INVOICE_MODULE . '/kt_matbao_invoice');
        $this->load->model(KT_MATBAO_INVOICE_MODULE . '/Kt_matbao_invoice_model');
        $this->load->model('kt_saas/Kt_saas_model');
        $this->load->library(KT_MATBAO_INVOICE_MODULE . '/Matbao_invoice_client');
        $this->load->library(KT_MATBAO_INVOICE_MODULE . '/Matbao_sign_client');
        $this->planFeatureTable = db_prefix() . 'kt_saas_plan_features';
    }

    private function guard($cap = 'matbao_invoice_view')
    {
        if (!kt_matbao_invoice_is_landlord_context()) {
            access_denied('KT MatBao Invoice (landlord only)');
        }
        if (!kt_matbao_invoice_staff_can($cap)) {
            access_denied('KT MatBao Invoice');
        }
    }

    private function requirePost()
    {
        if (strtoupper((string) $this->input->method()) !== 'POST') {
            show_404();
        }
    }

    public function index()
    {
        $this->guard('matbao_invoice_view');
        $data['title'] = _l('kt_matbao_invoice');
        $data['records'] = $this->Kt_matbao_invoice_model->get_records(null, 20);
        $data['logs'] = $this->Kt_matbao_invoice_model->get_logs(null, 20);
        $data['webhook_logs'] = $this->Kt_matbao_invoice_model->get_webhook_logs(null, 20);
        $this->load->view(KT_MATBAO_INVOICE_MODULE . '/admin/overview', $data);
    }

    public function settings()
    {
        $this->guard('matbao_invoice_configure');
        try {
            $settings = $this->Kt_matbao_invoice_model->get_settings(null, 'landlord');
        } catch (Throwable $e) {
            log_message('error', 'KT MatBao settings load failed: ' . $e->getMessage());
            show_error('KT MatBao settings load failed: ' . html_escape($e->getMessage()), 500);
            return;
        }

        if ($this->input->post()) {
            try {
                $this->Kt_matbao_invoice_model->save_settings($this->input->post(), null, 'landlord');
                update_option('kt_matbao_invoice_webhook_secret', trim((string) $this->input->post('webhook_secret')));
                set_alert('success', 'Đã lưu cấu hình KT Mắt Bão.');
            } catch (Throwable $e) {
                log_message('error', 'KT MatBao settings save failed: ' . $e->getMessage());
                set_alert('warning', 'Lưu cấu hình thất bại: ' . $e->getMessage());
            }
            redirect(admin_url('kt_matbao_invoice/settings'));
        }

        $data['title'] = _l('kt_matbao_invoice_settings');
        $data['settings'] = $settings;
        $data['ca_settings'] = $this->Kt_matbao_invoice_model->get_ca_account(null, 'landlord');
        $data['webhook_secret'] = (string) get_option('kt_matbao_invoice_webhook_secret');
        $this->load->view(KT_MATBAO_INVOICE_MODULE . '/admin/settings', $data);
    }

    public function test_connection()
    {
        $this->guard('matbao_invoice_configure');
        $this->requirePost();
        $settings = $this->Kt_matbao_invoice_model->get_settings(null, 'landlord');
        if (!$settings) {
            set_alert('warning', 'No settings to test.');
            redirect(admin_url('kt_matbao_invoice/settings'));
        }

        $result = $this->matbao_invoice_client->login($settings);
        $status = !empty($result['success']) ? 'success' : 'error';
        $message = !empty($result['success']) ? 'Connection success.' : ('Connection failed: ' . ($result['error'] ?: 'HTTP ' . (int) ($result['http_code'] ?? 0)));
        $this->Kt_matbao_invoice_model->update_test_result((int) $settings['id'], $status, $message);
        set_alert(!empty($result['success']) ? 'success' : 'warning', $message);
        redirect(admin_url('kt_matbao_invoice/settings'));
    }

    public function test_ca_connection()
    {
        $this->guard('matbao_invoice_configure');
        $this->requirePost();
        $settings = $this->Kt_matbao_invoice_model->get_ca_account(null, 'landlord');
        if (!$settings) {
            set_alert('warning', 'No CA/HSM settings to test.');
            redirect(admin_url('kt_matbao_invoice/settings'));
        }

        $result = $this->matbao_sign_client->login($settings);
        $message = !empty($result['success']) ? 'CA/HSM connection success.' : ('CA/HSM connection failed: ' . ($result['error'] ?: 'HTTP ' . (int) ($result['http_code'] ?? 0)));
        set_alert(!empty($result['success']) ? 'success' : 'warning', $message);
        redirect(admin_url('kt_matbao_invoice/settings'));
    }

    public function templates()
    {
        $this->guard('matbao_invoice_view');
        $data['title'] = _l('kt_matbao_invoice_templates');
        $data['templates'] = $this->Kt_matbao_invoice_model->get_templates(null, 'landlord');
        $this->load->view(KT_MATBAO_INVOICE_MODULE . '/admin/templates', $data);
    }

    public function sync_templates()
    {
        $this->guard('matbao_invoice_configure');
        $this->requirePost();

        $year = (int) ($this->input->post('year') ?: date('Y'));
        $settings = $this->Kt_matbao_invoice_model->get_settings(null, 'landlord');
        if (!$settings) {
            set_alert('warning', 'No settings for template sync.');
            redirect(admin_url('kt_matbao_invoice/settings'));
        }

        $result = $this->matbao_invoice_client->getTemplates($settings, $year);
        if (empty($result['success'])) {
            set_alert('warning', 'Template sync failed.');
            redirect(admin_url('kt_matbao_invoice/templates'));
        }

        $rows = [];
        $response = is_array($result['response']) ? $result['response'] : [];
        if (isset($response['Data']) && is_array($response['Data'])) {
            $rows = $response['Data'];
        } elseif (isset($response['data']) && is_array($response['data'])) {
            $rows = $response['data'];
        } elseif (is_array($response)) {
            $rows = $response;
        }

        $this->Kt_matbao_invoice_model->replace_templates(null, 'landlord', $year, $rows);
        set_alert('success', 'Template sync success.');
        redirect(admin_url('kt_matbao_invoice/templates'));
    }

    public function invoices()
    {
        $this->guard('matbao_invoice_view');
        $data['title'] = _l('kt_matbao_invoice_invoices');
        $data['records'] = $this->Kt_matbao_invoice_model->get_records(null, 200);
        $this->load->view(KT_MATBAO_INVOICE_MODULE . '/admin/invoices', $data);
    }

    public function logs()
    {
        $this->guard('matbao_invoice_logs');
        $data['title'] = _l('kt_matbao_invoice_logs');
        $data['logs'] = $this->Kt_matbao_invoice_model->get_logs(null, 300);
        $this->load->view(KT_MATBAO_INVOICE_MODULE . '/admin/logs', $data);
    }

    public function webhook_logs()
    {
        $this->guard('matbao_invoice_logs');
        $data['title'] = _l('kt_matbao_invoice_webhook_logs');
        $data['logs'] = $this->Kt_matbao_invoice_model->get_webhook_logs(null, 300);
        $this->load->view(KT_MATBAO_INVOICE_MODULE . '/admin/webhook_logs', $data);
    }

    public function plan_entitlements()
    {
        $this->guard('matbao_invoice_configure');
        if ($this->input->post()) {
            $this->requirePost();
            $matrix = $this->input->post('entitlements');
            if (is_array($matrix)) {
                $changedPlanIds = $this->savePlanEntitlements($matrix);
                $this->syncTenantModuleRegistriesByPlans($changedPlanIds);
                set_alert('success', 'Saved MatBao plan entitlements.');
            } else {
                set_alert('warning', 'Invalid entitlement payload.');
            }
            redirect(admin_url('kt_matbao_invoice/plan_entitlements'));
        }

        $data['title'] = _l('kt_matbao_invoice_plan_entitlements');
        $data['plans'] = $this->db->order_by('id', 'asc')->get(db_prefix() . 'kt_saas_plans')->result_array();
        $data['features'] = $this->matbaoFeatures();
        $data['entitlements'] = $this->getPlanEntitlementMap();
        $this->load->view(KT_MATBAO_INVOICE_MODULE . '/admin/plan_entitlements', $data);
    }

    private function matbaoFeatures()
    {
        return [
            'matbao_invoice.enabled' => ['type' => 'bool', 'label' => 'Enable module'],
            'matbao_invoice.tenant_config' => ['type' => 'bool', 'label' => 'Tenant custom account'],
            'matbao_invoice.shared_landlord_account' => ['type' => 'bool', 'label' => 'Use landlord shared account'],
            'matbao_invoice.create_draft' => ['type' => 'bool', 'label' => 'Create draft'],
            'matbao_invoice.issue_invoice' => ['type' => 'bool', 'label' => 'Issue invoice'],
            'matbao_invoice.download_pdf' => ['type' => 'bool', 'label' => 'Download PDF'],
            'matbao_invoice.download_xml' => ['type' => 'bool', 'label' => 'Download XML'],
            'matbao_invoice.buy_addon' => ['type' => 'bool', 'label' => 'Buy add-ons'],
            'matbao_invoice.hsm_signature' => ['type' => 'bool', 'label' => 'Use HSM signature'],
            'matbao_invoice.daily_quota' => ['type' => 'limit', 'label' => 'Daily quota'],
            'matbao_invoice.monthly_quota' => ['type' => 'limit', 'label' => 'Monthly quota'],
            'matbao_ca.enabled' => ['type' => 'bool', 'label' => 'Enable MatBaoCA'],
            'matbao_ca.sign_xml' => ['type' => 'bool', 'label' => 'Sign XML via MatBaoCA'],
            'matbao_ca.sign_pdf' => ['type' => 'bool', 'label' => 'Sign PDF via MatBaoCA'],
            'matbao_ca.shared_landlord_hsm' => ['type' => 'bool', 'label' => 'Use landlord shared HSM'],
        ];
    }

    private function getPlanEntitlementMap()
    {
        $rows = $this->db
            ->where('module_name', 'matbao_invoice')
            ->get($this->planFeatureTable)
            ->result_array();
        $map = [];
        foreach ($rows as $r) {
            $pid = (int) ($r['plan_id'] ?? 0);
            $key = (string) ($r['feature_key'] ?? '');
            if ($pid < 1 || $key === '') {
                continue;
            }
            $map[$pid][$key] = [
                'is_enabled' => (int) ($r['is_enabled'] ?? 0) === 1,
                'feature_value' => (string) ($r['feature_value'] ?? ''),
            ];
        }
        return $map;
    }

    private function savePlanEntitlements(array $matrix)
    {
        $now = date('Y-m-d H:i:s');
        $features = $this->matbaoFeatures();
        $changedPlanIds = [];
        foreach ($matrix as $planId => $payload) {
            $planId = (int) $planId;
            if ($planId < 1 || !is_array($payload)) {
                continue;
            }

            foreach ($features as $featureKey => $meta) {
                $safeKey = $this->featureSafeKey($featureKey);
                $row = $payload[$safeKey] ?? [];
                $enabled = !empty($row['enabled']) ? 1 : 0;
                $value = null;
                if ($meta['type'] === 'limit') {
                    $raw = isset($row['value']) ? trim((string) $row['value']) : '';
                    $value = $raw === '' ? null : (string) max(0, (int) $raw);
                    if ($value !== null && (int) $value > 0) {
                        $enabled = 1;
                    }
                }

                $exists = $this->db
                    ->where('plan_id', $planId)
                    ->where('module_name', 'matbao_invoice')
                    ->where('feature_key', $featureKey)
                    ->get($this->planFeatureTable)
                    ->row_array();

                $save = [
                    'plan_id' => $planId,
                    'module_name' => 'matbao_invoice',
                    'feature_key' => $featureKey,
                    'is_enabled' => $enabled,
                    'feature_value' => $value,
                    'created_at' => $exists['created_at'] ?? $now,
                ];

                if ($exists) {
                    $this->db->where('id', (int) $exists['id'])->update($this->planFeatureTable, $save);
                } else {
                    $this->db->insert($this->planFeatureTable, $save);
                }
            }

            // Bridge key for KT SaaS module registry and TenantUiService.
            $enableSafeKey = $this->featureSafeKey('matbao_invoice.enabled');
            $enableRow = $payload[$enableSafeKey] ?? [];
            $moduleAccessEnabled = !empty($enableRow['enabled']) ? 1 : 0;
            $moduleAccessRow = $this->db
                ->where('plan_id', $planId)
                ->where('module_name', KT_MATBAO_INVOICE_MODULE)
                ->where('feature_key', KT_MATBAO_INVOICE_MODULE . '.access')
                ->get($this->planFeatureTable)
                ->row_array();
            $moduleAccessPayload = [
                'plan_id' => $planId,
                'module_name' => KT_MATBAO_INVOICE_MODULE,
                'feature_key' => KT_MATBAO_INVOICE_MODULE . '.access',
                'is_enabled' => $moduleAccessEnabled,
                'feature_value' => null,
                'created_at' => $moduleAccessRow['created_at'] ?? $now,
            ];
            if ($moduleAccessRow) {
                $this->db->where('id', (int) $moduleAccessRow['id'])->update($this->planFeatureTable, $moduleAccessPayload);
            } else {
                $this->db->insert($this->planFeatureTable, $moduleAccessPayload);
            }

            $changedPlanIds[$planId] = $planId;
        }

        return array_values($changedPlanIds);
    }

    private function featureSafeKey($featureKey)
    {
        return str_replace('.', '__DOT__', (string) $featureKey);
    }

    private function syncTenantModuleRegistriesByPlans(array $planIds)
    {
        if (empty($planIds)) {
            return;
        }

        foreach ($planIds as $planId) {
            $planId = (int) $planId;
            if ($planId < 1) {
                continue;
            }
            $this->Kt_saas_model->rebuild_module_registries($planId);
        }
    }

    public function reseller_packages($serviceType = '')
    {
        $this->guard('matbao_invoice_configure');
        if ($this->input->post()) {
            $this->Kt_matbao_invoice_model->save_reseller_package($this->input->post(), $this->input->post('id'));
            set_alert('success', 'Đã lưu gói dịch vụ đại lý.');
            redirect(admin_url('kt_matbao_invoice/reseller_packages'));
        }

        $data['title'] = 'Gói dịch vụ đại lý';
        $data['service_type'] = $serviceType;
        $data['packages'] = $this->Kt_matbao_invoice_model->get_reseller_packages($serviceType !== '' ? $serviceType : null);
        $this->load->view(KT_MATBAO_INVOICE_MODULE . '/admin/reseller_packages', $data);
    }

    public function orders()
    {
        $this->guard('matbao_invoice_view');
        $data['title'] = 'Đơn hàng dịch vụ';
        $data['orders'] = $this->Kt_matbao_invoice_model->get_orders();
        $this->load->view(KT_MATBAO_INVOICE_MODULE . '/admin/orders', $data);
    }

    public function order_mark_paid($orderId)
    {
        $this->guard('matbao_invoice_configure');
        $this->requirePost();
        $result = $this->Kt_matbao_invoice_model->mark_order_paid_and_activate_addons((int) $orderId, 'manual_admin', [
            'payment_reference' => 'MATBAO-MANUAL-' . (int) $orderId,
            'source' => 'landlord_admin',
        ]);
        if (!empty($result['success'])) {
            set_alert('success', (string) ($result['message'] ?? 'Đã ghi nhận thanh toán và đưa dịch vụ vào hàng đợi cấp phát.'));
        } else {
            set_alert('warning', (string) ($result['message'] ?? 'Không thể ghi nhận thanh toán đơn hàng.'));
        }
        redirect(admin_url('kt_matbao_invoice/orders'));
    }

    public function provisioning_queue()
    {
        $this->guard('matbao_invoice_view');
        $data['title'] = 'Hàng đợi cấp phát nhà cung cấp';
        $data['jobs'] = $this->Kt_matbao_invoice_model->get_provider_jobs();
        $this->load->view(KT_MATBAO_INVOICE_MODULE . '/admin/provisioning_queue', $data);
    }

    public function tenant_addons()
    {
        $this->guard('matbao_invoice_view');
        $data['title'] = 'Gói dịch vụ bổ sung';
        $data['addons'] = $this->Kt_matbao_invoice_model->get_tenant_addons();
        $this->load->view(KT_MATBAO_INVOICE_MODULE . '/admin/tenant_addons', $data);
    }
}
