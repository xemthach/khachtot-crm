<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

require_once __DIR__ . '/../libraries/RoutePerformanceAggregator.php';

$assert = function ($condition, $message) {
    if (! $condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$aggregator = new RoutePerformanceAggregator();

$checkins = [
    [
        'checkin_at' => '2026-06-09 08:10:00',
        'crm_staff_id' => 5001,
        'crm_customer_id' => 9001,
        'store_id' => 61,
        'status' => 'warning',
    ],
    [
        'checkin_at' => '2026-06-09 09:15:00',
        'crm_staff_id' => 5001,
        'crm_customer_id' => 9002,
        'store_id' => 62,
        'status' => 'valid',
    ],
];

$visits = [
    [
        'started_at' => '2026-06-09 08:00:00',
        'crm_staff_id' => 5001,
        'crm_customer_id' => 9001,
        'store_id' => 61,
        'visit_status' => 'completed',
    ],
    [
        'started_at' => '2026-06-09 09:00:00',
        'crm_staff_id' => 5001,
        'crm_customer_id' => 9002,
        'store_id' => 62,
        'visit_status' => 'partial',
    ],
    [
        'started_at' => '2026-06-09 10:00:00',
        'crm_staff_id' => 5002,
        'crm_customer_id' => 9003,
        'store_id' => 63,
        'visit_status' => 'cancelled',
    ],
];

$stores = [[1], [2], [3], [4]];
$customers = [[1], [2], [3], [4], [5]];

$result = $aggregator->aggregate($checkins, $visits, $stores, $customers, [
    'date_from' => '2026-06-09',
    'date_to' => '2026-06-09',
]);

$assert(($result['summary']['total_checkins'] ?? 0) === 2, 'Expected total checkins to be 2.');
$assert(($result['summary']['total_visits'] ?? 0) === 3, 'Expected total visits to be 3.');
$assert(($result['summary']['completed_visits'] ?? 0) === 1, 'Expected completed visits to be 1.');
$assert(($result['summary']['partial_visits'] ?? 0) === 1, 'Expected partial visits to be 1.');
$assert(($result['summary']['cancelled_visits'] ?? 0) === 1, 'Expected cancelled visits to be 1.');
$assert(($result['coverage']['store_coverage']['visited'] ?? 0) === 3, 'Expected visited stores to be 3.');
$assert(($result['coverage']['customer_coverage']['visited'] ?? 0) === 3, 'Expected visited customers to be 3.');
$assert(count($result['items'] ?? []) === 3, 'Expected three snapshot rows.');

$first = $result['items'][0];
$assert(($first['snapshot_date'] ?? '') === '2026-06-09', 'Expected snapshot date to normalize.');
$assert(($first['total_checkins'] ?? 0) >= 1, 'Expected snapshot row to include checkins.');

fwrite(STDOUT, "RoutePerformanceAggregatorTest passed." . PHP_EOL);
