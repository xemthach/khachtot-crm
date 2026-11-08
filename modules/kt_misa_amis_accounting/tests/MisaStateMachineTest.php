<?php

define('BASEPATH', true);
require_once dirname(__DIR__) . '/libraries/MisaRequestStateMachine.php';
$machine = new MisaRequestStateMachine();
$invalid = [['draft','succeeded'],['queued','succeeded'],['accepted_by_misa','succeeded'],['permanent_failed','dispatching'],['cancelled','dispatching']];
foreach ($invalid as $pair) if ($machine->canTransition($pair[0], $pair[1])) throw new RuntimeException('Invalid transition accepted: ' . implode(' -> ', $pair));
$valid = [['draft','queued'],['queued','dispatching'],['dispatching','accepted_by_misa'],['accepted_by_misa','awaiting_callback'],['awaiting_callback','succeeded'],['awaiting_callback','partially_succeeded'],['retryable_failed','queued']];
foreach ($valid as $pair) if (!$machine->canTransition($pair[0], $pair[1])) throw new RuntimeException('Valid transition rejected: ' . implode(' -> ', $pair));
echo "MisaStateMachineTest passed.\n";

