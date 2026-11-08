<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_integration_hub extends AdminController
{
    private $dmsContractStore;

    public function __construct()
    {
        parent::__construct();
        $this->load->helper(KT_INTEGRATION_HUB_MODULE . '/kt_integration_hub');
        $this->load->model(KT_INTEGRATION_HUB_MODULE . '/Kt_integration_model');
        require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/DmsIntegrationContractStore.php');
        $this->dmsContractStore = new DmsIntegrationContractStore();
        $this->dmsContractStore->ensureSchema();
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
        $this->requirePostRequest();
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
        $this->requirePostRequest();
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
        $this->requirePostRequest();
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

    public function dms_overview()
    {
        $tenantId = $this->requireTenantContext();
        $this->requireDmsEnabled();
        $this->requireCapability('kt_integration_hub_view');

        $data['title'] = _l('kt_integration_hub_dms_overview');
        $data['page_heading'] = 'Tổng quan vận hành DMS';
        $data['section'] = 'overview';
        $data['metrics'] = $this->Kt_integration_model->get_dms_overview_metrics($tenantId);
        $data['business_dashboard'] = $this->Kt_integration_model->get_dms_business_dashboard($tenantId);
        $data['recent_checkins'] = $this->Kt_integration_model->get_dms_checkins($tenantId, [], 5, 0)['rows'];
        $data['recent_visits'] = $this->Kt_integration_model->get_dms_visits($tenantId, [], 5, 0)['rows'];
        $data['recent_reviews'] = $this->Kt_integration_model->get_dms_manual_reviews($tenantId, [], 5, 0)['rows'];
        $data['recent_notifications'] = $this->Kt_integration_model->get_dms_notifications($tenantId, [], 5, 0)['rows'];
        $data['tenant_settings'] = $this->Kt_integration_model->get_dms_tenant_settings($tenantId);
        $data['tenant_settings']['operations_audit'] = $this->Kt_integration_model->get_dms_operational_settings_audit();
        $data['readiness'] = $this->Kt_integration_model->get_dms_readiness_state($tenantId, $data['tenant_settings']);
        $data['console_status'] = $this->Kt_integration_model->get_dms_console_status($tenantId, $data['tenant_settings']);
        $data['actions'] = $this->buildDmsActions($tenantId, $data['tenant_settings']);
        $data['workspace'] = $this->buildDmsWorkspaceSummary($tenantId, $data['actions']);
        $data['open_dms_experience'] = $this->buildOpenDmsExperience($data['actions']);
        $data['onboarding'] = $this->buildOnboardingModel($tenantId, $data['tenant_settings'], $data['readiness'], $data['actions'], $data['workspace']);
        $data['empty_state'] = [
            'title' => 'Chưa có dữ liệu vận hành DMS.',
            'reason' => 'Dữ liệu sẽ cập nhật sau khi đội sales bắt đầu check-in, gửi vị trí hoặc tạo đơn hàng.',
            'next' => 'Mở app DMS để ghi nhận hoạt động đầu tiên.',
        ];
        $this->renderDmsPage('overview', $data);
    }

    public function dms_sales_team_today()
    {
        $tenantId = $this->requireTenantContext();
        $this->requireDmsEnabled();
        $this->requireCapability('kt_integration_hub_view');

        $data = [
            'title' => 'DMS Sales Team Today',
            'page_heading' => 'Doi sales hom nay',
            'section' => 'sales_team_today',
            'rows' => $this->Kt_integration_model->get_dms_sales_team_today($tenantId),
            'actions' => $this->buildDmsActions($tenantId),
            'tracking_summary' => $this->Kt_integration_model->get_dms_live_tracking_summary($tenantId),
            'field_summary' => $this->Kt_integration_model->get_dms_checkin_summary($tenantId, ['date_from' => date('Y-m-d'), 'date_to' => date('Y-m-d')]),
        ];

        $this->renderDmsPage('sales_team_today', $data);
    }

    public function dms_map_activity()
    {
        $tenantId = $this->requireTenantContext();
        $this->requireDmsEnabled();
        $this->requireCapability('kt_integration_hub_view');
        try {
            $mapActivity = $this->Kt_integration_model->get_dms_map_activity($tenantId);
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('warning', 'DMS map activity presenter unavailable: ' . $e->getMessage());
            $mapActivity = [
                'staff_locations' => [],
                'checkin_locations' => [],
                'store_locations' => [],
                'order_locations' => [],
                'markers' => [],
                'notes' => [
                    'failsafe' => 'Dữ liệu vị trí DMS đang được đồng bộ. Vui lòng thử lại sau.',
                ],
            ];
        }
        try {
            $tenantSettings = $this->Kt_integration_model->get_dms_tenant_settings($tenantId);
        } catch (Throwable $e) {
            $this->db->reset_query();
            $tenantSettings = ['features' => []];
        }

        $data = [
            'title' => 'Bản đồ hoạt động DMS',
            'page_heading' => 'Bản đồ hoạt động',
            'section' => 'map_activity',
            'map_activity' => $mapActivity,
            'actions' => $this->buildDmsActions($tenantId),
            'live_tracking_enabled' => !empty($tenantSettings['features']['LIVE_TRACKING_ENABLED']),
            'map_config' => array_merge($this->effectiveTenantMapConfig($tenantSettings), [
                'loader_url' => admin_url('kt_integration_hub/dms_google_maps_loader'),
            ]),
        ];

        $this->renderDmsPage('map_activity', $data);
    }

    public function dms_setup_wizard()
    {
        $tenantId = $this->requireTenantContext();
        $this->requireCapability('kt_integration_hub_view');

        $this->renderDmsSetupWizard($tenantId);
    }

    public function dms_setup_center()
    {
        $tenantId = $this->requireTenantContext();
        $this->requireCapability('kt_integration_hub_view');

        $this->renderDmsSetupCenter($tenantId, $this->input->get('tab', true));
    }

    public function dms_run_staff_reprovision()
    {
        $tenantId = $this->requireTenantContext();
        $this->requireDmsEnabled();
        $this->requireCapability('kt_integration_hub_connect');
        $this->requirePostRequest();

        require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/ResyncAllStaffToDmsJob.php');

        $job = new ResyncAllStaffToDmsJob();
        $summary = $job->handle($tenantId, false);
        $jobsDone = (int) ($summary['jobs_done'] ?? 0);
        $staffQueued = (int) ($summary['staff_queued'] ?? 0);

        if ($jobsDone > 0 || $staffQueued > 0) {
            set_alert('success', 'Đã xếp hàng đồng bộ ' . max($jobsDone, $staffQueued) . ' nhân sự. Vui lòng kiểm tra lại trạng thái đồng bộ.');
        } else {
            $reason = (string) ($summary['reason_message'] ?? '');
            if ($reason === '') {
                $reason = (int) ($summary['active_staff'] ?? 0) > 0
                    ? 'Gán vai trò DMS cho nhân sự và thử lại.'
                    : 'Gán nhân sự vào DMS.';
            }
            set_alert('warning', $reason);
        }

        redirect(admin_url('kt_integration_hub/dms_setup_center?tab=users'));
    }

    public function dms_save_staff_access($staffId)
    {
        $tenantId = $this->requireTenantContext();
        $this->requireDmsEnabled();
        $this->requireCapability('kt_integration_hub_connect');
        $this->requirePostRequest();

        $staffId = (int) $staffId;
        $saved = $this->Kt_integration_model->save_dms_staff_access($tenantId, $staffId, [
            'dms_access_enabled' => $this->input->post('dms_access_enabled') ? 1 : 0,
            'dms_role_code' => $this->input->post('dms_role_code', true),
            'manager_crm_staff_id' => $this->input->post('manager_crm_staff_id', true),
        ]);

        if (!$saved) {
            set_alert('danger', 'Không thể lưu quyền DMS cho nhân sự này.');
            redirect(admin_url('kt_integration_hub/dms_setup_center?tab=users'));
        }

        $queued = $this->Kt_integration_model->queue_staff_sync_job($tenantId, $staffId);
        if (empty($queued['success'])) {
            $queueMessage = trim((string) ($queued['message'] ?? 'Không thể gửi yêu cầu đồng bộ nhân sự.'));
            $this->Kt_integration_model->update_dms_staff_access_sync_result(
                $tenantId,
                $staffId,
                'failed',
                $queueMessage
            );
            set_alert('warning', 'Đã lưu quyền DMS, nhưng chưa gửi được yêu cầu đồng bộ nhân sự: ' . $queueMessage . ' Vui lòng dùng Đồng bộ nhân sự sau khi kết nối được khôi phục.');
            redirect(admin_url('kt_integration_hub/dms_setup_center?tab=users'));
        }

        set_alert('success', 'Đã lưu quyền DMS và gửi yêu cầu đồng bộ nhân sự.');
        redirect(admin_url('kt_integration_hub/dms_setup_center?tab=users'));
    }

    public function dms_save_sync_scope()
    {
        $tenantId = $this->requireTenantContext();
        $this->requireDmsEnabled();
        $this->requireCapability('kt_integration_hub_connect');
        $this->requirePostRequest();

        $scope = [
            'product_writeback_enabled' => (int) $this->input->post('product_writeback_enabled') === 1,
        ];
        foreach (['customer', 'product', 'warehouse', 'store', 'region'] as $domain) {
            $rawIds = trim((string) $this->input->post($domain . '_selected_ids', true));
            $ids = $rawIds === '' ? [] : preg_split('/[\s,;]+/', $rawIds);
            $scope[$domain] = [
                'mode' => (string) $this->input->post($domain . '_mode', true),
                'enabled' => (int) $this->input->post($domain . '_enabled') === 1,
                'selected_ids' => array_values(array_filter(array_map('intval', (array) $ids))),
            ];
        }

        $saved = $this->Kt_integration_model->save_dms_sync_scope_settings($tenantId, $scope);
        set_alert($saved ? 'success' : 'warning', $saved ? 'Đã lưu phạm vi đồng bộ DMS.' : 'Không thể lưu phạm vi đồng bộ DMS.');
        redirect(admin_url('kt_integration_hub/dms_setup_center?tab=operations'));
    }

    public function dms_run_customer_resync()
    {
        $this->runDmsDomainResync('customer');
    }

    public function dms_run_product_resync()
    {
        $this->runDmsDomainResync('product');
    }

    public function dms_run_warehouse_resync()
    {
        $this->runDmsDomainResync('warehouse');
    }

    public function dms_retry_bridge_record($type, $jobId)
    {
        $tenantId = $this->requireTenantContext();
        $this->requireDmsEnabled();
        $this->requireCapability('kt_integration_hub_retry_jobs');
        $this->requirePostRequest();

        $result = $this->Kt_integration_model->retry_dms_bridge_record($tenantId, $type, $jobId);
        set_alert(!empty($result['success']) ? 'success' : 'warning', !empty($result['success'])
            ? 'Bản ghi đồng bộ đã được xử lý lại.'
            : (string) ($result['message'] ?? 'Thử lại đồng bộ chưa thành công.'));

        redirect(admin_url('kt_integration_hub/dms_' . ($type === 'visit' ? 'visits' : 'checkins')));
    }

    public function dms_checkins()
    {
        $_GET['tab'] = 'checkins';
        $this->dms_field_activity();
    }

    public function dms_field_activity()
    {
        $tenantId = $this->requireTenantContext();
        $this->requireDmsEnabled();
        $this->requireCapability('kt_integration_hub_view');

        $activeTab = strtolower(trim((string) $this->input->get('tab', true)));
        if (!in_array($activeTab, ['today', 'checkins', 'visits', 'exceptions', 'staff', 'notifications'], true)) {
            $activeTab = 'today';
        }

        $filters = $this->collectFilters(['status', 'crm_staff_id', 'crm_customer_id', 'store_id', 'date_from', 'date_to']);
        $paging = $this->resolvePaging(25);
        $checkins = $this->Kt_integration_model->get_dms_checkins($tenantId, $filters, $paging['limit'], $paging['offset']);
        $visitFilters = $filters;
        if (isset($visitFilters['status'])) {
            $visitFilters['visit_status'] = $visitFilters['status'];
            unset($visitFilters['status']);
        }
        $visits = $this->Kt_integration_model->get_dms_visits($tenantId, $visitFilters, $paging['limit'], $paging['offset']);
        $notifications = $this->Kt_integration_model->get_dms_notifications($tenantId, [], 10, 0);
        $checkinSummary = $this->Kt_integration_model->get_dms_checkin_summary($tenantId, $filters);
        $visitSummary = $this->Kt_integration_model->get_dms_visit_summary($tenantId, $visitFilters);

        $data = [
            'title' => 'DMS Check-in / Visit',
            'page_heading' => 'Check-in / Visit',
            'section' => 'field_activity',
            'active_tab' => $activeTab,
            'filters' => $filters,
            'filter_fields' => [
                ['key' => 'crm_staff_id', 'label' => 'Nhân viên'],
                ['key' => 'crm_customer_id', 'label' => 'Khách hàng'],
                ['key' => 'store_id', 'label' => 'Điểm bán'],
                ['key' => 'date_from', 'label' => 'Từ ngày'],
                ['key' => 'date_to', 'label' => 'Đến ngày'],
            ],
            'summary_cards' => [
                ['label' => 'Sales đang hoạt động', 'value' => (int) ($checkinSummary['active_staff_count'] ?? 0), 'detail' => 'Nhân viên có hoạt động thị trường.'],
                ['label' => 'Check-in hôm nay', 'value' => (int) ($checkinSummary['total'] ?? 0), 'detail' => ((int) ($checkinSummary['valid'] ?? 0)) . ' hợp lệ / ' . ((int) ($checkinSummary['warning'] ?? 0)) . ' cần chú ý.'],
                ['label' => 'Lượt viếng thăm hoàn tất', 'value' => (int) ($visitSummary['completed'] ?? 0), 'detail' => ((int) ($visitSummary['partial'] ?? 0)) . ' đang thực hiện / ' . ((int) ($visitSummary['cancelled'] ?? 0)) . ' đã hủy.'],
                ['label' => 'Lượt viếng thăm bị lỡ', 'value' => (int) ($visitSummary['cancelled'] ?? 0), 'detail' => 'Lượt bị hủy hoặc chưa hoàn tất cần xem lại.'],
                ['label' => 'Cần chú ý', 'value' => (int) (($checkinSummary['warning'] ?? 0) + ($checkinSummary['invalid'] ?? 0)), 'detail' => 'Check-in cần quản lý theo dõi.'],
                ['label' => 'Lỗi đồng bộ', 'value' => $this->countFailedBridgeRows($checkins['rows'], $visits['rows']), 'detail' => 'Bản ghi đồng bộ thất bại có thể thử lại.'],
            ],
            'checkins' => $checkins['rows'],
            'visits' => $visits['rows'],
            'notifications' => $notifications['rows'],
            'page' => $paging['page'],
            'limit' => $paging['limit'],
            'total' => max((int) $checkins['total'], (int) $visits['total']),
            'actions' => $this->buildDmsActions($tenantId),
            'checkin_source' => $this->Kt_integration_model->get_dms_bridge_source_status($tenantId, 'checkins'),
            'visit_source' => $this->Kt_integration_model->get_dms_bridge_source_status($tenantId, 'visits'),
            'empty_state' => [
                'title' => 'Chưa có hoạt động thị trường được đồng bộ.',
                'reason' => 'Dữ liệu chỉ xuất hiện sau khi nhân viên mở DMS, chọn điểm bán và tạo check-in hoặc hoàn tất lượt viếng thăm.',
                'next' => 'Mở DMS để tạo hoạt động đầu tiên, sau đó quay lại CRM để theo dõi dữ liệu đồng bộ.',
            ],
        ];

        $this->renderDmsPage('field_activity', $data);
    }

    public function dms_visits()
    {
        $_GET['tab'] = 'visits';
        $this->dms_field_activity();
    }

    public function dms_field_feedback()
    {
        $tenantId = $this->requireTenantContext();
        $this->requireDmsEnabled();
        $this->requireCapability('kt_integration_hub_view');

        $filters = $this->collectFilters(['feedback_type', 'status', 'severity', 'date_from', 'date_to']);
        $paging = $this->resolvePaging(25);
        $feedback = $this->Kt_integration_model->get_dms_field_feedback($tenantId, $filters, $paging['limit'], $paging['offset']);

        $data = [
            'title' => 'DMS Phản ánh thị trường',
            'page_heading' => 'Phản ánh thị trường',
            'section' => 'field_feedback',
            'filters' => $filters,
            'filter_fields' => [
                ['key' => 'feedback_type', 'label' => 'Loại phản ánh'],
                ['key' => 'status', 'label' => 'Trạng thái'],
                ['key' => 'severity', 'label' => 'Mức độ'],
                ['key' => 'date_from', 'label' => 'Từ ngày'],
                ['key' => 'date_to', 'label' => 'Đến ngày'],
            ],
            'summary' => $this->Kt_integration_model->get_dms_field_feedback_summary($tenantId, $filters),
            'rows' => $feedback['rows'],
            'page' => $paging['page'],
            'limit' => $paging['limit'],
            'total' => $feedback['total'],
            'actions' => $this->buildDmsActions($tenantId),
            'source_context' => $this->Kt_integration_model->get_dms_bridge_source_status($tenantId, 'field_feedback'),
        ];

        $this->renderDmsPage('field_feedback', $data);
    }

    public function dms_route_analytics()
    {
        $tenantId = $this->requireTenantContext();
        $this->requireDmsEnabled();
        $this->requireCapability('kt_integration_hub_view');
        $this->requireTenantDmsModuleVisible($tenantId, 'route_analytics');

        $filters = $this->collectFilters(['crm_staff_id', 'crm_customer_id', 'store_id', 'date_from', 'date_to']);
        $paging = $this->resolvePaging(25);
        $pageData = $this->Kt_integration_model->get_dms_route_performance_rows($tenantId, $filters, $paging['limit'], $paging['offset']);

        $data['title'] = _l('kt_integration_hub_dms_route_analytics');
        $data['page_heading'] = 'Coverage Center';
        $data['section'] = 'route_analytics';
        $data['filters'] = $filters;
        $data['filter_fields'] = [
            ['key' => 'crm_staff_id', 'label' => _l('kt_integration_hub_staff')],
            ['key' => 'crm_customer_id', 'label' => _l('kt_integration_hub_customer')],
            ['key' => 'store_id', 'label' => _l('kt_integration_hub_store')],
            ['key' => 'date_from', 'label' => _l('kt_integration_hub_date_from')],
            ['key' => 'date_to', 'label' => _l('kt_integration_hub_date_to')],
        ];
        $data['page'] = $paging['page'];
        $data['limit'] = $paging['limit'];
        $data['total'] = $pageData['total'];
        $data['rows'] = $pageData['rows'];
        $data['summary'] = $this->Kt_integration_model->get_dms_route_performance_summary($tenantId, $filters);
        $data['summary_cards'] = $this->buildRouteAnalyticsSummaryCards($data['summary']);
        $data['actions'] = $this->buildDmsActions($tenantId);
        $data['page_note'] = empty($data['summary']['store_scope_total']) || empty($data['summary']['customer_scope_total'])
            ? 'Coverage scope unavailable for at least one dimension. CRM only shows visited entities when full scope is not available in source-backed data.'
            : 'Coverage Center combines synced route activity with the available tenant scope.';
        $data['empty_state'] = [
            'title' => 'Chưa có dữ liệu độ phủ để tổng hợp.',
            'reason' => 'Số liệu sẽ xuất hiện sau khi check-in và lượt viếng thăm được đồng bộ về CRM.',
            'next' => 'Kiểm tra lại Check-in và Lịch viếng thăm để xác nhận đã có dữ liệu.',
        ];
        $data['columns'] = [
            'snapshot_date' => _l('kt_integration_hub_snapshot_date'),
            'staff_name' => _l('kt_integration_hub_staff'),
            'customer_name' => _l('kt_integration_hub_customer'),
            'store_id' => _l('kt_integration_hub_store'),
            'total_checkins' => _l('kt_integration_hub_checkins_today'),
            'total_visits' => _l('kt_integration_hub_visits_today'),
            'completed_visits' => _l('kt_integration_hub_completed'),
            'partial_visits' => _l('kt_integration_hub_partial'),
            'cancelled_visits' => _l('kt_integration_hub_cancelled'),
        ];
        $this->renderDmsPage('list', $data);
    }

    public function dms_live_tracking()
    {
        $tenantId = $this->requireTenantContext();
        $this->requireDmsEnabled();
        $this->requireCapability('kt_integration_hub_view');
        $this->requireTenantDmsModuleVisible($tenantId, 'live_tracking');

        $filters = $this->collectFilters(['tracking_status', 'crm_staff_id', 'date_from', 'date_to']);
        $paging = $this->resolvePaging(25);
        $pageData = $this->Kt_integration_model->get_dms_live_tracking($tenantId, $filters, $paging['limit'], $paging['offset']);

        $data['title'] = _l('kt_integration_hub_dms_live_tracking');
        $data['section'] = 'live_tracking';
        $data['filters'] = $filters;
        $data['filter_fields'] = [
            ['key' => 'tracking_status', 'label' => _l('kt_integration_hub_tracking_status')],
            ['key' => 'crm_staff_id', 'label' => _l('kt_integration_hub_staff')],
            ['key' => 'date_from', 'label' => _l('kt_integration_hub_date_from')],
            ['key' => 'date_to', 'label' => _l('kt_integration_hub_date_to')],
        ];
        $data['page'] = $paging['page'];
        $data['limit'] = $paging['limit'];
        $data['total'] = $pageData['total'];
        $data['rows'] = $pageData['rows'];
        $data['summary_cards'] = $this->buildLiveTrackingSummaryCards(
            $this->Kt_integration_model->get_dms_live_tracking_summary($tenantId, $filters)
        );
        $data['source_context'] = $this->Kt_integration_model->get_dms_bridge_source_status($tenantId, 'live_tracking');
        $data['actions'] = $this->buildDmsActions($tenantId);
        $data['empty_state'] = [
            'title' => 'Chưa có vị trí nhân viên nào được đồng bộ.',
            'reason' => 'Điều này bình thường nếu live tracking chưa bật hoặc đội sales chưa phát sinh cập nhật vị trí.',
            'next' => 'Mở DMS để kiểm tra theo dõi trực tiếp hoặc xem lại cài đặt vận hành của workspace.',
        ];
        $data['columns'] = [
            'staff_name' => _l('kt_integration_hub_staff'),
            'latitude' => _l('kt_integration_hub_latitude'),
            'longitude' => _l('kt_integration_hub_longitude'),
            'last_seen_at' => _l('kt_integration_hub_last_seen'),
            'tracking_status' => _l('kt_integration_hub_tracking_status'),
        ];
        $this->renderDmsPage('list', $data);
    }

    public function dms_manual_reviews()
    {
        $tenantId = $this->requireTenantContext();
        $this->requireDmsEnabled();
        $this->requireCapability('kt_integration_hub_view');
        $this->requireTenantDmsModuleVisible($tenantId, 'manual_reviews');

        $filters = $this->collectFilters(['review_status', 'priority', 'review_type', 'assigned_staff_id', 'date_from', 'date_to']);
        $paging = $this->resolvePaging(25);
        $pageData = $this->Kt_integration_model->get_dms_manual_reviews($tenantId, $filters, $paging['limit'], $paging['offset']);

        $data['title'] = _l('kt_integration_hub_dms_manual_reviews');
        $data['section'] = 'manual_reviews';
        $data['filters'] = $filters;
        $data['filter_fields'] = [
            ['key' => 'review_status', 'label' => _l('kt_integration_hub_review_status')],
            ['key' => 'priority', 'label' => _l('kt_integration_hub_priority')],
            ['key' => 'review_type', 'label' => _l('kt_integration_hub_review_type')],
            ['key' => 'assigned_staff_id', 'label' => _l('kt_integration_hub_assigned_staff')],
            ['key' => 'date_from', 'label' => _l('kt_integration_hub_date_from')],
            ['key' => 'date_to', 'label' => _l('kt_integration_hub_date_to')],
        ];
        $data['page'] = $paging['page'];
        $data['limit'] = $paging['limit'];
        $data['total'] = $pageData['total'];
        $data['rows'] = $pageData['rows'];
        $data['summary_cards'] = $this->buildManualReviewSummaryCards(
            $this->Kt_integration_model->get_dms_manual_review_summary($tenantId, $filters)
        );
        $data['actions'] = $this->buildDmsActions($tenantId);
        $data['empty_state'] = [
            'title' => 'Hiện không có yêu cầu xem xét thủ công nào.',
            'reason' => 'Điều này bình thường khi dữ liệu đồng bộ ổn định và không có ngoại lệ nghiệp vụ.',
            'next' => 'Bạn có thể theo dõi Thông báo hoặc quay lại DMS để tiếp tục vận hành.',
        ];
        $data['columns'] = [
            'review_type' => _l('kt_integration_hub_review_type'),
            'review_status' => _l('kt_integration_hub_review_status'),
            'priority' => _l('kt_integration_hub_priority'),
            'assigned_staff_name' => _l('kt_integration_hub_assigned_staff'),
            'created_at' => _l('kt_integration_hub_created_at'),
        ];
        $this->renderDmsPage('list', $data);
    }

    public function dms_notifications()
    {
        $tenantId = $this->requireTenantContext();
        $this->requireDmsEnabled();
        $this->requireCapability('kt_integration_hub_view');
        $this->requireTenantDmsModuleVisible($tenantId, 'notifications');

        $filters = $this->collectFilters(['status', 'priority', 'event_type', 'date_from', 'date_to']);
        $paging = $this->resolvePaging(25);
        $pageData = $this->Kt_integration_model->get_dms_notifications($tenantId, $filters, $paging['limit'], $paging['offset']);

        $data['title'] = _l('kt_integration_hub_dms_notifications');
        $data['section'] = 'notifications';
        $data['filters'] = $filters;
        $data['filter_fields'] = [
            ['key' => 'status', 'label' => _l('kt_integration_hub_status')],
            ['key' => 'priority', 'label' => _l('kt_integration_hub_priority')],
            ['key' => 'event_type', 'label' => _l('kt_integration_hub_event_type')],
            ['key' => 'date_from', 'label' => _l('kt_integration_hub_date_from')],
            ['key' => 'date_to', 'label' => _l('kt_integration_hub_date_to')],
        ];
        $data['page'] = $paging['page'];
        $data['limit'] = $paging['limit'];
        $data['total'] = $pageData['total'];
        $data['rows'] = $pageData['rows'];
        $data['summary_cards'] = $this->buildNotificationSummaryCards(
            $this->Kt_integration_model->get_dms_notification_summary($tenantId, $filters)
        );
        $data['source_context'] = $this->Kt_integration_model->get_dms_bridge_source_status($tenantId, 'notifications');
        $data['actions'] = $this->buildDmsActions($tenantId);
        $data['empty_state'] = [
            'title' => 'Chưa có thông báo vận hành nào được phát sinh.',
            'reason' => 'Điều này bình thường nếu doanh nghiệp chưa có ngoại lệ, yêu cầu xem xét hoặc sự kiện đồng bộ cần thông báo.',
            'next' => 'Quay lại DMS để tiếp tục phát sinh hoạt động, hoặc mở Đồng bộ & Sức khỏe để kiểm tra lỗi.',
        ];
        $data['columns'] = [
            'title' => _l('kt_integration_hub_title'),
            'message' => _l('kt_integration_hub_message'),
            'priority' => _l('kt_integration_hub_priority'),
            'status' => _l('kt_integration_hub_status'),
            'created_at' => _l('kt_integration_hub_created_at'),
        ];
        $this->renderDmsPage('list', $data);
    }

    public function dms_orders_invoices()
    {
        $tenantId = $this->requireTenantContext();
        $this->requireDmsEnabled();
        $this->requireCapability('kt_integration_hub_view');

        $filters = $this->collectFilters(['bridge_status', 'crm_invoice_id', 'date_from', 'date_to']);
        $paging = $this->resolvePaging(25);
        $orders = $this->Kt_integration_model->get_dms_orders_invoices($tenantId, $filters, $paging['limit'], $paging['offset']);
        $activeTab = strtolower(trim((string) $this->input->get('tab', true)));
        if (!in_array($activeTab, ['orders', 'finance', 'errors'], true)) {
            $activeTab = 'orders';
        }

        $data = [
            'title' => 'DMS - Đơn hàng & Hóa đơn',
            'page_heading' => 'Đơn hàng & Hóa đơn',
            'section' => 'orders_invoices',
            'active_tab' => $activeTab,
            'filters' => $filters,
            'filter_fields' => [
                ['key' => 'bridge_status', 'label' => 'Trạng thái đồng bộ'],
                ['key' => 'crm_invoice_id', 'label' => 'Hóa đơn CRM'],
                ['key' => 'date_from', 'label' => 'Từ ngày'],
                ['key' => 'date_to', 'label' => 'Đến ngày'],
            ],
            'summary' => $this->Kt_integration_model->get_dms_orders_invoice_summary($tenantId),
            'rows' => $orders['rows'],
            'page' => $paging['page'],
            'limit' => $paging['limit'],
            'total' => $orders['total'],
            'actions' => $this->buildDmsActions($tenantId),
            'source_context' => $this->Kt_integration_model->get_dms_bridge_source_status($tenantId, 'orders'),
        ];

        $this->renderDmsPage('orders_invoices', $data);
    }

    public function dms_store_products()
    {
        $tenantId = $this->requireTenantContext();
        $this->requireDmsEnabled();
        $this->requireCapability('kt_integration_hub_view');

        $data = [
            'title' => 'DMS - Điểm bán & Sản phẩm',
            'page_heading' => 'Điểm bán & Sản phẩm',
            'section' => 'store_products',
            'operations' => $this->Kt_integration_model->get_dms_store_product_operations($tenantId),
            'actions' => $this->buildDmsActions($tenantId),
        ];

        $this->renderDmsPage('store_products', $data);
    }

    public function dms_sync_health()
    {
        $tenantId = $this->requireTenantContext();
        $this->requireDmsEnabled();
        $this->requireCapability('kt_integration_hub_view');

        $tenantSettings = $this->Kt_integration_model->get_dms_tenant_settings($tenantId);
        $consoleStatus = $this->Kt_integration_model->get_dms_console_status($tenantId, $tenantSettings);
        $actions = $this->buildDmsActions($tenantId, $tenantSettings);

        $data = [
            'title' => 'DMS - Sức khỏe đồng bộ',
            'page_heading' => 'Đồng bộ & Sức khỏe',
            'section' => 'sync_health',
            'contract_readiness' => $this->contractReadinessForTenant((int) $tenantId),
            'mapping_issues' => $this->contractIssuesForTenant((int) $tenantId),
            'tenant_settings' => $tenantSettings,
            'actions' => $actions,
            'health_rows' => [
                ['label' => 'Đồng bộ nhân sự', 'type' => 'sync', 'value' => $consoleStatus['staff_sync'] ?? [], 'action' => $actions['reprovision_staff_url'] ?? null],
                ['label' => 'Đồng bộ khách hàng / điểm bán', 'type' => 'sync', 'value' => $consoleStatus['customer_sync'] ?? [], 'action' => admin_url('kt_integration_hub/dms_run_customer_resync')],
                ['label' => 'Đồng bộ sản phẩm', 'type' => 'sync', 'value' => $consoleStatus['product_sync'] ?? [], 'action' => admin_url('kt_integration_hub/dms_run_product_resync')],
                ['label' => 'Đồng bộ check-in', 'type' => 'bridge', 'value' => $consoleStatus['checkin_bridge'] ?? [], 'action' => admin_url('kt_integration_hub/dms_field_activity?tab=checkins')],
                ['label' => 'Đồng bộ lịch viếng thăm', 'type' => 'bridge', 'value' => $consoleStatus['visit_bridge'] ?? [], 'action' => admin_url('kt_integration_hub/dms_field_activity?tab=visits')],
                ['label' => 'Theo dõi trực tiếp', 'type' => 'bridge', 'value' => $consoleStatus['live_tracking_bridge'] ?? ($consoleStatus['tracking'] ?? []), 'action' => admin_url('kt_integration_hub/dms_map_activity')],
                ['label' => 'Lịch sử di chuyển', 'type' => 'projection', 'value' => $consoleStatus['locations'] ?? [], 'action' => admin_url('kt_integration_hub/dms_map_activity')],
                ['label' => 'Vị trí gửi đơn hàng', 'type' => 'projection', 'value' => $consoleStatus['locations'] ?? [], 'action' => admin_url('kt_integration_hub/dms_map_activity')],
                ['label' => 'Phản ánh thị trường', 'type' => 'bridge', 'value' => $consoleStatus['field_feedback_bridge'] ?? [], 'action' => admin_url('kt_integration_hub/dms_field_feedback')],
                ['label' => 'Vị trí phản ánh', 'type' => 'projection', 'value' => $consoleStatus['field_feedback_bridge'] ?? [], 'action' => admin_url('kt_integration_hub/dms_field_feedback')],
                ['label' => 'Đồng bộ đơn hàng', 'type' => 'bridge', 'value' => $consoleStatus['order_bridge'] ?? [], 'action' => admin_url('kt_integration_hub/dms_orders_invoices')],
                ['label' => 'Dữ liệu vận hành CRM', 'type' => 'projection', 'value' => $consoleStatus['projections'] ?? [], 'action' => null],
                ['label' => 'Workspace / Đăng nhập một lần', 'type' => 'state', 'value' => ['last_job_status' => ($actions['open_dms_mode'] ?? 'disabled') !== 'disabled' ? 'ready' : 'blocked', 'last_job_at' => ''], 'action' => $actions['open_dms_url'] ?? null],
                ['label' => 'Quyền sử dụng DMS', 'type' => 'state', 'value' => ['last_job_status' => !empty($tenantSettings['features']['DMS_ENABLED']) ? 'enabled' : 'blocked', 'last_job_at' => (string) ($tenantSettings['fetched_at'] ?? '')], 'action' => null],
            ],
            'latest_error' => $consoleStatus['latest_error'] ?? null,
        ];

        $this->renderDmsPage('sync_health', $data);
    }

    public function dms_open()
    {
        $tenantId = $this->requireTenantContext();
        $this->requireDmsEnabled();
        $this->requireCapability('kt_integration_hub_view');

        $actions = $this->buildDmsActions($tenantId);
        if (!empty($actions['open_dms_url']) && ($actions['open_dms_mode'] ?? 'disabled') !== 'disabled') {
            $tenant = kt_integration_hub_current_tenant();
            $this->renderDmsPage('sso_transition', [
                'title' => 'Đang mở Khách Tốt DMS',
                'workspace_name' => (string) ($tenant['company_name'] ?? $tenant['tenant_code'] ?? 'DMS workspace'),
                'target_url' => (string) $actions['open_dms_url'],
                'fallback_url' => (string) ($actions['fallback_login_url'] ?? ''),
                'support_code' => 'SSO-' . strtoupper(substr(sha1((string) $tenantId . '|' . microtime(true)), 0, 8)),
            ]);
            return;
        }

        set_alert('warning', 'Workspace DMS chưa sẵn sàng. Vui lòng kiểm tra Thiết lập đội DMS.');
        redirect(admin_url('kt_integration_hub/dms_setup_center'));
    }

    public function dms_settings()
    {
        $tenantId = $this->requireTenantContext();
        $this->requireCapability('kt_integration_hub_view');

        $this->renderDmsSetupCenter($tenantId, 'capabilities');
    }

    public function dms_workspaces()
    {
        $tenantId = $this->requireTenantContext();
        $this->requireCapability('kt_integration_hub_view');
        $this->requireDmsEnabled();

        try { $workspaces=$this->Kt_integration_model->refresh_dms_workspace_registry($tenantId); } catch(Throwable $e){ $workspaces=$this->Kt_integration_model->get_dms_workspaces_for_canonical_tenant($tenantId); }
        $this->render(KT_INTEGRATION_HUB_MODULE . '/tenant/dms/workspaces', [
            'title' => 'Workspace DMS',
            'workspaces' => $workspaces,
        ]);
    }

    public function dms_workspace_add()
    {
        $tenantId = $this->requireTenantContext();
        $this->requireCapability('kt_integration_hub_view');
        $this->requireDmsEnabled();
        $settings = $this->Kt_integration_model->get_dms_tenant_settings($tenantId);
        $features = (array) ($settings['features'] ?? []);
        $companyId = (int) (kt_integration_hub_crm_company_id((array) kt_integration_hub_current_tenant()) ?? 0);

        $this->render(KT_INTEGRATION_HUB_MODULE . '/tenant/dms/workspace_add', [
            'title' => 'Mua / thêm workspace DMS',
            'workspaces' => $companyId > 0 ? $this->Kt_integration_model->get_dms_workspaces_for_tenant($tenantId, $companyId) : [],
            'multi_workspace_enabled' => !empty($features['MULTI_WORKSPACE_ENABLED']),
            'package_code' => (string) ($settings['package_code'] ?? $settings['package'] ?? ''),
            'last_request' => $this->session->flashdata('dms_workspace_request_result'),
            'availability_result' => $this->session->flashdata('dms_domain_availability_result'),
        ]);
    }

    public function dms_workspace_request()
    {
        $tenantId = $this->requireTenantContext(); $this->requireCapability('kt_integration_hub_manage'); $this->requireDmsEnabled(); $this->requirePostRequest();
        $tenant = kt_integration_hub_current_tenant();
        $companyId = (int) (kt_integration_hub_crm_company_id((array) $tenant) ?? 0);
        if ($companyId <= 0) { show_error('Canonical CRM company identity is required.', 409); return; }
        $connection = $this->Kt_integration_model->get_salesflow_connection_for_tenant($tenantId);
        $settings = $this->Kt_integration_model->get_dms_tenant_settings($tenantId);
        $features = (array) ($settings['features'] ?? []); $workspaces = $this->Kt_integration_model->get_dms_workspaces_for_tenant($tenantId, $companyId);
        $correlationId = 'wsr-' . bin2hex(random_bytes(10));
        $result = ['success' => false, 'status' => 'blocked', 'code' => 'MULTI_WORKSPACE_NOT_ENTITLED', 'correlation_id' => $correlationId,
            'message' => 'Gói hiện tại chưa cho phép thêm workspace. Yêu cầu đã được ghi nhận để phê duyệt thương mại.'];
        if (empty($workspaces) || !empty($features['MULTI_WORKSPACE_ENABLED'])) {
            require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/DmsWorkspaceMutationClient.php');
            $packageCode = trim((string) ($settings['package_code'] ?? $settings['package'] ?? ''));
            $mirrorChecksum = trim((string) ($settings['checksum'] ?? $settings['snapshot_checksum'] ?? ''));
            $payload = ['correlation_id'=>$correlationId,'source_system'=>'crm','crm_tenant_id'=>$tenantId,'crm_company_id'=>$companyId,
                'canonical_crm_company_id'=>$companyId,'compatibility_crm_company_id'=>$tenantId,'identity_trust_level'=>'canonical',
                'crm_order_id'=>null,'workspace_name'=>trim((string)$this->input->post('workspace_name', true)),
                'workspace_slug'=>strtolower(trim((string)$this->input->post('workspace_slug', true))),
                'workspace_host'=>strtolower(trim((string)$this->input->post('workspace_host', true))),
                'package_code'=>$packageCode,'entitlement_snapshot_checksum'=>$mirrorChecksum,
                'requested_by'=>(string)get_staff_user_id()];
            try { $result=(new DmsWorkspaceMutationClient())->provision((array)$tenant,(array)$connection,$payload,'workspace-'.$correlationId); $this->Kt_integration_model->save_dms_workspace_mutation_result($tenantId,$companyId,$result); }
            catch(Throwable $e){ $result=['success'=>false,'status'=>'failed','code'=>'SIGNED_REQUEST_FAILED','correlation_id'=>$correlationId,'message'=>$e->getMessage()]; }
        }
        $this->dmsContractStore->recordHealth($tenantId,$companyId,'workspace_request',$correlationId,!empty($result['success'])?'synced':'failed',isset($result['workspace_id'])?(string)$result['workspace_id']:null,$result['code']??null,$result['message']??null,$correlationId);
        $this->session->set_flashdata('dms_workspace_request_result',$result);
        redirect(admin_url('kt_integration_hub/dms_workspace_add'));
    }

    public function dms_domains()
    {
        $tenantId = $this->requireTenantContext();
        $this->requireCapability('kt_integration_hub_view');
        $this->requireDmsEnabled();

        $companyId=(int)(kt_integration_hub_crm_company_id((array)kt_integration_hub_current_tenant())??0);
        try { $workspaces=$this->Kt_integration_model->refresh_dms_workspace_registry($tenantId); } catch(Throwable $e){ $workspaces=$companyId>0?$this->Kt_integration_model->get_dms_workspaces_for_tenant($tenantId,$companyId):[]; }
        $this->render(KT_INTEGRATION_HUB_MODULE . '/tenant/dms/domains', [
            'title' => 'Domain DMS',
            'workspaces' => $workspaces,
            'last_request' => $this->session->flashdata('dms_domain_request_result'),
            'last_check' => $this->session->flashdata('dms_domain_check_result'),
        ]);
    }

    public function dms_domain_request()
    {
        $tenantId=$this->requireTenantContext(); $this->requireCapability('kt_integration_hub_manage'); $this->requireDmsEnabled(); $this->requirePostRequest();
        $tenant=(array)kt_integration_hub_current_tenant(); $companyId=(int)(kt_integration_hub_crm_company_id($tenant)??0); if($companyId<=0){show_error('Canonical CRM company identity is required.',409);return;} $connection=(array)$this->Kt_integration_model->get_salesflow_connection_for_tenant($tenantId);
        $workspaceId=(int)$this->input->post('workspace_id'); $workspace=$this->Kt_integration_model->get_dms_workspace_for_tenant($tenantId,$workspaceId);
        if(!$workspace){ set_alert('warning','Workspace không thuộc doanh nghiệp hiện tại.'); redirect(admin_url('kt_integration_hub/dms_domains')); return; }
        $correlationId='dom-'.bin2hex(random_bytes(10));
        $payload=['correlation_id'=>$correlationId,'crm_tenant_id'=>$tenantId,'crm_company_id'=>$companyId,'canonical_crm_company_id'=>$companyId,'compatibility_crm_company_id'=>$tenantId,'identity_trust_level'=>'canonical','workspace_id'=>$workspaceId,
            'domain_type'=>(string)$this->input->post('domain_type',true),'requested_domain'=>strtolower(trim((string)$this->input->post('requested_domain',true))),
            'verification_method'=>(string)$this->input->post('verification_method',true)];
        try { require_once module_dir_path(KT_INTEGRATION_HUB_MODULE,'libraries/DmsWorkspaceMutationClient.php'); $result=(new DmsWorkspaceMutationClient())->requestDomain($tenant,$connection,$payload,'domain-'.$correlationId); if(!empty($result['success'])) $this->Kt_integration_model->save_dms_workspace_mutation_result($tenantId,$companyId,$result); }
        catch(Throwable $e){ $result=['success'=>false,'status'=>'failed','code'=>'SIGNED_REQUEST_FAILED','correlation_id'=>$correlationId,'message'=>$e->getMessage()]; }
        $this->dmsContractStore->recordHealth($tenantId,$companyId,'domain_request',$correlationId,!empty($result['success'])?'synced':'failed',isset($result['domain_id'])?(string)$result['domain_id']:null,$result['code']??null,$result['message']??null,$correlationId);
        $this->session->set_flashdata('dms_domain_request_result',$result); redirect(admin_url('kt_integration_hub/dms_domains'));
    }

    public function dms_domain_availability()
    {
        $tenantId=$this->requireTenantContext(); $this->requireCapability('kt_integration_hub_manage'); $this->requireDmsEnabled(); $this->requirePostRequest();
        $tenant=(array)kt_integration_hub_current_tenant(); $companyId=(int)(kt_integration_hub_crm_company_id($tenant)??0); if($companyId<=0){show_error('Canonical CRM company identity is required.',409);return;} $connection=(array)$this->Kt_integration_model->get_salesflow_connection_for_tenant($tenantId);
        $correlationId='dav-'.bin2hex(random_bytes(10));
        $payload=['correlation_id'=>$correlationId,'crm_tenant_id'=>$tenantId,'crm_company_id'=>$companyId,'canonical_crm_company_id'=>$companyId,'compatibility_crm_company_id'=>$tenantId,'identity_trust_level'=>'canonical',
            'domain_type'=>(string)$this->input->post('domain_type',true),
            'requested_domain'=>strtolower(trim((string)$this->input->post('requested_domain',true)))];
        try { require_once module_dir_path(KT_INTEGRATION_HUB_MODULE,'libraries/DmsWorkspaceMutationClient.php'); $result=(new DmsWorkspaceMutationClient())->checkDomainAvailability($tenant,$connection,$payload,'availability-'.$correlationId); }
        catch(Throwable $e){ $result=['success'=>false,'status'=>'unavailable','code'=>'SIGNED_REQUEST_FAILED','correlation_id'=>$correlationId,'message'=>$e->getMessage()]; }
        $this->session->set_flashdata('dms_domain_availability_result',$result);
        redirect(admin_url('kt_integration_hub/dms_workspace_add'));
    }

    public function dms_domain_check()
    {
        $tenantId=$this->requireTenantContext(); $this->requireCapability('kt_integration_hub_manage'); $this->requireDmsEnabled(); $this->requirePostRequest();
        $tenant=(array)kt_integration_hub_current_tenant(); $companyId=(int)(kt_integration_hub_crm_company_id($tenant)??0); if($companyId<=0){show_error('Canonical CRM company identity is required.',409);return;} $connection=(array)$this->Kt_integration_model->get_salesflow_connection_for_tenant($tenantId);
        $workspaceId=(int)$this->input->post('workspace_id'); $workspace=$this->Kt_integration_model->get_dms_workspace_for_tenant($tenantId,$workspaceId);
        if(!$workspace){ set_alert('warning','Workspace không thuộc doanh nghiệp hiện tại.'); redirect(admin_url('kt_integration_hub/dms_domains')); return; }
        $correlationId='dck-'.bin2hex(random_bytes(10));
        $payload=['correlation_id'=>$correlationId,'crm_tenant_id'=>$tenantId,'crm_company_id'=>$companyId,'canonical_crm_company_id'=>$companyId,'compatibility_crm_company_id'=>$tenantId,'identity_trust_level'=>'canonical',
            'workspace_id'=>$workspaceId,'domain_id'=>(int)$this->input->post('domain_id')];
        try { require_once module_dir_path(KT_INTEGRATION_HUB_MODULE,'libraries/DmsWorkspaceMutationClient.php'); $result=(new DmsWorkspaceMutationClient())->checkDomain($tenant,$connection,$payload,'domain-check-'.$correlationId); if(!empty($result['workspace_id'])&&!empty($result['domain_id'])) $this->Kt_integration_model->save_dms_workspace_mutation_result($tenantId,$companyId,$result); }
        catch(Throwable $e){ $result=['success'=>false,'status'=>'failed','code'=>'SIGNED_REQUEST_FAILED','correlation_id'=>$correlationId,'message'=>$e->getMessage()]; }
        $this->dmsContractStore->recordHealth($tenantId,$companyId,'domain_check',$correlationId,!empty($result['success'])?'synced':'failed',isset($result['domain_id'])?(string)$result['domain_id']:null,$result['code']??null,$result['message']??null,$correlationId);
        $this->session->set_flashdata('dms_domain_check_result',$result); redirect(admin_url('kt_integration_hub/dms_domains'));
    }

    public function dms_control_center()
    {
        $this->requireLandlord();
        $this->requireCapability('kt_integration_hub_manage');
        if (!isset($this->Kt_saas_model)) {
            $this->load->model('kt_saas/Kt_saas_model');
        }

        $data = [
            'title' => 'Trung tâm điều khiển DMS',
            'dashboard' => $this->Kt_integration_model->get_landlord_dms_fleet_dashboard(),
            'platform_config' => $this->Kt_integration_model->get_dms_platform_config(),
            'provider_safe_mirror' => $this->Kt_integration_model->get_dms_provider_safe_mirror(),
            'platform_provider_url' => $this->dms_platform_provider_url(),
            'packages' => $this->Kt_saas_model->get_dms_addon_package_catalog(),
            'config_version' => $this->Kt_saas_model->get_dms_config_version(),
            'config_apply_summary' => [
                'applied_at' => (string) get_option('kt_dms_global_config_updated_at'),
                'tenant_count' => count(array_filter($this->Kt_saas_model->get_tenants(false), static function ($tenant) {
                    return in_array((string) ($tenant['status'] ?? ''), ['active', 'trial', 'grace'], true);
                })),
                'error_count' => 0,
                'mode' => 'versioned_read_through',
            ],
            'config_validation' => json_decode((string) get_option('kt_dms_global_validation_json'), true) ?: [],
        ];
        $this->load->view(KT_INTEGRATION_HUB_MODULE . '/admin/dms_landlord/control_center', $data);
    }

    private function dms_platform_provider_url()
    {
        $host = function_exists('kt_integration_hub_dms_base_domain')
            ? kt_integration_hub_dms_base_domain([], [])
            : 'salesrep.test';
        $scheme = parse_url(defined('APP_BASE_URL') ? APP_BASE_URL : site_url(), PHP_URL_SCHEME) ?: 'https';

        return $scheme . '://' . strtolower(trim((string) $host)) . '/platform/providers';
    }

    public function dms_control_center_save()
    {
        $this->requireLandlord();
        $this->requireCapability('kt_integration_hub_manage');
        $this->requirePostRequest();

        $result = $this->Kt_integration_model->save_dms_platform_config((array) $this->input->post());
        set_alert(!empty($result['success']) ? 'success' : 'warning', (string) ($result['message'] ?? 'Không thể lưu cấu hình DMS.'));
        redirect(admin_url('kt_integration_hub/dms_control_center'));
    }

    public function dms_control_center_validate()
    {
        $this->requireLandlord();
        $this->requireCapability('kt_integration_hub_manage');
        $this->requirePostRequest();
        $result = $this->Kt_integration_model->validate_dms_global_config();
        set_alert(!empty($result['success']) ? 'success' : 'warning', !empty($result['success'])
            ? 'Cấu hình toàn hệ thống và kết nối tenant đều hợp lệ.'
            : 'Cấu hình còn ' . (int) ($result['failed_count'] ?? 0) . ' tenant cần xử lý.');
        redirect(admin_url('kt_integration_hub/dms_control_center'));
    }

    public function dms_landlord_connection_save($tenantId)
    {
        $this->requireLandlord();
        $this->requireCapability('kt_integration_hub_manage');
        $this->requirePostRequest();
        $result = $this->Kt_integration_model->provision_salesflow_connection(
            (int) $tenantId,
            trim((string) $this->input->post('base_url', true))
        );
        set_alert(!empty($result['success']) ? 'success' : 'warning', (string) ($result['message'] ?? 'Không thể lưu kết nối Salesrep.'));
        redirect(admin_url('kt_integration_hub/dms_landlord_tenant/' . (int) $tenantId));
    }

    public function dms_landlord_connection_test($tenantId)
    {
        $this->requireLandlord();
        $this->requireCapability('kt_integration_hub_manage');
        $this->requirePostRequest();
        $result = $this->Kt_integration_model->test_salesflow_connection((int) $tenantId);
        set_alert(!empty($result['success']) ? 'success' : 'warning', (string) ($result['message'] ?? 'Không thể kiểm tra kết nối Salesrep.'));
        redirect(admin_url('kt_integration_hub/dms_landlord_tenant/' . (int) $tenantId));
    }

    public function dms_google_maps_loader()
    {
        $this->requireTenantContext();
        $this->requireCapability('kt_integration_hub_view');
        $config = $this->Kt_integration_model->get_dms_platform_config();
        $encrypted = (string) get_option('kt_dms_maps_runtime_browser_key_encrypted');
        if ($encrypted === '') {
            $encrypted = (string) get_option('kt_dms_google_maps_api_key_encrypted');
        }
        $key = $encrypted !== '' ? kt_integration_hub_decrypt_value($encrypted) : '';
        $allowed = (array) ($config['allowed_domains'] ?? []);
        $host = strtolower((string) $this->input->server('HTTP_HOST'));
        $hostAllowed = false;
        foreach ($allowed as $domain) {
            $domain = strtolower(ltrim((string) $domain, '*.'));
            if ($domain !== '' && ($host === $domain || str_ends_with($host, '.' . $domain))) {
                $hostAllowed = true;
                break;
            }
        }
        if (($config['map_provider'] ?? '') !== 'google' || $key === '' || !$hostAllowed) {
            $this->output->set_status_header(409)->set_content_type('application/javascript')
                ->set_output('window.ktDmsGoogleMapLoadError="Cấu hình Google Maps chưa hợp lệ cho domain hiện tại.";');
            return;
        }
        $this->output->set_content_type('application/javascript')
            ->set_output('(function(){var s=document.createElement("script");s.src="https://maps.googleapis.com/maps/api/js?key='
                . rawurlencode($key)
                . '&callback=ktDmsInitGoogleMap";s.async=true;s.defer=true;s.onerror=function(){window.ktDmsGoogleMapLoadError="Không tải được Google Maps.";};document.head.appendChild(s);}());');
    }

    public function dms_landlord_subscriptions()
    {
        $this->requireLandlord();
        $this->requireCapability('kt_integration_hub_manage');

        $data = [
            'title' => 'Quản lý DMS theo tenant',
            'rows' => $this->Kt_integration_model->get_landlord_dms_tenant_subscriptions(),
        ];

        $this->load->view(KT_INTEGRATION_HUB_MODULE . '/admin/dms_landlord/tenant_subscriptions', $data);
    }

    public function dms_tenants()
    {
        $this->dms_landlord_subscriptions();
    }

    public function dms_landlord_dashboard()
    {
        $this->requireLandlord();
        $this->requireCapability('kt_integration_hub_manage');

        $readinessRows = [];
        foreach ($this->Kt_integration_model->get_landlord_dms_tenant_subscriptions() as $row) {
            $readinessTenantId = (int) ($row['tenant_id'] ?? $row['id'] ?? 0);
            if ($readinessTenantId > 0) {
                $readinessRows[] = $this->contractReadinessForTenant($readinessTenantId);
            }
        }
        $data = [
            'title' => 'Tổng quan DMS',
            'dashboard' => $this->Kt_integration_model->get_landlord_dms_fleet_dashboard(),
            'crm_dms_readiness' => $readinessRows,
            'provider_safe_mirror' => $this->Kt_integration_model->get_dms_provider_safe_mirror(),
        ];

        $this->load->view(KT_INTEGRATION_HUB_MODULE . '/admin/dms_landlord/dashboard', $data);
    }

    public function dms_refresh_provider_safe_mirror()
    {
        $this->requireLandlord();
        $this->requireCapability('kt_integration_hub_manage');
        if (strtolower((string) $this->input->method()) !== 'post') show_error('Method not allowed', 405);
        $tenantId = (int) $this->input->post('tenant_id');
        $tenant = $this->db->where('id', $tenantId)->get(db_prefix() . 'kt_saas_tenants')->row_array();
        $connection = $this->Kt_integration_model->get_salesflow_connection_for_tenant($tenantId);
        try {
            require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/ProviderSafeMirrorClient.php');
            $snapshot = (new ProviderSafeMirrorClient())->fetch((array) $tenant, (array) $connection);
            $this->Kt_integration_model->save_dms_provider_safe_mirror($snapshot['providers'], $snapshot['browser_runtime']);
            set_alert('success', 'Đã làm mới trạng thái nhà cung cấp từ Salesrep DMS Platform.');
        } catch (Throwable $e) {
            log_message('error', 'Provider safe mirror refresh failed: ' . $e->getMessage());
            set_alert('warning', 'Không thể làm mới trạng thái nhà cung cấp. Vui lòng kiểm tra kết nối DMS.');
        }
        redirect(admin_url('kt_integration_hub/dms_landlord_dashboard'));
    }

    public function dms_landlord_tenant($tenantId)
    {
        $this->requireLandlord();
        $this->requireCapability('kt_integration_hub_manage');

        $detail = $this->Kt_integration_model->get_landlord_dms_tenant_detail((int) $tenantId);
        if (empty($detail)) {
            show_404();
        }

        $data = [
            'title' => 'Chi tiết DMS tenant',
            'detail' => $detail,
            'process_health' => isset($this->Kt_saas_model)
                ? $this->Kt_saas_model->get_dms_runtime_process_health((int) $tenantId)
                : [],
            'support_override_preview' => $this->Kt_integration_model->get_dms_support_override_preview((int) $tenantId),
            'crm_dms_readiness' => $this->contractReadinessForTenant((int) $tenantId),
            'mapping_issues' => $this->contractIssuesForTenant((int) $tenantId),
        ];

        $this->load->view(KT_INTEGRATION_HUB_MODULE . '/admin/dms_landlord/tenant_detail', $data);
    }

    public function dms_landlord_update_status($tenantId)
    {
        $this->requireLandlord();
        $this->requireCapability('kt_integration_hub_manage');
        $this->requirePostRequest();

        $status = (string) $this->input->post('status', true);
        $reason = (string) $this->input->post('reason', true);
        $result = $this->Kt_integration_model->landlord_update_dms_addon_status((int) $tenantId, $status, $reason);
        set_alert(!empty($result['success']) ? 'success' : 'warning', (string) ($result['message'] ?? 'DMS status update finished.'));
        redirect(admin_url('kt_integration_hub/dms_landlord_tenant/' . (int) $tenantId));
    }

    public function dms_landlord_change_package($tenantId)
    {
        $this->requireLandlord();
        $this->requireCapability('kt_integration_hub_manage');
        $this->requirePostRequest();

        $packageCode = (string) $this->input->post('package_code', true);
        $changeType = (string) $this->input->post('change_type', true);
        $confirmed = (int) $this->input->post('confirm_change') === 1;
        if (!$confirmed) {
            set_alert('warning', 'Vui lòng xác nhận thay đổi gói DMS trước khi thực hiện.');
            redirect(admin_url('kt_integration_hub/dms_landlord_tenant/' . (int) $tenantId));
        }

        $result = $this->Kt_integration_model->landlord_change_dms_package((int) $tenantId, $packageCode, $changeType);
        set_alert(!empty($result['success']) ? 'success' : 'warning', (string) ($result['message'] ?? 'Package change recorded.'));
        redirect(admin_url('kt_integration_hub/dms_landlord_tenant/' . (int) $tenantId));
    }

    public function dms_landlord_save_overrides($tenantId)
    {
        $this->requireLandlord();
        $this->requireCapability('kt_integration_hub_manage');
        $this->requirePostRequest();

        $features = (array) $this->input->post('features');
        $quota = (array) $this->input->post('quota');
        $result = $this->Kt_integration_model->save_landlord_dms_tenant_overrides((int) $tenantId, $features, $quota);
        set_alert(!empty($result['success']) ? 'success' : 'warning', (string) ($result['message'] ?? 'Overrides saved.'));
        redirect(admin_url('kt_integration_hub/dms_landlord_tenant/' . (int) $tenantId));
    }

    public function dms_landlord_support_override_preview($tenantId)
    {
        $this->requireLandlord();
        $this->requireCapability('kt_integration_hub_manage');

        $detail = $this->Kt_integration_model->get_dms_support_override_preview((int) $tenantId);
        if (empty($detail)) {
            show_404();
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(kt_integration_hub_json_encode($detail));
    }

    public function dms_landlord_support_override_run($tenantId)
    {
        $this->requireLandlord();
        $this->requireCapability('kt_integration_hub_manage');
        $this->requirePostRequest();

        $result = $this->Kt_integration_model->run_dms_support_override((int) $tenantId, [
            'action' => (string) $this->input->post('support_action', true),
            'reason' => (string) $this->input->post('support_reason', true),
            'confirm' => (int) $this->input->post('confirm_preview') === 1,
        ]);

        set_alert(!empty($result['success']) ? 'success' : 'warning', (string) ($result['message'] ?? 'Support override finished.'));
        redirect(admin_url('kt_integration_hub/dms_landlord_tenant/' . (int) $tenantId));
    }

    public function cron_process_jobs()
    {
        if (!is_cli()) {
            $this->requireCapability('kt_integration_hub_retry_jobs');
            $this->requirePostRequest();
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

    private function requireTenantContext()
    {
        $tenantId = $this->scopeTenantId();
        if ($tenantId === null) {
            access_denied(KT_INTEGRATION_HUB_MODULE);
        }

        return $tenantId;
    }

    private function requireLandlord()
    {
        if (kt_integration_hub_is_tenant_runtime()) {
            show_error('Truy cập bị từ chối. Tài khoản doanh nghiệp không được mở khu vực quản trị nền tảng.', 403, 'Không đủ quyền');
        }
    }

    private function requireCapability($capability)
    {
        if (!kt_integration_hub_staff_can($capability)) {
            access_denied(KT_INTEGRATION_HUB_MODULE);
        }
    }

    private function requirePostRequest()
    {
        if (strtolower((string) $this->input->method()) !== 'post') {
            show_error('Action này yêu cầu POST và CSRF.', 405, 'Method not allowed');
        }
    }

    private function requireDmsEnabled()
    {
        $tenantId = $this->scopeTenantId();
        $tenantSettings = $tenantId !== null ? $this->Kt_integration_model->get_dms_tenant_settings($tenantId) : [];
        $enabled = !empty($tenantSettings['features']['DMS_ENABLED']);

        if ($enabled) {
            return;
        }

        if ($tenantId !== null) {
            set_alert('warning', 'DMS chưa sẵn sàng. Vui lòng kiểm tra trạng thái thanh toán và kích hoạt.');
            redirect(admin_url('kt_saas/dms_order_status?source=operational'));
        }

        access_denied(KT_INTEGRATION_HUB_MODULE);
    }

    private function requireTenantDmsModuleVisible($tenantId, $module)
    {
        $tenantSettings = $this->Kt_integration_model->get_dms_tenant_settings((int) $tenantId);
        $visibility = $this->Kt_integration_model->get_dms_tenant_module_visibility((int) $tenantId, $tenantSettings);
        if (!empty($visibility[(string) $module])) {
            return;
        }

        show_error(
            'Module này chưa sẵn sàng trong CRM tenant console hoặc chưa được bật trong gói DMS hiện tại.',
            404,
            'DMS module unavailable'
        );
    }

    private function render($view, $data = [])
    {
        $this->load->view($view, $data);
    }

    private function renderDmsPage($view, array $data = [])
    {
        $this->load->view(KT_INTEGRATION_HUB_MODULE . '/admin/dms/' . $view, $data);
    }

    private function collectFilters(array $keys)
    {
        $filters = [];
        foreach ($keys as $key) {
            $value = $this->input->get($key, true);
            if ($value !== null && $value !== '') {
                $filters[$key] = trim((string) $value);
            }
        }

        return $filters;
    }

    private function resolvePaging($defaultLimit = 25)
    {
        $page = max(1, (int) $this->input->get('page') ?: 1);
        $limit = max(5, min(100, (int) $this->input->get('limit') ?: (int) $defaultLimit));

        return [
            'page' => $page,
            'limit' => $limit,
            'offset' => ($page - 1) * $limit,
        ];
    }

    private function buildDmsActions($tenantId, array $tenantSettings = [])
    {
        $openDmsUrl = function_exists('site_url') ? site_url('sso/dms') : '/sso/dms';
        $setupCenterUrl = admin_url('kt_integration_hub/dms_setup_center');
        $setupWizardUrl = admin_url('kt_integration_hub/dms_setup_wizard');
        $reprovisionStaffUrl = admin_url('kt_integration_hub/dms_run_staff_reprovision');
        $featureFlags = (array) ($tenantSettings['features'] ?? []);
        $ssoEnabled = isset($featureFlags['SSO_ENABLED']) ? (bool) $featureFlags['SSO_ENABLED'] : true;
        $connection = method_exists($this->Kt_integration_model, 'get_salesflow_connection_for_tenant')
            ? $this->Kt_integration_model->get_salesflow_connection_for_tenant((int) $tenantId)
            : null;
        $settings = is_array($connection) ? kt_integration_hub_json_decode((string) ($connection['settings_json'] ?? ''), []) : [];
        $tenant = function_exists('kt_integration_hub_current_tenant') ? kt_integration_hub_current_tenant() : [];
        $workspaceBaseUrl = is_array($tenant) ? kt_integration_hub_dms_workspace_base_url($tenant, (array) $connection) : '';
        $fallbackUrl = $workspaceBaseUrl !== ''
            ? $workspaceBaseUrl
            : trim((string) ($settings['base_url'] ?? $settings['app_url'] ?? ''));

        return [
            'open_dms_url' => $ssoEnabled ? $openDmsUrl : ($fallbackUrl !== '' ? $fallbackUrl : null),
            'open_dms_mode' => $ssoEnabled ? 'sso' : ($fallbackUrl !== '' ? 'fallback' : 'disabled'),
            'sso_enabled' => $ssoEnabled,
            'provisioning_url' => $setupCenterUrl . '?tab=users',
            'setup_wizard_url' => $setupWizardUrl,
            'setup_center_url' => $setupCenterUrl,
            'reprovision_staff_url' => $reprovisionStaffUrl,
            'fallback_login_url' => $fallbackUrl !== '' ? rtrim($fallbackUrl, '/') . '/login' : null,
        ];
    }

    private function renderDmsSetupCenter($tenantId, $requestedTab = 'status')
    {
        $tenantSettings = $this->Kt_integration_model->get_dms_tenant_settings($tenantId);
        $tenantSettings['operations_audit'] = $this->Kt_integration_model->get_dms_operational_settings_audit();
        $readiness = $this->Kt_integration_model->get_dms_readiness_state($tenantId, $tenantSettings);
        $actions = $this->buildDmsActions($tenantId, $tenantSettings);
        $workspace = $this->buildDmsWorkspaceSummary($tenantId, $actions);

        $data = [
            'title' => 'Thiết lập đội DMS',
            'page_heading' => 'Thiết lập đội DMS',
            'section' => 'setup_center',
            'dms_workspaces' => $this->Kt_integration_model->get_dms_workspaces_for_canonical_tenant($tenantId),
            'setup_center' => $this->buildDmsSetupCenterPresenter(
                $tenantId,
                $tenantSettings,
                $readiness,
                $actions,
                $workspace,
                $requestedTab
            ),
        ];

        $this->renderDmsPage('setup_center', $data);
    }

    private function countFailedBridgeRows(array $checkins, array $visits)
    {
        $count = 0;
        foreach (array_merge($checkins, $visits) as $row) {
            if (strtolower(trim((string) ($row['sync_status'] ?? ''))) === 'failed') {
                $count++;
            }
        }

        return $count;
    }

    private function runDmsDomainResync($domain)
    {
        $tenantId = $this->requireTenantContext();
        $this->requireDmsEnabled();
        $this->requireCapability('kt_integration_hub_connect');
        $this->requirePostRequest();

        $summary = $this->Kt_integration_model->queue_all_dms_domain_sync_jobs($tenantId, (string) $domain);
        if (!empty($summary['queued'])) {
            set_alert('success', 'Đã xếp hàng đồng bộ ' . (int) $summary['queued'] . ' bản ghi.');
        } else {
            set_alert('warning', (string) ($summary['message'] ?? 'Chưa có dữ liệu nguồn phù hợp để đồng bộ.'));
        }

        redirect(admin_url('kt_integration_hub/dms_setup_center?tab=status'));
    }

    private function renderDmsSetupWizard($tenantId)
    {
        $tenantSettings = $this->Kt_integration_model->get_dms_tenant_settings($tenantId);
        $tenantSettings['operations_audit'] = $this->Kt_integration_model->get_dms_operational_settings_audit();
        $readiness = $this->Kt_integration_model->get_dms_readiness_state($tenantId, $tenantSettings);
        $actions = $this->buildDmsActions($tenantId, $tenantSettings);
        $workspace = $this->buildDmsWorkspaceSummary($tenantId, $actions);

        $data = [
            'title' => 'Thiết lập DMS',
            'page_heading' => 'Thiết lập DMS cho đội sales thị trường',
            'section' => 'setup_center',
            'tenant_settings' => $tenantSettings,
            'readiness' => $readiness,
            'actions' => $actions,
            'workspace' => $workspace,
            'open_dms_experience' => $this->buildOpenDmsExperience($actions),
            'onboarding' => $this->buildOnboardingModel($tenantId, $tenantSettings, $readiness, $actions, $workspace),
            'map_config' => $this->effectiveTenantMapConfig($tenantSettings),
        ];

        $this->renderDmsPage('setup_wizard', $data);
    }

    private function buildDmsSetupCenterPresenter($tenantId, array $tenantSettings, array $readiness, array $actions, array $workspace, $requestedTab)
    {
        $allowedTabs = ['status', 'users', 'capabilities', 'operations', 'support'];
        $contractReadiness = $this->contractReadinessForTenant((int) $tenantId);
        $activeTab = strtolower(trim((string) $requestedTab));
        if (!in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'status';
        }

        $features = (array) ($tenantSettings['features'] ?? []);
        $consoleStatus = $this->Kt_integration_model->get_dms_console_status($tenantId, $tenantSettings);
        $staffAccessRows = $this->Kt_integration_model->get_dms_staff_access_rows($tenantId);
        $managerCandidates = array_values(array_filter($staffAccessRows, static function ($row) {
            return in_array((string) ($row['crm_role'] ?? ''), ['admin', 'tenant_admin', 'manager', 'moderator', 'regional_manager'], true)
                && !empty($row['dms_access_enabled']);
        }));
        $operations = array_map(function ($item) {
            return [
                'label' => (string) ($item['label'] ?? 'Operational setting'),
                'value' => (string) ($item['value'] ?? 'Not configured in DMS source'),
                'configured' => stripos((string) ($item['value'] ?? ''), 'Not configured') === false,
            ];
        }, array_values((array) ($tenantSettings['operations_audit'] ?? [])));
        $syncScope = method_exists($this->Kt_integration_model, 'get_dms_sync_scope_settings')
            ? $this->Kt_integration_model->get_dms_sync_scope_settings($tenantId)
            : ['product_writeback_enabled' => false];

        $dmsEnabled = !empty($readiness['dms_enabled']);
        $addonStatus = strtolower(trim((string) ($tenantSettings['DMS_SUBSCRIPTION_STATUS'] ?? 'none')));
        $provisionedUsers = (int) ($readiness['provisioned_users'] ?? 0);
        $connectionReady = !empty($readiness['connection_ready']);
        $packageCode = strtoupper(trim((string) ($tenantSettings['DMS_PACKAGE'] ?? $tenantSettings['dms_package'] ?? '')));
        $packageName = trim((string) ($tenantSettings['dms_package_name'] ?? ''));
        $billingCycle = strtoupper(trim((string) ($tenantSettings['dms_billing_cycle'] ?? '')));
        if ($packageName === '' && $packageCode !== '') {
            $packageName = ucwords(str_replace('_', ' ', strtolower($packageCode)));
        }
        $quotaRows = [
            ['label' => 'User limit', 'value' => (int) ($tenantSettings['USER_LIMIT'] ?? 0)],
            ['label' => 'Store limit', 'value' => (int) ($tenantSettings['STORE_LIMIT'] ?? 0)],
            ['label' => 'Product limit', 'value' => (int) ($tenantSettings['PRODUCT_LIMIT'] ?? 0)],
            ['label' => 'Monthly check-in limit', 'value' => (int) ($tenantSettings['CHECKIN_MONTHLY_LIMIT'] ?? 0)],
            ['label' => 'Photo limit', 'value' => (int) ($tenantSettings['PHOTO_LIMIT'] ?? 0)],
            ['label' => 'Report export limit', 'value' => (int) ($tenantSettings['REPORT_EXPORT_LIMIT'] ?? 0)],
            ['label' => 'Log retention months', 'value' => (int) ($tenantSettings['LOG_RETENTION_MONTHS'] ?? 0)],
        ];
        $roleCounts = ['tenant_admin' => 0, 'manager' => 0, 'field_sales' => 0, 'finance' => 0];
        $fieldSalesWithManager = 0;
        foreach ($staffAccessRows as $staffAccessRow) {
            $roleCode = (string) ($staffAccessRow['dms_role_code'] ?? 'field_sales');
            if ($roleCode === 'tenant_admin') {
                $roleCounts['tenant_admin']++;
            } elseif ($roleCode === 'regional_manager') {
                $roleCounts['manager']++;
            } elseif ($roleCode === 'finance') {
                $roleCounts['finance']++;
            } else {
                $roleCounts['field_sales']++;
                if (!empty($staffAccessRow['manager_crm_staff_id'])) {
                    $fieldSalesWithManager++;
                }
            }
        }

        return [
            'contract_readiness' => $contractReadiness,
            'active_tab' => $activeTab,
            'tabs' => [
                'status' => 'Status',
                'users' => 'Users & Access',
                'capabilities' => 'Capabilities',
                'operations' => 'Operations',
                'support' => 'Support',
            ],
            'eligibility' => [
                'eligible' => $dmsEnabled,
                'title' => $dmsEnabled ? 'Gói DMS đang hoạt động cho tenant này' : 'Gói DMS chưa được kích hoạt',
                'message' => $dmsEnabled
                    ? 'Thiết lập đội DMS tập trung vào nhân viên, phân quyền, dữ liệu và mức sẵn sàng vận hành.'
                    : ($addonStatus === 'payment_pending'
                        ? 'Gói DMS đã được chọn nhưng đang chờ thanh toán. Hoàn tất thanh toán trước khi tiếp tục.'
                        : 'DMS là dịch vụ mua thêm. Vui lòng chọn gói DMS trong Marketplace trước khi tiếp tục thiết lập.'),
            ],
            'source_of_truth' => [
                ['label' => '1. Gói CRM', 'status' => !empty($tenantSettings['status']) ? kt_dms_ui_status_label('subscription', (string) $tenantSettings['status']) : 'Chưa xác định', 'value' => 'Quản lý quyền sử dụng CRM của doanh nghiệp.'],
                ['label' => '2. Gói DMS', 'status' => kt_dms_ui_status_label('subscription', $addonStatus !== '' ? $addonStatus : 'none'), 'value' => 'Quản lý gói DMS, hạn mức và chu kỳ thanh toán.'],
                ['label' => '3. Chọn gói', 'status' => 'Theo dõi kích hoạt', 'value' => 'Chọn gói và theo dõi tiến độ kích hoạt.'],
                ['label' => '4. Thiết lập DMS', 'status' => 'Sẵn sàng vận hành', 'value' => 'Hoàn tất nhân sự, dữ liệu và quyền truy cập.'],
                ['label' => '5. Quyền sử dụng', 'status' => $dmsEnabled ? 'Đang bật' : 'Chưa bật', 'value' => 'Kiểm soát các tính năng DMS được phép sử dụng.'],
            ],
            'status' => [
                'state' => (string) ($readiness['label'] ?? 'Chưa xác định'),
                'summary' => (string) ($readiness['summary'] ?? 'Chưa có tóm tắt mức sẵn sàng.'),
                'connection_ready' => $connectionReady,
                'users_ready' => $provisionedUsers > 0,
                'first_activity' => !empty($readiness['first_checkin_exists'])
                    || !empty($readiness['first_visit_exists'])
                    || !empty($readiness['tracking_exists']),
                'last_sync' => (string) ($tenantSettings['fetched_at'] ?? 'Chưa có'),
                'licensing_enabled' => $dmsEnabled,
                'package_code' => $packageCode !== '' ? $packageCode : 'Chưa chọn',
                'package_name' => $packageName !== '' ? $packageName : 'Chưa có gói DMS đang hoạt động',
                'billing_cycle' => $billingCycle !== '' ? $billingCycle : 'Chưa chọn',
                'subscription_status' => strtoupper($addonStatus !== '' ? $addonStatus : 'none'),
                'subscription_expires_at' => (string) ($tenantSettings['DMS_EXPIRES_AT'] ?? 'Chưa có'),
                'quota_rows' => $quotaRows,
                'workspace_host' => (string) ($workspace['host'] ?? 'Chưa có'),
                'workspace_url' => (string) ($workspace['workspace_url'] ?? 'Chưa có'),
                'dns_status' => (string) ($workspace['dns_status'] ?? 'Chưa xác định'),
                'ssl_status' => (string) ($workspace['ssl_status'] ?? 'Chưa xác định'),
                'catalog_sync_ready' => !empty($readiness['catalog_sync_ready']),
                'catalog_sync_summary' => (string) ($readiness['catalog_sync_summary'] ?? 'Chưa có tóm tắt đồng bộ.'),
                'settings_ready' => !empty($readiness['settings_ready']),
                'settings_summary' => (string) ($readiness['settings_summary'] ?? 'Chưa có tóm tắt cấu hình.'),
                'sync_operator_guidance' => $connectionReady
                    ? 'Lưu & Đồng bộ sẽ gửi yêu cầu để hệ thống xử lý. Tenant Admin có thể bấm Đồng bộ nhân sự, Đồng bộ điểm bán hoặc Đồng bộ sản phẩm để thử lại thủ công.'
                    : 'Chưa có kết nối Salesrep DMS đang hoạt động. Kiểm tra hồ sơ workspace, SSO/kết nối Salesrep DMS rồi bấm Đồng bộ nhân sự để thử lại.',
                'inventory_guidance' => 'Liên kết điểm bán/kho là bước thủ công cho vận hành kho/cửa hàng; không được tự đánh dấu sẵn sàng bằng dữ liệu giả.',
                'outbound_collection_guidance' => 'Thu tiền từ DMS về CRM là phạm vi tương lai và không chặn đồng bộ lõi nhân sự, điểm bán, sản phẩm.',
            ],
            'users' => [
                'provisioned_count' => $provisionedUsers,
                'used_count' => (int) ($consoleStatus['usage']['metrics']['dms_user'] ?? $provisionedUsers),
                'limit' => (int) ($consoleStatus['usage']['limits']['dms_user'] ?? ($tenantSettings['USER_LIMIT'] ?? 0)),
                'active_staff_count' => count($staffAccessRows),
                'rows' => $staffAccessRows,
                'manager_candidates' => $managerCandidates,
                'role_counts' => $roleCounts,
                'field_sales_with_manager' => $fieldSalesWithManager,
                'ready' => $provisionedUsers > 0,
                'message' => $provisionedUsers > 0
                    ? $provisionedUsers . ' staff provisioning signal(s) are available.'
                    : 'No successful staff provisioning signal is available yet.',
                'reprovision_url' => $dmsEnabled ? ($actions['reprovision_staff_url'] ?? null) : null,
                'customer_resync_url' => $dmsEnabled ? admin_url('kt_integration_hub/dms_run_customer_resync') : null,
                'product_resync_url' => $dmsEnabled ? admin_url('kt_integration_hub/dms_run_product_resync') : null,
                'warehouse_resync_url' => $dmsEnabled ? admin_url('kt_integration_hub/dms_run_warehouse_resync') : null,
            ],
            'sync_scope' => [
                'rows' => $syncScope,
                'save_url' => $dmsEnabled ? admin_url('kt_integration_hub/dms_save_sync_scope') : null,
            ],
            'capabilities' => [
                ['label' => 'Check-in', 'enabled' => !empty($features['CHECKIN_ENABLED'])],
                ['label' => 'Visit snapshots', 'enabled' => !empty($features['VISIT_ENABLED'])],
                ['label' => 'Route analytics', 'enabled' => !empty($features['ROUTE_ANALYTICS_ENABLED'])],
                ['label' => 'Live tracking', 'enabled' => !empty($features['LIVE_TRACKING_ENABLED'])],
                ['label' => 'Notification bridge', 'enabled' => !empty($features['NOTIFICATION_BRIDGE_ENABLED'])],
                ['label' => 'Single sign-on', 'enabled' => !empty($features['SSO_ENABLED'])],
            ],
            'operations' => $operations,
            'sync_status' => [
                ['label' => 'Nhân sự', 'value' => $consoleStatus['staff_sync']],
                ['label' => 'Khách hàng / Điểm bán', 'value' => $consoleStatus['customer_sync']],
                ['label' => 'Sản phẩm', 'value' => $consoleStatus['product_sync']],
                ['label' => 'Check-in', 'value' => $consoleStatus['checkin_bridge']],
                ['label' => 'Visit', 'value' => $consoleStatus['visit_bridge']],
            ],
            'usage' => $consoleStatus['usage'],
            'latest_error' => $consoleStatus['latest_error'],
            'actions' => [
                'open_dms_url' => $dmsEnabled ? ($actions['open_dms_url'] ?? null) : null,
                'open_dms_mode' => $dmsEnabled ? ($actions['open_dms_mode'] ?? 'disabled') : 'disabled',
                'overview_url' => $dmsEnabled ? admin_url('kt_integration_hub/dms_overview') : null,
            ],
            'support' => [
                'message' => $dmsEnabled
                    ? 'For access or provisioning issues, contact the tenant administrator. Bridge diagnostics and secrets are managed by the Khach Tot platform team.'
                    : 'Activate the DMS add-on in Marketplace first. Bridge diagnostics and secrets remain internal to the Khach Tot platform team.',
                'internal_url' => $this->canAccessDmsInternalDiagnostics()
                    ? admin_url('kt_integration_hub/connections')
                    : null,
            ],
        ];
    }

    private function canAccessDmsInternalDiagnostics()
    {
        return !kt_integration_hub_is_tenant_runtime() && function_exists('is_admin') && is_admin();
    }

    private function buildOnboardingModel($tenantId, array $tenantSettings, array $readiness, array $actions, array $workspace = [])
    {
        $tenantId = (int) $tenantId;
        $operationsAudit = array_values((array) ($tenantSettings['operations_audit'] ?? []));
        $hasOperationalSettings = !empty($readiness['settings_ready']);
        $localUserReady = (int) ($readiness['provisioned_users'] ?? 0) > 0;
        $entitlementActive = !empty($readiness['dms_enabled']);
        $connectionReady = !empty($readiness['connection_ready']);
        $addonStatus = strtolower(trim((string) ($tenantSettings['DMS_SUBSCRIPTION_STATUS'] ?? 'none')));
        $workspaceReady = !empty($workspace['ready']);
        $domainRoutingReady = $workspaceReady && !empty($workspace['dns_status']) && !empty($workspace['ssl_status']);
        $catalogSyncReady = !empty($readiness['catalog_sync_ready']);
        $openDmsReady = $entitlementActive && $localUserReady && (($actions['open_dms_mode'] ?? 'disabled') !== 'disabled');

        $steps = [
            [
                'index' => 1,
                'title' => 'Choose package and confirm subscription',
                'done' => $entitlementActive && $addonStatus === 'active',
                'summary' => $entitlementActive && $addonStatus === 'active'
                    ? 'A paid DMS add-on subscription is active. Setup can move on to workspace and provisioning.'
                    : 'Choose a DMS package, complete checkout, and wait for the DMS add-on to become active before operational setup.',
                'checks' => [
                    ['label' => 'DMS add-on subscription is active', 'done' => $addonStatus === 'active'],
                    ['label' => 'Runtime licensing is enabled', 'done' => $entitlementActive],
                    ['label' => 'Marketplace purchase state is no longer pending payment', 'done' => $addonStatus !== 'payment_pending'],
                ],
                'cta' => $addonStatus === 'active' ? null : [
                    'label' => 'Return to Marketplace',
                    'url' => admin_url('kt_saas/tenant_marketplace_app/salesflow-dms'),
                    'style' => 'primary',
                ],
            ],
            [
                'index' => 2,
                'title' => 'Choose DMS workspace subdomain',
                'done' => $workspaceReady,
                'summary' => $workspaceReady
                    ? 'The DMS workspace host is derived from the CRM tenant subdomain and is ready for this tenant.'
                    : 'DMS workspace host has not been derived yet from the tenant context.',
                'checks' => [
                    ['label' => 'Tenant subdomain is available', 'done' => !empty($workspace['tenant_subdomain'])],
                    ['label' => 'DMS workspace host is derived', 'done' => $workspaceReady, 'detail' => (string) ($workspace['host'] ?? '')],
                    ['label' => 'Open DMS will use the tenant-specific host', 'done' => !empty($workspace['workspace_url'])],
                ],
            ],
            [
                'index' => 3,
                'title' => 'Check DNS and domain status',
                'done' => $domainRoutingReady,
                'summary' => $domainRoutingReady
                    ? 'Workspace routing is available and platform-managed for this tenant environment.'
                    : 'Workspace routing or platform-managed domain status is still unavailable.',
                'checks' => [
                    ['label' => 'Workspace DNS status', 'done' => !empty($workspace['dns_status']), 'detail' => (string) ($workspace['dns_status'] ?? '')],
                    ['label' => 'Workspace SSL status', 'done' => !empty($workspace['ssl_status']), 'detail' => (string) ($workspace['ssl_status'] ?? '')],
                    ['label' => 'Technical bridge connected', 'done' => $connectionReady],
                ],
            ],
            [
                'index' => 4,
                'title' => 'Chuẩn bị nhân sự và người dùng',
                'done' => $localUserReady,
                'summary' => $localUserReady
                    ? (int) ($readiness['provisioned_users'] ?? 0) . ' nhân sự DMS đã có tín hiệu đồng bộ thành công.'
                    : 'Chưa có tín hiệu người dùng DMS. Vui lòng chạy lại đồng bộ nhân sự để khôi phục danh tính trong DMS.',
                'checks' => [
                    ['label' => 'Đồng bộ nhân sự đã hoàn tất', 'done' => (int) ($readiness['provisioned_users'] ?? 0) > 0],
                    ['label' => 'Đã có tín hiệu người dùng DMS', 'done' => $localUserReady],
                ],
                'cta' => !$localUserReady ? [
                    'label' => 'Đồng bộ nhân sự',
                    'url' => $actions['reprovision_staff_url'] ?? null,
                    'style' => 'default',
                ] : null,
            ],
            [
                'index' => 5,
                'title' => 'Đồng bộ điểm bán và sản phẩm',
                'done' => $catalogSyncReady,
                'summary' => $catalogSyncReady
                    ? (string) ($readiness['catalog_sync_summary'] ?? 'Đã có tín hiệu đồng bộ khách hàng, điểm bán và sản phẩm.')
                    : 'Tín hiệu đồng bộ khách hàng, điểm bán và sản phẩm chưa hoàn tất. Vui lòng kiểm tra dữ liệu nguồn trước khi vận hành.',
                'checks' => [
                    ['label' => 'Đồng bộ khách hàng / điểm bán', 'done' => $catalogSyncReady, 'detail' => (string) ($readiness['catalog_sync_summary'] ?? '')],
                    ['label' => 'Tín hiệu đồng bộ sản phẩm', 'done' => $catalogSyncReady],
                ],
                'cta' => [
                    'label' => 'Xem trạng thái chuẩn bị',
                    'url' => admin_url('kt_saas/tenant_marketplace_provisioning/salesflow-dms'),
                    'style' => 'default',
                ],
            ],
            [
                'index' => 6,
                'title' => 'Configure check-in rules',
                'done' => $hasOperationalSettings,
                'summary' => $hasOperationalSettings
                    ? (string) ($readiness['settings_summary'] ?? 'Source-backed operational settings are available for review.')
                    : 'No source-backed operational settings evidence is available yet.',
                'checks' => array_slice(array_map(function ($item) {
                    return [
                        'label' => (string) ($item['label'] ?? 'Setting'),
                        'done' => stripos((string) ($item['value'] ?? ''), 'Not configured') === false,
                        'detail' => (string) ($item['value'] ?? ''),
                    ];
                }, $operationsAudit), 0, 4),
                'cta' => [
                    'label' => 'Review Check-in Settings',
                    'url' => admin_url('kt_integration_hub/dms_settings'),
                    'style' => 'default',
                ],
            ],
            [
                'index' => 7,
                'title' => 'Open DMS',
                'done' => $openDmsReady,
                'summary' => $openDmsReady
                    ? 'CRM can now open DMS through the supported SSO or fallback entry path.'
                    : 'Open DMS remains blocked until runtime licensing, local user access, and a valid entry path all exist.',
                'checks' => [
                    ['label' => 'Runtime licensing enabled', 'done' => $entitlementActive],
                    ['label' => 'Local user inferred from staff provisioning', 'done' => $localUserReady],
                    ['label' => 'Open DMS route available', 'done' => (($actions['open_dms_mode'] ?? 'disabled') !== 'disabled')],
                ],
                'cta' => (($actions['open_dms_mode'] ?? 'disabled') !== 'disabled') ? [
                    'label' => 'Open DMS',
                    'url' => $actions['open_dms_url'] ?? null,
                    'style' => 'primary',
                ] : null,
            ],
        ];

        $mapConfig = $this->effectiveTenantMapConfig($tenantSettings);
        $mapReady = ($mapConfig['map_status'] ?? 'ready') === 'ready';
        $ordersReady = $entitlementActive && !empty($tenantSettings['features']['ORDERS_ENABLED']);
        $steps = [
            [
                'index' => 1,
                'title' => 'Gói DMS & trạng thái',
                'done' => $entitlementActive && $addonStatus === 'active',
                'summary' => $entitlementActive ? 'Gói DMS đang hoạt động và cấu hình hiệu lực đã sẵn sàng.' : 'Chọn và kích hoạt gói DMS trước khi thiết lập vận hành.',
                'checks' => [
                    ['label' => 'Thuê bao DMS đang hoạt động', 'done' => $addonStatus === 'active'],
                    ['label' => 'Config runtime có hiệu lực', 'done' => $entitlementActive, 'detail' => 'Version ' . (int) ($tenantSettings['config_version'] ?? 1)],
                ],
                'cta' => $entitlementActive ? null : ['label' => 'Xem gói DMS', 'url' => admin_url('kt_saas/tenant_marketplace_app/salesflow-dms'), 'style' => 'primary'],
            ],
            [
                'index' => 2,
                'title' => 'Nhân sự & vai trò',
                'done' => $localUserReady,
                'summary' => $localUserReady ? 'Nhân sự đã được cấp quyền vào workspace DMS.' : 'Cần cấp quyền và vai trò DMS cho ít nhất một nhân viên.',
                'checks' => [['label' => 'Có nhân sự đã provisioning', 'done' => $localUserReady, 'detail' => (int) ($readiness['provisioned_users'] ?? 0) . ' nhân sự']],
                'cta' => ['label' => 'Thiết lập nhân sự', 'url' => admin_url('kt_integration_hub/dms_setup_center?tab=users'), 'style' => 'default'],
            ],
            [
                'index' => 3,
                'title' => 'Điểm bán & phân công',
                'done' => $catalogSyncReady,
                'summary' => $catalogSyncReady ? 'Điểm bán đã có tín hiệu đồng bộ và sẵn sàng phân công.' : 'Đồng bộ điểm bán và kiểm tra phân công cho nhân viên.',
                'checks' => [['label' => 'Điểm bán đã đồng bộ', 'done' => $catalogSyncReady, 'detail' => (string) ($readiness['catalog_sync_summary'] ?? '')]],
                'cta' => ['label' => 'Xem điểm bán', 'url' => admin_url('kt_integration_hub/dms_store_products'), 'style' => 'default'],
            ],
            [
                'index' => 4,
                'title' => 'Sản phẩm & tồn kho',
                'done' => $catalogSyncReady,
                'summary' => $catalogSyncReady ? 'Danh mục sản phẩm đã có dữ liệu đồng bộ.' : 'Đồng bộ sản phẩm; tồn kho chỉ áp dụng khi gói DMS cho phép.',
                'checks' => [
                    ['label' => 'Sản phẩm đã đồng bộ', 'done' => $catalogSyncReady],
                    ['label' => 'Tính năng tồn kho', 'done' => !empty($tenantSettings['features']['INVENTORY_ENABLED'])],
                ],
                'cta' => ['label' => 'Xem sản phẩm & tồn kho', 'url' => admin_url('kt_integration_hub/dms_store_products'), 'style' => 'default'],
            ],
            [
                'index' => 5,
                'title' => 'Bản đồ & vị trí',
                'done' => $mapReady,
                'summary' => $mapReady ? 'Bản đồ do nền tảng quản lý đã sẵn sàng.' : 'Bản đồ đang thiếu cấu hình hoặc bị khóa bởi gói DMS.',
                'checks' => [
                    ['label' => 'Provider do nền tảng quản lý', 'done' => ($mapConfig['map_status'] ?? '') === 'ready', 'detail' => (string) ($mapConfig['map_provider'] ?? 'keyless')],
                    ['label' => 'Gói cho phép bản đồ', 'done' => !empty($tenantSettings['features']['MAP_ENABLED'])],
                ],
                'cta' => ['label' => 'Mở bản đồ hoạt động', 'url' => admin_url('kt_integration_hub/dms_map_activity'), 'style' => 'default'],
            ],
            [
                'index' => 6,
                'title' => 'Đơn hàng, hóa đơn & thanh toán',
                'done' => $ordersReady,
                'summary' => $ordersReady ? 'Luồng đơn hàng DMS và dữ liệu tài chính từ CRM đã được cấp quyền.' : 'Gói hiện tại chưa cho phép luồng đơn hàng hoặc tài chính.',
                'checks' => [
                    ['label' => 'Đơn hàng được bật', 'done' => !empty($tenantSettings['features']['ORDERS_ENABLED'])],
                    ['label' => 'CRM là nguồn hóa đơn/thanh toán', 'done' => true],
                ],
                'cta' => ['label' => 'Kiểm tra đơn hàng', 'url' => admin_url('kt_integration_hub/dms_orders_invoices'), 'style' => 'default'],
            ],
            [
                'index' => 7,
                'title' => 'Đồng bộ & kiểm tra cuối',
                'done' => $connectionReady && $openDmsReady,
                'summary' => $connectionReady && $openDmsReady ? 'Workspace, đồng bộ và đăng nhập một lần đã sẵn sàng.' : 'Kiểm tra kết nối, lỗi đồng bộ và quyền mở DMS.',
                'checks' => [
                    ['label' => 'Kết nối CRM-DMS', 'done' => $connectionReady],
                    ['label' => 'Workspace đúng tenant', 'done' => $workspaceReady, 'detail' => (string) ($workspace['host'] ?? '')],
                    ['label' => 'Đăng nhập một lần', 'done' => $openDmsReady],
                ],
                'cta' => ['label' => 'Mở Đồng bộ & Sức khỏe', 'url' => admin_url('kt_integration_hub/dms_sync_health'), 'style' => 'primary'],
            ],
        ];

        $steps = [
            [
                'index' => 1,
                'title' => 'Gói DMS',
                'done' => $entitlementActive && $addonStatus === 'active',
                'summary' => $entitlementActive
                    ? 'Gói DMS đang hoạt động và hạn mức đã sẵn sàng áp dụng.'
                    : 'Chọn hoặc kích hoạt gói DMS phù hợp trước khi bắt đầu vận hành.',
                'checks' => [
                    ['label' => 'Thuê bao DMS đang hoạt động', 'done' => $addonStatus === 'active'],
                    ['label' => 'Quyền sử dụng đã được áp dụng', 'done' => $entitlementActive],
                ],
                'cta' => $entitlementActive ? null : [
                    'label' => 'Chọn gói DMS',
                    'url' => admin_url('kt_saas/tenant_marketplace_app/salesflow-dms'),
                    'style' => 'primary',
                ],
            ],
            [
                'index' => 2,
                'title' => 'Nhân sự & vai trò',
                'done' => $localUserReady,
                'summary' => $localUserReady
                    ? 'Nhân sự đã được cấp quyền vào workspace DMS.'
                    : 'Thêm nhân viên thị trường, quản lý sales và kế toán; sau đó gán vai trò phù hợp.',
                'checks' => [
                    ['label' => 'Có nhân sự được cấp quyền DMS', 'done' => $localUserReady],
                    ['label' => 'Vai trò đã được gán', 'done' => $localUserReady],
                ],
                'cta' => [
                    'label' => 'Thiết lập nhân sự',
                    'url' => admin_url('kt_integration_hub/dms_setup_center?tab=users'),
                    'style' => 'default',
                ],
            ],
            [
                'index' => 3,
                'title' => 'Điểm bán & phân công',
                'done' => $catalogSyncReady,
                'summary' => $catalogSyncReady
                    ? 'Điểm bán đã có dữ liệu đồng bộ để phân công cho đội sales.'
                    : 'Đồng bộ điểm bán và gán từng điểm bán cho nhân viên phụ trách.',
                'checks' => [
                    ['label' => 'Điểm bán đã được đồng bộ', 'done' => $catalogSyncReady],
                    ['label' => 'Dữ liệu sẵn sàng để phân công', 'done' => $catalogSyncReady],
                ],
                'cta' => [
                    'label' => 'Xem điểm bán',
                    'url' => admin_url('kt_integration_hub/dms_store_products'),
                    'style' => 'default',
                ],
            ],
            [
                'index' => 4,
                'title' => 'Sản phẩm & tồn kho',
                'done' => $catalogSyncReady,
                'summary' => $catalogSyncReady
                    ? 'Danh mục sản phẩm đã có dữ liệu để đội sales tạo đơn hàng.'
                    : 'Kiểm tra danh mục sản phẩm; tồn kho chỉ áp dụng khi gói DMS cho phép.',
                'checks' => [
                    ['label' => 'Sản phẩm đã được đồng bộ', 'done' => $catalogSyncReady],
                    ['label' => 'Tính năng tồn kho được cấp', 'done' => !empty($tenantSettings['features']['INVENTORY_ENABLED'])],
                ],
                'cta' => [
                    'label' => 'Xem sản phẩm & tồn kho',
                    'url' => admin_url('kt_integration_hub/dms_store_products'),
                    'style' => 'default',
                ],
            ],
            [
                'index' => 5,
                'title' => 'Bản đồ & vị trí',
                'done' => $mapReady,
                'summary' => $mapReady
                    ? 'Bản đồ do nền tảng quản lý đã sẵn sàng cho workspace.'
                    : 'Kiểm tra quyền sử dụng bản đồ và trạng thái cấu hình vị trí.',
                'checks' => [
                    ['label' => 'Nhà cung cấp bản đồ đã sẵn sàng', 'done' => ($mapConfig['map_status'] ?? '') === 'ready'],
                    ['label' => 'Gói DMS cho phép sử dụng bản đồ', 'done' => !empty($tenantSettings['features']['MAP_ENABLED'])],
                ],
                'cta' => [
                    'label' => 'Mở bản đồ hoạt động',
                    'url' => admin_url('kt_integration_hub/dms_map_activity'),
                    'style' => 'default',
                ],
            ],
            [
                'index' => 6,
                'title' => 'Đơn hàng, hóa đơn & thanh toán',
                'done' => $ordersReady,
                'summary' => $ordersReady
                    ? 'Luồng đơn hàng DMS và dữ liệu tài chính từ CRM đã được cấp quyền.'
                    : 'Kiểm tra quyền tạo đơn hàng và luồng hóa đơn, thanh toán đọc từ CRM.',
                'checks' => [
                    ['label' => 'Đơn hàng được bật', 'done' => !empty($tenantSettings['features']['ORDERS_ENABLED'])],
                    ['label' => 'CRM là nguồn hóa đơn và thanh toán', 'done' => true],
                ],
                'cta' => [
                    'label' => 'Kiểm tra đơn hàng',
                    'url' => admin_url('kt_integration_hub/dms_orders_invoices'),
                    'style' => 'default',
                ],
            ],
            [
                'index' => 7,
                'title' => 'Đồng bộ & sức khỏe hệ thống',
                'done' => $connectionReady && $openDmsReady,
                'summary' => $connectionReady && $openDmsReady
                    ? 'Workspace, đồng bộ và đăng nhập một lần đã sẵn sàng.'
                    : 'Kiểm tra kết nối, quyền truy cập và các lỗi cần xử lý trước khi mở DMS.',
                'checks' => [
                    ['label' => 'Kết nối CRM-DMS hoạt động', 'done' => $connectionReady],
                    ['label' => 'Workspace đúng doanh nghiệp', 'done' => $workspaceReady],
                    ['label' => 'Đăng nhập một lần sẵn sàng', 'done' => $openDmsReady],
                ],
                'cta' => [
                    'label' => 'Xem sức khỏe đồng bộ',
                    'url' => admin_url('kt_integration_hub/dms_sync_health'),
                    'style' => 'primary',
                ],
            ],
        ];

        $completedSteps = count(array_filter($steps, static function ($step) {
            return !empty($step['done']);
        }));
        $totalSteps = count($steps);

        return [
            'title' => 'Thiết lập DMS cho đội sales thị trường',
            'state_code' => (string) ($readiness['code'] ?? 'UNKNOWN'),
            'state_label' => (string) ($readiness['label'] ?? 'Chưa xác định'),
            'hide' => false,
            'progress_label' => $completedSteps . '/' . $totalSteps,
            'progress_percent' => $totalSteps > 0 ? (int) round(($completedSteps / $totalSteps) * 100) : 0,
            'summary' => (string) ($readiness['summary'] ?? ''),
            'steps' => $steps,
            'next_action' => $completedSteps < $totalSteps ? ($steps[$completedSteps]['title'] ?? $steps[0]['title']) : 'Mở DMS',
        ];
    }

    private function buildCheckinSummaryCards(array $summary)
    {
        return [
            ['label' => 'Tổng check-in', 'value' => (int) ($summary['total'] ?? 0), 'detail' => 'Tổng số check-in theo bộ lọc hiện tại.'],
            ['label' => 'Hợp lệ', 'value' => (int) ($summary['valid'] ?? 0), 'detail' => 'Check-in hợp lệ theo quy tắc GPS và trạng thái xác minh.'],
            ['label' => 'Cần lưu ý', 'value' => (int) ($summary['warning'] ?? 0), 'detail' => 'Check-in cần lưu ý nhưng chưa phải lỗi nghiêm trọng.'],
            ['label' => 'Không hợp lệ', 'value' => (int) ($summary['invalid'] ?? 0), 'detail' => 'Check-in không đạt quy tắc vận hành.'],
            ['label' => 'Nhân viên có hoạt động', 'value' => (int) ($summary['active_staff_count'] ?? 0), 'detail' => 'Số nhân viên đã phát sinh check-in.'],
            ['label' => 'Check-in gần nhất', 'value' => trim((string) ($summary['latest_checkin_at'] ?? '')) ?: 'Chưa có dữ liệu', 'detail' => 'Thời điểm check-in mới nhất đã đồng bộ về CRM.'],
        ];
    }

    private function buildVisitSummaryCards(array $summary)
    {
        return [
            ['label' => 'Tổng lượt viếng thăm', 'value' => (int) ($summary['total'] ?? 0), 'detail' => 'Tổng số lượt viếng thăm theo bộ lọc.'],
            ['label' => 'Hoàn tất', 'value' => (int) ($summary['completed'] ?? 0), 'detail' => 'Lượt viếng thăm đã hoàn tất.'],
            ['label' => 'Hoàn thành một phần', 'value' => (int) ($summary['partial'] ?? 0), 'detail' => 'Lượt mới hoàn thành một phần.'],
            ['label' => 'Đã hủy', 'value' => (int) ($summary['cancelled'] ?? 0), 'detail' => 'Lượt bị hủy hoặc không hoàn tất.'],
            ['label' => 'Thời lượng trung bình', 'value' => isset($summary['average_duration']) && $summary['average_duration'] !== null ? number_format((float) $summary['average_duration'], 1, ',', '.') . ' phút' : 'Chưa có dữ liệu', 'detail' => 'Thời lượng trung bình của lượt viếng thăm.'],
            ['label' => 'Nhân viên có lượt viếng thăm', 'value' => (int) ($summary['staff_with_visits'] ?? 0), 'detail' => 'Số nhân viên đã có ít nhất một lượt viếng thăm.'],
        ];
    }

    private function buildRouteAnalyticsSummaryCards(array $summary)
    {
        $completedRate = (int) ($summary['total_visits'] ?? 0) > 0
            ? round(((int) ($summary['completed_visits'] ?? 0) / (int) ($summary['total_visits'])) * 100, 1) . '%'
            : 'Chưa có';

        return [
            ['label' => 'Điểm bán đã ghé', 'value' => (int) ($summary['store_coverage'] ?? 0), 'detail' => 'Điểm bán có hoạt động tuyến trong dữ liệu đã đồng bộ.'],
            ['label' => 'Điểm bán chưa ghé', 'value' => array_key_exists('stores_not_visited', $summary) && $summary['stores_not_visited'] !== null ? (int) $summary['stores_not_visited'] : 'Chưa có phạm vi đối chiếu', 'detail' => array_key_exists('stores_not_visited', $summary) && $summary['stores_not_visited'] !== null ? 'Điểm bán trong phạm vi nhưng chưa có hoạt động.' : 'CRM chưa có tổng phạm vi điểm bán đáng tin cậy cho tenant này.'],
            ['label' => 'Khách hàng đã ghé', 'value' => (int) ($summary['customer_coverage'] ?? 0), 'detail' => 'Khách hàng có hoạt động tuyến trong dữ liệu đã đồng bộ.'],
            ['label' => 'Khách hàng chưa ghé', 'value' => array_key_exists('customers_not_visited', $summary) && $summary['customers_not_visited'] !== null ? (int) $summary['customers_not_visited'] : 'Chưa có phạm vi đối chiếu', 'detail' => array_key_exists('customers_not_visited', $summary) && $summary['customers_not_visited'] !== null ? 'Khách hàng CRM đang hoạt động nhưng chưa có hoạt động tuyến.' : 'CRM chưa xác định được tổng phạm vi khách hàng đáng tin cậy.'],
            ['label' => 'Nhân sự hoạt động', 'value' => (int) ($summary['staff_activity'] ?? 0), 'detail' => 'Nhân sự thị trường có hoạt động đã đồng bộ.'],
            ['label' => 'Check-in', 'value' => (int) ($summary['total_checkins'] ?? 0), 'detail' => 'Tổng số check-in trong phạm vi lọc hiện tại.'],
            ['label' => 'Lượt ghé', 'value' => (int) ($summary['total_visits'] ?? 0), 'detail' => 'Tổng snapshot lượt ghé trong phạm vi lọc hiện tại.'],
            ['label' => 'Chất lượng lượt ghé', 'value' => $completedRate, 'detail' => ((int) ($summary['completed_visits'] ?? 0)) . ' hoàn tất / ' . ((int) ($summary['partial_visits'] ?? 0)) . ' một phần / ' . ((int) ($summary['cancelled_visits'] ?? 0)) . ' đã hủy'],
        ];
    }

    private function buildLiveTrackingSummaryCards(array $summary)
    {
        return [
            ['label' => 'Đang online', 'value' => (int) ($summary['online'] ?? 0), 'detail' => 'Nhân sự có snapshot vị trí mới.'],
            ['label' => 'Dữ liệu đã cũ', 'value' => (int) ($summary['stale'] ?? 0), 'detail' => 'Nhân sự có cập nhật vị trí cuối đã quá hạn.'],
            ['label' => 'Offline', 'value' => (int) ($summary['offline'] ?? 0), 'detail' => 'Nhân sự đang được đánh dấu offline trong dữ liệu tracking đã đồng bộ.'],
            ['label' => 'Tổng nhân sự theo dõi', 'value' => (int) ($summary['total'] ?? 0), 'detail' => 'Tổng nhân sự có snapshot tracking.'],
            ['label' => 'Cập nhật vị trí mới nhất', 'value' => trim((string) ($summary['latest_seen_at'] ?? '')) ?: 'Chưa có', 'detail' => 'Cập nhật tracking gần nhất CRM đã nhận.'],
        ];
    }

    private function buildManualReviewSummaryCards(array $summary)
    {
        return [
            ['label' => 'Đang mở', 'value' => (int) ($summary['open'] ?? 0), 'detail' => 'Mục xem xét thủ công đang chờ xử lý.'],
            ['label' => 'Đang xử lý', 'value' => (int) ($summary['in_progress'] ?? 0), 'detail' => 'Mục xem xét thủ công đang được xử lý.'],
            ['label' => 'Đã xử lý', 'value' => (int) ($summary['resolved'] ?? 0), 'detail' => 'Mục xem xét thủ công đã hoàn tất.'],
            ['label' => 'Ưu tiên cao / nghiêm trọng', 'value' => (int) ($summary['high_or_critical'] ?? 0), 'detail' => 'Mục cần chú ý khẩn cấp.'],
            ['label' => 'Mục mở lâu nhất', 'value' => trim((string) ($summary['oldest_open_at'] ?? '')) ?: 'Chưa có', 'detail' => 'Mục chưa xử lý lâu nhất trong danh sách.'],
        ];
    }

    private function buildNotificationSummaryCards(array $summary)
    {
        return [
            ['label' => 'Chưa đọc', 'value' => (int) ($summary['unread'] ?? 0), 'detail' => 'Thông báo chưa được đọc.'],
            ['label' => 'Nghiêm trọng', 'value' => (int) ($summary['critical'] ?? 0), 'detail' => 'Sự kiện thông báo nghiêm trọng.'],
            ['label' => 'Ưu tiên cao', 'value' => (int) ($summary['high'] ?? 0), 'detail' => 'Sự kiện thông báo ưu tiên cao.'],
            ['label' => 'Thông báo mới nhất', 'value' => trim((string) ($summary['latest_notification_at'] ?? '')) ?: 'Chưa có', 'detail' => 'Thông báo gần nhất CRM đã ghi nhận.'],
        ];
    }

    private function buildSettingsGroups(array $tenantSettings)
    {
        $features = (array) ($tenantSettings['features'] ?? []);
        $operationsAudit = array_values((array) ($tenantSettings['operations_audit'] ?? []));

        return [
            'app_status' => [
                ['label' => 'Tenant', 'value' => $tenantSettings['tenant_code'] ?? ($tenantSettings['tenant_id'] ?? '-')],
                ['label' => 'Plan', 'value' => $tenantSettings['plan'] ?? '-'],
                ['label' => 'DMS Status', 'value' => $tenantSettings['status'] ?? '-'],
                ['label' => 'Last Sync', 'value' => $tenantSettings['fetched_at'] ?? '-'],
            ],
            'enabled_capabilities' => [
                ['label' => 'Check-in', 'value' => !empty($features['CHECKIN_ENABLED']) ? 'Enabled' : 'Disabled'],
                ['label' => 'Visits', 'value' => !empty($features['VISIT_ENABLED']) ? 'Enabled' : 'Disabled'],
                ['label' => 'Route Analytics', 'value' => !empty($features['ROUTE_ANALYTICS_ENABLED']) ? 'Enabled' : 'Disabled'],
                ['label' => 'Live Tracking', 'value' => !empty($features['LIVE_TRACKING_ENABLED']) ? 'Enabled' : 'Disabled'],
                ['label' => 'Notification Bridge', 'value' => !empty($features['NOTIFICATION_BRIDGE_ENABLED']) ? 'Enabled' : 'Disabled'],
                ['label' => 'SSO', 'value' => !empty($features['SSO_ENABLED']) ? 'Enabled' : 'Disabled'],
            ],
            'operational_settings' => array_map(function ($item) {
                return [
                    'label' => $item['label'] ?? 'Chưa xác định',
                    'value' => $item['value'] ?? 'Not configured in DMS source',
                    'detail' => $item['evidence'] ?? '',
                ];
            }, $operationsAudit),
        ];
    }

    private function buildDmsWorkspaceSummary($tenantId, array $actions)
    {
        if (!isset($this->Kt_saas_model)) {
            $this->load->model('kt_saas/Kt_saas_model');
        }

        $tenant = $this->Kt_saas_model->get_tenant((int) $tenantId);
        $tenantSubdomain = strtolower(trim((string) ($tenant['subdomain'] ?? '')));
        $workspaceBaseUrl = trim((string) ($actions['fallback_login_url'] ?? ''));
        $workspaceBaseUrl = $workspaceBaseUrl !== ''
            ? preg_replace('#/login/?$#i', '', $workspaceBaseUrl)
            : '';
        $workspaceHost = '';
        if ($workspaceBaseUrl !== '') {
            $host = parse_url($workspaceBaseUrl, PHP_URL_HOST);
            $workspaceHost = is_string($host) ? trim($host) : '';
        }

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
                ? 'The DMS workspace host follows the CRM tenant subdomain automatically, so tenant admins can verify the destination before opening DMS.'
                : 'The tenant-specific DMS workspace host could not be derived from the current tenant context yet.',
        ];
    }

    private function buildOpenDmsExperience(array $actions)
    {
        $mode = (string) ($actions['open_dms_mode'] ?? 'disabled');

        if ($mode === 'sso') {
            return [
                'mode' => 'SSO',
                'entry_url' => $actions['open_dms_url'] ?? null,
                'summary' => 'CRM will issue a trusted SSO token and redirect the user into Salesflow DMS.',
                'fallback' => 'If SSO reports a missing local DMS user, contact an administrator and run staff reprovision.',
            ];
        }

        if ($mode === 'fallback') {
            return [
                'mode' => 'DMS login',
                'entry_url' => $actions['fallback_login_url'] ?? ($actions['open_dms_url'] ?? null),
                'summary' => 'SSO is disabled for this tenant. Users must authenticate on the DMS login page.',
                'fallback' => 'If the local DMS user is missing, contact an administrator and run staff reprovision.',
            ];
        }

        return [
            'mode' => 'Unavailable',
            'entry_url' => null,
            'summary' => 'This tenant cannot open DMS yet because SSO is disabled and no fallback DMS login URL is configured.',
            'fallback' => 'Review licensing and Salesflow DMS connection settings before go-live.',
        ];
    }

    private function effectiveTenantMapConfig(array $tenantSettings): array
    {
        $config = method_exists($this->Kt_integration_model, 'get_dms_platform_config')
            ? $this->Kt_integration_model->get_dms_platform_config()
            : ['map_status' => 'ready', 'map_provider' => 'keyless', 'managed_by' => 'platform'];
        $packageEnabled = !empty($tenantSettings['features']['MAP_ENABLED']);
        if (!$packageEnabled) {
            $config['map_status'] = 'disabled_by_package';
        } elseif (($config['map_status'] ?? '') === 'disabled') {
            $config['map_status'] = 'disabled_by_platform';
        } elseif (($config['map_status'] ?? '') === 'missing_config') {
            $config['map_status'] = 'missing_platform_config';
        } else {
            $config['map_status'] = 'ready';
        }
        $config['map_enabled'] = $config['map_status'] === 'ready';
        $config['package_enabled'] = $packageEnabled;
        $config['config_version'] = max(1, (int) ($tenantSettings['config_version'] ?? ($config['config_version'] ?? 1)));
        $config['managed_by'] = 'platform';

        return $config;
    }

    private function contractStore()
    {
        if ($this->dmsContractStore instanceof DmsIntegrationContractStore) {
            return $this->dmsContractStore;
        }

        require_once module_dir_path('kt_integration_hub', 'libraries/DmsIntegrationContractStore.php');
        $this->dmsContractStore = new DmsIntegrationContractStore();
        $this->dmsContractStore->ensureSchema();

        return $this->dmsContractStore;
    }

    private function contractReadinessForTenant(int $tenantId): array
    {
        $ci = &get_instance();
        if (!isset($ci->db)) {
            $companyId = $tenantId;
            return [
                'status' => 'UNKNOWN',
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'staff_mappings' => 0,
                'customer_store_mappings' => 0,
                'product_mappings' => 0,
                'warehouse_catalog_mappings' => 0,
                'open_issues' => 0,
                'failed_health_rows' => 0,
                'warehouse_catalog' => 'WAREHOUSE_CATALOG_REQUIRED',
                'warehouse_catalog_readiness' => 'WAREHOUSE_CATALOG_REQUIRED',
                'inventory' => 'WAREHOUSE_STORE_MAPPING_REQUIRED',
                'outbound_collection' => 'MANUAL_REVIEW_ONLY',
                'outbound_collection_readiness' => 'READY_MANUAL_REVIEW_ONLY',
                'delivery_projection' => 'STATUS_EVIDENCE_EXCEPTION_ONLY',
                'return_projection' => 'REQUEST_RECEIPT_ONLY',
                'supervisor_policy' => 'MONITOR_ONLY',
                'notifications' => 'NO_V1',
                'reports' => 'OPERATIONAL_VIEWS_ONLY',
            ];
        }

        if (!isset($this->Kt_saas_model)) {
            $this->load->model('kt_saas/Kt_saas_model');
        }
        $tenant = (array) $this->Kt_saas_model->get_tenant($tenantId);
        $companyId = (int) (kt_integration_hub_crm_company_id($tenant) ?? $tenantId);

        $readiness = $this->contractStore()->readiness($tenantId, $companyId);
        $sourceCounts = method_exists($this->Kt_integration_model, 'get_dms_bootstrap_source_counts')
            ? $this->Kt_integration_model->get_dms_bootstrap_source_counts($tenantId)
            : ['available' => false];

        if (!empty($sourceCounts['available'])) {
            $readiness['source_counts'] = $sourceCounts;
            $staffRequired = (int) ($sourceCounts['staff'] ?? 0) > 0;
            $customerRequired = (int) ($sourceCounts['customers'] ?? 0) > 0;
            $productRequired = (int) ($sourceCounts['products'] ?? 0) > 0;
            $warehouseRequired = (int) ($sourceCounts['warehouses'] ?? 0) > 0;
            $coreReady = (!$staffRequired || (int) ($readiness['staff_mappings'] ?? 0) > 0)
                && (!$customerRequired || (int) ($readiness['customer_store_mappings'] ?? 0) > 0)
                && (!$productRequired || (int) ($readiness['product_mappings'] ?? 0) > 0)
                && (!$warehouseRequired || (int) ($readiness['warehouse_catalog_mappings'] ?? 0) > 0);
            if ((int) ($readiness['open_issues'] ?? 0) === 0 && (int) ($readiness['failed_health_rows'] ?? 0) === 0) {
                $readiness['status'] = $coreReady ? 'READY' : 'PARTIAL';
            }
            $readiness['customer_store_required'] = $customerRequired;
            $readiness['product_required'] = $productRequired;
            $readiness['warehouse_catalog_required'] = $warehouseRequired;
        }

        return $readiness;
    }

    private function contractIssuesForTenant(int $tenantId): array
    {
        $ci = &get_instance();
        if (!isset($ci->db)) {
            return [];
        }

        if (!isset($this->Kt_saas_model)) {
            $this->load->model('kt_saas/Kt_saas_model');
        }
        $tenant = (array) $this->Kt_saas_model->get_tenant($tenantId);
        $companyId = (int) (kt_integration_hub_crm_company_id($tenant) ?? $tenantId);

        return $this->contractStore()->issues($tenantId, $companyId);
    }
}
