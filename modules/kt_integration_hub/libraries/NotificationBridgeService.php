<?php

defined('BASEPATH') or exit('No direct script access allowed');

class NotificationBridgeService
{
    public const STATUS_NEW = 'NEW';
    public const STATUS_READ = 'READ';
    public const STATUS_ACKED = 'ACKED';
    public const STATUS_RESOLVED = 'RESOLVED';

    public const EVENT_MANUAL_REVIEW_CREATED = 'MANUAL_REVIEW_CREATED';
    public const EVENT_MANUAL_REVIEW_ASSIGNED = 'MANUAL_REVIEW_ASSIGNED';
    public const EVENT_MANUAL_REVIEW_RESOLVED = 'MANUAL_REVIEW_RESOLVED';
    public const EVENT_INVOICE_STATUS_CHANGED = 'INVOICE_STATUS_CHANGED';
    public const EVENT_PAYMENT_STATUS_CHANGED = 'PAYMENT_STATUS_CHANGED';
    public const EVENT_ORDER_CANCELLED = 'ORDER_CANCELLED';
    public const EVENT_ORDER_LOCKED = 'ORDER_LOCKED';

    public function list(array $filters = []): array
    {
        $query = $this->db()->from(db_prefix() . 'kt_notification_events');

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', (int) $filters['tenant_id']);
        }

        if (! empty($filters['status'])) {
            $statuses = array_values(array_filter(array_map('trim', explode(',', (string) $filters['status']))));
            if ($statuses !== []) {
                $query->where_in('status', $this->normalizeStatuses($statuses));
            }
        }

        if (! empty($filters['event_type'])) {
            $query->where('event_type', trim((string) $filters['event_type']));
        }

        if (! empty($filters['target_type'])) {
            $query->where('target_type', trim((string) $filters['target_type']));
        }

        if (! empty($filters['target_id'])) {
            $query->where('target_id', (int) $filters['target_id']);
        }

        $rows = $query
            ->order_by('created_at', 'desc')
            ->order_by('id', 'desc')
            ->get()
            ->result_array();

        return array_map([$this, 'normalizeRow'], $rows);
    }

    public function find(int $id, ?int $tenantId = null): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $query = $this->db()->where('id', $id);
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $row = $query->get(db_prefix() . 'kt_notification_events')->row_array();

        return $row ? $this->normalizeRow($row) : null;
    }

    public function createNotification(array $attributes): array
    {
        $tenantId = (int) ($attributes['tenant_id'] ?? 0);
        $eventType = trim((string) ($attributes['event_type'] ?? ''));
        $eventCode = trim((string) ($attributes['event_code'] ?? ''));
        $title = trim((string) ($attributes['title'] ?? ''));
        $targetType = trim((string) ($attributes['target_type'] ?? 'tenant')) ?: 'tenant';
        $targetId = (int) ($attributes['target_id'] ?? $tenantId);

        if ($tenantId <= 0 || $eventType === '' || $eventCode === '' || $title === '') {
            throw new RuntimeException('Notification identity is required.');
        }

        $existing = $this->db()
            ->where('tenant_id', $tenantId)
            ->where('event_type', $eventType)
            ->where('event_code', $eventCode)
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->limit(1)
            ->get(db_prefix() . 'kt_notification_events')
            ->row_array();

        if ($existing) {
            return $this->normalizeRow($existing);
        }

        $now = kt_integration_hub_now();
        $record = [
            'tenant_id' => $tenantId,
            'event_type' => $eventType,
            'event_code' => $eventCode,
            'title' => $title,
            'message' => trim((string) ($attributes['message'] ?? '')) ?: null,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'priority' => $this->normalizePriority($attributes['priority'] ?? 'LOW'),
            'payload_json' => $this->encodePayload($attributes['payload_json'] ?? $attributes['payload'] ?? []),
            'status' => $this->normalizeStatus($attributes['status'] ?? self::STATUS_NEW),
            'read_at' => $attributes['read_at'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $this->db()->insert(db_prefix() . 'kt_notification_events', $record);
        $record['id'] = (int) $this->db()->insert_id();

        return $this->normalizeRow($record);
    }

    public function markRead(int $id, ?int $tenantId = null): array
    {
        $notification = $this->find($id, $tenantId);
        if (! $notification) {
            throw new RuntimeException('Notification not found.');
        }

        $now = kt_integration_hub_now();
        $this->db()->where('id', $id)->update(db_prefix() . 'kt_notification_events', [
            'status' => self::STATUS_READ,
            'read_at' => $notification['read_at'] ?? $now,
            'updated_at' => $now,
        ]);

        return $this->find($id, $tenantId) ?? $notification;
    }

    public function ack(int $id, ?int $tenantId = null): array
    {
        $notification = $this->find($id, $tenantId);
        if (! $notification) {
            throw new RuntimeException('Notification not found.');
        }

        $now = kt_integration_hub_now();
        $this->db()->where('id', $id)->update(db_prefix() . 'kt_notification_events', [
            'status' => self::STATUS_ACKED,
            'read_at' => $notification['read_at'] ?? $now,
            'updated_at' => $now,
        ]);

        return $this->find($id, $tenantId) ?? $notification;
    }

    public function resolveEvent(int $id, ?int $tenantId = null): array
    {
        $notification = $this->find($id, $tenantId);
        if (! $notification) {
            throw new RuntimeException('Notification not found.');
        }

        $now = kt_integration_hub_now();
        $this->db()->where('id', $id)->update(db_prefix() . 'kt_notification_events', [
            'status' => self::STATUS_RESOLVED,
            'read_at' => $notification['read_at'] ?? $now,
            'updated_at' => $now,
        ]);

        return $this->find($id, $tenantId) ?? $notification;
    }

    public function publishManualReviewCreated(array $review): ?array
    {
        return $this->createNotification([
            'tenant_id' => (int) ($review['tenant_id'] ?? 0),
            'event_type' => self::EVENT_MANUAL_REVIEW_CREATED,
            'event_code' => 'manual-review-created-' . (int) ($review['id'] ?? 0),
            'title' => 'Manual review created',
            'message' => (string) ($review['review_reason'] ?? 'Manual review created.'),
            'target_type' => 'tenant',
            'target_id' => (int) ($review['tenant_id'] ?? 0),
            'priority' => $this->priorityForReviewType((string) ($review['review_type'] ?? '')),
            'payload' => $review,
        ]);
    }

    public function publishManualReviewAssigned(array $review): ?array
    {
        return $this->createNotification([
            'tenant_id' => (int) ($review['tenant_id'] ?? 0),
            'event_type' => self::EVENT_MANUAL_REVIEW_ASSIGNED,
            'event_code' => 'manual-review-assigned-' . (int) ($review['id'] ?? 0),
            'title' => 'Manual review assigned',
            'message' => 'A manual review has been assigned.',
            'target_type' => 'tenant',
            'target_id' => (int) ($review['tenant_id'] ?? 0),
            'priority' => 'MEDIUM',
            'payload' => $review,
        ]);
    }

    public function publishManualReviewResolved(array $review): ?array
    {
        return $this->createNotification([
            'tenant_id' => (int) ($review['tenant_id'] ?? 0),
            'event_type' => self::EVENT_MANUAL_REVIEW_RESOLVED,
            'event_code' => 'manual-review-resolved-' . (int) ($review['id'] ?? 0),
            'title' => 'Manual review resolved',
            'message' => 'A manual review has been resolved.',
            'target_type' => 'tenant',
            'target_id' => (int) ($review['tenant_id'] ?? 0),
            'priority' => 'MEDIUM',
            'payload' => $review,
        ]);
    }

    public function publishInvoiceStatusChanged(int $tenantId, array $payload): ?array
    {
        $invoiceId = (int) ($payload['crm_invoice_id'] ?? 0);
        $status = strtolower(trim((string) ($payload['status'] ?? '')));
        $updatedAt = trim((string) ($payload['updated_at'] ?? ''));

        return $this->createNotification([
            'tenant_id' => $tenantId,
            'event_type' => self::EVENT_INVOICE_STATUS_CHANGED,
            'event_code' => 'invoice-status-' . $invoiceId . '-' . $status . '-' . sha1($updatedAt ?: json_encode($payload)),
            'title' => 'Invoice status changed',
            'message' => 'Invoice status changed to ' . $status . '.',
            'target_type' => 'tenant',
            'target_id' => $tenantId,
            'priority' => 'HIGH',
            'payload' => $payload,
        ]);
    }

    public function publishPaymentStatusChanged(int $tenantId, array $payload): ?array
    {
        $invoiceId = (int) ($payload['crm_invoice_id'] ?? 0);
        $paymentStatus = strtolower(trim((string) ($payload['payment_status'] ?? '')));
        $updatedAt = trim((string) ($payload['updated_at'] ?? ''));

        return $this->createNotification([
            'tenant_id' => $tenantId,
            'event_type' => self::EVENT_PAYMENT_STATUS_CHANGED,
            'event_code' => 'payment-status-' . $invoiceId . '-' . $paymentStatus . '-' . sha1($updatedAt ?: json_encode($payload)),
            'title' => 'Payment status changed',
            'message' => 'Payment status changed to ' . $paymentStatus . '.',
            'target_type' => 'tenant',
            'target_id' => $tenantId,
            'priority' => 'HIGH',
            'payload' => $payload,
        ]);
    }

    public function publishOrderCancelled(int $tenantId, array $payload): ?array
    {
        $orderId = (int) ($payload['order_id'] ?? 0);
        $updatedAt = trim((string) ($payload['updated_at'] ?? ''));

        return $this->createNotification([
            'tenant_id' => $tenantId,
            'event_type' => self::EVENT_ORDER_CANCELLED,
            'event_code' => 'order-cancelled-' . $orderId . '-' . sha1($updatedAt ?: json_encode($payload)),
            'title' => 'Order cancelled',
            'message' => 'An order cancellation was processed.',
            'target_type' => 'tenant',
            'target_id' => $tenantId,
            'priority' => 'MEDIUM',
            'payload' => $payload,
        ]);
    }

    public function publishOrderLocked(int $tenantId, array $payload): ?array
    {
        $orderId = (int) ($payload['order_id'] ?? 0);
        $updatedAt = trim((string) ($payload['updated_at'] ?? ''));

        return $this->createNotification([
            'tenant_id' => $tenantId,
            'event_type' => self::EVENT_ORDER_LOCKED,
            'event_code' => 'order-locked-' . $orderId . '-' . sha1($updatedAt ?: json_encode($payload)),
            'title' => 'Order locked',
            'message' => 'An order requires manual review or is locked.',
            'target_type' => 'tenant',
            'target_id' => $tenantId,
            'priority' => 'HIGH',
            'payload' => $payload,
        ]);
    }

    private function normalizeRow(array $row): array
    {
        $payload = $this->decodePayload((string) ($row['payload_json'] ?? ''));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'tenant_id' => (int) ($row['tenant_id'] ?? 0),
            'event_type' => (string) ($row['event_type'] ?? ''),
            'event_code' => (string) ($row['event_code'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'message' => (string) ($row['message'] ?? ''),
            'target_type' => (string) ($row['target_type'] ?? 'tenant'),
            'target_id' => (int) ($row['target_id'] ?? 0),
            'priority' => strtoupper((string) ($row['priority'] ?? 'LOW')),
            'status' => strtoupper((string) ($row['status'] ?? self::STATUS_NEW)),
            'payload_json' => (string) ($row['payload_json'] ?? ''),
            'payload' => $payload,
            'read_at' => $row['read_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function normalizePriority(string $priority): string
    {
        $priority = strtoupper(trim($priority));

        return in_array($priority, ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'], true) ? $priority : 'LOW';
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtoupper(trim($status));

        return in_array($status, [self::STATUS_NEW, self::STATUS_READ, self::STATUS_ACKED, self::STATUS_RESOLVED], true)
            ? $status
            : self::STATUS_NEW;
    }

    private function normalizeStatuses(array $statuses): array
    {
        return array_values(array_filter(array_map(function ($status) {
            return $this->normalizeStatus((string) $status);
        }, $statuses)));
    }

    private function encodePayload(mixed $payload): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            return json_encode(['value' => $payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function decodePayload(string $payloadJson): array
    {
        $payloadJson = trim($payloadJson);
        if ($payloadJson === '') {
            return [];
        }

        $decoded = json_decode($payloadJson, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function priorityForReviewType(string $reviewType): string
    {
        return match ($reviewType) {
            'PAYMENT_CONFLICT', 'INVOICE_LOCKED' => 'HIGH',
            'ORDER_CANCEL_PARTIALLY_PAID', 'ORDER_CANCEL_OVERDUE' => 'MEDIUM',
            default => 'LOW',
        };
    }

    protected function db()
    {
        $CI = &get_instance();
        if (! isset($CI->db)) {
            throw new RuntimeException('Database instance is not available.');
        }

        return $CI->db;
    }
}
