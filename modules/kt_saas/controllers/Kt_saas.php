<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_saas extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('app_modules');
        $this->load->helper(KT_SAAS_MODULE . '/kt_saas');
        $this->load->model(KT_SAAS_MODULE . '/Kt_saas_model');

        if ($this->isTenantPortalMethod()) {
            $this->requireTenantContext();
            if ($this->isTenantWorkspaceSettingsMethod()) {
                $this->requireTenantWorkspaceSettingsAccess();
            } elseif ($this->isTenantGovernanceMethod()) {
                $this->requireTenantWorkspaceGovernanceViewAccess();
            } else {
                $this->requireTenantAdmin();
            }
            return;
        }

        $this->requireLandlordContext();
    }

    public function index()
    {
        $this->requireCapability('kt_saas_view');

        $data['title'] = _l('kt_saas_dashboard');
        $data['summary'] = $this->Kt_saas_model->get_dashboard_summary();
        $data['business_kpis'] = $this->Kt_saas_model->get_landlord_dashboard_kpis();
        $data['customer_status'] = $this->Kt_saas_model->get_landlord_customer_status_summary();
        $data['billing_health'] = $this->Kt_saas_model->get_landlord_billing_health();
        $data['operations_alerts'] = $this->Kt_saas_model->get_landlord_operations_alerts();
        $data['business_activity'] = $this->Kt_saas_model->get_landlord_recent_business_activity(10);
        $data['landlord_tables'] = $this->Kt_saas_model->get_landlord_table_status();
        $data['runtime'] = $this->Kt_saas_model->get_runtime_overview();
        $data['billing_overview'] = $this->Kt_saas_model->get_billing_dashboard_overview();
        $data['recent_invoices'] = $this->Kt_saas_model->get_recent_invoices(10);
        $data['recent_payments'] = $this->Kt_saas_model->get_recent_payments(10);
        $data['signup_funnel'] = $this->Kt_saas_model->get_signup_funnel_overview(7);
        $data['provision_alerts'] = $this->Kt_saas_model->get_provisioning_alerts();
        $data['usage_overview'] = $this->Kt_saas_model->get_usage_dashboard_overview();
        $data['usage_snapshots'] = $this->Kt_saas_model->get_latest_usage_snapshots(60);
        $data['overage_rows'] = $this->Kt_saas_model->get_overage_dashboard_rows();
        $data['old_usage_rows'] = $this->Kt_saas_model->count_usage_rows_older_than_retention();
        $data['recent_logs'] = $this->Kt_saas_model->get_recent_activity_logs(10);
        $this->load->view(KT_SAAS_MODULE . '/dashboard/index', $data);
    }

    public function dashboard()
    {
        $this->index();
    }

    public function architecture()
    {
        $this->requireCapability('kt_saas_view');

        $data['title'] = _l('kt_saas_architecture');
        $data['doc_path'] = module_dir_path(KT_SAAS_MODULE, 'docs/ARCHITECTURE.md');
        $this->load->view(KT_SAAS_MODULE . '/dashboard/architecture', $data);
    }

    public function tenants($id = null)
    {
        $this->requireCapability('kt_saas_manage_tenants');

        if ($this->input->post()) {
            $result = $this->Kt_saas_model->save_tenant($this->input->post(), $id ?: null);
            $this->flashResult($result, 'Tenant saved successfully.');
            redirect(admin_url('kt_saas/tenants'));
        }

        $data['title'] = _l('kt_saas_tenants');
        $data['show_deleted'] = !empty($this->input->get('include_deleted'));
        $data['tenants'] = $this->Kt_saas_model->get_tenants($data['show_deleted']);
        $data['plans'] = $this->Kt_saas_model->get_plans();
        $data['statuses'] = kt_saas_tenant_statuses();
        $data['edit_tenant'] = $id ? $this->Kt_saas_model->get_tenant($id) : null;
        $data['can_delete_tenants'] = kt_saas_staff_can('kt_saas_delete_tenants');
        $data['can_purge_tenants'] = $data['can_delete_tenants'] && $this->Kt_saas_model->tenant_purge_allowed();
        $data['orphan_report'] = null;
        $this->load->view(KT_SAAS_MODULE . '/dashboard/tenants', $data);
    }

    public function tenant_generate_profile($id = null)
    {
        $this->requireCapability('kt_saas_manage_tenants');
        $companyName = (string) $this->input->get('company_name', true);
        $values = $this->Kt_saas_model->generate_tenant_form_values($companyName, $id ? (int) $id : null);
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(['success' => true, 'data' => $values], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public function tenant_field_check($field, $id = null)
    {
        $this->requireCapability('kt_saas_manage_tenants');
        $value = (string) $this->input->get('value', true);
        $result = $this->Kt_saas_model->check_tenant_field_availability($field, $value, $id ? (int) $id : null);
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public function bulk_tenants()
    {
        $this->requireCapability('kt_saas_manage_tenants');
        $this->requirePost();

        $action = trim((string) $this->input->post('bulk_action'));
        $ids = $this->normalizeBulkIds($this->input->post('ids'));
        if ($action === '' || empty($ids)) {
            set_alert('warning', 'Vui lòng chọn ít nhất một tenant và một thao tác.');
            redirect(admin_url('kt_saas/tenants'));
        }

        $processed = 0;
        $failed = 0;
        foreach ($ids as $id) {
            $ok = false;
            if ($action === 'activate') {
                $ok = $this->Kt_saas_model->set_tenant_status($id, 'active');
            } elseif ($action === 'suspend') {
                $ok = $this->Kt_saas_model->set_tenant_status($id, 'suspended');
            } elseif ($action === 'terminate') {
                $ok = $this->Kt_saas_model->set_tenant_status($id, 'terminated');
            } elseif ($action === 'archive') {
                $ok = $this->Kt_saas_model->archive_tenant($id);
            } elseif ($action === 'delete') {
                if (kt_saas_staff_can('kt_saas_delete_tenants')) {
                    $result = $this->Kt_saas_model->delete_tenant($id, !empty($this->input->post('force_delete')));
                    $ok = !empty($result['success']);
                }
            } elseif ($action === 'queue_provision') {
                $tenant = $this->Kt_saas_model->get_tenant($id);
                if ($tenant) {
                    $this->Kt_saas_model->create_provision_job($id, 'provision_tenant', [
                        'tenant_id'   => $id,
                        'tenant_code' => $tenant['tenant_code'],
                    ]);
                    $ok = true;
                }
            } elseif ($action === 'export') {
                $ok = true;
            }

            if ($ok) {
                $processed++;
            } else {
                $failed++;
            }
        }

        $this->Kt_saas_model->log_activity('tenant.bulk_action', $failed > 0 ? 'warning' : 'info', [
            'action'    => $action,
            'ids'       => $ids,
            'processed' => $processed,
            'failed'    => $failed,
        ]);

        if ($action === 'export') {
            $rows = [];
            foreach ($ids as $id) {
                $tenant = $this->Kt_saas_model->get_tenant($id);
                if ($tenant) {
                    $rows[] = $tenant;
                }
            }

            $this->streamCsv('kt_saas_tenants_selected.csv', ['ID', 'Tenant code', 'Company', 'Owner email', 'Status'], array_map(function ($t) {
                return [(int) $t['id'], (string) $t['tenant_code'], (string) $t['company_name'], (string) $t['owner_email'], (string) $t['status']];
            }, $rows));
            return;
        }

        $this->setBulkAlert($processed, $failed, 'Đã xử lý tenant hàng loạt.');
        redirect(admin_url('kt_saas/tenants'));
    }

    public function tenant_access($id)
    {
        $this->requireCapability('kt_saas_manage_tenants');

        $tenant = $this->Kt_saas_model->get_tenant((int) $id);
        if (!$tenant) {
            show_404();
        }

        require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantAdminAccessService.php');
        $service = new TenantAdminAccessService();

        if ($this->input->post('regenerate_onboarding')) {
            $result = $service->regenerateOnboarding($tenant);
            $emailSent = !empty($result['email_dispatch']['success']);
            set_alert(!empty($result['success']) && $emailSent ? 'success' : 'warning', !empty($result['success']) && $emailSent ? 'Đã gửi lại email hướng dẫn thiết lập mật khẩu.' : ($result['message'] ?? 'Không thể gửi lại email hướng dẫn thiết lập mật khẩu.'));
            redirect(admin_url('kt_saas/tenant_access/' . (int) $tenant['id']));
        }

        if ($this->input->post('manual_password_submit')) {
            $result = $service->setManualPassword($tenant, (string) $this->input->post('manual_password', false));
            set_alert(!empty($result['success']) ? 'success' : 'warning', !empty($result['success']) ? 'Đã cập nhật thủ công mật khẩu quản trị tenant.' : ($result['message'] ?? 'Không thể cập nhật mật khẩu quản trị tenant.'));
            redirect(admin_url('kt_saas/tenant_access/' . (int) $tenant['id']));
        }

        $profile = $service->getTenantAdminAccessProfile($tenant);

        $data['title'] = 'Truy cập tenant';
        $data['tenant'] = $tenant;
        $data['access_profile'] = $profile;
        $this->load->view(KT_SAAS_MODULE . '/dashboard/tenant_access', $data);
    }

    public function tenant_status($id, $status)
    {
        $this->requireCapability('kt_saas_manage_tenants');
        $this->requirePost();

        $success = in_array($status, ['trial', 'active', 'grace', 'suspended', 'terminated'], true)
            ? $this->Kt_saas_model->set_tenant_status((int) $id, $status)
            : false;

        set_alert($success ? 'success' : 'warning', $success ? 'Đã cập nhật trạng thái tenant.' : 'Không thể cập nhật trạng thái tenant.');
        redirect(admin_url('kt_saas/tenants'));
    }

    public function archive_tenant($id)
    {
        $this->requireCapability('kt_saas_manage_tenants');
        $this->requirePost();
        $success = $this->Kt_saas_model->archive_tenant((int) $id);
        set_alert($success ? 'success' : 'warning', $success ? 'Đã lưu trữ tenant.' : 'Không thể lưu trữ tenant.');
        redirect(admin_url('kt_saas/tenants'));
    }

    public function delete_tenant($id)
    {
        $this->requireCapability('kt_saas_delete_tenants');
        $this->requirePost();

        $tenant = $this->Kt_saas_model->get_tenant_for_lifecycle((int) $id);
        if (!$tenant) {
            show_404();
        }

        if (!empty($tenant['deleted_at'])) {
            set_alert('warning', 'Tenant da duoc xoa mem. Hay dung Purge neu can xoa vinh vien trong moi truong duoc phep.');
            redirect(admin_url('kt_saas/tenants?include_deleted=1'));
        }

        $confirmCode = strtoupper(trim((string) $this->input->post('confirm_code')));
        if ($confirmCode !== strtoupper((string) ($tenant['tenant_code'] ?? ''))) {
            set_alert('warning', 'Mã tenant xác nhận không khớp.');
            redirect(admin_url('kt_saas/tenants'));
        }

        $result = $this->Kt_saas_model->delete_tenant((int) $id, !empty($this->input->post('force_delete')));
        set_alert(!empty($result['success']) ? 'success' : 'warning', !empty($result['success']) ? 'Đã xóa mềm tenant.' : ($result['message'] ?? 'Không thể xóa tenant.'));
        redirect(admin_url('kt_saas/tenants'));
    }

    public function purge_tenant($id)
    {
        $this->requireCapability('kt_saas_delete_tenants');
        $this->requirePost();

        if (!$this->Kt_saas_model->tenant_purge_allowed()) {
            show_error('Hard purge is disabled in production.', 403);
        }

        $confirmation = trim((string) $this->input->post('purge_confirmation', true));
        $result = $this->Kt_saas_model->purge_tenant((int) $id, $confirmation);

        set_alert(
            !empty($result['success']) ? 'success' : 'warning',
            !empty($result['success'])
                ? 'Da xoa vinh vien tenant. Database, tep va ban ghi lien quan da duoc don theo whitelist.'
                : ($result['message'] ?? 'Khong the xoa vinh vien tenant.')
        );
        redirect(admin_url('kt_saas/tenants?include_deleted=1'));
    }

    public function tenant_orphans()
    {
        $this->requireCapability('kt_saas_delete_tenants');

        if (!$this->Kt_saas_model->tenant_purge_allowed()) {
            show_error('Orphan scan is disabled in production.', 403);
        }

        $data['title'] = _l('kt_saas_tenants');
        $data['show_deleted'] = true;
        $data['tenants'] = $this->Kt_saas_model->get_tenants(true);
        $data['plans'] = $this->Kt_saas_model->get_plans();
        $data['statuses'] = kt_saas_tenant_statuses();
        $data['edit_tenant'] = null;
        $data['can_delete_tenants'] = kt_saas_staff_can('kt_saas_delete_tenants');
        $data['can_purge_tenants'] = $data['can_delete_tenants'] && $this->Kt_saas_model->tenant_purge_allowed();
        $data['orphan_report'] = $this->Kt_saas_model->scan_orphan_tenant_data();
        $this->load->view(KT_SAAS_MODULE . '/dashboard/tenants', $data);
    }

    public function workspace_isolation_audit($id)
    {
        $this->requireCapability('kt_saas_manage_tenants');
        $result = $this->Kt_saas_model->run_workspace_isolation_audit((int) $id);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function workspace_isolation_audit_report($id)
    {
        $this->requireCapability('kt_saas_manage_tenants');
        $data['title'] = 'Workspace Isolation Audit';
        $data['result'] = $this->Kt_saas_model->run_workspace_isolation_audit((int) $id);
        $this->load->view(KT_SAAS_MODULE . '/dashboard/workspace_isolation_audit_report', $data);
    }

    public function plans($id = null)
    {
        $this->requireCapability('kt_saas_manage_plans');

        if ($this->input->post()) {
            $result = $this->Kt_saas_model->save_plan($this->input->post(), $id ?: null);
            $this->flashResult($result, 'Đã lưu gói dịch vụ.');
            redirect(admin_url('kt_saas/plans'));
        }

        $data['title'] = _l('kt_saas_plans');
        $data['plans'] = $this->Kt_saas_model->get_plans();
        $data['edit_plan'] = $id ? $this->Kt_saas_model->get_plan($id) : null;
        $data['workspace_feature_catalog'] = $this->Kt_saas_model->get_workspace_feature_catalog();
        $data['edit_plan_workspace_features'] = $id ? $this->Kt_saas_model->get_plan_workspace_feature_keys((int) $id) : [];
        $data['integration_feature_catalog'] = $this->Kt_saas_model->get_integration_feature_catalog();
        $data['edit_plan_integration_features'] = $id ? $this->Kt_saas_model->get_plan_integration_feature_keys((int) $id) : [];
        $data['billing_cycles'] = kt_saas_billing_cycles();
        $data['can_delete_plans'] = kt_saas_staff_can('kt_saas_delete_plans');
        $data['show_form'] = $id ? true : !empty($this->input->get('create'));
        $data['plan_dependency_map'] = [];
        foreach ($data['plans'] as $planRow) {
            $planId = (int) ($planRow['id'] ?? 0);
            if ($planId > 0) {
                $data['plan_dependency_map'][$planId] = $this->Kt_saas_model->get_plan_dependency_summary($planId);
            }
        }

        $data['available_modules'] = array_filter(
            $this->app_modules->get(),
            function ($module) {
                return $module['system_name'] !== KT_SAAS_MODULE;
            }
        );

        $this->load->view(KT_SAAS_MODULE . '/dashboard/plans', $data);
    }

    public function dms_packages($packageCode = null)
    {
        $this->requireCapability('kt_saas_manage_plans');

        if ($this->input->post()) {
            $result = $this->Kt_saas_model->save_dms_addon_package($this->input->post(), $packageCode);
            $this->flashResult($result, 'DMS package saved successfully.');
            redirect(admin_url('kt_saas/dms_packages'));
        }

        $catalog = $this->Kt_saas_model->get_dms_addon_package_catalog();
        $normalizedCode = strtolower(trim((string) $packageCode));

        $data['title'] = 'DMS Packages';
        $data['packages'] = $catalog;
        $data['edit_package'] = $normalizedCode !== '' && isset($catalog[$normalizedCode]) ? $catalog[$normalizedCode] : null;
        $data['show_form'] = $data['edit_package'] !== null || !empty($this->input->get('create'));
        $data['package_usage_map'] = [];
        foreach ($catalog as $code => $package) {
            $data['package_usage_map'][$code] = $this->Kt_saas_model->count_dms_addon_package_usage($code);
        }

        $this->load->view(KT_SAAS_MODULE . '/dashboard/dms_packages', $data);
    }

    public function delete_dms_package($packageCode)
    {
        $this->requireCapability('kt_saas_manage_plans');
        $this->requirePost();

        $result = $this->Kt_saas_model->delete_dms_addon_package($packageCode);
        set_alert(!empty($result['success']) ? 'success' : 'warning', !empty($result['success']) ? 'DMS package deleted successfully.' : ($result['message'] ?? 'Unable to delete DMS package.'));
        redirect(admin_url('kt_saas/dms_packages'));
    }

    public function bulk_plans()
    {
        $this->requireCapability('kt_saas_manage_plans');
        $this->requirePost();

        $action = trim((string) $this->input->post('bulk_action'));
        $ids = $this->normalizeBulkIds($this->input->post('ids'));
        if ($action === '' || empty($ids)) {
            set_alert('warning', 'Vui lòng chọn ít nhất một gói và một thao tác.');
            redirect(admin_url('kt_saas/plans'));
        }

        $processed = 0;
        $failed = 0;
        foreach ($ids as $id) {
            $ok = false;
            if ($action === 'hide') {
                $ok = $this->Kt_saas_model->set_plan_visibility($id, false);
            } elseif ($action === 'show') {
                $ok = $this->Kt_saas_model->set_plan_visibility($id, true);
            } elseif ($action === 'archive') {
                $ok = $this->Kt_saas_model->archive_plan($id);
            } elseif ($action === 'duplicate') {
                $result = $this->Kt_saas_model->duplicate_plan($id);
                $ok = !empty($result['success']);
            } elseif ($action === 'delete') {
                if (kt_saas_staff_can('kt_saas_delete_plans')) {
                    $result = $this->Kt_saas_model->delete_plan($id);
                    $ok = !empty($result['success']);
                }
            } elseif ($action === 'export') {
                $ok = true;
            }

            if ($ok) {
                $processed++;
            } else {
                $failed++;
            }
        }

        if ($action === 'export') {
            $rows = [];
            foreach ($ids as $id) {
                $plan = $this->Kt_saas_model->get_plan($id);
                if ($plan) {
                    $rows[] = $plan;
                }
            }
            $this->streamCsv('kt_saas_plans_selected.csv', ['ID', 'Plan code', 'Plan name', 'Price', 'Currency', 'Billing cycle', 'Active', 'Public'], array_map(function ($p) {
                return [(int) $p['id'], (string) $p['plan_code'], (string) $p['plan_name'], (string) $p['price'], (string) $p['currency'], (string) $p['billing_cycle'], (int) $p['is_active'], (int) $p['is_public']];
            }, $rows));
            return;
        }

        $this->setBulkAlert($processed, $failed, 'Đã xử lý gói hàng loạt.');
        redirect(admin_url('kt_saas/plans'));
    }

    public function plan_duplicate($id)
    {
        $this->requireCapability('kt_saas_manage_plans');
        $this->requirePost();
        $result = $this->Kt_saas_model->duplicate_plan((int) $id);
        set_alert(!empty($result['success']) ? 'success' : 'warning', !empty($result['success']) ? 'Đã nhân bản gói.' : ($result['message'] ?? 'Không thể nhân bản gói.'));
        redirect(admin_url('kt_saas/plans'));
    }

    public function plan_visibility($id, $mode)
    {
        $this->requireCapability('kt_saas_manage_plans');
        $this->requirePost();
        $ok = $this->Kt_saas_model->set_plan_visibility((int) $id, $mode === 'show');
        set_alert($ok ? 'success' : 'warning', $ok ? 'Đã cập nhật hiển thị gói.' : 'Không thể cập nhật hiển thị gói.');
        redirect(admin_url('kt_saas/plans'));
    }

    public function archive_plan($id)
    {
        $this->requireCapability('kt_saas_manage_plans');
        $this->requirePost();
        $ok = $this->Kt_saas_model->archive_plan((int) $id);
        set_alert($ok ? 'success' : 'warning', $ok ? 'Đã lưu trữ gói.' : 'Không thể lưu trữ gói.');
        redirect(admin_url('kt_saas/plans'));
    }

    public function rehydrate_plan_features()
    {
        $this->requireCapability('kt_saas_manage_plans');
        $this->requirePost();
        $result = $this->Kt_saas_model->rehydrate_all_plan_features();
        $this->flashResult($result, 'Plan features rehydrated.');
        redirect(admin_url('kt_saas/plans'));
    }

    public function delete_plan($id)
    {
        $this->requireCapability('kt_saas_delete_plans');
        $this->requirePost();
        $result = $this->Kt_saas_model->delete_plan((int) $id);
        set_alert(!empty($result['success']) ? 'success' : 'warning', !empty($result['success']) ? 'Đã xóa mềm gói.' : ($result['message'] ?? 'Không thể xóa gói.'));
        redirect(admin_url('kt_saas/plans'));
    }

    public function subscriptions($id = null)
    {
        $this->requireCapability('kt_saas_manage_billing');

        if ($this->input->post()) {
            $result = $this->Kt_saas_model->save_subscription($this->input->post(), $id ?: null);
            $this->flashResult($result, 'Đã lưu gói đăng ký.');
            redirect(admin_url('kt_saas/subscriptions'));
        }

        $data['title'] = _l('kt_saas_subscriptions');
        $data['subscriptions'] = $this->Kt_saas_model->get_subscriptions();
        $data['tenants'] = $this->Kt_saas_model->get_tenants();
        $data['plans'] = $this->Kt_saas_model->get_plans();
        $data['edit_subscription'] = $id ? $this->Kt_saas_model->get_subscription($id) : null;
        $data['statuses'] = kt_saas_subscription_statuses();
        $data['billing_cycles'] = kt_saas_billing_cycles();
        $this->load->view(KT_SAAS_MODULE . '/dashboard/subscriptions', $data);
    }

    public function bulk_subscriptions()
    {
        $this->requireCapability('kt_saas_manage_billing');
        $this->requirePost();

        $action = trim((string) $this->input->post('bulk_action'));
        $ids = $this->normalizeBulkIds($this->input->post('ids'));
        if ($action === '' || empty($ids)) {
            set_alert('warning', 'Vui lòng chọn ít nhất một gói đăng ký và một thao tác.');
            redirect(admin_url('kt_saas/subscriptions'));
        }

        $allowed = ['trial', 'active', 'grace', 'suspended', 'cancelled', 'terminated'];
        if (!in_array($action, $allowed, true)) {
            set_alert('warning', 'Thao tác hàng loạt không hợp lệ.');
            redirect(admin_url('kt_saas/subscriptions'));
        }

        $processed = 0;
        $failed = 0;
        foreach ($ids as $id) {
            if ($this->Kt_saas_model->set_subscription_status($id, $action)) {
                $processed++;
            } else {
                $failed++;
            }
        }

        $this->Kt_saas_model->log_activity('subscription.bulk_action', $failed > 0 ? 'warning' : 'info', [
            'action'    => $action,
            'ids'       => $ids,
            'processed' => $processed,
            'failed'    => $failed,
        ]);

        $this->setBulkAlert($processed, $failed, 'Đã xử lý gói đăng ký hàng loạt.');
        redirect(admin_url('kt_saas/subscriptions'));
    }

    public function invoices()
    {
        $this->requireCapability('kt_saas_manage_billing');
        require_once module_dir_path(KT_SAAS_MODULE, 'services/PaymentCollectionService.php');

        $payments = new PaymentCollectionService();
        $sepayEnabled = $this->app_modules->is_active('kt_sepay');
        $sepayGateway = null;
        if ($sepayEnabled) {
            $this->load->helper('kt_sepay/kt_sepay');
            $this->load->model('kt_sepay/Kt_sepay_model');
            $sepayEnabled = $this->Kt_sepay_model->is_active();
            if ($sepayEnabled) {
                $this->load->library('kt_sepay/Kt_sepay_gateway');
                $sepayGateway = $this->kt_sepay_gateway;
            }
        }

        $invoices = $this->Kt_saas_model->get_invoices();
        foreach ($invoices as &$invoice) {
            $invoice['checkout_url'] = !in_array((string) ($invoice['status'] ?? ''), ['paid', 'cancelled'], true)
                ? $payments->getCheckoutUrl($invoice)
                : '';
            $invoice['sepay_url'] = '';
            if (
                $sepayEnabled
                && $sepayGateway
                && strtoupper((string) ($invoice['currency'] ?? '')) === 'VND'
                && !in_array((string) ($invoice['status'] ?? ''), ['paid', 'cancelled'], true)
            ) {
                $tenant = $this->Kt_saas_model->get_tenant((int) ($invoice['tenant_id'] ?? 0));
                if ($tenant) {
                    $requestId = $sepayGateway->createKtSaasInvoiceRequest($invoice, $tenant, [
                        'source' => 'landlord_billing',
                    ]);
                    if ($requestId > 0) {
                        $request = $this->Kt_sepay_model->get_payment_request($requestId);
                        if ($request) {
                            $invoice['sepay_url'] = site_url('kt_sepay/pay/' . (int) $request['id'] . '/' . rawurlencode((string) $request['access_token']));
                        }
                    }
                }
            }
        }
        unset($invoice);

        $data['title'] = _l('kt_saas_invoices');
        $data['invoices'] = $invoices;
        $data['statuses'] = kt_saas_invoice_statuses();
        $data['sepay_enabled'] = $sepayEnabled;
        $this->load->view(KT_SAAS_MODULE . '/dashboard/invoices', $data);
    }

    public function bulk_invoices()
    {
        $this->requireCapability('kt_saas_manage_billing');
        $this->requirePost();

        $action = trim((string) $this->input->post('bulk_action'));
        $ids = $this->normalizeBulkIds($this->input->post('ids'));
        if ($action !== 'mark_paid' || empty($ids)) {
            set_alert('warning', 'Vui lòng chọn ít nhất một hóa đơn và một thao tác hợp lệ.');
            redirect(admin_url('kt_saas/invoices'));
        }

        require_once module_dir_path(KT_SAAS_MODULE, 'services/BillingEngineService.php');
        $service = new BillingEngineService();

        $processed = 0;
        $failed = 0;
        foreach ($ids as $id) {
            $invoice = $this->Kt_saas_model->get_invoice($id);
            if (!$invoice || in_array((string) ($invoice['status'] ?? ''), ['paid', 'cancelled'], true)) {
                $failed++;
                continue;
            }

            $result = $service->markInvoicePaid($invoice, [
                'gateway'           => 'manual',
                'payment_reference' => 'LANDLORD-BULK-' . date('YmdHis') . '-' . $id,
                'amount'            => (float) $invoice['grand_total'],
                'currency'          => $invoice['currency'],
            ]);

            if (!empty($result['success'])) {
                $processed++;
            } else {
                $failed++;
            }
        }

        $this->Kt_saas_model->log_activity('invoice.bulk_action', $failed > 0 ? 'warning' : 'info', [
            'action'    => $action,
            'ids'       => $ids,
            'processed' => $processed,
            'failed'    => $failed,
        ]);

        $this->setBulkAlert($processed, $failed, 'Đã xử lý hóa đơn hàng loạt.');
        redirect(admin_url('kt_saas/invoices'));
    }

    public function payments()
    {
        $this->requireCapability('kt_saas_manage_billing');

        $data['title'] = _l('kt_saas_payments');
        $data['payments'] = $this->Kt_saas_model->get_payments();
        $data['statuses'] = kt_saas_payment_statuses();
        $this->load->view(KT_SAAS_MODULE . '/dashboard/payments', $data);
    }

    public function mark_invoice_paid($invoiceId)
    {
        $this->requireCapability('kt_saas_manage_billing');

        $invoice = $this->Kt_saas_model->get_invoice((int) $invoiceId);
        if (!$invoice) {
            show_404();
        }

        require_once module_dir_path(KT_SAAS_MODULE, 'services/BillingEngineService.php');
        $service = new BillingEngineService();
        $result = $service->markInvoicePaid($invoice, [
            'gateway'           => 'manual',
            'payment_reference' => 'LANDLORD-' . date('YmdHis') . '-' . (int) $invoice['id'],
            'amount'            => (float) $invoice['grand_total'],
            'currency'          => $invoice['currency'],
        ]);

        set_alert(!empty($result['success']) ? 'success' : 'warning', !empty($result['success']) ? 'Đã ghi nhận hóa đơn đã thanh toán và kích hoạt lại tenant nếu đủ điều kiện.' : ($result['message'] ?? 'Không thể đánh dấu hóa đơn đã thanh toán.'));
        redirect(admin_url('kt_saas/invoices'));
    }

    public function domains($id = null)
    {
        $this->requireCapability('kt_saas_manage_domains');

        if ($this->input->post()) {
            $result = $this->Kt_saas_model->save_domain($this->input->post(), $id ?: null);
            $this->flashResult($result, 'Đã lưu tên miền.');
            redirect(admin_url('kt_saas/domains'));
        }

        $data['title'] = _l('kt_saas_domains');
        $data['domains'] = $this->Kt_saas_model->get_domains();
        $data['tenants'] = $this->Kt_saas_model->get_tenants();
        $data['edit_domain'] = $id ? $this->Kt_saas_model->get_domain($id) : null;
        $data['readiness_statuses'] = kt_saas_domain_readiness_statuses();
        $data['domain_statuses'] = kt_saas_domain_statuses();
        $this->load->view(KT_SAAS_MODULE . '/dashboard/domains', $data);
    }

    public function modules()
    {
        $this->requireCapability('kt_saas_manage_modules');
        $this->Kt_saas_model->sync_module_catalog();
        $this->Kt_saas_model->rebuild_module_registries();

        if ($this->input->post('sync_catalog')) {
            $count = $this->Kt_saas_model->sync_module_catalog();
            $this->Kt_saas_model->rebuild_module_registries();
            set_alert('success', 'Đã đồng bộ danh mục ứng dụng. Số bản ghi đã cập nhật: ' . (int) $count . '.');
            redirect(admin_url('kt_saas/modules'));
        }

        if ($this->input->post('catalog_submit')) {
            $moduleName = (string) $this->input->post('module_name');
            $isActive = $this->input->post('is_global_active') === '1';
            $result = $this->Kt_saas_model->set_module_catalog_status($moduleName, $isActive);
            set_alert(!empty($result['success']) ? 'success' : 'warning', !empty($result['success']) ? 'Đã cập nhật trạng thái danh mục ứng dụng.' : ($result['message'] ?? 'Không thể cập nhật danh mục ứng dụng.'));
            redirect(admin_url('kt_saas/modules'));
        }

        if ($this->input->post('tenant_override_submit')) {
            $tenantId = (int) $this->input->post('tenant_id');
            $moduleName = (string) $this->input->post('module_name');
            $mode = (string) $this->input->post('override_mode');
            $result = $this->Kt_saas_model->save_tenant_module_override($tenantId, $moduleName, $mode);
            set_alert(!empty($result['success']) ? 'success' : 'warning', !empty($result['success']) ? 'Đã cập nhật thiết lập ứng dụng riêng cho tenant.' : ($result['message'] ?? 'Không thể cập nhật thiết lập ứng dụng riêng cho tenant.'));
            redirect(admin_url('kt_saas/modules?tenant_id=' . $tenantId));
        }

        $tenantId = (int) $this->input->get('tenant_id');
        $data['title'] = _l('kt_saas_modules');
        $data['catalog'] = $this->Kt_saas_model->get_module_catalog();
        $data['tenants'] = $this->Kt_saas_model->get_tenants();
        $data['selected_tenant_id'] = $tenantId;
        $data['tenant_modules'] = $tenantId > 0 ? $this->Kt_saas_model->get_tenant_module_registry($tenantId) : [];
        $this->load->view(KT_SAAS_MODULE . '/dashboard/modules', $data);
    }

    public function backups()
    {
        $this->requireCapability('kt_saas_manage_backups');

        $tenantId = (int) $this->input->get('tenant_id');
        $data['title'] = _l('kt_saas_backups');
        $data['tenants'] = $this->Kt_saas_model->get_tenants();
        $data['selected_tenant_id'] = $tenantId;
        $data['backups'] = $this->Kt_saas_model->get_backups($tenantId > 0 ? $tenantId : null);
        $this->load->view(KT_SAAS_MODULE . '/dashboard/backups', $data);
    }

    public function create_backup($tenantId)
    {
        $this->requireCapability('kt_saas_manage_backups');

        require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantBackupService.php');
        $service = new TenantBackupService();
        $result = $service->createBackup((int) $tenantId);
        set_alert(!empty($result['success']) ? 'success' : 'warning', !empty($result['success']) ? 'Đã tạo bản sao lưu tenant.' : ($result['message'] ?? 'Không thể tạo bản sao lưu tenant.'));
        redirect(admin_url('kt_saas/backups?tenant_id=' . (int) $tenantId));
    }

    public function restore_backup($backupId)
    {
        $this->requireCapability('kt_saas_manage_backups');

        require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantBackupService.php');
        $service = new TenantBackupService();
        $result = $service->restoreBackup((int) $backupId);
        $tenantId = (int) ($result['tenant_id'] ?? 0);
        set_alert(!empty($result['success']) ? 'success' : 'warning', !empty($result['success']) ? 'Đã khôi phục bản sao lưu tenant.' : ($result['message'] ?? 'Không thể khôi phục bản sao lưu tenant.'));
        redirect(admin_url('kt_saas/backups' . ($tenantId > 0 ? '?tenant_id=' . $tenantId : '')));
    }

    public function download_backup($backupId)
    {
        $this->requireCapability('kt_saas_manage_backups');

        $backup = $this->Kt_saas_model->get_backup((int) $backupId);
        if (!$backup || empty($backup['file_path'])) {
            show_404();
        }

        $path = $backup['file_path'];
        if (!is_file($path)) {
            show_404();
        }

        $backupBase = realpath(module_dir_path(KT_SAAS_MODULE, 'storage/backups'));
        $realPath = realpath($path);
        if ($backupBase === false || $realPath === false || (strpos($realPath, $backupBase . DIRECTORY_SEPARATOR) !== 0 && $realPath !== $backupBase)) {
            show_404();
        }

        $expectedChecksum = trim((string) ($backup['checksum'] ?? ''));
        if ($expectedChecksum !== '' && !hash_equals($expectedChecksum, (string) hash_file('sha256', $realPath))) {
            set_alert('warning', 'Backup checksum mismatch. Download blocked.');
            redirect(admin_url('kt_saas/backups?tenant_id=' . (int) ($backup['tenant_id'] ?? 0)));
        }

        $this->Kt_saas_model->log_activity('backup.downloaded', 'info', [
            'backup_id' => (int) $backupId,
            'tenant_id' => (int) ($backup['tenant_id'] ?? 0),
            'file_path' => $realPath,
            'file_size_bytes' => (int) filesize($realPath),
            'checksum' => $expectedChecksum,
        ], !empty($backup['tenant_id']) ? (int) $backup['tenant_id'] : null);

        $this->load->helper('download');
        force_download(basename($realPath), file_get_contents($realPath));
    }

    public function verify_domain($id)
    {
        $this->requireCapability('kt_saas_manage_domains');

        $domain = $this->Kt_saas_model->get_domain((int) $id);
        if (!$domain) {
            show_404();
        }

        require_once module_dir_path(KT_SAAS_MODULE, 'services/DomainVerificationService.php');
        $service = new DomainVerificationService();
        $result = $service->verify($domain);

        $this->Kt_saas_model->save_domain_verification((int) $id, $result);

        if (!empty($result['success'])) {
            $message = 'Đã kiểm tra tên miền. Mức sẵn sàng: ' . strtoupper((string) $result['readiness_status']) . ', DNS: ' . strtoupper((string) $result['dns_status']) . ', SSL: ' . strtoupper((string) $result['ssl_status']) . '.';
            set_alert(($result['readiness_status'] ?? '') === 'ready' ? 'success' : 'warning', $message);
        } else {
            set_alert('warning', $result['message'] ?? 'Kiểm tra tên miền thất bại.');
        }

        redirect(admin_url('kt_saas/domains'));
    }

    public function verify_domains()
    {
        $this->requireCapability('kt_saas_manage_domains');

        require_once module_dir_path(KT_SAAS_MODULE, 'services/DomainVerificationService.php');
        $service = new DomainVerificationService();

        $domains = $this->Kt_saas_model->get_domains();
        $verified = 0;
        $warnings = 0;

        foreach ($domains as $domain) {
            $result = $service->verify($domain);
            $this->Kt_saas_model->save_domain_verification((int) $domain['id'], $result);

            if (($result['readiness_status'] ?? '') === 'ready') {
                $verified++;
            } else {
                $warnings++;
            }
        }

        set_alert($warnings === 0 ? 'success' : 'warning', 'Đã kiểm tra toàn bộ tên miền. Sẵn sàng: ' . $verified . ', cần xem lại: ' . $warnings . '.');
        redirect(admin_url('kt_saas/domains'));
    }

    public function provision_jobs()
    {
        $this->requireCapability('kt_saas_run_provisioning');

        $data['title'] = _l('kt_saas_provision_jobs');
        $data['jobs'] = $this->Kt_saas_model->get_provision_jobs();
        $this->load->view(KT_SAAS_MODULE . '/dashboard/provision_jobs', $data);
    }

    public function activity_logs()
    {
        $this->requireCapability('kt_saas_view');

        $page = max(1, (int) $this->input->get('page'));
        $perPage = (int) $this->input->get('per_page');
        if (!in_array($perPage, [25, 50, 100], true)) {
            $perPage = 50;
        }
        $filters = [
            'event_key' => trim((string) $this->input->get('event_key', true)),
            'severity' => trim((string) $this->input->get('severity', true)),
            'tenant_id' => (int) $this->input->get('tenant_id'),
        ];
        $total = $this->Kt_saas_model->count_activity_logs($filters);

        $data['title'] = _l('kt_saas_activity_logs');
        $data['logs'] = $this->Kt_saas_model->get_activity_logs_paginated($filters, $perPage, ($page - 1) * $perPage);
        $data['filters'] = $filters;
        $data['page'] = $page;
        $data['per_page'] = $perPage;
        $data['total'] = $total;
        $data['total_pages'] = max(1, (int) ceil($total / $perPage));
        $this->load->view(KT_SAAS_MODULE . '/dashboard/activity_logs', $data);
    }

    public function activity_logs_delete()
    {
        $this->requireCapability('kt_saas_delete_tenants');
        $this->requirePost();

        $result = $this->Kt_saas_model->purge_activity_logs([
            'mode' => (string) $this->input->post('mode', true),
            'days' => (int) $this->input->post('days'),
            'confirm' => (string) $this->input->post('confirm', true),
        ]);

        set_alert(
            !empty($result['success']) ? 'success' : 'warning',
            !empty($result['success'])
                ? 'Da xoa ' . (int) ($result['deleted'] ?? 0) . ' dong nhat ky hoat dong.'
                : ($result['message'] ?? 'Khong the xoa nhat ky hoat dong.')
        );
        redirect(admin_url('kt_saas/activity_logs'));
    }

    public function retry_provision_job($id)
    {
        $this->requireCapability('kt_saas_run_provisioning');
        $this->requirePost();
        $success = $this->Kt_saas_model->retry_provision_job((int) $id);
        set_alert($success ? 'success' : 'warning', $success ? 'Đã đưa lại tác vụ cấp phát vào hàng đợi.' : 'Không thể đưa lại tác vụ cấp phát vào hàng đợi.');
        redirect(admin_url('kt_saas/provision_jobs'));
    }

    public function run_provision_job($id)
    {
        $this->requireCapability('kt_saas_run_provisioning');
        $this->requirePost();

        require_once module_dir_path(KT_SAAS_MODULE, 'provisioning/ProvisioningJobRunner.php');
        $job = $this->Kt_saas_model->mark_provision_job_running((int) $id);
        if (!$job) {
            set_alert('warning', 'Không tìm thấy tác vụ cấp phát.');
            redirect(admin_url('kt_saas/provision_jobs'));
        }

        $runner = new ProvisioningJobRunner();
        $result = $runner->execute($job);
        if (!empty($result['success'])) {
            $this->Kt_saas_model->mark_provision_job_done((int) $id, $result);
            set_alert('success', 'Tác vụ cấp phát đã hoàn tất thành công.');
            redirect(admin_url('kt_saas/provision_jobs'));
        }

        $this->Kt_saas_model->mark_provision_job_failed((int) $id, $result['message'] ?? 'Cấp phát thất bại.', $result);
        set_alert('warning', $result['message'] ?? 'Cấp phát thất bại.');
        redirect(admin_url('kt_saas/provision_jobs'));
    }

    public function queue_provision_job($tenantId)
    {
        $this->requireCapability('kt_saas_run_provisioning');
        $this->requirePost();
        $tenant = $this->Kt_saas_model->get_tenant((int) $tenantId);
        if (!$tenant) {
            show_404();
        }

        if (strtolower(trim((string) ($tenant['status'] ?? ''))) === 'active'
            && strtolower(trim((string) ($tenant['provisioning_status'] ?? ''))) === 'done'
            && $this->Kt_saas_model->tenantConnectionMetadataReady($tenant)
        ) {
            set_alert('info', 'Tenant đã hoàn tất khởi tạo. Không cần xếp lại tác vụ cấp phát CRM.');
            redirect(admin_url('kt_saas/provision_jobs'));
        }

        $this->Kt_saas_model->create_provision_job((int) $tenantId, 'provision_tenant', [
            'tenant_id' => (int) $tenantId,
            'tenant_code' => $tenant['tenant_code'],
        ]);
        set_alert('success', 'Đã đưa tác vụ cấp phát vào hàng đợi.');
        redirect(admin_url('kt_saas/provision_jobs'));
    }

    public function backfill_reference_data($tenantId = 0)
    {
        $this->requireCapability('kt_saas_run_provisioning');

        require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantReferenceDataBackfillService.php');
        $service = new TenantReferenceDataBackfillService();

        $dryRunInput = $this->input->get('dry_run');
        $dryRun = $dryRunInput === null ? true : ((string) $dryRunInput !== '0');
        $tenantId = (int) $tenantId;

        $result = $service->run([
            'tenant_id' => $tenantId,
            'dry_run' => $dryRun,
        ]);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public function settings()
    {
        $this->requireCapability('kt_saas_manage_settings');

        if ($this->input->post()) {
            $result = $this->Kt_saas_model->save_settings($this->input->post());
            $this->flashResult($result, 'Đã lưu cấu hình.');
            redirect(admin_url('kt_saas/settings'));
        }

        $data['title'] = _l('kt_saas_settings');
        $data['global_email_provider'] = (string) get_option('kt_saas_global_email_provider');
        $data['global_sender_name'] = (string) get_option('kt_saas_global_sender_name');
        $data['global_sender_email'] = (string) get_option('kt_saas_global_sender_email');
        $data['global_reply_to_email'] = (string) get_option('kt_saas_global_reply_to_email');
        $data['global_email_fallback_policy'] = (string) get_option('kt_saas_global_email_fallback_policy');
        $data['global_brevo_smtp_host'] = (string) get_option('kt_saas_global_brevo_smtp_host');
        $data['global_brevo_smtp_port'] = (string) get_option('kt_saas_global_brevo_smtp_port');
        $data['global_brevo_smtp_encryption'] = (string) get_option('kt_saas_global_brevo_smtp_encryption');
        $data['global_brevo_smtp_user'] = (string) get_option('kt_saas_global_brevo_smtp_user');
        $data['global_brevo_smtp_has_password'] = get_option('kt_saas_global_brevo_smtp_pass_enc') !== '';
        $data['global_brevo_api_has_key'] = get_option('kt_saas_global_brevo_api_key_enc') !== '';
        $this->load->view(KT_SAAS_MODULE . '/dashboard/settings', $data);
    }

    public function settings_email_test()
    {
        $this->requireCapability('kt_saas_manage_settings');
        $this->requirePost();
        $this->load->model('emails_model');
        $email = trim((string) $this->input->post('test_email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_alert('warning', 'Invalid test email.');
            redirect(admin_url('kt_saas/settings'));
        }

        $subject = 'KT SaaS Global Email Test - ' . date('Y-m-d H:i:s');
        $message = 'Global provider connectivity test.';
        require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEmailProviderService.php');
        $providerService = new TenantEmailProviderService();
        $ctx = $providerService->resolveGlobal('notification');
        if (($ctx['provider'] ?? '') === 'blocked') {
            set_alert('warning', (string) ($ctx['error'] ?? 'Provider is blocked.'));
            redirect(admin_url('kt_saas/settings'));
        }

        $providerService->applyRuntimeTransport($ctx);
        $ok = $this->emails_model->send_simple_email($email, $subject, nl2br($message));
        $providerService->applyRuntimeTransport(['transport' => null]);
        $messageId = method_exists($this->emails_model, 'get_last_send_message_id') ? trim((string) $this->emails_model->get_last_send_message_id()) : '';
        $fromEmail = (string) ($ctx['from_email'] ?? get_option('smtp_email'));
        $fromName = (string) ($ctx['from_name'] ?? get_option('companyname'));
        $existingLog = $this->db
            ->where('tenant_id', $tenantId)
            ->where('recipient', $email)
            ->where('subject', $subject)
            ->get(db_prefix() . 'kt_saas_email_logs')
            ->row_array();
        if (!$existingLog && $sendException === '') {
            $providerService->logEmailResult(
                $email,
                $subject,
                $ok ? 'sent' : 'failed',
                $ok ? '' : (method_exists($this->emails_model, 'get_last_send_error') ? trim((string) $this->emails_model->get_last_send_error()) : 'Email send failed.'),
                (string) ($ctx['provider'] ?? ''),
                $tenantId,
                'notification',
                $messageId,
                $fromEmail,
                'tenant_email_test',
                (string) $tenantId
            );
        }
        if ($ok) {
            $parts = [
                'Global email test sent.',
                'provider=' . (string) ($ctx['provider'] ?? 'unknown'),
                'transport=' . (is_array($ctx['transport'] ?? null) && !empty($ctx['transport']['protocol']) ? (string) $ctx['transport']['protocol'] : 'n/a'),
                'from=' . $fromName . ' <' . $fromEmail . '>',
                'to=' . $email,
            ];
            if ($messageId !== '') {
                $parts[] = 'message_id=' . $messageId;
            }
            set_alert('success', implode(' | ', $parts));
        } else {
            $error = method_exists($this->emails_model, 'get_last_send_error') ? trim((string) $this->emails_model->get_last_send_error()) : '';
            $code = method_exists($this->emails_model, 'get_last_send_error_code') ? (int) $this->emails_model->get_last_send_error_code() : 0;
            $provider = (string) ($ctx['provider'] ?? get_option('kt_saas_global_email_provider') ?? 'unknown');
            $transportProtocol = is_array($ctx['transport'] ?? null) && !empty($ctx['transport']['protocol']) ? (string) $ctx['transport']['protocol'] : 'n/a';
            $transportHost = is_array($ctx['transport'] ?? null) && !empty($ctx['transport']['smtp_host']) ? (string) $ctx['transport']['smtp_host'] : 'n/a';
            $messageParts = [
                'Global email test failed.',
                'provider=' . $provider,
                'transport=' . $transportProtocol,
                'from=' . $fromName . ' <' . $fromEmail . '>',
                'to=' . $email,
                'host=' . $transportHost,
            ];
            if ($code > 0) {
                $messageParts[] = 'code=' . $code;
            }
            if ($error !== '') {
                $messageParts[] = $error;
            }
            $error = implode(' | ', $messageParts);
            set_alert('warning', $error);
        }
        redirect(admin_url('kt_saas/settings'));
    }

    public function tenant_subscription()
    {
        require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');

        $tenant = kt_saas_current_tenant();
        $service = new TenantEntitlementService();
        $profile = $service->getRuntimeProfile($tenant);
        $usage = $service->getTenantUsageSnapshot($tenant);
        $subscription = $this->Kt_saas_model->get_tenant_subscription_profile((int) $tenant['id']);

        $data['title'] = _l('kt_saas_my_subscription');
        $data['tenant'] = $tenant;
        $data['subscription'] = $subscription;
        $data['profile'] = $profile;
        $data['usage'] = $usage;
        $data['public_plans'] = $this->Kt_saas_model->get_public_plans();
        $metadata = json_decode((string) ($subscription['metadata_json'] ?? ''), true);
        $data['scheduled_plan_change'] = is_array($metadata) && !empty($metadata['scheduled_plan_change']) && is_array($metadata['scheduled_plan_change'])
            ? $metadata['scheduled_plan_change']
            : null;
        $data['open_plan_change_requests'] = !empty($subscription['id'])
            ? $this->Kt_saas_model->get_open_tenant_plan_change_requests((int) $tenant['id'], (int) $subscription['id'])
            : [];

        $this->load->view(KT_SAAS_MODULE . '/tenant/subscription', $data);
    }

    public function tenant_request_renewal()
    {
        if (strtolower((string) $this->input->method()) !== 'post') {
            redirect(admin_url('kt_saas/tenant_subscription'));
        }

        require_once module_dir_path(KT_SAAS_MODULE, 'services/BillingEngineService.php');

        $tenant = kt_saas_current_tenant();
        $subscription = $this->Kt_saas_model->get_tenant_subscription_profile((int) $tenant['id']);
        if (!$subscription || empty($subscription['plan_id'])) {
            set_alert('warning', 'Không tìm thấy hồ sơ gói đăng ký đang hoạt động.');
            redirect(admin_url('kt_saas/tenant_subscription'));
        }

        $plan = $this->Kt_saas_model->get_plan((int) $subscription['plan_id']);
        if (!$plan) {
            set_alert('warning', 'Gói hiện tại không còn khả dụng.');
            redirect(admin_url('kt_saas/tenant_subscription'));
        }

        $service = new BillingEngineService();
        $result = $service->createSubscriptionInvoice($tenant, $subscription, $plan, [
            'source' => 'tenant_portal',
            'reason' => 'subscription_renewal',
        ]);

        set_alert(!empty($result['success']) ? 'success' : 'warning', !empty($result['created']) ? _l('kt_saas_renewal_invoice_created') : _l('kt_saas_open_renewal_invoice_exists'));
        redirect(admin_url('kt_saas/tenant_billing'));
    }

    public function tenant_request_plan_change($planId)
    {
        if (strtolower((string) $this->input->method()) !== 'post') {
            redirect(admin_url('kt_saas/tenant_subscription'));
        }

        require_once module_dir_path(KT_SAAS_MODULE, 'services/BillingEngineService.php');

        $tenant = kt_saas_current_tenant();
        $subscription = $this->Kt_saas_model->get_tenant_subscription_profile((int) $tenant['id']);
        if (!$subscription || empty($subscription['id'])) {
            set_alert('warning', 'Không tìm thấy hồ sơ gói đăng ký đang hoạt động.');
            redirect(admin_url('kt_saas/tenant_subscription'));
        }

        $targetPlan = $this->Kt_saas_model->get_plan((int) $planId);
        if (!$targetPlan || (int) ($targetPlan['is_active'] ?? 0) !== 1 || (int) ($targetPlan['is_public'] ?? 0) !== 1) {
            set_alert('warning', 'Gói dịch vụ được yêu cầu hiện không khả dụng.');
            redirect(admin_url('kt_saas/tenant_subscription'));
        }

        if ((int) $targetPlan['id'] === (int) ($subscription['plan_id'] ?? 0)) {
            set_alert('warning', 'Bạn đang sử dụng chính gói này.');
            redirect(admin_url('kt_saas/tenant_subscription'));
        }

        $service = new BillingEngineService();
        $result = $service->createPlanChangeRequestInvoice($tenant, $subscription, $targetPlan, [
            'source' => 'tenant_portal',
            'requested_by_tenant_admin' => get_staff_user_id(),
        ]);

        if (!empty($result['scheduled'])) {
            set_alert('success', _l('kt_saas_plan_downgrade_scheduled'));
            redirect(admin_url('kt_saas/tenant_subscription'));
        }

        set_alert(!empty($result['success']) ? 'success' : 'warning', !empty($result['created']) ? _l('kt_saas_plan_change_requested') : _l('kt_saas_open_plan_change_request_exists'));
        redirect(admin_url('kt_saas/tenant_billing'));
    }

    public function tenant_billing()
    {
        require_once module_dir_path(KT_SAAS_MODULE, 'services/PaymentCollectionService.php');

        $tenant = kt_saas_current_tenant();
        $payments = new PaymentCollectionService();
        $sepayEnabled = $this->app_modules->is_active('kt_sepay');
        $sepayGateway = null;
        if ($sepayEnabled) {
            $this->load->helper('kt_sepay/kt_sepay');
            $this->load->model('kt_sepay/Kt_sepay_model');
            $sepayEnabled = $this->Kt_sepay_model->is_active();
            if ($sepayEnabled) {
                $this->load->library('kt_sepay/Kt_sepay_gateway');
                $sepayGateway = $this->kt_sepay_gateway;
            }
        }

        $invoices = $this->Kt_saas_model->get_tenant_billing_invoices((int) $tenant['id'], 100);
        foreach ($invoices as &$invoice) {
            $invoice['checkout_url'] = !in_array((string) ($invoice['status'] ?? ''), ['paid', 'cancelled'], true)
                ? $payments->getCheckoutUrl($invoice, $tenant)
                : '';
            $invoice['sepay_url'] = '';
            if (
                $sepayEnabled
                && $sepayGateway
                && strtoupper((string) ($invoice['currency'] ?? '')) === 'VND'
                && !in_array((string) ($invoice['status'] ?? ''), ['paid', 'cancelled'], true)
            ) {
                $requestId = $sepayGateway->createKtSaasInvoiceRequest($invoice, $tenant, [
                    'source' => 'tenant_portal',
                ]);
                if ($requestId > 0) {
                    $invoice['sepay_url'] = admin_url('kt_sepay/tenant_payment/' . $requestId);
                }
            }
        }
        unset($invoice);

        $data['title'] = _l('kt_saas_my_billing');
        $data['tenant'] = $tenant;
        $data['invoices'] = $invoices;
        $data['payments'] = $this->Kt_saas_model->get_tenant_billing_payments((int) $tenant['id'], 100);
        $data['webhook_url_manual'] = $payments->getWebhookUrl('manual');
        $data['sepay_enabled'] = $sepayEnabled;

        $this->load->view(KT_SAAS_MODULE . '/tenant/billing', $data);
    }

    public function tenant_usage()
    {
        require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');

        $tenant = kt_saas_current_tenant();
        $service = new TenantEntitlementService();
        $profile = $service->getRuntimeProfile($tenant);
        $usage = $service->getTenantUsageSnapshot($tenant);

        $data['title'] = _l('kt_saas_my_usage');
        $data['tenant'] = $tenant;
        $data['profile'] = $profile;
        $data['usage'] = $usage;
        $data['history'] = $this->Kt_saas_model->get_tenant_usage_history((int) $tenant['id'], 100);

        $this->load->view(KT_SAAS_MODULE . '/tenant/usage', $data);
    }

    public function tenant_settings()
    {
        require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');
        $tenant = kt_saas_current_tenant();
        $entitlementService = new TenantEntitlementService();
        $tenantId = (int) ($tenant['id'] ?? 0);
        $tenantEmailEntitlements = [
            'own_credentials' => (bool) $entitlementService->getFeatureValue($tenantId, 'email.own_credentials', false),
            'custom_sender' => (bool) $entitlementService->getFeatureValue($tenantId, 'email.custom_sender', false),
            'custom_smtp' => (bool) $entitlementService->getFeatureValue($tenantId, 'email.custom_smtp', false),
            'brevo_smtp' => (bool) $entitlementService->getFeatureValue($tenantId, 'email.brevo_smtp', false),
            'brevo_api' => (bool) $entitlementService->getFeatureValue($tenantId, 'email.brevo_api', false),
        ];
        $canEditBranding = kt_saas_workspace_feature_allowed('workspace.branding.edit', false);
        $canEditCompanyProfile = kt_saas_workspace_feature_allowed('workspace.company.edit', false);
        $canEditLocalization = kt_saas_workspace_feature_allowed('workspace.localization.edit', false);
        $canEditFinance = kt_saas_workspace_feature_allowed('workspace.finance.edit', false);
        $canEditFinanceAdvanced = kt_saas_workspace_feature_allowed('workspace.finance.advanced.edit', false);
        $canEditMailIdentity = kt_saas_workspace_feature_allowed('workspace.mail.identity.edit', false);
        $canEditNotifications = kt_saas_workspace_feature_allowed('workspace.notifications.edit', false);
        $canViewGovernance = kt_saas_workspace_feature_allowed('workspace.governance.view', false);
        $canManageGovernance = kt_saas_workspace_feature_allowed('workspace.governance.manage', false);
        $data['title'] = 'Workspace Settings';
        $data['tenant'] = $tenant;
        $data['settings'] = $this->Kt_saas_model->get_tenant_workspace_settings((int) $tenant['id']);
        $data['staff_members'] = $this->Kt_saas_model->get_active_tenant_staff_members();
        $data['form_options'] = $this->Kt_saas_model->get_tenant_workspace_form_options();
        $data['localization_warning'] = '';
        if (
            !empty($data['settings']['active_language'])
            && !in_array((string) $data['settings']['active_language'], (array) ($data['form_options']['languages'] ?? []), true)
        ) {
            $data['localization_warning'] = 'Current tenant language is not in enabled language list. It will fall back to english when saved.';
        }
        $data['can_edit_branding'] = $canEditBranding;
        $data['can_edit_company_profile'] = $canEditCompanyProfile;
        $data['can_edit_localization'] = $canEditLocalization;
        $data['can_edit_finance'] = $canEditFinance;
        $data['can_edit_finance_advanced'] = $canEditFinanceAdvanced;
        $data['can_edit_mail_identity'] = $canEditMailIdentity;
        $data['can_edit_notifications'] = $canEditNotifications;
        $data['can_view_governance'] = $canViewGovernance;
        $data['can_manage_governance'] = $canManageGovernance;
        $data['quick_links'] = array_values(array_filter([
            ['label' => _l('kt_saas_my_subscription'), 'href' => admin_url('kt_saas/tenant_subscription'), 'visible' => is_admin()],
            ['label' => _l('kt_saas_my_billing'), 'href' => admin_url('kt_saas/tenant_billing'), 'visible' => is_admin()],
            ['label' => _l('kt_saas_my_usage'), 'href' => admin_url('kt_saas/tenant_usage'), 'visible' => is_admin()],
            ['label' => _l('kt_saas_users_roles'), 'href' => admin_url('kt_saas/tenant_governance'), 'visible' => function_exists('kt_saas_can_view_workspace_governance') && kt_saas_can_view_workspace_governance()],
            ['label' => _l('kt_saas_activity_logs'), 'href' => admin_url('kt_saas/tenant_activity_logs'), 'visible' => true],
        ], static function ($link) {
            return !empty($link['visible']);
        }));
        $data['tenant_email_entitlements'] = $tenantEmailEntitlements;
        $data['tenant_email_settings'] = $this->Kt_saas_model->get_tenant_email_setting($tenantId);
        $data['global_email_provider'] = (string) get_option('kt_saas_global_email_provider');
        $data['global_sender_name'] = (string) get_option('kt_saas_global_sender_name');
        $data['global_reply_to_email'] = (string) get_option('kt_saas_global_reply_to_email');

        $this->load->view(KT_SAAS_MODULE . '/tenant/settings', $data);
    }

    public function tenant_settings_profile_save()
    {
        $this->saveTenantWorkspaceSection('saveTenantProfile', 'Đã lưu hồ sơ doanh nghiệp.', 'workspace.company.edit');
    }

    public function tenant_settings_localization_save()
    {
        $this->saveTenantWorkspaceSection('saveTenantLocalization', 'Đã lưu ngôn ngữ và định dạng.', 'workspace.localization.edit');
    }

    public function tenant_settings_email_identity_save()
    {
        $this->saveTenantWorkspaceSection('saveTenantEmailIdentity', 'Đã lưu nhận diện email.', 'workspace.mail.identity.edit');
    }

    public function tenant_settings_invoice_save()
    {
        $this->saveTenantWorkspaceSection('saveTenantInvoiceDefaults', 'Đã lưu cấu hình hóa đơn.', 'workspace.finance.edit');
    }

    public function tenant_settings_finance_save()
    {
        $this->saveTenantWorkspaceSection('saveTenantFinanceAdvanced', 'Đã lưu cấu hình tài chính nâng cao.', 'workspace.finance.advanced.edit');
    }

    public function tenant_settings_branding_save()
    {
        $this->saveTenantWorkspaceSection('saveTenantBranding', 'Đã lưu thương hiệu.', 'workspace.branding.edit');
    }

    public function tenant_settings_notifications_save()
    {
        $this->saveTenantWorkspaceSection('saveTenantNotifications', 'Đã lưu thông báo.', 'workspace.notifications.edit');
    }

    public function tenant_settings_governance_save()
    {
        $this->saveTenantWorkspaceSection('saveTenantGovernance', 'Đã lưu truy cập và điều phối.', 'workspace.governance.manage');
    }

    public function tenant_email_settings_save()
    {
        $this->requirePost();
        require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');
        $tenant = kt_saas_current_tenant();
        $tenantId = (int) ($tenant['id'] ?? 0);
        $entitlementService = new TenantEntitlementService();
        $entitlements = [
            'own_credentials' => (bool) $entitlementService->getFeatureValue($tenantId, 'email.own_credentials', false),
            'custom_sender' => (bool) $entitlementService->getFeatureValue($tenantId, 'email.custom_sender', false),
            'custom_smtp' => (bool) $entitlementService->getFeatureValue($tenantId, 'email.custom_smtp', false),
            'brevo_smtp' => (bool) $entitlementService->getFeatureValue($tenantId, 'email.brevo_smtp', false),
            'brevo_api' => (bool) $entitlementService->getFeatureValue($tenantId, 'email.brevo_api', false),
        ];
        if (empty($entitlements['own_credentials']) && empty($entitlements['custom_sender'])) {
            set_alert('warning', 'Your plan does not allow tenant email configuration.');
            redirect(admin_url('kt_saas/tenant_settings'));
        }

        $result = $this->Kt_saas_model->save_tenant_email_settings($tenantId, $this->input->post(), $entitlements);
        $this->flashResult($result, 'Tenant email settings saved.');
        redirect(admin_url('kt_saas/tenant_settings'));
    }

    public function tenant_email_settings_reset()
    {
        $this->requirePost();
        require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');
        $tenant = kt_saas_current_tenant();
        $tenantId = (int) ($tenant['id'] ?? 0);
        $entitlementService = new TenantEntitlementService();
        $entitlements = [
            'own_credentials' => (bool) $entitlementService->getFeatureValue($tenantId, 'email.own_credentials', false),
            'custom_sender' => (bool) $entitlementService->getFeatureValue($tenantId, 'email.custom_sender', false),
            'custom_smtp' => (bool) $entitlementService->getFeatureValue($tenantId, 'email.custom_smtp', false),
            'brevo_smtp' => (bool) $entitlementService->getFeatureValue($tenantId, 'email.brevo_smtp', false),
            'brevo_api' => (bool) $entitlementService->getFeatureValue($tenantId, 'email.brevo_api', false),
        ];
        if (empty($entitlements['own_credentials']) && empty($entitlements['custom_sender'])) {
            set_alert('warning', 'Your plan does not allow tenant email configuration.');
            redirect(admin_url('kt_saas/tenant_settings'));
        }
        $row = $this->Kt_saas_model->get_tenant_email_setting($tenantId);
        if ($row) {
            $this->Kt_saas_model->save_tenant_email_settings($tenantId, [
                'provider' => 'system_smtp',
                'fallback_policy' => 'use_landlord',
                'is_active' => 0,
                'sender_name' => (string) ($row['sender_name'] ?? ''),
                'sender_email' => (string) ($row['sender_email'] ?? ''),
                'reply_to_email' => (string) ($row['reply_to_email'] ?? ''),
            ], $entitlements);
        }
        set_alert('success', 'Tenant email provider switched to landlord global.');
        redirect(admin_url('kt_saas/tenant_settings'));
    }

    public function tenant_email_settings_test()
    {
        $this->requirePost();
        $this->load->model('emails_model');
        $tenant = kt_saas_current_tenant();
        require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');
        $entitlementService = new TenantEntitlementService();
        $tenantId = (int) ($tenant['id'] ?? 0);
        $canOwnCreds = (bool) $entitlementService->getFeatureValue($tenantId, 'email.own_credentials', false);
        $canCustomSender = (bool) $entitlementService->getFeatureValue($tenantId, 'email.custom_sender', false);
        if (!$canOwnCreds && !$canCustomSender) {
            set_alert('warning', 'Your plan does not allow tenant email configuration.');
            redirect(admin_url('kt_saas/tenant_settings'));
        }
        $email = trim((string) $this->input->post('test_email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_alert('warning', 'Invalid test email.');
            redirect(admin_url('kt_saas/tenant_settings'));
        }

        require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEmailProviderService.php');
        $providerService = new TenantEmailProviderService();
        $ctx = $providerService->resolveForTenant((int) ($tenant['id'] ?? 0), 'transactional');
        if (($ctx['provider'] ?? '') === 'blocked') {
            set_alert('warning', (string) ($ctx['error'] ?? 'Provider is blocked.'));
            redirect(admin_url('kt_saas/tenant_settings'));
        }
        $subject = 'KT SaaS Tenant Email Test - ' . date('Y-m-d H:i:s');
        $ctx['tenant_id'] = $tenantId;
        $ctx['related_type'] = 'tenant_email_test';
        $ctx['related_id'] = (string) $tenantId;
        $ctx['event_key'] = 'tenant_email_settings_test';
        $ctx['dedupe_key'] = '';

        $ok = false;
        $sendException = '';
        try {
            $providerService->applyRuntimeTransport($ctx);
            $ok = $this->emails_model->send_simple_email(
                $email,
                $subject,
                'Tenant provider connectivity test.'
            );
        } catch (Throwable $e) {
            $sendException = $e->getMessage();
            $providerService->logEmailResult(
                $email,
                $subject,
                'failed',
                $sendException,
                (string) ($ctx['provider'] ?? ''),
                $tenantId,
                'notification',
                '',
                (string) ($ctx['from_email'] ?? ''),
                'tenant_email_test',
                (string) $tenantId
            );
        } finally {
            $providerService->applyRuntimeTransport(['transport' => null]);
        }

        $messageId = method_exists($this->emails_model, 'get_last_send_message_id') ? trim((string) $this->emails_model->get_last_send_message_id()) : '';
        $fromEmail = (string) ($ctx['from_email'] ?? get_option('smtp_email'));
        $fromName = (string) ($ctx['from_name'] ?? get_option('companyname'));
        if ($ok) {
            $parts = [
                'Tenant email test sent.',
                'provider=' . (string) ($ctx['provider'] ?? 'unknown'),
                'transport=' . (is_array($ctx['transport'] ?? null) && !empty($ctx['transport']['protocol']) ? (string) $ctx['transport']['protocol'] : 'n/a'),
                'from=' . $fromName . ' <' . $fromEmail . '>',
                'to=' . $email,
            ];
            if ($messageId !== '') {
                $parts[] = 'message_id=' . $messageId;
            }
            set_alert('success', implode(' | ', $parts));
        } else {
            $error = $sendException !== '' ? $sendException : (method_exists($this->emails_model, 'get_last_send_error') ? trim((string) $this->emails_model->get_last_send_error()) : '');
            $code = method_exists($this->emails_model, 'get_last_send_error_code') ? (int) $this->emails_model->get_last_send_error_code() : 0;
            $provider = (string) ($ctx['provider'] ?? 'unknown');
            $transportProtocol = is_array($ctx['transport'] ?? null) && !empty($ctx['transport']['protocol']) ? (string) $ctx['transport']['protocol'] : 'n/a';
            $transportHost = is_array($ctx['transport'] ?? null) && !empty($ctx['transport']['smtp_host']) ? (string) $ctx['transport']['smtp_host'] : 'n/a';
            $messageParts = [
                'Tenant email test failed.',
                'provider=' . $provider,
                'transport=' . $transportProtocol,
                'from=' . $fromName . ' <' . $fromEmail . '>',
                'to=' . $email,
                'host=' . $transportHost,
            ];
            if ($code > 0) {
                $messageParts[] = 'code=' . $code;
            }
            if ($error !== '') {
                $messageParts[] = $error;
            }
            set_alert('warning', implode(' | ', $messageParts));
        }
        redirect(admin_url('kt_saas/tenant_settings'));
    }

    public function tenant_activity_logs()
    {
        $tenant = kt_saas_current_tenant();
        $filters = [
            'event_key' => trim((string) $this->input->get('event_key')),
            'severity'  => trim((string) $this->input->get('severity')),
        ];

        $data['title'] = _l('kt_saas_activity_logs');
        $data['tenant'] = $tenant;
        $data['filters'] = $filters;
        $data['logs'] = $this->Kt_saas_model->get_tenant_activity_logs((int) ($tenant['id'] ?? 0), $filters, 200);
        $this->load->view(KT_SAAS_MODULE . '/tenant/activity_logs', $data);
    }

    public function tenant_governance()
    {
        require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');
        $this->requireTenantWorkspaceFeature('workspace.governance.view');

        $tenant = kt_saas_current_tenant();
        $service = new TenantEntitlementService();
        $profile = $service->getRuntimeProfile($tenant);
        $usage = $service->getTenantUsageSnapshot($tenant);

        $this->load->model('roles_model');

        $summary = $this->Kt_saas_model->get_tenant_governance_summary();
        $staffMembers = $this->Kt_saas_model->get_active_tenant_staff_members();
        $roles = $this->Kt_saas_model->get_tenant_roles();
        $roleUsage = $this->Kt_saas_model->get_tenant_role_usage_counts();

        foreach ($roles as &$role) {
            $role['assigned_staff_count'] = (int) ($roleUsage[(int) ($role['roleid'] ?? 0)] ?? 0);
            $permissions = [];
            if (!empty($role['permissions'])) {
                $decoded = @unserialize($role['permissions']);
                if (is_array($decoded)) {
                    $permissions = $decoded;
                }
            }

            $capabilityCount = 0;
            foreach ($permissions as $featureCapabilities) {
                if (is_array($featureCapabilities)) {
                    $capabilityCount += count($featureCapabilities);
                }
            }

            $role['capability_count'] = $capabilityCount;
        }
        unset($role);

        $data['title'] = _l('kt_saas_users_roles');
        $data['tenant'] = $tenant;
        $data['profile'] = $profile;
        $data['usage'] = $usage;
        $data['summary'] = $summary;
        $data['staff_members'] = $staffMembers;
        $data['roles'] = $roles;
        $data['staff_limit'] = $profile['limits']['staff'] ?? null;
        $data['can_manage_governance'] = kt_saas_can_manage_workspace_governance();
        $data['can_manage_roles'] = kt_saas_can_manage_workspace_governance() && kt_saas_workspace_feature_allowed('workspace.roles.manage', false);
        $data['can_view_staff_directory'] = staff_can('view', 'staff');
        $data['can_create_staff'] = staff_can('create', 'staff');
        $data['can_manage_departments'] = kt_saas_can_manage_workspace_governance() && kt_saas_workspace_feature_allowed('workspace.departments.manage', false);
        $data['can_view_departments'] = kt_saas_workspace_feature_allowed('workspace.departments.manage', false);
        $this->load->view(KT_SAAS_MODULE . '/tenant/governance', $data);
    }

    public function tenant_role($id = '')
    {
        $this->requireTenantWorkspaceFeature('workspace.governance.view');
        $tenant = kt_saas_current_tenant();
        $this->load->model('roles_model');

        $id = is_numeric($id) ? (int) $id : 0;
        if ($id === 0 && !kt_saas_can_manage_workspace_governance()) {
            access_denied(KT_SAAS_MODULE);
        }

        if ($this->input->post()) {
            $this->requireTenantWorkspaceGovernanceManageAccess();
            $this->requireTenantWorkspaceFeature('workspace.roles.manage');

            if ($id === 0) {
                $currentRoles = count($this->Kt_saas_model->get_tenant_roles());
                $service = new TenantEntitlementService();
                $service->assertWithinLimit('roles', $currentRoles + 1);
                $roleId = $this->roles_model->add($this->input->post());
                if ($roleId) {
                    $service->persistUsageSnapshot($tenant);
                    $this->Kt_saas_model->log_activity('tenant.role_created', 'info', [
                        'tenant_id' => (int) ($tenant['id'] ?? 0),
                        'role_id'   => (int) $roleId,
                        'name'      => trim((string) $this->input->post('name')),
                    ], (int) ($tenant['id'] ?? 0));
                    set_alert('success', _l('added_successfully', _l('role')));
                    redirect(admin_url('kt_saas/tenant_role/' . $roleId));
                }
            } else {
                $success = $this->roles_model->update($this->input->post(), $id);
                if ($success) {
                    $service = new TenantEntitlementService();
                    $service->persistUsageSnapshot($tenant);
                    $this->Kt_saas_model->log_activity('tenant.role_updated', 'info', [
                        'tenant_id' => (int) ($tenant['id'] ?? 0),
                        'role_id'   => (int) $id,
                        'name'      => trim((string) $this->input->post('name')),
                    ], (int) ($tenant['id'] ?? 0));
                    set_alert('success', _l('updated_successfully', _l('role')));
                }
                redirect(admin_url('kt_saas/tenant_role/' . $id));
            }
        }

        $data['tenant'] = $tenant;
        $data['governance_url'] = admin_url('kt_saas/tenant_governance');
        $data['can_manage_role'] = kt_saas_can_manage_workspace_governance();
        if ($id === 0) {
            $data['title'] = _l('add_new', _l('role'));
        } else {
            $role = $this->roles_model->get($id);
            if (!$role) {
                show_404();
            }

            $data['role'] = $role;
            $data['role_staff'] = $this->roles_model->get_role_staff($id);
            $data['title'] = _l('edit', _l('role')) . ' ' . $role->name;
        }

        $this->load->view(KT_SAAS_MODULE . '/tenant/role', $data);
    }

    public function tenant_departments()
    {
        $this->requireTenantWorkspaceFeature('workspace.governance.view');
        $tenant = kt_saas_current_tenant();
        $this->load->model('departments_model');

        if ($this->input->post()) {
            $this->requireTenantWorkspaceGovernanceManageAccess();
            $this->requireTenantWorkspaceFeature('workspace.departments.manage');

            $payload = $this->input->post();
            $payload['password'] = $this->input->post('password', false);

            if (isset($payload['fakeusernameremembered']) || isset($payload['fakepasswordremembered'])) {
                unset($payload['fakeusernameremembered'], $payload['fakepasswordremembered']);
            }

            $departmentId = (int) ($payload['id'] ?? 0);
            $success = false;
            if ($departmentId > 0) {
                unset($payload['id']);
                $success = $this->departments_model->update($payload, $departmentId);
                if ($success) {
                    require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');
                    $service = new TenantEntitlementService();
                    $service->persistUsageSnapshot($tenant);
                    $this->Kt_saas_model->log_activity('tenant.department_updated', 'info', [
                        'tenant_id'     => (int) ($tenant['id'] ?? 0),
                        'department_id' => $departmentId,
                        'name'          => trim((string) ($payload['name'] ?? '')),
                    ], (int) ($tenant['id'] ?? 0));
                    set_alert('success', _l('updated_successfully', _l('department')));
                }
            } else {
                require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');
                $service = new TenantEntitlementService();
                $currentDepartments = is_array($this->departments_model->get()) ? count($this->departments_model->get()) : 0;
                $service->assertWithinLimit('departments', $currentDepartments + 1);
                $departmentId = (int) $this->departments_model->add($payload);
                $success = $departmentId > 0;
                if ($success) {
                    $service->persistUsageSnapshot($tenant);
                    $this->Kt_saas_model->log_activity('tenant.department_created', 'info', [
                        'tenant_id'     => (int) ($tenant['id'] ?? 0),
                        'department_id' => $departmentId,
                        'name'          => trim((string) ($payload['name'] ?? '')),
                    ], (int) ($tenant['id'] ?? 0));
                    set_alert('success', _l('added_successfully', _l('department')));
                }
            }

            if (!$success) {
                set_alert('warning', _l('problem_updating', _l('department_lowercase')));
            }

            redirect(admin_url('kt_saas/tenant_departments'));
        }

        $editId = (int) $this->input->get('edit');
        $departments = $this->departments_model->get();
        $departmentUsage = [];
        $staffMembers = $this->Kt_saas_model->get_active_tenant_staff_members();
        foreach ($staffMembers as $staffMember) {
            $assignedDepartments = $this->departments_model->get_staff_departments((int) ($staffMember['staffid'] ?? 0), true);
            foreach ($assignedDepartments as $departmentId) {
                $departmentId = (int) $departmentId;
                $departmentUsage[$departmentId] = (int) ($departmentUsage[$departmentId] ?? 0) + 1;
            }
        }

        $editDepartment = null;
        if ($editId > 0) {
            $editDepartment = $this->departments_model->get($editId);
        }

        $data['title'] = _l('departments');
        $data['tenant'] = $tenant;
        $data['departments'] = is_array($departments) ? $departments : [];
        $data['department_usage'] = $departmentUsage;
        $data['edit_department'] = $editDepartment;
        $data['can_manage_departments'] = kt_saas_can_manage_workspace_governance();
        $this->load->view(KT_SAAS_MODULE . '/tenant/departments', $data);
    }

    public function tenant_delete_role($id)
    {
        $this->requirePost();
        $this->requireTenantWorkspaceGovernanceManageAccess();
        $this->requireTenantWorkspaceFeature('workspace.roles.manage');

        $tenant = kt_saas_current_tenant();
        $this->load->model('roles_model');

        $id = (int) $id;
        if ($id <= 0) {
            redirect(admin_url('kt_saas/tenant_governance'));
        }

        $response = $this->roles_model->delete($id);
        if (is_array($response) && isset($response['referenced'])) {
            set_alert('warning', _l('is_referenced', _l('role_lowercase')));
        } elseif ($response === true) {
            require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');
            $service = new TenantEntitlementService();
            $service->persistUsageSnapshot($tenant);
            $this->Kt_saas_model->log_activity('tenant.role_deleted', 'warning', [
                'tenant_id' => (int) ($tenant['id'] ?? 0),
                'role_id'   => $id,
            ], (int) ($tenant['id'] ?? 0));
            set_alert('success', _l('deleted', _l('role')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('role_lowercase')));
        }

        redirect(admin_url('kt_saas/tenant_governance'));
    }

    public function tenant_delete_department($id)
    {
        $this->requirePost();
        $this->requireTenantWorkspaceGovernanceManageAccess();
        $this->requireTenantWorkspaceFeature('workspace.departments.manage');

        $tenant = kt_saas_current_tenant();
        $this->load->model('departments_model');

        $id = (int) $id;
        if ($id <= 0) {
            redirect(admin_url('kt_saas/tenant_departments'));
        }

        $response = $this->departments_model->delete($id);
        if (is_array($response) && isset($response['referenced'])) {
            set_alert('warning', _l('is_referenced', _l('department_lowercase')));
        } elseif ($response === true) {
            require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');
            $service = new TenantEntitlementService();
            $service->persistUsageSnapshot($tenant);
            $this->Kt_saas_model->log_activity('tenant.department_deleted', 'warning', [
                'tenant_id'     => (int) ($tenant['id'] ?? 0),
                'department_id' => $id,
            ], (int) ($tenant['id'] ?? 0));
            set_alert('success', _l('deleted', _l('department')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('department_lowercase')));
        }

        redirect(admin_url('kt_saas/tenant_departments'));
    }

    public function tenant_remove_company_logo($type = '')
    {
        $this->requirePost();
        $this->requireTenantWorkspaceFeature('workspace.branding.edit');
        $this->Kt_saas_model->remove_tenant_workspace_branding($type === 'dark' ? 'company_logo_dark' : 'company_logo');
        redirect(admin_url('kt_saas/tenant_settings'));
    }

    public function tenant_remove_favicon()
    {
        $this->requirePost();
        $this->requireTenantWorkspaceFeature('workspace.branding.edit');
        $this->Kt_saas_model->remove_tenant_workspace_branding('favicon');
        redirect(admin_url('kt_saas/tenant_settings'));
    }

    public function recalculate_usage()
    {
        $this->requireCapability('kt_saas_manage_usage');

        require_once module_dir_path(KT_SAAS_MODULE, 'services/UsageSnapshotRunner.php');
        $runner = new UsageSnapshotRunner();
        $result = $runner->recalculateAll(100);

        if (!empty($result['failed'])) {
            set_alert('warning', 'Đã tính lại dữ liệu sử dụng nhưng có ' . (int) $result['failed'] . ' tenant bị lỗi.');
            redirect(admin_url('kt_saas'));
        }

        set_alert('success', 'Đã tính lại dữ liệu sử dụng cho ' . (int) $result['successful'] . ' tenant.');
        redirect(admin_url('kt_saas'));
    }

    public function cleanup_usage()
    {
        $this->requireCapability('kt_saas_manage_usage');

        require_once module_dir_path(KT_SAAS_MODULE, 'services/UsageRetentionService.php');
        $service = new UsageRetentionService();
        $result = $service->cleanupOldSnapshots();

        set_alert('success', 'Đã dọn ' . (int) $result['deleted_snapshots'] . ' bản ghi dữ liệu sử dụng cũ.');
        redirect(admin_url('kt_saas'));
    }

    public function run_billing_cycle()
    {
        $this->requireCapability('kt_saas_manage_billing');

        require_once module_dir_path(KT_SAAS_MODULE, 'billing/RecurringBillingRunner.php');
        $runner = new RecurringBillingRunner();
        $result = $runner->run(100);

        set_alert(
            'success',
            'Đã chạy chu kỳ thanh toán. Gia hạn miễn phí: ' . (int) ($result['free_renewals'] ?? 0)
            . ', hóa đơn tạo mới: ' . (int) ($result['renewal_invoices'] ?? 0)
            . ', hóa đơn vượt mức mới: ' . (int) ($result['overage_invoices'] ?? 0)
            . ', hóa đơn vượt mức đã tồn tại: ' . (int) ($result['overage_existing'] ?? 0)
            . ', bắt đầu ân hạn: ' . (int) ($result['grace_started'] ?? 0)
            . ', hóa đơn quá hạn: ' . (int) ($result['invoices_overdue'] ?? 0)
            . ', lượt nhắc thanh toán: ' . (int) ($result['dunning_attempts'] ?? 0)
            . ', tenant bị tạm ngưng: ' . ((int) ($result['trial_to_suspended'] ?? 0) + (int) ($result['grace_to_suspended'] ?? 0)) . '.'
        );
        redirect(admin_url('kt_saas'));
    }

    private function flashResult($result, $successMessage)
    {
        if (!empty($result['success'])) {
            set_alert('success', $successMessage);
            return;
        }

        set_alert('warning', $result['message'] ?? 'Không thể lưu dữ liệu.');
    }

    private function requirePost()
    {
        if (strtolower((string) $this->input->method()) !== 'post') {
            show_404();
        }
    }

    private function normalizeBulkIds($ids)
    {
        if (!is_array($ids)) {
            return [];
        }

        $normalized = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $normalized[$id] = $id;
            }
        }

        return array_values($normalized);
    }

    private function setBulkAlert($processed, $failed, $prefix)
    {
        if ($processed > 0 && $failed === 0) {
            set_alert('success', trim($prefix . ' Thành công: ' . (int) $processed . '.'));
            return;
        }

        set_alert('warning', trim($prefix . ' Thành công: ' . (int) $processed . ', thất bại: ' . (int) $failed . '.'));
    }

    private function streamCsv($filename, array $headers, array $rows)
    {
        $filename = trim((string) $filename) !== '' ? trim((string) $filename) : 'export.csv';
        if (substr($filename, -4) !== '.csv') {
            $filename .= '.csv';
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        if (!$out) {
            exit;
        }

        fwrite($out, chr(239) . chr(187) . chr(191));
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    private function requireCapability($capability)
    {
        if (!kt_saas_staff_can($capability)) {
            access_denied(KT_SAAS_MODULE);
        }
    }

    private function requireLandlordContext()
    {
        if (!kt_saas_is_landlord_context()) {
            set_status_header(403);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Khu vực này chỉ khả dụng trên hệ thống landlord.';
            exit;
        }
    }

    private function requireTenantContext()
    {
        if (!kt_saas_is_tenant_runtime()) {
            show_404();
        }
    }

    private function requireTenantAdmin()
    {
        if (!is_admin()) {
            access_denied(KT_SAAS_MODULE);
        }
    }

    private function requireTenantWorkspaceSettingsAccess()
    {
        if (!kt_saas_can_manage_workspace_settings()) {
            access_denied(KT_SAAS_MODULE);
        }
    }

    private function requireTenantWorkspaceGovernanceViewAccess()
    {
        if (!kt_saas_can_view_workspace_governance()) {
            access_denied(KT_SAAS_MODULE);
        }
    }

    private function requireTenantWorkspaceGovernanceManageAccess()
    {
        if (!kt_saas_can_manage_workspace_governance()) {
            access_denied(KT_SAAS_MODULE);
        }
    }

    private function requireTenantWorkspaceFeature($featureKey)
    {
        if (!kt_saas_workspace_feature_allowed($featureKey, false)) {
            access_denied(KT_SAAS_MODULE);
        }
    }

    private function saveTenantWorkspaceSection($modelMethod, $successMessage, $featureKey)
    {
        $this->requirePost();
        $this->requireTenantWorkspaceFeature($featureKey);

        $tenant = kt_saas_current_tenant();
        $tenantId = (int) ($tenant['id'] ?? 0);
        if ($tenantId <= 0 || !method_exists($this->Kt_saas_model, $modelMethod)) {
            set_alert('warning', 'Không thể lưu cấu hình workspace.');
            redirect(admin_url('kt_saas/tenant_settings'));
        }

        $result = $this->Kt_saas_model->{$modelMethod}($tenantId, $this->input->post());
        $this->flashResult($result, $successMessage);
        redirect(admin_url('kt_saas/tenant_settings'));
    }

    public function tenant_marketplace()
    {
        $tenant = kt_saas_current_tenant();
        $data = $this->buildSalesflowMarketplaceViewData($tenant);
        $data['title'] = 'Marketplace';

        $this->load->view(KT_SAAS_MODULE . '/tenant/marketplace', $data);
    }

    public function marketplace()
    {
        $this->tenant_marketplace();
    }

    public function tenant_marketplace_app($slug = 'salesflow-dms')
    {
        if (trim((string) $slug) !== 'salesflow-dms') {
            show_404();
        }

        $tenant = kt_saas_current_tenant();
        $data = $this->buildSalesflowMarketplaceViewData($tenant);
        $data['title'] = 'Gói DMS - Khách Tốt CRM';

        $this->load->view(KT_SAAS_MODULE . '/tenant/marketplace_app', $data);
    }

    public function tenant_marketplace_provisioning($slug = 'salesflow-dms')
    {
        if (trim((string) $slug) !== 'salesflow-dms') {
            show_404();
        }

        $tenant = kt_saas_current_tenant();
        $data = $this->buildSalesflowMarketplaceViewData($tenant);
        $data['title'] = 'Khach Tot DMS Installation Progress';

        $this->load->view(KT_SAAS_MODULE . '/tenant/marketplace_provisioning', $data);
    }

    public function tenant_install_salesflow_dms()
    {
        $this->requirePost();

        $tenant = kt_saas_current_tenant();
        $tenantId = (int) ($tenant['id'] ?? 0);
        if ($tenantId <= 0) {
            set_alert('warning', 'Không xác định được doanh nghiệp hiện tại.');
            redirect(admin_url('kt_saas/tenant_marketplace_app/salesflow-dms'));
        }

        $packageCode = strtolower(trim((string) $this->input->post('package_code', false)));
        $billingCycle = strtolower(trim((string) $this->input->post('billing_cycle', false)));
        $billingCycle = $billingCycle !== '' ? $billingCycle : 'monthly';

        if ($packageCode !== '') {
            $purchase = $this->Kt_saas_model->create_or_refresh_tenant_dms_addon_invoice($tenantId, $packageCode, $billingCycle, [
                'source' => 'tenant_marketplace',
            ]);
            if (empty($purchase['success'])) {
                if (!empty($purchase['pending_order']['id'])) {
                    set_alert('warning', (string) ($purchase['message'] ?? 'Bạn đã có đơn DMS đang chờ xử lý.'));
                    redirect(admin_url('kt_saas/dms_order_status/' . (int) $purchase['pending_order']['id']));
                }
                if (!empty($purchase['order']['id'])) {
                    set_alert('warning', (string) ($purchase['message'] ?? 'Gói DMS hiện tại đang được xử lý.'));
                    redirect(admin_url('kt_saas/dms_order_status/' . (int) $purchase['order']['id']));
                }
                set_alert('warning', (string) ($purchase['message'] ?? 'Chưa thể bắt đầu quy trình chọn gói DMS.'));
                redirect(admin_url('kt_saas/tenant_marketplace_app/salesflow-dms'));
            }

            $invoice = is_array($purchase['invoice'] ?? null) ? $purchase['invoice'] : [];
            if (!empty($invoice['id']) && (string) ($invoice['status'] ?? '') === 'paid') {
                $this->Kt_saas_model->activate_tenant_dms_addon_from_invoice($invoice, [
                    'gateway' => 'system_zero_total',
                    'payment_reference' => 'DMS-ZERO-' . (int) $invoice['id'],
                    'paid_at' => (string) ($invoice['paid_at'] ?? date('Y-m-d H:i:s')),
                ]);
                set_alert('success', 'Gói DMS đã được kích hoạt. Bạn có thể tiếp tục thiết lập workspace.');
                redirect(admin_url('kt_saas/tenant_marketplace_provisioning/salesflow-dms'));
            }

            require_once module_dir_path(KT_SAAS_MODULE, 'services/PaymentCollectionService.php');
            $checkout = new PaymentCollectionService();
            $checkoutUrl = !empty($invoice['id']) ? $checkout->getCheckoutUrl($invoice, $tenant) : '';

            if ($checkoutUrl !== '') {
                if (!empty($purchase['order_id'])) {
                    $this->Kt_saas_model->append_dms_order_event(
                        (int) $purchase['order_id'],
                        'payment_page_opened',
                        'Doanh nghiệp đã mở trang thanh toán DMS.',
                        [],
                        'payment_page_opened|' . date('YmdH')
                    );
                }
                redirect($checkoutUrl);
            }

            set_alert('warning', 'Đã chọn gói DMS. Vui lòng hoàn tất thanh toán trước khi tiếp tục thiết lập.');
            redirect(admin_url('kt_saas/tenant_marketplace_app/salesflow-dms'));
        }

        $result = $this->Kt_saas_model->install_salesflow_dms_marketplace_app($tenantId, null, [
            'billing_cycle' => $billingCycle,
        ]);
        if (!empty($result['success'])) {
            if (!empty($result['requires_activation'])) {
                set_alert('warning', (string) ($result['message'] ?? 'Đã chọn gói DMS. Vui lòng hoàn tất thanh toán trước khi tiếp tục.'));
            } else {
                set_alert('success', 'Khách Tốt DMS đang khởi tạo workspace. Hãy tiếp tục tại màn hình thiết lập DMS.');
            }
            redirect(admin_url('kt_saas/tenant_marketplace_provisioning/salesflow-dms'));
        }

        set_alert('warning', (string) ($result['message'] ?? 'Chưa thể khởi tạo Khách Tốt DMS cho doanh nghiệp này.'));
        redirect(admin_url('kt_saas/tenant_marketplace_app/salesflow-dms'));
    }

    public function dms_order_status($orderId = null)
    {
        $tenant = kt_saas_current_tenant();
        $tenantId = (int) ($tenant['id'] ?? 0);
        $order = $orderId !== null
            ? $this->Kt_saas_model->get_dms_commercial_order((int) $orderId, $tenantId)
            : $this->Kt_saas_model->get_latest_dms_commercial_order($tenantId);
        if ($orderId !== null && empty($order)) {
            show_404();
        }

        $checkoutUrl = '';
        if (!empty($order['invoice']['id']) && !in_array((string) ($order['invoice']['status'] ?? ''), ['paid', 'cancelled'], true)) {
            $checkoutUrl = admin_url('kt_saas/dms_order_pay/' . (int) $order['id']);
        }
        $data = [
            'title' => 'Trạng thái đơn DMS',
            'tenant' => $tenant,
            'order' => $order,
            'checkout_url' => $checkoutUrl,
            'operational_source' => (string) $this->input->get('source', true) === 'operational',
            'status_label' => function ($status) {
                return $this->Kt_saas_model->dms_order_status_label($status);
            },
            'open_dms_url' => site_url('sso/dms'),
        ];
        $this->load->view(KT_SAAS_MODULE . '/tenant/dms_order_status', $data);
    }

    public function dms_order_pay($orderId)
    {
        $tenant = kt_saas_current_tenant();
        $tenantId = (int) ($tenant['id'] ?? 0);
        $order = $this->Kt_saas_model->get_dms_commercial_order((int) $orderId, $tenantId);
        if (!$order || empty($order['invoice']['id'])) {
            show_404();
        }
        if (in_array((string) ($order['invoice']['status'] ?? ''), ['paid', 'cancelled'], true)) {
            redirect(admin_url('kt_saas/dms_order_status/' . (int) $orderId));
        }
        require_once module_dir_path(KT_SAAS_MODULE, 'services/PaymentCollectionService.php');
        $payments = new PaymentCollectionService();
        $checkoutUrl = (string) $payments->getCheckoutUrl((array) $order['invoice'], $tenant);
        if ($checkoutUrl === '') {
            set_alert('warning', 'Trang thanh toán tạm thời chưa khả dụng. Vui lòng thử lại sau.');
            redirect(admin_url('kt_saas/dms_order_status/' . (int) $orderId));
        }
        $this->Kt_saas_model->append_dms_order_event((int) $orderId, 'payment_page_opened', 'Doanh nghiệp đã mở trang thanh toán DMS.', [], 'payment_page_opened|' . date('YmdH'));
        redirect($checkoutUrl);
    }

    public function tenant_cancel_dms_order($orderId)
    {
        $this->requirePost();
        $tenant = kt_saas_current_tenant();
        $tenantId = (int) ($tenant['id'] ?? 0);
        $result = $this->Kt_saas_model->cancel_dms_commercial_order((int) $orderId, $tenantId, 'Tenant cancelled pending DMS order.');
        set_alert(!empty($result['success']) ? 'success' : 'warning', (string) ($result['message'] ?? (!empty($result['success']) ? 'Đã hủy đơn DMS.' : 'Chưa thể hủy đơn DMS.')));
        redirect(admin_url('kt_saas/dms_order_status/' . (int) $orderId));
    }

    public function dms_orders()
    {
        $this->requireCapability('kt_saas_view');
        $filters = [
            'status' => (string) $this->input->get('status', true),
            'payment_status' => (string) $this->input->get('payment_status', true),
            'verification_status' => (string) $this->input->get('verification_status', true),
            'provisioning_status' => (string) $this->input->get('provisioning_status', true),
            'workspace_status' => (string) $this->input->get('workspace_status', true),
            'package_code' => (string) $this->input->get('package_code', true),
            'tenant_id' => (int) $this->input->get('tenant_id'),
        ];
        $data = [
            'title' => 'Đơn kích hoạt DMS',
            'orders' => $this->Kt_saas_model->get_dms_commercial_orders($filters),
            'filters' => $filters,
            'packages' => $this->Kt_saas_model->get_dms_addon_package_catalog(),
            'tenants' => $this->Kt_saas_model->get_tenants(),
        ];
        $this->load->view(KT_SAAS_MODULE . '/dashboard/dms_orders', $data);
    }

    public function dms_order($orderId)
    {
        $this->requireCapability('kt_saas_view');
        $order = $this->Kt_saas_model->get_dms_commercial_order((int) $orderId);
        if (!$order) {
            show_404();
        }
        $data = [
            'title' => 'Chi tiết đơn DMS',
            'order' => $order,
            'process_health' => $this->Kt_saas_model->get_dms_runtime_process_health((int) $order['tenant_id']),
        ];
        $this->load->view(KT_SAAS_MODULE . '/dashboard/dms_order_detail', $data);
    }

    public function dms_order_action($orderId)
    {
        $this->requireCapability('kt_saas_manage_billing');
        $this->requirePost();
        $order = $this->Kt_saas_model->get_dms_commercial_order((int) $orderId);
        if (!$order) {
            show_404();
        }
        $action = strtolower(trim((string) $this->input->post('action', true)));
        $result = ['success' => false, 'message' => 'Thao tác không hợp lệ.'];
        if ($action === 'recheck_payment') {
            $result = $this->Kt_saas_model->recheck_dms_commercial_order_payment((int) $orderId);
        } elseif ($action === 'verify_payment') {
            $confirmed = (int) $this->input->post('confirm_payment') === 1;
            $reference = trim((string) $this->input->post('payment_reference', true));
            if (!$confirmed || $reference === '') {
                $result = ['success' => false, 'message' => 'Cần xác nhận và nhập mã thanh toán trước khi xác minh thủ công.'];
            } else {
                require_once module_dir_path(KT_SAAS_MODULE, 'services/BillingEngineService.php');
                $billing = new BillingEngineService();
                $invoice = (array) ($order['invoice'] ?? []);
                $result = $billing->markInvoicePaid($invoice, [
                    'gateway' => 'landlord_manual_verification',
                    'payment_reference' => $reference,
                    'amount' => (float) ($invoice['grand_total'] ?? 0),
                    'currency' => (string) ($invoice['currency'] ?? 'VND'),
                ]);
            }
        } elseif ($action === 'activate_subscription') {
            $invoice = (array) ($order['invoice'] ?? []);
            $result = (string) ($invoice['status'] ?? '') === 'paid'
                ? $this->Kt_saas_model->activate_tenant_dms_addon_from_invoice($invoice, ['payment_reference' => (string) ($order['payment_reference'] ?? '')])
                : ['success' => false, 'message' => 'Không thể kích hoạt khi thanh toán chưa được xác minh.'];
        } elseif ($action === 'retry_provisioning') {
            $result = $this->Kt_saas_model->retry_dms_commercial_order_provisioning((int) $orderId);
        } elseif ($action === 'retry_staff_sync') {
            $this->load->model('kt_integration_hub/Kt_integration_model');
            $staffRows = $this->Kt_integration_model->get_dms_staff_access_rows((int) $order['tenant_id']);
            $queued = 0;
            foreach ($staffRows as $staffRow) {
                $staffId = (int) ($staffRow['crm_staff_id'] ?? $staffRow['staff_id'] ?? 0);
                if ($staffId > 0 && !empty($this->Kt_integration_model->queue_staff_sync_job((int) $order['tenant_id'], $staffId)['success'])) {
                    $queued++;
                }
            }
            $this->Kt_saas_model->append_dms_order_event((int) $orderId, 'staff_sync_started', 'Đã yêu cầu đồng bộ lại nhân sự DMS.', ['queued' => $queued], 'staff_sync_retry|' . date('YmdHi'));
            $result = ['success' => $queued > 0, 'message' => $queued > 0 ? 'Đã xếp hàng đồng bộ lại nhân sự.' : 'Không có nhân sự phù hợp để đồng bộ.'];
        } elseif ($action === 'cancel_order') {
            $result = $this->Kt_saas_model->cancel_dms_commercial_order((int) $orderId, null, (string) $this->input->post('reason', true));
        } elseif ($action === 'suspend_dms') {
            $this->load->model('kt_integration_hub/Kt_integration_model');
            $result = $this->Kt_integration_model->landlord_update_dms_addon_status((int) $order['tenant_id'], 'suspended', (string) $this->input->post('reason', true));
            if (!empty($result['success'])) {
                $this->Kt_saas_model->update_dms_commercial_order((int) $orderId, ['status' => 'blocked', 'subscription_status' => 'suspended', 'blocked_reason' => 'DMS suspended by landlord.'], 'manual_action', 'Landlord đã tạm ngưng DMS.', ['dedupe' => 'suspend|' . date('YmdHi')]);
            }
        }
        set_alert(!empty($result['success']) ? 'success' : 'warning', (string) ($result['message'] ?? (!empty($result['success']) ? 'Đã cập nhật đơn DMS.' : 'Chưa thể cập nhật đơn DMS.')));
        redirect(admin_url('kt_saas/dms_order/' . (int) $orderId));
    }

    public function tenant_request_marketplace_service()
    {
        $this->requirePost();

        $tenant = kt_saas_current_tenant();
        $tenantId = (int) ($tenant['id'] ?? 0);
        if ($tenantId <= 0) {
            set_alert('warning', 'Tenant context is missing.');
            redirect(admin_url('kt_saas/tenant_marketplace'));
        }

        $serviceSlug = strtolower(trim((string) $this->input->post('service_slug', false)));
        $serviceMap = $this->getMarketplaceManualServiceMap();
        if ($serviceSlug === '' || !isset($serviceMap[$serviceSlug])) {
            set_alert('warning', 'Requested service is not available in Marketplace.');
            redirect(admin_url('kt_saas/tenant_marketplace'));
        }

        if (!isset($this->Kt_landing_model)) {
            $this->load->model('kt_landing/Kt_landing_model');
        }

        $service = $serviceMap[$serviceSlug];
        $tenantCode = (string) ($tenant['tenant_code'] ?? '');
        $ownerName = trim((string) ($tenant['owner_name'] ?? $tenant['contact_name'] ?? 'Tenant Admin'));
        $ownerEmail = trim((string) ($tenant['owner_email'] ?? $tenant['email'] ?? ''));
        $phone = trim((string) ($tenant['phone'] ?? $tenant['owner_phone'] ?? ''));
        $company = trim((string) ($tenant['company_name'] ?? $tenant['company'] ?? $tenant['tenant_name'] ?? $tenantCode));
        $message = sprintf(
            'Tenant marketplace service request. Service: %s. Tenant ID: %d. Tenant code: %s. Source: tenant_marketplace.',
            (string) ($service['name'] ?? $serviceSlug),
            $tenantId,
            $tenantCode
        );

        $ok = $this->Kt_landing_model->save_lead([
            'name' => $ownerName,
            'company' => $company,
            'phone' => $phone,
            'email' => $ownerEmail,
            'message' => $message,
            'source' => 'tenant_marketplace_request:' . $serviceSlug,
            'status' => 'new',
        ]);

        $this->Kt_saas_model->log_activity($ok ? 'marketplace.service_request_created' : 'marketplace.service_request_failed', $ok ? 'info' : 'warning', [
            'tenant_id' => $tenantId,
            'service_slug' => $serviceSlug,
            'service_name' => (string) ($service['name'] ?? ''),
            'owner_email' => $ownerEmail,
        ], $tenantId);

        if ($ok) {
            set_alert('success', 'Service request sent for ' . (string) ($service['name'] ?? 'this service') . '. The landlord operations team can follow up from landing leads.');
        } else {
            set_alert('warning', 'Unable to submit the service request right now.');
        }

        redirect(admin_url('kt_saas/tenant_marketplace'));
    }

    private function buildSalesflowMarketplaceViewData(array $tenant)
    {
        if (!isset($this->Kt_integration_model)) {
            $this->load->model('kt_integration_hub/Kt_integration_model');
        }

        $tenantId = (int) ($tenant['id'] ?? 0);
        $tenantRow = $this->Kt_saas_model->get_tenant($tenantId) ?: $tenant;
        $subscription = $this->Kt_saas_model->get_tenant_subscription_profile($tenantId);
        $addonContext = method_exists($this->Kt_saas_model, 'resolve_tenant_dms_addon_context')
            ? $this->Kt_saas_model->resolve_tenant_dms_addon_context($tenantId)
            : [];
        $packageCatalog = method_exists($this->Kt_saas_model, 'get_dms_addon_package_catalog')
            ? $this->Kt_saas_model->get_dms_addon_package_catalog()
            : [];
        $tenantSettings = $this->Kt_integration_model->get_dms_tenant_settings($tenantId);
        $tenantSettings['operations_audit'] = $this->Kt_integration_model->get_dms_operational_settings_audit();
        $readiness = $this->Kt_integration_model->get_dms_readiness_state($tenantId, $tenantSettings);
        $state = $this->Kt_integration_model->get_dms_marketplace_state($tenantId, $tenantSettings, $readiness);
        $featureCatalog = $this->Kt_saas_model->get_integration_feature_catalog();
        $catalogEntry = $this->resolveSalesflowCatalogEntry();
        $dmsEnabled = !empty($tenantSettings['features']['DMS_ENABLED']);
        $addonStatus = strtolower(trim((string) ($tenantSettings['DMS_SUBSCRIPTION_STATUS'] ?? 'none')));
        $selectedPackageCode = strtolower(trim((string) ($addonContext['package_code'] ?? ($tenantSettings['dms_package'] ?? ''))));
        $selectedPackage = $selectedPackageCode !== '' && isset($packageCatalog[$selectedPackageCode]) ? $packageCatalog[$selectedPackageCode] : [];
        $pendingInvoice = method_exists($this->Kt_saas_model, 'get_tenant_dms_addon_invoice')
            ? $this->Kt_saas_model->get_tenant_dms_addon_invoice($tenantId, $selectedPackageCode !== '' ? $selectedPackageCode : null)
            : [];
        $selectedBillingCycle = strtolower(trim((string) ($addonContext['addon']['billing_cycle'] ?? '')));
        if ($selectedBillingCycle === '' && !empty($pendingInvoice['payload_json'])) {
            $pendingPayload = json_decode((string) $pendingInvoice['payload_json'], true);
            if (is_array($pendingPayload)) {
                $selectedBillingCycle = strtolower(trim((string) ($pendingPayload['billing_cycle'] ?? ($pendingPayload['context']['billing_cycle'] ?? ''))));
            }
        }
        if (!in_array($selectedBillingCycle, ['monthly', 'quarterly', 'yearly'], true)) {
            $selectedBillingCycle = 'monthly';
        }
        $checkoutUrl = '';
        if (!empty($pendingInvoice['id']) && !in_array((string) ($pendingInvoice['status'] ?? ''), ['paid', 'cancelled'], true)) {
            require_once module_dir_path(KT_SAAS_MODULE, 'services/PaymentCollectionService.php');
            $paymentCollection = new PaymentCollectionService();
            $checkoutUrl = (string) $paymentCollection->getCheckoutUrl($pendingInvoice, $tenantRow);
        }
        $actions = $this->buildSalesflowMarketplaceActions($tenantId, $tenantSettings, $state, [
            'checkout_url' => $checkoutUrl,
            'pending_invoice' => $pendingInvoice,
        ]);
        $workspace = $this->buildSalesflowWorkspaceSummary($tenantRow, $actions);
        $stateCode = strtoupper((string) ($state['code'] ?? 'DISCOVERED'));
        $stateDisplay = $this->buildSalesflowMarketplaceStateDisplay($stateCode);
        $firstActivityReady = !empty($readiness['first_checkin_exists']) || !empty($readiness['first_visit_exists']) || !empty($readiness['tracking_exists']);
        $staffSync = $this->Kt_integration_model->get_dms_staff_sync_status_snapshot($tenantId);
        $installHistory = $this->buildSalesflowInstallHistory($tenantId, $tenantRow, $staffSync);
        $usageSummary = $this->Kt_saas_model->get_tenant_dms_usage_summary($tenantId, [
            'addon_context' => $addonContext,
        ]);
        $latestCommercialOrder = method_exists($this->Kt_saas_model, 'get_latest_dms_commercial_order')
            ? $this->Kt_saas_model->get_latest_dms_commercial_order($tenantId)
            : [];
        $gettingStarted = $stateCode === 'ACTIVE' ? [] : [
            [
                'label' => 'Choose DMS Package',
                'done' => $addonStatus !== 'none',
                'detail' => $addonStatus !== 'none'
                    ? 'Current package selection: ' . strtoupper($selectedPackageCode !== '' ? $selectedPackageCode : 'dms_addon') . ' / ' . strtoupper($selectedBillingCycle)
                    : 'Choose a paid DMS add-on package before provisioning can start.',
            ],
            [
                'label' => 'Verify DMS Workspace Host',
                'done' => !empty($workspace['ready']),
                'detail' => !empty($workspace['ready'])
                    ? 'Workspace host: ' . (string) ($workspace['host'] ?? '-') . '. CRM will open this host after setup.'
                    : 'Workspace host has not been derived from the current tenant context yet.',
            ],
            [
                'label' => 'Provision Staff in Setup Wizard',
                'done' => (int) ($readiness['provisioned_users'] ?? 0) > 0,
                'detail' => (int) ($readiness['provisioned_users'] ?? 0) > 0
                    ? 'Local DMS user signals already exist for this tenant.'
                    : 'Run staff reprovision so Khach Tot DMS can issue local runtime access.',
            ],
            [
                'label' => 'Sync Stores and Products',
                'done' => !empty($readiness['catalog_sync_ready']),
                'detail' => !empty($readiness['catalog_sync_summary'])
                    ? (string) $readiness['catalog_sync_summary']
                    : 'Review product and customer/store sync readiness before first field rollout.',
            ],
            [
                'label' => 'Configure Check-in Rules',
                'done' => !empty($readiness['settings_ready']),
                'detail' => !empty($readiness['settings_summary'])
                    ? (string) $readiness['settings_summary']
                    : 'Review the source-backed operational settings before go-live.',
            ],
            [
                'label' => 'Open DMS',
                'done' => $dmsEnabled && (($actions['open_dms_mode'] ?? 'disabled') !== 'disabled'),
                'detail' => $dmsEnabled
                    ? (!empty($actions['sso_enabled'])
                        ? 'Use CRM -> DMS SSO when the tenant is ready.'
                        : 'Fallback login URL will be used because SSO is disabled for this tenant.')
                    : 'Open DMS remains blocked until the paid DMS add-on subscription is active.',
            ],
            [
                'label' => 'Create First Check-in',
                'done' => !empty($readiness['first_checkin_exists']),
                'detail' => !empty($readiness['first_checkin_exists'])
                    ? 'First field activity signal already exists.'
                    : 'Field activity starts when the sales team creates the first check-in from DMS.',
            ],
            [
                'label' => 'Verify Tracking',
                'done' => !empty($readiness['tracking_exists']),
                'detail' => !empty($readiness['tracking_exists'])
                    ? 'Live tracking snapshots already exist for this tenant.'
                    : 'Verify that live tracking snapshots begin to appear after the team starts moving in the field.',
            ],
        ];
        $health = [
            'app_status' => (string) ($stateDisplay['label'] ?? 'Unknown'),
            'last_sync' => (string) ($installHistory['last_sync'] ?? ''),
            'provisioning_status' => (string) ($state['provisioning']['provisioning_status'] ?? 'not_started'),
            'connection_status' => !empty($readiness['connection_ready']) ? 'Bridge connected' : 'Bridge not ready',
            'first_activity' => $firstActivityReady ? 'Activity received' : 'No activity yet',
            'sso_status' => !empty($tenantSettings['features']['SSO_ENABLED']) ? 'Runtime enabled' : 'Runtime disabled',
            'staff_sync_status' => (int) ($staffSync['successful_count'] ?? 0) > 0 ? 'Staff access ready' : 'Staff access pending',
            'last_staff_sync' => (string) ($staffSync['last_successful_sync_at'] ?? ''),
            'dms_access' => ($dmsEnabled && (($actions['open_dms_mode'] ?? 'disabled') !== 'disabled')) ? 'Open DMS available' : 'Open DMS blocked',
            'readiness_state' => (string) ($readiness['label'] ?? 'Unknown'),
            'marketplace_state' => (string) ($stateDisplay['label'] ?? 'Unknown'),
            'runtime_status' => $dmsEnabled ? 'Runtime enabled' : 'Runtime blocked',
            'addon_status' => $addonStatus,
        ];
        $stateOwnership = [
            [
                'owner' => 'CRM Plan',
                'label' => 'CRM subscription',
                'status' => !empty($subscription['status']) ? strtoupper((string) $subscription['status']) : 'UNKNOWN',
                'detail' => 'CRM subscription keeps tenant access to the CRM platform, but it no longer owns DMS commercial rights.',
            ],
            [
                'owner' => 'DMS Add-on',
                'label' => 'Commercial owner',
                'status' => strtoupper($addonStatus !== '' ? $addonStatus : 'none'),
                'detail' => $addonStatus === 'active'
                    ? 'An active DMS add-on subscription now owns commercial access for this tenant.'
                    : 'Tenant must purchase or activate a DMS add-on before provisioning and runtime access can continue.',
            ],
            [
                'owner' => 'Marketplace',
                'label' => 'Installation lifecycle',
                'status' => (string) ($stateDisplay['label'] ?? 'Unknown'),
                'detail' => 'Marketplace now handles package selection, purchase handoff, and install/provision lifecycle.',
            ],
            [
                'owner' => 'Setup Center',
                'label' => 'Operational readiness',
                'status' => (string) ($readiness['label'] ?? 'Unknown'),
                'detail' => 'Setup Center tracks staff provisioning, readiness checks, and first activity.',
            ],
            [
                'owner' => 'Licensing',
                'label' => 'Runtime feature gate',
                'status' => $dmsEnabled ? 'Runtime enabled' : 'Runtime blocked',
                'detail' => 'DMS runtime features now project from the DMS add-on subscription payload instead of CRM plan feature ownership.',
            ],
            [
                'owner' => 'Connection',
                'label' => 'Technical bridge health',
                'status' => !empty($readiness['connection_ready']) ? 'Bridge connected' : 'Bridge not ready',
                'detail' => 'Connection state remains an internal integration health concern, not a commercial install status.',
            ],
        ];

        $app = [
            'slug' => 'salesflow-dms',
            'type' => 'installable',
            'icon' => 'fa fa-map-signs',
            'name' => 'Khách Tốt DMS',
            'tagline' => 'Quản lý đội sales thị trường, check-in, lượt ghé và hoạt động tại điểm bán.',
            'short_description' => 'DMS hỗ trợ doanh nghiệp quản lý đội sales, điểm bán, check-in, đơn hàng và hoạt động thị trường.',
            'description' => 'Khách Tốt DMS vận hành hoạt động sales thị trường và đồng bộ dữ liệu cần thiết về Khách Tốt CRM.',
            'status' => $state,
            'status_display' => $stateDisplay,
            'catalog_source' => $catalogEntry,
            'package_summary' => $this->buildSalesflowPackageSummary($subscription, $addonContext),
            'packages_note' => 'Dịch vụ DMS bổ sung',
            'supported_plans' => [],
            'package_catalog' => array_values($packageCatalog),
            'selected_package_code' => $selectedPackageCode,
            'selected_package' => $selectedPackage,
            'selected_billing_cycle' => $selectedBillingCycle,
            'pending_invoice' => $pendingInvoice,
            'checkout_url' => $checkoutUrl,
            'workspace' => $workspace,
            'capabilities' => $this->buildSalesflowCapabilities($featureCatalog, $tenantSettings),
            'value_proposition' => [
                [
                    'title' => 'Check-in',
                    'body' => 'Capture who checked in, when, where, and whether the field team arrived within expected location quality signals.',
                ],
                [
                    'title' => 'Visit Management',
                    'body' => 'Review visit snapshots and follow field execution outcomes without making CRM the owner of visit operations.',
                ],
                [
                    'title' => 'Route Analytics',
                    'body' => 'Understand customer and store coverage using synced snapshots and route performance summaries that already exist.',
                ],
                [
                    'title' => 'Live Tracking',
                    'body' => 'See who is active, stale, or recently online through synced tracking snapshots without introducing a new tracking engine.',
                ],
                [
                    'title' => 'CRM Integration',
                    'body' => 'Reuse tenant licensing, setup wizard, notifications, and trusted SSO so DMS behaves like part of the CRM platform surface.',
                ],
            ],
            'requirements' => [
                [
                    'label' => 'DMS Add-on Subscription',
                    'done' => $addonStatus === 'active',
                    'detail' => $addonStatus === 'active'
                        ? 'A paid DMS add-on subscription is active for this tenant.'
                        : 'Choose a package and activate the DMS add-on before provisioning can continue.',
                ],
                [
                    'label' => 'Bridge Health',
                    'done' => !empty($readiness['connection_ready']),
                    'detail' => !empty($readiness['connection_ready'])
                        ? 'The technical CRM-DMS bridge is connected.'
                        : 'The platform bridge must be connected before DMS can be opened.',
                ],
                [
                    'label' => 'Staff Provisioning',
                    'done' => (int) ($readiness['provisioned_users'] ?? 0) > 0,
                    'detail' => (int) ($readiness['provisioned_users'] ?? 0) > 0
                        ? (int) ($readiness['provisioned_users'] ?? 0) . ' provisioning signal(s) found.'
                        : 'Run staff reprovision before first tenant login.',
                ],
                [
                    'label' => 'First Activity',
                    'done' => $firstActivityReady,
                    'detail' => $firstActivityReady
                        ? 'At least one check-in, visit snapshot, or live tracking signal already exists.'
                        : 'No field activity has been synced yet.',
                ],
                [
                    'label' => 'SSO',
                    'done' => !empty($tenantSettings['features']['SSO_ENABLED']),
                    'detail' => !empty($tenantSettings['features']['SSO_ENABLED'])
                        ? 'Tenant can open DMS through trusted CRM SSO.'
                        : 'Current DMS package does not enable SSO, so the fallback DMS login URL is used.',
                ],
                [
                    'label' => 'Runtime Licensing',
                    'done' => $dmsEnabled,
                    'detail' => 'Runtime feature gates are derived from the active DMS add-on subscription.',
                ],
            ],
            'install_journey' => [
                'CRM Subscription: Active',
                'Marketplace: Package selected',
                'DMS Add-on: Activated',
                'Setup Center: Ready',
                'Licensing: Runtime enabled',
                'Open DMS: Operational',
            ],
            'getting_started' => $gettingStarted,
            'health' => $health,
            'install_history' => $installHistory,
            'usage_summary' => $usageSummary,
            'latest_commercial_order' => $latestCommercialOrder,
            'plan_inheritance' => [
                'title' => 'DMS is a paid add-on, not a CRM plan feature.',
                'body' => $addonStatus === 'active'
                    ? 'This tenant has an active DMS add-on subscription. Marketplace, Setup Center, and licensing now project from that add-on lifecycle.'
                    : 'CRM subscription keeps tenant access to the platform, but DMS requires a separate paid add-on package before provisioning and runtime access can continue.',
            ],
            'billing' => [
                'subscription_status' => $addonStatus,
                'current_package' => (string) ($selectedPackage['name'] ?? strtoupper($selectedPackageCode)),
                'current_billing_cycle' => $selectedBillingCycle,
                'expires_at' => (string) (($addonContext['addon']['ends_at'] ?? '') ?: ''),
                'renewal_status' => $addonStatus === 'active' ? 'Manual renewal until automated renewal rules are connected.' : 'Payment required',
                'checkout_url' => $checkoutUrl,
                'pending_invoice_number' => (string) ($pendingInvoice['invoice_number'] ?? ''),
                'pending_invoice_status' => (string) ($pendingInvoice['status'] ?? ''),
                'pending_invoice_total' => isset($pendingInvoice['grand_total']) ? (float) $pendingInvoice['grand_total'] : null,
            ],
            'state_ownership' => $stateOwnership,
            'product_model' => [
                ['owner' => 'CRM Subscription', 'status' => !empty($subscription['status']) ? strtoupper((string) $subscription['status']) : 'UNKNOWN', 'detail' => 'Main CRM package and tenant access.'],
                ['owner' => 'DMS Add-on', 'status' => strtoupper($addonStatus !== '' ? $addonStatus : 'none'), 'detail' => 'Commercial owner of DMS package, quota, renewal, and suspension.'],
                ['owner' => 'Marketplace', 'status' => (string) ($stateDisplay['label'] ?? 'Unknown'), 'detail' => 'App installation lifecycle.'],
                ['owner' => 'Setup Center', 'status' => (string) ($readiness['label'] ?? 'Unknown'), 'detail' => 'Operational readiness for staff and first activity.'],
                ['owner' => 'Licensing', 'status' => $dmsEnabled ? 'Runtime enabled' : 'Runtime blocked', 'detail' => 'Runtime feature gates enforced by DMS.'],
                ['owner' => 'Connection', 'status' => !empty($readiness['connection_ready']) ? 'Bridge connected' : 'Bridge not ready', 'detail' => 'Internal technical CRM-DMS bridge health.'],
            ],
            'screen_preview' => [
                'title' => 'Preview Coming Soon',
                'body' => 'No approved product screenshot asset is stored in source yet. Marketplace uses a controlled placeholder instead of a fabricated preview.',
            ],
        ];

        $catalog = $this->buildTenantMarketplaceCatalog($tenantRow, $app, $actions, $tenantSettings);
        $catalogGroups = [
            'installable' => array_values(array_filter($catalog, static function ($item) {
                return (string) ($item['type'] ?? '') === 'installable';
            })),
            'requestable' => array_values(array_filter($catalog, static function ($item) {
                return (string) ($item['type'] ?? '') === 'requestable';
            })),
            'coming_soon' => array_values(array_filter($catalog, static function ($item) {
                return (string) ($item['type'] ?? '') === 'coming_soon';
            })),
        ];

        return [
            'tenant' => $tenantRow,
            'subscription' => $subscription,
            'tenant_settings' => $tenantSettings,
            'readiness' => $readiness,
            'marketplace_state' => $state,
            'marketplace_actions' => $actions,
            'salesflow_app' => $app,
            'marketplace_catalog' => $catalog,
            'marketplace_groups' => $catalogGroups,
        ];
    }

    private function buildSalesflowMarketplaceActions($tenantId, array $tenantSettings, array $state, array $purchaseContext = [])
    {
        $openDmsUrl = function_exists('site_url') ? site_url('sso/dms') : '/sso/dms';
        $setupWizardUrl = admin_url('kt_integration_hub/dms_setup_wizard');
        $settingsUrl = admin_url('kt_integration_hub/dms_settings');
        $overviewUrl = admin_url('kt_integration_hub/dms_overview');
        $subscriptionUrl = admin_url('kt_saas/tenant_subscription');
        $installUrl = admin_url('kt_saas/tenant_install_salesflow_dms');
        $provisioningUrl = admin_url('kt_saas/tenant_marketplace_provisioning/salesflow-dms');
        $featureFlags = (array) ($tenantSettings['features'] ?? []);
        $ssoEnabled = isset($featureFlags['SSO_ENABLED']) ? (bool) $featureFlags['SSO_ENABLED'] : true;
        $connection = $this->Kt_integration_model->get_salesflow_connection_for_tenant((int) $tenantId);
        $settings = [];
        if (is_array($connection)) {
            $decodedSettings = json_decode((string) ($connection['settings_json'] ?? ''), true);
            $settings = is_array($decodedSettings) ? $decodedSettings : [];
        }
        $tenant = function_exists('kt_saas_current_tenant') ? kt_saas_current_tenant() : [];
        $workspaceBaseUrl = is_array($tenant) ? kt_integration_hub_dms_workspace_base_url($tenant, (array) $connection) : '';
        $fallbackUrl = $workspaceBaseUrl !== ''
            ? $workspaceBaseUrl
            : trim((string) ($settings['base_url'] ?? $settings['app_url'] ?? ''));
        $fallbackLoginUrl = $fallbackUrl !== '' ? rtrim($fallbackUrl, '/') . '/login' : null;
        $openUrl = $ssoEnabled ? $openDmsUrl : $fallbackLoginUrl;
        $openMode = $ssoEnabled ? 'sso' : ($fallbackLoginUrl !== null ? 'fallback' : 'disabled');
        $detailUrl = admin_url('kt_saas/tenant_marketplace_app/salesflow-dms');
        $addonStatus = strtolower(trim((string) ($tenantSettings['DMS_SUBSCRIPTION_STATUS'] ?? 'none')));
        $checkoutUrl = trim((string) ($purchaseContext['checkout_url'] ?? ''));

        $primary = [
            'label' => 'Mở DMS',
            'url' => $openUrl,
            'style' => 'primary',
        ];
        $secondary = [
            'label' => 'Thiết lập DMS',
            'url' => $setupWizardUrl,
            'style' => 'default',
        ];

        switch (strtoupper((string) ($state['code'] ?? 'DISCOVERED'))) {
            case 'DISCOVERED':
                $primary = [
                    'label' => $addonStatus === 'payment_pending' ? 'Xem thanh toán' : 'Chọn gói DMS',
                    'url' => $addonStatus === 'payment_pending' && $checkoutUrl !== '' ? $checkoutUrl : $detailUrl,
                    'style' => 'primary',
                ];
                $secondary = [
                    'label' => 'Xem trạng thái khởi tạo',
                    'url' => $provisioningUrl,
                    'style' => 'default',
                ];
                break;
            case 'SELECTED':
            case 'PROVISIONING':
            case 'SETUP_REQUIRED':
                $primary = [
                    'label' => $addonStatus === 'payment_pending' ? 'Xem thanh toán' : 'Tiếp tục thiết lập',
                    'url' => $addonStatus === 'payment_pending' && $checkoutUrl !== '' ? $checkoutUrl : $provisioningUrl,
                    'style' => 'primary',
                ];
                $secondary = [
                    'label' => 'Thiết lập DMS',
                    'url' => $setupWizardUrl,
                    'style' => 'default',
                ];
                break;
            case 'SUSPENDED':
                $primary = [
                    'label' => 'Xem gói hiện tại',
                    'url' => $subscriptionUrl,
                    'style' => 'warning',
                ];
                $secondary = [
                    'label' => 'Xem thanh toán',
                    'url' => $subscriptionUrl,
                    'style' => 'default',
                ];
                break;
        }

        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'open_dms_url' => $openUrl,
            'open_dms_mode' => $openMode,
            'fallback_login_url' => $fallbackLoginUrl,
            'setup_wizard_url' => $setupWizardUrl,
            'overview_url' => $overviewUrl,
            'settings_url' => $settingsUrl,
            'subscription_url' => $subscriptionUrl,
            'provisioning_url' => $provisioningUrl,
            'install_url' => $installUrl,
            'detail_url' => $detailUrl,
            'sso_enabled' => $ssoEnabled,
        ];
    }

    private function buildSalesflowWorkspaceSummary(array $tenant, array $actions): array
    {
        $workspaceBaseUrl = trim((string) ($actions['fallback_login_url'] ?? ''));
        $workspaceBaseUrl = $workspaceBaseUrl !== ''
            ? preg_replace('#/login/?$#i', '', $workspaceBaseUrl)
            : '';
        $workspaceHost = '';
        if ($workspaceBaseUrl !== '') {
            $host = parse_url($workspaceBaseUrl, PHP_URL_HOST);
            $workspaceHost = is_string($host) ? trim($host) : '';
        }

        $tenantSubdomain = strtolower(trim((string) ($tenant['subdomain'] ?? '')));
        $hostReady = $workspaceHost !== '' && $tenantSubdomain !== '' && stripos($workspaceHost, $tenantSubdomain . '.') === 0;
        $localWorkspace = $workspaceHost !== '' && preg_match('/\.test$/i', $workspaceHost);

        return [
            'tenant_subdomain' => $tenantSubdomain,
            'host' => $workspaceHost,
            'workspace_url' => $workspaceBaseUrl,
            'login_url' => (string) ($actions['fallback_login_url'] ?? ''),
            'ready' => $hostReady,
            'dns_status' => $localWorkspace ? 'Platform-managed (.test local runtime)' : ($hostReady ? 'Managed by platform DNS' : 'Workspace host unavailable'),
            'ssl_status' => $localWorkspace ? 'Platform-managed (.test local runtime)' : ($hostReady ? 'Managed by platform SSL' : 'Workspace host unavailable'),
            'message' => $hostReady
                ? 'Your DMS workspace host is derived automatically from the CRM tenant subdomain. No landlord-only manual host mapping is required for tenant onboarding.'
                : 'DMS workspace host could not be derived from the current tenant context yet.',
        ];
    }

    private function buildSalesflowMarketplaceStateDisplay($stateCode)
    {
        $states = [
            'DISCOVERED' => ['label' => 'Chưa kích hoạt', 'summary' => 'Doanh nghiệp chưa có gói DMS đang hoạt động.'],
            'SELECTED' => ['label' => 'Đã chọn gói', 'summary' => 'Gói DMS đã được chọn và đang chờ hoàn tất kích hoạt.'],
            'PROVISIONING' => ['label' => 'Đang khởi tạo', 'summary' => 'Workspace DMS đang được khởi tạo.'],
            'SETUP_REQUIRED' => ['label' => 'Cần hoàn tất thiết lập', 'summary' => 'Workspace đã được tạo và cần hoàn tất các bước thiết lập.'],
            'ACTIVE' => ['label' => 'Đang hoạt động', 'summary' => 'DMS đang hoạt động cho doanh nghiệp này.'],
            'SUSPENDED' => ['label' => 'Tạm ngưng', 'summary' => 'Quyền truy cập DMS đang tạm ngưng.'],
        ];

        return $states[$stateCode] ?? ['label' => 'Chưa xác định', 'summary' => 'Chưa có dữ liệu trạng thái DMS.'];
    }

    private function buildSalesflowInstallHistory($tenantId, array $tenant, array $staffSync)
    {
        $jobs = $this->Kt_saas_model->get_tenant_provision_jobs((int) $tenantId, ['install_salesflow_dms', 'provision_tenant'], 20);
        $logs = $this->Kt_saas_model->get_tenant_activity_logs((int) $tenantId, [], 50);

        $installedAt = null;
        $lastProvisionedAt = trim((string) ($tenant['last_provisioned_at'] ?? ''));
        if ($lastProvisionedAt === '' && !empty($jobs)) {
            foreach ($jobs as $job) {
                $candidate = trim((string) ($job['finished_at'] ?? $job['updated_at'] ?? $job['created_at'] ?? ''));
                if ($candidate !== '') {
                    $lastProvisionedAt = $candidate;
                    break;
                }
            }
        }

        foreach (array_reverse($logs) as $log) {
            if ((string) ($log['event_key'] ?? '') === 'marketplace.salesflow_dms_install_requested') {
                $installedAt = (string) ($log['created_at'] ?? '');
                break;
            }
        }

        if ($installedAt === null && !empty($jobs)) {
            $installedAt = (string) ($jobs[count($jobs) - 1]['created_at'] ?? '');
        }

        return [
            'installed_at' => $installedAt,
            'last_provisioned_at' => $lastProvisionedAt !== '' ? $lastProvisionedAt : null,
            'last_sync' => !empty($staffSync['last_successful_sync_at']) ? (string) $staffSync['last_successful_sync_at'] : (string) ($tenant['updated_at'] ?? ''),
            'jobs' => $jobs,
            'recent_logs' => array_values(array_filter($logs, static function ($log) {
                $eventKey = (string) ($log['event_key'] ?? '');
                return strpos($eventKey, 'marketplace.salesflow_dms_') === 0 || strpos($eventKey, 'provision.') === 0;
            })),
        ];
    }

    private function resolveSalesflowCatalogEntry()
    {
        $catalog = $this->Kt_saas_model->get_module_catalog();
        foreach ($catalog as $entry) {
            $moduleName = strtolower(trim((string) ($entry['module_name'] ?? '')));
            $slug = strtolower(trim((string) ($entry['slug'] ?? '')));
            if (in_array($moduleName, ['kt_integration_hub', 'salesflow_dms'], true) || in_array($slug, ['kt-integration-hub', 'salesflow-dms'], true)) {
                return $entry;
            }
        }

        return [];
    }

    private function buildTenantMarketplaceCatalog(array $tenant, array $salesflowApp, array $salesflowActions, array $tenantSettings)
    {
        $catalog = $this->Kt_saas_model->get_module_catalog();
        $items = [];

        $dmsCatalogEntry = is_array($salesflowApp['catalog_source'] ?? null) ? $salesflowApp['catalog_source'] : [];
        $items[] = [
            'slug' => (string) ($salesflowApp['slug'] ?? 'salesflow-dms'),
            'module_name' => (string) ($dmsCatalogEntry['module_name'] ?? 'kt_integration_hub'),
            'name' => (string) ($salesflowApp['name'] ?? 'Khach Tot DMS'),
            'tagline' => (string) ($salesflowApp['tagline'] ?? ''),
            'short_description' => (string) ($salesflowApp['short_description'] ?? ''),
            'description' => (string) ($salesflowApp['description'] ?? ''),
            'icon' => (string) ($salesflowApp['icon'] ?? 'fa fa-cube'),
            'type' => 'installable',
            'type_label' => 'Installable App',
            'status_label' => (string) (($salesflowApp['status_display']['label'] ?? 'Not installed')),
            'status_summary' => (string) (($salesflowApp['status_display']['summary'] ?? '')),
            'detail_url' => admin_url('kt_saas/tenant_marketplace_app/' . ($salesflowApp['slug'] ?? 'salesflow-dms')),
            'primary_action' => is_array($salesflowActions['primary'] ?? null) ? $salesflowActions['primary'] : [],
            'secondary_action' => is_array($salesflowActions['secondary'] ?? null) ? $salesflowActions['secondary'] : [],
            'catalog_source' => $dmsCatalogEntry,
            'tenant_note' => 'Choose a paid DMS add-on package, activate it, then continue through Setup Center.',
            'state_owner_rows' => [
                ['label' => 'CRM subscription', 'status' => !empty($tenant['status']) ? strtoupper((string) $tenant['status']) : 'UNKNOWN'],
                ['label' => 'DMS add-on', 'status' => (string) ($salesflowApp['package_summary'] ?? 'Choose package')],
                ['label' => 'Install lifecycle', 'status' => (string) (($salesflowApp['status_display']['label'] ?? 'Not installed'))],
                ['label' => 'Runtime licensing', 'status' => !empty($tenantSettings['features']['DMS_ENABLED']) ? 'Runtime enabled' : 'Runtime blocked'],
            ],
        ];

        $matbaoEntry = $this->findCatalogEntryByModules($catalog, ['kt_matbao_invoice'], ['kt-matbao-invoice']);
        $items[] = [
            'slug' => (string) ($matbaoEntry['slug'] ?? 'kt-matbao-invoice'),
            'module_name' => (string) ($matbaoEntry['module_name'] ?? 'kt_matbao_invoice'),
            'name' => (string) ($matbaoEntry['display_name'] ?? 'KT MatBao Invoice'),
            'tagline' => 'Dich vu hoa don dien tu va chu ky so duoc van hanh theo goi bo sung.',
            'short_description' => 'Tenant mua them goi hoa don dien tu hoac HSM tu flow dich vu bo sung, khong phai install app tu dong.',
            'description' => 'Commercial add-on service for e-invoice and HSM signing. This surface reuses the existing tenant add-on purchase flow instead of pretending to be a one-click app install.',
            'icon' => 'fa fa-file-text-o',
            'type' => 'requestable',
            'type_label' => 'Service Add-on',
            'action_mode' => 'purchase_required',
            'status_label' => 'Purchase or manage separately',
            'status_summary' => 'Use the tenant add-on workspace to buy and manage e-invoice or HSM packages.',
            'detail_url' => null,
            'primary_action' => [
                'label' => 'Buy add-on',
                'url' => admin_url('kt_matbao_invoice/tenant/addons'),
                'style' => 'primary',
            ],
            'secondary_action' => [],
            'catalog_source' => $matbaoEntry,
            'tenant_note' => 'Commercial activation happens in the add-on service flow, not in app install lifecycle.',
            'state_owner_rows' => [
                ['label' => 'Plan eligibility', 'status' => 'Separate add-on commercial flow'],
                ['label' => 'Billing', 'status' => 'Purchase required'],
                ['label' => 'Install lifecycle', 'status' => 'Not installed from Marketplace'],
                ['label' => 'Runtime licensing', 'status' => 'Only active after paid add-on activation'],
            ],
        ];

        $sepayEntry = $this->findCatalogEntryByModules($catalog, ['kt_sepay'], ['kt-sepay']);
        $items[] = [
            'slug' => (string) ($sepayEntry['slug'] ?? 'kt-sepay'),
            'module_name' => (string) ($sepayEntry['module_name'] ?? 'kt_sepay'),
            'name' => (string) ($sepayEntry['display_name'] ?? 'KT SePay'),
            'tagline' => 'Khong gian doi soat thanh toan va payment request duoc quan ly trong workspace rieng.',
            'short_description' => 'KT SePay da co tenant workspace rieng. Marketplace hien chi dieu huong sang bo mat quan ly hien co.',
            'description' => 'Payment reconciliation and payment request workspace. It is not wired into the current one-click app install lifecycle, so Marketplace treats it as a separate managed workspace.',
            'icon' => 'fa fa-qrcode',
            'type' => 'requestable',
            'type_label' => 'Managed Workspace',
            'action_mode' => 'workspace',
            'status_label' => 'Managed separately',
            'status_summary' => 'Open the existing KT SePay tenant workspace when this module is available for your tenant runtime.',
            'detail_url' => null,
            'primary_action' => [
                'label' => 'Open KT SePay',
                'url' => admin_url('kt_sepay/tenant_portal'),
                'style' => 'default',
            ],
            'secondary_action' => [],
            'catalog_source' => $sepayEntry,
            'tenant_note' => 'This module uses its own tenant workspace today and is not provisioned through DMS install flow.',
            'state_owner_rows' => [
                ['label' => 'Plan eligibility', 'status' => 'Depends on workspace enablement'],
                ['label' => 'Billing', 'status' => 'Managed separately'],
                ['label' => 'Install lifecycle', 'status' => 'No Marketplace install flow'],
                ['label' => 'Runtime licensing', 'status' => 'Managed in KT SePay workspace'],
            ],
        ];

        $manualServiceNote = 'Tenant marketplace does not have a self-service install lifecycle for this item, so requests are routed to landlord operations.';
        $items[] = [
            'slug' => 'domain-service',
            'module_name' => '',
            'name' => 'Domain',
            'tagline' => 'Dich vu domain van con duoc xu ly thu cong o cap van hanh.',
            'short_description' => 'Request domain registration or transfer through a tenant-safe service request.',
            'description' => 'Manual commercial service. Marketplace captures the request and routes it to landlord operations instead of pretending to install the service automatically.',
            'icon' => 'fa fa-globe',
            'type' => 'requestable',
            'type_label' => 'Manual Service',
            'action_mode' => 'request_manual',
            'status_label' => 'Request service',
            'status_summary' => 'Send a service request to register or transfer a business domain.',
            'detail_url' => null,
            'primary_action' => [
                'label' => 'Request service',
                'url' => admin_url('kt_saas/tenant_request_marketplace_service'),
                'method' => 'post',
                'style' => 'default',
                'fields' => [
                    'service_slug' => 'domain-service',
                ],
            ],
            'secondary_action' => [],
            'catalog_source' => [],
            'tenant_note' => 'This request goes to landlord operations for manual fulfillment.',
            'state_owner_rows' => [
                ['label' => 'Plan eligibility', 'status' => 'Commercial request only'],
                ['label' => 'Billing', 'status' => 'Quoted manually'],
                ['label' => 'Install lifecycle', 'status' => 'Request required'],
                ['label' => 'Runtime licensing', 'status' => 'Not applicable until fulfilled'],
            ],
        ];
        $items[] = [
            'slug' => 'hosting-service',
            'module_name' => '',
            'name' => 'Hosting',
            'tagline' => 'Tai nguyen hosting bo sung chua co tenant request flow trong marketplace hien tai.',
            'short_description' => 'Request hosting capacity through a tenant-safe service request.',
            'description' => 'Manual commercial service. Marketplace routes the request to landlord operations instead of exposing a broken install button.',
            'icon' => 'fa fa-server',
            'type' => 'requestable',
            'type_label' => 'Manual Service',
            'action_mode' => 'request_manual',
            'status_label' => 'Request service',
            'status_summary' => 'Send a service request for hosting capacity or hosting support.',
            'detail_url' => null,
            'primary_action' => [
                'label' => 'Request service',
                'url' => admin_url('kt_saas/tenant_request_marketplace_service'),
                'method' => 'post',
                'style' => 'default',
                'fields' => [
                    'service_slug' => 'hosting-service',
                ],
            ],
            'secondary_action' => [],
            'catalog_source' => [],
            'tenant_note' => 'This request goes to landlord operations for manual fulfillment.',
            'state_owner_rows' => [
                ['label' => 'Plan eligibility', 'status' => 'Commercial request only'],
                ['label' => 'Billing', 'status' => 'Quoted manually'],
                ['label' => 'Install lifecycle', 'status' => 'Request required'],
                ['label' => 'Runtime licensing', 'status' => 'Not applicable until fulfilled'],
            ],
        ];
        $items[] = [
            'slug' => 'website-setup-service',
            'module_name' => '',
            'name' => 'Website Setup',
            'tagline' => 'Goi dich vu website hien duoc market tren landing, nhung chua co tenant install flow.',
            'short_description' => 'Request a website setup service instead of a fake one-click install.',
            'description' => 'Manual service offering. Marketplace captures the service request and routes it to operations for follow-up.',
            'icon' => 'fa fa-desktop',
            'type' => 'requestable',
            'type_label' => 'Manual Service',
            'action_mode' => 'request_manual',
            'status_label' => 'Request service',
            'status_summary' => 'Send a request to discuss website setup and fulfillment.',
            'detail_url' => null,
            'primary_action' => [
                'label' => 'Request service',
                'url' => admin_url('kt_saas/tenant_request_marketplace_service'),
                'method' => 'post',
                'style' => 'default',
                'fields' => [
                    'service_slug' => 'website-setup-service',
                ],
            ],
            'secondary_action' => [],
            'catalog_source' => [],
            'tenant_note' => 'This request goes to landlord operations for manual fulfillment.',
            'state_owner_rows' => [
                ['label' => 'Plan eligibility', 'status' => 'Commercial request only'],
                ['label' => 'Billing', 'status' => 'Quoted manually'],
                ['label' => 'Install lifecycle', 'status' => 'Request required'],
                ['label' => 'Runtime licensing', 'status' => 'Not applicable until fulfilled'],
            ],
        ];

        return $items;
    }

    private function findCatalogEntryByModules(array $catalog, array $moduleNames, array $slugs = [])
    {
        $normalizedModules = array_map('strtolower', array_map('trim', $moduleNames));
        $normalizedSlugs = array_map('strtolower', array_map('trim', $slugs));

        foreach ($catalog as $entry) {
            $moduleName = strtolower(trim((string) ($entry['module_name'] ?? '')));
            $slug = strtolower(trim((string) ($entry['slug'] ?? '')));
            if (in_array($moduleName, $normalizedModules, true) || in_array($slug, $normalizedSlugs, true)) {
                return $entry;
            }
        }

        return [];
    }

    private function getMarketplaceManualServiceMap()
    {
        return [
            'domain-service' => ['name' => 'Domain'],
            'hosting-service' => ['name' => 'Hosting'],
            'website-setup-service' => ['name' => 'Website Setup'],
        ];
    }

    private function buildSalesflowPackageSummary($subscription, array $addonContext = [])
    {
        $packageCode = strtolower(trim((string) ($addonContext['package_code'] ?? '')));
        $package = is_array($addonContext['package'] ?? null) ? $addonContext['package'] : [];
        $status = strtolower(trim((string) ($addonContext['status'] ?? 'none')));

        if ($packageCode !== '' && !empty($package)) {
            $label = (string) ($package['name'] ?? strtoupper($packageCode));
            return $status === 'active'
                ? sprintf('%s active as paid DMS add-on', $label)
                : sprintf('%s selected (%s)', $label, $status);
        }

        if (!empty($subscription['status'])) {
            return 'Choose a paid DMS add-on package to activate Khach Tot DMS';
        }

        return 'DMS add-on package has not been selected yet';
    }

    private function buildSalesflowCapabilities(array $featureCatalog, array $tenantSettings)
    {
        $features = (array) ($tenantSettings['features'] ?? []);
        $map = [
            'CHECKIN_ENABLED' => 'dms.checkin_enabled',
            'VISIT_ENABLED' => 'dms.visit_enabled',
            'LIVE_TRACKING_ENABLED' => 'dms.live_tracking_enabled',
            'ROUTE_ANALYTICS_ENABLED' => 'dms.route_analytics_enabled',
            'NOTIFICATION_BRIDGE_ENABLED' => 'dms.notification_bridge_enabled',
            'SSO_ENABLED' => 'dms.sso_enabled',
        ];
        $labels = [
            'CHECKIN_ENABLED' => 'Check-in',
            'VISIT_ENABLED' => 'Visit',
            'LIVE_TRACKING_ENABLED' => 'Live Tracking',
            'ROUTE_ANALYTICS_ENABLED' => 'Route Analytics',
            'NOTIFICATION_BRIDGE_ENABLED' => 'Notification',
            'SSO_ENABLED' => 'SSO',
        ];

        $capabilities = [];
        foreach ($map as $featureFlag => $catalogKey) {
            $capabilities[] = [
                'label' => $labels[$featureFlag],
                'enabled' => !empty($features[$featureFlag]),
                'description' => (string) ($featureCatalog[$catalogKey]['description'] ?? ''),
            ];
        }

        return $capabilities;
    }

    private function isTenantWorkspaceSettingsMethod()
    {
        return in_array($this->router->fetch_method(), ['tenant_settings', 'tenant_activity_logs', 'tenant_remove_company_logo', 'tenant_remove_favicon', 'tenant_settings_profile_save', 'tenant_settings_localization_save', 'tenant_settings_email_identity_save', 'tenant_settings_invoice_save', 'tenant_settings_finance_save', 'tenant_settings_branding_save', 'tenant_settings_notifications_save', 'tenant_settings_governance_save', 'tenant_email_settings_save', 'tenant_email_settings_reset', 'tenant_email_settings_test'], true);
    }

    private function isTenantGovernanceMethod()
    {
        return in_array($this->router->fetch_method(), ['tenant_governance', 'tenant_role', 'tenant_delete_role', 'tenant_departments', 'tenant_delete_department'], true);
    }

    private function isTenantPortalMethod()
    {
        return in_array($this->router->fetch_method(), ['marketplace', 'tenant_marketplace', 'tenant_marketplace_app', 'tenant_marketplace_provisioning', 'tenant_install_salesflow_dms', 'dms_order_status', 'dms_order_pay', 'tenant_cancel_dms_order', 'tenant_request_marketplace_service', 'tenant_subscription', 'tenant_billing', 'tenant_usage', 'tenant_settings', 'tenant_activity_logs', 'tenant_governance', 'tenant_role', 'tenant_delete_role', 'tenant_departments', 'tenant_delete_department', 'tenant_remove_company_logo', 'tenant_remove_favicon', 'tenant_request_renewal', 'tenant_request_plan_change', 'tenant_settings_profile_save', 'tenant_settings_localization_save', 'tenant_settings_email_identity_save', 'tenant_settings_finance_save', 'tenant_settings_branding_save', 'tenant_settings_notifications_save', 'tenant_settings_governance_save', 'tenant_email_settings_save', 'tenant_email_settings_reset', 'tenant_email_settings_test'], true);
    }
}
