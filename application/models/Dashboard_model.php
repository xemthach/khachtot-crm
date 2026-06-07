<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Dashboard_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return array
     * Used in home dashboard page
     * Return all upcoming events this week
     */
    public function get_upcoming_events()
    {
        $monday_this_week = date('Y-m-d', strtotime('monday this week'));
        $sunday_this_week = date('Y-m-d', strtotime('sunday this week'));

        $this->db->where("(start BETWEEN '$monday_this_week' and '$sunday_this_week')");
        $this->db->where('(userid = ' . get_staff_user_id() . ' OR public = 1)');
        $this->db->order_by('start', 'desc');
        $this->db->limit(6);

        return $this->db->get(db_prefix() . 'events')->result_array();
    }

    /**
     * @param  integer (optional) Limit upcoming events
     * @return integer
     * Used in home dashboard page
     * Return total upcoming events next week
     */
    public function get_upcoming_events_next_week()
    {
        $monday_this_week = date('Y-m-d', strtotime('monday next week'));
        $sunday_this_week = date('Y-m-d', strtotime('sunday next week'));
        $this->db->where("(start BETWEEN '$monday_this_week' and '$sunday_this_week')");
        $this->db->where('(userid = ' . get_staff_user_id() . ' OR public = 1)');

        return $this->db->count_all_results(db_prefix() . 'events');
    }

    /**
     * @param  mixed
     * @return array
     * Used in home dashboard page, currency passed from javascript (undefined or integer)
     * Displays weekly payment statistics (chart)
     */
    public function get_weekly_payments_statistics($currency)
    {
        $all_payments                 = [];
        $has_permission_payments_view = staff_can('view',  'payments');
        $this->db->select(db_prefix() . 'invoicepaymentrecords.id, amount,' . db_prefix() . 'invoicepaymentrecords.date');
        $this->db->from(db_prefix() . 'invoicepaymentrecords');
        $this->db->join(db_prefix() . 'invoices', '' . db_prefix() . 'invoices.id = ' . db_prefix() . 'invoicepaymentrecords.invoiceid');
        $this->db->where('YEARWEEK(' . db_prefix() . 'invoicepaymentrecords.date) = YEARWEEK(CURRENT_DATE)');
        $this->db->where('' . db_prefix() . 'invoices.status !=', 5);
        if ($currency != 'undefined') {
            $this->db->where('currency', $currency);
        }

        if (!$has_permission_payments_view) {
            $this->db->where('invoiceid IN (SELECT id FROM ' . db_prefix() . 'invoices WHERE addedfrom=' . get_staff_user_id() . ' and addedfrom IN (SELECT staff_id FROM ' . db_prefix() . 'staff_permissions WHERE feature="invoices" AND capability="view_own"))');
        }

        // Current week
        $all_payments[] = $this->db->get()->result_array();
        $this->db->select(db_prefix() . 'invoicepaymentrecords.id, amount,' . db_prefix() . 'invoicepaymentrecords.date');
        $this->db->from(db_prefix() . 'invoicepaymentrecords');
        $this->db->join(db_prefix() . 'invoices', '' . db_prefix() . 'invoices.id = ' . db_prefix() . 'invoicepaymentrecords.invoiceid');
        $this->db->where('YEARWEEK(' . db_prefix() . 'invoicepaymentrecords.date) = YEARWEEK(CURRENT_DATE - INTERVAL 7 DAY) ');

        $this->db->where('' . db_prefix() . 'invoices.status !=', 5);
        if ($currency != 'undefined') {
            $this->db->where('currency', $currency);
        }

        if (!$has_permission_payments_view) {
            $this->db->where('invoiceid IN (SELECT id FROM ' . db_prefix() . 'invoices WHERE addedfrom=' . get_staff_user_id() . ' and addedfrom IN (SELECT staff_id FROM ' . db_prefix() . 'staff_permissions WHERE feature="invoices" AND capability="view_own"))');
        }

        // Last Week
        $all_payments[] = $this->db->get()->result_array();

        $chart = [
            'labels'   => get_weekdays(),
            'datasets' => [
                [
                    'label'           => _l('this_week_payments'),
                    'backgroundColor' => 'rgba(37,155,35,0.2)',
                    'borderColor'     => '#84c529',
                    'borderWidth'     => 1,
                    'tension'         => false,
                    'data'            => [
                        0,
                        0,
                        0,
                        0,
                        0,
                        0,
                        0,
                    ],
                ],
                [
                    'label'           => _l('last_week_payments'),
                    'backgroundColor' => 'rgba(197, 61, 169, 0.5)',
                    'borderColor'     => '#c53da9',
                    'borderWidth'     => 1,
                    'tension'         => false,
                    'data'            => [
                        0,
                        0,
                        0,
                        0,
                        0,
                        0,
                        0,
                    ],
                ],
            ],
        ];


        for ($i = 0; $i < count($all_payments); $i++) {
            foreach ($all_payments[$i] as $payment) {
                $payment_day = date('l', strtotime($payment['date']));
                $x           = 0;
                foreach (get_weekdays_original() as $day) {
                    if ($payment_day == $day) {
                        $chart['datasets'][$i]['data'][$x] += $payment['amount'];
                    }
                    $x++;
                }
            }
        }

        return $chart;
    }


    /**
     * @param  mixed
     * @return array
     * Used in home dashboard page, currency passed from javascript (undefined or integer)
     * Displays monthly payment statistics (chart)
     */
    public function get_monthly_payments_statistics($currency)
    {
        $all_payments                 = [];
        $has_permission_payments_view = staff_can('view',  'payments');
        $this->db->select('SUM(amount) as total, MONTH(' . db_prefix() . 'invoicepaymentrecords.date) as month');
        $this->db->from(db_prefix() . 'invoicepaymentrecords');
        $this->db->join(db_prefix() . 'invoices', '' . db_prefix() . 'invoices.id = ' . db_prefix() . 'invoicepaymentrecords.invoiceid');
        $this->db->where('YEAR(' . db_prefix() . 'invoicepaymentrecords.date) = YEAR(CURRENT_DATE)');
        $this->db->where('' . db_prefix() . 'invoices.status !=', 5);
        $this->db->group_by('month');

        if ($currency != 'undefined') {
            $this->db->where('currency', $currency);
        }

        if (!$has_permission_payments_view) {
            $this->db->where('invoiceid IN (SELECT id FROM ' . db_prefix() . 'invoices WHERE addedfrom=' . get_staff_user_id() . ' and addedfrom IN (SELECT staff_id FROM ' . db_prefix() . 'staff_permissions WHERE feature="invoices" AND capability="view_own"))');
        }

        $all_payments = $this->db->get()->result_array();

        for ($i = 1; $i <= 12; $i++) {
            if (!isset($all_payments[$i])) {
                $all_payments[$i]['total'] = 0;
                $all_payments[$i]['month'] = $i;
            }
            $all_payments[$i]['label'] = _l(date("F", mktime(0, 0, 0, $i, 1)));
        }
        usort($all_payments, function($a, $b) {
            return (int) $a['month'] <=> (int) $b['month'];
        });

        $chart = [
            'labels'   => array_column($all_payments, 'label'),
            'datasets' => [
                [
                    'label'           => _l('report_sales_type_income'),
                    'backgroundColor' => 'rgba(37,155,35,0.2)',
                    'borderColor'     => '#84c529',
                    'borderWidth'     => 1,
                    'tension'         => false,
                    'data'            => array_column($all_payments, 'total'),
                ],
            ],
        ];
        return $chart;
    }

    public function projects_status_stats()
    {
        $this->load->model('projects_model');
        $statuses = $this->projects_model->get_project_statuses();
        $colors   = get_system_favourite_colors();

        $chart = [
            'labels'   => [],
            'datasets' => [],
        ];

        $_data                         = [];
        $_data['data']                 = [];
        $_data['backgroundColor']      = [];
        $_data['hoverBackgroundColor'] = [];
        $_data['statusLink']           = [];


        $has_permission = staff_can('view',  'projects');
        $sql            = '';
        foreach ($statuses as $status) {
            $sql .= ' SELECT COUNT(*) as total';
            $sql .= ' FROM ' . db_prefix() . 'projects';
            $sql .= ' WHERE status=' . $status['id'];
            if (!$has_permission) {
                $sql .= ' AND id IN (SELECT project_id FROM ' . db_prefix() . 'project_members WHERE staff_id=' . get_staff_user_id() . ')';
            }
            $sql .= ' UNION ALL ';
            $sql = trim($sql);
        }

        $result = [];
        if ($sql != '') {
            // Remove the last UNION ALL
            $sql    = substr($sql, 0, -10);
            $result = $this->db->query($sql)->result();
        }

        foreach ($statuses as $key => $status) {
            array_push($_data['statusLink'], admin_url('projects?status=' . $status['id']));
            array_push($chart['labels'], $status['name']);
            array_push($_data['backgroundColor'], $status['color']);
            array_push($_data['hoverBackgroundColor'], adjust_color_brightness($status['color'], -20));
            array_push($_data['data'], $result[$key]->total);
        }

        $chart['datasets'][]           = $_data;
        $chart['datasets'][0]['label'] = _l('home_stats_by_project_status');

        return $chart;
    }

    public function leads_status_stats()
    {
        $chart = [
            'labels'   => [],
            'datasets' => [],
        ];

        $_data                         = [];
        $_data['data']                 = [];
        $_data['backgroundColor']      = [];
        $_data['hoverBackgroundColor'] = [];
        $_data['statusLink']           = [];

        $result = get_leads_summary();

        foreach ($result as $status) {
            if ($status['color'] == '') {
                $status['color'] = '#737373';
            }
            array_push($chart['labels'], $status['name']);
            array_push($_data['backgroundColor'], $status['color']);
            if (!isset($status['junk']) && !isset($status['lost'])) {
                array_push($_data['statusLink'], admin_url('leads?status=' . $status['id']));
            }
            array_push($_data['hoverBackgroundColor'], adjust_color_brightness($status['color'], -20));
            array_push($_data['data'], $status['total']);
        }

        $chart['datasets'][] = $_data;

        return $chart;
    }

    /**
     * Display total tickets awaiting reply by department (chart)
     * @return array
     */
    public function tickets_awaiting_reply_by_department()
    {
        $this->load->model('departments_model');
        $departments = $this->departments_model->get();
        $colors      = get_system_favourite_colors();
        $chart       = [
            'labels'   => [],
            'datasets' => [],
        ];

        $_data                         = [];
        $_data['data']                 = [];
        $_data['backgroundColor']      = [];
        $_data['hoverBackgroundColor'] = [];

        $i = 0;
        foreach ($departments as $department) {
            if (!is_admin()) {
                if (get_option('staff_access_only_assigned_departments') == 1) {
                    $staff_deparments_ids = $this->departments_model->get_staff_departments(get_staff_user_id(), true);
                    $departments_ids      = [];
                    if (count($staff_deparments_ids) == 0) {
                        $departments = $this->departments_model->get();
                        foreach ($departments as $department) {
                            array_push($departments_ids, $department['departmentid']);
                        }
                    } else {
                        $departments_ids = $staff_deparments_ids;
                    }
                    if (count($departments_ids) > 0) {
                        $this->db->where('department IN (SELECT departmentid FROM ' . db_prefix() . 'staff_departments WHERE departmentid IN (' . implode(',', $departments_ids) . ') AND staffid="' . get_staff_user_id() . '")');
                    }
                }
            }
            $this->db->where_in('status', [
                1,
                2,
                4,
            ]);

            $this->db->where('department', $department['departmentid']);
            $this->db->where(db_prefix() . 'tickets.merged_ticket_id IS NULL', null, false);
            $total = $this->db->count_all_results(db_prefix() . 'tickets');

            if ($total > 0) {
                $color = '#333';
                if (isset($colors[$i])) {
                    $color = $colors[$i];
                }
                array_push($chart['labels'], $department['name']);
                array_push($_data['backgroundColor'], $color);
                array_push($_data['hoverBackgroundColor'], adjust_color_brightness($color, -20));
                array_push($_data['data'], $total);
            }
            $i++;
        }

        $chart['datasets'][] = $_data;

        return $chart;
    }

    /**
     * Display total tickets awaiting reply by status (chart)
     * @return array
     */
    public function tickets_awaiting_reply_by_status()
    {
        $this->load->model('tickets_model');
        $statuses             = $this->tickets_model->get_ticket_status();
        $_statuses_with_reply = [
            1,
            2,
            4,
        ];

        $chart = [
            'labels'   => [],
            'datasets' => [],
        ];

        $_data                         = [];
        $_data['data']                 = [];
        $_data['backgroundColor']      = [];
        $_data['hoverBackgroundColor'] = [];
        $_data['statusLink']           = [];

        foreach ($statuses as $status) {
            if (in_array($status['ticketstatusid'], $_statuses_with_reply)) {
                if (!is_admin()) {
                    if (get_option('staff_access_only_assigned_departments') == 1) {
                        $staff_deparments_ids = $this->departments_model->get_staff_departments(get_staff_user_id(), true);
                        $departments_ids      = [];
                        if (count($staff_deparments_ids) == 0) {
                            $departments = $this->departments_model->get();
                            foreach ($departments as $department) {
                                array_push($departments_ids, $department['departmentid']);
                            }
                        } else {
                            $departments_ids = $staff_deparments_ids;
                        }
                        if (count($departments_ids) > 0) {
                            $this->db->where('department IN (SELECT departmentid FROM ' . db_prefix() . 'staff_departments WHERE departmentid IN (' . implode(',', $departments_ids) . ') AND staffid="' . get_staff_user_id() . '")');
                        }
                    }
                }

                $this->db->where('status', $status['ticketstatusid']);
                $this->db->where(db_prefix() . 'tickets.merged_ticket_id IS NULL', null, false);
                $total = $this->db->count_all_results(db_prefix() . 'tickets');
                if ($total > 0) {
                    array_push($chart['labels'], ticket_status_translate($status['ticketstatusid']));
                    array_push($_data['statusLink'], admin_url('tickets/index/' . $status['ticketstatusid']));
                    array_push($_data['backgroundColor'], $status['statuscolor']);
                    array_push($_data['hoverBackgroundColor'], adjust_color_brightness($status['statuscolor'], -20));
                    array_push($_data['data'], $total);
                }
            }
        }

        $chart['datasets'][] = $_data;

        return $chart;
    }

    public function get_tenant_business_dashboard(array $tenant = [])
    {
        $tenant = is_array($tenant) ? $tenant : [];
        $permissions = $this->tenantDashboardPermissions();
        $todayActions = $this->tenantTodayActions($permissions);
        $usageHealth = $this->tenantUsageHealth($tenant);
        $integrationHealth = $this->tenantIntegrationHealth($tenant);
        $hasMinimumData = $this->tenantHasMinimumBusinessData();
        $checklist = $this->tenantOnboardingChecklist($tenant, $permissions);

        return [
            'tenant' => $tenant,
            'permissions' => $permissions,
            'has_minimum_data' => $hasMinimumData,
            'snapshot' => $this->tenantBusinessSnapshot($permissions),
            'business_health' => $this->tenantBusinessHealthScore($todayActions, $usageHealth, $integrationHealth, $hasMinimumData),
            'today_actions' => $todayActions,
            'sales_funnel' => $this->tenantSalesFunnel($permissions),
            'finance' => $this->tenantFinanceSummary($permissions),
            'usage_health' => $usageHealth,
            'integration_health' => $integrationHealth,
            'quick_actions' => $this->tenantQuickActions($permissions),
            'onboarding' => $checklist,
            'onboarding_checklist' => $checklist,
        ];
    }

    protected function tenantDashboardPermissions()
    {
        return [
            'admin' => is_admin(),
            'customers' => is_admin() || staff_can('view', 'customers') || staff_can('view_own', 'customers'),
            'leads' => is_admin() || staff_can('view', 'leads') || staff_can('view_own', 'leads'),
            'invoices' => is_admin() || staff_can('view', 'invoices') || staff_can('view_own', 'invoices') || get_option('allow_staff_view_invoices_assigned') == '1',
            'payments' => is_admin() || staff_can('view', 'payments') || staff_can('view_own', 'invoices'),
            'estimates' => is_admin() || staff_can('view', 'estimates') || staff_can('view_own', 'estimates'),
            'contracts' => is_admin() || staff_can('view', 'contracts') || staff_can('view_own', 'contracts'),
            'tasks' => is_admin() || staff_can('view', 'tasks') || staff_can('view_own', 'tasks'),
            'tickets' => is_admin() || staff_can('view', 'tickets') || staff_can('view_own', 'tickets'),
            'expenses' => is_admin() || staff_can('view', 'expenses') || staff_can('view_own', 'expenses'),
            'settings' => is_admin() || (function_exists('kt_saas_can_manage_workspace_settings') && kt_saas_can_manage_workspace_settings()),
        ];
    }

    protected function tenantBusinessSnapshot(array $permissions)
    {
        $paidMonth = $permissions['payments'] ? $this->tenantMonthlyPaidRevenue() : null;

        return [
            ['label' => 'Khách hàng', 'value' => $permissions['customers'] ? $this->tenantCount('clients', ['active' => 1]) : null, 'url' => admin_url('clients'), 'visible' => $permissions['customers']],
            ['label' => 'Khách tiềm năng', 'value' => $permissions['leads'] ? $this->tenantOpenLeadsCount() : null, 'url' => admin_url('leads'), 'visible' => $permissions['leads']],
            ['label' => 'Doanh thu tháng này', 'value' => $paidMonth, 'is_money' => true, 'url' => admin_url('payments'), 'visible' => $permissions['payments']],
            ['label' => 'Hóa đơn chờ thanh toán', 'value' => $permissions['invoices'] ? $this->tenantOpenInvoicesCount() : null, 'url' => admin_url('invoices'), 'visible' => $permissions['invoices']],
        ];
    }

    protected function tenantBusinessHealthScore(array $todayActions, array $usageHealth, array $integrationHealth, $hasMinimumData = true)
    {
        if (!$hasMinimumData) {
            return [
                'score' => null,
                'message' => 'Hoàn tất checklist khởi tạo để bắt đầu đo sức khỏe doanh nghiệp.',
                'level' => 'muted',
                'status_label' => 'Sẵn sàng khởi tạo',
            ];
        }

        $score = 100;
        $reasons = [];

        foreach ($todayActions as $action) {
            $count = (int) ($action['count'] ?? 0);
            if ($count <= 0) {
                continue;
            }
            $label = (string) ($action['label'] ?? '');
            $level = (string) ($action['level'] ?? 'warning');
            $penalty = $level === 'danger' ? min(24, $count * 6) : min(14, $count * 3);
            $score -= $penalty;
            if (count($reasons) < 2) {
                $reasons[] = function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label);
            }
        }

        foreach ((array) ($usageHealth['limits'] ?? []) as $limit) {
            if (($limit['level'] ?? '') === 'danger') {
                $score -= 15;
                $reasons[] = 'hạn mức CRM gần đầy';
                break;
            }
            if (($limit['level'] ?? '') === 'warning') {
                $score -= 8;
                $reasons[] = 'hạn mức CRM cần theo dõi';
                break;
            }
        }

        if (!empty($usageHealth['days_left']) && (int) $usageHealth['days_left'] <= 7) {
            $score -= 12;
            $reasons[] = 'gói CRM sắp hết hạn';
        }

        foreach ($integrationHealth as $integration) {
            if (($integration['level'] ?? '') === 'danger') {
                $score -= 12;
                $reasons[] = 'tích hợp có lỗi';
                break;
            }
            if (($integration['level'] ?? '') === 'warning') {
                $score -= 5;
            }
        }

        $score = max(0, min(100, $score));
        $message = 'Doanh nghiệp đang vận hành tốt';
        if ($score < 80 && $reasons) {
            $message = 'Cần xử lý ' . implode(' và ', array_slice(array_unique($reasons), 0, 2));
        }
        if ($score < 60) {
            $message = 'Cần ưu tiên xử lý các điểm nghẽn vận hành';
        }

        return [
            'score' => $score,
            'message' => $message,
            'level' => $score >= 85 ? 'success' : ($score >= 70 ? 'warning' : 'danger'),
            'status_label' => $score . '/100',
        ];
    }

    protected function tenantTodayActions(array $permissions)
    {
        $actions = [];
        if ($permissions['invoices']) {
            $actions[] = ['label' => 'Hóa đơn quá hạn', 'count' => $this->tenantOverdueInvoicesCount(), 'level' => 'danger', 'url' => admin_url('invoices?status=4')];
        }
        if ($permissions['estimates']) {
            $actions[] = ['label' => 'Báo giá sắp hết hạn', 'count' => $this->tenantExpiringEstimatesCount(), 'level' => 'warning', 'url' => admin_url('estimates')];
        }
        if ($permissions['leads']) {
            $actions[] = ['label' => 'Lead chưa chăm sóc', 'count' => $this->tenantStaleLeadsCount(), 'level' => 'warning', 'url' => admin_url('leads')];
        }
        if ($permissions['tickets']) {
            $actions[] = ['label' => 'Ticket chưa xử lý', 'count' => $this->tenantOpenTicketsCount(), 'level' => 'warning', 'url' => admin_url('tickets')];
        }
        if ($permissions['tasks']) {
            $actions[] = ['label' => 'Công việc quá hạn', 'count' => $this->tenantOverdueTasksCount(), 'level' => 'danger', 'url' => admin_url('tasks')];
        }
        if ($permissions['contracts']) {
            $actions[] = ['label' => 'Hợp đồng sắp hết hạn', 'count' => $this->tenantExpiringContractsCount(), 'level' => 'warning', 'url' => admin_url('contracts')];
        }
        if ($this->tenantTableExists('kt_sepay_payment_requests')) {
            $actions[] = ['label' => 'Thanh toán chờ đối soát', 'count' => $this->tenantCount('kt_sepay_payment_requests', ['status' => 'pending']), 'level' => 'warning', 'url' => admin_url('kt_sepay/tenant_portal')];
        }
        if ($this->tenantTableExists('kt_matbao_invoice_records')) {
            $actions[] = ['label' => 'Hóa đơn điện tử lỗi phát hành', 'count' => $this->tenantMatbaoIssueCount(), 'level' => 'danger', 'url' => admin_url('kt_matbao_invoice/tenant/invoices')];
        }

        return array_values(array_filter($actions, static function ($row) {
            return (int) ($row['count'] ?? 0) > 0;
        }));
    }

    protected function tenantSalesFunnel(array $permissions)
    {
        if (!$permissions['leads'] || !$this->tenantTableExists('leads') || !$this->tenantTableExists('leads_status')) {
            return [];
        }

        $statuses = $this->db->order_by('statusorder', 'asc')->get(db_prefix() . 'leads_status')->result_array();
        $rows = [];
        foreach ($statuses as $status) {
            $this->db->where('status', (int) $status['id']);
            $this->applyLeadOpenFilters();
            $rows[] = [
                'label' => $this->tenantLeadFunnelLabel((string) ($status['name'] ?? '')),
                'count' => (int) $this->db->count_all_results(db_prefix() . 'leads'),
                'color' => (string) ($status['color'] ?? '#2563eb'),
                'url' => admin_url('leads?status=' . (int) $status['id']),
            ];
        }

        if (count($rows) <= 8) {
            return ['rows' => $rows, 'total_statuses' => count($rows), 'all_url' => admin_url('leads')];
        }

        return [
            'rows' => array_slice($rows, 0, 4),
            'total_statuses' => count($rows),
            'all_url' => admin_url('leads'),
        ];
    }

    protected function tenantLeadFunnelLabel($label)
    {
        $label = trim((string) preg_replace('/\s*\(([^)]*)\)\s*/u', '', (string) $label));
        $map = [
            'new' => 'Mới',
            'contacted' => 'Đã liên hệ',
            'qualified' => 'Đủ điều kiện',
            'proposal sent' => 'Đã gửi báo giá',
            'won' => 'Thành công',
            'lost' => 'Thất bại',
        ];
        $key = function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label);

        return $map[$key] ?? $label;
    }

    protected function tenantFinanceSummary(array $permissions)
    {
        if (!$permissions['invoices']) {
            return [];
        }

        $revenueMonth = $permissions['payments'] ? $this->tenantMonthlyPaidRevenue() : null;
        $collectedToday = $permissions['payments'] ? $this->tenantPaymentsToday() : null;
        $open = $this->tenantInvoiceSum('status != 2 AND status != 5');
        $overdue = $this->tenantInvoiceSum('status != 2 AND status != 5 AND duedate IS NOT NULL AND duedate < ' . $this->db->escape(date('Y-m-d')));
        $dueSevenDays = $this->tenantInvoiceSum('status != 2 AND status != 5 AND duedate IS NOT NULL AND duedate >= ' . $this->db->escape(date('Y-m-d')) . ' AND duedate <= ' . $this->db->escape(date('Y-m-d', strtotime('+7 days'))));
        $expenses = $permissions['expenses'] ? $this->tenantMonthlyExpenses() : null;
        $profit = ($revenueMonth !== null && $expenses !== null && $expenses > 0) ? ($revenueMonth - $expenses) : null;

        return [
            ['label' => 'Thu tiền hôm nay', 'value' => $collectedToday, 'is_money' => true, 'visible' => $permissions['payments'], 'hide_zero' => true],
            ['label' => 'Thu tiền tháng này', 'value' => $revenueMonth, 'is_money' => true, 'visible' => $permissions['payments']],
            ['label' => 'Công nợ phải thu', 'value' => $open, 'is_money' => true, 'visible' => true],
            ['label' => 'Hóa đơn đến hạn trong 7 ngày', 'value' => $dueSevenDays, 'is_money' => true, 'visible' => true, 'hide_zero' => true],
            ['label' => 'Quá hạn', 'value' => $overdue, 'is_money' => true, 'visible' => true, 'hide_zero' => true],
            ['label' => 'Chi phí tháng', 'value' => $expenses, 'is_money' => true, 'visible' => $permissions['expenses'] && $expenses > 0],
            ['label' => 'Lợi nhuận tạm tính', 'value' => $profit, 'is_money' => true, 'visible' => $profit !== null],
        ];
    }

    protected function tenantUsageHealth(array $tenant)
    {
        $rows = [];
        if (empty($tenant) || !defined('KT_SAAS_MODULE')) {
            return ['plan_name' => '', 'period_end' => '', 'days_left' => null, 'limits' => []];
        }

        $profile = [];
        $usage = [];
        $plan = [];
        try {
            require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');
            $service = new TenantEntitlementService();
            $profile = (array) $service->getRuntimeProfile($tenant);
            $usage = (array) $service->getTenantUsageSnapshot($tenant);
            $plan = (array) ($profile['plan'] ?? []);
        } catch (Throwable $e) {
            return ['plan_name' => '', 'period_end' => '', 'days_left' => null, 'limits' => []];
        }

        $limits = (array) ($profile['limits'] ?? []);
        $metricMap = [
            'staff' => 'Nhân sự',
            'clients' => 'Khách hàng',
            'invoices' => 'Hóa đơn',
            'storage_mb' => 'Dung lượng',
        ];

        foreach ($metricMap as $metric => $label) {
            $limit = (float) ($limits[$metric] ?? ($plan['limit_' . $metric] ?? 0));
            if ($metric === 'storage_mb') {
                $limit = (float) ($limits[$metric] ?? ($plan['limit_storage_mb'] ?? 0));
            }
            $current = (float) ($usage[$metric] ?? 0);
            $percent = $limit > 0 ? min(999, ($current / $limit) * 100) : 0;
            $level = $limit > 0 && $percent >= 95 ? 'danger' : ($limit > 0 && $percent >= 80 ? 'warning' : 'success');
            $rows[] = [
                'metric' => $metric,
                'label' => $label,
                'current' => $current,
                'limit' => $limit,
                'percent' => $percent,
                'level' => $level,
                'display' => $limit > 0 ? number_format($current, 0, ',', '.') . ' / ' . number_format($limit, 0, ',', '.') : number_format($current, 0, ',', '.') . ' / Không giới hạn',
                'forecast' => 'Chưa đủ dữ liệu',
            ];
        }

        $periodEnd = (string) ($tenant['expires_at'] ?? '');
        $daysLeft = null;
        if ($periodEnd !== '') {
            $daysLeft = (int) floor((strtotime($periodEnd . ' 23:59:59') - time()) / 86400);
        }

        return [
            'plan_name' => (string) ($plan['plan_name'] ?? ($tenant['plan_name'] ?? '')),
            'period_end' => $periodEnd,
            'days_left' => $daysLeft,
            'limits' => $rows,
        ];
    }

    protected function tenantIntegrationHealth(array $tenant)
    {
        $tenantId = (int) ($tenant['id'] ?? 0);
        $rows = [];

        $emailActive = (bool) (get_option('smtp_email') || get_option('email_protocol'));
        $rows[] = [
            'label' => 'Email gửi đi',
            'status_label' => $emailActive ? 'Hoạt động' : 'Cần cấu hình',
            'detail' => $emailActive ? 'Đã có cấu hình gửi email' : 'Chưa có cấu hình gửi email',
            'level' => $emailActive ? 'success' : 'warning',
            'url' => admin_url('kt_saas/tenant_settings'),
        ];

        if (defined('KT_SEPAY_MODULE')) {
            try {
                $this->load->model('kt_sepay/Kt_sepay_model');
                $active = $tenantId > 0 && $this->Kt_sepay_model->is_active($tenantId, false);
                $summary = $tenantId > 0 ? $this->Kt_sepay_model->get_summary($tenantId) : [];
                $pending = $this->tenantTableExists('kt_sepay_payment_requests') ? $this->tenantCount('kt_sepay_payment_requests', ['status' => 'pending']) : 0;
                $rows[] = [
                    'label' => 'KT SePay',
                    'status_label' => $active ? 'Hoạt động' : 'Cần cấu hình',
                    'detail' => $active ? ('Chờ đối soát: ' . number_format($pending, 0, ',', '.')) : 'Chưa bật tài khoản nhận thanh toán',
                    'level' => $active ? (((int) ($summary['error_txs'] ?? 0) > 0 || (int) ($summary['unmatched_txs'] ?? 0) > 0) ? 'warning' : 'success') : 'warning',
                    'url' => admin_url('kt_sepay/tenant_portal'),
                ];
            } catch (Throwable $e) {
                $rows[] = ['label' => 'KT SePay', 'status_label' => 'Có lỗi', 'detail' => 'Không đọc được trạng thái SePay', 'level' => 'danger', 'url' => admin_url('kt_sepay/tenant_portal')];
            }
        }

        if (defined('KT_MATBAO_INVOICE_MODULE')) {
            try {
                $this->load->model('kt_matbao_invoice/Kt_matbao_invoice_model');
                $summary = $tenantId > 0 ? $this->Kt_matbao_invoice_model->get_tenant_addon_usage_summary($tenantId) : [];
                $hasQuota = ((int) ($summary['einvoice_remaining'] ?? 0) > 0) || ((int) ($summary['hsm_active'] ?? 0) > 0);
                $rows[] = [
                    'label' => 'KT Mắt Bão Invoice',
                    'status_label' => $hasQuota ? 'Hoạt động' : 'Chưa bật',
                    'detail' => $hasQuota ? ('Hóa đơn còn lại: ' . number_format((int) ($summary['einvoice_remaining'] ?? 0), 0, ',', '.')) : 'Chưa có hạn mức hóa đơn điện tử',
                    'level' => $hasQuota ? 'success' : 'muted',
                    'url' => admin_url('kt_matbao_invoice/tenant/addons'),
                ];
            } catch (Throwable $e) {
                $rows[] = ['label' => 'KT Mắt Bão Invoice', 'status_label' => 'Có lỗi', 'detail' => 'Không đọc được trạng thái hóa đơn điện tử', 'level' => 'danger', 'url' => admin_url('kt_matbao_invoice/tenant/addons')];
            }
        }

        $rows[] = [
            'label' => 'Kho nội bộ',
            'status_label' => $this->tenantTableExists('items') ? 'Hoạt động' : 'Chưa bật',
            'detail' => $this->tenantTableExists('items') ? 'Có danh mục hàng hóa/dịch vụ' : 'Chưa có dữ liệu kho',
            'level' => $this->tenantTableExists('items') ? 'success' : 'muted',
            'url' => admin_url('kt_inventory'),
        ];

        return $rows;
    }

    protected function tenantQuickActions(array $permissions)
    {
        return [
            ['label' => 'Thêm khách hàng', 'url' => admin_url('clients/client'), 'visible' => is_admin() || staff_can('create', 'customers')],
            ['label' => 'Thêm lead', 'url' => admin_url('leads'), 'visible' => is_admin() || staff_can('create', 'leads')],
            ['label' => 'Tạo báo giá', 'url' => admin_url('estimates/estimate'), 'visible' => is_admin() || staff_can('create', 'estimates')],
            ['label' => 'Tạo hóa đơn', 'url' => admin_url('invoices/invoice'), 'visible' => is_admin() || staff_can('create', 'invoices')],
            ['label' => 'Tạo yêu cầu thanh toán', 'url' => admin_url('kt_sepay/tenant_portal'), 'visible' => defined('KT_SEPAY_MODULE')],
            ['label' => 'Phát hành hóa đơn điện tử', 'url' => admin_url('kt_matbao_invoice/tenant/invoices'), 'visible' => defined('KT_MATBAO_INVOICE_MODULE')],
        ];
    }

    protected function tenantOnboardingChecklist(array $tenant, array $permissions = [])
    {
        $items = [
            ['label' => 'Thêm khách hàng đầu tiên', 'done' => $this->tenantCount('clients') > 0, 'url' => admin_url('clients/client'), 'action_visible' => is_admin() || staff_can('create', 'customers')],
            ['label' => 'Thêm lead đầu tiên', 'done' => $this->tenantCount('leads') > 0, 'url' => admin_url('leads'), 'action_visible' => is_admin() || staff_can('create', 'leads')],
            ['label' => 'Cấu hình email', 'done' => (bool) (get_option('smtp_email') || get_option('email_protocol')), 'url' => admin_url('kt_saas/tenant_settings'), 'action_visible' => !empty($permissions['settings'])],
            ['label' => 'Cấu hình SePay', 'done' => $this->tenantIntegrationDone('KT SePay', $tenant), 'url' => admin_url('kt_sepay/tenant_portal'), 'action_visible' => is_admin()],
            ['label' => 'Cấu hình hóa đơn điện tử', 'done' => $this->tenantIntegrationDone('KT Mắt Bão Invoice', $tenant), 'url' => admin_url('kt_matbao_invoice/tenant/addons'), 'action_visible' => is_admin()],
            ['label' => 'Mời nhân viên đầu tiên', 'done' => $this->tenantCount('staff', ['active' => 1]) > 1, 'url' => admin_url('staff'), 'action_visible' => is_admin()],
        ];

        $remaining = array_values(array_filter($items, static function ($item) {
            return empty($item['done']);
        }));

        return $remaining === [] ? [] : $items;
    }

    protected function tenantHasMinimumBusinessData()
    {
        if ($this->tenantCount('clients') > 0 || $this->tenantCount('leads') > 0 || $this->tenantCount('invoices') > 0) {
            return true;
        }

        if ($this->tenantMonthlyPaidRevenue() > 0 || $this->tenantPaymentsToday() > 0) {
            return true;
        }

        return false;
    }

    protected function tenantIntegrationDone($label, array $tenant)
    {
        foreach ($this->tenantIntegrationHealth($tenant) as $row) {
            if (($row['label'] ?? '') === $label) {
                return ($row['level'] ?? '') === 'success';
            }
        }
        return false;
    }

    protected function tenantPaymentsToday()
    {
        if (!$this->tenantTableExists('invoicepaymentrecords')) {
            return 0.0;
        }
        $this->db->select('COALESCE(SUM(amount),0) as total', false);
        $this->db->where('date', date('Y-m-d'));
        $row = $this->db->get(db_prefix() . 'invoicepaymentrecords')->row_array();
        return (float) ($row['total'] ?? 0);
    }
    protected function tenantMonthlyPaidRevenue()
    {
        if (!$this->tenantTableExists('invoicepaymentrecords')) {
            return 0.0;
        }
        $start = date('Y-m-01');
        $end = date('Y-m-t');
        $this->db->select('COALESCE(SUM(amount),0) as total', false);
        $this->db->where('date >=', $start);
        $this->db->where('date <=', $end);
        $row = $this->db->get(db_prefix() . 'invoicepaymentrecords')->row_array();
        return (float) ($row['total'] ?? 0);
    }

    protected function tenantMonthlyExpenses()
    {
        if (!$this->tenantTableExists('expenses')) {
            return 0.0;
        }
        $this->db->select('COALESCE(SUM(amount),0) as total', false);
        $this->db->where('date >=', date('Y-m-01'));
        $this->db->where('date <=', date('Y-m-t'));
        $row = $this->db->get(db_prefix() . 'expenses')->row_array();
        return (float) ($row['total'] ?? 0);
    }

    protected function tenantInvoiceSum($whereSql)
    {
        if (!$this->tenantTableExists('invoices')) {
            return 0.0;
        }
        $this->db->select('COALESCE(SUM(total),0) as total', false);
        $this->db->where($whereSql, null, false);
        $row = $this->db->get(db_prefix() . 'invoices')->row_array();
        return (float) ($row['total'] ?? 0);
    }

    protected function tenantOpenInvoicesCount()
    {
        if (!$this->tenantTableExists('invoices')) {
            return 0;
        }
        $this->db->where('status !=', 2);
        $this->db->where('status !=', 5);
        return (int) $this->db->count_all_results(db_prefix() . 'invoices');
    }

    protected function tenantOverdueInvoicesCount()
    {
        if (!$this->tenantTableExists('invoices')) {
            return 0;
        }
        $this->db->where('status !=', 2);
        $this->db->where('status !=', 5);
        $this->db->where('duedate IS NOT NULL', null, false);
        $this->db->where('duedate <', date('Y-m-d'));
        return (int) $this->db->count_all_results(db_prefix() . 'invoices');
    }

    protected function tenantOpenLeadsCount()
    {
        if (!$this->tenantTableExists('leads')) {
            return 0;
        }
        $this->applyLeadOpenFilters();
        return (int) $this->db->count_all_results(db_prefix() . 'leads');
    }

    protected function applyLeadOpenFilters()
    {
        if ($this->db->field_exists('junk', db_prefix() . 'leads')) {
            $this->db->where('junk', 0);
        }
        if ($this->db->field_exists('lost', db_prefix() . 'leads')) {
            $this->db->where('lost', 0);
        }
    }

    protected function tenantStaleLeadsCount()
    {
        if (!$this->tenantTableExists('leads')) {
            return 0;
        }
        $this->applyLeadOpenFilters();
        $this->db->group_start();
        $this->db->where('lastcontact IS NULL', null, false);
        $this->db->or_where('lastcontact <', date('Y-m-d H:i:s', strtotime('-7 days')));
        $this->db->group_end();
        return (int) $this->db->count_all_results(db_prefix() . 'leads');
    }

    protected function tenantExpiringEstimatesCount()
    {
        if (!$this->tenantTableExists('estimates')) {
            return 0;
        }
        $this->db->where('expirydate >=', date('Y-m-d'));
        $this->db->where('expirydate <=', date('Y-m-d', strtotime('+7 days')));
        return (int) $this->db->count_all_results(db_prefix() . 'estimates');
    }

    protected function tenantOpenTicketsCount()
    {
        if (!$this->tenantTableExists('tickets')) {
            return 0;
        }
        $this->db->where_in('status', [1, 2, 4]);
        $this->db->where('merged_ticket_id IS NULL', null, false);
        return (int) $this->db->count_all_results(db_prefix() . 'tickets');
    }

    protected function tenantOverdueTasksCount()
    {
        if (!$this->tenantTableExists('tasks')) {
            return 0;
        }
        $this->db->where('status !=', 5);
        $this->db->where('duedate IS NOT NULL', null, false);
        $this->db->where('duedate <', date('Y-m-d'));
        return (int) $this->db->count_all_results(db_prefix() . 'tasks');
    }

    protected function tenantExpiringContractsCount()
    {
        if (!$this->tenantTableExists('contracts')) {
            return 0;
        }
        $this->db->where('trash', 0);
        $this->db->where('dateend >=', date('Y-m-d'));
        $this->db->where('dateend <=', date('Y-m-d', strtotime('+30 days')));
        return (int) $this->db->count_all_results(db_prefix() . 'contracts');
    }

    protected function tenantMatbaoIssueCount()
    {
        if (!$this->tenantTableExists('kt_matbao_invoice_records')) {
            return 0;
        }
        if ($this->db->field_exists('status', db_prefix() . 'kt_matbao_invoice_records')) {
            $this->db->where_in('status', ['failed', 'error', 'rejected']);
        } elseif ($this->db->field_exists('error_message', db_prefix() . 'kt_matbao_invoice_records')) {
            $this->db->where('error_message IS NOT NULL', null, false);
            $this->db->where('error_message !=', '');
        } else {
            return 0;
        }
        return (int) $this->db->count_all_results(db_prefix() . 'kt_matbao_invoice_records');
    }

    protected function tenantCount($table, array $where = [])
    {
        if (!$this->tenantTableExists($table)) {
            return 0;
        }
        foreach ($where as $key => $value) {
            if ($this->db->field_exists($key, db_prefix() . $table)) {
                $this->db->where($key, $value);
            }
        }
        return (int) $this->db->count_all_results(db_prefix() . $table);
    }

    protected function tenantTableExists($table)
    {
        return $this->db->table_exists(db_prefix() . $table);
    }
}

