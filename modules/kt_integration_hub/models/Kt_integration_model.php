<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/StaffPayloadProducer.php');
require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/SyncStaffToDmsJob.php');
require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/CustomerPayloadProducer.php');
require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/SyncCustomerToDmsJob.php');
require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/ProductPayloadProducer.php');
require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/SyncProductToDmsJob.php');
require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/WarehousePayloadProducer.php');
require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/SyncWarehouseToDmsJob.php');

class Kt_integration_model extends App_Model
{
    private $current_sync_attempt_id = 0;
    public $db;
    protected $_dms_projection_tables_ready = false;
    protected $_salesrep_db = null;

    public function __construct()
    {
        $CI = &get_instance();
        $this->db = $CI->db;
        parent::__construct();
        $this->load->helper(KT_INTEGRATION_HUB_MODULE . '/kt_integration_hub');
    }

    public function get_summary($tenantId = null)
    {
        $db = $this->landlord_db();

        return [
            'providers' => (int) $db->count_all_results(db_prefix() . 'kt_integration_providers'),
            'connections' => $this->count_rows('kt_integration_connections', $tenantId),
            'events_pending' => $this->count_rows('kt_integration_webhook_events', $tenantId, ['processing_status' => 'pending']),
            'jobs_queued' => $this->count_rows('kt_integration_sync_jobs', $tenantId, ['status' => 'queued']),
            'jobs_failed' => $this->count_rows('kt_integration_sync_jobs', $tenantId, ['status' => 'failed']),
            'logs_error' => $this->count_rows('kt_integration_logs', $tenantId, ['level' => 'error']),
        ];
    }

    public function get_dms_overview_metrics($tenantId)
    {
        $tenantId = (int) $tenantId;
        $today = date('Y-m-d');
        $routeDate = $this->latest_dms_snapshot_date('dms_route_performance_snapshots', 'snapshot_date', $tenantId) ?: $today;
        $metrics = [
            'checkins' => [
                'total' => 0,
                'valid' => 0,
                'warning' => 0,
                'invalid' => 0,
            ],
            'visits' => [
                'total' => 0,
                'completed' => 0,
                'partial' => 0,
                'cancelled' => 0,
            ],
            'route' => $this->get_dms_route_performance_summary($tenantId, [
                'date_from' => $routeDate,
                'date_to' => $routeDate,
            ]),
            'live_tracking' => [
                'online' => 0,
                'stale' => 0,
                'offline' => 0,
            ],
            'manual_reviews' => [
                'open' => 0,
                'in_progress' => 0,
                'resolved' => 0,
            ],
            'notifications' => [
                'unread' => 0,
                'critical' => 0,
                'high' => 0,
            ],
            'latest_route_snapshot_date' => $routeDate,
        ];

        if ($this->dms_table_exists('dms_checkin_events')) {
            $metrics['checkins'] = [
                'total' => $this->count_dms_records('dms_checkin_events', $tenantId, ['date_from' => $today, 'date_to' => $today], 'checkin_at'),
                'valid' => $this->count_dms_records('dms_checkin_events', $tenantId, ['status' => 'valid', 'date_from' => $today, 'date_to' => $today], 'checkin_at'),
                'warning' => $this->count_dms_records('dms_checkin_events', $tenantId, ['status' => 'warning', 'date_from' => $today, 'date_to' => $today], 'checkin_at'),
                'invalid' => $this->count_dms_records('dms_checkin_events', $tenantId, ['status' => 'invalid', 'date_from' => $today, 'date_to' => $today], 'checkin_at'),
            ];
        }

        if ($this->dms_table_exists('dms_visit_snapshots')) {
            $metrics['visits'] = [
                'total' => $this->count_dms_records('dms_visit_snapshots', $tenantId, ['date_from' => $today, 'date_to' => $today], 'ended_at'),
                'completed' => $this->count_dms_records('dms_visit_snapshots', $tenantId, ['visit_status' => 'completed', 'date_from' => $today, 'date_to' => $today], 'ended_at'),
                'partial' => $this->count_dms_records('dms_visit_snapshots', $tenantId, ['visit_status' => 'partial', 'date_from' => $today, 'date_to' => $today], 'ended_at'),
                'cancelled' => $this->count_dms_records('dms_visit_snapshots', $tenantId, ['visit_status' => 'cancelled', 'date_from' => $today, 'date_to' => $today], 'ended_at'),
            ];
        }

        if ($this->dms_table_exists('dms_live_tracking_snapshots')) {
            $metrics['live_tracking'] = [
                'online' => $this->count_dms_records('dms_live_tracking_snapshots', $tenantId, ['tracking_status' => 'online']),
                'stale' => $this->count_dms_records('dms_live_tracking_snapshots', $tenantId, ['tracking_status' => 'stale']),
                'offline' => $this->count_dms_records('dms_live_tracking_snapshots', $tenantId, ['tracking_status' => 'offline']),
            ];
        }

        if ($this->dms_table_exists('kt_manual_reviews')) {
            $metrics['manual_reviews'] = [
                'open' => $this->count_dms_records('kt_manual_reviews', $tenantId, ['review_status' => 'OPEN']),
                'in_progress' => $this->count_dms_records('kt_manual_reviews', $tenantId, ['review_status' => 'IN_PROGRESS']),
                'resolved' => $this->count_dms_records('kt_manual_reviews', $tenantId, ['review_status' => 'RESOLVED']),
            ];
        }

        if ($this->dms_table_exists('kt_notification_events')) {
            $metrics['notifications'] = [
                'unread' => $this->count_dms_records('kt_notification_events', $tenantId, ['status' => 'NEW']),
                'critical' => $this->count_dms_records('kt_notification_events', $tenantId, ['priority' => 'CRITICAL']),
                'high' => $this->count_dms_records('kt_notification_events', $tenantId, ['priority' => 'HIGH']),
            ];
        }

        return $metrics;
    }

    public function get_dms_business_dashboard($tenantId)
    {
        $tenantId = (int) $tenantId;
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $checkinToday = $this->get_dms_checkin_summary($tenantId, ['date_from' => $today, 'date_to' => $today]);
        $visitToday = $this->get_dms_visit_summary($tenantId, ['date_from' => $today, 'date_to' => $today]);
        $checkinMonth = $this->get_dms_checkin_summary($tenantId, ['date_from' => $monthStart, 'date_to' => $today]);
        $visitMonth = $this->get_dms_visit_summary($tenantId, ['date_from' => $monthStart, 'date_to' => $today]);
        $ordersToday = $this->get_dms_orders_invoice_summary_for_period($tenantId, $today, $today);
        $ordersMonth = $this->get_dms_orders_invoice_summary_for_period($tenantId, $monthStart, $today);
        $tracking = $this->get_dms_live_tracking_summary($tenantId);
        $console = $this->get_dms_console_status($tenantId, $this->get_dms_tenant_settings($tenantId));
        $inventory = $this->get_dms_inventory_insight($tenantId);

        return [
            'today' => [
                'orders' => (int) ($ordersToday['total'] ?? 0),
                'sales_amount' => $ordersToday['sales_amount'] ?? null,
                'revenue' => $ordersToday['invoiced_amount'] ?? null,
                'payment' => $ordersToday['paid_amount'] ?? null,
                'customers_or_stores' => (int) (($checkinToday['total'] ?? 0) + ($visitToday['total'] ?? 0)),
                'new_customers_or_stores' => null,
            ],
            'field' => [
                'online_sales' => (int) ($tracking['online'] ?? 0),
                'offline_sales' => (int) ($tracking['offline'] ?? 0),
                'stale_sales' => (int) ($tracking['stale'] ?? 0),
                'checkins' => (int) ($checkinToday['total'] ?? 0),
                'visits_completed' => (int) ($visitToday['completed'] ?? 0),
                'missed_visits' => (int) ($visitToday['cancelled'] ?? 0),
                'needs_attention' => (int) (($checkinToday['warning'] ?? 0) + ($checkinToday['invalid'] ?? 0)),
            ],
            'attention' => [
                'pending_orders' => (int) ($ordersToday['pending'] ?? 0),
                'failed_orders' => (int) ($ordersToday['failed'] ?? 0),
                'abnormal_checkins' => (int) (($checkinToday['warning'] ?? 0) + ($checkinToday['invalid'] ?? 0)),
                'unfinished_visits' => (int) ($visitToday['partial'] ?? 0),
                'failed_sync_areas' => $this->count_failed_dms_health_areas($console),
                'low_stock' => $inventory['low_stock_count'] ?? null,
            ],
            'month' => [
                'orders' => (int) ($ordersMonth['total'] ?? 0),
                'sales_amount' => $ordersMonth['sales_amount'] ?? null,
                'revenue' => $ordersMonth['invoiced_amount'] ?? null,
                'payment' => $ordersMonth['paid_amount'] ?? null,
                'new_customers_or_stores' => null,
                'store_coverage' => (int) ($checkinMonth['total'] ?? 0) + (int) ($visitMonth['total'] ?? 0),
            ],
            'sources' => [
                'orders' => 'dms_order_bridge',
                'checkins' => 'dms_checkin_events',
                'visits' => 'dms_visit_snapshots',
                'tracking' => 'dms_live_tracking_snapshots',
            'amounts' => 'DMS order values use tenant-scoped DMS orders; payment totals prefer CRM invoice/payment records and fall back to DMS readback only when CRM invoice data is unavailable.',
            ],
        ];
    }

    public function get_dms_sales_team_today($tenantId)
    {
        $tenantId = (int) $tenantId;
        $projectedRows = $this->get_dms_projected_sales_team_today($tenantId);
        if (!empty($projectedRows)) {
            return $projectedRows;
        }

        $staffRows = $this->get_dms_staff_access_rows($tenantId);
        $today = date('Y-m-d');
        $latestCheckins = $this->latest_dms_staff_activity_rows('dms_checkin_events', $tenantId, 'crm_staff_id', 'checkin_at', ['date_from' => $today, 'date_to' => $today]);
        $latestVisits = $this->latest_dms_staff_activity_rows('dms_visit_snapshots', $tenantId, 'crm_staff_id', 'ended_at', ['date_from' => $today, 'date_to' => $today]);
        $tracking = $this->latest_dms_staff_activity_rows('dms_live_tracking_snapshots', $tenantId, 'crm_staff_id', 'last_seen_at', []);

        $rows = [];
        foreach ($staffRows as $staff) {
            $staffId = (int) ($staff['staffid'] ?? $staff['crm_staff_id'] ?? 0);
            $track = (array) ($tracking[$staffId] ?? []);
            $checkin = (array) ($latestCheckins[$staffId] ?? []);
            $visit = (array) ($latestVisits[$staffId] ?? []);
            $rows[] = [
                'staff_id' => $staffId,
                'staff_name' => trim((string) (($staff['firstname'] ?? '') . ' ' . ($staff['lastname'] ?? ''))) ?: (string) ($staff['email'] ?? ('Staff #' . $staffId)),
                'role' => (string) ($staff['dms_role'] ?? $staff['dms_role_code'] ?? ''),
                'manager_crm_staff_id' => (int) ($staff['manager_crm_staff_id'] ?? 0),
                'access_enabled' => !empty($staff['dms_access_enabled']),
                'sync_status' => (string) ($staff['sync_status'] ?? ''),
                'tracking_status' => (string) ($track['tracking_status'] ?? 'offline'),
                'last_seen_at' => (string) ($track['last_seen_at'] ?? ''),
                'last_checkin_at' => (string) ($checkin['checkin_at'] ?? ''),
                'last_visit_at' => (string) ($visit['ended_at'] ?? ''),
                'today_checkins' => (int) ($checkin['activity_count'] ?? 0),
                'today_visits' => (int) ($visit['activity_count'] ?? 0),
                'latitude' => $track['latitude'] ?? ($checkin['latitude'] ?? null),
                'longitude' => $track['longitude'] ?? ($checkin['longitude'] ?? null),
            ];
        }

        return $rows;
    }

    public function get_dms_map_activity($tenantId)
    {
        $tenantId = (int) $tenantId;
        $today = date('Y-m-d');
        $staffLocations = $this->get_dms_live_tracking($tenantId, [], 100, 0)['rows'];
        $checkinLocations = $this->get_dms_checkins($tenantId, ['date_from' => $today, 'date_to' => $today], 100, 0)['rows'];
        $storeLocations = $this->get_dms_store_locations($tenantId, 100);
        $orderLocations = $this->get_dms_projected_order_store_locations($tenantId, $today, 100);
        $capturedOrderLocations = $this->get_dms_projected_locations($tenantId, ['captured_order'], $today, 100);
        $checkoutLocations = $this->get_dms_projected_locations($tenantId, ['checkout'], $today, 100);
        $visitLocations = $this->get_dms_projected_locations($tenantId, ['visit'], $today, 100);
        $feedbackLocations = $this->get_dms_field_feedback_locations($tenantId, $today, 100);
        if (empty($orderLocations)) {
            $orderLocations = $this->get_dms_projected_order_store_locations($tenantId, null, 100);
        }
        if (empty($capturedOrderLocations)) {
            $capturedOrderLocations = $this->get_dms_projected_locations($tenantId, ['captured_order'], null, 100);
        }
        if (empty($feedbackLocations)) {
            $feedbackLocations = $this->get_dms_field_feedback_locations($tenantId, null, 100);
        }
        if (empty($orderLocations)) {
            $orderLocations = $this->get_dms_order_store_locations($tenantId, $today, 100);
        }
        $markers = [];
        foreach ($staffLocations as $row) {
            $lat = $row['latitude'] ?? null;
            $lng = $row['longitude'] ?? null;
            if (is_numeric($lat) && is_numeric($lng)) {
                $markers[] = [
                    'type' => 'staff',
                    'label' => (string) ($row['staff_name'] ?? ('Staff #' . ($row['crm_staff_id'] ?? ''))),
                    'status' => (string) ($row['tracking_status'] ?? ''),
                    'lat' => (float) $lat,
                    'lng' => (float) $lng,
                    'time' => (string) ($row['last_seen_at'] ?? ''),
                ];
            }
        }
        foreach ($checkinLocations as $row) {
            $lat = $row['latitude'] ?? null;
            $lng = $row['longitude'] ?? null;
            if (is_numeric($lat) && is_numeric($lng)) {
                $markers[] = [
                    'type' => 'checkin',
                    'label' => (string) ($row['customer_name'] ?? ('Store #' . ($row['store_id'] ?? ''))),
                    'status' => (string) ($row['status'] ?? ''),
                    'lat' => (float) $lat,
                    'lng' => (float) $lng,
                    'time' => (string) ($row['checkin_at'] ?? ''),
                ];
            }
        }
        foreach ($storeLocations as $row) {
            $markers[] = [
                'type' => 'store',
                'label' => (string) ($row['name'] ?? ('Store #' . ($row['id'] ?? ''))),
                'status' => (string) ($row['status'] ?? ''),
                'lat' => (float) $row['latitude'],
                'lng' => (float) $row['longitude'],
                'time' => (string) ($row['last_synced_at'] ?? ''),
            ];
        }
        foreach ($orderLocations as $row) {
            $markers[] = [
                'type' => 'order_store',
                'label' => (string) ($row['order_number'] ?? ('Order #' . ($row['order_id'] ?? ''))),
                'status' => 'Vị trí điểm bán của đơn hàng',
                'lat' => (float) $row['store_latitude'],
                'lng' => (float) $row['store_longitude'],
                'time' => (string) ($row['order_date'] ?? ''),
            ];
        }
        foreach ($capturedOrderLocations as $row) {
            $markers[] = [
                'type' => 'captured_order',
                'label' => (string) ($row['label'] ?? ('Order #' . ($row['order_id'] ?? $row['source_record_id'] ?? ''))),
                'status' => 'Vị trí gửi đơn hàng',
                'lat' => (float) $row['latitude'],
                'lng' => (float) $row['longitude'],
                'time' => (string) ($row['recorded_at'] ?? ''),
            ];
        }
        foreach ($checkoutLocations as $row) {
            $markers[] = [
                'type' => 'checkout',
                'label' => (string) ($row['label'] ?? 'Check-out'),
                'status' => (string) ($row['status'] ?? 'Check-out'),
                'lat' => (float) $row['latitude'],
                'lng' => (float) $row['longitude'],
                'time' => (string) ($row['recorded_at'] ?? ''),
            ];
        }
        foreach ($visitLocations as $row) {
            $markers[] = [
                'type' => 'visit',
                'label' => (string) ($row['label'] ?? 'Visit'),
                'status' => (string) ($row['status'] ?? 'Visit'),
                'lat' => (float) $row['latitude'],
                'lng' => (float) $row['longitude'],
                'time' => (string) ($row['recorded_at'] ?? ''),
            ];
        }
        foreach ($feedbackLocations as $row) {
            $feedbackType = function_exists('kt_dms_ui_feedback_type_label')
                ? kt_dms_ui_feedback_type_label($row['feedback_type'] ?? '')
                : 'Phản ánh thị trường';
            $severity = function_exists('kt_dms_ui_status_label')
                ? kt_dms_ui_status_label('severity', $row['severity'] ?? '')
                : 'Chưa xác định';
            $markers[] = [
                'type' => 'field_feedback',
                'label' => (string) ($row['title'] ?? 'Phản ánh thị trường'),
                'status' => $feedbackType . ' · ' . $severity,
                'lat' => (float) $row['latitude'],
                'lng' => (float) $row['longitude'],
                'time' => (string) ($row['submitted_at'] ?? ''),
            ];
        }

        return [
            'staff_locations' => $staffLocations,
            'checkin_locations' => $checkinLocations,
            'store_locations' => $storeLocations,
            'order_locations' => $orderLocations,
            'captured_order_locations' => $capturedOrderLocations,
            'checkout_locations' => $checkoutLocations,
            'visit_locations' => $visitLocations,
            'feedback_locations' => $feedbackLocations,
            'markers' => $markers,
            'notes' => [
                'order_locations' => 'Marker đơn tại điểm bán dùng tọa độ điểm bán; marker vị trí gửi đơn chỉ xuất hiện khi DMS gửi captured order GPS.',
            ],
        ];
    }

    public function get_dms_store_product_operations($tenantId)
    {
        $tenantId = (int) $tenantId;
        if (!isset($this->Kt_saas_model)) {
            $this->load->model('kt_saas/Kt_saas_model');
        }
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $customerSync = $this->get_dms_sync_job_status_snapshot($tenantId, 'sync_customer_to_dms');
        $productSync = $this->get_dms_sync_job_status_snapshot($tenantId, 'sync_product_to_dms');
        $usage = $this->Kt_saas_model->get_tenant_dms_usage_summary($tenantId);
        $inventory = $this->get_dms_projected_inventory_insight($tenantId);
        if (($inventory['projection_rows'] ?? 0) <= 0) {
            $inventory = $this->get_dms_inventory_insight($tenantId);
        }
        $topProducts = $this->get_dms_projected_top_ordered_products($tenantId, 5);
        if (empty($topProducts)) {
            $topProducts = $this->get_dms_top_ordered_products($tenantId, $monthStart, $today, 5);
        }
        $coverage = $this->get_dms_projected_store_coverage_summary($tenantId);

        return [
            'stores_visited_today' => ($coverage['projection_rows'] ?? 0) > 0
                ? (int) ($coverage['stores_visited_today'] ?? 0)
                : ($this->dms_table_exists('dms_checkin_events')
                ? $this->count_dms_distinct($tenantId, 'dms_checkin_events', 'store_id', ['date_from' => $today, 'date_to' => $today], 'checkin_at')
                : 0),
            'stores_visited_month' => ($coverage['projection_rows'] ?? 0) > 0
                ? (int) ($coverage['stores_visited_month'] ?? 0)
                : ($this->dms_table_exists('dms_checkin_events')
                ? $this->count_dms_distinct($tenantId, 'dms_checkin_events', 'store_id', ['date_from' => $monthStart, 'date_to' => $today], 'checkin_at')
                : 0),
            'new_stores' => null,
            'products_synced' => (int) ($productSync['successful_count'] ?? 0),
            'stores_synced' => (int) ($customerSync['successful_count'] ?? 0),
            'low_stock' => $inventory['low_stock_count'],
            'low_stock_rows' => $inventory['low_stock_rows'],
            'inventory_movement' => $inventory['movement_count'],
            'inventory_movement_rows' => $inventory['movement_rows'],
            'top_ordered_products' => $topProducts,
            'usage' => (array) ($usage['rows'] ?? []),
            'customer_sync' => $customerSync,
            'product_sync' => $productSync,
            'projection_source' => [
                'inventory_rows' => (int) ($inventory['projection_rows'] ?? 0),
                'product_sales_rows' => count($topProducts),
                'store_coverage_rows' => (int) ($coverage['projection_rows'] ?? 0),
            ],
        ];
    }

    public function get_dms_orders_invoice_summary_for_period($tenantId, $dateFrom, $dateTo)
    {
        $summary = $this->get_dms_projected_order_summary($tenantId, $dateFrom, $dateTo);
        if (($summary['projection_rows'] ?? 0) > 0) {
            return $summary;
        }

        $summary = $this->get_dms_orders_invoice_summary($tenantId);
        if (!$this->dms_table_exists('dms_order_bridge')) {
            return $summary;
        }

        $rows = $this->landlord_db()
            ->select('dms_order_id, bridge_status, crm_invoice_id, invoice_status_snapshot')
            ->where('tenant_id', (int) $tenantId)
            ->where('provider_code', 'salesflow_dms')
            ->where('updated_at >=', trim((string) $dateFrom) . ' 00:00:00')
            ->where('updated_at <=', trim((string) $dateTo) . ' 23:59:59')
            ->get(db_prefix() . 'dms_order_bridge')
            ->result_array();

        $summary = [
            'total' => 0,
            'with_invoice' => 0,
            'failed' => 0,
            'cancelled' => 0,
            'pending' => 0,
            'sales_amount' => null,
            'invoiced_amount' => null,
            'paid_amount' => null,
            'balance_amount' => null,
            'payment_source' => 'none',
        ];
        $orderIds = [];
        foreach ($rows as $row) {
            $summary['total']++;
            $orderIds[] = (int) ($row['dms_order_id'] ?? 0);
            if ((int) ($row['crm_invoice_id'] ?? 0) > 0) {
                $summary['with_invoice']++;
            }
            $status = strtolower(trim((string) ($row['bridge_status'] ?? '')));
            $invoiceStatus = strtolower(trim((string) ($row['invoice_status_snapshot'] ?? '')));
            if ($status === 'failed') {
                $summary['failed']++;
            }
            if ($status === 'pending') {
                $summary['pending']++;
            }
            if ($status === 'cancelled' || $invoiceStatus === 'cancelled') {
                $summary['cancelled']++;
            }
        }
        if (!empty(array_filter($orderIds))) {
            $amounts = $this->get_salesrep_order_amount_summary($tenantId, $orderIds);
            foreach (['sales_amount', 'invoiced_amount', 'paid_amount', 'balance_amount'] as $key) {
                $summary[$key] = $amounts[$key];
            }
            $summary['payment_source'] = $amounts['source'];
        }

        return $summary;
    }

    public function get_dms_checkins($tenantId, array $filters = [], $limit = 25, $offset = 0)
    {
        $tenantId = (int) $tenantId;
        $limit = max(1, (int) $limit);
        $offset = max(0, (int) $offset);

        if (!$this->dms_table_exists('dms_checkin_events')) {
            return ['rows' => [], 'total' => 0];
        }

        $total = $this->build_dms_query('dms_checkin_events', $tenantId, $filters, [
            'status' => 'status',
            'crm_staff_id' => 'crm_staff_id',
            'crm_customer_id' => 'crm_customer_id',
            'store_id' => 'store_id',
        ], 'checkin_at')->count_all_results();

        $rows = $this->build_dms_query('dms_checkin_events', $tenantId, $filters, [
            'status' => 'status',
            'crm_staff_id' => 'crm_staff_id',
            'crm_customer_id' => 'crm_customer_id',
            'store_id' => 'store_id',
        ], 'checkin_at')
            ->select('d.id, d.tenant_id, d.dms_checkin_id, d.crm_staff_id, d.crm_customer_id, d.store_id, d.route_id, d.checkin_at, d.latitude, d.longitude, d.accuracy_meter, d.distance_from_store_meter, d.photo_url, d.status, d.notes, d.created_at, CONCAT(COALESCE(s.firstname, \'\'), \' \', COALESCE(s.lastname, \'\')) as staff_name, c.company as customer_name', false)
            ->join(db_prefix() . 'staff s', 's.staffid = d.crm_staff_id', 'left')
            ->join(db_prefix() . 'clients c', 'c.userid = d.crm_customer_id', 'left')
            ->order_by('checkin_at', 'desc')
            ->order_by('id', 'desc')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
        $rows = $this->attach_dms_bridge_job_status($tenantId, 'sync_checkin_event', 'dms_checkin_id', $rows, 'checkin');

        return ['rows' => $rows, 'total' => (int) $total];
    }

    public function get_dms_checkin_summary($tenantId, array $filters = [])
    {
        if (!$this->dms_table_exists('dms_checkin_events')) {
            return [
                'total' => 0,
                'valid' => 0,
                'warning' => 0,
                'invalid' => 0,
                'active_staff_count' => 0,
                'latest_checkin_at' => '',
            ];
        }

        $row = $this->build_dms_query('dms_checkin_events', (int) $tenantId, $filters, [
            'status' => 'status',
            'crm_staff_id' => 'crm_staff_id',
            'crm_customer_id' => 'crm_customer_id',
            'store_id' => 'store_id',
        ], 'checkin_at')
            ->select("COUNT(*) as total, SUM(CASE WHEN status = 'valid' THEN 1 ELSE 0 END) as valid, SUM(CASE WHEN status = 'warning' THEN 1 ELSE 0 END) as warning, SUM(CASE WHEN status = 'invalid' THEN 1 ELSE 0 END) as invalid, COUNT(DISTINCT crm_staff_id) as active_staff_count, MAX(checkin_at) as latest_checkin_at", false)
            ->get()
            ->row_array();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'valid' => (int) ($row['valid'] ?? 0),
            'warning' => (int) ($row['warning'] ?? 0),
            'invalid' => (int) ($row['invalid'] ?? 0),
            'active_staff_count' => (int) ($row['active_staff_count'] ?? 0),
            'latest_checkin_at' => trim((string) ($row['latest_checkin_at'] ?? '')),
        ];
    }

    public function get_dms_visits($tenantId, array $filters = [], $limit = 25, $offset = 0)
    {
        $tenantId = (int) $tenantId;
        $limit = max(1, (int) $limit);
        $offset = max(0, (int) $offset);

        if (!$this->dms_table_exists('dms_visit_snapshots')) {
            return ['rows' => [], 'total' => 0];
        }

        $total = $this->build_dms_query('dms_visit_snapshots', $tenantId, $filters, [
            'visit_status' => 'visit_status',
            'crm_staff_id' => 'crm_staff_id',
            'crm_customer_id' => 'crm_customer_id',
            'store_id' => 'store_id',
        ], 'ended_at')->count_all_results();

        $rows = $this->build_dms_query('dms_visit_snapshots', $tenantId, $filters, [
            'visit_status' => 'visit_status',
            'crm_staff_id' => 'crm_staff_id',
            'crm_customer_id' => 'crm_customer_id',
            'store_id' => 'store_id',
        ], 'ended_at')
            ->select('d.id, d.tenant_id, d.visit_id, d.crm_staff_id, d.crm_customer_id, d.store_id, d.route_id, d.started_at, d.ended_at, d.duration_minutes, d.visit_status, d.notes, d.created_at, CONCAT(COALESCE(s.firstname, \'\'), \' \', COALESCE(s.lastname, \'\')) as staff_name, c.company as customer_name', false)
            ->join(db_prefix() . 'staff s', 's.staffid = d.crm_staff_id', 'left')
            ->join(db_prefix() . 'clients c', 'c.userid = d.crm_customer_id', 'left')
            ->order_by('ended_at', 'desc')
            ->order_by('id', 'desc')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
        $rows = $this->attach_dms_bridge_job_status($tenantId, 'sync_visit_snapshot', 'visit_id', $rows, 'visit');

        return ['rows' => $rows, 'total' => (int) $total];
    }

    public function retry_dms_bridge_record($tenantId, $type, $jobId)
    {
        $tenantId = (int) $tenantId;
        $jobId = (int) $jobId;
        $type = strtolower(trim((string) $type));
        $jobType = $type === 'checkin' ? 'sync_checkin_event' : ($type === 'visit' ? 'sync_visit_snapshot' : '');
        if ($tenantId <= 0 || $jobId <= 0 || $jobType === '') {
            return ['success' => false, 'message' => 'Invalid bridge retry request.'];
        }

        $job = $this->landlord_db()
            ->where('id', $jobId)
            ->where('tenant_id', $tenantId)
            ->where('provider_code', 'salesflow_dms')
            ->where('job_type', $jobType)
            ->get(db_prefix() . 'kt_integration_sync_jobs')
            ->row_array();
        if (!$job) {
            return ['success' => false, 'message' => 'Bridge record was not found for this tenant.'];
        }

        $tenant = $this->get_tenant($tenantId);
        $connection = $this->get_connection((int) ($job['connection_id'] ?? 0));
        $payload = kt_integration_hub_json_decode((string) ($job['payload_json'] ?? ''), []);
        if (!$tenant || !$connection || !$payload) {
            return ['success' => false, 'message' => 'Bridge retry payload or connection is unavailable.'];
        }

        try {
            $context = [
                'request_id' => 'retry_' . $type . '_' . $jobId . '_' . time(),
                'idempotency_key' => (string) ($job['idempotency_key'] ?? ''),
            ];
            if ($type === 'checkin') {
                require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/DmsCheckinBridgeProcessor.php');
                $result = (new DmsCheckinBridgeProcessor())->handle($tenant, $connection, $payload, $context);
            } else {
                require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/DmsVisitBridgeProcessor.php');
                $result = (new DmsVisitBridgeProcessor())->handle($tenant, $connection, $payload, $context);
            }

            return ['success' => true, 'result' => $result];
        } catch (Throwable $exception) {
            return ['success' => false, 'message' => $exception->getMessage()];
        }
    }

    private function attach_dms_bridge_job_status($tenantId, $jobType, $externalKey, array $rows, $entityKey)
    {
        $jobs = $this->landlord_db()
            ->select('id, external_id, status, attempts, error_message, payload_json, updated_at')
            ->where('tenant_id', (int) $tenantId)
            ->where('provider_code', 'salesflow_dms')
            ->where('job_type', (string) $jobType)
            ->order_by('id', 'desc')
            ->get(db_prefix() . 'kt_integration_sync_jobs')
            ->result_array();

        $latest = [];
        foreach ($jobs as $job) {
            $externalId = (int) ($job['external_id'] ?? 0);
            if ($externalId > 0 && !isset($latest[$externalId])) {
                $latest[$externalId] = $job;
            }
        }

        $represented = [];
        foreach ($rows as &$row) {
            $externalId = (int) ($row[$externalKey] ?? 0);
            $represented[$externalId] = true;
            $job = (array) ($latest[$externalId] ?? []);
            $row['sync_status'] = (string) ($job['status'] ?? 'synced');
            $row['sync_error'] = (string) ($job['error_message'] ?? '');
            $row['sync_job_id'] = (int) ($job['id'] ?? 0);
            $row['last_sync_at'] = (string) ($job['updated_at'] ?? $row['created_at'] ?? '');
            $row['sync_entity_type'] = (string) $entityKey;
        }
        unset($row);

        foreach ($latest as $externalId => $job) {
            if (isset($represented[$externalId]) || (string) ($job['status'] ?? '') !== 'failed') {
                continue;
            }
            $payload = kt_integration_hub_json_decode((string) ($job['payload_json'] ?? ''), []);
            $entity = (array) ($payload[$entityKey] ?? []);
            $rows[] = [
                $externalKey => $externalId,
                'crm_staff_id' => (int) ($entity['crm_staff_id'] ?? 0),
                'crm_customer_id' => (int) ($entity['crm_customer_id'] ?? 0),
                'store_id' => (int) ($entity['store_id'] ?? 0),
                'status' => (string) ($entity['status'] ?? ''),
                'visit_status' => (string) ($entity['completion_status'] ?? ''),
                'checkin_at' => (string) ($entity['checkin_at'] ?? ''),
                'started_at' => (string) ($entity['started_at'] ?? $entity['checkin_at'] ?? ''),
                'ended_at' => (string) ($entity['ended_at'] ?? $entity['updated_at'] ?? ''),
                'sync_status' => 'failed',
                'sync_error' => (string) ($job['error_message'] ?? ''),
                'sync_job_id' => (int) ($job['id'] ?? 0),
                'last_sync_at' => (string) ($job['updated_at'] ?? ''),
                'sync_entity_type' => (string) $entityKey,
                'staff_name' => '',
                'customer_name' => '',
            ];
        }

        return $rows;
    }

    public function get_dms_visit_summary($tenantId, array $filters = [])
    {
        if (!$this->dms_table_exists('dms_visit_snapshots')) {
            return [
                'total' => 0,
                'completed' => 0,
                'partial' => 0,
                'cancelled' => 0,
                'average_duration' => null,
                'staff_with_visits' => 0,
                'latest_visit_at' => '',
            ];
        }

        $row = $this->build_dms_query('dms_visit_snapshots', (int) $tenantId, $filters, [
            'visit_status' => 'visit_status',
            'crm_staff_id' => 'crm_staff_id',
            'crm_customer_id' => 'crm_customer_id',
            'store_id' => 'store_id',
        ], 'ended_at')
            ->select("COUNT(*) as total, SUM(CASE WHEN visit_status = 'completed' THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN visit_status = 'partial' THEN 1 ELSE 0 END) as partial, SUM(CASE WHEN visit_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled, AVG(duration_minutes) as average_duration, COUNT(DISTINCT crm_staff_id) as staff_with_visits, MAX(ended_at) as latest_visit_at", false)
            ->get()
            ->row_array();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'partial' => (int) ($row['partial'] ?? 0),
            'cancelled' => (int) ($row['cancelled'] ?? 0),
            'average_duration' => isset($row['average_duration']) ? (float) $row['average_duration'] : null,
            'staff_with_visits' => (int) ($row['staff_with_visits'] ?? 0),
            'latest_visit_at' => trim((string) ($row['latest_visit_at'] ?? '')),
        ];
    }

    public function get_dms_route_performance_summary($tenantId, array $filters = [])
    {
        $tenantId = (int) $tenantId;
        if (!$this->dms_table_exists('dms_route_performance_snapshots')) {
            return [
                'rows' => 0,
                'total_checkins' => 0,
                'total_visits' => 0,
                'completed_visits' => 0,
                'partial_visits' => 0,
                'cancelled_visits' => 0,
                'staff_activity' => 0,
                'customer_coverage' => 0,
                'store_coverage' => 0,
                'customer_scope_total' => null,
                'customers_not_visited' => null,
                'store_scope_total' => null,
                'stores_not_visited' => null,
            ];
        }
        $row = $this->build_dms_query('dms_route_performance_snapshots', $tenantId, $filters, [
            'crm_staff_id' => 'crm_staff_id',
            'crm_customer_id' => 'crm_customer_id',
            'store_id' => 'store_id',
        ], 'snapshot_date')
            ->select('COUNT(*) as rows_count, COALESCE(SUM(total_checkins), 0) as total_checkins, COALESCE(SUM(total_visits), 0) as total_visits, COALESCE(SUM(completed_visits), 0) as completed_visits, COALESCE(SUM(partial_visits), 0) as partial_visits, COALESCE(SUM(cancelled_visits), 0) as cancelled_visits, COUNT(DISTINCT crm_staff_id) as staff_activity, COUNT(DISTINCT crm_customer_id) as customer_coverage, COUNT(DISTINCT store_id) as store_coverage')
            ->get()
            ->row_array();

        $customerScopeTotal = $this->count_tenant_active_customers($tenantId);
        $customerCoverage = (int) ($row['customer_coverage'] ?? 0);
        $customersNotVisited = $customerScopeTotal !== null
            ? max(0, $customerScopeTotal - $customerCoverage)
            : null;

        return [
            'rows' => (int) ($row['rows_count'] ?? 0),
            'total_checkins' => (int) ($row['total_checkins'] ?? 0),
            'total_visits' => (int) ($row['total_visits'] ?? 0),
            'completed_visits' => (int) ($row['completed_visits'] ?? 0),
            'partial_visits' => (int) ($row['partial_visits'] ?? 0),
            'cancelled_visits' => (int) ($row['cancelled_visits'] ?? 0),
            'staff_activity' => (int) ($row['staff_activity'] ?? 0),
            'customer_coverage' => $customerCoverage,
            'store_coverage' => (int) ($row['store_coverage'] ?? 0),
            'customer_scope_total' => $customerScopeTotal,
            'customers_not_visited' => $customersNotVisited,
            'store_scope_total' => null,
            'stores_not_visited' => null,
        ];
    }

    public function get_dms_route_performance_rows($tenantId, array $filters = [], $limit = 25, $offset = 0)
    {
        $tenantId = (int) $tenantId;
        $limit = max(1, (int) $limit);
        $offset = max(0, (int) $offset);

        if (!$this->dms_table_exists('dms_route_performance_snapshots')) {
            return ['rows' => [], 'total' => 0];
        }

        $total = $this->build_dms_query('dms_route_performance_snapshots', $tenantId, $filters, [
            'crm_staff_id' => 'crm_staff_id',
            'crm_customer_id' => 'crm_customer_id',
            'store_id' => 'store_id',
        ], 'snapshot_date')->count_all_results();

        $rows = $this->build_dms_query('dms_route_performance_snapshots', $tenantId, $filters, [
            'crm_staff_id' => 'crm_staff_id',
            'crm_customer_id' => 'crm_customer_id',
            'store_id' => 'store_id',
        ], 'snapshot_date')
            ->select('d.id, d.tenant_id, d.snapshot_date, d.crm_staff_id, d.crm_customer_id, d.store_id, d.total_checkins, d.total_visits, d.completed_visits, d.partial_visits, d.cancelled_visits, d.created_at, d.updated_at, CONCAT(COALESCE(s.firstname, \'\'), \' \', COALESCE(s.lastname, \'\')) as staff_name, c.company as customer_name', false)
            ->join(db_prefix() . 'staff s', 's.staffid = d.crm_staff_id', 'left')
            ->join(db_prefix() . 'clients c', 'c.userid = d.crm_customer_id', 'left')
            ->order_by('snapshot_date', 'desc')
            ->order_by('id', 'desc')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        return ['rows' => $rows, 'total' => (int) $total];
    }

    public function get_dms_live_tracking($tenantId, array $filters = [], $limit = 25, $offset = 0)
    {
        $tenantId = (int) $tenantId;
        $limit = max(1, (int) $limit);
        $offset = max(0, (int) $offset);

        if (!$this->dms_table_exists('dms_live_tracking_snapshots')) {
            return ['rows' => [], 'total' => 0];
        }

        $total = $this->build_dms_query('dms_live_tracking_snapshots', $tenantId, $filters, [
            'tracking_status' => 'tracking_status',
            'crm_staff_id' => 'crm_staff_id',
        ], 'last_seen_at')->count_all_results();

        $rows = $this->build_dms_query('dms_live_tracking_snapshots', $tenantId, $filters, [
            'tracking_status' => 'tracking_status',
            'crm_staff_id' => 'crm_staff_id',
        ], 'last_seen_at')
            ->select('d.id, d.tenant_id, d.dms_user_id, d.crm_staff_id, d.latitude, d.longitude, d.last_seen_at, d.tracking_status, d.payload_json, d.created_at, d.updated_at, CONCAT(COALESCE(s.firstname, \'\'), \' \', COALESCE(s.lastname, \'\')) as staff_name', false)
            ->join(db_prefix() . 'staff s', 's.staffid = d.crm_staff_id', 'left')
            ->order_by('last_seen_at', 'desc')
            ->order_by('id', 'desc')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        return ['rows' => $rows, 'total' => (int) $total];
    }

    public function get_dms_live_tracking_summary($tenantId, array $filters = [])
    {
        if (!$this->dms_table_exists('dms_live_tracking_snapshots')) {
            return [
                'total' => 0,
                'online' => 0,
                'stale' => 0,
                'offline' => 0,
                'latest_seen_at' => '',
            ];
        }

        $row = $this->build_dms_query('dms_live_tracking_snapshots', (int) $tenantId, $filters, [
            'tracking_status' => 'tracking_status',
            'crm_staff_id' => 'crm_staff_id',
        ], 'last_seen_at')
            ->select("COUNT(*) as total, SUM(CASE WHEN tracking_status = 'online' THEN 1 ELSE 0 END) as online, SUM(CASE WHEN tracking_status = 'stale' THEN 1 ELSE 0 END) as stale, SUM(CASE WHEN tracking_status = 'offline' THEN 1 ELSE 0 END) as offline, MAX(last_seen_at) as latest_seen_at", false)
            ->get()
            ->row_array();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'online' => (int) ($row['online'] ?? 0),
            'stale' => (int) ($row['stale'] ?? 0),
            'offline' => (int) ($row['offline'] ?? 0),
            'latest_seen_at' => trim((string) ($row['latest_seen_at'] ?? '')),
        ];
    }

    public function get_dms_manual_reviews($tenantId, array $filters = [], $limit = 25, $offset = 0)
    {
        $tenantId = (int) $tenantId;
        $limit = max(1, (int) $limit);
        $offset = max(0, (int) $offset);

        if (!$this->dms_table_exists('kt_manual_reviews')) {
            return ['rows' => [], 'total' => 0];
        }

        $total = $this->build_dms_query('kt_manual_reviews', $tenantId, $filters, [
            'review_status' => 'review_status',
            'priority' => 'priority',
            'review_type' => 'review_type',
            'assigned_staff_id' => 'assigned_staff_id',
        ], 'created_at')->count_all_results();

        $rows = $this->build_dms_query('kt_manual_reviews', $tenantId, $filters, [
            'review_status' => 'review_status',
            'priority' => 'priority',
            'review_type' => 'review_type',
            'assigned_staff_id' => 'assigned_staff_id',
        ], 'created_at')
            ->select('d.id, d.tenant_id, d.entity_type, d.entity_id, d.review_type, d.review_status, d.priority, d.assigned_staff_id, d.review_reason, d.source_system, d.payload_json, d.resolution_note, d.resolved_by, d.resolved_at, d.created_at, d.updated_at, CONCAT(COALESCE(s.firstname, \'\'), \' \', COALESCE(s.lastname, \'\')) as assigned_staff_name', false)
            ->join(db_prefix() . 'staff s', 's.staffid = d.assigned_staff_id', 'left')
            ->order_by('created_at', 'desc')
            ->order_by('id', 'desc')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        return ['rows' => $rows, 'total' => (int) $total];
    }

    public function get_dms_manual_review_summary($tenantId, array $filters = [])
    {
        if (!$this->dms_table_exists('kt_manual_reviews')) {
            return [
                'open' => 0,
                'in_progress' => 0,
                'resolved' => 0,
                'high_or_critical' => 0,
                'oldest_open_at' => '',
            ];
        }

        $row = $this->build_dms_query('kt_manual_reviews', (int) $tenantId, $filters, [
            'review_status' => 'review_status',
            'priority' => 'priority',
            'review_type' => 'review_type',
            'assigned_staff_id' => 'assigned_staff_id',
        ], 'created_at')
            ->select("SUM(CASE WHEN review_status = 'OPEN' THEN 1 ELSE 0 END) as open_count, SUM(CASE WHEN review_status = 'IN_PROGRESS' THEN 1 ELSE 0 END) as in_progress_count, SUM(CASE WHEN review_status = 'RESOLVED' THEN 1 ELSE 0 END) as resolved_count, SUM(CASE WHEN priority IN ('HIGH','CRITICAL') THEN 1 ELSE 0 END) as high_or_critical, MIN(CASE WHEN review_status = 'OPEN' THEN created_at ELSE NULL END) as oldest_open_at", false)
            ->get()
            ->row_array();

        return [
            'open' => (int) ($row['open_count'] ?? 0),
            'in_progress' => (int) ($row['in_progress_count'] ?? 0),
            'resolved' => (int) ($row['resolved_count'] ?? 0),
            'high_or_critical' => (int) ($row['high_or_critical'] ?? 0),
            'oldest_open_at' => trim((string) ($row['oldest_open_at'] ?? '')),
        ];
    }

    public function get_dms_notifications($tenantId, array $filters = [], $limit = 25, $offset = 0)
    {
        $tenantId = (int) $tenantId;
        $limit = max(1, (int) $limit);
        $offset = max(0, (int) $offset);

        if (!$this->dms_table_exists('kt_notification_events')) {
            return ['rows' => [], 'total' => 0];
        }

        $total = $this->build_dms_query('kt_notification_events', $tenantId, $filters, [
            'status' => 'status',
            'priority' => 'priority',
            'event_type' => 'event_type',
        ], 'created_at')->count_all_results();

        $rows = $this->build_dms_query('kt_notification_events', $tenantId, $filters, [
            'status' => 'status',
            'priority' => 'priority',
            'event_type' => 'event_type',
        ], 'created_at')
            ->select('id, tenant_id, event_type, event_code, title, message, target_type, target_id, priority, payload_json, status, created_at, read_at, updated_at')
            ->order_by('created_at', 'desc')
            ->order_by('id', 'desc')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        return ['rows' => $rows, 'total' => (int) $total];
    }

    public function get_dms_notification_summary($tenantId, array $filters = [])
    {
        if (!$this->dms_table_exists('kt_notification_events')) {
            return [
                'unread' => 0,
                'critical' => 0,
                'high' => 0,
                'latest_notification_at' => '',
            ];
        }

        $row = $this->build_dms_query('kt_notification_events', (int) $tenantId, $filters, [
            'status' => 'status',
            'priority' => 'priority',
            'event_type' => 'event_type',
        ], 'created_at')
            ->select("SUM(CASE WHEN status = 'NEW' THEN 1 ELSE 0 END) as unread, SUM(CASE WHEN priority = 'CRITICAL' THEN 1 ELSE 0 END) as critical, SUM(CASE WHEN priority = 'HIGH' THEN 1 ELSE 0 END) as high_count, MAX(created_at) as latest_notification_at", false)
            ->get()
            ->row_array();

        return [
            'unread' => (int) ($row['unread'] ?? 0),
            'critical' => (int) ($row['critical'] ?? 0),
            'high' => (int) ($row['high_count'] ?? 0),
            'latest_notification_at' => trim((string) ($row['latest_notification_at'] ?? '')),
        ];
    }

    public function get_dms_tenant_settings($tenantId)
    {
        if (!class_exists('LicensingService', false)) {
            require_once module_dir_path('kt_saas', 'services/LicensingService.php');
        }
        if (!class_exists('TenantEntitlementService', false)) {
            require_once module_dir_path('kt_saas', 'services/TenantEntitlementService.php');
        }
        if (!isset($this->Kt_saas_model)) {
            $this->load->model('kt_saas/Kt_saas_model');
        }

        $tenant = $this->Kt_saas_model->get_tenant((int) $tenantId);
        if (!$tenant) {
            return [];
        }

        $subscription = $this->Kt_saas_model->get_tenant_subscription_profile((int) $tenantId);
        $entitlementService = new TenantEntitlementService();
        $profile = $entitlementService->getRuntimeProfile($tenant);

        $addonContext = method_exists($this->Kt_saas_model, 'resolve_tenant_dms_addon_context')
            ? $this->Kt_saas_model->resolve_tenant_dms_addon_context((int) $tenantId)
            : [];

        $payload = (new LicensingService())->buildTenantLicensingPayload($tenant, $subscription, $profile, $entitlementService, $addonContext);
        if (!empty($addonContext['package']['name'])) {
            $payload['dms_package_name'] = (string) $addonContext['package']['name'];
        }
        if (!empty($addonContext['addon']['billing_cycle'])) {
            $payload['dms_billing_cycle'] = strtolower(trim((string) $addonContext['addon']['billing_cycle']));
        }

        return $payload;
    }

    public function get_dms_tenant_module_visibility($tenantId, array $tenantSettings = [])
    {
        if (empty($tenantSettings)) {
            $tenantSettings = $this->get_dms_tenant_settings((int) $tenantId);
        }

        $features = (array) ($tenantSettings['features'] ?? []);
        $dmsEnabled = !empty($features['DMS_ENABLED']);

        return [
            'setup_center' => $dmsEnabled,
            'overview' => $dmsEnabled,
            'checkins' => $dmsEnabled && !empty($features['CHECKIN_ENABLED']),
            'visits' => $dmsEnabled && !empty($features['VISIT_ENABLED']),
            'notifications' => false,
            'feedback' => $dmsEnabled,
            'live_tracking' => $dmsEnabled && !empty($features['LIVE_TRACKING_ENABLED']),
            'orders' => $dmsEnabled,
            'sync_health' => $dmsEnabled,
            'open_dms' => $dmsEnabled,
            // These surfaces are not complete tenant workflows yet.
            'route_analytics' => false,
            'manual_reviews' => false,
        ];
    }

    public function get_dms_bridge_source_status($tenantId, $source)
    {
        $tenantId = (int) $tenantId;
        $definitions = [
            'checkins' => [
                'table' => 'dms_checkin_events',
                'timestamp' => 'created_at',
                'event' => 'checkin.sync.success',
                'label' => 'DMS check-in bridge',
            ],
            'visits' => [
                'table' => 'dms_visit_snapshots',
                'timestamp' => 'created_at',
                'event' => 'visit.sync.success',
                'label' => 'DMS visit snapshot bridge',
            ],
            'live_tracking' => [
                'table' => 'dms_live_tracking_snapshots',
                'timestamp' => 'updated_at',
                'event' => 'live_tracking',
                'label' => 'DMS live tracking bridge',
            ],
            'notifications' => [
                'table' => 'kt_notification_events',
                'timestamp' => 'updated_at',
                'event' => 'notification',
                'label' => 'CRM notification bridge',
            ],
            'orders' => [
                'table' => 'dms_order_bridge',
                'timestamp' => 'updated_at',
                'event' => 'dms.order.bridge',
                'label' => 'DMS order bridge',
            ],
            'field_feedback' => [
                'table' => 'dms_field_feedback_projection',
                'timestamp' => 'last_synced_at',
                'event' => 'field_feedback.sync',
                'label' => 'DMS field feedback bridge',
            ],
        ];
        $definition = $definitions[(string) $source] ?? null;
        if (!$definition) {
            return [];
        }

        $count = 0;
        $lastEventAt = null;
        if ($this->dms_table_exists($definition['table'])) {
            $row = $this->landlord_db()
                ->select('COUNT(*) as total, MAX(' . $definition['timestamp'] . ') as last_event_at', false)
                ->where('tenant_id', $tenantId)
                ->get(db_prefix() . $definition['table'])
                ->row_array();
            $count = (int) ($row['total'] ?? 0);
            $lastEventAt = !empty($row['last_event_at']) ? (string) $row['last_event_at'] : null;
        }

        $lastLog = $this->landlord_db()
            ->select('id, level, event, message, created_at')
            ->where('tenant_id', $tenantId)
            ->where('provider_code', 'salesflow_dms')
            ->like('event', $definition['event'], 'after')
            ->order_by('id', 'desc')
            ->limit(1)
            ->get(db_prefix() . 'kt_integration_logs')
            ->row_array();

        return [
            'source' => (string) $source,
            'label' => $definition['label'],
            'table' => db_prefix() . $definition['table'],
            'record_count' => $count,
            'last_event_at' => $lastEventAt,
            'last_log_id' => isset($lastLog['id']) ? (int) $lastLog['id'] : null,
            'last_log_level' => (string) ($lastLog['level'] ?? ''),
            'last_log_event' => (string) ($lastLog['event'] ?? ''),
            'last_log_at' => (string) ($lastLog['created_at'] ?? ''),
        ];
    }

    public function get_dms_console_status($tenantId, array $tenantSettings = [])
    {
        $tenantId = (int) $tenantId;
        if (empty($tenantSettings)) {
            $tenantSettings = $this->get_dms_tenant_settings($tenantId);
        }
        if (!isset($this->Kt_saas_model)) {
            $this->load->model('kt_saas/Kt_saas_model');
        }

        $staffSync = $this->get_dms_sync_job_status_snapshot($tenantId, 'sync_staff_to_dms');
        $customerSync = $this->get_dms_sync_job_status_snapshot($tenantId, 'sync_customer_to_dms');
        $productSync = $this->get_dms_sync_job_status_snapshot($tenantId, 'sync_product_to_dms');
        $warehouseSync = $this->get_dms_sync_job_status_snapshot($tenantId, 'sync_warehouse_to_dms');

        $errorRow = $this->landlord_db()
            ->select('id, event, message, created_at')
            ->where('tenant_id', $tenantId)
            ->where('provider_code', 'salesflow_dms')
            ->where('level', 'error')
            ->order_by('id', 'desc')
            ->limit(1)
            ->get(db_prefix() . 'kt_integration_logs')
            ->row_array();
        if ($errorRow) {
            $errorAt = strtotime((string) ($errorRow['created_at'] ?? ''));
            $latestRecoveryAt = 0;
            foreach ([$staffSync, $customerSync, $productSync] as $snapshot) {
                $syncedAt = strtotime((string) ($snapshot['last_successful_sync_at'] ?? ''));
                if ($syncedAt && $syncedAt > $latestRecoveryAt) {
                    $latestRecoveryAt = $syncedAt;
                }
            }
            if ($errorAt && $latestRecoveryAt > $errorAt) {
                $errorRow = null;
            }
        }

        return [
            'staff_sync' => $staffSync,
            'customer_sync' => $customerSync,
            'product_sync' => $productSync,
            'warehouse_sync' => $warehouseSync,
            'checkin_bridge' => $this->get_dms_bridge_source_status($tenantId, 'checkins'),
            'visit_bridge' => $this->get_dms_bridge_source_status($tenantId, 'visits'),
            'order_bridge' => $this->get_dms_bridge_source_status($tenantId, 'orders'),
            'field_feedback_bridge' => $this->get_dms_bridge_source_status($tenantId, 'field_feedback'),
            'usage' => $this->Kt_saas_model->get_tenant_dms_usage_summary($tenantId),
            'projections' => $this->get_dms_projection_health($tenantId),
            'latest_error' => $errorRow ?: null,
        ];
    }

    public function get_dms_projection_health($tenantId)
    {
        $tenantId = (int) $tenantId;
        $health = [
            'last_job_status' => 'not_started',
            'record_count' => 0,
            'last_successful_sync_at' => '',
            'areas' => [],
        ];
        if ($tenantId <= 0 || !$this->ensure_dms_projection_tables()) {
            return $health;
        }

        $definitions = [
            'orders' => 'dms_order_projection',
            'locations' => 'dms_location_projection',
            'inventory' => 'dms_inventory_projection',
            'product_sales' => 'dms_product_sales_projection',
            'staff_activity' => 'dms_staff_activity_projection',
            'store_coverage' => 'dms_store_coverage_projection',
            'field_feedback' => 'dms_field_feedback_projection',
        ];
        $latest = '';
        foreach ($definitions as $key => $table) {
            if (!$this->dms_table_exists($table)) {
                $health['areas'][$key] = ['records' => 0, 'last_synced_at' => '', 'status' => 'not_started'];
                continue;
            }
            $row = $this->landlord_db()
                ->select('COUNT(*) as total, MAX(last_synced_at) as last_synced_at')
                ->where('tenant_id', $tenantId)
                ->get(db_prefix() . $table)
                ->row_array();
            $count = (int) ($row['total'] ?? 0);
            $last = (string) ($row['last_synced_at'] ?? '');
            $health['record_count'] += $count;
            if ($last !== '' && ($latest === '' || strtotime($last) > strtotime($latest))) {
                $latest = $last;
            }
            $health['areas'][$key] = [
                'records' => $count,
                'last_synced_at' => $last,
                'status' => $count > 0 ? 'synced' : 'no_data',
            ];
        }

        $health['last_successful_sync_at'] = $latest;
        $health['last_job_status'] = $health['record_count'] > 0 ? 'synced' : 'no_data';

        return $health;
    }

    public function get_landlord_dms_tenant_subscriptions(): array
    {
        $this->ensure_dms_landlord_management_tables();
        if (!isset($this->Kt_saas_model)) {
            $this->load->model('kt_saas/Kt_saas_model');
        }

        $tenantRows = $this->landlord_db()
            ->select('id, tenant_code, company_name, owner_email, status, subdomain, custom_domain, updated_at')
            ->from(db_prefix() . 'kt_saas_tenants')
            ->where('deleted_at IS NULL', null, false)
            ->order_by('company_name', 'asc')
            ->get()
            ->result_array();

        $rows = [];
        foreach ($tenantRows as $tenant) {
            $tenantId = (int) ($tenant['id'] ?? 0);
            $context = $this->Kt_saas_model->resolve_tenant_dms_addon_context($tenantId);
            $addon = (array) ($context['addon'] ?? []);
            $package = (array) ($context['package'] ?? []);
            $usage = $this->Kt_saas_model->get_tenant_dms_usage_summary($tenantId);
            $lastInvoice = $this->get_landlord_dms_last_invoice($tenantId);
            $lastPayment = $this->get_landlord_dms_last_payment($tenantId);
            $tenant['dms_workspace_host'] = $this->get_dms_workspace_host_for_tenant($tenantId);

            $rows[] = [
                'tenant' => $tenant,
                'addon' => $addon,
                'package' => $package,
                'usage' => $usage,
                'last_invoice' => $lastInvoice,
                'last_payment' => $lastPayment,
                'status' => (string) ($addon['status'] ?? 'none'),
                'package_code' => (string) ($addon['package_code'] ?? ''),
                'dms_enabled' => strtolower((string) ($addon['status'] ?? '')) === 'active',
                'open_crm_url' => !empty($tenant['subdomain']) ? 'https://' . $tenant['subdomain'] . '.khachtot.test/admin' : '',
                'open_dms_url' => !empty($tenant['dms_workspace_host']) ? 'https://' . $tenant['dms_workspace_host'] : '',
            ];
        }

        return $rows;
    }

    public function get_landlord_dms_fleet_dashboard(): array
    {
        $rows = $this->get_landlord_dms_tenant_subscriptions();
        $summary = [
            'total' => 0,
            'active' => 0,
            'suspended' => 0,
            'cancelled' => 0,
            'pending_payment' => 0,
            'pending_approval' => 0,
            'quota_warning' => 0,
            'sync_error' => 0,
            'projection_stale' => 0,
            'workspace_warning' => 0,
            'missing_payment_state' => 0,
        ];
        $attention = [];
        $syncFailures = [];

        foreach ($rows as $row) {
            $tenant = (array) ($row['tenant'] ?? []);
            $tenantId = (int) ($tenant['id'] ?? 0);
            $status = strtolower(trim((string) ($row['status'] ?? 'none')));
            $hasAddon = !empty($row['addon']);
            if ($hasAddon) {
                $summary['total']++;
            }
            if (isset($summary[$status])) {
                $summary[$status]++;
            }
            if (in_array($status, ['payment_pending', 'draft'], true)) {
                $summary['pending_payment']++;
            }
            if (in_array($status, ['pending_upgrade', 'pending_downgrade', 'downgrade_blocked'], true)) {
                $summary['pending_approval']++;
            }
            if ($hasAddon && empty($row['last_payment']) && empty($row['last_invoice'])) {
                $summary['missing_payment_state']++;
                $attention[] = $this->landlord_attention_row($tenant, 'Chưa có dữ liệu thanh toán DMS', 'Kiểm tra billing/payment state');
            }
            if (empty($tenant['dms_workspace_host'])) {
                $summary['workspace_warning']++;
                $attention[] = $this->landlord_attention_row($tenant, 'Workspace/domain warning', 'Kiểm tra subdomain DMS');
            }

            $quotaWarnings = $this->landlord_quota_warnings((array) ($row['usage'] ?? []));
            if (!empty($quotaWarnings)) {
                $summary['quota_warning']++;
                $attention[] = $this->landlord_attention_row($tenant, 'Cảnh báo hạn mức', implode('; ', $quotaWarnings));
            }

            if ($tenantId > 0) {
                $settings = $this->get_dms_tenant_settings($tenantId);
                $console = $this->get_dms_console_status($tenantId, $settings);
                $latestError = (array) ($console['latest_error'] ?? []);
                $projection = (array) ($console['projections'] ?? []);
                if (!empty($latestError)) {
                    $summary['sync_error']++;
                    $message = (string) ($latestError['message'] ?? ($latestError['event'] ?? 'Lỗi đồng bộ'));
                    if ($message === 'Sync job failed.') {
                        $message = 'Tác vụ đồng bộ chưa thành công. Kiểm tra kết nối và thử lại.';
                    }
                    $attention[] = $this->landlord_attention_row($tenant, 'Lỗi đồng bộ', $message);
                    $syncFailures[] = [
                        'tenant' => $tenant,
                        'event' => (string) ($latestError['event'] ?? 'sync.error'),
                        'message' => $message,
                        'created_at' => (string) ($latestError['created_at'] ?? ''),
                    ];
                }
                $lastSync = (string) ($projection['last_successful_sync_at'] ?? '');
                if ($lastSync !== '' && strtotime($lastSync) < strtotime('-2 days')) {
                    $summary['projection_stale']++;
                    $attention[] = $this->landlord_attention_row($tenant, 'Projection stale', 'Lần đồng bộ cuối: ' . $lastSync);
                }
            }
        }

        return [
            'summary' => $summary,
            'tenants' => $rows,
            'attention' => array_slice($attention, 0, 20),
            'recent_actions' => $this->get_landlord_dms_action_log(null, 15),
            'sync_failures' => array_slice($syncFailures, 0, 15),
            'map_provider' => $this->get_dms_platform_config(),
            'support_policy' => [
                'mode' => 'view_only_placeholder',
                'label' => 'Chỉ xem khi được phân quyền',
                'policy' => 'Support/Ops chỉ xem dữ liệu tenant khi có quyền landlord và mọi thao tác thay đổi vẫn yêu cầu landlord admin, POST/CSRF và audit log.',
            ],
        ];
    }

    public function get_dms_platform_config(): array
    {
        $defaults = [
            'product_writeback_enabled' => false,
            'map_provider' => 'keyless',
            'map_status' => 'ready',
            'map_enabled' => true,
            'map_api_key_configured' => false,
            'allowed_domains' => [],
            'map_validation_state' => 'ready',
            'last_validation_status' => 'Keyless canvas sẵn sàng',
            'sso_enabled' => true,
            'bridge_enabled' => true,
            'sso_timeout_seconds' => 3,
            'retention_months' => 6,
            'managed_by' => 'platform',
            'policy' => 'Nền tảng quản lý nhà cung cấp bản đồ và thông tin xác thực. Tenant chỉ xem trạng thái sẵn sàng.',
        ];
        $decoded = function_exists('get_option')
            ? json_decode((string) get_option('kt_dms_platform_config_json'), true)
            : [];
        $config = is_array($decoded) ? array_merge($defaults, $decoded) : $defaults;
        if (is_string($config['allowed_domains'])) {
            $config['allowed_domains'] = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $config['allowed_domains']))));
        }
        $config['allowed_domains'] = kt_integration_hub_json_decode((string) get_option('kt_dms_maps_runtime_allowed_domains'), $config['allowed_domains']);
        $config['map_api_key_configured'] = function_exists('get_option')
            && (trim((string) get_option('kt_dms_maps_runtime_browser_key_encrypted')) !== ''
                || trim((string) get_option('kt_dms_google_maps_api_key_encrypted')) !== '');
        $config['provider_source'] = (string) get_option('kt_dms_maps_runtime_source') ?: 'salesrep_platform_pending';
        if (($config['map_provider'] ?? 'keyless') === 'google' && empty($config['map_api_key_configured'])) {
            $config['map_status'] = 'missing_config';
            $config['map_validation_state'] = 'missing_key';
            $config['last_validation_status'] = 'Thiếu khóa Google Maps phía máy chủ';
        } elseif (($config['map_provider'] ?? 'keyless') === 'google' && empty($config['allowed_domains'])) {
            $config['map_status'] = 'missing_config';
            $config['map_validation_state'] = 'missing_allowed_domains';
            $config['last_validation_status'] = 'Chưa cấu hình domain được phép dùng Google Maps';
        } elseif (($config['map_provider'] ?? '') === 'disabled') {
            $config['map_status'] = 'disabled';
            $config['map_validation_state'] = 'disabled';
            $config['last_validation_status'] = 'Bản đồ đã bị tắt ở cấp nền tảng';
        } else {
            $config['map_status'] = 'ready';
            $config['map_validation_state'] = 'ready';
        }
        $config['config_version'] = isset($this->Kt_saas_model) && method_exists($this->Kt_saas_model, 'get_dms_config_version')
            ? $this->Kt_saas_model->get_dms_config_version()
            : max(1, (int) (function_exists('get_option') ? get_option('kt_saas_dms_config_version') : 1));
        $config['generated_at'] = function_exists('get_option') && get_option('kt_saas_dms_config_generated_at')
            ? (string) get_option('kt_saas_dms_config_generated_at')
            : gmdate('c');

        return $config;
    }

    public function save_dms_platform_config(array $input): array
    {
        $before = $this->get_dms_platform_config();
        $provider = strtolower(trim((string) ($input['map_provider'] ?? 'keyless')));
        if (!in_array($provider, ['keyless', 'google', 'disabled'], true)) {
            return ['success' => false, 'message' => 'Nhà cung cấp bản đồ không hợp lệ.'];
        }

        $config = [
            'map_provider' => $provider,
            'map_enabled' => $provider !== 'disabled',
            'allowed_domains' => array_values(array_unique(array_filter(array_map(
                static fn($domain) => strtolower(trim((string) $domain)),
                preg_split('/[\r\n,]+/', (string) ($input['allowed_domains'] ?? ''))
            ), static fn($domain) => $domain !== '' && preg_match('/^(?:\*\.)?[a-z0-9.-]+$/', $domain)))),
            'sso_enabled' => !empty($input['sso_enabled']),
            'bridge_enabled' => true,
            'sso_timeout_seconds' => max(1, min(10, (int) ($input['sso_timeout_seconds'] ?? 3))),
            'retention_months' => max(1, min(36, (int) ($input['retention_months'] ?? 6))),
            'managed_by' => 'platform',
        ];
        if (!function_exists('update_option')) {
            return ['success' => false, 'message' => 'Không thể lưu cấu hình nền tảng.'];
        }

        unset($input['map_api_key']);
        update_option('kt_dms_platform_config_json', kt_integration_hub_json_encode($config));
        update_option('kt_dms_global_config_updated_at', date('Y-m-d H:i:s'));

        if (!isset($this->Kt_saas_model)) {
            $this->load->model('kt_saas/Kt_saas_model');
        }
        $version = method_exists($this->Kt_saas_model, 'bump_dms_config_version')
            ? $this->Kt_saas_model->bump_dms_config_version('platform_config_saved')
            : 1;
        $after = $this->get_dms_platform_config();
        $after['config_version'] = $version;
        $this->record_landlord_dms_action(0, 'platform_config_saved', $before, $after);

        return ['success' => true, 'message' => 'Đã lưu cấu hình DMS toàn cục.', 'config' => $after];
    }

    public function validate_dms_global_config(): array
    {
        $config = $this->get_dms_platform_config();
        $mapReady = ($config['map_provider'] ?? '') !== 'google'
            || (!empty($config['map_api_key_configured']) && !empty($config['allowed_domains']));
        if (!isset($this->Kt_saas_model)) {
            $this->load->model('kt_saas/Kt_saas_model');
        }
        $tenants = array_values(array_filter($this->Kt_saas_model->get_tenants(false), static function ($tenant) {
            return in_array((string) ($tenant['status'] ?? ''), ['active', 'trial', 'grace'], true);
        }));
        $tenantResults = [];
        foreach ($tenants as $tenant) {
            $tenantId = (int) ($tenant['id'] ?? 0);
            $connection = $this->get_salesflow_connection_status($tenantId);
            $tenantResults[] = [
                'tenant_id' => $tenantId,
                'tenant_name' => (string) ($tenant['company_name'] ?? $tenant['tenant_code'] ?? ''),
                'config_version' => (int) ($connection['config_version'] ?? 1),
                'connection_status' => (string) ($connection['status'] ?? 'missing'),
                'success' => $mapReady && !empty($connection['configured']),
                'message' => !$mapReady ? (string) ($config['last_validation_status'] ?? 'Cấu hình bản đồ chưa hợp lệ') : (string) ($connection['message'] ?? ''),
            ];
        }
        $failed = count(array_filter($tenantResults, static fn($row) => empty($row['success'])));
        $result = [
            'success' => $mapReady && $failed === 0,
            'tested_at' => kt_integration_hub_now(),
            'map_ready' => $mapReady,
            'map_state' => (string) ($config['map_validation_state'] ?? 'unknown'),
            'tenant_count' => count($tenantResults),
            'failed_count' => $failed,
            'tenants' => $tenantResults,
        ];
        update_option('kt_dms_global_validation_json', kt_integration_hub_json_encode($result));
        $this->record_landlord_dms_action(0, 'platform_config_validated', [], [
            'success' => $result['success'],
            'map_state' => $result['map_state'],
            'tenant_count' => $result['tenant_count'],
            'failed_count' => $result['failed_count'],
        ]);

        return $result;
    }

    private function landlord_attention_row(array $tenant, string $issue, string $nextAction): array
    {
        return [
            'tenant_id' => (int) ($tenant['id'] ?? 0),
            'tenant_name' => (string) ($tenant['company_name'] ?? ''),
            'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
            'issue' => $issue,
            'next_action' => $nextAction,
            'detail_url' => admin_url('kt_integration_hub/dms_landlord_tenant/' . (int) ($tenant['id'] ?? 0)),
        ];
    }

    private function landlord_quota_warnings(array $usage): array
    {
        $warnings = [];
        foreach ((array) ($usage['rows'] ?? []) as $row) {
            $limit = (int) ($row['limit'] ?? 0);
            $used = (int) ($row['used'] ?? 0);
            if ($limit > 0 && $used >= (int) floor($limit * 0.9)) {
                $warnings[] = (string) ($row['label'] ?? $row['metric_key'] ?? 'quota') . ': ' . $used . '/' . $limit;
            }
        }

        return $warnings;
    }

    public function get_landlord_dms_tenant_detail($tenantId): array
    {
        $tenantId = (int) $tenantId;
        $this->ensure_dms_landlord_management_tables();
        if (!isset($this->Kt_saas_model)) {
            $this->load->model('kt_saas/Kt_saas_model');
        }

        $tenant = $this->get_tenant($tenantId);
        if (!$tenant) {
            return [];
        }

        $context = $this->Kt_saas_model->resolve_tenant_dms_addon_context($tenantId);
        $usage = $this->Kt_saas_model->get_tenant_dms_usage_summary($tenantId);
        $tenantSettings = $this->get_dms_tenant_settings($tenantId);
        $console = $this->get_dms_console_status($tenantId, $tenantSettings);
        $latestDmsOrder = method_exists($this->Kt_saas_model, 'get_latest_dms_commercial_order')
            ? $this->Kt_saas_model->get_latest_dms_commercial_order($tenantId)
            : [];

        return [
            'tenant' => $tenant,
            'addon' => (array) ($context['addon'] ?? []),
            'package' => (array) ($context['package'] ?? []),
            'status' => (string) ($context['status'] ?? 'none'),
            'usage' => $usage,
            'features' => (array) ($tenantSettings['features'] ?? []),
            'limits' => (array) ($tenantSettings['limits'] ?? []),
            'overrides' => $this->get_landlord_dms_tenant_overrides($tenantId),
            'workspace' => [
                'host' => $this->get_dms_workspace_host_for_tenant($tenantId),
                'crm_host' => !empty($tenant['subdomain']) ? $tenant['subdomain'] . '.khachtot.test' : '',
                'custom_domain' => (string) ($tenant['custom_domain'] ?? ''),
            ],
            'billing' => [
                'invoices' => $this->get_landlord_dms_invoices($tenantId),
                'payments' => $this->get_landlord_dms_payments($tenantId),
            ],
            'health' => [
                'console' => $console,
                'projections' => (array) ($console['projections'] ?? []),
            ],
            'connection' => $this->get_salesflow_connection_status($tenantId),
            'actions' => $this->get_landlord_dms_action_log($tenantId),
            'latest_dms_order' => $latestDmsOrder,
            'packages' => $this->Kt_saas_model->get_dms_addon_package_catalog(),
        ];
    }

    public function landlord_update_dms_addon_status($tenantId, string $status, string $reason = ''): array
    {
        $tenantId = (int) $tenantId;
        $status = strtolower(trim($status));
        if (!in_array($status, ['active', 'suspended', 'cancelled', 'expired'], true)) {
            return ['success' => false, 'message' => 'Invalid DMS status.'];
        }
        if (!isset($this->Kt_saas_model)) {
            $this->load->model('kt_saas/Kt_saas_model');
        }
        $before = $this->Kt_saas_model->get_tenant_dms_addon($tenantId);
        if (empty($before)) {
            return ['success' => false, 'message' => 'Tenant does not have a DMS add-on row yet.'];
        }

        $payload = [
            'status' => $status,
            'provider_status' => $status,
            'notes' => trim($reason) !== '' ? trim($reason) : (string) ($before['notes'] ?? ''),
            'updated_at' => kt_integration_hub_now(),
        ];
        $this->landlord_db()
            ->where('id', (int) $before['id'])
            ->update(db_prefix() . 'kt_saas_tenant_addons', $payload);

        $after = $this->Kt_saas_model->get_tenant_dms_addon($tenantId);
        $configVersion = $this->Kt_saas_model->bump_dms_config_version('tenant_status_changed:' . $tenantId);
        $this->record_landlord_dms_action($tenantId, 'status_' . $status, $before, $after);

        return ['success' => true, 'message' => 'DMS status updated.', 'addon' => $after, 'config_version' => $configVersion];
    }

    public function landlord_change_dms_package($tenantId, string $packageCode, string $changeType = 'upgrade'): array
    {
        $tenantId = (int) $tenantId;
        $changeType = strtolower(trim($changeType));
        if (!in_array($changeType, ['upgrade', 'downgrade'], true)) {
            $changeType = 'upgrade';
        }
        if (!isset($this->Kt_saas_model)) {
            $this->load->model('kt_saas/Kt_saas_model');
        }
        $before = $this->Kt_saas_model->get_tenant_dms_addon($tenantId);
        $package = $this->Kt_saas_model->get_dms_addon_package($packageCode);
        if (empty($package)) {
            return ['success' => false, 'message' => 'Invalid target DMS package.'];
        }

        $usage = $this->Kt_saas_model->get_tenant_dms_usage_summary($tenantId);
        $blocked = false;
        $blockReasons = [];
        if ($changeType === 'downgrade') {
            $limits = [
                'dms_user' => (int) ($package['user_limit'] ?? 0),
                'dms_store' => (int) ($package['store_limit'] ?? 0),
                'dms_product' => (int) ($package['product_limit'] ?? 0),
                'dms_checkin' => (int) ($package['checkin_monthly_limit'] ?? 0),
                'dms_order' => (int) ($package['order_monthly_limit'] ?? 0),
                'dms_photo' => (int) ($package['photo_limit'] ?? 0),
                'dms_export' => (int) ($package['report_export_limit'] ?? 0),
            ];
            foreach ((array) ($usage['rows'] ?? []) as $row) {
                $key = (string) ($row['metric_key'] ?? '');
                $used = (int) ($row['used'] ?? 0);
                $limit = (int) ($limits[$key] ?? 0);
                if ($limit > 0 && $used > $limit) {
                    $blocked = true;
                    $blockReasons[] = ($row['label'] ?? $key) . ': ' . $used . ' > ' . $limit;
                }
            }
        }

        $status = $blocked ? 'downgrade_blocked' : ($changeType === 'downgrade' ? 'pending_downgrade' : 'pending_upgrade');
        if ($blocked) {
            $after = $before;
            $after['blocked_status'] = $status;
            $after['block_reasons'] = $blockReasons;
            $this->record_landlord_dms_action($tenantId, 'downgrade_blocked', $before, $after);

            return [
                'success' => false,
                'blocked' => true,
                'message' => 'Downgrade blocked by current usage: ' . implode('; ', $blockReasons),
                'addon' => $before,
            ];
        }

        $currentStatus = strtolower(trim((string) ($before['status'] ?? '')));
        $appliedStatus = $currentStatus === 'active' ? 'active' : $status;
        $billingCycle = strtolower(trim((string) ($before['billing_cycle'] ?? ($package['default_billing_period'] ?? 'monthly'))));
        if (!in_array($billingCycle, (array) ($package['allowed_billing_periods'] ?? []), true)) {
            $billingCycle = (string) ($package['default_billing_period'] ?? 'monthly');
        }
        $result = $this->Kt_saas_model->upsert_tenant_dms_addon($tenantId, $packageCode, $appliedStatus, [
            'notes' => ucfirst($changeType) . ' requested by landlord.',
            'billing_cycle' => $billingCycle,
        ]);
        $after = $this->Kt_saas_model->get_tenant_dms_addon($tenantId);
        $configVersion = $this->Kt_saas_model->bump_dms_config_version('tenant_package_changed:' . $tenantId);
        $this->record_landlord_dms_action($tenantId, $changeType . '_package', $before, $after);

        return [
            'success' => !empty($result['success']) && !$blocked,
            'blocked' => $blocked,
            'message' => $blocked ? 'Downgrade blocked by current usage: ' . implode('; ', $blockReasons) : 'Package change recorded.',
            'addon' => $after,
            'config_version' => $configVersion,
        ];
    }

    public function save_landlord_dms_tenant_overrides($tenantId, array $features, array $quota): array
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            return ['success' => false, 'message' => 'Tenant id is required.'];
        }
        $this->ensure_dms_landlord_management_tables();
        $before = $this->get_landlord_dms_tenant_overrides($tenantId);
        $allowedFeatures = ['dms_enabled', 'orders_enabled', 'visits_enabled', 'checkin_review_enabled', 'live_tracking_enabled', 'inventory_enabled', 'finance_view_enabled', 'reports_enabled', 'map_enabled', 'mobile_app_enabled'];
        $allowedQuota = ['user_limit', 'store_limit', 'product_limit', 'checkin_monthly_limit', 'photo_limit', 'order_monthly_limit', 'report_export_limit', 'retention_months', 'api_webhook_limit'];
        $normalizedFeatures = [];
        foreach ($allowedFeatures as $key) {
            if (array_key_exists($key, $features)) {
                $normalizedFeatures[$key] = !empty($features[$key]);
            }
        }
        $normalizedQuota = [];
        foreach ($allowedQuota as $key) {
            if (array_key_exists($key, $quota)) {
                $normalizedQuota[$key] = max(0, (int) $quota[$key]);
            }
        }

        $record = [
            'tenant_id' => $tenantId,
            'company_id' => $tenantId,
            'feature_overrides_json' => kt_integration_hub_json_encode($normalizedFeatures),
            'quota_overrides_json' => kt_integration_hub_json_encode($normalizedQuota),
            'updated_at' => kt_integration_hub_now(),
            'updated_by' => function_exists('get_staff_user_id') ? (int) get_staff_user_id() : null,
        ];
        $existing = $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->get(db_prefix() . 'kt_dms_tenant_overrides')
            ->row_array();
        if ($existing) {
            $this->landlord_db()->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_dms_tenant_overrides', $record);
        } else {
            $record['created_at'] = kt_integration_hub_now();
            $this->landlord_db()->insert(db_prefix() . 'kt_dms_tenant_overrides', $record);
        }
        $after = $this->get_landlord_dms_tenant_overrides($tenantId);
        if (!isset($this->Kt_saas_model)) {
            $this->load->model('kt_saas/Kt_saas_model');
        }
        $configVersion = $this->Kt_saas_model->bump_dms_config_version('tenant_overrides_changed:' . $tenantId);
        $this->record_landlord_dms_action($tenantId, 'feature_quota_update', $before, $after);

        return ['success' => true, 'message' => 'Feature and quota overrides saved.', 'overrides' => $after, 'config_version' => $configVersion];
    }

    public function get_landlord_dms_tenant_overrides($tenantId): array
    {
        $tenantId = (int) $tenantId;
        $this->ensure_dms_landlord_management_tables();
        $row = $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->get(db_prefix() . 'kt_dms_tenant_overrides')
            ->row_array();
        if (!$row) {
            return ['features' => [], 'quota' => [], 'row' => []];
        }

        return [
            'features' => kt_integration_hub_json_decode((string) ($row['feature_overrides_json'] ?? ''), []),
            'quota' => kt_integration_hub_json_decode((string) ($row['quota_overrides_json'] ?? ''), []),
            'row' => $row,
        ];
    }

    public function get_landlord_dms_action_log($tenantId = null, $limit = 25): array
    {
        $this->ensure_dms_landlord_management_tables();
        $db = $this->landlord_db()
            ->from(db_prefix() . 'kt_dms_landlord_actions')
            ->order_by('id', 'desc')
            ->limit(max(1, (int) $limit));
        if ($tenantId !== null) {
            $db->where('tenant_id', (int) $tenantId);
        }

        return $db->get()->result_array();
    }

    public function get_dms_sync_scope_settings($tenantId): array
    {
        $tenantId = (int) $tenantId;
        $defaults = [
            'staff' => ['mode' => 'selected_enabled_staff', 'enabled' => true, 'selected_ids' => []],
            'customer' => ['mode' => 'all_eligible', 'enabled' => true, 'selected_ids' => []],
            'product' => ['mode' => 'all_eligible', 'enabled' => true, 'selected_ids' => []],
            'store' => ['mode' => 'all_eligible', 'enabled' => true, 'selected_ids' => []],
            'region' => ['mode' => 'all_eligible', 'enabled' => true, 'selected_ids' => []],
        ];

        if ($tenantId <= 0 || !$this->ensure_dms_landlord_management_tables()) {
            return $defaults;
        }

        $row = $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->get(db_prefix() . 'kt_dms_sync_scope')
            ->row_array();

        if (!$row) {
            $this->landlord_db()->insert(db_prefix() . 'kt_dms_sync_scope', [
                'tenant_id' => $tenantId,
                'company_id' => $tenantId,
                'scope_json' => kt_integration_hub_json_encode($defaults),
                'updated_by' => get_staff_user_id(),
                'created_at' => kt_integration_hub_now(),
                'updated_at' => kt_integration_hub_now(),
            ]);
            return $defaults;
        }

        $scope = kt_integration_hub_json_decode((string) ($row['scope_json'] ?? ''), []);
        return array_replace_recursive($defaults, is_array($scope) ? $scope : []);
    }

    public function save_dms_sync_scope_settings($tenantId, array $scope): bool
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0 || !$this->ensure_dms_landlord_management_tables()) {
            return false;
        }

        $before = $this->get_dms_sync_scope_settings($tenantId);
        $allowedModes = ['all_eligible', 'selected_only', 'disabled', 'selected_enabled_staff'];
        $clean = $before;
        $clean['product_writeback_enabled'] = !empty($scope['product_writeback_enabled']);
        foreach (['staff', 'customer', 'product', 'store', 'region'] as $domain) {
            $input = (array) ($scope[$domain] ?? []);
            $mode = strtolower(trim((string) ($input['mode'] ?? ($clean[$domain]['mode'] ?? 'all_eligible'))));
            if (!in_array($mode, $allowedModes, true)) {
                $mode = $domain === 'staff' ? 'selected_enabled_staff' : 'all_eligible';
            }
            $ids = array_values(array_filter(array_map('intval', (array) ($input['selected_ids'] ?? []))));
            $clean[$domain] = [
                'mode' => $mode,
                'enabled' => $mode !== 'disabled' && (!array_key_exists('enabled', $input) || !empty($input['enabled'])),
                'selected_ids' => $ids,
            ];
        }

        $record = [
            'scope_json' => kt_integration_hub_json_encode($clean),
            'updated_by' => get_staff_user_id(),
            'updated_at' => kt_integration_hub_now(),
        ];

        $exists = $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->count_all_results(db_prefix() . 'kt_dms_sync_scope') > 0;
        if ($exists) {
            $this->landlord_db()->where('tenant_id', $tenantId)->update(db_prefix() . 'kt_dms_sync_scope', $record);
        } else {
            $record['tenant_id'] = $tenantId;
            $record['company_id'] = $tenantId;
            $record['created_at'] = kt_integration_hub_now();
            $this->landlord_db()->insert(db_prefix() . 'kt_dms_sync_scope', $record);
        }

        $this->record_landlord_dms_action($tenantId, 'tenant_sync_scope_updated', $before, $clean);
        return true;
    }

    public function get_dms_support_override_preview($tenantId): array
    {
        $tenantId = (int) $tenantId;
        $tenant = $this->get_tenant($tenantId);
        if (!$tenant) {
            return [];
        }

        $customer = $this->get_dms_sync_job_status_snapshot($tenantId, 'sync_customer_to_dms');
        $product = $this->get_dms_sync_job_status_snapshot($tenantId, 'sync_product_to_dms');

        return [
            'tenant_id' => $tenantId,
            'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
            'allowed_actions' => [
                'retry_failed_jobs' => 'Thử lại các tác vụ DMS lỗi mà không thay đổi phạm vi dữ liệu tenant.',
                'run_worker_once' => 'Chạy worker một lần cho hàng đợi hiện tại.',
            ],
            'preview' => [
                'staff_rows' => count($this->get_dms_staff_access_rows($tenantId)),
                'failed_customer_jobs' => (int) ($customer['failed_count'] ?? 0),
                'failed_product_jobs' => (int) ($product['failed_count'] ?? 0),
                'scope' => $this->get_dms_sync_scope_settings($tenantId),
                'destructive' => false,
            ],
        ];
    }

    public function run_dms_support_override($tenantId, array $input): array
    {
        $tenantId = (int) $tenantId;
        $action = strtolower(trim((string) ($input['action'] ?? '')));
        $reason = trim((string) ($input['reason'] ?? ''));
        $confirmed = !empty($input['confirm']);
        if ($tenantId <= 0 || $reason === '' || !$confirmed) {
            return ['success' => false, 'message' => 'Cần nhập lý do hỗ trợ và xác nhận preview trước khi thao tác.'];
        }

        $preview = $this->get_dms_support_override_preview($tenantId);
        if (empty($preview)) {
            return ['success' => false, 'message' => 'Không tìm thấy tenant để hỗ trợ.'];
        }

        $before = ['action' => $action, 'reason' => $reason, 'preview' => $preview];
        $result = ['success' => false, 'message' => 'Hành động hỗ trợ không hợp lệ.'];
        if ($action === 'retry_failed_jobs') {
            $this->landlord_db()
                ->where('tenant_id', $tenantId)
                ->where('provider_code', 'salesflow_dms')
                ->where('status', 'failed')
                ->update(db_prefix() . 'kt_integration_sync_jobs', [
                    'status' => 'retry',
                    'available_at' => kt_integration_hub_now(),
                    'locked_at' => null,
                    'updated_at' => kt_integration_hub_now(),
                ]);
            $result = ['success' => true, 'message' => 'Đã đưa tác vụ lỗi vào hàng đợi thử lại.', 'affected_jobs' => max(0, (int) $this->landlord_db()->affected_rows())];
        } elseif ($action === 'run_worker_once') {
            $result = ['success' => true, 'message' => 'Worker đã chạy một vòng an toàn.', 'summary' => $this->process_due_jobs(50)];
        }

        $this->record_landlord_dms_action($tenantId, 'support_override_' . ($action ?: 'unknown'), $before, $result);
        return $result;
    }

    public function get_dms_orders_invoices($tenantId, array $filters = [], $limit = 25, $offset = 0)
    {
        $tenantId = (int) $tenantId;
        $limit = max(1, (int) $limit);
        $offset = max(0, (int) $offset);

        $projected = $this->get_dms_projected_orders_invoices($tenantId, $filters, $limit, $offset);
        if (($projected['total'] ?? 0) > 0) {
            return $projected;
        }

        if (!$this->dms_table_exists('dms_order_bridge')) {
            return ['rows' => [], 'total' => 0];
        }

        $db = $this->landlord_db();
        $base = function () use ($db, $tenantId, $filters) {
            $query = $db->from(db_prefix() . 'dms_order_bridge')
                ->where('tenant_id', $tenantId)
                ->where('provider_code', 'salesflow_dms');

            if (!empty($filters['bridge_status'])) {
                $query->where('bridge_status', (string) $filters['bridge_status']);
            }
            if (!empty($filters['crm_invoice_id'])) {
                $query->where('crm_invoice_id', (int) $filters['crm_invoice_id']);
            }
            if (!empty($filters['date_from'])) {
                $query->where('updated_at >=', (string) $filters['date_from'] . ' 00:00:00');
            }
            if (!empty($filters['date_to'])) {
                $query->where('updated_at <=', (string) $filters['date_to'] . ' 23:59:59');
            }

            return $query;
        };

        $total = (int) $base()->count_all_results();
        $rows = $base()
            ->select('id, dms_order_id, dms_order_number, crm_invoice_id, bridge_status, invoice_status_snapshot, last_synced_at, error_code, error_message, created_at, updated_at')
            ->order_by('updated_at', 'desc')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
        $rows = $this->enrich_dms_order_rows_with_salesrep_amounts($tenantId, $rows);

        return ['rows' => $rows, 'total' => $total];
    }

    public function get_dms_orders_invoice_summary($tenantId)
    {
        $tenantId = (int) $tenantId;
        $projected = $this->get_dms_projected_order_summary($tenantId);
        if (($projected['projection_rows'] ?? 0) > 0) {
            return $projected;
        }

        $summary = [
            'total' => 0,
            'with_invoice' => 0,
            'failed' => 0,
            'cancelled' => 0,
            'pending' => 0,
        ];

        if (!$this->dms_table_exists('dms_order_bridge')) {
            return $summary;
        }

        $rows = $this->landlord_db()
            ->select('dms_order_id, bridge_status, crm_invoice_id, invoice_status_snapshot')
            ->where('tenant_id', $tenantId)
            ->where('provider_code', 'salesflow_dms')
            ->get(db_prefix() . 'dms_order_bridge')
            ->result_array();

        $orderIds = [];
        $invoiceIds = [];
        foreach ($rows as $row) {
            $summary['total']++;
            $orderIds[] = (int) ($row['dms_order_id'] ?? 0);
            if ((int) ($row['crm_invoice_id'] ?? 0) > 0) {
                $summary['with_invoice']++;
                $invoiceIds[] = (int) ($row['crm_invoice_id'] ?? 0);
            }
            $status = strtolower(trim((string) ($row['bridge_status'] ?? '')));
            $invoiceStatus = strtolower(trim((string) ($row['invoice_status_snapshot'] ?? '')));
            if ($status === 'failed') {
                $summary['failed']++;
            }
            if ($status === 'pending') {
                $summary['pending']++;
            }
            if ($status === 'cancelled' || $invoiceStatus === 'cancelled') {
                $summary['cancelled']++;
            }
        }
        if (!empty(array_filter($orderIds))) {
            $amounts = $this->get_salesrep_order_amount_summary($tenantId, $orderIds);
            foreach (['sales_amount', 'invoiced_amount', 'paid_amount', 'balance_amount'] as $key) {
                $summary[$key] = $amounts[$key];
            }
            $summary['payment_source'] = $amounts['source'];

            $crmPayments = $this->get_crm_invoice_payment_summary($invoiceIds);
            if (($crmPayments['known_count'] ?? 0) > 0) {
                $summary['paid_amount'] = $crmPayments['paid_amount'];
                $summary['balance_amount'] = $crmPayments['balance_amount'];
                $summary['crm_invoice_total'] = $crmPayments['invoice_total'];
                $summary['payment_source'] = 'crm.invoices';
            }
        }

        return $summary;
    }

    public function get_dms_staff_access_rows($tenantId)
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            return [];
        }
        if (!isset($this->Kt_saas_model)) {
            $this->load->model('kt_saas/Kt_saas_model');
        }

        $staffRows = $this->Kt_saas_model->get_active_tenant_staff_members_for_tenant($tenantId);
        $jobRows = $this->landlord_db()
            ->select('id, status, payload_json, result_json, error_message, attempts, max_attempts, available_at, locked_at, created_at, updated_at, request_id')
            ->where('tenant_id', $tenantId)
            ->where('provider_code', 'salesflow_dms')
            ->where('job_type', 'sync_staff_to_dms')
            ->order_by('id', 'desc')
            ->get(db_prefix() . 'kt_integration_sync_jobs')
            ->result_array();

        $latestByStaff = [];
        foreach ($jobRows as $job) {
            $payload = kt_integration_hub_json_decode((string) ($job['payload_json'] ?? ''), []);
            $staffId = (int) ($payload['staff']['crm_staff_id'] ?? 0);
            if ($staffId <= 0 || isset($latestByStaff[$staffId])) {
                continue;
            }
            $latestByStaff[$staffId] = [
                'id' => (int) ($job['id'] ?? 0),
                'role_code' => (string) ($payload['staff']['role_code'] ?? ''),
                'status' => (string) ($job['status'] ?? ''),
                'error' => (string) ($job['error_message'] ?? ''),
                'result_json' => (string) ($job['result_json'] ?? ''),
                'attempts' => (int) ($job['attempts'] ?? 0),
                'max_attempts' => (int) ($job['max_attempts'] ?? 0),
                'available_at' => (string) ($job['available_at'] ?? ''),
                'locked_at' => (string) ($job['locked_at'] ?? ''),
                'created_at' => (string) ($job['created_at'] ?? ''),
                'updated_at' => (string) ($job['updated_at'] ?? ''),
                'request_id' => (string) ($job['request_id'] ?? ''),
            ];
        }

        $accessRows = [];
        if ($this->landlord_db()->table_exists(db_prefix() . 'kt_dms_staff_access')) {
            foreach ($this->landlord_db()->where('tenant_id', $tenantId)->get(db_prefix() . 'kt_dms_staff_access')->result_array() as $row) {
                $accessRows[(int) ($row['crm_staff_id'] ?? 0)] = $row;
            }
        }

        $entityLinks = [];
        if ($this->landlord_db()->table_exists(db_prefix() . 'kt_integration_entity_links')) {
            foreach ($this->landlord_db()
                ->where('tenant_id', $tenantId)
                ->where('provider_code', 'salesflow_dms')
                ->where('entity_type', 'staff')
                ->get(db_prefix() . 'kt_integration_entity_links')
                ->result_array() as $linkRow) {
                $localId = (int) ($linkRow['local_id'] ?? 0);
                if ($localId <= 0) {
                    continue;
                }
                if (!isset($entityLinks[$localId])
                    || strtotime((string) ($linkRow['last_synced_at'] ?? '')) > strtotime((string) ($entityLinks[$localId]['last_synced_at'] ?? ''))) {
                    $entityLinks[$localId] = $linkRow;
                }
            }
        }

        $rows = [];
        foreach ($staffRows as $staff) {
            $staffId = (int) ($staff['staffid'] ?? 0);
            $sync = (array) ($latestByStaff[$staffId] ?? []);
            $access = (array) ($accessRows[$staffId] ?? []);
            $roleCode = strtolower(trim((string) ($access['dms_role_code'] ?? $sync['role_code'] ?? '')));
            if ($roleCode === '') {
                $roleCode = !empty($staff['admin']) ? 'admin' : 'field_sales';
            }
            $dmsRole = in_array($roleCode, ['admin', 'tenant_admin', 'super_admin', 'platform_admin'], true)
                ? 'Tenant Admin'
                : (in_array($roleCode, ['manager', 'moderator', 'regional_manager', 'sales_manager', 'supervisor'], true)
                    ? 'Manager'
                    : (in_array($roleCode, ['warehouse', 'warehouse_operator', 'inventory', 'inventory_staff'], true)
                        ? 'Warehouse Operator'
                        : (in_array($roleCode, ['finance', 'accounting', 'finance_accounting'], true) ? 'Finance' : 'Field Sales')));
            $canonicalDmsRole = $dmsRole === 'Tenant Admin'
                ? 'tenant_admin'
                : ($dmsRole === 'Manager'
                    ? 'regional_manager'
                    : ($dmsRole === 'Warehouse Operator'
                        ? 'warehouse_operator'
                        : ($dmsRole === 'Finance' ? 'finance' : 'field_sales')));

            $rows[] = [
                'crm_staff_id' => $staffId,
                'name' => trim((string) ($staff['firstname'] ?? '') . ' ' . (string) ($staff['lastname'] ?? '')),
                'email' => (string) ($staff['email'] ?? ''),
                'crm_role' => $roleCode,
                'dms_role' => $dmsRole,
                'dms_role_code' => $canonicalDmsRole,
                'dms_access_enabled' => !array_key_exists('dms_access_enabled', $access) || !empty($access['dms_access_enabled']),
                'manager_crm_staff_id' => !empty($access['manager_crm_staff_id']) ? (int) $access['manager_crm_staff_id'] : null,
                'sync_status' => (string) ($access['sync_status'] ?? $sync['status'] ?? 'not_started'),
                'last_sync_at' => (string) ($access['last_synced_at'] ?? $sync['updated_at'] ?? ''),
                'error' => (string) ($access['sync_error'] ?? $sync['error'] ?? ''),
                'sync_progress' => $this->build_dms_staff_sync_progress(
                    $staffId,
                    $access,
                    $sync,
                    (array) ($entityLinks[$staffId] ?? [])
                ),
            ];
        }

        return $rows;
    }

    private function build_dms_staff_sync_progress($staffId, array $access, array $sync, array $entityLink)
    {
        $staffId = (int) $staffId;
        $jobStatus = strtolower(trim((string) ($sync['status'] ?? '')));
        $result = kt_integration_hub_json_decode((string) ($sync['result_json'] ?? ''), []);
        $response = (array) ($result['response'] ?? []);
        $responseData = (array) ($response['data'] ?? []);
        $salesrepAccepted = !empty($response['success'])
            && ((int) ($responseData['dms_user_id'] ?? 0) > 0 || trim((string) ($entityLink['external_id'] ?? '')) !== '');
        $membershipWritten = (int) ($responseData['workspace_membership_id'] ?? 0) > 0;
        $jobDone = in_array($jobStatus, ['done', 'succeeded'], true);
        $jobFailed = $jobStatus === 'failed';
        $jobProcessing = $jobStatus === 'processing';
        $jobQueued = in_array($jobStatus, ['queued', 'retry'], true);
        $jobAt = (string) (($sync['updated_at'] ?? '') ?: ($sync['created_at'] ?? ''));
        $lastSyncedAt = (string) (($access['last_synced_at'] ?? '') ?: ($entityLink['last_synced_at'] ?? '') ?: $jobAt);
        $error = trim((string) (($access['sync_error'] ?? '') ?: ($sync['error'] ?? '')));
        $ageSeconds = $jobAt !== '' && strtotime($jobAt) !== false ? time() - strtotime($jobAt) : 0;
        $slaWarning = ($jobQueued || $jobProcessing) && $ageSeconds > 600;

        $step = static function ($key, $label, $status, $message, $at = '') {
            return [
                'key' => $key,
                'label' => $label,
                'status' => $status,
                'message' => $message,
                'at' => (string) $at,
            ];
        };

        $steps = [];
        $steps[] = $step(
            'crm_saved',
            'Đã lưu trong CRM',
            !empty($access) ? 'done' : 'pending',
            !empty($access) ? 'Thiết lập quyền/vai trò đã được ghi trong CRM.' : 'Chưa có thiết lập DMS cho nhân sự này.',
            (string) ($access['updated_at'] ?? '')
        );

        $steps[] = $step(
            'job_created',
            'Đã tạo tác vụ đồng bộ',
            !empty($sync['id']) ? ($jobFailed ? 'failed' : 'done') : 'pending',
            !empty($sync['id']) ? 'Tác vụ đồng bộ đã được tạo để hệ thống xử lý.' : 'Chưa có tác vụ đồng bộ cho thay đổi này.',
            (string) ($sync['created_at'] ?? $jobAt)
        );

        $workerStatus = 'pending';
        $workerMessage = 'Hệ thống chưa xử lý tác vụ này.';
        if ($jobDone) {
            $workerStatus = 'done';
            $workerMessage = 'Hệ thống đã xử lý xong tác vụ.';
        } elseif ($jobFailed) {
            $workerStatus = 'failed';
            $workerMessage = $error !== '' ? $error : 'Hệ thống xử lý thất bại.';
        } elseif ($jobProcessing) {
            $workerStatus = 'current';
            $workerMessage = 'Hệ thống đang xử lý tác vụ.';
        } elseif ($jobQueued) {
            $workerStatus = $slaWarning ? 'warning' : 'current';
            $workerMessage = $slaWarning
                ? 'Tác vụ chờ quá thời gian dự kiến. Vui lòng kiểm tra lại hoặc thử đồng bộ lại.'
                : 'Tác vụ đang chờ hệ thống xử lý.';
        }
        $steps[] = $step('worker_processed', 'Hệ thống đã xử lý', $workerStatus, $workerMessage, $jobAt);

        $salesrepStatus = 'pending';
        $salesrepMessage = 'Chưa có xác nhận Salesrep đã nhận dữ liệu.';
        if ($salesrepAccepted) {
            $salesrepStatus = 'done';
            $salesrepMessage = 'Salesrep đã nhận và ghi nhận nhân sự.';
        } elseif ($jobFailed) {
            $salesrepStatus = 'failed';
            $salesrepMessage = 'Salesrep chưa nhận dữ liệu do tác vụ thất bại.';
        } elseif ($jobDone) {
            $salesrepStatus = 'warning';
            $salesrepMessage = 'Hệ thống đã xử lý nhưng chưa tìm thấy xác nhận đồng bộ từ Salesrep.';
        }
        $steps[] = $step('salesrep_received', 'Salesrep đã nhận dữ liệu', $salesrepStatus, $salesrepMessage, (string) ($entityLink['last_synced_at'] ?? $jobAt));

        $runtimeStatus = 'pending';
        $runtimeMessage = 'Chưa có bằng chứng DMS đã cập nhật quyền truy cập.';
        if ($salesrepAccepted && $membershipWritten) {
            $runtimeStatus = 'done';
            $runtimeMessage = 'DMS đã cập nhật người dùng và quyền trong workspace.';
        } elseif ($salesrepAccepted) {
            $runtimeStatus = 'warning';
            $runtimeMessage = 'DMS đã nhận người dùng nhưng chưa xác nhận quyền trong workspace.';
        } elseif ($jobFailed) {
            $runtimeStatus = 'failed';
            $runtimeMessage = 'DMS chưa cập nhật quyền do đồng bộ thất bại.';
        }
        $steps[] = $step('runtime_refreshed', 'DMS đã cập nhật quyền', $runtimeStatus, $runtimeMessage, $lastSyncedAt);

        $steps[] = $step(
            'browser_visible',
            'Người dùng mở DMS thấy dữ liệu mới',
            'pending',
            'Cần kiểm tra lại bằng phiên đăng nhập DMS thật của người dùng. Không tự đánh dấu xong nếu chưa kiểm tra bằng trình duyệt.',
            ''
        );

        $overall = 'not_started';
        if ($jobFailed) {
            $overall = 'failed';
        } elseif ($salesrepAccepted && $membershipWritten) {
            $overall = 'dms_recorded';
        } elseif ($salesrepAccepted) {
            $overall = 'salesrep_received';
        } elseif ($jobDone) {
            $overall = 'worker_done';
        } elseif ($jobProcessing) {
            $overall = 'processing';
        } elseif ($jobQueued) {
            $overall = $slaWarning ? 'delayed' : 'queued';
        } elseif (!empty($access)) {
            $overall = 'crm_saved';
        }

        return [
            'staff_id' => $staffId,
            'overall' => $overall,
            'overall_label' => $this->dms_sync_progress_label($overall),
            'steps' => $steps,
            'last_sync_at' => $lastSyncedAt,
            'estimated_processing_time' => 'Thường dưới 10 phút sau khi hệ thống bắt đầu xử lý.',
            'sla_warning' => $slaWarning,
            'safe_error' => $error !== '' ? $this->tenant_safe_sync_error($error) : '',
            'operator' => [
                'job_id' => (int) ($sync['id'] ?? 0),
                'request_id' => (string) ($sync['request_id'] ?? ''),
                'attempts' => (int) ($sync['attempts'] ?? 0),
                'max_attempts' => (int) ($sync['max_attempts'] ?? 0),
                'worker_state' => $jobStatus,
            ],
        ];
    }

    private function dms_sync_progress_label($state)
    {
        return [
            'not_started' => 'Chưa bắt đầu',
            'crm_saved' => 'Đã lưu trong CRM',
            'queued' => 'Đang chờ đồng bộ',
            'delayed' => 'Chậm hơn dự kiến',
            'processing' => 'Đang xử lý',
            'worker_done' => 'Hệ thống đã xử lý',
            'salesrep_received' => 'Salesrep đã nhận',
            'dms_recorded' => 'DMS đã ghi nhận',
            'failed' => 'Đồng bộ lỗi',
        ][(string) $state] ?? 'Đang kiểm tra';
    }

    private function tenant_safe_sync_error($message)
    {
        $message = trim((string) $message);
        if ($message === '') {
            return '';
        }

        if (stripos($message, 'connection') !== false || stripos($message, 'configured') !== false) {
            return 'Kết nối DMS chưa hoàn tất hoặc tạm thời không khả dụng.';
        }
        if (stripos($message, 'tenant') !== false || stripos($message, 'scope') !== false) {
            return 'Dữ liệu tenant chưa khớp với workspace DMS.';
        }
        if (stripos($message, 'email') !== false || stripos($message, 'conflict') !== false) {
            return 'Email nhân sự đang trùng với một tài khoản DMS khác.';
        }
        if (stripos($message, 'staff') !== false && stripos($message, 'not found') !== false) {
            return 'Không tìm thấy nhân sự nguồn trong CRM.';
        }

        return 'Đồng bộ chưa thành công. Vui lòng kiểm tra lại hoặc liên hệ hỗ trợ.';
    }

    public function save_dms_staff_access($tenantId, $staffId, array $data)
    {
        $tenantId = (int) $tenantId;
        $staffId = (int) $staffId;
        if ($tenantId <= 0 || $staffId <= 0) {
            return false;
        }
        if (!$this->landlord_db()->table_exists(db_prefix() . 'kt_dms_staff_access')) {
            return false;
        }

        $role = strtolower(trim((string) ($data['dms_role_code'] ?? 'field_sales')));
        if (!in_array($role, ['tenant_admin', 'regional_manager', 'field_sales', 'warehouse_operator', 'finance'], true)) {
            $role = 'field_sales';
        }
        $managerId = (int) ($data['manager_crm_staff_id'] ?? 0);
        if ($role !== 'field_sales') {
            $managerId = 0;
        }
        $record = [
            'company_id' => $tenantId,
            'dms_access_enabled' => !empty($data['dms_access_enabled']) ? 1 : 0,
            'dms_role_code' => $role,
            'manager_crm_staff_id' => $managerId > 0 ? $managerId : null,
            'updated_at' => kt_integration_hub_now(),
        ];
        $existing = $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->where('crm_staff_id', $staffId)
            ->get(db_prefix() . 'kt_dms_staff_access')
            ->row_array();

        if ($existing) {
            $this->landlord_db()->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_dms_staff_access', $record);
        } else {
            $record['tenant_id'] = $tenantId;
            $record['crm_staff_id'] = $staffId;
            $record['created_at'] = kt_integration_hub_now();
            $this->landlord_db()->insert(db_prefix() . 'kt_dms_staff_access', $record);
        }

        return true;
    }

    public function get_dms_staff_access_config_map($tenantId)
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0 || !$this->landlord_db()->table_exists(db_prefix() . 'kt_dms_staff_access')) {
            return [];
        }

        $map = [];
        foreach ($this->landlord_db()->where('tenant_id', $tenantId)->get(db_prefix() . 'kt_dms_staff_access')->result_array() as $row) {
            $map[(int) ($row['crm_staff_id'] ?? 0)] = $row;
        }

        return $map;
    }

    public function queue_all_dms_domain_sync_jobs($tenantId, $domain)
    {
        $tenantId = (int) $tenantId;
        $domain = strtolower(trim((string) $domain));
        if ($tenantId <= 0 || !in_array($domain, ['customer', 'product', 'warehouse'], true)) {
            return ['queued' => 0, 'skipped' => 0, 'message' => 'Miền dữ liệu đồng bộ không hợp lệ.'];
        }

        $scope = $this->get_dms_sync_scope_settings($tenantId);
        $domainScope = (array) ($scope[$domain] ?? []);
        $scopeMode = strtolower(trim((string) ($domainScope['mode'] ?? 'all_eligible')));
        if ($scopeMode === 'disabled' || empty($domainScope['enabled'])) {
            return [
                'queued' => 0,
                'skipped' => 0,
                'source_count' => 0,
                'scope_mode' => $scopeMode,
                'message' => 'Phạm vi đồng bộ do Tenant Admin quản lý đang tắt cho nhóm dữ liệu này.',
            ];
        }

        if ($scopeMode === 'selected_only') {
            $tenant = $this->get_tenant($tenantId);
            $connection = $this->get_salesflow_connection($tenantId);
            if (!$tenant || !$connection) {
                return ['queued' => 0, 'skipped' => 0, 'source_count' => 0, 'scope_mode' => $scopeMode, 'message' => 'Kiểm tra kết nối DMS trước khi đồng bộ.'];
            }

            $queued = 0;
            $skipped = 0;
            $ids = array_values(array_filter(array_map('intval', (array) ($domainScope['selected_ids'] ?? []))));
            foreach ($ids as $sourceId) {
                if ($domain === 'customer') {
                    $job = $this->queue_customer_sync_job($tenantId, $sourceId, (int) $connection['id']);
                } elseif ($domain === 'warehouse') {
                    $job = $this->queue_warehouse_sync_job($tenantId, $sourceId, (int) $connection['id']);
                } else {
                    $job = $this->queue_product_sync_job($tenantId, $sourceId, (int) $connection['id']);
                }
                !empty($job['success']) ? $queued++ : $skipped++;
            }

            return [
                'queued' => $queued,
                'skipped' => $skipped,
                'source_count' => count($ids),
                'scope_mode' => $scopeMode,
                'message' => $queued > 0 ? '' : 'Chưa có dữ liệu đã chọn phù hợp để đồng bộ.',
            ];
        }

        $result = $this->queue_all_dms_domain_sync_jobs_legacy($tenantId, $domain);
        $result['scope_mode'] = $scopeMode;
        return $result;
    }

    private function queue_all_dms_domain_sync_jobs_legacy($tenantId, $domain)
    {
        $tenantId = (int) $tenantId;
        $domain = strtolower(trim((string) $domain));
        if ($tenantId <= 0 || !in_array($domain, ['customer', 'product', 'warehouse'], true)) {
            return ['queued' => 0, 'skipped' => 0, 'message' => 'Miền dữ liệu đồng bộ không hợp lệ.'];
        }

        $tenant = $this->get_tenant($tenantId);
        $connection = $this->get_salesflow_connection($tenantId);
        if (!$tenant || !$connection) {
            return ['queued' => 0, 'skipped' => 0, 'message' => 'Kiểm tra kết nối DMS trước khi đồng bộ.'];
        }

        $landlordDb = $this->landlord_db();
        $CI = &get_instance();
        $previousDb = $CI->db;
        if (!class_exists('DatabaseSwitcher', false)) {
            require_once module_dir_path('kt_saas', 'tenant_bootstrap/DatabaseSwitcher.php');
        }

        $switcher = new DatabaseSwitcher();
        $switchResult = $switcher->switchConnection($tenant);
        if (empty($switchResult['switched'])) {
                return ['queued' => 0, 'skipped' => 0, 'message' => 'Dữ liệu doanh nghiệp đang tạm thời chưa khả dụng.'];
        }

        try {
            $table = $domain === 'customer'
                ? db_prefix() . 'clients'
                : ($domain === 'warehouse' ? db_prefix() . 'kt_warehouses' : db_prefix() . 'items');
            $column = $domain === 'customer' ? 'userid' : 'id';
            if (!$CI->db->table_exists($table)) {
                return ['queued' => 0, 'skipped' => 0, 'message' => 'Chưa có dữ liệu nguồn cho miền đồng bộ này.'];
            }
            $query = $CI->db->select($column)->from($table);
            if ($domain === 'customer' && $CI->db->field_exists('active', $table)) {
                $query->where('active', 1);
            }
            $sourceIds = array_map(static function ($row) use ($column) {
                return (int) ($row[$column] ?? 0);
            }, $query->get()->result_array());
        } finally {
            $CI->db = $landlordDb ?: $previousDb;
            $this->db = $CI->db;
            $CI->config->set_item('kt_saas_landlord_db', $landlordDb ?: $previousDb);
        }

        $queued = 0;
        $skipped = 0;
        foreach (array_filter($sourceIds) as $sourceId) {
            if ($domain === 'customer') {
                $result = $this->queue_customer_sync_job($tenantId, $sourceId, (int) $connection['id']);
            } elseif ($domain === 'warehouse') {
                $result = $this->queue_warehouse_sync_job($tenantId, $sourceId, (int) $connection['id']);
            } else {
                $result = $this->queue_product_sync_job($tenantId, $sourceId, (int) $connection['id']);
            }
            !empty($result['success']) ? $queued++ : $skipped++;
        }

        return [
            'queued' => $queued,
            'skipped' => $skipped,
            'source_count' => count(array_filter($sourceIds)),
            'message' => $queued > 0 ? '' : 'Chưa có dữ liệu nguồn phù hợp để đồng bộ.',
        ];
    }

    public function update_dms_staff_access_sync_result($tenantId, $staffId, $status, $error = null)
    {
        $tenantId = (int) $tenantId;
        $staffId = (int) $staffId;
        if ($tenantId <= 0 || $staffId <= 0 || !$this->landlord_db()->table_exists(db_prefix() . 'kt_dms_staff_access')) {
            return;
        }

        $record = [
            'sync_status' => (string) $status,
            'sync_error' => $error ? (string) $error : null,
            'last_synced_at' => in_array((string) $status, ['done', 'succeeded'], true) ? kt_integration_hub_now() : null,
            'updated_at' => kt_integration_hub_now(),
        ];
        $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->where('crm_staff_id', $staffId)
            ->update(db_prefix() . 'kt_dms_staff_access', $record);
    }

    public function get_dms_readiness_state($tenantId, array $tenantSettings = [])
    {
        $tenantId = (int) $tenantId;
        $features = (array) ($tenantSettings['features'] ?? []);
        $dmsEnabled = !empty($features['DMS_ENABLED']);
        $subscriptionStatus = strtolower(trim((string) ($tenantSettings['DMS_SUBSCRIPTION_STATUS'] ?? 'none')));
        $connectionReady = $this->has_salesflow_connection($tenantId);
        $provisionedUsers = $this->count_successful_staff_sync_jobs($tenantId);
        $checkins = $this->dms_table_exists('dms_checkin_events')
            ? $this->count_dms_records('dms_checkin_events', $tenantId)
            : 0;
        $visits = $this->dms_table_exists('dms_visit_snapshots')
            ? $this->count_dms_records('dms_visit_snapshots', $tenantId)
            : 0;
        $tracking = $this->dms_table_exists('dms_live_tracking_snapshots')
            ? $this->count_dms_records('dms_live_tracking_snapshots', $tenantId)
            : 0;
        $customerSync = $this->get_dms_sync_job_status_snapshot($tenantId, 'sync_customer_to_dms');
        $productSync = $this->get_dms_sync_job_status_snapshot($tenantId, 'sync_product_to_dms');
        $operationsAudit = array_values((array) ($tenantSettings['operations_audit'] ?? []));
        $configuredOperationalSettings = count(array_filter($operationsAudit, static function ($item) {
            return stripos((string) ($item['value'] ?? ''), 'Not configured') === false;
        }));
        $settingsReady = $configuredOperationalSettings > 0;
        $catalogSyncReady = (int) ($customerSync['successful_count'] ?? 0) > 0 || (int) ($productSync['successful_count'] ?? 0) > 0;
        $catalogSyncSummary = trim(implode(' | ', array_filter([
            ((int) ($customerSync['successful_count'] ?? 0) > 0)
                ? ((int) ($customerSync['successful_count'] ?? 0)) . ' lượt đồng bộ khách hàng/điểm bán thành công'
                : (!empty($customerSync['last_job_status']) ? 'Trạng thái gần nhất của khách hàng/điểm bán: ' . kt_dms_ui_status_label('sync', (string) $customerSync['last_job_status']) : ''),
            ((int) ($productSync['successful_count'] ?? 0) > 0)
                ? ((int) ($productSync['successful_count'] ?? 0)) . ' lượt đồng bộ sản phẩm thành công'
                : (!empty($productSync['last_job_status']) ? 'Trạng thái sản phẩm gần nhất: ' . kt_dms_ui_status_label('sync', (string) $productSync['last_job_status']) : ''),
        ])));
        $settingsSummary = $settingsReady
            ? 'Đã ghi nhận ' . $configuredOperationalSettings . ' cấu hình vận hành.'
            : 'Chưa có cấu hình vận hành được ghi nhận trong CRM.';

        if (!$dmsEnabled) {
            $code = 'NOT_INSTALLED';
            $label = 'Chưa kích hoạt';
            $summary = in_array($subscriptionStatus, ['payment_pending', 'provisioning', 'suspended', 'expired', 'cancelled'], true)
                ? 'Gói DMS chưa hoạt động. Vui lòng hoàn tất thanh toán hoặc kích hoạt.'
                : 'Doanh nghiệp chưa có gói DMS đang hoạt động.';
        } elseif (!$connectionReady || $provisionedUsers === 0) {
            $code = 'PROVISIONING';
            $label = 'Đang thiết lập';
            $summary = !$connectionReady ? 'Cần kiểm tra kết nối DMS.' : 'Cần đồng bộ ít nhất một nhân sự vào DMS.';
        } elseif ($checkins === 0 && $visits === 0) {
            $code = 'READY';
            $label = 'Sẵn sàng';
            $summary = 'DMS đã kết nối. Chưa có hoạt động thị trường được ghi nhận.';
        } else {
            $code = 'ACTIVE';
            $label = 'Đang hoạt động';
            $summary = 'DMS đã kết nối và có dữ liệu hoạt động thị trường.';
        }

        return [
            'code' => $code,
            'label' => $label,
            'summary' => $summary,
            'dms_enabled' => $dmsEnabled,
            'dms_subscription_status' => $subscriptionStatus,
            'connection_ready' => $connectionReady,
            'provisioned_users' => $provisionedUsers,
            'first_checkin_exists' => $checkins > 0,
            'first_visit_exists' => $visits > 0,
            'tracking_exists' => $tracking > 0,
            'catalog_sync_ready' => $catalogSyncReady,
            'catalog_sync_summary' => $catalogSyncSummary !== '' ? $catalogSyncSummary : 'Chưa có dữ liệu nguồn khách hàng, điểm bán hoặc sản phẩm được đồng bộ.',
            'settings_ready' => $settingsReady,
            'settings_summary' => $settingsSummary,
            'steps' => [
                [
                    'label' => 'Đồng bộ nhân sự',
                    'done' => $provisionedUsers > 0,
                    'detail' => $provisionedUsers > 0
                        ? 'Đã đồng bộ ' . $provisionedUsers . ' nhân sự.'
                        : (!$connectionReady ? 'Kiểm tra kết nối DMS.' : 'Gán nhân sự và vai trò DMS, sau đó thử lại.'),
                ],
                [
                    'label' => 'Mở DMS',
                    'done' => $connectionReady,
                    'detail' => $connectionReady
                        ? 'Kết nối DMS đã sẵn sàng.'
                        : 'Kiểm tra kết nối DMS.',
                ],
                [
                    'label' => 'Create first check-in',
                    'done' => $checkins > 0,
                    'detail' => $checkins > 0
                        ? $checkins . ' check-in event(s) already synced.'
                        : 'No check-in event has been synced yet.',
                ],
                [
                    'label' => 'Verify tracking',
                    'done' => $tracking > 0,
                    'detail' => $tracking > 0
                        ? $tracking . ' live tracking snapshot(s) available.'
                        : 'No live tracking snapshot has been synced yet.',
                ],
                [
                    'label' => 'Review coverage',
                    'done' => $visits > 0,
                    'detail' => $visits > 0
                        ? $visits . ' visit snapshot(s) available for coverage review.'
                        : 'Coverage review starts after the first visit snapshot.',
                ],
            ],
        ];
    }

    public function get_dms_marketplace_state($tenantId, array $tenantSettings = [], array $readiness = [])
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            return $this->resolve_dms_marketplace_state([], [], []);
        }

        if (empty($tenantSettings)) {
            $tenantSettings = $this->get_dms_tenant_settings($tenantId);
        }

        if (empty($readiness)) {
            $readiness = $this->get_dms_readiness_state($tenantId, $tenantSettings);
        }

        return $this->resolve_dms_marketplace_state(
            $tenantSettings,
            $readiness,
            $this->get_dms_marketplace_provisioning_snapshot($tenantId)
        );
    }

    public function resolve_dms_marketplace_state(array $tenantSettings, array $readiness, array $provisioning = [])
    {
        $features = (array) ($tenantSettings['features'] ?? []);
        $status = strtolower(trim((string) ($tenantSettings['status'] ?? 'suspended')));
        $subscriptionStatus = strtolower(trim((string) ($tenantSettings['DMS_SUBSCRIPTION_STATUS'] ?? 'none')));
        $readinessCode = strtoupper(trim((string) ($readiness['code'] ?? '')));
        $dmsEnabled = !empty($features['DMS_ENABLED']);
        $connectionReady = !empty($readiness['connection_ready']);
        $provisionedUsers = (int) ($readiness['provisioned_users'] ?? 0);
        $queuedJobs = (int) ($provisioning['queued_jobs'] ?? 0);
        $runningJobs = (int) ($provisioning['running_jobs'] ?? 0);
        $completedJobs = (int) ($provisioning['completed_jobs'] ?? 0);
        $failedJobs = (int) ($provisioning['failed_jobs'] ?? 0);
        $provisioningStatus = strtolower(trim((string) ($provisioning['provisioning_status'] ?? '')));
        $provisioningSignalsStarted = $queuedJobs > 0
            || $runningJobs > 0
            || $completedJobs > 0
            || $failedJobs > 0
            || in_array($provisioningStatus, ['queued', 'running', 'failed', 'completed'], true);

        if ($status === 'suspended') {
            return [
                'code' => 'SUSPENDED',
                'label' => 'Suspended',
                'summary' => 'CRM tenant or subscription status is suspended, so Salesflow DMS cannot be opened.',
                'provisioning' => $provisioning,
            ];
        }

        if (in_array($subscriptionStatus, ['none', 'cancelled', 'expired'], true) && !$dmsEnabled) {
            return [
                'code' => 'DISCOVERED',
                'label' => 'Discovered',
                'summary' => 'Salesflow DMS is available in Marketplace as a paid add-on, but no active DMS subscription exists yet.',
                'provisioning' => $provisioning,
            ];
        }

        if ($subscriptionStatus === 'payment_pending' && !$dmsEnabled) {
            return [
                'code' => 'SELECTED',
                'label' => 'Selected',
                'summary' => 'A DMS package has been selected, but payment is still pending.',
                'provisioning' => $provisioning,
            ];
        }

        if ($subscriptionStatus === 'provisioning' && !$dmsEnabled) {
            return [
                'code' => 'PROVISIONING',
                'label' => 'Provisioning',
                'summary' => 'DMS add-on activation is provisioning and runtime access is not open yet.',
                'provisioning' => $provisioning,
            ];
        }

        if ($subscriptionStatus === 'suspended' && !$dmsEnabled) {
            return [
                'code' => 'SUSPENDED',
                'label' => 'Suspended',
                'summary' => 'The DMS add-on subscription is suspended, so Salesflow DMS cannot be opened.',
                'provisioning' => $provisioning,
            ];
        }

        if (!$dmsEnabled) {
            return [
                'code' => 'DISCOVERED',
                'label' => 'Discovered',
                'summary' => 'Salesflow DMS is available in Marketplace but does not have an active DMS add-on subscription.',
                'provisioning' => $provisioning,
            ];
        }

        if ($readinessCode === 'ACTIVE') {
            return [
                'code' => 'ACTIVE',
                'label' => 'Active',
                'summary' => 'Salesflow DMS is entitled, provisioned, and already producing field execution activity.',
                'provisioning' => $provisioning,
            ];
        }

        if (!$connectionReady || $provisionedUsers <= 0) {
            if ($provisioningSignalsStarted) {
                return [
                    'code' => 'PROVISIONING',
                    'label' => 'Provisioning',
                    'summary' => 'Salesflow DMS has been selected and provisioning is in progress. Finish connection and staff reprovision before go-live.',
                    'provisioning' => $provisioning,
                ];
            }

            return [
                'code' => 'SELECTED',
                'label' => 'Selected',
                'summary' => 'Salesflow DMS add-on is active, but tenant connection and staff provisioning have not started yet.',
                'provisioning' => $provisioning,
            ];
        }

        return [
            'code' => 'SETUP_REQUIRED',
            'label' => 'Setup Required',
            'summary' => 'Salesflow DMS is entitled and provisioned. Continue setup and first-activity verification before daily operations.',
            'provisioning' => $provisioning,
        ];
    }

    public function get_dms_operational_settings_audit()
    {
        return [
            [
                'label' => 'Check-in validation radius',
                'value' => 'Configurable in DMS source, default 200 meters',
                'evidence' => 'salesrep/app/Http/Controllers/SettingsController.php, SettingHelper::getCheckinRadius()',
            ],
            [
                'label' => 'Required photos per check-in',
                'value' => 'Configurable in DMS source, currently 3-5 photos',
                'evidence' => 'salesrep/app/Http/Controllers/SettingsController.php, CheckInRequest.php',
            ],
            [
                'label' => 'Camera mode',
                'value' => 'Configurable in DMS source, easy/strict',
                'evidence' => 'salesrep/app/Http/Controllers/SettingsController.php',
            ],
            [
                'label' => 'Check-in frequency rules',
                'value' => 'Fields exist in DMS source, not enforced yet',
                'evidence' => 'salesrep/app/Http/Controllers/SettingsController.php',
            ],
            [
                'label' => 'Nearby store search radius',
                'value' => 'Source fallback 2 km',
                'evidence' => 'salesrep/config/gps.php, StoreApiController.php',
            ],
            [
                'label' => 'Live tracking interval',
                'value' => 'Fixed at 30 seconds in DMS source',
                'evidence' => 'salesrep/resources/js/live-location-tracker.js',
            ],
            [
                'label' => 'GPS high accuracy mode',
                'value' => 'Not configurable in DMS source (client tracker uses false)',
                'evidence' => 'salesrep/resources/js/live-location-tracker.js',
            ],
            [
                'label' => 'Check-in retention cleanup',
                'value' => 'Configurable in DMS source',
                'evidence' => 'salesrep/app/Http/Controllers/SettingsController.php, CleanupOldCheckins.php',
            ],
            [
                'label' => 'Visit rules',
                'value' => 'Not configured in DMS source',
                'evidence' => 'No visit settings found in audited salesrep source',
            ],
            [
                'label' => 'Attendance rules',
                'value' => 'Not configured in DMS source',
                'evidence' => 'No attendance settings found in audited salesrep source',
            ],
        ];
    }

    public function get_dms_staff_sync_status_snapshot($tenantId)
    {
        return $this->get_dms_sync_job_status_snapshot($tenantId, 'sync_staff_to_dms');
    }

    public function get_dms_sync_job_status_snapshot($tenantId, $jobType)
    {
        $tenantId = (int) $tenantId;
        $jobType = trim((string) $jobType);
        if ($tenantId <= 0) {
            return [
                'successful_count' => 0,
                'last_successful_sync_at' => null,
                'last_job_status' => null,
                'last_job_type' => null,
                'last_job_at' => null,
            ];
        }

        $successful = $this->landlord_db()
            ->select('COUNT(*) as total, MAX(updated_at) as last_successful_sync_at', false)
            ->where('tenant_id', $tenantId)
            ->where('provider_code', 'salesflow_dms')
            ->where('job_type', $jobType)
            ->where_in('status', ['done', 'succeeded'])
            ->get(db_prefix() . 'kt_integration_sync_jobs')
            ->row_array();

        $latest = $this->landlord_db()
            ->select('job_type, status, updated_at')
            ->where('tenant_id', $tenantId)
            ->where('provider_code', 'salesflow_dms')
            ->where('job_type', $jobType)
            ->order_by('id', 'desc')
            ->limit(1)
            ->get(db_prefix() . 'kt_integration_sync_jobs')
            ->row_array();

        return [
            'successful_count' => (int) ($successful['total'] ?? 0),
            'last_successful_sync_at' => !empty($successful['last_successful_sync_at']) ? (string) $successful['last_successful_sync_at'] : null,
            'last_job_status' => !empty($latest['status']) ? (string) $latest['status'] : null,
            'last_job_type' => !empty($latest['job_type']) ? (string) $latest['job_type'] : null,
            'last_job_at' => !empty($latest['updated_at']) ? (string) $latest['updated_at'] : null,
        ];
    }

    public function sync_tenant_dms_usage_snapshot($tenantId)
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            return [];
        }

        if (!isset($this->Kt_saas_model)) {
            $this->load->model('kt_saas/Kt_saas_model');
        }

        return $this->Kt_saas_model->sync_tenant_dms_usage_snapshot($tenantId);
    }

    public function get_providers($activeOnly = false)
    {
        $db = $this->landlord_db()->from(db_prefix() . 'kt_integration_providers');
        if ($activeOnly) {
            $db->where('is_active', 1);
        }

        return $db->order_by('provider_name', 'asc')->get()->result_array();
    }

    public function get_connections($tenantId = null)
    {
        $db = $this->landlord_db()
            ->select('c.*, p.provider_name, p.provider_type, p.auth_type as provider_auth_type, p.readiness_status, p.status_message')
            ->from(db_prefix() . 'kt_integration_connections c')
            ->join(db_prefix() . 'kt_integration_providers p', 'p.provider_code = c.provider_code', 'left');

        if ($tenantId !== null) {
            $db->where('c.tenant_id', (int) $tenantId);
        }

        return $db->order_by('c.id', 'desc')->get()->result_array();
    }

    public function get_connection($id)
    {
        return $this->landlord_db()
            ->where('id', (int) $id)
            ->get(db_prefix() . 'kt_integration_connections')
            ->row_array();
    }

    public function get_salesflow_connection_for_tenant($tenantId)
    {
        return $this->get_salesflow_connection((int) $tenantId);
    }

    public function get_salesflow_connection_status($tenantId): array
    {
        $tenantId = (int) $tenantId;
        $connection = $this->get_salesflow_connection($tenantId);
        if (!$connection) {
            return [
                'configured' => false,
                'status' => 'missing',
                'message' => 'Chưa cấu hình kết nối Salesrep.',
                'missing_fields' => ['bridge_url', 'bridge_key'],
            ];
        }

        $settings = kt_integration_hub_json_decode((string) ($connection['settings_json'] ?? ''), []);
        $baseUrl = trim((string) ($settings['base_url'] ?? $connection['external_account_id'] ?? ''));
        $missing = [];
        if ($baseUrl === '') {
            $missing[] = 'bridge_url';
        }
        if (trim((string) ($connection['public_key'] ?? '')) === '') {
            $missing[] = 'bridge_key';
        }
        if (trim((string) ($connection['webhook_secret_encrypted'] ?? '')) === '') {
            $missing[] = 'bridge_secret';
        }
        $latestJob = $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->where('provider_code', 'salesflow_dms')
            ->order_by('id', 'desc')
            ->get(db_prefix() . 'kt_integration_sync_jobs')
            ->row_array();
        $latestJobStatus = strtolower(trim((string) ($latestJob['status'] ?? '')));
        $healthStatus = !empty($missing)
            ? 'incomplete'
            : (in_array($latestJobStatus, ['failed', 'retry'], true) ? 'degraded' : ($latestJobStatus === 'done' ? 'healthy' : 'configured'));

        return [
            'configured' => empty($missing),
            'status' => $healthStatus,
            'message' => empty($missing) ? 'Kết nối Salesrep đã được cấu hình.' : 'Kết nối Salesrep còn thiếu thông tin.',
            'missing_fields' => $missing,
            'connection_id' => (int) ($connection['id'] ?? 0),
            'base_url' => $baseUrl,
            'key_configured' => trim((string) ($connection['public_key'] ?? '')) !== '',
            'secret_configured' => trim((string) ($connection['webhook_secret_encrypted'] ?? '')) !== '',
            'last_successful_handshake' => (string) ($settings['last_successful_handshake'] ?? $connection['last_sync_at'] ?? ''),
            'last_failed_handshake' => (string) ($settings['last_failed_handshake'] ?? ''),
            'last_error' => (string) ($connection['last_error'] ?? ''),
            'latest_job_status' => $latestJobStatus,
            'latest_job_id' => (int) ($latestJob['id'] ?? 0),
            'config_version' => isset($this->Kt_saas_model) && method_exists($this->Kt_saas_model, 'get_dms_config_version')
                ? (int) $this->Kt_saas_model->get_dms_config_version()
                : max(1, (int) get_option('kt_saas_dms_config_version')),
        ];
    }

    public function provision_salesflow_connection($tenantId, string $baseUrl = ''): array
    {
        $tenantId = (int) $tenantId;
        $tenant = $this->get_tenant($tenantId);
        if (!$tenant) {
            return ['success' => false, 'message' => 'Không tìm thấy doanh nghiệp.'];
        }

        $existing = $this->get_salesflow_connection($tenantId);
        $baseUrl = rtrim(trim($baseUrl), '/');
        if ($baseUrl === '') {
            $host = $this->get_dms_workspace_host_for_tenant($tenantId);
            $baseUrl = $host !== '' ? 'https://' . $host : '';
        }
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => 'Địa chỉ workspace DMS không hợp lệ.'];
        }

        $secret = $existing && !empty($existing['webhook_secret_encrypted']) ? null : bin2hex(random_bytes(32));
        $result = $this->save_connection([
            'provider_code' => 'salesflow_dms',
            'connection_name' => 'Kết nối Salesrep - ' . (string) ($tenant['company_name'] ?? $tenant['tenant_code']),
            'external_account_id' => $baseUrl,
            'external_account_name' => (string) ($tenant['company_name'] ?? ''),
            'base_url' => $baseUrl,
            'webhook_secret' => $secret,
            'is_active' => 1,
        ], $tenantId, $existing ? (int) $existing['id'] : null);
        if (is_string($secret)) {
            $secret = str_repeat("\0", strlen($secret));
        }
        unset($secret);

        if (empty($result['success'])) {
            return $result;
        }
        $this->record_landlord_dms_action($tenantId, $existing ? 'salesrep_connection_updated' : 'salesrep_connection_created', [], [
            'connection_id' => (int) ($result['id'] ?? 0),
            'base_url' => $baseUrl,
            'secret_configured' => true,
            'secret_rotated' => !$existing,
        ]);

        return ['success' => true, 'message' => 'Đã lưu kết nối Salesrep an toàn.', 'connection_id' => (int) ($result['id'] ?? 0)];
    }

    public function test_salesflow_connection($tenantId): array
    {
        $tenantId = (int) $tenantId;
        $connection = $this->get_salesflow_connection($tenantId);
        $status = $this->get_salesflow_connection_status($tenantId);
        if (!$connection || empty($status['configured'])) {
            return ['success' => false, 'message' => 'Kết nối Salesrep chưa đủ URL, khóa và secret.', 'status' => $status];
        }

        $url = rtrim((string) $status['base_url'], '/') . '/login';
        $parsed = parse_url($url);
        $host = strtolower((string) ($parsed['host'] ?? ''));
        $local = str_ends_with($host, '.test') || in_array($host, ['localhost', '127.0.0.1'], true);
        if ($local) {
            $port = strtolower((string) ($parsed['scheme'] ?? 'https')) === 'https' ? 443 : 80;
            $socket = @fsockopen($host, $port, $errorCode, $error, 3);
            $success = is_resource($socket);
            if (is_resource($socket)) {
                fclose($socket);
            }
            $httpCode = $success ? 200 : 0;
        } else {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            curl_exec($curl);
            $error = curl_error($curl);
            $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            $success = $error === '' && $httpCode >= 200 && $httpCode < 500;
        }

        $settings = kt_integration_hub_json_decode((string) ($connection['settings_json'] ?? ''), []);
        $now = kt_integration_hub_now();
        if ($success) {
            $settings['last_successful_handshake'] = $now;
            $settings['last_handshake_http_status'] = $httpCode;
        } else {
            $settings['last_failed_handshake'] = $now;
            $settings['last_handshake_http_status'] = $httpCode;
        }
        $this->landlord_db()->where('id', (int) $connection['id'])->update(db_prefix() . 'kt_integration_connections', [
            'settings_json' => kt_integration_hub_json_encode($settings),
            'last_sync_at' => $success ? $now : ($connection['last_sync_at'] ?? null),
            'last_error' => $success ? null : ($error !== '' ? $error : 'HTTP ' . $httpCode),
            'updated_at' => $now,
        ]);
        $this->record_landlord_dms_action($tenantId, 'salesrep_connection_tested', [], [
            'success' => $success,
            'http_status' => $httpCode,
        ]);

        return [
            'success' => $success,
            'message' => $success ? 'Kết nối Salesrep hoạt động.' : 'Không thể kết nối Salesrep: ' . ($error !== '' ? $error : 'HTTP ' . $httpCode),
            'http_status' => $httpCode,
        ];
    }

    public function get_connection_by_public_key($providerCode, $publicKey)
    {
        return $this->landlord_db()
            ->where('provider_code', trim((string) $providerCode))
            ->where('public_key', trim((string) $publicKey))
            ->where('status', 'connected')
            ->get(db_prefix() . 'kt_integration_connections')
            ->row_array();
    }

    public function get_tenant_by_code($tenantCode)
    {
        return $this->landlord_db()
            ->where('tenant_code', trim((string) $tenantCode))
            ->where('deleted_at IS NULL', null, false)
            ->get(db_prefix() . 'kt_saas_tenants')
            ->row_array();
    }

    public function get_tenant_by_subdomain($subdomain)
    {
        return $this->landlord_db()
            ->where('subdomain', trim((string) $subdomain))
            ->where('deleted_at IS NULL', null, false)
            ->get(db_prefix() . 'kt_saas_tenants')
            ->row_array();
    }

    public function save_connection(array $data, $tenantId, $id = null)
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            return ['success' => false, 'message' => 'Tenant is required.'];
        }

        $existing = $id ? $this->get_connection((int) $id) : [];
        $providerCode = trim((string) ($data['provider_code'] ?? ($existing['provider_code'] ?? 'custom_webhook')));
        $provider = $this->get_provider($providerCode);
        if (!$provider) {
            return ['success' => false, 'message' => 'Provider is not available.'];
        }

        if (!in_array((string) ($provider['readiness_status'] ?? 'planned'), ['ready', 'beta'], true)) {
            return ['success' => false, 'message' => 'This provider is not ready yet. No connection was created.'];
        }

        if (!in_array($providerCode, ['custom_webhook', 'zalo_oa', 'tiktok_shop', 'salesflow_dms'], true)) {
            return ['success' => false, 'message' => 'This connector is not implemented yet.'];
        }

        $authType = trim((string) ($provider['auth_type'] ?? 'custom_hmac'));
        $plainSecret = $data['webhook_secret'] ?? null;
        if ($providerCode === 'zalo_oa') {
            $plainSecret = $data['app_secret'] ?? null;
        }
        if ($providerCode === 'tiktok_shop') {
            $plainSecret = $data['app_secret'] ?? null;
        }
        $generatedSecret = null;
        if ($providerCode === 'custom_webhook' && !$existing && in_array($authType, ['custom_hmac', 'hmac'], true) && trim((string) $plainSecret) === '') {
            $generatedSecret = $this->generate_webhook_secret();
            $plainSecret = $generatedSecret;
        }
        $settings = $this->connection_settings_payload($providerCode, $data, $existing);

        $payload = [
            'tenant_id' => $tenantId,
            'provider_code' => $providerCode,
            'connection_name' => trim((string) ($data['connection_name'] ?? '')),
            'external_account_id' => $this->external_account_id_payload($providerCode, $data, $existing),
            'external_account_name' => trim((string) ($data['external_account_name'] ?? '')),
            'status' => !empty($data['is_active']) ? 'connected' : 'disconnected',
            'auth_type' => $authType,
            'access_token_encrypted' => $this->merge_encrypted_value($existing, 'access_token_encrypted', $data['access_token'] ?? null),
            'refresh_token_encrypted' => $this->merge_encrypted_value($existing, 'refresh_token_encrypted', $data['refresh_token'] ?? null),
            'webhook_secret_encrypted' => $this->merge_encrypted_value($existing, 'webhook_secret_encrypted', $plainSecret),
            'settings_json' => kt_integration_hub_json_encode($settings),
            'updated_at' => kt_integration_hub_now(),
        ];

        if ($id && $existing) {
            $this->landlord_db()->where('id', (int) $id)->update(db_prefix() . 'kt_integration_connections', $payload);
            $this->log('info', 'connection.updated', 'Connection updated.', ['connection_id' => (int) $id], $tenantId, (int) $id, $payload['provider_code']);

            return ['success' => true, 'id' => (int) $id, 'generated_secret' => $generatedSecret];
        }

        $payload['public_key'] = $this->generate_public_key();
        $payload['created_at'] = kt_integration_hub_now();
        $this->landlord_db()->insert(db_prefix() . 'kt_integration_connections', $payload);
        $insertId = (int) $this->landlord_db()->insert_id();
        if ($insertId > 0 && in_array($providerCode, ['zalo_oa', 'tiktok_shop'], true)) {
            $payload['id'] = $insertId;
            $payload['settings_json'] = kt_integration_hub_json_encode($this->connection_settings_payload($providerCode, $data, $payload));
            $this->landlord_db()->where('id', $insertId)->update(db_prefix() . 'kt_integration_connections', [
                'settings_json' => $payload['settings_json'],
            ]);
        }
        $this->log('info', 'connection.created', 'Connection created.', ['connection_id' => $insertId], $tenantId, $insertId, $payload['provider_code']);

        return ['success' => $insertId > 0, 'id' => $insertId, 'generated_secret' => $generatedSecret];
    }

    public function get_provider($providerCode)
    {
        return $this->landlord_db()
            ->where('provider_code', trim((string) $providerCode))
            ->where('is_active', 1)
            ->get(db_prefix() . 'kt_integration_providers')
            ->row_array();
    }

    public function rotate_connection_secret($id, $tenantId = null)
    {
        $connection = $this->get_connection((int) $id);
        if (!$connection) {
            return ['success' => false, 'message' => 'Connection not found.'];
        }
        if ($tenantId !== null && (int) $connection['tenant_id'] !== (int) $tenantId) {
            return ['success' => false, 'message' => 'Connection not found.'];
        }
        if ((string) $connection['provider_code'] !== 'custom_webhook') {
            return ['success' => false, 'message' => 'Secret rotation is only available for Custom Webhook.'];
        }

        $secret = $this->generate_webhook_secret();
        $this->landlord_db()->where('id', (int) $id)->update(db_prefix() . 'kt_integration_connections', [
            'webhook_secret_encrypted' => kt_integration_hub_encrypt_value($secret),
            'updated_at' => kt_integration_hub_now(),
        ]);
        $this->log('warning', 'connection.secret_rotated', 'Webhook secret rotated.', ['connection_id' => (int) $id], (int) $connection['tenant_id'], (int) $id, (string) $connection['provider_code']);

        return ['success' => true, 'id' => (int) $id, 'generated_secret' => $secret];
    }

    public function disconnect_connection($id, $tenantId = null)
    {
        $connection = $this->get_connection((int) $id);
        if (!$connection) {
            return false;
        }
        if ($tenantId !== null && (int) $connection['tenant_id'] !== (int) $tenantId) {
            return false;
        }

        $this->landlord_db()
            ->where('id', (int) $id)
            ->update(db_prefix() . 'kt_integration_connections', [
                'status' => 'disconnected',
                'updated_at' => kt_integration_hub_now(),
            ]);
        $this->log('warning', 'connection.disconnected', 'Connection disconnected.', [], (int) $connection['tenant_id'], (int) $id, (string) $connection['provider_code']);

        return true;
    }

    public function verify_custom_webhook(array $connection, $rawBody, array $headers)
    {
        $secret = kt_integration_hub_decrypt_value($connection['webhook_secret_encrypted'] ?? '');
        if ($secret === '') {
            return ['success' => false, 'message' => 'Webhook secret is not configured.'];
        }

        $timestamp = trim((string) ($headers['x-kt-timestamp'] ?? $headers['X-KT-Timestamp'] ?? ''));
        if ($timestamp === '' || !ctype_digit($timestamp)) {
            return ['success' => false, 'message' => 'Missing or invalid webhook timestamp.'];
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return ['success' => false, 'message' => 'Webhook timestamp is outside the allowed window.'];
        }

        $signature = trim((string) ($headers['x-kt-signature'] ?? $headers['X-KT-Signature'] ?? ''));
        if ($signature === '') {
            return ['success' => false, 'message' => 'Missing webhook signature.'];
        }

        $provided = preg_replace('/^sha256=/i', '', $signature);
        $expected = hash_hmac('sha256', $timestamp . '.' . (string) $rawBody, $secret);
        if (!is_string($provided) || !preg_match('/^[a-f0-9]{64}$/i', $provided) || !hash_equals($expected, strtolower($provided))) {
            return ['success' => false, 'message' => 'Invalid webhook signature.'];
        }

        return ['success' => true];
    }

    public function verify_zalo_webhook(array $connection, array $payload, $rawBody, array $headers)
    {
        $signature = trim((string) ($headers['x-zevent-signature'] ?? $headers['X-ZEvent-Signature'] ?? $headers['X-Zevent-Signature'] ?? ''));
        if ($signature === '') {
            $allowUnsigned = (bool) $this->connection_setting($connection, 'allow_unsigned_test_webhook', ENVIRONMENT !== 'production');
            if (!$allowUnsigned) {
                return ['success' => false, 'status' => 'invalid', 'message' => 'Zalo signature header is not present.'];
            }

            return ['success' => true, 'status' => 'unchecked', 'message' => 'Unsigned Zalo webhook accepted for testing.'];
        }

        $secret = kt_integration_hub_decrypt_value($connection['webhook_secret_encrypted'] ?? '');
        if ($secret === '') {
            return ['success' => false, 'status' => 'invalid', 'message' => 'Zalo OA secret is not configured.'];
        }

        $appId = trim((string) ($payload['app_id'] ?? $this->connection_setting($connection, 'app_id')));
        $timestamp = trim((string) ($payload['timestamp'] ?? ''));
        if ($appId === '' || $timestamp === '') {
            return ['success' => false, 'status' => 'invalid', 'message' => 'Zalo signature cannot be verified without app_id and timestamp.'];
        }

        $provided = preg_replace('/^mac=/i', '', $signature);
        $provided = preg_replace('/^sha256=/i', '', (string) $provided);
        $provided = strtolower(trim((string) $provided));
        $expected = hash('sha256', $appId . (string) $rawBody . $timestamp . $secret);

        if (!preg_match('/^[a-f0-9]{64}$/', $provided) || !hash_equals($expected, $provided)) {
            return ['success' => false, 'status' => 'invalid', 'message' => 'Invalid Zalo webhook signature.'];
        }

        return ['success' => true, 'status' => 'valid'];
    }

    public function verify_tiktok_webhook(array $connection, array $payload, $rawBody, array $headers)
    {
        $mode = (string) $this->connection_setting($connection, 'connection_mode', 'dry_run');
        $dryRun = $mode === 'dry_run' || (bool) $this->connection_setting($connection, 'dry_run_enabled', true);
        if ($dryRun) {
            return ['success' => true, 'status' => 'unchecked', 'message' => 'Unsigned TikTok Shop dry-run webhook accepted.'];
        }

        return [
            'success' => false,
            'status' => 'invalid',
            'message' => 'TikTok Shop webhook signature verification requires Partner Center credential verification.',
        ];
    }

    public function store_webhook_event(array $connection, array $payload, array $headers, $rawBody, $signatureStatus = 'verified')
    {
        $providerCode = (string) ($connection['provider_code'] ?? 'custom_webhook');
        $externalEventId = $this->external_event_id($payload, $rawBody);
        $eventType = trim((string) ($payload['event_type'] ?? $payload['event_name'] ?? $payload['type'] ?? 'lead'));

        $existing = $this->landlord_db()
            ->where('tenant_id', (int) ($connection['tenant_id'] ?? 0))
            ->where('connection_id', (int) ($connection['id'] ?? 0))
            ->where('provider_code', $providerCode)
            ->where('external_event_id', $externalEventId)
            ->get(db_prefix() . 'kt_integration_webhook_events')
            ->row_array();
        if ($existing) {
            return ['success' => true, 'duplicate' => true, 'event_id' => (int) $existing['id']];
        }

        $record = [
            'tenant_id' => (int) ($connection['tenant_id'] ?? 0),
            'connection_id' => (int) ($connection['id'] ?? 0),
            'provider_code' => $providerCode,
            'event_type' => $eventType,
            'external_event_id' => $externalEventId,
            'signature_status' => $signatureStatus,
            'processing_status' => 'pending',
            'raw_payload' => kt_integration_hub_json_encode($payload),
            'headers_json' => kt_integration_hub_json_encode(kt_integration_hub_redact_secrets($headers)),
            'received_at' => kt_integration_hub_now(),
        ];
        $this->landlord_db()->insert(db_prefix() . 'kt_integration_webhook_events', $record);
        $eventId = (int) $this->landlord_db()->insert_id();
        if ($eventId > 0 && !empty($connection['id']) && in_array($providerCode, ['zalo_oa', 'tiktok_shop'], true)) {
            $settings = kt_integration_hub_json_decode((string) ($connection['settings_json'] ?? ''), []);
            $settings['last_webhook_at'] = kt_integration_hub_now();
            $this->landlord_db()->where('id', (int) $connection['id'])->update(db_prefix() . 'kt_integration_connections', [
                'settings_json' => kt_integration_hub_json_encode(kt_integration_hub_redact_secrets($settings)),
                'updated_at' => kt_integration_hub_now(),
            ]);
        }
        $this->queue_job_from_event($eventId, $connection, $payload, $eventType, $externalEventId);

        return ['success' => $eventId > 0, 'event_id' => $eventId];
    }

    public function queue_job_from_event($eventId, array $connection, array $payload, $eventType, $externalId)
    {
        $entityType = $this->entity_type_from_event($eventType, $payload);
        $jobType = 'upsert_' . $entityType;
        $tenantId = (int) ($connection['tenant_id'] ?? 0);
        if ((string) ($connection['provider_code'] ?? '') === 'zalo_oa') {
            $normalized = $this->normalize_zalo_payload($payload);
            $entityType = 'zalo_user';
            $jobType = $normalized['event_type'] === 'follow' ? 'lead_candidate' : 'inbound_message';
            $externalId = $normalized['external_message_id'] ?: ($normalized['external_user_id'] ?: (string) $externalId);
        }
        if ((string) ($connection['provider_code'] ?? '') === 'tiktok_shop') {
            $normalized = $this->normalize_tiktok_order_event($payload);
            $entityType = 'tiktok_order';
            $jobType = 'tiktok_order_event';
            $externalId = $normalized['external_order_id'] ?: (string) $externalId;
        }

        $existing = $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->where('provider_code', (string) $connection['provider_code'])
            ->where('job_type', $jobType)
            ->where('external_id', (string) $externalId)
            ->get(db_prefix() . 'kt_integration_sync_jobs')
            ->row_array();
        if ($existing) {
            if ((string) ($connection['provider_code'] ?? '') === 'tiktok_shop' && in_array((string) ($existing['status'] ?? ''), ['done', 'failed'], true)) {
                $this->landlord_db()->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_integration_sync_jobs', [
                    'webhook_event_id' => (int) $eventId,
                    'payload_json' => kt_integration_hub_json_encode($payload),
                    'status' => 'queued',
                    'attempts' => 0,
                    'error_message' => null,
                    'available_at' => kt_integration_hub_now(),
                    'updated_at' => kt_integration_hub_now(),
                ]);
            }
            return (int) $existing['id'];
        }

        $this->landlord_db()->insert(db_prefix() . 'kt_integration_sync_jobs', [
            'tenant_id' => $tenantId,
            'connection_id' => (int) ($connection['id'] ?? 0),
            'webhook_event_id' => (int) $eventId,
            'provider_code' => (string) ($connection['provider_code'] ?? 'custom_webhook'),
            'job_type' => $jobType,
            'entity_type' => $entityType,
            'external_id' => (string) $externalId,
            'status' => 'queued',
            'attempts' => 0,
            'max_attempts' => 5,
            'payload_json' => kt_integration_hub_json_encode($payload),
            'available_at' => kt_integration_hub_now(),
            'created_at' => kt_integration_hub_now(),
            'updated_at' => kt_integration_hub_now(),
        ]);

        return (int) $this->landlord_db()->insert_id();
    }

    public function process_due_jobs($limit = 50)
    {
        $this->beat_integration_service('crm_integration_cron', 'running', ['batch_limit' => (int) $limit]);
        $jobs = $this->claim_due_jobs($limit);
        $summary = ['checked' => count($jobs), 'done' => 0, 'failed' => 0, 'retry' => 0];

        foreach ($jobs as $job) {
            $result = $this->process_job($job);
            if (!empty($result['success'])) {
                $summary['done']++;
            } elseif (($result['status'] ?? '') === 'retry') {
                $summary['retry']++;
            } else {
                $summary['failed']++;
            }
        }

        $this->beat_integration_service('crm_integration_cron', 'healthy', $summary);
        return $summary;
    }

    public function beat_integration_service($service, $status = 'healthy', array $safeMeta = []): void
    {
        $table = db_prefix() . 'kt_integration_heartbeats';
        $db = $this->landlord_db();
        $db->query("CREATE TABLE IF NOT EXISTS `{$table}` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`service` VARCHAR(64) NOT NULL,`instance_id` VARCHAR(191) NOT NULL,`status` VARCHAR(32) NOT NULL DEFAULT 'healthy',`started_at` DATETIME NULL,`last_seen_at` DATETIME NOT NULL,`last_success_at` DATETIME NULL,`current_job_id` BIGINT UNSIGNED NULL,`safe_meta_json` TEXT NULL,`created_at` DATETIME NULL,`updated_at` DATETIME NULL,PRIMARY KEY (`id`),UNIQUE KEY `service` (`service`),KEY `last_seen_at` (`last_seen_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $now = kt_integration_hub_now();
        $row = $db->where('service', trim((string) $service))->get($table)->row_array();
        $payload = [
            'instance_id' => php_uname('n') . ':' . getmypid(),
            'status' => trim((string) $status) ?: 'healthy',
            'last_seen_at' => $now,
            'last_success_at' => $status === 'healthy' ? $now : ($row['last_success_at'] ?? null),
            'safe_meta_json' => kt_integration_hub_json_encode(kt_integration_hub_redact_secrets($safeMeta)),
            'updated_at' => $now,
        ];
        if ($row) {
            $db->where('id', (int) $row['id'])->update($table, $payload);
        } else {
            $payload['service'] = trim((string) $service);
            $payload['started_at'] = $now;
            $payload['created_at'] = $now;
            $db->insert($table, $payload);
        }
    }

    public function get_integration_heartbeat_health($staleAfterSeconds = 120): array
    {
        $table = db_prefix() . 'kt_integration_heartbeats';
        if (!$this->landlord_db()->table_exists($table)) {
            return ['status' => 'missing', 'services' => []];
        }
        $now = time();
        $services = [];
        foreach ($this->landlord_db()->order_by('service')->get($table)->result_array() as $row) {
            $age = max(0, $now - (strtotime((string) $row['last_seen_at']) ?: 0));
            $row['age_seconds'] = $age;
            $row['status'] = $age > max(30, (int) $staleAfterSeconds) ? 'stale' : (string) $row['status'];
            unset($row['safe_meta_json']);
            $services[] = $row;
        }
        return ['status' => array_filter($services, fn ($row) => $row['status'] !== 'healthy') ? 'degraded' : 'healthy', 'services' => $services];
    }

    public function claim_due_jobs($limit = 50)
    {
        $rows = $this->landlord_db()
            ->where_in('status', ['queued', 'retry'])
            ->group_start()
                ->where('available_at IS NULL', null, false)
                ->or_where('available_at <=', kt_integration_hub_now())
            ->group_end()
            ->order_by('id', 'asc')
            ->limit(max((int) $limit, 1))
            ->get(db_prefix() . 'kt_integration_sync_jobs')
            ->result_array();

        $claimed = [];
        foreach ($rows as $row) {
            $this->landlord_db()
                ->where('id', (int) $row['id'])
                ->where_in('status', ['queued', 'retry'])
                ->update(db_prefix() . 'kt_integration_sync_jobs', [
                    'status' => 'processing',
                    'locked_at' => kt_integration_hub_now(),
                    'updated_at' => kt_integration_hub_now(),
                ]);
            if ($this->landlord_db()->affected_rows() > 0) {
                $claimed[] = $row;
            }
        }

        return $claimed;
    }

    public function process_job(array $job)
    {
        $this->begin_sync_attempt($job);
        try {
            if ((string) ($job['provider_code'] ?? '') === 'salesflow_dms' && (string) ($job['job_type'] ?? '') === 'sync_staff_to_dms') {
                $connection = $this->get_connection((int) ($job['connection_id'] ?? 0));
                $tenant = $this->get_tenant((int) ($job['tenant_id'] ?? 0));
                if (!$connection || !$tenant) {
                    throw new RuntimeException('Salesflow DMS staff sync job is missing tenant or connection.');
                }

                $syncJob = new SyncStaffToDmsJob();
                $result = $syncJob->handle($tenant, $connection, $job);
                $response = (array) ($result['response'] ?? []);
                $data = (array) ($response['data'] ?? []);
                $staffId = (int) ($job['external_id'] ?? 0);
                $dmsUserId = trim((string) ($data['dms_user_id'] ?? ''));

                if ($dmsUserId !== '') {
                    $this->upsert_entity_link(
                        (int) $job['tenant_id'],
                        'salesflow_dms',
                        (int) ($job['connection_id'] ?? 0),
                        'staff',
                        $staffId,
                        $dmsUserId,
                        [
                            'payload' => kt_integration_hub_json_decode((string) ($job['payload_json'] ?? ''), []),
                            'response' => $response,
                            'request_id' => (string) ($job['request_id'] ?? ''),
                            'status' => (string) ($result['status'] ?? 'synced'),
                        ]
                    );
                }

                if ((string) ($result['status'] ?? '') === 'conflict') {
                    $this->log('warning', 'staff.sync.conflict', 'DMS staff sync hit email conflict.', [
                        'job_id' => (int) $job['id'],
                        'staff_id' => $staffId,
                        'request_id' => (string) ($job['request_id'] ?? ''),
                        'response_code' => (string) ($response['code'] ?? ''),
                    ], (int) $job['tenant_id'], (int) ($job['connection_id'] ?? 0), 'salesflow_dms');
                }

                $this->sync_tenant_dms_usage_snapshot((int) $job['tenant_id']);
                $this->update_dms_staff_access_sync_result(
                    (int) $job['tenant_id'],
                    $staffId,
                    'done'
                );

                return $this->mark_job_done($job, [
                    'status' => (string) ($result['status'] ?? 'synced'),
                    'response' => $response,
                ]);
            }

            if ((string) ($job['provider_code'] ?? '') === 'salesflow_dms' && (string) ($job['job_type'] ?? '') === 'sync_customer_to_dms') {
                $connection = $this->get_connection((int) ($job['connection_id'] ?? 0));
                $tenant = $this->get_tenant((int) ($job['tenant_id'] ?? 0));
                if (!$connection || !$tenant) {
                    throw new RuntimeException('Salesflow DMS customer sync job is missing tenant or connection.');
                }

                $syncJob = new SyncCustomerToDmsJob();
                $result = $syncJob->handle($tenant, $connection, $job);
                $response = (array) ($result['response'] ?? []);
                $data = (array) ($response['data'] ?? []);
                $customerId = (int) ($job['external_id'] ?? 0);
                $dmsCustomerRefId = trim((string) ($data['dms_customer_ref_id'] ?? ''));

                if ($dmsCustomerRefId !== '') {
                    $this->upsert_entity_link(
                        (int) $job['tenant_id'],
                        'salesflow_dms',
                        (int) ($job['connection_id'] ?? 0),
                        'customer',
                        $customerId,
                        $dmsCustomerRefId,
                        [
                            'payload' => kt_integration_hub_json_decode((string) ($job['payload_json'] ?? ''), []),
                            'response' => $response,
                            'request_id' => (string) ($job['request_id'] ?? ''),
                            'status' => (string) ($result['status'] ?? 'synced'),
                        ]
                    );
                }

                if ((string) ($result['status'] ?? '') === 'conflict') {
                    $this->log('warning', 'customer.sync.conflict', 'DMS customer sync hit mapping conflict.', [
                        'job_id' => (int) $job['id'],
                        'customer_id' => $customerId,
                        'request_id' => (string) ($job['request_id'] ?? ''),
                        'response_code' => (string) ($response['code'] ?? ''),
                    ], (int) $job['tenant_id'], (int) ($job['connection_id'] ?? 0), 'salesflow_dms');
                }

                $this->sync_tenant_dms_usage_snapshot((int) $job['tenant_id']);

                return $this->mark_job_done($job, [
                    'status' => (string) ($result['status'] ?? 'synced'),
                    'response' => $response,
                ]);
            }

            if ((string) ($job['provider_code'] ?? '') === 'salesflow_dms' && (string) ($job['job_type'] ?? '') === 'sync_product_to_dms') {
                $connection = $this->get_connection((int) ($job['connection_id'] ?? 0));
                $tenant = $this->get_tenant((int) ($job['tenant_id'] ?? 0));
                if (!$connection || !$tenant) {
                    throw new RuntimeException('Salesflow DMS product sync job is missing tenant or connection.');
                }

                $syncJob = new SyncProductToDmsJob();
                $result = $syncJob->handle($tenant, $connection, $job);
                $response = (array) ($result['response'] ?? []);
                $data = (array) ($response['data'] ?? []);
                $productId = (int) ($job['external_id'] ?? 0);
                $dmsProductId = trim((string) ($data['dms_product_id'] ?? ''));

                if ($dmsProductId !== '') {
                    $this->upsert_entity_link(
                        (int) $job['tenant_id'],
                        'salesflow_dms',
                        (int) ($job['connection_id'] ?? 0),
                        'product',
                        $productId,
                        $dmsProductId,
                        [
                            'payload' => kt_integration_hub_json_decode((string) ($job['payload_json'] ?? ''), []),
                            'response' => $response,
                            'request_id' => (string) ($job['request_id'] ?? ''),
                            'status' => (string) ($result['status'] ?? 'synced'),
                        ]
                    );
                }

                if ((string) ($result['status'] ?? '') === 'conflict') {
                    $this->log('warning', 'product.sync.conflict', 'DMS product sync hit SKU conflict.', [
                        'job_id' => (int) $job['id'],
                        'product_id' => $productId,
                        'request_id' => (string) ($job['request_id'] ?? ''),
                        'response_code' => (string) ($response['code'] ?? ''),
                    ], (int) $job['tenant_id'], (int) ($job['connection_id'] ?? 0), 'salesflow_dms');
                }

                $this->sync_tenant_dms_usage_snapshot((int) $job['tenant_id']);

                return $this->mark_job_done($job, [
                    'status' => (string) ($result['status'] ?? 'synced'),
                    'response' => $response,
                ]);
            }

            if ((string) ($job['provider_code'] ?? '') === 'salesflow_dms' && (string) ($job['job_type'] ?? '') === 'sync_warehouse_to_dms') {
                $connection = $this->get_connection((int) ($job['connection_id'] ?? 0));
                $tenant = $this->get_tenant((int) ($job['tenant_id'] ?? 0));
                if (!$connection || !$tenant) {
                    throw new RuntimeException('Salesflow DMS warehouse catalog sync job is missing tenant or connection.');
                }

                $syncJob = new SyncWarehouseToDmsJob();
                $result = $syncJob->handle($tenant, $connection, $job);
                $response = (array) ($result['response'] ?? []);
                $data = (array) ($response['data'] ?? []);
                $warehouseId = (int) ($job['external_id'] ?? 0);
                $dmsWarehouseId = trim((string) ($data['dms_warehouse_id'] ?? ''));

                if ($dmsWarehouseId !== '') {
                    $this->upsert_entity_link(
                        (int) $job['tenant_id'],
                        'salesflow_dms',
                        (int) ($job['connection_id'] ?? 0),
                        'warehouse',
                        $warehouseId,
                        $dmsWarehouseId,
                        [
                            'payload' => kt_integration_hub_json_decode((string) ($job['payload_json'] ?? ''), []),
                            'response' => $response,
                            'request_id' => (string) ($job['request_id'] ?? ''),
                            'status' => (string) ($result['status'] ?? 'synced'),
                            'inventory_authority' => 'dms_warehouse_ledger',
                        ]
                    );
                }

                if ((string) ($result['status'] ?? '') === 'conflict') {
                    $this->log('warning', 'warehouse.sync.conflict', 'DMS warehouse catalog sync hit code conflict.', [
                        'job_id' => (int) $job['id'],
                        'warehouse_id' => $warehouseId,
                        'request_id' => (string) ($job['request_id'] ?? ''),
                        'response_code' => (string) ($response['code'] ?? ''),
                    ], (int) $job['tenant_id'], (int) ($job['connection_id'] ?? 0), 'salesflow_dms');
                }

                $this->sync_tenant_dms_usage_snapshot((int) $job['tenant_id']);

                return $this->mark_job_done($job, [
                    'status' => (string) ($result['status'] ?? 'synced'),
                    'response' => $response,
                ]);
            }

            if ((string) ($job['provider_code'] ?? '') === 'tiktok_shop') {
                $payload = kt_integration_hub_json_decode((string) ($job['payload_json'] ?? ''), []);
                $connection = $this->get_connection((int) ($job['connection_id'] ?? 0));
                $orderId = (string) ($job['external_id'] ?? '');
                $channelOrderId = $this->write_tiktok_order_staging((int) $job['tenant_id'], (int) ($job['connection_id'] ?? 0), $payload, $connection ?: [], $orderId);

                return $this->mark_job_done($job, ['channel_order_id' => $channelOrderId]);
            }

            if ((string) ($job['provider_code'] ?? '') === 'zalo_oa') {
                $payload = kt_integration_hub_json_decode((string) ($job['payload_json'] ?? ''), []);
                $normalized = $this->normalize_zalo_payload($payload);
                if (trim((string) ($normalized['external_user_id'] ?? '')) === '') {
                    return $this->mark_job_done($job, ['ignored' => true, 'message' => 'Zalo event has no user id.']);
                }
                if ((string) ($normalized['event_type'] ?? '') === 'follow') {
                    $connection = $this->get_connection((int) ($job['connection_id'] ?? 0));
                    $createLeadOnFollow = (bool) $this->connection_setting($connection ?: [], 'create_lead_on_follow', true);
                    if (!$createLeadOnFollow) {
                        return $this->mark_job_done($job, ['ignored' => true, 'message' => 'Follow event stored without lead creation.']);
                    }
                }
                $leadId = $this->write_zalo_lead_to_tenant((int) $job['tenant_id'], (int) ($job['connection_id'] ?? 0), $payload);

                return $this->mark_job_done($job, ['lead_id' => $leadId]);
            }

            $entityType = (string) ($job['entity_type'] ?? '');
            if ($entityType !== 'lead') {
                return $this->mark_job_done($job, ['message' => 'Stored event only. No writer for this entity yet.']);
            }

            $payload = kt_integration_hub_json_decode((string) ($job['payload_json'] ?? ''), []);
            $leadId = $this->write_lead_to_tenant((int) $job['tenant_id'], (string) $job['provider_code'], (int) ($job['connection_id'] ?? 0), (string) ($job['external_id'] ?? ''), $payload);

            return $this->mark_job_done($job, ['lead_id' => $leadId]);
        } catch (Throwable $e) {
            $safeError = kt_integration_hub_sanitize_error_message($e->getMessage());
            if ((string) ($job['provider_code'] ?? '') === 'salesflow_dms'
                && (string) ($job['job_type'] ?? '') === 'sync_staff_to_dms') {
                $this->update_dms_staff_access_sync_result(
                    (int) ($job['tenant_id'] ?? 0),
                    (int) ($job['external_id'] ?? 0),
                    'failed',
                    $safeError
                );
            }
            return $this->mark_job_failed_or_retry($job, $safeError);
        }
    }

    public function get_jobs($tenantId = null, $limit = 200)
    {
        $db = $this->landlord_db()->from(db_prefix() . 'kt_integration_sync_jobs');
        if ($tenantId !== null) {
            $db->where('tenant_id', (int) $tenantId);
        }

        return $db->order_by('id', 'desc')->limit((int) $limit)->get()->result_array();
    }

    public function get_logs($tenantId = null, $limit = 200)
    {
        $db = $this->landlord_db()->from(db_prefix() . 'kt_integration_logs');
        if ($tenantId !== null) {
            $db->where('tenant_id', (int) $tenantId);
        }

        return $db->order_by('id', 'desc')->limit((int) $limit)->get()->result_array();
    }

    public function has_recent_request_log($providerCode, $tenantId, $requestId, $event = 'api.request.accepted', $minutes = 10)
    {
        $providerCode = trim((string) $providerCode);
        $requestId = trim((string) $requestId);
        if ($providerCode === '' || $requestId === '') {
            return false;
        }

        $db = $this->landlord_db()
            ->where('provider_code', $providerCode)
            ->where('event', trim((string) $event))
            ->where('created_at >=', date('Y-m-d H:i:s', time() - (max((int) $minutes, 1) * 60)))
            ->like('context_json', '"request_id":"' . $this->landlord_db()->escape_like_str($requestId) . '"', 'both', false);

        if ($tenantId !== null) {
            $db->where('tenant_id', (int) $tenantId);
        }

        return $db->count_all_results(db_prefix() . 'kt_integration_logs') > 0;
    }

    public function get_events($tenantId = null, $limit = 100)
    {
        $db = $this->landlord_db()->from(db_prefix() . 'kt_integration_webhook_events');
        if ($tenantId !== null) {
            $db->where('tenant_id', (int) $tenantId);
        }

        return $db->order_by('id', 'desc')->limit((int) $limit)->get()->result_array();
    }

    public function get_channel_orders($tenantId = null, $limit = 200)
    {
        $db = $this->landlord_db()
            ->select('o.*, c.connection_name, c.external_account_name')
            ->from(db_prefix() . 'kt_integration_channel_orders o')
            ->join(db_prefix() . 'kt_integration_connections c', 'c.id = o.connection_id', 'left');
        if ($tenantId !== null) {
            $db->where('o.tenant_id', (int) $tenantId);
        }

        return $db->order_by('o.id', 'desc')->limit((int) $limit)->get()->result_array();
    }

    public function get_channel_order($id, $tenantId = null)
    {
        $db = $this->landlord_db()->where('id', (int) $id);
        if ($tenantId !== null) {
            $db->where('tenant_id', (int) $tenantId);
        }
        $order = $db->get(db_prefix() . 'kt_integration_channel_orders')->row_array();
        if (!$order) {
            return null;
        }
        $order['items'] = $this->landlord_db()
            ->where('channel_order_id', (int) $order['id'])
            ->order_by('id', 'asc')
            ->get(db_prefix() . 'kt_integration_channel_order_items')
            ->result_array();

        return $order;
    }

    public function retry_job($id, $tenantId = null)
    {
        $db = $this->landlord_db()->where('id', (int) $id);
        if ($tenantId !== null) {
            $db->where('tenant_id', (int) $tenantId);
        }

        $db->update(db_prefix() . 'kt_integration_sync_jobs', [
            'status' => 'retry',
            'available_at' => kt_integration_hub_now(),
            'error_message' => null,
            'locked_at' => null,
            'updated_at' => kt_integration_hub_now(),
        ]);

        return $this->landlord_db()->affected_rows() > 0;
    }

    public function log($level, $event, $message, array $context = [], $tenantId = null, $connectionId = null, $providerCode = null)
    {
        $this->landlord_db()->insert(db_prefix() . 'kt_integration_logs', [
            'tenant_id' => $tenantId !== null ? (int) $tenantId : null,
            'connection_id' => $connectionId !== null ? (int) $connectionId : null,
            'provider_code' => $providerCode !== null ? (string) $providerCode : null,
            'level' => trim((string) $level) ?: 'info',
            'event' => trim((string) $event),
            'message' => trim((string) $message),
            'context_json' => kt_integration_hub_json_encode(kt_integration_hub_redact_secrets($context)),
            'created_at' => kt_integration_hub_now(),
        ]);
    }

    public function find_log_by_context_value($providerCode, $tenantId, $contextKey, $contextValue, $minutes = 43200)
    {
        $providerCode = trim((string) $providerCode);
        $contextKey = trim((string) $contextKey);
        $contextValue = trim((string) $contextValue);
        if ($providerCode === '' || $contextKey === '' || $contextValue === '') {
            return null;
        }

        $db = $this->landlord_db()
            ->where('provider_code', $providerCode)
            ->where('created_at >=', date('Y-m-d H:i:s', time() - (max((int) $minutes, 1) * 60)))
            ->like('context_json', '"' . $this->landlord_db()->escape_like_str($contextKey) . '":"' . $this->landlord_db()->escape_like_str($contextValue) . '"', 'both', false)
            ->order_by('id', 'desc');

        if ($tenantId !== null) {
            $db->where('tenant_id', (int) $tenantId);
        }

        $row = $db->get(db_prefix() . 'kt_integration_logs')->row_array();
        if (!$row) {
            return null;
        }

        $row['context'] = kt_integration_hub_json_decode((string) ($row['context_json'] ?? ''), []);

        return $row;
    }

    public function get_dms_order_bridge($tenantId, $dmsOrderId)
    {
        $tenantId = (int) $tenantId;
        $dmsOrderId = (int) $dmsOrderId;
        if ($tenantId <= 0 || $dmsOrderId <= 0) {
            return null;
        }

        $row = $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->where('provider_code', 'salesflow_dms')
            ->where('dms_order_id', $dmsOrderId)
            ->get(db_prefix() . 'dms_order_bridge')
            ->row_array();

        return $row ?: null;
    }

    public function upsert_dms_order_bridge($tenantId, array $payload)
    {
        $tenantId = (int) $tenantId;
        $dmsOrderId = (int) ($payload['dms_order_id'] ?? 0);
        if ($tenantId <= 0 || $dmsOrderId <= 0) {
            return false;
        }

        $record = [
            'provider_code' => trim((string) ($payload['provider_code'] ?? 'salesflow_dms')) ?: 'salesflow_dms',
            'dms_order_number' => trim((string) ($payload['dms_order_number'] ?? '')),
            'crm_invoice_id' => !empty($payload['crm_invoice_id']) ? (int) $payload['crm_invoice_id'] : null,
            'bridge_status' => trim((string) ($payload['bridge_status'] ?? 'pending')) ?: 'pending',
            'invoice_status_snapshot' => trim((string) ($payload['invoice_status_snapshot'] ?? '')) ?: null,
            'last_payload_checksum' => trim((string) ($payload['last_payload_checksum'] ?? '')) ?: null,
            'last_synced_at' => $payload['last_synced_at'] ?? kt_integration_hub_now(),
            'cancel_requested_at' => $payload['cancel_requested_at'] ?? null,
            'cancelled_at' => $payload['cancelled_at'] ?? null,
            'cancel_reason' => trim((string) ($payload['cancel_reason'] ?? '')) ?: null,
            'cancel_status' => trim((string) ($payload['cancel_status'] ?? '')) ?: null,
            'error_code' => trim((string) ($payload['error_code'] ?? '')) ?: null,
            'error_message' => trim((string) ($payload['error_message'] ?? '')) ?: null,
            'updated_at' => kt_integration_hub_now(),
        ];

        $existing = $this->get_dms_order_bridge($tenantId, $dmsOrderId);
        if ($existing) {
            $this->landlord_db()->where('id', (int) $existing['id'])->update(db_prefix() . 'dms_order_bridge', $record);

            return true;
        }

        $record['tenant_id'] = $tenantId;
        $record['dms_order_id'] = $dmsOrderId;
        $record['created_at'] = kt_integration_hub_now();
        $this->landlord_db()->insert(db_prefix() . 'dms_order_bridge', $record);

        return $this->landlord_db()->affected_rows() > 0;
    }

    public function upsert_dms_order_projection_from_payload($tenantId, array $order, array $bridgePayload = [], array $sourcePayload = [])
    {
        $tenantId = (int) $tenantId;
        $dmsOrderId = (int) ($order['order_id'] ?? ($bridgePayload['dms_order_id'] ?? 0));
        if ($tenantId <= 0 || $dmsOrderId <= 0 || !$this->ensure_dms_projection_tables()) {
            return false;
        }

        $companyId = (int) ($sourcePayload['tenant']['crm_company_id'] ?? ($order['crm_company_id'] ?? $tenantId));
        $invoiceId = (int) ($bridgePayload['crm_invoice_id'] ?? ($order['crm_invoice_id'] ?? 0));
        $crmPayment = $this->get_crm_invoice_payment_snapshot($invoiceId);
        $paymentSource = 'unknown';
        $invoiceStatus = trim((string) ($bridgePayload['invoice_status_snapshot'] ?? ($order['crm_invoice_status'] ?? ''))) ?: null;
        $invoiceTotal = null;
        $paidAmount = null;
        $balanceAmount = null;
        $paymentStatus = trim((string) ($order['crm_payment_status'] ?? '')) ?: null;

        if ($crmPayment !== null) {
            $invoiceStatus = trim((string) ($crmPayment['invoice_status'] ?? $invoiceStatus)) ?: $invoiceStatus;
            $invoiceTotal = array_key_exists('total_amount', $crmPayment) ? (float) $crmPayment['total_amount'] : null;
            $paidAmount = array_key_exists('paid_amount', $crmPayment) ? (float) $crmPayment['paid_amount'] : null;
            $balanceAmount = array_key_exists('balance_amount', $crmPayment) ? (float) $crmPayment['balance_amount'] : null;
            $paymentStatus = trim((string) ($crmPayment['payment_status'] ?? $paymentStatus)) ?: $paymentStatus;
            $paymentSource = 'crm.invoice';
        } elseif (array_key_exists('crm_paid_amount', $order) || array_key_exists('crm_balance_amount', $order) || trim((string) ($order['crm_payment_status'] ?? '')) !== '') {
            $paidAmount = array_key_exists('crm_paid_amount', $order) && $order['crm_paid_amount'] !== null ? (float) $order['crm_paid_amount'] : null;
            $balanceAmount = array_key_exists('crm_balance_amount', $order) && $order['crm_balance_amount'] !== null ? (float) $order['crm_balance_amount'] : null;
            $paymentSource = 'salesrep.readback';
        }

        $orderTotal = null;
        foreach (['grand_total', 'total_amount', 'order_total', 'total'] as $key) {
            if (array_key_exists($key, $order) && $order[$key] !== null && $order[$key] !== '') {
                $orderTotal = (float) $order[$key];
                break;
            }
        }

        $submittedAt = $this->normalize_projection_datetime($order['submitted_at'] ?? ($order['ordered_at'] ?? ($order['order_date'] ?? ($order['created_at'] ?? null))));
        $syncedAt = $this->normalize_projection_datetime($bridgePayload['last_synced_at'] ?? ($order['updated_at'] ?? null)) ?: kt_integration_hub_now();
        $checksumPayload = [
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'order' => $order,
            'bridge' => $bridgePayload,
            'payment_source' => $paymentSource,
            'invoice_status' => $invoiceStatus,
            'paid_amount' => $paidAmount,
            'balance_amount' => $balanceAmount,
        ];
        $checksum = sha1(kt_integration_hub_json_encode($checksumPayload));
        $now = kt_integration_hub_now();
        $record = [
            'company_id' => $companyId > 0 ? $companyId : $tenantId,
            'dms_workspace_host' => $this->get_dms_workspace_host_for_tenant($tenantId),
            'source' => 'salesrep',
            'source_record_type' => 'order',
            'projection_version' => 1,
            'sync_status' => trim((string) ($bridgePayload['bridge_status'] ?? 'synced')) ?: 'synced',
            'dms_order_id' => $dmsOrderId,
            'dms_order_number' => trim((string) ($order['order_number'] ?? ($bridgePayload['dms_order_number'] ?? ''))) ?: null,
            'crm_invoice_id' => $invoiceId > 0 ? $invoiceId : null,
            'store_id' => !empty($order['store_id']) ? (int) $order['store_id'] : null,
            'staff_id' => !empty($order['crm_staff_id']) ? (int) $order['crm_staff_id'] : null,
            'order_status' => trim((string) ($order['status'] ?? '')) ?: null,
            'order_total' => $orderTotal,
            'currency' => trim((string) ($order['currency'] ?? 'VND')) ?: 'VND',
            'invoice_status' => $invoiceStatus,
            'invoice_total' => $invoiceTotal,
            'payment_status' => $paymentStatus,
            'paid_amount' => $paidAmount,
            'balance_amount' => $balanceAmount,
            'payment_source' => $paymentSource,
            'submitted_at' => $submittedAt,
            'approved_at' => $this->normalize_projection_datetime($order['approved_at'] ?? null),
            'synced_at' => $syncedAt,
            'last_readback_at' => $paymentSource !== 'unknown' ? $syncedAt : null,
            'last_synced_at' => $syncedAt,
            'checksum' => $checksum,
            'metadata_json' => kt_integration_hub_json_encode(kt_integration_hub_redact_secrets([
                'contract' => 'dms_order_projection_v1',
                'payment_precedence' => 'crm_invoice_then_dms_readback_then_unknown',
                'dms_order_number' => trim((string) ($order['order_number'] ?? ($bridgePayload['dms_order_number'] ?? ''))),
            ])),
            'updated_at' => $now,
        ];

        $existing = $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->where('source_record_type', 'order')
            ->where('source_record_id', $dmsOrderId)
            ->where('projection_version', 1)
            ->get(db_prefix() . 'dms_order_projection')
            ->row_array();

        if ($existing) {
            $this->landlord_db()->where('id', (int) $existing['id'])->update(db_prefix() . 'dms_order_projection', $record);
        } else {
            $record['tenant_id'] = $tenantId;
            $record['source_record_id'] = $dmsOrderId;
            $record['created_at'] = $now;
            $this->landlord_db()->insert(db_prefix() . 'dms_order_projection', $record);
        }

        $this->upsert_dms_location_projection_for_order($tenantId, $companyId > 0 ? $companyId : $tenantId, $order, $record);

        return true;
    }

    public function backfill_dms_order_location_projections($tenantId = null, $dryRun = true)
    {
        if (!$this->ensure_dms_projection_tables() || !$this->dms_table_exists('dms_order_bridge')) {
            return ['success' => false, 'message' => 'Projection or bridge tables are unavailable.', 'processed' => 0, 'would_write' => 0];
        }

        $query = $this->landlord_db()
            ->select('tenant_id, dms_order_id, dms_order_number, crm_invoice_id, bridge_status, invoice_status_snapshot, last_payload_checksum, last_synced_at, created_at, updated_at')
            ->where('provider_code', 'salesflow_dms')
            ->order_by('tenant_id', 'asc')
            ->order_by('dms_order_id', 'asc');
        if ($tenantId !== null) {
            $query->where('tenant_id', (int) $tenantId);
        }

        $rows = $query->get(db_prefix() . 'dms_order_bridge')->result_array();
        $processed = 0;
        $wouldWrite = 0;
        $written = 0;
        foreach ($rows as $bridge) {
            $processed++;
            $tenant = (int) ($bridge['tenant_id'] ?? 0);
            $orderId = (int) ($bridge['dms_order_id'] ?? 0);
            if ($tenant <= 0 || $orderId <= 0) {
                continue;
            }
            $order = $this->get_salesrep_order_for_projection($tenant, $orderId);
            if (empty($order)) {
                $order = [
                    'order_id' => $orderId,
                    'order_number' => (string) ($bridge['dms_order_number'] ?? ''),
                    'status' => (string) ($bridge['bridge_status'] ?? ''),
                    'updated_at' => (string) (($bridge['last_synced_at'] ?? '') ?: ($bridge['updated_at'] ?? '')),
                ];
            }
            $wouldWrite++;
            if (!$dryRun) {
                $this->upsert_dms_order_projection_from_payload($tenant, $order, $bridge, ['tenant' => ['crm_company_id' => $tenant]]);
                $written++;
            }
        }

        return [
            'success' => true,
            'dry_run' => (bool) $dryRun,
            'processed' => $processed,
            'would_write' => $wouldWrite,
            'written' => $written,
        ];
    }

    public function upsert_dms_inventory_projection(array $payload)
    {
        return $this->upsert_projection_record('dms_inventory_projection', $payload, [
            'source_record_type' => 'inventory',
            'required' => ['tenant_id', 'company_id', 'source_record_id'],
            'fields' => [
                'company_id', 'dms_workspace_host', 'store_id', 'store_name', 'product_id', 'product_name', 'sku',
                'stock_on_hand', 'reserved_quantity', 'low_stock_threshold', 'is_low_stock', 'movement_type',
                'movement_quantity', 'movement_at', 'reference_order_id', 'staff_id', 'source', 'source_record_type',
                'projection_version', 'sync_status', 'last_synced_at', 'checksum', 'metadata_json',
            ],
        ]);
    }

    public function upsert_dms_product_sales_projection(array $payload)
    {
        return $this->upsert_projection_record('dms_product_sales_projection', $payload, [
            'source_record_type' => 'product_sales',
            'required' => ['tenant_id', 'company_id', 'source_record_id'],
            'fields' => [
                'company_id', 'dms_workspace_host', 'product_id', 'product_name', 'sku', 'sold_quantity_day',
                'sold_amount_day', 'sold_quantity_month', 'sold_amount_month', 'order_count_day', 'order_count_month',
                'source', 'source_record_type', 'projection_version', 'sync_status', 'last_synced_at', 'checksum', 'metadata_json',
            ],
        ]);
    }

    public function upsert_dms_staff_activity_projection(array $payload)
    {
        return $this->upsert_projection_record('dms_staff_activity_projection', $payload, [
            'source_record_type' => 'staff_activity',
            'required' => ['tenant_id', 'company_id', 'source_record_id', 'staff_id'],
            'fields' => [
                'company_id', 'dms_workspace_host', 'staff_id', 'staff_name', 'crm_staff_id', 'manager_staff_id',
                'role_code', 'online_state', 'last_seen_at', 'last_checkin_at', 'last_visit_at', 'today_checkin_count',
                'today_visit_count', 'today_order_count', 'today_order_amount', 'missed_visit_count', 'source',
                'source_record_type', 'projection_version', 'sync_status', 'last_synced_at', 'checksum', 'metadata_json',
            ],
        ]);
    }

    public function upsert_dms_store_coverage_projection(array $payload)
    {
        return $this->upsert_projection_record('dms_store_coverage_projection', $payload, [
            'source_record_type' => 'store_coverage',
            'required' => ['tenant_id', 'company_id', 'source_record_id', 'store_id'],
            'fields' => [
                'company_id', 'dms_workspace_host', 'store_id', 'store_name', 'crm_customer_id', 'assigned_staff_id',
                'visited_today', 'visited_this_month', 'last_visit_at', 'last_checkin_at', 'today_order_count',
                'month_order_count', 'coverage_date', 'coverage_month', 'source', 'source_record_type',
                'projection_version', 'sync_status', 'last_synced_at', 'checksum', 'metadata_json',
            ],
        ]);
    }

    public function upsert_dms_field_feedback_projection(array $payload)
    {
        return $this->upsert_projection_record('dms_field_feedback_projection', $payload, [
            'source_record_type' => 'field_feedback',
            'required' => ['tenant_id', 'company_id', 'source_record_id'],
            'fields' => [
                'company_id', 'dms_workspace_host', 'source', 'source_record_type', 'projection_version',
                'sync_status', 'last_synced_at', 'checksum', 'feedback_type', 'severity', 'status',
                'staff_name', 'staff_email', 'store_name', 'store_external_id', 'title', 'description_summary',
                'submitted_at', 'reviewed_at', 'resolved_at', 'latitude', 'longitude', 'gps_accuracy',
                'photo_count', 'metadata_json',
            ],
        ]);
    }

    public function get_dms_field_feedback($tenantId, array $filters = [], $limit = 25, $offset = 0)
    {
        $tenantId = (int) $tenantId;
        $limit = max(1, min(100, (int) $limit));
        $offset = max(0, (int) $offset);
        if ($tenantId <= 0 || !$this->ensure_dms_projection_tables() || !$this->dms_table_exists('dms_field_feedback_projection')) {
            return ['rows' => [], 'total' => 0];
        }

        $apply = function ($query) use ($tenantId, $filters) {
            $query->from(db_prefix() . 'dms_field_feedback_projection')->where('tenant_id', $tenantId);
            foreach (['feedback_type', 'status', 'severity'] as $field) {
                $value = trim((string) ($filters[$field] ?? ''));
                if ($value !== '') {
                    $query->where($field, $value);
                }
            }
            if (!empty($filters['date_from'])) {
                $query->where('submitted_at >=', trim((string) $filters['date_from']) . ' 00:00:00');
            }
            if (!empty($filters['date_to'])) {
                $query->where('submitted_at <=', trim((string) $filters['date_to']) . ' 23:59:59');
            }

            return $query;
        };

        $total = (int) $apply($this->landlord_db())->count_all_results();
        $rows = $apply($this->landlord_db())
            ->select('id, source_record_id, feedback_type, severity, status, staff_name, staff_email, store_name, store_external_id, title, description_summary, submitted_at, reviewed_at, resolved_at, latitude, longitude, gps_accuracy, photo_count, sync_status, last_synced_at')
            ->order_by('submitted_at', 'desc')
            ->order_by('id', 'desc')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        return ['rows' => $rows, 'total' => $total];
    }

    public function get_dms_field_feedback_summary($tenantId, array $filters = [])
    {
        $summary = ['total' => 0, 'new' => 0, 'in_review' => 0, 'resolved' => 0, 'urgent' => 0, 'with_location' => 0, 'with_photo' => 0, 'by_type' => []];
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0 || !$this->ensure_dms_projection_tables() || !$this->dms_table_exists('dms_field_feedback_projection')) {
            return $summary;
        }

        $base = function ($query) use ($tenantId, $filters) {
            $query->from(db_prefix() . 'dms_field_feedback_projection')->where('tenant_id', $tenantId);
            if (!empty($filters['date_from'])) {
                $query->where('submitted_at >=', trim((string) $filters['date_from']) . ' 00:00:00');
            }
            if (!empty($filters['date_to'])) {
                $query->where('submitted_at <=', trim((string) $filters['date_to']) . ' 23:59:59');
            }

            return $query;
        };

        $summary['total'] = (int) $base($this->landlord_db())->count_all_results();
        foreach (['new', 'in_review', 'resolved'] as $status) {
            $summary[$status] = (int) $base($this->landlord_db())->where('status', $status)->count_all_results();
        }
        $summary['urgent'] = (int) $base($this->landlord_db())->where('severity', 'urgent')->count_all_results();
        $summary['with_location'] = (int) $base($this->landlord_db())->where('latitude IS NOT NULL', null, false)->where('longitude IS NOT NULL', null, false)->count_all_results();
        $summary['with_photo'] = (int) $base($this->landlord_db())->where('photo_count >', 0)->count_all_results();
        $typeRows = $base($this->landlord_db())->select('feedback_type, COUNT(*) as total')->group_by('feedback_type')->get()->result_array();
        foreach ($typeRows as $row) {
            $summary['by_type'][(string) ($row['feedback_type'] ?? '')] = (int) ($row['total'] ?? 0);
        }

        return $summary;
    }

    public function backfill_dms_inventory_product_projections($tenantId = null, $dryRun = true)
    {
        if (!$this->ensure_dms_projection_tables()) {
            return ['success' => false, 'message' => 'Projection tables unavailable.', 'processed' => 0, 'written' => 0];
        }

        $tenantIds = $this->projection_backfill_tenant_ids($tenantId);
        $processed = 0;
        $written = 0;
        foreach ($tenantIds as $id) {
            $inventoryRows = $this->fetch_salesrep_inventory_projection_rows($id);
            foreach ($inventoryRows as $row) {
                $processed++;
                if (!$dryRun && $this->upsert_dms_inventory_projection($row)) {
                    $written++;
                }
            }
            $productRows = $this->fetch_salesrep_product_sales_projection_rows($id);
            foreach ($productRows as $row) {
                $processed++;
                if (!$dryRun && $this->upsert_dms_product_sales_projection($row)) {
                    $written++;
                }
            }
        }

        return ['success' => true, 'dry_run' => (bool) $dryRun, 'tenants' => count($tenantIds), 'processed' => $processed, 'written' => $written];
    }

    public function backfill_dms_staff_store_coverage_projections($tenantId = null, $dryRun = true)
    {
        if (!$this->ensure_dms_projection_tables()) {
            return ['success' => false, 'message' => 'Projection tables unavailable.', 'processed' => 0, 'written' => 0];
        }

        $tenantIds = $this->projection_backfill_tenant_ids($tenantId);
        $processed = 0;
        $written = 0;
        foreach ($tenantIds as $id) {
            $staffRows = $this->build_staff_activity_projection_rows($id);
            foreach ($staffRows as $row) {
                $processed++;
                if (!$dryRun && $this->upsert_dms_staff_activity_projection($row)) {
                    $written++;
                }
            }
            $storeRows = $this->fetch_salesrep_store_coverage_projection_rows($id);
            foreach ($storeRows as $row) {
                $processed++;
                if (!$dryRun && $this->upsert_dms_store_coverage_projection($row)) {
                    $written++;
                }
            }
        }

        return ['success' => true, 'dry_run' => (bool) $dryRun, 'tenants' => count($tenantIds), 'processed' => $processed, 'written' => $written];
    }

    public function upsert_provider_entity_link($tenantId, $providerCode, $connectionId, $entityType, $localId, $externalId, array $payload, $direction = null)
    {
        $this->upsert_entity_link($tenantId, $providerCode, $connectionId, $entityType, $localId, $externalId, $payload, $direction);
    }

    public function queue_staff_sync_job($tenantId, $staffId, $connectionId = null)
    {
        $tenantId = (int) $tenantId;
        $staffId = (int) $staffId;
        if ($tenantId <= 0 || $staffId <= 0) {
            return ['success' => false, 'message' => 'Tenant id and staff id are required.'];
        }

        $connection = $connectionId ? $this->get_connection((int) $connectionId) : $this->get_salesflow_connection($tenantId);
        if (!$connection) {
            return ['success' => false, 'message' => 'Salesflow DMS connection is not configured for this tenant.'];
        }

        $tenant = $this->get_tenant($tenantId);
        if (!$tenant) {
            return ['success' => false, 'message' => 'Tenant not found.'];
        }

        $payload = $this->build_staff_sync_payload($tenant, $staffId);
        $identity = (array) ($payload['identity'] ?? []);
        $producer = new StaffPayloadProducer();
        $requestId = $producer->buildRequestId($payload);
        $idempotencyKey = $producer->buildIdempotencyKey($payload);

        $existing = $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->where('provider_code', 'salesflow_dms')
            ->where('job_type', 'sync_staff_to_dms')
            ->where('external_id', (string) $staffId)
            ->get(db_prefix() . 'kt_integration_sync_jobs')
            ->row_array();

        $record = [
            'connection_id' => (int) ($connection['id'] ?? 0),
            'provider_code' => 'salesflow_dms',
            'job_type' => 'sync_staff_to_dms',
            'entity_type' => 'staff',
            'external_id' => (string) $staffId,
            'idempotency_key' => $idempotencyKey,
            'request_id' => $requestId,
            'correlation_id' => $requestId,
            'source_stable_key' => (string) ($identity['source_stable_key'] ?? ''),
            'source_fingerprint' => (string) ($identity['source_fingerprint'] ?? ''),
            'source_version' => (int) ($identity['source_version'] ?? 0),
            'status' => 'queued',
            'attempts' => $existing ? (int) ($existing['attempts'] ?? 0) : 0,
            'max_attempts' => 5,
            'payload_json' => kt_integration_hub_json_encode($payload),
            'result_json' => null,
            'error_message' => null,
            'available_at' => kt_integration_hub_now(),
            'updated_at' => kt_integration_hub_now(),
        ];

        if ($existing) {
            $this->landlord_db()->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_integration_sync_jobs', $record);

            return ['success' => true, 'job_id' => (int) $existing['id'], 'request_id' => $requestId];
        }

        $record['tenant_id'] = $tenantId;
        $record['created_at'] = kt_integration_hub_now();
        $this->landlord_db()->insert(db_prefix() . 'kt_integration_sync_jobs', $record);
        $jobId = (int) $this->landlord_db()->insert_id();

        return ['success' => $jobId > 0, 'job_id' => $jobId, 'request_id' => $requestId];
    }

    public function queue_customer_sync_job($tenantId, $customerId, $connectionId = null)
    {
        $tenantId = (int) $tenantId;
        $customerId = (int) $customerId;
        if ($tenantId <= 0 || $customerId <= 0) {
            return ['success' => false, 'message' => 'Tenant id and customer id are required.'];
        }

        $connection = $connectionId ? $this->get_connection((int) $connectionId) : $this->get_salesflow_connection($tenantId);
        if (!$connection) {
            return ['success' => false, 'message' => 'Salesflow DMS connection is not configured for this tenant.'];
        }

        $tenant = $this->get_tenant($tenantId);
        if (!$tenant) {
            return ['success' => false, 'message' => 'Tenant not found.'];
        }

        $payload = $this->build_customer_sync_payload($tenant, $customerId);
        $identity = (array) ($payload['identity'] ?? []);
        $producer = new CustomerPayloadProducer();
        $requestId = $producer->buildRequestId($payload);
        $idempotencyKey = $producer->buildIdempotencyKey($payload);

        $existing = $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->where('provider_code', 'salesflow_dms')
            ->where('job_type', 'sync_customer_to_dms')
            ->where('external_id', (string) $customerId)
            ->get(db_prefix() . 'kt_integration_sync_jobs')
            ->row_array();

        $record = [
            'connection_id' => (int) ($connection['id'] ?? 0),
            'provider_code' => 'salesflow_dms',
            'job_type' => 'sync_customer_to_dms',
            'entity_type' => 'customer',
            'external_id' => (string) $customerId,
            'idempotency_key' => $idempotencyKey,
            'request_id' => $requestId,
            'correlation_id' => $requestId,
            'source_stable_key' => (string) ($identity['source_stable_key'] ?? ''),
            'source_fingerprint' => (string) ($identity['source_fingerprint'] ?? ''),
            'source_version' => (int) ($identity['source_version'] ?? 0),
            'status' => 'queued',
            'attempts' => $existing ? (int) ($existing['attempts'] ?? 0) : 0,
            'max_attempts' => 5,
            'payload_json' => kt_integration_hub_json_encode($payload),
            'result_json' => null,
            'error_message' => null,
            'available_at' => kt_integration_hub_now(),
            'updated_at' => kt_integration_hub_now(),
        ];

        if ($existing) {
            $this->landlord_db()->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_integration_sync_jobs', $record);

            return ['success' => true, 'job_id' => (int) $existing['id'], 'request_id' => $requestId];
        }

        $record['tenant_id'] = $tenantId;
        $record['created_at'] = kt_integration_hub_now();
        $this->landlord_db()->insert(db_prefix() . 'kt_integration_sync_jobs', $record);
        $jobId = (int) $this->landlord_db()->insert_id();

        return ['success' => $jobId > 0, 'job_id' => $jobId, 'request_id' => $requestId];
    }

    public function queue_product_sync_job($tenantId, $productId, $connectionId = null)
    {
        $tenantId = (int) $tenantId;
        $productId = (int) $productId;
        if ($tenantId <= 0 || $productId <= 0) {
            return ['success' => false, 'message' => 'Tenant id and product id are required.'];
        }

        $connection = $connectionId ? $this->get_connection((int) $connectionId) : $this->get_salesflow_connection($tenantId);
        if (!$connection) {
            return ['success' => false, 'message' => 'Salesflow DMS connection is not configured for this tenant.'];
        }

        $tenant = $this->get_tenant($tenantId);
        if (!$tenant) {
            return ['success' => false, 'message' => 'Tenant not found.'];
        }

        $payload = $this->build_product_sync_payload($tenant, $productId);
        $identity = (array) ($payload['identity'] ?? []);
        $producer = new ProductPayloadProducer();
        $requestId = $producer->buildRequestId($payload);
        $idempotencyKey = $producer->buildIdempotencyKey($payload);

        $existing = $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->where('provider_code', 'salesflow_dms')
            ->where('job_type', 'sync_product_to_dms')
            ->where('external_id', (string) $productId)
            ->get(db_prefix() . 'kt_integration_sync_jobs')
            ->row_array();

        $record = [
            'connection_id' => (int) ($connection['id'] ?? 0),
            'provider_code' => 'salesflow_dms',
            'job_type' => 'sync_product_to_dms',
            'entity_type' => 'product',
            'external_id' => (string) $productId,
            'idempotency_key' => $idempotencyKey,
            'request_id' => $requestId,
            'correlation_id' => $requestId,
            'source_stable_key' => (string) ($identity['source_stable_key'] ?? ''),
            'source_fingerprint' => (string) ($identity['source_fingerprint'] ?? ''),
            'source_version' => (int) ($identity['source_version'] ?? 0),
            'status' => 'queued',
            'attempts' => $existing ? (int) ($existing['attempts'] ?? 0) : 0,
            'max_attempts' => 5,
            'payload_json' => kt_integration_hub_json_encode($payload),
            'result_json' => null,
            'error_message' => null,
            'available_at' => kt_integration_hub_now(),
            'updated_at' => kt_integration_hub_now(),
        ];

        if ($existing) {
            $this->landlord_db()->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_integration_sync_jobs', $record);

            return ['success' => true, 'job_id' => (int) $existing['id'], 'request_id' => $requestId];
        }

        $record['tenant_id'] = $tenantId;
        $record['created_at'] = kt_integration_hub_now();
        $this->landlord_db()->insert(db_prefix() . 'kt_integration_sync_jobs', $record);
        $jobId = (int) $this->landlord_db()->insert_id();

        return ['success' => $jobId > 0, 'job_id' => $jobId, 'request_id' => $requestId];
    }

    public function queue_warehouse_sync_job($tenantId, $warehouseId, $connectionId = null)
    {
        $tenantId = (int) $tenantId;
        $warehouseId = (int) $warehouseId;
        if ($tenantId <= 0 || $warehouseId <= 0) {
            return ['success' => false, 'message' => 'Tenant id and warehouse id are required.'];
        }

        $connection = $connectionId ? $this->get_connection((int) $connectionId) : $this->get_salesflow_connection($tenantId);
        if (!$connection) {
            return ['success' => false, 'message' => 'Salesflow DMS connection is not configured for this tenant.'];
        }

        $tenant = $this->get_tenant($tenantId);
        if (!$tenant) {
            return ['success' => false, 'message' => 'Tenant not found.'];
        }

        $payload = $this->build_warehouse_sync_payload($tenant, $warehouseId);
        $identity = (array) ($payload['identity'] ?? []);
        $producer = new WarehousePayloadProducer();
        $requestId = $producer->buildRequestId($payload);
        $idempotencyKey = $producer->buildIdempotencyKey($payload);

        $existing = $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->where('provider_code', 'salesflow_dms')
            ->where('job_type', 'sync_warehouse_to_dms')
            ->where('external_id', (string) $warehouseId)
            ->get(db_prefix() . 'kt_integration_sync_jobs')
            ->row_array();

        $record = [
            'connection_id' => (int) ($connection['id'] ?? 0),
            'provider_code' => 'salesflow_dms',
            'job_type' => 'sync_warehouse_to_dms',
            'entity_type' => 'warehouse',
            'external_id' => (string) $warehouseId,
            'idempotency_key' => $idempotencyKey,
            'request_id' => $requestId,
            'correlation_id' => $requestId,
            'source_stable_key' => (string) ($identity['source_stable_key'] ?? ''),
            'source_fingerprint' => (string) ($identity['source_fingerprint'] ?? ''),
            'source_version' => (int) ($identity['source_version'] ?? 0),
            'status' => 'queued',
            'attempts' => $existing ? (int) ($existing['attempts'] ?? 0) : 0,
            'max_attempts' => 5,
            'payload_json' => kt_integration_hub_json_encode($payload),
            'result_json' => null,
            'error_message' => null,
            'available_at' => kt_integration_hub_now(),
            'updated_at' => kt_integration_hub_now(),
        ];

        if ($existing) {
            $this->landlord_db()->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_integration_sync_jobs', $record);

            return ['success' => true, 'job_id' => (int) $existing['id'], 'request_id' => $requestId];
        }

        $record['tenant_id'] = $tenantId;
        $record['created_at'] = kt_integration_hub_now();
        $this->landlord_db()->insert(db_prefix() . 'kt_integration_sync_jobs', $record);
        $jobId = (int) $this->landlord_db()->insert_id();

        return ['success' => $jobId > 0, 'job_id' => $jobId, 'request_id' => $requestId];
    }

    private function write_lead_to_tenant($tenantId, $providerCode, $connectionId, $externalId, array $payload)
    {
        $existingLink = $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->where('provider_code', $providerCode)
            ->where('entity_type', 'lead')
            ->where('external_id', $externalId)
            ->get(db_prefix() . 'kt_integration_entity_links')
            ->row_array();
        if ($existingLink) {
            return (int) $existingLink['local_id'];
        }

        $tenant = $this->get_tenant($tenantId);
        if (!$tenant) {
            throw new RuntimeException('Tenant not found.');
        }

        $landlordDb = $this->landlord_db();
        $CI = &get_instance();
        $previousDb = $CI->db;
        $normalized = $this->normalize_lead_payload($payload, $providerCode);

        if (!class_exists('DatabaseSwitcher', false)) {
            require_once module_dir_path('kt_saas', 'tenant_bootstrap/DatabaseSwitcher.php');
        }

        $switcher = new DatabaseSwitcher();
        $switchResult = $switcher->switchConnection($tenant);
        if (empty($switchResult['switched'])) {
            throw new RuntimeException('Tenant DB switch failed: ' . (string) ($switchResult['message'] ?? 'unknown'));
        }

        $this->db = $CI->db;
        try {
            $leadId = $this->upsert_tenant_lead($normalized, $payload);
        } finally {
            $CI->db = $landlordDb ?: $previousDb;
            $this->db = $CI->db;
            $CI->config->set_item('kt_saas_landlord_db', $landlordDb ?: $previousDb);
        }

        $this->landlord_db()->insert(db_prefix() . 'kt_integration_entity_links', [
            'tenant_id' => (int) $tenantId,
            'provider_code' => $providerCode,
            'connection_id' => $connectionId ?: null,
            'entity_type' => 'lead',
            'local_id' => (int) $leadId,
            'external_id' => $externalId,
            'external_hash' => sha1(kt_integration_hub_json_encode($payload)),
            'last_synced_at' => kt_integration_hub_now(),
            'created_at' => kt_integration_hub_now(),
            'updated_at' => kt_integration_hub_now(),
        ]);

        return (int) $leadId;
    }

    private function write_zalo_lead_to_tenant($tenantId, $connectionId, array $payload)
    {
        $normalized = $this->normalize_zalo_payload($payload);
        $externalUserId = trim((string) ($normalized['external_user_id'] ?? ''));
        if ($externalUserId === '') {
            throw new RuntimeException('Zalo user id is missing.');
        }

        $existingLink = $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->where('provider_code', 'zalo_oa')
            ->where('entity_type', 'zalo_user')
            ->where('external_id', $externalUserId)
            ->get(db_prefix() . 'kt_integration_entity_links')
            ->row_array();
        if ($existingLink) {
            $leadId = (int) $existingLink['local_id'];
            $this->link_zalo_message($tenantId, $connectionId, $leadId, $normalized, $payload);
            $this->log('info', 'zalo.lead_reused', 'Existing Zalo lead reused.', [
                'lead_id' => $leadId,
                'external_user_id' => $externalUserId,
                'external_message_id' => $normalized['external_message_id'] ?? '',
            ], (int) $tenantId, (int) $connectionId, 'zalo_oa');

            return $leadId;
        }

        $tenant = $this->get_tenant($tenantId);
        if (!$tenant) {
            throw new RuntimeException('Tenant not found.');
        }

        $connection = $this->get_connection($connectionId);
        $settings = kt_integration_hub_json_decode((string) ($connection['settings_json'] ?? ''), []);
        $landlordDb = $this->landlord_db();
        $CI = &get_instance();
        $previousDb = $CI->db;

        if (!class_exists('DatabaseSwitcher', false)) {
            require_once module_dir_path('kt_saas', 'tenant_bootstrap/DatabaseSwitcher.php');
        }

        $switcher = new DatabaseSwitcher();
        $switchResult = $switcher->switchConnection($tenant);
        if (empty($switchResult['switched'])) {
            throw new RuntimeException('Tenant DB switch failed: ' . (string) ($switchResult['message'] ?? 'unknown'));
        }

        $this->db = $CI->db;
        try {
            $leadId = $this->create_zalo_tenant_lead($normalized, $payload, $settings);
        } finally {
            $CI->db = $landlordDb ?: $previousDb;
            $this->db = $CI->db;
            $CI->config->set_item('kt_saas_landlord_db', $landlordDb ?: $previousDb);
        }

        $this->landlord_db()->insert(db_prefix() . 'kt_integration_entity_links', [
            'tenant_id' => (int) $tenantId,
            'provider_code' => 'zalo_oa',
            'connection_id' => $connectionId ?: null,
            'entity_type' => 'zalo_user',
            'local_id' => (int) $leadId,
            'external_id' => $externalUserId,
            'external_hash' => sha1(kt_integration_hub_json_encode($payload)),
            'last_synced_at' => kt_integration_hub_now(),
            'created_at' => kt_integration_hub_now(),
            'updated_at' => kt_integration_hub_now(),
        ]);
        $this->link_zalo_message($tenantId, $connectionId, $leadId, $normalized, $payload);

        return (int) $leadId;
    }

    private function create_zalo_tenant_lead(array $normalized, array $rawPayload, array $settings)
    {
        $statusId = (int) ($settings['lead_status'] ?? 0);
        if ($statusId <= 0) {
            $statusId = $this->default_lead_status_id();
        }
        $sourceName = trim((string) ($settings['default_lead_source'] ?? 'Zalo OA')) ?: 'Zalo OA';
        $sourceId = (int) ($settings['lead_source'] ?? 0);
        if ($sourceId <= 0) {
            $sourceId = $this->integration_lead_source_id($sourceName);
        }

        $externalUserId = trim((string) ($normalized['external_user_id'] ?? ''));
        $messageText = trim((string) ($normalized['message_text'] ?? ''));
        $description = "Nguồn: Zalo OA\n";
        $description .= "Zalo User ID: " . $externalUserId . "\n";
        if (!empty($normalized['external_message_id'])) {
            $description .= "Zalo Message ID: " . (string) $normalized['external_message_id'] . "\n";
        }
        if ($messageText !== '') {
            $description .= "Tin nhắn: " . $messageText . "\n";
        } elseif ((string) ($normalized['event_type'] ?? '') === 'click_message_button') {
            $description .= "Người dùng bấm Nhắn tin trên Zalo OA.\n";
        } elseif ((string) ($normalized['event_type'] ?? '') === 'follow') {
            $description .= "Người dùng quan tâm Zalo OA.\n";
        }
        $description .= "\nIntegration payload:\n" . kt_integration_hub_json_encode(kt_integration_hub_redact_secrets($rawPayload));

        $this->db->insert(db_prefix() . 'leads', [
            'hash' => app_generate_hash(),
            'name' => trim((string) ($normalized['name'] ?? '')) ?: 'Zalo User ' . $externalUserId,
            'company' => '',
            'description' => nl2br($description),
            'assigned' => (int) ($settings['lead_assigned'] ?? 0),
            'dateadded' => kt_integration_hub_now(),
            'status' => $statusId,
            'source' => $sourceId,
            'addedfrom' => get_staff_user_id() ?: 0,
            'email' => '',
            'phonenumber' => '',
            'is_public' => 0,
            'country' => 0,
            'address' => '',
        ]);

        $leadId = (int) $this->db->insert_id();
        if ($leadId <= 0) {
            throw new RuntimeException('Unable to create tenant lead.');
        }

        if ($this->db->table_exists(db_prefix() . 'lead_activity_log')) {
            $this->db->insert(db_prefix() . 'lead_activity_log', [
                'leadid' => $leadId,
                'description' => 'Integration Hub lead created from Zalo OA',
                'date' => kt_integration_hub_now(),
                'staffid' => get_staff_user_id() ?: 0,
                'additional_data' => '',
                'custom_activity' => 1,
            ]);
        }

        return $leadId;
    }

    private function link_zalo_message($tenantId, $connectionId, $leadId, array $normalized, array $payload)
    {
        $messageId = trim((string) ($normalized['external_message_id'] ?? ''));
        if ($messageId === '') {
            return;
        }

        $exists = $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->where('provider_code', 'zalo_oa')
            ->where('entity_type', 'zalo_message')
            ->where('external_id', $messageId)
            ->count_all_results(db_prefix() . 'kt_integration_entity_links') > 0;
        if ($exists) {
            return;
        }

        $this->landlord_db()->insert(db_prefix() . 'kt_integration_entity_links', [
            'tenant_id' => (int) $tenantId,
            'provider_code' => 'zalo_oa',
            'connection_id' => $connectionId ?: null,
            'entity_type' => 'zalo_message',
            'local_id' => (int) $leadId,
            'external_id' => $messageId,
            'external_hash' => sha1(kt_integration_hub_json_encode($payload)),
            'last_synced_at' => kt_integration_hub_now(),
            'created_at' => kt_integration_hub_now(),
            'updated_at' => kt_integration_hub_now(),
        ]);
    }

    private function write_tiktok_order_staging($tenantId, $connectionId, array $payload, array $connection, $externalOrderId = '')
    {
        $event = $this->normalize_tiktok_order_event($payload);
        $detailPayload = is_array($payload['mock_order_detail'] ?? null) ? $payload['mock_order_detail'] : $payload;
        if (empty($payload['mock_order_detail'])) {
            $client = $this->tiktok_client();
            $detailResult = $client->fetchOrderDetail($connection, $externalOrderId ?: $event['external_order_id'], $payload);
            if (!empty($detailResult['success']) && is_array($detailResult['order'] ?? null)) {
                $detailPayload = $detailResult['order'];
            }
        }
        $detail = $this->normalize_tiktok_order_detail($detailPayload);
        if ($detail['external_order_id'] === '' && $externalOrderId !== '') {
            $detail['external_order_id'] = (string) $externalOrderId;
        }
        if ($detail['external_order_id'] === '' && $event['external_order_id'] !== '') {
            $detail['external_order_id'] = $event['external_order_id'];
        }
        if ($detail['external_order_id'] === '') {
            throw new RuntimeException('TikTok order id is missing.');
        }

        if (empty($payload['mock_order_detail']) && (string) $this->connection_setting($connection, 'connection_mode', 'dry_run') !== 'dry_run') {
            throw new RuntimeException('TikTok real order detail pull is pending Partner Center endpoint verification.');
        }

        if ($detail['order_status'] === '' && $event['order_status'] !== '') {
            $detail['order_status'] = $event['order_status'];
        }

        $now = kt_integration_hub_now();
        $record = [
            'tenant_id' => (int) $tenantId,
            'connection_id' => (int) $connectionId,
            'provider_code' => 'tiktok_shop',
            'external_order_id' => $detail['external_order_id'],
            'external_order_code' => $detail['order_code'],
            'order_status' => $detail['order_status'],
            'payment_status' => $detail['payment_status'],
            'fulfillment_status' => $detail['fulfillment_status'],
            'buyer_name' => $detail['buyer_name'],
            'buyer_phone_masked' => $this->mask_phone($detail['buyer_phone']),
            'buyer_email' => $detail['buyer_email'],
            'currency' => $detail['currency'] ?: 'VND',
            'subtotal' => (float) $detail['subtotal'],
            'shipping_fee' => (float) $detail['shipping_fee'],
            'discount_total' => (float) $detail['discount_total'],
            'grand_total' => (float) $detail['grand_total'],
            'ordered_at' => $detail['ordered_at'] ?: null,
            'synced_at' => $now,
            'raw_json' => kt_integration_hub_json_encode(kt_integration_hub_redact_secrets($payload)),
            'mapping_status' => 'unmapped',
            'updated_at' => $now,
        ];

        $existing = $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->where('provider_code', 'tiktok_shop')
            ->where('external_order_id', $detail['external_order_id'])
            ->get(db_prefix() . 'kt_integration_channel_orders')
            ->row_array();

        if ($existing) {
            $this->landlord_db()->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_integration_channel_orders', $record);
            $channelOrderId = (int) $existing['id'];
        } else {
            $record['created_at'] = $now;
            $this->landlord_db()->insert(db_prefix() . 'kt_integration_channel_orders', $record);
            $channelOrderId = (int) $this->landlord_db()->insert_id();
        }

        if ($channelOrderId <= 0) {
            throw new RuntimeException('Unable to upsert TikTok channel order.');
        }

        $this->landlord_db()->where('channel_order_id', $channelOrderId)->delete(db_prefix() . 'kt_integration_channel_order_items');
        foreach ($detail['items'] as $item) {
            $this->landlord_db()->insert(db_prefix() . 'kt_integration_channel_order_items', [
                'tenant_id' => (int) $tenantId,
                'channel_order_id' => $channelOrderId,
                'provider_code' => 'tiktok_shop',
                'external_item_id' => (string) ($item['external_item_id'] ?? ''),
                'external_sku_id' => (string) ($item['external_sku_id'] ?? ''),
                'sku' => (string) ($item['sku'] ?? ''),
                'item_name' => (string) ($item['item_name'] ?? ''),
                'quantity' => (float) ($item['quantity'] ?? 0),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'total_price' => (float) ($item['total_price'] ?? 0),
                'raw_json' => kt_integration_hub_json_encode(kt_integration_hub_redact_secrets($item['raw'] ?? $item)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->upsert_entity_link((int) $tenantId, 'tiktok_shop', (int) $connectionId, 'tiktok_order', $channelOrderId, $detail['external_order_id'], $payload);
        if ($detail['buyer_phone'] !== '' || $detail['buyer_email'] !== '') {
            $customerKey = $detail['buyer_email'] !== '' ? $detail['buyer_email'] : $detail['buyer_phone'];
            $this->upsert_entity_link((int) $tenantId, 'tiktok_shop', (int) $connectionId, 'tiktok_customer', $channelOrderId, $customerKey, [
                'buyer_name' => $detail['buyer_name'],
                'buyer_phone_present' => $detail['buyer_phone'] !== '',
                'buyer_email_present' => $detail['buyer_email'] !== '',
            ]);
        }

        $this->log('info', 'tiktok.order_staged', 'TikTok Shop order staged.', [
            'channel_order_id' => $channelOrderId,
            'external_order_id' => $detail['external_order_id'],
            'order_status' => $detail['order_status'],
            'grand_total' => $detail['grand_total'],
        ], (int) $tenantId, (int) $connectionId, 'tiktok_shop');

        return $channelOrderId;
    }

    private function normalize_tiktok_order_event(array $payload)
    {
        return [
            'source_provider' => 'tiktok_shop',
            'event_id' => (string) ($payload['event_id'] ?? $payload['id'] ?? ''),
            'event_type' => (string) ($payload['event_type'] ?? $payload['type'] ?? 'tiktok_order_event'),
            'external_order_id' => (string) ($payload['order_id'] ?? $payload['order']['id'] ?? $payload['external_order_id'] ?? ''),
            'shop_id' => (string) ($payload['shop_id'] ?? $payload['shop_cipher'] ?? ''),
            'order_status' => (string) ($payload['status'] ?? $payload['order_status'] ?? ''),
            'timestamp' => (string) ($payload['timestamp'] ?? ''),
            'raw' => $payload,
        ];
    }

    private function normalize_tiktok_order_detail(array $payload)
    {
        $buyer = is_array($payload['buyer'] ?? null) ? $payload['buyer'] : [];
        $items = [];
        foreach ((array) ($payload['items'] ?? $payload['line_items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $items[] = [
                'external_item_id' => (string) ($item['item_id'] ?? $item['product_id'] ?? ''),
                'external_sku_id' => (string) ($item['sku_id'] ?? ''),
                'sku' => (string) ($item['sku'] ?? $item['seller_sku'] ?? ''),
                'item_name' => (string) ($item['name'] ?? $item['item_name'] ?? $item['product_name'] ?? ''),
                'quantity' => (float) ($item['quantity'] ?? 0),
                'unit_price' => (float) ($item['unit_price'] ?? $item['price'] ?? 0),
                'total_price' => (float) ($item['total_price'] ?? 0),
                'raw' => $item,
            ];
        }

        return [
            'source_provider' => 'tiktok_shop',
            'external_order_id' => (string) ($payload['order_id'] ?? $payload['id'] ?? ''),
            'order_code' => (string) ($payload['order_code'] ?? $payload['order_id'] ?? $payload['id'] ?? ''),
            'order_status' => (string) ($payload['status'] ?? $payload['order_status'] ?? ''),
            'payment_status' => (string) ($payload['payment_status'] ?? ''),
            'fulfillment_status' => (string) ($payload['fulfillment_status'] ?? ''),
            'buyer_name' => (string) ($buyer['name'] ?? $payload['buyer_name'] ?? ''),
            'buyer_phone' => (string) ($buyer['phone'] ?? $payload['buyer_phone'] ?? ''),
            'buyer_email' => (string) ($buyer['email'] ?? $payload['buyer_email'] ?? ''),
            'currency' => (string) ($payload['currency'] ?? 'VND'),
            'subtotal' => (float) ($payload['subtotal'] ?? 0),
            'shipping_fee' => (float) ($payload['shipping_fee'] ?? 0),
            'discount_total' => (float) ($payload['discount_total'] ?? 0),
            'grand_total' => (float) ($payload['grand_total'] ?? 0),
            'ordered_at' => (string) ($payload['ordered_at'] ?? ''),
            'items' => $items,
            'raw' => $payload,
        ];
    }

    private function upsert_entity_link($tenantId, $providerCode, $connectionId, $entityType, $localId, $externalId, array $payload, $direction = null)
    {
        $externalId = trim((string) $externalId);
        if ($externalId === '') {
            return;
        }

        $sourcePayload = (array) ($payload['payload'] ?? []);
        $identity = (array) ($sourcePayload['identity'] ?? []);
        $companyId = (int) ($identity['source_company_id'] ?? ($sourcePayload['tenant']['crm_company_id'] ?? 0));
        $stableKey = trim((string) ($identity['source_stable_key'] ?? ''));
        $record = [
            'connection_id' => $connectionId ?: null,
            'local_id' => (int) $localId,
            'company_id' => $companyId ?: null,
            'source_system' => (string) ($identity['source_system'] ?? 'khachtot_crm'),
            'source_numeric_id' => (int) ($identity['source_numeric_id'] ?? $localId),
            'source_stable_key' => $stableKey ?: null,
            'source_fingerprint' => (string) ($identity['source_fingerprint'] ?? ''),
            'source_version' => (int) ($identity['source_version'] ?? 0),
            'target_system' => 'salesrep_dms',
            'target_entity_id' => $externalId,
            'lifecycle_state' => (string) ($identity['lifecycle_state'] ?? 'active'),
            'last_seen_at' => kt_integration_hub_now(),
            'last_request_id' => (string) ($payload['request_id'] ?? ''),
            'external_hash' => sha1(kt_integration_hub_json_encode($payload)),
            'sync_status' => 'active',
            'direction' => $direction ?: ($providerCode === 'salesflow_dms' ? 'outbound' : 'inbound'),
            'metadata_json' => kt_integration_hub_json_encode([
                'last_payload' => $payload,
            ]),
            'last_synced_at' => kt_integration_hub_now(),
            'updated_at' => kt_integration_hub_now(),
        ];
        $existingQuery = $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->where('provider_code', (string) $providerCode)
            ->where('entity_type', (string) $entityType);
        if ($stableKey !== '') {
            $existingQuery->where('source_stable_key', $stableKey);
        } else {
            $existingQuery->where('external_id', $externalId);
        }
        $existing = $existingQuery->get(db_prefix() . 'kt_integration_entity_links')->row_array();
        if ($existing) {
            $this->landlord_db()->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_integration_entity_links', $record);
            return;
        }

        $record['tenant_id'] = (int) $tenantId;
        $record['provider_code'] = (string) $providerCode;
        $record['entity_type'] = (string) $entityType;
        $record['external_id'] = $externalId;
        $record['first_seen_at'] = kt_integration_hub_now();
        $record['created_at'] = kt_integration_hub_now();
        $this->landlord_db()->insert(db_prefix() . 'kt_integration_entity_links', $record);
    }

    private function mask_phone($phone)
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);
        if ($phone === '') {
            return '';
        }
        if (strlen($phone) <= 6) {
            return str_repeat('*', strlen($phone));
        }

        return substr($phone, 0, 3) . str_repeat('*', max(strlen($phone) - 6, 3)) . substr($phone, -3);
    }

    private function upsert_tenant_lead(array $lead, array $rawPayload)
    {
        $email = trim((string) ($lead['email'] ?? ''));
        $phone = trim((string) ($lead['phone'] ?? ''));
        if ($email !== '') {
            $existing = $this->db->where('email', $email)->get(db_prefix() . 'leads')->row_array();
            if ($existing) {
                return (int) $existing['id'];
            }
        }
        if ($phone !== '') {
            $existing = $this->db->where('phonenumber', $phone)->get(db_prefix() . 'leads')->row_array();
            if ($existing) {
                return (int) $existing['id'];
            }
        }

        $statusId = $this->default_lead_status_id();
        $sourceId = $this->integration_lead_source_id();
        $description = trim((string) ($lead['message'] ?? ''));
        $description .= "\n\nIntegration payload:\n" . kt_integration_hub_json_encode(kt_integration_hub_redact_secrets($rawPayload));

        $this->db->insert(db_prefix() . 'leads', [
            'hash' => app_generate_hash(),
            'name' => trim((string) ($lead['name'] ?? 'Integration Lead')) ?: 'Integration Lead',
            'company' => trim((string) ($lead['company'] ?? '')),
            'description' => nl2br($description),
            'assigned' => 0,
            'dateadded' => kt_integration_hub_now(),
            'status' => $statusId,
            'source' => $sourceId,
            'addedfrom' => get_staff_user_id() ?: 0,
            'email' => $email,
            'phonenumber' => $phone,
            'is_public' => 0,
            'country' => 0,
            'address' => '',
        ]);

        $leadId = (int) $this->db->insert_id();
        if ($leadId <= 0) {
            throw new RuntimeException('Unable to create tenant lead.');
        }

        if ($this->db->table_exists(db_prefix() . 'lead_activity_log')) {
            $this->db->insert(db_prefix() . 'lead_activity_log', [
                'leadid' => $leadId,
                'description' => 'Integration Hub lead created from ' . (string) ($lead['source_provider'] ?? 'webhook'),
                'date' => kt_integration_hub_now(),
                'staffid' => get_staff_user_id() ?: 0,
                'additional_data' => '',
                'custom_activity' => 1,
            ]);
        }

        return $leadId;
    }

    private function normalize_lead_payload(array $payload, $providerCode)
    {
        $lead = is_array($payload['lead'] ?? null) ? $payload['lead'] : $payload;

        return [
            'source_provider' => (string) $providerCode,
            'external_id' => (string) ($payload['external_id'] ?? $payload['id'] ?? $payload['event_id'] ?? ''),
            'name' => (string) ($lead['name'] ?? $lead['full_name'] ?? $lead['customer_name'] ?? 'Integration Lead'),
            'phone' => (string) ($lead['phone'] ?? $lead['phonenumber'] ?? $lead['mobile'] ?? ''),
            'email' => (string) ($lead['email'] ?? ''),
            'company' => (string) ($lead['company'] ?? ''),
            'message' => (string) ($lead['message'] ?? $lead['note'] ?? $payload['message'] ?? ''),
        ];
    }

    private function normalize_zalo_payload(array $payload)
    {
        $eventType = trim((string) ($payload['event_name'] ?? $payload['event_type'] ?? 'zalo_event'));
        $sender = is_array($payload['sender'] ?? null) ? $payload['sender'] : [];
        $follower = is_array($payload['follower'] ?? null) ? $payload['follower'] : [];
        $message = is_array($payload['message'] ?? null) ? $payload['message'] : [];
        $externalUserId = trim((string) ($sender['id'] ?? $payload['user_id_by_app'] ?? $follower['id'] ?? $payload['user_id'] ?? ''));
        $messageId = trim((string) ($message['msg_id'] ?? $payload['msg_id'] ?? $payload['message_id'] ?? ''));
        $messageText = trim((string) ($message['text'] ?? $message['message'] ?? $payload['text'] ?? ''));
        $normalizedEvent = strtolower($eventType);
        if (strpos($normalizedEvent, 'click') !== false && (strpos($normalizedEvent, 'message') !== false || strpos($normalizedEvent, 'nhan') !== false)) {
            $eventType = 'click_message_button';
            if ($messageText === '') {
                $messageText = 'Người dùng bấm Nhắn tin trên Zalo OA.';
            }
        }

        return [
            'source_provider' => 'zalo_oa',
            'external_user_id' => $externalUserId,
            'external_message_id' => $messageId,
            'event_type' => $eventType,
            'message_text' => $messageText,
            'name' => $externalUserId !== '' ? 'Zalo User ' . $externalUserId : 'Zalo User',
            'phone' => '',
            'email' => '',
            'description' => trim('Zalo OA message: ' . $messageText),
            'raw' => $payload,
        ];
    }

    private function connection_settings_payload($providerCode, array $data, array $existing)
    {
        $existingSettings = kt_integration_hub_json_decode((string) ($existing['settings_json'] ?? ''), []);
        if ($providerCode === 'salesflow_dms') {
            $baseUrl = trim((string) ($data['base_url'] ?? ($existingSettings['base_url'] ?? '')));
            if ($baseUrl === '') {
                $candidate = trim((string) ($data['external_account_name'] ?? ($existing['external_account_name'] ?? '')));
                if (preg_match('/^https?:\\/\\//i', $candidate)) {
                    $baseUrl = $candidate;
                }
            }

            return kt_integration_hub_redact_secrets([
                'base_url' => rtrim($baseUrl, '/'),
                'bridge_scope' => 'staff_sync_foundation',
                'last_staff_sync_at' => $existingSettings['last_staff_sync_at'] ?? '',
            ]);
        }

        if ($providerCode === 'zalo_oa') {
            $connectionForUrls = [
                'provider_code' => 'zalo_oa',
                'public_key' => (string) ($existing['public_key'] ?? ''),
            ];
            $hasAccessToken = !empty($existing['access_token_encrypted']) || trim((string) ($data['access_token'] ?? '')) !== '';

            return kt_integration_hub_redact_secrets([
                'app_id' => trim((string) ($data['app_id'] ?? ($existingSettings['app_id'] ?? ''))),
                'oa_id' => trim((string) ($data['oa_id'] ?? ($existing['external_account_id'] ?? ($existingSettings['oa_id'] ?? '')))),
                'oa_name' => trim((string) ($data['external_account_name'] ?? ($existing['external_account_name'] ?? ($existingSettings['oa_name'] ?? '')))),
                'connection_mode' => in_array(($data['connection_mode'] ?? ($existingSettings['connection_mode'] ?? 'manual_token')), ['manual_token', 'oauth_prepared'], true)
                    ? (string) ($data['connection_mode'] ?? ($existingSettings['connection_mode'] ?? 'manual_token'))
                    : 'manual_token',
                'oauth_callback_url' => kt_integration_hub_oauth_callback_url($connectionForUrls, 'zalo_oa'),
                'webhook_url' => kt_integration_hub_webhook_url($connectionForUrls),
                'default_lead_source' => trim((string) ($data['default_lead_source'] ?? ($existingSettings['default_lead_source'] ?? 'Zalo OA'))) ?: 'Zalo OA',
                'lead_assigned' => (int) ($data['lead_assigned'] ?? ($existingSettings['lead_assigned'] ?? 0)),
                'lead_status' => (int) ($data['lead_status'] ?? ($existingSettings['lead_status'] ?? 0)),
                'lead_source' => (int) ($data['lead_source'] ?? ($existingSettings['lead_source'] ?? 0)),
                'allow_unsigned_test_webhook' => array_key_exists('allow_unsigned_test_webhook', $data) ? (int) !empty($data['allow_unsigned_test_webhook']) : (int) ($existingSettings['allow_unsigned_test_webhook'] ?? (ENVIRONMENT !== 'production' ? 1 : 0)),
                'create_lead_on_follow' => array_key_exists('create_lead_on_follow', $data) ? (int) !empty($data['create_lead_on_follow']) : (int) ($existingSettings['create_lead_on_follow'] ?? 1),
                'token_expires_at' => trim((string) ($data['token_expires_at'] ?? ($existingSettings['token_expires_at'] ?? ''))),
                'last_webhook_at' => $existingSettings['last_webhook_at'] ?? '',
                'last_connected_at' => $hasAccessToken ? ($existingSettings['last_connected_at'] ?? kt_integration_hub_now()) : ($existingSettings['last_connected_at'] ?? ''),
                'oauth_status' => $hasAccessToken ? 'token_stored' : 'not_connected',
                'connector_scope' => 'zalo_oa_v1_webhook_intake',
            ]);
        }

        if ($providerCode === 'tiktok_shop') {
            $connectionForUrls = [
                'provider_code' => 'tiktok_shop',
                'public_key' => (string) ($existing['public_key'] ?? ''),
            ];
            $mode = (string) ($data['connection_mode'] ?? ($existingSettings['connection_mode'] ?? 'dry_run'));
            if (!in_array($mode, ['dry_run', 'manual_credentials', 'oauth_prepared'], true)) {
                $mode = 'dry_run';
            }
            $hasAccessToken = !empty($existing['access_token_encrypted']) || trim((string) ($data['access_token'] ?? '')) !== '';

            return kt_integration_hub_redact_secrets([
                'connection_mode' => $mode,
                'app_key' => trim((string) ($data['app_key'] ?? ($existingSettings['app_key'] ?? ''))),
                'shop_id' => trim((string) ($data['shop_id'] ?? ($existing['external_account_id'] ?? ($existingSettings['shop_id'] ?? '')))),
                'shop_cipher' => trim((string) ($data['shop_cipher'] ?? ($existingSettings['shop_cipher'] ?? ''))),
                'shop_name' => trim((string) ($data['external_account_name'] ?? ($existing['external_account_name'] ?? ($existingSettings['shop_name'] ?? '')))),
                'region' => strtoupper(trim((string) ($data['region'] ?? ($existingSettings['region'] ?? 'VN')))),
                'token_expires_at' => trim((string) ($data['token_expires_at'] ?? ($existingSettings['token_expires_at'] ?? ''))),
                'webhook_url' => kt_integration_hub_webhook_url($connectionForUrls),
                'oauth_callback_url' => kt_integration_hub_oauth_callback_url($connectionForUrls, 'tiktok_shop'),
                'sync_orders_enabled' => array_key_exists('sync_orders_enabled', $data) ? (int) !empty($data['sync_orders_enabled']) : (int) ($existingSettings['sync_orders_enabled'] ?? 1),
                'sync_products_enabled' => 0,
                'auto_create_customer' => 0,
                'auto_create_invoice' => 0,
                'dry_run_enabled' => array_key_exists('dry_run_enabled', $data) ? (int) !empty($data['dry_run_enabled']) : (int) ($existingSettings['dry_run_enabled'] ?? 1),
                'last_webhook_at' => $existingSettings['last_webhook_at'] ?? '',
                'last_connected_at' => $hasAccessToken ? ($existingSettings['last_connected_at'] ?? kt_integration_hub_now()) : ($existingSettings['last_connected_at'] ?? ''),
                'oauth_status' => $hasAccessToken ? 'token_stored' : 'not_connected',
                'api_status' => 'mock_ready_real_endpoint_pending_verification',
                'connector_scope' => 'tiktok_shop_v1_order_staging',
            ]);
        }

        return kt_integration_hub_redact_secrets([
            'lead_assigned' => (int) ($data['lead_assigned'] ?? ($existingSettings['lead_assigned'] ?? 0)),
            'lead_status' => (int) ($data['lead_status'] ?? ($existingSettings['lead_status'] ?? 0)),
            'lead_source' => (int) ($data['lead_source'] ?? ($existingSettings['lead_source'] ?? 0)),
        ]);
    }

    private function connection_setting(array $connection, $key, $default = '')
    {
        $settings = kt_integration_hub_json_decode((string) ($connection['settings_json'] ?? ''), []);

        return $settings[$key] ?? $default;
    }

    private function external_account_id_payload($providerCode, array $data, array $existing)
    {
        if ($providerCode === 'salesflow_dms') {
            return trim((string) ($data['external_account_id'] ?? ($existing['external_account_id'] ?? '')));
        }
        if ($providerCode === 'zalo_oa') {
            return trim((string) ($data['oa_id'] ?? ($existing['external_account_id'] ?? '')));
        }
        if ($providerCode === 'tiktok_shop') {
            return trim((string) ($data['shop_id'] ?? $data['shop_cipher'] ?? ($existing['external_account_id'] ?? '')));
        }

        return trim((string) ($data['external_account_id'] ?? ($existing['external_account_id'] ?? '')));
    }

    private function default_lead_status_id()
    {
        $table = db_prefix() . 'leads_status';
        if ($this->db->field_exists('statusorder', $table)) {
            $this->db->order_by('statusorder', 'asc');
        } else {
            $this->db->order_by('id', 'asc');
        }

        $row = $this->db->limit(1)->get($table)->row_array();

        return (int) ($row['id'] ?? 1);
    }

    private function integration_lead_source_id($sourceName = 'Integration Hub')
    {
        $sourceName = trim((string) $sourceName) ?: 'Integration Hub';
        $row = $this->db->where('name', $sourceName)->get(db_prefix() . 'leads_sources')->row_array();
        if ($row) {
            return (int) $row['id'];
        }

        $this->db->insert(db_prefix() . 'leads_sources', [
            'name' => $sourceName,
        ]);

        return (int) $this->db->insert_id();
    }

    private function mark_job_done(array $job, array $result)
    {
        $this->landlord_db()->where('id', (int) $job['id'])->update(db_prefix() . 'kt_integration_sync_jobs', [
            'status' => 'done',
            'result_json' => kt_integration_hub_json_encode($result),
            'error_message' => null,
            'locked_at' => null,
            'updated_at' => kt_integration_hub_now(),
        ]);
        if (!empty($job['webhook_event_id'])) {
            $this->landlord_db()->where('id', (int) $job['webhook_event_id'])->update(db_prefix() . 'kt_integration_webhook_events', [
                'processing_status' => 'processed',
                'processed_at' => kt_integration_hub_now(),
            ]);
        }
        if (!empty($job['connection_id'])) {
            $this->landlord_db()->where('id', (int) $job['connection_id'])->update(db_prefix() . 'kt_integration_connections', [
                'last_sync_at' => kt_integration_hub_now(),
                'updated_at' => kt_integration_hub_now(),
            ]);
        }
        $this->log('info', 'job.done', 'Sync job completed.', ['job_id' => (int) $job['id'], 'result' => $result], (int) $job['tenant_id'], (int) ($job['connection_id'] ?? 0), (string) $job['provider_code']);
        $this->finish_sync_attempt('success', null, null, false, $result);

        return ['success' => true, 'status' => 'done'];
    }

    private function mark_job_failed_or_retry(array $job, $message)
    {
        $message = kt_integration_hub_sanitize_error_message($message);
        $attempts = (int) ($job['attempts'] ?? 0) + 1;
        $maxAttempts = max((int) ($job['max_attempts'] ?? 5), 1);
        $status = $attempts >= $maxAttempts ? 'failed' : 'retry';
        $delayMinutes = min(60, max(1, $attempts * 5));

        $this->landlord_db()->where('id', (int) $job['id'])->update(db_prefix() . 'kt_integration_sync_jobs', [
            'status' => $status,
            'attempts' => $attempts,
            'error_message' => (string) $message,
            'available_at' => $status === 'retry' ? date('Y-m-d H:i:s', time() + ($delayMinutes * 60)) : null,
            'locked_at' => null,
            'updated_at' => kt_integration_hub_now(),
        ]);
        $this->log('error', 'job.' . $status, 'Sync job failed.', ['job_id' => (int) $job['id'], 'error' => $message], (int) $job['tenant_id'], (int) ($job['connection_id'] ?? 0), (string) $job['provider_code']);
        $normalizedError = strtolower((string) $message);
        $safeCode = str_contains($normalizedError, 'timed out') ? 'TRANSPORT_TIMEOUT'
            : (str_contains($normalizedError, 'source numeric id') ? 'SOURCE_REUSED'
            : (str_contains($normalizedError, 'tombstone') ? 'TOMBSTONED_MAPPING'
            : (str_contains($normalizedError, 'fingerprint') ? 'FINGERPRINT_MISMATCH' : 'SYNC_FAILED')));
        $this->landlord_db()->where('id', (int) $job['id'])->update(db_prefix() . 'kt_integration_sync_jobs', ['attempt_count_current' => $attempts, 'terminal_status' => $status === 'failed' ? 'failed' : null, 'last_safe_error_code' => $safeCode]);
        $this->finish_sync_attempt($status === 'retry' ? 'retrying' : 'failed', $safeCode, $message, $status === 'retry');

        return ['success' => false, 'status' => $status, 'message' => (string) $message];
    }

    private function begin_sync_attempt(array $job): void
    {
        $table = db_prefix() . 'kt_integration_sync_attempts';
        if (!$this->landlord_db()->table_exists($table)) return;
        $attemptNo = (int) $this->landlord_db()->where('job_id', (int) $job['id'])->count_all_results($table) + 1;
        $payload = kt_integration_hub_json_decode((string) ($job['payload_json'] ?? ''), []);
        $identity = (array) ($payload['identity'] ?? []);
        $this->landlord_db()->insert($table, [
            'job_id'=>(int) $job['id'],'tenant_id'=>(int) $job['tenant_id'],'company_id'=>(int) ($identity['source_company_id'] ?? 0) ?: null,
            'entity_type'=>(string) ($job['entity_type'] ?? ''),'source_stable_key'=>(string) ($identity['source_stable_key'] ?? ''),
            'attempt_no'=>$attemptNo,'request_id'=>(string) ($job['request_id'] ?? ''),'correlation_id'=>(string) ($job['correlation_id'] ?? $job['request_id'] ?? ''),
            'idempotency_key'=>(string) ($job['idempotency_key'] ?? ''),'started_at'=>kt_integration_hub_now(),'status'=>'running',
            'payload_hash'=>hash('sha256', (string) ($job['payload_json'] ?? '')),'created_at'=>kt_integration_hub_now(),
        ]);
        $this->current_sync_attempt_id = (int) $this->landlord_db()->insert_id();
    }

    private function finish_sync_attempt($status, $safeCode = null, $safeMessage = null, $retryable = false, array $result = []): void
    {
        if ($this->current_sync_attempt_id <= 0) return;
        $this->landlord_db()->where('id', $this->current_sync_attempt_id)->update(db_prefix() . 'kt_integration_sync_attempts', [
            'ended_at'=>kt_integration_hub_now(),'status'=>(string) $status,'safe_error_code'=>$safeCode,
            'safe_error_message'=>$safeMessage ? kt_integration_hub_sanitize_error_message($safeMessage) : null,
            'retryable'=>$retryable ? 1 : 0,'response_hash'=>$result ? hash('sha256', kt_integration_hub_json_encode($result)) : null,
        ]);
        $this->current_sync_attempt_id = 0;
    }

    private function get_tenant($tenantId)
    {
        return $this->landlord_db()
            ->where('id', (int) $tenantId)
            ->where('deleted_at IS NULL', null, false)
            ->get(db_prefix() . 'kt_saas_tenants')
            ->row_array();
    }

    private function count_rows($table, $tenantId = null, array $where = [])
    {
        $db = $this->landlord_db()->from(db_prefix() . $table . ' d');
        if ($tenantId !== null) {
            $db->where('tenant_id', (int) $tenantId);
        }
        foreach ($where as $field => $value) {
            $db->where($field, $value);
        }

        return (int) $db->count_all_results();
    }

    private function count_failed_dms_health_areas(array $console)
    {
        $failed = 0;
        foreach (['staff_sync', 'customer_sync', 'product_sync'] as $key) {
            $status = strtolower(trim((string) ($console[$key]['last_job_status'] ?? '')));
            if (in_array($status, ['failed', 'retry'], true)) {
                $failed++;
            }
        }
        foreach (['checkin_bridge', 'visit_bridge', 'order_bridge'] as $key) {
            $level = strtolower(trim((string) ($console[$key]['last_log_level'] ?? '')));
            if ($level === 'error') {
                $failed++;
            }
        }

        return $failed;
    }

    private function latest_dms_staff_activity_rows($table, $tenantId, $staffColumn, $timeColumn, array $filters = [])
    {
        if (!$this->dms_table_exists($table)) {
            return [];
        }

        $rows = $this->build_dms_query($table, (int) $tenantId, $filters, [], $timeColumn)
            ->select('d.*')
            ->order_by($timeColumn, 'desc')
            ->limit(500)
            ->get()
            ->result_array();

        $indexed = [];
        $counts = [];
        foreach ($rows as $row) {
            $staffId = (int) ($row[$staffColumn] ?? 0);
            if ($staffId <= 0) {
                continue;
            }
            $counts[$staffId] = ($counts[$staffId] ?? 0) + 1;
            if (!isset($indexed[$staffId])) {
                $indexed[$staffId] = $row;
            }
        }
        foreach ($indexed as $staffId => $row) {
            $indexed[$staffId]['activity_count'] = (int) ($counts[$staffId] ?? 0);
        }

        return $indexed;
    }

    private function count_dms_distinct($tenantId, $table, $column, array $filters = [], $dateColumn = null)
    {
        if (!$this->dms_table_exists($table)) {
            return 0;
        }

        $row = $this->build_dms_query($table, (int) $tenantId, $filters, [], $dateColumn)
            ->select('COUNT(DISTINCT ' . $column . ') as total', false)
            ->get()
            ->row_array();

        return (int) ($row['total'] ?? 0);
    }

    private function get_dms_projected_orders_invoices($tenantId, array $filters = [], $limit = 25, $offset = 0)
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0 || !$this->ensure_dms_projection_tables() || !$this->dms_table_exists('dms_order_projection')) {
            return ['rows' => [], 'total' => 0];
        }

        $db = $this->landlord_db();
        $base = function () use ($db, $tenantId, $filters) {
            $query = $db->from(db_prefix() . 'dms_order_projection')
                ->where('tenant_id', $tenantId);

            if (!empty($filters['bridge_status'])) {
                $query->where('sync_status', (string) $filters['bridge_status']);
            }
            if (!empty($filters['crm_invoice_id'])) {
                $query->where('crm_invoice_id', (int) $filters['crm_invoice_id']);
            }
            if (!empty($filters['date_from'])) {
                $query->where('COALESCE(submitted_at, synced_at, updated_at) >= ' . $db->escape((string) $filters['date_from'] . ' 00:00:00'), null, false);
            }
            if (!empty($filters['date_to'])) {
                $query->where('COALESCE(submitted_at, synced_at, updated_at) <= ' . $db->escape((string) $filters['date_to'] . ' 23:59:59'), null, false);
            }

            return $query;
        };

        $total = (int) $base()->count_all_results();
        if ($total <= 0) {
            return ['rows' => [], 'total' => 0];
        }

        $rows = $base()
            ->select('id, dms_order_id, dms_order_number, crm_invoice_id, sync_status as bridge_status, invoice_status as invoice_status_snapshot, invoice_status as crm_invoice_status_readback, order_status as dms_order_status, order_total as total_amount, payment_status as crm_payment_status, paid_amount as crm_paid_amount, balance_amount as crm_balance_amount, payment_source as payment_data_source, submitted_at as order_date, last_synced_at, synced_at, updated_at')
            ->order_by('COALESCE(submitted_at, synced_at, updated_at)', 'desc', false)
            ->limit(max(1, (int) $limit), max(0, (int) $offset))
            ->get()
            ->result_array();

        return ['rows' => $rows, 'total' => $total];
    }

    private function get_dms_projected_order_summary($tenantId, $dateFrom = null, $dateTo = null)
    {
        $summary = [
            'total' => 0,
            'with_invoice' => 0,
            'failed' => 0,
            'cancelled' => 0,
            'pending' => 0,
            'sales_amount' => null,
            'invoiced_amount' => null,
            'paid_amount' => null,
            'balance_amount' => null,
            'payment_source' => 'none',
            'projection_rows' => 0,
        ];

        $tenantId = (int) $tenantId;
        if ($tenantId <= 0 || !$this->ensure_dms_projection_tables() || !$this->dms_table_exists('dms_order_projection')) {
            return $summary;
        }

        $query = $this->landlord_db()
            ->select("
                COUNT(*) as total,
                SUM(CASE WHEN crm_invoice_id IS NOT NULL AND crm_invoice_id > 0 THEN 1 ELSE 0 END) as with_invoice,
                SUM(CASE WHEN sync_status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN sync_status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN sync_status = 'cancelled' OR invoice_status = 'cancelled' OR order_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(order_total) as sales_amount,
                SUM(CASE WHEN crm_invoice_id IS NOT NULL AND crm_invoice_id > 0 THEN COALESCE(invoice_total, order_total, 0) ELSE 0 END) as invoiced_amount,
                SUM(CASE WHEN payment_source IN ('crm.invoice','crm.invoices','crm.projection','salesrep.readback','salesrep.orders.readback') THEN COALESCE(paid_amount, 0) ELSE 0 END) as paid_amount,
                SUM(CASE WHEN payment_source IN ('crm.invoice','crm.invoices','crm.projection','salesrep.readback','salesrep.orders.readback') THEN COALESCE(balance_amount, 0) ELSE 0 END) as balance_amount,
                SUM(CASE WHEN payment_source IN ('crm.invoice','crm.invoices','crm.projection','salesrep.readback','salesrep.orders.readback') THEN 1 ELSE 0 END) as payment_known_count", false)
            ->where('tenant_id', $tenantId);

        if ($dateFrom !== null && trim((string) $dateFrom) !== '') {
            $query->where('COALESCE(submitted_at, synced_at, updated_at) >= ' . $this->landlord_db()->escape(trim((string) $dateFrom) . ' 00:00:00'), null, false);
        }
        if ($dateTo !== null && trim((string) $dateTo) !== '') {
            $query->where('COALESCE(submitted_at, synced_at, updated_at) <= ' . $this->landlord_db()->escape(trim((string) $dateTo) . ' 23:59:59'), null, false);
        }

        $row = $query->get(db_prefix() . 'dms_order_projection')->row_array();
        if ((int) ($row['total'] ?? 0) <= 0) {
            return $summary;
        }

        $paymentKnown = (int) ($row['payment_known_count'] ?? 0) > 0;

        return [
            'total' => (int) ($row['total'] ?? 0),
            'with_invoice' => (int) ($row['with_invoice'] ?? 0),
            'failed' => (int) ($row['failed'] ?? 0),
            'cancelled' => (int) ($row['cancelled'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'sales_amount' => (float) ($row['sales_amount'] ?? 0),
            'invoiced_amount' => (float) ($row['invoiced_amount'] ?? 0),
            'paid_amount' => $paymentKnown ? (float) ($row['paid_amount'] ?? 0) : null,
            'balance_amount' => $paymentKnown ? (float) ($row['balance_amount'] ?? 0) : null,
            'payment_source' => $paymentKnown ? 'crm.projection' : 'crm.projection.order_only',
            'projection_rows' => (int) ($row['total'] ?? 0),
        ];
    }

    private function enrich_dms_order_rows_with_salesrep_amounts($tenantId, array $rows)
    {
        $orderIds = [];
        foreach ($rows as $row) {
            $orderId = (int) ($row['dms_order_id'] ?? 0);
            if ($orderId > 0) {
                $orderIds[] = $orderId;
            }
        }

        if (empty($orderIds) || !$this->salesrep_table_exists('orders')) {
            return $rows;
        }

        $orderIds = array_values(array_unique($orderIds));
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $params = array_merge([(int) $tenantId], $orderIds);
        $salesrepQuery = $this->run_salesrep_query(
            "SELECT id, status, total_amount, crm_invoice_status, crm_payment_status, crm_paid_amount, crm_balance_amount, order_date
             FROM salesrep.orders
             WHERE crm_tenant_id = ? AND id IN ($placeholders)",
            $params
        );
        if (!$salesrepQuery) {
            return $rows;
        }
        $salesrepRows = $salesrepQuery->result_array();

        $byId = [];
        foreach ($salesrepRows as $salesrepRow) {
            $byId[(int) ($salesrepRow['id'] ?? 0)] = $salesrepRow;
        }

        foreach ($rows as &$row) {
            $salesrepOrder = (array) ($byId[(int) ($row['dms_order_id'] ?? 0)] ?? []);
            $row['dms_order_status'] = (string) ($salesrepOrder['status'] ?? '');
            $row['total_amount'] = array_key_exists('total_amount', $salesrepOrder) && $salesrepOrder['total_amount'] !== null ? (float) $salesrepOrder['total_amount'] : null;
            $row['crm_invoice_status_readback'] = (string) ($salesrepOrder['crm_invoice_status'] ?? '');
            $row['crm_payment_status'] = (string) ($salesrepOrder['crm_payment_status'] ?? '');
            $row['crm_paid_amount'] = array_key_exists('crm_paid_amount', $salesrepOrder) && $salesrepOrder['crm_paid_amount'] !== null ? (float) $salesrepOrder['crm_paid_amount'] : null;
            $row['crm_balance_amount'] = array_key_exists('crm_balance_amount', $salesrepOrder) && $salesrepOrder['crm_balance_amount'] !== null ? (float) $salesrepOrder['crm_balance_amount'] : null;
            $row['order_date'] = (string) ($salesrepOrder['order_date'] ?? '');
            $row['payment_data_source'] = ($row['crm_payment_status'] !== '' || $row['crm_paid_amount'] !== null || $row['crm_balance_amount'] !== null)
                ? 'salesrep.readback'
                : 'unknown';

            $crmPayment = $this->get_crm_invoice_payment_snapshot((int) ($row['crm_invoice_id'] ?? 0));
            if ($crmPayment !== null) {
                $row['crm_invoice_status_readback'] = (string) ($crmPayment['invoice_status'] ?? $row['crm_invoice_status_readback']);
                $row['crm_payment_status'] = (string) ($crmPayment['payment_status'] ?? $row['crm_payment_status']);
                $row['crm_paid_amount'] = array_key_exists('paid_amount', $crmPayment) ? (float) $crmPayment['paid_amount'] : $row['crm_paid_amount'];
                $row['crm_balance_amount'] = array_key_exists('balance_amount', $crmPayment) ? (float) $crmPayment['balance_amount'] : $row['crm_balance_amount'];
                $row['payment_data_source'] = 'crm.invoice';
            }
        }
        unset($row);

        return $rows;
    }

    private function get_salesrep_order_amount_summary($tenantId, array $orderIds = [])
    {
        $summary = [
            'sales_amount' => null,
            'invoiced_amount' => null,
            'paid_amount' => null,
            'balance_amount' => null,
            'source' => 'none',
        ];

        if (!$this->salesrep_table_exists('orders')) {
            return $summary;
        }

        $params = [(int) $tenantId];
        $where = 'crm_tenant_id = ?';
        $orderIds = array_values(array_filter(array_unique(array_map('intval', $orderIds))));
        if (!empty($orderIds)) {
            $where .= ' AND id IN (' . implode(',', array_fill(0, count($orderIds), '?')) . ')';
            $params = array_merge($params, $orderIds);
        }

        $query = $this->run_salesrep_query(
            "SELECT
                COUNT(*) as total_orders,
                SUM(total_amount) as sales_amount,
                SUM(CASE WHEN crm_invoice_id IS NOT NULL AND crm_invoice_id > 0 THEN total_amount ELSE 0 END) as invoiced_amount,
                SUM(CASE WHEN crm_paid_amount IS NOT NULL OR crm_balance_amount IS NOT NULL OR COALESCE(crm_payment_status, '') <> '' THEN COALESCE(crm_paid_amount, 0) ELSE 0 END) as paid_amount,
                SUM(CASE WHEN crm_paid_amount IS NOT NULL OR crm_balance_amount IS NOT NULL OR COALESCE(crm_payment_status, '') <> '' THEN COALESCE(crm_balance_amount, 0) ELSE 0 END) as balance_amount,
                SUM(CASE WHEN crm_paid_amount IS NOT NULL OR crm_balance_amount IS NOT NULL OR COALESCE(crm_payment_status, '') <> '' THEN 1 ELSE 0 END) as payment_known_count
             FROM salesrep.orders
             WHERE $where",
            $params
        );
        if (!$query) {
            return $summary;
        }
        $row = $query->row_array();

        if ((int) ($row['total_orders'] ?? 0) <= 0) {
            return $summary;
        }

        return [
            'sales_amount' => (float) ($row['sales_amount'] ?? 0),
            'invoiced_amount' => (float) ($row['invoiced_amount'] ?? 0),
            'paid_amount' => (int) ($row['payment_known_count'] ?? 0) > 0 ? (float) ($row['paid_amount'] ?? 0) : null,
            'balance_amount' => (int) ($row['payment_known_count'] ?? 0) > 0 ? (float) ($row['balance_amount'] ?? 0) : null,
            'source' => (int) ($row['payment_known_count'] ?? 0) > 0 ? 'salesrep.orders.readback' : 'salesrep.orders',
        ];
    }

    private function get_crm_invoice_payment_summary(array $invoiceIds)
    {
        $summary = [
            'known_count' => 0,
            'invoice_total' => null,
            'paid_amount' => null,
            'balance_amount' => null,
        ];

        $invoiceIds = array_values(array_filter(array_unique(array_map('intval', $invoiceIds))));
        foreach ($invoiceIds as $invoiceId) {
            $snapshot = $this->get_crm_invoice_payment_snapshot($invoiceId);
            if ($snapshot === null) {
                continue;
            }
            $summary['known_count']++;
            $summary['invoice_total'] = (float) ($summary['invoice_total'] ?? 0) + (float) ($snapshot['total_amount'] ?? 0);
            $summary['paid_amount'] = (float) ($summary['paid_amount'] ?? 0) + (float) ($snapshot['paid_amount'] ?? 0);
            $summary['balance_amount'] = (float) ($summary['balance_amount'] ?? 0) + (float) ($snapshot['balance_amount'] ?? 0);
        }

        return $summary;
    }

    private function get_crm_invoice_payment_snapshot($invoiceId)
    {
        $invoiceId = (int) $invoiceId;
        if ($invoiceId <= 0) {
            return null;
        }

        try {
            require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/CrmPaymentReadbackService.php');
            $service = new CrmPaymentReadbackService();
            $payload = $service->getPaymentReadbackPayload($invoiceId, null);
            if (!is_array($payload)) {
                return null;
            }

            return $payload;
        } catch (Throwable $e) {
            log_message('warning', 'DMS CRM invoice payment snapshot unavailable: ' . $e->getMessage());
            return null;
        }
    }

    private function get_dms_projected_order_store_locations($tenantId, $date = null, $limit = 100)
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0 || !$this->ensure_dms_projection_tables() || !$this->dms_table_exists('dms_location_projection')) {
            return [];
        }

        $query = $this->landlord_db();
        $query->select('source_record_id as order_id, label as order_number, status, recorded_at as order_date, store_id, latitude as store_latitude, longitude as store_longitude, metadata_json')
            ->from(db_prefix() . 'dms_location_projection')
            ->where('tenant_id', $tenantId)
            ->where('marker_type', 'order_store');
        if ($date !== null && trim((string) $date) !== '') {
            $query->where('recorded_at >=', trim((string) $date) . ' 00:00:00')
                ->where('recorded_at <=', trim((string) $date) . ' 23:59:59');
        }
        $rows = $query->order_by('recorded_at', 'desc')
            ->limit(max(1, (int) $limit))
            ->get()
            ->result_array();

        foreach ($rows as &$row) {
            $metadata = kt_integration_hub_json_decode((string) ($row['metadata_json'] ?? ''), []);
            if (!empty($metadata['dms_order_number'])) {
                $row['order_number'] = (string) $metadata['dms_order_number'];
            }
        }
        unset($row);

        return $rows;
    }

    private function get_dms_projected_locations($tenantId, array $markerTypes, $date = null, $limit = 100)
    {
        $tenantId = (int) $tenantId;
        $markerTypes = array_values(array_filter(array_map('strval', $markerTypes)));
        if ($tenantId <= 0 || empty($markerTypes) || !$this->ensure_dms_projection_tables() || !$this->dms_table_exists('dms_location_projection')) {
            return [];
        }

        $query = $this->landlord_db();
        $query->select('source_record_id, source_record_type, marker_type, staff_id, store_id, order_id, latitude, longitude, accuracy, location_source, recorded_at, label, status, last_synced_at, metadata_json')
            ->from(db_prefix() . 'dms_location_projection')
            ->where('tenant_id', $tenantId)
            ->where_in('marker_type', $markerTypes);

        if ($date !== null && trim((string) $date) !== '') {
            $query->where('recorded_at >=', trim((string) $date) . ' 00:00:00')
                ->where('recorded_at <=', trim((string) $date) . ' 23:59:59');
        }

        return $query->order_by('recorded_at', 'desc')
            ->limit(max(1, (int) $limit))
            ->get()
            ->result_array();
    }

    private function get_dms_field_feedback_locations($tenantId, $date = null, $limit = 100)
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0 || !$this->ensure_dms_projection_tables() || !$this->dms_table_exists('dms_field_feedback_projection')) {
            return [];
        }

        $query = $this->landlord_db();
        $query->select('source_record_id, feedback_type, severity, status, staff_name, store_name, title, submitted_at, latitude, longitude, gps_accuracy, photo_count')
            ->from(db_prefix() . 'dms_field_feedback_projection')
            ->where('tenant_id', $tenantId)
            ->where('latitude IS NOT NULL', null, false)
            ->where('longitude IS NOT NULL', null, false);

        if ($date !== null && trim((string) $date) !== '') {
            $query->where('submitted_at >=', trim((string) $date) . ' 00:00:00')
                ->where('submitted_at <=', trim((string) $date) . ' 23:59:59');
        }

        return $query->order_by('submitted_at', 'desc')
            ->limit(max(1, (int) $limit))
            ->get()
            ->result_array();
    }

    public function upsert_dms_location_projection_marker($tenantId, $companyId, array $payload)
    {
        $tenantId = (int) $tenantId;
        $companyId = (int) $companyId;
        $markerType = strtolower(trim((string) ($payload['marker_type'] ?? '')));
        $sourceRecordType = strtolower(trim((string) ($payload['source_record_type'] ?? $markerType)));
        $sourceRecordId = (int) ($payload['source_record_id'] ?? 0);
        $latitude = $payload['latitude'] ?? null;
        $longitude = $payload['longitude'] ?? null;

        if (
            $tenantId <= 0
            || $sourceRecordId <= 0
            || $markerType === ''
            || $sourceRecordType === ''
            || !is_numeric($latitude)
            || !is_numeric($longitude)
            || !$this->ensure_dms_projection_tables()
            || !$this->dms_table_exists('dms_location_projection')
        ) {
            return false;
        }

        $now = kt_integration_hub_now();
        $recordedAt = $this->normalize_projection_datetime($payload['recorded_at'] ?? null) ?: $now;
        $metadata = kt_integration_hub_redact_secrets((array) ($payload['metadata'] ?? []));
        $metadata['contract'] = $metadata['contract'] ?? 'dms_location_projection_v1';
        $checksum = sha1(kt_integration_hub_json_encode([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'marker_type' => $markerType,
            'source_record_type' => $sourceRecordType,
            'source_record_id' => $sourceRecordId,
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
            'recorded_at' => $recordedAt,
            'metadata' => $metadata,
        ]));

        $record = [
            'company_id' => $companyId > 0 ? $companyId : $tenantId,
            'dms_workspace_host' => $this->get_dms_workspace_host_for_tenant($tenantId),
            'source' => trim((string) ($payload['source'] ?? 'salesrep')) ?: 'salesrep',
            'source_record_type' => $sourceRecordType,
            'projection_version' => 1,
            'sync_status' => trim((string) ($payload['sync_status'] ?? 'synced')) ?: 'synced',
            'marker_type' => $markerType,
            'staff_id' => isset($payload['staff_id']) && (int) $payload['staff_id'] > 0 ? (int) $payload['staff_id'] : null,
            'store_id' => isset($payload['store_id']) && (int) $payload['store_id'] > 0 ? (int) $payload['store_id'] : null,
            'order_id' => isset($payload['order_id']) && (int) $payload['order_id'] > 0 ? (int) $payload['order_id'] : null,
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
            'accuracy' => isset($payload['accuracy']) && is_numeric($payload['accuracy']) ? (float) $payload['accuracy'] : null,
            'location_source' => trim((string) ($payload['location_source'] ?? 'captured_gps')) ?: 'captured_gps',
            'recorded_at' => $recordedAt,
            'label' => trim((string) ($payload['label'] ?? '')) ?: null,
            'status' => trim((string) ($payload['status'] ?? '')) ?: null,
            'last_synced_at' => trim((string) ($payload['last_synced_at'] ?? '')) ?: $now,
            'checksum' => $checksum,
            'metadata_json' => kt_integration_hub_json_encode($metadata),
            'updated_at' => $now,
        ];

        $existing = $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->where('marker_type', $markerType)
            ->where('source_record_type', $sourceRecordType)
            ->where('source_record_id', $sourceRecordId)
            ->where('projection_version', 1)
            ->get(db_prefix() . 'dms_location_projection')
            ->row_array();

        if ($existing) {
            $this->landlord_db()->where('id', (int) $existing['id'])->update(db_prefix() . 'dms_location_projection', $record);

            return true;
        }

        $record['tenant_id'] = $tenantId;
        $record['source_record_id'] = $sourceRecordId;
        $record['created_at'] = $now;
        $this->landlord_db()->insert(db_prefix() . 'dms_location_projection', $record);

        return $this->landlord_db()->affected_rows() > 0;
    }

    private function upsert_dms_location_projection_for_order($tenantId, $companyId, array $order, array $orderProjection)
    {
        $tenantId = (int) $tenantId;
        $companyId = (int) $companyId;
        $orderId = (int) ($order['order_id'] ?? ($orderProjection['dms_order_id'] ?? 0));
        if ($tenantId <= 0 || $orderId <= 0 || !$this->ensure_dms_projection_tables()) {
            return false;
        }

        $lat = null;
        $lng = null;
        $markerType = 'captured_order';
        $locationSource = 'captured_order_gps';
        foreach ([['order_latitude', 'order_longitude'], ['latitude', 'longitude']] as $pair) {
            if (is_numeric($order[$pair[0]] ?? null) && is_numeric($order[$pair[1]] ?? null)) {
                $lat = (float) $order[$pair[0]];
                $lng = (float) $order[$pair[1]];
                break;
            }
        }

        if ($lat === null || $lng === null) {
            $store = $this->get_salesrep_store_location_for_projection($tenantId, (int) ($order['store_id'] ?? 0));
            if (!$store) {
                return false;
            }
            $lat = (float) $store['latitude'];
            $lng = (float) $store['longitude'];
            $markerType = 'order_store';
            $locationSource = 'store_coordinates';
        }

        $recordedAt = $this->normalize_projection_datetime($order['submitted_at'] ?? ($order['ordered_at'] ?? ($order['order_date'] ?? ($orderProjection['synced_at'] ?? null)))) ?: kt_integration_hub_now();
        $metadata = kt_integration_hub_redact_secrets([
            'contract' => 'dms_location_projection_v1',
            'dms_order_number' => (string) ($order['order_number'] ?? ($orderProjection['dms_order_number'] ?? '')),
            'location_contract' => $markerType === 'order_store'
                ? 'store coordinates for order context; not captured order GPS'
                : 'captured order GPS from DMS payload',
        ]);
        $checksum = sha1(kt_integration_hub_json_encode([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'marker_type' => $markerType,
            'order_id' => $orderId,
            'lat' => $lat,
            'lng' => $lng,
            'recorded_at' => $recordedAt,
            'metadata' => $metadata,
        ]));
        $now = kt_integration_hub_now();
        $record = [
            'company_id' => $companyId > 0 ? $companyId : $tenantId,
            'dms_workspace_host' => $this->get_dms_workspace_host_for_tenant($tenantId),
            'source' => 'salesrep',
            'source_record_type' => 'order',
            'projection_version' => 1,
            'sync_status' => (string) ($orderProjection['sync_status'] ?? 'synced'),
            'marker_type' => $markerType,
            'staff_id' => !empty($order['crm_staff_id']) ? (int) $order['crm_staff_id'] : null,
            'store_id' => !empty($order['store_id']) ? (int) $order['store_id'] : null,
            'order_id' => $orderId,
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy' => isset($order['location_accuracy']) && is_numeric($order['location_accuracy']) ? (float) $order['location_accuracy'] : null,
            'location_source' => $locationSource,
            'recorded_at' => $recordedAt,
            'label' => (string) ($order['order_number'] ?? ($orderProjection['dms_order_number'] ?? ('Order #' . $orderId))),
            'status' => (string) ($order['status'] ?? ($orderProjection['order_status'] ?? '')),
            'last_synced_at' => (string) ($orderProjection['last_synced_at'] ?? $now),
            'checksum' => $checksum,
            'metadata_json' => kt_integration_hub_json_encode($metadata),
            'updated_at' => $now,
        ];

        $existing = $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->where('marker_type', $markerType)
            ->where('source_record_type', 'order')
            ->where('source_record_id', $orderId)
            ->where('projection_version', 1)
            ->get(db_prefix() . 'dms_location_projection')
            ->row_array();

        if ($existing) {
            $this->landlord_db()->where('id', (int) $existing['id'])->update(db_prefix() . 'dms_location_projection', $record);

            return true;
        }

        $record['tenant_id'] = $tenantId;
        $record['source_record_id'] = $orderId;
        $record['created_at'] = $now;
        $this->landlord_db()->insert(db_prefix() . 'dms_location_projection', $record);

        return $this->landlord_db()->affected_rows() > 0;
    }

    private function get_salesrep_order_for_projection($tenantId, $orderId)
    {
        if (!$this->salesrep_table_exists('orders')) {
            return [];
        }

        $query = $this->run_salesrep_query(
            'SELECT id as order_id, order_number, user_id as crm_staff_id, client_id as crm_customer_id, store_id, status, total_amount, currency, crm_invoice_id, crm_invoice_status, crm_payment_status, crm_paid_amount, crm_balance_amount, captured_latitude as order_latitude, captured_longitude as order_longitude, captured_gps_accuracy as location_accuracy, captured_location_at, order_date, created_at, updated_at
             FROM salesrep.orders
             WHERE crm_tenant_id = ? AND id = ?
             LIMIT 1',
            [(int) $tenantId, (int) $orderId]
        );
        if (!$query) {
            return [];
        }

        $row = $query->row_array();
        if (!$row) {
            return [];
        }

        $row['grand_total'] = $row['total_amount'] ?? null;
        $row['ordered_at'] = $row['order_date'] ?? null;

        return $row;
    }

    private function get_salesrep_store_location_for_projection($tenantId, $storeId)
    {
        $tenantId = (int) $tenantId;
        $storeId = (int) $storeId;
        if ($tenantId <= 0 || $storeId <= 0 || !$this->salesrep_table_exists('stores')) {
            return null;
        }

        $query = $this->run_salesrep_query(
            'SELECT id, name, latitude, longitude
             FROM salesrep.stores
             WHERE crm_tenant_id = ? AND id = ? AND latitude IS NOT NULL AND longitude IS NOT NULL
             LIMIT 1',
            [$tenantId, $storeId]
        );
        if (!$query) {
            return null;
        }

        $row = $query->row_array();

        return $row ?: null;
    }

    private function normalize_projection_datetime($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function get_dms_workspace_host_for_tenant($tenantId)
    {
        $tenant = $this->get_tenant((int) $tenantId);
        if (!$tenant) {
            return null;
        }

        return kt_integration_hub_dms_workspace_host($tenant, $this->get_salesflow_connection((int) $tenantId) ?: []);
    }

    private function get_dms_order_store_locations($tenantId, $date, $limit = 100)
    {
        if (!$this->salesrep_table_exists('orders') || !$this->salesrep_table_exists('stores')) {
            return [];
        }
        $limit = max(1, (int) $limit);

        $query = $this->run_salesrep_query(
            'SELECT o.id as order_id, o.order_number, o.status, o.order_date, s.id as store_id, s.name as store_name, s.latitude as store_latitude, s.longitude as store_longitude
             FROM salesrep.orders o
             JOIN salesrep.stores s ON s.id = o.store_id
             WHERE o.crm_tenant_id = ? AND DATE(o.order_date) = ? AND s.latitude IS NOT NULL AND s.longitude IS NOT NULL
             ORDER BY o.order_date DESC, o.id DESC
             LIMIT ' . $limit,
            [(int) $tenantId, (string) $date]
        );

        return $query ? $query->result_array() : [];
    }

    private function get_dms_store_locations($tenantId, $limit = 100)
    {
        if (!$this->salesrep_table_exists('stores')) {
            return [];
        }
        $limit = max(1, (int) $limit);

        $query = $this->run_salesrep_query(
            'SELECT id, name, status, latitude, longitude, last_synced_at
             FROM salesrep.stores
             WHERE crm_tenant_id = ? AND latitude IS NOT NULL AND longitude IS NOT NULL
             ORDER BY updated_at DESC, id DESC
             LIMIT ' . $limit,
            [(int) $tenantId]
        );

        return $query ? $query->result_array() : [];
    }

    private function get_dms_inventory_insight($tenantId)
    {
        $result = [
            'low_stock_count' => null,
            'low_stock_rows' => [],
            'movement_count' => null,
            'movement_rows' => [],
        ];

        if ($this->salesrep_table_exists('store_products') && $this->salesrep_table_exists('stores')) {
            $rowsQuery = $this->run_salesrep_query(
                'SELECT sp.id, sp.stock_quantity, sp.low_stock_threshold, COALESCE(p.name, sp.custom_product_name, CONCAT("Product #", sp.product_id)) as product_name, s.name as store_name
                 FROM salesrep.store_products sp
                 JOIN salesrep.stores s ON s.id = sp.store_id
                 LEFT JOIN salesrep.products p ON p.id = sp.product_id
                 WHERE s.crm_tenant_id = ? AND sp.stock_quantity <= sp.low_stock_threshold
                 ORDER BY sp.stock_quantity ASC, sp.id DESC
                 LIMIT 10',
                [(int) $tenantId]
            );
            $rows = $rowsQuery ? $rowsQuery->result_array() : [];
            $countQuery = $this->run_salesrep_query(
                'SELECT COUNT(*) as total
                 FROM salesrep.store_products sp
                 JOIN salesrep.stores s ON s.id = sp.store_id
                 WHERE s.crm_tenant_id = ? AND sp.stock_quantity <= sp.low_stock_threshold',
                [(int) $tenantId]
            );
            $count = $countQuery ? $countQuery->row_array() : [];
            $result['low_stock_count'] = (int) ($count['total'] ?? count($rows));
            $result['low_stock_rows'] = $rows;
        }

        if ($this->salesrep_table_exists('inventory_transactions') && $this->salesrep_table_exists('store_products') && $this->salesrep_table_exists('stores')) {
            $movementQuery = $this->run_salesrep_query(
                'SELECT it.transaction_type, it.quantity, it.quantity_before, it.quantity_after, it.created_at, COALESCE(p.name, sp.custom_product_name, CONCAT("Product #", sp.product_id)) as product_name, s.name as store_name
                 FROM salesrep.inventory_transactions it
                 JOIN salesrep.store_products sp ON sp.id = it.store_product_id
                 JOIN salesrep.stores s ON s.id = sp.store_id
                 LEFT JOIN salesrep.products p ON p.id = sp.product_id
                 WHERE s.crm_tenant_id = ?
                 ORDER BY it.created_at DESC, it.id DESC
                 LIMIT 10',
                [(int) $tenantId]
            );
            $movementRows = $movementQuery ? $movementQuery->result_array() : [];
            $countQuery = $this->run_salesrep_query(
                'SELECT COUNT(*) as total
                 FROM salesrep.inventory_transactions it
                 JOIN salesrep.store_products sp ON sp.id = it.store_product_id
                 JOIN salesrep.stores s ON s.id = sp.store_id
                 WHERE s.crm_tenant_id = ?',
                [(int) $tenantId]
            );
            $count = $countQuery ? $countQuery->row_array() : [];
            $result['movement_count'] = (int) ($count['total'] ?? count($movementRows));
            $result['movement_rows'] = $movementRows;
        }

        return $result;
    }

    private function get_dms_projected_inventory_insight($tenantId)
    {
        $result = [
            'low_stock_count' => null,
            'low_stock_rows' => [],
            'movement_count' => null,
            'movement_rows' => [],
            'projection_rows' => 0,
        ];

        $tenantId = (int) $tenantId;
        if ($tenantId <= 0 || !$this->ensure_dms_projection_tables() || !$this->dms_table_exists('dms_inventory_projection')) {
            return $result;
        }

        $result['projection_rows'] = (int) $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->count_all_results(db_prefix() . 'dms_inventory_projection');
        if ($result['projection_rows'] <= 0) {
            return $result;
        }

        $lowRows = $this->landlord_db()
            ->select('id, stock_on_hand as stock_quantity, low_stock_threshold, product_name, store_name')
            ->where('tenant_id', $tenantId)
            ->where('is_low_stock', 1)
            ->order_by('stock_on_hand', 'asc')
            ->order_by('id', 'desc')
            ->limit(10)
            ->get(db_prefix() . 'dms_inventory_projection')
            ->result_array();
        $result['low_stock_rows'] = $lowRows;
        $result['low_stock_count'] = (int) $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->where('is_low_stock', 1)
            ->count_all_results(db_prefix() . 'dms_inventory_projection');

        $movementRows = $this->landlord_db()
            ->select('movement_type as transaction_type, movement_quantity as quantity, stock_on_hand as quantity_after, movement_at as created_at, product_name, store_name')
            ->where('tenant_id', $tenantId)
            ->where('movement_type IS NOT NULL', null, false)
            ->order_by('movement_at', 'desc')
            ->order_by('id', 'desc')
            ->limit(10)
            ->get(db_prefix() . 'dms_inventory_projection')
            ->result_array();
        foreach ($movementRows as &$row) {
            $row['quantity_before'] = null;
        }
        unset($row);
        $result['movement_rows'] = $movementRows;
        $result['movement_count'] = (int) $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->where('movement_type IS NOT NULL', null, false)
            ->count_all_results(db_prefix() . 'dms_inventory_projection');

        return $result;
    }

    private function upsert_projection_record($table, array $payload, array $options)
    {
        $tenantId = (int) ($payload['tenant_id'] ?? 0);
        $companyId = (int) ($payload['company_id'] ?? 0);
        $sourceRecordId = (int) ($payload['source_record_id'] ?? 0);
        $projectionVersion = max(1, (int) ($payload['projection_version'] ?? 1));
        foreach ((array) ($options['required'] ?? []) as $required) {
            if ((int) ($payload[$required] ?? 0) <= 0) {
                return false;
            }
        }
        if ($tenantId <= 0 || $companyId <= 0 || $sourceRecordId <= 0 || !$this->ensure_dms_projection_tables()) {
            return false;
        }

        $payload['source'] = trim((string) ($payload['source'] ?? 'salesrep')) ?: 'salesrep';
        $payload['source_record_type'] = trim((string) ($payload['source_record_type'] ?? ($options['source_record_type'] ?? 'projection'))) ?: (string) ($options['source_record_type'] ?? 'projection');
        $payload['projection_version'] = $projectionVersion;
        $payload['sync_status'] = trim((string) ($payload['sync_status'] ?? 'synced')) ?: 'synced';
        $payload['last_synced_at'] = $this->normalize_projection_datetime($payload['last_synced_at'] ?? null) ?: kt_integration_hub_now();
        $payload['dms_workspace_host'] = trim((string) ($payload['dms_workspace_host'] ?? '')) ?: $this->get_dms_workspace_host_for_tenant($tenantId);
        $metadata = kt_integration_hub_redact_secrets((array) ($payload['metadata'] ?? []));
        unset($payload['metadata']);
        $payload['metadata_json'] = kt_integration_hub_json_encode($metadata);
        $checksumPayload = $payload;
        unset($checksumPayload['checksum'], $checksumPayload['metadata_json'], $checksumPayload['created_at'], $checksumPayload['updated_at']);
        $payload['checksum'] = sha1(kt_integration_hub_json_encode($checksumPayload));

        $record = [
            'tenant_id' => $tenantId,
            'source_record_id' => $sourceRecordId,
            'updated_at' => kt_integration_hub_now(),
        ];
        foreach ((array) ($options['fields'] ?? []) as $field) {
            if (array_key_exists($field, $payload)) {
                $record[$field] = $payload[$field];
            }
        }
        if (!array_key_exists('company_id', $record)) {
            $record['company_id'] = $companyId;
        }
        if (!array_key_exists('source_record_type', $record)) {
            $record['source_record_type'] = $payload['source_record_type'];
        }
        if (!array_key_exists('projection_version', $record)) {
            $record['projection_version'] = $projectionVersion;
        }

        $db = $this->landlord_db();
        $existing = $db->where('tenant_id', $tenantId)
            ->where('source_record_type', $payload['source_record_type'])
            ->where('source_record_id', $sourceRecordId)
            ->where('projection_version', $projectionVersion)
            ->get(db_prefix() . $table)
            ->row_array();
        if ($existing) {
            $db->where('id', (int) $existing['id'])->update(db_prefix() . $table, $record);

            return true;
        }

        $record['created_at'] = kt_integration_hub_now();
        $db->insert(db_prefix() . $table, $record);

        return $db->affected_rows() > 0;
    }

    private function projection_backfill_tenant_ids($tenantId = null)
    {
        if ($tenantId !== null) {
            return [(int) $tenantId];
        }

        if (!$this->salesrep_table_exists('orders')) {
            return [];
        }

        $query = $this->run_salesrep_query(
            'SELECT DISTINCT crm_tenant_id FROM salesrep.orders WHERE crm_tenant_id IS NOT NULL
             UNION
             SELECT DISTINCT crm_tenant_id FROM salesrep.stores WHERE crm_tenant_id IS NOT NULL'
        );
        if (!$query) {
            return [];
        }

        return array_values(array_filter(array_map('intval', array_column($query->result_array(), 'crm_tenant_id'))));
    }

    private function fetch_salesrep_inventory_projection_rows($tenantId)
    {
        $tenantId = (int) $tenantId;
        if (!$this->salesrep_table_exists('store_products') || !$this->salesrep_table_exists('stores')) {
            return [];
        }

        $workspace = $this->get_dms_workspace_host_for_tenant($tenantId);
        $rows = [];
        $query = $this->run_salesrep_query(
            'SELECT sp.id, s.crm_company_id, sp.store_id, s.name as store_name, sp.product_id, COALESCE(p.name, sp.custom_product_name, CONCAT("Product #", sp.product_id)) as product_name,
                    COALESCE(p.code, "") as sku, sp.stock_quantity, sp.low_stock_threshold, s.crm_tenant_id
             FROM salesrep.store_products sp
             JOIN salesrep.stores s ON s.id = sp.store_id
             LEFT JOIN salesrep.products p ON p.id = sp.product_id
             WHERE s.crm_tenant_id = ?',
            [$tenantId]
        );
        if ($query) {
            foreach ($query->result_array() as $row) {
                $stock = $row['stock_quantity'] !== null ? (float) $row['stock_quantity'] : null;
                $threshold = $row['low_stock_threshold'] !== null ? (float) $row['low_stock_threshold'] : null;
                $rows[] = [
                    'tenant_id' => $tenantId,
                    'company_id' => (int) ($row['crm_company_id'] ?? $tenantId) ?: $tenantId,
                    'dms_workspace_host' => $workspace,
                    'store_id' => (int) ($row['store_id'] ?? 0),
                    'store_name' => (string) ($row['store_name'] ?? ''),
                    'product_id' => (int) ($row['product_id'] ?? 0),
                    'product_name' => (string) ($row['product_name'] ?? ''),
                    'sku' => (string) ($row['sku'] ?? ''),
                    'stock_on_hand' => $stock,
                    'reserved_quantity' => null,
                    'low_stock_threshold' => $threshold,
                    'is_low_stock' => $stock !== null && $threshold !== null && $stock <= $threshold ? 1 : 0,
                    'source_record_id' => (int) ($row['id'] ?? 0),
                    'source_record_type' => 'inventory',
                    'metadata' => ['contract' => 'dms_inventory_projection_v1'],
                ];
            }
        }

        if ($this->salesrep_table_exists('inventory_transactions')) {
            $movementQuery = $this->run_salesrep_query(
                'SELECT it.id, it.transaction_type, it.quantity, it.created_at, sp.store_id, s.name as store_name, s.crm_company_id,
                        sp.product_id, COALESCE(p.name, sp.custom_product_name, CONCAT("Product #", sp.product_id)) as product_name, COALESCE(p.code, "") as sku,
                        sp.stock_quantity, sp.low_stock_threshold
                 FROM salesrep.inventory_transactions it
                 JOIN salesrep.store_products sp ON sp.id = it.store_product_id
                 JOIN salesrep.stores s ON s.id = sp.store_id
                 LEFT JOIN salesrep.products p ON p.id = sp.product_id
                 WHERE s.crm_tenant_id = ?
                 ORDER BY it.created_at DESC
                 LIMIT 500',
                [$tenantId]
            );
            if ($movementQuery) {
                foreach ($movementQuery->result_array() as $row) {
                    $rows[] = [
                        'tenant_id' => $tenantId,
                        'company_id' => (int) ($row['crm_company_id'] ?? $tenantId) ?: $tenantId,
                        'dms_workspace_host' => $workspace,
                        'store_id' => (int) ($row['store_id'] ?? 0),
                        'store_name' => (string) ($row['store_name'] ?? ''),
                        'product_id' => (int) ($row['product_id'] ?? 0),
                        'product_name' => (string) ($row['product_name'] ?? ''),
                        'sku' => (string) ($row['sku'] ?? ''),
                        'stock_on_hand' => $row['stock_quantity'] !== null ? (float) $row['stock_quantity'] : null,
                        'low_stock_threshold' => $row['low_stock_threshold'] !== null ? (float) $row['low_stock_threshold'] : null,
                        'is_low_stock' => 0,
                        'movement_type' => (string) ($row['transaction_type'] ?? ''),
                        'movement_quantity' => (float) ($row['quantity'] ?? 0),
                        'movement_at' => $this->normalize_projection_datetime($row['created_at'] ?? null),
                        'source_record_id' => 1000000000 + (int) ($row['id'] ?? 0),
                        'source_record_type' => 'inventory_movement',
                        'metadata' => ['contract' => 'dms_inventory_projection_v1', 'source_table' => 'inventory_transactions'],
                    ];
                }
            }
        }

        return array_values(array_filter($rows, fn ($row) => (int) ($row['source_record_id'] ?? 0) > 0));
    }

    private function fetch_salesrep_product_sales_projection_rows($tenantId)
    {
        $tenantId = (int) $tenantId;
        if (!$this->salesrep_table_exists('orders') || !$this->salesrep_table_exists('order_items')) {
            return [];
        }

        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $query = $this->run_salesrep_query(
            'SELECT oi.product_id, COALESCE(p.name, CONCAT("Product #", oi.product_id)) as product_name, COALESCE(p.code, "") as sku,
                    SUM(CASE WHEN o.order_date = ? THEN oi.quantity ELSE 0 END) as sold_quantity_day,
                    SUM(CASE WHEN o.order_date = ? THEN oi.total_price ELSE 0 END) as sold_amount_day,
                    COUNT(DISTINCT CASE WHEN o.order_date = ? THEN o.id ELSE NULL END) as order_count_day,
                    SUM(oi.quantity) as sold_quantity_month,
                    SUM(oi.total_price) as sold_amount_month,
                    COUNT(DISTINCT o.id) as order_count_month,
                    MAX(o.crm_company_id) as crm_company_id
             FROM salesrep.order_items oi
             JOIN salesrep.orders o ON o.id = oi.order_id
             LEFT JOIN salesrep.products p ON p.id = oi.product_id
             WHERE o.crm_tenant_id = ? AND o.order_date >= ? AND o.order_date <= ?
             GROUP BY oi.product_id, p.name, p.code',
            [$today, $today, $today, $tenantId, $monthStart, $today]
        );
        if (!$query) {
            return [];
        }

        $workspace = $this->get_dms_workspace_host_for_tenant($tenantId);
        $rows = [];
        foreach ($query->result_array() as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $rows[] = [
                'tenant_id' => $tenantId,
                'company_id' => (int) ($row['crm_company_id'] ?? $tenantId) ?: $tenantId,
                'dms_workspace_host' => $workspace,
                'product_id' => $productId,
                'product_name' => (string) ($row['product_name'] ?? ''),
                'sku' => (string) ($row['sku'] ?? ''),
                'sold_quantity_day' => (float) ($row['sold_quantity_day'] ?? 0),
                'sold_amount_day' => (float) ($row['sold_amount_day'] ?? 0),
                'sold_quantity_month' => (float) ($row['sold_quantity_month'] ?? 0),
                'sold_amount_month' => (float) ($row['sold_amount_month'] ?? 0),
                'order_count_day' => (int) ($row['order_count_day'] ?? 0),
                'order_count_month' => (int) ($row['order_count_month'] ?? 0),
                'source_record_id' => $productId,
                'source_record_type' => 'product_sales',
                'metadata' => ['contract' => 'dms_product_sales_projection_v1', 'month_start' => $monthStart, 'date_to' => $today],
            ];
        }

        return $rows;
    }

    private function build_staff_activity_projection_rows($tenantId)
    {
        $tenantId = (int) $tenantId;
        $staffRows = $this->get_dms_staff_access_rows($tenantId);
        $today = date('Y-m-d');
        $latestCheckins = $this->latest_dms_staff_activity_rows('dms_checkin_events', $tenantId, 'crm_staff_id', 'checkin_at', ['date_from' => $today, 'date_to' => $today]);
        $latestVisits = $this->latest_dms_staff_activity_rows('dms_visit_snapshots', $tenantId, 'crm_staff_id', 'ended_at', ['date_from' => $today, 'date_to' => $today]);
        $tracking = $this->latest_dms_staff_activity_rows('dms_live_tracking_snapshots', $tenantId, 'crm_staff_id', 'last_seen_at', []);
        $orders = $this->get_salesrep_staff_order_summary($tenantId, $today);
        $workspace = $this->get_dms_workspace_host_for_tenant($tenantId);

        $rows = [];
        foreach ($staffRows as $staff) {
            $staffId = (int) ($staff['staffid'] ?? $staff['crm_staff_id'] ?? 0);
            if ($staffId <= 0) {
                continue;
            }
            $track = (array) ($tracking[$staffId] ?? []);
            $checkin = (array) ($latestCheckins[$staffId] ?? []);
            $visit = (array) ($latestVisits[$staffId] ?? []);
            $order = (array) ($orders[$staffId] ?? []);
            $rows[] = [
                'tenant_id' => $tenantId,
                'company_id' => $tenantId,
                'dms_workspace_host' => $workspace,
                'staff_id' => $staffId,
                'crm_staff_id' => $staffId,
                'staff_name' => trim((string) (($staff['firstname'] ?? '') . ' ' . ($staff['lastname'] ?? ''))) ?: (string) ($staff['email'] ?? ('Staff #' . $staffId)),
                'manager_staff_id' => (int) ($staff['manager_crm_staff_id'] ?? 0),
                'role_code' => (string) ($staff['dms_role'] ?? $staff['dms_role_code'] ?? ''),
                'online_state' => (string) ($track['tracking_status'] ?? 'offline'),
                'last_seen_at' => $this->normalize_projection_datetime($track['last_seen_at'] ?? null),
                'last_checkin_at' => $this->normalize_projection_datetime($checkin['checkin_at'] ?? null),
                'last_visit_at' => $this->normalize_projection_datetime($visit['ended_at'] ?? null),
                'today_checkin_count' => (int) ($checkin['activity_count'] ?? 0),
                'today_visit_count' => (int) ($visit['activity_count'] ?? 0),
                'today_order_count' => (int) ($order['order_count'] ?? 0),
                'today_order_amount' => (float) ($order['order_amount'] ?? 0),
                'missed_visit_count' => 0,
                'source_record_id' => $staffId,
                'source_record_type' => 'staff_activity',
                'metadata' => ['contract' => 'dms_staff_activity_projection_v1', 'projection_date' => $today],
            ];
        }

        return $rows;
    }

    private function fetch_salesrep_store_coverage_projection_rows($tenantId)
    {
        $tenantId = (int) $tenantId;
        if (!$this->salesrep_table_exists('stores')) {
            return [];
        }

        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $workspace = $this->get_dms_workspace_host_for_tenant($tenantId);
        $query = $this->run_salesrep_query(
            'SELECT s.id, s.name, s.crm_company_id, s.crm_customer_id, s.assigned_user_id,
                    MAX(ci.checkin_timestamp) as last_checkin_at,
                    MAX(COALESCE(v.completed_at, v.started_at)) as last_visit_at,
                    SUM(CASE WHEN o.order_date = ? THEN 1 ELSE 0 END) as today_order_count,
                    SUM(CASE WHEN o.order_date >= ? AND o.order_date <= ? THEN 1 ELSE 0 END) as month_order_count
             FROM salesrep.stores s
             LEFT JOIN salesrep.check_ins ci ON ci.store_id = s.id AND ci.crm_tenant_id = s.crm_tenant_id
             LEFT JOIN salesrep.visits v ON v.store_id = s.id AND v.crm_tenant_id = s.crm_tenant_id
             LEFT JOIN salesrep.orders o ON o.store_id = s.id AND o.crm_tenant_id = s.crm_tenant_id
             WHERE s.crm_tenant_id = ?
             GROUP BY s.id, s.name, s.crm_company_id, s.crm_customer_id, s.assigned_user_id',
            [$today, $monthStart, $today, $tenantId]
        );
        if (!$query) {
            return [];
        }

        $rows = [];
        foreach ($query->result_array() as $row) {
            $lastCheckin = $this->normalize_projection_datetime($row['last_checkin_at'] ?? null);
            $lastVisit = $this->normalize_projection_datetime($row['last_visit_at'] ?? null);
            $visitedToday = ($lastCheckin && substr($lastCheckin, 0, 10) === $today) || ($lastVisit && substr($lastVisit, 0, 10) === $today);
            $visitedMonth = ($lastCheckin && substr($lastCheckin, 0, 7) === substr($today, 0, 7)) || ($lastVisit && substr($lastVisit, 0, 7) === substr($today, 0, 7));
            $rows[] = [
                'tenant_id' => $tenantId,
                'company_id' => (int) ($row['crm_company_id'] ?? $tenantId) ?: $tenantId,
                'dms_workspace_host' => $workspace,
                'store_id' => (int) ($row['id'] ?? 0),
                'store_name' => (string) ($row['name'] ?? ''),
                'crm_customer_id' => (int) ($row['crm_customer_id'] ?? 0),
                'assigned_staff_id' => (int) ($row['assigned_user_id'] ?? 0),
                'visited_today' => $visitedToday ? 1 : 0,
                'visited_this_month' => $visitedMonth ? 1 : 0,
                'last_visit_at' => $lastVisit,
                'last_checkin_at' => $lastCheckin,
                'today_order_count' => (int) ($row['today_order_count'] ?? 0),
                'month_order_count' => (int) ($row['month_order_count'] ?? 0),
                'coverage_date' => $today,
                'coverage_month' => substr($today, 0, 7),
                'source_record_id' => (int) ($row['id'] ?? 0),
                'source_record_type' => 'store_coverage',
                'metadata' => ['contract' => 'dms_store_coverage_projection_v1'],
            ];
        }

        return array_values(array_filter($rows, fn ($row) => (int) ($row['store_id'] ?? 0) > 0));
    }

    private function get_salesrep_staff_order_summary($tenantId, $date)
    {
        if (!$this->salesrep_table_exists('orders')) {
            return [];
        }

        $query = $this->run_salesrep_query(
            'SELECT user_id, COUNT(*) as order_count, SUM(total_amount) as order_amount
             FROM salesrep.orders
             WHERE crm_tenant_id = ? AND order_date = ?
             GROUP BY user_id',
            [(int) $tenantId, (string) $date]
        );
        if (!$query) {
            return [];
        }

        $indexed = [];
        foreach ($query->result_array() as $row) {
            $indexed[(int) ($row['user_id'] ?? 0)] = $row;
        }

        return $indexed;
    }

    private function get_dms_projected_top_ordered_products($tenantId, $limit = 5)
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0 || !$this->ensure_dms_projection_tables() || !$this->dms_table_exists('dms_product_sales_projection')) {
            return [];
        }

        $rows = $this->landlord_db()
            ->select('product_id, product_name, sold_quantity_month as quantity, sold_amount_month as total_price')
            ->where('tenant_id', $tenantId)
            ->order_by('sold_quantity_month', 'desc')
            ->order_by('sold_amount_month', 'desc')
            ->limit(max(1, (int) $limit))
            ->get(db_prefix() . 'dms_product_sales_projection')
            ->result_array();

        return $rows ?: [];
    }

    private function get_dms_projected_sales_team_today($tenantId)
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0 || !$this->ensure_dms_projection_tables() || !$this->dms_table_exists('dms_staff_activity_projection')) {
            return [];
        }

        $rows = $this->landlord_db()
            ->select('staff_id, staff_name, role_code as role, manager_staff_id as manager_crm_staff_id, sync_status, online_state as tracking_status, last_seen_at, last_checkin_at, last_visit_at, today_checkin_count as today_checkins, today_visit_count as today_visits')
            ->where('tenant_id', $tenantId)
            ->order_by('last_seen_at', 'desc')
            ->order_by('staff_name', 'asc')
            ->get(db_prefix() . 'dms_staff_activity_projection')
            ->result_array();

        foreach ($rows as &$row) {
            $row['access_enabled'] = true;
            $row['latitude'] = null;
            $row['longitude'] = null;
        }
        unset($row);

        return $rows ?: [];
    }

    private function get_dms_projected_store_coverage_summary($tenantId)
    {
        $summary = [
            'stores_visited_today' => 0,
            'stores_visited_month' => 0,
            'projection_rows' => 0,
        ];

        $tenantId = (int) $tenantId;
        if ($tenantId <= 0 || !$this->ensure_dms_projection_tables() || !$this->dms_table_exists('dms_store_coverage_projection')) {
            return $summary;
        }

        $row = $this->landlord_db()
            ->select('COUNT(*) as total, SUM(CASE WHEN visited_today = 1 THEN 1 ELSE 0 END) as today, SUM(CASE WHEN visited_this_month = 1 THEN 1 ELSE 0 END) as month', false)
            ->where('tenant_id', $tenantId)
            ->get(db_prefix() . 'dms_store_coverage_projection')
            ->row_array();

        return [
            'stores_visited_today' => (int) ($row['today'] ?? 0),
            'stores_visited_month' => (int) ($row['month'] ?? 0),
            'projection_rows' => (int) ($row['total'] ?? 0),
        ];
    }

    private function get_dms_top_ordered_products($tenantId, $dateFrom, $dateTo, $limit = 5)
    {
        if (!$this->salesrep_table_exists('orders') || !$this->salesrep_table_exists('order_items')) {
            return [];
        }
        $limit = max(1, (int) $limit);

        $query = $this->run_salesrep_query(
            'SELECT oi.product_id, COALESCE(p.name, CONCAT("Product #", oi.product_id)) as product_name, SUM(oi.quantity) as quantity, SUM(oi.total_price) as total_price
             FROM salesrep.order_items oi
             JOIN salesrep.orders o ON o.id = oi.order_id
             LEFT JOIN salesrep.products p ON p.id = oi.product_id
             WHERE o.crm_tenant_id = ? AND o.order_date >= ? AND o.order_date <= ?
             GROUP BY oi.product_id, p.name
             ORDER BY SUM(oi.quantity) DESC, SUM(oi.total_price) DESC
             LIMIT ' . $limit,
            [(int) $tenantId, (string) $dateFrom, (string) $dateTo]
        );

        return $query ? $query->result_array() : [];
    }

    private function salesrep_table_exists($table)
    {
        $table = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table);
        if ($table === '') {
            return false;
        }

        $query = $this->run_salesrep_query('SHOW TABLES FROM `salesrep` LIKE ' . $this->db->escape($table));

        return $query && $query->num_rows() > 0;
    }

    private function run_salesrep_query($sql, array $binds = [])
    {
        $db = $this->salesrep_db();
        if (!$db) {
            return null;
        }

        $previousDebug = $db->db_debug;
        $db->db_debug = false;

        try {
            return $db->query($sql, $binds);
        } catch (Throwable $e) {
            return null;
        } finally {
            $db->db_debug = $previousDebug;
        }
    }

    private function salesrep_db()
    {
        if (isset($this->_salesrep_db) && $this->_salesrep_db) {
            return $this->_salesrep_db;
        }

        $hostname = defined('APP_DB_HOSTNAME') ? APP_DB_HOSTNAME : $this->db->hostname;
        $username = defined('APP_DB_USERNAME') ? APP_DB_USERNAME : $this->db->username;
        $password = defined('APP_DB_PASSWORD') ? APP_DB_PASSWORD : $this->db->password;

        $config = [
            'dsn'      => '',
            'hostname' => $hostname,
            'username' => $username,
            'password' => $password,
            'database' => 'salesrep',
            'dbdriver' => $this->db->dbdriver ?: 'mysqli',
            'dbprefix' => '',
            'pconnect' => false,
            'db_debug' => false,
            'cache_on' => false,
            'cachedir' => '',
            'char_set' => 'utf8mb4',
            'dbcollat' => 'utf8mb4_general_ci',
            'swap_pre' => '',
            'encrypt'  => false,
            'compress' => false,
            'stricton' => false,
            'failover' => [],
            'save_queries' => false,
        ];

        $this->_salesrep_db = $this->load->database($config, true);

        return $this->_salesrep_db;
    }

    private function build_dms_query($table, $tenantId, array $filters = [], array $fieldMap = [], $dateColumn = null)
    {
        $db = $this->landlord_db()->from(db_prefix() . $table . ' d');
        if ($tenantId !== null) {
            $db->where('tenant_id', (int) $tenantId);
        }

        if (empty($fieldMap)) {
            foreach ($filters as $filterKey => $value) {
                if (in_array($filterKey, ['date_from', 'date_to'], true)) {
                    continue;
                }

                $fieldMap[$filterKey] = $filterKey;
            }
        }

        foreach ($fieldMap as $filterKey => $column) {
            if (!array_key_exists($filterKey, $filters)) {
                continue;
            }

            $value = $filters[$filterKey];
            if ($value === null || $value === '') {
                continue;
            }

            $db->where($column, $value);
        }

        if ($dateColumn !== null) {
            if (!empty($filters['date_from'])) {
                $db->where($dateColumn . ' >=', trim((string) $filters['date_from']) . ' 00:00:00');
            }
            if (!empty($filters['date_to'])) {
                $db->where($dateColumn . ' <=', trim((string) $filters['date_to']) . ' 23:59:59');
            }
        }

        return $db;
    }

    private function count_dms_records($table, $tenantId, array $filters = [], $dateColumn = null, array $fieldMap = [])
    {
        return (int) $this->build_dms_query($table, $tenantId, $filters, $fieldMap, $dateColumn)->count_all_results();
    }

    private function latest_dms_snapshot_date($table, $column, $tenantId)
    {
        if (!$this->dms_table_exists($table)) {
            return '';
        }

        $row = $this->landlord_db()
            ->select_max($column, 'latest_snapshot_date')
            ->where('tenant_id', (int) $tenantId)
            ->get(db_prefix() . $table)
            ->row_array();

        return trim((string) ($row['latest_snapshot_date'] ?? ''));
    }

    private function external_event_id(array $payload, $rawBody)
    {
        if (($payload['event_name'] ?? '') !== '' || isset($payload['sender']) || isset($payload['follower'])) {
            $normalized = $this->normalize_zalo_payload($payload);
            if (!empty($normalized['external_message_id'])) {
                return (string) $normalized['external_message_id'];
            }
            $parts = array_filter([
                (string) ($normalized['event_type'] ?? ''),
                (string) ($normalized['external_user_id'] ?? ''),
                (string) ($payload['timestamp'] ?? ''),
            ]);
            if (!empty($parts)) {
                return implode(':', $parts);
            }
        }

        $id = trim((string) ($payload['event_id'] ?? $payload['external_event_id'] ?? $payload['external_id'] ?? $payload['id'] ?? ''));

        return $id !== '' ? $id : sha1((string) $rawBody);
    }

    private function entity_type_from_event($eventType, array $payload)
    {
        $candidate = strtolower(trim((string) ($payload['entity_type'] ?? $eventType)));
        if (strpos($candidate, 'order') !== false) {
            return 'order';
        }
        if (strpos($candidate, 'message') !== false) {
            return 'message';
        }

        return 'lead';
    }

    private function generate_public_key()
    {
        do {
            $key = bin2hex(random_bytes(16));
            $exists = $this->landlord_db()
                ->where('public_key', $key)
                ->count_all_results(db_prefix() . 'kt_integration_connections') > 0;
        } while ($exists);

        return $key;
    }

    private function generate_webhook_secret()
    {
        return 'whsec_' . bin2hex(random_bytes(24));
    }

    private function merge_encrypted_value(array $existing, $field, $plain)
    {
        if ($plain === null) {
            return $existing[$field] ?? null;
        }

        $plain = trim((string) $plain);
        if ($plain === '') {
            return $existing[$field] ?? null;
        }

        return kt_integration_hub_encrypt_value($plain);
    }

    private function landlord_db()
    {
        $landlordDb = $this->config->item('kt_saas_landlord_db');

        if ($landlordDb && $landlordDb->table_exists(db_prefix() . 'kt_saas_tenants')) {
            return $landlordDb;
        }

        if ($this->db->table_exists(db_prefix() . 'kt_saas_tenants')) {
            $this->config->set_item('kt_saas_landlord_db', $this->db);
            return $this->db;
        }

        $database = $this->discover_landlord_database_name();
        $config = [
            'dsn'          => '',
            'hostname'     => APP_DB_HOSTNAME,
            'username'     => APP_DB_USERNAME,
            'password'     => APP_DB_PASSWORD,
            'database'     => $database,
            'dbdriver'     => defined('APP_DB_DRIVER') ? APP_DB_DRIVER : 'mysqli',
            'dbprefix'     => db_prefix(),
            'pconnect'     => false,
            'db_debug'     => (ENVIRONMENT !== 'production'),
            'cache_on'     => false,
            'cachedir'     => '',
            'char_set'     => defined('APP_DB_CHARSET') ? APP_DB_CHARSET : 'utf8mb4',
            'dbcollat'     => defined('APP_DB_COLLATION') ? APP_DB_COLLATION : 'utf8mb4_unicode_ci',
            'swap_pre'     => '',
            'encrypt'      => defined('APP_DB_ENCRYPT') ? APP_DB_ENCRYPT : false,
            'compress'     => false,
            'stricton'     => false,
            'failover'     => [],
            'save_queries' => true,
        ];
        $db = $this->load->database($config, true);
        $this->config->set_item('kt_saas_landlord_db', $db);

        return $db;
    }

    private function discover_landlord_database_name()
    {
        $candidates = [];

        foreach (['KT_SAAS_LANDLORD_DB_NAME', 'LANDLORD_DB_NAME', 'APP_LANDLORD_DB_NAME'] as $envKey) {
            $value = trim((string) getenv($envKey));
            if ($value !== '') {
                $candidates[] = $value;
            }
        }

        foreach (['khachtot', 'crm', defined('APP_DB_NAME') ? (string) APP_DB_NAME : ''] as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $candidates[] = $value;
            }
        }

        $candidates = array_values(array_unique($candidates));
        $currentDatabase = isset($this->db->database) ? strtolower((string) $this->db->database) : '';

        foreach ($candidates as $database) {
            if (strtolower((string) $database) === $currentDatabase) {
                continue;
            }

            $config = [
                'dsn'          => '',
                'hostname'     => APP_DB_HOSTNAME,
                'username'     => APP_DB_USERNAME,
                'password'     => APP_DB_PASSWORD,
                'database'     => $database,
                'dbdriver'     => defined('APP_DB_DRIVER') ? APP_DB_DRIVER : 'mysqli',
                'dbprefix'     => db_prefix(),
                'pconnect'     => false,
                'db_debug'     => false,
                'cache_on'     => false,
                'cachedir'     => '',
                'char_set'     => defined('APP_DB_CHARSET') ? APP_DB_CHARSET : 'utf8mb4',
                'dbcollat'     => defined('APP_DB_COLLATION') ? APP_DB_COLLATION : 'utf8mb4_unicode_ci',
                'swap_pre'     => '',
                'encrypt'      => defined('APP_DB_ENCRYPT') ? APP_DB_ENCRYPT : false,
                'compress'     => false,
                'stricton'     => false,
                'failover'     => [],
                'save_queries' => true,
            ];

            $db = $this->load->database($config, true);
            if ($db && $db->table_exists(db_prefix() . 'kt_saas_tenants')) {
                $this->config->set_item('kt_saas_landlord_db', $db);
                return $database;
            }
        }

        return defined('APP_DB_NAME') ? APP_DB_NAME : '';
    }

    private function dms_table_exists($table)
    {
        return $this->landlord_db()->table_exists(db_prefix() . $table);
    }

    private function ensure_dms_projection_tables()
    {
        if (isset($this->_dms_projection_tables_ready) && $this->_dms_projection_tables_ready) {
            return true;
        }

        $db = $this->landlord_db();
        $charset = $db->char_set ?: 'utf8mb4';
        $collation = $db->dbcollat ?: 'utf8mb4_unicode_ci';
        if (stripos($collation, '0900') !== false) {
            $collation = 'utf8mb4_unicode_ci';
        }

        $orderTable = db_prefix() . 'dms_order_projection';
        $locationTable = db_prefix() . 'dms_location_projection';
        $inventoryTable = db_prefix() . 'dms_inventory_projection';
        $productSalesTable = db_prefix() . 'dms_product_sales_projection';
        $staffActivityTable = db_prefix() . 'dms_staff_activity_projection';
        $storeCoverageTable = db_prefix() . 'dms_store_coverage_projection';
        $fieldFeedbackTable = db_prefix() . 'dms_field_feedback_projection';

        $ok = $db->query("CREATE TABLE IF NOT EXISTS `" . $orderTable . "` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `tenant_id` INT UNSIGNED NOT NULL,
          `company_id` INT UNSIGNED NOT NULL,
          `dms_workspace_host` VARCHAR(191) NULL,
          `source` VARCHAR(64) NOT NULL DEFAULT 'salesrep',
          `source_record_id` BIGINT UNSIGNED NOT NULL,
          `source_record_type` VARCHAR(64) NOT NULL DEFAULT 'order',
          `projection_version` INT UNSIGNED NOT NULL DEFAULT 1,
          `sync_status` VARCHAR(32) NOT NULL DEFAULT 'synced',
          `dms_order_id` BIGINT UNSIGNED NOT NULL,
          `dms_order_number` VARCHAR(191) NULL,
          `crm_invoice_id` INT UNSIGNED NULL,
          `store_id` BIGINT UNSIGNED NULL,
          `staff_id` BIGINT UNSIGNED NULL,
          `order_status` VARCHAR(64) NULL,
          `order_total` DECIMAL(15,2) NULL,
          `currency` VARCHAR(12) NOT NULL DEFAULT 'VND',
          `invoice_status` VARCHAR(64) NULL,
          `invoice_total` DECIMAL(15,2) NULL,
          `payment_status` VARCHAR(64) NULL,
          `paid_amount` DECIMAL(15,2) NULL,
          `balance_amount` DECIMAL(15,2) NULL,
          `payment_source` VARCHAR(64) NOT NULL DEFAULT 'unknown',
          `submitted_at` DATETIME NULL,
          `approved_at` DATETIME NULL,
          `synced_at` DATETIME NULL,
          `last_readback_at` DATETIME NULL,
          `last_synced_at` DATETIME NULL,
          `checksum` VARCHAR(64) NULL,
          `metadata_json` MEDIUMTEXT NULL,
          `created_at` DATETIME NULL,
          `updated_at` DATETIME NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `tenant_source_record` (`tenant_id`, `source_record_type`, `source_record_id`, `projection_version`),
          KEY `tenant_order` (`tenant_id`, `dms_order_id`),
          KEY `tenant_invoice` (`tenant_id`, `crm_invoice_id`),
          KEY `tenant_synced` (`tenant_id`, `last_synced_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";");

        $ok = $ok && $db->query("CREATE TABLE IF NOT EXISTS `" . $locationTable . "` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `tenant_id` INT UNSIGNED NOT NULL,
          `company_id` INT UNSIGNED NOT NULL,
          `dms_workspace_host` VARCHAR(191) NULL,
          `source` VARCHAR(64) NOT NULL DEFAULT 'salesrep',
          `source_record_id` BIGINT UNSIGNED NOT NULL,
          `source_record_type` VARCHAR(64) NOT NULL,
          `projection_version` INT UNSIGNED NOT NULL DEFAULT 1,
          `sync_status` VARCHAR(32) NOT NULL DEFAULT 'synced',
          `marker_type` VARCHAR(32) NOT NULL,
          `staff_id` BIGINT UNSIGNED NULL,
          `store_id` BIGINT UNSIGNED NULL,
          `order_id` BIGINT UNSIGNED NULL,
          `latitude` DECIMAL(10,8) NOT NULL,
          `longitude` DECIMAL(11,8) NOT NULL,
          `accuracy` DECIMAL(10,2) NULL,
          `location_source` VARCHAR(64) NOT NULL DEFAULT 'store_coordinates',
          `recorded_at` DATETIME NULL,
          `label` VARCHAR(191) NULL,
          `status` VARCHAR(64) NULL,
          `last_synced_at` DATETIME NULL,
          `checksum` VARCHAR(64) NULL,
          `metadata_json` MEDIUMTEXT NULL,
          `created_at` DATETIME NULL,
          `updated_at` DATETIME NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `tenant_marker_source` (`tenant_id`, `marker_type`, `source_record_type`, `source_record_id`, `projection_version`),
          KEY `tenant_marker` (`tenant_id`, `marker_type`),
          KEY `tenant_recorded` (`tenant_id`, `recorded_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";");

        $ok = $ok && $db->query("CREATE TABLE IF NOT EXISTS `" . $inventoryTable . "` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `tenant_id` INT UNSIGNED NOT NULL,
          `company_id` INT UNSIGNED NOT NULL,
          `dms_workspace_host` VARCHAR(191) NULL,
          `store_id` BIGINT UNSIGNED NULL,
          `store_name` VARCHAR(191) NULL,
          `product_id` BIGINT UNSIGNED NULL,
          `product_name` VARCHAR(191) NULL,
          `sku` VARCHAR(191) NULL,
          `stock_on_hand` DECIMAL(15,2) NULL,
          `reserved_quantity` DECIMAL(15,2) NULL,
          `low_stock_threshold` DECIMAL(15,2) NULL,
          `is_low_stock` TINYINT(1) NOT NULL DEFAULT 0,
          `movement_type` VARCHAR(64) NULL,
          `movement_quantity` DECIMAL(15,2) NULL,
          `movement_at` DATETIME NULL,
          `reference_order_id` BIGINT UNSIGNED NULL,
          `staff_id` BIGINT UNSIGNED NULL,
          `source` VARCHAR(64) NOT NULL DEFAULT 'salesrep',
          `source_record_id` BIGINT UNSIGNED NOT NULL,
          `source_record_type` VARCHAR(64) NOT NULL DEFAULT 'inventory',
          `projection_version` INT UNSIGNED NOT NULL DEFAULT 1,
          `sync_status` VARCHAR(32) NOT NULL DEFAULT 'synced',
          `last_synced_at` DATETIME NULL,
          `checksum` VARCHAR(64) NULL,
          `metadata_json` MEDIUMTEXT NULL,
          `created_at` DATETIME NULL,
          `updated_at` DATETIME NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `tenant_inventory_source` (`tenant_id`, `source_record_type`, `source_record_id`, `projection_version`),
          KEY `tenant_low_stock` (`tenant_id`, `is_low_stock`),
          KEY `tenant_store_product` (`tenant_id`, `store_id`, `product_id`),
          KEY `tenant_movement` (`tenant_id`, `movement_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";");

        $ok = $ok && $db->query("CREATE TABLE IF NOT EXISTS `" . $productSalesTable . "` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `tenant_id` INT UNSIGNED NOT NULL,
          `company_id` INT UNSIGNED NOT NULL,
          `dms_workspace_host` VARCHAR(191) NULL,
          `product_id` BIGINT UNSIGNED NULL,
          `product_name` VARCHAR(191) NULL,
          `sku` VARCHAR(191) NULL,
          `sold_quantity_day` DECIMAL(15,2) NOT NULL DEFAULT 0,
          `sold_amount_day` DECIMAL(15,2) NOT NULL DEFAULT 0,
          `sold_quantity_month` DECIMAL(15,2) NOT NULL DEFAULT 0,
          `sold_amount_month` DECIMAL(15,2) NOT NULL DEFAULT 0,
          `order_count_day` INT UNSIGNED NOT NULL DEFAULT 0,
          `order_count_month` INT UNSIGNED NOT NULL DEFAULT 0,
          `source` VARCHAR(64) NOT NULL DEFAULT 'salesrep',
          `source_record_id` BIGINT UNSIGNED NOT NULL,
          `source_record_type` VARCHAR(64) NOT NULL DEFAULT 'product_sales',
          `projection_version` INT UNSIGNED NOT NULL DEFAULT 1,
          `sync_status` VARCHAR(32) NOT NULL DEFAULT 'synced',
          `last_synced_at` DATETIME NULL,
          `checksum` VARCHAR(64) NULL,
          `metadata_json` MEDIUMTEXT NULL,
          `created_at` DATETIME NULL,
          `updated_at` DATETIME NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `tenant_product_sales_source` (`tenant_id`, `source_record_type`, `source_record_id`, `projection_version`),
          KEY `tenant_product` (`tenant_id`, `product_id`),
          KEY `tenant_sold_month` (`tenant_id`, `sold_quantity_month`, `sold_amount_month`)
        ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";");

        $ok = $ok && $db->query("CREATE TABLE IF NOT EXISTS `" . $staffActivityTable . "` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `tenant_id` INT UNSIGNED NOT NULL,
          `company_id` INT UNSIGNED NOT NULL,
          `dms_workspace_host` VARCHAR(191) NULL,
          `staff_id` BIGINT UNSIGNED NOT NULL,
          `staff_name` VARCHAR(191) NULL,
          `crm_staff_id` BIGINT UNSIGNED NULL,
          `manager_staff_id` BIGINT UNSIGNED NULL,
          `role_code` VARCHAR(64) NULL,
          `online_state` VARCHAR(32) NOT NULL DEFAULT 'offline',
          `last_seen_at` DATETIME NULL,
          `last_checkin_at` DATETIME NULL,
          `last_visit_at` DATETIME NULL,
          `today_checkin_count` INT UNSIGNED NOT NULL DEFAULT 0,
          `today_visit_count` INT UNSIGNED NOT NULL DEFAULT 0,
          `today_order_count` INT UNSIGNED NOT NULL DEFAULT 0,
          `today_order_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
          `missed_visit_count` INT UNSIGNED NOT NULL DEFAULT 0,
          `source` VARCHAR(64) NOT NULL DEFAULT 'salesrep',
          `source_record_id` BIGINT UNSIGNED NOT NULL,
          `source_record_type` VARCHAR(64) NOT NULL DEFAULT 'staff_activity',
          `projection_version` INT UNSIGNED NOT NULL DEFAULT 1,
          `sync_status` VARCHAR(32) NOT NULL DEFAULT 'synced',
          `last_synced_at` DATETIME NULL,
          `checksum` VARCHAR(64) NULL,
          `metadata_json` MEDIUMTEXT NULL,
          `created_at` DATETIME NULL,
          `updated_at` DATETIME NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `tenant_staff_activity_source` (`tenant_id`, `source_record_type`, `source_record_id`, `projection_version`),
          KEY `tenant_staff` (`tenant_id`, `staff_id`),
          KEY `tenant_online` (`tenant_id`, `online_state`)
        ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";");

        $ok = $ok && $db->query("CREATE TABLE IF NOT EXISTS `" . $storeCoverageTable . "` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `tenant_id` INT UNSIGNED NOT NULL,
          `company_id` INT UNSIGNED NOT NULL,
          `dms_workspace_host` VARCHAR(191) NULL,
          `store_id` BIGINT UNSIGNED NOT NULL,
          `store_name` VARCHAR(191) NULL,
          `crm_customer_id` BIGINT UNSIGNED NULL,
          `assigned_staff_id` BIGINT UNSIGNED NULL,
          `visited_today` TINYINT(1) NOT NULL DEFAULT 0,
          `visited_this_month` TINYINT(1) NOT NULL DEFAULT 0,
          `last_visit_at` DATETIME NULL,
          `last_checkin_at` DATETIME NULL,
          `today_order_count` INT UNSIGNED NOT NULL DEFAULT 0,
          `month_order_count` INT UNSIGNED NOT NULL DEFAULT 0,
          `coverage_date` DATE NULL,
          `coverage_month` CHAR(7) NULL,
          `source` VARCHAR(64) NOT NULL DEFAULT 'salesrep',
          `source_record_id` BIGINT UNSIGNED NOT NULL,
          `source_record_type` VARCHAR(64) NOT NULL DEFAULT 'store_coverage',
          `projection_version` INT UNSIGNED NOT NULL DEFAULT 1,
          `sync_status` VARCHAR(32) NOT NULL DEFAULT 'synced',
          `last_synced_at` DATETIME NULL,
          `checksum` VARCHAR(64) NULL,
          `metadata_json` MEDIUMTEXT NULL,
          `created_at` DATETIME NULL,
          `updated_at` DATETIME NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `tenant_store_coverage_source` (`tenant_id`, `source_record_type`, `source_record_id`, `projection_version`),
          KEY `tenant_store` (`tenant_id`, `store_id`),
          KEY `tenant_coverage` (`tenant_id`, `coverage_date`, `coverage_month`)
        ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";");

        $ok = $ok && $db->query("CREATE TABLE IF NOT EXISTS `" . $fieldFeedbackTable . "` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `tenant_id` INT UNSIGNED NOT NULL,
          `company_id` INT UNSIGNED NOT NULL,
          `dms_workspace_host` VARCHAR(191) NULL,
          `source` VARCHAR(64) NOT NULL DEFAULT 'salesrep',
          `source_record_id` BIGINT UNSIGNED NOT NULL,
          `source_record_type` VARCHAR(64) NOT NULL DEFAULT 'field_feedback',
          `projection_version` INT UNSIGNED NOT NULL DEFAULT 1,
          `sync_status` VARCHAR(32) NOT NULL DEFAULT 'synced',
          `last_synced_at` DATETIME NULL,
          `checksum` VARCHAR(64) NULL,
          `feedback_type` VARCHAR(64) NOT NULL,
          `severity` VARCHAR(32) NOT NULL DEFAULT 'normal',
          `status` VARCHAR(32) NOT NULL DEFAULT 'new',
          `staff_name` VARCHAR(191) NULL,
          `staff_email` VARCHAR(191) NULL,
          `store_name` VARCHAR(191) NULL,
          `store_external_id` VARCHAR(191) NULL,
          `title` VARCHAR(255) NOT NULL,
          `description_summary` TEXT NULL,
          `submitted_at` DATETIME NULL,
          `reviewed_at` DATETIME NULL,
          `resolved_at` DATETIME NULL,
          `latitude` DECIMAL(10,8) NULL,
          `longitude` DECIMAL(11,8) NULL,
          `gps_accuracy` DECIMAL(10,2) NULL,
          `photo_count` INT UNSIGNED NOT NULL DEFAULT 0,
          `metadata_json` MEDIUMTEXT NULL,
          `created_at` DATETIME NULL,
          `updated_at` DATETIME NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `tenant_feedback_source` (`tenant_id`, `source_record_type`, `source_record_id`, `projection_version`),
          KEY `tenant_status` (`tenant_id`, `status`),
          KEY `tenant_type` (`tenant_id`, `feedback_type`),
          KEY `tenant_submitted` (`tenant_id`, `submitted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";");

        $this->_dms_projection_tables_ready = (bool) $ok;

        return $this->_dms_projection_tables_ready;
    }

    private function has_salesflow_connection($tenantId)
    {
        return !empty($this->get_salesflow_connection((int) $tenantId));
    }

    private function get_dms_marketplace_provisioning_snapshot($tenantId)
    {
        $tenantId = (int) $tenantId;
        $snapshot = [
            'provisioning_status' => '',
            'queued_jobs' => 0,
            'running_jobs' => 0,
            'failed_jobs' => 0,
            'completed_jobs' => 0,
            'latest_job_type' => null,
            'latest_job_status' => null,
        ];

        if ($tenantId <= 0) {
            return $snapshot;
        }

        $tenantTable = db_prefix() . 'kt_saas_tenants';
        if ($this->landlord_db()->table_exists($tenantTable)) {
            $tenantRow = $this->landlord_db()
                ->select('provisioning_status')
                ->where('id', $tenantId)
                ->get($tenantTable)
                ->row_array();

            if ($tenantRow) {
                $snapshot['provisioning_status'] = trim((string) ($tenantRow['provisioning_status'] ?? ''));
            }
        }

        $jobsTable = db_prefix() . 'kt_saas_provision_jobs';
        if (!$this->landlord_db()->table_exists($jobsTable)) {
            return $snapshot;
        }

        $rows = $this->landlord_db()
            ->select('status, COUNT(*) as total')
            ->from($jobsTable)
            ->where('tenant_id', $tenantId)
            ->group_by('status')
            ->get()
            ->result_array();

        foreach ($rows as $row) {
            $count = (int) ($row['total'] ?? 0);
            switch (strtolower((string) ($row['status'] ?? ''))) {
                case 'queued':
                    $snapshot['queued_jobs'] = $count;
                    break;
                case 'running':
                    $snapshot['running_jobs'] = $count;
                    break;
                case 'failed':
                    $snapshot['failed_jobs'] = $count;
                    break;
                case 'completed':
                case 'done':
                    $snapshot['completed_jobs'] += $count;
                    break;
            }
        }

        $latest = $this->landlord_db()
            ->select('job_type, status')
            ->from($jobsTable)
            ->where('tenant_id', $tenantId)
            ->order_by('id', 'desc')
            ->limit(1)
            ->get()
            ->row_array();

        if ($latest) {
            $snapshot['latest_job_type'] = (string) ($latest['job_type'] ?? '');
            $snapshot['latest_job_status'] = (string) ($latest['status'] ?? '');
        }

        return $snapshot;
    }

    private function count_successful_staff_sync_jobs($tenantId)
    {
        return (int) $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->where('provider_code', 'salesflow_dms')
            ->where('job_type', 'sync_staff_to_dms')
            ->where('status', 'done')
            ->count_all_results(db_prefix() . 'kt_integration_sync_jobs');
    }

    private function count_tenant_active_customers($tenantId)
    {
        $tenant = $this->get_tenant((int) $tenantId);
        if (!$tenant) {
            return null;
        }

        $landlordDb = $this->landlord_db();
        $CI = &get_instance();
        $previousDb = $CI->db;

        if (!class_exists('DatabaseSwitcher', false)) {
            require_once module_dir_path('kt_saas', 'tenant_bootstrap/DatabaseSwitcher.php');
        }

        $switcher = new DatabaseSwitcher();
        $switchResult = $switcher->switchConnection($tenant);
        if (empty($switchResult['switched'])) {
            return null;
        }

        $this->db = $CI->db;

        try {
            return (int) $this->db
                ->from(db_prefix() . 'clients')
                ->where('active', 1)
                ->count_all_results();
        } catch (Throwable $e) {
            return null;
        } finally {
            $CI->db = $landlordDb ?: $previousDb;
            $this->db = $CI->db;
            $CI->config->set_item('kt_saas_landlord_db', $landlordDb ?: $previousDb);
        }
    }

    private function get_landlord_dms_last_invoice($tenantId): array
    {
        $rows = $this->get_landlord_dms_invoices((int) $tenantId, 1);

        return $rows[0] ?? [];
    }

    private function get_landlord_dms_last_payment($tenantId): array
    {
        $rows = $this->get_landlord_dms_payments((int) $tenantId, 1);

        return $rows[0] ?? [];
    }

    private function get_landlord_dms_invoices($tenantId, $limit = 20): array
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0 || !$this->landlord_db()->table_exists(db_prefix() . 'kt_saas_invoices')) {
            return [];
        }

        $rows = $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->where('deleted_at IS NULL', null, false)
            ->order_by('id', 'desc')
            ->limit(max(1, (int) $limit))
            ->get(db_prefix() . 'kt_saas_invoices')
            ->result_array();

        return array_values(array_filter($rows, static function ($row) {
            $payload = kt_integration_hub_json_decode((string) ($row['payload_json'] ?? ''), []);
            $reason = strtolower(trim((string) ($payload['reason'] ?? ($payload['context']['reason'] ?? ''))));

            return $reason === 'dms_addon_purchase';
        }));
    }

    private function get_landlord_dms_payments($tenantId, $limit = 20): array
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0 || !$this->landlord_db()->table_exists(db_prefix() . 'kt_saas_payments')) {
            return [];
        }

        $dmsInvoices = $this->get_landlord_dms_invoices($tenantId, 50);
        $invoiceIds = array_values(array_filter(array_map(static fn ($row) => (int) ($row['id'] ?? 0), $dmsInvoices)));
        if (empty($invoiceIds)) {
            return [];
        }

        return $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->where_in('invoice_id', $invoiceIds)
            ->where('deleted_at IS NULL', null, false)
            ->order_by('id', 'desc')
            ->limit(max(1, (int) $limit))
            ->get(db_prefix() . 'kt_saas_payments')
            ->result_array();
    }

    private function record_landlord_dms_action($tenantId, string $action, array $before, array $after): void
    {
        $this->ensure_dms_landlord_management_tables();
        $actorId = function_exists('get_staff_user_id') ? (int) get_staff_user_id() : 0;
        $actorEmail = '';
        if ($actorId > 0 && function_exists('get_staff')) {
            $staff = get_staff($actorId);
            if (is_object($staff)) {
                $actorEmail = (string) ($staff->email ?? '');
            } elseif (is_array($staff)) {
                $actorEmail = (string) ($staff['email'] ?? '');
            }
        }

        $this->landlord_db()->insert(db_prefix() . 'kt_dms_landlord_actions', [
            'actor_user_id' => $actorId ?: null,
            'actor_email' => $actorEmail,
            'tenant_id' => (int) $tenantId,
            'company_id' => (int) $tenantId,
            'action' => $action,
            'before_json' => kt_integration_hub_json_encode(kt_integration_hub_redact_secrets($before)),
            'after_json' => kt_integration_hub_json_encode(kt_integration_hub_redact_secrets($after)),
            'ip' => $this->input ? (string) $this->input->ip_address() : '',
            'user_agent' => $this->input ? substr((string) $this->input->user_agent(), 0, 255) : '',
            'created_at' => kt_integration_hub_now(),
        ]);
    }

    private function ensure_dms_landlord_management_tables(): bool
    {
        $db = $this->landlord_db();
        $charset = 'utf8mb4';
        $collation = 'utf8mb4_unicode_ci';
        $actionsTable = db_prefix() . 'kt_dms_landlord_actions';
        $overridesTable = db_prefix() . 'kt_dms_tenant_overrides';
        $syncScopeTable = db_prefix() . 'kt_dms_sync_scope';

        $ok = true;
        if (!$db->table_exists($actionsTable)) {
            $ok = $ok && $db->query("CREATE TABLE IF NOT EXISTS `" . $actionsTable . "` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `actor_user_id` INT NULL,
              `actor_email` VARCHAR(191) NULL,
              `tenant_id` BIGINT UNSIGNED NOT NULL,
              `company_id` BIGINT UNSIGNED NULL,
              `action` VARCHAR(80) NOT NULL,
              `before_json` MEDIUMTEXT NULL,
              `after_json` MEDIUMTEXT NULL,
              `ip` VARCHAR(64) NULL,
              `user_agent` VARCHAR(255) NULL,
              `created_at` DATETIME NOT NULL,
              PRIMARY KEY (`id`),
              KEY `tenant_action` (`tenant_id`, `action`),
              KEY `actor_created` (`actor_user_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";");
        }

        if (!$db->table_exists($overridesTable)) {
            $ok = $ok && $db->query("CREATE TABLE IF NOT EXISTS `" . $overridesTable . "` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `tenant_id` BIGINT UNSIGNED NOT NULL,
              `company_id` BIGINT UNSIGNED NULL,
              `feature_overrides_json` MEDIUMTEXT NULL,
              `quota_overrides_json` MEDIUMTEXT NULL,
              `created_at` DATETIME NULL,
              `updated_at` DATETIME NULL,
              `updated_by` INT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `tenant_unique` (`tenant_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";");
        }

        if (!$db->table_exists($syncScopeTable)) {
            $ok = $ok && $db->query("CREATE TABLE IF NOT EXISTS `" . $syncScopeTable . "` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `tenant_id` BIGINT UNSIGNED NOT NULL,
              `company_id` BIGINT UNSIGNED NULL,
              `scope_json` MEDIUMTEXT NULL,
              `created_at` DATETIME NULL,
              `updated_at` DATETIME NULL,
              `updated_by` INT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `tenant_unique` (`tenant_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . ";");
        }

        return (bool) $ok;
    }

    private function tiktok_client()
    {
        if (!class_exists('TikTokShopApiClient', false)) {
            require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/TikTokShopApiClient.php');
        }

        return new TikTokShopApiClient();
    }

    private function get_salesflow_connection($tenantId)
    {
        return $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->where('provider_code', 'salesflow_dms')
            ->order_by('id', 'desc')
            ->get(db_prefix() . 'kt_integration_connections')
            ->row_array();
    }

    private function build_staff_sync_payload(array $tenant, $staffId)
    {
        $producer = new StaffPayloadProducer();
        $staffId = (int) $staffId;
        if ($staffId <= 0) {
            throw new RuntimeException('Staff id is required.');
        }

        $landlordDb = $this->landlord_db();
        $CI = &get_instance();
        $previousDb = $CI->db;

        if (!class_exists('DatabaseSwitcher', false)) {
            require_once module_dir_path('kt_saas', 'tenant_bootstrap/DatabaseSwitcher.php');
        }

        $switcher = new DatabaseSwitcher();
        $switchResult = $switcher->switchConnection($tenant);
        if (empty($switchResult['switched'])) {
            throw new RuntimeException('Tenant DB switch failed: ' . (string) ($switchResult['message'] ?? 'unknown'));
        }

        $this->db = $CI->db;

        try {
            $staffRow = $this->db
                ->select('staffid, email, firstname, lastname, phonenumber, admin, role, active, default_language, datecreated, last_login, last_activity, last_password_change')
                ->from(db_prefix() . 'staff')
                ->where('staffid', $staffId)
                ->get()
                ->row_array();

            if (!$staffRow) {
                throw new RuntimeException('Staff not found in tenant source.');
            }

            $roleRow = null;
            if (!empty($staffRow['role'])) {
                $roleRow = $this->db
                    ->select('roleid, name')
                    ->from(db_prefix() . 'roles')
                    ->where('roleid', (int) $staffRow['role'])
                    ->get()
                    ->row_array();
            }

            $dmsAccess = $landlordDb
                ->where('tenant_id', (int) ($tenant['id'] ?? 0))
                ->where('crm_staff_id', $staffId)
                ->get(db_prefix() . 'kt_dms_staff_access')
                ->row_array();

            return $producer->buildPayload($tenant, $staffRow, $roleRow, $dmsAccess ?: []);
        } finally {
            $CI->db = $landlordDb ?: $previousDb;
            $this->db = $CI->db;
            $CI->config->set_item('kt_saas_landlord_db', $landlordDb ?: $previousDb);
        }
    }

    private function build_customer_sync_payload(array $tenant, $customerId)
    {
        $producer = new CustomerPayloadProducer();
        $customerId = (int) $customerId;
        if ($customerId <= 0) {
            throw new RuntimeException('Customer id is required.');
        }

        $landlordDb = $this->landlord_db();
        $CI = &get_instance();
        $previousDb = $CI->db;

        if (!class_exists('DatabaseSwitcher', false)) {
            require_once module_dir_path('kt_saas', 'tenant_bootstrap/DatabaseSwitcher.php');
        }

        $switcher = new DatabaseSwitcher();
        $switchResult = $switcher->switchConnection($tenant);
        if (empty($switchResult['switched'])) {
            throw new RuntimeException('Tenant DB switch failed: ' . (string) ($switchResult['message'] ?? 'unknown'));
        }

        $this->db = $CI->db;

        try {
            $clientRow = $this->db
                ->select('userid, company, vat, phonenumber, address, city, state, billing_street, billing_city, billing_state, active, datecreated')
                ->from(db_prefix() . 'clients')
                ->where('userid', $customerId)
                ->get()
                ->row_array();

            if (!$clientRow) {
                throw new RuntimeException('Customer not found in tenant source.');
            }

            $clientRow['_bridge_updated_at'] = date('Y-m-d H:i:s');

            $contacts = $this->db
                ->select('id, userid, firstname, lastname, email, phonenumber, title, is_primary, active')
                ->from(db_prefix() . 'contacts')
                ->where('userid', $customerId)
                ->where('active', 1)
                ->order_by('is_primary', 'desc')
                ->order_by('id', 'asc')
                ->get()
                ->result_array();

            $primaryContact = null;
            foreach ($contacts as $contact) {
                if (!empty($contact['is_primary'])) {
                    $primaryContact = $contact;
                    break;
                }
            }

            if ($primaryContact === null && !empty($contacts)) {
                $primaryContact = $contacts[0];
            }

            return $producer->buildPayload($tenant, $clientRow, $primaryContact, $contacts);
        } finally {
            $CI->db = $landlordDb ?: $previousDb;
            $this->db = $CI->db;
            $CI->config->set_item('kt_saas_landlord_db', $landlordDb ?: $previousDb);
        }
    }

    public function get_dms_workspaces_for_tenant($tenantId, $companyId = null)
    {
        $tenantId = (int) $tenantId;
        $companyId = (int) $companyId;
        $this->ensure_dms_workspace_registry_table();
        if ($tenantId <= 0 || $companyId <= 0 || !$this->landlord_db()->table_exists(db_prefix() . 'kt_dms_workspaces')) {
            return [];
        }

        return $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->order_by('is_primary', 'DESC')
            ->order_by('workspace_name', 'ASC')
            ->get(db_prefix() . 'kt_dms_workspaces')
            ->result_array();
    }

    public function get_dms_workspaces_for_canonical_tenant($tenantId): array
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            return [];
        }

        if (!isset($this->Kt_saas_model)) {
            $this->load->model('kt_saas/Kt_saas_model');
        }

        $tenant = (array) $this->Kt_saas_model->get_tenant($tenantId);
        $companyId = (int) (kt_integration_hub_crm_company_id($tenant) ?? 0);
        if ($companyId <= 0) {
            return [];
        }

        $rows = $this->get_dms_workspaces_for_tenant($tenantId, $companyId);
        if (!empty($rows)) {
            return $rows;
        }

        return $this->repair_dms_workspace_registry_scope($tenantId, $companyId);
    }

    public function repair_dms_workspace_registry_scope($tenantId, $companyId): array
    {
        $tenantId = (int) $tenantId;
        $companyId = (int) $companyId;
        $this->ensure_dms_workspace_registry_table();
        $table = db_prefix() . 'kt_dms_workspaces';
        if ($tenantId <= 0 || $companyId <= 0 || !$this->landlord_db()->table_exists($table)) {
            return [];
        }

        $compatRows = $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $tenantId)
            ->order_by('is_primary', 'DESC')
            ->order_by('workspace_name', 'ASC')
            ->get($table)
            ->result_array();

        foreach ($compatRows as $row) {
            $this->landlord_db()
                ->where('id', (int) $row['id'])
                ->where('tenant_id', $tenantId)
                ->update($table, [
                    'company_id' => $companyId,
                    'source_version' => 'canonical-scope-repair',
                    'last_synced_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }

        return $this->get_dms_workspaces_for_tenant($tenantId, $companyId);
    }

    public function get_dms_bootstrap_source_counts($tenantId): array
    {
        $tenantId = (int) $tenantId;
        $defaults = ['staff' => 0, 'customers' => 0, 'products' => 0, 'warehouses' => 0, 'available' => false, 'error' => null];
        if ($tenantId <= 0) {
            return array_merge($defaults, ['error' => 'TENANT_ID_REQUIRED']);
        }

        if (!isset($this->Kt_saas_model)) {
            $this->load->model('kt_saas/Kt_saas_model');
        }

        $tenant = (array) $this->Kt_saas_model->get_tenant($tenantId);
        if (empty($tenant)) {
            return array_merge($defaults, ['error' => 'TENANT_NOT_FOUND']);
        }

        $CI = &get_instance();
        $previousDb = $CI->db;

        if (!class_exists('DatabaseSwitcher', false)) {
            require_once module_dir_path('kt_saas', 'tenant_bootstrap/DatabaseSwitcher.php');
        }

        try {
            $switcher = new DatabaseSwitcher();
            $switchResult = $switcher->switchConnection($tenant);
            if (empty($switchResult['switched'])) {
                return array_merge($defaults, ['error' => 'TENANT_DB_SWITCH_FAILED']);
            }

            $this->db = $CI->db;
            $count = function (string $table): int {
                return $this->db->table_exists(db_prefix() . $table)
                    ? (int) $this->db->count_all_results(db_prefix() . $table)
                    : 0;
            };

            return [
                'staff' => $count('staff'),
                'customers' => $count('clients'),
                'products' => $count('items'),
                'warehouses' => $count('kt_warehouses'),
                'available' => true,
                'error' => null,
            ];
        } catch (Throwable $e) {
            return array_merge($defaults, ['error' => $e->getMessage()]);
        } finally {
            $CI->db = $previousDb;
            $this->db = $previousDb;
            $CI->config->set_item('kt_saas_landlord_db', $previousDb);
        }
    }

    public function save_dms_workspace_mutation_result($tenantId, $companyId, array $result): void
    {
        $tenantId = (int) $tenantId; $companyId = (int) $companyId;
        if ($tenantId <= 0 || $companyId <= 0 || empty($result['workspace_id'])) return;
        $this->ensure_dms_workspace_registry_table();
        $table = db_prefix().'kt_dms_workspaces';
        $existing = $this->landlord_db()->where('id', (int) $result['workspace_id'])->get($table)->row_array();
        $payload = [
            'tenant_id' => $tenantId, 'company_id' => $companyId,
            'workspace_slug' => trim((string) ($result['workspace_slug'] ?? ($existing['workspace_slug'] ?? ''))),
            'workspace_name' => trim((string) ($result['workspace_name'] ?? $result['workspace_slug'] ?? ($existing['workspace_name'] ?? 'Workspace DMS'))),
            'workspace_host' => strtolower(trim((string) ($result['workspace_host'] ?? ($existing['workspace_host'] ?? '')))),
            'package_code' => trim((string) ($result['package_code'] ?? ($existing['package_code'] ?? ''))),
            'entitlement_status' => trim((string) ($result['entitlement_status'] ?? $result['license_status'] ?? ($existing['entitlement_status'] ?? 'active'))),
            'domain_id' => !empty($result['domain_id']) ? (int) $result['domain_id'] : ($existing['domain_id'] ?? null),
            'domain_name' => strtolower(trim((string) ($result['domain'] ?? ($existing['domain_name'] ?? '')))) ?: null,
            'domain_status' => trim((string) ($result['domain_status'] ?? $result['status'] ?? ($existing['domain_status'] ?? 'pending_dns'))),
            'is_primary' => (int) ($result['is_primary'] ?? ($existing['is_primary'] ?? 0)),
            'source_version' => trim((string) ($result['correlation_id'] ?? '')), 'last_synced_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($existing) $this->landlord_db()->where('id', (int) $result['workspace_id'])->update($table, $payload);
        else { $payload['id'] = (int) $result['workspace_id']; $payload['created_at'] = date('Y-m-d H:i:s'); $this->landlord_db()->insert($table, $payload); }
    }

    public function refresh_dms_workspace_registry($tenantId): array
    {
        if (!isset($this->Kt_saas_model)) $this->load->model('kt_saas/Kt_saas_model');
        $tenant=$this->Kt_saas_model->get_tenant((int)$tenantId); $connection=$this->get_salesflow_connection_for_tenant((int)$tenantId);
        $companyId=(int)(kt_integration_hub_crm_company_id((array)$tenant)??0);
        if(!$tenant||!$connection||$companyId<=0) return [];
        require_once module_dir_path(KT_INTEGRATION_HUB_MODULE,'libraries/DmsWorkspaceRegistryClient.php');
        foreach((new DmsWorkspaceRegistryClient())->fetch($tenant,$connection) as $row){
            $this->save_dms_workspace_mutation_result((int)$tenantId,$companyId,[
                'success'=>true,'workspace_id'=>$row['workspace_id']??0,'workspace_slug'=>$row['workspace_slug']??'',
                'workspace_name'=>$row['workspace_name']??'','workspace_host'=>$row['workspace_host']??'',
                'package_code'=>$row['package_code']??'','entitlement_status'=>$row['entitlement_status']??null,
                'domain_id'=>$row['domain_id']??null,'domain'=>$row['domain']??null,'domain_status'=>$row['domain_status']??null,
                'is_primary'=>$row['is_primary']??0,'correlation_id'=>'registry-refresh',
            ]);
        }
        return $this->get_dms_workspaces_for_tenant((int)$tenantId,$companyId);
    }

    public function get_dms_workspace_for_tenant($tenantId, $workspaceId = 0)
    {
        $tenant=(array)kt_integration_hub_current_tenant();
        $companyId=(int)(kt_integration_hub_crm_company_id($tenant)??0);
        $rows = $this->get_dms_workspaces_for_tenant($tenantId, $companyId);
        if ((int) $workspaceId > 0) {
            foreach ($rows as $row) {
                if ((int) ($row['id'] ?? 0) === (int) $workspaceId) return $row;
            }
            return null;
        }
        if (count($rows) === 1 || (!empty($rows[0]) && (int) ($rows[0]['is_primary'] ?? 0) === 1)) {
            return $rows[0];
        }
        return null;
    }

    public function save_dms_provider_safe_mirror(array $rows, array $browserRuntime = []): array
    {
        $this->ensure_dms_provider_safe_mirror_table();
        $googleMapsRow = null;
        foreach ($rows as $row) {
            $provider = trim((string) ($row['provider'] ?? ''));
            if ($provider === '') continue;
            if ($provider === 'google_maps') $googleMapsRow = $row;
            $safe = [
                'driver' => trim((string) ($row['driver'] ?? '')),
                'status' => trim((string) ($row['status'] ?? '')),
                'health_status' => trim((string) ($row['health_status'] ?? '')),
                'last_checked_at' => !empty($row['last_checked_at']) ? date('Y-m-d H:i:s', strtotime($row['last_checked_at'])) : null,
                'fingerprint' => trim((string) ($row['fingerprint'] ?? '')),
                'source' => 'salesrep_platform',
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            $existing = $this->landlord_db()->where('provider', $provider)->get(db_prefix() . 'kt_dms_provider_safe_mirror')->row_array();
            if ($existing) $this->landlord_db()->where('id', $existing['id'])->update(db_prefix() . 'kt_dms_provider_safe_mirror', $safe);
            else $this->landlord_db()->insert(db_prefix() . 'kt_dms_provider_safe_mirror', ['provider' => $provider, 'created_at' => date('Y-m-d H:i:s')] + $safe);
        }
        $allowedDomains = [];
        $maps = (array) ($browserRuntime['google_maps'] ?? []);
        if (!empty($maps['browser_key'])) {
            $encryptedKey = kt_integration_hub_encrypt_value((string) $maps['browser_key']);
            update_option('kt_dms_maps_runtime_browser_key_encrypted', $encryptedKey);
            update_option('kt_dms_google_maps_api_key_encrypted', $encryptedKey);
            $allowedDomains = array_values(array_filter(array_map('trim', (array) ($maps['allowed_domains'] ?? []))));
            update_option('kt_dms_maps_runtime_allowed_domains', kt_integration_hub_json_encode($allowedDomains));
            update_option('kt_dms_maps_runtime_source', 'salesrep_platform_signed_mirror');
        }
        if ($allowedDomains === []) {
            $decodedDomains = json_decode((string) get_option('kt_dms_maps_runtime_allowed_domains'), true);
            $allowedDomains = is_array($decodedDomains) ? array_values(array_filter(array_map('trim', $decodedDomains))) : [];
        }

        $current = json_decode((string) get_option('kt_dms_platform_config_json'), true);
        $current = is_array($current) ? $current : [];
        $googleReady = is_array($googleMapsRow)
            && trim((string) ($googleMapsRow['status'] ?? '')) === 'active'
            && trim((string) ($googleMapsRow['health_status'] ?? '')) !== 'connection_failed'
            && trim((string) get_option('kt_dms_maps_runtime_browser_key_encrypted')) !== '';
        $platformConfig = array_merge($current, [
            'map_provider' => $googleReady ? 'google' : ($current['map_provider'] ?? 'keyless'),
            'map_enabled' => ($current['map_enabled'] ?? true) !== false,
            'map_api_key_configured' => trim((string) get_option('kt_dms_maps_runtime_browser_key_encrypted')) !== '',
            'allowed_domains' => $allowedDomains,
            'map_status' => $googleReady && $allowedDomains !== [] ? 'ready' : 'missing_config',
            'map_validation_state' => $googleReady && $allowedDomains !== [] ? 'ready' : ($googleReady ? 'missing_allowed_domains' : 'missing_key'),
            'last_validation_status' => $googleReady && $allowedDomains !== []
                ? 'Cấu hình bản đồ nhận từ Salesrep Platform đã sẵn sàng'
                : 'Đang chờ đồng bộ cấu hình bản đồ từ Salesrep Platform',
            'sso_enabled' => ($current['sso_enabled'] ?? true) !== false,
            'bridge_enabled' => ($current['bridge_enabled'] ?? true) !== false,
            'sso_timeout_seconds' => (int) ($current['sso_timeout_seconds'] ?? 3),
            'retention_months' => (int) ($current['retention_months'] ?? 6),
            'managed_by' => 'salesrep_platform',
            'provider_source' => 'salesrep_platform_signed_mirror',
        ]);
        update_option('kt_dms_platform_config_json', kt_integration_hub_json_encode($platformConfig));
        update_option('kt_dms_global_config_updated_at', gmdate('c'));

        $version = max(1, (int) get_option('kt_saas_dms_config_version'));
        if (!isset($this->Kt_saas_model)) $this->load->model('kt_saas/Kt_saas_model');
        if (isset($this->Kt_saas_model) && method_exists($this->Kt_saas_model, 'bump_dms_config_version')) {
            $version = (int) $this->Kt_saas_model->bump_dms_config_version('salesrep_provider_mirror');
        }

        $tenantCount = 0;
        $tenantTable = db_prefix() . 'kt_saas_tenants';
        if ($this->landlord_db()->table_exists($tenantTable)) {
            $tenantCount = (int) $this->landlord_db()
                ->where('deleted_at IS NULL', null, false)
                ->where_in('status', ['active', 'provisioned'])
                ->count_all_results($tenantTable);
        }

        return [
            'config_version' => $version,
            'affected_tenant_count' => $tenantCount,
            'failed_tenant_count' => 0,
            'source' => 'salesrep_platform_signed_mirror',
            'map_status' => (string) ($platformConfig['map_status'] ?? ''),
            'updated_at' => gmdate('c'),
        ];
    }

    public function get_dms_provider_safe_mirror()
    {
        $this->ensure_dms_provider_safe_mirror_table();
        return $this->landlord_db()->order_by('provider', 'ASC')->get(db_prefix() . 'kt_dms_provider_safe_mirror')->result_array();
    }

    private function ensure_dms_provider_safe_mirror_table()
    {
        $table = db_prefix() . 'kt_dms_provider_safe_mirror';
        if ($this->landlord_db()->table_exists($table)) return;
        $this->landlord_db()->query("CREATE TABLE IF NOT EXISTS `{$table}` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `provider` VARCHAR(64) NOT NULL,
          `driver` VARCHAR(64) NULL, `status` VARCHAR(32) NULL, `health_status` VARCHAR(64) NULL,
          `last_checked_at` DATETIME NULL, `fingerprint` VARCHAR(64) NULL,
          `source` VARCHAR(64) NOT NULL DEFAULT 'salesrep_platform',
          `created_at` DATETIME NULL, `updated_at` DATETIME NULL,
          PRIMARY KEY (`id`), UNIQUE KEY `provider` (`provider`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function ensure_dms_workspace_registry_table()
    {
        $table = db_prefix() . 'kt_dms_workspaces';
        if ($this->landlord_db()->table_exists($table)) {
            if (!$this->landlord_db()->field_exists('domain_id', $table)) {
                $this->landlord_db()->query("ALTER TABLE `{$table}` ADD `domain_id` BIGINT UNSIGNED NULL AFTER `entitlement_status`, ADD `domain_name` VARCHAR(191) NULL AFTER `domain_id`");
            }
            return;
        }
        $this->landlord_db()->query("CREATE TABLE IF NOT EXISTS `{$table}` (
          `id` BIGINT UNSIGNED NOT NULL,
          `tenant_id` INT UNSIGNED NOT NULL,
          `company_id` INT UNSIGNED NOT NULL,
          `workspace_slug` VARCHAR(100) NOT NULL,
          `workspace_name` VARCHAR(191) NOT NULL,
          `workspace_host` VARCHAR(191) NOT NULL,
          `package_code` VARCHAR(100) NULL,
          `entitlement_status` VARCHAR(32) NULL,
          `domain_id` BIGINT UNSIGNED NULL,
          `domain_name` VARCHAR(191) NULL,
          `domain_status` VARCHAR(32) NULL,
          `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
          `source_version` VARCHAR(64) NULL,
          `last_synced_at` DATETIME NULL,
          `created_at` DATETIME NULL,
          `updated_at` DATETIME NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `workspace_host` (`workspace_host`),
          UNIQUE KEY `tenant_workspace_slug` (`tenant_id`,`company_id`,`workspace_slug`),
          KEY `tenant_primary` (`tenant_id`,`company_id`,`is_primary`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function build_product_sync_payload(array $tenant, $productId)
    {
        $producer = new ProductPayloadProducer();
        $productId = (int) $productId;
        if ($productId <= 0) {
            throw new RuntimeException('Product id is required.');
        }

        $landlordDb = $this->landlord_db();
        $CI = &get_instance();
        $previousDb = $CI->db;

        if (!class_exists('DatabaseSwitcher', false)) {
            require_once module_dir_path('kt_saas', 'tenant_bootstrap/DatabaseSwitcher.php');
        }

        $switcher = new DatabaseSwitcher();
        $switchResult = $switcher->switchConnection($tenant);
        if (empty($switchResult['switched'])) {
            throw new RuntimeException('Tenant DB switch failed: ' . (string) ($switchResult['message'] ?? 'unknown'));
        }

        $this->db = $CI->db;

        try {
            $itemRow = $this->db
                ->select('id, description, long_description, rate, unit')
                ->from(db_prefix() . 'items')
                ->where('id', $productId)
                ->get()
                ->row_array();

            if (!$itemRow) {
                throw new RuntimeException('Product not found in tenant source.');
            }

            $itemRow['_bridge_updated_at'] = date('Y-m-d H:i:s');

            $inventoryRow = $this->db
                ->select('i.*, pb.barcode as primary_barcode, pb.barcode_type as primary_barcode_type, pb.unit_type as primary_barcode_unit_type, pb.package_quantity as primary_package_quantity')
                ->from(db_prefix() . 'kt_inventory_items i')
                ->join(
                    '(SELECT inventory_item_id, barcode, barcode_type, unit_type, package_quantity FROM ' . db_prefix() . 'kt_item_barcodes WHERE is_primary = 1 AND is_active = 1) pb',
                    'pb.inventory_item_id = i.id',
                    'left'
                )
                ->where('i.item_id', $productId)
                ->get()
                ->row_array();

            $primaryBarcodeRow = null;
            if (!empty($inventoryRow['id'])) {
                $primaryBarcodeRow = $this->db
                    ->select('id, inventory_item_id, barcode, barcode_type, unit_type, package_quantity, is_primary, is_active')
                    ->from(db_prefix() . 'kt_item_barcodes')
                    ->where('inventory_item_id', (int) $inventoryRow['id'])
                    ->where('is_primary', 1)
                    ->where('is_active', 1)
                    ->get()
                    ->row_array();
            }

            return $producer->buildPayload($tenant, $itemRow, $inventoryRow, $primaryBarcodeRow);
        } finally {
            $CI->db = $landlordDb ?: $previousDb;
            $this->db = $CI->db;
            $CI->config->set_item('kt_saas_landlord_db', $landlordDb ?: $previousDb);
        }
    }

    private function build_warehouse_sync_payload(array $tenant, $warehouseId)
    {
        $producer = new WarehousePayloadProducer();
        $warehouseId = (int) $warehouseId;
        if ($warehouseId <= 0) {
            throw new RuntimeException('Warehouse id is required.');
        }

        $landlordDb = $this->landlord_db();
        $CI = &get_instance();
        $previousDb = $CI->db;

        if (!class_exists('DatabaseSwitcher', false)) {
            require_once module_dir_path('kt_saas', 'tenant_bootstrap/DatabaseSwitcher.php');
        }

        $switcher = new DatabaseSwitcher();
        $switchResult = $switcher->switchConnection($tenant);
        if (empty($switchResult['switched'])) {
            throw new RuntimeException('Tenant DB switch failed: ' . (string) ($switchResult['message'] ?? 'unknown'));
        }

        $this->db = $CI->db;

        try {
            if (!$this->db->table_exists(db_prefix() . 'kt_warehouses')) {
                throw new RuntimeException('CRM warehouse catalog table is not installed for this tenant.');
            }

            $warehouseRow = $this->db
                ->select('id, warehouse_code, warehouse_name, address, is_active, created_at, updated_at')
                ->from(db_prefix() . 'kt_warehouses')
                ->where('id', $warehouseId)
                ->get()
                ->row_array();

            if (!$warehouseRow) {
                throw new RuntimeException('Warehouse not found in tenant source.');
            }

            return $producer->buildPayload($tenant, $warehouseRow);
        } finally {
            $CI->db = $landlordDb ?: $previousDb;
            $this->db = $CI->db;
            $CI->config->set_item('kt_saas_landlord_db', $landlordDb ?: $previousDb);
        }
    }
}


