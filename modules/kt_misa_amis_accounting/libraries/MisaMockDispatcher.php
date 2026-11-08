<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/MisaRequestStateMachine.php';

final class MisaMockDispatcher
{
    private $stateMachine;

    public function __construct(MisaRequestStateMachine $stateMachine = null)
    {
        $this->stateMachine = $stateMachine ?: new MisaRequestStateMachine();
    }

    public function dispatch(array $request, $outcome = 'accepted')
    {
        $state = (string) ($request['status'] ?? 'queued');
        if ($state === 'queued') $state = $this->stateMachine->transition($state, 'dispatching');
        if ($outcome === 'timeout') {
            $state = $this->stateMachine->transition($state, 'retryable_failed');
        } else {
            $state = $this->stateMachine->transition($state, 'accepted_by_misa');
            $state = $this->stateMachine->transition($state, 'awaiting_callback');
            if ($outcome === 'partial') $state = $this->stateMachine->transition($state, 'partially_succeeded');
        }
        return ['status' => $state, 'external_call' => false, 'transport' => 'mock', 'attempt_recorded' => true];
    }
}
