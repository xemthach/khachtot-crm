<?php

defined('BASEPATH') or exit('No direct script access allowed');

class LiveTrackingAggregator
{
    public function aggregate(array $rows, array $filters = []): array
    {
        $date = $this->normalizeDate((string) ($filters['date'] ?? ''));
        $staffId = ! empty($filters['staff_id']) ? (int) $filters['staff_id'] : null;
        $status = strtolower(trim((string) ($filters['status'] ?? '')));

        $rows = array_values(array_filter($rows, function (array $row) use ($date, $staffId) {
            if ($staffId !== null && $staffId > 0 && (int) ($row['crm_staff_id'] ?? 0) !== $staffId) {
                return false;
            }

            if ($date) {
                $rowDate = $this->normalizeDate((string) ($row['last_seen_at'] ?? ''));
                if ($rowDate !== $date) {
                    return false;
                }
            }

            return true;
        }));

        $items = [];
        foreach ($rows as $row) {
            $resolvedStatus = $this->resolveTrackingStatus((string) ($row['last_seen_at'] ?? ''));
            if ($status !== '' && $resolvedStatus !== $status) {
                continue;
            }

            $row['tracking_status'] = $resolvedStatus;
            $row['age_minutes'] = $this->ageMinutes((string) ($row['last_seen_at'] ?? ''));
            $items[] = $row;
        }

        usort($items, function (array $a, array $b) {
            return strcmp((string) ($b['last_seen_at'] ?? ''), (string) ($a['last_seen_at'] ?? ''));
        });

        $summary = [
            'total_users' => count($items),
            'online_users' => count(array_filter($items, fn (array $row) => ($row['tracking_status'] ?? '') === 'online')),
            'stale_users' => count(array_filter($items, fn (array $row) => ($row['tracking_status'] ?? '') === 'stale')),
            'offline_users' => count(array_filter($items, fn (array $row) => ($row['tracking_status'] ?? '') === 'offline')),
        ];

        return [
            'summary' => $summary,
            'items' => $items,
        ];
    }

    private function resolveTrackingStatus(string $lastSeenAt): string
    {
        $age = $this->ageMinutes($lastSeenAt);
        if ($age <= 5) {
            return 'online';
        }

        if ($age <= 10) {
            return 'stale';
        }

        return 'offline';
    }

    private function ageMinutes(string $lastSeenAt): int
    {
        $timestamp = strtotime($lastSeenAt);
        if ($timestamp === false) {
            return 9999;
        }

        return (int) floor(max(time() - $timestamp, 0) / 60);
    }

    private function normalizeDate(string $value): string
    {
        $timestamp = strtotime(trim($value));
        if ($timestamp === false) {
            return '';
        }

        return date('Y-m-d', $timestamp);
    }
}
