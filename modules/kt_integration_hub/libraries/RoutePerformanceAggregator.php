<?php

defined('BASEPATH') or exit('No direct script access allowed');

class RoutePerformanceAggregator
{
    public function aggregate(array $checkinEvents, array $visitSnapshots, array $stores = [], array $customers = [], array $filters = []): array
    {
        $dateFrom = $this->normalizeDateBoundary($filters['date_from'] ?? null, false);
        $dateTo = $this->normalizeDateBoundary($filters['date_to'] ?? null, true);
        $staffId = ! empty($filters['staff_id']) ? (int) $filters['staff_id'] : null;
        $customerId = ! empty($filters['customer_id']) ? (int) $filters['customer_id'] : null;
        $storeId = ! empty($filters['store_id']) ? (int) $filters['store_id'] : null;

        $checkinEvents = $this->filterRows($checkinEvents, 'checkin_at', $dateFrom, $dateTo, $staffId, $customerId, $storeId);
        $visitSnapshots = $this->filterRows($visitSnapshots, 'started_at', $dateFrom, $dateTo, $staffId, $customerId, $storeId);

        $items = [];
        $staffActivity = [];
        $visitedStoreIds = [];
        $visitedCustomerIds = [];

        foreach ($checkinEvents as $row) {
            $date = $this->dateKey((string) ($row['checkin_at'] ?? ''));
            $key = $this->snapshotKey($date, (int) ($row['crm_staff_id'] ?? 0), (int) ($row['crm_customer_id'] ?? 0), (int) ($row['store_id'] ?? 0));
            $items[$key] = $items[$key] ?? $this->emptySnapshotRow($date, $row);
            $items[$key]['total_checkins']++;
            $visitedStoreIds[(int) ($row['store_id'] ?? 0)] = true;
            $visitedCustomerIds[(int) ($row['crm_customer_id'] ?? 0)] = true;
            $this->bumpStaffActivity($staffActivity, (int) ($row['crm_staff_id'] ?? 0), 'checkins');
        }

        foreach ($visitSnapshots as $row) {
            $date = $this->dateKey((string) ($row['started_at'] ?? $row['ended_at'] ?? ''));
            $key = $this->snapshotKey($date, (int) ($row['crm_staff_id'] ?? 0), (int) ($row['crm_customer_id'] ?? 0), (int) ($row['store_id'] ?? 0));
            $items[$key] = $items[$key] ?? $this->emptySnapshotRow($date, $row);
            $items[$key]['total_visits']++;
            $status = strtolower(trim((string) ($row['visit_status'] ?? 'partial')));
            if ($status === 'completed') {
                $items[$key]['completed_visits']++;
            } elseif ($status === 'cancelled') {
                $items[$key]['cancelled_visits']++;
            } else {
                $items[$key]['partial_visits']++;
            }
            $visitedStoreIds[(int) ($row['store_id'] ?? 0)] = true;
            $visitedCustomerIds[(int) ($row['crm_customer_id'] ?? 0)] = true;
            $this->bumpStaffActivity($staffActivity, (int) ($row['crm_staff_id'] ?? 0), 'visits');
            $this->bumpStaffActivity($staffActivity, (int) ($row['crm_staff_id'] ?? 0), 'store_ids', (int) ($row['store_id'] ?? 0));
        }

        $items = array_values(array_map(function (array $row) {
            ksort($row);

            return $row;
        }, $items));

        usort($items, function (array $a, array $b) {
            return strcmp((string) $a['snapshot_date'], (string) $b['snapshot_date'])
                ?: ($a['crm_staff_id'] <=> $b['crm_staff_id'])
                ?: ($a['crm_customer_id'] <=> $b['crm_customer_id'])
                ?: ($a['store_id'] <=> $b['store_id']);
        });

        $summary = [
            'total_checkins' => array_sum(array_column($items, 'total_checkins')),
            'total_visits' => array_sum(array_column($items, 'total_visits')),
            'completed_visits' => array_sum(array_column($items, 'completed_visits')),
            'partial_visits' => array_sum(array_column($items, 'partial_visits')),
            'cancelled_visits' => array_sum(array_column($items, 'cancelled_visits')),
            'staff_with_activity' => count(array_filter($staffActivity, fn (array $row) => (($row['checkins'] ?? 0) + ($row['visits'] ?? 0)) > 0)),
        ];

        return [
            'summary' => $summary,
            'coverage' => [
                'staff_activity' => array_values(array_map(function (array $row, int $staffIdKey) {
                    return [
                        'crm_staff_id' => $staffIdKey,
                        'total_checkins' => (int) ($row['checkins'] ?? 0),
                        'total_visits' => (int) ($row['visits'] ?? 0),
                        'store_count' => count(array_unique(array_values(array_filter((array) ($row['store_ids'] ?? []), fn ($value) => (int) $value > 0)))),
                    ];
                }, $staffActivity, array_keys($staffActivity))),
                'customer_coverage' => $this->buildCoverage(count($customers), count(array_filter(array_keys($visitedCustomerIds))), count($customers)),
                'store_coverage' => $this->buildCoverage(count($stores), count(array_filter(array_keys($visitedStoreIds))), count($stores)),
            ],
            'items' => $items,
        ];
    }

    public function persistSnapshots(int $tenantId, array $items): void
    {
        if ($tenantId <= 0 || $items === []) {
            return;
        }

        $table = db_prefix() . 'dms_route_performance_snapshots';
        foreach ($items as $row) {
            $record = [
                'tenant_id' => $tenantId,
                'snapshot_date' => (string) ($row['snapshot_date'] ?? date('Y-m-d')),
                'crm_staff_id' => (int) ($row['crm_staff_id'] ?? 0),
                'crm_customer_id' => (int) ($row['crm_customer_id'] ?? 0),
                'store_id' => (int) ($row['store_id'] ?? 0),
                'total_checkins' => (int) ($row['total_checkins'] ?? 0),
                'total_visits' => (int) ($row['total_visits'] ?? 0),
                'completed_visits' => (int) ($row['completed_visits'] ?? 0),
                'partial_visits' => (int) ($row['partial_visits'] ?? 0),
                'cancelled_visits' => (int) ($row['cancelled_visits'] ?? 0),
                'created_at' => kt_integration_hub_now(),
                'updated_at' => kt_integration_hub_now(),
            ];

            $existing = $this->db()
                ->where('tenant_id', $tenantId)
                ->where('snapshot_date', $record['snapshot_date'])
                ->where('crm_staff_id', $record['crm_staff_id'])
                ->where('crm_customer_id', $record['crm_customer_id'])
                ->where('store_id', $record['store_id'])
                ->get($table)
                ->row_array();

            if ($existing) {
                $this->db()->where('id', (int) $existing['id'])->update($table, $record);
                continue;
            }

            $this->db()->insert($table, $record);
        }
    }

    private function emptySnapshotRow(string $snapshotDate, array $row): array
    {
        return [
            'snapshot_date' => $snapshotDate,
            'crm_staff_id' => (int) ($row['crm_staff_id'] ?? 0),
            'crm_customer_id' => (int) ($row['crm_customer_id'] ?? 0),
            'store_id' => (int) ($row['store_id'] ?? 0),
            'total_checkins' => 0,
            'total_visits' => 0,
            'completed_visits' => 0,
            'partial_visits' => 0,
            'cancelled_visits' => 0,
        ];
    }

    private function bumpStaffActivity(array &$staffActivity, int $staffId, string $field, int $storeId = 0): void
    {
        if ($staffId <= 0) {
            return;
        }

        $staffActivity[$staffId] ??= [
            'checkins' => 0,
            'visits' => 0,
            'store_ids' => [],
        ];
        if ($field === 'store_ids') {
            if ($storeId > 0) {
                $staffActivity[$staffId]['store_ids'][$storeId] = true;
            }

            return;
        }

        $staffActivity[$staffId][$field]++;
    }

    private function buildCoverage(int $totalScope, int $visited, int $fallbackTotal): array
    {
        $total = $totalScope > 0 ? $totalScope : $fallbackTotal;
        $visited = min($visited, $total > 0 ? $total : $visited);
        $unvisited = max($total - $visited, 0);
        $rate = $total > 0 ? round(($visited / $total) * 100, 2) : null;

        return [
            'total' => $total,
            'visited' => $visited,
            'unvisited' => $unvisited,
            'coverage_rate' => $rate,
        ];
    }

    private function filterRows(array $rows, string $dateField, ?string $dateFrom, ?string $dateTo, ?int $staffId, ?int $customerId, ?int $storeId): array
    {
        return array_values(array_filter($rows, function (array $row) use ($dateField, $dateFrom, $dateTo, $staffId, $customerId, $storeId) {
            $rowDate = $this->normalizeDateBoundary((string) ($row[$dateField] ?? ''), false);
            if ($dateFrom && $rowDate < $dateFrom) {
                return false;
            }
            if ($dateTo && $rowDate > $dateTo) {
                return false;
            }
            if ($staffId !== null && $staffId > 0 && (int) ($row['crm_staff_id'] ?? 0) !== $staffId) {
                return false;
            }
            if ($customerId !== null && $customerId > 0 && (int) ($row['crm_customer_id'] ?? 0) !== $customerId) {
                return false;
            }
            if ($storeId !== null && $storeId > 0 && (int) ($row['store_id'] ?? 0) !== $storeId) {
                return false;
            }

            return true;
        }));
    }

    private function normalizeDateBoundary(?string $value, bool $endOfDay): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function dateKey(string $value): string
    {
        $timestamp = strtotime($value);

        return $timestamp !== false ? date('Y-m-d', $timestamp) : date('Y-m-d');
    }

    private function snapshotKey(string $date, int $staffId, int $customerId, int $storeId): string
    {
        return $date . ':' . $staffId . ':' . $customerId . ':' . $storeId;
    }

    private function db()
    {
        $CI = &get_instance();
        if (! isset($CI->db)) {
            throw new RuntimeException('Database instance is not available.');
        }

        return $CI->db;
    }
}
