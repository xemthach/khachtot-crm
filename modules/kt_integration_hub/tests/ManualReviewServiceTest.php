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
        return '2026-06-09 20:00:00';
    }
}

require_once dirname(__DIR__) . '/libraries/ManualReviewService.php';

class FakeManualReviewResult
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

class FakeManualReviewDb
{
    public array $rows = [];
    private array $conditions = [];
    private array $whereIn = [];
    private ?array $orderBy = null;
    private ?int $limit = null;
    private int $lastInsertId = 0;
    private ?string $table = null;

    public function from($table)
    {
        $this->table = $table;

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

        return new FakeManualReviewResult($rows);
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

    public function count_all_results($table)
    {
        return count($this->get($table)->result_array());
    }

    private function resetQuery(): void
    {
        $this->conditions = [];
        $this->whereIn = [];
        $this->orderBy = null;
        $this->limit = null;
        $this->table = null;
    }
}

class FakeManualReviewService extends ManualReviewService
{
    public function __construct(private readonly FakeManualReviewDb $db)
    {
    }

    protected function db()
    {
        return $this->db;
    }
}

$db = new FakeManualReviewDb();
$service = new FakeManualReviewService($db);

$review = $service->createReview([
    'tenant_id' => 1001,
    'entity_type' => 'order',
    'entity_id' => 33001,
    'review_type' => ManualReviewService::TYPE_ORDER_CANCEL_UNPAID,
    'priority' => 'MEDIUM',
    'review_reason' => 'Invoice requires manual review before cancellation.',
    'source_system' => 'salesflow_dms',
    'payload_json' => '{}',
]);

if (($review['review_status'] ?? null) !== ManualReviewService::STATUS_OPEN) {
    throw new RuntimeException('Expected created review to be OPEN.');
}

$duplicate = $service->createReview([
    'tenant_id' => 1001,
    'entity_type' => 'order',
    'entity_id' => 33001,
    'review_type' => ManualReviewService::TYPE_ORDER_CANCEL_UNPAID,
    'priority' => 'MEDIUM',
    'review_reason' => 'Invoice requires manual review before cancellation.',
    'source_system' => 'salesflow_dms',
    'payload_json' => '{}',
]);

if ((int) ($duplicate['id'] ?? 0) !== (int) ($review['id'] ?? 0)) {
    throw new RuntimeException('Expected duplicate manual review creation to reuse existing open review.');
}

$assigned = $service->assignReview((int) $review['id'], 901);
if (($assigned['review_status'] ?? null) !== ManualReviewService::STATUS_IN_PROGRESS) {
    throw new RuntimeException('Expected assigned review to be IN_PROGRESS.');
}
if ((int) ($assigned['assigned_staff_id'] ?? 0) !== 901) {
    throw new RuntimeException('Expected assigned staff id to be persisted.');
}

$resolved = $service->resolveReview((int) $review['id'], 'Reviewed and approved.', 902);
if (($resolved['review_status'] ?? null) !== ManualReviewService::STATUS_RESOLVED) {
    throw new RuntimeException('Expected resolved review to be RESOLVED.');
}

$closed = $service->closeReview((int) $review['id']);
if (($closed['review_status'] ?? null) !== ManualReviewService::STATUS_CLOSED) {
    throw new RuntimeException('Expected closed review to be CLOSED.');
}

$latest = $service->findLatestForEntity(1001, 'order', 33001);
if (!is_array($latest) || (int) ($latest['id'] ?? 0) !== (int) ($review['id'] ?? 0)) {
    throw new RuntimeException('Expected latest review lookup to return created review.');
}

echo "ManualReviewServiceTest passed.\n";
