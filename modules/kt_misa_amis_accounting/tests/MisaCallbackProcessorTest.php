<?php

define('BASEPATH', true);
require_once dirname(__DIR__) . '/helpers/kt_misa_amis_accounting_helper.php';
require_once dirname(__DIR__) . '/libraries/MisaScope.php';
require_once dirname(__DIR__) . '/libraries/MisaCallbackProcessor.php';

if (!function_exists('db_prefix')) {
    function db_prefix() { return 'tbl'; }
}

function misa_processor_assert($condition, $message) { if (!$condition) throw new RuntimeException($message); }

$processor = new MisaCallbackProcessor(new stdClass());
$parsed = $processor->parseItems('[{"org_refid":"r-1","success":true,"remoteId":"remote-1"},{"org_refid":"r-2","success":false,"ErrorCode":"MockError","ErrorMessage":"secret detail"}]');
misa_processor_assert(count($parsed) === 2, 'Callback item parser did not preserve all items.');
misa_processor_assert($parsed[0]['org_refid'] === 'r-1' && $parsed[0]['remote_id'] === 'remote-1', 'Success item normalization failed.');
misa_processor_assert($parsed[1]['error_code'] === 'MockError', 'Failure error code normalization failed.');
misa_processor_assert($processor->aggregateStatuses([['result_status'=>'success','success'=>1],['result_status'=>'failed','success'=>0]]) === 'partially_succeeded', 'Mixed result aggregation failed.');
misa_processor_assert($processor->aggregateStatuses([['result_status'=>'pending'],['result_status'=>'success','success'=>1]]) === 'awaiting_callback', 'Pending result aggregation failed.');
misa_processor_assert($processor->aggregateStatuses([['result_status'=>'success','success'=>1],['result_status'=>'success','success'=>1]]) === 'succeeded', 'All-success aggregation failed.');
misa_processor_assert($processor->aggregateStatuses([['result_status'=>'retryable_failed','success'=>0]]) === 'retryable_failed', 'Retryable aggregation failed.');
misa_processor_assert($processor->aggregateStatuses([['result_status'=>'failed','success'=>0]]) === 'permanent_failed', 'Permanent aggregation failed.');
echo "MisaCallbackProcessorTest passed; parser_and_aggregation=pass.\n";
