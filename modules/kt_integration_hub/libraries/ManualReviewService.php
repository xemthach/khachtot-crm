<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once module_dir_path(KT_INTEGRATION_HUB_MODULE, 'libraries/NotificationBridgeService.php');

class ManualReviewService
{
    public const STATUS_OPEN = 'OPEN';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_RESOLVED = 'RESOLVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_CLOSED = 'CLOSED';

    public const TYPE_ORDER_CANCEL_UNPAID = 'ORDER_CANCEL_UNPAID';
    public const TYPE_ORDER_CANCEL_PARTIALLY_PAID = 'ORDER_CANCEL_PARTIALLY_PAID';
    public const TYPE_ORDER_CANCEL_OVERDUE = 'ORDER_CANCEL_OVERDUE';
    public const TYPE_INVOICE_LOCKED = 'INVOICE_LOCKED';
    public const TYPE_CUSTOMER_NOT_MAPPED = 'CUSTOMER_NOT_MAPPED';
    public const TYPE_PRODUCT_NOT_MAPPED = 'PRODUCT_NOT_MAPPED';
    public const TYPE_PAYMENT_CONFLICT = 'PAYMENT_CONFLICT';
    public const TYPE_COLLECTION_EVIDENCE = 'COLLECTION_EVIDENCE';

    public function list(array $filters = []): array
    {
        $query = $this->db()
            ->from(db_prefix() . 'kt_manual_reviews');

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', (int) $filters['tenant_id']);
        }
        if (! empty($filters['entity_type'])) {
            $query->where('entity_type', trim((string) $filters['entity_type']));
        }
        if (! empty($filters['entity_id'])) {
            $query->where('entity_id', (int) $filters['entity_id']);
        }
        if (! empty($filters['review_status'])) {
            $statuses = array_values(array_filter(array_map('trim', explode(',', (string) $filters['review_status']))));
            if ($statuses !== []) {
                $query->where_in('review_status', $statuses);
            }
        }
        if (! empty($filters['review_type'])) {
            $query->where('review_type', trim((string) $filters['review_type']));
        }

        return $query
            ->order_by('priority', 'asc')
            ->order_by('updated_at', 'desc')
            ->get(db_prefix() . 'kt_manual_reviews')
            ->result_array();
    }

    public function find(int $id, ?int $tenantId = null): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $db = $this->db()->where('id', $id);
        if ($tenantId !== null) {
            $db->where('tenant_id', $tenantId);
        }

        $row = $db->get(db_prefix() . 'kt_manual_reviews')->row_array();

        return $row ?: null;
    }

    public function findLatestForEntity(int $tenantId, string $entityType, int $entityId): ?array
    {
        if ($tenantId <= 0 || $entityId <= 0) {
            return null;
        }

        $row = $this->db()
            ->where('tenant_id', $tenantId)
            ->where('entity_type', trim($entityType))
            ->where('entity_id', $entityId)
            ->order_by('updated_at', 'desc')
            ->order_by('id', 'desc')
            ->limit(1)
            ->get(db_prefix() . 'kt_manual_reviews')
            ->row_array();

        return $row ?: null;
    }

    public function createReview(array $attributes): array
    {
        $tenantId = (int) ($attributes['tenant_id'] ?? 0);
        $entityType = trim((string) ($attributes['entity_type'] ?? ''));
        $entityId = (int) ($attributes['entity_id'] ?? 0);
        $reviewType = trim((string) ($attributes['review_type'] ?? ''));
        if ($tenantId <= 0 || $entityType === '' || $entityId <= 0 || $reviewType === '') {
            throw new RuntimeException('Manual review identity is required.');
        }

        $existing = $this->db()
            ->where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('review_type', $reviewType)
            ->where_in('review_status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS])
            ->limit(1)
            ->get(db_prefix() . 'kt_manual_reviews')
            ->row_array();
        if ($existing) {
            return $existing;
        }

        $record = [
            'tenant_id' => $tenantId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'review_type' => $reviewType,
            'review_status' => strtoupper(trim((string) ($attributes['review_status'] ?? self::STATUS_OPEN))),
            'priority' => strtoupper(trim((string) ($attributes['priority'] ?? 'LOW'))),
            'assigned_staff_id' => ! empty($attributes['assigned_staff_id']) ? (int) $attributes['assigned_staff_id'] : null,
            'review_reason' => trim((string) ($attributes['review_reason'] ?? '')) ?: null,
            'source_system' => trim((string) ($attributes['source_system'] ?? 'salesflow_dms')) ?: 'salesflow_dms',
            'payload_json' => isset($attributes['payload_json']) ? (string) $attributes['payload_json'] : null,
            'resolution_note' => trim((string) ($attributes['resolution_note'] ?? '')) ?: null,
            'resolved_by' => ! empty($attributes['resolved_by']) ? (int) $attributes['resolved_by'] : null,
            'resolved_at' => $attributes['resolved_at'] ?? null,
            'created_at' => kt_integration_hub_now(),
            'updated_at' => kt_integration_hub_now(),
        ];

        $this->db()->insert(db_prefix() . 'kt_manual_reviews', $record);
        $record['id'] = (int) $this->db()->insert_id();
        if (empty($attributes['suppress_notification'])) {
            $this->publishNotification('created', $record);
        }

        return $record;
    }

    public function assignReview(int $id, int $staffId): array
    {
        $review = $this->find($id);
        if (! $review) {
            throw new RuntimeException('Manual review not found.');
        }

        $this->db()->where('id', $id)->update(db_prefix() . 'kt_manual_reviews', [
            'assigned_staff_id' => $staffId > 0 ? $staffId : null,
            'review_status' => self::STATUS_IN_PROGRESS,
            'updated_at' => kt_integration_hub_now(),
        ]);

        $updated = $this->find($id) ?? $review;
        $this->publishNotification('assigned', $updated);

        return $updated;
    }

    public function resolveReview(int $id, string $note, ?int $resolvedBy = null): array
    {
        $review = $this->find($id);
        if (! $review) {
            throw new RuntimeException('Manual review not found.');
        }

        $this->db()->where('id', $id)->update(db_prefix() . 'kt_manual_reviews', [
            'review_status' => self::STATUS_RESOLVED,
            'resolution_note' => trim($note) ?: null,
            'resolved_by' => $resolvedBy,
            'resolved_at' => kt_integration_hub_now(),
            'updated_at' => kt_integration_hub_now(),
        ]);

        $updated = $this->find($id) ?? $review;
        $this->publishNotification('resolved', $updated);

        return $updated;
    }

    public function closeReview(int $id): array
    {
        $review = $this->find($id);
        if (! $review) {
            throw new RuntimeException('Manual review not found.');
        }

        $this->db()->where('id', $id)->update(db_prefix() . 'kt_manual_reviews', [
            'review_status' => self::STATUS_CLOSED,
            'updated_at' => kt_integration_hub_now(),
        ]);

        $updated = $this->find($id) ?? $review;
        $this->publishNotification('closed', $updated);

        return $updated;
    }

    public function duplicateExists(array $attributes): bool
    {
        $tenantId = (int) ($attributes['tenant_id'] ?? 0);
        $entityType = trim((string) ($attributes['entity_type'] ?? ''));
        $entityId = (int) ($attributes['entity_id'] ?? 0);
        $reviewType = trim((string) ($attributes['review_type'] ?? ''));
        if ($tenantId <= 0 || $entityType === '' || $entityId <= 0 || $reviewType === '') {
            return false;
        }

        return $this->db()
            ->where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('review_type', $reviewType)
            ->where_in('review_status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS])
            ->count_all_results(db_prefix() . 'kt_manual_reviews') > 0;
    }

    protected function db()
    {
        $CI = &get_instance();
        if (! isset($CI->db)) {
            throw new RuntimeException('Database instance is not available.');
        }

        return $CI->db;
    }

    private function publishNotification(string $action, array $review): void
    {
        try {
            $service = new NotificationBridgeService();
            if ($action === 'created') {
                $service->publishManualReviewCreated($review);

                return;
            }

            if ($action === 'assigned') {
                $service->publishManualReviewAssigned($review);

                return;
            }

            $service->publishManualReviewResolved($review);
        } catch (Throwable $e) {
            $message = 'Manual review notification publish failed: ' . $e->getMessage();
            if (function_exists('log_message')) {
                log_message('warning', $message);
            } else {
                error_log($message);
            }
        }
    }
}
