<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);
defined('KT_INTEGRATION_HUB_MODULE') or define('KT_INTEGRATION_HUB_MODULE', 'kt_integration_hub');
if (!function_exists('module_dir_path')) {
    function module_dir_path($module, $concat = '')
    {
        return dirname(__DIR__) . '/' . ltrim((string) $concat, '/');
    }
}
if (!function_exists('db_prefix')) {
    function db_prefix(): string
    {
        return '';
    }
}
if (!function_exists('kt_integration_hub_now')) {
    function kt_integration_hub_now(): string
    {
        return '2026-06-10 09:00:00';
    }
}

require_once dirname(__DIR__) . '/libraries/NotificationBridgeService.php';

class FakeNotificationBridgeResult
{
    public function __construct(private readonly array $rows)
    {
    }

    public function row_array(): ?array
    {
        return $this->rows[0] ?? null;
    }

    public function result_array(): array
    {
        return array_values($this->rows);
    }
}

class FakeNotificationBridgeDb
{
    public array $rows = [];
    private array $conditions = [];
    private array $whereIn = [];
    private ?array $orderBy = null;
    private ?int $limit = null;
    private int $lastInsertId = 0;

    public function from($table)
    {
        return $this;
    }

    public function where($field, $value)
    {
        $this->conditions[] = [$field, '=', $value];

        return $this;
    }

    public function where_in($field, $values)
    {
        $this->whereIn[] = [$field, array_values((array) $values)];

        return $this;
    }

    public function order_by($field, $direction = 'asc')
    {
        $this->orderBy = [$field, strtolower((string) $direction)];

        return $this;
    }

    public function limit($limit)
    {
        $this->limit = (int) $limit;

        return $this;
    }

    public function get($table = null)
    {
        $rows = array_values($this->rows);
        $rows = array_values(array_filter($rows, function (array $row): bool {
            foreach ($this->conditions as [$field, $operator, $value]) {
                if (($row[$field] ?? null) != $value) {
                    return false;
                }
            }
            foreach ($this->whereIn as [$field, $values]) {
                if (! in_array($row[$field] ?? null, $values, true)) {
                    return false;
                }
            }

            return true;
        }));

        if ($this->orderBy !== null) {
            [$field, $direction] = $this->orderBy;
            usort($rows, function (array $a, array $b) use ($field, $direction): int {
                $left = $a[$field] ?? null;
                $right = $b[$field] ?? null;

                if ($left === $right) {
                    return 0;
                }

                $comparison = $left <=> $right;

                return $direction === 'desc' ? -$comparison : $comparison;
            });
        }

        if ($this->limit !== null) {
            $rows = array_slice($rows, 0, $this->limit);
        }

        $this->resetQuery();

        return new FakeNotificationBridgeResult($rows);
    }

    public function insert($table, array $record)
    {
        $id = ++$this->lastInsertId;
        $record['id'] = $id;
        $this->rows[$id] = $record;
    }

    public function insert_id()
    {
        return $this->lastInsertId;
    }

    public function update($table, array $record)
    {
        $updated = 0;
        foreach ($this->rows as $id => $row) {
            $matches = true;
            foreach ($this->conditions as [$field, $operator, $value]) {
                if (($row[$field] ?? null) != $value) {
                    $matches = false;
                    break;
                }
            }
            if (! $matches) {
                continue;
            }
            $this->rows[$id] = array_merge($row, $record);
            $updated++;
        }
        $this->resetQuery();

        return $updated > 0;
    }

    private function resetQuery(): void
    {
        $this->conditions = [];
        $this->whereIn = [];
        $this->orderBy = null;
        $this->limit = null;
    }
}

class FakeNotificationBridgeService extends NotificationBridgeService
{
    public function __construct(private readonly FakeNotificationBridgeDb $db)
    {
    }

    protected function db()
    {
        return $this->db;
    }
}

$db = new FakeNotificationBridgeDb();
$service = new FakeNotificationBridgeService($db);

$created = $service->createNotification([
    'tenant_id' => 27,
    'event_type' => NotificationBridgeService::EVENT_MANUAL_REVIEW_CREATED,
    'event_code' => 'manual-review-created-9001',
    'title' => 'Manual review created',
    'message' => 'Manual review created.',
    'target_type' => 'tenant',
    'target_id' => 27,
    'priority' => 'HIGH',
    'payload' => ['review_id' => 9001],
]);

if ((int) ($created['id'] ?? 0) !== 1) {
    throw new RuntimeException('Expected first notification insert.');
}

$duplicate = $service->createNotification([
    'tenant_id' => 27,
    'event_type' => NotificationBridgeService::EVENT_MANUAL_REVIEW_CREATED,
    'event_code' => 'manual-review-created-9001',
    'title' => 'Manual review created',
    'message' => 'Manual review created.',
    'target_type' => 'tenant',
    'target_id' => 27,
    'priority' => 'HIGH',
    'payload' => ['review_id' => 9001],
]);

if ((int) ($duplicate['id'] ?? 0) !== 1) {
    throw new RuntimeException('Expected duplicate notification to reuse existing row.');
}

$read = $service->markRead(1, 27);
if (($read['status'] ?? null) !== NotificationBridgeService::STATUS_READ) {
    throw new RuntimeException('Expected notification to be marked read.');
}

$acked = $service->ack(1, 27);
if (($acked['status'] ?? null) !== NotificationBridgeService::STATUS_ACKED) {
    throw new RuntimeException('Expected notification to be acknowledged.');
}

$listed = $service->list(['tenant_id' => 27]);
if (count($listed) !== 1) {
    throw new RuntimeException('Expected one notification in list result.');
}

echo "NotificationBridgeServiceTest passed.\n";
