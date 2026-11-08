<?php

defined('BASEPATH') or exit('No direct script access allowed');

final class MisaRequestStateMachine
{
    private $transitions = [
        'draft' => ['queued', 'cancelled'],
        'queued' => ['dispatching', 'cancelled'],
        'dispatching' => ['accepted_by_misa', 'retryable_failed', 'permanent_failed'],
        'accepted_by_misa' => ['awaiting_callback'],
        'awaiting_callback' => ['succeeded', 'partially_succeeded', 'retryable_failed', 'permanent_failed', 'reconciliation_required'],
        'retryable_failed' => ['queued', 'cancelled'],
        'reconciliation_required' => ['awaiting_callback', 'succeeded', 'partially_succeeded', 'permanent_failed'],
        'succeeded' => [], 'partially_succeeded' => [], 'permanent_failed' => [], 'cancelled' => [],
    ];

    public function canTransition($from, $to)
    {
        return in_array((string) $to, $this->transitions[(string) $from] ?? [], true);
    }

    public function transition($from, $to)
    {
        if (!$this->canTransition($from, $to)) throw new LogicException('Invalid MISA request transition: ' . $from . ' -> ' . $to);
        return (string) $to;
    }

    public function transitions() { return $this->transitions; }
}

