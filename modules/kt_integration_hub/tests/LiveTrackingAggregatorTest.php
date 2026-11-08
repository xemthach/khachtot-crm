<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

require_once __DIR__ . '/../libraries/LiveTrackingAggregator.php';

$assert = function ($condition, $message) {
    if (! $condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$aggregator = new LiveTrackingAggregator();

$rows = [
    [
        'tenant_id' => 1001,
        'dms_user_id' => 55001,
        'crm_staff_id' => 5001,
        'latitude' => 10.7769,
        'longitude' => 106.7009,
        'last_seen_at' => date('Y-m-d H:i:s', strtotime('-2 minutes')),
        'tracking_status' => 'online',
        'payload_json' => '{}',
    ],
    [
        'tenant_id' => 1001,
        'dms_user_id' => 55002,
        'crm_staff_id' => 5002,
        'latitude' => 10.7779,
        'longitude' => 106.7019,
        'last_seen_at' => date('Y-m-d H:i:s', strtotime('-7 minutes')),
        'tracking_status' => 'stale',
        'payload_json' => '{}',
    ],
    [
        'tenant_id' => 1001,
        'dms_user_id' => 55003,
        'crm_staff_id' => 5003,
        'latitude' => 10.7789,
        'longitude' => 106.7029,
        'last_seen_at' => date('Y-m-d H:i:s', strtotime('-20 minutes')),
        'tracking_status' => 'offline',
        'payload_json' => '{}',
    ],
];

$result = $aggregator->aggregate($rows, []);
$assert(($result['summary']['total_users'] ?? 0) === 3, 'Expected total live tracking users to be 3.');
$assert(($result['summary']['online_users'] ?? 0) === 1, 'Expected online users to be 1.');
$assert(($result['summary']['stale_users'] ?? 0) === 1, 'Expected stale users to be 1.');
$assert(($result['summary']['offline_users'] ?? 0) === 1, 'Expected offline users to be 1.');
$assert(count($result['items'] ?? []) === 3, 'Expected three live tracking rows.');

$filtered = $aggregator->aggregate($rows, [
    'status' => 'online',
]);
$assert(($filtered['summary']['total_users'] ?? 0) === 1, 'Expected online filter to return one row.');

$dateFiltered = $aggregator->aggregate($rows, [
    'date' => date('Y-m-d'),
]);
$assert(($dateFiltered['summary']['total_users'] ?? 0) === 3, 'Expected date filter to keep all same-day rows.');

fwrite(STDOUT, "LiveTrackingAggregatorTest passed." . PHP_EOL);
