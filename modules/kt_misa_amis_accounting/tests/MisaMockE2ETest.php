<?php

define('BASEPATH', true);
require_once dirname(__DIR__) . '/helpers/kt_misa_amis_accounting_helper.php';
require_once dirname(__DIR__) . '/libraries/MisaScope.php';
require_once dirname(__DIR__) . '/libraries/MisaSecretVault.php';
require_once dirname(__DIR__) . '/libraries/MisaMockTransport.php';
require_once dirname(__DIR__) . '/libraries/MisaTokenService.php';
require_once dirname(__DIR__) . '/libraries/MisaCallbackVerifier.php';
require_once dirname(__DIR__) . '/libraries/MisaCallbackInboxService.php';
require_once dirname(__DIR__) . '/libraries/MisaPayloadBuilders.php';
require_once dirname(__DIR__) . '/libraries/MisaPayloadValidator.php';
require_once dirname(__DIR__) . '/libraries/MisaDryRunService.php';
require_once dirname(__DIR__) . '/libraries/MisaRequestStateMachine.php';
require_once dirname(__DIR__) . '/libraries/MisaMockDispatcher.php';
require_once dirname(__DIR__) . '/libraries/MisaRequestLedgerService.php';

function misa_e2e_assert($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function misa_e2e_db($db, $sql, $params = [], $types = '') {
    $stmt = $db->prepare($sql); if (!$stmt) throw new RuntimeException($db->error);
    if ($params) { if (strlen($types) !== count($params)) $types = str_repeat('s', count($params)); $refs = [$types]; foreach ($params as $i => &$value) $refs[] = &$value; call_user_func_array([$stmt, 'bind_param'], $refs); }
    if (!$stmt->execute()) throw new RuntimeException($stmt->error);
    $id = $stmt->insert_id; $stmt->close(); return $id;
}

$dbName = getenv('MISA_TEST_DB') ?: 'khachtot_misa_runtime_20260730';
$db = new mysqli('127.0.0.1', 'root', '', $dbName);
if ($db->connect_errno) throw new RuntimeException('Cannot connect to isolated MISA database: ' . $db->connect_error);
$tables = ['kt_misa_audit_logs','kt_misa_attempts','kt_misa_request_items','kt_misa_requests','kt_misa_callback_inbox','kt_misa_mappings','kt_misa_connections'];
$p = 'tbl'; foreach ($tables as $table) $db->query("DELETE FROM {$p}{$table}");
$now = date('Y-m-d H:i:s'); $scope = new MisaScope(101, 201);
$vault = new MisaSecretVault(function ($v) { return 'enc:' . base64_encode($v); }, function ($v) { return base64_decode(substr($v, 4)); });
$transport = new MisaMockTransport();
$token = (new MisaTokenService($transport, $vault))->connect(['app_id' => 'mock-e2e-app', 'access_code' => 'mock-e2e-access', 'org_company_code' => 'mock-e2e-company']);
misa_e2e_assert($token['success'] && strpos($token['token_encrypted'], 'enc:') === 0, 'Flow A mock token exchange failed.');
$connectionId = misa_e2e_db($db, "INSERT INTO {$p}kt_misa_connections (tenant_id,company_id,name,environment,app_id,access_code_encrypted,access_token_encrypted,org_company_code,token_expires_at,token_expires_ticks,status,certification_level,last_connected_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", [101,201,'Mock E2E','mock','mock-e2e-app',$vault->encrypt('mock-e2e-access'),$token['token_encrypted'],'mock-e2e-company',$token['token_expires_at'],$token['token_expires_ticks'],'enabled','MOCK_VERIFIED',$now,$now,$now], 'iisssssssssssss');
$stored = $db->query("SELECT access_code_encrypted,access_token_encrypted FROM {$p}kt_misa_connections WHERE id={$connectionId}")->fetch_assoc();
misa_e2e_assert($stored['access_code_encrypted'] !== 'mock-e2e-access' && $stored['access_token_encrypted'] !== 'mock-access-token', 'Secrets were persisted in plaintext.');
$dry = (new MisaDryRunService(new MisaPayloadBuilders(), new MisaPayloadValidator()))->run('customer', ['code' => 'CUST-E2E-1', 'name' => 'Sanitized mock customer']);
misa_e2e_assert($dry['valid'] && !$dry['external_call'] && !$dry['remote_mapping_created'], 'Customer dry-run failed or attempted network/mapping.');
$ledger = new MisaRequestLedgerService(); $request = $ledger->draft($scope, $connectionId, 'customer', ['code' => 'CUST-E2E-1'], $dry['payload_hash']);
$request = $ledger->transition($request, 'queued');
$requestId = misa_e2e_db($db, "INSERT INTO {$p}kt_misa_requests (tenant_id,company_id,connection_id,operation,object_type,local_stable_key,idempotency_key,correlation_id,status,payload_hash,payload_redacted_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)", [$request['tenant_id'],$request['company_id'],$request['connection_id'],$request['operation'],$request['object_type'],$request['local_stable_key'],$request['idempotency_key'],$request['correlation_id'],$request['status'],$request['payload_hash'],json_encode($dry['payload']),$now,$now], 'iiissssssssss');
$state = (new MisaMockDispatcher())->dispatch(['status' => 'queued'], 'accepted'); misa_e2e_assert($state['status'] === 'awaiting_callback', 'Accepted request did not await callback.');
misa_e2e_db($db, "INSERT INTO {$p}kt_misa_attempts (tenant_id,company_id,connection_id,request_id,attempt_number,transport,outcome,started_at,finished_at,created_at) VALUES (?,?,?,?,?,'mock','accepted',?,?,?)", [101,201,$connectionId,$requestId,1,$now,$now,$now], 'iiiiisss');
misa_e2e_db($db, "INSERT INTO {$p}kt_misa_request_items (request_id,tenant_id,company_id,org_refid,result_status,success,processed_at,created_at,updated_at) VALUES (?,?,?,?,?,1,?,?,?)", [$requestId,101,201,'mock-e2e-ref','success',$now,$now,$now], 'iiisssss');
$data = json_encode([['org_refid' => 'mock-e2e-ref', 'success' => true]]); $signature = hash_hmac('sha256', $data, 'mock-e2e-app');
$record = (new MisaCallbackInboxService(new MisaCallbackVerifier()))->validateEnvelope(['app_id'=>'mock-e2e-app','org_company_code'=>'mock-e2e-company','data_type'=>1,'signature'=>$signature], $data, $scope, ['id'=>$connectionId,'app_id'=>'mock-e2e-app','org_company_code'=>'mock-e2e-company']);
$callbackId = misa_e2e_db($db, "INSERT INTO {$p}kt_misa_callback_inbox (tenant_id,company_id,connection_id,app_id,org_company_code,data_type,signature,signature_valid,payload_hash,raw_data,processing_status,received_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,1,?,?, 'processed',?,?,?)", [$record['tenant_id'],$record['company_id'],$record['connection_id'],$record['app_id'],$record['org_company_code'],$record['data_type'],$record['signature'],$record['payload_hash'],$record['raw_data'],$now,$now,$now], 'iiisssssssss');
$duplicateId = misa_e2e_db($db, "INSERT INTO {$p}kt_misa_callback_inbox (tenant_id,company_id,connection_id,app_id,org_company_code,data_type,signature,signature_valid,payload_hash,raw_data,processing_status,duplicate_of,received_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,1,?,?, 'duplicate',?,?,?,?)", [$record['tenant_id'],$record['company_id'],$record['connection_id'],$record['app_id'],$record['org_company_code'],$record['data_type'],$record['signature'],$record['payload_hash'],$record['raw_data'],$callbackId,$now,$now,$now], 'iiisssssssssss');
misa_e2e_assert($duplicateId > $callbackId, 'Duplicate callback was not retained as evidence.');
$counts = $db->query("SELECT (SELECT COUNT(*) FROM {$p}kt_misa_requests WHERE id={$requestId}) requests, (SELECT COUNT(*) FROM {$p}kt_misa_attempts WHERE request_id={$requestId}) attempts, (SELECT COUNT(*) FROM {$p}kt_misa_request_items WHERE request_id={$requestId}) items, (SELECT COUNT(*) FROM {$p}kt_misa_callback_inbox WHERE payload_hash='" . $db->real_escape_string($record['payload_hash']) . "') callbacks")->fetch_assoc();
misa_e2e_assert((int)$counts['requests'] === 1 && (int)$counts['attempts'] === 1 && (int)$counts['items'] === 1 && (int)$counts['callbacks'] === 2, 'Mock E2E ledger counts are incorrect.');
$db->query("DELETE FROM {$p}kt_misa_audit_logs");
echo "MisaMockE2ETest passed on {$dbName}; external_calls=0; network_calls=" . count($transport->calls()) . ".\n";
